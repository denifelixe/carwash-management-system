<script setup lang="ts">
import { Head } from '@inertiajs/vue3';
import PortalLoginForm from '@/components/auth/PortalLoginForm.vue';
import { register } from '@/routes/admin';
import { store } from '@/routes/admin/login';
import { request } from '@/routes/admin/password';

defineOptions({
    layout: {
        title: 'Admin login',
        description: 'Sign in with your staff account',
    },
});

defineProps<{
    status?: string;
}>();
</script>

<template>
    <Head title="Admin login" />

    <div
        v-if="status"
        class="mb-4 text-center text-sm font-medium text-green-600"
    >
        {{ status }}
    </div>

    <PortalLoginForm
        :form="store.form()"
        :password-request="request()"
        :registration="register()"
    />
</template>
