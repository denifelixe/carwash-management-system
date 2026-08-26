<script setup lang="ts">
import { computed } from 'vue';
import { Avatar, AvatarFallback, AvatarImage } from '@/components/ui/avatar';
import { useInitials } from '@/composables/useInitials';
import type { Admin } from '@/types';

type Props = {
    admin: Admin;
    showEmail?: boolean;
};

const props = withDefaults(defineProps<Props>(), {
    showEmail: false,
});

const { getInitials } = useInitials();

// Compute whether we should show the avatar image
const showAvatar = computed(
    () => props.admin.avatar && props.admin.avatar !== '',
);
</script>

<template>
    <Avatar class="h-8 w-8 overflow-hidden rounded-lg">
        <AvatarImage v-if="showAvatar" :src="admin.avatar!" :alt="admin.name" />
        <AvatarFallback class="rounded-lg text-black dark:text-white">
            {{ getInitials(admin.name) }}
        </AvatarFallback>
    </Avatar>

    <div class="grid flex-1 text-left text-sm leading-tight">
        <span class="truncate font-medium">{{ admin.name }}</span>
        <span v-if="showEmail" class="truncate text-xs text-muted-foreground">{{
            admin.email
        }}</span>
    </div>
</template>
