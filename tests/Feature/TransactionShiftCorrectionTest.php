<?php

use App\Actions\Admin\UpdateDailyBalance;
use App\Models\Admin;
use App\Models\AdminShift;
use App\Models\CashEntry;
use App\Models\DailyBalance;
use App\Support\Admin\FinanceQueries;
use App\Support\Admin\TransactionShiftResolver;
use Illuminate\Support\Facades\Process;
use Inertia\Testing\AssertableInertia;

beforeEach(function () {
    $this->travelTo('2026-09-06 14:45:00');
});

test('shift correction moves only the selected transaction and preserves balances and login shift', function (string $kind, bool $unassigned) {
    $admin = Admin::factory()->create(['is_owner' => true, 'shift_mode' => 'schedule']);
    $morning = AdminShift::query()->where('key', 'morning')->firstOrFail();
    $evening = AdminShift::query()->where('key', 'evening')->firstOrFail();
    $this->post(route('admin.login.store'), ['email' => $admin->email, 'password' => 'password'])->assertSessionHasNoErrors();
    $sessionShift = session('transaction_shift');

    $this->travelTo('2026-09-05 10:00:00');
    if ($kind === 'pos') {
        $order = paidOrder($admin, 50000);
        $record = $order->transactions()->sole();
        app(UpdateDailyBalance::class)->handle(today()->toDateString(), cashIncomeDelta: 50000);
        $route = route('admin.finance.transactions.update', $record);
        $payload = ['amount' => 50000, 'channels' => [['label' => 'Tunai', 'amount' => 50000]]];
    } else {
        $factory = CashEntry::factory()->withDailyBalance();
        $record = ($kind === 'out' ? $factory->moneyOut() : $factory)->create(['amount' => 50000]);
        $route = route('admin.finance.update', $record);
        $payload = [...$record->only(['category', 'description', 'amount', 'method']), 'entry_date' => '2026-09-05', 'entry_time' => '10:00'];
    }
    $original = $record->getAttributes();
    $this->travelTo('2026-09-06 17:00:00');
    $other = CashEntry::factory()->withDailyBalance()->create();
    $balances = DailyBalance::query()->orderBy('date')->get()->toArray();

    $this->patch($route, [...$payload, 'transaction_shift_id' => $unassigned ? null : $evening->id])->assertSessionHasNoErrors();

    expect($record->refresh()->shift_name)->toBe($unassigned ? null : $evening->name)
        ->and($record->updated_by_admin_id)->toBe($admin->id)
        ->and($record->amount)->toBe($original['amount'])
        ->and($other->refresh()->shift_name)->toBe($morning->name)
        ->and(DailyBalance::query()->orderBy('date')->get()->toArray())->toBe($balances)
        ->and(session('transaction_shift'))->toBe($sessionShift)
        ->and(app(TransactionShiftResolver::class)->resolve($admin, null, now())?->name)->toBe($morning->name);
    foreach (['paid_at', 'entry_date', 'occurred_at', 'recorded_by_admin_id', 'channel_breakdown'] as $field) {
        if (array_key_exists($field, $original)) {
            expect($record->getRawOriginal($field))->toBe($original[$field]);
        }
    }

    $ledger = FinanceQueries::ledgerForDate('2026-09-05');
    $rows = [...$ledger['moneyIn'], ...$ledger['moneyOut']];
    expect($rows)->toHaveCount(1)
        ->and($rows[0]['shift'])->toBe($unassigned ? null : $evening->name)
        ->and($rows[0]['amount'])->toBe(50000);
    $summary = collect(FinanceQueries::shiftSummary($ledger['moneyIn'], $ledger['moneyOut'], '2026-09-05', true));
    expect($summary->firstWhere('name', $morning->name)[$kind === 'out' ? 'moneyOut' : 'moneyIn'])->toBe(0);
    if ($kind === 'pos') {
        expect($order->refresh()->paid_amount)->toBe(50000);
        $this->get(route('admin.pos.index', ['date' => '2026-09-05']))
            ->assertInertia(fn (AssertableInertia $page) => $page->where('dailyOrders.0.transactions.0.shift', $unassigned ? null : $evening->name));
    }
})->with(['pos', 'in', 'out'])->with([false, true]);

