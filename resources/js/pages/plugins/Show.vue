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
import { index as pluginsIndex, activate, deactivate, test } from '@/routes/plugins';
import { update as configUpdate, reset as configReset } from '@/routes/plugins/config';
import { update as scheduleUpdate } from '@/routes/plugins/schedule';
import { store as ratingsStore, update as ratingsUpdate, destroy as ratingsDestroy } from '@/routes/plugins/ratings';
import { ArrowLeft, Calendar, CheckCircle, Clock, Play, RotateCcw, Save, Settings, Star, Trash2, XCircle, FileText, History, MapPin } from 'lucide-vue-next';
import { computed, ref, watch } from 'vue';
import Swal from 'sweetalert2';

// Country list (ISO 3166-1 alpha-2: name)
const COUNTRIES: Record<string, string> = {
    AF: 'Afghanistan', AL: 'Albania', DZ: 'Algeria', AD: 'Andorra', AO: 'Angola',
    AR: 'Argentina', AU: 'Australia', AT: 'Austria', AZ: 'Azerbaijan', BH: 'Bahrain',
    BD: 'Bangladesh', BE: 'Belgium', BJ: 'Benin', BT: 'Bhutan', BO: 'Bolivia',
    BA: 'Bosnia and Herzegovina', BW: 'Botswana', BR: 'Brazil', BN: 'Brunei', BG: 'Bulgaria',
    BF: 'Burkina Faso', BI: 'Burundi', CV: 'Cabo Verde', KH: 'Cambodia', CM: 'Cameroon',
    CA: 'Canada', CF: 'Central African Republic', TD: 'Chad', CL: 'Chile', CN: 'China',
    CO: 'Colombia', KM: 'Comoros', CD: 'Congo (DRC)', CG: 'Congo', CR: 'Costa Rica',
    CI: "Côte d'Ivoire", HR: 'Croatia', CY: 'Cyprus', CZ: 'Czech Republic',
    DK: 'Denmark', DJ: 'Djibouti', DO: 'Dominican Republic', EC: 'Ecuador', EG: 'Egypt',
    SV: 'El Salvador', GQ: 'Equatorial Guinea', ER: 'Eritrea', EE: 'Estonia', SZ: 'Eswatini',
    ET: 'Ethiopia', FJ: 'Fiji', FI: 'Finland', FR: 'France', GA: 'Gabon',
    GM: 'Gambia', GE: 'Georgia', DE: 'Germany', GH: 'Ghana', GR: 'Greece',
    GT: 'Guatemala', GN: 'Guinea', GW: 'Guinea-Bissau', GY: 'Guyana', HT: 'Haiti',
    HN: 'Honduras', HU: 'Hungary', IS: 'Iceland', IN: 'India', ID: 'Indonesia',
    IR: 'Iran', IQ: 'Iraq', IE: 'Ireland', IL: 'Israel', IT: 'Italy',
    JM: 'Jamaica', JP: 'Japan', JO: 'Jordan', KZ: 'Kazakhstan', KE: 'Kenya',
    KI: 'Kiribati', KW: 'Kuwait', KG: 'Kyrgyzstan', LA: 'Laos', LV: 'Latvia',
    LB: 'Lebanon', LS: 'Lesotho', LR: 'Liberia', LY: 'Libya', LI: 'Liechtenstein',
    LT: 'Lithuania', LU: 'Luxembourg', MG: 'Madagascar', MW: 'Malawi', MY: 'Malaysia',
    MV: 'Maldives', ML: 'Mali', MT: 'Malta', MR: 'Mauritania', MU: 'Mauritius',
    MX: 'Mexico', FM: 'Micronesia', MD: 'Moldova', MC: 'Monaco', MN: 'Mongolia',
    ME: 'Montenegro', MA: 'Morocco', MZ: 'Mozambique', MM: 'Myanmar', NA: 'Namibia',
    NP: 'Nepal', NL: 'Netherlands', NZ: 'New Zealand', NI: 'Nicaragua', NE: 'Niger',
    NG: 'Nigeria', MK: 'North Macedonia', NO: 'Norway', OM: 'Oman', PK: 'Pakistan',
    PW: 'Palau', PA: 'Panama', PG: 'Papua New Guinea', PY: 'Paraguay', PE: 'Peru',
    PH: 'Philippines', PL: 'Poland', PT: 'Portugal', QA: 'Qatar', RO: 'Romania',
    RU: 'Russia', RW: 'Rwanda', SA: 'Saudi Arabia', SN: 'Senegal', RS: 'Serbia',
    SL: 'Sierra Leone', SG: 'Singapore', SK: 'Slovakia', SI: 'Slovenia', SO: 'Somalia',
    ZA: 'South Africa', SS: 'South Sudan', ES: 'Spain', LK: 'Sri Lanka', SD: 'Sudan',
    SR: 'Suriname', SE: 'Sweden', CH: 'Switzerland', SY: 'Syria', TW: 'Taiwan',
    TJ: 'Tajikistan', TZ: 'Tanzania', TH: 'Thailand', TL: 'Timor-Leste', TG: 'Togo',
    TT: 'Trinidad and Tobago', TN: 'Tunisia', TR: 'Turkey', TM: 'Turkmenistan',
    UG: 'Uganda', UA: 'Ukraine', AE: 'United Arab Emirates', GB: 'United Kingdom',
    US: 'United States', UY: 'Uruguay', UZ: 'Uzbekistan', VE: 'Venezuela', VN: 'Vietnam',
    YE: 'Yemen', ZM: 'Zambia', ZW: 'Zimbabwe',
};

