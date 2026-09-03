<script setup lang="ts">
import { Head, useForm } from '@inertiajs/vue3';
import { ImageIcon, ReceiptText, Save, Trash2, Upload } from '@lucide/vue';
import { computed, nextTick, onBeforeUnmount, ref } from 'vue';
import { update as updateReceiptSettings } from '@/actions/App/Http/Controllers/Admin/Master/ReceiptController';
import InputError from '@/components/InputError.vue';
import type { CarwashBrand } from '@/types/demo';

const props = defineProps<{
    brand: CarwashBrand;
    settings: {
        receiptBusinessName: string;
        receiptFooterNote: string;
        receiptShowLogo: boolean;
        receiptShowQr: boolean;
        appPhotoUrl: string | null;
        receiptPhotoUrl: string | null;
        hasOwnReceiptPhoto: boolean;
        receiptLogoWidth: number;
        receiptLogoWidthMin: number;
        receiptLogoWidthMax: number;
    };
    capabilities: { update: boolean };
}>();

const form = useForm({
    receipt_business_name: props.settings.receiptBusinessName,
    receipt_footer_note: props.settings.receiptFooterNote,
    receipt_show_logo: props.settings.receiptShowLogo,
    receipt_show_qr: props.settings.receiptShowQr,
    receipt_logo_width: props.settings.receiptLogoWidth,
    remove_receipt_photo: false,
    receipt_photo: null as File | null,
});

/** The roll's printable area, which the size is a share of. See posReceipt.ts. */
const PRINTABLE_WIDTH_MM = 72;

/**
 * The preview strip stands in for that 72mm, so the mark inside it is drawn at
 * the share of the roll it will actually occupy rather than at some fixed box.
 */
const logoPreviewWidth = computed<string>(
    () => `${(form.receipt_logo_width / PRINTABLE_WIDTH_MM) * 100}%`,
);

/* The effective mark: the slip's own upload, else the app photo it borrows. */
const receiptPhotoPreview = ref<string | null>(props.settings.receiptPhotoUrl);
let receiptPhotoObjectUrl: string | null = null;

function selectReceiptPhoto(event: Event): void {
    form.receipt_photo = (event.target as HTMLInputElement).files?.[0] ?? null;
    form.remove_receipt_photo = false;
    form.clearErrors('receipt_photo');

    if (receiptPhotoObjectUrl !== null) {
        URL.revokeObjectURL(receiptPhotoObjectUrl);
    }

    receiptPhotoObjectUrl = form.receipt_photo
        ? URL.createObjectURL(form.receipt_photo)
        : null;
    receiptPhotoPreview.value =
        receiptPhotoObjectUrl ?? props.settings.receiptPhotoUrl;
}

/* Dropping the slip's own mark hands it back to the app photo, not to nothing. */
function removeReceiptPhoto(): void {
    form.receipt_photo = null;
    form.remove_receipt_photo = true;
    form.clearErrors('receipt_photo');

    if (receiptPhotoObjectUrl !== null) {
        URL.revokeObjectURL(receiptPhotoObjectUrl);
        receiptPhotoObjectUrl = null;
    }

    receiptPhotoPreview.value = props.settings.appPhotoUrl;
}

onBeforeUnmount(() => {
    if (receiptPhotoObjectUrl !== null) {
        URL.revokeObjectURL(receiptPhotoObjectUrl);
    }
});

function scrollToFirstError(errors: Record<string, string>): void {
    void nextTick(() => {
        const fieldName = Object.keys(errors).find(
            (errorField) => document.getElementById(errorField) !== null,
        );

        if (fieldName === undefined) {
            return;
        }

        const field = document.getElementById(fieldName);
        const isFileField =
            field instanceof HTMLInputElement && field.type === 'file';
        const scrollTarget = isFileField
            ? document.querySelector<HTMLElement>(`label[for="${fieldName}"]`)
            : field;

        scrollTarget?.scrollIntoView({ behavior: 'smooth', block: 'center' });

        if (field instanceof HTMLElement && !isFileField) {
            field.focus({ preventScroll: true });
        }
    });
}

