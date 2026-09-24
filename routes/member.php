<?php

use App\Http\Controllers\Auth\MemberAuthenticatedSessionController;
use App\Http\Controllers\Member\PortalController;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Route;

/*
 * member.portal runs ahead of portal.auth so an unbuilt portal short-circuits
 * before the Fortify config is swapped and before guest/auth redirects fire.
 */
Route::domain((string) config('domains.member'))
    ->name('member.')
    ->middleware(['member.portal', 'portal.auth:member'])
    ->group(function (): void {
        Route::get('/', fn (): RedirectResponse => Auth::guard('member')->check()
            ? to_route('member.dashboard')
            : to_route('member.login'))->name('home');

        Route::middleware('guest:member')->group(function (): void {
            Route::get('login', [MemberAuthenticatedSessionController::class, 'create'])->name('login');
            Route::post('login', [MemberAuthenticatedSessionController::class, 'store'])
                ->middleware('throttle:member-login')
                ->name('login.store');
        });

        Route::middleware('auth:member')->group(function (): void {
            Route::post('logout', [MemberAuthenticatedSessionController::class, 'destroy'])->name('logout');
            /* Information and loyalty only, so every portal page is a read-only GET. */
            Route::get('dashboard', [PortalController::class, 'dashboard'])->name('dashboard');
            Route::get('stempel', [PortalController::class, 'stamps'])->name('stamps');
            Route::get('layanan', [PortalController::class, 'services'])->name('services');
            Route::get('reward', [PortalController::class, 'rewards'])->name('rewards');
            Route::get('profil', [PortalController::class, 'profile'])->name('profile');
        });

        /* Any other path on the member domain answers the same way. */
        Route::fallback(fn () => abort(404));
    });