// City search state (keyed by field key to support multiple city_search fields)
interface CityResult {
    name: string;
    admin1: string;
    country: string;
    latitude: number;
    longitude: number;
}

const citySearchQuery = ref<Record<string, string>>({});
const citySearchResults = ref<Record<string, CityResult[]>>({});
const citySearchLoading = ref<Record<string, boolean>>({});
const citySearchOpen = ref<Record<string, boolean>>({});
let citySearchTimers: Record<string, ReturnType<typeof setTimeout>> = {};

const searchCities = (fieldKey: string, dependsOnKey: string | null | undefined) => {
    const query = citySearchQuery.value[fieldKey];
    if (!query || query.length < 2) {
        citySearchResults.value[fieldKey] = [];
        citySearchOpen.value[fieldKey] = false;
        return;
    }

    clearTimeout(citySearchTimers[fieldKey]);
    citySearchTimers[fieldKey] = setTimeout(async () => {
        citySearchLoading.value[fieldKey] = true;
        try {
            const countryCode = dependsOnKey ? (configForm.config[dependsOnKey] as string) : '';
            let url = `https://geocoding-api.open-meteo.com/v1/search?name=${encodeURIComponent(query)}&count=8&language=id&format=json`;
            if (countryCode) {
                url += `&countryCode=${countryCode}`;
            }
            const resp = await fetch(url);
            const data = await resp.json();
            citySearchResults.value[fieldKey] = (data.results || []) as CityResult[];
            citySearchOpen.value[fieldKey] = citySearchResults.value[fieldKey].length > 0;
        } catch {
            citySearchResults.value[fieldKey] = [];
        } finally {
            citySearchLoading.value[fieldKey] = false;
        }
    }, 350);
};

const selectCity = (field: ConfigField, city: CityResult) => {
    // Set the city name value
    configForm.config[field.key] = city.name;
    citySearchQuery.value[field.key] = city.name;
    citySearchOpen.value[field.key] = false;

    // Auto-fill related fields based on 'fills'
    if (field.fills) {
        for (const fillKey of field.fills) {
            if (fillKey === 'latitude') {
                configForm.config[fillKey] = city.latitude;
            } else if (fillKey === 'longitude') {
                configForm.config[fillKey] = city.longitude;
            } else if (fillKey === 'location') {
                configForm.config[fillKey] = `${city.name}, ${city.country}`;
            }
        }
    }
};

const closeCitySearchDelayed = (fieldKey: string) => {
    window.setTimeout(() => {
        citySearchOpen.value[fieldKey] = false;
    }, 200);
};

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

// Active tab
const activeTab = ref<'config' | 'history' | 'about'>('config');

