<script setup lang="ts">
import { ChevronDown, Printer } from '@lucide/vue';
import {
    DropdownMenu,
    DropdownMenuContent,
    DropdownMenuItem,
    DropdownMenuTrigger,
} from '@/components/ui/dropdown-menu';
import type { RecapPaper } from '@/lib/recapSheet';

/**
 * The paper picker both recaps print from. It chooses the paper, not the
 * outcome: the sheet opens in its own window, and the toolbar there offers
 * "Cetak" to the printer and "Unduh PDF" straight to a file, both on whichever
 * paper was picked here — so one menu covers cetak and download alike.
 */
defineProps<{
    /** Set when the browser refused the window, so the desk can retry. */
    blocked?: boolean;
}>();

const emit = defineEmits<{
    print: [paper: RecapPaper];
}>();
</script>

<template>
    <div class="flex flex-col items-start gap-1">
        <DropdownMenu>
            <DropdownMenuTrigger as-child>
                <button
                    type="button"
                    class="inline-flex items-center gap-1.5 rounded-xl border border-slate-200 bg-white px-3 py-2 text-xs font-semibold text-slate-600 shadow-sm transition hover:border-cyan-200 hover:bg-cyan-50 hover:text-cyan-700 focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-cyan-600"
                >
                    <Printer class="h-4 w-4" />
                    Cetak / Unduh
                    <ChevronDown class="h-3.5 w-3.5" />
                </button>
            </DropdownMenuTrigger>
            <DropdownMenuContent align="end" class="w-60">
                <DropdownMenuItem
                    class="cursor-pointer flex-col items-start gap-0.5"
                    @select="emit('print', 'a4')"
                >
                    <span class="font-semibold">Cetak A4</span>
                    <span class="text-xs text-slate-500">
                        Ukuran surat, untuk arsip
                    </span>
                </DropdownMenuItem>
                <DropdownMenuItem
                    class="cursor-pointer flex-col items-start gap-0.5"
                    @select="emit('print', 'struk')"
                >
                    <span class="font-semibold">Cetak Struk (78mm)</span>
                    <span class="text-xs text-slate-500">
                        Roll kasir 80mm
                    </span>
                </DropdownMenuItem>
            </DropdownMenuContent>
        </DropdownMenu>

        <p v-if="blocked" class="max-w-60 text-[11px] text-amber-600">
            Jendela cetak diblokir browser. Izinkan pop-up untuk situs ini, lalu
            coba lagi.
        </p>
    </div>
</template>
