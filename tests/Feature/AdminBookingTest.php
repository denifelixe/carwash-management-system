<?php

use App\Models\Admin;
use App\Models\Lead;
use App\Models\Member;
use App\Models\MemberVehicle;
use App\Models\Order;
use App\Models\Service;
use Inertia\Testing\AssertableInertia;

test('guests cannot open the live booking module', function () {
    $this->get(route('admin.bookings.index'))
        ->assertRedirect(route('admin.login'));
});

test('the live booking page uses the shared component and database records', function () {
    $owner = Admin::factory()->create(['is_owner' => true]);
    $service = Service::factory()->create(['name' => 'Premium Wash']);
    $variation = $service->serviceVariations()->firstOrFail();
    $booking = Order::factory()->create([
        'number' => 'ORD-BK-TEST-001',
        'source' => 'booking',
        'status' => 'booking',
        'service_date' => now()->addDay()->toDateString(),
        'arrived_at' => null,
        'booking_date' => now()->toDateString(),
    ]);
    $booking->serviceVariations()->attach($variation, [
        'service_name' => $service->name,
        'variations' => null,
        'unit_price' => $service->price,
        'quantity' => 1,
        'total_price' => $service->price,
        'stamps' => $service->stamps,
    ]);

    $this->actingAs($owner, 'admin')
        ->get(route('admin.bookings.index'))
        ->assertOk()
        ->assertInertia(
            fn (AssertableInertia $page) => $page
                ->component('admin/Bookings')
                ->where('mode', 'live')
                ->where('bookings.0.code', 'ORD-BK-TEST-001')
                ->where('bookings.0.orderStatus', 'booking')
                ->where('bookings.0.serviceIds.0', $service->id)
                ->where('capabilities.create', true)
                ->where('capabilities.update', true)
                ->where('capabilities.delete', true)
                ->where('bookings.0.isMutable', true)
                ->where('bookings.0.isDeletable', true),
        );
});

test('an owner can create a member booking at database prices', function () {
    $owner = Admin::factory()->create(['is_owner' => true]);
    $member = Member::factory()->create();
    $vehicle = MemberVehicle::factory()->for($member)->create([
        'name' => 'Honda Brio',
        'plate' => 'B 1234 XYZ',
    ]);
    $services = Service::factory()->count(2)->sequence(
        ['price' => 45000, 'stamps' => 1],
        ['price' => 25000, 'stamps' => 2],
    )->create();
    $variations = $services->map(fn (Service $service) => $service->serviceVariations()->firstOrFail());
    $serviceDate = now()->addDays(2)->toDateString();

    $this->actingAs($owner, 'admin')
        ->post(route('admin.bookings.store'), [
            'customer_mode' => 'existing',
            'member_id' => $member->id,
            'member_vehicle_id' => $vehicle->id,
            'items' => $variations->map(fn ($variation): array => [
                'service_variation_id' => $variation->id,
                'quantity' => 1,
            ])->all(),
            'service_date' => $serviceDate,
        ])
        ->assertRedirect(route('admin.bookings.index'))
        ->assertSessionHasNoErrors();

    $booking = Order::query()->latest('id')->firstOrFail();

    expect($booking)
        ->member_id->toBe($member->id)
        ->member_vehicle_id->toBe($vehicle->id)
        ->source->toBe('booking')
        ->status->toBe('booking')
        ->service_date->toDateString()->toBe($serviceDate)
        ->booking_date->toDateString()->toBe(now()->toDateString())
        ->arrived_at->toBeNull()
        ->subtotal->toBe(70000)
        ->total->toBe(70000)
        ->stamps_earned->toBe(3)
        ->and($booking->serviceVariations()->count())->toBe(2);
});

