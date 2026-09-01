import { jsPDF } from 'jspdf';

import { formatCurrency, formatDate } from '@/composables/useCarwashFormat';
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
 * same 78mm as the roll and on one continuous page, so the file reads as the
 * slip the printer would produce.
 *
 * The trade-off is that this is the second layout of the same document, next to
 * the HTML in posReceipt.ts. Both are driven by the same `PosReceipt`, so a new
 * figure has to be added here as well or it will be missing from the file.
 */

/** Printable width of the roll, matching PAPER_WIDTH in posReceipt.ts. */
const PAGE_WIDTH = 78;

const MARGIN = 4;

const CONTENT_WIDTH = PAGE_WIDTH - MARGIN * 2;

const RIGHT_EDGE = MARGIN + CONTENT_WIDTH;

const CENTER = PAGE_WIDTH / 2;

/** jsPDF sizes type in points whatever the document's unit is. */
const PT_TO_MM = 25.4 / 72;

const BODY_SIZE = 8;

const HEADING_SIZE = 10;

const FINE_SIZE = 6.5;

/** Matches the 1.45 line-height the HTML slip is set in. */
function lineHeight(size: number): number {
    return size * 1.45 * PT_TO_MM;
}

const INK: [number, number, number] = [15, 23, 42];

const MUTED: [number, number, number] = [100, 116, 139];

const RULE: [number, number, number] = [148, 163, 184];

const LINK: [number, number, number] = [29, 78, 216];

interface RasterImage {
    dataUrl: string;
    width: number;
    height: number;
}

interface ReceiptArtwork {
    logo: RasterImage | null;
}

interface TextOptions {
    size?: number;
    bold?: boolean;
    color?: [number, number, number];
}

/**
 * The built-in PDF fonts are encoded in WinAnsi, which has no U+2212, so the
 * minus the HTML slip prints becomes the hyphen the font does carry.
 */
function pdfText(value: string): string {
    return value.replaceAll('−', '-').replaceAll(' ', ' ');
}

/** Spaced out the way the slip letter-spaces its status stamp: "L U N A S". */
function spacedOut(value: string): string {
    return [...value].join(' ');
}

/** A cursor down the page: every writer takes the top and returns the next. */
class Slip {
    constructor(
        public readonly doc: jsPDF,
        public y: number = MARGIN + 1,
    ) {}

    private apply({ size, bold, color }: TextOptions = {}): number {
        const fontSize = size ?? BODY_SIZE;

        this.doc.setFont('courier', bold === true ? 'bold' : 'normal');
        this.doc.setFontSize(fontSize);
        this.doc.setTextColor(...(color ?? INK));

        return fontSize;
    }

    /** Wrapped paragraph across the full content width. */
    paragraph(
        value: string,
        align: 'left' | 'center' = 'left',
        options: TextOptions = {},
    ): void {
        const size = this.apply(options);
        const x = align === 'center' ? CENTER : MARGIN;

        for (const line of this.doc.splitTextToSize(
            pdfText(value),
            CONTENT_WIDTH,
        )) {
            this.doc.text(line, x, this.y, { align, baseline: 'top' });
            this.y += lineHeight(size);
        }
    }

    /**
     * Label on the left, figure on the right. The label wraps into whatever the
     * figure leaves, so a long service name never pushes the price off the roll.
     */
    row(label: string, value: string, options: TextOptions = {}): void {
        const size = this.apply(options);
        const valueWidth = this.doc.getTextWidth(pdfText(value));
        const labelLines = this.doc.splitTextToSize(
            pdfText(label),
            /* A figure wide enough to crowd the label out still leaves it room. */
            Math.max(CONTENT_WIDTH - valueWidth - 1.5, 12),
        );

        this.doc.text(pdfText(value), RIGHT_EDGE, this.y, {
            align: 'right',
            baseline: 'top',
        });

        for (const line of labelLines) {
            this.doc.text(line, MARGIN, this.y, { baseline: 'top' });
            this.y += lineHeight(size);
        }
    }

