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
        favicon16Url: string | null;
        favicon32Url: string | null;
        appleTouchIconUrl: string | null;
        androidChrome192Url: string | null;
        androidChrome512Url: string | null;
        siteWebmanifestUrl: string | null;
        whatsapp: string;
        instagram: string;
        metaTitle: string;
        metaDescription: string;
        metaImageUrl: string | null;
    };
    capabilities: { update: boolean };
}>();

const form = useForm({
    app_name: props.settings.appName,
    whatsapp: props.settings.whatsapp,
    instagram: props.settings.instagram,
    meta_title: props.settings.metaTitle,
    meta_description: props.settings.metaDescription,
    meta_image: null as File | null,
    app_photo: null as File | null,
    favicon: null as File | null,
    favicon_16: null as File | null,
    favicon_32: null as File | null,
    apple_touch_icon: null as File | null,
    android_chrome_192: null as File | null,
    android_chrome_512: null as File | null,
    site_webmanifest: null as File | null,
});

const appPhotoPreview = ref<string | null>(props.settings.appPhotoUrl);
const metaImagePreview = ref<string | null>(props.settings.metaImageUrl);
let appPhotoObjectUrl: string | null = null;
let metaImageObjectUrl: string | null = null;

type FaviconField =
    | 'favicon'
    | 'favicon_16'
    | 'favicon_32'
    | 'apple_touch_icon'
    | 'android_chrome_192'
    | 'android_chrome_512'
    | 'site_webmanifest';

const faviconAssets: Array<{
    field: FaviconField;
    label: string;
    description: string;
    accept: string;
    currentUrl: string | null;
    required: boolean;
    preview: boolean;
}> = [
    {
        field: 'favicon',
        label: 'favicon.ico',
        description: 'Fallback utama browser. ICO, maksimal 512 KB.',
        accept: '.ico,image/x-icon,image/vnd.microsoft.icon',
        currentUrl: props.settings.faviconUrl,
        required: true,
        preview: true,
    },
    {
        field: 'favicon_16',
        label: 'favicon-16x16.png',
        description: 'PNG tepat 16×16 piksel, maksimal 256 KB.',
        accept: 'image/png',
        currentUrl: props.settings.favicon16Url,
        required: false,
        preview: true,
    },
    {
        field: 'favicon_32',
        label: 'favicon-32x32.png',
        description: 'PNG tepat 32×32 piksel, maksimal 256 KB.',
        accept: 'image/png',
        currentUrl: props.settings.favicon32Url,
        required: false,
        preview: true,
    },
    {
        field: 'apple_touch_icon',
        label: 'apple-touch-icon.png',
        description: 'PNG tepat 180×180 piksel, maksimal 512 KB.',
        accept: 'image/png',
        currentUrl: props.settings.appleTouchIconUrl,
        required: false,
        preview: true,
    },
    {
        field: 'android_chrome_192',
        label: 'android-chrome-192x192.png',
        description: 'PNG tepat 192×192 piksel, maksimal 1 MB.',
        accept: 'image/png',
        currentUrl: props.settings.androidChrome192Url,
        required: false,
        preview: true,
    },
    {
        field: 'android_chrome_512',
        label: 'android-chrome-512x512.png',
        description: 'PNG tepat 512×512 piksel, maksimal 2 MB.',
        accept: 'image/png',
        currentUrl: props.settings.androidChrome512Url,
        required: false,
        preview: true,
    },
    {
        field: 'site_webmanifest',
        label: 'site.webmanifest',
        description: 'Manifest JSON/webmanifest, maksimal 100 KB.',
        accept: '.webmanifest,application/manifest+json,application/json',
        currentUrl: props.settings.siteWebmanifestUrl,
        required: false,
        preview: false,
    },
];

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

function selectMetaImage(event: Event): void {
    form.meta_image = selectedFile(event);
    form.clearErrors('meta_image');

    if (metaImageObjectUrl !== null) {
        URL.revokeObjectURL(metaImageObjectUrl);
    }

    metaImageObjectUrl = form.meta_image
        ? URL.createObjectURL(form.meta_image)
        : null;
    metaImagePreview.value = metaImageObjectUrl ?? props.settings.metaImageUrl;
}

