<script setup lang="ts">
/**
 * Wraps a stat-card grid so mobile users land straight on the action area
 * instead of scrolling past summary cards.
 *
 * By default the toggle only exists below `sm`; from `sm` up the grid is always
 * rendered, so a collapsed state can never hide the cards on desktop. Pages that
 * want the summary out of the way on every screen pass `collapsible="always"`.
 */
import { ChevronDown } from '@lucide/vue';
import { computed, ref, useId } from 'vue';

const props = withDefaults(
    defineProps<{
        title?: string;
        caption?: string;
        columns?: 2 | 3 | 4;
        collapsible?: 'mobile' | 'always';
    }>(),
    {
        title: 'Ringkasan',
        caption: undefined,
        columns: 4,
        collapsible: 'mobile',
    },
);

const contentId = useId();
const isOpen = ref<boolean>(false);

const isAlwaysCollapsible = computed<boolean>(
    () => props.collapsible === 'always',
);

/**
 * Written out in full so Tailwind can see each class, and picked so the cards
 * always fill the row instead of leaving a hole at the end.
 */
const columnClass = computed<string>(
    () =>
        ({
            2: 'sm:grid-cols-2',
            3: 'sm:grid-cols-3',
            4: 'sm:grid-cols-2 xl:grid-cols-4',
        })[props.columns],
);

const contentClass = computed<string>(() => {
    if (isAlwaysCollapsible.value) {
        return isOpen.value ? 'mt-3 grid' : 'hidden';
    }

    return isOpen.value ? 'mt-3 grid sm:mt-0' : 'hidden sm:grid';
});
</script>

<template>
    <section>
        <button
            type="button"
            class="flex w-full items-center justify-between gap-3 rounded-2xl border border-slate-200/80 bg-white px-4 py-3 text-left shadow-sm transition hover:bg-slate-50"
            :class="isAlwaysCollapsible ? '' : 'sm:hidden'"
            :aria-expanded="isOpen"
            :aria-controls="contentId"
            @click="isOpen = !isOpen"
        >
            <span class="min-w-0">
                <span class="block text-sm font-semibold text-slate-900">
                    {{ title }}
                </span>
                <span
                    v-if="caption"
                    class="mt-0.5 block truncate text-xs text-slate-500"
                >
                    {{ caption }}
                </span>
            </span>
            <ChevronDown
                class="h-4 w-4 shrink-0 text-slate-400 transition-transform"
                :class="isOpen ? 'rotate-180' : ''"
            />
        </button>

        <div
            :id="contentId"
            class="grid-cols-1 gap-4"
            :class="[columnClass, contentClass]"
        >
            <slot />
        </div>
    </section>
</template>
