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
use App\Models\StockItem;
use App\Models\StockMovement;
use App\Support\Admin\FinanceQueries;
use App\Support\Demo\RoleAccess;
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

function openFinanceLog(Admin $admin, array $query = []): TestResponse
{
    return test()->actingAs($admin, 'admin')->get(route('admin.reports.index', $query), [
        'X-Inertia' => 'true',
        'X-Inertia-Version' => app(HandleInertiaRequests::class)->version(request()),
        'X-Inertia-Partial-Component' => 'admin/Reports',
        'X-Inertia-Partial-Data' => 'financeLog',
    ]);
}

test('finance report reconciles with the ledger and uses payment and entry dates', function (): void {
    $admin = Admin::factory()->create(['is_owner' => true]);
    $order = Order::factory()->create(['service_date' => '2026-09-01']);
    reportPayment('2026-08-29 23:59:59', 70000, ['order_id' => $order->id, 'channel_breakdown' => [['label' => 'Tunai', 'amount' => 100000]]]);
    CashEntry::factory()->create(['direction' => 'in', 'amount' => 30000, 'entry_date' => '2026-08-29']);
    CashEntry::factory()->create(['direction' => 'out', 'amount' => 20000, 'entry_date' => '2026-08-29']);
    reportPayment('2026-08-30 00:00:00', 90000);
    reportPayment('2026-08-29 12:00:00', 0);
    reportPayment('2026-08-29 12:00:00', 50000)->delete();
    CashEntry::factory()->create(['entry_date' => '2026-08-29', 'amount' => 40000])->delete();
    $query = ['from' => '2026-08-29', 'to' => '2026-08-29'];
    $props = openReport($admin, $query);
    $ledger = FinanceQueries::ledgerForDate('2026-08-29');

    expect($props)->not->toHaveKey('financeLog')
        ->and($props['financeSummary'])->toBe([
            'moneyIn' => 100000, 'moneyOut' => 20000, 'net' => 80000, 'transactions' => 3,
        ])
        ->and($props['financeSummary']['moneyIn'])->toBe(collect($ledger['moneyIn'])->sum('amount'))
        ->and($props['financeSummary']['moneyOut'])->toBe(collect($ledger['moneyOut'])->sum('amount'));

    $rows = openFinanceLog($admin, $query)->assertOk()
        ->assertJsonCount(3, 'props.financeLog.data')
        ->assertJsonPath('props.financeLog.meta.total', 3)->json('props.financeLog.data');
    expect(collect($rows)->sum('amount'))->toBe(120000);
});

test('finance log filters and paginates while export includes every matching row', function (): void {
    $admin = reportStaff(['read' => true]);
    CashEntry::factory()->count(27)->create(['direction' => 'out', 'entry_date' => '2026-08-29', 'occurred_at' => '2026-08-29 10:00:00', 'amount' => 15000]);
    reportPayment('2026-08-29 11:00:00', 70000);
    $query = ['from' => '2026-08-29', 'to' => '2026-08-29', 'direction' => 'out'];

    openFinanceLog($admin, $query)->assertOk()
        ->assertJsonCount(25, 'props.financeLog.data')->assertJsonPath('props.financeLog.meta.total', 27)
        ->assertJsonPath('props.financeLog.data.0.direction', 'out');
    openFinanceLog($admin, [...$query, 'financePage' => 2])->assertOk()
        ->assertJsonCount(2, 'props.financeLog.data')->assertJsonPath('props.financeLog.meta.currentPage', 2);

    $csv = $this->get(route('admin.reports.finance.export', [...$query, 'financePage' => 2]))
        ->assertOk()->assertDownload('laporan-keuangan-pengeluaran-2026-08-29-sd-2026-08-29.csv')->streamedContent();
    expect($csv)->toStartWith("\u{FEFF}Tanggal;Jam;Referensi;")
        ->and(array_filter(explode("\r\n", $csv)))->toHaveCount(28)
        ->and($csv)->not->toContain(';POS;');
});

test('finance export preserves numeric amounts and escapes spreadsheet formulas', function (): void {
    CashEntry::factory()->create([
        'direction' => 'in', 'entry_date' => '2026-08-29', 'occurred_at' => '2026-08-29 08:30:00',
        'description' => '=1+1', 'category' => 'Pendapatan Lain', 'amount' => 25000,
    ]);
    reportPayment('2026-08-29 09:00:00', 45000);
    $csv = $this->actingAs(reportStaff(['read' => true]), 'admin')
        ->get(route('admin.reports.finance.export', ['from' => '2026-08-29', 'to' => '2026-08-29', 'direction' => 'in']))
        ->assertOk()->streamedContent();
    $rows = array_map(fn (string $line): array => str_getcsv($line, ';', '"', ''), array_filter(explode("\r\n", $csv)));

    expect($rows)->toHaveCount(3)
        ->and($rows[1][4])->toBe('POS')
        ->and($rows[1][11])->toBe('45000')
        ->and($rows[2][1])->toBe('08:30')
        ->and($rows[2][6])->toBe("'=1+1")
        ->and($rows[2][11])->toBe('25000')
        ->and($rows[2][12])->toBe('0');
});

