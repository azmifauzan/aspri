<script setup lang="ts">
import InputError from '@/components/InputError.vue';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select';
import { Tabs, TabsContent, TabsList, TabsTrigger } from '@/components/ui/tabs';
import AdminLayout from '@/layouts/AdminLayout.vue';
import admin from '@/routes/admin';
import type { BreadcrumbItem, SettingsPageProps } from '@/types';
import { Head, router, useForm } from '@inertiajs/vue3';
import { Banknote, Bot, Check, CreditCard, Globe, Loader2, Mail, Save, Sparkles, TestTube } from 'lucide-vue-next';
import { ref } from 'vue';
import Swal from 'sweetalert2';

const props = defineProps<SettingsPageProps>();

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Admin', href: admin.index().url },
    { title: 'Settings' },
];

// Loading states for test buttons
const testingAi = ref(false);
const testingTelegram = ref(false);
const testingEmail = ref(false);

// AI Settings Form
const aiForm = useForm({
    ai_provider: props.aiSettings.ai_provider,
    gemini_api_key: '',
    gemini_model: props.aiSettings.gemini_model,
    openai_api_key: '',
    openai_model: props.aiSettings.openai_model,
    anthropic_api_key: '',
    anthropic_model: props.aiSettings.anthropic_model,
});

const submitAi = () => {
    aiForm.post(admin.settings.updateAi().url, {
        preserveScroll: true,
        onSuccess: () => {
            aiForm.reset('gemini_api_key', 'openai_api_key', 'anthropic_api_key');
            Swal.fire({
                icon: 'success',
                title: 'Saved!',
                text: 'AI settings saved successfully',
                timer: 2000,
                showConfirmButton: false,
            });
        },
        onError: () => {
            Swal.fire({
                icon: 'error',
                title: 'Error',
                text: 'Failed to save AI settings',
            });
        },
    });
};

const testAi = () => {
    testingAi.value = true;
    router.post(
        admin.settings.testAi().url,
        {},
        {
            preserveScroll: true,
            onSuccess: (page) => {
                const flash = page.props.flash as Record<string, string | null> | undefined;
                if (flash?.success) {
                    Swal.fire({
                        icon: 'success',
                        title: 'Connection Successful!',
                        text: flash.success,
                    });
                } else if (flash?.error) {
                    Swal.fire({
                        icon: 'error',
                        title: 'Connection Failed',
                        text: flash.error,
                    });
                }
            },
            onError: () => {
                Swal.fire({
                    icon: 'error',
                    title: 'Error',
                    text: 'Failed to test AI connection',
                });
            },
            onFinish: () => {
                testingAi.value = false;
            },
        },
    );
};

// Telegram Settings Form
const telegramForm = useForm({
    bot_token: '',
    webhook_url: props.telegramSettings.webhook_url || '',
    bot_username: props.telegramSettings.bot_username || '',
    admin_chat_ids: props.telegramSettings.admin_chat_ids || '',
});

const submitTelegram = () => {
    telegramForm.post(admin.settings.updateTelegram().url, {
        preserveScroll: true,
        onSuccess: () => {
            telegramForm.reset('bot_token');
            Swal.fire({
                icon: 'success',
                title: 'Saved!',
                text: 'Telegram settings saved successfully',
                timer: 2000,
                showConfirmButton: false,
            });
        },
        onError: () => {
            Swal.fire({
                icon: 'error',
                title: 'Error',
                text: 'Failed to save Telegram settings',
            });
        },
    });
};

