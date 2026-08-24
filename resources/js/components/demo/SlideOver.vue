<script setup lang="ts">
import { X } from '@lucide/vue';

defineProps<{
    open: boolean;
    title?: string;
    caption?: string;
}>();

const emit = defineEmits<{
    close: [];
}>();
</script>

<template>
    <div
        v-if="open"
        class="fixed inset-0 z-50 flex justify-end bg-slate-950/40 backdrop-blur-sm"
        @click.self="emit('close')"
    >
        <div
            class="flex h-full w-full max-w-md flex-col overflow-y-auto bg-white shadow-2xl"
        >
            <div
                class="sticky top-0 z-10 flex items-start justify-between gap-3 border-b border-slate-100 bg-white px-6 py-4"
            >
                <div v-if="title" class="min-w-0">
                    <p class="text-base font-semibold text-slate-900">
                        {{ title }}
                    </p>
                    <p v-if="caption" class="text-xs text-slate-500">
                        {{ caption }}
                    </p>
                </div>
                <slot name="header" />
                <button
                    type="button"
                    class="rounded-lg p-1.5 text-slate-400 transition hover:bg-slate-100 hover:text-slate-600"
                    aria-label="Tutup"
                    @click="emit('close')"
                >
                    <X class="h-4 w-4" />
                </button>
            </div>

            <div class="flex-1 p-6">
                <slot />
            </div>

            <div
                v-if="$slots.footer"
                class="sticky bottom-0 flex gap-2 border-t border-slate-100 bg-white p-4"
            >
                <slot name="footer" />
            </div>
        </div>
    </div>
</template>
