<?php

namespace App\Services;

use App\Models\SystemAuditLog;
use App\Models\User;
use Illuminate\Http\Request;

class SystemAuditLogService
{
    /**
     * @param  array<string, mixed>|null  $payload
     */
    public function record(
        ?User $user,
        string $eventType,
        string $label,
        ?string $description = null,
        ?Request $request = null,
        ?string $email = null,
        ?array $payload = null,
    ): void {
        SystemAuditLog::query()->create([
            'user_id' => $user?->id,
            'event_type' => $eventType,
            'label' => $label,
            'description' => $description,
            'email' => $email ?? $user?->email,
            'ip_address' => $request?->ip(),
            'user_agent' => $request?->userAgent(),
            'url' => $request?->fullUrl(),
            'payload' => $payload,
            'occurred_at' => now(),
        ]);
    }
}