// Configuration form - ensure boolean values are properly initialized
const configForm = useForm<{ config: Record<string, unknown> }>({
    config: Object.keys(props.config).reduce((acc, key) => {
        const value = props.config[key];
        // Ensure boolean values are proper booleans
        if (typeof value === 'boolean') {
            acc[key] = value === true;
        } else {
            acc[key] = value;
        }
        return acc;
    }, {} as Record<string, unknown>),
});

// Watch for config changes from server (after save/refresh)  
watch(() => props.config, (newConfig) => {
    // Clear existing config first
    for (const key in configForm.config) {
        delete configForm.config[key];
    }
    // Then set all new values with proper type conversion
    Object.keys(newConfig).forEach(key => {
        const value = newConfig[key];
        // Ensure boolean values are proper booleans
        if (typeof value === 'boolean') {
            configForm.config[key] = value === true;
        } else {
            configForm.config[key] = value;
        }
    });
}, { deep: true });

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
    configForm.post(configUpdate.url(props.plugin.id), {
        preserveScroll: true,
        onSuccess: () => {
            Swal.fire({
                icon: 'success',
                title: 'Konfigurasi berhasil disimpan',
                text: 'Perubahan konfigurasi telah tersimpan.',
                timer: 2000,
                showConfirmButton: false,
            });
        },
        onError: () => {
            Swal.fire({
                icon: 'error',
                title: 'Gagal menyimpan konfigurasi',
                text: 'Terjadi kesalahan saat menyimpan konfigurasi.',
            });
        },
    });
};

const resetConfig = () => {
    Swal.fire({
        title: 'Reset Konfigurasi?',
        text: 'Apakah Anda yakin ingin mereset konfigurasi ke default?',
        icon: 'warning',
        showCancelButton: true,
        confirmButtonColor: '#3085d6',
        cancelButtonColor: '#d33',
        confirmButtonText: 'Ya, Reset!',
        cancelButtonText: 'Batal',
    }).then((result) => {
        if (result.isConfirmed) {
            router.delete(configReset.url(props.plugin.id), {
                preserveScroll: true,
                onSuccess: () => {
                    Swal.fire({
                        icon: 'success',
                        title: 'Konfigurasi berhasil direset',
                        text: 'Konfigurasi telah dikembalikan ke pengaturan default.',
                        timer: 2000,
                        showConfirmButton: false,
                    });
                },
            });
        }
    });
};

const saveSchedule = () => {
    scheduleForm.post(scheduleUpdate.url(props.plugin.id), {
        preserveScroll: true,
        onSuccess: () => {
            Swal.fire({
                icon: 'success',
                title: 'Jadwal berhasil disimpan',
                text: 'Jadwal eksekusi plugin telah diperbarui.',
                timer: 2000,
                showConfirmButton: false,
            });
        },
        onError: () => {
            Swal.fire({
                icon: 'error',
                title: 'Gagal menyimpan jadwal',
                text: 'Terjadi kesalahan saat menyimpan jadwal.',
            });
        },
    });
};

const testPlugin = () => {
    router.post(
        test.url(props.plugin.id),
        {},
        {
            preserveScroll: true,
            onSuccess: () => {
                Swal.fire({
                    icon: 'info',
                    title: 'Plugin sedang ditest',
                    text: 'Plugin akan dijalankan segera.',
                    timer: 2000,
                    showConfirmButton: false,
                });
            },
        },
    );
};

