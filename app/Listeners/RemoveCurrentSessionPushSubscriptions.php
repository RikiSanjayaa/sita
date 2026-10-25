<?php

namespace App\Listeners;

use App\Models\User;
use Illuminate\Auth\Events\Logout;
use Illuminate\Http\Request;

/**
 * Removes only the push subscriptions enrolled from the session that is logging out.
 */
class RemoveCurrentSessionPushSubscriptions
{
    public function __construct(private readonly Request $request) {}

    public function handle(Logout $event): void
    {
        $user = $event->user;

        if (! $user instanceof User || ! $this->request->hasSession()) {
            return;
        }

        $user->pushSubscriptions()
            ->where('session_id', hash('sha256', $this->request->session()->getId()))
            ->delete();
    }
}
