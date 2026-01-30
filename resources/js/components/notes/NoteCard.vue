<script setup lang="ts">
import { defineProps, defineEmits } from 'vue';
import { Trash2, Pin, Pencil } from 'lucide-vue-next';

const props = defineProps<{
    note: {
        id: number;
        title: string;
        content: string;
        is_pinned: boolean;
        color: string | null;
        updated_at: string;
    }
}>();

const emit = defineEmits(['edit', 'delete', 'toggle-pin']);

const formatDate = (dateString: string) => {
    return new Date(dateString).toLocaleDateString('id-ID', {
        day: 'numeric',
        month: 'short',
        year: 'numeric'
    });
};
</script>

<template>
    <div 
        class="group relative flex flex-col justify-between rounded-xl border p-4 shadow-sm transition-all hover:shadow-md"
        :class="[
            note.color ? `bg-[${note.color}]` : 'bg-white dark:bg-zinc-900',
            note.is_pinned ? 'border-primary/50 ring-1 ring-primary/20' : 'border-zinc-200 dark:border-zinc-800'
        ]"
    >
        <!-- Content -->
        <div class="cursor-pointer" @click="$emit('edit', note)">
            <div class="mb-2 flex items-start justify-between">
                <h3 class="font-semibold text-zinc-900 dark:text-zinc-100 line-clamp-1">{{ note.title }}</h3>
                <span v-if="note.is_pinned" class="text-primary">
                    <Pin :size="14" class="fill-current" />
                </span>
            </div>
            
            <p class="mb-4 text-sm text-zinc-600 dark:text-zinc-400 line-clamp-3 min-h-[3rem]">
                {{ note.content || 'Start writing...' }}
            </p>
            
            <div class="flex items-center text-xs text-zinc-400">
                {{ formatDate(note.updated_at) }}
            </div>
        </div>

        <!-- Actions (visible on hover) -->
        <div class="absolute bottom-3 right-3 flex items-center gap-2 opacity-0 transition-opacity group-hover:opacity-100">
            <button 
                @click.stop="$emit('toggle-pin', note)"
                class="rounded-full p-1.5 text-zinc-400 hover:bg-zinc-100 dark:hover:bg-zinc-800 hover:text-primary transition-colors"
                :title="note.is_pinned ? 'Unpin' : 'Pin'"
            >
                <Pin :size="14" />
            </button>
            <button 
                @click.stop="$emit('edit', note)"
                class="rounded-full p-1.5 text-zinc-400 hover:bg-zinc-100 dark:hover:bg-zinc-800 hover:text-blue-500 transition-colors"
                title="Edit"
            >
                <Pencil :size="14" />
            </button>
            <button 
                @click.stop="$emit('delete', note)"
                class="rounded-full p-1.5 text-zinc-400 hover:bg-zinc-100 dark:hover:bg-zinc-800 hover:text-red-500 transition-colors"
                title="Delete"
            >
                <Trash2 :size="14" />
            </button>
        </div>
    </div>
</template>
