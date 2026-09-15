<script setup lang="ts">
import { Head, router, useForm } from '@inertiajs/vue3';
import {
    ArrowLeftRight,
    Boxes,
    Package,
    Pencil,
    Plus,
    Power,
    TriangleAlert,
} from '@lucide/vue';
import { computed, ref, watch } from 'vue';
import {
    index as indexInventory,
    store as storeItem,
    storeMovement as storeStockMovement,
    update as updateItem,
    updateStatus as updateItemStatus,
} from '@/actions/App/Http/Controllers/Admin/InventoryController';
import DataPagination from '@/components/demo/DataPagination.vue';
import DataToolbar from '@/components/demo/DataToolbar.vue';
import EmptyState from '@/components/demo/EmptyState.vue';
import ModalDialog from '@/components/demo/ModalDialog.vue';
import StatCard from '@/components/demo/StatCard.vue';
import StatusPill from '@/components/demo/StatusPill.vue';
import MoneyInput from '@/components/MoneyInput.vue';
import { formatCurrency } from '@/composables/useCarwashFormat';
import admin from '@/routes/demo/admin';
import type {
    CarwashBrand,
    CarwashPaginated,
    CarwashStockFilters,
    CarwashStockItem,
    CarwashStockItemOption,
    CarwashStockMovement,
    CarwashStockStats,
} from '@/types/demo';

const props = defineProps<{
    mode: 'demo' | 'live';
    brand: CarwashBrand;
    items: CarwashPaginated<CarwashStockItem>;
    movements: CarwashPaginated<CarwashStockMovement>;
    stats: CarwashStockStats;
    itemOptions: CarwashStockItemOption[];
    filters: CarwashStockFilters;
    categories: string[];
    suppliers: string[];
    movementTypes: string[];
    statusFilters: string[];
    stockFilters: string[];
    capabilities: { create: boolean; update: boolean };
}>();

/**
 * The demo mutates this copy in place; live re-reads it from the reloaded
 * props, the same split admin/master/Services.vue uses.
 */
const itemList = ref<CarwashStockItem[]>(props.items.data.map(cloneItem));
const movementLog = ref<CarwashStockMovement[]>(
    props.movements.data.map((movement) => ({ ...movement })),
);
const optionList = ref<CarwashStockItemOption[]>(
    props.itemOptions.map((option) => ({ ...option })),
);
const demoStats = ref<CarwashStockStats>({ ...props.stats });

const search = ref<string>(props.filters.q);
const categoryFilter = ref<string>(props.filters.category);
const stockFilter = ref<string>(props.filters.stock);
const statusFilter = ref<string>(props.filters.status);

const isMovementOpen = ref<boolean>(false);
const isFormOpen = ref<boolean>(false);
const editingItemId = ref<number | null>(null);

const categoryOptions = computed<string[]>(() => [
    'Semua',
    ...props.categories,
]);

const stats = computed<CarwashStockStats>(() =>
    props.mode === 'demo' ? demoStats.value : props.stats,
);

/** Items at or below their reorder point (BR-09 stock monitoring). */
const lowStockItems = computed<CarwashStockItem[]>(() =>
    itemList.value.filter((item) => item.isLowStock),
);

const itemDraft = ref({
    sku: '',
    name: '',
    category: '',
    unit: '',
    quantity: 0,
    minQuantity: 0,
    unitCost: 0,
    supplier: '',
    notes: '',
});

const movementDraft = ref({
    itemId: props.itemOptions[0]?.id ?? 0,
    type: 'masuk',
    quantity: 1,
    unitCost: 0,
    note: '',
});

const itemForm = useForm({
    sku: '',
    name: '',
    category: '',
    unit: '',
    quantity: 0,
    min_quantity: 0,
    unit_cost: 0,
    supplier: '' as string | null,
    notes: '' as string | null,
});
const movementForm = useForm({
    type: 'masuk',
    quantity: 1,
    unit_cost: null as number | null,
    note: '' as string | null,
});
const statusForm = useForm({ is_active: true });

const movementItem = computed<CarwashStockItemOption | null>(
    () =>
        optionList.value.find(
            (option) => option.id === movementDraft.value.itemId,
        ) ??
        optionList.value[0] ??
        null,
);

/** Corrections may be negative; ins and outs are always positive amounts. */
const resultingQuantity = computed<number>(() => {
    const current = movementItem.value?.quantity ?? 0;
    const quantity = movementDraft.value.quantity;

    if (movementDraft.value.type === 'masuk') {
        return current + Math.abs(quantity);
    }

    if (movementDraft.value.type === 'keluar') {
        return current - Math.abs(quantity);
    }

    return current + quantity;
});

