<?php

namespace App\Http\Middleware\Demo;

use App\Support\Demo\RoleAccess;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Guards an admin module against roles that may not reach it (BR-11).
 */
class EnsureModule
{
    /**
     * @param  Closure(Request): (Response)  $next
     */
    public function handle(Request $request, Closure $next, string $module): Response
    {
        $role = (string) $request->session()->get(RoleAccess::SESSION_KEY, '');

        if (! RoleAccess::isValidRole($role)) {
            return redirect()->route('demo.home');
        }

        abort_unless(
            RoleAccess::allows($role, $module),
            403,
            'Role '.RoleAccess::role($role)['name'].' tidak memiliki akses ke modul ini.',
        );

        return $next($request);
    }
}
