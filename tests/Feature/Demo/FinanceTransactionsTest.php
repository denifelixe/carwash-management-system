<?php

use App\Support\Demo\DateFilter;
use App\Support\Demo\Finance;
use App\Support\Demo\Operations;
use App\Support\Demo\Reports;
use App\Support\Demo\RoleAccess;
use Illuminate\Support\Facades\Process;
use Inertia\Testing\AssertableInertia;

test('finance channel totals include bank providers and split payments', function () {
    $script = <<<'JS'
const fs = require('node:fs');
const ts = require('typescript');
const source = fs.readFileSync('resources/js/pages/admin/Finance.vue', 'utf8');
const start = source.indexOf('function channelTotal(');
const end = source.indexOf('\nconst channelRows', start);
const code = ts.transpile(source.slice(start, end));
const channelTotal = new Function(`${code}; return channelTotal;`)();
const entries = [
    { channelBreakdown: [{ label: 'Kredit · Mandiri', amount: 20000 }] },
    { channelBreakdown: [
        { label: 'Tunai', amount: 5000 },
        { label: 'Kredit · BCA', amount: 10000 },
        { label: 'Debit · Mandiri', amount: 7000 },
    ] },
    { channelBreakdown: [{ label: 'Kredit', amount: 3000 }] },
    { channelBreakdown: [{ label: 'Transfer · Mandiri', amount: 11000 }] },
    { channelBreakdown: [{ label: 'QRIS', amount: 13000 }] },
    { channelBreakdown: [{ label: 'E-Money', amount: 17000 }] },
];
console.log(JSON.stringify(
    ['Tunai', 'Kredit', 'Debit', 'Transfer', 'QRIS', 'E-Money']
        .map(channel => channelTotal(entries, channel))
));
JS;

    $result = Process::path(base_path())->run(['node', '-e', $script]);

    expect($result->successful())->toBeTrue($result->errorOutput())
        ->and(json_decode($result->output(), true))
        ->toBe([5000, 33000, 7000, 11000, 13000, 17000]);
});

test('POS income records every received payment on its transaction date', function () {
    $posEntries = array_values(array_filter(
        Finance::moneyIn(),
        fn (array $entry): bool => $entry['source'] === 'pos',
    ));
    $expectedTransactions = [];

    foreach (Operations::orders() as $order) {
        foreach ($order['transactions'] as $transaction) {
            if ($transaction['amount'] > 0) {
                $expectedTransactions['pos-'.$transaction['id']] = [
                    $order,
                    $transaction,
                ];
            }
        }
    }

    $actualIdentifiers = array_column($posEntries, 'id');
    $expectedIdentifiers = array_keys($expectedTransactions);

    sort($actualIdentifiers);
    sort($expectedIdentifiers);

    expect($actualIdentifiers)->toBe($expectedIdentifiers);

    foreach ($posEntries as $entry) {
        [$order, $transaction] = $expectedTransactions[$entry['id']];

        expect($entry)
            ->toMatchArray([
                'ref' => $transaction['id'],
                'date' => $transaction['date'],
                'time' => $transaction['time'],
                'category' => $transaction['type'] === 'Pembayaran Sebagian'
                    ? 'Pembayaran Sebagian/Booking Order'
                    : 'Pembayaran Sisa/Lunas (Order Selesai)',
                'amount' => $transaction['amount'],
                'method' => $transaction['channels'],
                'orderId' => $order['id'],
                'orderNo' => $order['orderNo'],
                'customer' => $order['customer'],
                'vehicle' => $order['vehicle'],
                'plate' => $order['plate'],
            ]);
    }

    expect(array_column($posEntries, 'description'))
        ->each->not->toContain('Setoran POS');

    expect(Finance::incomeCategories())
        ->toContain('Pembayaran Sebagian/Booking Order', 'Pembayaran Sisa/Lunas (Order Selesai)')
        ->not->toContain(
            'Pembayaran Sebagian Order',
            'Pembayaran Lunas Order',
            'Pembayaran Sebagian Booking',
            'Pelunasan Order',
            'Penjualan Layanan',
        );
});

