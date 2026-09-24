<script setup lang="ts">
import { Head } from '@inertiajs/vue3';
import { ChevronLeft, ChevronRight, Search, Sparkles, X } from '@lucide/vue';
import { computed, ref } from 'vue';
import EmptyState from '@/components/demo/EmptyState.vue';
import ModalDialog from '@/components/demo/ModalDialog.vue';
import { formatCurrency } from '@/composables/useCarwashFormat';
import type { CarwashBrand, CarwashMember, CarwashService } from '@/types/demo';

const props = defineProps<{
    mode: 'demo' | 'live';
    brand: CarwashBrand;
    member: CarwashMember;
    services: CarwashService[];
    categories: string[];
}>();

const activeGroup = ref<string | null>(null);
const activeCategory = ref<string | null>(null);
const search = ref<string>('');
const detailService = ref<CarwashService | null>(null);

const sellableServices = computed<CarwashService[]>(() =>
    props.services.filter(
        (service) =>
            service.isActive &&
            service.serviceVariations.some((variation) => variation.isActive),
    ),
);

const groups = computed<string[]>(() => [
    ...new Set(sellableServices.value.map((service) => service.categoryGroup)),
]);

const currentGroup = computed<string | null>(
    () =>
        activeGroup.value ??
        (groups.value.length === 1 ? groups.value[0] : null),
);

const categoriesInGroup = computed<string[]>(() => [
    ...new Set(
        sellableServices.value
            .filter((service) => service.categoryGroup === currentGroup.value)
            .map((service) => service.category),
    ),
]);

const currentCategory = computed<string | null>(
    () =>
        activeCategory.value ??
        (categoriesInGroup.value.length === 1
            ? categoriesInGroup.value[0]
            : null),
);

interface CatalogCard {
    kind: 'group' | 'category';
    label: string;
    count: number;
    icon: string;
}

const overviewCards = computed<CatalogCard[] | null>(() => {
    if (
        sellableServices.value.length === 0 ||
        search.value.trim() ||
        currentCategory.value !== null
    ) {
        return null;
    }

    const labels =
        currentGroup.value === null ? groups.value : categoriesInGroup.value;

    return labels.map((label) => {
        const services = sellableServices.value.filter((service) =>
            currentGroup.value === null
                ? service.categoryGroup === label
                : service.categoryGroup === currentGroup.value &&
                  service.category === label,
        );

        return {
            kind: currentGroup.value === null ? 'group' : 'category',
            label,
            count: services.length,
            icon: services.find((service) => service.icon)?.icon ?? '🫧',
        };
    });
});

const breadcrumb = computed<string>(() =>
    [
        groups.value.length > 1 ? currentGroup.value : null,
        categoriesInGroup.value.length > 1 ? currentCategory.value : null,
    ]
        .filter((label): label is string => label !== null)
        .join(' › '),
);

function openCard(card: CatalogCard): void {
    if (card.kind === 'group') {
        activeGroup.value = card.label;
        activeCategory.value = null;
    } else {
        activeCategory.value = card.label;
    }
}

function goBack(): void {
    if (activeCategory.value !== null && categoriesInGroup.value.length > 1) {
        activeCategory.value = null;
    } else {
        activeGroup.value = null;
        activeCategory.value = null;
    }
}

const visibleServices = computed<CarwashService[]>(() => {
    const tokens = search.value.toLowerCase().split(/\s+/).filter(Boolean);

    return sellableServices.value.filter((service) => {
        if (
            (currentGroup.value !== null &&
                service.categoryGroup !== currentGroup.value) ||
            (currentCategory.value !== null &&
                service.category !== currentCategory.value)
        ) {
            return false;
        }

        const haystack = [
            service.name,
            service.category,
            service.description,
            ...Object.keys(service.variations ?? {}),
            ...Object.values(service.variations ?? {}).flat(),
        ]
            .join(' ')
            .toLowerCase();

        return tokens.every((token) => haystack.includes(token));
    });
});

