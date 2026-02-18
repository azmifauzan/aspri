<script setup lang="ts">
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import {
    Table,
    TableBody,
    TableCell,
    TableHead,
    TableHeader,
    TableRow,
} from '@/components/ui/table';
import AdminLayout from '@/layouts/AdminLayout.vue';
import admin from '@/routes/admin';
import type { BreadcrumbItem } from '@/types';
import type { PromoCodeShowPageProps } from '@/types/admin';
import { Head, router } from '@inertiajs/vue3';
import {
    ArrowLeft,
    Calendar,
    ChevronLeft,
    ChevronRight,
    Clock,
    Copy,
    Tag,
    User,
    Users,
} from 'lucide-vue-next';
import Swal from 'sweetalert2';

const props = defineProps<PromoCodeShowPageProps>();

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Admin', href: '/admin' },
    { title: 'Kode Promo', href: '/admin/promo-codes' },
    { title: props.promoCode.code, href: `/admin/promo-codes/${props.promoCode.id}` },
];

const formatDate = (dateStr: string | null) => {
    if (!dateStr) return '-';
    return new Date(dateStr).toLocaleDateString('id-ID', {
        day: 'numeric',
        month: 'short',
        year: 'numeric',
        hour: '2-digit',
        minute: '2-digit',
    });
};

const formatDateShort = (dateStr: string) => {
    return new Date(dateStr).toLocaleDateString('id-ID', {
        day: 'numeric',
        month: 'short',
        year: 'numeric',
    });
};

const getStatusInfo = () => {
    const promo = props.promoCode;
    if (!promo.is_active) {
        return { variant: 'secondary' as const, label: 'Nonaktif' };
    }
    if (new Date(promo.expires_at) < new Date()) {
        return { variant: 'destructive' as const, label: 'Kadaluarsa' };
    }
    if (promo.usage_count >= promo.max_usages) {
        return { variant: 'outline' as const, label: 'Habis' };
    }
    return { variant: 'default' as const, label: 'Aktif' };
};

const copyCode = async (code: string) => {
    await navigator.clipboard.writeText(code);
    Swal.fire({
        icon: 'success',
        title: 'Tersalin!',
        text: `Kode ${code} berhasil disalin.`,
        timer: 1500,
        showConfirmButton: false,
    });
};

const goToPage = (page: number) => {
    router.get(`/admin/promo-codes/${props.promoCode.id}`, {
        page,
    }, {
        preserveState: true,
    });
};

const statusInfo = getStatusInfo();
</script>

