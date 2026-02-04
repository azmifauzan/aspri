<script setup lang="ts">
import { dashboard, login, register } from '@/routes';
import { Head, Link } from '@inertiajs/vue3';
import {
    Bot,
    Calendar,
    Check,
    Clock,
    Crown,
    MessageSquare,
    Sparkles,
    TrendingUp,
    Wallet,
} from 'lucide-vue-next';

interface PricingInfo {
    monthly_price: number;
    yearly_price: number;
    free_trial_days: number;
    free_trial_daily_chat_limit: number;
    full_member_daily_chat_limit: number;
}

const props = withDefaults(
    defineProps<{
        canRegister: boolean;
        pricing: PricingInfo;
    }>(),
    {
        canRegister: true,
        pricing: () => ({
            monthly_price: 10000,
            yearly_price: 100000,
            free_trial_days: 30,
            free_trial_daily_chat_limit: 50,
            full_member_daily_chat_limit: 500,
        }),
    },
);

const formatCurrency = (value: number) => {
    return new Intl.NumberFormat('id-ID').format(value);
};

const yearlySavings = props.pricing.monthly_price * 12 - props.pricing.yearly_price;

const features = [
    {
        icon: MessageSquare,
        title: 'Chat-First Experience',
        description:
            'Kelola jadwal dan keuangan Anda hanya dengan berbicara menggunakan bahasa natural',
    },
    {
        icon: Bot,
        title: 'AI-Powered Assistant',
        description:
            'Asisten pribadi cerdas yang memahami konteks dan preferensi Anda',
    },
    {
        icon: Calendar,
        title: 'Smart Scheduling',
        description:
            'Manajemen jadwal dengan reminder otomatis via web dan Telegram',
    },
    {
        icon: Wallet,
        title: 'Finance Tracking',
        description:
            'Catat dan analisis pengeluaran dengan visualisasi yang informatif',
    },
    {
        icon: Sparkles,
        title: 'Multi-Platform',
        description:
            'Akses melalui web atau Telegram - data selalu tersinkronisasi',
    },
    {
        icon: TrendingUp,
        title: 'Insights & Reports',
        description:
            'Laporan bulanan dan insight untuk keputusan finansial lebih baik',
    },
];
</script>

