<script setup lang="ts">
import StarRating from '@/components/StarRating.vue';
import LanguageToggle from '@/components/LanguageToggle.vue';
import { login, register } from '@/routes';
import { Head, Link, router } from '@inertiajs/vue3';
import {
    ArrowLeft,
    BellRing,
    BookOpen,
    Bot,
    CheckCircle,
    Clock,
    Droplets,
    Gift,
    Heart,
    Puzzle,
    Search,
    SmilePlus,
    Sparkles,
} from 'lucide-vue-next';
import { type Component, ref, watch } from 'vue';
import { useDebounceFn } from '@vueuse/core';

interface Plugin {
    slug: string;
    name: string;
    description: string;
    icon: string;
    average_rating?: number;
    total_ratings?: number;
}

interface PaginatedPlugins {
    data: Plugin[];
    current_page: number;
    last_page: number;
    per_page: number;
    total: number;
    from: number;
    to: number;
}

interface Filters {
    search?: string;
    min_rating?: number | string;
    sort_by?: string;
}

const props = withDefaults(
    defineProps<{
        plugins: PaginatedPlugins;
        filters: Filters;
    }>(),
    {
        filters: () => ({}),
    },
);

const search = ref(props.filters.search || '');

const debouncedSearch = useDebounceFn(() => {
    router.get(
        '/explore-plugins',
        { search: search.value || undefined },
        {
            preserveState: true,
            preserveScroll: true,
            replace: true,
        },
    );
}, 300);

watch(search, () => {
    debouncedSearch();
});

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
</script>

