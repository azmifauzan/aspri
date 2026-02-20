<script setup lang="ts">
import InputError from '@/components/InputError.vue';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Spinner } from '@/components/ui/spinner';
import AppLayout from '@/layouts/AppLayout.vue';
import subscription from '@/routes/subscription';
import type { BreadcrumbItem } from '@/types';
import type { PromoCodeRedemption } from '@/types/admin';
import { Head, router, useForm } from '@inertiajs/vue3';
import {
    AlertCircle,
    Banknote,
    Calendar,
    Check,
    Clock,
    CreditCard,
    Crown,
    Gift,
    MessageSquare,
    Sparkles,
    Upload,
    X,
} from 'lucide-vue-next';
import { computed, ref } from 'vue';
import { useI18n } from 'vue-i18n';
import Swal from 'sweetalert2';

const { t, locale } = useI18n();

interface PricingInfo {
    monthly_price: number;
    yearly_price: number;
    free_trial_days: number;
    free_trial_daily_chat_limit: number;
    full_member_daily_chat_limit: number;
}

interface BankInfo {
    bank_name: string;
    account_number: string;
    account_name: string;
}

interface SubscriptionInfo {
    status: string;
    plan: string | null;
    ends_at: string | null;
    days_remaining: number;
    is_paid: boolean;
}

interface PaymentProof {
    id: number;
    plan_type: string;
    amount: number;
    status: string;
    created_at: string;
    admin_notes: string | null;
}

const props = defineProps<{
    pricing: PricingInfo;
    bankInfo: BankInfo;
    subscriptionInfo: SubscriptionInfo;
    pendingPayments: PaymentProof[];
    paymentHistory: PaymentProof[];
    promoRedemptions: PromoCodeRedemption[];
}>();

const breadcrumbs = computed<BreadcrumbItem[]>(() => [
    { title: t('subscription.title'), href: subscription.index().url },
]);

const selectedPlan = ref<'monthly' | 'yearly'>('monthly');
const showPaymentForm = ref(false);

const paymentForm = useForm({
    plan_type: 'monthly' as 'monthly' | 'yearly',
    transfer_proof: null as File | null,
    bank_name: '',
    account_name: '',
    transfer_date: '',
});

const promoForm = useForm({
    code: '',
});

const formatCurrency = (value: number) => {
    return new Intl.NumberFormat(locale.value === 'id' ? 'id-ID' : 'en-US').format(value);
};

const formatDate = (dateStr: string) => {
    return new Date(dateStr).toLocaleDateString(locale.value === 'id' ? 'id-ID' : 'en-US', {
        day: 'numeric',
        month: 'long',
        year: 'numeric',
    });
};

const selectedPrice = computed(() => {
    return selectedPlan.value === 'yearly' ? props.pricing.yearly_price : props.pricing.monthly_price;
});

const yearlySavings = computed(() => {
    const monthlyTotal = props.pricing.monthly_price * 12;
    return monthlyTotal - props.pricing.yearly_price;
});

const handleFileSelect = (event: Event) => {
    const target = event.target as HTMLInputElement;
    if (target.files && target.files[0]) {
        paymentForm.transfer_proof = target.files[0];
    }
};

const startUpgrade = (plan: 'monthly' | 'yearly') => {
    selectedPlan.value = plan;
    paymentForm.plan_type = plan;
    showPaymentForm.value = true;
};

const submitPayment = () => {
    paymentForm.post(subscription.submitPayment().url, {
        preserveScroll: true,
        onSuccess: () => {
            paymentForm.reset();
            showPaymentForm.value = false;
            Swal.fire({
                icon: 'success',
                title: t('subscription.paymentSuccessTitle'),
                text: t('subscription.paymentSuccessText'),
            });
        },
        onError: () => {
            Swal.fire({
                icon: 'error',
                title: t('subscription.paymentErrorTitle'),
                text: t('subscription.paymentErrorText'),
            });
        },
    });
};

const cancelPayment = (paymentId: number) => {
    Swal.fire({
        title: t('subscription.cancelPaymentTitle'),
        text: t('subscription.cancelPaymentText'),
        icon: 'warning',
        showCancelButton: true,
        confirmButtonText: t('subscription.cancelPaymentConfirm'),
        cancelButtonText: t('subscription.cancelPaymentCancel'),
    }).then((result) => {
        if (result.isConfirmed) {
            router.delete(subscription.cancelPayment(paymentId).url, {
                preserveScroll: true,
            });
        }
    });
};

