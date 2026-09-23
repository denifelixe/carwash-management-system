<?php

use Illuminate\Support\Facades\Process;

test('the slip header is the short form agreed in the 17 September meeting', function () {
    $script = <<<'JS'
const fs = require('node:fs');
const ts = require('typescript');
const assert = require('node:assert/strict');
const source = fs.readFileSync('resources/js/lib/posReceipt.ts', 'utf8');
const ast = ts.createSourceFile('posReceipt.ts', source, ts.ScriptTarget.Latest, true);
const code = ts.transpile(ast.statements
    .filter(node => ts.isFunctionDeclaration(node) && ['receiptTransactionRows', 'receiptCustomerRows'].includes(node.name?.text))
    .map(node => node.getText(ast))
    .join('\n'));
const formatDate = value => value === '2026-09-17' ? '17 Sep 2026' : value;
const formatPlate = value => value === 'B1916C' ? 'B 1916 C' : value;
eval(code + `
const receipt = {
    orderNo: 'ORD-20260917-IYQI', invoice: 'ZW-20260917-IYQI', reference: 'ORD-20260917-IYQI-TRX',
    date: '2026-09-17', time: '17.05', cashier: 'Puteri Maul', shift: 'Shift Sore',
    customer: 'Kak Nabil', customerStatus: 'Non-member', vehicle: 'Inno', plate: 'B1916C',
};
assert.deepEqual(receiptTransactionRows(receipt), [
    ['Order', 'ORD-20260917-IYQI'],
    ['Tanggal', '17 Sep 2026 · 17.05'],
    ['Kasir', 'Puteri Maul'],
]);
assert.deepEqual(receiptCustomerRows(receipt), [
    ['Customer', 'Kak Nabil (Non-member)'],
    ['Kendaraan / Plat', 'Inno / B 1916 C'],
]);
const printed = JSON.stringify([...receiptTransactionRows(receipt), ...receiptCustomerRows(receipt)]);
for (const dropped of ['ZW-20260917-IYQI', 'ORD-20260917-IYQI-TRX', 'Shift Sore']) {
    assert.ok(!printed.includes(dropped), dropped + ' must not print');
}
`);
JS;

    $result = Process::path(base_path())->run(['node', '-e', $script]);

    expect($result->successful())->toBeTrue($result->errorOutput());
});

test('both slip layouts print the header from the shared rows', function () {
    expect(file_get_contents(resource_path('js/lib/posReceipt.ts')))
        ->toContain('receiptTransactionRows(receipt)')
        ->toContain('receiptCustomerRows(receipt)')
        ->not->toContain("metaRow('Shift'")
        ->not->toContain("metaRow('Ref.'");

    expect(file_get_contents(resource_path('js/lib/posReceiptPdf.ts')))
        ->toContain('receiptTransactionRows(receipt)')
        ->toContain('receiptCustomerRows(receipt)')
        ->not->toContain("slip.meta('Shift'")
        ->not->toContain("slip.meta('Ref.'");
});
