<script lang="ts">
let openModalCount = 0;

/** Close requests of the open dialogs, newest last, so Escape only reaches the top one. */
const openDialogClosers: Array<() => void> = [];

function closeTopDialog(event: KeyboardEvent): void {
    if (event.key === 'Escape' && openDialogClosers.length > 0) {
        openDialogClosers[openDialogClosers.length - 1]();
    }
}
</script>

<script setup lang="ts">
import { X } from '@lucide/vue';
import { onBeforeUnmount, onMounted, ref, watch } from 'vue';

/*
 * dismissible must default to true explicitly: Vue casts an omitted boolean
 * prop to false, which silently hid the close button on every dialog.
 */
const props = withDefaults(
    defineProps<{
        open: boolean;
        title?: string;
        caption?: string;
        size?: 'sm' | 'md' | 'lg' | 'xl' | '2xl';
        dismissible?: boolean;
        layer?: 'default' | 'nested' | 'top';
    }>(),
    { dismissible: true },
);

const emit = defineEmits<{
    close: [];
}>();

const widths: Record<string, string> = {
    sm: 'max-w-sm',
    md: 'max-w-lg',
    lg: 'max-w-2xl',
    xl: 'max-w-4xl',
    '2xl': 'max-w-6xl',
};

const layers: Record<NonNullable<typeof props.layer>, string> = {
    default: 'z-50',
    nested: 'z-[60]',
    top: 'z-[70]',
};

/*
 * Laravel's Inertia root does not inject Vue's SSR teleport buffers into the
 * body. Waiting until mount keeps the server HTML and the hydration pass
 * identical; the dialog moves to the body immediately afterwards.
 */
const canTeleport = ref(false);

/** Each mounted dialog owns one lock so stacked modals cannot unlock each other. */
let ownsPageScrollLock = false;

/** A locked dialog (dismissible=false) swallows Escape instead of closing. */
function requestClose(): void {
    if (props.dismissible) {
        emit('close');
    }
}

/*
 * A click only counts when it also started on the backdrop. Dragging a text
 * selection out of an input and releasing over the backdrop fires a click
 * there too, and must not throw the form away.
 */
const pressedBackdrop = ref(false);

function closeFromBackdrop(): void {
    if (pressedBackdrop.value) {
        requestClose();
    }

    pressedBackdrop.value = false;
}

function syncPageScrollLock(locked: boolean): void {
    if (locked && !ownsPageScrollLock) {
        openModalCount += 1;
        ownsPageScrollLock = true;
        openDialogClosers.push(requestClose);

        if (openDialogClosers.length === 1) {
            window.addEventListener('keydown', closeTopDialog);
        }
    } else if (!locked && ownsPageScrollLock) {
        openModalCount = Math.max(openModalCount - 1, 0);
        ownsPageScrollLock = false;
        openDialogClosers.splice(openDialogClosers.indexOf(requestClose), 1);

        if (openDialogClosers.length === 0) {
            window.removeEventListener('keydown', closeTopDialog);
        }
    }

    document.body.style.overflow = openModalCount > 0 ? 'hidden' : '';
}

onMounted(() => {
    canTeleport.value = true;
    watch(() => props.open, syncPageScrollLock, { immediate: true });
});

onBeforeUnmount(() => syncPageScrollLock(false));
</script>

<template>
    <Teleport v-if="canTeleport" to="body">
        <div
            v-if="open"
            class="fixed inset-0 flex items-end justify-center bg-slate-950/50 p-0 backdrop-blur-sm sm:items-center sm:p-4"
            :class="layers[layer ?? 'default']"
            @pointerdown.self="pressedBackdrop = true"
            @click.self="closeFromBackdrop"
        >
            <div
                class="relative flex max-h-[92dvh] w-full flex-col overflow-hidden rounded-t-3xl bg-white shadow-2xl sm:rounded-3xl"
                :class="widths[size ?? 'md']"
            >
                <!-- Untitled dialogs have no header row, so their close button floats. -->
                <button
                    v-if="!title && dismissible"
                    type="button"
                    class="absolute top-3 right-3 z-10 rounded-full p-2 transition"
                    :class="
                        $slots.hero
                            ? 'bg-white/20 text-white hover:bg-white/30'
                            : 'text-slate-400 hover:bg-slate-100 hover:text-slate-600'
                    "
                    aria-label="Tutup"
                    @click="emit('close')"
                >
                    <X class="h-5 w-5" />
                </button>

                <div v-if="$slots.hero" class="shrink-0">
                    <slot name="hero" />
                </div>

                <div
                    v-if="title"
                    class="flex shrink-0 items-start justify-between gap-3 border-b border-slate-100 px-6 py-4"
                >
                    <div class="min-w-0">
                        <p class="text-base font-semibold text-slate-900">
                            {{ title }}
                        </p>
                        <p v-if="caption" class="text-xs text-slate-500">
                            {{ caption }}
                        </p>
                    </div>
                    <button
                        v-if="dismissible"
                        type="button"
                        class="-m-1 rounded-lg p-2 text-slate-400 transition hover:bg-slate-100 hover:text-slate-600"
                        aria-label="Tutup"
                        @click="emit('close')"
                    >
                        <X class="h-5 w-5" />
                    </button>
                </div>

                <div
                    class="min-h-0 flex-1 [scrollbar-gutter:stable] overflow-y-auto p-6"
                >
                    <slot />
                </div>

                <div
                    v-if="$slots.footer"
                    class="flex shrink-0 gap-2 border-t border-slate-100 p-4"
                >
                    <slot name="footer" />
                </div>
            </div>
        </div>
    </Teleport>
</template>
