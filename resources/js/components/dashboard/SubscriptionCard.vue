<script setup lang="ts">
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import { Progress } from '@/components/ui/progress';
import { redeemPromo } from '@/routes/subscription';
import type { ChatLimit, SubscriptionInfo } from '@/types/dashboard';
import { Link, useForm } from '@inertiajs/vue3';
import { Calendar, Clock, Crown, MessageSquare, Sparkles, Tag } from 'lucide-vue-next';
import { computed, ref } from 'vue';
import { useI18n } from 'vue-i18n';
import Swal from 'sweetalert2';

const { t } = useI18n();

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
    const locale = useI18n().locale.value === 'id' ? 'id-ID' : 'en-US';
    return new Date(dateStr).toLocaleDateString(locale, {
        day: 'numeric',
        month: 'short',
        year: 'numeric',
    });
};

const getPlanLabel = (plan: string | null) => {
    switch (plan) {
        case 'free_trial':
            return t('dashboard.freeTrial');
        case 'monthly':
            return t('dashboard.planMonthly');
        case 'yearly':
            return t('dashboard.planYearly');
        default:
            return t('dashboard.inactive');
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

const showPromoInput = ref(false);

const promoForm = useForm({
    code: '',
});

const submitPromoCode = () => {
    promoForm.post(redeemPromo().url, {
        preserveScroll: true,
        onSuccess: () => {
            promoForm.reset();
            showPromoInput.value = false;
            Swal.fire({
                icon: 'success',
                title: t('dashboard.promoSuccess'),
                text: t('dashboard.promoSuccessText'),
            });
        },
        onError: () => {
            // Errors will be shown inline
        },
    });
};
</script>

<template>
    <Card>
        <CardHeader class="pb-3">
            <div class="flex items-center justify-between">
                <CardTitle class="text-sm font-medium">{{ $t('dashboard.subscription') }}</CardTitle>
                <Badge v-if="subscriptionInfo?.is_paid" variant="default" class="gap-1">
                    <Crown class="h-3 w-3" />
                    Full Member
                </Badge>
                <Badge v-else-if="subscriptionInfo?.plan === 'free_trial'" variant="secondary" class="gap-1">
                    <Clock class="h-3 w-3" />
                    Trial
                </Badge>
                <Badge v-else variant="outline">{{ $t('dashboard.inactive') }}</Badge>
            </div>
            <CardDescription class="text-xs">
                {{ getPlanLabel(subscriptionInfo?.plan) }}
                <span v-if="subscriptionInfo?.ends_at">
                    • {{ $t('dashboard.endsAt', { date: formatDate(subscriptionInfo.ends_at) }) }}
                </span>
            </CardDescription>
        </CardHeader>
        <CardContent class="space-y-4">
            <!-- Days Remaining (for trial users) -->
            <div v-if="!subscriptionInfo?.is_paid && subscriptionInfo?.days_remaining && subscriptionInfo.days_remaining > 0" class="flex items-center gap-2 text-sm">
                <Calendar class="h-4 w-4 text-muted-foreground" />
                <span>
                    <strong>{{ subscriptionInfo.days_remaining }}</strong> {{ $t('dashboard.daysRemaining', { days: '' }).trim() }}
                </span>
            </div>

            <!-- Chat Usage -->
            <div class="space-y-2">
                <div class="flex items-center justify-between text-sm">
                    <div class="flex items-center gap-2">
                        <MessageSquare class="h-4 w-4 text-muted-foreground" />
                        <span>{{ $t('dashboard.chatToday') }}</span>
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
                    {{ $t('dashboard.chatLimitReached') }}
                </p>
                <p v-else-if="isLowOnChats" class="text-xs text-orange-500">
                    {{ $t('dashboard.chatRemaining', { remaining: chatLimit?.remaining ?? 0 }) }}
                </p>
            </div>

            <!-- Upgrade Button (for non-paid users) -->
            <Button v-if="!subscriptionInfo?.is_paid" class="w-full" size="sm" as-child>
                <Link href="/subscription">
                    <Sparkles class="mr-2 h-4 w-4" />
                    {{ $t('dashboard.upgradeToFull') }}
                </Link>
            </Button>

            <!-- Promo Code -->
            <div class="space-y-2">
                <Button
                    variant="ghost"
                    size="sm"
                    class="w-full text-muted-foreground"
                    @click="showPromoInput = !showPromoInput"
                >
                    <Tag class="mr-2 h-3.5 w-3.5" />
                    {{ showPromoInput ? $t('dashboard.closePromoCode') : $t('dashboard.havePromoCode') }}
                </Button>

                <div v-if="showPromoInput" class="space-y-2">
                    <div class="flex gap-2">
                        <Input
                            v-model="promoForm.code"
                            :placeholder="$t('dashboard.enterPromoCode')"
                            class="h-8 text-sm uppercase"
                            :disabled="promoForm.processing"
                            @keyup.enter="submitPromoCode"
                        />
                        <Button
                            size="sm"
                            class="h-8 px-3 shrink-0"
                            :disabled="promoForm.processing || !promoForm.code"
                            @click="submitPromoCode"
                        >
                            {{ promoForm.processing ? '...' : $t('dashboard.usePromo') }}
                        </Button>
                    </div>
                    <p v-if="promoForm.errors.code" class="text-xs text-red-500">
                        {{ promoForm.errors.code }}
                    </p>
                </div>
            </div>
        </CardContent>
    </Card>
</template>
