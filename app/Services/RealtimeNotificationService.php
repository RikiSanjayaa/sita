<?php

namespace App\Services;

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

        if (DB::transactionLevel() > 0) {
            DB::afterCommit(static fn() => $user->notify($notification));

            return;
        }

        $user->notify($notification);
    }
}
