<script setup lang="ts">
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import type { FinanceBudget } from '@/types';
import { finance } from '@/routes';
import { ArrowRight, AlertTriangle, CheckCircle2 } from 'lucide-vue-next';
import { Link } from '@inertiajs/vue3';
import { computed } from 'vue';
import { useI18n } from 'vue-i18n';

const { locale } = useI18n();

const props = defineProps<{
    budgets: FinanceBudget[];
}>();

const formatCurrency = (amount: number) =>
    new Intl.NumberFormat(locale.value === 'id' ? 'id-ID' : 'en-US', {
        style: 'currency',
        currency: 'IDR',
        minimumFractionDigits: 0,
        maximumFractionDigits: 0,
    }).format(amount);

const budgetsUrl = computed(() => `${finance().url}/budgets`);

const barColor = (budget: FinanceBudget) => {
    if (budget.is_over) return 'bg-red-500';
    if (budget.is_approaching) return 'bg-amber-400';
    return 'bg-emerald-500';
};

const barWidth = (budget: FinanceBudget) =>
    `${Math.min(budget.used_pct, 100)}%`;
</script>

<template>
    <Card>
        <CardHeader class="flex flex-row items-center justify-between pb-2">
            <CardTitle class="text-base font-semibold">Budget Bulan Ini</CardTitle>
            <Link :href="budgetsUrl" class="flex items-center gap-1 text-xs text-muted-foreground hover:text-foreground transition-colors">
                Lihat semua
                <ArrowRight :size="12" />
            </Link>
        </CardHeader>
        <CardContent>
            <div v-if="budgets.length === 0" class="py-4 text-center text-sm text-muted-foreground">
                Belum ada budget. <Link :href="budgetsUrl" class="underline">Buat sekarang</Link>
            </div>
            <div v-else class="space-y-4">
                <div
                    v-for="budget in budgets.slice(0, 5)"
                    :key="budget.id"
                    class="space-y-1.5"
                >
                    <div class="flex items-center justify-between text-sm">
                        <div class="flex items-center gap-2">
                            <AlertTriangle
                                v-if="budget.is_over || budget.is_approaching"
                                :size="13"
                                :class="budget.is_over ? 'text-red-500' : 'text-amber-400'"
                            />
                            <CheckCircle2
                                v-else
                                :size="13"
                                class="text-emerald-500"
                            />
                            <span class="font-medium">
                                {{ budget.category?.name ?? 'Semua Pengeluaran' }}
                            </span>
                        </div>
                        <span class="text-xs text-muted-foreground">
                            {{ budget.used_pct.toFixed(0) }}%
                        </span>
                    </div>
                    <div class="h-1.5 w-full rounded-full bg-muted">
                        <div
                            class="h-1.5 rounded-full transition-all"
                            :class="barColor(budget)"
                            :style="{ width: barWidth(budget) }"
                        />
                    </div>
                    <div class="flex justify-between text-xs text-muted-foreground">
                        <span>{{ formatCurrency(budget.spent) }}</span>
                        <span>{{ formatCurrency(budget.amount) }}</span>
                    </div>
                </div>
            </div>
        </CardContent>
    </Card>
</template>
