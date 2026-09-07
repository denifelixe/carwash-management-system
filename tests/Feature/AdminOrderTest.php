<?php

use App\Http\Middleware\HandleInertiaRequests;
use App\Models\Admin;
use App\Models\AdminModule;
use App\Models\AdminRole;
use App\Models\Lead;
use App\Models\Member;
use App\Models\MemberVehicle;
use App\Models\Order;
use App\Models\OrderTransaction;
use App\Models\Service;
use App\Models\ServiceVariation;
use App\Support\Admin\OrderQueries;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use Inertia\Testing\AssertableInertia;

test('guests cannot open the live order module', function () {
    $this->get(route('admin.orders.index'))
        ->assertRedirect(route('admin.login'));
});

test('order handler columns exist', function () {
    expect(Schema::hasColumns('orders', [
        'handled_by_admin_id',
        'handled_by',
    ]))->toBeTrue();
});

test('earlier unfinished orders are separate from the selected day and sorted oldest first', function (int $daysAgo) {
    $owner = Admin::factory()->create(['is_owner' => true]);
    $date = today()->subDays($daysAgo);
    $expected = [];

    foreach (['booking', 'menunggu', 'proses', 'pelunasan'] as $index => $status) {
        $expected[] = Order::factory()->create([
            'status' => $status,
            'service_date' => $date->copy()->subDays(4 - $index),
        ])->id;
    }

    foreach (['selesai', 'batal'] as $status) {
        Order::factory()->create(['status' => $status, 'service_date' => $date->copy()->subDays(5)]);
    }

    $selected = Order::factory()->create(['service_date' => $date]);
    Order::factory()->create(['service_date' => $date->copy()->addDay()]);

    $this->actingAs($owner, 'admin')
        ->get(route('admin.orders.index', $daysAgo === 0 ? [] : ['date' => $date->toDateString()]))
        ->assertSuccessful()
        ->assertInertia(fn (AssertableInertia $page) => $page
            ->has('orders', 1)
            ->where('orders.0.id', $selected->id)
            ->has('previousOrders', 4)
            ->where('previousOrders', fn ($orders): bool => collect($orders)->pluck('id')->all() === $expected));
})->with([0, 2]);

test('an earlier order disappears from the unfinished section after cancellation', function () {
    $owner = Admin::factory()->create(['is_owner' => true]);
    $order = Order::factory()->create(['service_date' => today()->subDay()]);

    $this->actingAs($owner, 'admin')
        ->patch(route('admin.orders.status.update', $order), ['status' => 'batal'])
        ->assertSessionHasNoErrors();

    $this->get(route('admin.orders.index'))
        ->assertSuccessful()
        ->assertInertia(fn (AssertableInertia $page) => $page->has('previousOrders', 0));
});

test('the live order page uses the shared component and database records', function () {
    $owner = Admin::factory()->create(['is_owner' => true]);
    $service = Service::factory()->create(['name' => 'Premium Wash', 'variations' => ['Ukuran' => ['Small']]]);
    $variation = $service->serviceVariations()->firstOrFail();
    $variation->update(['variations' => ['Ukuran' => 'Small']]);
    $order = Order::factory()->create([
        'number' => 'ORD-TEST-001',
        'created_by_admin_id' => $owner->id,
        'handled_by' => 'Pak Joko',
    ]);
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
                ->where('orders.0.inputBy', $owner->name)
                ->where('orders.0.handledByAdminId', null)
                ->where('orders.0.handledByManual', 'Pak Joko')
                ->where('orders.0.handledBy', 'Pak Joko')
                ->where('orders.0.serviceIds.0', $service->id)
                ->where('services.0.variations.Ukuran.0', 'Small')
                ->where('services.0.serviceVariations.0.variations.Ukuran', 'Small')
                ->where('capabilities.create', true)
                ->where('capabilities.update', true)
                ->where('capabilities.delete', true)
                ->where('orders.0.isMutable', true)
                ->where('orders.0.isDeletable', true)
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
            'created_by_admin_id' => 999999,
            'handled_by_admin_id' => $owner->id,
            'handled_by' => '  Pak   Joko  ',
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
        ->created_by_admin_id->toBe($owner->id)
        ->handled_by_admin_id->toBe($owner->id)
        ->handled_by->toBe('Pak Joko')
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
        ->created_by_admin_id->toBe($owner->id)
        ->handled_by->toBeNull()
        ->vehicle_plate->toBe('B9876ABC');
});

