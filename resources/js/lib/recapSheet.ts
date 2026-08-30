import {
    brandContacts,
    brandMark,
    escapeHtml,
    printedAt,
} from '@/lib/printDocument';
import type { CarwashBrand } from '@/types/demo';

/**
 * Shift recap rendered outside the SPA.
 *
 * Keuangan and Kasir POS both close a shift by handing over the same thing: a
 * few totals and one or two tables of channels. That is all this document
 * carries — never the transaction list behind it — so one module prints both,
 * on either the A4 the office files or the 78mm roll the cashier station is
 * already loaded with.
 *
 * Every figure arrives pre-formatted from the page, because the page is what
 * knows how it is spelled on screen and the printout must agree with it.
 */

/** Printable width of the 80mm roll, the same one the POS slip is laid out at. */
const PAPER_WIDTH = '78mm';

/** Wide enough for the browser's print prompt, which is drawn inside the frame. */
const WINDOW_WIDTHS: Record<RecapPaper, number> = {
    a4: 860,
    struk: 520,
};

const WINDOW_HEIGHT = 820;

export type RecapPaper = 'a4' | 'struk';

/** Pushes a figure green or red, the way the cards read on screen. */
export type RecapTone = 'default' | 'positive' | 'negative';

export interface RecapSummaryItem {
    label: string;
    /** Already formatted, e.g. "Rp 1.850.000". */
    value: string;
    caption?: string;
    tone?: RecapTone;
}

export interface RecapTableRow {
    label: string;
    /** Already formatted, one per column after the label. */
    values: string[];
    tones?: RecapTone[];
}

export interface RecapTable {
    heading: string;
    caption?: string;
    /** The label column first, then one heading per value. */
    columns: string[];
    rows: RecapTableRow[];
    /** Footed total, so a printed sheet adds up without a calculator. */
    total?: RecapTableRow;
    emptyMessage?: string;
}

export interface RecapSheet {
    title: string;
    /** Window name and document title stem, e.g. 'rekap-keuangan'. */
    slug: string;
    /** The day the recap covers, spelled the way the modules spell a date. */
    periodLabel: string;
    /** The shift tab the recap was taken from, including 'Tanpa Shift'. */
    shiftLabel: string;
    shiftCaption: string | null;
    meta: Array<{ label: string; value: string }>;
    summary: RecapSummaryItem[];
    tables: RecapTable[];
    /** The outlet's zone, so the print stamp reads the shop clock. */
    timezone: string;
}

function toneClass(tone: RecapTone | undefined): string {
    if (tone === 'positive') {
        return ' positive';
    }

    if (tone === 'negative') {
        return ' negative';
    }

    return '';
}

function metaRow(label: string, value: string): string {
    return `<div class="meta"><span>${escapeHtml(label)}</span><span>${escapeHtml(value)}</span></div>`;
}

function summaryItem(item: RecapSummaryItem): string {
    const caption =
        item.caption === undefined
            ? ''
            : `<p class="summary-caption">${escapeHtml(item.caption)}</p>`;

    return `<div class="summary-item">
    <p class="summary-label">${escapeHtml(item.label)}</p>
    <p class="summary-value${toneClass(item.tone)}">${escapeHtml(item.value)}</p>
    ${caption}
</div>`;
}

function summaryBlock(sheet: RecapSheet): string {
    if (sheet.summary.length === 0) {
        return '';
    }

    return `<section class="block summary">${sheet.summary.map(summaryItem).join('')}</section>`;
}

/**
 * One row, laid out twice over. The A4 sheet reads it as table cells; the roll
 * is too narrow for columns, so the same cells carry their own heading and the
 * stylesheet stacks them instead of the row being rendered a second time.
 */
function tableRow(
    row: RecapTableRow,
    columns: string[],
    modifier: '' | 'total' = '',
): string {
    const cells = row.values
        .map((value, index) => {
            const heading = escapeHtml(columns[index + 1] ?? '');
            const tone = toneClass(row.tones?.[index]);

            return `<td class="value${tone}"><span class="cell-label">${heading}</span><span class="cell-value">${escapeHtml(value)}</span></td>`;
        })
        .join('');

    const className = modifier === '' ? 'row' : `row ${modifier}`;

    return `<tr class="${className}"><th scope="row">${escapeHtml(row.label)}</th>${cells}</tr>`;
}

