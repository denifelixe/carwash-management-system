<?php

use App\Models\Admin;
use App\Models\AdminModule;
use App\Models\AdminRole;
use App\Models\Member;
use App\Models\MemberVehicle;
use App\Models\Order;
use App\Models\Service;
use Inertia\Testing\AssertableInertia;

test('guests cannot open the live order module', function () {
    $this->get(route('admin.orders.index'))
        ->assertRedirect(route('admin.login'));
});

test('the live order page uses the shared component and database records', function () {
    $owner = Admin::factory()->create(['is_owner' => true]);
    $service = Service::factory()->create(['name' => 'Premium Wash']);
    $order = Order::factory()->create(['number' => 'ORD-TEST-001']);
    $order->services()->attach($service, [
        'service_name' => $service->name,
        'unit_price' => $service->price,
        'stamps' => $service->stamps,
    ]);

    $this->actingAs($owner, 'admin')
        ->get(route('admin.orders.index', ['date' => $order->service_date->toDateString()]))
        ->assertOk()
        ->assertInertia(
            fn (AssertableInertia $page) => $page
                ->component('admin/Orders')
                ->where('mode', 'live')
                ->where('orders.0.orderNo', 'ORD-TEST-001')
                ->where('orders.0.serviceIds.0', $service->id)
                ->where('capabilities.create', true)
                ->where('capabilities.update', true)
                ->where('modules.1.key', 'orders')
                ->where('modules.1.enabled', true)
                ->where('modules.1.active', true),
        );
});

test('an owner can create a member order with database priced services', function () {
    $owner = Admin::factory()->create(['is_owner' => true]);
    $member = Member::factory()->create();
    $vehicle = MemberVehicle::factory()->for($member)->create([
        'name' => 'Honda Brio',
        'plate' => 'B 1234 XYZ',
    ]);
    $services = Service::factory()->count(2)->sequence(
        ['price' => 45000, 'stamps' => 1],
        ['price' => 25000, 'stamps' => 0],
    )->create();

    $this->actingAs($owner, 'admin')
        ->post(route('admin.orders.store'), [
            'customer_mode' => 'existing',
            'member_id' => $member->id,
            'member_vehicle_id' => $vehicle->id,
            'customer_name' => '',
            'customer_phone' => '',
            'vehicle_name' => '',
            'vehicle_plate' => '',
            'service_ids' => $services->modelKeys(),
        ])
        ->assertRedirect(route('admin.orders.index'))
        ->assertSessionHasNoErrors();

    $order = Order::query()->latest('id')->firstOrFail();

    expect($order)
        ->member_id->toBe($member->id)
        ->member_vehicle_id->toBe($vehicle->id)
        ->customer_name->toBe($member->name)
        ->vehicle_plate->toBe('B1234XYZ')
        ->subtotal->toBe(70000)
        ->total->toBe(70000)
        ->status->toBe('menunggu')
        ->and($order->services()->count())->toBe(2);
});

test('an owner can create a non member order without registering a member', function () {
    $owner = Admin::factory()->create(['is_owner' => true]);
    $service = Service::factory()->create(['price' => 45000]);

    $this->actingAs($owner, 'admin')
        ->post(route('admin.orders.store'), [
            'customer_mode' => 'walk-in',
            'member_id' => null,
            'member_vehicle_id' => null,
            'customer_name' => 'Tamu Walk In',
            'customer_phone' => '081234567890',
            'vehicle_name' => 'Toyota Calya',
            'vehicle_plate' => 'b 9876 abc',
            'service_ids' => [$service->id],
        ])
        ->assertSessionHasNoErrors();

    expect(Order::query()->latest('id')->firstOrFail())
        ->member_id->toBeNull()
        ->customer_name->toBe('Tamu Walk In')
        ->vehicle_plate->toBe('B9876ABC');
});

test('a member vehicle must belong to the selected member', function () {
    $owner = Admin::factory()->create(['is_owner' => true]);
    $member = Member::factory()->create();
    $otherVehicle = MemberVehicle::factory()->create();
    $service = Service::factory()->create();

    $this->actingAs($owner, 'admin')
        ->post(route('admin.orders.store'), [
            'customer_mode' => 'existing',
            'member_id' => $member->id,
            'member_vehicle_id' => $otherVehicle->id,
            'service_ids' => [$service->id],
        ])
        ->assertSessionHasErrors('member_vehicle_id');

    expect(Order::query()->count())->toBe(0);
});

test('order status can be updated except when cashier has completed it', function () {
    $owner = Admin::factory()->create(['is_owner' => true]);
    $order = Order::factory()->create(['status' => 'menunggu']);

    $this->actingAs($owner, 'admin')
        ->patch(route('admin.orders.status.update', $order), ['status' => 'proses'])
        ->assertSessionHasNoErrors();

    expect($order->refresh()->status)->toBe('proses');

    $order->update(['status' => 'selesai']);

    $this->actingAs($owner, 'admin')
        ->patch(route('admin.orders.status.update', $order), ['status' => 'menunggu'])
        ->assertUnprocessable();
});

test('order access follows the role permission matrix', function () {
    $module = AdminModule::query()->where('key', 'orders')->firstOrFail();
    $role = AdminRole::query()->create([
        'key' => 'order_reader',
        'name' => 'Order Reader',
        'is_active' => true,
    ]);
    $role->modules()->attach($module, ['can_read' => true]);
    $admin = Admin::factory()->create(['role_id' => $role->id]);

    $this->actingAs($admin, 'admin')
        ->get(route('admin.orders.index'))
        ->assertOk();

    $this->actingAs($admin, 'admin')
        ->post(route('admin.orders.store'), [])
        ->assertForbidden();
});

test('a walk in order is refused when the plate already belongs to a member', function () {
    $owner = Admin::factory()->create(['is_owner' => true]);
    $member = Member::factory()->create(['name' => 'Sinta Melati']);
    MemberVehicle::factory()->for($member)->create(['plate' => 'B 8120 DS']);
    $service = Service::factory()->create();

    /* Typed with the spacing the member's own plate was not stored with. */
    $this->actingAs($owner, 'admin')
        ->post(route('admin.orders.store'), [
            'customer_mode' => 'walk-in',
            'customer_name' => 'Tamu Walk In',
            'customer_phone' => '081200001111',
            'vehicle_name' => 'Toyota Avanza',
            'vehicle_plate' => 'b8120ds',
            'service_ids' => [$service->id],
        ])
        ->assertSessionHasErrors('vehicle_plate');

    expect(Order::query()->count())->toBe(0);
});

test('a member vehicle keeps its plate in the one stored form', function () {
    $vehicle = MemberVehicle::factory()->create(['plate' => 'b  8120  ds']);

    expect($vehicle->refresh()->plate)->toBe('B8120DS');
});

test('demo and live order pages have one frontend source of truth', function () {
    expect(resource_path('js/pages/admin/Orders.vue'))->toBeFile()
        ->and(resource_path('js/pages/demo/admin/Orders.vue'))->not->toBeFile()
        ->and(file_get_contents(app_path('Http/Controllers/Demo/OrderController.php')))
        ->toContain("'admin/Orders'");
});
