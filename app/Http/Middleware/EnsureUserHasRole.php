<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Autorise l'accès seulement aux rôles indiqués.
 * Exemple dans les routes : ->middleware('role:admin,directeur')
 */
class EnsureUserHasRole
{
    public function handle(Request $request, Closure $next, string ...$roles): Response
    {
        abort_unless(in_array($request->user()?->role->value, $roles, true), 403);

        return $next($request);
    }
}
