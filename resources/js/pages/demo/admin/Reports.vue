<script setup lang="ts">
import { Head, router } from '@inertiajs/vue3';
import {
    Boxes,
    CalendarClock,
    ChartColumn,
    Sparkles,
    TrendingDown,
    TrendingUp,
    Users,
    Wallet,
} from '@lucide/vue';
import { computed, ref } from 'vue';
import DateRangeFilter from '@/components/demo/DateRangeFilter.vue';
import SectionCard from '@/components/demo/SectionCard.vue';
import StatCard from '@/components/demo/StatCard.vue';
import StatusPill from '@/components/demo/StatusPill.vue';
import {
    formatCurrency,
    formatNumber,
    formatPercent,
    formatShortCurrency,
} from '@/composables/useCarwashFormat';
import admin from '@/routes/demo/admin';
import type {
    CarwashBookingSummary,
    CarwashBrand,
    CarwashCashSummary,
    CarwashCustomerActivity,
    CarwashInventorySummary,
    CarwashReportFilters,
    CarwashShift,
    CarwashStat,
    CarwashTopService,
    CarwashTrendPoint,
} from '@/types/demo';

const props = defineProps<{
    brand: CarwashBrand;
    stats: CarwashStat[];
    trend: CarwashTrendPoint[];
    filters: CarwashReportFilters;
    topServices: CarwashTopService[];
    customerActivity: CarwashCustomerActivity;
    bookingSummary: CarwashBookingSummary;
    inventorySummary: CarwashInventorySummary;
    cashSummary: CarwashCashSummary;
    shifts: CarwashShift[];
}>();

/** Gross margin the business measures itself against, in percent. */
const MARGIN_TARGET = 50;

/** Most axis labels to print before they start colliding. */
const MAX_AXIS_LABELS = 12;

const hoveredBar = ref<number | null>(null);
const isLoading = ref<boolean>(false);

