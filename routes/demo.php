<?php

use App\Http\Controllers\Demo\BookingController;
use App\Http\Controllers\Demo\CustomerController;
use App\Http\Controllers\Demo\DashboardController;
use App\Http\Controllers\Demo\EntryController;
use App\Http\Controllers\Demo\FinanceController;
use App\Http\Controllers\Demo\InventoryController;
use App\Http\Controllers\Demo\MemberController;
use App\Http\Controllers\Demo\OrderController;
use App\Http\Controllers\Demo\PosController;
use App\Http\Controllers\Demo\ReportController;
use App\Http\Controllers\Demo\RewardController;
use App\Http\Controllers\Demo\UserRoleController;
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

        Route::get('customers', [CustomerController::class, 'index'])
            ->middleware('demo.module:customers')
            ->name('customers');

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

        Route::get('users', [UserRoleController::class, 'index'])
            ->middleware('demo.module:users')
            ->name('users');

        Route::get('reports', [ReportController::class, 'index'])
            ->middleware('demo.module:reports')
            ->name('reports');
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
