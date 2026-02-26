<?php

namespace App\Http\Middleware;

use Illuminate\Http\Request;
use Inertia\Middleware;

class HandleInertiaRequests extends Middleware
{
    /**
     * The root template that's loaded on the first page visit.
     *
     * @see https://inertiajs.com/server-side-setup#root-template
     *
     * @var string
     */
    protected $rootView = 'app';

    /**
     * Determines the current asset version.
     *
     * @see https://inertiajs.com/asset-versioning
     */
    public function version(Request $request): ?string
    {
        return parent::version($request);
    }

    /**
     * Define the props that are shared by default.
     *
     * @see https://inertiajs.com/shared-data
     *
     * @return array<string, mixed>
     */
    public function share(Request $request): array
    {
        $user = $request->user();
        $activeRole = null;
        $availableRoles = [];
        $userPayload = null;
        $notificationSettings = null;
        $notifications = [];
        $unreadNotificationCount = 0;

        if ($user !== null) {
            $user->loadMissing('roles');

            $activeRole = $user->resolveActiveRole(
                $request->session()->get('active_role'),
            );
            $availableRoles = $user->availableRoles();
            $request->session()->put('active_role', $activeRole);

            $userPayload = $user->toArray();
            $userPayload['roles'] = $user->roleNames();

            $notificationSettings = [
                'browserNotifications' => (bool) $user->browser_notifications_enabled,
                ...$user->resolvedNotificationPreferences(),
            ];

            $notifications = $user->notifications()
                ->latest()
                ->limit(15)
                ->get()
                ->map(fn ($notification): array => [
                    'id' => $notification->id,
                    'title' => (string) data_get($notification->data, 'title', 'Notifikasi'),
                    'description' => (string) data_get($notification->data, 'description', ''),
                    'time' => $notification->created_at->diffForHumans(),
                    'icon' => (string) data_get($notification->data, 'icon', 'bell'),
                    'unread' => $notification->read_at === null,
                    'url' => data_get($notification->data, 'url'),
                ])
                ->all();

            $unreadNotificationCount = $user->unreadNotifications()->count();
        }

        return [
            ...parent::share($request),
            'name' => config('app.name'),
            'auth' => [
                'user' => $userPayload,
                'activeRole' => $activeRole,
                'availableRoles' => $availableRoles,
            ],
            'sidebarOpen' => ! $request->hasCookie('sidebar_state') || $request->cookie('sidebar_state') === 'true',
            'notificationSettings' => $notificationSettings,
            'notifications' => $notifications,
            'unreadNotificationCount' => $unreadNotificationCount,
        ];
    }
}
