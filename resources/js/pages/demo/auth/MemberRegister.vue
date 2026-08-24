<script setup lang="ts">
import { Head, Link } from '@inertiajs/vue3';
import {
    ArrowLeft,
    Car,
    CircleCheck,
    Lock,
    Mail,
    Phone,
    User,
} from '@lucide/vue';
import { computed, ref } from 'vue';
import { home } from '@/routes/demo';
import member from '@/routes/demo/member';
import type { CarwashBrand } from '@/types/demo';

defineProps<{
    brand: CarwashBrand;
}>();

const form = ref({
    name: '',
    phone: '',
    email: '',
    plate: '',
    vehicle: '',
    password: '',
});

const attempted = ref<boolean>(false);
const registered = ref<boolean>(false);

const errors = computed<Record<string, string>>(() => {
    if (!attempted.value) {
        return {};
    }

    const found: Record<string, string> = {};

    if (form.value.name.trim() === '') {
        found.name = 'Nama lengkap wajib diisi.';
    }

    if (form.value.phone.trim() === '') {
        found.phone = 'Nomor HP wajib diisi.';
    }

    if (form.value.email.trim() !== '' && !form.value.email.includes('@')) {
        found.email = 'Format email tidak valid.';
    }

    if (form.value.plate.trim() === '') {
        found.plate = 'Plat nomor wajib diisi.';
    }

    if (form.value.password.length < 8) {
        found.password = 'Password minimal 8 karakter.';
    }

    return found;
});

function submit(): void {
    attempted.value = true;

    if (Object.keys(errors.value).length > 0) {
        return;
    }

    registered.value = true;
}
</script>

