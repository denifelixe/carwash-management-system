<?php

use Inertia\Testing\AssertableInertia as Assert;

test('the role picker is the home page', function () {
    $this->get(route('demo.home'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page->component('demo/auth/Entry'));
});

test('demo routes use the derived demo subdomain without a carwash URL prefix', function () {
    expect(config('domains.demo'))->toBe('demo.carwash-management-system.test')
        ->and(config('domains.demo_url'))->toBe('https://demo.carwash-management-system.test')
        ->and(parse_url(route('demo.home'), PHP_URL_HOST))->toBe('demo.carwash-management-system.test')
        ->and(parse_url(route('demo.admin.dashboard'), PHP_URL_HOST))->toBe('demo.carwash-management-system.test')
        ->and(parse_url(route('demo.member.dashboard'), PHP_URL_HOST))->toBe('demo.carwash-management-system.test')
        ->and(route('demo.admin.dashboard', absolute: false))->toBe('/admin/dashboard')
        ->and(route('demo.member.dashboard', absolute: false))->toBe('/member/dashboard');

    $this->get('https://carwash-management-system.test/admin/dashboard')->assertNotFound();

    $this->get('/carwash')->assertNotFound();
});
