<script setup lang="ts">
import { Head, useForm, usePage } from '@inertiajs/vue3';
import { Clock3, Pencil, Plus, Trash2, UserRoundCheck } from '@lucide/vue';
import { computed, ref, watch } from 'vue';
import {
    destroy as destroyWorkShift,
    store as storeWorkShift,
    update as updateWorkShift,
} from '@/actions/App/Http/Controllers/Admin/Master/WorkShiftController';
import DataToolbar from '@/components/demo/DataToolbar.vue';
import EmptyState from '@/components/demo/EmptyState.vue';
import ModalDialog from '@/components/demo/ModalDialog.vue';
import StatCard from '@/components/demo/StatCard.vue';
import StatusPill from '@/components/demo/StatusPill.vue';
import InputError from '@/components/InputError.vue';
import type { CarwashBrand } from '@/types/demo';

type WorkShift = {
    id: number;
    key: string;
    name: string;
    starts_at: string | null;
    ends_at: string | null;
    is_active: boolean;
    admin_count: number;
};

const props = defineProps<{
    mode: 'demo' | 'live';
    brand: CarwashBrand;
    workShifts: WorkShift[];
    capabilities: {
        create: boolean;
        update: boolean;
        delete: boolean;
    };
}>();

const workShiftList = ref(
    props.workShifts.map((workShift) => ({ ...workShift })),
);
const search = ref('');
const statusFilter = ref('Semua');
const editingWorkShiftId = ref<number | null>(null);
const isWorkShiftFormOpen = ref(false);
const deletingWorkShift = ref<WorkShift | null>(null);
const hasWorkHours = ref(true);

watch(
    () => props.workShifts,
    (workShifts) => {
        if (props.mode === 'live') {
            workShiftList.value = workShifts.map((workShift) => ({
                ...workShift,
            }));
        }
    },
);

const workShiftForm = useForm({
    key: '',
    name: '',
    starts_at: '08:00' as string | null,
    ends_at: '16:00' as string | null,
    is_active: true,
});
const deleteForm = useForm({});
const page = usePage<{ errors: Record<string, string> }>();

const deleteError = computed(() => page.props.errors.work_shift ?? '');
const filterOptions = ['Semua', 'Aktif', 'Nonaktif'];

const filteredWorkShifts = computed(() => {
    const query = search.value.trim().toLowerCase();

    return workShiftList.value.filter((workShift) => {
        const matchesStatus =
            statusFilter.value === 'Semua' ||
            (statusFilter.value === 'Aktif' && workShift.is_active) ||
            (statusFilter.value === 'Nonaktif' && !workShift.is_active);
        const matchesQuery =
            query === '' ||
            workShift.name.toLowerCase().includes(query) ||
            workShift.key.toLowerCase().includes(query);

        return matchesStatus && matchesQuery;
    });
});

const activeCount = computed(
    () => workShiftList.value.filter((workShift) => workShift.is_active).length,
);
const assignedAdminCount = computed(() =>
    workShiftList.value.reduce(
        (total, workShift) => total + workShift.admin_count,
        0,
    ),
);

function openCreateWorkShift(): void {
    editingWorkShiftId.value = null;
    hasWorkHours.value = true;
    workShiftForm.clearErrors();
    workShiftForm.defaults({
        key: '',
        name: '',
        starts_at: '08:00',
        ends_at: '16:00',
        is_active: true,
    });
    workShiftForm.reset();
    isWorkShiftFormOpen.value = true;
}

function openEditWorkShift(workShift: WorkShift): void {
    if (!props.capabilities.update) {
        return;
    }

    editingWorkShiftId.value = workShift.id;
    hasWorkHours.value =
        workShift.starts_at !== null && workShift.ends_at !== null;
    workShiftForm.clearErrors();
    workShiftForm.defaults({
        key: workShift.key,
        name: workShift.name,
        starts_at: workShift.starts_at,
        ends_at: workShift.ends_at,
        is_active: workShift.is_active,
    });
    workShiftForm.reset();
    isWorkShiftFormOpen.value = true;
}

