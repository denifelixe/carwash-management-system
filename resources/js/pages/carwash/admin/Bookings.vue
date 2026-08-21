<script setup lang="ts">
import { Head } from '@inertiajs/vue3';
import {
    CalendarCheck,
    CalendarClock,
    CircleCheck,
    Clock,
    Phone,
    Plus,
    Search,
    Sparkles,
} from '@lucide/vue';
import type { LucideIcon } from '@lucide/vue';
import { computed, ref } from 'vue';
import Multiselect from 'vue-multiselect';
import 'vue-multiselect/dist/vue-multiselect.css';
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
import type {
    CarwashBooking,
    CarwashBrand,
    CarwashCustomer,
    CarwashService,
    CarwashVehicle,
} from '@/types/carwash';

const props = defineProps<{
    brand: CarwashBrand;
    bookings: CarwashBooking[];
    /** The prototype's fixed "today", ISO formatted, anchoring every day label. */
    today: string;
    services: CarwashService[];
    customers: CarwashCustomer[];
}>();

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

const bookingList = ref<CarwashBooking[]>(
    props.bookings.map((booking) => ({ ...booking })),
);

const customerList = ref<CarwashCustomer[]>(
    props.customers.map((customer) => ({ ...customer })),
);

/** One option per vehicle, so a customer with two cars is booked on the right one. */
const customerOptions: CustomerOption[] = customerList.value.flatMap(
    (customer) =>
        customer.vehicles.map((vehicle, vehicleIndex) => ({
            key: `customer-${customer.id}-vehicle-${vehicleIndex}`,
            label: customer.name,
            customer,
            vehicle,
        })),
);

const detailBookingId = ref<number | null>(null);
const isCreateOpen = ref<boolean>(false);
const editingBookingId = ref<number | null>(null);
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
    date: props.today,
});

/** Days between a booking date and the prototype's today; negative is past. */
function daysFromToday(date: string): number {
    const dayInMs = 24 * 60 * 60 * 1000;

    return Math.round(
        (Date.parse(`${date}T00:00:00`) -
            Date.parse(`${props.today}T00:00:00`)) /
            dayInMs,
    );
}

/** Bookings whose day has passed; the order module says how each one ended. */
const pastBookings = computed<CarwashBooking[]>(() =>
    bookingList.value.filter((booking) => daysFromToday(booking.date) < 0),
);

const todayBookings = computed<CarwashBooking[]>(() =>
    bookingList.value.filter((booking) => daysFromToday(booking.date) === 0),
);

const upcomingBookings = computed<CarwashBooking[]>(() =>
    bookingList.value.filter((booking) => daysFromToday(booking.date) > 0),
);

/** Today follows the order lifecycle; future bookings remain scheduled. */
function bookingPill(booking: CarwashBooking): string {
    const daysAhead = daysFromToday(booking.date);

    if (daysAhead <= 0) {
        return booking.orderStatus;
    }

    return 'mendatang';
}

function bookingCustomerType(booking: CarwashBooking): string {
    return booking.customerId === null ? 'Non-Member' : 'Member';
}

type BookingBoard = {
    key: string;
    title: string;
    caption: string;
    badge: string;
    badgeTone: string;
    icon: LucideIcon;
    emptyTitle: string;
    emptyCaption: string;
    bookings: CarwashBooking[];
};

/** The three stages a booking passes through, stacked the way crew reads them. */
const bookingBoards = computed<BookingBoard[]>(() => [
    {
        key: 'today',
        title: 'Booking hari ini',
        caption: 'Kedatangan yang harus disiapkan crew hari ini',
        badge: `${todayBookings.value.length} antre`,
        badgeTone: 'bg-cyan-50 text-cyan-700',
        icon: CalendarClock,
        emptyTitle: 'Belum ada booking hari ini',
        emptyCaption: 'Booking untuk hari ini akan tampil di sini.',
        bookings: todayBookings.value,
    },
    {
        key: 'upcoming',
        title: 'Booking mendatang',
        caption: 'Jadwal untuk hari-hari berikutnya',
        badge: `${upcomingBookings.value.length} terjadwal`,
        badgeTone: 'bg-amber-50 text-amber-700',
        icon: CalendarClock,
        emptyTitle: 'Belum ada booking mendatang',
        emptyCaption: 'Booking baru akan tampil di sini.',
        bookings: upcomingBookings.value,
    },
    {
        key: 'past',
        title: 'Booking selesai / batal',
        caption: 'Booking yang sudah lewat jadwalnya',
        badge: `${pastBookings.value.length} riwayat`,
        badgeTone: 'bg-slate-100 text-slate-600',
        icon: CalendarCheck,
        emptyTitle: 'Belum ada booking yang lewat',
        emptyCaption: 'Booking selesai atau batal akan tampil di sini.',
        bookings: pastBookings.value,
    },
]);

