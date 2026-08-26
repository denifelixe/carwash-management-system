<?php

use Illuminate\Support\Facades\Route;

test('public admin registration is disabled', function () {
    expect(Route::has('admin.register'))->toBeFalse()
        ->and(Route::has('admin.register.store'))->toBeFalse();

    $this->get('https://'.config('domains.admin').'/register')
        ->assertNotFound();

    $this->post('https://'.config('domains.admin').'/register', [
        'name' => 'Test Admin',
        'email' => 'test@example.com',
        'password' => 'password',
        'password_confirmation' => 'password',
    ])->assertNotFound();
});
