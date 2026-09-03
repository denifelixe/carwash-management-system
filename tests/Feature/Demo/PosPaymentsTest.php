<?php

use App\Support\Demo\Brand;
use App\Support\Demo\Catalog;
use App\Support\Demo\DateFilter;
use App\Support\Demo\Finance;
use App\Support\Demo\Operations;
use App\Support\Demo\Reports;
use App\Support\Demo\RoleAccess;
use Inertia\Testing\AssertableInertia;

/**
 * The cashier settles orders that already exist, so every order has to carry a
 * `paidAmount` the POS can subtract from (BR-06).
 */
test('the cashier lands on the POS with the order list attached', function () {
    $this->withSession([RoleAccess::SESSION_KEY => 'cashier'])
        ->get(route('demo.admin.pos'))
        ->assertOk()
        ->assertInertia(
            fn (AssertableInertia $page) => $page
                ->component('admin/Pos')
                ->where('role.key', 'cashier')
                // The cashier opens on today's transactions.
                ->has('orders', count(DateFilter::apply(Operations::settlementOrders(), Reports::todayDate())))
                ->has('orders.0.paidAmount')
                ->has('orders.0.paymentStatus')
                ->has('orders.0.transactions')
                ->has('dailyOrders', count(DateFilter::apply(Operations::orders(), Reports::todayDate())))
                ->has('partialPaymentBookings', count(Operations::partialPaymentBookingOrders()))
        );
});

test('the POS recap uses received payments from the selected transaction date', function () {
    $dailyOrders = DateFilter::apply(
        Operations::orders(),
        Reports::todayDate(),
    );
    $receivedTransactions = array_values(array_filter(
        array_merge(...array_map(
            fn (array $order): array => $order['transactions'],
            Operations::orders(),
        )),
        fn (array $transaction): bool => $transaction['date'] === Reports::todayDate()
            && $transaction['amount'] > 0,
    ));
    $partialPaymentTransactions = array_filter(
        $receivedTransactions,
        fn (array $transaction): bool => $transaction['type'] === 'Pembayaran Sebagian',
    );
    $finalPaymentTransactions = array_filter(
        $receivedTransactions,
        fn (array $transaction): bool => $transaction['type'] === 'Pembayaran Lunas',
    );
    $receivedPaymentTotal = array_sum(array_column(
        $receivedTransactions,
        'amount',
    ));

    $channelRecap = [];

    foreach ($receivedTransactions as $transaction) {
        foreach ($transaction['channelBreakdown'] as $channel) {
            $channelRecap[$channel['label']] ??= ['count' => 0, 'amount' => 0];
            $channelRecap[$channel['label']]['count']++;
            $channelRecap[$channel['label']]['amount'] += $channel['amount'];
        }
    }

    $response = $this->withSession([RoleAccess::SESSION_KEY => 'cashier'])
        ->get(route('demo.admin.pos'))
        ->assertOk();

    expect($response->inertiaProps('dailyOrders'))->toBe($dailyOrders)
        ->and($receivedTransactions)->toHaveCount(7)
        ->and($partialPaymentTransactions)->toHaveCount(4)
        ->and($finalPaymentTransactions)->toHaveCount(3)
        ->and($receivedPaymentTotal)->toBe(1860000)
        ->and($channelRecap['Transfer'])->toBe(['count' => 1, 'amount' => 1500000])
        ->and($channelRecap['QRIS'])->toBe(['count' => 2, 'amount' => 60000])
        ->and($channelRecap['Debit'])->toBe(['count' => 1, 'amount' => 150000])
        ->and($channelRecap['Tunai'])->toBe(['count' => 4, 'amount' => 150000])
        ->and(array_sum(array_column($channelRecap, 'amount')))
        ->toBe($receivedPaymentTotal);

    $posPage = file_get_contents(
        resource_path('js/pages/admin/Pos.vue'),
    );

    expect($posPage)
        ->toContain('transaction.date === props.filters.date')
        ->toContain('transaction.amount > 0')
        ->toContain('const paymentRecapPartialTransactions = computed')
        ->toContain('const paymentRecapFinalTransactions = computed')
        ->toContain("transaction.type === 'Pembayaran Sebagian'")
        ->toContain("transaction.type === 'Pembayaran Lunas'")
        ->toContain('paymentRecapFinalTotal.value + paymentRecapPartialTotal.value')
        ->toContain('paymentRecapTransactionCount')
        ->toContain('for (const channel of transaction.channelBreakdown)')
        ->toContain('row.amount += channel.amount');
});

