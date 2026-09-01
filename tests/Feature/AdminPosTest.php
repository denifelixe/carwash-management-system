<?php

use App\Models\Admin;
use App\Models\AdminModule;
use App\Models\AdminRole;
use App\Models\AdminShift;
use App\Models\DailyBalance;
use App\Models\Member;
use App\Models\MemberVehicle;
use App\Models\Order;
use App\Models\OrderTransaction;
use App\Models\Service;
use Inertia\Testing\AssertableInertia;

test('guests cannot open the live cashier module', function () {
    $this->get(route('admin.pos.index'))
        ->assertRedirect(route('admin.login'));
});

test('the live cashier page uses the shared component and database records', function () {
    $owner = Admin::factory()->create(['is_owner' => true]);
    $service = Service::factory()->create(['name' => 'Premium Wash']);
    $order = Order::factory()->create(['number' => 'ORD-TEST-001', 'status' => 'pelunasan']);
    $variation = $service->serviceVariations()->firstOrFail();
    $order->serviceVariations()->attach($variation, [
        'service_name' => $service->name,
        'unit_price' => $service->price,
        'quantity' => 1,
        'total_price' => $service->price,
        'stamps' => $service->stamps,
    ]);

    $this->actingAs($owner, 'admin')
        ->get(route('admin.pos.index'))
        ->assertOk()
        ->assertInertia(
            fn (AssertableInertia $page) => $page
                ->component('admin/Pos')
                ->where('mode', 'live')
                ->where('orders.0.orderNo', 'ORD-TEST-001')
                ->where('orders.0.paidAmount', 0)
                ->where('orders.0.paymentStatus', 'belum bayar')
                ->where('dailyOrders.0.serviceIds.0', $service->id)
                ->where('capabilities.create', true)
                ->where('modules.2.key', 'pos')
                ->where('modules.2.enabled', true)
                ->where('modules.2.active', true),
        );
});

test('the cashier only settles orders the floor handed over, and keeps upcoming bookings in view', function () {
    $owner = Admin::factory()->create(['is_owner' => true]);
    Order::factory()->create(['status' => 'proses']);
    $settlement = Order::factory()->create(['status' => 'pelunasan']);
    $booking = Order::factory()->create([
        'status' => 'booking',
        'source' => 'booking',
        'service_date' => today()->addDays(3),
    ]);
    Order::factory()->create([
        'status' => 'booking',
        'source' => 'booking',
        'service_date' => today()->subDay(),
    ]);

    $this->actingAs($owner, 'admin')
        ->get(route('admin.pos.index'))
        ->assertOk()
        ->assertInertia(
            fn (AssertableInertia $page) => $page
                ->has('orders', 1)
                ->where('orders.0.id', $settlement->id)
                ->has('dailyOrders', 2)
                ->has('partialPaymentBookings', 1)
                ->where('partialPaymentBookings.0.id', $booking->id),
        );
});

test('a partial payment leaves the order open and records its channels', function () {
    $shift = AdminShift::query()->where('key', 'morning')->firstOrFail();
    $otherShift = AdminShift::query()->where('key', 'evening')->firstOrFail();
    $cashier = Admin::factory()->create(['is_owner' => true, 'shift_id' => $shift->id]);
    $order = Order::factory()->create(['status' => 'pelunasan', 'total' => 100000]);

    $this->actingAs($cashier, 'admin')
        ->post(route('admin.pos.payments.store', $order), [
            'intent' => 'partial',
            'discount' => 0,
            'amount' => 40000,
            'channels' => [
                ['method' => 'Tunai', 'amount' => 25000, 'provider' => '', 'reference' => ''],
                ['method' => 'Debit', 'amount' => 15000, 'provider' => 'BCA', 'reference' => '99881'],
            ],
            'transaction_shift_id' => $otherShift->id,
        ])
        ->assertSessionHasNoErrors();

    $transaction = OrderTransaction::query()->latest('id')->firstOrFail();
    $dailyBalance = DailyBalance::query()->sole();

    expect($order->refresh())
        ->paid_amount->toBe(40000)
        ->total->toBe(100000)
        ->status->toBe('pelunasan')
        ->invoice_number->toBeNull()
        ->payment_method->toBe('Tunai + Debit · BCA')
        ->and($transaction)
        ->type->toBe('Pembayaran Sebagian')
        ->amount->toBe(40000)
        ->shift_name->toBe('Shift Pagi')
        ->recorded_by_admin_id->toBe($cashier->id)
        ->reference->toBe($order->number.'-TRX-1')
        ->and($transaction->channel_breakdown)->toBe([
            ['label' => 'Tunai', 'amount' => 25000],
            ['label' => 'Debit · BCA', 'amount' => 15000, 'reference' => '99881'],
        ])
        ->and($dailyBalance->cash_income)->toBe(25000)
        ->and($dailyBalance->cash_balance)->toBe(25000)
        ->and($dailyBalance->non_cash_income)->toBe(15000)
        ->and($dailyBalance->non_cash_balance)->toBe(15000);
});