const detailBooking = computed<CarwashBooking | null>(
    () =>
        bookingList.value.find(
            (booking) => booking.id === detailBookingId.value,
        ) ?? null,
);

const canEditDetailBooking = computed<boolean>(
    () => detailBooking.value?.orderStatus === 'booking',
);

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

const hasBookableDate = computed<boolean>(
    () => draft.value.date !== '' && draft.value.date >= props.today,
);

const canCreate = computed<boolean>(
    () =>
        draftServices.value.length > 0 &&
        draft.value.plate.trim() !== '' &&
        hasBookableDate.value &&
        hasCustomer.value,
);

function normalizeCustomerSearch(value: string): string {
    return value.toLocaleLowerCase('id-ID').replace(/[^a-z0-9]/g, '');
}

/** How far off a date sits, worded the way the boards read. */
function dayLabelFor(date: string): string {
    const daysAhead = daysFromToday(date);

    if (daysAhead === 0) {
        return 'Hari ini';
    }

    if (daysAhead === 1) {
        return 'Besok';
    }

    if (daysAhead === -1) {
        return 'Kemarin';
    }

    return daysAhead > 0
        ? `${daysAhead} hari lagi`
        : `${Math.abs(daysAhead)} hari lalu`;
}

function pickCustomer(option: CustomerOption): void {
    selectedCustomerOption.value = option;
    draft.value.customerId = option.customer.id;
    draft.value.walkInName = '';
    draft.value.newMemberPhone = '';
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
    draft.value.newMemberPhone = '';
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
        newMemberPhone: '',
        vehicle: '',
        plate: '',
        serviceIds: [],
        date: props.today,
    };
    customerQuery.value = '';
    customerMode.value = 'existing';
    selectedCustomerOption.value = null;
}

function openCreateBooking(): void {
    editingBookingId.value = null;
    resetDraft();
    isCreateOpen.value = true;
}

function closeBookingForm(): void {
    isCreateOpen.value = false;
    editingBookingId.value = null;
    resetDraft();
}

function startEditingBooking(): void {
    const booking = detailBooking.value;

    if (booking === null || booking.orderStatus !== 'booking') {
        return;
    }

    const customerOption = customerOptions.find(
        (option) =>
            option.customer.id === booking.customerId &&
            option.vehicle.plate === booking.plate,
    );

    editingBookingId.value = booking.id;
    customerQuery.value = '';
    selectedCustomerOption.value = customerOption ?? null;
    customerMode.value = customerOption
        ? 'existing'
        : booking.phone === '—'
          ? 'walk-in'
          : 'new-member';
    draft.value = {
        customerId: customerOption?.customer.id ?? null,
        walkInName: customerOption ? '' : booking.customer,
        newMemberPhone:
            customerOption || booking.phone === '—' ? '' : booking.phone,
        vehicle: booking.vehicle,
        plate: booking.plate,
        serviceIds: [...booking.serviceIds],
        date: booking.date,
    };
    detailBookingId.value = null;
    isCreateOpen.value = true;
}

function saveBooking(): void {
    if (!canCreate.value) {
        return;
    }

    const customer = draftCustomer.value;
    const bookingFields = {
        customerId: customer?.id ?? null,
        customer: customer?.name ?? draft.value.walkInName,
        phone: (customer?.phone ?? draft.value.newMemberPhone.trim()) || '—',
        vehicle: draft.value.vehicle || '—',
        plate: draft.value.plate.toUpperCase(),
        service: draftServices.value.map((service) => service.name).join(', '),
        serviceIds: [...draft.value.serviceIds],
        date: draft.value.date,
        estimate: draftTotal.value,
    };

    if (editingBookingId.value !== null) {
        bookingList.value = bookingList.value.map((booking) =>
            booking.id === editingBookingId.value
                ? { ...booking, ...bookingFields }
                : booking,
        );
        closeBookingForm();

        return;
    }

    const sequence = bookingList.value.length + 1;

    bookingList.value = [
        {
            id: 1000 + sequence,
            code: `ORD-BK-${formatDateCode(draft.value.date)}${String(sequence).padStart(2, '0')}`,
            ...bookingFields,
            bookingDate: props.today,
            orderStatus: 'booking',
            notes: '—',
        },
        ...bookingList.value,
    ];

    closeBookingForm();
}
</script>

