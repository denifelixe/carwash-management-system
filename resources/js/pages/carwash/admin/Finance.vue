<script setup lang="ts">
import { Head, router } from '@inertiajs/vue3';
import {
    Banknote,
    Paperclip,
    Plus,
    TrendingDown,
    TrendingUp,
    TriangleAlert,
    Wallet,
} from '@lucide/vue';
import { computed, ref } from 'vue';
import DataToolbar from '@/components/carwash/DataToolbar.vue';
import DateFilterBar from '@/components/carwash/DateFilterBar.vue';
import EmptyState from '@/components/carwash/EmptyState.vue';
import ModalDialog from '@/components/carwash/ModalDialog.vue';
import StatCard from '@/components/carwash/StatCard.vue';
import {
    formatCurrency,
    formatDate,
    formatDateCode,
} from '@/composables/useCarwashFormat';
import admin from '@/routes/carwash/admin';
import type {
    CarwashDateFilter,
    CarwashBrand,
    CarwashCashSummary,
    CarwashMoneyEntry,
    CarwashOrder,
    CarwashShift,
    CarwashTransaction,
} from '@/types/carwash';

const props = defineProps<{
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
}>();

type Ledger = 'in' | 'out';
type Shift = 'all' | 'pagi' | 'sore';

const activeLedger = ref<Ledger>('in');
const activeShift = ref<Shift>('all');
const search = ref<string>('');
const categoryFilter = ref<string>('Semua');
const isFormOpen = ref<boolean>(false);
const selectedTransactionEntry = ref<CarwashMoneyEntry | null>(null);
const selectedOrder = ref<CarwashOrder | null>(null);
const highlightedTransactionId = ref<string | null>(null);

const incomeList = ref<CarwashMoneyEntry[]>(
    props.moneyIn.map((entry) => ({ ...entry })),
);
const expenseList = ref<CarwashMoneyEntry[]>(
    props.moneyOut.map((entry) => ({ ...entry })),
);

const draft = ref({
    category: props.incomeCategories[0],
    description: '',
    amount: 0,
    method: 'Tunai',
    attachmentName: '',
});

const activeCategories = computed<string[]>(() =>
    activeLedger.value === 'in'
        ? props.incomeCategories
        : props.expenseCategories,
);

const shiftTabs = computed(() => [
    { id: 'all' as const, label: 'Seluruh Shift', caption: 'Semua transaksi' },
    ...props.shifts.map((shift) => ({
        id: shift.id as Exclude<Shift, 'all'>,
        label: shift.name,
        caption: `${shift.time} · ${shift.cashier}`,
    })),
]);

function isInActiveShift(entry: CarwashMoneyEntry): boolean {
    if (activeShift.value === 'all') {
        return true;
    }

    const isMorning = entry.time < '15.00';

    return activeShift.value === 'pagi' ? isMorning : !isMorning;
}

const scopedIncome = computed<CarwashMoneyEntry[]>(() =>
    incomeList.value.filter(isInActiveShift),
);

const scopedExpenses = computed<CarwashMoneyEntry[]>(() =>
    expenseList.value.filter(isInActiveShift),
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
 * button stays disabled until an attachment is named.
 */
const requiresAttachment = computed<boolean>(
    () => activeLedger.value === 'out',
);

const canSave = computed<boolean>(() => {
    if (draft.value.description.trim() === '' || draft.value.amount <= 0) {
        return false;
    }

    return !requiresAttachment.value || draft.value.attachmentName !== '';
});

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
    draft.value = {
        category: activeCategories.value[0],
        description: '',
        amount: 0,
        method: 'Tunai',
        attachmentName: '',
    };
    isFormOpen.value = true;
}

function findRelatedOrder(entry: CarwashMoneyEntry): CarwashOrder | null {
    if (entry.orderId == null) {
        return null;
    }

    return props.orders.find((order) => order.id === entry.orderId) ?? null;
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

/** Stands in for a real upload — records the chosen file's name and size. */
function onFileSelected(event: Event): void {
    const file = (event.target as HTMLInputElement).files?.[0];

    if (file) {
        draft.value.attachmentName = file.name;
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
        recordedBy: 'Sesi demo',
        attachment: isIncome
            ? null
            : { name: draft.value.attachmentName, size: '—' },
    };

    if (isIncome) {
        incomeList.value = [entry, ...incomeList.value];
    } else {
        expenseList.value = [entry, ...expenseList.value];
    }

    isFormOpen.value = false;
}

/** Filtering is a fresh visit, so the page rebuilds from the narrowed props. */
function applyDate(date: string): void {
    router.get(
        admin.finance.url(),
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
                    <span class="mt-0.5 block text-[11px] opacity-75">
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
                            </td>
                            <td
                                v-if="activeLedger === 'out'"
                                class="px-5 py-3.5"
                            >
                                <span
                                    v-if="entry.attachment"
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
            activeLedger === 'in' ? 'Catat uang masuk' : 'Catat uang keluar'
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
            <div v-if="requiresAttachment">
                <p class="text-xs font-medium text-slate-600">
                    Bukti pendukung
                    <span class="text-rose-500">*</span>
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
                    v-if="!draft.attachmentName"
                    class="mt-1.5 flex items-center gap-1 text-[11px] text-rose-600"
                >
                    <TriangleAlert class="h-3.5 w-3.5" />
                    Pengeluaran wajib menyertakan bukti pendukung.
                </p>
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
                :disabled="!canSave"
                @click="saveEntry"
            >
                Simpan catatan
            </button>
        </template>
    </ModalDialog>
</template>
