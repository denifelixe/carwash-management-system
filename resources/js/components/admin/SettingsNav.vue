<script setup lang="ts">
import { Link } from '@inertiajs/vue3';
import { ShieldCheck, UserRound } from '@lucide/vue';
import { useCurrentUrl } from '@/composables/useCurrentUrl';
import { toUrl } from '@/lib/utils';
import { edit as editProfile } from '@/routes/admin/profile';
import { edit as editSecurity } from '@/routes/admin/security';
import type { NavItem } from '@/types';

const items: NavItem[] = [
    { title: 'Profil', href: editProfile(), icon: UserRound },
    { title: 'Keamanan', href: editSecurity(), icon: ShieldCheck },
];

const { isCurrentOrParentUrl } = useCurrentUrl();
</script>

<template>
    <nav
        class="flex w-full gap-1 overflow-x-auto rounded-2xl border border-slate-200/80 bg-white p-1.5 shadow-sm sm:w-fit"
        aria-label="Pengaturan akun"
    >
        <Link
            v-for="item in items"
            :key="toUrl(item.href)"
            :href="item.href"
            class="flex min-w-fit flex-1 items-center justify-center gap-2 rounded-xl px-4 py-2 text-sm font-medium transition sm:flex-none"
            :class="
                isCurrentOrParentUrl(item.href)
                    ? 'bg-cyan-50 text-cyan-700 ring-1 ring-cyan-200'
                    : 'text-slate-500 hover:bg-slate-50 hover:text-slate-800'
            "
        >
            <component :is="item.icon" class="h-4 w-4" />
            {{ item.title }}
        </Link>
    </nav>
</template>
