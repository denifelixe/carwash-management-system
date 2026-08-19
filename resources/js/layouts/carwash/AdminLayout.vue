<script setup lang="ts">
import { Link, router, usePage } from '@inertiajs/vue3';
import {
    Bell,
    Boxes,
    CalendarClock,
    ChartColumn,
    ClipboardList,
    Gift,
    LayoutDashboard,
    LogOut,
    Menu,
    PanelLeftClose,
    PanelLeftOpen,
    ScanLine,
    ShieldCheck,
    Users,
    Wallet,
    X,
} from '@lucide/vue';
import type { LucideIcon } from '@lucide/vue';
import { computed, onMounted, ref } from 'vue';
import { home } from '@/routes';
import admin from '@/routes/carwash/admin';
import session from '@/routes/carwash/session';
import type {
    CarwashAdminShellProps,
    CarwashNotification,
} from '@/types/carwash';

const page = usePage<CarwashAdminShellProps>();

const brand = computed(() => page.props.brand);
const role = computed(() => page.props.role);
const modules = computed(() => page.props.modules);
const persona = computed(() => page.props.persona);

/** Icon key on each module record → the actual Lucide component. */
const moduleIcons: Record<string, LucideIcon> = {
    dashboard: LayoutDashboard,
    orders: ClipboardList,
    pos: ScanLine,
    customers: Users,
    finance: Wallet,
    bookings: CalendarClock,
    inventory: Boxes,
    rewards: Gift,
    users: ShieldCheck,
    reports: ChartColumn,
};

/** Route name on each module record → the Wayfinder helper for its URL. */
const moduleUrls: Record<string, () => string> = {
    'carwash.admin.dashboard': () => admin.dashboard.url(),
    'carwash.admin.orders': () => admin.orders.url(),
    'carwash.admin.pos': () => admin.pos.url(),
    'carwash.admin.customers': () => admin.customers.url(),
    'carwash.admin.finance': () => admin.finance.url(),
    'carwash.admin.bookings': () => admin.bookings.url(),
    'carwash.admin.inventory': () => admin.inventory.url(),
    'carwash.admin.rewards': () => admin.rewards.url(),
    'carwash.admin.users': () => admin.users.url(),
    'carwash.admin.reports': () => admin.reports.url(),
};

const sidebarStorageKey = 'carwash.admin.sidebar';

/** Off-canvas drawer state, only meaningful below `lg`. */
const isSidebarOpen = ref<boolean>(false);
/** Icon-rail state, only meaningful from `lg` up. Read after mount so SSR stays stable. */
const isSidebarCollapsed = ref<boolean>(false);
const isNotificationsOpen = ref<boolean>(false);

onMounted(() => {
    isSidebarCollapsed.value =
        localStorage.getItem(sidebarStorageKey) === 'collapsed';
});

const notifications = ref<CarwashNotification[]>(
    page.props.notifications.map((notification) => ({ ...notification })),
);

const unreadNotifications = computed<number>(
    () =>
        notifications.value.filter((notification) => notification.unread)
            .length,
);

const activeModule = computed(
    () =>
        modules.value.find(
            (module) => moduleUrls[module.route]?.() === page.url.split('?')[0],
        ) ?? modules.value[0],
);

function readNotification(notification: CarwashNotification): void {
    notification.unread = false;
}

function readAllNotifications(): void {
    notifications.value.forEach((notification) => {
        notification.unread = false;
    });
}

function toggleSidebarCollapse(): void {
    isSidebarCollapsed.value = !isSidebarCollapsed.value;
    localStorage.setItem(
        sidebarStorageKey,
        isSidebarCollapsed.value ? 'collapsed' : 'expanded',
    );
}

function leaveConsole(): void {
    router.post(session.exit.url());
}
</script>

