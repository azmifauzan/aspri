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
import { categories as financeCategories } from '@/routes/finance';
import type { BreadcrumbItem, FinanceCategoriesProps, FinanceCategory } from '@/types';

import { Head, router, useForm } from '@inertiajs/vue3';
import { Pencil, Plus, Tag, Trash2, TrendingDown, TrendingUp } from 'lucide-vue-next';
import { computed, ref } from 'vue';
import { useI18n } from 'vue-i18n';
import Swal from 'sweetalert2';

const { t } = useI18n();

const props = defineProps<FinanceCategoriesProps>();

const breadcrumbs = computed<BreadcrumbItem[]>(() => [
    { title: t('finance.title'), href: finance().url },
    { title: t('finance.categories'), href: financeCategories().url },
]);

const showAddModal = ref(false);
const isEditing = ref(false);
const editingCategory = ref<FinanceCategory | null>(null);

const form = useForm({
    name: '',
    tx_type: 'expense' as 'income' | 'expense',
    icon: '',
    color: '#6b7280',
});

const expenseCategories = computed(() =>
    props.categories.filter((c) => c.tx_type === 'expense')
);

const incomeCategories = computed(() =>
    props.categories.filter((c) => c.tx_type === 'income')
);

const openAddModal = () => {
    isEditing.value = false;
    editingCategory.value = null;
    form.reset();
    form.defaults({
        name: '',
        tx_type: 'expense',
        icon: '',
        color: '#6b7280',
    });
    showAddModal.value = true;
};

const editCategory = (cat: FinanceCategory) => {
    isEditing.value = true;
    editingCategory.value = cat;
    form.name = cat.name;
    form.tx_type = cat.tx_type as 'income' | 'expense';
    form.color = cat.color || '#6b7280';
    showAddModal.value = true;
};

const submitCategory = () => {
    if (isEditing.value && editingCategory.value) {
        form.put(`/finance/categories/${editingCategory.value.id}`, {
            preserveScroll: true,
            onSuccess: () => {
                showAddModal.value = false;
                form.reset();
                isEditing.value = false;
                editingCategory.value = null;
                Swal.fire({
                    icon: 'success',
                    title: t('finance.categoryUpdated'),
                    text: t('finance.categoryUpdatedDesc'),
                    timer: 2000,
                    showConfirmButton: false,
                });
            },
            onError: () => {
                Swal.fire({
                    icon: 'error',
                    title: t('finance.categoryUpdateFailed'),
                    text: t('finance.categoryUpdateError'),
                });
            },
        });
    } else {
        form.post(financeCategories().url, {
            preserveScroll: true,
            onSuccess: () => {
                showAddModal.value = false;
                form.reset();
                Swal.fire({
                    icon: 'success',
                    title: t('finance.categoryCreated'),
                    text: t('finance.categoryCreatedDesc'),
                    timer: 2000,
                    showConfirmButton: false,
                });
            },
            onError: () => {
                Swal.fire({
                    icon: 'error',
                    title: t('finance.categoryCreateFailed'),
                    text: t('finance.categoryCreateError'),
                });
            },
        });
    }
};

const confirmDelete = (cat: FinanceCategory) => {
    Swal.fire({
        title: t('finance.deleteCategory'),
        text: t('finance.deleteCategoryDesc', { name: cat.name }),
        icon: 'warning',
        showCancelButton: true,
        confirmButtonColor: '#d33',
        cancelButtonColor: '#6b7280',
        confirmButtonText: t('finance.yesDelete'),
        cancelButtonText: t('common.cancel'),
    }).then((result) => {
        if (result.isConfirmed) {
            router.delete(`/finance/categories/${cat.id}`, {
                preserveScroll: true,
                onSuccess: () => {
                    Swal.fire({
                        icon: 'success',
                        title: t('finance.categoryDeleted'),
                        text: t('finance.categoryDeletedDesc'),
                        timer: 2000,
                        showConfirmButton: false,
                    });
                },
                onError: () => {
                    Swal.fire({
                        icon: 'error',
                        title: t('finance.categoryDeleteFailed'),
                        text: t('finance.categoryDeleteError'),
                    });
                },
            });
        }
    });
};

const colorOptions = [
    '#ef4444', '#f97316', '#eab308', '#84cc16', '#22c55e',
    '#14b8a6', '#06b6d4', '#3b82f6', '#8b5cf6', '#ec4899',
    '#6b7280',
];
</script>


