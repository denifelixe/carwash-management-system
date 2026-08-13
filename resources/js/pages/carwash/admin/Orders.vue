<script setup lang="ts">
import { Head } from '@inertiajs/vue3';
import {
    Car,
    CircleCheck,
    ClipboardList,
    Clock,
    Plus,
    Search,
    Sparkles,
    Wallet,
} from '@lucide/vue';
import { computed, ref } from 'vue';
import Multiselect from 'vue-multiselect';
import 'vue-multiselect/dist/vue-multiselect.css';
import CollapsibleSummary from '@/components/carwash/CollapsibleSummary.vue';
import DataToolbar from '@/components/carwash/DataToolbar.vue';
import EmptyState from '@/components/carwash/EmptyState.vue';
import ModalDialog from '@/components/carwash/ModalDialog.vue';
import SlideOver from '@/components/carwash/SlideOver.vue';
import StatCard from '@/components/carwash/StatCard.vue';
import StatusPill from '@/components/carwash/StatusPill.vue';
import { formatCurrency } from '@/composables/useCarwashFormat';
import type {
    CarwashBrand,
    CarwashCrewMember,
    CarwashCustomer,
    CarwashOrder,
    CarwashReward,
    CarwashService,
    CarwashVehicle,
} from '@/types/carwash';

const props = defineProps<{
    brand: CarwashBrand;
    orders: CarwashOrder[];
    services: CarwashService[];
    serviceCategories: string[];
    customers: CarwashCustomer[];
    rewards: CarwashReward[];
    crew: CarwashCrewMember[];
    paymentMethods: string[];
}>();

const statusFlow = ['menunggu', 'proses', 'selesai'] as const;

type CustomerMode = 'existing' | 'walk-in' | 'new-member';

type CustomerOption = {
    key: `customer-${number}-vehicle-${number}`;
    label: string;
    customer: CarwashCustomer;
    vehicle: CarwashVehicle;
};

const customerTabs: { key: CustomerMode; label: string }[] = [
    { key: 'existing', label: 'Customer terdaftar' },
    { key: 'walk-in', label: 'Non member' },
    { key: 'new-member', label: 'Member baru' },
];

/** Local copies so redeeming a reward can spend the member's stamps. */
const customerList = ref<CarwashCustomer[]>(
    props.customers.map((customer) => ({ ...customer })),
);

/** Built from the local copies so the picker reflects a spent stamp balance. */
const customerOptions: CustomerOption[] = customerList.value.flatMap(
    (customer) =>
        customer.vehicles.map((vehicle, vehicleIndex) => ({
            key: `customer-${customer.id}-vehicle-${vehicleIndex}`,
            label: customer.name,
            customer,
            vehicle,
        })),
);

const orderList = ref<CarwashOrder[]>(
    props.orders.map((order) => ({ ...order })),
);

const search = ref<string>('');
const statusFilter = ref<string>('Semua');
const detailOrderId = ref<number | null>(null);
const isCreateOpen = ref<boolean>(false);
const customerQuery = ref<string>('');
const customerMode = ref<CustomerMode>('existing');
const selectedCustomerOption = ref<CustomerOption | null>(null);

const draft = ref({
    customerId: null as number | null,
    walkInName: '',
    newMemberPhone: '',
    vehicle: '',
    plate: '',
    serviceIds: [] as number[],
    rewardId: null as number | null,
});

const filterOptions = ['Semua', 'menunggu', 'proses', 'selesai'];

const visibleCustomerOptions = computed<CustomerOption[]>(() => {
    const query = normalizeCustomerSearch(customerQuery.value);

    if (query === '') {
        return customerOptions;
    }

    return customerOptions.filter(({ customer, vehicle }) =>
        [customer.name, customer.phone, vehicle.plate, vehicle.name].some(
            (value) => normalizeCustomerSearch(value).includes(query),
        ),
    );
});

const filteredOrders = computed<CarwashOrder[]>(() => {
    const query = search.value.trim().toLowerCase();

    return orderList.value.filter((order) => {
        const matchesStatus =
            statusFilter.value === 'Semua' ||
            order.status === statusFilter.value;
        const matchesQuery =
            query === '' ||
            order.orderNo.toLowerCase().includes(query) ||
            order.customer.toLowerCase().includes(query) ||
            order.plate.toLowerCase().includes(query);

        return matchesStatus && matchesQuery;
    });
});

const detailOrder = computed<CarwashOrder | null>(
    () =>
        orderList.value.find((order) => order.id === detailOrderId.value) ??
        null,
);

