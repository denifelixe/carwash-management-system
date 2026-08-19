<?php

use App\Http\Controllers\Carwash\BookingController;
use App\Http\Controllers\Carwash\CustomerController;
use App\Http\Controllers\Carwash\DashboardController;
use App\Http\Controllers\Carwash\EntryController;
use App\Http\Controllers\Carwash\FinanceController;
use App\Http\Controllers\Carwash\InventoryController;
use App\Http\Controllers\Carwash\MemberController;
use App\Http\Controllers\Carwash\OrderController;
use App\Http\Controllers\Carwash\PosController;
use App\Http\Controllers\Carwash\ReportController;
use App\Http\Controllers\Carwash\RewardController;
use App\Http\Controllers\Carwash\UserRoleController;
use Illuminate\Support\Facades\Route;

/*
 * The role picker now lives at the site root; keep the old entry URL working.
 */
Route::permanentRedirect('carwash', '/');

Route::prefix('carwash')->name('carwash.')->group(function () {
    Route::post('session/role', [EntryController::class, 'store'])->name('session.role');
    Route::post('session/exit', [EntryController::class, 'destroy'])->name('session.exit');

    /*
     * Admin console. Each module is guarded by the role matrix in RoleAccess.
     */
    Route::prefix('admin')->name('admin.')->group(function () {
        Route::get('dashboard', [DashboardController::class, 'index'])
            ->middleware('carwash.module:dashboard')
            ->name('dashboard');

        Route::get('orders', [OrderController::class, 'index'])
            ->middleware('carwash.module:orders')
            ->name('orders');

        Route::get('pos', [PosController::class, 'index'])
            ->middleware('carwash.module:pos')
            ->name('pos');

        Route::get('customers', [CustomerController::class, 'index'])
            ->middleware('carwash.module:customers')
            ->name('customers');

        Route::get('finance', [FinanceController::class, 'index'])
            ->middleware('carwash.module:finance')
            ->name('finance');

        Route::get('bookings', [BookingController::class, 'index'])
            ->middleware('carwash.module:bookings')
            ->name('bookings');

        Route::get('inventory', [InventoryController::class, 'index'])
            ->middleware('carwash.module:inventory')
            ->name('inventory');

        Route::get('rewards', [RewardController::class, 'index'])
            ->middleware('carwash.module:rewards')
            ->name('rewards');

        Route::get('users', [UserRoleController::class, 'index'])
            ->middleware('carwash.module:users')
            ->name('users');

        Route::get('reports', [ReportController::class, 'index'])
            ->middleware('carwash.module:reports')
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
