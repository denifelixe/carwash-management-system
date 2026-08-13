<script setup lang="ts">
import { Head } from '@inertiajs/vue3';
import {
    Banknote,
    CircleCheck,
    ClipboardList,
    Clock,
    CreditCard,
    Printer,
    ScanLine,
    Sparkles,
    Wallet,
} from '@lucide/vue';
import { computed, ref } from 'vue';
import CollapsibleSummary from '@/components/carwash/CollapsibleSummary.vue';
import DataToolbar from '@/components/carwash/DataToolbar.vue';
import EmptyState from '@/components/carwash/EmptyState.vue';
import ModalDialog from '@/components/carwash/ModalDialog.vue';
import StatCard from '@/components/carwash/StatCard.vue';
import StatusPill from '@/components/carwash/StatusPill.vue';
import { formatCurrency, formatNumber } from '@/composables/useCarwashFormat';
import type {
    CarwashBrand,
    CarwashCustomer,
    CarwashOrder,
    CarwashService,
} from '@/types/carwash';

const props = defineProps<{
    brand: CarwashBrand;
    orders: CarwashOrder[];
    services: CarwashService[];
    customers: CarwashCustomer[];
    paymentMethods: string[];
}>();

interface PosReceipt {
    orderNo: string;
    invoice: string;
    customer: string;
    items: string;
    total: number;
    amountPaid: number;
    paidTotal: number;
    dueAfter: number;
    isSettled: boolean;
    payment: string;
    stampsEarned: number;
    stampsAfter: number | null;
    /** Redeemed at intake, shown here so the customer sees it on the slip. */
    reward: string;
}

/** Settle the whole remaining balance, or take a deposit against it. */
type PaymentMode = 'lunas' | 'sebagian';

const filterOptions = ['Perlu dibayar', 'Sudah lunas', 'Semua'];

const search = ref<string>('');
const paymentFilter = ref<string>('Perlu dibayar');
const selectedOrderId = ref<number | null>(null);
const paymentMode = ref<PaymentMode>('lunas');
const partialAmount = ref<number>(0);
const paymentMethod = ref<string>('QRIS');
const receipt = ref<PosReceipt | null>(null);

/** Local copies so taking a payment can mutate orders and stamps without a backend. */
const orderList = ref<CarwashOrder[]>(
    props.orders.map((order) => ({ ...order })),
);
const customerList = ref<CarwashCustomer[]>(
    props.customers.map((customer) => ({ ...customer })),
);

const outstandingTotal = computed<number>(() =>
    orderList.value.reduce(
        (sum, order) => sum + order.total - order.paidAmount,
        0,
    ),
);

const depositCount = computed<number>(
    () =>
        orderList.value.filter((order) => order.paymentStatus === 'dp').length,
);

const collectedTotal = computed<number>(() =>
    orderList.value.reduce((sum, order) => sum + order.paidAmount, 0),
);

/** Keeps the headline number visible while the summary cards stay collapsed. */
const summaryCaption = computed<string>(
    () => `${formatCurrency(outstandingTotal.value)} perlu ditagih`,
);

const visibleOrders = computed<CarwashOrder[]>(() => {
    const query = search.value.trim().toLowerCase();

    return orderList.value.filter((order) => {
        const isSettled = order.paymentStatus === 'lunas';
        const matchesFilter =
            paymentFilter.value === 'Semua' ||
            (paymentFilter.value === 'Sudah lunas' ? isSettled : !isSettled);
        const matchesQuery =
            query === '' ||
            order.orderNo.toLowerCase().includes(query) ||
            order.customer.toLowerCase().includes(query) ||
            order.plate.toLowerCase().includes(query);

        return matchesFilter && matchesQuery;
    });
});

const selectedOrder = computed<CarwashOrder | null>(
    () =>
        orderList.value.find((order) => order.id === selectedOrderId.value) ??
        null,
);

const orderCustomer = computed<CarwashCustomer | null>(
    () =>
        customerList.value.find(
            (customer) => customer.id === selectedOrder.value?.customerId,
        ) ?? null,
);

const orderServices = computed<CarwashService[]>(() =>
    props.services.filter((service) =>
        selectedOrder.value?.serviceIds.includes(service.id),
    ),
);

const dueAmount = computed<number>(() => {
    const order = selectedOrder.value;

    if (!order) {
        return 0;
    }

    return Math.max(order.total - order.paidAmount, 0);
});

const payAmount = computed<number>(() => {
    if (paymentMode.value === 'lunas') {
        return dueAmount.value;
    }

    const typed = Math.trunc(partialAmount.value);

    return Number.isFinite(typed)
        ? Math.min(Math.max(typed, 0), dueAmount.value)
        : 0;
});

