<?php

namespace App\Http\Middleware;

use App\Models\User;
use App\Services\SystemAuditLogService;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Support\ViewErrorBag;
use Symfony\Component\HttpFoundation\Response;

class LogPasswordResetRequest
{
    public function __construct(
        private readonly SystemAuditLogService $systemAuditLogService,
    ) {}

    /**
     * @param  Closure(Request): Response  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $response = $next($request);

        if (! $request->isMethod('post') || ! $request->routeIs('password.email')) {
            return $response;
        }

        $email = $this->normalizeEmail($request->input('email'));
        $status = $request->session()->get('status');
        $errors = $request->session()->get('errors');
        $hasEmailError = $errors instanceof ViewErrorBag
            ? $errors->getBag('default')->has('email')
            : false;
        $wasSuccessful = is_string($status) && $status !== '' && (! $hasEmailError);

        $this->systemAuditLogService->record(
            user: $email !== null ? User::query()->where('email', $email)->first() : null,
            eventType: 'password_reset_requested',
            label: 'Permintaan reset password',
            description: $wasSuccessful
                ? 'Tautan reset password berhasil dikirim melalui portal pengguna.'
                : 'Ada permintaan reset password melalui portal pengguna.',
            request: $request,
            email: $email,
            payload: [
                'source' => 'web',
                'status' => is_string($status) ? $status : null,
                'success' => $wasSuccessful,
                'response_status' => $response->getStatusCode(),
            ],
        );

        return $response;
    }

    private function normalizeEmail(mixed $email): ?string
    {
        if (! is_string($email)) {
            return null;
        }

        $email = Str::lower(trim($email));

        return $email !== '' ? $email : null;
    }
}
