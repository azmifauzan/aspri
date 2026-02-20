<script setup lang="ts">
import { Head, router } from '@inertiajs/vue3';
import { LogOut, MessageSquare } from 'lucide-vue-next';
import { computed, onMounted, ref } from 'vue';
import Swal from 'sweetalert2';
import Heading from '@/components/Heading.vue';
import { Button } from '@/components/ui/button';
import { Card, CardContent } from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import { Separator } from '@/components/ui/separator';
import AppLayout from '@/layouts/AppLayout.vue';
import SettingsLayout from '@/layouts/settings/Layout.vue';
import { type BreadcrumbItem } from '@/types';
import { useI18n } from 'vue-i18n';

type Props = {
    botUsername?: string;
    linkCode?: string;
    isLinked: boolean;
    telegramUsername?: string;
};

const props = defineProps<Props>();

const { t } = useI18n();

// Debug logging
onMounted(() => {
    console.log('Telegram Page Props:', {
        botUsername: props.botUsername,
        linkCode: props.linkCode,
        isLinked: props.isLinked,
        telegramUsername: props.telegramUsername,
    });
});

const disconnecting = ref(false);

const disconnectTelegram = () => {
    Swal.fire({
        title: t('settings.disconnectTelegramTitle'),
        text: t('settings.disconnectTelegramText', { username: props.telegramUsername }),
        icon: 'warning',
        showCancelButton: true,
        confirmButtonColor: '#d33',
        cancelButtonColor: '#6b7280',
        confirmButtonText: t('settings.disconnectConfirm'),
        cancelButtonText: t('settings.cancel'),
    }).then((result) => {
        if (result.isConfirmed) {
            disconnecting.value = true;
            router.delete('/settings/telegram', {
                preserveScroll: true,
                onFinish: () => {
                    disconnecting.value = false;
                },
                onSuccess: () => {
                    Swal.fire({
                        icon: 'success',
                        title: t('settings.successTitle'),
                        text: t('settings.disconnectSuccess'),
                        timer: 2000,
                        showConfirmButton: false,
                    });
                },
                onError: () => {
                    Swal.fire({
                        icon: 'error',
                        title: t('settings.failedTitle'),
                        text: t('settings.disconnectFailed'),
                    });
                },
            });
        }
    });
};

