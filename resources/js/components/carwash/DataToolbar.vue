<script setup lang="ts">
import { Search } from '@lucide/vue';

defineProps<{
    placeholder?: string;
    filters?: string[];
    activeFilter?: string;
    wideSearch?: boolean;
}>();

const search = defineModel<string>('search', { default: '' });

const emit = defineEmits<{
    filter: [value: string];
}>();
</script>

<template>
    <div class="flex flex-wrap items-center gap-2">
        <div
            class="flex items-center gap-2 rounded-xl border border-slate-300 bg-white px-3 py-2 shadow-sm transition focus-within:border-cyan-500 focus-within:ring-2 focus-within:ring-cyan-100"
            :class="wideSearch ? 'w-full sm:w-96' : undefined"
        >
            <Search class="h-4 w-4 shrink-0 text-slate-500" />
            <input
                v-model="search"
                type="search"
                :placeholder="placeholder ?? 'Cari'"
                class="min-w-0 bg-transparent text-sm text-slate-800 placeholder:text-slate-500 focus:outline-none"
                :class="wideSearch ? 'w-full' : 'w-44'"
            />
        </div>

        <div v-if="filters?.length" class="flex flex-wrap gap-1.5">
            <button
                v-for="filter in filters"
                :key="filter"
                type="button"
                class="rounded-full px-3 py-1.5 text-xs font-medium capitalize transition"
                :class="
                    activeFilter === filter
                        ? 'bg-slate-900 text-white'
                        : 'bg-slate-100 text-slate-600 hover:bg-slate-200'
                "
                @click="emit('filter', filter)"
            >
                {{ filter }}
            </button>
        </div>

        <slot />
    </div>
</template>
