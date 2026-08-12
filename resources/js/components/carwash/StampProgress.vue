<script setup lang="ts">
import { Droplets, Gift } from '@lucide/vue';
import { computed } from 'vue';

const props = defineProps<{
    stamps: number;
    target: number;
    compact?: boolean;
}>();

/** Stamps left before the free-wash reward unlocks (BR-02). */
const remaining = computed<number>(() =>
    Math.max(props.target - props.stamps, 0),
);

const percent = computed<number>(() =>
    Math.min(Math.round((props.stamps / props.target) * 100), 100),
);
</script>

<template>
    <div>
        <div v-if="!compact" class="grid grid-cols-5 gap-2">
            <div
                v-for="index in target"
                :key="index"
                class="flex aspect-square items-center justify-center rounded-xl border transition"
                :class="
                    index <= stamps
                        ? 'border-cyan-200 bg-gradient-to-br from-cyan-500 to-sky-600 text-white shadow-sm shadow-cyan-500/30'
                        : index === target
                          ? 'border-dashed border-amber-300 bg-amber-50 text-amber-500'
                          : 'border-dashed border-slate-200 bg-slate-50 text-slate-300'
                "
            >
                <Gift
                    v-if="index === target && index > stamps"
                    class="h-4 w-4"
                />
                <Droplets v-else class="h-4 w-4" />
            </div>
        </div>

        <div v-else>
            <div
                class="flex items-center justify-between text-[11px] font-medium text-slate-500"
            >
                <span>{{ stamps }} / {{ target }} stempel</span>
                <span v-if="remaining > 0">{{ remaining }} lagi</span>
                <span v-else class="text-emerald-600">Reward siap ditukar</span>
            </div>
            <div class="mt-1.5 h-2 overflow-hidden rounded-full bg-slate-100">
                <div
                    class="h-full rounded-full bg-gradient-to-r from-cyan-500 to-sky-500 transition-all duration-700"
                    :style="{ width: `${percent}%` }"
                ></div>
            </div>
        </div>
    </div>
</template>
