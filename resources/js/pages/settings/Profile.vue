<script setup lang="ts">
import { Head, useForm, usePage } from '@inertiajs/vue3';
import { Camera, Save, UserRound } from '@lucide/vue';
import { computed, onBeforeUnmount, ref } from 'vue';
import ProfileController from '@/actions/App/Http/Controllers/Settings/ProfileController';
import SettingsNav from '@/components/admin/SettingsNav.vue';
import InputError from '@/components/InputError.vue';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';

const page = usePage();
const admin = computed(() => page.props.auth.admin);
const photoPreviewUrl = ref<string | null>(null);
const photoInput = ref<HTMLInputElement | null>(null);
const form = useForm({
    _method: 'patch',
    name: admin.value?.name ?? '',
    email: admin.value?.email ?? '',
    photo: null as File | null,
});

function clearPhotoPreview(): void {
    if (photoPreviewUrl.value !== null) {
        URL.revokeObjectURL(photoPreviewUrl.value);
        photoPreviewUrl.value = null;
    }
}

function selectPhoto(event: Event): void {
    clearPhotoPreview();

    const file = (event.target as HTMLInputElement).files?.[0] ?? null;
    form.photo = file;

    if (file !== null) {
        photoPreviewUrl.value = URL.createObjectURL(file);
    }
}

function submit(): void {
    form.post(ProfileController.update.url(), {
        forceFormData: true,
        preserveScroll: true,
        onSuccess: () => {
            clearPhotoPreview();
            form.photo = null;

            if (photoInput.value !== null) {
                photoInput.value.value = '';
            }
        },
    });
}

onBeforeUnmount(clearPhotoPreview);
</script>

<template>
    <Head title="Pengaturan Profil" />

    <div class="space-y-6">
        <header>
            <p class="text-sm font-medium text-cyan-700">Pengaturan akun</p>
            <h2
                class="mt-1 text-2xl font-semibold tracking-tight text-slate-900"
            >
                Profil admin
            </h2>
            <p class="mt-1 text-sm text-slate-500">
                Perbarui nama dan alamat email yang digunakan pada akun admin.
            </p>
        </header>

        <SettingsNav />

        <div v-if="admin">
            <section
                class="rounded-2xl border border-slate-200/80 bg-white p-5 shadow-sm sm:p-6"
            >
                <div
                    class="flex items-center gap-3 border-b border-slate-100 pb-5"
                >
                    <span
                        class="flex h-11 w-11 items-center justify-center rounded-xl bg-cyan-50 text-cyan-700"
                    >
                        <UserRound class="h-5 w-5" />
                    </span>
                    <div>
                        <h3 class="font-semibold text-slate-900">
                            Informasi profil
                        </h3>
                        <p class="text-sm text-slate-500">
                            Data ini ditampilkan pada konsol admin.
                        </p>
                    </div>
                </div>

                <form
                    :action="ProfileController.update.url()"
                    method="post"
                    enctype="multipart/form-data"
                    class="mt-6 space-y-5"
                    @submit.prevent="submit"
                >
                    <div class="grid gap-2">
                        <Label for="photo" class="text-slate-700">
                            Foto profil
                        </Label>
                        <div
                            class="flex flex-col gap-4 rounded-xl border border-slate-200 bg-slate-50/60 p-4 sm:flex-row sm:items-center"
                        >
                            <div
                                class="flex h-20 w-20 shrink-0 items-center justify-center overflow-hidden rounded-full bg-gradient-to-br from-cyan-400 to-sky-600 text-white"
                            >
                                <img
                                    v-if="photoPreviewUrl || admin.avatar"
                                    :src="photoPreviewUrl ?? admin.avatar"
                                    :alt="admin.name"
                                    class="h-full w-full object-cover"
                                />
                                <UserRound v-else class="h-8 w-8" />
                            </div>
                            <div class="min-w-0 space-y-2">
                                <label
                                    for="photo"
                                    class="inline-flex cursor-pointer items-center gap-2 rounded-lg border border-slate-200 bg-white px-3 py-2 text-sm font-medium text-slate-700 shadow-sm transition hover:border-cyan-300 hover:text-cyan-700"
                                >
                                    <Camera class="h-4 w-4" />
                                    Pilih foto
                                </label>
                                <input
                                    ref="photoInput"
                                    id="photo"
                                    name="photo"
                                    type="file"
                                    accept="image/jpeg,image/png,image/webp"
                                    class="sr-only"
                                    @change="selectPhoto"
                                />
                                <p
                                    v-if="form.photo"
                                    class="max-w-sm truncate text-sm font-medium text-cyan-700"
                                >
                                    {{ form.photo.name }}
                                </p>
                                <p class="text-xs text-slate-500">
                                    JPG, PNG, atau WebP. Maksimal 20 MB.
                                </p>
                            </div>
                        </div>
                        <InputError :message="form.errors.photo" />
                    </div>

                    <div class="grid gap-2">
                        <Label for="name" class="text-slate-700">Nama</Label>
                        <Input
                            id="name"
                            v-model="form.name"
                            required
                            autocomplete="name"
                            placeholder="Nama lengkap"
                            class="h-11 border-slate-200 bg-white focus-visible:ring-cyan-500"
                        />
                        <InputError :message="form.errors.name" />
                    </div>

                    <div class="grid gap-2">
                        <Label for="email" class="text-slate-700">
                            Alamat email
                        </Label>
                        <Input
                            id="email"
                            type="email"
                            v-model="form.email"
                            required
                            autocomplete="username"
                            placeholder="email@contoh.com"
                            class="h-11 border-slate-200 bg-white focus-visible:ring-cyan-500"
                        />
                        <InputError :message="form.errors.email" />
                    </div>

                    <div
                        class="flex justify-end border-t border-slate-100 pt-5"
                    >
                        <button
                            type="submit"
                            :disabled="form.processing"
                            data-test="update-profile-button"
                            class="inline-flex items-center gap-2 rounded-xl bg-cyan-600 px-4 py-2.5 text-sm font-semibold text-white shadow-sm transition hover:bg-cyan-700 disabled:cursor-not-allowed disabled:opacity-60"
                        >
                            <Save class="h-4 w-4" />
                            {{
                                form.processing
                                    ? 'Menyimpan...'
                                    : 'Simpan perubahan'
                            }}
                        </button>
                    </div>
                </form>
            </section>
        </div>
    </div>
</template>
