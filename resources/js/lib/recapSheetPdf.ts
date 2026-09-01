import { jsPDF } from 'jspdf';

import {
    brandArtwork,
    drawBrandMark,
    INK,
    lineHeight,
    LINK,
    MUTED,
    PdfCursor,
    pdfFileName,
    PT_TO_MM,
    rasterize,
    pdfText,
    RULE,
} from '@/lib/pdfDocument';
import type { PageMetrics, RasterImage } from '@/lib/pdfDocument';
import { formatWhatsapp, printedAt } from '@/lib/printDocument';
import type {
    RecapPaper,
    RecapSheet,
    RecapTable,
    RecapTableMerge,
    RecapTableRow,
    RecapTone,
} from '@/lib/recapSheet';
import type { CarwashBrand } from '@/types/demo';

/**
 * The shift recap as a real PDF.
 *
 * Drawn from the `RecapSheet` rather than snapshotted off the rendered HTML, so
 * the figures stay text: the office can select a total out of the archive copy
 * and search it. Every value on the sheet arrives pre-formatted from the page,
 * exactly as the HTML renderer receives it, so this module only lays out.
 *
 * One set of block writers serves both papers, driven by the metrics below —
 * the same guarantee recapSheetStyles() gives the HTML by swapping stylesheets
 * over one body: a figure cannot land on A4 and go missing on the roll.
 */

/** What the two papers disagree on, beyond the page itself. */
interface RecapMetrics extends PageMetrics {
    titleSize: number;
    headingSize: number;
    fineSize: number;
    summaryValueSize: number;
    logoHeight: number;
    logoWidth: number;
    /** A4 rules real table columns; the roll is too narrow and stacks them. */
    tabular: boolean;
    /** How wide the meta pairs are allowed to spread. */
    metaWidth: number;
}

const A4: RecapMetrics = {
    width: 210,
    height: 297,
    margin: 14,
    bodySize: 9,
    font: 'helvetica',
    dashedRules: false,
    blockGap: 4,
    titleSize: 12,
    headingSize: 9.5,
    fineSize: 7.5,
    summaryValueSize: 14,
    logoHeight: 16,
    logoWidth: 24,
    tabular: true,
    metaWidth: 90,
};

const ROLL: RecapMetrics = {
    width: 78,
    margin: 4,
    bodySize: 8,
    font: 'courier',
    dashedRules: true,
    blockGap: 1.8,
    titleSize: 8,
    headingSize: 8,
    fineSize: 6.5,
    summaryValueSize: 8,
    logoHeight: 10,
    logoWidth: 14,
    tabular: false,
    metaWidth: 70,
};

const POSITIVE: [number, number, number] = [4, 120, 87];

const NEGATIVE: [number, number, number] = [190, 18, 60];

/** Width a value column is given on A4, the rest going to the label. */
const VALUE_COLUMN = 30;

function toneColor(tone: RecapTone | undefined): [number, number, number] {
    if (tone === 'positive') {
        return POSITIVE;
    }

    if (tone === 'negative') {
        return NEGATIVE;
    }

    return INK;
}

function brandHeader(
    cursor: PdfCursor,
    metrics: RecapMetrics,
    brand: CarwashBrand,
    logo: RasterImage | null,
): void {
    drawBrandMark(cursor, logo, metrics.logoHeight, metrics.logoWidth);

    cursor.paragraph(brand.name, 'center', {
        size: metrics.titleSize + 2,
        bold: true,
    });
    cursor.paragraph(formatWhatsapp(brand.whatsapp), 'center', {
        size: metrics.fineSize,
        color: MUTED,
    });
    cursor.paragraph(`@${brand.instagram}`, 'center', {
        size: metrics.fineSize,
        color: MUTED,
    });
}

