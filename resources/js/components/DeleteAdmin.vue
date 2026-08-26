<script setup lang="ts">
import { Form } from '@inertiajs/vue3';
import { TriangleAlert } from '@lucide/vue';
import { useTemplateRef } from 'vue';
import ProfileController from '@/actions/App/Http/Controllers/Settings/ProfileController';
import InputError from '@/components/InputError.vue';
import PasswordInput from '@/components/PasswordInput.vue';
import { Button } from '@/components/ui/button';
import {
    Dialog,
    DialogClose,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogHeader,
    DialogTitle,
    DialogTrigger,
} from '@/components/ui/dialog';
import { Label } from '@/components/ui/label';

const passwordInput = useTemplateRef('passwordInput');
</script>

<template>
    <div class="rounded-2xl border border-rose-200/80 bg-white p-5 shadow-sm">
        <div class="flex items-start gap-3">
            <span
                class="flex h-10 w-10 shrink-0 items-center justify-center rounded-xl bg-rose-50 text-rose-600"
            >
                <TriangleAlert class="h-5 w-5" />
            </span>
            <div>
                <h3 class="font-semibold text-slate-900">Hapus akun</h3>
                <p class="mt-1 text-sm text-slate-500">
                    Tindakan ini tidak dapat dibatalkan.
                </p>
            </div>
        </div>
        <div
            class="mt-4 space-y-4 rounded-xl border border-rose-100 bg-rose-50 p-4"
        >
            <div class="text-sm text-rose-700">
                Akun dan seluruh sesi login admin ini akan dihapus permanen.
            </div>
            <Dialog>
                <DialogTrigger as-child>
                    <Button
                        variant="destructive"
                        data-test="delete-admin-button"
                        >Hapus akun</Button
                    >
                </DialogTrigger>
                <DialogContent>
                    <Form
                        v-bind="ProfileController.destroy.form()"
                        reset-on-success
                        @error="() => passwordInput?.focus()"
                        :options="{
                            preserveScroll: true,
                        }"
                        class="space-y-6"
                        v-slot="{ errors, processing, reset, clearErrors }"
                    >
                        <DialogHeader class="space-y-3">
                            <DialogTitle
                                >Yakin ingin menghapus akun?</DialogTitle
                            >
                            <DialogDescription>
                                Masukkan kata sandi untuk mengonfirmasi
                                penghapusan akun secara permanen.
                            </DialogDescription>
                        </DialogHeader>

                        <div class="grid gap-2">
                            <Label for="password" class="sr-only"
                                >Kata sandi</Label
                            >
                            <PasswordInput
                                id="password"
                                name="password"
                                ref="passwordInput"
                                placeholder="Kata sandi"
                            />
                            <InputError :message="errors.password" />
                        </div>

                        <DialogFooter class="gap-2">
                            <DialogClose as-child>
                                <Button
                                    variant="secondary"
                                    @click="
                                        () => {
                                            clearErrors();
                                            reset();
                                        }
                                    "
                                >
                                    Batal
                                </Button>
                            </DialogClose>

                            <Button
                                type="submit"
                                variant="destructive"
                                :disabled="processing"
                                data-test="confirm-delete-admin-button"
                            >
                                Hapus akun
                            </Button>
                        </DialogFooter>
                    </Form>
                </DialogContent>
            </Dialog>
        </div>
    </div>
</template>
