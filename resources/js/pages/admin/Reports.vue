<script setup lang="ts">
import { Head, router } from '@inertiajs/vue3';
import {
    Boxes,
    ChevronRight,
    Download,
    ListOrdered,
    Users,
    Wallet,
} from '@lucide/vue';
import { computed, ref } from 'vue';
import {
    exportFinance,
    exportOrders,
    index as indexReports,
} from '@/actions/App/Http/Controllers/Admin/ReportController';
import DataPagination from '@/components/demo/DataPagination.vue';
import DateRangeFilter from '@/components/demo/DateRangeFilter.vue';
import EmptyState from '@/components/demo/EmptyState.vue';
import ModalDialog from '@/components/demo/ModalDialog.vue';
import SectionCard from '@/components/demo/SectionCard.vue';
import StatusPill from '@/components/demo/StatusPill.vue';
import {
    formatCurrency,
    formatNumber,
    formatPercent,
    formatShortCurrency,
} from '@/composables/useCarwashFormat';
import { formatPlate } from '@/lib/vehiclePlate';
import admin from '@/routes/demo/admin';
import type {
    CarwashBookingSummary,
    CarwashBrand,
    CarwashCustomerBase,
    CarwashInventorySummary,
    CarwashMoneyEntry,
    CarwashPaginated,
    CarwashReportFilters,
    CarwashReportOrder,
    CarwashReportShift,
    CarwashTopService,
    CarwashTrendPoint,
} from '@/types/demo';

const props = defineProps<{
    mode: 'demo' | 'live';
    brand: CarwashBrand;
    trend: CarwashTrendPoint[];
    filters: CarwashReportFilters;
    topServices: CarwashTopService[];
    customerBase: CarwashCustomerBase;
    bookingSummary: CarwashBookingSummary;
    inventorySummary: CarwashInventorySummary;
    shifts: CarwashReportShift[];
    /** Only present once the contribution card has been opened. */
    orderLog?: CarwashPaginated<CarwashReportOrder>;
    financeSummary: {
        moneyIn: number;
        moneyOut: number;
        net: number;
        transactions: number;
    };
    financeLog?: CarwashPaginated<
        CarwashMoneyEntry & { direction: 'in' | 'out' }
    >;
    capabilities: { read: boolean };
}>();

/** Most axis labels to print before they start colliding. */
const MAX_AXIS_LABELS = 12;

const hoveredBar = ref<number | null>(null);
const isLoading = ref<boolean>(false);

/** The range lives in the URL, so a filtered report stays shareable. */
function applyRange(range: { from: string; to: string }): void {
    isFinanceLogOpen.value = false;
    router.get(
        props.mode === 'demo' ? admin.reports.url() : indexReports.url(),
        range,
        {
            preserveState: true,
            preserveScroll: true,
            replace: true,
            /* The inventory card is left out on purpose: it does not move when
             * only the range changes. */
            only: [
                'trend',
                'filters',
                'topServices',
                'customerBase',
                'bookingSummary',
                'shifts',
                'financeSummary',
            ],
            onStart: () => {
                isLoading.value = true;
            },
            onFinish: () => {
                isLoading.value = false;
            },
        },
    );
}

/** Largest value in a list, or 0 when empty — `Math.max()` alone returns -Infinity. */
function peakOf(values: number[]): number {
    return values.length === 0 ? 0 : Math.max(...values);
}

/** `value` as a percentage of `total`, clamped to 0…100 and safe when `total` is 0. */
function shareOf(value: number, total: number): number {
    if (total <= 0) {
        return 0;
    }

    return Math.min(100, Math.max(0, (value / total) * 100));
}

/** Print every nth label so a 60-day range keeps a readable axis. */
const axisStride = computed<number>(() =>
    Math.ceil(props.trend.length / MAX_AXIS_LABELS),
);

/** Both series share one scale so the bars stay comparable. */
const trendPeak = computed<number>(() =>
    peakOf(props.trend.flatMap((point) => [point.revenue, point.expense])),
);

/** Sorted so the bar lengths run top to bottom, matching "layanan teratas". */
const rankedServices = computed<CarwashTopService[]>(() =>
    [...props.topServices].sort((a, b) => b.revenue - a.revenue),
);

const topServiceRevenue = computed<number>(() =>
    peakOf(props.topServices.map((service) => service.revenue)),
);

const totalServiceRevenue = computed<number>(() =>
    props.topServices.reduce((total, service) => total + service.revenue, 0),
);

