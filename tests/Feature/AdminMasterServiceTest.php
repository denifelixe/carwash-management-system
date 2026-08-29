<?php

use App\Models\Admin;
use App\Models\AdminModule;
use App\Models\AdminRole;
use App\Models\Order;
use App\Models\Service;
use Inertia\Testing\AssertableInertia;

/**
 * @param  array<string, bool>  $abilities
 */
function serviceStaff(array $abilities): Admin
{
    $role = AdminRole::query()->create([
        'key' => 'master_'.uniqid(),
        'name' => 'Master Staff',
        'description' => 'Role uji akses master layanan.',
        'is_active' => true,
    ]);

    $role->modules()->attach(
        AdminModule::query()->where('key', 'master_services')->firstOrFail(),
        [
            'can_create' => $abilities['create'] ?? false,
            'can_read' => $abilities['read'] ?? false,
            'can_update' => $abilities['update'] ?? false,
            'can_delete' => $abilities['delete'] ?? false,
        ],
    );

    return Admin::factory()->create(['role_id' => $role->id]);
}

/**
 * @return array<string, mixed>
 */
function servicePayload(array $overrides = []): array
{
    return array_merge([
        'name' => 'Cuci Kilat',
        'category' => 'Cuci Mobil',
        'price' => 55000,
        'stamps' => 1,
        'icon' => '🚿',
        'description' => 'Cuci cepat 1 kali proses.',
        'is_popular' => false,
        'is_active' => true,
    ], $overrides);
}

test('guests cannot open the master service module', function () {
    $this->get(route('admin.master.services.index'))
        ->assertRedirect(route('admin.login'));
});

test('an owner sees the service list and an expandable master sidebar group', function () {
    $owner = Admin::factory()->create(['is_owner' => true]);
    Service::factory()->count(3)->create();

    $this->actingAs($owner, 'admin')
        ->get(route('admin.master.services.index'))
        ->assertOk()
        ->assertInertia(
            fn (AssertableInertia $page) => $page
                ->component('admin/master/Services')
                ->where('mode', 'live')
                ->has('services', 3)
                ->has('categories')
                ->where('capabilities.create', true)
                ->where('capabilities.update', true)
                ->where('capabilities.delete', true)
                ->where('modules.10.key', 'master')
                ->where('modules.10.href', null)
                ->where('modules.10.active', true)
                ->where('modules.10.children.0.key', 'master_services')
                ->where('modules.10.children.0.enabled', true)
                ->where('modules.10.children.0.active', true)
                ->where('modules.10.children.0.href', route('admin.master.services.index', absolute: false)),
        );
});

test('an owner can create a service', function () {
    $owner = Admin::factory()->create(['is_owner' => true]);

    $this->actingAs($owner, 'admin')
        ->post(route('admin.master.services.store'), servicePayload())
        ->assertRedirect(route('admin.master.services.index'))
        ->assertSessionHasNoErrors();

    $service = Service::query()->where('name', 'Cuci Kilat')->firstOrFail();

    expect($service)
        ->category->toBe('Cuci Mobil')
        ->price->toBe(55000)
        ->stamps->toBe(1)
        ->icon->toBe('🚿')
        ->is_popular->toBeFalse()
        ->is_active->toBeTrue();
});

test('an owner can update a service and deactivate it', function () {
    $owner = Admin::factory()->create(['is_owner' => true]);
    $service = Service::factory()->create(['name' => 'Cuci Lama', 'is_active' => true]);

    $this->actingAs($owner, 'admin')
        ->patch(route('admin.master.services.update', $service), servicePayload([
            'name' => 'Cuci Baru',
            'price' => 75000,
            'is_active' => false,
            'is_popular' => true,
        ]))
        ->assertRedirect(route('admin.master.services.index'))
        ->assertSessionHasNoErrors();

    expect($service->refresh())
        ->name->toBe('Cuci Baru')
        ->price->toBe(75000)
        ->is_active->toBeFalse()
        ->is_popular->toBeTrue();
});

test('service names stay unique', function () {
    $owner = Admin::factory()->create(['is_owner' => true]);
    Service::factory()->create(['name' => 'Cuci Kilat']);

    $this->actingAs($owner, 'admin')
        ->post(route('admin.master.services.store'), servicePayload())
        ->assertSessionHasErrors('name');

    expect(Service::query()->where('name', 'Cuci Kilat')->count())->toBe(1);
});

test('only icons from the shared picker list are accepted', function () {
    $owner = Admin::factory()->create(['is_owner' => true]);

    $this->actingAs($owner, 'admin')
        ->post(route('admin.master.services.store'), servicePayload(['icon' => '🍕']))
        ->assertSessionHasErrors('icon');

    expect(Service::query()->where('name', 'Cuci Kilat')->exists())->toBeFalse();
});

test('an owner can delete a service that no order uses', function () {
    $owner = Admin::factory()->create(['is_owner' => true]);
    $service = Service::factory()->create();

    $this->actingAs($owner, 'admin')
        ->delete(route('admin.master.services.destroy', $service))
        ->assertRedirect(route('admin.master.services.index'))
        ->assertSessionHasNoErrors();

    expect(Service::query()->find($service->id))->toBeNull();
});

test('a service already used by an order cannot be deleted', function () {
    $owner = Admin::factory()->create(['is_owner' => true]);
    $service = Service::factory()->create();
    $order = Order::factory()->create();
    $order->services()->attach($service, [
        'service_name' => $service->name,
        'unit_price' => $service->price,
        'stamps' => $service->stamps,
    ]);

    $this->actingAs($owner, 'admin')
        ->from(route('admin.master.services.index'))
        ->delete(route('admin.master.services.destroy', $service))
        ->assertRedirect(route('admin.master.services.index'))
        ->assertSessionHasErrors('service');

    expect(Service::query()->find($service->id))->not->toBeNull();
});

test('staff without the master service module cannot open it', function () {
    $staff = serviceStaff(['read' => false]);

    $this->actingAs($staff, 'admin')
        ->get(route('admin.master.services.index'))
        ->assertForbidden();
});

test('read only staff see the list without write capabilities', function () {
    $staff = serviceStaff(['read' => true]);
    $service = Service::factory()->create();

    $this->actingAs($staff, 'admin')
        ->get(route('admin.master.services.index'))
        ->assertOk()
        ->assertInertia(
            fn (AssertableInertia $page) => $page
                ->component('admin/master/Services')
                ->where('capabilities.create', false)
                ->where('capabilities.update', false)
                ->where('capabilities.delete', false),
        );

    $this->actingAs($staff, 'admin')
        ->post(route('admin.master.services.store'), servicePayload())
        ->assertForbidden();

    $this->actingAs($staff, 'admin')
        ->patch(route('admin.master.services.update', $service), servicePayload())
        ->assertForbidden();

    $this->actingAs($staff, 'admin')
        ->delete(route('admin.master.services.destroy', $service))
        ->assertForbidden();
});
