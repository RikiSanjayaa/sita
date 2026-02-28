<?php

namespace App\Http\Controllers;

use App\Enums\AppRole;
use Illuminate\Http\RedirectResponse;
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

        if ($role === AppRole::Admin) {
            return \Inertia\Inertia::location(route($dashboardRouteName));
        }

        return redirect()->route($dashboardRouteName);
    }
}
