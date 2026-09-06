<script setup lang="ts">
import { reactive, watch } from 'vue';
import {
    normalizePlate,
    plateSegmentLengths,
    splitPlate,
} from '@/lib/vehiclePlate';
import type { PlateSegments } from '@/lib/vehiclePlate';

type SegmentKey = keyof PlateSegments;

const props = withDefaults(
    defineProps<{
        id: string;
        invalid?: boolean;
        disabled?: boolean;
    }>(),
    { invalid: false, disabled: false },
);

const plate = defineModel<string>({ required: true });

const order: SegmentKey[] = ['prefix', 'digits', 'suffix'];

const allowed: Record<SegmentKey, RegExp> = {
    prefix: /[A-Z]/,
    digits: /[0-9]/,
    suffix: /[A-Z]/,
};

const columns: {
    key: SegmentKey;
    placeholder: string;
    label: string;
    inputmode: 'text' | 'numeric';
}[] = [
    {
        key: 'prefix',
        placeholder: 'B',
        label: 'Kode wilayah',
        inputmode: 'text',
    },
    {
        key: 'digits',
        placeholder: '1234',
        label: 'Nomor',
        inputmode: 'numeric',
    },
    {
        key: 'suffix',
        placeholder: 'CDE',
        label: 'Huruf belakang',
        inputmode: 'text',
    },
];

const segments = reactive<PlateSegments>(splitPlate(plate.value));
const fields: Record<SegmentKey, HTMLInputElement | null> = {
    prefix: null,
    digits: null,
    suffix: null,
};

const joined = (): string =>
    `${segments.prefix}${segments.digits}${segments.suffix}`;

const neighbour = (key: SegmentKey, step: -1 | 1): SegmentKey | null =>
    order[order.indexOf(key) + step] ?? null;

/** Move the caret into another column, landing where typing continues. */
const focusSegment = (key: SegmentKey, caret: 'start' | 'end'): void => {
    const field = fields[key];

    if (field === null) {
        return;
    }

    field.value = segments[key];
    field.focus();
    const position = caret === 'start' ? 0 : segments[key].length;
    field.setSelectionRange(position, position);
};

const publish = (): void => {
    plate.value = joined();
};

/**
 * Keep a column to the characters it accepts and hand the rest to the next one,
 * so a plate typed straight through — "b8120ds" — lands in all three columns
 * without the cashier ever reaching for Tab.
 */
const handleInput = (key: SegmentKey, event: Event): void => {
    const field = event.target as HTMLInputElement;
    const typed = normalizePlate(field.value);
    const kept = [...typed]
        .filter((character) => allowed[key].test(character))
        .join('')
        .slice(0, plateSegmentLengths[key]);
    const spill = [...typed]
        .filter((character) => !allowed[key].test(character))
        .join('');

    segments[key] = kept;
    field.value = kept;

    const next = neighbour(key, 1);
    publish();

    if (next === null) {
        return;
    }

    const carried = [...spill]
        .filter((character) => allowed[next].test(character))
        .join('');

    if (carried !== '') {
        segments[next] = `${carried}${segments[next]}`.slice(
            0,
            plateSegmentLengths[next],
        );
        publish();
        focusSegment(next, 'end');

        return;
    }

    if (kept.length === plateSegmentLengths[key]) {
        focusSegment(next, 'end');
    }
};

/** Backspace at the head of a column keeps eating into the previous one. */
const handleBackspace = (key: SegmentKey, event: KeyboardEvent): void => {
    const field = event.target as HTMLInputElement;

    if (field.selectionStart !== 0 || field.selectionEnd !== 0) {
        return;
    }

    const previous = neighbour(key, -1);

    if (previous === null) {
        return;
    }

    event.preventDefault();
    segments[previous] = segments[previous].slice(0, -1);
    publish();
    focusSegment(previous, 'end');
};

const handleArrow = (
    key: SegmentKey,
    event: KeyboardEvent,
    step: -1 | 1,
): void => {
    const field = event.target as HTMLInputElement;
    const edge = step === -1 ? 0 : segments[key].length;

    if (field.selectionStart !== edge || field.selectionEnd !== edge) {
        return;
    }

    const target = neighbour(key, step);

    if (target === null) {
        return;
    }

    event.preventDefault();
    focusSegment(target, step === -1 ? 'end' : 'start');
};

/** A pasted plate fills every column at once, however it was written. */
const handlePaste = (event: ClipboardEvent): void => {
    const pasted = event.clipboardData?.getData('text') ?? '';

    if (pasted === '') {
        return;
    }

    event.preventDefault();
    Object.assign(segments, splitPlate(pasted));
    publish();

    const landing =
        order.find((key) => segments[key].length < plateSegmentLengths[key]) ??
        'suffix';
    focusSegment(landing, 'end');
};

watch(plate, (value) => {
    if (normalizePlate(value ?? '') === joined()) {
        return;
    }

    Object.assign(segments, splitPlate(value));
});
</script>

<template>
    <div
        class="grid grid-cols-[2fr_auto_4fr_auto_3fr] items-center gap-1.5 sm:gap-2"
    >
        <template v-for="(column, index) in columns" :key="column.key">
            <span v-if="index > 0" class="text-sm text-slate-300">-</span>
            <input
                :id="index === 0 ? props.id : `${props.id}-${column.key}`"
                :ref="
                    (element) => {
                        fields[column.key] = element as HTMLInputElement | null;
                    }
                "
                :value="segments[column.key]"
                :aria-label="column.label"
                :placeholder="column.placeholder"
                :maxlength="plateSegmentLengths[column.key]"
                :inputmode="column.inputmode"
                :disabled="props.disabled"
                type="text"
                autocomplete="off"
                autocapitalize="characters"
                spellcheck="false"
                class="w-full min-w-0 rounded-xl border bg-white px-1.5 py-2.5 text-center text-sm font-medium tracking-wide text-slate-900 uppercase placeholder:font-normal placeholder:tracking-normal placeholder:text-slate-400 focus:border-cyan-400 focus:outline-none disabled:cursor-not-allowed disabled:opacity-60"
                :class="props.invalid ? 'border-rose-300' : 'border-slate-200'"
                @input="handleInput(column.key, $event)"
                @keydown.backspace="handleBackspace(column.key, $event)"
                @keydown.left="handleArrow(column.key, $event, -1)"
                @keydown.right="handleArrow(column.key, $event, 1)"
                @paste="handlePaste"
                @focus="($event.target as HTMLInputElement).select()"
            />
        </template>
    </div>
</template>
