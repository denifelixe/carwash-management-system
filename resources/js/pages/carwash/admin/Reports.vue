<script setup lang="ts">
import { Head } from '@inertiajs/vue3';
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
import SectionCard from '@/components/carwash/SectionCard.vue';
import StatCard from '@/components/carwash/StatCard.vue';
import StatusPill from '@/components/carwash/StatusPill.vue';
import {
    formatCurrency,
    formatNumber,
    formatPercent,
    formatShortCurrency,
} from '@/composables/useCarwashFormat';
import type {
    CarwashBookingSummary,
    CarwashBrand,
    CarwashCashSummary,
    CarwashCustomerActivity,
    CarwashInventorySummary,
    CarwashMonthlyPoint,
    CarwashRevenuePoint,
    CarwashShift,
    CarwashStat,
    CarwashTopService,
} from '@/types/carwash';

const props = defineProps<{
    brand: CarwashBrand;
    stats: CarwashStat[];
    revenueTrend: CarwashRevenuePoint[];
    monthlyTrend: CarwashMonthlyPoint[];
    topServices: CarwashTopService[];
    customerActivity: CarwashCustomerActivity;
    bookingSummary: CarwashBookingSummary;
    inventorySummary: CarwashInventorySummary;
    cashSummary: CarwashCashSummary;
    shifts: CarwashShift[];
}>();

const period = ref<'minggu' | 'bulan'>('minggu');

const weekRevenue = computed<number>(() =>
    props.revenueTrend.reduce((total, point) => total + point.revenue, 0),
);

const weekTransactions = computed<number>(() =>
    props.revenueTrend.reduce((total, point) => total + point.transactions, 0),
);

const monthRevenue = computed<number>(() =>
    props.monthlyTrend.reduce((total, point) => total + point.revenue, 0),
);

const monthExpense = computed<number>(() =>
    props.monthlyTrend.reduce((total, point) => total + point.expense, 0),
);

const grossMargin = computed<number>(() =>
    monthRevenue.value === 0
        ? 0
        : ((monthRevenue.value - monthExpense.value) / monthRevenue.value) *
          100,
);

const maxMonthly = computed<number>(() =>
    Math.max(...props.monthlyTrend.map((point) => point.revenue)),
);

const maxTopServiceRevenue = computed<number>(() =>
    Math.max(...props.topServices.map((service) => service.revenue)),
);

const totalServiceRevenue = computed<number>(() =>
    props.topServices.reduce((total, service) => total + service.revenue, 0),
);

const stampRedemptionRate = computed<number>(() =>
    props.customerActivity.stampsIssued === 0
        ? 0
        : (props.customerActivity.stampsRedeemed /
              props.customerActivity.stampsIssued) *
          100,
);
</script>

