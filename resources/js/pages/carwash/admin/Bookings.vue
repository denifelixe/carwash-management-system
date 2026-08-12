<script setup lang="ts">
import { Head } from '@inertiajs/vue3';
import {
    CalendarClock,
    CalendarPlus,
    CircleCheck,
    Clock,
    Phone,
    Plus,
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
    CarwashBooking,
    CarwashBrand,
    CarwashCustomer,
    CarwashService,
} from '@/types/carwash';

const props = defineProps<{
    brand: CarwashBrand;
    bookings: CarwashBooking[];
    slots: string[];
    services: CarwashService[];
    customers: CarwashCustomer[];
}>();

const bookingList = ref<CarwashBooking[]>(
    props.bookings.map((booking) => ({ ...booking })),
);

const search = ref<string>('');
const statusFilter = ref<string>('Semua');
const detailBookingId = ref<number | null>(null);
const isCreateOpen = ref<boolean>(false);

const draft = ref({
    customerId: null as number | null,
    walkInName: '',
    phone: '',
    vehicle: '',
    plate: '',
    serviceId: props.services[0].id,
    date: '5 Agu 2026',
    time: props.slots[0],
    notes: '',
});

const filterOptions = ['Semua', 'terjadwal', 'dikerjakan', 'selesai', 'batal'];

const filteredBookings = computed<CarwashBooking[]>(() => {
    const query = search.value.trim().toLowerCase();

    return bookingList.value.filter((booking) => {
        const matchesStatus =
            statusFilter.value === 'Semua' ||
            booking.status === statusFilter.value;
        const matchesQuery =
            query === '' ||
            booking.code.toLowerCase().includes(query) ||
            booking.customer.toLowerCase().includes(query) ||
            booking.plate.toLowerCase().includes(query);

        return matchesStatus && matchesQuery;
    });
});

/** Bookings still ahead of the crew, newest first in the day column. */
const upcomingBookings = computed<CarwashBooking[]>(() =>
    bookingList.value.filter(
        (booking) =>
            booking.status === 'terjadwal' || booking.status === 'dikerjakan',
    ),
);

const todayBookings = computed<CarwashBooking[]>(() =>
    upcomingBookings.value.filter((booking) => booking.dayLabel === 'Hari ini'),
);

const scheduledValue = computed<number>(() =>
    upcomingBookings.value.reduce(
        (total, booking) => total + booking.estimate,
        0,
    ),
);

const completedCount = computed<number>(
    () =>
        bookingList.value.filter((booking) => booking.status === 'selesai')
            .length,
);

const detailBooking = computed<CarwashBooking | null>(
    () =>
        bookingList.value.find(
            (booking) => booking.id === detailBookingId.value,
        ) ?? null,
);

const draftService = computed<CarwashService>(
    () =>
        props.services.find(
            (service) => service.id === draft.value.serviceId,
        ) ?? props.services[0],
);

const canCreate = computed<boolean>(
    () =>
        draft.value.plate.trim() !== '' &&
        (draft.value.customerId !== null ||
            draft.value.walkInName.trim() !== ''),
);

function pickCustomer(customerId: number | null): void {
    draft.value.customerId = customerId;

    const customer = props.customers.find((item) => item.id === customerId);

    if (customer) {
        draft.value.vehicle = customer.vehicle;
        draft.value.plate = customer.plate;
        draft.value.phone = customer.phone;
        draft.value.walkInName = '';
    }
}

function setStatus(booking: CarwashBooking, status: string): void {
    booking.status = status;
}

function createBooking(): void {
    if (!canCreate.value) {
        return;
    }

    const customer = props.customers.find(
        (item) => item.id === draft.value.customerId,
    );
    const sequence = bookingList.value.length + 1;

    bookingList.value = [
        {
            id: 1000 + sequence,
            code: `BK-260805-${String(sequence).padStart(2, '0')}`,
            customerId: customer?.id ?? null,
            customer: customer?.name ?? draft.value.walkInName,
            phone: customer?.phone ?? (draft.value.phone || '—'),
            vehicle: draft.value.vehicle || '—',
            plate: draft.value.plate.toUpperCase(),
            service: draftService.value.name,
            serviceId: draftService.value.id,
            date: draft.value.date,
            time: draft.value.time,
            dayLabel: 'Terjadwal',
            status: 'terjadwal',
            estimate: draftService.value.price,
            notes: draft.value.notes || '—',
        },
        ...bookingList.value,
    ];

    draft.value.walkInName = '';
    draft.value.notes = '';
    isCreateOpen.value = false;
}
</script>