const togglePlugin = () => {
    if (isActive.value) {
        router.post(deactivate.url(props.plugin.id), {}, { 
            preserveScroll: true,
            onSuccess: () => {
                Swal.fire({
                    icon: 'success',
                    title: 'Plugin dinonaktifkan',
                    text: 'Plugin berhasil dinonaktifkan.',
                    timer: 2000,
                    showConfirmButton: false,
                });
            },
        });
    } else {
        router.post(activate.url(props.plugin.id), {}, { 
            preserveScroll: true,
            onSuccess: () => {
                Swal.fire({
                    icon: 'success',
                    title: 'Plugin diaktifkan',
                    text: 'Plugin berhasil diaktifkan.',
                    timer: 2000,
                    showConfirmButton: false,
                });
            },
        });
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
        Swal.fire({
            icon: 'warning',
            title: 'Rating tidak valid',
            text: 'Silakan pilih rating bintang terlebih dahulu.',
        });
        return;
    }

    if (props.userRating) {
        // Update existing rating
        ratingForm.put(ratingsUpdate.url([props.plugin.id, props.userRating.id]), {
            preserveScroll: true,
            onSuccess: () => {
                isEditingRating.value = false;
                Swal.fire({
                    icon: 'success',
                    title: 'Rating berhasil diperbarui',
                    text: 'Terima kasih atas feedback Anda.',
                    timer: 2000,
                    showConfirmButton: false,
                });
            },
        });
    } else {
        // Create new rating
        ratingForm.post(ratingsStore.url(props.plugin.id), {
            preserveScroll: true,
            onSuccess: () => {
                Swal.fire({
                    icon: 'success',
                    title: 'Rating berhasil ditambahkan',
                    text: 'Terima kasih atas feedback Anda.',
                    timer: 2000,
                    showConfirmButton: false,
                });
            },
        });
    }
};

