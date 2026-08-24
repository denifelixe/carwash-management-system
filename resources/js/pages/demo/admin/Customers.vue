<script setup lang="ts">
import { Head } from '@inertiajs/vue3';
import {
    Car,
    Gift,
    Mail,
    Phone,
    Plus,
    Sparkles,
    Trash2,
    UserPlus,
    Users,
} from '@lucide/vue';
import { computed, ref } from 'vue';
import DataToolbar from '@/components/demo/DataToolbar.vue';
import EmptyState from '@/components/demo/EmptyState.vue';
import ModalDialog from '@/components/demo/ModalDialog.vue';
import SlideOver from '@/components/demo/SlideOver.vue';
import StampProgress from '@/components/demo/StampProgress.vue';
import StatCard from '@/components/demo/StatCard.vue';
import StatusPill from '@/components/demo/StatusPill.vue';
import {
    formatCurrency,
    formatNumber,
    formatShortCurrency,
} from '@/composables/useCarwashFormat';
import type {
    CarwashBrand,
    CarwashCustomer,
    CarwashOrder,
    CarwashReward,
    CarwashStampEntry,
    CarwashVehicle,
} from '@/types/demo';

const props = defineProps<{
    brand: CarwashBrand;
    customers: CarwashCustomer[];
    orders: CarwashOrder[];
    stampHistory: CarwashStampEntry[];
    rewards: CarwashReward[];
    stampTarget: number;
}>();

const customerList = ref<CarwashCustomer[]>(
    props.customers.map((customer) => ({
        ...customer,
        vehicles: customer.vehicles.map((vehicle) => ({ ...vehicle })),
    })),
);

const search = ref<string>('');
const statusFilter = ref<string>('Semua');
const detailCustomerId = ref<number | null>(null);
const detailTab = ref<'stamps' | 'orders'>('stamps');
const isCreateOpen = ref<boolean>(false);

const draft = ref({
    name: '',
    phone: '',
    email: '',
    vehicles: [emptyVehicle(true)],
});

const filterOptions = ['Semua', 'aktif', 'tidak aktif'];

const filteredCustomers = computed<CarwashCustomer[]>(() => {
    const query = search.value.trim().toLowerCase();

    return customerList.value.filter((customer) => {
        const matchesStatus =
            statusFilter.value === 'Semua' ||
            customer.status === statusFilter.value;
        const matchesQuery =
            query === '' ||
            customer.name.toLowerCase().includes(query) ||
            customer.vehicles.some(
                (vehicle) =>
                    vehicle.plate.toLowerCase().includes(query) ||
                    vehicle.name.toLowerCase().includes(query),
            ) ||
            customer.phone.includes(query) ||
            customer.memberId.toLowerCase().includes(query);

        return matchesStatus && matchesQuery;
    });
});

const detailCustomer = computed<CarwashCustomer | null>(
    () =>
        customerList.value.find(
            (customer) => customer.id === detailCustomerId.value,
        ) ?? null,
);

/** Orders belonging to the customer in the drawer (BR-07). */
const detailOrders = computed<CarwashOrder[]>(() =>
    props.orders.filter((order) => order.customerId === detailCustomerId.value),
);

const totalStamps = computed<number>(() =>
    customerList.value.reduce((total, customer) => total + customer.stamps, 0),
);

const activeCount = computed<number>(
    () =>
        customerList.value.filter((customer) => customer.status === 'aktif')
            .length,
);

const withAccountCount = computed<number>(
    () => customerList.value.filter((customer) => customer.hasAccount).length,
);

/** Rewards the drawer customer can already claim. */
const unlockedRewards = computed<CarwashReward[]>(() => {
    const customer = detailCustomer.value;

    if (!customer) {
        return [];
    }

    return props.rewards.filter(
        (reward) =>
            reward.status === 'aktif' &&
            reward.requiredStamps <= customer.stamps,
    );
});

const canCreate = computed<boolean>(
    () =>
        draft.value.name.trim() !== '' &&
        draft.value.phone.trim() !== '' &&
        draft.value.vehicles.length > 0 &&
        draft.value.vehicles.every(
            (vehicle) =>
                vehicle.name.trim() !== '' && vehicle.plate.trim() !== '',
        ),
);

function emptyVehicle(isPrimary = false): CarwashVehicle {
    return {
        name: '',
        plate: '',
        type: 'Mobil',
        isPrimary,
    };
}

function addVehicle(): void {
    draft.value.vehicles.push(emptyVehicle());
}

