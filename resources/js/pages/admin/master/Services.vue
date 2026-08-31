<script setup lang="ts">
import { Head, useForm, usePage } from '@inertiajs/vue3';
import {
    ArrowUpDown,
    ChevronDown,
    ChevronUp,
    GripVertical,
    Pencil,
    Plus,
    Save,
    Sparkles,
    SprayCan,
    Trash2,
    Wallet,
    X,
} from '@lucide/vue';
import { computed, onUnmounted, ref, watch } from 'vue';
import {
    destroy as destroyService,
    store as storeService,
    update as updateService,
    updateOrder as updateServiceOrder,
} from '@/actions/App/Http/Controllers/Admin/Master/ServiceController';
import DataToolbar from '@/components/demo/DataToolbar.vue';
import EmptyState from '@/components/demo/EmptyState.vue';
import ModalDialog from '@/components/demo/ModalDialog.vue';
import StatCard from '@/components/demo/StatCard.vue';
import StatusPill from '@/components/demo/StatusPill.vue';
import InputError from '@/components/InputError.vue';
import { formatCurrency } from '@/composables/useCarwashFormat';
import type { CarwashBrand } from '@/types/demo';

type ServiceIcon = {
    value: string;
    label: string;
};

type Service = {
    id: number;
    name: string;
    category: string;
    price: number;
    stamps: number;
    icon: string;
    description: string;
    is_popular: boolean;
    is_active: boolean;
    sort_order: number;
    order_count: number;
};

const props = defineProps<{
    mode: 'demo' | 'live';
    brand: CarwashBrand;
    services: Service[];
    categories: string[];
    icons: ServiceIcon[];
    capabilities: {
        create: boolean;
        update: boolean;
        delete: boolean;
    };
}>();

const serviceList = ref(props.services.map((service) => ({ ...service })));
const search = ref('');
const categoryFilter = ref('Semua');
const editingServiceId = ref<number | null>(null);
const isServiceFormOpen = ref(false);
const deletingService = ref<Service | null>(null);
const isReorderMode = ref(false);
const orderSnapshot = ref<number[]>([]);
const draggingIndex = ref<number | null>(null);
const dropIndex = ref<number | null>(null);
const orderForm = useForm<{ ids: number[] }>({ ids: [] });

watch(
    () => props.services,
    (services) => {
        if (props.mode !== 'live') {
            return;
        }

        /**
         * A reload that lands mid-reorder would throw away the rows the
         * operator has already dragged, so only the save round-trip
         * (still `processing` while props swap) is allowed through.
         */
        if (isReorderMode.value && !orderForm.processing) {
            return;
        }

        serviceList.value = services.map((service) => ({ ...service }));
        orderSnapshot.value = serviceList.value.map((service) => service.id);
    },
);

const defaultIcon = props.icons[0]?.value ?? '';

const serviceForm = useForm({
    name: '',
    category: '',
    price: 0,
    stamps: 0,
    icon: defaultIcon,
    description: '',
    is_popular: false,
    is_active: true,
});

const deleteForm = useForm({});
const page = usePage<{ errors: Record<string, string> }>();

/** The destroy endpoint rejects services already used by an order. */
const deleteError = computed(() => page.props.errors.service ?? '');

const filterOptions = computed(() => ['Semua', ...props.categories]);

const filteredServices = computed(() => {
    const query = search.value.trim().toLowerCase();

    return serviceList.value.filter((service) => {
        const matchesCategory =
            categoryFilter.value === 'Semua' ||
            service.category === categoryFilter.value;
        const matchesQuery =
            query === '' ||
            service.name.toLowerCase().includes(query) ||
            service.category.toLowerCase().includes(query) ||
            service.description.toLowerCase().includes(query);

        return matchesCategory && matchesQuery;
    });
});

/** Dragging a filtered subset would reorder rows the operator cannot see. */
const visibleServices = computed(() =>
    isReorderMode.value ? serviceList.value : filteredServices.value,
);

const isOrderDirty = computed(() =>
    serviceList.value.some(
        (service, index) => service.id !== orderSnapshot.value[index],
    ),
);

const activeCount = computed(
    () => serviceList.value.filter((service) => service.is_active).length,
);

const popularCount = computed(
    () => serviceList.value.filter((service) => service.is_popular).length,
);

