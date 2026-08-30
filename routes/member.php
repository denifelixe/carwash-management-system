<?php

use App\Http\Controllers\Auth\MemberAuthenticatedSessionController;
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
            Route::inertia('dashboard', 'member/Dashboard')->name('dashboard');
        });

        /* Any other path on the member domain answers the same way. */
        Route::fallback(fn () => abort(404));
    });