<template>
    <Head :title="`${brand.name} — Booking Order`" />

    <div class="space-y-4">
        <section class="grid grid-cols-1 gap-4 sm:grid-cols-2 xl:grid-cols-4">
            <StatCard
                label="Booking hari ini"
                :value="String(todayBookings.length)"
                caption="terjadwal & dikerjakan"
                :icon="CalendarClock"
            />
            <StatCard
                label="Total mendatang"
                :value="String(upcomingBookings.length)"
                caption="belum dikerjakan"
                :icon="Clock"
                tone="amber"
            />
            <StatCard
                label="Nilai terjadwal"
                :value="formatCurrency(scheduledValue)"
                caption="estimasi pendapatan"
                :icon="CalendarPlus"
                tone="emerald"
            />
            <StatCard
                label="Selesai"
                :value="String(completedCount)"
                caption="booking rampung"
                :icon="CircleCheck"
            />
        </section>

        <!-- Upcoming timeline -->
        <section
            class="rounded-2xl border border-slate-200/80 bg-white p-5 shadow-sm"
        >
            <div class="flex items-center justify-between">
                <div>
                    <h3 class="text-sm font-semibold text-slate-900">
                        Jadwal mendatang
                    </h3>
                    <p class="mt-0.5 text-xs text-slate-500">
                        Urutan kedatangan yang harus disiapkan crew
                    </p>
                </div>
                <span
                    class="flex items-center gap-1.5 rounded-full bg-cyan-50 px-2.5 py-1 text-xs font-medium text-cyan-700"
                >
                    <span
                        class="h-1.5 w-1.5 animate-pulse rounded-full bg-cyan-500"
                    ></span>
                    {{ upcomingBookings.length }} antre
                </span>
            </div>

            <ul v-if="upcomingBookings.length > 0" class="mt-4 space-y-2.5">
                <li
                    v-for="booking in upcomingBookings"
                    :key="booking.id"
                    class="flex flex-wrap items-center gap-3 rounded-xl border border-slate-100 bg-slate-50/60 p-3.5 transition hover:border-cyan-200 hover:bg-white"
                >
                    <div
                        class="flex h-12 w-16 shrink-0 flex-col items-center justify-center rounded-xl bg-white ring-1 ring-slate-200"
                    >
                        <span class="text-sm font-semibold text-slate-900">
                            {{ booking.time }}
                        </span>
                        <span class="text-[10px] text-slate-500">
                            {{ booking.dayLabel }}
                        </span>
                    </div>
                    <div class="min-w-0 flex-1">
                        <p class="text-sm font-semibold text-slate-900">
                            {{ booking.customer }}
                        </p>
                        <p class="truncate text-xs text-slate-500">
                            {{ booking.service }} • {{ booking.plate }}
                        </p>
                        <p
                            class="mt-0.5 flex items-center gap-1 text-[11px] text-slate-400"
                        >
                            <Phone class="h-3 w-3" />
                            {{ booking.phone }}
                        </p>
                    </div>
                    <div class="text-right">
                        <StatusPill :status="booking.status" />
                        <p
                            class="mt-1 text-sm font-medium text-slate-900 tabular-nums"
                        >
                            {{ formatCurrency(booking.estimate) }}
                        </p>
                    </div>
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
                :icon="CalendarClock"
                title="Belum ada booking mendatang"
                caption="Booking baru akan tampil di sini."
            />
        </section>

        <!-- All bookings -->
        <section
            class="rounded-2xl border border-slate-200/80 bg-white shadow-sm"
        >
            <div
                class="flex flex-wrap items-center justify-between gap-3 border-b border-slate-100 p-5"
            >
                <div>
                    <h3 class="text-sm font-semibold text-slate-900">
                        Riwayat booking
                    </h3>
                    <p class="mt-0.5 text-xs text-slate-500">
                        {{ filteredBookings.length }} booking ditampilkan
                    </p>
                </div>
                <div class="flex flex-wrap items-center gap-2">
                    <DataToolbar
                        v-model:search="search"
                        placeholder="Cari kode / customer"
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
                        Buat Booking
                    </button>
                </div>
            </div>

            <div v-if="filteredBookings.length > 0" class="overflow-x-auto">
                <table class="w-full min-w-[880px] text-sm">
                    <thead>
                        <tr
                            class="border-b border-slate-100 text-left text-[11px] font-medium tracking-wider text-slate-400 uppercase"
                        >
                            <th class="px-5 py-3">Kode</th>
                            <th class="px-5 py-3">Customer</th>
                            <th class="px-5 py-3">Layanan</th>
                            <th class="px-5 py-3">Jadwal</th>
                            <th class="px-5 py-3">Status</th>
                            <th class="px-5 py-3 text-right">Estimasi</th>
                            <th class="px-5 py-3"></th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-50">
                        <tr
                            v-for="booking in filteredBookings"
                            :key="booking.id"
                            class="transition hover:bg-slate-50/70"
                        >
                            <td class="px-5 py-3.5 font-medium text-slate-900">
                                {{ booking.code }}
                            </td>
                            <td class="px-5 py-3.5">
                                <p class="text-slate-700">
                                    {{ booking.customer }}
                                </p>
                                <p class="text-[11px] text-slate-500">
                                    {{ booking.plate }}
                                </p>
                            </td>
                            <td class="px-5 py-3.5 text-slate-600">
                                {{ booking.service }}
                            </td>
                            <td class="px-5 py-3.5">
                                <p class="text-slate-700">
                                    {{ booking.date }}
                                </p>
                                <p class="text-[11px] text-slate-500">
                                    {{ booking.time }} WIB
                                </p>
                            </td>
                            <td class="px-5 py-3.5">
                                <StatusPill :status="booking.status" />
                            </td>
                            <td
                                class="px-5 py-3.5 text-right font-medium text-slate-900 tabular-nums"
                            >
                                {{ formatCurrency(booking.estimate) }}
                            </td>
                            <td class="px-5 py-3.5 text-right">
                                <button
                                    type="button"
                                    class="rounded-lg px-3 py-1.5 text-xs font-medium text-cyan-700 transition hover:bg-cyan-50"
                                    @click="detailBookingId = booking.id"
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
                :icon="CalendarClock"
                title="Booking tidak ditemukan"
                caption="Ubah kata kunci atau filter status."
            />
        </section>
    </div>

    <!-- Booking detail -->
    <SlideOver
        :open="detailBooking !== null"
        :title="detailBooking?.code"
        :caption="`${detailBooking?.date} • ${detailBooking?.time} WIB`"
        @close="detailBookingId = null"
    >
        <div v-if="detailBooking" class="space-y-5">
            <StatusPill :status="detailBooking.status" />

            <div class="rounded-2xl bg-slate-50 p-4">
                <p class="text-sm font-semibold text-slate-900">
                    {{ detailBooking.customer }}
                </p>
                <p
                    class="mt-1 flex items-center gap-1.5 text-xs text-slate-500"
                >
                    <Phone class="h-3.5 w-3.5" />
                    {{ detailBooking.phone }}
                </p>
                <p class="mt-0.5 text-xs text-slate-500">
                    {{ detailBooking.vehicle }} • {{ detailBooking.plate }}
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
                    <dt class="text-slate-500">Jadwal</dt>
                    <dd class="text-slate-800">
                        {{ detailBooking.date }}, {{ detailBooking.time }}
                    </dd>
                </div>
                <div
                    class="flex justify-between border-t border-slate-200 pt-2"
                >
                    <dt class="font-medium text-slate-600">Estimasi biaya</dt>
                    <dd class="font-semibold text-slate-900 tabular-nums">
                        {{ formatCurrency(detailBooking.estimate) }}
                    </dd>
                </div>
            </dl>

            <div>
                <p class="text-xs font-medium text-slate-500">Catatan</p>
                <p
                    class="mt-1.5 rounded-xl border border-slate-200 p-3 text-xs text-slate-600"
                >
                    {{ detailBooking.notes }}
                </p>
            </div>
        </div>

        <template #footer>
            <div v-if="detailBooking" class="flex gap-2">
                <button
                    v-if="detailBooking.status === 'terjadwal'"
                    type="button"
                    class="flex-1 rounded-xl border border-rose-200 py-2.5 text-sm font-medium text-rose-600 transition hover:bg-rose-50"
                    @click="setStatus(detailBooking, 'batal')"
                >
                    Batalkan
                </button>
                <button
                    v-if="detailBooking.status === 'terjadwal'"
                    type="button"
                    class="flex-1 rounded-xl bg-gradient-to-r from-cyan-500 to-sky-600 py-2.5 text-sm font-semibold text-white transition hover:from-cyan-600 hover:to-sky-700"
                    @click="setStatus(detailBooking, 'dikerjakan')"
                >
                    Mulai kerjakan
                </button>
                <button
                    v-else-if="detailBooking.status === 'dikerjakan'"
                    type="button"
                    class="flex-1 rounded-xl bg-gradient-to-r from-emerald-500 to-teal-600 py-2.5 text-sm font-semibold text-white transition hover:from-emerald-600 hover:to-teal-700"
                    @click="setStatus(detailBooking, 'selesai')"
                >
                    Tandai selesai
                </button>
                <button
                    v-else
                    type="button"
                    class="flex-1 rounded-xl bg-slate-900 py-2.5 text-sm font-semibold text-white transition hover:bg-slate-800"
                    @click="detailBookingId = null"
                >
                    Tutup
                </button>
            </div>
        </template>
    </SlideOver>

    <!-- Create booking -->
    <ModalDialog
        :open="isCreateOpen"
        title="Buat booking"
        caption="Jadwalkan kedatangan customer"
        size="lg"
        @close="isCreateOpen = false"
    >
        <div class="space-y-5">
            <div>
                <p
                    class="text-[11px] font-medium tracking-wider text-slate-400 uppercase"
                >
                    Customer
                </p>
                <select
                    :value="draft.customerId ?? ''"
                    class="mt-2 w-full rounded-xl border border-slate-200 px-3 py-2.5 text-sm focus:border-cyan-400 focus:outline-none"
                    @change="
                        pickCustomer(
                            ($event.target as HTMLSelectElement).value === ''
                                ? null
                                : Number(
                                      ($event.target as HTMLSelectElement)
                                          .value,
                                  ),
                        )
                    "
                >
                    <option value="">Customer baru / non-member</option>
                    <option
                        v-for="customer in customers"
                        :key="customer.id"
                        :value="customer.id"
                    >
                        {{ customer.name }} — {{ customer.plate }}
                    </option>
                </select>
                <div
                    v-if="draft.customerId === null"
                    class="mt-2 grid grid-cols-2 gap-3"
                >
                    <input
                        v-model="draft.walkInName"
                        type="text"
                        placeholder="Nama customer"
                        class="rounded-xl border border-slate-200 px-3 py-2.5 text-sm focus:border-cyan-400 focus:outline-none"
                    />
                    <input
                        v-model="draft.phone"
                        type="tel"
                        placeholder="Nomor HP"
                        class="rounded-xl border border-slate-200 px-3 py-2.5 text-sm focus:border-cyan-400 focus:outline-none"
                    />
                </div>
            </div>

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

            <div>
                <p
                    class="text-[11px] font-medium tracking-wider text-slate-400 uppercase"
                >
                    Layanan
                </p>
                <select
                    v-model="draft.serviceId"
                    class="mt-2 w-full rounded-xl border border-slate-200 px-3 py-2.5 text-sm focus:border-cyan-400 focus:outline-none"
                >
                    <option
                        v-for="service in services"
                        :key="service.id"
                        :value="service.id"
                    >
                        {{ service.name }} —
                        {{ formatCurrency(service.price) }}
                    </option>
                </select>
            </div>

            <div>
                <p
                    class="text-[11px] font-medium tracking-wider text-slate-400 uppercase"
                >
                    Tanggal
                </p>
                <input
                    v-model="draft.date"
                    type="text"
                    class="mt-2 w-full rounded-xl border border-slate-200 px-3 py-2.5 text-sm focus:border-cyan-400 focus:outline-none"
                />
            </div>

            <div>
                <p
                    class="text-[11px] font-medium tracking-wider text-slate-400 uppercase"
                >
                    Jam kedatangan
                </p>
                <div class="mt-2 grid grid-cols-4 gap-2">
                    <button
                        v-for="slot in slots"
                        :key="slot"
                        type="button"
                        class="flex items-center justify-center gap-1 rounded-xl py-2.5 text-xs font-medium transition"
                        :class="
                            draft.time === slot
                                ? 'bg-gradient-to-r from-cyan-500 to-sky-600 text-white shadow-md shadow-cyan-500/25'
                                : 'bg-slate-100 text-slate-600 hover:bg-slate-200'
                        "
                        @click="draft.time = slot"
                    >
                        <Clock class="h-3.5 w-3.5" />
                        {{ slot }}
                    </button>
                </div>
            </div>

            <div>
                <p
                    class="text-[11px] font-medium tracking-wider text-slate-400 uppercase"
                >
                    Catatan
                </p>
                <textarea
                    v-model="draft.notes"
                    rows="2"
                    placeholder="Permintaan khusus dari customer"
                    class="mt-2 w-full resize-none rounded-xl border border-slate-200 px-3 py-2.5 text-sm focus:border-cyan-400 focus:outline-none"
                ></textarea>
            </div>

            <div
                class="flex items-center justify-between rounded-2xl bg-slate-50 p-4"
            >
                <div>
                    <p class="text-[11px] text-slate-500">
                        {{ draftService.name }} • ±
                        {{ draftService.duration }} menit
                    </p>
                    <p
                        class="text-lg font-semibold text-slate-900 tabular-nums"
                    >
                        {{ formatCurrency(draftService.price) }}
                    </p>
                </div>
                <p class="text-xs font-medium text-emerald-600">
                    +{{ draftService.stamps }} stempel
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
                @click="createBooking"
            >
                Simpan booking
            </button>
        </template>
    </ModalDialog>
</template>
