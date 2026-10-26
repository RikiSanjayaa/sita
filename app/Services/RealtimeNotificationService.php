<?php

namespace App\Services;

use App\Jobs\SendBrowserNotification;
use App\Models\User;
use App\Notifications\RealtimeNotification;
use Illuminate\Support\Facades\DB;

class RealtimeNotificationService
{
    /**
     * @param  array<string, mixed>  $payload
     */
    public function notifyUser(User $user, string $preferenceKey, array $payload): void
    {
        if (! in_array($preferenceKey, User::NOTIFICATION_PREFERENCE_KEYS, true)) {
            return;
        }

        $preferences = $user->resolvedNotificationPreferences();

        if (($preferences[$preferenceKey] ?? true) !== true) {
            return;
        }

        $notification = new RealtimeNotification([
            ...$payload,
            'preferenceKey' => $preferenceKey,
        ]);

        $deliver = function () use ($user, $notification, $preferenceKey, $payload): void {
            $user->notify($notification);

            $this->queueBrowserPush($user, $notification, $preferenceKey, $payload);
        };

        if (DB::transactionLevel() > 0) {
            DB::afterCommit($deliver);

            return;
        }

        $deliver();
    }

    /**
     * Push is async and runs only after the notification is persisted.
     *
     * @param  array<string, mixed>  $payload
     */
    private function queueBrowserPush(User $user, RealtimeNotification $notification, string $preferenceKey, array $payload): void
    {
        if (! is_string($notification->id) || ! SendBrowserNotification::shouldQueueFor($user, $preferenceKey)) {
            return;
        }

        // The raw url identifies the stored notification; it is sanitized only when the push is built.
        $url = is_string($payload['url'] ?? null) ? $payload['url'] : '';

        dispatch(new SendBrowserNotification($user->id, $notification->id, $preferenceKey, $url));
    }
}
