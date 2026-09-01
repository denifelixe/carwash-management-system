<script setup lang="ts">
import {
    ChevronDown,
    Minus,
    Plus,
    Search,
    ShoppingCart,
    SprayCan,
    Trash2,
} from '@lucide/vue';
import { computed, nextTick, onBeforeUnmount, reactive, ref, watch } from 'vue';
import ModalDialog from '@/components/demo/ModalDialog.vue';
import { formatCurrency } from '@/composables/useCarwashFormat';
import type {
    CarwashCartItem,
    CarwashService,
    CarwashServiceVariation,
} from '@/types/demo';

const props = defineProps<{
    services: CarwashService[];
    modelValue: CarwashCartItem[];
}>();

const emit = defineEmits<{
    'update:modelValue': [items: CarwashCartItem[]];
}>();

const query = ref('');
/** An empty selection means every category, matching the "Semua" tab. */
const selectedCategories = ref<string[]>([]);
const activeService = ref<CarwashService | null>(null);
const selectedValues = reactive<Record<string, string>>({});
const quantity = ref(1);

/**
 * Phone screens show one panel at a time so the cart sits one tap below the
 * catalog instead of past its scroll. From `sm` up both panels stay open.
 */
const openPanel = ref<'services' | 'cart' | null>('services');

function togglePanel(panel: 'services' | 'cart'): void {
    openPanel.value = openPanel.value === panel ? null : panel;
}

/** Only services that can actually be ordered feed the tabs and the list. */
const sellableServices = computed(() =>
    props.services.filter(
        (service) =>
            service.isActive &&
            service.serviceVariations.some((variation) => variation.isActive),
    ),
);

const categoryOptions = computed(() => [
    ...new Set(sellableServices.value.map((service) => service.category)),
]);

/** A catalog reload can retire a category the operator had selected. */
watch(categoryOptions, (options) => {
    selectedCategories.value = selectedCategories.value.filter((option) =>
        options.includes(option),
    );
});

function toggleCategory(option: string): void {
    selectedCategories.value = selectedCategories.value.includes(option)
        ? selectedCategories.value.filter((selected) => selected !== option)
        : [...selectedCategories.value, option];
}

