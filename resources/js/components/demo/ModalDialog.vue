<script lang="ts">
let openModalCount = 0;
</script>

<script setup lang="ts">
import { X } from '@lucide/vue';
import { onBeforeUnmount, watch } from 'vue';

const props = defineProps<{
    open: boolean;
    title?: string;
    caption?: string;
    size?: 'sm' | 'md' | 'lg' | 'xl';
    layer?: 'default' | 'nested' | 'top';
}>();

const emit = defineEmits<{
    close: [];
}>();

const widths: Record<string, string> = {
    sm: 'max-w-sm',
    md: 'max-w-lg',
    lg: 'max-w-2xl',
    xl: 'max-w-4xl',
};

const layers: Record<NonNullable<typeof props.layer>, string> = {
    default: 'z-50',
    nested: 'z-[60]',
    top: 'z-[70]',
};

/** Each mounted dialog owns one lock so stacked modals cannot unlock each other. */
let ownsPageScrollLock = false;

function syncPageScrollLock(locked: boolean): void {
    if (locked && !ownsPageScrollLock) {
        openModalCount += 1;
        ownsPageScrollLock = true;
    } else if (!locked && ownsPageScrollLock) {
        openModalCount = Math.max(openModalCount - 1, 0);
        ownsPageScrollLock = false;
    }

    document.body.style.overflow = openModalCount > 0 ? 'hidden' : '';
}

watch(() => props.open, syncPageScrollLock, { immediate: true });

onBeforeUnmount(() => syncPageScrollLock(false));
</script>

<template>
    <Teleport to="body">
        <div
            v-if="open"
            class="fixed inset-0 flex items-end justify-center bg-slate-950/50 p-0 backdrop-blur-sm sm:items-center sm:p-4"
            :class="layers[layer ?? 'default']"
            @click.self="emit('close')"
        >
            <div
                class="flex max-h-[92dvh] w-full flex-col overflow-hidden rounded-t-3xl bg-white shadow-2xl sm:rounded-3xl"
                :class="widths[size ?? 'md']"
            >
                <div v-if="$slots.hero" class="shrink-0">
                    <slot name="hero" />
                </div>

                <div
                    v-if="title"
                    class="flex shrink-0 items-start justify-between gap-3 border-b border-slate-100 px-6 py-4"
                >
                    <div class="min-w-0">
                        <p class="text-base font-semibold text-slate-900">
                            {{ title }}
                        </p>
                        <p v-if="caption" class="text-xs text-slate-500">
                            {{ caption }}
                        </p>
                    </div>
                    <button
                        type="button"
                        class="rounded-lg p-1.5 text-slate-400 transition hover:bg-slate-100 hover:text-slate-600"
                        aria-label="Tutup"
                        @click="emit('close')"
                    >
                        <X class="h-4 w-4" />
                    </button>
                </div>

                <div
                    class="min-h-0 flex-1 [scrollbar-gutter:stable] overflow-y-auto p-6"
                >
                    <slot />
                </div>

                <div
                    v-if="$slots.footer"
                    class="flex shrink-0 gap-2 border-t border-slate-100 p-4"
                >
                    <slot name="footer" />
                </div>
            </div>
        </div>
    </Teleport>
</template>
