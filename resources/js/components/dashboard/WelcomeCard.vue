<script setup lang="ts">
import { Card, CardContent } from '@/components/ui/card';
import { usePage } from '@inertiajs/vue3';
import { computed } from 'vue';

const page = usePage();
const userName = computed(() => page.props.auth.user.name);

const getGreeting = () => {
    const hour = new Date().getHours();
    if (hour < 12) return 'Selamat Pagi';
    if (hour < 15) return 'Selamat Siang';
    if (hour < 18) return 'Selamat Sore';
    return 'Selamat Malam';
};

const greeting = getGreeting();
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
                        Semoga harimu menyenangkan!
                    </p>
                </div>
                <div class="hidden text-6xl sm:block">🌟</div>
            </div>
        </CardContent>
    </Card>
</template>
