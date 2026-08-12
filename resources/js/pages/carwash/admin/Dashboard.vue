<script setup lang="ts">
import { Head, Link } from '@inertiajs/vue3';
import {
    ArrowUpRight,
    Car,
    Clock,
    Gift,
    ScanLine,
    Sparkles,
    Star,
    TrendingDown,
    TrendingUp,
    UserPlus,
    Users,
    Wallet,
} from '@lucide/vue';
import type { LucideIcon } from '@lucide/vue';
import { computed, ref } from 'vue';
import StatCard from '@/components/carwash/StatCard.vue';
import StatusPill from '@/components/carwash/StatusPill.vue';
import {
    formatCurrency,
    formatShortCurrency,
} from '@/composables/useCarwashFormat';
import admin from '@/routes/carwash/admin';
import type {
    CarwashBrand,
    CarwashCashSummary,
    CarwashCrewMember,
    CarwashQueueItem,
    CarwashRevenuePoint,
    CarwashShift,
    CarwashStat,
    CarwashTopService,
} from '@/types/carwash';

const props = defineProps<{
    brand: CarwashBrand;
    persona: { name: string; title: string; initials: string };
    stats: CarwashStat[];
    revenueTrend: CarwashRevenuePoint[];
    topServices: CarwashTopService[];
    shifts: CarwashShift[];
    queue: CarwashQueueItem[];
    crew: CarwashCrewMember[];
    cashSummary: CarwashCashSummary;
    customerCount: number;
}>();

const statIcons: Record<string, LucideIcon> = {
    wallet: Wallet,
    car: Car,
    users: Users,
    gift: Gift,
};

const hoveredBar = ref<number | null>(null);

const maxRevenue = computed<number>(() =>
    Math.max(...props.revenueTrend.map((point) => point.revenue)),
);

const weekRevenue = computed<number>(() =>
    props.revenueTrend.reduce((total, point) => total + point.revenue, 0),
);

const weekTransactions = computed<number>(() =>
    props.revenueTrend.reduce((total, point) => total + point.transactions, 0),
);

const averageTicket = computed<number>(() =>
    Math.round(weekRevenue.value / weekTransactions.value),
);

const maxTopServiceOrders = computed<number>(() =>
    Math.max(...props.topServices.map((service) => service.orders)),
);

const activeQueue = computed<CarwashQueueItem[]>(() =>
    props.queue.filter((item) => item.status !== 'selesai'),
);
</script>

