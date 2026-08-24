<script setup lang="ts">
import { Head } from '@inertiajs/vue3';
import {
    CircleCheck,
    Gift,
    Pencil,
    Plus,
    Sparkles,
    Trash2,
    Users,
} from '@lucide/vue';
import { computed, ref } from 'vue';
import DataToolbar from '@/components/demo/DataToolbar.vue';
import EmptyState from '@/components/demo/EmptyState.vue';
import ModalDialog from '@/components/demo/ModalDialog.vue';
import StatCard from '@/components/demo/StatCard.vue';
import StatusPill from '@/components/demo/StatusPill.vue';
import type {
    CarwashBrand,
    CarwashCustomer,
    CarwashReward,
} from '@/types/demo';

const props = defineProps<{
    brand: CarwashBrand;
    rewards: CarwashReward[];
    categories: string[];
    stampTarget: number;
    customers: CarwashCustomer[];
}>();

const rewardList = ref<CarwashReward[]>(
    props.rewards.map((reward) => ({ ...reward })),
);

const search = ref<string>('');
const categoryFilter = ref<string>('Semua');
const editingId = ref<number | null>(null);
const isFormOpen = ref<boolean>(false);
const pendingDelete = ref<CarwashReward | null>(null);

const draft = ref({
    name: '',
    description: '',
    requiredStamps: 5,
    icon: '🎁',
    category: props.categories[0],
    status: 'aktif',
    stock: 20,
});

const filterOptions = computed<string[]>(() => ['Semua', ...props.categories]);

const filteredRewards = computed<CarwashReward[]>(() => {
    const query = search.value.trim().toLowerCase();

    return rewardList.value.filter((reward) => {
        const matchesCategory =
            categoryFilter.value === 'Semua' ||
            reward.category === categoryFilter.value;
        const matchesQuery =
            query === '' || reward.name.toLowerCase().includes(query);

        return matchesCategory && matchesQuery;
    });
});

const activeCount = computed<number>(
    () => rewardList.value.filter((reward) => reward.status === 'aktif').length,
);

const totalRedeemed = computed<number>(() =>
    rewardList.value.reduce((total, reward) => total + reward.redeemed, 0),
);

/** How many customers currently qualify for each reward (BR-13). */
function eligibleCount(reward: CarwashReward): number {
    return props.customers.filter(
        (customer) => customer.stamps >= reward.requiredStamps,
    ).length;
}

const canSave = computed<boolean>(
    () => draft.value.name.trim() !== '' && draft.value.requiredStamps > 0,
);

function openCreate(): void {
    editingId.value = null;
    draft.value = {
        name: '',
        description: '',
        requiredStamps: 5,
        icon: '🎁',
        category: props.categories[0],
        status: 'aktif',
        stock: 20,
    };
    isFormOpen.value = true;
}

function openEdit(reward: CarwashReward): void {
    editingId.value = reward.id;
    draft.value = {
        name: reward.name,
        description: reward.description,
        requiredStamps: reward.requiredStamps,
        icon: reward.icon,
        category: reward.category,
        status: reward.status,
        stock: reward.stock,
    };
    isFormOpen.value = true;
}

function saveReward(): void {
    if (!canSave.value) {
        return;
    }

    if (editingId.value !== null) {
        const existing = rewardList.value.find(
            (reward) => reward.id === editingId.value,
        );

        if (existing) {
            Object.assign(existing, draft.value);
        }
    } else {
        rewardList.value = [
            {
                id: 1000 + rewardList.value.length,
                ...draft.value,
                applicableServiceIds: [],
                redeemed: 0,
            },
            ...rewardList.value,
        ];
    }

    isFormOpen.value = false;
}

function toggleStatus(reward: CarwashReward): void {
    reward.status = reward.status === 'aktif' ? 'nonaktif' : 'aktif';
}

