<script setup lang="ts">
import { Head, router, useForm } from '@inertiajs/vue3';
import {
    Banknote,
    ChevronDown,
    CircleCheck,
    ClipboardList,
    Clock,
    CreditCard,
    Plus,
    Printer,
    Sparkles,
    Trash2,
    Wallet,
} from '@lucide/vue';
import { computed, nextTick, onMounted, ref, watch } from 'vue';
import {
    index as indexPos,
    store as storePosPayment,
    storeMember as storePosMember,
} from '@/actions/App/Http/Controllers/Admin/PosController';
import RecapQrController from '@/actions/App/Http/Controllers/Admin/RecapQrController';
import TransactionShiftDialog from '@/components/admin/TransactionShiftDialog.vue';
import AccordionSection from '@/components/demo/AccordionSection.vue';
import DataToolbar from '@/components/demo/DataToolbar.vue';
import DateFilterBar from '@/components/demo/DateFilterBar.vue';
import EmptyState from '@/components/demo/EmptyState.vue';
import ModalDialog from '@/components/demo/ModalDialog.vue';
import RecapPrintMenu from '@/components/demo/RecapPrintMenu.vue';
import StatCard from '@/components/demo/StatCard.vue';
import StatusPill from '@/components/demo/StatusPill.vue';
import MoneyInput from '@/components/MoneyInput.vue';
import {
    formatCurrency,
    formatDate,
    formatNumber,
} from '@/composables/useCarwashFormat';
import { useCarwashWorkflow } from '@/composables/useCarwashWorkflow';
import { matchingTransactionShifts } from '@/composables/useTransactionShift';
import { financialPaymentBreakdown } from '@/lib/paymentChannelBreakdown';
import { openPosReceiptWindow, paymentChannelLabel } from '@/lib/posReceipt';
import type {
    PosPaymentBreakdown,
    PosReceipt,
    PosReceiptHistoryEntry,
    PosReceiptLine,
} from '@/lib/posReceipt';
import { absoluteUrl, openRecapSheetWindow } from '@/lib/recapSheet';
import type { RecapPaper, RecapSheet } from '@/lib/recapSheet';
import { formatPlate, normalizePlate } from '@/lib/vehiclePlate';
import demoAdmin from '@/routes/demo/admin';
import type {
    CarwashDateFilter,
    CarwashBrand,
    CarwashCustomer,
    CarwashOrder,
    CarwashPersona,
    CarwashReward,
    CarwashService,
    CarwashTransaction,
    CarwashTransactionShiftAssignment,
    CarwashTransactionShiftOption,
    CarwashWorkShift,
} from '@/types/demo';

const props = defineProps<{
    mode: 'demo' | 'live';
    brand: CarwashBrand;
    orders: CarwashOrder[];
    dailyOrders: CarwashOrder[];
    partialPaymentBookings: CarwashOrder[];
    filters: CarwashDateFilter;
    shifts: CarwashWorkShift[];
    services: CarwashService[];
    customers: CarwashCustomer[];
    rewards: CarwashReward[];
    paymentMethods: string[];
    persona: CarwashPersona;
    transactionShift: CarwashTransactionShiftAssignment;
    capabilities: {
        create: boolean;
    };
}>();

/** What the cashier keeps hold of while the payment is written server-side. */
interface PaymentSnapshot {
    intent: 'settlement' | 'partial';
    amount: number;
    reward: CarwashReward | null;
    rewardDiscount: number;
    cashierDiscount: number;
    discount: number;
    /** Bill total before this payment's discounts came off. */
    subtotal: number;
    /** Discount an earlier payment already took off the listed services. */
    priorDiscount: number;
    previouslyPaid: number;
    tendered: number;
    change: number;
    lines: PosReceiptLine[];
    history: PosReceiptHistoryEntry[];
    breakdown: PosPaymentBreakdown[];
}

/** What the cashier types into the "jadikan member" panel. */
interface MemberDraft {
    plate: string;
    vehicle: string;
    name: string;
    phone: string;
}

interface PaymentChannelRow {
    id: number;
    method: string;
}

interface PendingPayment {
    order: CarwashOrder;
    snapshot: PaymentSnapshot;
}

interface PaymentRecapRow {
    label: string;
    count: number;
    amount: number;
}

interface PaymentRecapSelection {
    category: 'all' | 'type' | 'channel';
    label: string;
}

interface PaymentRecapDetail {
    transaction: CarwashTransaction;
    order: CarwashOrder;
    amount: number;
}

interface PaymentRecapOrderDetail {
    order: CarwashOrder;
    sourceTransactionId: string;
    highlightedTransactionId: string | null;
}

/** A recap tab: 'total', a shift key from the master list, or the bucket below. */
type PaymentRecapShift = string;

const paymentRecapTotalKey = 'total';
const paymentRecapUnassignedKey = 'tanpa-shift';
const unassignedShiftLabel = 'Tanpa Shift';

const bankPaymentMethods = ['Kredit', 'Debit', 'Transfer'];
const bankOptions = [
    'BCA',
    'Mandiri',
    'BNI',
    'BRI',
    'CIMB Niaga',
    'Bank lainnya',
];
const eMoneyOptions = [
    'Flazz BCA',
    'e-Money Mandiri',
    'BRIZZI BRI',
    'TapCash BNI',
    'JakCard DKI',
    'Kartu lainnya',
];

const search = ref<string>('');
const partialPaymentSearch = ref<string>('');
const completedSearch = ref<string>('');
const selectedPaymentRecap = ref<PaymentRecapSelection | null>(null);
const paymentRecapDetailsElement = ref<HTMLElement | null>(null);
const selectedPaymentRecapTransaction = ref<PaymentRecapDetail | null>(null);
const selectedPaymentRecapOrder = ref<PaymentRecapOrderDetail | null>(null);
/*
 * The recap tab is client state — switching it never hits the server — so it
 * is read straight off the query rather than through props. That is what makes
 * a printed recap's link land on the shift it was taken from.
 */
const activePaymentRecapShift = ref<PaymentRecapShift>(paymentRecapTotalKey);

onMounted(() => {
    activePaymentRecapShift.value =
        new URLSearchParams(window.location.search).get('shift') ??
        paymentRecapTotalKey;
});
const selectedOrderId = ref<number | null>(null);
const paymentIntent = ref<'settlement' | 'partial'>('settlement');
const selectedRewardId = ref<number | null>(null);
const discountAmount = ref<number>(0);
const paymentTotalInput = ref<number>(0);
const isPaymentTotalEdited = ref<boolean>(false);
let nextPaymentChannelRowId = 1;
const paymentChannelRows = ref<PaymentChannelRow[]>([
    { id: nextPaymentChannelRowId, method: '' },
]);
const paymentAmounts = ref<Record<string, number>>(
    Object.fromEntries(props.paymentMethods.map((method) => [method, 0])),
);
const paymentProviders = ref<Record<string, string>>(
    Object.fromEntries(props.paymentMethods.map((method) => [method, ''])),
);
const paymentReferences = ref<Record<string, string>>(
    Object.fromEntries(props.paymentMethods.map((method) => [method, ''])),
);
const emptyMemberDraft: MemberDraft = {
    plate: '',
    vehicle: '',
    name: '',
    phone: '',
};
const memberDraft = ref<MemberDraft>({ ...emptyMemberDraft });
const receipt = ref<PosReceipt | null>(null);
/** Set when the browser refuses the slip window so the cashier can retry. */
const isReceiptWindowBlocked = ref<boolean>(false);
const isPaymentRecapOpen = ref<boolean>(false);
/** Set when the browser refuses the recap window so the cashier can retry. */
const isRecapWindowBlocked = ref<boolean>(false);

const partialPaymentBookingIds = new Set(
    props.partialPaymentBookings.map((order) => order.id),
);

const workflow = useCarwashWorkflow();

if (props.mode === 'demo') {
    workflow.hydrateOrders([
        ...props.dailyOrders,
        ...props.partialPaymentBookings,
    ]);
    workflow.hydrateCustomers(props.customers);
}

/**
 * The day's orders plus the bookings still waiting for their car. A booking due
 * today sits in both lists, so it is keyed by id to appear once.
 */
const liveOrders = computed<CarwashOrder[]>(() =>
    Array.from(
        new Map(
            [...props.dailyOrders, ...props.partialPaymentBookings].map(
                (order) => [order.id, order],
            ),
        ).values(),
    ),
);

const orderList = computed<CarwashOrder[]>(() =>
    props.mode === 'demo' ? workflow.orders.value : liveOrders.value,
);

const customerList = computed<CarwashCustomer[]>(() =>
    props.mode === 'demo' ? workflow.customers.value : props.customers,
);

const settlementOrderList = computed<CarwashOrder[]>(() =>
    orderList.value.filter(
        (order) =>
            props.filters.date === '' || order.date === props.filters.date,
    ),
);

const outstandingTotal = computed<number>(() =>
    settlementOrderList.value.reduce(
        (sum, order) => sum + order.total - order.paidAmount,
        0,
    ),
);

const paymentRecapTransactions = computed(() =>
    Array.from(
        new Map(
            orderList.value
                .flatMap((order) => order.transactions)
                .filter(
                    (transaction) =>
                        transaction.amount > 0 &&
                        (props.filters.date === '' ||
                            transaction.date === props.filters.date),
                )
                .map((transaction) => [transaction.id, transaction]),
        ).values(),
    ),
);

const paymentRecapPartialTransactions = computed(() =>
    paymentRecapTransactions.value.filter(
        (transaction) => transaction.type === 'Pembayaran Sebagian',
    ),
);

const paymentRecapFinalTransactions = computed(() =>
    paymentRecapTransactions.value.filter(
        (transaction) => transaction.type === 'Pembayaran Lunas',
    ),
);

/**
 * One tab per shift on the master list, so renaming a shift or rostering a new
 * one reaches the recap without a code change, bracketed by the day's total and
 * the payments no shift claims.
 */
const paymentRecapShiftOptions = computed<
    Array<{ key: PaymentRecapShift; label: string; caption: string }>
>(() => [
    {
        key: paymentRecapTotalKey,
        label: 'Total',
        caption: 'Seluruh jam operasional',
    },
    ...props.shifts.map((shift) => ({
        key: shift.key,
        label: shift.name,
        caption: shift.time ?? 'Tanpa jam kerja',
    })),
    {
        key: paymentRecapUnassignedKey,
        label: unassignedShiftLabel,
        caption: 'Kasir tanpa shift',
    },
]);

/** Each shift tab carries its own transaction count so the split reads before switching. */
const paymentRecapShiftTabs = computed(() =>
    paymentRecapShiftOptions.value.map((option) => ({
        ...option,
        count: paymentRecapTransactions.value.filter((transaction) =>
            isTransactionInShift(transaction, option.key),
        ).length,
    })),
);

/**
 * A payment is filtered by the shift name resolved and stamped when it was
 * written. Reporting never re-derives that value from the transaction hour,
 * so later schedule changes cannot move historical money between tabs.
 */
function isTransactionInShift(
    transaction: CarwashTransaction,
    shift: PaymentRecapShift,
): boolean {
    if (shift === paymentRecapTotalKey) {
        return true;
    }

    const shiftName = transaction.shift ?? null;

    /*
     * Payments taken without a shift land here, and so do payments stamped with
     * a shift that has since been renamed or retired — otherwise they would
     * vanish from every tab but Total, and the tabs would not add up to it.
     */
    if (shift === paymentRecapUnassignedKey) {
        return (
            shiftName === null ||
            !props.shifts.some((option) => option.name === shiftName)
        );
    }

    return (
        props.shifts.find((option) => option.key === shift)?.name === shiftName
    );
}

function isTransactionInActiveShift(transaction: CarwashTransaction): boolean {
    return isTransactionInShift(transaction, activePaymentRecapShift.value);
}

const activePaymentRecapTransactions = computed<CarwashTransaction[]>(() =>
    paymentRecapTransactions.value.filter(isTransactionInActiveShift),
);

const activePaymentRecapFinalTransactions = computed<CarwashTransaction[]>(() =>
    paymentRecapFinalTransactions.value.filter(isTransactionInActiveShift),
);

const activePaymentRecapPartialTransactions = computed<CarwashTransaction[]>(
    () =>
        paymentRecapPartialTransactions.value.filter(
            isTransactionInActiveShift,
        ),
);

const paymentRecapFinalTotal = computed<number>(() =>
    paymentRecapFinalTransactions.value.reduce(
        (total, transaction) => total + transaction.amount,
        0,
    ),
);

const paymentRecapPartialTotal = computed<number>(() =>
    paymentRecapPartialTransactions.value.reduce(
        (total, transaction) => total + transaction.amount,
        0,
    ),
);

const collectedTotal = computed<number>(
    () => paymentRecapFinalTotal.value + paymentRecapPartialTotal.value,
);

const activePaymentRecapFinalTotal = computed<number>(() =>
    activePaymentRecapFinalTransactions.value.reduce(
        (total, transaction) => total + transaction.amount,
        0,
    ),
);

const activePaymentRecapPartialTotal = computed<number>(() =>
    activePaymentRecapPartialTransactions.value.reduce(
        (total, transaction) => total + transaction.amount,
        0,
    ),
);

const activePaymentRecapTotal = computed<number>(
    () =>
        activePaymentRecapFinalTotal.value +
        activePaymentRecapPartialTotal.value,
);

const paymentRecapTransactionCount = computed<number>(
    () => activePaymentRecapTransactions.value.length,
);

function partialPaymentTransactions(order: CarwashOrder) {
    return order.transactions.filter(
        (transaction) => transaction.type === 'Pembayaran Sebagian',
    );
}

const paymentRecapFinalOrderCount = computed<number>(
    () =>
        new Set(
            activePaymentRecapFinalTransactions.value.map(
                (transaction) => transaction.orderId,
            ),
        ).size,
);

const partialPaymentRecapLabel = 'Pembayaran Sebagian/Booking';
const finalPaymentRecapLabel = 'Pembayaran Sisa/Lunas (Order Selesai)';

const paymentRecapByType = computed<PaymentRecapRow[]>(() => [
    {
        label: partialPaymentRecapLabel,
        count: activePaymentRecapPartialTransactions.value.length,
        amount: activePaymentRecapPartialTotal.value,
    },
    {
        label: finalPaymentRecapLabel,
        count: paymentRecapFinalOrderCount.value,
        amount: activePaymentRecapFinalTotal.value,
    },
]);

const paymentRecapByChannel = computed<PaymentRecapRow[]>(() => {
    const channels = new Map<string, PaymentRecapRow>();

    for (const transaction of activePaymentRecapTransactions.value) {
        for (const channel of transaction.channelBreakdown) {
            const row = channels.get(channel.label) ?? {
                label: channel.label,
                count: 0,
                amount: 0,
            };

            row.count += 1;
            row.amount += channel.amount;
            channels.set(channel.label, row);
        }
    }

    const channelOrder = new Map(
        props.paymentMethods.map((method, index) => [method, index]),
    );

    return Array.from(channels.values()).sort((first, second) => {
        const firstMethod = first.label.split(' · ')[0];
        const secondMethod = second.label.split(' · ')[0];

        return (
            (channelOrder.get(firstMethod) ?? Number.MAX_SAFE_INTEGER) -
            (channelOrder.get(secondMethod) ?? Number.MAX_SAFE_INTEGER)
        );
    });
});

/**
 * What the till hands over at the end of a shift: the money taken, split by the
 * kind of payment and by the channel it arrived through. The drill-down list
 * under those sections is deliberately left off — the sheet is a recap, and a
 * cashier closing up does not need every transaction reprinted with it.
 */
/**
 * Where a printed recap points back to: the same console, day and shift. The
 * QR is encoded by the server, which builds the address itself from these two
 * filters rather than taking a destination from the page.
 */
function recapLinks(
    date: string,
    shift: string,
): { sourceUrl: string; qrUrl: string } {
    return {
        sourceUrl: absoluteUrl(indexPos.url({ query: { date, shift } })),
        qrUrl: absoluteUrl(
            RecapQrController.url({ query: { page: 'pos', date, shift } }),
        ),
    };
}

