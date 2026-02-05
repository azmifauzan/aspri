<script setup lang="ts">
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardDescription, CardFooter, CardHeader, CardTitle } from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select';
import { Dialog, DialogContent, DialogDescription, DialogFooter, DialogHeader, DialogTitle } from '@/components/ui/dialog';
import { Textarea } from '@/components/ui/textarea';
import { Spinner } from '@/components/ui/spinner';
import StarRating from '@/components/StarRating.vue';
import AppLayout from '@/layouts/AppLayout.vue';
import type { BreadcrumbItem, Plugin, PluginIndexProps } from '@/types';
import { Head, Link, router, useForm } from '@inertiajs/vue3';
import { index as pluginsIndex } from '@/routes/plugins';
import { activate, deactivate, show } from '@/routes/plugins';
import { store as storeRating } from '@/routes/plugins/ratings';
import {
    Banknote,
    Bell,
    Book,
    BookOpen,
    CheckCircle,
    CheckCircle2,
    Clock,
    CloudSun,
    Cog,
    Droplets,
    Gift,
    Heart,
    Lightbulb,
    MessageSquareQuote,
    Moon,
    Newspaper,
    Puzzle,
    Search,
    Smile,
    Sparkles,
    Star,
    XCircle,
} from 'lucide-vue-next';
import { computed, ref, watch } from 'vue';

const props = defineProps<PluginIndexProps>();

const breadcrumbs: BreadcrumbItem[] = [
    {
        title: 'Plugins',
        href: pluginsIndex().url,
    },
];

const searchQuery = ref('');
const statusFilter = ref<'all' | 'active' | 'inactive'>('all');
const sortBy = ref(props.filters?.sort_by || 'rating');
const minRating = ref<number>(props.filters?.min_rating ? Number(props.filters.min_rating) : 0);
const currentPage = ref(1);
const perPage = 12;

// Loading state for plugin toggle
const togglingPluginId = ref<number | null>(null);

// Rating dialog state
const showRatingDialog = ref(false);
const selectedPlugin = ref<Plugin | null>(null);
const ratingForm = useForm({
    rating: 0,
    review: '',
});

const hoverRating = ref(0);

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

const paginatedPlugins = computed(() => {
    const start = (currentPage.value - 1) * perPage;
    const end = start + perPage;
    return filteredPlugins.value.slice(start, end);
});

const totalPages = computed(() => Math.ceil(filteredPlugins.value.length / perPage));

const paginationInfo = computed(() => {
    const total = filteredPlugins.value.length;
    const start = (currentPage.value - 1) * perPage + 1;
    const end = Math.min(currentPage.value * perPage, total);
    return { start, end, total };
});

// Reset to page 1 when filters change
const resetPage = () => {
    currentPage.value = 1;
};

const nextPage = () => {
    if (currentPage.value < totalPages.value) {
        currentPage.value++;
    }
};

const prevPage = () => {
    if (currentPage.value > 1) {
        currentPage.value--;
    }
};

// Watch for filter changes and reset page
watch([searchQuery, statusFilter], resetPage);

// Apply backend filters
watch([sortBy, minRating], () => {
    router.get(
        pluginsIndex.url(),
        {
            sort_by: sortBy.value,
            min_rating: minRating.value > 0 ? minRating.value : undefined,
        },
        {
            preserveState: true,
            preserveScroll: true,
        },
    );
});

const getPluginIcon = (plugin: Plugin) => {
    // Map icon names to Lucide components
    const iconMap: Record<string, any> = {
        // New plugins
        'banknote': Banknote,
        'cloud-sun': CloudSun,
        'mosque': Moon,
        'newspaper': Newspaper,
        'quote-left': MessageSquareQuote,
        'lightbulb': Lightbulb,
        // Existing plugins
        'gift': Gift,
        'book-open': BookOpen,
        'bell-ring': Bell,
        'check-circle': CheckCircle2,
        'heart': Heart,
        'sparkles': Sparkles,
        'emoji-happy': Smile,
        'droplets': Droplets,
        'clock': Clock,
        'book': Book,
        'puzzle': Puzzle,
    };

    return iconMap[plugin.icon] || Puzzle;
};