test('the cashier page exposes the current overlap status and selectable windows', function () {
    $this->travelTo('2026-08-31 14:30:00');
    AdminShift::query()->create([
        'key' => 'afternoon',
        'name' => 'Shift Siang',
        'starts_at' => '14:00',
        'ends_at' => '20:00',
        'is_active' => true,
    ]);
    $cashier = Admin::factory()->create([
        'is_owner' => true,
        'shift_mode' => 'schedule',
        'shift_id' => null,
    ]);

    $this->actingAs($cashier, 'admin')
        ->get(route('admin.pos.index'))
        ->assertInertia(fn (AssertableInertia $page) => $page
            ->where('persona.shift', 'Pilih saat transaksi')
            ->where('transactionShift.mode', 'schedule')
            ->where('transactionShift.label', 'Pilih saat transaksi')
            ->where('transactionShift.caption', 'Shift Pagi & Shift Siang')
            ->has('transactionShift.shifts', 3)
            ->where('transactionShift.shifts.0.starts_at', '08:00'));
});

test('settling the balance closes the order and issues its invoice', function () {
    $cashier = Admin::factory()->create(['is_owner' => true]);
    $order = Order::factory()->create([
        'number' => 'ORD-20260829-ABCDEF',
        'status' => 'pelunasan',
        'total' => 100000,
        'paid_amount' => 40000,
        'payment_method' => 'Tunai',
    ]);

    $this->actingAs($cashier, 'admin')
        ->post(route('admin.pos.payments.store', $order), [
            'intent' => 'settlement',
            'discount' => 10000,
            'amount' => 50000,
            'channels' => [
                ['method' => 'QRIS', 'amount' => 50000, 'provider' => '', 'reference' => ''],
            ],
        ])
        ->assertSessionHasNoErrors();

    expect($order->refresh())
        ->status->toBe('selesai')
        ->discount->toBe(10000)
        ->total->toBe(90000)
        ->paid_amount->toBe(90000)
        ->invoice_number->toBe('ZW-20260829-ABCDEF')
        ->payment_method->toBe('Tunai + QRIS')
        ->and(OrderTransaction::query()->latest('id')->firstOrFail()->type)
        ->toBe('Pembayaran Lunas');
});

test('a payment may not exceed the outstanding balance', function () {
    $cashier = Admin::factory()->create(['is_owner' => true]);
    $order = Order::factory()->create(['status' => 'pelunasan', 'total' => 50000]);

    $this->actingAs($cashier, 'admin')
        ->post(route('admin.pos.payments.store', $order), [
            'intent' => 'settlement',
            'discount' => 0,
            'amount' => 60000,
            'channels' => [
                ['method' => 'Tunai', 'amount' => 60000, 'provider' => '', 'reference' => ''],
            ],
        ])
        ->assertSessionHasErrors('amount');

    expect($order->refresh()->paid_amount)->toBe(0);
});

test('the money received may not fall short of the payment being booked', function () {
    $cashier = Admin::factory()->create(['is_owner' => true]);
    $order = Order::factory()->create(['status' => 'pelunasan', 'total' => 50000]);

    $this->actingAs($cashier, 'admin')
        ->post(route('admin.pos.payments.store', $order), [
            'intent' => 'settlement',
            'discount' => 0,
            'amount' => 50000,
            'channels' => [
                ['method' => 'Tunai', 'amount' => 20000, 'provider' => '', 'reference' => ''],
            ],
        ])
        ->assertSessionHasErrors('channels');

    expect($order->refresh()->paid_amount)->toBe(0);
});