test('the cashier booking list only contains todays and upcoming bookings that have not arrived', function () {
    $bookings = Operations::partialPaymentBookingOrders();
    $scheduledBookingNumbers = array_column(array_filter(
        Operations::scheduledBookings(),
        fn (array $booking): bool => $booking['date'] >= Reports::todayDate()
            && $booking['orderStatus'] === 'booking',
    ), 'code');
    $cashierBookingNumbers = array_column($bookings, 'orderNo');
    $todayBookings = array_filter(
        $bookings,
        fn (array $order): bool => $order['date'] === Reports::todayDate(),
    );
    $upcomingBookings = array_filter(
        $bookings,
        fn (array $order): bool => $order['date'] > Reports::todayDate(),
    );

    sort($scheduledBookingNumbers);
    sort($cashierBookingNumbers);

    expect($bookings)->not->toBeEmpty()
        ->and($todayBookings)->toHaveCount(3)
        ->and($upcomingBookings)->toHaveCount(3)
        ->and($cashierBookingNumbers)->toBe($scheduledBookingNumbers);

    foreach ($bookings as $booking) {
        expect($booking['source'])->toBe('booking')
            ->and($booking['date'])->toBeGreaterThanOrEqual(Reports::todayDate());
    }
});

test('a booking that has reached settlement leaves the not-arrived booking list', function () {
    $settlementBookingNumbers = array_column(array_filter(
        Operations::settlementOrders(),
        fn (array $order): bool => $order['source'] === 'booking',
    ), 'orderNo');
    $partialPaymentBookingNumbers = array_column(
        Operations::partialPaymentBookingOrders(),
        'orderNo',
    );

    $budiOrderNumber = 'ORD-BK-'.Reports::today()->format('ymd').'01';

    expect($settlementBookingNumbers)->toContain($budiOrderNumber)
        ->and($partialPaymentBookingNumbers)->not->toContain($budiOrderNumber)
        ->and(array_intersect($settlementBookingNumbers, $partialPaymentBookingNumbers))
        ->toBe([]);
});

test('the partial payment section sits below settlement and opens the same payment flow', function () {
    $posPage = file_get_contents(
        resource_path('js/pages/admin/Pos.vue'),
    );

    expect($posPage)
        ->toContain('visiblePartialPaymentBookings')
        ->toContain("selectOrder(order, 'partial')")
        ->toContain('const isScheduledBooking = partialPaymentBookingIds.has(order.id)')
        ->toContain('partialPaymentTransactions(order).length > 0')
        ->toContain('Pembayaran Sebagian/Booking sebesar')
        ->not->toContain('Pembayaran sudah lunas.')
        // The header splits the queue into cars due today and later ones.
        ->toContain('Booking (${today} Hari Ini - ${bookings.length - today} Mendatang)')
        // Both lists are accordion panels now; the tones live in the component.
        ->toContain('title="Pelunasan"')
        ->toContain('title="Pembayaran Sebagian/Booking"')
        ->and(mb_strpos($posPage, 'title="Pelunasan"'))
        ->toBeLessThan(
            mb_strpos($posPage, 'title="Pembayaran Sebagian/Booking"'),
        );

    expect(file_get_contents(
        resource_path('js/components/demo/AccordionSection.vue'),
    ))
        ->toContain('border-violet-200/80 bg-violet-50/30')
        ->toContain('border-orange-200/80 bg-amber-50/40');
});

test('cashier bookings use today and upcoming schedule statuses', function () {
    $posPage = file_get_contents(
        resource_path('js/pages/admin/Pos.vue'),
    );
    $statusPill = file_get_contents(
        resource_path('js/components/demo/StatusPill.vue'),
    );

    expect($posPage)
        ->toContain("return order.date > props.filters.today ? 'Booking Mendatang' : 'booking';")
        ->toContain(':status="bookingDisplayStatus(order)"');

    expect($statusPill)
        ->toContain("case 'booking':")
        ->toContain("case 'Booking Mendatang':")
        ->toContain('bg-cyan-50 text-cyan-700 ring-1 ring-cyan-200')
        ->toContain('bg-amber-50 text-amber-700 ring-1 ring-amber-200');
});

