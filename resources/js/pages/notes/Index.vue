<script setup lang="ts">
import { ref } from 'vue';
import { Head, router } from '@inertiajs/vue3';
import AppLayout from '@/layouts/AppLayout.vue';
import NoteCard from '@/components/notes/NoteCard.vue';
import NoteModal from '@/components/notes/NoteModal.vue';
import { Button } from '@/components/ui/button';
import { Plus } from 'lucide-vue-next';

const props = defineProps<{
    notes: any[];
}>();

const showModal = ref(false);
const selectedNote = ref<any>(null);

const openCreateModal = () => {
    selectedNote.value = null;
    showModal.value = true;
};

const openEditModal = (note: any) => {
    selectedNote.value = note;
    showModal.value = true;
};

const deleteNote = (note: any) => {
    if (confirm('Are you sure you want to delete this note?')) {
        router.delete(route('notes.destroy', note.id));
    }
};

const togglePin = (note: any) => {
    router.put(route('notes.update', note.id), {
        ...note,
        is_pinned: !note.is_pinned
    }, {
        preserveScroll: true
    });
};
</script>

<template>
    <Head title="Notes" />

    <AppLayout>
        <template #header>
            <div class="flex items-center justify-between">
                <h2 class="text-xl font-semibold leading-tight text-gray-800 dark:text-gray-200">
                    My Notes
                </h2>
                <Button @click="openCreateModal">
                    <Plus :size="16" class="mr-2" />
                    New Note
                </Button>
            </div>
        </template>

        <div class="py-12">
            <div class="mx-auto max-w-7xl sm:px-6 lg:px-8">
                <div v-if="notes.length === 0" class="flex flex-col items-center justify-center py-20 text-center">
                    <div class="rounded-full bg-gray-100 p-4 dark:bg-gray-800">
                        <Plus :size="32" class="text-gray-400" />
                    </div>
                    <h3 class="mt-4 text-lg font-medium text-gray-900 dark:text-gray-100">No notes yet</h3>
                    <p class="mt-1 text-gray-500 dark:text-gray-400">Get started by creating your first note.</p>
                </div>

                <div v-else class="grid grid-cols-1 gap-4 sm:grid-cols-2 lg:grid-cols-3 xl:grid-cols-4">
                    <NoteCard
                        v-for="note in notes"
                        :key="note.id"
                        :note="note"
                        @edit="openEditModal"
                        @delete="deleteNote"
                        @toggle-pin="togglePin"
                    />
                </div>
            </div>
        </div>

        <NoteModal
            :show="showModal"
            :note="selectedNote"
            @close="showModal = false"
        />
    </AppLayout>
</template>