test('an owner can update a booking that has not entered processing', function () {
    $owner = Admin::factory()->create(['is_owner' => true]);
    $oldService = Service::factory()->create(['price' => 45000]);
    $newService = Service::factory()->create(['price' => 80000]);
    $oldVariation = $oldService->serviceVariations()->firstOrFail();
    $newVariation = $newService->serviceVariations()->firstOrFail();
    $booking = Order::factory()->create([
        'source' => 'booking',
        'status' => 'booking',
        'service_date' => now()->addDay()->toDateString(),
        'arrived_at' => null,
        'booking_date' => now()->toDateString(),
    ]);
    $booking->serviceVariations()->attach($oldVariation, [
        'service_name' => $oldService->name,
        'variations' => null,
        'unit_price' => $oldService->price,
        'quantity' => 1,
        'total_price' => $oldService->price,
        'stamps' => $oldService->stamps,
    ]);
    $newDate = now()->addDays(3)->toDateString();

    $this->actingAs($owner, 'admin')
        ->patch(route('admin.bookings.update', $booking), [
            'customer_mode' => 'walk-in',
            'customer_name' => 'Tamu Booking',
            'customer_phone' => '081234567890',
            'vehicle_name' => 'Toyota Calya',
            'vehicle_plate' => 'b 9876 abc',
            'items' => [['service_variation_id' => $newVariation->id, 'quantity' => 1]],
            'service_date' => $newDate,
        ])
        ->assertRedirect(route('admin.bookings.index'))
        ->assertSessionHasNoErrors();

    expect($booking->refresh())
        ->customer_name->toBe('Tamu Booking')
        ->vehicle_plate->toBe('B9876ABC')
        ->service_date->toDateString()->toBe($newDate)
        ->total->toBe(80000)
        ->and($booking->serviceVariations()->sole()->is($newVariation))->toBeTrue();
});

test('booking totals include variation quantity and preserve its snapshots', function () {
    $owner = Admin::factory()->create(['is_owner' => true]);
    $service = Service::factory()->create(['name' => 'Salon', 'stamps' => 3]);
    $variation = $service->serviceVariations()->firstOrFail();
    $variation->update(['price' => 125000]);

    $this->actingAs($owner, 'admin')->post(route('admin.bookings.store'), [
        'customer_mode' => 'walk-in',
        'customer_name' => 'Booking Qty',
        'customer_phone' => '08123',
        'vehicle_name' => 'Avanza',
        'vehicle_plate' => 'B1234QQ',
        'items' => [['service_variation_id' => $variation->id, 'quantity' => 3]],
        'service_date' => now()->addDay()->toDateString(),
    ])->assertSessionHasNoErrors();

    $booking = Order::query()->latest('id')->firstOrFail();
    $pivot = $booking->serviceVariations()->firstOrFail()->pivot;
    expect($booking->subtotal)->toBe(375000)
        ->and((int) $pivot->quantity)->toBe(3)
        ->and((int) $pivot->total_price)->toBe(375000);
});

test('a booking cannot be moved to the past or edited after processing starts', function () {
    $owner = Admin::factory()->create(['is_owner' => true]);
    $service = Service::factory()->create();
    $variation = $service->serviceVariations()->firstOrFail();
    $booking = Order::factory()->create([
        'source' => 'booking',
        'status' => 'booking',
        'service_date' => now()->addDay()->toDateString(),
        'arrived_at' => null,
    ]);
    $payload = [
        'customer_mode' => 'walk-in',
        'customer_name' => 'Tamu Booking',
        'customer_phone' => '081234567890',
        'vehicle_name' => 'Toyota Calya',
        'vehicle_plate' => 'B1234ABC',
        'items' => [['service_variation_id' => $variation->id, 'quantity' => 1]],
        'service_date' => now()->subDay()->toDateString(),
    ];

    $this->actingAs($owner, 'admin')
        ->patch(route('admin.bookings.update', $booking), $payload)
        ->assertSessionHasErrors('service_date');

    $booking->update(['status' => 'menunggu']);

    $this->actingAs($owner, 'admin')
        ->patch(route('admin.bookings.update', $booking), [
            ...$payload,
            'service_date' => now()->addDays(2)->toDateString(),
        ])
        ->assertUnprocessable();
});

