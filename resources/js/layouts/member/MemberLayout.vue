<script setup lang="ts">
import { Form, Link, usePage } from '@inertiajs/vue3';
import { LogOut } from '@lucide/vue';
import { computed } from 'vue';
import AppLogoIcon from '@/components/AppLogoIcon.vue';
import { Button } from '@/components/ui/button';
import { dashboard, logout } from '@/routes/member';
import type { Auth } from '@/types';

const page = usePage<{ auth: Auth }>();
const member = computed(() => page.props.auth.member);
</script>

<template>
    <div class="min-h-svh bg-background text-foreground">
        <header class="border-b bg-background">
            <div
                class="mx-auto flex h-16 w-full max-w-6xl items-center justify-between gap-4 px-4 sm:px-6"
            >
                <Link :href="dashboard()" class="flex items-center gap-3">
                    <AppLogoIcon class="size-8 fill-current" />
                    <span class="text-sm font-semibold">ZenWash Member</span>
                </Link>

                <div class="flex min-w-0 items-center gap-3">
                    <div v-if="member" class="min-w-0 text-right">
                        <p class="truncate text-sm font-medium">
                            {{ member.name }}
                        </p>
                        <p class="truncate text-xs text-muted-foreground">
                            {{ member.email }}
                        </p>
                    </div>

                    <Form v-bind="logout.form()">
                        <Button
                            type="submit"
                            variant="ghost"
                            size="icon"
                            title="Log out"
                            aria-label="Log out"
                        >
                            <LogOut class="size-4" />
                        </Button>
                    </Form>
                </div>
            </div>
        </header>

        <main class="mx-auto w-full max-w-6xl px-4 py-8 sm:px-6">
            <slot />
        </main>
    </div>
</template>