const testTelegram = () => {
    testingTelegram.value = true;
    router.post(
        admin.settings.testTelegram().url,
        {},
        {
            preserveScroll: true,
            onSuccess: (page) => {
                const flash = page.props.flash as Record<string, string | null> | undefined;
                if (flash?.success) {
                    Swal.fire({
                        icon: 'success',
                        title: 'Bot Connected!',
                        text: flash.success,
                    });
                } else if (flash?.error) {
                    Swal.fire({
                        icon: 'error',
                        title: 'Connection Failed',
                        text: flash.error,
                    });
                }
            },
            onError: () => {
                Swal.fire({
                    icon: 'error',
                    title: 'Error',
                    text: 'Failed to test Telegram connection',
                });
            },
            onFinish: () => {
                testingTelegram.value = false;
            },
        },
    );
};

// App Settings Form
const appForm = useForm({
    app_name: props.appSettings.app_name,
    app_description: props.appSettings.app_description,
    app_locale: props.appSettings.app_locale,
    app_timezone: props.appSettings.app_timezone,
    maintenance_mode: props.appSettings.maintenance_mode,
});

const submitApp = () => {
    appForm.post(admin.settings.updateApp().url, {
        preserveScroll: true,
        onSuccess: () => {
            Swal.fire({
                icon: 'success',
                title: 'Saved!',
                text: 'Application settings saved successfully',
                timer: 2000,
                showConfirmButton: false,
            });
        },
        onError: () => {
            Swal.fire({
                icon: 'error',
                title: 'Error',
                text: 'Failed to save application settings',
            });
        },
    });
};

// Subscription Settings Form
const subscriptionForm = useForm({
    free_trial_days: props.subscriptionSettings.free_trial_days,
    monthly_price: props.subscriptionSettings.monthly_price,
    yearly_price: props.subscriptionSettings.yearly_price,
    free_trial_daily_chat_limit: props.subscriptionSettings.free_trial_daily_chat_limit,
    full_member_daily_chat_limit: props.subscriptionSettings.full_member_daily_chat_limit,
    bank_name: props.subscriptionSettings.bank_name,
    bank_account_number: props.subscriptionSettings.bank_account_number,
    bank_account_name: props.subscriptionSettings.bank_account_name,
});

const submitSubscription = () => {
    subscriptionForm.post(admin.settings.updateSubscription().url, {
        preserveScroll: true,
        onSuccess: () => {
            Swal.fire({
                icon: 'success',
                title: 'Saved!',
                text: 'Subscription settings saved successfully',
                timer: 2000,
                showConfirmButton: false,
            });
        },
        onError: () => {
            Swal.fire({
                icon: 'error',
                title: 'Error',
                text: 'Failed to save subscription settings',
            });
        },
    });
};

// Email Settings Form
const emailForm = useForm({
    mail_host: props.emailSettings.mail_host,
    mail_port: props.emailSettings.mail_port,
    mail_encryption: props.emailSettings.mail_encryption || 'tls',
    mail_username: props.emailSettings.mail_username,
    mail_password: '',
    mail_from_address: props.emailSettings.mail_from_address,
    mail_from_name: props.emailSettings.mail_from_name,
});

const testEmail = ref('');

const submitEmail = () => {
    emailForm.post(admin.settings.updateEmail().url, {
        preserveScroll: true,
        onSuccess: () => {
            emailForm.reset('mail_password');
            Swal.fire({
                icon: 'success',
                title: 'Saved!',
                text: 'Email settings saved successfully',
                timer: 2000,
                showConfirmButton: false,
            });
        },
        onError: () => {
            Swal.fire({
                icon: 'error',
                title: 'Error',
                text: 'Failed to save email settings',
            });
        },
    });
};

const sendTestEmail = () => {
    if (!testEmail.value) {
        Swal.fire({
            icon: 'warning',
            title: 'Email Required',
            text: 'Please enter an email address to send test email',
        });
        return;
    }
    testingEmail.value = true;
    router.post(
        admin.settings.testEmail().url,
        { test_email: testEmail.value },
        {
            preserveScroll: true,
            onSuccess: (page) => {
                const flash = page.props.flash as Record<string, string | null> | undefined;
                if (flash?.success) {
                    Swal.fire({
                        icon: 'success',
                        title: 'Email Sent!',
                        text: flash.success,
                    });
                } else if (flash?.error) {
                    Swal.fire({
                        icon: 'error',
                        title: 'Email Failed',
                        text: flash.error,
                    });
                }
            },
            onError: () => {
                Swal.fire({
                    icon: 'error',
                    title: 'Error',
                    text: 'Failed to send test email',
                });
            },
            onFinish: () => {
                testingEmail.value = false;
            },
        },
    );
};

