<script setup lang="ts">
import type { LucideIcon } from '@lucide/vue';

defineProps<{
    icon?: LucideIcon;
    title: string;
    caption?: string;
}>();
</script>

<template>
    <div
        class="flex flex-col items-center justify-center px-6 py-10 text-center"
    >
        <div
            class="flex h-14 w-14 items-center justify-center rounded-2xl bg-slate-50 text-slate-300"
        >
            <component :is="icon" v-if="icon" class="h-7 w-7" />
        </div>
        <p class="mt-3 text-sm text-slate-500">{{ title }}</p>
        <p v-if="caption" class="mt-1 max-w-xs text-xs text-slate-400">
            {{ caption }}
        </p>
        <slot />
    </div>
</template>
