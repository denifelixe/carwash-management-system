<script setup lang="ts">
import { Head, router, useForm } from '@inertiajs/vue3';
import {
    ChevronDown,
    CircleCheck,
    ChevronLeft,
    ChevronRight,
    Gift,
    Pencil,
    Plus,
    Search,
    Sparkles,
    Trash2,
    Users,
} from '@lucide/vue';
import { onClickOutside } from '@vueuse/core';
import { computed, ref, watch } from 'vue';
import {
    destroy as destroyReward,
    index as indexRewards,
    store as storeReward,
    update as updateReward,
    updateStatus as updateRewardStatus,
} from '@/actions/App/Http/Controllers/Admin/RewardController';
import DataPagination from '@/components/demo/DataPagination.vue';
import DataToolbar from '@/components/demo/DataToolbar.vue';
import EmptyState from '@/components/demo/EmptyState.vue';
import ModalDialog from '@/components/demo/ModalDialog.vue';
import StatCard from '@/components/demo/StatCard.vue';
import StatusPill from '@/components/demo/StatusPill.vue';
import { formatCurrency, formatNumber } from '@/composables/useCarwashFormat';
import admin from '@/routes/demo/admin';
import type {
    CarwashBrand,
    CarwashPaginated,
    CarwashReward,
    CarwashRewardFilters,
    CarwashRewardRedemption,
    CarwashRewardServiceOption,
    CarwashRewardStats,
} from '@/types/demo';

const props = defineProps<{
    mode: 'demo' | 'live';
    brand: CarwashBrand;
    rewards: CarwashReward[];
    redemptions: CarwashPaginated<CarwashRewardRedemption>;
    stats: CarwashRewardStats;
    stampBalances: number[];
    categories: string[];
    serviceOptions: CarwashRewardServiceOption[];
    filters: CarwashRewardFilters;
    capabilities: { create: boolean; update: boolean; delete: boolean };
}>();

/**
 * The demo mutates this copy in place; live re-reads it from the reloaded
 * props, the same split admin/Inventory.vue uses.
 */
const rewardList = ref<CarwashReward[]>(props.rewards.map(cloneReward));

const search = ref<string>('');
const editingId = ref<number | null>(null);
const isFormOpen = ref<boolean>(false);
const isIconPickerOpen = ref<boolean>(false);
const iconPicker = ref<HTMLElement | null>(null);
const pendingDelete = ref<CarwashReward | null>(null);
const serviceSearch = ref('');
const activeGroup = ref<string | null>(null);
const activeCategory = ref<string | null>(null);
const activeServiceId = ref<number | null>(null);

const rewardIconOptions = [
    { icon: '🎁', label: 'Hadiah' },
    { icon: '🚗', label: 'Mobil' },
    { icon: '🚙', label: 'SUV' },
    { icon: '🏍️', label: 'Motor' },
    { icon: '🚿', label: 'Cuci' },
    { icon: '🫧', label: 'Busa' },
    { icon: '🧽', label: 'Spons' },
    { icon: '🪣', label: 'Ember' },
    { icon: '💧', label: 'Air' },
    { icon: '✨', label: 'Kilap' },
    { icon: '🛡️', label: 'Proteksi' },
    { icon: '🧴', label: 'Cairan' },
    { icon: '🧼', label: 'Sabun' },
    { icon: '🛞', label: 'Ban' },
    { icon: '🔧', label: 'Perawatan' },
    { icon: '🏆', label: 'Premium' },
];

onClickOutside(iconPicker, () => {
    isIconPickerOpen.value = false;
});

const draft = ref(emptyDraft());

const rewardForm = useForm({
    name: '',
    description: '' as string | null,
    icon: '🎁',
    category: '',
    required_stamps: 5,
    stock: 20,
    is_active: true,
    variation_discounts: [] as Array<{
        service_variation_id: number;
        quantity: number;
        discount_percent: number;
    }>,
});
const statusForm = useForm({ is_active: true });
const deleteForm = useForm({});

watch(
    () => props.rewards,
    (rewards) => {
        if (props.mode === 'live') {
            rewardList.value = rewards.map(cloneReward);
        }
    },
);

function cloneReward(reward: CarwashReward): CarwashReward {
    return {
        ...reward,
        applicableVariations: reward.applicableVariations.map((variation) => ({
            ...variation,
        })),
    };
}

function emptyDraft() {
    return {
        name: '',
        description: '',
        requiredStamps: 5,
        icon: '🎁',
        category: 'Reward',
        status: 'aktif',
        stock: 20,
        applicableVariations: [] as CarwashReward['applicableVariations'],
    };
}

const filteredRewards = computed<CarwashReward[]>(() => {
    const query = search.value.trim().toLowerCase();

    return rewardList.value.filter(
        (reward) => query === '' || reward.name.toLowerCase().includes(query),
    );
});

