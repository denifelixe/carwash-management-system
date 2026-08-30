<?php

use App\Models\Admin;
use App\Support\AppSettings;
use Illuminate\Support\Facades\Storage;
use Inertia\Testing\AssertableInertia as Assert;

test('login screen can be rendered', function () {
    $response = $this->get(route('admin.login'));

    $response
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('auth/AdminLogin')
            ->where('brand.photo', null),
        );
});

test('admin login screen uses the uploaded app logo', function () {
    Storage::fake('public');

    AppSettings::put(AppSettings::APP_NAME, 'Showtime Autocare');
    AppSettings::put(AppSettings::APP_PHOTO, 'app-branding/app-photo.png');

    $this->get(route('admin.login'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('auth/AdminLogin')
            ->where('brand.name', 'Showtime Autocare')
            ->where('brand.photo', Storage::disk('public')->url('app-branding/app-photo.png')),
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
