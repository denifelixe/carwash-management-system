<script setup lang="ts">
import { Head, Link, router } from '@inertiajs/vue3';
import { ArrowLeft, Eye, EyeOff, Lock, Phone, Sparkles } from '@lucide/vue';
import { computed, ref } from 'vue';
import carwash from '@/routes/carwash';
import member from '@/routes/carwash/member';
import type { CarwashBrand } from '@/types/carwash';

defineProps<{
    brand: CarwashBrand;
}>();

const phone = ref<string>('0812-3456-7890');
const password = ref<string>('demo1234');
const showPassword = ref<boolean>(false);
const attempted = ref<boolean>(false);

const phoneError = computed<string | null>(() => {
    if (!attempted.value) {
        return null;
    }

    return phone.value.trim() === '' ? 'Nomor HP wajib diisi.' : null;
});

const passwordError = computed<string | null>(() => {
    if (!attempted.value) {
        return null;
    }

    if (password.value === '') {
        return 'Password wajib diisi.';
    }

    return password.value.length < 8 ? 'Password minimal 8 karakter.' : null;
});

/** Prototype login: validates locally, then enters the portal. */
function submit(): void {
    attempted.value = true;

    if (phoneError.value || passwordError.value) {
        return;
    }

    router.visit(member.dashboard.url());
}
</script>

<template>
    <Head :title="`${brand.name} — Masuk Member`" />

    <div
        class="flex min-h-screen items-center justify-center bg-gradient-to-br from-slate-100 via-slate-50 to-cyan-100 px-4 py-10 font-sans"
    >
        <div class="w-full max-w-sm">
            <Link
                :href="carwash.entry.url()"
                class="mb-6 inline-flex items-center gap-1.5 text-xs font-medium text-slate-500 transition hover:text-slate-700"
            >
                <ArrowLeft class="h-3.5 w-3.5" />
                Kembali ke pemilihan aplikasi
            </Link>

            <div
                class="overflow-hidden rounded-3xl border border-slate-200 bg-white shadow-xl shadow-slate-300/40"
            >
                <div
                    class="bg-gradient-to-br from-cyan-500 to-sky-600 px-6 py-8 text-center text-white"
                >
                    <div
                        class="mx-auto flex h-14 w-14 items-center justify-center rounded-2xl bg-white/20 text-2xl"
                    >
                        {{ brand.logo }}
                    </div>
                    <p class="mt-3 text-lg font-semibold">{{ brand.name }}</p>
                    <p class="mt-1 text-xs text-cyan-50/90">
                        Masuk untuk melihat stempel dan reward kamu
                    </p>
                </div>

                <form class="space-y-4 p-6" @submit.prevent="submit">
                    <div>
                        <label
                            for="phone"
                            class="text-xs font-medium text-slate-600"
                        >
                            Nomor HP
                        </label>
                        <div
                            class="mt-1.5 flex items-center gap-2 rounded-xl border bg-white px-3 py-2.5 transition focus-within:border-cyan-400"
                            :class="
                                phoneError
                                    ? 'border-rose-300'
                                    : 'border-slate-200'
                            "
                        >
                            <Phone class="h-4 w-4 shrink-0 text-slate-400" />
                            <input
                                id="phone"
                                v-model="phone"
                                type="tel"
                                autocomplete="tel"
                                placeholder="0812-xxxx-xxxx"
                                class="w-full bg-transparent text-sm text-slate-800 placeholder:text-slate-400 focus:outline-none"
                            />
                        </div>
                        <p
                            v-if="phoneError"
                            class="mt-1 text-[11px] text-rose-600"
                        >
                            {{ phoneError }}
                        </p>
                    </div>

                    <div>
                        <label
                            for="password"
                            class="text-xs font-medium text-slate-600"
                        >
                            Password
                        </label>
                        <div
                            class="mt-1.5 flex items-center gap-2 rounded-xl border bg-white px-3 py-2.5 transition focus-within:border-cyan-400"
                            :class="
                                passwordError
                                    ? 'border-rose-300'
                                    : 'border-slate-200'
                            "
                        >
                            <Lock class="h-4 w-4 shrink-0 text-slate-400" />
                            <input
                                id="password"
                                v-model="password"
                                :type="showPassword ? 'text' : 'password'"
                                autocomplete="current-password"
                                placeholder="Minimal 8 karakter"
                                class="w-full bg-transparent text-sm text-slate-800 placeholder:text-slate-400 focus:outline-none"
                            />
                            <button
                                type="button"
                                class="shrink-0 text-slate-400 transition hover:text-slate-600"
                                :aria-label="
                                    showPassword
                                        ? 'Sembunyikan password'
                                        : 'Tampilkan password'
                                "
                                @click="showPassword = !showPassword"
                            >
                                <component
                                    :is="showPassword ? EyeOff : Eye"
                                    class="h-4 w-4"
                                />
                            </button>
                        </div>
                        <p
                            v-if="passwordError"
                            class="mt-1 text-[11px] text-rose-600"
                        >
                            {{ passwordError }}
                        </p>
                    </div>

                    <div class="flex items-center justify-between text-xs">
                        <label
                            class="flex items-center gap-2 text-slate-500"
                            for="remember"
                        >
                            <input
                                id="remember"
                                type="checkbox"
                                class="h-3.5 w-3.5 rounded border-slate-300 text-cyan-600 focus:ring-cyan-500"
                            />
                            Ingat saya
                        </label>
                        <span class="font-medium text-cyan-700">
                            Lupa password?
                        </span>
                    </div>

                    <button
                        type="submit"
                        class="flex w-full items-center justify-center gap-2 rounded-xl bg-gradient-to-r from-cyan-500 to-sky-600 py-3 text-sm font-semibold text-white shadow-lg shadow-cyan-500/25 transition hover:from-cyan-600 hover:to-sky-700"
                    >
                        <Sparkles class="h-4 w-4" />
                        Masuk
                    </button>

                    <p class="text-center text-xs text-slate-500">
                        Belum punya akun?
                        <Link
                            :href="member.register.url()"
                            class="font-semibold text-cyan-700 hover:text-cyan-800"
                        >
                            Daftar member
                        </Link>
                    </p>
                </form>
            </div>

            <p class="mt-4 text-center text-[11px] text-slate-400">
                Demo prototipe — kredensial sudah terisi, klik Masuk untuk
                melanjutkan.
            </p>
        </div>
    </div>
</template>
