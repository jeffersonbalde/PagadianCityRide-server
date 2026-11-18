<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureUserHasRole
{
    /**
     * Handle an incoming request. The person must have one of the roles, for
     * example `role:driver` or `role:admin`.
     *
     * @param  Closure(Request): (Response)  $next
     */
    public function handle(Request $request, Closure $next, string ...$roles): Response
    {
        $role = $request->user()?->role->value;

        abort_unless(in_array($role, $roles, true), 403, 'This action is not available for your account.');

        return $next($request);
    }
}
