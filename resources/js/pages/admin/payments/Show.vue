<script setup lang="ts">
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import { Dialog, DialogContent, DialogDescription, DialogFooter, DialogHeader, DialogTitle } from '@/components/ui/dialog';
import { Label } from '@/components/ui/label';
import { Textarea } from '@/components/ui/textarea';
import AdminLayout from '@/layouts/AdminLayout.vue';
import type { BreadcrumbItem } from '@/types';
import { Head, router, useForm } from '@inertiajs/vue3';
import {
    ArrowLeft,
    Banknote,
    Calendar,
    Check,
    Clock,
    CreditCard,
    Download,
    Image,
    Mail,
    User,
    X,
} from 'lucide-vue-next';
import { ref } from 'vue';
import Swal from 'sweetalert2';

interface UserData {
    id: number;
    name: string;
    email: string;
}

interface SubscriptionData {
    id: number;
    plan: string;
    status: string;
    starts_at: string;
    ends_at: string;
}

interface PaymentProof {
    id: number;
    plan_type: string;
    amount: number;
    status: string;
    bank_name: string | null;
    account_name: string | null;
    transfer_date: string | null;
    transfer_proof_url: string | null;
    admin_notes: string | null;
    created_at: string;
    reviewed_at: string | null;
    user: UserData;
    subscription: SubscriptionData | null;
    reviewer: UserData | null;
}

const props = defineProps<{
    payment: PaymentProof;
}>();

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Admin', href: '/admin' },
    { title: 'Pembayaran', href: '/admin/payments' },
    { title: `#${props.payment.id}`, href: `/admin/payments/${props.payment.id}` },
];

const showRejectDialog = ref(false);
const rejectForm = useForm({
    reason: '',
});

const formatCurrency = (value: number) => {
    return new Intl.NumberFormat('id-ID').format(value);
};

const formatDate = (dateStr: string | null) => {
    if (!dateStr) return '-';
    return new Date(dateStr).toLocaleDateString('id-ID', {
        day: 'numeric',
        month: 'long',
        year: 'numeric',
        hour: '2-digit',
        minute: '2-digit',
    });
};

const getStatusBadge = (status: string) => {
    switch (status) {
        case 'pending':
            return { variant: 'secondary' as const, icon: Clock, label: 'Menunggu Verifikasi', color: 'text-yellow-500' };
        case 'approved':
            return { variant: 'default' as const, icon: Check, label: 'Disetujui', color: 'text-green-500' };
        case 'rejected':
            return { variant: 'destructive' as const, icon: X, label: 'Ditolak', color: 'text-red-500' };
        default:
            return { variant: 'outline' as const, icon: Clock, label: status, color: '' };
    }
};

const getPlanLabel = (plan: string) => {
    return plan === 'yearly' ? 'Tahunan (1 Tahun)' : 'Bulanan (1 Bulan)';
};

const approvePayment = () => {
    Swal.fire({
        title: 'Setujui Pembayaran?',
        text: 'Subscription user akan diaktifkan setelah disetujui.',
        icon: 'question',
        showCancelButton: true,
        confirmButtonText: 'Ya, Setujui',
        cancelButtonText: 'Batal',
        confirmButtonColor: '#22c55e',
    }).then((result) => {
        if (result.isConfirmed) {
            router.post(`/admin/payments/${props.payment.id}/approve`, {}, {
                onSuccess: () => {
                    Swal.fire('Berhasil!', 'Pembayaran telah disetujui dan subscription user telah diaktifkan.', 'success');
                },
            });
        }
    });
};

const rejectPayment = () => {
    rejectForm.post(`/admin/payments/${props.payment.id}/reject`, {
        onSuccess: () => {
            showRejectDialog.value = false;
            rejectForm.reset();
            Swal.fire('Berhasil!', 'Pembayaran telah ditolak.', 'success');
        },
    });
};

const goBack = () => {
    router.visit('/admin/payments');
};
</script>

