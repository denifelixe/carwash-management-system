<script setup lang="ts">
import { Head } from '@inertiajs/vue3';
import PortalLoginForm from '@/components/auth/PortalLoginForm.vue';
import { store } from '@/routes/admin/login';

defineOptions({
    layout: {
        title: 'Login Admin',
        description: 'Masuk menggunakan akun staf Anda',
    },
});

defineProps<{
    status?: string;
}>();
</script>

<template>
    <Head title="Login Admin" />

    <div
        v-if="status"
        class="mb-4 text-center text-sm font-medium text-green-600"
    >
        {{ status }}
    </div>

    <PortalLoginForm :form="store.form()" />
</template>
