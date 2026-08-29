<?php

use App\Models\Admin;
use App\Models\AdminModule;
use App\Models\AdminRole;
use App\Models\Member;
use Inertia\Testing\AssertableInertia;

test('guests are redirected to the login page', function () {
    $response = $this->get(route('admin.dashboard'));
    $response->assertRedirect(route('admin.login'));
});

test('authenticated admins can visit the dashboard', function () {
    $admin = Admin::factory()->create();
    $this->actingAs($admin, 'admin');

    $this->get(route('admin.dashboard'))
        ->assertOk()
        ->assertInertia(
            fn (AssertableInertia $page) => $page
                ->component('admin/Dashboard')
                ->where('mode', 'live')
                ->has('stats', 4)
                ->has('modules')
                ->has('filters')
                ->has('cashSummary')
                ->has('orderSummary')
        );
});

test('owner dashboard uses live member data and enables completed modules', function () {
    $owner = Admin::factory()->create(['is_owner' => true]);
    Member::factory()->count(2)->create();
    Member::factory()->create(['is_active' => false]);

    $this->actingAs($owner, 'admin');

    $this->get(route('admin.dashboard'))
        ->assertOk()
        ->assertInertia(
            fn (AssertableInertia $page) => $page
                ->component('admin/Dashboard')
                ->where('mode', 'live')
                ->where('stats.0.value', 'Rp 0')
                ->where('stats.1.value', '0')
                ->where('stats.2.value', '2')
                ->where('stats.2.caption', 'dari 3 member terdaftar')
                ->where('stats.3.value', '0')
                ->has('shifts', 0)
                ->has('notifications', 0)
                ->has('modules', 11)
                ->where('modules.0.key', 'dashboard')
                ->where('modules.0.enabled', true)
                ->where('modules.0.active', true)
                ->where('modules.1.enabled', true)
                ->where('modules.1.href', route('admin.orders.index', absolute: false))
                ->where('profileHref', route('admin.profile.edit', absolute: false))
                ->where('headerAction', null)
                ->where('exitAction.method', 'post')
        );
});

test('demo and live dashboards share the same inertia component', function () {
    $admin = Admin::factory()->create();

    $this->actingAs($admin, 'admin')
        ->get(route('admin.dashboard'))
        ->assertInertia(fn (AssertableInertia $page) => $page->component('admin/Dashboard'));

    $this->withSession(['carwash_role' => 'owner'])
        ->get(route('demo.admin.dashboard'))
        ->assertInertia(
            fn (AssertableInertia $page) => $page
                ->component('admin/Dashboard')
                ->where('mode', 'demo')
                ->where('profileHref', null)
                ->where('headerAction.label', 'Ganti role')
                ->where('modules.0.enabled', true)
        );
});

test('staff sidebar follows readable modules from its role', function () {
    $role = AdminRole::query()->create([
        'key' => 'test_cashier',
        'name' => 'Kasir',
        'description' => 'Akses kasir.',
        'is_active' => true,
    ]);
    $orders = AdminModule::query()->where('key', 'orders')->firstOrFail();
    $reports = AdminModule::query()->where('key', 'reports')->firstOrFail();

    $role->readableModules()->attach($orders, ['can_read' => true]);
    $role->readableModules()->attach($reports, ['can_read' => false]);

    $admin = Admin::factory()->create([
        'role_id' => $role->id,
        'is_owner' => false,
    ]);

    $this->actingAs($admin, 'admin')
        ->get(route('admin.dashboard'))
        ->assertOk()
        ->assertInertia(
            fn (AssertableInertia $page) => $page
                ->has('modules', 2)
                ->where('modules.0.key', 'dashboard')
                ->where('modules.1.key', 'orders')
                ->where('modules.1.enabled', true)
        );
});
