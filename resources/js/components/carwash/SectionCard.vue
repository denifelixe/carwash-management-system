<script setup lang="ts">
defineProps<{
    title?: string;
    caption?: string;
    padded?: boolean;
}>();
</script>

<template>
    <article
        class="rounded-2xl border border-slate-200/80 bg-white shadow-sm"
        :class="padded === false ? '' : 'p-5'"
    >
        <div
            v-if="title || $slots.actions"
            class="flex flex-wrap items-start justify-between gap-3"
            :class="padded === false ? 'border-b border-slate-100 p-5' : ''"
        >
            <div v-if="title">
                <h3 class="text-sm font-semibold text-slate-900">
                    {{ title }}
                </h3>
                <p v-if="caption" class="mt-0.5 text-xs text-slate-500">
                    {{ caption }}
                </p>
            </div>
            <slot name="actions" />
        </div>

        <slot />
    </article>
</template>
