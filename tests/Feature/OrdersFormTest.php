<?php

use App\Support\Carwash\Operations;
use App\Support\Carwash\Reports;
use App\Support\Carwash\RoleAccess;

test('new orders defer crew assignment and payment to their dedicated workflows', function () {
    $ordersPage = file_get_contents(
        resource_path('js/pages/carwash/admin/Orders.vue'),
    );

    expect($ordersPage)
        ->not->toContain('v-model="draft.crew"')
        ->not->toContain('v-model="draft.paymentStatus"')
        ->not->toContain('Bayar nanti di kasir')
        ->toContain('createdOrderAlert.value = { orderNo, customer: customerName }')
        ->toContain('Order berhasil disimpan');
});

test('the order lifecycle starts on booking and ends in selesai or batal', function () {
    expect(Operations::orderStatuses())
        ->toBe(['booking', 'menunggu', 'proses', 'pelunasan', 'selesai', 'batal']);
});

test('the floor can set the stages up to pelunasan or cancel the order', function () {
    expect(Operations::editableOrderStatuses())
        ->toBe(['booking', 'menunggu', 'proses', 'pelunasan', 'batal'])
        ->not->toContain('selesai');
});

test('a booking scheduled today is marked as not arrived wherever it is shown', function () {
    $ordersPage = file_get_contents(
        resource_path('js/pages/carwash/admin/Orders.vue'),
    );

    expect($ordersPage)
        ->toContain("const bookingStatusLabel = 'Booking Hari ini - Belum Datang';")
        ->toContain('label: bookingStatusLabel,')
        ->toContain('<StatusPill :status="displayedStatus(order)" />');

    expect(file_get_contents(resource_path('js/components/carwash/StatusPill.vue')))
        ->toContain("booking: 'Booking Hari ini - Belum Datang',");
});

test('every order sits on one of the lifecycle stages', function () {
    $stages = Operations::orderStatuses();

    foreach (Operations::orders() as $order) {
        expect($stages)->toContain($order['status']);
    }
});

test('the order list tracks a single status and leaves money to the cashier', function () {
    $ordersPage = file_get_contents(
        resource_path('js/pages/carwash/admin/Orders.vue'),
    );

    expect($ordersPage)
        // One status column: the payment column and its pill are gone.
        ->not->toContain('<th class="px-5 py-3">Pembayaran</th>')
        ->not->toContain(':status="order.paymentStatus"')
        ->not->toContain(':status="detailOrder.paymentStatus"')
        // The summary counts orders per stage, never money.
        ->not->toContain('label="Pembayaran diterima"')
        ->not->toContain('label="Stempel diberikan"')
        ->toContain('label="Total Order"')
        ->toContain(':label="card.label"')
        // The booking chip uses the full status label.
        ->toContain("status === 'booking' ? bookingStatusLabel : status")
        ->toContain('@filter="applyStatusFilter"')
        // The list lands on the queue that still needs a crew.
        ->toContain("const statusFilter = ref<string>('menunggu');")
        // The status column shows the stage on its own, without a bay.
        ->toContain('<StatusPill :status="displayedStatus(order)" />')
        ->not->toContain('{{ order.bay }}')
        // Crew and bay are not tracked from the order page at all.
        ->not->toContain('Crew & bay')
        ->not->toContain('{{ detailOrder.crew }}')
        ->not->toContain('{{ detailOrder.bay }}');
});

test('the summary shows a card per lifecycle stage on top of the total', function () {
    $ordersPage = file_get_contents(
        resource_path('js/pages/carwash/admin/Orders.vue'),
    );

    expect($ordersPage)
        ->toContain('label="Total Order"')
        ->toContain(':columns="7"');

    foreach (Operations::orderStatuses() as $status) {
        $expectedLabel = $status === 'booking'
            ? 'bookingStatusLabel'
            : "'".ucfirst($status)."'";

        expect($ordersPage)->toContain("    {$status}: {");
        expect($ordersPage)->toContain("label: {$expectedLabel},");
    }
});

test('the booking filter includes booking orders until they are closed', function () {
    $ordersPage = file_get_contents(
        resource_path('js/pages/carwash/admin/Orders.vue'),
    );

    expect($ordersPage)
        ->toContain("order.source === 'booking' && !closedStatuses.includes(order.status)")
        ->toContain("status === 'booking'")
        ->toContain('isOpenBookingOrder(order)');
});

test('today booking count matches the booking order module', function () {
    $today = Reports::todayDate();
    $closedStatuses = ['selesai', 'batal'];

    $todayBookingCount = count(array_filter(
        Operations::scheduledBookings(),
        fn (array $booking): bool => $booking['date'] === $today,
    ));
    $todayOpenBookingOrderCount = count(array_filter(
        Operations::orders(),
        fn (array $order): bool => $order['date'] === $today
            && $order['source'] === 'booking'
            && ! in_array($order['status'], $closedStatuses, true),
    ));

    expect($todayOpenBookingOrderCount)->toBe($todayBookingCount);
});

test('summary cards filter the order list and expose their active state', function () {
    $ordersPage = file_get_contents(
        resource_path('js/pages/carwash/admin/Orders.vue'),
    );
    $statCard = file_get_contents(
        resource_path('js/components/carwash/StatCard.vue'),
    );

    expect($ordersPage)
        ->toContain(':active="statusFilter === \'Semua\'"')
        ->toContain('@click="statusFilter = \'Semua\'"')
        ->toContain(':active="statusFilter === card.status"')
        ->toContain('@click="statusFilter = card.status"');

    expect($statCard)
        ->toContain("interactive ? 'button' : 'article'")
        ->toContain("'cursor-pointer")
        ->toContain(':aria-pressed="interactive ? active : undefined"')
        ->toContain('@click="$emit(\'click\')"');
});

