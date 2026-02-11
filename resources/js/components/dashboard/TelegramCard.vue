<script setup lang="ts">
import { Link } from '@inertiajs/vue3';
import { Check, Copy, Eye, EyeOff, MessageCircle, X } from 'lucide-vue-next';
import { ref } from 'vue';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import type { TelegramInfo } from '@/types/dashboard';

const props = defineProps<{
    telegramInfo: TelegramInfo;
}>();

const showCode = ref(false);
const copied = ref(false);

const toggleCodeVisibility = () => {
    showCode.value = !showCode.value;
};

const copyToClipboard = async () => {
    if (!props.telegramInfo.linkCode) return;
    
    try {
        await navigator.clipboard.writeText(props.telegramInfo.linkCode);
        copied.value = true;
        setTimeout(() => {
            copied.value = false;
        }, 2000);
    } catch (err) {
        console.error('Failed to copy:', err);
    }
};

const openTelegram = () => {
    if (!props.telegramInfo.botUsername) return;
    window.open(`https://t.me/${props.telegramInfo.botUsername}`, '_blank');
};
</script>

<template>
    <Card>
        <CardHeader class="pb-3">
            <div class="flex items-center justify-between">
                <CardTitle class="text-sm font-medium">Telegram</CardTitle>
                <Badge v-if="telegramInfo.isLinked" variant="default" class="gap-1">
                    <Check class="h-3 w-3" />
                    Terhubung
                </Badge>
                <Badge v-else variant="secondary" class="gap-1">
                    <X class="h-3 w-3" />
                    Belum Terhubung
                </Badge>
            </div>
            <CardDescription class="text-xs">
                {{ telegramInfo.isLinked 
                    ? `@${telegramInfo.username}` 
                    : 'Hubungkan akun Telegram Anda' 
                }}
            </CardDescription>
        </CardHeader>
        <CardContent class="space-y-3">
            <!-- Connected State -->
            <div v-if="telegramInfo.isLinked" class="flex items-center gap-2 text-sm text-muted-foreground">
                <MessageCircle class="h-4 w-4" />
                <span>Notifikasi aktif via Telegram</span>
            </div>

            <!-- Not Connected State -->
            <div v-else class="space-y-3">
                <div class="space-y-2">
                    <div class="flex items-center justify-between">
                        <label class="text-xs font-medium">Kode Koneksi</label>
                        <Button 
                            variant="ghost" 
                            size="sm" 
                            @click="toggleCodeVisibility"
                            class="h-7 px-2 text-xs"
                        >
                            <Eye v-if="!showCode" class="h-3 w-3 mr-1" />
                            <EyeOff v-else class="h-3 w-3 mr-1" />
                            {{ showCode ? 'Sembunyikan' : 'Tampilkan' }}
                        </Button>
                    </div>
                    
                    <div v-if="showCode" class="flex gap-2">
                        <Input 
                            :value="telegramInfo.linkCode" 
                            readonly 
                            class="font-mono text-sm"
                        />
                        <Button 
                            variant="outline" 
                            size="sm" 
                            @click="copyToClipboard"
                            class="shrink-0"
                        >
                            <Copy v-if="!copied" class="h-4 w-4" />
                            <Check v-else class="h-4 w-4 text-green-500" />
                        </Button>
                    </div>
                </div>

                <div class="space-y-2">
                    <Button 
                        variant="default" 
                        size="sm" 
                        class="w-full gap-2"
                        @click="openTelegram"
                        :disabled="!telegramInfo.botUsername"
                    >
                        <MessageCircle class="h-4 w-4" />
                        Buka Bot Telegram
                    </Button>
                    <p class="text-xs text-muted-foreground text-center">
                        Kirim kode di atas ke bot untuk menghubungkan
                    </p>
                </div>
            </div>

            <!-- Settings Link -->
            <div class="pt-2 border-t">
                <Link href="/settings/telegram" class="text-xs text-primary hover:underline">
                    {{ telegramInfo.isLinked ? 'Kelola Telegram' : 'Pengaturan Lengkap' }} →
                </Link>
            </div>
        </CardContent>
    </Card>
</template>
