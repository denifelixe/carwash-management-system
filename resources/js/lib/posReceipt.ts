import { formatCurrency, formatDate } from '@/composables/useCarwashFormat';
import {
    brandContacts,
    brandMark,
    escapeHtml,
    printedAt,
} from '@/lib/printDocument';
import { formatPlate } from '@/lib/vehiclePlate';
import type { CarwashBrand } from '@/types/demo';

/**
 * Thermal receipt rendered outside the SPA.
 *
 * The cashier station prints on an 80mm roll whose printable area is 78mm, so
 * the slip is laid out at that exact width and opened in its own window sized
 * to match. The document is self-contained because it is written once and
 * never re-renders, which also keeps it printable without the app bundle.
 */

/** Printable width of the 80mm roll the cashier station is loaded with. */
const PAPER_WIDTH = '78mm';

/**
 * The slip itself only needs ~295px (78mm at 96dpi), but the browser's print
 * prompt is drawn inside this window and gets clipped at that width, so the
 * frame is opened wide enough to show the whole dialog. The paper stays 78mm
 * and sits centred on the backdrop.
 */
const WINDOW_WIDTH = 520;

const WINDOW_HEIGHT = 780;

export interface PosReceiptLine {
    name: string;
    price: number;
}

/** One payment the order already took, printed so the bill reconciles. */
export interface PosReceiptHistoryEntry {
    /** ISO day the payment was recorded on. */
    date: string;
    time: string;
    type: string;
    /** Channels with their amounts, e.g. "Tunai Rp 30.000 + Debit · BCA …". */
    channels: string;
    cashier: string;
    amount: number;
}

export interface PosPaymentBreakdown {
    method: string;
    amount: number;
    provider: string;
    /** EDC trace, approval, or transfer reference the cashier keys in. */
    reference: string;
}

export interface PosReceipt {
    orderNo: string;
    invoice: string;
    /** Public transaction reference, mirrored by the Finance ledger. */
    reference: string;
    /** ISO day the settlement was recorded on. */
    date: string;
    time: string;
    cashier: string;
    shift: string;
    customer: string;
    vehicle: string;
    plate: string;
    items: string;
    lines: PosReceiptLine[];
    /** Bill total before this payment's discounts. */
    subtotal: number;
    /** Discount an earlier payment already took off the listed services. */
    priorDiscount: number;
    rewardDiscount: number;
    cashierDiscount: number;
    total: number;
    tenderedTotal: number;
    change: number;
    /** Payments the order took before this one, oldest first. */
    history: PosReceiptHistoryEntry[];
    /** Amount already settled before this payment landed. */
    previouslyPaid: number;
    paidTotal: number;
    dueAfter: number;
    isSettled: boolean;
    /** Marks the slip as a copy so it is not mistaken for the original. */
    isReprint: boolean;
    /** The outlet's zone, so a reprint stamp reads the shop clock. */
    timezone: string;
    payment: string;
    paymentBreakdown: PosPaymentBreakdown[];
    /** Redeemed by the cashier, shown here so the customer sees it on the slip. */
    reward: string;
    /** Signed guest URL used to view and verify a live receipt. */
    publicUrl: string | null;
    /** Server-generated QR data URL for the signed verification link. */
    verificationQr: string;
}

export function paymentChannelLabel(payment: PosPaymentBreakdown): string {
    return payment.provider === ''
        ? payment.method
        : `${payment.method} · ${payment.provider}`;
}

function metaRow(label: string, value: string): string {
    return `<div class="meta"><span>${escapeHtml(label)}</span><span>${escapeHtml(value)}</span></div>`;
}

function amountRow(
    label: string,
    amount: number,
    modifier: '' | 'negative' | 'strong' | 'grand' = '',
): string {
    const sign = modifier === 'negative' ? '−' : '';
    const className = modifier === '' ? 'amount' : `amount ${modifier}`;
    const value = escapeHtml(formatCurrency(amount));

    return `<div class="${className}"><span>${escapeHtml(label)}</span><span class="value">${sign}${value}</span></div>`;
}

