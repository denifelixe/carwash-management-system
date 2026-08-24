<script setup lang="ts">
import { Head } from '@inertiajs/vue3';
import { CircleCheck, Lock, Sparkles, Ticket } from '@lucide/vue';
import { computed, ref } from 'vue';
import StampProgress from '@/components/demo/StampProgress.vue';
import type {
    CarwashBrand,
    CarwashMember,
    CarwashReward,
    CarwashVoucher,
} from '@/types/demo';

const props = defineProps<{
    brand: CarwashBrand;
    member: CarwashMember;
    rewards: CarwashReward[];
    categories: string[];
    vouchers: CarwashVoucher[];
}>();

const activeCategory = ref<string>('Semua');

const filterOptions = computed<string[]>(() => ['Semua', ...props.categories]);

/** Only active rewards are shown to customers (BR-04). */
const availableRewards = computed<CarwashReward[]>(() =>
    props.rewards.filter((reward) => reward.status === 'aktif'),
);

const visibleRewards = computed<CarwashReward[]>(() =>
    activeCategory.value === 'Semua'
        ? availableRewards.value
        : availableRewards.value.filter(
              (reward) => reward.category === activeCategory.value,
          ),
);

const unlockedCount = computed<number>(
    () =>
        availableRewards.value.filter(
            (reward) => reward.requiredStamps <= props.member.stamps,
        ).length,
);

function isUnlocked(reward: CarwashReward): boolean {
    return reward.requiredStamps <= props.member.stamps;
}

function progressFor(reward: CarwashReward): number {
    return Math.min(
        Math.round((props.member.stamps / reward.requiredStamps) * 100),
        100,
    );
}
</script>

