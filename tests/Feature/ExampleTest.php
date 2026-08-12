<?php

use Inertia\Testing\AssertableInertia as Assert;

test('the business selector is the home page', function () {
    $response = $this->get(route('home'));

    $response
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page->component('Welcome'));
});
