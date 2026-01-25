<script setup lang="ts">
import { dashboard, login, register } from '@/routes';
import { Head, Link } from '@inertiajs/vue3';
import {
    Bot,
    Calendar,
    MessageSquare,
    Sparkles,
    TrendingUp,
    Wallet,
} from 'lucide-vue-next';

withDefaults(
    defineProps<{
        canRegister: boolean;
    }>(),
    {
        canRegister: true,
    },
);

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
