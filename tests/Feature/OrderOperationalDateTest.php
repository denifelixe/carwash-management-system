<?php

use App\Models\Admin;
use App\Models\Order;
use App\Models\Service;
use App\Support\Admin\OrderQueries;
use Illuminate\Support\Facades\Process;
use Inertia\Testing\AssertableInertia;

test('the order board uses arrival day with the schedule as a fallback', function (string $source, string $status, ?string $arrival, string $expectedDate) {
    $owner = Admin::factory()->create(['is_owner' => true]);
    $order = Order::factory()->create([
        'source' => $source,
        'status' => $status,
        'service_date' => '2026-09-04',
        'booking_date' => $source === 'booking' ? '2026-09-03' : null,
        'arrived_at' => $arrival,
    ]);

    $this->actingAs($owner, 'admin')
        ->get(route('admin.orders.index', ['date' => $expectedDate]))
        ->assertInertia(fn (AssertableInertia $page) => $page
            ->has('orders', 1)
            ->where('orders.0.id', $order->id)
            ->where('orders.0.date', '2026-09-04')
            ->where('orders.0.arrivalDate', $arrival === null ? null : substr($arrival, 0, 10))
            ->where('filters.earliest', $expectedDate)
            ->has('previousOrders', 0));

    $otherDate = $expectedDate === '2026-09-04' ? '2026-09-06' : '2026-09-04';
    $this->get(route('admin.orders.index', ['date' => $otherDate]))
        ->assertInertia(fn (AssertableInertia $page) => $page->has('orders', 0));
})->with([
    'unarrived booking' => ['booking', 'booking', null, '2026-09-04'],
    'legacy missing arrival' => ['booking', 'proses', null, '2026-09-04'],
    'late booking' => ['booking', 'menunggu', '2026-09-06 23:21:00', '2026-09-06'],
    'completed booking' => ['booking', 'selesai', '2026-09-06 23:21:00', '2026-09-06'],
    'cancelled after arrival' => ['booking', 'batal', '2026-09-06 23:21:00', '2026-09-06'],
    'cancelled before arrival' => ['booking', 'batal', null, '2026-09-04'],
    'walk-in' => ['walk-in', 'proses', '2026-09-06 23:21:00', '2026-09-06'],
    'just before midnight' => ['booking', 'proses', '2026-09-05 23:59:59', '2026-09-05'],
    'at midnight' => ['booking', 'proses', '2026-09-06 00:00:00', '2026-09-06'],
    'early arrival' => ['booking', 'proses', '2026-09-03 10:00:00', '2026-09-03'],
]);

test('previous unfinished orders use operational dates and never duplicate the daily list', function () {
    $owner = Admin::factory()->create(['is_owner' => true]);
    $unarrived = Order::factory()->create(['service_date' => '2026-09-03', 'source' => 'booking', 'status' => 'booking', 'arrived_at' => null]);
    $earlierArrival = Order::factory()->create(['service_date' => '2026-09-01', 'arrived_at' => '2026-09-05 23:00:00']);
    $sameDay = Order::factory()->create(['service_date' => '2026-09-04', 'arrived_at' => '2026-09-06 00:00:00']);
    Order::factory()->create(['service_date' => '2026-09-02', 'arrived_at' => '2026-09-07 00:00:00']);
    Order::factory()->create(['service_date' => '2026-09-01', 'status' => 'selesai', 'arrived_at' => '2026-09-05 10:00:00']);
    Order::factory()->create(['service_date' => '2026-09-01', 'status' => 'batal', 'arrived_at' => null]);

    $this->actingAs($owner, 'admin')->get(route('admin.orders.index', ['date' => '2026-09-06']))
        ->assertInertia(fn (AssertableInertia $page) => $page
            ->where('orders', fn ($orders): bool => collect($orders)->pluck('id')->all() === [$sameDay->id])
            ->where('previousOrders', fn ($orders): bool => collect($orders)->pluck('id')->all() === [$unarrived->id, $earlierArrival->id]));
});