test('a settled order cannot be paid again', function () {
    $cashier = Admin::factory()->create(['is_owner' => true]);
    $order = Order::factory()->create([
        'status' => 'selesai',
        'total' => 50000,
        'paid_amount' => 50000,
    ]);

    $this->actingAs($cashier, 'admin')
        ->post(route('admin.pos.payments.store', $order), [
            'intent' => 'settlement',
            'discount' => 0,
            'amount' => 10000,
            'channels' => [
                ['method' => 'Tunai', 'amount' => 10000, 'provider' => '', 'reference' => ''],
            ],
        ])
        ->assertSessionHasErrors('amount');

    expect(OrderTransaction::query()->count())->toBe(0);
});

test('a bill cleared entirely by a discount still books a transaction', function () {
    $cashier = Admin::factory()->create(['is_owner' => true]);
    $order = Order::factory()->create(['status' => 'pelunasan', 'total' => 20000]);

    $this->actingAs($cashier, 'admin')
        ->post(route('admin.pos.payments.store', $order), [
            'intent' => 'settlement',
            'discount' => 20000,
            'amount' => 0,
            'channels' => [],
        ])
        ->assertSessionHasNoErrors();

    expect($order->refresh())
        ->total->toBe(0)
        ->status->toBe('selesai')
        ->and(OrderTransaction::query()->latest('id')->firstOrFail()->channel_breakdown)
        ->toBe([['label' => 'Diskon', 'amount' => 0]]);
});

test('cashier access follows the role permission matrix', function () {
    $module = AdminModule::query()->where('key', 'pos')->firstOrFail();
    $role = AdminRole::query()->create([
        'key' => 'pos_reader',
        'name' => 'POS Reader',
        'is_active' => true,
    ]);
    $role->modules()->attach($module, ['can_read' => true]);
    $admin = Admin::factory()->create(['role_id' => $role->id]);
    $order = Order::factory()->create(['status' => 'pelunasan']);

    $this->actingAs($admin, 'admin')
        ->get(route('admin.pos.index'))
        ->assertOk()
        ->assertInertia(fn (AssertableInertia $page) => $page->where('capabilities.create', false));

    $this->actingAs($admin, 'admin')
        ->post(route('admin.pos.payments.store', $order), [])
        ->assertForbidden();
});

test('the cashier can sign the walk in behind an order up as a member', function () {
    $cashier = Admin::factory()->create(['is_owner' => true]);
    $service = Service::factory()->create(['stamps' => 2]);
    $order = Order::factory()->create([
        'status' => 'pelunasan',
        'customer_name' => 'Tamu Walk In',
        'stamps_earned' => 0,
    ]);
    $variation = $service->serviceVariations()->firstOrFail();
    $order->serviceVariations()->attach($variation, [
        'service_name' => $service->name,
        'unit_price' => $service->price,
        'quantity' => 1,
        'total_price' => $service->price,
        'stamps' => $service->stamps,
    ]);

    $this->actingAs($cashier, 'admin')
        ->post(route('admin.pos.member.store', $order), [
            'name' => 'Deni Victoria',
            'phone' => '081200002222',
            'vehicle_name' => 'Toyota Avanza',
            'vehicle_plate' => 'B 8120 DS',
        ])
        ->assertSessionHasNoErrors();

    $member = Member::query()->latest('id')->firstOrFail();
    $vehicle = MemberVehicle::query()->latest('id')->firstOrFail();

    expect($member)
        ->name->toBe('Deni Victoria')
        ->phone->toBe('081200002222')
        ->email->toBeNull()
        ->password->toBeNull()
        ->and($vehicle)
        ->member_id->toBe($member->id)
        ->name->toBe('Toyota Avanza')
        ->plate->toBe('B8120DS')
        ->is_primary->toBeTrue()
        ->and($order->refresh())
        ->member_id->toBe($member->id)
        ->member_vehicle_id->toBe($vehicle->id)
        ->customer_name->toBe('Deni Victoria')
        ->customer_phone->toBe('081200002222')
        ->vehicle_plate->toBe('B8120DS')
        /* The visit was always worth its stamps; there is finally someone to hold them. */
        ->stamps_earned->toBe(2);
});

