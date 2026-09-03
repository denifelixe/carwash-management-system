<?php

use App\Models\Admin;
use App\Models\AdminRole;
use App\Support\AppSettings;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
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

test('a deactivated admin is refused by every guard retrieval path', function () {
    $admin = Admin::factory()->create(['remember_token' => Str::random(60)]);
    $admin->update(['is_active' => false]);

    $provider = Auth::guard('admin')->getProvider();

    expect($provider->retrieveById($admin->id))->toBeNull()
        ->and($provider->retrieveByToken($admin->id, (string) $admin->remember_token))->toBeNull();
});

test('a deactivated admin loses their remember token and stored sessions', function () {
    $owner = Admin::factory()->create(['is_owner' => true]);
    $role = AdminRole::query()->where('key', 'manager')->firstOrFail();
    $staff = Admin::factory()->create([
        'role_id' => $role->id,
        'is_active' => true,
        'remember_token' => Str::random(60),
    ]);

    DB::table('sessions')->insert([
        'id' => 'staff-session',
        'admin_id' => $staff->id,
        'member_id' => null,
        'ip_address' => '127.0.0.1',
        'user_agent' => 'phpunit',
        'payload' => base64_encode(serialize([])),
        'last_activity' => now()->getTimestamp(),
    ]);

    $this->actingAs($owner, 'admin')
        ->patch(route('admin.users.update', $staff), [
            'name' => $staff->name,
            'email' => $staff->email,
            'phone' => $staff->phone,
            'role_id' => $role->id,
            'shift_id' => null,
            'password' => '',
            'password_confirmation' => '',
            'is_active' => false,
        ])
        ->assertRedirect(route('admin.users.index'));

    expect($staff->refresh()->is_active)->toBeFalse()
        ->and($staff->remember_token)->toBeNull()
        ->and(DB::table('sessions')->where('admin_id', $staff->id)->exists())->toBeFalse();
});
