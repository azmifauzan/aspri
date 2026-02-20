<script setup lang="ts">
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogHeader,
    DialogTitle,
    DialogTrigger,
} from '@/components/ui/dialog';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import AppLayout from '@/layouts/AppLayout.vue';
import { finance } from '@/routes';
import { accounts as financeAccounts } from '@/routes/finance';
import type { BreadcrumbItem, FinanceAccountsProps } from '@/types';

import { Head, useForm } from '@inertiajs/vue3';
import { Banknote, CreditCard, Plus, Wallet } from 'lucide-vue-next';
import { computed, ref } from 'vue';
import { useI18n } from 'vue-i18n';
import Swal from 'sweetalert2';

const { t, locale } = useI18n();

const props = defineProps<FinanceAccountsProps>();

const breadcrumbs = computed<BreadcrumbItem[]>(() => [
    { title: t('finance.title'), href: finance().url },
    { title: t('finance.accounts'), href: financeAccounts().url },
]);

const showAddModal = ref(false);

const form = useForm({
    name: '',
    type: 'cash' as 'cash' | 'bank' | 'e-wallet',
    initial_balance: '',
});

const formatCurrency = (amount: number) => {
    return new Intl.NumberFormat(locale.value === 'id' ? 'id-ID' : 'en-US', {
        style: 'currency',
        currency: 'IDR',
        minimumFractionDigits: 0,
    }).format(amount);
};

const getAccountIcon = (type: string) => {
    switch (type) {
        case 'bank':
            return Banknote;
        case 'e-wallet':
            return CreditCard;
        default:
            return Wallet;
    }
};

const getAccountTypeName = (type: string) => {
    switch (type) {
        case 'bank':
            return t('finance.bankAccount');
        case 'e-wallet':
            return t('finance.eWallet');
        default:
            return t('finance.cash');
    }
};

const submitAccount = () => {
    form.post(financeAccounts().url, {
        preserveScroll: true,
        onSuccess: () => {
            showAddModal.value = false;
            form.reset();
            Swal.fire({
                icon: 'success',
                title: t('finance.accountCreated'),
                text: t('finance.accountCreatedDesc'),
                timer: 2000,
                showConfirmButton: false,
            });
        },
        onError: () => {
            Swal.fire({
                icon: 'error',
                title: t('finance.accountCreateFailed'),
                text: t('finance.accountCreateError'),
            });
        },
    });
};

const totalBalance = () => {
    return props.accounts.reduce((sum, acc) => sum + (acc.current_balance || acc.initial_balance), 0);
};
</script>

