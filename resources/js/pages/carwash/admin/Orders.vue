<script setup lang="ts">
import { Head, router } from '@inertiajs/vue3';
import {
    Ban,
    CalendarClock,
    Car,
    CircleCheck,
    ClipboardList,
    Hourglass,
    Plus,
    Search,
    Wallet,
} from '@lucide/vue';
import type { LucideIcon } from '@lucide/vue';
import { computed, ref, watch } from 'vue';
import Multiselect from 'vue-multiselect';
import 'vue-multiselect/dist/vue-multiselect.css';
import CollapsibleSummary from '@/components/carwash/CollapsibleSummary.vue';
import DataToolbar from '@/components/carwash/DataToolbar.vue';
import DateFilterBar from '@/components/carwash/DateFilterBar.vue';
import EmptyState from '@/components/carwash/EmptyState.vue';
import ModalDialog from '@/components/carwash/ModalDialog.vue';
import SlideOver from '@/components/carwash/SlideOver.vue';
import StatCard from '@/components/carwash/StatCard.vue';
import StatusPill from '@/components/carwash/StatusPill.vue';
import {
    formatCurrency,
    formatDate,
    formatDateCode,
} from '@/composables/useCarwashFormat';
import { useCarwashWorkflow } from '@/composables/useCarwashWorkflow';
import admin from '@/routes/carwash/admin';
import type {
    CarwashDateFilter,
    CarwashBooking,
    CarwashBrand,
    CarwashCrewMember,
    CarwashCustomer,
    CarwashOrder,
    CarwashService,
    CarwashVehicle,
} from '@/types/carwash';

const props = defineProps<{
    brand: CarwashBrand;
    orders: CarwashOrder[];
    filters: CarwashDateFilter;
    orderStatuses: string[];
    editableOrderStatuses: string[];
    upcoming: CarwashBooking[];
    services: CarwashService[];
    serviceCategories: string[];
    customers: CarwashCustomer[];
    crew: CarwashCrewMember[];
    paymentMethods: string[];
}>();

/** Single lifecycle the floor tracks; payment state stays with the cashier. */
const statusFlow = props.orderStatuses;

/** Stages an order cannot move on from: settled by the cashier, or cancelled. */
const closedStatuses = ['selesai', 'batal'];

/** Cars on the floor right now: a booking has not arrived yet, so it is left out. */
const runningStatuses = statusFlow.filter(
    (status) => !closedStatuses.includes(status) && status !== 'booking',
);

type CustomerMode = 'existing' | 'walk-in';

type CustomerOption = {
    key: `customer-${number}-vehicle-${number}`;
    label: string;
    customer: CarwashCustomer;
    vehicle: CarwashVehicle;
};

type CreatedOrderAlert = {
    orderNo: string;
    customer: string;
};

const customerTabs: { key: CustomerMode; label: string }[] = [
    { key: 'existing', label: 'Member' },
    { key: 'walk-in', label: 'Non-Member' },
];

/** Local copies used by the member and vehicle picker. */
const workflow = useCarwashWorkflow();
workflow.hydrateCustomers(props.customers);
workflow.hydrateOrders(props.orders);

const customerList = workflow.customers;

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

const orderList = workflow.orders;

/** Orders belonging to the date selected on this page. Other modules may load
 * future bookings into the shared workflow store, but they must not inflate
 * this page's daily summary. */
const scopedOrders = computed<CarwashOrder[]>(() =>
    orderList.value.filter(
        (order) =>
            props.filters.date === '' || order.date === props.filters.date,
    ),
);

const search = ref<string>('');
const statusFilter = ref<string>('menunggu');
const detailOrderId = ref<number | null>(null);
const isCreateOpen = ref<boolean>(false);
const createdOrderAlert = ref<CreatedOrderAlert | null>(null);
const customerQuery = ref<string>('');
const customerMode = ref<CustomerMode>('existing');
const selectedCustomerOption = ref<CustomerOption | null>(null);

