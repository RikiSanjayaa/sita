<?php

namespace App\Listeners;

use App\Services\SystemAuditLogService;
use Illuminate\Auth\Events\Logout;
use Illuminate\Http\Request;

class LogSuccessfulLogout
{
    public function __construct(
        private readonly SystemAuditLogService $systemAuditLogService,
        private readonly Request $request,
    ) {}

    public function handle(Logout $event): void
    {
        $user = $event->user;

        if ($user === null) {
            return;
        }

        $this->systemAuditLogService->record(
            user: $user,
            eventType: 'logout',
            label: 'Logout',
            description: 'Pengguna keluar dari sistem.',
            request: $this->request,
        );
    }
}
