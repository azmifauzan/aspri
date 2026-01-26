<script setup lang="ts">
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import {
    CalendarPlus,
    FileText,
    TrendingDown,
    TrendingUp,
    Zap,
} from 'lucide-vue-next';

const quickActions = [
    {
        id: 'expense',
        label: 'Pengeluaran',
        icon: TrendingDown,
        color: 'text-red-600 dark:text-red-400',
        bgColor: 'bg-red-100 dark:bg-red-900/30 hover:bg-red-200 dark:hover:bg-red-900/50',
    },
    {
        id: 'income',
        label: 'Pemasukan',
        icon: TrendingUp,
        color: 'text-emerald-600 dark:text-emerald-400',
        bgColor: 'bg-emerald-100 dark:bg-emerald-900/30 hover:bg-emerald-200 dark:hover:bg-emerald-900/50',
    },
    {
        id: 'event',
        label: 'Event',
        icon: CalendarPlus,
        color: 'text-blue-600 dark:text-blue-400',
        bgColor: 'bg-blue-100 dark:bg-blue-900/30 hover:bg-blue-200 dark:hover:bg-blue-900/50',
    },
    {
        id: 'note',
        label: 'Note',
        icon: FileText,
        color: 'text-amber-600 dark:text-amber-400',
        bgColor: 'bg-amber-100 dark:bg-amber-900/30 hover:bg-amber-200 dark:hover:bg-amber-900/50',
    },
];

const handleAction = (actionId: string) => {
    // TODO: Implement navigation or modal opening
    console.log('Quick action:', actionId);
};
</script>

<template>
    <Card>
        <CardHeader class="pb-2">
            <CardTitle class="flex items-center gap-2 text-base font-medium">
                <Zap class="h-4 w-4" />
                Aksi Cepat
            </CardTitle>
        </CardHeader>
        <CardContent>
            <div class="grid grid-cols-2 gap-2">
                <Button
                    v-for="action in quickActions"
                    :key="action.id"
                    variant="ghost"
                    class="h-auto flex-col gap-2 py-4"
                    :class="action.bgColor"
                    @click="handleAction(action.id)"
                >
                    <component
                        :is="action.icon"
                        class="h-5 w-5"
                        :class="action.color"
                    />
                    <span class="text-xs font-medium" :class="action.color">
                        {{ action.label }}
                    </span>
                </Button>
            </div>
        </CardContent>
    </Card>
</template>
