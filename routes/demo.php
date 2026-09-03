<?php

use App\Http\Controllers\Demo\AdminRoleController;
use App\Http\Controllers\Demo\AppSettingController;
use App\Http\Controllers\Demo\BookingController;
use App\Http\Controllers\Demo\CustomerController;
use App\Http\Controllers\Demo\DashboardController;
use App\Http\Controllers\Demo\EntryController;
use App\Http\Controllers\Demo\FinanceController;
use App\Http\Controllers\Demo\InventoryController;
use App\Http\Controllers\Demo\MemberController;
use App\Http\Controllers\Demo\OrderController;
use App\Http\Controllers\Demo\PosController;
use App\Http\Controllers\Demo\ReceiptController;
use App\Http\Controllers\Demo\ReportController;
use App\Http\Controllers\Demo\RewardController;
use App\Http\Controllers\Demo\ServiceController;
use App\Http\Controllers\Demo\TimezoneController;
use App\Http\Controllers\Demo\WorkShiftController;
use Illuminate\Support\Facades\Route;

/*
 * The role picker is the demo landing page: visitors reach the console without
 * an extra hop through a business selector.
 */
Route::get('/', [EntryController::class, 'index'])->name('demo.home');

Route::name('demo.')->group(function () {
    Route::post('session/role', [EntryController::class, 'store'])->name('session.role');
    Route::post('session/exit', [EntryController::class, 'destroy'])->name('session.exit');

    /*
     * Admin console. Each module is guarded by the role matrix in RoleAccess.
     */
    Route::prefix('admin')->name('admin.')->group(function () {
        Route::get('dashboard', [DashboardController::class, 'index'])
            ->middleware('demo.module:dashboard')
            ->name('dashboard');

        Route::get('orders', [OrderController::class, 'index'])
            ->middleware('demo.module:orders')
            ->name('orders');

        Route::get('pos', [PosController::class, 'index'])
            ->middleware('demo.module:pos')
            ->name('pos');

        Route::get('members', [CustomerController::class, 'index'])
            ->middleware('demo.module:members')
            ->name('members');

        Route::get('finance', [FinanceController::class, 'index'])
            ->middleware('demo.module:finance')
            ->name('finance');

        Route::get('bookings', [BookingController::class, 'index'])
            ->middleware('demo.module:bookings')
            ->name('bookings');

        Route::get('inventory', [InventoryController::class, 'index'])
            ->middleware('demo.module:inventory')
            ->name('inventory');

        Route::get('rewards', [RewardController::class, 'index'])
            ->middleware('demo.module:rewards')
            ->name('rewards');

        Route::get('users', [AdminRoleController::class, 'index'])
            ->middleware('demo.module:users')
            ->name('users');

        Route::get('reports', [ReportController::class, 'index'])
            ->middleware('demo.module:reports')
            ->name('reports');

        Route::get('master/layanan', [ServiceController::class, 'index'])
            ->middleware('demo.module:master_services')
            ->name('master.services');

        Route::get('master/shift', [WorkShiftController::class, 'index'])
            ->middleware('demo.module:master_work_shifts')
            ->name('master.work-shifts');

        Route::get('master/zona-waktu', [TimezoneController::class, 'index'])
            ->middleware('demo.module:master_timezone')
            ->name('master.timezone');

        Route::get('master/app-setting', [AppSettingController::class, 'index'])
            ->middleware('demo.module:master_app_settings')
            ->name('master.app-settings');

        Route::get('master/struk', [ReceiptController::class, 'index'])
            ->middleware('demo.module:master_receipt')
            ->name('master.receipt');
    });

    /*
     * Customer web application. Information and loyalty only, so every route
     * here is a read-only GET.
     */
    Route::prefix('member')->name('member.')->group(function () {
        Route::get('login', [MemberController::class, 'login'])->name('login');
        Route::get('register', [MemberController::class, 'register'])->name('register');
        Route::get('dashboard', [MemberController::class, 'dashboard'])->name('dashboard');
        Route::get('stamps', [MemberController::class, 'stamps'])->name('stamps');
        Route::get('services', [MemberController::class, 'services'])->name('services');
        Route::get('rewards', [MemberController::class, 'rewards'])->name('rewards');
        Route::get('profile', [MemberController::class, 'profile'])->name('profile');
    });
});
