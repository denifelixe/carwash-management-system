import type { jsPDF } from 'jspdf';

import type { CarwashBrand } from '@/types/demo';

/**
 * Helpers shared by the documents the app writes as PDF files.
 *
 * The POS slip and the shift recap are both drawn from their own data rather
 * than snapshotted off the rendered HTML, so their text stays text. What they
 * share is mechanism, not content: a cursor that walks down the page, the two
 * marks the built-in fonts cannot draw, and the encoding those fonts are
 * limited to. These live here rather than in one of the two modules for the
 * same reason escapeHtml lives in printDocument.ts — there must be exactly one.
 */

/** jsPDF sizes type in points whatever unit the document is laid out in. */
export const PT_TO_MM = 25.4 / 72;

export const INK: [number, number, number] = [15, 23, 42];

export const MUTED: [number, number, number] = [100, 116, 139];

export const RULE: [number, number, number] = [148, 163, 184];

export const LINK: [number, number, number] = [29, 78, 216];

/** Matches the 1.45 line-height both printed documents are set in. */
export function lineHeight(size: number): number {
    return size * 1.45 * PT_TO_MM;
}

/**
 * The built-in PDF fonts are encoded in WinAnsi, which has no U+2212, so the
 * minus the HTML documents print becomes the hyphen the font does carry.
 */
export function pdfText(value: string): string {
    return value.replaceAll('−', '-').replaceAll(' ', ' ');
}

/** Trims a document's title down to something a filesystem will take. */
export function pdfFileName(stem: string): string {
    return `${stem.replace(/[^A-Za-z0-9-]+/g, '-').replace(/-+/g, '-')}.pdf`;
}

/** How a page is measured: everything the cursor below needs to know. */
export interface PageMetrics {
    width: number;
    /** Absent on a roll, which is cut to the length its content fills. */
    height?: number;
    margin: number;
    bodySize: number;
    font: 'courier' | 'helvetica';
    /** The roll rules its blocks off with dashes, the A4 sheet with a hairline. */
    dashedRules: boolean;
    /** Space above and below a block's dividing rule. */
    blockGap: number;
}

export interface TextOptions {
    size?: number;
    bold?: boolean;
    color?: [number, number, number];
}

/**
 * A cursor down the page: every writer takes the top and leaves `y` on the next
 * free line. A fixed-height page breaks through `ensureRoom`; a roll has no
 * `height` and simply runs on until the page is cut to it.
 */
export class PdfCursor {
    public y: number;

    constructor(
        public readonly doc: jsPDF,
        public readonly page: PageMetrics,
    ) {
        this.y = page.margin;
    }

    get contentWidth(): number {
        return this.page.width - this.page.margin * 2;
    }

    get rightEdge(): number {
        return this.page.width - this.page.margin;
    }

    get center(): number {
        return this.page.width / 2;
    }

    apply({ size, bold, color }: TextOptions = {}): number {
        const fontSize = size ?? this.page.bodySize;

        this.doc.setFont(this.page.font, bold === true ? 'bold' : 'normal');
        this.doc.setFontSize(fontSize);
        this.doc.setTextColor(...(color ?? INK));

        return fontSize;
    }

    /**
     * Starts a new page when `needed` millimetres will not fit above the bottom
     * margin, and reports whether it did so a table can redraw its header. A
     * roll never breaks, so this is a no-op there.
     */
    ensureRoom(needed: number): boolean {
        if (this.page.height === undefined) {
            return false;
        }

        if (this.y + needed <= this.page.height - this.page.margin) {
            return false;
        }

        this.doc.addPage();
        this.y = this.page.margin;

        return true;
    }

    /** Wrapped paragraph across the full content width. */
    paragraph(
        value: string,
        align: 'left' | 'center' = 'left',
        options: TextOptions = {},
    ): void {
        const size = this.apply(options);
        const x = align === 'center' ? this.center : this.page.margin;

        for (const line of this.doc.splitTextToSize(
            pdfText(value),
            this.contentWidth,
        )) {
            /* A page break resets the font, so it is set again after it. */
            if (this.ensureRoom(lineHeight(size))) {
                this.apply(options);
            }

            this.doc.text(line, x, this.y, { align, baseline: 'top' });
            this.y += lineHeight(size);
        }
    }

