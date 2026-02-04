<script setup lang="ts">
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import { Progress } from '@/components/ui/progress';
import type { ChatLimit, SubscriptionInfo } from '@/types/dashboard';
import { Link } from '@inertiajs/vue3';
import { Calendar, Clock, Crown, MessageSquare, Sparkles } from 'lucide-vue-next';
import { computed } from 'vue';

const props = withDefaults(
    defineProps<{
        subscriptionInfo?: SubscriptionInfo;
        chatLimit?: ChatLimit;
    }>(),
    {
        subscriptionInfo: () => ({
            status: 'none',
            plan: null,
            ends_at: null,
            days_remaining: 0,
            is_paid: false,
        }),
        chatLimit: () => ({
            used: 0,
            limit: 50,
            remaining: 50,
        }),
    }
);

const chatUsagePercent = computed(() => {
    if (!props.chatLimit || props.chatLimit.limit === 0) return 0;
    return Math.round((props.chatLimit.used / props.chatLimit.limit) * 100);
});

const formatDate = (dateStr: string | null) => {
    if (!dateStr) return '-';
    return new Date(dateStr).toLocaleDateString('id-ID', {
        day: 'numeric',
        month: 'short',
        year: 'numeric',
    });
};

const getPlanLabel = (plan: string | null) => {
    switch (plan) {
        case 'free_trial':
            return 'Free Trial';
        case 'monthly':
            return 'Bulanan';
        case 'yearly':
            return 'Tahunan';
        default:
            return 'Tidak Aktif';
    }
};

const isLowOnChats = computed(() => {
    if (!props.chatLimit) return false;
    return props.chatLimit.remaining <= 10 && props.chatLimit.remaining > 0;
});

const isOutOfChats = computed(() => {
    if (!props.chatLimit) return false;
    return props.chatLimit.remaining === 0;
});
</script>

<template>
    <Card>
        <CardHeader class="pb-3">
            <div class="flex items-center justify-between">
                <CardTitle class="text-sm font-medium">Subscription</CardTitle>
                <Badge v-if="subscriptionInfo?.is_paid" variant="default" class="gap-1">
                    <Crown class="h-3 w-3" />
                    Full Member
                </Badge>
                <Badge v-else-if="subscriptionInfo?.plan === 'free_trial'" variant="secondary" class="gap-1">
                    <Clock class="h-3 w-3" />
                    Trial
                </Badge>
                <Badge v-else variant="outline">Tidak Aktif</Badge>
            </div>
            <CardDescription class="text-xs">
                {{ getPlanLabel(subscriptionInfo?.plan) }}
                <span v-if="subscriptionInfo?.ends_at">
                    • Berakhir {{ formatDate(subscriptionInfo.ends_at) }}
                </span>
            </CardDescription>
        </CardHeader>
        <CardContent class="space-y-4">
            <!-- Days Remaining (for trial users) -->
            <div v-if="!subscriptionInfo?.is_paid && subscriptionInfo?.days_remaining && subscriptionInfo.days_remaining > 0" class="flex items-center gap-2 text-sm">
                <Calendar class="h-4 w-4 text-muted-foreground" />
                <span>
                    <strong>{{ subscriptionInfo.days_remaining }}</strong> hari tersisa
                </span>
            </div>

            <!-- Chat Usage -->
            <div class="space-y-2">
                <div class="flex items-center justify-between text-sm">
                    <div class="flex items-center gap-2">
                        <MessageSquare class="h-4 w-4 text-muted-foreground" />
                        <span>Chat Hari Ini</span>
                    </div>
                    <span :class="{ 'text-red-500': isOutOfChats, 'text-orange-500': isLowOnChats }">
                        {{ chatLimit?.used ?? 0 }}/{{ chatLimit?.limit ?? 50 }}
                    </span>
                </div>
                <Progress 
                    :model-value="chatUsagePercent" 
                    class="h-2"
                    :class="{ 
                        '[&>div]:bg-red-500': isOutOfChats, 
                        '[&>div]:bg-orange-500': isLowOnChats && !isOutOfChats 
                    }"
                />
                <p v-if="isOutOfChats" class="text-xs text-red-500">
                    Limit chat harian tercapai. Upgrade untuk mendapatkan lebih banyak chat.
                </p>
                <p v-else-if="isLowOnChats" class="text-xs text-orange-500">
                    Sisa {{ chatLimit?.remaining ?? 0 }} chat hari ini.
                </p>
            </div>

            <!-- Upgrade Button (for non-paid users) -->
            <Button v-if="!subscriptionInfo?.is_paid" class="w-full" size="sm" as-child>
                <Link href="/subscription">
                    <Sparkles class="mr-2 h-4 w-4" />
                    Upgrade ke Full Member
                </Link>
            </Button>
        </CardContent>
    </Card>
</template>
