<script setup lang="ts">
import { usePage } from '@inertiajs/vue3';
import { Calendar, Crown, MessageSquare } from 'lucide-vue-next';
import { computed } from 'vue';
import { Badge } from '@/components/ui/badge';
import { Separator } from '@/components/ui/separator';
import { useI18n } from 'vue-i18n';

const { t } = useI18n();

const page = usePage();
const subscriptionInfo = computed(() => page.props.auth.subscriptionInfo);
const chatLimit = computed(() => page.props.auth.chatLimit);

const membershipBadgeVariant = computed(() => {
    if (!subscriptionInfo.value) return 'secondary';
    
    if (subscriptionInfo.value.is_paid) {
        return 'default';
    }
    
    return 'secondary';
});

const membershipLabel = computed(() => {
    if (!subscriptionInfo.value || subscriptionInfo.value.status === 'none') {
        return t('common.freeTrial');
    }
    
    if (subscriptionInfo.value.is_paid) {
        return t('common.premiumMember');
    }
    
    return t('common.freeTrial');
});

const expiryText = computed(() => {
    if (!subscriptionInfo.value || subscriptionInfo.value.status === 'none') {
        return null;
    }
    
    const daysRemaining = subscriptionInfo.value.days_remaining;
    
    if (daysRemaining === 0) {
        return t('common.expiresToday');
    } else if (daysRemaining === 1) {
        return t('common.expiresIn1Day');
    } else if (daysRemaining > 0) {
        return t('common.expiresInDays', { days: daysRemaining });
    } else {
        return t('common.expired');
    }
});

const chatUsageText = computed(() => {
    if (!chatLimit.value) return '0 / 0';
    return `${chatLimit.value.used} / ${chatLimit.value.limit}`;
});

const chatUsagePercentage = computed(() => {
    if (!chatLimit.value || chatLimit.value.limit === 0) return 0;
    return Math.round((chatLimit.value.used / chatLimit.value.limit) * 100);
});

const chatUsageColor = computed(() => {
    const percentage = chatUsagePercentage.value;
    if (percentage >= 90) return 'text-red-500 dark:text-red-400';
    if (percentage >= 70) return 'text-yellow-500 dark:text-yellow-400';
    return 'text-green-500 dark:text-green-400';
});
</script>

<template>
    <div class="border-b border-sidebar-border/70 bg-background px-6 py-3 md:px-4">
        <div class="flex flex-wrap items-center justify-between gap-4">
            <!-- Membership Status -->
            <div class="flex items-center gap-3">
                <div class="flex items-center gap-2">
                    <Crown class="h-4 w-4 text-muted-foreground" />
                    <span class="text-sm font-medium">{{ $t('common.statusLabel') }}</span>
                </div>
                <div class="flex items-center gap-2">
                    <Badge :variant="membershipBadgeVariant" class="gap-1">
                        {{ membershipLabel }}
                    </Badge>
                    <span v-if="expiryText" class="text-xs text-muted-foreground">
                        <Separator orientation="vertical" class="mx-2 inline-block h-3" />
                        <Calendar class="mr-1 inline-block h-3 w-3" />
                        {{ expiryText }}
                    </span>
                </div>
            </div>

            <!-- Chat Usage -->
            <div class="flex items-center gap-2">
                <MessageSquare class="h-4 w-4 text-muted-foreground" />
                <span class="text-sm font-medium">{{ $t('common.chatUsageLabel') }}</span>
                <span :class="['text-sm font-semibold', chatUsageColor]">
                    {{ chatUsageText }}
                </span>
                <span class="text-xs text-muted-foreground">({{ chatUsagePercentage }}%)</span>
            </div>
        </div>
    </div>
</template>
