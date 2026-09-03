<script setup lang="ts">
import { Head, router, useForm } from '@inertiajs/vue3';
import {
    Car,
    Pencil,
    Phone,
    Plus,
    Power,
    Repeat,
    UserCheck,
    UserPlus,
} from '@lucide/vue';
import { computed, ref, watch } from 'vue';
import {
    index as indexLeads,
    store as storeLead,
    update as updateLead,
    updateStatus as updateLeadStatus,
} from '@/actions/App/Http/Controllers/Admin/LeadController';
import DataPagination from '@/components/demo/DataPagination.vue';
import DataToolbar from '@/components/demo/DataToolbar.vue';
import EmptyState from '@/components/demo/EmptyState.vue';
import ModalDialog from '@/components/demo/ModalDialog.vue';
import SlideOver from '@/components/demo/SlideOver.vue';
import StatCard from '@/components/demo/StatCard.vue';
import StatusPill from '@/components/demo/StatusPill.vue';
import {
    formatCurrency,
    formatShortCurrency,
} from '@/composables/useCarwashFormat';
import { formatPlate } from '@/lib/vehiclePlate';
import type {
    CarwashBrand,
    CarwashLead,
    CarwashLeadDetail,
    CarwashLeadFilters,
    CarwashLeadStats,
    CarwashOrder,
    CarwashPaginated,
} from '@/types/demo';

const props = defineProps<{
    mode: 'demo' | 'live';
    brand: CarwashBrand;
    leads: CarwashPaginated<CarwashLead>;
    stats: CarwashLeadStats;
    leadDetail: CarwashLeadDetail | null;
    filters: CarwashLeadFilters;
    statusFilters: string[];
    conversionFilters: string[];
    capabilities: { create: boolean; update: boolean };
}>();

const search = ref<string>(props.filters.q);
const statusFilter = ref<string>(props.filters.status);
const conversionFilter = ref<string>(props.filters.conversion);
const selectableStatusFilters = computed<string[]>(() =>
    props.statusFilters.filter((filter) => filter !== 'Semua'),
);
const detailLeadId = ref<number | null>(props.leadDetail?.lead.id ?? null);
const isFormOpen = ref<boolean>(false);
const editingLeadId = ref<number | null>(null);

const draft = ref({
    name: '',
    phone: '',
    vehicleName: '',
    plate: '',
    notes: '',
});

const detailLead = computed<CarwashLead | null>(() =>
    props.leadDetail?.lead.id === detailLeadId.value
        ? props.leadDetail.lead
        : null,
);

/** Visits belonging to the lead in the drawer. */
const detailOrders = computed<CarwashOrder[]>(() =>
    detailLead.value ? (props.leadDetail?.orders ?? []) : [],
);

const canSave = computed<boolean>(
    () => draft.value.name.trim() !== '' && draft.value.plate.trim() !== '',
);

const leadForm = useForm({
    name: '',
    phone: '' as string | null,
    vehicle_name: '' as string | null,
    vehicle_plate: '',
    notes: '' as string | null,
});
const statusForm = useForm({ is_active: true });

let searchTimer: ReturnType<typeof setTimeout> | undefined;

watch(search, () => {
    clearTimeout(searchTimer);
    searchTimer = setTimeout(() => visit({ q: search.value, page: 1 }), 300);
});

watch(
    () => props.leadDetail?.lead.id,
    (leadId) => {
        if (leadId !== undefined) {
            detailLeadId.value = leadId;
        }
    },
);

function openDetail(lead: CarwashLead): void {
    detailLeadId.value = lead.id;

    router.reload({ only: ['leadDetail'], data: { lead: lead.id } });
}

function closeDetail(): void {
    detailLeadId.value = null;
}

function visit(overrides: Partial<CarwashLeadFilters> = {}): void {
    router.get(
        indexLeads.url(),
        {
            q: search.value,
            status: statusFilter.value,
            conversion: conversionFilter.value,
            page: props.filters.page,
            ...overrides,
        },
        { preserveState: true, preserveScroll: true, replace: true },
    );
}