test('a booking older than H-30 cannot be edited or deleted', function () {
    $owner = Admin::factory()->create(['is_owner' => true]);
    $service = Service::factory()->create();
    $variation = $service->serviceVariations()->firstOrFail();
    $booking = Order::factory()->create([
        'source' => 'booking',
        'status' => 'booking',
        'service_date' => now()->subDays(31),
        'arrived_at' => null,
    ]);
    $payload = [
        'customer_mode' => 'walk-in',
        'customer_name' => 'Tamu Booking',
        'customer_phone' => '081234567890',
        'vehicle_name' => 'Toyota Calya',
        'vehicle_plate' => 'B1234ABC',
        'items' => [['service_variation_id' => $variation->id, 'quantity' => 1]],
        'service_date' => now()->addDay()->toDateString(),
    ];

    $this->actingAs($owner, 'admin')
        ->patch(route('admin.bookings.update', $booking), $payload)
        ->assertUnprocessable();
    $this->actingAs($owner, 'admin')
        ->delete(route('admin.bookings.destroy', $booking))
        ->assertUnprocessable();

    $this->assertNotSoftDeleted($booking);
});

test('an owner can soft delete a booking inside the mutation window', function () {
    $owner = Admin::factory()->create(['is_owner' => true]);
    $booking = Order::factory()->create([
        'source' => 'booking',
        'status' => 'booking',
        'service_date' => now()->subDays(30),
        'arrived_at' => null,
    ]);

    $this->actingAs($owner, 'admin')
        ->delete(route('admin.bookings.destroy', $booking))
        ->assertRedirect(route('admin.bookings.index'))
        ->assertSessionHasNoErrors();

    $this->assertSoftDeleted($booking);
    expect(Order::withTrashed()->findOrFail($booking->id)->deleted_by_admin_id)->toBe($owner->id);
});

test('demo and live booking pages have one frontend source of truth', function () {
    expect(resource_path('js/pages/admin/Bookings.vue'))->toBeFile()
        ->and(resource_path('js/pages/demo/admin/Bookings.vue'))->not->toBeFile()
        ->and(file_get_contents(app_path('Http/Controllers/Demo/BookingController.php')))
        ->toContain("'admin/Bookings'");
});

test('a non member booking files a lead the same way a walk-in order does', function () {
    $owner = Admin::factory()->create(['is_owner' => true]);
    $service = Service::factory()->create();
    $variation = $service->serviceVariations()->firstOrFail();

    $this->actingAs($owner, 'admin')->post(route('admin.bookings.store'), [
        'customer_mode' => 'walk-in',
        'customer_name' => 'Tamu Booking',
        'customer_phone' => '081234567890',
        'vehicle_name' => 'Toyota Calya',
        'vehicle_plate' => 'b 9876 abc',
        'items' => [['service_variation_id' => $variation->id, 'quantity' => 1]],
        'service_date' => now()->addDay()->toDateString(),
    ])->assertSessionHasNoErrors();

    $lead = Lead::query()->sole();
    $booking = Order::query()->latest('id')->firstOrFail();

    expect($lead->vehicle_plate)->toBe('B9876ABC')
        ->and($lead->name)->toBe('Tamu Booking')
        ->and($booking->lead_id)->toBe($lead->id);
});

test('a member booking files no lead', function () {
    $owner = Admin::factory()->create(['is_owner' => true]);
    $member = Member::factory()->create();
    $vehicle = MemberVehicle::factory()->for($member)->create();
    $service = Service::factory()->create();
    $variation = $service->serviceVariations()->firstOrFail();

    $this->actingAs($owner, 'admin')->post(route('admin.bookings.store'), [
        'customer_mode' => 'existing',
        'member_id' => $member->id,
        'member_vehicle_id' => $vehicle->id,
        'items' => [['service_variation_id' => $variation->id, 'quantity' => 1]],
        'service_date' => now()->addDay()->toDateString(),
    ])->assertSessionHasNoErrors();

    expect(Lead::query()->count())->toBe(0)
        ->and(Order::query()->latest('id')->firstOrFail()->lead_id)->toBeNull();
});