function headingBlock(
    cursor: PdfCursor,
    metrics: RecapMetrics,
    sheet: RecapSheet,
): void {
    /* Centred on the roll, ranged left on the sheet, as the stylesheets have it. */
    const align = metrics.tabular ? 'left' : 'center';

    cursor.block();
    cursor.paragraph(sheet.title.toUpperCase(), align, {
        size: metrics.titleSize,
        bold: true,
    });
    cursor.paragraph(sheet.shiftLabel, align, { bold: true });

    if (sheet.shiftCaption !== null) {
        cursor.paragraph(sheet.shiftCaption, align, {
            size: metrics.fineSize,
            color: MUTED,
        });
    }

    cursor.gap();
    cursor.meta('Periode', sheet.periodLabel, metrics.metaWidth);

    for (const entry of sheet.meta) {
        cursor.meta(entry.label, entry.value, metrics.metaWidth);
    }
}

/**
 * Three bordered cards across on A4, the way the totals read on screen; the
 * roll has no room for them and stacks the same figures as label/value rows.
 */
function summaryBlock(
    cursor: PdfCursor,
    metrics: RecapMetrics,
    sheet: RecapSheet,
): void {
    if (sheet.summary.length === 0) {
        return;
    }

    cursor.block();

    if (!metrics.tabular) {
        for (const item of sheet.summary) {
            cursor.row(item.label, item.value, {
                bold: true,
                color: toneColor(item.tone),
            });

            if (item.caption !== undefined) {
                cursor.paragraph(item.caption, 'left', {
                    size: metrics.fineSize,
                    color: MUTED,
                });
            }
        }

        return;
    }

    const gap = 3;
    const perRow = 3;
    const cardWidth = (cursor.contentWidth - gap * (perRow - 1)) / perRow;
    const height =
        lineHeight(metrics.fineSize) * 2 +
        lineHeight(metrics.summaryValueSize) +
        5;

    for (let index = 0; index < sheet.summary.length; index += perRow) {
        const cards = sheet.summary.slice(index, index + perRow);

        /* A row of cards is kept whole rather than split across two pages. */
        cursor.ensureRoom(height);

        const top = cursor.y;

        cards.forEach((item, column) => {
            const left = cursor.page.margin + column * (cardWidth + gap);

            cursor.doc.setDrawColor(226, 232, 240);
            cursor.doc.setLineWidth(0.2);
            cursor.doc.roundedRect(left, top, cardWidth, height, 1.5, 1.5, 'S');

            cursor.apply({ size: metrics.fineSize, color: MUTED });
            cursor.doc.text(pdfText(item.label), left + 2.5, top + 2.5, {
                baseline: 'top',
            });

            cursor.apply({
                size: metrics.summaryValueSize,
                bold: true,
                color: toneColor(item.tone),
            });
            cursor.doc.text(
                pdfText(item.value),
                left + 2.5,
                top + 2.5 + lineHeight(metrics.fineSize),
                { baseline: 'top' },
            );

            if (item.caption === undefined) {
                return;
            }

            cursor.apply({ size: metrics.fineSize, color: MUTED });

            const [caption] = cursor.doc.splitTextToSize(
                pdfText(item.caption),
                cardWidth - 5,
            );

            cursor.doc.text(
                caption,
                left + 2.5,
                top +
                    2.5 +
                    lineHeight(metrics.fineSize) +
                    lineHeight(metrics.summaryValueSize),
                { baseline: 'top' },
            );
        });

        cursor.y = top + height + gap;
    }
}

/** Left edge of every column: the label takes whatever the figures leave. */
function columnEdges(cursor: PdfCursor, table: RecapTable): number[] {
    const valueCount = table.columns.length - 1;
    const labelWidth = cursor.contentWidth - VALUE_COLUMN * valueCount;
    const edges = [cursor.page.margin];

    for (let index = 0; index < valueCount; index += 1) {
        edges.push(cursor.page.margin + labelWidth + VALUE_COLUMN * index);
    }

    return edges;
}

function tableHeader(
    cursor: PdfCursor,
    metrics: RecapMetrics,
    table: RecapTable,
): void {
    const edges = columnEdges(cursor, table);

    cursor.apply({ size: metrics.fineSize, color: MUTED });
    table.columns.forEach((column, index) => {
        const isValue = index > 0;

        cursor.doc.text(
            pdfText(column.toUpperCase()),
            isValue ? edges[index] + VALUE_COLUMN : edges[index],
            cursor.y,
            { align: isValue ? 'right' : 'left', baseline: 'top' },
        );
    });

    cursor.y += lineHeight(metrics.fineSize) + 1.5;
    cursor.doc.setDrawColor(...RULE);
    cursor.doc.setLineWidth(0.2);
    cursor.doc.line(cursor.page.margin, cursor.y, cursor.rightEdge, cursor.y);
    cursor.y += 1.5;
}

