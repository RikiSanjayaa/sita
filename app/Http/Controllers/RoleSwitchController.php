<?php

namespace App\Http\Controllers;

use App\Enums\AppRole;
use App\Services\SystemAuditLogService;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class RoleSwitchController extends Controller
{
    public function __invoke(Request $request, SystemAuditLogService $systemAuditLogService): \Symfony\Component\HttpFoundation\Response
    {
        $validated = $request->validate([
            'role' => ['required', 'string', Rule::in(AppRole::uiValues())],
        ]);

        $user = $request->user();
        $role = $validated['role'];

        abort_unless($user->hasRole($role), 403);

        $request->session()->put('active_role', $role);

        if ($user->last_active_role !== $role) {
            $user->forceFill(['last_active_role' => $role])->save();
        }

        $systemAuditLogService->record(
            user: $user,
            eventType: 'role_switched',
            label: 'Peran diganti',
            description: 'Pengguna beralih ke peran '.match (AppRole::from($role)) {
                AppRole::Mahasiswa => 'Mahasiswa',
                AppRole::Dosen => 'Dosen',
                AppRole::Admin => 'Admin',
                AppRole::SuperAdmin => 'Super Admin',
                AppRole::Penguji => 'Penguji',
            }.'.',
            request: $request,
            payload: [
                'role' => $role,
            ],
        );

        $dashboardRouteName = AppRole::from($role)->dashboardRouteName();

        if ($role === AppRole::Admin->value) {
            return \Inertia\Inertia::location(filament()->getPanel('admin')?->getUrl() ?? url('/admin'));
        }

        return redirect()->route($dashboardRouteName);
    }
}