const deleteRating = () => {
    if (!props.userRating) return;
    
    Swal.fire({
        title: 'Hapus Rating?',
        text: 'Apakah Anda yakin ingin menghapus rating Anda?',
        icon: 'warning',
        showCancelButton: true,
        confirmButtonColor: '#3085d6',
        cancelButtonColor: '#d33',
        confirmButtonText: 'Ya, Hapus!',
        cancelButtonText: 'Batal',
    }).then((result) => {
        if (result.isConfirmed) {
            router.delete(ratingsDestroy.url([props.plugin.id, props.userRating.id]), {
                preserveScroll: true,
                onSuccess: () => {
                    Swal.fire({
                        icon: 'success',
                        title: 'Rating berhasil dihapus',
                        timer: 2000,
                        showConfirmButton: false,
                    });
                },
            });
        }
    });
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
        <div class="flex h-full flex-1 flex-col p-4 md:p-6">
            <!-- Header -->
            <div class="mb-6 flex items-center gap-4">
                <Button variant="ghost" size="icon" as-child>
                    <a :href="pluginsIndex.url()">
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

            <!-- Vertical Tabs Layout -->
            <div class="flex flex-col gap-6 lg:flex-row lg:gap-12">
                <!-- Sidebar Navigation -->
                <aside class="w-full lg:w-48">
                    <nav class="flex flex-col space-y-1" aria-label="Plugin sections">
                        <Button
                            variant="ghost"
                            :class="[
                                'w-full justify-start gap-2',
                                { 'bg-muted': activeTab === 'config' },
                            ]"
                            @click="activeTab = 'config'"
                        >
                            <Settings class="h-4 w-4" />
                            Konfigurasi
                        </Button>
                        <Button
                            variant="ghost"
                            :class="[
                                'w-full justify-start gap-2',
                                { 'bg-muted': activeTab === 'history' },
                            ]"
                            @click="activeTab = 'history'"
                        >
                            <History class="h-4 w-4" />
                            Riwayat Eksekusi
                        </Button>
                        <Button
                            variant="ghost"
                            :class="[
                                'w-full justify-start gap-2',
                                { 'bg-muted': activeTab === 'about' },
                            ]"
                            @click="activeTab = 'about'"
                        >
                            <FileText class="h-4 w-4" />
                            Tentang
                        </Button>
                    </nav>
                </aside>

                <Separator class="my-6 lg:hidden" />

                <!-- Main Content Area -->
                <div class="flex-1">
                    <!-- ============================================ -->
                    <!-- TAB 1: KONFIGURASI -->
                    <!-- ============================================ -->
                    <div v-if="activeTab === 'config'" class="space-y-6">
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

                        <!-- Configuration Form -->
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
                                        <div v-if="shouldShowField(field) && field.type !== 'hidden'" class="space-y-2">
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
                                                :step="field.step ?? (field.type === 'integer' ? 1 : 'any')"
                                                :readonly="field.readonly"
                                                :class="field.readonly ? 'bg-muted text-muted-foreground cursor-not-allowed' : ''"
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
                                                <input 
                                                    type="checkbox"
                                                    :id="field.key" 
                                                    :checked="!!configForm.config[field.key]"
                                                    @change="(e: Event) => {
                                                        const target = e.target as HTMLInputElement;
                                                        configForm.config[field.key] = target.checked;
                                                    }"
                                                    class="peer border-input data-[state=checked]:bg-primary data-[state=checked]:text-primary-foreground data-[state=checked]:border-primary focus-visible:border-ring focus-visible:ring-ring/50 aria-invalid:ring-destructive/20 dark:aria-invalid:ring-destructive/40 aria-invalid:border-destructive size-4 shrink-0 rounded-[4px] border shadow-xs transition-shadow outline-none focus-visible:ring-[3px] disabled:cursor-not-allowed disabled:opacity-50 cursor-pointer"
                                                />
                                                <Label :for="field.key" class="font-normal cursor-pointer">
                                                    {{ field.description || field.label }}
                                                </Label>
                                            </div>

                                            <!-- Country select -->
                                            <Select
                                                v-else-if="field.type === 'country_select'"
                                                v-model="configForm.config[field.key]"
                                            >
                                                <SelectTrigger>
                                                    <SelectValue :placeholder="`Pilih ${field.label}`" />
                                                </SelectTrigger>
                                                <SelectContent>
                                                    <SelectItem
                                                        v-for="(countryName, code) in COUNTRIES"
                                                        :key="code"
                                                        :value="code"
                                                    >
                                                        {{ countryName }}
                                                    </SelectItem>
                                                </SelectContent>
                                            </Select>

                                            <!-- City search with autocomplete -->
                                            <div
                                                v-else-if="field.type === 'city_search'"
                                                class="relative"
                                            >
                                                <div class="relative">
                                                    <MapPin class="absolute left-3 top-1/2 -translate-y-1/2 h-4 w-4 text-muted-foreground pointer-events-none z-10" />
                                                    <input
                                                        :id="field.key"
                                                        :value="citySearchQuery[field.key] !== undefined ? citySearchQuery[field.key] : (configForm.config[field.key] as string || '')"
                                                        class="file:text-foreground placeholder:text-muted-foreground selection:bg-primary selection:text-primary-foreground dark:bg-input/30 border-input h-9 w-full min-w-0 rounded-md border bg-transparent pl-9 pr-3 py-1 text-base shadow-xs transition-[color,box-shadow] outline-none md:text-sm focus-visible:border-ring focus-visible:ring-ring/50 focus-visible:ring-[3px]"
                                                        :placeholder="field.placeholder || 'Cari kota...'"
                                                        :required="field.required"
                                                        autocomplete="off"
                                                        @input="(e: Event) => { citySearchQuery[field.key] = (e.target as HTMLInputElement).value; searchCities(field.key, field.depends_on); }"
                                                        @blur="() => closeCitySearchDelayed(field.key)"
                                                    />
                                                    <span v-if="citySearchLoading[field.key]" class="absolute right-3 top-1/2 -translate-y-1/2 h-4 w-4 animate-spin rounded-full border-2 border-muted-foreground border-t-transparent" />
                                                </div>

                                                <!-- Dropdown results -->
                                                <div
                                                    v-if="citySearchOpen[field.key] && citySearchResults[field.key]?.length"
                                                    class="absolute z-50 mt-1 w-full rounded-md border bg-popover text-popover-foreground shadow-md"
                                                >
                                                    <ul class="max-h-60 overflow-auto py-1">
                                                        <li
                                                            v-for="(city, idx) in citySearchResults[field.key]"
                                                            :key="idx"
                                                            class="flex cursor-pointer items-center gap-2 px-3 py-2 text-sm hover:bg-accent hover:text-accent-foreground"
                                                            @mousedown.prevent="selectCity(field, city)"
                                                        >
                                                            <MapPin class="h-3.5 w-3.5 shrink-0 text-muted-foreground" />
                                                            <span>
                                                                <span class="font-medium">{{ city.name }}</span>
                                                                <span class="text-muted-foreground">, {{ city.admin1 ? city.admin1 + ', ' : '' }}{{ city.country }}</span>
                                                            </span>
                                                        </li>
                                                    </ul>
                                                </div>
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
                    </div>

                    <!-- ============================================ -->
                    <!-- TAB 2: RIWAYAT EKSEKUSI -->
                    <!-- ============================================ -->
                    <div v-else-if="activeTab === 'history'" class="space-y-6">
                        <!-- Not Active Message -->
                        <Card v-if="!isActive">
                            <CardContent class="py-12">
                                <div class="flex flex-col items-center justify-center text-center">
                                    <XCircle class="h-12 w-12 text-muted-foreground" />
                                    <h3 class="mt-4 text-lg font-medium">Plugin Tidak Aktif</h3>
                                    <p class="mt-2 text-muted-foreground">Aktifkan plugin untuk melihat riwayat eksekusi.</p>
                                    <Button class="mt-4" @click="togglePlugin">Aktifkan Sekarang</Button>
                                </div>
                            </CardContent>
                        </Card>

                        <!-- Execution History -->
                        <Card v-else-if="executionHistory.length > 0">
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

                        <!-- Empty State -->
                        <Card v-else>
                            <CardContent class="py-12">
                                <div class="flex flex-col items-center justify-center text-center">
                                    <History class="h-12 w-12 text-muted-foreground" />
                                    <h3 class="mt-4 text-lg font-medium">Belum Ada Riwayat</h3>
                                    <p class="mt-2 text-muted-foreground">Plugin belum pernah dijalankan. Jalankan test untuk melihat riwayat eksekusi.</p>
                                    <Button class="mt-4" @click="testPlugin">
                                        <Play class="mr-2 h-4 w-4" />
                                        Test Plugin
                                    </Button>
                                </div>
                            </CardContent>
                        </Card>
                    </div>

                    <!-- ============================================ -->
                    <!-- TAB 3: TENTANG -->
                    <!-- ============================================ -->
                    <div v-else-if="activeTab === 'about'" class="space-y-6">
                        <!-- Plugin Description -->
                        <Card>
                            <CardHeader>
                                <CardTitle>Tentang Plugin</CardTitle>
                            </CardHeader>
                            <CardContent>
                                <div class="space-y-4">
                                    <div>
                                        <h4 class="mb-2 text-sm font-medium">Deskripsi</h4>
                                        <p class="text-sm text-muted-foreground">{{ plugin.description }}</p>
                                    </div>
                                    <Separator />
                                    <div class="grid gap-4 sm:grid-cols-2">
                                        <div>
                                            <h4 class="mb-1 text-sm font-medium">Versi</h4>
                                            <p class="text-sm text-muted-foreground">{{ plugin.version }}</p>
                                        </div>
                                        <div>
                                            <h4 class="mb-1 text-sm font-medium">Pembuat</h4>
                                            <p class="text-sm text-muted-foreground">{{ plugin.author }}</p>
                                        </div>
                                        <div>
                                            <h4 class="mb-1 text-sm font-medium">Kategori</h4>
                                            <Badge variant="outline">{{ plugin.category }}</Badge>
                                        </div>
                                        <div>
                                            <h4 class="mb-1 text-sm font-medium">Rating Rata-rata</h4>
                                            <div class="flex items-center gap-2">
                                                <StarRating :rating="plugin.average_rating || 0" :size="16" />
                                                <span class="text-sm text-muted-foreground">
                                                    ({{ plugin.ratings_count || 0 }} rating)
                                                </span>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </CardContent>
                        </Card>

                        <!-- User Rating -->
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

                        <!-- No Ratings Yet -->
                        <Card v-else>
                            <CardContent class="py-12">
                                <div class="flex flex-col items-center justify-center text-center">
                                    <Star class="h-12 w-12 text-muted-foreground" />
                                    <h3 class="mt-4 text-lg font-medium">Belum Ada Rating</h3>
                                    <p class="mt-2 text-muted-foreground">Jadilah yang pertama memberikan rating untuk plugin ini!</p>
                                </div>
                            </CardContent>
                        </Card>
                    </div>
                </div>
            </div>
        </div>
    </AppLayout>
</template>