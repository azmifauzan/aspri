<script setup lang="ts">
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent } from '@/components/ui/card';
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
import {
    Select,
    SelectContent,
    SelectGroup,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';
import {
    Table,
    TableBody,
    TableCell,
    TableHead,
    TableHeader,
    TableRow,
} from '@/components/ui/table';
import { Textarea } from '@/components/ui/textarea';
import AdminLayout from '@/layouts/AdminLayout.vue';
import admin from '@/routes/admin';
import type { BreadcrumbItem } from '@/types';
import type { PromoCodesPageProps } from '@/types/admin';
import { Head, router, useForm } from '@inertiajs/vue3';
import {
    ChevronLeft,
    ChevronRight,
    Copy,
    Eye,
    Pencil,
    Plus,
    Search,
    Tag,
    ToggleLeft,
    ToggleRight,
    Trash2,
} from 'lucide-vue-next';
import { ref, watch } from 'vue';
import Swal from 'sweetalert2';

const props = defineProps<PromoCodesPageProps>();

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Admin', href: '/admin' },
    { title: 'Kode Promo', href: '/admin/promo-codes' },
];

const searchQuery = ref(props.filters.search);
const statusFilter = ref(props.filters.status || 'all');

const showCreateDialog = ref(false);
const showEditDialog = ref(false);
const editingPromo = ref<typeof props.promoCodes.data[0] | null>(null);

const createForm = useForm({
    code: '',
    description: '',
    duration_days: 30,
    max_usages: 100,
    expires_at: '',
});

const editForm = useForm({
    description: '',
    duration_days: 30,
    max_usages: 100,
    is_active: true,
    expires_at: '',
});

const formatDate = (dateStr: string) => {
    return new Date(dateStr).toLocaleDateString('id-ID', {
        day: 'numeric',
        month: 'short',
        year: 'numeric',
        hour: '2-digit',
        minute: '2-digit',
    });
};

const formatDateShort = (dateStr: string) => {
    return new Date(dateStr).toLocaleDateString('id-ID', {
        day: 'numeric',
        month: 'short',
        year: 'numeric',
    });
};

const getStatusInfo = (promo: typeof props.promoCodes.data[0]) => {
    if (!promo.is_active) {
        return { variant: 'secondary' as const, label: 'Nonaktif' };
    }
    if (new Date(promo.expires_at) < new Date()) {
        return { variant: 'destructive' as const, label: 'Kadaluarsa' };
    }
    if (promo.usage_count >= promo.max_usages) {
        return { variant: 'outline' as const, label: 'Habis' };
    }
    return { variant: 'default' as const, label: 'Aktif' };
};

const applyFilters = () => {
    router.get(admin.promoCodes.index().url, {
        search: searchQuery.value || undefined,
        status: statusFilter.value !== 'all' ? statusFilter.value : undefined,
    }, {
        preserveState: true,
        replace: true,
    });
};

watch([statusFilter], () => {
    applyFilters();
});

const handleSearch = () => {
    applyFilters();
};

const goToPage = (page: number) => {
    router.get(admin.promoCodes.index().url, {
        page,
        search: searchQuery.value || undefined,
        status: statusFilter.value !== 'all' ? statusFilter.value : undefined,
    }, {
        preserveState: true,
    });
};

const generateCode = () => {
    const chars = 'ABCDEFGHIJKLMNOPQRSTUVWXYZ0123456789';
    let code = 'ASPRI-';
    for (let i = 0; i < 6; i++) {
        code += chars.charAt(Math.floor(Math.random() * chars.length));
    }
    createForm.code = code;
};

const openCreateDialog = () => {
    createForm.reset();
    const tomorrow = new Date();
    tomorrow.setDate(tomorrow.getDate() + 30);
    createForm.expires_at = tomorrow.toISOString().split('T')[0];
    generateCode();
    showCreateDialog.value = true;
};

const submitCreate = () => {
    createForm.post(admin.promoCodes.store().url, {
        preserveScroll: true,
        onSuccess: () => {
            showCreateDialog.value = false;
            createForm.reset();
            Swal.fire('Berhasil!', 'Kode promo berhasil dibuat.', 'success');
        },
    });
};

const openEditDialog = (promo: typeof props.promoCodes.data[0]) => {
    editingPromo.value = promo;
    editForm.description = promo.description || '';
    editForm.duration_days = promo.duration_days;
    editForm.max_usages = promo.max_usages;
    editForm.is_active = promo.is_active;
    editForm.expires_at = new Date(promo.expires_at).toISOString().split('T')[0];
    showEditDialog.value = true;
};

