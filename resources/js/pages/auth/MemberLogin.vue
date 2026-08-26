<script setup lang="ts">
import { Head } from '@inertiajs/vue3';
import PortalLoginForm from '@/components/auth/PortalLoginForm.vue';
import { store } from '@/routes/member/login';

defineOptions({
    layout: {
        title: 'Member login',
        description: 'Sign in with your member account',
    },
});

defineProps<{
    status?: string;
}>();
</script>

<template>
    <Head title="Member login" />

    <div
        v-if="status"
        class="mb-4 text-center text-sm font-medium text-green-600"
    >
        {{ status }}
    </div>

    <PortalLoginForm :form="store.form()" />
</template>