<template>
    <Head :title="$t('finance.accountTitle')" />

    <AppLayout :breadcrumbs="breadcrumbs">
        <div class="flex h-full flex-1 flex-col gap-4 p-4">
            <!-- Header -->
            <div class="flex items-center justify-between">
                <h1 class="text-2xl font-bold">{{ $t('finance.accountTitle') }}</h1>
                
                <Dialog v-model:open="showAddModal">
                    <DialogTrigger as-child>
                        <Button>
                            <Plus class="mr-2 h-4 w-4" />
                            {{ $t('finance.addAccount') }}
                        </Button>
                    </DialogTrigger>
                    <DialogContent class="sm:max-w-md">
                        <DialogHeader>
                            <DialogTitle>{{ $t('finance.addAccountTitle') }}</DialogTitle>
                            <DialogDescription>
                                {{ $t('finance.addAccountDesc') }}
                            </DialogDescription>
                        </DialogHeader>
                        <form @submit.prevent="submitAccount" class="space-y-4">
                            <!-- Account Type -->
                            <div class="space-y-2">
                                <Label>{{ $t('finance.accountType') }}</Label>
                                <div class="grid grid-cols-3 gap-2">
                                    <Button
                                        type="button"
                                        :variant="form.type === 'cash' ? 'default' : 'outline'"
                                        class="flex-col gap-1 py-4"
                                        @click="form.type = 'cash'"
                                    >
                                        <Wallet class="h-5 w-5" />
                                        <span class="text-xs">{{ $t('finance.cash') }}</span>
                                    </Button>
                                    <Button
                                        type="button"
                                        :variant="form.type === 'bank' ? 'default' : 'outline'"
                                        class="flex-col gap-1 py-4"
                                        @click="form.type = 'bank'"
                                    >
                                        <Banknote class="h-5 w-5" />
                                        <span class="text-xs">{{ $t('finance.bank') }}</span>
                                    </Button>
                                    <Button
                                        type="button"
                                        :variant="form.type === 'e-wallet' ? 'default' : 'outline'"
                                        class="flex-col gap-1 py-4"
                                        @click="form.type = 'e-wallet'"
                                    >
                                        <CreditCard class="h-5 w-5" />
                                        <span class="text-xs">{{ $t('finance.eWallet') }}</span>
                                    </Button>
                                </div>
                            </div>

                            <!-- Name -->
                            <div class="space-y-2">
                                <Label for="name">{{ $t('finance.accountName') }}</Label>
                                <Input
                                    id="name"
                                    v-model="form.name"
                                    type="text"
                                    :placeholder="$t('finance.accountNamePlaceholder')"
                                    required
                                />
                            </div>

                            <!-- Initial Balance -->
                            <div class="space-y-2">
                                <Label for="balance">{{ $t('finance.initialBalance') }}</Label>
                                <Input
                                    id="balance"
                                    v-model="form.initial_balance"
                                    type="number"
                                    placeholder="0"
                                />
                            </div>

                            <DialogFooter>
                                <Button type="submit" :disabled="form.processing">
                                    {{ $t('common.save') }}
                                </Button>
                            </DialogFooter>
                        </form>
                    </DialogContent>
                </Dialog>
            </div>

            <!-- Total Balance Card -->
            <Card class="bg-gradient-to-br from-primary/90 to-primary text-primary-foreground">
                <CardContent class="p-6">
                    <p class="text-sm opacity-90">{{ $t('finance.totalBalance') }}</p>
                    <p class="mt-1 text-3xl font-bold">
                        {{ formatCurrency(totalBalance()) }}
                    </p>
                    <p class="mt-2 text-sm opacity-80">
                        {{ $t('finance.accountCount', { count: props.accounts.length }) }}
                    </p>
                </CardContent>
            </Card>

            <!-- Accounts List -->
            <Card>
                <CardHeader class="pb-2">
                    <CardTitle class="text-base font-medium">
                        {{ $t('finance.accountList') }}
                    </CardTitle>
                </CardHeader>
                <CardContent>
                    <div
                        v-if="props.accounts.length === 0"
                        class="py-12 text-center text-muted-foreground"
                    >
                        {{ $t('finance.noAccounts') }}
                    </div>
                    <div v-else class="grid gap-3 sm:grid-cols-2 lg:grid-cols-3">
                        <div
                            v-for="account in props.accounts"
                            :key="account.id"
                            class="flex items-center gap-4 rounded-lg border border-border p-4"
                        >
                            <div
                                class="flex h-12 w-12 items-center justify-center rounded-full bg-primary/10"
                            >
                                <component
                                    :is="getAccountIcon(account.type)"
                                    class="h-6 w-6 text-primary"
                                />
                            </div>
                            <div class="flex-1">
                                <p class="font-medium">{{ account.name }}</p>
                                <p class="text-xs text-muted-foreground">
                                    {{ getAccountTypeName(account.type) }}
                                </p>
                            </div>
                            <div class="text-right">
                                <p class="font-semibold">
                                    {{ formatCurrency(account.current_balance || account.initial_balance) }}
                                </p>
                            </div>
                        </div>
                    </div>
                </CardContent>
            </Card>
        </div>
    </AppLayout>
</template>