function tabularRow(
    cursor: PdfCursor,
    metrics: RecapMetrics,
    table: RecapTable,
    row: RecapTableRow,
    isTotal: boolean,
): void {
    const edges = columnEdges(cursor, table);
    const labelWidth = edges[1] - edges[0] - 2;

    cursor.apply({ bold: isTotal });

    const labelLines = cursor.doc.splitTextToSize(
        pdfText(row.label),
        labelWidth,
    );
    const height = lineHeight(cursor.page.bodySize) * labelLines.length + 2.5;

    if (cursor.ensureRoom(height + 4)) {
        tableHeader(cursor, metrics, table);
    }

    if (isTotal) {
        cursor.doc.setDrawColor(...INK);
        cursor.doc.setLineWidth(0.3);
        cursor.doc.line(
            cursor.page.margin,
            cursor.y,
            cursor.rightEdge,
            cursor.y,
        );
        cursor.y += 1.5;
    }

    const top = cursor.y;

    cursor.apply({ bold: isTotal });
    labelLines.forEach((line: string, index: number) => {
        cursor.doc.text(
            line,
            edges[0],
            top + lineHeight(cursor.page.bodySize) * index,
            { baseline: 'top' },
        );
    });

    /*
     * A merged column is left blank here and drawn once for the whole block by
     * mergedCells() below, the way the screen spans it down the rows.
     */
    let filled = 0;

    table.columns.slice(1).forEach((heading, index) => {
        if (table.merge?.values[index] != null) {
            return;
        }

        const value = row.values[filled] ?? '';
        const tone = row.tones?.[filled];

        filled += 1;

        cursor.apply({ bold: isTotal, color: toneColor(tone) });
        cursor.doc.text(pdfText(value), edges[index + 1] + VALUE_COLUMN, top, {
            align: 'right',
            baseline: 'top',
        });
    });

    cursor.y = top + height;

    if (isTotal) {
        return;
    }

    cursor.doc.setDrawColor(226, 232, 240);
    cursor.doc.setLineWidth(0.2);
    cursor.doc.line(cursor.page.margin, cursor.y, cursor.rightEdge, cursor.y);
    cursor.y += 1;
}

/**
 * The figures that belong to the table rather than to any one row, centred
 * down the block they span and ruled off from the per-row columns — the same
 * cell the screen draws with a rowspan. Drawn after the rows because that is
 * when the block's height is known; a table that breaks draws them again on
 * the page it continues onto, over the part that landed there.
 */
function mergedCells(
    cursor: PdfCursor,
    table: RecapTable,
    merge: RecapTableMerge,
    top: number,
): void {
    const edges = columnEdges(cursor, table);
    const middle =
        top + (cursor.y - top - lineHeight(cursor.page.bodySize)) / 2;

    merge.values.forEach((merged, index) => {
        if (merged === null) {
            return;
        }

        cursor.doc.setDrawColor(226, 232, 240);
        cursor.doc.setLineWidth(0.2);
        cursor.doc.line(edges[index + 1], top, edges[index + 1], cursor.y);

        cursor.apply({ color: toneColor(merged.tone) });
        cursor.doc.text(
            pdfText(merged.value),
            edges[index + 1] + VALUE_COLUMN,
            middle,
            { align: 'right', baseline: 'top' },
        );
    });
}

/**
 * The roll cannot hold columns, so every cell carries its own heading and the
 * row stacks — the same trick the struk stylesheet plays on the HTML table.
 */