test('empty finance reports and downloads are valid', function (): void {
    $admin = reportStaff(['read' => true]);
    expect(openReport($admin)['financeSummary'])->toBe(['moneyIn' => 0, 'moneyOut' => 0, 'net' => 0, 'transactions' => 0]);
    openFinanceLog($admin)->assertOk()->assertJsonCount(0, 'props.financeLog.data')->assertJsonPath('props.financeLog.meta.total', 0);
    $csv = $this->get(route('admin.reports.finance.export'))->assertOk()->streamedContent();
    expect(array_filter(explode("\r\n", $csv)))->toHaveCount(1);
});

test('finance report downloads require report read access', function (): void {
    $this->get(route('admin.reports.finance.export'))->assertRedirect(route('admin.login'));
    $this->actingAs(reportStaff(['read' => false]), 'admin')->get(route('admin.reports.finance.export'))->assertForbidden();
    openFinanceLog(reportStaff(['read' => false]))->assertForbidden();
});

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
    reportPayment('2026-05-10 09:00', 500000);
    reportPayment('2026-05-20 09:00', 250000);
    reportPayment('2026-07-02 09:00', 125000);

    $props = openReport(
        Admin::factory()->create(['is_owner' => true]),
        ['from' => '2026-05-01', 'to' => '2026-07-31'],
    );

    $labels = array_column($props['trend'], 'label');
    $may = collect($props['trend'])->firstWhere('label', 'Mei 26');

    expect($props['filters']['granularity'])->toBe('bulanan')
        ->and($labels)->toBe(['Mei 26', 'Jun 26', 'Jul 26'])
        ->and($may['caption'])->toBe('Mei 2026')
        ->and($may['revenue'])->toBe(750000)
        ->and($may['transactions'])->toBe(2);
});

