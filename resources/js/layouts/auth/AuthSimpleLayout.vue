<script setup lang="ts">
import { Link, usePage } from '@inertiajs/vue3';
import { home } from '@/routes';
import type { CarwashBrand } from '@/types/demo';

const page = usePage<{ brand?: CarwashBrand }>();

defineProps<{
    title?: string;
    description?: string;
}>();
</script>

<template>
    <div
        class="flex min-h-svh flex-col items-center justify-center gap-6 bg-background p-6 md:p-10"
    >
        <div class="w-full max-w-sm">
            <div class="flex flex-col gap-8">
                <div class="flex flex-col items-center gap-4">
                    <Link
                        :href="home()"
                        class="flex flex-col items-center gap-2 font-medium"
                    >
                        <div
                            class="mb-1 flex size-14 items-center justify-center overflow-hidden rounded-2xl text-2xl shadow-lg"
                            :class="
                                page.props.brand?.photo
                                    ? 'bg-white shadow-black/10'
                                    : 'bg-gradient-to-br from-cyan-400 to-sky-600 shadow-cyan-500/30'
                            "
                        >
                            <img
                                v-if="page.props.brand?.photo"
                                :src="page.props.brand.photo"
                                :alt="page.props.brand.name"
                                class="h-full w-full object-contain"
                            />
                            <span v-else aria-hidden="true">🚗</span>
                        </div>
                        <span class="sr-only">{{ title }}</span>
                    </Link>
                    <div class="space-y-2 text-center">
                        <h1 class="text-xl font-medium">{{ title }}</h1>
                        <p class="text-center text-sm text-muted-foreground">
                            {{ description }}
                        </p>
                    </div>
                </div>
                <slot />
            </div>
        </div>
    </div>
</template>