test('finance and POS agree on todays partial payment transaction count', function () {
    $today = Reports::todayDate();
    $posPartialPayments = collect(Operations::orders())
        ->flatMap(fn (array $order): array => $order['transactions'])
        ->where('date', $today)
        ->where('type', 'Pembayaran Sebagian')
        ->values();
    $financePartialPayments = collect(Finance::moneyIn())
        ->where('date', $today)
        ->where('category', 'Pembayaran Sebagian/Booking Order')
        ->values();

    expect($posPartialPayments)->toHaveCount(4)
        ->and($financePartialPayments)->toHaveCount($posPartialPayments->count());
});

test('finance separates order payment references from daily cash numbers', function () {
    $entries = [...Finance::moneyIn(), ...Finance::moneyOut()];

    foreach ($entries as $entry) {
        expect(preg_match(
            ($entry['source'] ?? 'manual') === 'pos' ? '/^\d{8}\/ORD\/(?:BK\/)?\d{4}\/TRX\d+$/' : '/^TRX-[A-Z0-9]+-\d{6}-\d{4}$/',
            $entry['ref'],
        ))->toBe(1);
    }

    $partialPayment = collect(Finance::moneyIn())
        ->firstWhere('category', 'Pembayaran Sebagian/Booking Order');
    $finalPayment = collect(Finance::moneyIn())
        ->firstWhere('category', 'Pembayaran Sisa/Lunas (Order Selesai)');
    $productSale = collect(Finance::moneyIn())
        ->firstWhere('category', 'Penjualan Produk');
    $materialPurchase = collect(Finance::moneyOut())
        ->firstWhere('category', 'Pembelian Bahan');

    expect($partialPayment['ref'])->toContain('/TRX')
        ->and($finalPayment['ref'])->toContain('/TRX')
        ->and($productSale['ref'])->toStartWith('TRX-PP-')
        ->and($materialPurchase['ref'])->toStartWith('TRX-PB-')
        ->and(implode(' ', array_column(Finance::moneyIn(), 'ref')))
        ->not->toContain('ZW');

    $installmentReferences = collect(Finance::moneyIn())
        ->where('orderNo', collect(Operations::orders())->firstWhere('id', 10)['orderNo'])
        ->pluck('ref')
        ->sort()
        ->values()
        ->all();

    expect($installmentReferences)->toBe([
        collect(Operations::orders())->firstWhere('id', 10)['orderNo'].'/TRX1',
        collect(Operations::orders())->firstWhere('id', 10)['orderNo'].'/TRX2',
        collect(Operations::orders())->firstWhere('id', 10)['orderNo'].'/TRX3',
    ]);

    $financePage = file_get_contents(
        resource_path('js/pages/admin/Finance.vue'),
    );

    expect($financePage)
        ->toContain('function transactionReference(')
        ->toContain('`TRX-${categoryCode}-${formatDateCode(date)}-${stableIdentifier}`')
        ->toContain('ref: nextDemoCashReference(')
        ->toContain('props.capabilities.edit_cash_entry_backdate')
        ->toContain('? props.filters.date')
        ->toContain(': props.filters.today;')
        ->toContain('entryForm.entry_time = outletClock();')
        ->toContain('v-model="entryForm.entry_date"')
        ->toContain('v-model="entryForm.entry_time"')
        ->toContain('v-model="transactionForm.entry_date"')
        ->toContain('v-model="transactionForm.entry_time"')
        ->toContain('v-if="capabilities.edit_cash_entry_backdate"')
        ->toContain('date: entryForm.entry_date,')
        ->toContain("time: entryForm.entry_time.replace(':', '.'),")
        ->toContain('max-w-48')
        ->toContain('whitespace-normal')
        ->toContain('Pembayaran Sisa/Lunas')
        ->toContain('(Order Selesai)')
        ->and(substr_count($financePage, 'class="block whitespace-nowrap"'))
        ->toBe(2);
});