test('cashier bookings are ordered from today to upcoming dates', function () {
    $posPage = file_get_contents(
        resource_path('js/pages/admin/Pos.vue'),
    );

    expect($posPage)
        ->toContain('first.date.localeCompare(second.date)')
        ->toContain('first.orderNo.localeCompare(second.orderNo)');
});

test('order cards lead with the plate and classify the customer source', function () {
    $posPage = file_get_contents(
        resource_path('js/pages/admin/Pos.vue'),
    );

    expect($posPage)
        ->toContain("return 'Booking'")
        ->toContain("'Walk-in non-customer'")
        ->toContain("'Walk-in customer'")
        ->toContain('{{ formatPlate(order.plate) }}')
        ->toContain('{{ order.vehicle }}')
        ->toContain('{{ orderTypeLabel(order) }}')
        ->and(substr_count($posPage, 'No. order {{ order.orderNo }}'))
        ->toBe(2)
        // Settlement, booking, and the settled list all lead with the plate.
        ->and(substr_count($posPage, 'mt-1 text-xl font-bold tracking-wide text-slate-950'))
        ->toBe(3);
});

test('the cashier summary only shows settlement balance and received payments', function () {
    $posPage = file_get_contents(
        resource_path('js/pages/admin/Pos.vue'),
    );

    expect($posPage)
        ->toContain('<div class="grid grid-cols-1 gap-4 sm:grid-cols-2">')
        ->toContain('label="Order Berjalan"')
        ->toContain('label="Pembayaran diterima"')
        ->not->toContain('label="Order menggantung"')
        ->not->toContain('depositCount')
        ->not->toContain('CollapsibleSummary')
        ->not->toContain('Kasir hari ini')
        ->not->toContain('summaryCaption');
});

test('the demo POS ships the shift tabs and books every payment under one', function () {
    $this->withSession([RoleAccess::SESSION_KEY => 'cashier'])
        ->get(route('demo.admin.pos'))
        ->assertOk()
        ->assertInertia(
            fn (AssertableInertia $page) => $page
                ->component('admin/Pos')
                ->has('shifts', count(Brand::shifts()))
                ->where('shifts.0.key', 'pagi')
                ->where('shifts.0.name', 'Shift Pagi')
                ->where('shifts.0.time', '07.00 - 15.00')
                ->where('shifts.1.name', 'Shift Sore')
        );

    /*
     * The recap buckets on the stamped name alone, so a fixture payment left
     * without one would drop out of every shift tab and into 'Tanpa Shift'.
     */
    $transactions = array_merge(
        ...array_column(Operations::orders(), 'transactions'),
    );
    $shiftNames = array_column(Brand::shifts(), 'name');

    expect($transactions)->not->toBeEmpty();

    foreach ($transactions as $transaction) {
        expect($transaction)->toHaveKey('shift')
            ->and($transaction['shift'])->toBeIn($shiftNames);
    }
});

test('the received payment card opens a payment recap', function () {
    $posPage = file_get_contents(
        resource_path('js/pages/admin/Pos.vue'),
    );

    expect($posPage)
        ->toContain(':active="isPaymentRecapOpen"')
        ->toContain('@click="isPaymentRecapOpen = true"')
        ->toContain('title="Rekap Pembayaran Diterima"')
        ->toContain('paymentRecapTransactions')
        ->toContain('paymentRecapByType')
        ->toContain('paymentRecapByChannel')
        ->toContain('paymentRecapFinalOrderCount')
        ->toContain('transaction.date === props.filters.date')
        ->toContain('sm:grid-cols-2')
        ->toContain('Jumlah transaksi')
        ->toContain('Pembayaran Sebagian/Booking')
        ->toContain('Pembayaran Sisa/Lunas (Order Selesai)')
        ->toContain('Jenis transaksi')
        ->toContain("const partialPaymentRecapLabel = 'Pembayaran Sebagian/Booking'")
        ->toContain("const finalPaymentRecapLabel = 'Pembayaran Sisa/Lunas (Order Selesai)'")
        ->toContain('text-xs leading-snug whitespace-normal text-slate-500')
        ->toContain('label: partialPaymentRecapLabel')
        ->toContain('label: finalPaymentRecapLabel')
        ->toContain('Kanal pembayaran')
        ->toContain("const paymentRecapTotalKey = 'total'")
        ->toContain("const paymentRecapUnassignedKey = 'tanpa-shift'")
        ->toContain("const unassignedShiftLabel = 'Tanpa Shift'")
        ->toContain('...props.shifts.map((shift) => ({')
        ->toContain('key: paymentRecapUnassignedKey,')
        ->toContain('label: unassignedShiftLabel,')
        ->toContain('{{ tab.count }} transaksi')
        ->toContain('role="tablist"')
        ->toContain(':aria-selected="activePaymentRecapShift === tab.key"')
        ->toContain('selectPaymentRecapShift(tab.key)')
        ->toContain('movePaymentRecapShift(-1)')
        ->toContain('const paymentRecapDetailsElement = ref<HTMLElement | null>(null)')
        ->toContain('await nextTick()')
        ->toContain('paymentRecapDetailsElement.value?.scrollIntoView({')
        ->toContain("behavior: 'smooth'")
        ->toContain("block: 'start'")
        ->toContain('ref="paymentRecapDetailsElement"')
        ->toContain('movePaymentRecapShift(1)')
        ->toContain(
            '!props.shifts.some((option) => option.name === shiftName)',
        )
        ->toContain(
            'props.shifts.find((option) => option.key === shift)?.name === shiftName',
        )
        /* The clock no longer stands in for a shift the payment never had. */
        ->not->toContain('transactionMinutes')
        ->not->toContain("transaction.time >= '15.00' ? 'Shift Sore'")
        ->toContain('activePaymentRecapTransactions')
        ->toContain('formatCurrency(activePaymentRecapTotal)')
        ->not->toContain('paymentRecapDiscountTotal')
        ->not->toContain('Order terbayar')
        ->not->toContain('Total potongan');
});

