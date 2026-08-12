<script setup lang="ts">
import { Head, Link } from '@inertiajs/vue3';
import {
    Car,
    CircleCheck,
    Copy,
    Mail,
    Phone,
    Sparkles,
    Users,
} from '@lucide/vue';
import { ref } from 'vue';
import StampProgress from '@/components/carwash/StampProgress.vue';
import {
    formatNumber,
    formatShortCurrency,
} from '@/composables/useCarwashFormat';
import carwash from '@/routes/carwash';
import type {
    CarwashBrand,
    CarwashMember,
    CarwashVoucher,
    CarwashWashEntry,
} from '@/types/carwash';

const props = defineProps<{
    brand: CarwashBrand;
    member: CarwashMember;
    washHistory: CarwashWashEntry[];
    vouchers: CarwashVoucher[];
}>();

const isCodeCopied = ref<boolean>(false);

function copyReferralCode(): void {
    navigator.clipboard?.writeText(props.member.referralCode);
    isCodeCopied.value = true;
    window.setTimeout(() => {
        isCodeCopied.value = false;
    }, 1800);
}
</script>

<template>
    <Head :title="`${brand.name} — Profil`" />

    <div class="space-y-5 px-5 py-5">
        <!-- Identity -->
        <section
            class="flex items-center gap-4 rounded-2xl border border-slate-200 bg-white p-5"
        >
            <div
                class="flex h-16 w-16 items-center justify-center rounded-2xl bg-gradient-to-br from-cyan-500 to-sky-600 text-xl font-bold text-white shadow-lg shadow-cyan-500/25"
            >
                {{ member.initials }}
            </div>
            <div class="min-w-0 flex-1">
                <p class="text-base font-semibold text-slate-900">
                    {{ member.name }}
                </p>
                <p class="flex items-center gap-1 text-xs text-slate-500">
                    <Phone class="h-3 w-3" />
                    {{ member.phone }}
                </p>
                <p class="flex items-center gap-1 text-xs text-slate-500">
                    <Mail class="h-3 w-3" />
                    {{ member.email }}
                </p>
                <span
                    class="mt-1.5 inline-flex items-center gap-1 rounded-full bg-cyan-50 px-2 py-0.5 text-[11px] font-semibold text-cyan-700"
                >
                    <Sparkles class="h-3 w-3" />
                    {{ member.memberId }}
                </span>
            </div>
        </section>

        <!-- Loyalty summary -->
        <section class="rounded-2xl border border-slate-200 bg-white p-5">
            <div class="flex items-start justify-between">
                <div>
                    <h2 class="text-sm font-semibold text-slate-900">
                        Ringkasan loyalty
                    </h2>
                    <p class="mt-0.5 text-xs text-slate-500">
                        Member sejak {{ member.joinedAt }}
                    </p>
                </div>
                <span
                    class="rounded-full bg-cyan-50 px-2.5 py-1 text-xs font-semibold text-cyan-700"
                >
                    {{ member.stamps }}/{{ brand.stampTarget }}
                </span>
            </div>

            <div class="mt-4">
                <StampProgress
                    :stamps="member.stamps"
                    :target="brand.stampTarget"
                    compact
                />
            </div>

            <div class="mt-4 grid grid-cols-3 gap-3">
                <div class="rounded-xl bg-slate-50 p-3">
                    <p class="text-[11px] text-slate-500">Kunjungan</p>
                    <p
                        class="mt-0.5 text-lg font-semibold text-slate-900 tabular-nums"
                    >
                        {{ member.visits }}
                    </p>
                </div>
                <div class="rounded-xl bg-slate-50 p-3">
                    <p class="text-[11px] text-slate-500">Total stempel</p>
                    <p
                        class="mt-0.5 text-lg font-semibold text-slate-900 tabular-nums"
                    >
                        {{ formatNumber(member.lifetimeStamps) }}
                    </p>
                </div>
                <div class="rounded-xl bg-slate-50 p-3">
                    <p class="text-[11px] text-slate-500">Total belanja</p>
                    <p
                        class="mt-0.5 text-lg font-semibold text-slate-900 tabular-nums"
                    >
                        {{ formatShortCurrency(member.spend) }}
                    </p>
                </div>
            </div>
        </section>

        <!-- Vehicles -->
        <section>
            <h2 class="text-sm font-semibold text-slate-900">Kendaraan saya</h2>
            <ul class="mt-3 space-y-2">
                <li
                    v-for="vehicle in member.vehicles"
                    :key="vehicle.plate"
                    class="flex items-center gap-3 rounded-2xl border border-slate-200 bg-white p-3.5"
                >
                    <div
                        class="flex h-10 w-10 items-center justify-center rounded-xl bg-slate-50 text-slate-500"
                    >
                        <Car class="h-5 w-5" />
                    </div>
                    <div class="min-w-0 flex-1">
                        <p class="text-sm font-medium text-slate-800">
                            {{ vehicle.name }}
                        </p>
                        <p class="text-[11px] text-slate-500">
                            {{ vehicle.plate }} • {{ vehicle.type }}
                        </p>
                    </div>
                    <span
                        v-if="vehicle.isPrimary"
                        class="rounded-full bg-cyan-50 px-2 py-0.5 text-[10px] font-medium text-cyan-700"
                    >
                        Utama
                    </span>
                </li>
            </ul>
        </section>

        <!-- Referral -->
        <section
            class="relative overflow-hidden rounded-2xl bg-gradient-to-br from-violet-600 to-indigo-600 p-5 text-white"
        >
            <div
                class="absolute -top-10 -right-8 h-32 w-32 rounded-full bg-white/10 blur-2xl"
            ></div>
            <div class="relative">
                <p class="flex items-center gap-1.5 text-sm font-semibold">
                    <Users class="h-4 w-4" />
                    Ajak teman, dapat 1 stempel bonus
                </p>
                <p class="mt-1 text-xs text-white/80">
                    Bagikan kode referral kamu. Teman dapat diskon 20% untuk
                    cuci pertama.
                </p>
                <button
                    type="button"
                    class="mt-4 flex w-full items-center justify-between gap-2 rounded-xl bg-white/15 px-4 py-3 ring-1 ring-white/25 transition hover:bg-white/25"
                    @click="copyReferralCode"
                >
                    <span class="font-mono text-sm tracking-widest">
                        {{ member.referralCode }}
                    </span>
                    <span
                        class="flex items-center gap-1 text-[11px] font-medium"
                    >
                        <component
                            :is="isCodeCopied ? CircleCheck : Copy"
                            class="h-3.5 w-3.5"
                        />
                        {{ isCodeCopied ? 'Tersalin' : 'Salin' }}
                    </span>
                </button>
            </div>
        </section>

        <!-- Account actions -->
        <section class="space-y-2">
            <a
                :href="`https://wa.me/${brand.whatsapp}`"
                target="_blank"
                rel="noopener"
                class="flex w-full items-center justify-center gap-2 rounded-2xl bg-emerald-600 py-3.5 text-sm font-semibold text-white transition hover:bg-emerald-700"
            >
                <Phone class="h-4 w-4" />
                Hubungi Customer Service
            </a>
            <Link
                :href="carwash.entry.url()"
                class="flex w-full items-center justify-center gap-2 rounded-2xl border border-slate-200 bg-white py-3.5 text-sm font-semibold text-slate-600 transition hover:bg-slate-50"
            >
                Keluar
            </Link>
        </section>

        <p class="text-center text-[11px] text-slate-400">
            {{ washHistory.length }} kunjungan tercatat •
            {{ vouchers.length }} voucher
        </p>
    </div>
</template>