<template>
    <Head :title="`Kode Promo: ${promoCode.code}`" />

    <AdminLayout :breadcrumbs="breadcrumbs">
        <div class="flex h-full flex-1 flex-col gap-6 p-4 md:p-6">
            <!-- Header -->
            <div class="flex flex-col gap-4 md:flex-row md:items-center md:justify-between">
                <div class="flex items-center gap-4">
                    <Button variant="outline" size="sm" @click="router.visit(admin.promoCodes.index().url)">
                        <ArrowLeft class="mr-2 h-4 w-4" />
                        Kembali
                    </Button>
                    <div>
                        <h1 class="flex items-center gap-2 text-2xl font-semibold">
                            <Tag class="h-6 w-6" />
                            Detail Kode Promo
                        </h1>
                    </div>
                </div>
            </div>

            <!-- Promo Code Info -->
            <div class="grid gap-6 md:grid-cols-3">
                <!-- Main Info -->
                <Card class="md:col-span-2">
                    <CardHeader>
                        <CardTitle class="flex items-center gap-3">
                            <code class="rounded bg-muted px-3 py-1 text-lg font-mono font-bold">{{ promoCode.code }}</code>
                            <Button variant="ghost" size="sm" class="h-7 w-7 p-0" @click="copyCode(promoCode.code)">
                                <Copy class="h-4 w-4" />
                            </Button>
                            <Badge :variant="statusInfo.variant">{{ statusInfo.label }}</Badge>
                        </CardTitle>
                        <CardDescription v-if="promoCode.description">{{ promoCode.description }}</CardDescription>
                    </CardHeader>
                    <CardContent>
                        <div class="grid grid-cols-2 gap-4 md:grid-cols-4">
                            <div class="space-y-1">
                                <p class="text-sm text-muted-foreground">Durasi</p>
                                <p class="text-lg font-semibold">{{ promoCode.duration_days }} hari</p>
                            </div>
                            <div class="space-y-1">
                                <p class="text-sm text-muted-foreground">Penggunaan</p>
                                <p class="text-lg font-semibold font-mono">{{ promoCode.usage_count }}/{{ promoCode.max_usages }}</p>
                            </div>
                            <div class="space-y-1">
                                <p class="text-sm text-muted-foreground">Sisa Kuota</p>
                                <p class="text-lg font-semibold">{{ promoCode.remaining_usages }}</p>
                            </div>
                            <div class="space-y-1">
                                <p class="text-sm text-muted-foreground">Kadaluarsa</p>
                                <p class="text-sm font-medium">{{ formatDateShort(promoCode.expires_at) }}</p>
                            </div>
                        </div>
                    </CardContent>
                </Card>

                <!-- Side Info -->
                <Card>
                    <CardHeader>
                        <CardTitle class="text-base">Informasi</CardTitle>
                    </CardHeader>
                    <CardContent class="space-y-4">
                        <div class="flex items-center gap-3">
                            <User class="h-4 w-4 text-muted-foreground" />
                            <div>
                                <p class="text-xs text-muted-foreground">Dibuat oleh</p>
                                <p class="text-sm font-medium">{{ promoCode.creator?.name ?? 'Sistem' }}</p>
                            </div>
                        </div>
                        <div class="flex items-center gap-3">
                            <Calendar class="h-4 w-4 text-muted-foreground" />
                            <div>
                                <p class="text-xs text-muted-foreground">Tanggal dibuat</p>
                                <p class="text-sm font-medium">{{ formatDate(promoCode.created_at) }}</p>
                            </div>
                        </div>
                        <div class="flex items-center gap-3">
                            <Users class="h-4 w-4 text-muted-foreground" />
                            <div>
                                <p class="text-xs text-muted-foreground">Total penggunaan</p>
                                <p class="text-sm font-medium">{{ redemptions.total }} pengguna</p>
                            </div>
                        </div>
                    </CardContent>
                </Card>
            </div>

            <!-- Redemptions Table -->
            <Card>
                <CardHeader>
                    <CardTitle class="flex items-center gap-2 text-base">
                        <Clock class="h-5 w-5" />
                        Riwayat Penggunaan
                    </CardTitle>
                    <CardDescription>
                        Daftar pengguna yang telah menggunakan kode promo ini
                    </CardDescription>
                </CardHeader>
                <CardContent class="p-0">
                    <Table>
                        <TableHeader>
                            <TableRow>
                                <TableHead>Pengguna</TableHead>
                                <TableHead>Email</TableHead>
                                <TableHead>Hari Ditambahkan</TableHead>
                                <TableHead>Sebelumnya Berakhir</TableHead>
                                <TableHead>Sekarang Berakhir</TableHead>
                                <TableHead>Tanggal Redeem</TableHead>
                            </TableRow>
                        </TableHeader>
                        <TableBody>
                            <TableRow v-if="redemptions.data.length === 0">
                                <TableCell colspan="6" class="h-24 text-center text-muted-foreground">
                                    Belum ada yang menggunakan kode promo ini
                                </TableCell>
                            </TableRow>
                            <TableRow v-for="redemption in redemptions.data" :key="redemption.id">
                                <TableCell>
                                    <div class="flex items-center gap-2">
                                        <div class="flex h-8 w-8 items-center justify-center rounded-full bg-muted text-xs font-medium">
                                            {{ redemption.user?.name?.charAt(0)?.toUpperCase() ?? '?' }}
                                        </div>
                                        <span class="font-medium">{{ redemption.user?.name ?? 'User Terhapus' }}</span>
                                    </div>
                                </TableCell>
                                <TableCell class="text-sm text-muted-foreground">
                                    {{ redemption.user?.email ?? '-' }}
                                </TableCell>
                                <TableCell>
                                    <Badge variant="outline">+{{ redemption.days_added }} hari</Badge>
                                </TableCell>
                                <TableCell class="text-sm">
                                    {{ formatDate(redemption.previous_ends_at) }}
                                </TableCell>
                                <TableCell class="text-sm">
                                    {{ formatDate(redemption.new_ends_at) }}
                                </TableCell>
                                <TableCell class="text-sm">
                                    {{ formatDate(redemption.created_at) }}
                                </TableCell>
                            </TableRow>
                        </TableBody>
                    </Table>
                </CardContent>
            </Card>

            <!-- Pagination -->
            <div v-if="redemptions.last_page > 1" class="flex items-center justify-between">
                <p class="text-sm text-muted-foreground">
                    Halaman {{ redemptions.current_page }} dari {{ redemptions.last_page }} ({{ redemptions.total }} data)
                </p>
                <div class="flex items-center gap-2">
                    <Button
                        variant="outline"
                        size="sm"
                        :disabled="redemptions.current_page === 1"
                        @click="goToPage(redemptions.current_page - 1)"
                    >
                        <ChevronLeft class="h-4 w-4" />
                        Sebelumnya
                    </Button>
                    <Button
                        variant="outline"
                        size="sm"
                        :disabled="redemptions.current_page === redemptions.last_page"
                        @click="goToPage(redemptions.current_page + 1)"
                    >
                        Selanjutnya
                        <ChevronRight class="h-4 w-4" />
                    </Button>
                </div>
            </div>
        </div>
    </AdminLayout>
</template>