test('omitting shift correction preserves retired shift names', function (string $kind) {
    $admin = Admin::factory()->create(['is_owner' => true]);
    if ($kind === 'pos') {
        $record = paidOrder($admin)->transactions()->sole();
        $route = route('admin.finance.transactions.update', $record);
        $payload = ['amount' => $record->amount, 'channels' => [['label' => 'Tunai', 'amount' => $record->amount]]];
    } else {
        $record = CashEntry::factory()->create();
        $route = route('admin.finance.update', $record);
        $payload = [...$record->only(['category', 'description', 'amount', 'method']), 'entry_date' => today()->toDateString(), 'entry_time' => '14:45'];
    }
    $record->update(['shift_name' => 'Shift Lama']);
    $this->actingAs($admin, 'admin')->patch($route, $payload)->assertSessionHasNoErrors();
    expect($record->refresh()->shift_name)->toBe('Shift Lama');
})->with(['pos', 'manual']);

test('shift corrections validate active ids permission and the operational window', function (string $kind, string $case) {
    $admin = Admin::factory()->create(['is_owner' => true]);
    $shift = AdminShift::query()->where('key', 'evening')->firstOrFail();
    $age = $case === 'too old' ? 31 : 30;
    $date = today()->subDays($age);
    $this->travelTo($date->setTime(10, 0));
    if ($kind === 'pos') {
        $record = paidOrder($admin)->transactions()->sole();
        $route = route('admin.finance.transactions.update', $record);
        $payload = ['amount' => $record->amount, 'channels' => [['label' => 'Tunai', 'amount' => $record->amount]]];
    } else {
        $record = CashEntry::factory()->create();
        $route = route('admin.finance.update', $record);
        $payload = [...$record->only(['category', 'description', 'amount', 'method']), 'entry_date' => $date->toDateString(), 'entry_time' => '10:00'];
    }
    $this->travelTo('2026-09-06 14:45:00');
    if ($case === 'inactive') {
        $shift->update(['is_active' => false]);
    }
    $actor = $case === 'forbidden' ? Admin::factory()->create(['role_id' => null, 'is_owner' => false]) : $admin;
    $response = $this->actingAs($actor, 'admin')->patch($route, [...$payload, 'transaction_shift_id' => $case === 'invalid' ? 999999 : $shift->id]);
    if (in_array($case, ['invalid', 'inactive'], true)) {
        $response->assertSessionHasErrors('transaction_shift_id');
    } elseif ($case === 'forbidden') {
        $response->assertForbidden();
    } elseif ($case === 'too old') {
        if ($kind === 'pos') {
            $response->assertUnprocessable();
        } else {
            $response->assertSessionHasErrors('entry_date');
        }
    } else {
        $response->assertSessionHasNoErrors();
    }
    expect($record->refresh()->shift_name)->toBe($case === 'boundary' ? $shift->name : 'Shift Pagi');
})->with(['pos', 'manual'])->with(['invalid', 'inactive', 'forbidden', 'too old', 'boundary']);

test('finance provides every active shift independently of the login window', function () {
    $admin = Admin::factory()->create(['is_owner' => true, 'shift_mode' => 'schedule']);
    $untimed = AdminShift::query()->create(['key' => 'untimed', 'name' => 'Tanpa Jam', 'is_active' => true]);
    AdminShift::query()->create(['key' => 'retired', 'name' => 'Nonaktif', 'is_active' => false]);
    $this->actingAs($admin, 'admin')->get(route('admin.finance.index'))
        ->assertInertia(fn (AssertableInertia $page) => $page
            ->has('shiftOptions', 3)
            ->where('shiftOptions.0.id', $untimed->id)
            ->has('transactionShift.shifts', 1));
});

