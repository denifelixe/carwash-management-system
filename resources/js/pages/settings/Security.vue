<script setup lang="ts">
import { Form, Head } from '@inertiajs/vue3';
import { KeyRound, Save } from '@lucide/vue';
import SecurityController from '@/actions/App/Http/Controllers/Settings/SecurityController';
import SettingsNav from '@/components/admin/SettingsNav.vue';
import InputError from '@/components/InputError.vue';
import PasswordInput from '@/components/PasswordInput.vue';
import { Label } from '@/components/ui/label';
</script>

<template>
    <Head title="Keamanan Akun" />

    <div class="space-y-6">
        <header>
            <p class="text-sm font-medium text-cyan-700">Pengaturan akun</p>
            <h2
                class="mt-1 text-2xl font-semibold tracking-tight text-slate-900"
            >
                Keamanan akun
            </h2>
            <p class="mt-1 text-sm text-slate-500">
                Gunakan kata sandi yang kuat dan tidak digunakan pada akun lain.
            </p>
        </header>

        <SettingsNav />

        <section
            class="max-w-3xl rounded-2xl border border-slate-200/80 bg-white p-5 shadow-sm sm:p-6"
        >
            <div class="flex items-center gap-3 border-b border-slate-100 pb-5">
                <span
                    class="flex h-11 w-11 items-center justify-center rounded-xl bg-cyan-50 text-cyan-700"
                >
                    <KeyRound class="h-5 w-5" />
                </span>
                <div>
                    <h3 class="font-semibold text-slate-900">
                        Ubah kata sandi
                    </h3>
                    <p class="text-sm text-slate-500">
                        Konfirmasi kata sandi saat ini sebelum membuat yang
                        baru.
                    </p>
                </div>
            </div>

            <Form
                v-bind="SecurityController.update.form()"
                :options="{ preserveScroll: true }"
                reset-on-success
                :reset-on-error="[
                    'password',
                    'password_confirmation',
                    'current_password',
                ]"
                class="mt-6 space-y-5"
                v-slot="{ errors, processing }"
            >
                <div class="grid gap-2">
                    <Label for="current_password" class="text-slate-700">
                        Kata sandi saat ini
                    </Label>
                    <PasswordInput
                        id="current_password"
                        name="current_password"
                        class="h-11"
                        autocomplete="current-password"
                        placeholder="Kata sandi saat ini"
                    />
                    <InputError :message="errors.current_password" />
                </div>

                <div class="grid gap-4 sm:grid-cols-2">
                    <div class="grid gap-2">
                        <Label for="password" class="text-slate-700">
                            Kata sandi baru
                        </Label>
                        <PasswordInput
                            id="password"
                            name="password"
                            class="h-11"
                            autocomplete="new-password"
                            placeholder="Kata sandi baru"
                        />
                        <InputError :message="errors.password" />
                    </div>

                    <div class="grid gap-2">
                        <Label
                            for="password_confirmation"
                            class="text-slate-700"
                        >
                            Konfirmasi kata sandi
                        </Label>
                        <PasswordInput
                            id="password_confirmation"
                            name="password_confirmation"
                            class="h-11"
                            autocomplete="new-password"
                            placeholder="Ulangi kata sandi baru"
                        />
                        <InputError :message="errors.password_confirmation" />
                    </div>
                </div>

                <div class="flex justify-end border-t border-slate-100 pt-5">
                    <button
                        type="submit"
                        :disabled="processing"
                        data-test="update-password-button"
                        class="inline-flex items-center gap-2 rounded-xl bg-cyan-600 px-4 py-2.5 text-sm font-semibold text-white shadow-sm transition hover:bg-cyan-700 disabled:cursor-not-allowed disabled:opacity-60"
                    >
                        <Save class="h-4 w-4" />
                        {{ processing ? 'Menyimpan...' : 'Simpan kata sandi' }}
                    </button>
                </div>
            </Form>
        </section>
    </div>
</template>
