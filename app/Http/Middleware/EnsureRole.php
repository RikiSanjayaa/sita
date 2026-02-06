<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureRole
{
    /**
     * @param  array<int, string>  ...$roles
     */
    public function handle(Request $request, Closure $next, ...$roles): Response
    {
        $user = $request->user();

        abort_unless($user !== null, 401);

        $allowedRoles = collect($roles)
            ->flatMap(static fn (string $role): array => explode('|', $role))
            ->map(static fn (string $role): string => trim($role))
            ->filter()
            ->values()
            ->all();

        $activeRole = $user->resolveActiveRole($request->session()->get('active_role'));
        $userHasAllowedRole = $user->hasAnyRole($allowedRoles);

        if (! $userHasAllowedRole) {
            abort(403);
        }

        if (in_array($activeRole, $allowedRoles, true)) {
            return $next($request);
        }

        $nextRole = collect($allowedRoles)->first(
            static fn (string $role): bool => $user->hasRole($role),
        );

        $request->session()->put('active_role', $nextRole);

        if ($user->last_active_role !== $nextRole) {
            $user->forceFill(['last_active_role' => $nextRole])->save();
        }

        return $next($request);
    }
}