test('payment recap rows reveal their transaction and order details', function () {
    $posPage = file_get_contents(
        resource_path('js/pages/admin/Pos.vue'),
    );

    preg_match(
        '/Tanggal order(?<orderDateField>.*?)<\/dd>/s',
        $posPage,
        $orderDateMatch,
    );

    expect($orderDateMatch['orderDateField'] ?? '')
        ->toContain('formatDate(')
        ->toContain('detail.order.date')
        ->not->toContain('detail.order.time');

    expect(strpos($posPage, 'Tanggal booking'))
        ->toBeLessThan(strpos($posPage, 'Tanggal order'));

    expect($posPage)
        ->toContain("selectPaymentRecap('all', 'Semua pembayaran')")
        ->toContain("selectPaymentRecap('type', row.label)")
        ->toContain("selectPaymentRecap('channel', row.label)")
        ->toContain("selection.category === 'all'")
        ->toContain('isPaymentRecapSelected(')
        ->toContain('const paymentRecapDetails = computed<PaymentRecapDetail[]>')
        ->toContain('transaction.channelBreakdown.find(')
        ->toContain('candidate.id === transaction.orderId')
        ->toContain('Detail transaksi &amp; order')
        ->toContain('{{ detail.order.orderNo }}')
        ->toContain('{{ detail.order.customer }}')
        ->toContain('showPaymentRecapOrder(')
        ->toContain('showPaymentRecapTransaction(')
        ->toContain('showSelectedPaymentRecapTransactionOrder(')
        ->toContain('selectedPaymentRecapTransaction')
        ->toContain('paymentTransactionReference(')
        ->toContain('`TRX-${categoryCode}-${dateCode}-${stableIdentifier}`')
        ->toContain('paymentTransactionReference(detail.transaction, detail.order)')
        ->not->toContain('{{ detail.transaction.id }}')
        ->toContain('md:grid-cols-[minmax(0,1fr)_minmax(0,1.2fr)_8rem]')
        ->toContain('md:items-start')
        ->toContain('class="min-w-0"')
        ->toContain('Lihat detail order ${detail.order.orderNo} dan transaksinya')
        ->toContain('selectedPaymentRecapOrder')
        ->toContain('sourceTransactionId: detail.transaction.id')
        ->toContain('highlightedTransactionId: null')
        ->toContain('highlightedTransactionId: detail.transaction.id')
        ->toContain('selectedPaymentRecapOrder?.highlightedTransactionId')
        ->toContain('title="Rekap Transaksi"')
        ->toContain('Nomor transaksi')
        ->toContain('Dicatat oleh')
        ->toContain('paymentTransactionRecorder(')
        ->toContain('Order terkait')
        ->toContain("? 'top'")
        ->toContain(": 'nested'")
        ->toContain('title="Detail Order"')
        ->toContain('layer="nested"')
        ->toContain('Detail order')
        ->toContain('Tanggal booking')
        ->toContain('detail.order.bookingDate')
        ->toContain("? 'sm:grid-cols-5'")
        ->toContain('Riwayat transaksi')
        ->toContain('v-for="transaction in detail')
        ->toContain('.order.transactions"')
        ->toContain('Transaksi dipilih')
        ->toContain('Kembali ke rekap')
        ->toContain("'border-cyan-300 bg-cyan-100 ring-2 ring-cyan-400/30'")
        ->toContain('paymentTransactionLabel(')
        ->toContain('transactionChannelsLabel(')
        ->toContain('{{ formatCurrency(detail.amount) }}')
        ->toContain(':aria-pressed=')
        ->toContain('Pilih jenis transaksi atau kanal pembayaran');

    expect(substr_count(
        $posPage,
        'highlightedTransactionId: detail.transaction.id',
    ))->toBe(1);

    $modalDialog = file_get_contents(
        resource_path('js/components/demo/ModalDialog.vue'),
    );

    expect($modalDialog)
        ->toContain("layer?: 'default' | 'nested' | 'top';")
        ->toContain("top: 'z-[70]'")
        ->toContain("layers[layer ?? 'default']")
        ->toContain('let openModalCount = 0;')
        ->toContain('onMounted(() => {')
        ->toContain('watch(() => props.open, syncPageScrollLock, { immediate: true })');
});

