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
import { Head, router, useForm } from '@inertiajs/vue3';
import {
    AlertCircle,
    Banknote,
    Calendar,
    Check,
    Clock,
    CreditCard,
    Crown,
    MessageSquare,
    Sparkles,
    Upload,
    X,
} from 'lucide-vue-next';
import { computed, ref } from 'vue';
import Swal from 'sweetalert2';

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
}>();

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Subscription', href: subscription.index().url },
];

const selectedPlan = ref<'monthly' | 'yearly'>('monthly');
const showPaymentForm = ref(false);

const paymentForm = useForm({
    plan_type: 'monthly' as 'monthly' | 'yearly',
    transfer_proof: null as File | null,
    bank_name: '',
    account_name: '',
    transfer_date: '',
});

const formatCurrency = (value: number) => {
    return new Intl.NumberFormat('id-ID').format(value);
};

const formatDate = (dateStr: string) => {
    return new Date(dateStr).toLocaleDateString('id-ID', {
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
                title: 'Berhasil!',
                text: 'Bukti pembayaran berhasil dikirim. Kami akan memverifikasi dalam 1x24 jam.',
            });
        },
        onError: () => {
            Swal.fire({
                icon: 'error',
                title: 'Gagal',
                text: 'Terjadi kesalahan saat mengirim bukti pembayaran',
            });
        },
    });
};

const cancelPayment = (paymentId: number) => {
    Swal.fire({
        title: 'Batalkan Pembayaran?',
        text: 'Bukti pembayaran akan dihapus dan tidak dapat dikembalikan.',
        icon: 'warning',
        showCancelButton: true,
        confirmButtonText: 'Ya, Batalkan',
        cancelButtonText: 'Tidak',
    }).then((result) => {
        if (result.isConfirmed) {
            router.delete(subscription.cancelPayment(paymentId).url, {
                preserveScroll: true,
            });
        }
    });
};

const getStatusBadge = (status: string) => {
    switch (status) {
        case 'pending':
            return { variant: 'secondary' as const, label: 'Menunggu Verifikasi' };
        case 'approved':
            return { variant: 'default' as const, label: 'Disetujui' };
        case 'rejected':
            return { variant: 'destructive' as const, label: 'Ditolak' };
        default:
            return { variant: 'outline' as const, label: status };
    }
};

const getPlanLabel = (plan: string) => {
    switch (plan) {
        case 'free_trial':
            return 'Free Trial';
        case 'monthly':
            return 'Bulanan';
        case 'yearly':
            return 'Tahunan';
        default:
            return plan;
    }
};
</script>

