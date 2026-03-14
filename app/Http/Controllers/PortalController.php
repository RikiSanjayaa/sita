<?php

namespace App\Http\Controllers;

use App\Enums\AppRole;
use Illuminate\Http\Request;

class PortalController extends Controller
{
    public function __invoke(Request $request): \Symfony\Component\HttpFoundation\Response
    {
        $user = $request->user();
        $activeRole = $user->resolveActiveRole($request->session()->get('active_role'));

        $request->session()->put('active_role', $activeRole);

        if ($user->last_active_role !== $activeRole) {
            $user->forceFill(['last_active_role' => $activeRole])->save();
        }

        $role = AppRole::tryFrom($activeRole) ?? AppRole::Mahasiswa;
        $dashboardRouteName = $role->dashboardRouteName();

        if ($role->isAdminRole()) {
            return \Inertia\Inertia::location(filament()->getPanel('admin')?->getUrl() ?? url('/admin'));
        }

        return redirect()->route($dashboardRouteName);
    }
}
