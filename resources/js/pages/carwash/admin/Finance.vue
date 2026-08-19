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
    CarwashShift,
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
}>();

type Ledger = 'in' | 'out';
type Shift = 'all' | 'pagi' | 'sore';

const activeLedger = ref<Ledger>('in');
const activeShift = ref<Shift>('all');
const search = ref<string>('');
const categoryFilter = ref<string>('Semua');
const isFormOpen = ref<boolean>(false);

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
                                <p class="font-medium text-slate-900">
                                    {{ entry.ref }}
                                </p>
                                <p class="text-[11px] text-slate-500">
                                    {{ formatDate(entry.date) }} •
                                    {{ entry.time }}
                                </p>
                            </td>
                            <td class="px-5 py-3.5">
                                <span
                                    class="rounded-lg bg-slate-100 px-2 py-1 text-[11px] font-medium text-slate-600"
                                >
                                    {{ entry.category }}
                                </span>
                            </td>
                            <td
                                class="max-w-[240px] px-5 py-3.5 text-slate-600"
                            >
                                {{ entry.description }}
                            </td>
                            <td class="px-5 py-3.5">
                                <template v-if="entry.orderNo">
                                    <p class="font-medium text-slate-900">
                                        {{ entry.orderNo }}
                                    </p>
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