test('a range is never longer than 95 days; the start gives way', function (): void {
    Order::factory()->create(['service_date' => '2025-06-01']);

    $filters = openReport(
        Admin::factory()->create(['is_owner' => true]),
        ['from' => '2026-01-01', 'to' => '2026-08-30'],
    )['filters'];

    expect($filters['to'])->toBe('2026-08-30')
        ->and($filters['from'])->toBe('2026-05-28')
        ->and($filters['days'])->toBe(95)
        ->and($filters['maxDays'])->toBe(95);
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
    'a start before the first service day is lifted' => [['from' => '2020-01-01', 'to' => '2025-08-01'], '2025-06-01', '2025-08-01'],
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

test('the inventory card reads the live stock module', function (): void {
    $short = StockItem::factory()->create([
        'name' => 'Snow Foam pH Netral',
        'quantity' => 3,
        'min_quantity' => 6,
        'unit_cost' => 320_000,
    ]);
    StockItem::factory()->create([
        'name' => 'Spons Cuci Halus',
        'quantity' => 10,
        'min_quantity' => 4,
        'unit_cost' => 12_000,
    ]);
    /* A retired item is out of every figure the card prints. */
    StockItem::factory()->create(['quantity' => 99, 'unit_cost' => 1_000, 'is_active' => false]);

    StockMovement::factory()->for($short, 'stockItem')->create([
        'type' => 'keluar',
        'quantity' => -5,
        'recorded_at' => now()->subDay(),
    ]);
    StockMovement::factory()->for($short, 'stockItem')->create([
        'type' => 'keluar',
        'quantity' => -2,
        'recorded_at' => now(),
    ]);
    /* Older than the rolling week, so it counts for nothing. */
    StockMovement::factory()->for($short, 'stockItem')->create([
        'type' => 'keluar',
        'quantity' => -40,
        'recorded_at' => now()->subDays(20),
    ]);

    $summary = openReport(Admin::factory()->create(['is_owner' => true]))['inventorySummary'];

    expect($summary['totalItems'])->toBe(2)
        ->and($summary['lowStock'])->toBe(1)
        ->and($summary['stockValue'])->toBe(3 * 320_000 + 10 * 12_000)
        ->and($summary['movementsThisWeek'])->toBe(2)
        ->and($summary['topConsumed'])->toBe('Snow Foam pH Netral');
});

test('the inventory card says nothing was consumed when nothing moved', function (): void {
    StockItem::factory()->create(['quantity' => 10, 'min_quantity' => 2, 'unit_cost' => 5_000]);

    $summary = openReport(Admin::factory()->create(['is_owner' => true]))['inventorySummary'];

    expect($summary['totalItems'])->toBe(1)
        ->and($summary['lowStock'])->toBe(0)
        ->and($summary['movementsThisWeek'])->toBe(0)
        ->and($summary['topConsumed'])->toBe('—');
});

test('the daily sales report splits each payment day by method and foots to the trend', function (): void {
    $owner = Admin::factory()->create(['is_owner' => true]);
    reportPayment('2026-08-28 09:00:00', 45000);
    /* 100k tendered in cash for a 70k bill: the 30k change never reaches the Tunai column. */
    reportPayment('2026-08-28 15:00:00', 70000, [
        'channel_breakdown' => [['label' => 'Tunai', 'amount' => 100000]],
    ]);
    reportPayment('2026-08-30 08:00:00', 150000, [
        'channel_breakdown' => [
            ['label' => 'Debit · BCA', 'amount' => 100000, 'reference' => 'EDC-1'],
            ['label' => 'QRIS', 'amount' => 50000],
        ],
    ]);
    reportPayment('2026-08-27 08:00:00', 999000);

    $response = $this->actingAs($owner, 'admin')
        ->get(route('admin.reports.index', ['from' => '2026-08-28', 'to' => '2026-08-30']))
        ->assertOk();
    $report = $response->inertiaProps('dailySales');

    expect($report['methods'])->toBe(['Tunai', 'QRIS', 'Kredit', 'Debit', 'Transfer', 'E-Money'])
        ->and(array_column($report['rows'], 'date'))->toBe(['2026-08-28', '2026-08-29', '2026-08-30'])
        ->and($report['rows'][0])->toMatchArray(['transactions' => 2, 'total' => 115000])
        ->and($report['rows'][0]['methods']['Tunai'])->toBe(115000)
        ->and($report['rows'][1])->toMatchArray(['transactions' => 0, 'total' => 0])
        ->and($report['rows'][2]['methods'])->toMatchArray(['Debit' => 100000, 'QRIS' => 50000, 'Tunai' => 0])
        ->and($report['total'])->toMatchArray(['transactions' => 3, 'total' => 265000])
        ->and(array_sum($report['total']['methods']))->toBe(265000)
        ->and($report['total']['total'])->toBe(array_sum(array_column($response->inertiaProps('trend'), 'revenue')));
});

test('the daily sales report downloads as a spreadsheet with a total line', function (): void {
    $owner = Admin::factory()->create(['is_owner' => true]);
    reportPayment('2026-08-29 09:00:00', 45000);
    reportPayment('2026-08-29 10:00:00', 55000, ['channel_breakdown' => [['label' => 'Transfer · Mandiri', 'amount' => 55000]]]);

    $csv = $this->actingAs($owner, 'admin')
        ->get(route('admin.reports.daily-sales.export', ['from' => '2026-08-29', 'to' => '2026-08-30']))
        ->assertOk()
        ->assertDownload('laporan-penjualan-harian-2026-08-29-sd-2026-08-30.csv')
        ->streamedContent();
    $rows = array_map(fn (string $line): array => str_getcsv($line, ';', '"', ''), array_filter(explode("\r\n", $csv)));

    expect($csv)->toStartWith("\u{FEFF}Tanggal;")
        ->and($rows[0])->toBe(["\u{FEFF}Tanggal", 'Jml Trs', 'Total Transaksi', 'Jml Bayar Tunai', 'Jml Bayar QRIS', 'Jml Bayar Kredit', 'Jml Bayar Debit', 'Jml Bayar Transfer', 'Jml Bayar E-Money'])
        ->and($rows[1])->toBe(['29/08/2026', '2', '100000', '45000', '0', '0', '0', '55000', '0'])
        ->and($rows[2])->toBe(['30/08/2026', '0', '0', '0', '0', '0', '0', '0', '0'])
        ->and($rows[3])->toBe(['TOTAL', '2', '100000', '45000', '0', '0', '0', '55000', '0']);
});

test('a staff member without report access cannot download the daily sales report', function (): void {
    $this->actingAs(reportStaff(['read' => false]), 'admin')
        ->get(route('admin.reports.daily-sales.export'))
        ->assertForbidden();
});

test('the demo report serves the same daily sales shape and download', function (): void {
    $this->withSession([RoleAccess::SESSION_KEY => 'owner'])
        ->get(route('demo.admin.reports', ['from' => '2026-08-28', 'to' => '2026-08-30']))
        ->assertOk()
        ->assertInertia(fn (AssertableInertia $page) => $page
            ->has('dailySales.rows', 3)
            ->where('dailySales.methods', ['Tunai', 'QRIS', 'Kredit', 'Debit', 'Transfer', 'E-Money'])
            ->where('dailySales.rows', fn ($rows): bool => collect($rows)->every(
                fn (array $row): bool => array_sum($row['methods']) === $row['total'],
            )));

    $this->withSession([RoleAccess::SESSION_KEY => 'owner'])
        ->get(route('demo.admin.reports.daily-sales.export'))->assertOk();
});