/** Money actually in the drawer, so deposits count for what they are worth. */
const todayRevenue = computed<number>(() =>
    orderList.value.reduce((total, order) => total + order.paidAmount, 0),
);

const unpaidTotal = computed<number>(() =>
    orderList.value
        .filter((order) => order.paymentStatus !== 'lunas')
        .reduce((total, order) => total + order.total - order.paidAmount, 0),
);

const activeCount = computed<number>(
    () => orderList.value.filter((order) => order.status !== 'selesai').length,
);

const stampsIssued = computed<number>(() =>
    orderList.value
        .filter((order) => order.status === 'selesai')
        .reduce((total, order) => total + order.stampsEarned, 0),
);

/** Keeps the headline numbers visible while the summary cards stay collapsed. */
const summaryCaption = computed<string>(
    () =>
        `${activeCount.value} order aktif • ${formatCurrency(todayRevenue.value)} diterima`,
);

const draftCustomer = computed<CarwashCustomer | null>(
    () =>
        customerList.value.find(
            (customer) => customer.id === draft.value.customerId,
        ) ?? null,
);

const draftServices = computed<CarwashService[]>(() =>
    props.services.filter((service) =>
        draft.value.serviceIds.includes(service.id),
    ),
);

const draftSubtotal = computed<number>(() =>
    draftServices.value.reduce((total, service) => total + service.price, 0),
);

/**
 * Rewards the registered member has enough stamps for (BR-04, BR-13). Redeeming
 * happens here at intake, not at the till, so the cashier only ever sees the
 * amount actually payable.
 */
const redeemableRewards = computed<CarwashReward[]>(() => {
    const customer = draftCustomer.value;

    if (customerMode.value !== 'existing' || !customer) {
        return [];
    }

    return props.rewards.filter(
        (reward) =>
            reward.status === 'aktif' &&
            reward.requiredStamps <= customer.stamps,
    );
});

const appliedReward = computed<CarwashReward | null>(
    () =>
        redeemableRewards.value.find(
            (reward) => reward.id === draft.value.rewardId,
        ) ?? null,
);

/**
 * A redeemed reward covers the cheapest service on the order, capped at the
 * subtotal — enough to make the discount believable in the demo.
 */
const rewardDiscount = computed<number>(() => {
    if (!appliedReward.value || draftServices.value.length === 0) {
        return 0;
    }

    const cheapest = Math.min(
        ...draftServices.value.map((service) => service.price),
    );

    return Math.min(cheapest, draftSubtotal.value);
});

const draftTotal = computed<number>(() =>
    Math.max(draftSubtotal.value - rewardDiscount.value, 0),
);

const draftStamps = computed<number>(() =>
    draft.value.customerId === null
        ? 0
        : draftServices.value.reduce(
              (total, service) => total + service.stamps,
              0,
          ),
);

const hasCustomer = computed<boolean>(() => {
    if (customerMode.value === 'existing') {
        return draft.value.customerId !== null;
    }

    if (customerMode.value === 'new-member') {
        return (
            draft.value.walkInName.trim() !== '' &&
            draft.value.newMemberPhone.trim() !== ''
        );
    }

    return draft.value.walkInName.trim() !== '';
});

const canCreate = computed<boolean>(
    () =>
        draftServices.value.length > 0 &&
        draft.value.plate.trim() !== '' &&
        hasCustomer.value,
);

function normalizeCustomerSearch(value: string): string {
    return value.toLocaleLowerCase('id-ID').replace(/[^a-z0-9]/g, '');
}

/** Moves an order along menunggu → proses → selesai, awarding stamps at the end. */
function advanceStatus(order: CarwashOrder): void {
    const index = statusFlow.indexOf(
        order.status as (typeof statusFlow)[number],
    );

    if (index === -1 || index === statusFlow.length - 1) {
        return;
    }

    order.status = statusFlow[index + 1];

    if (order.status === 'proses' && order.bay === '—') {
        order.bay = 'Bay 1';
        order.crew = props.crew[0].name.split(' ')[0];
    }
}

function markPaid(order: CarwashOrder): void {
    order.paymentStatus = 'lunas';
    order.paidAmount = order.total;

    if (order.invoice === '—') {
        order.invoice = order.orderNo.replace('ORD', 'ZW');
    }
}

function pickCustomer(option: CustomerOption): void {
    selectedCustomerOption.value = option;
    draft.value.customerId = option.customer.id;
    draft.value.walkInName = '';
    draft.value.newMemberPhone = '';
    draft.value.vehicle = option.vehicle.name;
    draft.value.plate = option.vehicle.plate;
    draft.value.rewardId = null;
}