<template>
    <Head :title="`${brand.name} — Daftar Member`" />

    <div
        class="flex min-h-screen items-center justify-center bg-gradient-to-br from-slate-100 via-slate-50 to-cyan-100 px-4 py-10 font-sans"
    >
        <div class="w-full max-w-sm">
            <Link
                :href="home.url()"
                class="mb-6 inline-flex items-center gap-1.5 text-xs font-medium text-slate-500 transition hover:text-slate-700"
            >
                <ArrowLeft class="h-3.5 w-3.5" />
                Kembali ke pemilihan aplikasi
            </Link>

            <div
                class="overflow-hidden rounded-3xl border border-slate-200 bg-white shadow-xl shadow-slate-300/40"
            >
                <template v-if="!registered">
                    <div class="px-6 pt-6 pb-2">
                        <div
                            class="flex h-12 w-12 items-center justify-center rounded-2xl bg-gradient-to-br from-cyan-400 to-sky-600 text-xl shadow-lg shadow-cyan-500/25"
                        >
                            {{ brand.logo }}
                        </div>
                        <p class="mt-3 text-lg font-semibold text-slate-900">
                            Daftar member
                        </p>
                        <p class="mt-1 text-xs text-slate-500">
                            Gratis, langsung dapat kartu stempel digital.
                        </p>
                    </div>

                    <form class="space-y-3.5 p-6" @submit.prevent="submit">
                        <div>
                            <label
                                for="name"
                                class="text-xs font-medium text-slate-600"
                            >
                                Nama lengkap
                            </label>
                            <div
                                class="mt-1.5 flex items-center gap-2 rounded-xl border bg-white px-3 py-2.5 focus-within:border-cyan-400"
                                :class="
                                    errors.name
                                        ? 'border-rose-300'
                                        : 'border-slate-200'
                                "
                            >
                                <User class="h-4 w-4 shrink-0 text-slate-400" />
                                <input
                                    id="name"
                                    v-model="form.name"
                                    type="text"
                                    placeholder="Nama sesuai identitas"
                                    class="w-full bg-transparent text-sm text-slate-800 placeholder:text-slate-400 focus:outline-none"
                                />
                            </div>
                            <p
                                v-if="errors.name"
                                class="mt-1 text-[11px] text-rose-600"
                            >
                                {{ errors.name }}
                            </p>
                        </div>

                        <div>
                            <label
                                for="reg-phone"
                                class="text-xs font-medium text-slate-600"
                            >
                                Nomor HP
                            </label>
                            <div
                                class="mt-1.5 flex items-center gap-2 rounded-xl border bg-white px-3 py-2.5 focus-within:border-cyan-400"
                                :class="
                                    errors.phone
                                        ? 'border-rose-300'
                                        : 'border-slate-200'
                                "
                            >
                                <Phone
                                    class="h-4 w-4 shrink-0 text-slate-400"
                                />
                                <input
                                    id="reg-phone"
                                    v-model="form.phone"
                                    type="tel"
                                    placeholder="0812-xxxx-xxxx"
                                    class="w-full bg-transparent text-sm text-slate-800 placeholder:text-slate-400 focus:outline-none"
                                />
                            </div>
                            <p
                                v-if="errors.phone"
                                class="mt-1 text-[11px] text-rose-600"
                            >
                                {{ errors.phone }}
                            </p>
                        </div>

                        <div>
                            <label
                                for="email"
                                class="text-xs font-medium text-slate-600"
                            >
                                Email
                                <span class="text-slate-400">(opsional)</span>
                            </label>
                            <div
                                class="mt-1.5 flex items-center gap-2 rounded-xl border bg-white px-3 py-2.5 focus-within:border-cyan-400"
                                :class="
                                    errors.email
                                        ? 'border-rose-300'
                                        : 'border-slate-200'
                                "
                            >
                                <Mail class="h-4 w-4 shrink-0 text-slate-400" />
                                <input
                                    id="email"
                                    v-model="form.email"
                                    type="email"
                                    placeholder="nama@email.com"
                                    class="w-full bg-transparent text-sm text-slate-800 placeholder:text-slate-400 focus:outline-none"
                                />
                            </div>
                            <p
                                v-if="errors.email"
                                class="mt-1 text-[11px] text-rose-600"
                            >
                                {{ errors.email }}
                            </p>
                        </div>

                        <div class="grid grid-cols-2 gap-3">
                            <div>
                                <label
                                    for="plate"
                                    class="text-xs font-medium text-slate-600"
                                >
                                    Plat nomor
                                </label>
                                <div
                                    class="mt-1.5 flex items-center gap-2 rounded-xl border bg-white px-3 py-2.5 focus-within:border-cyan-400"
                                    :class="
                                        errors.plate
                                            ? 'border-rose-300'
                                            : 'border-slate-200'
                                    "
                                >
                                    <Car
                                        class="h-4 w-4 shrink-0 text-slate-400"
                                    />
                                    <input
                                        id="plate"
                                        v-model="form.plate"
                                        type="text"
                                        placeholder="B 1234 CDE"
                                        class="w-full bg-transparent text-sm text-slate-800 uppercase placeholder:text-slate-400 placeholder:normal-case focus:outline-none"
                                    />
                                </div>
                            </div>
                            <div>
                                <label
                                    for="vehicle"
                                    class="text-xs font-medium text-slate-600"
                                >
                                    Kendaraan
                                </label>
                                <input
                                    id="vehicle"
                                    v-model="form.vehicle"
                                    type="text"
                                    placeholder="Toyota Avanza"
                                    class="mt-1.5 w-full rounded-xl border border-slate-200 bg-white px-3 py-2.5 text-sm text-slate-800 placeholder:text-slate-400 focus:border-cyan-400 focus:outline-none"
                                />
                            </div>
                        </div>
                        <p
                            v-if="errors.plate"
                            class="text-[11px] text-rose-600"
                        >
                            {{ errors.plate }}
                        </p>

                        <div>
                            <label
                                for="reg-password"
                                class="text-xs font-medium text-slate-600"
                            >
                                Password
                            </label>
                            <div
                                class="mt-1.5 flex items-center gap-2 rounded-xl border bg-white px-3 py-2.5 focus-within:border-cyan-400"
                                :class="
                                    errors.password
                                        ? 'border-rose-300'
                                        : 'border-slate-200'
                                "
                            >
                                <Lock class="h-4 w-4 shrink-0 text-slate-400" />
                                <input
                                    id="reg-password"
                                    v-model="form.password"
                                    type="password"
                                    placeholder="Minimal 8 karakter"
                                    class="w-full bg-transparent text-sm text-slate-800 placeholder:text-slate-400 focus:outline-none"
                                />
                            </div>
                            <p
                                v-if="errors.password"
                                class="mt-1 text-[11px] text-rose-600"
                            >
                                {{ errors.password }}
                            </p>
                        </div>

                        <button
                            type="submit"
                            class="mt-2 w-full rounded-xl bg-gradient-to-r from-cyan-500 to-sky-600 py-3 text-sm font-semibold text-white shadow-lg shadow-cyan-500/25 transition hover:from-cyan-600 hover:to-sky-700"
                        >
                            Buat akun member
                        </button>

                        <p class="text-center text-xs text-slate-500">
                            Sudah punya akun?
                            <Link
                                :href="member.login.url()"
                                class="font-semibold text-cyan-700 hover:text-cyan-800"
                            >
                                Masuk
                            </Link>
                        </p>
                    </form>
                </template>

                <template v-else>
                    <div
                        class="bg-gradient-to-br from-emerald-500 to-teal-600 px-6 py-8 text-center text-white"
                    >
                        <div
                            class="mx-auto flex h-14 w-14 items-center justify-center rounded-full bg-white/20"
                        >
                            <CircleCheck class="h-8 w-8" />
                        </div>
                        <p class="mt-3 text-lg font-semibold">
                            Akun berhasil dibuat
                        </p>
                        <p class="mt-1 text-xs text-emerald-50/90">
                            Kartu stempel digital kamu sudah aktif.
                        </p>
                    </div>
                    <div class="space-y-3 p-6 text-center">
                        <p class="text-sm text-slate-600">
                            Halo
                            <span class="font-semibold text-slate-900">
                                {{ form.name }}
                            </span>
                            , kumpulkan {{ brand.stampTarget }} stempel untuk
                            {{ brand.stampReward }}.
                        </p>
                        <Link
                            :href="member.dashboard.url()"
                            class="block w-full rounded-xl bg-slate-900 py-3 text-sm font-semibold text-white transition hover:bg-slate-800"
                        >
                            Masuk ke portal member
                        </Link>
                    </div>
                </template>
            </div>
        </div>
    </div>
</template>
