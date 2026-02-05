<script setup lang="ts">
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import { Checkbox } from '@/components/ui/checkbox';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select';
import { Separator } from '@/components/ui/separator';
import { Textarea } from '@/components/ui/textarea';
import StarRating from '@/components/StarRating.vue';
import AppLayout from '@/layouts/AppLayout.vue';
import type { BreadcrumbItem, ConfigField, PluginShowProps } from '@/types';
import { Head, router, useForm } from '@inertiajs/vue3';
import { index as pluginsIndex } from '@/routes/plugins';
import { ArrowLeft, Calendar, CheckCircle, Clock, Play, RotateCcw, Save, Star, Trash2, XCircle } from 'lucide-vue-next';
import { computed, ref } from 'vue';

const props = defineProps<PluginShowProps>();

const breadcrumbs = computed<BreadcrumbItem[]>(() => [
    {
        title: 'Plugins',
        href: pluginsIndex().url,
    },
    {
        title: props.plugin.name,
        href: '#',
    },
]);

// Configuration form
const configForm = useForm<{ config: Record<string, unknown> }>({
    config: { ...props.config },
});

// Schedule form
const scheduleForm = useForm({
    schedule_type: props.schedule?.schedule_type || 'daily',
    schedule_value: props.schedule?.schedule_value || '07:00',
    metadata: props.schedule?.metadata || null,
});

// Rating form
const ratingForm = useForm({
    rating: props.userRating?.rating || 0,
    review: props.userRating?.review || '',
});

const isEditingRating = ref(false);
const showRatingForm = computed(() => !props.userRating || isEditingRating.value);

const isActive = computed(() => props.userPlugin?.is_active ?? false);

const shouldShowField = (field: ConfigField): boolean => {
    if (!field.condition) {
        return true;
    }

    // Simple condition evaluation (e.g., "include_custom === true")
    try {
        const [key, op, val] = field.condition.split(/\s*(===|!==|==|!=)\s*/);
        const configValue = configForm.config[key];
        const compareValue = val === 'true' ? true : val === 'false' ? false : val;

        if (op === '===' || op === '==') {
            return configValue === compareValue;
        }
        if (op === '!==' || op === '!=') {
            return configValue !== compareValue;
        }
    } catch {
        return true;
    }

    return true;
};

const saveConfig = () => {
    configForm.post(route('plugins.config.update', props.plugin.id), {
        preserveScroll: true,
    });
};

const resetConfig = () => {
    if (confirm('Apakah Anda yakin ingin mereset konfigurasi ke default?')) {
        router.delete(route('plugins.config.reset', props.plugin.id), {
            preserveScroll: true,
        });
    }
};

const saveSchedule = () => {
    scheduleForm.post(route('plugins.schedule.update', props.plugin.id), {
        preserveScroll: true,
    });
};

const testPlugin = () => {
    router.post(
        route('plugins.test', props.plugin.id),
        {},
        {
            preserveScroll: true,
        },
    );
};

const togglePlugin = () => {
    if (isActive.value) {
        router.post(route('plugins.deactivate', props.plugin.id), {}, { preserveScroll: true });
    } else {
        router.post(route('plugins.activate', props.plugin.id), {}, { preserveScroll: true });
    }
};

const formatDate = (date: string | null) => {
    if (!date) {
        return '-';
    }
    return new Date(date).toLocaleString('id-ID');
};

const submitRating = () => {
    if (ratingForm.rating === 0) {
        alert('Silakan pilih rating bintang terlebih dahulu');
        return;
    }

    if (props.userRating) {
        // Update existing rating
        ratingForm.put(route('plugins.ratings.update', [props.plugin.id, props.userRating.id]), {
            preserveScroll: true,
            onSuccess: () => {
                isEditingRating.value = false;
            },
        });
    } else {
        // Create new rating
        ratingForm.post(route('plugins.ratings.store', props.plugin.id), {
            preserveScroll: true,
        });
    }
};

