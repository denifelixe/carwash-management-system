<?php

use App\Support\Demo\Operations;
use App\Support\Demo\Reports;

/*
 * The cashier's slip is printed by a standalone document opened from the POS,
 * so the width of the roll and the wiring that opens it are asserted against
 * the sources rather than a rendered page.
 */

function posReceiptModule(): string
{
    return file_get_contents(resource_path('js/lib/posReceipt.ts'));
}

function posModule(): string
{
    return file_get_contents(resource_path('js/pages/admin/Pos.vue'));
}

function printDocumentModule(): string
{
    return file_get_contents(resource_path('js/lib/printDocument.ts'));
}

test('the slip is laid out at the printable width of the 80mm roll', function () {
    expect(posReceiptModule())
        // The driver receives the real media size and never scales the layout.
        ->toContain("const PAPER_WIDTH = '80mm'")
        // TM-T82's 576-dot print head exposes 72mm on 80mm stock.
        ->toContain("const PRINTABLE_WIDTH = '72mm'")
        ->toContain('const WINDOW_WIDTH = 520')
        ->toContain('width: ${PAPER_WIDTH}')
        ->toContain('@page { margin: 0; size: ${PAPER_WIDTH} auto; }')
        // Keep the 72mm print area inside the centred TM-T82 print head.
        ->toContain('padding: 0 4mm 6mm;')
        ->toContain('color: #000000;')
        ->toContain('font-size: 14px;')
        ->toContain('.contact, .copy { font-size: 12px; }')
        ->toContain('.reference, .detail, .fineprint, .verification-caption { font-size: 11px; }')
        ->toContain('.name, .amount.grand, .status { font-size: 15px; }')
        // The roll must never widen, so long references wrap instead.
        ->toContain('overflow-wrap: anywhere');
});

/*
 * The slip is dressed from Master > Struk, so its settings have to reach the
 * markup rather than sit in the settings table unread.
 */
test('the slip is dressed by the receipt settings', function () {
    expect(posReceiptModule())
        // The fine print is the outlet's line, and a blank one prints nothing.
        ->toContain("brand.receipt.footerNote === '' ? '' :")
        ->toContain('escapeHtml(brand.receipt.footerNote)')
        ->not->toContain('Struk ini adalah bukti pembayaran yang sah.')
        // The QR is a switch now, never a hardcoded hide.
        ->toContain('!brand.receipt.showQr ||')
        ->toContain('.verification { display: block; }')
        ->not->toContain('Temporarily hidden for receipt testing');

    expect(file_get_contents(resource_path('js/lib/posReceiptPdf.ts')))
        ->toContain('bodySize: 9,')
        ->toContain('const HEADING_SIZE = 11;')
        ->toContain('const FINE_SIZE = 7.5;')
        ->toContain("slip.paragraph(brand.receipt.name, 'center', {")
        ->toContain("slip.meta('Status', receipt.customerStatus)")
        ->toContain("if (brand.receipt.footerNote !== '') {")
        // Nothing is rasterised for a slip that prints no logo.
        ->toContain('const logo = brand.receipt.showLogo')
        // ...and what is rasterised is the slip's mark, not the app photo.
        ->toContain('brandArtwork(receiptWindow, brand, brand.receipt.photo)')
        // Both layouts size the mark in the same millimetres of roll.
        ->toContain('brand.receipt.logoWidth * 0.71,');

    expect(posReceiptModule())
        ->toContain('function receiptStyles(brand: CarwashBrand): string')
        ->toContain('const logoWidth = brand.receipt.logoWidth;')
        ->toContain('max-width: ${logoWidth}mm')
        ->toContain('max-height: ${logoHeight}mm')
        // The emoji fallback scales with the same setting.
        ->toContain('.logo { font-size: ${(logoWidth * 0.35).toFixed(1)}mm;')
        ->not->toContain('max-width: 56px');
});

test('the slip opens in its own window instead of inside the SPA', function () {
    expect(posReceiptModule())
        ->toContain('export function openPosReceiptWindow(')
        ->toContain("window.open(\n        receipt.publicUrl ?? '',")
        ->toContain('`popup=yes,width=${WINDOW_WIDTH},height=${WINDOW_HEIGHT},scrollbars=yes,resizable=yes`')
        ->toContain('receiptWindow.print()')
        // A blocked window is reported back rather than swallowed.
        ->toContain('return null;');
});

test('processing a payment hands the settlement straight to the slip', function () {
    expect(posModule())
        ->toContain("from '@/lib/posReceipt'")
        ->toContain('function submitPayment(): void')
        // Opened inside the click so the browser does not treat it as a popup.
        ->toContain('printReceipt();')
        ->toContain('openPosReceiptWindow(slip, props.brand) === null')
        ->toContain('@click="submitPayment"')
        ->toContain('@click="printReceipt"');
});

