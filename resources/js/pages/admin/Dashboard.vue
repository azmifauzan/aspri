<script setup lang="ts">
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import AdminLayout from '@/layouts/AdminLayout.vue';
import admin from '@/routes/admin';
import type { AdminDashboardProps, BreadcrumbItem } from '@/types';
import { Head, router } from '@inertiajs/vue3';
import {
    Activity,
    AlertTriangle,
    Database,
    HardDrive,
    MessageSquare,
    RefreshCw,
    Server,
    Users,
    Zap,
} from 'lucide-vue-next';
import { computed, onMounted, onUnmounted, ref } from 'vue';

const props = defineProps<AdminDashboardProps>();

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Admin', href: admin.index().url },
    { title: 'Dashboard' },
];

const isRefreshing = ref(false);
const autoRefresh = ref(false);
let refreshInterval: ReturnType<typeof setInterval> | null = null;

const refresh = () => {
    isRefreshing.value = true;
    router.reload({
        onFinish: () => {
            isRefreshing.value = false;
        },
    });
};

const toggleAutoRefresh = () => {
    autoRefresh.value = !autoRefresh.value;
    if (autoRefresh.value) {
        refreshInterval = setInterval(refresh, 30000); // Refresh every 30 seconds
    } else if (refreshInterval) {
        clearInterval(refreshInterval);
        refreshInterval = null;
    }
};

onMounted(() => {
    // Optional: Start auto-refresh by default
});

onUnmounted(() => {
    if (refreshInterval) {
        clearInterval(refreshInterval);
    }
});

const queueHealth = computed(() => {
    if (props.queueStats.failed_jobs > 10) return 'critical';
    if (props.queueStats.failed_jobs > 0) return 'warning';
    return 'healthy';
});
</script>

