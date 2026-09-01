<?php

use App\Support\Demo\Operations;
use App\Support\Demo\Reports;
use App\Support\Demo\RoleAccess;

test('new orders defer crew assignment and payment to their dedicated workflows', function () {
    $ordersPage = file_get_contents(
        resource_path('js/pages/admin/Orders.vue'),
    );

    expect($ordersPage)
        ->not->toContain('v-model="draft.crew"')
        ->not->toContain('v-model="draft.paymentStatus"')
        ->not->toContain('Bayar nanti di kasir')
        ->toContain('createdOrderAlert.value = { orderNo, customer: customerName }')
        ->toContain('Order berhasil disimpan');
});

test('the order form uses a variation cart with quantity priced from the selected combination', function () {
    $ordersPage = file_get_contents(
        resource_path('js/pages/admin/Orders.vue'),
    );
    $picker = file_get_contents(
        resource_path('js/components/admin/ServiceCartPicker.vue'),
    );

    expect($ordersPage)
        ->toContain('<ServiceCartPicker')
        ->toContain('v-model="draft.serviceItems"')
        ->not->toContain('{{ formatCurrency(draftTotal) }}')
        ->not->toContain('+{{ draftStamps }} stempel');

    expect($picker)
        ->toContain('Keranjang layanan')
        ->toContain('cartDetails(item)!.variation.price')
        ->toContain('item.quantity')
        ->toContain('Belum ada layanan di keranjang.');
});

test('the service picker keeps its catalog compact behind an internal scroll', function () {
    $picker = file_get_contents(
        resource_path('js/components/admin/ServiceCartPicker.vue'),
    );

    expect($picker)
        ->toContain('class="grid max-h-40 [scrollbar-gutter:stable] grid-cols-1 gap-2 overflow-y-auto pr-1 sm:max-h-64 sm:grid-cols-2"')
        // A round chevron button hints at the overflow and hides at the bottom.
        ->toContain('@scroll="syncScrollHint"')
        ->toContain('v-if="canScrollDown"')
        ->toContain('<ChevronDown class="h-4 w-4" />')
        ->toContain('@click="scrollCatalogDown"')
        ->toContain(
            'element.scrollHeight - element.scrollTop - element.clientHeight > 8',
        );
});

test('the order list omits totals while detail only references service prices', function () {
    $ordersPage = file_get_contents(
        resource_path('js/pages/admin/Orders.vue'),
    );

    expect($ordersPage)
        ->not->toContain('<th class="px-5 py-3 text-right">Total</th>')
        ->not->toContain('{{ formatCurrency(order.total) }}')
        ->not->toContain('<dt class="text-slate-500">Metode bayar</dt>')
        ->not->toContain('{{ detailOrder.payment }}')
        ->not->toContain('{{ formatCurrency(detailOrder.total) }}')
        ->not->toContain('{{ formatCurrency(detailOrder.paidAmount) }}')
        ->not->toContain('detailOrder.total - detailOrder.paidAmount')
        ->not->toContain('<dt class="text-slate-500">Invoice</dt>')
        ->not->toContain('{{ detailOrder.invoice }}')
        ->not->toContain('<dt class="text-slate-500">Stempel diberikan</dt>')
        ->not->toContain('+{{ detailOrder.stampsEarned }}')
        // Detail reads immutable line-item snapshots, not current catalog prices.
        ->toContain('{{ formatCurrency(item.unitPrice) }}')
        ->toContain('{{ formatCurrency(item.totalPrice) }}')
        ->not->toContain(')?.price ?? 0,');
});

test('the order detail leads with its date and highlighted vehicle information', function () {
    $ordersPage = file_get_contents(
        resource_path('js/pages/admin/Orders.vue'),
    );

    expect($ordersPage)
        ->toContain('`${formatDate(detailOrder.date)} • ${detailOrder.time}`')
        ->not->toContain('`${detailOrder?.customer} • ${detailOrder?.time}`')
        ->toContain('Info Pelanggan')
        ->toContain('class="mt-1 text-xl font-bold tracking-wide text-slate-900"')
        ->toContain('{{ formatPlate(detailOrder.plate) }}')
        ->toContain('{{ detailOrder.vehicle }}')
        ->toContain('{{ orderSourceLabel(detailOrder) }}')
        ->toContain('{{ detailOrder.customer }}')
        ->toContain('{{ detailOrder.phone }}');
});

