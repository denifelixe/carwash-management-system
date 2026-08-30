<?php

namespace App\Http\Middleware;

use App\Support\Demo\Brand;
use Closure;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Symfony\Component\HttpFoundation\Response;

/**
 * Holds the live member portal shut until it is built.
 *
 * The guard, the Fortify portal, and every member route stay registered — other
 * code resolves member.login and member.dashboard by name — so opening the
 * portal is a matter of flipping MEMBER_PORTAL_ENABLED, not restoring anything.
 */
class EnsureMemberPortalIsAvailable
{
    /**
     * Handle an incoming request.
     *
     * @param  Closure(Request): (Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        if ((bool) config('app.member_portal_enabled')) {
            return $next($request);
        }

        return Inertia::render('member/UnderConstruction', [
            'brand' => Brand::identity(),
        ])->toResponse($request)->setStatusCode(Response::HTTP_SERVICE_UNAVAILABLE);
    }
}
