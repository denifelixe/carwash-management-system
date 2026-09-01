<script setup lang="ts">
import { computed } from 'vue';
import { formatNumber } from '@/composables/useCarwashFormat';

defineOptions({ inheritAttrs: false });

const props = defineProps<{
    maxValue?: number;
}>();

const modelValue = defineModel<number>({ required: true });

const formattedValue = computed<string>(() => {
    const amount = Math.trunc(modelValue.value);

    return amount > 0 ? formatNumber(amount) : '';
});

function caretPositionAfterDigit(
    formattedAmount: string,
    digitPosition: number,
): number {
    if (digitPosition === 0) {
        return 0;
    }

    let digitsSeen = 0;

    for (let index = 0; index < formattedAmount.length; index += 1) {
        if (/\d/.test(formattedAmount[index] ?? '')) {
            digitsSeen += 1;
        }

        if (digitsSeen === digitPosition) {
            return index + 1;
        }
    }

    return formattedAmount.length;
}

function updateAmount(event: Event): void {
    const input = event.target as HTMLInputElement;
    const caretPosition = input.selectionStart ?? input.value.length;
    const digitsBeforeCaret = input.value
        .slice(0, caretPosition)
        .replace(/\D/g, '').length;
    const digits = input.value.replace(/\D/g, '');
    const parsedAmount = digits === '' ? 0 : Number.parseInt(digits, 10);
    let amount = Number.isSafeInteger(parsedAmount) ? parsedAmount : 0;

    if (props.maxValue !== undefined) {
        amount = Math.min(amount, props.maxValue);
    }

    modelValue.value = amount;
    input.value = amount > 0 ? formatNumber(amount) : '';

    const nextCaretPosition = caretPositionAfterDigit(
        input.value,
        digitsBeforeCaret,
    );
    input.setSelectionRange(nextCaretPosition, nextCaretPosition);
}
</script>

<template>
    <input
        v-bind="$attrs"
        :value="formattedValue"
        type="text"
        inputmode="numeric"
        autocomplete="off"
        data-money-input
        class="tabular-nums"
        @input="updateAmount"
    />
</template>