<template>
    <Head :title="`${brand.name} — Dashboard`" />

    <div class="space-y-6">
        <!-- Hero -->
        <section
            class="relative overflow-hidden rounded-3xl bg-gradient-to-br from-slate-900 via-slate-900 to-cyan-950 p-6 text-white sm:p-8"
        >
            <div
                class="absolute -top-24 -right-16 h-64 w-64 rounded-full bg-cyan-500/20 blur-3xl"
            ></div>
            <div
                class="absolute -bottom-32 left-1/3 h-64 w-64 rounded-full bg-sky-500/10 blur-3xl"
            ></div>

            <div
                class="relative flex flex-col gap-6 lg:flex-row lg:items-end lg:justify-between"
            >
                <div class="max-w-lg">
                    <span
                        class="inline-flex items-center gap-1.5 rounded-full bg-white/10 px-3 py-1 text-[11px] font-medium text-cyan-200 ring-1 ring-white/15"
                    >
                        <Sparkles class="h-3.5 w-3.5" />
                        Semua bay beroperasi normal
                    </span>
                    <h2 class="mt-4 text-2xl font-semibold sm:text-3xl">
                        Halo, {{ persona.name.split(' ')[0] }} 👋
                    </h2>
                    <p class="mt-2 text-sm text-slate-300">
                        {{ activeQueue.length }} kendaraan sedang dalam antrean
                        dan {{ customerCount }} customer terdaftar di database
                        loyalty.
                    </p>
                    <div class="mt-5 flex flex-wrap gap-2">
                        <Link
                            :href="admin.pos.url()"
                            class="flex items-center gap-2 rounded-xl bg-white px-4 py-2.5 text-sm font-medium text-slate-900 transition hover:bg-slate-100"
                        >
                            <ScanLine class="h-4 w-4" />
                            Buka Kasir
                        </Link>
                        <Link
                            :href="admin.customers.url()"
                            class="flex items-center gap-2 rounded-xl bg-white/10 px-4 py-2.5 text-sm font-medium text-white ring-1 ring-white/15 transition hover:bg-white/15"
                        >
                            <UserPlus class="h-4 w-4" />
                            Kelola Customer
                        </Link>
                    </div>
                </div>

                <div
                    class="grid grid-cols-3 gap-3 rounded-2xl bg-white/5 p-4 ring-1 ring-white/10 sm:gap-6"
                >
                    <div>
                        <p class="text-[11px] text-slate-400">
                            Omzet minggu ini
                        </p>
                        <p
                            class="mt-1 text-lg font-semibold tabular-nums sm:text-xl"
                        >
                            {{ formatShortCurrency(weekRevenue) }}
                        </p>
                    </div>
                    <div>
                        <p class="text-[11px] text-slate-400">Transaksi</p>
                        <p
                            class="mt-1 text-lg font-semibold tabular-nums sm:text-xl"
                        >
                            {{ weekTransactions }}
                        </p>
                    </div>
                    <div>
                        <p class="text-[11px] text-slate-400">Rata-rata</p>
                        <p
                            class="mt-1 text-lg font-semibold tabular-nums sm:text-xl"
                        >
                            {{ formatShortCurrency(averageTicket) }}
                        </p>
                    </div>
                </div>
            </div>
        </section>

        <!-- Today's stats -->
        <section class="grid grid-cols-1 gap-4 sm:grid-cols-2 xl:grid-cols-4">
            <StatCard
                v-for="stat in stats"
                :key="stat.label"
                :label="stat.label"
                :value="stat.value"
                :caption="stat.caption"
                :delta="stat.delta"
                :trend="stat.trend"
                :icon="statIcons[stat.icon]"
            />
        </section>

        <!-- Cash + shifts -->
        <section class="grid grid-cols-1 gap-4 xl:grid-cols-3">
            <article
                class="rounded-2xl border border-slate-200/80 bg-white p-5 shadow-sm"
            >
                <h3 class="text-sm font-semibold text-slate-900">
                    Arus kas hari ini
                </h3>
                <p class="mt-0.5 text-xs text-slate-500">
                    Saldo awal
                    {{ formatShortCurrency(cashSummary.openingBalance) }}
                </p>

                <div class="mt-4 space-y-3">
                    <div class="flex items-center gap-3">
                        <span
                            class="flex h-9 w-9 items-center justify-center rounded-xl bg-emerald-50 text-emerald-600"
                        >
                            <TrendingUp class="h-4 w-4" />
                        </span>
                        <div class="min-w-0 flex-1">
                            <p class="text-xs text-slate-500">Uang masuk</p>
                            <p
                                class="text-sm font-semibold text-slate-900 tabular-nums"
                            >
                                {{ formatCurrency(cashSummary.todayIn) }}
                            </p>
                        </div>
                    </div>
                    <div class="flex items-center gap-3">
                        <span
                            class="flex h-9 w-9 items-center justify-center rounded-xl bg-rose-50 text-rose-600"
                        >
                            <TrendingDown class="h-4 w-4" />
                        </span>
                        <div class="min-w-0 flex-1">
                            <p class="text-xs text-slate-500">Uang keluar</p>
                            <p
                                class="text-sm font-semibold text-slate-900 tabular-nums"
                            >
                                {{ formatCurrency(cashSummary.todayOut) }}
                            </p>
                        </div>
                    </div>
                </div>

                <div
                    class="mt-4 flex items-end justify-between border-t border-dashed border-slate-200 pt-3"
                >
                    <span class="text-xs font-medium text-slate-600">
                        Saldo akhir
                    </span>
                    <span
                        class="text-lg font-semibold text-slate-900 tabular-nums"
                    >
                        {{ formatCurrency(cashSummary.closingBalance) }}
                    </span>
                </div>

                <p
                    v-if="cashSummary.pendingPayments > 0"
                    class="mt-2 rounded-xl bg-amber-50 px-3 py-2 text-[11px] text-amber-800"
                >
                    {{ formatCurrency(cashSummary.pendingPayments) }} belum
                    tertagih dari order berjalan.
                </p>
            </article>

            <!-- Shift overview -->
            <article
                class="rounded-2xl border border-slate-200/80 bg-white p-5 shadow-sm xl:col-span-2"
            >
                <h3 class="text-sm font-semibold text-slate-900">
                    Ringkasan shift
                </h3>
                <p class="mt-0.5 text-xs text-slate-500">
                    Performa kasir per shift hari ini
                </p>

                <div class="mt-4 grid grid-cols-1 gap-3 sm:grid-cols-2">
                    <div
                        v-for="shift in shifts"
                        :key="shift.id"
                        class="rounded-xl border border-slate-100 bg-slate-50/60 p-4"
                    >
                        <div class="flex items-center gap-3">
                            <div
                                class="flex h-9 w-9 items-center justify-center rounded-full bg-white text-xs font-semibold text-slate-600 ring-1 ring-slate-200"
                            >
                                {{ shift.initials }}
                            </div>
                            <div class="min-w-0 flex-1">
                                <p
                                    class="truncate text-sm font-medium text-slate-900"
                                >
                                    {{ shift.name }}
                                </p>
                                <p class="text-[11px] text-slate-500">
                                    {{ shift.time }}
                                </p>
                            </div>
                            <StatusPill :status="shift.status" />
                        </div>

                        <div class="mt-3 grid grid-cols-3 gap-2">
                            <div>
                                <p class="text-[10px] text-slate-500">Omzet</p>
                                <p
                                    class="text-sm font-semibold text-slate-900 tabular-nums"
                                >
                                    {{ formatShortCurrency(shift.revenue) }}
                                </p>
                            </div>
                            <div>
                                <p class="text-[10px] text-slate-500">
                                    Transaksi
                                </p>
                                <p
                                    class="text-sm font-semibold text-slate-900 tabular-nums"
                                >
                                    {{ shift.transactions }}
                                </p>
                            </div>
                            <div>
                                <p class="text-[10px] text-slate-500">Kasir</p>
                                <p
                                    class="truncate text-sm font-medium text-slate-700"
                                >
                                    {{ shift.cashier.split(' ')[0] }}
                                </p>
                            </div>
                        </div>
                    </div>
                </div>
            </article>
        </section>

        <!-- Revenue chart + top services -->
        <section class="grid grid-cols-1 gap-4 xl:grid-cols-3">
            <article
                class="rounded-2xl border border-slate-200/80 bg-white p-5 shadow-sm xl:col-span-2"
            >
                <div class="flex flex-wrap items-start justify-between gap-3">
                    <div>
                        <h3 class="text-sm font-semibold text-slate-900">
                            Pendapatan 7 hari terakhir
                        </h3>
                        <p class="mt-0.5 text-xs text-slate-500">
                            Total {{ formatCurrency(weekRevenue) }} dari
                            {{ weekTransactions }} transaksi
                        </p>
                    </div>
                    <span
                        class="flex items-center gap-1.5 rounded-full bg-emerald-50 px-2.5 py-1 text-xs font-medium text-emerald-600"
                    >
                        <ArrowUpRight class="h-3.5 w-3.5" />
                        +18,2% vs minggu lalu
                    </span>
                </div>

                <div class="relative mt-8">
                    <div
                        class="pointer-events-none absolute inset-x-0 top-0 bottom-7 flex flex-col justify-between"
                    >
                        <div
                            v-for="line in 4"
                            :key="line"
                            class="border-t border-dashed border-slate-100"
                        ></div>
                    </div>

                    <div class="relative flex h-52 items-end gap-2 sm:gap-3">
                        <div
                            v-for="(point, index) in revenueTrend"
                            :key="point.day"
                            class="group relative flex h-full flex-1 flex-col justify-end"
                            @mouseenter="hoveredBar = index"
                            @mouseleave="hoveredBar = null"
                        >
                            <div
                                v-if="hoveredBar === index"
                                class="absolute -top-1 left-1/2 z-10 w-36 -translate-x-1/2 -translate-y-full rounded-xl bg-slate-900 px-3 py-2 text-left shadow-xl"
                            >
                                <p class="text-[11px] text-slate-400">
                                    {{ point.date }}
                                </p>
                                <p
                                    class="text-sm font-semibold text-white tabular-nums"
                                >
                                    {{ formatCurrency(point.revenue) }}
                                </p>
                                <p class="text-[11px] text-cyan-300">
                                    {{ point.transactions }} transaksi
                                </p>
                            </div>

                            <div
                                class="w-full rounded-t bg-gradient-to-t transition-all duration-300"
                                :class="
                                    point.revenue === maxRevenue
                                        ? 'from-cyan-500 to-sky-400'
                                        : 'from-cyan-600/70 to-cyan-400/70 group-hover:from-cyan-500 group-hover:to-sky-400'
                                "
                                :style="{
                                    height: `${Math.round((point.revenue / maxRevenue) * 100)}%`,
                                }"
                            ></div>
                            <p
                                class="mt-2 text-center text-[11px] font-medium text-slate-500"
                            >
                                {{ point.day }}
                            </p>
                        </div>
                    </div>
                </div>
            </article>

            <article
                class="rounded-2xl border border-slate-200/80 bg-white p-5 shadow-sm"
            >
                <h3 class="text-sm font-semibold text-slate-900">
                    Layanan terlaris
                </h3>
                <p class="mt-0.5 text-xs text-slate-500">30 hari terakhir</p>
                <ul class="mt-4 space-y-3.5">
                    <li v-for="service in topServices" :key="service.name">
                        <div class="flex items-baseline justify-between gap-3">
                            <p class="truncate text-sm text-slate-700">
                                {{ service.name }}
                            </p>
                            <p
                                class="shrink-0 text-xs font-medium text-slate-500 tabular-nums"
                            >
                                {{ service.orders }}×
                            </p>
                        </div>
                        <div class="mt-1.5 flex items-center gap-2">
                            <div
                                class="h-1.5 flex-1 overflow-hidden rounded-full bg-slate-100"
                            >
                                <div
                                    class="h-full rounded-full bg-cyan-500"
                                    :style="{
                                        width: `${Math.round((service.orders / maxTopServiceOrders) * 100)}%`,
                                    }"
                                ></div>
                            </div>
                            <p
                                class="w-16 text-right text-[11px] text-slate-400 tabular-nums"
                            >
                                {{ formatShortCurrency(service.revenue) }}
                            </p>
                        </div>
                    </li>
                </ul>
            </article>
        </section>

        <!-- Queue + crew -->
        <section class="grid grid-cols-1 gap-4 xl:grid-cols-3">
            <article
                class="rounded-2xl border border-slate-200/80 bg-white p-5 shadow-sm xl:col-span-2"
            >
                <div class="flex items-center justify-between">
                    <div>
                        <h3 class="text-sm font-semibold text-slate-900">
                            Antrean hari ini
                        </h3>
                        <p class="mt-0.5 text-xs text-slate-500">
                            {{ activeQueue.length }} kendaraan belum selesai
                            dikerjakan
                        </p>
                    </div>
                    <span
                        class="flex items-center gap-1.5 rounded-full bg-cyan-50 px-2.5 py-1 text-xs font-medium text-cyan-700"
                    >
                        <span
                            class="h-1.5 w-1.5 animate-pulse rounded-full bg-cyan-500"
                        ></span>
                        Live
                    </span>
                </div>

                <ul class="mt-4 space-y-2.5">
                    <li
                        v-for="item in queue"
                        :key="item.id"
                        class="rounded-xl border border-slate-100 bg-slate-50/60 p-3.5 transition hover:border-cyan-200 hover:bg-white"
                    >
                        <div class="flex flex-wrap items-center gap-3">
                            <div
                                class="flex h-10 w-10 items-center justify-center rounded-xl bg-white text-slate-500 ring-1 ring-slate-200"
                            >
                                <Car class="h-5 w-5" />
                            </div>
                            <div class="min-w-0 flex-1">
                                <p class="text-sm font-semibold text-slate-900">
                                    {{ item.plate }}
                                </p>
                                <p class="truncate text-xs text-slate-500">
                                    {{ item.vehicle }} • {{ item.service }}
                                </p>
                            </div>
                            <div class="text-right">
                                <StatusPill :status="item.status" />
                                <p
                                    class="mt-1 flex items-center justify-end gap-1 text-[11px] text-slate-400"
                                >
                                    <Clock class="h-3 w-3" />
                                    {{ item.eta }}
                                </p>
                            </div>
                        </div>

                        <div
                            v-if="item.status === 'proses'"
                            class="mt-3 flex items-center gap-3"
                        >
                            <div
                                class="h-1.5 flex-1 overflow-hidden rounded-full bg-slate-200"
                            >
                                <div
                                    class="h-full rounded-full bg-gradient-to-r from-cyan-500 to-sky-500 transition-all"
                                    :style="{ width: `${item.progress}%` }"
                                ></div>
                            </div>
                            <p
                                class="w-24 text-right text-[11px] text-slate-500"
                            >
                                {{ item.bay }} • {{ item.progress }}%
                            </p>
                        </div>
                    </li>
                </ul>
            </article>

            <article
                class="rounded-2xl border border-slate-200/80 bg-white p-5 shadow-sm"
            >
                <h3 class="text-sm font-semibold text-slate-900">
                    Performa crew
                </h3>
                <p class="mt-0.5 text-xs text-slate-500">
                    Pekerjaan selesai hari ini
                </p>
                <ul class="mt-4 space-y-3">
                    <li
                        v-for="person in crew"
                        :key="person.name"
                        class="flex items-center gap-3"
                    >
                        <div
                            class="flex h-9 w-9 items-center justify-center rounded-full bg-slate-100 text-xs font-semibold text-slate-600"
                        >
                            {{ person.initials }}
                        </div>
                        <div class="min-w-0 flex-1">
                            <p
                                class="truncate text-sm font-medium text-slate-800"
                            >
                                {{ person.name }}
                            </p>
                            <p class="text-[11px] text-slate-500">
                                {{ person.role }} • {{ person.jobs }} pekerjaan
                            </p>
                        </div>
                        <p
                            class="flex items-center gap-1 text-xs font-medium text-amber-500"
                        >
                            <Star
                                class="h-3.5 w-3.5 fill-amber-400 text-amber-400"
                            />
                            {{ person.rating }}
                        </p>
                    </li>
                </ul>
            </article>
        </section>
    </div>
</template>
