<script setup lang="ts">
import InputError from '@/components/InputError.vue';

defineProps<{
    id: string;
    currentShift: string | null;
    options: { id: number; name: string }[];
    error?: string;
}>();

const selection = defineModel<number | null | 'keep'>({ required: true });
</script>

<template>
    <div>
        <label :for="id" class="text-xs font-medium text-slate-600"
            >Shift</label
        >
        <select
            :id="id"
            v-model="selection"
            class="mt-1.5 w-full rounded-xl border border-slate-200 px-3 py-2.5 text-sm focus:border-cyan-400 focus:outline-none"
        >
            <option value="keep">
                {{ currentShift ?? 'Tanpa Shift' }} (saat ini)
            </option>
            <option :value="null">Tanpa Shift</option>
            <option v-for="shift in options" :key="shift.id" :value="shift.id">
                {{ shift.name }}
            </option>
        </select>
        <InputError class="mt-1.5" :message="error" />
    </div>
</template>
