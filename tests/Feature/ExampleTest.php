<?php

use Inertia\Testing\AssertableInertia as Assert;

test('the role picker is the home page', function () {
    $this->get(route('demo.home'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page->component('demo/auth/Entry'));
});

test('demo routes use the local demo domain without a carwash URL prefix', function () {
    expect(config('demo.mode'))->toBe('local')
        ->and(config('demo.domain'))->toBe('demo.carwash-management-system.test')
        ->and(config('demo.domains.live'))->toBe('carwash-demo.zenagital.id')
        ->and(parse_url(route('demo.home'), PHP_URL_HOST))->toBe('demo.carwash-management-system.test')
        ->and(parse_url(route('demo.admin.dashboard'), PHP_URL_HOST))->toBe('demo.carwash-management-system.test')
        ->and(parse_url(route('demo.member.dashboard'), PHP_URL_HOST))->toBe('demo.carwash-management-system.test')
        ->and(route('demo.admin.dashboard', absolute: false))->toBe('/admin/dashboard')
        ->and(route('demo.member.dashboard', absolute: false))->toBe('/member/dashboard');

    $this->get('https://carwash-management-system.test/admin/dashboard')->assertNotFound();

    $this->get('/carwash')->assertNotFound();
});