function tableBlock(table: RecapTable): string {
    const caption =
        table.caption === undefined
            ? ''
            : `<p class="caption">${escapeHtml(table.caption)}</p>`;

    if (table.rows.length === 0) {
        const empty = table.emptyMessage ?? 'Belum ada data pada periode ini.';

        return `<section class="block">
    <p class="heading">${escapeHtml(table.heading)}</p>
    ${caption}
    <p class="empty">${escapeHtml(empty)}</p>
</section>`;
    }

    const headings = table.columns
        .map(
            (column, index) =>
                `<th scope="col"${index === 0 ? '' : ' class="value"'}>${escapeHtml(column)}</th>`,
        )
        .join('');

    const rows = table.rows.map((row) => tableRow(row, table.columns)).join('');

    const total =
        table.total === undefined
            ? ''
            : `<tfoot>${tableRow(table.total, table.columns, 'total')}</tfoot>`;

    return `<section class="block">
    <p class="heading">${escapeHtml(table.heading)}</p>
    ${caption}
    <table>
        <thead><tr>${headings}</tr></thead>
        <tbody>${rows}</tbody>
        ${total}
    </table>
</section>`;
}

function recapSheetBody(sheet: RecapSheet, brand: CarwashBrand): string {
    const shiftCaption =
        sheet.shiftCaption === null
            ? ''
            : `<p class="shift-caption">${escapeHtml(sheet.shiftCaption)}</p>`;

    return `<header class="brand">
    ${brandMark(brand.photo, brand.logo, brand.name)}
    <p class="name">${escapeHtml(brand.name)}</p>
    ${brandContacts(brand.whatsapp, brand.instagram)}
</header>
<section class="block">
    <p class="title">${escapeHtml(sheet.title)}</p>
    <p class="shift">${escapeHtml(sheet.shiftLabel)}</p>
    ${shiftCaption}
    ${metaRow('Periode', sheet.periodLabel)}
    ${sheet.meta.map((entry) => metaRow(entry.label, entry.value)).join('')}
</section>
${summaryBlock(sheet)}
${sheet.tables.map(tableBlock).join('')}
<footer class="footer">
    <p>Dicetak ${escapeHtml(printedAt(sheet.timezone))}.</p>
    <p class="fineprint">Rekap internal untuk serah terima kas, bukan bukti pembayaran.</p>
</footer>`;
}

function sharedStyles(): string {
    return `:root { color-scheme: light; }
* { box-sizing: border-box; margin: 0; padding: 0; }
.contacts { align-items: center; display: flex; flex-direction: column; gap: 1px; margin-top: 2px; }
.contact { align-items: center; color: #475569; display: inline-flex; gap: 4px; }
.contact-icon { display: inline-flex; height: 1em; width: 1em; }
.contact-icon svg { height: 100%; width: 100%; }
.contact-icon.whatsapp { color: #16a34a; }
.contact-icon.instagram { color: #c026d3; }
.toolbar { display: flex; gap: 6px; margin: 0 auto 12px; }
.toolbar button {
    background: #0284c7;
    border: 0;
    border-radius: 6px;
    color: #ffffff;
    cursor: pointer;
    flex: 1;
    font: inherit;
    font-weight: 700;
    padding: 8px 0;
}
.toolbar button.secondary { background: #475569; }
.paper { background: #ffffff; margin: 0 auto; }
.brand { text-align: center; }
.title { font-weight: 700; letter-spacing: 0.08em; text-transform: uppercase; }
.heading { font-weight: 700; letter-spacing: 0.06em; text-transform: uppercase; }
table { border-collapse: collapse; width: 100%; }
/* Long channel names must wrap, never widen the sheet. */
.meta, .summary-item, th, td { overflow-wrap: anywhere; }
.value { font-variant-numeric: tabular-nums; }
.positive { color: #047857; }
.negative { color: #be123c; }
.empty { color: #64748b; }
@media print { .toolbar { display: none; } }`;
}

