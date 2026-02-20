import { createI18n } from 'vue-i18n';
import en from './en';
import id from './id';

const savedLocale = localStorage.getItem('locale') || 'en';

const i18n = createI18n({
    legacy: false,
    locale: savedLocale,
    fallbackLocale: 'en',
    messages: {
        en,
        id,
    },
});

export function setLocale(locale: 'en' | 'id'): void {
    i18n.global.locale.value = locale;
    localStorage.setItem('locale', locale);
    document.documentElement.setAttribute('lang', locale);

    // Notify backend about locale change
    fetch('/locale', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json',
            'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') ?? '',
        },
        body: JSON.stringify({ locale }),
    }).catch(() => {
        // silently fail
    });
}

export function getLocale(): string {
    return i18n.global.locale.value;
}

export default i18n;