function posRecapSheet(): RecapSheet {
    const tab = paymentRecapShiftTabs.value.find(
        (option) => option.key === activePaymentRecapShift.value,
    );
    const channels = paymentRecapByChannel.value;
    const channelTotal = channels.reduce(
        (total, channel) => total + channel.amount,
        0,
    );
    const channelCount = channels.reduce(
        (total, channel) => total + channel.count,
        0,
    );

    return {
        title: 'Rekap Pembayaran Diterima',
        slug: 'rekap-kasir-pos',
        periodLabel: formatDate(props.filters.date),
        shiftLabel: tab?.label ?? 'Total',
        shiftCaption: tab?.caption ?? null,
        meta: [{ label: 'Dicetak oleh', value: props.persona.name }],
        ...recapLinks(props.filters.date, activePaymentRecapShift.value),
        summary: [
            {
                label: 'Total pembayaran diterima',
                value: formatCurrency(activePaymentRecapTotal.value),
                caption: 'Termasuk pembayaran sebagian dan pembayaran lunas',
                tone: 'positive',
            },
            {
                label: 'Jumlah transaksi',
                value: formatNumber(paymentRecapTransactionCount.value),
            },
            {
                label: finalPaymentRecapLabel,
                value: formatNumber(paymentRecapFinalOrderCount.value),
            },
        ],
        tables: [
            {
                heading: 'Jenis transaksi',
                columns: ['Jenis', 'Jumlah', 'Nominal'],
                rows: paymentRecapByType.value.map((row) => ({
                    label: row.label,
                    values: [
                        formatNumber(row.count),
                        formatCurrency(row.amount),
                    ],
                })),
                total: {
                    label: 'Total',
                    values: [
                        formatNumber(paymentRecapTransactionCount.value),
                        formatCurrency(activePaymentRecapTotal.value),
                    ],
                },
            },
            {
                heading: 'Kanal pembayaran',
                columns: ['Kanal', 'Jumlah', 'Nominal'],
                rows: channels.map((row) => ({
                    label: row.label,
                    values: [
                        formatNumber(row.count),
                        formatCurrency(row.amount),
                    ],
                })),
                /*
                 * One payment can be split across channels, so the channel
                 * count runs ahead of the transaction count and is footed on
                 * its own rather than restating the card above.
                 */
                total: {
                    label: 'Total',
                    values: [
                        formatNumber(channelCount),
                        formatCurrency(channelTotal),
                    ],
                },
                emptyMessage: 'Belum ada pembayaran pada periode ini.',
            },
        ],
        timezone: props.filters.timezone,
    };
}

/** Hands the recap to its own window; a blocked window is flagged, not fatal. */
function printRecap(paper: RecapPaper): void {
    isRecapWindowBlocked.value =
        openRecapSheetWindow(posRecapSheet(), props.brand, paper) === null;
}

const paymentRecapDetails = computed<PaymentRecapDetail[]>(() => {
    const selection = selectedPaymentRecap.value;

    if (!selection) {
        return [];
    }

    return activePaymentRecapTransactions.value.flatMap((transaction) => {
        const order = orderList.value.find(
            (candidate) => candidate.id === transaction.orderId,
        );

        if (!order) {
            return [];
        }

        if (selection.category === 'all') {
            return [{ transaction, order, amount: transaction.amount }];
        }

        if (selection.category === 'type') {
            /*
             * Both rows are counted per transaction, so both are listed that
             * way too. Matching the settled row by order instead dragged the
             * order's earlier instalments in, listing them under a total that
             * never included them and repeating them under the partial row.
             */
            const matchesSelection =
                selection.label === partialPaymentRecapLabel
                    ? transaction.type === 'Pembayaran Sebagian'
                    : transaction.type === 'Pembayaran Lunas';

            return matchesSelection
                ? [{ transaction, order, amount: transaction.amount }]
                : [];
        }

        const selectedChannel = transaction.channelBreakdown.find(
            (channel) => channel.label === selection.label,
        );

        return selectedChannel
            ? [{ transaction, order, amount: selectedChannel.amount }]
            : [];
    });
});

const paymentRecapDetailTotal = computed<number>(() =>
    paymentRecapDetails.value.reduce(
        (total, detail) => total + detail.amount,
        0,
    ),
);

async function selectPaymentRecap(
    category: PaymentRecapSelection['category'],
    label: string,
): Promise<void> {
    selectedPaymentRecap.value = { category, label };
    selectedPaymentRecapTransaction.value = null;
    selectedPaymentRecapOrder.value = null;

    await nextTick();
    paymentRecapDetailsElement.value?.scrollIntoView({
        behavior: 'smooth',
        block: 'start',
    });
}

function showPaymentRecapOrder(detail: PaymentRecapDetail): void {
    selectedPaymentRecapTransaction.value = null;
    selectedPaymentRecapOrder.value = {
        order: detail.order,
        sourceTransactionId: detail.transaction.id,
        highlightedTransactionId: null,
    };
}

function showPaymentRecapTransaction(detail: PaymentRecapDetail): void {
    selectedPaymentRecapTransaction.value = detail;
    selectedPaymentRecapOrder.value = null;
}

function showSelectedPaymentRecapTransactionOrder(): void {
    const detail = selectedPaymentRecapTransaction.value;

    if (!detail) {
        return;
    }

    selectedPaymentRecapOrder.value = {
        order: detail.order,
        sourceTransactionId: detail.transaction.id,
        highlightedTransactionId: detail.transaction.id,
    };
}

function isPaymentRecapSelected(
    category: PaymentRecapSelection['category'],
    label: string,
): boolean {
    return (
        selectedPaymentRecap.value?.category === category &&
        selectedPaymentRecap.value.label === label
    );
}

function selectPaymentRecapShift(shift: PaymentRecapShift): void {
    activePaymentRecapShift.value = shift;
    selectedPaymentRecap.value = null;
    selectedPaymentRecapTransaction.value = null;
    selectedPaymentRecapOrder.value = null;
}

/* A shift retired from the master takes its tab with it, so the recap falls
 * back to the total rather than reading off a tab that is no longer there. */
watch(paymentRecapShiftOptions, (options) => {
    if (
        !options.some((option) => option.key === activePaymentRecapShift.value)
    ) {
        selectPaymentRecapShift(paymentRecapTotalKey);
    }
});

/** Arrow-key roving between shift tabs, as expected of a `role="tablist"`. */
function movePaymentRecapShift(offset: number): void {
    const options = paymentRecapShiftOptions.value;
    const currentIndex = options.findIndex(
        (option) => option.key === activePaymentRecapShift.value,
    );
    const nextIndex = (currentIndex + offset + options.length) % options.length;
    const nextShift = options[nextIndex];

    if (!nextShift) {
        return;
    }

    selectPaymentRecapShift(nextShift.key);
    document.getElementById(`payment-recap-tab-${nextShift.key}`)?.focus();
}

function closePaymentRecap(): void {
    isPaymentRecapOpen.value = false;
    selectedPaymentRecap.value = null;
    selectedPaymentRecapTransaction.value = null;
    selectedPaymentRecapOrder.value = null;
    activePaymentRecapShift.value = paymentRecapTotalKey;
    isRecapWindowBlocked.value = false;
}

function orderTypeLabel(order: CarwashOrder): string {
    if (order.source === 'booking') {
        return 'Booking';
    }

    return order.customerId === null
        ? 'Walk-in non-customer'
        : 'Walk-in customer';
}

function bookingDisplayStatus(order: CarwashOrder): string {
    if (order.source !== 'booking') {
        return order.status;
    }

    return order.date > props.filters.today ? 'Booking Mendatang' : 'booking';
}

const visibleOrders = computed<CarwashOrder[]>(() => {
    const query = search.value.trim().toLowerCase();

    return settlementOrderList.value.filter((order) => {
        const isReadyForSettlement = order.status === 'pelunasan';
        const matchesQuery =
            query === '' ||
            order.orderNo.toLowerCase().includes(query) ||
            order.customer.toLowerCase().includes(query) ||
            order.plate.toLowerCase().includes(query);

        return isReadyForSettlement && matchesQuery;
    });
});

const receiptHeadline = computed<string>(() => {
    if (!receipt.value) {
        return '';
    }

    if (receipt.value.isReprint) {
        return 'Salinan struk';
    }

    return receipt.value.isSettled
        ? 'Pembayaran berhasil'
        : 'Pembayaran sebagian diterima';
});

/**
 * Settled orders for the filtered day, newest first. The cashier only comes
 * here to reprint, so the most recent settlement sits at the top.
 */
const visibleCompletedOrders = computed<CarwashOrder[]>(() => {
    const query = completedSearch.value.trim().toLowerCase();

    return settlementOrderList.value
        .filter((order) => {
            const isCompleted =
                order.status === 'selesai' && order.paymentStatus === 'lunas';
            const matchesQuery =
                query === '' ||
                order.orderNo.toLowerCase().includes(query) ||
                order.invoice.toLowerCase().includes(query) ||
                order.customer.toLowerCase().includes(query) ||
                order.plate.toLowerCase().includes(query);

            return isCompleted && matchesQuery;
        })
        .sort(
            (first, second) =>
                second.date.localeCompare(first.date) ||
                second.orderNo.localeCompare(first.orderNo),
        );
});

const visiblePartialPaymentBookings = computed<CarwashOrder[]>(() => {
    const query = partialPaymentSearch.value.trim().toLowerCase();

    return orderList.value
        .filter((order) => {
            const isScheduledBooking = partialPaymentBookingIds.has(order.id);
            const matchesQuery =
                query === '' ||
                order.orderNo.toLowerCase().includes(query) ||
                order.customer.toLowerCase().includes(query) ||
                order.plate.toLowerCase().includes(query);

            return isScheduledBooking && matchesQuery;
        })
        .sort(
            (first, second) =>
                first.date.localeCompare(second.date) ||
                first.orderNo.localeCompare(second.orderNo),
        );
});

/**
 * The queue mixes cars due today with ones booked for later, so the panel
 * header splits the total rather than leaving the cashier to count rows.
 */
const partialPaymentBookingCaption = computed<string>(() => {
    const bookings = visiblePartialPaymentBookings.value;
    const today = bookings.filter(
        (order) => order.date === props.filters.today,
    ).length;

    return `${bookings.length} Booking (${today} Hari Ini - ${bookings.length - today} Mendatang)`;
});

const selectedOrder = computed<CarwashOrder | null>(
    () =>
        orderList.value.find((order) => order.id === selectedOrderId.value) ??
        null,
);

const paymentAmountLabel = computed<string>(() =>
    paymentIntent.value === 'partial'
        ? 'Pembayaran Sebagian/Booking'
        : 'Pembayaran Sebagian/Lunas',
);

const orderCustomer = computed<CarwashCustomer | null>(
    () =>
        customerList.value.find(
            (customer) => customer.id === selectedOrder.value?.customerId,
        ) ?? null,
);

const orderServices = computed(() =>
    (selectedOrder.value?.serviceItems ?? []).map((item) => ({
        ...item,
        icon:
            props.services.find((service) => service.id === item.serviceId)
                ?.icon ?? '✨',
        name: item.label,
        price: item.totalPrice,
        id: item.serviceVariationId,
    })),
);

const orderServiceTotal = computed<number>(() =>
    orderServices.value.reduce((total, service) => total + service.price, 0),
);

const dueAmount = computed<number>(() => {
    const order = selectedOrder.value;

    if (!order) {
        return 0;
    }

    return Math.max(order.total - order.paidAmount, 0);
});

const redeemableRewards = computed<CarwashReward[]>(() => {
    const customer = orderCustomer.value;
    const order = selectedOrder.value;

    if (!customer || !order || order.reward !== '—') {
        return [];
    }

    return props.rewards.filter(
        (reward) =>
            reward.status === 'aktif' &&
            reward.stock > 0 &&
            reward.requiredStamps <= customer.stamps &&
            reward.applicableServiceIds.some((serviceId) =>
                order.serviceIds.includes(serviceId),
            ),
    );
});

const selectedReward = computed<CarwashReward | null>(
    () =>
        redeemableRewards.value.find(
            (reward) => reward.id === selectedRewardId.value,
        ) ?? null,
);

const rewardDiscount = computed<number>(() => {
    if (!selectedReward.value || orderServices.value.length === 0) {
        return 0;
    }

    const applicableServicePrices = orderServices.value
        .filter((service) =>
            selectedReward.value?.applicableServiceIds.includes(
                service.serviceId,
            ),
        )
        .map((service) => service.price);

    if (applicableServicePrices.length === 0) {
        return 0;
    }

    const cheapestApplicableService = Math.min(...applicableServicePrices);

    return Math.min(cheapestApplicableService, dueAmount.value);
});

const maximumCashierDiscount = computed<number>(() =>
    Math.max(dueAmount.value - rewardDiscount.value, 0),
);

const cashierDiscount = computed<number>(() => {
    const typed = Math.trunc(discountAmount.value);

    return Number.isFinite(typed)
        ? Math.min(Math.max(typed, 0), maximumCashierDiscount.value)
        : 0;
});

const totalDiscount = computed<number>(
    () => rewardDiscount.value + cashierDiscount.value,
);

const amountAfterDiscount = computed<number>(() =>
    Math.max(dueAmount.value - totalDiscount.value, 0),
);

const paymentBreakdown = computed<PosPaymentBreakdown[]>(() =>
    props.paymentMethods
        .map((method) => {
            const typed = Math.trunc(paymentAmounts.value[method] ?? 0);

            return {
                method,
                amount: Number.isFinite(typed) ? Math.max(typed, 0) : 0,
                provider: paymentProviders.value[method] ?? '',
                reference: paymentReferences.value[method]?.trim() ?? '',
            };
        })
        .filter((payment) => payment.amount > 0),
);

const paymentProvidersAreValid = computed<boolean>(() =>
    paymentBreakdown.value.every(
        (payment) =>
            !requiresPaymentProvider(payment.method) || payment.provider !== '',
    ),
);

const canAddPaymentChannel = computed<boolean>(
    () =>
        paymentChannelRows.value.every((row) => row.method !== '') &&
        paymentChannelRows.value.length < props.paymentMethods.length,
);

const tenderedTotal = computed<number>(() =>
    paymentBreakdown.value.reduce(
        (total, payment) => total + payment.amount,
        0,
    ),
);

const paymentTotal = computed<number>(() => {
    const typed = Math.trunc(paymentTotalInput.value);

    return Number.isFinite(typed)
        ? Math.min(Math.max(typed, 0), amountAfterDiscount.value)
        : 0;
});

const payAmount = computed<number>(() => paymentTotal.value);

const remainingAfterPayment = computed<number>(() =>
    Math.max(amountAfterDiscount.value - paymentTotal.value, 0),
);

const remainingTenderAmount = computed<number>(() =>
    Math.max(paymentTotal.value - tenderedTotal.value, 0),
);

const changeAmount = computed<number>(() =>
    Math.max(tenderedTotal.value - paymentTotal.value, 0),
);

const cashTenderedTotal = computed<number>(() =>
    paymentBreakdown.value
        .filter((payment) => payment.method === 'Tunai')
        .reduce((total, payment) => total + payment.amount, 0),
);

const changeIsCoveredByCash = computed<boolean>(
    () => changeAmount.value <= cashTenderedTotal.value,
);

const canSubmit = computed<boolean>(() => {
    if (!selectedOrder.value || dueAmount.value <= 0) {
        return false;
    }

    const isPaidByDiscount =
        amountAfterDiscount.value === 0 && totalDiscount.value > 0;

    return (
        isPaidByDiscount ||
        (paymentTotal.value > 0 &&
            tenderedTotal.value >= paymentTotal.value &&
            changeIsCoveredByCash.value &&
            paymentProvidersAreValid.value)
    );
});

watch(amountAfterDiscount, (nextAmount, previousAmount) => {
    if (paymentIntent.value === 'partial' && !isPaymentTotalEdited.value) {
        paymentTotalInput.value = 0;

        return;
    }

    if (
        !isPaymentTotalEdited.value ||
        paymentTotalInput.value >= previousAmount
    ) {
        paymentTotalInput.value = nextAmount;

        return;
    }

    paymentTotalInput.value = Math.min(paymentTotalInput.value, nextAmount);
});

function resetPaymentInputs(): void {
    selectedRewardId.value = null;
    discountAmount.value = 0;
    paymentTotalInput.value = 0;
    isPaymentTotalEdited.value = false;
    paymentChannelRows.value = [{ id: ++nextPaymentChannelRowId, method: '' }];
    paymentAmounts.value = Object.fromEntries(
        props.paymentMethods.map((method) => [method, 0]),
    );
    paymentProviders.value = Object.fromEntries(
        props.paymentMethods.map((method) => [method, '']),
    );
    paymentReferences.value = Object.fromEntries(
        props.paymentMethods.map((method) => [method, '']),
    );
}