test('cash summary is calculated from individual ledger transactions', function () {
    $today = Reports::todayDate();
    $todayIn = array_sum(array_column(
        DateFilter::apply(Finance::moneyIn(), $today),
        'amount',
    ));
    $todayOut = array_sum(array_column(
        DateFilter::apply(Finance::moneyOut(), $today),
        'amount',
    ));

    expect(Finance::summary())
        ->toMatchArray([
            'todayIn' => $todayIn,
            'todayOut' => $todayOut,
            'remainingBalance' => $todayIn - $todayOut,
            'closingBalance' => 12400000 + $todayIn - $todayOut,
        ]);
});

test('finance attachments are client-only and keep the current ledger position', function () {
    $financePage = file_get_contents(
        resource_path('js/pages/admin/Finance.vue'),
    );

    expect($financePage)
        ->not->toContain("import { Fancybox } from '@fancyapps/ui';")
        ->toContain("await import('@fancyapps/ui')")
        ->toContain('Fancybox.fromNodes([triggerEl], {')
        ->toContain('@click="openAttachment($event)"')
        ->toContain(':data-type="attachmentLightboxType(attachment)"')
        ->toContain('attachmentLightboxType(attachment)')
        ->not->toContain('LIGHTBOX_GROUP');
});

test('finance attachment clicks wait for the viewer without navigating away', function () {
    $script = <<<'JS'
const fs = require('node:fs');
const ts = require('typescript');
const assert = require('node:assert/strict');
const source = fs.readFileSync('resources/js/pages/admin/Finance.vue', 'utf8').split('<script setup lang="ts">')[1].split('</script>')[0];
const ast = ts.createSourceFile('Finance.ts', source, ts.ScriptTarget.Latest, true);
const statements = ast.statements.filter(node =>
    (ts.isFunctionDeclaration(node) && node.name?.text === 'openAttachment') ||
    (ts.isVariableStatement(node) && node.declarationList.declarations.some(declaration =>
        ['attachmentLightbox', 'attachmentLightboxLoading', 'financePageUnmounted'].includes(declaration.name.getText(ast))
    ))
);
const code = ts.transpileModule(statements.map(node => node.getText(ast)).join('\n')
    .replace("await import('@fancyapps/ui')", 'await loadFancybox()'), {
    compilerOptions: { target: ts.ScriptTarget.ES2022, module: ts.ModuleKind.ESNext },
}).outputText;
assert.equal((fs.readFileSync('resources/js/pages/admin/Finance.vue', 'utf8').match(/@click="openAttachment\(\$event\)"/g) || []).length, 2);

(async () => {
    for (const outcome of ['loaded', 'failed', 'unmounted']) {
        let resolve, reject, loads = 0;
        const pending = new Promise((yes, no) => { resolve = yes; reject = no; });
        const opened = [], errors = [];
        const handler = new Function('loadFancybox', 'toast', code + `
            return { openAttachment, unmount() { financePageUnmounted = true; } };
        `)(() => { loads++; return pending; }, { error: message => errors.push(message) });
        const trigger = { dataset: { type: 'image' }, href: '/finance/attachments/10' };
        const event = { currentTarget: trigger, defaultPrevented: false, preventDefault() { this.defaultPrevented = true; } };
        const opening = handler.openAttachment(event);
        assert.equal(event.defaultPrevented, true, 'navigation must stop before the import resolves');
        await handler.openAttachment(event);
        assert.equal(loads, 1, 'repeated clicks must not open duplicate viewers');
        event.currentTarget = null;
        assert.equal(opened.length, 0);

        if (outcome === 'failed') reject(new Error('module unavailable'));
        else {
            if (outcome === 'unmounted') handler.unmount();
            resolve({ Fancybox: { fromNodes: (nodes, options) => opened.push({ nodes, options }) } });
        }
        await opening;
        assert.equal(opened.length, outcome === 'loaded' ? 1 : 0);
        assert.equal(errors.length, outcome === 'failed' ? 1 : 0);
        if (outcome === 'loaded') assert.deepEqual(opened[0], { nodes: [trigger], options: { Hash: false } });
    }
})().catch(error => { console.error(error); process.exitCode = 1; });
JS;

    $result = Process::path(base_path())->run(['node', '-e', $script]);

    expect($result->successful())->toBeTrue($result->errorOutput());
});

