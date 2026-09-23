<script setup lang="ts">
import { CalendarDays } from '@lucide/vue';
import { computed, onMounted, onUnmounted, ref } from 'vue';
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

const displayDate = computed<string>(() => {
    const [year, month, day] = props.filters.date.split('-');

    if (!year || !month || !day) {
        return 'Pilih tanggal';
    }

    return `${day}/${month}/${year}`;
});

const barElement = ref<HTMLElement | null>(null);
const isStuck = ref<boolean>(false);
let pendingFrame: number | undefined;

/** The bar is stuck once its top has reached the header's bottom edge. */
function updateStuckState(): void {
    pendingFrame = undefined;

    if (!barElement.value) {
        return;
    }

    const headerHeight =
        parseFloat(
            getComputedStyle(barElement.value).getPropertyValue(
                '--admin-header-height',
            ),
        ) || 0;

    isStuck.value =
        window.scrollY > 0 &&
        barElement.value.getBoundingClientRect().top <= headerHeight + 1;
}

function scheduleStuckCheck(): void {
    if (pendingFrame === undefined) {
        pendingFrame = requestAnimationFrame(updateStuckState);
    }
}

onMounted(() => {
    window.addEventListener('scroll', scheduleStuckCheck, { passive: true });
    window.addEventListener('resize', scheduleStuckCheck, { passive: true });
    updateStuckState();
});

onUnmounted(() => {
    window.removeEventListener('scroll', scheduleStuckCheck);
    window.removeEventListener('resize', scheduleStuckCheck);

    if (pendingFrame !== undefined) {
        cancelAnimationFrame(pendingFrame);
    }
});
</script>

<template>
    <!--
        Sticks flush under the admin header (AdminLayout publishes its height as
        --admin-header-height). Once stuck it bleeds out of <main>'s padding
        (px-4 sm:px-6 lg:px-8) so it reads as one bar with the header.
    -->
    <section
        ref="barElement"
        class="sticky top-[var(--admin-header-height,4.5rem)] z-20 flex flex-wrap items-center justify-between gap-3 border-slate-200/80 py-3"
        :class="
            isStuck
                ? '-mx-4 border border-x-0 border-t-transparent bg-white/85 px-4 shadow-sm backdrop-blur-xl sm:-mx-6 sm:px-6 lg:-mx-8 lg:px-8'
                : 'rounded-2xl border bg-white px-3 shadow-sm'
        "
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

            <div
                class="relative rounded-xl border border-slate-200 text-slate-700 transition focus-within:border-cyan-400 focus-within:ring-2 focus-within:ring-cyan-100 hover:bg-slate-50"
            >
                <span
                    aria-hidden="true"
                    class="flex items-center gap-3 px-3 py-1.5 text-sm select-none"
                >
                    <span class="tabular-nums">{{ displayDate }}</span>
                    <CalendarDays
                        aria-hidden="true"
                        class="h-4 w-4 text-slate-600"
                    />
                </span>

                <input
                    id="filter-date"
                    type="date"
                    :aria-label="`Pilih tanggal, ${filters.label}`"
                    :value="filters.date"
                    :max="latest"
                    class="absolute inset-0 h-full w-full cursor-pointer text-base opacity-0 [&::-webkit-calendar-picker-indicator]:absolute [&::-webkit-calendar-picker-indicator]:inset-0 [&::-webkit-calendar-picker-indicator]:h-full [&::-webkit-calendar-picker-indicator]:w-full [&::-webkit-calendar-picker-indicator]:cursor-pointer"
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