const activeCount = computed<number>(() =>
    props.mode === 'live'
        ? props.stats.active
        : rewardList.value.filter((reward) => reward.status === 'aktif').length,
);

const totalRedeemed = computed<number>(() =>
    props.mode === 'live'
        ? props.stats.redeemed
        : rewardList.value.reduce(
              (total, reward) => total + reward.redeemed,
              0,
          ),
);

/** The cheapest active reward sets the bar for "can redeem something now". */
const cheapestActiveStamps = computed<number | null>(() => {
    const requirements = rewardList.value
        .filter((reward) => reward.status === 'aktif')
        .map((reward) => reward.requiredStamps);

    return requirements.length > 0 ? Math.min(...requirements) : null;
});

const eligibleMembers = computed<number>(() =>
    cheapestActiveStamps.value === null
        ? 0
        : eligibleCountFor(cheapestActiveStamps.value),
);

const availableServices = computed(() =>
    props.serviceOptions.filter(
        (service) =>
            service.isActive &&
            service.variations.some((variation) => variation.isActive),
    ),
);
const serviceGroups = computed(() => [
    ...new Set(availableServices.value.map((service) => service.categoryGroup)),
]);
const serviceCategories = computed(() => [
    ...new Set(
        availableServices.value
            .filter((service) => service.categoryGroup === activeGroup.value)
            .map((service) => service.category),
    ),
]);
const visibleServices = computed(() => {
    const query = serviceSearch.value.trim().toLowerCase();

    return availableServices.value.filter((service) =>
        query !== ''
            ? [
                  service.name,
                  service.category,
                  service.categoryGroup,
                  ...service.variations.map((variation) => variation.label),
              ].some((label) => label.toLowerCase().includes(query))
            : service.categoryGroup === activeGroup.value &&
              service.category === activeCategory.value,
    );
});
const activeService = computed(
    () =>
        props.serviceOptions.find(
            (service) => service.id === activeServiceId.value,
        ) ?? null,
);

function variationDetails(serviceVariationId: number): {
    name: string;
    price: number;
    isActive: boolean;
} {
    for (const service of props.serviceOptions) {
        const variation = service.variations.find(
            (candidate) => candidate.id === serviceVariationId,
        );

        if (variation) {
            return {
                name: variation.label
                    ? `${service.name} (${variation.label})`
                    : service.name,
                price: variation.price,
                isActive: service.isActive && variation.isActive,
            };
        }
    }

    return { name: 'Variasi tidak tersedia', price: 0, isActive: false };
}

/** How many members currently hold enough stamps (BR-13). */
function eligibleCountFor(requiredStamps: number): number {
    return props.stampBalances.filter((stamps) => stamps >= requiredStamps)
        .length;
}

function servicesLabel(reward: CarwashReward): string {
    if (reward.applicableVariations.length === 0) {
        return 'Merchandise · tanpa potongan';
    }

    return reward.applicableVariations
        .map(
            (variation) =>
                `${variationDetails(variation.serviceVariationId).name} ×${variation.quantity} · ${variation.discountPercent}%`,
        )
        .join(', ');
}

const canSave = computed<boolean>(
    () =>
        draft.value.name.trim() !== '' &&
        draft.value.icon.trim() !== '' &&
        draft.value.requiredStamps > 0 &&
        draft.value.stock >= 0 &&
        draft.value.applicableVariations.every(
            (variation) =>
                Number.isInteger(variation.quantity) &&
                variation.quantity >= 1 &&
                variation.quantity <= 1000 &&
                Number.isInteger(variation.discountPercent) &&
                variation.discountPercent >= 1 &&
                variation.discountPercent <= 100,
        ),
);

function addVariation(serviceVariationId: number): void {
    if (
        draft.value.applicableVariations.some(
            (variation) => variation.serviceVariationId === serviceVariationId,
        )
    ) {
        return;
    }

    draft.value.applicableVariations.push({
        serviceVariationId,
        quantity: 1,
        discountPercent: 100,
    });
}

function removeVariation(serviceVariationId: number): void {
    draft.value.applicableVariations = draft.value.applicableVariations.filter(
        (variation) => variation.serviceVariationId !== serviceVariationId,
    );
}

function resetServicePicker(): void {
    serviceSearch.value = '';
    activeGroup.value = null;
    activeCategory.value = null;
    activeServiceId.value = null;
}

function selectRewardIcon(icon: string): void {
    draft.value.icon = icon;
    isIconPickerOpen.value = false;
}

function openCreate(): void {
    if (!props.capabilities.create) {
        return;
    }

    editingId.value = null;
    draft.value = emptyDraft();
    resetServicePicker();
    isIconPickerOpen.value = false;
    rewardForm.clearErrors();
    isFormOpen.value = true;
}

