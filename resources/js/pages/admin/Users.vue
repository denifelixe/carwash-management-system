<script setup lang="ts">
import { Head, router, useForm, usePage } from '@inertiajs/vue3';
import { Check, Pencil, Plus, ShieldCheck, Users, X } from '@lucide/vue';
import { computed, ref, watch } from 'vue';
import {
    store as storeRole,
    update as updateRole,
} from '@/actions/App/Http/Controllers/Admin/AdminRoleController';
import {
    store as storeUser,
    update as updateUser,
    updateShift as updateUserShift,
} from '@/actions/App/Http/Controllers/Admin/AdminUserController';
import DataToolbar from '@/components/demo/DataToolbar.vue';
import EmptyState from '@/components/demo/EmptyState.vue';
import ModalDialog from '@/components/demo/ModalDialog.vue';
import StatCard from '@/components/demo/StatCard.vue';
import StatusPill from '@/components/demo/StatusPill.vue';
import InputError from '@/components/InputError.vue';
import type { CarwashAdminShellProps, CarwashBrand } from '@/types/demo';

type Staff = {
    id: number;
    name: string;
    email: string;
    phone: string;
    role_id: number | null;
    role_key: string;
    role_name: string;
    work_shift_id: number | null;
    shift_name: string;
    is_owner: boolean;
    is_active: boolean;
    last_active: string;
    initials: string;
};

type Permission = {
    module_id: number;
    can_create: boolean;
    can_read: boolean;
    can_update: boolean;
    can_delete: boolean;
};

type Role = {
    id: number;
    key: string;
    name: string;
    description: string;
    is_active: boolean;
    staff_count: number;
    permissions: Permission[];
};

type Module = {
    id: number;
    key: string;
    label: string;
    caption: string;
};

type Shift = {
    id: number;
    name: string;
};

const props = defineProps<{
    mode: 'demo' | 'live';
    brand: CarwashBrand;
    staff: Staff[];
    roles: Role[];
    shifts: Shift[];
    allModules: Module[];
    ownerSummary: {
        key: string;
        name: string;
        description: string;
        staff_count: number;
        module_count: number;
    };
    capabilities: {
        create: boolean;
        update: boolean;
    };
}>();

const page = usePage<CarwashAdminShellProps>();
const staffList = ref(props.staff.map((person) => ({ ...person })));
const roleList = ref(
    props.roles.map((role) => ({
        ...role,
        permissions: role.permissions.map((permission) => ({ ...permission })),
    })),
);
const search = ref('');
const roleFilter = ref('Semua');
const editingUserId = ref<number | null>(null);
const editingRoleId = ref<number | null>(null);
const isUserFormOpen = ref(false);
const isRoleFormOpen = ref(false);
const savingShiftIds = ref<number[]>([]);

watch(
    () => props.staff,
    (staff) => {
        if (props.mode === 'live') {
            staffList.value = staff.map((person) => ({ ...person }));
        }
    },
);

watch(
    () => props.roles,
    (roles) => {
        if (props.mode === 'live') {
            roleList.value = roles.map((role) => ({
                ...role,
                permissions: role.permissions.map((permission) => ({
                    ...permission,
                })),
            }));
        }
    },
);

const userForm = useForm({
    name: '',
    email: '',
    phone: '',
    role_id: null as number | null,
    work_shift_id: null as number | null,
    password: '',
    password_confirmation: '',
    is_active: true,
});

const roleForm = useForm({
    name: '',
    description: '',
    is_active: true,
    permissions: [] as Permission[],
});

const filterOptions = computed(() => [
    'Semua',
    'Owner',
    ...roleList.value.map((role) => role.name),
]);

const filteredStaff = computed(() => {
    const query = search.value.trim().toLowerCase();

    return staffList.value.filter((person) => {
        const matchesRole =
            roleFilter.value === 'Semua' ||
            person.role_name === roleFilter.value;
        const matchesQuery =
            query === '' ||
            person.name.toLowerCase().includes(query) ||
            person.email.toLowerCase().includes(query) ||
            person.phone.toLowerCase().includes(query);

        return matchesRole && matchesQuery;
    });
});

const activeCount = computed(
    () => staffList.value.filter((person) => person.is_active).length,
);

function roleAccent(key: string): string {
    const accents: Record<string, string> = {
        owner: '#0891b2',
        manager: '#7c3aed',
        cashier: '#059669',
        cs: '#d97706',
        finance: '#dc2626',
    };

    return accents[key] ?? '#475569';
}

