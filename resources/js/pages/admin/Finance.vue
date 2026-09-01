<script setup lang="ts">
import type { Fancybox } from '@fancyapps/ui';
import { Head, router, useForm } from '@inertiajs/vue3';
import {
    Banknote,
    Image as ImageIcon,
    Paperclip,
    Pencil,
    Plus,
    Trash2,
    TrendingDown,
    TrendingUp,
    TriangleAlert,
    Wallet,
} from '@lucide/vue';
import '@fancyapps/ui/dist/fancybox/fancybox.css';
import { computed, onMounted, onUnmounted, ref } from 'vue';
import {
    destroy as destroyCashEntry,
    index as indexFinance,
    store as storeCashEntry,
    update as updateCashEntry,
    updateTransaction as updateOrderTransaction,
} from '@/actions/App/Http/Controllers/Admin/FinanceController';
import TransactionShiftDialog from '@/components/admin/TransactionShiftDialog.vue';
import DataToolbar from '@/components/demo/DataToolbar.vue';
import DateFilterBar from '@/components/demo/DateFilterBar.vue';
import EmptyState from '@/components/demo/EmptyState.vue';
import ModalDialog from '@/components/demo/ModalDialog.vue';
import RecapPrintMenu from '@/components/demo/RecapPrintMenu.vue';
import StatCard from '@/components/demo/StatCard.vue';
import InputError from '@/components/InputError.vue';
import MoneyInput from '@/components/MoneyInput.vue';
import {
    formatCurrency,
    formatDate,
    formatDateCode,
} from '@/composables/useCarwashFormat';
import { useCarwashWorkflow } from '@/composables/useCarwashWorkflow';
import { matchingTransactionShifts } from '@/composables/useTransactionShift';
import { openRecapSheetWindow } from '@/lib/recapSheet';
import type { RecapPaper, RecapSheet } from '@/lib/recapSheet';
import admin from '@/routes/demo/admin';
import type {
    CarwashDateFilter,
    CarwashBrand,
    CarwashCashSummary,
    CarwashMoneyEntry,
    CarwashOrder,
    CarwashPersona,
    CarwashShift,
    CarwashTransaction,
    CarwashTransactionShiftAssignment,
    CarwashTransactionShiftOption,
} from '@/types/demo';

const props = defineProps<{
    mode: 'demo' | 'live';
    brand: CarwashBrand;
    moneyIn: CarwashMoneyEntry[];
    filters: CarwashDateFilter;
    moneyOut: CarwashMoneyEntry[];
    incomeCategories: string[];
    expenseCategories: string[];
    cashSummary: CarwashCashSummary;
    paymentMethods: string[];
    shifts: CarwashShift[];
    orders: CarwashOrder[];
    persona: CarwashPersona;
    transactionShift: CarwashTransactionShiftAssignment;
    capabilities: {
        create: boolean;
        update: boolean;
        delete: boolean;
    };
}>();

type Ledger = 'in' | 'out';
/** 'all', the id of one of the shifts this console was given, or the bucket below. */
type Shift = string;

const allShiftsKey = 'all';
const unassignedShiftKey = 'tanpa-shift';

const activeLedger = ref<Ledger>('in');
const activeShift = ref<Shift>(allShiftsKey);
const search = ref<string>('');
const categoryFilters = ref<string[]>(['Semua']);
const isFormOpen = ref<boolean>(false);
const editingEntry = ref<CarwashMoneyEntry | null>(null);
const editingPosEntry = ref<CarwashMoneyEntry | null>(null);
const deletingEntry = ref<CarwashMoneyEntry | null>(null);
const selectedTransactionEntry = ref<CarwashMoneyEntry | null>(null);
const selectedOrder = ref<CarwashOrder | null>(null);
const highlightedTransactionId = ref<string | null>(null);
/** Set when the browser refuses the recap window so the desk can retry. */
const isRecapWindowBlocked = ref<boolean>(false);

const workflow = useCarwashWorkflow();

if (props.mode === 'demo') {
    workflow.hydrateOrders(props.orders);
    workflow.hydrateMoneyIn(props.moneyIn);
    workflow.hydrateMoneyOut(props.moneyOut);
}

const incomeList = computed<CarwashMoneyEntry[]>(() =>
    props.mode === 'demo' ? workflow.moneyIn.value : props.moneyIn,
);

const expenseList = computed<CarwashMoneyEntry[]>(() =>
    props.mode === 'demo' ? workflow.moneyOut.value : props.moneyOut,
);

const orderList = computed<CarwashOrder[]>(() =>
    props.mode === 'demo' ? workflow.orders.value : props.orders,
);

interface PendingAttachment {
    id: string;
    file: File;
    previewUrl: string | null;
}

const pendingAttachments = ref<PendingAttachment[]>([]);
const removedAttachmentIds = ref<number[]>([]);
const pendingShiftEntry = ref(false);
const overlappingTransactionShifts = ref<CarwashTransactionShiftOption[]>([]);

const draft = ref({
    category: props.incomeCategories[0],
    description: '',
    amount: 0,
    method: 'Tunai',
});

/** The live ledger posts the entry, including a real supporting document. */
const entryForm = useForm<{
    direction: Ledger;
    category: string;
    description: string;
    amount: number;
    method: string;
    attachments: File[];
    removed_attachment_ids: number[];
    transaction_shift_id: number | null;
}>({
    direction: 'in',
    category: props.incomeCategories[0],
    description: '',
    amount: 0,
    method: 'Tunai',
    attachments: [],
    removed_attachment_ids: [],
    transaction_shift_id: null,
});

const deleteForm = useForm({});

const transactionForm = useForm<{
    amount: number;
    channels: Array<{
        label: string;
        amount: number;
        reference: string;
    }>;
}>({
    amount: 0,
    channels: [],
});

const activeCategories = computed<string[]>(() =>
    activeLedger.value === 'in'
        ? props.incomeCategories
        : props.expenseCategories,
);

const shiftTabs = computed(() => [
    {
        id: allShiftsKey,
        label: 'Seluruh Shift & Tanpa Shift',
        caption: 'Semua transaksi',
    },
    ...props.shifts.map((shift) => ({
        id: shift.id,
        label: shift.name,
        caption: shift.time
            ? shift.cashier
                ? `${shift.time} · ${shift.cashier}`
                : shift.time
            : null,
    })),
    {
        id: unassignedShiftKey,
        label: 'Tanpa Shift',
        caption: 'Dicatat tanpa shift',
    },
]);

/**
 * An entry is filtered by the shift name resolved and stamped when it was
 * written. Reporting never re-derives that value from the transaction hour,
 * so later schedule changes cannot move historical money between tabs.
 */
function isInActiveShift(entry: CarwashMoneyEntry): boolean {
    if (activeShift.value === allShiftsKey) {
        return true;
    }

    const entryShift = entry.shift ?? null;

    /*
     * Rows written without a shift land here, and so do rows stamped with a
     * shift that has since been renamed or retired, so the tabs still add up.
     */
    if (activeShift.value === unassignedShiftKey) {
        return (
            entryShift === null ||
            !props.shifts.some((shift) => shift.name === entryShift)
        );
    }

    /* An entry records the shift by name; a tab is keyed by its id. */
    return (
        props.shifts.find((shift) => shift.id === activeShift.value)?.name ===
        entryShift
    );
}

const scopedIncome = computed<CarwashMoneyEntry[]>(() =>
    incomeList.value.filter(
        (entry) =>
            (props.filters.date === '' || entry.date === props.filters.date) &&
            isInActiveShift(entry),
    ),
);

const scopedExpenses = computed<CarwashMoneyEntry[]>(() =>
    expenseList.value.filter(
        (entry) =>
            (props.filters.date === '' || entry.date === props.filters.date) &&
            isInActiveShift(entry),
    ),
);

const activeEntries = computed<CarwashMoneyEntry[]>(() =>
    activeLedger.value === 'in' ? scopedIncome.value : scopedExpenses.value,
);

const filterOptions = computed<string[]>(() => [
    'Semua',
    ...new Set(activeEntries.value.map((entry) => entry.category)),
]);

const filteredEntries = computed<CarwashMoneyEntry[]>(() => {
    const query = search.value.trim().toLowerCase();

    return activeEntries.value.filter((entry) => {
        const matchesCategory =
            categoryFilters.value.includes('Semua') ||
            categoryFilters.value.includes(entry.category);
        const matchesQuery =
            query === '' ||
            entry.description.toLowerCase().includes(query) ||
            entry.ref.toLowerCase().includes(query) ||
            entry.recordedBy.toLowerCase().includes(query) ||
            entry.orderNo?.toLowerCase().includes(query) ||
            entry.customer?.toLowerCase().includes(query) ||
            entry.plate?.toLowerCase().includes(query);

        return matchesCategory && matchesQuery;
    });
});