const submitEdit = () => {
    if (!editingPromo.value) {
        return;
    }
    editForm.put(admin.promoCodes.update(editingPromo.value.id).url, {
        preserveScroll: true,
        onSuccess: () => {
            showEditDialog.value = false;
            editingPromo.value = null;
            Swal.fire('Berhasil!', 'Kode promo berhasil diperbarui.', 'success');
        },
    });
};

const toggleActive = (promo: typeof props.promoCodes.data[0]) => {
    const newStatus = promo.is_active ? 'menonaktifkan' : 'mengaktifkan';
    Swal.fire({
        title: `${promo.is_active ? 'Nonaktifkan' : 'Aktifkan'} Kode Promo?`,
        text: `Anda akan ${newStatus} kode promo ${promo.code}.`,
        icon: 'question',
        showCancelButton: true,
        confirmButtonText: 'Ya',
        cancelButtonText: 'Batal',
    }).then((result) => {
        if (result.isConfirmed) {
            router.post(admin.promoCodes.toggleActive(promo.id).url, {}, {
                preserveScroll: true,
            });
        }
    });
};

const deletePromo = (promo: typeof props.promoCodes.data[0]) => {
    Swal.fire({
        title: 'Hapus Kode Promo?',
        text: `Kode promo ${promo.code} akan dihapus permanen.`,
        icon: 'warning',
        showCancelButton: true,
        confirmButtonText: 'Ya, Hapus',
        cancelButtonText: 'Batal',
        confirmButtonColor: '#ef4444',
    }).then((result) => {
        if (result.isConfirmed) {
            router.delete(admin.promoCodes.destroy(promo.id).url, {
                preserveScroll: true,
                onSuccess: () => {
                    Swal.fire('Berhasil!', 'Kode promo berhasil dihapus.', 'success');
                },
            });
        }
    });
};

const copyCode = async (code: string) => {
    await navigator.clipboard.writeText(code);
    Swal.fire({
        icon: 'success',
        title: 'Tersalin!',
        text: `Kode ${code} berhasil disalin.`,
        timer: 1500,
        showConfirmButton: false,
    });
};
</script>

