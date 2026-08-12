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
    CarwashService,
} from '@/types/carwash';

const props = defineProps<{
    brand: CarwashBrand;
    orders: CarwashOrder[];
    services: CarwashService[];
    serviceCategories: string[];
    customers: CarwashCustomer[];
    crew: CarwashCrewMember[];
    paymentMethods: string[];
}>();

const statusFlow = ['menunggu', 'proses', 'selesai'] as const;

const orderList = ref<CarwashOrder[]>(
    props.orders.map((order) => ({ ...order })),
);

const search = ref<string>('');
const statusFilter = ref<string>('Semua');
const detailOrderId = ref<number | null>(null);
const isCreateOpen = ref<boolean>(false);

const draft = ref({
    customerId: null as number | null,
    walkInName: '',
    vehicle: '',
    plate: '',
    serviceIds: [] as number[],
    crew: '',
    payment: 'QRIS',
    paymentStatus: 'belum bayar',
});

const filterOptions = ['Semua', 'menunggu', 'proses', 'selesai'];

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

const todayRevenue = computed<number>(() =>
    orderList.value
        .filter((order) => order.paymentStatus === 'lunas')
        .reduce((total, order) => total + order.total, 0),
);

const unpaidTotal = computed<number>(() =>
    orderList.value
        .filter((order) => order.paymentStatus !== 'lunas')
        .reduce((total, order) => total + order.total, 0),
);

const activeCount = computed<number>(
    () => orderList.value.filter((order) => order.status !== 'selesai').length,
);

const stampsIssued = computed<number>(() =>
    orderList.value
        .filter((order) => order.status === 'selesai')
        .reduce((total, order) => total + order.stampsEarned, 0),
);

const draftCustomer = computed<CarwashCustomer | null>(
    () =>
        props.customers.find(
            (customer) => customer.id === draft.value.customerId,
        ) ?? null,
);

const draftServices = computed<CarwashService[]>(() =>
    props.services.filter((service) =>
        draft.value.serviceIds.includes(service.id),
    ),
);

const draftTotal = computed<number>(() =>
    draftServices.value.reduce((total, service) => total + service.price, 0),
);

const draftStamps = computed<number>(() =>
    draft.value.customerId === null
        ? 0
        : draftServices.value.reduce(
              (total, service) => total + service.stamps,
              0,
          ),
);

const canCreate = computed<boolean>(
    () =>
        draftServices.value.length > 0 &&
        draft.value.plate.trim() !== '' &&
        (draft.value.customerId !== null ||
            draft.value.walkInName.trim() !== ''),
);

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

    if (order.invoice === '—') {
        order.invoice = order.orderNo.replace('ORD', 'ZW');
    }
}

function pickCustomer(customerId: number | null): void {
    draft.value.customerId = customerId;

    const customer = props.customers.find((item) => item.id === customerId);

    if (customer) {
        draft.value.vehicle = customer.vehicle;
        draft.value.plate = customer.plate;
        draft.value.walkInName = '';
    }
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
        vehicle: '',
        plate: '',
        serviceIds: [],
        crew: '',
        payment: 'QRIS',
        paymentStatus: 'belum bayar',
    };
}