function clearPaymentMethod(method: string): void {
    paymentAmounts.value[method] = 0;
    paymentProviders.value[method] = '';
    paymentReferences.value[method] = '';
}

function selectPaymentMethod(row: PaymentChannelRow, event: Event): void {
    const previousMethod = row.method;
    const method = (event.target as HTMLSelectElement).value;

    row.method = method;

    if (previousMethod !== '' && previousMethod !== method) {
        clearPaymentMethod(previousMethod);
    }
}

function isPaymentMethodDisabled(method: string, rowId: number): boolean {
    return paymentChannelRows.value.some(
        (row) => row.id !== rowId && row.method === method,
    );
}

function addPaymentChannel(): void {
    if (!canAddPaymentChannel.value) {
        return;
    }

    paymentChannelRows.value.push({
        id: ++nextPaymentChannelRowId,
        method: '',
    });
}

function removePaymentChannel(rowId: number): void {
    const row = paymentChannelRows.value.find(
        (paymentRow) => paymentRow.id === rowId,
    );

    if (row?.method) {
        clearPaymentMethod(row.method);
    }

    paymentChannelRows.value = paymentChannelRows.value.filter(
        (paymentRow) => paymentRow.id !== rowId,
    );
}

function selectOrder(
    order: CarwashOrder,
    intent: 'settlement' | 'partial' = 'settlement',
): void {
    paymentIntent.value = intent;
    selectedOrderId.value = order.id;
    resetPaymentInputs();
    fillMemberDraft(order);
    paymentTotalInput.value = intent === 'settlement' ? dueAmount.value : 0;
}

function resetPanel(): void {
    selectedOrderId.value = null;
    paymentIntent.value = 'settlement';
    resetPaymentInputs();
    memberDraft.value = { ...emptyMemberDraft };
    memberForm.clearErrors();
}

function fillRemainingAmount(method: string): void {
    paymentAmounts.value[method] =
        (paymentAmounts.value[method] ?? 0) + remainingTenderAmount.value;
}

function markPaymentTotalEdited(): void {
    isPaymentTotalEdited.value = true;
}

function requiresPaymentProvider(method: string): boolean {
    return bankPaymentMethods.includes(method) || method === 'E-Money';
}

function paymentProviderOptions(method: string): string[] {
    return method === 'E-Money' ? eMoneyOptions : bankOptions;
}

function paymentProviderLabel(method: string): string {
    return method === 'E-Money' ? 'Kartu' : 'Bank';
}

function transactionChannelsLabel(transaction: CarwashTransaction): string {
    return transaction.channelBreakdown
        .map((channel) => `${channel.label} ${formatCurrency(channel.amount)}`)
        .join(' + ');
}

function paymentTransactionLabel(transaction: CarwashTransaction): string {
    return transaction.type === 'Pembayaran Sebagian'
        ? partialPaymentRecapLabel
        : finalPaymentRecapLabel;
}

/** Uses the same public transaction reference shown by the Finance ledger. */
function paymentTransactionReference(
    transaction: CarwashTransaction,
    order: CarwashOrder,
): string {
    const categoryCode =
        transaction.type === 'Pembayaran Sebagian' ? 'PSO' : 'PLO';
    const dateCode = transaction.date.replaceAll('-', '').slice(2);
    const transactionIndex = order.transactions.findIndex(
        (candidate) => candidate.id === transaction.id,
    );
    const transactionNumber = Math.max(transactionIndex + 1, 1);
    const stableIdentifier = `${order.orderNo}-TRX-${transactionNumber}`
        .toUpperCase()
        .replace(/[^A-Z0-9]+/g, '');

    return `TRX-${categoryCode}-${dateCode}-${stableIdentifier}`;
}

function paymentTransactionRecorder(transaction: CarwashTransaction): string {
    return (
        transaction.recordedBy ??
        (transaction.time >= '15.00' ? 'Rina Marlina' : 'Yuni Astuti')
    );
}

/** A payment taken by a cashier who is on no shift says so on the slip. */
function paymentTransactionShift(transaction: CarwashTransaction): string {
    return transaction.shift ?? unassignedShiftLabel;
}

function paymentHistoryTypeLabel(transaction: CarwashTransaction): string {
    return transaction.type === 'Pembayaran Sebagian'
        ? 'Pembayaran Sebagian/Booking'
        : transaction.type;
}

function toggleReward(rewardId: number): void {
    selectedRewardId.value =
        selectedRewardId.value === rewardId ? null : rewardId;
    discountAmount.value = 0;
}

/**
 * A walk-in order the cashier may still sign up. Once the order belongs to a
 * member there is nothing left to register, so the panel takes itself away.
 */
const canRegisterMember = computed<boolean>(
    () =>
        props.capabilities.create &&
        selectedOrder.value !== null &&
        selectedOrder.value.customerId === null &&
        selectedOrder.value.status !== 'batal',
);

/**
 * The member, if any, already holding the plate being typed. The server rejects
 * it either way; catching it here saves the cashier the round trip.
 */
const memberDraftPlateOwner = computed<CarwashCustomer | null>(() => {
    const plate = normalizePlate(memberDraft.value.plate);

    if (plate === '') {
        return null;
    }

    return (
        customerList.value.find((customer) =>
            customer.vehicles.some(
                (vehicle) => normalizePlate(vehicle.plate) === plate,
            ),
        ) ?? null
    );
});

const canSubmitMember = computed<boolean>(
    () =>
        canRegisterMember.value &&
        memberDraftPlateOwner.value === null &&
        !memberForm.processing &&
        memberDraft.value.plate.trim() !== '' &&
        memberDraft.value.vehicle.trim() !== '' &&
        memberDraft.value.name.trim() !== '' &&
        memberDraft.value.phone.trim() !== '',
);

/**
 * Seeds the panel with what the floor already captured, so the cashier corrects
 * a plate rather than retyping the customer. The presenter marks a walk-in by
 * appending "(non-member)" to their name, which is a label and not part of it.
 */
function fillMemberDraft(order: CarwashOrder): void {
    memberDraft.value = {
        plate: order.plate === '—' ? '' : order.plate,
        vehicle: order.vehicle === '—' ? '' : order.vehicle,
        name: order.customer.replace(' (non-member)', ''),
        phone: order.phone,
    };
}

/**
 * Signs the walk-in up and moves the order onto them, without closing the
 * payment the cashier is part-way through composing.
 */
function submitMember(): void {
    const order = selectedOrder.value;

    if (!order || !canSubmitMember.value) {
        return;
    }

    if (props.mode === 'live') {
        submitLiveMember(order);

        return;
    }

    applyDemoMember(order);
}

/**
 * The reload lands with the order already renamed and carrying its member, so
 * the panel is not dismissed by hand — it stops applying and closes itself.
 */
function submitLiveMember(order: CarwashOrder): void {
    memberForm.name = memberDraft.value.name.trim();
    memberForm.phone = memberDraft.value.phone.trim();
    memberForm.vehicle_name = memberDraft.value.vehicle.trim();
    memberForm.vehicle_plate = memberDraft.value.plate.trim();

    memberForm.submit(storePosMember(order.id), {
        preserveScroll: true,
        onSuccess: () => {
            memberForm.reset();
            memberDraft.value = { ...emptyMemberDraft };
        },
    });
}

/**
 * Walks the demo copy through the same registration, so the console keeps
 * behaving without a database behind it.
 */
function applyDemoMember(order: CarwashOrder): void {
    const name = memberDraft.value.name.trim();
    const plate = normalizePlate(memberDraft.value.plate);
    const vehicle = memberDraft.value.vehicle.trim();
    const phone = memberDraft.value.phone.trim();
    const id =
        customerList.value.reduce(
            (highest, customer) => Math.max(highest, customer.id),
            0,
        ) + 1;

    workflow.addCustomer({
        id,
        name,
        memberId: `MEM-${String(id).padStart(6, '0')}`,
        phone,
        email: '',
        vehicle,
        plate,
        vehicles: [{ name: vehicle, plate, type: 'Mobil', isPrimary: true }],
        stamps: 0,
        lifetimeStamps: 0,
        visits: 1,
        spend: order.total,
        joinedAt: formatDate(props.filters.today),
        lastVisit: '—',
        initials: name
            .split(' ')
            .slice(0, 2)
            .map((part) => part.charAt(0).toUpperCase())
            .join(''),
        status: 'aktif',
        /* Registered at the counter, so there are no portal credentials yet. */
        hasAccount: false,
    });

    order.customerId = id;
    order.customer = name;
    order.phone = phone;
    order.vehicle = vehicle;
    order.plate = plate;

    memberDraft.value = { ...emptyMemberDraft };
}

/**
 * Everything the slip needs that the payment itself is about to change, read
 * before the order moves so the figures printed are the ones the cashier saw.
 */
function paymentSnapshot(order: CarwashOrder): PaymentSnapshot {
    return {
        intent: paymentIntent.value,
        amount: payAmount.value,
        reward: selectedReward.value,
        rewardDiscount: rewardDiscount.value,
        cashierDiscount: cashierDiscount.value,
        discount: totalDiscount.value,
        subtotal: order.total,
        priorDiscount: Math.max(orderServiceTotal.value - order.total, 0),
        previouslyPaid: order.paidAmount,
        /*
         * Settling the order drives `dueAmount` to zero, which collapses
         * `paymentTotal` and would report the whole tender as change. Both
         * figures are therefore read before the order is touched.
         */
        tendered: tenderedTotal.value,
        change: changeAmount.value,
        lines: orderServices.value.map((service) => ({
            name: service.name,
            price: service.price,
        })),
        history: order.transactions.map((entry) => ({
            date: entry.date,
            time: entry.time,
            type: paymentHistoryTypeLabel(entry),
            channels: transactionChannelsLabel(entry),
            cashier: paymentTransactionRecorder(entry),
            amount: entry.amount,
        })),
        breakdown: paymentBreakdown.value.map((payment) => ({ ...payment })),
    };
}

/**
 * Records money against the selected order. Anything short of the full amount
 * leaves the order with a `sebagian` payment; settling it issues the invoice and
 * releases the member's stamps.
 */
function submitPayment(): void {
    const order = selectedOrder.value;

    if (!order || !canSubmit.value || paymentForm.processing) {
        return;
    }

    const snapshot = paymentSnapshot(order);
    const matchingShifts = matchingTransactionShifts(
        props.transactionShift,
        props.filters.timezone,
    );

    if (matchingShifts.length > 1) {
        pendingPayment.value = { order, snapshot };
        overlappingTransactionShifts.value = matchingShifts;

        return;
    }

    completePayment(order, snapshot, matchingShifts[0]?.id ?? null);
}

function selectTransactionShift(shiftId: number): void {
    const pending = pendingPayment.value;

    if (pending === null) {
        return;
    }

    closeTransactionShiftDialog();
    completePayment(pending.order, pending.snapshot, shiftId);
}

function closeTransactionShiftDialog(): void {
    pendingPayment.value = null;
    overlappingTransactionShifts.value = [];
}

function completePayment(
    order: CarwashOrder,
    snapshot: PaymentSnapshot,
    transactionShiftId: number | null,
): void {
    if (props.mode === 'live') {
        submitLivePayment(order, snapshot, transactionShiftId);

        return;
    }

    applyDemoPayment(order, snapshot, transactionShiftId);

    /*
     * Still inside the cashier's click, so the browser lets the slip window
     * through instead of treating it as an unsolicited popup.
     */
    printReceipt();

    resetPanel();
}

/**
 * Hands the payment to the server and prints from what comes back, because the
 * invoice number and the transaction reference are issued by the write.
 */
function submitLivePayment(
    order: CarwashOrder,
    snapshot: PaymentSnapshot,
    transactionShiftId: number | null,
): void {
    paymentForm.intent = snapshot.intent;
    paymentForm.discount = snapshot.discount;
    paymentForm.amount = snapshot.amount;
    paymentForm.channels = snapshot.breakdown.map((payment) => ({
        method: payment.method,
        amount: payment.amount,
        provider: payment.provider,
        reference: payment.reference,
    }));
    paymentForm.transaction_shift_id = transactionShiftId;

    paymentForm.submit(storePosPayment(order.id), {
        preserveScroll: true,
        onSuccess: () => {
            receipt.value = settledReceipt(order.id, snapshot);
            paymentForm.reset();
            resetPanel();

            /*
             * The reload has already returned by the time this runs, so the
             * slip window is no longer opening inside the cashier's click and
             * may be blocked. The dialog keeps a retry button for that.
             */
            printReceipt();
        },
    });
}

/**
 * The slip for a payment the server has just written, read back off the
 * reloaded order so the invoice, reference, and totals are the stored ones.
 */
function settledReceipt(
    orderId: number,
    snapshot: PaymentSnapshot,
): PosReceipt | null {
    const order = orderList.value.find((candidate) => candidate.id === orderId);

    if (!order) {
        return null;
    }

    const transaction = order.transactions[order.transactions.length - 1];
    const isSettled = transaction?.type === 'Pembayaran Lunas';

    return {
        orderNo: order.orderNo,
        invoice: order.invoice,
        reference: transaction
            ? paymentTransactionReference(transaction, order)
            : '—',
        date: transaction?.date ?? props.filters.today,
        time: transaction?.time ?? '—',
        cashier: transaction?.recordedBy ?? props.persona.name,
        shift:
            transaction?.shift || props.persona.shift || unassignedShiftLabel,
        customer: order.customer,
        customerStatus: order.customerId === null ? 'Non-member' : 'Member',
        vehicle: order.vehicle,
        plate: order.plate,
        items: order.items,
        lines: snapshot.lines,
        subtotal: snapshot.subtotal,
        priorDiscount: snapshot.priorDiscount,
        rewardDiscount: snapshot.rewardDiscount,
        cashierDiscount: snapshot.cashierDiscount,
        total: order.total,
        tenderedTotal: snapshot.tendered,
        change: snapshot.change,
        history: snapshot.history,
        previouslyPaid: snapshot.previouslyPaid,
        paidTotal: order.paidAmount,
        dueAfter: Math.max(order.total - order.paidAmount, 0),
        isSettled,
        isReprint: false,
        timezone: props.filters.timezone,
        payment: order.payment,
        paymentBreakdown: snapshot.breakdown,
        reward: order.reward,
        publicUrl: transaction?.receiptUrl ?? null,
        verificationQr: '',
    };
}

/**
 * Walks the demo copy of the order through the same settlement the server
 * performs, so the console keeps behaving without a database behind it.
 */
