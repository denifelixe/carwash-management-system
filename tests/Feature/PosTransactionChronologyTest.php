<?php

use Illuminate\Contracts\Process\ProcessResult;
use Illuminate\Support\Facades\Process;

test('the cashier separates received money from completed order value across dates and shifts', function () {
    $result = runPosScript(
        [
            'paymentRecapOrderList', 'compareTransactionsByPaidAt', 'paymentRecapTransactions',
            'paymentRecapPartialTransactions', 'paymentRecapFinalTransactions',
            'paymentRecapFinalTotal', 'paymentRecapPartialTotal', 'collectedTotal',
            'completedOrderRecap', 'completedOrderTotal', 'activeCompletedOrderRecap', 'activeCompletedOrderTotal',
            'isTransactionInShift', 'isTransactionInActiveShift',
        ],
        <<<'JS'
const props = ref({ mode: 'live', filters: { date: '2026-10-01' }, shifts: [{ key: 'morning', name: 'Pagi' }, { key: 'evening', name: 'Sore' }], paymentRecapOrders: [] }).value;
const workflow = { orders: ref([]) };
const orderList = ref([]);
const paymentRecapTotalKey = 'total';
const paymentRecapUnassignedKey = 'tanpa-shift';
const activePaymentRecapShift = ref('total');
const activeCompletedOrderShift = ref('total');
const payment = (id, date, amount, type, shift) => ({ id, orderId: 1, date, time: '10.00', amount, type, shift });
const deposit = payment('TRX1', '2026-10-01', 500000, 'Pembayaran Sebagian', 'Pagi');
const final = payment('TRX2', '2026-10-03', 1500000, 'Pembayaran Lunas', 'Sore');
props.paymentRecapOrders = [{ id: 1, status: 'selesai', total: 2000000, transactions: [deposit, final] }];
assert.equal(collectedTotal.value, 500000);
assert.equal(completedOrderTotal.value, 0);
props.filters.date = '2026-10-03';
assert.equal(collectedTotal.value, 1500000);
assert.equal(completedOrderTotal.value, 2000000);
orderList.value = props.paymentRecapOrders;
assert.equal(completedOrderTotal.value, 2000000, 'overlapping data must not double count');
activeCompletedOrderShift.value = 'morning';
assert.equal(activeCompletedOrderTotal.value, 0);
activeCompletedOrderShift.value = 'evening';
assert.equal(activeCompletedOrderTotal.value, 2000000);
activeCompletedOrderShift.value = 'tanpa-shift';
for (const shift of [null, 'Retired']) {
    orderList.value[0].transactions[1].shift = shift;
    assert.equal(activeCompletedOrderTotal.value, 2000000);
}
orderList.value[0].status = 'pelunasan';
orderList.value[0].transactions.splice(0, 1);
orderList.value[0].transactions[0].type = 'Pembayaran Sebagian';
assert.equal(completedOrderTotal.value, 0, 'a reopened order must disappear from completed value');
orderList.value[0].status = 'selesai';
orderList.value[0].transactions.push(payment('TRX3', '2026-10-04', 500000, 'Pembayaran Lunas', 'Sore'));
assert.equal(completedOrderTotal.value, 0);
props.filters.date = '2026-10-04';
assert.equal(completedOrderTotal.value, 2000000);

props.mode = 'demo';
props.filters.date = '2026-10-01';
workflow.orders.value = [{ id: 1, status: 'booking', total: 1800000, transactions: [payment('TRX1', '2026-10-01', 1800000, 'Pembayaran Sebagian', 'Pagi')] }];
assert.equal(collectedTotal.value, 1800000);
assert.equal(completedOrderTotal.value, 1800000, 'fully prepaid bookings count on the day they become fully paid');
props.filters.date = '2026-10-03';
workflow.orders.value[0].status = 'selesai';
workflow.orders.value[0].transactions.push(payment('TRX2', '2026-10-03', 0, 'Pembayaran Lunas', 'Sore'));
assert.equal(collectedTotal.value, 0);
assert.equal(completedOrderTotal.value, 0, 'closing a prepaid booking must not count its value again');
activeCompletedOrderShift.value = 'evening';
assert.equal(activeCompletedOrderTotal.value, 0);
props.filters.date = '2026-10-01';
assert.equal(completedOrderTotal.value, 1800000);
activeCompletedOrderShift.value = 'morning';
assert.equal(activeCompletedOrderTotal.value, 1800000);
workflow.orders.value.push({ id: 2, status: 'selesai', total: 900000, transactions: [] });
workflow.orders.value.push({ id: 3, status: 'batal', total: 900000, transactions: [final] });
assert.equal(completedOrderTotal.value, 1800000, 'cancelled orders and undated completions do not count');
props.filters.date = '2026-10-03';
workflow.orders.value = [{ id: 4, status: 'selesai', total: 100000, transactions: [payment('TRX4', '2026-10-03', 100000, 'Pembayaran Lunas', 'Sore')] }];
assert.equal(collectedTotal.value, 100000);
assert.equal(completedOrderTotal.value, 100000, 'one full payment contributes to both figures');
JS,
    );

    expect($result->successful())->toBeTrue($result->errorOutput());
});