<template>
    <Head title="Kelola Kode Promo" />

    <AdminLayout :breadcrumbs="breadcrumbs">
        <div class="flex h-full flex-1 flex-col gap-6 p-4 md:p-6">
            <!-- Header -->
            <div class="flex flex-col gap-4 md:flex-row md:items-center md:justify-between">
                <div>
                    <h1 class="text-2xl font-semibold flex items-center gap-2">
                        <Tag class="h-6 w-6" />
                        Kelola Kode Promo
                    </h1>
                    <p class="text-muted-foreground">
                        Buat dan kelola kode promo untuk perpanjangan membership
                    </p>
                </div>
                <Button @click="openCreateDialog">
                    <Plus class="mr-2 h-4 w-4" />
                    Buat Kode Promo
                </Button>
            </div>

            <!-- Filters -->
            <div class="flex flex-col gap-4 md:flex-row">
                <div class="relative flex-1">
                    <Search class="absolute left-3 top-1/2 h-4 w-4 -translate-y-1/2 text-muted-foreground" />
                    <Input
                        v-model="searchQuery"
                        placeholder="Cari kode atau deskripsi..."
                        class="pl-10"
                        @keyup.enter="handleSearch"
                    />
                </div>
                <Select v-model="statusFilter">
                    <SelectTrigger class="w-full md:w-48">
                        <SelectValue placeholder="Status" />
                    </SelectTrigger>
                    <SelectContent>
                        <SelectGroup>
                            <SelectItem value="all">Semua Status</SelectItem>
                            <SelectItem value="active">Aktif</SelectItem>
                            <SelectItem value="expired">Kadaluarsa</SelectItem>
                            <SelectItem value="inactive">Nonaktif</SelectItem>
                        </SelectGroup>
                    </SelectContent>
                </Select>
            </div>

            <!-- Promo Codes Table -->
            <Card>
                <CardContent class="p-0">
                    <Table>
                        <TableHeader>
                            <TableRow>
                                <TableHead>Kode</TableHead>
                                <TableHead>Deskripsi</TableHead>
                                <TableHead>Durasi</TableHead>
                                <TableHead>Penggunaan</TableHead>
                                <TableHead>Kadaluarsa</TableHead>
                                <TableHead>Status</TableHead>
                                <TableHead class="text-right">Aksi</TableHead>
                            </TableRow>
                        </TableHeader>
                        <TableBody>
                            <TableRow v-if="promoCodes.data.length === 0">
                                <TableCell colspan="7" class="h-24 text-center text-muted-foreground">
                                    Belum ada kode promo
                                </TableCell>
                            </TableRow>
                            <TableRow v-for="promo in promoCodes.data" :key="promo.id">
                                <TableCell>
                                    <div class="flex items-center gap-2">
                                        <code class="rounded bg-muted px-2 py-1 text-sm font-mono font-semibold">
                                            {{ promo.code }}
                                        </code>
                                        <Button variant="ghost" size="sm" class="h-6 w-6 p-0" @click="copyCode(promo.code)">
                                            <Copy class="h-3 w-3" />
                                        </Button>
                                    </div>
                                </TableCell>
                                <TableCell>
                                    <span v-if="promo.description" class="text-sm">{{ promo.description }}</span>
                                    <span v-else class="text-sm text-muted-foreground">-</span>
                                </TableCell>
                                <TableCell>
                                    <span class="font-medium">{{ promo.duration_days }} hari</span>
                                </TableCell>
                                <TableCell>
                                    <span class="font-mono text-sm">
                                        {{ promo.usage_count }}/{{ promo.max_usages }}
                                    </span>
                                </TableCell>
                                <TableCell class="text-sm">
                                    {{ formatDateShort(promo.expires_at) }}
                                </TableCell>
                                <TableCell>
                                    <Badge :variant="getStatusInfo(promo).variant">
                                        {{ getStatusInfo(promo).label }}
                                    </Badge>
                                </TableCell>
                                <TableCell class="text-right">
                                    <div class="flex items-center justify-end gap-1">
                                        <Button
                                            v-if="promo.usage_count > 0"
                                            variant="ghost"
                                            size="sm"
                                            @click="router.visit(admin.promoCodes.show(promo.id).url)"
                                            title="Lihat Detail Penggunaan"
                                        >
                                            <Eye class="h-4 w-4" />
                                        </Button>
                                        <Button
                                            variant="ghost"
                                            size="sm"
                                            @click="openEditDialog(promo)"
                                        >
                                            <Pencil class="h-4 w-4" />
                                        </Button>
                                        <Button
                                            variant="ghost"
                                            size="sm"
                                            @click="toggleActive(promo)"
                                        >
                                            <ToggleRight v-if="promo.is_active" class="h-4 w-4 text-green-500" />
                                            <ToggleLeft v-else class="h-4 w-4 text-muted-foreground" />
                                        </Button>
                                        <Button
                                            v-if="promo.usage_count === 0"
                                            variant="ghost"
                                            size="sm"
                                            class="text-red-500 hover:text-red-600"
                                            @click="deletePromo(promo)"
                                        >
                                            <Trash2 class="h-4 w-4" />
                                        </Button>
                                    </div>
                                </TableCell>
                            </TableRow>
                        </TableBody>
                    </Table>
                </CardContent>
            </Card>

            <!-- Pagination -->
            <div v-if="promoCodes.meta.last_page > 1" class="flex items-center justify-between">
                <p class="text-sm text-muted-foreground">
                    Menampilkan {{ promoCodes.meta.from }} - {{ promoCodes.meta.to }} dari {{ promoCodes.meta.total }} data
                </p>
                <div class="flex items-center gap-2">
                    <Button
                        variant="outline"
                        size="sm"
                        :disabled="promoCodes.meta.current_page === 1"
                        @click="goToPage(promoCodes.meta.current_page - 1)"
                    >
                        <ChevronLeft class="h-4 w-4" />
                        Sebelumnya
                    </Button>
                    <span class="text-sm text-muted-foreground">
                        Halaman {{ promoCodes.meta.current_page }} dari {{ promoCodes.meta.last_page }}
                    </span>
                    <Button
                        variant="outline"
                        size="sm"
                        :disabled="promoCodes.meta.current_page === promoCodes.meta.last_page"
                        @click="goToPage(promoCodes.meta.current_page + 1)"
                    >
                        Selanjutnya
                        <ChevronRight class="h-4 w-4" />
                    </Button>
                </div>
            </div>
        </div>

        <!-- Create Dialog -->
        <Dialog v-model:open="showCreateDialog">
            <DialogContent class="sm:max-w-md">
                <DialogHeader>
                    <DialogTitle>Buat Kode Promo Baru</DialogTitle>
                    <DialogDescription>
                        Buat kode promo yang dapat digunakan user untuk memperpanjang membership.
                    </DialogDescription>
                </DialogHeader>
                <form class="space-y-4" @submit.prevent="submitCreate">
                    <div class="space-y-2">
                        <Label for="create-code">Kode Promo *</Label>
                        <div class="flex gap-2">
                            <Input
                                id="create-code"
                                v-model="createForm.code"
                                placeholder="ASPRI-XXXXX"
                                class="font-mono uppercase"
                                required
                            />
                            <Button type="button" variant="outline" size="sm" @click="generateCode">
                                Generate
                            </Button>
                        </div>
                        <p v-if="createForm.errors.code" class="text-sm text-red-500">{{ createForm.errors.code }}</p>
                    </div>

                    <div class="space-y-2">
                        <Label for="create-description">Deskripsi</Label>
                        <Textarea
                            id="create-description"
                            v-model="createForm.description"
                            placeholder="Deskripsi kode promo..."
                            rows="2"
                        />
                    </div>

                    <div class="grid grid-cols-2 gap-4">
                        <div class="space-y-2">
                            <Label for="create-duration">Durasi (hari) *</Label>
                            <Input
                                id="create-duration"
                                v-model.number="createForm.duration_days"
                                type="number"
                                min="1"
                                max="365"
                                required
                            />
                            <p v-if="createForm.errors.duration_days" class="text-sm text-red-500">{{ createForm.errors.duration_days }}</p>
                        </div>
                        <div class="space-y-2">
                            <Label for="create-max-usages">Maks. Penggunaan *</Label>
                            <Input
                                id="create-max-usages"
                                v-model.number="createForm.max_usages"
                                type="number"
                                min="1"
                                required
                            />
                            <p v-if="createForm.errors.max_usages" class="text-sm text-red-500">{{ createForm.errors.max_usages }}</p>
                        </div>
                    </div>

                    <div class="space-y-2">
                        <Label for="create-expires">Tanggal Kadaluarsa *</Label>
                        <Input
                            id="create-expires"
                            v-model="createForm.expires_at"
                            type="date"
                            required
                        />
                        <p v-if="createForm.errors.expires_at" class="text-sm text-red-500">{{ createForm.errors.expires_at }}</p>
                    </div>

                    <DialogFooter>
                        <Button type="button" variant="outline" @click="showCreateDialog = false">Batal</Button>
                        <Button type="submit" :disabled="createForm.processing">
                            {{ createForm.processing ? 'Menyimpan...' : 'Buat Kode Promo' }}
                        </Button>
                    </DialogFooter>
                </form>
            </DialogContent>
        </Dialog>

        <!-- Edit Dialog -->
        <Dialog v-model:open="showEditDialog">
            <DialogContent class="sm:max-w-md">
                <DialogHeader>
                    <DialogTitle>Edit Kode Promo</DialogTitle>
                    <DialogDescription v-if="editingPromo">
                        Edit kode promo <code class="font-mono font-bold">{{ editingPromo.code }}</code>
                    </DialogDescription>
                </DialogHeader>
                <form class="space-y-4" @submit.prevent="submitEdit">
                    <div class="space-y-2">
                        <Label for="edit-description">Deskripsi</Label>
                        <Textarea
                            id="edit-description"
                            v-model="editForm.description"
                            placeholder="Deskripsi kode promo..."
                            rows="2"
                        />
                    </div>

                    <div class="grid grid-cols-2 gap-4">
                        <div class="space-y-2">
                            <Label for="edit-duration">Durasi (hari) *</Label>
                            <Input
                                id="edit-duration"
                                v-model.number="editForm.duration_days"
                                type="number"
                                min="1"
                                max="365"
                                required
                            />
                            <p v-if="editForm.errors.duration_days" class="text-sm text-red-500">{{ editForm.errors.duration_days }}</p>
                        </div>
                        <div class="space-y-2">
                            <Label for="edit-max-usages">Maks. Penggunaan *</Label>
                            <Input
                                id="edit-max-usages"
                                v-model.number="editForm.max_usages"
                                type="number"
                                min="1"
                                required
                            />
                            <p v-if="editForm.errors.max_usages" class="text-sm text-red-500">{{ editForm.errors.max_usages }}</p>
                        </div>
                    </div>

                    <div class="space-y-2">
                        <Label for="edit-expires">Tanggal Kadaluarsa *</Label>
                        <Input
                            id="edit-expires"
                            v-model="editForm.expires_at"
                            type="date"
                            required
                        />
                        <p v-if="editForm.errors.expires_at" class="text-sm text-red-500">{{ editForm.errors.expires_at }}</p>
                    </div>

                    <DialogFooter>
                        <Button type="button" variant="outline" @click="showEditDialog = false">Batal</Button>
                        <Button type="submit" :disabled="editForm.processing">
                            {{ editForm.processing ? 'Menyimpan...' : 'Simpan Perubahan' }}
                        </Button>
                    </DialogFooter>
                </form>
            </DialogContent>
        </Dialog>
    </AdminLayout>
</template>