test('order service counts move to arrival day while shared schedule reads keep their existing dates', function () {
    $owner = Admin::factory()->create(['is_owner' => true]);
    $service = Service::factory()->create(['category_group' => 'Cuci', 'category' => 'Mobil']);
    $variation = $service->serviceVariations()->firstOrFail();
    $order = Order::factory()->create(['service_date' => '2026-09-04', 'arrived_at' => '2026-09-06 23:21:00']);
    $order->serviceVariations()->attach($variation, ['service_name' => $service->name, 'unit_price' => $variation->price, 'total_price' => $variation->price, 'quantity' => 1, 'stamps' => 0]);

    $this->actingAs($owner, 'admin')->get(route('admin.orders.index', ['date' => '2026-09-06']))
        ->assertInertia(fn (AssertableInertia $page) => $page
            ->where('serviceOverview.0.count', 1)
            ->where('serviceOverview.0.categories.0.orders.0.id', $order->id));
    $this->get(route('admin.orders.index', ['date' => '2026-09-04']))
        ->assertInertia(fn (AssertableInertia $page) => $page->has('serviceOverview', 0));

    expect(OrderQueries::forDate('2026-09-04')->modelKeys())->toBe([$order->id])
        ->and(OrderQueries::forDate('2026-09-06')->modelKeys())->toBe([])
        ->and(OrderQueries::summaryForDate('2026-09-04')['total'])->toBe(1)
        ->and(OrderQueries::summaryForDate('2026-09-06')['total'])->toBe(0)
        ->and(OrderQueries::serviceOverviewForDate('2026-09-04')[0]['count'])->toBe(1)
        ->and(OrderQueries::serviceOverviewForDate('2026-09-06'))->toBe([]);
});

test('demo arrival moves daily membership and reactive service counts without changing the schedule', function () {
    $script = <<<'JS'
const fs = require('node:fs');
const ts = require('typescript');
const assert = require('node:assert/strict');
const source = fs.readFileSync('resources/js/pages/admin/Orders.vue', 'utf8').split('<script setup lang="ts">')[1].split('</script>')[0];
const ast = ts.createSourceFile('Orders.ts', source, ts.ScriptTarget.Latest, true);
const names = ['operationalOrderDate', 'buildOrderServiceOverview', 'setStatus', 'canEditStatus'];
const values = ['scopedOrders', 'unfinishedPreviousOrders', 'displayedServiceOverview'];
const code = ts.transpile(ast.statements.filter(node =>
    ts.isFunctionDeclaration(node) && names.includes(node.name.text)
    || ts.isVariableStatement(node) && node.declarationList.declarations.some(declaration => values.includes(declaration.name.text))
).map(node => node.getText(ast)).join('\n'), { target: ts.ScriptTarget.ES2020 });
const props = { mode: 'demo', capabilities: { update: true }, filters: { date: '2026-09-04', today: '2026-09-06', timezone: 'Asia/Jakarta' }, services: [
    { id: 1, categoryGroup: 'Cuci', category: 'Mobil' },
    { id: 2, categoryGroup: 'Cuci', category: 'Mobil' },
] };
const booking = { id: 1, source: 'booking', status: 'booking', date: '2026-09-04', time: '—', serviceIds: [1, 2], serviceItems: [
    { serviceId: 1, serviceName: 'Regular Wash', variations: { Ukuran: 'Large' }, quantity: 2 },
    { serviceId: 2, serviceName: 'Extra Wash', variations: null, quantity: 1 },
], plate: 'B8120DS', orderNo: 'BK1', customer: 'Member', vehicle: 'Civic' };
const orderList = { value: [booking] };
const computed = fn => ({ get value() { return fn(); } });
const clock = class extends Date { constructor() { super('2026-09-06T16:21:00Z'); } };
const { scopedOrders, unfinishedPreviousOrders, displayedServiceOverview, setStatus } = new Function('props', 'orderList', 'computed', 'Date', 'closedStatuses', `${code}; return { scopedOrders, unfinishedPreviousOrders, displayedServiceOverview, setStatus };`)(props, orderList, computed, clock, ['selesai', 'batal']);
assert.equal(scopedOrders.value.length, 1);
assert.equal(displayedServiceOverview.value[0].count, 1);
assert.equal(displayedServiceOverview.value[0].categories[0].count, 1);
assert.deepEqual(displayedServiceOverview.value[0].categories[0].orders[0].services, [
    'Cuci → Mobil → Regular Wash → Large (2)x',
    'Cuci → Mobil → Extra Wash (1)x',
]);
setStatus(booking, 'menunggu');
assert.equal(booking.date, '2026-09-04');
assert.equal(booking.arrivalDate, '2026-09-06');
assert.equal(scopedOrders.value.length, 0);
assert.deepEqual(displayedServiceOverview.value, []);
props.filters.date = '2026-09-06';
assert.deepEqual(scopedOrders.value.map(order => order.id), [1]);
assert.deepEqual(unfinishedPreviousOrders.value, []);
assert.equal(displayedServiceOverview.value[0].categories[0].orders[0].date, '2026-09-06');
props.filters.date = '2026-09-07';
assert.deepEqual(scopedOrders.value, []);
assert.deepEqual(unfinishedPreviousOrders.value.map(order => order.id), [1]);
JS;

    $result = Process::path(base_path())->run(['node', '-e', $script]);

    expect($result->successful())->toBeTrue($result->errorOutput());
});

