import { jsPDF } from 'jspdf';

import { formatCurrency, formatDate } from '@/composables/useCarwashFormat';
import {
    brandArtwork,
    drawBrandMark,
    lineHeight,
    LINK,
    MUTED,
    PdfCursor,
    pdfFileName,
    PT_TO_MM,
} from '@/lib/pdfDocument';
import type { PageMetrics, RasterImage } from '@/lib/pdfDocument';
import { paymentChannelLabel } from '@/lib/posReceipt';
import type { PosReceipt, PosReceiptHistoryEntry } from '@/lib/posReceipt';
import { formatWhatsapp, printedAt } from '@/lib/printDocument';
import { formatPlate } from '@/lib/vehiclePlate';
import type { CarwashBrand } from '@/types/demo';

/**
 * The slip as a real PDF.
 *
 * The file is drawn from the receipt itself rather than snapshotted off the
 * rendered HTML, so its text stays text: the customer can select the total,
 * search the reference, and tap the verification link. It is laid out at the
 * same 80mm as the roll and on one continuous page, so the file reads as the
 * slip the printer would produce.
 *
 * The trade-off is that this is the second layout of the same document, next to
 * the HTML in posReceipt.ts. Both are driven by the same `PosReceipt`, so a new
 * figure has to be added here as well or it will be missing from the file.
 */

/** The roll the slip is laid out at, matching PAPER_WIDTH in posReceipt.ts. */
const ROLL: PageMetrics = {
    width: 80,
    margin: 4,
    bodySize: 8,
    font: 'courier',
    dashedRules: true,
    blockGap: 1.8,
};

const PAGE_WIDTH = ROLL.width;

const CONTENT_WIDTH = PAGE_WIDTH - ROLL.margin * 2;

const CENTER = PAGE_WIDTH / 2;

const HEADING_SIZE = 10;

const FINE_SIZE = 6.5;

/** A figure row, spelled the way the slip spells money. */
function amountRow(
    slip: PdfCursor,
    label: string,
    value: number,
    bold = false,
): void {
    slip.row(label, formatCurrency(value), { bold });
}

/** Spaced out the way the slip letter-spaces its status stamp: "L U N A S". */
function spacedOut(value: string): string {
    return [...value].join(' ');
}

function brandHeader(
    slip: PdfCursor,
    brand: CarwashBrand,
    logo: RasterImage | null,
): void {
    drawBrandMark(slip, logo, 10, 14);

    slip.paragraph(brand.name, 'center', { size: HEADING_SIZE, bold: true });
    slip.paragraph(formatWhatsapp(brand.whatsapp), 'center', {
        size: FINE_SIZE,
        color: MUTED,
    });
    slip.paragraph(`@${brand.instagram}`, 'center', {
        size: FINE_SIZE,
        color: MUTED,
    });
}

function summaryBlock(slip: PdfCursor, receipt: PosReceipt): void {
    slip.block();
    slip.paragraph(
        receipt.isSettled ? 'STRUK PEMBAYARAN' : 'BUKTI PEMBAYARAN SEBAGIAN',
        'center',
        { bold: true },
    );

    if (receipt.isReprint) {
        slip.paragraph('— SALINAN / CETAK ULANG —', 'center', {
            size: FINE_SIZE,
            color: [180, 83, 9],
        });
    }

    slip.gap(0.8);
    slip.meta('No.', receipt.isSettled ? receipt.invoice : receipt.orderNo);

    if (receipt.isSettled) {
        slip.meta('Order', receipt.orderNo);
    }

    slip.meta('Ref.', receipt.reference);
    slip.meta('Tanggal', formatDate(receipt.date));
    slip.meta('Jam', receipt.time);
    slip.meta('Kasir', receipt.cashier);
    slip.meta('Shift', receipt.shift);

    slip.block();
    slip.meta('Customer', receipt.customer);
    slip.meta('Kendaraan', receipt.vehicle);
    slip.meta('Plat', formatPlate(receipt.plate));
}

function servicesBlock(slip: PdfCursor, receipt: PosReceipt): void {
    slip.block();
    slip.paragraph('RINCIAN LAYANAN', 'left', { bold: true });
    slip.gap(0.6);

    if (receipt.lines.length === 0) {
        slip.paragraph(receipt.items);

        return;
    }

    for (const line of receipt.lines) {
        slip.row(line.name, formatCurrency(line.price));
    }
}