function stackedRow(
    cursor: PdfCursor,
    metrics: RecapMetrics,
    table: RecapTable,
    row: RecapTableRow,
    isTotal: boolean,
): void {
    /*
     * A footed row is written like any other block on the roll — no rule above
     * it and no extra weight — so the closing figures read alongside the
     * channels rather than louder than them.
     */
    cursor.paragraph(row.label, 'left', { bold: true });

    let filled = 0;

    table.columns.slice(1).forEach((heading, index) => {
        /* A merged column is footed under the block, not repeated per row. */
        if (!isTotal && table.merge?.values[index] != null) {
            return;
        }

        const value = row.values[filled] ?? '';
        const tone = row.tones?.[filled];

        filled += 1;

        cursor.row(`  ${heading}`, value, { color: toneColor(tone) });
    });

    cursor.gap(0.8);
}

function tableBlock(
    cursor: PdfCursor,
    metrics: RecapMetrics,
    table: RecapTable,
): void {
    cursor.block();
    cursor.paragraph(table.heading.toUpperCase(), 'left', {
        size: metrics.headingSize,
        bold: true,
    });

    if (table.caption !== undefined) {
        cursor.paragraph(table.caption, 'left', {
            size: metrics.fineSize,
            color: MUTED,
        });
    }

    cursor.gap();

    if (table.rows.length === 0) {
        cursor.paragraph(table.emptyMessage ?? 'Tidak ada data.', 'left', {
            color: MUTED,
        });

        return;
    }

    if (metrics.tabular) {
        tableHeader(cursor, metrics, table);
    }

    const writeRow = metrics.tabular ? tabularRow : stackedRow;
    let blockTop = cursor.y;

    for (const row of table.rows) {
        const before = cursor.y;

        writeRow(cursor, metrics, table, row, false);

        /* The row started a new page, so the span it left behind is closed. */
        if (metrics.tabular && table.merge !== undefined && cursor.y < before) {
            mergedCells(cursor, table, table.merge, blockTop);
            blockTop = cursor.page.margin;
        }
    }

    if (metrics.tabular && table.merge !== undefined) {
        mergedCells(cursor, table, table.merge, blockTop);
    }

    /*
     * The sheet has already spanned those figures down the rows, so restating
     * them underneath would say the same thing twice; the roll, which cannot
     * span, foots them instead.
     */
    if (table.total !== undefined && !(metrics.tabular && table.merge)) {
        writeRow(cursor, metrics, table, table.total, true);
    }
}

function footerBlock(
    cursor: PdfCursor,
    metrics: RecapMetrics,
    sheet: RecapSheet,
): void {
    const align = metrics.tabular ? 'left' : 'center';

    cursor.block();
    cursor.paragraph(`Dicetak ${printedAt(sheet.timezone)}.`, align, {
        size: metrics.fineSize,
        color: MUTED,
    });
    cursor.paragraph(
        'Rekap internal untuk serah terima kas, bukan bukti pembayaran.',
        align,
        { size: metrics.fineSize, color: MUTED },
    );
}

/**
 * The address this recap lives at, as both a code to scan and a line to tap.
 * A filed PDF is read on a screen and off a printout of itself, so it carries
 * both: the link for the reader who can click, the QR for the one who cannot.
 */
function verificationBlock(
    cursor: PdfCursor,
    metrics: RecapMetrics,
    sheet: RecapSheet,
    qr: RasterImage | null,
): void {
    if (sheet.sourceUrl === null) {
        return;
    }

    const step = lineHeight(metrics.fineSize);

    cursor.block();

    if (qr !== null) {
        const size = metrics.tabular ? 26 : 22;

        cursor.ensureRoom(size + step * 3);
        cursor.doc.addImage(
            qr.dataUrl,
            'PNG',
            cursor.center - size / 2,
            cursor.y,
            size,
            size,
        );
        cursor.y += size + 1;
    }

    cursor.paragraph('Pindai atau buka rekap ini di konsol:', 'center', {
        size: metrics.fineSize,
        color: MUTED,
    });

    /*
     * Drawn line by line so every wrapped part of the URL is clickable, and the
     * annotation is placed by hand: textWithLink measures its rectangle off the
     * alphabetic baseline, which lands it above text written from the top.
     */
    cursor.apply({ size: metrics.fineSize, color: LINK });
    cursor.doc.setDrawColor(...LINK);
    cursor.doc.setLineWidth(0.1);

    for (const line of cursor.doc.splitTextToSize(
        sheet.sourceUrl,
        cursor.contentWidth,
    )) {
        const width = cursor.doc.getTextWidth(line);
        const left = cursor.center - width / 2;

        if (cursor.ensureRoom(step)) {
            cursor.apply({ size: metrics.fineSize, color: LINK });
        }

        cursor.doc.text(line, cursor.center, cursor.y, {
            align: 'center',
            baseline: 'top',
        });
        cursor.doc.line(
            left,
            cursor.y + metrics.fineSize * PT_TO_MM + 0.2,
            left + width,
            cursor.y + metrics.fineSize * PT_TO_MM + 0.2,
        );
        cursor.doc.link(left, cursor.y, width, step, { url: sheet.sourceUrl });
        cursor.y += step;
    }
}

