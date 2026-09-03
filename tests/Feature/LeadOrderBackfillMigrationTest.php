<?php

use App\Models\Lead;
use App\Models\Member;
use App\Models\MemberVehicle;
use App\Models\Order;
use Carbon\CarbonImmutable;

function runLeadOrderBackfill(): void
{
    $migration = require database_path('migrations/2026_09_03_194053_backfill_leads_from_existing_non_member_orders.php');

    $migration->up();
}

test('historical non-member orders are idempotently grouped into leads and linked', function () {
    $olderOrder = Order::factory()->create([
        'customer_name' => 'Budi Lama',
        'customer_phone' => '081111111111',
        'vehicle_name' => 'Toyota Avanza',
        'vehicle_plate' => 'B 1234 ABC',
        'service_date' => '2026-07-01',
        'arrived_at' => '2026-07-01 08:00:00',
        'created_at' => '2026-07-01 08:00:00',
        'updated_at' => '2026-07-01 08:00:00',
    ]);
    $newerOrder = Order::factory()->create([
        'customer_name' => '  Budi   Santoso  ',
        'customer_phone' => '081222222222',
        'vehicle_name' => '  Toyota   Innova  ',
        'vehicle_plate' => 'B1234ABC',
        'service_date' => '2026-08-01',
        'arrived_at' => '2026-08-01 09:00:00',
        'created_at' => '2026-08-01 09:00:00',
        'updated_at' => '2026-08-01 09:00:00',
    ]);
    $memberOrder = Order::factory()->create([
        'member_id' => Member::factory()->create()->id,
        'vehicle_plate' => 'D5555XYZ',
    ]);

    runLeadOrderBackfill();
    runLeadOrderBackfill();

    $lead = Lead::query()->sole();

    expect($lead->name)->toBe('Budi Santoso')
        ->and($lead->phone)->toBe('081222222222')
        ->and($lead->vehicle_name)->toBe('Toyota Innova')
        ->and($lead->vehicle_plate)->toBe('B1234ABC')
        ->and($lead->created_at?->toDateTimeString())->toBe('2026-07-01 08:00:00')
        ->and($lead->updated_at?->toDateTimeString())->toBe('2026-08-01 09:00:00')
        ->and($olderOrder->refresh()->lead_id)->toBe($lead->id)
        ->and($newerOrder->refresh()->lead_id)->toBe($lead->id)
        ->and($memberOrder->refresh()->lead_id)->toBeNull();
});

test('the backfill reuses an existing lead without replacing its profile', function () {
    $lead = Lead::factory()->create([
        'name' => 'Nama Terbaru',
        'phone' => '089999999999',
        'vehicle_name' => 'Honda Brio',
        'vehicle_plate' => 'B8120DS',
    ]);
    $historicalOrder = Order::factory()->create([
        'customer_name' => 'Nama Lama',
        'customer_phone' => '081111111111',
        'vehicle_name' => 'Mobil Lama',
        'vehicle_plate' => 'B 8120 DS',
    ]);

    runLeadOrderBackfill();

    expect(Lead::query()->count())->toBe(1)
        ->and($lead->refresh()->name)->toBe('Nama Terbaru')
        ->and($lead->phone)->toBe('089999999999')
        ->and($lead->vehicle_name)->toBe('Honda Brio')
        ->and($historicalOrder->refresh()->lead_id)->toBe($lead->id);
});

test('a historical lead is marked converted when its plate now belongs to a member', function () {
    $member = Member::factory()->create();
    $vehicle = MemberVehicle::factory()->for($member)->create([
        'plate' => 'F1234OLD',
        'created_at' => CarbonImmutable::parse('2026-08-15 10:00:00'),
    ]);
    $historicalOrder = Order::factory()->create([
        'vehicle_plate' => $vehicle->plate,
        'service_date' => '2026-07-01',
        'created_at' => '2026-07-01 08:00:00',
    ]);

    runLeadOrderBackfill();

    $lead = Lead::query()->sole();

    expect($lead->converted_member_id)->toBe($member->id)
        ->and($lead->converted_at?->toDateTimeString())->toBe('2026-08-15 10:00:00')
        ->and($historicalOrder->refresh()->lead_id)->toBe($lead->id);
});
