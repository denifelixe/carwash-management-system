<?php

use App\Http\Middleware\HandleInertiaRequests;
use App\Models\Admin;
use App\Models\AdminModule;
use App\Models\AdminRole;
use App\Models\AdminShift;
use App\Models\CashEntry;
use App\Models\Lead;
use App\Models\Member;
use App\Models\Order;
use App\Models\OrderTransaction;
use App\Models\Service;
use App\Support\Admin\FinanceQueries;
use Carbon\CarbonImmutable;
use Illuminate\Testing\TestResponse;
use Inertia\Testing\AssertableInertia;

/*
 * A report range is a span of business days, so the suite stands on one fixed
 * instant and names its dates outright rather than counting from "now".
 */
beforeEach(function (): void {
    $this->travelTo(CarbonImmutable::parse('2026-08-30 10:00', 'Asia/Jakarta'));
});

/**
 * @param  array<string, mixed>  $abilities
 */
function reportStaff(array $abilities): Admin
{
    $role = AdminRole::query()->create([
        'key' => 'reports_'.uniqid(),
        'name' => 'Report Staff',
        'description' => 'Role uji akses laporan.',
        'is_active' => true,
    ]);

    $role->modules()->attach(
        AdminModule::query()->where('key', 'reports')->firstOrFail(),
        [
            'can_create' => $abilities['create'] ?? false,
            'can_read' => $abilities['read'] ?? false,
            'can_update' => $abilities['update'] ?? false,
            'can_delete' => $abilities['delete'] ?? false,
            'additional_actions' => json_encode([], JSON_THROW_ON_ERROR),
        ],
    );

    return Admin::factory()->create(['role_id' => $role->id]);
}

/** A settled payment on a given day, which is what the report reads as revenue. */
function reportPayment(string $paidAt, int $amount, array $overrides = []): OrderTransaction
{
    return OrderTransaction::factory()->create([
        'amount' => $amount,
        'channel_breakdown' => [['label' => 'Tunai', 'amount' => $amount]],
        'paid_at' => CarbonImmutable::parse($paidAt),
        ...$overrides,
    ]);
}

/**
 * The order log is an optional prop, so only a partial visit pays for it —
 * exactly what the contribution card issues when it is opened.
 */
function openOrderLog(Admin $admin, array $query = []): TestResponse
{
    return test()->actingAs($admin, 'admin')->get(
        route('admin.reports.index', $query),
        [
            'X-Inertia' => 'true',
            'X-Inertia-Version' => app(HandleInertiaRequests::class)->version(request()),
            'X-Inertia-Partial-Component' => 'admin/Reports',
            'X-Inertia-Partial-Data' => 'orderLog',
        ],
    );
}

/** @return array<string, mixed> */
function openReport(Admin $admin, array $query = []): array
{
    $page = null;

    test()->actingAs($admin, 'admin')
        ->get(route('admin.reports.index', $query))
        ->assertOk()
        ->assertInertia(function (AssertableInertia $inertia) use (&$page): void {
            $page = $inertia;
        });

    return $page->toArray()['props'];
}

test('guests cannot open the report module', function (): void {
    $this->get(route('admin.reports.index'))->assertRedirect(route('admin.login'));
});

test('a staff member without read access is refused the report module', function (): void {
    $this->actingAs(reportStaff(['read' => false]), 'admin')
        ->get(route('admin.reports.index'))
        ->assertForbidden();
});

test('a staff member with read access opens the live report', function (): void {
    $this->actingAs(reportStaff(['read' => true]), 'admin')
        ->get(route('admin.reports.index'))
        ->assertOk()
        ->assertInertia(fn (AssertableInertia $page) => $page
            ->component('admin/Reports')
            ->where('mode', 'live')
            ->where('capabilities.read', true)
            ->has('trend')
            ->has('filters')
            ->has('topServices')
            ->has('customerBase')
            ->has('bookingSummary')
            ->has('inventorySummary')
            ->has('shifts'));
});

test('the sidebar links the report module once it is live', function (): void {
    $modules = openReport(Admin::factory()->create(['is_owner' => true]))['modules'];
    $reports = collect($modules)->firstWhere('key', 'reports');

    expect($reports['href'])->toBe(route('admin.reports.index', absolute: false))
        ->and($reports['enabled'])->toBeTrue()
        ->and($reports['active'])->toBeTrue();
});

