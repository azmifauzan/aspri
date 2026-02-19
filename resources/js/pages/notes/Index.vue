<script setup lang="ts">
import { ref } from 'vue';
import { Head, router } from '@inertiajs/vue3';
import AppLayout from '@/layouts/AppLayout.vue';
import NoteCard from '@/components/notes/NoteCard.vue';
import NoteModal from '@/components/notes/NoteModal.vue';
import { Button } from '@/components/ui/button';
import { Plus } from 'lucide-vue-next';
import { index as notesIndex, destroy, update } from '@/routes/notes';
import type { BreadcrumbItem } from '@/types';
import Swal from 'sweetalert2';

const props = defineProps<{
    notes: any[];
}>();

const breadcrumbs: BreadcrumbItem[] = [
    {
        title: 'Notes',
        href: notesIndex().url,
    },
];

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
    Swal.fire({
        title: 'Hapus Catatan?',
        text: `Catatan "${note.title}" akan dihapus permanen.`,
        icon: 'warning',
        showCancelButton: true,
        confirmButtonColor: '#d33',
        cancelButtonColor: '#6b7280',
        confirmButtonText: 'Ya, Hapus!',
        cancelButtonText: 'Batal',
    }).then((result) => {
        if (result.isConfirmed) {
            router.delete(destroy(note.id).url, {
                preserveScroll: true,
                onSuccess: () => {
                    Swal.fire({
                        icon: 'success',
                        title: 'Catatan dihapus',
                        text: 'Catatan berhasil dihapus.',
                        timer: 2000,
                        showConfirmButton: false,
                    });
                },
                onError: () => {
                    Swal.fire({
                        icon: 'error',
                        title: 'Gagal menghapus',
                        text: 'Terjadi kesalahan saat menghapus catatan.',
                    });
                },
            });
        }
    });
};

const togglePin = (note: any) => {
    router.put(update(note.id).url, {
        ...note,
        is_pinned: !note.is_pinned
    }, {
        preserveScroll: true,
        onSuccess: () => {
            Swal.fire({
                icon: 'success',
                title: note.is_pinned ? 'Catatan di-unpin' : 'Catatan di-pin',
                text: note.is_pinned ? 'Catatan berhasil di-unpin.' : 'Catatan berhasil di-pin.',
                timer: 1500,
                showConfirmButton: false,
            });
        },
        onError: () => {
            Swal.fire({
                icon: 'error',
                title: 'Gagal',
                text: 'Terjadi kesalahan saat memperbarui catatan.',
            });
        },
    });
};
</script>

<template>
    <Head title="Notes" />

    <AppLayout :breadcrumbs="breadcrumbs">
        <div class="flex h-full flex-1 flex-col gap-4 p-4">
            <!-- Header -->
            <div class="flex items-center justify-between">
                <h1 class="text-2xl font-bold">Notes</h1>
                <Button @click="openCreateModal">
                    <Plus :size="16" class="mr-2" />
                    New Note
                </Button>
            </div>

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

        <NoteModal
            :show="showModal"
            :note="selectedNote"
            @close="showModal = false"
        />
    </AppLayout>
</template>