<template>
    <Head :title="$t('explorePlugins.title')" />
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
                        <LanguageToggle />
                        <Link
                            v-if="$page.props.auth.user"
                            href="/dashboard"
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

        <!-- Main Content -->
        <section class="container mx-auto px-4 py-12 lg:py-16">
            <div class="mx-auto max-w-6xl">
                <!-- Back to Home Link -->
                <Link
                    href="/"
                    class="mb-8 inline-flex items-center gap-2 text-sm text-muted-foreground transition-colors hover:text-foreground"
                >
                    <ArrowLeft class="h-4 w-4" />
                    {{ $t('explorePlugins.backToHome') }}
                </Link>

                <!-- Header -->
                <div class="mb-8 text-center">
                    <div
                        class="mb-4 inline-flex items-center gap-2 rounded-full border border-border/40 bg-muted/50 px-4 py-2 text-sm"
                    >
                        <Puzzle class="h-4 w-4 text-primary" />
                        <span class="font-medium">{{ $t('explorePlugins.badge') }}</span>
                    </div>
                    <h1
                        class="mb-4 text-4xl font-extrabold tracking-tight sm:text-5xl"
                    >
                        {{ $t('explorePlugins.heading') }}
                    </h1>
                    <p class="text-lg text-muted-foreground max-w-2xl mx-auto">
                        {{ $t('explorePlugins.description') }}
                    </p>
                </div>

                <!-- Search Bar -->
                <div class="mb-8 max-w-xl mx-auto">
                    <div class="relative">
                        <Search
                            class="absolute left-3 top-1/2 h-4 w-4 -translate-y-1/2 text-muted-foreground"
                        />
                        <input
                            v-model="search"
                            type="text"
                            :placeholder="$t('explorePlugins.searchPlaceholder')"
                            class="w-full rounded-lg border border-input bg-background pl-10 pr-4 py-2.5 text-sm transition-colors placeholder:text-muted-foreground focus:border-primary focus:outline-none focus:ring-2 focus:ring-primary/20"
                        />
                    </div>
                </div>

                <!-- Results Info -->
                <div
                    v-if="plugins.data.length > 0"
                    class="mb-6 text-sm text-muted-foreground"
                >
                    {{ $t('explorePlugins.showingResults', { from: plugins.from, to: plugins.to, total: plugins.total }) }}
                    <span v-if="search">
                        {{ $t('explorePlugins.forSearch') }} "<span class="font-medium text-foreground">{{ search }}</span>"
                    </span>
                </div>

                <!-- Empty State -->
                <div
                    v-if="plugins.data.length === 0"
                    class="text-center py-16 rounded-xl border border-border/40 bg-muted/20"
                >
                    <Puzzle
                        class="mx-auto mb-4 h-16 w-16 text-muted-foreground/40"
                    />
                    <h3 class="text-xl font-semibold mb-2">
                        {{ search ? $t('explorePlugins.notFound') : $t('explorePlugins.noPluginsYet') }}
                    </h3>
                    <p class="text-muted-foreground mb-6">
                        {{ search ? $t('explorePlugins.tryOtherKeyword') : $t('explorePlugins.comingSoon') }}
                    </p>
                </div>

                <!-- All Plugins Grid -->
                <div v-else class="space-y-8">
                    <div class="grid gap-6 sm:grid-cols-2 lg:grid-cols-3">
                        <div
                            v-for="plugin in plugins.data"
                            :key="plugin.slug"
                            class="group relative overflow-hidden rounded-xl border border-border/40 bg-card p-6 transition-all hover:border-primary/50 hover:shadow-lg"
                        >
                            <div
                                class="absolute inset-0 bg-gradient-to-br from-primary/5 via-transparent to-transparent opacity-0 transition-opacity group-hover:opacity-100"
                            />
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
                                <p class="mb-3 text-sm text-muted-foreground">
                                    {{ plugin.description }}
                                </p>
                                
                                <!-- Rating Display -->
                                <div class="flex items-center gap-2">
                                    <StarRating 
                                        :rating="plugin.average_rating || 0" 
                                        :size="14" 
                                        show-value 
                                    />
                                    <span class="text-xs text-muted-foreground">
                                        ({{ plugin.total_ratings || 0 }})
                                    </span>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Pagination -->
                    <div
                        v-if="plugins.last_page > 1"
                        class="flex items-center justify-center gap-2"
                    >
                        <Link
                            v-for="page in plugins.last_page"
                            :key="page"
                            :href="`/explore-plugins?page=${page}${search ? `&search=${search}` : ''}`"
                            preserve-state
                            :class="[
                                'inline-flex h-9 w-9 items-center justify-center rounded-md text-sm font-medium transition-colors',
                                page === plugins.current_page
                                    ? 'bg-primary text-primary-foreground'
                                    : 'border border-input bg-background hover:bg-accent hover:text-accent-foreground',
                            ]"
                        >
                            {{ page }}
                        </Link>
                    </div>
                </div>

                <!-- CTA Section -->
                <div
                    v-if="plugins.length > 0 && !$page.props.auth.user"
                    class="mt-16 rounded-2xl border border-border/40 bg-gradient-to-br from-primary/5 to-primary/10 p-8 text-center lg:p-12"
                >
                    <h2
                        class="mb-4 text-3xl font-bold tracking-tight sm:text-4xl"
                    >
                        {{ $t('explorePlugins.ctaTitle') }}
                    </h2>
                    <p class="mb-8 text-lg text-muted-foreground">
                        {{ $t('explorePlugins.ctaDesc') }}
                    </p>
                    <div class="flex flex-col sm:flex-row items-center justify-center gap-4">
                        <Link
                            :href="register()"
                            class="inline-flex h-12 items-center justify-center rounded-md bg-primary px-8 text-base font-semibold text-primary-foreground transition-colors hover:bg-primary/90"
                        >
                            {{ $t('explorePlugins.ctaButton') }}
                        </Link>
                        <Link
                            :href="login()"
                            class="inline-flex h-12 items-center justify-center rounded-md border border-input bg-background px-8 text-base font-semibold transition-colors hover:bg-accent hover:text-accent-foreground"
                        >
                            {{ $t('auth.hasAccount') }}
                        </Link>
                    </div>
                </div>
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