const averagePrice = computed(() => {
    if (serviceList.value.length === 0) {
        return 0;
    }

    const total = serviceList.value.reduce(
        (sum, service) => sum + service.price,
        0,
    );

    return Math.round(total / serviceList.value.length);
});

function enterReorderMode(): void {
    if (!props.capabilities.update) {
        return;
    }

    search.value = '';
    categoryFilter.value = 'Semua';
    orderSnapshot.value = serviceList.value.map((service) => service.id);
    orderForm.clearErrors();
    isReorderMode.value = true;
}

function cancelReorder(): void {
    const snapshot = orderSnapshot.value;

    serviceList.value = [...serviceList.value].sort(
        (a, b) => snapshot.indexOf(a.id) - snapshot.indexOf(b.id),
    );
    resetDrag();
    isReorderMode.value = false;
}

function moveService(from: number, to: number): void {
    if (from === to || to < 0 || to >= serviceList.value.length) {
        return;
    }

    const rows = [...serviceList.value];
    const [moved] = rows.splice(from, 1);
    rows.splice(to, 0, moved);
    serviceList.value = rows;
}

/**
 * A native drag will not scroll the page for us — the browser tries the
 * `overflow-x-auto` wrapper around the table instead — so the catalog has to
 * be walked by hand while the pointer sits near the top or bottom edge.
 */
const AUTO_SCROLL_EDGE = 140;
const AUTO_SCROLL_MAX_STEP = 22;

let autoScrollFrame: number | null = null;
let pointerY = 0;

function trackPointer(event: DragEvent): void {
    pointerY = event.clientY;
}

function startAutoScroll(): void {
    if (autoScrollFrame !== null) {
        return;
    }

    document.addEventListener('dragover', trackPointer);

    const step = (): void => {
        const bottomEdge = window.innerHeight - AUTO_SCROLL_EDGE;
        let distance = 0;

        if (pointerY < AUTO_SCROLL_EDGE) {
            distance = -(AUTO_SCROLL_EDGE - pointerY);
        } else if (pointerY > bottomEdge) {
            distance = pointerY - bottomEdge;
        }

        if (distance !== 0) {
            const speed = (distance / AUTO_SCROLL_EDGE) * AUTO_SCROLL_MAX_STEP;
            window.scrollBy(0, speed);
        }

        autoScrollFrame = requestAnimationFrame(step);
    };

    autoScrollFrame = requestAnimationFrame(step);
}

function stopAutoScroll(): void {
    if (autoScrollFrame !== null) {
        cancelAnimationFrame(autoScrollFrame);
        autoScrollFrame = null;
    }

    document.removeEventListener('dragover', trackPointer);
}

onUnmounted(stopAutoScroll);

function resetDrag(): void {
    draggingIndex.value = null;
    dropIndex.value = null;
    stopAutoScroll();
}

function onRowDragStart(index: number, event: DragEvent): void {
    draggingIndex.value = index;
    pointerY = event.clientY;

    if (event.dataTransfer) {
        event.dataTransfer.effectAllowed = 'move';
        /** Firefox refuses to start a drag without a payload. */
        event.dataTransfer.setData('text/plain', String(index));
    }

    startAutoScroll();
}

function onRowDragOver(index: number, event: DragEvent): void {
    if (draggingIndex.value === null) {
        return;
    }

    event.preventDefault();

    if (event.dataTransfer) {
        event.dataTransfer.dropEffect = 'move';
    }

    dropIndex.value = index;
}

function onRowDrop(index: number): void {
    if (draggingIndex.value !== null) {
        moveService(draggingIndex.value, index);
    }

    resetDrag();
}

function saveOrder(): void {
    if (props.mode === 'demo') {
        orderSnapshot.value = serviceList.value.map((service) => service.id);
        isReorderMode.value = false;

        return;
    }

    orderForm.ids = serviceList.value.map((service) => service.id);
    orderForm.submit(updateServiceOrder(), {
        preserveScroll: true,
        onSuccess: () => {
            isReorderMode.value = false;
        },
    });
}

function openCreateService(): void {
    editingServiceId.value = null;
    serviceForm.clearErrors();
    serviceForm.defaults({
        name: '',
        category: props.categories[0] ?? '',
        price: 0,
        stamps: 0,
        icon: defaultIcon,
        description: '',
        is_popular: false,
        is_active: true,
    });
    serviceForm.reset();
    isServiceFormOpen.value = true;
}

