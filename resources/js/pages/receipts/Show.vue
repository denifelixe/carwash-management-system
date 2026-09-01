<script setup lang="ts">
import { onMounted } from 'vue';
import { mountPosReceiptDocument } from '@/lib/posReceipt';
import type { PosReceipt } from '@/lib/posReceipt';
import type { CarwashBrand } from '@/types/demo';

const props = defineProps<{
    brand: CarwashBrand;
    receipt: PosReceipt;
}>();

onMounted(() => {
    mountPosReceiptDocument(window, props.receipt, props.brand);
});
</script>

<template>
    <main></main>
</template>
