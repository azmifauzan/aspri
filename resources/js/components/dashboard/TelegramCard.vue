<script setup lang="ts">
import { Link, router } from '@inertiajs/vue3';
import { Check, Copy, ExternalLink, LogOut, MessageCircle, X } from 'lucide-vue-next';
import { computed, ref } from 'vue';
import { useI18n } from 'vue-i18n';
import Swal from 'sweetalert2';

const { t } = useI18n();
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import type { TelegramInfo } from '@/types/dashboard';

const props = defineProps<{
    telegramInfo: TelegramInfo;
}>();

const copied = ref(false);
const disconnecting = ref(false);

const telegramBotLink = computed(() => {
    if (props.telegramInfo.botUsername && props.telegramInfo.linkCode) {
        return `https://t.me/${props.telegramInfo.botUsername}?start=${props.telegramInfo.linkCode}`;
    }
    if (props.telegramInfo.botUsername) {
        return `https://t.me/${props.telegramInfo.botUsername}`;
    }
    return null;
});

const copyToClipboard = async () => {
    if (!props.telegramInfo.linkCode) return;
    try {
        await navigator.clipboard.writeText(`connect ${props.telegramInfo.linkCode}`);
        copied.value = true;
        setTimeout(() => { copied.value = false; }, 2000);
    } catch (err) {
        console.error('Failed to copy:', err);
    }
};

const disconnectTelegram = () => {
    Swal.fire({
        title: t('dashboard.telegramDisconnectTitle'),
        text: t('dashboard.telegramDisconnectText', { username: props.telegramInfo.username }),
        icon: 'warning',
        showCancelButton: true,
        confirmButtonColor: '#d33',
        cancelButtonColor: '#6b7280',
        confirmButtonText: t('dashboard.telegramDisconnectConfirm'),
        cancelButtonText: t('common.cancel'),
    }).then((result) => {
        if (result.isConfirmed) {
            disconnecting.value = true;
            router.delete('/settings/telegram', {
                preserveScroll: true,
                onFinish: () => { disconnecting.value = false; },
                onSuccess: () => {
                    Swal.fire({
                        icon: 'success',
                        title: t('common.success'),
                        text: t('dashboard.telegramDisconnectSuccess'),
                        timer: 2000,
                        showConfirmButton: false,
                    });
                },
            });
        }
    });
};
</script>

<template>
    <Card>
        <CardHeader class="pb-3">
            <div class="flex items-center justify-between">
                <CardTitle class="text-sm font-medium">Telegram</CardTitle>
                <Badge v-if="telegramInfo.isLinked" variant="default" class="gap-1">
                    <Check class="h-3 w-3" />
                    {{ $t('dashboard.telegramConnected') }}
                </Badge>
                <Badge v-else variant="secondary" class="gap-1">
                    <X class="h-3 w-3" />
                    {{ $t('dashboard.telegramNotConnected') }}
                </Badge>
            </div>
            <CardDescription class="text-xs">
                {{ telegramInfo.isLinked
                    ? `@${telegramInfo.username}`
                    : $t('dashboard.telegramLinkAccount') }}
            </CardDescription>
        </CardHeader>

        <CardContent class="space-y-3">
            <!-- Connected State -->
            <div v-if="telegramInfo.isLinked" class="space-y-3">
                <div class="flex items-center gap-2 text-sm text-muted-foreground">
                    <MessageCircle class="h-4 w-4 text-green-500" />
                    <span>{{ $t('dashboard.telegramNotifActive') }}</span>
                </div>
                <Button
                    variant="destructive"
                    size="sm"
                    class="w-full gap-2"
                    :disabled="disconnecting"
                    @click="disconnectTelegram"
                >
                    <LogOut class="h-4 w-4" />
                    {{ disconnecting ? $t('dashboard.telegramDisconnecting') : $t('dashboard.telegramDisconnect') }}
                </Button>
            </div>

            <!-- Not Connected State -->
            <div v-else class="space-y-3">
                <!-- Step 1: Command to send -->
                <div class="space-y-1">
                    <p class="text-xs font-medium text-muted-foreground">{{ $t('dashboard.telegramSendCommand') }}</p>
                    <div class="flex items-center gap-2">
                        <div class="flex-1 rounded-md border-2 border-primary bg-primary/5 px-3 py-2">
                            <code class="text-sm font-bold tracking-wide text-foreground">
                                connect {{ telegramInfo.linkCode ?? '...' }}
                            </code>
                        </div>
                        <Button
                            variant="outline"
                            size="sm"
                            class="shrink-0"
                            :disabled="!telegramInfo.linkCode"
                            @click="copyToClipboard"
                        >
                            <Copy v-if="!copied" class="h-4 w-4" />
                            <Check v-else class="h-4 w-4 text-green-500" />
                        </Button>
                    </div>
                </div>

                <!-- Step 2: Open bot -->
                <Button
                    v-if="telegramBotLink"
                    as-child
                    variant="default"
                    size="sm"
                    class="w-full gap-2"
                >
                    <a :href="telegramBotLink" target="_blank" rel="noopener noreferrer">
                        <ExternalLink class="h-4 w-4" />
                        {{ $t('dashboard.telegramOpenBot') }}
                    </a>
                </Button>
                <Button v-else variant="default" size="sm" class="w-full" disabled>
                    <MessageCircle class="h-4 w-4 mr-2" />
                    {{ $t('dashboard.telegramBotNotConfigured') }}
                </Button>
            </div>

            <!-- Settings Link -->
            <div class="border-t pt-2">
                <Link href="/settings/telegram" class="text-xs text-primary hover:underline">
                    {{ telegramInfo.isLinked ? $t('dashboard.telegramManage') : $t('dashboard.telegramFullSettings') }} →
                </Link>
            </div>
        </CardContent>
    </Card>
</template>
