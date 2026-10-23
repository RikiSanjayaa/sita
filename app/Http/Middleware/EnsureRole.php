<?php

namespace App\Http\Middleware;

use App\Enums\AppRole;
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

        // Kaprodi works inside the dosen area in the same workspace, so the active role stays kaprodi.
        $kaprodiCoversDosen = $activeRole === AppRole::Kaprodi->value
            && in_array(AppRole::Dosen->value, $allowedRoles, true);

        if (! $kaprodiCoversDosen && ! $user->hasAnyRole($allowedRoles)) {
            abort(403);
        }

        if ($kaprodiCoversDosen || in_array($activeRole, $allowedRoles, true)) {
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