test('the order detail edits and cancels through the status dropdown', function () {
    $ordersPage = file_get_contents(
        resource_path('js/pages/carwash/admin/Orders.vue'),
    );

    expect($ordersPage)
        // The dropdown options come from the server, so selesai is never offered.
        ->toContain('v-model="statusDraft"')
        ->toContain('v-for="status in editableOrderStatuses"')
        // The draft only lands on the order once it is saved.
        ->toContain(':disabled="!isStatusDirty"')
        ->toContain('@click="saveStatus"')
        ->not->toContain('function cancelOrder')
        ->not->toContain('@click="cancelOrder(detailOrder)"')
        // Advancing blindly through the flow is gone.
        ->not->toContain('function advanceStatus');
});

test('cancelled orders remain editable while completed orders are locked', function () {
    $ordersPage = file_get_contents(
        resource_path('js/pages/carwash/admin/Orders.vue'),
    );

    expect($ordersPage)
        ->toContain("detailOrder.value?.status === 'selesai'")
        ->toContain('v-if="isDetailReadOnly"')
        ->not->toContain("detailOrder.value?.status === 'batal'")
        ->not->toContain('v-if="isDetailClosed"');
});

test('the whole order row opens the detail, not just the Detail button', function () {
    $ordersPage = file_get_contents(
        resource_path('js/pages/carwash/admin/Orders.vue'),
    );

    expect($ordersPage)
        ->toContain('class="cursor-pointer transition hover:bg-slate-50/70"')
        ->toContain('@click="detailOrderId = order.id"');
});

test('the order list distinguishes customer and non customer walk-ins', function () {
    $ordersPage = file_get_contents(
        resource_path('js/pages/carwash/admin/Orders.vue'),
    );
    $walkInOrders = array_filter(
        Operations::orders(),
        fn (array $order): bool => $order['source'] === 'walk-in',
    );

    expect($ordersPage)
        ->toContain("return 'Booking';")
        ->toContain("? 'Walk-in Non Customer'")
        ->toContain(": 'Walk-in Customer';")
        ->toContain('{{ orderSourceLabel(order) }}');

    expect(array_filter(
        $walkInOrders,
        fn (array $order): bool => $order['customerId'] === null,
    ))->not->toBeEmpty();

    expect(array_filter(
        $walkInOrders,
        fn (array $order): bool => $order['customerId'] !== null,
    ))->not->toBeEmpty();
});

test('non member walk-ins keep their name and phone number', function () {
    $ordersPage = file_get_contents(
        resource_path('js/pages/carwash/admin/Orders.vue'),
    );
    $nonMemberOrders = array_filter(
        Operations::orders(),
        fn (array $order): bool => $order['source'] === 'walk-in'
            && $order['customerId'] === null,
    );

    expect($ordersPage)
        ->toContain('v-model="draft.customerPhone"')
        ->toContain("customerMode.value === 'walk-in' ? ' (non-member)' : ''")
        ->toContain('draft.value.customerPhone.trim() !== \'\'')
        ->toContain('phone: customer?.phone ?? draft.value.customerPhone.trim()');

    expect($nonMemberOrders)->not->toBeEmpty();

    foreach ($nonMemberOrders as $order) {
        expect($order['customer'])->toEndWith(' (non-member)')
            ->and($order['phone'])->not->toBe('—')
            ->not->toBeEmpty();
    }
});

test('new orders separate members and non-members without registering members', function () {
    $ordersPage = file_get_contents(
        resource_path('js/pages/carwash/admin/Orders.vue'),
    );

    expect($ordersPage)
        ->toContain("{ key: 'existing', label: 'Member' },")
        ->toContain("{ key: 'walk-in', label: 'Non-Member' },")
        ->toContain('class="mt-2 grid grid-cols-2 gap-1')
        ->toContain('for="order-customer-name"')
        ->toContain('for="order-customer-phone"')
        ->toContain('for="order-vehicle-plate"')
        ->toContain('for="order-vehicle-type"')
        ->toContain('Nama')
        ->toContain('Nomor Telpon')
        ->toContain('Plat Nomor')
        ->toContain('Tipe Mobil')
        ->toContain('Member tidak ditemukan.')
        ->toContain('atau pilih Non-Member.')
        ->not->toContain("'new-member'")
        ->not->toContain('Member baru');
});

test('the sidebar calls the module Order, not Order / Transaksi', function () {
    $labels = array_column(RoleAccess::modules(), 'label', 'key');

    expect($labels['orders'])->toBe('Order');
});

test('the card in force wears its own colour, not just an outline', function () {
    $statCard = file_get_contents(
        resource_path('js/components/carwash/StatCard.vue'),
    );

    expect($statCard)
        // Surface, icon chip, and accent are picked per tone.
        ->toContain('style.surface')
        ->toContain('style.activeChip')
        ->toContain('style.accent')
        // A check badge and a top bar mark the chosen card at a glance.
        ->toContain('<Check class="h-3.5 w-3.5" />')
        ->toContain('absolute inset-x-0 top-0 h-1')
        // The old neutral outline is gone.
        ->not->toContain("'border-slate-900 ring-2 ring-slate-900/10'");

    foreach (['default', 'emerald', 'rose', 'amber', 'violet', 'slate'] as $tone) {
        expect($statCard)->toContain($tone.': {');
    }
});
