<script setup lang="ts">
import { Head, router } from '@inertiajs/vue3';
import {
    Car,
    Gift,
    TrendingDown,
    TrendingUp,
    Users,
    Wallet,
} from '@lucide/vue';
import type { LucideIcon } from '@lucide/vue';
import DateFilterBar from '@/components/demo/DateFilterBar.vue';
import StatCard from '@/components/demo/StatCard.vue';
import StatusPill from '@/components/demo/StatusPill.vue';
import { formatCurrency } from '@/composables/useCarwashFormat';
import type {
    CarwashBrand,
    CarwashCashSummary,
    CarwashDateFilter,
    CarwashOrderSummary,
    CarwashPersona,
    CarwashShift,
    CarwashStat,
} from '@/types/demo';

const props = defineProps<{
    brand: CarwashBrand;
    persona: CarwashPersona;
    filterUrl: string;
    stats: CarwashStat[];
    filters: CarwashDateFilter;
    shifts: CarwashShift[];
    orderSummary: CarwashOrderSummary;
    cashSummary: CarwashCashSummary;
}>();

const statIcons: Record<string, LucideIcon> = {
    wallet: Wallet,
    car: Car,
    users: Users,
    gift: Gift,
};

function applyDate(date: string): void {
    router.get(
        props.filterUrl,
        { date },
        {
            preserveScroll: true,
            replace: true,
        },
    );
}
</script>

<template>
    <Head :title="`${brand.name} — Dashboard`" />

    <div class="space-y-6">
        <DateFilterBar :filters="filters" @change="applyDate" />

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

            <div class="relative">
                <div class="max-w-lg">
                    <h2 class="text-2xl font-semibold sm:text-3xl">
                        Halo, {{ persona.name.split(' ')[0] }} 👋
                    </h2>
                    <p class="mt-2 text-sm text-slate-300">
                        Total order kendaraan hari ini
                        {{ orderSummary.total }} ({{
                            orderSummary.served
                        }}
                        dilayani, {{ orderSummary.awaitingBooking }} booking -
                        belum datang)
                    </p>
                </div>
            </div>
        </section>

        <section class="grid grid-cols-1 gap-4 sm:grid-cols-2 xl:grid-cols-4">
            <StatCard
                v-for="stat in stats"
                :key="stat.label"
                :label="stat.label"
                :value="stat.value"
                :caption="stat.caption"
                :delta="stat.delta"
                :trend="stat.trend"
                comparison-label="Dibanding hari sebelumnya"
                :icon="statIcons[stat.icon]"
            />
        </section>

        <section class="space-y-4">
            <article
                class="rounded-2xl border border-slate-200/80 bg-white p-5 shadow-sm"
            >
                <h3 class="text-sm font-semibold text-slate-900">
                    Ringkasan shift
                </h3>
                <p class="mt-0.5 text-xs text-slate-500">
                    Performa kasir per shift hari ini
                </p>

                <div
                    v-if="shifts.length > 0"
                    class="mt-4 grid grid-cols-1 gap-3 sm:grid-cols-2 xl:grid-cols-3"
                >
                    <div
                        v-for="shift in shifts"
                        :key="shift.id"
                        class="rounded-xl border border-slate-100 bg-slate-50/60 p-4"
                    >
                        <div class="flex items-center gap-3">
                            <div class="min-w-0 flex-1">
                                <p
                                    class="truncate text-sm font-medium text-slate-900"
                                >
                                    {{ shift.name }}
                                </p>
                                <p
                                    v-if="shift.time"
                                    class="text-[11px] text-slate-500"
                                >
                                    {{ shift.time }}
                                </p>
                            </div>
                            <StatusPill
                                v-if="shift.status"
                                :status="shift.status"
                            />
                        </div>

                        <div class="mt-3 grid grid-cols-2 gap-2">
                            <div>
                                <p class="text-[10px] text-slate-500">Omzet</p>
                                <p
                                    class="text-sm font-semibold text-slate-900 tabular-nums"
                                >
                                    {{ formatCurrency(shift.revenue) }}
                                </p>
                            </div>
                            <div>
                                <p class="text-[10px] text-slate-500">
                                    Kendaraan dilayani
                                </p>
                                <p
                                    class="text-sm font-semibold text-slate-900 tabular-nums"
                                >
                                    {{ shift.vehiclesServed }}
                                </p>
                            </div>
                        </div>
                    </div>
                </div>

                <div
                    v-else
                    class="mt-4 flex min-h-28 items-center justify-center rounded-xl border border-dashed border-slate-200 bg-slate-50/60 px-4 text-center"
                >
                    <p class="text-sm text-slate-500">
                        Belum ada data performa shift untuk tanggal ini.
                    </p>
                </div>
            </article>

            <article
                class="rounded-2xl border border-slate-200/80 bg-white p-5 shadow-sm"
            >
                <h3 class="text-sm font-semibold text-slate-900">
                    Arus kas hari ini
                </h3>

                <div class="mt-4 grid grid-cols-1 gap-3 sm:grid-cols-3">
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
                    <div class="flex items-center gap-3">
                        <span
                            class="flex h-9 w-9 items-center justify-center rounded-xl bg-cyan-50 text-cyan-600"
                        >
                            <Wallet class="h-4 w-4" />
                        </span>
                        <div class="min-w-0 flex-1">
                            <p class="text-xs text-slate-500">Sisa Saldo</p>
                            <p
                                class="text-sm font-semibold text-slate-900 tabular-nums"
                            >
                                {{
                                    formatCurrency(cashSummary.remainingBalance)
                                }}
                            </p>
                        </div>
                    </div>
                </div>

                <p
                    v-if="cashSummary.pendingPayments > 0"
                    class="mt-4 rounded-xl bg-amber-50 px-3 py-2 text-[11px] text-amber-800"
                >
                    {{ formatCurrency(cashSummary.pendingPayments) }} belum
                    tertagih dari order berjalan.
                </p>
            </article>
        </section>
    </div>
</template>