function openEditService(service: Service): void {
    if (!props.capabilities.update) {
        return;
    }

    editingServiceId.value = service.id;
    serviceForm.clearErrors();
    serviceForm.defaults({
        name: service.name,
        category: service.category,
        price: service.price,
        stamps: service.stamps,
        icon: service.icon,
        description: service.description,
        is_popular: service.is_popular,
        is_active: service.is_active,
    });
    serviceForm.reset();
    isServiceFormOpen.value = true;
}

function submitService(): void {
    if (props.mode === 'demo') {
        saveDemoService();

        return;
    }

    const action =
        editingServiceId.value === null
            ? storeService()
            : updateService(editingServiceId.value);

    serviceForm.submit(action, {
        preserveScroll: true,
        onSuccess: () => {
            isServiceFormOpen.value = false;
            serviceForm.reset();
        },
    });
}

/** The demo console keeps its edits in memory instead of hitting the database. */
function saveDemoService(): void {
    serviceForm.clearErrors();

    if (!serviceForm.name.trim()) {
        serviceForm.setError('name', 'Nama layanan wajib diisi.');
    }

    if (!serviceForm.category.trim()) {
        serviceForm.setError('category', 'Kategori wajib diisi.');
    }

    if (serviceForm.price < 0) {
        serviceForm.setError('price', 'Harga tidak boleh negatif.');
    }

    const isDuplicate = serviceList.value.some(
        (service) =>
            service.id !== editingServiceId.value &&
            service.name.trim().toLowerCase() ===
                serviceForm.name.trim().toLowerCase(),
    );

    if (isDuplicate) {
        serviceForm.setError('name', 'Nama layanan sudah dipakai.');
    }

    if (serviceForm.hasErrors) {
        return;
    }

    const values = {
        name: serviceForm.name.trim(),
        category: serviceForm.category.trim(),
        price: serviceForm.price,
        stamps: serviceForm.stamps,
        icon: serviceForm.icon,
        description: serviceForm.description,
        is_popular: serviceForm.is_popular,
        is_active: serviceForm.is_active,
    };

    if (editingServiceId.value === null) {
        serviceList.value.unshift({
            id:
                Math.max(0, ...serviceList.value.map((service) => service.id)) +
                1,
            ...values,
            sort_order:
                Math.max(
                    0,
                    ...serviceList.value.map((service) => service.sort_order),
                ) + 1,
            order_count: 0,
        });
    } else {
        const service = serviceList.value.find(
            (item) => item.id === editingServiceId.value,
        );

        if (service) {
            Object.assign(service, values);
        }
    }

    isServiceFormOpen.value = false;
    serviceForm.reset();
}

function openDeleteService(service: Service): void {
    if (!props.capabilities.delete || service.order_count > 0) {
        return;
    }

    deleteForm.clearErrors();
    deletingService.value = service;
}

function confirmDeleteService(): void {
    if (deletingService.value === null) {
        return;
    }

    if (props.mode === 'demo') {
        serviceList.value = serviceList.value.filter(
            (service) => service.id !== deletingService.value?.id,
        );
        deletingService.value = null;

        return;
    }

    deleteForm.submit(destroyService(deletingService.value.id), {
        preserveScroll: true,
        onSuccess: () => {
            deletingService.value = null;
        },
    });
}
</script>

