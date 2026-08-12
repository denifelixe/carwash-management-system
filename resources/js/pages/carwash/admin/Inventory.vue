<script setup lang="ts">
import { Head } from '@inertiajs/vue3';
import {
    ArrowLeftRight,
    Boxes,
    Package,
    Plus,
    TriangleAlert,
    Wallet,
} from '@lucide/vue';
import { computed, ref } from 'vue';
import DataToolbar from '@/components/carwash/DataToolbar.vue';
import EmptyState from '@/components/carwash/EmptyState.vue';
import ModalDialog from '@/components/carwash/ModalDialog.vue';
import StatCard from '@/components/carwash/StatCard.vue';
import StatusPill from '@/components/carwash/StatusPill.vue';
import {
    formatCurrency,
    formatShortCurrency,
} from '@/composables/useCarwashFormat';
import type {
    CarwashBrand,
    CarwashStockItem,
    CarwashStockMovement,
} from '@/types/carwash';

const props = defineProps<{
    brand: CarwashBrand;
    items: CarwashStockItem[];
    movements: CarwashStockMovement[];
    categories: string[];
    movementTypes: string[];
}>();

const itemList = ref<CarwashStockItem[]>(
    props.items.map((item) => ({ ...item })),
);
const movementLog = ref<CarwashStockMovement[]>(
    props.movements.map((movement) => ({ ...movement })),
);

const search = ref<string>('');
const categoryFilter = ref<string>('Semua');
const isMovementOpen = ref<boolean>(false);

const draft = ref({
    itemId: props.items[0].id,
    type: 'masuk',
    quantity: 1,
    note: '',
});

const filterOptions = computed<string[]>(() => ['Semua', ...props.categories]);

const filteredItems = computed<CarwashStockItem[]>(() => {
    const query = search.value.trim().toLowerCase();

    return itemList.value.filter((item) => {
        const matchesCategory =
            categoryFilter.value === 'Semua' ||
            item.category === categoryFilter.value;
        const matchesQuery =
            query === '' ||
            item.name.toLowerCase().includes(query) ||
            item.sku.toLowerCase().includes(query) ||
            item.supplier.toLowerCase().includes(query);

        return matchesCategory && matchesQuery;
    });
});

/** Items at or below their reorder point (BR-09 stock monitoring). */
const lowStockItems = computed<CarwashStockItem[]>(() =>
    itemList.value.filter((item) => item.quantity <= item.minQuantity),
);

const stockValue = computed<number>(() =>
    itemList.value.reduce(
        (total, item) => total + item.quantity * item.unitCost,
        0,
    ),
);

const draftItem = computed<CarwashStockItem>(
    () =>
        itemList.value.find((item) => item.id === draft.value.itemId) ??
        itemList.value[0],
);

/** Adjustments may be negative; ins and outs are always positive amounts. */
const resultingQuantity = computed<number>(() => {
    const current = draftItem.value.quantity;

    if (draft.value.type === 'masuk') {
        return current + Math.abs(draft.value.quantity);
    }

    if (draft.value.type === 'keluar') {
        return Math.max(current - Math.abs(draft.value.quantity), 0);
    }

    return Math.max(current + draft.value.quantity, 0);
});

