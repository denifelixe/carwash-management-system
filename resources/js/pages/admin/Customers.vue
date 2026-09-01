<script setup lang="ts">
import { Head, router, useForm } from '@inertiajs/vue3';
import {
    Car,
    Gift,
    Mail,
    Phone,
    Pencil,
    Plus,
    Power,
    Sparkles,
    Trash2,
    UserPlus,
    Users,
} from '@lucide/vue';
import { computed, ref, watch } from 'vue';
import {
    index as indexMembers,
    store as storeMember,
    update as updateMember,
    updateStatus as updateMemberStatus,
} from '@/actions/App/Http/Controllers/Admin/MemberController';
import DataPagination from '@/components/demo/DataPagination.vue';
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
import { useCarwashWorkflow } from '@/composables/useCarwashWorkflow';
import { formatPlate } from '@/lib/vehiclePlate';
import admin from '@/routes/demo/admin';
import type {
    CarwashBrand,
    CarwashCustomer,
    CarwashMemberDetail,
    CarwashMemberFilters,
    CarwashMemberStats,
    CarwashOrder,
    CarwashPaginated,
    CarwashReward,
    CarwashStampEntry,
    CarwashVehicle,
} from '@/types/demo';

const props = defineProps<{
    mode: 'demo' | 'live';
    brand: CarwashBrand;
    members: CarwashPaginated<CarwashCustomer>;
    stats: CarwashMemberStats;
    memberDetail: CarwashMemberDetail | null;
    filters: CarwashMemberFilters;
    statusFilters: string[];
    accountFilters: string[];
    vehicleTypes: string[];
    rewards: CarwashReward[];
    stampTarget: number;
    capabilities: { create: boolean; update: boolean };
}>();

const workflow = useCarwashWorkflow();

if (props.mode === 'demo') {
    workflow.hydrateCustomers(props.members.data);
}

const memberList = computed<CarwashCustomer[]>(() =>
    props.mode === 'demo' ? workflow.customers.value : props.members.data,
);

const search = ref<string>(props.filters.q);
const statusFilter = ref<string>(props.filters.status);
const accountFilter = ref<string>(props.filters.account);
const selectableStatusFilters = computed<string[]>(() =>
    props.statusFilters.filter((filter) => filter !== 'Semua'),
);
const allFiltersSelected = computed<boolean>(
    () => statusFilter.value === 'Semua' && accountFilter.value === 'Semua',
);
const detailCustomerId = ref<number | null>(
    props.memberDetail?.customer.id ?? null,
);
const detailTab = ref<'stamps' | 'orders'>('stamps');
const isCreateOpen = ref<boolean>(false);
const editingCustomerId = ref<number | null>(null);

const draft = ref({
    name: '',
    phone: '',
    email: '',
    vehicles: [emptyVehicle(true)],
});

const detailCustomer = computed<CarwashCustomer | null>(() => {
    if (props.mode === 'demo') {
        return (
            workflow.customers.value.find(
                (customer) => customer.id === detailCustomerId.value,
            ) ?? null
        );
    }

    return props.memberDetail?.customer.id === detailCustomerId.value
        ? props.memberDetail.customer
        : null;
});

/** Orders belonging to the customer in the drawer (BR-07). */
const detailOrders = computed<CarwashOrder[]>(() =>
    detailCustomer.value ? (props.memberDetail?.orders ?? []) : [],
);