<template>
    <Head title="Welcome to ASPRI">
        <link rel="preconnect" href="https://rsms.me/" />
        <link rel="stylesheet" href="https://rsms.me/inter/inter.css" />
    </Head>
    <div
        class="min-h-screen bg-gradient-to-b from-background to-muted/20 dark:from-background dark:to-muted/10"
    >
        <!-- Header Navigation -->
        <header
            class="sticky top-0 z-50 w-full border-b border-border/40 bg-background/95 backdrop-blur supports-[backdrop-filter]:bg-background/60"
        >
            <div class="container mx-auto flex h-16 items-center px-4">
                <div class="flex flex-1 items-center justify-between">
                    <div class="flex items-center gap-2">
                        <Bot class="h-6 w-6 text-primary" />
                        <span class="text-xl font-bold">ASPRI</span>
                    </div>
                    <nav class="flex items-center gap-2">
                        <Link
                            v-if="$page.props.auth.user"
                            :href="dashboard()"
                            class="inline-flex h-9 items-center justify-center rounded-md border border-input bg-background px-4 text-sm font-medium transition-colors hover:bg-accent hover:text-accent-foreground"
                        >
                            Dashboard
                        </Link>
                        <template v-else>
                            <Link
                                :href="login()"
                                class="inline-flex h-9 items-center justify-center rounded-md px-4 text-sm font-medium transition-colors hover:bg-accent hover:text-accent-foreground"
                            >
                                Masuk
                            </Link>
                            <Link
                                v-if="canRegister"
                                :href="register()"
                                class="inline-flex h-9 items-center justify-center rounded-md border border-input bg-primary px-4 text-sm font-medium text-primary-foreground transition-colors hover:bg-primary/90"
                            >
                                Daftar Gratis
                            </Link>
                        </template>
                    </nav>
                </div>
            </div>
        </header>

        <!-- Hero Section -->
        <section class="container mx-auto px-4 py-20 lg:py-28">
            <div class="mx-auto max-w-4xl text-center">
                <div
                    class="mb-6 inline-flex items-center gap-2 rounded-full border border-border/40 bg-muted/50 px-4 py-2 text-sm"
                >
                    <Sparkles class="h-4 w-4 text-primary" />
                    <span class="font-medium"
                        >Asisten Pribadi Berbasis AI</span
                    >
                </div>
                <h1
                    class="mb-6 text-4xl font-extrabold tracking-tight sm:text-5xl lg:text-6xl"
                >
                    Kelola Jadwal dan Keuangan
                    <span class="text-primary">dengan Mudah</span>
                </h1>
                <p
                    class="mb-8 text-xl text-muted-foreground sm:text-2xl lg:mb-12"
                >
                    ASPRI membantu Anda mengelola aktivitas harian melalui
                    percakapan natural. Cukup chat, dan biarkan AI mengurus
                    sisanya.
                </p>
                <div
                    class="flex flex-col items-center justify-center gap-4 sm:flex-row"
                >
                    <Link
                        v-if="canRegister"
                        :href="register()"
                        class="inline-flex h-12 items-center justify-center rounded-md bg-primary px-8 text-base font-semibold text-primary-foreground transition-colors hover:bg-primary/90"
                    >
                        Mulai Sekarang
                    </Link>
                    <Link
                        :href="login()"
                        class="inline-flex h-12 items-center justify-center rounded-md border border-input bg-background px-8 text-base font-semibold transition-colors hover:bg-accent hover:text-accent-foreground"
                    >
                        Sudah Punya Akun?
                    </Link>
                </div>
            </div>
        </section>

        <!-- Features Grid -->
        <section class="container mx-auto px-4 py-16 lg:py-24">
            <div class="mx-auto max-w-6xl">
                <div class="mb-12 text-center">
                    <h2
                        class="mb-4 text-3xl font-bold tracking-tight sm:text-4xl"
                    >
                        Fitur Unggulan
                    </h2>
                    <p class="text-lg text-muted-foreground">
                        Semua yang Anda butuhkan untuk produktivitas harian
                    </p>
                </div>
                <div class="grid gap-6 sm:grid-cols-2 lg:grid-cols-3">
                    <div
                        v-for="feature in features"
                        :key="feature.title"
                        class="group rounded-lg border border-border/40 bg-card p-6 transition-all hover:border-primary/50 hover:shadow-md"
                    >
                        <div
                            class="mb-4 inline-flex h-12 w-12 items-center justify-center rounded-lg bg-primary/10 transition-colors group-hover:bg-primary/20"
                        >
                            <component
                                :is="feature.icon"
                                class="h-6 w-6 text-primary"
                            />
                        </div>
                        <h3 class="mb-2 text-lg font-semibold">
                            {{ feature.title }}
                        </h3>
                        <p class="text-sm text-muted-foreground">
                            {{ feature.description }}
                        </p>
                    </div>
                </div>
            </div>
        </section>

        <!-- Pricing Section -->
        <section id="pricing" class="container mx-auto px-4 py-16 lg:py-24 bg-muted/30">
            <div class="mx-auto max-w-4xl">
                <div class="mb-12 text-center">
                    <h2 class="mb-4 text-3xl font-bold tracking-tight sm:text-4xl">
                        Harga Terjangkau
                    </h2>
                    <p class="text-lg text-muted-foreground">
                        Mulai dengan {{ pricing.free_trial_days }} hari trial gratis, lalu pilih paket yang sesuai
                    </p>
                </div>

                <!-- Free Trial Banner -->
                <div class="mb-8 rounded-xl border border-primary/20 bg-primary/5 p-6 text-center">
                    <div class="flex items-center justify-center gap-2 mb-2">
                        <Clock class="h-5 w-5 text-primary" />
                        <span class="text-lg font-semibold">Free Trial {{ pricing.free_trial_days }} Hari</span>
                    </div>
                    <p class="text-muted-foreground mb-4">
                        Coba semua fitur dengan {{ pricing.free_trial_daily_chat_limit }} chat AI per hari, tanpa kartu kredit
                    </p>
                    <Link
                        v-if="canRegister"
                        :href="register()"
                        class="inline-flex h-10 items-center justify-center rounded-md bg-primary px-6 text-sm font-medium text-primary-foreground transition-colors hover:bg-primary/90"
                    >
                        Mulai Trial Gratis
                    </Link>
                </div>

                <!-- Pricing Cards -->
                <div class="grid gap-6 md:grid-cols-2">
                    <!-- Monthly -->
                    <div class="rounded-xl border border-border/40 bg-card p-6">
                        <div class="mb-4 flex items-center gap-2">
                            <Crown class="h-5 w-5 text-primary" />
                            <h3 class="text-xl font-semibold">Bulanan</h3>
                        </div>
                        <div class="mb-4">
                            <span class="text-4xl font-bold">Rp {{ formatCurrency(pricing.monthly_price) }}</span>
                            <span class="text-muted-foreground">/bulan</span>
                        </div>
                        <ul class="mb-6 space-y-3 text-sm">
                            <li class="flex items-center gap-2">
                                <Check class="h-4 w-4 text-green-500" />
                                {{ pricing.full_member_daily_chat_limit }} chat AI per hari
                            </li>
                            <li class="flex items-center gap-2">
                                <Check class="h-4 w-4 text-green-500" />
                                Semua fitur premium
                            </li>
                            <li class="flex items-center gap-2">
                                <Check class="h-4 w-4 text-green-500" />
                                Support prioritas
                            </li>
                            <li class="flex items-center gap-2">
                                <Check class="h-4 w-4 text-green-500" />
                                Fleksibel tanpa komitmen
                            </li>
                        </ul>
                        <Link
                            v-if="canRegister"
                            :href="register()"
                            class="flex h-10 w-full items-center justify-center rounded-md border border-input bg-background text-sm font-medium transition-colors hover:bg-accent hover:text-accent-foreground"
                        >
                            Pilih Bulanan
                        </Link>
                    </div>

                    <!-- Yearly (Best Value) -->
                    <div class="relative rounded-xl border-2 border-primary bg-card p-6 shadow-lg">
                        <div class="absolute -top-3 left-1/2 -translate-x-1/2">
                            <span class="rounded-full bg-green-500 px-3 py-1 text-xs font-semibold text-white">
                                Hemat {{ Math.round((yearlySavings / (pricing.monthly_price * 12)) * 100) }}%
                            </span>
                        </div>
                        <div class="mb-4 flex items-center gap-2">
                            <Crown class="h-5 w-5 text-yellow-500" />
                            <h3 class="text-xl font-semibold">Tahunan</h3>
                            <span class="rounded-full bg-primary/10 px-2 py-0.5 text-xs font-medium text-primary">
                                Best Value
                            </span>
                        </div>
                        <div class="mb-2">
                            <span class="text-4xl font-bold">Rp {{ formatCurrency(pricing.yearly_price) }}</span>
                            <span class="text-muted-foreground">/tahun</span>
                        </div>
                        <p class="mb-4 text-sm text-green-600 dark:text-green-400">
                            Setara Rp {{ formatCurrency(Math.round(pricing.yearly_price / 12)) }}/bulan • Hemat Rp {{ formatCurrency(yearlySavings) }}
                        </p>
                        <ul class="mb-6 space-y-3 text-sm">
                            <li class="flex items-center gap-2">
                                <Check class="h-4 w-4 text-green-500" />
                                {{ pricing.full_member_daily_chat_limit }} chat AI per hari
                            </li>
                            <li class="flex items-center gap-2">
                                <Check class="h-4 w-4 text-green-500" />
                                Semua fitur premium
                            </li>
                            <li class="flex items-center gap-2">
                                <Check class="h-4 w-4 text-green-500" />
                                Support prioritas
                            </li>
                            <li class="flex items-center gap-2">
                                <Check class="h-4 w-4 text-green-500" />
                                Harga terbaik untuk pengguna setia
                            </li>
                        </ul>
                        <Link
                            v-if="canRegister"
                            :href="register()"
                            class="flex h-10 w-full items-center justify-center rounded-md bg-primary text-sm font-medium text-primary-foreground transition-colors hover:bg-primary/90"
                        >
                            Pilih Tahunan
                        </Link>
                    </div>
                </div>
            </div>
        </section>

        <!-- CTA Section -->
        <section class="container mx-auto px-4 py-16 lg:py-24">
            <div
                class="mx-auto max-w-4xl rounded-2xl border border-border/40 bg-gradient-to-br from-primary/5 to-primary/10 p-8 text-center lg:p-12"
            >
                <h2 class="mb-4 text-3xl font-bold tracking-tight sm:text-4xl">
                    Siap untuk Memulai?
                </h2>
                <p class="mb-8 text-lg text-muted-foreground">
                    Daftar sekarang dan rasakan kemudahan mengelola kehidupan
                    Anda dengan ASPRI
                </p>
                <Link
                    v-if="canRegister"
                    :href="register()"
                    class="inline-flex h-12 items-center justify-center rounded-md bg-primary px-8 text-base font-semibold text-primary-foreground transition-colors hover:bg-primary/90"
                >
                    Daftar Gratis Sekarang
                </Link>
            </div>
        </section>

        <!-- Footer -->
        <footer
            class="border-t border-border/40 bg-muted/30 py-8 text-center text-sm text-muted-foreground"
        >
            <div class="container mx-auto px-4">
                <p>&copy; 2026 ASPRI. Asisten Pribadi Berbasis AI.</p>
            </div>
        </footer>
    </div>
</template>
