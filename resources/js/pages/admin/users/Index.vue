<script setup lang="ts">
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select';
import AdminLayout from '@/layouts/AdminLayout.vue';
import admin from '@/routes/admin';
import type { BreadcrumbItem, UserManagementProps } from '@/types';
import { Head, Link, router, useForm } from '@inertiajs/vue3';
import { debounce } from 'lodash-es';
import { Check, ChevronLeft, ChevronRight, Edit, Key, Search, Trash2, UserX, X } from 'lucide-vue-next';
import { computed, ref, watch } from 'vue';
import Swal from 'sweetalert2';

const props = defineProps<UserManagementProps>();

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Admin', href: admin.index().url },
    { title: 'Users' },
];

const searchQuery = ref(props.filters.search || '');
const selectedRole = ref(props.filters.role || 'all');
const selectedStatus = ref(props.filters.is_active ?? 'all');

const debouncedSearch = debounce(() => {
    router.get(
        admin.users.index().url,
        {
            search: searchQuery.value || undefined,
            role: selectedRole.value !== 'all' ? selectedRole.value : undefined,
            is_active: selectedStatus.value !== 'all' ? selectedStatus.value : undefined,
        },
        { preserveState: true, replace: true },
    );
}, 300);

watch([searchQuery, selectedRole, selectedStatus], () => {
    debouncedSearch();
});

const toggleActive = (userId: number) => {
    router.post(admin.users.toggleActive({ user: userId }).url, {}, { preserveScroll: true });
};

const resetPassword = (userId: number) => {
    Swal.fire({
        title: 'Reset Password?',
        text: 'Password user akan direset. User akan menerima email dengan password baru.',
        icon: 'warning',
        showCancelButton: true,
        confirmButtonColor: '#3085d6',
        cancelButtonColor: '#6b7280',
        confirmButtonText: 'Ya, Reset!',
        cancelButtonText: 'Batal',
    }).then((result) => {
        if (result.isConfirmed) {
            router.post(admin.users.resetPassword({ user: userId }).url, {}, {
                preserveScroll: true,
                onSuccess: () => {
                    Swal.fire({
                        icon: 'success',
                        title: 'Password direset',
                        text: 'Password user berhasil direset.',
                        timer: 2000,
                        showConfirmButton: false,
                    });
                },
            });
        }
    });
};

const deleteUser = (userId: number) => {
    Swal.fire({
        title: 'Hapus User?',
        text: 'User ini akan dihapus permanen beserta seluruh datanya.',
        icon: 'warning',
        showCancelButton: true,
        confirmButtonColor: '#d33',
        cancelButtonColor: '#6b7280',
        confirmButtonText: 'Ya, Hapus!',
        cancelButtonText: 'Batal',
    }).then((result) => {
        if (result.isConfirmed) {
            router.delete(admin.users.destroy({ user: userId }).url, {
                preserveScroll: true,
                onSuccess: () => {
                    Swal.fire({
                        icon: 'success',
                        title: 'User dihapus',
                        text: 'User berhasil dihapus.',
                        timer: 2000,
                        showConfirmButton: false,
                    });
                },
            });
        }
    });
};

const roleColors: Record<string, string> = {
    user: 'default',
    admin: 'secondary',
    super_admin: 'destructive',
};
</script>

