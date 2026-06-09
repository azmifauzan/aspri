<script setup lang="ts">
import LanguageToggle from '@/components/LanguageToggle.vue';
import { login, register, home } from '@/routes';
import { Head, Link } from '@inertiajs/vue3';
import { ArrowLeft, Bot } from 'lucide-vue-next';

defineProps<{
    lastUpdated?: string;
}>();
</script>

<template>
    <Head :title="$t('legal.termsOfService')" />
    <div
        class="min-h-screen bg-gradient-to-b from-background to-muted/20 dark:from-background dark:to-muted/10 flex flex-col"
    >
        <!-- Header Navigation -->
        <header
            class="sticky top-0 z-50 w-full border-b border-border/40 bg-background/95 backdrop-blur supports-[backdrop-filter]:bg-background/60"
        >
            <div class="container mx-auto flex h-16 items-center px-4">
                <div class="flex flex-1 items-center justify-between">
                    <Link :href="home()" class="flex items-center gap-2">
                        <Bot class="h-6 w-6 text-primary" />
                        <span class="text-xl font-bold">ASPRI</span>
                    </Link>
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
        <main class="container mx-auto px-4 py-12 lg:py-16 flex-1">
            <div class="mx-auto max-w-4xl">
                <!-- Back to Home Link -->
                <Link
                    :href="home()"
                    class="mb-8 inline-flex items-center gap-2 text-sm text-muted-foreground transition-colors hover:text-foreground"
                >
                    <ArrowLeft class="h-4 w-4" />
                    {{ $t('legal.backToHome') }}
                </Link>

                <div class="rounded-2xl border border-border/40 bg-card p-8 lg:p-12 shadow-sm">
                    <h1 class="text-4xl font-extrabold tracking-tight mb-4">
                        {{ $t('legal.termsOfService') }}
                    </h1>
                    <p class="text-muted-foreground mb-8" v-if="lastUpdated">
                        {{ $t('legal.lastUpdated', { date: lastUpdated }) }}
                    </p>

                    <div class="prose prose-slate dark:prose-invert max-w-none" v-html="$t('legal.termsContent')">
                    </div>
                </div>
            </div>
        </main>

        <!-- Footer -->
        <footer
            class="border-t border-border/40 bg-muted/30 py-8 text-center text-sm text-muted-foreground"
        >
            <div class="container mx-auto px-4">
                <p class="mb-4">{{ $t('landing.footer') }}</p>
                <div class="flex items-center justify-center gap-4">
                    <Link href="/privacy-policy" class="hover:text-foreground transition-colors">{{ $t('legal.privacyPolicy') }}</Link>
                    <span>&middot;</span>
                    <Link href="/terms-of-service" class="hover:text-foreground transition-colors">{{ $t('legal.termsOfService') }}</Link>
                </div>
            </div>
        </footer>
    </div>
</template>