function billBlock(slip: PdfCursor, receipt: PosReceipt): void {
    slip.block();
    amountRow(slip, 'Subtotal', receipt.subtotal + receipt.priorDiscount);

    if (receipt.priorDiscount > 0) {
        /* A reprint cannot tell which payment took the discount. */
        slip.row(
            receipt.isReprint ? 'Diskon' : 'Diskon sebelumnya',
            `-${formatCurrency(receipt.priorDiscount)}`,
        );
    }

    if (receipt.reward !== '—') {
        slip.meta('Reward', receipt.reward);
    }

    if (receipt.rewardDiscount > 0) {
        slip.row(
            'Potongan reward',
            `-${formatCurrency(receipt.rewardDiscount)}`,
        );
    }

    if (receipt.cashierDiscount > 0) {
        slip.row('Diskon kasir', `-${formatCurrency(receipt.cashierDiscount)}`);
    }

    slip.gap(1);
    slip.line(false);
    slip.gap(1);
    slip.row('TOTAL', formatCurrency(receipt.total), {
        size: HEADING_SIZE,
        bold: true,
    });
}

function historyEntry(slip: PdfCursor, entry: PosReceiptHistoryEntry): void {
    slip.row(
        `${formatDate(entry.date)} · ${entry.time}`,
        formatCurrency(entry.amount),
    );
    slip.paragraph(entry.type, 'left', { size: FINE_SIZE, color: MUTED });
    slip.paragraph(entry.channels, 'left', { size: FINE_SIZE, color: MUTED });
    slip.paragraph(`Kasir ${entry.cashier}`, 'left', {
        size: FINE_SIZE,
        color: MUTED,
    });
    slip.gap(0.8);
}

/**
 * Payments the order already took. A prototype order can carry a paid amount
 * with no transactions behind it, so the total prints either way.
 */
function historyBlock(slip: PdfCursor, receipt: PosReceipt): void {
    if (receipt.history.length === 0 && receipt.previouslyPaid <= 0) {
        return;
    }

    slip.block();
    slip.paragraph('RIWAYAT PEMBAYARAN', 'left', { bold: true });
    slip.gap(0.6);

    for (const entry of receipt.history) {
        historyEntry(slip, entry);
    }

    amountRow(slip, 'Dibayar sebelumnya', receipt.previouslyPaid, true);
}

function paymentBlock(slip: PdfCursor, receipt: PosReceipt): void {
    const hasHistory = receipt.history.length > 0 || receipt.previouslyPaid > 0;
    /* A copy can be of any payment in the run, not just the most recent one. */
    const heading = hasHistory
        ? receipt.isReprint
            ? 'PEMBAYARAN INI'
            : 'PEMBAYARAN SAAT INI'
        : 'PEMBAYARAN';

    slip.block();
    slip.paragraph(heading, 'left', { bold: true });
    slip.gap(0.6);

    if (receipt.paymentBreakdown.length === 0) {
        slip.paragraph(receipt.payment);
    }

    for (const payment of receipt.paymentBreakdown) {
        slip.row(paymentChannelLabel(payment), formatCurrency(payment.amount));

        if (payment.reference !== '') {
            slip.paragraph(`Ref. ${payment.reference}`, 'left', {
                size: FINE_SIZE,
                color: MUTED,
            });
        }
    }

    amountRow(slip, 'Total diterima', receipt.tenderedTotal, true);

    if (receipt.change > 0) {
        amountRow(slip, 'Kembalian', receipt.change, true);
    }

    if (receipt.isSettled) {
        return;
    }

    slip.block();
    amountRow(slip, 'Total sudah dibayar', receipt.paidTotal);
    amountRow(slip, 'Sisa tagihan', receipt.dueAfter, true);
}

/**
 * The file carries the link, never the QR: a slip read on a screen is one tap
 * away from the URL, and the QR is there for the printed copy that cannot be
 * tapped. The HTML keeps the same split under body[data-output-mode="print"].
 */
