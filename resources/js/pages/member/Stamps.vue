<script setup lang="ts">
import { Head } from '@inertiajs/vue3';
import { Car, ChevronDown, ReceiptText, Star } from '@lucide/vue';
import { computed, ref } from 'vue';
import StatusPill from '@/components/demo/StatusPill.vue';
import { formatCurrency, formatNumber } from '@/composables/useCarwashFormat';
import type {
    CarwashBrand,
    CarwashMember,
    CarwashReward,
    CarwashStampEntry,
    CarwashWashEntry,
} from '@/types/demo';

const props = defineProps<{
    mode: 'demo' | 'live';
    brand: CarwashBrand;
    member: CarwashMember;
    stampHistory: CarwashStampEntry[];
    washHistory: CarwashWashEntry[];
    rewards: CarwashReward[];
}>();

const activeTab = ref<'orders' | 'stamps'>('orders');
const expandedOrderId = ref<number | null>(null);

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
    <Head :title="`${brand.name} — Riwayat`" />

    <div class="space-y-5 px-5 py-5">
        <!-- Tabs -->
        <div class="flex gap-1 rounded-xl bg-slate-200/70 p-1 text-sm">
            <button
                type="button"
                class="flex-1 rounded-lg py-2 font-medium transition"
                :class="
                    activeTab === 'orders'
                        ? 'bg-white text-slate-900 shadow-sm'
                        : 'text-slate-500'
                "
                @click="activeTab = 'orders'"
            >
                Riwayat Order
            </button>
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
                Mutasi Stempel
            </button>
        </div>

        <!-- Current balance -->
        <section
            v-if="activeTab === 'stamps'"
            class="rounded-2xl bg-gradient-to-br from-slate-900 to-cyan-900 p-5 text-white"
        >
            <p class="text-xs text-slate-300">Stempel tersedia</p>
            <p class="mt-1 text-3xl font-semibold tracking-tight tabular-nums">
                {{ member.stamps }}
            </p>
        </section>

        <!-- Next reward -->
        <section
            v-if="activeTab === 'stamps' && nextReward"
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
        <section v-if="activeTab === 'stamps'" class="grid grid-cols-2 gap-2">
            <div
                class="rounded-2xl border border-slate-200 bg-white p-3 text-center"
            >
                <p class="text-lg font-semibold text-emerald-600 tabular-nums">
                    {{ formatNumber(member.lifetimeStamps) }}
                </p>
                <p class="text-[11px] text-slate-500">Total dikumpulkan</p>
            </div>
            <div
                class="rounded-2xl border border-slate-200 bg-white p-3 text-center"
            >
                <p class="text-lg font-semibold text-cyan-600 tabular-nums">
                    {{ totalRedeemed > 0 ? `−${totalRedeemed}` : '0' }}
                </p>
                <p class="text-[11px] text-slate-500">Ditukar</p>
            </div>
        </section>

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

        <ul
            v-if="activeTab === 'orders' && washHistory.length > 0"
            class="space-y-3"
        >
            <li
                v-for="visit in washHistory"
                :key="visit.id"
                class="overflow-hidden rounded-2xl border border-slate-200 bg-white"
            >
                <button
                    type="button"
                    class="w-full p-4 text-left"
                    :aria-expanded="expandedOrderId === visit.id"
                    @click="
                        expandedOrderId =
                            expandedOrderId === visit.id ? null : visit.id
                    "
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
                        <div
                            v-if="visit.rating > 0"
                            class="flex items-center gap-1"
                        >
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
                        <div class="ml-auto flex items-center gap-2 text-right">
                            <p
                                class="text-sm font-semibold text-slate-900 tabular-nums"
                            >
                                {{ formatCurrency(visit.total) }}
                            </p>
                            <ChevronDown
                                class="h-4 w-4 text-slate-400 transition-transform"
                                :class="
                                    expandedOrderId === visit.id
                                        ? 'rotate-180'
                                        : ''
                                "
                            />
                        </div>
                    </div>
                </button>
                <div
                    v-if="expandedOrderId === visit.id"
                    class="space-y-4 border-t border-slate-100 px-4 py-4 text-sm"
                >
                    <div>
                        <h3 class="font-semibold text-slate-900">
                            Detail order
                        </h3>
                        <p
                            v-if="visit.number"
                            class="mt-1 text-xs text-slate-500"
                        >
                            No. order {{ visit.number }}
                        </p>
                        <p v-if="visit.invoice" class="text-xs text-slate-500">
                            Invoice {{ visit.invoice }}
                        </p>
                        <p class="mt-1 text-xs text-slate-500">
                            {{ visit.vehicleName || visit.vehicle }} ·
                            {{ visit.date }}
                        </p>
                    </div>
                    <div
                        v-if="visit.items?.length"
                        class="space-y-2 border-t border-slate-100 pt-3"
                    >
                        <div
                            v-for="(item, index) in visit.items"
                            :key="index"
                            class="flex justify-between gap-3"
                        >
                            <span
                                >{{ item.name
                                }}<span v-if="item.quantity > 1">
                                    × {{ item.quantity }}</span
                                ></span
                            >
                            <span class="shrink-0 tabular-nums">{{
                                formatCurrency(item.total)
                            }}</span>
                        </div>
                    </div>
                    <div class="space-y-1 border-t border-slate-100 pt-3">
                        <div
                            v-if="visit.subtotal !== undefined"
                            class="flex justify-between"
                        >
                            <span>Subtotal</span
                            ><span>{{ formatCurrency(visit.subtotal) }}</span>
                        </div>
                        <div v-if="visit.discount" class="flex justify-between">
                            <span>Diskon</span
                            ><span>−{{ formatCurrency(visit.discount) }}</span>
                        </div>
                        <div class="flex justify-between font-semibold">
                            <span>Total</span
                            ><span>{{ formatCurrency(visit.total) }}</span>
                        </div>
                        <div
                            v-if="visit.paidAmount !== undefined"
                            class="flex justify-between"
                        >
                            <span>Sudah dibayar</span
                            ><span>{{ formatCurrency(visit.paidAmount) }}</span>
                        </div>
                        <div
                            v-if="
                                visit.paidAmount !== undefined &&
                                visit.total > visit.paidAmount
                            "
                            class="flex justify-between text-amber-700"
                        >
                            <span>Sisa pembayaran</span
                            ><span>{{
                                formatCurrency(visit.total - visit.paidAmount)
                            }}</span>
                        </div>
                    </div>
                    <div class="border-t border-slate-100 pt-3">
                        <h3 class="font-semibold text-slate-900">Transaksi</h3>
                        <p
                            v-if="!visit.transactions?.length"
                            class="mt-2 text-xs text-slate-500"
                        >
                            Belum ada transaksi pembayaran.
                        </p>
                        <div
                            v-for="transaction in visit.transactions"
                            :key="transaction.reference"
                            class="mt-3 rounded-xl bg-slate-50 p-3"
                        >
                            <div class="flex justify-between gap-2 font-medium">
                                <span>{{ transaction.type }}</span
                                ><span>{{
                                    formatCurrency(transaction.amount)
                                }}</span>
                            </div>
                            <p class="mt-1 text-xs text-slate-500">
                                {{ transaction.reference }} ·
                                {{ transaction.date }}
                            </p>
                            <p class="text-xs text-slate-500">
                                {{ transaction.channels }}
                            </p>
                            <a
                                v-if="transaction.receiptUrl"
                                :href="transaction.receiptUrl"
                                target="_blank"
                                rel="noopener noreferrer"
                                class="mt-3 inline-flex items-center gap-2 rounded-lg bg-cyan-600 px-3 py-2 text-xs font-semibold text-white"
                            >
                                <ReceiptText class="h-4 w-4" /> Lihat / unduh
                                struk
                            </a>
                            <p v-else class="mt-2 text-xs text-slate-400">
                                Struk hanya tersedia untuk transaksi sebenarnya.
                            </p>
                        </div>
                    </div>
                </div>
            </li>
        </ul>
        <p
            v-if="activeTab === 'orders' && washHistory.length === 0"
            class="rounded-2xl border border-slate-200 bg-white p-5 text-center text-sm text-slate-500"
        >
            Belum ada riwayat order.
        </p>
    </div>
</template>