test('search fields remain visible against tinted section backgrounds', function () {
    $toolbar = file_get_contents(
        resource_path('js/components/demo/DataToolbar.vue'),
    );

    expect($toolbar)
        ->toContain('border border-slate-300 bg-white')
        ->toContain('shadow-sm')
        ->toContain('focus-within:border-cyan-500')
        ->toContain('focus-within:ring-cyan-100')
        ->toContain('placeholder:text-slate-500');
});

test('the cashier only receives orders in the pelunasan stage', function () {
    $orders = Operations::settlementOrders();

    expect($orders)->not->toBeEmpty()
        ->and(array_unique(array_column($orders, 'status')))->toBe(['pelunasan'])
        ->and(array_column($orders, 'paymentStatus'))->toContain('sebagian');
});

test('the POS shows one lifecycle chip and describes an existing partial payment', function () {
    $posPage = file_get_contents(
        resource_path('js/pages/admin/Pos.vue'),
    );

    /* The heading now belongs to the accordion panel wrapping the list. */
    expect(preg_match(
        '/<AccordionSection\s+title="Pelunasan"/',
        $posPage,
    ))->toBe(1);

    expect($posPage)
        ->toContain(':status="order.status"')
        ->toContain('label="Pelunasan"')
        ->not->toContain('<StatusPill :status="order.paymentStatus" />')
        ->toContain('Pembayaran Sisa/Lunas (Order Selesai)')
        ->not->toContain('Order untuk dibayar')
        ->toContain('partialPaymentTransactions(order)')
        ->toContain('Pembayaran Sebagian/Booking sebesar')
        ->toContain('formatCurrency(transaction.amount)')
        ->toContain("order.status = 'selesai'");

    expect(file_get_contents(resource_path('js/components/demo/StatusPill.vue')))
        ->toContain('label?: string;')
        ->toContain('props.label ?? statusLabels[props.status] ?? props.status')
        ->toContain("pelunasan: 'Pelunasan'");
});

test('each transaction belongs to its order', function () {
    foreach (Operations::orders() as $order) {
        foreach ($order['transactions'] as $transaction) {
            expect($transaction['orderId'])->toBe($order['id'])
                ->and($transaction['channelBreakdown'])->not->toBeEmpty()
                ->and(array_sum(array_column($transaction['channelBreakdown'], 'amount')))
                ->toBe($transaction['amount']);
        }
    }
});