function deleteReward(): void {
    if (!pendingDelete.value) {
        return;
    }

    rewardList.value = rewardList.value.filter(
        (reward) => reward.id !== pendingDelete.value?.id,
    );
    pendingDelete.value = null;
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
                :value="String(totalRedeemed)"
                caption="sepanjang program"
                :icon="CircleCheck"
                tone="emerald"
            />
            <StatCard
                label="Target kartu stempel"
                :value="String(stampTarget)"
                :caption="brand.stampReward"
                :icon="Sparkles"
                tone="amber"
            />
            <StatCard
                label="Customer memenuhi syarat"
                :value="
                    String(
                        customers.filter((customer) => customer.stamps >= 3)
                            .length,
                    )
                "
                caption="punya minimal 3 stempel"
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
                        :filters="filterOptions"
                        :active-filter="categoryFilter"
                        @filter="categoryFilter = $event"
                    />
                    <button
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
                        <StatusPill :status="reward.status" />
                    </div>

                    <p class="mt-3 text-sm font-semibold text-slate-900">
                        {{ reward.name }}
                    </p>
                    <p class="mt-1 line-clamp-2 text-xs text-slate-500">
                        {{ reward.description }}
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
                        <p class="text-[11px] text-slate-400">
                            stok {{ reward.stock }}
                        </p>
                    </div>

                    <div
                        class="mt-2 flex items-center justify-between text-[11px]"
                    >
                        <span class="text-slate-500">
                            {{ eligibleCount(reward) }} customer memenuhi syarat
                        </span>
                        <span class="text-slate-400">
                            {{ reward.redeemed }}× ditukar
                        </span>
                    </div>

                    <div class="mt-3 flex gap-1.5">
                        <button
                            type="button"
                            class="flex flex-1 items-center justify-center gap-1 rounded-lg border border-slate-200 py-1.5 text-[11px] font-medium text-slate-600 transition hover:bg-slate-50"
                            @click="openEdit(reward)"
                        >
                            <Pencil class="h-3 w-3" />
                            Edit
                        </button>
                        <button
                            type="button"
                            class="flex-1 rounded-lg border border-slate-200 py-1.5 text-[11px] font-medium text-slate-600 transition hover:bg-slate-50"
                            @click="toggleStatus(reward)"
                        >
                            {{
                                reward.status === 'aktif'
                                    ? 'Nonaktifkan'
                                    : 'Aktifkan'
                            }}
                        </button>
                        <button
                            type="button"
                            class="rounded-lg border border-slate-200 px-2 py-1.5 text-slate-400 transition hover:border-rose-200 hover:bg-rose-50 hover:text-rose-500"
                            aria-label="Hapus reward"
                            @click="pendingDelete = reward"
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
    </div>

    <!-- Create / edit -->
    <ModalDialog
        :open="isFormOpen"
        :title="editingId !== null ? 'Edit reward' : 'Tambah reward'"
        caption="Syarat stempel menentukan kapan customer bisa menukar"
        @close="isFormOpen = false"
    >
        <div class="space-y-4">
            <div class="grid grid-cols-[auto_1fr] gap-3">
                <div>
                    <label
                        class="text-xs font-medium text-slate-600"
                        for="reward-icon"
                    >
                        Ikon
                    </label>
                    <input
                        id="reward-icon"
                        v-model="draft.icon"
                        type="text"
                        maxlength="2"
                        class="mt-1.5 w-16 rounded-xl border border-slate-200 px-3 py-2.5 text-center text-xl focus:border-cyan-400 focus:outline-none"
                    />
                </div>
                <div>
                    <label
                        class="text-xs font-medium text-slate-600"
                        for="reward-name"
                    >
                        Nama reward
                    </label>
                    <input
                        id="reward-name"
                        v-model="draft.name"
                        type="text"
                        placeholder="Gratis Cuci Mobil Reguler"
                        class="mt-1.5 w-full rounded-xl border border-slate-200 px-3 py-2.5 text-sm focus:border-cyan-400 focus:outline-none"
                    />
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

            <div class="grid grid-cols-2 gap-3">
                <div>
                    <label
                        class="text-xs font-medium text-slate-600"
                        for="reward-cat"
                    >
                        Kategori
                    </label>
                    <select
                        id="reward-cat"
                        v-model="draft.category"
                        class="mt-1.5 w-full rounded-xl border border-slate-200 px-3 py-2.5 text-sm focus:border-cyan-400 focus:outline-none"
                    >
                        <option
                            v-for="category in categories"
                            :key="category"
                            :value="category"
                        >
                            {{ category }}
                        </option>
                    </select>
                </div>
                <div>
                    <label
                        class="text-xs font-medium text-slate-600"
                        for="reward-status"
                    >
                        Status
                    </label>
                    <select
                        id="reward-status"
                        v-model="draft.status"
                        class="mt-1.5 w-full rounded-xl border border-slate-200 px-3 py-2.5 text-sm focus:border-cyan-400 focus:outline-none"
                    >
                        <option value="aktif">Aktif</option>
                        <option value="nonaktif">Nonaktif</option>
                    </select>
                </div>
            </div>

            <p
                class="rounded-xl bg-cyan-50 px-3 py-2.5 text-[11px] text-cyan-800 ring-1 ring-cyan-100"
            >
                Dengan syarat {{ draft.requiredStamps }} stempel,
                {{
                    customers.filter(
                        (customer) => customer.stamps >= draft.requiredStamps,
                    ).length
                }}
                dari {{ customers.length }} customer bisa langsung menukar.
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
                :disabled="!canSave"
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
                class="flex-1 rounded-xl bg-rose-600 py-2.5 text-sm font-semibold text-white transition hover:bg-rose-700"
                @click="deleteReward"
            >
                Hapus
            </button>
        </template>
    </ModalDialog>
</template>
