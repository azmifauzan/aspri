<script setup lang="ts">
import { Toaster as Sonner, type ToasterProps } from 'vue-sonner';

const props = withDefaults(defineProps<ToasterProps>(), {
    position: 'top-right',
    richColors: true,
    closeButton: true,
    duration: 5000,
});
</script>

<template>
    <Sonner v-bind="props" />
</template>