test('a payment taken after the walk in joins belongs to the new member', function () {
    $cashier = Admin::factory()->create(['is_owner' => true]);
    $order = Order::factory()->create(['status' => 'pelunasan', 'total' => 50000]);

    $this->actingAs($cashier, 'admin')
        ->post(route('admin.pos.member.store', $order), [
            'name' => 'Deni Victoria',
            'phone' => '081200002222',
            'vehicle_name' => 'Toyota Avanza',
            'vehicle_plate' => 'B 8120 DS',
        ])
        ->assertSessionHasNoErrors();

    $this->actingAs($cashier, 'admin')
        ->post(route('admin.pos.payments.store', $order), [
            'intent' => 'settlement',
            'discount' => 0,
            'amount' => 50000,
            'channels' => [
                ['method' => 'Tunai', 'amount' => 50000, 'provider' => '', 'reference' => ''],
            ],
        ])
        ->assertSessionHasNoErrors();

    $member = Member::query()->latest('id')->firstOrFail();

    expect($order->refresh())
        ->status->toBe('selesai')
        ->member_id->toBe($member->id)
        ->and($member->orders()->count())->toBe(1);
});

test('a walk in cannot join on a plate or phone another member already holds', function () {
    $cashier = Admin::factory()->create(['is_owner' => true]);
    $existing = Member::factory()->create(['phone' => '081200003333']);
    MemberVehicle::factory()->for($existing)->create(['plate' => 'B 8120 DS']);
    $order = Order::factory()->create(['status' => 'pelunasan']);

    $this->actingAs($cashier, 'admin')
        ->post(route('admin.pos.member.store', $order), [
            'name' => 'Deni Victoria',
            'phone' => '081200003333',
            'vehicle_name' => 'Toyota Avanza',
            'vehicle_plate' => 'b8120ds',
        ])
        ->assertSessionHasErrors(['phone', 'vehicle_plate']);

    expect(Member::query()->count())->toBe(1)
        ->and($order->refresh()->member_id)->toBeNull();
});

test('an order that already belongs to a member cannot be handed to another', function () {
    $cashier = Admin::factory()->create(['is_owner' => true]);
    $member = Member::factory()->create();
    $order = Order::factory()->create(['status' => 'pelunasan', 'member_id' => $member->id]);

    $this->actingAs($cashier, 'admin')
        ->post(route('admin.pos.member.store', $order), [
            'name' => 'Deni Victoria',
            'phone' => '081200002222',
            'vehicle_name' => 'Toyota Avanza',
            'vehicle_plate' => 'B 8120 DS',
        ])
        ->assertSessionHasErrors('name');

    expect($order->refresh()->member_id)->toBe($member->id)
        ->and(Member::query()->count())->toBe(1);
});

test('registering a member follows the cashier write permission', function () {
    $module = AdminModule::query()->where('key', 'pos')->firstOrFail();
    $role = AdminRole::query()->create([
        'key' => 'pos_reader_member',
        'name' => 'POS Reader',
        'is_active' => true,
    ]);
    $role->modules()->attach($module, ['can_read' => true]);
    $admin = Admin::factory()->create(['role_id' => $role->id]);
    $order = Order::factory()->create(['status' => 'pelunasan']);

    $this->actingAs($admin, 'admin')
        ->post(route('admin.pos.member.store', $order), [
            'name' => 'Deni Victoria',
            'phone' => '081200002222',
            'vehicle_name' => 'Toyota Avanza',
            'vehicle_plate' => 'B 8120 DS',
        ])
        ->assertForbidden();

    expect(Member::query()->count())->toBe(0);
});

test('demo and live cashier pages have one frontend source of truth', function () {
    expect(resource_path('js/pages/admin/Pos.vue'))->toBeFile()
        ->and(resource_path('js/pages/demo/admin/Pos.vue'))->not->toBeFile()
        ->and(file_get_contents(app_path('Http/Controllers/Demo/PosController.php')))
        ->toContain("'admin/Pos'");
});

test('the recap tabs are built from the active shifts, in the order the day runs', function () {
    $owner = Admin::factory()->create(['is_owner' => true]);
    AdminShift::query()->create([
        'key' => 'night',
        'name' => 'Shift Malam',
        'starts_at' => '23:00:00',
        'ends_at' => '08:00:00',
        'is_active' => true,
    ]);
    AdminShift::query()->create([
        'key' => 'retired',
        'name' => 'Shift Lama',
        'starts_at' => '05:00:00',
        'ends_at' => '07:00:00',
        'is_active' => false,
    ]);

    $this->actingAs($owner, 'admin')
        ->get(route('admin.pos.index'))
        ->assertOk()
        ->assertInertia(
            fn (AssertableInertia $page) => $page
                ->component('admin/Pos')
                ->has('shifts', 3)
                ->where('shifts.0.key', 'morning')
                ->where('shifts.0.name', 'Shift Pagi')
                ->where('shifts.0.time', '08.00 - 16.00')
                ->where('shifts.1.key', 'evening')
                ->where('shifts.2.key', 'night')
                /* Retired shifts keep no tab: their payments read as unassigned. */
                ->where('shifts.2.name', 'Shift Malam'),
        );
});