test('the order detail shows transaction history and a full width close button', function () {
    $ordersPage = file_get_contents(
        resource_path('js/pages/admin/Orders.vue'),
    );

    expect($ordersPage)
        ->toContain('Riwayat transaksi')
        ->toContain('{{ detailOrder.transactions.length }} transaksi')
        ->toContain('v-for="transaction in detailOrder.transactions"')
        ->toContain('function transactionCaption(type: string): string')
        ->toContain("type === 'Pembayaran Sebagian'")
        ->toContain("? 'Pembayaran Sebagian/Booking'")
        ->toContain(": 'Pembayaran Sisa/Lunas (Order Selesai)';")
        ->toContain('transactionCaption(transaction.type)')
        ->not->toContain('Pembayaran Sebagian/Walk-In')
        ->toContain('{{ transaction.channels }}')
        ->toContain('{{ formatCurrency(transaction.amount) }}')
        ->toContain('Belum ada transaksi')
        ->not->toContain('stempel sudah masuk ke akun customer')
        ->toContain('class="w-full rounded-xl bg-slate-900');
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
        resource_path('js/pages/admin/Orders.vue'),
    );

    expect($ordersPage)
        ->toContain("const bookingStatusLabel = 'Booking - Belum Datang';")
        ->toContain('label: bookingStatusLabel,')
        ->toContain(':status="displayedStatus(order)"');

    expect(file_get_contents(resource_path('js/components/demo/StatusPill.vue')))
        ->toContain("booking: 'Booking - Belum Datang',");
});

test('every order sits on one of the lifecycle stages', function () {
    $stages = Operations::orderStatuses();

    foreach (Operations::orders() as $order) {
        expect($stages)->toContain($order['status']);
    }
});

