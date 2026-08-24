<script setup lang="ts">
import { Link, usePage } from '@inertiajs/vue3';
import { Bell, Clock, Gift, House, Sparkles, User } from '@lucide/vue';
import type { LucideIcon } from '@lucide/vue';
import { computed, ref } from 'vue';
import member from '@/routes/demo/member';
import type {
    CarwashMemberShellProps,
    CarwashNotification,
} from '@/types/demo';

const page = usePage<CarwashMemberShellProps>();

const brand = computed(() => page.props.brand);
const memberProfile = computed(() => page.props.member);

const tabs: { key: string; label: string; icon: LucideIcon; url: string }[] = [
    {
        key: 'dashboard',
        label: 'Beranda',
        icon: House,
        url: member.dashboard.url(),
    },
    {
        key: 'stamps',
        label: 'Stempel',
        icon: Sparkles,
        url: member.stamps.url(),
    },
    {
        key: 'services',
        label: 'Layanan',
        icon: Clock,
        url: member.services.url(),
    },
    { key: 'rewards', label: 'Reward', icon: Gift, url: member.rewards.url() },
    { key: 'profile', label: 'Profil', icon: User, url: member.profile.url() },
];

const currentPath = computed<string>(() => page.url.split('?')[0]);

const isNotificationsOpen = ref<boolean>(false);

const notifications = ref<CarwashNotification[]>(
    page.props.notifications.map((notification) => ({ ...notification })),
);

const unreadNotifications = computed<number>(
    () =>
        notifications.value.filter((notification) => notification.unread)
            .length,
);

function readNotification(notification: CarwashNotification): void {
    notification.unread = false;
}

function readAllNotifications(): void {
    notifications.value.forEach((notification) => {
        notification.unread = false;
    });
}
</script>

<template>
    <div
        class="min-h-screen bg-slate-100 font-sans text-slate-900 sm:bg-gradient-to-br sm:from-slate-200 sm:via-slate-100 sm:to-cyan-100"
    >
        <div
            class="relative mx-auto flex min-h-screen w-full max-w-md flex-col bg-slate-50 pb-24 shadow-xl shadow-slate-300/40"
        >
            <header
                class="sticky top-0 z-30 flex items-center gap-3 border-b border-slate-200/70 bg-white/90 px-5 py-3.5 backdrop-blur-xl"
            >
                <div
                    class="flex h-10 w-10 items-center justify-center rounded-2xl bg-gradient-to-br from-cyan-400 to-sky-600 text-lg shadow-lg shadow-cyan-500/25"
                >
                    {{ brand.logo }}
                </div>
                <div class="min-w-0 flex-1 leading-tight">
                    <p class="text-[11px] text-slate-500">Selamat datang,</p>
                    <p class="truncate text-sm font-semibold text-slate-900">
                        {{ memberProfile.name.split(' ')[0] }} 👋
                    </p>
                </div>
                <div class="relative">
                    <button
                        type="button"
                        class="relative rounded-xl border border-slate-200 bg-white p-2 text-slate-500 transition hover:bg-slate-50"
                        aria-label="Notifikasi"
                        :aria-expanded="isNotificationsOpen"
                        @click="isNotificationsOpen = !isNotificationsOpen"
                    >
                        <Bell class="h-[18px] w-[18px]" />
                        <span
                            v-if="unreadNotifications > 0"
                            class="absolute -top-1 -right-1 flex h-4 min-w-4 items-center justify-center rounded-full bg-rose-500 px-1 text-[10px] font-semibold text-white ring-2 ring-white"
                        >
                            {{ unreadNotifications }}
                        </span>
                    </button>

                    <div
                        v-if="isNotificationsOpen"
                        class="fixed inset-0 z-30"
                        @click="isNotificationsOpen = false"
                    ></div>

                    <div
                        v-if="isNotificationsOpen"
                        class="absolute top-full right-0 z-40 mt-2 w-80 overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-2xl shadow-slate-400/20"
                    >
                        <div
                            class="flex items-center justify-between border-b border-slate-100 px-4 py-3"
                        >
                            <div>
                                <p class="text-sm font-semibold text-slate-900">
                                    Notifikasi
                                </p>
                                <p class="text-[11px] text-slate-500">
                                    {{ unreadNotifications }} belum dibaca
                                </p>
                            </div>
                            <button
                                v-if="unreadNotifications > 0"
                                type="button"
                                class="text-[11px] font-medium text-cyan-700 hover:text-cyan-800"
                                @click="readAllNotifications"
                            >
                                Tandai dibaca
                            </button>
                        </div>

                        <ul
                            class="max-h-80 divide-y divide-slate-50 overflow-y-auto"
                        >
                            <li
                                v-for="notification in notifications"
                                :key="notification.id"
                            >
                                <button
                                    type="button"
                                    class="flex w-full items-start gap-3 px-4 py-3 text-left transition hover:bg-slate-50"
                                    :class="
                                        notification.unread
                                            ? 'bg-cyan-50/40'
                                            : ''
                                    "
                                    @click="readNotification(notification)"
                                >
                                    <span
                                        class="flex h-9 w-9 shrink-0 items-center justify-center rounded-xl bg-slate-50 text-base"
                                    >
                                        {{ notification.icon }}
                                    </span>
                                    <span class="min-w-0 flex-1">
                                        <span class="flex items-center gap-1.5">
                                            <span
                                                class="truncate text-[13px] font-semibold text-slate-800"
                                            >
                                                {{ notification.title }}
                                            </span>
                                            <span
                                                v-if="notification.unread"
                                                class="h-1.5 w-1.5 shrink-0 rounded-full bg-cyan-500"
                                            ></span>
                                        </span>
                                        <span
                                            class="mt-0.5 block text-[11px] leading-relaxed text-slate-500"
                                        >
                                            {{ notification.message }}
                                        </span>
                                        <span
                                            class="mt-1 block text-[10px] text-slate-400"
                                        >
                                            {{ notification.time }}
                                        </span>
                                    </span>
                                </button>
                            </li>
                        </ul>
                    </div>
                </div>
            </header>

            <main class="flex-1">
                <slot />
            </main>

            <!-- Bottom nav -->
            <nav
                class="fixed bottom-0 left-1/2 z-30 w-full max-w-md -translate-x-1/2 border-t border-slate-200 bg-white/95 px-2 py-2 backdrop-blur-xl"
            >
                <div class="grid grid-cols-5">
                    <Link
                        v-for="tab in tabs"
                        :key="tab.key"
                        :href="tab.url"
                        class="flex flex-col items-center gap-1 rounded-xl py-2 transition"
                        :class="
                            currentPath === tab.url
                                ? 'text-cyan-600'
                                : 'text-slate-400 hover:text-slate-600'
                        "
                    >
                        <component
                            :is="tab.icon"
                            class="h-5 w-5"
                            :class="
                                currentPath === tab.url
                                    ? 'scale-110 transition-transform'
                                    : ''
                            "
                        />
                        <span class="text-[10px] font-medium">
                            {{ tab.label }}
                        </span>
                    </Link>
                </div>
            </nav>
        </div>
    </div>
</template>
