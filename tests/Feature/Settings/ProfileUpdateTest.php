<?php

use App\Models\Admin;
use Inertia\Testing\AssertableInertia;

test('profile page is displayed', function () {
    $admin = Admin::factory()->create();

    $response = $this
        ->actingAs($admin, 'admin')
        ->get(route('admin.profile.edit'));

    $response->assertOk()
        ->assertInertia(
            fn (AssertableInertia $page) => $page
                ->component('settings/Profile')
                ->where('mode', 'live')
                ->where('pageTitle', 'Pengaturan profil')
                ->where('profileHref', route('admin.profile.edit', absolute: false))
                ->where('headerAction', null)
                ->has('modules')
        );
});

test('settings pages use the shared admin layout instead of the starter layout', function () {
    $app = file_get_contents(resource_path('js/app.ts'));
    $profile = file_get_contents(resource_path('js/pages/settings/Profile.vue'));

    expect($app)
        ->toContain("case name.startsWith('settings/'):")
        ->toContain('return AdminLayout;')
        ->not->toContain('SettingsLayout')
        ->and(resource_path('js/layouts/settings/Layout.vue'))->not->toBeFile()
        ->and($profile)
        ->toContain('<SettingsNav />')
        ->toContain('Profil admin')
        ->toContain('Simpan perubahan');
});

test('profile information can be updated', function () {
    $admin = Admin::factory()->create();
    $emailVerifiedAt = $admin->email_verified_at;

    $response = $this
        ->actingAs($admin, 'admin')
        ->patch(route('admin.profile.update'), [
            'name' => 'Test Admin',
            'email' => 'test@example.com',
        ]);

    $response
        ->assertSessionHasNoErrors()
        ->assertRedirect(route('admin.profile.edit'));

    $admin->refresh();

    expect($admin->name)->toBe('Test Admin');
    expect($admin->email)->toBe('test@example.com');
    expect($admin->email_verified_at)->toEqual($emailVerifiedAt);
});

test('legacy email verification timestamp is unchanged when the email address is unchanged', function () {
    $admin = Admin::factory()->create();

    $response = $this
        ->actingAs($admin, 'admin')
        ->patch(route('admin.profile.update'), [
            'name' => 'Test Admin',
            'email' => $admin->email,
        ]);

    $response
        ->assertSessionHasNoErrors()
        ->assertRedirect(route('admin.profile.edit'));

    expect($admin->refresh()->email_verified_at)->not->toBeNull();
});

test('admin can delete their account', function () {
    $admin = Admin::factory()->create();

    $response = $this
        ->actingAs($admin, 'admin')
        ->delete(route('admin.profile.destroy'), [
            'password' => 'password',
        ]);

    $response
        ->assertSessionHasNoErrors()
        ->assertRedirect(route('admin.login'));

    $this->assertGuest('admin');
    expect($admin->fresh())->toBeNull();
});

test('correct password must be provided to delete account', function () {
    $admin = Admin::factory()->create();

    $response = $this
        ->actingAs($admin, 'admin')
        ->from(route('admin.profile.edit'))
        ->delete(route('admin.profile.destroy'), [
            'password' => 'wrong-password',
        ]);

    $response
        ->assertSessionHasErrors('password')
        ->assertRedirect(route('admin.profile.edit'));

    expect($admin->fresh())->not->toBeNull();
});