function readableModuleCount(role: Role): number {
    return role.permissions.filter((permission) => permission.can_read).length;
}

function staffCountForRole(role: Role): number {
    return staffList.value.filter((person) => person.role_id === role.id)
        .length;
}

function permissionFor(role: Role, moduleId: number): Permission | undefined {
    return role.permissions.find(
        (permission) => permission.module_id === moduleId,
    );
}

function openCreateUser(): void {
    editingUserId.value = null;
    userForm.reset();
    userForm.clearErrors();
    userForm.role_id =
        roleList.value.find((role) => role.is_active)?.id ?? null;
    userForm.work_shift_id = props.shifts[0]?.id ?? null;
    userForm.is_active = true;
    isUserFormOpen.value = true;
}

function openEditUser(person: Staff): void {
    if (person.is_owner || !props.capabilities.update) {
        return;
    }

    editingUserId.value = person.id;
    userForm.clearErrors();
    userForm.defaults({
        name: person.name,
        email: person.email,
        phone: person.phone,
        role_id: person.role_id,
        work_shift_id: person.work_shift_id,
        password: '',
        password_confirmation: '',
        is_active: person.is_active,
    });
    userForm.reset();
    isUserFormOpen.value = true;
}

function submitUser(): void {
    if (props.mode === 'demo') {
        saveDemoUser();

        return;
    }

    const action =
        editingUserId.value === null
            ? storeUser()
            : updateUser(editingUserId.value);

    userForm.submit(action, {
        preserveScroll: true,
        onSuccess: () => {
            isUserFormOpen.value = false;
            userForm.reset();
        },
    });
}

function changeShift(person: Staff, event: Event): void {
    const selectedValue = (event.target as HTMLSelectElement).value;
    const workShiftId = selectedValue === '' ? null : Number(selectedValue);
    const previousShiftId = person.work_shift_id;
    const previousShiftName = person.shift_name;
    const shift = props.shifts.find((item) => item.id === workShiftId);

    person.work_shift_id = workShiftId;
    person.shift_name = shift?.name ?? 'Tidak ada Shift';

    if (props.mode === 'demo') {
        if (page.props.persona.id === person.id) {
            page.props.persona.shift = person.shift_name;
        }

        return;
    }

    savingShiftIds.value.push(person.id);

    router.visit(updateUserShift(person.id), {
        data: { work_shift_id: workShiftId },
        preserveScroll: true,
        preserveState: true,
        only: ['staff', 'persona'],
        onError: () => {
            person.work_shift_id = previousShiftId;
            person.shift_name = previousShiftName;
        },
        onFinish: () => {
            savingShiftIds.value = savingShiftIds.value.filter(
                (id) => id !== person.id,
            );
        },
    });
}

function saveDemoUser(): void {
    userForm.clearErrors();

    if (!userForm.name.trim()) {
        userForm.setError('name', 'Nama wajib diisi.');
    }

    if (!userForm.email.includes('@')) {
        userForm.setError('email', 'Email tidak valid.');
    }

    if (userForm.role_id === null) {
        userForm.setError('role_id', 'Role wajib dipilih.');
    }

    if (userForm.hasErrors) {
        return;
    }

    const role = roleList.value.find((item) => item.id === userForm.role_id);
    const shift = props.shifts.find(
        (item) => item.id === userForm.work_shift_id,
    );
    const values = {
        name: userForm.name,
        email: userForm.email,
        phone: userForm.phone,
        role_id: userForm.role_id,
        role_key: role?.key ?? 'unassigned',
        role_name: role?.name ?? 'Belum ada role',
        work_shift_id: userForm.work_shift_id,
        shift_name: shift?.name ?? 'Tidak ada Shift',
        is_active: userForm.is_active,
        initials: initialsOf(userForm.name),
    };

    if (editingUserId.value === null) {
        staffList.value.unshift({
            id: Math.max(0, ...staffList.value.map((person) => person.id)) + 1,
            ...values,
            is_owner: false,
            last_active: 'Belum pernah login',
        });
    } else {
        const person = staffList.value.find(
            (item) => item.id === editingUserId.value,
        );

        if (person) {
            Object.assign(person, values);

            if (page.props.persona.id === person.id) {
                Object.assign(page.props.persona, {
                    name: person.name,
                    initials: person.initials,
                    shift: person.shift_name,
                });
            }
        }
    }

    isUserFormOpen.value = false;
    userForm.reset();
}