test('dashboard revenue matches finance money in for the selected day', function () {
    $today = Reports::todayDate();
    $income = DateFilter::apply(Finance::moneyIn(), $today);
    $revenue = array_sum(array_column($income, 'amount'));
    $stats = Reports::dashboardStats($today);

    expect($stats[0])
        ->toMatchArray([
            'value' => 'Rp '.number_format($revenue, 0, ',', '.'),
            'caption' => 'dari '.count($income).' transaksi keuangan',
        ]);
});

/*
 * A row belongs to the shift whoever wrote it was rostered onto, stamped on it
 * by name. The clock plays no part: the console would otherwise credit a shift
 * with money taken by someone who was not working it.
 */
test('dashboard shift figures follow the shift each ledger row was booked under', function () {
    $today = Reports::todayDate();
    $shifts = collect(Finance::shiftSummary($today))->keyBy('id');
    $todayIncome = DateFilter::apply(Finance::moneyIn(), $today);
    $todayExpenses = DateFilter::apply(Finance::moneyOut(), $today);

    foreach (['pagi' => 'Shift Pagi', 'sore' => 'Shift Sore'] as $shiftId => $shiftName) {
        $income = collect($todayIncome)->where('shift', $shiftName);
        $expenses = collect($todayExpenses)->where('shift', $shiftName);
        $posIncome = $income->where('source', 'pos');

        expect($shifts[$shiftId])
            ->toMatchArray([
                'revenue' => $posIncome->sum('amount'),
                /* One vehicle per order, however many instalments it took. */
                'vehiclesServed' => $posIncome->pluck('orderId')->unique()->count(),
                'moneyIn' => $income->sum('amount'),
                'moneyOut' => $expenses->sum('amount'),
            ]);
    }

    expect($shifts['pagi']['vehiclesServed'])->toBe(6)
        ->and($shifts['sore']['vehiclesServed'])->toBe(0)
        ->and($shifts->sum('vehiclesServed'))->toBe(6);

    /* The bucket closing the cards holds whatever no rostered shift claimed. */
    $unclaimed = collect($todayIncome)->whereNotIn('shift', ['Shift Pagi', 'Shift Sore']);

    expect($shifts['tanpa-shift'])
        ->toMatchArray([
            'name' => 'Tanpa Shift',
            'status' => '',
            'moneyIn' => $unclaimed->sum('amount'),
            'moneyOut' => collect($todayExpenses)
                ->whereNotIn('shift', ['Shift Pagi', 'Shift Sore'])
                ->sum('amount'),
        ])
        ->and($shifts->sum('moneyIn'))->toBe(collect($todayIncome)->sum('amount'));
});