<template>
    <Head :title="`Pembayaran #${payment.id}`" />

    <AdminLayout :breadcrumbs="breadcrumbs">
        <div class="flex h-full flex-1 flex-col gap-6 p-4 md:p-6">
            <!-- Header -->
            <div class="flex items-center justify-between">
                <div class="flex items-center gap-4">
                    <Button variant="ghost" size="sm" @click="goBack">
                        <ArrowLeft class="h-4 w-4" />
                    </Button>
                    <div>
                        <h1 class="text-2xl font-semibold flex items-center gap-2">
                            <CreditCard class="h-6 w-6" />
                            Pembayaran #{{ payment.id }}
                        </h1>
                        <p class="text-muted-foreground">
                            Detail bukti pembayaran
                        </p>
                    </div>
                </div>
                <Badge :variant="getStatusBadge(payment.status).variant" class="h-8 px-4 text-sm">
                    <component :is="getStatusBadge(payment.status).icon" class="mr-2 h-4 w-4" />
                    {{ getStatusBadge(payment.status).label }}
                </Badge>
            </div>

            <div class="grid gap-6 md:grid-cols-2">
                <!-- User & Payment Info -->
                <Card>
                    <CardHeader>
                        <CardTitle class="flex items-center gap-2">
                            <User class="h-5 w-5" />
                            Informasi Pengguna
                        </CardTitle>
                    </CardHeader>
                    <CardContent class="space-y-4">
                        <div class="flex items-center gap-3 rounded-lg border p-3">
                            <div class="h-10 w-10 rounded-full bg-primary/10 flex items-center justify-center">
                                <User class="h-5 w-5 text-primary" />
                            </div>
                            <div>
                                <div class="font-medium">{{ payment.user.name }}</div>
                                <div class="flex items-center gap-1 text-sm text-muted-foreground">
                                    <Mail class="h-3 w-3" />
                                    {{ payment.user.email }}
                                </div>
                            </div>
                        </div>

                        <div class="space-y-3">
                            <div class="flex justify-between py-2 border-b">
                                <span class="text-muted-foreground">Paket</span>
                                <Badge variant="outline">{{ getPlanLabel(payment.plan_type) }}</Badge>
                            </div>
                            <div class="flex justify-between py-2 border-b">
                                <span class="text-muted-foreground">Jumlah</span>
                                <span class="font-mono font-semibold">Rp {{ formatCurrency(payment.amount) }}</span>
                            </div>
                            <div class="flex justify-between py-2 border-b">
                                <span class="text-muted-foreground">Tanggal Pengajuan</span>
                                <span class="text-sm">{{ formatDate(payment.created_at) }}</span>
                            </div>
                        </div>
                    </CardContent>
                </Card>

                <!-- Bank Transfer Info -->
                <Card>
                    <CardHeader>
                        <CardTitle class="flex items-center gap-2">
                            <Banknote class="h-5 w-5" />
                            Informasi Transfer
                        </CardTitle>
                    </CardHeader>
                    <CardContent class="space-y-4">
                        <div class="grid gap-3">
                            <div class="flex justify-between py-2 border-b">
                                <span class="text-muted-foreground">Bank Pengirim</span>
                                <span>{{ payment.bank_name || '-' }}</span>
                            </div>
                            <div class="flex justify-between py-2 border-b">
                                <span class="text-muted-foreground">Nama Pengirim</span>
                                <span>{{ payment.account_name || '-' }}</span>
                            </div>
                            <div class="flex justify-between py-2 border-b">
                                <span class="text-muted-foreground">Tanggal Transfer</span>
                                <span>{{ payment.transfer_date ? formatDate(payment.transfer_date) : '-' }}</span>
                            </div>
                        </div>

                        <div v-if="payment.status !== 'pending'" class="pt-4 border-t space-y-3">
                            <div class="flex justify-between py-2">
                                <span class="text-muted-foreground">Direview oleh</span>
                                <span>{{ payment.reviewer?.name || '-' }}</span>
                            </div>
                            <div class="flex justify-between py-2">
                                <span class="text-muted-foreground">Tanggal Review</span>
                                <span>{{ formatDate(payment.reviewed_at) }}</span>
                            </div>
                            <div v-if="payment.admin_notes" class="pt-2">
                                <span class="text-muted-foreground text-sm">Catatan Admin:</span>
                                <p class="mt-1 p-3 rounded-lg bg-muted text-sm">{{ payment.admin_notes }}</p>
                            </div>
                        </div>
                    </CardContent>
                </Card>

                <!-- Transfer Proof Image -->
                <Card class="md:col-span-2">
                    <CardHeader>
                        <CardTitle class="flex items-center gap-2">
                            <Image class="h-5 w-5" />
                            Bukti Transfer
                        </CardTitle>
                        <CardDescription>
                            Gambar bukti transfer yang diunggah oleh pengguna
                        </CardDescription>
                    </CardHeader>
                    <CardContent>
                        <div v-if="payment.transfer_proof_url" class="space-y-4">
                            <div class="rounded-lg border overflow-hidden bg-muted/50">
                                <img 
                                    :src="payment.transfer_proof_url" 
                                    alt="Bukti Transfer"
                                    class="w-full max-h-[600px] object-contain"
                                />
                            </div>
                            <div class="flex justify-end">
                                <Button variant="outline" as="a" :href="payment.transfer_proof_url" target="_blank" download>
                                    <Download class="mr-2 h-4 w-4" />
                                    Download Gambar
                                </Button>
                            </div>
                        </div>
                        <div v-else class="h-48 flex items-center justify-center rounded-lg border border-dashed">
                            <div class="text-center text-muted-foreground">
                                <Image class="mx-auto h-12 w-12 mb-2 opacity-50" />
                                <p>Tidak ada bukti transfer</p>
                            </div>
                        </div>
                    </CardContent>
                </Card>
            </div>

            <!-- Action Buttons -->
            <div v-if="payment.status === 'pending'" class="flex justify-end gap-3 pt-4 border-t">
                <Button variant="outline" class="text-red-500 hover:bg-red-500/10" @click="showRejectDialog = true">
                    <X class="mr-2 h-4 w-4" />
                    Tolak Pembayaran
                </Button>
                <Button class="bg-green-500 hover:bg-green-600" @click="approvePayment">
                    <Check class="mr-2 h-4 w-4" />
                    Setujui Pembayaran
                </Button>
            </div>

            <!-- Reject Dialog -->
            <Dialog v-model:open="showRejectDialog">
                <DialogContent>
                    <DialogHeader>
                        <DialogTitle>Tolak Pembayaran</DialogTitle>
                        <DialogDescription>
                            Berikan alasan penolakan yang jelas agar pengguna dapat memperbaiki pengajuannya.
                        </DialogDescription>
                    </DialogHeader>
                    <div class="space-y-4 py-4">
                        <div class="space-y-2">
                            <Label for="reason">Alasan Penolakan *</Label>
                            <Textarea
                                id="reason"
                                v-model="rejectForm.reason"
                                placeholder="Contoh: Bukti transfer tidak jelas, nominal tidak sesuai, dll."
                                rows="4"
                            />
                            <p v-if="rejectForm.errors.reason" class="text-sm text-red-500">
                                {{ rejectForm.errors.reason }}
                            </p>
                        </div>
                    </div>
                    <DialogFooter>
                        <Button variant="outline" @click="showRejectDialog = false">
                            Batal
                        </Button>
                        <Button variant="destructive" :disabled="rejectForm.processing || !rejectForm.reason" @click="rejectPayment">
                            <X class="mr-2 h-4 w-4" />
                            Tolak Pembayaran
                        </Button>
                    </DialogFooter>
                </DialogContent>
            </Dialog>
        </div>
    </AdminLayout>
</template>
