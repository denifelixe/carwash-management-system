<?php

use Illuminate\Contracts\Process\ProcessResult;
use Illuminate\Support\Facades\Process;

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
const orderList = ref([
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