function toggleStatusFilter(status: string): void {
    const nextStatus = statusFilter.value === status ? 'Semua' : status;

    statusFilter.value = nextStatus;
    visit({ status: nextStatus, page: 1 });
}

function applyConversionFilter(conversion: string): void {
    conversionFilter.value = conversion;
    visit({ conversion, page: 1 });
}

function openCreateForm(): void {
    if (!props.capabilities.create) {
        return;
    }

    editingLeadId.value = null;
    draft.value = {
        name: '',
        phone: '',
        vehicleName: '',
        plate: '',
        notes: '',
    };
    leadForm.clearErrors();
    isFormOpen.value = true;
}

function openEditForm(): void {
    if (!props.capabilities.update || !detailLead.value) {
        return;
    }

    editingLeadId.value = detailLead.value.id;
    draft.value = {
        name: detailLead.value.name,
        phone: detailLead.value.phone,
        vehicleName: detailLead.value.vehicleName,
        plate: detailLead.value.vehiclePlate,
        notes: detailLead.value.notes,
    };
    leadForm.clearErrors();
    isFormOpen.value = true;
}

function saveLead(): void {
    const requiredCapability =
        editingLeadId.value === null
            ? props.capabilities.create
            : props.capabilities.update;

    if (!requiredCapability || !canSave.value) {
        return;
    }

    leadForm.name = draft.value.name;
    leadForm.phone = draft.value.phone || null;
    leadForm.vehicle_name = draft.value.vehicleName || null;
    leadForm.vehicle_plate = draft.value.plate;
    leadForm.notes = draft.value.notes || null;

    const action =
        editingLeadId.value === null
            ? storeLead()
            : updateLead(editingLeadId.value);

    leadForm.submit(action, {
        preserveScroll: true,
        onSuccess: () => {
            isFormOpen.value = false;
            editingLeadId.value = null;
        },
    });
}

function toggleStatus(lead: CarwashLead): void {
    if (!props.capabilities.update) {
        return;
    }

    statusForm.is_active = lead.status !== 'aktif';
    statusForm.submit(updateLeadStatus(lead.id), { preserveScroll: true });
}
</script>