test('the report defaults to the last seven days', function (): void {
    $filters = openReport(Admin::factory()->create(['is_owner' => true]))['filters'];

    expect($filters['from'])->toBe('2026-08-24')
        ->and($filters['to'])->toBe('2026-08-30')
        ->and($filters['days'])->toBe(7)
        ->and($filters['granularity'])->toBe('harian')
        ->and($filters['today'])->toBe('2026-08-30');
});

test('the trend charts a quiet day as a zero instead of dropping it', function (): void {
    reportPayment('2026-08-28 09:00', 150000);

    $trend = openReport(Admin::factory()->create(['is_owner' => true]))['trend'];
    $quiet = collect($trend)->firstWhere('caption', '29 Agu 2026');

    expect($trend)->toHaveCount(7)
        ->and($quiet['revenue'])->toBe(0)
        ->and($quiet['expense'])->toBe(0)
        ->and($quiet['transactions'])->toBe(0)
        ->and($quiet['label'])->toBe('29 Agu');
});

test('a day on the report reports the same takings the finance ledger does', function (): void {
    reportPayment('2026-08-28 09:00', 150000);
    reportPayment('2026-08-28 14:30', 220000);
    reportPayment('2026-08-29 11:00', 90000);
    CashEntry::factory()->moneyOut()->create([
        'amount' => 60000,
        'entry_date' => '2026-08-28',
        'occurred_at' => CarbonImmutable::parse('2026-08-28 16:00'),
    ]);

    $trend = openReport(Admin::factory()->create(['is_owner' => true]))['trend'];
    $day = collect($trend)->firstWhere('caption', '28 Agu 2026');

    ['moneyIn' => $moneyIn, 'moneyOut' => $moneyOut] = FinanceQueries::ledgerForDate('2026-08-28');
    $ledgerRevenue = collect($moneyIn)->where('source', 'pos')->sum('amount');

    expect($day['revenue'])->toBe(370000)
        ->and($day['revenue'])->toBe((int) $ledgerRevenue)
        ->and($day['transactions'])->toBe(2)
        ->and($day['expense'])->toBe((int) collect($moneyOut)->sum('amount'));
});

test('a range wider than two months rolls the chart up into whole months', function (): void {
    Order::factory()->create(['service_date' => '2026-01-05']);
    reportPayment('2026-02-10 09:00', 500000);
    reportPayment('2026-02-20 09:00', 250000);
    reportPayment('2026-04-02 09:00', 125000);

    $props = openReport(
        Admin::factory()->create(['is_owner' => true]),
        ['from' => '2026-02-01', 'to' => '2026-07-31'],
    );

    $labels = array_column($props['trend'], 'label');
    $february = collect($props['trend'])->firstWhere('label', 'Feb 26');

    expect($props['filters']['granularity'])->toBe('bulanan')
        ->and($labels)->toBe(['Feb 26', 'Mar 26', 'Apr 26', 'Mei 26', 'Jun 26', 'Jul 26'])
        ->and($february['caption'])->toBe('Feb 2026')
        ->and($february['revenue'])->toBe(750000)
        ->and($february['transactions'])->toBe(2);
});

test('an unusable range is clamped rather than rejected', function (array $query, string $from, string $to): void {
    Order::factory()->create(['service_date' => '2025-06-01']);

    $filters = openReport(Admin::factory()->create(['is_owner' => true]), $query)['filters'];

    expect($filters['from'])->toBe($from)
        ->and($filters['to'])->toBe($to);
})->with([
    'reversed ends are swapped' => [['from' => '2026-08-20', 'to' => '2026-08-10'], '2026-08-10', '2026-08-20'],
    'a future end is pulled back to today' => [['from' => '2026-08-28', 'to' => '2099-01-01'], '2026-08-28', '2026-08-30'],
    'an unparsable pair falls back to the week' => [['from' => 'kemarin', 'to' => 'besok'], '2026-08-24', '2026-08-30'],
    'a rolled-over date is refused' => [['from' => '2026-02-31', 'to' => '2026-08-30'], '2026-08-24', '2026-08-30'],
    'only a start anchors the default span' => [['from' => '2026-08-01'], '2026-08-01', '2026-08-07'],
    'only an end anchors the default span' => [['to' => '2026-08-20'], '2026-08-14', '2026-08-20'],
    'a start before the first service day is lifted' => [['from' => '2020-01-01', 'to' => '2026-08-30'], '2025-06-01', '2026-08-30'],
]);

