<script setup lang="ts">
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Send } from 'lucide-vue-next';
import { ref, onMounted } from 'vue';

type Props = {
    isLoading: boolean;
};

type Emits = {
    send: [message: string];
};

defineProps<Props>();
const emit = defineEmits<Emits>();

const message = ref('');
const inputRef = ref<HTMLInputElement | null>(null);

const focusInput = () => {
    setTimeout(() => {
        // Access the actual input element from the component
        const inputElement = inputRef.value as any;
        if (inputElement?.$el) {
            inputElement.$el.focus();
        } else if (inputElement?.focus) {
            inputElement.focus();
        }
    }, 100);
};

const handleSubmit = () => {
    if (!message.value.trim()) return;

    emit('send', message.value);
    message.value = '';
    focusInput();
};

const handleKeydown = (e: KeyboardEvent) => {
    if (e.key === 'Enter' && !e.shiftKey) {
        e.preventDefault();
        handleSubmit();
    }
};

onMounted(() => {
    focusInput();
});

defineExpose({
    focusInput,
});
</script>

<template>
    <div class="border-t bg-background p-4">
        <form
            @submit.prevent="handleSubmit"
            class="mx-auto flex max-w-3xl items-center gap-2"
        >
            <Input
                ref="inputRef"
                v-model="message"
                placeholder="Ketik pesan..."
                class="flex-1"
                :disabled="isLoading"
                @keydown="handleKeydown"
            />
            <Button
                type="submit"
                size="icon"
                :disabled="isLoading || !message.trim()"
            >
                <Send class="h-4 w-4" />
            </Button>
        </form>
        <p class="mx-auto mt-2 max-w-3xl text-center text-xs text-muted-foreground">
            Tekan Enter untuk mengirim pesan
        </p>
    </div>
</template>
