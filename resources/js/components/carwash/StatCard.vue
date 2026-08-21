<script setup lang="ts">
import { Check, Minus, TrendingDown, TrendingUp } from '@lucide/vue';
import type { LucideIcon } from '@lucide/vue';
import { computed } from 'vue';

type Tone = 'default' | 'emerald' | 'rose' | 'amber' | 'violet' | 'slate';

const props = defineProps<{
    label: string;
    value: string;
    caption?: string;
    delta?: number | null;
    trend?: 'up' | 'down' | 'flat';
    comparisonLabel?: string;
    icon?: LucideIcon;
    tone?: Tone;
    interactive?: boolean;
    active?: boolean;
}>();

defineEmits<{
    click: [];
}>();

/**
 * A card that is switched on wears its own colour rather than a neutral
 * outline, so a glance at the row says which filter is in force.
 */
type ToneStyle = {
    /** Icon chip while the card sits idle, and once it is switched on. */
    chip: string;
    activeChip: string;
    /** Surface, border, and glow of a switched-on card. */
    surface: string;
    /** The label and the check badge that mark it as chosen. */
    accent: string;
};

const toneStyles: Record<Tone, ToneStyle> = {
    default: {
        chip: 'bg-cyan-50 text-cyan-600',
        activeChip: 'bg-cyan-500 text-white shadow-sm shadow-cyan-500/40',
        surface:
            'border-cyan-300 bg-cyan-50/60 ring-2 ring-cyan-500/15 shadow-lg shadow-cyan-500/10',
        accent: 'text-cyan-700',
    },
    emerald: {
        chip: 'bg-emerald-50 text-emerald-600',
        activeChip: 'bg-emerald-500 text-white shadow-sm shadow-emerald-500/40',
        surface:
            'border-emerald-300 bg-emerald-50/60 ring-2 ring-emerald-500/15 shadow-lg shadow-emerald-500/10',
        accent: 'text-emerald-700',
    },
    rose: {
        chip: 'bg-rose-50 text-rose-600',
        activeChip: 'bg-rose-500 text-white shadow-sm shadow-rose-500/40',
        surface:
            'border-rose-300 bg-rose-50/60 ring-2 ring-rose-500/15 shadow-lg shadow-rose-500/10',
        accent: 'text-rose-700',
    },
    amber: {
        chip: 'bg-amber-50 text-amber-600',
        activeChip: 'bg-amber-500 text-white shadow-sm shadow-amber-500/40',
        surface:
            'border-amber-300 bg-amber-50/60 ring-2 ring-amber-500/15 shadow-lg shadow-amber-500/10',
        accent: 'text-amber-700',
    },
    violet: {
        chip: 'bg-violet-50 text-violet-600',
        activeChip: 'bg-violet-500 text-white shadow-sm shadow-violet-500/40',
        surface:
            'border-violet-300 bg-violet-50/60 ring-2 ring-violet-500/15 shadow-lg shadow-violet-500/10',
        accent: 'text-violet-700',
    },
    slate: {
        chip: 'bg-slate-100 text-slate-500',
        activeChip: 'bg-slate-700 text-white shadow-sm shadow-slate-700/40',
        surface:
            'border-slate-300 bg-slate-50 ring-2 ring-slate-900/10 shadow-lg shadow-slate-900/5',
        accent: 'text-slate-700',
    },
};

const style = computed<ToneStyle>(() => toneStyles[props.tone ?? 'default']);
</script>

<template>
    <component
        :is="interactive ? 'button' : 'article'"
        :type="interactive ? 'button' : undefined"
        class="relative w-full overflow-hidden rounded-2xl border bg-white p-5 text-left shadow-sm transition duration-200"
        :class="[
            active
                ? `${style.surface} -translate-y-0.5`
                : 'border-slate-200/80',
            interactive
                ? 'cursor-pointer hover:-translate-y-0.5 hover:border-slate-300 hover:shadow-md focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-slate-900 active:translate-y-0'
                : 'hover:shadow-md',
        ]"
        :aria-pressed="interactive ? active : undefined"
        @click="$emit('click')"
    >
        <!-- A bar along the top so an active card reads even at a glance. -->
        <span
            v-if="active"
            class="absolute inset-x-0 top-0 h-1"
            :class="style.activeChip"
        ></span>

        <div class="flex items-start justify-between">
            <div
                class="flex h-10 w-10 items-center justify-center rounded-xl transition"
                :class="active ? style.activeChip : style.chip"
            >
                <component :is="icon" v-if="icon" class="h-5 w-5" />
            </div>
            <span
                v-if="delta !== undefined && delta !== null"
                class="flex items-center gap-1 rounded-full px-2 py-1 text-[11px] font-medium"
                :class="
                    trend === 'up'
                        ? 'bg-emerald-50 text-emerald-600'
                        : trend === 'down'
                          ? 'bg-rose-50 text-rose-600'
                          : 'bg-slate-100 text-slate-500'
                "
                :title="comparisonLabel"
            >
                <component
                    :is="
                        trend === 'up'
                            ? TrendingUp
                            : trend === 'down'
                              ? TrendingDown
                              : Minus
                    "
                    class="h-3.5 w-3.5"
                />
                {{ Math.abs(delta) }}%
            </span>
            <span
                v-else-if="active"
                class="flex h-6 w-6 items-center justify-center rounded-full"
                :class="style.activeChip"
            >
                <Check class="h-3.5 w-3.5" />
            </span>
        </div>
        <p
            class="mt-4 text-sm"
            :class="active ? `font-medium ${style.accent}` : 'text-slate-500'"
        >
            {{ label }}
        </p>
        <p
            class="mt-1 text-2xl font-semibold tracking-tight text-slate-900 tabular-nums"
        >
            {{ value }}
        </p>
        <p
            v-if="caption"
            class="mt-1 text-xs"
            :class="active ? 'text-slate-500' : 'text-slate-400'"
        >
            {{ caption }}
        </p>
    </component>
</template>