function removeVehicle(index: number): void {
    if (draft.value.vehicles.length === 1) {
        return;
    }

    draft.value.vehicles.splice(index, 1);
}

function initialsOf(name: string): string {
    return name
        .split(' ')
        .map((part) => part[0])
        .join('')
        .slice(0, 2)
        .toUpperCase();
}

function openDetail(customer: CarwashCustomer): void {
    detailCustomerId.value = customer.id;
    detailTab.value = 'stamps';
}

function createCustomer(): void {
    if (!canCreate.value) {
        return;
    }

    const sequence = customerList.value.length + 1;
    const vehicles = draft.value.vehicles.map((vehicle, index) => ({
        ...vehicle,
        name: vehicle.name.trim(),
        plate: vehicle.plate.trim().toUpperCase(),
        isPrimary: index === 0,
    }));
    const primaryVehicle = vehicles[0];

    customerList.value = [
        {
            id: 1000 + sequence,
            name: draft.value.name,
            memberId: `ZW-2026-${String(1000 + sequence).slice(-4)}`,
            phone: draft.value.phone,
            email: draft.value.email || '—',
            vehicle: primaryVehicle.name,
            plate: primaryVehicle.plate,
            vehicles,
            stamps: 0,
            lifetimeStamps: 0,
            visits: 0,
            spend: 0,
            joinedAt: 'Agu 2026',
            lastVisit: 'Belum pernah',
            initials: initialsOf(draft.value.name),
            status: 'aktif',
            hasAccount: false,
        },
        ...customerList.value,
    ];

    draft.value = {
        name: '',
        phone: '',
        email: '',
        vehicles: [emptyVehicle(true)],
    };
    isCreateOpen.value = false;
}

