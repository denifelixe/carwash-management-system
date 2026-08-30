import { formatCurrency, formatDate } from '@/composables/useCarwashFormat';
import {
    brandContacts,
    brandMark,
    escapeHtml,
    printedAt,
} from '@/lib/printDocument';
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
    ${metaRow('Plat', receipt.plate)}
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
</footer>`;
}

function receiptStyles(): string {
    return `:root { color-scheme: light; }
* { box-sizing: border-box; margin: 0; padding: 0; }
body {
    background: #e2e8f0;
    color: #0f172a;
    font-family: 'Consolas', 'Courier New', ui-monospace, monospace;
    font-size: 11px;
    line-height: 1.45;
    padding: 12px 0 28px;
}
.toolbar {
    display: flex;
    gap: 6px;
    margin: 0 auto 12px;
    width: ${PAPER_WIDTH};
}
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
.paper {
    background: #ffffff;
    box-shadow: 0 8px 24px rgba(15, 23, 42, 0.18);
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
@page { margin: 0; size: ${PAPER_WIDTH} auto; }
@media print {
    body { background: #ffffff; padding: 0; }
    .toolbar { display: none; }
    .paper { box-shadow: none; padding: 0 3mm 6mm; }
}`;
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
    <button type="button" data-receipt-print>Cetak</button>
    <button type="button" class="secondary" data-receipt-close>Tutup</button>
</div>
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
        '',
        `pos-receipt-${receipt.orderNo}`,
        `popup=yes,width=${WINDOW_WIDTH},height=${WINDOW_HEIGHT},scrollbars=yes,resizable=yes`,
    );

    if (!receiptWindow) {
        return null;
    }

    receiptWindow.document.open();
    receiptWindow.document.write(renderPosReceiptDocument(receipt, brand));
    receiptWindow.document.close();

    receiptWindow.document
        .querySelector('[data-receipt-print]')
        ?.addEventListener('click', () => receiptWindow.print());
    receiptWindow.document
        .querySelector('[data-receipt-close]')
        ?.addEventListener('click', () => receiptWindow.close());

    receiptWindow.focus();

    return receiptWindow;
}