function openEdit(reward: CarwashReward): void {
    if (!props.capabilities.update) {
        return;
    }

    editingId.value = reward.id;
    isIconPickerOpen.value = false;
    resetServicePicker();
    draft.value = {
        name: reward.name,
        description: reward.description,
        requiredStamps: reward.requiredStamps,
        icon: reward.icon,
        category: reward.category,
        status: reward.status,
        stock: reward.stock,
        applicableVariations: reward.applicableVariations.map((variation) => ({
            ...variation,
        })),
    };
    rewardForm.clearErrors();
    isFormOpen.value = true;
}

function saveReward(): void {
    const requiredCapability =
        editingId.value === null
            ? props.capabilities.create
            : props.capabilities.update;

    if (!requiredCapability || !canSave.value) {
        return;
    }

    if (props.mode === 'demo') {
        saveDemoReward();

        return;
    }

    rewardForm.name = draft.value.name;
    rewardForm.description = draft.value.description || null;
    rewardForm.icon = draft.value.icon;
    rewardForm.category = draft.value.category;
    rewardForm.required_stamps = draft.value.requiredStamps;
    rewardForm.stock = draft.value.stock;
    rewardForm.is_active = draft.value.status === 'aktif';
    rewardForm.variation_discounts = draft.value.applicableVariations.map(
        (variation) => ({
            service_variation_id: variation.serviceVariationId,
            quantity: variation.quantity,
            discount_percent: variation.discountPercent,
        }),
    );

    const action =
        editingId.value === null
            ? storeReward()
            : updateReward(editingId.value);

    rewardForm.submit(action, {
        preserveScroll: true,
        onSuccess: () => {
            isFormOpen.value = false;
            editingId.value = null;
        },
    });
}

/** The demo keeps every change in this session's memory only. */
function saveDemoReward(): void {
    const { applicableVariations, ...fields } = draft.value;

    if (editingId.value !== null) {
        const existing = rewardList.value.find(
            (reward) => reward.id === editingId.value,
        );

        if (existing) {
            Object.assign(existing, {
                ...fields,
                applicableVariations: applicableVariations.map((variation) => ({
                    ...variation,
                })),
            });
        }
    } else {
        rewardList.value = [
            {
                id:
                    Math.max(
                        0,
                        ...rewardList.value.map((reward) => reward.id),
                    ) + 1,
                ...fields,
                applicableVariations: applicableVariations.map((variation) => ({
                    ...variation,
                })),
                redeemed: 0,
            },
            ...rewardList.value,
        ];
    }

    isFormOpen.value = false;
}

function toggleStatus(reward: CarwashReward): void {
    if (!props.capabilities.update) {
        return;
    }

    const isActive = reward.status !== 'aktif';

    if (props.mode === 'demo') {
        reward.status = isActive ? 'aktif' : 'nonaktif';

        return;
    }

    statusForm.is_active = isActive;
    statusForm.submit(updateRewardStatus(reward.id), { preserveScroll: true });
}

function openDelete(reward: CarwashReward): void {
    deleteForm.clearErrors();
    pendingDelete.value = reward;
}

function deleteReward(): void {
    const reward = pendingDelete.value;

    if (!reward || !props.capabilities.delete) {
        return;
    }

    if (props.mode === 'demo') {
        rewardList.value = rewardList.value.filter(
            (candidate) => candidate.id !== reward.id,
        );
        pendingDelete.value = null;

        return;
    }

    deleteForm.submit(destroyReward(reward.id), {
        preserveScroll: true,
        onSuccess: () => {
            pendingDelete.value = null;
        },
    });
}

function visitRedemptionPage(redemptionPage: number): void {
    router.get(
        props.mode === 'demo' ? admin.rewards.url() : indexRewards.url(),
        { redemptionPage },
        {
            preserveState: true,
            preserveScroll: true,
            replace: true,
            only: ['redemptions', 'filters'],
        },
    );
}
</script>