function priceRange(service: CarwashService): string {
    const prices = service.serviceVariations
        .filter((variation) => variation.isActive)
        .map((variation) => variation.price);
    const minimum = Math.min(...prices);
    const maximum = Math.max(...prices);

    return minimum === maximum
        ? formatCurrency(minimum)
        : `${formatCurrency(minimum)}–${formatCurrency(maximum)}`;
}

function variationLabel(
    variation: CarwashService['serviceVariations'][number],
): string {
    return Object.values(variation.variations ?? {}).join(' · ');
}
</script>

<template>
    <Head :title="`${brand.name} — Layanan`" />

    <div class="space-y-4 px-5 py-5">
        <section>
            <h1 class="text-base font-semibold text-slate-900">
                Katalog layanan
            </h1>
            <p class="mt-0.5 text-xs text-slate-500">
                Harga layanan di {{ brand.name }}.
            </p>
        </section>

        <div
            v-if="activeGroup !== null || activeCategory !== null"
            class="flex items-center gap-2"
        >
            <button
                type="button"
                class="inline-flex shrink-0 items-center gap-1 rounded-full border border-slate-200 bg-white px-3 py-1.5 text-xs font-semibold text-slate-600"
                @click="goBack"
            >
                <ChevronLeft class="h-3.5 w-3.5" /> Kembali
            </button>
            <span class="min-w-0 truncate text-sm font-semibold text-slate-800">
                {{ breadcrumb }}
            </span>
        </div>

        <div
            class="flex items-center gap-2 rounded-xl bg-white px-3 py-2.5 ring-1 ring-slate-200"
        >
            <Search class="h-4 w-4 shrink-0 text-slate-400" />
            <input
                v-model="search"
                type="search"
                placeholder="Cari layanan, kategori, atau variasi"
                class="w-full bg-transparent text-sm text-slate-700 placeholder:text-slate-400 focus:outline-none"
            />
        </div>

        <ul v-if="overviewCards !== null" class="grid grid-cols-2 gap-2">
            <li
                v-for="card in overviewCards"
                :key="`${card.kind}-${card.label}`"
            >
                <button
                    type="button"
                    class="flex h-full w-full items-center gap-2 rounded-xl border border-slate-200 bg-white p-3 text-left transition hover:border-cyan-300 hover:bg-cyan-50/40"
                    @click="openCard(card)"
                >
                    <span class="text-xl">{{ card.icon }}</span>
                    <span class="min-w-0 flex-1">
                        <span
                            class="block truncate text-sm font-semibold text-slate-800"
                            >{{ card.label }}</span
                        >
                        <span class="block text-xs text-slate-500"
                            >{{ card.count }} layanan</span
                        >
                    </span>
                    <ChevronRight class="h-4 w-4 shrink-0 text-cyan-600" />
                </button>
            </li>
        </ul>

        <ul v-else-if="visibleServices.length > 0" class="space-y-3">
            <li
                v-for="service in visibleServices"
                :key="service.id"
                class="overflow-hidden rounded-2xl border border-slate-200 bg-white"
            >
                <button
                    type="button"
                    class="w-full p-4 text-left transition hover:bg-slate-50"
                    @click="detailService = service"
                >
                    <div class="flex items-start gap-3">
                        <div
                            class="flex h-12 w-12 shrink-0 items-center justify-center rounded-xl bg-gradient-to-br from-cyan-50 to-sky-100 text-2xl"
                        >
                            {{ service.icon }}
                        </div>
                        <div class="min-w-0 flex-1">
                            <div class="flex items-start justify-between gap-2">
                                <p class="text-sm font-semibold text-slate-900">
                                    {{ service.name }}
                                </p>
                                <span
                                    v-if="service.popular"
                                    class="shrink-0 rounded-full bg-amber-100 px-2 py-0.5 text-[10px] font-medium text-amber-700"
                                >
                                    Populer
                                </span>
                            </div>
                            <p
                                v-if="currentCategory === null"
                                class="mt-0.5 truncate text-[11px] text-cyan-700"
                            >
                                {{
                                    currentGroup === null
                                        ? `${service.categoryGroup} › ${service.category}`
                                        : service.category
                                }}
                            </p>
                            <p
                                class="mt-0.5 line-clamp-2 text-xs text-slate-500"
                            >
                                {{ service.description }}
                            </p>
                            <div
                                class="mt-2 flex flex-wrap items-center gap-x-3 gap-y-1"
                            >
                                <span
                                    class="text-sm font-semibold text-cyan-700 tabular-nums"
                                >
                                    {{ priceRange(service) }}
                                </span>
                                <span
                                    v-if="service.stamps > 0"
                                    class="flex items-center gap-1 text-[11px] font-medium text-emerald-600"
                                >
                                    <Sparkles class="h-3.5 w-3.5" />
                                    +{{ service.stamps }} stempel
                                </span>
                            </div>
                        </div>
                    </div>
                </button>
            </li>
        </ul>

        <EmptyState
            v-else
            :icon="Search"
            title="Layanan tidak ditemukan"
            caption="Coba kata kunci lain atau pilih kategori berbeda."
        />

        <p class="pt-1 text-center text-[11px] text-slate-400">
            Pemesanan layanan dilakukan langsung di tempat atau melalui kasir.
        </p>
    </div>

    <!-- Service detail -->
    <ModalDialog
        :open="detailService !== null"
        size="sm"
        @close="detailService = null"
    >
        <div v-if="detailService" class="text-center">
            <button
                type="button"
                class="absolute top-4 right-4 rounded-lg p-1.5 text-slate-400 transition hover:bg-slate-100"
                aria-label="Tutup"
                @click="detailService = null"
            >
                <X class="h-4 w-4" />
            </button>

            <div
                class="mx-auto flex h-16 w-16 items-center justify-center rounded-2xl bg-gradient-to-br from-cyan-50 to-sky-100 text-3xl"
            >
                {{ detailService.icon }}
            </div>
            <p class="mt-3 text-base font-semibold text-slate-900">
                {{ detailService.name }}
            </p>
            <span
                class="mt-1 inline-block rounded-full bg-slate-100 px-2 py-0.5 text-[10px] font-medium text-slate-500"
            >
                {{ detailService.categoryGroup }} › {{ detailService.category }}
            </span>
            <p class="mt-3 text-xs leading-relaxed text-slate-500">
                {{ detailService.description }}
            </p>

            <dl class="mt-5 space-y-2 rounded-2xl bg-slate-50 p-4 text-sm">
                <div class="flex justify-between">
                    <dt class="text-slate-500">Harga</dt>
                    <dd class="font-semibold text-slate-900 tabular-nums">
                        {{ priceRange(detailService) }}
                    </dd>
                </div>
                <div class="flex justify-between">
                    <dt class="text-slate-500">Stempel didapat</dt>
                    <dd class="font-medium text-emerald-600 tabular-nums">
                        +{{ detailService.stamps }}
                    </dd>
                </div>
            </dl>

            <div
                v-if="
                    detailService.serviceVariations.filter(
                        (variation) => variation.isActive,
                    ).length > 1
                "
                class="mt-4 text-left"
            >
                <p class="mb-2 text-xs font-semibold text-slate-700">
                    Harga per variasi
                </p>
                <ul
                    class="divide-y divide-slate-100 rounded-xl border border-slate-200 px-3"
                >
                    <li
                        v-for="variation in detailService.serviceVariations.filter(
                            (item) => item.isActive,
                        )"
                        :key="variation.id"
                        class="flex justify-between gap-3 py-2 text-xs"
                    >
                        <span class="text-slate-600">{{
                            variationLabel(variation)
                        }}</span>
                        <span
                            class="shrink-0 font-semibold text-slate-900 tabular-nums"
                            >{{ formatCurrency(variation.price) }}</span
                        >
                    </li>
                </ul>
            </div>

            <p class="mt-4 text-[11px] text-slate-400">
                Tunjukkan kartu member kamu ke kasir untuk mencatat stempel.
            </p>
        </div>
    </ModalDialog>
</template>
