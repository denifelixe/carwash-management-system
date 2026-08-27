<?php

use App\Models\Admin;
use App\Models\Member;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Inertia\Testing\AssertableInertia as Assert;

beforeEach(function () {
    config(['session.driver' => 'database']);

    app('session')->forgetDrivers();
});

test('authentication has no implicit default guard or password broker', function () {
    expect(config('auth.defaults.guard'))->toBeNull()
        ->and(config('auth.defaults.passwords'))->toBeNull()
        ->and(config('auth.guards.admin.provider'))->toBe('admins')
        ->and(config('auth.guards.member.provider'))->toBe('members')
        ->and(config('auth.providers.admins.model'))->toBe(Admin::class)
        ->and(config('auth.providers.members.model'))->toBe(Member::class);
});

test('routes can be inspected without an implicit authentication guard', function () {
    expect(Artisan::call('route:list', ['--except-vendor' => true]))->toBe(0);
});

test('admin and member login pages are distinct', function () {
    $this->get(route('admin.login'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page->component('auth/AdminLogin'));

    $this->get(route('member.login'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page->component('auth/MemberLogin'));
});

test('admin can authenticate only with the admin guard', function () {
    $admin = Admin::factory()->create();

    $this->post(route('admin.login.store'), [
        'email' => $admin->email,
        'password' => 'password',
    ])->assertRedirect(route('admin.dashboard', absolute: false));

    $this->assertAuthenticatedAs($admin, 'admin');
    $this->assertGuest('member');
    expect($admin->refresh()->last_login_at)->not->toBeNull();

    $this->get(route('admin.dashboard'))->assertOk();

    expect(DB::table('sessions')
        ->where('admin_id', $admin->id)
        ->whereNull('member_id')
        ->exists())->toBeTrue();
});

test('member can authenticate only with the member guard', function () {
    $member = Member::factory()->create();

    $this->post(route('member.login.store'), [
        'email' => $member->email,
        'password' => 'password',
    ])->assertRedirect(route('member.dashboard', absolute: false));

    $this->assertAuthenticatedAs($member, 'member');
    $this->assertGuest('admin');
    expect($member->refresh()->last_login_at)->not->toBeNull();

    $this->get(route('member.dashboard'))->assertOk();

    expect(DB::table('sessions')
        ->whereNull('admin_id')
        ->where('member_id', $member->id)
        ->exists())->toBeTrue();
});

test('credentials cannot cross authentication providers', function () {
    $admin = Admin::factory()->create();
    $member = Member::factory()->create();

    $memberResponse = $this->post(route('member.login.store'), [
        'email' => $admin->email,
        'password' => 'password',
    ]);

    $memberResponse->assertSessionHasErrors([
        'email' => 'Email atau kata sandi tidak sesuai.',
    ]);

    $this->post(route('admin.login.store'), [
        'email' => $member->email,
        'password' => 'password',
    ])->assertSessionHasErrors('email');

    $this->assertGuest('admin');
    $this->assertGuest('member');
    expect(config('auth.defaults.guard'))->toBeNull();
});

test('inactive admin and member accounts cannot authenticate', function () {
    $admin = Admin::factory()->create(['is_active' => false]);
    $member = Member::factory()->create(['is_active' => false]);

    $this->post(route('admin.login.store'), [
        'email' => $admin->email,
        'password' => 'password',
    ])->assertSessionHasErrors('email');

    $this->post(route('member.login.store'), [
        'email' => $member->email,
        'password' => 'password',
    ])->assertSessionHasErrors('email');

    $this->assertGuest('admin');
    $this->assertGuest('member');
});

test('member can visit dashboard and logout', function () {
    $member = Member::factory()->create();

    $this->actingAs($member, 'member')
        ->get(route('member.dashboard'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('member/Dashboard')
            ->where('auth.member.id', $member->id)
            ->where('auth.admin', null));

    $this->post(route('member.logout'))
        ->assertRedirect(route('member.login'));

    $this->assertGuest('member');
});
