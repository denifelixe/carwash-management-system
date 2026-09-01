<?php

use App\Models\Admin;
use App\Models\AdminModule;
use App\Models\AdminRole;
use App\Models\Member;
use App\Models\MemberVehicle;
use App\Models\Order;
use App\Models\Service;
use App\Models\ServiceVariation;
use Inertia\Testing\AssertableInertia;

test('guests cannot open the live order module', function () {
    $this->get(route('admin.orders.index'))
        ->assertRedirect(route('admin.login'));
});

test('the live order page uses the shared component and database records', function () {
    $owner = Admin::factory()->create(['is_owner' => true]);
    $service = Service::factory()->create(['name' => 'Premium Wash', 'variations' => ['Ukuran' => ['Small']]]);
    $variation = $service->serviceVariations()->firstOrFail();
    $variation->update(['variations' => ['Ukuran' => 'Small']]);
    $order = Order::factory()->create(['number' => 'ORD-TEST-001']);
    $order->serviceVariations()->attach($variation, [
        'service_name' => $service->name,
        'variations' => json_encode($variation->variations),
        'unit_price' => $service->price,
        'quantity' => 1,
        'total_price' => $service->price,
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
                ->where('services.0.variations.Ukuran.0', 'Small')
                ->where('services.0.serviceVariations.0.variations.Ukuran', 'Small')
                ->where('capabilities.create', true)
                ->where('capabilities.update', true)
                ->where('modules.1.key', 'orders')
                ->where('modules.1.enabled', true)
                ->where('modules.1.active', true),
        );
});

test('hidden admins are absent from the order crew list', function () {
    $owner = Admin::factory()->create(['is_owner' => true]);
    $visibleCrew = Admin::factory()->create(['name' => 'Visible Crew']);
    $hiddenCrew = Admin::factory()->create([
        'name' => 'Hidden Crew',
        'is_hidden' => true,
    ]);

    $response = $this->actingAs($owner, 'admin')->get(route('admin.orders.index'));
    $crewNames = collect($response->inertiaProps('crew'))->pluck('name');

    $response->assertOk();

    expect($crewNames)
        ->toContain($visibleCrew->name)
        ->not->toContain($hiddenCrew->name);
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
    $variations = $services->map(fn (Service $service) => $service->serviceVariations()->firstOrFail());

    $this->actingAs($owner, 'admin')
        ->post(route('admin.orders.store'), [
            'customer_mode' => 'existing',
            'member_id' => $member->id,
            'member_vehicle_id' => $vehicle->id,
            'customer_name' => '',
            'customer_phone' => '',
            'vehicle_name' => '',
            'vehicle_plate' => '',
            'items' => $variations->map(fn ($variation): array => [
                'service_variation_id' => $variation->id,
                'quantity' => 1,
            ])->all(),
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
        ->and($order->serviceVariations()->count())->toBe(2);
});

test('an owner can create a non member order without registering a member', function () {
    $owner = Admin::factory()->create(['is_owner' => true]);
    $service = Service::factory()->create(['price' => 45000]);
    $variation = $service->serviceVariations()->firstOrFail();

    $this->actingAs($owner, 'admin')
        ->post(route('admin.orders.store'), [
            'customer_mode' => 'walk-in',
            'member_id' => null,
            'member_vehicle_id' => null,
            'customer_name' => 'Tamu Walk In',
            'customer_phone' => '081234567890',
            'vehicle_name' => 'Toyota Calya',
            'vehicle_plate' => 'b 9876 abc',
            'items' => [['service_variation_id' => $variation->id, 'quantity' => 1]],
        ])
        ->assertSessionHasNoErrors();

    expect(Order::query()->latest('id')->firstOrFail())
        ->member_id->toBeNull()
        ->customer_name->toBe('Tamu Walk In')
        ->vehicle_plate->toBe('B9876ABC');
});

test('order cart supports quantity and different variations of the same service using database prices', function () {
    $owner = Admin::factory()->create(['is_owner' => true]);
    $member = Member::factory()->create();
    $vehicle = MemberVehicle::factory()->for($member)->create();
    $service = Service::factory()->create([
        'name' => 'Coating Lite',
        'variations' => ['Ukuran' => ['Small', 'Large']],
        'stamps' => 2,
    ]);
    $small = $service->serviceVariations()->firstOrFail();
    $small->update(['variations' => ['Ukuran' => 'Small'], 'price' => 100000]);
    $large = ServiceVariation::factory()->for($service)->create([
        'variations' => ['Ukuran' => 'Large'],
        'price' => 175000,
    ]);

    $this->actingAs($owner, 'admin')->post(route('admin.orders.store'), [
        'customer_mode' => 'existing',
        'member_id' => $member->id,
        'member_vehicle_id' => $vehicle->id,
        'items' => [
            ['service_variation_id' => $small->id, 'quantity' => 2],
            ['service_variation_id' => $large->id, 'quantity' => 1],
        ],
    ])->assertSessionHasNoErrors();

    $order = Order::query()->latest('id')->firstOrFail();
    $smallItem = $order->serviceVariations()->whereKey($small->id)->firstOrFail()->pivot;

    expect($order->subtotal)->toBe(375000)
        ->and($order->stamps_earned)->toBe(6)
        ->and($order->serviceVariations()->count())->toBe(2)
        ->and((int) $smallItem->quantity)->toBe(2)
        ->and((int) $smallItem->unit_price)->toBe(100000)
        ->and((int) $smallItem->total_price)->toBe(200000)
        ->and(json_decode($smallItem->variations, true))->toBe(['Ukuran' => 'Small']);

    $small->update(['price' => 999999, 'variations' => ['Ukuran' => 'Retired']]);
    expect((int) $order->serviceVariations()->whereKey($small->id)->firstOrFail()->pivot->unit_price)->toBe(100000);
});

test('inactive variations and duplicate cart rows are rejected', function () {
    $owner = Admin::factory()->create(['is_owner' => true]);
    $service = Service::factory()->create();
    $variation = $service->serviceVariations()->firstOrFail();
    $variation->update(['is_active' => false]);
    $payload = [
        'customer_mode' => 'walk-in',
        'customer_name' => 'Tamu',
        'customer_phone' => '0812',
        'vehicle_name' => 'Mobil',
        'vehicle_plate' => 'B123ABC',
        'items' => [
            ['service_variation_id' => $variation->id, 'quantity' => 1],
            ['service_variation_id' => $variation->id, 'quantity' => 2],
        ],
    ];

    $this->actingAs($owner, 'admin')->post(route('admin.orders.store'), $payload)
        ->assertSessionHasErrors('items.0.service_variation_id');
});

test('a member vehicle must belong to the selected member', function () {
    $owner = Admin::factory()->create(['is_owner' => true]);
    $member = Member::factory()->create();
    $otherVehicle = MemberVehicle::factory()->create();
    $service = Service::factory()->create();
    $variation = $service->serviceVariations()->firstOrFail();

    $this->actingAs($owner, 'admin')
        ->post(route('admin.orders.store'), [
            'customer_mode' => 'existing',
            'member_id' => $member->id,
            'member_vehicle_id' => $otherVehicle->id,
            'items' => [['service_variation_id' => $variation->id, 'quantity' => 1]],
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
    $variation = $service->serviceVariations()->firstOrFail();

    /* Typed with the spacing the member's own plate was not stored with. */
    $this->actingAs($owner, 'admin')
        ->post(route('admin.orders.store'), [
            'customer_mode' => 'walk-in',
            'customer_name' => 'Tamu Walk In',
            'customer_phone' => '081200001111',
            'vehicle_name' => 'Toyota Avanza',
            'vehicle_plate' => 'b8120ds',
            'items' => [['service_variation_id' => $variation->id, 'quantity' => 1]],
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
