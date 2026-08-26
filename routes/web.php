<?php

use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Route;

Route::domain((string) config('demo.domain'))->group(function (): void {
    require __DIR__.'/demo.php';
});

require __DIR__.'/admin.php';
require __DIR__.'/member.php';

Route::domain((string) config('domains.app'))
    ->get('/', fn (): RedirectResponse => to_route('admin.login'))
    ->name('home');