function serviceLines(receipt: PosReceipt): string {
    if (receipt.lines.length === 0) {
        return `<p class="wrap">${escapeHtml(receipt.items)}</p>`;
    }

    return receipt.lines
        .map((line) => {
            const price = escapeHtml(formatCurrency(line.price));

            return `<div class="line"><span class="line-name">${escapeHtml(line.name)}</span><span class="value">${price}</span></div>`;
        })
        .join('');
}

function paymentLines(receipt: PosReceipt): string {
    if (receipt.paymentBreakdown.length === 0) {
        return `<p class="wrap">${escapeHtml(receipt.payment)}</p>`;
    }

    return receipt.paymentBreakdown
        .map((payment) => {
            const label = escapeHtml(paymentChannelLabel(payment));
            const amount = escapeHtml(formatCurrency(payment.amount));
            const reference =
                payment.reference === ''
                    ? ''
                    : `<span class="reference">Ref. ${escapeHtml(payment.reference)}</span>`;

            return `<div class="line"><span class="line-name">${label}${reference}</span><span class="value">${amount}</span></div>`;
        })
        .join('');
}

function billBlock(receipt: PosReceipt): string {
    return [
        amountRow('Subtotal', receipt.subtotal + receipt.priorDiscount),
        receipt.priorDiscount > 0
            ? amountRow(
                  /* A reprint cannot tell which payment took the discount. */
                  receipt.isReprint ? 'Diskon' : 'Diskon sebelumnya',
                  receipt.priorDiscount,
                  'negative',
              )
            : '',
        receipt.reward === '—' ? '' : metaRow('Reward', receipt.reward),
        receipt.rewardDiscount > 0
            ? amountRow('Potongan reward', receipt.rewardDiscount, 'negative')
            : '',
        receipt.cashierDiscount > 0
            ? amountRow('Diskon kasir', receipt.cashierDiscount, 'negative')
            : '',
        amountRow('TOTAL', receipt.total, 'grand'),
    ].join('');
}

function historyEntry(entry: PosReceiptHistoryEntry): string {
    const when = escapeHtml(`${formatDate(entry.date)} · ${entry.time}`);
    const amount = escapeHtml(formatCurrency(entry.amount));

    return `<div class="history">
    <div class="line"><span class="line-name">${when}</span><span class="value">${amount}</span></div>
    <p class="detail">${escapeHtml(entry.type)}</p>
    <p class="detail">${escapeHtml(entry.channels)}</p>
    <p class="detail">Kasir ${escapeHtml(entry.cashier)}</p>
</div>`;
}

/**
 * Payments the order already took. A prototype order can carry a paid amount
 * with no transactions behind it, so the total prints either way.
 */
function historyBlock(receipt: PosReceipt): string {
    if (receipt.history.length === 0 && receipt.previouslyPaid <= 0) {
        return '';
    }

    const entries = receipt.history.map(historyEntry).join('');

    return `<section class="block">
    <p class="heading">Riwayat pembayaran</p>
    ${entries}
    ${amountRow('Dibayar sebelumnya', receipt.previouslyPaid, 'strong')}
</section>`;
}

function paymentHeading(receipt: PosReceipt): string {
    if (receipt.history.length === 0 && receipt.previouslyPaid <= 0) {
        return 'Pembayaran';
    }

    /* A copy can be of any payment in the run, not just the most recent one. */
    return receipt.isReprint ? 'Pembayaran ini' : 'Pembayaran saat ini';
}

function paymentBlock(receipt: PosReceipt): string {
    return [
        `<p class="heading">${paymentHeading(receipt)}</p>`,
        paymentLines(receipt),
        amountRow('Total diterima', receipt.tenderedTotal, 'strong'),
        receipt.change > 0
            ? amountRow('Kembalian', receipt.change, 'strong')
            : '',
    ].join('');
}

function outstandingBlock(receipt: PosReceipt): string {
    if (receipt.isSettled) {
        return '';
    }

    return `<section class="block">${amountRow('Total sudah dibayar', receipt.paidTotal)}${amountRow('Sisa tagihan', receipt.dueAfter, 'strong')}</section>`;
}