function a4Styles(): string {
    return `body {
    background: #e2e8f0;
    color: #0f172a;
    font-family: 'Segoe UI', system-ui, -apple-system, sans-serif;
    font-size: 12px;
    line-height: 1.5;
    padding: 16px 0 32px;
}
.toolbar { width: 180mm; }
.paper { box-shadow: 0 8px 24px rgba(15, 23, 42, 0.18); padding: 14mm; width: 180mm; }
.logo { font-size: 26px; line-height: 1.2; }
.logo-image { display: block; height: auto; margin: 0 auto 6px; max-height: 64px; max-width: 90px; object-fit: contain; }
.name { font-size: 17px; font-weight: 700; letter-spacing: 0.04em; }
.contact { color: #475569; font-size: 11px; }
.block { border-top: 1px solid #cbd5e1; margin-top: 14px; padding-top: 14px; }
.title { font-size: 15px; margin-bottom: 6px; }
.shift { font-size: 13px; font-weight: 600; }
.shift-caption { color: #64748b; font-size: 11px; margin-bottom: 6px; }
.heading { font-size: 12px; margin-bottom: 2px; }
.caption { color: #64748b; font-size: 11px; margin-bottom: 8px; }
.meta { display: flex; gap: 12px; justify-content: space-between; max-width: 90mm; }
.meta > span:first-child { color: #475569; }
.summary { display: grid; gap: 10px; grid-template-columns: repeat(3, minmax(0, 1fr)); }
.summary-item { border: 1px solid #e2e8f0; border-radius: 8px; padding: 10px 12px; }
.summary-label { color: #475569; font-size: 11px; }
.summary-value { font-size: 18px; font-weight: 700; font-variant-numeric: tabular-nums; margin-top: 2px; }
.summary-caption { color: #64748b; font-size: 10px; margin-top: 2px; }
thead th {
    border-bottom: 1px solid #94a3b8;
    color: #475569;
    font-size: 10px;
    letter-spacing: 0.06em;
    padding: 6px 8px;
    text-align: left;
    text-transform: uppercase;
}
thead th.value, tbody td, tfoot td { text-align: right; }
tbody th, tfoot th { font-weight: 500; text-align: left; }
tbody th, tbody td, tfoot th, tfoot td { border-bottom: 1px solid #e2e8f0; padding: 7px 8px; }
tfoot th, tfoot td { border-bottom: 0; border-top: 1px solid #0f172a; font-weight: 700; }
/* The per-cell headings are for the roll, which cannot hold columns. */
.cell-label { display: none; }
.footer { border-top: 1px solid #cbd5e1; color: #64748b; font-size: 10px; margin-top: 14px; padding-top: 10px; }
.fineprint { margin-top: 2px; }
@page { margin: 14mm; size: A4; }
@media print {
    body { background: #ffffff; padding: 0; }
    .paper { box-shadow: none; padding: 0; width: auto; }
}`;
}

