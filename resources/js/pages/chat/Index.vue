<script setup lang="ts">
import ChatController from '@/actions/App/Http/Controllers/ChatController';
import { ChatInput, ChatMessageList, ChatSidebar } from '@/components/chat';
import { Button } from '@/components/ui/button';
import AppLayout from '@/layouts/AppLayout.vue';
import { index as chatIndex } from '@/routes/chat';
import type { BreadcrumbItem, ChatMessage, ChatPageProps, ChatThread } from '@/types';
import { Head, router, usePage } from '@inertiajs/vue3';
import { ArrowLeft } from 'lucide-vue-next';
import { ref, watch, nextTick, computed } from 'vue';
import { useI18n } from 'vue-i18n';

const { t } = useI18n();

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
const chatInputRef = ref<InstanceType<typeof ChatInput> | null>(null);
const chatMessageListRef = ref<InstanceType<typeof ChatMessageList> | null>(null);

// Mobile layout state: true = show sidebar, false = show chat
const showMobileSidebar = ref(props.currentThread === null);

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
    
    // Scroll after adding user message
    await nextTick();
    chatMessageListRef.value?.scrollToBottom();

    isLoading.value = true;

    // Create placeholder for streaming assistant message
    const streamingMessageId = `streaming-${Date.now()}`;
    let streamingMessage: ChatMessage = {
        id: streamingMessageId,
        role: 'assistant',
        content: '',
        createdAt: new Date().toLocaleTimeString('id-ID', { hour: '2-digit', minute: '2-digit' }),
    };

    try {
        // Get CSRF token
        const csrfToken = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') ?? '';
        
        // Create fetch request with streaming
        const response = await fetch('/chat/message/stream', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'X-CSRF-TOKEN': csrfToken,
                'Accept': 'text/event-stream',
            },
            body: JSON.stringify({
                message: content,
                thread_id: currentThreadId.value,
            }),
        });

        if (!response.ok) {
            const contentType = response.headers.get('Content-Type') ?? '';
            if (contentType.includes('application/json')) {
                const errorData = await response.json();
                throw new Error(errorData.error || 'Failed to connect to chat stream');
            } else {
                throw new Error(`Server error: ${response.status} ${response.statusText}`);
            }
        }

        const reader = response.body?.getReader();
        const decoder = new TextDecoder();
        let buffer = '';
        let messageStarted = false;
        let currentEvent = '';

        while (reader) {
            const { done, value } = await reader.read();
            
            if (done) break;

            // Decode chunk and add to buffer
            buffer += decoder.decode(value, { stream: true });

            // Process complete SSE messages (separated by double newline)
            let doubleNewlineIndex;
            while ((doubleNewlineIndex = buffer.indexOf('\n\n')) !== -1) {
                const message = buffer.substring(0, doubleNewlineIndex);
                buffer = buffer.substring(doubleNewlineIndex + 2);

                // Parse SSE message
                const lines = message.split('\n');
                let eventType = '';
                let eventData = '';

                for (const line of lines) {
                    if (line.startsWith('event: ')) {
                        eventType = line.substring(7).trim();
                    } else if (line.startsWith('data: ')) {
                        eventData = line.substring(6);
                    }
                }

                if (!eventData) continue;

                try {
                    const data = JSON.parse(eventData);

                    if (eventType === 'thread') {
                        // Update thread id if this was a new conversation
                        if (!currentThreadId.value && data.id) {
                            currentThreadId.value = data.id;
                            
                            // Add new thread to sidebar
                            threads.value.unshift({
                                id: data.id,
                                title: data.title,
                                lastMessageAt: t('common.justNow'),
                            });

                            // Update URL without full page reload
                            window.history.replaceState({}, '', `/chat/${data.id}`);
                        }
                    } else if (eventType === 'user_message') {
                        // Replace temp message with real one
                        const userIdx = messages.value.findIndex((m) => m.id === tempUserMessage.id);
                        if (userIdx !== -1) {
                            messages.value[userIdx] = data;
                        }
                    } else if (eventType === 'message_chunk') {
                        if (!messageStarted) {
                            // Add placeholder message on first chunk
                            messages.value.push(streamingMessage);
                            messageStarted = true;
                        }

                        // Append chunk to streaming message
                        const msgIdx = messages.value.findIndex((m) => m.id === streamingMessageId);
                        if (msgIdx !== -1) {
                            messages.value[msgIdx].content += data.content;
                            
                            // Auto-scroll as content streams in
                            await nextTick();
                            chatMessageListRef.value?.scrollToBottom();
                        }
                    } else if (eventType === 'complete') {
                        // Update streaming message with final ID and timestamp
                        const msgIdx = messages.value.findIndex((m) => m.id === streamingMessageId);
                        if (msgIdx !== -1) {
                            messages.value[msgIdx].id = data.message_id;
                            messages.value[msgIdx].createdAt = data.createdAt;
                        }

                        // Update chat limit info if provided
                        if (data.chatLimit) {
                            console.log('Chat limit updated:', data.chatLimit);
                        }

                        isLoading.value = false;

                        // Focus input after successful message
                        chatInputRef.value?.focusInput();

                        // Final scroll
                        await nextTick();
                        setTimeout(() => chatMessageListRef.value?.scrollToBottom(), 100);
                    } else if (eventType === 'error') {
                        throw new Error(data.message || t('chat.errorMessage'));
                    }
                } catch (parseError) {
                    console.error('Failed to parse SSE data:', parseError, eventData);
                }
            }
        }

    } catch (error: any) {
        console.error('Failed to send message:', error);
        
        // Remove optimistic and streaming messages on error
        messages.value = messages.value.filter((m) => 
            m.id !== tempUserMessage.id && m.id !== streamingMessageId
        );
        
        // Add error message
        messages.value.push({
            id: `error-${Date.now()}`,
            role: 'assistant',
            content: error.message || t('chat.errorMessage'),
            createdAt: new Date().toLocaleTimeString('id-ID', { hour: '2-digit', minute: '2-digit' }),
        });
        
        // Scroll to error message
        await nextTick();
        chatMessageListRef.value?.scrollToBottom();

        isLoading.value = false;

        // Focus input after error
        chatInputRef.value?.focusInput();
    }
};