function receiptBody(receipt: PosReceipt, brand: CarwashBrand): string {
    const title = receipt.isSettled
        ? 'STRUK PEMBAYARAN'
        : 'BUKTI PEMBAYARAN SEBAGIAN';

    return `<header class="brand">
    ${brandMark(brand.photo, brand.logo, brand.name)}
    <p class="name">${escapeHtml(brand.name)}</p>
    ${brandContacts(brand.whatsapp, brand.instagram)}
</header>
<section class="block">
    <p class="title">${title}</p>
    ${receipt.isReprint ? '<p class="copy">— SALINAN / CETAK ULANG —</p>' : ''}
    ${metaRow('No.', receipt.isSettled ? receipt.invoice : receipt.orderNo)}
    ${receipt.isSettled ? metaRow('Order', receipt.orderNo) : ''}
    ${metaRow('Ref.', receipt.reference)}
    ${metaRow('Tanggal', formatDate(receipt.date))}
    ${metaRow('Jam', receipt.time)}
    ${metaRow('Kasir', receipt.cashier)}
    ${metaRow('Shift', receipt.shift)}
</section>
<section class="block">
    ${metaRow('Customer', receipt.customer)}
    ${metaRow('Kendaraan', receipt.vehicle)}
    ${metaRow('Plat', formatPlate(receipt.plate))}
</section>
<section class="block">
    <p class="heading">Rincian layanan</p>
    ${serviceLines(receipt)}
</section>
<section class="block">${billBlock(receipt)}</section>
${historyBlock(receipt)}
<section class="block">${paymentBlock(receipt)}</section>
${outstandingBlock(receipt)}
<footer class="footer">
    <p class="status">${receipt.isSettled ? 'LUNAS' : 'BELUM LUNAS'}</p>
    <p>Terima kasih atas kunjungan Anda.</p>
    <p class="fineprint">Struk ini adalah bukti pembayaran yang sah.</p>
    ${receipt.isReprint ? `<p class="fineprint">Dicetak ulang ${escapeHtml(printedAt(receipt.timezone))}.</p>` : ''}
    ${verificationBlock(receipt)}
</footer>`;
}

/**
 * Printed copies only. On screen the slip is already being read at its own
 * verification URL, and the PDF spells the link out in text, so the QR would be
 * a third copy of the same address — it earns its space only on paper, which
 * cannot be tapped. Hidden by stylesheet rather than left out of the markup so
 * a plain Ctrl+P still prints it.
 */
function verificationBlock(receipt: PosReceipt): string {
    if (receipt.publicUrl === null || receipt.verificationQr === '') {
        return '';
    }

    return `<div class="verification">
    <img class="verification-qr-image" src="${escapeHtml(receipt.verificationQr)}" alt="QR verifikasi struk">
    <p class="verification-caption">Pindai untuk memeriksa keabsahan struk</p>
</div>`;
}

