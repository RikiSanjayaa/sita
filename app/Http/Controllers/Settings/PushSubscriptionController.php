<?php

namespace App\Http\Controllers\Settings;

use App\Http\Controllers\Controller;
use App\Http\Requests\Settings\PushSubscriptionEndpointRequest;
use App\Http\Requests\Settings\StorePushSubscriptionRequest;
use App\Models\User;
use App\Support\WebPushConfig;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\DB;
use Minishlink\WebPush\ContentEncoding;
use NotificationChannels\WebPush\PushSubscription;

class PushSubscriptionController extends Controller
{
    public function index(): JsonResponse
    {
        $enabled = WebPushConfig::enabled();
        $publicKey = $enabled ? config('webpush.vapid.public_key') : null;

        return response()->json([
            'enabled' => $enabled,
            'publicKey' => is_string($publicKey) ? $publicKey : null,
        ]);
    }

    public function status(PushSubscriptionEndpointRequest $request): JsonResponse
    {
        $user = $this->user($request);

        $subscribed = $user->pushSubscriptions()
            ->where('endpoint', $request->endpoint())
            ->where('session_id', self::sessionHash($request))
            ->exists();

        return response()->json(['subscribed' => $subscribed]);
    }

    public function store(StorePushSubscriptionRequest $request): JsonResponse
    {
        if (! WebPushConfig::enabled()) {
            return response()->json(['message' => 'Notifikasi push belum tersedia.'], 503);
        }

        $user = $this->user($request);
        $endpoint = $request->endpoint();
        $attributes = [
            'public_key' => (string) $request->validated('keys.p256dh'),
            'auth_token' => (string) $request->validated('keys.auth'),
            'content_encoding' => ContentEncoding::aes128gcm,
            'session_id' => self::sessionHash($request),
        ];

        try {
            $stored = DB::transaction(fn (): bool => $this->upsertOwned($user, $endpoint, $attributes));
        } catch (UniqueConstraintViolationException) {
            // A concurrent request inserted the endpoint first; re-evaluate ownership once.
            $stored = DB::transaction(fn (): bool => $this->upsertOwned($user, $endpoint, $attributes));
        }

        if (! $stored) {
            return response()->json(['message' => 'Endpoint push sudah dipakai akun lain.'], 409);
        }

        return response()->json(['subscribed' => true]);
    }

    public function destroy(PushSubscriptionEndpointRequest $request): JsonResponse
    {
        $this->user($request)->pushSubscriptions()
            ->where('endpoint', $request->endpoint())
            ->where('session_id', self::sessionHash($request))
            ->delete();

        return response()->json(['subscribed' => false]);
    }

    /**
     * Insert or refresh a row owned by $user. Never takes over another owner's endpoint.
     *
     * @param  array<string, mixed>  $attributes
     */
    private function upsertOwned(User $user, string $endpoint, array $attributes): bool
    {
        /** @var class-string<PushSubscription> $model */
        $model = Config::string('webpush.model');

        $existing = $model::query()->where('endpoint', $endpoint)->lockForUpdate()->first();

        if ($existing === null) {
            $user->pushSubscriptions()->make()->forceFill(['endpoint' => $endpoint, ...$attributes])->save();

            return true;
        }

        if (! $user->ownsPushSubscription($existing)) {
            return false;
        }

        $existing->forceFill($attributes)->save();

        return true;
    }

    private function user(Request $request): User
    {
        /** @var User $user */
        $user = $request->user();

        return $user;
    }

    public static function sessionHash(Request $request): string
    {
        return hash('sha256', $request->session()->getId());
    }
}
