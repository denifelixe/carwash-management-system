<?php

use App\Http\Controllers\Admin\DashboardController;
use App\Http\Controllers\Auth\AdminAuthenticatedSessionController;
use App\Http\Controllers\Settings\ProfileController;
use App\Http\Controllers\Settings\SecurityController;
use Illuminate\Auth\Middleware\RequirePassword;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Route;
use Laravel\Fortify\Features;
use Laravel\Fortify\Http\Controllers\ConfirmablePasswordController;
use Laravel\Fortify\Http\Controllers\ConfirmedPasswordStatusController;
use Laravel\Fortify\Http\Controllers\NewPasswordController;
use Laravel\Fortify\Http\Controllers\PasswordResetLinkController;
use Laravel\Fortify\Http\Controllers\RegisteredUserController;

Route::domain((string) config('domains.admin'))
    ->name('admin.')
    ->middleware('portal.auth:admin')
    ->group(function (): void {
        Route::get('/', fn (): RedirectResponse => Auth::guard('admin')->check()
            ? to_route('admin.dashboard')
            : to_route('admin.login'))->name('home');

        Route::middleware('guest:admin')->group(function (): void {
            Route::get('login', [AdminAuthenticatedSessionController::class, 'create'])->name('login');
            Route::post('login', [AdminAuthenticatedSessionController::class, 'store'])
                ->middleware('throttle:admin-login')
                ->name('login.store');

            if (Features::enabled(Features::registration())) {
                Route::get('register', [RegisteredUserController::class, 'create'])->name('register');
                Route::post('register', [RegisteredUserController::class, 'store'])->name('register.store');
            }

            if (Features::enabled(Features::resetPasswords())) {
                Route::get('forgot-password', [PasswordResetLinkController::class, 'create'])->name('password.request');
                Route::post('forgot-password', [PasswordResetLinkController::class, 'store'])->name('password.email');
                Route::get('reset-password/{token}', [NewPasswordController::class, 'create'])->name('password.reset');
                Route::post('reset-password', [NewPasswordController::class, 'store'])->name('password.update');
            }
        });

        Route::middleware('auth:admin')->group(function (): void {
            Route::post('logout', [AdminAuthenticatedSessionController::class, 'destroy'])->name('logout');
            Route::get('dashboard', DashboardController::class)->name('dashboard');

            Route::get('user/confirm-password', [ConfirmablePasswordController::class, 'show'])->name('password.confirm');
            Route::post('user/confirm-password', [ConfirmablePasswordController::class, 'store'])->name('password.confirm.store');
            Route::get('user/confirmed-password-status', [ConfirmedPasswordStatusController::class, 'show'])
                ->name('password.confirmation');

        });

        Route::middleware('auth:admin')->group(function (): void {
            Route::redirect('settings', '/settings/profile')->name('settings');

            Route::get('settings/profile', [ProfileController::class, 'edit'])->name('profile.edit');
            Route::patch('settings/profile', [ProfileController::class, 'update'])->name('profile.update');
        });

        Route::middleware('auth:admin')->group(function (): void {
            Route::delete('settings/profile', [ProfileController::class, 'destroy'])->name('profile.destroy');

            Route::get('settings/security', [SecurityController::class, 'edit'])
                ->middleware(RequirePassword::using('admin.password.confirm'))
                ->name('security.edit');

            Route::put('settings/password', [SecurityController::class, 'update'])
                ->middleware('throttle:6,1')
                ->name('user-password.update');
        });
    });
