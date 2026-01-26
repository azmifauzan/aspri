<script setup lang="ts">
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import type { RecentActivity } from '@/types';
import {
    Activity,
    Calendar,
    FileText,
    ShoppingCart,
    TrendingDown,
    TrendingUp,
    Wallet,
} from 'lucide-vue-next';
import { type Component } from 'vue';

defineProps<{
    activities: RecentActivity[];
}>();

const iconMap: Record<string, Component> = {
    wallet: Wallet,
    calendar: Calendar,
    'file-text': FileText,
    'trending-up': TrendingUp,
    'trending-down': TrendingDown,
    'shopping-cart': ShoppingCart,
};

const getTypeStyles = (type: string) => {
    const styles: Record<string, { bg: string; text: string }> = {
        expense: {
            bg: 'bg-red-100 dark:bg-red-900/30',
            text: 'text-red-600 dark:text-red-400',
        },
        income: {
            bg: 'bg-emerald-100 dark:bg-emerald-900/30',
            text: 'text-emerald-600 dark:text-emerald-400',
        },
        event: {
            bg: 'bg-blue-100 dark:bg-blue-900/30',
            text: 'text-blue-600 dark:text-blue-400',
        },
        note: {
            bg: 'bg-amber-100 dark:bg-amber-900/30',
            text: 'text-amber-600 dark:text-amber-400',
        },
    };
    return styles[type] || { bg: 'bg-gray-100 dark:bg-gray-900/30', text: 'text-gray-600 dark:text-gray-400' };
};
</script>

<template>
    <Card>
        <CardHeader class="pb-2">
            <CardTitle class="flex items-center gap-2 text-base font-medium">
                <Activity class="h-4 w-4" />
                Aktivitas Terbaru
            </CardTitle>
        </CardHeader>
        <CardContent>
            <!-- Empty State -->
            <div
                v-if="activities.length === 0"
                class="flex flex-col items-center justify-center py-8 text-center"
            >
                <Activity class="h-12 w-12 text-muted-foreground/50" />
                <p class="mt-2 text-sm text-muted-foreground">
                    Belum ada aktivitas
                </p>
            </div>

            <!-- Activities List -->
            <div v-else class="space-y-3">
                <div
                    v-for="activity in activities"
                    :key="activity.id"
                    class="flex items-start gap-3"
                >
                    <div
                        class="flex h-8 w-8 shrink-0 items-center justify-center rounded-full"
                        :class="getTypeStyles(activity.type).bg"
                    >
                        <component
                            :is="iconMap[activity.icon] || Activity"
                            class="h-4 w-4"
                            :class="getTypeStyles(activity.type).text"
                        />
                    </div>
                    <div class="min-w-0 flex-1">
                        <p class="truncate text-sm font-medium">
                            {{ activity.title }}
                        </p>
                        <p class="text-xs text-muted-foreground">
                            {{ activity.description }}
                        </p>
                    </div>
                    <span class="shrink-0 text-xs text-muted-foreground">
                        {{ activity.time }}
                    </span>
                </div>
            </div>
        </CardContent>
    </Card>
</template>