<template>
    <Head title="Subscription" />

    <AppLayout :breadcrumbs="breadcrumbs">
        <div class="flex h-full flex-1 flex-col gap-6 p-4 md:p-6">
            <!-- Current Subscription Status -->
            <Card>
                <CardHeader>
                    <CardTitle class="flex items-center gap-2">
                        <CreditCard class="h-5 w-5" />
                        Status Langganan Anda
                    </CardTitle>
                </CardHeader>
                <CardContent>
                    <div class="flex flex-col md:flex-row md:items-center md:justify-between gap-4">
                        <div class="space-y-2">
                            <div class="flex items-center gap-2">
                                <span class="text-lg font-semibold">{{ getPlanLabel(subscriptionInfo.plan || 'none') }}</span>
                                <Badge v-if="subscriptionInfo.is_paid" variant="default">
                                    <Crown class="mr-1 h-3 w-3" />
                                    Full Member
                                </Badge>
                                <Badge v-else-if="subscriptionInfo.plan === 'free_trial'" variant="secondary">
                                    <Clock class="mr-1 h-3 w-3" />
                                    Trial
                                </Badge>
                            </div>
                            <div v-if="subscriptionInfo.ends_at" class="flex items-center gap-2 text-sm text-muted-foreground">
                                <Calendar class="h-4 w-4" />
                                Berakhir: {{ formatDate(subscriptionInfo.ends_at) }}
                                <span v-if="subscriptionInfo.days_remaining > 0" class="text-orange-500">
                                    ({{ subscriptionInfo.days_remaining }} hari lagi)
                                </span>
                            </div>
                        </div>
                        <div class="flex items-center gap-2 text-sm">
                            <MessageSquare class="h-4 w-4" />
                            Limit chat harian:
                            <span class="font-semibold">
                                {{ subscriptionInfo.is_paid ? pricing.full_member_daily_chat_limit : pricing.free_trial_daily_chat_limit }} pesan
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
                            <p class="font-medium">Pembayaran Menunggu Verifikasi</p>
                            <p class="text-sm text-muted-foreground">
                                Anda memiliki {{ pendingPayments.length }} bukti pembayaran yang sedang kami verifikasi. 
                                Proses verifikasi membutuhkan waktu 1x24 jam.
                            </p>
                            <div class="flex flex-wrap gap-2 mt-2">
                                <div v-for="payment in pendingPayments" :key="payment.id" class="flex items-center gap-2 rounded-lg border bg-background px-3 py-2 text-sm">
                                    <span>{{ payment.plan_type === 'yearly' ? 'Tahunan' : 'Bulanan' }}</span>
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
                <h2 class="text-xl font-semibold mb-4">Pilih Paket</h2>
                <div class="grid gap-6 md:grid-cols-2">
                    <!-- Monthly Plan -->
                    <Card class="relative" :class="{ 'border-primary': selectedPlan === 'monthly' }">
                        <CardHeader>
                            <CardTitle>Bulanan</CardTitle>
                            <CardDescription>Fleksibel tanpa komitmen jangka panjang</CardDescription>
                        </CardHeader>
                        <CardContent class="space-y-4">
                            <div class="text-3xl font-bold">
                                Rp {{ formatCurrency(pricing.monthly_price) }}
                                <span class="text-base font-normal text-muted-foreground">/bulan</span>
                            </div>
                            <ul class="space-y-2 text-sm">
                                <li class="flex items-center gap-2">
                                    <Check class="h-4 w-4 text-green-500" />
                                    {{ pricing.full_member_daily_chat_limit }} chat AI per hari
                                </li>
                                <li class="flex items-center gap-2">
                                    <Check class="h-4 w-4 text-green-500" />
                                    Semua fitur premium
                                </li>
                                <li class="flex items-center gap-2">
                                    <Check class="h-4 w-4 text-green-500" />
                                    Support prioritas
                                </li>
                            </ul>
                            <Button class="w-full" @click="startUpgrade('monthly')" :disabled="pendingPayments.length > 0">
                                <Sparkles class="mr-2 h-4 w-4" />
                                Pilih Bulanan
                            </Button>
                        </CardContent>
                    </Card>

                    <!-- Yearly Plan -->
                    <Card class="relative" :class="{ 'border-primary': selectedPlan === 'yearly' }">
                        <div class="absolute -top-3 right-4">
                            <Badge variant="default" class="bg-green-500">
                                Hemat Rp {{ formatCurrency(yearlySavings) }}
                            </Badge>
                        </div>
                        <CardHeader>
                            <CardTitle>Tahunan</CardTitle>
                            <CardDescription>Lebih hemat untuk pengguna setia</CardDescription>
                        </CardHeader>
                        <CardContent class="space-y-4">
                            <div class="text-3xl font-bold">
                                Rp {{ formatCurrency(pricing.yearly_price) }}
                                <span class="text-base font-normal text-muted-foreground">/tahun</span>
                            </div>
                            <p class="text-sm text-muted-foreground">
                                Setara Rp {{ formatCurrency(Math.round(pricing.yearly_price / 12)) }}/bulan
                            </p>
                            <ul class="space-y-2 text-sm">
                                <li class="flex items-center gap-2">
                                    <Check class="h-4 w-4 text-green-500" />
                                    {{ pricing.full_member_daily_chat_limit }} chat AI per hari
                                </li>
                                <li class="flex items-center gap-2">
                                    <Check class="h-4 w-4 text-green-500" />
                                    Semua fitur premium
                                </li>
                                <li class="flex items-center gap-2">
                                    <Check class="h-4 w-4 text-green-500" />
                                    Support prioritas
                                </li>
                                <li class="flex items-center gap-2">
                                    <Crown class="h-4 w-4 text-yellow-500" />
                                    Hemat {{ Math.round((yearlySavings / (pricing.monthly_price * 12)) * 100) }}%
                                </li>
                            </ul>
                            <Button class="w-full" variant="default" @click="startUpgrade('yearly')" :disabled="pendingPayments.length > 0">
                                <Crown class="mr-2 h-4 w-4" />
                                Pilih Tahunan
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
                            <CardTitle>Upload Bukti Transfer</CardTitle>
                            <CardDescription>
                                Paket {{ selectedPlan === 'yearly' ? 'Tahunan' : 'Bulanan' }} - Rp {{ formatCurrency(selectedPrice) }}
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
                                Transfer ke Rekening
                            </h4>
                            <div class="rounded-lg border bg-muted/50 p-4 space-y-2">
                                <div class="flex justify-between">
                                    <span class="text-muted-foreground">Bank</span>
                                    <span class="font-medium">{{ bankInfo.bank_name || '-' }}</span>
                                </div>
                                <div class="flex justify-between">
                                    <span class="text-muted-foreground">No. Rekening</span>
                                    <span class="font-mono font-medium">{{ bankInfo.account_number || '-' }}</span>
                                </div>
                                <div class="flex justify-between">
                                    <span class="text-muted-foreground">Atas Nama</span>
                                    <span class="font-medium">{{ bankInfo.account_name || '-' }}</span>
                                </div>
                                <div class="flex justify-between border-t pt-2 mt-2">
                                    <span class="text-muted-foreground">Jumlah Transfer</span>
                                    <span class="font-bold text-lg">Rp {{ formatCurrency(selectedPrice) }}</span>
                                </div>
                            </div>
                        </div>

                        <!-- Upload Form -->
                        <form class="space-y-4" @submit.prevent="submitPayment">
                            <div class="space-y-2">
                                <Label for="transfer_proof">Bukti Transfer *</Label>
                                <div class="flex items-center gap-2">
                                    <Input 
                                        id="transfer_proof" 
                                        type="file" 
                                        accept="image/*"
                                        @change="handleFileSelect"
                                        required
                                    />
                                </div>
                                <p class="text-xs text-muted-foreground">Format: JPG, PNG. Max 5MB</p>
                                <InputError :message="paymentForm.errors.transfer_proof" />
                            </div>

                            <div class="space-y-2">
                                <Label for="bank_name">Bank Pengirim</Label>
                                <Input id="bank_name" v-model="paymentForm.bank_name" placeholder="BCA, Mandiri, dll" />
                                <InputError :message="paymentForm.errors.bank_name" />
                            </div>

                            <div class="space-y-2">
                                <Label for="account_name">Nama Pengirim</Label>
                                <Input id="account_name" v-model="paymentForm.account_name" placeholder="Nama di rekening" />
                                <InputError :message="paymentForm.errors.account_name" />
                            </div>

                            <div class="space-y-2">
                                <Label for="transfer_date">Tanggal Transfer</Label>
                                <Input id="transfer_date" v-model="paymentForm.transfer_date" type="date" />
                                <InputError :message="paymentForm.errors.transfer_date" />
                            </div>

                            <Button type="submit" class="w-full" :disabled="paymentForm.processing">
                                <Spinner v-if="paymentForm.processing" class="mr-2" />
                                <Upload v-else class="mr-2 h-4 w-4" />
                                Kirim Bukti Transfer
                            </Button>
                        </form>
                    </div>
                </CardContent>
            </Card>

            <!-- Payment History -->
            <Card v-if="paymentHistory.length > 0">
                <CardHeader>
                    <CardTitle>Riwayat Pembayaran</CardTitle>
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
                                    Paket {{ payment.plan_type === 'yearly' ? 'Tahunan' : 'Bulanan' }}
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