function applyDemoPayment(
    order: CarwashOrder,
    snapshot: PaymentSnapshot,
    transactionShiftId: number | null,
): void {
    const customer = orderCustomer.value;
    const { amount, discount, reward } = snapshot;
    const currentPaymentMethods =
        order.paidAmount > 0 && order.payment !== '—'
            ? order.payment.split(' + ')
            : [];
    const usedPaymentMethods = snapshot.breakdown.map(paymentChannelLabel);
    const transactionChannelBreakdown = snapshot.breakdown.map((payment) => ({
        label: paymentChannelLabel(payment),
        amount: payment.amount,
        ...(payment.reference === '' ? {} : { reference: payment.reference }),
    }));
    const financialChannelBreakdown = financialPaymentBreakdown(
        transactionChannelBreakdown,
        amount,
    );
    const fallbackChannel = reward ? 'Reward' : 'Diskon';
    const transactionShiftName =
        props.transactionShift.mode === 'schedule'
            ? (props.transactionShift.shifts.find(
                  (shift) => shift.id === transactionShiftId,
              )?.name ?? null)
            : props.persona.shift || null;

    order.total -= discount;
    order.discount += discount;
    order.reward = reward?.name ?? order.reward;
    order.paidAmount += amount;
    order.payment = Array.from(
        new Set([...currentPaymentMethods, ...usedPaymentMethods]),
    ).join(' + ');

    if (order.payment === '') {
        order.payment = reward ? 'Reward' : 'Diskon penuh';
    }

    if (customer && reward) {
        customer.stamps -= reward.requiredStamps;
    }

    const isFullyPaid = order.paidAmount >= order.total;
    const completesOrder = isFullyPaid && snapshot.intent === 'settlement';

    const transactionTime = new Intl.DateTimeFormat('id-ID', {
        hour: '2-digit',
        minute: '2-digit',
        hour12: false,
        timeZone: props.filters.timezone,
    })
        .format(new Date())
        .replace(':', '.');
    const transaction: CarwashTransaction = {
        id: `${order.orderNo}-TRX-${order.transactions.length + 1}`,
        orderId: order.id,
        date: props.filters.today,
        time: transactionTime,
        type: completesOrder ? 'Pembayaran Lunas' : 'Pembayaran Sebagian',
        amount,
        channels: usedPaymentMethods.join(' + ') || fallbackChannel,
        channelBreakdown:
            financialChannelBreakdown.length > 0
                ? financialChannelBreakdown
                : [{ label: fallbackChannel, amount }],
        tenderBreakdown:
            transactionChannelBreakdown.length > 0
                ? transactionChannelBreakdown
                : [{ label: fallbackChannel, amount }],
        tenderedAmount: snapshot.tendered,
        changeAmount: snapshot.change,
        recordedBy: props.persona.name,
        shift: transactionShiftName,
    };

    order.transactions.push(transaction);

    workflow.addMoneyIn({
        id: `pos-${transaction.id}`,
        ref: paymentTransactionReference(transaction, order),
        date: transaction.date,
        time: transaction.time,
        category:
            transaction.type === 'Pembayaran Sebagian'
                ? 'Pembayaran Sebagian/Booking Order'
                : 'Pembayaran Sisa/Lunas (Order Selesai)',
        description: `${transaction.type} ${order.orderNo}`,
        amount: transaction.amount,
        method: transaction.channels,
        channelBreakdown: transaction.channelBreakdown,
        recordedBy: props.persona.name,
        shift: transactionShiftName,
        source: 'pos',
        orderId: order.id,
        orderNo: order.orderNo,
        customer: order.customer,
        vehicle: order.vehicle,
        plate: order.plate,
    });

    order.paymentStatus = isFullyPaid ? 'lunas' : 'sebagian';

    if (completesOrder) {
        order.status = 'selesai';

        if (order.invoice === '—') {
            order.invoice = order.orderNo.replace('ORD', 'ZW');
        }

        if (customer) {
            customer.stamps += order.stampsEarned;
            customer.lifetimeStamps += order.stampsEarned;
            customer.visits += 1;
            customer.spend += order.total;
            customer.lastVisit = 'Baru saja';
        }
    }

    receipt.value = {
        orderNo: order.orderNo,
        invoice: order.invoice,
        reference: paymentTransactionReference(transaction, order),
        date: transaction.date,
        time: transaction.time,
        cashier: transaction.recordedBy ?? props.persona.name,
        shift: transaction.shift || props.persona.shift || unassignedShiftLabel,
        customer: order.customer,
        customerStatus: order.customerId === null ? 'Non-member' : 'Member',
        vehicle: order.vehicle,
        plate: order.plate,
        items: order.items,
        lines: snapshot.lines,
        subtotal: snapshot.subtotal,
        priorDiscount: snapshot.priorDiscount,
        rewardDiscount: snapshot.rewardDiscount,
        cashierDiscount: snapshot.cashierDiscount,
        total: order.total,
        tenderedTotal: snapshot.tendered,
        change: snapshot.change,
        history: snapshot.history,
        previouslyPaid: snapshot.previouslyPaid,
        paidTotal: order.paidAmount,
        dueAfter: Math.max(order.total - order.paidAmount, 0),
        isSettled: completesOrder,
        isReprint: false,
        timezone: props.filters.timezone,
        payment: order.payment,
        paymentBreakdown: snapshot.breakdown,
        reward: order.reward,
        publicUrl: null,
        verificationQr: '',
    };
}

/**
 * Hands a slip to its own window, sized to the 78mm roll. A blocked window is
 * not fatal, so it is flagged for the dialog the cashier is looking at.
 */
function openReceipt(slip: PosReceipt): void {
    isReceiptWindowBlocked.value =
        openPosReceiptWindow(slip, props.brand) === null;
}

/** Reopens the slip behind the confirmation the cashier still has on screen. */
function printReceipt(): void {
    if (!receipt.value) {
        return;
    }

    openReceipt(receipt.value);
}

function closeReceipt(): void {
    receipt.value = null;
    isReceiptWindowBlocked.value = false;
}

/**
 * Rebuilds the slip one payment produced, from what the order still carries.
 *
 * The chosen payment takes the place of the settlement and everything before
 * it becomes history, which is how the original slip was laid out. Passing
 * `null` picks the order's last payment. Figures the order never kept are left
 * out rather than guessed: the EDC references, the cash actually tendered, and
 * the member's stamp balance at the time all fall away, and a single discount
 * line stands in for the reward-versus-cashier split. The bill printed is the
 * order's total as it stands now, which is why every copy says SALINAN.
 */
function transactionReceipt(
    order: CarwashOrder,
    transaction: CarwashTransaction | null,
): PosReceipt {
    const transactions = order.transactions;
    const found = transaction
        ? transactions.findIndex((entry) => entry.id === transaction.id)
        : -1;
    const index = found === -1 ? transactions.length - 1 : found;
    const settlement = transactions[index] ?? null;
    const history = index > 0 ? transactions.slice(0, index) : [];
    const previouslyPaid = history.reduce(
        (total, entry) => total + entry.amount,
        0,
    );
    const paidTotal = previouslyPaid + (settlement?.amount ?? 0);

    return {
        orderNo: order.orderNo,
        invoice: order.invoice,
        reference: settlement
            ? paymentTransactionReference(settlement, order)
            : '—',
        date: settlement?.date ?? order.date,
        time: settlement?.time ?? order.time,
        cashier: settlement ? paymentTransactionRecorder(settlement) : '—',
        shift: settlement ? paymentTransactionShift(settlement) : '—',
        customer: order.customer,
        customerStatus: order.customerId === null ? 'Non-member' : 'Member',
        vehicle: order.vehicle,
        plate: order.plate,
        items: order.items,
        lines: order.serviceItems.map((item) => ({
            name: item.label,
            price: item.totalPrice,
        })),
        subtotal: order.total,
        priorDiscount: order.discount,
        rewardDiscount: 0,
        cashierDiscount: 0,
        total: order.total,
        tenderedTotal: settlement?.tenderedAmount ?? order.paidAmount,
        change: settlement?.changeAmount ?? 0,
        history: history.map((entry) => ({
            date: entry.date,
            time: entry.time,
            type: paymentHistoryTypeLabel(entry),
            channels: transactionChannelsLabel(entry),
            cashier: paymentTransactionRecorder(entry),
            amount: entry.amount,
        })),
        previouslyPaid,
        paidTotal,
        dueAfter: Math.max(order.total - paidTotal, 0),
        /* Only the payment that closed the order prints as a settled slip. */
        isSettled: settlement?.type === 'Pembayaran Lunas',
        isReprint: true,
        timezone: props.filters.timezone,
        payment: order.payment,
        paymentBreakdown:
            settlement?.tenderBreakdown.map((channel) => ({
                method: channel.label,
                amount: channel.amount,
                provider: '',
                reference: channel.reference ?? '',
            })) ?? [],
        reward: order.reward,
        publicUrl: settlement?.receiptUrl ?? null,
        verificationQr: '',
    };
}

/**
 * Reprints one payment from a transaction history. The cashier is already
 * inside a dialog here, so the slip goes straight to its own window rather
 * than stacking the confirmation on top.
 */
function reprintTransaction(
    order: CarwashOrder,
    transaction: CarwashTransaction,
): void {
    openReceipt(transactionReceipt(order, transaction));
}

/** Reprints a settled order from its last payment, with the recap on screen. */
function reprintReceipt(order: CarwashOrder): void {
    receipt.value = transactionReceipt(order, null);

    printReceipt();
}

/** Filtering is a fresh visit, so the page rebuilds from the narrowed props. */
function applyDate(date: string): void {
    router.get(
        props.mode === 'demo' ? demoAdmin.pos.url() : indexPos.url(),
        { date },
        { preserveScroll: true, replace: true },
    );
}

const paymentForm = useForm({
    intent: 'settlement' as 'settlement' | 'partial',
    discount: 0,
    amount: 0,
    channels: [] as PosPaymentBreakdown[],
    transaction_shift_id: null as number | null,
});
const pendingPayment = ref<PendingPayment | null>(null);
const overlappingTransactionShifts = ref<CarwashTransactionShiftOption[]>([]);
const memberForm = useForm({
    name: '',
    phone: '',
    vehicle_name: '',
    vehicle_plate: '',
});
</script>

