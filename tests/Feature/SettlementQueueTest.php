<?php

use App\Models\Admin;
use App\Models\Order;
use App\Models\OrderTransaction;
use App\Support\Admin\OrderPresenter;
use App\Support\Admin\OrderQueries;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Process;
use Inertia\Testing\AssertableInertia;

test('settlement entry survives partial payments and reloads and resets on reentry', function () {
    $this->travelTo(CarbonImmutable::parse('2026-09-15 10:00:00'));
    $owner = Admin::factory()->create(['is_owner' => true]);
    $order = Order::factory()->create(['status' => 'proses', 'total' => 100000, 'paid_amount' => 0]);

    expect($order->settlement_entered_at)->toBeNull();

    $this->actingAs($owner, 'admin')
        ->patch(route('admin.orders.status.update', $order), ['status' => 'pelunasan'])
        ->assertSessionHasNoErrors();

    expect($order->refresh()->settlement_entered_at->toDateTimeString())->toBe('2026-09-15 10:00:00');
    $enteredAt = $order->settlement_entered_at->valueOf();

    $this->travel(5)->minutes();
    $this->post(route('admin.pos.payments.store', $order), [
        'intent' => 'partial', 'discount' => 0, 'amount' => 20000,
        'channels' => [['method' => 'Tunai', 'amount' => 20000]],
    ])->assertSessionHasNoErrors();
    $this->patch(route('admin.orders.status.update', $order), ['status' => 'pelunasan'])
        ->assertSessionHasNoErrors();

    expect($order->refresh()->settlement_entered_at->valueOf())->toBe($enteredAt);
    $this->get(route('admin.pos.index'))->assertInertia(fn (AssertableInertia $page) => $page
        ->where('orders.0.settlementEnteredAt', (int) $enteredAt));

    $this->patch(route('admin.orders.status.update', $order), ['status' => 'proses'])
        ->assertSessionHasNoErrors();
    $this->patch(route('admin.orders.status.update', $order), ['status' => 'pelunasan'])
        ->assertSessionHasNoErrors();
    expect($order->refresh()->settlement_entered_at->toDateTimeString())->toBe('2026-09-15 10:05:00');
});

test('legacy settlements use their arrival time without changing it on later edits', function () {
    $order = Order::withoutEvents(fn () => Order::factory()->create([
        'status' => 'pelunasan', 'arrived_at' => now()->subHour(),
    ]));
    $order->update(['notes' => 'Updated notes']);

    $presented = OrderPresenter::order(OrderQueries::forDate($order->service_date->toDateString())->first());

    expect($order->refresh()->settlement_entered_at)->toBeNull()
        ->and($presented['settlementEnteredAt'])->toBe($order->arrived_at->valueOf());
});

test('deleting the final payment records a new settlement entry', function () {
    $this->travelTo(CarbonImmutable::parse('2026-09-15 11:00:00'));
    $owner = Admin::factory()->create(['is_owner' => true]);
    $order = Order::factory()->create(['status' => 'selesai', 'total' => 50000, 'paid_amount' => 50000]);
    $transaction = OrderTransaction::factory()->withDailyBalance()->create([
        'order_id' => $order->id, 'amount' => 50000, 'type' => 'Pembayaran Lunas',
        'channel_breakdown' => [['label' => 'Tunai', 'amount' => 50000]],
        'paid_at' => now(),
    ]);

    $this->actingAs($owner, 'admin')
        ->delete(route('admin.finance.transactions.destroy', $transaction))
        ->assertSessionHasNoErrors();

    expect($order->refresh()->status)->toBe('pelunasan')
        ->and($order->settlement_entered_at->toDateTimeString())->toBe('2026-09-15 11:00:00');
});

