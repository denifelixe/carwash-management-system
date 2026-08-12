<script setup lang="ts">
import { Head } from '@inertiajs/vue3';
import {
    ChevronDown,
    CircleCheck,
    CreditCard,
    Minus,
    Plus,
    Printer,
    ScanLine,
    Search,
    Sparkles,
    Timer,
    Trash2,
    UserPlus,
} from '@lucide/vue';
import { computed, ref } from 'vue';
import EmptyState from '@/components/carwash/EmptyState.vue';
import ModalDialog from '@/components/carwash/ModalDialog.vue';
import { formatCurrency, formatNumber } from '@/composables/useCarwashFormat';
import type {
    CarwashBrand,
    CarwashCartLine,
    CarwashCustomer,
    CarwashOrder,
    CarwashReward,
    CarwashService,
} from '@/types/carwash';

const props = defineProps<{
    brand: CarwashBrand;
    services: CarwashService[];
    serviceCategories: string[];
    customers: CarwashCustomer[];
    transactions: CarwashOrder[];
    paymentMethods: string[];
    rewards: CarwashReward[];
}>();

interface PosReceipt {
    invoice: string;
    customer: string;
    items: string;
    total: number;
    payment: string;
    stampsEarned: number;
    stampsUsed: number;
    stampsAfter: number | null;
    rewardName: string | null;
}

const category = ref<string>('Semua');
const search = ref<string>('');
const cart = ref<CarwashCartLine[]>([]);
const selectedCustomerId = ref<number | null>(null);
const isCustomerPickerOpen = ref<boolean>(false);
const customerQuery = ref<string>('');
const appliedRewardId = ref<number | null>(null);
const paymentMethod = ref<string>('QRIS');
const receipt = ref<PosReceipt | null>(null);

/** Local copies so checkout can mutate stamps without a backend. */
const customerList = ref<CarwashCustomer[]>(
    props.customers.map((customer) => ({ ...customer })),
);
const transactionLog = ref<CarwashOrder[]>(
    props.transactions.map((transaction) => ({ ...transaction })),
);

const categoryOptions = computed<string[]>(() => [
    'Semua',
    ...props.serviceCategories,
]);

const visibleServices = computed<CarwashService[]>(() => {
    const query = search.value.trim().toLowerCase();

    return props.services.filter((service) => {
        const matchesCategory =
            category.value === 'Semua' || service.category === category.value;
        const matchesQuery =
            query === '' || service.name.toLowerCase().includes(query);

        return service.isActive && matchesCategory && matchesQuery;
    });
});

const selectedCustomer = computed<CarwashCustomer | null>(
    () =>
        customerList.value.find(
            (customer) => customer.id === selectedCustomerId.value,
        ) ?? null,
);

const customerResults = computed<CarwashCustomer[]>(() => {
    const query = customerQuery.value.trim().toLowerCase();

    if (query === '') {
        return customerList.value.slice(0, 6);
    }

    return customerList.value
        .filter(
            (customer) =>
                customer.name.toLowerCase().includes(query) ||
                customer.plate.toLowerCase().includes(query) ||
                customer.phone.includes(query),
        )
        .slice(0, 6);
});

const subtotal = computed<number>(() =>
    cart.value.reduce(
        (total, line) => total + line.service.price * line.quantity,
        0,
    ),
);