<template>
    <Head :title="`${brand.name} — Booking Order`" />

    <div class="space-y-4">
        <div class="flex justify-end">
            <button
                type="button"
                class="flex items-center gap-2 rounded-xl bg-gradient-to-r from-cyan-500 to-sky-600 px-3 py-2 text-sm font-medium text-white shadow-lg shadow-cyan-500/25 transition hover:from-cyan-600 hover:to-sky-700"
                @click="openCreateBooking"
            >
                <Plus class="h-4 w-4" />
                Buat Booking
            </button>
        </div>

        <section class="grid grid-cols-1 gap-4 sm:grid-cols-2">
            <StatCard
                label="Booking hari ini"
                :value="String(todayBookings.length)"
                caption="jadwal kedatangan hari ini"
                :icon="CalendarClock"
            />
            <StatCard
                label="Booking mendatang"
                :value="String(upcomingBookings.length)"
                caption="jadwal setelah hari ini"
                :icon="Clock"
                tone="amber"
            />
        </section>

        <!-- Booking boards: hari ini, mendatang, lalu yang sudah lewat -->
        <section
            v-for="board in bookingBoards"
            :key="board.key"
            class="rounded-2xl border border-slate-200/80 bg-white p-5 shadow-sm"
        >
            <div class="flex items-center justify-between">
                <div>
                    <h3 class="text-sm font-semibold text-slate-900">
                        {{ board.title }}
                    </h3>
                    <p class="mt-0.5 text-xs text-slate-500">
                        {{ board.caption }}
                    </p>
                </div>
                <span
                    class="flex items-center gap-1.5 rounded-full px-2.5 py-1 text-xs font-medium"
                    :class="board.badgeTone"
                >
                    {{ board.badge }}
                </span>
            </div>

            <ul v-if="board.bookings.length > 0" class="mt-4 space-y-2.5">
                <li
                    v-for="booking in board.bookings"
                    :key="booking.id"
                    class="flex flex-wrap items-center gap-3 rounded-xl border border-slate-100 bg-slate-50/60 p-3.5 transition hover:border-cyan-200 hover:bg-white"
                >
                    <div class="min-w-0 flex-1">
                        <p
                            class="text-xl font-bold tracking-wide text-slate-900"
                        >
                            {{ booking.plate }}
                        </p>
                        <p class="mt-0.5 text-xs text-slate-600">
                            {{ booking.vehicle }}
                        </p>
                        <p class="mt-1.5 text-sm font-medium text-slate-800">
                            {{ booking.customer }}
                            <span class="font-normal text-slate-400">
                                ({{ bookingCustomerType(booking) }})
                            </span>
                        </p>
                        <p
                            class="mt-0.5 flex items-center gap-1 text-[11px] text-slate-500"
                        >
                            <Phone class="h-3 w-3" />
                            {{ booking.phone }}
                        </p>
                        <p class="mt-1 truncate text-xs text-slate-600">
                            {{ booking.service }}
                        </p>
                        <div class="mt-1.5 flex flex-wrap items-center gap-2">
                            <span
                                class="rounded-md bg-white px-1.5 py-0.5 text-[11px] font-medium text-slate-500 ring-1 ring-slate-200"
                            >
                                {{ booking.code }}
                            </span>
                            <span class="text-[11px] text-slate-400">
                                {{ dayLabelFor(booking.date) }}
                            </span>
                        </div>
                    </div>
                    <StatusPill :status="bookingPill(booking)" />
                    <button
                        type="button"
                        class="rounded-lg px-3 py-1.5 text-xs font-medium text-cyan-700 transition hover:bg-cyan-50"
                        @click="detailBookingId = booking.id"
                    >
                        Detail
                    </button>
                </li>
            </ul>

            <EmptyState
                v-else
                :icon="board.icon"
                :title="board.emptyTitle"
                :caption="board.emptyCaption"
            />
        </section>
    </div>

    <!-- Booking detail -->
    <SlideOver
        :open="detailBooking !== null"
        :title="detailBooking?.code"
        :caption="
            detailBooking
                ? `Tanggal Booking: ${formatDate(detailBooking.bookingDate)}`
                : undefined
        "
        @close="detailBookingId = null"
    >
        <div v-if="detailBooking" class="space-y-5">
            <StatusPill :status="bookingPill(detailBooking)" />

            <div class="rounded-2xl bg-slate-50 p-4">
                <p class="text-2xl font-bold tracking-wide text-slate-900">
                    {{ detailBooking.plate }}
                </p>
                <p class="mt-0.5 text-sm text-slate-600">
                    {{ detailBooking.vehicle }}
                </p>
                <p class="mt-3 text-sm font-semibold text-slate-900">
                    {{ detailBooking.customer }}
                    <span class="font-normal text-slate-400">
                        ({{ bookingCustomerType(detailBooking) }})
                    </span>
                </p>
                <p
                    class="mt-1 flex items-center gap-1.5 text-xs text-slate-500"
                >
                    <Phone class="h-3.5 w-3.5" />
                    {{ detailBooking.phone }}
                </p>
            </div>

            <dl class="space-y-2 text-sm">
                <div class="flex justify-between">
                    <dt class="text-slate-500">Layanan</dt>
                    <dd class="font-medium text-slate-800">
                        {{ detailBooking.service }}
                    </dd>
                </div>
                <div class="flex justify-between">
                    <dt class="text-slate-500">Tanggal Booking</dt>
                    <dd class="text-slate-800">
                        {{ formatDate(detailBooking.bookingDate) }}
                    </dd>
                </div>
                <div class="flex justify-between">
                    <dt class="text-slate-500">Tanggal Order</dt>
                    <dd class="text-slate-800">
                        {{ formatDate(detailBooking.date) }} •
                        {{ dayLabelFor(detailBooking.date) }}
                    </dd>
                </div>
            </dl>
        </div>

        <template #footer>
            <button
                v-if="canEditDetailBooking"
                type="button"
                class="flex-1 rounded-xl border border-cyan-200 py-2.5 text-sm font-semibold text-cyan-700 transition hover:bg-cyan-50"
                @click="startEditingBooking"
            >
                Edit Booking
            </button>
            <button
                type="button"
                class="flex-1 rounded-xl bg-slate-900 py-2.5 text-sm font-semibold text-white transition hover:bg-slate-800"
                @click="detailBookingId = null"
            >
                Tutup
            </button>
        </template>
    </SlideOver>

    <!-- Create booking: the order form plus the date the customer is coming -->
    <ModalDialog
        :open="isCreateOpen"
        :title="editingBookingId === null ? 'Buat booking' : 'Edit booking'"
        :caption="
            editingBookingId === null
                ? 'Jadwalkan kedatangan customer'
                : 'Perbarui data booking sebelum diproses'
        "
        size="lg"
        @close="closeBookingForm"
    >
        <div class="space-y-5">
            <!-- Customer -->
            <div>
                <label
                    for="booking-customer"
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
                        id="booking-customer"
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
                        Booking dicatat tanpa membuat data member.
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
                        Data member dan booking diinput bersamaan.
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

            <!-- Booking date: the one field an order does not have -->
            <div>
                <label
                    for="booking-date"
                    class="text-[11px] font-medium tracking-wider text-slate-400 uppercase"
                >
                    Tanggal kedatangan
                </label>
                <input
                    id="booking-date"
                    v-model="draft.date"
                    type="date"
                    :min="today"
                    class="mt-2 w-full rounded-xl border border-slate-200 px-3 py-2.5 text-sm focus:border-cyan-400 focus:outline-none"
                />
                <p
                    v-if="draft.date !== ''"
                    class="mt-1.5 text-[11px] text-slate-400"
                >
                    {{ formatDate(draft.date) }} •
                    {{ dayLabelFor(draft.date) }}
                </p>
                <p
                    v-if="draft.date !== '' && !hasBookableDate"
                    class="mt-1.5 text-[11px] font-medium text-red-600"
                >
                    Tanggal kedatangan tidak boleh sebelum hari ini.
                </p>
            </div>

            <!-- Summary -->
            <div class="space-y-2 rounded-2xl bg-slate-50 p-4">
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
                @click="closeBookingForm"
            >
                Batal
            </button>
            <button
                type="button"
                class="flex-1 rounded-xl bg-gradient-to-r from-cyan-500 to-sky-600 py-2.5 text-sm font-semibold text-white transition hover:from-cyan-600 hover:to-sky-700 disabled:cursor-not-allowed disabled:from-slate-300 disabled:to-slate-300"
                :disabled="!canCreate"
                @click="saveBooking"
            >
                {{
                    editingBookingId === null
                        ? 'Simpan booking'
                        : 'Simpan perubahan'
                }}
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
