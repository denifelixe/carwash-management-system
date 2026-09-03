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

function recapPrintDocumentModule(): string
{
    return file_get_contents(resource_path('js/lib/printDocument.ts'));
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
        ->toContain("label: 'Profit / Keuntungan',")
        ->toContain('formatCurrency(totalIn.value)')
        ->toContain('formatCurrency(totalOut.value)')
        ->toContain('formatCurrency(profit.value)')
        /*
         * The channel tables, straight off the rows the page renders. The cash
         * section is headed "Tunai" for what it holds: heading it "Kanal
         * Keuangan" only repeated the first column's own header underneath it.
         */
        ->toContain("heading: 'Tunai',")
        ->toContain('const cash = cashChannelRow.value;')
        ->toContain("'Pemasukan',")
        ->toContain("'Pengeluaran',")
        ->toContain("'Profit/Keuntungan Kanal',")
        // Non-cash prints as its own section, merged into a footed total.
        ->toContain("heading: 'Non-Tunai',")
        ->toContain('const nonCash = nonCashChannelRows.value;')
        ->toContain('const nonCashTotal = nonCashTotals.value;')
        ->toContain("label: 'Total Non-Tunai',");
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

    expect(recapPrintDocumentModule())
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

function recapPdfModule(): string
{
    return file_get_contents(resource_path('js/lib/recapSheetPdf.ts'));
}

/*
 * The recap's toolbar was two coloured blocks and offered no way to save a file
 * except the browser's print prompt, which the slip had already grown out of.
 */
test('the recap toolbar is the same segmented bar the slip carries', function () {
    expect(recapSheetModule())
        ->toContain('${toolbarStyles()}')
        ->toContain("toolbarButton('data-recap-print', toolbarIcons.print, 'Cetak', 'primary')")
        ->toContain("toolbarButton('data-recap-download', toolbarIcons.download, 'Unduh PDF')")
        ->toContain("toolbarButton('data-recap-close', toolbarIcons.close, 'Tutup')")
        ->toContain('${toastMarkup()}')
        // Neither the bar nor the toast belongs on the paper.
        ->toContain("@media print {\n    .toolbar, .toast { display: none; }")
        // The old flat blue and slate blocks are gone.
        ->not->toContain('#0284c7')
        ->not->toContain('class="secondary"');

    // One copy of the bar, shared by both documents, beside the one escaper.
    expect(file_get_contents(resource_path('js/lib/printDocument.ts')))
        ->toContain('export function toolbarStyles(): string')
        ->toContain('export function toolbarButton(')
        ->toContain('export const toolbarIcons = {');
});

test('the recap downloads as a PDF without going through the print prompt', function () {
    expect(recapSheetModule())
        // Fetched on the click so Finance and the POS never carry jsPDF.
        ->toContain("await import('@/lib/recapSheetPdf')")
        ->toContain('await downloadRecapSheetPdf(recapWindow, sheet, brand, paper)')
        // The button is held while the file is built, then handed back.
        ->toContain('downloadButton.disabled = true')
        ->toContain("downloadLabel.textContent = 'Membuat…'")
        ->toContain("showToast('PDF gagal dibuat', 'error')")
        /*
         * Read from the document, never off the event: the handler awaits the
         * writer, and by then the click has finished dispatching.
         */
        ->toContain("recapWindow.document.querySelector<HTMLButtonElement>(\n            '[data-recap-download]',\n        )")
        ->not->toContain('event.currentTarget as');

    expect(recapPdfModule())
        ->toContain('export async function downloadRecapSheetPdf(')
        ->toContain('export function renderRecapSheetPdf(')
        ->toContain('recapFileName(sheet)')
        ->toContain('return pdfFileName(`${sheet.title} ${sheet.periodLabel}`);');
});

/*
 * The file follows the paper its window was opened for, and both come off one
 * set of block writers — the same guarantee recapSheetStyles() gives the HTML,
 * so a figure cannot land on A4 and go missing on the roll.
 */
test('one set of block writers serves both papers in the PDF', function () {
    expect(recapPdfModule())
        ->toContain('const metrics = paper === \'a4\' ? A4 : ROLL;')
        ->toContain("const A4: RecapMetrics = {\n    width: 210,\n    height: 297,")
        ->toContain("const ROLL: RecapMetrics = {\n    width: 78,")
        ->toContain("font: 'helvetica'")
        ->toContain("font: 'courier'")
        // Every block is written once and reads its paper from the metrics.
        ->toContain('brandHeader(cursor, metrics, brand, art.logo);')
        ->toContain('headingBlock(cursor, metrics, sheet);')
        ->toContain('summaryBlock(cursor, metrics, sheet);')
        ->toContain('tableBlock(cursor, metrics, table);')
        ->toContain('footerBlock(cursor, metrics, sheet);')
        // Columns on the sheet, stacked cells on the roll, one row writer each.
        ->toContain('const writeRow = metrics.tabular ? tabularRow : stackedRow;');
});

/*
 * A4 is a fixed page, unlike the roll: a recap with more channels than fit has
 * to break, and the table it breaks in the middle of has to say what its
 * columns are on the page it continues onto.
 */
test('the A4 recap paginates and repeats its table header, the roll runs on', function () {
    expect(recapPdfModule())
        ->toContain('if (cursor.ensureRoom(height + 4)) {')
        ->toContain('tableHeader(cursor, metrics, table);')
        // A row of summary cards is kept whole rather than split.
        ->toContain('cursor.ensureRoom(height);')
        // The roll has no height, so it is cut to what the layout filled.
        ->toContain('metrics.height === undefined')
        ->toContain('format: [metrics.width, 2000]');

    expect(file_get_contents(resource_path('js/lib/pdfDocument.ts')))
        ->toContain('ensureRoom(needed: number): boolean {')
        ->toContain('this.doc.addPage();')
        // A roll never breaks.
        ->toContain("if (this.page.height === undefined) {\n            return false;\n        }");
});

test('the recap PDF lays out the figures the page already formatted', function () {
    expect(recapPdfModule())
        // No currency or date logic here: the sheet arrives spelled out.
        ->not->toContain('formatCurrency')
        ->not->toContain('formatDate')
        ->toContain('cursor.meta(entry.label, entry.value, metrics.metaWidth)')
        ->toContain('cursor.paragraph(table.emptyMessage ?? ')
        // Green and red carry over from the cards on screen.
        ->toContain('const POSITIVE: [number, number, number] = [4, 120, 87];')
        ->toContain('const NEGATIVE: [number, number, number] = [190, 18, 60];');
});

/*
 * A recap has no public page behind it the way a receipt does — its figures are
 * the outlet's takings — so the address it carries is a deep link into the
 * console, which still asks whoever follows it to log in.
 */
test('the recap carries a deep link back to the day and shift it was taken from', function () {
    expect(recapSheetModule())
        ->toContain('sourceUrl: string | null;')
        ->toContain('qrUrl: string | null;')
        // Wayfinder hands back a protocol-relative URL; about:blank has no scheme.
        ->toContain('export function absoluteUrl(url: string): string')
        ->toContain("url.startsWith('//')");

    foreach ([recapFinancePage(), recapPosPage()] as $page) {
        expect($page)
            ->toContain("import RecapQrController from '@/actions/App/Http/Controllers/Admin/RecapQrController'")
            ->toContain('sourceUrl: absoluteUrl(')
            ->toContain('qrUrl: absoluteUrl(')
            // The tab is client state, so the link reads it back off the query.
            ->toContain("new URLSearchParams(window.location.search).get('shift')");
    }

    expect(recapFinancePage())
        ->toContain('const activeShift = ref<Shift>(allShiftsKey);')
        ->toContain("onMounted(() => {\n    activeShift.value =");

    expect(recapPosPage())
        ->toContain('const activePaymentRecapShift = ref<PaymentRecapShift>(paymentRecapTotalKey);')
        ->toContain("onMounted(() => {\n    activePaymentRecapShift.value =");
});

test('the recap toolbar can copy that link, and says so', function () {
    expect(recapSheetModule())
        ->toContain("toolbarButton('data-recap-copy', toolbarIcons.link, 'Salin Link', '', sheet.sourceUrl === null)")
        ->toContain('await copyToClipboard(recapWindow, sheet.sourceUrl)')
        ->toContain("showToast('Link rekap disalin')")
        ->toContain("showToast('Link gagal disalin', 'error')")
        // Held rather than read off the event, which is null after the await.
        ->toContain("const copyButton =\n        recapWindow.document.querySelector<HTMLButtonElement>(\n            '[data-recap-copy]',\n        )");

    /*
     * One clipboard helper for both documents: it has to stand up to an outlet
     * served over plain http, where navigator.clipboard does not exist at all.
     */
    expect(file_get_contents(resource_path('js/lib/printDocument.ts')))
        ->toContain('export async function copyToClipboard(')
        ->toContain('documentWindow.isSecureContext && documentWindow.navigator.clipboard')
        ->toContain("documentWindow.document.execCommand('copy')");

    expect(file_get_contents(resource_path('js/lib/posReceipt.ts')))
        ->toContain('await copyToClipboard(receiptWindow, receipt.publicUrl)')
        ->not->toContain('function copyReceiptLink(');
});

/*
 * The screen has the Salin Link button right there; paper and a filed PDF have
 * no button to press, so they carry the address itself.
 */
test('the QR and the link are printed and filed, not shown on screen', function () {
    expect(recapSheetModule())
        ->toContain('function verificationBlock(sheet: RecapSheet): string')
        ->toContain('Pindai untuk membuka rekap ini')
        ->toContain('class="verification-qr-image" src="${escapeHtml(sheet.qrUrl)}"')
        // Hidden by stylesheet, so a plain Ctrl+P still prints it.
        ->toContain('.verification { display: none; text-align: center; }')
        ->toContain('.verification { display: block; }');

    expect(recapPdfModule())
        ->toContain('function verificationBlock(')
        ->toContain('Pindai atau buka rekap ini di konsol:')
        // Placed by hand: textWithLink measures its box off the wrong baseline.
        ->toContain('cursor.doc.link(left, cursor.y, width, step, { url: sheet.sourceUrl })')
        ->not->toContain('doc.textWithLink(')
        // The console renders the code; jsPDF cannot read the SVG it sends.
        ->toContain('rasterize(recapWindow, sheet.qrUrl)');
});

/*
 * Non-cash outgoings are booked against the section, not the channel that took
 * the money, so the screen spans one figure down the rows. The printed sheet
 * now does the same instead of repeating an em dash on every row.
 */
test('a merged figure spans its rows on the sheet and is footed on the roll', function () {
    expect(recapSheetModule())
        ->toContain('export interface RecapTableMerge {')
        ->toContain('values: Array<RecapMergedValue | null>;')
        ->toContain('merge?: RecapTableMerge;')
        // Written once, by the row it starts on, and spanning the rest.
        ->toContain('class="value cell-span${toneClass(merged.tone)}" rowspan="${span}"')
        // Each paper shows one of the two readings, never both.
        ->toContain('.cell-span { border-left: 1px solid #e2e8f0; vertical-align: middle; }')
        ->toContain('.row.merged { display: none; }')
        /*
         * Hiding it takes the row's own specificity: '.row > td { display:
         * flex }' otherwise keeps the spanning cell on the roll, where it read
         * as two unlabelled figures under the first channel.
         */
        ->toContain('.row > .cell-span { display: none; }')
        // The roll writes a footed row like any other block: no rule, no bold.
        ->not->toContain('.row.total, .row.merged { border-top')
        ->toContain("table.merge === undefined ? 'total' : 'merged',");

    $sheet = recapBuilderSource(recapFinancePage(), 'function financeRecapSheet(): RecapSheet {');

    expect($sheet)
        // Only the income is per channel; the em-dash placeholders are gone.
        ->toContain('values: [formatCurrency(channel.income)],')
        ->toContain("tones: ['positive' as const],")
        ->not->toContain("'—', '—'")
        // Both readings come off the same figures, so they cannot drift.
        ->toContain("merge: {\n                    label: 'Total Non-Tunai',")
        ->toContain('value: formatCurrency(nonCashTotal.expense),')
        ->toContain('value: formatCurrency(nonCashTotal.balance),');
});

test('the recap PDF spans the merge on A4 and foots it on the roll', function () {
    expect(recapPdfModule())
        ->toContain('function mergedCells(')
        // Centred down the block it spans, and ruled off from the row columns.
        ->toContain('cursor.doc.line(edges[index + 1], top, edges[index + 1], cursor.y);')
        // A table that breaks closes the span and opens a new one overleaf.
        ->toContain('if (metrics.tabular && table.merge !== undefined && cursor.y < before)')
        // Restating a spanned figure underneath would say it twice.
        ->toContain('if (table.total !== undefined && !(metrics.tabular && table.merge))')
        // The roll skips the merged columns per row and foots them once.
        ->toContain('if (!isTotal && table.merge?.values[index] != null)')
        /*
         * A footed figure reads alongside the per-channel ones rather than
         * louder than them, the way the cash section already does.
         */
        ->toContain('cursor.row(`  ${heading}`, value, { color: toneColor(tone) });');
});

/*
 * The roll's closing figures used to be ruled off and bolded, which made them
 * read louder than the cash section right above them.
 */
test('a footed row on the roll is written like every other block', function () {
    expect(recapPdfModule())
        ->toContain('cursor.paragraph(row.label,')
        // No rule drawn above it, and no weight the channels above do not have.
        ->not->toContain('if (isTotal) {\n        cursor.gap(1);\n        cursor.line(false);');
});

/*
 * The balance is an accumulation, so a sheet carrying only the closing figure
 * would not say what the day actually moved: the opening one is printed first.
 */
test('the recap prints the balance the day opened from and the one it closed on', function () {
    $sheet = recapBuilderSource(recapFinancePage(), 'function financeRecapSheet(): RecapSheet {');

    expect($sheet)
        ->toContain("heading: 'Saldo Kas',")
        ->toContain('caption: balanceCaption.value,')
        // Both channels and what they come to together.
        ->toContain("columns: ['Saldo', 'Tunai', 'Non-Tunai', 'Total Saldo'],")
        // Yesterday first, then today, in that order.
        ->toContain("balanceRow(\n                        props.dailyBalance.previous.date,")
        ->toContain("balanceRow(\n                        props.filters.date,");

    expect(recapFinancePage())
        ->toContain('function balanceRow(
    date: string,
    cash: number,
    nonCash: number,
): RecapTableRow')
        ->toContain('label: `Saldo ${formatDate(date)}`')
        // Red where it is negative, the way the card reads on screen.
        ->toContain("cash < 0 ? ('negative' as const) : ('default' as const),")
        // What the outlet holds across both channels, on the sheet…
        ->toContain('const total = cash + nonCash;')
        // …and on the card the recap is taken from.
        ->toContain('const dailyBalanceTotal = computed<number>(')
        ->toContain('props.dailyBalance.cash + props.dailyBalance.nonCash,')
        ->toContain('>Total Saldo</span');
});
