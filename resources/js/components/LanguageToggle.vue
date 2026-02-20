<script setup lang="ts">
import { Button } from '@/components/ui/button';
import {
    DropdownMenu,
    DropdownMenuContent,
    DropdownMenuItem,
    DropdownMenuTrigger,
} from '@/components/ui/dropdown-menu';
import { getLocale, setLocale } from '@/i18n';
import { Globe } from 'lucide-vue-next';
import { ref, watch } from 'vue';
import { useI18n } from 'vue-i18n';

const { locale } = useI18n();
const currentLocale = ref(getLocale());

const languages = [
    { code: 'en' as const, label: 'English', flag: '🇺🇸' },
    { code: 'id' as const, label: 'Indonesia', flag: '🇮🇩' },
];

const switchLocale = (code: 'en' | 'id') => {
    setLocale(code);
    currentLocale.value = code;
};

watch(locale, (val) => {
    currentLocale.value = val;
});
</script>

<template>
    <DropdownMenu>
        <DropdownMenuTrigger as-child>
            <Button variant="ghost" size="icon" class="h-9 w-9">
                <Globe class="h-4 w-4" />
                <span class="sr-only">{{ $t('common.language') }}</span>
            </Button>
        </DropdownMenuTrigger>
        <DropdownMenuContent align="end">
            <DropdownMenuItem
                v-for="lang in languages"
                :key="lang.code"
                @click="switchLocale(lang.code)"
                class="flex items-center gap-2"
                :class="{ 'bg-accent': currentLocale === lang.code }"
            >
                <span>{{ lang.flag }}</span>
                <span>{{ lang.label }}</span>
            </DropdownMenuItem>
        </DropdownMenuContent>
    </DropdownMenu>
</template>
