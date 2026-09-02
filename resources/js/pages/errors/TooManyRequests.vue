<script setup lang="ts">
import { Head, router } from '@inertiajs/vue3';
import { Clock3, Mail, RotateCcw, ShieldCheck } from '@lucide/vue';
import { computed, onBeforeUnmount, onMounted, ref } from 'vue';
import { Button } from '@/components/ui/button';

const props = defineProps<{
    email: string;
    retryAfter: number;
    returnUrl: string;
}>();

const initialWait = Math.max(Math.ceil(props.retryAfter), 1);
const remainingSeconds = ref(initialWait);
let countdownTimer: number | undefined;

const canRetry = computed(() => remainingSeconds.value === 0);
const countdown = computed(() => {
    const minutes = Math.floor(remainingSeconds.value / 60);
    const seconds = remainingSeconds.value % 60;

    return `${String(minutes).padStart(2, '0')}:${String(seconds).padStart(2, '0')}`;
});
const progress = computed(() =>
    Math.max(0, (remainingSeconds.value / initialWait) * 100),
);

onMounted(() => {
    countdownTimer = window.setInterval(() => {
        if (remainingSeconds.value <= 1) {
            remainingSeconds.value = 0;
            window.clearInterval(countdownTimer);

            return;
        }

        remainingSeconds.value -= 1;
    }, 1000);
});

onBeforeUnmount(() => {
    window.clearInterval(countdownTimer);
});

function returnToLogin(): void {
    if (canRetry.value) {
        router.visit(props.returnUrl, { replace: true });
    }
}
</script>

<template>
    <Head title="Terlalu Banyak Percobaan" />

    <div
        class="relative grid min-h-svh place-items-center overflow-hidden bg-slate-50 px-4 py-10 sm:px-6"
    >
        <div
            aria-hidden="true"
            class="absolute -top-32 -left-24 size-72 rounded-full bg-cyan-200/50 blur-3xl"
        />
        <div
            aria-hidden="true"
            class="absolute -right-24 -bottom-32 size-80 rounded-full bg-sky-200/60 blur-3xl"
        />

        <main
            class="relative w-full max-w-md overflow-hidden rounded-3xl border border-white/80 bg-white/90 shadow-2xl shadow-slate-300/40 backdrop-blur"
        >
            <div class="h-1.5 bg-slate-100">
                <div
                    class="h-full bg-gradient-to-r from-cyan-500 to-sky-600 transition-[width] duration-1000 ease-linear"
                    :style="{ width: `${progress}%` }"
                />
            </div>

            <div class="p-6 text-center sm:p-8">
                <span
                    class="inline-flex items-center gap-1.5 rounded-full bg-emerald-50 px-3 py-1.5 text-xs font-medium text-emerald-700 ring-1 ring-emerald-100"
                >
                    <ShieldCheck class="size-4" />
                    Perlindungan akun
                </span>

                <div
                    class="mx-auto mt-6 grid size-20 place-items-center rounded-3xl bg-gradient-to-br from-cyan-50 to-sky-100 text-sky-600 shadow-inner ring-1 ring-sky-100"
                >
                    <Clock3 class="size-9" :stroke-width="1.8" />
                </div>

                <h1
                    class="mt-6 text-2xl font-bold tracking-tight text-slate-900 sm:text-3xl"
                >
                    Terlalu banyak percobaan
                </h1>
                <p
                    class="mx-auto mt-3 max-w-sm text-sm leading-6 text-slate-500"
                >
                    Akses masuk dihentikan sementara untuk menjaga keamanan
                    akun. Tunggu hingga penghitung selesai sebelum mencoba
                    kembali.
                </p>

                <div
                    class="mt-6 flex items-center gap-3 rounded-2xl border border-slate-200 bg-white px-4 py-3 text-left shadow-sm"
                >
                    <span
                        class="grid size-10 shrink-0 place-items-center rounded-xl bg-slate-100 text-slate-500"
                    >
                        <Mail class="size-5" />
                    </span>
                    <div class="min-w-0">
                        <p class="text-xs font-medium text-slate-400">
                            Percobaan masuk untuk
                        </p>
                        <p
                            class="mt-0.5 text-sm font-semibold break-all text-slate-800"
                        >
                            {{ email }}
                        </p>
                    </div>
                </div>

                <div
                    class="mt-4 rounded-2xl border border-slate-200 bg-slate-50 px-5 py-4"
                >
                    <p
                        class="text-xs font-semibold tracking-widest text-slate-400 uppercase"
                    >
                        Coba lagi dalam
                    </p>
                    <p
                        class="mt-1 font-mono text-4xl font-bold tracking-tight text-slate-900 tabular-nums"
                        aria-live="polite"
                    >
                        {{ countdown }}
                    </p>
                </div>

                <Button
                    type="button"
                    size="lg"
                    class="mt-6 w-full rounded-xl"
                    :disabled="!canRetry"
                    @click="returnToLogin"
                >
                    <RotateCcw class="size-4" />
                    {{
                        canRetry
                            ? 'Kembali ke halaman masuk'
                            : 'Mohon tunggu sebentar'
                    }}
                </Button>

                <p class="mt-4 text-xs leading-5 text-slate-400">
                    Pastikan email dan kata sandi sudah benar agar pembatasan
                    tidak terpicu kembali.
                </p>
            </div>
        </main>
    </div>
</template>
