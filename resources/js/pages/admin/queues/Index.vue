<script setup lang="ts">
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import AdminLayout from '@/layouts/AdminLayout.vue';
import admin from '@/routes/admin';
import type { BreadcrumbItem, QueueMonitorPageProps } from '@/types';
import { Head, router } from '@inertiajs/vue3';
import { AlertTriangle, Database, Play, RefreshCw, RotateCcw, Trash2, Zap } from 'lucide-vue-next';
import { computed, onMounted, onUnmounted, ref } from 'vue';

const props = defineProps<QueueMonitorPageProps>();

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Admin', href: admin.index().url },
    { title: 'Queue Monitor' },
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
        refreshInterval = setInterval(refresh, 5000); // Refresh every 5 seconds
    } else if (refreshInterval) {
        clearInterval(refreshInterval);
        refreshInterval = null;
    }
};

onUnmounted(() => {
    if (refreshInterval) {
        clearInterval(refreshInterval);
    }
});

const retryJob = (id: number) => {
    router.post(admin.queues.retry({ id }).url, {}, { preserveScroll: true });
};

const retryAll = () => {
    if (confirm('Are you sure you want to retry all failed jobs?')) {
        router.post(admin.queues.retryAll().url, {}, { preserveScroll: true });
    }
};

const deleteJob = (id: number) => {
    if (confirm('Are you sure you want to delete this failed job?')) {
        router.delete(admin.queues.delete({ id }).url, { preserveScroll: true });
    }
};

const flushFailed = () => {
    if (confirm('Are you sure you want to delete ALL failed jobs? This cannot be undone.')) {
        router.post(admin.queues.flush().url, {}, { preserveScroll: true });
    }
};

const clearPending = () => {
    if (confirm('WARNING: This will delete ALL pending jobs. Are you absolutely sure?')) {
        router.post(admin.queues.clear().url, {}, { preserveScroll: true });
    }
};

const queueHealth = computed(() => {
    if (props.stats.failed_jobs > 10) return 'critical';
    if (props.stats.failed_jobs > 0) return 'warning';
    return 'healthy';
});
</script>

