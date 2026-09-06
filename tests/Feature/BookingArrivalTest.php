<?php

use App\Models\Admin;
use App\Models\Order;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Process;
use Inertia\Testing\AssertableInertia;

beforeEach(function (): void {
    $this->travelTo(CarbonImmutable::parse('2026-09-06 10:15:00'));
});

test('moving an unfinished booking into the floor records its first arrival', function (string $status, int $daysAgo) {
    $owner = Admin::factory()->create(['is_owner' => true]);
    $order = Order::factory()->create([
        'source' => 'booking', 'status' => 'booking', 'arrived_at' => null,
        'service_date' => today()->subDays($daysAgo),
    ]);

    $this->actingAs($owner, 'admin')
        ->patch(route('admin.orders.status.update', $order), ['status' => $status])
        ->assertRedirect()->assertSessionHasNoErrors();

    expect($order->refresh()->status)->toBe($status)
        ->and($order->arrived_at->toDateTimeString())->toBe('2026-09-06 10:15:00');

    $this->get(route('admin.orders.index', ['date' => $order->service_date->toDateString()]))
        ->assertInertia(fn (AssertableInertia $page) => $page
            ->where('orders.0.time', '10.15')
            ->where('orders.0.arrivalDate', '2026-09-06')
            ->where('orders.0.date', $order->service_date->toDateString()));

    $this->travel(1)->hours();
    foreach (['booking', 'proses', 'pelunasan'] as $nextStatus) {
        $this->patch(route('admin.orders.status.update', $order), ['status' => $nextStatus])
            ->assertSessionHasNoErrors();
    }

    expect($order->refresh()->arrived_at->toDateTimeString())->toBe('2026-09-06 10:15:00');
})->with(['menunggu', 'proses', 'pelunasan'])->with([0, 1, 30]);

test('status changes do not record arrivals for future closed or unchanged orders', function (array $attributes, string $status) {
    $owner = Admin::factory()->create(['is_owner' => true]);
    $order = Order::factory()->create(array_merge([
        'source' => 'booking', 'status' => 'booking', 'arrived_at' => null,
    ], $attributes));

    $this->actingAs($owner, 'admin')
        ->patch(route('admin.orders.status.update', $order), ['status' => $status])
        ->assertRedirect()->assertSessionHasNoErrors();

    expect($order->refresh()->arrived_at)->toBeNull();
})->with([
    'future waiting' => [['service_date' => '2026-09-07'], 'menunggu'],
    'future processing' => [['service_date' => '2026-09-07'], 'proses'],
    'future settlement' => [['service_date' => '2026-09-07'], 'pelunasan'],
    'cancelled' => [[], 'batal'],
    'still booking' => [[], 'booking'],
    'unchanged processing' => [['status' => 'proses'], 'proses'],
    'completed' => [['status' => 'selesai'], 'pelunasan'],
    'previously cancelled' => [['status' => 'batal'], 'menunggu'],
    'walk-in' => [['source' => 'walk-in'], 'menunggu'],
]);

test('old bookings already on the floor receive a missing arrival on their next status change', function (string $status) {
    $owner = Admin::factory()->create(['is_owner' => true]);
    $order = Order::factory()->create([
        'source' => 'booking', 'status' => $status,
        'service_date' => today()->subDays(2), 'arrived_at' => null,
    ]);

    $this->actingAs($owner, 'admin')
        ->patch(route('admin.orders.status.update', $order), [
            'status' => $status === 'pelunasan' ? 'proses' : 'pelunasan',
        ])->assertSessionHasNoErrors();

    expect($order->refresh()->arrived_at->toDateTimeString())->toBe('2026-09-06 10:15:00')
        ->and($order->service_date->toDateString())->toBe('2026-09-04');
})->with(['menunggu', 'proses', 'pelunasan']);

