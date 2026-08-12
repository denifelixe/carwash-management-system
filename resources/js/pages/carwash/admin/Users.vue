<script setup lang="ts">
import { Head } from '@inertiajs/vue3';
import { Check, Pencil, Plus, ShieldCheck, Users, X } from '@lucide/vue';
import { computed, ref } from 'vue';
import DataToolbar from '@/components/carwash/DataToolbar.vue';
import EmptyState from '@/components/carwash/EmptyState.vue';
import ModalDialog from '@/components/carwash/ModalDialog.vue';
import StatCard from '@/components/carwash/StatCard.vue';
import StatusPill from '@/components/carwash/StatusPill.vue';
import type {
    CarwashBrand,
    CarwashModule,
    CarwashRole,
    CarwashRoleMatrix,
    CarwashStaff,
} from '@/types/carwash';

const props = defineProps<{
    brand: CarwashBrand;
    staff: CarwashStaff[];
    roles: CarwashRole[];
    matrix: CarwashRoleMatrix;
    allModules: CarwashModule[];
}>();

const staffList = ref<CarwashStaff[]>(
    props.staff.map((person) => ({ ...person })),
);

const search = ref<string>('');
const roleFilter = ref<string>('Semua');
const editingId = ref<number | null>(null);
const isFormOpen = ref<boolean>(false);

const draft = ref({
    name: '',
    email: '',
    phone: '',
    role: 'cashier',
    status: 'aktif',
});

const filterOptions = computed<string[]>(() => [
    'Semua',
    ...props.roles.map((role) => role.name),
]);

const filteredStaff = computed<CarwashStaff[]>(() => {
    const query = search.value.trim().toLowerCase();

    return staffList.value.filter((person) => {
        const matchesRole =
            roleFilter.value === 'Semua' ||
            roleNameOf(person.role) === roleFilter.value;
        const matchesQuery =
            query === '' ||
            person.name.toLowerCase().includes(query) ||
            person.email.toLowerCase().includes(query);

        return matchesRole && matchesQuery;
    });
});

const activeCount = computed<number>(
    () => staffList.value.filter((person) => person.status === 'aktif').length,
);

const canSave = computed<boolean>(
    () => draft.value.name.trim() !== '' && draft.value.email.includes('@'),
);

function roleNameOf(key: string): string {
    return props.roles.find((role) => role.key === key)?.name ?? key;
}

function roleAccentOf(key: string): string {
    return props.roles.find((role) => role.key === key)?.accent ?? '#64748b';
}

function moduleCountOf(key: string): number {
    return props.matrix[key]?.length ?? 0;
}

function initialsOf(name: string): string {
    return name
        .split(' ')
        .map((part) => part[0])
        .join('')
        .slice(0, 2)
        .toUpperCase();
}

function openCreate(): void {
    editingId.value = null;
    draft.value = {
        name: '',
        email: '',
        phone: '',
        role: 'cashier',
        status: 'aktif',
    };
    isFormOpen.value = true;
}

function openEdit(person: CarwashStaff): void {
    editingId.value = person.id;
    draft.value = {
        name: person.name,
        email: person.email,
        phone: person.phone,
        role: person.role,
        status: person.status,
    };
    isFormOpen.value = true;
}

function saveStaff(): void {
    if (!canSave.value) {
        return;
    }

    if (editingId.value !== null) {
        const existing = staffList.value.find(
            (person) => person.id === editingId.value,
        );

        if (existing) {
            Object.assign(existing, draft.value, {
                initials: initialsOf(draft.value.name),
            });
        }
    } else {
        staffList.value = [
            {
                id: 1000 + staffList.value.length,
                ...draft.value,
                lastActive: 'Belum pernah login',
                initials: initialsOf(draft.value.name),
            },
            ...staffList.value,
        ];
    }

    isFormOpen.value = false;
}

function toggleStatus(person: CarwashStaff): void {
    person.status = person.status === 'aktif' ? 'nonaktif' : 'aktif';
}
</script>