const submitPromoCode = () => {
    promoForm.post(subscription.redeemPromo().url, {
        preserveScroll: true,
        onSuccess: () => {
            promoForm.reset();
            Swal.fire({
                icon: 'success',
                title: t('subscription.promoSuccessTitle'),
                text: t('subscription.promoSuccessText'),
            });
        },
        onError: () => {
            // Errors will be shown inline
        },
    });
};

const getStatusBadge = (status: string) => {
    switch (status) {
        case 'pending':
            return { variant: 'secondary' as const, label: t('subscription.pendingVerification') };
        case 'approved':
            return { variant: 'default' as const, label: t('subscription.approved') };
        case 'rejected':
            return { variant: 'destructive' as const, label: t('subscription.rejected') };
        default:
            return { variant: 'outline' as const, label: status };
    }
};

const getPlanLabel = (plan: string) => {
    switch (plan) {
        case 'free_trial':
            return t('subscription.freeTrial');
        case 'monthly':
            return t('subscription.planMonthly');
        case 'yearly':
            return t('subscription.planYearly');
        case 'none':
            return t('subscription.freeTrial');
        default:
            return plan;
    }
};
</script>

<template>
    <Head :title="$t('subscription.title')" />

    <AppLayout :breadcrumbs="breadcrumbs">
        <div class="flex h-full flex-1 flex-col gap-6 p-4 md:p-6">
            <!-- Current Subscription Status -->
            <Card>
                <CardHeader>
                    <CardTitle class="flex items-center gap-2">
                        <CreditCard class="h-5 w-5" />
                        {{ $t('subscription.subscriptionStatus') }}
                    </CardTitle>
                </CardHeader>
                <CardContent>
                    <div class="flex flex-col md:flex-row md:items-center md:justify-between gap-4">
                        <div class="space-y-2">
                            <div class="flex items-center gap-2">
                                <span class="text-lg font-semibold">{{ getPlanLabel(subscriptionInfo.plan || 'none') }}</span>
                                <Badge v-if="subscriptionInfo.is_paid" variant="default">
                                    <Crown class="mr-1 h-3 w-3" />
                                    {{ $t('subscription.fullMember') }}
                                </Badge>
                                <Badge v-else-if="subscriptionInfo.plan === 'free_trial'" variant="secondary">
                                    <Clock class="mr-1 h-3 w-3" />
                                    Trial
                                </Badge>
                            </div>
                            <div v-if="subscriptionInfo.ends_at" class="flex items-center gap-2 text-sm">
                                <Calendar class="h-4 w-4 text-muted-foreground" />
                                <span class="text-muted-foreground">{{ $t('subscription.validUntilLabel') }}</span>
                                <span class="font-medium">{{ formatDate(subscriptionInfo.ends_at) }}</span>
                                <span v-if="subscriptionInfo.days_remaining > 0" class="text-orange-500 font-medium">
                                    ({{ $t('subscription.daysLeft', { days: subscriptionInfo.days_remaining }) }})
                                </span>
                                <span v-else class="text-red-500 font-medium">
                                    ({{ $t('subscription.alreadyExpired') }})
                                </span>
                            </div>
                            <div v-else-if="subscriptionInfo.plan && subscriptionInfo.plan !== 'none'" class="flex items-center gap-2 text-sm text-muted-foreground">
                                <Calendar class="h-4 w-4" />
                                {{ $t('subscription.noTimeLimit') }}
                            </div>
                            <div v-else class="flex items-center gap-2 text-sm text-muted-foreground">
                                <AlertCircle class="h-4 w-4" />
                                {{ $t('subscription.freeTrialUpgradeHint') }}
                            </div>
                        </div>
                        <div class="flex items-center gap-2 text-sm">
                            <MessageSquare class="h-4 w-4" />
                            {{ $t('subscription.dailyChatLimit') }}
                            <span class="font-semibold">
                                {{ subscriptionInfo.is_paid ? pricing.full_member_daily_chat_limit : pricing.free_trial_daily_chat_limit }} {{ $t('subscription.messages') }}
                            </span>
                        </div>
                    </div>
                </CardContent>
            </Card>

            <!-- Pending Payments Alert -->
            <Card v-if="pendingPayments.length > 0" class="border-orange-500/50 bg-orange-500/5">
                <CardContent class="pt-6">
                    <div class="flex items-start gap-4">
                        <AlertCircle class="h-5 w-5 text-orange-500 flex-shrink-0 mt-0.5" />
                        <div class="space-y-2">
                            <p class="font-medium">{{ $t('subscription.pendingPaymentTitle') }}</p>
                            <p class="text-sm text-muted-foreground">
                                {{ $t('subscription.pendingPaymentDesc', { count: pendingPayments.length }) }}
                            </p>
                            <div class="flex flex-wrap gap-2 mt-2">
                                <div v-for="payment in pendingPayments" :key="payment.id" class="flex items-center gap-2 rounded-lg border bg-background px-3 py-2 text-sm">
                                    <span>{{ payment.plan_type === 'yearly' ? $t('subscription.planYearly') : $t('subscription.planMonthly') }}</span>
                                    <span class="text-muted-foreground">Rp {{ formatCurrency(payment.amount) }}</span>
                                    <Button variant="ghost" size="sm" class="h-6 w-6 p-0" @click="cancelPayment(payment.id)">
                                        <X class="h-4 w-4" />
                                    </Button>
                                </div>
                            </div>
                        </div>
                    </div>
                </CardContent>
            </Card>

            <!-- Pricing Cards -->
            <div v-if="!showPaymentForm">
                <h2 class="text-xl font-semibold mb-4">{{ $t('subscription.choosePlan') }}</h2>
                <div class="grid gap-6 md:grid-cols-2">
                    <!-- Monthly Plan -->
                    <Card class="relative" :class="{ 'border-primary': selectedPlan === 'monthly' }">
                        <CardHeader>
                            <CardTitle>{{ $t('subscription.monthly') }}</CardTitle>
                            <CardDescription>{{ $t('subscription.monthlyFlexible') }}</CardDescription>
                        </CardHeader>
                        <CardContent class="space-y-4">
                            <div class="text-3xl font-bold">
                                Rp {{ formatCurrency(pricing.monthly_price) }}
                                <span class="text-base font-normal text-muted-foreground">{{ $t('subscription.perMonth') }}</span>
                            </div>
                            <ul class="space-y-2 text-sm">
                                <li class="flex items-center gap-2">
                                    <Check class="h-4 w-4 text-green-500" />
                                    {{ $t('subscription.chatPerDay', { limit: pricing.full_member_daily_chat_limit }) }}
                                </li>
                                <li class="flex items-center gap-2">
                                    <Check class="h-4 w-4 text-green-500" />
                                    {{ $t('subscription.allPremiumFeatures') }}
                                </li>
                                <li class="flex items-center gap-2">
                                    <Check class="h-4 w-4 text-green-500" />
                                    {{ $t('subscription.prioritySupport') }}
                                </li>
                            </ul>
                            <Button class="w-full" @click="startUpgrade('monthly')" :disabled="pendingPayments.length > 0">
                                <Sparkles class="mr-2 h-4 w-4" />
                                {{ $t('subscription.chooseMonthly') }}
                            </Button>
                        </CardContent>
                    </Card>

                    <!-- Yearly Plan -->
                    <Card class="relative" :class="{ 'border-primary': selectedPlan === 'yearly' }">
                        <div class="absolute -top-3 right-4">
                            <Badge variant="default" class="bg-green-500">
                                {{ $t('subscription.savingsAmount', { amount: formatCurrency(yearlySavings) }) }}
                            </Badge>
                        </div>
                        <CardHeader>
                            <CardTitle>{{ $t('subscription.yearly') }}</CardTitle>
                            <CardDescription>{{ $t('subscription.yearlyMoreSaving') }}</CardDescription>
                        </CardHeader>
                        <CardContent class="space-y-4">
                            <div class="text-3xl font-bold">
                                Rp {{ formatCurrency(pricing.yearly_price) }}
                                <span class="text-base font-normal text-muted-foreground">{{ $t('subscription.perYear') }}</span>
                            </div>
                            <p class="text-sm text-muted-foreground">
                                {{ $t('subscription.equivalent', { price: formatCurrency(Math.round(pricing.yearly_price / 12)) }) }}
                            </p>
                            <ul class="space-y-2 text-sm">
                                <li class="flex items-center gap-2">
                                    <Check class="h-4 w-4 text-green-500" />
                                    {{ $t('subscription.chatPerDay', { limit: pricing.full_member_daily_chat_limit }) }}
                                </li>
                                <li class="flex items-center gap-2">
                                    <Check class="h-4 w-4 text-green-500" />
                                    {{ $t('subscription.allPremiumFeatures') }}
                                </li>
                                <li class="flex items-center gap-2">
                                    <Check class="h-4 w-4 text-green-500" />
                                    {{ $t('subscription.prioritySupport') }}
                                </li>
                                <li class="flex items-center gap-2">
                                    <Crown class="h-4 w-4 text-yellow-500" />
                                    {{ $t('subscription.save', { percent: Math.round((yearlySavings / (pricing.monthly_price * 12)) * 100) }) }}
                                </li>
                            </ul>
                            <Button class="w-full" variant="default" @click="startUpgrade('yearly')" :disabled="pendingPayments.length > 0">
                                <Crown class="mr-2 h-4 w-4" />
                                {{ $t('subscription.chooseYearly') }}
                            </Button>
                        </CardContent>
                    </Card>
                </div>
            </div>

            <!-- Payment Form -->
            <Card v-if="showPaymentForm">
                <CardHeader>
                    <div class="flex items-center justify-between">
                        <div>
                            <CardTitle>{{ $t('subscription.uploadTransferProof') }}</CardTitle>
                            <CardDescription>
                                {{ $t('subscription.planSummary', { plan: selectedPlan === 'yearly' ? $t('subscription.planYearly') : $t('subscription.planMonthly'), price: formatCurrency(selectedPrice) }) }}
                            </CardDescription>
                        </div>
                        <Button variant="ghost" size="sm" @click="showPaymentForm = false">
                            <X class="h-4 w-4" />
                        </Button>
                    </div>
                </CardHeader>
                <CardContent>
                    <div class="grid gap-6 md:grid-cols-2">
                        <!-- Bank Info -->
                        <div class="space-y-4">
                            <h4 class="font-medium flex items-center gap-2">
                                <Banknote class="h-4 w-4" />
                                {{ $t('subscription.transferToAccount') }}
                            </h4>
                            <div class="rounded-lg border bg-muted/50 p-4 space-y-2">
                                <div class="flex justify-between">
                                    <span class="text-muted-foreground">{{ $t('subscription.bank') }}</span>
                                    <span class="font-medium">{{ bankInfo.bank_name || '-' }}</span>
                                </div>
                                <div class="flex justify-between">
                                    <span class="text-muted-foreground">{{ $t('subscription.accountNumber') }}</span>
                                    <span class="font-mono font-medium">{{ bankInfo.account_number || '-' }}</span>
                                </div>
                                <div class="flex justify-between">
                                    <span class="text-muted-foreground">{{ $t('subscription.accountHolder') }}</span>
                                    <span class="font-medium">{{ bankInfo.account_name || '-' }}</span>
                                </div>
                                <div class="flex justify-between border-t pt-2 mt-2">
                                    <span class="text-muted-foreground">{{ $t('subscription.transferAmount') }}</span>
                                    <span class="font-bold text-lg">Rp {{ formatCurrency(selectedPrice) }}</span>
                                </div>
                            </div>
                        </div>

                        <!-- Upload Form -->
                        <form class="space-y-4" @submit.prevent="submitPayment">
                            <div class="space-y-2">
                                <Label for="transfer_proof">{{ $t('subscription.transferProofLabel') }}</Label>
                                <div class="flex items-center gap-2">
                                    <Input 
                                        id="transfer_proof" 
                                        type="file" 
                                        accept="image/*"
                                        @change="handleFileSelect"
                                        required
                                    />
                                </div>
                                <p class="text-xs text-muted-foreground">{{ $t('subscription.transferProofFormat') }}</p>
                                <InputError :message="paymentForm.errors.transfer_proof" />
                            </div>

                            <div class="space-y-2">
                                <Label for="bank_name">{{ $t('subscription.senderBank') }}</Label>
                                <Input id="bank_name" v-model="paymentForm.bank_name" :placeholder="$t('subscription.senderBankPlaceholder')" />
                                <InputError :message="paymentForm.errors.bank_name" />
                            </div>

                            <div class="space-y-2">
                                <Label for="account_name">{{ $t('subscription.senderName') }}</Label>
                                <Input id="account_name" v-model="paymentForm.account_name" :placeholder="$t('subscription.senderNamePlaceholder')" />
                                <InputError :message="paymentForm.errors.account_name" />
                            </div>

                            <div class="space-y-2">
                                <Label for="transfer_date">{{ $t('subscription.transferDate') }}</Label>
                                <Input id="transfer_date" v-model="paymentForm.transfer_date" type="date" />
                                <InputError :message="paymentForm.errors.transfer_date" />
                            </div>

                            <Button type="submit" class="w-full" :disabled="paymentForm.processing">
                                <Spinner v-if="paymentForm.processing" class="mr-2" />
                                <Upload v-else class="mr-2 h-4 w-4" />
                                {{ $t('subscription.submitTransferProof') }}
                            </Button>
                        </form>
                    </div>
                </CardContent>
            </Card>

            <!-- Promo Code Redemption -->
            <Card>
                <CardHeader>
                    <CardTitle class="flex items-center gap-2">
                        <Gift class="h-5 w-5" />
                        {{ $t('subscription.usePromoCode') }}
                    </CardTitle>
                    <CardDescription>
                        {{ $t('subscription.promoCodeDesc') }}
                    </CardDescription>
                </CardHeader>
                <CardContent>
                    <form @submit.prevent="submitPromoCode">
                        <div class="space-y-2">
                            <Label for="promo_code">{{ $t('subscription.promoCode') }}</Label>
                            <div class="flex gap-2">
                                <Input
                                    id="promo_code"
                                    v-model="promoForm.code"
                                    :placeholder="$t('subscription.enterPromoCode')"
                                    class="flex-1 font-mono uppercase"
                                    required
                                />
                                <Button type="submit" :disabled="promoForm.processing || !promoForm.code">
                                    <Spinner v-if="promoForm.processing" class="mr-2" />
                                    <Gift v-else class="mr-2 h-4 w-4" />
                                    {{ $t('subscription.useCode') }}
                                </Button>
                            </div>
                            <InputError :message="promoForm.errors.code" />
                        </div>
                    </form>

                    <!-- Promo Redemption History -->
                    <div v-if="promoRedemptions.length > 0" class="mt-6 space-y-3">
                        <h4 class="text-sm font-medium text-muted-foreground">{{ $t('subscription.promoHistory') }}</h4>
                        <div
                            v-for="redemption in promoRedemptions"
                            :key="redemption.id"
                            class="flex items-center justify-between rounded-lg border p-3"
                        >
                            <div class="space-y-1">
                                <div class="flex items-center gap-2">
                                    <code class="rounded bg-muted px-2 py-0.5 text-sm font-mono">
                                        {{ redemption.promo_code?.code }}
                                    </code>
                                    <Badge variant="default">{{ $t('subscription.daysAdded', { days: redemption.days_added }) }}</Badge>
                                </div>
                                <div class="text-sm text-muted-foreground">
                                    {{ formatDate(redemption.created_at) }}
                                </div>
                            </div>
                            <div class="text-right text-sm text-muted-foreground">
                                <div v-if="redemption.new_ends_at">
                                    {{ $t('subscription.untilDate', { date: formatDate(redemption.new_ends_at) }) }}
                                </div>
                            </div>
                        </div>
                    </div>
                </CardContent>
            </Card>

            <!-- Payment History -->
            <Card v-if="paymentHistory.length > 0">
                <CardHeader>
                    <CardTitle>{{ $t('subscription.paymentHistory') }}</CardTitle>
                </CardHeader>
                <CardContent>
                    <div class="space-y-3">
                        <div 
                            v-for="payment in paymentHistory" 
                            :key="payment.id"
                            class="flex items-center justify-between rounded-lg border p-3"
                        >
                            <div class="space-y-1">
                                <div class="font-medium">
                                    {{ $t('subscription.planPackage', { plan: payment.plan_type === 'yearly' ? $t('subscription.planYearly') : $t('subscription.planMonthly') }) }}
                                </div>
                                <div class="text-sm text-muted-foreground">
                                    {{ formatDate(payment.created_at) }} • Rp {{ formatCurrency(payment.amount) }}
                                </div>
                                <p v-if="payment.admin_notes && payment.status === 'rejected'" class="text-sm text-red-500">
                                    {{ payment.admin_notes }}
                                </p>
                            </div>
                            <Badge :variant="getStatusBadge(payment.status).variant">
                                {{ getStatusBadge(payment.status).label }}
                            </Badge>
                        </div>
                    </div>
                </CardContent>
            </Card>
        </div>
    </AppLayout>
</template>