const canSubmit = computed<boolean>(
    () => selectedOrder.value !== null && payAmount.value > 0,
);

function selectOrder(order: CarwashOrder): void {
    selectedOrderId.value = order.id;
    paymentMode.value = 'lunas';
    partialAmount.value = 0;
    /** A deposit usually gets settled with the same method it started on. */
    paymentMethod.value = props.paymentMethods.includes(order.payment)
        ? order.payment
        : 'QRIS';
}

function resetPanel(): void {
    selectedOrderId.value = null;
    paymentMode.value = 'lunas';
    partialAmount.value = 0;
    paymentMethod.value = 'QRIS';
}

/**
 * Records money against the selected order. Anything short of the full amount
 * leaves the order hanging as a `dp`; settling it issues the invoice and
 * releases the member's stamps.
 */
function submitPayment(): void {
    const order = selectedOrder.value;

    if (!order || payAmount.value <= 0) {
        return;
    }

    const customer = orderCustomer.value;
    const amount = payAmount.value;

    order.paidAmount += amount;
    order.payment = paymentMethod.value;

    const isSettled = order.paidAmount >= order.total;

    if (isSettled) {
        order.paymentStatus = 'lunas';

        if (order.invoice === '—') {
            order.invoice = order.orderNo.replace('ORD', 'ZW');
        }

        if (customer) {
            customer.stamps += order.stampsEarned;
            customer.lifetimeStamps += order.stampsEarned;
            customer.visits += 1;
            customer.spend += order.total;
            customer.lastVisit = 'Baru saja';
        }
    } else {
        order.paymentStatus = 'dp';
    }

    receipt.value = {
        orderNo: order.orderNo,
        invoice: order.invoice,
        customer: order.customer,
        items: order.items,
        total: order.total,
        amountPaid: amount,
        paidTotal: order.paidAmount,
        dueAfter: Math.max(order.total - order.paidAmount, 0),
        isSettled,
        payment: order.payment,
        stampsEarned: isSettled ? order.stampsEarned : 0,
        stampsAfter: customer?.stamps ?? null,
        reward: order.reward,
    };

    resetPanel();
}
</script>

