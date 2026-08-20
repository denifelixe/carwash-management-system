<script setup lang="ts">
import { computed } from 'vue';

const props = defineProps<{
    status: string;
}>();

/** Status words the `capitalize` class alone would render badly. */
const statusLabels: Record<string, string> = {
    sebagian: 'Pembayaran Sebagian/Booking',
    pelunasan: 'Pembayaran Lunas/Sisa',
    lunas: 'Pembayaran Lunas/Sisa',
    booking: 'Booking - Belum Datang',
};

const label = computed<string>(
    () => statusLabels[props.status] ?? props.status,
);

/** Maps every status word used across the modules onto a colour family. */
const toneClass = computed<string>(() => {
    switch (props.status) {
        case 'selesai':
        case 'lunas':
        case 'aktif':
        case 'masuk':
            return 'bg-emerald-50 text-emerald-700 ring-1 ring-emerald-200';
        case 'proses':
        case 'dikerjakan':
        case 'terjadwal':
        case 'hari ini':
        case 'berjalan':
        case 'sebagian':
        case 'booking':
            return 'bg-cyan-50 text-cyan-700 ring-1 ring-cyan-200';
        case 'pelunasan':
            return 'bg-violet-50 text-violet-700 ring-1 ring-violet-200';
        case 'menunggu':
        case 'mendatang':
        case 'Booking Mendatang':
        case 'belum bayar':
        case 'penyesuaian':
            return 'bg-amber-50 text-amber-700 ring-1 ring-amber-200';
        case 'batal':
        case 'nonaktif':
        case 'tidak aktif':
        case 'keluar':
        case 'terpakai':
            return 'bg-rose-50 text-rose-700 ring-1 ring-rose-200';
        default:
            return 'bg-slate-100 text-slate-600 ring-1 ring-slate-200';
    }
});
</script>

<template>
    <span
        class="inline-flex rounded-full px-2 py-1 text-[11px] font-medium capitalize"
        :class="toneClass"
    >
        {{ label }}
    </span>
</template>