const totalIn = computed<number>(() =>
    scopedIncome.value.reduce((total, entry) => total + entry.amount, 0),
);

const totalOut = computed<number>(() =>
    scopedExpenses.value.reduce((total, entry) => total + entry.amount, 0),
);

const remainingBalance = computed<number>(() => totalIn.value - totalOut.value);

const financialChannels = props.paymentMethods.map((key) => ({
    key,
    label: key === 'E-Money' ? 'Emoney' : key,
}));

function channelTotal(entries: CarwashMoneyEntry[], channel: string): number {
    return entries.reduce(
        (total, entry) =>
            total +
            entry.channelBreakdown
                .filter((item) => item.label === channel)
                .reduce((subtotal, item) => subtotal + item.amount, 0),
        0,
    );
}

const channelRows = computed(() =>
    financialChannels.map((channel) => {
        const income = channelTotal(scopedIncome.value, channel.key);
        const expense = channelTotal(scopedExpenses.value, channel.key);

        return {
            ...channel,
            income,
            expense,
            balance: income - expense,
        };
    }),
);

/**
 * What the shift being handed over comes to: the three figures on the cards and
 * the channel table under them, and nothing else. The ledger rows behind them
 * are deliberately left off — the sheet is a handover, not a statement.
 *
 * Everything is read off the same computeds the page renders, so the printout
 * agrees with the screen, and the shift is whichever tab is open.
 */
function financeRecapSheet(): RecapSheet {
    const tab = shiftTabs.value.find((shift) => shift.id === activeShift.value);
    const channels = channelRows.value;

    const totalRow = {
        label: 'Total',
        values: [
            formatCurrency(totalIn.value),
            formatCurrency(totalOut.value),
            formatCurrency(remainingBalance.value),
        ],
        tones: [
            'positive' as const,
            'negative' as const,
            remainingBalance.value < 0 ? ('negative' as const) : undefined,
        ],
    };

    return {
        title: 'Rekap Keuangan',
        slug: 'rekap-keuangan',
        periodLabel: formatDate(props.filters.date),
        shiftLabel: tab?.label ?? 'Seluruh Shift & Tanpa Shift',
        shiftCaption: tab?.caption ?? null,
        meta: [{ label: 'Dicetak oleh', value: props.persona.name }],
        summary: [
            {
                label: 'Uang masuk',
                value: formatCurrency(totalIn.value),
                caption: `${scopedIncome.value.length} transaksi tercatat`,
                tone: 'positive',
            },
            {
                label: 'Uang keluar',
                value: formatCurrency(totalOut.value),
                caption: `${scopedExpenses.value.length} pengeluaran`,
                tone: 'negative',
            },
            {
                label: 'Sisa saldo',
                value: formatCurrency(remainingBalance.value),
                caption: 'Uang masuk dikurangi uang keluar',
                tone: remainingBalance.value < 0 ? 'negative' : 'default',
            },
        ],
        tables: [
            {
                heading: 'Kanal Keuangan',
                caption:
                    'Ringkasan pemasukan, pengeluaran, dan saldo per kanal',
                columns: [
                    'Kanal Keuangan',
                    'Pemasukan',
                    'Pengeluaran',
                    'Saldo Kanal',
                ],
                rows: channels.map((channel) => ({
                    label: channel.label,
                    values: [
                        formatCurrency(channel.income),
                        formatCurrency(channel.expense),
                        formatCurrency(channel.balance),
                    ],
                    tones: [
                        'positive' as const,
                        'negative' as const,
                        channel.balance < 0
                            ? ('negative' as const)
                            : ('default' as const),
                    ],
                })),
                /*
                 * The channels only add up to the day when every entry names
                 * one, so the foot restates the cards rather than summing the
                 * column and quietly disagreeing with them.
                 */
                total: {
                    ...totalRow,
                    tones: totalRow.tones.map((tone) => tone ?? 'default'),
                },
            },
        ],
        timezone: props.filters.timezone,
    };
}

/** Hands the recap to its own window; a blocked window is flagged, not fatal. */
function printRecap(paper: RecapPaper): void {
    isRecapWindowBlocked.value =
        openRecapSheetWindow(financeRecapSheet(), props.brand, paper) === null;
}

const visibleStoredAttachments = computed(() =>
    (editingEntry.value?.attachments ?? []).filter(
        (attachment) =>
            typeof attachment.id !== 'number' ||
            !removedAttachmentIds.value.includes(attachment.id),
    ),
);

const attachmentCount = computed(
    () =>
        visibleStoredAttachments.value.length + pendingAttachments.value.length,
);

const canAddAttachment = computed(() => attachmentCount.value < 10);

/** Outgoing money must retain at least one supporting document (BR-10). */
const requiresAttachment = computed<boolean>(
    () => activeLedger.value === 'out' && attachmentCount.value === 0,
);

const canSave = computed<boolean>(() => {
    if (draft.value.description.trim() === '' || draft.value.amount <= 0) {
        return false;
    }

    return !requiresAttachment.value;
});

const attachmentError = computed<string | undefined>(() => {
    const errors = entryForm.errors as Record<string, string | undefined>;

    return (
        errors.attachments ??
        Object.entries(errors).find(([key]) =>
            key.startsWith('attachments.'),
        )?.[1]
    );
});

function isEditable(entry: CarwashMoneyEntry): boolean {
    return (
        props.mode === 'live' &&
        (cashEntryId(entry) !== null || posTransactionId(entry) !== null)
    );
}

/**
 * The row id of a hand-written entry. A payment read back from the till is
 * keyed by its transaction reference instead, and has no ledger row to address.
 */
function cashEntryId(entry: CarwashMoneyEntry): number | null {
    return typeof entry.id === 'number' ? entry.id : null;
}

function posTransactionId(entry: CarwashMoneyEntry): number | null {
    return entry.source === 'pos' && typeof entry.transactionId === 'number'
        ? entry.transactionId
        : null;
}

function switchLedger(ledger: Ledger): void {
    activeLedger.value = ledger;
    categoryFilters.value = ['Semua'];
    search.value = '';
}

function switchShift(shift: Shift): void {
    activeShift.value = shift;
    categoryFilters.value = ['Semua'];
    search.value = '';
}

function toggleCategoryFilter(category: string): void {
    if (category === 'Semua') {
        categoryFilters.value = ['Semua'];

        return;
    }

    const selectedCategories = categoryFilters.value.filter(
        (selectedCategory) => selectedCategory !== 'Semua',
    );

    categoryFilters.value = selectedCategories.includes(category)
        ? selectedCategories.filter(
              (selectedCategory) => selectedCategory !== category,
          )
        : [...selectedCategories, category];

    if (categoryFilters.value.length === 0) {
        categoryFilters.value = ['Semua'];
    }
}

function clearPendingAttachments(): void {
    pendingAttachments.value.forEach((attachment) => {
        if (attachment.previewUrl) {
            URL.revokeObjectURL(attachment.previewUrl);
        }
    });
    pendingAttachments.value = [];
    entryForm.attachments = [];
}

function resetAttachmentDraft(): void {
    clearPendingAttachments();
    removedAttachmentIds.value = [];
    entryForm.removed_attachment_ids = [];
}

function closeEntryForm(): void {
    isFormOpen.value = false;
    resetAttachmentDraft();
}

function openForm(): void {
    resetAttachmentDraft();
    editingEntry.value = null;
    draft.value = {
        category: activeCategories.value[0],
        description: '',
        amount: 0,
        method: 'Tunai',
    };
    entryForm.clearErrors();
    isFormOpen.value = true;
}

function openEditForm(entry: CarwashMoneyEntry): void {
    if (!isEditable(entry) || !props.capabilities.update) {
        return;
    }

    selectedTransactionEntry.value = null;

    if (posTransactionId(entry) !== null) {
        editingPosEntry.value = entry;
        transactionForm.clearErrors();
        transactionForm.amount = entry.amount;
        transactionForm.channels = entry.channelBreakdown.map((channel) => ({
            label: channel.label,
            amount: channel.amount,
            reference: channel.reference ?? '',
        }));

        return;
    }

    resetAttachmentDraft();
    editingEntry.value = entry;
    draft.value = {
        category: entry.category,
        description: entry.description,
        amount: entry.amount,
        method: entry.method,
    };
    entryForm.clearErrors();
    isFormOpen.value = true;
}