function stockLevelClass(item: CarwashStockItem): string {
    if (item.quantity <= item.minQuantity) {
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

function recordMovement(): void {
    const item = draftItem.value;
    const delta = resultingQuantity.value - item.quantity;

    item.quantity = resultingQuantity.value;
    item.updatedAt = 'Baru saja';

    movementLog.value = [
        {
            id: movementLog.value.length + 1,
            itemId: item.id,
            item: item.name,
            sku: item.sku,
            type: draft.value.type,
            quantity: delta,
            note: draft.value.note || '—',
            date: 'Baru saja',
            time: '',
            by: 'Sesi demo',
        },
        ...movementLog.value,
    ];

    draft.value.quantity = 1;
    draft.value.note = '';
    isMovementOpen.value = false;
}
</script>

<template>
    <Head :title="`${brand.name} — Stock Inventory`" />

    <div class="space-y-4">
        <section class="grid grid-cols-1 gap-4 sm:grid-cols-2 xl:grid-cols-4">
            <StatCard
                label="Total item"
                :value="String(itemList.length)"
                :caption="`${categories.length} kategori`"
                :icon="Boxes"
            />
            <StatCard
                label="Stok menipis"
                :value="String(lowStockItems.length)"
                caption="di bawah batas minimum"
                :icon="TriangleAlert"
                tone="rose"
            />
            <StatCard
                label="Nilai persediaan"
                :value="formatShortCurrency(stockValue)"
                caption="harga beli × stok"
                :icon="Wallet"
                tone="emerald"
            />
            <StatCard
                label="Pergerakan"
                :value="String(movementLog.length)"
                caption="tercatat periode ini"
                :icon="ArrowLeftRight"
                tone="amber"
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
                        {{ filteredItems.length }} item ditampilkan
                    </p>
                </div>
                <div class="flex flex-wrap items-center gap-2">
                    <DataToolbar
                        v-model:search="search"
                        placeholder="Cari item / SKU"
                        :filters="filterOptions"
                        :active-filter="categoryFilter"
                        @filter="categoryFilter = $event"
                    />
                    <button
                        type="button"
                        class="flex items-center gap-2 rounded-xl bg-gradient-to-r from-cyan-500 to-sky-600 px-3 py-2 text-sm font-medium text-white shadow-lg shadow-cyan-500/25 transition hover:from-cyan-600 hover:to-sky-700"
                        @click="isMovementOpen = true"
                    >
                        <Plus class="h-4 w-4" />
                        Catat Stok
                    </button>
                </div>
            </div>

            <div v-if="filteredItems.length > 0" class="overflow-x-auto">
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
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-50">
                        <tr
                            v-for="item in filteredItems"
                            :key="item.id"
                            class="transition hover:bg-slate-50/70"
                        >
                            <td class="px-5 py-3.5">
                                <p class="font-medium text-slate-900">
                                    {{ item.name }}
                                </p>
                                <p class="text-[11px] text-slate-500">
                                    {{ item.sku }}
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
                                            item.quantity <= item.minQuantity
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
                                {{ item.supplier }}
                            </td>
                            <td
                                class="px-5 py-3.5 text-right font-medium text-slate-900 tabular-nums"
                            >
                                {{
                                    formatCurrency(
                                        item.quantity * item.unitCost,
                                    )
                                }}
                            </td>
                            <td class="px-5 py-3.5 text-[11px] text-slate-500">
                                {{ item.updatedAt }}
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
            <ul class="divide-y divide-slate-50">
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
                        {{ movement.date }}
                    </p>
                </li>
            </ul>
        </section>
    </div>

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
                    v-model="draft.itemId"
                    class="mt-1.5 w-full rounded-xl border border-slate-200 px-3 py-2.5 text-sm focus:border-cyan-400 focus:outline-none"
                >
                    <option
                        v-for="item in itemList"
                        :key="item.id"
                        :value="item.id"
                    >
                        {{ item.name }} ({{ item.quantity }} {{ item.unit }})
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
                            draft.type === type
                                ? 'bg-slate-900 text-white'
                                : 'bg-slate-100 text-slate-600 hover:bg-slate-200'
                        "
                        @click="draft.type = type"
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
                    Jumlah ({{ draftItem.unit }})
                    <span
                        v-if="draft.type === 'penyesuaian'"
                        class="text-slate-400"
                    >
                        — boleh negatif
                    </span>
                </label>
                <input
                    id="stock-qty"
                    v-model.number="draft.quantity"
                    type="number"
                    class="mt-1.5 w-full rounded-xl border border-slate-200 px-3 py-2.5 text-sm tabular-nums focus:border-cyan-400 focus:outline-none"
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
                    v-model="draft.note"
                    type="text"
                    placeholder="Alasan atau referensi"
                    class="mt-1.5 w-full rounded-xl border border-slate-200 px-3 py-2.5 text-sm focus:border-cyan-400 focus:outline-none"
                />
            </div>

            <div
                class="flex items-center justify-between rounded-2xl bg-slate-50 p-4"
            >
                <div>
                    <p class="text-[11px] text-slate-500">
                        Stok {{ draftItem.name }}
                    </p>
                    <p class="text-sm text-slate-600 tabular-nums">
                        {{ draftItem.quantity }} →
                        <span class="font-semibold text-slate-900">
                            {{ resultingQuantity }} {{ draftItem.unit }}
                        </span>
                    </p>
                </div>
                <p
                    v-if="resultingQuantity <= draftItem.minQuantity"
                    class="flex items-center gap-1 text-xs font-medium text-rose-600"
                >
                    <TriangleAlert class="h-3.5 w-3.5" />
                    Di bawah minimum
                </p>
            </div>
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
                class="flex-1 rounded-xl bg-gradient-to-r from-cyan-500 to-sky-600 py-2.5 text-sm font-semibold text-white transition hover:from-cyan-600 hover:to-sky-700"
                @click="recordMovement"
            >
                Simpan pergerakan
            </button>
        </template>
    </ModalDialog>
</template>