test('an admin with update access can edit an unpaid unfinished order', function () {
    $owner = Admin::factory()->create(['is_owner' => true]);
    $order = Order::factory()->create([
        'customer_name' => 'Pelanggan Lama',
        'subtotal' => 45000,
        'total' => 45000,
        'paid_amount' => 0,
    ]);
    $service = Service::factory()->create(['price' => 75000, 'stamps' => 2]);
    $variation = $service->serviceVariations()->firstOrFail();

    $this->actingAs($owner, 'admin')
        ->patch(route('admin.orders.update', $order), [
            'customer_mode' => 'walk-in',
            'customer_name' => '  Pelanggan   Baru  ',
            'customer_phone' => '081234567890',
            'vehicle_name' => '  Toyota   Innova  ',
            'vehicle_plate' => 'b 1234 xyz',
            'handled_by' => '  Pak   Budi  ',
            'items' => [['service_variation_id' => $variation->id, 'quantity' => 2]],
        ])
        ->assertSessionHasNoErrors();

    expect($order->refresh())
        ->customer_name->toBe('Pelanggan Baru')
        ->customer_phone->toBe('081234567890')
        ->vehicle_name->toBe('Toyota Innova')
        ->vehicle_plate->toBe('B1234XYZ')
        ->handled_by->toBe('Pak Budi')
        ->subtotal->toBe(150000)
        ->total->toBe(150000)
        ->member_id->toBeNull()
        ->stamps_earned->toBe(0)
        ->and($order->serviceVariations()->firstOrFail()->pivot->quantity)->toBe(2);
});

test('an order without transactions can be edited regardless of its status', function (array $attributes) {
    $owner = Admin::factory()->create(['is_owner' => true]);
    $order = Order::factory()->create($attributes);
    $service = Service::factory()->create(['price' => 75000]);
    $variation = $service->serviceVariations()->firstOrFail();

    $this->actingAs($owner, 'admin')
        ->patch(route('admin.orders.update', $order), [
            'customer_mode' => 'walk-in',
            'customer_name' => 'Tidak Berubah',
            'customer_phone' => '081234567890',
            'vehicle_name' => 'Toyota Innova',
            'vehicle_plate' => 'B1234XYZ',
            'items' => [['service_variation_id' => $variation->id, 'quantity' => 2]],
        ])
        ->assertRedirect()
        ->assertSessionHasNoErrors();

    expect($order->refresh()->customer_name)->toBe('Tidak Berubah');
})->with([
    'lunas' => [['status' => 'proses', 'total' => 45000, 'paid_amount' => 45000]],
    'selesai' => [['status' => 'selesai', 'total' => 45000, 'paid_amount' => 0]],
]);