function openDeleteEntry(entry: CarwashMoneyEntry): void {
    if (cashEntryId(entry) === null || !props.capabilities.delete) {
        return;
    }

    deleteForm.clearErrors();
    deletingEntry.value = entry;
}

function confirmDeleteEntry(): void {
    const entryId =
        deletingEntry.value === null ? null : cashEntryId(deletingEntry.value);

    if (entryId === null) {
        return;
    }

    deleteForm.submit(destroyCashEntry(entryId), {
        preserveScroll: true,
        onSuccess: () => {
            deletingEntry.value = null;
        },
    });
}

function findRelatedOrder(entry: CarwashMoneyEntry): CarwashOrder | null {
    if (entry.orderId == null) {
        return null;
    }

    return orderList.value.find((order) => order.id === entry.orderId) ?? null;
}

function transactionIdFromEntry(entry: CarwashMoneyEntry): string | null {
    return typeof entry.id === 'string' && entry.id.startsWith('pos-')
        ? entry.id.slice(4)
        : null;
}

function openTransactionRecap(entry: CarwashMoneyEntry): void {
    selectedTransactionEntry.value = entry;
}

function editSelectedTransaction(): void {
    if (selectedTransactionEntry.value === null) {
        return;
    }

    const entry = selectedTransactionEntry.value;

    openEditForm(entry);
}

const transactionChannelTotal = computed<number>(() =>
    transactionForm.channels.reduce(
        (total, channel) => total + Math.max(channel.amount, 0),
        0,
    ),
);

const canSavePosTransaction = computed<boolean>(
    () =>
        editingPosEntry.value !== null &&
        transactionForm.amount > 0 &&
        transactionForm.channels.length > 0 &&
        transactionForm.channels.every(
            (channel) => channel.label !== '' && channel.amount > 0,
        ) &&
        transactionChannelTotal.value === transactionForm.amount,
);

function addTransactionChannel(): void {
    const method = props.paymentMethods.find(
        (candidate) =>
            !transactionForm.channels.some(
                (channel) => channel.label === candidate,
            ),
    );

    if (!method) {
        return;
    }

    transactionForm.channels.push({
        label: method,
        amount: 0,
        reference: '',
    });
}

function removeTransactionChannel(index: number): void {
    if (transactionForm.channels.length <= 1) {
        return;
    }

    transactionForm.channels.splice(index, 1);
}

function isTransactionMethodDisabled(method: string, index: number): boolean {
    return transactionForm.channels.some(
        (channel, channelIndex) =>
            channelIndex !== index && channel.label === method,
    );
}

function closePosTransactionForm(): void {
    editingPosEntry.value = null;
    transactionForm.clearErrors();
    transactionForm.reset();
}

function savePosTransaction(): void {
    const transactionId =
        editingPosEntry.value === null
            ? null
            : posTransactionId(editingPosEntry.value);

    if (transactionId === null || !canSavePosTransaction.value) {
        return;
    }

    transactionForm.submit(updateOrderTransaction(transactionId), {
        preserveScroll: true,
        onSuccess: closePosTransactionForm,
    });
}

function openOrderRecap(entry: CarwashMoneyEntry): void {
    const order = findRelatedOrder(entry);

    if (!order) {
        return;
    }

    selectedOrder.value = order;
    highlightedTransactionId.value = transactionIdFromEntry(entry);
}

function closeOrderRecap(): void {
    selectedOrder.value = null;
    highlightedTransactionId.value = null;
}

function orderTransactionReference(
    order: CarwashOrder,
    transaction: CarwashTransaction,
    transactionIndex: number,
): string {
    return transactionReference(
        `${transaction.type} Order`,
        transaction.date,
        `${order.orderNo}-TRX-${transactionIndex + 1}`,
    );
}

function transactionTypeLabel(entry: CarwashMoneyEntry): string {
    if (entry.category === 'Pembayaran Sebagian/Booking Order') {
        return 'Pembayaran Sebagian/Booking';
    }

    return entry.category;
}

function syncPendingAttachments(): void {
    entryForm.attachments = pendingAttachments.value.map(
        (attachment) => attachment.file,
    );
}

function formatAttachmentSize(bytes: number): string {
    return bytes >= 1048576
        ? `${(bytes / 1048576).toFixed(1)} MB`
        : `${Math.max(1, Math.round(bytes / 1024))} KB`;
}

/** Each selection appends to the current draft until the ten-file limit. */
function onFileSelected(event: Event): void {
    const input = event.target as HTMLInputElement;
    const availableSlots = Math.max(0, 10 - attachmentCount.value);
    const files = Array.from(input.files ?? []).slice(0, availableSlots);

    pendingAttachments.value.push(
        ...files.map((file) => ({
            id: `${file.name}-${file.size}-${file.lastModified}-${crypto.randomUUID()}`,
            file,
            previewUrl: file.type.startsWith('image/')
                ? URL.createObjectURL(file)
                : null,
        })),
    );
    syncPendingAttachments();
    input.value = '';
}

function removePendingAttachment(id: string): void {
    const attachment = pendingAttachments.value.find((item) => item.id === id);

    if (attachment?.previewUrl) {
        URL.revokeObjectURL(attachment.previewUrl);
    }

    pendingAttachments.value = pendingAttachments.value.filter(
        (item) => item.id !== id,
    );
    syncPendingAttachments();
}

function removeStoredAttachment(id: number | string): void {
    if (typeof id !== 'number') {
        return;
    }

    removedAttachmentIds.value.push(id);
    entryForm.removed_attachment_ids = [...removedAttachmentIds.value];
}

function transactionReference(
    category: string,
    date: string,
    identifier: string | number,
): string {
    const categoryCode = (category.toUpperCase().match(/[A-Z0-9]+/g) ?? [])
        .map((word) => word[0])
        .join('');
    const stableIdentifier =
        typeof identifier === 'number'
            ? String(identifier).padStart(4, '0')
            : identifier.toUpperCase().replace(/[^A-Z0-9]+/g, '');

    return `TRX-${categoryCode}-${formatDateCode(date)}-${stableIdentifier}`;
}

function saveEntry(): void {
    if (!canSave.value) {
        return;
    }

    if (editingEntry.value !== null) {
        completeEntrySave(null);

        return;
    }

    const matchingShifts = matchingTransactionShifts(
        props.transactionShift,
        props.filters.timezone,
    );

    if (matchingShifts.length > 1) {
        pendingShiftEntry.value = true;
        overlappingTransactionShifts.value = matchingShifts;

        return;
    }

    completeEntrySave(matchingShifts[0]?.id ?? null);
}

function selectTransactionShift(shiftId: number): void {
    closeTransactionShiftDialog();
    completeEntrySave(shiftId);
}

function closeTransactionShiftDialog(): void {
    pendingShiftEntry.value = false;
    overlappingTransactionShifts.value = [];
}

function completeEntrySave(transactionShiftId: number | null): void {
    if (props.mode === 'live') {
        saveLiveEntry(transactionShiftId);

        return;
    }

    saveDemoEntry(transactionShiftId);
}

/** The live ledger writes to the database and re-reads the reloaded props. */
function saveLiveEntry(transactionShiftId: number | null): void {
    entryForm.direction = activeLedger.value;
    entryForm.category = draft.value.category;
    entryForm.description = draft.value.description;
    entryForm.amount = draft.value.amount;
    entryForm.method = draft.value.method;
    entryForm.transaction_shift_id = transactionShiftId;

    const editingId =
        editingEntry.value === null ? null : cashEntryId(editingEntry.value);
    const action =
        editingId === null ? storeCashEntry() : updateCashEntry(editingId);

    entryForm.submit(action, {
        preserveScroll: true,
        onSuccess: () => {
            isFormOpen.value = false;
            editingEntry.value = null;
            clearPendingAttachments();
            removedAttachmentIds.value = [];
            entryForm.reset();
        },
    });
}

