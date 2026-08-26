<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

class UsePortalAuthentication
{
    /**
     * Handle an incoming request.
     *
     * @param  Closure(Request): (Response)  $next
     */
    public function handle(Request $request, Closure $next, string $portal): Response
    {
        /** @var array{guard: string, passwords: string, domain: string, home: string}|null $context */
        $context = config("fortify.portals.{$portal}");

        abort_if($context === null, Response::HTTP_INTERNAL_SERVER_ERROR, 'Authentication portal is not configured.');

        $original = [
            'auth.defaults.guard' => config('auth.defaults.guard'),
            'fortify.guard' => config('fortify.guard'),
            'fortify.passwords' => config('fortify.passwords'),
            'fortify.domain' => config('fortify.domain'),
            'fortify.home' => config('fortify.home'),
        ];

        Auth::shouldUse($context['guard']);

        config([
            'fortify.guard' => $context['guard'],
            'fortify.passwords' => $context['passwords'],
            'fortify.domain' => $context['domain'],
            'fortify.home' => $context['home'],
        ]);

        try {
            return $next($request);
        } finally {
            config($original);
        }
    }
}