/** Which service the open order log is narrowed to; null is every order. */
const openedService = ref<string | null>(null);
const isOrderLogOpen = ref<boolean>(false);
const isOrderLogLoading = ref<boolean>(false);

/**
 * The log is an optional prop, so opening the card is what fetches it. The
 * range already lives in the URL — only the service and the page are added.
 */
function loadOrderLog(page: number): void {
    router.reload({
        only: ['orderLog'],
        data: { service: openedService.value ?? undefined, orderPage: page },
        onStart: () => {
            isOrderLogLoading.value = true;
        },
        onFinish: () => {
            isOrderLogLoading.value = false;
        },
    });
}

function openOrderLog(serviceName: string | null): void {
    openedService.value = serviceName;
    isOrderLogOpen.value = true;
    loadOrderLog(1);
}

function closeOrderLog(): void {
    isOrderLogOpen.value = false;
}

const orderLogRows = computed<CarwashReportOrder[]>(
    () => props.orderLog?.data ?? [],
);

const orderLogTitle = computed<string>(() =>
    openedService.value === null
        ? 'Order pada periode ini'
        : `Order — ${openedService.value}`,
);

/**
 * A plain link rather than a visit: the CSV is streamed straight back as a
 * download, so Inertia must not try to read it as a page. The range and the
 * service travel in the URL so the file matches what is on screen — every row
 * of it, not just the page being read.
 */
const orderLogDownloadUrl = computed<string>(() => {
    const query = {
        from: props.filters.from,
        to: props.filters.to,
        ...(openedService.value === null
            ? {}
            : { service: openedService.value }),
    };

    return props.mode === 'demo'
        ? admin.reports.orders.export.url({ query })
        : exportOrders.url({ query });
});

const isFinanceLogOpen = ref(false);
const isFinanceLogLoading = ref(false);
const financeLogError = ref(false);
const financeDirection = ref<'all' | 'in' | 'out'>('all');
const financeDirections = [
    { value: 'all', label: 'Semua transaksi' },
    { value: 'in', label: 'Pemasukan' },
    { value: 'out', label: 'Pengeluaran' },
] as const;

function loadFinanceLog(page: number): void {
    financeLogError.value = false;
    isFinanceLogLoading.value = true;
    router.reload({
        only: ['financeLog'],
        data: {
            from: props.filters.from,
            to: props.filters.to,
            direction: financeDirection.value,
            financePage: page,
        },
        onSuccess: () => {
            financeLogError.value = false;
        },
        onError: () => {
            financeLogError.value = true;
        },
        onFinish: () => {
            isFinanceLogLoading.value = false;
        },
    });
}

function openFinanceLog(direction: 'all' | 'in' | 'out' = 'all'): void {
    financeDirection.value = direction;
    isFinanceLogOpen.value = true;
    loadFinanceLog(1);
}

const financeLogDownloadUrl = computed(() => {
    const query = {
        from: props.filters.from,
        to: props.filters.to,
        direction: financeDirection.value,
    };

    return props.mode === 'demo'
        ? admin.reports.finance.export.url({ query })
        : exportFinance.url({ query });
});
</script>

