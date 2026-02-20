<script setup lang="ts">
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import type { WeeklyExpense } from '@/types';
import { BarChart3 } from 'lucide-vue-next';
import { computed } from 'vue';
import { useI18n } from 'vue-i18n';

const props = defineProps<{
    expenses: WeeklyExpense[];
}>();

const { locale } = useI18n();

const maxAmount = computed(() => {
    return Math.max(...props.expenses.map((e) => e.amount));
});

const totalWeekly = computed(() => {
    return props.expenses.reduce((sum, e) => sum + e.amount, 0);
});

const formatCurrency = (amount: number) => {
    return new Intl.NumberFormat(locale.value === 'id' ? 'id-ID' : 'en-US', {
        style: 'currency',
        currency: 'IDR',
        minimumFractionDigits: 0,
        maximumFractionDigits: 0,
    }).format(amount);
};

const formatShortCurrency = (amount: number) => {
    if (amount >= 1000000) {
        return `${(amount / 1000000).toFixed(1)}jt`;
    }
    if (amount >= 1000) {
        return `${(amount / 1000).toFixed(0)}rb`;
    }
    return amount.toString();
};

const getBarHeight = (amount: number) => {
    if (maxAmount.value === 0) return '0%';
    const percentage = (amount / maxAmount.value) * 100;
    return `${Math.max(percentage, 5)}%`;
};
</script>

<template>
    <Card>
        <CardHeader class="pb-2">
            <div class="flex items-center justify-between">
                <CardTitle class="flex items-center gap-2 text-base font-medium">
                    <BarChart3 class="h-4 w-4" />
                    {{ $t('dashboard.weeklyExpenses') }}
                </CardTitle>
                <span class="text-sm font-semibold text-muted-foreground">
                    {{ formatCurrency(totalWeekly) }}
                </span>
            </div>
        </CardHeader>
        <CardContent>
            <div class="flex h-40 items-end justify-between gap-2">
                <div
                    v-for="expense in expenses"
                    :key="expense.day"
                    class="group flex flex-1 flex-col items-center"
                >
                    <!-- Tooltip -->
                    <div
                        class="mb-1 rounded bg-popover px-2 py-1 text-xs font-medium text-popover-foreground opacity-0 shadow-md transition-opacity group-hover:opacity-100"
                    >
                        {{ formatShortCurrency(expense.amount) }}
                    </div>

                    <!-- Bar -->
                    <div
                        class="w-full max-w-8 rounded-t-md bg-primary/80 transition-all group-hover:bg-primary"
                        :style="{ height: getBarHeight(expense.amount) }"
                    />

                    <!-- Day Label -->
                    <span
                        class="mt-2 text-xs font-medium text-muted-foreground"
                    >
                        {{ expense.day }}
                    </span>
                </div>
            </div>
        </CardContent>
    </Card>
</template>