/**
 * Runs the named top-level declarations of Pos.vue's script against the given
 * fixture setup and assertions.
 *
 * @param  list<string>  $names
 */
function runPosScript(array $names, string $body): ProcessResult
{
    $script = <<<'JS'
const fs = require('node:fs');
const ts = require('typescript');
const assert = require('node:assert/strict');
const { ref, computed } = require('vue');
const source = fs.readFileSync('resources/js/pages/admin/Pos.vue', 'utf8');
const script = source.split('<script setup lang="ts">')[1].split('</script>')[0];
const ast = ts.createSourceFile('Pos.ts', script, ts.ScriptTarget.Latest, true);
const names = JSON.parse(process.argv[1]);
const declarations = ast.statements.filter(node =>
    (ts.isFunctionDeclaration(node) && names.includes(node.name?.text)) ||
    (ts.isVariableStatement(node) && node.declarationList.declarations.some(declaration => names.includes(declaration.name.getText(ast)))));
const code = ts.transpile(declarations.map(node => node.getText(ast)).join('\n'));
const transaction = (id, orderId, time, date = '2026-09-23') => ({ id, orderId, date, time, amount: 10000, type: 'Pembayaran Lunas' });
eval(code + '\n' + process.argv[2]);
JS;

    return Process::path(base_path())->run(['node', '-e', $script, json_encode($names), $body]);
}

test('the cashier recap lists the day payments in the order they were received', function () {
    $result = runPosScript(
        ['compareTransactionsByPaidAt', 'paymentRecapTransactions'],
        <<<'JS'
const props = { filters: { date: '2026-09-23' } };
const paymentRecapOrderList = ref([
    { id: 1, transactions: [transaction('TRX-1', 1, '15.30')] },
    { id: 2, transactions: [transaction('TRX-2', 2, '08.05'), transaction('TRX-3', 2, '12.00')] },
    { id: 3, transactions: [transaction('TRX-5', 3, '09.10'), transaction('TRX-4', 3, '09.10'), transaction('TRX-6', 3, '07.00', '2026-09-22')] },
]);
assert.deepEqual(paymentRecapTransactions.value.map(entry => entry.id), ['TRX-2', 'TRX-4', 'TRX-5', 'TRX-3', 'TRX-1']);
JS,
    );

    expect($result->successful())->toBeTrue($result->errorOutput());
});