test('the earliest selectable day still leaves room for the default week', function (): void {
    Order::factory()->create(['service_date' => '2026-08-30']);

    $filters = openReport(Admin::factory()->create(['is_owner' => true]))['filters'];

    expect($filters['earliest'])->toBe('2026-08-24')
        ->and($filters['from'])->toBe('2026-08-24');
});

test('the service ranking reads the name the order was sold under', function (): void {
    $service = Service::factory()->create(['name' => 'Snow Wash Premium']);
    $variation = $service->serviceVariations()->firstOrFail();
    $order = Order::factory()->create();
    $order->serviceVariations()->attach($variation, [
        'service_name' => 'Snow Wash Premium',
        'variations' => json_encode($variation->variations),
        'unit_price' => 85000,
        'quantity' => 2,
        'total_price' => 170000,
        'stamps' => 1,
    ]);
    reportPayment('2026-08-28 09:00', 170000, ['order_id' => $order->id]);

    // The service is renamed after the sale; the report must not follow it.
    $service->update(['name' => 'Snow Wash Deluxe']);

    $topServices = openReport(Admin::factory()->create(['is_owner' => true]))['topServices'];

    expect($topServices)->toHaveCount(1)
        ->and($topServices[0]['name'])->toBe('Snow Wash Premium')
        ->and($topServices[0]['revenue'])->toBe(170000)
        ->and($topServices[0]['orders'])->toBe(1);
});

test('shift rows are filed by the shift stamped on them', function (): void {
    reportPayment('2026-08-28 09:00', 150000, ['shift_name' => 'Shift Pagi']);
    reportPayment('2026-08-28 18:00', 240000, ['shift_name' => 'Shift Sore']);
    CashEntry::factory()->moneyOut()->create([
        'amount' => 50000,
        'shift_name' => 'Shift Pagi',
        'entry_date' => '2026-08-28',
        'occurred_at' => CarbonImmutable::parse('2026-08-28 10:00'),
    ]);

    $shifts = collect(openReport(Admin::factory()->create(['is_owner' => true]))['shifts']);
    $morning = $shifts->firstWhere('name', 'Shift Pagi');
    $evening = $shifts->firstWhere('name', 'Shift Sore');

    expect($morning['revenue'])->toBe(150000)
        ->and($morning['transactions'])->toBe(1)
        ->and($morning['vehiclesServed'])->toBe(1)
        ->and($morning['moneyIn'])->toBe(150000)
        ->and($morning['moneyOut'])->toBe(50000)
        ->and($morning['time'])->toBe('08.00 - 16.00')
        ->and($morning)->not->toHaveKey('status')
        ->and($evening['revenue'])->toBe(240000)
        ->and($evening['moneyOut'])->toBe(0);
});

test('a row stamped with a retired shift falls into the Tanpa Shift bucket', function (): void {
    reportPayment('2026-08-28 09:00', 150000, ['shift_name' => 'Shift Malam']);
    reportPayment('2026-08-28 12:00', 75000, ['shift_name' => null]);
    AdminShift::query()->where('key', 'evening')->update(['is_active' => false]);
    reportPayment('2026-08-28 18:00', 60000, ['shift_name' => 'Shift Sore']);

    $shifts = collect(openReport(Admin::factory()->create(['is_owner' => true]))['shifts']);
    $unassigned = $shifts->firstWhere('id', FinanceQueries::UNASSIGNED_SHIFT_KEY);

    expect($shifts->pluck('name')->all())->toBe(['Shift Pagi', 'Tanpa Shift'])
        ->and($unassigned['revenue'])->toBe(285000)
        ->and($unassigned['transactions'])->toBe(3)
        ->and($unassigned['time'])->toBeNull();
});

