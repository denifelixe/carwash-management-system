<?php

use App\Models\Admin;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
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
        ->toContain('Simpan perubahan')
        ->toContain('Foto profil')
        ->toContain('multipart/form-data')
        ->toContain('@change="selectPhoto"')
        ->toContain('forceFormData: true')
        ->not->toContain('Akun aktif')
        ->not->toContain('DeleteAdmin')
        ->not->toContain('Hapus akun');
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

test('admin can upload and replace their profile photo on the configured disk', function () {
    $disk = (string) config('filesystems.default');
    Storage::fake($disk);
    $admin = Admin::factory()->create();

    $this->actingAs($admin, 'admin')
        ->post(route('admin.profile.update'), [
            '_method' => 'patch',
            'name' => $admin->name,
            'email' => $admin->email,
            'photo' => UploadedFile::fake()->image('foto-lama.jpg'),
        ])
        ->assertRedirect(route('admin.profile.edit'))
        ->assertSessionHasNoErrors();

    $oldPath = $admin->refresh()->profile_photo_path;

    expect($oldPath)->not->toBeNull();
    Storage::disk($disk)->assertExists($oldPath);

    $this->actingAs($admin, 'admin')
        ->post(route('admin.profile.update'), [
            '_method' => 'patch',
            'name' => $admin->name,
            'email' => $admin->email,
            'photo' => UploadedFile::fake()->image('foto-baru.png'),
        ])
        ->assertRedirect(route('admin.profile.edit'))
        ->assertSessionHasNoErrors();

    $newPath = $admin->refresh()->profile_photo_path;

    expect($newPath)->not->toBe($oldPath);
    expect($admin->profilePhotoUrl())->toContain(basename($newPath));
    Storage::disk($disk)->assertExists($newPath);
    Storage::disk($disk)->assertMissing($oldPath);

    $this->actingAs($admin, 'admin')
        ->get(route('admin.users.photo', $admin))
        ->assertOk();
});

test('profile photo must be a supported image no larger than twenty megabytes', function () {
    Storage::fake((string) config('filesystems.default'));
    $admin = Admin::factory()->create();

    $this->actingAs($admin, 'admin')
        ->from(route('admin.profile.edit'))
        ->post(route('admin.profile.update'), [
            '_method' => 'patch',
            'name' => $admin->name,
            'email' => $admin->email,
            'photo' => UploadedFile::fake()->create('avatar.pdf', 100, 'application/pdf'),
        ])
        ->assertRedirect(route('admin.profile.edit'))
        ->assertSessionHasErrors('photo');

    $this->actingAs($admin, 'admin')
        ->from(route('admin.profile.edit'))
        ->post(route('admin.profile.update'), [
            '_method' => 'patch',
            'name' => $admin->name,
            'email' => $admin->email,
            'photo' => UploadedFile::fake()->image('avatar.jpg')->size(20481),
        ])
        ->assertRedirect(route('admin.profile.edit'))
        ->assertSessionHasErrors('photo');
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
