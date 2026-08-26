import { createInertiaApp } from '@inertiajs/vue3';
import AppLayout from '@/layouts/AppLayout.vue';
import AuthLayout from '@/layouts/AuthLayout.vue';
import DemoAdminLayout from '@/layouts/demo/AdminLayout.vue';
import DemoMemberLayout from '@/layouts/demo/MemberLayout.vue';
import MemberLayout from '@/layouts/member/MemberLayout.vue';
import SettingsLayout from '@/layouts/settings/Layout.vue';
import { initializeFlashToast } from '@/lib/flashToast';

const appName = import.meta.env.VITE_APP_NAME || 'ZenWash Auto Care';

createInertiaApp({
    title: (title) => title || appName,
    layout: (name) => {
        switch (true) {
            // The demo ships its own shells; auth screens are bare.
            case name.startsWith('demo/auth/'):
                return null;
            case name.startsWith('demo/admin/'):
                return DemoAdminLayout;
            case name.startsWith('demo/member/'):
                return DemoMemberLayout;
            case name.startsWith('member/'):
                return MemberLayout;
            case name.startsWith('auth/'):
                return AuthLayout;
            case name.startsWith('settings/'):
                return [AppLayout, SettingsLayout];
            default:
                return AppLayout;
        }
    },
    progress: {
        color: '#4B5563',
    },
});

// This will listen for flash toast data from the server...
initializeFlashToast();