function createOrder(): void {
    if (!canCreate.value) {
        return;
    }

    const sequence = orderList.value.length + 13;
    const customer = draftCustomer.value;

    orderList.value = [
        {
            id: sequence,
            orderNo: `ORD-2608${String(sequence).padStart(4, '0')}`,
            invoice:
                draft.value.paymentStatus === 'lunas'
                    ? `ZW-2608${String(sequence).padStart(4, '0')}`
                    : '—',
            time: 'Baru saja',
            customerId: customer?.id ?? null,
            customer: customer?.name ?? draft.value.walkInName,
            phone: customer?.phone ?? '—',
            vehicle: draft.value.vehicle || '—',
            plate: draft.value.plate.toUpperCase(),
            items: draftServices.value
                .map((service) => service.name)
                .join(', '),
            serviceIds: [...draft.value.serviceIds],
            total: draftTotal.value,
            payment:
                draft.value.paymentStatus === 'lunas'
                    ? draft.value.payment
                    : '—',
            paymentStatus: draft.value.paymentStatus,
            status: 'menunggu',
            stampsEarned: draftStamps.value,
            crew: draft.value.crew || 'Menunggu crew',
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
        <section class="grid grid-cols-1 gap-4 sm:grid-cols-2 xl:grid-cols-4">
            <StatCard
                label="Order aktif"
                :value="String(activeCount)"
                caption="menunggu & sedang dikerjakan"
                :icon="Car"
            />
            <StatCard
                label="Penjualan lunas"
                :value="formatCurrency(todayRevenue)"
                :caption="`${orderList.length} order tercatat`"
                :icon="Wallet"
                tone="emerald"
            />
            <StatCard
                label="Belum dibayar"
                :value="formatCurrency(unpaidTotal)"
                caption="perlu ditagih di kasir"
                :icon="Clock"
                tone="amber"
            />
            <StatCard
                label="Stempel diberikan"
                :value="String(stampsIssued)"
                caption="dari order yang selesai"
                :icon="Sparkles"
            />
        </section>

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
                    class="flex justify-between border-t border-slate-200 pt-2 text-base"
                >
                    <dt class="font-medium text-slate-600">Total</dt>
                    <dd class="font-semibold text-slate-900 tabular-nums">
                        {{ formatCurrency(detailOrder.total) }}
                    </dd>
                </div>
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
                <p
                    class="text-[11px] font-medium tracking-wider text-slate-400 uppercase"
                >
                    Customer
                </p>
                <div
                    class="mt-2 flex items-center gap-2 rounded-xl bg-slate-100 px-3 py-2"
                >
                    <Search class="h-4 w-4 text-slate-400" />
                    <select
                        :value="draft.customerId ?? ''"
                        class="w-full bg-transparent text-sm text-slate-700 focus:outline-none"
                        @change="
                            pickCustomer(
                                ($event.target as HTMLSelectElement).value ===
                                    ''
                                    ? null
                                    : Number(
                                          ($event.target as HTMLSelectElement)
                                              .value,
                                      ),
                            )
                        "
                    >
                        <option value="">Umum (non-member)</option>
                        <option
                            v-for="customer in customers"
                            :key="customer.id"
                            :value="customer.id"
                        >
                            {{ customer.name }} — {{ customer.plate }}
                        </option>
                    </select>
                </div>
                <input
                    v-if="draft.customerId === null"
                    v-model="draft.walkInName"
                    type="text"
                    placeholder="Nama pelanggan walk-in"
                    class="mt-2 w-full rounded-xl border border-slate-200 px-3 py-2.5 text-sm focus:border-cyan-400 focus:outline-none"
                />
            </div>

            <!-- Vehicle -->
            <div>
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

            <!-- Crew & payment -->
            <div class="grid grid-cols-2 gap-3">
                <div>
                    <p
                        class="text-[11px] font-medium tracking-wider text-slate-400 uppercase"
                    >
                        Crew
                    </p>
                    <select
                        v-model="draft.crew"
                        class="mt-2 w-full rounded-xl border border-slate-200 px-3 py-2.5 text-sm focus:border-cyan-400 focus:outline-none"
                    >
                        <option value="">Menunggu crew</option>
                        <option
                            v-for="person in crew"
                            :key="person.name"
                            :value="person.name"
                        >
                            {{ person.name }} — {{ person.role }}
                        </option>
                    </select>
                </div>
                <div>
                    <p
                        class="text-[11px] font-medium tracking-wider text-slate-400 uppercase"
                    >
                        Status bayar
                    </p>
                    <select
                        v-model="draft.paymentStatus"
                        class="mt-2 w-full rounded-xl border border-slate-200 px-3 py-2.5 text-sm focus:border-cyan-400 focus:outline-none"
                    >
                        <option value="belum bayar">
                            Bayar nanti di kasir
                        </option>
                        <option value="lunas">Sudah dibayar</option>
                    </select>
                </div>
            </div>

            <div
                v-if="draft.paymentStatus === 'lunas'"
                class="flex flex-wrap gap-1.5"
            >
                <button
                    v-for="method in paymentMethods"
                    :key="method"
                    type="button"
                    class="rounded-lg px-3 py-1.5 text-[11px] font-medium transition"
                    :class="
                        draft.payment === method
                            ? 'bg-slate-900 text-white'
                            : 'bg-slate-100 text-slate-600 hover:bg-slate-200'
                    "
                    @click="draft.payment = method"
                >
                    {{ method }}
                </button>
            </div>

            <!-- Summary -->
            <div
                class="flex items-center justify-between rounded-2xl bg-slate-50 p-4"
            >
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