test('partial payments keep their dates and one order may have multiple installments', function () {
    $ordersWithMultiplePartialPayments = array_filter(
        Operations::orders(),
        fn (array $order): bool => count(array_filter(
            $order['transactions'],
            fn (array $transaction): bool => $transaction['type'] === 'Pembayaran Sebagian',
        )) > 1,
    );

    expect($ordersWithMultiplePartialPayments)->not->toBeEmpty();

    foreach ($ordersWithMultiplePartialPayments as $order) {
        $partialPayments = array_filter(
            $order['transactions'],
            fn (array $transaction): bool => $transaction['type'] === 'Pembayaran Sebagian',
        );

        expect(array_sum(array_column($partialPayments, 'amount')))
            ->toBe($order['paidAmount']);

        foreach ($partialPayments as $partialPayment) {
            expect($partialPayment['date'])->not->toBeEmpty();
        }
    }

    $demoOrder = array_values(array_filter(
        $ordersWithMultiplePartialPayments,
        fn (array $order): bool => $order['customer'] === 'Budi Santoso',
    ))[0];
    $demoPartialPayments = array_filter(
        $demoOrder['transactions'],
        fn (array $transaction): bool => $transaction['type'] === 'Pembayaran Sebagian',
    );

    expect($demoOrder)
        ->toMatchArray([
            'source' => 'booking',
            'bookingDate' => Reports::today()->subDay()->toDateString(),
            'status' => 'pelunasan',
            'paidAmount' => 55000,
            'paymentStatus' => 'sebagian',
        ]);
    $todayPartialPayments = array_values(array_filter(
        $demoPartialPayments,
        fn (array $partialPayment): bool => $partialPayment['date'] === Reports::todayDate(),
    ));

    expect(array_column($demoPartialPayments, 'date'))
        ->toContain(Reports::todayDate())
        ->toContain(Reports::today()->subDay()->toDateString());

    expect($todayPartialPayments)
        ->toHaveCount(2)
        ->and($todayPartialPayments[0]['amount'])->toBe(20000)
        ->and($todayPartialPayments[0]['channels'])
        ->toBe('QRIS + Tunai')
        ->and($todayPartialPayments[0]['channelBreakdown'])
        ->toBe([
            ['label' => 'QRIS', 'amount' => 15000],
            ['label' => 'Tunai', 'amount' => 5000],
        ])
        ->and($todayPartialPayments[1]['time'])->toBe('10.30')
        ->and($todayPartialPayments[1]['amount'])->toBe(15000)
        ->and($todayPartialPayments[1]['channels'])->toBe('Tunai')
        ->and($todayPartialPayments[1]['channelBreakdown'])
        ->toBe([
            ['label' => 'Tunai', 'amount' => 15000],
        ]);

    $posPage = file_get_contents(
        resource_path('js/pages/admin/Pos.vue'),
    );

    expect($posPage)
        ->toContain('partialPaymentTransactions(order)')
        ->toContain('formatDate(transaction.date)')
        ->toContain(':key="transaction.id"');
});

test('payments only use partial and fully paid transaction types', function () {
    foreach (Operations::orders() as $order) {
        foreach ($order['transactions'] as $transaction) {
            expect($transaction['type'])->toBeIn([
                'Pembayaran Sebagian',
                'Pembayaran Lunas',
            ]);
        }
    }

    $posPage = file_get_contents(
        resource_path('js/pages/admin/Pos.vue'),
    );
    $types = file_get_contents(resource_path('js/types/demo.ts'));

    expect($posPage)
        ->toContain("type: completesOrder ? 'Pembayaran Lunas' : 'Pembayaran Sebagian'")
        ->not->toContain("'DP'")
        ->not->toContain('DP diterima');

    expect($types)
        ->toContain("type: 'Pembayaran Sebagian' | 'Pembayaran Lunas';")
        ->toContain('channelBreakdown: CarwashTransactionChannel[];')
        ->not->toContain("'DP'");
});

test('waiting and in-progress orders remain unpaid without transactions', function () {
    $activeOrders = array_filter(
        Operations::orders(),
        fn (array $order): bool => in_array($order['status'], ['menunggu', 'proses'], true),
    );

    expect($activeOrders)->not->toBeEmpty();

    foreach ($activeOrders as $order) {
        expect($order)
            ->toMatchArray([
                'invoice' => '—',
                'paidAmount' => 0,
                'payment' => '—',
                'paymentStatus' => 'belum bayar',
                'transactions' => [],
            ]);
    }

    $posPage = file_get_contents(
        resource_path('js/pages/admin/Pos.vue'),
    );

    expect($posPage)
        ->toContain("isFullyPaid && snapshot.intent === 'settlement'")
        ->toContain("type: completesOrder ? 'Pembayaran Lunas' : 'Pembayaran Sebagian'")
        ->toContain('isSettled: completesOrder')
        ->not->toContain('stampsEarned: completesOrder ? order.stampsEarned : 0');
});

