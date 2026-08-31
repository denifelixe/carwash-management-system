<?php

use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Route;

$applicationType = (string) config('app.type');
$generateAllRoutes = (bool) config('app.generate_all_routes');

if ($generateAllRoutes || in_array($applicationType, ['DEMO', 'ALL'], true)) {
    Route::domain((string) config('domains.demo'))->group(function (): void {
        require __DIR__.'/demo.php';
    });
}

if ($generateAllRoutes || in_array($applicationType, ['LIVE', 'STAGING', 'ALL'], true)) {
    require __DIR__.'/admin.php';
    require __DIR__.'/member.php';

    $appDomain = $generateAllRoutes && $applicationType === 'DEMO'
        ? 'live.invalid'
        : (string) config('domains.app');

    Route::domain($appDomain)
        ->get('/', fn (): RedirectResponse => to_route('member.home'))
        ->name('home');
}
