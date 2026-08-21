<script setup lang="ts">
/**
 * A titled panel whose body folds away, so a page can stack several long lists
 * without burying the one the user came for.
 *
 * The header keeps the icon-and-caption layout the flat sections used, and the
 * whole label area is the toggle. Anything in the `toolbar` slot sits beside it
 * and is hidden while the panel is shut, since filtering a list nobody can see
 * only adds noise.
 */
import { ChevronDown } from '@lucide/vue';
import type { LucideIcon } from '@lucide/vue';
import { computed, ref, useId } from 'vue';

type Tone = 'violet' | 'orange' | 'emerald';

const props = withDefaults(
    defineProps<{
        title: string;
        caption?: string;
        icon?: LucideIcon;
        tone?: Tone;
        defaultOpen?: boolean;
    }>(),
    {
        caption: undefined,
        icon: undefined,
        tone: 'violet',
        defaultOpen: false,
    },
);

type ToneStyle = {
    shell: string;
    chip: string;
    title: string;
    caption: string;
    chevron: string;
};

/** Written out in full so Tailwind keeps every class in the build. */
const toneStyles: Record<Tone, ToneStyle> = {
    violet: {
        shell: 'border-violet-200/80 bg-violet-50/30',
        chip: 'bg-violet-100 text-violet-700',
        title: 'text-violet-950',
        caption: 'text-violet-700/70',
        chevron: 'text-violet-400',
    },
    orange: {
        shell: 'border-orange-200/80 bg-amber-50/40',
        chip: 'bg-orange-100 text-orange-700',
        title: 'text-orange-950',
        caption: 'text-orange-700/70',
        chevron: 'text-orange-400',
    },
    emerald: {
        shell: 'border-emerald-200/80 bg-emerald-50/30',
        chip: 'bg-emerald-100 text-emerald-700',
        title: 'text-emerald-950',
        caption: 'text-emerald-700/70',
        chevron: 'text-emerald-400',
    },
};

const contentId = useId();
const isOpen = ref<boolean>(props.defaultOpen);

const styles = computed<ToneStyle>(() => toneStyles[props.tone]);
</script>

<template>
    <section class="rounded-2xl border p-5 shadow-sm" :class="styles.shell">
        <div class="flex flex-wrap items-center justify-between gap-3">
            <button
                type="button"
                class="flex min-w-0 flex-1 items-center gap-3 text-left"
                :aria-expanded="isOpen"
                :aria-controls="contentId"
                @click="isOpen = !isOpen"
            >
                <span
                    class="flex h-9 w-9 shrink-0 items-center justify-center rounded-xl"
                    :class="styles.chip"
                >
                    <component :is="icon" v-if="icon" class="h-4.5 w-4.5" />
                </span>
                <span class="min-w-0">
                    <span
                        class="block text-sm font-semibold"
                        :class="styles.title"
                    >
                        {{ title }}
                    </span>
                    <span
                        v-if="caption"
                        class="mt-0.5 block text-xs"
                        :class="styles.caption"
                    >
                        {{ caption }}
                    </span>
                </span>
                <ChevronDown
                    class="ml-1 h-4 w-4 shrink-0 transition-transform"
                    :class="[styles.chevron, isOpen ? 'rotate-180' : '']"
                />
            </button>

            <div v-if="$slots.toolbar" v-show="isOpen">
                <slot name="toolbar" />
            </div>
        </div>

        <div v-show="isOpen" :id="contentId">
            <slot />
        </div>
    </section>
</template>
