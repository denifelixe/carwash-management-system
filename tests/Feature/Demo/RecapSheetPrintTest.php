<?php

/*
 * Both shift recaps are printed by a standalone document opened outside the
 * SPA, so the papers it lays out and the wiring that opens it are asserted
 * against the sources rather than a rendered page.
 */

function recapSheetModule(): string
{
    return file_get_contents(resource_path('js/lib/recapSheet.ts'));
}

function recapPrintMenuComponent(): string
{
    return file_get_contents(
        resource_path('js/components/demo/RecapPrintMenu.vue'),
    );
}

function recapFinancePage(): string
{
    return file_get_contents(resource_path('js/pages/admin/Finance.vue'));
}

function recapPosPage(): string
{
    return file_get_contents(resource_path('js/pages/admin/Pos.vue'));
}

test('the recap prints on either A4 or the 78mm roll', function () {
    expect(recapSheetModule())
        ->toContain("export type RecapPaper = 'a4' | 'struk';")
        ->toContain('@page { margin: 14mm; size: A4; }')
        // The same roll the cashier station is already loaded with.
        ->toContain("const PAPER_WIDTH = '78mm'")
        ->toContain('@page { margin: 0; size: ${PAPER_WIDTH} auto; }')
        // A long channel name must wrap, never widen the sheet.
        ->toContain('overflow-wrap: anywhere');
});

/*
 * One body, two stylesheets: a figure cannot appear on one paper and go
 * missing on the other, because only the CSS is chosen by paper.
 */
test('both papers are laid out from one body', function () {
    expect(recapSheetModule())
        ->toContain('function recapSheetBody(sheet: RecapSheet, brand: CarwashBrand): string')
        ->toContain("paper === 'a4' ? a4Styles() : strukStyles()")
        // The roll cannot hold columns, so each row stacks its own headings.
        ->toContain('thead { display: none; }')
        ->toContain('.cell-label { display: none; }');
});

test('the recap opens in its own window and reports a blocked one', function () {
    expect(recapSheetModule())
        ->toContain('export function openRecapSheetWindow(')
        ->toContain('`popup=yes,width=${WINDOW_WIDTHS[paper]},height=${WINDOW_HEIGHT},scrollbars=yes,resizable=yes`')
        ->toContain('recapWindow.print()')
        // A blocked window is reported back rather than swallowed.
        ->toContain('return null;');

    // Both pages flag it so the desk is told to allow pop-ups.
    expect(recapFinancePage())
        ->toContain('openRecapSheetWindow(financeRecapSheet(), props.brand, paper) === null')
        ->toContain(':blocked="isRecapWindowBlocked"');

    expect(recapPosPage())
        ->toContain('openRecapSheetWindow(posRecapSheet(), props.brand, paper) === null')
        ->toContain(':blocked="isRecapWindowBlocked"');
});

test('the sheet prints the recap only, never the transactions behind it', function () {
    // The module has no notion of a transaction to print in the first place.
    expect(recapSheetModule())
        ->toContain('summary: RecapSummaryItem[];')
        ->toContain('tables: RecapTable[];')
        ->not->toContain('CarwashTransaction')
        ->not->toContain('CarwashMoneyEntry');

    // Neither builder reaches for the list its page renders under the recap.
    $financeSheet = recapBuilderSource(recapFinancePage(), 'function financeRecapSheet(): RecapSheet {');
    $posSheet = recapBuilderSource(recapPosPage(), 'function posRecapSheet(): RecapSheet {');

    expect($financeSheet)->not->toContain('filteredEntries');
    expect($posSheet)->not->toContain('paymentRecapDetails');
});

test('the finance recap carries the cards and every channel', function () {
    $sheet = recapBuilderSource(recapFinancePage(), 'function financeRecapSheet(): RecapSheet {');

    expect($sheet)
        ->toContain("title: 'Rekap Keuangan',")
        // The three cards, spelled the way the page spells them.
        ->toContain("label: 'Uang masuk',")
        ->toContain("label: 'Uang keluar',")
        ->toContain("label: 'Sisa saldo',")
        ->toContain('formatCurrency(totalIn.value)')
        ->toContain('formatCurrency(totalOut.value)')
        ->toContain('formatCurrency(remainingBalance.value)')
        // The channel table, straight off the rows the page renders.
        ->toContain("heading: 'Kanal Keuangan',")
        ->toContain('const channels = channelRows.value;')
        ->toContain("'Pemasukan',")
        ->toContain("'Pengeluaran',")
        ->toContain("'Saldo Kanal',");
});

