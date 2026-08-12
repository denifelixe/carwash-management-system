<script setup lang="ts">
import { Head } from '@inertiajs/vue3';
import { Car, Sparkles, Star } from '@lucide/vue';
import { computed, ref } from 'vue';
import StampProgress from '@/components/carwash/StampProgress.vue';
import StatusPill from '@/components/carwash/StatusPill.vue';
import { formatCurrency, formatNumber } from '@/composables/useCarwashFormat';
import type {
    CarwashBrand,
    CarwashMember,
    CarwashReward,
    CarwashStampEntry,
    CarwashWashEntry,
} from '@/types/carwash';

const props = defineProps<{
    brand: CarwashBrand;
    member: CarwashMember;
    stampHistory: CarwashStampEntry[];
    washHistory: CarwashWashEntry[];
    rewards: CarwashReward[];
}>();

const activeTab = ref<'stamps' | 'visits'>('stamps');

const stampsToReward = computed<number>(() =>
    Math.max(props.brand.stampTarget - props.member.stamps, 0),
);

/** The cheapest reward still out of reach, to show what's next (BR-04). */
const nextReward = computed<CarwashReward | null>(
    () =>
        props.rewards
            .filter(
                (reward) =>
                    reward.status === 'aktif' &&
                    reward.requiredStamps > props.member.stamps,
            )
            .sort(
                (first, second) => first.requiredStamps - second.requiredStamps,
            )[0] ?? null,
);

const totalEarned = computed<number>(() =>
    props.stampHistory
        .filter((entry) => entry.stamps > 0)
        .reduce((total, entry) => total + entry.stamps, 0),
);

const totalRedeemed = computed<number>(() =>
    props.stampHistory
        .filter((entry) => entry.stamps < 0)
        .reduce((total, entry) => total + Math.abs(entry.stamps), 0),
);

function activityToneClass(type: string): string {
    switch (type) {
        case 'redeem':
            return 'text-cyan-600';
        case 'bonus':
            return 'text-amber-600';
        default:
            return 'text-emerald-600';
    }
}
</script>

