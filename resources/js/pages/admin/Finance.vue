<script setup lang="ts">
import { Fancybox } from '@fancyapps/ui';
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
} from '@/actions/App/Http/Controllers/Admin/FinanceController';
import DataToolbar from '@/components/demo/DataToolbar.vue';
import DateFilterBar from '@/components/demo/DateFilterBar.vue';
import EmptyState from '@/components/demo/EmptyState.vue';
import ModalDialog from '@/components/demo/ModalDialog.vue';
import StatCard from '@/components/demo/StatCard.vue';
import InputError from '@/components/InputError.vue';
import {
    formatCurrency,
    formatDate,
    formatDateCode,
} from '@/composables/useCarwashFormat';
import { useCarwashWorkflow } from '@/composables/useCarwashWorkflow';
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
    capabilities: {
        create: boolean;
        update: boolean;
        delete: boolean;
    };
}>();

type Ledger = 'in' | 'out';
/** 'all', or the id of one of the shifts this console was given. */
type Shift = string;

const activeLedger = ref<Ledger>('in');
const activeShift = ref<Shift>('all');
const search = ref<string>('');
const categoryFilter = ref<string>('Semua');
const isFormOpen = ref<boolean>(false);
const editingEntry = ref<CarwashMoneyEntry | null>(null);
const deletingEntry = ref<CarwashMoneyEntry | null>(null);
const selectedTransactionEntry = ref<CarwashMoneyEntry | null>(null);
const selectedOrder = ref<CarwashOrder | null>(null);
const highlightedTransactionId = ref<string | null>(null);

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

const draft = ref({
    category: props.incomeCategories[0],
    description: '',
    amount: 0,
    method: 'Tunai',
    attachmentName: '',
});

/** The live ledger posts the entry, including a real supporting document. */
const entryForm = useForm<{
    direction: Ledger;
    category: string;
    description: string;
    amount: number;
    method: string;
    attachment: File | null;
}>({
    direction: 'in',
    category: props.incomeCategories[0],
    description: '',
    amount: 0,
    method: 'Tunai',
    attachment: null,
});

const deleteForm = useForm({});

const activeCategories = computed<string[]>(() =>
    activeLedger.value === 'in'
        ? props.incomeCategories
        : props.expenseCategories,
);

const shiftTabs = computed(() => [
    { id: 'all', label: 'Seluruh Shift', caption: 'Semua transaksi' },
    ...props.shifts.map((shift) => ({
        id: shift.id,
        label: shift.name,
        caption: shift.time
            ? shift.cashier
                ? `${shift.time} · ${shift.cashier}`
                : shift.time
            : null,
    })),
]);

