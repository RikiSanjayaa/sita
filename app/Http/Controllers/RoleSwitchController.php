<?php

namespace App\Http\Controllers;

use App\Enums\AppRole;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class RoleSwitchController extends Controller
{
    public function __invoke(Request $request): RedirectResponse
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

        $dashboardRouteName = AppRole::from($role)->dashboardRouteName();

        return redirect()->route($dashboardRouteName);
    }
}
