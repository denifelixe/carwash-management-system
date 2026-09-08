<script setup lang="ts">
import { router, useForm, usePage } from '@inertiajs/vue3';
import { computed, ref, watch } from 'vue';
import ModalDialog from '@/components/demo/ModalDialog.vue';
import {
    adminLogoutRequested,
    requestAdminLogout,
} from '@/composables/useAdminLogout';
import { logout } from '@/routes/admin';
import { confirm } from '@/routes/admin/login-shift';

const page = usePage();
const loginShift = computed(() => page.props.loginShift);
const form = useForm({ shift_id: null as number | null });
const loggingOut = ref(false);
const logoutError = ref('');

watch(
    () => loginShift.value?.pending,
    () => form.resetAndClearErrors(),
);

function confirmShift(): void {
    form.post(confirm.url(), { preserveScroll: true });
}

function confirmLogout(): void {
    if (loggingOut.value) {
        return;
    }

    loggingOut.value = true;
    logoutError.value = '';
    router.post(
        logout.url(),
        {},
        {
            onSuccess: () => {
                adminLogoutRequested.value = false;
                router.flushAll();
            },
            onError: () => {
                logoutError.value = 'Gagal keluar. Silakan coba lagi.';
            },
            onFinish: () => {
                loggingOut.value = false;
            },
        },
    );
}
</script>

<template>
    <div v-if="page.props.auth.admin && page.props.mode !== 'demo'">
        <ModalDialog
            :open="!!loginShift?.pending"
            title="Login berhasil"
            :caption="
                loginShift?.requires_selection
                    ? 'Ada beberapa shift aktif ketika Anda login. Pilih satu shift untuk sesi ini.'
                    : 'Periksa shift Anda sebelum melanjutkan.'
            "
            :dismissible="false"
            size="sm"
        >
            <form
                id="confirm-login-shift"
                class="space-y-4"
                @submit.prevent="confirmShift"
            >
                <fieldset
                    v-if="loginShift?.requires_selection"
                    class="space-y-2"
                    :disabled="form.processing"
                >
                    <legend class="sr-only">Pilih shift login</legend>
                    <label
                        v-for="shift in loginShift.shifts"
                        :key="shift.id"
                        class="flex cursor-pointer items-center gap-3 rounded-xl border border-slate-200 p-4 has-checked:border-cyan-500 has-checked:bg-cyan-50"
                    >
                        <input
                            v-model="form.shift_id"
                            type="radio"
                            name="shift_id"
                            :value="shift.id"
                            class="accent-cyan-600"
                        />
                        <span>
                            <span class="block font-semibold text-slate-900">{{
                                shift.name
                            }}</span>
                            <span class="text-sm text-slate-500">{{
                                shift.time
                            }}</span>
                        </span>
                    </label>
                </fieldset>
                <div v-else class="rounded-xl bg-cyan-50 p-4">
                    <p class="font-semibold text-slate-900">
                        {{ loginShift?.label }}
                    </p>
                    <p
                        v-if="loginShift?.shifts[0]?.time"
                        class="mt-1 text-sm text-slate-600"
                    >
                        {{ loginShift.shifts[0].time }}
                    </p>
                </div>
                <p
                    v-if="loginShift?.requires_selection"
                    class="text-sm text-slate-500"
                >
                    Shift yang dipilih digunakan untuk transaksi selama sesi
                    login ini.
                </p>
                <p
                    v-if="form.errors.shift_id"
                    role="alert"
                    class="text-sm text-red-600"
                >
                    {{ form.errors.shift_id }}
                </p>
            </form>
            <template #footer>
                <button
                    type="button"
                    class="rounded-xl border border-slate-200 px-4 py-2 text-sm text-slate-600"
                    :disabled="form.processing"
                    @click="requestAdminLogout"
                >
                    Keluar
                </button>
                <button
                    type="submit"
                    form="confirm-login-shift"
                    class="flex-1 rounded-xl bg-cyan-600 px-4 py-2 text-sm font-semibold text-white disabled:opacity-50"
                    :disabled="
                        form.processing ||
                        (loginShift?.requires_selection &&
                            form.shift_id === null)
                    "
                >
                    {{
                        form.processing
                            ? 'Menyimpan…'
                            : loginShift?.requires_selection
                              ? 'Gunakan Shift'
                              : 'Lanjutkan'
                    }}
                </button>
            </template>
        </ModalDialog>
        <ModalDialog
            :open="adminLogoutRequested"
            title="Yakin ingin keluar?"
            caption="Anda perlu login kembali untuk melanjutkan aktivitas."
            size="sm"
            layer="top"
            :dismissible="!loggingOut"
            @close="adminLogoutRequested = false"
        >
            <p class="text-sm text-slate-600">
                Pastikan pekerjaan Anda sudah tersimpan sebelum keluar.
            </p>
            <p
                v-if="logoutError"
                role="alert"
                class="mt-2 text-sm text-red-600"
            >
                {{ logoutError }}
            </p>
            <template #footer>
                <button
                    type="button"
                    class="flex-1 rounded-xl border border-slate-200 px-4 py-2 text-sm font-medium text-slate-600"
                    :disabled="loggingOut"
                    @click="adminLogoutRequested = false"
                >
                    Batal
                </button>
                <button
                    type="button"
                    class="flex-1 rounded-xl bg-red-600 px-4 py-2 text-sm font-semibold text-white disabled:opacity-50"
                    :disabled="loggingOut"
                    @click="confirmLogout"
                >
                    {{ loggingOut ? 'Keluar…' : 'Ya, Keluar' }}
                </button>
            </template>
        </ModalDialog>
    </div>
</template>