const togglePlugin = (plugin: Plugin) => {
    togglingPluginId.value = plugin.id;
    
    const url = plugin.user_is_active ? deactivate.url(plugin.id) : activate.url(plugin.id);
    
    router.post(url, {}, {
        preserveScroll: true,
        onFinish: () => {
            togglingPluginId.value = null;
        },
    });
};

const openRatingDialog = (plugin: Plugin) => {
    selectedPlugin.value = plugin;
    ratingForm.rating = 0;
    ratingForm.review = '';
    showRatingDialog.value = true;
};

const submitRating = () => {
    if (!selectedPlugin.value || ratingForm.rating === 0) return;

    ratingForm.post(storeRating.url(selectedPlugin.value.id), {
        preserveScroll: true,
        onSuccess: () => {
            showRatingDialog.value = false;
            ratingForm.reset();
        },
    });
};
</script>

<template>
    <Head title="Plugins" />

    <AppLayout :breadcrumbs="breadcrumbs">
        <div class="flex h-full flex-1 flex-col gap-4 p-4">
            <!-- Header -->
            <div class="flex items-center justify-between">
                <div>
                    <h1 class="text-2xl font-bold">Plugins</h1>
                    <p class="mt-1 text-sm text-muted-foreground">Kelola dan konfigurasi plugin untuk memperluas kemampuan ASPRI</p>
                </div>
            </div>

            <!-- Filters -->
            <Card class="mb-6">
                <CardContent class="pt-6">
                    <div class="flex flex-col gap-4 lg:flex-row lg:items-center">
                        <!-- Search -->
                        <div class="relative flex-1">
                            <Search class="absolute left-3 top-1/2 h-4 w-4 -translate-y-1/2 text-muted-foreground" />
                            <Input v-model="searchQuery" placeholder="Cari plugin..." class="pl-10" />
                        </div>
                        
                        <!-- Status Filter -->
                        <Select v-model="statusFilter">
                            <SelectTrigger class="w-full lg:w-40">
                                <SelectValue placeholder="Status" />
                            </SelectTrigger>
                            <SelectContent>
                                <SelectItem value="all">Semua</SelectItem>
                                <SelectItem value="active">Aktif</SelectItem>
                                <SelectItem value="inactive">Tidak Aktif</SelectItem>
                            </SelectContent>
                        </Select>
                        
                        <!-- Rating Filter -->
                        <Select v-model="minRating">
                            <SelectTrigger class="w-full lg:w-44">
                                <SelectValue placeholder="Rating" />
                            </SelectTrigger>
                            <SelectContent>
                                <SelectItem :value="0">Semua Rating</SelectItem>
                                <SelectItem :value="1">⭐ 1+</SelectItem>
                                <SelectItem :value="2">⭐ 2+</SelectItem>
                                <SelectItem :value="3">⭐ 3+</SelectItem>
                                <SelectItem :value="4">⭐ 4+</SelectItem>
                                <SelectItem :value="5">⭐ 5</SelectItem>
                            </SelectContent>
                        </Select>
                        
                        <!-- Sort -->
                        <Select v-model="sortBy">
                            <SelectTrigger class="w-full lg:w-48">
                                <SelectValue placeholder="Urutkan" />
                            </SelectTrigger>
                            <SelectContent>
                                <SelectItem value="rating">Rating Tertinggi</SelectItem>
                                <SelectItem value="name">Nama A-Z</SelectItem>
                                <SelectItem value="newest">Terbaru</SelectItem>
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
                <Card v-for="plugin in paginatedPlugins" :key="plugin.id" class="flex flex-col transition-shadow hover:shadow-md">
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

                        <!-- Rating Display -->
                        <div class="mt-3 flex items-center gap-2">
                            <StarRating :rating="plugin.average_rating || 0" :size="16" show-value />
                            <span class="text-xs text-muted-foreground">
                                ({{ plugin.total_ratings || 0 }} {{ plugin.total_ratings === 1 ? 'rating' : 'ratings' }})
                            </span>
                        </div>

                        <div class="mt-3 flex flex-wrap gap-2">
                            <Badge v-if="plugin.is_system" variant="outline">Sistem</Badge>
                            <Badge v-if="plugin.author" variant="outline">{{ plugin.author }}</Badge>
                        </div>
                    </CardContent>
                    <CardFooter class="flex gap-2 border-t pt-4">
                        <Button 
                            :variant="plugin.user_is_active ? 'outline' : 'default'" 
                            size="sm" 
                            class="flex-1" 
                            :disabled="togglingPluginId === plugin.id"
                            @click="togglePlugin(plugin)"
                        >
                            <Spinner v-if="togglingPluginId === plugin.id" class="mr-2" />
                            {{ plugin.user_is_active ? 'Nonaktifkan' : 'Aktifkan' }}
                        </Button>
                        <Button v-if="plugin.user_is_active" variant="outline" size="sm" as-child>
                            <Link :href="show.url(plugin.id)">
                                <Cog class="h-4 w-4" />
                            </Link>
                        </Button>
                        <Button variant="outline" size="sm" @click="openRatingDialog(plugin)" title="Beri Rating">
                            <Star class="h-4 w-4" />
                        </Button>
                    </CardFooter>
                </Card>
            </div>

            <!-- Pagination -->
            <div v-if="totalPages > 1" class="mt-6 flex items-center justify-between">
                <div class="text-sm text-muted-foreground">
                    Showing {{ paginationInfo.start }} to {{ paginationInfo.end }} of {{ paginationInfo.total }} plugins
                </div>
                <div class="flex items-center gap-2">
                    <Button variant="outline" size="sm" :disabled="currentPage === 1" @click="prevPage">Previous</Button>
                    <div class="text-sm text-muted-foreground">Page {{ currentPage }} of {{ totalPages }}</div>
                    <Button variant="outline" size="sm" :disabled="currentPage === totalPages" @click="nextPage">Next</Button>
                </div>
            </div>
        </div>

        <!-- Rating Dialog -->
        <Dialog v-model:open="showRatingDialog">
            <DialogContent class="sm:max-w-md">
                <DialogHeader>
                    <DialogTitle>Beri Rating untuk {{ selectedPlugin?.name }}</DialogTitle>
                    <DialogDescription>
                        Bagikan pengalaman Anda menggunakan plugin ini
                    </DialogDescription>
                </DialogHeader>

                <div class="space-y-4 py-4">
                    <!-- Star Rating -->
                    <div class="flex flex-col items-center gap-2">
                        <Label>Rating</Label>
                        <div class="flex gap-1">
                            <button
                                v-for="star in 5"
                                :key="star"
                                type="button"
                                @click="ratingForm.rating = star"
                                @mouseenter="hoverRating = star"
                                @mouseleave="hoverRating = 0"
                                class="transition-colors"
                            >
                                <Star
                                    :size="32"
                                    :class="[
                                        (hoverRating >= star || ratingForm.rating >= star)
                                            ? 'fill-yellow-400 text-yellow-400'
                                            : 'text-gray-300 dark:text-gray-600'
                                    ]"
                                />
                            </button>
                        </div>
                        <p v-if="ratingForm.errors.rating" class="text-sm text-red-500">{{ ratingForm.errors.rating }}</p>
                    </div>

                    <!-- Review Text -->
                    <div class="space-y-2">
                        <Label for="review">Review (Opsional)</Label>
                        <Textarea
                            id="review"
                            v-model="ratingForm.review"
                            placeholder="Tulis review Anda tentang plugin ini..."
                            rows="4"
                        />
                        <p v-if="ratingForm.errors.review" class="text-sm text-red-500">{{ ratingForm.errors.review }}</p>
                    </div>
                </div>

                <DialogFooter>
                    <Button variant="outline" @click="showRatingDialog = false" :disabled="ratingForm.processing">
                        Batal
                    </Button>
                    <Button @click="submitRating" :disabled="ratingForm.processing || ratingForm.rating === 0">
                        {{ ratingForm.processing ? 'Mengirim...' : 'Kirim Rating' }}
                    </Button>
                </DialogFooter>
            </DialogContent>
        </Dialog>
    </AppLayout>
</template>
