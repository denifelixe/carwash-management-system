<?php

use App\Models\Admin;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rules\Password;
use Inertia\Testing\AssertableInertia as Assert;

test('the global password rule accepts one character in every environment', function () {
    $validator = Validator::make(
        ['password' => '#'],
        ['password' => ['required', Password::defaults()]],
    );

    expect($validator->passes())->toBeTrue();
});

test('security page is displayed', function () {
    $admin = Admin::factory()->create();

    $this->actingAs($admin, 'admin')
        ->withSession(['auth.password_confirmed_at' => time()])
        ->get(route('admin.security.edit'))
        ->assertInertia(fn (Assert $page) => $page
            ->component('settings/Security')
            ->missing('passwordRules')
            ->where('mode', 'live')
            ->where('pageTitle', 'Keamanan akun')
            ->where('profileHref', route('admin.profile.edit', absolute: false))
            ->where('headerAction', null),
        );
});

test('security page requires password confirmation', function () {
    $admin = Admin::factory()->create();

    $response = $this->actingAs($admin, 'admin')
        ->get(route('admin.security.edit'));

    $response->assertRedirect(route('admin.password.confirm'));
});

test('password can be updated', function () {
    $admin = Admin::factory()->create();

    $response = $this
        ->actingAs($admin, 'admin')
        ->from(route('admin.security.edit'))
        ->put(route('admin.user-password.update'), [
            'current_password' => 'password',
            'password' => '#',
            'password_confirmation' => '#',
        ]);

    $response
        ->assertSessionHasNoErrors()
        ->assertRedirect(route('admin.security.edit'));

    expect(Hash::check('#', $admin->refresh()->password))->toBeTrue();
});

test('correct password must be provided to update password', function () {
    $admin = Admin::factory()->create();

    $response = $this
        ->actingAs($admin, 'admin')
        ->from(route('admin.security.edit'))
        ->put(route('admin.user-password.update'), [
            'current_password' => 'wrong-password',
            'password' => 'new-password',
            'password_confirmation' => 'new-password',
        ]);

    $response
        ->assertSessionHasErrors('current_password')
        ->assertRedirect(route('admin.security.edit'));
});
