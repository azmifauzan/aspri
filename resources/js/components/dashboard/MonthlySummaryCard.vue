<script setup lang="ts">
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import type { MonthlySummary } from '@/types';
import { TrendingDown, TrendingUp, Wallet } from 'lucide-vue-next';
import { computed } from 'vue';
import { useI18n } from 'vue-i18n';

const { locale } = useI18n();

const props = withDefaults(
    defineProps<{
        summary?: MonthlySummary;
    }>(),
    {
        summary: () => ({
            income: 0,
            expense: 0,
            balance: 0,
            incomeChange: 0,
            expenseChange: 0,
        }),
    },
);

const formatCurrency = (amount: number) => {
    return new Intl.NumberFormat(locale.value === 'id' ? 'id-ID' : 'en-US', {
        style: 'currency',
        currency: 'IDR',
        minimumFractionDigits: 0,
        maximumFractionDigits: 0,
    }).format(amount);
};

const balanceColor = computed(() => {
    return props.summary.balance >= 0
        ? 'text-emerald-600 dark:text-emerald-400'
        : 'text-red-600 dark:text-red-400';
});
</script>

<template>
    <Card>
        <CardHeader class="pb-2">
            <CardTitle class="flex items-center gap-2 text-base font-medium">
                <Wallet class="h-4 w-4" />
                {{ $t('dashboard.monthlySummary') }}
            </CardTitle>
        </CardHeader>
        <CardContent class="space-y-4">
            <!-- Income -->
            <div class="flex items-center justify-between">
                <div class="flex items-center gap-2">
                    <div
                        class="flex h-8 w-8 items-center justify-center rounded-full bg-emerald-100 dark:bg-emerald-900/30"
                    >
                        <TrendingUp
                            class="h-4 w-4 text-emerald-600 dark:text-emerald-400"
                        />
                    </div>
                    <span class="text-sm text-muted-foreground">{{ $t('dashboard.income') }}</span>
                </div>
                <div class="text-right">
                    <p class="font-semibold text-emerald-600 dark:text-emerald-400">
                        {{ formatCurrency(summary.income) }}
                    </p>
                    <p
                        v-if="summary.incomeChange"
                        class="text-xs text-muted-foreground"
                    >
                        {{ summary.incomeChange > 0 ? '+' : ''
                        }}{{ $t('dashboard.fromLastMonth', { change: summary.incomeChange }) }}
                    </p>
                </div>
            </div>

            <!-- Expense -->
            <div class="flex items-center justify-between">
                <div class="flex items-center gap-2">
                    <div
                        class="flex h-8 w-8 items-center justify-center rounded-full bg-red-100 dark:bg-red-900/30"
                    >
                        <TrendingDown
                            class="h-4 w-4 text-red-600 dark:text-red-400"
                        />
                    </div>
                    <span class="text-sm text-muted-foreground"
                        >{{ $t('dashboard.expense') }}</span
                    >
                </div>
                <div class="text-right">
                    <p class="font-semibold text-red-600 dark:text-red-400">
                        {{ formatCurrency(summary.expense) }}
                    </p>
                    <p
                        v-if="summary.expenseChange"
                        class="text-xs text-muted-foreground"
                    >
                        {{ summary.expenseChange > 0 ? '+' : ''
                        }}{{ $t('dashboard.fromLastMonth', { change: summary.expenseChange }) }}
                    </p>
                </div>
            </div>

            <!-- Divider -->
            <div class="border-t border-border" />

            <!-- Balance -->
            <div class="flex items-center justify-between">
                <span class="text-sm font-medium">{{ $t('dashboard.balance') }}</span>
                <p class="text-lg font-bold" :class="balanceColor">
                    {{ formatCurrency(summary.balance) }}
                </p>
            </div>
        </CardContent>
    </Card>
</template>
