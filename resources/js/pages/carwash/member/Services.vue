<script setup lang="ts">
import { Head } from '@inertiajs/vue3';
import { Search, Sparkles, Timer, X } from '@lucide/vue';
import { computed, ref } from 'vue';
import EmptyState from '@/components/carwash/EmptyState.vue';
import ModalDialog from '@/components/carwash/ModalDialog.vue';
import { formatCurrency } from '@/composables/useCarwashFormat';
import type {
    CarwashBrand,
    CarwashMember,
    CarwashService,
} from '@/types/carwash';

const props = defineProps<{
    brand: CarwashBrand;
    member: CarwashMember;
    services: CarwashService[];
    categories: string[];
}>();

const activeCategory = ref<string>('Semua');
const search = ref<string>('');
const detailService = ref<CarwashService | null>(null);

const filterOptions = computed<string[]>(() => ['Semua', ...props.categories]);

const visibleServices = computed<CarwashService[]>(() => {
    const query = search.value.trim().toLowerCase();

    return props.services.filter((service) => {
        const matchesCategory =
            activeCategory.value === 'Semua' ||
            service.category === activeCategory.value;
        const matchesQuery =
            query === '' ||
            service.name.toLowerCase().includes(query) ||
            service.description.toLowerCase().includes(query);

        return service.isActive && matchesCategory && matchesQuery;
    });
});
</script>

<template>
    <Head :title="`${brand.name} — Layanan`" />

    <div class="space-y-4 px-5 py-5">
        <section>
            <h1 class="text-base font-semibold text-slate-900">
                Katalog layanan
            </h1>
            <p class="mt-0.5 text-xs text-slate-500">
                Harga dan durasi layanan di {{ brand.name }}.
            </p>
        </section>

        <div
            class="flex items-center gap-2 rounded-xl bg-white px-3 py-2.5 ring-1 ring-slate-200"
        >
            <Search class="h-4 w-4 shrink-0 text-slate-400" />
            <input
                v-model="search"
                type="search"
                placeholder="Cari layanan"
                class="w-full bg-transparent text-sm text-slate-700 placeholder:text-slate-400 focus:outline-none"
            />
        </div>

        <div class="flex flex-wrap gap-2">
            <button
                v-for="category in filterOptions"
                :key="category"
                type="button"
                class="rounded-full px-3.5 py-1.5 text-xs font-medium transition"
                :class="
                    activeCategory === category
                        ? 'bg-slate-900 text-white'
                        : 'bg-white text-slate-600 ring-1 ring-slate-200'
                "
                @click="activeCategory = category"
            >
                {{ category }}
            </button>
        </div>

        <ul v-if="visibleServices.length > 0" class="space-y-3">
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
                                    {{ formatCurrency(service.price) }}
                                </span>
                                <span
                                    class="flex items-center gap-1 text-[11px] text-slate-400"
                                >
                                    <Timer class="h-3.5 w-3.5" />
                                    {{ service.duration }} menit
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
                {{ detailService.category }}
            </span>
            <p class="mt-3 text-xs leading-relaxed text-slate-500">
                {{ detailService.description }}
            </p>

            <dl class="mt-5 space-y-2 rounded-2xl bg-slate-50 p-4 text-sm">
                <div class="flex justify-between">
                    <dt class="text-slate-500">Harga</dt>
                    <dd class="font-semibold text-slate-900 tabular-nums">
                        {{ formatCurrency(detailService.price) }}
                    </dd>
                </div>
                <div class="flex justify-between">
                    <dt class="text-slate-500">Estimasi pengerjaan</dt>
                    <dd class="text-slate-800 tabular-nums">
                        ± {{ detailService.duration }} menit
                    </dd>
                </div>
                <div class="flex justify-between">
                    <dt class="text-slate-500">Stempel didapat</dt>
                    <dd class="font-medium text-emerald-600 tabular-nums">
                        +{{ detailService.stamps }}
                    </dd>
                </div>
            </dl>

            <p class="mt-4 text-[11px] text-slate-400">
                Tunjukkan kartu member kamu ke kasir untuk mencatat stempel.
            </p>
        </div>
    </ModalDialog>
</template>