function submit(): void {
    if (!props.capabilities.update) {
        return;
    }

    form.submit(updateReceiptSettings(), {
        preserveScroll: true,
        onError: scrollToFirstError,
    });
}
</script>

<template>
    <div>
        <Head :title="`${brand.name} — Struk`" />

        <form class="space-y-4" @submit.prevent="submit">
            <section
                class="rounded-2xl border border-slate-200/80 bg-white shadow-sm"
            >
                <div
                    class="flex items-start gap-3 border-b border-slate-100 p-5"
                >
                    <span
                        class="flex size-11 shrink-0 items-center justify-center rounded-xl bg-amber-50 text-amber-600"
                    >
                        <ReceiptText class="size-5" />
                    </span>
                    <div>
                        <h2 class="text-base font-semibold text-slate-900">
                            Pengaturan struk
                        </h2>
                        <p class="mt-0.5 text-sm text-slate-500">
                            Berlaku untuk struk kasir 80mm, hasil cetaknya, dan
                            unduhan PDF-nya.
                        </p>
                    </div>
                </div>

                <div class="border-b border-slate-100 p-5">
                    <div class="flex items-center justify-between gap-3">
                        <label
                            for="receipt_business_name"
                            class="text-sm font-semibold text-slate-900"
                        >
                            Nama bisnis / toko
                        </label>
                        <span class="text-[11px] text-slate-400">
                            {{ form.receipt_business_name.length }}/60
                        </span>
                    </div>
                    <p class="mt-0.5 text-sm text-slate-500">
                        Dicetak pada kepala struk. Isi berbeda dari nama
                        aplikasi bila usaha menagih dengan nama lain.
                    </p>
                    <input
                        id="receipt_business_name"
                        v-model="form.receipt_business_name"
                        type="text"
                        maxlength="60"
                        :disabled="!capabilities.update"
                        class="mt-3 block w-full rounded-xl border border-slate-200 bg-white px-3.5 py-2.5 text-sm text-slate-900 transition outline-none placeholder:text-slate-400 focus:border-cyan-400 focus:ring-3 focus:ring-cyan-500/10 disabled:cursor-not-allowed disabled:bg-slate-50"
                    />
                    <InputError
                        class="mt-2"
                        :message="form.errors.receipt_business_name"
                    />
                </div>

                <div class="border-b border-slate-100 p-5">
                    <div class="flex items-center justify-between gap-3">
                        <label
                            for="receipt_footer_note"
                            class="text-sm font-semibold text-slate-900"
                        >
                            Catatan kaki struk
                        </label>
                        <span class="text-[11px] text-slate-400">
                            {{ form.receipt_footer_note.length }}/120
                        </span>
                    </div>
                    <p class="mt-0.5 text-sm text-slate-500">
                        Baris kecil di bawah “Terima kasih atas kunjungan Anda.”
                        Kosongkan bila baris ini tidak perlu dicetak.
                    </p>
                    <input
                        id="receipt_footer_note"
                        v-model="form.receipt_footer_note"
                        type="text"
                        maxlength="120"
                        placeholder="Struk ini adalah bukti pembayaran yang sah."
                        :disabled="!capabilities.update"
                        class="mt-3 block w-full rounded-xl border border-slate-200 bg-white px-3.5 py-2.5 text-sm text-slate-900 transition outline-none placeholder:text-slate-400 focus:border-cyan-400 focus:ring-3 focus:ring-cyan-500/10 disabled:cursor-not-allowed disabled:bg-slate-50"
                    />
                    <InputError
                        class="mt-2"
                        :message="form.errors.receipt_footer_note"
                    />
                </div>

                <div class="border-b border-slate-100 p-5">
                    <div class="rounded-xl border border-slate-200 p-4">
                        <div class="flex items-start gap-4">
                            <div
                                class="w-[168px] shrink-0 rounded-xl border border-slate-200 bg-white px-2 py-3 text-center"
                            >
                                <img
                                    v-if="receiptPhotoPreview"
                                    :src="receiptPhotoPreview"
                                    :style="{ width: logoPreviewWidth }"
                                    alt="Pratinjau logo struk"
                                    class="mx-auto h-auto object-contain"
                                />
                                <ImageIcon
                                    v-else
                                    class="mx-auto size-6 text-slate-300"
                                />
                                <p
                                    class="mt-1.5 truncate font-mono text-[10px] font-bold tracking-wide text-slate-900"
                                >
                                    {{
                                        form.receipt_business_name || brand.name
                                    }}
                                </p>
                                <p class="mt-1 text-[10px] text-slate-400">
                                    Pratinjau lebar roll 72mm
                                </p>
                            </div>
                            <div class="min-w-0">
                                <p class="text-sm font-semibold text-slate-900">
                                    Logo struk
                                </p>
                                <p
                                    class="mt-1 text-xs leading-relaxed text-slate-500"
                                >
                                    PNG, JPG, atau WebP. Maksimal 2 MB. Struk
                                    dicetak hitam-putih, jadi gambar berkontras
                                    tinggi terbaca paling baik.
                                </p>
                                <p
                                    v-if="!settings.hasOwnReceiptPhoto"
                                    class="mt-1 text-xs text-slate-500"
                                >
                                    Belum ada logo khusus, jadi struk memakai
                                    foto aplikasi.
                                </p>
                            </div>
                        </div>

                        <div class="mt-4 flex flex-col gap-2 sm:flex-row">
                            <label
                                for="receipt_photo"
                                class="flex flex-1 items-center justify-center gap-2 rounded-xl border border-dashed border-slate-300 px-3 py-2.5 text-sm font-medium text-slate-600 transition hover:border-cyan-400 hover:bg-cyan-50/50 hover:text-cyan-700"
                                :class="
                                    capabilities.update
                                        ? 'cursor-pointer'
                                        : 'cursor-not-allowed opacity-60'
                                "
                            >
                                <Upload class="size-4" />
                                {{
                                    form.receipt_photo?.name ??
                                    (settings.hasOwnReceiptPhoto &&
                                    !form.remove_receipt_photo
                                        ? 'Ganti logo struk'
                                        : 'Pilih logo struk')
                                }}
                            </label>
                            <button
                                v-if="
                                    (settings.hasOwnReceiptPhoto ||
                                        form.receipt_photo !== null) &&
                                    !form.remove_receipt_photo
                                "
                                type="button"
                                :disabled="!capabilities.update"
                                class="inline-flex items-center justify-center gap-2 rounded-xl border border-rose-200 px-3 py-2.5 text-sm font-medium text-rose-600 transition hover:border-rose-300 hover:bg-rose-50 disabled:cursor-not-allowed disabled:opacity-60"
                                @click="removeReceiptPhoto"
                            >
                                <Trash2 class="size-4" />
                                Pakai foto aplikasi
                            </button>
                        </div>
                        <input
                            id="receipt_photo"
                            type="file"
                            accept="image/png,image/jpeg,image/webp"
                            :disabled="!capabilities.update"
                            class="sr-only"
                            @change="selectReceiptPhoto"
                        />
                        <InputError
                            class="mt-2"
                            :message="form.errors.receipt_photo"
                        />

                        <div class="mt-4 border-t border-slate-100 pt-4">
                            <div
                                class="flex items-center justify-between gap-3"
                            >
                                <label
                                    for="receipt_logo_width"
                                    class="text-sm font-medium text-slate-900"
                                >
                                    Ukuran logo
                                </label>
                                <span
                                    class="font-mono text-xs font-semibold text-slate-700"
                                >
                                    {{ form.receipt_logo_width }} mm
                                </span>
                            </div>
                            <input
                                id="receipt_logo_width"
                                v-model.number="form.receipt_logo_width"
                                type="range"
                                :min="settings.receiptLogoWidthMin"
                                :max="settings.receiptLogoWidthMax"
                                step="1"
                                :disabled="!capabilities.update"
                                class="mt-3 block w-full accent-cyan-600 disabled:cursor-not-allowed disabled:opacity-60"
                            />
                            <div
                                class="mt-1 flex justify-between text-[11px] text-slate-400"
                            >
                                <span
                                    >{{ settings.receiptLogoWidthMin }} mm</span
                                >
                                <span
                                    >{{ settings.receiptLogoWidthMax }} mm</span
                                >
                            </div>
                            <p
                                class="mt-2 text-xs leading-relaxed text-slate-500"
                            >
                                Lebar cetak pada roll 80mm, yang area cetaknya
                                72mm. Tinggi mengikuti proporsi gambar.
                            </p>
                            <InputError
                                class="mt-2"
                                :message="form.errors.receipt_logo_width"
                            />
                        </div>
                    </div>
                </div>

                <div class="space-y-3 p-5">
                    <label
                        class="flex items-start gap-3 rounded-xl border border-slate-200 p-4 text-sm text-slate-700"
                        :class="
                            capabilities.update
                                ? 'cursor-pointer'
                                : 'cursor-not-allowed opacity-60'
                        "
                    >
                        <input
                            v-model="form.receipt_show_logo"
                            type="checkbox"
                            :disabled="!capabilities.update"
                            class="mt-0.5 h-4 w-4 rounded border-slate-300 text-cyan-600 focus:ring-cyan-500"
                        />
                        <span>
                            <span class="block font-medium text-slate-900">
                                Cetak logo pada struk
                            </span>
                            <span class="block text-xs text-slate-500">
                                Mencetak logo struk di atas nama bisnis.
                                Nonaktifkan agar struk memuat teks saja.
                            </span>
                            <span
                                v-if="
                                    form.receipt_show_logo &&
                                    receiptPhotoPreview === null
                                "
                                class="mt-1 block text-xs text-amber-600"
                            >
                                Belum ada logo struk maupun foto aplikasi, jadi
                                struk tetap tercetak tanpa logo.
                            </span>
                        </span>
                    </label>

                    <label
                        class="flex items-start gap-3 rounded-xl border border-slate-200 p-4 text-sm text-slate-700"
                        :class="
                            capabilities.update
                                ? 'cursor-pointer'
                                : 'cursor-not-allowed opacity-60'
                        "
                    >
                        <input
                            v-model="form.receipt_show_qr"
                            type="checkbox"
                            :disabled="!capabilities.update"
                            class="mt-0.5 h-4 w-4 rounded border-slate-300 text-cyan-600 focus:ring-cyan-500"
                        />
                        <span>
                            <span class="block font-medium text-slate-900">
                                Cetak QR verifikasi pada struk
                            </span>
                            <span class="block text-xs text-slate-500">
                                QR pemeriksa keabsahan struk. Hanya muncul pada
                                hasil cetak dan memakan sekitar 30mm roll.
                            </span>
                        </span>
                    </label>
                </div>

                <div
                    v-if="capabilities.update"
                    class="flex justify-end border-t border-slate-100 p-5"
                >
                    <button
                        type="submit"
                        :disabled="form.processing"
                        class="inline-flex items-center gap-2 rounded-xl bg-gradient-to-r from-cyan-500 to-sky-500 px-4 py-2.5 text-sm font-semibold text-white shadow-sm transition hover:from-cyan-600 hover:to-sky-600 disabled:cursor-not-allowed disabled:opacity-50"
                    >
                        <Save class="size-4" />
                        {{ form.processing ? 'Menyimpan…' : 'Simpan setting' }}
                    </button>
                </div>
            </section>
        </form>
    </div>
</template>
