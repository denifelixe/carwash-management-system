<script setup lang="ts">
import { ChevronLeft, ChevronRight } from '@lucide/vue';
import type { CarwashPaginationMeta } from '@/types/demo';

defineProps<{ meta: CarwashPaginationMeta }>();

const emit = defineEmits<{
    change: [page: number];
}>();
</script>

<template>
    <div
        v-if="meta.lastPage > 1"
        class="flex flex-wrap items-center justify-between gap-3 border-t border-slate-100 px-5 py-3"
    >
        <p class="text-xs text-slate-500">
            Menampilkan {{ meta.from }}–{{ meta.to }} dari {{ meta.total }}
            member
        </p>
        <div class="flex items-center gap-2">
            <button
                type="button"
                class="rounded-lg border border-slate-200 p-2 text-slate-500 transition hover:bg-slate-50 disabled:cursor-not-allowed disabled:opacity-40"
                :disabled="meta.currentPage <= 1"
                aria-label="Halaman sebelumnya"
                @click="emit('change', meta.currentPage - 1)"
            >
                <ChevronLeft class="h-4 w-4" />
            </button>
            <span class="text-xs font-medium text-slate-600">
                {{ meta.currentPage }} / {{ meta.lastPage }}
            </span>
            <button
                type="button"
                class="rounded-lg border border-slate-200 p-2 text-slate-500 transition hover:bg-slate-50 disabled:cursor-not-allowed disabled:opacity-40"
                :disabled="meta.currentPage >= meta.lastPage"
                aria-label="Halaman berikutnya"
                @click="emit('change', meta.currentPage + 1)"
            >
                <ChevronRight class="h-4 w-4" />
            </button>
        </div>
    </div>
</template>
