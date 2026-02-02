<script setup lang="ts">
import ChatController from '@/actions/App/Http/Controllers/ChatController';
import { ChatInput, ChatMessageList, ChatSidebar } from '@/components/chat';
import AppLayout from '@/layouts/AppLayout.vue';
import { index as chatIndex } from '@/routes/chat';
import type { BreadcrumbItem, ChatMessage, ChatPageProps, ChatThread } from '@/types';
import { Head, router, usePage } from '@inertiajs/vue3';
import { ref, watch, nextTick, computed } from 'vue';

const props = defineProps<ChatPageProps>();

const page = usePage();
const user = computed(() => page.props.auth.user);

const breadcrumbs: BreadcrumbItem[] = [
    {
        title: 'Chat',
        href: chatIndex().url,
    },
];

// Local state
const messages = ref<ChatMessage[]>([...props.messages]);
const isLoading = ref(false);
const currentThreadId = ref<string | null>(props.currentThread?.id ?? null);
const threads = ref<ChatThread[]>([...props.threads]);

// Watch for prop changes (when navigating between threads)
watch(
    () => props.messages,
    (newMessages) => {
        messages.value = [...newMessages];
    }
);

watch(
    () => props.currentThread,
    (newThread) => {
        currentThreadId.value = newThread?.id ?? null;
    }
);

watch(
    () => props.threads,
    (newThreads) => {
        threads.value = [...newThreads];
    }
);

const sendMessage = async (content: string) => {
    if (!content.trim() || isLoading.value) return;

    // Optimistically add user message
    const tempUserMessage: ChatMessage = {
        id: `temp-${Date.now()}`,
        role: 'user',
        content: content,
        createdAt: new Date().toLocaleTimeString('id-ID', { hour: '2-digit', minute: '2-digit' }),
    };
    messages.value.push(tempUserMessage);

    isLoading.value = true;

    try {
        const response = await fetch(ChatController.sendMessage.url(), {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') ?? '',
                'Accept': 'application/json',
            },
            body: JSON.stringify({
                message: content,
                thread_id: currentThreadId.value,
            }),
        });

        const data = await response.json();

        if (!response.ok) {
            throw new Error(data.error || 'Terjadi kesalahan');
        }

        // Update with real messages
        const userIdx = messages.value.findIndex((m) => m.id === tempUserMessage.id);
        if (userIdx !== -1) {
            messages.value[userIdx] = data.userMessage;
        }

        // Add assistant response
        messages.value.push(data.assistantMessage);

        // Update thread id if this was a new conversation
        if (!currentThreadId.value && data.thread) {
            currentThreadId.value = data.thread.id;
            
            // Add new thread to sidebar
            threads.value.unshift({
                id: data.thread.id,
                title: data.thread.title,
                lastMessageAt: 'Baru saja',
            });

            // Update URL without full page reload
            window.history.replaceState({}, '', `/chat/${data.thread.id}`);
        }
    } catch (error) {
        console.error('Failed to send message:', error);
        // Remove optimistic message on error
        messages.value = messages.value.filter((m) => m.id !== tempUserMessage.id);
        
        // Add error message
        messages.value.push({
            id: `error-${Date.now()}`,
            role: 'assistant',
            content: 'Maaf, terjadi kesalahan. Silakan coba lagi.',
            createdAt: new Date().toLocaleTimeString('id-ID', { hour: '2-digit', minute: '2-digit' }),
        });
    } finally {
        isLoading.value = false;
    }
};

const selectThread = (threadId: string) => {
    router.visit(`/chat/${threadId}`, {
        preserveState: true,
        preserveScroll: true,
    });
};

const startNewChat = () => {
    currentThreadId.value = null;
    messages.value = [];
    window.history.replaceState({}, '', '/chat');
};

const deleteThread = async (threadId: string) => {
    try {
        await fetch(ChatController.destroy.url(threadId), {
            method: 'DELETE',
            headers: {
                'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') ?? '',
                'Accept': 'application/json',
            },
        });

        threads.value = threads.value.filter((t) => t.id !== threadId);
        
        if (currentThreadId.value === threadId) {
            startNewChat();
        }
    } catch (error) {
        console.error('Failed to delete thread:', error);
    }
};
</script>

<template>
    <Head title="Chat" />

    <AppLayout :breadcrumbs="breadcrumbs">
        <div class="flex h-[calc(100vh-4rem)] overflow-hidden">
            <!-- Sidebar -->
            <ChatSidebar
                :threads="threads"
                :current-thread-id="currentThreadId"
                @select="selectThread"
                @new-chat="startNewChat"
                @delete="deleteThread"
            />

            <!-- Main Chat Area -->
            <div class="flex flex-1 flex-col">
                <!-- Messages -->
                <ChatMessageList
                    :messages="messages"
                    :is-loading="isLoading"
                    :user-name="user.name"
                />

                <!-- Input -->
                <ChatInput
                    :is-loading="isLoading"
                    @send="sendMessage"
                />
            </div>
        </div>
    </AppLayout>
</template>
