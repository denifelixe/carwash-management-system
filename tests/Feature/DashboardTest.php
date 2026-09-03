<?php

use App\Models\Admin;
use App\Models\AdminModule;
use App\Models\AdminRole;
use App\Models\CashEntry;
use App\Models\Member;
use App\Models\Order;
use Carbon\CarbonImmutable;
use Inertia\Testing\AssertableInertia;

/* The cards read "today" off the Jakarta clock; pin it so the day cannot turn
 * over midway through a run. */
beforeEach(function (): void {
    $this->travelTo(CarbonImmutable::parse('2026-08-30 10:00', 'Asia/Jakarta'));
});

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
                ->where('timezone.id', 'Asia/Jakarta')
                ->where('timezone.code', 'WIB')
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
                /* Two rostered shifts, closed by the Tanpa Shift bucket. */
                ->has('shifts', 3)
                ->has('notifications', 0)
                ->has('modules', 12)
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

test('the dashboard restates the finance ledger for the same day', function () {
    $owner = Admin::factory()->create(['is_owner' => true]);
    $order = paidOrder($owner, 150000);
    CashEntry::factory()->moneyOut()->create(['amount' => 50000]);

    $ledger = $this->actingAs($owner, 'admin')
        ->get(route('admin.finance.index'))
        ->assertOk()
        ->viewData('page')['props']['cashSummary'];

    $this->actingAs($owner, 'admin')
        ->get(route('admin.dashboard'))
        ->assertOk()
        ->assertInertia(
            fn (AssertableInertia $page) => $page
                ->where('cashSummary', $ledger)
                ->where('stats.0.value', 'Rp 150.000')
                ->where('stats.0.caption', 'dari 1 transaksi keuangan')
                ->where('shifts.0.moneyIn', 150000)
                ->where('shifts.0.moneyOut', 50000),
        );

    expect($order->transactions()->count())->toBe(1);
});

/*
 * A row is filed under the shift stamped on it. Rows carrying none, and rows
 * carrying a shift that has since been retired, used to vanish from these cards;
 * the Tanpa Shift bucket keeps the shift cards adding up to the day's ledger.
 */
test('the shift cards close with a Tanpa Shift bucket for rows on no active shift', function () {
    $owner = Admin::factory()->create(['is_owner' => true]);
    CashEntry::factory()->create(['amount' => 90000, 'shift_name' => null]);
    CashEntry::factory()->moneyOut()->create(['amount' => 40000, 'shift_name' => 'Shift Malam']);

    $this->actingAs($owner, 'admin')
        ->get(route('admin.dashboard'))
        ->assertOk()
        ->assertInertia(
            fn (AssertableInertia $page) => $page
                ->has('shifts', 3)
                ->where('shifts.2.id', 'tanpa-shift')
                ->where('shifts.2.name', 'Tanpa Shift')
                ->where('shifts.2.status', '')
                ->where('shifts.2.moneyIn', 90000)
                ->where('shifts.2.moneyOut', 40000)
                ->where('shifts.0.moneyIn', 0)
                ->where('shifts.0.moneyOut', 0),
        );
});

test('the vehicle card counts served orders and leaves out cancelled ones', function () {
    $owner = Admin::factory()->create(['is_owner' => true]);
    $today = now('Asia/Jakarta')->toDateString();

    Order::factory()->count(2)->create(['service_date' => $today, 'status' => 'selesai']);
    Order::factory()->create(['service_date' => $today, 'status' => 'booking']);
    Order::factory()->create(['service_date' => $today, 'status' => 'batal']);

    $this->actingAs($owner, 'admin')
        ->get(route('admin.dashboard'))
        ->assertOk()
        ->assertInertia(
            fn (AssertableInertia $page) => $page
                ->where('orderSummary.total', 3)
                ->where('orderSummary.served', 2)
                ->where('orderSummary.awaitingBooking', 1)
                ->where('stats.1.value', '2')
                ->where('stats.1.caption', 'dari 3 order kendaraan'),
        );
});

test('a takings card compares the day against the one before it', function () {
    $owner = Admin::factory()->create(['is_owner' => true]);
    $today = CarbonImmutable::now('Asia/Jakarta')->startOfDay();

    CashEntry::factory()->create([
        'amount' => 100000,
        'entry_date' => $today->subDay()->toDateString(),
        'occurred_at' => $today->subDay()->addHours(10),
    ]);
    CashEntry::factory()->create([
        'amount' => 150000,
        'entry_date' => $today->toDateString(),
        'occurred_at' => $today->addHours(10),
    ]);

    $this->actingAs($owner, 'admin')
        ->get(route('admin.dashboard'))
        ->assertOk()
        ->assertInertia(
            fn (AssertableInertia $page) => $page
                ->where('stats.0.value', 'Rp 150.000')
                /* JSON has no float|int distinction: the page receives 50. */
                ->where('stats.0.delta', 50)
                ->where('stats.0.trend', 'up'),
        );
});

test('a day with no takings to compare against reports no movement', function () {
    $owner = Admin::factory()->create(['is_owner' => true]);
    CashEntry::factory()->create(['amount' => 150000]);

    $this->actingAs($owner, 'admin')
        ->get(route('admin.dashboard'))
        ->assertOk()
        ->assertInertia(
            fn (AssertableInertia $page) => $page
                ->where('stats.0.delta', null)
                ->where('stats.0.trend', 'flat'),
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

test('staff sidebar only contains readable modules from its role', function () {
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
        ->get(route('admin.orders.index'))
        ->assertOk()
        ->assertInertia(
            fn (AssertableInertia $page) => $page
                ->has('modules', 1)
                ->where('modules.0.key', 'orders')
                ->where('modules.0.enabled', true)
        );
});
