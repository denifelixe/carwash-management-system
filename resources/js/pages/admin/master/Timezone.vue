<script setup lang="ts">
import { Head, useForm } from '@inertiajs/vue3';
import { Check, Clock3, Globe, MapPin, TriangleAlert } from '@lucide/vue';
import { computed, onBeforeUnmount, onMounted, ref } from 'vue';
import { update as updateTimezone } from '@/actions/App/Http/Controllers/Admin/Master/TimezoneController';
import InputError from '@/components/InputError.vue';
import type { CarwashBrand } from '@/types/demo';

interface TimezoneOption {
    id: string;
    code: string;
    name: string;
    offset: string;
    cities: string;
    clock: string;
}

const props = defineProps<{
    mode: 'demo' | 'live';
    brand: CarwashBrand;
    timezone: string;
    timezones: TimezoneOption[];
    capabilities: { update: boolean };
}>();

const timezoneForm = useForm({ timezone: props.timezone });

/**
 * The clock on each card runs live, so the owner recognises their own time
 * before choosing rather than trusting a label.
 */
const now = ref(new Date());
let ticker: ReturnType<typeof setInterval> | undefined;

onMounted(() => {
    ticker = setInterval(() => {
        now.value = new Date();
    }, 1000);
});

onBeforeUnmount(() => {
    if (ticker !== undefined) {
        clearInterval(ticker);
    }
});

function clockIn(timeZone: string): string {
    return new Intl.DateTimeFormat('id-ID', {
        hour: '2-digit',
        minute: '2-digit',
        second: '2-digit',
        hour12: false,
        timeZone,
    }).format(now.value);
}

const activeZone = computed(
    () => props.timezones.find((zone) => zone.id === props.timezone) ?? null,
);

const isDirty = computed(() => timezoneForm.timezone !== props.timezone);

function submitTimezone(): void {
    if (!props.capabilities.update || !isDirty.value) {
        return;
    }

    timezoneForm.submit(updateTimezone(), { preserveScroll: true });
}
</script>

<template>
    <Head :title="`${brand.name} — Timezone`" />

    <div class="space-y-4">
        <section
            class="rounded-2xl border border-slate-200/80 bg-white p-5 shadow-sm"
        >
            <div class="flex flex-wrap items-start justify-between gap-4">
                <div class="flex items-start gap-3">
                    <span
                        class="flex size-11 shrink-0 items-center justify-center rounded-xl bg-cyan-50 text-cyan-600"
                    >
                        <Globe class="size-5" />
                    </span>
                    <div>
                        <h2 class="text-base font-semibold text-slate-900">
                            Zona waktu operasional
                        </h2>
                        <p class="mt-0.5 text-sm text-slate-500">
                            Seluruh jam order, pembayaran, dan kas dicatat
                            memakai zona ini.
                        </p>
                    </div>
                </div>

                <div
                    v-if="activeZone"
                    class="rounded-xl border border-slate-200 bg-slate-50/80 px-4 py-2.5 text-right"
                >
                    <p
                        class="text-[11px] font-medium tracking-wide text-slate-500 uppercase"
                    >
                        Waktu sekarang · {{ activeZone.code }}
                    </p>
                    <p
                        class="font-mono text-xl leading-tight font-semibold text-slate-900 tabular-nums"
                    >
                        {{ clockIn(activeZone.id) }}
                    </p>
                </div>
            </div>
        </section>

        <section
            class="rounded-2xl border border-slate-200/80 bg-white shadow-sm"
        >
            <div class="border-b border-slate-100 p-5">
                <h3 class="text-sm font-semibold text-slate-900">
                    Pilih zona waktu
                </h3>
                <p class="mt-0.5 text-sm text-slate-500">
                    Pilih zona sesuai lokasi outlet.
                </p>
            </div>

            <div class="grid grid-cols-1 gap-3 p-5 sm:grid-cols-3">
                <label
                    v-for="zone in timezones"
                    :key="zone.id"
                    class="relative flex cursor-pointer flex-col gap-3 rounded-xl border p-4 transition"
                    :class="[
                        timezoneForm.timezone === zone.id
                            ? 'border-cyan-300 bg-cyan-50/60 ring-2 ring-cyan-500/15'
                            : 'border-slate-200 bg-white hover:border-slate-300',
                        capabilities.update
                            ? ''
                            : 'cursor-not-allowed opacity-70',
                    ]"
                >
                    <input
                        v-model="timezoneForm.timezone"
                        type="radio"
                        name="timezone"
                        :value="zone.id"
                        :disabled="!capabilities.update"
                        class="sr-only"
                    />

                    <div class="flex items-start justify-between gap-2">
                        <div>
                            <p
                                class="text-lg font-semibold"
                                :class="
                                    timezoneForm.timezone === zone.id
                                        ? 'text-cyan-700'
                                        : 'text-slate-900'
                                "
                            >
                                {{ zone.code }}
                            </p>
                            <p class="text-xs text-slate-500">{{ zone.name }}</p>
                        </div>
                        <span
                            v-if="timezoneForm.timezone === zone.id"
                            class="flex size-5 shrink-0 items-center justify-center rounded-full bg-cyan-500 text-white"
                        >
                            <Check class="size-3" />
                        </span>
                    </div>

                    <p
                        class="font-mono text-2xl leading-none font-semibold text-slate-900 tabular-nums"
                    >
                        {{ clockIn(zone.id) }}
                    </p>

                    <div class="space-y-1.5 text-xs text-slate-500">
                        <p class="flex items-center gap-1.5">
                            <Clock3 class="size-3.5 shrink-0" />
                            {{ zone.offset }} · {{ zone.id }}
                        </p>
                        <p class="flex items-start gap-1.5">
                            <MapPin class="mt-px size-3.5 shrink-0" />
                            {{ zone.cities }}
                        </p>
                    </div>
                </label>
            </div>

            <div class="px-5">
                <InputError :message="timezoneForm.errors.timezone" />
            </div>

            <div
                class="flex flex-wrap items-center justify-between gap-3 border-t border-slate-100 p-5"
            >
                <p class="flex items-start gap-1.5 text-xs text-amber-700">
                    <TriangleAlert class="mt-px size-3.5 shrink-0" />
                    Mengubah zona hanya memengaruhi transaksi berikutnya. Jam
                    yang sudah tercatat tidak digeser.
                </p>

                <button
                    v-if="capabilities.update"
                    type="button"
                    :disabled="!isDirty || timezoneForm.processing"
                    class="rounded-xl bg-gradient-to-r from-cyan-500 to-sky-500 px-4 py-2 text-sm font-semibold text-white shadow-sm transition hover:from-cyan-600 hover:to-sky-600 disabled:cursor-not-allowed disabled:opacity-50"
                    @click="submitTimezone"
                >
                    {{
                        timezoneForm.processing
                            ? 'Menyimpan…'
                            : 'Simpan zona waktu'
                    }}
                </button>
            </div>
        </section>
    </div>
</template>
