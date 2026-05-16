<script setup lang="ts">
import { Editor, EditorContent } from '@tiptap/vue-3';
import StarterKit from '@tiptap/starter-kit';
import Image from '@tiptap/extension-image';
import Placeholder from '@tiptap/extension-placeholder';
import { onBeforeUnmount, onMounted, ref, watch } from 'vue';
import { Bold, Code, Heading1, Heading2, Image as ImageIcon, Italic, List, ListOrdered } from 'lucide-vue-next';

const props = defineProps<{
    modelValue: string | null;
    placeholder?: string;
}>();

const emit = defineEmits<{
    (e: 'update:modelValue', value: string): void;
}>();

const editor = ref<Editor | null>(null);

const parseInitial = (value: string | null): object => {
    if (!value) {
        return { type: 'doc', content: [{ type: 'paragraph' }] };
    }
    try {
        const parsed = JSON.parse(value);
        // Stored Tiptap JSON
        if (parsed && parsed.type === 'doc') {
            return parsed;
        }
        // Legacy "blocks" array — convert to Tiptap doc.
        if (Array.isArray(parsed)) {
            return legacyBlocksToTiptap(parsed);
        }
        return { type: 'doc', content: [{ type: 'paragraph', content: [{ type: 'text', text: String(value) }] }] };
    } catch {
        return { type: 'doc', content: [{ type: 'paragraph', content: [{ type: 'text', text: value }] }] };
    }
};

const legacyBlocksToTiptap = (blocks: Array<{ type: string; content?: string; items?: string[] }>): object => {
    const content = blocks.map((block) => {
        if (block.type === 'list' && Array.isArray(block.items)) {
            return {
                type: 'bulletList',
                content: block.items.map((item) => ({
                    type: 'listItem',
                    content: [{ type: 'paragraph', content: [{ type: 'text', text: item }] }],
                })),
            };
        }
        if (block.type === 'heading') {
            return {
                type: 'heading',
                attrs: { level: 2 },
                content: block.content ? [{ type: 'text', text: block.content }] : [],
            };
        }
        if (block.type === 'code') {
            return {
                type: 'codeBlock',
                content: block.content ? [{ type: 'text', text: block.content }] : [],
            };
        }
        return {
            type: 'paragraph',
            content: block.content ? [{ type: 'text', text: block.content }] : [],
        };
    });
    return { type: 'doc', content: content.length > 0 ? content : [{ type: 'paragraph' }] };
};

onMounted(() => {
    editor.value = new Editor({
        content: parseInitial(props.modelValue),
        extensions: [
            StarterKit,
            Image,
            Placeholder.configure({ placeholder: props.placeholder ?? '' }),
        ],
        editorProps: {
            attributes: {
                class: 'prose prose-sm dark:prose-invert max-w-none min-h-32 px-3 py-2 focus:outline-none',
            },
        },
        onUpdate: ({ editor }) => {
            emit('update:modelValue', JSON.stringify(editor.getJSON()));
        },
    });
});

watch(
    () => props.modelValue,
    (value) => {
        if (!editor.value) {
            return;
        }
        const current = JSON.stringify(editor.value.getJSON());
        if (current !== value) {
            editor.value.commands.setContent(parseInitial(value), { emitUpdate: false });
        }
    },
);

onBeforeUnmount(() => {
    editor.value?.destroy();
});

const promptImage = () => {
    const url = window.prompt('Image URL');
    if (url) {
        editor.value?.chain().focus().setImage({ src: url }).run();
    }
};
</script>

<template>
    <div class="rounded-md border border-input bg-background">
        <div class="flex flex-wrap items-center gap-1 border-b border-input p-1">
            <button
                type="button"
                class="rounded p-1.5 text-muted-foreground hover:bg-muted"
                :class="{ 'bg-muted text-foreground': editor?.isActive('heading', { level: 1 }) }"
                title="Heading 1"
                @click="editor?.chain().focus().toggleHeading({ level: 1 }).run()"
            >
                <Heading1 :size="16" />
            </button>
            <button
                type="button"
                class="rounded p-1.5 text-muted-foreground hover:bg-muted"
                :class="{ 'bg-muted text-foreground': editor?.isActive('heading', { level: 2 }) }"
                title="Heading 2"
                @click="editor?.chain().focus().toggleHeading({ level: 2 }).run()"
            >
                <Heading2 :size="16" />
            </button>
            <button
                type="button"
                class="rounded p-1.5 text-muted-foreground hover:bg-muted"
                :class="{ 'bg-muted text-foreground': editor?.isActive('bold') }"
                title="Bold"
                @click="editor?.chain().focus().toggleBold().run()"
            >
                <Bold :size="16" />
            </button>
            <button
                type="button"
                class="rounded p-1.5 text-muted-foreground hover:bg-muted"
                :class="{ 'bg-muted text-foreground': editor?.isActive('italic') }"
                title="Italic"
                @click="editor?.chain().focus().toggleItalic().run()"
            >
                <Italic :size="16" />
            </button>
            <button
                type="button"
                class="rounded p-1.5 text-muted-foreground hover:bg-muted"
                :class="{ 'bg-muted text-foreground': editor?.isActive('bulletList') }"
                title="Bullet List"
                @click="editor?.chain().focus().toggleBulletList().run()"
            >
                <List :size="16" />
            </button>
            <button
                type="button"
                class="rounded p-1.5 text-muted-foreground hover:bg-muted"
                :class="{ 'bg-muted text-foreground': editor?.isActive('orderedList') }"
                title="Ordered List"
                @click="editor?.chain().focus().toggleOrderedList().run()"
            >
                <ListOrdered :size="16" />
            </button>
            <button
                type="button"
                class="rounded p-1.5 text-muted-foreground hover:bg-muted"
                :class="{ 'bg-muted text-foreground': editor?.isActive('codeBlock') }"
                title="Code Block"
                @click="editor?.chain().focus().toggleCodeBlock().run()"
            >
                <Code :size="16" />
            </button>
            <button
                type="button"
                class="rounded p-1.5 text-muted-foreground hover:bg-muted"
                title="Insert Image"
                @click="promptImage"
            >
                <ImageIcon :size="16" />
            </button>
        </div>
        <EditorContent :editor="editor" />
    </div>
</template>
