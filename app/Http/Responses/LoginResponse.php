<?php

namespace App\Http\Responses;

use App\Enums\AppRole;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Laravel\Fortify\Contracts\LoginResponse as LoginResponseContract;
use Laravel\Fortify\Fortify;

class LoginResponse implements LoginResponseContract
{
    public function toResponse($request): \Symfony\Component\HttpFoundation\Response
    {
        if ($request->wantsJson()) {
            return response()->json(['two_factor' => false]);
        }

        $user = $request->user();

        if ($user !== null && $user->hasRole(AppRole::Admin->value)) {
            $request->session()->put('active_role', AppRole::Admin->value);

            if ($user->last_active_role !== AppRole::Admin->value) {
                $user->forceFill(['last_active_role' => AppRole::Admin->value])->save();
            }

            return \Inertia\Inertia::location('/admin');
        }

        return redirect()->intended(Fortify::redirects('login'));
    }
}