test('order services cannot be changed until the transaction is deleted', function () {
    $owner = Admin::factory()->create(['is_owner' => true]);
    $order = Order::factory()->create(['total' => 100000, 'paid_amount' => 50000]);
    $transaction = OrderTransaction::factory()->create([
        'order_id' => $order->id,
        'amount' => 50000,
    ]);
    $service = Service::factory()->create(['price' => 50000]);
    $variation = $service->serviceVariations()->firstOrFail();

    $this->actingAs($owner, 'admin')
        ->patch(route('admin.orders.update', $order), [
            'customer_mode' => 'walk-in',
            'customer_name' => 'Pelanggan',
            'customer_phone' => '081234567890',
            'vehicle_name' => 'Toyota Innova',
            'vehicle_plate' => 'B1234XYZ',
            'items' => [['service_variation_id' => $variation->id, 'quantity' => 1]],
        ])
        ->assertSessionHasErrors('items');

    expect($order->refresh()->total)->toBe(100000);

    $transaction->delete();

    $this->actingAs($owner, 'admin')
        ->patch(route('admin.orders.update', $order), [
            'customer_mode' => 'walk-in',
            'customer_name' => 'Pelanggan Baru',
            'customer_phone' => '081234567890',
            'vehicle_name' => 'Toyota Innova',
            'vehicle_plate' => 'B1234XYZ',
            'items' => [['service_variation_id' => $variation->id, 'quantity' => 2]],
        ])
        ->assertSessionHasNoErrors();

    expect($order->refresh()->customer_name)->toBe('Pelanggan Baru');
});

test('order handler prioritizes admin then manual name then the order creator', function () {
    $owner = Admin::factory()->create(['is_owner' => true]);
    $inputAdmin = Admin::factory()->create(['name' => 'Kasir Pembuat']);
    $order = Order::factory()->create([
        'status' => 'proses',
        'created_by_admin_id' => $inputAdmin->id,
        'handled_by' => 'Petugas Lama',
    ]);

    $this->actingAs($owner, 'admin')
        ->patch(route('admin.orders.handler.update', $order), [
            'handled_by_admin_id' => $owner->id,
            'handled_by' => '  Petugas   Serabutan  ',
        ])
        ->assertSessionHasNoErrors();

    expect($order->refresh())
        ->handled_by_admin_id->toBe($owner->id)
        ->handled_by->toBe('Petugas Serabutan');

    $this->actingAs($owner, 'admin')
        ->get(route('admin.orders.index', ['date' => $order->service_date->toDateString()]))
        ->assertInertia(
            fn (AssertableInertia $page) => $page
                ->where('orders.0.handledByAdminId', $owner->id)
                ->where('orders.0.handledByManual', 'Petugas Serabutan')
                ->where('orders.0.handledBy', $owner->name),
        );

    $this->actingAs($owner, 'admin')
        ->patch(route('admin.orders.handler.update', $order), [
            'handled_by_admin_id' => null,
            'handled_by' => '  Petugas   Manual  ',
        ])
        ->assertSessionHasNoErrors();

    $this->actingAs($owner, 'admin')
        ->get(route('admin.orders.index', ['date' => $order->service_date->toDateString()]))
        ->assertInertia(
            fn (AssertableInertia $page) => $page
                ->where('orders.0.handledByAdminId', null)
                ->where('orders.0.handledByManual', 'Petugas Manual')
                ->where('orders.0.handledBy', 'Petugas Manual'),
        );

    $this->actingAs($owner, 'admin')
        ->patch(route('admin.orders.handler.update', $order), [
            'handled_by_admin_id' => null,
            'handled_by' => '   ',
        ])
        ->assertSessionHasNoErrors();

    expect($order->refresh())
        ->handled_by_admin_id->toBeNull()
        ->handled_by->toBeNull();

    $this->actingAs($owner, 'admin')
        ->get(route('admin.orders.index', ['date' => $order->service_date->toDateString()]))
        ->assertInertia(
            fn (AssertableInertia $page) => $page
                ->where('orders.0.handledByAdminId', null)
                ->where('orders.0.handledByManual', null)
                ->where('orders.0.handledBy', 'Kasir Pembuat'),
        );
});

test('order handler admin id cannot impersonate another admin', function () {
    $owner = Admin::factory()->create(['is_owner' => true]);
    $otherAdmin = Admin::factory()->create();
    $order = Order::factory()->create();

    $this->actingAs($owner, 'admin')
        ->patch(route('admin.orders.handler.update', $order), [
            'handled_by_admin_id' => $otherAdmin->id,
            'handled_by' => null,
        ])
        ->assertSessionHasErrors('handled_by_admin_id');

    expect($order->refresh()->handled_by_admin_id)->toBeNull();
});