<template>
    <Head :title="`${brand.name} — Stempel`" />

    <div class="space-y-5 px-5 py-5">
        <!-- Current balance -->
        <section
            class="rounded-2xl bg-gradient-to-br from-slate-900 to-cyan-900 p-5 text-white"
        >
            <p class="text-xs text-slate-300">Stempel tersedia</p>
            <p class="mt-1 text-3xl font-semibold tracking-tight tabular-nums">
                {{ member.stamps }}
                <span class="text-base font-medium text-slate-400">
                    / {{ brand.stampTarget }}
                </span>
            </p>

            <div class="mt-4">
                <StampProgress
                    :stamps="member.stamps"
                    :target="brand.stampTarget"
                    compact
                />
            </div>

            <p class="mt-3 text-[11px] text-slate-400">
                <template v-if="stampsToReward > 0">
                    Kumpulkan {{ stampsToReward }} stempel lagi untuk
                    {{ brand.stampReward.toLowerCase() }}.
                </template>
                <template v-else>
                    Kartu penuh — tunjukkan ke kasir untuk klaim
                    {{ brand.stampReward.toLowerCase() }}.
                </template>
            </p>
        </section>

        <!-- Stamp grid -->
        <section class="rounded-2xl border border-slate-200 bg-white p-5">
            <h2 class="text-sm font-semibold text-slate-900">
                Progres kartu stempel
            </h2>
            <p class="mt-0.5 text-xs text-slate-500">
                1 stempel per cuci — layanan besar memberi lebih banyak.
            </p>
            <div class="mt-4">
                <StampProgress
                    :stamps="member.stamps"
                    :target="brand.stampTarget"
                />
            </div>
        </section>

        <!-- Next reward -->
        <section
            v-if="nextReward"
            class="flex items-center gap-3 rounded-2xl border border-dashed border-cyan-300 bg-cyan-50/60 p-4"
        >
            <div
                class="flex h-12 w-12 items-center justify-center rounded-xl bg-white text-2xl shadow-sm"
            >
                {{ nextReward.icon }}
            </div>
            <div class="min-w-0 flex-1">
                <p class="text-[11px] font-medium text-cyan-700">
                    Reward berikutnya
                </p>
                <p class="truncate text-sm font-semibold text-slate-900">
                    {{ nextReward.name }}
                </p>
                <p class="text-[11px] text-slate-500">
                    Kurang
                    {{ nextReward.requiredStamps - member.stamps }} stempel lagi
                </p>
            </div>
        </section>

        <!-- Totals -->
        <section class="grid grid-cols-3 gap-2">
            <div
                class="rounded-2xl border border-slate-200 bg-white p-3 text-center"
            >
                <p class="text-lg font-semibold text-slate-900 tabular-nums">
                    {{ formatNumber(member.lifetimeStamps) }}
                </p>
                <p class="text-[11px] text-slate-500">Total dikumpulkan</p>
            </div>
            <div
                class="rounded-2xl border border-slate-200 bg-white p-3 text-center"
            >
                <p class="text-lg font-semibold text-emerald-600 tabular-nums">
                    +{{ totalEarned }}
                </p>
                <p class="text-[11px] text-slate-500">Periode ini</p>
            </div>
            <div
                class="rounded-2xl border border-slate-200 bg-white p-3 text-center"
            >
                <p class="text-lg font-semibold text-cyan-600 tabular-nums">
                    −{{ totalRedeemed }}
                </p>
                <p class="text-[11px] text-slate-500">Ditukar</p>
            </div>
        </section>

        <!-- Tabs -->
        <div class="flex gap-1 rounded-xl bg-slate-200/70 p-1 text-sm">
            <button
                type="button"
                class="flex-1 rounded-lg py-2 font-medium transition"
                :class="
                    activeTab === 'stamps'
                        ? 'bg-white text-slate-900 shadow-sm'
                        : 'text-slate-500'
                "
                @click="activeTab = 'stamps'"
            >
                Mutasi stempel
            </button>
            <button
                type="button"
                class="flex-1 rounded-lg py-2 font-medium transition"
                :class="
                    activeTab === 'visits'
                        ? 'bg-white text-slate-900 shadow-sm'
                        : 'text-slate-500'
                "
                @click="activeTab = 'visits'"
            >
                Riwayat cuci
            </button>
        </div>

        <ul
            v-if="activeTab === 'stamps'"
            class="divide-y divide-slate-100 overflow-hidden rounded-2xl border border-slate-200 bg-white"
        >
            <li
                v-for="activity in stampHistory"
                :key="activity.id"
                class="flex items-center gap-3 p-4"
            >
                <div
                    class="flex h-10 w-10 items-center justify-center rounded-xl bg-slate-50 text-lg"
                >
                    {{ activity.icon }}
                </div>
                <div class="min-w-0 flex-1">
                    <p class="truncate text-sm font-medium text-slate-800">
                        {{ activity.title }}
                    </p>
                    <p class="truncate text-[11px] text-slate-500">
                        {{ activity.detail }}
                    </p>
                    <p class="text-[11px] text-slate-400">
                        {{ activity.date }}
                    </p>
                </div>
                <p
                    class="text-sm font-semibold tabular-nums"
                    :class="activityToneClass(activity.type)"
                >
                    {{ activity.stamps > 0 ? '+' : '' }}{{ activity.stamps }}
                </p>
            </li>
        </ul>

        <ul v-else class="space-y-3">
            <li
                v-for="visit in washHistory"
                :key="visit.id"
                class="rounded-2xl border border-slate-200 bg-white p-4"
            >
                <div class="flex items-start justify-between gap-3">
                    <div class="min-w-0">
                        <p class="text-sm font-semibold text-slate-900">
                            {{ visit.service }}
                        </p>
                        <p
                            class="mt-0.5 flex items-center gap-1 text-[11px] text-slate-500"
                        >
                            <Car class="h-3 w-3" />
                            {{ visit.vehicle }}
                        </p>
                    </div>
                    <div class="shrink-0 text-right">
                        <StatusPill :status="visit.status" />
                        <p class="mt-1 text-[11px] text-slate-400">
                            {{ visit.date }}
                        </p>
                    </div>
                </div>
                <div
                    class="mt-3 flex items-center justify-between border-t border-slate-100 pt-3"
                >
                    <div class="flex items-center gap-1">
                        <Star
                            v-for="index in 5"
                            :key="index"
                            class="h-3.5 w-3.5"
                            :class="
                                index <= visit.rating
                                    ? 'fill-amber-400 text-amber-400'
                                    : 'text-slate-200'
                            "
                        />
                    </div>
                    <div class="text-right">
                        <p
                            class="text-sm font-semibold text-slate-900 tabular-nums"
                        >
                            {{ formatCurrency(visit.total) }}
                        </p>
                        <p
                            class="flex items-center justify-end gap-1 text-[11px] font-medium text-emerald-600"
                        >
                            <Sparkles class="h-3 w-3" />
                            +{{ visit.stamps }} stempel
                        </p>
                    </div>
                </div>
            </li>
        </ul>
    </div>
</template>