test('finance page exposes and displays related order details', function () {
    $todayEntries = DateFilter::apply(
        Finance::moneyIn(),
        Reports::todayDate(),
    );
    $posEntry = array_values(array_filter(
        $todayEntries,
        fn (array $entry): bool => $entry['source'] === 'pos',
    ))[0];
    $cashOnlyBalance = Finance::dailyBalance(Reports::todayDate());
    $cashOnlyBalance['nonCash'] = 0;
    $cashOnlyBalance['previous']['nonCash'] = 0;
    $cashOnlyHistory = array_map(
        fn (array $balance): array => [
            ...$balance,
            'nonCashIncome' => 0,
            'nonCashExpense' => 0,
            'nonCashBalance' => 0,
        ],
        Finance::dailyBalanceHistory(Reports::todayDate()),
    );

    $this->withSession([RoleAccess::SESSION_KEY => 'finance'])
        ->get(route('demo.admin.finance'))
        ->assertOk()
        ->assertInertia(
            fn (AssertableInertia $page) => $page
                ->component('admin/Finance')
                ->has('moneyIn', count($todayEntries))
                ->has('orders', count(Operations::orders()))
                ->where('capabilities.view_non_cash_balance', false)
                ->where('capabilities.edit_cash_entry_backdate', false)
                ->where('dailyBalance', $cashOnlyBalance)
                ->where('dailyBalanceHistory', $cashOnlyHistory)
                ->where('moneyIn.0.ref', $todayEntries[0]['ref'])
                ->where(
                    'moneyIn.'.array_search($posEntry, $todayEntries, true).'.orderNo',
                    $posEntry['orderNo'],
                )
        );

    $financePage = file_get_contents(
        resource_path('js/pages/admin/Finance.vue'),
    );

    expect($financePage)
        ->toContain('Order terkait')
        ->toContain('v-if="entry.orderNo"')
        ->toContain('{{ entry.orderNo }}')
        ->toContain('{{ entry.customer }}')
        ->toContain('@click="openTransactionRecap(entry)"')
        ->toContain('@click="openOrderRecap(entry)"')
        ->toContain('title="Detail Transaksi"')
        ->toContain('title="Detail Order"')
        ->toContain('Riwayat transaksi')
        ->toContain('highlightedTransactionId')
        ->toContain('before:inset-y-2 before:left-0 before:w-1')
        ->not->toContain('bg-cyan-50 ring-2 ring-cyan-300 ring-inset')
        ->toContain('{{ entry.vehicle }} ·')
        ->toContain('{{ formatPlate(entry.plate) }}')
        ->toContain('Tidak terkait order')
        ->toContain('entry.orderNo?.toLowerCase().includes(query)')
        ->toContain('placeholder="Cari transaksi / order / plat"');
});

test('finance overview shows shift tabs stacked summaries and financial channels', function () {
    $financePage = file_get_contents(
        resource_path('js/pages/admin/Finance.vue'),
    );

    expect($financePage)
        ->toContain("label: 'Seluruh Shift & Tanpa Shift'")
        ->toContain("const unassignedShiftKey = 'tanpa-shift'")
        ->toContain("label: 'Tanpa Shift'")
        ->toContain('v-if="shift.caption"')
        ->toContain('label="Uang masuk"')
        ->toContain('label="Uang keluar"')
        ->toContain('label="Profit / Keuntungan"')
        ->toContain('<p class="text-sm text-slate-500">Saldo</p>')
        ->toContain('<Wallet class="h-5 w-5" />')
        ->toContain('Tunai')
        ->toContain('Non-Tunai')
        ->toContain('formatCurrency(dailyBalance.cash)')
        ->toContain('formatCurrency(dailyBalance.nonCash)')
        ->toContain('{{ balanceCaption }}')
        ->toContain('@click="balanceHistoryOpen = true"')
        ->toContain('title="Riwayat Saldo Harian"')
        ->toContain('v-for="day in dailyBalanceHistory"')
        ->toContain('formatCurrency(day.cashBalance)')
        ->toContain('formatCurrency(day.nonCashBalance)')
        ->toContain('Akumulasi sampai tanggal ')
        ->toContain('formatLongDate(props.filters.date)')
        ->toContain('sm:grid-cols-2 xl:grid-cols-1')
        ->toContain('Kanal Keuangan')
        ->toContain('Pemasukan')
        ->toContain('Pengeluaran')
        ->toContain('Profit/Keuntungan Kanal')
        // Every payment channel, plus the finance-only Setor Tunai.
        ->toContain('const financialChannels = [...props.paymentMethods, CASH_DEPOSIT].map(')
        ->toContain("label: key === 'E-Money' ? 'Emoney' : key")
        // Cash keeps its own section; the rest share one merged figure.
        ->toContain("const cashChannelKey = 'Tunai';")
        ->toContain('const cashChannelRow = computed(')
        ->toContain('const nonCashChannelRows = computed(')
        ->toContain('const nonCashTotals = computed(')
        ->toContain('formatCurrency(cashChannelRow.expense)')
        ->toContain('v-for="(channel, index) in nonCashChannelRows"')
        ->toContain(':rowspan="nonCashChannelRows.length"')
        ->toContain('formatCurrency(nonCashTotals.expense)')
        ->toContain('formatCurrency(nonCashTotals.balance)')
        ->toContain('xl:grid-cols-[minmax(320px,1fr)_minmax(0,2fr)]')
        ->not->toContain('formatShortCurrency(shift.moneyIn)');
});

