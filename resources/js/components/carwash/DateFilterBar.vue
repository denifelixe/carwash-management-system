<script setup lang="ts">
import { CalendarDays } from '@lucide/vue';
import { computed } from 'vue';
import type { CarwashDateFilter } from '@/types/carwash';

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

            <label class="sr-only" for="filter-date">Tanggal</label>
            <input
                id="filter-date"
                type="date"
                :value="filters.date"
                :min="filters.earliest"
                :max="latest"
                class="rounded-xl border border-slate-200 px-3 py-1.5 text-sm text-slate-700 tabular-nums focus:border-cyan-400 focus:outline-none"
                @change="
                    emit('change', ($event.target as HTMLInputElement).value)
                "
            />
        </div>
    </section>
</template>
