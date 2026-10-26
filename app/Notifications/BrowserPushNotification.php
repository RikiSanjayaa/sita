<?php

namespace App\Notifications;

use Illuminate\Notifications\Notification;
use NotificationChannels\WebPush\WebPushChannel;
use NotificationChannels\WebPush\WebPushMessage;

/**
 * Lock-screen safe browser push. The content is intentionally generic: it never carries
 * names, message contents, or panel titles, only a same-origin route to open.
 */
class BrowserPushNotification extends Notification
{
    public const TITLE = 'SITA';

    public const BODY = 'Ada pembaruan di SITA. Buka aplikasi untuk melihatnya.';

    public const ICON = '/apple-touch-icon.png';

    public const FALLBACK_URL = '/';

    public function __construct(
        private readonly int $userId,
        private readonly string $notificationId,
        private readonly string $url,
        private readonly string $tag,
        private readonly int $ttl,
    ) {}

    /**
     * @return array<int, class-string>
     */
    public function via(object $notifiable): array
    {
        return [WebPushChannel::class];
    }

    public function toWebPush(object $notifiable, Notification $notification): WebPushMessage
    {
        return (new WebPushMessage)
            ->title(self::TITLE)
            ->body(self::BODY)
            ->tag($this->tag)
            ->icon(self::ICON)
            ->data([
                'url' => self::safeUrl($this->url),
                'userId' => $this->userId,
                'notificationId' => $this->notificationId,
            ])
            ->options(['TTL' => $this->ttl, 'urgency' => 'normal']);
    }

    /**
     * Only a plain same-origin path may reach the service worker.
     */
    public static function safeUrl(mixed $url): string
    {
        if (! is_string($url) || $url === '' || strlen($url) > 512) {
            return self::FALLBACK_URL;
        }

        if ($url[0] !== '/' || str_starts_with($url, '//') || str_contains($url, '\\') || preg_match('/[\x00-\x20\x7f]/', $url) === 1) {
            return self::FALLBACK_URL;
        }

        return $url;
    }
}