function submitWorkShift(): void {
    if (props.mode === 'demo') {
        saveDemoWorkShift();

        return;
    }

    const action =
        editingWorkShiftId.value === null
            ? storeWorkShift()
            : updateWorkShift(editingWorkShiftId.value);

    workShiftForm.submit(action, {
        preserveScroll: true,
        onSuccess: () => {
            isWorkShiftFormOpen.value = false;
            workShiftForm.reset();
        },
    });
}

function toggleWorkHours(): void {
    workShiftForm.clearErrors('starts_at', 'ends_at');

    if (hasWorkHours.value) {
        workShiftForm.starts_at ??= '08:00';
        workShiftForm.ends_at ??= '16:00';

        return;
    }

    workShiftForm.starts_at = null;
    workShiftForm.ends_at = null;
}

function saveDemoWorkShift(): void {
    workShiftForm.clearErrors();
    const normalizedKey = workShiftForm.key.trim().toLowerCase();
    const normalizedName = workShiftForm.name.trim();

    if (!normalizedKey) {
        workShiftForm.setError('key', 'Kode shift wajib diisi.');
    } else if (!/^[a-z0-9_-]+$/.test(normalizedKey)) {
        workShiftForm.setError(
            'key',
            'Kode hanya boleh berisi huruf kecil, angka, strip, dan garis bawah.',
        );
    }

    if (!normalizedName) {
        workShiftForm.setError('name', 'Nama shift wajib diisi.');
    }

    if (
        hasWorkHours.value &&
        workShiftForm.starts_at === workShiftForm.ends_at
    ) {
        workShiftForm.setError(
            'ends_at',
            'Jam selesai harus berbeda dari jam mulai.',
        );
    }

    const hasDuplicateKey = workShiftList.value.some(
        (workShift) =>
            workShift.id !== editingWorkShiftId.value &&
            workShift.key.toLowerCase() === normalizedKey,
    );
    const hasDuplicateName = workShiftList.value.some(
        (workShift) =>
            workShift.id !== editingWorkShiftId.value &&
            workShift.name.toLowerCase() === normalizedName.toLowerCase(),
    );

    if (hasDuplicateKey) {
        workShiftForm.setError('key', 'Kode shift sudah dipakai.');
    }

    if (hasDuplicateName) {
        workShiftForm.setError('name', 'Nama shift sudah dipakai.');
    }

    if (workShiftForm.hasErrors) {
        return;
    }

    const values = {
        key: normalizedKey,
        name: normalizedName,
        starts_at: workShiftForm.starts_at,
        ends_at: workShiftForm.ends_at,
        is_active: workShiftForm.is_active,
    };

    if (editingWorkShiftId.value === null) {
        workShiftList.value.push({
            id:
                Math.max(
                    0,
                    ...workShiftList.value.map((workShift) => workShift.id),
                ) + 1,
            ...values,
            admin_count: 0,
        });
    } else {
        const workShift = workShiftList.value.find(
            (item) => item.id === editingWorkShiftId.value,
        );

        if (workShift) {
            Object.assign(workShift, values);
        }
    }

    workShiftList.value.sort((first, second) =>
        (first.starts_at ?? '').localeCompare(second.starts_at ?? ''),
    );
    isWorkShiftFormOpen.value = false;
    workShiftForm.reset();
}

function openDeleteWorkShift(workShift: WorkShift): void {
    if (!props.capabilities.delete || workShift.admin_count > 0) {
        return;
    }

    deleteForm.clearErrors();
    deletingWorkShift.value = workShift;
}

function confirmDeleteWorkShift(): void {
    if (deletingWorkShift.value === null) {
        return;
    }

    if (props.mode === 'demo') {
        workShiftList.value = workShiftList.value.filter(
            (workShift) => workShift.id !== deletingWorkShift.value?.id,
        );
        deletingWorkShift.value = null;

        return;
    }

    deleteForm.submit(destroyWorkShift(deletingWorkShift.value.id), {
        preserveScroll: true,
        onSuccess: () => {
            deletingWorkShift.value = null;
        },
    });
}