function updateCustomerQuery(query: string): void {
    customerQuery.value = query;
}

/** Clears whichever customer the previous tab captured so tabs never mix input. */
function clearCustomer(): void {
    customerQuery.value = '';
    selectedCustomerOption.value = null;
    draft.value.customerId = null;
    draft.value.walkInName = '';
    draft.value.newMemberPhone = '';
    draft.value.vehicle = '';
    draft.value.plate = '';
    draft.value.rewardId = null;
}

function selectCustomerMode(mode: CustomerMode): void {
    if (customerMode.value === mode) {
        return;
    }

    customerMode.value = mode;
    clearCustomer();
}

function toggleDraftService(serviceId: number): void {
    draft.value.serviceIds = draft.value.serviceIds.includes(serviceId)
        ? draft.value.serviceIds.filter((id) => id !== serviceId)
        : [...draft.value.serviceIds, serviceId];
}

function resetDraft(): void {
    draft.value = {
        customerId: null,
        walkInName: '',
        newMemberPhone: '',
        vehicle: '',
        plate: '',
        serviceIds: [],
        rewardId: null,
    };
    customerQuery.value = '';
    customerMode.value = 'existing';
    selectedCustomerOption.value = null;
}

function createOrder(): void {
    if (!canCreate.value) {
        return;
    }

    const sequence = orderList.value.length + 13;
    const customer = draftCustomer.value;
    const reward = appliedReward.value;

    /* The stamps are spent the moment the reward is written onto the order. */
    if (customer && reward) {
        customer.stamps -= reward.requiredStamps;
    }

    orderList.value = [
        {
            id: sequence,
            orderNo: `ORD-2608${String(sequence).padStart(4, '0')}`,
            invoice: '—',
            time: 'Baru saja',
            customerId: customer?.id ?? null,
            customer: customer?.name ?? draft.value.walkInName,
            phone:
                (customer?.phone ?? draft.value.newMemberPhone.trim()) || '—',
            vehicle: draft.value.vehicle || '—',
            plate: draft.value.plate.toUpperCase(),
            items: draftServices.value
                .map((service) => service.name)
                .join(', '),
            serviceIds: [...draft.value.serviceIds],
            total: draftTotal.value,
            discount: rewardDiscount.value,
            reward: reward?.name ?? '—',
            paidAmount: 0,
            payment: '—',
            paymentStatus: 'belum bayar',
            status: 'menunggu',
            stampsEarned: draftStamps.value,
            crew: 'Menunggu crew',
            bay: '—',
            source: 'walk-in',
        },
        ...orderList.value,
    ];

    resetDraft();
    isCreateOpen.value = false;
}
</script>