function receiptStyles(): string {
    return `:root { color-scheme: light; }
* { box-sizing: border-box; margin: 0; padding: 0; }
body {
    background: #eef2f7;
    color: #0f172a;
    font-family: 'Consolas', 'Courier New', ui-monospace, monospace;
    font-size: 11px;
    line-height: 1.45;
    padding: 14px 0 28px;
}
.toolbar {
    background: #ffffff;
    border: 1px solid rgba(15, 23, 42, 0.07);
    border-radius: 14px;
    box-shadow:
        0 1px 2px rgba(15, 23, 42, 0.05),
        0 10px 26px -12px rgba(15, 23, 42, 0.28);
    display: flex;
    gap: 2px;
    margin: 0 auto 14px;
    padding: 5px;
    width: ${PAPER_WIDTH};
}
.toolbar button {
    align-items: center;
    background: transparent;
    border: 0;
    border-radius: 10px;
    color: #475569;
    cursor: pointer;
    display: flex;
    flex: 1;
    flex-direction: column;
    font-family: ui-sans-serif, system-ui, 'Segoe UI', sans-serif;
    font-size: 9.5px;
    font-weight: 600;
    gap: 4px;
    letter-spacing: 0.01em;
    line-height: 1;
    min-width: 0;
    padding: 7px 2px 6px;
    transition: background-color 140ms ease, color 140ms ease;
}
.toolbar button svg {
    fill: none;
    height: 15px;
    stroke: currentColor;
    stroke-linecap: round;
    stroke-linejoin: round;
    stroke-width: 1.7;
    width: 15px;
}
.toolbar button span {
    overflow: hidden;
    text-overflow: ellipsis;
    white-space: nowrap;
    width: 100%;
}
.toolbar button:hover { background: #f1f5f9; color: #0f172a; }
.toolbar button:active { background: #e2e8f0; }
.toolbar button:focus-visible { outline: 2px solid #0f172a; outline-offset: -1px; }
.toolbar button.primary { background: #0f172a; color: #ffffff; }
.toolbar button.primary:hover { background: #1e293b; }
.toolbar button.primary:active { background: #334155; }
.toolbar button.copied, .toolbar button.copied:hover { color: #059669; }
.toolbar button:disabled { background: transparent; color: #cbd5e1; cursor: not-allowed; }
/* Feedback for actions that leave no trace on the page, e.g. the copied link. */
.toast {
    align-items: center;
    background: #0f172a;
    border-radius: 10px;
    box-shadow: 0 10px 26px -10px rgba(15, 23, 42, 0.45);
    color: #ffffff;
    display: flex;
    font-family: ui-sans-serif, system-ui, 'Segoe UI', sans-serif;
    font-size: 11px;
    font-weight: 600;
    gap: 7px;
    opacity: 0;
    padding: 9px 12px;
    pointer-events: none;
    position: fixed;
    right: 14px;
    top: 14px;
    transform: translateY(-8px);
    transition: opacity 180ms ease, transform 180ms ease;
    z-index: 10;
}
.toast.visible { opacity: 1; transform: translateY(0); }
.toast.error { background: #b91c1c; }
.toast.error svg { stroke: #fecaca; }
.toast svg {
    fill: none;
    flex: 0 0 auto;
    height: 14px;
    stroke: #4ade80;
    stroke-linecap: round;
    stroke-linejoin: round;
    stroke-width: 2.2;
    width: 14px;
}
.paper {
    background: #ffffff;
    border-radius: 3px;
    box-shadow:
        0 1px 2px rgba(15, 23, 42, 0.06),
        0 18px 40px -18px rgba(15, 23, 42, 0.35);
    margin: 0 auto;
    padding: 14px 10px 18px;
    width: ${PAPER_WIDTH};
}
.brand { text-align: center; }
.logo { font-size: 20px; line-height: 1.2; }
.logo-image { display: block; height: auto; margin: 0 auto 4px; max-height: 40px; max-width: 56px; object-fit: contain; }
.name { font-size: 13px; font-weight: 700; letter-spacing: 0.04em; }
.contacts { align-items: center; display: flex; flex-direction: column; gap: 1px; margin-top: 2px; }
.contact { align-items: center; color: #475569; display: inline-flex; font-size: 10px; gap: 4px; }
.contact-icon { display: inline-flex; height: 11px; width: 11px; }
.contact-icon svg { height: 100%; width: 100%; }
.contact-icon.whatsapp { color: #16a34a; }
.contact-icon.instagram { color: #c026d3; }
.block {
    border-top: 1px dashed #94a3b8;
    margin-top: 8px;
    padding-top: 8px;
}
.title {
    font-weight: 700;
    letter-spacing: 0.08em;
    margin-bottom: 4px;
    text-align: center;
}
.heading {
    font-weight: 700;
    letter-spacing: 0.06em;
    margin-bottom: 3px;
    text-transform: uppercase;
}
.copy {
    color: #b45309;
    font-size: 10px;
    font-weight: 700;
    letter-spacing: 0.06em;
    margin-bottom: 4px;
    text-align: center;
}
/* Long references and service names must wrap, never widen the roll. */
.meta, .amount, .line {
    display: flex;
    gap: 8px;
    justify-content: space-between;
    overflow-wrap: anywhere;
}
.meta > span, .amount > span, .line > span { min-width: 0; }
.meta > span:first-child { color: #475569; flex: 0 0 auto; }
.meta > span:last-child { text-align: right; }
.line { align-items: flex-start; }
.line-name { flex: 1 1 auto; }
.wrap { overflow-wrap: anywhere; }
.reference { color: #64748b; display: block; font-size: 9px; }
.history { margin-bottom: 6px; }
.history + .amount { border-top: 1px dotted #cbd5e1; padding-top: 4px; }
.detail {
    color: #64748b;
    font-size: 9px;
    line-height: 1.35;
    overflow-wrap: anywhere;
    padding-left: 6px;
}
.value { flex: 0 0 auto; font-variant-numeric: tabular-nums; }
.amount > span:first-child { color: #475569; }
.amount.strong { font-weight: 700; }
.amount.strong > span:first-child { color: #0f172a; }
.amount.grand {
    border-top: 1px solid #0f172a;
    font-size: 13px;
    font-weight: 700;
    margin-top: 4px;
    padding-top: 4px;
}
.amount.grand > span:first-child { color: #0f172a; }
.footer {
    border-top: 1px dashed #94a3b8;
    margin-top: 8px;
    padding-top: 8px;
    text-align: center;
}
.status {
    font-size: 13px;
    font-weight: 700;
    letter-spacing: 0.18em;
    margin-bottom: 4px;
}
.fineprint { color: #64748b; font-size: 9px; margin-top: 6px; }
.verification { border-top: 1px dashed #94a3b8; display: none; margin-top: 8px; padding-top: 8px; }
.verification-qr-image { display: block; height: 30mm; margin: 0 auto 3px; width: 30mm; }
.verification-caption { color: #64748b; font-size: 9px; }
@page { margin: 0; size: ${PAPER_WIDTH} auto; }
@media print {
    body { background: #ffffff; padding: 0; }
    .toolbar, .toast { display: none; }
    .paper { box-shadow: none; padding: 0 3mm 6mm; }
    /* The QR is worth its space only on paper, which cannot be tapped. */
    .verification { display: block; }
}`;
}