test('the cashier opens the payment workflow in a modal', function () {
    $posPage = file_get_contents(
        resource_path('js/pages/admin/Pos.vue'),
    );

    expect($posPage)
        ->toContain(':open="selectedOrder !== null"')
        ->toContain('title="Pembayaran"')
        ->toContain('size="xl"')
        ->toContain('@close="resetPanel"')
        ->not->toContain('xl:grid-cols-[1fr_380px]');
});

test('the payment modal follows the order transaction payment sequence', function () {
    $posPage = file_get_contents(
        resource_path('js/pages/admin/Pos.vue'),
    );

    expect($posPage)
        ->toContain('Riwayat transaksi')
        ->toContain('selectedOrder.transactions')
        ->toContain('v-model="discountAmount"')
        ->toContain('v-model="paymentTotalInput"')
        ->toContain(':max-value="amountAfterDiscount"')
        ->toContain('markPaymentTotalEdited')
        ->toContain('paymentAmounts[row.method]')
        ->toContain('paymentProviders[row.method]')
        ->toContain('<MoneyInput')
        ->toContain('placeholder="0"')
        ->toContain('paymentChannelRows')
        ->toContain('Pilih kanal pembayaran')
        ->toContain('@click="addPaymentChannel"')
        ->toContain('Tambah pembayaran')
        ->toContain('removePaymentChannel(row.id)')
        ->toContain('Total Sisa Pembayaran')
        ->toContain("paymentIntent.value === 'partial'")
        ->toContain("? 'Pembayaran Sebagian/Booking'")
        ->toContain(": 'Pembayaran Sebagian/Lunas'")
        ->toContain('{{ paymentAmountLabel }}')
        ->toContain('paymentHistoryTypeLabel(')
        ->toContain("transaction.type === 'Pembayaran Sebagian'")
        ->toContain(': transaction.type;')
        ->toContain('bg-amber-100/80')
        ->toContain('text-3xl font-bold')
        ->toContain('Sisa Tagihan setelah pembayaran ini:')
        ->toContain('Kanal Pembayaran')
        ->toContain('Total Diterima')
        ->toContain('Kembalian')
        ->toContain('bg-orange-100')
        ->toContain('paymentProvidersAreValid')
        ->toContain('remainingAfterPayment')
        ->toContain('<details')
        ->toContain('<summary')
        ->toContain('v-if="orderServices.length > 0"')
        ->toContain('-mt-6')
        ->toContain('v-if="selectedOrder.transactions.length > 0"')
        ->toContain('redeemableRewards.length > 0')
        ->toContain('Opsional, buka untuk menambahkan diskon.')
        ->not->toContain('Belum ada transaksi untuk order ini.')
        ->not->toContain('Belum ada reward yang sesuai dengan item order')
        ->toContain('Proses')
        ->not->toContain('Pembayaran split');
});

test('cashier payment channels include bank and electronic money options', function () {
    expect(Operations::paymentMethods())
        ->toBe(['Tunai', 'QRIS', 'Kredit', 'Debit', 'Transfer', 'E-Money']);

    $posPage = file_get_contents(
        resource_path('js/pages/admin/Pos.vue'),
    );

    expect($posPage)
        ->toContain("const bankPaymentMethods = ['Kredit', 'Debit', 'Transfer']")
        ->toContain("'Flazz BCA'")
        ->toContain("'e-Money Mandiri'")
        ->toContain("'BRIZZI BRI'")
        ->toContain("'TapCash BNI'")
        ->toContain('const channelOrder = new Map(')
        ->toContain('props.paymentMethods.map((method, index) => [method, index])')
        ->toContain('paymentChannelLabel(payment)');
});

test('every order carries a paid amount within its total', function () {
    $this->withSession([RoleAccess::SESSION_KEY => 'owner'])
        ->get(route('demo.admin.pos'))
        ->assertOk()
        ->assertInertia(function (AssertableInertia $page) {
            /** @var list<array{paidAmount: int, total: int}> $orders */
            $orders = $page->toArray()['props']['orders'];

            expect($orders)->not->toBeEmpty();

            foreach ($orders as $order) {
                expect($order['paidAmount'])
                    ->toBeGreaterThanOrEqual(0)
                    ->toBeLessThanOrEqual($order['total']);
            }
        });
});

