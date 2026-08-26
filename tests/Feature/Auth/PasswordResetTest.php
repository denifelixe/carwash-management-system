<?php

use Illuminate\Support\Facades\Route;

test('public admin password reset is disabled', function () {
    expect(Route::has('admin.password.request'))->toBeFalse()
        ->and(Route::has('admin.password.email'))->toBeFalse()
        ->and(Route::has('admin.password.reset'))->toBeFalse()
        ->and(Route::has('admin.password.update'))->toBeFalse();

    $this->get('https://'.config('domains.admin').'/forgot-password')
        ->assertNotFound();

    $this->post('https://'.config('domains.admin').'/forgot-password', [
        'email' => 'admin@example.com',
    ])->assertNotFound();

    $this->get('https://'.config('domains.admin').'/reset-password/invalid-token')
        ->assertNotFound();

    $this->post('https://'.config('domains.admin').'/reset-password', [
        'token' => 'invalid-token',
        'email' => 'admin@example.com',
        'password' => 'newpassword123',
        'password_confirmation' => 'newpassword123',
    ])->assertNotFound();
});
