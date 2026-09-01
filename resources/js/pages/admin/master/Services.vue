<script setup lang="ts">
import { Head, useForm } from '@inertiajs/vue3';
import {
    ChevronDown,
    ChevronRight,
    GripVertical,
    Pencil,
    Plus,
    Search,
    Sparkles,
    SprayCan,
    Trash2,
    X,
} from '@lucide/vue';
import { computed, onBeforeUnmount, ref, watch } from 'vue';
import {
    destroy as destroyService,
    store as storeService,
    update as updateService,
    updateOrder as updateServiceOrder,
} from '@/actions/App/Http/Controllers/Admin/Master/ServiceController';
import EmptyState from '@/components/demo/EmptyState.vue';
import ModalDialog from '@/components/demo/ModalDialog.vue';
import StatCard from '@/components/demo/StatCard.vue';
import StatusPill from '@/components/demo/StatusPill.vue';
import InputError from '@/components/InputError.vue';
import { formatCurrency } from '@/composables/useCarwashFormat';
import type { CarwashBrand } from '@/types/demo';

type ServiceIcon = { value: string; label: string };
type VariationRow = {
    id: number | null;
    variations: Record<string, string> | null;
    price: number;
    is_active: boolean;
    order_count: number;
};
type Service = {
    id: number;
    name: string;
    category: string;
    price: number;
    variations: Record<string, string[]> | null;
    service_variations: VariationRow[];
    stamps: number;
    icon: string;
    description: string;
    is_popular: boolean;
    is_active: boolean;
    sort_order: number;
    order_count: number;
};
type VariationAttribute = { name: string; values: string[] };

const props = defineProps<{
    mode: 'demo' | 'live';
    brand: CarwashBrand;
    services: Service[];
    categories: string[];
    icons: ServiceIcon[];
    capabilities: { create: boolean; update: boolean; delete: boolean };
}>();

const serviceList = ref(props.services.map(cloneService));
const query = ref('');
/** An empty selection means every category, matching the "Semua" chip. */
const selectedCategories = ref<string[]>([]);
const expandedIds = ref<number[]>([]);
const isFormOpen = ref(false);
const editingId = ref<number | null>(null);
const deletingService = ref<Service | null>(null);
const isSorting = ref(false);
const orderSnapshot = ref<number[]>([]);
const draggingId = ref<number | null>(null);
const listRef = ref<HTMLElement | null>(null);
const variationAttributes = ref<VariationAttribute[]>([]);

const serviceForm = useForm({
    name: '',
    category: '',
    variations: null as Record<string, string[]> | null,
    service_variations: [
        { id: null, variations: null, price: 0, is_active: true },
    ] as VariationRow[],
    stamps: 0,
    icon: props.icons[0]?.value ?? 'sparkles',
    description: '',
    is_popular: false,
    is_active: true,
});
const deleteForm = useForm({});
const orderForm = useForm({ ids: [] as number[] });

watch(
    () => props.services,
    (services) => {
        if (props.mode === 'live') {
            serviceList.value = services.map(cloneService);
            orderSnapshot.value = serviceList.value.map(
                (service) => service.id,
            );
        }
    },
);

