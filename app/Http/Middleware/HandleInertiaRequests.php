<?php

namespace App\Http\Middleware;

use App\Enums\AppRole;
use App\Models\KaprodiAssignment;
use App\Models\User;
use App\Services\ChatUnreadService;
use App\Services\MentorshipTicketService;
use App\Support\AcademicTerminology;
use App\Support\WitaDateTime;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
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
        $kaprodiCapabilities = null;

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

            if ($activeRole === 'kaprodi') {
                $user->loadMissing('kaprodiAssignment');
                $kaprodiCapabilities = $user->kaprodiAssignment instanceof KaprodiAssignment
                    ? KaprodiAssignment::normalizeCapabilities($user->kaprodiAssignment->capabilities)
                    : KaprodiAssignment::defaultCapabilities();
            }
        }

        return [
            ...parent::share($request),
            'name' => config('app.name'),
            'flash' => [
                'id' => fn (): ?string => $request->session()->hasAny(['success', 'error'])
                    ? (string) Str::uuid()
                    : null,
                'success' => fn (): ?string => $request->session()->get('success'),
                'error' => fn (): ?string => $request->session()->get('error'),
            ],
            'auth' => [
                'user' => $userPayload,
                'activeRole' => $activeRole,
                'availableRoles' => $availableRoles,
                'kaprodiCapabilities' => $kaprodiCapabilities,
            ],
            'sidebarOpen' => ! $request->hasCookie('sidebar_state') || $request->cookie('sidebar_state') === 'true',
            'notificationSettings' => $notificationSettings,
            'notifications' => $notifications,
            'unreadNotificationCount' => $unreadNotificationCount,
            'unreadMessageCount' => fn (): int => $user === null
                ? 0
                : app(ChatUnreadService::class)->unreadMessageCount($user, $activeRole),
            'academicTerminology' => $user?->hasRole('mahasiswa')
                ? AcademicTerminology::forStudent($user)
                : AcademicTerminology::neutral(),
            'phonePrompt' => $this->phonePrompt($user, $activeRole),
            'mentorshipTicket' => $this->mentorshipTicket($user, $activeRole),
        ];
    }

    /**
     * Shares the active mahasiswa's bimbingan ticket status with the app shell.
     *
     * @return array{suspended: bool, validUntil: string, message: ?string}|null
     */
    private function mentorshipTicket(?User $user, ?string $activeRole): ?array
    {
        if ($user === null || $activeRole !== AppRole::Mahasiswa->value) {
            return null;
        }

        $project = app(MentorshipTicketService::class)->activeProjectForStudent($user->id);

        if ($project === null || $project->mentorship_valid_until === null) {
            return null;
        }

        $suspended = $project->isMentorshipSuspended();

        return [
            'suspended' => $suspended,
            'validUntil' => WitaDateTime::format($project->mentorship_valid_until, 'd M Y', false),
            'message' => $suspended ? MentorshipTicketService::suspensionMessage($project) : null,
        ];
    }

    /**
     * Tells the app shell whether a mahasiswa must enter or confirm a phone number.
     *
     * @return array{mode: string, phoneNumber: ?string}|null
     */
    private function phonePrompt(?User $user, ?string $activeRole): ?array
    {
        if ($user === null || $activeRole !== AppRole::Mahasiswa->value) {
            return null;
        }

        if (blank($user->phone_number)) {
            return ['mode' => 'required', 'phoneNumber' => null];
        }

        if ($user->phone_number_confirmed_at === null) {
            return ['mode' => 'confirm', 'phoneNumber' => $user->phone_number];
        }

        return null;
    }
}