test('demo stage transitions record entry time only when entering settlement', function () {
    $script = <<<'JS'
const fs = require('node:fs');
const ts = require('typescript');
const assert = require('node:assert/strict');
const source = fs.readFileSync('resources/js/pages/admin/Orders.vue', 'utf8');
const script = source.split('<script setup lang="ts">')[1].split('</script>')[0];
const ast = ts.createSourceFile('Orders.ts', script, ts.ScriptTarget.Latest, true);
const declarations = ast.statements.filter(node => ts.isFunctionDeclaration(node) && ['setStatus', 'canEditStatus'].includes(node.name?.text));
const props = { capabilities: { update: true } };
let clock = 1000;
Date.now = () => clock;
eval(ts.transpile(declarations.map(node => node.getText(ast)).join('\n')) + `
const order = { source: 'walk-in', status: 'proses' };
setStatus(order, 'pelunasan');
assert.equal(order.settlementEnteredAt, 1000);
clock = 2000;
setStatus(order, 'pelunasan');
assert.equal(order.settlementEnteredAt, 1000);
setStatus(order, 'proses');
setStatus(order, 'pelunasan');
assert.equal(order.settlementEnteredAt, 2000);
`);
JS;

    $result = Process::path(base_path())->run(['node', '-e', $script]);

    expect($result->successful())->toBeTrue($result->errorOutput());
});

test('the cashier sorts settlements by entry time and running orders by arrival without mutating the source', function () {
    $script = <<<'JS'
const fs = require('node:fs');
const ts = require('typescript');
const assert = require('node:assert/strict');
const { ref, computed } = require('vue');
const source = fs.readFileSync('resources/js/pages/admin/Pos.vue', 'utf8');
const script = source.split('<script setup lang="ts">')[1].split('</script>')[0];
const ast = ts.createSourceFile('Pos.ts', script, ts.ScriptTarget.Latest, true);
const names = ['search', 'showAllOrders', 'visibleOrders', 'settlementGroups'];
const declarations = ast.statements.filter(node => ts.isVariableStatement(node) && node.declarationList.declarations.some(declaration => names.includes(declaration.name.getText(ast))));
const code = ts.transpile(declarations.map(node => node.getText(ast)).join('\n'));
const props = { filters: { date: '2026-09-15', today: '2026-09-15' } };
const formatDate = value => value;
const order = (id, settlementEnteredAt, date = '2026-09-15', time = '08.00', status = 'pelunasan') => ({ id, settlementEnteredAt, date, time, status, orderNo: `ORD-${id}`, customer: 'Customer', plate: 'B123AA' });
const orderList = ref([order(1, 3000), order(2, 1000), order(3, 2000), order(5, 4000, '2026-09-13'), order(4, 500, '2026-09-14'), order(6, 2000)]);
eval(code + `
assert.deepEqual(settlementGroups.value[0].orders.map(order => order.id), [2, 3, 6, 1]);
assert.deepEqual(settlementGroups.value[1].orders.map(order => order.id), [4, 5]);
assert.deepEqual(orderList.value.map(order => order.id), [1, 2, 3, 5, 4, 6]);
orderList.value[1].paidAmount = 20000;
assert.equal(settlementGroups.value[0].orders[0].id, 2);
orderList.value = [...orderList.value].reverse();
assert.deepEqual(settlementGroups.value[0].orders.map(order => order.id), [2, 3, 6, 1]);
search.value = 'ord-1';
assert.deepEqual(visibleOrders.value.map(order => order.id), [1]);
search.value = '';
showAllOrders.value = true;
orderList.value = [
    order(10, null, '2026-09-15', '11.30', 'menunggu'),
    order(11, 100, '2026-09-15', '07.45'),
    order(12, null, '2026-09-15', '—', 'booking'),
    order(13, null, '2026-09-14', '16.00', 'proses'),
    order(14, null, '2026-09-15', '09.10', 'selesai'),
];
assert.deepEqual(settlementGroups.value[0].orders.map(order => order.id), [11, 10, 12]);
assert.deepEqual(settlementGroups.value[1].orders.map(order => order.id), [13]);
assert.deepEqual(orderList.value.map(order => order.id), [10, 11, 12, 13, 14]);
`);
JS;

    $result = Process::path(base_path())->run(['node', '-e', $script]);

    expect($result->successful())->toBeTrue($result->errorOutput());
});
