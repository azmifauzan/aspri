<script setup lang="ts">
import { MonthlySummaryCard, WeeklyChartCard } from '@/components/dashboard';
import TransactionFormModal from '@/components/finance/TransactionFormModal.vue';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import AppLayout from '@/layouts/AppLayout.vue';
import { finance } from '@/routes';
import { transactions as financeTransactions } from '@/routes/finance';
import type { BreadcrumbItem, FinanceOverviewProps, FinanceTransaction } from '@/types';

import { Head, Link } from '@inertiajs/vue3';
import { ArrowRight, Plus, Wallet } from 'lucide-vue-next';
import { computed, ref } from 'vue';
import { useI18n } from 'vue-i18n';

const { t, locale } = useI18n();

const props = defineProps<FinanceOverviewProps>();

const breadcrumbs = computed<BreadcrumbItem[]>(() => [
    {
        title: t('finance.title'),
        href: finance().url,
    },
]);

const showAddModal = ref(false);

const formatCurrency = (amount: number) => {
    return new Intl.NumberFormat(locale.value === 'id' ? 'id-ID' : 'en-US', {
        style: 'currency',
        currency: 'IDR',
        minimumFractionDigits: 0,
        maximumFractionDigits: 0,
    }).format(amount);
};

const formatDate = (dateString: string) => {
    return new Date(dateString).toLocaleDateString(locale.value === 'id' ? 'id-ID' : 'en-US', {
        day: 'numeric',
        month: 'short',
    });
};

const getTransactionColor = (tx: FinanceTransaction) => {
    return tx.tx_type === 'income'
        ? 'text-emerald-600 dark:text-emerald-400'
        : 'text-red-600 dark:text-red-400';
};

const getTransactionSign = (tx: FinanceTransaction) => {
    return tx.tx_type === 'income' ? '+' : '-';
};
</script>

<template>
    <Head :title="$t('finance.title')" />

    <AppLayout :breadcrumbs="breadcrumbs">
        <div class="flex h-full flex-1 flex-col gap-4 p-4">
            <!-- Header with Add Button -->
            <div class="flex items-center justify-between">
                <h1 class="text-2xl font-bold">{{ $t('finance.title') }}</h1>
                <Button @click="showAddModal = true">
                    <Plus class="mr-2 h-4 w-4" />
                    {{ $t('finance.addTransaction') }}
                </Button>
            </div>

            <!-- Summary & Chart Row -->
            <div class="grid gap-4 lg:grid-cols-2">
                <MonthlySummaryCard :summary="props.monthlySummary" />
                <WeeklyChartCard :expenses="props.weeklyExpenses" />
            </div>

            <!-- Accounts Summary -->
            <Card v-if="props.accounts.length > 0">
                <CardHeader class="pb-2">
                    <CardTitle class="flex items-center gap-2 text-base font-medium">
                        <Wallet class="h-4 w-4" />
                        {{ $t('finance.financeAccounts') }}
                    </CardTitle>
                </CardHeader>
                <CardContent>
                    <div class="grid gap-3 sm:grid-cols-2 lg:grid-cols-3">
                        <div
                            v-for="account in props.accounts"
                            :key="account.id"
                            class="flex items-center justify-between rounded-lg border border-border p-3"
                        >
                            <div>
                                <p class="font-medium">{{ account.name }}</p>
                                <p class="text-xs text-muted-foreground capitalize">
                                    {{ account.type }}
                                </p>
                            </div>
                            <p class="font-semibold">
                                {{ formatCurrency(account.current_balance || account.initial_balance) }}
                            </p>
                        </div>
                    </div>
                </CardContent>
            </Card>

            <!-- Recent Transactions -->
            <Card>
                <CardHeader class="pb-2">
                    <div class="flex items-center justify-between">
                        <CardTitle class="text-base font-medium">
                            {{ $t('finance.recentTransactions') }}
                        </CardTitle>
                        <Link
                            :href="financeTransactions().url"
                            class="flex items-center gap-1 text-sm text-primary hover:underline"
                        >
                            {{ $t('finance.viewAll') }}
                            <ArrowRight class="h-4 w-4" />
                        </Link>
                    </div>
                </CardHeader>
                <CardContent>
                    <div
                        v-if="props.recentTransactions.length === 0"
                        class="py-8 text-center text-muted-foreground"
                    >
                        {{ $t('finance.noTransactions') }}
                    </div>
                    <div v-else class="space-y-3">
                        <div
                            v-for="tx in props.recentTransactions"
                            :key="tx.id"
                            class="flex items-center justify-between rounded-lg border border-border p-3"
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
                                        {{ tx.category?.icon ? '📦' : tx.tx_type === 'income' ? '💰' : '💸' }}
                                    </span>
                                </div>
                                <div>
                                    <p class="font-medium">
                                        {{ tx.category?.name || (tx.tx_type === 'income' ? $t('finance.income') : $t('finance.expense')) }}
                                    </p>
                                    <p class="text-xs text-muted-foreground">
                                        {{ tx.note || formatDate(tx.occurred_at) }}
                                    </p>
                                </div>
                            </div>
                            <p class="font-semibold" :class="getTransactionColor(tx)">
                                {{ getTransactionSign(tx) }}{{ formatCurrency(tx.amount) }}
                            </p>
                        </div>
                    </div>
                </CardContent>
            </Card>
        </div>

        <TransactionFormModal
            v-model:open="showAddModal"
            :categories="props.categories"
            :accounts="props.accounts"
        />
    </AppLayout>
</template>

