<script setup lang="ts">
import { Search } from '@lucide/vue';

defineProps<{
    placeholder?: string;
    filters?: string[];
    activeFilter?: string;
}>();

const search = defineModel<string>('search', { default: '' });

const emit = defineEmits<{
    filter: [value: string];
}>();
</script>

<template>
    <div class="flex flex-wrap items-center gap-2">
        <div class="flex items-center gap-2 rounded-xl bg-slate-100 px-3 py-2">
            <Search class="h-4 w-4 shrink-0 text-slate-400" />
            <input
                v-model="search"
                type="search"
                :placeholder="placeholder ?? 'Cari'"
                class="w-44 bg-transparent text-sm text-slate-700 placeholder:text-slate-400 focus:outline-none"
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