function strukStyles(): string {
    return `body {
    background: #e2e8f0;
    color: #0f172a;
    font-family: 'Consolas', 'Courier New', ui-monospace, monospace;
    font-size: 11px;
    line-height: 1.45;
    padding: 12px 0 28px;
}
.toolbar { width: ${PAPER_WIDTH}; }
.paper { box-shadow: 0 8px 24px rgba(15, 23, 42, 0.18); padding: 14px 10px 18px; width: ${PAPER_WIDTH}; }
.logo { font-size: 20px; line-height: 1.2; }
.logo-image { display: block; height: auto; margin: 0 auto 4px; max-height: 40px; max-width: 56px; object-fit: contain; }
.name { font-size: 13px; font-weight: 700; letter-spacing: 0.04em; }
.contact { color: #475569; font-size: 10px; }
.block { border-top: 1px dashed #94a3b8; margin-top: 8px; padding-top: 8px; }
.title { margin-bottom: 4px; text-align: center; }
.shift { font-weight: 700; text-align: center; }
.shift-caption { color: #475569; font-size: 10px; margin-bottom: 4px; text-align: center; }
.heading { margin-bottom: 3px; }
.caption { color: #64748b; font-size: 9px; margin-bottom: 4px; }
.meta { display: flex; gap: 8px; justify-content: space-between; }
.meta > span:first-child { color: #475569; flex: 0 0 auto; }
.meta > span:last-child { text-align: right; }
.summary-item { display: flex; flex-wrap: wrap; gap: 8px; justify-content: space-between; }
.summary-item + .summary-item { margin-top: 3px; }
.summary-label { color: #475569; flex: 1 1 auto; }
.summary-value { font-weight: 700; font-variant-numeric: tabular-nums; text-align: right; }
.summary-caption { color: #64748b; flex: 1 0 100%; font-size: 9px; }
/* The roll is too narrow for columns, so each row stacks its own headings. */
thead { display: none; }
table, tbody, tfoot, tr, th, td { display: block; }
.row { border-top: 1px dotted #cbd5e1; margin-top: 4px; padding-top: 4px; }
tbody .row:first-child { border-top: 0; margin-top: 0; padding-top: 0; }
.row > th { font-weight: 700; text-align: left; }
.row > td { display: flex; gap: 8px; justify-content: space-between; padding-left: 6px; }
.cell-label { color: #475569; flex: 0 0 auto; }
.cell-value { text-align: right; }
.row.total { border-top: 1px solid #0f172a; font-weight: 700; margin-top: 5px; padding-top: 5px; }
.row.total .cell-label { color: #0f172a; }
.footer { border-top: 1px dashed #94a3b8; margin-top: 8px; padding-top: 8px; text-align: center; }
.fineprint { color: #64748b; font-size: 9px; margin-top: 4px; }
@page { margin: 0; size: ${PAPER_WIDTH} auto; }
@media print {
    body { background: #ffffff; padding: 0; }
    .paper { box-shadow: none; padding: 0 3mm 6mm; }
}`;
}

function recapSheetStyles(paper: RecapPaper): string {
    return `${sharedStyles()}
${paper === 'a4' ? a4Styles() : strukStyles()}`;
}

/**
 * Full standalone document for the recap. The body is the same for both papers
 * and the stylesheet is what turns it into a filed sheet or a till roll, so a
 * figure can never appear on one and go missing on the other.
 */
export function renderRecapSheetDocument(
    sheet: RecapSheet,
    brand: CarwashBrand,
    paper: RecapPaper,
): string {
    return `<!DOCTYPE html>
<html lang="id">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<meta name="color-scheme" content="light">
<title>${escapeHtml(sheet.title)} ${escapeHtml(sheet.periodLabel)} — ${escapeHtml(brand.name)}</title>
<style>${recapSheetStyles(paper)}</style>
</head>
<body>
<div class="toolbar">
    <button type="button" data-recap-print>Cetak</button>
    <button type="button" class="secondary" data-recap-close>Tutup</button>
</div>
<main class="paper">${recapSheetBody(sheet, brand)}</main>
</body>
</html>`;
}

/**
 * Opens the recap in its own window, sized to the paper it was asked for.
 * Handlers are wired from here rather than an inline script so the written
 * document stays free of executable markup. Returns `null` when the browser
 * blocks the window, so the page can say so instead of looking broken.
 */
export function openRecapSheetWindow(
    sheet: RecapSheet,
    brand: CarwashBrand,
    paper: RecapPaper,
): Window | null {
    const recapWindow = window.open(
        '',
        `${sheet.slug}-${paper}`,
        `popup=yes,width=${WINDOW_WIDTHS[paper]},height=${WINDOW_HEIGHT},scrollbars=yes,resizable=yes`,
    );

    if (!recapWindow) {
        return null;
    }

    recapWindow.document.open();
    recapWindow.document.write(renderRecapSheetDocument(sheet, brand, paper));
    recapWindow.document.close();

    recapWindow.document
        .querySelector('[data-recap-print]')
        ?.addEventListener('click', () => recapWindow.print());
    recapWindow.document
        .querySelector('[data-recap-close]')
        ?.addEventListener('click', () => recapWindow.close());

    recapWindow.focus();

    return recapWindow;
}
