<script setup lang="ts">
import {
    ChevronDown,
    ChevronRight,
    Droplets,
    Layers3,
    Package,
    Plus,
    ShieldCheck,
    Sparkles,
} from '@lucide/vue';
import type { LucideIcon } from '@lucide/vue';
import { ref, useId } from 'vue';
import ModalDialog from '@/components/demo/ModalDialog.vue';
import StatusPill from '@/components/demo/StatusPill.vue';
import { formatPlate } from '@/lib/vehiclePlate';
import type { CarwashServiceOverviewGroup } from '@/types/demo';

withDefaults(
    defineProps<{
        groups: CarwashServiceOverviewGroup[];
        collapsible?: boolean;
    }>(),
    { collapsible: false },
);

const contentId = useId();
const isOpen = ref(false);
const openGroup = ref<string | null>(null);
const selectedCategory = ref<
    CarwashServiceOverviewGroup['categories'][number] | null
>(null);
const emit = defineEmits<{ selectOrder: [orderId: number] }>();

function selectOrder(orderId: number): void {
    selectedCategory.value = null;
    emit('selectOrder', orderId);
}

const groupIcons: Record<string, LucideIcon> = {
    Cuci: Droplets,
    Detailing: Sparkles,
    Coating: ShieldCheck,
    Paket: Package,
    'Add-on': Plus,
};
</script>

<template>
    <div>
        <section
            class="rounded-2xl border border-slate-200/80 bg-white p-5 shadow-sm"
        >
            <button
                v-if="collapsible"
                type="button"
                class="flex w-full items-center justify-between gap-3 text-left"
                :aria-expanded="isOpen"
                :aria-controls="contentId"
                @click="isOpen = !isOpen"
            >
                <span>
                    <span class="block text-sm font-semibold text-slate-900"
                        >Overview layanan</span
                    >
                    <span class="mt-0.5 block text-xs text-slate-500"
                        >Kendaraan per grup layanan pada tanggal terpilih</span
                    >
                </span>
                <ChevronDown
                    class="h-4 w-4 shrink-0 text-slate-500 transition-transform"
                    :class="isOpen ? 'rotate-180' : ''"
                />
            </button>
            <div v-else>
                <h3 class="text-sm font-semibold text-slate-900">
                    Overview layanan
                </h3>
                <p class="mt-0.5 text-xs text-slate-500">
                    Kendaraan per grup layanan pada tanggal terpilih
                </p>
            </div>

            <div
                :id="contentId"
                :class="collapsible && !isOpen ? 'hidden' : 'mt-4'"
            >
                <p
                    v-if="groups.length === 0"
                    class="rounded-xl border border-dashed border-slate-200 bg-slate-50 px-4 py-6 text-center text-sm text-slate-500"
                >
                    Belum ada layanan pada tanggal ini.
                </p>
                <div v-else class="space-y-3">
                    <div
                        v-for="group in groups"
                        :key="group.name"
                        class="rounded-xl border border-slate-200"
                    >
                        <button
                            type="button"
                            class="flex w-full items-center gap-3 rounded-xl px-4 py-3 text-left transition hover:bg-slate-50"
                            :aria-expanded="openGroup === group.name"
                            @click="
                                openGroup =
                                    openGroup === group.name ? null : group.name
                            "
                        >
                            <span
                                class="flex h-11 w-11 shrink-0 items-center justify-center rounded-xl bg-cyan-50 text-cyan-700"
                            >
                                <component
                                    :is="groupIcons[group.name] ?? Layers3"
                                    class="h-5 w-5"
                                />
                            </span>
                            <span class="min-w-0 flex-1">
                                <span
                                    class="block text-sm font-semibold text-slate-900"
                                    >{{ group.name }}</span
                                >
                            </span>
                            <span
                                class="flex shrink-0 items-baseline gap-1 rounded-xl bg-cyan-50 px-3 py-1.5 text-cyan-800"
                            >
                                <span class="text-xl font-bold tabular-nums">{{
                                    group.count
                                }}</span>
                                <span class="text-xs font-medium"
                                    >kendaraan</span
                                >
                            </span>
                            <ChevronRight
                                class="h-4 w-4 shrink-0 text-cyan-600 transition-transform"
                                :class="
                                    openGroup === group.name ? 'rotate-90' : ''
                                "
                            />
                        </button>
                        <div
                            v-if="openGroup === group.name"
                            class="border-t border-slate-100 px-4 py-2"
                        >
                            <button
                                v-for="category in group.categories"
                                :key="category.name"
                                type="button"
                                class="flex w-full items-center justify-between gap-3 rounded-lg px-2 py-2 text-left text-sm transition hover:bg-slate-50"
                                @click="selectedCategory = category"
                            >
                                <span class="text-slate-700">{{
                                    category.name
                                }}</span>
                                <span class="flex shrink-0 items-center gap-2">
                                    <span
                                        class="rounded-lg bg-slate-100 px-2.5 py-1 font-semibold text-slate-900 tabular-nums"
                                    >
                                        {{ category.count }} kendaraan
                                    </span>
                                    <ChevronRight
                                        class="h-4 w-4 text-cyan-600"
                                    />
                                </span>
                            </button>
                        </div>
                    </div>
                </div>
            </div>
        </section>

        <ModalDialog
            :open="selectedCategory !== null"
            :title="selectedCategory?.name"
            :caption="`${selectedCategory?.count ?? 0} kendaraan pada tanggal terpilih`"
            size="lg"
            @close="selectedCategory = null"
        >
            <div class="space-y-2">
                <button
                    v-for="order in selectedCategory?.orders ?? []"
                    :key="order.id"
                    type="button"
                    class="flex w-full flex-wrap items-center justify-between gap-3 rounded-xl border border-slate-200 p-4 text-left transition hover:border-cyan-300 hover:bg-cyan-50/50"
                    @click="selectOrder(order.id)"
                >
                    <span class="min-w-0">
                        <span class="block text-sm font-semibold text-slate-900"
                            >{{ formatPlate(order.plate) }} ·
                            {{ order.vehicle }}</span
                        >
                        <span class="mt-1 block text-xs text-slate-500"
                            >{{ order.orderNo }} · {{ order.customer }}</span
                        >
                    </span>
                    <span class="flex items-center gap-2">
                        <StatusPill :status="order.status" />
                        <ChevronRight class="h-4 w-4 text-cyan-600" />
                    </span>
                </button>
            </div>
        </ModalDialog>
    </div>
</template>
