<?php

use App\Models\User;

test('the application always declares a light color scheme', function () {
    $response = $this->get(route('demo.home'));

    $response
        ->assertOk()
        ->assertSee('<meta name="color-scheme" content="light">', false)
        ->assertDontSee('prefers-color-scheme', false)
        ->assertDontSee('class="dark"', false);

    expect(file_get_contents(resource_path('css/app.css')))
        ->toContain('color-scheme: light;');
});

test('appearance settings cannot be opened', function () {
    $this->actingAs(User::factory()->create())
        ->get('/settings/appearance')
        ->assertNotFound();
});
