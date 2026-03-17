<?php

namespace App\Listeners;

use App\Services\SystemAuditLogService;
use Illuminate\Auth\Events\Login;
use Illuminate\Http\Request;

class LogSuccessfulLogin
{
    public function __construct(
        private readonly SystemAuditLogService $systemAuditLogService,
        private readonly Request $request,
    ) {}

    public function handle(Login $event): void
    {
        $this->systemAuditLogService->record(
            user: $event->user,
            eventType: 'login_success',
            label: 'Login berhasil',
            description: 'Pengguna berhasil masuk ke sistem.',
            request: $this->request,
        );
    }
}
