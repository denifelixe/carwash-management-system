<?php

use App\Models\Admin;
use Illuminate\Auth\Notifications\VerifyEmail;
use Illuminate\Support\Facades\Notification;
use Laravel\Fortify\Features;

beforeEach(function () {
    $this->skipUnlessFortifyHas(Features::emailVerification());
});

test('sends verification notification', function () {
    Notification::fake();

    $admin = Admin::factory()->unverified()->create();

    $this->actingAs($admin, 'admin')
        ->post(route('admin.verification.send'))
        ->assertRedirect(route('admin.home'));

    Notification::assertSentTo($admin, VerifyEmail::class);
});

test('does not send verification notification if email is verified', function () {
    Notification::fake();

    $admin = Admin::factory()->create();

    $this->actingAs($admin, 'admin')
        ->post(route('admin.verification.send'))
        ->assertRedirect(route('admin.dashboard', absolute: false));

    Notification::assertNothingSent();
});
