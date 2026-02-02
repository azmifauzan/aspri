<script setup lang="ts">
import { Avatar, AvatarFallback } from '@/components/ui/avatar';
import { ScrollArea } from '@/components/ui/scroll-area';
import { Skeleton } from '@/components/ui/skeleton';
import type { ChatMessage } from '@/types';
import { Bot, User } from 'lucide-vue-next';
import { nextTick, ref, watch } from 'vue';

type Props = {
    messages: ChatMessage[];
    isLoading: boolean;
    userName: string;
};

const props = defineProps<Props>();

const scrollContainer = ref<HTMLElement | null>(null);

const scrollToBottom = () => {
    nextTick(() => {
        if (scrollContainer.value) {
            const scrollArea = scrollContainer.value.querySelector('[data-radix-scroll-area-viewport]');
            if (scrollArea) {
                scrollArea.scrollTop = scrollArea.scrollHeight;
            }
        }
    });
};

watch(
    () => props.messages.length,
    () => {
        scrollToBottom();
    }
);

watch(
    () => props.isLoading,
    () => {
        scrollToBottom();
    }
);

const getInitials = (name: string) => {
    return name
        .split(' ')
        .map((n) => n[0])
        .join('')
        .toUpperCase()
        .slice(0, 2);
};

const formatContent = (content: string) => {
    // Simple markdown-like formatting
    return content
        .replace(/\*\*(.*?)\*\*/g, '<strong>$1</strong>')
        .replace(/\*(.*?)\*/g, '<em>$1</em>')
        .replace(/`(.*?)`/g, '<code class="bg-muted px-1 py-0.5 rounded text-sm">$1</code>')
        .replace(/\n/g, '<br />');
};
</script>

<template>
    <ScrollArea ref="scrollContainer" class="flex-1">
        <div class="mx-auto max-w-3xl space-y-6 p-4">
            <!-- Empty State -->
            <div
                v-if="messages.length === 0 && !isLoading"
                class="flex flex-col items-center justify-center py-20 text-center"
            >
                <div class="mb-4 rounded-full bg-primary/10 p-4">
                    <Bot class="h-8 w-8 text-primary" />
                </div>
                <h3 class="mb-2 text-lg font-semibold">Halo! 👋</h3>
                <p class="max-w-sm text-muted-foreground">
                    Saya asisten pribadi kamu. Tanyakan apa saja tentang jadwal,
                    keuangan, atau hal lainnya!
                </p>
            </div>

            <!-- Messages -->
            <div
                v-for="message in messages"
                :key="message.id"
                class="flex gap-3"
                :class="{
                    'flex-row-reverse': message.role === 'user',
                }"
            >
                <!-- Avatar -->
                <Avatar class="h-8 w-8 shrink-0">
                    <AvatarFallback
                        :class="{
                            'bg-primary text-primary-foreground': message.role === 'user',
                            'bg-muted': message.role === 'assistant',
                        }"
                    >
                        <User v-if="message.role === 'user'" class="h-4 w-4" />
                        <Bot v-else class="h-4 w-4" />
                    </AvatarFallback>
                </Avatar>

                <!-- Message Bubble -->
                <div
                    class="max-w-[80%] rounded-2xl px-4 py-2"
                    :class="{
                        'bg-primary text-primary-foreground': message.role === 'user',
                        'bg-muted': message.role === 'assistant',
                    }"
                >
                    <div
                        class="prose prose-sm dark:prose-invert max-w-none"
                        v-html="formatContent(message.content)"
                    />
                    <div
                        class="mt-1 text-[10px] opacity-60"
                        :class="{
                            'text-right': message.role === 'user',
                        }"
                    >
                        {{ message.createdAt }}
                    </div>
                </div>
            </div>

            <!-- Loading Indicator -->
            <div v-if="isLoading" class="flex gap-3">
                <Avatar class="h-8 w-8 shrink-0">
                    <AvatarFallback class="bg-muted">
                        <Bot class="h-4 w-4" />
                    </AvatarFallback>
                </Avatar>
                <div class="max-w-[80%] space-y-2 rounded-2xl bg-muted px-4 py-3">
                    <Skeleton class="h-4 w-48" />
                    <Skeleton class="h-4 w-32" />
                </div>
            </div>
        </div>
    </ScrollArea>
</template>