const categoryOptions = computed(() => [
    ...new Set(serviceList.value.map((service) => service.category)),
]);
const filteredServices = computed(() => {
    const tokens = query.value.toLowerCase().split(/\s+/).filter(Boolean);

    return serviceList.value.filter((service) => {
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
const activeCount = computed(
    () => serviceList.value.filter((service) => service.is_active).length,
);
/** Reordering a filtered subset would move rows the operator cannot see. */
const visibleServices = computed(() =>
    isSorting.value ? serviceList.value : filteredServices.value,
);
const isOrderDirty = computed(() =>
    serviceList.value.some(
        (service, index) => service.id !== orderSnapshot.value[index],
    ),
);

function categoryCount(option: string): number {
    return serviceList.value.filter((service) => service.category === option)
        .length;
}

function toggleCategory(option: string): void {
    selectedCategories.value = selectedCategories.value.includes(option)
        ? selectedCategories.value.filter((selected) => selected !== option)
        : [...selectedCategories.value, option];
}

function cloneService(service: Service): Service {
    return {
        ...service,
        variations: cloneVariationConfiguration(service.variations),
        service_variations: service.service_variations.map((variation) => ({
            ...variation,
            variations: variation.variations
                ? { ...variation.variations }
                : null,
        })),
    };
}

function cloneVariationConfiguration(
    variations: Record<string, string[]> | null,
): Record<string, string[]> | null {
    if (variations === null) {
        return null;
    }

    return Object.fromEntries(
        Object.entries(variations).map(([attribute, values]) => [
            attribute,
            [...values],
        ]),
    );
}

function openCreate(): void {
    editingId.value = null;
    variationAttributes.value = [];
    serviceForm.clearErrors();
    serviceForm.defaults({
        name: '',
        category: props.categories[0] ?? '',
        variations: null,
        service_variations: [
            {
                id: null,
                variations: null,
                price: 0,
                is_active: true,
                order_count: 0,
            },
        ],
        stamps: 0,
        icon: props.icons[0]?.value ?? 'sparkles',
        description: '',
        is_popular: false,
        is_active: true,
    });
    serviceForm.reset();
    isFormOpen.value = true;
}

function openEdit(service: Service): void {
    editingId.value = service.id;
    variationAttributes.value = Object.entries(service.variations ?? {}).map(
        ([name, values]) => ({ name, values: [...values] }),
    );
    serviceForm.clearErrors();
    serviceForm.defaults({
        name: service.name,
        category: service.category,
        variations: cloneVariationConfiguration(service.variations),
        service_variations: service.service_variations.map((variation) => ({
            ...variation,
            variations: variation.variations
                ? { ...variation.variations }
                : null,
        })),
        stamps: service.stamps,
        icon: service.icon,
        description: service.description,
        is_popular: service.is_popular,
        is_active: service.is_active,
    });
    serviceForm.reset();
    isFormOpen.value = true;
}

function addAttribute(): void {
    variationAttributes.value.push({
        name: variationAttributes.value.length
            ? `Variasi ${variationAttributes.value.length + 1}`
            : 'Ukuran',
        values: [''],
    });
    regenerateVariations();
}

function removeAttribute(index: number): void {
    variationAttributes.value.splice(index, 1);
    regenerateVariations();
}

function addValue(index: number): void {
    variationAttributes.value[index]?.values.push('');
    regenerateVariations();
}

function removeValue(attributeIndex: number, valueIndex: number): void {
    variationAttributes.value[attributeIndex]?.values.splice(valueIndex, 1);
    regenerateVariations();
}

function regenerateVariations(): void {
    const configuration = Object.fromEntries(
        variationAttributes.value
            .map(
                (attribute) =>
                    [
                        attribute.name.trim(),
                        attribute.values
                            .map((value) => value.trim())
                            .filter(Boolean),
                    ] as const,
            )
            .filter(([name, values]) => name !== '' && values.length > 0),
    );
    serviceForm.variations = Object.keys(configuration).length
        ? configuration
        : null;
    const combinations = buildCombinations(configuration);
    const existing = new Map(
        serviceForm.service_variations.map((variation) => [
            signature(variation.variations),
            variation,
        ]),
    );
    serviceForm.service_variations = combinations.map((values) => {
        const retained = existing.get(signature(values));

        return retained
            ? { ...retained, variations: values }
            : {
                  id: null,
                  variations: values,
                  price: 0,
                  is_active: true,
                  order_count: 0,
              };
    });
}

function buildCombinations(
    configuration: Record<string, string[]>,
): (Record<string, string> | null)[] {
    if (!Object.keys(configuration).length) {
        return [null];
    }

    let combinations: Record<string, string>[] = [{}];

    for (const [attribute, values] of Object.entries(configuration)) {
        combinations = combinations.flatMap((combination) =>
            values.map((value) => ({ ...combination, [attribute]: value })),
        );
    }

    return combinations;
}

function signature(values: Record<string, string> | null): string {
    return JSON.stringify(values);
}

function variationLabel(values: Record<string, string> | null): string {
    return (
        Object.entries(values ?? {})
            .map(([key, value]) => `${key}: ${value}`)
            .join(' · ') || 'Harga default'
    );
}

function priceRange(service: Service): string {
    const prices = service.service_variations
        .filter((variation) => variation.is_active)
        .map((variation) => variation.price);

    if (!prices.length) {
        return 'Tidak tersedia';
    }

    const min = Math.min(...prices);
    const max = Math.max(...prices);

    return min === max
        ? formatCurrency(min)
        : `${formatCurrency(min)}–${formatCurrency(max)}`;
}

function submitService(): void {
    regenerateVariations();

    if (props.mode === 'demo') {
        saveDemoService();

        return;
    }

    const action =
        editingId.value === null
            ? storeService()
            : updateService(editingId.value);
    serviceForm.submit(action, {
        preserveScroll: true,
        onSuccess: () => {
            isFormOpen.value = false;
            serviceForm.reset();
        },
    });
}

function saveDemoService(): void {
    const id =
        editingId.value ??
        Math.max(0, ...serviceList.value.map((service) => service.id)) + 1;
    const service: Service = {
        id,
        name: serviceForm.name,
        category: serviceForm.category,
        price: Math.min(
            ...serviceForm.service_variations.map(
                (variation) => variation.price,
            ),
        ),
        variations: serviceForm.variations,
        service_variations: serviceForm.service_variations.map(
            (variation, index) => ({
                ...variation,
                id: variation.id ?? id * 100 + index + 1,
            }),
        ),
        stamps: serviceForm.stamps,
        icon: serviceForm.icon,
        description: serviceForm.description,
        is_popular: serviceForm.is_popular,
        is_active: serviceForm.is_active,
        sort_order:
            editingId.value === null
                ? serviceList.value.length + 1
                : (serviceList.value.find((item) => item.id === id)
                      ?.sort_order ?? 0),
        order_count: serviceForm.service_variations.reduce(
            (sum, variation) => sum + variation.order_count,
            0,
        ),
    };
    serviceList.value =
        editingId.value === null
            ? [...serviceList.value, service]
            : serviceList.value.map((item) =>
                  item.id === id ? service : item,
              );
    isFormOpen.value = false;
}

function confirmDelete(): void {
    if (!deletingService.value) {
        return;
    }

    const id = deletingService.value.id;

    if (props.mode === 'demo') {
        serviceList.value = serviceList.value.filter(
            (service) => service.id !== id,
        );
        deletingService.value = null;

        return;
    }

    deleteForm.submit(destroyService(id), {
        preserveScroll: true,
        onSuccess: () => {
            deletingService.value = null;
        },
    });
}

function toggleExpanded(id: number): void {
    if (isSorting.value) {
        return;
    }

    expandedIds.value = expandedIds.value.includes(id)
        ? expandedIds.value.filter((candidate) => candidate !== id)
        : [...expandedIds.value, id];
}

function startSorting(): void {
    query.value = '';
    selectedCategories.value = [];
    expandedIds.value = [];
    orderSnapshot.value = serviceList.value.map((service) => service.id);
    orderForm.clearErrors();
    isSorting.value = true;
}

function cancelSorting(): void {
    const snapshot = orderSnapshot.value;
    serviceList.value = [...serviceList.value].sort(
        (a, b) => snapshot.indexOf(a.id) - snapshot.indexOf(b.id),
    );
    stopDrag();
    isSorting.value = false;
}

function moveService(from: number, to: number): void {
    if (from === to || to < 0 || to >= serviceList.value.length) {
        return;
    }

    const rows = [...serviceList.value];
    const [moved] = rows.splice(from, 1);
    rows.splice(to, 0, moved!);
    serviceList.value = rows;
}

function moveServiceByKey(service: Service, direction: -1 | 1): void {
    const from = serviceList.value.findIndex(
        (candidate) => candidate.id === service.id,
    );
    moveService(from, from + direction);
}

/**
 * The drag runs on pointer events instead of the native HTML5 drag so the
 * operator keeps the mouse wheel while holding a row, and so the page can be
 * walked by hand while the pointer rests against a viewport edge.
 */
const AUTO_SCROLL_EDGE = 120;
const AUTO_SCROLL_MAX_STEP = 24;

let pointerY = 0;
let dragFrame: number | null = null;

/**
 * The move is tracked on the document rather than through pointer capture:
 * capture is dropped as soon as the row is re-inserted somewhere else, which
 * cut every upward drag short the moment the page scrolled.
 */
function startDrag(service: Service, event: PointerEvent): void {
    if (!isSorting.value || event.button !== 0) {
        return;
    }

    event.preventDefault();
    draggingId.value = service.id;
    pointerY = event.clientY;
    document.addEventListener('pointermove', trackDrag);
    document.addEventListener('pointerup', stopDrag);
    document.addEventListener('pointercancel', stopDrag);
    document.body.style.userSelect = 'none';

    if (dragFrame === null) {
        dragFrame = requestAnimationFrame(stepDrag);
    }
}

function trackDrag(event: PointerEvent): void {
    if (draggingId.value === null) {
        return;
    }

    pointerY = event.clientY;
}

function stopDrag(): void {
    document.removeEventListener('pointermove', trackDrag);
    document.removeEventListener('pointerup', stopDrag);
    document.removeEventListener('pointercancel', stopDrag);
    document.body.style.removeProperty('user-select');
    draggingId.value = null;

    if (dragFrame !== null) {
        cancelAnimationFrame(dragFrame);
        dragFrame = null;
    }
}

function stepDrag(): void {
    if (draggingId.value === null) {
        dragFrame = null;

        return;
    }

    autoScroll();
    settleDraggedRow();
    dragFrame = requestAnimationFrame(stepDrag);
}

function autoScroll(): void {
    const bottomEdge = window.innerHeight - AUTO_SCROLL_EDGE;
    let distance = 0;

    if (pointerY < AUTO_SCROLL_EDGE) {
        distance = pointerY - AUTO_SCROLL_EDGE;
    } else if (pointerY > bottomEdge) {
        distance = pointerY - bottomEdge;
    }

    if (distance !== 0) {
        window.scrollBy(
            0,
            (distance / AUTO_SCROLL_EDGE) * AUTO_SCROLL_MAX_STEP,
        );
    }
}

/** Rows are re-measured every frame so wheel and auto scrolling stay honest. */
function settleDraggedRow(): void {
    const rows =
        listRef.value?.querySelectorAll<HTMLElement>('[data-service-row]');
    const from = serviceList.value.findIndex(
        (service) => service.id === draggingId.value,
    );

    if (!rows?.length || from === -1) {
        return;
    }

    const first = rows[0]!.getBoundingClientRect();
    const last = rows[rows.length - 1]!.getBoundingClientRect();

    if (pointerY <= first.top) {
        moveService(from, 0);

        return;
    }

    if (pointerY >= last.bottom) {
        moveService(from, rows.length - 1);

        return;
    }

    for (let index = 0; index < rows.length; index += 1) {
        const rect = rows[index]!.getBoundingClientRect();

        if (pointerY >= rect.top && pointerY <= rect.bottom) {
            moveService(from, index);

            return;
        }
    }
}

onBeforeUnmount(stopDrag);

function saveOrder(): void {
    stopDrag();

    if (props.mode === 'demo') {
        orderSnapshot.value = serviceList.value.map((service) => service.id);
        isSorting.value = false;

        return;
    }

    orderForm.ids = serviceList.value.map((service) => service.id);
    orderForm.submit(updateServiceOrder(), {
        preserveScroll: true,
        onSuccess: () => {
            orderSnapshot.value = serviceList.value.map(
                (service) => service.id,
            );
            isSorting.value = false;
        },
    });
}
</script>

<template>
    <Head :title="`${brand.name} — Master Layanan`" />
    <div class="space-y-4">
        <div class="grid gap-4 sm:grid-cols-2">
            <StatCard
                label="Total layanan"
                :value="String(serviceList.length)"
                caption="Service logis"
                :icon="SprayCan"
            />
            <StatCard
                label="Layanan aktif"
                :value="String(activeCount)"
                caption="Tampil di order"
                :icon="Sparkles"
                tone="emerald"
            />
        </div>

        <section
            class="overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-sm"
        >
            <div
                class="flex flex-col gap-3 border-b border-slate-100 p-5 sm:flex-row sm:items-center sm:justify-between"
            >
                <div>
                    <h2 class="text-sm font-semibold text-slate-900">
                        Daftar layanan
                    </h2>
                    <p class="text-xs text-slate-500">
                        {{ visibleServices.length }} layanan ditampilkan
                    </p>
                </div>
                <div
                    class="flex w-full items-center gap-2 rounded-xl border border-slate-300 px-3 py-2 sm:w-72"
                    :class="isSorting ? 'opacity-50' : ''"
                >
                    <Search class="h-4 w-4 text-slate-400" /><input
                        v-model="query"
                        :disabled="isSorting"
                        class="min-w-0 flex-1 text-sm focus:outline-none disabled:cursor-not-allowed"
                        placeholder="Cari layanan / variasi"
                    />
                </div>
            </div>

            <div
                class="flex flex-wrap gap-2 border-b border-slate-100 px-5 py-3"
                :class="isSorting ? 'pointer-events-none opacity-50' : ''"
            >
                <button
                    type="button"
                    class="rounded-full px-3 py-1.5 text-xs font-semibold transition"
                    :class="
                        selectedCategories.length
                            ? 'border border-slate-200 bg-white text-slate-600 hover:border-cyan-300 hover:text-cyan-700'
                            : 'bg-cyan-600 text-white shadow-sm shadow-cyan-600/30'
                    "
                    @click="selectedCategories = []"
                >
                    Semua
                    <span
                        class="ml-1 tabular-nums"
                        :class="
                            selectedCategories.length
                                ? 'text-slate-400'
                                : 'text-white/70'
                        "
                        >{{ serviceList.length }}</span
                    >
                </button>
                <button
                    v-for="option in categoryOptions"
                    :key="option"
                    type="button"
                    class="rounded-full px-3 py-1.5 text-xs font-semibold transition"
                    :class="
                        selectedCategories.includes(option)
                            ? 'bg-cyan-600 text-white shadow-sm shadow-cyan-600/30'
                            : 'border border-slate-200 bg-white text-slate-600 hover:border-cyan-300 hover:text-cyan-700'
                    "
                    @click="toggleCategory(option)"
                >
                    {{ option }}
                    <span
                        class="ml-1 tabular-nums"
                        :class="
                            selectedCategories.includes(option)
                                ? 'text-white/70'
                                : 'text-slate-400'
                        "
                        >{{ categoryCount(option) }}</span
                    >
                </button>
            </div>

            <div
                class="flex flex-wrap items-center gap-2 border-b border-slate-100 px-5 py-3"
            >
                <button
                    v-if="capabilities.create"
                    type="button"
                    :disabled="isSorting"
                    class="inline-flex items-center gap-2 rounded-xl bg-cyan-600 px-4 py-2 text-sm font-semibold text-white shadow-sm hover:bg-cyan-700 disabled:opacity-40"
                    @click="openCreate"
                >
                    <Plus class="h-4 w-4" />Tambah Layanan
                </button>
                <button
                    v-if="capabilities.update"
                    type="button"
                    class="inline-flex items-center gap-2 rounded-xl border border-slate-300 px-3 py-2 text-sm font-medium text-slate-600 hover:border-cyan-300 hover:text-cyan-700"
                    :class="isSorting ? 'border-cyan-300 text-cyan-700' : ''"
                    @click="isSorting ? cancelSorting() : startSorting()"
                >
                    <GripVertical class="h-4 w-4" />{{
                        isSorting ? 'Batal ubah urutan' : 'Ubah urutan'
                    }}
                </button>
            </div>

            <div
                v-if="visibleServices.length"
                ref="listRef"
                class="divide-y divide-slate-100"
                :class="draggingId !== null ? 'select-none' : ''"
            >
                <article
                    v-for="service in visibleServices"
                    :key="service.id"
                    data-service-row
                    class="bg-white transition"
                    :class="[
                        isSorting
                            ? 'cursor-grab select-none active:cursor-grabbing'
                            : '',
                        draggingId === service.id
                            ? 'relative z-10 rounded-xl shadow-lg ring-2 shadow-cyan-500/20 ring-cyan-400'
                            : '',
                    ]"
                    draggable="false"
                    @dragstart.prevent
                    @pointerdown="startDrag(service, $event)"
                >
                    <div
                        class="grid items-center gap-3 px-5 py-4 md:grid-cols-[minmax(0,2fr)_1fr_1fr_auto]"
                    >
                        <div class="flex min-w-0 items-center gap-3">
                            <button
                                v-if="isSorting"
                                type="button"
                                class="-ml-1 touch-none rounded-lg p-1.5 text-slate-400 hover:bg-slate-100 hover:text-slate-600"
                                :class="
                                    draggingId === service.id
                                        ? 'bg-cyan-50 text-cyan-600'
                                        : ''
                                "
                                :aria-label="`Geser posisi ${service.name}`"
                                title="Seret baris untuk mengubah urutan"
                                @keydown.up.prevent="
                                    moveServiceByKey(service, -1)
                                "
                                @keydown.down.prevent="
                                    moveServiceByKey(service, 1)
                                "
                            >
                                <GripVertical class="h-4 w-4" />
                            </button>
                            <button
                                type="button"
                                class="flex min-w-0 flex-1 items-center gap-3 text-left"
                                :class="isSorting ? 'pointer-events-none' : ''"
                                @click="toggleExpanded(service.id)"
                            >
                                <ChevronDown
                                    v-if="expandedIds.includes(service.id)"
                                    class="h-4 w-4 text-slate-400"
                                /><ChevronRight
                                    v-else-if="!isSorting"
                                    class="h-4 w-4 text-slate-400"
                                />
                                <span
                                    class="flex h-10 w-10 items-center justify-center rounded-xl bg-cyan-50 text-xl"
                                    >{{ service.icon }}</span
                                >
                                <span class="min-w-0"
                                    ><span
                                        class="block truncate text-sm font-semibold text-slate-900"
                                        >{{ service.name }}</span
                                    ><span
                                        class="block truncate text-xs text-slate-500"
                                        >{{ service.description }}</span
                                    ></span
                                >
                            </button>
                        </div>
                        <div>
                            <p class="text-xs text-slate-500">
                                {{ service.category }}
                            </p>
                            <p class="text-sm font-semibold text-slate-800">
                                {{ priceRange(service) }}
                            </p>
                        </div>
                        <div class="flex items-center gap-2">
                            <StatusPill
                                :status="
                                    service.is_active ? 'aktif' : 'nonaktif'
                                "
                            /><span class="text-xs text-slate-500"
                                >{{ service.order_count }} order</span
                            >
                        </div>
                        <div class="flex items-center justify-end gap-1">
                            <template v-if="!isSorting"
                                ><button
                                    v-if="capabilities.update"
                                    type="button"
                                    class="rounded-lg p-2 text-cyan-600 hover:bg-cyan-50"
                                    @click="openEdit(service)"
                                >
                                    <Pencil class="h-4 w-4" /></button
                                ><button
                                    v-if="capabilities.delete"
                                    type="button"
                                    class="rounded-lg p-2 text-rose-500 hover:bg-rose-50"
                                    @click="deletingService = service"
                                >
                                    <Trash2 class="h-4 w-4" /></button
                            ></template>
                        </div>
                    </div>
                    <div
                        v-if="!isSorting && expandedIds.includes(service.id)"
                        class="border-t border-slate-100 bg-slate-50/70 px-5 py-4"
                    >
                        <div
                            class="overflow-x-auto rounded-xl border border-slate-200 bg-white"
                        >
                            <table class="w-full text-left text-xs">
                                <thead class="bg-slate-50 text-slate-500">
                                    <tr>
                                        <th class="px-4 py-2.5">Kombinasi</th>
                                        <th class="px-4 py-2.5">Harga</th>
                                        <th class="px-4 py-2.5">Status</th>
                                        <th class="px-4 py-2.5">Dipakai</th>
                                    </tr>
                                </thead>
                                <tbody class="divide-y divide-slate-100">
                                    <tr
                                        v-for="variation in service.service_variations"
                                        :key="
                                            variation.id ??
                                            signature(variation.variations)
                                        "
                                    >
                                        <td
                                            class="px-4 py-3 font-medium text-slate-800"
                                        >
                                            {{
                                                variationLabel(
                                                    variation.variations,
                                                )
                                            }}
                                        </td>
                                        <td class="px-4 py-3 tabular-nums">
                                            {{
                                                formatCurrency(variation.price)
                                            }}
                                        </td>
                                        <td class="px-4 py-3">
                                            <StatusPill
                                                :status="
                                                    variation.is_active
                                                        ? 'aktif'
                                                        : 'nonaktif'
                                                "
                                            />
                                        </td>
                                        <td class="px-4 py-3 text-slate-500">
                                            {{ variation.order_count }} order
                                        </td>
                                    </tr>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </article>
            </div>
            <EmptyState
                v-else
                title="Layanan tidak ditemukan"
                description="Ubah kata pencarian atau kategori."
            />
        </section>
        <div v-if="isSorting" class="h-24" aria-hidden="true"></div>
    </div>

    <Teleport to="body">
        <Transition
            enter-active-class="transition duration-200"
            enter-from-class="translate-y-6 opacity-0"
            leave-active-class="transition duration-150"
            leave-to-class="translate-y-6 opacity-0"
        >
            <div
                v-if="isSorting"
                class="fixed inset-x-0 bottom-6 z-50 flex justify-center px-4"
            >
                <div
                    class="flex w-full max-w-2xl flex-col gap-3 rounded-2xl bg-slate-900 px-5 py-4 text-white shadow-2xl shadow-slate-900/30 sm:flex-row sm:items-center sm:justify-between"
                >
                    <p
                        class="flex items-center gap-2 text-xs text-slate-300 sm:text-sm"
                    >
                        <GripVertical class="h-4 w-4 shrink-0 text-cyan-300" />
                        <span>
                            Seret baris layanan untuk mengatur urutan.
                            <strong
                                v-if="isOrderDirty"
                                class="font-semibold text-amber-300"
                                >Urutan belum disimpan.</strong
                            >
                        </span>
                    </p>
                    <div class="flex shrink-0 items-center gap-2">
                        <button
                            type="button"
                            class="rounded-xl px-4 py-2 text-sm font-medium text-slate-300 hover:text-white"
                            @click="cancelSorting"
                        >
                            Batal
                        </button>
                        <button
                            type="button"
                            :disabled="orderForm.processing || !isOrderDirty"
                            class="rounded-xl bg-cyan-500 px-4 py-2 text-sm font-semibold text-white hover:bg-cyan-400 disabled:opacity-40"
                            @click="saveOrder"
                        >
                            {{
                                orderForm.processing
                                    ? 'Menyimpan...'
                                    : 'Simpan urutan'
                            }}
                        </button>
                    </div>
                </div>
            </div>
        </Transition>
    </Teleport>

    <ModalDialog
        :open="isFormOpen"
        :title="editingId === null ? 'Tambah Layanan' : 'Edit Layanan'"
        caption="Harga disimpan pada setiap kombinasi variation."
        size="xl"
        @close="isFormOpen = false"
    >
        <form
            id="service-form"
            class="space-y-5"
            @submit.prevent="submitService"
        >
            <div class="grid gap-4 sm:grid-cols-2">
                <label class="space-y-1 text-xs font-medium text-slate-600"
                    >Nama<input
                        v-model="serviceForm.name"
                        class="w-full rounded-xl border border-slate-300 px-3 py-2.5 text-sm"
                        required /><InputError
                        :message="serviceForm.errors.name"
                /></label>
                <label class="space-y-1 text-xs font-medium text-slate-600"
                    >Kategori<input
                        v-model="serviceForm.category"
                        list="service-categories"
                        class="w-full rounded-xl border border-slate-300 px-3 py-2.5 text-sm"
                        required /><datalist id="service-categories">
                        <option
                            v-for="option in categoryOptions"
                            :key="option"
                            :value="option"
                        /></datalist
                    ><InputError :message="serviceForm.errors.category"
                /></label>
                <label class="space-y-1 text-xs font-medium text-slate-600"
                    >Icon<select
                        v-model="serviceForm.icon"
                        class="w-full rounded-xl border border-slate-300 px-3 py-2.5 text-sm"
                    >
                        <option
                            v-for="icon in icons"
                            :key="icon.value"
                            :value="icon.value"
                        >
                            {{ icon.label }}
                        </option>
                    </select></label
                >
                <label class="space-y-1 text-xs font-medium text-slate-600"
                    >Stempel<input
                        v-model.number="serviceForm.stamps"
                        type="number"
                        min="0"
                        max="999"
                        class="w-full rounded-xl border border-slate-300 px-3 py-2.5 text-sm"
                /></label>
            </div>
            <label class="block space-y-1 text-xs font-medium text-slate-600"
                >Deskripsi<textarea
                    v-model="serviceForm.description"
                    rows="2"
                    class="w-full rounded-xl border border-slate-300 px-3 py-2.5 text-sm"
                />
            </label>
            <div class="flex flex-wrap gap-4">
                <label class="flex items-center gap-2 text-sm text-slate-700"
                    ><input
                        v-model="serviceForm.is_active"
                        type="checkbox"
                        class="rounded border-slate-300 text-cyan-600"
                    />Layanan aktif</label
                ><label class="flex items-center gap-2 text-sm text-slate-700"
                    ><input
                        v-model="serviceForm.is_popular"
                        type="checkbox"
                        class="rounded border-slate-300 text-cyan-600"
                    />Layanan populer</label
                >
            </div>

            <section class="rounded-2xl border border-slate-200 p-4">
                <div class="flex items-center justify-between gap-3">
                    <div>
                        <h3 class="text-sm font-semibold text-slate-900">
                            Jenis variation
                        </h3>
                        <p class="text-xs text-slate-500">
                            Kosongkan untuk layanan dengan satu harga default.
                        </p>
                    </div>
                    <button
                        type="button"
                        class="inline-flex items-center gap-1 rounded-lg border border-cyan-200 px-3 py-2 text-xs font-semibold text-cyan-700"
                        @click="addAttribute"
                    >
                        <Plus class="h-3.5 w-3.5" />Tambah jenis
                    </button>
                </div>
                <div class="mt-4 space-y-3">
                    <div
                        v-for="(
                            attribute, attributeIndex
                        ) in variationAttributes"
                        :key="attributeIndex"
                        class="rounded-xl bg-slate-50 p-3"
                    >
                        <div class="flex gap-2">
                            <input
                                v-model="attribute.name"
                                class="min-w-0 flex-1 rounded-lg border border-slate-300 px-3 py-2 text-sm"
                                placeholder="Contoh: Ukuran"
                                @input="regenerateVariations"
                            /><button
                                type="button"
                                class="rounded-lg p-2 text-rose-500"
                                @click="removeAttribute(attributeIndex)"
                            >
                                <Trash2 class="h-4 w-4" />
                            </button>
                        </div>
                        <div class="mt-2 flex flex-wrap gap-2">
                            <div
                                v-for="(_, valueIndex) in attribute.values"
                                :key="valueIndex"
                                class="flex items-center rounded-lg border border-slate-300 bg-white"
                            >
                                <input
                                    v-model="attribute.values[valueIndex]"
                                    class="w-28 px-2.5 py-1.5 text-sm focus:outline-none"
                                    placeholder="Nilai"
                                    @input="regenerateVariations"
                                /><button
                                    type="button"
                                    class="p-1.5 text-slate-400 hover:text-rose-500"
                                    @click="
                                        removeValue(attributeIndex, valueIndex)
                                    "
                                >
                                    <X class="h-3.5 w-3.5" />
                                </button>
                            </div>
                            <button
                                type="button"
                                class="rounded-lg border border-dashed border-slate-300 px-2.5 py-1.5 text-xs text-slate-500"
                                @click="addValue(attributeIndex)"
                            >
                                + Nilai
                            </button>
                        </div>
                    </div>
                </div>
            </section>

            <section>
                <h3 class="text-sm font-semibold text-slate-900">
                    Kombinasi dan harga
                </h3>
                <div
                    class="mt-2 overflow-x-auto rounded-xl border border-slate-200"
                >
                    <table class="w-full text-left text-xs">
                        <thead class="bg-slate-50 text-slate-500">
                            <tr>
                                <th class="px-3 py-2.5">Kombinasi</th>
                                <th class="px-3 py-2.5">Harga</th>
                                <th class="px-3 py-2.5">Aktif</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-slate-100">
                            <tr
                                v-for="(
                                    variation, index
                                ) in serviceForm.service_variations"
                                :key="
                                    variation.id ??
                                    signature(variation.variations)
                                "
                            >
                                <td
                                    class="px-3 py-3 font-medium text-slate-800"
                                >
                                    {{ variationLabel(variation.variations) }}
                                </td>
                                <td class="px-3 py-3">
                                    <input
                                        v-model.number="variation.price"
                                        type="number"
                                        min="0"
                                        max="999999999"
                                        class="w-40 rounded-lg border border-slate-300 px-2.5 py-2 text-sm"
                                        required
                                    />
                                </td>
                                <td class="px-3 py-3">
                                    <input
                                        v-model="variation.is_active"
                                        type="checkbox"
                                        class="rounded border-slate-300 text-cyan-600"
                                    /><InputError
                                        :message="
                                            serviceForm.errors[
                                                `service_variations.${index}.price`
                                            ]
                                        "
                                    />
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>
                <InputError
                    class="mt-1"
                    :message="serviceForm.errors.service_variations"
                />
            </section>
        </form>
        <template #footer
            ><button
                type="button"
                class="flex-1 rounded-xl border border-slate-200 py-2.5 text-sm font-medium text-slate-600"
                @click="isFormOpen = false"
            >
                Batal</button
            ><button
                form="service-form"
                :disabled="serviceForm.processing"
                class="flex-1 rounded-xl bg-cyan-600 py-2.5 text-sm font-semibold text-white disabled:opacity-50"
            >
                {{ serviceForm.processing ? 'Menyimpan...' : 'Simpan layanan' }}
            </button></template
        >
    </ModalDialog>

    <ModalDialog
        :open="deletingService !== null"
        title="Hapus layanan?"
        size="sm"
        @close="deletingService = null"
        ><p class="text-sm text-slate-600">
            Layanan <strong>{{ deletingService?.name }}</strong> hanya dapat
            dihapus jika belum pernah dipakai order.
        </p>
        <template #footer
            ><button
                type="button"
                class="flex-1 rounded-xl border border-slate-200 py-2.5 text-sm"
                @click="deletingService = null"
            >
                Batal</button
            ><button
                type="button"
                :disabled="deleteForm.processing"
                class="flex-1 rounded-xl bg-rose-600 py-2.5 text-sm font-semibold text-white"
                @click="confirmDelete"
            >
                Hapus
            </button></template
        ></ModalDialog
    >
</template>