test('the slip carries everything the customer needs to reconcile the payment', function () {
    expect(posReceiptModule())
        ->toContain("metaRow('Ref.', receipt.reference)")
        ->toContain("metaRow('Kasir', receipt.cashier)")
        ->toContain("metaRow('Status', receipt.customerStatus)")
        ->toContain("metaRow('Plat', formatPlate(receipt.plate))")
        ->toContain("amountRow('TOTAL', receipt.total, 'grand')")
        ->toContain("amountRow('Sisa tagihan', receipt.dueAfter, 'strong')")
        ->toContain("'STRUK PEMBAYARAN'")
        ->toContain("'BUKTI PEMBAYARAN SEBAGIAN'")
        // Every value on the slip comes from user input, so none of it is raw.
        ->toContain("from '@/lib/printDocument'")
        ->toContain('escapeHtml(receipt.items)');

    expect(printDocumentModule())
        ->toContain('export function escapeHtml(value: string): string')
        ->toContain("replaceAll('<', '&lt;')")
        ->toContain('export function brandMark(')
        ->toContain('if (photo !== null)')
        ->toContain('src="${escapeHtml(photo)}"')
        ->toContain('alt="${escapeHtml(name)}"')
        ->toContain('${escapeHtml(logo)}')
        ->toContain('export function brandContacts(')
        ->toContain('contact-icon whatsapp')
        ->toContain('contact-icon instagram');

    expect(posReceiptModule())
        ->toContain(
            '${brand.receipt.showLogo ? brandMark(brand.receipt.photo, brand.logo, brand.receipt.name) : \'\'}',
        )
        ->toContain('${escapeHtml(brand.receipt.name)}')
        ->toContain('.logo-image')
        ->toContain('${brandContacts(brand.whatsapp, brand.instagram)}')
        ->not->toContain('stampBlock')
        ->not->toContain('Stempel didapat')
        ->not->toContain('Saldo stempel')
        ->not->toContain('Kumpulkan ${formatNumber(brand.stampTarget)} stempel');

    expect(posModule())
        ->toContain('reference: paymentTransactionReference(transaction, order)')
        ->toContain('lines: snapshot.lines,')
        ->toContain('previouslyPaid: snapshot.previouslyPaid,')
        ->toContain('priorDiscount: snapshot.priorDiscount,');
});

test('the slip itemises every payment the order already took', function () {
    expect(posReceiptModule())
        ->toContain('export interface PosReceiptHistoryEntry {')
        ->toContain('function historyBlock(receipt: PosReceipt): string')
        ->toContain('<p class="heading">Riwayat pembayaran</p>')
        // Time, channels with their amounts, and who took the payment.
        ->toContain('${formatDate(entry.date)} · ${entry.time}')
        ->toContain('escapeHtml(entry.channels)')
        ->toContain('Kasir ${escapeHtml(entry.cashier)}')
        ->toContain("amountRow('Dibayar sebelumnya', receipt.previouslyPaid, 'strong')")
        // A prototype order can carry a paid amount with no transactions.
        ->toContain('receipt.history.length === 0 && receipt.previouslyPaid <= 0')
        ->toContain('function paymentHeading(receipt: PosReceipt): string')
        ->toContain("return 'Pembayaran';");

    expect(posModule())
        ->toContain("customerStatus: order.customerId === null ? 'Non-member' : 'Member'")
        ->toContain('history: order.transactions.map((entry) => ({')
        ->toContain('type: paymentHistoryTypeLabel(entry)')
        ->toContain('channels: transactionChannelsLabel(entry)')
        ->toContain('cashier: paymentTransactionRecorder(entry)')
        ->toContain('history: snapshot.history,');
});

test('the POS stacks its lists as accordions with only Pelunasan open', function () {
    $accordion = file_get_contents(
        resource_path('js/components/demo/AccordionSection.vue'),
    );
    $pos = posModule();

    expect($accordion)
        ->toContain('const isOpen = ref<boolean>(props.defaultOpen)')
        ->toContain(':aria-expanded="isOpen"')
        ->toContain(':aria-controls="contentId"')
        // Filtering a list nobody can see is only noise.
        ->toContain('<div v-if="$slots.toolbar" v-show="isOpen">');

    expect($pos)
        ->toContain("import AccordionSection from '@/components/demo/AccordionSection.vue'")
        // Exactly one panel opens on arrival, and it is the settlement list.
        ->toContain('title="Pelunasan"')
        ->toContain('default-open')
        ->toContain('title="Pembayaran Sebagian/Booking"')
        ->toContain('title="Lunas"');

    expect(substr_count($pos, 'default-open'))->toBe(1);
    expect(substr_count($pos, '<AccordionSection'))->toBe(3);
});