<template>
    <Head :title="`${brand.name} — Kasir POS`" />

    <TransactionShiftDialog
        :open="pendingPayment !== null"
        :shifts="overlappingTransactionShifts"
        @close="closeTransactionShiftDialog"
        @select="selectTransactionShift"
    />

    <div class="space-y-4">
        <DateFilterBar :filters="filters" @change="applyDate" />

        <!-- Summary -->
        <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
            <StatCard
                label="Order Berjalan"
                :value="formatCurrency(outstandingTotal)"
                caption="order yang sedang berlangsung dan belum melakukan pembayaran"
                :icon="Clock"
                tone="amber"
            />
            <StatCard
                label="Pembayaran diterima"
                :value="formatCurrency(collectedTotal)"
                caption="ketuk untuk melihat rekap pembayaran"
                :icon="Banknote"
                tone="emerald"
                interactive
                :active="isPaymentRecapOpen"
                @click="isPaymentRecapOpen = true"
            />
        </div>

        <ModalDialog
            :open="isPaymentRecapOpen"
            title="Rekap Pembayaran Diterima"
            :caption="`Periode ${formatDate(filters.date)}`"
            size="xl"
            @close="closePaymentRecap"
        >
            <div class="space-y-5">
                <div
                    class="flex flex-wrap gap-1 rounded-2xl bg-slate-100 p-1 ring-1 ring-slate-200/80 ring-inset"
                    role="tablist"
                    aria-label="Filter rekap pembayaran berdasarkan shift"
                    @keydown.left.prevent="movePaymentRecapShift(-1)"
                    @keydown.right.prevent="movePaymentRecapShift(1)"
                >
                    <button
                        v-for="tab in paymentRecapShiftTabs"
                        :id="`payment-recap-tab-${tab.key}`"
                        :key="tab.key"
                        type="button"
                        role="tab"
                        :title="tab.caption"
                        :aria-selected="activePaymentRecapShift === tab.key"
                        aria-controls="payment-recap-panel"
                        :tabindex="activePaymentRecapShift === tab.key ? 0 : -1"
                        class="flex flex-1 basis-24 flex-col items-center justify-center rounded-xl px-3 py-2 leading-tight transition duration-200 focus-visible:ring-2 focus-visible:ring-cyan-500 focus-visible:ring-offset-2 focus-visible:ring-offset-slate-100 focus-visible:outline-none"
                        :class="
                            activePaymentRecapShift === tab.key
                                ? 'bg-gradient-to-r from-cyan-500 to-sky-600 text-white shadow-md shadow-cyan-500/30'
                                : 'text-slate-500 hover:bg-white hover:text-slate-800 hover:shadow-sm'
                        "
                        @click="selectPaymentRecapShift(tab.key)"
                    >
                        <span class="text-sm font-semibold">
                            {{ tab.label }}
                        </span>
                        <span
                            class="mt-0.5 text-[11px] tabular-nums"
                            :class="
                                activePaymentRecapShift === tab.key
                                    ? 'text-cyan-50/85'
                                    : 'text-slate-400'
                            "
                        >
                            {{ tab.count }} transaksi
                        </span>
                    </button>
                </div>

                <div
                    id="payment-recap-panel"
                    role="tabpanel"
                    :aria-labelledby="`payment-recap-tab-${activePaymentRecapShift}`"
                    class="space-y-5"
                >
                    <button
                        type="button"
                        class="w-full rounded-2xl border border-emerald-200 bg-emerald-50 p-5 text-left transition hover:border-emerald-300 hover:bg-emerald-100/70 focus-visible:ring-2 focus-visible:ring-emerald-500 focus-visible:outline-none"
                        :class="
                            isPaymentRecapSelected('all', 'Semua pembayaran')
                                ? 'ring-2 ring-emerald-400'
                                : ''
                        "
                        :aria-pressed="
                            isPaymentRecapSelected('all', 'Semua pembayaran')
                        "
                        @click="selectPaymentRecap('all', 'Semua pembayaran')"
                    >
                        <div class="flex items-start justify-between gap-4">
                            <div>
                                <p
                                    class="text-xs font-semibold tracking-wide text-emerald-700 uppercase"
                                >
                                    Total pembayaran diterima
                                </p>
                                <p
                                    class="mt-2 text-3xl font-bold tracking-tight text-emerald-950 tabular-nums"
                                >
                                    {{
                                        formatCurrency(activePaymentRecapTotal)
                                    }}
                                </p>
                                <p class="mt-1 text-xs text-emerald-700/75">
                                    Termasuk pembayaran sebagian dan pembayaran
                                    lunas
                                </p>
                            </div>
                            <span
                                class="flex h-11 w-11 shrink-0 items-center justify-center rounded-2xl bg-emerald-500 text-white shadow-sm shadow-emerald-500/30"
                            >
                                <Banknote class="h-5 w-5" />
                            </span>
                        </div>
                    </button>

                    <div class="grid grid-cols-1 gap-3 sm:grid-cols-2">
                        <button
                            type="button"
                            class="rounded-2xl border border-slate-200 bg-white p-4 text-left transition hover:border-cyan-200 hover:bg-cyan-50/40 focus-visible:ring-2 focus-visible:ring-cyan-500 focus-visible:outline-none"
                            :class="
                                isPaymentRecapSelected(
                                    'all',
                                    'Semua pembayaran',
                                )
                                    ? 'border-cyan-200 bg-cyan-50 ring-1 ring-cyan-200'
                                    : ''
                            "
                            :aria-pressed="
                                isPaymentRecapSelected(
                                    'all',
                                    'Semua pembayaran',
                                )
                            "
                            @click="
                                selectPaymentRecap('all', 'Semua pembayaran')
                            "
                        >
                            <p
                                class="text-xs leading-snug whitespace-normal text-slate-500"
                            >
                                Jumlah transaksi
                            </p>
                            <p
                                class="mt-1 text-xl font-semibold text-slate-900 tabular-nums"
                            >
                                {{ formatNumber(paymentRecapTransactionCount) }}
                            </p>
                        </button>
                        <button
                            type="button"
                            class="rounded-2xl border border-slate-200 bg-white p-4 text-left transition hover:border-cyan-200 hover:bg-cyan-50/40 focus-visible:ring-2 focus-visible:ring-cyan-500 focus-visible:outline-none"
                            :class="
                                isPaymentRecapSelected(
                                    'type',
                                    finalPaymentRecapLabel,
                                )
                                    ? 'border-cyan-200 bg-cyan-50 ring-1 ring-cyan-200'
                                    : ''
                            "
                            :aria-pressed="
                                isPaymentRecapSelected(
                                    'type',
                                    finalPaymentRecapLabel,
                                )
                            "
                            @click="
                                selectPaymentRecap(
                                    'type',
                                    finalPaymentRecapLabel,
                                )
                            "
                        >
                            <p
                                class="text-xs leading-snug whitespace-normal text-slate-500"
                            >
                                {{ finalPaymentRecapLabel }}
                            </p>
                            <p
                                class="mt-1 text-xl font-semibold text-slate-900 tabular-nums"
                            >
                                {{ formatNumber(paymentRecapFinalOrderCount) }}
                            </p>
                        </button>
                    </div>

                    <div class="grid grid-cols-1 gap-4 md:grid-cols-2">
                        <section
                            class="overflow-hidden rounded-2xl border border-slate-200 bg-white"
                        >
                            <div class="border-b border-slate-100 px-4 py-3">
                                <h3
                                    class="text-sm font-semibold text-slate-900"
                                >
                                    Jenis transaksi
                                </h3>
                            </div>
                            <div class="divide-y divide-slate-100 px-4">
                                <button
                                    v-for="row in paymentRecapByType"
                                    :key="row.label"
                                    type="button"
                                    class="flex w-full items-center justify-between gap-3 rounded-xl px-2 py-3 text-left transition hover:bg-slate-50 focus-visible:ring-2 focus-visible:ring-cyan-500 focus-visible:outline-none"
                                    :class="
                                        isPaymentRecapSelected(
                                            'type',
                                            row.label,
                                        )
                                            ? 'bg-cyan-50 ring-1 ring-cyan-200'
                                            : ''
                                    "
                                    :aria-pressed="
                                        isPaymentRecapSelected(
                                            'type',
                                            row.label,
                                        )
                                    "
                                    @click="
                                        selectPaymentRecap('type', row.label)
                                    "
                                >
                                    <div class="min-w-0">
                                        <p
                                            class="text-sm leading-snug whitespace-normal text-slate-700"
                                        >
                                            {{ row.label }}
                                        </p>
                                        <p class="text-[11px] text-slate-400">
                                            {{ formatNumber(row.count) }}
                                            transaksi
                                        </p>
                                    </div>
                                    <p
                                        class="text-sm font-semibold text-slate-900 tabular-nums"
                                    >
                                        {{ formatCurrency(row.amount) }}
                                    </p>
                                </button>
                            </div>
                        </section>

                        <section
                            class="overflow-hidden rounded-2xl border border-slate-200 bg-white"
                        >
                            <div class="border-b border-slate-100 px-4 py-3">
                                <h3
                                    class="text-sm font-semibold text-slate-900"
                                >
                                    Kanal pembayaran
                                </h3>
                            </div>
                            <div
                                v-if="paymentRecapByChannel.length > 0"
                                class="divide-y divide-slate-100 px-4"
                            >
                                <button
                                    v-for="row in paymentRecapByChannel"
                                    :key="row.label"
                                    type="button"
                                    class="flex w-full items-center justify-between gap-3 rounded-xl px-2 py-3 text-left transition hover:bg-slate-50 focus-visible:ring-2 focus-visible:ring-cyan-500 focus-visible:outline-none"
                                    :class="
                                        isPaymentRecapSelected(
                                            'channel',
                                            row.label,
                                        )
                                            ? 'bg-cyan-50 ring-1 ring-cyan-200'
                                            : ''
                                    "
                                    :aria-pressed="
                                        isPaymentRecapSelected(
                                            'channel',
                                            row.label,
                                        )
                                    "
                                    @click="
                                        selectPaymentRecap('channel', row.label)
                                    "
                                >
                                    <div class="min-w-0">
                                        <p
                                            class="truncate text-sm text-slate-700"
                                            :title="row.label"
                                        >
                                            {{ row.label }}
                                        </p>
                                        <p class="text-[11px] text-slate-400">
                                            {{ formatNumber(row.count) }}
                                            transaksi
                                        </p>
                                    </div>
                                    <p
                                        class="shrink-0 text-sm font-semibold text-slate-900 tabular-nums"
                                    >
                                        {{ formatCurrency(row.amount) }}
                                    </p>
                                </button>
                            </div>
                            <div v-else class="px-4 py-8 text-center">
                                <p class="text-sm text-slate-500">
                                    Belum ada pembayaran pada periode ini.
                                </p>
                            </div>
                        </section>
                    </div>

                    <section
                        v-if="selectedPaymentRecap"
                        ref="paymentRecapDetailsElement"
                        class="overflow-hidden rounded-2xl border border-slate-200 bg-white"
                    >
                        <div
                            class="flex flex-col gap-2 border-b border-slate-100 bg-slate-50/70 px-4 py-3 sm:flex-row sm:items-center sm:justify-between"
                        >
                            <div>
                                <p class="text-xs font-medium text-cyan-700">
                                    {{
                                        selectedPaymentRecap.category === 'all'
                                            ? 'Semua pembayaran'
                                            : selectedPaymentRecap.category ===
                                                'type'
                                              ? 'Jenis transaksi'
                                              : 'Kanal pembayaran'
                                    }}
                                </p>
                                <h3
                                    class="text-sm font-semibold text-slate-900"
                                >
                                    Detail transaksi &amp; order —
                                    {{ selectedPaymentRecap.label }}
                                </h3>
                            </div>
                            <div class="text-left sm:text-right">
                                <p class="text-xs text-slate-500">
                                    {{
                                        formatNumber(paymentRecapDetails.length)
                                    }}
                                    transaksi
                                </p>
                                <p
                                    class="text-sm font-semibold text-slate-900 tabular-nums"
                                >
                                    {{
                                        formatCurrency(paymentRecapDetailTotal)
                                    }}
                                </p>
                            </div>
                        </div>

                        <div class="divide-y divide-slate-100">
                            <article
                                v-for="detail in paymentRecapDetails"
                                :key="`${selectedPaymentRecap.category}-${selectedPaymentRecap.label}-${detail.transaction.id}`"
                                class="px-4 py-4"
                            >
                                <div
                                    class="grid gap-4 md:grid-cols-[minmax(0,1fr)_minmax(0,1.2fr)_8rem] md:items-start"
                                >
                                    <div class="min-w-0">
                                        <p
                                            class="text-[11px] font-medium tracking-wide text-slate-400 uppercase"
                                        >
                                            Transaksi
                                        </p>
                                        <button
                                            type="button"
                                            class="mt-1 text-left text-xs font-semibold wrap-anywhere text-cyan-700 underline decoration-cyan-300 underline-offset-2 transition hover:text-cyan-900 focus-visible:rounded focus-visible:ring-2 focus-visible:ring-cyan-500 focus-visible:ring-offset-2 focus-visible:outline-none"
                                            :aria-label="`Lihat transaksi ${paymentTransactionReference(detail.transaction, detail.order)} pada order ${detail.order.orderNo}`"
                                            @click="
                                                showPaymentRecapTransaction(
                                                    detail,
                                                )
                                            "
                                        >
                                            {{
                                                paymentTransactionReference(
                                                    detail.transaction,
                                                    detail.order,
                                                )
                                            }}
                                        </button>
                                        <p
                                            class="mt-1 text-sm font-medium text-slate-900"
                                        >
                                            {{
                                                paymentTransactionLabel(
                                                    detail.transaction,
                                                )
                                            }}
                                        </p>
                                        <p
                                            class="mt-0.5 text-xs text-slate-500"
                                        >
                                            {{
                                                formatDate(
                                                    detail.transaction.date,
                                                )
                                            }}
                                            ·
                                            {{ detail.transaction.time }}
                                        </p>
                                        <p
                                            class="mt-0.5 text-xs text-slate-500"
                                        >
                                            {{
                                                transactionChannelsLabel(
                                                    detail.transaction,
                                                )
                                            }}
                                        </p>
                                    </div>

                                    <div class="min-w-0">
                                        <p
                                            class="text-[11px] font-medium tracking-wide text-slate-400 uppercase"
                                        >
                                            Order
                                        </p>
                                        <p
                                            class="mt-1 text-sm font-medium text-slate-900"
                                        >
                                            <button
                                                type="button"
                                                class="font-semibold text-cyan-700 underline decoration-cyan-300 underline-offset-2 transition hover:text-cyan-900 focus-visible:rounded focus-visible:ring-2 focus-visible:ring-cyan-500 focus-visible:ring-offset-2 focus-visible:outline-none"
                                                :aria-label="`Lihat detail order ${detail.order.orderNo} dan transaksinya`"
                                                @click="
                                                    showPaymentRecapOrder(
                                                        detail,
                                                    )
                                                "
                                            >
                                                {{ detail.order.orderNo }}
                                            </button>
                                            · {{ detail.order.customer }}
                                        </p>
                                        <p
                                            class="mt-0.5 text-xs text-slate-500"
                                        >
                                            {{ detail.order.vehicle }} ·
                                            {{
                                                formatPlate(detail.order.plate)
                                            }}
                                        </p>
                                        <p
                                            class="mt-0.5 text-xs text-slate-500"
                                        >
                                            {{ detail.order.items }}
                                        </p>
                                    </div>

                                    <p
                                        class="text-base font-semibold text-emerald-700 tabular-nums md:text-right"
                                    >
                                        {{ formatCurrency(detail.amount) }}
                                    </p>
                                </div>

                                <ModalDialog
                                    :open="
                                        selectedPaymentRecapOrder?.order.id ===
                                            detail.order.id &&
                                        selectedPaymentRecapOrder.sourceTransactionId ===
                                            detail.transaction.id
                                    "
                                    title="Detail Order"
                                    :caption="`${detail.order.orderNo} · ${detail.order.customer}`"
                                    size="lg"
                                    :layer="
                                        selectedPaymentRecapTransaction
                                            ? 'top'
                                            : 'nested'
                                    "
                                    @close="selectedPaymentRecapOrder = null"
                                >
                                    <div
                                        class="rounded-2xl border border-cyan-200 bg-cyan-50/50 p-4"
                                    >
                                        <div>
                                            <p
                                                class="text-[11px] font-semibold tracking-wide text-cyan-700 uppercase"
                                            >
                                                Detail order
                                            </p>
                                            <h4
                                                class="mt-1 text-base font-semibold text-slate-950"
                                            >
                                                {{ detail.order.orderNo }} ·
                                                {{ detail.order.customer }}
                                            </h4>
                                            <p
                                                class="mt-1 text-xs text-slate-600"
                                            >
                                                {{ detail.order.vehicle }} ·
                                                {{
                                                    formatPlate(
                                                        detail.order.plate,
                                                    )
                                                }}
                                                ·
                                                {{
                                                    orderTypeLabel(detail.order)
                                                }}
                                            </p>
                                        </div>
                                    </div>

                                    <dl
                                        class="mt-4 grid grid-cols-2 gap-x-4 gap-y-3 rounded-xl bg-white p-4 ring-1 ring-slate-200"
                                        :class="
                                            detail.order.source === 'booking'
                                                ? 'sm:grid-cols-5'
                                                : 'sm:grid-cols-4'
                                        "
                                    >
                                        <div
                                            v-if="
                                                detail.order.source ===
                                                    'booking' &&
                                                detail.order.bookingDate
                                            "
                                        >
                                            <dt
                                                class="text-[10px] font-medium tracking-wide text-slate-400 uppercase"
                                            >
                                                Tanggal booking
                                            </dt>
                                            <dd
                                                class="mt-1 text-xs font-medium text-slate-700"
                                            >
                                                {{
                                                    formatDate(
                                                        detail.order
                                                            .bookingDate,
                                                    )
                                                }}
                                            </dd>
                                        </div>
                                        <div>
                                            <dt
                                                class="text-[10px] font-medium tracking-wide text-slate-400 uppercase"
                                            >
                                                Tanggal order
                                            </dt>
                                            <dd
                                                class="mt-1 text-xs font-medium text-slate-700"
                                            >
                                                {{
                                                    formatDate(
                                                        detail.order.date,
                                                    )
                                                }}
                                            </dd>
                                        </div>
                                        <div>
                                            <dt
                                                class="text-[10px] font-medium tracking-wide text-slate-400 uppercase"
                                            >
                                                Layanan
                                            </dt>
                                            <dd
                                                class="mt-1 text-xs font-medium text-slate-700"
                                            >
                                                {{ detail.order.items }}
                                            </dd>
                                        </div>
                                        <div>
                                            <dt
                                                class="text-[10px] font-medium tracking-wide text-slate-400 uppercase"
                                            >
                                                Total order
                                            </dt>
                                            <dd
                                                class="mt-1 text-xs font-semibold text-slate-900 tabular-nums"
                                            >
                                                {{
                                                    formatCurrency(
                                                        detail.order.total,
                                                    )
                                                }}
                                            </dd>
                                        </div>
                                        <div>
                                            <dt
                                                class="text-[10px] font-medium tracking-wide text-slate-400 uppercase"
                                            >
                                                Sisa tagihan
                                            </dt>
                                            <dd
                                                class="mt-1 text-xs font-semibold text-amber-800 tabular-nums"
                                            >
                                                {{
                                                    formatCurrency(
                                                        Math.max(
                                                            detail.order.total -
                                                                detail.order
                                                                    .paidAmount,
                                                            0,
                                                        ),
                                                    )
                                                }}
                                            </dd>
                                        </div>
                                    </dl>

                                    <div class="mt-4">
                                        <div
                                            class="flex items-center justify-between gap-3"
                                        >
                                            <h5
                                                class="text-sm font-semibold text-slate-900"
                                            >
                                                Riwayat transaksi
                                            </h5>
                                            <span
                                                class="text-xs text-slate-500"
                                            >
                                                {{
                                                    formatNumber(
                                                        detail.order
                                                            .transactions
                                                            .length,
                                                    )
                                                }}
                                                transaksi
                                            </span>
                                        </div>

                                        <ul class="mt-2 space-y-2">
                                            <li
                                                v-for="transaction in detail
                                                    .order.transactions"
                                                :key="transaction.id"
                                                class="flex flex-col gap-2 rounded-xl border px-3 py-3 transition sm:flex-row sm:items-center sm:justify-between"
                                                :class="
                                                    transaction.id ===
                                                    selectedPaymentRecapOrder?.highlightedTransactionId
                                                        ? 'border-cyan-300 bg-cyan-100 ring-2 ring-cyan-400/30'
                                                        : 'border-slate-200 bg-white'
                                                "
                                            >
                                                <div>
                                                    <div
                                                        class="flex flex-wrap items-center gap-2"
                                                    >
                                                        <p
                                                            class="text-xs font-semibold text-slate-900"
                                                        >
                                                            {{
                                                                paymentTransactionLabel(
                                                                    transaction,
                                                                )
                                                            }}
                                                        </p>
                                                        <span
                                                            v-if="
                                                                transaction.id ===
                                                                selectedPaymentRecapOrder?.highlightedTransactionId
                                                            "
                                                            class="rounded-full bg-cyan-600 px-2 py-0.5 text-[10px] font-semibold text-white"
                                                        >
                                                            Transaksi dipilih
                                                        </span>
                                                    </div>
                                                    <p
                                                        class="mt-0.5 text-[11px] text-slate-500"
                                                    >
                                                        {{
                                                            formatDate(
                                                                transaction.date,
                                                            )
                                                        }}
                                                        · {{ transaction.time }}
                                                        ·
                                                        {{
                                                            transactionChannelsLabel(
                                                                transaction,
                                                            )
                                                        }}
                                                    </p>
                                                </div>
                                                <div
                                                    class="flex shrink-0 items-center gap-2"
                                                >
                                                    <p
                                                        class="text-sm font-semibold text-emerald-700 tabular-nums"
                                                    >
                                                        {{
                                                            formatCurrency(
                                                                transaction.amount,
                                                            )
                                                        }}
                                                    </p>
                                                    <button
                                                        type="button"
                                                        class="flex items-center gap-1 rounded-lg border border-slate-200 bg-white px-2 py-1.5 text-[11px] font-semibold text-slate-600 transition hover:bg-slate-50"
                                                        title="Cetak ulang struk pembayaran ini"
                                                        @click="
                                                            reprintTransaction(
                                                                detail.order,
                                                                transaction,
                                                            )
                                                        "
                                                    >
                                                        <Printer
                                                            class="h-3.5 w-3.5"
                                                        />
                                                        Struk
                                                    </button>
                                                </div>
                                            </li>
                                        </ul>
                                        <p
                                            v-if="isReceiptWindowBlocked"
                                            class="mt-2 rounded-xl bg-rose-50 px-3 py-2 text-[11px] font-medium text-rose-700 ring-1 ring-rose-100"
                                        >
                                            Jendela struk diblokir browser.
                                            Izinkan pop-up untuk situs ini, lalu
                                            coba lagi.
                                        </p>
                                    </div>
                                    <template #footer>
                                        <button
                                            type="button"
                                            class="ml-auto rounded-xl bg-slate-900 px-5 py-2.5 text-sm font-semibold text-white transition hover:bg-slate-800"
                                            @click="
                                                selectedPaymentRecapOrder = null
                                            "
                                        >
                                            {{
                                                selectedPaymentRecapTransaction
                                                    ? 'Kembali ke transaksi'
                                                    : 'Kembali ke rekap'
                                            }}
                                        </button>
                                    </template>
                                </ModalDialog>
                            </article>
                        </div>
                    </section>

                    <div
                        v-else
                        class="rounded-2xl border border-dashed border-slate-200 bg-slate-50/60 px-5 py-6 text-center"
                    >
                        <p class="text-sm font-medium text-slate-700">
                            Pilih jenis transaksi atau kanal pembayaran
                        </p>
                        <p class="mt-1 text-xs text-slate-500">
                            Detail transaksi dan order terkait akan tampil di
                            sini.
                        </p>
                    </div>
                </div>
            </div>

            <template #footer>
                <RecapPrintMenu
                    :blocked="isRecapWindowBlocked"
                    @print="printRecap"
                />
                <button
                    type="button"
                    class="ml-auto rounded-xl bg-slate-900 px-5 py-2.5 text-sm font-semibold text-white transition hover:bg-slate-800"
                    @click="closePaymentRecap"
                >
                    Tutup
                </button>
            </template>
        </ModalDialog>

        <ModalDialog
            :open="selectedPaymentRecapTransaction !== null"
            title="Rekap Transaksi"
            :caption="
                selectedPaymentRecapTransaction
                    ? paymentTransactionReference(
                          selectedPaymentRecapTransaction.transaction,
                          selectedPaymentRecapTransaction.order,
                      )
                    : undefined
            "
            size="lg"
            layer="nested"
            @close="selectedPaymentRecapTransaction = null"
        >
            <template v-if="selectedPaymentRecapTransaction">
                <div
                    class="rounded-2xl border border-emerald-200 bg-emerald-50 p-5"
                >
                    <div class="flex items-start justify-between gap-4">
                        <div class="min-w-0">
                            <p
                                class="text-[11px] font-semibold tracking-wide text-emerald-700 uppercase"
                            >
                                {{
                                    paymentTransactionLabel(
                                        selectedPaymentRecapTransaction.transaction,
                                    )
                                }}
                            </p>
                            <p
                                class="mt-2 text-2xl font-bold text-emerald-950 tabular-nums"
                            >
                                {{
                                    formatCurrency(
                                        selectedPaymentRecapTransaction
                                            .transaction.amount,
                                    )
                                }}
                            </p>
                            <p class="mt-1 text-xs text-emerald-800/75">
                                {{
                                    formatDate(
                                        selectedPaymentRecapTransaction
                                            .transaction.date,
                                    )
                                }}
                                ·
                                {{
                                    selectedPaymentRecapTransaction.transaction
                                        .time
                                }}
                            </p>
                        </div>
                        <span
                            class="flex h-11 w-11 shrink-0 items-center justify-center rounded-2xl bg-emerald-500 text-white shadow-sm shadow-emerald-500/30"
                        >
                            <Banknote class="h-5 w-5" />
                        </span>
                    </div>
                </div>

                <dl
                    class="mt-4 grid grid-cols-1 gap-x-4 gap-y-3 rounded-2xl bg-slate-50 p-4 sm:grid-cols-2"
                >
                    <div>
                        <dt
                            class="text-[10px] font-medium tracking-wide text-slate-400 uppercase"
                        >
                            Nomor transaksi
                        </dt>
                        <dd
                            class="mt-1 text-xs font-semibold wrap-anywhere text-slate-900"
                        >
                            {{
                                paymentTransactionReference(
                                    selectedPaymentRecapTransaction.transaction,
                                    selectedPaymentRecapTransaction.order,
                                )
                            }}
                        </dd>
                    </div>
                    <div>
                        <dt
                            class="text-[10px] font-medium tracking-wide text-slate-400 uppercase"
                        >
                            Dicatat oleh
                        </dt>
                        <dd class="mt-1 text-xs font-medium text-slate-700">
                            {{
                                paymentTransactionRecorder(
                                    selectedPaymentRecapTransaction.transaction,
                                )
                            }}
                        </dd>
                    </div>
                    <div class="sm:col-span-2">
                        <dt
                            class="text-[10px] font-medium tracking-wide text-slate-400 uppercase"
                        >
                            Deskripsi
                        </dt>
                        <dd class="mt-1 text-xs font-medium text-slate-700">
                            {{ selectedPaymentRecapTransaction.order.items }}
                        </dd>
                    </div>
                </dl>

                <section
                    class="mt-4 overflow-hidden rounded-2xl border border-slate-200"
                >
                    <div
                        class="border-b border-slate-100 bg-slate-50/70 px-4 py-3"
                    >
                        <h3 class="text-sm font-semibold text-slate-900">
                            Kanal pembayaran
                        </h3>
                    </div>
                    <ul class="divide-y divide-slate-100 px-4">
                        <li
                            v-for="channel in selectedPaymentRecapTransaction
                                .transaction.channelBreakdown"
                            :key="channel.label"
                            class="flex items-center justify-between gap-4 py-3 text-xs"
                        >
                            <span class="font-medium text-slate-600">
                                {{ channel.label }}
                            </span>
                            <span
                                class="font-semibold text-slate-900 tabular-nums"
                            >
                                {{ formatCurrency(channel.amount) }}
                            </span>
                        </li>
                    </ul>
                </section>

                <button
                    type="button"
                    class="mt-4 flex w-full items-center justify-between gap-4 rounded-2xl border border-cyan-200 bg-cyan-50/60 p-4 text-left transition hover:border-cyan-300 hover:bg-cyan-100/70 focus-visible:ring-2 focus-visible:ring-cyan-500 focus-visible:outline-none"
                    @click="showSelectedPaymentRecapTransactionOrder"
                >
                    <span class="min-w-0">
                        <span
                            class="block text-[10px] font-semibold tracking-wide text-cyan-700 uppercase"
                        >
                            Order terkait · lihat rekap lengkap
                        </span>
                        <span class="mt-1 block font-semibold text-slate-950">
                            {{ selectedPaymentRecapTransaction.order.orderNo }}
                            ·
                            {{ selectedPaymentRecapTransaction.order.customer }}
                        </span>
                        <span class="mt-0.5 block text-xs text-slate-600">
                            {{ selectedPaymentRecapTransaction.order.vehicle }}
                            ·
                            {{
                                formatPlate(
                                    selectedPaymentRecapTransaction.order.plate,
                                )
                            }}
                        </span>
                    </span>
                    <span class="shrink-0 text-lg text-cyan-700">→</span>
                </button>
            </template>

            <template #footer>
                <button
                    type="button"
                    class="ml-auto rounded-xl bg-slate-900 px-5 py-2.5 text-sm font-semibold text-white transition hover:bg-slate-800"
                    @click="selectedPaymentRecapTransaction = null"
                >
                    Tutup
                </button>
            </template>
        </ModalDialog>

        <div class="space-y-4">
            <!-- Order picker -->
            <AccordionSection
                title="Pelunasan"
                :caption="`${visibleOrders.length} order ditampilkan — ketuk untuk memproses pembayaran`"
                :icon="Wallet"
                tone="violet"
                default-open
            >
                <template #toolbar>
                    <DataToolbar
                        v-model:search="search"
                        placeholder="Cari order / plat"
                    />
                </template>

                <ul v-if="visibleOrders.length > 0" class="mt-4 space-y-2.5">
                    <li v-for="order in visibleOrders" :key="order.id">
                        <button
                            type="button"
                            class="w-full rounded-2xl border p-4 text-left transition"
                            :class="
                                selectedOrderId === order.id
                                    ? 'border-violet-400 bg-violet-50 shadow-sm'
                                    : 'border-violet-200 bg-white hover:border-violet-400 hover:shadow-lg hover:shadow-violet-500/10'
                            "
                            @click="selectOrder(order)"
                        >
                            <div
                                class="flex flex-wrap items-start justify-between gap-2"
                            >
                                <div class="min-w-0">
                                    <p
                                        class="text-[11px] font-semibold tracking-wide text-violet-700"
                                    >
                                        No. order {{ order.orderNo }}
                                    </p>
                                    <p
                                        class="mt-1 text-xl font-bold tracking-wide text-slate-950"
                                    >
                                        {{ formatPlate(order.plate) }}
                                    </p>
                                    <p
                                        class="mt-0.5 text-sm font-medium text-slate-700"
                                    >
                                        {{ order.vehicle }}
                                    </p>
                                </div>
                                <div class="flex shrink-0 gap-1.5">
                                    <StatusPill
                                        :status="order.status"
                                        label="Pelunasan"
                                    />
                                </div>
                            </div>

                            <p class="mt-2 text-[11px] text-slate-500">
                                {{ formatDate(order.date) }} •
                                {{ orderTypeLabel(order) }}
                            </p>
                            <p
                                class="mt-3 truncate text-sm font-medium text-slate-900"
                            >
                                {{ order.customer }}
                            </p>
                            <p class="mt-1 line-clamp-1 text-xs text-slate-500">
                                {{ order.items }}
                            </p>
                            <ul
                                v-if="
                                    partialPaymentTransactions(order).length > 0
                                "
                                class="mt-2 space-y-1 text-xs font-medium text-violet-700"
                            >
                                <li
                                    v-for="transaction in partialPaymentTransactions(
                                        order,
                                    )"
                                    :key="transaction.id"
                                >
                                    {{ formatDate(transaction.date) }} ·
                                    Pembayaran Sebagian/Booking sebesar
                                    {{ formatCurrency(transaction.amount) }}.
                                </li>
                            </ul>

                            <div
                                class="mt-3 flex items-end justify-between border-t border-dashed border-slate-200 pt-2.5"
                            >
                                <span class="text-[11px] text-slate-500">
                                    Total {{ formatCurrency(order.total) }}
                                </span>
                                <span
                                    v-if="order.paymentStatus === 'lunas'"
                                    class="text-sm font-semibold text-emerald-600"
                                >
                                    Pembayaran Sisa/Lunas (Order Selesai)
                                </span>
                                <span v-else class="text-right">
                                    <span
                                        class="block text-[11px] text-slate-500"
                                    >
                                        Sisa tagihan
                                    </span>
                                    <span
                                        class="block text-sm font-semibold text-slate-900 tabular-nums"
                                    >
                                        {{
                                            formatCurrency(
                                                order.total - order.paidAmount,
                                            )
                                        }}
                                    </span>
                                </span>
                            </div>
                        </button>
                    </li>
                </ul>

                <EmptyState
                    v-else
                    :icon="ClipboardList"
                    title="Tidak ada order untuk pembayaran sisa/lunas"
                    caption="Belum ada order berstatus Pembayaran Sisa/Lunas (Order Selesai) atau pencarian tidak cocok."
                />
            </AccordionSection>

            <!-- Booking payments before arrival -->
            <AccordionSection
                title="Pembayaran Sebagian/Booking"
                :caption="partialPaymentBookingCaption"
                :icon="CreditCard"
                tone="orange"
            >
                <template #toolbar>
                    <DataToolbar
                        v-model:search="partialPaymentSearch"
                        placeholder="Cari booking / plat"
                    />
                </template>

                <ul
                    v-if="visiblePartialPaymentBookings.length > 0"
                    class="mt-4 space-y-2.5"
                >
                    <li
                        v-for="order in visiblePartialPaymentBookings"
                        :key="order.id"
                    >
                        <button
                            type="button"
                            class="w-full rounded-2xl border p-4 text-left transition"
                            :class="
                                selectedOrderId === order.id
                                    ? 'border-orange-400 bg-orange-50 shadow-sm'
                                    : 'border-orange-200 bg-white hover:border-orange-400 hover:shadow-lg hover:shadow-orange-500/10'
                            "
                            @click="selectOrder(order, 'partial')"
                        >
                            <div
                                class="flex flex-wrap items-start justify-between gap-2"
                            >
                                <div class="min-w-0">
                                    <p
                                        class="text-[11px] font-semibold tracking-wide text-orange-700"
                                    >
                                        No. order {{ order.orderNo }}
                                    </p>
                                    <p
                                        class="mt-1 text-xl font-bold tracking-wide text-slate-950"
                                    >
                                        {{ formatPlate(order.plate) }}
                                    </p>
                                    <p
                                        class="mt-0.5 text-sm font-medium text-slate-700"
                                    >
                                        {{ order.vehicle }}
                                    </p>
                                </div>
                                <StatusPill
                                    :status="bookingDisplayStatus(order)"
                                />
                            </div>

                            <p class="mt-2 text-[11px] text-slate-500">
                                {{ formatDate(order.date) }} •
                                {{ orderTypeLabel(order) }}
                            </p>
                            <p
                                class="mt-3 truncate text-sm font-medium text-slate-900"
                            >
                                {{ order.customer }}
                            </p>
                            <p class="mt-1 line-clamp-1 text-xs text-slate-500">
                                {{ order.items }}
                            </p>
                            <ul
                                v-if="
                                    partialPaymentTransactions(order).length > 0
                                "
                                class="mt-2 space-y-1 text-xs font-medium text-orange-700"
                            >
                                <li
                                    v-for="transaction in partialPaymentTransactions(
                                        order,
                                    )"
                                    :key="transaction.id"
                                >
                                    {{ formatDate(transaction.date) }} ·
                                    Pembayaran Sebagian/Booking sebesar
                                    {{ formatCurrency(transaction.amount) }}.
                                </li>
                            </ul>
                            <p
                                v-else
                                class="mt-2 text-xs font-medium text-amber-700"
                            >
                                Belum ada pembayaran.
                            </p>

                            <div
                                class="mt-3 flex items-end justify-between border-t border-dashed border-slate-200 pt-2.5"
                            >
                                <span class="text-[11px] text-slate-500">
                                    Total {{ formatCurrency(order.total) }}
                                </span>
                                <span class="text-right">
                                    <span
                                        class="block text-[11px] text-slate-500"
                                    >
                                        Sisa tagihan
                                    </span>
                                    <span
                                        class="block text-sm font-semibold text-slate-900 tabular-nums"
                                    >
                                        {{
                                            formatCurrency(
                                                order.total - order.paidAmount,
                                            )
                                        }}
                                    </span>
                                </span>
                            </div>
                        </button>
                    </li>
                </ul>

                <EmptyState
                    v-else
                    :icon="ClipboardList"
                    title="Tidak ada booking untuk Pembayaran Sebagian/Booking"
                    caption="Booking hari ini atau mendatang akan tampil di sini."
                />
            </AccordionSection>

            <!-- Settled orders, kept around so the slip can be reprinted -->
            <AccordionSection
                title="Lunas"
                :caption="`${visibleCompletedOrders.length} order lunas — cetak ulang struk di sini`"
                :icon="CircleCheck"
                tone="emerald"
            >
                <template #toolbar>
                    <DataToolbar
                        v-model:search="completedSearch"
                        placeholder="Cari order / invoice / plat"
                    />
                </template>

                <ul
                    v-if="visibleCompletedOrders.length > 0"
                    class="mt-4 space-y-2.5"
                >
                    <li
                        v-for="order in visibleCompletedOrders"
                        :key="order.id"
                        class="rounded-2xl border border-emerald-200 bg-white p-4"
                    >
                        <div
                            class="flex flex-wrap items-start justify-between gap-2"
                        >
                            <div class="min-w-0">
                                <p
                                    class="text-[11px] font-semibold tracking-wide text-emerald-700"
                                >
                                    {{ order.invoice }} · {{ order.orderNo }}
                                </p>
                                <p
                                    class="mt-1 text-xl font-bold tracking-wide text-slate-950"
                                >
                                    {{ formatPlate(order.plate) }}
                                </p>
                                <p
                                    class="mt-0.5 text-sm font-medium text-slate-700"
                                >
                                    {{ order.vehicle }}
                                </p>
                            </div>
                            <StatusPill :status="order.status" />
                        </div>

                        <p class="mt-2 text-[11px] text-slate-500">
                            {{ formatDate(order.date) }} •
                            {{ orderTypeLabel(order) }} •
                            {{ order.transactions.length }} pembayaran
                        </p>
                        <p
                            class="mt-3 truncate text-sm font-medium text-slate-900"
                        >
                            {{ order.customer }}
                        </p>
                        <p class="mt-1 line-clamp-1 text-xs text-slate-500">
                            {{ order.items }}
                        </p>
                        <!--
                            The whole payment run in order, so the instalments
                            that led to the settlement stay readable next to it.
                        -->
                        <ul
                            v-if="order.transactions.length > 0"
                            class="mt-2 space-y-1 text-xs"
                        >
                            <li
                                v-for="transaction in order.transactions"
                                :key="transaction.id"
                                :class="
                                    transaction.type === 'Pembayaran Sebagian'
                                        ? 'font-medium text-emerald-700'
                                        : 'font-semibold text-emerald-800'
                                "
                            >
                                {{ formatDate(transaction.date) }} ·
                                {{ transaction.time }} ·
                                {{ paymentTransactionLabel(transaction) }}
                                sebesar
                                {{ formatCurrency(transaction.amount) }}.
                            </li>
                        </ul>

                        <div
                            class="mt-3 flex flex-wrap items-end justify-between gap-3 border-t border-dashed border-slate-200 pt-2.5"
                        >
                            <span class="text-[11px] text-slate-500">
                                Total
                                <span
                                    class="font-semibold text-slate-900 tabular-nums"
                                >
                                    {{ formatCurrency(order.total) }}
                                </span>
                                · {{ order.payment }}
                            </span>
                            <button
                                type="button"
                                class="flex items-center gap-1.5 rounded-xl border border-emerald-300 bg-emerald-50 px-3 py-2 text-xs font-semibold text-emerald-700 transition hover:bg-emerald-100"
                                @click="reprintReceipt(order)"
                            >
                                <Printer class="h-3.5 w-3.5" />
                                Cetak ulang struk
                            </button>
                        </div>
                    </li>
                </ul>

                <EmptyState
                    v-else
                    :icon="ClipboardList"
                    title="Belum ada order selesai"
                    caption="Order yang sudah lunas akan tampil di sini untuk cetak ulang struk."
                />
            </AccordionSection>

            <!-- Payment modal -->
            <ModalDialog
                :open="selectedOrder !== null"
                title="Pembayaran"
                :caption="
                    selectedOrder
                        ? `${selectedOrder.orderNo} · ${selectedOrder.customer} · ${formatPlate(selectedOrder.plate)}`
                        : undefined
                "
                size="xl"
                @close="resetPanel"
            >
                <template v-if="selectedOrder">
                    <div
                        class="-mx-6 -mt-6 -mb-6 divide-y divide-slate-200 bg-slate-50/60"
                    >
                        <details v-if="orderServices.length > 0" class="group">
                            <summary
                                class="flex cursor-pointer list-none items-center justify-between gap-4 px-6 py-3.5 transition hover:bg-slate-100/80 [&::-webkit-details-marker]:hidden"
                            >
                                <span class="min-w-0">
                                    <span
                                        class="block text-sm font-semibold text-slate-900"
                                    >
                                        Layanan
                                        <span class="text-slate-400"
                                            >({{ orderServices.length }})</span
                                        >
                                    </span>
                                    <span
                                        class="block truncate text-[11px] text-slate-500"
                                    >
                                        {{
                                            orderServices
                                                .map((service) => service.name)
                                                .join(', ')
                                        }}
                                    </span>
                                </span>
                                <span class="flex shrink-0 items-center gap-3">
                                    <span
                                        class="text-sm font-bold text-slate-900 tabular-nums"
                                    >
                                        {{ formatCurrency(orderServiceTotal) }}
                                    </span>
                                    <ChevronDown
                                        class="h-4 w-4 text-slate-400 transition group-open:rotate-180"
                                    />
                                </span>
                            </summary>
                            <ul
                                class="max-h-44 [scrollbar-gutter:stable] space-y-2 overflow-y-auto px-6 pb-4"
                            >
                                <li
                                    v-for="service in orderServices"
                                    :key="service.id"
                                    class="flex items-center gap-3 rounded-xl bg-white px-3 py-2.5 ring-1 ring-slate-200"
                                >
                                    <span
                                        class="flex h-8 w-8 shrink-0 items-center justify-center rounded-lg bg-slate-100 text-sm"
                                    >
                                        {{ service.icon }}
                                    </span>
                                    <span
                                        class="min-w-0 flex-1 truncate text-xs font-medium text-slate-800"
                                    >
                                        {{ service.name }}
                                    </span>
                                    <span
                                        class="text-xs text-slate-700 tabular-nums"
                                    >
                                        {{ formatCurrency(service.price) }}
                                    </span>
                                </li>
                            </ul>
                        </details>

                        <details v-if="canRegisterMember" class="group">
                            <summary
                                class="flex cursor-pointer list-none items-center justify-between gap-4 px-6 py-3.5 transition hover:bg-slate-100/80 [&::-webkit-details-marker]:hidden"
                            >
                                <span class="min-w-0">
                                    <span
                                        class="block text-sm font-semibold text-slate-900"
                                    >
                                        Jadikan member
                                    </span>
                                    <span
                                        class="block truncate text-[11px] text-slate-500"
                                    >
                                        Opsional — daftarkan pelanggan ini,
                                        order langsung masuk ke member.
                                    </span>
                                </span>
                                <ChevronDown
                                    class="h-4 w-4 shrink-0 text-slate-400 transition group-open:rotate-180"
                                />
                            </summary>

                            <div class="space-y-3 px-6 pb-4">
                                <div
                                    v-if="memberForm.hasErrors"
                                    class="rounded-xl bg-rose-50 px-4 py-3 text-xs text-rose-700"
                                >
                                    <p
                                        v-for="(
                                            message, field
                                        ) in memberForm.errors"
                                        :key="field"
                                    >
                                        {{ message }}
                                    </p>
                                </div>

                                <div class="grid gap-3 sm:grid-cols-2">
                                    <div class="space-y-1.5">
                                        <label
                                            for="pos-member-plate"
                                            class="block text-xs font-medium text-slate-600"
                                        >
                                            Plat Nomor
                                        </label>
                                        <input
                                            id="pos-member-plate"
                                            v-model="memberDraft.plate"
                                            type="text"
                                            placeholder="Plat nomor"
                                            class="w-full rounded-xl border border-slate-200 bg-white px-3 py-2.5 text-sm uppercase placeholder:normal-case focus:border-cyan-400 focus:outline-none"
                                        />
                                        <p
                                            v-if="memberDraftPlateOwner"
                                            class="text-[11px] font-medium text-rose-600"
                                        >
                                            Plat ini sudah terdaftar atas nama
                                            {{ memberDraftPlateOwner.name }}.
                                        </p>
                                    </div>
                                    <div class="space-y-1.5">
                                        <label
                                            for="pos-member-vehicle"
                                            class="block text-xs font-medium text-slate-600"
                                        >
                                            Tipe Mobil
                                        </label>
                                        <input
                                            id="pos-member-vehicle"
                                            v-model="memberDraft.vehicle"
                                            type="text"
                                            placeholder="Merk / tipe mobil"
                                            class="w-full rounded-xl border border-slate-200 bg-white px-3 py-2.5 text-sm focus:border-cyan-400 focus:outline-none"
                                        />
                                    </div>
                                    <div class="space-y-1.5">
                                        <label
                                            for="pos-member-name"
                                            class="block text-xs font-medium text-slate-600"
                                        >
                                            Nama
                                        </label>
                                        <input
                                            id="pos-member-name"
                                            v-model="memberDraft.name"
                                            type="text"
                                            placeholder="Nama pelanggan"
                                            class="w-full rounded-xl border border-slate-200 bg-white px-3 py-2.5 text-sm focus:border-cyan-400 focus:outline-none"
                                        />
                                    </div>
                                    <div class="space-y-1.5">
                                        <label
                                            for="pos-member-phone"
                                            class="block text-xs font-medium text-slate-600"
                                        >
                                            Nomor Telpon
                                        </label>
                                        <input
                                            id="pos-member-phone"
                                            v-model="memberDraft.phone"
                                            type="tel"
                                            inputmode="tel"
                                            placeholder="Nomor telepon"
                                            class="w-full rounded-xl border border-slate-200 bg-white px-3 py-2.5 text-sm focus:border-cyan-400 focus:outline-none"
                                        />
                                    </div>
                                </div>

                                <div
                                    class="flex flex-wrap items-center justify-between gap-3"
                                >
                                    <p class="text-[11px] text-slate-400">
                                        Member dibuat tanpa akun portal — email
                                        dan kata sandi bisa diisi belakangan.
                                    </p>
                                    <button
                                        type="button"
                                        class="rounded-xl bg-cyan-600 px-4 py-2 text-xs font-semibold text-white shadow-sm shadow-cyan-600/30 transition hover:bg-cyan-700 disabled:cursor-not-allowed disabled:bg-slate-200 disabled:text-slate-400 disabled:shadow-none"
                                        :disabled="!canSubmitMember"
                                        @click="submitMember"
                                    >
                                        Simpan member
                                    </button>
                                </div>
                            </div>
                        </details>

                        <details
                            v-if="selectedOrder.transactions.length > 0"
                            class="group"
                        >
                            <summary
                                class="flex cursor-pointer list-none items-center justify-between gap-4 px-6 py-3.5 transition hover:bg-slate-100/80 [&::-webkit-details-marker]:hidden"
                            >
                                <span>
                                    <span
                                        class="block text-sm font-semibold text-slate-900"
                                    >
                                        Riwayat transaksi
                                        <span class="text-slate-400">
                                            ({{
                                                selectedOrder.transactions
                                                    .length
                                            }})
                                        </span>
                                    </span>
                                    <span
                                        class="block text-[11px] text-slate-500"
                                    >
                                        Pembayaran yang sudah diterima.
                                    </span>
                                </span>
                                <ChevronDown
                                    class="h-4 w-4 shrink-0 text-slate-400 transition group-open:rotate-180"
                                />
                            </summary>
                            <ul class="space-y-2 px-6 pb-4">
                                <li
                                    v-for="transaction in selectedOrder.transactions"
                                    :key="transaction.id"
                                    class="flex items-center justify-between gap-4 rounded-xl bg-white px-4 py-3 ring-1 ring-slate-200"
                                >
                                    <div class="min-w-0">
                                        <p
                                            class="text-xs font-semibold text-slate-800"
                                        >
                                            {{
                                                paymentHistoryTypeLabel(
                                                    transaction,
                                                )
                                            }}
                                        </p>
                                        <p
                                            class="truncate text-[11px] text-slate-500"
                                        >
                                            {{ formatDate(transaction.date) }} ·
                                            {{ transaction.time }} ·
                                            {{
                                                transactionChannelsLabel(
                                                    transaction,
                                                )
                                            }}
                                        </p>
                                    </div>
                                    <div
                                        class="flex shrink-0 items-center gap-2"
                                    >
                                        <p
                                            class="text-sm font-semibold text-emerald-700 tabular-nums"
                                        >
                                            −{{
                                                formatCurrency(
                                                    transaction.amount,
                                                )
                                            }}
                                        </p>
                                        <button
                                            type="button"
                                            class="flex items-center gap-1 rounded-lg border border-slate-200 px-2 py-1.5 text-[11px] font-semibold text-slate-600 transition hover:bg-slate-50"
                                            title="Cetak ulang struk pembayaran ini"
                                            @click="
                                                reprintTransaction(
                                                    selectedOrder,
                                                    transaction,
                                                )
                                            "
                                        >
                                            <Printer class="h-3.5 w-3.5" />
                                            Struk
                                        </button>
                                    </div>
                                </li>
                            </ul>
                            <p
                                v-if="isReceiptWindowBlocked"
                                class="mx-6 mb-4 rounded-xl bg-rose-50 px-3 py-2 text-[11px] font-medium text-rose-700 ring-1 ring-rose-100"
                            >
                                Jendela struk diblokir browser. Izinkan pop-up
                                untuk situs ini, lalu coba lagi.
                            </p>
                        </details>

                        <details
                            v-if="
                                redeemableRewards.length > 0 ||
                                selectedOrder.reward !== '—'
                            "
                            class="group"
                        >
                            <summary
                                class="flex cursor-pointer list-none items-center justify-between gap-4 px-6 py-3.5 transition hover:bg-slate-100/80 [&::-webkit-details-marker]:hidden"
                            >
                                <span>
                                    <span
                                        class="block text-sm font-semibold text-slate-900"
                                    >
                                        Tukar reward
                                    </span>
                                    <span
                                        class="block text-[11px] text-slate-500"
                                    >
                                        {{
                                            selectedOrder.reward !== '—'
                                                ? selectedOrder.reward
                                                : 'Saldo ' +
                                                  (orderCustomer?.stamps ?? 0) +
                                                  ' stempel'
                                        }}
                                    </span>
                                </span>
                                <span class="flex shrink-0 items-center gap-3">
                                    <span
                                        v-if="rewardDiscount > 0"
                                        class="text-sm font-semibold text-emerald-600 tabular-nums"
                                    >
                                        −{{ formatCurrency(rewardDiscount) }}
                                    </span>
                                    <ChevronDown
                                        class="h-4 w-4 text-slate-400 transition group-open:rotate-180"
                                    />
                                </span>
                            </summary>

                            <div
                                v-if="
                                    orderCustomer &&
                                    selectedOrder.reward === '—' &&
                                    redeemableRewards.length > 0
                                "
                                class="grid max-h-44 [scrollbar-gutter:stable] grid-cols-1 gap-2 overflow-y-auto px-6 pb-4 sm:grid-cols-2"
                            >
                                <button
                                    v-for="reward in redeemableRewards"
                                    :key="reward.id"
                                    type="button"
                                    class="flex items-center gap-2 rounded-xl border p-3 text-left transition"
                                    :class="
                                        selectedRewardId === reward.id
                                            ? 'border-cyan-400 bg-cyan-50 ring-1 ring-cyan-100'
                                            : 'border-slate-200 bg-white hover:border-cyan-300'
                                    "
                                    @click="toggleReward(reward.id)"
                                >
                                    <span class="text-lg">{{
                                        reward.icon
                                    }}</span>
                                    <span class="min-w-0 flex-1 leading-tight">
                                        <span
                                            class="block truncate text-xs font-medium text-slate-800"
                                            >{{ reward.name }}</span
                                        >
                                        <span
                                            class="block text-[10px] text-slate-500"
                                            >Tukar
                                            {{ reward.requiredStamps }}
                                            stempel</span
                                        >
                                    </span>
                                    <CircleCheck
                                        v-if="selectedRewardId === reward.id"
                                        class="h-4 w-4 shrink-0 text-cyan-600"
                                    />
                                </button>
                            </div>
                            <p
                                v-else-if="selectedOrder.reward !== '—'"
                                class="mx-6 mb-4 rounded-xl bg-emerald-50 px-4 py-3 text-xs font-medium text-emerald-700 ring-1 ring-emerald-100"
                            >
                                {{ selectedOrder.reward }} sudah diterapkan pada
                                order ini.
                            </p>
                        </details>

                        <details class="group">
                            <summary
                                class="flex cursor-pointer list-none items-center justify-between gap-4 px-6 py-3.5 transition hover:bg-slate-100/80 [&::-webkit-details-marker]:hidden"
                            >
                                <span>
                                    <span
                                        class="block text-sm font-semibold text-slate-900"
                                    >
                                        Diskon tambahan
                                    </span>
                                    <span
                                        class="block text-[11px] text-slate-500"
                                    >
                                        Opsional, buka untuk menambahkan diskon.
                                    </span>
                                </span>
                                <span class="flex shrink-0 items-center gap-3">
                                    <span
                                        v-if="cashierDiscount > 0"
                                        class="text-sm font-semibold text-emerald-600 tabular-nums"
                                    >
                                        −{{ formatCurrency(cashierDiscount) }}
                                    </span>
                                    <ChevronDown
                                        class="h-4 w-4 text-slate-400 transition group-open:rotate-180"
                                    />
                                </span>
                            </summary>
                            <label class="block px-6 pb-4">
                                <span
                                    class="flex items-center gap-2 rounded-xl bg-white px-4 py-3 ring-1 ring-slate-200 focus-within:ring-cyan-400"
                                >
                                    <span class="text-sm text-slate-500"
                                        >Rp</span
                                    >
                                    <MoneyInput
                                        v-model="discountAmount"
                                        min="0"
                                        :max-value="maximumCashierDiscount"
                                        placeholder="0"
                                        class="w-full bg-transparent text-base font-semibold text-slate-900 tabular-nums placeholder:text-slate-300 focus:outline-none"
                                    />
                                </span>
                            </label>
                        </details>

                        <section
                            class="flex items-center justify-between gap-4 px-6 py-4"
                        >
                            <div>
                                <h3
                                    class="text-sm font-semibold text-slate-900"
                                >
                                    Total Sisa Pembayaran
                                </h3>
                                <p class="text-[11px] text-slate-500">
                                    Setelah transaksi, reward, dan diskon.
                                </p>
                            </div>
                            <p
                                class="text-xl font-bold text-amber-900 tabular-nums"
                            >
                                {{ formatCurrency(amountAfterDiscount) }}
                            </p>
                        </section>

                        <section class="bg-white px-6 py-5">
                            <label class="block">
                                <span
                                    class="text-base font-bold text-slate-950"
                                >
                                    {{ paymentAmountLabel }}
                                </span>
                                <span
                                    class="mt-3 flex items-center gap-3 rounded-2xl bg-amber-100/80 px-5 py-5 ring-2 ring-amber-300 transition focus-within:bg-amber-50 focus-within:ring-amber-500"
                                >
                                    <span
                                        class="text-lg font-semibold text-slate-500"
                                        >Rp</span
                                    >
                                    <MoneyInput
                                        v-model="paymentTotalInput"
                                        :max-value="amountAfterDiscount"
                                        placeholder="0"
                                        class="w-full bg-transparent text-3xl font-bold tracking-tight text-slate-950 tabular-nums focus:outline-none sm:text-4xl"
                                        @update:model-value="
                                            markPaymentTotalEdited
                                        "
                                    />
                                </span>
                            </label>
                            <div class="mt-3 space-y-1.5">
                                <p class="text-[11px] text-slate-500">
                                    Otomatis diisi sebesar total sisa
                                    pembayaran. Ubah nominal untuk menerima
                                    pembayaran sebagian.
                                </p>
                                <p class="text-xs font-medium text-orange-700">
                                    Sisa Tagihan setelah pembayaran ini:
                                    <span class="font-bold tabular-nums">
                                        {{
                                            formatCurrency(
                                                remainingAfterPayment,
                                            )
                                        }}
                                    </span>
                                </p>
                            </div>
                        </section>

                        <section class="px-6 py-4">
                            <div class="flex items-start justify-between gap-4">
                                <div>
                                    <h3
                                        class="text-sm font-semibold text-slate-900"
                                    >
                                        Kanal Pembayaran
                                    </h3>
                                    <p class="text-[11px] text-slate-500">
                                        Pilih satu kanal atau tambahkan kanal
                                        lain untuk split pembayaran.
                                    </p>
                                </div>
                                <button
                                    type="button"
                                    :disabled="!canAddPaymentChannel"
                                    class="inline-flex shrink-0 items-center gap-1.5 rounded-lg bg-cyan-50 px-3 py-2 text-xs font-semibold text-cyan-700 transition hover:bg-cyan-100 disabled:cursor-not-allowed disabled:opacity-40"
                                    @click="addPaymentChannel"
                                >
                                    <Plus class="h-4 w-4" />
                                    <span class="hidden sm:inline"
                                        >Tambah pembayaran</span
                                    >
                                    <span class="sr-only sm:hidden"
                                        >Tambah pembayaran</span
                                    >
                                </button>
                            </div>
                            <div class="mt-3 space-y-2">
                                <div
                                    v-for="(row, index) in paymentChannelRows"
                                    :key="row.id"
                                    class="rounded-xl bg-white p-3 ring-1 ring-slate-200 md:flex md:flex-wrap md:items-center md:gap-x-4"
                                >
                                    <div
                                        class="flex items-center gap-2 md:min-w-0 md:flex-1"
                                    >
                                        <label
                                            :for="`payment-channel-${row.id}`"
                                            class="sr-only"
                                        >
                                            Kanal pembayaran
                                        </label>
                                        <select
                                            :id="`payment-channel-${row.id}`"
                                            :value="row.method"
                                            :aria-label="`Kanal pembayaran ${index + 1}`"
                                            class="min-w-0 flex-1 rounded-lg border-0 bg-slate-50 px-3 py-2.5 text-sm font-semibold text-slate-700 ring-1 ring-slate-200 focus:bg-white focus:ring-2 focus:ring-cyan-400 focus:outline-none md:max-w-52"
                                            @change="
                                                selectPaymentMethod(row, $event)
                                            "
                                        >
                                            <option value="" disabled>
                                                Pilih kanal pembayaran
                                            </option>
                                            <option
                                                v-for="method in paymentMethods"
                                                :key="method"
                                                :value="method"
                                                :disabled="
                                                    isPaymentMethodDisabled(
                                                        method,
                                                        row.id,
                                                    )
                                                "
                                            >
                                                {{ method }}
                                            </option>
                                        </select>
                                        <template v-if="row.method">
                                            <span class="text-xs text-slate-400"
                                                >Rp</span
                                            >
                                            <MoneyInput
                                                :id="`payment-${row.id}`"
                                                v-model="
                                                    paymentAmounts[row.method]
                                                "
                                                placeholder="0"
                                                :aria-label="`Nominal pembayaran ${row.method}`"
                                                class="min-w-0 flex-1 bg-transparent text-right text-sm font-semibold text-slate-900 tabular-nums placeholder:text-slate-300 focus:outline-none"
                                            />
                                            <button
                                                v-if="remainingTenderAmount > 0"
                                                type="button"
                                                class="shrink-0 rounded-lg bg-cyan-50 px-2.5 py-1.5 text-[10px] font-semibold text-cyan-700 transition hover:bg-cyan-100"
                                                @click="
                                                    fillRemainingAmount(
                                                        row.method,
                                                    )
                                                "
                                            >
                                                Isi sisa
                                            </button>
                                        </template>
                                        <button
                                            v-if="paymentChannelRows.length > 1"
                                            type="button"
                                            class="inline-flex h-9 w-9 shrink-0 items-center justify-center rounded-lg text-slate-400 transition hover:bg-rose-50 hover:text-rose-600"
                                            :aria-label="`Hapus kanal pembayaran ${index + 1}`"
                                            @click="
                                                removePaymentChannel(row.id)
                                            "
                                        >
                                            <Trash2 class="h-4 w-4" />
                                        </button>
                                    </div>

                                    <div
                                        v-if="
                                            row.method &&
                                            requiresPaymentProvider(row.method)
                                        "
                                        class="mt-3 space-y-2 border-t border-slate-100 pt-3 md:mt-0 md:w-80 md:shrink-0 md:border-t-0 md:border-l md:pt-0 md:pl-4"
                                    >
                                        <label
                                            class="grid gap-1.5 sm:grid-cols-[6rem_minmax(0,1fr)] sm:items-center sm:gap-3"
                                        >
                                            <span
                                                class="text-[11px] font-medium text-slate-500"
                                            >
                                                {{
                                                    paymentProviderLabel(
                                                        row.method,
                                                    )
                                                }}
                                            </span>
                                            <select
                                                v-model="
                                                    paymentProviders[row.method]
                                                "
                                                :aria-label="`${paymentProviderLabel(row.method)} untuk ${row.method}`"
                                                class="w-full rounded-lg border-0 bg-slate-50 px-3 py-2 text-xs font-medium text-slate-700 ring-1 ring-slate-200 focus:bg-white focus:ring-2 focus:ring-cyan-400 focus:outline-none"
                                            >
                                                <option value="" disabled>
                                                    Pilih
                                                    {{
                                                        paymentProviderLabel(
                                                            row.method,
                                                        ).toLowerCase()
                                                    }}
                                                </option>
                                                <option
                                                    v-for="option in paymentProviderOptions(
                                                        row.method,
                                                    )"
                                                    :key="option"
                                                    :value="option"
                                                >
                                                    {{ option }}
                                                </option>
                                            </select>
                                        </label>
                                        <label
                                            class="grid gap-1.5 sm:grid-cols-[6rem_minmax(0,1fr)] sm:items-center sm:gap-3"
                                        >
                                            <span
                                                class="text-[11px] font-medium text-slate-500"
                                            >
                                                No. Referensi
                                            </span>
                                            <input
                                                v-model.trim="
                                                    paymentReferences[
                                                        row.method
                                                    ]
                                                "
                                                type="text"
                                                maxlength="32"
                                                placeholder="No. Referensi"
                                                :aria-label="`No. referensi untuk ${row.method}`"
                                                class="w-full rounded-lg border-0 bg-slate-50 px-3 py-2 text-xs font-medium text-slate-700 ring-1 ring-slate-200 placeholder:text-slate-400 focus:bg-white focus:ring-2 focus:ring-cyan-400 focus:outline-none"
                                            />
                                        </label>
                                    </div>
                                    <div
                                        v-else-if="row.method"
                                        aria-hidden="true"
                                        class="hidden md:block md:w-80 md:shrink-0"
                                    ></div>
                                    <p
                                        v-if="
                                            requiresPaymentProvider(
                                                row.method,
                                            ) &&
                                            paymentAmounts[row.method] > 0 &&
                                            !paymentProviders[row.method]
                                        "
                                        class="mt-2 text-[10px] font-medium text-rose-600 sm:pl-27 md:basis-full md:pl-0"
                                    >
                                        Pilih
                                        {{
                                            paymentProviderLabel(
                                                row.method,
                                            ).toLowerCase()
                                        }}
                                        untuk melanjutkan.
                                    </p>
                                </div>
                            </div>
                        </section>

                        <section class="px-6 py-4">
                            <div class="grid grid-cols-1 gap-3 sm:grid-cols-2">
                                <div
                                    class="rounded-2xl bg-emerald-100 px-4 py-3 ring-1 ring-emerald-200"
                                >
                                    <p
                                        class="text-xs font-medium text-emerald-700"
                                    >
                                        Total Diterima
                                    </p>
                                    <p
                                        class="mt-1 text-xl font-bold text-emerald-950 tabular-nums"
                                    >
                                        {{ formatCurrency(tenderedTotal) }}
                                    </p>
                                </div>
                                <div
                                    class="rounded-2xl bg-orange-100 px-4 py-3 ring-1 ring-orange-200"
                                >
                                    <p
                                        class="text-xs font-medium text-orange-700"
                                    >
                                        Kembalian
                                    </p>
                                    <p
                                        class="mt-1 text-xl font-bold text-orange-950 tabular-nums"
                                    >
                                        {{ formatCurrency(changeAmount) }}
                                    </p>
                                </div>
                            </div>
                        </section>

                        <section class="space-y-3 px-6 py-4">
                            <p
                                v-if="
                                    orderCustomer &&
                                    selectedOrder.stampsEarned > 0
                                "
                                class="flex items-center gap-1.5 rounded-xl bg-emerald-50 px-3 py-2 text-xs font-medium text-emerald-700"
                            >
                                <Sparkles class="h-3.5 w-3.5 shrink-0" />
                                {{ orderCustomer.name.split(' ')[0] }} dapat +{{
                                    selectedOrder.stampsEarned
                                }}
                                stempel saat order lunas
                            </p>
                            <div
                                v-if="mode === 'live' && paymentForm.hasErrors"
                                class="rounded-xl bg-rose-50 px-4 py-3 text-xs text-rose-700"
                            >
                                <p
                                    v-for="(
                                        message, field
                                    ) in paymentForm.errors"
                                    :key="field"
                                >
                                    {{ message }}
                                </p>
                            </div>
                            <p
                                v-else-if="!capabilities.create"
                                class="rounded-xl bg-slate-100 px-4 py-3 text-xs text-slate-600"
                            >
                                Role Anda tidak memiliki akses untuk menerima
                                pembayaran.
                            </p>
                            <button
                                type="button"
                                class="flex w-full items-center justify-center gap-2 rounded-xl bg-gradient-to-r from-cyan-500 to-sky-600 py-3 text-sm font-semibold text-white shadow-lg shadow-cyan-500/30 transition hover:from-cyan-600 hover:to-sky-700 disabled:cursor-not-allowed disabled:from-slate-300 disabled:to-slate-300 disabled:shadow-none"
                                :disabled="
                                    !canSubmit ||
                                    !capabilities.create ||
                                    paymentForm.processing
                                "
                                @click="submitPayment"
                            >
                                <CreditCard class="h-4 w-4" />
                                {{
                                    paymentForm.processing
                                        ? 'Memproses…'
                                        : 'Proses'
                                }}
                            </button>
                        </section>
                    </div>
                </template>
            </ModalDialog>
        </div>
    </div>

    <!-- Receipt -->
    <ModalDialog :open="receipt !== null" size="sm" @close="closeReceipt">
        <template #hero>
            <div
                v-if="receipt"
                class="px-6 py-7 text-center text-white"
                :class="
                    receipt.isSettled
                        ? 'bg-gradient-to-br from-emerald-500 to-teal-600'
                        : 'bg-gradient-to-br from-amber-500 to-orange-600'
                "
            >
                <div
                    class="mx-auto flex h-14 w-14 items-center justify-center rounded-full bg-white/20"
                >
                    <Printer v-if="receipt.isReprint" class="h-8 w-8" />
                    <CircleCheck
                        v-else-if="receipt.isSettled"
                        class="h-8 w-8"
                    />
                    <Clock v-else class="h-8 w-8" />
                </div>
                <p class="mt-3 text-lg font-semibold">
                    {{ receiptHeadline }}
                </p>
                <p class="text-sm text-white/85">
                    {{ receipt.isSettled ? receipt.invoice : receipt.orderNo }}
                    • {{ receipt.payment }}
                </p>
            </div>
        </template>

        <div v-if="receipt" class="space-y-3 text-sm">
            <div class="flex justify-between">
                <span class="text-slate-500">Customer</span>
                <span class="font-medium text-slate-800">
                    {{ receipt.customer }}
                </span>
            </div>
            <div class="flex justify-between gap-4">
                <span class="shrink-0 text-slate-500">Layanan</span>
                <span class="text-right text-slate-700">
                    {{ receipt.items }}
                </span>
            </div>
            <div
                v-if="receipt.reward !== '—'"
                class="flex justify-between gap-4"
            >
                <span class="shrink-0 text-slate-500">Reward dipakai</span>
                <span class="text-right text-cyan-700">
                    {{ receipt.reward }}
                </span>
            </div>
            <div
                v-if="receipt.rewardDiscount > 0 || receipt.cashierDiscount > 0"
                class="flex justify-between"
            >
                <span class="text-slate-500">Subtotal</span>
                <span class="text-slate-700 tabular-nums">
                    {{ formatCurrency(receipt.subtotal) }}
                </span>
            </div>
            <div v-if="receipt.rewardDiscount > 0" class="flex justify-between">
                <span class="text-slate-500">Potongan reward</span>
                <span class="font-medium text-emerald-600 tabular-nums">
                    −{{ formatCurrency(receipt.rewardDiscount) }}
                </span>
            </div>
            <div
                v-if="receipt.cashierDiscount > 0"
                class="flex justify-between"
            >
                <span class="text-slate-500">Diskon kasir</span>
                <span class="font-medium text-emerald-600 tabular-nums">
                    −{{ formatCurrency(receipt.cashierDiscount) }}
                </span>
            </div>
            <div class="flex justify-between font-medium">
                <span class="text-slate-600">Total setelah potongan</span>
                <span class="text-slate-700 tabular-nums">
                    {{ formatCurrency(receipt.total) }}
                </span>
            </div>
            <div
                v-if="receipt.paymentBreakdown.length > 0"
                class="space-y-1 border-t border-dashed border-slate-200 pt-3"
            >
                <div
                    v-for="payment in receipt.paymentBreakdown"
                    :key="payment.method"
                    class="flex items-start justify-between gap-3 text-xs"
                >
                    <span class="text-slate-500">
                        {{ paymentChannelLabel(payment) }}
                        <span
                            v-if="payment.reference !== ''"
                            class="block text-[10px] text-slate-400"
                        >
                            Ref. {{ payment.reference }}
                        </span>
                    </span>
                    <span class="text-slate-700 tabular-nums">
                        {{ formatCurrency(payment.amount) }}
                    </span>
                </div>
            </div>
            <div
                class="flex justify-between border-t border-dashed border-slate-200 pt-3"
            >
                <span class="text-slate-500">Total diterima</span>
                <span class="text-lg font-semibold text-slate-900 tabular-nums">
                    {{ formatCurrency(receipt.tenderedTotal) }}
                </span>
            </div>
            <div
                v-if="receipt.change > 0"
                class="flex justify-between rounded-xl bg-orange-50 p-3 font-medium text-orange-900 ring-1 ring-orange-100"
            >
                <span>Kembalian</span>
                <span class="tabular-nums">
                    {{ formatCurrency(receipt.change) }}
                </span>
            </div>

            <div
                v-if="!receipt.isSettled"
                class="rounded-xl bg-amber-50 p-3 ring-1 ring-amber-100"
            >
                <p
                    class="flex items-center justify-between text-xs text-amber-900"
                >
                    <span>Total sudah dibayar</span>
                    <span class="font-semibold tabular-nums">
                        {{ formatCurrency(receipt.paidTotal) }}
                    </span>
                </p>
                <p
                    class="mt-1 flex items-center justify-between border-t border-amber-200/60 pt-1 text-xs font-medium text-amber-900"
                >
                    <span>Sisa tagihan</span>
                    <span class="tabular-nums">
                        {{ formatCurrency(receipt.dueAfter) }}
                    </span>
                </p>
            </div>

            <p
                v-if="isReceiptWindowBlocked"
                class="rounded-xl bg-rose-50 p-3 text-xs font-medium text-rose-700 ring-1 ring-rose-100"
            >
                Jendela struk diblokir browser. Izinkan pop-up untuk situs ini,
                lalu tekan “Buka struk”.
            </p>
        </div>

        <template #footer>
            <button
                type="button"
                class="flex flex-1 items-center justify-center gap-2 rounded-xl border border-slate-200 py-2.5 text-sm font-medium text-slate-600 transition hover:bg-slate-50"
                @click="printReceipt"
            >
                <Printer class="h-4 w-4" />
                Buka struk
            </button>
            <button
                type="button"
                class="flex-1 rounded-xl bg-slate-900 py-2.5 text-sm font-semibold text-white transition hover:bg-slate-800"
                @click="closeReceipt"
            >
                Selesai
            </button>
        </template>
    </ModalDialog>
</template>
