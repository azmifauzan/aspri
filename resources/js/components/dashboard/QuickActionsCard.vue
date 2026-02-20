<script setup lang="ts">
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { router } from '@inertiajs/vue3';
import {
    CalendarPlus,
    FileText,
    MessageCircle,
    Wallet,
    Zap,
} from 'lucide-vue-next';
import { useI18n } from 'vue-i18n';
import { computed } from 'vue';

const { t } = useI18n();

const quickActions = computed(() => [
    {
        id: 'chat',
        label: t('dashboard.startChat'),
        icon: MessageCircle,
        color: 'text-green-600 dark:text-green-400',
        bgColor: 'bg-green-100 dark:bg-green-900/30 hover:bg-green-200 dark:hover:bg-green-900/50',
        url: '/chat',
    },
    {
        id: 'finance',
        label: t('dashboard.addTransaction'),
        icon: Wallet,
        color: 'text-purple-600 dark:text-purple-400',
        bgColor: 'bg-purple-100 dark:bg-purple-900/30 hover:bg-purple-200 dark:hover:bg-purple-900/50',
        url: '/finance',
    },    
    {
        id: 'event',
        label: t('dashboard.viewSchedule'),
        icon: CalendarPlus,
        color: 'text-blue-600 dark:text-blue-400',
        bgColor: 'bg-blue-100 dark:bg-blue-900/30 hover:bg-blue-200 dark:hover:bg-blue-900/50',
        url: '/schedules',
    },
    {
        id: 'note',
        label: t('dashboard.createNote'),
        icon: FileText,
        color: 'text-amber-600 dark:text-amber-400',
        bgColor: 'bg-amber-100 dark:bg-amber-900/30 hover:bg-amber-200 dark:hover:bg-amber-900/50',
        url: '/notes',
    },
]);

const handleAction = (url: string) => {
    router.visit(url);
};
</script>

<template>
    <Card>
        <CardHeader class="pb-2">
            <CardTitle class="flex items-center gap-2 text-base font-medium">
                <Zap class="h-4 w-4" />
                {{ $t('dashboard.quickActions') }}
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
                    @click="handleAction(action.url)"
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