    /**
     * Label on the left, figure on the right. The label wraps into whatever the
     * figure leaves, so a long channel name never pushes the total off the page.
     */
    row(label: string, value: string, options: TextOptions = {}): void {
        const size = this.apply(options);
        const valueWidth = this.doc.getTextWidth(pdfText(value));
        const labelLines = this.doc.splitTextToSize(
            pdfText(label),
            /* A figure wide enough to crowd the label out still leaves it room. */
            Math.max(this.contentWidth - valueWidth - 1.5, 12),
        );

        if (this.ensureRoom(lineHeight(size) * labelLines.length)) {
            this.apply(options);
        }

        this.doc.text(pdfText(value), this.rightEdge, this.y, {
            align: 'right',
            baseline: 'top',
        });

        for (const line of labelLines) {
            this.doc.text(line, this.page.margin, this.y, { baseline: 'top' });
            this.y += lineHeight(size);
        }
    }

    /**
     * The muted label / dark value pairing both documents head their blocks
     * with. `width` narrows the pair, so a wide sheet does not strand its value
     * an inch away from its label.
     */
    meta(label: string, value: string, width = this.contentWidth): void {
        const size = this.apply();

        if (this.ensureRoom(lineHeight(size))) {
            this.apply();
        }

        this.doc.setTextColor(...MUTED);
        this.doc.text(pdfText(label), this.page.margin, this.y, {
            baseline: 'top',
        });
        this.doc.setTextColor(...INK);
        this.doc.text(pdfText(value), this.page.margin + width, this.y, {
            align: 'right',
            baseline: 'top',
        });
        this.y += lineHeight(size);
    }

    /** A rule across the content width, dashed or solid as the paper asks. */
    line(dashed = this.page.dashedRules): void {
        this.doc.setDrawColor(...(dashed ? RULE : INK));
        this.doc.setLineWidth(0.15);
        this.doc.setLineDashPattern(dashed ? [0.5, 0.5] : [], 0);
        this.doc.line(this.page.margin, this.y, this.rightEdge, this.y);
        this.doc.setLineDashPattern([], 0);
    }

    /** The divider every block on both documents opens with. */
    block(): void {
        this.y += this.page.blockGap;
        this.line();
        this.y += this.page.blockGap;
    }

    gap(millimetres = 1.2): void {
        this.y += millimetres;
    }
}

export interface RasterImage {
    dataUrl: string;
    width: number;
    height: number;
}

/**
 * Redraws an image as PNG data so jsPDF can place it: an SVG is something it
 * cannot read, and the outlet's photo can be served from another origin.
 * Resolves null when the source will not load or the canvas comes back tainted,
 * and the document simply prints without that mark.
 */
export function rasterize(
    documentWindow: Window,
    source: string,
): Promise<RasterImage | null> {
    return new Promise((resolve) => {
        const image = documentWindow.document.createElement('img');

        image.crossOrigin = 'anonymous';
        image.addEventListener('error', () => resolve(null));
        image.addEventListener('load', () => {
            /* Oversampled so the mark stays sharp at the size it is placed. */
            const scale = 4;
            const width = (image.naturalWidth || 200) * scale;
            const height = (image.naturalHeight || 200) * scale;
            const canvas = documentWindow.document.createElement('canvas');

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
export function rasterizeEmoji(
    documentWindow: Window,
    logo: string,
): RasterImage | null {
    const size = 160;
    const canvas = documentWindow.document.createElement('canvas');

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

/**
 * The outlet's mark is the only thing on either file that is not drawn as text.
 * `photo` is passed explicitly by documents that carry their own mark — the
 * slip prints `brand.receipt.photo`, the recap the app photo.
 */
export async function brandArtwork(
    documentWindow: Window,
    brand: CarwashBrand,
    photo: string | null = brand.photo,
): Promise<RasterImage | null> {
    return photo === null
        ? rasterizeEmoji(documentWindow, brand.logo)
        : rasterize(documentWindow, photo);
}

/** Places the outlet's mark centred, at the size the paper gives it. */
export function drawBrandMark(
    cursor: PdfCursor,
    logo: RasterImage | null,
    maxHeight: number,
    maxWidth: number,
): void {
    if (logo === null) {
        return;
    }

    const width = Math.min(maxWidth, (maxHeight * logo.width) / logo.height);
    const height = (width * logo.height) / logo.width;

    cursor.doc.addImage(
        logo.dataUrl,
        'PNG',
        cursor.center - width / 2,
        cursor.y,
        width,
        height,
    );
    cursor.y += height + 1;
}
