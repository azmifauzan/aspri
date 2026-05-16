<script setup lang="ts">
import { Button } from '@/components/ui/button';
import { Badge } from '@/components/ui/badge';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import {
    Dialog,
    DialogContent,
    DialogFooter,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';
import AppLayout from '@/layouts/AppLayout.vue';
import { finance } from '@/routes';
import { index as budgetsIndex, store, update, destroy } from '@/routes/budgets';
import type { BreadcrumbItem, FinanceBudget, FinanceBudgetsProps } from '@/types';
import { Head, router, useForm } from '@inertiajs/vue3';
import { AlertTriangle, CheckCircle2, Pencil, Plus, Trash2 } from 'lucide-vue-next';
import { computed, ref } from 'vue';
import { useI18n } from 'vue-i18n';
import Swal from 'sweetalert2';

const { locale } = useI18n();

const props = defineProps<FinanceBudgetsProps>();

const breadcrumbs = computed<BreadcrumbItem[]>(() => [
    { title: 'Finance', href: finance().url },
    { title: 'Budget' },
]);

const showCreateDialog = ref(false);
const editingBudget = ref<FinanceBudget | null>(null);

const MONTHS = [
    'Januari', 'Februari', 'Maret', 'April', 'Mei', 'Juni',
    'Juli', 'Agustus', 'September', 'Oktober', 'November', 'Desember',
];
const periodLabel = computed(() => `${MONTHS[props.period.month - 1]} ${props.period.year}`);

const formatCurrency = (amount: number) =>
    new Intl.NumberFormat(locale.value === 'id' ? 'id-ID' : 'en-US', {
        style: 'currency',
        currency: 'IDR',
        minimumFractionDigits: 0,
        maximumFractionDigits: 0,
    }).format(amount);

const createForm = useForm({
    category_id: null as string | null,
    period_year: props.period.year,
    period_month: props.period.month,
    amount: '',
    alert_threshold_pct: 80,
});

const editForm = useForm({
    category_id: null as string | null,
    amount: '',
    alert_threshold_pct: 80,
});

const openEdit = (budget: FinanceBudget) => {
    editingBudget.value = budget;
    editForm.category_id = budget.category_id;
    editForm.amount = String(budget.amount);
    editForm.alert_threshold_pct = budget.alert_threshold_pct;
};

const submitCreate = () => {
    createForm.post(store().url, {
        onSuccess: () => {
            showCreateDialog.value = false;
            createForm.reset();
        },
    });
};

const submitEdit = () => {
    if (!editingBudget.value) return;
    editForm.put(update({ financeBudget: editingBudget.value.id }).url, {
        onSuccess: () => { editingBudget.value = null; },
    });
};

const deleteBudget = (budget: FinanceBudget) => {
    Swal.fire({
        title: 'Hapus budget?',
        text: `Budget "${budget.category?.name ?? 'Semua Pengeluaran'}" akan dihapus.`,
        icon: 'warning',
        showCancelButton: true,
        confirmButtonColor: '#d33',
        cancelButtonColor: '#6b7280',
        confirmButtonText: 'Ya, Hapus!',
        cancelButtonText: 'Batal',
    }).then((result) => {
        if (result.isConfirmed) {
            router.delete(destroy({ financeBudget: budget.id }).url, { preserveScroll: true });
        }
    });
};

const changePeriod = (delta: number) => {
    const d = new Date(props.period.year, props.period.month - 1 + delta, 1);
    router.get(budgetsIndex({ year: d.getFullYear(), month: d.getMonth() + 1 }).url, {}, { preserveScroll: true });
};

const barColor = (budget: FinanceBudget) => {
    if (budget.is_over) return 'bg-red-500';
    if (budget.is_approaching) return 'bg-amber-400';
    return 'bg-emerald-500';
};
</script>

<template>
    <Head title="Budget" />

    <AppLayout :breadcrumbs="breadcrumbs">
        <div class="flex h-full flex-1 flex-col gap-4 p-4">
            <!-- Header -->
            <div class="flex items-center justify-between">
                <div class="flex items-center gap-3">
                    <Button variant="outline" size="icon" @click="changePeriod(-1)">‹</Button>
                    <h1 class="text-lg font-semibold">{{ periodLabel }}</h1>
                    <Button variant="outline" size="icon" @click="changePeriod(1)">›</Button>
                </div>
                <Button @click="showCreateDialog = true">
                    <Plus :size="16" class="mr-2" />
                    Tambah Budget
                </Button>
            </div>

            <!-- Budget list -->
            <div v-if="budgets.length === 0" class="flex flex-col items-center justify-center py-20 text-center">
                <p class="text-muted-foreground">Belum ada budget untuk {{ periodLabel }}.</p>
                <Button class="mt-4" @click="showCreateDialog = true">Buat Budget Pertama</Button>
            </div>

            <div v-else class="grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
                <Card v-for="budget in budgets" :key="budget.id">
                    <CardHeader class="flex flex-row items-start justify-between pb-2">
                        <div>
                            <CardTitle class="text-sm font-semibold">
                                {{ budget.category?.name ?? 'Semua Pengeluaran' }}
                            </CardTitle>
                            <Badge
                                :variant="budget.is_over ? 'destructive' : budget.is_approaching ? 'secondary' : 'outline'"
                                class="mt-1 text-xs"
                            >
                                <AlertTriangle v-if="budget.is_over || budget.is_approaching" :size="11" class="mr-1" />
                                <CheckCircle2 v-else :size="11" class="mr-1" />
                                {{ budget.used_pct.toFixed(1) }}%
                            </Badge>
                        </div>
                        <div class="flex gap-1">
                            <Button variant="ghost" size="icon" class="h-7 w-7" @click="openEdit(budget)">
                                <Pencil :size="13" />
                            </Button>
                            <Button variant="ghost" size="icon" class="h-7 w-7 text-destructive" @click="deleteBudget(budget)">
                                <Trash2 :size="13" />
                            </Button>
                        </div>
                    </CardHeader>
                    <CardContent class="space-y-2">
                        <div class="h-2 w-full rounded-full bg-muted">
                            <div
                                class="h-2 rounded-full transition-all"
                                :class="barColor(budget)"
                                :style="{ width: `${Math.min(budget.used_pct, 100)}%` }"
                            />
                        </div>
                        <div class="flex justify-between text-xs text-muted-foreground">
                            <span>{{ formatCurrency(budget.spent) }} terpakai</span>
                            <span>Limit: {{ formatCurrency(budget.amount) }}</span>
                        </div>
                        <p v-if="budget.is_over" class="text-xs text-red-500 font-medium">
                            Melebihi budget {{ formatCurrency(Math.abs(budget.remaining)) }}
                        </p>
                        <p v-else class="text-xs text-muted-foreground">
                            Sisa: {{ formatCurrency(budget.remaining) }}
                        </p>
                    </CardContent>
                </Card>
            </div>
        </div>

        <!-- Create dialog -->
        <Dialog :open="showCreateDialog" @update:open="(v) => !v && (showCreateDialog = false)">
            <DialogContent class="sm:max-w-md">
                <DialogHeader>
                    <DialogTitle>Tambah Budget</DialogTitle>
                </DialogHeader>
                <div class="space-y-4 py-2">
                    <div class="space-y-2">
                        <Label>Kategori (kosong = semua pengeluaran)</Label>
                        <Select v-model="createForm.category_id">
                            <SelectTrigger>
                                <SelectValue placeholder="Semua Pengeluaran" />
                            </SelectTrigger>
                            <SelectContent>
                                <SelectItem :value="null">Semua Pengeluaran</SelectItem>
                                <SelectItem v-for="cat in categories" :key="cat.id" :value="cat.id">
                                    {{ cat.name }}
                                </SelectItem>
                            </SelectContent>
                        </Select>
                    </div>
                    <div class="grid grid-cols-2 gap-3">
                        <div class="space-y-2">
                            <Label>Tahun</Label>
                            <Input v-model.number="createForm.period_year" type="number" min="2020" max="2100" />
                        </div>
                        <div class="space-y-2">
                            <Label>Bulan</Label>
                            <Select v-model="createForm.period_month">
                                <SelectTrigger>
                                    <SelectValue />
                                </SelectTrigger>
                                <SelectContent>
                                    <SelectItem v-for="(name, i) in MONTHS" :key="i" :value="i + 1">{{ name }}</SelectItem>
                                </SelectContent>
                            </Select>
                        </div>
                    </div>
                    <div class="space-y-2">
                        <Label>Limit (Rp)</Label>
                        <Input v-model.number="createForm.amount" type="number" min="0" placeholder="2000000" />
                    </div>
                    <div class="space-y-2">
                        <Label>Alert ketika mencapai (%) — default 80</Label>
                        <Input v-model.number="createForm.alert_threshold_pct" type="number" min="1" max="100" />
                    </div>
                </div>
                <DialogFooter>
                    <Button variant="outline" @click="showCreateDialog = false">Batal</Button>
                    <Button :disabled="createForm.processing" @click="submitCreate">Simpan</Button>
                </DialogFooter>
            </DialogContent>
        </Dialog>

        <!-- Edit dialog -->
        <Dialog :open="!!editingBudget" @update:open="(v) => !v && (editingBudget = null)">
            <DialogContent class="sm:max-w-md">
                <DialogHeader>
                    <DialogTitle>Edit Budget</DialogTitle>
                </DialogHeader>
                <div class="space-y-4 py-2">
                    <div class="space-y-2">
                        <Label>Limit (Rp)</Label>
                        <Input v-model.number="editForm.amount" type="number" min="0" />
                    </div>
                    <div class="space-y-2">
                        <Label>Alert ketika mencapai (%)</Label>
                        <Input v-model.number="editForm.alert_threshold_pct" type="number" min="1" max="100" />
                    </div>
                </div>
                <DialogFooter>
                    <Button variant="outline" @click="editingBudget = null">Batal</Button>
                    <Button :disabled="editForm.processing" @click="submitEdit">Simpan</Button>
                </DialogFooter>
            </DialogContent>
        </Dialog>
    </AppLayout>
</template>
