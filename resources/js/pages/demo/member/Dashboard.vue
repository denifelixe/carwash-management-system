<script setup lang="ts">
import { Head, Link } from '@inertiajs/vue3';
import {
    Car,
    ChevronRight,
    Clock,
    Gift,
    QrCode,
    Sparkles,
    X,
} from '@lucide/vue';
import { computed, ref } from 'vue';
import StampProgress from '@/components/demo/StampProgress.vue';
import { formatCurrency, formatNumber } from '@/composables/useCarwashFormat';
import memberRoutes from '@/routes/demo/member';
import type {
    CarwashBrand,
    CarwashMember,
    CarwashPromo,
    CarwashReward,
    CarwashStampEntry,
    CarwashWashEntry,
} from '@/types/demo';

const props = defineProps<{
    brand: CarwashBrand;
    member: CarwashMember;
    stampHistory: CarwashStampEntry[];
    washHistory: CarwashWashEntry[];
    rewards: CarwashReward[];
    promos: CarwashPromo[];
}>();

/**
 * Feature flag for the digital member card (QR modal). Set to `true` to bring
 * the scan button and its modal back — the markup below is kept intact.
 */
const isMemberCardEnabled = false as boolean;

const isQrOpen = ref<boolean>(false);

const stampsToReward = computed<number>(() =>
    Math.max(props.brand.stampTarget - props.member.stamps, 0),
);

/** Rewards the member already has enough stamps for (BR-04). */
const unlockedRewards = computed<CarwashReward[]>(() =>
    props.rewards
        .filter(
            (reward) =>
                reward.status === 'aktif' &&
                reward.requiredStamps <= props.member.stamps,
        )
        .sort((first, second) => second.requiredStamps - first.requiredStamps),
);

