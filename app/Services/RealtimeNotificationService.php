<?php

namespace App\Services;

use App\Models\User;
use App\Notifications\RealtimeNotification;

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

        $user->notify(new RealtimeNotification([
            ...$payload,
            'preferenceKey' => $preferenceKey,
        ]));
    }
}