function isInActiveShift(entry: CarwashMoneyEntry): boolean {
    if (activeShift.value === 'all') {
        return true;
    }

    if (entry.shift) {
        /* An entry records the shift by name; a tab is keyed by its id, which
         * only spells out the name on the demo console. */
        const shiftName = props.shifts
            .find((shift) => shift.id === activeShift.value)
            ?.name.toLocaleLowerCase('id-ID');
        const entryShift = entry.shift.toLocaleLowerCase('id-ID');

        return (
            entryShift === shiftName || entryShift.includes(activeShift.value)
        );
    }

    const inferredShift = entry.time < '15.00' ? 'pagi' : 'sore';

    return activeShift.value === inferredShift;
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
            categoryFilter.value === 'Semua' ||
            entry.category === categoryFilter.value;
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
 * Outgoing money must carry supporting documentation (BR-10), so the save
 * button stays disabled until an attachment is named. An entry being edited
 * keeps the document already on file unless a new one is chosen.
 */
const requiresAttachment = computed<boolean>(
    () => activeLedger.value === 'out' && !editingEntry.value?.attachment,
);

const canSave = computed<boolean>(() => {
    if (draft.value.description.trim() === '' || draft.value.amount <= 0) {
        return false;
    }

    return !requiresAttachment.value || draft.value.attachmentName !== '';
});

/** Payments booked by the cashier are read-only here: they belong to the till. */
function isEditable(entry: CarwashMoneyEntry): boolean {
    return props.mode === 'live' && cashEntryId(entry) !== null;
}

/**
 * The row id of a hand-written entry. A payment read back from the till is
 * keyed by its transaction reference instead, and has no ledger row to address.
 */
function cashEntryId(entry: CarwashMoneyEntry): number | null {
    return typeof entry.id === 'number' ? entry.id : null;
}

function switchLedger(ledger: Ledger): void {
    activeLedger.value = ledger;
    categoryFilter.value = 'Semua';
    search.value = '';
}

function switchShift(shift: Shift): void {
    activeShift.value = shift;
    categoryFilter.value = 'Semua';
    search.value = '';
}

function openForm(): void {
    editingEntry.value = null;
    draft.value = {
        category: activeCategories.value[0],
        description: '',
        amount: 0,
        method: 'Tunai',
        attachmentName: '',
    };
    entryForm.clearErrors();
    entryForm.attachment = null;
    isFormOpen.value = true;
}

function openEditForm(entry: CarwashMoneyEntry): void {
    if (!isEditable(entry) || !props.capabilities.update) {
        return;
    }

    editingEntry.value = entry;
    draft.value = {
        category: entry.category,
        description: entry.description,
        amount: entry.amount,
        method: entry.method,
        attachmentName: '',
    };
    entryForm.clearErrors();
    entryForm.attachment = null;
    isFormOpen.value = true;
}

function openDeleteEntry(entry: CarwashMoneyEntry): void {
    if (!isEditable(entry) || !props.capabilities.delete) {
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

/** The demo only records the chosen file's name; the live ledger uploads it. */
function onFileSelected(event: Event): void {
    const file = (event.target as HTMLInputElement).files?.[0];

    if (file) {
        draft.value.attachmentName = file.name;
        entryForm.attachment = file;
    }
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

    if (props.mode === 'live') {
        saveLiveEntry();

        return;
    }

    saveDemoEntry();
}

/** The live ledger writes to the database and re-reads the reloaded props. */
function saveLiveEntry(): void {
    entryForm.direction = activeLedger.value;
    entryForm.category = draft.value.category;
    entryForm.description = draft.value.description;
    entryForm.amount = draft.value.amount;
    entryForm.method = draft.value.method;

    const editingId =
        editingEntry.value === null ? null : cashEntryId(editingEntry.value);
    const action =
        editingId === null ? storeCashEntry() : updateCashEntry(editingId);

    entryForm.submit(action, {
        preserveScroll: true,
        onSuccess: () => {
            isFormOpen.value = false;
            editingEntry.value = null;
            entryForm.reset();
        },
    });
}

/** The demo console keeps its entries in memory instead of hitting the database. */
function saveDemoEntry(): void {
    const isIncome = activeLedger.value === 'in';
    const sequence =
        (isIncome ? incomeList.value.length : expenseList.value.length) + 32;

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
        shift: props.persona.shift,
        attachment: isIncome
            ? null
            : { name: draft.value.attachmentName, size: '—' },
    };

    if (isIncome) {
        workflow.addMoneyIn(entry);
    } else {
        workflow.addMoneyOut(entry);
    }

    isFormOpen.value = false;
}

/*
 * Image attachments open in place rather than downloading. Fancybox binds by
 * delegation, so rows that appear later — a new filter, a fresh visit — are
 * picked up without rebinding. Its hash integration stays disabled so closing
 * an attachment cannot navigate Inertia's history or lose the ledger position.
 */
const LIGHTBOX_GROUP = 'lampiran-keuangan';

onMounted(() => {
    Fancybox.bind(`[data-fancybox="${LIGHTBOX_GROUP}"]`, {
        Hash: false,
    });
});

onUnmounted(() => {
    Fancybox.destroy();
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

    <div class="space-y-4">
        <DateFilterBar :filters="filters" @change="applyDate" />

        <section
            class="rounded-2xl border border-slate-200/80 bg-white p-2 shadow-sm"
        >
            <div
                class="grid grid-cols-1 gap-1 sm:grid-cols-3"
                role="tablist"
                aria-label="Filter shift"
            >
                <button
                    v-for="shift in shiftTabs"
                    :key="shift.id"
                    type="button"
                    role="tab"
                    class="rounded-xl px-4 py-3 text-left transition focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-cyan-600"
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
                <div class="border-b border-slate-100 px-5 py-4">
                    <h2 class="font-semibold text-slate-900">Kanal Keuangan</h2>
                    <p class="mt-0.5 text-xs text-slate-500">
                        Ringkasan pemasukan, pengeluaran, dan saldo per kanal
                    </p>
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
                        :active-filter="categoryFilter"
                        wide-search
                        @filter="categoryFilter = $event"
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
                            <th v-if="activeLedger === 'out'" class="px-5 py-3">
                                Lampiran
                            </th>
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
                            <td
                                v-if="activeLedger === 'out'"
                                class="px-5 py-3.5"
                            >
                                <a
                                    v-if="
                                        entry.attachment && entry.attachmentUrl
                                    "
                                    :href="entry.attachmentUrl"
                                    :data-fancybox="
                                        entry.attachmentIsImage
                                            ? LIGHTBOX_GROUP
                                            : null
                                    "
                                    :data-caption="
                                        entry.attachmentIsImage
                                            ? `${entry.ref} — ${entry.description}`
                                            : null
                                    "
                                    class="flex items-center gap-1.5 text-[11px] text-cyan-700 underline decoration-cyan-300 underline-offset-2 transition hover:text-cyan-900"
                                >
                                    <component
                                        :is="
                                            entry.attachmentIsImage
                                                ? ImageIcon
                                                : Paperclip
                                        "
                                        class="h-3.5 w-3.5 shrink-0"
                                    />
                                    <span class="max-w-[10rem] truncate">
                                        {{ entry.attachment.name }}
                                    </span>
                                </a>
                                <span
                                    v-else-if="entry.attachment"
                                    class="flex items-center gap-1.5 text-[11px] text-cyan-700"
                                >
                                    <Paperclip class="h-3.5 w-3.5 shrink-0" />
                                    <span class="max-w-[10rem] truncate">
                                        {{ entry.attachment.name }}
                                    </span>
                                </span>
                                <span
                                    v-else
                                    class="flex items-center gap-1 text-[11px] text-rose-500"
                                >
                                    <TriangleAlert class="h-3.5 w-3.5" />
                                    Belum ada
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
                                        v-if="capabilities.delete"
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
        title="Rekap Transaksi"
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
                        {{ selectedTransactionEntry.recordedBy }}
                        <span v-if="selectedTransactionEntry.shift">
                            · {{ selectedTransactionEntry.shift }}
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
                        Order terkait · lihat rekap lengkap
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
        title="Rekap Order"
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
        @close="isFormOpen = false"
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
                    <input
                        id="fin-amount"
                        v-model.number="draft.amount"
                        type="number"
                        min="0"
                        step="1000"
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

            <!-- Attachment, required for expenses -->
            <div v-if="activeLedger === 'out'">
                <p class="text-xs font-medium text-slate-600">
                    Bukti pendukung
                    <span v-if="requiresAttachment" class="text-rose-500">
                        *
                    </span>
                </p>
                <label
                    class="mt-1.5 flex cursor-pointer items-center gap-3 rounded-xl border border-dashed p-3 transition"
                    :class="
                        draft.attachmentName
                            ? 'border-cyan-300 bg-cyan-50/60'
                            : 'border-slate-300 hover:border-cyan-400 hover:bg-slate-50'
                    "
                >
                    <span
                        class="flex h-9 w-9 shrink-0 items-center justify-center rounded-lg bg-white text-slate-400 ring-1 ring-slate-200"
                    >
                        <Paperclip class="h-4 w-4" />
                    </span>
                    <span class="min-w-0 flex-1">
                        <span
                            class="block truncate text-xs font-medium text-slate-800"
                        >
                            {{
                                draft.attachmentName ||
                                editingEntry?.attachment?.name ||
                                'Pilih nota / struk / invoice'
                            }}
                        </span>
                        <span class="block text-[11px] text-slate-500">
                            JPG, PNG, atau PDF
                        </span>
                    </span>
                    <input
                        type="file"
                        accept="image/*,.pdf"
                        class="hidden"
                        @change="onFileSelected"
                    />
                </label>
                <p
                    v-if="requiresAttachment && !draft.attachmentName"
                    class="mt-1.5 flex items-center gap-1 text-[11px] text-rose-600"
                >
                    <TriangleAlert class="h-3.5 w-3.5" />
                    Pengeluaran wajib menyertakan bukti pendukung.
                </p>
                <InputError
                    class="mt-1.5"
                    :message="entryForm.errors.attachment"
                />
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
                @click="isFormOpen = false"
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