<template>
    <Head :title="`${brand.name} — Leads`" />

    <div class="space-y-4">
        <section class="grid grid-cols-1 gap-4 sm:grid-cols-2 xl:grid-cols-4">
            <StatCard
                label="Total lead"
                :value="String(stats.total)"
                caption="seluruh non-member tercatat"
                :icon="UserPlus"
            />
            <StatCard
                label="Belum jadi member"
                :value="String(stats.open)"
                caption="siap ditawari membership"
                :icon="Car"
                tone="amber"
            />
            <StatCard
                label="Sudah balik lagi"
                :value="String(stats.returning)"
                caption="minimal 2 kunjungan"
                :icon="Repeat"
                tone="emerald"
            />
            <StatCard
                label="Sudah jadi member"
                :value="String(stats.converted)"
                caption="lead yang berhasil dikonversi"
                :icon="UserCheck"
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
                        Database lead
                    </h3>
                    <p class="mt-0.5 text-xs text-slate-500">
                        {{ leads.meta.total }} lead ditemukan
                    </p>
                </div>
                <div class="flex flex-wrap items-center gap-2">
                    <DataToolbar
                        v-model:search="search"
                        placeholder="Cari nama / plat / HP"
                    >
                        <div
                            class="flex flex-wrap items-center gap-1.5"
                            aria-label="Filter lead"
                        >
                            <button
                                v-for="filter in conversionFilters"
                                :key="filter"
                                type="button"
                                class="rounded-full px-3 py-1.5 text-xs font-medium transition"
                                :class="
                                    conversionFilter === filter
                                        ? 'bg-slate-900 text-white'
                                        : 'bg-slate-100 text-slate-600 hover:bg-slate-200'
                                "
                                :aria-pressed="conversionFilter === filter"
                                @click="applyConversionFilter(filter)"
                            >
                                {{ filter }}
                            </button>
                            <span
                                class="mx-0.5 h-5 w-px bg-slate-200"
                                aria-hidden="true"
                            />
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
                        </div>
                    </DataToolbar>
                    <button
                        v-if="capabilities.create"
                        type="button"
                        class="flex items-center gap-2 rounded-xl bg-gradient-to-r from-cyan-500 to-sky-600 px-3 py-2 text-sm font-medium text-white shadow-lg shadow-cyan-500/25 transition hover:from-cyan-600 hover:to-sky-700"
                        @click="openCreateForm"
                    >
                        <Plus class="h-4 w-4" />
                        Tambah Lead
                    </button>
                </div>
            </div>

            <div v-if="leads.data.length > 0" class="overflow-x-auto">
                <table class="w-full min-w-[780px] text-sm">
                    <thead>
                        <tr
                            class="border-b border-slate-100 text-left text-[11px] font-medium tracking-wider text-slate-400 uppercase"
                        >
                            <th class="px-5 py-3">Calon pelanggan</th>
                            <th class="px-5 py-3">Kendaraan</th>
                            <th class="px-5 py-3">Kunjungan</th>
                            <th class="px-5 py-3">Belanja</th>
                            <th class="px-5 py-3">Status</th>
                            <th class="px-5 py-3"></th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-50">
                        <tr
                            v-for="lead in leads.data"
                            :key="lead.id"
                            class="transition hover:bg-slate-50/70"
                        >
                            <td class="px-5 py-3.5">
                                <div class="flex items-center gap-3">
                                    <div
                                        class="flex h-9 w-9 items-center justify-center rounded-full bg-gradient-to-br from-slate-100 to-slate-200 text-xs font-semibold text-slate-600"
                                    >
                                        {{ lead.initials }}
                                    </div>
                                    <div>
                                        <p class="font-medium text-slate-900">
                                            {{ lead.name }}
                                        </p>
                                        <p class="text-[11px] text-slate-500">
                                            {{ lead.phone || 'Tanpa nomor' }}
                                        </p>
                                    </div>
                                </div>
                            </td>
                            <td class="px-5 py-3.5">
                                <p class="font-semibold text-slate-900">
                                    {{ formatPlate(lead.vehiclePlate) }}
                                </p>
                                <p class="text-[11px] text-slate-500">
                                    {{ lead.vehicleName || '—' }}
                                </p>
                            </td>
                            <td class="px-5 py-3.5">
                                <p class="text-slate-700 tabular-nums">
                                    {{ lead.visits }}×
                                </p>
                                <p class="text-[11px] text-slate-500">
                                    {{ lead.lastVisit }}
                                </p>
                            </td>
                            <td class="px-5 py-3.5">
                                <p
                                    class="font-medium text-slate-900 tabular-nums"
                                >
                                    {{ formatShortCurrency(lead.spend) }}
                                </p>
                            </td>
                            <td class="px-5 py-3.5">
                                <div class="flex flex-col items-start gap-1.5">
                                    <StatusPill
                                        :status="lead.status"
                                        :label="
                                            lead.status === 'aktif'
                                                ? 'Lead aktif'
                                                : 'Lead tidak aktif'
                                        "
                                    />
                                    <StatusPill
                                        v-if="lead.isConverted"
                                        status="aktif"
                                        label="Sudah jadi member"
                                    />
                                </div>
                            </td>
                            <td class="px-5 py-3.5 text-right">
                                <button
                                    type="button"
                                    class="rounded-lg px-3 py-1.5 text-xs font-medium text-cyan-700 transition hover:bg-cyan-50"
                                    @click="openDetail(lead)"
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
                :icon="UserPlus"
                title="Lead tidak ditemukan"
                caption="Lead tercatat otomatis dari setiap order non-member."
            />
            <DataPagination
                :meta="leads.meta"
                label="lead"
                @change="visit({ page: $event })"
            />
        </section>
    </div>

    <!-- Lead detail -->
    <SlideOver
        :open="detailLeadId !== null"
        :title="detailLead?.name"
        :caption="detailLead ? formatPlate(detailLead.vehiclePlate) : undefined"
        @close="closeDetail"
    >
        <div v-if="detailLead" class="space-y-5">
            <div class="grid grid-cols-3 gap-3">
                <div class="rounded-xl bg-slate-50 p-3">
                    <p class="text-[11px] text-slate-500">Kunjungan</p>
                    <p
                        class="mt-0.5 text-lg font-semibold text-slate-900 tabular-nums"
                    >
                        {{ detailLead.visits }}
                    </p>
                </div>
                <div class="rounded-xl bg-slate-50 p-3">
                    <p class="text-[11px] text-slate-500">Total belanja</p>
                    <p
                        class="mt-0.5 text-lg font-semibold text-slate-900 tabular-nums"
                    >
                        {{ formatShortCurrency(detailLead.spend) }}
                    </p>
                </div>
                <div class="rounded-xl bg-slate-50 p-3">
                    <p class="text-[11px] text-slate-500">Tercatat sejak</p>
                    <p class="mt-0.5 text-sm font-semibold text-slate-900">
                        {{ detailLead.firstSeen }}
                    </p>
                </div>
            </div>

            <p
                v-if="detailLead.isConverted"
                class="rounded-xl bg-emerald-50 px-3 py-2.5 text-[11px] text-emerald-800 ring-1 ring-emerald-100"
            >
                Lead ini sudah didaftarkan sebagai member, jadi order berikutnya
                dibuat lewat tab Member.
            </p>

            <div class="space-y-2">
                <div
                    class="flex items-center gap-3 rounded-xl border border-slate-200 p-3"
                >
                    <Phone class="h-4 w-4 shrink-0 text-slate-400" />
                    <span
                        class="text-sm"
                        :class="
                            detailLead.phone
                                ? 'text-slate-700'
                                : 'text-slate-400'
                        "
                    >
                        {{ detailLead.phone || 'Tidak ada nomor telepon' }}
                    </span>
                </div>
                <div
                    class="flex items-center gap-3 rounded-xl border border-slate-200 p-3"
                >
                    <Car class="h-4 w-4 shrink-0 text-slate-400" />
                    <span class="text-sm text-slate-700">
                        {{ formatPlate(detailLead.vehiclePlate) }} ·
                        {{ detailLead.vehicleName || 'Tipe belum dicatat' }}
                    </span>
                </div>
                <p
                    v-if="detailLead.notes"
                    class="rounded-xl border border-slate-200 p-3 text-sm text-slate-600"
                >
                    {{ detailLead.notes }}
                </p>
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
                    @click="toggleStatus(detailLead)"
                >
                    <Power class="h-3.5 w-3.5" />
                    {{
                        detailLead.status === 'aktif'
                            ? 'Nonaktifkan'
                            : 'Aktifkan'
                    }}
                </button>
            </div>

            <div>
                <p class="text-xs font-medium text-slate-500">
                    Riwayat kunjungan
                </p>
                <ul
                    v-if="detailOrders.length > 0"
                    class="mt-2 divide-y divide-slate-100 overflow-hidden rounded-xl border border-slate-200"
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
                        <div class="mt-1.5">
                            <StatusPill :status="order.status" />
                        </div>
                    </li>
                </ul>
                <p
                    v-else
                    class="mt-2 rounded-xl border border-dashed border-slate-200 py-6 text-center text-xs text-slate-400"
                >
                    Belum ada order tercatat untuk lead ini.
                </p>
            </div>
        </div>
        <div v-else class="animate-pulse space-y-4">
            <div class="h-20 rounded-2xl bg-slate-100"></div>
            <div class="h-16 rounded-xl bg-slate-100"></div>
            <div class="h-48 rounded-xl bg-slate-100"></div>
        </div>
    </SlideOver>

    <!-- Lead form -->
    <ModalDialog
        :open="isFormOpen"
        :title="editingLeadId === null ? 'Tambah lead' : 'Ubah lead'"
        caption="Calon pelanggan yang belum jadi member"
        @close="isFormOpen = false"
    >
        <div class="space-y-3">
            <div class="grid grid-cols-2 gap-3">
                <div>
                    <label
                        class="text-xs font-medium text-slate-600"
                        for="lead-plate"
                    >
                        Plat nomor
                    </label>
                    <input
                        id="lead-plate"
                        v-model="draft.plate"
                        type="text"
                        placeholder="B 1234 CDE"
                        class="mt-1.5 w-full rounded-xl border border-slate-200 bg-white px-3 py-2.5 text-sm text-slate-900 uppercase placeholder:text-slate-400 placeholder:normal-case focus:border-cyan-400 focus:outline-none"
                    />
                </div>
                <div>
                    <label
                        class="text-xs font-medium text-slate-600"
                        for="lead-vehicle-name"
                    >
                        Tipe mobil
                        <span class="text-slate-400">(opsional)</span>
                    </label>
                    <input
                        id="lead-vehicle-name"
                        v-model="draft.vehicleName"
                        type="text"
                        placeholder="Merk / tipe mobil"
                        class="mt-1.5 w-full rounded-xl border border-slate-200 bg-white px-3 py-2.5 text-sm text-slate-900 placeholder:text-slate-400 focus:border-cyan-400 focus:outline-none"
                    />
                </div>
            </div>
            <div class="grid grid-cols-2 gap-3">
                <div>
                    <label
                        class="text-xs font-medium text-slate-600"
                        for="lead-name"
                    >
                        Nama
                    </label>
                    <input
                        id="lead-name"
                        v-model="draft.name"
                        type="text"
                        placeholder="Nama pelanggan"
                        class="mt-1.5 w-full rounded-xl border border-slate-200 bg-white px-3 py-2.5 text-sm text-slate-900 placeholder:text-slate-400 focus:border-cyan-400 focus:outline-none"
                    />
                </div>
                <div>
                    <label
                        class="text-xs font-medium text-slate-600"
                        for="lead-phone"
                    >
                        Nomor telepon
                        <span class="text-slate-400">(opsional)</span>
                    </label>
                    <input
                        id="lead-phone"
                        v-model="draft.phone"
                        type="tel"
                        inputmode="tel"
                        placeholder="0812-xxxx-xxxx"
                        class="mt-1.5 w-full rounded-xl border border-slate-200 bg-white px-3 py-2.5 text-sm text-slate-900 placeholder:text-slate-400 focus:border-cyan-400 focus:outline-none"
                    />
                </div>
            </div>
            <div>
                <label
                    class="text-xs font-medium text-slate-600"
                    for="lead-notes"
                >
                    Catatan
                    <span class="text-slate-400">(opsional)</span>
                </label>
                <textarea
                    id="lead-notes"
                    v-model="draft.notes"
                    rows="2"
                    placeholder="Misal: minta dihubungi lagi bulan depan"
                    class="mt-1.5 w-full rounded-xl border border-slate-200 bg-white px-3 py-2.5 text-sm text-slate-900 placeholder:text-slate-400 focus:border-cyan-400 focus:outline-none"
                ></textarea>
            </div>

            <p
                class="rounded-xl bg-cyan-50 px-3 py-2.5 text-[11px] text-cyan-800 ring-1 ring-cyan-100"
            >
                Lead dikenali dari plat nomornya, jadi order non-member
                berikutnya dengan plat yang sama masuk ke baris ini.
            </p>
            <p
                v-if="Object.keys(leadForm.errors).length > 0"
                class="rounded-xl bg-rose-50 px-3 py-2.5 text-xs text-rose-700 ring-1 ring-rose-100"
            >
                {{ Object.values(leadForm.errors)[0] }}
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
                :disabled="!canSave || leadForm.processing"
                @click="saveLead"
            >
                Simpan lead
            </button>
        </template>
    </ModalDialog>
</template>