function initialsOf(name: string): string {
    return name
        .trim()
        .split(/\s+/)
        .slice(0, 2)
        .map((part) => part.charAt(0).toUpperCase())
        .join('');
}

function blankPermissions(): Permission[] {
    return props.allModules.map((module) => ({
        module_id: module.id,
        can_create: false,
        can_read: module.key === 'dashboard',
        can_update: false,
        can_delete: false,
    }));
}

function openCreateRole(): void {
    editingRoleId.value = null;
    roleForm.clearErrors();
    roleForm.defaults({
        name: '',
        description: '',
        is_active: true,
        permissions: blankPermissions(),
    });
    roleForm.reset();
    isRoleFormOpen.value = true;
}

function openEditRole(role: Role): void {
    if (!props.capabilities.update) {
        return;
    }

    editingRoleId.value = role.id;
    roleForm.clearErrors();
    roleForm.defaults({
        name: role.name,
        description: role.description,
        is_active: role.is_active,
        permissions: role.permissions.map((permission) => ({ ...permission })),
    });
    roleForm.reset();
    isRoleFormOpen.value = true;
}

function submitRole(): void {
    if (props.mode === 'demo') {
        saveDemoRole();

        return;
    }

    const action =
        editingRoleId.value === null
            ? storeRole()
            : updateRole(editingRoleId.value);

    roleForm.submit(action, {
        preserveScroll: true,
        onSuccess: () => {
            isRoleFormOpen.value = false;
            roleForm.reset();
        },
    });
}