test('the payment status always agrees with how much was collected', function () {
    foreach (Operations::orders() as $order) {
        expect($order['paymentStatus'])->toBeIn(['belum bayar', 'sebagian', 'lunas']);

        $expected = match (true) {
            $order['paidAmount'] === 0 => 'belum bayar',
            $order['paidAmount'] >= $order['total'] => 'lunas',
            default => 'sebagian',
        };

        expect($order['paymentStatus'])->toBe(
            $expected,
            "Order {$order['orderNo']} is marked {$order['paymentStatus']} but has paid {$order['paidAmount']} of {$order['total']}.",
        );
    }
});

test('unpaid and partially paid orders are both on show in the demo', function () {
    $statuses = array_column(Operations::orders(), 'paymentStatus');

    expect($statuses)->toContain('belum bayar')
        ->and($statuses)->toContain('sebagian')
        ->and($statuses)->toContain('lunas');
});

test('an order that is only half paid still owes the rest', function () {
    $partiallyPaidOrders = array_values(array_filter(
        Operations::orders(),
        fn (array $order): bool => $order['paymentStatus'] === 'sebagian',
    ));

    expect($partiallyPaidOrders)->not->toBeEmpty();

    foreach ($partiallyPaidOrders as $order) {
        expect($order['total'] - $order['paidAmount'])->toBeGreaterThan(0)
            ->and($order['payment'])->not->toBe('—');
    }
});

test('the cashier receives the reward catalog for redemption', function () {
    $this->withSession([RoleAccess::SESSION_KEY => 'cashier'])
        ->get(route('demo.admin.pos'))
        ->assertOk()
        ->assertInertia(
            fn (AssertableInertia $page) => $page
                ->component('admin/Pos')
                ->has('rewards', count(Catalog::rewards()))
        );
});

test('cashier rewards are limited to services already in the order', function () {
    $serviceIds = array_column(Catalog::services(), 'id');

    foreach (Catalog::rewards() as $reward) {
        expect($reward)->toHaveKey('applicableServiceIds');

        foreach ($reward['applicableServiceIds'] as $applicableServiceId) {
            expect($serviceIds)->toContain($applicableServiceId);
        }
    }

    $waxOrderRewards = array_filter(
        Catalog::rewards(),
        fn (array $reward): bool => array_intersect([2], $reward['applicableServiceIds']) !== [],
    );

    expect($waxOrderRewards)->toBeEmpty();

    $posPage = file_get_contents(
        resource_path('js/pages/admin/Pos.vue'),
    );

    expect($posPage)
        ->toContain('reward.applicableServiceIds.some')
        ->toContain('order.serviceIds.includes(serviceId)')
        ->toContain('redeemableRewards.length > 0')
        ->not->toContain('Belum ada reward yang sesuai dengan item order');
});

test('the front office order screen has no reward redemption', function () {
    $this->withSession([RoleAccess::SESSION_KEY => 'cs'])
        ->get(route('demo.admin.orders'))
        ->assertOk()
        ->assertInertia(
            fn (AssertableInertia $page) => $page
                ->component('admin/Orders')
                ->missing('rewards')
        );

    $ordersPage = file_get_contents(
        resource_path('js/pages/admin/Orders.vue'),
    );
    $posPage = file_get_contents(
        resource_path('js/pages/admin/Pos.vue'),
    );

    expect($ordersPage)->not->toContain('Tukar reward')
        ->and($posPage)->toContain('Tukar reward')
        ->toContain('selectedRewardId');
});

test('the cash summary reports the real outstanding balance', function () {
    // Bookings are not billable until the car arrives, so they owe nothing yet.
    $outstanding = array_sum(array_map(
        fn (array $order): int => $order['total'] - $order['paidAmount'],
        Operations::billableOrders(),
    ));

    expect(Operations::outstandingTotal())->toBe($outstanding)
        ->and(Finance::summary()['pendingPayments'])->toBe($outstanding);
});

test('channels that need a bank or card also capture a reference number', function () {
    $posPage = file_get_contents(
        resource_path('js/pages/admin/Pos.vue'),
    );

    expect($posPage)
        ->toContain('const paymentReferences = ref<Record<string, string>>(')
        ->toContain('v-model.trim=')
        ->toContain('paymentReferences[')
        ->toContain('row.method')
        ->toContain('No. Referensi')
        ->toContain(':aria-label="`No. referensi untuk ${row.method}`"')
        ->toContain("reference: paymentReferences.value[method]?.trim() ?? ''")
        ->toContain('paymentReferences.value = Object.fromEntries(')
        ->toContain('Ref. {{ payment.reference }}');
});
