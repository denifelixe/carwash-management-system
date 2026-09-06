<?php

use App\Models\Admin;
use App\Models\Lead;
use App\Models\Member;
use App\Models\Order;
use App\Models\Service;
use App\Support\VehiclePlate;

test('special mode belongs to each member vehicle independently', function () {
    $owner = Admin::factory()->create(['is_owner' => true]);

    $this->actingAs($owner, 'admin')->postJson(route('admin.members.store'), [
        'name' => 'Budi',
        'phone' => '081234567890',
        'vehicles' => [
            ['name' => 'Fortuner', 'plate' => '84348-00', 'type' => 'Mobil', 'is_special_plate' => true],
            ['name' => 'Avanza', 'plate' => '-NEW-', 'type' => 'Mobil'],
        ],
    ])->assertUnprocessable()
        ->assertJsonValidationErrors(['vehicles.1.plate'])
        ->assertJsonMissingValidationErrors(['vehicles.0.plate']);
});

test('special plates still require a value', function () {
    $owner = Admin::factory()->create(['is_owner' => true]);

    $this->actingAs($owner, 'admin')->postJson(route('admin.leads.store'), [
        'name' => 'Budi',
        'vehicle_plate' => '',
        'is_special_plate' => true,
    ])->assertUnprocessable()->assertJsonValidationErrors(['vehicle_plate']);
});

test('every plate submission validates ordinary plates and accepts explicit special plates', function (string $routeName, string $method, ?string $modelType, string $field, bool $special) {
    $owner = Admin::factory()->create(['is_owner' => true]);
    $record = match ($modelType) {
        'order' => Order::factory()->create(['member_id' => null, 'source' => 'booking', 'status' => 'booking']),
        'member' => Member::factory()->create(),
        'lead' => Lead::factory()->create(),
        default => null,
    };
    $variation = Service::factory()->create()->serviceVariations()->firstOrFail();
    $plate = $special ? '84348-00' : '-NEW-';
    $payload = [
        'customer_mode' => 'walk-in',
        'customer_name' => 'Budi',
        'customer_phone' => '081234567890',
        'name' => 'Budi',
        'phone' => '081234567890',
        'vehicle_name' => 'Toyota Avanza',
        'vehicle_plate' => $plate,
        'vehicles' => [['name' => 'Toyota Avanza', 'plate' => $plate, 'type' => 'Mobil']],
        'items' => [['service_variation_id' => $variation->id, 'quantity' => 1]],
        'service_date' => now()->addDay()->toDateString(),
    ];

    if ($special) {
        $payload['is_special_plate'] = true;
        $payload['vehicles'][0]['is_special_plate'] = true;
    }

    $response = $this->actingAs($owner, 'admin')
        ->{$method}(route($routeName, $record), $payload);

    if ($special) {
        $response->assertRedirect()->assertSessionHasNoErrors();
        $table = str_contains($routeName, 'members.') || str_contains($routeName, 'pos.')
            ? 'member_vehicles'
            : (str_contains($routeName, 'leads.') ? 'leads' : 'orders');
        $column = $table === 'member_vehicles' ? 'plate' : 'vehicle_plate';
        $this->assertDatabaseHas($table, [$column => $plate]);

        return;
    }

    $response->assertUnprocessable()
        ->assertJsonValidationErrors([$field => VehiclePlate::FORMAT_MESSAGE]);

    $this->assertDatabaseMissing('orders', ['vehicle_plate' => '-NEW-']);
    $this->assertDatabaseMissing('leads', ['vehicle_plate' => '-NEW-']);
    $this->assertDatabaseMissing('member_vehicles', ['plate' => '-NEW-']);
})->with([
    'create order' => ['admin.orders.store', 'postJson', null, 'vehicle_plate'],
    'update order' => ['admin.orders.update', 'patchJson', 'order', 'vehicle_plate'],
    'create booking' => ['admin.bookings.store', 'postJson', null, 'vehicle_plate'],
    'update booking' => ['admin.bookings.update', 'patchJson', 'order', 'vehicle_plate'],
    'cashier member' => ['admin.pos.member.store', 'postJson', 'order', 'vehicle_plate'],
    'create member' => ['admin.members.store', 'postJson', null, 'vehicles.0.plate'],
    'update member' => ['admin.members.update', 'patchJson', 'member', 'vehicles.0.plate'],
    'create lead' => ['admin.leads.store', 'postJson', null, 'vehicle_plate'],
    'update lead' => ['admin.leads.update', 'patchJson', 'lead', 'vehicle_plate'],
])->with([false, true]);

test('plate validation rejects incomplete and malformed plates', function (string $plate) {
    $owner = Admin::factory()->create(['is_owner' => true]);

    $this->actingAs($owner, 'admin')
        ->postJson(route('admin.leads.store'), ['name' => 'Budi', 'vehicle_plate' => $plate])
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['vehicle_plate']);

    $this->assertDatabaseCount('leads', 0);
})->with([
    'empty' => '',
    'placeholder' => '-new-',
    'military plate without special mode' => '84348-00',
    'letters only' => 'NEW',
    'missing number' => 'B CDE',
    'missing region' => '1234CDE',
    'region too long' => 'ABC1234D',
    'number too long' => 'B12345CD',
    'suffix too long' => 'B1234CDEF',
    'hyphens' => 'B-1234-CDE',
    'punctuation' => 'B1234CD!',
    'digits after suffix' => 'B1234CD5',
]);

test('plate validation accepts supported formats and stores canonical values', function (string $plate, string $canonical) {
    $owner = Admin::factory()->create(['is_owner' => true]);

    $this->actingAs($owner, 'admin')
        ->postJson(route('admin.leads.store'), ['name' => 'Budi', 'vehicle_plate' => $plate])
        ->assertRedirect()
        ->assertSessionHasNoErrors();

    $this->assertDatabaseHas('leads', ['vehicle_plate' => $canonical]);
})->with([
    'three columns' => ['B1234CDE', 'B1234CDE'],
    'lowercase and spaces' => [' b 1234 cde ', 'B1234CDE'],
    'two letter region' => ['AB1234CD', 'AB1234CD'],
    'one digit' => ['B1A', 'B1A'],
    'no suffix' => ['B1234', 'B1234'],
]);