/** The two marks on the file that are not drawn as text. */
interface RecapArtwork {
    logo: RasterImage | null;
    qr: RasterImage | null;
}

/** Draws the whole recap and reports the height it filled. */
function layoutRecap(
    doc: jsPDF,
    metrics: RecapMetrics,
    sheet: RecapSheet,
    brand: CarwashBrand,
    art: RecapArtwork,
): number {
    const cursor = new PdfCursor(doc, metrics);

    brandHeader(cursor, metrics, brand, art.logo);
    headingBlock(cursor, metrics, sheet);
    summaryBlock(cursor, metrics, sheet);

    for (const table of sheet.tables) {
        tableBlock(cursor, metrics, table);
    }

    footerBlock(cursor, metrics, sheet);
    verificationBlock(cursor, metrics, sheet, art.qr);

    return cursor.y + metrics.margin;
}

/** Filename the recap is saved under, e.g. "Rekap-Keuangan-1-Sep-2026.pdf". */
export function recapFileName(sheet: RecapSheet): string {
    return pdfFileName(`${sheet.title} ${sheet.periodLabel}`);
}

/**
 * The finished document, before anything is done with it.
 *
 * A4 is a fixed page and breaks through the cursor's `ensureRoom`, redrawing a
 * table's header on the page it continues onto. The roll has no fixed length,
 * so it takes the slip's trick instead: lay the sheet out once on a throwaway
 * document to measure it, then replay it onto a page cut to that height.
 *
 * Kept separate from the download so the layout can be rendered and inspected
 * without a browser, the way renderRecapSheetDocument is separate from the
 * window that opens it.
 */
export function renderRecapSheetPdf(
    sheet: RecapSheet,
    brand: CarwashBrand,
    paper: RecapPaper,
    art: RecapArtwork,
): jsPDF {
    const metrics = paper === 'a4' ? A4 : ROLL;
    const format: [number, number] =
        metrics.height === undefined
            ? [
                  metrics.width,
                  layoutRecap(
                      new jsPDF({ unit: 'mm', format: [metrics.width, 2000] }),
                      metrics,
                      sheet,
                      brand,
                      art,
                  ),
              ]
            : [metrics.width, metrics.height];
    const doc = new jsPDF({ unit: 'mm', format });

    doc.setProperties({
        title: `${sheet.title} ${sheet.periodLabel}`,
        subject: sheet.shiftLabel,
        author: brand.name,
    });
    layoutRecap(doc, metrics, sheet, brand, art);

    return doc;
}

/** Writes the recap straight to a PDF file, with no print prompt in between. */
export async function downloadRecapSheetPdf(
    recapWindow: Window,
    sheet: RecapSheet,
    brand: CarwashBrand,
    paper: RecapPaper,
): Promise<void> {
    const [logo, qr] = await Promise.all([
        brandArtwork(recapWindow, brand),
        /* The console renders the code; jsPDF cannot read the SVG it sends. */
        sheet.qrUrl === null
            ? Promise.resolve(null)
            : rasterize(recapWindow, sheet.qrUrl),
    ]);

    renderRecapSheetPdf(sheet, brand, paper, { logo, qr }).save(
        recapFileName(sheet),
    );
}
