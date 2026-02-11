<script setup lang="ts">
import {
    MonthlySummaryCard,
    QuickActionsCard,
    RecentActivityCard,
    SubscriptionCard,
    TelegramCard,
    TodayScheduleCard,
    WeeklyChartCard,
    WelcomeCard,
} from '@/components/dashboard';
import AppLayout from '@/layouts/AppLayout.vue';
import { dashboard } from '@/routes';
import type {
    BreadcrumbItem,
    ChatLimit,
    MonthlySummary,
    RecentActivity,
    SubscriptionInfo,
    TelegramInfo,
    TodayEvent,
    WeeklyExpense,
} from '@/types';
import { Head, usePage } from '@inertiajs/vue3';

// Use usePage to get props directly since defineProps has issues with some props
const page = usePage<{
    monthlySummary: MonthlySummary;
    todayEvents: TodayEvent[];
    weeklyExpenses: WeeklyExpense[];
    recentActivities: RecentActivity[];
    subscriptionInfo: SubscriptionInfo;
    chatLimit: ChatLimit;
    telegramInfo: TelegramInfo;
}>();

const breadcrumbs: BreadcrumbItem[] = [
    {
        title: 'Dashboard',
        href: dashboard().url,
    },
];

// Type-safe data access from page props
const monthlySummary = page.props.monthlySummary;
const todayEvents = page.props.todayEvents;
const weeklyExpenses = page.props.weeklyExpenses;
const recentActivities = page.props.recentActivities;
const subscriptionInfo = page.props.subscriptionInfo;
const chatLimit = page.props.chatLimit;
const telegramInfo = page.props.telegramInfo;
</script>

<template>
    <Head title="Dashboard" />

    <AppLayout :breadcrumbs="breadcrumbs">
        <div class="flex h-full flex-1 flex-col gap-4 p-4">
            <!-- Welcome Card - Full Width -->
            <WelcomeCard />

            <!-- Baris 1: Subscription, Telegram, Quick Actions -->
            <div class="grid gap-4 md:grid-cols-2 lg:grid-cols-3">
                <SubscriptionCard :subscription-info="subscriptionInfo" :chat-limit="chatLimit" />
                <TelegramCard :telegram-info="telegramInfo" />
                <QuickActionsCard />
            </div>

            <!-- Baris 2: Monthly Summary, Today Schedule -->
            <div class="grid gap-4 md:grid-cols-2">
                <MonthlySummaryCard :summary="monthlySummary" />
                <TodayScheduleCard :events="todayEvents" />
            </div>

            <!-- Baris 3: Weekly Chart & Recent Activity -->
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