<template>
    <Head :title="`${brand.name} — Laporan`" />

    <div class="space-y-4">
        <!-- Period switch -->
        <section class="flex flex-wrap items-center justify-between gap-3">
            <div>
                <h2 class="text-base font-semibold text-slate-900">
                    Laporan & monitoring
                </h2>
                <p class="mt-0.5 text-xs text-slate-500">
                    Rekap operasional, keuangan, dan loyalty
                </p>
            </div>
            <div class="flex gap-1 rounded-xl bg-slate-200/70 p-1 text-sm">
                <button
                    type="button"
                    class="rounded-lg px-4 py-1.5 font-medium transition"
                    :class="
                        period === 'minggu'
                            ? 'bg-white text-slate-900 shadow-sm'
                            : 'text-slate-500'
                    "
                    @click="period = 'minggu'"
                >
                    Mingguan
                </button>
                <button
                    type="button"
                    class="rounded-lg px-4 py-1.5 font-medium transition"
                    :class="
                        period === 'bulan'
                            ? 'bg-white text-slate-900 shadow-sm'
                            : 'text-slate-500'
                    "
                    @click="period = 'bulan'"
                >
                    Bulanan
                </button>
            </div>
        </section>

        <!-- Headline numbers -->
        <section class="grid grid-cols-1 gap-4 sm:grid-cols-2 xl:grid-cols-4">
            <StatCard
                label="Pendapatan"
                :value="
                    formatShortCurrency(
                        period === 'minggu' ? weekRevenue : monthRevenue,
                    )
                "
                :caption="
                    period === 'minggu'
                        ? `${weekTransactions} transaksi minggu ini`
                        : '6 bulan terakhir'
                "
                :icon="Wallet"
                tone="emerald"
            />
            <StatCard
                label="Pengeluaran"
                :value="
                    formatShortCurrency(
                        period === 'minggu'
                            ? cashSummary.todayOut * 7
                            : monthExpense,
                    )
                "
                caption="biaya operasional"
                :icon="TrendingDown"
                tone="rose"
            />
            <StatCard
                label="Margin kotor"
                :value="formatPercent(grossMargin)"
                caption="pendapatan dikurangi biaya"
                :icon="TrendingUp"
            />
            <StatCard
                label="Rata-rata transaksi"
                :value="
                    formatCurrency(Math.round(weekRevenue / weekTransactions))
                "
                caption="per kendaraan"
                :icon="ChartColumn"
                tone="amber"
            />
        </section>

        <!-- Revenue vs expense -->
        <SectionCard
            title="Pendapatan vs pengeluaran"
            caption="Perbandingan 6 bulan terakhir"
        >
            <div class="mt-6 flex h-56 items-end gap-3 sm:gap-5">
                <div
                    v-for="point in monthlyTrend"
                    :key="point.month"
                    class="flex h-full flex-1 flex-col justify-end"
                >
                    <div class="flex h-full items-end justify-center gap-1">
                        <div
                            class="w-1/2 rounded-t bg-gradient-to-t from-cyan-600 to-cyan-400 transition-all"
                            :style="{
                                height: `${Math.round((point.revenue / maxMonthly) * 100)}%`,
                            }"
                            :title="`Pendapatan ${formatCurrency(point.revenue)}`"
                        ></div>
                        <div
                            class="w-1/2 rounded-t bg-gradient-to-t from-rose-500 to-rose-300 transition-all"
                            :style="{
                                height: `${Math.round((point.expense / maxMonthly) * 100)}%`,
                            }"
                            :title="`Pengeluaran ${formatCurrency(point.expense)}`"
                        ></div>
                    </div>
                    <p
                        class="mt-2 text-center text-[11px] font-medium text-slate-500"
                    >
                        {{ point.month }}
                    </p>
                </div>
            </div>

            <div
                class="mt-4 flex items-center gap-4 text-[11px] text-slate-500"
            >
                <span class="flex items-center gap-1.5">
                    <span class="h-2.5 w-2.5 rounded-sm bg-cyan-500"></span>
                    Pendapatan
                </span>
                <span class="flex items-center gap-1.5">
                    <span class="h-2.5 w-2.5 rounded-sm bg-rose-400"></span>
                    Pengeluaran
                </span>
            </div>
        </SectionCard>

        <section class="grid grid-cols-1 gap-4 xl:grid-cols-2">
            <!-- Service revenue -->
            <SectionCard
                title="Kontribusi layanan"
                :caption="`Total ${formatShortCurrency(totalServiceRevenue)} dari 5 layanan teratas`"
            >
                <ul class="mt-4 space-y-3.5">
                    <li v-for="service in topServices" :key="service.name">
                        <div class="flex items-baseline justify-between gap-3">
                            <p class="truncate text-sm text-slate-700">
                                {{ service.name }}
                            </p>
                            <p
                                class="shrink-0 text-xs font-medium text-slate-500 tabular-nums"
                            >
                                {{ formatShortCurrency(service.revenue) }}
                            </p>
                        </div>
                        <div class="mt-1.5 flex items-center gap-2">
                            <div
                                class="h-1.5 flex-1 overflow-hidden rounded-full bg-slate-100"
                            >
                                <div
                                    class="h-full rounded-full bg-gradient-to-r from-cyan-500 to-sky-500"
                                    :style="{
                                        width: `${Math.round((service.revenue / maxTopServiceRevenue) * 100)}%`,
                                    }"
                                ></div>
                            </div>
                            <p
                                class="w-12 text-right text-[11px] text-slate-400 tabular-nums"
                            >
                                {{ service.orders }}×
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
                            {{ customerActivity.newCustomers }}
                        </p>
                    </div>
                    <div class="rounded-xl bg-slate-50 p-3">
                        <p class="text-[11px] text-slate-500">
                            Customer kembali
                        </p>
                        <p
                            class="mt-0.5 text-xl font-semibold text-slate-900 tabular-nums"
                        >
                            {{ customerActivity.returningCustomers }}
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
                        <span class="font-medium text-slate-700">
                            {{ formatPercent(stampRedemptionRate) }}
                        </span>
                    </div>
                    <div
                        class="mt-1.5 h-2 overflow-hidden rounded-full bg-slate-100"
                    >
                        <div
                            class="h-full rounded-full bg-gradient-to-r from-cyan-500 to-sky-500"
                            :style="{ width: `${stampRedemptionRate}%` }"
                        ></div>
                    </div>
                </div>

                <div
                    class="mt-4 flex items-center gap-2 rounded-xl bg-amber-50 px-3 py-2.5 text-[11px] text-amber-800"
                >
                    <Sparkles class="h-3.5 w-3.5 shrink-0" />
                    {{ customerActivity.rewardsClaimed }} reward diklaim •
                    {{ customerActivity.churnRisk }} customer berisiko tidak
                    kembali
                </div>
            </SectionCard>
        </section>

        <section class="grid grid-cols-1 gap-4 xl:grid-cols-3">
            <!-- Booking summary -->
            <SectionCard title="Ringkasan booking" caption="Periode berjalan">
                <ul class="mt-4 space-y-2.5 text-sm">
                    <li class="flex items-center justify-between">
                        <span class="text-slate-500">Total booking</span>
                        <span class="font-semibold text-slate-900 tabular-nums">
                            {{ bookingSummary.total }}
                        </span>
                    </li>
                    <li class="flex items-center justify-between">
                        <span class="text-slate-500">Terjadwal</span>
                        <span class="font-medium text-cyan-600 tabular-nums">
                            {{ bookingSummary.scheduled }}
                        </span>
                    </li>
                    <li class="flex items-center justify-between">
                        <span class="text-slate-500">Selesai</span>
                        <span class="font-medium text-emerald-600 tabular-nums">
                            {{ bookingSummary.completed }}
                        </span>
                    </li>
                    <li class="flex items-center justify-between">
                        <span class="text-slate-500">Batal</span>
                        <span class="font-medium text-rose-600 tabular-nums">
                            {{ bookingSummary.cancelled }}
                        </span>
                    </li>
                    <li
                        class="flex items-center justify-between border-t border-slate-100 pt-2.5"
                    >
                        <span class="text-slate-500">Tingkat kehadiran</span>
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
                    <li class="flex items-center justify-between">
                        <span class="text-slate-500">Total item</span>
                        <span class="font-semibold text-slate-900 tabular-nums">
                            {{ inventorySummary.totalItems }}
                        </span>
                    </li>
                    <li class="flex items-center justify-between">
                        <span class="text-slate-500">Stok menipis</span>
                        <span class="font-medium text-rose-600 tabular-nums">
                            {{ inventorySummary.lowStock }}
                        </span>
                    </li>
                    <li class="flex items-center justify-between">
                        <span class="text-slate-500">Nilai persediaan</span>
                        <span class="font-semibold text-slate-900 tabular-nums">
                            {{
                                formatShortCurrency(inventorySummary.stockValue)
                            }}
                        </span>
                    </li>
                    <li class="flex items-center justify-between">
                        <span class="text-slate-500"
                            >Pergerakan minggu ini</span
                        >
                        <span class="font-medium text-slate-700 tabular-nums">
                            {{ inventorySummary.movementsThisWeek }}
                        </span>
                    </li>
                </ul>
                <p
                    class="mt-3 flex items-center gap-2 rounded-xl bg-slate-50 px-3 py-2 text-[11px] text-slate-600"
                >
                    <Boxes class="h-3.5 w-3.5 shrink-0" />
                    Paling banyak terpakai:
                    {{ inventorySummary.topConsumed }}
                </p>
            </SectionCard>

            <!-- Cash position -->
            <SectionCard title="Posisi kas" caption="Hari ini">
                <ul class="mt-4 space-y-2.5 text-sm">
                    <li class="flex items-center justify-between">
                        <span class="text-slate-500">Saldo awal</span>
                        <span class="text-slate-800 tabular-nums">
                            {{
                                formatShortCurrency(cashSummary.openingBalance)
                            }}
                        </span>
                    </li>
                    <li class="flex items-center justify-between">
                        <span class="text-slate-500">Uang masuk</span>
                        <span class="font-medium text-emerald-600 tabular-nums">
                            +{{ formatShortCurrency(cashSummary.todayIn) }}
                        </span>
                    </li>
                    <li class="flex items-center justify-between">
                        <span class="text-slate-500">Uang keluar</span>
                        <span class="font-medium text-rose-600 tabular-nums">
                            −{{ formatShortCurrency(cashSummary.todayOut) }}
                        </span>
                    </li>
                    <li
                        class="flex items-center justify-between border-t border-slate-100 pt-2.5"
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
                                        class="flex h-7 w-7 items-center justify-center rounded-full bg-slate-100 text-[10px] font-semibold text-slate-600"
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
                                {{ shift.transactions }}
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
            caption="Ringkasan otomatis periode ini"
        >
            <ul class="mt-3 space-y-2 text-xs text-slate-600">
                <li class="flex items-start gap-2">
                    <Users class="mt-0.5 h-3.5 w-3.5 shrink-0 text-cyan-600" />
                    Rata-rata
                    {{ customerActivity.averageVisitsPerCustomer }} kunjungan
                    per customer — naikkan dengan promo double stempel akhir
                    pekan.
                </li>
                <li class="flex items-start gap-2">
                    <TrendingUp
                        class="mt-0.5 h-3.5 w-3.5 shrink-0 text-emerald-600"
                    />
                    Margin kotor {{ formatPercent(grossMargin) }}, di atas
                    target internal 50%.
                </li>
                <li class="flex items-start gap-2">
                    <Boxes class="mt-0.5 h-3.5 w-3.5 shrink-0 text-rose-600" />
                    {{ inventorySummary.lowStock }} item stok menipis berpotensi
                    menghambat layanan detailing.
                </li>
            </ul>
        </SectionCard>
    </div>
</template>
