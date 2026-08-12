<script setup lang="ts">
import { Head } from '@inertiajs/vue3';
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
import EmptyState from '@/components/carwash/EmptyState.vue';
import ModalDialog from '@/components/carwash/ModalDialog.vue';
import StatCard from '@/components/carwash/StatCard.vue';
import {
    formatCurrency,
    formatShortCurrency,
} from '@/composables/useCarwashFormat';
import type {
    CarwashBrand,
    CarwashCashSummary,
    CarwashMoneyEntry,
    CarwashShift,
} from '@/types/carwash';

const props = defineProps<{
    brand: CarwashBrand;
    moneyIn: CarwashMoneyEntry[];
    moneyOut: CarwashMoneyEntry[];
    incomeCategories: string[];
    expenseCategories: string[];
    cashSummary: CarwashCashSummary;
    paymentMethods: string[];
    shifts: CarwashShift[];
}>();

type Ledger = 'in' | 'out';

const activeLedger = ref<Ledger>('in');
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

const filterOptions = computed<string[]>(() => [
    'Semua',
    ...activeCategories.value,
]);

const activeEntries = computed<CarwashMoneyEntry[]>(() =>
    activeLedger.value === 'in' ? incomeList.value : expenseList.value,
);

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
            entry.recordedBy.toLowerCase().includes(query);

        return matchesCategory && matchesQuery;
    });
});

const totalIn = computed<number>(() =>
    incomeList.value.reduce((total, entry) => total + entry.amount, 0),
);

const totalOut = computed<number>(() =>
    expenseList.value.reduce((total, entry) => total + entry.amount, 0),
);

const netCash = computed<number>(() => totalIn.value - totalOut.value);

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

function saveEntry(): void {
    if (!canSave.value) {
        return;
    }

    const isIncome = activeLedger.value === 'in';
    const sequence =
        (isIncome ? incomeList.value.length : expenseList.value.length) + 32;

    const entry: CarwashMoneyEntry = {
        id: sequence,
        ref: `${isIncome ? 'IN' : 'OUT'}-2608-${String(sequence).padStart(4, '0')}`,
        date: '3 Agu 2026',
        time: 'Baru saja',
        category: draft.value.category,
        description: draft.value.description,
        amount: draft.value.amount,
        method: draft.value.method,
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
</script>

<template>
    <Head :title="`${brand.name} — Uang Masuk & Keluar`" />

    <div class="space-y-4">
        <section class="grid grid-cols-1 gap-4 sm:grid-cols-2 xl:grid-cols-4">
            <StatCard
                label="Uang masuk"
                :value="formatCurrency(totalIn)"
                :caption="`${incomeList.length} transaksi tercatat`"
                :icon="TrendingUp"
                tone="emerald"
            />
            <StatCard
                label="Uang keluar"
                :value="formatCurrency(totalOut)"
                :caption="`${expenseList.length} pengeluaran`"
                :icon="TrendingDown"
                tone="rose"
            />
            <StatCard
                label="Arus kas bersih"
                :value="formatCurrency(netCash)"
                caption="masuk dikurangi keluar"
                :icon="Banknote"
            />
            <StatCard
                label="Saldo kas"
                :value="formatShortCurrency(cashSummary.closingBalance)"
                :caption="`Awal ${formatShortCurrency(cashSummary.openingBalance)}`"
                :icon="Wallet"
                tone="amber"
            />
        </section>

        <!-- Shift breakdown -->
        <section class="grid grid-cols-1 gap-4 sm:grid-cols-2">
            <article
                v-for="shift in shifts"
                :key="shift.id"
                class="rounded-2xl border border-slate-200/80 bg-white p-5 shadow-sm"
            >
                <div class="flex items-center justify-between">
                    <div>
                        <p class="text-sm font-semibold text-slate-900">
                            {{ shift.name }}
                        </p>
                        <p class="text-xs text-slate-500">
                            {{ shift.time }} • {{ shift.cashier }}
                        </p>
                    </div>
                    <span
                        class="rounded-full px-2.5 py-1 text-[11px] font-medium capitalize"
                        :class="
                            shift.status === 'berjalan'
                                ? 'bg-cyan-50 text-cyan-700'
                                : 'bg-slate-100 text-slate-600'
                        "
                    >
                        {{ shift.status }}
                    </span>
                </div>
                <div class="mt-4 grid grid-cols-3 gap-3">
                    <div>
                        <p class="text-[11px] text-slate-500">Masuk</p>
                        <p
                            class="mt-0.5 text-sm font-semibold text-emerald-600 tabular-nums"
                        >
                            {{ formatShortCurrency(shift.moneyIn) }}
                        </p>
                    </div>
                    <div>
                        <p class="text-[11px] text-slate-500">Keluar</p>
                        <p
                            class="mt-0.5 text-sm font-semibold text-rose-600 tabular-nums"
                        >
                            {{ formatShortCurrency(shift.moneyOut) }}
                        </p>
                    </div>
                    <div>
                        <p class="text-[11px] text-slate-500">Transaksi</p>
                        <p
                            class="mt-0.5 text-sm font-semibold text-slate-900 tabular-nums"
                        >
                            {{ shift.transactions }}
                        </p>
                    </div>
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
                        placeholder="Cari deskripsi / ref"
                        :filters="filterOptions"
                        :active-filter="categoryFilter"
                        @filter="categoryFilter = $event"
                    />
                </div>
            </div>

            <div v-if="filteredEntries.length > 0" class="overflow-x-auto">
                <table class="w-full min-w-[880px] text-sm">
                    <thead>
                        <tr
                            class="border-b border-slate-100 text-left text-[11px] font-medium tracking-wider text-slate-400 uppercase"
                        >
                            <th class="px-5 py-3">Referensi</th>
                            <th class="px-5 py-3">Kategori</th>
                            <th class="px-5 py-3">Deskripsi</th>
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
                                    {{ entry.date }} • {{ entry.time }}
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
                            <td class="px-5 py-3.5 text-slate-600">
                                {{ entry.method }}
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
