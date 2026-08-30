<script setup lang="ts">
import { Head, useForm } from '@inertiajs/vue3';
import { AppWindow, ImageIcon, Save, Upload } from '@lucide/vue';
import { onBeforeUnmount, ref } from 'vue';
import { update as updateAppSettings } from '@/actions/App/Http/Controllers/Admin/Master/AppSettingController';
import InputError from '@/components/InputError.vue';
import type { CarwashBrand } from '@/types/demo';

const props = defineProps<{
    brand: CarwashBrand;
    settings: {
        appName: string;
        appPhotoUrl: string | null;
        faviconUrl: string | null;
        whatsapp: string;
        instagram: string;
    };
    capabilities: { update: boolean };
}>();

const form = useForm({
    app_name: props.settings.appName,
    whatsapp: props.settings.whatsapp,
    instagram: props.settings.instagram,
    app_photo: null as File | null,
    favicon: null as File | null,
});

const appPhotoPreview = ref<string | null>(props.settings.appPhotoUrl);
const faviconPreview = ref<string | null>(props.settings.faviconUrl);
let appPhotoObjectUrl: string | null = null;
let faviconObjectUrl: string | null = null;

function selectedFile(event: Event): File | null {
    return (event.target as HTMLInputElement).files?.[0] ?? null;
}

function selectAppPhoto(event: Event): void {
    form.app_photo = selectedFile(event);
    form.clearErrors('app_photo');

    if (appPhotoObjectUrl !== null) {
        URL.revokeObjectURL(appPhotoObjectUrl);
    }

    appPhotoObjectUrl = form.app_photo
        ? URL.createObjectURL(form.app_photo)
        : null;
    appPhotoPreview.value = appPhotoObjectUrl ?? props.settings.appPhotoUrl;
}

function selectFavicon(event: Event): void {
    form.favicon = selectedFile(event);
    form.clearErrors('favicon');

    if (faviconObjectUrl !== null) {
        URL.revokeObjectURL(faviconObjectUrl);
    }

    faviconObjectUrl = form.favicon ? URL.createObjectURL(form.favicon) : null;
    faviconPreview.value = faviconObjectUrl ?? props.settings.faviconUrl;
}

function submit(): void {
    if (!props.capabilities.update) {
        return;
    }

    form.submit(updateAppSettings(), { preserveScroll: true });
}

onBeforeUnmount(() => {
    if (appPhotoObjectUrl !== null) {
        URL.revokeObjectURL(appPhotoObjectUrl);
    }

    if (faviconObjectUrl !== null) {
        URL.revokeObjectURL(faviconObjectUrl);
    }
});
</script>

