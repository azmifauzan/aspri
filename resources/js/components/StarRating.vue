<script setup lang="ts">
import { Star } from 'lucide-vue-next';
import { computed } from 'vue';

interface Props {
    rating: number;
    maxStars?: number;
    size?: number;
    showValue?: boolean;
    interactive?: boolean;
}

const props = withDefaults(defineProps<Props>(), {
    maxStars: 5,
    size: 16,
    showValue: false,
    interactive: false,
});

const emit = defineEmits<{
    (e: 'update:rating', value: number): void;
}>();

const stars = computed(() => {
    const result = [];
    for (let i = 1; i <= props.maxStars; i++) {
        const filled = i <= Math.round(props.rating);
        result.push({
            id: i,
            filled,
            partial: !filled && i === Math.ceil(props.rating) && props.rating % 1 !== 0,
        });
    }
    return result;
});

const handleClick = (rating: number) => {
    if (props.interactive) {
        emit('update:rating', rating);
    }
};
</script>

<template>
    <div class="flex items-center gap-0.5">
        <button
            v-for="star in stars"
            :key="star.id"
            type="button"
            :disabled="!interactive"
            :class="[
                'transition-all duration-150 rounded',
                interactive ? 'cursor-pointer hover:scale-125 active:scale-110' : 'cursor-default',
            ]"
            @click="handleClick(star.id)"
        >
            <Star
                :size="size"
                :class="[
                    'transition-all',
                    star.filled
                        ? 'fill-yellow-400 text-yellow-400'
                        : interactive
                        ? 'fill-transparent text-gray-300 hover:text-yellow-300'
                        : 'fill-transparent text-muted-foreground',
                ]"
            />
        </button>
        <span v-if="showValue" class="ml-2 text-sm font-medium text-muted-foreground">
            {{ rating.toFixed(1) }}
        </span>
    </div>
</template>