<template>
    <Head :title="`${brand.name} — Laporan`" />

    <div class="space-y-4">
        <!-- Range filter -->
        <section class="flex flex-wrap items-center justify-between gap-3">
            <div>
                <h2 class="text-base font-semibold text-slate-900">
                    Laporan & monitoring
                </h2>
                <p class="mt-0.5 text-xs text-slate-500">
                    {{ filters.label }} · {{ formatNumber(filters.days) }} hari
                </p>
            </div>
            <DateRangeFilter
                :from="filters.from"
                :to="filters.to"
                :today="filters.today"
                :earliest="filters.earliest"
                @change="applyRange"
            />
        </section>

        <SectionCard
            title="Laporan Keuangan"
            :caption="`Arus kas tercatat · ${filters.label} · ${formatNumber(financeSummary.transactions)} transaksi`"
        >
            <template #actions>
                <button
                    type="button"
                    class="inline-flex items-center gap-1.5 rounded-lg border border-slate-200 px-2.5 py-1.5 text-[11px] font-medium text-slate-600 transition hover:bg-slate-50"
                    @click="openFinanceLog()"
                >
                    <ListOrdered class="h-3.5 w-3.5" />
                    Semua transaksi
                </button>
            </template>
            <div class="mt-4 grid gap-3 sm:grid-cols-3">
                <button
                    type="button"
                    class="rounded-xl bg-emerald-50 p-4 text-left transition hover:bg-emerald-100"
                    @click="openFinanceLog('in')"
                >
                    <p class="text-xs text-emerald-700">Total pemasukan</p>
                    <p
                        class="mt-1 text-lg font-semibold text-emerald-800 tabular-nums"
                    >
                        {{ formatCurrency(financeSummary.moneyIn) }}
                    </p>
                    <p class="mt-1 text-[11px] text-emerald-700">
                        Pembayaran POS dan pemasukan manual
                    </p>
                </button>
                <button
                    type="button"
                    class="rounded-xl bg-rose-50 p-4 text-left transition hover:bg-rose-100"
                    @click="openFinanceLog('out')"
                >
                    <p class="text-xs text-rose-700">Total pengeluaran</p>
                    <p
                        class="mt-1 text-lg font-semibold text-rose-800 tabular-nums"
                    >
                        {{ formatCurrency(financeSummary.moneyOut) }}
                    </p>
                    <p class="mt-1 text-[11px] text-rose-700">
                        Lihat rincian pengeluaran
                    </p>
                </button>
                <div class="rounded-xl bg-slate-50 p-4">
                    <p class="text-xs text-slate-600">Selisih periode</p>
                    <p
                        class="mt-1 text-lg font-semibold text-slate-900 tabular-nums"
                    >
                        {{ formatCurrency(financeSummary.net) }}
                    </p>
                    <p class="mt-1 text-[11px] text-slate-500">
                        Pemasukan dikurangi pengeluaran
                    </p>
                </div>
            </div>
            <div
                class="mt-6 flex flex-wrap items-center justify-between gap-3 border-t border-slate-100 pt-5"
            >
                <div>
                    <h3 class="text-sm font-medium text-slate-900">
                        Pendapatan vs pengeluaran
                    </h3>
                    <p class="mt-0.5 text-xs text-slate-500">
                        Perbandingan {{ filters.granularity }}
                    </p>
                </div>
                <div class="flex items-center gap-4 text-[11px] text-slate-500">
                    <span class="flex items-center gap-1.5">
                        <span
                            class="h-2.5 w-2.5 rounded-sm bg-gradient-to-t from-emerald-600 to-emerald-400"
                        ></span>
                        Pendapatan
                    </span>
                    <span class="flex items-center gap-1.5">
                        <span
                            class="h-2.5 w-2.5 rounded-sm bg-gradient-to-t from-rose-500 to-rose-300"
                        ></span>
                        Pengeluaran
                    </span>
                </div>
            </div>

            <div
                class="mt-6 flex gap-3 transition-opacity duration-200"
                :class="isLoading ? 'opacity-40' : 'opacity-100'"
            >
                <!-- Value scale, so bar heights can actually be read -->
                <div
                    class="flex h-56 w-16 shrink-0 flex-col justify-between pb-7 text-right text-[10px] whitespace-nowrap text-slate-400 tabular-nums"
                >
                    <span>{{ formatShortCurrency(trendPeak) }}</span>
                    <span>{{ formatShortCurrency(trendPeak / 2) }}</span>
                    <span>0</span>
                </div>

                <div class="relative min-w-0 flex-1">
                    <!-- Gridlines -->
                    <div
                        class="pointer-events-none absolute inset-x-0 top-0 flex h-56 flex-col justify-between pb-7"
                    >
                        <div class="border-t border-slate-100"></div>
                        <div class="border-t border-slate-100"></div>
                        <div class="border-t border-slate-200"></div>
                    </div>

                    <div
                        class="relative flex h-56 items-end"
                        :class="
                            trend.length > 14 ? 'gap-0.5' : 'gap-2 sm:gap-4'
                        "
                    >
                        <div
                            v-for="(point, index) in trend"
                            :key="point.label"
                            class="group relative flex h-full min-w-0 flex-1 flex-col justify-end"
                            @mouseenter="hoveredBar = index"
                            @mouseleave="hoveredBar = null"
                        >
                            <div
                                v-if="hoveredBar === index"
                                class="absolute -top-1 left-1/2 z-10 w-44 -translate-x-1/2 -translate-y-full rounded-xl bg-slate-900 px-3 py-2 text-left shadow-xl"
                            >
                                <p class="text-[11px] text-slate-400">
                                    {{ point.caption }}
                                </p>
                                <p
                                    class="mt-0.5 flex items-baseline justify-between gap-2 text-[11px] text-emerald-300"
                                >
                                    <span>Masuk</span>
                                    <span
                                        class="font-semibold text-white tabular-nums"
                                    >
                                        {{ formatCurrency(point.revenue) }}
                                    </span>
                                </p>
                                <p
                                    class="flex items-baseline justify-between gap-2 text-[11px] text-rose-300"
                                >
                                    <span>Keluar</span>
                                    <span
                                        class="font-semibold text-white tabular-nums"
                                    >
                                        {{ formatCurrency(point.expense) }}
                                    </span>
                                </p>
                                <p
                                    class="mt-1 border-t border-white/10 pt-1 text-[11px] text-slate-400"
                                >
                                    {{ formatNumber(point.transactions) }}
                                    transaksi
                                </p>
                            </div>

                            <div
                                class="flex h-full items-end justify-center gap-1 pb-7"
                            >
                                <div
                                    class="w-1/2 rounded-t bg-gradient-to-t from-emerald-600 to-emerald-400 transition-all duration-300"
                                    :style="{
                                        height: `${Math.max(2, shareOf(point.revenue, trendPeak))}%`,
                                    }"
                                ></div>
                                <div
                                    class="w-1/2 rounded-t bg-gradient-to-t from-rose-500 to-rose-300 transition-all duration-300"
                                    :style="{
                                        height: `${Math.max(2, shareOf(point.expense, trendPeak))}%`,
                                    }"
                                ></div>
                            </div>

                            <p
                                v-if="
                                    index % axisStride === 0 ||
                                    hoveredBar === index
                                "
                                class="absolute inset-x-0 bottom-0 truncate text-center text-[11px] font-medium transition"
                                :class="
                                    hoveredBar === index
                                        ? 'text-slate-900'
                                        : 'text-slate-500'
                                "
                            >
                                {{ point.label }}
                            </p>
                        </div>
                    </div>
                </div>
            </div>
        </SectionCard>

        <section class="grid grid-cols-1 gap-4 xl:grid-cols-2">
            <!-- Service revenue -->
            <SectionCard
                title="Kontribusi Layanan (Order)"
                :caption="`Nilai layanan terjual ${formatShortCurrency(totalServiceRevenue)} dari 5 layanan teratas`"
            >
                <template #actions>
                    <button
                        type="button"
                        class="inline-flex items-center gap-1.5 rounded-lg border border-slate-200 px-2.5 py-1.5 text-[11px] font-medium text-slate-600 transition hover:bg-slate-50"
                        @click="openOrderLog(null)"
                    >
                        <ListOrdered class="h-3.5 w-3.5" />
                        Semua order
                    </button>
                </template>

                <ul class="mt-4 space-y-1">
                    <li v-for="service in rankedServices" :key="service.name">
                        <button
                            type="button"
                            class="group w-full cursor-pointer rounded-xl px-2 py-2 text-left transition hover:bg-slate-50"
                            :title="`Lihat order ${service.name}`"
                            @click="openOrderLog(service.name)"
                        >
                            <div
                                class="flex items-baseline justify-between gap-3"
                            >
                                <p
                                    class="flex min-w-0 items-center gap-1.5 truncate text-sm text-slate-700"
                                >
                                    <span class="truncate">
                                        {{ service.name }}
                                    </span>
                                    <ChevronRight
                                        class="h-3.5 w-3.5 shrink-0 text-slate-300 transition group-hover:translate-x-0.5 group-hover:text-slate-500"
                                    />
                                </p>
                                <p
                                    class="shrink-0 text-xs font-medium text-slate-700 tabular-nums"
                                >
                                    {{ formatShortCurrency(service.revenue) }}
                                </p>
                            </div>
                            <div class="mt-1.5 flex items-center gap-3">
                                <div
                                    class="h-1.5 min-w-0 flex-1 overflow-hidden rounded-full bg-slate-100"
                                >
                                    <div
                                        class="h-full rounded-full bg-gradient-to-r from-cyan-500 to-sky-500 transition-all duration-300"
                                        :style="{
                                            width: `${shareOf(service.revenue, topServiceRevenue)}%`,
                                        }"
                                    ></div>
                                </div>
                                <p
                                    class="w-24 shrink-0 text-right text-[11px] text-slate-400 tabular-nums"
                                >
                                    {{
                                        formatPercent(
                                            shareOf(
                                                service.revenue,
                                                totalServiceRevenue,
                                            ),
                                        )
                                    }}
                                    · {{ formatNumber(service.orders) }}×
                                </p>
                            </div>
                        </button>
                    </li>
                </ul>
            </SectionCard>

            <!-- Customer activity -->
            <SectionCard
                title="Leads & Member"
                caption="Pertumbuhan basis customer"
            >
                <div class="mt-4 grid grid-cols-2 gap-3">
                    <div class="rounded-xl bg-slate-50 p-3">
                        <p class="text-[11px] text-slate-500">Member baru</p>
                        <p
                            class="mt-0.5 text-xl font-semibold text-slate-900 tabular-nums"
                        >
                            {{ formatNumber(customerBase.newMembers) }}
                        </p>
                    </div>
                    <div class="rounded-xl bg-slate-50 p-3">
                        <p class="text-[11px] text-slate-500">Member kembali</p>
                        <p
                            class="mt-0.5 text-xl font-semibold text-slate-900 tabular-nums"
                        >
                            {{ formatNumber(customerBase.returningMembers) }}
                        </p>
                    </div>
                    <div class="rounded-xl bg-cyan-50 p-3">
                        <p class="text-[11px] text-cyan-700">Leads baru</p>
                        <p
                            class="mt-0.5 text-xl font-semibold text-cyan-700 tabular-nums"
                        >
                            {{ formatNumber(customerBase.newLeads) }}
                        </p>
                    </div>
                    <div class="rounded-xl bg-emerald-50 p-3">
                        <p class="text-[11px] text-emerald-700">
                            Leads jadi member
                        </p>
                        <p
                            class="mt-0.5 text-xl font-semibold text-emerald-700 tabular-nums"
                        >
                            {{ formatNumber(customerBase.convertedLeads) }}
                        </p>
                    </div>
                </div>

                <ul class="mt-4 space-y-2.5 text-sm">
                    <li class="flex items-center justify-between gap-3">
                        <span class="text-slate-500">Member dilayani</span>
                        <span class="font-medium text-slate-700 tabular-nums">
                            {{ formatNumber(customerBase.membersServed) }}
                        </span>
                    </li>
                    <li class="flex items-center justify-between gap-3">
                        <span class="text-slate-500">
                            Rata-rata kunjungan per member
                        </span>
                        <span class="font-medium text-slate-700 tabular-nums">
                            {{ customerBase.averageVisitsPerMember }}×
                        </span>
                    </li>
                </ul>

                <p
                    class="mt-4 flex items-start gap-2 rounded-xl bg-slate-50 px-3 py-2.5 text-[11px] text-slate-600"
                >
                    <Users class="mt-0.5 h-3.5 w-3.5 shrink-0" />
                    <span>
                        {{ formatNumber(customerBase.openLeads) }} leads belum
                        jadi member •
                        {{ formatNumber(customerBase.churnRisk) }} member
                        berisiko tidak kembali
                    </span>
                </p>
            </SectionCard>
        </section>

        <section class="grid grid-cols-1 gap-4 xl:grid-cols-2">
            <!-- Booking summary -->
            <SectionCard title="Ringkasan booking" :caption="filters.label">
                <ul class="mt-4 space-y-2.5 text-sm">
                    <li class="flex items-center justify-between gap-3">
                        <span class="text-slate-500">Total booking</span>
                        <span class="font-semibold text-slate-900 tabular-nums">
                            {{ formatNumber(bookingSummary.total) }}
                        </span>
                    </li>
                    <li class="flex items-center justify-between gap-3">
                        <span class="text-slate-500">Terjadwal</span>
                        <span class="font-medium text-cyan-600 tabular-nums">
                            {{ formatNumber(bookingSummary.scheduled) }}
                        </span>
                    </li>
                    <li class="flex items-center justify-between gap-3">
                        <span class="text-slate-500">Selesai</span>
                        <span class="font-medium text-emerald-600 tabular-nums">
                            {{ formatNumber(bookingSummary.completed) }}
                        </span>
                    </li>
                    <li class="flex items-center justify-between gap-3">
                        <span class="text-slate-500">Batal</span>
                        <span class="font-medium text-rose-600 tabular-nums">
                            {{ formatNumber(bookingSummary.cancelled) }}
                        </span>
                    </li>
                    <li
                        class="flex items-center justify-between gap-3 border-t border-slate-100 pt-2.5"
                    >
                        <span class="font-medium text-slate-600">
                            Tingkat kehadiran
                        </span>
                        <span class="font-semibold text-slate-900 tabular-nums">
                            {{ formatPercent(bookingSummary.showRate) }}
                        </span>
                    </li>
                </ul>
            </SectionCard>

            <!-- Inventory summary -->
            <SectionCard title="Ringkasan inventory" caption="Stok operasional">
                <ul class="mt-4 space-y-2.5 text-sm">
                    <li class="flex items-center justify-between gap-3">
                        <span class="text-slate-500">Total item</span>
                        <span class="font-semibold text-slate-900 tabular-nums">
                            {{ formatNumber(inventorySummary.totalItems) }}
                        </span>
                    </li>
                    <li class="flex items-center justify-between gap-3">
                        <span class="text-slate-500">Stok menipis</span>
                        <span
                            class="font-medium tabular-nums"
                            :class="
                                inventorySummary.lowStock > 0
                                    ? 'text-rose-600'
                                    : 'text-slate-700'
                            "
                        >
                            {{ formatNumber(inventorySummary.lowStock) }}
                        </span>
                    </li>
                    <li class="flex items-center justify-between gap-3">
                        <span class="text-slate-500"
                            >Pergerakan minggu ini</span
                        >
                        <span class="font-medium text-slate-700 tabular-nums">
                            {{
                                formatNumber(inventorySummary.movementsThisWeek)
                            }}
                        </span>
                    </li>
                    <li
                        class="flex items-center justify-between gap-3 border-t border-slate-100 pt-2.5"
                    >
                        <span class="font-medium text-slate-600">
                            Nilai persediaan
                        </span>
                        <span class="font-semibold text-slate-900 tabular-nums">
                            {{
                                formatShortCurrency(inventorySummary.stockValue)
                            }}
                        </span>
                    </li>
                </ul>
                <p
                    class="mt-3 flex items-center gap-2 rounded-xl bg-slate-50 px-3 py-2 text-[11px] text-slate-600"
                >
                    <Boxes class="h-3.5 w-3.5 shrink-0" />
                    <span class="truncate">
                        Paling banyak terpakai:
                        {{ inventorySummary.topConsumed }}
                    </span>
                </p>
            </SectionCard>
        </section>

        <!-- Shift report -->
        <SectionCard
            title="Laporan per shift"
            :caption="`Rekap kasir dan arus kas tiap shift — ${filters.label}`"
            :padded="false"
        >
            <div class="overflow-x-auto">
                <table class="w-full min-w-[720px] text-sm">
                    <thead>
                        <tr
                            class="border-b border-slate-100 text-left text-[11px] font-medium tracking-wider text-slate-400 uppercase"
                        >
                            <th class="px-5 py-3">Shift</th>
                            <th class="px-5 py-3">Kasir</th>
                            <th class="px-5 py-3 text-right">Kendaraan</th>
                            <th class="px-5 py-3 text-right">Transaksi</th>
                            <th class="px-5 py-3 text-right">Uang masuk</th>
                            <th class="px-5 py-3 text-right">Uang keluar</th>
                            <th class="px-5 py-3 text-right">Net</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-50">
                        <tr
                            v-for="shift in shifts"
                            :key="shift.id"
                            class="transition hover:bg-slate-50/70"
                        >
                            <td class="px-5 py-3.5">
                                <p class="font-medium text-slate-900">
                                    {{ shift.name }}
                                </p>
                                <p class="text-[11px] text-slate-500">
                                    {{ shift.time ?? 'Di luar jadwal aktif' }}
                                </p>
                            </td>
                            <td class="px-5 py-3.5">
                                <div class="flex items-center gap-2">
                                    <span
                                        v-if="shift.initials"
                                        class="flex h-7 w-7 shrink-0 items-center justify-center rounded-full bg-slate-100 text-[10px] font-semibold text-slate-600"
                                    >
                                        {{ shift.initials }}
                                    </span>
                                    <span class="text-slate-700">
                                        {{ shift.cashier || '—' }}
                                    </span>
                                </div>
                            </td>
                            <td
                                class="px-5 py-3.5 text-right text-slate-700 tabular-nums"
                            >
                                {{ formatNumber(shift.vehiclesServed) }}
                            </td>
                            <td
                                class="px-5 py-3.5 text-right text-slate-700 tabular-nums"
                            >
                                {{ formatNumber(shift.transactions) }}
                            </td>
                            <td
                                class="px-5 py-3.5 text-right font-medium text-emerald-600 tabular-nums"
                            >
                                {{ formatCurrency(shift.moneyIn) }}
                            </td>
                            <td
                                class="px-5 py-3.5 text-right font-medium text-rose-600 tabular-nums"
                            >
                                {{ formatCurrency(shift.moneyOut) }}
                            </td>
                            <td
                                class="px-5 py-3.5 text-right font-semibold text-slate-900 tabular-nums"
                            >
                                {{
                                    formatCurrency(
                                        shift.moneyIn - shift.moneyOut,
                                    )
                                }}
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </SectionCard>

        <ModalDialog
            :open="isFinanceLogOpen"
            title="Daftar transaksi keuangan"
            :caption="filters.label"
            size="2xl"
            dismissible
            @close="isFinanceLogOpen = false"
        >
            <div class="mb-4 flex flex-wrap gap-2">
                <button
                    v-for="option in financeDirections"
                    :key="option.value"
                    type="button"
                    :disabled="isFinanceLogLoading"
                    :aria-pressed="financeDirection === option.value"
                    class="rounded-lg px-3 py-2 text-xs font-medium transition disabled:opacity-50"
                    :class="
                        financeDirection === option.value
                            ? 'bg-slate-900 text-white'
                            : 'bg-slate-100 text-slate-600 hover:bg-slate-200'
                    "
                    @click="openFinanceLog(option.value)"
                >
                    {{ option.label }}
                </button>
            </div>
            <div
                v-if="isFinanceLogLoading"
                class="space-y-3 py-6"
                role="status"
                aria-label="Memuat transaksi keuangan"
            >
                <div
                    v-for="line in 4"
                    :key="line"
                    class="h-8 animate-pulse rounded bg-slate-100"
                ></div>
            </div>
            <div
                v-else-if="financeLogError"
                class="py-6 text-center text-sm text-slate-600"
            >
                <p>Transaksi keuangan belum berhasil dimuat.</p>
                <button
                    type="button"
                    class="mt-2 underline"
                    @click="loadFinanceLog(1)"
                >
                    Coba lagi
                </button>
            </div>
            <EmptyState
                v-else-if="!financeLog?.data.length"
                :icon="Wallet"
                title="Belum ada transaksi keuangan"
                caption="Tidak ada transaksi sesuai jenis dan rentang tanggal ini."
            />
            <div v-else class="-mx-5 -mb-5">
                <div class="max-h-[60vh] overflow-auto">
                    <table class="w-full min-w-[980px] text-sm">
                        <thead
                            class="sticky top-0 bg-white text-left text-[11px] tracking-wider text-slate-400 uppercase"
                        >
                            <tr class="border-b border-slate-100">
                                <th class="px-5 py-3">Waktu / Referensi</th>
                                <th class="px-5 py-3">Kategori / Keterangan</th>
                                <th class="px-5 py-3">Metode</th>
                                <th class="px-5 py-3">Shift / Petugas</th>
                                <th class="px-5 py-3 text-right">Pemasukan</th>
                                <th class="px-5 py-3 text-right">
                                    Pengeluaran
                                </th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-slate-50">
                            <tr
                                v-for="entry in financeLog.data"
                                :key="`${entry.source}-${entry.id}`"
                                class="hover:bg-slate-50"
                            >
                                <td class="px-5 py-3 whitespace-nowrap">
                                    <p class="font-medium text-slate-900">
                                        {{ entry.date }} · {{ entry.time }}
                                    </p>
                                    <p class="text-[11px] text-slate-500">
                                        {{ entry.ref }}
                                    </p>
                                    <p class="text-[11px] text-slate-500">
                                        {{
                                            entry.source === 'pos'
                                                ? 'POS'
                                                : 'Manual'
                                        }}<span v-if="entry.orderNo">
                                            · {{ entry.orderNo }}</span
                                        >
                                    </p>
                                </td>
                                <td class="max-w-64 px-5 py-3">
                                    <p class="text-slate-700">
                                        {{ entry.category }}
                                    </p>
                                    <p class="text-xs text-slate-500">
                                        {{ entry.description || '—' }}
                                    </p>
                                </td>
                                <td class="px-5 py-3 text-slate-600">
                                    {{ entry.method }}
                                </td>
                                <td class="px-5 py-3 text-slate-600">
                                    <p>{{ entry.shift ?? 'Tanpa Shift' }}</p>
                                    <p class="text-[11px] text-slate-500">
                                        {{ entry.recordedBy }}
                                    </p>
                                </td>
                                <td
                                    class="px-5 py-3 text-right font-medium whitespace-nowrap text-emerald-700 tabular-nums"
                                >
                                    {{
                                        entry.direction === 'in'
                                            ? formatCurrency(entry.amount)
                                            : '—'
                                    }}
                                </td>
                                <td
                                    class="px-5 py-3 text-right font-medium whitespace-nowrap text-rose-700 tabular-nums"
                                >
                                    {{
                                        entry.direction === 'out'
                                            ? formatCurrency(entry.amount)
                                            : '—'
                                    }}
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>
                <DataPagination
                    :meta="financeLog.meta"
                    label="transaksi"
                    @change="loadFinanceLog"
                />
            </div>
            <template #footer>
                <div
                    class="flex w-full flex-wrap items-center justify-between gap-3"
                >
                    <p class="text-[11px] text-slate-500">
                        Unduhan berisi seluruh transaksi sesuai jenis dan
                        rentang tanggal terpilih.
                    </p>
                    <a
                        :href="financeLogDownloadUrl"
                        class="inline-flex shrink-0 items-center gap-1.5 rounded-lg bg-slate-900 px-3 py-2 text-xs font-medium text-white transition hover:bg-slate-700"
                        download
                    >
                        <Download class="h-3.5 w-3.5" />
                        Unduh Excel (CSV)
                    </a>
                </div>
            </template>
        </ModalDialog>

        <!-- The orders behind the contribution card -->
        <ModalDialog
            :open="isOrderLogOpen"
            :title="orderLogTitle"
            :caption="`${filters.label} · ${formatNumber(orderLog?.meta.total ?? 0)} order`"
            size="2xl"
            dismissible
            @close="closeOrderLog"
        >
            <div
                class="-mx-5 -mb-5 transition-opacity duration-200"
                :class="isOrderLogLoading ? 'opacity-40' : 'opacity-100'"
            >
                <EmptyState
                    v-if="!isOrderLogLoading && orderLogRows.length === 0"
                    :icon="ListOrdered"
                    title="Belum ada order"
                    caption="Tidak ada order yang dibayar pada rentang tanggal ini."
                />

                <div v-else class="max-h-[60vh] overflow-auto">
                    <table class="w-full min-w-[780px] text-sm">
                        <thead
                            class="sticky top-0 z-10 bg-white text-left text-[11px] font-medium tracking-wider text-slate-400 uppercase"
                        >
                            <tr class="border-b border-slate-100">
                                <th class="px-5 py-3">Waktu</th>
                                <th class="px-5 py-3">Kendaraan</th>
                                <th class="px-5 py-3">Customer</th>
                                <th class="px-5 py-3">Layanan</th>
                                <th class="px-5 py-3">Status</th>
                                <th class="px-5 py-3 text-right">Total</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-slate-50">
                            <tr
                                v-for="order in orderLogRows"
                                :key="order.id"
                                class="transition hover:bg-slate-50/70"
                            >
                                <td class="px-5 py-3 whitespace-nowrap">
                                    <p
                                        class="font-medium text-slate-900 tabular-nums"
                                    >
                                        {{ order.date }}
                                    </p>
                                    <p
                                        class="text-[11px] text-slate-500 tabular-nums"
                                    >
                                        {{ order.time }} · {{ order.orderNo }}
                                    </p>
                                </td>
                                <td class="px-5 py-3">
                                    <p class="text-slate-700">
                                        {{ order.vehicle }}
                                    </p>
                                    <p
                                        class="text-[11px] font-medium tracking-wide text-slate-500 uppercase"
                                    >
                                        {{ formatPlate(order.plate) }}
                                    </p>
                                </td>
                                <td class="px-5 py-3">
                                    <p class="text-slate-700">
                                        {{ order.customer }}
                                    </p>
                                    <p
                                        class="text-[11px] text-slate-500 tabular-nums"
                                    >
                                        {{ order.phone }}
                                    </p>
                                </td>
                                <td
                                    class="max-w-[220px] px-5 py-3 text-slate-600"
                                >
                                    {{ order.services || '—' }}
                                </td>
                                <td class="px-5 py-3">
                                    <StatusPill :status="order.status" />
                                </td>
                                <td
                                    class="px-5 py-3 text-right font-semibold whitespace-nowrap text-slate-900 tabular-nums"
                                >
                                    {{ formatCurrency(order.total) }}
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>

                <DataPagination
                    v-if="orderLog"
                    :meta="orderLog.meta"
                    label="order"
                    @change="loadOrderLog"
                />
            </div>

            <template #footer>
                <div
                    class="flex w-full flex-wrap items-center justify-between gap-3"
                >
                    <p class="text-[11px] text-slate-500">
                        Unduhan berisi seluruh order pada rentang ini, bukan
                        hanya halaman yang tampil.
                    </p>
                    <a
                        :href="orderLogDownloadUrl"
                        class="inline-flex shrink-0 items-center gap-1.5 rounded-lg bg-slate-900 px-3 py-2 text-xs font-medium text-white transition hover:bg-slate-700"
                        download
                    >
                        <Download class="h-3.5 w-3.5" />
                        Unduh Excel (CSV)
                    </a>
                </div>
            </template>
        </ModalDialog>
    </div>
</template>