test('a payment taken by a cashier on no shift is stored without one', function () {
    $cashier = Admin::factory()->create(['is_owner' => true, 'shift_id' => null]);
    $order = Order::factory()->create(['status' => 'pelunasan', 'total' => 50000]);

    $this->actingAs($cashier, 'admin')
        ->post(route('admin.pos.payments.store', $order), [
            'intent' => 'settlement',
            'discount' => 0,
            'amount' => 50000,
            'channels' => [
                ['method' => 'Tunai', 'amount' => 50000],
            ],
        ])
        ->assertRedirect();

    expect(OrderTransaction::query()->firstOrFail())
        ->shift_name->toBeNull()
        ->recorded_by_admin_id->toBe($cashier->id);
});

test('a scheduled cashier payment follows the only matching shift', function () {
    $this->travelTo('2026-08-31 09:00:00');
    $cashier = Admin::factory()->create([
        'is_owner' => true,
        'shift_mode' => 'schedule',
        'shift_id' => null,
    ]);
    $order = Order::factory()->create(['status' => 'pelunasan', 'total' => 50000]);

    $this->actingAs($cashier, 'admin')
        ->post(route('admin.pos.payments.store', $order), [
            'intent' => 'settlement',
            'discount' => 0,
            'amount' => 50000,
            'channels' => [['method' => 'Tunai', 'amount' => 50000]],
        ])
        ->assertSessionHasNoErrors();

    expect(OrderTransaction::query()->firstOrFail()->shift_name)->toBe('Shift Pagi');
});

test('a scheduled cashier payment outside every shift is stored without one', function () {
    $this->travelTo('2026-08-31 07:00:00');
    $cashier = Admin::factory()->create([
        'is_owner' => true,
        'shift_mode' => 'schedule',
        'shift_id' => null,
    ]);
    $order = Order::factory()->create(['status' => 'pelunasan', 'total' => 50000]);

    $this->actingAs($cashier, 'admin')
        ->post(route('admin.pos.payments.store', $order), [
            'intent' => 'settlement',
            'discount' => 0,
            'amount' => 50000,
            'channels' => [['method' => 'Tunai', 'amount' => 50000]],
        ])
        ->assertSessionHasNoErrors();

    expect(OrderTransaction::query()->firstOrFail()->shift_name)->toBeNull();
});

test('a scheduled cashier must choose one of overlapping shifts for every payment', function () {
    $this->travelTo('2026-08-31 14:30:00');
    $overlappingShift = AdminShift::query()->create([
        'key' => 'afternoon',
        'name' => 'Shift Siang',
        'starts_at' => '14:00',
        'ends_at' => '20:00',
        'is_active' => true,
    ]);
    $outsideShift = AdminShift::query()->where('key', 'evening')->firstOrFail();
    $cashier = Admin::factory()->create([
        'is_owner' => true,
        'shift_mode' => 'schedule',
        'shift_id' => null,
    ]);
    $firstOrder = Order::factory()->create(['status' => 'pelunasan', 'total' => 50000]);
    $secondOrder = Order::factory()->create(['status' => 'pelunasan', 'total' => 50000]);
    $payload = [
        'intent' => 'settlement',
        'discount' => 0,
        'amount' => 50000,
        'channels' => [['method' => 'Tunai', 'amount' => 50000]],
    ];

    $this->actingAs($cashier, 'admin')
        ->post(route('admin.pos.payments.store', $firstOrder), $payload)
        ->assertSessionHasErrors('transaction_shift_id');

    expect(OrderTransaction::query()->count())->toBe(0);

    $this->actingAs($cashier, 'admin')
        ->post(route('admin.pos.payments.store', $firstOrder), [
            ...$payload,
            'transaction_shift_id' => $outsideShift->id,
        ])
        ->assertSessionHasErrors('transaction_shift_id');

    $this->actingAs($cashier, 'admin')
        ->post(route('admin.pos.payments.store', $secondOrder), [
            ...$payload,
            'transaction_shift_id' => $overlappingShift->id,
        ])
        ->assertSessionHasNoErrors();

    expect(OrderTransaction::query()->firstOrFail()->shift_name)->toBe('Shift Siang');
});
