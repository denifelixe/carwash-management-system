<script setup lang="ts">
import { X } from '@lucide/vue';

defineProps<{
    open: boolean;
    title?: string;
    caption?: string;
    size?: 'sm' | 'md' | 'lg';
}>();

const emit = defineEmits<{
    close: [];
}>();

const widths: Record<string, string> = {
    sm: 'max-w-sm',
    md: 'max-w-lg',
    lg: 'max-w-2xl',
};
</script>

<template>
    <div
        v-if="open"
        class="fixed inset-0 z-50 flex items-end justify-center bg-slate-950/50 p-0 backdrop-blur-sm sm:items-center sm:p-4"
        @click.self="emit('close')"
    >
        <div
            class="max-h-[92vh] w-full overflow-y-auto rounded-t-3xl bg-white shadow-2xl sm:rounded-3xl"
            :class="widths[size ?? 'md']"
        >
            <div
                v-if="title"
                class="flex items-start justify-between gap-3 border-b border-slate-100 px-6 py-4"
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

            <div class="p-6">
                <slot />
            </div>

            <div
                v-if="$slots.footer"
                class="flex gap-2 border-t border-slate-100 p-4"
            >
                <slot name="footer" />
            </div>
        </div>
    </div>
</template>