<template>
    <Head :title="`${brand.name} — Reward`" />

    <div class="space-y-5 px-5 py-5">
        <!-- Balance header -->
        <section
            class="rounded-2xl bg-gradient-to-br from-slate-900 to-cyan-900 p-5 text-white"
        >
            <p class="text-xs text-slate-300">Stempel tersedia</p>
            <p class="mt-1 text-3xl font-semibold tracking-tight tabular-nums">
                {{ member.stamps }}
            </p>
            <p class="mt-1 text-[11px] text-slate-400">
                {{ unlockedCount }} dari {{ availableRewards.length }} reward
                sudah bisa kamu klaim
            </p>
            <div class="mt-4">
                <StampProgress
                    :stamps="member.stamps"
                    :target="brand.stampTarget"
                    compact
                />
            </div>
        </section>

        <!-- Claimed vouchers -->
        <section v-if="vouchers.length > 0">
            <h2 class="text-sm font-semibold text-slate-900">
                Voucher saya ({{ vouchers.length }})
            </h2>
            <ul class="mt-3 space-y-2">
                <li
                    v-for="voucher in vouchers"
                    :key="voucher.id"
                    class="flex items-center gap-3 rounded-2xl border border-dashed p-3.5"
                    :class="
                        voucher.status === 'aktif'
                            ? 'border-cyan-300 bg-cyan-50/60'
                            : 'border-slate-200 bg-slate-50 opacity-70'
                    "
                >
                    <div
                        class="flex h-11 w-11 items-center justify-center rounded-xl bg-white text-xl shadow-sm"
                    >
                        {{ voucher.icon }}
                    </div>
                    <div class="min-w-0 flex-1">
                        <p class="truncate text-sm font-medium text-slate-800">
                            {{ voucher.name }}
                        </p>
                        <p class="text-[11px] text-slate-500">
                            {{ voucher.code }} • {{ voucher.expiresAt }}
                        </p>
                    </div>
                    <Ticket
                        class="h-5 w-5 shrink-0"
                        :class="
                            voucher.status === 'aktif'
                                ? 'text-cyan-500'
                                : 'text-slate-300'
                        "
                    />
                </li>
            </ul>
        </section>

        <!-- Catalog -->
        <section>
            <h2 class="text-sm font-semibold text-slate-900">Katalog reward</h2>
            <p class="mt-0.5 text-xs text-slate-500">
                Tukarkan stempel kamu di kasir saat berkunjung.
            </p>

            <div class="mt-3 flex flex-wrap gap-2">
                <button
                    v-for="category in filterOptions"
                    :key="category"
                    type="button"
                    class="rounded-full px-3 py-1.5 text-xs font-medium transition"
                    :class="
                        activeCategory === category
                            ? 'bg-slate-900 text-white'
                            : 'bg-white text-slate-600 ring-1 ring-slate-200'
                    "
                    @click="activeCategory = category"
                >
                    {{ category }}
                </button>
            </div>

            <ul class="mt-3 space-y-3">
                <li
                    v-for="reward in visibleRewards"
                    :key="reward.id"
                    class="rounded-2xl border bg-white p-4"
                    :class="
                        isUnlocked(reward)
                            ? 'border-emerald-200 ring-1 ring-emerald-100'
                            : 'border-slate-200'
                    "
                >
                    <div class="flex items-start gap-3">
                        <div
                            class="flex h-12 w-12 shrink-0 items-center justify-center rounded-xl bg-gradient-to-br from-cyan-50 to-sky-100 text-2xl"
                        >
                            {{ reward.icon }}
                        </div>
                        <div class="min-w-0 flex-1">
                            <div class="flex items-start justify-between gap-2">
                                <p class="text-sm font-semibold text-slate-900">
                                    {{ reward.name }}
                                </p>
                                <span
                                    class="shrink-0 rounded-full px-2 py-0.5 text-[10px] font-medium"
                                    :class="
                                        isUnlocked(reward)
                                            ? 'bg-emerald-50 text-emerald-700'
                                            : 'bg-slate-100 text-slate-500'
                                    "
                                >
                                    {{ reward.category }}
                                </span>
                            </div>
                            <p class="mt-0.5 text-xs text-slate-500">
                                {{ reward.description }}
                            </p>
                        </div>
                    </div>

                    <!-- Progress toward the requirement -->
                    <div v-if="!isUnlocked(reward)" class="mt-3">
                        <div
                            class="h-1.5 overflow-hidden rounded-full bg-slate-100"
                        >
                            <div
                                class="h-full rounded-full bg-gradient-to-r from-cyan-500 to-sky-500"
                                :style="{ width: `${progressFor(reward)}%` }"
                            ></div>
                        </div>
                        <p class="mt-1 text-[11px] text-slate-400">
                            Kurang
                            {{ reward.requiredStamps - member.stamps }} stempel
                            lagi
                        </p>
                    </div>

                    <div
                        class="mt-3 flex items-center justify-between border-t border-slate-100 pt-3"
                    >
                        <p
                            class="flex items-center gap-1 text-sm font-semibold text-cyan-700 tabular-nums"
                        >
                            <Sparkles class="h-4 w-4" />
                            {{ reward.requiredStamps }} stempel
                        </p>
                        <span
                            v-if="isUnlocked(reward)"
                            class="flex items-center gap-1 rounded-xl bg-emerald-50 px-3 py-1.5 text-xs font-semibold text-emerald-700"
                        >
                            <CircleCheck class="h-3.5 w-3.5" />
                            Siap ditukar
                        </span>
                        <span
                            v-else
                            class="flex items-center gap-1 rounded-xl bg-slate-100 px-3 py-1.5 text-xs font-medium text-slate-400"
                        >
                            <Lock class="h-3.5 w-3.5" />
                            Belum terbuka
                        </span>
                    </div>
                </li>
            </ul>
        </section>

        <p class="text-center text-[11px] text-slate-400">
            Penukaran reward diproses oleh kasir. Tunjukkan kartu member digital
            kamu untuk klaim.
        </p>
    </div>
</template>