function saveDemoRole(): void {
    roleForm.clearErrors();

    if (!roleForm.name.trim()) {
        roleForm.setError('name', 'Nama role wajib diisi.');

        return;
    }

    if (editingRoleId.value === null) {
        const id = Math.max(0, ...roleList.value.map((role) => role.id)) + 1;
        const key = roleForm.name
            .trim()
            .toLowerCase()
            .replace(/[^a-z0-9]+/g, '_')
            .replace(/^_|_$/g, '');

        roleList.value.push({
            id,
            key: key || `role_${id}`,
            name: roleForm.name,
            description: roleForm.description,
            is_active: roleForm.is_active,
            staff_count: 0,
            permissions: roleForm.permissions.map((permission) => ({
                ...permission,
            })),
        });
    } else {
        const role = roleList.value.find(
            (item) => item.id === editingRoleId.value,
        );

        if (role) {
            Object.assign(role, {
                name: roleForm.name,
                description: roleForm.description,
                is_active: roleForm.is_active,
                permissions: roleForm.permissions.map((permission) => ({
                    ...permission,
                })),
            });

            staffList.value
                .filter((person) => person.role_id === role.id)
                .forEach((person) => {
                    person.role_name = role.name;
                    person.role_key = role.key;
                });
        }
    }

    isRoleFormOpen.value = false;
    roleForm.reset();
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
                :value="String(roleList.length + 1)"
                caption="Termasuk role Owner"
                :icon="ShieldCheck"
                tone="emerald"
            />
            <StatCard
                label="Modul sistem"
                :value="String(allModules.length)"
                caption="Dikontrol lewat matriks akses"
                :icon="Check"
                tone="amber"
            />
            <StatCard
                label="Akun nonaktif"
                :value="String(staffList.length - activeCount)"
                caption="Tidak bisa login"
                :icon="X"
                tone="rose"
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
                        Role & hak akses
                    </h3>
                    <p class="mt-0.5 text-xs text-slate-500">
                        Atur modul dan aksi yang dapat digunakan setiap role.
                    </p>
                </div>
                <button
                    v-if="capabilities.create"
                    type="button"
                    class="flex items-center gap-2 rounded-xl border border-cyan-200 bg-cyan-50 px-3 py-2 text-sm font-medium text-cyan-700 transition hover:bg-cyan-100"
                    @click="openCreateRole"
                >
                    <Plus class="h-4 w-4" /> Tambah Role
                </button>
            </div>
            <div
                class="grid grid-cols-1 gap-3 p-5 sm:grid-cols-2 xl:grid-cols-5"
            >
                <article
                    class="rounded-2xl border border-cyan-200 bg-cyan-50/50 p-4"
                >
                    <div class="flex items-start justify-between gap-2">
                        <div>
                            <p class="text-sm font-semibold text-slate-900">
                                {{ ownerSummary.name }}
                            </p>
                            <p class="text-[11px] text-cyan-700">
                                {{ ownerSummary.module_count }} modul
                            </p>
                        </div>
                        <ShieldCheck class="h-5 w-5 text-cyan-600" />
                    </div>
                    <p class="mt-2 text-[11px] leading-relaxed text-slate-500">
                        {{ ownerSummary.description }}
                    </p>
                    <p class="mt-3 text-[11px] font-medium text-slate-700">
                        {{ ownerSummary.staff_count }} pegawai
                    </p>
                </article>
                <button
                    v-for="role in roleList"
                    :key="role.id"
                    type="button"
                    class="rounded-2xl border border-slate-200 bg-white p-4 text-left transition hover:border-cyan-300 hover:shadow-sm disabled:cursor-default"
                    :disabled="!capabilities.update"
                    @click="openEditRole(role)"
                >
                    <div class="flex items-start justify-between gap-2">
                        <div class="min-w-0">
                            <p
                                class="truncate text-sm font-semibold text-slate-900"
                            >
                                {{ role.name }}
                            </p>
                            <p
                                class="text-[11px]"
                                :style="{ color: roleAccent(role.key) }"
                            >
                                {{ readableModuleCount(role) }} modul
                            </p>
                        </div>
                        <StatusPill
                            :status="role.is_active ? 'aktif' : 'nonaktif'"
                        />
                    </div>
                    <p
                        class="mt-2 min-h-8 text-[11px] leading-relaxed text-slate-500"
                    >
                        {{ role.description }}
                    </p>
                    <p class="mt-3 text-[11px] font-medium text-slate-700">
                        {{ staffCountForRole(role) }} pegawai
                    </p>
                </button>
            </div>
        </section>

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
                        placeholder="Cari nama / email / HP"
                        :filters="filterOptions"
                        :active-filter="roleFilter"
                        @filter="roleFilter = $event"
                    />
                    <button
                        v-if="capabilities.create"
                        type="button"
                        class="flex items-center gap-2 rounded-xl bg-gradient-to-r from-cyan-500 to-sky-600 px-3 py-2 text-sm font-medium text-white shadow-lg shadow-cyan-500/25 transition hover:from-cyan-600 hover:to-sky-700"
                        @click="openCreateUser"
                    >
                        <Plus class="h-4 w-4" /> Tambah User
                    </button>
                </div>
            </div>

            <div v-if="filteredStaff.length" class="overflow-x-auto">
                <table class="w-full min-w-[860px] text-sm">
                    <thead>
                        <tr
                            class="border-b border-slate-100 text-left text-[11px] font-medium tracking-wider text-slate-400 uppercase"
                        >
                            <th class="px-5 py-3">Pegawai</th>
                            <th class="px-5 py-3">Role</th>
                            <th class="px-5 py-3">Shift</th>
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
                                        <p
                                            v-if="person.phone"
                                            class="text-[10px] text-slate-400"
                                        >
                                            {{ person.phone }}
                                        </p>
                                    </div>
                                </div>
                            </td>
                            <td class="px-5 py-3.5">
                                <span
                                    class="rounded-full px-2 py-1 text-[11px] font-medium"
                                    :style="{
                                        backgroundColor: `${roleAccent(person.role_key)}1a`,
                                        color: roleAccent(person.role_key),
                                    }"
                                    >{{ person.role_name }}</span
                                >
                            </td>
                            <td class="px-5 py-3.5">
                                <select
                                    :value="person.work_shift_id ?? ''"
                                    class="w-full min-w-32 rounded-lg border border-slate-200 bg-white px-2.5 py-1.5 text-xs font-medium text-slate-700 transition outline-none focus:border-cyan-400 focus:ring-2 focus:ring-cyan-100 disabled:cursor-not-allowed disabled:bg-slate-50 disabled:text-slate-400"
                                    :disabled="
                                        !capabilities.update ||
                                        savingShiftIds.includes(person.id)
                                    "
                                    :aria-label="`Atur shift ${person.name}`"
                                    @change="changeShift(person, $event)"
                                >
                                    <option value="">Tidak ada Shift</option>
                                    <option
                                        v-for="shift in shifts"
                                        :key="shift.id"
                                        :value="shift.id"
                                    >
                                        {{ shift.name }}
                                    </option>
                                </select>
                            </td>
                            <td class="px-5 py-3.5">
                                <StatusPill
                                    :status="
                                        person.is_active ? 'aktif' : 'nonaktif'
                                    "
                                />
                            </td>
                            <td class="px-5 py-3.5 text-[11px] text-slate-500">
                                {{ person.last_active }}
                            </td>
                            <td class="px-5 py-3.5 text-right">
                                <span
                                    v-if="person.is_owner"
                                    class="text-[11px] font-medium text-slate-400"
                                    >Dikelola via profil</span
                                >
                                <button
                                    v-else-if="capabilities.update"
                                    type="button"
                                    class="rounded-lg p-2 text-cyan-700 transition hover:bg-cyan-50"
                                    aria-label="Edit user"
                                    @click="openEditUser(person)"
                                >
                                    <Pencil class="h-4 w-4" />
                                </button>
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

        <section
            class="rounded-2xl border border-slate-200/80 bg-white shadow-sm"
        >
            <div class="border-b border-slate-100 p-5">
                <h3 class="text-sm font-semibold text-slate-900">
                    Matriks akses baca
                </h3>
                <p class="mt-0.5 text-xs text-slate-500">
                    Ringkasan modul yang dapat dibuka oleh setiap role.
                </p>
            </div>
            <div class="overflow-x-auto">
                <table class="w-full min-w-[760px] text-sm">
                    <thead>
                        <tr class="border-b border-slate-100 text-left">
                            <th
                                class="px-5 py-3 text-[11px] font-medium tracking-wider text-slate-400 uppercase"
                            >
                                Modul
                            </th>
                            <th
                                class="px-4 py-3 text-center text-[11px] font-medium tracking-wider text-slate-400 uppercase"
                            >
                                Owner
                            </th>
                            <th
                                v-for="role in roleList"
                                :key="role.id"
                                class="px-4 py-3 text-center text-[11px] font-medium tracking-wider text-slate-400 uppercase"
                            >
                                {{ role.name }}
                            </th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-50">
                        <tr
                            v-for="module in allModules"
                            :key="module.id"
                            class="hover:bg-slate-50/70"
                        >
                            <td class="px-5 py-3">
                                <p class="font-medium text-slate-800">
                                    {{ module.label }}
                                </p>
                                <p class="text-[11px] text-slate-500">
                                    {{ module.caption }}
                                </p>
                            </td>
                            <td class="px-4 py-3">
                                <span
                                    class="mx-auto flex h-6 w-6 items-center justify-center rounded-full bg-emerald-50 text-emerald-600 ring-1 ring-emerald-200"
                                    ><Check
                                        class="h-3.5 w-3.5"
                                        :stroke-width="3"
                                /></span>
                            </td>
                            <td
                                v-for="role in roleList"
                                :key="role.id"
                                class="px-4 py-3"
                            >
                                <span
                                    v-if="
                                        permissionFor(role, module.id)?.can_read
                                    "
                                    class="mx-auto flex h-6 w-6 items-center justify-center rounded-full bg-emerald-50 text-emerald-600 ring-1 ring-emerald-200"
                                    ><Check
                                        class="h-3.5 w-3.5"
                                        :stroke-width="3"
                                /></span>
                                <span
                                    v-else
                                    class="mx-auto flex h-6 w-6 items-center justify-center rounded-full bg-rose-50 text-rose-500 ring-1 ring-rose-200"
                                    ><X class="h-3.5 w-3.5" :stroke-width="3"
                                /></span>
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </section>
    </div>

    <ModalDialog
        :open="isUserFormOpen"
        :title="editingUserId ? 'Edit pegawai' : 'Tambah pegawai'"
        caption="Atur akun, role, shift, dan status pegawai."
        size="lg"
        @close="isUserFormOpen = false"
    >
        <form
            id="admin-user-form"
            class="space-y-4"
            @submit.prevent="submitUser"
        >
            <div>
                <label
                    for="user-name"
                    class="text-xs font-medium text-slate-600"
                    >Nama lengkap</label
                >
                <input
                    id="user-name"
                    v-model="userForm.name"
                    type="text"
                    class="mt-1.5 w-full rounded-xl border border-slate-200 px-3 py-2.5 text-sm focus:border-cyan-400 focus:outline-none"
                />
                <InputError class="mt-1" :message="userForm.errors.name" />
            </div>
            <div class="grid grid-cols-1 gap-3 sm:grid-cols-2">
                <div>
                    <label
                        for="user-email"
                        class="text-xs font-medium text-slate-600"
                        >Email</label
                    >
                    <input
                        id="user-email"
                        v-model="userForm.email"
                        type="email"
                        class="mt-1.5 w-full rounded-xl border border-slate-200 px-3 py-2.5 text-sm focus:border-cyan-400 focus:outline-none"
                    />
                    <InputError class="mt-1" :message="userForm.errors.email" />
                </div>
                <div>
                    <label
                        for="user-phone"
                        class="text-xs font-medium text-slate-600"
                        >Nomor HP</label
                    >
                    <input
                        id="user-phone"
                        v-model="userForm.phone"
                        type="tel"
                        class="mt-1.5 w-full rounded-xl border border-slate-200 px-3 py-2.5 text-sm focus:border-cyan-400 focus:outline-none"
                    />
                    <InputError class="mt-1" :message="userForm.errors.phone" />
                </div>
            </div>
            <div class="grid grid-cols-1 gap-3 sm:grid-cols-2">
                <div>
                    <label
                        for="user-role"
                        class="text-xs font-medium text-slate-600"
                        >Role</label
                    >
                    <select
                        id="user-role"
                        v-model="userForm.role_id"
                        class="mt-1.5 w-full rounded-xl border border-slate-200 px-3 py-2.5 text-sm focus:border-cyan-400 focus:outline-none"
                    >
                        <option :value="null" disabled>Pilih role</option>
                        <option
                            v-for="role in roleList.filter(
                                (item) => item.is_active,
                            )"
                            :key="role.id"
                            :value="role.id"
                        >
                            {{ role.name }}
                        </option>
                    </select>
                    <InputError
                        class="mt-1"
                        :message="userForm.errors.role_id"
                    />
                </div>
                <div>
                    <label
                        for="user-shift"
                        class="text-xs font-medium text-slate-600"
                        >Shift</label
                    >
                    <select
                        id="user-shift"
                        v-model="userForm.work_shift_id"
                        class="mt-1.5 w-full rounded-xl border border-slate-200 px-3 py-2.5 text-sm focus:border-cyan-400 focus:outline-none"
                    >
                        <option :value="null">Tanpa shift</option>
                        <option
                            v-for="shift in shifts"
                            :key="shift.id"
                            :value="shift.id"
                        >
                            {{ shift.name }}
                        </option>
                    </select>
                    <InputError
                        class="mt-1"
                        :message="userForm.errors.work_shift_id"
                    />
                </div>
            </div>
            <div class="grid grid-cols-1 gap-3 sm:grid-cols-2">
                <div>
                    <label
                        for="user-password"
                        class="text-xs font-medium text-slate-600"
                        >Password {{ editingUserId ? '(opsional)' : '' }}</label
                    >
                    <input
                        id="user-password"
                        v-model="userForm.password"
                        type="password"
                        autocomplete="new-password"
                        class="mt-1.5 w-full rounded-xl border border-slate-200 px-3 py-2.5 text-sm focus:border-cyan-400 focus:outline-none"
                    />
                    <InputError
                        class="mt-1"
                        :message="userForm.errors.password"
                    />
                </div>
                <div>
                    <label
                        for="user-password-confirmation"
                        class="text-xs font-medium text-slate-600"
                        >Konfirmasi password</label
                    >
                    <input
                        id="user-password-confirmation"
                        v-model="userForm.password_confirmation"
                        type="password"
                        autocomplete="new-password"
                        class="mt-1.5 w-full rounded-xl border border-slate-200 px-3 py-2.5 text-sm focus:border-cyan-400 focus:outline-none"
                    />
                </div>
            </div>
            <label
                class="flex items-center gap-3 rounded-xl border border-slate-200 p-3 text-sm text-slate-700"
            >
                <input
                    v-model="userForm.is_active"
                    type="checkbox"
                    class="h-4 w-4 rounded border-slate-300 text-cyan-600 focus:ring-cyan-500"
                />
                Akun aktif dan dapat login
            </label>
        </form>
        <template #footer>
            <button
                type="button"
                class="flex-1 rounded-xl border border-slate-200 py-2.5 text-sm font-medium text-slate-600 hover:bg-slate-50"
                @click="isUserFormOpen = false"
            >
                Batal
            </button>
            <button
                form="admin-user-form"
                type="submit"
                class="flex-1 rounded-xl bg-gradient-to-r from-cyan-500 to-sky-600 py-2.5 text-sm font-semibold text-white disabled:cursor-not-allowed disabled:opacity-60"
                :disabled="userForm.processing"
            >
                {{ userForm.processing ? 'Menyimpan...' : 'Simpan user' }}
            </button>
        </template>
    </ModalDialog>

    <ModalDialog
        :open="isRoleFormOpen"
        :title="editingRoleId ? 'Edit role' : 'Tambah role'"
        caption="Tentukan identitas role dan izin CRUD per modul."
        size="xl"
        @close="isRoleFormOpen = false"
    >
        <form
            id="admin-role-form"
            class="space-y-5"
            @submit.prevent="submitRole"
        >
            <div class="grid grid-cols-1 gap-3 sm:grid-cols-2">
                <div>
                    <label
                        for="role-name"
                        class="text-xs font-medium text-slate-600"
                        >Nama role</label
                    >
                    <input
                        id="role-name"
                        v-model="roleForm.name"
                        type="text"
                        class="mt-1.5 w-full rounded-xl border border-slate-200 px-3 py-2.5 text-sm focus:border-cyan-400 focus:outline-none"
                    />
                    <InputError class="mt-1" :message="roleForm.errors.name" />
                </div>
                <label
                    class="mt-6 flex items-center gap-3 rounded-xl border border-slate-200 px-3 py-2.5 text-sm text-slate-700"
                >
                    <input
                        v-model="roleForm.is_active"
                        type="checkbox"
                        class="h-4 w-4 rounded border-slate-300 text-cyan-600 focus:ring-cyan-500"
                    />
                    Role aktif
                </label>
            </div>
            <div>
                <label
                    for="role-description"
                    class="text-xs font-medium text-slate-600"
                    >Deskripsi</label
                >
                <textarea
                    id="role-description"
                    v-model="roleForm.description"
                    rows="2"
                    class="mt-1.5 w-full rounded-xl border border-slate-200 px-3 py-2.5 text-sm focus:border-cyan-400 focus:outline-none"
                ></textarea>
                <InputError
                    class="mt-1"
                    :message="roleForm.errors.description"
                />
            </div>
            <div class="overflow-x-auto rounded-xl border border-slate-200">
                <table class="w-full min-w-[680px] text-sm">
                    <thead>
                        <tr
                            class="border-b border-slate-200 bg-slate-50 text-[11px] tracking-wider text-slate-500 uppercase"
                        >
                            <th class="px-4 py-3 text-left">Modul</th>
                            <th class="px-3 py-3">Baca</th>
                            <th class="px-3 py-3">Tambah</th>
                            <th class="px-3 py-3">Ubah</th>
                            <th class="px-3 py-3">Hapus</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100">
                        <tr
                            v-for="(permission, index) in roleForm.permissions"
                            :key="permission.module_id"
                        >
                            <td class="px-4 py-3">
                                <p class="font-medium text-slate-800">
                                    {{ allModules[index]?.label }}
                                </p>
                                <p class="text-[11px] text-slate-500">
                                    {{ allModules[index]?.caption }}
                                </p>
                            </td>
                            <td
                                v-for="field in [
                                    'can_read',
                                    'can_create',
                                    'can_update',
                                    'can_delete',
                                ] as const"
                                :key="field"
                                class="px-3 py-3 text-center"
                            >
                                <input
                                    v-model="permission[field]"
                                    type="checkbox"
                                    class="h-4 w-4 rounded border-slate-300 text-cyan-600 focus:ring-cyan-500"
                                />
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>
            <InputError :message="roleForm.errors.permissions" />
        </form>
        <template #footer>
            <button
                type="button"
                class="flex-1 rounded-xl border border-slate-200 py-2.5 text-sm font-medium text-slate-600 hover:bg-slate-50"
                @click="isRoleFormOpen = false"
            >
                Batal
            </button>
            <button
                form="admin-role-form"
                type="submit"
                class="flex-1 rounded-xl bg-gradient-to-r from-cyan-500 to-sky-600 py-2.5 text-sm font-semibold text-white disabled:cursor-not-allowed disabled:opacity-60"
                :disabled="roleForm.processing"
            >
                {{ roleForm.processing ? 'Menyimpan...' : 'Simpan role' }}
            </button>
        </template>
    </ModalDialog>
</template>