test('received payment cards distinguish arrival time from the booking schedule', function () {
    $result = runPosScript(
        ['orderArrivalLabel', 'receivedOrderArrivalLabel'],
        <<<'JS'
const formatDate = date => date;
const order = { date: '2026-10-07', arrivalDate: '2026-10-08', time: '15.48', status: 'selesai' };
assert.equal(receivedOrderArrivalLabel(order), '2026-10-08 · 15.48');
order.time = '—';
order.arrivalDate = null;
order.status = 'booking';
assert.equal(receivedOrderArrivalLabel(order), 'Belum datang');
order.status = 'batal';
assert.equal(receivedOrderArrivalLabel(order), 'Tidak tercatat (dibatalkan)');
order.status = 'menunggu';
assert.equal(receivedOrderArrivalLabel(order), 'Belum tercatat');
JS,
    );

    expect($result->successful())->toBeTrue($result->errorOutput());
});

test('received payment cards emphasize only orders settled on the selected day', function () {
    $result = runPosScript(
        ['compareTransactionsByPaidAt', 'paymentHistory', 'isReceivedOnSelectedDate', 'receivedPayments', 'isSettledOnSelectedDate'],
        <<<'JS'
const props = { filters: { date: '2026-09-23', today: '2026-09-24' } };
const order = { status: 'selesai', total: 10000, paymentStatus: 'lunas', transactions: [transaction('TRX-1', 1, '10.00')] };
assert.equal(isSettledOnSelectedDate(order), true);
props.filters.date = '2026-09-24';
assert.equal(isSettledOnSelectedDate(order), false);
props.filters.date = '2026-09-23';
order.transactions[0].type = 'Pembayaran Sebagian';
order.status = 'booking';
assert.equal(isSettledOnSelectedDate(order), true, 'a fully prepaid booking is emphasized even with a partial payment label');
order.transactions[0].type = 'Pembayaran Lunas';
order.paymentStatus = 'sebagian';
assert.equal(isSettledOnSelectedDate(order), false);
order.paymentStatus = 'lunas';
order.transactions[0].amount = 0;
assert.equal(isSettledOnSelectedDate(order), false);
order.transactions = [transaction('TRX-LATER', 1, '12.00', '2026-09-24'), { ...transaction('TRX-1', 1, '10.00'), amount: 6000 }, { ...transaction('TRX-2', 1, '11.00'), amount: 4000 }];
assert.equal(isSettledOnSelectedDate(order), true, 'split deposits are recognized when they first cover the bill');
props.filters.date = '2026-09-24';
assert.equal(isSettledOnSelectedDate(order), false, 'later transactions do not recognize the order value again');
props.filters.date = '2026-09-23';
order.status = 'batal';
assert.equal(isSettledOnSelectedDate(order), false);
JS,
    );

    expect($result->successful())->toBeTrue($result->errorOutput());
});

test('fully paid bookings leave the partial payment queue and return when payment is removed', function () {
    $result = runPosScript(
        ['visiblePartialPaymentBookings'],
        <<<'JS'
const partialPaymentSearch = ref('');
const props = ref({ filters: { today: '2026-10-22' } }).value;
const partialPaymentBookingIds = new Set([1, 2, 3, 4, 5]);
const booking = (id, paidAmount, overrides = {}) => ({ id, source: 'booking', status: 'booking', orderNo: `ORD-${id}`, customer: 'Budi', plate: `B${id}AA`, date: '2026-10-23', total: 70000, paidAmount, ...overrides });
const orderList = ref([booking(1, 0), booking(2, 20000), booking(3, 70000), booking(4, 80000), booking(5, 20000, { status: 'batal' }), booking(6, 0)]);
assert.deepEqual(visiblePartialPaymentBookings.value.map(order => order.id), [1, 2]);
orderList.value[1].paidAmount = 70000;
assert.deepEqual(visiblePartialPaymentBookings.value.map(order => order.id), [1]);
orderList.value[2].paidAmount = 20000;
assert.deepEqual(visiblePartialPaymentBookings.value.map(order => order.id), [1, 3]);
partialPaymentSearch.value = 'B3AA';
assert.deepEqual(visiblePartialPaymentBookings.value.map(order => order.id), [3]);
partialPaymentSearch.value = '';
props.filters.today = '2026-10-23';
assert.deepEqual(visiblePartialPaymentBookings.value, [], 'bookings leave the upcoming queue on their scheduled day');
JS,
    );

    expect($result->successful())->toBeTrue($result->errorOutput());
});

