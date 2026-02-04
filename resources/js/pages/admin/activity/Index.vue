<script setup lang="ts">
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select';
import AdminLayout from '@/layouts/AdminLayout.vue';
import admin from '@/routes/admin';
import type { ActivityLogsPageProps, BreadcrumbItem } from '@/types';
import { Head, router } from '@inertiajs/vue3';
import { debounce } from 'lodash-es';
import { Activity, Calendar, ChevronLeft, ChevronRight, Search } from 'lucide-vue-next';
import { ref, watch } from 'vue';

const props = defineProps<ActivityLogsPageProps>();

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Admin', href: admin.index().url },
    { title: 'Activity Logs' },
];

const searchQuery = ref(props.filters.search || '');
const selectedAction = ref(props.filters.action || 'all');
const startDate = ref(props.filters.start_date || '');
const endDate = ref(props.filters.end_date || '');

const debouncedSearch = debounce(() => {
    router.get(
        admin.activity.index().url,
        {
            search: searchQuery.value || undefined,
            action: selectedAction.value !== 'all' ? selectedAction.value : undefined,
            start_date: startDate.value || undefined,
            end_date: endDate.value || undefined,
        },
        { preserveState: true, replace: true },
    );
}, 300);

watch([searchQuery, selectedAction, startDate, endDate], () => {
    debouncedSearch();
});

const clearFilters = () => {
    searchQuery.value = '';
    selectedAction.value = 'all';
    startDate.value = '';
    endDate.value = '';
};

const actionColors: Record<string, string> = {
    create: 'default',
    update: 'secondary',
    delete: 'destructive',
    login: 'default',
    logout: 'secondary',
    view: 'outline',
};
</script>

<template>
    <Head title="Activity Logs" />

    <AdminLayout :breadcrumbs="breadcrumbs">
        <div class="flex h-full flex-1 flex-col gap-6 p-4 md:p-6">
            <div>
                <h1 class="text-2xl font-bold tracking-tight">Activity Logs</h1>
                <p class="text-muted-foreground">Track all user and system activities</p>
            </div>

            <!-- Filters -->
            <Card>
                <CardContent class="pt-6">
                    <div class="flex flex-col gap-4 md:flex-row md:items-center">
                        <div class="relative flex-1">
                            <Search class="absolute left-3 top-1/2 h-4 w-4 -translate-y-1/2 text-muted-foreground" />
                            <Input v-model="searchQuery" placeholder="Search activities..." class="pl-10" />
                        </div>
                        <Select v-model="selectedAction">
                            <SelectTrigger class="w-full md:w-40">
                                <SelectValue placeholder="All Actions" />
                            </SelectTrigger>
                            <SelectContent>
                                <SelectItem value="all">All Actions</SelectItem>
                                <SelectItem v-for="action in actions" :key="action" :value="action">
                                    {{ action }}
                                </SelectItem>
                            </SelectContent>
                        </Select>
                        <div class="flex items-center gap-2">
                            <Input v-model="startDate" type="date" class="w-40" placeholder="Start date" />
                            <span class="text-muted-foreground">to</span>
                            <Input v-model="endDate" type="date" class="w-40" placeholder="End date" />
                        </div>
                        <Button variant="outline" size="sm" @click="clearFilters">Clear</Button>
                    </div>
                </CardContent>
            </Card>

            <!-- Logs Table -->
            <Card>
                <CardHeader>
                    <CardTitle>Logs ({{ logs.total }})</CardTitle>
                    <CardDescription>Showing page {{ logs.current_page }} of {{ logs.last_page }}</CardDescription>
                </CardHeader>
                <CardContent>
                    <div class="overflow-x-auto">
                        <table class="w-full">
                            <thead>
                                <tr class="border-b text-left">
                                    <th class="pb-3 font-medium">Time</th>
                                    <th class="pb-3 font-medium">User</th>
                                    <th class="pb-3 font-medium">Action</th>
                                    <th class="pb-3 font-medium">Description</th>
                                    <th class="pb-3 font-medium">IP Address</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y">
                                <tr v-for="log in logs.data" :key="log.id" class="group">
                                    <td class="py-4 text-sm">
                                        <div class="flex items-center gap-2">
                                            <Calendar class="h-4 w-4 text-muted-foreground" />
                                            <span>{{ new Date(log.created_at).toLocaleString() }}</span>
                                        </div>
                                    </td>
                                    <td class="py-4">
                                        <div v-if="log.user" class="flex items-center gap-2">
                                            <div class="flex h-8 w-8 items-center justify-center rounded-full bg-muted text-xs font-medium">
                                                {{ log.user.name.charAt(0).toUpperCase() }}
                                            </div>
                                            <div>
                                                <p class="text-sm font-medium">{{ log.user.name }}</p>
                                                <p class="text-xs text-muted-foreground">{{ log.user.email }}</p>
                                            </div>
                                        </div>
                                        <span v-else class="text-sm text-muted-foreground">System</span>
                                    </td>
                                    <td class="py-4">
                                        <Badge :variant="(actionColors[log.action] || 'secondary') as any">
                                            {{ log.action }}
                                        </Badge>
                                    </td>
                                    <td class="py-4">
                                        <p class="max-w-md truncate text-sm">
                                            {{ log.description || '-' }}
                                        </p>
                                        <p v-if="log.model_type" class="text-xs text-muted-foreground">
                                            {{ log.model_type }} #{{ log.model_id }}
                                        </p>
                                    </td>
                                    <td class="py-4 text-sm text-muted-foreground">
                                        {{ log.ip_address || '-' }}
                                    </td>
                                </tr>
                                <tr v-if="logs.data.length === 0">
                                    <td colspan="5" class="py-8 text-center text-muted-foreground">
                                        <Activity class="mx-auto mb-2 h-8 w-8" />
                                        No activity logs found
                                    </td>
                                </tr>
                            </tbody>
                        </table>
                    </div>

                    <!-- Pagination -->
                    <div v-if="logs.last_page > 1" class="mt-4 flex items-center justify-between border-t pt-4">
                        <p class="text-sm text-muted-foreground">
                            Showing {{ (logs.current_page - 1) * logs.per_page + 1 }} to
                            {{ Math.min(logs.current_page * logs.per_page, logs.total) }} of {{ logs.total }} entries
                        </p>
                        <div class="flex items-center gap-2">
                            <Button
                                variant="outline"
                                size="sm"
                                :disabled="logs.current_page === 1"
                                @click="router.get(logs.links[0].url!)"
                            >
                                <ChevronLeft class="h-4 w-4" />
                                Previous
                            </Button>
                            <Button
                                variant="outline"
                                size="sm"
                                :disabled="logs.current_page === logs.last_page"
                                @click="router.get(logs.links[logs.links.length - 1].url!)"
                            >
                                Next
                                <ChevronRight class="h-4 w-4" />
                            </Button>
                        </div>
                    </div>
                </CardContent>
            </Card>
        </div>
    </AdminLayout>
</template>