test('order handler is limited to 255 characters', function () {
    $owner = Admin::factory()->create(['is_owner' => true]);
    $order = Order::factory()->create();

    $this->actingAs($owner, 'admin')
        ->patch(route('admin.orders.handler.update', $order), [
            'handled_by' => Str::repeat('a', 256),
        ])
        ->assertSessionHasErrors('handled_by');

    expect($order->refresh()->handled_by)->toBeNull();
});

test('order payload eagerly includes the admin who input it', function () {
    $admin = Admin::factory()->create([
        'name' => 'Admin CS',
        'is_owner' => true,
    ]);
    $createdOrder = Order::factory()->create([
        'created_by_admin_id' => $admin->id,
        'handled_by' => '',
    ]);

    $order = OrderQueries::forDate(now()->toDateString())->firstOrFail();

    expect($order->relationLoaded('createdBy'))->toBeTrue()
        ->and($order->relationLoaded('handledByAdmin'))->toBeTrue()
        ->and($order->createdBy?->name)->toBe('Admin CS');

    $this->actingAs($admin, 'admin')
        ->get(route('admin.orders.index', ['date' => $createdOrder->service_date->toDateString()]))
        ->assertInertia(
            fn (AssertableInertia $page) => $page
                ->where('orders.0.inputBy', 'Admin CS')
                ->where('orders.0.handledByAdminId', null)
                ->where('orders.0.handledByManual', null)
                ->where('orders.0.handledBy', 'Admin CS'),
        );
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

test('open order status can be updated', function () {
    $owner = Admin::factory()->create(['is_owner' => true]);
    $order = Order::factory()->create(['status' => 'menunggu']);

    $this->actingAs($owner, 'admin')
        ->patch(route('admin.orders.status.update', $order), ['status' => 'proses'])
        ->assertSessionHasNoErrors();

    expect($order->refresh()->status)->toBe('proses');

    $order->update(['status' => 'proses', 'paid_amount' => $order->total]);

    $this->actingAs($owner, 'admin')
        ->patch(route('admin.orders.status.update', $order), ['status' => 'pelunasan'])
        ->assertRedirect()
        ->assertSessionHasNoErrors();

    expect($order->refresh()->status)->toBe('pelunasan');

    $this->actingAs($owner, 'admin')
        ->patch(route('admin.orders.handler.update', $order), ['handled_by' => 'Petugas Baru'])
        ->assertSessionHasNoErrors();
});

test('completed order status cannot be changed manually', function (string $status) {
    $owner = Admin::factory()->create(['is_owner' => true]);
    $order = paidOrder($owner, 135000);
    $attributes = $order->refresh()->getAttributes();

    $this->actingAs($owner, 'admin')
        ->patch(route('admin.orders.status.update', $order), ['status' => $status])
        ->assertSessionHasErrors('status');

    expect($order->refresh()->getAttributes())->toBe($attributes);
})->with(['booking', 'menunggu', 'proses', 'pelunasan', 'batal']);

test('deleting a settled payment reopens the order for status changes', function () {
    $owner = Admin::factory()->create(['is_owner' => true]);
    $order = paidOrder($owner, 135000);

    $this->actingAs($owner, 'admin')
        ->delete(route('admin.finance.transactions.destroy', $order->transactions()->sole()))
        ->assertSessionHasNoErrors();

    expect($order->refresh())->status->toBe('pelunasan')->paid_amount->toBe(0);

    $this->patch(route('admin.orders.status.update', $order), ['status' => 'proses'])
        ->assertSessionHasNoErrors();

    expect($order->refresh()->status)->toBe('proses');
});

test('status updates preserve existing transactions and order amounts', function (string $status, int $paidAmount) {
    $owner = Admin::factory()->create(['is_owner' => true]);
    $order = Order::factory()->create([
        'status' => 'pelunasan',
        'total' => 45000,
        'paid_amount' => $paidAmount,
    ]);
    $transaction = OrderTransaction::factory()->withDailyBalance()->create([
        'order_id' => $order->id,
        'amount' => $paidAmount,
        'channel_breakdown' => [['label' => 'Tunai', 'amount' => $paidAmount]],
    ]);
    $orderAttributes = $order->refresh()->getAttributes();
    $transactionAttributes = $transaction->refresh()->getAttributes();
    $dailyBalance = DB::table('daily_balance')->get()->toArray();

    $this->actingAs($owner, 'admin')
        ->patch(route('admin.orders.status.update', $order), [
            'status' => $status,
            'paid_amount' => 0,
            'total' => 0,
        ])
        ->assertRedirect()
        ->assertSessionHasNoErrors();

    expect($order->refresh()->status)->toBe($status)
        ->and(collect($order->getAttributes())->except(['status', 'updated_at'])->all())
        ->toBe(collect($orderAttributes)->except(['status', 'updated_at'])->all())
        ->and($transaction->refresh()->getAttributes())->toBe($transactionAttributes)
        ->and($order->transactions()->count())->toBe(1)
        ->and(DB::table('daily_balance')->get()->toArray())->toEqual($dailyBalance);
})->with(['booking', 'menunggu', 'proses', 'pelunasan', 'batal'])
    ->with(['partial payment' => 20000, 'fully paid' => 45000]);

test('orders remain mutable through H-30 and lock on H-31', function () {
    $owner = Admin::factory()->create(['is_owner' => true]);
    $allowed = Order::factory()->create(['service_date' => now()->subDays(30)]);
    $locked = Order::factory()->create(['service_date' => now()->subDays(31)]);

    $this->actingAs($owner, 'admin')
        ->patch(route('admin.orders.status.update', $allowed), ['status' => 'proses'])
        ->assertSessionHasNoErrors();
    $this->actingAs($owner, 'admin')
        ->patch(route('admin.orders.status.update', $locked), ['status' => 'proses'])
        ->assertUnprocessable();
    $this->actingAs($owner, 'admin')
        ->patch(route('admin.orders.handler.update', $locked), ['handled_by' => 'Petugas Baru'])
        ->assertUnprocessable();

    expect($allowed->refresh()->status)->toBe('proses')
        ->and($locked->refresh()->status)->toBe('menunggu')
        ->and($locked->handled_by)->toBeNull();
});

test('deleting an order soft deletes its transactions', function () {
    $owner = Admin::factory()->create(['is_owner' => true]);
    $order = Order::factory()->create(['paid_amount' => 20000]);
    $transaction = OrderTransaction::factory()->withDailyBalance()->create([
        'order_id' => $order->id,
        'amount' => 20000,
        'channel_breakdown' => [['label' => 'Tunai', 'amount' => 20000]],
    ]);

    $this->actingAs($owner, 'admin')
        ->delete(route('admin.orders.destroy', $order))
        ->assertSessionHasNoErrors();

    $this->assertSoftDeleted($order);
    $this->assertSoftDeleted($transaction);
    expect(Order::withTrashed()->findOrFail($order->id)->deleted_by_admin_id)->toBe($owner->id)
        ->and(OrderTransaction::withTrashed()->findOrFail($transaction->id)->deleted_by_admin_id)->toBe($owner->id)
        ->and(Order::query()->count())->toBe(0)
        ->and(OrderTransaction::query()->count())->toBe(0);
    $this->assertDatabaseCount('daily_balance', 0);
});

test('an order cannot be deleted when one of its payments is older than H-30', function () {
    $owner = Admin::factory()->create(['is_owner' => true]);
    $order = Order::factory()->create();
    $transaction = OrderTransaction::factory()->create([
        'order_id' => $order->id,
        'paid_at' => now()->subDays(31),
    ]);

    $this->actingAs($owner, 'admin')
        ->delete(route('admin.orders.destroy', $order))
        ->assertUnprocessable();

    $this->assertNotSoftDeleted($order);
    $this->assertNotSoftDeleted($transaction);
    expect($order->refresh()->deleted_by_admin_id)->toBeNull()
        ->and($transaction->refresh()->deleted_by_admin_id)->toBeNull();
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

    $order = Order::factory()->create();

    $this->actingAs($admin, 'admin')
        ->patch(route('admin.orders.handler.update', $order), ['handled_by' => 'Petugas'])
        ->assertForbidden();

    $this->actingAs($admin, 'admin')
        ->patch(route('admin.orders.update', $order), [])
        ->assertForbidden();

    $this->actingAs($admin, 'admin')
        ->delete(route('admin.orders.destroy', $order))
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

test('a walk-in order files a lead and a repeat visit reuses it by plate', function () {
    $owner = Admin::factory()->create(['is_owner' => true]);
    $service = Service::factory()->create(['price' => 45000]);
    $variation = $service->serviceVariations()->firstOrFail();
    $payload = [
        'customer_mode' => 'walk-in',
        'customer_name' => 'Tamu Walk In',
        'customer_phone' => '081234567890',
        'vehicle_name' => 'Toyota Calya',
        'vehicle_plate' => 'b 9876 abc',
        'items' => [['service_variation_id' => $variation->id, 'quantity' => 1]],
    ];

    $this->actingAs($owner, 'admin')
        ->post(route('admin.orders.store'), $payload)
        ->assertSessionHasNoErrors();

    $lead = Lead::query()->sole();

    expect($lead->vehicle_plate)->toBe('B9876ABC')
        ->and($lead->name)->toBe('Tamu Walk In')
        ->and(Order::query()->latest('id')->firstOrFail()->lead_id)->toBe($lead->id);

    /* Same car, name spelled differently: one lead, refreshed. */
    $this->actingAs($owner, 'admin')
        ->post(route('admin.orders.store'), [
            ...$payload,
            'customer_name' => 'Tamu Walk-In Baru',
            'customer_phone' => '081200002222',
            'vehicle_plate' => 'B9876ABC',
        ])
        ->assertSessionHasNoErrors();

    expect(Lead::query()->count())->toBe(1)
        ->and($lead->refresh()->name)->toBe('Tamu Walk-In Baru')
        ->and($lead->phone)->toBe('081200002222')
        ->and(Order::query()->where('lead_id', $lead->id)->count())->toBe(2);
});

test('a member order files no lead', function () {
    $owner = Admin::factory()->create(['is_owner' => true]);
    $member = Member::factory()->create();
    $vehicle = MemberVehicle::factory()->for($member)->create();
    $service = Service::factory()->create();
    $variation = $service->serviceVariations()->firstOrFail();

    $this->actingAs($owner, 'admin')
        ->post(route('admin.orders.store'), [
            'customer_mode' => 'existing',
            'member_id' => $member->id,
            'member_vehicle_id' => $vehicle->id,
            'items' => [['service_variation_id' => $variation->id, 'quantity' => 1]],
        ])
        ->assertSessionHasNoErrors();

    expect(Lead::query()->count())->toBe(0)
        ->and(Order::query()->latest('id')->firstOrFail()->lead_id)->toBeNull();
});

test('the walk-in tab searches leads through a partial reload', function () {
    $owner = Admin::factory()->create(['is_owner' => true]);
    $member = Member::factory()->create();
    $match = Lead::factory()->create(['name' => 'Rina Prospek', 'vehicle_plate' => 'B1234CDE']);
    Lead::factory()->create(['name' => 'Bukan Hasil', 'vehicle_plate' => 'D5555ZZ']);
    Lead::factory()->create([
        'name' => 'Sudah Member',
        'vehicle_plate' => 'B1234CDF',
        'converted_member_id' => $member->id,
        'converted_at' => now(),
    ]);

    /* A full page load never pays for the search. */
    $this->actingAs($owner, 'admin')
        ->get(route('admin.orders.index'))
        ->assertInertia(fn (AssertableInertia $page) => $page->missing('leadOptions'));

    $this->actingAs($owner, 'admin')
        ->get(route('admin.orders.index', ['leadQuery' => 'b 1234 cde']), [
            'X-Inertia' => 'true',
            'X-Inertia-Version' => app(HandleInertiaRequests::class)->version(request()),
            'X-Inertia-Partial-Component' => 'admin/Orders',
            'X-Inertia-Partial-Data' => 'leadOptions',
        ])
        ->assertOk()
        ->assertJsonCount(1, 'props.leadOptions')
        ->assertJsonPath('props.leadOptions.0.id', $match->id)
        ->assertJsonPath('props.leadOptions.0.vehiclePlate', 'B1234CDE');
});

test('transaction orders allow metadata edits while preserving financial snapshots', function (string $status, int $paidAmount) {
    $owner = Admin::factory()->create(['is_owner' => true]);
    $service = Service::factory()->create(['price' => 50000]);
    $variation = $service->serviceVariations()->firstOrFail();
    $order = Order::factory()->create([
        'status' => $status,
        'subtotal' => 100000,
        'discount' => 10000,
        'total' => 90000,
        'paid_amount' => $paidAmount,
    ]);
    $order->serviceVariations()->attach($variation, [
        'service_name' => 'Layanan Lama', 'unit_price' => 50000,
        'quantity' => 2, 'total_price' => 100000, 'stamps' => 1,
    ]);
    $transaction = OrderTransaction::factory()->withDailyBalance()->create([
        'order_id' => $order->id, 'amount' => $paidAmount,
        'channel_breakdown' => [['label' => 'Tunai', 'amount' => $paidAmount]],
    ]);
    $financials = $order->only(['subtotal', 'discount', 'total', 'paid_amount', 'stamps_earned', 'status']);
    $pivot = $order->serviceVariations()->firstOrFail()->pivot->getAttributes();
    $transactionAttributes = $transaction->refresh()->getAttributes();
    $dailyBalance = DB::table('daily_balance')->get()->toArray();
    $variation->update(['price' => 80000, 'is_active' => false]);
    $service->update(['name' => 'Nama Baru', 'is_active' => false]);

    $payload = [
        'customer_mode' => 'walk-in', 'customer_name' => 'Pelanggan Baru',
        'customer_phone' => '081234567899', 'vehicle_name' => 'Honda Jazz',
        'vehicle_plate' => 'b 9876 xyz', 'handled_by' => 'Petugas Baru',
        'items' => [['service_variation_id' => $variation->id, 'quantity' => 2]],
        'total' => 1, 'paid_amount' => 0, 'discount' => 90000,
    ];
    $this->actingAs($owner, 'admin')->patch(route('admin.orders.update', $order), $payload)
        ->assertRedirect()->assertSessionHasNoErrors();

    expect($order->refresh())
        ->customer_name->toBe('Pelanggan Baru')->customer_phone->toBe('081234567899')
        ->vehicle_name->toBe('Honda Jazz')->vehicle_plate->toBe('B9876XYZ')
        ->handled_by->toBe('Petugas Baru')
        ->and($order->only(array_keys($financials)))->toBe($financials)
        ->and($order->serviceVariations()->firstOrFail()->pivot->getAttributes())->toBe($pivot)
        ->and($transaction->refresh()->getAttributes())->toBe($transactionAttributes)
        ->and(DB::table('daily_balance')->get()->toArray())->toEqual($dailyBalance);

    $payload['items'][0]['quantity'] = 3;
    $payload['customer_name'] = 'Tidak Boleh Tersimpan';
    $this->patch(route('admin.orders.update', $order), $payload)->assertSessionHasErrors('items');
    expect($order->refresh()->customer_name)->toBe('Pelanggan Baru');

    $this->patch(route('admin.orders.handler.update', $order), ['handled_by' => 'Petugas Lain'])
        ->assertSessionHasNoErrors();
    expect($order->refresh()->handled_by)->toBe('Petugas Lain');
})->with(['proses', 'selesai'])->with([50000, 90000]);