/**
 * Toolbar glyphs. They ship as markup rather than a font so the slip stays a
 * single self-contained document with nothing left to fetch.
 */
const toolbarIcons = {
    print: '<svg viewBox="0 0 24 24" aria-hidden="true"><path d="M7 8V3h10v5"/><path d="M7 18H5a2 2 0 0 1-2-2v-5a2 2 0 0 1 2-2h14a2 2 0 0 1 2 2v5a2 2 0 0 1-2 2h-2"/><rect x="7" y="14" width="10" height="7" rx="1.5"/></svg>',
    download:
        '<svg viewBox="0 0 24 24" aria-hidden="true"><path d="M12 3v12"/><path d="m7.5 10.5 4.5 4.5 4.5-4.5"/><path d="M4 20h16"/></svg>',
    link: '<svg viewBox="0 0 24 24" aria-hidden="true"><path d="m9.8 14.2 4.4-4.4"/><path d="M11.3 6.6 13 4.9a4.1 4.1 0 0 1 5.8 5.8l-1.7 1.7"/><path d="m12.7 17.4-1.7 1.7a4.1 4.1 0 0 1-5.8-5.8l1.7-1.7"/></svg>',
    check: '<svg viewBox="0 0 24 24" aria-hidden="true"><path d="m5 12.5 4.5 4.5L19 7.5"/></svg>',
    close: '<svg viewBox="0 0 24 24" aria-hidden="true"><path d="M6.5 6.5 17.5 17.5"/><path d="M17.5 6.5 6.5 17.5"/></svg>',
};

