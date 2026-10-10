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

test('the cashier lists completed orders by their settling payment, newest first', function () {
    $result = runPosScript(
        ['compareTransactionsByPaidAt', 'settlingTransaction', 'completedSearch', 'visibleCompletedOrders'],
        <<<'JS'
const order = (id, orderNo, transactions) => ({ id, orderNo, invoice: `INV-${id}`, customer: 'Customer', plate: 'B123AA', date: '2026-09-23', status: 'selesai', paymentStatus: 'lunas', transactions });
const settlementOrderList = ref([
    order(1, 'ORD-003', [transaction('TRX-1', 1, '09.00')]),
    order(2, 'ORD-001', [transaction('TRX-2', 2, '08.00'), transaction('TRX-3', 2, '16.45')]),
    order(3, 'ORD-002', [transaction('TRX-4', 3, '11.20')]),
    order(4, 'ORD-004', []),
]);
assert.deepEqual(visibleCompletedOrders.value.map(entry => entry.id), [2, 3, 1, 4]);
JS,
    );

    expect($result->successful())->toBeTrue($result->errorOutput());
});