<template>
    <Head :title="`${brand.name} — Order / Transaksi`" />

    <div class="space-y-4">
        <!-- Summary -->
        <CollapsibleSummary
            title="Ringkasan hari ini"
            :caption="summaryCaption"
        >
            <StatCard
                label="Order aktif"
                :value="String(activeCount)"
                caption="menunggu & sedang dikerjakan"
                :icon="Car"
            />
            <StatCard
                label="Pembayaran diterima"
                :value="formatCurrency(todayRevenue)"
                :caption="`${orderList.length} order tercatat`"
                :icon="Wallet"
                tone="emerald"
            />
            <StatCard
                label="Belum dibayar"
                :value="formatCurrency(unpaidTotal)"
                caption="sisa tagihan di kasir"
                :icon="Clock"
                tone="amber"
            />
            <StatCard
                label="Stempel diberikan"
                :value="String(stampsIssued)"
                caption="dari order yang selesai"
                :icon="Sparkles"
            />
        </CollapsibleSummary>

        <!-- Order table -->
        <section
            class="rounded-2xl border border-slate-200/80 bg-white shadow-sm"
        >
            <div
                class="flex flex-wrap items-center justify-between gap-3 border-b border-slate-100 p-5"
            >
                <div>
                    <h3 class="text-sm font-semibold text-slate-900">
                        Daftar order
                    </h3>
                    <p class="mt-0.5 text-xs text-slate-500">
                        {{ filteredOrders.length }} order ditampilkan
                    </p>
                </div>
                <div class="flex flex-wrap items-center gap-2">
                    <DataToolbar
                        v-model:search="search"
                        placeholder="Cari order / plat"
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
                        Buat Order
                    </button>
                </div>
            </div>

            <div v-if="filteredOrders.length > 0" class="overflow-x-auto">
                <table class="w-full min-w-[900px] text-sm">
                    <thead>
                        <tr
                            class="border-b border-slate-100 text-left text-[11px] font-medium tracking-wider text-slate-400 uppercase"
                        >
                            <th class="px-5 py-3">Order</th>
                            <th class="px-5 py-3">Customer</th>
                            <th class="px-5 py-3">Kendaraan</th>
                            <th class="px-5 py-3">Layanan</th>
                            <th class="px-5 py-3">Status</th>
                            <th class="px-5 py-3">Pembayaran</th>
                            <th class="px-5 py-3 text-right">Total</th>
                            <th class="px-5 py-3"></th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-50">
                        <tr
                            v-for="order in filteredOrders"
                            :key="order.id"
                            class="transition hover:bg-slate-50/70"
                        >
                            <td class="px-5 py-3.5">
                                <p class="font-medium text-slate-900">
                                    {{ order.orderNo }}
                                </p>
                                <p class="text-[11px] text-slate-500">
                                    {{ order.time }} • {{ order.source }}
                                </p>
                            </td>
                            <td class="px-5 py-3.5">
                                <p class="text-slate-700">
                                    {{ order.customer }}
                                </p>
                                <p class="text-[11px] text-slate-500">
                                    {{ order.phone }}
                                </p>
                            </td>
                            <td class="px-5 py-3.5">
                                <p class="text-slate-700">
                                    {{ order.vehicle }}
                                </p>
                                <p class="text-[11px] text-slate-500">
                                    {{ order.plate }}
                                </p>
                            </td>
                            <td
                                class="max-w-[200px] px-5 py-3.5 text-slate-600"
                            >
                                {{ order.items }}
                            </td>
                            <td class="px-5 py-3.5">
                                <StatusPill :status="order.status" />
                                <p class="mt-1 text-[11px] text-slate-400">
                                    {{ order.bay }}
                                </p>
                            </td>
                            <td class="px-5 py-3.5">
                                <StatusPill :status="order.paymentStatus" />
                                <p class="mt-1 text-[11px] text-slate-400">
                                    {{ order.payment }}
                                </p>
                            </td>
                            <td
                                class="px-5 py-3.5 text-right font-medium text-slate-900 tabular-nums"
                            >
                                {{ formatCurrency(order.total) }}
                            </td>
                            <td class="px-5 py-3.5 text-right">
                                <button
                                    type="button"
                                    class="rounded-lg px-3 py-1.5 text-xs font-medium text-cyan-700 transition hover:bg-cyan-50"
                                    @click="detailOrderId = order.id"
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
                :icon="ClipboardList"
                title="Tidak ada order yang cocok"
                caption="Ubah kata kunci pencarian atau filter status."
            />
        </section>
    </div>

    <!-- Order detail -->
    <SlideOver
        :open="detailOrder !== null"
        :title="detailOrder?.orderNo"
        :caption="`${detailOrder?.customer} • ${detailOrder?.time}`"
        @close="detailOrderId = null"
    >
        <div v-if="detailOrder" class="space-y-5">
            <div class="flex gap-2">
                <StatusPill :status="detailOrder.status" />
                <StatusPill :status="detailOrder.paymentStatus" />
            </div>

            <div class="grid grid-cols-2 gap-3">
                <div class="rounded-xl bg-slate-50 p-3">
                    <p class="text-[11px] text-slate-500">Kendaraan</p>
                    <p class="mt-0.5 text-sm font-medium text-slate-900">
                        {{ detailOrder.vehicle }}
                    </p>
                    <p class="text-[11px] text-slate-500">
                        {{ detailOrder.plate }}
                    </p>
                </div>
                <div class="rounded-xl bg-slate-50 p-3">
                    <p class="text-[11px] text-slate-500">Crew & bay</p>
                    <p class="mt-0.5 text-sm font-medium text-slate-900">
                        {{ detailOrder.crew }}
                    </p>
                    <p class="text-[11px] text-slate-500">
                        {{ detailOrder.bay }}
                    </p>
                </div>
            </div>

            <div>
                <p class="text-xs font-medium text-slate-500">
                    Layanan dipesan
                </p>
                <ul class="mt-2 space-y-2">
                    <li
                        v-for="serviceId in detailOrder.serviceIds"
                        :key="serviceId"
                        class="flex items-center gap-3 rounded-xl border border-slate-200 p-3"
                    >
                        <span class="text-lg">
                            {{
                                services.find(
                                    (service) => service.id === serviceId,
                                )?.icon
                            }}
                        </span>
                        <span class="min-w-0 flex-1 text-sm text-slate-800">
                            {{
                                services.find(
                                    (service) => service.id === serviceId,
                                )?.name
                            }}
                        </span>
                        <span
                            class="text-sm font-medium text-slate-900 tabular-nums"
                        >
                            {{
                                formatCurrency(
                                    services.find(
                                        (service) => service.id === serviceId,
                                    )?.price ?? 0,
                                )
                            }}
                        </span>
                    </li>
                </ul>
            </div>

            <dl class="space-y-2 rounded-2xl bg-slate-50 p-4 text-sm">
                <div class="flex justify-between">
                    <dt class="text-slate-500">Invoice</dt>
                    <dd class="font-medium text-slate-800">
                        {{ detailOrder.invoice }}
                    </dd>
                </div>
                <div class="flex justify-between">
                    <dt class="text-slate-500">Metode bayar</dt>
                    <dd class="text-slate-800">{{ detailOrder.payment }}</dd>
                </div>
                <div class="flex justify-between">
                    <dt class="text-slate-500">Stempel diberikan</dt>
                    <dd class="font-medium text-emerald-600 tabular-nums">
                        +{{ detailOrder.stampsEarned }}
                    </dd>
                </div>
                <div
                    v-if="detailOrder.discount > 0"
                    class="flex justify-between gap-4"
                >
                    <dt class="shrink-0 text-slate-500">Reward dipakai</dt>
                    <dd class="min-w-0 text-right text-cyan-700">
                        {{ detailOrder.reward }}
                        <span class="text-emerald-600 tabular-nums">
                            (−{{ formatCurrency(detailOrder.discount) }})
                        </span>
                    </dd>
                </div>
                <div
                    class="flex justify-between border-t border-slate-200 pt-2 text-base"
                >
                    <dt class="font-medium text-slate-600">Total</dt>
                    <dd class="font-semibold text-slate-900 tabular-nums">
                        {{ formatCurrency(detailOrder.total) }}
                    </dd>
                </div>
                <template v-if="detailOrder.paymentStatus !== 'lunas'">
                    <div class="flex justify-between">
                        <dt class="text-slate-500">Sudah dibayar</dt>
                        <dd class="text-emerald-600 tabular-nums">
                            {{ formatCurrency(detailOrder.paidAmount) }}
                        </dd>
                    </div>
                    <div class="flex justify-between">
                        <dt class="font-medium text-slate-600">Sisa tagihan</dt>
                        <dd class="font-semibold text-amber-600 tabular-nums">
                            {{
                                formatCurrency(
                                    detailOrder.total - detailOrder.paidAmount,
                                )
                            }}
                        </dd>
                    </div>
                </template>
            </dl>

            <p
                v-if="detailOrder.status === 'selesai'"
                class="flex items-center gap-2 rounded-xl bg-emerald-50 px-3 py-2.5 text-xs font-medium text-emerald-700"
            >
                <CircleCheck class="h-4 w-4 shrink-0" />
                Order selesai — stempel sudah masuk ke akun customer.
            </p>
        </div>

        <template #footer>
            <div v-if="detailOrder" class="flex gap-2">
                <button
                    v-if="detailOrder.paymentStatus !== 'lunas'"
                    type="button"
                    class="flex-1 rounded-xl border border-slate-200 py-2.5 text-sm font-medium text-slate-600 transition hover:bg-slate-50"
                    @click="markPaid(detailOrder)"
                >
                    Tandai lunas
                </button>
                <button
                    v-if="detailOrder.status !== 'selesai'"
                    type="button"
                    class="flex-1 rounded-xl bg-gradient-to-r from-cyan-500 to-sky-600 py-2.5 text-sm font-semibold text-white transition hover:from-cyan-600 hover:to-sky-700"
                    @click="advanceStatus(detailOrder)"
                >
                    {{
                        detailOrder.status === 'menunggu'
                            ? 'Mulai pengerjaan'
                            : 'Selesaikan order'
                    }}
                </button>
                <button
                    v-else
                    type="button"
                    class="flex-1 rounded-xl bg-slate-900 py-2.5 text-sm font-semibold text-white transition hover:bg-slate-800"
                    @click="detailOrderId = null"
                >
                    Tutup
                </button>
            </div>
        </template>
    </SlideOver>

    <!-- Create order -->
    <ModalDialog
        :open="isCreateOpen"
        title="Buat order baru"
        caption="Catat kendaraan yang baru datang"
        size="lg"
        @close="isCreateOpen = false"
    >
        <div class="space-y-5">
            <!-- Customer -->
            <div>
                <label
                    for="order-customer"
                    class="text-[11px] font-medium tracking-wider text-slate-400 uppercase"
                >
                    Customer
                </label>

                <div
                    class="mt-2 grid grid-cols-3 gap-1 rounded-xl bg-slate-100 p-1"
                    role="tablist"
                >
                    <button
                        v-for="tab in customerTabs"
                        :key="tab.key"
                        type="button"
                        role="tab"
                        :aria-selected="customerMode === tab.key"
                        class="rounded-lg px-2 py-2 text-xs leading-tight transition focus-visible:ring-2 focus-visible:ring-cyan-400 focus-visible:outline-none"
                        :class="
                            customerMode === tab.key
                                ? 'bg-cyan-600 font-semibold text-white shadow-sm shadow-cyan-600/30'
                                : 'font-medium text-slate-500 hover:bg-white/70 hover:text-slate-700'
                        "
                        @click="selectCustomerMode(tab.key)"
                    >
                        {{ tab.label }}
                    </button>
                </div>

                <div
                    v-if="
                        customerMode === 'existing' && !selectedCustomerOption
                    "
                    class="relative mt-3"
                >
                    <!--
                        `.multiselect--active` gets `z-index: 50`, so the icon
                        must sit above that or the focused input's white
                        background paints over it.
                    -->
                    <Search
                        class="pointer-events-none absolute top-3.5 left-3 z-[60] h-4 w-4 text-slate-400"
                    />
                    <Multiselect
                        id="order-customer"
                        v-model="selectedCustomerOption"
                        class="customer-search"
                        :options="visibleCustomerOptions"
                        :internal-search="false"
                        :allow-empty="false"
                        :show-labels="false"
                        :max-height="260"
                        track-by="key"
                        label="label"
                        placeholder="Cari plat nomor, nama, atau telepon"
                        @search-change="updateCustomerQuery"
                        @select="pickCustomer"
                    >
                        <template #singleLabel="{ option }">
                            <span class="block truncate text-sm text-slate-700">
                                {{ option.label }} — {{ option.vehicle.plate }}
                            </span>
                        </template>
                        <template #option="{ option }">
                            <div
                                class="flex items-center justify-between gap-3 px-3 py-2.5"
                            >
                                <div class="min-w-0">
                                    <p
                                        class="truncate text-sm font-medium text-slate-800"
                                    >
                                        {{ option.customer.name }}
                                    </p>
                                    <p class="truncate text-xs text-slate-500">
                                        {{ option.customer.phone }}
                                    </p>
                                </div>
                                <div class="shrink-0 text-right">
                                    <p
                                        class="text-xs font-semibold text-slate-700"
                                    >
                                        {{ option.vehicle.plate }}
                                    </p>
                                    <p class="text-[10px] text-slate-400">
                                        {{ option.vehicle.name }}
                                    </p>
                                </div>
                            </div>
                        </template>
                        <template #noResult>
                            <p class="px-3 py-3 text-sm text-slate-500">
                                Tidak ada customer yang cocok — pakai tab
                                <span class="font-medium text-slate-700">
                                    Member baru
                                </span>
                                atau
                                <span class="font-medium text-slate-700">
                                    Non member
                                </span>
                                .
                            </p>
                        </template>
                    </Multiselect>
                </div>

                <div
                    v-else-if="
                        customerMode === 'existing' && selectedCustomerOption
                    "
                    class="mt-3 rounded-2xl border border-cyan-200 bg-cyan-50/60 p-4"
                >
                    <div class="flex items-start gap-3">
                        <div
                            class="flex h-10 w-10 shrink-0 items-center justify-center rounded-xl bg-cyan-600 text-sm font-semibold text-white"
                        >
                            {{ selectedCustomerOption.customer.initials }}
                        </div>
                        <div class="min-w-0 flex-1">
                            <div
                                class="flex flex-wrap items-start justify-between gap-2"
                            >
                                <div class="min-w-0">
                                    <p
                                        class="truncate text-sm font-semibold text-slate-900"
                                    >
                                        {{
                                            selectedCustomerOption.customer.name
                                        }}
                                    </p>
                                    <p class="text-xs text-slate-500">
                                        {{
                                            selectedCustomerOption.customer
                                                .phone
                                        }}
                                        ·
                                        {{
                                            selectedCustomerOption.customer
                                                .memberId
                                        }}
                                    </p>
                                </div>
                                <button
                                    type="button"
                                    class="shrink-0 rounded-lg border border-cyan-200 bg-white px-2.5 py-1 text-[11px] font-semibold text-cyan-700 transition hover:border-cyan-300 hover:bg-cyan-100"
                                    @click="clearCustomer"
                                >
                                    Ganti
                                </button>
                            </div>
                            <div
                                class="mt-3 flex flex-wrap items-center gap-x-3 gap-y-1 border-t border-cyan-200/70 pt-3"
                            >
                                <span
                                    class="rounded-lg bg-white px-2.5 py-1 text-xs font-semibold text-slate-800 shadow-sm"
                                >
                                    {{ selectedCustomerOption.vehicle.plate }}
                                </span>
                                <span class="text-xs text-slate-600">
                                    {{ selectedCustomerOption.vehicle.name }}
                                </span>
                                <span class="text-[11px] text-slate-400">
                                    {{ selectedCustomerOption.vehicle.type }}
                                </span>
                            </div>
                        </div>
                    </div>
                </div>
                <div
                    v-else-if="customerMode === 'walk-in'"
                    class="mt-3 space-y-1.5"
                >
                    <input
                        v-model="draft.walkInName"
                        type="text"
                        placeholder="Nama pelanggan walk-in"
                        class="w-full rounded-xl border border-slate-200 px-3 py-2.5 text-sm focus:border-cyan-400 focus:outline-none"
                    />
                    <p class="text-[11px] text-slate-400">
                        Order dicatat tanpa membuat data member.
                    </p>
                </div>

                <div v-else class="mt-3 space-y-1.5">
                    <div class="grid gap-2 sm:grid-cols-2">
                        <input
                            v-model="draft.walkInName"
                            type="text"
                            placeholder="Nama member baru"
                            class="w-full rounded-xl border border-slate-200 px-3 py-2.5 text-sm focus:border-cyan-400 focus:outline-none"
                        />
                        <input
                            v-model="draft.newMemberPhone"
                            type="tel"
                            inputmode="tel"
                            placeholder="Nomor telepon"
                            class="w-full rounded-xl border border-slate-200 px-3 py-2.5 text-sm focus:border-cyan-400 focus:outline-none"
                        />
                    </div>
                    <p class="text-[11px] text-slate-400">
                        Data member dan order diinput bersamaan.
                    </p>
                </div>
            </div>

            <!-- Vehicle -->
            <div v-if="customerMode !== 'existing'">
                <p
                    class="text-[11px] font-medium tracking-wider text-slate-400 uppercase"
                >
                    Kendaraan
                </p>
                <div class="mt-2 grid grid-cols-2 gap-3">
                    <input
                        v-model="draft.plate"
                        type="text"
                        placeholder="Plat nomor"
                        class="rounded-xl border border-slate-200 px-3 py-2.5 text-sm uppercase placeholder:normal-case focus:border-cyan-400 focus:outline-none"
                    />
                    <input
                        v-model="draft.vehicle"
                        type="text"
                        placeholder="Merk / tipe"
                        class="rounded-xl border border-slate-200 px-3 py-2.5 text-sm focus:border-cyan-400 focus:outline-none"
                    />
                </div>
            </div>

            <!-- Services -->
            <div>
                <p
                    class="text-[11px] font-medium tracking-wider text-slate-400 uppercase"
                >
                    Layanan
                </p>
                <div
                    class="mt-2 grid max-h-56 grid-cols-1 gap-2 overflow-y-auto sm:grid-cols-2"
                >
                    <button
                        v-for="service in services"
                        :key="service.id"
                        type="button"
                        class="flex items-center gap-2 rounded-xl border p-2.5 text-left transition"
                        :class="
                            draft.serviceIds.includes(service.id)
                                ? 'border-cyan-400 bg-cyan-50/60'
                                : 'border-slate-200 hover:border-slate-300'
                        "
                        @click="toggleDraftService(service.id)"
                    >
                        <span class="text-lg">{{ service.icon }}</span>
                        <span class="min-w-0 flex-1 leading-tight">
                            <span
                                class="block truncate text-xs font-medium text-slate-800"
                            >
                                {{ service.name }}
                            </span>
                            <span class="block text-[10px] text-slate-500">
                                {{ formatCurrency(service.price) }} • +{{
                                    service.stamps
                                }}
                                stempel
                            </span>
                        </span>
                        <CircleCheck
                            v-if="draft.serviceIds.includes(service.id)"
                            class="h-4 w-4 shrink-0 text-cyan-600"
                        />
                    </button>
                </div>
            </div>

            <!-- Reward redemption (BR-13) -->
            <div v-if="redeemableRewards.length > 0">
                <p
                    class="text-[11px] font-medium tracking-wider text-slate-400 uppercase"
                >
                    Tukar reward
                </p>
                <p class="mt-1 text-[11px] text-slate-500">
                    {{ draftCustomer?.name }} punya
                    {{ draftCustomer?.stamps }} stempel — potongan langsung
                    dipakai di order ini.
                </p>
                <div
                    class="mt-2 grid max-h-40 grid-cols-1 gap-2 overflow-y-auto sm:grid-cols-2"
                >
                    <button
                        v-for="reward in redeemableRewards"
                        :key="reward.id"
                        type="button"
                        class="flex items-center gap-2 rounded-xl border p-2.5 text-left transition"
                        :class="
                            draft.rewardId === reward.id
                                ? 'border-cyan-400 bg-cyan-50/60'
                                : 'border-slate-200 hover:border-slate-300'
                        "
                        @click="
                            draft.rewardId =
                                draft.rewardId === reward.id ? null : reward.id
                        "
                    >
                        <span class="text-lg">{{ reward.icon }}</span>
                        <span class="min-w-0 flex-1 leading-tight">
                            <span
                                class="block truncate text-xs font-medium text-slate-800"
                            >
                                {{ reward.name }}
                            </span>
                            <span class="block text-[10px] text-slate-500">
                                Tukar {{ reward.requiredStamps }} stempel
                            </span>
                        </span>
                        <CircleCheck
                            v-if="draft.rewardId === reward.id"
                            class="h-4 w-4 shrink-0 text-cyan-600"
                        />
                    </button>
                </div>
            </div>

            <!-- Summary -->
            <div class="space-y-2 rounded-2xl bg-slate-50 p-4">
                <div
                    v-if="rewardDiscount > 0"
                    class="flex justify-between text-sm"
                >
                    <span class="min-w-0 truncate text-slate-500">
                        Reward: {{ appliedReward?.name }}
                    </span>
                    <span class="shrink-0 text-emerald-600 tabular-nums">
                        −{{ formatCurrency(rewardDiscount) }}
                    </span>
                </div>
                <div class="flex items-center justify-between">
                    <div>
                        <p class="text-[11px] text-slate-500">
                            {{ draftServices.length }} layanan dipilih
                        </p>
                        <p
                            class="text-lg font-semibold text-slate-900 tabular-nums"
                        >
                            {{ formatCurrency(draftTotal) }}
                        </p>
                    </div>
                    <p
                        v-if="draftStamps > 0"
                        class="flex items-center gap-1 text-xs font-medium text-emerald-600"
                    >
                        <Sparkles class="h-3.5 w-3.5" />
                        +{{ draftStamps }} stempel
                    </p>
                </div>
            </div>
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
                @click="createOrder"
            >
                Simpan order
            </button>
        </template>
    </ModalDialog>