test('settled orders stay reachable so their slip can be reprinted', function () {
    expect(posModule())
        ->toContain('const visibleCompletedOrders = computed<CarwashOrder[]>')
        ->toContain("order.status === 'selesai' && order.paymentStatus === 'lunas'")
        ->toContain('function reprintReceipt(order: CarwashOrder): void')
        ->toContain('@click="reprintReceipt(order)"')
        ->toContain('Cetak ulang struk')
        // The last payment is the settlement; the rest is history.
        ->toContain('isReprint: true')
        ->toContain('change: 0')
        // Seeded payments carry no shift, so the clock stands in.
        ->toContain('function paymentTransactionShift(transaction: CarwashTransaction): string')
        ->toContain('shift: settlement ? paymentTransactionShift(settlement) : ');

    expect(posReceiptModule())
        ->toContain('isReprint: boolean;')
        ->toContain('SALINAN / CETAK ULANG')
        ->toContain('Dicetak ulang ${escapeHtml(printedAt(receipt.timezone))}')
        // A copy cannot tell which payment took the discount.
        ->toContain("receipt.isReprint ? 'Diskon' : 'Diskon sebelumnya'")
        ->toContain("receipt.isReprint ? 'Pembayaran ini' : 'Pembayaran saat ini'");
});

/*
 * A copy can be taken of any payment in the run, so the slip is rebuilt as of
 * that moment: later payments must not leak onto it.
 */
test('any payment in the history can be reprinted as of its own moment', function () {
    expect(posModule())
        ->toContain('function transactionReceipt(')
        ->toContain('transaction: CarwashTransaction | null,')
        ->toContain('): PosReceipt {')
        // The chosen payment splits the run into history and settlement.
        ->toContain('const index = found === -1 ? transactions.length - 1 : found;')
        ->toContain('const settlement = transactions[index] ?? null;')
        ->toContain('const history = index > 0 ? transactions.slice(0, index) : [];')
        ->toContain('const paidTotal = previouslyPaid + (settlement?.amount ?? 0);')
        ->toContain('dueAfter: Math.max(order.total - paidTotal, 0)')
        // A partial payment reprints as a partial slip, not a settled one.
        ->toContain("isSettled: settlement?.type === 'Pembayaran Lunas'")
        // Reprinting from inside a dialog prints without stacking another one.
        ->toContain('function reprintTransaction(')
        ->toContain('openReceipt(transactionReceipt(order, transaction))')
        ->toContain('reprintTransaction(
'.str_repeat(' ', 52).'selectedOrder,')
        ->toContain('reprintTransaction(
'.str_repeat(' ', 64).'detail.order,')
        // Taking a payment still produces a live slip, never a copy.
        ->toContain('isReprint: false');
});

/*
 * Settling the order drives `dueAmount` to zero, so `paymentTotal` collapses
 * with it. Reading the change afterwards reported the whole tender back to the
 * customer as change.
 */
test('the tender and change are read before the order is settled', function () {
    $pos = posModule();

    expect($pos)
        // Both figures are banked in the snapshot the payment is built from...
        ->toContain('tendered: tenderedTotal.value,')
        ->toContain('change: changeAmount.value,')
        // ...and every slip prints them from there, never re-reading them.
        ->toContain('tenderedTotal: snapshot.tendered,')
        ->toContain('change: snapshot.change,')
        ->not->toContain('tenderedTotal: tenderedTotal.value');

    $snapshot = strpos($pos, 'change: changeAmount.value,');
    $mutation = strpos($pos, 'order.paidAmount += amount;');

    expect($snapshot)->toBeLessThan($mutation);
});

/*
 * Both recap rows are counted and totalled per transaction. Matching the
 * settled row by order instead listed that order's earlier instalments under a
 * total that never included them, and repeated them under the partial row.
 */
test('the recap detail list matches the row it expands', function () {
    $pos = posModule();

    expect($pos)
        ->toContain("? transaction.type === 'Pembayaran Sebagian'")
        ->toContain(": transaction.type === 'Pembayaran Lunas'")
        // Matching the settled row by order is what leaked the instalments.
        ->not->toContain('fullyPaidOrderIds');

    /*
     * The leak needs an order that already took instalments today and is still
     * waiting to be settled: settling it puts both types on the one order.
     */
    $today = Reports::todayDate();
    $awaitingSettlement = array_filter(
        Operations::orders(),
        function (array $order) use ($today): bool {
            $instalments = array_filter(
                $order['transactions'],
                fn (array $transaction): bool => $transaction['date'] === $today
                    && $transaction['type'] === 'Pembayaran Sebagian',
            );

            return $order['status'] === 'pelunasan' && $instalments !== [];
        },
    );

    // Without one of these the fixture could not catch a regression here.
    expect($awaitingSettlement)->not->toBeEmpty();
});

test('a settled order card lists its whole payment run', function () {
    expect(posModule())
        ->toContain('v-for="transaction in order.transactions"')
        ->toContain('{{ paymentTransactionLabel(transaction) }}')
        // Instalments first, the settlement last and weighted heavier.
        ->toContain("transaction.type === 'Pembayaran Sebagian'")
        ->toContain("'font-medium text-emerald-700'")
        ->toContain("'font-semibold text-emerald-800'");
});