test('customer activity counts real visits and holds redemption at zero', function (): void {
    $returning = Member::factory()->create(['created_at' => '2026-01-10 09:00']);
    $fresh = Member::factory()->create(['created_at' => '2026-08-26 09:00']);
    Order::factory()->create([
        'member_id' => $returning->id,
        'service_date' => '2026-05-01',
        'status' => 'selesai',
    ]);

    /* Three visits across two members, so the average has to actually divide. */
    foreach ([$returning, $returning, $fresh] as $member) {
        $order = Order::factory()->create([
            'member_id' => $member->id,
            'service_date' => '2026-08-28',
            'status' => 'selesai',
            'stamps_earned' => 2,
        ]);
        reportPayment('2026-08-28 09:00', 120000, ['order_id' => $order->id]);
    }

    $base = openReport(Admin::factory()->create(['is_owner' => true]))['customerBase'];

    expect($base['newMembers'])->toBe(1)
        ->and($base['returningMembers'])->toBe(1)
        ->and($base['membersServed'])->toBe(2)
        ->and($base['averageVisitsPerMember'])->toBe(1.5);
});

test('the lead side of the card counts new and converted leads in the range', function (): void {
    $converted = Member::factory()->create();
    Lead::factory()->create([
        'created_at' => '2026-08-26 09:00',
        'converted_member_id' => $converted->id,
        'converted_at' => '2026-08-28 09:00',
    ]);
    Lead::factory()->create(['created_at' => '2026-08-27 09:00']);
    /* Captured before the range opened, and still waiting to convert. */
    Lead::factory()->create(['created_at' => '2026-01-10 09:00']);

    $base = openReport(Admin::factory()->create(['is_owner' => true]))['customerBase'];

    expect($base['newLeads'])->toBe(2)
        ->and($base['convertedLeads'])->toBe(1)
        ->and($base['openLeads'])->toBe(2);
});

test('a member who stopped visiting counts as a churn risk', function (): void {
    $lapsed = Member::factory()->create();
    $recent = Member::factory()->create();
    Order::factory()->create([
        'member_id' => $lapsed->id,
        'service_date' => '2026-01-15',
        'status' => 'selesai',
    ]);
    Order::factory()->create([
        'member_id' => $recent->id,
        'service_date' => '2026-08-20',
        'status' => 'selesai',
    ]);

    $base = openReport(Admin::factory()->create(['is_owner' => true]))['customerBase'];

    expect($base['churnRisk'])->toBe(1);
});

test('the booking summary counts bookings on the day they were served', function (): void {
    Order::factory()->count(2)->create([
        'source' => 'booking',
        'service_date' => '2026-08-28',
        'status' => 'selesai',
        'arrived_at' => CarbonImmutable::parse('2026-08-28 09:30'),
    ]);
    Order::factory()->create([
        'source' => 'booking',
        'service_date' => '2026-08-28',
        'status' => 'batal',
        'arrived_at' => null,
    ]);
    Order::factory()->create([
        'source' => 'booking',
        'service_date' => '2026-08-29',
        'status' => 'booking',
        'arrived_at' => null,
    ]);
    /* A walk-in on the same day must stay out of the booking figures. */
    Order::factory()->create(['service_date' => '2026-08-28', 'status' => 'selesai']);

    $summary = openReport(Admin::factory()->create(['is_owner' => true]))['bookingSummary'];

    expect($summary['total'])->toBe(4)
        ->and($summary['scheduled'])->toBe(1)
        ->and($summary['completed'])->toBe(2)
        ->and($summary['cancelled'])->toBe(1)
        ->and($summary['showRate'])->toBe(66.7);
});

test('the order log is withheld until the contribution card asks for it', function (): void {
    reportPayment('2026-08-28 09:00', 150000);

    $this->actingAs(Admin::factory()->create(['is_owner' => true]), 'admin')
        ->get(route('admin.reports.index'))
        ->assertOk()
        ->assertInertia(fn (AssertableInertia $page) => $page->missing('orderLog'));
});