<template>
    <Head :title="$t('finance.categoryTitle')" />

    <AppLayout :breadcrumbs="breadcrumbs">
        <div class="flex h-full flex-1 flex-col gap-4 p-4">
            <!-- Header -->
            <div class="flex items-center justify-between">
                <h1 class="text-2xl font-bold">{{ $t('finance.categories') }}</h1>
                
                <Button @click="openAddModal">
                    <Plus class="mr-2 h-4 w-4" />
                    {{ $t('finance.addCategory') }}
                </Button>

                <Dialog v-model:open="showAddModal">
                    <DialogContent class="sm:max-w-md">
                        <DialogHeader>
                            <DialogTitle>{{ isEditing ? $t('finance.editCategory') : $t('finance.addCategory') }}</DialogTitle>
                            <DialogDescription>
                                {{ isEditing ? $t('finance.changeCategoryDetails') : $t('finance.createCategoryDesc') }}
                            </DialogDescription>
                        </DialogHeader>
                        <form @submit.prevent="submitCategory" class="space-y-4">
                            <!-- Category Type -->
                            <div class="flex gap-2">
                                <Button
                                    type="button"
                                    :variant="form.tx_type === 'expense' ? 'default' : 'outline'"
                                    class="flex-1"
                                    @click="form.tx_type = 'expense'"
                                >
                                    <TrendingDown class="mr-2 h-4 w-4" />
                                    {{ $t('finance.expense') }}
                                </Button>
                                <Button
                                    type="button"
                                    :variant="form.tx_type === 'income' ? 'default' : 'outline'"
                                    class="flex-1"
                                    @click="form.tx_type = 'income'"
                                >
                                    <TrendingUp class="mr-2 h-4 w-4" />
                                    {{ $t('finance.income') }}
                                </Button>
                            </div>

                            <!-- Name -->
                            <div class="space-y-2">
                                <Label for="name">{{ $t('finance.categoryName') }}</Label>
                                <Input
                                    id="name"
                                    v-model="form.name"
                                    type="text"
                                    :placeholder="$t('finance.categoryNamePlaceholder')"
                                    required
                                />
                            </div>

                            <!-- Color -->
                            <div class="space-y-2">
                                <Label>{{ $t('finance.color') }}</Label>
                                <div class="flex flex-wrap gap-2">
                                    <button
                                        v-for="color in colorOptions"
                                        :key="color"
                                        type="button"
                                        class="h-8 w-8 rounded-full border-2 transition-transform hover:scale-110"
                                        :class="form.color === color ? 'border-foreground' : 'border-transparent'"
                                        :style="{ backgroundColor: color }"
                                        @click="form.color = color"
                                    />
                                </div>
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


            <!-- Expense Categories -->
            <Card>
                <CardHeader class="pb-2">
                    <CardTitle class="flex items-center gap-2 text-base font-medium">
                        <TrendingDown class="h-4 w-4 text-red-500" />
                        {{ $t('finance.expenseCategories') }}
                    </CardTitle>
                </CardHeader>
                <CardContent>
                    <div
                        v-if="expenseCategories.length === 0"
                        class="py-8 text-center text-muted-foreground"
                    >
                        {{ $t('finance.noExpenseCategories') }}
                    </div>
                    <div v-else class="grid gap-2 sm:grid-cols-2 lg:grid-cols-3">
                        <div
                            v-for="category in expenseCategories"
                            :key="category.id"
                            class="flex items-center gap-3 rounded-lg border border-border p-3"
                        >
                            <div
                                class="flex h-10 w-10 items-center justify-center rounded-full"
                                :style="{ backgroundColor: category.color + '20' }"
                            >
                                <Tag
                                    class="h-5 w-5"
                                    :style="{ color: category.color }"
                                />
                            </div>
                            <div class="flex-1">
                                <p class="font-medium">{{ category.name }}</p>
                                <p class="text-xs text-muted-foreground">
                                    {{ $t('finance.transactionCount', { count: category.transactions_count || 0 }) }}
                                </p>
                            </div>
                            <div class="flex items-center gap-1">
                                <Button
                                    variant="ghost"
                                    size="icon"
                                    class="h-8 w-8 text-muted-foreground hover:text-primary"
                                    @click="editCategory(category)"
                                >
                                    <Pencil class="h-4 w-4" />
                                </Button>
                                <Button
                                    variant="ghost"
                                    size="icon"
                                    class="h-8 w-8 text-muted-foreground hover:text-destructive"
                                    @click="confirmDelete(category)"
                                >
                                    <Trash2 class="h-4 w-4" />
                                </Button>
                            </div>

                        </div>
                    </div>
                </CardContent>
            </Card>

            <!-- Income Categories -->
            <Card>
                <CardHeader class="pb-2">
                    <CardTitle class="flex items-center gap-2 text-base font-medium">
                        <TrendingUp class="h-4 w-4 text-emerald-500" />
                        {{ $t('finance.incomeCategories') }}
                    </CardTitle>
                </CardHeader>
                <CardContent>
                    <div
                        v-if="incomeCategories.length === 0"
                        class="py-8 text-center text-muted-foreground"
                    >
                        {{ $t('finance.noIncomeCategories') }}
                    </div>
                    <div v-else class="grid gap-2 sm:grid-cols-2 lg:grid-cols-3">
                        <div
                            v-for="category in incomeCategories"
                            :key="category.id"
                            class="flex items-center gap-3 rounded-lg border border-border p-3"
                        >
                            <div
                                class="flex h-10 w-10 items-center justify-center rounded-full"
                                :style="{ backgroundColor: category.color + '20' }"
                            >
                                <Tag
                                    class="h-5 w-5"
                                    :style="{ color: category.color }"
                                />
                            </div>
                            <div class="flex-1">
                                <p class="font-medium">{{ category.name }}</p>
                                <p class="text-xs text-muted-foreground">
                                    {{ $t('finance.transactionCount', { count: category.transactions_count || 0 }) }}
                                </p>
                            </div>
                            <div class="flex items-center gap-1">
                                <Button
                                    variant="ghost"
                                    size="icon"
                                    class="h-8 w-8 text-muted-foreground hover:text-primary"
                                    @click="editCategory(category)"
                                >
                                    <Pencil class="h-4 w-4" />
                                </Button>
                                <Button
                                    variant="ghost"
                                    size="icon"
                                    class="h-8 w-8 text-muted-foreground hover:text-destructive"
                                    @click="confirmDelete(category)"
                                >
                                    <Trash2 class="h-4 w-4" />
                                </Button>
                            </div>

                        </div>
                    </div>
                </CardContent>
            </Card>
        </div>

    </AppLayout>

</template>
