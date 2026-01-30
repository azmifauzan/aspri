<script setup lang="ts">
import { ref, watch } from 'vue';
import { useForm } from '@inertiajs/vue3';
import Modal from '@/Components/Modal.vue';
import InputLabel from '@/Components/InputLabel.vue';
import TextInput from '@/Components/TextInput.vue';
import PrimaryButton from '@/Components/PrimaryButton.vue';
import SecondaryButton from '@/Components/SecondaryButton.vue';
import InputError from '@/Components/InputError.vue';
import { Pin } from 'lucide-vue-next';

const props = defineProps<{
    show: boolean;
    note?: any;
}>();

const emit = defineEmits(['close']);

const form = useForm({
    title: '',
    content: '',
    is_pinned: false,
    color: null as string | null,
});

watch(() => props.note, (newNote) => {
    if (newNote) {
        form.title = newNote.title;
        form.content = newNote.content;
        form.is_pinned = newNote.is_pinned;
        form.color = newNote.color;
    } else {
        form.reset();
    }
}, { immediate: true });

const submit = () => {
    if (props.note) {
        form.put(route('notes.update', props.note.id), {
            onSuccess: () => {
                form.reset();
                emit('close');
            },
        });
    } else {
        form.post(route('notes.store'), {
            onSuccess: () => {
                form.reset();
                emit('close');
            },
        });
    }
};

const close = () => {
    form.reset();
    emit('close');
};

const colors = [
    null, // Default
    '#fecaca', // Red
    '#fde68a', // Amber
    '#bbf7d0', // Green
    '#bfdbfe', // Blue
    '#ddd6fe', // Violet
    '#fbcfe8', // Pink
];
</script>

<template>
    <Modal :show="show" @close="close">
        <div class="p-6">
            <h2 class="text-lg font-medium text-gray-900 dark:text-gray-100">
                {{ note ? 'Edit Note' : 'Create Note' }}
            </h2>

            <div class="mt-6 space-y-4">
                <!-- Title -->
                <div>
                    <InputLabel for="title" value="Title" />
                    <TextInput
                        id="title"
                        v-model="form.title"
                        type="text"
                        class="mt-1 block w-full"
                        placeholder="Note title"
                    />
                    <InputError :message="form.errors.title" class="mt-2" />
                </div>

                <!-- Content -->
                <div>
                    <InputLabel for="content" value="Content" />
                    <textarea
                        id="content"
                        v-model="form.content"
                        class="mt-1 block w-full rounded-md border-gray-300 dark:border-gray-700 dark:bg-gray-900 dark:text-gray-300 focus:border-indigo-500 focus:ring-indigo-500 shadow-sm"
                        rows="6"
                        placeholder="Write your note here..."
                    ></textarea>
                    <InputError :message="form.errors.content" class="mt-2" />
                </div>

                <!-- Options Row -->
                <div class="flex items-center justify-between">
                    <!-- Pin Toggle -->
                    <button
                        type="button"
                        @click="form.is_pinned = !form.is_pinned"
                        class="flex items-center gap-2 rounded-md px-3 py-2 text-sm font-medium transition-colors"
                        :class="form.is_pinned ? 'bg-primary/10 text-primary' : 'text-gray-500 hover:bg-gray-100 dark:hover:bg-gray-800'"
                    >
                        <Pin :size="16" :class="{'fill-current': form.is_pinned}" />
                        {{ form.is_pinned ? 'Pinned' : 'Pin Note' }}
                    </button>

                    <!-- Color Picker (Simple) -->
                    <!-- <div class="flex gap-1">
                         You can implement color picker here if needed
                    </div> -->
                </div>
            </div>

            <div class="mt-6 flex justify-end gap-3">
                <SecondaryButton @click="close"> Cancel </SecondaryButton>
                <PrimaryButton @click="submit" :disabled="form.processing"> Save </PrimaryButton>
            </div>
        </div>
    </Modal>
</template>
