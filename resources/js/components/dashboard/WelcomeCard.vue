<script setup lang="ts">
import { Card, CardContent } from '@/components/ui/card';
import { usePage } from '@inertiajs/vue3';
import { computed } from 'vue';
import { useI18n } from 'vue-i18n';

const { t } = useI18n();
const page = usePage();
const userName = computed(() => page.props.auth.user.name);

const getGreeting = () => {
    const hour = new Date().getHours();
    if (hour < 12) return t('dashboard.greetingMorning');
    if (hour < 15) return t('dashboard.greetingAfternoon');
    if (hour < 18) return t('dashboard.greetingEvening');
    return t('dashboard.greetingNight');
};

const greeting = computed(() => getGreeting());
</script>

<template>
    <Card
        class="bg-gradient-to-br from-primary/90 to-primary text-primary-foreground"
    >
        <CardContent class="p-6">
            <div class="flex items-center justify-between">
                <div>
                    <p class="text-sm font-medium opacity-90">
                        {{ greeting }} 👋
                    </p>
                    <h2 class="mt-1 text-2xl font-bold">{{ userName }}</h2>
                    <p class="mt-2 text-sm opacity-80">
                        {{ $t('dashboard.welcomeDesc') }}
                    </p>
                </div>
                <div class="hidden text-6xl sm:block">🌟</div>
            </div>
        </CardContent>
    </Card>
</template>