/** The demo console keeps its entries in memory instead of hitting the database. */
function saveDemoEntry(transactionShiftId: number | null): void {
    const isIncome = activeLedger.value === 'in';
    const sequence =
        (isIncome ? incomeList.value.length : expenseList.value.length) + 32;

    const transactionShiftName =
        props.transactionShift.mode === 'schedule'
            ? (props.transactionShift.shifts.find(
                  (shift) => shift.id === transactionShiftId,
              )?.name ?? null)
            : props.persona.shift || null;
    const entry: CarwashMoneyEntry = {
        id: sequence,
        ref: transactionReference(
            draft.value.category,
            props.filters.today,
            sequence,
        ),
        date: props.filters.today,
        time: new Intl.DateTimeFormat('id-ID', {
            hour: '2-digit',
            minute: '2-digit',
            hour12: false,
            timeZone: props.filters.timezone,
        })
            .format(new Date())
            .replace(':', '.'),
        category: draft.value.category,
        description: draft.value.description,
        amount: draft.value.amount,
        method: draft.value.method,
        channelBreakdown: [
            { label: draft.value.method, amount: draft.value.amount },
        ],
        recordedBy: props.persona.name,
        shift: transactionShiftName,
        attachments: pendingAttachments.value.map((attachment, index) => ({
            id: `demo-${sequence}-${index}`,
            name: attachment.file.name,
            size: '—',
            url: null,
            isImage: attachment.file.type.startsWith('image/'),
        })),
    };

    if (isIncome) {
        workflow.addMoneyIn(entry);
    } else {
        workflow.addMoneyOut(entry);
    }

    closeEntryForm();
}

/*
 * Image attachments open in place rather than downloading. Fancybox binds by
 * delegation, so rows that appear later — a new filter, a fresh visit — are
 * picked up without rebinding. Its hash integration stays disabled so closing
 * an attachment cannot navigate Inertia's history or lose the ledger position.
 */
const LIGHTBOX_GROUP = 'lampiran-keuangan';
type FancyboxApi = typeof Fancybox;

let attachmentLightbox: FancyboxApi | null = null;
let financePageUnmounted = false;

/** Fancybox mutates browser globals when imported, so SSR must never load it. */
async function bindAttachmentLightbox(): Promise<void> {
    const { Fancybox } = await import('@fancyapps/ui');

    if (financePageUnmounted) {
        return;
    }

    attachmentLightbox = Fancybox;
    Fancybox.bind(`[data-fancybox="${LIGHTBOX_GROUP}"]`, {
        Hash: false,
    });
}

onMounted(() => {
    void bindAttachmentLightbox();
});

onUnmounted(() => {
    financePageUnmounted = true;
    clearPendingAttachments();
    attachmentLightbox?.destroy();
});

/** Filtering is a fresh visit, so the page rebuilds from the narrowed props. */
function applyDate(date: string): void {
    router.get(
        props.mode === 'demo' ? admin.finance.url() : indexFinance.url(),
        { date },
        { preserveScroll: true, replace: true },
    );
}
</script>

