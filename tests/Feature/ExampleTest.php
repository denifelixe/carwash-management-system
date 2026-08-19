<?php

use Inertia\Testing\AssertableInertia as Assert;

test('the role picker is the home page', function () {
    $this->get(route('home'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page->component('carwash/auth/Entry'));
});

test('the legacy carwash url redirects to the home page', function () {
    $this->get('/carwash')->assertRedirect('/');
});
