<script setup lang="ts">
import { Clock } from '@lucide/vue';
import ModalDialog from '@/components/demo/ModalDialog.vue';
import type { CarwashTransactionShiftOption } from '@/types/demo';

defineProps<{
    open: boolean;
    shifts: CarwashTransactionShiftOption[];
}>();

const emit = defineEmits<{
    close: [];
    select: [shiftId: number];
}>();
</script>

<template>
    <ModalDialog
        :open="open"
        title="Pilih shift transaksi"
        caption="Jam transaksi ini masuk ke lebih dari satu shift."
        size="sm"
        layer="top"
        @close="emit('close')"
    >
        <div class="space-y-2">
            <button
                v-for="shift in shifts"
                :key="shift.id"
                type="button"
                class="flex w-full items-center justify-between gap-4 rounded-xl border border-slate-200 bg-white px-4 py-3 text-left transition hover:border-cyan-300 hover:bg-cyan-50 focus-visible:ring-2 focus-visible:ring-cyan-500 focus-visible:outline-none"
                @click="emit('select', shift.id)"
            >
                <span class="font-semibold text-slate-900">{{
                    shift.name
                }}</span>
                <span
                    class="flex shrink-0 items-center gap-1.5 text-xs font-medium text-slate-500 tabular-nums"
                >
                    <Clock class="h-3.5 w-3.5" />
                    {{ shift.time }}
                </span>
            </button>
        </div>
    </ModalDialog>
</template>
