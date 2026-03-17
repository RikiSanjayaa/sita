<?php

namespace App\Listeners;

use App\Services\SystemAuditLogService;
use Illuminate\Auth\Events\Failed;
use Illuminate\Http\Request;

class LogFailedLogin
{
    public function __construct(
        private readonly SystemAuditLogService $systemAuditLogService,
        private readonly Request $request,
    ) {}

    public function handle(Failed $event): void
    {
        $email = is_string($event->credentials['email'] ?? null)
            ? $event->credentials['email']
            : null;

        $this->systemAuditLogService->record(
            user: $event->user,
            eventType: 'login_failed',
            label: 'Login gagal',
            description: 'Ada percobaan login yang gagal.',
            request: $this->request,
            email: $email,
        );
    }
}
