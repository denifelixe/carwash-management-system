<?php

use App\Models\Admin;
use App\Models\AdminModule;
use App\Models\AdminRole;

/*
 * A printed shift recap carries a QR back to the console. The receipt's is
 * rendered with the slip because the server knows the whole address up front; a
 * recap's does not exist until the desk has picked a day and a shift tab, so it
 * is encoded here on request instead.
 */

/**
 * @param  array<string, bool>  $abilities
 */
function recapStaff(string $moduleKey, array $abilities): Admin
{
    $role = AdminRole::query()->create([
        'key' => 'recap_'.uniqid(),
        'name' => 'Recap Staff',
        'description' => 'Role uji akses rekap.',
        'is_active' => true,
    ]);

    $role->modules()->attach(
        AdminModule::query()->where('key', $moduleKey)->firstOrFail(),
        [
            'can_create' => false,
            'can_read' => $abilities['read'] ?? false,
            'can_update' => false,
            'can_delete' => false,
        ],
    );

    return Admin::factory()->create(['role_id' => $role->id]);
}

test('the console encodes a recap QR for the day and shift it was asked for', function () {
    $owner = Admin::factory()->create(['is_owner' => true]);

    $response = $this->actingAs($owner, 'admin')->get(route('admin.recap.qr', [
        'page' => 'finance',
        'date' => '2026-09-01',
        'shift' => 'all',
    ]));

    $response->assertOk()->assertHeader('Content-Type', 'image/svg+xml');

    expect($response->getContent())->toStartWith('<?xml');
});

test('the QR encodes an address the console builds, never one the caller hands over', function () {
    $owner = Admin::factory()->create(['is_owner' => true]);

    /*
     * The destination is assembled from the page key and the two filters, so a
     * caller cannot put its own address behind the outlet's domain.
     */
    $this->actingAs($owner, 'admin')
        ->get(route('admin.recap.qr', ['page' => 'https://evil.test']))
        ->assertNotFound();

    $this->actingAs($owner, 'admin')
        ->get(route('admin.recap.qr'))
        ->assertNotFound();

    // A date or shift it cannot make sense of is dropped, not passed through.
    $this->actingAs($owner, 'admin')
        ->get(route('admin.recap.qr', [
            'page' => 'finance',
            'date' => 'https://evil.test',
            'shift' => '../../etc',
        ]))
        ->assertOk();
});

test('each recap QR is gated by the console it points at', function () {
    $this->actingAs(recapStaff('finance', ['read' => true]), 'admin')
        ->get(route('admin.recap.qr', ['page' => 'finance']))
        ->assertOk();

    // Finance access does not open the POS recap, and the reverse holds too.
    $this->actingAs(recapStaff('finance', ['read' => true]), 'admin')
        ->get(route('admin.recap.qr', ['page' => 'pos']))
        ->assertForbidden();

    $this->actingAs(recapStaff('pos', ['read' => true]), 'admin')
        ->get(route('admin.recap.qr', ['page' => 'finance']))
        ->assertForbidden();
});

test('a guest cannot ask the console for a recap QR', function () {
    $this->get(route('admin.recap.qr', ['page' => 'finance']))
        ->assertRedirect(route('admin.login'));
});