<template>
    <Head :title="`${brand.name} — Kasir POS`" />

    <div class="space-y-4">
        <!-- Summary -->
        <CollapsibleSummary
            title="Kasir hari ini"
            :caption="summaryCaption"
            :columns="3"
            collapsible="always"
        >
            <StatCard
                label="Perlu ditagih"
                :value="formatCurrency(outstandingTotal)"
                caption="sisa tagihan seluruh order"
                :icon="Clock"
                tone="amber"
            />
            <StatCard
                label="Order menggantung"
                :value="String(depositCount)"
                caption="sudah DP, belum lunas"
                :icon="Wallet"
            />
            <StatCard
                label="Pembayaran diterima"
                :value="formatCurrency(collectedTotal)"
                caption="termasuk DP yang sudah masuk"
                :icon="Banknote"
                tone="emerald"
            />
        </CollapsibleSummary>

        <div class="grid grid-cols-1 gap-4 xl:grid-cols-[1fr_380px]">
            <!-- Order picker -->
            <section
                class="rounded-2xl border border-slate-200/80 bg-white p-5 shadow-sm"
            >
                <div class="flex flex-wrap items-center justify-between gap-3">
                    <div>
                        <h3 class="text-sm font-semibold text-slate-900">
                            Order untuk dibayar
                        </h3>
                        <p class="mt-0.5 text-xs text-slate-500">
                            {{ visibleOrders.length }} order ditampilkan — ketuk
                            untuk memproses pembayaran
                        </p>
                    </div>
                    <DataToolbar
                        v-model:search="search"
                        placeholder="Cari order / plat"
                        :filters="filterOptions"
                        :active-filter="paymentFilter"
                        @filter="paymentFilter = $event"
                    />
                </div>

                <ul v-if="visibleOrders.length > 0" class="mt-4 space-y-2.5">
                    <li v-for="order in visibleOrders" :key="order.id">
                        <button
                            type="button"
                            class="w-full rounded-2xl border p-4 text-left transition"
                            :class="
                                selectedOrderId === order.id
                                    ? 'border-cyan-300 bg-cyan-50/60 shadow-sm'
                                    : 'border-slate-200 bg-white hover:border-cyan-300 hover:shadow-lg hover:shadow-cyan-500/10'
                            "
                            @click="selectOrder(order)"
                        >
                            <div
                                class="flex flex-wrap items-start justify-between gap-2"
                            >
                                <div class="min-w-0">
                                    <p
                                        class="text-sm font-semibold text-slate-900"
                                    >
                                        {{ order.orderNo }}
                                    </p>
                                    <p class="text-[11px] text-slate-500">
                                        {{ order.time }} • {{ order.source }}
                                    </p>
                                </div>
                                <div class="flex shrink-0 gap-1.5">
                                    <StatusPill :status="order.status" />
                                    <StatusPill :status="order.paymentStatus" />
                                </div>
                            </div>

                            <p class="mt-2 truncate text-sm text-slate-800">
                                {{ order.customer }}
                            </p>
                            <p class="text-[11px] text-slate-500">
                                {{ order.vehicle }} • {{ order.plate }}
                            </p>
                            <p class="mt-1 line-clamp-1 text-xs text-slate-500">
                                {{ order.items }}
                            </p>

                            <div
                                class="mt-3 flex items-end justify-between border-t border-dashed border-slate-200 pt-2.5"
                            >
                                <span class="text-[11px] text-slate-500">
                                    Total {{ formatCurrency(order.total) }}
                                </span>
                                <span
                                    v-if="order.paymentStatus === 'lunas'"
                                    class="text-sm font-semibold text-emerald-600"
                                >
                                    Lunas
                                </span>
                                <span v-else class="text-right">
                                    <span
                                        class="block text-[11px] text-slate-500"
                                    >
                                        Sisa tagihan
                                    </span>
                                    <span
                                        class="block text-sm font-semibold text-slate-900 tabular-nums"
                                    >
                                        {{
                                            formatCurrency(
                                                order.total - order.paidAmount,
                                            )
                                        }}
                                    </span>
                                </span>
                            </div>
                        </button>
                    </li>
                </ul>

                <EmptyState
                    v-else
                    :icon="ClipboardList"
                    title="Tidak ada order pada filter ini"
                    caption="Ubah kata kunci pencarian atau pilih filter lain."
                />
            </section>

            <!-- Payment panel -->
            <section
                class="flex h-fit flex-col rounded-2xl border border-slate-200/80 bg-white shadow-sm xl:sticky xl:top-24"
            >
                <div class="border-b border-slate-100 p-5">
                    <div class="flex items-center justify-between">
                        <h3 class="text-sm font-semibold text-slate-900">
                            Pembayaran
                        </h3>
                        <button
                            v-if="selectedOrder"
                            type="button"
                            class="text-xs font-medium text-slate-500 hover:text-slate-700"
                            @click="resetPanel"
                        >
                            Batalkan
                        </button>
                    </div>
                    <template v-if="selectedOrder">
                        <p class="mt-2 text-sm font-medium text-slate-800">
                            {{ selectedOrder.orderNo }}
                        </p>
                        <p class="text-[11px] text-slate-500">
                            {{ selectedOrder.customer }} •
                            {{ selectedOrder.plate }}
                        </p>
                    </template>
                    <p v-else class="mt-0.5 text-xs text-slate-500">
                        Kasir hanya menerima uang — order dibuat di modul Order
                    </p>
                </div>

                <template v-if="selectedOrder">
                    <!-- Ordered services -->
                    <div class="max-h-56 overflow-y-auto p-5">
                        <ul class="space-y-3">
                            <li
                                v-for="service in orderServices"
                                :key="service.id"
                                class="flex items-center gap-3"
                            >
                                <div
                                    class="flex h-9 w-9 items-center justify-center rounded-lg bg-slate-100 text-base"
                                >
                                    {{ service.icon }}
                                </div>
                                <p
                                    class="min-w-0 flex-1 truncate text-sm font-medium text-slate-800"
                                >
                                    {{ service.name }}
                                </p>
                                <p class="text-sm text-slate-700 tabular-nums">
                                    {{ formatCurrency(service.price) }}
                                </p>
                            </li>
                        </ul>
                    </div>

                    <!-- Summary -->
                    <div
                        class="space-y-3 border-t border-slate-100 bg-slate-50/60 p-5"
                    >
                        <dl class="space-y-1.5 text-sm">
                            <div
                                v-if="selectedOrder.discount > 0"
                                class="flex justify-between"
                            >
                                <dt class="min-w-0 truncate text-slate-500">
                                    Reward: {{ selectedOrder.reward }}
                                </dt>
                                <dd
                                    class="shrink-0 text-emerald-600 tabular-nums"
                                >
                                    −{{
                                        formatCurrency(selectedOrder.discount)
                                    }}
                                </dd>
                            </div>
                            <div class="flex justify-between">
                                <dt class="text-slate-500">Total order</dt>
                                <dd class="text-slate-800 tabular-nums">
                                    {{ formatCurrency(selectedOrder.total) }}
                                </dd>
                            </div>
                            <div
                                v-if="selectedOrder.paidAmount > 0"
                                class="flex justify-between"
                            >
                                <dt class="text-slate-500">Sudah dibayar</dt>
                                <dd class="text-emerald-600 tabular-nums">
                                    −{{
                                        formatCurrency(selectedOrder.paidAmount)
                                    }}
                                </dd>
                            </div>
                        </dl>

                        <div
                            class="flex items-end justify-between border-t border-dashed border-slate-200 pt-3"
                        >
                            <span class="text-sm font-medium text-slate-600">
                                Sisa tagihan
                            </span>
                            <span
                                class="text-xl font-semibold text-slate-900 tabular-nums"
                            >
                                {{ formatCurrency(dueAmount) }}
                            </span>
                        </div>

                        <!-- Lunas or deposit -->
                        <div class="grid grid-cols-2 gap-1.5">
                            <button
                                type="button"
                                class="rounded-lg py-2 text-[11px] font-medium transition"
                                :class="
                                    paymentMode === 'lunas'
                                        ? 'bg-slate-900 text-white'
                                        : 'bg-white text-slate-600 ring-1 ring-slate-200 hover:bg-slate-50'
                                "
                                @click="paymentMode = 'lunas'"
                            >
                                Lunas
                            </button>
                            <button
                                type="button"
                                class="rounded-lg py-2 text-[11px] font-medium transition"
                                :class="
                                    paymentMode === 'sebagian'
                                        ? 'bg-slate-900 text-white'
                                        : 'bg-white text-slate-600 ring-1 ring-slate-200 hover:bg-slate-50'
                                "
                                @click="paymentMode = 'sebagian'"
                            >
                                Bayar sebagian (DP)
                            </button>
                        </div>

                        <label
                            v-if="paymentMode === 'sebagian'"
                            class="block rounded-xl bg-white p-3 ring-1 ring-slate-200"
                        >
                            <span
                                class="text-[11px] font-medium text-slate-500"
                            >
                                Nominal diterima
                            </span>
                            <span class="mt-1 flex items-center gap-2">
                                <span class="text-sm text-slate-500">Rp</span>
                                <input
                                    v-model.number="partialAmount"
                                    type="number"
                                    min="0"
                                    :max="dueAmount"
                                    step="1000"
                                    placeholder="0"
                                    class="w-full bg-transparent text-sm font-medium text-slate-800 tabular-nums placeholder:text-slate-300 focus:outline-none"
                                />
                            </span>
                            <span
                                class="mt-1.5 block text-[11px] text-slate-500"
                            >
                                Sisa setelah pembayaran ini:
                                {{ formatCurrency(dueAmount - payAmount) }}
                            </span>
                        </label>

                        <p
                            v-if="
                                orderCustomer && selectedOrder.stampsEarned > 0
                            "
                            class="flex items-center gap-1.5 rounded-xl bg-emerald-50 px-3 py-2 text-xs font-medium text-emerald-700"
                        >
                            <Sparkles class="h-3.5 w-3.5 shrink-0" />
                            {{ orderCustomer.name.split(' ')[0] }} dapat +{{
                                selectedOrder.stampsEarned
                            }}
                            stempel saat order lunas
                        </p>

                        <div class="grid grid-cols-4 gap-1.5">
                            <button
                                v-for="method in paymentMethods"
                                :key="method"
                                type="button"
                                class="rounded-lg py-2 text-[11px] font-medium transition"
                                :class="
                                    paymentMethod === method
                                        ? 'bg-slate-900 text-white'
                                        : 'bg-white text-slate-600 ring-1 ring-slate-200 hover:bg-slate-50'
                                "
                                @click="paymentMethod = method"
                            >
                                {{ method }}
                            </button>
                        </div>

                        <button
                            type="button"
                            class="flex w-full items-center justify-center gap-2 rounded-xl bg-gradient-to-r from-cyan-500 to-sky-600 py-3 text-sm font-semibold text-white shadow-lg shadow-cyan-500/30 transition hover:from-cyan-600 hover:to-sky-700 disabled:cursor-not-allowed disabled:from-slate-300 disabled:to-slate-300 disabled:shadow-none"
                            :disabled="!canSubmit"
                            @click="submitPayment"
                        >
                            <CreditCard class="h-4 w-4" />
                            Terima {{ formatCurrency(payAmount) }}
                        </button>
                    </div>
                </template>

                <EmptyState
                    v-else
                    :icon="ScanLine"
                    title="Belum ada order dipilih"
                    caption="Pilih order di sebelah kiri untuk menerima pembayaran"
                />
            </section>
        </div>
    </div>

    <!-- Receipt -->
    <ModalDialog :open="receipt !== null" size="sm" @close="receipt = null">
        <div v-if="receipt">
            <div
                class="-m-6 mb-4 px-6 py-7 text-center text-white"
                :class="
                    receipt.isSettled
                        ? 'bg-gradient-to-br from-emerald-500 to-teal-600'
                        : 'bg-gradient-to-br from-amber-500 to-orange-600'
                "
            >
                <div
                    class="mx-auto flex h-14 w-14 items-center justify-center rounded-full bg-white/20"
                >
                    <CircleCheck v-if="receipt.isSettled" class="h-8 w-8" />
                    <Clock v-else class="h-8 w-8" />
                </div>
                <p class="mt-3 text-lg font-semibold">
                    {{
                        receipt.isSettled
                            ? 'Pembayaran berhasil'
                            : 'DP diterima'
                    }}
                </p>
                <p class="text-sm text-white/85">
                    {{ receipt.isSettled ? receipt.invoice : receipt.orderNo }}
                    • {{ receipt.payment }}
                </p>
            </div>

            <div class="space-y-3 pt-4 text-sm">
                <div class="flex justify-between">
                    <span class="text-slate-500">Customer</span>
                    <span class="font-medium text-slate-800">
                        {{ receipt.customer }}
                    </span>
                </div>
                <div class="flex justify-between gap-4">
                    <span class="shrink-0 text-slate-500">Layanan</span>
                    <span class="text-right text-slate-700">
                        {{ receipt.items }}
                    </span>
                </div>
                <div
                    v-if="receipt.reward !== '—'"
                    class="flex justify-between gap-4"
                >
                    <span class="shrink-0 text-slate-500">Reward dipakai</span>
                    <span class="text-right text-cyan-700">
                        {{ receipt.reward }}
                    </span>
                </div>
                <div class="flex justify-between">
                    <span class="text-slate-500">Total order</span>
                    <span class="text-slate-700 tabular-nums">
                        {{ formatCurrency(receipt.total) }}
                    </span>
                </div>
                <div
                    class="flex justify-between border-t border-dashed border-slate-200 pt-3"
                >
                    <span class="text-slate-500">Diterima sekarang</span>
                    <span
                        class="text-lg font-semibold text-slate-900 tabular-nums"
                    >
                        {{ formatCurrency(receipt.amountPaid) }}
                    </span>
                </div>

                <div
                    v-if="!receipt.isSettled"
                    class="rounded-xl bg-amber-50 p-3 ring-1 ring-amber-100"
                >
                    <p
                        class="flex items-center justify-between text-xs text-amber-900"
                    >
                        <span>Total sudah dibayar</span>
                        <span class="font-semibold tabular-nums">
                            {{ formatCurrency(receipt.paidTotal) }}
                        </span>
                    </p>
                    <p
                        class="mt-1 flex items-center justify-between border-t border-amber-200/60 pt-1 text-xs font-medium text-amber-900"
                    >
                        <span>Sisa tagihan</span>
                        <span class="tabular-nums">
                            {{ formatCurrency(receipt.dueAfter) }}
                        </span>
                    </p>
                </div>

                <div
                    v-else-if="receipt.stampsAfter !== null"
                    class="rounded-xl bg-cyan-50 p-3 ring-1 ring-cyan-100"
                >
                    <p
                        class="flex items-center justify-between text-xs text-cyan-900"
                    >
                        <span>Stempel didapat</span>
                        <span class="font-semibold tabular-nums">
                            +{{ receipt.stampsEarned }}
                        </span>
                    </p>
                    <p
                        class="mt-1 flex items-center justify-between border-t border-cyan-200/60 pt-1 text-xs font-medium text-cyan-900"
                    >
                        <span>Saldo stempel sekarang</span>
                        <span class="tabular-nums">
                            {{ formatNumber(receipt.stampsAfter) }}
                        </span>
                    </p>
                </div>
            </div>
        </div>

        <template #footer>
            <button
                type="button"
                class="flex flex-1 items-center justify-center gap-2 rounded-xl border border-slate-200 py-2.5 text-sm font-medium text-slate-600 transition hover:bg-slate-50"
                @click="receipt = null"
            >
                <Printer class="h-4 w-4" />
                Cetak struk
            </button>
            <button
                type="button"
                class="flex-1 rounded-xl bg-slate-900 py-2.5 text-sm font-semibold text-white transition hover:bg-slate-800"
                @click="receipt = null"
            >
                Selesai
            </button>
        </template>
    </ModalDialog>
</template>