const breadcrumbItems = computed<BreadcrumbItem[]>(() => [
    {
        title: t('settings.title'),
        href: '/settings',
    },
    {
        title: t('settings.telegramIntegration'),
        href: '/settings/telegram',
    },
]);

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
        <Head :title="$t('settings.telegramIntegration')" />

        <h1 class="sr-only">{{ $t('settings.telegramIntegration') }}</h1>

        <SettingsLayout>
            <div class="flex flex-col space-y-6">
                <Heading
                    variant="small"
                    :title="$t('settings.telegramIntegration')"
                    :description="$t('settings.telegramIntegrationDescription')"
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
                                    {{ $t('settings.telegramConnectedStatus') }}
                                </p>
                                <p class="text-sm text-muted-foreground">
                                    {{ $t('settings.connectedTo') }} <strong>@{{ telegramUsername }}</strong>
                                </p>
                                <p class="text-sm text-muted-foreground">
                                    {{ $t('settings.connectedHelp') }}
                                </p>
                            </div>
                        </div>
                        <div class="mt-4 border-t pt-4">
                            <Button
                                variant="destructive"
                                size="sm"
                                class="gap-2"
                                :disabled="disconnecting"
                                @click="disconnectTelegram"
                            >
                                <LogOut class="h-4 w-4" />
                                {{ disconnecting ? $t('settings.disconnecting') : $t('settings.disconnectTelegram') }}
                            </Button>
                        </div>
                    </CardContent>
                </Card>

                <!-- Not Linked - Connection Instructions -->
                <div v-else class="space-y-4">
                    <!-- Alert if bot username not configured -->
                    <Card v-if="!botUsername" class="border-yellow-500/20 bg-yellow-500/5">
                        <CardContent class="pt-6">
                            <p class="text-sm text-yellow-800 dark:text-yellow-200">
                                <strong>{{ $t('settings.configMissing') }}</strong> {{ $t('settings.configMissingDescBefore') }} <code class="rounded bg-yellow-100 px-1 py-0.5 dark:bg-yellow-900">TELEGRAM_BOT_USERNAME</code> {{ $t('settings.configMissingDescAfter') }}
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
                                            <p class="font-medium">{{ $t('settings.connectToTelegram') }}</p>
                                            <p class="text-sm text-muted-foreground">
                                                {{ $t('settings.connectSteps') }}
                                            </p>
                                        </div>

                                        <div class="space-y-3">
                                            <div class="rounded-lg border-2 border-primary/30 bg-primary/5 p-4">
                                                <h4 class="mb-2 font-semibold text-primary">{{ $t('settings.step1Title') }}</h4>
                                                <p class="mb-3 text-sm text-muted-foreground">
                                                    {{ $t('settings.step1Desc') }}
                                                </p>
                                                <div class="flex items-center gap-2">
                                                    <div v-if="linkCode" class="flex-1 rounded-lg border-2 border-primary bg-white px-4 py-3 dark:bg-neutral-900">
                                                        <code class="text-lg font-bold tracking-wide text-foreground">
                                                            connect {{ linkCode }}
                                                        </code>
                                                    </div>
                                                    <div v-else class="flex-1 rounded-lg border-2 border-red-500 bg-red-50 px-4 py-3 dark:bg-red-950">
                                                        <code class="text-sm font-semibold text-red-600 dark:text-red-400">
                                                            {{ $t('settings.codeNotGenerated') }}
                                                        </code>
                                                    </div>
                                                    <Button
                                                        type="button"
                                                        variant="default"
                                                        @click="copyCode"
                                                        class="min-w-20"
                                                        :disabled="!linkCode"
                                                    >
                                                        {{ copied ? $t('settings.copied') : $t('settings.copy') }}
                                                    </Button>
                                                </div>
                                            </div>

                                            <div class="rounded-lg border bg-muted/40 p-4">
                                                <h4 class="mb-2 font-medium">{{ $t('settings.step2Title') }}</h4>
                                                <p class="mb-3 text-sm text-muted-foreground">
                                                    <template v-if="botUsername">
                                                        {{ $t('settings.step2DescBefore') }} <strong class="text-foreground">{{ botUsername.startsWith('@') ? botUsername : '@' + botUsername }}</strong> {{ $t('settings.step2DescAfter') }}
                                                    </template>
                                                    <template v-else>
                                                        <span class="text-red-600 dark:text-red-400">{{ $t('settings.botNotConfiguredError') }}</span>
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
                                                        {{ $t('settings.openTelegramBot') }}
                                                    </a>
                                                </Button>
                                                <Button
                                                    v-else
                                                    disabled
                                                    class="w-full gap-2"
                                                    size="lg"
                                                >
                                                    <MessageSquare class="h-4 w-4" />
                                                    {{ $t('settings.botNotConfigured') }}
                                                </Button>
                                            </div>

                                            <div class="rounded-lg border bg-muted/40 p-4">
                                                <h4 class="mb-2 font-medium">{{ $t('settings.step3Title') }}</h4>
                                                <p class="text-sm text-muted-foreground">
                                                    {{ $t('settings.step3DescBefore') }} <code class="rounded bg-muted px-1.5 py-0.5 font-mono text-xs">connect YOURCODE</code> {{ $t('settings.step3DescAfter') }}
                                                </p>
                                            </div>

                                            <div class="rounded-lg border bg-muted/40 p-4">
                                                <h4 class="mb-2 font-medium">{{ $t('settings.step4Title') }}</h4>
                                                <p class="text-sm text-muted-foreground">
                                                    {{ $t('settings.step4Desc') }}
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
                                <strong>{{ $t('settings.note') }}</strong> {{ $t('settings.linkCodeNote') }}
                            </p>
                        </CardContent>
                    </Card>
                </div>
            </div>
        </SettingsLayout>
    </AppLayout>
</template>
