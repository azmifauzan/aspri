<script setup lang="ts">
import { watch } from 'vue';
import { useForm } from '@inertiajs/vue3';
import {
    Dialog,
    DialogContent,
    DialogHeader,
    DialogTitle,
    DialogFooter,
} from '@/components/ui/dialog';
import { Label } from '@/components/ui/label';
import { Input } from '@/components/ui/input';
import { Button } from '@/components/ui/button';
import InputError from '@/components/InputError.vue';
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
    <Dialog :open="show" @update:open="(val) => !val && close()">
        <DialogContent class="sm:max-w-lg">
            <DialogHeader>
                <DialogTitle>{{ note ? 'Edit Note' : 'Create Note' }}</DialogTitle>
            </DialogHeader>

            <div class="space-y-4 py-4">
                <!-- Title -->
                <div class="space-y-2">
                    <Label for="title">Title</Label>
                    <Input
                        id="title"
                        v-model="form.title"
                        type="text"
                        placeholder="Note title"
                    />
                    <InputError :message="form.errors.title" />
                </div>

                <!-- Content -->
                <div class="space-y-2">
                    <Label for="content">Content</Label>
                    <textarea
                        id="content"
                        v-model="form.content"
                        class="flex min-h-32 w-full rounded-md border border-input bg-background px-3 py-2 text-sm ring-offset-background placeholder:text-muted-foreground focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-ring focus-visible:ring-offset-2 disabled:cursor-not-allowed disabled:opacity-50"
                        placeholder="Write your note here..."
                    ></textarea>
                    <InputError :message="form.errors.content" />
                </div>

                <!-- Options Row -->
                <div class="flex items-center justify-between">
                    <!-- Pin Toggle -->
                    <button
                        type="button"
                        @click="form.is_pinned = !form.is_pinned"
                        class="flex items-center gap-2 rounded-md px-3 py-2 text-sm font-medium transition-colors"
                        :class="form.is_pinned ? 'bg-primary/10 text-primary' : 'text-muted-foreground hover:bg-muted'"
                    >
                        <Pin :size="16" :class="{'fill-current': form.is_pinned}" />
                        {{ form.is_pinned ? 'Pinned' : 'Pin Note' }}
                    </button>
                </div>
            </div>

            <DialogFooter class="gap-2">
                <Button variant="outline" @click="close">Cancel</Button>
                <Button @click="submit" :disabled="form.processing">Save</Button>
            </DialogFooter>
        </DialogContent>
    </Dialog>
</template>
