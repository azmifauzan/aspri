<script setup lang="ts">
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import type { TodayEvent } from '@/types';
import { Calendar, Clock } from 'lucide-vue-next';

defineProps<{
    events: TodayEvent[];
}>();

const getEventTypeColor = (type: string) => {
    const colors: Record<string, string> = {
        meeting: 'bg-blue-500',
        work: 'bg-amber-500',
        personal: 'bg-emerald-500',
        reminder: 'bg-purple-500',
    };
    return colors[type] || 'bg-gray-500';
};
</script>

<template>
    <Card>
        <CardHeader class="pb-2">
            <CardTitle class="flex items-center gap-2 text-base font-medium">
                <Calendar class="h-4 w-4" />
                Jadwal Hari Ini
            </CardTitle>
        </CardHeader>
        <CardContent>
            <!-- Empty State -->
            <div
                v-if="events.length === 0"
                class="flex flex-col items-center justify-center py-8 text-center"
            >
                <Calendar class="h-12 w-12 text-muted-foreground/50" />
                <p class="mt-2 text-sm text-muted-foreground">
                    Tidak ada jadwal hari ini
                </p>
            </div>

            <!-- Events List -->
            <div v-else class="space-y-3">
                <div
                    v-for="event in events"
                    :key="event.id"
                    class="flex items-start gap-3 rounded-lg border border-border p-3 transition-colors hover:bg-accent/50"
                >
                    <div
                        class="mt-0.5 h-2 w-2 rounded-full"
                        :class="getEventTypeColor(event.type)"
                    />
                    <div class="min-w-0 flex-1">
                        <p class="truncate font-medium">{{ event.title }}</p>
                        <div
                            class="mt-1 flex items-center gap-1 text-xs text-muted-foreground"
                        >
                            <Clock class="h-3 w-3" />
                            <span>{{ event.time }}</span>
                            <span v-if="event.endTime">
                                - {{ event.endTime }}
                            </span>
                        </div>
                    </div>
                </div>
            </div>
        </CardContent>
    </Card>
</template>