/** Deterministic 21×21 pattern so the demo QR looks real without a library. */
const qrCells = computed<boolean[]>(() => {
    const seedSource = `${props.member.memberId}${props.member.name}`;
    let seed = 0;

    for (const character of seedSource) {
        seed = (seed * 31 + character.charCodeAt(0)) % 233280;
    }

    return Array.from({ length: 441 }, (_, index) => {
        const row = Math.floor(index / 21);
        const column = index % 21;
        const inFinder = (startRow: number, startColumn: number): boolean =>
            row >= startRow &&
            row < startRow + 7 &&
            column >= startColumn &&
            column < startColumn + 7;

        if (inFinder(0, 0) || inFinder(0, 14) || inFinder(14, 0)) {
            const localRow = row % 14;
            const localColumn = column % 14;
            const ring = Math.max(
                Math.abs(3 - (localRow > 6 ? localRow - 14 : localRow)),
                Math.abs(
                    3 - (localColumn > 6 ? localColumn - 14 : localColumn),
                ),
            );

            return ring !== 2;
        }

        seed = (seed * 9301 + 49297) % 233280;

        return seed / 233280 > 0.5;
    });
});

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
    <Head :title="`${brand.name} — Beranda`" />

    <div class="space-y-6 px-5 py-5">
        <!-- Stamp card -->
        <section
            class="relative overflow-hidden rounded-3xl bg-gradient-to-br from-slate-900 via-slate-900 to-cyan-900 p-5 text-white shadow-lg shadow-slate-900/20"
        >
            <div
                class="absolute -top-16 -right-12 h-44 w-44 rounded-full bg-cyan-500/20 blur-3xl"
            ></div>

            <div class="relative">
                <div class="flex items-start justify-between">
                    <div>
                        <p
                            class="text-[11px] tracking-wider text-cyan-200/70 uppercase"
                        >
                            {{ brand.name }}
                        </p>
                        <p class="mt-1 text-lg font-bold">Kartu Stempel</p>
                    </div>
                    <button
                        v-if="isMemberCardEnabled"
                        type="button"
                        class="flex flex-col items-center gap-1 rounded-2xl bg-white/10 px-3 py-2.5 ring-1 ring-white/20 transition hover:bg-white/15"
                        @click="isQrOpen = true"
                    >
                        <QrCode class="h-6 w-6" />
                        <span class="text-[10px] font-medium">Scan</span>
                    </button>
                </div>

                <div class="mt-5 flex items-end justify-between">
                    <div>
                        <p class="text-xs text-slate-400">Stempel kamu</p>
                        <p
                            class="text-4xl font-bold tracking-tight tabular-nums"
                        >
                            {{ member.stamps }}
                            <span class="text-lg font-medium text-slate-400">
                                / {{ brand.stampTarget }}
                            </span>
                        </p>
                    </div>
                    <p
                        v-if="stampsToReward > 0"
                        class="max-w-[9rem] text-right text-[11px] text-cyan-200"
                    >
                        {{ stampsToReward }} stempel lagi untuk
                        {{ brand.stampReward }}
                    </p>
                    <p
                        v-else
                        class="max-w-[9rem] text-right text-[11px] font-semibold text-emerald-300"
                    >
                        Reward siap ditukar di kasir!
                    </p>
                </div>

                <div class="mt-5">
                    <StampProgress
                        :stamps="member.stamps"
                        :target="brand.stampTarget"
                        compact
                    />
                </div>

                <div
                    class="mt-4 flex items-center justify-between border-t border-white/10 pt-3 text-[11px]"
                >
                    <span class="font-medium tracking-widest">
                        {{ member.memberId }}
                    </span>
                    <span class="text-slate-400">
                        Bergabung {{ member.joinedAt }}
                    </span>
                </div>
            </div>
        </section>

        <!-- Summary -->
        <section class="grid grid-cols-3 gap-2">
            <div
                class="rounded-2xl border border-slate-200 bg-white p-3 text-center"
            >
                <p class="text-lg font-semibold text-slate-900 tabular-nums">
                    {{ member.visits }}
                </p>
                <p class="text-[11px] text-slate-500">Kunjungan</p>
            </div>
            <div
                class="rounded-2xl border border-slate-200 bg-white p-3 text-center"
            >
                <p class="text-lg font-semibold text-slate-900 tabular-nums">
                    {{ formatNumber(member.lifetimeStamps) }}
                </p>
                <p class="text-[11px] text-slate-500">Total stempel</p>
            </div>
            <div
                class="rounded-2xl border border-slate-200 bg-white p-3 text-center"
            >
                <p class="text-lg font-semibold text-slate-900 tabular-nums">
                    {{ member.rewardsClaimed }}
                </p>
                <p class="text-[11px] text-slate-500">Reward diklaim</p>
            </div>
        </section>

        <!-- Full stamp card -->
        <section class="rounded-2xl border border-slate-200 bg-white p-5">
            <div class="flex items-start justify-between">
                <div>
                    <h2 class="text-sm font-semibold text-slate-900">
                        Kartu cuci gratis
                    </h2>
                    <p class="mt-0.5 text-xs text-slate-500">
                        {{ stampsToReward }} cuci lagi untuk
                        {{ brand.stampReward.toLowerCase() }}
                    </p>
                </div>
                <span
                    class="rounded-full bg-cyan-50 px-2.5 py-1 text-xs font-semibold text-cyan-700"
                >
                    {{ member.stamps }}/{{ brand.stampTarget }}
                </span>
            </div>

            <div class="mt-4">
                <StampProgress
                    :stamps="member.stamps"
                    :target="brand.stampTarget"
                />
            </div>
        </section>

        <!-- Promos -->
        <section>
            <div class="flex items-center justify-between">
                <h2 class="text-sm font-semibold text-slate-900">
                    Promo untukmu
                </h2>
                <span class="text-[11px] text-slate-400">Geser →</span>
            </div>
            <div
                class="-mx-5 mt-3 snap-x snap-mandatory scroll-px-5 [scrollbar-width:none] overflow-x-auto px-5 pb-1 [&::-webkit-scrollbar]:hidden"
            >
                <div class="flex w-max gap-3">
                    <article
                        v-for="promo in promos"
                        :key="promo.id"
                        class="relative w-64 shrink-0 snap-start overflow-hidden rounded-2xl p-4 text-white shadow-lg"
                        :style="{
                            background: `linear-gradient(135deg, ${promo.gradFrom}, ${promo.gradTo})`,
                        }"
                    >
                        <div
                            class="absolute -top-8 -right-6 h-24 w-24 rounded-full bg-white/15 blur-xl"
                        ></div>
                        <div class="relative">
                            <div class="flex items-center justify-between">
                                <span class="text-2xl">{{ promo.icon }}</span>
                                <span
                                    class="rounded-full bg-white/20 px-2 py-0.5 text-[10px] font-semibold ring-1 ring-white/25"
                                >
                                    {{ promo.badge }}
                                </span>
                            </div>
                            <p class="mt-3 text-sm font-semibold">
                                {{ promo.title }}
                            </p>
                            <p class="mt-1 text-[11px] text-white/85">
                                {{ promo.description }}
                            </p>
                        </div>
                    </article>
                </div>
            </div>
        </section>

        <!-- Unlocked rewards -->
        <section v-if="unlockedRewards.length > 0">
            <div class="flex items-center justify-between">
                <h2 class="text-sm font-semibold text-slate-900">
                    Reward yang sudah terbuka
                </h2>
                <Link
                    :href="memberRoutes.rewards.url()"
                    class="flex items-center gap-0.5 text-[11px] font-medium text-cyan-700"
                >
                    Lihat semua
                    <ChevronRight class="h-3.5 w-3.5" />
                </Link>
            </div>
            <ul class="mt-3 space-y-2">
                <li
                    v-for="reward in unlockedRewards.slice(0, 3)"
                    :key="reward.id"
                    class="flex items-center gap-3 rounded-2xl border border-slate-200 bg-white p-3"
                >
                    <div
                        class="flex h-11 w-11 items-center justify-center rounded-xl bg-gradient-to-br from-cyan-50 to-sky-100 text-xl"
                    >
                        {{ reward.icon }}
                    </div>
                    <div class="min-w-0 flex-1">
                        <p class="truncate text-sm font-medium text-slate-800">
                            {{ reward.name }}
                        </p>
                        <p
                            class="flex items-center gap-1 text-[11px] font-medium text-cyan-700"
                        >
                            <Sparkles class="h-3 w-3" />
                            {{ reward.requiredStamps }} stempel
                        </p>
                    </div>
                    <span
                        class="rounded-full bg-emerald-50 px-2 py-1 text-[10px] font-medium text-emerald-700"
                    >
                        Siap ditukar
                    </span>
                </li>
            </ul>
            <p class="mt-2 text-[11px] text-slate-400">
                Penukaran reward dilakukan oleh kasir saat kamu berkunjung.
            </p>
        </section>

        <!-- Recent activity -->
        <section>
            <div class="flex items-center justify-between">
                <h2 class="text-sm font-semibold text-slate-900">
                    Aktivitas terakhir
                </h2>
                <Link
                    :href="memberRoutes.stamps.url()"
                    class="flex items-center gap-0.5 text-[11px] font-medium text-cyan-700"
                >
                    Semua
                    <ChevronRight class="h-3.5 w-3.5" />
                </Link>
            </div>
            <ul
                class="mt-3 divide-y divide-slate-100 overflow-hidden rounded-2xl border border-slate-200 bg-white"
            >
                <li
                    v-for="activity in stampHistory.slice(0, 3)"
                    :key="activity.id"
                    class="flex items-center gap-3 p-3.5"
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
                            {{ activity.date }}
                        </p>
                    </div>
                    <p
                        class="text-sm font-semibold tabular-nums"
                        :class="activityToneClass(activity.type)"
                    >
                        {{ activity.stamps > 0 ? '+' : ''
                        }}{{ activity.stamps }}
                    </p>
                </li>
            </ul>
        </section>

        <!-- Last visit -->
        <section v-if="washHistory.length > 0">
            <h2 class="text-sm font-semibold text-slate-900">
                Kunjungan terakhir
            </h2>
            <div class="mt-3 rounded-2xl border border-slate-200 bg-white p-4">
                <div class="flex items-start gap-3">
                    <div
                        class="flex h-10 w-10 items-center justify-center rounded-xl bg-slate-50 text-slate-500"
                    >
                        <Car class="h-5 w-5" />
                    </div>
                    <div class="min-w-0 flex-1">
                        <p class="text-sm font-semibold text-slate-900">
                            {{ washHistory[0].service }}
                        </p>
                        <p class="mt-0.5 text-[11px] text-slate-500">
                            {{ washHistory[0].vehicle }}
                        </p>
                    </div>
                    <div class="text-right">
                        <p
                            class="text-sm font-semibold text-slate-900 tabular-nums"
                        >
                            {{ formatCurrency(washHistory[0].total) }}
                        </p>
                        <p
                            class="flex items-center justify-end gap-1 text-[11px] text-slate-400"
                        >
                            <Clock class="h-3 w-3" />
                            {{ washHistory[0].date }}
                        </p>
                    </div>
                </div>
            </div>
        </section>
    </div>

    <!-- QR modal -->
    <div
        v-if="isMemberCardEnabled && isQrOpen"
        class="fixed inset-0 z-50 flex items-center justify-center bg-slate-950/60 p-6 backdrop-blur-sm"
        @click.self="isQrOpen = false"
    >
        <div class="w-full max-w-xs rounded-3xl bg-white p-6 text-center">
            <div class="flex items-center justify-between">
                <p class="text-sm font-semibold text-slate-900">
                    Kartu member digital
                </p>
                <button
                    type="button"
                    class="rounded-lg p-1 text-slate-400 hover:bg-slate-100"
                    aria-label="Tutup"
                    @click="isQrOpen = false"
                >
                    <X class="h-4 w-4" />
                </button>
            </div>

            <div
                class="mx-auto mt-5 grid w-48 gap-px rounded-xl bg-white p-3 ring-1 ring-slate-200"
                :style="{ gridTemplateColumns: 'repeat(21, minmax(0, 1fr))' }"
            >
                <span
                    v-for="(filled, index) in qrCells"
                    :key="index"
                    class="aspect-square"
                    :class="filled ? 'bg-slate-900' : 'bg-white'"
                ></span>
            </div>

            <p class="mt-4 text-xs text-slate-500">
                Tunjukkan kode ini ke kasir untuk mengumpulkan stempel
            </p>
            <p class="mt-1 font-mono text-sm tracking-widest text-slate-900">
                {{ member.memberId }}
            </p>
            <div
                class="mt-4 flex items-center justify-center gap-1.5 rounded-xl bg-cyan-50 py-2.5 text-sm font-semibold text-cyan-800"
            >
                <Gift class="h-4 w-4" />
                {{ member.stamps }}/{{ brand.stampTarget }} stempel
            </div>
        </div>
    </div>
</template>
