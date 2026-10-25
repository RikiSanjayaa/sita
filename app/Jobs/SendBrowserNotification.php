<?php

namespace App\Jobs;

use App\Models\User;
use App\Notifications\BrowserPushNotification;
use App\Notifications\RealtimeNotification;
use App\Support\WebPushConfig;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldBeUniqueUntilProcessing;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Notifications\DatabaseNotification;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Notification;

/**
 * Sends one OS-level push after the database/Echo notification was persisted.
 *
 * Chat messages ("pesanBaru") are delayed and coalesced per user + chat URL: a burst
 * enqueues a single job, which at execution pushes for the latest unread stored
 * notification of that chat. Everything else is pushed immediately.
 *
 * Latest is decided by created_at, then id; RealtimeNotification ids are time-ordered UUIDv7,
 * so notifications created within the same second still sort in creation order.
 */
class SendBrowserNotification implements ShouldBeUniqueUntilProcessing, ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public const CHAT_PREFERENCE_KEY = 'pesanBaru';

    public const CHAT_DELAY_SECONDS = 60;

    public const CHAT_TTL = 900;

    public const ACADEMIC_TTL = 3600;

    public int $tries = 1;

    public int $timeout = 60;

    public int $uniqueFor = 180;

    public function __construct(
        public readonly int $userId,
        public readonly string $notificationId,
        public readonly string $preferenceKey,
        public readonly string $url,
    ) {
        // Always async, regardless of QUEUE_CONNECTION (tests and local dev run it as sync).
        $this->onConnection((string) config('webpush.queue_connection', 'database'));
        $this->onQueue((string) config('webpush.queue', 'default'));

        if ($this->isChat()) {
            $this->delay(self::CHAT_DELAY_SECONDS);
        }
    }

    public function isChat(): bool
    {
        return $this->preferenceKey === self::CHAT_PREFERENCE_KEY;
    }

    public function uniqueId(): string
    {
        return $this->isChat()
            ? sprintf('browser-push:chat:%d:%s', $this->userId, sha1($this->url))
            : sprintf('browser-push:%s', $this->notificationId);
    }

    /**
     * Cheap gate shared by the enqueue site, so nothing is queued for users that cannot receive a push.
     */
    public static function shouldQueueFor(User $user, string $preferenceKey): bool
    {
        return WebPushConfig::enabled()
            && $user->browser_notifications_enabled === true
            && ($user->resolvedNotificationPreferences()[$preferenceKey] ?? true) === true
            && $user->pushSubscriptions()->exists();
    }

    public function handle(): void
    {
        $user = User::query()->find($this->userId);

        if (! $user instanceof User || ! self::shouldQueueFor($user, $this->preferenceKey)) {
            return;
        }

        $stored = $this->resolveStoredNotification($user);

        if (! $stored instanceof DatabaseNotification) {
            return;
        }

        $ttl = $this->isChat() ? self::CHAT_TTL : self::ACADEMIC_TTL;

        // The provider TTL only starts once we hand the push over. Do not surface a backlog that already outlived it.
        if ($stored->created_at === null || $stored->created_at->diffInSeconds(now(), true) > $ttl) {
            return;
        }

        $url = BrowserPushNotification::safeUrl($this->url);

        Notification::sendNow($user, new BrowserPushNotification(
            userId: $user->id,
            notificationId: (string) $stored->getKey(),
            url: $url,
            tag: $this->isChat()
                ? sprintf('chat:%d:%s', $user->id, substr(sha1($url), 0, 16))
                : sprintf('notification:%s', $stored->getKey()),
            ttl: $ttl,
        ));
    }

    private function resolveStoredNotification(User $user): ?DatabaseNotification
    {
        $query = $user->unreadNotifications()
            ->where('type', RealtimeNotification::class);

        if (! $this->isChat()) {
            return $query->whereKey($this->notificationId)->first();
        }

        return $query
            ->where('data->preferenceKey', self::CHAT_PREFERENCE_KEY)
            ->where('data->url', $this->url)
            ->orderByDesc('created_at')
            ->orderByDesc('id')
            ->first();
    }
}