const draft = ref({
    customerId: null as number | null,
    walkInName: '',
    customerPhone: '',
    vehicle: '',
    plate: '',
    serviceIds: [] as number[],
});

const bookingStatusLabel = 'Booking - Belum Datang';

/** Stage chips first, so 'Semua' reads as the escape hatch it is. */
const filterOptions = [
    ...statusFlow.map((status) =>
        status === 'booking' ? bookingStatusLabel : status,
    ),
    'Semua',
];

const activeFilterLabel = computed<string>(() =>
    statusFilter.value === 'booking' ? bookingStatusLabel : statusFilter.value,
);

function applyStatusFilter(filter: string): void {
    statusFilter.value = filter === bookingStatusLabel ? 'booking' : filter;
}

function isAwaitingArrivalBooking(order: CarwashOrder): boolean {
    return order.source === 'booking' && order.status === 'booking';
}

function displayedStatus(order: CarwashOrder): string {
    return statusFilter.value === 'booking' && isAwaitingArrivalBooking(order)
        ? 'booking'
        : order.status;
}

function orderSourceLabel(order: CarwashOrder): string {
    if (order.source === 'booking') {
        return order.customerId === null
            ? 'Booking Non Member'
            : 'Booking Member';
    }

    return order.customerId === null ? 'Walk-In Non Member' : 'Walk-In Member';
}

function transactionCaption(type: string): string {
    return type === 'Pembayaran Sebagian'
        ? 'Pembayaran Sebagian/Booking'
        : 'Pembayaran Sisa/Lunas (Order Selesai)';
}