/** The range lives in the URL, so a filtered report stays shareable. */
function applyRange(range: { from: string; to: string }): void {
    router.get(admin.reports.url(), range, {
        preserveState: true,
        preserveScroll: true,
        replace: true,
        only: [
            'trend',
            'filters',
            'topServices',
            'customerActivity',
            'bookingSummary',
        ],
        onStart: () => {
            isLoading.value = true;
        },
        onFinish: () => {
            isLoading.value = false;
        },
    });
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

const periodRevenue = computed<number>(() =>
    props.trend.reduce((total, point) => total + point.revenue, 0),
);

const periodExpense = computed<number>(() =>
    props.trend.reduce((total, point) => total + point.expense, 0),
);

const periodTransactions = computed<number>(() =>
    props.trend.reduce((total, point) => total + point.transactions, 0),
);

const grossMargin = computed<number>(() =>
    periodRevenue.value === 0
        ? 0
        : ((periodRevenue.value - periodExpense.value) / periodRevenue.value) *
          100,
);

const averageTicket = computed<number>(() =>
    periodTransactions.value === 0
        ? 0
        : Math.round(periodRevenue.value / periodTransactions.value),
);

const isMarginOnTarget = computed<boolean>(
    () => grossMargin.value >= MARGIN_TARGET,
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

const stampRedemptionRate = computed<number>(() =>
    shareOf(
        props.customerActivity.stampsRedeemed,
        props.customerActivity.stampsIssued,
    ),
);
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

        <!-- Headline numbers, all on the selected period -->
        <section class="grid grid-cols-1 gap-4 sm:grid-cols-2 xl:grid-cols-4">
            <StatCard
                label="Pendapatan"
                :value="formatShortCurrency(periodRevenue)"
                :caption="`${formatNumber(periodTransactions)} transaksi`"
                :icon="Wallet"
                tone="emerald"
            />
            <StatCard
                label="Pengeluaran"
                :value="formatShortCurrency(periodExpense)"
                caption="biaya operasional"
                :icon="TrendingDown"
                tone="rose"
            />
            <StatCard
                label="Margin kotor"
                :value="formatPercent(grossMargin)"
                :caption="`target internal ${formatPercent(MARGIN_TARGET)}`"
                :icon="isMarginOnTarget ? TrendingUp : TrendingDown"
            />
            <StatCard
                label="Rata-rata transaksi"
                :value="formatCurrency(averageTicket)"
                caption="per kendaraan"
                :icon="ChartColumn"
                tone="amber"
            />
        </section>

        <!-- Revenue vs expense -->
        <SectionCard
            title="Pendapatan vs pengeluaran"
            :caption="`Perbandingan ${filters.granularity} · ${filters.label}`"
        >
            <template #actions>
                <div class="flex items-center gap-4 text-[11px] text-slate-500">
                    <span class="flex items-center gap-1.5">
                        <span
                            class="h-2.5 w-2.5 rounded-sm bg-gradient-to-t from-cyan-600 to-cyan-400"
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
            </template>

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
                                    class="mt-0.5 flex items-baseline justify-between gap-2 text-[11px] text-cyan-300"
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
                                    class="w-1/2 rounded-t bg-gradient-to-t from-cyan-600 to-cyan-400 transition-all duration-300"
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
                title="Kontribusi layanan"
                :caption="`Total ${formatShortCurrency(totalServiceRevenue)} dari 5 layanan teratas`"
            >
                <ul class="mt-4 space-y-3.5">
                    <li v-for="service in rankedServices" :key="service.name">
                        <div class="flex items-baseline justify-between gap-3">
                            <p class="truncate text-sm text-slate-700">
                                {{ service.name }}
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
                    </li>
                </ul>
            </SectionCard>

            <!-- Customer activity -->
            <SectionCard
                title="Aktivitas customer & loyalty"
                caption="Pertumbuhan dan penggunaan stempel"
            >
                <div class="mt-4 grid grid-cols-2 gap-3">
                    <div class="rounded-xl bg-slate-50 p-3">
                        <p class="text-[11px] text-slate-500">Customer baru</p>
                        <p
                            class="mt-0.5 text-xl font-semibold text-slate-900 tabular-nums"
                        >
                            {{ formatNumber(customerActivity.newCustomers) }}
                        </p>
                    </div>
                    <div class="rounded-xl bg-slate-50 p-3">
                        <p class="text-[11px] text-slate-500">
                            Customer kembali
                        </p>
                        <p
                            class="mt-0.5 text-xl font-semibold text-slate-900 tabular-nums"
                        >
                            {{
                                formatNumber(
                                    customerActivity.returningCustomers,
                                )
                            }}
                        </p>
                    </div>
                    <div class="rounded-xl bg-emerald-50 p-3">
                        <p class="text-[11px] text-emerald-700">
                            Stempel diberikan
                        </p>
                        <p
                            class="mt-0.5 text-xl font-semibold text-emerald-700 tabular-nums"
                        >
                            {{ formatNumber(customerActivity.stampsIssued) }}
                        </p>
                    </div>
                    <div class="rounded-xl bg-cyan-50 p-3">
                        <p class="text-[11px] text-cyan-700">Stempel ditukar</p>
                        <p
                            class="mt-0.5 text-xl font-semibold text-cyan-700 tabular-nums"
                        >
                            {{ formatNumber(customerActivity.stampsRedeemed) }}
                        </p>
                    </div>
                </div>

                <div class="mt-4">
                    <div
                        class="flex items-center justify-between text-[11px] text-slate-500"
                    >
                        <span>Tingkat penukaran stempel</span>
                        <span class="font-medium text-slate-700 tabular-nums">
                            {{ formatPercent(stampRedemptionRate) }}
                        </span>
                    </div>
                    <div
                        class="mt-1.5 h-2 overflow-hidden rounded-full bg-slate-100"
                    >
                        <div
                            class="h-full rounded-full bg-gradient-to-r from-cyan-500 to-sky-500 transition-all duration-300"
                            :style="{ width: `${stampRedemptionRate}%` }"
                        ></div>
                    </div>
                </div>

                <div
                    class="mt-4 flex items-start gap-2 rounded-xl bg-amber-50 px-3 py-2.5 text-[11px] text-amber-800"
                >
                    <Sparkles class="mt-0.5 h-3.5 w-3.5 shrink-0" />
                    <span>
                        {{ formatNumber(customerActivity.rewardsClaimed) }}
                        reward diklaim •
                        {{ formatNumber(customerActivity.churnRisk) }} customer
                        berisiko tidak kembali
                    </span>
                </div>
            </SectionCard>
        </section>

        <section class="grid grid-cols-1 gap-4 md:grid-cols-2 xl:grid-cols-3">
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
                <p
                    class="mt-3 flex items-center gap-2 rounded-xl bg-slate-50 px-3 py-2 text-[11px] text-slate-600"
                >
                    <CalendarClock class="h-3.5 w-3.5 shrink-0" />
                    Jam tersibuk: {{ bookingSummary.peakSlot }}
                </p>
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

            <!-- Cash position -->
            <SectionCard title="Posisi kas" caption="Hari ini">
                <ul class="mt-4 space-y-2.5 text-sm">
                    <li class="flex items-center justify-between gap-3">
                        <span class="text-slate-500">Saldo awal</span>
                        <span class="text-slate-800 tabular-nums">
                            {{
                                formatShortCurrency(cashSummary.openingBalance)
                            }}
                        </span>
                    </li>
                    <li class="flex items-center justify-between gap-3">
                        <span class="text-slate-500">Uang masuk</span>
                        <span class="font-medium text-emerald-600 tabular-nums">
                            +{{ formatShortCurrency(cashSummary.todayIn) }}
                        </span>
                    </li>
                    <li class="flex items-center justify-between gap-3">
                        <span class="text-slate-500">Uang keluar</span>
                        <span class="font-medium text-rose-600 tabular-nums">
                            −{{ formatShortCurrency(cashSummary.todayOut) }}
                        </span>
                    </li>
                    <li
                        class="flex items-center justify-between gap-3 border-t border-slate-100 pt-2.5"
                    >
                        <span class="font-medium text-slate-600">
                            Saldo akhir
                        </span>
                        <span class="font-semibold text-slate-900 tabular-nums">
                            {{
                                formatShortCurrency(cashSummary.closingBalance)
                            }}
                        </span>
                    </li>
                </ul>
                <p
                    class="mt-3 flex items-center gap-2 rounded-xl bg-amber-50 px-3 py-2 text-[11px] text-amber-800"
                >
                    <Wallet class="h-3.5 w-3.5 shrink-0" />
                    {{ formatCurrency(cashSummary.pendingPayments) }} belum
                    tertagih
                </p>
            </SectionCard>
        </section>

        <!-- Shift report -->
        <SectionCard
            title="Laporan per shift"
            caption="Rekap kasir dan arus kas tiap shift"
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
                            <th class="px-5 py-3">Status</th>
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
                                    {{ shift.time }}
                                </p>
                            </td>
                            <td class="px-5 py-3.5">
                                <div class="flex items-center gap-2">
                                    <span
                                        class="flex h-7 w-7 shrink-0 items-center justify-center rounded-full bg-slate-100 text-[10px] font-semibold text-slate-600"
                                    >
                                        {{ shift.initials }}
                                    </span>
                                    <span class="text-slate-700">
                                        {{ shift.cashier }}
                                    </span>
                                </div>
                            </td>
                            <td class="px-5 py-3.5">
                                <StatusPill :status="shift.status" />
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

        <!-- Customer retention note -->
        <SectionCard
            title="Catatan monitoring"
            :caption="`Ringkasan otomatis — ${filters.label}`"
        >
            <ul class="mt-3 space-y-2 text-xs text-slate-600">
                <li class="flex items-start gap-2">
                    <Users class="mt-0.5 h-3.5 w-3.5 shrink-0 text-cyan-600" />
                    <span>
                        Rata-rata
                        {{ customerActivity.averageVisitsPerCustomer }}
                        kunjungan per customer — naikkan dengan promo double
                        stempel akhir pekan.
                    </span>
                </li>
                <li class="flex items-start gap-2">
                    <component
                        :is="isMarginOnTarget ? TrendingUp : TrendingDown"
                        class="mt-0.5 h-3.5 w-3.5 shrink-0"
                        :class="
                            isMarginOnTarget
                                ? 'text-emerald-600'
                                : 'text-rose-600'
                        "
                    />
                    <span>
                        Margin kotor {{ formatPercent(grossMargin) }},
                        {{ isMarginOnTarget ? 'di atas' : 'di bawah' }} target
                        internal {{ formatPercent(MARGIN_TARGET) }}.
                    </span>
                </li>
                <li
                    v-if="inventorySummary.lowStock > 0"
                    class="flex items-start gap-2"
                >
                    <Boxes class="mt-0.5 h-3.5 w-3.5 shrink-0 text-rose-600" />
                    <span>
                        {{ formatNumber(inventorySummary.lowStock) }} item stok
                        menipis berpotensi menghambat layanan detailing.
                    </span>
                </li>
            </ul>
        </SectionCard>
    </div>
</template>
