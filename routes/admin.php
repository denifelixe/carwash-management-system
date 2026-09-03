<?php

use App\Http\Controllers\Admin\AdminRoleController;
use App\Http\Controllers\Admin\AdminUserController;
use App\Http\Controllers\Admin\BookingController;
use App\Http\Controllers\Admin\DashboardController;
use App\Http\Controllers\Admin\FinanceController;
use App\Http\Controllers\Admin\LeadController;
use App\Http\Controllers\Admin\Master\AppSettingController;
use App\Http\Controllers\Admin\Master\ReceiptController;
use App\Http\Controllers\Admin\Master\ServiceController;
use App\Http\Controllers\Admin\Master\TimezoneController;
use App\Http\Controllers\Admin\Master\WorkShiftController;
use App\Http\Controllers\Admin\MemberController;
use App\Http\Controllers\Admin\OrderController;
use App\Http\Controllers\Admin\PosController;
use App\Http\Controllers\Admin\RecapQrController;
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
            Route::get('orders', [OrderController::class, 'index'])->name('orders.index');
            Route::post('orders', [OrderController::class, 'store'])->name('orders.store');
            Route::patch('orders/{order}/handler', [OrderController::class, 'updateHandler'])->name('orders.handler.update');
            Route::patch('orders/{order}/status', [OrderController::class, 'updateStatus'])->name('orders.status.update');
            Route::get('bookings', [BookingController::class, 'index'])->name('bookings.index');
            Route::post('bookings', [BookingController::class, 'store'])->name('bookings.store');
            Route::patch('bookings/{order}', [BookingController::class, 'update'])->name('bookings.update');
            Route::get('pos', [PosController::class, 'index'])->name('pos.index');
            Route::post('pos/{order}/payments', [PosController::class, 'store'])->name('pos.payments.store');
            Route::post('pos/{order}/member', [PosController::class, 'storeMember'])->name('pos.member.store');
            Route::get('finance', [FinanceController::class, 'index'])->name('finance.index');
            Route::post('finance', [FinanceController::class, 'store'])->name('finance.store');
            Route::patch('finance/transactions/{orderTransaction}', [FinanceController::class, 'updateTransaction'])->name('finance.transactions.update');
            Route::patch('finance/{cashEntry}', [FinanceController::class, 'update'])->name('finance.update');
            Route::delete('finance/{cashEntry}', [FinanceController::class, 'destroy'])->name('finance.destroy');
            Route::get('finance/attachments/{cashEntryAttachment}', [FinanceController::class, 'attachment'])->name('finance.attachment');
            Route::get('rekap/qr', RecapQrController::class)->name('recap.qr');
            Route::get('members', [MemberController::class, 'index'])->name('members.index');
            Route::post('members', [MemberController::class, 'store'])->name('members.store');
            Route::patch('members/{member}', [MemberController::class, 'update'])->name('members.update');
            Route::patch('members/{member}/status', [MemberController::class, 'updateStatus'])->name('members.status.update');
            Route::get('leads', [LeadController::class, 'index'])->name('leads.index');
            Route::post('leads', [LeadController::class, 'store'])->name('leads.store');
            Route::patch('leads/{lead}', [LeadController::class, 'update'])->name('leads.update');
            Route::patch('leads/{lead}/status', [LeadController::class, 'updateStatus'])->name('leads.status.update');
            Route::get('users', [AdminUserController::class, 'index'])->name('users.index');
            Route::get('users/{adminUser}/photo', [AdminUserController::class, 'photo'])->name('users.photo');
            Route::post('users', [AdminUserController::class, 'store'])->name('users.store');
            Route::patch('users/{adminUser}/shift', [AdminUserController::class, 'updateShift'])->name('users.shift.update');
            Route::patch('users/{adminUser}', [AdminUserController::class, 'update'])->name('users.update');
            Route::post('roles', [AdminRoleController::class, 'store'])->name('roles.store');
            Route::patch('roles/{adminRole}', [AdminRoleController::class, 'update'])->name('roles.update');
            Route::get('master/layanan', [ServiceController::class, 'index'])->name('master.services.index');
            Route::post('master/layanan', [ServiceController::class, 'store'])->name('master.services.store');
            Route::patch('master/layanan/urutan', [ServiceController::class, 'updateOrder'])->name('master.services.order.update');
            Route::patch('master/layanan/{service}', [ServiceController::class, 'update'])->name('master.services.update');
            Route::delete('master/layanan/{service}', [ServiceController::class, 'destroy'])->name('master.services.destroy');
            Route::get('master/shift', [WorkShiftController::class, 'index'])->name('master.work-shifts.index');
            Route::post('master/shift', [WorkShiftController::class, 'store'])->name('master.work-shifts.store');
            Route::patch('master/shift/{workShift}', [WorkShiftController::class, 'update'])->name('master.work-shifts.update');
            Route::delete('master/shift/{workShift}', [WorkShiftController::class, 'destroy'])->name('master.work-shifts.destroy');
            Route::get('master/zona-waktu', [TimezoneController::class, 'index'])->name('master.timezone.index');
            Route::patch('master/zona-waktu', [TimezoneController::class, 'update'])->name('master.timezone.update');
            Route::get('master/app-setting', [AppSettingController::class, 'index'])->name('master.app-settings.index');
            Route::post('master/app-setting', [AppSettingController::class, 'update'])->name('master.app-settings.update');
            Route::get('master/struk', [ReceiptController::class, 'index'])->name('master.receipt.index');
            Route::post('master/struk', [ReceiptController::class, 'update'])->name('master.receipt.update');

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