test('paying a booking deposit does not record arrival', function () {
    $owner = Admin::factory()->create(['is_owner' => true]);
    $order = Order::factory()->create([
        'source' => 'booking', 'status' => 'booking', 'arrived_at' => null,
    ]);

    $this->actingAs($owner, 'admin')->post(route('admin.pos.payments.store', $order), [
        'intent' => 'partial', 'discount' => 0, 'amount' => 20000,
        'channels' => [['method' => 'Tunai', 'amount' => 20000]],
    ])->assertSessionHasNoErrors();

    expect($order->refresh()->arrived_at)->toBeNull()
        ->and($order->status)->toBe('booking')
        ->and($order->paid_amount)->toBe(20000);
});

test('arrival labels and demo transitions distinguish unknown times from unarrived bookings', function () {
    $script = <<<'JS'
const fs = require('node:fs');
const ts = require('typescript');
const assert = require('node:assert/strict');
const source = fs.readFileSync('resources/js/pages/admin/Orders.vue', 'utf8').split('<script setup lang="ts">')[1].split('</script>')[0];
const ast = ts.createSourceFile('Orders.ts', source, ts.ScriptTarget.Latest, true);
const names = ['orderArrivalLabel', 'setStatus'];
const code = ts.transpile(ast.statements.filter(node => ts.isFunctionDeclaration(node) && names.includes(node.name.text)).map(node => node.getText(ast)).join('\n'), { target: ts.ScriptTarget.ES2020 });
const props = { filters: { today: '2026-09-06', timezone: 'Asia/Jakarta' } };
const clock = class extends Date { constructor() { super('2026-09-06T03:15:00Z'); } };
const { orderArrivalLabel, setStatus } = new Function('props', 'formatDate', 'Date', `${code}; return { orderArrivalLabel, setStatus };`)(props, date => date.split('-').reverse().join('/'), clock);
const booking = { source: 'booking', status: 'booking', date: props.filters.today, time: '—' };
assert.equal(orderArrivalLabel(booking), 'Jadwal: 06/09/2026 · Belum masuk');
for (const status of ['menunggu', 'proses', 'pelunasan', 'selesai']) {
    assert.equal(orderArrivalLabel({ ...booking, status }), '06/09/2026 · Jam kedatangan belum tercatat');
}
assert.equal(orderArrivalLabel({ ...booking, status: 'batal' }), '06/09/2026 · Dibatalkan');
for (const status of ['booking', 'menunggu', 'proses', 'pelunasan', 'selesai', 'batal']) {
    assert.equal(orderArrivalLabel({ ...booking, status, time: '09.30' }), '06/09/2026 · 09.30');
}
for (const status of ['menunggu', 'proses', 'pelunasan']) {
    const order = { ...booking };
    setStatus(order, status);
    assert.equal(order.status, status);
    assert.equal(order.time, '10.15');
    order.time = '09.30';
    setStatus(order, 'booking');
    setStatus(order, status);
    assert.equal(order.time, '09.30');
    for (const date of ['2026-09-05', '2026-09-07']) {
        const otherDay = { ...booking, date };
        setStatus(otherDay, status);
        assert.equal(otherDay.time, date < props.filters.today ? '10.15' : '—');
        if (date < props.filters.today) {
            assert.equal(otherDay.arrivalDate, props.filters.today);
            assert.equal(orderArrivalLabel(otherDay), '06/09/2026 · 10.15');
        }
    }
}
for (const status of ['booking', 'batal']) {
    const order = { ...booking };
    setStatus(order, status);
    assert.equal(order.time, '—');
}
for (const overrides of [{ source: 'walk-in' }, { status: 'selesai' }, { status: 'batal' }, { status: 'pelunasan' }]) {
    const order = { ...booking, ...overrides };
    setStatus(order, 'pelunasan');
    assert.equal(order.time, '—');
}
for (const status of ['menunggu', 'proses', 'pelunasan']) {
    const order = { ...booking, date: '2026-09-04', status };
    setStatus(order, status === 'pelunasan' ? 'proses' : 'pelunasan');
    assert.equal(order.time, '10.15');
    assert.equal(orderArrivalLabel(order), '06/09/2026 · 10.15');
}
JS;

    $result = Process::path(base_path())->run(['node', '-e', $script]);

    expect($result->successful())->toBeTrue($result->errorOutput());
});