const canSaveItem = computed<boolean>(
    () =>
        itemDraft.value.sku.trim() !== '' &&
        itemDraft.value.name.trim() !== '' &&
        itemDraft.value.category.trim() !== '' &&
        itemDraft.value.unit.trim() !== '',
);

const canSaveMovement = computed<boolean>(
    () =>
        movementItem.value !== null &&
        movementDraft.value.quantity !== 0 &&
        resultingQuantity.value >= 0,
);

let searchTimer: ReturnType<typeof setTimeout> | undefined;

watch(search, () => {
    clearTimeout(searchTimer);
    searchTimer = setTimeout(() => visit({ q: search.value, page: 1 }), 300);
});

watch(
    () => props.items,
    (items) => {
        if (props.mode === 'live') {
            itemList.value = items.data.map(cloneItem);
        }
    },
);

watch(
    () => props.movements,
    (movements) => {
        if (props.mode === 'live') {
            movementLog.value = movements.data.map((movement) => ({
                ...movement,
            }));
        }
    },
);

watch(
    () => props.itemOptions,
    (options) => {
        if (props.mode === 'live') {
            optionList.value = options.map((option) => ({ ...option }));
        }
    },
);

function cloneItem(item: CarwashStockItem): CarwashStockItem {
    return { ...item };
}

function visit(overrides: Partial<CarwashStockFilters> = {}): void {
    router.get(
        props.mode === 'demo' ? admin.inventory.url() : indexInventory.url(),
        {
            q: search.value,
            category: categoryFilter.value,
            status: statusFilter.value,
            stock: stockFilter.value,
            page: props.filters.page,
            movementPage: props.filters.movementPage,
            ...overrides,
        },
        { preserveState: true, preserveScroll: true, replace: true },
    );
}

function applyCategoryFilter(category: string): void {
    categoryFilter.value = category;
    visit({ category, page: 1 });
}

function toggleLowStockFilter(): void {
    const next =
        stockFilter.value === 'Stok menipis' ? 'Semua' : 'Stok menipis';

    stockFilter.value = next;
    visit({ stock: next, page: 1 });
}

function toggleInactiveFilter(): void {
    const next = statusFilter.value === 'Semua' ? 'aktif' : 'Semua';

    statusFilter.value = next;
    visit({ status: next, page: 1 });
}

function stockLevelClass(item: CarwashStockItem): string {
    if (item.isLowStock) {
        return 'text-rose-600';
    }

    if (item.quantity <= item.minQuantity * 1.5) {
        return 'text-amber-600';
    }

    return 'text-slate-900';
}

function stockPercent(item: CarwashStockItem): number {
    return Math.min(
        Math.round((item.quantity / Math.max(item.minQuantity * 2, 1)) * 100),
        100,
    );
}

function openCreateForm(): void {
    if (!props.capabilities.create) {
        return;
    }

    editingItemId.value = null;
    itemDraft.value = {
        sku: '',
        name: '',
        category: '',
        unit: '',
        quantity: 0,
        minQuantity: 0,
        unitCost: 0,
        supplier: '',
        notes: '',
    };
    itemForm.clearErrors();
    isFormOpen.value = true;
}

function openEditForm(item: CarwashStockItem): void {
    if (!props.capabilities.update) {
        return;
    }

    editingItemId.value = item.id;
    itemDraft.value = {
        sku: item.sku,
        name: item.name,
        category: item.category,
        unit: item.unit,
        quantity: item.quantity,
        minQuantity: item.minQuantity,
        unitCost: item.unitCost,
        supplier: item.supplier,
        notes: item.notes,
    };
    itemForm.clearErrors();
    isFormOpen.value = true;
}

function openMovementForm(item?: CarwashStockItem): void {
    if (!props.capabilities.create || optionList.value.length === 0) {
        return;
    }

    movementDraft.value = {
        itemId: item?.id ?? optionList.value[0].id,
        type: 'masuk',
        quantity: 1,
        unitCost: 0,
        note: '',
    };
    movementForm.clearErrors();
    isMovementOpen.value = true;
}