<template>
    <Head title="Queue Monitor" />

    <AdminLayout :breadcrumbs="breadcrumbs">
        <div class="flex h-full flex-1 flex-col gap-6 p-4 md:p-6">
            <!-- Header -->
            <div class="flex items-center justify-between">
                <div>
                    <h1 class="text-2xl font-bold tracking-tight">Queue Monitor</h1>
                    <p class="text-muted-foreground">Monitor and manage background jobs</p>
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
            <div class="grid gap-4 md:grid-cols-3">
                <Card>
                    <CardHeader class="flex flex-row items-center justify-between space-y-0 pb-2">
                        <CardTitle class="text-sm font-medium">Pending Jobs</CardTitle>
                        <Database class="h-4 w-4 text-muted-foreground" />
                    </CardHeader>
                    <CardContent>
                        <div class="text-4xl font-bold">{{ stats.pending_jobs }}</div>
                        <p class="text-xs text-muted-foreground">Jobs waiting to be processed</p>
                    </CardContent>
                </Card>

                <Card :class="stats.failed_jobs > 0 && 'border-destructive'">
                    <CardHeader class="flex flex-row items-center justify-between space-y-0 pb-2">
                        <CardTitle class="text-sm font-medium">Failed Jobs</CardTitle>
                        <AlertTriangle :class="['h-4 w-4', stats.failed_jobs > 0 ? 'text-destructive' : 'text-muted-foreground']" />
                    </CardHeader>
                    <CardContent>
                        <div class="text-4xl font-bold" :class="stats.failed_jobs > 0 && 'text-destructive'">
                            {{ stats.failed_jobs }}
                        </div>
                        <p class="text-xs text-muted-foreground">Jobs that need attention</p>
                    </CardContent>
                </Card>

                <Card>
                    <CardHeader class="flex flex-row items-center justify-between space-y-0 pb-2">
                        <CardTitle class="text-sm font-medium">Queue Health</CardTitle>
                        <div :class="['h-3 w-3 rounded-full', queueHealth === 'healthy' ? 'bg-green-500' : queueHealth === 'warning' ? 'bg-yellow-500' : 'bg-red-500']" />
                    </CardHeader>
                    <CardContent>
                        <div class="text-2xl font-bold capitalize">{{ queueHealth }}</div>
                        <p class="text-xs text-muted-foreground">System status</p>
                    </CardContent>
                </Card>
            </div>

            <!-- Jobs by Queue -->
            <Card v-if="Object.keys(stats.jobs_by_queue).length > 0">
                <CardHeader>
                    <CardTitle>Jobs by Queue</CardTitle>
                    <CardDescription>Distribution of pending jobs across queues</CardDescription>
                </CardHeader>
                <CardContent>
                    <div class="grid gap-4 md:grid-cols-4">
                        <div v-for="(count, queue) in stats.jobs_by_queue" :key="queue" class="flex items-center justify-between rounded-lg border p-4">
                            <span class="font-medium">{{ queue }}</span>
                            <Badge variant="secondary">{{ count }}</Badge>
                        </div>
                    </div>
                </CardContent>
            </Card>

            <!-- Failed Jobs -->
            <Card>
                <CardHeader>
                    <div class="flex items-center justify-between">
                        <div>
                            <CardTitle>Failed Jobs</CardTitle>
                            <CardDescription>Recently failed jobs that need attention</CardDescription>
                        </div>
                        <div v-if="stats.failed_jobs > 0" class="flex items-center gap-2">
                            <Button variant="outline" size="sm" @click="retryAll">
                                <RotateCcw class="mr-2 h-4 w-4" />
                                Retry All
                            </Button>
                            <Button variant="destructive" size="sm" @click="flushFailed">
                                <Trash2 class="mr-2 h-4 w-4" />
                                Flush All
                            </Button>
                        </div>
                    </div>
                </CardHeader>
                <CardContent>
                    <div v-if="stats.recent_failed_jobs.length > 0" class="space-y-4">
                        <div v-for="job in stats.recent_failed_jobs" :key="job.id" class="rounded-lg border p-4">
                            <div class="flex items-start justify-between">
                                <div class="flex-1">
                                    <div class="flex items-center gap-2">
                                        <Badge variant="destructive">{{ job.queue }}</Badge>
                                        <span class="text-sm text-muted-foreground">ID: {{ job.id }}</span>
                                    </div>
                                    <p class="mt-2 text-sm text-muted-foreground">
                                        Failed at: {{ job.failed_at }}
                                    </p>
                                    <div class="mt-2 max-h-24 overflow-y-auto rounded bg-muted p-2">
                                        <pre class="text-xs">{{ job.exception }}</pre>
                                    </div>
                                </div>
                                <div class="ml-4 flex items-center gap-2">
                                    <Button variant="outline" size="icon-sm" title="Retry" @click="retryJob(job.id)">
                                        <Play class="h-4 w-4" />
                                    </Button>
                                    <Button variant="ghost" size="icon-sm" title="Delete" class="text-destructive hover:text-destructive" @click="deleteJob(job.id)">
                                        <Trash2 class="h-4 w-4" />
                                    </Button>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div v-else class="py-8 text-center text-muted-foreground">
                        <Database class="mx-auto mb-2 h-8 w-8" />
                        No failed jobs
                    </div>
                </CardContent>
            </Card>

            <!-- Job Batches -->
            <Card v-if="stats.recent_batches.length > 0">
                <CardHeader>
                    <CardTitle>Recent Batches</CardTitle>
                    <CardDescription>Job batch processing status</CardDescription>
                </CardHeader>
                <CardContent>
                    <div class="overflow-x-auto">
                        <table class="w-full">
                            <thead>
                                <tr class="border-b text-left">
                                    <th class="pb-3 font-medium">Name</th>
                                    <th class="pb-3 font-medium">Total</th>
                                    <th class="pb-3 font-medium">Pending</th>
                                    <th class="pb-3 font-medium">Failed</th>
                                    <th class="pb-3 font-medium">Status</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y">
                                <tr v-for="batch in stats.recent_batches" :key="batch.id">
                                    <td class="py-3 font-medium">{{ batch.name }}</td>
                                    <td class="py-3">{{ batch.total_jobs }}</td>
                                    <td class="py-3">{{ batch.pending_jobs }}</td>
                                    <td class="py-3">
                                        <span :class="batch.failed_jobs > 0 && 'text-destructive'">
                                            {{ batch.failed_jobs }}
                                        </span>
                                    </td>
                                    <td class="py-3">
                                        <Badge :variant="batch.finished_at ? 'default' : 'secondary'">
                                            {{ batch.finished_at ? 'Completed' : 'Running' }}
                                        </Badge>
                                    </td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </CardContent>
            </Card>

            <!-- Danger Zone -->
            <Card class="border-destructive">
                <CardHeader>
                    <CardTitle class="text-destructive">Danger Zone</CardTitle>
                    <CardDescription>Destructive actions - use with caution</CardDescription>
                </CardHeader>
                <CardContent>
                    <div class="flex items-center justify-between">
                        <div>
                            <p class="font-medium">Clear All Pending Jobs</p>
                            <p class="text-sm text-muted-foreground">
                                This will permanently delete all pending jobs. This action cannot be undone.
                            </p>
                        </div>
                        <Button variant="destructive" :disabled="stats.pending_jobs === 0" @click="clearPending">
                            <Trash2 class="mr-2 h-4 w-4" />
                            Clear Pending
                        </Button>
                    </div>
                </CardContent>
            </Card>
        </div>
    </AdminLayout>
</template>