function selectFaviconAsset(field: FaviconField, event: Event): void {
    form[field] = selectedFile(event);
    form.clearErrors(field);
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

    if (metaImageObjectUrl !== null) {
        URL.revokeObjectURL(metaImageObjectUrl);
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

                <div
                    class="grid gap-5 border-b border-slate-100 p-5 lg:grid-cols-[minmax(0,1fr)_minmax(320px,0.9fr)]"
                >
                    <div class="space-y-4">
                        <div>
                            <h3 class="text-sm font-semibold text-slate-900">
                                Metadata & social preview
                            </h3>
                            <p
                                class="mt-1 text-xs leading-relaxed text-slate-500"
                            >
                                Digunakan oleh mesin pencari, Facebook,
                                WhatsApp, LinkedIn, dan X. URL mengikuti halaman
                                yang sedang dibuka.
                            </p>
                        </div>

                        <div>
                            <div
                                class="flex items-center justify-between gap-3"
                            >
                                <label
                                    for="meta_title"
                                    class="text-xs font-semibold text-slate-700"
                                >
                                    Meta title
                                </label>
                                <span class="text-[11px] text-slate-400">
                                    {{ form.meta_title.length }}/70
                                </span>
                            </div>
                            <input
                                id="meta_title"
                                v-model="form.meta_title"
                                type="text"
                                maxlength="70"
                                required
                                :disabled="!capabilities.update"
                                class="mt-2 block w-full rounded-xl border border-slate-200 bg-white px-3.5 py-2.5 text-sm text-slate-900 transition outline-none placeholder:text-slate-400 focus:border-cyan-400 focus:ring-3 focus:ring-cyan-500/10 disabled:cursor-not-allowed disabled:bg-slate-50"
                            />
                            <InputError
                                class="mt-2"
                                :message="form.errors.meta_title"
                            />
                        </div>

                        <div>
                            <div
                                class="flex items-center justify-between gap-3"
                            >
                                <label
                                    for="meta_description"
                                    class="text-xs font-semibold text-slate-700"
                                >
                                    Meta description
                                </label>
                                <span class="text-[11px] text-slate-400">
                                    {{ form.meta_description.length }}/200
                                </span>
                            </div>
                            <textarea
                                id="meta_description"
                                v-model="form.meta_description"
                                rows="4"
                                maxlength="200"
                                required
                                :disabled="!capabilities.update"
                                class="mt-2 block w-full resize-y rounded-xl border border-slate-200 bg-white px-3.5 py-2.5 text-sm text-slate-900 transition outline-none placeholder:text-slate-400 focus:border-cyan-400 focus:ring-3 focus:ring-cyan-500/10 disabled:cursor-not-allowed disabled:bg-slate-50"
                            />
                            <InputError
                                class="mt-2"
                                :message="form.errors.meta_description"
                            />
                        </div>

                        <div>
                            <label
                                for="meta_image"
                                class="flex w-full items-center justify-center gap-2 rounded-xl border border-dashed border-slate-300 px-3 py-2.5 text-sm font-medium text-slate-600 transition hover:border-cyan-400 hover:bg-cyan-50/50 hover:text-cyan-700"
                                :class="
                                    capabilities.update
                                        ? 'cursor-pointer'
                                        : 'cursor-not-allowed opacity-60'
                                "
                            >
                                <Upload class="size-4" />
                                {{
                                    form.meta_image?.name ??
                                    (metaImagePreview
                                        ? 'Ganti social image'
                                        : 'Pilih social image')
                                }}
                            </label>
                            <input
                                id="meta_image"
                                type="file"
                                accept="image/png,image/jpeg,image/webp"
                                :disabled="!capabilities.update"
                                class="sr-only"
                                @change="selectMetaImage"
                            />
                            <p class="mt-1.5 text-[11px] text-slate-500">
                                PNG, JPG, atau WebP. Rekomendasi 1200×630
                                piksel, maksimal 5 MB.
                            </p>
                            <InputError
                                class="mt-2"
                                :message="form.errors.meta_image"
                            />
                        </div>
                    </div>

                    <div>
                        <p class="text-xs font-semibold text-slate-700">
                            Pratinjau saat dibagikan
                        </p>
                        <div
                            class="mt-2 overflow-hidden rounded-xl border border-slate-200 bg-white shadow-sm"
                        >
                            <div
                                class="flex aspect-[1200/630] items-center justify-center overflow-hidden bg-gradient-to-br from-sky-100 to-cyan-50 text-slate-400"
                            >
                                <img
                                    v-if="metaImagePreview"
                                    :src="metaImagePreview"
                                    alt="Pratinjau social image"
                                    class="h-full w-full object-cover"
                                />
                                <ImageIcon v-else class="size-10" />
                            </div>
                            <div class="space-y-1 p-3.5">
                                <p
                                    class="line-clamp-2 text-sm font-semibold text-slate-900"
                                >
                                    {{ form.meta_title }}
                                </p>
                                <p
                                    class="line-clamp-3 text-xs leading-relaxed text-slate-500"
                                >
                                    {{ form.meta_description }}
                                </p>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="space-y-5 p-5">
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
                        <div>
                            <h3 class="text-sm font-semibold text-slate-900">
                                Paket favicon
                            </h3>
                            <p
                                class="mt-1 text-xs leading-relaxed text-slate-500"
                            >
                                Favicon ICO wajib sebagai fallback browser.
                                Asset perangkat dan manifest lainnya opsional.
                            </p>
                        </div>

                        <div
                            class="mt-4 grid gap-3 md:grid-cols-2 xl:grid-cols-3"
                        >
                            <div
                                v-for="asset in faviconAssets"
                                :key="asset.field"
                                class="rounded-xl border border-slate-200 bg-slate-50/60 p-3"
                            >
                                <div class="flex items-start gap-3">
                                    <div
                                        class="flex size-11 shrink-0 items-center justify-center overflow-hidden rounded-lg bg-white text-slate-400 ring-1 ring-slate-200"
                                    >
                                        <img
                                            v-if="
                                                asset.preview &&
                                                asset.currentUrl
                                            "
                                            :src="asset.currentUrl"
                                            :alt="'Pratinjau ' + asset.label"
                                            class="size-8 object-contain"
                                        />
                                        <AppWindow
                                            v-else-if="asset.preview"
                                            class="size-5"
                                        />
                                        <span
                                            v-else
                                            class="text-[10px] font-bold text-slate-500"
                                        >
                                            JSON
                                        </span>
                                    </div>
                                    <div class="min-w-0">
                                        <div
                                            class="flex flex-wrap items-center gap-1.5"
                                        >
                                            <p
                                                class="truncate text-xs font-semibold text-slate-900"
                                            >
                                                {{ asset.label }}
                                            </p>
                                            <span
                                                v-if="asset.required"
                                                class="rounded-full bg-rose-100 px-1.5 py-0.5 text-[10px] font-semibold text-rose-700"
                                            >
                                                Wajib
                                            </span>
                                            <span
                                                v-else
                                                class="rounded-full bg-slate-200 px-1.5 py-0.5 text-[10px] font-medium text-slate-600"
                                            >
                                                Opsional
                                            </span>
                                        </div>
                                        <p
                                            class="mt-1 text-[11px] leading-relaxed text-slate-500"
                                        >
                                            {{ asset.description }}
                                        </p>
                                    </div>
                                </div>

                                <label
                                    :for="asset.field"
                                    class="mt-3 flex w-full items-center justify-center gap-2 rounded-lg border border-dashed border-slate-300 bg-white px-2.5 py-2 text-xs font-medium text-slate-600 transition hover:border-cyan-400 hover:bg-cyan-50/50 hover:text-cyan-700"
                                    :class="
                                        capabilities.update
                                            ? 'cursor-pointer'
                                            : 'cursor-not-allowed opacity-60'
                                    "
                                >
                                    <Upload class="size-3.5" />
                                    {{
                                        form[asset.field]?.name ??
                                        (asset.currentUrl
                                            ? 'Ganti file'
                                            : 'Pilih file')
                                    }}
                                </label>
                                <input
                                    :id="asset.field"
                                    type="file"
                                    :accept="asset.accept"
                                    :required="
                                        asset.required && !asset.currentUrl
                                    "
                                    :disabled="!capabilities.update"
                                    class="sr-only"
                                    @change="
                                        selectFaviconAsset(asset.field, $event)
                                    "
                                />
                                <InputError
                                    class="mt-2"
                                    :message="form.errors[asset.field]"
                                />
                            </div>
                        </div>
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