const deleteRating = () => {
    if (!props.userRating) return;
    
    if (confirm('Apakah Anda yakin ingin menghapus rating Anda?')) {
        router.delete(route('plugins.ratings.destroy', [props.plugin.id, props.userRating.id]), {
            preserveScroll: true,
        });
    }
};

const cancelEdit = () => {
    ratingForm.rating = props.userRating?.rating || 0;
    ratingForm.review = props.userRating?.review || '';
    isEditingRating.value = false;
};

const getLogLevelColor = (level: string) => {
    switch (level) {
        case 'error':
            return 'destructive';
        case 'warning':
            return 'secondary';
        case 'info':
            return 'default';
        default:
            return 'outline';
    }
};

// Safe access to ratings with default empty values
const ratings = computed(() => props.ratings ?? {
    data: [],
    current_page: 1,
    last_page: 1,
    per_page: 10,
    total: 0,
    from: 0,
    to: 0,
    prev_page_url: null,
    next_page_url: null,
});

// Expose props for template use
const { plugin, userPlugin, config, formFields, supportsScheduling, schedule, executionHistory, userRating } = props;
</script>

<template>
    <Head :title="`Plugin: ${plugin.name}`" />

    <AppLayout :breadcrumbs="breadcrumbs">
        <div class="flex h-full flex-1 flex-col gap-4 p-4">
            <!-- Header -->
            <div class="flex items-center gap-4">
                <Button variant="ghost" size="icon" as-child>
                    <a :href="route('plugins.index')">
                        <ArrowLeft class="h-5 w-5" />
                    </a>
                </Button>
                <div class="flex-1">
                    <div class="flex items-center gap-3">
                        <h1 class="text-2xl font-bold">
                            {{ plugin.name }}
                        </h1>
                        <Badge :variant="isActive ? 'default' : 'secondary'">
                            <component :is="isActive ? CheckCircle : XCircle" class="mr-1 h-3 w-3" />
                            {{ isActive ? 'Aktif' : 'Tidak Aktif' }}
                        </Badge>
                    </div>
                    <p class="text-sm text-muted-foreground">v{{ plugin.version }} • {{ plugin.author }}</p>
                </div>
                <Button :variant="isActive ? 'outline' : 'default'" @click="togglePlugin">
                    {{ isActive ? 'Nonaktifkan' : 'Aktifkan' }}
                </Button>
            </div>

            <!-- Main Content -->
            <div class="mx-auto max-w-4xl space-y-6">
                <!-- Description -->
                <Card>
                    <CardHeader>
                        <CardTitle>Tentang Plugin</CardTitle>
                    </CardHeader>
                    <CardContent>
                        <p class="text-muted-foreground">{{ plugin.description || 'Tidak ada deskripsi.' }}</p>
                        
                        <!-- Rating Summary -->
                        <div class="mt-4 flex items-center gap-4 border-t pt-4">
                            <div>
                                <StarRating :rating="plugin.average_rating" :size="20" show-value />
                            </div>
                            <span class="text-sm text-muted-foreground">
                                {{ plugin.total_ratings }} {{ plugin.total_ratings === 1 ? 'rating' : 'ratings' }}
                            </span>
                        </div>
                    </CardContent>
                </Card>

                <!-- User Rating Section -->
                <Card>
                    <CardHeader>
                        <CardTitle>Rating Anda</CardTitle>
                        <CardDescription>Bagikan pengalaman Anda menggunakan plugin ini</CardDescription>
                    </CardHeader>
                    <CardContent>
                        <!-- Existing Rating Display -->
                        <div v-if="userRating && !isEditingRating" class="space-y-4">
                            <div class="flex items-start justify-between">
                                <div class="space-y-2">
                                    <StarRating :rating="userRating.rating" :size="24" />
                                    <p v-if="userRating.review" class="text-sm text-muted-foreground">
                                        {{ userRating.review }}
                                    </p>
                                </div>
                                <div class="flex gap-2">
                                    <Button variant="outline" size="sm" @click="isEditingRating = true">
                                        Edit
                                    </Button>
                                    <Button variant="outline" size="sm" @click="deleteRating">
                                        <Trash2 class="h-4 w-4" />
                                    </Button>
                                </div>
                            </div>
                        </div>

                        <!-- Rating Form -->
                        <form v-else class="space-y-4" @submit.prevent="submitRating">
                            <div class="space-y-2">
                                <Label>Rating <span class="text-destructive">*</span></Label>
                                <StarRating
                                    v-model:rating="ratingForm.rating"
                                    :size="32"
                                    :interactive="true"
                                />
                            </div>

                            <div class="space-y-2">
                                <Label for="review">Review (Opsional)</Label>
                                <Textarea
                                    id="review"
                                    v-model="ratingForm.review"
                                    placeholder="Tulis review Anda tentang plugin ini..."
                                    :maxlength="500"
                                    rows="3"
                                />
                                <p class="text-xs text-muted-foreground">
                                    {{ ratingForm.review?.length || 0 }}/500 karakter
                                </p>
                            </div>

                            <div class="flex justify-end gap-2">
                                <Button
                                    v-if="isEditingRating"
                                    type="button"
                                    variant="outline"
                                    @click="cancelEdit"
                                >
                                    Batal
                                </Button>
                                <Button type="submit" :disabled="ratingForm.processing || ratingForm.rating === 0">
                                    <Star class="mr-2 h-4 w-4" />
                                    {{ userRating ? 'Update Rating' : 'Kirim Rating' }}
                                </Button>
                            </div>
                        </form>
                    </CardContent>
                </Card>

                <!-- All Ratings -->
                <Card v-if="ratings.data.length > 0">
                    <CardHeader>
                        <CardTitle>Semua Rating ({{ ratings.total }})</CardTitle>
                    </CardHeader>
                    <CardContent>
                        <div class="space-y-4">
                            <div
                                v-for="rating in ratings.data"
                                :key="rating.id"
                                class="border-b pb-4 last:border-b-0"
                            >
                                <div class="flex items-start justify-between">
                                    <div class="space-y-1">
                                        <div class="flex items-center gap-2">
                                            <span class="font-medium">{{ rating.user.name }}</span>
                                            <StarRating :rating="rating.rating" :size="16" />
                                        </div>
                                        <p v-if="rating.review" class="text-sm text-muted-foreground">
                                            {{ rating.review }}
                                        </p>
                                        <p class="text-xs text-muted-foreground">
                                            {{ formatDate(rating.created_at) }}
                                        </p>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- Pagination -->
                        <div v-if="ratings.last_page > 1" class="mt-4 flex items-center justify-between border-t pt-4">
                            <div class="text-sm text-muted-foreground">
                                Showing {{ ratings.from }} to {{ ratings.to }} of {{ ratings.total }} ratings
                            </div>
                            <div class="flex gap-2">
                                <Button
                                    variant="outline"
                                    size="sm"
                                    :disabled="!ratings.prev_page_url"
                                    @click="router.get(ratings.prev_page_url)"
                                >
                                    Previous
                                </Button>
                                <Button
                                    variant="outline"
                                    size="sm"
                                    :disabled="!ratings.next_page_url"
                                    @click="router.get(ratings.next_page_url)"
                                >
                                    Next
                                </Button>
                            </div>
                        </div>
                    </CardContent>
                </Card>

                <!-- Configuration -->
                <Card v-if="isActive && formFields.length > 0">
                    <CardHeader>
                        <div class="flex items-center justify-between">
                            <div>
                                <CardTitle>Konfigurasi</CardTitle>
                                <CardDescription>Atur preferensi plugin sesuai kebutuhan Anda</CardDescription>
                            </div>
                            <div class="flex gap-2">
                                <Button variant="outline" size="sm" @click="resetConfig">
                                    <RotateCcw class="mr-2 h-4 w-4" />
                                    Reset
                                </Button>
                            </div>
                        </div>
                    </CardHeader>
                    <CardContent>
                        <form class="space-y-6" @submit.prevent="saveConfig">
                            <template v-for="field in formFields" :key="field.key">
                                <div v-if="shouldShowField(field)" class="space-y-2">
                                    <Label :for="field.key">
                                        {{ field.label }}
                                        <span v-if="field.required" class="text-destructive">*</span>
                                    </Label>

                                    <!-- Text input -->
                                    <Input
                                        v-if="field.type === 'text' || field.type === 'email'"
                                        :id="field.key"
                                        v-model="configForm.config[field.key]"
                                        :type="field.type"
                                        :required="field.required"
                                    />

                                    <!-- Number input -->
                                    <Input
                                        v-else-if="field.type === 'number' || field.type === 'integer'"
                                        :id="field.key"
                                        v-model.number="configForm.config[field.key]"
                                        type="number"
                                        :min="field.min"
                                        :max="field.max"
                                        :required="field.required"
                                    />

                                    <!-- Time input -->
                                    <Input
                                        v-else-if="field.type === 'time'"
                                        :id="field.key"
                                        v-model="configForm.config[field.key]"
                                        type="time"
                                        :required="field.required"
                                    />

                                    <!-- Textarea -->
                                    <Textarea
                                        v-else-if="field.type === 'textarea'"
                                        :id="field.key"
                                        v-model="configForm.config[field.key]"
                                        :required="field.required"
                                        rows="4"
                                    />

                                    <!-- Boolean (Checkbox) -->
                                    <div v-else-if="field.type === 'boolean'" class="flex items-center space-x-2">
                                        <Checkbox :id="field.key" v-model:checked="configForm.config[field.key]" />
                                        <Label :for="field.key" class="font-normal">{{ field.description || field.label }}</Label>
                                    </div>

                                    <!-- Select -->
                                    <Select v-else-if="field.type === 'select'" v-model="configForm.config[field.key]">
                                        <SelectTrigger>
                                            <SelectValue :placeholder="`Pilih ${field.label}`" />
                                        </SelectTrigger>
                                        <SelectContent>
                                            <SelectItem v-for="option in field.options" :key="option" :value="option">
                                                {{ option }}
                                            </SelectItem>
                                        </SelectContent>
                                    </Select>

                                    <!-- Description -->
                                    <p v-if="field.description && field.type !== 'boolean'" class="text-sm text-muted-foreground">
                                        {{ field.description }}
                                    </p>

                                    <!-- Error -->
                                    <p v-if="configForm.errors[`config.${field.key}`]" class="text-sm text-destructive">
                                        {{ configForm.errors[`config.${field.key}`] }}
                                    </p>
                                </div>
                            </template>

                            <div class="flex justify-end gap-2">
                                <Button type="submit" :disabled="configForm.processing">
                                    <Save class="mr-2 h-4 w-4" />
                                    Simpan Konfigurasi
                                </Button>
                            </div>
                        </form>
                    </CardContent>
                </Card>

                <!-- Scheduling -->
                <Card v-if="isActive && supportsScheduling">
                    <CardHeader>
                        <div class="flex items-center justify-between">
                            <div>
                                <CardTitle>Jadwal Eksekusi</CardTitle>
                                <CardDescription>Atur kapan plugin akan dijalankan secara otomatis</CardDescription>
                            </div>
                            <Button variant="outline" size="sm" @click="testPlugin">
                                <Play class="mr-2 h-4 w-4" />
                                Test Sekarang
                            </Button>
                        </div>
                    </CardHeader>
                    <CardContent>
                        <form class="space-y-4" @submit.prevent="saveSchedule">
                            <div class="grid gap-4 sm:grid-cols-2">
                                <div class="space-y-2">
                                    <Label for="schedule_type">Tipe Jadwal</Label>
                                    <Select v-model="scheduleForm.schedule_type">
                                        <SelectTrigger>
                                            <SelectValue placeholder="Pilih tipe jadwal" />
                                        </SelectTrigger>
                                        <SelectContent>
                                            <SelectItem value="daily">Harian</SelectItem>
                                            <SelectItem value="interval">Interval (menit)</SelectItem>
                                            <SelectItem value="weekly">Mingguan</SelectItem>
                                            <SelectItem value="cron">Cron Expression</SelectItem>
                                        </SelectContent>
                                    </Select>
                                </div>

                                <div class="space-y-2">
                                    <Label for="schedule_value">
                                        {{ scheduleForm.schedule_type === 'daily' ? 'Waktu (HH:MM)' : scheduleForm.schedule_type === 'interval' ? 'Interval (menit)' : scheduleForm.schedule_type === 'weekly' ? 'Hari:Jam:Menit (MON:09:00)' : 'Cron Expression' }}
                                    </Label>
                                    <Input
                                        id="schedule_value"
                                        v-model="scheduleForm.schedule_value"
                                        :type="scheduleForm.schedule_type === 'daily' ? 'time' : 'text'"
                                        :placeholder="
                                            scheduleForm.schedule_type === 'daily'
                                                ? '07:00'
                                                : scheduleForm.schedule_type === 'interval'
                                                  ? '60'
                                                  : scheduleForm.schedule_type === 'weekly'
                                                    ? 'MON:09:00'
                                                    : '0 7 * * *'
                                        "
                                    />
                                </div>
                            </div>

                            <!-- Current Schedule Info -->
                            <div v-if="schedule" class="rounded-lg bg-muted p-4">
                                <div class="flex items-center gap-4 text-sm">
                                    <div class="flex items-center gap-2">
                                        <Clock class="h-4 w-4 text-muted-foreground" />
                                        <span class="text-muted-foreground">Terakhir dijalankan:</span>
                                        <span>{{ formatDate(schedule.last_run_at) }}</span>
                                    </div>
                                    <Separator orientation="vertical" class="h-4" />
                                    <div class="flex items-center gap-2">
                                        <Calendar class="h-4 w-4 text-muted-foreground" />
                                        <span class="text-muted-foreground">Berikutnya:</span>
                                        <span>{{ formatDate(schedule.next_run_at) }}</span>
                                    </div>
                                </div>
                            </div>

                            <div class="flex justify-end">
                                <Button type="submit" :disabled="scheduleForm.processing">
                                    <Save class="mr-2 h-4 w-4" />
                                    Simpan Jadwal
                                </Button>
                            </div>
                        </form>
                    </CardContent>
                </Card>

                <!-- Execution History -->
                <Card v-if="isActive && executionHistory.length > 0">
                    <CardHeader>
                        <CardTitle>Riwayat Eksekusi</CardTitle>
                        <CardDescription>Log aktivitas plugin terbaru</CardDescription>
                    </CardHeader>
                    <CardContent>
                        <div class="space-y-3">
                            <div
                                v-for="log in executionHistory"
                                :key="log.id"
                                class="flex items-start gap-3 rounded-lg border p-3"
                            >
                                <Badge :variant="getLogLevelColor(log.level)" class="mt-0.5">
                                    {{ log.level }}
                                </Badge>
                                <div class="flex-1">
                                    <p class="text-sm">{{ log.message }}</p>
                                    <p class="text-xs text-muted-foreground">
                                        {{ formatDate(log.created_at) }}
                                    </p>
                                </div>
                            </div>
                        </div>
                    </CardContent>
                </Card>

                <!-- Not Active Message -->
                <Card v-if="!isActive">
                    <CardContent class="py-12">
                        <div class="flex flex-col items-center justify-center text-center">
                            <XCircle class="h-12 w-12 text-muted-foreground" />
                            <h3 class="mt-4 text-lg font-medium">Plugin Tidak Aktif</h3>
                            <p class="mt-2 text-muted-foreground">Aktifkan plugin untuk mengakses konfigurasi dan jadwal eksekusi.</p>
                            <Button class="mt-4" @click="togglePlugin">Aktifkan Sekarang</Button>
                        </div>
                    </CardContent>
                </Card>
            </div>
        </div>
    </AppLayout>
</template>