function verificationBlock(slip: PdfCursor, receipt: PosReceipt): void {
    if (receipt.publicUrl === null) {
        return;
    }

    slip.block();
    slip.paragraph('Verifikasi struk:', 'center', {
        size: FINE_SIZE,
        color: MUTED,
    });

    /*
     * Drawn line by line so every wrapped part of the URL is clickable, and the
     * annotation is placed by hand: textWithLink measures its rectangle off the
     * alphabetic baseline, which lands it above text written from the top.
     */
    const step = lineHeight(FINE_SIZE);

    slip.doc.setFont('courier', 'normal');
    slip.doc.setFontSize(FINE_SIZE);
    slip.doc.setTextColor(...LINK);
    slip.doc.setDrawColor(...LINK);
    slip.doc.setLineWidth(0.1);

    for (const line of slip.doc.splitTextToSize(
        receipt.publicUrl,
        CONTENT_WIDTH,
    )) {
        const width = slip.doc.getTextWidth(line);
        const left = CENTER - width / 2;
        const underline = slip.y + FINE_SIZE * PT_TO_MM + 0.2;

        slip.doc.text(line, CENTER, slip.y, {
            align: 'center',
            baseline: 'top',
        });
        slip.doc.line(left, underline, left + width, underline);
        slip.doc.link(left, slip.y, width, step, { url: receipt.publicUrl });
        slip.y += step;
    }
}

function footerBlock(slip: PdfCursor, receipt: PosReceipt): void {
    slip.block();
    slip.paragraph(
        spacedOut(receipt.isSettled ? 'LUNAS' : 'BELUM LUNAS'),
        'center',
        { size: HEADING_SIZE, bold: true },
    );
    slip.gap(0.8);
    slip.paragraph('Terima kasih atas kunjungan Anda.', 'center');
    slip.gap(0.8);
    slip.paragraph('Struk ini adalah bukti pembayaran yang sah.', 'center', {
        size: FINE_SIZE,
        color: MUTED,
    });

    if (receipt.isReprint) {
        slip.paragraph(
            `Dicetak ulang ${printedAt(receipt.timezone)}.`,
            'center',
            { size: FINE_SIZE, color: MUTED },
        );
    }

    verificationBlock(slip, receipt);
}

/** Draws the whole slip and reports the height it filled. */
function layoutSlip(
    doc: jsPDF,
    receipt: PosReceipt,
    brand: CarwashBrand,
    logo: RasterImage | null,
): number {
    const slip = new PdfCursor(doc, ROLL);

    brandHeader(slip, brand, logo);
    summaryBlock(slip, receipt);
    servicesBlock(slip, receipt);
    billBlock(slip, receipt);
    historyBlock(slip, receipt);
    paymentBlock(slip, receipt);
    footerBlock(slip, receipt);

    return slip.y + ROLL.margin + 2;
}

/** Filename the slip is saved under, e.g. "Struk-ZW-20260901-AIHOQS.pdf". */
export function receiptFileName(receipt: PosReceipt): string {
    const number = receipt.isSettled ? receipt.invoice : receipt.orderNo;

    return pdfFileName(`Struk-${number}`);
}

/**
 * The finished document, before anything is done with it.
 *
 * The roll has no fixed length, so the page is sized to the slip: the layout is
 * run once against a throwaway document to measure it, then replayed onto a
 * page cut to that exact height. It draws twice, which for a receipt costs
 * nothing and keeps the file a single continuous page like the roll.
 *
 * Kept separate from the download so the layout can be rendered and inspected
 * without a browser, the way renderPosReceiptDocument is separate from the
 * window that opens it.
 */
export function renderPosReceiptPdf(
    receipt: PosReceipt,
    brand: CarwashBrand,
    logo: RasterImage | null,
): jsPDF {
    const measured = new jsPDF({ unit: 'mm', format: [PAGE_WIDTH, 1000] });
    const height = layoutSlip(measured, receipt, brand, logo);
    const doc = new jsPDF({ unit: 'mm', format: [PAGE_WIDTH, height] });

    doc.setProperties({
        title: `Struk ${receipt.isSettled ? receipt.invoice : receipt.orderNo}`,
        subject: receipt.reference,
        author: brand.name,
    });
    layoutSlip(doc, receipt, brand, logo);

    return doc;
}

/** Writes the slip straight to a PDF file, with no print prompt in between. */
export async function downloadPosReceiptPdf(
    receiptWindow: Window,
    receipt: PosReceipt,
    brand: CarwashBrand,
): Promise<void> {
    const logo = await brandArtwork(receiptWindow, brand);

    renderPosReceiptPdf(receipt, brand, logo).save(receiptFileName(receipt));
}
