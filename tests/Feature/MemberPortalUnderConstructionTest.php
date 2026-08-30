<?php

use App\Models\Admin;
use App\Models\Member;
use Inertia\Testing\AssertableInertia as Assert;

function memberUrl(string $path = '/'): string
{
    return 'https://'.config('domains.member').$path;
}

test('every member portal path answers with the under construction notice', function (string $path) {
    $this->get(memberUrl($path))
        ->assertServiceUnavailable()
        ->assertInertia(fn (Assert $page) => $page
            ->component('member/UnderConstruction')
            ->has('brand'));
})->with(['/', '/login', '/dashboard', '/halaman-yang-tidak-ada']);

/*
 * Without an uploaded app photo the notice used to fall back to the framework's
 * own logo, which says nothing about the outlet. It wears the shipped social
 * image instead, and the app photo as soon as one is saved.
 */
test('the under construction notice falls back to the shipped social image, not the framework logo', function () {
    $notice = file_get_contents(resource_path('js/pages/member/UnderConstruction.vue'));

    expect($notice)
        ->toContain("props.brand.photo ?? '/og-image.png'")
        ->not->toContain('AppLogoIcon');

    expect(public_path('og-image.png'))->toBeFile();

    $this->get(memberUrl())
        ->assertServiceUnavailable()
        ->assertInertia(fn (Assert $page) => $page->where('brand.photo', null));
});

test('member login is closed while the portal is under construction', function () {
    $member = Member::factory()->create();

    $this->post(route('member.login.store'), [
        'email' => $member->email,
        'password' => 'password',
    ])->assertServiceUnavailable();

    $this->assertGuest('member');
    expect($member->refresh()->last_login_at)->toBeNull();
});

test('an authenticated member also lands on the under construction notice', function () {
    $member = Member::factory()->create();

    $this->actingAs($member, 'member')
        ->get(route('member.dashboard'))
        ->assertServiceUnavailable()
        ->assertInertia(fn (Assert $page) => $page->component('member/UnderConstruction'));
});

test('the admin portal is untouched by the member portal gate', function () {
    $admin = Admin::factory()->create();

    $this->get(route('admin.login'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page->component('auth/AdminLogin'));

    $this->actingAs($admin, 'admin')
        ->get(route('admin.dashboard'))
        ->assertOk();
});

test('enabling the flag reopens the portal exactly as it was', function () {
    config(['app.member_portal_enabled' => true]);

    $this->get(route('member.login'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page->component('auth/MemberLogin'));

    $member = Member::factory()->create();

    $this->actingAs($member, 'member')
        ->get(route('member.dashboard'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page->component('member/Dashboard'));

    $this->get(memberUrl('/halaman-yang-tidak-ada'))->assertNotFound();
});
