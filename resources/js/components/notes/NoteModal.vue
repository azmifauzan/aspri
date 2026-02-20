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
import { store, update } from '@/routes/notes';
import Swal from 'sweetalert2';
import { useI18n } from 'vue-i18n';

const { t } = useI18n();

const props = defineProps<{
    show: boolean;
    note?: any;
}>();

const emit = defineEmits(['close']);

/** Extract plain text from JSON block content for display in the textarea */
const blocksToText = (content: string | null): string => {
    if (!content) { return ''; }
    try {
        const blocks = JSON.parse(content);
        if (!Array.isArray(blocks)) { return content; }
        return blocks.map((block: any) => {
            if (block.type === 'list' && Array.isArray(block.items)) {
                return block.items.join('\n');
            }
            return block.content ?? '';
        }).join('\n\n').trim();
    } catch {
        return content;
    }
};

/** Wrap plain text into a single paragraph block for storage */
const textToBlocks = (text: string): string => {
    return JSON.stringify([{ type: 'paragraph', content: text }]);
};

const form = useForm({
    title: '',
    content: '',
    is_pinned: false,
    color: null as string | null,
});

watch(() => props.note, (newNote) => {
    if (newNote) {
        form.title = newNote.title;
        form.content = blocksToText(newNote.content);
        form.is_pinned = newNote.is_pinned;
        form.color = newNote.color;
    } else {
        form.reset();
    }
}, { immediate: true });

const submit = () => {
    const payload = { ...form.data(), content: form.content ? textToBlocks(form.content) : null };
    if (props.note) {
        form.transform(() => payload).put(update(props.note!.id).url, {
            onSuccess: () => {
                form.reset();
                emit('close');
                Swal.fire({
                    icon: 'success',
                    title: t('notes.noteUpdated'),
                    text: t('notes.noteUpdatedDesc'),
                    timer: 2000,
                    showConfirmButton: false,
                });
            },
            onError: () => {
                Swal.fire({
                    icon: 'error',
                    title: t('notes.updateFailed'),
                    text: t('notes.updateError'),
                });
            },
        });
    } else {
        form.transform(() => payload).post(store().url, {
            onSuccess: () => {
                form.reset();
                emit('close');
                Swal.fire({
                    icon: 'success',
                    title: t('notes.noteCreated'),
                    text: t('notes.noteCreatedDesc'),
                    timer: 2000,
                    showConfirmButton: false,
                });
            },
            onError: () => {
                Swal.fire({
                    icon: 'error',
                    title: t('notes.createFailed'),
                    text: t('notes.createError'),
                });
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
                <DialogTitle>{{ note ? $t('notes.editNote') : $t('notes.createNote') }}</DialogTitle>
            </DialogHeader>

            <div class="space-y-4 py-4">
                <!-- Title -->
                <div class="space-y-2">
                    <Label for="title">{{ $t('notes.titleLabel') }}</Label>
                    <Input
                        id="title"
                        v-model="form.title"
                        type="text"
                        :placeholder="$t('notes.titlePlaceholder')"
                    />
                    <InputError :message="form.errors.title" />
                </div>

                <!-- Content -->
                <div class="space-y-2">
                    <Label for="content">{{ $t('notes.contentLabel') }}</Label>
                    <textarea
                        id="content"
                        v-model="form.content"
                        class="flex min-h-32 w-full rounded-md border border-input bg-background px-3 py-2 text-sm ring-offset-background placeholder:text-muted-foreground focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-ring focus-visible:ring-offset-2 disabled:cursor-not-allowed disabled:opacity-50"
                        :placeholder="$t('notes.contentPlaceholder')"
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
                        {{ form.is_pinned ? $t('notes.pinned') : $t('notes.pinNote') }}
                    </button>
                </div>
            </div>

            <DialogFooter class="gap-2">
                <Button variant="outline" @click="close">{{ $t('common.cancel') }}</Button>
                <Button @click="submit" :disabled="form.processing">{{ $t('common.save') }}</Button>
            </DialogFooter>
        </DialogContent>
    </Dialog>
</template>