function stampToneClass(type: string): string {
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
    <Head :title="`${brand.name} — Member`" />

    <div class="space-y-4">
        <section class="grid grid-cols-1 gap-4 sm:grid-cols-2 xl:grid-cols-4">
            <StatCard
                label="Total member"
                :value="String(customerList.length)"
                :caption="`${activeCount} aktif`"
                :icon="Users"
            />
            <StatCard
                label="Punya akun portal"
                :value="String(withAccountCount)"
                caption="terdaftar di aplikasi member"
                :icon="UserPlus"
                tone="emerald"
            />
            <StatCard
                label="Stempel beredar"
                :value="formatNumber(totalStamps)"
                caption="belum ditukar reward"
                :icon="Sparkles"
                tone="amber"
            />
            <StatCard
                label="Target kartu"
                :value="`${stampTarget} stempel`"
                :caption="brand.stampReward"
                :icon="Gift"
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
                        Database member
                    </h3>
                    <p class="mt-0.5 text-xs text-slate-500">
                        {{ filteredCustomers.length }} member ditampilkan
                    </p>
                </div>
                <div class="flex flex-wrap items-center gap-2">
                    <DataToolbar
                        v-model:search="search"
                        placeholder="Cari nama / plat / HP"
                        :filters="filterOptions"
                        :active-filter="statusFilter"
                        @filter="statusFilter = $event"
                    />
                    <button
                        type="button"
                        class="flex items-center gap-2 rounded-xl bg-gradient-to-r from-cyan-500 to-sky-600 px-3 py-2 text-sm font-medium text-white shadow-lg shadow-cyan-500/25 transition hover:from-cyan-600 hover:to-sky-700"
                        @click="isCreateOpen = true"
                    >
                        <Plus class="h-4 w-4" />
                        Daftar Member
                    </button>
                </div>
            </div>

            <div v-if="filteredCustomers.length > 0" class="overflow-x-auto">
                <table class="w-full min-w-[860px] text-sm">
                    <thead>
                        <tr
                            class="border-b border-slate-100 text-left text-[11px] font-medium tracking-wider text-slate-400 uppercase"
                        >
                            <th class="px-5 py-3">Member</th>
                            <th class="px-5 py-3">Kendaraan</th>
                            <th class="px-5 py-3">Stempel</th>
                            <th class="px-5 py-3">Kunjungan</th>
                            <th class="px-5 py-3">Status</th>
                            <th class="px-5 py-3"></th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-50">
                        <tr
                            v-for="customer in filteredCustomers"
                            :key="customer.id"
                            class="transition hover:bg-slate-50/70"
                        >
                            <td class="px-5 py-3.5">
                                <div class="flex items-center gap-3">
                                    <div
                                        class="flex h-9 w-9 items-center justify-center rounded-full bg-gradient-to-br from-slate-100 to-slate-200 text-xs font-semibold text-slate-600"
                                    >
                                        {{ customer.initials }}
                                    </div>
                                    <div>
                                        <p class="font-medium text-slate-900">
                                            {{ customer.name }}
                                        </p>
                                        <p class="text-[11px] text-slate-500">
                                            {{ customer.memberId }} •
                                            {{ customer.phone }}
                                        </p>
                                    </div>
                                </div>
                            </td>
                            <td class="px-5 py-3.5">
                                <div class="space-y-1.5">
                                    <div
                                        v-for="vehicle in customer.vehicles.slice(
                                            0,
                                            2,
                                        )"
                                        :key="vehicle.plate"
                                        class="flex items-center gap-2"
                                    >
                                        <div class="min-w-0">
                                            <p class="truncate text-slate-700">
                                                {{ vehicle.name }}
                                            </p>
                                            <p
                                                class="text-[11px] text-slate-500"
                                            >
                                                {{ vehicle.plate }}
                                            </p>
                                        </div>
                                        <span
                                            v-if="vehicle.isPrimary"
                                            class="rounded-md bg-cyan-50 px-1.5 py-0.5 text-[9px] font-medium text-cyan-700"
                                        >
                                            Utama
                                        </span>
                                    </div>
                                    <p
                                        v-if="customer.vehicles.length > 2"
                                        class="text-[11px] font-medium text-cyan-700"
                                    >
                                        +{{ customer.vehicles.length - 2 }}
                                        kendaraan lain
                                    </p>
                                </div>
                            </td>
                            <td class="px-5 py-3.5">
                                <p
                                    class="font-medium text-slate-900 tabular-nums"
                                >
                                    {{ customer.stamps }}
                                    <span
                                        class="text-[11px] font-normal text-slate-400"
                                    >
                                        / {{ stampTarget }}
                                    </span>
                                </p>
                                <div
                                    class="mt-1 h-1.5 w-24 overflow-hidden rounded-full bg-slate-100"
                                >
                                    <div
                                        class="h-full rounded-full bg-gradient-to-r from-cyan-500 to-sky-500"
                                        :style="{
                                            width: `${Math.min((customer.stamps / stampTarget) * 100, 100)}%`,
                                        }"
                                    ></div>
                                </div>
                            </td>
                            <td class="px-5 py-3.5">
                                <p class="text-slate-700 tabular-nums">
                                    {{ customer.visits }}×
                                </p>
                                <p class="text-[11px] text-slate-500">
                                    {{ customer.lastVisit }}
                                </p>
                            </td>
                            <td class="px-5 py-3.5">
                                <StatusPill :status="customer.status" />
                            </td>
                            <td class="px-5 py-3.5 text-right">
                                <button
                                    type="button"
                                    class="rounded-lg px-3 py-1.5 text-xs font-medium text-cyan-700 transition hover:bg-cyan-50"
                                    @click="openDetail(customer)"
                                >
                                    Detail
                                </button>
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>

            <EmptyState
                v-else
                :icon="Users"
                title="Member tidak ditemukan"
                caption="Ubah kata kunci atau daftarkan member baru."
            />
        </section>
    </div>

    <!-- Customer detail -->
    <SlideOver
        :open="detailCustomer !== null"
        :title="detailCustomer?.name"
        :caption="detailCustomer?.memberId"
        @close="detailCustomerId = null"
    >
        <div v-if="detailCustomer" class="space-y-5">
            <!-- Stamp card -->
            <div
                class="rounded-2xl bg-gradient-to-br from-slate-900 to-cyan-900 p-4 text-white"
            >
                <div class="flex items-end justify-between">
                    <div>
                        <p class="text-xs text-slate-400">Stempel saat ini</p>
                        <p class="text-3xl font-bold tabular-nums">
                            {{ detailCustomer.stamps }}
                            <span class="text-base font-medium text-slate-400">
                                / {{ stampTarget }}
                            </span>
                        </p>
                    </div>
                    <StatusPill :status="detailCustomer.status" />
                </div>
                <div class="mt-3">
                    <StampProgress
                        :stamps="detailCustomer.stamps"
                        :target="stampTarget"
                        compact
                    />
                </div>
            </div>

            <div class="grid grid-cols-3 gap-3">
                <div class="rounded-xl bg-slate-50 p-3">
                    <p class="text-[11px] text-slate-500">Kunjungan</p>
                    <p
                        class="mt-0.5 text-lg font-semibold text-slate-900 tabular-nums"
                    >
                        {{ detailCustomer.visits }}
                    </p>
                </div>
                <div class="rounded-xl bg-slate-50 p-3">
                    <p class="text-[11px] text-slate-500">Total stempel</p>
                    <p
                        class="mt-0.5 text-lg font-semibold text-slate-900 tabular-nums"
                    >
                        {{ detailCustomer.lifetimeStamps }}
                    </p>
                </div>
                <div class="rounded-xl bg-slate-50 p-3">
                    <p class="text-[11px] text-slate-500">Total belanja</p>
                    <p
                        class="mt-0.5 text-lg font-semibold text-slate-900 tabular-nums"
                    >
                        {{ formatShortCurrency(detailCustomer.spend) }}
                    </p>
                </div>
            </div>

            <!-- Contact -->
            <div class="space-y-2">
                <div
                    class="flex items-center gap-3 rounded-xl border border-slate-200 p-3"
                >
                    <Phone class="h-4 w-4 shrink-0 text-slate-400" />
                    <span class="text-sm text-slate-700">
                        {{ detailCustomer.phone }}
                    </span>
                </div>
                <div
                    class="flex items-center gap-3 rounded-xl border border-slate-200 p-3"
                >
                    <Mail class="h-4 w-4 shrink-0 text-slate-400" />
                    <span class="min-w-0 truncate text-sm text-slate-700">
                        {{ detailCustomer.email }}
                    </span>
                </div>
                <div class="rounded-xl border border-slate-200 p-3">
                    <div class="flex items-center gap-2">
                        <Car class="h-4 w-4 shrink-0 text-slate-400" />
                        <p class="text-xs font-medium text-slate-600">
                            {{ detailCustomer.vehicles.length }} kendaraan
                        </p>
                    </div>
                    <ul class="mt-3 space-y-2">
                        <li
                            v-for="vehicle in detailCustomer.vehicles"
                            :key="vehicle.plate"
                            class="flex items-center justify-between gap-3 rounded-lg bg-slate-50 px-3 py-2"
                        >
                            <div class="min-w-0">
                                <p class="truncate text-sm text-slate-700">
                                    {{ vehicle.name }}
                                </p>
                                <p class="text-[11px] text-slate-500">
                                    {{ vehicle.plate }} · {{ vehicle.type }}
                                </p>
                            </div>
                            <span
                                v-if="vehicle.isPrimary"
                                class="shrink-0 rounded-md bg-cyan-100 px-2 py-1 text-[10px] font-medium text-cyan-700"
                            >
                                Utama
                            </span>
                        </li>
                    </ul>
                </div>
            </div>

            <!-- Unlocked rewards -->
            <div v-if="unlockedRewards.length > 0">
                <p class="text-xs font-medium text-slate-500">
                    Reward siap ditukar
                </p>
                <ul class="mt-2 space-y-1.5">
                    <li
                        v-for="reward in unlockedRewards"
                        :key="reward.id"
                        class="flex items-center gap-2 rounded-xl bg-emerald-50 px-3 py-2 ring-1 ring-emerald-100"
                    >
                        <span class="text-base">{{ reward.icon }}</span>
                        <span
                            class="min-w-0 flex-1 truncate text-xs font-medium text-emerald-800"
                        >
                            {{ reward.name }}
                        </span>
                        <span class="text-[10px] text-emerald-700">
                            {{ reward.requiredStamps }} stempel
                        </span>
                    </li>
                </ul>
            </div>

            <!-- History tabs -->
            <div>
                <div class="flex gap-1 rounded-xl bg-slate-100 p-1 text-sm">
                    <button
                        type="button"
                        class="flex-1 rounded-lg py-1.5 text-xs font-medium transition"
                        :class="
                            detailTab === 'stamps'
                                ? 'bg-white text-slate-900 shadow-sm'
                                : 'text-slate-500'
                        "
                        @click="detailTab = 'stamps'"
                    >
                        Riwayat stempel
                    </button>
                    <button
                        type="button"
                        class="flex-1 rounded-lg py-1.5 text-xs font-medium transition"
                        :class="
                            detailTab === 'orders'
                                ? 'bg-white text-slate-900 shadow-sm'
                                : 'text-slate-500'
                        "
                        @click="detailTab = 'orders'"
                    >
                        Riwayat transaksi
                    </button>
                </div>

                <ul
                    v-if="detailTab === 'stamps'"
                    class="mt-3 divide-y divide-slate-100 overflow-hidden rounded-xl border border-slate-200"
                >
                    <li
                        v-for="entry in stampHistory"
                        :key="entry.id"
                        class="flex items-center gap-3 p-3"
                    >
                        <span class="text-base">{{ entry.icon }}</span>
                        <div class="min-w-0 flex-1">
                            <p
                                class="truncate text-xs font-medium text-slate-800"
                            >
                                {{ entry.title }}
                            </p>
                            <p class="truncate text-[11px] text-slate-500">
                                {{ entry.date }}
                            </p>
                        </div>
                        <span
                            class="text-sm font-semibold tabular-nums"
                            :class="stampToneClass(entry.type)"
                        >
                            {{ entry.stamps > 0 ? '+' : '' }}{{ entry.stamps }}
                        </span>
                    </li>
                </ul>

                <div v-else class="mt-3">
                    <ul
                        v-if="detailOrders.length > 0"
                        class="divide-y divide-slate-100 overflow-hidden rounded-xl border border-slate-200"
                    >
                        <li
                            v-for="order in detailOrders"
                            :key="order.id"
                            class="p-3"
                        >
                            <div class="flex items-start justify-between gap-2">
                                <div class="min-w-0">
                                    <p
                                        class="truncate text-xs font-medium text-slate-800"
                                    >
                                        {{ order.items }}
                                    </p>
                                    <p class="text-[11px] text-slate-500">
                                        {{ order.orderNo }} • {{ order.time }}
                                    </p>
                                </div>
                                <p
                                    class="shrink-0 text-sm font-semibold text-slate-900 tabular-nums"
                                >
                                    {{ formatCurrency(order.total) }}
                                </p>
                            </div>
                            <div class="mt-1.5 flex items-center gap-2">
                                <StatusPill :status="order.status" />
                                <span
                                    v-if="order.stampsEarned > 0"
                                    class="text-[11px] font-medium text-emerald-600"
                                >
                                    +{{ order.stampsEarned }} stempel
                                </span>
                            </div>
                        </li>
                    </ul>
                    <p
                        v-else
                        class="rounded-xl border border-dashed border-slate-200 py-6 text-center text-xs text-slate-400"
                    >
                        Belum ada transaksi tercatat untuk member ini.
                    </p>
                </div>
            </div>
        </div>
    </SlideOver>

    <!-- Register customer -->
    <ModalDialog
        :open="isCreateOpen"
        title="Daftarkan member"
        caption="Data dasar untuk mulai mengumpulkan stempel"
        @close="isCreateOpen = false"
    >
        <div class="space-y-3">
            <div>
                <label
                    class="text-xs font-medium text-slate-600"
                    for="cust-name"
                >
                    Nama lengkap
                </label>
                <input
                    id="cust-name"
                    v-model="draft.name"
                    type="text"
                    placeholder="Nama member"
                    class="mt-1.5 w-full rounded-xl border border-slate-200 bg-white px-3 py-2.5 text-sm text-slate-900 placeholder:text-slate-400 focus:border-cyan-400 focus:outline-none"
                />
            </div>
            <div class="grid grid-cols-2 gap-3">
                <div>
                    <label
                        class="text-xs font-medium text-slate-600"
                        for="cust-phone"
                    >
                        Nomor HP
                    </label>
                    <input
                        id="cust-phone"
                        v-model="draft.phone"
                        type="tel"
                        placeholder="0812-xxxx-xxxx"
                        class="mt-1.5 w-full rounded-xl border border-slate-200 bg-white px-3 py-2.5 text-sm text-slate-900 placeholder:text-slate-400 focus:border-cyan-400 focus:outline-none"
                    />
                </div>
                <div>
                    <label
                        class="text-xs font-medium text-slate-600"
                        for="cust-email"
                    >
                        Email
                        <span class="text-slate-400">(opsional)</span>
                    </label>
                    <input
                        id="cust-email"
                        v-model="draft.email"
                        type="email"
                        placeholder="nama@email.com"
                        class="mt-1.5 w-full rounded-xl border border-slate-200 bg-white px-3 py-2.5 text-sm text-slate-900 placeholder:text-slate-400 focus:border-cyan-400 focus:outline-none"
                    />
                </div>
            </div>
            <div class="space-y-2.5">
                <div class="flex items-center justify-between gap-3">
                    <div>
                        <p class="text-xs font-medium text-slate-600">
                            Kendaraan member
                        </p>
                        <p class="text-[11px] text-slate-400">
                            Kendaraan pertama menjadi kendaraan utama.
                        </p>
                    </div>
                    <button
                        type="button"
                        class="flex shrink-0 items-center gap-1.5 rounded-lg bg-cyan-50 px-2.5 py-1.5 text-xs font-medium text-cyan-700 transition hover:bg-cyan-100"
                        @click="addVehicle"
                    >
                        <Plus class="h-3.5 w-3.5" />
                        Tambah kendaraan
                    </button>
                </div>

                <div
                    v-for="(vehicle, index) in draft.vehicles"
                    :key="index"
                    class="rounded-xl border border-slate-200 p-3"
                >
                    <div class="mb-2 flex items-center justify-between gap-2">
                        <p class="text-[11px] font-medium text-slate-500">
                            Kendaraan {{ index + 1 }}
                            <span v-if="index === 0" class="text-cyan-600">
                                · utama
                            </span>
                        </p>
                        <button
                            v-if="draft.vehicles.length > 1"
                            type="button"
                            class="rounded-md p-1 text-slate-400 transition hover:bg-rose-50 hover:text-rose-600"
                            :aria-label="`Hapus kendaraan ${index + 1}`"
                            @click="removeVehicle(index)"
                        >
                            <Trash2 class="h-3.5 w-3.5" />
                        </button>
                    </div>
                    <div class="grid gap-2 sm:grid-cols-[1fr_1fr_90px]">
                        <div>
                            <label
                                class="text-[11px] font-medium text-slate-600"
                                :for="`member-vehicle-${index}-plate`"
                            >
                                Plat nomor
                            </label>
                            <input
                                :id="`member-vehicle-${index}-plate`"
                                v-model="vehicle.plate"
                                type="text"
                                placeholder="B 1234 CDE"
                                class="mt-1 w-full rounded-xl border border-slate-200 bg-white px-3 py-2.5 text-sm text-slate-900 uppercase placeholder:text-slate-400 placeholder:normal-case focus:border-cyan-400 focus:outline-none"
                            />
                        </div>
                        <div>
                            <label
                                class="text-[11px] font-medium text-slate-600"
                                :for="`member-vehicle-${index}-name`"
                            >
                                Nama kendaraan
                            </label>
                            <input
                                :id="`member-vehicle-${index}-name`"
                                v-model="vehicle.name"
                                type="text"
                                placeholder="Toyota Avanza"
                                class="mt-1 w-full rounded-xl border border-slate-200 bg-white px-3 py-2.5 text-sm text-slate-900 placeholder:text-slate-400 focus:border-cyan-400 focus:outline-none"
                            />
                        </div>
                        <div>
                            <label
                                class="text-[11px] font-medium text-slate-600"
                                :for="`member-vehicle-${index}-type`"
                            >
                                Jenis
                            </label>
                            <select
                                :id="`member-vehicle-${index}-type`"
                                v-model="vehicle.type"
                                class="mt-1 w-full rounded-xl border border-slate-200 bg-white px-3 py-2.5 text-sm text-slate-900 focus:border-cyan-400 focus:outline-none"
                            >
                                <option class="bg-white text-slate-900">
                                    Mobil
                                </option>
                                <option class="bg-white text-slate-900">
                                    Motor
                                </option>
                            </select>
                        </div>
                    </div>
                </div>
            </div>

            <p
                class="rounded-xl bg-cyan-50 px-3 py-2.5 text-[11px] text-cyan-800 ring-1 ring-cyan-100"
            >
                Member baru dimulai dengan 0 stempel. Kumpulkan
                {{ stampTarget }} stempel untuk {{ brand.stampReward }}.
            </p>
        </div>

        <template #footer>
            <button
                type="button"
                class="flex-1 rounded-xl border border-slate-200 py-2.5 text-sm font-medium text-slate-600 transition hover:bg-slate-50"
                @click="isCreateOpen = false"
            >
                Batal
            </button>
            <button
                type="button"
                class="flex-1 rounded-xl bg-gradient-to-r from-cyan-500 to-sky-600 py-2.5 text-sm font-semibold text-white transition hover:from-cyan-600 hover:to-sky-700 disabled:cursor-not-allowed disabled:from-slate-300 disabled:to-slate-300"
                :disabled="!canCreate"
                @click="createCustomer"
            >
                Simpan member
            </button>
        </template>
    </ModalDialog>
</template>
