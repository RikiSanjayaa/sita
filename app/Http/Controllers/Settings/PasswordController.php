<?php

namespace App\Http\Controllers\Settings;

use App\Http\Controllers\Controller;
use App\Http\Requests\Settings\PasswordUpdateRequest;
use App\Models\User;
use App\Services\SystemAuditLogService;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;
use Inertia\Response;

class PasswordController extends Controller
{
    /**
     * Show the user's password settings page.
     */
    public function edit(): Response
    {
        return Inertia::render('settings/password');
    }

    /**
     * Update the user's password.
     */
    public function update(PasswordUpdateRequest $request): RedirectResponse
    {
        /** @var User $user */
        $user = $request->user();

        $user->update([
            'password' => $request->password,
        ]);

        app(SystemAuditLogService::class)->record(
            user: $user,
            eventType: 'password_changed_by_user',
            label: 'Password akun diperbarui',
            description: 'Pengguna mengganti password akun melalui halaman pengaturan password.',
            request: $request,
            payload: [
                'source' => 'settings_password',
            ],
        );

        return back();
    }
}