function orderArrivalLabel(order: CarwashOrder): string {
    return order.time === '—'
        ? 'Belum masuk'
        : `${formatDate(order.date)} · ${order.time}`;
}

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

    return scopedOrders.value.filter((order) => {
        const matchesStatus =
            statusFilter.value === 'Semua' ||
            (statusFilter.value === 'booking'
                ? isAwaitingArrivalBooking(order)
                : order.status === statusFilter.value);
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

/** Only cashier-settled orders are locked; a cancellation can be corrected. */
const isDetailReadOnly = computed<boolean>(
    () => detailOrder.value?.status === 'selesai',
);

/** The dropdown edits a draft so nothing moves before the user saves. */
const statusDraft = ref<string>('');

watch(
    detailOrder,
    (order) => {
        statusDraft.value = order?.status ?? '';
    },
    { immediate: true },
);

const isStatusDirty = computed<boolean>(
    () =>
        detailOrder.value !== null &&
        statusDraft.value !== detailOrder.value.status,
);

/** Everything still on the floor: waiting, being washed, or at the cashier. */
const runningCount = computed<number>(
    () =>
        scopedOrders.value.filter((order) =>
            runningStatuses.includes(order.status),
        ).length,
);

type StatusCardStyle = {
    label: string;
    caption: string;
    icon: LucideIcon;
    tone: 'default' | 'emerald' | 'rose' | 'amber' | 'violet' | 'slate';
};

/** How each lifecycle stage is presented; the stages themselves come from the server. */
const statusCardStyles: Record<string, StatusCardStyle> = {
    booking: {
        label: bookingStatusLabel,
        caption: 'berasal dari Booking Order',
        icon: CalendarClock,
        tone: 'slate',
    },
    menunggu: {
        label: 'Menunggu',
        caption: 'antre, belum dikerjakan',
        icon: Hourglass,
        tone: 'amber',
    },
    proses: {
        label: 'Proses',
        caption: 'sedang dikerjakan crew',
        icon: Car,
        tone: 'default',
    },
    pelunasan: {
        label: 'Pelunasan',
        caption: 'menunggu kasir',
        icon: Wallet,
        tone: 'violet',
    },
    selesai: {
        label: 'Selesai',
        caption: 'lunas & sudah keluar',
        icon: CircleCheck,
        tone: 'emerald',
    },
    batal: {
        label: 'Batal',
        caption: 'dibatalkan, tidak ditagih',
        icon: Ban,
        tone: 'rose',
    },
};

/** Spelled-out names for stages the one-word card label leaves ambiguous. */
const statusLongLabels: Record<string, string> = {
    booking: bookingStatusLabel,
};

function statusLabel(status: string): string {
    return (
        statusLongLabels[status] ?? statusCardStyles[status]?.label ?? status
    );
}

type StatusCard = StatusCardStyle & { status: string; count: number };

/** One card per stage so the summary mirrors the order lifecycle exactly. */
const statusCards = computed<StatusCard[]>(() =>
    statusFlow.map((status) => ({
        status,
        label: statusCardStyles[status]?.label ?? status,
        caption: statusCardStyles[status]?.caption ?? '',
        icon: statusCardStyles[status]?.icon ?? ClipboardList,
        tone: statusCardStyles[status]?.tone ?? 'default',
        count: scopedOrders.value.filter((order) =>
            status === 'booking'
                ? isAwaitingArrivalBooking(order)
                : order.status === status,
        ).length,
    })),
);

/** Keeps the headline numbers visible while the summary cards stay collapsed. */
const summaryCaption = computed<string>(
    () => `${scopedOrders.value.length} order • ${runningCount.value} berjalan`,
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

const draftTotal = computed<number>(() => draftSubtotal.value);

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

    return (
        draft.value.walkInName.trim() !== '' &&
        draft.value.customerPhone.trim() !== ''
    );
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

/**
 * Moves an order between the stages the floor owns, up to 'pelunasan'. Closing
 * an order to 'selesai' happens in the cashier module once the bill is settled.
 */
function setStatus(order: CarwashOrder, status: string): void {
    order.status = status;
}

/** Writes the dropdown choice onto the open order. */
function saveStatus(): void {
    if (detailOrder.value === null) {
        return;
    }

    setStatus(detailOrder.value, statusDraft.value);
}

function pickCustomer(option: CustomerOption): void {
    selectedCustomerOption.value = option;
    draft.value.customerId = option.customer.id;
    draft.value.walkInName = '';
    draft.value.customerPhone = '';
    draft.value.vehicle = option.vehicle.name;
    draft.value.plate = option.vehicle.plate;
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
    draft.value.customerPhone = '';
    draft.value.vehicle = '';
    draft.value.plate = '';
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
        customerPhone: '',
        vehicle: '',
        plate: '',
        serviceIds: [],
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
    const orderNo = `ORD-${formatDateCode(props.filters.today)}${String(sequence).padStart(2, '0')}`;
    const walkInLabel = customerMode.value === 'walk-in' ? ' (non-member)' : '';
    const customerName =
        customer?.name ?? `${draft.value.walkInName.trim()}${walkInLabel}`;

    workflow.addOrder({
        id: sequence,
        orderNo,
        invoice: '—',
        date: props.filters.today,
        time: 'Baru saja',
        bookingDate: null,
        customerId: customer?.id ?? null,
        customer: customerName,
        phone: customer?.phone ?? draft.value.customerPhone.trim(),
        vehicle: draft.value.vehicle || '—',
        plate: draft.value.plate.toUpperCase(),
        items: draftServices.value.map((service) => service.name).join(', '),
        serviceIds: [...draft.value.serviceIds],
        total: draftTotal.value,
        discount: 0,
        reward: '—',
        paidAmount: 0,
        payment: '—',
        paymentStatus: 'belum bayar',
        status: 'menunggu',
        stampsEarned: draftStamps.value,
        crew: 'Menunggu crew',
        bay: '—',
        source: 'walk-in',
        transactions: [],
    });

    resetDraft();
    isCreateOpen.value = false;
    createdOrderAlert.value = { orderNo, customer: customerName };
}

/** Filtering is a fresh visit, so the page rebuilds from the narrowed props. */
function applyDate(date: string): void {
    router.get(
        admin.orders.url(),
        { date },
        { preserveScroll: true, replace: true },
    );
}
</script>

<template>
    <Head :title="`${brand.name} — Order`" />

    <div class="space-y-4">
        <DateFilterBar :filters="filters" @change="applyDate" />

        <!-- Summary -->
        <CollapsibleSummary
            title="Ringkasan hari ini"
            :caption="summaryCaption"
            :columns="7"
        >
            <StatCard
                label="Total Order"
                :value="String(scopedOrders.length)"
                caption="seluruh order hari ini"
                :icon="ClipboardList"
                interactive
                :active="statusFilter === 'Semua'"
                @click="statusFilter = 'Semua'"
            />
            <StatCard
                v-for="card in statusCards"
                :key="card.status"
                :label="card.label"
                :value="String(card.count)"
                :caption="card.caption"
                :icon="card.icon"
                :tone="card.tone"
                interactive
                :active="statusFilter === card.status"
                @click="statusFilter = card.status"
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
                        :active-filter="activeFilterLabel"
                        @filter="applyStatusFilter"
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
                            <th class="px-5 py-3">Kendaraan</th>
                            <th class="px-5 py-3">Customer</th>
                            <th class="px-5 py-3">Layanan</th>
                            <th class="px-5 py-3">Status</th>
                            <th class="px-5 py-3"></th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-50">
                        <tr
                            v-for="order in filteredOrders"
                            :key="order.id"
                            class="cursor-pointer transition hover:bg-slate-50/70"
                            @click="detailOrderId = order.id"
                        >
                            <td class="min-w-52 px-5 py-3.5">
                                <p
                                    class="text-xl font-bold tracking-wide text-slate-900"
                                >
                                    {{ order.plate }}
                                </p>
                                <p class="mt-0.5 text-xs text-slate-600">
                                    {{ order.vehicle }}
                                </p>
                                <div
                                    class="mt-1.5 flex flex-col gap-0.5 text-[11px] text-slate-500"
                                >
                                    <span>{{ orderArrivalLabel(order) }}</span>
                                    <span>{{ orderSourceLabel(order) }}</span>
                                    <span>{{ order.orderNo }}</span>
                                </div>
                            </td>
                            <td class="px-5 py-3.5">
                                <p class="text-slate-700">
                                    {{ order.customer }}
                                </p>
                                <p class="text-[11px] text-slate-500">
                                    {{ order.phone }}
                                </p>
                            </td>
                            <td
                                class="max-w-[200px] px-5 py-3.5 text-slate-600"
                            >
                                {{ order.items }}
                            </td>
                            <td class="px-5 py-3.5">
                                <StatusPill :status="displayedStatus(order)" />
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
        :caption="
            detailOrder
                ? `${formatDate(detailOrder.date)} • ${detailOrder.time}`
                : undefined
        "
        @close="detailOrderId = null"
    >
        <div v-if="detailOrder" class="space-y-5">
            <div>
                <p class="text-xs font-medium text-slate-500">Status order</p>
                <div v-if="isDetailReadOnly" class="mt-2 flex gap-2">
                    <StatusPill :status="detailOrder.status" />
                </div>
                <template v-else>
                    <div class="mt-2 flex gap-2">
                        <select
                            v-model="statusDraft"
                            class="w-full rounded-xl border border-slate-200 bg-white px-3 py-2 text-sm text-slate-700 transition focus:border-cyan-500 focus:ring-2 focus:ring-cyan-100 focus:outline-none"
                        >
                            <option
                                v-for="status in editableOrderStatuses"
                                :key="status"
                                :value="status"
                            >
                                {{ statusLabel(status) }}
                            </option>
                        </select>
                        <button
                            type="button"
                            :disabled="!isStatusDirty"
                            class="shrink-0 rounded-xl bg-cyan-600 px-4 py-2 text-sm font-semibold text-white transition hover:bg-cyan-700 disabled:cursor-not-allowed disabled:bg-slate-100 disabled:text-slate-400"
                            @click="saveStatus"
                        >
                            Simpan
                        </button>
                    </div>
                    <p class="mt-2 text-[11px] text-slate-400">
                        Status selesai dicatat kasir saat pembayaran diterima.
                    </p>
                </template>
            </div>

            <div class="rounded-xl bg-slate-50 p-3">
                <p class="text-[11px] text-slate-500">Info Pelanggan</p>
                <p class="mt-1 text-xl font-bold tracking-wide text-slate-900">
                    {{ detailOrder.plate }}
                </p>
                <p class="mt-0.5 text-sm text-slate-600">
                    {{ detailOrder.vehicle }}
                </p>
                <p class="mt-1 text-xs text-slate-500">
                    {{ orderSourceLabel(detailOrder) }}
                </p>
                <div class="mt-3 border-t border-slate-200 pt-3">
                    <p class="text-sm font-medium text-slate-800">
                        {{ detailOrder.customer }}
                    </p>
                    <p class="mt-0.5 text-xs text-slate-500">
                        {{ detailOrder.phone }}
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
                            class="shrink-0 text-sm font-medium text-slate-700 tabular-nums"
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

            <section
                class="overflow-hidden rounded-2xl border border-slate-200"
            >
                <div
                    class="flex items-center justify-between gap-3 border-b border-slate-100 bg-slate-50/70 px-4 py-3"
                >
                    <h3 class="text-sm font-semibold text-slate-900">
                        Riwayat transaksi
                    </h3>
                    <span class="text-xs text-slate-500">
                        {{ detailOrder.transactions.length }} transaksi
                    </span>
                </div>
                <ul
                    v-if="detailOrder.transactions.length > 0"
                    class="divide-y divide-slate-100"
                >
                    <li
                        v-for="transaction in detailOrder.transactions"
                        :key="transaction.id"
                        class="flex items-center justify-between gap-4 px-4 py-3"
                    >
                        <div class="min-w-0">
                            <p class="text-xs font-semibold text-slate-800">
                                {{ transactionCaption(transaction.type) }}
                            </p>
                            <p
                                class="mt-0.5 truncate text-[11px] text-slate-500"
                            >
                                {{ formatDate(transaction.date) }} ·
                                {{ transaction.time }} ·
                                {{ transaction.channels }}
                            </p>
                        </div>
                        <p
                            class="shrink-0 text-sm font-semibold text-emerald-700 tabular-nums"
                        >
                            {{ formatCurrency(transaction.amount) }}
                        </p>
                    </li>
                </ul>
                <p v-else class="px-4 py-5 text-center text-xs text-slate-400">
                    Belum ada transaksi
                </p>
            </section>

            <p
                v-if="detailOrder.status === 'batal'"
                class="flex items-center gap-2 rounded-xl bg-rose-50 px-3 py-2.5 text-xs font-medium text-rose-700"
            >
                <Ban class="h-4 w-4 shrink-0" />
                Order dibatalkan — tidak ada tagihan yang ditutup.
            </p>
        </div>

        <template #footer>
            <button
                v-if="detailOrder"
                type="button"
                class="w-full rounded-xl bg-slate-900 py-2.5 text-sm font-semibold text-white transition hover:bg-slate-800"
                @click="detailOrderId = null"
            >
                Tutup
            </button>
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
                    class="mt-2 grid grid-cols-2 gap-1 rounded-xl bg-slate-100 p-1"
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
                                Member tidak ditemukan. Daftarkan terlebih
                                dahulu di modul
                                <span class="font-medium text-slate-700">
                                    Customer
                                </span>
                                atau pilih Non-Member.
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
                <div v-else class="mt-3 space-y-1.5">
                    <div class="grid gap-2 sm:grid-cols-2">
                        <div class="space-y-1.5">
                            <label
                                for="order-customer-name"
                                class="block text-xs font-medium text-slate-600"
                            >
                                Nama
                            </label>
                            <input
                                id="order-customer-name"
                                v-model="draft.walkInName"
                                type="text"
                                placeholder="Nama pelanggan"
                                class="w-full rounded-xl border border-slate-200 px-3 py-2.5 text-sm focus:border-cyan-400 focus:outline-none"
                            />
                        </div>
                        <div class="space-y-1.5">
                            <label
                                for="order-customer-phone"
                                class="block text-xs font-medium text-slate-600"
                            >
                                Nomor Telpon
                            </label>
                            <input
                                id="order-customer-phone"
                                v-model="draft.customerPhone"
                                type="tel"
                                inputmode="tel"
                                placeholder="Nomor telepon"
                                class="w-full rounded-xl border border-slate-200 px-3 py-2.5 text-sm focus:border-cyan-400 focus:outline-none"
                            />
                        </div>
                    </div>
                    <p class="text-[11px] text-slate-400">
                        Data ini hanya dicatat pada order dan tidak membuat
                        member baru.
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
                    <div class="space-y-1.5">
                        <label
                            for="order-vehicle-plate"
                            class="block text-xs font-medium text-slate-600"
                        >
                            Plat Nomor
                        </label>
                        <input
                            id="order-vehicle-plate"
                            v-model="draft.plate"
                            type="text"
                            placeholder="Plat nomor"
                            class="w-full rounded-xl border border-slate-200 px-3 py-2.5 text-sm uppercase placeholder:normal-case focus:border-cyan-400 focus:outline-none"
                        />
                    </div>
                    <div class="space-y-1.5">
                        <label
                            for="order-vehicle-type"
                            class="block text-xs font-medium text-slate-600"
                        >
                            Tipe Mobil
                        </label>
                        <input
                            id="order-vehicle-type"
                            v-model="draft.vehicle"
                            type="text"
                            placeholder="Merk / tipe mobil"
                            class="w-full rounded-xl border border-slate-200 px-3 py-2.5 text-sm focus:border-cyan-400 focus:outline-none"
                        />
                    </div>
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

            <!-- Selected services -->
            <div class="rounded-2xl bg-slate-50 p-4">
                <p class="text-[11px] font-medium text-slate-500">
                    {{ draftServices.length }} layanan dipilih
                </p>
                <ul
                    v-if="draftServices.length > 0"
                    class="mt-2 flex flex-wrap gap-2"
                >
                    <li
                        v-for="service in draftServices"
                        :key="service.id"
                        class="rounded-lg bg-white px-2.5 py-1.5 text-xs font-medium text-slate-700 ring-1 ring-slate-200"
                    >
                        {{ service.name }}
                    </li>
                </ul>
                <p v-else class="mt-1 text-sm text-slate-400">
                    Belum ada layanan dipilih
                </p>
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

    <!-- SweetAlert-style success confirmation -->
    <ModalDialog
        :open="createdOrderAlert !== null"
        size="sm"
        @close="createdOrderAlert = null"
    >
        <div v-if="createdOrderAlert" class="text-center">
            <div
                class="mx-auto flex h-20 w-20 items-center justify-center rounded-full bg-emerald-100 ring-8 ring-emerald-50"
            >
                <CircleCheck class="h-11 w-11 text-emerald-600" />
            </div>
            <h3 class="mt-6 text-xl font-semibold text-slate-900">
                Order berhasil disimpan
            </h3>
            <p class="mt-2 text-sm leading-relaxed text-slate-500">
                Order untuk
                <span class="font-medium text-slate-700">
                    {{ createdOrderAlert.customer }}
                </span>
                sudah masuk ke antrean.
            </p>
            <div
                class="mt-5 rounded-2xl bg-slate-50 px-4 py-3 ring-1 ring-slate-100"
            >
                <p class="text-[11px] text-slate-500">Nomor order</p>
                <p class="mt-0.5 font-semibold text-slate-900">
                    {{ createdOrderAlert.orderNo }}
                </p>
            </div>
            <button
                type="button"
                class="mt-6 w-full rounded-xl bg-gradient-to-r from-cyan-500 to-sky-600 py-3 text-sm font-semibold text-white shadow-lg shadow-cyan-500/25 transition hover:from-cyan-600 hover:to-sky-700"
                @click="createdOrderAlert = null"
            >
                Oke
            </button>
        </div>
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