<template>
    <Head title="User Management" />

    <AdminLayout :breadcrumbs="breadcrumbs">
        <div class="flex h-full flex-1 flex-col gap-6 p-4 md:p-6">
            <div>
                <h1 class="text-2xl font-bold tracking-tight">User Management</h1>
                <p class="text-muted-foreground">Manage user accounts, roles, and permissions</p>
            </div>

            <!-- Filters -->
            <Card>
                <CardContent class="pt-6">
                    <div class="flex flex-col gap-4 md:flex-row md:items-center">
                        <div class="relative flex-1">
                            <Search class="absolute left-3 top-1/2 h-4 w-4 -translate-y-1/2 text-muted-foreground" />
                            <Input v-model="searchQuery" placeholder="Search users..." class="pl-10" />
                        </div>
                        <Select v-model="selectedRole">
                            <SelectTrigger class="w-full md:w-40">
                                <SelectValue placeholder="All Roles" />
                            </SelectTrigger>
                            <SelectContent>
                                <SelectItem value="all">All Roles</SelectItem>
                                <SelectItem value="user">User</SelectItem>
                                <SelectItem value="admin">Admin</SelectItem>
                                <SelectItem value="super_admin">Super Admin</SelectItem>
                            </SelectContent>
                        </Select>
                        <Select v-model="selectedStatus">
                            <SelectTrigger class="w-full md:w-40">
                                <SelectValue placeholder="All Status" />
                            </SelectTrigger>
                            <SelectContent>
                                <SelectItem value="all">All Status</SelectItem>
                                <SelectItem value="1">Active</SelectItem>
                                <SelectItem value="0">Inactive</SelectItem>
                            </SelectContent>
                        </Select>
                    </div>
                </CardContent>
            </Card>

            <!-- Users Table -->
            <Card>
                <CardHeader>
                    <CardTitle>Users ({{ users.total }})</CardTitle>
                    <CardDescription>A list of all users in the system</CardDescription>
                </CardHeader>
                <CardContent>
                    <div class="overflow-x-auto">
                        <table class="w-full">
                            <thead>
                                <tr class="border-b text-left">
                                    <th class="pb-3 font-medium">User</th>
                                    <th class="pb-3 font-medium">Role</th>
                                    <th class="pb-3 font-medium">Status</th>
                                    <th class="pb-3 font-medium">Joined</th>
                                    <th class="pb-3 font-medium text-right">Actions</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y">
                                <tr v-for="user in users.data" :key="user.id" class="group">
                                    <td class="py-4">
                                        <div class="flex items-center gap-3">
                                            <div class="flex h-10 w-10 items-center justify-center rounded-full bg-muted font-medium">
                                                {{ user.name.charAt(0).toUpperCase() }}
                                            </div>
                                            <div>
                                                <p class="font-medium">{{ user.name }}</p>
                                                <p class="text-sm text-muted-foreground">{{ user.email }}</p>
                                            </div>
                                        </div>
                                    </td>
                                    <td class="py-4">
                                        <Badge :variant="roleColors[user.role] as any">
                                            {{ user.role.replace('_', ' ') }}
                                        </Badge>
                                    </td>
                                    <td class="py-4">
                                        <Badge :variant="user.is_active ? 'default' : 'secondary'">
                                            <Check v-if="user.is_active" class="mr-1 h-3 w-3" />
                                            <X v-else class="mr-1 h-3 w-3" />
                                            {{ user.is_active ? 'Active' : 'Inactive' }}
                                        </Badge>
                                    </td>
                                    <td class="py-4 text-sm text-muted-foreground">
                                        {{ new Date(user.created_at).toLocaleDateString() }}
                                    </td>
                                    <td class="py-4">
                                        <div class="flex items-center justify-end gap-2 opacity-0 transition-opacity group-hover:opacity-100">
                                            <Button variant="ghost" size="icon-sm" as-child :title="'View ' + user.name">
                                                <Link :href="admin.users.show({ user: user.id }).url">
                                                    <Edit class="h-4 w-4" />
                                                </Link>
                                            </Button>
                                            <Button variant="ghost" size="icon-sm" title="Reset Password" @click="resetPassword(user.id)">
                                                <Key class="h-4 w-4" />
                                            </Button>
                                            <Button variant="ghost" size="icon-sm" :title="user.is_active ? 'Deactivate' : 'Activate'" @click="toggleActive(user.id)">
                                                <UserX class="h-4 w-4" />
                                            </Button>
                                            <Button variant="ghost" size="icon-sm" title="Delete User" class="text-destructive hover:text-destructive" @click="deleteUser(user.id)">
                                                <Trash2 class="h-4 w-4" />
                                            </Button>
                                        </div>
                                    </td>
                                </tr>
                                <tr v-if="users.data.length === 0">
                                    <td colspan="5" class="py-8 text-center text-muted-foreground">No users found</td>
                                </tr>
                            </tbody>
                        </table>
                    </div>

                    <!-- Pagination -->
                    <div v-if="users.last_page > 1" class="mt-4 flex items-center justify-between border-t pt-4">
                        <p class="text-sm text-muted-foreground">
                            Page {{ users.current_page }} of {{ users.last_page }}
                        </p>
                        <div class="flex items-center gap-2">
                            <Button
                                variant="outline"
                                size="sm"
                                :disabled="users.current_page === 1"
                                @click="router.get(users.links[0].url!)"
                            >
                                <ChevronLeft class="h-4 w-4" />
                                Previous
                            </Button>
                            <Button
                                variant="outline"
                                size="sm"
                                :disabled="users.current_page === users.last_page"
                                @click="router.get(users.links[users.links.length - 1].url!)"
                            >
                                Next
                                <ChevronRight class="h-4 w-4" />
                            </Button>
                        </div>
                    </div>
                </CardContent>
            </Card>
        </div>
    </AdminLayout>
</template>