<template>
    <Head :title="`${brand.name} — Keuangan`" />

    <TransactionShiftDialog
        :open="pendingShiftEntry"
        :shifts="overlappingTransactionShifts"
        @close="closeTransactionShiftDialog"
        @select="selectTransactionShift"
    />

    <div class="space-y-4">
        <DateFilterBar :filters="filters" @change="applyDate" />

        <section
            class="rounded-2xl border border-slate-200/80 bg-white p-2 shadow-sm"
        >
            <div
                class="flex flex-col gap-1 sm:flex-row sm:flex-wrap"
                role="tablist"
                aria-label="Filter shift"
            >
                <button
                    v-for="shift in shiftTabs"
                    :key="shift.id"
                    type="button"
                    role="tab"
                    class="flex-1 rounded-xl px-4 py-3 text-left transition focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-cyan-600 sm:basis-40"
                    :class="
                        activeShift === shift.id
                            ? 'bg-cyan-50 text-cyan-700 shadow-sm ring-1 ring-cyan-200'
                            : 'text-slate-500 hover:bg-slate-50 hover:text-slate-900'
                    "
                    :aria-selected="activeShift === shift.id"
                    @click="switchShift(shift.id)"
                >
                    <span class="block text-sm font-semibold">
                        {{ shift.label }}
                    </span>
                    <span
                        v-if="shift.caption"
                        class="mt-0.5 block text-[11px] opacity-75"
                    >
                        {{ shift.caption }}
                    </span>
                </button>
            </div>
        </section>

        <section
            class="grid grid-cols-1 gap-4 xl:grid-cols-[minmax(260px,0.75fr)_minmax(0,2.25fr)]"
        >
            <div class="grid grid-cols-1 gap-4 sm:grid-cols-3 xl:grid-cols-1">
                <StatCard
                    label="Uang masuk"
                    :value="formatCurrency(totalIn)"
                    :caption="`${scopedIncome.length} transaksi tercatat`"
                    :icon="TrendingUp"
                    tone="emerald"
                />
                <StatCard
                    label="Uang keluar"
                    :value="formatCurrency(totalOut)"
                    :caption="`${scopedExpenses.length} pengeluaran`"
                    :icon="TrendingDown"
                    tone="rose"
                />
                <StatCard
                    label="Sisa saldo"
                    :value="formatCurrency(remainingBalance)"
                    caption="Uang masuk dikurangi uang keluar"
                    :icon="Wallet"
                    tone="amber"
                />
            </div>

            <article
                class="overflow-hidden rounded-2xl border border-slate-200/80 bg-white shadow-sm"
            >
                <div
                    class="flex items-start justify-between gap-3 border-b border-slate-100 px-5 py-4"
                >
                    <div class="min-w-0">
                        <h2 class="font-semibold text-slate-900">
                            Kanal Keuangan
                        </h2>
                        <p class="mt-0.5 text-xs text-slate-500">
                            Ringkasan pemasukan, pengeluaran, dan saldo per
                            kanal
                        </p>
                    </div>
                    <RecapPrintMenu
                        :blocked="isRecapWindowBlocked"
                        @print="printRecap"
                    />
                </div>
                <div class="overflow-x-auto">
                    <table class="w-full min-w-[680px] text-sm">
                        <thead>
                            <tr
                                class="border-b border-slate-100 text-left text-[11px] font-medium tracking-wider text-slate-400 uppercase"
                            >
                                <th class="px-5 py-3">Kanal Keuangan</th>
                                <th class="px-5 py-3 text-right">Pemasukan</th>
                                <th class="px-5 py-3 text-right">
                                    Pengeluaran
                                </th>
                                <th class="px-5 py-3 text-right">
                                    Saldo Kanal
                                </th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-slate-50">
                            <tr
                                v-for="channel in channelRows"
                                :key="channel.key"
                                class="transition hover:bg-slate-50/70"
                            >
                                <td
                                    class="px-5 py-4 font-medium text-slate-900"
                                >
                                    {{ channel.label }}
                                </td>
                                <td
                                    class="px-5 py-4 text-right font-medium text-emerald-600 tabular-nums"
                                >
                                    {{ formatCurrency(channel.income) }}
                                </td>
                                <td
                                    class="px-5 py-4 text-right font-medium text-rose-600 tabular-nums"
                                >
                                    {{ formatCurrency(channel.expense) }}
                                </td>
                                <td
                                    class="px-5 py-4 text-right font-semibold tabular-nums"
                                    :class="
                                        channel.balance < 0
                                            ? 'text-rose-600'
                                            : 'text-slate-900'
                                    "
                                >
                                    {{ formatCurrency(channel.balance) }}
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </article>
        </section>

        <!-- Ledger -->
        <section
            class="rounded-2xl border border-slate-200/80 bg-white shadow-sm"
        >
            <div class="border-b border-slate-100 p-5">
                <div class="flex flex-wrap items-center justify-between gap-3">
                    <div class="flex gap-1 rounded-xl bg-slate-100 p-1 text-sm">
                        <button
                            type="button"
                            class="rounded-lg px-4 py-2 font-medium transition"
                            :class="
                                activeLedger === 'in'
                                    ? 'bg-white text-slate-900 shadow-sm'
                                    : 'text-slate-500'
                            "
                            @click="switchLedger('in')"
                        >
                            Uang Masuk
                        </button>
                        <button
                            type="button"
                            class="rounded-lg px-4 py-2 font-medium transition"
                            :class="
                                activeLedger === 'out'
                                    ? 'bg-white text-slate-900 shadow-sm'
                                    : 'text-slate-500'
                            "
                            @click="switchLedger('out')"
                        >
                            Uang Keluar
                        </button>
                    </div>

                    <button
                        v-if="capabilities.create"
                        type="button"
                        class="flex items-center gap-2 rounded-xl bg-gradient-to-r from-cyan-500 to-sky-600 px-3 py-2 text-sm font-medium text-white shadow-lg shadow-cyan-500/25 transition hover:from-cyan-600 hover:to-sky-700"
                        @click="openForm"
                    >
                        <Plus class="h-4 w-4" />
                        Catat
                        {{
                            activeLedger === 'in' ? 'Pemasukan' : 'Pengeluaran'
                        }}
                    </button>
                </div>

                <div class="mt-3">
                    <DataToolbar
                        v-model:search="search"
                        placeholder="Cari transaksi / order / plat"
                        :filters="filterOptions"
                        :active-filter="categoryFilters"
                        wide-search
                        @filter="toggleCategoryFilter"
                    />
                </div>
            </div>

            <div v-if="filteredEntries.length > 0" class="overflow-x-auto">
                <table class="w-full min-w-[1080px] text-sm">
                    <thead>
                        <tr
                            class="border-b border-slate-100 text-left text-[11px] font-medium tracking-wider text-slate-400 uppercase"
                        >
                            <th class="px-5 py-3">Referensi</th>
                            <th class="px-5 py-3">Kategori</th>
                            <th class="px-5 py-3">Deskripsi</th>
                            <th class="px-5 py-3">Order terkait</th>
                            <th class="px-5 py-3">Metode</th>
                            <th class="px-5 py-3">Dicatat oleh</th>
                            <th class="px-5 py-3">Lampiran</th>
                            <th class="px-5 py-3 text-right">Nominal</th>
                            <th
                                v-if="mode === 'live'"
                                class="px-5 py-3 text-right"
                            >
                                Aksi
                            </th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-50">
                        <tr
                            v-for="entry in filteredEntries"
                            :key="entry.id"
                            class="transition hover:bg-slate-50/70"
                        >
                            <td class="px-5 py-3.5">
                                <button
                                    type="button"
                                    class="text-left font-semibold wrap-anywhere text-cyan-700 underline decoration-cyan-300 underline-offset-2 transition hover:text-cyan-900 focus-visible:rounded focus-visible:ring-2 focus-visible:ring-cyan-500 focus-visible:ring-offset-2 focus-visible:outline-none"
                                    :aria-label="`Lihat rekap transaksi ${entry.ref}`"
                                    @click="openTransactionRecap(entry)"
                                >
                                    {{ entry.ref }}
                                </button>
                                <p class="text-[11px] text-slate-500">
                                    {{ formatDate(entry.date) }} •
                                    {{ entry.time }}
                                </p>
                            </td>
                            <td class="px-5 py-3.5">
                                <span
                                    class="inline-block max-w-48 rounded-lg bg-slate-100 px-2 py-1 text-[11px] leading-snug font-medium whitespace-normal text-slate-600"
                                >
                                    <template
                                        v-if="
                                            entry.category ===
                                            'Pembayaran Sisa/Lunas (Order Selesai)'
                                        "
                                    >
                                        <span class="block whitespace-nowrap">
                                            Pembayaran Sisa/Lunas
                                        </span>
                                        <span class="block whitespace-nowrap">
                                            (Order Selesai)
                                        </span>
                                    </template>
                                    <template v-else>
                                        {{ entry.category }}
                                    </template>
                                </span>
                            </td>
                            <td
                                class="max-w-[240px] px-5 py-3.5 text-slate-600"
                            >
                                {{ entry.description }}
                            </td>
                            <td class="px-5 py-3.5">
                                <template v-if="entry.orderNo">
                                    <button
                                        type="button"
                                        class="text-left font-semibold text-cyan-700 underline decoration-cyan-300 underline-offset-2 transition hover:text-cyan-900 focus-visible:rounded focus-visible:ring-2 focus-visible:ring-cyan-500 focus-visible:ring-offset-2 focus-visible:outline-none"
                                        :aria-label="`Lihat rekap order ${entry.orderNo}`"
                                        @click="openOrderRecap(entry)"
                                    >
                                        {{ entry.orderNo }}
                                    </button>
                                    <p
                                        class="mt-0.5 text-[11px] text-slate-500"
                                    >
                                        {{ entry.customer }}
                                    </p>
                                    <p class="text-[11px] text-slate-500">
                                        {{ entry.vehicle }} · {{ entry.plate }}
                                    </p>
                                </template>
                                <span v-else class="text-xs text-slate-400">
                                    Tidak terkait order
                                </span>
                            </td>
                            <td class="px-5 py-3.5 text-slate-600">
                                <div
                                    v-if="entry.channelBreakdown.length > 1"
                                    class="space-y-1"
                                >
                                    <div
                                        v-for="channel in entry.channelBreakdown"
                                        :key="channel.label"
                                        class="flex items-center justify-between gap-3 whitespace-nowrap"
                                    >
                                        <span>{{ channel.label }}</span>
                                        <span
                                            class="font-medium text-slate-900 tabular-nums"
                                        >
                                            {{ formatCurrency(channel.amount) }}
                                        </span>
                                    </div>
                                </div>
                                <span v-else>{{ entry.method }}</span>
                            </td>
                            <td class="px-5 py-3.5 text-slate-600">
                                {{ entry.recordedBy }}
                                <p
                                    v-if="entry.shift"
                                    class="mt-0.5 text-[11px] text-slate-400"
                                >
                                    {{ entry.shift }}
                                </p>
                            </td>
                            <td class="px-5 py-3.5">
                                <div
                                    v-if="entry.attachments?.length"
                                    class="space-y-1.5"
                                >
                                    <a
                                        v-for="attachment in entry.attachments"
                                        :key="attachment.id"
                                        :href="attachment.url || undefined"
                                        :data-fancybox="
                                            attachment.isImage
                                                ? LIGHTBOX_GROUP
                                                : null
                                        "
                                        :data-caption="
                                            attachment.isImage
                                                ? `${entry.ref} — ${entry.description}`
                                                : null
                                        "
                                        class="flex items-center gap-1.5 text-[11px] text-cyan-700 underline decoration-cyan-300 underline-offset-2 transition hover:text-cyan-900"
                                    >
                                        <component
                                            :is="
                                                attachment.isImage
                                                    ? ImageIcon
                                                    : Paperclip
                                            "
                                            class="h-3.5 w-3.5 shrink-0"
                                        />
                                        <span class="max-w-[10rem] truncate">
                                            {{ attachment.name }}
                                        </span>
                                    </a>
                                </div>
                                <span
                                    v-else
                                    class="flex items-center gap-1 text-[11px]"
                                    :class="
                                        activeLedger === 'out'
                                            ? 'text-rose-500'
                                            : 'text-slate-400'
                                    "
                                >
                                    <TriangleAlert
                                        v-if="activeLedger === 'out'"
                                        class="h-3.5 w-3.5"
                                    />
                                    {{
                                        activeLedger === 'out'
                                            ? 'Belum ada'
                                            : '—'
                                    }}
                                </span>
                            </td>
                            <td
                                class="px-5 py-3.5 text-right font-semibold tabular-nums"
                                :class="
                                    activeLedger === 'in'
                                        ? 'text-emerald-600'
                                        : 'text-rose-600'
                                "
                            >
                                {{ activeLedger === 'in' ? '+' : '−'
                                }}{{ formatCurrency(entry.amount) }}
                            </td>
                            <td v-if="mode === 'live'" class="px-5 py-3.5">
                                <div
                                    v-if="isEditable(entry)"
                                    class="flex items-center justify-end gap-1"
                                >
                                    <button
                                        v-if="capabilities.update"
                                        type="button"
                                        class="rounded-lg p-1.5 text-slate-400 transition hover:bg-slate-100 hover:text-cyan-700"
                                        :aria-label="`Ubah catatan ${entry.ref}`"
                                        @click="openEditForm(entry)"
                                    >
                                        <Pencil class="h-4 w-4" />
                                    </button>
                                    <button
                                        v-if="
                                            capabilities.delete &&
                                            cashEntryId(entry) !== null
                                        "
                                        type="button"
                                        class="rounded-lg p-1.5 text-slate-400 transition hover:bg-rose-50 hover:text-rose-600"
                                        :aria-label="`Hapus catatan ${entry.ref}`"
                                        @click="openDeleteEntry(entry)"
                                    >
                                        <Trash2 class="h-4 w-4" />
                                    </button>
                                </div>
                                <span
                                    v-else
                                    class="block text-right text-[11px] text-slate-400"
                                >
                                    Dari kasir
                                </span>
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>

            <EmptyState
                v-else
                :icon="Banknote"
                title="Belum ada catatan"
                caption="Ubah filter atau catat transaksi baru."
            />
        </section>
    </div>

    <ModalDialog
        :open="selectedTransactionEntry !== null"
        title="Detail Transaksi"
        :caption="selectedTransactionEntry?.ref"
        size="lg"
        @close="selectedTransactionEntry = null"
    >
        <template v-if="selectedTransactionEntry">
            <div
                class="rounded-2xl border border-emerald-200 bg-emerald-50 p-5"
            >
                <div class="flex items-start justify-between gap-4">
                    <div class="min-w-0">
                        <p
                            class="text-[11px] font-semibold tracking-wide text-emerald-700 uppercase"
                        >
                            {{ transactionTypeLabel(selectedTransactionEntry) }}
                        </p>
                        <p
                            class="mt-2 text-2xl font-bold text-emerald-950 tabular-nums"
                        >
                            {{
                                formatCurrency(selectedTransactionEntry.amount)
                            }}
                        </p>
                        <p class="mt-1 text-xs text-emerald-800/75">
                            {{ formatDate(selectedTransactionEntry.date) }} ·
                            {{ selectedTransactionEntry.time }}
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
                        {{ selectedTransactionEntry.ref }}
                    </dd>
                </div>
                <div>
                    <dt
                        class="text-[10px] font-medium tracking-wide text-slate-400 uppercase"
                    >
                        Dicatat oleh
                    </dt>
                    <dd class="mt-1 text-xs font-medium text-slate-700">
                        <span class="block">
                            {{ selectedTransactionEntry.recordedBy }}
                        </span>
                        <span class="mt-0.5 block text-[11px] text-slate-500">
                            Shift:
                            {{
                                selectedTransactionEntry.shift ?? 'Tanpa shift'
                            }}
                        </span>
                    </dd>
                </div>
                <div class="sm:col-span-2">
                    <dt
                        class="text-[10px] font-medium tracking-wide text-slate-400 uppercase"
                    >
                        Deskripsi
                    </dt>
                    <dd class="mt-1 text-xs font-medium text-slate-700">
                        {{ selectedTransactionEntry.description }}
                    </dd>
                </div>
            </dl>

            <section
                class="mt-4 overflow-hidden rounded-2xl border border-slate-200"
            >
                <div class="border-b border-slate-100 bg-slate-50/70 px-4 py-3">
                    <h3 class="text-sm font-semibold text-slate-900">
                        Kanal pembayaran
                    </h3>
                </div>
                <ul class="divide-y divide-slate-100 px-4">
                    <li
                        v-for="channel in selectedTransactionEntry.channelBreakdown"
                        :key="channel.label"
                        class="flex items-center justify-between gap-4 py-3 text-xs"
                    >
                        <span class="font-medium text-slate-600">
                            {{ channel.label }}
                        </span>
                        <span class="font-semibold text-slate-900 tabular-nums">
                            {{ formatCurrency(channel.amount) }}
                        </span>
                    </li>
                </ul>
            </section>

            <button
                v-if="findRelatedOrder(selectedTransactionEntry)"
                type="button"
                class="mt-4 flex w-full items-center justify-between gap-4 rounded-2xl border border-cyan-200 bg-cyan-50/60 p-4 text-left transition hover:border-cyan-300 hover:bg-cyan-100/70 focus-visible:ring-2 focus-visible:ring-cyan-500 focus-visible:outline-none"
                @click="openOrderRecap(selectedTransactionEntry)"
            >
                <span class="min-w-0">
                    <span
                        class="block text-[10px] font-semibold tracking-wide text-cyan-700 uppercase"
                    >
                        Order terkait · lihat detail lengkap
                    </span>
                    <span class="mt-1 block font-semibold text-slate-950">
                        {{ selectedTransactionEntry.orderNo }} ·
                        {{ selectedTransactionEntry.customer }}
                    </span>
                    <span class="mt-0.5 block text-xs text-slate-600">
                        {{ selectedTransactionEntry.vehicle }} ·
                        {{ selectedTransactionEntry.plate }}
                    </span>
                </span>
                <span class="shrink-0 text-lg text-cyan-700">→</span>
            </button>

            <div
                v-else
                class="mt-4 rounded-2xl border border-dashed border-slate-200 bg-slate-50/60 px-4 py-4 text-center text-xs text-slate-500"
            >
                Transaksi ini dicatat manual dan tidak terkait dengan order.
            </div>
        </template>

        <template #footer>
            <button
                v-if="
                    selectedTransactionEntry &&
                    capabilities.update &&
                    isEditable(selectedTransactionEntry)
                "
                type="button"
                class="flex items-center gap-2 rounded-xl border border-slate-200 bg-white px-4 py-2.5 text-sm font-semibold text-slate-700 transition hover:border-cyan-300 hover:bg-cyan-50 hover:text-cyan-700"
                @click="editSelectedTransaction"
            >
                <Pencil class="h-4 w-4" />
                Ubah transaksi
            </button>
            <button
                type="button"
                class="ml-auto rounded-xl bg-slate-900 px-5 py-2.5 text-sm font-semibold text-white transition hover:bg-slate-800"
                @click="selectedTransactionEntry = null"
            >
                Tutup
            </button>
        </template>
    </ModalDialog>

    <ModalDialog
        :open="selectedOrder !== null"
        title="Detail Order"
        :caption="
            selectedOrder
                ? `${selectedOrder.orderNo} · ${selectedOrder.customer}`
                : undefined
        "
        size="lg"
        :layer="selectedTransactionEntry ? 'nested' : 'default'"
        @close="closeOrderRecap"
    >
        <template v-if="selectedOrder">
            <div class="rounded-2xl border border-cyan-200 bg-cyan-50/50 p-4">
                <p
                    class="text-[11px] font-semibold tracking-wide text-cyan-700 uppercase"
                >
                    Detail order
                </p>
                <h3 class="mt-1 text-base font-semibold text-slate-950">
                    {{ selectedOrder.orderNo }} · {{ selectedOrder.customer }}
                </h3>
                <p class="mt-1 text-xs text-slate-600">
                    {{ selectedOrder.vehicle }} · {{ selectedOrder.plate }} ·
                    {{
                        selectedOrder.source === 'booking'
                            ? 'Booking'
                            : 'Walk-in'
                    }}
                </p>
            </div>

            <dl
                class="mt-4 grid grid-cols-2 gap-x-4 gap-y-3 rounded-2xl bg-white p-4 ring-1 ring-slate-200 sm:grid-cols-4"
            >
                <div>
                    <dt
                        class="text-[10px] font-medium tracking-wide text-slate-400 uppercase"
                    >
                        Tanggal order
                    </dt>
                    <dd class="mt-1 text-xs font-medium text-slate-700">
                        {{ formatDate(selectedOrder.date) }} ·
                        {{ selectedOrder.time }}
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
                        {{ formatCurrency(selectedOrder.total) }}
                    </dd>
                </div>
                <div>
                    <dt
                        class="text-[10px] font-medium tracking-wide text-slate-400 uppercase"
                    >
                        Sudah dibayar
                    </dt>
                    <dd
                        class="mt-1 text-xs font-semibold text-emerald-700 tabular-nums"
                    >
                        {{ formatCurrency(selectedOrder.paidAmount) }}
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
                                    selectedOrder.total -
                                        selectedOrder.paidAmount,
                                    0,
                                ),
                            )
                        }}
                    </dd>
                </div>
                <div class="col-span-2 sm:col-span-4">
                    <dt
                        class="text-[10px] font-medium tracking-wide text-slate-400 uppercase"
                    >
                        Layanan
                    </dt>
                    <dd class="mt-1 text-xs font-medium text-slate-700">
                        {{ selectedOrder.items }}
                    </dd>
                </div>
            </dl>

            <section
                class="mt-4 overflow-hidden rounded-2xl border border-slate-200"
            >
                <div
                    class="flex items-center justify-between gap-3 border-b border-slate-100 bg-slate-50/70 px-4 py-3"
                >
                    <h3 class="text-sm font-semibold text-slate-900">
                        Riwayat transaksi
                    </h3>
                    <span class="text-xs text-slate-500">
                        {{ selectedOrder.transactions.length }} transaksi
                    </span>
                </div>
                <ul
                    v-if="selectedOrder.transactions.length > 0"
                    class="divide-y divide-slate-100"
                >
                    <li
                        v-for="(
                            transaction, transactionIndex
                        ) in selectedOrder.transactions"
                        :key="transaction.id"
                        class="relative flex flex-col gap-2 px-4 py-3 transition sm:flex-row sm:items-center sm:justify-between"
                        :class="
                            transaction.id === highlightedTransactionId
                                ? 'bg-cyan-50 before:absolute before:inset-y-2 before:left-0 before:w-1 before:rounded-r-full before:bg-cyan-400'
                                : 'bg-white'
                        "
                    >
                        <div class="min-w-0">
                            <p
                                class="text-xs font-semibold wrap-anywhere text-slate-900"
                            >
                                {{
                                    orderTransactionReference(
                                        selectedOrder,
                                        transaction,
                                        transactionIndex,
                                    )
                                }}
                            </p>
                            <p class="mt-0.5 text-[11px] text-slate-500">
                                {{ transaction.type }} ·
                                {{ formatDate(transaction.date) }} ·
                                {{ transaction.time }} ·
                                {{ transaction.channels }}
                            </p>
                        </div>
                        <p
                            class="shrink-0 text-sm font-semibold text-emerald-700 tabular-nums"
                        >
                            {{ formatCurrency(transaction.amount) }}
                        </p>
                    </li>
                </ul>
                <p v-else class="px-4 py-6 text-center text-xs text-slate-500">
                    Belum ada pembayaran untuk order ini.
                </p>
            </section>
        </template>

        <template #footer>
            <button
                type="button"
                class="ml-auto rounded-xl bg-slate-900 px-5 py-2.5 text-sm font-semibold text-white transition hover:bg-slate-800"
                @click="closeOrderRecap"
            >
                {{
                    selectedTransactionEntry ? 'Kembali ke transaksi' : 'Tutup'
                }}
            </button>
        </template>
    </ModalDialog>

    <ModalDialog
        :open="editingPosEntry !== null"
        title="Ubah Transaksi"
        :caption="editingPosEntry?.ref"
        @close="closePosTransactionForm"
    >
        <div v-if="editingPosEntry" class="space-y-4">
            <div>
                <label
                    class="text-xs font-medium text-slate-600"
                    for="transaction-amount"
                >
                    Nominal transaksi (Rp)
                </label>
                <MoneyInput
                    id="transaction-amount"
                    v-model="transactionForm.amount"
                    min="1"
                    class="mt-1.5 w-full rounded-xl border border-slate-200 px-3 py-2.5 text-sm tabular-nums focus:border-cyan-400 focus:outline-none"
                />
                <InputError
                    class="mt-1.5"
                    :message="transactionForm.errors.amount"
                />
            </div>

            <section
                class="overflow-hidden rounded-2xl border border-slate-200"
            >
                <div
                    class="flex items-center justify-between gap-3 border-b border-slate-100 bg-slate-50/70 px-4 py-3"
                >
                    <div>
                        <h3 class="text-sm font-semibold text-slate-900">
                            Kanal pembayaran
                        </h3>
                        <p class="mt-0.5 text-[11px] text-slate-500">
                            Total kanal harus sama dengan nominal transaksi.
                        </p>
                    </div>
                    <button
                        v-if="
                            transactionForm.channels.length <
                            paymentMethods.length
                        "
                        type="button"
                        class="flex items-center gap-1 rounded-lg px-2 py-1.5 text-xs font-semibold text-cyan-700 transition hover:bg-cyan-100"
                        @click="addTransactionChannel"
                    >
                        <Plus class="h-3.5 w-3.5" />
                        Tambah
                    </button>
                </div>

                <div class="divide-y divide-slate-100">
                    <div
                        v-for="(
                            channel, channelIndex
                        ) in transactionForm.channels"
                        :key="channelIndex"
                        class="space-y-3 p-4"
                    >
                        <div
                            class="grid gap-3 sm:grid-cols-[minmax(0,1fr)_10rem_auto]"
                        >
                            <div>
                                <label
                                    class="text-[11px] font-medium text-slate-500"
                                    :for="`transaction-channel-${channelIndex}`"
                                >
                                    Metode
                                </label>
                                <select
                                    :id="`transaction-channel-${channelIndex}`"
                                    v-model="channel.label"
                                    class="mt-1 w-full rounded-xl border border-slate-200 px-3 py-2.5 text-sm focus:border-cyan-400 focus:outline-none"
                                >
                                    <option
                                        v-if="
                                            !paymentMethods.includes(
                                                channel.label,
                                            )
                                        "
                                        :value="channel.label"
                                    >
                                        {{ channel.label }}
                                    </option>
                                    <option
                                        v-for="method in paymentMethods"
                                        :key="method"
                                        :value="method"
                                        :disabled="
                                            isTransactionMethodDisabled(
                                                method,
                                                channelIndex,
                                            )
                                        "
                                    >
                                        {{ method }}
                                    </option>
                                </select>
                            </div>
                            <div>
                                <label
                                    class="text-[11px] font-medium text-slate-500"
                                    :for="`transaction-channel-amount-${channelIndex}`"
                                >
                                    Nominal (Rp)
                                </label>
                                <MoneyInput
                                    :id="`transaction-channel-amount-${channelIndex}`"
                                    v-model="channel.amount"
                                    min="1"
                                    class="mt-1 w-full rounded-xl border border-slate-200 px-3 py-2.5 text-sm tabular-nums focus:border-cyan-400 focus:outline-none"
                                />
                            </div>
                            <button
                                type="button"
                                class="mt-6 rounded-lg p-2 text-slate-400 transition hover:bg-rose-50 hover:text-rose-600 disabled:cursor-not-allowed disabled:opacity-30"
                                :disabled="transactionForm.channels.length <= 1"
                                :aria-label="`Hapus kanal ${channel.label}`"
                                @click="removeTransactionChannel(channelIndex)"
                            >
                                <Trash2 class="h-4 w-4" />
                            </button>
                        </div>

                        <div>
                            <label
                                class="text-[11px] font-medium text-slate-500"
                                :for="`transaction-channel-reference-${channelIndex}`"
                            >
                                Referensi kanal (opsional)
                            </label>
                            <input
                                :id="`transaction-channel-reference-${channelIndex}`"
                                v-model="channel.reference"
                                type="text"
                                maxlength="60"
                                placeholder="Nomor referensi EDC / transfer"
                                class="mt-1 w-full rounded-xl border border-slate-200 px-3 py-2.5 text-sm focus:border-cyan-400 focus:outline-none"
                            />
                        </div>
                    </div>
                </div>

                <div
                    class="flex items-center justify-between gap-4 border-t border-slate-100 bg-slate-50/70 px-4 py-3 text-xs"
                >
                    <span class="font-medium text-slate-500">Total kanal</span>
                    <span
                        class="font-semibold tabular-nums"
                        :class="
                            transactionChannelTotal === transactionForm.amount
                                ? 'text-emerald-700'
                                : 'text-rose-600'
                        "
                    >
                        {{ formatCurrency(transactionChannelTotal) }}
                    </span>
                </div>
            </section>
            <InputError
                class="mt-1.5"
                :message="transactionForm.errors.channels"
            />
        </div>

        <template #footer>
            <button
                type="button"
                class="flex-1 rounded-xl border border-slate-200 py-2.5 text-sm font-medium text-slate-600 transition hover:bg-slate-50"
                @click="closePosTransactionForm"
            >
                Batal
            </button>
            <button
                type="button"
                class="flex-1 rounded-xl bg-gradient-to-r from-cyan-500 to-sky-600 py-2.5 text-sm font-semibold text-white transition hover:from-cyan-600 hover:to-sky-700 disabled:cursor-not-allowed disabled:from-slate-300 disabled:to-slate-300"
                :disabled="!canSavePosTransaction || transactionForm.processing"
                @click="savePosTransaction"
            >
                {{ transactionForm.processing ? 'Menyimpan...' : 'Simpan' }}
            </button>
        </template>
    </ModalDialog>

    <!-- Record entry -->
    <ModalDialog
        :open="isFormOpen"
        :title="
            editingEntry
                ? 'Ubah catatan keuangan'
                : activeLedger === 'in'
                  ? 'Catat uang masuk'
                  : 'Catat uang keluar'
        "
        :caption="
            activeLedger === 'in'
                ? 'Pemasukan operasional harian'
                : 'Pengeluaran wajib disertai bukti pendukung'
        "
        @close="closeEntryForm"
    >
        <div class="space-y-4">
            <div>
                <label class="text-xs font-medium text-slate-600" for="fin-cat">
                    Kategori
                </label>
                <select
                    id="fin-cat"
                    v-model="draft.category"
                    class="mt-1.5 w-full rounded-xl border border-slate-200 px-3 py-2.5 text-sm focus:border-cyan-400 focus:outline-none"
                >
                    <option
                        v-for="category in activeCategories"
                        :key="category"
                        :value="category"
                    >
                        {{ category }}
                    </option>
                </select>
                <InputError
                    class="mt-1.5"
                    :message="entryForm.errors.category"
                />
            </div>

            <div>
                <label
                    class="text-xs font-medium text-slate-600"
                    for="fin-desc"
                >
                    Deskripsi
                </label>
                <input
                    id="fin-desc"
                    v-model="draft.description"
                    type="text"
                    placeholder="Keterangan transaksi"
                    class="mt-1.5 w-full rounded-xl border border-slate-200 px-3 py-2.5 text-sm focus:border-cyan-400 focus:outline-none"
                />
                <InputError
                    class="mt-1.5"
                    :message="entryForm.errors.description"
                />
            </div>

            <div class="grid grid-cols-2 gap-3">
                <div>
                    <label
                        class="text-xs font-medium text-slate-600"
                        for="fin-amount"
                    >
                        Nominal (Rp)
                    </label>
                    <MoneyInput
                        id="fin-amount"
                        v-model="draft.amount"
                        min="0"
                        class="mt-1.5 w-full rounded-xl border border-slate-200 px-3 py-2.5 text-sm tabular-nums focus:border-cyan-400 focus:outline-none"
                    />
                    <InputError
                        class="mt-1.5"
                        :message="entryForm.errors.amount"
                    />
                </div>
                <div>
                    <label
                        class="text-xs font-medium text-slate-600"
                        for="fin-method"
                    >
                        Metode
                    </label>
                    <select
                        id="fin-method"
                        v-model="draft.method"
                        class="mt-1.5 w-full rounded-xl border border-slate-200 px-3 py-2.5 text-sm focus:border-cyan-400 focus:outline-none"
                    >
                        <option
                            v-for="method in paymentMethods"
                            :key="method"
                            :value="method"
                        >
                            {{ method }}
                        </option>
                    </select>
                </div>
            </div>

            <div>
                <p class="text-xs font-medium text-slate-600">
                    Bukti pendukung
                    <span v-if="activeLedger === 'out'" class="text-rose-500">
                        *
                    </span>
                </p>
                <div
                    v-if="attachmentCount > 0"
                    class="mt-1.5 grid grid-cols-2 gap-2 sm:grid-cols-3"
                >
                    <div
                        v-for="attachment in visibleStoredAttachments"
                        :key="`stored-${attachment.id}`"
                        class="relative overflow-hidden rounded-xl border border-slate-200 bg-white"
                    >
                        <a
                            :href="attachment.url || undefined"
                            :data-fancybox="
                                attachment.isImage ? LIGHTBOX_GROUP : null
                            "
                            class="flex aspect-[4/3] items-center justify-center bg-slate-50"
                        >
                            <img
                                v-if="attachment.isImage && attachment.url"
                                :src="attachment.url"
                                :alt="attachment.name"
                                class="h-full w-full object-cover"
                            />
                            <Paperclip v-else class="h-7 w-7 text-slate-300" />
                        </a>
                        <div class="p-2.5 pr-9">
                            <p
                                class="truncate text-[11px] font-medium text-slate-700"
                                :title="attachment.name"
                            >
                                {{ attachment.name }}
                            </p>
                            <p class="mt-0.5 text-[10px] text-slate-400">
                                Tersimpan · {{ attachment.size }}
                            </p>
                        </div>
                        <button
                            type="button"
                            class="absolute right-2 bottom-2 rounded-lg bg-rose-50 p-1.5 text-rose-500 transition hover:bg-rose-100 hover:text-rose-700"
                            :aria-label="`Hapus ${attachment.name}`"
                            @click="removeStoredAttachment(attachment.id)"
                        >
                            <Trash2 class="h-3.5 w-3.5" />
                        </button>
                    </div>

                    <div
                        v-for="attachment in pendingAttachments"
                        :key="attachment.id"
                        class="relative overflow-hidden rounded-xl border border-cyan-200 bg-cyan-50/40"
                    >
                        <div
                            class="flex aspect-[4/3] items-center justify-center bg-cyan-50"
                        >
                            <img
                                v-if="attachment.previewUrl"
                                :src="attachment.previewUrl"
                                :alt="attachment.file.name"
                                class="h-full w-full object-cover"
                            />
                            <Paperclip v-else class="h-7 w-7 text-cyan-300" />
                        </div>
                        <div class="p-2.5 pr-9">
                            <p
                                class="truncate text-[11px] font-medium text-slate-700"
                                :title="attachment.file.name"
                            >
                                {{ attachment.file.name }}
                            </p>
                            <p class="mt-0.5 text-[10px] text-cyan-600">
                                Baru ·
                                {{ formatAttachmentSize(attachment.file.size) }}
                            </p>
                        </div>
                        <button
                            type="button"
                            class="absolute right-2 bottom-2 rounded-lg bg-rose-50 p-1.5 text-rose-500 transition hover:bg-rose-100 hover:text-rose-700"
                            :aria-label="`Hapus ${attachment.file.name}`"
                            @click="removePendingAttachment(attachment.id)"
                        >
                            <Trash2 class="h-3.5 w-3.5" />
                        </button>
                    </div>
                </div>
                <label
                    v-if="canAddAttachment"
                    class="mt-1.5 flex cursor-pointer items-center gap-3 rounded-xl border border-dashed p-3 transition"
                    :class="
                        attachmentCount > 0
                            ? 'border-cyan-300 bg-cyan-50/60'
                            : 'border-slate-300 hover:border-cyan-400 hover:bg-slate-50'
                    "
                >
                    <span
                        class="flex h-9 w-9 shrink-0 items-center justify-center rounded-lg bg-white text-slate-400 ring-1 ring-slate-200"
                    >
                        <Plus class="h-4 w-4" />
                    </span>
                    <span class="min-w-0 flex-1">
                        <span
                            class="block truncate text-xs font-medium text-slate-800"
                        >
                            {{
                                attachmentCount > 0
                                    ? 'Tambah lampiran lain'
                                    : 'Pilih nota / struk / invoice'
                            }}
                        </span>
                        <span class="block text-[11px] text-slate-500">
                            JPG, PNG, atau PDF · maks. 10 file, masing-masing 4
                            MB
                        </span>
                    </span>
                    <input
                        type="file"
                        accept="image/*,.pdf"
                        multiple
                        class="hidden"
                        @change="onFileSelected"
                    />
                </label>
                <p
                    v-if="requiresAttachment"
                    class="mt-1.5 flex items-center gap-1 text-[11px] text-rose-600"
                >
                    <TriangleAlert class="h-3.5 w-3.5" />
                    Pengeluaran wajib menyertakan bukti pendukung.
                </p>
                <InputError class="mt-1.5" :message="attachmentError" />
            </div>

            <div class="rounded-2xl bg-slate-50 p-4">
                <p class="text-[11px] text-slate-500">Nominal tercatat</p>
                <p
                    class="text-xl font-semibold tabular-nums"
                    :class="
                        activeLedger === 'in'
                            ? 'text-emerald-600'
                            : 'text-rose-600'
                    "
                >
                    {{ activeLedger === 'in' ? '+' : '−'
                    }}{{ formatCurrency(draft.amount) }}
                </p>
            </div>
        </div>

        <template #footer>
            <button
                type="button"
                class="flex-1 rounded-xl border border-slate-200 py-2.5 text-sm font-medium text-slate-600 transition hover:bg-slate-50"
                @click="closeEntryForm"
            >
                Batal
            </button>
            <button
                type="button"
                class="flex-1 rounded-xl bg-gradient-to-r from-cyan-500 to-sky-600 py-2.5 text-sm font-semibold text-white transition hover:from-cyan-600 hover:to-sky-700 disabled:cursor-not-allowed disabled:from-slate-300 disabled:to-slate-300"
                :disabled="!canSave || entryForm.processing"
                @click="saveEntry"
            >
                {{ entryForm.processing ? 'Menyimpan...' : 'Simpan catatan' }}
            </button>
        </template>
    </ModalDialog>

    <ModalDialog
        :open="deletingEntry !== null"
        title="Hapus catatan keuangan"
        caption="Catatan yang dihapus tidak dapat dikembalikan."
        size="sm"
        @close="deletingEntry = null"
    >
        <p v-if="deletingEntry" class="text-sm text-slate-600">
            Yakin ingin menghapus
            <span class="font-semibold text-slate-900">
                {{ deletingEntry.ref }}
            </span>
            sebesar
            <span class="font-semibold text-slate-900">
                {{ formatCurrency(deletingEntry.amount) }}
            </span>
            dari buku kas?
        </p>
        <template #footer>
            <button
                type="button"
                class="flex-1 rounded-xl border border-slate-200 py-2.5 text-sm font-medium text-slate-600 transition hover:bg-slate-50"
                @click="deletingEntry = null"
            >
                Batal
            </button>
            <button
                type="button"
                class="flex-1 rounded-xl bg-rose-600 py-2.5 text-sm font-semibold text-white transition hover:bg-rose-700 disabled:cursor-not-allowed disabled:opacity-60"
                :disabled="deleteForm.processing"
                @click="confirmDeleteEntry"
            >
                {{ deleteForm.processing ? 'Menghapus...' : 'Hapus catatan' }}
            </button>
        </template>
    </ModalDialog>
</template>