test('the order log lists the orders behind the range', function (): void {
    $order = Order::factory()->create([
        'customer_name' => 'Pak Satria',
        'customer_phone' => '087880808037',
        'vehicle_name' => 'CR-V',
        'vehicle_plate' => 'F 1203 MR',
        'service_date' => '2026-08-28',
        'arrived_at' => CarbonImmutable::parse('2026-08-28 08:12'),
        'handled_by' => 'Hari',
        'status' => 'selesai',
        'total' => 150000,
    ]);
    $service = Service::factory()->create(['name' => 'Sealant Body']);
    $order->serviceVariations()->attach($service->serviceVariations()->firstOrFail(), [
        'service_name' => 'Sealant Body',
        'variations' => null,
        'unit_price' => 150000,
        'quantity' => 1,
        'total_price' => 150000,
        'stamps' => 1,
    ]);
    reportPayment('2026-08-28 09:00', 150000, ['order_id' => $order->id]);
    /* A payment outside the range must not drag its order into the log. */
    reportPayment('2026-07-01 09:00', 90000);

    openOrderLog(Admin::factory()->create(['is_owner' => true]))
        ->assertOk()
        ->assertJsonCount(1, 'props.orderLog.data')
        ->assertJsonPath('props.orderLog.data.0.date', '28/08/2026')
        ->assertJsonPath('props.orderLog.data.0.time', '08:12')
        ->assertJsonPath('props.orderLog.data.0.vehicle', 'CR-V')
        ->assertJsonPath('props.orderLog.data.0.plate', 'F1203MR')
        ->assertJsonPath('props.orderLog.data.0.customer', 'Pak Satria')
        ->assertJsonPath('props.orderLog.data.0.phone', '087880808037')
        ->assertJsonPath('props.orderLog.data.0.services', 'Sealant Body')
        ->assertJsonPath('props.orderLog.data.0.total', 150000)
        ->assertJsonPath('props.orderLog.meta.total', 1);
});

test('opening one service narrows the order log to that service', function (): void {
    $sealant = Service::factory()->create(['name' => 'Sealant Body']);
    $regular = Service::factory()->create(['name' => 'Regular']);

    foreach (['Sealant Body' => $sealant, 'Regular' => $regular] as $name => $service) {
        $order = Order::factory()->create(['service_date' => '2026-08-28']);
        $order->serviceVariations()->attach($service->serviceVariations()->firstOrFail(), [
            'service_name' => $name,
            'variations' => null,
            'unit_price' => 100000,
            'quantity' => 1,
            'total_price' => 100000,
            'stamps' => 1,
        ]);
        reportPayment('2026-08-28 09:00', 100000, ['order_id' => $order->id]);
    }

    openOrderLog(
        Admin::factory()->create(['is_owner' => true]),
        ['service' => 'Sealant Body'],
    )
        ->assertOk()
        ->assertJsonCount(1, 'props.orderLog.data')
        ->assertJsonPath('props.orderLog.data.0.services', 'Sealant Body');
});

test('the order log pages rather than shipping a whole range at once', function (): void {
    foreach (range(1, 27) as $index) {
        reportPayment('2026-08-28 09:00', 10000 + $index);
    }

    openOrderLog(Admin::factory()->create(['is_owner' => true]), ['orderPage' => 2])
        ->assertOk()
        ->assertJsonCount(2, 'props.orderLog.data')
        ->assertJsonPath('props.orderLog.meta.currentPage', 2)
        ->assertJsonPath('props.orderLog.meta.lastPage', 2)
        ->assertJsonPath('props.orderLog.meta.total', 27);
});

test('a staff member without read access cannot download the order log', function (): void {
    $this->actingAs(reportStaff(['read' => false]), 'admin')
        ->get(route('admin.reports.orders.export'))
        ->assertForbidden();
});

