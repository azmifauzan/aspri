<script setup lang="ts">
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardDescription, CardFooter, CardHeader, CardTitle } from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select';
import AppLayout from '@/layouts/AppLayout.vue';
import type { Plugin, PluginIndexProps } from '@/types';
import { Head, Link, router } from '@inertiajs/vue3';
import { CheckCircle, Cog, Puzzle, Search, XCircle } from 'lucide-vue-next';
import { computed, ref } from 'vue';

const props = defineProps<PluginIndexProps>();

const searchQuery = ref('');
const statusFilter = ref<'all' | 'active' | 'inactive'>('all');

const filteredPlugins = computed(() => {
    let result = props.plugins;

    // Filter by search
    if (searchQuery.value) {
        const query = searchQuery.value.toLowerCase();
        result = result.filter(
            (p) => p.name.toLowerCase().includes(query) || p.description?.toLowerCase().includes(query),
        );
    }

    // Filter by status
    if (statusFilter.value === 'active') {
        result = result.filter((p) => p.user_is_active);
    } else if (statusFilter.value === 'inactive') {
        result = result.filter((p) => !p.user_is_active);
    }

    return result;
});

const getPluginIcon = (plugin: Plugin) => {
    // Map icon names to components - using Puzzle as default
    return Puzzle;
};

const togglePlugin = (plugin: Plugin) => {
    if (plugin.user_is_active) {
        router.post(
            route('plugins.deactivate', plugin.id),
            {},
            {
                preserveScroll: true,
            },
        );
    } else {
        router.post(
            route('plugins.activate', plugin.id),
            {},
            {
                preserveScroll: true,
            },
        );
    }
};
</script>

<template>
    <Head title="Plugins" />

    <AppLayout>
        <template #header>
            <div class="flex items-center justify-between">
                <div>
                    <h2 class="text-xl font-semibold leading-tight text-gray-800 dark:text-gray-200">Plugins</h2>
                    <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">Kelola dan konfigurasi plugin untuk memperluas kemampuan ASPRI</p>
                </div>
            </div>
        </template>

        <div class="py-6">
            <div class="mx-auto max-w-7xl sm:px-6 lg:px-8">
                <!-- Filters -->
                <Card class="mb-6">
                    <CardContent class="pt-6">
                        <div class="flex flex-col gap-4 md:flex-row md:items-center">
                            <div class="relative flex-1">
                                <Search class="absolute left-3 top-1/2 h-4 w-4 -translate-y-1/2 text-muted-foreground" />
                                <Input v-model="searchQuery" placeholder="Cari plugin..." class="pl-10" />
                            </div>
                            <Select v-model="statusFilter">
                                <SelectTrigger class="w-full md:w-40">
                                    <SelectValue placeholder="Semua Status" />
                                </SelectTrigger>
                                <SelectContent>
                                    <SelectItem value="all">Semua Status</SelectItem>
                                    <SelectItem value="active">Aktif</SelectItem>
                                    <SelectItem value="inactive">Tidak Aktif</SelectItem>
                                </SelectContent>
                            </Select>
                        </div>
                    </CardContent>
                </Card>

                <!-- Empty State -->
                <div v-if="filteredPlugins.length === 0" class="flex flex-col items-center justify-center rounded-lg border border-dashed py-20 text-center">
                    <div class="rounded-full bg-gray-100 p-4 dark:bg-gray-800">
                        <Puzzle :size="32" class="text-gray-400" />
                    </div>
                    <h3 class="mt-4 text-lg font-medium text-gray-900 dark:text-gray-100">Tidak ada plugin</h3>
                    <p class="mt-1 text-gray-500 dark:text-gray-400">
                        {{ searchQuery || statusFilter !== 'all' ? 'Tidak ada plugin yang sesuai dengan filter.' : 'Belum ada plugin yang tersedia.' }}
                    </p>
                </div>

                <!-- Plugin Grid -->
                <div v-else class="grid grid-cols-1 gap-6 sm:grid-cols-2 lg:grid-cols-3">
                    <Card v-for="plugin in filteredPlugins" :key="plugin.id" class="flex flex-col transition-shadow hover:shadow-md">
                        <CardHeader>
                            <div class="flex items-start justify-between">
                                <div class="flex items-center gap-3">
                                    <div class="flex h-10 w-10 items-center justify-center rounded-lg bg-primary/10">
                                        <component :is="getPluginIcon(plugin)" class="h-5 w-5 text-primary" />
                                    </div>
                                    <div>
                                        <CardTitle class="text-lg">{{ plugin.name }}</CardTitle>
                                        <p class="text-xs text-muted-foreground">v{{ plugin.version }}</p>
                                    </div>
                                </div>
                                <Badge :variant="plugin.user_is_active ? 'default' : 'secondary'">
                                    <component :is="plugin.user_is_active ? CheckCircle : XCircle" class="mr-1 h-3 w-3" />
                                    {{ plugin.user_is_active ? 'Aktif' : 'Tidak Aktif' }}
                                </Badge>
                            </div>
                        </CardHeader>
                        <CardContent class="flex-1">
                            <CardDescription class="line-clamp-3">
                                {{ plugin.description || 'Tidak ada deskripsi.' }}
                            </CardDescription>
                            <div class="mt-3 flex flex-wrap gap-2">
                                <Badge v-if="plugin.is_system" variant="outline">Sistem</Badge>
                                <Badge v-if="plugin.author" variant="outline">{{ plugin.author }}</Badge>
                            </div>
                        </CardContent>
                        <CardFooter class="flex gap-2 border-t pt-4">
                            <Button :variant="plugin.user_is_active ? 'outline' : 'default'" size="sm" class="flex-1" @click="togglePlugin(plugin)">
                                {{ plugin.user_is_active ? 'Nonaktifkan' : 'Aktifkan' }}
                            </Button>
                            <Button v-if="plugin.user_is_active" variant="outline" size="sm" as-child>
                                <Link :href="route('plugins.show', plugin.id)">
                                    <Cog class="h-4 w-4" />
                                </Link>
                            </Button>
                        </CardFooter>
                    </Card>
                </div>
            </div>
        </div>
    </AppLayout>
</template>