<template>
    <div class="min-h-screen bg-slate-100 font-sans text-slate-900">
        <!-- Sidebar -->
        <aside
            class="fixed inset-y-0 left-0 z-50 flex w-72 flex-col bg-slate-950 transition-[transform,width] duration-300 lg:translate-x-0"
            :class="[
                isSidebarOpen ? 'translate-x-0' : '-translate-x-full',
                isSidebarCollapsed ? 'lg:w-20' : 'lg:w-72',
            ]"
        >
            <div
                class="flex items-center gap-3 border-b border-white/5 px-5 py-5"
                :class="isSidebarCollapsed ? 'lg:justify-center lg:px-0' : ''"
            >
                <div
                    class="flex h-11 w-11 shrink-0 items-center justify-center rounded-2xl bg-gradient-to-br from-cyan-400 to-sky-600 text-xl shadow-lg shadow-cyan-500/30"
                >
                    {{ brand.logo }}
                </div>
                <div
                    class="min-w-0 flex-1 leading-tight"
                    :class="isSidebarCollapsed ? 'lg:hidden' : ''"
                >
                    <p class="truncate text-sm font-semibold text-white">
                        {{ brand.name }}
                    </p>
                    <p class="text-[11px] text-cyan-300/80">
                        Admin & POS Console
                    </p>
                </div>
                <button
                    type="button"
                    class="rounded-lg p-1.5 text-slate-400 hover:bg-white/5 hover:text-white lg:hidden"
                    aria-label="Tutup menu"
                    @click="isSidebarOpen = false"
                >
                    <X class="h-5 w-5" />
                </button>
            </div>

            <!-- Role badge: makes the active access level obvious (BR-11) -->
            <div class="px-3 pt-4">
                <div
                    class="flex items-center gap-2 rounded-xl px-3 py-2 ring-1"
                    :class="
                        isSidebarCollapsed ? 'lg:justify-center lg:px-0' : ''
                    "
                    :title="isSidebarCollapsed ? role.name : undefined"
                    :style="{
                        backgroundColor: `${role.accent}1f`,
                        boxShadow: `inset 0 0 0 1px ${role.accent}55`,
                    }"
                >
                    <span class="text-base">{{ role.icon }}</span>
                    <div
                        class="min-w-0 flex-1 leading-tight"
                        :class="isSidebarCollapsed ? 'lg:hidden' : ''"
                    >
                        <p
                            class="text-[10px] tracking-wider text-slate-400 uppercase"
                        >
                            Login sebagai
                        </p>
                        <p class="truncate text-sm font-semibold text-white">
                            {{ role.name }}
                        </p>
                    </div>
                    <span
                        class="text-[10px] text-slate-400"
                        :class="isSidebarCollapsed ? 'lg:hidden' : ''"
                    >
                        {{ modules.length }} modul
                    </span>
                </div>
            </div>

            <nav class="flex-1 space-y-1 overflow-y-auto px-3 py-4">
                <Link
                    v-for="module in modules"
                    :key="module.key"
                    :href="moduleUrls[module.route]()"
                    class="flex w-full items-center gap-3 rounded-xl px-3 py-2.5 text-left transition"
                    :class="[
                        activeModule.key === module.key
                            ? 'bg-gradient-to-r from-cyan-500 to-sky-600 text-white shadow-lg shadow-cyan-500/25'
                            : 'text-slate-400 hover:bg-white/5 hover:text-white',
                        isSidebarCollapsed ? 'lg:justify-center lg:px-0' : '',
                    ]"
                    :title="isSidebarCollapsed ? module.label : undefined"
                    @click="isSidebarOpen = false"
                >
                    <component
                        :is="moduleIcons[module.icon]"
                        class="h-[18px] w-[18px] shrink-0"
                    />
                    <span
                        class="flex-1 leading-tight"
                        :class="isSidebarCollapsed ? 'lg:hidden' : ''"
                    >
                        <span class="block text-sm font-medium">
                            {{ module.label }}
                        </span>
                        <span
                            class="block text-[11px]"
                            :class="
                                activeModule.key === module.key
                                    ? 'text-cyan-50/80'
                                    : 'text-slate-500'
                            "
                        >
                            {{ module.caption }}
                        </span>
                    </span>
                </Link>
            </nav>

            <div class="space-y-3 border-t border-white/5 p-4">
                <div
                    class="flex items-center gap-3 px-1"
                    :class="
                        isSidebarCollapsed ? 'lg:flex-col lg:gap-2 lg:px-0' : ''
                    "
                >
                    <div
                        class="flex h-9 w-9 shrink-0 items-center justify-center rounded-full bg-gradient-to-br from-cyan-400 to-sky-600 text-xs font-semibold text-white"
                    >
                        {{ persona.initials }}
                    </div>
                    <div
                        class="min-w-0 flex-1 leading-tight"
                        :class="isSidebarCollapsed ? 'lg:hidden' : ''"
                    >
                        <p class="truncate text-sm font-medium text-white">
                            {{ persona.name }}
                        </p>
                        <p class="truncate text-[11px] text-slate-500">
                            {{ persona.title }}
                        </p>
                    </div>
                    <button
                        type="button"
                        class="rounded-lg p-1.5 text-slate-500 transition hover:bg-white/5 hover:text-white"
                        aria-label="Keluar"
                        title="Ganti role / keluar"
                        @click="leaveConsole"
                    >
                        <LogOut class="h-4 w-4" />
                    </button>
                </div>
            </div>
        </aside>

        <div
            v-if="isSidebarOpen"
            class="fixed inset-0 z-40 bg-slate-950/60 backdrop-blur-sm lg:hidden"
            @click="isSidebarOpen = false"
        ></div>

        <!-- Main -->
        <div
            class="transition-[padding] duration-300"
            :class="isSidebarCollapsed ? 'lg:pl-20' : 'lg:pl-72'"
        >
            <header
                class="sticky top-0 z-30 border-b border-slate-200 bg-white/85 backdrop-blur-xl"
            >
                <div
                    class="flex items-center gap-3 px-4 py-3.5 sm:px-6 lg:px-8"
                >
                    <button
                        type="button"
                        class="rounded-xl border border-slate-200 p-2 text-slate-600 lg:hidden"
                        aria-label="Buka menu"
                        @click="isSidebarOpen = true"
                    >
                        <Menu class="h-5 w-5" />
                    </button>

                    <button
                        type="button"
                        class="hidden rounded-xl border border-slate-200 p-2 text-slate-600 transition hover:bg-slate-50 lg:block"
                        :aria-label="
                            isSidebarCollapsed
                                ? 'Lebarkan menu samping'
                                : 'Ciutkan menu samping'
                        "
                        :aria-expanded="!isSidebarCollapsed"
                        :title="
                            isSidebarCollapsed
                                ? 'Lebarkan menu'
                                : 'Ciutkan menu'
                        "
                        @click="toggleSidebarCollapse"
                    >
                        <component
                            :is="
                                isSidebarCollapsed
                                    ? PanelLeftOpen
                                    : PanelLeftClose
                            "
                            class="h-5 w-5"
                        />
                    </button>

                    <div class="min-w-0 flex-1">
                        <h1
                            class="truncate text-base font-semibold text-slate-900 sm:text-lg"
                        >
                            {{ activeModule.label }}
                        </h1>
                    </div>

                    <div class="relative">
                        <button
                            type="button"
                            class="relative rounded-xl border border-slate-200 p-2 text-slate-600 transition hover:bg-slate-50"
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
                            class="absolute top-full right-0 z-40 mt-2 w-[21rem] overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-2xl shadow-slate-400/20"
                        >
                            <div
                                class="flex items-center justify-between border-b border-slate-100 px-4 py-3"
                            >
                                <div>
                                    <p
                                        class="text-sm font-semibold text-slate-900"
                                    >
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
                                class="max-h-96 divide-y divide-slate-50 overflow-y-auto"
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
                                            <span
                                                class="flex items-center gap-1.5"
                                            >
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

                    <Link
                        :href="home.url()"
                        class="hidden rounded-xl border border-slate-200 px-3 py-2 text-sm font-medium text-slate-600 transition hover:bg-slate-50 sm:block"
                    >
                        Ganti role
                    </Link>
                </div>
            </header>

            <main class="px-4 py-6 sm:px-6 lg:px-8">
                <slot />
            </main>
        </div>
    </div>
</template>