const formatCurrency = (value: number) => {
    return new Intl.NumberFormat('id-ID').format(value);
};
</script>

<template>
    <Head title="Settings" />

    <AdminLayout :breadcrumbs="breadcrumbs">
        <div class="flex h-full flex-1 flex-col gap-6 p-4 md:p-6">
            <div>
                <h1 class="text-2xl font-bold tracking-tight">System Settings</h1>
                <p class="text-muted-foreground">Configure AI providers, subscription, email, and application settings</p>
            </div>

            <Tabs default-value="ai" class="w-full">
                <TabsList class="grid w-full grid-cols-5">
                    <TabsTrigger value="ai">
                        <Sparkles class="mr-2 h-4 w-4" />
                        AI
                    </TabsTrigger>
                    <TabsTrigger value="telegram">
                        <Bot class="mr-2 h-4 w-4" />
                        Telegram
                    </TabsTrigger>
                    <TabsTrigger value="subscription">
                        <CreditCard class="mr-2 h-4 w-4" />
                        Subscription
                    </TabsTrigger>
                    <TabsTrigger value="email">
                        <Mail class="mr-2 h-4 w-4" />
                        Email
                    </TabsTrigger>
                    <TabsTrigger value="app">
                        <Globe class="mr-2 h-4 w-4" />
                        App
                    </TabsTrigger>
                </TabsList>

                <!-- AI Tab -->
                <TabsContent value="ai" class="space-y-6">
                    <Card>
                        <CardHeader>
                            <CardTitle class="flex items-center gap-2">
                                <Sparkles class="h-5 w-5" />
                                AI Provider Configuration
                            </CardTitle>
                            <CardDescription>Configure AI providers and their API keys</CardDescription>
                        </CardHeader>
                        <CardContent>
                            <form class="space-y-6" @submit.prevent="submitAi">
                                <div class="space-y-2">
                                    <Label for="ai_provider">Active AI Provider</Label>
                                    <Select v-model="aiForm.ai_provider">
                                        <SelectTrigger class="w-full md:w-64">
                                            <SelectValue />
                                        </SelectTrigger>
                                        <SelectContent>
                                            <SelectItem value="gemini">Google Gemini</SelectItem>
                                            <SelectItem value="openai">OpenAI</SelectItem>
                                            <SelectItem value="anthropic">Anthropic Claude</SelectItem>
                                        </SelectContent>
                                    </Select>
                                </div>

                                <div class="grid gap-6 md:grid-cols-3">
                                    <!-- Gemini -->
                                    <div class="space-y-4 rounded-lg border p-4" :class="aiForm.ai_provider === 'gemini' && 'border-primary'">
                                        <div class="flex items-center justify-between">
                                            <h4 class="font-medium">Google Gemini</h4>
                                            <Check v-if="aiSettings.has_gemini_key" class="h-4 w-4 text-green-500" />
                                        </div>
                                        <div class="space-y-2">
                                            <Label>API Key</Label>
                                            <Input v-model="aiForm.gemini_api_key" type="password" :placeholder="aiSettings.has_gemini_key ? '••••••••' : 'Enter API key'" />
                                        </div>
                                        <div class="space-y-2">
                                            <Label>Model</Label>
                                            <Input v-model="aiForm.gemini_model" placeholder="gemini-pro" />
                                        </div>
                                    </div>

                                    <!-- OpenAI -->
                                    <div class="space-y-4 rounded-lg border p-4" :class="aiForm.ai_provider === 'openai' && 'border-primary'">
                                        <div class="flex items-center justify-between">
                                            <h4 class="font-medium">OpenAI</h4>
                                            <Check v-if="aiSettings.has_openai_key" class="h-4 w-4 text-green-500" />
                                        </div>
                                        <div class="space-y-2">
                                            <Label>API Key</Label>
                                            <Input v-model="aiForm.openai_api_key" type="password" :placeholder="aiSettings.has_openai_key ? '••••••••' : 'Enter API key'" />
                                        </div>
                                        <div class="space-y-2">
                                            <Label>Model</Label>
                                            <Input v-model="aiForm.openai_model" placeholder="gpt-4-turbo" />
                                        </div>
                                    </div>

                                    <!-- Anthropic -->
                                    <div class="space-y-4 rounded-lg border p-4" :class="aiForm.ai_provider === 'anthropic' && 'border-primary'">
                                        <div class="flex items-center justify-between">
                                            <h4 class="font-medium">Anthropic Claude</h4>
                                            <Check v-if="aiSettings.has_anthropic_key" class="h-4 w-4 text-green-500" />
                                        </div>
                                        <div class="space-y-2">
                                            <Label>API Key</Label>
                                            <Input v-model="aiForm.anthropic_api_key" type="password" :placeholder="aiSettings.has_anthropic_key ? '••••••••' : 'Enter API key'" />
                                        </div>
                                        <div class="space-y-2">
                                            <Label>Model</Label>
                                            <Input v-model="aiForm.anthropic_model" placeholder="claude-3-sonnet" />
                                        </div>
                                    </div>
                                </div>

                                <div class="flex items-center gap-2">
                                    <Button type="submit" :disabled="aiForm.processing">
                                        <Save class="mr-2 h-4 w-4" />
                                        Save AI Settings
                                    </Button>
                                    <Button type="button" variant="outline" :disabled="testingAi" @click="testAi">
                                        <Loader2 v-if="testingAi" class="mr-2 h-4 w-4 animate-spin" />
                                        <TestTube v-else class="mr-2 h-4 w-4" />
                                        {{ testingAi ? 'Testing...' : 'Test Connection' }}
                                    </Button>
                                </div>
                            </form>
                        </CardContent>
                    </Card>
                </TabsContent>

                <!-- Telegram Tab -->
                <TabsContent value="telegram" class="space-y-6">
                    <Card>
                        <CardHeader>
                            <CardTitle class="flex items-center gap-2">
                                <Bot class="h-5 w-5" />
                                Telegram Bot Configuration
                            </CardTitle>
                            <CardDescription>Configure Telegram bot for notifications and chat</CardDescription>
                        </CardHeader>
                        <CardContent>
                            <form class="space-y-4" @submit.prevent="submitTelegram">
                                <div class="grid gap-4 md:grid-cols-3">
                                    <div class="space-y-2">
                                        <Label>Bot Token</Label>
                                        <Input v-model="telegramForm.bot_token" type="password" :placeholder="telegramSettings.has_bot_token ? '••••••••' : 'Enter bot token'" />
                                        <InputError :message="telegramForm.errors.bot_token" />
                                    </div>
                                    <div class="space-y-2">
                                        <Label>Webhook URL</Label>
                                        <Input v-model="telegramForm.webhook_url" placeholder="https://your-domain.com/api/telegram/webhook" />
                                        <InputError :message="telegramForm.errors.webhook_url" />
                                    </div>
                                    <div class="space-y-2">
                                        <Label>Bot Username</Label>
                                        <Input v-model="telegramForm.bot_username" placeholder="@your_bot" />
                                        <InputError :message="telegramForm.errors.bot_username" />
                                    </div>
                                </div>

                                <div class="space-y-2">
                                    <Label>Admin Chat IDs</Label>
                                    <Input v-model="telegramForm.admin_chat_ids" placeholder="123456789, 987654321" />
                                    <p class="text-xs text-muted-foreground">Comma-separated Telegram chat IDs to receive admin notifications (new registrations, payment proofs, etc.)</p>
                                    <InputError :message="telegramForm.errors.admin_chat_ids" />
                                </div>

                                <div class="flex items-center gap-2">
                                    <Button type="submit" :disabled="telegramForm.processing">
                                        <Save class="mr-2 h-4 w-4" />
                                        Save Telegram Settings
                                    </Button>
                                    <Button type="button" variant="outline" :disabled="testingTelegram" @click="testTelegram">
                                        <Loader2 v-if="testingTelegram" class="mr-2 h-4 w-4 animate-spin" />
                                        <TestTube v-else class="mr-2 h-4 w-4" />
                                        {{ testingTelegram ? 'Testing...' : 'Test Bot' }}
                                    </Button>
                                </div>
                            </form>
                        </CardContent>
                    </Card>
                </TabsContent>

                <!-- Subscription Tab -->
                <TabsContent value="subscription" class="space-y-6">
                    <Card>
                        <CardHeader>
                            <CardTitle class="flex items-center gap-2">
                                <CreditCard class="h-5 w-5" />
                                Subscription & Pricing
                            </CardTitle>
                            <CardDescription>Configure subscription plans and pricing</CardDescription>
                        </CardHeader>
                        <CardContent>
                            <form class="space-y-6" @submit.prevent="submitSubscription">
                                <!-- Pricing -->
                                <div class="space-y-4">
                                    <h4 class="font-medium">Pricing</h4>
                                    <div class="grid gap-4 md:grid-cols-3">
                                        <div class="space-y-2">
                                            <Label>Free Trial Days</Label>
                                            <Input v-model.number="subscriptionForm.free_trial_days" type="number" min="1" max="365" />
                                            <InputError :message="subscriptionForm.errors.free_trial_days" />
                                        </div>
                                        <div class="space-y-2">
                                            <Label>Monthly Price (Rp)</Label>
                                            <Input v-model.number="subscriptionForm.monthly_price" type="number" min="0" />
                                            <p class="text-xs text-muted-foreground">Rp {{ formatCurrency(subscriptionForm.monthly_price) }}</p>
                                            <InputError :message="subscriptionForm.errors.monthly_price" />
                                        </div>
                                        <div class="space-y-2">
                                            <Label>Yearly Price (Rp)</Label>
                                            <Input v-model.number="subscriptionForm.yearly_price" type="number" min="0" />
                                            <p class="text-xs text-muted-foreground">Rp {{ formatCurrency(subscriptionForm.yearly_price) }}</p>
                                            <InputError :message="subscriptionForm.errors.yearly_price" />
                                        </div>
                                    </div>
                                </div>

                                <!-- Chat Limits -->
                                <div class="space-y-4">
                                    <h4 class="font-medium">Daily Chat Limits</h4>
                                    <div class="grid gap-4 md:grid-cols-2">
                                        <div class="space-y-2">
                                            <Label>Free Trial Daily Chat Limit</Label>
                                            <Input v-model.number="subscriptionForm.free_trial_daily_chat_limit" type="number" min="1" />
                                            <InputError :message="subscriptionForm.errors.free_trial_daily_chat_limit" />
                                        </div>
                                        <div class="space-y-2">
                                            <Label>Full Member Daily Chat Limit</Label>
                                            <Input v-model.number="subscriptionForm.full_member_daily_chat_limit" type="number" min="1" />
                                            <InputError :message="subscriptionForm.errors.full_member_daily_chat_limit" />
                                        </div>
                                    </div>
                                </div>

                                <!-- Bank Info -->
                                <div class="space-y-4">
                                    <h4 class="font-medium flex items-center gap-2">
                                        <Banknote class="h-4 w-4" />
                                        Bank Transfer Info
                                    </h4>
                                    <div class="grid gap-4 md:grid-cols-3">
                                        <div class="space-y-2">
                                            <Label>Bank Name</Label>
                                            <Input v-model="subscriptionForm.bank_name" placeholder="BCA, Mandiri, BNI, etc." />
                                            <InputError :message="subscriptionForm.errors.bank_name" />
                                        </div>
                                        <div class="space-y-2">
                                            <Label>Account Number</Label>
                                            <Input v-model="subscriptionForm.bank_account_number" placeholder="1234567890" />
                                            <InputError :message="subscriptionForm.errors.bank_account_number" />
                                        </div>
                                        <div class="space-y-2">
                                            <Label>Account Name</Label>
                                            <Input v-model="subscriptionForm.bank_account_name" placeholder="Your Name" />
                                            <InputError :message="subscriptionForm.errors.bank_account_name" />
                                        </div>
                                    </div>
                                </div>

                                <div class="flex items-center gap-2">
                                    <Button type="submit" :disabled="subscriptionForm.processing">
                                        <Save class="mr-2 h-4 w-4" />
                                        Save Subscription Settings
                                    </Button>
                                </div>
                            </form>
                        </CardContent>
                    </Card>
                </TabsContent>

                <!-- Email Tab -->
                <TabsContent value="email" class="space-y-6">
                    <Card>
                        <CardHeader>
                            <CardTitle class="flex items-center gap-2">
                                <Mail class="h-5 w-5" />
                                Email / SMTP Configuration
                            </CardTitle>
                            <CardDescription>Configure SMTP settings for email verification and notifications (Brevo recommended)</CardDescription>
                        </CardHeader>
                        <CardContent>
                            <form class="space-y-6" @submit.prevent="submitEmail">
                                <div class="grid gap-4 md:grid-cols-3">
                                    <div class="space-y-2">
                                        <Label>SMTP Host</Label>
                                        <Input v-model="emailForm.mail_host" placeholder="smtp-relay.brevo.com" />
                                        <InputError :message="emailForm.errors.mail_host" />
                                    </div>
                                    <div class="space-y-2">
                                        <Label>SMTP Port</Label>
                                        <Input v-model.number="emailForm.mail_port" type="number" placeholder="587" />
                                        <InputError :message="emailForm.errors.mail_port" />
                                    </div>
                                    <div class="space-y-2">
                                        <Label>Encryption</Label>
                                        <Select v-model="emailForm.mail_encryption">
                                            <SelectTrigger>
                                                <SelectValue />
                                            </SelectTrigger>
                                            <SelectContent>
                                                <SelectItem value="tls">TLS</SelectItem>
                                                <SelectItem value="ssl">SSL</SelectItem>
                                                <SelectItem :value="null">None</SelectItem>
                                            </SelectContent>
                                        </Select>
                                    </div>
                                </div>

                                <div class="grid gap-4 md:grid-cols-2">
                                    <div class="space-y-2">
                                        <Label>SMTP Username</Label>
                                        <Input v-model="emailForm.mail_username" placeholder="your-brevo-smtp-key" />
                                        <InputError :message="emailForm.errors.mail_username" />
                                    </div>
                                    <div class="space-y-2">
                                        <Label>SMTP Password</Label>
                                        <Input v-model="emailForm.mail_password" type="password" :placeholder="emailSettings.has_mail_password ? '••••••••' : 'Enter SMTP password'" />
                                        <InputError :message="emailForm.errors.mail_password" />
                                    </div>
                                </div>

                                <div class="grid gap-4 md:grid-cols-2">
                                    <div class="space-y-2">
                                        <Label>From Email Address</Label>
                                        <Input v-model="emailForm.mail_from_address" type="email" placeholder="noreply@example.com" />
                                        <InputError :message="emailForm.errors.mail_from_address" />
                                    </div>
                                    <div class="space-y-2">
                                        <Label>From Name</Label>
                                        <Input v-model="emailForm.mail_from_name" placeholder="ASPRI" />
                                        <InputError :message="emailForm.errors.mail_from_name" />
                                    </div>
                                </div>

                                <div class="flex items-center gap-2">
                                    <Button type="submit" :disabled="emailForm.processing">
                                        <Save class="mr-2 h-4 w-4" />
                                        Save Email Settings
                                    </Button>
                                </div>
                            </form>
                        </CardContent>
                    </Card>

                    <!-- Test Email Card -->
                    <Card>
                        <CardHeader>
                            <CardTitle>Test Email</CardTitle>
                            <CardDescription>Send a test email to verify your SMTP configuration</CardDescription>
                        </CardHeader>
                        <CardContent>
                            <div class="flex gap-4">
                                <Input v-model="testEmail" type="email" placeholder="your@email.com" class="max-w-sm" />
                                <Button type="button" variant="outline" :disabled="testingEmail" @click="sendTestEmail">
                                    <Loader2 v-if="testingEmail" class="mr-2 h-4 w-4 animate-spin" />
                                    <TestTube v-else class="mr-2 h-4 w-4" />
                                    {{ testingEmail ? 'Sending...' : 'Send Test Email' }}
                                </Button>
                            </div>
                        </CardContent>
                    </Card>
                </TabsContent>

                <!-- App Tab -->
                <TabsContent value="app" class="space-y-6">
                    <Card>
                        <CardHeader>
                            <CardTitle class="flex items-center gap-2">
                                <Globe class="h-5 w-5" />
                                Application Settings
                            </CardTitle>
                            <CardDescription>General application configuration</CardDescription>
                        </CardHeader>
                        <CardContent>
                            <form class="space-y-4" @submit.prevent="submitApp">
                                <div class="grid gap-4 md:grid-cols-2">
                                    <div class="space-y-2">
                                        <Label>Application Name</Label>
                                        <Input v-model="appForm.app_name" />
                                        <InputError :message="appForm.errors.app_name" />
                                    </div>
                                    <div class="space-y-2">
                                        <Label>Description</Label>
                                        <Input v-model="appForm.app_description" />
                                        <InputError :message="appForm.errors.app_description" />
                                    </div>
                                </div>

                                <div class="grid gap-4 md:grid-cols-2">
                                    <div class="space-y-2">
                                        <Label>Locale</Label>
                                        <Select v-model="appForm.app_locale">
                                            <SelectTrigger>
                                                <SelectValue />
                                            </SelectTrigger>
                                            <SelectContent>
                                                <SelectItem value="id">Indonesia</SelectItem>
                                                <SelectItem value="en">English</SelectItem>
                                            </SelectContent>
                                        </Select>
                                    </div>
                                    <div class="space-y-2">
                                        <Label>Timezone</Label>
                                        <Select v-model="appForm.app_timezone">
                                            <SelectTrigger>
                                                <SelectValue />
                                            </SelectTrigger>
                                            <SelectContent>
                                                <SelectItem value="Asia/Jakarta">Asia/Jakarta (WIB)</SelectItem>
                                                <SelectItem value="Asia/Makassar">Asia/Makassar (WITA)</SelectItem>
                                                <SelectItem value="Asia/Jayapura">Asia/Jayapura (WIT)</SelectItem>
                                                <SelectItem value="UTC">UTC</SelectItem>
                                            </SelectContent>
                                        </Select>
                                    </div>
                                </div>

                                <div class="flex items-center gap-2">
                                    <Button type="submit" :disabled="appForm.processing">
                                        <Save class="mr-2 h-4 w-4" />
                                        Save Application Settings
                                    </Button>
                                </div>
                            </form>
                        </CardContent>
                    </Card>
                </TabsContent>
            </Tabs>
        </div>
    </AdminLayout>
</template>