test('the POS recap carries the payment types and channels', function () {
    $sheet = recapBuilderSource(recapPosPage(), 'function posRecapSheet(): RecapSheet {');

    expect($sheet)
        ->toContain("title: 'Rekap Pembayaran Diterima',")
        ->toContain('formatCurrency(activePaymentRecapTotal.value)')
        ->toContain('formatNumber(paymentRecapTransactionCount.value)')
        ->toContain('formatNumber(paymentRecapFinalOrderCount.value)')
        ->toContain("heading: 'Jenis transaksi',")
        ->toContain('paymentRecapByType.value.map')
        ->toContain("heading: 'Kanal pembayaran',")
        ->toContain('const channels = paymentRecapByChannel.value;')
        ->toContain("emptyMessage: 'Belum ada pembayaran pada periode ini.',");
});

/*
 * A recap is only worth printing if it says whose shift it is, and it must be
 * the tab the user is looking at rather than a fixed one.
 */
test('the printed recap follows the shift tab that is open', function () {
    expect(recapFinancePage())
        ->toContain('shiftTabs.value.find((shift) => shift.id === activeShift.value)')
        ->toContain("shiftLabel: tab?.label ?? 'Seluruh Shift & Tanpa Shift',")
        ->toContain('shiftCaption: tab?.caption ?? null,');

    expect(recapPosPage())
        ->toContain('(option) => option.key === activePaymentRecapShift.value,')
        ->toContain("shiftLabel: tab?.label ?? 'Total',")
        ->toContain('shiftCaption: tab?.caption ?? null,');

    expect(recapSheetModule())
        ->toContain('<p class="shift">${escapeHtml(sheet.shiftLabel)}</p>');
});

test('the print stamp reads the outlet clock, not the machine', function () {
    expect(recapSheetModule())
        ->toContain('timezone: string;')
        ->toContain('printedAt(sheet.timezone)');

    expect(recapFinancePage())->toContain('timezone: props.filters.timezone,');
    expect(recapPosPage())->toContain('timezone: props.filters.timezone,');

    expect(printDocumentModule())
        ->toContain('export function printedAt(timeZone: string): string')
        ->toContain('timeZone,');
});

test('one paper picker serves both recaps', function () {
    expect(recapPrintMenuComponent())
        ->toContain('print: [paper: RecapPaper];')
        ->toContain('Cetak A4')
        ->toContain('Cetak Struk (78mm)')
        ->toContain("@select=\"emit('print', 'a4')\"")
        ->toContain("@select=\"emit('print', 'struk')\"");

    // Finance prints from the channel card; the POS from its recap modal.
    expect(recapFinancePage())
        ->toContain("import RecapPrintMenu from '@/components/demo/RecapPrintMenu.vue'")
        ->toContain('@print="printRecap"');

    expect(recapPosPage())
        ->toContain("import RecapPrintMenu from '@/components/demo/RecapPrintMenu.vue'")
        ->toContain('@print="printRecap"');
});

test('every value on the sheet comes from user input and none of it is raw', function () {
    expect(recapSheetModule())
        ->toContain('brandMark,')
        ->toContain('${brandMark(brand.photo, brand.logo, brand.name)}')
        ->toContain('.logo-image')
        ->toContain('${brandContacts(brand.whatsapp, brand.instagram)}')
        ->toContain('escapeHtml(sheet.title)')
        ->toContain('escapeHtml(row.label)')
        ->toContain('escapeHtml(value)')
        ->toContain('escapeHtml(brand.name)');
});

/** The body of one recap builder, so an assertion cannot drift into the page. */
function recapBuilderSource(string $page, string $signature): string
{
    $start = strpos($page, $signature);

    expect($start)->not->toBeFalse();

    $body = substr($page, $start);
    $end = strpos($body, "\n}\n");

    expect($end)->not->toBeFalse();

    return substr($body, 0, $end);
}
