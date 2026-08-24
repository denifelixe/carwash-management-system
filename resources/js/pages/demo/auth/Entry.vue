<script setup lang="ts">
import { Head, Link, router } from '@inertiajs/vue3';
import { ArrowRight, Check, Smartphone, X } from '@lucide/vue';
import { ref } from 'vue';
import member from '@/routes/demo/member';
import session from '@/routes/demo/session';
import type {
    CarwashBrand,
    CarwashModule,
    CarwashRole,
    CarwashRoleMatrix,
} from '@/types/demo';

defineProps<{
    brand: CarwashBrand;
    roles: CarwashRole[];
    matrix: CarwashRoleMatrix;
    modules: CarwashModule[];
    activeRole: string | null;
}>();

const submitting = ref<string | null>(null);

function signInAs(role: string): void {
    submitting.value = role;
    router.post(
        session.role.url(),
        { role },
        {
            onFinish: () => {
                submitting.value = null;
            },
        },
    );
}
</script>

<template>
    <Head :title="`${brand.name} — Masuk`" />

    <div
        class="min-h-screen bg-gradient-to-br from-slate-950 via-slate-900 to-cyan-950 px-4 py-10 font-sans text-white sm:px-6 lg:py-16"
    >
        <div class="mx-auto max-w-6xl">
            <header class="flex flex-col items-center text-center">
                <div
                    class="flex h-16 w-16 items-center justify-center rounded-3xl bg-gradient-to-br from-cyan-400 to-sky-600 text-3xl shadow-xl shadow-cyan-500/30"
                >
                    {{ brand.logo }}
                </div>
                <h1 class="mt-5 flex flex-col items-center gap-1">
                    <span class="text-2xl font-semibold sm:text-3xl">
                        {{ brand.name }}
                    </span>
                    <span
                        class="text-base font-medium text-slate-300 sm:text-lg"
                    >
                        {{ brand.system.replace(brand.name, '').trim() }}
                    </span>
                </h1>
                <p class="mt-2 max-w-lg text-sm text-slate-400">
                    Pilih role untuk masuk ke konsol admin, atau buka aplikasi
                    customer.
                </p>
            </header>

            <!-- Staff roles -->
            <section class="mt-10">
                <h2
                    class="text-[11px] font-semibold tracking-wider text-slate-500 uppercase"
                >
                    Aplikasi Admin — pilih role
                </h2>

                <div
                    class="mt-4 grid grid-cols-1 gap-4 sm:grid-cols-2 xl:grid-cols-3"
                >
                    <button
                        v-for="role in roles"
                        :key="role.key"
                        type="button"
                        class="group relative overflow-hidden rounded-2xl bg-white/5 p-5 text-left ring-1 ring-white/10 transition hover:-translate-y-0.5 hover:bg-white/10 hover:ring-white/20 disabled:opacity-60"
                        :disabled="submitting !== null"
                        @click="signInAs(role.key)"
                    >
                        <div
                            class="absolute -top-16 -right-10 h-32 w-32 rounded-full opacity-20 blur-2xl transition group-hover:opacity-40"
                            :style="{ backgroundColor: role.accent }"
                        ></div>

                        <div class="relative">
                            <div class="flex items-center justify-between">
                                <span
                                    class="flex h-11 w-11 items-center justify-center rounded-2xl text-xl"
                                    :style="{
                                        backgroundColor: `${role.accent}2e`,
                                        boxShadow: `inset 0 0 0 1px ${role.accent}66`,
                                    }"
                                >
                                    {{ role.icon }}
                                </span>
                                <span
                                    v-if="activeRole === role.key"
                                    class="rounded-full bg-emerald-500/15 px-2 py-0.5 text-[10px] font-medium text-emerald-300 ring-1 ring-emerald-400/30"
                                >
                                    Sesi aktif
                                </span>
                            </div>

                            <p class="mt-4 text-base font-semibold">
                                {{ role.name }}
                            </p>
                            <p
                                class="mt-1 text-xs leading-relaxed text-slate-400"
                            >
                                {{ role.description }}
                            </p>

                            <div class="mt-4 flex flex-wrap gap-1">
                                <span
                                    v-for="moduleKey in matrix[role.key]"
                                    :key="moduleKey"
                                    class="rounded-md bg-white/5 px-1.5 py-0.5 text-[10px] text-slate-400 ring-1 ring-white/10"
                                >
                                    {{
                                        modules.find(
                                            (item) => item.key === moduleKey,
                                        )?.label
                                    }}
                                </span>
                            </div>

                            <p
                                class="mt-4 flex items-center gap-1 text-xs font-medium text-cyan-300"
                            >
                                {{
                                    submitting === role.key
                                        ? 'Membuka konsol…'
                                        : 'Masuk sebagai role ini'
                                }}
                                <ArrowRight class="h-3.5 w-3.5" />
                            </p>
                        </div>
                    </button>

                    <!-- Customer portal -->
                    <Link
                        :href="member.login.url()"
                        class="group relative overflow-hidden rounded-2xl bg-gradient-to-br from-cyan-500/20 to-sky-600/10 p-5 ring-1 ring-cyan-400/30 transition hover:-translate-y-0.5 hover:ring-cyan-300/50"
                    >
                        <div
                            class="absolute -top-16 -right-10 h-32 w-32 rounded-full bg-cyan-400/30 blur-2xl"
                        ></div>
                        <div class="relative">
                            <span
                                class="flex h-11 w-11 items-center justify-center rounded-2xl bg-cyan-400/20 ring-1 ring-cyan-300/40"
                            >
                                <Smartphone class="h-5 w-5 text-cyan-200" />
                            </span>
                            <p class="mt-4 text-base font-semibold">
                                Aplikasi Customer
                            </p>
                            <p
                                class="mt-1 text-xs leading-relaxed text-cyan-100/70"
                            >
                                Portal member: kartu stempel, katalog layanan,
                                dan informasi reward.
                            </p>
                            <p
                                class="mt-4 flex items-center gap-1 text-xs font-medium text-cyan-200"
                            >
                                Buka portal customer
                                <ArrowRight class="h-3.5 w-3.5" />
                            </p>
                        </div>
                    </Link>
                </div>
            </section>

            <!-- Access matrix -->
            <section class="mt-12">
                <h2
                    class="text-[11px] font-semibold tracking-wider text-slate-500 uppercase"
                >
                    Matriks hak akses
                </h2>
                <div
                    class="mt-4 overflow-x-auto rounded-2xl bg-white/5 ring-1 ring-white/10"
                >
                    <table class="w-full min-w-[720px] text-sm">
                        <thead>
                            <tr class="border-b border-white/10 text-left">
                                <th
                                    class="px-4 py-3 text-[11px] font-medium tracking-wider text-slate-400 uppercase"
                                >
                                    Modul
                                </th>
                                <th
                                    v-for="role in roles"
                                    :key="role.key"
                                    class="px-4 py-3 text-center text-[11px] font-medium tracking-wider text-slate-400 uppercase"
                                >
                                    {{ role.name }}
                                </th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-white/5">
                            <tr
                                v-for="module in modules"
                                :key="module.key"
                                class="transition hover:bg-white/5"
                            >
                                <td class="px-4 py-2.5 text-slate-300">
                                    {{ module.label }}
                                </td>
                                <td
                                    v-for="role in roles"
                                    :key="role.key"
                                    class="px-4 py-2.5 text-center"
                                >
                                    <span
                                        v-if="
                                            matrix[role.key].includes(
                                                module.key,
                                            )
                                        "
                                        class="mx-auto flex h-6 w-6 items-center justify-center rounded-full bg-emerald-400/15 text-emerald-300 ring-1 ring-emerald-400/30"
                                        title="Bisa diakses"
                                    >
                                        <Check
                                            class="h-3.5 w-3.5"
                                            :stroke-width="3"
                                        />
                                    </span>
                                    <span
                                        v-else
                                        class="mx-auto flex h-6 w-6 items-center justify-center rounded-full bg-rose-500/15 text-rose-300 ring-1 ring-rose-400/30"
                                        title="Tidak bisa diakses"
                                    >
                                        <X
                                            class="h-3.5 w-3.5"
                                            :stroke-width="3"
                                        />
                                    </span>
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>
                <p class="mt-3 text-[11px] text-slate-500">
                    Prototipe demo — role disimpan di session, bukan database.
                    Modul di luar hak akses akan menolak dengan HTTP 403.
                </p>
            </section>
        </div>
    </div>
</template>