function saveItem(): void {
    const requiredCapability =
        editingItemId.value === null
            ? props.capabilities.create
            : props.capabilities.update;

    if (!requiredCapability || !canSaveItem.value) {
        return;
    }

    if (props.mode === 'demo') {
        saveDemoItem();

        return;
    }

    itemForm.sku = itemDraft.value.sku;
    itemForm.name = itemDraft.value.name;
    itemForm.category = itemDraft.value.category;
    itemForm.unit = itemDraft.value.unit;
    itemForm.quantity = itemDraft.value.quantity;
    itemForm.min_quantity = itemDraft.value.minQuantity;
    itemForm.unit_cost = itemDraft.value.unitCost;
    itemForm.supplier = itemDraft.value.supplier || null;
    itemForm.notes = itemDraft.value.notes || null;

    const action =
        editingItemId.value === null
            ? storeItem()
            : updateItem(editingItemId.value);

    itemForm.submit(action, {
        preserveScroll: true,
        onSuccess: () => {
            isFormOpen.value = false;
            editingItemId.value = null;
        },
    });
}

/** The demo keeps every change in this session's memory only. */
function saveDemoItem(): void {
    const draft = itemDraft.value;
    const existing = itemList.value.find(
        (item) => item.id === editingItemId.value,
    );

    if (existing) {
        Object.assign(existing, {
            sku: draft.sku,
            name: draft.name,
            category: draft.category,
            unit: draft.unit,
            minQuantity: draft.minQuantity,
            unitCost: draft.unitCost,
            supplier: draft.supplier,
            notes: draft.notes,
            isLowStock: existing.quantity <= draft.minQuantity,
            stockValue: existing.quantity * draft.unitCost,
            updatedAt: 'Baru saja',
        });
    } else {
        const id = Math.max(0, ...itemList.value.map((item) => item.id)) + 1;

        itemList.value = [
            {
                id,
                sku: draft.sku,
                name: draft.name,
                category: draft.category,
                unit: draft.unit,
                quantity: draft.quantity,
                minQuantity: draft.minQuantity,
                isLowStock: draft.quantity <= draft.minQuantity,
                unitCost: draft.unitCost,
                stockValue: draft.quantity * draft.unitCost,
                supplier: draft.supplier,
                notes: draft.notes,
                isActive: true,
                updatedAt: 'Baru saja',
            },
            ...itemList.value,
        ];
        optionList.value = [
            {
                id,
                sku: draft.sku,
                name: draft.name,
                unit: draft.unit,
                quantity: draft.quantity,
                minQuantity: draft.minQuantity,
                isLowStock: draft.quantity <= draft.minQuantity,
            },
            ...optionList.value,
        ];
    }

    recountDemoStats();
    isFormOpen.value = false;
    editingItemId.value = null;
}

function saveMovement(): void {
    if (!props.capabilities.create || !canSaveMovement.value) {
        return;
    }

    const item = movementItem.value;

    if (item === null) {
        return;
    }

    if (props.mode === 'demo') {
        saveDemoMovement(item);

        return;
    }

    movementForm.type = movementDraft.value.type;
    movementForm.quantity = movementDraft.value.quantity;
    movementForm.unit_cost =
        movementDraft.value.type === 'masuk' && movementDraft.value.unitCost > 0
            ? movementDraft.value.unitCost
            : null;
    movementForm.note = movementDraft.value.note || null;

    movementForm.submit(storeStockMovement(item.id), {
        preserveScroll: true,
        onSuccess: () => {
            isMovementOpen.value = false;
        },
    });
}

function saveDemoMovement(option: CarwashStockItemOption): void {
    const after = resultingQuantity.value;
    const delta = after - option.quantity;
    const listed = itemList.value.find((item) => item.id === option.id);

    option.quantity = after;
    option.isLowStock = after <= option.minQuantity;

    if (listed) {
        listed.quantity = after;
        listed.isLowStock = after <= listed.minQuantity;
        listed.stockValue = after * listed.unitCost;
        listed.updatedAt = 'Baru saja';
    }

    movementLog.value = [
        {
            id: Math.max(0, ...movementLog.value.map((row) => row.id)) + 1,
            itemId: option.id,
            item: option.name,
            sku: option.sku,
            unit: option.unit,
            type: movementDraft.value.type,
            quantity: delta,
            quantityAfter: after,
            note: movementDraft.value.note || '—',
            date: 'Baru saja',
            time: '',
            by: 'Sesi demo',
        },
        ...movementLog.value,
    ];

    recountDemoStats();
    isMovementOpen.value = false;
}

function toggleStatus(item: CarwashStockItem): void {
    if (!props.capabilities.update) {
        return;
    }

    if (props.mode === 'demo') {
        item.isActive = !item.isActive;
        optionList.value = item.isActive
            ? [...optionList.value, toOption(item)]
            : optionList.value.filter((option) => option.id !== item.id);
        recountDemoStats();

        return;
    }

    statusForm.is_active = !item.isActive;
    statusForm.submit(updateItemStatus(item.id), { preserveScroll: true });
}