test('finance forms preserve omitted shifts and apply demo corrections to shared transaction state', function () {
    $script = <<<'JS'
const fs = require('node:fs');
const ts = require('typescript');
const assert = require('node:assert/strict');
const source = fs.readFileSync('resources/js/pages/admin/Finance.vue', 'utf8').split('<script setup lang="ts">')[1].split('</script>')[0];
const ast = ts.createSourceFile('Finance.ts', source, ts.ScriptTarget.Latest, true);
const names = ['correctedShiftName', 'savePosTransaction', 'saveDemoEntry', 'saveLiveEntry', 'isEditable'];
const code = ts.transpile(ast.statements.filter(node => ts.isFunctionDeclaration(node) && names.includes(node.name.text)).map(node => node.getText(ast)).join('\n'), { target: ts.ScriptTarget.ES2020 });
const state = {
    props: { mode: 'demo', capabilities: { edit_cash_entry_backdate: false }, shiftOptions: [{ id: 2, name: 'Shift Sore' }], filters: { today: '2026-09-06' } },
    shiftCorrection: { value: 2 },
    editingPosEntry: { value: null }, editingEntry: { value: null },
    canSavePosTransaction: { value: true },
    transactionForm: { amount: 50000, channels: [{ label: 'Tunai', amount: 50000, provider: '', reference: '' }], setError() { throw Error('Unexpected validation failure'); } },
    closePosTransactionForm() {}, closeEntryForm() {},
    findRelatedOrder() { return state.order; },
    transactionIdFromEntry(entry) { return entry.id.slice(4); },
    posTransactionId() { return 1; }, cashEntryId() { return 1; },
    updateOrderTransaction() { return {}; }, updateCashEntry() { return {}; },
    activeLedger: { value: 'in' },
    draft: { value: { category: 'Penjualan Produk', description: 'Parfum', amount: 50000, method: 'Tunai' } },
    entryForm: { entry_date: '2026-09-05', entry_time: '10:00' },
    removedAttachmentIds: { value: [] }, pendingAttachments: { value: [] },
};
const api = new Function('state', `with (state) { ${code}; return { ${names.join(',')} }; }`)(state);
for (const [choice, expected] of [[2, 'Shift Sore'], [null, null], ['keep', 'Shift Lama']]) {
    state.shiftCorrection.value = choice;
    const transaction = { id: 'trx1', amount: 50000, shift: 'Shift Lama', date: '2026-09-05' };
    state.order = { total: 50000, paidAmount: 50000, status: 'selesai', transactions: [transaction] };
    const posEntry = { id: 'pos-trx1', amount: 50000, shift: 'Shift Lama', date: '2026-09-05' };
    state.editingPosEntry.value = posEntry;
    api.savePosTransaction();
    assert.equal(transaction.shift, expected);
    assert.equal(posEntry.shift, expected);
    assert.equal(state.order.paidAmount, 50000);
    assert.equal(transaction.date, '2026-09-05');
    for (const direction of ['in', 'out']) {
        const entry = { id: 'manual1', direction, amount: 50000, shift: 'Shift Lama', recordedBy: 'Kasir', date: '2026-09-05', attachments: [] };
        state.editingEntry.value = entry;
        api.saveDemoEntry(null);
        assert.equal(entry.shift, expected);
        assert.equal(entry.id, 'manual1');
        assert.equal(entry.recordedBy, 'Kasir');
        assert.equal(entry.amount, 50000);
    }
}
assert.equal(api.isEditable({ date: '2026-08-07' }), true);
assert.equal(api.isEditable({ date: '2026-08-06' }), false);
state.props.capabilities.edit_cash_entry_backdate = true;
state.transactionForm.entry_date = '2026-09-04';
state.transactionForm.entry_time = '08:45';
api.savePosTransaction();
assert.equal(state.order.transactions[0].date, '2026-09-04');
assert.equal(state.order.transactions[0].time, '08.45');
assert.equal(state.editingPosEntry.value.date, '2026-09-04');
assert.equal(state.editingPosEntry.value.time, '08.45');
state.props.mode = 'live';
for (const form of [state.transactionForm, state.entryForm]) {
    form.transform = function (callback) { this.transformer = callback; return this; };
    form.submit = function () { this.payload = this.transformer({ amount: 50000, channels: [], transaction_shift_id: null }); };
}
for (const choice of ['keep', null, 2]) {
    state.shiftCorrection.value = choice;
    api.savePosTransaction();
    api.saveLiveEntry(null);
    for (const form of [state.transactionForm, state.entryForm]) {
        assert.equal(Object.hasOwn(form.payload, 'transaction_shift_id'), choice !== 'keep');
        if (choice !== 'keep') assert.equal(form.payload.transaction_shift_id, choice);
    }
}
console.log('passed');
JS;

    $result = Process::path(base_path())->run(['node', '-e', $script]);
    expect($result->successful())->toBeTrue($result->errorOutput());
});
