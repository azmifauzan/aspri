<script setup lang="ts">
import LanguageToggle from '@/components/LanguageToggle.vue';
import { dashboard, login, register } from '@/routes';
import { Head, Link } from '@inertiajs/vue3';
import {
    BellRing,
    BookOpen,
    Bot,
    Calendar,
    Check,
    CheckCircle,
    Clock,
    Crown,
    Droplets,
    Gift,
    Heart,
    MessageSquare,
    Puzzle,
    SmilePlus,
    Sparkles,
    TrendingUp,
    Wallet,
} from 'lucide-vue-next';
import { computed, type Component } from 'vue';
import { useI18n } from 'vue-i18n';

interface PricingInfo {
    monthly_price: number;
    yearly_price: number;
    free_trial_days: number;
    free_trial_daily_chat_limit: number;
    full_member_daily_chat_limit: number;
}

interface FeaturedPlugin {
    slug: string;
    name: string;
    description: string;
    icon: string;
}

const props = withDefaults(
    defineProps<{
        canRegister: boolean;
        pricing: PricingInfo;
        featuredPlugins: FeaturedPlugin[];
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
        featuredPlugins: () => [],
    },
);

const { t } = useI18n();

const formatCurrency = (value: number) => {
    return new Intl.NumberFormat('id-ID').format(value);
};

const yearlySavings = props.pricing.monthly_price * 12 - props.pricing.yearly_price;

// Map icon names to components
const iconMap: Record<string, Component> = {
    sparkles: Sparkles,
    droplets: Droplets,
    'bell-ring': BellRing,
    'puzzle-piece': Puzzle,
    clock: Clock,
    gift: Gift,
    'book-open': BookOpen,
    'check-circle': CheckCircle,
    heart: Heart,
    'emoji-happy': SmilePlus,
};

const getPluginIcon = (iconName: string): Component => {
    return iconMap[iconName] || Puzzle;
};

const features = computed(() => [
    {
        icon: MessageSquare,
        title: t('landing.featureChatTitle'),
        description: t('landing.featureChatDesc'),
    },
    {
        icon: Bot,
        title: t('landing.featureAiTitle'),
        description: t('landing.featureAiDesc'),
    },
    {
        icon: Calendar,
        title: t('landing.featureScheduleTitle'),
        description: t('landing.featureScheduleDesc'),
    },
    {
        icon: Wallet,
        title: t('landing.featureFinanceTitle'),
        description: t('landing.featureFinanceDesc'),
    },
    {
        icon: Sparkles,
        title: t('landing.featureMultiTitle'),
        description: t('landing.featureMultiDesc'),
    },
    {
        icon: TrendingUp,
        title: t('landing.featureInsightTitle'),
        description: t('landing.featureInsightDesc'),
    },
]);
</script>

