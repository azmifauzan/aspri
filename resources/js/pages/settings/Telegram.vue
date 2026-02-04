<script setup lang="ts">
import { Head } from '@inertiajs/vue3';
import { MessageSquare } from 'lucide-vue-next';
import { computed, ref } from 'vue';
import Heading from '@/components/Heading.vue';
import { Button } from '@/components/ui/button';
import { Card, CardContent } from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import { Separator } from '@/components/ui/separator';
import AppLayout from '@/layouts/AppLayout.vue';
import SettingsLayout from '@/layouts/settings/Layout.vue';
import { type BreadcrumbItem } from '@/types';

type Props = {
    botUsername?: string;
    linkCode?: string;
    isLinked: boolean;
    telegramUsername?: string;
};

const props = defineProps<Props>();

const breadcrumbItems: BreadcrumbItem[] = [
    {
        title: 'Telegram Integration',
        href: '/settings/telegram',
    },
];

const telegramBotLink = computed(() => {
    if (props.botUsername && props.linkCode) {
        return `https://t.me/${props.botUsername}?start=${props.linkCode}`;
    }
    return null;
});

const copied = ref(false);

const copyCode = () => {
    if (props.linkCode) {
        navigator.clipboard.writeText(props.linkCode);
        copied.value = true;
        setTimeout(() => {
            copied.value = false;
        }, 2000);
    }
};
</script>

<template>
    <AppLayout :breadcrumbs="breadcrumbItems">
        <Head title="Telegram Integration" />

        <h1 class="sr-only">Telegram Integration</h1>

        <SettingsLayout>
            <div class="flex flex-col space-y-6">
                <Heading
                    variant="small"
                    title="Telegram Integration"
                    description="Connect your Telegram account to receive notifications and interact with your assistant via Telegram"
                />

                <Separator />

                <!-- Linked Status -->
                <Card v-if="isLinked" class="border-green-500/20 bg-green-500/5">
                    <CardContent class="pt-6">
                        <div class="flex items-start gap-4">
                            <div class="flex size-10 items-center justify-center rounded-full bg-green-500/10">
                                <MessageSquare class="h-5 w-5 text-green-500" />
                            </div>
                            <div class="flex-1 space-y-1">
                                <p class="font-medium text-green-600 dark:text-green-400">
                                    Telegram Connected
                                </p>
                                <p class="text-sm text-muted-foreground">
                                    Your account is connected to <strong>@{{ telegramUsername }}</strong>
                                </p>
                                <p class="text-sm text-muted-foreground">
                                    You can now receive notifications and chat with your assistant via Telegram.
                                </p>
                            </div>
                        </div>
                    </CardContent>
                </Card>

                <!-- Not Linked - Connection Instructions -->
                <div v-else class="space-y-4">
                    <Card>
                        <CardContent class="pt-6">
                            <div class="space-y-4">
                                <div class="flex items-start gap-4">
                                    <div class="flex size-10 items-center justify-center rounded-full bg-primary/10">
                                        <MessageSquare class="h-5 w-5 text-primary" />
                                    </div>
                                    <div class="flex-1 space-y-3">
                                        <div>
                                            <p class="font-medium">Connect to Telegram</p>
                                            <p class="text-sm text-muted-foreground">
                                                Follow these steps to connect your Telegram account
                                            </p>
                                        </div>

                                        <div class="space-y-3">
                                            <div class="rounded-lg border bg-muted/40 p-4">
                                                <h4 class="mb-2 font-medium">Step 1: Your Link Code</h4>
                                                <p class="mb-3 text-sm text-muted-foreground">
                                                    Copy this unique code:
                                                </p>
                                                <div class="flex gap-2">
                                                    <Input
                                                        :value="linkCode"
                                                        readonly
                                                        class="bg-white font-mono text-black dark:bg-neutral-800 dark:text-white"
                                                    />
                                                    <Button
                                                        type="button"
                                                        variant="outline"
                                                        @click="copyCode"
                                                    >
                                                        {{ copied ? 'Copied!' : 'Copy' }}
                                                    </Button>
                                                </div>
                                            </div>

                                            <div class="rounded-lg border bg-muted/40 p-4">
                                                <h4 class="mb-2 font-medium">Step 2: Open Telegram Bot</h4>
                                                <p class="mb-3 text-sm text-muted-foreground">
                                                    Click the button below or search for <strong>{{ botUsername?.startsWith('@') ? botUsername : '@' + botUsername }}</strong> in Telegram
                                                </p>
                                                <Button
                                                    v-if="telegramBotLink"
                                                    as-child
                                                    class="w-full gap-2"
                                                >
                                                    <a
                                                        :href="telegramBotLink"
                                                        target="_blank"
                                                        rel="noopener noreferrer"
                                                    >
                                                        <MessageSquare class="h-4 w-4" />
                                                        Open Telegram Bot
                                                    </a>
                                                </Button>
                                            </div>

                                            <div class="rounded-lg border bg-muted/40 p-4">
                                                <h4 class="mb-2 font-medium">Step 3: Send the Code</h4>
                                                <p class="text-sm text-muted-foreground">
                                                    Send your link code to the bot, or simply click the button in Step 2 which will automatically include the code.
                                                </p>
                                            </div>

                                            <div class="rounded-lg border bg-muted/40 p-4">
                                                <h4 class="mb-2 font-medium">Step 4: Start Chatting!</h4>
                                                <p class="text-sm text-muted-foreground">
                                                    Once connected, you can chat with your assistant and receive notifications directly in Telegram.
                                                </p>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </CardContent>
                    </Card>

                    <Card class="border-yellow-500/20 bg-yellow-500/5">
                        <CardContent class="pt-6">
                            <p class="text-sm text-yellow-800 dark:text-yellow-200">
                                <strong>Note:</strong> The link code expires after 24 hours or once used. If you need a new code, simply refresh this page.
                            </p>
                        </CardContent>
                    </Card>
                </div>
            </div>
        </SettingsLayout>
    </AppLayout>
</template>