test('every ledger row is booked under the shift of whoever wrote it', function () {
    $entries = [...Finance::moneyIn(), ...Finance::moneyOut()];
    $shiftByStaff = array_column(RoleAccess::staff(), 'shift', 'name');

    expect($entries)->not->toBeEmpty();

    foreach ($entries as $entry) {
        expect($entry)->toHaveKey('shift')
            /* Never inferred from the hour: it is whoever wrote the row. */
            ->and($entry['shift'])->toBe($shiftByStaff[$entry['recordedBy']] ?? null);
    }
});

test('every finance entry exposes an exact channel breakdown', function () {
    $entries = [...Finance::moneyIn(), ...Finance::moneyOut()];

    foreach ($entries as $entry) {
        expect($entry['channelBreakdown'])->not->toBeEmpty()
            ->and(array_sum(array_column($entry['channelBreakdown'], 'amount')))
            ->toBe($entry['amount']);
    }

    $splitPayment = collect(Finance::moneyIn())
        ->first(fn (array $entry): bool => $entry['method'] === 'QRIS + Tunai');

    expect($splitPayment)->not->toBeNull()
        ->and($splitPayment['channelBreakdown'])->toBe([
            ['label' => 'QRIS', 'amount' => 15000],
            ['label' => 'Tunai', 'amount' => 5000],
        ]);
});

test('an expense only ever names cash or non-cash', function () {
    expect(Operations::expenseMethods())->toBe(['Tunai', 'Non-Tunai']);

    foreach (Finance::moneyOut() as $entry) {
        expect($entry['method'])->toBeIn(['Tunai', 'Non-Tunai'])
            ->and(array_column($entry['channelBreakdown'], 'label'))
            ->toBe([$entry['method']]);
    }

    $financePage = file_get_contents(
        resource_path('js/pages/admin/Finance.vue'),
    );

    expect($financePage)
        ->toContain('const activeMethods = computed<string[]>(')
        ->toContain(
            "return activeLedger.value === 'in'\n        ? props.paymentMethods\n        : props.expenseMethods;",
        )
        ->toContain('v-for="method in activeMethods"')
        ->toContain('method: activeMethods.value[0],')
        /* The merged cell takes every outgoing that did not leave the till. */
        ->toContain('const expense = totalOut.value - cashChannelRow.value.expense;');
});

test('finance transaction list shows amounts for split payment methods', function () {
    $financePage = file_get_contents(
        resource_path('js/pages/admin/Finance.vue'),
    );

    expect($financePage)
        ->toContain('v-if="entry.channelBreakdown.length > 1"')
        ->toContain('v-for="channel in entry.channelBreakdown"')
        ->toContain('{{ channel.label }}')
        ->toContain('{{ formatCurrency(channel.amount) }}')
        ->toContain('<span v-else>{{ entry.method }}</span>');
});

