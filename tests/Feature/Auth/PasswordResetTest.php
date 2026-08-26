<?php

use App\Models\Admin;
use Illuminate\Auth\Notifications\ResetPassword;
use Illuminate\Support\Facades\Notification;
use Laravel\Fortify\Features;

beforeEach(function () {
    $this->skipUnlessFortifyHas(Features::resetPasswords());
});

test('reset password link screen can be rendered', function () {
    $response = $this->get(route('admin.password.request'));

    $response->assertOk();
});

test('reset password link can be requested', function () {
    Notification::fake();

    $admin = Admin::factory()->create();

    $this->post(route('admin.password.email'), ['email' => $admin->email]);

    Notification::assertSentTo($admin, ResetPassword::class);
});

test('reset password screen can be rendered', function () {
    Notification::fake();

    $admin = Admin::factory()->create();

    $this->post(route('admin.password.email'), ['email' => $admin->email]);

    Notification::assertSentTo($admin, ResetPassword::class, function ($notification) {
        $response = $this->get(route('admin.password.reset', $notification->token));

        $response->assertOk();

        return true;
    });
});

test('password can be reset with valid token', function () {
    Notification::fake();

    $admin = Admin::factory()->create();

    $this->post(route('admin.password.email'), ['email' => $admin->email]);

    Notification::assertSentTo($admin, ResetPassword::class, function ($notification) use ($admin) {
        $response = $this->post(route('admin.password.update'), [
            'token' => $notification->token,
            'email' => $admin->email,
            'password' => 'password',
            'password_confirmation' => 'password',
        ]);

        $response
            ->assertSessionHasNoErrors()
            ->assertRedirect(route('admin.login'));

        return true;
    });
});

test('password cannot be reset with invalid token', function () {
    $admin = Admin::factory()->create();

    $response = $this->post(route('admin.password.update'), [
        'token' => 'invalid-token',
        'email' => $admin->email,
        'password' => 'newpassword123',
        'password_confirmation' => 'newpassword123',
    ]);

    $response->assertSessionHasErrors('email');
});