<template>
    <Head :title="`${brand.name} — Reward`" />

    <div class="space-y-4">
        <section class="grid grid-cols-1 gap-4 sm:grid-cols-2 xl:grid-cols-4">
            <StatCard
                label="Total reward"
                :value="String(rewardList.length)"
                :caption="`${activeCount} aktif`"
                :icon="Gift"
            />
            <StatCard
                label="Sudah ditukar"
                :value="formatNumber(totalRedeemed)"
                caption="sepanjang program"
                :icon="CircleCheck"
                tone="emerald"
            />
            <StatCard
                label="Stempel beredar"
                :value="formatNumber(stats.circulatingStamps)"
                caption="saldo stempel member yang belum ditukar"
                :icon="Sparkles"
                tone="amber"
            />
            <StatCard
                label="Customer memenuhi syarat"
                :value="String(eligibleMembers)"
                :caption="
                    cheapestActiveStamps === null
                        ? 'belum ada reward aktif'
                        : `punya minimal ${cheapestActiveStamps} stempel`
                "
                :icon="Users"
            />
        </section>

        <section
            class="rounded-2xl border border-slate-200/80 bg-white shadow-sm"
        >
            <div
                class="flex flex-wrap items-center justify-between gap-3 border-b border-slate-100 p-5"
            >
                <div>
                    <h3 class="text-sm font-semibold text-slate-900">
                        Katalog reward
                    </h3>
                    <p class="mt-0.5 text-xs text-slate-500">
                        Atur nama, deskripsi, dan syarat stempel
                    </p>
                </div>
                <div class="flex flex-wrap items-center gap-2">
                    <DataToolbar
                        v-model:search="search"
                        placeholder="Cari reward"
                    />
                    <button
                        v-if="capabilities.create"
                        type="button"
                        class="flex items-center gap-2 rounded-xl bg-gradient-to-r from-cyan-500 to-sky-600 px-3 py-2 text-sm font-medium text-white shadow-lg shadow-cyan-500/25 transition hover:from-cyan-600 hover:to-sky-700"
                        @click="openCreate"
                    >
                        <Plus class="h-4 w-4" />
                        Tambah Reward
                    </button>
                </div>
            </div>

            <div
                v-if="filteredRewards.length > 0"
                class="grid grid-cols-1 gap-3 p-5 sm:grid-cols-2 xl:grid-cols-3"
            >
                <article
                    v-for="reward in filteredRewards"
                    :key="reward.id"
                    class="group rounded-2xl border border-slate-200 p-4 transition hover:border-cyan-300 hover:shadow-lg hover:shadow-cyan-500/10"
                    :class="reward.status === 'nonaktif' ? 'opacity-60' : ''"
                >
                    <div class="flex items-start justify-between gap-2">
                        <div
                            class="flex h-11 w-11 items-center justify-center rounded-xl bg-gradient-to-br from-cyan-50 to-sky-100 text-xl"
                        >
                            {{ reward.icon }}
                        </div>
                        <div
                            v-if="capabilities.update"
                            class="flex items-center gap-2"
                        >
                            <span class="text-xs text-slate-600">{{
                                reward.status === 'aktif' ? 'Aktif' : 'Nonaktif'
                            }}</span>
                            <button
                                type="button"
                                role="switch"
                                :aria-label="`Status ${reward.name}`"
                                :aria-checked="reward.status === 'aktif'"
                                :disabled="statusForm.processing"
                                class="relative h-6 w-11 rounded-full transition-colors focus-visible:ring-2 focus-visible:ring-cyan-500 focus-visible:ring-offset-2 focus-visible:outline-none disabled:cursor-wait"
                                :class="
                                    reward.status === 'aktif'
                                        ? 'bg-cyan-600'
                                        : 'bg-slate-300'
                                "
                                @click="toggleStatus(reward)"
                            >
                                <span
                                    class="absolute top-0.5 left-0.5 h-5 w-5 rounded-full bg-white shadow-sm transition-transform"
                                    :class="
                                        reward.status === 'aktif'
                                            ? 'translate-x-5'
                                            : ''
                                    "
                                ></span>
                            </button>
                        </div>
                        <StatusPill v-else :status="reward.status" />
                    </div>

                    <p class="mt-3 text-sm font-semibold text-slate-900">
                        {{ reward.name }}
                    </p>
                    <p class="mt-1 line-clamp-2 text-xs text-slate-500">
                        {{ reward.description }}
                    </p>
                    <p class="mt-1.5 line-clamp-1 text-[11px] text-slate-400">
                        {{ servicesLabel(reward) }}
                    </p>

                    <div
                        class="mt-3 flex items-center justify-between border-t border-slate-100 pt-3"
                    >
                        <p
                            class="flex items-center gap-1 text-sm font-semibold text-cyan-700 tabular-nums"
                        >
                            <Sparkles class="h-3.5 w-3.5" />
                            {{ reward.requiredStamps }} stempel
                        </p>
                        <p
                            class="text-[11px]"
                            :class="
                                reward.stock === 0
                                    ? 'font-medium text-rose-600'
                                    : 'text-slate-400'
                            "
                        >
                            stok {{ reward.stock }}
                        </p>
                    </div>

                    <div
                        class="mt-2 flex items-center justify-between text-[11px]"
                    >
                        <span class="text-slate-500">
                            {{ eligibleCountFor(reward.requiredStamps) }}
                            customer memenuhi syarat
                        </span>
                        <span class="text-slate-400">
                            {{ reward.redeemed }}× ditukar
                        </span>
                    </div>

                    <div
                        v-if="capabilities.update || capabilities.delete"
                        class="mt-3 flex gap-1.5"
                    >
                        <button
                            v-if="capabilities.update"
                            type="button"
                            class="flex flex-1 items-center justify-center gap-1 rounded-lg border border-slate-200 py-1.5 text-[11px] font-medium text-slate-600 transition hover:bg-slate-50"
                            @click="openEdit(reward)"
                        >
                            <Pencil class="h-3 w-3" />
                            Edit
                        </button>
                        <button
                            v-if="capabilities.delete"
                            type="button"
                            class="rounded-lg border border-slate-200 px-2 py-1.5 text-slate-400 transition hover:border-rose-200 hover:bg-rose-50 hover:text-rose-500"
                            aria-label="Hapus reward"
                            @click="openDelete(reward)"
                        >
                            <Trash2 class="h-3 w-3" />
                        </button>
                    </div>
                </article>
            </div>

            <EmptyState
                v-else
                :icon="Gift"
                title="Reward tidak ditemukan"
                caption="Ubah kata kunci atau tambahkan reward baru."
            />
        </section>

        <section
            class="rounded-2xl border border-slate-200/80 bg-white shadow-sm"
        >
            <div class="border-b border-slate-100 p-5">
                <h3 class="text-sm font-semibold text-slate-900">
                    Riwayat penukaran
                </h3>
                <p class="mt-0.5 text-xs text-slate-500">
                    Reward yang ditukar kasir di POS
                </p>
            </div>

            <div v-if="redemptions.data.length > 0" class="overflow-x-auto">
                <table class="w-full min-w-[720px] text-left text-sm">
                    <thead
                        class="bg-slate-50/80 text-[11px] tracking-wide text-slate-500 uppercase"
                    >
                        <tr>
                            <th class="px-5 py-3 font-medium">Waktu</th>
                            <th class="px-5 py-3 font-medium">Member</th>
                            <th class="px-5 py-3 font-medium">Reward</th>
                            <th class="px-5 py-3 font-medium">Order</th>
                            <th class="px-5 py-3 text-right font-medium">
                                Stempel
                            </th>
                            <th class="px-5 py-3 text-right font-medium">
                                Potongan
                            </th>
                            <th class="px-5 py-3 font-medium">Kasir</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100">
                        <tr
                            v-for="redemption in redemptions.data"
                            :key="redemption.id"
                        >
                            <td class="px-5 py-3 text-xs text-slate-500">
                                {{ redemption.date }}, {{ redemption.time }}
                            </td>
                            <td class="px-5 py-3">
                                <p class="font-medium text-slate-800">
                                    {{ redemption.member }}
                                </p>
                                <p class="text-[11px] text-slate-400">
                                    {{ redemption.memberId }}
                                </p>
                            </td>
                            <td class="px-5 py-3 text-slate-700">
                                {{ redemption.reward }}
                            </td>
                            <td class="px-5 py-3 text-xs text-slate-500">
                                {{ redemption.order }}
                            </td>
                            <td
                                class="px-5 py-3 text-right font-semibold text-cyan-700 tabular-nums"
                            >
                                −{{ redemption.stamps }}
                            </td>
                            <td
                                class="px-5 py-3 text-right text-slate-700 tabular-nums"
                            >
                                {{
                                    redemption.discount > 0
                                        ? formatCurrency(redemption.discount)
                                        : '—'
                                }}
                            </td>
                            <td class="px-5 py-3 text-xs text-slate-500">
                                {{ redemption.by }}
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>

            <EmptyState
                v-else
                :icon="CircleCheck"
                title="Belum ada penukaran"
                caption="Reward yang ditukar kasir di POS akan tercatat di sini."
            />

            <DataPagination
                :meta="redemptions.meta"
                label="penukaran"
                @change="visitRedemptionPage"
            />
        </section>
    </div>

    <!-- Create / edit -->
    <ModalDialog
        :open="isFormOpen"
        :title="editingId !== null ? 'Edit reward' : 'Tambah reward'"
        caption="Syarat stempel menentukan kapan customer bisa menukar"
        @close="isFormOpen = false"
    >
        <div class="space-y-4">
            <div
                class="flex items-center justify-between rounded-xl border border-slate-200 px-3 py-2.5"
            >
                <span
                    id="reward-status-label"
                    class="text-xs font-medium text-slate-600"
                >
                    Status
                </span>
                <div class="flex items-center gap-2.5">
                    <span class="text-xs text-slate-600">{{
                        draft.status === 'aktif' ? 'Aktif' : 'Nonaktif'
                    }}</span>
                    <button
                        type="button"
                        role="switch"
                        aria-labelledby="reward-status-label"
                        :aria-checked="draft.status === 'aktif'"
                        class="relative h-6 w-11 rounded-full transition-colors focus-visible:ring-2 focus-visible:ring-cyan-500 focus-visible:ring-offset-2 focus-visible:outline-none"
                        :class="
                            draft.status === 'aktif'
                                ? 'bg-cyan-600'
                                : 'bg-slate-300'
                        "
                        @click="
                            draft.status =
                                draft.status === 'aktif' ? 'nonaktif' : 'aktif'
                        "
                    >
                        <span
                            class="absolute top-0.5 left-0.5 h-5 w-5 rounded-full bg-white shadow-sm transition-transform"
                            :class="
                                draft.status === 'aktif' ? 'translate-x-5' : ''
                            "
                        ></span>
                    </button>
                </div>
            </div>

            <div>
                <label
                    class="text-xs font-medium text-slate-600"
                    for="reward-name"
                >
                    Nama reward
                </label>
                <div
                    ref="iconPicker"
                    class="relative mt-1.5"
                    @keydown.esc.stop="isIconPickerOpen = false"
                >
                    <div
                        class="flex h-11 overflow-hidden rounded-xl border border-slate-200 focus-within:border-cyan-400"
                    >
                        <button
                            type="button"
                            class="flex w-16 shrink-0 items-center justify-center gap-0.5 border-r border-slate-200 text-xl transition hover:bg-slate-50"
                            aria-label="Pilih ikon reward"
                            aria-controls="reward-icon-options"
                            :aria-expanded="isIconPickerOpen"
                            @click="isIconPickerOpen = !isIconPickerOpen"
                        >
                            {{ draft.icon }}
                            <ChevronDown class="h-3 w-3 text-slate-400" />
                        </button>
                        <input
                            id="reward-name"
                            v-model="draft.name"
                            type="text"
                            placeholder="Gratis Cuci Mobil Reguler"
                            class="min-w-0 flex-1 px-3 text-sm focus:outline-none"
                            @focus="isIconPickerOpen = false"
                        />
                    </div>
                    <div
                        v-if="isIconPickerOpen"
                        id="reward-icon-options"
                        class="absolute inset-x-0 top-full z-20 mt-2 rounded-xl border border-slate-200 bg-white p-3 shadow-xl"
                    >
                        <div class="grid grid-cols-4 gap-2">
                            <button
                                v-for="option in rewardIconOptions"
                                :key="option.icon"
                                type="button"
                                class="flex flex-col items-center justify-center gap-1 rounded-lg border px-1 py-2 transition hover:border-cyan-300 hover:bg-cyan-50"
                                :class="
                                    draft.icon === option.icon
                                        ? 'border-cyan-400 bg-cyan-50'
                                        : 'border-slate-100'
                                "
                                :aria-label="option.label"
                                :aria-pressed="draft.icon === option.icon"
                                @click="selectRewardIcon(option.icon)"
                            >
                                <span class="text-xl">{{ option.icon }}</span>
                                <span class="text-[10px] text-slate-600">{{
                                    option.label
                                }}</span>
                            </button>
                        </div>
                    </div>
                </div>
            </div>

            <div>
                <label
                    class="text-xs font-medium text-slate-600"
                    for="reward-desc"
                >
                    Deskripsi
                </label>
                <textarea
                    id="reward-desc"
                    v-model="draft.description"
                    rows="2"
                    placeholder="Penjelasan singkat untuk customer"
                    class="mt-1.5 w-full resize-none rounded-xl border border-slate-200 px-3 py-2.5 text-sm focus:border-cyan-400 focus:outline-none"
                ></textarea>
            </div>

            <div class="grid grid-cols-2 gap-3">
                <div>
                    <label
                        class="text-xs font-medium text-slate-600"
                        for="reward-stamps"
                    >
                        Stempel dibutuhkan
                    </label>
                    <input
                        id="reward-stamps"
                        v-model.number="draft.requiredStamps"
                        type="number"
                        min="1"
                        class="mt-1.5 w-full rounded-xl border border-slate-200 px-3 py-2.5 text-sm tabular-nums focus:border-cyan-400 focus:outline-none"
                    />
                </div>
                <div>
                    <label
                        class="text-xs font-medium text-slate-600"
                        for="reward-stock"
                    >
                        Stok
                    </label>
                    <input
                        id="reward-stock"
                        v-model.number="draft.stock"
                        type="number"
                        min="0"
                        class="mt-1.5 w-full rounded-xl border border-slate-200 px-3 py-2.5 text-sm tabular-nums focus:border-cyan-400 focus:outline-none"
                    />
                </div>
            </div>

            <div>
                <p class="text-xs font-medium text-slate-600">Diskon Layanan</p>
                <p class="mt-0.5 text-[11px] text-slate-400">
                    Pilih variasi layanan. Saat ditukar, hanya pilihan dengan
                    potongan terbesar pada order yang berlaku.
                </p>
                <div
                    class="mt-2 space-y-2 rounded-xl border border-slate-200 p-2"
                >
                    <div
                        class="flex items-center gap-2 rounded-lg border border-slate-200 px-2 py-1.5 focus-within:border-cyan-400"
                    >
                        <Search class="h-4 w-4 text-slate-400" />
                        <input
                            v-model="serviceSearch"
                            type="search"
                            aria-label="Cari layanan atau variasi"
                            placeholder="Cari layanan atau variasi"
                            class="min-w-0 flex-1 text-xs focus:outline-none"
                            @input="activeServiceId = null"
                        />
                    </div>
                    <div class="flex items-center gap-2 text-xs text-slate-600">
                        <button
                            v-if="
                                activeServiceId !== null ||
                                activeCategory !== null ||
                                activeGroup !== null
                            "
                            type="button"
                            class="flex items-center gap-1 rounded-lg border border-slate-200 px-2 py-1 hover:bg-slate-50"
                            @click="
                                activeServiceId !== null
                                    ? (activeServiceId = null)
                                    : activeCategory !== null
                                      ? (activeCategory = null)
                                      : (activeGroup = null)
                            "
                        >
                            <ChevronLeft class="h-3 w-3" /> Kembali
                        </button>
                        <span class="truncate">{{
                            activeService?.name ??
                            activeCategory ??
                            activeGroup ??
                            'Pilih layanan'
                        }}</span>
                    </div>
                    <div
                        class="grid max-h-44 grid-cols-1 gap-1.5 overflow-y-auto sm:grid-cols-2"
                    >
                        <template v-if="activeService !== null">
                            <button
                                v-for="variation in activeService.variations.filter(
                                    (item) => item.isActive,
                                )"
                                :key="variation.id"
                                type="button"
                                class="flex items-center justify-between gap-2 rounded-lg border border-slate-200 px-2 py-2 text-left text-xs hover:border-cyan-300 hover:bg-cyan-50"
                                :disabled="
                                    draft.applicableVariations.some(
                                        (item) =>
                                            item.serviceVariationId ===
                                            variation.id,
                                    )
                                "
                                @click="addVariation(variation.id)"
                            >
                                <span class="min-w-0">
                                    <span class="block truncate font-medium">{{
                                        variation.label || activeService.name
                                    }}</span>
                                    <span class="text-slate-500">{{
                                        formatCurrency(variation.price)
                                    }}</span>
                                </span>
                                <span class="shrink-0 text-cyan-600">{{
                                    draft.applicableVariations.some(
                                        (item) =>
                                            item.serviceVariationId ===
                                            variation.id,
                                    )
                                        ? 'Dipilih'
                                        : '+'
                                }}</span>
                            </button>
                        </template>
                        <template
                            v-else-if="
                                serviceSearch.trim() !== '' ||
                                activeCategory !== null
                            "
                        >
                            <button
                                v-for="service in visibleServices"
                                :key="service.id"
                                type="button"
                                class="flex items-center gap-2 rounded-lg border border-slate-200 px-2 py-2 text-left text-xs hover:border-cyan-300 hover:bg-cyan-50"
                                @click="activeServiceId = service.id"
                            >
                                <span class="text-lg">{{ service.icon }}</span>
                                <span class="min-w-0 flex-1 truncate">{{
                                    service.name
                                }}</span>
                                <ChevronRight class="h-3 w-3 text-cyan-600" />
                            </button>
                        </template>
                        <template v-else-if="activeGroup !== null">
                            <button
                                v-for="category in serviceCategories"
                                :key="category"
                                type="button"
                                class="flex items-center justify-between rounded-lg border border-slate-200 px-2 py-2 text-left text-xs hover:border-cyan-300 hover:bg-cyan-50"
                                @click="activeCategory = category"
                            >
                                {{ category }}
                                <ChevronRight class="h-3 w-3 text-cyan-600" />
                            </button>
                        </template>
                        <template v-else>
                            <button
                                v-for="group in serviceGroups"
                                :key="group"
                                type="button"
                                class="flex items-center justify-between rounded-lg border border-slate-200 px-2 py-2 text-left text-xs hover:border-cyan-300 hover:bg-cyan-50"
                                @click="activeGroup = group"
                            >
                                {{ group }}
                                <ChevronRight class="h-3 w-3 text-cyan-600" />
                            </button>
                        </template>
                    </div>
                </div>
            </div>

            <div>
                <p
                    v-if="draft.applicableVariations.length === 0"
                    class="mt-2 rounded-xl bg-slate-50 px-3 py-3 text-xs text-slate-500"
                >
                    Belum ada pilihan. Reward ini menjadi merchandise tanpa
                    potongan.
                </p>
                <div v-else class="mt-2 space-y-2">
                    <div
                        v-for="(selection, index) in draft.applicableVariations"
                        :key="selection.serviceVariationId"
                        class="rounded-xl border border-slate-200 p-3"
                    >
                        <div class="flex items-start justify-between gap-2">
                            <div class="min-w-0">
                                <p
                                    class="truncate text-xs font-medium text-slate-800"
                                >
                                    {{
                                        variationDetails(
                                            selection.serviceVariationId,
                                        ).name
                                    }}
                                </p>
                                <p class="text-[11px] text-slate-500">
                                    {{
                                        formatCurrency(
                                            variationDetails(
                                                selection.serviceVariationId,
                                            ).price,
                                        )
                                    }}<span
                                        v-if="
                                            !variationDetails(
                                                selection.serviceVariationId,
                                            ).isActive
                                        "
                                    >
                                        · Nonaktif</span
                                    >
                                </p>
                            </div>
                            <button
                                type="button"
                                class="text-slate-400 hover:text-rose-600"
                                :aria-label="`Hapus ${variationDetails(selection.serviceVariationId).name}`"
                                @click="
                                    removeVariation(
                                        selection.serviceVariationId,
                                    )
                                "
                            >
                                <Trash2 class="h-4 w-4" />
                            </button>
                        </div>
                        <div class="mt-2 grid grid-cols-2 gap-2">
                            <label
                                class="text-[11px] text-slate-600"
                                :for="`reward-quantity-${index}`"
                                >Qty
                                <input
                                    :id="`reward-quantity-${index}`"
                                    v-model.number="selection.quantity"
                                    type="number"
                                    min="1"
                                    max="1000"
                                    class="mt-1 w-full rounded-lg border border-slate-200 px-2 py-1.5 text-sm tabular-nums focus:border-cyan-400 focus:outline-none"
                                />
                            </label>
                            <label
                                class="text-[11px] text-slate-600"
                                :for="`reward-percent-${index}`"
                                >Potongan (%)
                                <input
                                    :id="`reward-percent-${index}`"
                                    v-model.number="selection.discountPercent"
                                    type="number"
                                    min="1"
                                    max="100"
                                    class="mt-1 w-full rounded-lg border border-slate-200 px-2 py-1.5 text-sm tabular-nums focus:border-cyan-400 focus:outline-none"
                                />
                            </label>
                        </div>
                    </div>
                </div>
            </div>

            <p
                class="rounded-xl bg-cyan-50 px-3 py-2.5 text-[11px] text-cyan-800 ring-1 ring-cyan-100"
            >
                Dengan syarat {{ draft.requiredStamps }} stempel,
                {{ eligibleCountFor(draft.requiredStamps) }}
                dari {{ stats.members }} member aktif bisa langsung menukar.
            </p>

            <p
                v-if="Object.keys(rewardForm.errors).length > 0"
                class="rounded-xl bg-rose-50 px-3 py-2.5 text-xs text-rose-700 ring-1 ring-rose-100"
            >
                {{ Object.values(rewardForm.errors)[0] }}
            </p>
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
                :disabled="!canSave || rewardForm.processing"
                @click="saveReward"
            >
                {{ editingId !== null ? 'Simpan perubahan' : 'Tambah reward' }}
            </button>
        </template>
    </ModalDialog>

    <!-- Delete confirmation -->
    <ModalDialog
        :open="pendingDelete !== null"
        title="Hapus reward"
        size="sm"
        @close="pendingDelete = null"
    >
        <p class="text-sm text-slate-600">
            Hapus
            <span class="font-semibold text-slate-900">
                {{ pendingDelete?.name }}
            </span>
            dari katalog? Customer tidak akan melihat reward ini lagi.
        </p>
        <p
            v-if="Object.keys(deleteForm.errors).length > 0"
            class="mt-3 rounded-xl bg-rose-50 px-3 py-2.5 text-xs text-rose-700 ring-1 ring-rose-100"
        >
            {{ Object.values(deleteForm.errors)[0] }}
        </p>

        <template #footer>
            <button
                type="button"
                class="flex-1 rounded-xl border border-slate-200 py-2.5 text-sm font-medium text-slate-600 transition hover:bg-slate-50"
                @click="pendingDelete = null"
            >
                Batal
            </button>
            <button
                type="button"
                class="flex-1 rounded-xl bg-rose-600 py-2.5 text-sm font-semibold text-white transition hover:bg-rose-700 disabled:cursor-not-allowed disabled:opacity-60"
                :disabled="deleteForm.processing"
                @click="deleteReward"
            >
                Hapus
            </button>
        </template>
    </ModalDialog>
</template>
