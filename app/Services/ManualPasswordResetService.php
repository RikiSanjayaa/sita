<?php

namespace App\Services;

use App\Enums\AppRole;
use App\Models\User;
use App\Notifications\Auth\AdminResetPasswordNotification;
use Filament\Facades\Filament;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Auth\Events\PasswordResetLinkSent;
use Illuminate\Auth\Passwords\PasswordBroker;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Password;

class ManualPasswordResetService
{
    public function __construct(
        private readonly SystemAuditLogService $systemAuditLogService,
    ) {}

    public function canSendResetLink(User $actor, User $target): bool
    {
        if ($actor->is($target)) {
            return false;
        }

        if (! $actor->hasAnyRole([AppRole::Admin, AppRole::SuperAdmin])) {
            return false;
        }

        if ($target->hasAnyRole([AppRole::Admin, AppRole::SuperAdmin])) {
            return $actor->hasRole(AppRole::SuperAdmin);
        }

        return true;
    }

    /**
     * @throws AuthorizationException
     */
    public function sendResetLink(User $target, User $actor, ?Request $request = null): void
    {
        if (! $this->canSendResetLink($actor, $target)) {
            throw new AuthorizationException('Anda tidak diizinkan mengirim reset password untuk akun ini.');
        }

        /** @var PasswordBroker $passwordBroker */
        $passwordBroker = Password::broker(config('auth.defaults.passwords'));

        $token = $passwordBroker->createToken($target);

        if ($target->hasAnyRole([AppRole::Admin, AppRole::SuperAdmin])) {
            $this->sendAdminResetNotification($target, $token);
        } else {
            $target->sendPasswordResetNotification($token);
        }

        event(new PasswordResetLinkSent($target));

        $this->systemAuditLogService->record(
            user: $target,
            eventType: 'password_reset_link_sent_by_admin',
            label: 'Link reset password dikirim admin',
            description: 'Admin mengirim tautan reset password manual dari halaman manajemen pengguna.',
            request: $request,
            payload: [
                'source' => 'admin_user_management',
                'actor_user_id' => $actor->id,
                'actor_email' => $actor->email,
                'target_role' => $target->roles->pluck('name')->first(),
                'delivery_flow' => $target->hasAnyRole([AppRole::Admin, AppRole::SuperAdmin]) ? 'admin' : 'web',
            ],
        );
    }

    private function sendAdminResetNotification(User $target, string $token): void
    {
        $panel = Filament::getPanel('admin');

        if ($panel === null) {
            throw new AuthorizationException('Panel admin tidak tersedia untuk mengirim reset password.');
        }

        $notification = app(AdminResetPasswordNotification::class, ['token' => $token]);
        $notification->url = $panel->getResetPasswordUrl($token, $target);

        $target->notify($notification);
    }
}