function durationLabel(workShift: WorkShift): string {
    if (workShift.starts_at === null || workShift.ends_at === null) {
        return 'Tanpa jadwal';
    }

    const [startHour, startMinute] = workShift.starts_at.split(':').map(Number);
    const [endHour, endMinute] = workShift.ends_at.split(':').map(Number);
    const start = startHour * 60 + startMinute;
    let end = endHour * 60 + endMinute;

    if (end < start) {
        end += 24 * 60;
    }

    const duration = end - start;
    const hours = Math.floor(duration / 60);
    const minutes = duration % 60;

    return minutes === 0 ? `${hours} jam` : `${hours} jam ${minutes} menit`;
}
</script>

<template>
    <Head :title="`${brand.name} — Shift`" />

    <div class="space-y-4">
        <section class="grid grid-cols-1 gap-4 sm:grid-cols-3">
            <StatCard
                label="Total shift"
                :value="String(workShiftList.length)"
                caption="Jadwal kerja terdaftar"
                :icon="Clock3"
            />
            <StatCard
                label="Shift aktif"
                :value="String(activeCount)"
                caption="Dapat dipilih untuk admin"
                :icon="Clock3"
                tone="emerald"
            />
            <StatCard
                label="Admin terjadwal"
                :value="String(assignedAdminCount)"
                caption="Penempatan pada seluruh shift"
                :icon="UserRoundCheck"
                tone="violet"
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
                        Daftar shift
                    </h3>
                    <p class="mt-0.5 text-xs text-slate-500">
                        {{ filteredWorkShifts.length }} shift ditampilkan
                    </p>
                </div>
                <div class="flex flex-wrap items-center gap-2">
                    <DataToolbar
                        v-model:search="search"
                        placeholder="Cari nama / kode shift"
                        :filters="filterOptions"
                        :active-filter="statusFilter"
                        @filter="statusFilter = $event"
                    />
                    <button
                        v-if="capabilities.create"
                        type="button"
                        class="flex items-center gap-2 rounded-xl bg-gradient-to-r from-cyan-500 to-sky-600 px-3 py-2 text-sm font-medium text-white shadow-lg shadow-cyan-500/25 transition hover:from-cyan-600 hover:to-sky-700"
                        @click="openCreateWorkShift"
                    >
                        <Plus class="h-4 w-4" /> Tambah Shift
                    </button>
                </div>
            </div>

            <div v-if="filteredWorkShifts.length" class="overflow-x-auto">
                <table class="w-full min-w-[760px] text-sm">
                    <thead>
                        <tr
                            class="border-b border-slate-100 text-left text-[11px] font-medium tracking-wider text-slate-400 uppercase"
                        >
                            <th class="px-5 py-3">Shift</th>
                            <th class="px-5 py-3">Jam kerja</th>
                            <th class="px-5 py-3">Durasi</th>
                            <th class="px-5 py-3">Status</th>
                            <th class="px-5 py-3">Admin</th>
                            <th class="px-5 py-3"></th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-50">
                        <tr
                            v-for="workShift in filteredWorkShifts"
                            :key="workShift.id"
                            class="transition hover:bg-slate-50/70"
                        >
                            <td class="px-5 py-3.5">
                                <div class="flex items-center gap-3">
                                    <span
                                        class="flex h-9 w-9 shrink-0 items-center justify-center rounded-xl bg-gradient-to-br from-cyan-50 to-sky-100 text-cyan-700"
                                    >
                                        <Clock3 class="h-4 w-4" />
                                    </span>
                                    <div>
                                        <p class="font-medium text-slate-900">
                                            {{ workShift.name }}
                                        </p>
                                        <p
                                            class="font-mono text-[11px] text-slate-500"
                                        >
                                            {{ workShift.key }}
                                        </p>
                                    </div>
                                </div>
                            </td>
                            <td
                                class="px-5 py-3.5 font-medium text-slate-900 tabular-nums"
                            >
                                <template
                                    v-if="
                                        workShift.starts_at !== null &&
                                        workShift.ends_at !== null
                                    "
                                >
                                    {{ workShift.starts_at }}–{{
                                        workShift.ends_at
                                    }}
                                </template>
                                <span v-else class="font-normal text-slate-400">
                                    Tanpa jadwal
                                </span>
                            </td>
                            <td class="px-5 py-3.5 text-xs text-slate-600">
                                {{ durationLabel(workShift) }}
                            </td>
                            <td class="px-5 py-3.5">
                                <StatusPill
                                    :status="
                                        workShift.is_active
                                            ? 'aktif'
                                            : 'nonaktif'
                                    "
                                />
                            </td>
                            <td class="px-5 py-3.5 text-xs text-slate-600">
                                {{ workShift.admin_count }} admin
                            </td>
                            <td class="px-5 py-3.5">
                                <div
                                    class="flex items-center justify-end gap-1"
                                >
                                    <button
                                        v-if="capabilities.update"
                                        type="button"
                                        class="rounded-lg p-2 text-cyan-700 transition hover:bg-cyan-50"
                                        aria-label="Edit shift"
                                        @click="openEditWorkShift(workShift)"
                                    >
                                        <Pencil class="h-4 w-4" />
                                    </button>
                                    <button
                                        v-if="capabilities.delete"
                                        type="button"
                                        class="rounded-lg p-2 text-rose-600 transition hover:bg-rose-50 disabled:cursor-not-allowed disabled:text-slate-300 disabled:hover:bg-transparent"
                                        :disabled="workShift.admin_count > 0"
                                        :title="
                                            workShift.admin_count > 0
                                                ? 'Masih dipakai admin, nonaktifkan saja'
                                                : 'Hapus shift'
                                        "
                                        aria-label="Hapus shift"
                                        @click="openDeleteWorkShift(workShift)"
                                    >
                                        <Trash2 class="h-4 w-4" />
                                    </button>
                                </div>
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>
            <EmptyState
                v-else
                :icon="Clock3"
                title="Shift tidak ditemukan"
                caption="Ubah kata kunci atau filter status."
            />
        </section>
    </div>

    <ModalDialog
        :open="isWorkShiftFormOpen"
        :title="editingWorkShiftId ? 'Edit shift' : 'Tambah shift'"
        caption="Atur kode, nama, dan jam kerja admin bila diperlukan."
        size="lg"
        @close="isWorkShiftFormOpen = false"
    >
        <form
            id="master-work-shift-form"
            class="space-y-4"
            @submit.prevent="submitWorkShift"
        >
            <div class="grid grid-cols-1 gap-3 sm:grid-cols-2">
                <div>
                    <label
                        for="work-shift-name"
                        class="text-xs font-medium text-slate-600"
                        >Nama shift</label
                    >
                    <input
                        id="work-shift-name"
                        v-model="workShiftForm.name"
                        type="text"
                        placeholder="Contoh: Shift Pagi"
                        class="mt-1.5 w-full rounded-xl border border-slate-200 px-3 py-2.5 text-sm focus:border-cyan-400 focus:outline-none"
                    />
                    <InputError
                        class="mt-1"
                        :message="workShiftForm.errors.name"
                    />
                </div>
                <div>
                    <label
                        for="work-shift-key"
                        class="text-xs font-medium text-slate-600"
                        >Kode shift</label
                    >
                    <input
                        id="work-shift-key"
                        v-model="workShiftForm.key"
                        type="text"
                        placeholder="Contoh: morning"
                        class="mt-1.5 w-full rounded-xl border border-slate-200 px-3 py-2.5 font-mono text-sm focus:border-cyan-400 focus:outline-none"
                    />
                    <InputError
                        class="mt-1"
                        :message="workShiftForm.errors.key"
                    />
                </div>
            </div>

            <label
                class="flex items-center gap-3 rounded-xl border border-slate-200 p-3 text-sm text-slate-700"
            >
                <input
                    v-model="hasWorkHours"
                    type="checkbox"
                    class="h-4 w-4 rounded border-slate-300 text-cyan-600 focus:ring-cyan-500"
                    @change="toggleWorkHours"
                />
                <span>
                    <span class="block font-medium">Gunakan jam shift</span>
                    <span class="block text-xs text-slate-500">
                        Nonaktifkan bila shift hanya membutuhkan nama dan kode.
                    </span>
                </span>
            </label>

            <div
                v-if="hasWorkHours"
                class="grid grid-cols-1 gap-3 sm:grid-cols-2"
            >
                <div>
                    <label
                        for="work-shift-start"
                        class="text-xs font-medium text-slate-600"
                        >Jam mulai</label
                    >
                    <input
                        id="work-shift-start"
                        v-model="workShiftForm.starts_at"
                        type="time"
                        class="mt-1.5 w-full rounded-xl border border-slate-200 px-3 py-2.5 text-sm tabular-nums focus:border-cyan-400 focus:outline-none"
                    />
                    <InputError
                        class="mt-1"
                        :message="workShiftForm.errors.starts_at"
                    />
                </div>
                <div>
                    <label
                        for="work-shift-end"
                        class="text-xs font-medium text-slate-600"
                        >Jam selesai</label
                    >
                    <input
                        id="work-shift-end"
                        v-model="workShiftForm.ends_at"
                        type="time"
                        class="mt-1.5 w-full rounded-xl border border-slate-200 px-3 py-2.5 text-sm tabular-nums focus:border-cyan-400 focus:outline-none"
                    />
                    <InputError
                        class="mt-1"
                        :message="workShiftForm.errors.ends_at"
                    />
                </div>
            </div>

            <label
                class="flex items-center gap-3 rounded-xl border border-slate-200 p-3 text-sm text-slate-700"
            >
                <input
                    v-model="workShiftForm.is_active"
                    type="checkbox"
                    class="h-4 w-4 rounded border-slate-300 text-cyan-600 focus:ring-cyan-500"
                />
                Shift aktif dan dapat dipilih untuk admin
            </label>
        </form>
        <template #footer>
            <button
                type="button"
                class="flex-1 rounded-xl border border-slate-200 py-2.5 text-sm font-medium text-slate-600 hover:bg-slate-50"
                @click="isWorkShiftFormOpen = false"
            >
                Batal
            </button>
            <button
                form="master-work-shift-form"
                type="submit"
                class="flex-1 rounded-xl bg-gradient-to-r from-cyan-500 to-sky-600 py-2.5 text-sm font-semibold text-white disabled:cursor-not-allowed disabled:opacity-60"
                :disabled="workShiftForm.processing"
            >
                {{ workShiftForm.processing ? 'Menyimpan...' : 'Simpan shift' }}
            </button>
        </template>
    </ModalDialog>

    <ModalDialog
        :open="deletingWorkShift !== null"
        title="Hapus shift"
        caption="Shift yang dihapus tidak dapat dikembalikan."
        size="sm"
        @close="deletingWorkShift = null"
    >
        <p v-if="deletingWorkShift" class="text-sm text-slate-600">
            Yakin ingin menghapus
            <span class="font-semibold text-slate-900">{{
                deletingWorkShift.name
            }}</span>
            dari master shift?
        </p>
        <InputError class="mt-2" :message="deleteError" />
        <template #footer>
            <button
                type="button"
                class="flex-1 rounded-xl border border-slate-200 py-2.5 text-sm font-medium text-slate-600 hover:bg-slate-50"
                @click="deletingWorkShift = null"
            >
                Batal
            </button>
            <button
                type="button"
                class="flex-1 rounded-xl bg-rose-600 py-2.5 text-sm font-semibold text-white transition hover:bg-rose-700 disabled:cursor-not-allowed disabled:opacity-60"
                :disabled="deleteForm.processing"
                @click="confirmDeleteWorkShift"
            >
                {{ deleteForm.processing ? 'Menghapus...' : 'Hapus shift' }}
            </button>
        </template>
    </ModalDialog>
</template>
