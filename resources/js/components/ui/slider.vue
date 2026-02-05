<script setup lang="ts">
import { cn } from '@/lib/utils';
import { computed, ref } from 'vue';

interface Props {
    modelValue: number;
    min?: number;
    max?: number;
    step?: number;
    class?: string;
}

const props = withDefaults(defineProps<Props>(), {
    min: 0,
    max: 100,
    step: 1,
});

const emit = defineEmits<{
    (e: 'update:modelValue', value: number): void;
}>();

const percentage = computed(() => {
    return ((props.modelValue - props.min) / (props.max - props.min)) * 100;
});

const handleInput = (event: Event) => {
    const target = event.target as HTMLInputElement;
    emit('update:modelValue', Number(target.value));
};
</script>

<template>
    <div :class="cn('relative flex w-full touch-none select-none items-center', props.class)">
        <div class="relative h-2 w-full grow overflow-hidden rounded-full bg-secondary">
            <div
                class="absolute h-full bg-primary transition-all"
                :style="{ width: `${percentage}%` }"
            />
        </div>
        <input
            type="range"
            :value="modelValue"
            :min="min"
            :max="max"
            :step="step"
            class="absolute inset-0 h-full w-full cursor-pointer opacity-0"
            @input="handleInput"
        />
    </div>
</template>

