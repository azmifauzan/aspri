<script setup lang="ts">
import { Head } from '@inertiajs/vue3';
import { MessageSquare } from 'lucide-vue-next';
import { computed, onMounted, ref } from 'vue';
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

// Debug logging
onMounted(() => {
    console.log('Telegram Page Props:', {
        botUsername: props.botUsername,
        linkCode: props.linkCode,
        isLinked: props.isLinked,
        telegramUsername: props.telegramUsername,
    });
});

const breadcrumbItems: BreadcrumbItem[] = [
    {
        title: 'Settings',
        href: '/settings',
    },
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
        navigator.clipboard.writeText(`connect ${props.linkCode}`);
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
                    <!-- Alert if bot username not configured -->
                    <Card v-if="!botUsername" class="border-yellow-500/20 bg-yellow-500/5">
                        <CardContent class="pt-6">
                            <p class="text-sm text-yellow-800 dark:text-yellow-200">
                                <strong>Configuration Missing:</strong> Telegram bot username is not configured. Please set <code class="rounded bg-yellow-100 px-1 py-0.5 dark:bg-yellow-900">TELEGRAM_BOT_USERNAME</code> in your .env file.
                            </p>
                        </CardContent>
                    </Card>
                    
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
                                            <div class="rounded-lg border-2 border-primary/30 bg-primary/5 p-4">
                                                <h4 class="mb-2 font-semibold text-primary">Step 1: Your Link Code</h4>
                                                <p class="mb-3 text-sm text-muted-foreground">
                                                    Send this command to the bot:
                                                </p>
                                                <div class="flex items-center gap-2">
                                                    <div v-if="linkCode" class="flex-1 rounded-lg border-2 border-primary bg-white px-4 py-3 dark:bg-neutral-900">
                                                        <code class="text-lg font-bold tracking-wide text-foreground">
                                                            connect {{ linkCode }}
                                                        </code>
                                                    </div>
                                                    <div v-else class="flex-1 rounded-lg border-2 border-red-500 bg-red-50 px-4 py-3 dark:bg-red-950">
                                                        <code class="text-sm font-semibold text-red-600 dark:text-red-400">
                                                            Error: Code not generated
                                                        </code>
                                                    </div>
                                                    <Button
                                                        type="button"
                                                        variant="default"
                                                        @click="copyCode"
                                                        class="min-w-20"
                                                        :disabled="!linkCode"
                                                    >
                                                        {{ copied ? 'Copied!' : 'Copy' }}
                                                    </Button>
                                                </div>
                                            </div>

                                            <div class="rounded-lg border bg-muted/40 p-4">
                                                <h4 class="mb-2 font-medium">Step 2: Open Telegram Bot</h4>
                                                <p class="mb-3 text-sm text-muted-foreground">
                                                    <template v-if="botUsername">
                                                        Click the button below or search for <strong class="text-foreground">{{ botUsername.startsWith('@') ? botUsername : '@' + botUsername }}</strong> in Telegram
                                                    </template>
                                                    <template v-else>
                                                        <span class="text-red-600 dark:text-red-400">Bot username not configured. Please check your .env file.</span>
                                                    </template>
                                                </p>
                                                <Button
                                                    v-if="telegramBotLink"
                                                    as-child
                                                    class="w-full gap-2"
                                                    size="lg"
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
                                                <Button
                                                    v-else
                                                    disabled
                                                    class="w-full gap-2"
                                                    size="lg"
                                                >
                                                    <MessageSquare class="h-4 w-4" />
                                                    Bot Not Configured
                                                </Button>
                                            </div>

                                            <div class="rounded-lg border bg-muted/40 p-4">
                                                <h4 class="mb-2 font-medium">Step 3: Send the Code</h4>
                                                <p class="text-sm text-muted-foreground">
                                                    Send the command <code class="rounded bg-muted px-1.5 py-0.5 font-mono text-xs">connect YOURCODE</code> to the bot, or simply click the button in Step 2 which will automatically include the code.
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
