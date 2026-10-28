<?php

namespace App\Http\Middleware;

use Illuminate\Broadcasting\BroadcastController;
use Illuminate\Session\Middleware\StartSession;

class StartSessionWithoutBackgroundWrites extends StartSession
{
    protected function saveSession(mixed $request): void
    {
        $action = ltrim($request->route()?->getActionName() ?? '', '\\');
        $session = $request->session();
        $sameSession = $request->cookies->get($session->getName()) === $session->getId();

        // Do persist a new/rotated login session (for example, restored by a remember cookie).
        // Stable background readers must not consume flash or overwrite a newer foreground snapshot.
        if ($sameSession && ($request->routeIs('chat.unread') || in_array($action, [
            BroadcastController::class.'@authenticate',
            BroadcastController::class.'@authenticateUser',
        ], true))) {
            return;
        }

        parent::saveSession($request);
    }
}
