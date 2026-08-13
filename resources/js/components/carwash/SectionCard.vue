<script setup lang="ts">
/**
 * `padded` must carry an explicit default: Vue casts an absent Boolean prop to
 * `false`, so relying on `undefined` would leave every card edge-to-edge.
 * Pass `:padded="false"` for bleed content such as full-width tables, which
 * then gets its own divider under the header instead of card padding.
 */
withDefaults(
    defineProps<{
        title?: string;
        caption?: string;
        padded?: boolean;
    }>(),
    { title: undefined, caption: undefined, padded: true },
);
</script>

<template>
    <article
        class="rounded-2xl border border-slate-200/80 bg-white shadow-sm"
        :class="padded ? 'p-5' : ''"
    >
        <div
            v-if="title || $slots.actions"
            class="flex flex-wrap items-start justify-between gap-3"
            :class="padded ? '' : 'border-b border-slate-100 p-5'"
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