const stampHistory = computed<CarwashStampEntry[]>(() =>
    detailCustomer.value ? (props.memberDetail?.stampHistory ?? []) : [],
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

const memberForm = useForm({
    name: '',
    phone: '',
    email: '' as string | null,
    vehicles: [] as Array<{
        id?: number;
        name: string;
        plate: string;
        type: string;
    }>,
});
const statusForm = useForm({ is_active: true });

let searchTimer: ReturnType<typeof setTimeout> | undefined;

watch(search, () => {
    clearTimeout(searchTimer);
    searchTimer = setTimeout(() => visit({ q: search.value, page: 1 }), 300);
});

watch(
    () => props.memberDetail?.customer.id,
    (memberId) => {
        if (memberId !== undefined) {
            detailCustomerId.value = memberId;
        }
    },
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

    router.reload({
        only: ['memberDetail'],
        data: { member: customer.id },
    });
}

function closeDetail(): void {
    detailCustomerId.value = null;
}

function visit(overrides: Partial<CarwashMemberFilters> = {}): void {
    const filters = {
        q: search.value,
        status: statusFilter.value,
        account: accountFilter.value,
        page: props.filters.page,
        ...overrides,
    };

    router.get(
        props.mode === 'demo' ? admin.members.url() : indexMembers.url(),
        filters,
        { preserveState: true, preserveScroll: true, replace: true },
    );
}

function resetFilters(): void {
    statusFilter.value = 'Semua';
    accountFilter.value = 'Semua';
    visit({ status: 'Semua', account: 'Semua', page: 1 });
}

function toggleStatusFilter(status: string): void {
    const nextStatus = statusFilter.value === status ? 'Semua' : status;

    statusFilter.value = nextStatus;
    visit({ status: nextStatus, page: 1 });
}

function toggleAccountFilter(account: string): void {
    const nextAccount = accountFilter.value === account ? 'Semua' : account;

    accountFilter.value = nextAccount;
    visit({ account: nextAccount, page: 1 });
}

function openCreateForm(): void {
    if (!props.capabilities.create) {
        return;
    }

    editingCustomerId.value = null;
    draft.value = {
        name: '',
        phone: '',
        email: '',
        vehicles: [emptyVehicle(true)],
    };
    memberForm.clearErrors();
    isCreateOpen.value = true;
}

function openEditForm(): void {
    if (!props.capabilities.update || !detailCustomer.value) {
        return;
    }

    editingCustomerId.value = detailCustomer.value.id;
    draft.value = {
        name: detailCustomer.value.name,
        phone: detailCustomer.value.phone,
        email: detailCustomer.value.email,
        vehicles: detailCustomer.value.vehicles.map((vehicle) => ({
            ...vehicle,
        })),
    };
    memberForm.clearErrors();
    isCreateOpen.value = true;
}

function saveMember(): void {
    const requiredCapability =
        editingCustomerId.value === null
            ? props.capabilities.create
            : props.capabilities.update;

    if (!requiredCapability || !canCreate.value) {
        return;
    }

    if (props.mode === 'live') {
        saveLiveMember();

        return;
    }

    saveDemoMember();
}

function saveLiveMember(): void {
    memberForm.name = draft.value.name;
    memberForm.phone = draft.value.phone;
    memberForm.email = draft.value.email || null;
    memberForm.vehicles = draft.value.vehicles.map((vehicle) => ({
        ...(vehicle.id ? { id: vehicle.id } : {}),
        name: vehicle.name,
        plate: vehicle.plate,
        type: vehicle.type,
    }));

    const action =
        editingCustomerId.value === null
            ? storeMember()
            : updateMember(editingCustomerId.value);

    memberForm.submit(action, {
        preserveScroll: true,
        onSuccess: () => {
            isCreateOpen.value = false;
            editingCustomerId.value = null;
        },
    });
}

function saveDemoMember(): void {
    if (editingCustomerId.value !== null) {
        const index = workflow.customers.value.findIndex(
            (customer) => customer.id === editingCustomerId.value,
        );

        if (index >= 0) {
            const current = workflow.customers.value[index];
            const vehicles = normalizedDraftVehicles();
            workflow.customers.value[index] = {
                ...current,
                name: draft.value.name.trim(),
                phone: draft.value.phone.trim(),
                email: draft.value.email.trim(),
                vehicle: vehicles[0].name,
                plate: vehicles[0].plate,
                vehicles,
                initials: initialsOf(draft.value.name),
            };
        }

        isCreateOpen.value = false;

        return;
    }

    createCustomer();
}

function normalizedDraftVehicles(): CarwashVehicle[] {
    return draft.value.vehicles.map((vehicle, index) => ({
        ...vehicle,
        name: vehicle.name.trim(),
        plate: vehicle.plate.replace(/\s+/g, '').toUpperCase(),
        isPrimary: index === 0,
    }));
}

function createCustomer(): void {
    const sequence = workflow.customers.value.length + 1;
    const vehicles = normalizedDraftVehicles();
    const primaryVehicle = vehicles[0];

    workflow.addCustomer({
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
    });

    draft.value = {
        name: '',
        phone: '',
        email: '',
        vehicles: [emptyVehicle(true)],
    };
    isCreateOpen.value = false;
}

function toggleStatus(customer: CarwashCustomer): void {
    if (!props.capabilities.update) {
        return;
    }

    const isActive = customer.status !== 'aktif';

    if (props.mode === 'demo') {
        const target = workflow.customers.value.find(
            (candidate) => candidate.id === customer.id,
        );

        if (target) {
            target.status = isActive ? 'aktif' : 'tidak aktif';
        }

        return;
    }

    statusForm.is_active = isActive;
    statusForm.submit(updateMemberStatus(customer.id), {
        preserveScroll: true,
    });
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
                :value="String(stats.total)"
                :caption="`${stats.active} aktif`"
                :icon="Users"
            />
            <StatCard
                label="Punya akun portal"
                :value="String(stats.withAccount)"
                caption="terdaftar di aplikasi member"
                :icon="UserPlus"
                tone="emerald"
            />
            <StatCard
                label="Stempel beredar"
                :value="formatNumber(stats.circulatingStamps)"
                caption="belum ditukar reward"
                :icon="Sparkles"
                tone="amber"
            />
            <StatCard
                label="Target kartu"
                :value="`${stampTarget} stempel`"
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
                        {{ members.meta.total }} member ditemukan
                    </p>
                </div>
                <div class="flex flex-wrap items-center gap-2">
                    <DataToolbar
                        v-model:search="search"
                        placeholder="Cari nama / plat / HP"
                    >
                        <div
                            class="flex flex-wrap items-center gap-1.5"
                            aria-label="Filter member"
                        >
                            <button
                                type="button"
                                class="rounded-full px-3 py-1.5 text-xs font-medium transition"
                                :class="
                                    allFiltersSelected
                                        ? 'bg-slate-900 text-white'
                                        : 'bg-slate-100 text-slate-600 hover:bg-slate-200'
                                "
                                :aria-pressed="allFiltersSelected"
                                @click="resetFilters"
                            >
                                Semua
                            </button>
                            <button
                                v-for="filter in selectableStatusFilters"
                                :key="filter"
                                type="button"
                                class="rounded-full px-3 py-1.5 text-xs font-medium capitalize transition"
                                :class="
                                    statusFilter === filter
                                        ? 'bg-slate-900 text-white'
                                        : 'bg-slate-100 text-slate-600 hover:bg-slate-200'
                                "
                                :aria-pressed="statusFilter === filter"
                                @click="toggleStatusFilter(filter)"
                            >
                                {{ filter }}
                            </button>
                            <span
                                class="mx-0.5 h-5 w-px bg-slate-200"
                                aria-hidden="true"
                            />
                            <button
                                v-for="filter in accountFilters"
                                :key="filter"
                                type="button"
                                class="rounded-full px-3 py-1.5 text-xs font-medium transition"
                                :class="
                                    accountFilter === filter
                                        ? 'bg-slate-900 text-white'
                                        : 'bg-slate-100 text-slate-600 hover:bg-slate-200'
                                "
                                :aria-pressed="accountFilter === filter"
                                @click="toggleAccountFilter(filter)"
                            >
                                {{ filter }}
                            </button>
                        </div>
                    </DataToolbar>
                    <button
                        v-if="capabilities.create"
                        type="button"
                        class="flex items-center gap-2 rounded-xl bg-gradient-to-r from-cyan-500 to-sky-600 px-3 py-2 text-sm font-medium text-white shadow-lg shadow-cyan-500/25 transition hover:from-cyan-600 hover:to-sky-700"
                        @click="openCreateForm"
                    >
                        <Plus class="h-4 w-4" />
                        Daftar Member
                    </button>
                </div>
            </div>

            <div v-if="memberList.length > 0" class="overflow-x-auto">
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
                            v-for="customer in memberList"
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
                                                {{ formatPlate(vehicle.plate) }}
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
                                <div class="flex flex-col items-start gap-1.5">
                                    <StatusPill
                                        :status="customer.status"
                                        :label="
                                            customer.status === 'aktif'
                                                ? 'Member aktif'
                                                : 'Member tidak aktif'
                                        "
                                    />
                                    <StatusPill
                                        :status="
                                            customer.hasAccount
                                                ? 'aktif'
                                                : 'tidak aktif'
                                        "
                                        :label="
                                            customer.hasAccount
                                                ? 'Punya akun portal'
                                                : 'Tidak punya akun portal'
                                        "
                                    />
                                </div>
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
            <DataPagination
                :meta="members.meta"
                @change="visit({ page: $event })"
            />
        </section>
    </div>

    <!-- Customer detail -->
    <SlideOver
        :open="detailCustomerId !== null"
        :title="detailCustomer?.name"
        :caption="detailCustomer?.memberId"
        @close="closeDetail"
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
                    <div class="flex flex-col items-end gap-1.5">
                        <StatusPill
                            :status="detailCustomer.status"
                            :label="
                                detailCustomer.status === 'aktif'
                                    ? 'Member aktif'
                                    : 'Member tidak aktif'
                            "
                        />
                        <StatusPill
                            :status="
                                detailCustomer.hasAccount
                                    ? 'aktif'
                                    : 'tidak aktif'
                            "
                            :label="
                                detailCustomer.hasAccount
                                    ? 'Punya akun portal'
                                    : 'Tidak punya akun portal'
                            "
                        />
                    </div>
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
                    <span
                        class="min-w-0 truncate text-sm"
                        :class="
                            detailCustomer.email
                                ? 'text-slate-700'
                                : 'text-slate-400'
                        "
                    >
                        {{ detailCustomer.email || 'Tidak ada email' }}
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
                                    {{ formatPlate(vehicle.plate) }} ·
                                    {{ vehicle.type }}
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

            <div v-if="capabilities.update" class="grid grid-cols-2 gap-2">
                <button
                    type="button"
                    class="flex items-center justify-center gap-2 rounded-xl border border-slate-200 px-3 py-2 text-xs font-medium text-slate-700 transition hover:bg-slate-50"
                    @click="openEditForm"
                >
                    <Pencil class="h-3.5 w-3.5" />
                    Ubah data
                </button>
                <button
                    type="button"
                    class="flex items-center justify-center gap-2 rounded-xl border border-slate-200 px-3 py-2 text-xs font-medium text-slate-700 transition hover:bg-slate-50"
                    @click="toggleStatus(detailCustomer)"
                >
                    <Power class="h-3.5 w-3.5" />
                    {{
                        detailCustomer.status === 'aktif'
                            ? 'Nonaktifkan'
                            : 'Aktifkan'
                    }}
                </button>
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
        <div v-else class="animate-pulse space-y-4">
            <div class="h-28 rounded-2xl bg-slate-100"></div>
            <div class="h-16 rounded-xl bg-slate-100"></div>
            <div class="h-48 rounded-xl bg-slate-100"></div>
        </div>
    </SlideOver>

    <!-- Register customer -->
    <ModalDialog
        :open="isCreateOpen"
        title="Daftarkan member"
        :caption="
            editingCustomerId === null
                ? 'Data dasar untuk mulai mengumpulkan stempel'
                : 'Perbarui profil dan kendaraan member'
        "
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
                                <option
                                    v-for="vehicleType in vehicleTypes"
                                    :key="vehicleType"
                                    class="bg-white text-slate-900"
                                    :value="vehicleType"
                                >
                                    {{ vehicleType }}
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
            <p
                v-if="Object.keys(memberForm.errors).length > 0"
                class="rounded-xl bg-rose-50 px-3 py-2.5 text-xs text-rose-700 ring-1 ring-rose-100"
            >
                {{ Object.values(memberForm.errors)[0] }}
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
                :disabled="!canCreate || memberForm.processing"
                @click="saveMember"
            >
                Simpan member
            </button>
        </template>
    </ModalDialog>
</template>
