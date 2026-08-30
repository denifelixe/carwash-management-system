<?php

use App\Http\Middleware\Demo\EnsureModule;
use App\Http\Middleware\EnsureMemberPortalIsAvailable;
use App\Http\Middleware\HandleInertiaRequests;
use App\Http\Middleware\UsePortalAuthentication;
use Illuminate\Contracts\Auth\Middleware\AuthenticatesRequests;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Middleware\AddLinkHeadersForPreloadedAssets;
use Illuminate\Http\Request;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->trustProxies(at: '*');

        $middleware->encryptCookies(except: ['sidebar_state']);

        $middleware->alias([
            'demo.module' => EnsureModule::class,
            'member.portal' => EnsureMemberPortalIsAvailable::class,
            'portal.auth' => UsePortalAuthentication::class,
        ]);

        /*
         * Authenticate sits in the framework's priority list, so without this
         * it would outrank the portal gate and redirect member.dashboard to a
         * login page that is itself closed.
         */
        $middleware->prependToPriorityList(
            before: AuthenticatesRequests::class,
            prepend: EnsureMemberPortalIsAvailable::class,
        );

        $middleware->redirectGuestsTo(fn (Request $request): string => match ($request->getHost()) {
            config('domains.member') => route('member.login'),
            default => route('admin.login'),
        });

        $middleware->redirectUsersTo(fn (Request $request): string => match ($request->getHost()) {
            config('domains.member') => route('member.dashboard'),
            default => route('admin.dashboard'),
        });

        $middleware->web(append: [
            HandleInertiaRequests::class,
            AddLinkHeadersForPreloadedAssets::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $exceptions->shouldRenderJsonWhen(
            fn (Request $request) => $request->is('api/*') || $request->expectsJson(),
        );
    })->create();