test('dashboard vehicle links open the selected order on its operational date', function () {
    $script = <<<'JS'
const fs = require('node:fs');
const ts = require('typescript');
const assert = require('node:assert/strict');
const source = fs.readFileSync('resources/js/pages/admin/Dashboard.vue', 'utf8').split('<script setup lang="ts">')[1].split('</script>')[0];
const ast = ts.createSourceFile('Dashboard.ts', source, ts.ScriptTarget.Latest, true);
const declaration = ast.statements.find(node => ts.isFunctionDeclaration(node) && node.name.text === 'openOrder');
const code = ts.transpile(declaration.getText(ast), { target: ts.ScriptTarget.ES2020 });
for (const mode of ['live', 'demo']) {
    let visit;
    const props = { mode, filters: { date: '2026-09-04' } };
    const serviceOverviewOpen = { value: true };
    const router = { get: url => { visit = url; } };
    const indexOrder = { url: options => ({ mode: 'live', ...options }) };
    const demoAdmin = { orders: { url: options => ({ mode: 'demo', ...options }) } };
    const openOrder = new Function('props', 'serviceOverviewOpen', 'router', 'indexOrder', 'demoAdmin', `${code}; return openOrder;`)(props, serviceOverviewOpen, router, indexOrder, demoAdmin);
    openOrder(7, '2026-09-06');
    assert.deepEqual(visit, { mode, query: { date: '2026-09-06', order: 7 } });
    assert.equal(serviceOverviewOpen.value, false);
}
JS;

    $result = Process::path(base_path())->run(['node', '-e', $script]);

    expect($result->successful())->toBeTrue($result->errorOutput());
});

test('service overview groups start open and toggle independently', function () {
    $script = <<<'JS'
const fs = require('node:fs');
const ts = require('typescript');
const assert = require('node:assert/strict');
const source = fs.readFileSync('resources/js/components/admin/ServiceOverview.vue', 'utf8').split('<script setup lang="ts">')[1].split('</script>')[0];
const ast = ts.createSourceFile('ServiceOverview.ts', source, ts.ScriptTarget.Latest, true);
const code = ts.transpile(ast.statements.filter(node =>
    ts.isFunctionDeclaration(node) && node.name.text === 'toggleGroup'
    || ts.isVariableStatement(node) && node.declarationList.declarations.some(declaration => declaration.name.text === 'collapsedGroups')
).map(node => node.getText(ast)).join('\n'), { target: ts.ScriptTarget.ES2020 });
const ref = value => ({ value });
const { collapsedGroups, toggleGroup } = new Function('ref', `${code}; return { collapsedGroups, toggleGroup };`)(ref);
assert.deepEqual([...collapsedGroups.value], []);
toggleGroup('Cuci');
assert.deepEqual([...collapsedGroups.value], ['Cuci']);
toggleGroup('Paket');
assert.deepEqual([...collapsedGroups.value], ['Cuci', 'Paket']);
toggleGroup('Cuci');
assert.deepEqual([...collapsedGroups.value], ['Paket']);
toggleGroup('Paket');
assert.deepEqual([...collapsedGroups.value], []);
JS;

    $result = Process::path(base_path())->run(['node', '-e', $script]);

    expect($result->successful())->toBeTrue($result->errorOutput());
});

test('the order board hides previous unfinished orders when viewing a past date', function () {
    $script = <<<'JS'
const fs = require('node:fs');
const ts = require('typescript');
const assert = require('node:assert/strict');
const source = fs.readFileSync('resources/js/pages/admin/Orders.vue', 'utf8').split('<script setup lang="ts">')[1].split('</script>')[0];
const ast = ts.createSourceFile('Orders.ts', source, ts.ScriptTarget.Latest, true);
const declaration = ast.statements.find(node => ts.isVariableStatement(node)
    && node.declarationList.declarations.some(declaration => declaration.name.text === 'orderSections'));
const code = ts.transpile(declaration.getText(ast), { target: ts.ScriptTarget.ES2020 });
const props = { filters: { date: '2026-09-05', today: '2026-09-06' } };
const daily = { value: [{ id: 1 }] };
const previous = { value: [{ id: 2 }] };
const computed = fn => ({ get value() { return fn(); } });
const sections = new Function('props', 'computed', 'filteredOrders', 'unfinishedPreviousOrders', `${code}; return orderSections;`)(props, computed, daily, previous);
assert.deepEqual(sections.value.map(section => section.key), ['daily']);
assert.deepEqual(sections.value[0].orders, [{ id: 1 }]);
props.filters.date = '2026-09-06';
assert.deepEqual(sections.value.map(section => section.key), ['daily', 'previous']);
assert.deepEqual(sections.value[1].orders, [{ id: 2 }]);
props.filters.date = '2026-09-07';
assert.deepEqual(sections.value.map(section => section.key), ['daily', 'previous']);
props.filters.date = '2026-09-05';
assert.deepEqual(sections.value.map(section => section.key), ['daily']);
JS;

    $result = Process::path(base_path())->run(['node', '-e', $script]);

    expect($result->successful())->toBeTrue($result->errorOutput());
});