function toolbarButton(
    attribute: string,
    icon: string,
    label: string,
    modifier = '',
    disabled = false,
): string {
    const className = modifier === '' ? '' : ` class="${modifier}"`;

    return `<button type="button"${className} ${attribute}${disabled ? ' disabled' : ''} title="${escapeHtml(label)}">${icon}<span>${escapeHtml(label)}</span></button>`;
}

/**
 * Full standalone document for the slip, laid out at the roll's printable
 * width so the on-screen preview and the printout are the same object.
 */
export function renderPosReceiptDocument(
    receipt: PosReceipt,
    brand: CarwashBrand,
): string {
    const number = receipt.isSettled ? receipt.invoice : receipt.orderNo;

    return `<!DOCTYPE html>
<html lang="id">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<meta name="color-scheme" content="light">
<title>Struk ${escapeHtml(number)} — ${escapeHtml(brand.name)}</title>
<style>${receiptStyles()}</style>
</head>
<body>
<div class="toolbar">
    ${toolbarButton('data-receipt-print', toolbarIcons.print, 'Cetak', 'primary')}
    ${toolbarButton('data-receipt-download', toolbarIcons.download, 'Unduh PDF')}
    ${toolbarButton('data-receipt-copy', toolbarIcons.link, 'Salin Link', '', receipt.publicUrl === null)}
    ${toolbarButton('data-receipt-close', toolbarIcons.close, 'Tutup')}
</div>
<div class="toast" role="status" aria-live="polite" data-receipt-toast>${toolbarIcons.check}<span data-receipt-toast-message></span></div>
<main class="paper">${receiptBody(receipt, brand)}</main>
</body>
</html>`;
}

/**
 * Opens the slip in its own window sized to the roll. Handlers are wired from
 * here rather than an inline script so the written document stays free of
 * executable markup. Returns `null` when the browser blocks the window.
 */
export function openPosReceiptWindow(
    receipt: PosReceipt,
    brand: CarwashBrand,
): Window | null {
    const receiptWindow = window.open(
        receipt.publicUrl ?? '',
        `pos-receipt-${receipt.orderNo}`,
        `popup=yes,width=${WINDOW_WIDTH},height=${WINDOW_HEIGHT},scrollbars=yes,resizable=yes`,
    );

    if (!receiptWindow) {
        return null;
    }

    if (receipt.publicUrl === null) {
        mountPosReceiptDocument(receiptWindow, receipt, brand);
    }

    receiptWindow.focus();

    return receiptWindow;
}

/**
 * Turns one of the static toolbar glyphs into nodes so the copy button can
 * swap its icon. Only the constants above are ever passed in, so no user value
 * reaches innerHTML here.
 */
function parseIcon(receiptWindow: Window, icon: string): ChildNode[] {
    const template = receiptWindow.document.createElement('template');
    template.innerHTML = icon;

    return [...template.content.childNodes];
}

/** How long a toast stays up before it fades back out. */
const TOAST_DURATION = 2400;

let toastTimer = 0;

/**
 * Confirms an action that leaves nothing behind on the slip. The document is
 * written outside the SPA, so it cannot reach the app's toaster and carries its
 * own.
 */
function showReceiptToast(
    receiptWindow: Window,
    message: string,
    tone: 'success' | 'error' = 'success',
): void {
    const toast = receiptWindow.document.querySelector<HTMLElement>(
        '[data-receipt-toast]',
    );
    const label = toast?.querySelector('[data-receipt-toast-message]');

    if (!toast || !label) {
        return;
    }

    label.textContent = message;
    toast.classList.toggle('error', tone === 'error');
    toast.classList.add('visible');

    receiptWindow.clearTimeout(toastTimer);
    toastTimer = receiptWindow.setTimeout(() => {
        toast.classList.remove('visible');
    }, TOAST_DURATION);
}

/**
 * Puts the signed link on the clipboard, reporting whether it landed so the
 * slip can say so either way.
 *
 * `navigator.clipboard` only exists on a secure origin, and an outlet served
 * over plain http has none, so the selection-based copy stands behind it rather
 * than the button silently doing nothing.
 */