const selectThread = (threadId: string) => {
    showMobileSidebar.value = false;
    router.visit(`/chat/${threadId}`, {
        preserveState: true,
        preserveScroll: true,
    });
};

const startNewChat = () => {
    currentThreadId.value = null;
    messages.value = [];
    showMobileSidebar.value = false;
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
    <Head :title="$t('chat.title')" />

    <AppLayout :breadcrumbs="breadcrumbs">
        <div class="flex h-[calc(100vh-8rem)] overflow-hidden rounded-lg border bg-background">
            <!-- Sidebar: full-screen on mobile, fixed w-64 on desktop -->
            <div :class="showMobileSidebar ? 'flex w-full' : 'hidden md:flex md:w-64'">
                <ChatSidebar
                    :threads="threads"
                    :current-thread-id="currentThreadId"
                    @select="selectThread"
                    @new-chat="startNewChat"
                    @delete="deleteThread"
                />
            </div>

            <!-- Main Chat Area: full-screen on mobile when active, flex-1 on desktop -->
            <div :class="showMobileSidebar ? 'hidden md:flex md:flex-1 md:flex-col' : 'flex w-full flex-col md:flex-1'" class="overflow-hidden">
                <!-- Mobile header with back button -->
                <div class="flex shrink-0 items-center gap-2 border-b p-2 md:hidden">
                    <Button variant="ghost" size="icon" @click="showMobileSidebar = true">
                        <ArrowLeft class="h-4 w-4" />
                    </Button>
                    <span class="truncate text-sm font-medium">
                        {{ currentThreadId ? (threads.find((t) => t.id === currentThreadId)?.title ?? $t('chat.title')) : $t('chat.newChat') }}
                    </span>
                </div>

                <!-- Messages -->
                <div class="flex-1 overflow-hidden">
                    <ChatMessageList
                        ref="chatMessageListRef"
                        :messages="messages"
                        :is-loading="isLoading"
                        :user-name="user.name"
                    />
                </div>

                <!-- Input -->
                <div class="shrink-0">
                    <ChatInput
                        ref="chatInputRef"
                        :is-loading="isLoading"
                        @send="sendMessage"
                    />
                </div>
            </div>
        </div>
    </AppLayout>
</template>