/** Rewards the attached customer has enough stamps for (BR-04, BR-13). */
const redeemableRewards = computed<CarwashReward[]>(() => {
    const customer = selectedCustomer.value;

    if (!customer) {
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
        props.rewards.find((reward) => reward.id === appliedRewardId.value) ??
        null,
);

/**
 * A redeemed reward covers the cheapest matching line, capped at the subtotal —
 * enough to make the discount believable in the demo.
 */
const rewardDiscount = computed<number>(() => {
    if (!appliedReward.value || cart.value.length === 0) {
        return 0;
    }

    const cheapest = Math.min(...cart.value.map((line) => line.service.price));

    return Math.min(cheapest, subtotal.value);
});

const stampsUsed = computed<number>(() =>
    appliedReward.value ? appliedReward.value.requiredStamps : 0,
);

const total = computed<number>(() =>
    Math.max(subtotal.value - rewardDiscount.value, 0),
);

const stampsEarned = computed<number>(() =>
    selectedCustomer.value
        ? cart.value.reduce(
              (sum, line) => sum + line.service.stamps * line.quantity,
              0,
          )
        : 0,
);

const estimatedDuration = computed<number>(() =>
    cart.value.reduce(
        (sum, line) => sum + line.service.duration * line.quantity,
        0,
    ),
);

function addToCart(service: CarwashService): void {
    const line = cart.value.find((item) => item.service.id === service.id);

    if (line) {
        line.quantity += 1;

        return;
    }

    cart.value.push({ service, quantity: 1 });
}

function decreaseLine(line: CarwashCartLine): void {
    if (line.quantity > 1) {
        line.quantity -= 1;

        return;
    }

    removeLine(line);
}

function removeLine(line: CarwashCartLine): void {
    cart.value = cart.value.filter(
        (item) => item.service.id !== line.service.id,
    );
}

function selectCustomer(customer: CarwashCustomer | null): void {
    selectedCustomerId.value = customer?.id ?? null;
    isCustomerPickerOpen.value = false;
    customerQuery.value = '';
    appliedRewardId.value = null;
}

function resetCart(): void {
    cart.value = [];
    selectedCustomerId.value = null;
    appliedRewardId.value = null;
    paymentMethod.value = 'QRIS';
}

function checkout(): void {
    if (cart.value.length === 0) {
        return;
    }

    const customer = selectedCustomer.value;
    const items = cart.value
        .map((line) =>
            line.quantity > 1
                ? `${line.service.name} ×${line.quantity}`
                : line.service.name,
        )
        .join(', ');

    const invoice = `ZW-2608${String(transactionLog.value.length + 13).padStart(4, '0')}`;

    if (customer) {
        customer.stamps =
            customer.stamps - stampsUsed.value + stampsEarned.value;
        customer.lifetimeStamps += stampsEarned.value;
        customer.visits += 1;
        customer.spend += total.value;
        customer.lastVisit = 'Baru saja';
    }

    transactionLog.value = [
        {
            id: transactionLog.value.length + 1,
            orderNo: `ORD-2608${String(transactionLog.value.length + 13).padStart(4, '0')}`,
            invoice,
            time: 'Baru saja',
            customerId: customer?.id ?? null,
            customer: customer?.name ?? 'Umum (non-member)',
            phone: customer?.phone ?? '—',
            vehicle: customer?.vehicle ?? '—',
            plate: customer?.plate ?? '—',
            items,
            serviceIds: cart.value.map((line) => line.service.id),
            total: total.value,
            payment: paymentMethod.value,
            paymentStatus: 'lunas',
            status: 'proses',
            stampsEarned: stampsEarned.value,
            crew: 'Menunggu crew',
            bay: '—',
            source: 'walk-in',
        },
        ...transactionLog.value,
    ];

    receipt.value = {
        invoice,
        customer: customer?.name ?? 'Umum (non-member)',
        items,
        total: total.value,
        payment: paymentMethod.value,
        stampsEarned: stampsEarned.value,
        stampsUsed: stampsUsed.value,
        stampsAfter: customer?.stamps ?? null,
        rewardName: appliedReward.value?.name ?? null,
    };

    resetCart();
}
</script>

<template>
    <Head :title="`${brand.name} — Kasir POS`" />

    <div class="grid grid-cols-1 gap-4 xl:grid-cols-[1fr_380px]">
        <!-- Service picker -->
        <section
            class="rounded-2xl border border-slate-200/80 bg-white p-5 shadow-sm"
        >
            <div class="flex flex-wrap items-center justify-between gap-3">
                <div>
                    <h3 class="text-sm font-semibold text-slate-900">
                        Pilih layanan
                    </h3>
                    <p class="mt-0.5 text-xs text-slate-500">
                        Ketuk kartu untuk menambahkan ke keranjang
                    </p>
                </div>
                <div
                    class="flex items-center gap-2 rounded-xl bg-slate-100 px-3 py-2"
                >
                    <Search class="h-4 w-4 text-slate-400" />
                    <input
                        v-model="search"
                        type="search"
                        placeholder="Cari layanan"
                        class="w-40 bg-transparent text-sm text-slate-700 placeholder:text-slate-400 focus:outline-none"
                    />
                </div>
            </div>

            <div class="mt-4 flex flex-wrap gap-2">
                <button
                    v-for="option in categoryOptions"
                    :key="option"
                    type="button"
                    class="rounded-full px-3.5 py-1.5 text-xs font-medium transition"
                    :class="
                        category === option
                            ? 'bg-slate-900 text-white'
                            : 'bg-slate-100 text-slate-600 hover:bg-slate-200'
                    "
                    @click="category = option"
                >
                    {{ option }}
                </button>
            </div>

            <div
                class="mt-4 grid grid-cols-1 gap-3 sm:grid-cols-2 2xl:grid-cols-3"
            >
                <button
                    v-for="service in visibleServices"
                    :key="service.id"
                    type="button"
                    class="group relative overflow-hidden rounded-2xl border border-slate-200 bg-white p-4 text-left transition hover:-translate-y-0.5 hover:border-cyan-300 hover:shadow-lg hover:shadow-cyan-500/10"
                    @click="addToCart(service)"
                >
                    <span
                        v-if="service.popular"
                        class="absolute top-3 right-3 rounded-full bg-amber-100 px-2 py-0.5 text-[10px] font-medium text-amber-700"
                    >
                        Populer
                    </span>
                    <div
                        class="flex h-11 w-11 items-center justify-center rounded-xl bg-gradient-to-br from-cyan-50 to-sky-100 text-xl"
                    >
                        {{ service.icon }}
                    </div>
                    <p class="mt-3 text-sm font-semibold text-slate-900">
                        {{ service.name }}
                    </p>
                    <p class="mt-1 line-clamp-2 text-xs text-slate-500">
                        {{ service.description }}
                    </p>
                    <div class="mt-3 flex items-center justify-between">
                        <p
                            class="text-sm font-semibold text-cyan-700 tabular-nums"
                        >
                            {{ formatCurrency(service.price) }}
                        </p>
                        <span
                            class="flex items-center gap-1 text-[11px] text-slate-400"
                        >
                            <Timer class="h-3.5 w-3.5" />
                            {{ service.duration }} mnt
                        </span>
                    </div>
                    <div
                        v-if="service.stamps > 0"
                        class="mt-2 flex items-center gap-1 text-[11px] font-medium text-emerald-600"
                    >
                        <Sparkles class="h-3.5 w-3.5" />
                        +{{ service.stamps }} stempel member
                    </div>
                </button>
            </div>
        </section>

        <!-- Cart -->
        <section
            class="flex h-fit flex-col rounded-2xl border border-slate-200/80 bg-white shadow-sm xl:sticky xl:top-24"
        >
            <div class="border-b border-slate-100 p-5">
                <div class="flex items-center justify-between">
                    <h3 class="text-sm font-semibold text-slate-900">
                        Keranjang
                    </h3>
                    <button
                        v-if="cart.length > 0"
                        type="button"
                        class="text-xs font-medium text-rose-500 hover:text-rose-600"
                        @click="resetCart"
                    >
                        Kosongkan
                    </button>
                </div>

                <!-- Customer selector -->
                <div class="relative mt-3">
                    <button
                        type="button"
                        class="flex w-full items-center gap-3 rounded-xl border border-dashed border-slate-300 p-3 text-left transition hover:border-cyan-400 hover:bg-cyan-50/40"
                        :class="
                            selectedCustomer
                                ? 'border-solid border-cyan-200 bg-cyan-50/60'
                                : ''
                        "
                        @click="isCustomerPickerOpen = !isCustomerPickerOpen"
                    >
                        <div
                            class="flex h-9 w-9 items-center justify-center rounded-full text-xs font-semibold"
                            :class="
                                selectedCustomer
                                    ? 'bg-gradient-to-br from-cyan-500 to-sky-600 text-white'
                                    : 'bg-slate-100 text-slate-400'
                            "
                        >
                            <template v-if="selectedCustomer">
                                {{ selectedCustomer.initials }}
                            </template>
                            <UserPlus v-else class="h-4 w-4" />
                        </div>
                        <div class="min-w-0 flex-1">
                            <p
                                class="truncate text-sm font-medium text-slate-800"
                            >
                                {{ selectedCustomer?.name ?? 'Pilih customer' }}
                            </p>
                            <p class="truncate text-[11px] text-slate-500">
                                <template v-if="selectedCustomer">
                                    {{ selectedCustomer.plate }} •
                                    {{ selectedCustomer.stamps }}/{{
                                        brand.stampTarget
                                    }}
                                    stempel
                                </template>
                                <template v-else>
                                    Opsional — transaksi umum tanpa stempel
                                </template>
                            </p>
                        </div>
                        <ChevronDown class="h-4 w-4 shrink-0 text-slate-400" />
                    </button>

                    <div
                        v-if="isCustomerPickerOpen"
                        class="absolute inset-x-0 top-full z-20 mt-2 overflow-hidden rounded-xl border border-slate-200 bg-white shadow-xl"
                    >
                        <div
                            class="flex items-center gap-2 border-b border-slate-100 px-3 py-2"
                        >
                            <Search class="h-4 w-4 text-slate-400" />
                            <input
                                v-model="customerQuery"
                                type="search"
                                placeholder="Nama, plat, atau no. HP"
                                class="w-full bg-transparent py-1 text-sm placeholder:text-slate-400 focus:outline-none"
                            />
                        </div>
                        <ul class="max-h-64 overflow-y-auto">
                            <li
                                v-for="customer in customerResults"
                                :key="customer.id"
                            >
                                <button
                                    type="button"
                                    class="flex w-full items-center gap-3 px-3 py-2.5 text-left transition hover:bg-slate-50"
                                    @click="selectCustomer(customer)"
                                >
                                    <div
                                        class="flex h-8 w-8 items-center justify-center rounded-full bg-slate-100 text-[11px] font-semibold text-slate-600"
                                    >
                                        {{ customer.initials }}
                                    </div>
                                    <div class="min-w-0 flex-1">
                                        <p
                                            class="truncate text-sm text-slate-800"
                                        >
                                            {{ customer.name }}
                                        </p>
                                        <p class="text-[11px] text-slate-500">
                                            {{ customer.plate }} •
                                            {{ customer.stamps }} stempel
                                        </p>
                                    </div>
                                </button>
                            </li>
                            <li
                                v-if="customerResults.length === 0"
                                class="px-3 py-4 text-center text-xs text-slate-400"
                            >
                                Customer tidak ditemukan
                            </li>
                        </ul>
                        <button
                            type="button"
                            class="w-full border-t border-slate-100 px-3 py-2.5 text-left text-xs font-medium text-slate-500 hover:bg-slate-50"
                            @click="selectCustomer(null)"
                        >
                            Lanjut tanpa member
                        </button>
                    </div>
                </div>
            </div>

            <!-- Lines -->
            <div class="max-h-72 overflow-y-auto p-5">
                <ul v-if="cart.length > 0" class="space-y-3">
                    <li
                        v-for="line in cart"
                        :key="line.service.id"
                        class="flex items-center gap-3"
                    >
                        <div
                            class="flex h-9 w-9 items-center justify-center rounded-lg bg-slate-100 text-base"
                        >
                            {{ line.service.icon }}
                        </div>
                        <div class="min-w-0 flex-1">
                            <p
                                class="truncate text-sm font-medium text-slate-800"
                            >
                                {{ line.service.name }}
                            </p>
                            <p class="text-[11px] text-slate-500 tabular-nums">
                                {{ formatCurrency(line.service.price) }} ×
                                {{ line.quantity }}
                            </p>
                        </div>
                        <div class="flex items-center gap-1">
                            <button
                                type="button"
                                class="flex h-7 w-7 items-center justify-center rounded-lg border border-slate-200 text-slate-500 transition hover:bg-slate-50"
                                aria-label="Kurangi"
                                @click="decreaseLine(line)"
                            >
                                <Minus class="h-3.5 w-3.5" />
                            </button>
                            <span
                                class="w-6 text-center text-sm font-medium tabular-nums"
                            >
                                {{ line.quantity }}
                            </span>
                            <button
                                type="button"
                                class="flex h-7 w-7 items-center justify-center rounded-lg border border-slate-200 text-slate-500 transition hover:bg-slate-50"
                                aria-label="Tambah"
                                @click="line.quantity += 1"
                            >
                                <Plus class="h-3.5 w-3.5" />
                            </button>
                            <button
                                type="button"
                                class="ml-1 flex h-7 w-7 items-center justify-center rounded-lg text-slate-300 transition hover:bg-rose-50 hover:text-rose-500"
                                aria-label="Hapus"
                                @click="removeLine(line)"
                            >
                                <Trash2 class="h-3.5 w-3.5" />
                            </button>
                        </div>
                    </li>
                </ul>

                <EmptyState
                    v-else
                    :icon="ScanLine"
                    title="Keranjang masih kosong"
                    caption="Pilih layanan di sebelah kiri untuk memulai transaksi"
                />
            </div>

            <!-- Summary -->
            <div class="space-y-3 border-t border-slate-100 bg-slate-50/60 p-5">
                <!-- Reward redemption (BR-13) -->
                <div v-if="redeemableRewards.length > 0" class="space-y-1.5">
                    <p class="text-[11px] font-medium text-slate-500">
                        Reward tersedia untuk customer ini
                    </p>
                    <button
                        v-for="reward in redeemableRewards"
                        :key="reward.id"
                        type="button"
                        class="flex w-full items-center gap-2 rounded-xl border p-2.5 text-left transition"
                        :class="
                            appliedRewardId === reward.id
                                ? 'border-cyan-300 bg-cyan-50'
                                : 'border-slate-200 bg-white hover:border-cyan-300'
                        "
                        @click="
                            appliedRewardId =
                                appliedRewardId === reward.id ? null : reward.id
                        "
                    >
                        <span class="text-base">{{ reward.icon }}</span>
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
                            v-if="appliedRewardId === reward.id"
                            class="h-4 w-4 shrink-0 text-cyan-600"
                        />
                    </button>
                </div>

                <dl class="space-y-1.5 text-sm">
                    <div class="flex justify-between">
                        <dt class="text-slate-500">Subtotal</dt>
                        <dd class="text-slate-800 tabular-nums">
                            {{ formatCurrency(subtotal) }}
                        </dd>
                    </div>
                    <div v-if="rewardDiscount > 0" class="flex justify-between">
                        <dt class="min-w-0 truncate text-slate-500">
                            Reward: {{ appliedReward?.name }}
                        </dt>
                        <dd class="shrink-0 text-emerald-600 tabular-nums">
                            −{{ formatCurrency(rewardDiscount) }}
                        </dd>
                    </div>
                    <div
                        v-if="estimatedDuration > 0"
                        class="flex justify-between"
                    >
                        <dt class="text-slate-500">Estimasi pengerjaan</dt>
                        <dd class="text-slate-800 tabular-nums">
                            ± {{ estimatedDuration }} menit
                        </dd>
                    </div>
                </dl>

                <div
                    class="flex items-end justify-between border-t border-dashed border-slate-200 pt-3"
                >
                    <span class="text-sm font-medium text-slate-600">
                        Total
                    </span>
                    <span
                        class="text-xl font-semibold text-slate-900 tabular-nums"
                    >
                        {{ formatCurrency(total) }}
                    </span>
                </div>

                <p
                    v-if="selectedCustomer && cart.length > 0"
                    class="flex items-center gap-1.5 rounded-xl bg-emerald-50 px-3 py-2 text-xs font-medium text-emerald-700"
                >
                    <Sparkles class="h-3.5 w-3.5" />
                    {{ selectedCustomer.name.split(' ')[0] }} akan mendapat +{{
                        stampsEarned
                    }}
                    stempel
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
                    :disabled="cart.length === 0"
                    @click="checkout"
                >
                    <CreditCard class="h-4 w-4" />
                    Bayar {{ formatCurrency(total) }}
                </button>
            </div>
        </section>
    </div>

    <!-- Recent transactions -->
    <section
        class="mt-4 rounded-2xl border border-slate-200/80 bg-white shadow-sm"
    >
        <div class="border-b border-slate-100 p-5">
            <h3 class="text-sm font-semibold text-slate-900">
                Transaksi terakhir
            </h3>
            <p class="mt-0.5 text-xs text-slate-500">
                Transaksi baru dari kasir muncul paling atas
            </p>
        </div>
        <div class="overflow-x-auto">
            <table class="w-full min-w-[720px] text-sm">
                <thead>
                    <tr
                        class="border-b border-slate-100 text-left text-[11px] font-medium tracking-wider text-slate-400 uppercase"
                    >
                        <th class="px-5 py-3">Invoice</th>
                        <th class="px-5 py-3">Customer</th>
                        <th class="px-5 py-3">Layanan</th>
                        <th class="px-5 py-3">Bayar</th>
                        <th class="px-5 py-3 text-right">Total</th>
                        <th class="px-5 py-3 text-right">Stempel</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-50">
                    <tr
                        v-for="transaction in transactionLog.slice(0, 8)"
                        :key="transaction.id"
                        class="transition hover:bg-slate-50/70"
                    >
                        <td class="px-5 py-3.5">
                            <p class="font-medium text-slate-900">
                                {{ transaction.invoice }}
                            </p>
                            <p class="text-[11px] text-slate-500">
                                {{ transaction.time }}
                            </p>
                        </td>
                        <td class="px-5 py-3.5 text-slate-700">
                            {{ transaction.customer }}
                        </td>
                        <td class="max-w-[240px] px-5 py-3.5 text-slate-600">
                            {{ transaction.items }}
                        </td>
                        <td class="px-5 py-3.5">
                            <span
                                class="rounded-lg bg-slate-100 px-2 py-1 text-[11px] font-medium text-slate-600"
                            >
                                {{ transaction.payment }}
                            </span>
                        </td>
                        <td
                            class="px-5 py-3.5 text-right font-medium text-slate-900 tabular-nums"
                        >
                            {{ formatCurrency(transaction.total) }}
                        </td>
                        <td class="px-5 py-3.5 text-right tabular-nums">
                            <span
                                v-if="transaction.stampsEarned > 0"
                                class="text-emerald-600"
                            >
                                +{{ transaction.stampsEarned }}
                            </span>
                            <span v-else class="text-slate-300">—</span>
                        </td>
                    </tr>
                </tbody>
            </table>
        </div>
    </section>

    <!-- Receipt -->
    <ModalDialog :open="receipt !== null" size="sm" @close="receipt = null">
        <div v-if="receipt">
            <div
                class="-m-6 mb-4 bg-gradient-to-br from-emerald-500 to-teal-600 px-6 py-7 text-center text-white"
            >
                <div
                    class="mx-auto flex h-14 w-14 items-center justify-center rounded-full bg-white/20"
                >
                    <CircleCheck class="h-8 w-8" />
                </div>
                <p class="mt-3 text-lg font-semibold">Pembayaran berhasil</p>
                <p class="text-sm text-emerald-50/90">
                    {{ receipt.invoice }} • {{ receipt.payment }}
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
                    v-if="receipt.rewardName"
                    class="flex justify-between gap-4"
                >
                    <span class="shrink-0 text-slate-500">Reward dipakai</span>
                    <span class="text-right text-cyan-700">
                        {{ receipt.rewardName }}
                    </span>
                </div>
                <div
                    class="flex justify-between border-t border-dashed border-slate-200 pt-3"
                >
                    <span class="text-slate-500">Total dibayar</span>
                    <span
                        class="text-lg font-semibold text-slate-900 tabular-nums"
                    >
                        {{ formatCurrency(receipt.total) }}
                    </span>
                </div>

                <div
                    v-if="receipt.stampsAfter !== null"
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
                        v-if="receipt.stampsUsed > 0"
                        class="mt-1 flex items-center justify-between text-xs text-cyan-900"
                    >
                        <span>Stempel ditukar</span>
                        <span class="font-semibold tabular-nums">
                            −{{ receipt.stampsUsed }}
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
                Transaksi baru
            </button>
        </template>
    </ModalDialog>
</template>