async function copyReceiptLink(
    receiptWindow: Window,
    url: string,
): Promise<boolean> {
    if (receiptWindow.isSecureContext && receiptWindow.navigator.clipboard) {
        try {
            await receiptWindow.navigator.clipboard.writeText(url);

            return true;
        } catch {
            /* A denied permission or an unfocused window falls through. */
        }
    }

    const field = receiptWindow.document.createElement('textarea');

    field.value = url;
    field.setAttribute('readonly', '');
    field.style.left = '-9999px';
    field.style.position = 'fixed';
    receiptWindow.document.body.append(field);
    field.select();

    try {
        return receiptWindow.document.execCommand('copy');
    } catch {
        return false;
    } finally {
        field.remove();
    }
}

/**
 * The PDF writer is fetched on the click rather than imported at the top, so
 * the POS bundle never carries jsPDF for a button most sessions do not press.
 */
async function downloadPosReceiptPdf(
    receiptWindow: Window,
    receipt: PosReceipt,
    brand: CarwashBrand,
): Promise<void> {
    const { downloadPosReceiptPdf: writePdf } =
        await import('@/lib/posReceiptPdf');

    await writePdf(receiptWindow, receipt, brand);
}

export function mountPosReceiptDocument(
    receiptWindow: Window,
    receipt: PosReceipt,
    brand: CarwashBrand,
): void {
    receiptWindow.document.open();
    receiptWindow.document.write(renderPosReceiptDocument(receipt, brand));
    receiptWindow.document.close();

    /*
     * Nothing is staged before printing: what only belongs on paper — the
     * toolbar's absence, the QR's presence — is decided by @media print, so a
     * plain Ctrl+P produces the same sheet as the button.
     */
    receiptWindow.document
        .querySelector('[data-receipt-print]')
        ?.addEventListener('click', () => receiptWindow.print());

    const downloadButton =
        receiptWindow.document.querySelector<HTMLButtonElement>(
            '[data-receipt-download]',
        );

    downloadButton?.addEventListener('click', async () => {
        const downloadLabel = downloadButton.querySelector('span');

        downloadButton.disabled = true;

        if (downloadLabel !== null) {
            downloadLabel.textContent = 'Membuat…';
        }

        try {
            await downloadPosReceiptPdf(receiptWindow, receipt, brand);
        } finally {
            downloadButton.disabled = false;

            if (downloadLabel !== null) {
                downloadLabel.textContent = 'Unduh PDF';
            }
        }
    });

    /*
     * The button is read here rather than from the event: the handler awaits the
     * clipboard first, and by the time it resumes the click has finished
     * dispatching and `event.currentTarget` is already null.
     */
    const copyButton = receiptWindow.document.querySelector<HTMLButtonElement>(
        '[data-receipt-copy]',
    );

    copyButton?.addEventListener('click', async () => {
        if (receipt.publicUrl === null) {
            return;
        }

        const copyLabel = copyButton.querySelector('span');
        const copied = await copyReceiptLink(receiptWindow, receipt.publicUrl);

        if (!copied) {
            showReceiptToast(receiptWindow, 'Link gagal disalin', 'error');

            return;
        }

        showReceiptToast(receiptWindow, 'Link struk disalin');
        copyButton.classList.add('copied');
        copyButton
            .querySelector('svg')
            ?.replaceWith(...parseIcon(receiptWindow, toolbarIcons.check));

        if (copyLabel !== null) {
            copyLabel.textContent = 'Tersalin';
        }

        /* Falls back to the idle state so the button can be used again. */
        receiptWindow.setTimeout(() => {
            copyButton.classList.remove('copied');
            copyButton
                .querySelector('svg')
                ?.replaceWith(...parseIcon(receiptWindow, toolbarIcons.link));

            if (copyLabel !== null) {
                copyLabel.textContent = 'Salin Link';
            }
        }, TOAST_DURATION);
    });

    receiptWindow.document
        .querySelector('[data-receipt-close]')
        ?.addEventListener('click', () => receiptWindow.close());
}
