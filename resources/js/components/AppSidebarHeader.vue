<script setup lang="ts">
import Breadcrumbs from '@/components/Breadcrumbs.vue';
import { Badge } from '@/components/ui/badge';
import { Separator } from '@/components/ui/separator';
import { SidebarTrigger } from '@/components/ui/sidebar';
import type { BreadcrumbItem } from '@/types';
import { usePage } from '@inertiajs/vue3';
import LanguageToggle from '@/components/LanguageToggle.vue';
import { Calendar, Crown, MessageSquare } from 'lucide-vue-next';
import { computed } from 'vue';
import { useI18n } from 'vue-i18n';

const { t } = useI18n();

withDefaults(
    defineProps<{
        breadcrumbs?: BreadcrumbItem[];
    }>(),
    {
        breadcrumbs: () => [],
    },
);

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
    <header
        class="flex h-16 shrink-0 items-center justify-between gap-4 border-b border-sidebar-border/70 px-6 transition-[width,height] ease-linear group-has-data-[collapsible=icon]/sidebar-wrapper:h-12 md:px-4"
    >
        <div class="flex items-center gap-2">
            <SidebarTrigger class="-ml-1" />
            <template v-if="breadcrumbs && breadcrumbs.length > 0">
                <Breadcrumbs :breadcrumbs="breadcrumbs" />
            </template>
        </div>
        
        <!-- Membership Status, Chat Usage & Language Toggle -->
        <div class="flex flex-wrap items-center gap-4 text-sm">
            <!-- Language Toggle -->
            <LanguageToggle />
            <!-- Membership Status -->
            <div class="flex items-center gap-2">
                <Crown class="h-3.5 w-3.5 text-muted-foreground" />
                <Badge :variant="membershipBadgeVariant" class="text-xs">
                    {{ membershipLabel }}
                </Badge>
                <span v-if="expiryText" class="hidden text-xs text-muted-foreground lg:inline-flex items-center gap-1">
                    <Separator orientation="vertical" class="mx-1 h-3" />
                    <Calendar class="h-3 w-3" />
                    {{ expiryText }}
                </span>
            </div>

            <!-- Chat Usage -->
            <div class="flex items-center gap-1.5">
                <MessageSquare class="h-3.5 w-3.5 text-muted-foreground" />
                <span class="hidden text-xs font-medium sm:inline">{{ $t('common.chatLabel') }}</span>
                <span :class="['text-xs font-semibold', chatUsageColor]">
                    {{ chatUsageText }}
                </span>
                <span class="hidden text-xs text-muted-foreground md:inline">({{ chatUsagePercentage }}%)</span>
            </div>
        </div>
    </header>
</template>
