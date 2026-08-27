<?php

namespace App\Http\Middleware;

use App\Enums\Rolle;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Beschränkt eine Route auf bestimmte Rollen:
 * ->middleware('role:admin,projektleiter'). Admin darf immer.
 */
class EnsureRole
{
    public function handle(Request $request, Closure $next, string ...$rollen): Response
    {
        $user = $request->user();

        abort_unless($user !== null, 403);

        if ($user->role === Rolle::Admin) {
            return $next($request);
        }

        abort_unless(in_array($user->role->value, $rollen, true), 403);

        return $next($request);
    }
}
