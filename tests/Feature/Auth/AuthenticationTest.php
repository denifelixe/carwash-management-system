<?php

use App\Models\Admin;
use App\Support\AppSettings;
use Illuminate\Support\Facades\Auth;
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

test('admins can authenticate and be remembered', function () {
    $admin = Admin::factory()->create();

    $response = $this->post(route('admin.login.store'), [
        'email' => $admin->email,
        'password' => 'password',
        'remember' => '1',
    ]);

    $this->assertAuthenticatedAs($admin, 'admin');
    expect($admin->refresh()->remember_token)->not->toBeNull();
    $response->assertCookie(Auth::guard('admin')->getRecallerName());
});

test('hidden admins can authenticate using the admin login screen', function () {
    $admin = Admin::factory()->create(['is_hidden' => true]);

    $this->post(route('admin.login.store'), [
        'email' => $admin->email,
        'password' => 'password',
    ])->assertRedirect(route('admin.dashboard', absolute: false));

    $this->assertAuthenticatedAs($admin, 'admin');
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

test('admin login attempts show the rate limit page', function () {
    $admin = Admin::factory()->create();

    $this->withHeader('X-Inertia', 'true');

    foreach (range(1, 6) as $attempt) {
        $response = $this->post(route('admin.login.store'), [
            'email' => $admin->email,
            'password' => 'wrong-password',
        ]);
    }

    $response
        ->assertTooManyRequests()
        ->assertHeader('Retry-After')
        ->assertHeader('X-Inertia', 'true')
        ->assertJsonPath('component', 'errors/TooManyRequests')
        ->assertJsonPath('props.email', $admin->email)
        ->assertJsonPath('props.returnUrl', route('admin.login', absolute: false))
        ->assertJsonPath('props.retryAfter', fn (int $retryAfter): bool => $retryAfter > 0);
});

test('rate limit pages are rendered without an application layout', function () {
    $appSource = file_get_contents(resource_path('js/app.ts'));
    $rateLimitPageSource = file_get_contents(resource_path('js/pages/errors/TooManyRequests.vue'));

    expect($appSource)
        ->toContain("case name.startsWith('errors/'):")
        ->toContain("case name.startsWith('errors/'):\n                return null;")
        ->and($rateLimitPageSource)
        ->toContain('{{ email }}')
        ->not->toContain('ERROR 429');
});