    /** The muted label / dark value pairing the slip's summary blocks use. */
    meta(label: string, value: string): void {
        const size = this.apply();

        this.doc.setTextColor(...MUTED);
        this.doc.text(pdfText(label), MARGIN, this.y, { baseline: 'top' });
        this.doc.setTextColor(...INK);
        this.doc.text(pdfText(value), RIGHT_EDGE, this.y, {
            align: 'right',
            baseline: 'top',
        });
        this.y += lineHeight(size);
    }

    amount(label: string, value: number, bold = false): void {
        this.row(label, formatCurrency(value), { bold });
    }

    line(dashed = true): void {
        this.doc.setDrawColor(...(dashed ? RULE : INK));
        this.doc.setLineWidth(0.15);
        this.doc.setLineDashPattern(dashed ? [0.5, 0.5] : [], 0);
        this.doc.line(MARGIN, this.y, RIGHT_EDGE, this.y);
        this.doc.setLineDashPattern([], 0);
    }

    /** The dashed divider every block on the slip opens with. */
    block(): void {
        this.y += 1.8;
        this.line();
        this.y += 1.8;
    }

    gap(millimetres = 1.2): void {
        this.y += millimetres;
    }
}

function brandHeader(
    slip: Slip,
    brand: CarwashBrand,
    art: ReceiptArtwork,
): void {
    if (art.logo !== null) {
        const width = Math.min(14, (10 * art.logo.width) / art.logo.height);
        const height = (width * art.logo.height) / art.logo.width;

        slip.doc.addImage(
            art.logo.dataUrl,
            'PNG',
            CENTER - width / 2,
            slip.y,
            width,
            height,
        );
        slip.y += height + 1;
    }

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

function summaryBlock(slip: Slip, receipt: PosReceipt): void {
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

function servicesBlock(slip: Slip, receipt: PosReceipt): void {
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

function billBlock(slip: Slip, receipt: PosReceipt): void {
    slip.block();
    slip.amount('Subtotal', receipt.subtotal + receipt.priorDiscount);

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

function historyEntry(slip: Slip, entry: PosReceiptHistoryEntry): void {
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
function historyBlock(slip: Slip, receipt: PosReceipt): void {
    if (receipt.history.length === 0 && receipt.previouslyPaid <= 0) {
        return;
    }

    slip.block();
    slip.paragraph('RIWAYAT PEMBAYARAN', 'left', { bold: true });
    slip.gap(0.6);

    for (const entry of receipt.history) {
        historyEntry(slip, entry);
    }

    slip.amount('Dibayar sebelumnya', receipt.previouslyPaid, true);
}

function paymentBlock(slip: Slip, receipt: PosReceipt): void {
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

    slip.amount('Total diterima', receipt.tenderedTotal, true);

    if (receipt.change > 0) {
        slip.amount('Kembalian', receipt.change, true);
    }

    if (receipt.isSettled) {
        return;
    }

    slip.block();
    slip.amount('Total sudah dibayar', receipt.paidTotal);
    slip.amount('Sisa tagihan', receipt.dueAfter, true);
}

/**
 * The file carries the link, never the QR: a slip read on a screen is one tap
 * away from the URL, and the QR is there for the printed copy that cannot be
 * tapped. The HTML keeps the same split under body[data-output-mode="print"].
 */
function verificationBlock(slip: Slip, receipt: PosReceipt): void {
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

function footerBlock(slip: Slip, receipt: PosReceipt): void {
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
    art: ReceiptArtwork,
): number {
    const slip = new Slip(doc);

    brandHeader(slip, brand, art);
    summaryBlock(slip, receipt);
    servicesBlock(slip, receipt);
    billBlock(slip, receipt);
    historyBlock(slip, receipt);
    paymentBlock(slip, receipt);
    footerBlock(slip, receipt);

    return slip.y + MARGIN + 2;
}

/**
 * Redraws an image as PNG data so jsPDF can place it: the QR arrives as an SVG
 * it cannot read, and the outlet's photo can be served from another origin.
 * Returns null when the source will not load or the canvas comes back tainted,
 * and the slip simply prints without that mark.
 */
function rasterize(
    receiptWindow: Window,
    source: string,
): Promise<RasterImage | null> {
    return new Promise((resolve) => {
        const image = receiptWindow.document.createElement('img');

        image.crossOrigin = 'anonymous';
        image.addEventListener('error', () => resolve(null));
        image.addEventListener('load', () => {
            /* Oversampled so the mark stays sharp at the roll's small size. */
            const scale = 4;
            const width = (image.naturalWidth || 200) * scale;
            const height = (image.naturalHeight || 200) * scale;
            const canvas = receiptWindow.document.createElement('canvas');

            canvas.width = width;
            canvas.height = height;

            const context = canvas.getContext('2d');

            if (context === null) {
                resolve(null);

                return;
            }

            context.drawImage(image, 0, 0, width, height);

            try {
                resolve({
                    dataUrl: canvas.toDataURL('image/png'),
                    width,
                    height,
                });
            } catch {
                resolve(null);
            }
        });
        image.src = source;
    });
}

/**
 * The brand emoji drawn onto a canvas, so a shop with no uploaded photo still
 * gets its mark: the built-in PDF fonts have no glyph for it.
 */
function rasterizeEmoji(
    receiptWindow: Window,
    logo: string,
): RasterImage | null {
    const size = 160;
    const canvas = receiptWindow.document.createElement('canvas');

    canvas.width = size;
    canvas.height = size;

    const context = canvas.getContext('2d');

    if (context === null) {
        return null;
    }

    context.font = `${Math.round(size * 0.8)}px serif`;
    context.textAlign = 'center';
    context.textBaseline = 'middle';
    context.fillText(logo, size / 2, size / 2);

    try {
        return {
            dataUrl: canvas.toDataURL('image/png'),
            width: size,
            height: size,
        };
    } catch {
        return null;
    }
}

/** The outlet's mark is the only thing on the file that is not drawn as text. */
async function receiptArtwork(
    receiptWindow: Window,
    brand: CarwashBrand,
): Promise<ReceiptArtwork> {
    return {
        logo:
            brand.photo === null
                ? rasterizeEmoji(receiptWindow, brand.logo)
                : await rasterize(receiptWindow, brand.photo),
    };
}

/** Filename the slip is saved under, e.g. "Struk-ZW-20260901-AIHOQS.pdf". */
export function receiptFileName(receipt: PosReceipt): string {
    const number = receipt.isSettled ? receipt.invoice : receipt.orderNo;

    return `Struk-${number.replace(/[^A-Za-z0-9-]+/g, '-')}.pdf`;
}

/**
 * Writes the slip straight to a PDF file, with no print prompt in between.
 *
 * The roll has no fixed length, so the page is sized to the slip: the layout is
 * run once against a throwaway document to measure it, then replayed onto a
 * page cut to that exact height. It draws twice, which for a receipt costs
 * nothing and keeps the file a single continuous page like the roll.
 */
export async function downloadPosReceiptPdf(
    receiptWindow: Window,
    receipt: PosReceipt,
    brand: CarwashBrand,
): Promise<void> {
    const art = await receiptArtwork(receiptWindow, brand);
    const measured = new jsPDF({ unit: 'mm', format: [PAGE_WIDTH, 1000] });
    const height = layoutSlip(measured, receipt, brand, art);
    const doc = new jsPDF({ unit: 'mm', format: [PAGE_WIDTH, height] });

    doc.setProperties({
        title: `Struk ${receipt.isSettled ? receipt.invoice : receipt.orderNo}`,
        subject: receipt.reference,
        author: brand.name,
    });
    layoutSlip(doc, receipt, brand, art);
    doc.save(receiptFileName(receipt));
}