test('the order log downloads as a spreadsheet of the whole range', function (): void {
    $order = Order::factory()->create([
        'customer_name' => 'Pak Satria',
        'customer_phone' => '087880808037',
        'vehicle_name' => 'CR-V',
        'vehicle_plate' => 'F1203MR',
        'service_date' => '2026-08-28',
        'arrived_at' => CarbonImmutable::parse('2026-08-28 08:12'),
        'status' => 'selesai',
        'total' => 150000,
    ]);
    $service = Service::factory()->create(['name' => 'Sealant Body']);
    $order->serviceVariations()->attach($service->serviceVariations()->firstOrFail(), [
        'service_name' => 'Sealant Body',
        'variations' => null,
        'unit_price' => 150000,
        'quantity' => 1,
        'total_price' => 150000,
        'stamps' => 1,
    ]);
    reportPayment('2026-08-28 09:00', 150000, ['order_id' => $order->id]);

    $response = $this->actingAs(Admin::factory()->create(['is_owner' => true]), 'admin')
        ->get(route('admin.reports.orders.export'))
        ->assertOk()
        ->assertDownload('laporan-order-2026-08-24-sd-2026-08-30.csv');

    $csv = $response->streamedContent();
    [$heading, $row] = explode("\r\n", $csv);

    /* fputcsv quotes any field holding a space; Excel unwraps those again. */
    expect($csv)->toStartWith("\u{FEFF}")
        ->and($heading)->toBe(
            "\u{FEFF}Tanggal;Jam;\"No Order\";Kendaraan;Plat;Customer;\"No HP\";Layanan;Status;Total",
        )
        // The plate is spaced and the phone keeps its leading zero as text.
        ->and($row)->toContain(';"F 1203 MR";')
        ->and($row)->toContain(';0878-8080-8037;')
        ->and($row)->toContain('28/08/2026;08:12;')
        ->and($row)->toContain(';"Sealant Body";selesai;150000');
});

test('a phone number keeps its leading zero however long it is', function (string $stored, string $written): void {
    $order = Order::factory()->create([
        'customer_phone' => $stored,
        'service_date' => '2026-08-28',
    ]);
    reportPayment('2026-08-28 09:00', 50000, ['order_id' => $order->id]);

    $csv = $this->actingAs(Admin::factory()->create(['is_owner' => true]), 'admin')
        ->get(route('admin.reports.orders.export'))
        ->assertOk()
        ->streamedContent();

    expect($csv)->toContain(';'.$written.';');
})->with([
    'twelve digits group evenly' => ['081510239393', '0815-1023-9393'],
    'eleven digits keep a three digit tail' => ['08123456789', '0812-3456-789'],
    'a two digit tail joins the group before it' => ['0812345678', '0812-345678'],
    // Passed through untouched, and quoted by fputcsv because it holds spaces.
    'a number that is not plain digits is left alone' => ['+62 815 1023', '"+62 815 1023"'],
]);

test('the download carries every page of the log, not just the first', function (): void {
    foreach (range(1, 27) as $index) {
        reportPayment('2026-08-28 09:00', 10000 + $index);
    }

    $csv = $this->actingAs(Admin::factory()->create(['is_owner' => true]), 'admin')
        ->get(route('admin.reports.orders.export'))
        ->assertOk()
        ->streamedContent();

    // 27 orders, one heading row, and the trailing line break.
    expect(array_filter(explode("\r\n", $csv)))->toHaveCount(28);
});

test('downloading from one service narrows the file and names it', function (): void {
    $sealant = Service::factory()->create(['name' => 'Sealant Body']);
    $regular = Service::factory()->create(['name' => 'Regular']);

    foreach (['Sealant Body' => $sealant, 'Regular' => $regular] as $name => $service) {
        $order = Order::factory()->create(['service_date' => '2026-08-28']);
        $order->serviceVariations()->attach($service->serviceVariations()->firstOrFail(), [
            'service_name' => $name,
            'variations' => null,
            'unit_price' => 100000,
            'quantity' => 1,
            'total_price' => 100000,
            'stamps' => 1,
        ]);
        reportPayment('2026-08-28 09:00', 100000, ['order_id' => $order->id]);
    }

    $csv = $this->actingAs(Admin::factory()->create(['is_owner' => true]), 'admin')
        ->get(route('admin.reports.orders.export', ['service' => 'Sealant Body']))
        ->assertOk()
        ->assertDownload('laporan-order-sealant-body-2026-08-24-sd-2026-08-30.csv')
        ->streamedContent();

    expect($csv)->toContain('Sealant Body')
        ->and($csv)->not->toContain('Regular');
});

test('the inventory card is served an empty summary until the module exists', function (): void {
    $summary = openReport(Admin::factory()->create(['is_owner' => true]))['inventorySummary'];

    expect($summary['totalItems'])->toBe(0)
        ->and($summary['lowStock'])->toBe(0)
        ->and($summary['stockValue'])->toBe(0)
        ->and($summary['topConsumed'])->toBe('—');
});
