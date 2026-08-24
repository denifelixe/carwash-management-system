<script setup lang="ts">
import { computed } from 'vue';

const props = defineProps<{
    from: string;
    to: string;
    /** Latest selectable day — the prototype's fixed "today". */
    today: string;
    earliest: string;
}>();

const emit = defineEmits<{
    change: [range: { from: string; to: string }];
}>();

interface RangePreset {
    key: string;
    label: string;
    days: number;
}

const presets: RangePreset[] = [
    { key: 'week', label: '7 hari', days: 7 },
    { key: 'month', label: '30 hari', days: 30 },
    { key: 'quarter', label: '3 bulan', days: 90 },
    { key: 'year', label: '12 bulan', days: 365 },
];

/**
 * Day arithmetic in UTC. Parsing "2026-08-03" as local time would land on the
 * previous day for anyone west of UTC and shift every preset by one.
 */
function shiftDays(date: string, days: number): string {
    const moment = new Date(`${date}T00:00:00Z`);

    moment.setUTCDate(moment.getUTCDate() + days);

    return moment.toISOString().slice(0, 10);
}

function rangeFor(preset: RangePreset): { from: string; to: string } {
    return {
        from: shiftDays(props.today, -(preset.days - 1)),
        to: props.today,
    };
}

const activePreset = computed<string | null>(
    () =>
        presets.find((preset) => {
            const range = rangeFor(preset);

            return range.from === props.from && range.to === props.to;
        })?.key ?? null,
);

/** Editing one end drags the other along rather than emitting an inverted range. */
function changeFrom(value: string): void {
    if (value !== '') {
        emit('change', {
            from: value,
            to: value > props.to ? value : props.to,
        });
    }
}

function changeTo(value: string): void {
    if (value !== '') {
        emit('change', {
            from: value < props.from ? value : props.from,
            to: value,
        });
    }
}
</script>

<template>
    <div class="flex flex-wrap items-center gap-2">
        <div
            class="flex flex-wrap gap-1 rounded-xl bg-slate-200/70 p-1 text-sm"
        >
            <button
                v-for="preset in presets"
                :key="preset.key"
                type="button"
                class="rounded-lg px-3 py-1.5 font-medium transition"
                :class="
                    activePreset === preset.key
                        ? 'bg-white text-slate-900 shadow-sm'
                        : 'text-slate-500 hover:text-slate-700'
                "
                @click="emit('change', rangeFor(preset))"
            >
                {{ preset.label }}
            </button>
        </div>

        <div
            class="flex items-center gap-2 rounded-xl border border-slate-200 bg-white px-3 py-1.5"
        >
            <label class="sr-only" for="report-from">Dari tanggal</label>
            <input
                id="report-from"
                type="date"
                :value="from"
                :min="earliest"
                :max="today"
                class="bg-transparent text-sm text-slate-700 tabular-nums focus:outline-none"
                @change="changeFrom(($event.target as HTMLInputElement).value)"
            />
            <span class="text-slate-300">–</span>
            <label class="sr-only" for="report-to">Sampai tanggal</label>
            <input
                id="report-to"
                type="date"
                :value="to"
                :min="earliest"
                :max="today"
                class="bg-transparent text-sm text-slate-700 tabular-nums focus:outline-none"
                @change="changeTo(($event.target as HTMLInputElement).value)"
            />
        </div>
    </div>
</template>