<template>
    <Head :title="`${brand.name} — User & Role`" />

    <div class="space-y-4">
        <section class="grid grid-cols-1 gap-4 sm:grid-cols-2 xl:grid-cols-4">
            <StatCard
                label="Total pegawai"
                :value="String(staffList.length)"
                :caption="`${activeCount} akun aktif`"
                :icon="Users"
            />
            <StatCard
                label="Role tersedia"
                :value="String(roles.length)"
                caption="Owner, Manager, Kasir, CS, Finance"
                :icon="ShieldCheck"
                tone="emerald"
            />
            <StatCard
                label="Modul sistem"
                :value="String(allModules.length)"
                caption="dikontrol lewat matriks akses"
                :icon="Check"
                tone="amber"
            />
            <StatCard
                label="Akun nonaktif"
                :value="String(staffList.length - activeCount)"
                caption="tidak bisa login"
                :icon="X"
                tone="rose"
            />
        </section>

        <!-- Role cards -->
        <section class="grid grid-cols-1 gap-4 sm:grid-cols-2 xl:grid-cols-5">
            <article
                v-for="role in roles"
                :key="role.key"
                class="rounded-2xl border border-slate-200/80 bg-white p-4 shadow-sm"
            >
                <div class="flex items-center gap-2">
                    <span
                        class="flex h-9 w-9 items-center justify-center rounded-xl text-lg"
                        :style="{
                            backgroundColor: `${role.accent}1f`,
                            boxShadow: `inset 0 0 0 1px ${role.accent}44`,
                        }"
                    >
                        {{ role.icon }}
                    </span>
                    <div class="min-w-0 flex-1">
                        <p
                            class="truncate text-sm font-semibold text-slate-900"
                        >
                            {{ role.name }}
                        </p>
                        <p class="text-[11px] text-slate-500">
                            {{ moduleCountOf(role.key) }} modul
                        </p>
                    </div>
                </div>
                <p class="mt-2 text-[11px] leading-relaxed text-slate-500">
                    {{ role.description }}
                </p>
                <p class="mt-2 text-[11px] font-medium text-slate-600">
                    {{
                        staffList.filter((person) => person.role === role.key)
                            .length
                    }}
                    pegawai
                </p>
            </article>
        </section>

        <!-- Staff table -->
        <section
            class="rounded-2xl border border-slate-200/80 bg-white shadow-sm"
        >
            <div
                class="flex flex-wrap items-center justify-between gap-3 border-b border-slate-100 p-5"
            >
                <div>
                    <h3 class="text-sm font-semibold text-slate-900">
                        Daftar pegawai
                    </h3>
                    <p class="mt-0.5 text-xs text-slate-500">
                        {{ filteredStaff.length }} akun ditampilkan
                    </p>
                </div>
                <div class="flex flex-wrap items-center gap-2">
                    <DataToolbar
                        v-model:search="search"
                        placeholder="Cari nama / email"
                        :filters="filterOptions"
                        :active-filter="roleFilter"
                        @filter="roleFilter = $event"
                    />
                    <button
                        type="button"
                        class="flex items-center gap-2 rounded-xl bg-gradient-to-r from-cyan-500 to-sky-600 px-3 py-2 text-sm font-medium text-white shadow-lg shadow-cyan-500/25 transition hover:from-cyan-600 hover:to-sky-700"
                        @click="openCreate"
                    >
                        <Plus class="h-4 w-4" />
                        Tambah User
                    </button>
                </div>
            </div>

            <div v-if="filteredStaff.length > 0" class="overflow-x-auto">
                <table class="w-full min-w-[820px] text-sm">
                    <thead>
                        <tr
                            class="border-b border-slate-100 text-left text-[11px] font-medium tracking-wider text-slate-400 uppercase"
                        >
                            <th class="px-5 py-3">Pegawai</th>
                            <th class="px-5 py-3">Role</th>
                            <th class="px-5 py-3">Status</th>
                            <th class="px-5 py-3">Terakhir aktif</th>
                            <th class="px-5 py-3"></th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-50">
                        <tr
                            v-for="person in filteredStaff"
                            :key="person.id"
                            class="transition hover:bg-slate-50/70"
                        >
                            <td class="px-5 py-3.5">
                                <div class="flex items-center gap-3">
                                    <div
                                        class="flex h-9 w-9 items-center justify-center rounded-full bg-gradient-to-br from-slate-100 to-slate-200 text-xs font-semibold text-slate-600"
                                    >
                                        {{ person.initials }}
                                    </div>
                                    <div>
                                        <p class="font-medium text-slate-900">
                                            {{ person.name }}
                                        </p>
                                        <p class="text-[11px] text-slate-500">
                                            {{ person.email }}
                                        </p>
                                    </div>
                                </div>
                            </td>
                            <td class="px-5 py-3.5">
                                <span
                                    class="rounded-full px-2 py-1 text-[11px] font-medium"
                                    :style="{
                                        backgroundColor: `${roleAccentOf(person.role)}1a`,
                                        color: roleAccentOf(person.role),
                                    }"
                                >
                                    {{ roleNameOf(person.role) }}
                                </span>
                                <p class="mt-1 text-[11px] text-slate-400">
                                    {{ moduleCountOf(person.role) }} modul
                                </p>
                            </td>
                            <td class="px-5 py-3.5">
                                <StatusPill :status="person.status" />
                            </td>
                            <td class="px-5 py-3.5 text-[11px] text-slate-500">
                                {{ person.lastActive }}
                            </td>
                            <td class="px-5 py-3.5 text-right">
                                <div class="flex justify-end gap-1">
                                    <button
                                        type="button"
                                        class="rounded-lg px-2.5 py-1.5 text-xs font-medium text-cyan-700 transition hover:bg-cyan-50"
                                        @click="openEdit(person)"
                                    >
                                        <Pencil class="h-3.5 w-3.5" />
                                    </button>
                                    <button
                                        type="button"
                                        class="rounded-lg px-2.5 py-1.5 text-xs font-medium text-slate-500 transition hover:bg-slate-100"
                                        @click="toggleStatus(person)"
                                    >
                                        {{
                                            person.status === 'aktif'
                                                ? 'Nonaktifkan'
                                                : 'Aktifkan'
                                        }}
                                    </button>
                                </div>
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>

            <EmptyState
                v-else
                :icon="Users"
                title="Pegawai tidak ditemukan"
                caption="Ubah kata kunci atau filter role."
            />
        </section>

        <!-- Permission matrix -->
        <section
            class="rounded-2xl border border-slate-200/80 bg-white shadow-sm"
        >
            <div class="border-b border-slate-100 p-5">
                <h3 class="text-sm font-semibold text-slate-900">
                    Matriks hak akses
                </h3>
                <p class="mt-0.5 text-xs text-slate-500">
                    Modul yang bisa dibuka setiap role. Akses di luar matriks
                    ditolak sistem.
                </p>
            </div>
            <div class="overflow-x-auto">
                <table class="w-full min-w-[720px] text-sm">
                    <thead>
                        <tr class="border-b border-slate-100 text-left">
                            <th
                                class="px-5 py-3 text-[11px] font-medium tracking-wider text-slate-400 uppercase"
                            >
                                Modul
                            </th>
                            <th
                                v-for="role in roles"
                                :key="role.key"
                                class="px-5 py-3 text-center text-[11px] font-medium tracking-wider text-slate-400 uppercase"
                            >
                                {{ role.name }}
                            </th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-50">
                        <tr
                            v-for="module in allModules"
                            :key="module.key"
                            class="transition hover:bg-slate-50/70"
                        >
                            <td class="px-5 py-3">
                                <p class="font-medium text-slate-800">
                                    {{ module.label }}
                                </p>
                                <p class="text-[11px] text-slate-500">
                                    {{ module.caption }}
                                </p>
                            </td>
                            <td
                                v-for="role in roles"
                                :key="role.key"
                                class="px-5 py-3 text-center"
                            >
                                <span
                                    v-if="matrix[role.key].includes(module.key)"
                                    class="mx-auto flex h-6 w-6 items-center justify-center rounded-full bg-emerald-50 text-emerald-600 ring-1 ring-emerald-200"
                                    title="Bisa diakses"
                                >
                                    <Check
                                        class="h-3.5 w-3.5"
                                        :stroke-width="3"
                                    />
                                </span>
                                <span
                                    v-else
                                    class="mx-auto flex h-6 w-6 items-center justify-center rounded-full bg-rose-50 text-rose-500 ring-1 ring-rose-200"
                                    title="Tidak bisa diakses"
                                >
                                    <X class="h-3.5 w-3.5" :stroke-width="3" />
                                </span>
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </section>
    </div>

    <!-- Create / edit user -->
    <ModalDialog
        :open="isFormOpen"
        :title="editingId !== null ? 'Edit pegawai' : 'Tambah pegawai'"
        caption="Role menentukan modul yang bisa diakses"
        @close="isFormOpen = false"
    >
        <div class="space-y-4">
            <div>
                <label
                    class="text-xs font-medium text-slate-600"
                    for="user-name"
                >
                    Nama lengkap
                </label>
                <input
                    id="user-name"
                    v-model="draft.name"
                    type="text"
                    placeholder="Nama pegawai"
                    class="mt-1.5 w-full rounded-xl border border-slate-200 px-3 py-2.5 text-sm focus:border-cyan-400 focus:outline-none"
                />
            </div>

            <div class="grid grid-cols-2 gap-3">
                <div>
                    <label
                        class="text-xs font-medium text-slate-600"
                        for="user-email"
                    >
                        Email
                    </label>
                    <input
                        id="user-email"
                        v-model="draft.email"
                        type="email"
                        placeholder="nama@zenwash.id"
                        class="mt-1.5 w-full rounded-xl border border-slate-200 px-3 py-2.5 text-sm focus:border-cyan-400 focus:outline-none"
                    />
                </div>
                <div>
                    <label
                        class="text-xs font-medium text-slate-600"
                        for="user-phone"
                    >
                        Nomor HP
                    </label>
                    <input
                        id="user-phone"
                        v-model="draft.phone"
                        type="tel"
                        placeholder="0812-xxxx-xxxx"
                        class="mt-1.5 w-full rounded-xl border border-slate-200 px-3 py-2.5 text-sm focus:border-cyan-400 focus:outline-none"
                    />
                </div>
            </div>

            <div>
                <p class="text-xs font-medium text-slate-600">Role</p>
                <div class="mt-1.5 space-y-2">
                    <button
                        v-for="role in roles"
                        :key="role.key"
                        type="button"
                        class="flex w-full items-center gap-3 rounded-xl border p-3 text-left transition"
                        :class="
                            draft.role === role.key
                                ? 'border-cyan-400 bg-cyan-50/60'
                                : 'border-slate-200 hover:border-slate-300'
                        "
                        @click="draft.role = role.key"
                    >
                        <span class="text-lg">{{ role.icon }}</span>
                        <span class="min-w-0 flex-1 leading-tight">
                            <span
                                class="block text-sm font-medium text-slate-800"
                            >
                                {{ role.name }}
                            </span>
                            <span class="block text-[11px] text-slate-500">
                                {{ moduleCountOf(role.key) }} modul •
                                {{ role.description }}
                            </span>
                        </span>
                        <Check
                            v-if="draft.role === role.key"
                            class="h-4 w-4 shrink-0 text-cyan-600"
                        />
                    </button>
                </div>
            </div>

            <div>
                <div>
                    <label
                        class="text-xs font-medium text-slate-600"
                        for="user-status"
                    >
                        Status
                    </label>
                    <select
                        id="user-status"
                        v-model="draft.status"
                        class="mt-1.5 w-full rounded-xl border border-slate-200 px-3 py-2.5 text-sm focus:border-cyan-400 focus:outline-none"
                    >
                        <option value="aktif">Aktif</option>
                        <option value="nonaktif">Nonaktif</option>
                    </select>
                </div>
            </div>

            <div class="rounded-xl bg-slate-50 p-3">
                <p class="text-[11px] font-medium text-slate-600">
                    Modul yang bisa diakses
                </p>
                <div class="mt-1.5 flex flex-wrap gap-1">
                    <span
                        v-for="moduleKey in matrix[draft.role]"
                        :key="moduleKey"
                        class="rounded-md bg-white px-1.5 py-0.5 text-[10px] text-slate-600 ring-1 ring-slate-200"
                    >
                        {{
                            allModules.find((item) => item.key === moduleKey)
                                ?.label
                        }}
                    </span>
                </div>
            </div>
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
                :disabled="!canSave"
                @click="saveStaff"
            >
                {{ editingId !== null ? 'Simpan perubahan' : 'Tambah pegawai' }}
            </button>
        </template>
    </ModalDialog>
</template>