<template>
    <Head :title="`${brand.name} — Layanan`" />

    <div class="space-y-4">
        <section class="grid grid-cols-1 gap-4 sm:grid-cols-2 xl:grid-cols-4">
            <StatCard
                label="Total layanan"
                :value="String(serviceList.length)"
                :caption="`${categories.length} kategori`"
                :icon="SprayCan"
            />
            <StatCard
                label="Layanan aktif"
                :value="String(activeCount)"
                caption="Tampil di order & POS"
                :icon="Sparkles"
                tone="emerald"
            />
            <StatCard
                label="Layanan populer"
                :value="String(popularCount)"
                caption="Ditandai sebagai unggulan"
                :icon="Sparkles"
                tone="amber"
            />
            <StatCard
                label="Harga rata-rata"
                :value="formatCurrency(averagePrice)"
                caption="Seluruh layanan terdaftar"
                :icon="Wallet"
                tone="violet"
            />
        </section>

        <section
            class="rounded-2xl border border-slate-200/80 bg-white shadow-sm"
        >
            <div class="space-y-3 border-b border-slate-100 p-5">
                <div class="flex flex-wrap items-center justify-between gap-3">
                    <div>
                        <h3 class="text-sm font-semibold text-slate-900">
                            Daftar layanan
                        </h3>
                        <p class="mt-0.5 text-xs text-slate-500">
                            {{
                                isReorderMode
                                    ? 'Seret baris dengan gagang di kiri untuk mengubah urutan.'
                                    : `${filteredServices.length} layanan ditampilkan`
                            }}
                        </p>
                    </div>
                    <DataToolbar
                        v-if="!isReorderMode"
                        v-model:search="search"
                        placeholder="Cari nama / kategori"
                        :filters="filterOptions"
                        :active-filter="categoryFilter"
                        @filter="categoryFilter = $event"
                    />
                </div>

                <div
                    v-if="!isReorderMode"
                    class="flex flex-wrap items-center gap-2"
                >
                    <button
                        v-if="capabilities.create"
                        type="button"
                        class="flex items-center gap-2 rounded-xl bg-gradient-to-r from-cyan-500 to-sky-600 px-3 py-2 text-sm font-medium text-white shadow-lg shadow-cyan-500/25 transition hover:from-cyan-600 hover:to-sky-700"
                        @click="openCreateService"
                    >
                        <Plus class="h-4 w-4" /> Tambah Layanan
                    </button>
                    <button
                        v-if="capabilities.update && serviceList.length > 1"
                        type="button"
                        class="flex items-center gap-2 rounded-xl border border-slate-200 px-3 py-2 text-sm font-medium text-slate-600 transition hover:bg-slate-50"
                        @click="enterReorderMode"
                    >
                        <ArrowUpDown class="h-4 w-4" /> Ubah Urutan
                    </button>
                </div>
            </div>

            <div v-if="visibleServices.length" class="overflow-x-auto">
                <table class="w-full min-w-[860px] text-sm">
                    <thead>
                        <tr
                            class="border-b border-slate-100 text-left text-[11px] font-medium tracking-wider text-slate-400 uppercase"
                        >
                            <th v-if="isReorderMode" class="w-24 px-5 py-3">
                                Urutan
                            </th>
                            <th class="px-5 py-3">Layanan</th>
                            <th class="px-5 py-3">Kategori</th>
                            <th class="px-5 py-3">Harga</th>
                            <th class="px-5 py-3">Stempel</th>
                            <th class="px-5 py-3">Status</th>
                            <th class="px-5 py-3">Dipakai order</th>
                            <th class="px-5 py-3"></th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-50">
                        <tr
                            v-for="(service, index) in visibleServices"
                            :key="service.id"
                            class="transition"
                            :class="[
                                isReorderMode
                                    ? 'select-none'
                                    : 'hover:bg-slate-50/70',
                                draggingIndex === index ? 'opacity-40' : '',
                                isReorderMode &&
                                dropIndex === index &&
                                draggingIndex !== index
                                    ? 'bg-cyan-50/60 ring-2 ring-cyan-300 ring-inset'
                                    : '',
                            ]"
                            :draggable="isReorderMode"
                            @dragstart="onRowDragStart(index, $event)"
                            @dragover="onRowDragOver(index, $event)"
                            @drop.prevent="onRowDrop(index)"
                            @dragend="resetDrag"
                        >
                            <td v-if="isReorderMode" class="px-5 py-3.5">
                                <div class="flex items-center gap-1">
                                    <GripVertical
                                        class="h-4 w-4 shrink-0 cursor-grab text-slate-400 active:cursor-grabbing"
                                        aria-hidden="true"
                                    />
                                    <div class="flex flex-col">
                                        <button
                                            type="button"
                                            class="rounded p-0.5 text-slate-400 transition hover:bg-slate-100 hover:text-slate-700 disabled:cursor-not-allowed disabled:opacity-30 disabled:hover:bg-transparent"
                                            :disabled="index === 0"
                                            aria-label="Naikkan urutan"
                                            @click="
                                                moveService(index, index - 1)
                                            "
                                        >
                                            <ChevronUp class="h-3.5 w-3.5" />
                                        </button>
                                        <button
                                            type="button"
                                            class="rounded p-0.5 text-slate-400 transition hover:bg-slate-100 hover:text-slate-700 disabled:cursor-not-allowed disabled:opacity-30 disabled:hover:bg-transparent"
                                            :disabled="
                                                index ===
                                                visibleServices.length - 1
                                            "
                                            aria-label="Turunkan urutan"
                                            @click="
                                                moveService(index, index + 1)
                                            "
                                        >
                                            <ChevronDown class="h-3.5 w-3.5" />
                                        </button>
                                    </div>
                                    <span
                                        class="text-xs font-medium text-slate-400 tabular-nums"
                                    >
                                        {{ index + 1 }}
                                    </span>
                                </div>
                            </td>
                            <td class="px-5 py-3.5">
                                <div class="flex items-center gap-3">
                                    <div
                                        class="flex h-9 w-9 shrink-0 items-center justify-center rounded-xl bg-gradient-to-br from-cyan-50 to-sky-100 text-lg"
                                    >
                                        {{ service.icon }}
                                    </div>
                                    <div class="min-w-0">
                                        <p
                                            class="flex items-center gap-2 font-medium text-slate-900"
                                        >
                                            {{ service.name }}
                                            <span
                                                v-if="service.is_popular"
                                                class="rounded-full bg-amber-100 px-2 py-0.5 text-[10px] font-medium text-amber-700"
                                            >
                                                Populer
                                            </span>
                                        </p>
                                        <p
                                            v-if="service.description"
                                            class="max-w-md truncate text-[11px] text-slate-500"
                                        >
                                            {{ service.description }}
                                        </p>
                                    </div>
                                </div>
                            </td>
                            <td
                                class="px-5 py-3.5 text-xs font-medium text-slate-600"
                            >
                                {{ service.category }}
                            </td>
                            <td
                                class="px-5 py-3.5 font-medium text-slate-900 tabular-nums"
                            >
                                {{ formatCurrency(service.price) }}
                            </td>
                            <td
                                class="px-5 py-3.5 text-xs font-medium text-emerald-600 tabular-nums"
                            >
                                +{{ service.stamps }}
                            </td>
                            <td class="px-5 py-3.5">
                                <StatusPill
                                    :status="
                                        service.is_active ? 'aktif' : 'nonaktif'
                                    "
                                />
                            </td>
                            <td class="px-5 py-3.5 text-[11px] text-slate-500">
                                {{ service.order_count }} order
                            </td>
                            <td class="px-5 py-3.5">
                                <div
                                    class="flex items-center justify-end gap-1"
                                >
                                    <button
                                        v-if="
                                            capabilities.update &&
                                            !isReorderMode
                                        "
                                        type="button"
                                        class="rounded-lg p-2 text-cyan-700 transition hover:bg-cyan-50"
                                        aria-label="Edit layanan"
                                        @click="openEditService(service)"
                                    >
                                        <Pencil class="h-4 w-4" />
                                    </button>
                                    <button
                                        v-if="
                                            capabilities.delete &&
                                            !isReorderMode
                                        "
                                        type="button"
                                        class="rounded-lg p-2 text-rose-600 transition hover:bg-rose-50 disabled:cursor-not-allowed disabled:text-slate-300 disabled:hover:bg-transparent"
                                        :disabled="service.order_count > 0"
                                        :title="
                                            service.order_count > 0
                                                ? 'Sudah dipakai order, nonaktifkan saja'
                                                : 'Hapus layanan'
                                        "
                                        aria-label="Hapus layanan"
                                        @click="openDeleteService(service)"
                                    >
                                        <Trash2 class="h-4 w-4" />
                                    </button>
                                </div>
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>
            <EmptyState
                v-else
                :icon="SprayCan"
                title="Layanan tidak ditemukan"
                caption="Ubah kata kunci atau pilih kategori lain."
            />
        </section>

        <!-- Sticky so the operator never scrolls past it on a long catalog. -->
        <div
            v-if="isReorderMode"
            class="sticky bottom-4 z-20 flex flex-wrap items-center justify-between gap-3 rounded-2xl border border-slate-200 bg-white/95 p-4 shadow-lg backdrop-blur"
            :class="draggingIndex !== null ? 'pointer-events-none' : ''"
        >
            <div class="min-w-0">
                <p class="text-sm font-semibold text-slate-900">
                    {{
                        isOrderDirty
                            ? 'Urutan diubah dan belum disimpan.'
                            : 'Belum ada perubahan urutan.'
                    }}
                </p>
                <p class="mt-0.5 text-xs text-slate-500">
                    Urutan ini juga dipakai pada daftar layanan di POS dan
                    order.
                </p>
                <InputError class="mt-1" :message="orderForm.errors.ids" />
            </div>
            <div class="flex items-center gap-2">
                <button
                    type="button"
                    class="flex items-center gap-2 rounded-xl border border-slate-200 px-4 py-2.5 text-sm font-medium text-slate-600 transition hover:bg-slate-50"
                    :disabled="orderForm.processing"
                    @click="cancelReorder"
                >
                    <X class="h-4 w-4" /> Batal
                </button>
                <button
                    type="button"
                    class="flex items-center gap-2 rounded-xl bg-gradient-to-r from-cyan-500 to-sky-600 px-4 py-2.5 text-sm font-semibold text-white shadow-lg shadow-cyan-500/25 transition hover:from-cyan-600 hover:to-sky-700 disabled:cursor-not-allowed disabled:opacity-60"
                    :disabled="!isOrderDirty || orderForm.processing"
                    @click="saveOrder"
                >
                    <Save class="h-4 w-4" />
                    {{
                        orderForm.processing ? 'Menyimpan...' : 'Simpan urutan'
                    }}
                </button>
            </div>
        </div>
    </div>

    <ModalDialog
        :open="isServiceFormOpen"
        :title="editingServiceId ? 'Edit layanan' : 'Tambah layanan'"
        caption="Data ini dipakai pada order, POS, dan katalog member."
        size="lg"
        @close="isServiceFormOpen = false"
    >
        <form
            id="master-service-form"
            class="space-y-4"
            @submit.prevent="submitService"
        >
            <div class="grid grid-cols-1 gap-3 sm:grid-cols-[1fr_15rem]">
                <div>
                    <label
                        for="service-name"
                        class="text-xs font-medium text-slate-600"
                        >Nama layanan</label
                    >
                    <input
                        id="service-name"
                        v-model="serviceForm.name"
                        type="text"
                        class="mt-1.5 w-full rounded-xl border border-slate-200 px-3 py-2.5 text-sm focus:border-cyan-400 focus:outline-none"
                    />
                    <InputError
                        class="mt-1"
                        :message="serviceForm.errors.name"
                    />
                </div>
                <div>
                    <label
                        for="service-icon"
                        class="text-xs font-medium text-slate-600"
                        >Ikon</label
                    >
                    <div class="mt-1.5 flex items-center gap-2">
                        <span
                            class="flex h-10 w-10 shrink-0 items-center justify-center rounded-xl bg-gradient-to-br from-cyan-50 to-sky-100 text-lg"
                            aria-hidden="true"
                        >
                            {{ serviceForm.icon }}
                        </span>
                        <select
                            id="service-icon"
                            v-model="serviceForm.icon"
                            class="w-full rounded-xl border border-slate-200 px-3 py-2.5 text-sm focus:border-cyan-400 focus:outline-none"
                        >
                            <option
                                v-if="
                                    serviceForm.icon &&
                                    !icons.some(
                                        (icon) =>
                                            icon.value === serviceForm.icon,
                                    )
                                "
                                :value="serviceForm.icon"
                            >
                                {{ serviceForm.icon }} Ikon lama
                            </option>
                            <option
                                v-for="icon in icons"
                                :key="icon.value"
                                :value="icon.value"
                            >
                                {{ icon.value }} {{ icon.label }}
                            </option>
                        </select>
                    </div>
                    <InputError
                        class="mt-1"
                        :message="serviceForm.errors.icon"
                    />
                </div>
            </div>

            <div class="grid grid-cols-1 gap-3 sm:grid-cols-3">
                <div>
                    <label
                        for="service-category"
                        class="text-xs font-medium text-slate-600"
                        >Kategori</label
                    >
                    <input
                        id="service-category"
                        v-model="serviceForm.category"
                        type="text"
                        list="service-category-options"
                        class="mt-1.5 w-full rounded-xl border border-slate-200 px-3 py-2.5 text-sm focus:border-cyan-400 focus:outline-none"
                    />
                    <datalist id="service-category-options">
                        <option
                            v-for="category in categories"
                            :key="category"
                            :value="category"
                        ></option>
                    </datalist>
                    <InputError
                        class="mt-1"
                        :message="serviceForm.errors.category"
                    />
                </div>
                <div>
                    <label
                        for="service-price"
                        class="text-xs font-medium text-slate-600"
                        >Harga (Rp)</label
                    >
                    <input
                        id="service-price"
                        v-model.number="serviceForm.price"
                        type="number"
                        min="0"
                        step="1000"
                        class="mt-1.5 w-full rounded-xl border border-slate-200 px-3 py-2.5 text-sm tabular-nums focus:border-cyan-400 focus:outline-none"
                    />
                    <InputError
                        class="mt-1"
                        :message="serviceForm.errors.price"
                    />
                </div>
                <div>
                    <label
                        for="service-stamps"
                        class="text-xs font-medium text-slate-600"
                        >Stempel didapat</label
                    >
                    <input
                        id="service-stamps"
                        v-model.number="serviceForm.stamps"
                        type="number"
                        min="0"
                        class="mt-1.5 w-full rounded-xl border border-slate-200 px-3 py-2.5 text-sm tabular-nums focus:border-cyan-400 focus:outline-none"
                    />
                    <InputError
                        class="mt-1"
                        :message="serviceForm.errors.stamps"
                    />
                </div>
            </div>

            <div>
                <label
                    for="service-description"
                    class="text-xs font-medium text-slate-600"
                    >Deskripsi</label
                >
                <textarea
                    id="service-description"
                    v-model="serviceForm.description"
                    rows="3"
                    class="mt-1.5 w-full rounded-xl border border-slate-200 px-3 py-2.5 text-sm focus:border-cyan-400 focus:outline-none"
                ></textarea>
                <InputError
                    class="mt-1"
                    :message="serviceForm.errors.description"
                />
            </div>

            <div class="grid grid-cols-1 gap-3 sm:grid-cols-2">
                <label
                    class="flex items-center gap-3 rounded-xl border border-slate-200 p-3 text-sm text-slate-700"
                >
                    <input
                        v-model="serviceForm.is_active"
                        type="checkbox"
                        class="h-4 w-4 rounded border-slate-300 text-cyan-600 focus:ring-cyan-500"
                    />
                    Layanan aktif
                </label>
                <label
                    class="flex items-center gap-3 rounded-xl border border-slate-200 p-3 text-sm text-slate-700"
                >
                    <input
                        v-model="serviceForm.is_popular"
                        type="checkbox"
                        class="h-4 w-4 rounded border-slate-300 text-cyan-600 focus:ring-cyan-500"
                    />
                    Tandai sebagai populer
                </label>
            </div>
        </form>
        <template #footer>
            <button
                type="button"
                class="flex-1 rounded-xl border border-slate-200 py-2.5 text-sm font-medium text-slate-600 hover:bg-slate-50"
                @click="isServiceFormOpen = false"
            >
                Batal
            </button>
            <button
                form="master-service-form"
                type="submit"
                class="flex-1 rounded-xl bg-gradient-to-r from-cyan-500 to-sky-600 py-2.5 text-sm font-semibold text-white disabled:cursor-not-allowed disabled:opacity-60"
                :disabled="serviceForm.processing"
            >
                {{ serviceForm.processing ? 'Menyimpan...' : 'Simpan layanan' }}
            </button>
        </template>
    </ModalDialog>

    <ModalDialog
        :open="deletingService !== null"
        title="Hapus layanan"
        caption="Layanan yang dihapus tidak dapat dikembalikan."
        size="sm"
        @close="deletingService = null"
    >
        <p v-if="deletingService" class="text-sm text-slate-600">
            Yakin ingin menghapus
            <span class="font-semibold text-slate-900">{{
                deletingService.name
            }}</span>
            dari master layanan?
        </p>
        <InputError class="mt-2" :message="deleteError" />
        <template #footer>
            <button
                type="button"
                class="flex-1 rounded-xl border border-slate-200 py-2.5 text-sm font-medium text-slate-600 hover:bg-slate-50"
                @click="deletingService = null"
            >
                Batal
            </button>
            <button
                type="button"
                class="flex-1 rounded-xl bg-rose-600 py-2.5 text-sm font-semibold text-white transition hover:bg-rose-700 disabled:cursor-not-allowed disabled:opacity-60"
                :disabled="deleteForm.processing"
                @click="confirmDeleteService"
            >
                {{ deleteForm.processing ? 'Menghapus...' : 'Hapus layanan' }}
            </button>
        </template>
    </ModalDialog>
</template>
