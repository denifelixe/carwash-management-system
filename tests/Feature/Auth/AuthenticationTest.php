<?php

use App\Models\Admin;
use Inertia\Testing\AssertableInertia as Assert;

test('login screen can be rendered', function () {
    $response = $this->get(route('admin.login'));

    $response
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('auth/AdminLogin'),
        );
});

test('admins can authenticate using the admin login screen', function () {
    $admin = Admin::factory()->create();

    $response = $this->post(route('admin.login.store'), [
        'email' => $admin->email,
        'password' => 'password',
    ]);

    $this->assertAuthenticated('admin');
    $response->assertRedirect(route('admin.dashboard', absolute: false));
});

test('admins can not authenticate with invalid password', function () {
    $admin = Admin::factory()->create();

    $this->post(route('admin.login.store'), [
        'email' => $admin->email,
        'password' => 'wrong-password',
    ])->assertSessionHasErrors([
        'email' => 'Email atau kata sandi tidak sesuai.',
    ]);

    $this->assertGuest('admin');
});

test('admins can logout', function () {
    $admin = Admin::factory()->create();

    $response = $this->actingAs($admin, 'admin')->post(route('admin.logout'));

    $response->assertRedirect(route('admin.login'));

    $this->assertGuest('admin');
});

test('admin login attempts are rate limited', function () {
    $admin = Admin::factory()->create();

    foreach (range(1, 6) as $attempt) {
        $response = $this->post(route('admin.login.store'), [
            'email' => $admin->email,
            'password' => 'wrong-password',
        ]);
    }

    $response->assertTooManyRequests();
});