<template>
    <Head title="Admin Dashboard" />

    <AdminLayout :breadcrumbs="breadcrumbs">
        <div class="flex h-full flex-1 flex-col gap-6 p-4 md:p-6">
            <!-- Header Actions -->
            <div class="flex items-center justify-between">
                <div>
                    <h1 class="text-2xl font-bold tracking-tight">System Monitoring</h1>
                    <p class="text-muted-foreground">Real-time server and application metrics</p>
                </div>
                <div class="flex items-center gap-2">
                    <Button variant="outline" size="sm" :disabled="isRefreshing" @click="refresh">
                        <RefreshCw :class="['h-4 w-4', isRefreshing && 'animate-spin']" />
                        Refresh
                    </Button>
                    <Button :variant="autoRefresh ? 'default' : 'outline'" size="sm" @click="toggleAutoRefresh">
                        <Zap class="h-4 w-4" />
                        {{ autoRefresh ? 'Auto: ON' : 'Auto: OFF' }}
                    </Button>
                </div>
            </div>

            <!-- Stats Overview -->
            <div class="grid gap-4 md:grid-cols-2 lg:grid-cols-4">
                <Card>
                    <CardHeader class="flex flex-row items-center justify-between space-y-0 pb-2">
                        <CardTitle class="text-sm font-medium">Total Users</CardTitle>
                        <Users class="h-4 w-4 text-muted-foreground" />
                    </CardHeader>
                    <CardContent>
                        <div class="text-2xl font-bold">{{ userStats.total }}</div>
                        <p class="text-xs text-muted-foreground">
                            {{ userStats.active }} active, {{ userStats.inactive }} inactive
                        </p>
                    </CardContent>
                </Card>

                <Card>
                    <CardHeader class="flex flex-row items-center justify-between space-y-0 pb-2">
                        <CardTitle class="text-sm font-medium">AI Messages</CardTitle>
                        <MessageSquare class="h-4 w-4 text-muted-foreground" />
                    </CardHeader>
                    <CardContent>
                        <div class="text-2xl font-bold">{{ aiUsageStats.total_messages }}</div>
                        <p class="text-xs text-muted-foreground">{{ aiUsageStats.messages_today }} today</p>
                    </CardContent>
                </Card>

                <Card>
                    <CardHeader class="flex flex-row items-center justify-between space-y-0 pb-2">
                        <CardTitle class="text-sm font-medium">Queue Jobs</CardTitle>
                        <Database class="h-4 w-4 text-muted-foreground" />
                    </CardHeader>
                    <CardContent>
                        <div class="text-2xl font-bold">{{ queueStats.pending_jobs }}</div>
                        <div class="flex items-center gap-2">
                            <Badge :variant="queueHealth === 'healthy' ? 'default' : queueHealth === 'warning' ? 'secondary' : 'destructive'">
                                {{ queueStats.failed_jobs }} failed
                            </Badge>
                        </div>
                    </CardContent>
                </Card>

                <Card>
                    <CardHeader class="flex flex-row items-center justify-between space-y-0 pb-2">
                        <CardTitle class="text-sm font-medium">Activities Today</CardTitle>
                        <Activity class="h-4 w-4 text-muted-foreground" />
                    </CardHeader>
                    <CardContent>
                        <div class="text-2xl font-bold">{{ activityStats.total_today }}</div>
                        <p class="text-xs text-muted-foreground">{{ activityStats.total_week }} this week</p>
                    </CardContent>
                </Card>
            </div>

            <!-- Server Health & Queue Stats -->
            <div class="grid gap-6 lg:grid-cols-2">
                <!-- Server Health -->
                <Card>
                    <CardHeader>
                        <CardTitle class="flex items-center gap-2">
                            <Server class="h-5 w-5" />
                            Server Health
                        </CardTitle>
                        <CardDescription>System resources and PHP configuration</CardDescription>
                    </CardHeader>
                    <CardContent class="space-y-4">
                        <div class="grid grid-cols-2 gap-4">
                            <div class="space-y-1">
                                <p class="text-sm text-muted-foreground">PHP Version</p>
                                <p class="font-medium">{{ serverHealth.php_version }}</p>
                            </div>
                            <div class="space-y-1">
                                <p class="text-sm text-muted-foreground">Laravel Version</p>
                                <p class="font-medium">{{ serverHealth.laravel_version }}</p>
                            </div>
                            <div class="space-y-1">
                                <p class="text-sm text-muted-foreground">Memory Usage</p>
                                <p class="font-medium">{{ serverHealth.memory_usage }}</p>
                            </div>
                            <div class="space-y-1">
                                <p class="text-sm text-muted-foreground">Memory Limit</p>
                                <p class="font-medium">{{ serverHealth.memory_limit }}</p>
                            </div>
                        </div>

                        <div class="space-y-2">
                            <div class="flex items-center justify-between text-sm">
                                <span class="flex items-center gap-2">
                                    <HardDrive class="h-4 w-4" />
                                    Disk Usage
                                </span>
                                <span>{{ serverHealth.disk_free }} free</span>
                            </div>
                            <div class="h-2 w-full rounded-full bg-secondary">
                                <div class="h-2 rounded-full bg-primary" style="width: 45%" />
                            </div>
                        </div>

                        <div v-if="serverHealth.uptime" class="pt-2 border-t">
                            <p class="text-sm text-muted-foreground">Server Uptime: {{ serverHealth.uptime }}</p>
                        </div>
                    </CardContent>
                </Card>

                <!-- Queue Status -->
                <Card>
                    <CardHeader>
                        <CardTitle class="flex items-center gap-2">
                            <Database class="h-5 w-5" />
                            Queue Status
                        </CardTitle>
                        <CardDescription>Job queues and failed jobs monitoring</CardDescription>
                    </CardHeader>
                    <CardContent class="space-y-4">
                        <div class="grid grid-cols-2 gap-4">
                            <div class="rounded-lg border p-4">
                                <p class="text-sm text-muted-foreground">Pending Jobs</p>
                                <p class="text-3xl font-bold">{{ queueStats.pending_jobs }}</p>
                            </div>
                            <div class="rounded-lg border p-4" :class="queueStats.failed_jobs > 0 && 'border-destructive bg-destructive/5'">
                                <p class="text-sm text-muted-foreground">Failed Jobs</p>
                                <p class="text-3xl font-bold" :class="queueStats.failed_jobs > 0 && 'text-destructive'">
                                    {{ queueStats.failed_jobs }}
                                </p>
                            </div>
                        </div>

                        <div v-if="Object.keys(queueStats.jobs_by_queue).length > 0">
                            <p class="text-sm font-medium mb-2">Jobs by Queue</p>
                            <div class="space-y-2">
                                <div v-for="(count, queue) in queueStats.jobs_by_queue" :key="queue" class="flex items-center justify-between text-sm">
                                    <span>{{ queue }}</span>
                                    <Badge variant="secondary">{{ count }}</Badge>
                                </div>
                            </div>
                        </div>

                        <div v-if="queueStats.recent_failed_jobs.length > 0">
                            <p class="text-sm font-medium mb-2 flex items-center gap-2">
                                <AlertTriangle class="h-4 w-4 text-destructive" />
                                Recent Failed Jobs
                            </p>
                            <div class="space-y-2 max-h-32 overflow-y-auto">
                                <div v-for="job in queueStats.recent_failed_jobs" :key="job.id" class="text-xs p-2 rounded bg-muted">
                                    <div class="flex justify-between">
                                        <span class="font-medium">{{ job.queue }}</span>
                                        <span class="text-muted-foreground">{{ job.failed_at }}</span>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </CardContent>
                </Card>
            </div>

            <!-- Database & Recent Activity -->
            <div class="grid gap-6 lg:grid-cols-2">
                <!-- Database Stats -->
                <Card>
                    <CardHeader>
                        <CardTitle>Database Overview</CardTitle>
                        <CardDescription>Connection: {{ databaseStats.connection }}</CardDescription>
                    </CardHeader>
                    <CardContent>
                        <div class="space-y-4">
                            <div class="grid grid-cols-3 gap-4 text-center">
                                <div class="rounded-lg border p-3">
                                    <p class="text-2xl font-bold">{{ databaseStats.total_users }}</p>
                                    <p class="text-xs text-muted-foreground">Users</p>
                                </div>
                                <div class="rounded-lg border p-3">
                                    <p class="text-2xl font-bold">{{ databaseStats.total_messages }}</p>
                                    <p class="text-xs text-muted-foreground">Messages</p>
                                </div>
                                <div class="rounded-lg border p-3">
                                    <p class="text-2xl font-bold">{{ databaseStats.total_transactions }}</p>
                                    <p class="text-xs text-muted-foreground">Transactions</p>
                                </div>
                            </div>

                            <div v-if="databaseStats.tables.length > 0">
                                <p class="text-sm font-medium mb-2">Largest Tables</p>
                                <div class="space-y-1">
                                    <div v-for="table in databaseStats.tables.slice(0, 5)" :key="table.name" class="flex items-center justify-between text-sm">
                                        <span class="font-mono text-xs">{{ table.name }}</span>
                                        <span class="text-muted-foreground">{{ table.rows.toLocaleString() }} rows</span>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </CardContent>
                </Card>

                <!-- Recent Activity -->
                <Card>
                    <CardHeader>
                        <CardTitle>Recent Activity</CardTitle>
                        <CardDescription>Latest system activities</CardDescription>
                    </CardHeader>
                    <CardContent>
                        <div class="space-y-3 max-h-64 overflow-y-auto">
                            <div v-for="activity in activityStats.recent.slice(0, 10)" :key="activity.id" class="flex items-start gap-3 text-sm">
                                <div class="flex h-8 w-8 shrink-0 items-center justify-center rounded-full bg-muted">
                                    <Activity class="h-4 w-4" />
                                </div>
                                <div class="flex-1 min-w-0">
                                    <p class="font-medium truncate">{{ activity.action }}</p>
                                    <p class="text-xs text-muted-foreground truncate">
                                        {{ activity.user }} - {{ activity.description || 'No description' }}
                                    </p>
                                </div>
                                <span class="text-xs text-muted-foreground whitespace-nowrap">
                                    {{ new Date(activity.created_at).toLocaleTimeString() }}
                                </span>
                            </div>
                            <div v-if="activityStats.recent.length === 0" class="text-center py-8 text-muted-foreground">
                                No recent activities
                            </div>
                        </div>
                    </CardContent>
                </Card>
            </div>

            <!-- User Stats -->
            <Card>
                <CardHeader>
                    <CardTitle>User Statistics</CardTitle>
                    <CardDescription>User registration and role distribution</CardDescription>
                </CardHeader>
                <CardContent>
                    <div class="grid gap-6 md:grid-cols-3">
                        <div>
                            <h4 class="font-medium mb-3">Users by Role</h4>
                            <div class="space-y-2">
                                <div v-for="(count, role) in userStats.by_role" :key="role" class="flex items-center justify-between">
                                    <span class="capitalize">{{ role.replace('_', ' ') }}</span>
                                    <Badge>{{ count }}</Badge>
                                </div>
                            </div>
                        </div>
                        <div>
                            <h4 class="font-medium mb-3">Registration Stats</h4>
                            <div class="space-y-2">
                                <div class="flex justify-between">
                                    <span class="text-muted-foreground">Today</span>
                                    <span class="font-medium">{{ userStats.today }}</span>
                                </div>
                                <div class="flex justify-between">
                                    <span class="text-muted-foreground">This Week</span>
                                    <span class="font-medium">{{ userStats.this_week }}</span>
                                </div>
                                <div class="flex justify-between">
                                    <span class="text-muted-foreground">This Month</span>
                                    <span class="font-medium">{{ userStats.this_month }}</span>
                                </div>
                            </div>
                        </div>
                        <div>
                            <h4 class="font-medium mb-3">Recent Users</h4>
                            <div class="space-y-2">
                                <div v-for="user in userStats.recent.slice(0, 4)" :key="user.id" class="flex items-center gap-2">
                                    <div class="h-6 w-6 rounded-full bg-muted flex items-center justify-center text-xs font-medium">
                                        {{ user.name.charAt(0).toUpperCase() }}
                                    </div>
                                    <span class="text-sm truncate">{{ user.name }}</span>
                                </div>
                            </div>
                        </div>
                    </div>
                </CardContent>
            </Card>
        </div>
    </AdminLayout>
</template>
