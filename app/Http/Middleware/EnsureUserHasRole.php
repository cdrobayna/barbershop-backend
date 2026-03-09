<?php

namespace App\Http\Middleware;

use App\Enums\UserRole;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureUserHasRole
{
    public function handle(Request $request, Closure $next, string ...$roles): Response
    {
        $user = $request->user();

        if (! $user) {
            abort(401, 'Unauthenticated.');
        }

        $allowedRoles = array_map(
            fn (string $r) => UserRole::from($r),
            $roles
        );

        if (! in_array($user->role, $allowedRoles)) {
            abort(403, 'Forbidden.');
        }

        return $next($request);
    }
}
