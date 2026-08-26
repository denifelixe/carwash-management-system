<?php

use App\Models\Admin;
use Illuminate\Support\Facades\Route;

test('public admin email verification is disabled', function () {
    expect(Route::has('admin.verification.notice'))->toBeFalse()
        ->and(Route::has('admin.verification.verify'))->toBeFalse()
        ->and(Route::has('admin.verification.send'))->toBeFalse();

    $this->get('https://'.config('domains.admin').'/email/verify')
        ->assertNotFound();

    $this->get('https://'.config('domains.admin').'/email/verify/1/invalid-hash')
        ->assertNotFound();
});

test('unverified admins can access the dashboard', function () {
    $admin = Admin::factory()->unverified()->create();

    $this->actingAs($admin, 'admin')
        ->get(route('admin.dashboard'))
        ->assertOk();
});
