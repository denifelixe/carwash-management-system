<?php

use App\Models\Admin;
use App\Models\Member;

test('application domains are derived from the configured application url', function () {
    expect(config('domains.app'))->toBe('carwash-management-system.test')
        ->and(config('domains.admin'))->toBe('admin.carwash-management-system.test')
        ->and(config('domains.member'))->toBe('member.carwash-management-system.test')
        ->and(config('domains.admin_url'))->toBe('https://admin.carwash-management-system.test')
        ->and(config('domains.member_url'))->toBe('https://member.carwash-management-system.test')
        ->and(config('fortify.portals.admin.guard'))->toBe('admin')
        ->and(config('fortify.portals.member.guard'))->toBe('member');
});

test('the main domain sends visitors to the admin login', function () {
    $this->get('https://carwash-management-system.test/')
        ->assertRedirect(route('admin.login'));
});

test('the admin domain only serves admin authentication', function () {
    $this->get('https://admin.carwash-management-system.test/')
        ->assertRedirect(route('admin.login'));

    $this->get(route('admin.login'))->assertOk();

    $this->get('https://carwash-management-system.test/login')->assertNotFound();
    $this->get('https://member.carwash-management-system.test/login')->assertOk();
});

test('authenticated staff entering the admin domain are sent to their dashboard', function () {
    $admin = Admin::factory()->create();

    $this->actingAs($admin, 'admin')
        ->get('https://admin.carwash-management-system.test/')
        ->assertRedirect(route('admin.dashboard'));
});

test('the member domain serves member authentication without exposing admin routes', function () {
    $this->get('https://member.carwash-management-system.test/')
        ->assertRedirect(route('member.login'));

    $this->get('https://member.carwash-management-system.test/dashboard')
        ->assertRedirect(route('member.login'));

    $member = Member::factory()->create();

    $this->actingAs($member, 'member')
        ->get('https://member.carwash-management-system.test/')
        ->assertRedirect(route('member.dashboard'));
});

test('named routes generate urls on their intended domains', function () {
    expect(parse_url(route('home'), PHP_URL_HOST))->toBe('carwash-management-system.test')
        ->and(parse_url(route('admin.home'), PHP_URL_HOST))->toBe('admin.carwash-management-system.test')
        ->and(parse_url(route('admin.login'), PHP_URL_HOST))->toBe('admin.carwash-management-system.test')
        ->and(parse_url(route('admin.dashboard'), PHP_URL_HOST))->toBe('admin.carwash-management-system.test')
        ->and(parse_url(route('member.home'), PHP_URL_HOST))->toBe('member.carwash-management-system.test')
        ->and(parse_url(route('member.login'), PHP_URL_HOST))->toBe('member.carwash-management-system.test')
        ->and(parse_url(route('member.dashboard'), PHP_URL_HOST))->toBe('member.carwash-management-system.test')
        ->and(parse_url(route('demo.home'), PHP_URL_HOST))->toBe('demo.carwash-management-system.test');
});
