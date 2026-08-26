<?php

use App\Models\Admin;
use Illuminate\Auth\Events\Verified;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\URL;
use Laravel\Fortify\Features;

beforeEach(function () {
    $this->skipUnlessFortifyHas(Features::emailVerification());
});

test('email verification screen can be rendered', function () {
    $admin = Admin::factory()->unverified()->create();

    $response = $this->actingAs($admin, 'admin')->get(route('admin.verification.notice'));

    $response->assertOk();
});

test('email can be verified', function () {
    $admin = Admin::factory()->unverified()->create();

    Event::fake();

    $verificationUrl = URL::temporarySignedRoute(
        'admin.verification.verify',
        now()->addMinutes(60),
        ['id' => $admin->id, 'hash' => sha1($admin->email)],
    );

    $response = $this->actingAs($admin, 'admin')->get($verificationUrl);

    Event::assertDispatched(Verified::class);

    expect($admin->fresh()->hasVerifiedEmail())->toBeTrue();
    $response->assertRedirect(route('admin.dashboard', absolute: false).'?verified=1');
});

test('email is not verified with invalid hash', function () {
    $admin = Admin::factory()->unverified()->create();

    Event::fake();

    $verificationUrl = URL::temporarySignedRoute(
        'admin.verification.verify',
        now()->addMinutes(60),
        ['id' => $admin->id, 'hash' => sha1('wrong-email')],
    );

    $this->actingAs($admin, 'admin')->get($verificationUrl);

    Event::assertNotDispatched(Verified::class);
    expect($admin->fresh()->hasVerifiedEmail())->toBeFalse();
});

test('email is not verified with invalid admin id', function () {
    $admin = Admin::factory()->unverified()->create();

    Event::fake();

    $verificationUrl = URL::temporarySignedRoute(
        'admin.verification.verify',
        now()->addMinutes(60),
        ['id' => 123, 'hash' => sha1($admin->email)],
    );

    $this->actingAs($admin, 'admin')->get($verificationUrl);

    Event::assertNotDispatched(Verified::class);
    expect($admin->fresh()->hasVerifiedEmail())->toBeFalse();
});

test('verified admin is redirected to dashboard from verification prompt', function () {
    $admin = Admin::factory()->create();

    Event::fake();

    $response = $this->actingAs($admin, 'admin')->get(route('admin.verification.notice'));

    Event::assertNotDispatched(Verified::class);
    $response->assertRedirect(route('admin.dashboard', absolute: false));
});

test('already verified admin visiting verification link is redirected without firing event again', function () {
    $admin = Admin::factory()->create();

    Event::fake();

    $verificationUrl = URL::temporarySignedRoute(
        'admin.verification.verify',
        now()->addMinutes(60),
        ['id' => $admin->id, 'hash' => sha1($admin->email)],
    );

    $this->actingAs($admin, 'admin')->get($verificationUrl)
        ->assertRedirect(route('admin.dashboard', absolute: false).'?verified=1');

    Event::assertNotDispatched(Verified::class);
    expect($admin->fresh()->hasVerifiedEmail())->toBeTrue();
});
