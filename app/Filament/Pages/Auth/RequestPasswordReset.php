<?php

namespace App\Filament\Pages\Auth;

use App\Models\User;
use App\Notifications\Auth\AdminResetPasswordNotification;
use App\Services\SystemAuditLogService;
use DanHarrin\LivewireRateLimiting\Exceptions\TooManyRequestsException;
use Filament\Auth\Pages\PasswordReset\RequestPasswordReset as BaseRequestPasswordReset;
use Filament\Facades\Filament;
use Filament\Models\Contracts\FilamentUser;
use Filament\Support\Enums\Width;
use Illuminate\Auth\Events\PasswordResetLinkSent;
use Illuminate\Contracts\Support\Htmlable;
use Illuminate\Support\Facades\Password;
use LogicException;

class RequestPasswordReset extends BaseRequestPasswordReset
{
    public function request(): void
    {
        try {
            $this->rateLimit(2);
        } catch (TooManyRequestsException $exception) {
            $this->logResetPasswordRequest(
                email: $this->extractEmail($this->form->getState()['email'] ?? null),
                status: 'throttled',
                success: false,
                throttled: true,
            );

            $this->getRateLimitedNotification($exception)?->send();

            return;
        }

        $data = $this->form->getState();

        $status = Password::broker(Filament::getAuthPasswordBroker())->sendResetLink(
            $this->getCredentialsFromFormData($data),
            function (User $user, string $token): void {
                if (
                    ($user instanceof FilamentUser) &&
                    (! $user->canAccessPanel(Filament::getCurrentOrDefaultPanel()))
                ) {
                    return;
                }

                if (! method_exists($user, 'notify')) {
                    $userClass = $user::class;

                    throw new LogicException("Model [{$userClass}] does not have a [notify()] method.");
                }

                $notification = app(AdminResetPasswordNotification::class, ['token' => $token]);
                $notification->url = Filament::getResetPasswordUrl($token, $user);

                $user->notify($notification);

                event(new PasswordResetLinkSent($user));
            },
        );

        $email = $this->extractEmail($data['email'] ?? null);

        $this->logResetPasswordRequest(
            email: $email,
            status: $status,
            success: $status === Password::RESET_LINK_SENT,
        );

        if ($status !== Password::RESET_LINK_SENT) {
            $this->getFailureNotification($status)?->send();

            return;
        }

        $this->getSentNotification($status)?->send();

        $this->form->fill();
    }

    public function getTitle(): string|Htmlable
    {
        return 'Lupa password admin';
    }

    public function getHeading(): string|Htmlable|null
    {
        return 'Reset password panel admin';
    }

    public function getSubheading(): string|Htmlable|null
    {
        return 'Masukkan email akun admin untuk menerima tautan reset password.';
    }

    public function hasLogo(): bool
    {
        return false;
    }

    public function getMaxWidth(): Width|string|null
    {
        return Width::Large;
    }

    private function extractEmail(mixed $email): ?string
    {
        if (! is_string($email)) {
            return null;
        }

        $email = strtolower(trim($email));

        return $email !== '' ? $email : null;
    }

    private function logResetPasswordRequest(
        ?string $email,
        string $status,
        bool $success,
        bool $throttled = false,
    ): void {
        app(SystemAuditLogService::class)->record(
            user: $email !== null ? User::query()->where('email', $email)->first() : null,
            eventType: 'password_reset_requested',
            label: 'Permintaan reset password',
            description: $throttled
                ? 'Permintaan reset password admin dibatasi karena terlalu sering.'
                : ($success
                    ? 'Tautan reset password berhasil dikirim melalui panel admin.'
                    : 'Ada permintaan reset password melalui panel admin.'),
            request: request(),
            email: $email,
            payload: [
                'source' => 'admin',
                'status' => $status,
                'success' => $success,
                'throttled' => $throttled,
            ],
        );
    }
}
