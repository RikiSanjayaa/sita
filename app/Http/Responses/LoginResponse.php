<?php

namespace App\Http\Responses;

use App\Enums\AppRole;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\ValidationException;
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

        if ($user !== null) {
            $roles = $user->roleNames();
            $isAdminOnly = collect($roles)->every(
                fn(string $role): bool => in_array($role, [AppRole::Admin->value, AppRole::SuperAdmin->value], true),
            );

            if ($isAdminOnly) {
                // Admin-only users must use /admin/login
                Auth::guard('web')->logout();
                $request->session()->invalidate();
                $request->session()->regenerateToken();

                throw ValidationException::withMessages([
                    Fortify::username() => ['Admin harus login melalui halaman /admin/login.'],
                ]);
            }
        }

        return redirect()->intended(Fortify::redirects('login'));
    }
}