test('the order list tracks a single status and leaves money to the cashier', function () {
    $ordersPage = file_get_contents(
        resource_path('js/pages/admin/Orders.vue'),
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
        ->toContain(':status="displayedStatus(order)"')
        ->not->toContain('{{ order.bay }}')
        // Crew and bay are not tracked from the order page at all.
        ->not->toContain('Crew & bay')
        ->not->toContain('{{ detailOrder.crew }}')
        ->not->toContain('{{ detailOrder.bay }}');
});

test('the summary shows a card per lifecycle stage on top of the total', function () {
    $ordersPage = file_get_contents(
        resource_path('js/pages/admin/Orders.vue'),
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

test('the booking filter only includes vehicles that have not arrived', function () {
    $ordersPage = file_get_contents(
        resource_path('js/pages/admin/Orders.vue'),
    );

    expect($ordersPage)
        ->toContain("order.source === 'booking' && order.status === 'booking'")
        ->toContain('isAwaitingArrivalBooking(order)')
        ->not->toContain('isOpenBookingOrder(order)');
});

test('today booking count excludes bookings that have already arrived', function () {
    $today = Reports::todayDate();

    $todayBookingOrders = array_filter(
        Operations::orders(),
        fn (array $order): bool => $order['date'] === $today
            && $order['source'] === 'booking',
    );
    $todayAwaitingArrivalOrders = array_filter(
        $todayBookingOrders,
        fn (array $order): bool => $order['status'] === 'booking',
    );
    $todayArrivedBookingOrders = array_filter(
        $todayBookingOrders,
        fn (array $order): bool => $order['status'] !== 'booking',
    );

    expect($todayAwaitingArrivalOrders)
        ->not->toBeEmpty()
        ->and($todayArrivedBookingOrders)->not->toBeEmpty()
        ->and(count($todayAwaitingArrivalOrders))
        ->toBeLessThan(count($todayBookingOrders));
});

test('summary cards filter the order list and expose their active state', function () {
    $ordersPage = file_get_contents(
        resource_path('js/pages/admin/Orders.vue'),
    );
    $statCard = file_get_contents(
        resource_path('js/components/demo/StatCard.vue'),
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
        resource_path('js/pages/admin/Orders.vue'),
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
        resource_path('js/pages/admin/Orders.vue'),
    );

    expect($ordersPage)
        ->toContain("detailOrder.value?.status === 'selesai'")
        ->toContain('v-if="isDetailReadOnly"')
        ->not->toContain("detailOrder.value?.status === 'batal'")
        ->not->toContain('v-if="isDetailClosed"');
});

test('the whole order row opens the detail, not just the Detail button', function () {
    $ordersPage = file_get_contents(
        resource_path('js/pages/admin/Orders.vue'),
    );

    expect($ordersPage)
        ->toContain('cursor-pointer transition hover:bg-slate-50/70')
        ->toContain('@click="detailOrderId = order.id"')
        // The narrow layout hides the button, so the row also takes a key.
        ->toContain('@keydown.enter="detailOrderId = order.id"');
});

/*
 * A phone and a tablet only have room for three columns, so the customer joins
 * the vehicle cell and the Detail button drops away — the row itself opens the
 * order there.
 */
test('the narrow order list keeps only vehicle, services, and status', function () {
    $ordersPage = file_get_contents(
        resource_path('js/pages/admin/Orders.vue'),
    );

    expect($ordersPage)
        // Customer column and the Detail button are wide-layout only.
        ->toContain('<th class="hidden px-5 py-3 lg:table-cell">')
        ->toContain('<td class="hidden px-5 py-3.5 lg:table-cell">')
        // Kendaraan, Layanan, and Status carry no such guard.
        ->toContain('<th class="px-5 py-3">Kendaraan</th>')
        ->toContain('<th class="px-5 py-3">Layanan</th>')
        ->toContain('<th class="px-5 py-3">Status</th>')
        // The customer repeats inside the vehicle cell for that layout.
        ->toContain('<div class="mt-1 lg:hidden">')
        // Nothing forces a horizontal scroll before the wide layout.
        ->toContain('min-w-[340px] text-sm lg:min-w-[900px]');
});

/*
 * Reaching the detail panel to move a car one stage is a hop too far on a
 * phone, so the chip in the row is the picker and saves as it is chosen.
 */
test('the status chip in a row changes the stage without opening the order', function () {
    $ordersPage = file_get_contents(
        resource_path('js/pages/admin/Orders.vue'),
    );

    expect($ordersPage)
        ->toContain('function changeRowStatus(')
        ->toContain('statusForm.submit(updateOrderStatus(order.id), {')
        ->toContain('    ChevronDown,')
        ->toContain('<ChevronDown')
        // Only the stages the floor owns, and never on a settled order.
        ->toContain('v-for="status in editableOrderStatuses"')
        ->toContain("props.capabilities.update && order.status !== 'selesai'")
        // Choosing a stage must not also open the detail panel.
        ->toContain('<td class="px-5 py-3.5" @click.stop>')
        // The row in flight is the only one that greys out.
        ->toContain('pendingStatusOrderId === order.id');
});

test('the order list distinguishes customer and non customer walk-ins', function () {
    $ordersPage = file_get_contents(
        resource_path('js/pages/admin/Orders.vue'),
    );
    $walkInOrders = array_filter(
        Operations::orders(),
        fn (array $order): bool => $order['source'] === 'walk-in',
    );

    expect($ordersPage)
        ->toContain("? 'Booking Non Member'")
        ->toContain(": 'Booking Member';")
        ->toContain("? 'Walk-In Non Member'")
        ->toContain(": 'Walk-In Member';")
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

test('the order list leads with vehicle arrival information', function () {
    $ordersPage = file_get_contents(
        resource_path('js/pages/admin/Orders.vue'),
    );

    $headStart = (int) strpos($ordersPage, '<thead>');
    $head = substr(
        $ordersPage,
        $headStart,
        (int) strpos($ordersPage, '</thead>') - $headStart,
    );

    expect(strpos($head, 'Kendaraan'))
        ->toBeInt()
        ->toBeLessThan(strpos($head, 'Customer'))
        ->and($ordersPage)
        ->not->toContain('<th class="px-5 py-3">Order</th>')
        ->toContain('class="text-xl font-bold tracking-wide text-slate-900"')
        ->toContain('{{ formatPlate(order.plate) }}')
        ->toContain('{{ order.vehicle }}')
        ->toContain("order.time === '—'")
        ->toContain('`${formatDate(order.date)} · ${order.time}`')
        ->toContain('{{ orderArrivalLabel(order) }}')
        ->toContain("<span>{{ orderSourceLabel(order) }}</span>\n                                    <span>{{ order.orderNo }}</span>");
});

test('non member walk-ins keep their name and phone number', function () {
    $ordersPage = file_get_contents(
        resource_path('js/pages/admin/Orders.vue'),
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
        resource_path('js/pages/admin/Orders.vue'),
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
        resource_path('js/components/demo/StatCard.vue'),
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

test('the order form uses the shared variation and quantity cart picker', function () {
    $ordersPage = file_get_contents(resource_path('js/pages/admin/Orders.vue'));
    $picker = file_get_contents(resource_path('js/components/admin/ServiceCartPicker.vue'));

    expect($ordersPage)
        ->toContain('<ServiceCartPicker')
        ->toContain('v-model="draft.serviceItems"')
        ->toContain('service_variation_id: item.serviceVariationId')
        ->toContain('quantity: item.quantity');

    expect($picker)
        ->toContain('placeholder="Cari layanan, kategori, atau variasi"')
        ->toContain('tokens.every((token) => haystack.includes(token))')
        ->toContain('service.serviceVariations')
        ->toContain('variation.variations?.[attribute]')
        ->toContain('item.quantity + quantity.value')
        ->toContain('Tambah ke Keranjang')
        ->toContain('Layanan tidak ditemukan.');
});

test('the cart picker filters the catalog with multi select category tabs above the search', function () {
    $picker = file_get_contents(resource_path('js/components/admin/ServiceCartPicker.vue'));

    expect($picker)
        ->toContain('const selectedCategories = ref<string[]>([])')
        ->toContain('function toggleCategory(option: string): void')
        ->toContain('selectedCategories.value.includes(service.category)')
        ->toContain('@click="selectedCategories = []"')
        ->toContain('v-for="option in categoryOptions"')
        ->toContain('service.serviceVariations.some((variation) => variation.isActive)');

    expect(strpos($picker, 'v-if="categoryOptions.length > 1"'))
        ->toBeLessThan(strpos($picker, 'placeholder="Cari layanan, kategori, atau variasi"'));
});

test('the cart picker folds the catalog and the cart into one open panel on phones', function () {
    $picker = file_get_contents(resource_path('js/components/admin/ServiceCartPicker.vue'));

    expect($picker)
        // One panel at a time, catalog first, and both can be collapsed.
        ->toContain("const openPanel = ref<'services' | 'cart' | null>('services')")
        ->toContain("function togglePanel(panel: 'services' | 'cart'): void")
        ->toContain('openPanel.value = openPanel.value === panel ? null : panel')
        ->toContain('@click="togglePanel(\'services\')"')
        ->toContain('@click="togglePanel(\'cart\')"')
        ->toContain(':aria-expanded="openPanel === \'services\'"')
        ->toContain(':aria-expanded="openPanel === \'cart\'"')
        // Collapsing only applies below sm: tablets and desktops keep both panels open.
        ->toContain('openPanel === \'services\' ? \'\' : \'hidden sm:block\'')
        ->toContain('openPanel === \'cart\' ? \'\' : \'hidden sm:block\'')
        ->toContain('sm:pointer-events-none')
        // Each header carries its own count so a collapsed panel still reports state.
        ->toContain('{{ visibleServices.length }} layanan')
        ->toContain('{{ modelValue.length }} item');
});

test('a shorter catalog on phones keeps the cart header on the first screen', function () {
    $picker = file_get_contents(resource_path('js/components/admin/ServiceCartPicker.vue'));

    expect($picker)
        // The list scrolls inside a shorter box on phones, full height from sm up.
        ->toContain('max-h-40 [scrollbar-gutter:stable] grid-cols-1 gap-2 overflow-y-auto pr-1 sm:max-h-64')
        // The cart stays in normal flow below it — never a floating overlay.
        ->toContain('<div class="overflow-hidden rounded-2xl bg-slate-50">')
        ->not->toContain('sticky bottom-0')
        // A filled badge makes a non-empty cart obvious at a glance.
        ->toContain("modelValue.length\n                                ? 'bg-cyan-600 text-white'");

    expect(strpos($picker, "togglePanel('services')"))
        ->toBeLessThan(strpos($picker, "togglePanel('cart')"));
});

test('the order and booking forms drop their services label on phones so the picker owns the header', function () {
    foreach (['Orders', 'Bookings'] as $page) {
        expect(file_get_contents(resource_path("js/pages/admin/{$page}.vue")))
            ->toContain('class="mb-2 hidden text-[11px] font-medium tracking-wider text-slate-400 uppercase sm:block"');
    }
});
