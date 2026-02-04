<script setup lang="ts">
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent } from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import {
    Select,
    SelectContent,
    SelectGroup,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';
import {
    Table,
    TableBody,
    TableCell,
    TableHead,
    TableHeader,
    TableRow,
} from '@/components/ui/table';
import AdminLayout from '@/layouts/AdminLayout.vue';
import type { BreadcrumbItem } from '@/types';
import { Head, Link, router } from '@inertiajs/vue3';
import {
    AlertCircle,
    Check,
    ChevronLeft,
    ChevronRight,
    Clock,
    CreditCard,
    Eye,
    Search,
    X,
} from 'lucide-vue-next';
import { ref, watch } from 'vue';
import Swal from 'sweetalert2';

interface User {
    id: number;
    name: string;
    email: string;
}

interface PaymentProof {
    id: number;
    plan_type: string;
    amount: number;
    status: string;
    bank_name: string | null;
    account_name: string | null;
    transfer_date: string | null;
    created_at: string;
    user: User;
}

interface PaginationMeta {
    current_page: number;
    last_page: number;
    per_page: number;
    total: number;
    from: number | null;
    to: number | null;
}

interface Props {
    payments: {
        data: PaymentProof[];
        meta: PaginationMeta;
    };
    pendingCount: number;
    filters: {
        status: string;
        search: string;
    };
}

const props = defineProps<Props>();

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Admin', href: '/admin' },
    { title: 'Pembayaran', href: '/admin/payments' },
];

const searchQuery = ref(props.filters.search);
const statusFilter = ref(props.filters.status || 'all');

const formatCurrency = (value: number) => {
    return new Intl.NumberFormat('id-ID').format(value);
};

const formatDate = (dateStr: string) => {
    return new Date(dateStr).toLocaleDateString('id-ID', {
        day: 'numeric',
        month: 'short',
        year: 'numeric',
        hour: '2-digit',
        minute: '2-digit',
    });
};

const getStatusBadge = (status: string) => {
    switch (status) {
        case 'pending':
            return { variant: 'secondary' as const, icon: Clock, label: 'Pending' };
        case 'approved':
            return { variant: 'default' as const, icon: Check, label: 'Approved' };
        case 'rejected':
            return { variant: 'destructive' as const, icon: X, label: 'Rejected' };
        default:
            return { variant: 'outline' as const, icon: AlertCircle, label: status };
    }
};

const getPlanLabel = (plan: string) => {
    return plan === 'yearly' ? 'Tahunan' : 'Bulanan';
};

const applyFilters = () => {
    router.get('/admin/payments', {
        search: searchQuery.value || undefined,
        status: statusFilter.value !== 'all' ? statusFilter.value : undefined,
    }, {
        preserveState: true,
        replace: true,
    });
};

watch([statusFilter], () => {
    applyFilters();
});

const handleSearch = () => {
    applyFilters();
};

const goToPage = (page: number) => {
    router.get('/admin/payments', {
        page,
        search: searchQuery.value || undefined,
        status: statusFilter.value !== 'all' ? statusFilter.value : undefined,
    }, {
        preserveState: true,
    });
};

const quickApprove = (payment: PaymentProof) => {
    Swal.fire({
        title: 'Setujui Pembayaran?',
        html: `
            <p>User: <strong>${payment.user.name}</strong></p>
            <p>Paket: <strong>${getPlanLabel(payment.plan_type)}</strong></p>
            <p>Jumlah: <strong>Rp ${formatCurrency(payment.amount)}</strong></p>
        `,
        icon: 'question',
        showCancelButton: true,
        confirmButtonText: 'Ya, Setujui',
        cancelButtonText: 'Batal',
        confirmButtonColor: '#22c55e',
    }).then((result) => {
        if (result.isConfirmed) {
            router.post(`/admin/payments/${payment.id}/approve`, {}, {
                preserveScroll: true,
                onSuccess: () => {
                    Swal.fire('Berhasil!', 'Pembayaran telah disetujui.', 'success');
                },
            });
        }
    });
};

const quickReject = (payment: PaymentProof) => {
    Swal.fire({
        title: 'Tolak Pembayaran?',
        html: `
            <p>User: <strong>${payment.user.name}</strong></p>
            <p class="mt-2">Alasan penolakan:</p>
        `,
        input: 'textarea',
        inputPlaceholder: 'Masukkan alasan penolakan...',
        icon: 'warning',
        showCancelButton: true,
        confirmButtonText: 'Ya, Tolak',
        cancelButtonText: 'Batal',
        confirmButtonColor: '#ef4444',
        inputValidator: (value) => {
            if (!value) {
                return 'Alasan penolakan harus diisi!';
            }
        },
    }).then((result) => {
        if (result.isConfirmed) {
            router.post(`/admin/payments/${payment.id}/reject`, {
                reason: result.value,
            }, {
                preserveScroll: true,
                onSuccess: () => {
                    Swal.fire('Berhasil!', 'Pembayaran telah ditolak.', 'success');
                },
            });
        }
    });
};
</script>