test('finance transaction summary keeps the requested money labels', function () {
    $financePage = file_get_contents(
        resource_path('js/pages/admin/Finance.vue'),
    );

    expect($financePage)
        ->toContain('label="Uang masuk"')
        ->toContain('label="Uang keluar"')
        ->toContain('label="Profit / Keuntungan"')
        ->not->toContain('label="Arus kas bersih"');
});

test('finance transaction summary exposes permitted editing and the recorded shift', function () {
    $financePage = file_get_contents(
        resource_path('js/pages/admin/Finance.vue'),
    );

    expect($financePage)
        ->toContain('capabilities.update &&')
        ->toContain('isEditable(selectedTransactionEntry)')
        ->toContain('@click="editSelectedTransaction"')
        ->toContain('Ubah transaksi')
        ->toContain('updateOrderTransaction(transactionId)')
        ->toContain('title="Ubah Transaksi"')
        ->toContain('v-if="requiresTransactionBank(channel.label)"')
        ->toContain('v-model="channel.provider"')
        ->toContain('<option value="" disabled>Pilih bank</option>')
        ->toContain('v-if="entry.updatedAt"')
        ->toContain('{{ entry.updatedAt.time }}')
        ->toContain("selectedTransactionEntry.shift ?? 'Tanpa shift'");
});

test('finance transaction toolbar supports selecting multiple category chips', function () {
    $financePage = file_get_contents(
        resource_path('js/pages/admin/Finance.vue'),
    );

    expect($financePage)
        ->toContain('placeholder="Cari transaksi / order / plat"')
        ->toContain('const filterOptions = computed<string[]>(() => [')
        ->toContain('new Set(activeEntries.value.map((entry) => entry.category))')
        ->toContain("const categoryFilters = ref<string[]>(['Semua'])")
        ->toContain('categoryFilters.value.includes(entry.category)')
        ->toContain('function toggleCategoryFilter(category: string): void')
        ->toContain("categoryFilters.value = ['Semua']")
        ->toContain(':filters="filterOptions"')
        ->toContain(':active-filter="categoryFilters"')
        ->toContain('wide-search')
        ->toContain('@filter="toggleCategoryFilter"');

    $toolbar = file_get_contents(
        resource_path('js/components/demo/DataToolbar.vue'),
    );

    expect($toolbar)
        ->toContain('activeFilter?: string | string[]')
        ->toContain('activeFilter.includes(filter)')
        ->toContain(':aria-pressed="isFilterActive(activeFilter, filter)"')
        ->toContain('wideSearch?: boolean')
        ->toContain("wideSearch ? 'w-full sm:w-96' : undefined");
});

test('the demo balance history accumulates day by day up to the selected date', function () {
    $history = Finance::dailyBalanceHistory(Reports::todayDate());
    $dates = array_column($history, 'date');
    $descending = $dates;
    rsort($descending);

    expect($history)->not->toBeEmpty()
        ->and($dates)->toBe($descending)
        ->and($dates[0])->toBeLessThanOrEqual(Reports::todayDate())
        ->and(Finance::dailyBalance(Reports::todayDate()))->toMatchArray([
            'cash' => $history[0]['cashBalance'],
            'nonCash' => $history[0]['nonCashBalance'],
        ])
        // The recap prints where the day opened as well as where it closed.
        ->and(Finance::dailyBalance(Reports::todayDate())['previous'])->toBe([
            'date' => $history[1]['date'],
            'cash' => $history[1]['cashBalance'],
            'nonCash' => $history[1]['nonCashBalance'],
        ]);

    $oldest = $history[count($history) - 1];

    expect($oldest['cashBalance'])
        ->toBe($oldest['cashIncome'] - $oldest['cashExpense'])
        ->and($oldest['nonCashBalance'])
        ->toBe($oldest['nonCashIncome'] - $oldest['nonCashExpense']);

    /* An earlier date never carries a later day's movement. */
    expect(Finance::dailyBalanceHistory($oldest['date']))->toBe([$oldest]);
});
