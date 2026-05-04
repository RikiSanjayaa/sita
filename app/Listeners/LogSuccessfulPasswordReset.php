<?php

namespace App\Listeners;

use App\Models\User;
use App\Services\SystemAuditLogService;
use Illuminate\Auth\Events\PasswordReset;
use Illuminate\Http\Request;

class LogSuccessfulPasswordReset
{
    public function __construct(
        private readonly SystemAuditLogService $systemAuditLogService,
        private readonly Request $request,
    ) {}

    public function handle(PasswordReset $event): void
    {
        if (! $event->user instanceof User) {
            return;
        }

        $this->systemAuditLogService->record(
            user: $event->user,
            eventType: 'password_reset_success',
            label: 'Reset password berhasil',
            description: 'Password akun berhasil diperbarui melalui fitur reset password.',
            request: $this->request,
            payload: [
                'source' => $this->resolveSource(),
            ],
        );
    }

    private function resolveSource(): string
    {
        if ($this->request->routeIs('password.update')) {
            return 'web';
        }

        if ($this->request->is('livewire/update')) {
            return 'admin';
        }

        return 'unknown';
    }
}
