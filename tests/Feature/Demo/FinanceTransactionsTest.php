<?php

use App\Support\Demo\DateFilter;
use App\Support\Demo\Finance;
use App\Support\Demo\Operations;
use App\Support\Demo\Reports;
use App\Support\Demo\RoleAccess;
use Inertia\Testing\AssertableInertia;

test('POS income records every received payment on its transaction date', function () {
    $posEntries = array_values(array_filter(
        Finance::moneyIn(),
        fn (array $entry): bool => $entry['source'] === 'pos',
    ));
    $expectedTransactions = [];

    foreach (Operations::orders() as $order) {
        foreach ($order['transactions'] as $transactionIndex => $transaction) {
            if ($transaction['amount'] > 0) {
                $expectedTransactions['pos-'.$transaction['id']] = [
                    $order,
                    $transaction,
                    $transactionIndex + 1,
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
        [$order, $transaction, $transactionNumber] = $expectedTransactions[$entry['id']];
        $categoryCode = $transaction['type'] === 'Pembayaran Sebagian'
            ? 'PSO'
            : 'PLO';
        $dateCode = str_replace('-', '', substr($transaction['date'], 2));
        $orderNumber = preg_replace('/[^A-Z0-9]+/', '', $order['orderNo']);

        expect($entry)
            ->toMatchArray([
                'ref' => "TRX-{$categoryCode}-{$dateCode}-{$orderNumber}TRX{$transactionNumber}",
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

test('finance references use one category date and identifier format', function () {
    $entries = [...Finance::moneyIn(), ...Finance::moneyOut()];

    foreach ($entries as $entry) {
        expect(preg_match(
            '/^TRX-[A-Z0-9]+-\d{6}-[A-Z0-9]+$/',
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

    expect($partialPayment['ref'])->toStartWith('TRX-PSO-')
        ->and($finalPayment['ref'])->toStartWith('TRX-PLO-')
        ->and($productSale['ref'])->toStartWith('TRX-PP-')
        ->and($materialPurchase['ref'])->toStartWith('TRX-PB-')
        ->and(implode(' ', array_column(Finance::moneyIn(), 'ref')))
        ->not->toContain('ZW');

    $installmentReferences = collect(Finance::moneyIn())
        ->where('orderNo', 'ORD-BK-'.Reports::today()->format('ymd').'01')
        ->pluck('ref')
        ->sort()
        ->values()
        ->all();

    expect($installmentReferences)->toBe([
        'TRX-PSO-'.Reports::today()->subDay()->format('ymd').'-ORDBK'.Reports::today()->format('ymd').'01TRX1',
        'TRX-PSO-'.Reports::today()->format('ymd').'-ORDBK'.Reports::today()->format('ymd').'01TRX2',
        'TRX-PSO-'.Reports::today()->format('ymd').'-ORDBK'.Reports::today()->format('ymd').'01TRX3',
    ]);

    $financePage = file_get_contents(
        resource_path('js/pages/admin/Finance.vue'),
    );

    expect($financePage)
        ->toContain('function transactionReference(')
        ->toContain('`TRX-${categoryCode}-${formatDateCode(date)}-${stableIdentifier}`')
        ->toContain('ref: transactionReference(')
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

test('closing a finance attachment keeps the current ledger position', function () {
    $financePage = file_get_contents(
        resource_path('js/pages/admin/Finance.vue'),
    );

    expect($financePage)
        ->toContain('Fancybox.bind(`[data-fancybox="${LIGHTBOX_GROUP}"]`, {')
        ->toContain('Hash: false,');
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

    $this->withSession([RoleAccess::SESSION_KEY => 'finance'])
        ->get(route('demo.admin.finance'))
        ->assertOk()
        ->assertInertia(
            fn (AssertableInertia $page) => $page
                ->component('admin/Finance')
                ->has('moneyIn', count($todayEntries))
                ->has('orders', count(Operations::orders()))
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
        ->toContain('title="Rekap Transaksi"')
        ->toContain('title="Rekap Order"')
        ->toContain('Riwayat transaksi')
        ->toContain('highlightedTransactionId')
        ->toContain('before:inset-y-2 before:left-0 before:w-1')
        ->not->toContain('bg-cyan-50 ring-2 ring-cyan-300 ring-inset')
        ->toContain('{{ entry.vehicle }} · {{ entry.plate }}')
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
        ->toContain('label="Sisa saldo"')
        ->toContain('Kanal Keuangan')
        ->toContain('Pemasukan')
        ->toContain('Pengeluaran')
        ->toContain('Saldo Kanal')
        ->toContain('const financialChannels = props.paymentMethods.map')
        ->toContain("label: key === 'E-Money' ? 'Emoney' : key")
        ->toContain('xl:grid-cols-[minmax(260px,0.75fr)_minmax(0,2.25fr)]')
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
        ->toContain('label="Sisa saldo"')
        ->not->toContain('label="Arus kas bersih"');
});

test('finance transaction toolbar shows a wide search and category chips', function () {
    $financePage = file_get_contents(
        resource_path('js/pages/admin/Finance.vue'),
    );

    expect($financePage)
        ->toContain('placeholder="Cari transaksi / order / plat"')
        ->toContain('const filterOptions = computed<string[]>(() => [')
        ->toContain('new Set(activeEntries.value.map((entry) => entry.category))')
        ->toContain("const categoryFilter = ref<string>('Semua')")
        ->toContain(':filters="filterOptions"')
        ->toContain(':active-filter="categoryFilter"')
        ->toContain('wide-search')
        ->toContain('@filter="categoryFilter = $event"');

    $toolbar = file_get_contents(
        resource_path('js/components/demo/DataToolbar.vue'),
    );

    expect($toolbar)
        ->toContain('wideSearch?: boolean')
        ->toContain("wideSearch ? 'w-full sm:w-96' : undefined");
});