const visibleServices = computed(() => {
    const tokens = query.value.toLowerCase().split(/\s+/).filter(Boolean);

    return sellableServices.value.filter((service) => {
        if (
            selectedCategories.value.length &&
            !selectedCategories.value.includes(service.category)
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

const catalog = ref<HTMLElement | null>(null);
const canScrollDown = ref(false);
let catalogResize: ResizeObserver | null = null;

function syncScrollHint(): void {
    const element = catalog.value;

    canScrollDown.value = element
        ? element.scrollHeight - element.scrollTop - element.clientHeight > 8
        : false;
}

function scrollCatalogDown(): void {
    const element = catalog.value;

    element?.scrollBy({ top: element.clientHeight * 0.8, behavior: 'smooth' });
}

/** The catalog is behind `v-if`, so track the element instead of `onMounted`. */
watch(catalog, (element) => {
    catalogResize?.disconnect();
    catalogResize = null;

    if (element) {
        catalogResize = new ResizeObserver(syncScrollHint);
        catalogResize.observe(element);
    }

    syncScrollHint();
});

/** Filtering changes the scrollable height without resizing the container. */
watch(visibleServices, () => {
    void nextTick(syncScrollHint);
});

onBeforeUnmount(() => {
    catalogResize?.disconnect();
    catalogResize = null;
});

const selectedVariation = computed<CarwashServiceVariation | null>(() => {
    const service = activeService.value;

    if (!service) {
        return null;
    }

    return (
        service.serviceVariations.find((variation) => {
            if (!variation.isActive) {
                return false;
            }

            if (service.variations === null) {
                return variation.variations === null;
            }

            return Object.entries(service.variations).every(
                ([attribute]) =>
                    variation.variations?.[attribute] ===
                    selectedValues[attribute],
            );
        }) ?? null
    );
});

function openService(service: CarwashService): void {
    activeService.value = service;
    quantity.value = 1;

    for (const key of Object.keys(selectedValues)) {
        delete selectedValues[key];
    }
}

function optionAvailable(attribute: string, value: string): boolean {
    const service = activeService.value;

    if (!service) {
        return false;
    }

    return service.serviceVariations.some((variation) => {
        if (
            !variation.isActive ||
            variation.variations?.[attribute] !== value
        ) {
            return false;
        }

        return Object.entries(selectedValues).every(
            ([selectedAttribute, selectedValue]) =>
                selectedAttribute === attribute ||
                variation.variations?.[selectedAttribute] === selectedValue,
        );
    });
}

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

function addToCart(): void {
    const service = activeService.value;
    const variation = selectedVariation.value;

    if (!service || !variation || quantity.value < 1) {
        return;
    }

    const existing = props.modelValue.find(
        (item) => item.serviceVariationId === variation.id,
    );
    const next = existing
        ? props.modelValue.map((item) =>
              item.serviceVariationId === variation.id
                  ? { ...item, quantity: item.quantity + quantity.value }
                  : item,
          )
        : [
              ...props.modelValue,
              {
                  serviceVariationId: variation.id,
                  serviceId: service.id,
                  quantity: quantity.value,
              },
          ];

    emit('update:modelValue', next);
    activeService.value = null;
}

function cartDetails(item: CarwashCartItem): {
    service: CarwashService;
    variation: CarwashServiceVariation;
} | null {
    const service = props.services.find(
        (candidate) => candidate.id === item.serviceId,
    );
    const variation = service?.serviceVariations.find(
        (candidate) => candidate.id === item.serviceVariationId,
    );

    return service && variation ? { service, variation } : null;
}

function variationLabel(variation: CarwashServiceVariation): string {
    return Object.entries(variation.variations ?? {})
        .map(([attribute, value]) => `${attribute}: ${value}`)
        .join(' · ');
}

function updateQuantity(
    serviceVariationId: number,
    nextQuantity: number,
): void {
    if (nextQuantity < 1) {
        removeItem(serviceVariationId);

        return;
    }

    emit(
        'update:modelValue',
        props.modelValue.map((item) =>
            item.serviceVariationId === serviceVariationId
                ? { ...item, quantity: Math.min(nextQuantity, 999) }
                : item,
        ),
    );
}

function removeItem(serviceVariationId: number): void {
    emit(
        'update:modelValue',
        props.modelValue.filter(
            (item) => item.serviceVariationId !== serviceVariationId,
        ),
    );
}
</script>

<template>
    <div class="space-y-3 sm:space-y-4">
        <div
            class="overflow-hidden rounded-2xl border border-slate-200 sm:rounded-none sm:border-0"
        >
            <button
                type="button"
                class="flex w-full items-center justify-between gap-3 bg-slate-50 px-4 py-3 text-left sm:hidden"
                :aria-expanded="openPanel === 'services'"
                @click="togglePanel('services')"
            >
                <span
                    class="flex items-center gap-2 text-xs font-semibold text-slate-700"
                >
                    <SprayCan class="h-4 w-4" /> Pilih layanan
                </span>
                <span class="flex items-center gap-2 text-xs text-slate-500">
                    {{ visibleServices.length }} layanan
                    <ChevronDown
                        class="h-4 w-4 transition"
                        :class="openPanel === 'services' ? 'rotate-180' : ''"
                    />
                </span>
            </button>

            <div
                class="space-y-4 p-3 sm:p-0"
                :class="openPanel === 'services' ? '' : 'hidden sm:block'"
            >
                <div
                    v-if="categoryOptions.length > 1"
                    class="-mx-1 flex gap-2 overflow-x-auto px-1 pb-1"
                >
                    <button
                        type="button"
                        class="shrink-0 rounded-full px-3 py-1.5 text-xs font-semibold whitespace-nowrap transition"
                        :class="
                            selectedCategories.length
                                ? 'border border-slate-200 bg-white text-slate-600 hover:border-cyan-300 hover:text-cyan-700'
                                : 'bg-cyan-600 text-white shadow-sm shadow-cyan-600/30'
                        "
                        @click="selectedCategories = []"
                    >
                        Semua
                    </button>
                    <button
                        v-for="option in categoryOptions"
                        :key="option"
                        type="button"
                        class="shrink-0 rounded-full px-3 py-1.5 text-xs font-semibold whitespace-nowrap transition"
                        :class="
                            selectedCategories.includes(option)
                                ? 'bg-cyan-600 text-white shadow-sm shadow-cyan-600/30'
                                : 'border border-slate-200 bg-white text-slate-600 hover:border-cyan-300 hover:text-cyan-700'
                        "
                        @click="toggleCategory(option)"
                    >
                        {{ option }}
                    </button>
                </div>

                <div
                    class="flex items-center gap-2 rounded-xl border border-slate-300 bg-white px-3 py-2 shadow-sm focus-within:border-cyan-500 focus-within:ring-2 focus-within:ring-cyan-100"
                >
                    <Search class="h-4 w-4 shrink-0 text-slate-500" />
                    <input
                        v-model="query"
                        type="search"
                        placeholder="Cari layanan, kategori, atau variasi"
                        class="min-w-0 flex-1 bg-transparent text-sm text-slate-800 placeholder:text-slate-500 focus:outline-none"
                    />
                </div>

                <div v-if="visibleServices.length" class="relative">
                    <div
                        ref="catalog"
                        class="grid max-h-64 [scrollbar-gutter:stable] grid-cols-1 gap-2 overflow-y-auto pr-1 sm:grid-cols-2"
                        @scroll="syncScrollHint"
                    >
                        <button
                            v-for="service in visibleServices"
                            :key="service.id"
                            type="button"
                            class="flex items-center gap-3 rounded-xl border border-slate-200 p-3 text-left transition hover:border-cyan-300 hover:bg-cyan-50/40"
                            @click="openService(service)"
                        >
                            <span class="text-xl">{{ service.icon }}</span>
                            <span class="min-w-0 flex-1">
                                <span
                                    class="block truncate text-sm font-medium text-slate-800"
                                    >{{ service.name }}</span
                                >
                                <span class="block text-xs text-slate-500"
                                    >{{ priceRange(service) }} · +{{
                                        service.stamps
                                    }}
                                    stempel</span
                                >
                            </span>
                            <Plus class="h-4 w-4 text-cyan-600" />
                        </button>
                    </div>

                    <transition
                        enter-active-class="transition duration-150"
                        enter-from-class="translate-y-1 opacity-0"
                        leave-active-class="transition duration-150"
                        leave-to-class="translate-y-1 opacity-0"
                    >
                        <div
                            v-if="canScrollDown"
                            class="pointer-events-none absolute inset-x-0 bottom-0 flex justify-center pb-1"
                        >
                            <button
                                type="button"
                                class="pointer-events-auto flex h-8 w-8 items-center justify-center rounded-full border border-slate-200 bg-white text-slate-500 shadow-md transition hover:border-cyan-300 hover:text-cyan-600"
                                aria-label="Gulir daftar layanan"
                                @click="scrollCatalogDown"
                            >
                                <ChevronDown class="h-4 w-4" />
                            </button>
                        </div>
                    </transition>
                </div>
                <p
                    v-else
                    class="rounded-xl border border-dashed border-slate-200 px-3 py-5 text-center text-xs text-slate-400"
                >
                    Layanan tidak ditemukan.
                </p>
            </div>
        </div>

        <div class="overflow-hidden rounded-2xl bg-slate-50">
            <button
                type="button"
                class="flex w-full items-center justify-between gap-3 px-4 pt-4 pb-3 text-left sm:pointer-events-none"
                :aria-expanded="openPanel === 'cart'"
                @click="togglePanel('cart')"
            >
                <span
                    class="flex items-center gap-2 text-xs font-semibold text-slate-700"
                >
                    <ShoppingCart class="h-4 w-4" /> Keranjang layanan
                </span>
                <span class="flex items-center gap-2 text-xs text-slate-500">
                    {{ modelValue.length }} item
                    <ChevronDown
                        class="h-4 w-4 transition sm:hidden"
                        :class="openPanel === 'cart' ? 'rotate-180' : ''"
                    />
                </span>
            </button>

            <div
                class="px-4 pb-4"
                :class="openPanel === 'cart' ? '' : 'hidden sm:block'"
            >
                <ul v-if="modelValue.length" class="space-y-2">
                    <li
                        v-for="item in modelValue"
                        :key="item.serviceVariationId"
                        class="flex items-center gap-3 rounded-xl bg-white p-3 ring-1 ring-slate-200"
                    >
                        <template v-if="cartDetails(item)">
                            <span class="text-lg">{{
                                cartDetails(item)?.service.icon
                            }}</span>
                            <div class="min-w-0 flex-1">
                                <p
                                    class="truncate text-sm font-medium text-slate-800"
                                >
                                    {{ cartDetails(item)?.service.name }}
                                </p>
                                <p
                                    v-if="
                                        variationLabel(
                                            cartDetails(item)!.variation,
                                        )
                                    "
                                    class="truncate text-xs text-slate-500"
                                >
                                    {{
                                        variationLabel(
                                            cartDetails(item)!.variation,
                                        )
                                    }}
                                </p>
                                <p class="text-xs font-semibold text-cyan-700">
                                    {{
                                        formatCurrency(
                                            cartDetails(item)!.variation.price *
                                                item.quantity,
                                        )
                                    }}
                                </p>
                            </div>
                            <div
                                class="flex items-center rounded-lg border border-slate-200"
                            >
                                <button
                                    type="button"
                                    class="p-1.5 text-slate-500 hover:text-cyan-600"
                                    @click="
                                        updateQuantity(
                                            item.serviceVariationId,
                                            item.quantity - 1,
                                        )
                                    "
                                >
                                    <Minus class="h-3.5 w-3.5" />
                                </button>
                                <span
                                    class="min-w-7 text-center text-xs font-semibold"
                                    >{{ item.quantity }}</span
                                >
                                <button
                                    type="button"
                                    class="p-1.5 text-slate-500 hover:text-cyan-600"
                                    @click="
                                        updateQuantity(
                                            item.serviceVariationId,
                                            item.quantity + 1,
                                        )
                                    "
                                >
                                    <Plus class="h-3.5 w-3.5" />
                                </button>
                            </div>
                            <button
                                type="button"
                                class="rounded-lg p-1.5 text-rose-500 hover:bg-rose-50"
                                aria-label="Hapus item"
                                @click="removeItem(item.serviceVariationId)"
                            >
                                <Trash2 class="h-4 w-4" />
                            </button>
                        </template>
                    </li>
                </ul>
                <p v-else class="text-xs text-slate-400">
                    Belum ada layanan di keranjang.
                </p>
            </div>
        </div>
    </div>

    <ModalDialog
        :open="activeService !== null"
        :title="activeService?.name"
        caption="Pilih satu nilai untuk setiap variasi dan tentukan quantity."
        size="md"
        layer="nested"
        @close="activeService = null"
    >
        <div v-if="activeService" class="space-y-5">
            <div
                v-for="(values, attribute) in activeService.variations ?? {}"
                :key="attribute"
                class="space-y-2"
            >
                <p class="text-xs font-semibold text-slate-700">
                    {{ attribute }}
                </p>
                <div class="flex flex-wrap gap-2">
                    <button
                        v-for="value in values"
                        :key="value"
                        type="button"
                        :disabled="!optionAvailable(attribute, value)"
                        class="rounded-xl border px-3 py-2 text-sm transition disabled:cursor-not-allowed disabled:opacity-35"
                        :class="
                            selectedValues[attribute] === value
                                ? 'border-cyan-500 bg-cyan-50 font-semibold text-cyan-700'
                                : 'border-slate-200 text-slate-700 hover:border-cyan-300'
                        "
                        @click="selectedValues[attribute] = value"
                    >
                        {{ value }}
                    </button>
                </div>
            </div>

            <div
                class="flex items-center justify-between gap-4 rounded-xl bg-slate-50 p-4"
            >
                <div>
                    <p class="text-xs text-slate-500">Quantity</p>
                    <p class="text-sm font-semibold text-slate-800">
                        {{
                            selectedVariation
                                ? formatCurrency(
                                      selectedVariation.price * quantity,
                                  )
                                : 'Lengkapi variasi'
                        }}
                    </p>
                </div>
                <div
                    class="flex items-center rounded-xl border border-slate-200 bg-white"
                >
                    <button
                        type="button"
                        class="p-2 text-slate-500"
                        @click="quantity = Math.max(1, quantity - 1)"
                    >
                        <Minus class="h-4 w-4" />
                    </button>
                    <input
                        v-model.number="quantity"
                        type="number"
                        min="1"
                        max="999"
                        class="w-14 border-x border-slate-200 py-2 text-center text-sm font-semibold focus:outline-none"
                    />
                    <button
                        type="button"
                        class="p-2 text-slate-500"
                        @click="quantity = Math.min(999, quantity + 1)"
                    >
                        <Plus class="h-4 w-4" />
                    </button>
                </div>
            </div>
        </div>
        <template #footer>
            <button
                type="button"
                class="flex-1 rounded-xl border border-slate-200 px-4 py-2.5 text-sm font-medium text-slate-600"
                @click="activeService = null"
            >
                Batal
            </button>
            <button
                type="button"
                :disabled="selectedVariation === null || quantity < 1"
                class="flex-1 rounded-xl bg-cyan-600 px-4 py-2.5 text-sm font-semibold text-white shadow-sm hover:bg-cyan-700 disabled:cursor-not-allowed disabled:opacity-50"
                @click="addToCart"
            >
                Tambah ke Keranjang
            </button>
        </template>
    </ModalDialog>
</template>