<template>
    <Head title="Kelola Pembayaran" />

    <AdminLayout :breadcrumbs="breadcrumbs">
        <div class="flex h-full flex-1 flex-col gap-6 p-4 md:p-6">
            <!-- Header -->
            <div class="flex flex-col gap-4 md:flex-row md:items-center md:justify-between">
                <div>
                    <h1 class="text-2xl font-semibold flex items-center gap-2">
                        <CreditCard class="h-6 w-6" />
                        Kelola Pembayaran
                    </h1>
                    <p class="text-muted-foreground">
                        Verifikasi bukti transfer pembayaran pengguna
                    </p>
                </div>
                <Badge v-if="pendingCount > 0" variant="destructive" class="h-8 px-3 text-sm">
                    {{ pendingCount }} menunggu verifikasi
                </Badge>
            </div>

            <!-- Filters -->
            <div class="flex flex-col gap-4 md:flex-row">
                <div class="relative flex-1">
                    <Search class="absolute left-3 top-1/2 h-4 w-4 -translate-y-1/2 text-muted-foreground" />
                    <Input
                        v-model="searchQuery"
                        placeholder="Cari nama atau email user..."
                        class="pl-10"
                        @keyup.enter="handleSearch"
                    />
                </div>
                <Select v-model="statusFilter">
                    <SelectTrigger class="w-full md:w-48">
                        <SelectValue placeholder="Status" />
                    </SelectTrigger>
                    <SelectContent>
                        <SelectGroup>
                            <SelectItem value="all">Semua Status</SelectItem>
                            <SelectItem value="pending">Pending</SelectItem>
                            <SelectItem value="approved">Approved</SelectItem>
                            <SelectItem value="rejected">Rejected</SelectItem>
                        </SelectGroup>
                    </SelectContent>
                </Select>
            </div>

            <!-- Payments Table -->
            <Card>
                <CardContent class="p-0">
                    <Table>
                        <TableHeader>
                            <TableRow>
                                <TableHead>User</TableHead>
                                <TableHead>Paket</TableHead>
                                <TableHead>Jumlah</TableHead>
                                <TableHead>Bank</TableHead>
                                <TableHead>Tanggal</TableHead>
                                <TableHead>Status</TableHead>
                                <TableHead class="text-right">Aksi</TableHead>
                            </TableRow>
                        </TableHeader>
                        <TableBody>
                            <TableRow v-if="payments.data.length === 0">
                                <TableCell colspan="7" class="h-24 text-center text-muted-foreground">
                                    Tidak ada data pembayaran
                                </TableCell>
                            </TableRow>
                            <TableRow v-for="payment in payments.data" :key="payment.id">
                                <TableCell>
                                    <div>
                                        <div class="font-medium">{{ payment.user.name }}</div>
                                        <div class="text-sm text-muted-foreground">{{ payment.user.email }}</div>
                                    </div>
                                </TableCell>
                                <TableCell>
                                    <Badge variant="outline">
                                        {{ getPlanLabel(payment.plan_type) }}
                                    </Badge>
                                </TableCell>
                                <TableCell class="font-mono">
                                    Rp {{ formatCurrency(payment.amount) }}
                                </TableCell>
                                <TableCell>
                                    <div v-if="payment.bank_name">
                                        {{ payment.bank_name }}
                                        <div class="text-sm text-muted-foreground">{{ payment.account_name }}</div>
                                    </div>
                                    <span v-else class="text-muted-foreground">-</span>
                                </TableCell>
                                <TableCell class="text-sm">
                                    {{ formatDate(payment.created_at) }}
                                </TableCell>
                                <TableCell>
                                    <Badge :variant="getStatusBadge(payment.status).variant">
                                        <component :is="getStatusBadge(payment.status).icon" class="mr-1 h-3 w-3" />
                                        {{ getStatusBadge(payment.status).label }}
                                    </Badge>
                                </TableCell>
                                <TableCell class="text-right">
                                    <div class="flex items-center justify-end gap-2">
                                        <Button
                                            variant="ghost"
                                            size="sm"
                                            as-child
                                        >
                                            <Link :href="`/admin/payments/${payment.id}`">
                                                <Eye class="h-4 w-4" />
                                            </Link>
                                        </Button>
                                        <template v-if="payment.status === 'pending'">
                                            <Button
                                                variant="ghost"
                                                size="sm"
                                                class="text-green-500 hover:text-green-600"
                                                @click="quickApprove(payment)"
                                            >
                                                <Check class="h-4 w-4" />
                                            </Button>
                                            <Button
                                                variant="ghost"
                                                size="sm"
                                                class="text-red-500 hover:text-red-600"
                                                @click="quickReject(payment)"
                                            >
                                                <X class="h-4 w-4" />
                                            </Button>
                                        </template>
                                    </div>
                                </TableCell>
                            </TableRow>
                        </TableBody>
                    </Table>
                </CardContent>
            </Card>

            <!-- Simple Pagination -->
            <div v-if="payments.meta.last_page > 1" class="flex items-center justify-between">
                <p class="text-sm text-muted-foreground">
                    Menampilkan {{ payments.meta.from }} - {{ payments.meta.to }} dari {{ payments.meta.total }} data
                </p>
                <div class="flex items-center gap-2">
                    <Button
                        variant="outline"
                        size="sm"
                        :disabled="payments.meta.current_page === 1"
                        @click="goToPage(payments.meta.current_page - 1)"
                    >
                        <ChevronLeft class="h-4 w-4" />
                        Sebelumnya
                    </Button>
                    <span class="text-sm text-muted-foreground">
                        Halaman {{ payments.meta.current_page }} dari {{ payments.meta.last_page }}
                    </span>
                    <Button
                        variant="outline"
                        size="sm"
                        :disabled="payments.meta.current_page === payments.meta.last_page"
                        @click="goToPage(payments.meta.current_page + 1)"
                    >
                        Selanjutnya
                        <ChevronRight class="h-4 w-4" />
                    </Button>
                </div>
            </div>
        </div>
    </AdminLayout>
</template>
