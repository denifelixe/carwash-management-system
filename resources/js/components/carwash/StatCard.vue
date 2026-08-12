<script setup lang="ts">
import { TrendingDown, TrendingUp } from '@lucide/vue';
import type { LucideIcon } from '@lucide/vue';

defineProps<{
    label: string;
    value: string;
    caption?: string;
    delta?: number;
    trend?: string;
    icon?: LucideIcon;
    tone?: 'default' | 'emerald' | 'rose' | 'amber';
}>();

const toneClasses: Record<string, string> = {
    default: 'bg-cyan-50 text-cyan-600',
    emerald: 'bg-emerald-50 text-emerald-600',
    rose: 'bg-rose-50 text-rose-600',
    amber: 'bg-amber-50 text-amber-600',
};
</script>

<template>
    <article
        class="rounded-2xl border border-slate-200/80 bg-white p-5 shadow-sm transition hover:shadow-md"
    >
        <div class="flex items-start justify-between">
            <div
                class="flex h-10 w-10 items-center justify-center rounded-xl"
                :class="toneClasses[tone ?? 'default']"
            >
                <component :is="icon" v-if="icon" class="h-5 w-5" />
            </div>
            <span
                v-if="delta !== undefined"
                class="flex items-center gap-1 rounded-full px-2 py-1 text-[11px] font-medium"
                :class="
                    trend === 'up'
                        ? 'bg-emerald-50 text-emerald-600'
                        : 'bg-rose-50 text-rose-600'
                "
            >
                <component
                    :is="trend === 'up' ? TrendingUp : TrendingDown"
                    class="h-3.5 w-3.5"
                />
                {{ Math.abs(delta) }}%
            </span>
        </div>
        <p class="mt-4 text-sm text-slate-500">{{ label }}</p>
        <p
            class="mt-1 text-2xl font-semibold tracking-tight text-slate-900 tabular-nums"
        >
            {{ value }}
        </p>
        <p v-if="caption" class="mt-1 text-xs text-slate-400">{{ caption }}</p>
    </article>
</template>
