<script setup lang="ts">
import { Button } from '@/components/ui/button';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { categories as financeCategories, transactions as financeTransactions } from '@/routes/finance';
import type { FinanceAccount, FinanceCategory } from '@/types';
import { Link, useForm } from '@inertiajs/vue3';
import { computed } from 'vue';
import { useI18n } from 'vue-i18n';
import Swal from 'sweetalert2';

const { t } = useI18n();

const props = withDefaults(defineProps<{
    open: boolean;
    categories?: FinanceCategory[];
    accounts?: FinanceAccount[];
}>(), {
    categories: () => [],
    accounts: () => [],
});

const emit = defineEmits<{
    (e: 'update:open', value: boolean): void;
    (e: 'success'): void;
}>();


const form = useForm({
    tx_type: 'expense' as 'income' | 'expense',
    amount: '',
    category_id: '',
    account_id: '',
    occurred_at: new Date().toISOString().split('T')[0],
    note: '',
});

const filteredCategories = computed(() => {
    return props.categories.filter((c) => c.tx_type === form.tx_type);
});

const submitTransaction = () => {
    form.post(financeTransactions().url, {
        preserveScroll: true,
        onSuccess: () => {
            emit('update:open', false);
            form.reset();
            emit('success');
            Swal.fire({
                icon: 'success',
                title: t('finance.transactionRecorded'),
                text: t('finance.transactionRecordedText'),
                timer: 2000,
                showConfirmButton: false,
            });
        },
        onError: () => {
            Swal.fire({
                icon: 'error',
                title: t('finance.transactionRecordFailed'),
                text: t('finance.errorOccurred'),
            });
        },
    });
};
</script>

<template>
    <Dialog :open="open" @update:open="$emit('update:open', $event)">
        <DialogContent class="sm:max-w-md">
            <DialogHeader>
                <DialogTitle>{{ $t('finance.addTransaction') }}</DialogTitle>
                <DialogDescription>
                    {{ $t('finance.recordNewTransaction') }}
                </DialogDescription>
            </DialogHeader>
            <form @submit.prevent="submitTransaction" class="space-y-4">
                <!-- Transaction Type -->
                <div class="flex gap-2">
                    <Button
                        type="button"
                        :variant="form.tx_type === 'expense' ? 'default' : 'outline'"
                        class="flex-1"
                        @click="form.tx_type = 'expense'"
                    >
                        {{ $t('finance.expense') }}
                    </Button>
                    <Button
                        type="button"
                        :variant="form.tx_type === 'income' ? 'default' : 'outline'"
                        class="flex-1"
                        @click="form.tx_type = 'income'"
                    >
                        {{ $t('finance.income') }}
                    </Button>
                </div>

                <!-- Amount -->
                <div class="space-y-2">
                    <Label for="amount">{{ $t('finance.amount') }}</Label>
                    <Input
                        id="amount"
                        v-model="form.amount"
                        type="number"
                        placeholder="0"
                        required
                    />
                </div>

                <!-- Category -->
                <div class="space-y-2">
                    <div class="flex items-center justify-between">
                        <Label for="category">{{ $t('finance.category') }}</Label>
                        <Link
                            :href="financeCategories().url"
                            class="text-xs text-primary hover:underline"
                        >
                            + {{ $t('finance.manageCategories') }}
                        </Link>
                    </div>
                    <select
                        id="category"
                        v-model="form.category_id"
                        class="flex h-10 w-full rounded-md border border-input bg-background px-3 py-2 text-sm"
                    >
                        <option value="">{{ $t('finance.selectCategory') }}</option>
                        <option
                            v-for="cat in filteredCategories"
                            :key="cat.id"
                            :value="cat.id"
                        >
                            {{ cat.name }}
                        </option>
                    </select>
                </div>


                <!-- Account -->
                <div v-if="props.accounts.length > 0" class="space-y-2">
                    <Label for="account">{{ $t('finance.account') }}</Label>
                    <select
                        id="account"
                        v-model="form.account_id"
                        class="flex h-10 w-full rounded-md border border-input bg-background px-3 py-2 text-sm"
                    >
                        <option value="">{{ $t('finance.selectAccount') }}</option>
                        <option
                            v-for="acc in props.accounts"
                            :key="acc.id"
                            :value="acc.id"
                        >
                            {{ acc.name }}
                        </option>
                    </select>
                </div>

                <!-- Date -->
                <div class="space-y-2">
                    <Label for="date">{{ $t('finance.date') }}</Label>
                    <Input
                        id="date"
                        v-model="form.occurred_at"
                        type="date"
                        required
                    />
                </div>

                <!-- Note -->
                <div class="space-y-2">
                    <Label for="note">{{ $t('finance.note') }}</Label>
                    <Input
                        id="note"
                        v-model="form.note"
                        type="text"
                        :placeholder="$t('finance.notePlaceholder')"
                    />
                </div>

                <DialogFooter>
                    <Button type="submit" :disabled="form.processing">
                        {{ $t('finance.save') }}
                    </Button>
                </DialogFooter>
            </form>
        </DialogContent>
    </Dialog>
</template>
