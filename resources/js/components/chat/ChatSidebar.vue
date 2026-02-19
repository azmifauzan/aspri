<script setup lang="ts">
import { Button } from '@/components/ui/button';
import { ScrollArea } from '@/components/ui/scroll-area';
import type { ChatThread } from '@/types';
import { MessageSquarePlus, Trash2 } from 'lucide-vue-next';

type Props = {
    threads: ChatThread[];
    currentThreadId: string | null;
};

type Emits = {
    select: [threadId: string];
    newChat: [];
    delete: [threadId: string];
};

defineProps<Props>();
const emit = defineEmits<Emits>();
</script>

<template>
    <div class="flex w-full flex-col border-r bg-muted/30">
        <!-- Header -->
        <div class="flex items-center justify-between border-b p-4">
            <h2 class="text-sm font-semibold">Percakapan</h2>
            <Button
                variant="ghost"
                size="icon"
                @click="emit('newChat')"
                title="Chat baru"
            >
                <MessageSquarePlus class="h-4 w-4" />
            </Button>
        </div>

        <!-- Thread List -->
        <ScrollArea class="flex-1">
            <div class="p-2">
                <div
                    v-if="threads.length === 0"
                    class="px-3 py-8 text-center text-sm text-muted-foreground"
                >
                    Belum ada percakapan.<br />
                    Mulai chat baru!
                </div>

                <div
                    v-for="thread in threads"
                    :key="thread.id"
                    class="group relative"
                >
                    <button
                        class="w-full rounded-lg px-3 py-2 text-left text-sm transition-colors hover:bg-muted"
                        :class="{
                            'bg-muted': currentThreadId === thread.id,
                        }"
                        @click="emit('select', thread.id)"
                    >
                        <div class="truncate font-medium">{{ thread.title }}</div>
                        <div class="text-xs text-muted-foreground">
                            {{ thread.lastMessageAt ?? 'Baru' }}
                        </div>
                    </button>

                    <Button
                        variant="ghost"
                        size="icon"
                        class="absolute right-1 top-1/2 h-6 w-6 -translate-y-1/2 opacity-0 transition-opacity group-hover:opacity-100"
                        @click.stop="emit('delete', thread.id)"
                        title="Hapus"
                    >
                        <Trash2 class="h-3 w-3 text-destructive" />
                    </Button>
                </div>
            </div>
        </ScrollArea>
    </div>
</template>