<template>
    <Head :title="$t('landing.title')">
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
                    <nav class="flex items-center gap-3">
                        <a
                            href="https://github.com/azmifauzan/aspri"
                            target="_blank"
                            rel="noopener noreferrer"
                            class="hidden sm:block transition-opacity hover:opacity-80"
                        >
                            <img
                                src="https://img.shields.io/github/stars/azmifauzan/aspri?style=social"
                                alt="GitHub stars"
                                class="h-5"
                            />
                        </a>
                        <LanguageToggle />
                        <Link
                            v-if="$page.props.auth.user"
                            :href="dashboard()"
                            class="inline-flex h-9 items-center justify-center rounded-md border border-input bg-background px-4 text-sm font-medium transition-colors hover:bg-accent hover:text-accent-foreground"
                        >
                            {{ $t('common.dashboard') }}
                        </Link>
                        <template v-else>
                            <Link
                                :href="login()"
                                class="inline-flex h-9 items-center justify-center rounded-md px-4 text-sm font-medium transition-colors hover:bg-accent hover:text-accent-foreground"
                            >
                                {{ $t('common.login') }}
                            </Link>
                            <Link
                                v-if="canRegister"
                                :href="register()"
                                class="inline-flex h-9 items-center justify-center rounded-md border border-input bg-primary px-4 text-sm font-medium text-primary-foreground transition-colors hover:bg-primary/90"
                            >
                                {{ $t('common.register') }}
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
                        >{{ $t('landing.badge') }}</span
                    >
                </div>
                <h1
                    class="mb-6 text-4xl font-extrabold tracking-tight sm:text-5xl lg:text-6xl"
                >
                    {{ $t('landing.heroTitle') }}
                    <span class="text-primary">{{ $t('landing.heroHighlight') }}</span>
                </h1>
                <p
                    class="mb-8 text-xl text-muted-foreground sm:text-2xl lg:mb-12"
                >
                    {{ $t('landing.heroDescription') }}
                </p>
                <div
                    class="flex flex-col items-center justify-center gap-4 sm:flex-row"
                >
                    <Link
                        v-if="canRegister"
                        :href="register()"
                        class="inline-flex h-12 items-center justify-center rounded-md bg-primary px-8 text-base font-semibold text-primary-foreground transition-colors hover:bg-primary/90"
                    >
                        {{ $t('landing.heroCtaPrimary') }}
                    </Link>
                    <Link
                        :href="login()"
                        class="inline-flex h-12 items-center justify-center rounded-md border border-input bg-background px-8 text-base font-semibold transition-colors hover:bg-accent hover:text-accent-foreground"
                    >
                        {{ $t('landing.heroCtaSecondary') }}
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
                        {{ $t('landing.featuresTitle') }}
                    </h2>
                    <p class="text-lg text-muted-foreground">
                        {{ $t('landing.featuresSubtitle') }}
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

        <!-- Plugin Showcase Section -->
        <section class="container mx-auto px-4 py-16 lg:py-24 bg-muted/20">
            <div class="mx-auto max-w-6xl">
                <div class="mb-12 text-center">
                    <div
                        class="mb-4 inline-flex items-center gap-2 rounded-full border border-border/40 bg-muted/50 px-4 py-2 text-sm"
                    >
                        <Puzzle class="h-4 w-4 text-primary" />
                        <span class="font-medium">{{ $t('landing.pluginBadge') }}</span>
                    </div>
                    <h2
                        class="mb-4 text-3xl font-bold tracking-tight sm:text-4xl"
                    >
                        {{ $t('landing.pluginTitle') }}
                    </h2>
                    <p class="text-lg text-muted-foreground max-w-2xl mx-auto">
                        {{ $t('landing.pluginSubtitle') }}
                    </p>
                </div>
                <div v-if="featuredPlugins.length > 0" class="grid gap-6 sm:grid-cols-2 lg:grid-cols-3">
                    <div
                        v-for="plugin in featuredPlugins"
                        :key="plugin.slug"
                        class="group relative overflow-hidden rounded-xl border border-border/40 bg-card p-6 transition-all hover:border-primary/50 hover:shadow-lg"
                    >
                        <div class="absolute inset-0 bg-gradient-to-br from-primary/5 via-transparent to-transparent opacity-0 transition-opacity group-hover:opacity-100" />
                        <div class="relative">
                            <div
                                class="mb-4 inline-flex h-14 w-14 items-center justify-center rounded-xl bg-primary/10 transition-colors group-hover:bg-primary/20"
                            >
                                <component
                                    :is="getPluginIcon(plugin.icon)"
                                    class="h-7 w-7 text-primary"
                                />
                            </div>
                            <h3 class="mb-2 text-lg font-semibold">
                                {{ plugin.name }}
                            </h3>
                            <p class="text-sm text-muted-foreground line-clamp-2">
                                {{ plugin.description }}
                            </p>
                        </div>
                    </div>
                </div>
                <div v-else class="text-center py-12">
                    <Puzzle class="mx-auto mb-4 h-16 w-16 text-muted-foreground/40" />
                    <p class="text-lg text-muted-foreground mb-6">
                        {{ $t('landing.pluginEmpty') }}
                    </p>
                </div>
                <!-- View All Plugins Button -->
                <div class="mt-8 text-center">
                    <Link
                        href="/explore-plugins"
                        class="inline-flex items-center gap-2 rounded-lg border border-input bg-background px-6 py-3 text-sm font-medium transition-colors hover:bg-accent hover:text-accent-foreground"
                    >
                        <Puzzle class="h-4 w-4" />
                        {{ $t('landing.pluginViewAll') }}
                    </Link>
                </div>
            </div>
        </section>

        <!-- Pricing Section -->
        <section id="pricing" class="container mx-auto px-4 py-16 lg:py-24 bg-muted/30">
            <div class="mx-auto max-w-4xl">
                <div class="mb-12 text-center">
                    <h2 class="mb-4 text-3xl font-bold tracking-tight sm:text-4xl">
                        {{ $t('landing.pricingTitle') }}
                    </h2>
                    <p class="text-lg text-muted-foreground">
                        {{ $t('landing.pricingSubtitle', { days: pricing.free_trial_days }) }}
                    </p>
                </div>

                <!-- Free Trial Banner -->
                <div class="mb-8 rounded-xl border border-primary/20 bg-primary/5 p-6 text-center">
                    <div class="flex items-center justify-center gap-2 mb-2">
                        <Clock class="h-5 w-5 text-primary" />
                        <span class="text-lg font-semibold">{{ $t('landing.freeTrialTitle', { days: pricing.free_trial_days }) }}</span>
                    </div>
                    <p class="text-muted-foreground mb-4">
                        {{ $t('landing.freeTrialDesc', { limit: pricing.free_trial_daily_chat_limit }) }}
                    </p>
                    <Link
                        v-if="canRegister"
                        :href="register()"
                        class="inline-flex h-10 items-center justify-center rounded-md bg-primary px-6 text-sm font-medium text-primary-foreground transition-colors hover:bg-primary/90"
                    >
                        {{ $t('landing.startFreeTrial') }}
                    </Link>
                </div>

                <!-- Pricing Cards -->
                <div class="grid gap-6 md:grid-cols-2">
                    <!-- Monthly -->
                    <div class="rounded-xl border border-border/40 bg-card p-6">
                        <div class="mb-4 flex items-center gap-2">
                            <Crown class="h-5 w-5 text-primary" />
                            <h3 class="text-xl font-semibold">{{ $t('landing.monthly') }}</h3>
                        </div>
                        <div class="mb-4">
                            <span class="text-4xl font-bold">Rp {{ formatCurrency(pricing.monthly_price) }}</span>
                            <span class="text-muted-foreground">{{ $t('landing.perMonth') }}</span>
                        </div>
                        <ul class="mb-6 space-y-3 text-sm">
                            <li class="flex items-center gap-2">
                                <Check class="h-4 w-4 text-green-500" />
                                {{ $t('landing.chatPerDay', { limit: pricing.full_member_daily_chat_limit }) }}
                            </li>
                            <li class="flex items-center gap-2">
                                <Check class="h-4 w-4 text-green-500" />
                                {{ $t('landing.allPremiumFeatures') }}
                            </li>
                            <li class="flex items-center gap-2">
                                <Check class="h-4 w-4 text-green-500" />
                                {{ $t('landing.prioritySupport') }}
                            </li>
                            <li class="flex items-center gap-2">
                                <Check class="h-4 w-4 text-green-500" />
                                {{ $t('landing.flexibleNoCom') }}
                            </li>
                        </ul>
                        <Link
                            v-if="canRegister"
                            :href="register()"
                            class="flex h-10 w-full items-center justify-center rounded-md border border-input bg-background text-sm font-medium transition-colors hover:bg-accent hover:text-accent-foreground"
                        >
                            {{ $t('landing.chooseMonthly') }}
                        </Link>
                    </div>

                    <!-- Yearly (Best Value) -->
                    <div class="relative rounded-xl border-2 border-primary bg-card p-6 shadow-lg">
                        <div class="absolute -top-3 left-1/2 -translate-x-1/2">
                            <span class="rounded-full bg-green-500 px-3 py-1 text-xs font-semibold text-white">
                                {{ $t('landing.save', { percent: Math.round((yearlySavings / (pricing.monthly_price * 12)) * 100) }) }}
                            </span>
                        </div>
                        <div class="mb-4 flex items-center gap-2">
                            <Crown class="h-5 w-5 text-yellow-500" />
                            <h3 class="text-xl font-semibold">{{ $t('landing.yearly') }}</h3>
                            <span class="rounded-full bg-primary/10 px-2 py-0.5 text-xs font-medium text-primary">
                                {{ $t('landing.bestValue') }}
                            </span>
                        </div>
                        <div class="mb-2">
                            <span class="text-4xl font-bold">Rp {{ formatCurrency(pricing.yearly_price) }}</span>
                            <span class="text-muted-foreground">{{ $t('landing.perYear') }}</span>
                        </div>
                        <p class="mb-4 text-sm text-green-600 dark:text-green-400">
                            {{ $t('landing.equivalentPerMonth', { price: formatCurrency(Math.round(pricing.yearly_price / 12)), savings: formatCurrency(yearlySavings) }) }}
                        </p>
                        <ul class="mb-6 space-y-3 text-sm">
                            <li class="flex items-center gap-2">
                                <Check class="h-4 w-4 text-green-500" />
                                {{ $t('landing.chatPerDay', { limit: pricing.full_member_daily_chat_limit }) }}
                            </li>
                            <li class="flex items-center gap-2">
                                <Check class="h-4 w-4 text-green-500" />
                                {{ $t('landing.allPremiumFeatures') }}
                            </li>
                            <li class="flex items-center gap-2">
                                <Check class="h-4 w-4 text-green-500" />
                                {{ $t('landing.prioritySupport') }}
                            </li>
                            <li class="flex items-center gap-2">
                                <Check class="h-4 w-4 text-green-500" />
                                {{ $t('landing.bestPriceLoyal') }}
                            </li>
                        </ul>
                        <Link
                            v-if="canRegister"
                            :href="register()"
                            class="flex h-10 w-full items-center justify-center rounded-md bg-primary text-sm font-medium text-primary-foreground transition-colors hover:bg-primary/90"
                        >
                            {{ $t('landing.chooseYearly') }}
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
                    {{ $t('landing.ctaTitle') }}
                </h2>
                <p class="mb-8 text-lg text-muted-foreground">
                    {{ $t('landing.ctaDescription') }}
                </p>
                <Link
                    v-if="canRegister"
                    :href="register()"
                    class="inline-flex h-12 items-center justify-center rounded-md bg-primary px-8 text-base font-semibold text-primary-foreground transition-colors hover:bg-primary/90"
                >
                    {{ $t('landing.ctaButton') }}
                </Link>
            </div>
        </section>

        <!-- Footer -->
        <footer
            class="border-t border-border/40 bg-muted/30 py-8 text-center text-sm text-muted-foreground"
        >
            <div class="container mx-auto px-4">
                <p>{{ $t('landing.footer') }}</p>
            </div>
        </footer>
    </div>
</template>