function toOption(item: CarwashStockItem): CarwashStockItemOption {
    return {
        id: item.id,
        sku: item.sku,
        name: item.name,
        unit: item.unit,
        quantity: item.quantity,
        minQuantity: item.minQuantity,
        isLowStock: item.isLowStock,
    };
}

function recountDemoStats(): void {
    const active = itemList.value.filter((item) => item.isActive);

    demoStats.value = {
        totalItems: active.length,
        lowStock: active.filter((item) => item.isLowStock).length,
    };
}
</script>

<template>
    <div class="space-y-4">
        <Head :title="`${brand.name} — Stock Inventory`" />

        <section class="grid grid-cols-1 gap-4 sm:grid-cols-2">
            <StatCard
                label="Total item"
                :value="String(stats.totalItems)"
                :caption="`${categories.length} kategori`"
                :icon="Boxes"
            />
            <StatCard
                label="Stok menipis"
                :value="String(stats.lowStock)"
                caption="di bawah batas minimum"
                :icon="TriangleAlert"
                tone="rose"
                interactive
                :active="stockFilter === 'Stok menipis'"
                @click="toggleLowStockFilter"
            />
        </section>

        <!-- Low stock alert -->
        <section
            v-if="lowStockItems.length > 0"
            class="rounded-2xl border border-rose-200 bg-rose-50/60 p-5"
        >
            <p
                class="flex items-center gap-2 text-sm font-semibold text-rose-800"
            >
                <TriangleAlert class="h-4 w-4" />
                {{ lowStockItems.length }} item perlu segera direstock
            </p>
            <ul class="mt-3 flex flex-wrap gap-2">
                <li
                    v-for="item in lowStockItems"
                    :key="item.id"
                    class="rounded-xl bg-white px-3 py-2 text-xs ring-1 ring-rose-200"
                >
                    <span class="font-medium text-slate-800">
                        {{ item.name }}
                    </span>
                    <span class="ml-1.5 text-rose-600 tabular-nums">
                        {{ item.quantity }}/{{ item.minQuantity }}
                        {{ item.unit }}
                    </span>
                </li>
            </ul>
        </section>

        <!-- Stock list -->
        <section
            class="rounded-2xl border border-slate-200/80 bg-white shadow-sm"
        >
            <div
                class="flex flex-wrap items-center justify-between gap-3 border-b border-slate-100 p-5"
            >
                <div>
                    <h3 class="text-sm font-semibold text-slate-900">
                        Daftar stok
                    </h3>
                    <p class="mt-0.5 text-xs text-slate-500">
                        {{ items.meta.total }} item ditampilkan
                    </p>
                </div>
                <div class="flex flex-wrap items-center gap-2">
                    <DataToolbar
                        v-model:search="search"
                        placeholder="Cari item / SKU"
                        :filters="categoryOptions"
                        :active-filter="categoryFilter"
                        @filter="applyCategoryFilter"
                    >
                        <button
                            type="button"
                            class="rounded-lg px-3 py-1.5 text-xs font-medium transition"
                            :class="
                                statusFilter === 'Semua'
                                    ? 'bg-slate-900 text-white'
                                    : 'bg-slate-100 text-slate-600 hover:bg-slate-200'
                            "
                            @click="toggleInactiveFilter"
                        >
                            Termasuk nonaktif
                        </button>
                    </DataToolbar>
                    <button
                        v-if="capabilities.create"
                        type="button"
                        class="flex items-center gap-2 rounded-xl border border-slate-200 px-3 py-2 text-sm font-medium text-slate-600 transition hover:bg-slate-50"
                        @click="openCreateForm"
                    >
                        <Plus class="h-4 w-4" />
                        Item Baru
                    </button>
                    <button
                        v-if="capabilities.create"
                        type="button"
                        class="flex items-center gap-2 rounded-xl bg-gradient-to-r from-cyan-500 to-sky-600 px-3 py-2 text-sm font-medium text-white shadow-lg shadow-cyan-500/25 transition hover:from-cyan-600 hover:to-sky-700"
                        @click="openMovementForm()"
                    >
                        <ArrowLeftRight class="h-4 w-4" />
                        Catat Stok
                    </button>
                </div>
            </div>

            <div v-if="itemList.length > 0" class="overflow-x-auto">
                <table class="w-full min-w-[900px] text-sm">
                    <thead>
                        <tr
                            class="border-b border-slate-100 text-left text-[11px] font-medium tracking-wider text-slate-400 uppercase"
                        >
                            <th class="px-5 py-3">Item</th>
                            <th class="px-5 py-3">Kategori</th>
                            <th class="px-5 py-3">Stok</th>
                            <th class="px-5 py-3">Minimum</th>
                            <th class="px-5 py-3">Supplier</th>
                            <th class="px-5 py-3 text-right">Nilai</th>
                            <th class="px-5 py-3">Update</th>
                            <th class="px-5 py-3 text-right">Aksi</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-50">
                        <tr
                            v-for="item in itemList"
                            :key="item.id"
                            class="transition hover:bg-slate-50/70"
                            :class="item.isActive ? undefined : 'opacity-50'"
                        >
                            <td class="px-5 py-3.5">
                                <p class="font-medium text-slate-900">
                                    {{ item.name }}
                                </p>
                                <p class="text-[11px] text-slate-500">
                                    {{ item.sku }}
                                    <span v-if="!item.isActive"
                                        >• nonaktif</span
                                    >
                                </p>
                            </td>
                            <td class="px-5 py-3.5">
                                <span
                                    class="rounded-lg bg-slate-100 px-2 py-1 text-[11px] font-medium text-slate-600"
                                >
                                    {{ item.category }}
                                </span>
                            </td>
                            <td class="px-5 py-3.5">
                                <p
                                    class="font-semibold tabular-nums"
                                    :class="stockLevelClass(item)"
                                >
                                    {{ item.quantity }}
                                    <span
                                        class="text-[11px] font-normal text-slate-400"
                                    >
                                        {{ item.unit }}
                                    </span>
                                </p>
                                <div
                                    class="mt-1 h-1.5 w-20 overflow-hidden rounded-full bg-slate-100"
                                >
                                    <div
                                        class="h-full rounded-full"
                                        :class="
                                            item.isLowStock
                                                ? 'bg-rose-500'
                                                : 'bg-gradient-to-r from-cyan-500 to-sky-500'
                                        "
                                        :style="{
                                            width: `${stockPercent(item)}%`,
                                        }"
                                    ></div>
                                </div>
                            </td>
                            <td class="px-5 py-3.5 text-slate-600 tabular-nums">
                                {{ item.minQuantity }} {{ item.unit }}
                            </td>
                            <td class="px-5 py-3.5 text-slate-600">
                                {{ item.supplier || '—' }}
                            </td>
                            <td
                                class="px-5 py-3.5 text-right font-medium text-slate-900 tabular-nums"
                            >
                                {{ formatCurrency(item.stockValue) }}
                            </td>
                            <td class="px-5 py-3.5 text-[11px] text-slate-500">
                                {{ item.updatedAt }}
                            </td>
                            <td class="px-5 py-3.5">
                                <div
                                    class="flex items-center justify-end gap-1.5"
                                >
                                    <button
                                        v-if="
                                            capabilities.create && item.isActive
                                        "
                                        type="button"
                                        class="rounded-lg p-1.5 text-slate-400 transition hover:bg-slate-100 hover:text-cyan-600"
                                        title="Catat pergerakan"
                                        @click="openMovementForm(item)"
                                    >
                                        <ArrowLeftRight class="h-4 w-4" />
                                    </button>
                                    <button
                                        v-if="capabilities.update"
                                        type="button"
                                        class="rounded-lg p-1.5 text-slate-400 transition hover:bg-slate-100 hover:text-slate-700"
                                        title="Ubah item"
                                        @click="openEditForm(item)"
                                    >
                                        <Pencil class="h-4 w-4" />
                                    </button>
                                    <button
                                        v-if="capabilities.update"
                                        type="button"
                                        class="rounded-lg p-1.5 text-slate-400 transition hover:bg-slate-100"
                                        :class="
                                            item.isActive
                                                ? 'hover:text-rose-600'
                                                : 'hover:text-emerald-600'
                                        "
                                        :title="
                                            item.isActive
                                                ? 'Nonaktifkan item'
                                                : 'Aktifkan item'
                                        "
                                        @click="toggleStatus(item)"
                                    >
                                        <Power class="h-4 w-4" />
                                    </button>
                                </div>
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>

            <EmptyState
                v-else
                :icon="Package"
                title="Item tidak ditemukan"
                caption="Ubah kata kunci atau pilih kategori lain."
            />

            <DataPagination
                :meta="items.meta"
                label="item"
                @change="visit({ page: $event })"
            />
        </section>

        <!-- Movement history -->
        <section
            class="rounded-2xl border border-slate-200/80 bg-white shadow-sm"
        >
            <div class="border-b border-slate-100 p-5">
                <h3 class="text-sm font-semibold text-slate-900">
                    Riwayat pergerakan stok
                </h3>
                <p class="mt-0.5 text-xs text-slate-500">
                    Stok masuk, keluar, dan penyesuaian
                </p>
            </div>

            <ul v-if="movementLog.length > 0" class="divide-y divide-slate-50">
                <li
                    v-for="movement in movementLog"
                    :key="movement.id"
                    class="flex flex-wrap items-center gap-3 px-5 py-3.5"
                >
                    <div
                        class="flex h-9 w-9 shrink-0 items-center justify-center rounded-xl"
                        :class="
                            movement.type === 'masuk'
                                ? 'bg-emerald-50 text-emerald-600'
                                : movement.type === 'keluar'
                                  ? 'bg-rose-50 text-rose-600'
                                  : 'bg-amber-50 text-amber-600'
                        "
                    >
                        <ArrowLeftRight class="h-4 w-4" />
                    </div>
                    <div class="min-w-0 flex-1">
                        <p class="text-sm font-medium text-slate-800">
                            {{ movement.item }}
                        </p>
                        <p class="truncate text-[11px] text-slate-500">
                            {{ movement.note }} • {{ movement.by }}
                        </p>
                    </div>
                    <StatusPill :status="movement.type" />
                    <p
                        class="w-16 text-right text-sm font-semibold tabular-nums"
                        :class="
                            movement.quantity > 0
                                ? 'text-emerald-600'
                                : 'text-rose-600'
                        "
                    >
                        {{ movement.quantity > 0 ? '+' : ''
                        }}{{ movement.quantity }}
                    </p>
                    <p class="w-24 text-right text-[11px] text-slate-400">
                        sisa {{ movement.quantityAfter }}
                        <span class="block">{{ movement.date }}</span>
                    </p>
                </li>
            </ul>

            <EmptyState
                v-else
                :icon="ArrowLeftRight"
                title="Belum ada pergerakan"
                caption="Setiap stok masuk, keluar, dan penyesuaian tercatat di sini."
            />

            <DataPagination
                :meta="movements.meta"
                label="pergerakan"
                @change="visit({ movementPage: $event })"
            />
        </section>
    </div>

    <!-- Add / edit item -->
    <ModalDialog
        :open="isFormOpen"
        :title="editingItemId === null ? 'Tambah item stok' : 'Ubah item stok'"
        caption="Data master item operasional"
        size="lg"
        @close="isFormOpen = false"
    >
        <div class="space-y-4">
            <div class="grid grid-cols-1 gap-3 sm:grid-cols-2">
                <div>
                    <label
                        class="text-xs font-medium text-slate-600"
                        for="stock-sku"
                    >
                        SKU
                    </label>
                    <input
                        id="stock-sku"
                        v-model="itemDraft.sku"
                        type="text"
                        placeholder="SNW-001"
                        class="mt-1.5 w-full rounded-xl border border-slate-200 bg-white px-3 py-2.5 text-sm text-slate-900 uppercase placeholder:text-slate-400 focus:border-cyan-400 focus:outline-none"
                    />
                </div>
                <div>
                    <label
                        class="text-xs font-medium text-slate-600"
                        for="stock-name"
                    >
                        Nama item
                    </label>
                    <input
                        id="stock-name"
                        v-model="itemDraft.name"
                        type="text"
                        placeholder="Snow Foam pH Netral"
                        class="mt-1.5 w-full rounded-xl border border-slate-200 bg-white px-3 py-2.5 text-sm text-slate-900 placeholder:text-slate-400 focus:border-cyan-400 focus:outline-none"
                    />
                </div>
                <div>
                    <label
                        class="text-xs font-medium text-slate-600"
                        for="stock-category"
                    >
                        Kategori
                    </label>
                    <input
                        id="stock-category"
                        v-model="itemDraft.category"
                        type="text"
                        list="stock-category-options"
                        placeholder="Bahan Cuci"
                        class="mt-1.5 w-full rounded-xl border border-slate-200 bg-white px-3 py-2.5 text-sm text-slate-900 placeholder:text-slate-400 focus:border-cyan-400 focus:outline-none"
                    />
                    <datalist id="stock-category-options">
                        <option
                            v-for="category in categories"
                            :key="category"
                            :value="category"
                        ></option>
                    </datalist>
                </div>
                <div>
                    <label
                        class="text-xs font-medium text-slate-600"
                        for="stock-unit"
                    >
                        Satuan
                    </label>
                    <input
                        id="stock-unit"
                        v-model="itemDraft.unit"
                        type="text"
                        placeholder="liter"
                        class="mt-1.5 w-full rounded-xl border border-slate-200 bg-white px-3 py-2.5 text-sm text-slate-900 placeholder:text-slate-400 focus:border-cyan-400 focus:outline-none"
                    />
                </div>
                <div v-if="editingItemId === null">
                    <label
                        class="text-xs font-medium text-slate-600"
                        for="stock-opening"
                    >
                        Stok awal
                    </label>
                    <input
                        id="stock-opening"
                        v-model.number="itemDraft.quantity"
                        type="number"
                        min="0"
                        class="mt-1.5 w-full rounded-xl border border-slate-200 bg-white px-3 py-2.5 text-sm text-slate-900 tabular-nums focus:border-cyan-400 focus:outline-none"
                    />
                </div>
                <div>
                    <label
                        class="text-xs font-medium text-slate-600"
                        for="stock-min"
                    >
                        Stok minimum
                    </label>
                    <input
                        id="stock-min"
                        v-model.number="itemDraft.minQuantity"
                        type="number"
                        min="0"
                        class="mt-1.5 w-full rounded-xl border border-slate-200 bg-white px-3 py-2.5 text-sm text-slate-900 tabular-nums focus:border-cyan-400 focus:outline-none"
                    />
                </div>
                <div>
                    <label
                        class="text-xs font-medium text-slate-600"
                        for="stock-cost"
                    >
                        Harga beli / satuan
                    </label>
                    <MoneyInput
                        id="stock-cost"
                        v-model="itemDraft.unitCost"
                        class="mt-1.5 w-full rounded-xl border border-slate-200 bg-white px-3 py-2.5 text-sm text-slate-900 tabular-nums focus:border-cyan-400 focus:outline-none"
                    />
                </div>
                <div>
                    <label
                        class="text-xs font-medium text-slate-600"
                        for="stock-supplier"
                    >
                        Supplier
                    </label>
                    <input
                        id="stock-supplier"
                        v-model="itemDraft.supplier"
                        type="text"
                        list="stock-supplier-options"
                        placeholder="Opsional"
                        class="mt-1.5 w-full rounded-xl border border-slate-200 bg-white px-3 py-2.5 text-sm text-slate-900 placeholder:text-slate-400 focus:border-cyan-400 focus:outline-none"
                    />
                    <datalist id="stock-supplier-options">
                        <option
                            v-for="supplier in suppliers"
                            :key="supplier"
                            :value="supplier"
                        ></option>
                    </datalist>
                </div>
            </div>

            <div>
                <label
                    class="text-xs font-medium text-slate-600"
                    for="stock-notes"
                >
                    Catatan
                </label>
                <textarea
                    id="stock-notes"
                    v-model="itemDraft.notes"
                    rows="2"
                    placeholder="Opsional"
                    class="mt-1.5 w-full rounded-xl border border-slate-200 bg-white px-3 py-2.5 text-sm text-slate-900 placeholder:text-slate-400 focus:border-cyan-400 focus:outline-none"
                ></textarea>
            </div>

            <p
                v-if="Object.keys(itemForm.errors).length > 0"
                class="rounded-xl bg-rose-50 px-3 py-2.5 text-xs text-rose-700 ring-1 ring-rose-100"
            >
                {{ Object.values(itemForm.errors)[0] }}
            </p>
        </div>

        <template #footer>
            <button
                type="button"
                class="flex-1 rounded-xl border border-slate-200 py-2.5 text-sm font-medium text-slate-600 transition hover:bg-slate-50"
                @click="isFormOpen = false"
            >
                Batal
            </button>
            <button
                type="button"
                class="flex-1 rounded-xl bg-gradient-to-r from-cyan-500 to-sky-600 py-2.5 text-sm font-semibold text-white transition hover:from-cyan-600 hover:to-sky-700 disabled:cursor-not-allowed disabled:opacity-50"
                :disabled="!canSaveItem || itemForm.processing"
                @click="saveItem"
            >
                Simpan item
            </button>
        </template>
    </ModalDialog>

    <!-- Record movement -->
    <ModalDialog
        :open="isMovementOpen"
        title="Catat pergerakan stok"
        caption="Stok masuk, keluar, atau penyesuaian"
        @close="isMovementOpen = false"
    >
        <div class="space-y-4">
            <div>
                <label
                    class="text-xs font-medium text-slate-600"
                    for="stock-item"
                >
                    Item
                </label>
                <select
                    id="stock-item"
                    v-model="movementDraft.itemId"
                    class="mt-1.5 w-full rounded-xl border border-slate-200 bg-white px-3 py-2.5 text-sm text-slate-900 focus:border-cyan-400 focus:outline-none"
                >
                    <option
                        v-for="option in optionList"
                        :key="option.id"
                        :value="option.id"
                    >
                        {{ option.name }} ({{ option.quantity }}
                        {{ option.unit }})
                    </option>
                </select>
            </div>

            <div>
                <p class="text-xs font-medium text-slate-600">Jenis</p>
                <div class="mt-1.5 grid grid-cols-3 gap-2">
                    <button
                        v-for="type in movementTypes"
                        :key="type"
                        type="button"
                        class="rounded-xl py-2.5 text-xs font-medium capitalize transition"
                        :class="
                            movementDraft.type === type
                                ? 'bg-slate-900 text-white'
                                : 'bg-slate-100 text-slate-600 hover:bg-slate-200'
                        "
                        @click="movementDraft.type = type"
                    >
                        {{ type }}
                    </button>
                </div>
            </div>

            <div>
                <label
                    class="text-xs font-medium text-slate-600"
                    for="stock-qty"
                >
                    Jumlah ({{ movementItem?.unit ?? '' }})
                    <span
                        v-if="movementDraft.type === 'penyesuaian'"
                        class="text-slate-400"
                    >
                        — boleh negatif
                    </span>
                </label>
                <input
                    id="stock-qty"
                    v-model.number="movementDraft.quantity"
                    type="number"
                    class="mt-1.5 w-full rounded-xl border border-slate-200 bg-white px-3 py-2.5 text-sm text-slate-900 tabular-nums focus:border-cyan-400 focus:outline-none"
                />
            </div>

            <div v-if="movementDraft.type === 'masuk'">
                <label
                    class="text-xs font-medium text-slate-600"
                    for="stock-restock-cost"
                >
                    Harga beli baru
                    <span class="text-slate-400">— kosongkan bila sama</span>
                </label>
                <MoneyInput
                    id="stock-restock-cost"
                    v-model="movementDraft.unitCost"
                    class="mt-1.5 w-full rounded-xl border border-slate-200 bg-white px-3 py-2.5 text-sm text-slate-900 tabular-nums focus:border-cyan-400 focus:outline-none"
                />
            </div>

            <div>
                <label
                    class="text-xs font-medium text-slate-600"
                    for="stock-note"
                >
                    Catatan
                </label>
                <input
                    id="stock-note"
                    v-model="movementDraft.note"
                    type="text"
                    placeholder="Alasan atau referensi"
                    class="mt-1.5 w-full rounded-xl border border-slate-200 bg-white px-3 py-2.5 text-sm text-slate-900 placeholder:text-slate-400 focus:border-cyan-400 focus:outline-none"
                />
            </div>

            <div
                v-if="movementItem"
                class="flex items-center justify-between rounded-2xl bg-slate-50 p-4"
            >
                <div>
                    <p class="text-[11px] text-slate-500">
                        Stok {{ movementItem.name }}
                    </p>
                    <p class="text-sm text-slate-600 tabular-nums">
                        {{ movementItem.quantity }} →
                        <span
                            class="font-semibold"
                            :class="
                                resultingQuantity < 0
                                    ? 'text-rose-600'
                                    : 'text-slate-900'
                            "
                        >
                            {{ resultingQuantity }} {{ movementItem.unit }}
                        </span>
                    </p>
                </div>
                <p
                    v-if="resultingQuantity < 0"
                    class="flex items-center gap-1 text-xs font-medium text-rose-600"
                >
                    <TriangleAlert class="h-3.5 w-3.5" />
                    Melebihi stok tersedia
                </p>
                <p
                    v-else-if="resultingQuantity <= movementItem.minQuantity"
                    class="flex items-center gap-1 text-xs font-medium text-rose-600"
                >
                    <TriangleAlert class="h-3.5 w-3.5" />
                    Di bawah minimum
                </p>
            </div>

            <p
                v-if="Object.keys(movementForm.errors).length > 0"
                class="rounded-xl bg-rose-50 px-3 py-2.5 text-xs text-rose-700 ring-1 ring-rose-100"
            >
                {{ Object.values(movementForm.errors)[0] }}
            </p>
        </div>

        <template #footer>
            <button
                type="button"
                class="flex-1 rounded-xl border border-slate-200 py-2.5 text-sm font-medium text-slate-600 transition hover:bg-slate-50"
                @click="isMovementOpen = false"
            >
                Batal
            </button>
            <button
                type="button"
                class="flex-1 rounded-xl bg-gradient-to-r from-cyan-500 to-sky-600 py-2.5 text-sm font-semibold text-white transition hover:from-cyan-600 hover:to-sky-700 disabled:cursor-not-allowed disabled:opacity-50"
                :disabled="!canSaveMovement || movementForm.processing"
                @click="saveMovement"
            >
                Simpan pergerakan
            </button>
        </template>
    </ModalDialog>
</template>
