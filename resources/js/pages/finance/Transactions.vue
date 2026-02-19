<script setup lang="ts">
import TransactionFormModal from '@/components/finance/TransactionFormModal.vue';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import AppLayout from '@/layouts/AppLayout.vue';
import { categories as financeCategories, transactions as financeTransactions } from '@/routes/finance';
import type { BreadcrumbItem, FinanceTransactionsProps, FinanceTransaction } from '@/types';

import { Head, Link, router } from '@inertiajs/vue3';
import { Filter, Plus, Search, Tag, Trash2 } from 'lucide-vue-next';
import { ref, watch } from 'vue';
import Swal from 'sweetalert2';

const props = defineProps<FinanceTransactionsProps>();

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Dashboard', href: '/' },
    { title: 'Keuangan', href: '/finance' },
    { title: 'Transaksi', href: '/finance/transactions' },
];

const showAddModal = ref(false);
const searchQuery = ref(props.filters?.search || '');
const filterType = ref(props.filters?.type || '');

watch([searchQuery, filterType], () => {
    router.visit(financeTransactions().url, {
        data: {
            search: searchQuery.value,
            type: filterType.value,
        },
        preserveState: true,
        preserveScroll: true,
        replace: true,
    });
});

const formatDate = (dateString: string) => {
    return new Date(dateString).toLocaleDateString('id-ID', {
        day: 'numeric',
        month: 'long',
        year: 'numeric',
    });
};

const formatCurrency = (amount: number) => {
    return new Intl.NumberFormat('id-ID', {
        style: 'currency',
        currency: 'IDR',
        minimumFractionDigits: 0,
    }).format(amount);
};

const getTransactionColor = (tx: FinanceTransaction) => {
    return tx.tx_type === 'income' ? 'text-emerald-600' : 'text-red-600';
};

const confirmDelete = (tx: FinanceTransaction) => {
    Swal.fire({
        title: 'Hapus Transaksi?',
        text: 'Transaksi ini akan dihapus permanen dan tidak dapat dikembalikan.',
        icon: 'warning',
        showCancelButton: true,
        confirmButtonColor: '#d33',
        cancelButtonColor: '#6b7280',
        confirmButtonText: 'Ya, Hapus!',
        cancelButtonText: 'Batal',
    }).then((result) => {
        if (result.isConfirmed) {
            router.delete(financeTransactions.destroy(tx.id).url, {
                preserveScroll: true,
                onSuccess: () => {
                    Swal.fire({
                        icon: 'success',
                        title: 'Transaksi dihapus',
                        text: 'Transaksi berhasil dihapus.',
                        timer: 2000,
                        showConfirmButton: false,
                    });
                },
                onError: () => {
                    Swal.fire({
                        icon: 'error',
                        title: 'Gagal menghapus',
                        text: 'Terjadi kesalahan saat menghapus transaksi.',
                    });
                },
            });
        }
    });
};
</script>
<template>
    <Head title="Transaksi Keuangan" />

    <AppLayout :breadcrumbs="breadcrumbs">
        <div class="flex h-full flex-1 flex-col gap-4 p-4">
            <!-- Header -->
            <div class="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
                <h1 class="text-2xl font-bold">Transaksi</h1>
                
                <div class="flex items-center gap-2">
                    <Link :href="financeCategories().url">
                        <Button variant="outline">
                            <Tag class="mr-2 h-4 w-4" />
                            Kelola Kategori
                        </Button>
                    </Link>
                    <Button @click="showAddModal = true">
                        <Plus class="mr-2 h-4 w-4" />
                        Tambah Transaksi
                    </Button>
                </div>

                <TransactionFormModal
                    v-model:open="showAddModal"
                    :categories="props.categories"
                    :accounts="props.accounts"
                />
            </div>



            <!-- Filters -->
            <Card>
                <CardContent class="p-4">
                    <div class="flex flex-col gap-4 sm:flex-row">
                        <div class="relative flex-1">
                            <Search class="absolute left-3 top-1/2 h-4 w-4 -translate-y-1/2 text-muted-foreground" />
                            <Input
                                v-model="searchQuery"
                                placeholder="Cari transaksi..."
                                class="pl-10"
                            />
                        </div>
                        <div class="flex gap-2">
                            <select
                                v-model="filterType"
                                class="flex h-9 rounded-md border border-input bg-transparent px-3 py-1 text-sm shadow-sm transition-colors file:border-0 file:bg-transparent file:text-sm file:font-medium placeholder:text-muted-foreground focus-visible:outline-none focus-visible:ring-1 focus-visible:ring-ring disabled:cursor-not-allowed disabled:opacity-50"
                            >
                                <option value="">Semua</option>
                                <option value="income">Pemasukan</option>
                                <option value="expense">Pengeluaran</option>
                            </select>
                        </div>
                    </div>
                </CardContent>
            </Card>

            <!-- Transactions List -->
            <Card>
                <CardHeader class="pb-2">
                    <CardTitle class="text-base font-medium">
                        {{ props.transactions.total }} Transaksi
                    </CardTitle>
                </CardHeader>
                <CardContent>
                    <div
                        v-if="props.transactions.data.length === 0"
                        class="py-12 text-center text-muted-foreground"
                    >
                        Tidak ada transaksi ditemukan
                    </div>
                    <div v-else class="space-y-2">
                        <div
                            v-for="tx in props.transactions.data"
                            :key="tx.id"
                            class="flex items-center justify-between rounded-lg border border-border p-3 transition-colors hover:bg-accent/50"
                        >
                            <div class="flex items-center gap-3">
                                <div
                                    class="flex h-10 w-10 items-center justify-center rounded-full"
                                    :class="
                                        tx.tx_type === 'income'
                                            ? 'bg-emerald-100 dark:bg-emerald-900/30'
                                            : 'bg-red-100 dark:bg-red-900/30'
                                    "
                                >
                                    <span class="text-lg">
                                        {{ tx.tx_type === 'income' ? '💰' : '💸' }}
                                    </span>
                                </div>
                                <div>
                                    <p class="font-medium">
                                        {{ tx.category?.name || (tx.tx_type === 'income' ? 'Pemasukan' : 'Pengeluaran') }}
                                    </p>
                                    <div class="flex items-center gap-2 text-xs text-muted-foreground">
                                        <span>{{ formatDate(tx.occurred_at) }}</span>
                                        <span v-if="tx.note">• {{ tx.note }}</span>
                                    </div>
                                </div>
                            </div>
                            <div class="flex items-center gap-2">
                                <p class="font-semibold" :class="getTransactionColor(tx)">
                                    {{ tx.tx_type === 'income' ? '+' : '-' }}{{ formatCurrency(tx.amount) }}
                                </p>
                                <Button
                                    variant="ghost"
                                    size="icon"
                                    class="h-8 w-8 text-muted-foreground hover:text-destructive"
                                    @click="confirmDelete(tx)"
                                >
                                    <Trash2 class="h-4 w-4" />
                                </Button>
                            </div>
                        </div>
                    </div>

                    <!-- Pagination -->
                    <div
                        v-if="props.transactions.last_page > 1"
                        class="mt-4 flex items-center justify-center gap-2"
                    >
                        <Button
                            v-for="link in props.transactions.links"
                            :key="link.label"
                            variant="outline"
                            size="sm"
                            :disabled="!link.url"
                            :class="{ 'bg-primary text-primary-foreground': link.active }"
                            @click="link.url && router.get(link.url)"
                            v-html="link.label"
                        />
                    </div>
                </CardContent>
            </Card>
        </div>

    </AppLayout>
</template>
