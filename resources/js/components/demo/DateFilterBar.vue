<script setup lang="ts">
import { CalendarDays } from '@lucide/vue';
import { computed, ref } from 'vue';
import type { CarwashDateFilter } from '@/types/demo';

const props = withDefaults(
    defineProps<{
        filters: CarwashDateFilter;
        /** Schedules are picked ahead of today; transactions never are. */
        allowFuture?: boolean;
    }>(),
    { allowFuture: false },
);

const emit = defineEmits<{
    change: [date: string];
}>();

const latest = computed<string>(() =>
    props.allowFuture ? props.filters.latest : props.filters.today,
);

const dateInput = ref<HTMLInputElement | null>(null);

const displayDate = computed<string>(() => {
    const [year, month, day] = props.filters.date.split('-');

    if (!year || !month || !day) {
        return 'Pilih tanggal';
    }

    return `${day}/${month}/${year}`;
});

function openDatePicker(): void {
    const input = dateInput.value;

    if (!input) {
        return;
    }

    if (typeof input.showPicker === 'function') {
        try {
            input.showPicker();

            return;
        } catch {
            input.click();
        }

        return;
    }

    input.click();
}
</script>

<template>
    <section
        class="flex flex-wrap items-center justify-between gap-3 rounded-2xl border border-slate-200/80 bg-white p-3 shadow-sm"
    >
        <p class="flex items-center gap-2 px-1 text-sm text-slate-500">
            <CalendarDays class="h-4 w-4 text-slate-400" />
            <span class="font-medium text-slate-800">{{ filters.label }}</span>
        </p>

        <div class="flex flex-wrap items-center gap-2">
            <button
                v-if="filters.date !== filters.today"
                type="button"
                class="rounded-lg px-3 py-1.5 text-sm font-medium text-cyan-700 transition hover:bg-cyan-50"
                @click="emit('change', filters.today)"
            >
                Kembali ke Hari Ini
            </button>

            <div class="relative">
                <button
                    type="button"
                    aria-haspopup="dialog"
                    :aria-label="`Pilih tanggal, ${filters.label}`"
                    class="flex cursor-pointer items-center gap-3 rounded-xl border border-slate-200 px-3 py-1.5 text-sm text-slate-700 transition select-none hover:bg-slate-50 focus-visible:border-cyan-400 focus-visible:ring-2 focus-visible:ring-cyan-100 focus-visible:outline-none"
                    @click="openDatePicker"
                >
                    <span class="tabular-nums">{{ displayDate }}</span>
                    <CalendarDays
                        aria-hidden="true"
                        class="h-4 w-4 text-slate-600"
                    />
                </button>

                <input
                    id="filter-date"
                    ref="dateInput"
                    type="date"
                    tabindex="-1"
                    aria-hidden="true"
                    :value="filters.date"
                    :max="latest"
                    class="pointer-events-none absolute right-0 bottom-0 h-px w-px opacity-0"
                    @change="
                        emit(
                            'change',
                            ($event.target as HTMLInputElement).value,
                        )
                    "
                />
            </div>
        </div>
    </section>
</template>