test('received payments group orders by transaction date and highlight only that days payments', function () {
    $result = runPosScript(
        [
            'compareTransactionsByPaidAt', 'isReceivedOnSelectedDate', 'paymentHistory',
            'receivedPayments', 'receivedPaymentTotal', 'receivedPaymentSearch',
            'visibleReceivedPaymentOrders', 'receivedPaymentCount',
        ],
        <<<'JS'
const props = ref({ filters: { date: '2026-09-23', today: '2026-09-24' } }).value;
const order = (id, transactions, overrides = {}) => ({ id, orderNo: `ORD-${id}`, invoice: `INV-${id}`, customer: `Customer ${id}`, plate: `B${id}AA`, date: '2026-09-01', arrivalDate: '2026-09-02', status: 'selesai', paymentStatus: 'lunas', transactions, ...overrides });
const partial = (id, orderId, time, date = '2026-09-23') => ({ ...transaction(id, orderId, time, date), type: 'Pembayaran Sebagian' });
const booking = order(2, [partial('TRX-3', 2, '16.45'), partial('TRX-2', 2, '08.00'), transaction('TRX-5', 2, '23.00', '2026-09-24')], { status: 'booking', paymentStatus: 'sebagian', date: '2026-10-10', arrivalDate: null });
const oldOrder = order(1, [transaction('TRX-1', 1, '09.00'), partial('TRX-OLD', 1, '12.00', '2026-09-22')]);
const paymentRecapOrderList = ref([
    oldOrder,
    booking,
    order(3, [partial('TRX-4', 3, '11.20')], { status: 'proses', paymentStatus: 'sebagian' }),
    order(4, []),
    order(5, [transaction('TRX-6', 5, '10.00', '2026-09-24')]),
    order(6, [{ ...transaction('TRX-ZERO', 6, '20.00'), amount: 0 }]),
    booking,
]);
assert.deepEqual(visibleReceivedPaymentOrders.value.map(entry => entry.id), [2, 3, 1]);
assert.equal(receivedPaymentCount.value, 4);
assert.equal(receivedPaymentTotal(booking), 20000);
assert.equal(receivedPaymentTotal(oldOrder), 10000);
assert.deepEqual(paymentHistory(booking).map(entry => entry.id), ['TRX-2', 'TRX-3', 'TRX-5']);
assert.deepEqual(booking.transactions.map(entry => entry.id), ['TRX-3', 'TRX-2', 'TRX-5'], 'sorting never mutates the receipt history');
assert.deepEqual(paymentHistory(booking).filter(isReceivedOnSelectedDate).map(entry => entry.id), ['TRX-2', 'TRX-3']);
assert.equal(isReceivedOnSelectedDate(oldOrder.transactions[1]), false);
for (const query of ['ord-2', 'INV-2', 'Customer 2', ' B2AA ']) {
    receivedPaymentSearch.value = query;
    assert.deepEqual(visibleReceivedPaymentOrders.value.map(entry => entry.id), [2]);
    assert.equal(receivedPaymentCount.value, 2);
}
receivedPaymentSearch.value = 'missing';
assert.equal(visibleReceivedPaymentOrders.value.length, 0);
receivedPaymentSearch.value = '';
props.filters.date = '2026-09-24';
assert.deepEqual(visibleReceivedPaymentOrders.value.map(entry => entry.id), [2, 5]);
assert.deepEqual(receivedPayments(booking).map(entry => entry.id), ['TRX-5']);
props.filters.date = '';
assert.deepEqual(visibleReceivedPaymentOrders.value.map(entry => entry.id), [2, 5]);
props.filters.date = '2026-09-23';
booking.transactions.splice(0, 2);
assert.deepEqual(visibleReceivedPaymentOrders.value.map(entry => entry.id), [3, 1], 'orders leave the list when their selected-day payments are removed');
JS,
    );

    expect($result->successful())->toBeTrue($result->errorOutput());
});
