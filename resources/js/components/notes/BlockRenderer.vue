<script setup lang="ts">
import { computed } from 'vue';

const props = defineProps<{
    content: string | null;
    preview?: boolean; // If true, render plain text only
}>();

type TiptapNode = {
    type: string;
    content?: TiptapNode[];
    text?: string;
    attrs?: Record<string, unknown>;
};

const parsed = computed<TiptapNode | null>(() => {
    if (!props.content) {
        return null;
    }
    try {
        const value = JSON.parse(props.content);
        if (value && value.type === 'doc') {
            return value as TiptapNode;
        }
        if (Array.isArray(value)) {
            return legacyToDoc(value);
        }
        return null;
    } catch {
        return null;
    }
});

const legacyToDoc = (blocks: Array<{ type: string; content?: string; items?: string[] }>): TiptapNode => {
    const content: TiptapNode[] = blocks.map((b) => {
        if (b.type === 'list' && Array.isArray(b.items)) {
            return {
                type: 'bulletList',
                content: b.items.map((item) => ({
                    type: 'listItem',
                    content: [{ type: 'paragraph', content: [{ type: 'text', text: item }] }],
                })),
            };
        }
        return { type: 'paragraph', content: b.content ? [{ type: 'text', text: b.content }] : [] };
    });
    return { type: 'doc', content };
};

const nodeText = (node: TiptapNode): string => {
    if (node.type === 'text') {
        return node.text ?? '';
    }
    if (!node.content) {
        return '';
    }
    return node.content.map(nodeText).join(' ');
};

const plainText = computed(() => {
    if (!parsed.value) {
        return props.content ?? '';
    }
    return parsed.value.content?.map(nodeText).join(' ').trim() ?? '';
});
</script>

<template>
    <p v-if="preview" class="line-clamp-3">{{ plainText }}</p>
    <div v-else-if="parsed" class="prose prose-sm dark:prose-invert max-w-none">
        <template v-for="(node, idx) in parsed.content ?? []" :key="idx">
            <component :is="resolveTag(node.type, node.attrs)" v-if="node.type === 'heading'">
                {{ nodeText(node) }}
            </component>
            <p v-else-if="node.type === 'paragraph'">{{ nodeText(node) }}</p>
            <ul v-else-if="node.type === 'bulletList'">
                <li v-for="(item, i) in node.content ?? []" :key="i">{{ nodeText(item) }}</li>
            </ul>
            <ol v-else-if="node.type === 'orderedList'">
                <li v-for="(item, i) in node.content ?? []" :key="i">{{ nodeText(item) }}</li>
            </ol>
            <pre v-else-if="node.type === 'codeBlock'"><code>{{ nodeText(node) }}</code></pre>
            <img
                v-else-if="node.type === 'image'"
                :src="String(node.attrs?.src ?? '')"
                :alt="String(node.attrs?.alt ?? '')"
            />
        </template>
    </div>
    <p v-else>{{ content }}</p>
</template>

<script lang="ts">
const resolveTag = (type: string, attrs?: Record<string, unknown>): string => {
    if (type === 'heading') {
        const level = Number(attrs?.level ?? 2);
        return `h${level}`;
    }
    return 'div';
};
</script>