</template>

<style scoped>
:deep(.customer-search.multiselect) {
    min-height: 44px;
    color: inherit;
}

:deep(.customer-search .multiselect__tags) {
    min-height: 44px;
    border-color: var(--color-slate-200);
    border-radius: 0.75rem;
    padding: 10px 40px 8px;
    background: white;
}

:deep(.customer-search.multiselect--active .multiselect__tags) {
    border-color: var(--color-cyan-400);
}

:deep(.customer-search .multiselect__input),
:deep(.customer-search .multiselect__single) {
    min-height: 22px;
    margin: 0;
    padding: 0;
    background: transparent;
    font-size: 0.875rem;
    line-height: 1.375rem;
}

:deep(.customer-search .multiselect__placeholder) {
    margin: 0;
    padding: 0;
    color: var(--color-slate-400);
    font-size: 0.875rem;
    line-height: 1.375rem;
}

:deep(.customer-search .multiselect__select) {
    height: 42px;
}

:deep(.customer-search .multiselect__content-wrapper) {
    z-index: 70;
    margin-top: 4px;
    overflow: hidden;
    border-color: var(--color-slate-200);
    border-radius: 0.75rem;
    box-shadow: 0 16px 32px rgb(15 23 42 / 12%);
}

:deep(.customer-search .multiselect__option) {
    min-height: 0;
    padding: 0;
}

:deep(.customer-search .multiselect__option--highlight),
:deep(
    .customer-search
        .multiselect__option--selected.multiselect__option--highlight
) {
    background: var(--color-cyan-50);
    color: var(--color-slate-900);
}

:deep(.customer-search .multiselect__option--selected) {
    background: var(--color-slate-50);
    color: var(--color-slate-900);
}
</style>
