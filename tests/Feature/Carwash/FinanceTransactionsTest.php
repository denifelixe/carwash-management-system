<?php

use App\Support\Carwash\DateFilter;
use App\Support\Carwash\Finance;
use App\Support\Carwash\Operations;
use App\Support\Carwash\Reports;
use App\Support\Carwash\RoleAccess;
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
                    ? 'Pembayaran Sebagian Order'
                    : 'Pembayaran Lunas Order',
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
        ->toContain('Pembayaran Sebagian Order', 'Pembayaran Lunas Order')
        ->not->toContain(
            'Pembayaran Sebagian Booking',
            'Pelunasan Order',
            'Penjualan Layanan',
        );
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
        ->firstWhere('category', 'Pembayaran Sebagian Order');
    $finalPayment = collect(Finance::moneyIn())
        ->firstWhere('category', 'Pembayaran Lunas Order');
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
        resource_path('js/pages/carwash/admin/Finance.vue'),
    );

    expect($financePage)
        ->toContain('function transactionReference(')
        ->toContain('`TRX-${categoryCode}-${formatDateCode(date)}-${stableIdentifier}`')
        ->toContain('ref: transactionReference(');
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
            'closingBalance' => 12400000 + $todayIn - $todayOut,
        ]);
});

test('dashboard shift figures use served orders and the finance ledger for the selected day', function () {
    $today = Reports::todayDate();
    $shifts = collect(Finance::shiftSummary($today))->keyBy('id');
    $todayIncome = DateFilter::apply(Finance::moneyIn(), $today);
    $todayExpenses = DateFilter::apply(Finance::moneyOut(), $today);
    $servedOrders = Operations::servedOrders($today);

    foreach (['pagi', 'sore'] as $shiftId) {
        $isMorning = $shiftId === 'pagi';
        $income = collect($todayIncome)
            ->filter(fn (array $entry): bool => ($entry['time'] < '15.00') === $isMorning);
        $expenses = collect($todayExpenses)
            ->filter(fn (array $entry): bool => ($entry['time'] < '15.00') === $isMorning);
        $posIncome = $income->where('source', 'pos');
        $shiftOrders = collect($servedOrders)
            ->filter(fn (array $order): bool => ($order['time'] < '15.00') === $isMorning);

        expect($shifts[$shiftId])
            ->toMatchArray([
                'revenue' => $posIncome->sum('amount'),
                'vehiclesServed' => $shiftOrders->count(),
                'moneyIn' => $income->sum('amount'),
                'moneyOut' => $expenses->sum('amount'),
            ]);
    }

    expect($shifts['pagi']['vehiclesServed'])->toBe(8)
        ->and($shifts['sore']['vehiclesServed'])->toBe(0)
        ->and($shifts->sum('vehiclesServed'))->toBe(8);
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
        ->get(route('carwash.admin.finance'))
        ->assertOk()
        ->assertInertia(
            fn (AssertableInertia $page) => $page
                ->component('carwash/admin/Finance')
                ->has('moneyIn', count($todayEntries))
                ->where('moneyIn.0.ref', $todayEntries[0]['ref'])
                ->where(
                    'moneyIn.'.array_search($posEntry, $todayEntries, true).'.orderNo',
                    $posEntry['orderNo'],
                )
        );

    $financePage = file_get_contents(
        resource_path('js/pages/carwash/admin/Finance.vue'),
    );

    expect($financePage)
        ->toContain('Order terkait')
        ->toContain('v-if="entry.orderNo"')
        ->toContain('{{ entry.orderNo }}')
        ->toContain('{{ entry.customer }}')
        ->toContain('{{ entry.vehicle }} · {{ entry.plate }}')
        ->toContain('Tidak terkait order')
        ->toContain('entry.orderNo?.toLowerCase().includes(query)')
        ->toContain('placeholder="Cari transaksi / order / plat"');
});

test('finance overview shows shift tabs stacked summaries and financial channels', function () {
    $financePage = file_get_contents(
        resource_path('js/pages/carwash/admin/Finance.vue'),
    );

    expect($financePage)
        ->toContain("label: 'Seluruh Shift'")
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
        resource_path('js/pages/carwash/admin/Finance.vue'),
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
        resource_path('js/pages/carwash/admin/Finance.vue'),
    );

    expect($financePage)
        ->toContain('label="Uang masuk"')
        ->toContain('label="Uang keluar"')
        ->toContain('label="Sisa saldo"')
        ->not->toContain('label="Arus kas bersih"');
});

test('finance transaction toolbar shows a wide search and category chips', function () {
    $financePage = file_get_contents(
        resource_path('js/pages/carwash/admin/Finance.vue'),
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
        resource_path('js/components/carwash/DataToolbar.vue'),
    );

    expect($toolbar)
        ->toContain('wideSearch?: boolean')
        ->toContain("wideSearch ? 'w-full sm:w-96' : undefined");
});
