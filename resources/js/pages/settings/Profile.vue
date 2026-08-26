<script setup lang="ts">
import { Form, Head, usePage } from '@inertiajs/vue3';
import { Save, UserRound } from '@lucide/vue';
import { computed } from 'vue';
import ProfileController from '@/actions/App/Http/Controllers/Settings/ProfileController';
import SettingsNav from '@/components/admin/SettingsNav.vue';
import DeleteAdmin from '@/components/DeleteAdmin.vue';
import InputError from '@/components/InputError.vue';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';

const page = usePage();
const admin = computed(() => page.props.auth.admin);
</script>

<template>
    <Head title="Pengaturan Profil" />

    <div class="space-y-6">
        <header>
            <p class="text-sm font-medium text-cyan-700">Pengaturan akun</p>
            <h2
                class="mt-1 text-2xl font-semibold tracking-tight text-slate-900"
            >
                Profil admin
            </h2>
            <p class="mt-1 text-sm text-slate-500">
                Perbarui nama dan alamat email yang digunakan pada akun admin.
            </p>
        </header>

        <SettingsNav />

        <div v-if="admin" class="grid gap-6 xl:grid-cols-3">
            <section
                class="rounded-2xl border border-slate-200/80 bg-white p-5 shadow-sm sm:p-6 xl:col-span-2"
            >
                <div
                    class="flex items-center gap-3 border-b border-slate-100 pb-5"
                >
                    <span
                        class="flex h-11 w-11 items-center justify-center rounded-xl bg-cyan-50 text-cyan-700"
                    >
                        <UserRound class="h-5 w-5" />
                    </span>
                    <div>
                        <h3 class="font-semibold text-slate-900">
                            Informasi profil
                        </h3>
                        <p class="text-sm text-slate-500">
                            Data ini ditampilkan pada konsol admin.
                        </p>
                    </div>
                </div>

                <Form
                    v-bind="ProfileController.update.form()"
                    class="mt-6 space-y-5"
                    v-slot="{ errors, processing }"
                >
                    <div class="grid gap-2">
                        <Label for="name" class="text-slate-700">Nama</Label>
                        <Input
                            id="name"
                            name="name"
                            :default-value="admin.name"
                            required
                            autocomplete="name"
                            placeholder="Nama lengkap"
                            class="h-11 border-slate-200 bg-white focus-visible:ring-cyan-500"
                        />
                        <InputError :message="errors.name" />
                    </div>

                    <div class="grid gap-2">
                        <Label for="email" class="text-slate-700">
                            Alamat email
                        </Label>
                        <Input
                            id="email"
                            type="email"
                            name="email"
                            :default-value="admin.email"
                            required
                            autocomplete="username"
                            placeholder="email@contoh.com"
                            class="h-11 border-slate-200 bg-white focus-visible:ring-cyan-500"
                        />
                        <InputError :message="errors.email" />
                    </div>

                    <div
                        class="flex justify-end border-t border-slate-100 pt-5"
                    >
                        <button
                            type="submit"
                            :disabled="processing"
                            data-test="update-profile-button"
                            class="inline-flex items-center gap-2 rounded-xl bg-cyan-600 px-4 py-2.5 text-sm font-semibold text-white shadow-sm transition hover:bg-cyan-700 disabled:cursor-not-allowed disabled:opacity-60"
                        >
                            <Save class="h-4 w-4" />
                            {{
                                processing ? 'Menyimpan...' : 'Simpan perubahan'
                            }}
                        </button>
                    </div>
                </Form>
            </section>

            <aside class="space-y-6">
                <div
                    class="rounded-2xl border border-slate-200/80 bg-white p-5 shadow-sm"
                >
                    <p
                        class="text-xs font-medium tracking-wide text-slate-400 uppercase"
                    >
                        Akun aktif
                    </p>
                    <p class="mt-3 font-semibold text-slate-900">
                        {{ admin.name }}
                    </p>
                    <p class="mt-1 text-sm break-all text-slate-500">
                        {{ admin.email }}
                    </p>
                </div>

                <DeleteAdmin />
            </aside>
        </div>
    </div>
</template>
