<?php

use App\Http\Controllers\Carwash\EntryController;
use Illuminate\Support\Facades\Route;

/*
 * The role picker is the landing page: visitors reach the console without an
 * extra hop through a business selector.
 */
Route::get('/', [EntryController::class, 'index'])->name('home');

Route::middleware(['auth', 'verified'])->group(function () {
    Route::inertia('dashboard', 'Dashboard')->name('dashboard');
});

require __DIR__.'/settings.php';
require __DIR__.'/carwash.php';
