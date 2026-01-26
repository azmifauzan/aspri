<script setup lang="ts">
import {
    MonthlySummaryCard,
    QuickActionsCard,
    RecentActivityCard,
    TodayScheduleCard,
    WeeklyChartCard,
    WelcomeCard,
} from '@/components/dashboard';
import AppLayout from '@/layouts/AppLayout.vue';
import { dashboard } from '@/routes';
import type {
    BreadcrumbItem,
    DashboardProps,
    MonthlySummary,
    RecentActivity,
    TodayEvent,
    WeeklyExpense,
} from '@/types';
import { Head } from '@inertiajs/vue3';

const props = defineProps<DashboardProps>();

const breadcrumbs: BreadcrumbItem[] = [
    {
        title: 'Dashboard',
        href: dashboard().url,
    },
];

// Type-safe data access
const monthlySummary: MonthlySummary = props.monthlySummary;
const todayEvents: TodayEvent[] = props.todayEvents;
const weeklyExpenses: WeeklyExpense[] = props.weeklyExpenses;
const recentActivities: RecentActivity[] = props.recentActivities;
</script>

<template>
    <Head title="Dashboard" />

    <AppLayout :breadcrumbs="breadcrumbs">
        <div class="flex h-full flex-1 flex-col gap-4 p-4">
            <!-- Welcome Card - Full Width -->
            <WelcomeCard />

            <!-- Grid: Summary, Schedule, Quick Actions -->
            <div class="grid gap-4 md:grid-cols-3">
                <MonthlySummaryCard :summary="monthlySummary" />
                <TodayScheduleCard :events="todayEvents" />
                <QuickActionsCard />
            </div>

            <!-- Grid: Weekly Chart & Recent Activity -->
            <div class="grid gap-4 lg:grid-cols-3">
                <div class="lg:col-span-2">
                    <WeeklyChartCard :expenses="weeklyExpenses" />
                </div>
                <div>
                    <RecentActivityCard :activities="recentActivities" />
                </div>
            </div>
        </div>
    </AppLayout>
</template>