<template>
    <div>
        <Head :title="`${settings.appName} — App Setting`" />

        <form class="space-y-4" @submit.prevent="submit">
            <section
                class="rounded-2xl border border-slate-200/80 bg-white p-5 shadow-sm"
            >
                <div class="flex items-start gap-3">
                    <span
                        class="flex size-11 shrink-0 items-center justify-center rounded-xl bg-cyan-50 text-cyan-600"
                    >
                        <AppWindow class="size-5" />
                    </span>
                    <div>
                        <h2 class="text-base font-semibold text-slate-900">
                            Identitas aplikasi
                        </h2>
                        <p class="mt-0.5 text-sm text-slate-500">
                            Atur nama dan gambar yang tampil pada aplikasi serta
                            tab browser.
                        </p>
                    </div>
                </div>
            </section>

            <section
                class="rounded-2xl border border-slate-200/80 bg-white shadow-sm"
            >
                <div class="border-b border-slate-100 p-5">
                    <label
                        for="app_name"
                        class="text-sm font-semibold text-slate-900"
                    >
                        Nama aplikasi
                    </label>
                    <p class="mt-0.5 text-sm text-slate-500">
                        Nama ini digunakan pada sidebar dan judul halaman.
                    </p>
                    <input
                        id="app_name"
                        v-model="form.app_name"
                        type="text"
                        maxlength="100"
                        :disabled="!capabilities.update"
                        class="mt-3 block w-full rounded-xl border border-slate-200 bg-white px-3.5 py-2.5 text-sm text-slate-900 transition outline-none placeholder:text-slate-400 focus:border-cyan-400 focus:ring-3 focus:ring-cyan-500/10 disabled:cursor-not-allowed disabled:bg-slate-50"
                    />
                    <InputError class="mt-2" :message="form.errors.app_name" />
                </div>

                <div
                    class="grid gap-5 border-b border-slate-100 p-5 md:grid-cols-2"
                >
                    <div>
                        <label
                            for="whatsapp"
                            class="text-sm font-semibold text-slate-900"
                        >
                            Nomor WhatsApp
                        </label>
                        <p class="mt-0.5 text-sm text-slate-500">
                            Ditampilkan pada struk POS dan rekap keuangan.
                        </p>
                        <div class="relative mt-3">
                            <svg
                                viewBox="0 0 24 24"
                                aria-hidden="true"
                                class="pointer-events-none absolute top-1/2 left-3.5 size-4 -translate-y-1/2 text-emerald-600"
                                fill="none"
                                stroke="currentColor"
                                stroke-width="1.8"
                                stroke-linecap="round"
                                stroke-linejoin="round"
                            >
                                <path
                                    d="M21 11.5a8.5 8.5 0 0 1-12.6 7.4L3 21l2.1-5.2A8.5 8.5 0 1 1 21 11.5Z"
                                />
                                <path
                                    d="M8.2 8.1c.5 3.2 2.4 5.1 5.7 5.7l1.5-1.5"
                                />
                            </svg>
                            <input
                                id="whatsapp"
                                v-model="form.whatsapp"
                                type="tel"
                                inputmode="tel"
                                autocomplete="tel"
                                placeholder="6281234567890"
                                :disabled="!capabilities.update"
                                class="block w-full rounded-xl border border-slate-200 bg-white py-2.5 pr-3.5 pl-10 text-sm text-slate-900 transition outline-none placeholder:text-slate-400 focus:border-cyan-400 focus:ring-3 focus:ring-cyan-500/10 disabled:cursor-not-allowed disabled:bg-slate-50"
                            />
                        </div>
                        <InputError
                            class="mt-2"
                            :message="form.errors.whatsapp"
                        />
                    </div>

                    <div>
                        <label
                            for="instagram"
                            class="text-sm font-semibold text-slate-900"
                        >
                            Instagram
                        </label>
                        <p class="mt-0.5 text-sm text-slate-500">
                            Username tanpa URL; awalan @ boleh disertakan.
                        </p>
                        <div class="relative mt-3">
                            <svg
                                viewBox="0 0 24 24"
                                aria-hidden="true"
                                class="pointer-events-none absolute top-1/2 left-3.5 size-4 -translate-y-1/2 text-fuchsia-600"
                                fill="none"
                                stroke="currentColor"
                                stroke-width="1.8"
                            >
                                <rect
                                    x="3"
                                    y="3"
                                    width="18"
                                    height="18"
                                    rx="5"
                                />
                                <circle cx="12" cy="12" r="4" />
                                <circle
                                    cx="17.5"
                                    cy="6.5"
                                    r="1"
                                    fill="currentColor"
                                    stroke="none"
                                />
                            </svg>
                            <input
                                id="instagram"
                                v-model="form.instagram"
                                type="text"
                                autocomplete="off"
                                maxlength="31"
                                placeholder="namausaha"
                                :disabled="!capabilities.update"
                                class="block w-full rounded-xl border border-slate-200 bg-white py-2.5 pr-3.5 pl-10 text-sm text-slate-900 transition outline-none placeholder:text-slate-400 focus:border-cyan-400 focus:ring-3 focus:ring-cyan-500/10 disabled:cursor-not-allowed disabled:bg-slate-50"
                            />
                        </div>
                        <InputError
                            class="mt-2"
                            :message="form.errors.instagram"
                        />
                    </div>
                </div>

                <div class="grid gap-5 p-5 md:grid-cols-2">
                    <div class="rounded-xl border border-slate-200 p-4">
                        <div class="flex items-center gap-3">
                            <div
                                class="flex size-20 shrink-0 items-center justify-center overflow-hidden rounded-2xl bg-slate-100 text-slate-400"
                            >
                                <img
                                    v-if="appPhotoPreview"
                                    :src="appPhotoPreview"
                                    alt="Pratinjau foto aplikasi"
                                    class="h-full w-full object-cover"
                                />
                                <ImageIcon v-else class="size-7" />
                            </div>
                            <div class="min-w-0">
                                <p class="text-sm font-semibold text-slate-900">
                                    Foto aplikasi
                                </p>
                                <p
                                    class="mt-1 text-xs leading-relaxed text-slate-500"
                                >
                                    PNG, JPG, atau WebP. Maksimal 2 MB.
                                </p>
                            </div>
                        </div>

                        <label
                            for="app_photo"
                            class="mt-4 flex w-full items-center justify-center gap-2 rounded-xl border border-dashed border-slate-300 px-3 py-2.5 text-sm font-medium text-slate-600 transition hover:border-cyan-400 hover:bg-cyan-50/50 hover:text-cyan-700"
                            :class="
                                capabilities.update
                                    ? 'cursor-pointer'
                                    : 'cursor-not-allowed opacity-60'
                            "
                        >
                            <Upload class="size-4" />
                            {{ form.app_photo?.name ?? 'Pilih foto' }}
                        </label>
                        <input
                            id="app_photo"
                            type="file"
                            accept="image/png,image/jpeg,image/webp"
                            :disabled="!capabilities.update"
                            class="sr-only"
                            @change="selectAppPhoto"
                        />
                        <InputError
                            class="mt-2"
                            :message="form.errors.app_photo"
                        />
                    </div>

                    <div class="rounded-xl border border-slate-200 p-4">
                        <div class="flex items-center gap-3">
                            <div
                                class="flex size-20 shrink-0 items-center justify-center overflow-hidden rounded-2xl bg-slate-100 text-slate-400"
                            >
                                <img
                                    v-if="faviconPreview"
                                    :src="faviconPreview"
                                    alt="Pratinjau favicon"
                                    class="size-12 object-contain"
                                />
                                <AppWindow v-else class="size-7" />
                            </div>
                            <div class="min-w-0">
                                <p class="text-sm font-semibold text-slate-900">
                                    Favicon
                                </p>
                                <p
                                    class="mt-1 text-xs leading-relaxed text-slate-500"
                                >
                                    Gunakan gambar persegi. Maksimal 1 MB.
                                </p>
                            </div>
                        </div>

                        <label
                            for="favicon"
                            class="mt-4 flex w-full items-center justify-center gap-2 rounded-xl border border-dashed border-slate-300 px-3 py-2.5 text-sm font-medium text-slate-600 transition hover:border-cyan-400 hover:bg-cyan-50/50 hover:text-cyan-700"
                            :class="
                                capabilities.update
                                    ? 'cursor-pointer'
                                    : 'cursor-not-allowed opacity-60'
                            "
                        >
                            <Upload class="size-4" />
                            {{ form.favicon?.name ?? 'Pilih favicon' }}
                        </label>
                        <input
                            id="favicon"
                            type="file"
                            accept="image/png,image/jpeg,image/webp"
                            :disabled="!capabilities.update"
                            class="sr-only"
                            @change="selectFavicon"
                        />
                        <InputError
                            class="mt-2"
                            :message="form.errors.favicon"
                        />
                    </div>
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
