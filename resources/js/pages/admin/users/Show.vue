<script setup lang="ts">
import InputError from '@/components/InputError.vue';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select';
import AdminLayout from '@/layouts/AdminLayout.vue';
import admin from '@/routes/admin';
import type { BreadcrumbItem, UserWithProfile } from '@/types';
import { Head, router, useForm } from '@inertiajs/vue3';
import { ArrowLeft, Key, Save, Trash2, UserX } from 'lucide-vue-next';

const props = defineProps<{
    user: UserWithProfile;
    stats: {
        total_messages: number;
        total_transactions: number;
        total_schedules: number;
        recent_activities: Array<{
            id: number;
            action: string;
            description: string | null;
            created_at: string;
        }>;
    };
}>();

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Admin', href: admin.index().url },
    { title: 'Users', href: admin.users.index().url },
    { title: props.user.name },
];

const form = useForm({
    name: props.user.name,
    email: props.user.email,
    role: props.user.role,
    is_active: props.user.is_active,
});

const submit = () => {
    form.put(admin.users.update({ user: props.user.id }).url);
};

const toggleActive = () => {
    router.post(admin.users.toggleActive({ user: props.user.id }).url, {}, { preserveScroll: true });
};

const resetPassword = () => {
    if (confirm("Are you sure you want to reset this user's password?")) {
        router.post(admin.users.resetPassword({ user: props.user.id }).url, {}, { preserveScroll: true });
    }
};

const deleteUser = () => {
    if (confirm('Are you sure you want to delete this user? This action cannot be undone.')) {
        router.delete(admin.users.destroy({ user: props.user.id }).url);
    }
};

const roleColors: Record<string, string> = {
    user: 'default',
    admin: 'secondary',
    super_admin: 'destructive',
};
</script>

<template>
    <Head :title="`User: ${user.name}`" />

    <AdminLayout :breadcrumbs="breadcrumbs">
        <div class="flex h-full flex-1 flex-col gap-6 p-4 md:p-6">
            <!-- Header -->
            <div class="flex items-center justify-between">
                <div class="flex items-center gap-4">
                    <Button variant="outline" size="icon" as-child>
                        <a :href="admin.users.index().url">
                            <ArrowLeft class="h-4 w-4" />
                        </a>
                    </Button>
                    <div>
                        <h1 class="text-2xl font-bold tracking-tight">{{ user.name }}</h1>
                        <p class="text-muted-foreground">{{ user.email }}</p>
                    </div>
                </div>
                <Badge :variant="roleColors[user.role] as any" class="text-sm">
                    {{ user.role.replace('_', ' ') }}
                </Badge>
            </div>

            <div class="grid gap-6 lg:grid-cols-3">
                <!-- Edit Form -->
                <div class="lg:col-span-2">
                    <Card>
                        <CardHeader>
                            <CardTitle>User Details</CardTitle>
                            <CardDescription>Update user information and role</CardDescription>
                        </CardHeader>
                        <CardContent>
                            <form class="space-y-4" @submit.prevent="submit">
                                <div class="grid gap-4 md:grid-cols-2">
                                    <div class="space-y-2">
                                        <Label for="name">Name</Label>
                                        <Input id="name" v-model="form.name" />
                                        <InputError :message="form.errors.name" />
                                    </div>
                                    <div class="space-y-2">
                                        <Label for="email">Email</Label>
                                        <Input id="email" v-model="form.email" type="email" />
                                        <InputError :message="form.errors.email" />
                                    </div>
                                </div>

                                <div class="grid gap-4 md:grid-cols-2">
                                    <div class="space-y-2">
                                        <Label for="role">Role</Label>
                                        <Select v-model="form.role">
                                            <SelectTrigger>
                                                <SelectValue />
                                            </SelectTrigger>
                                            <SelectContent>
                                                <SelectItem value="user">User</SelectItem>
                                                <SelectItem value="admin">Admin</SelectItem>
                                                <SelectItem value="super_admin">Super Admin</SelectItem>
                                            </SelectContent>
                                        </Select>
                                        <InputError :message="form.errors.role" />
                                    </div>
                                    <div class="space-y-2">
                                        <Label>Status</Label>
                                        <div class="flex items-center gap-2">
                                            <Badge :variant="form.is_active ? 'default' : 'secondary'">
                                                {{ form.is_active ? 'Active' : 'Inactive' }}
                                            </Badge>
                                        </div>
                                    </div>
                                </div>

                                <div class="flex justify-end">
                                    <Button type="submit" :disabled="form.processing">
                                        <Save class="mr-2 h-4 w-4" />
                                        Save Changes
                                    </Button>
                                </div>
                            </form>
                        </CardContent>
                    </Card>

                    <!-- Actions -->
                    <Card class="mt-6">
                        <CardHeader>
                            <CardTitle>Account Actions</CardTitle>
                            <CardDescription>Manage user account status and security</CardDescription>
                        </CardHeader>
                        <CardContent>
                            <div class="flex flex-wrap gap-2">
                                <Button variant="outline" @click="toggleActive">
                                    <UserX class="mr-2 h-4 w-4" />
                                    {{ user.is_active ? 'Deactivate' : 'Activate' }} Account
                                </Button>
                                <Button variant="outline" @click="resetPassword">
                                    <Key class="mr-2 h-4 w-4" />
                                    Reset Password
                                </Button>
                                <Button variant="destructive" @click="deleteUser">
                                    <Trash2 class="mr-2 h-4 w-4" />
                                    Delete User
                                </Button>
                            </div>
                        </CardContent>
                    </Card>
                </div>

                <!-- Stats Sidebar -->
                <div class="space-y-6">
                    <Card>
                        <CardHeader>
                            <CardTitle>Usage Statistics</CardTitle>
                        </CardHeader>
                        <CardContent>
                            <div class="space-y-4">
                                <div class="flex justify-between">
                                    <span class="text-muted-foreground">Messages</span>
                                    <span class="font-medium">{{ stats.total_messages }}</span>
                                </div>
                                <div class="flex justify-between">
                                    <span class="text-muted-foreground">Transactions</span>
                                    <span class="font-medium">{{ stats.total_transactions }}</span>
                                </div>
                                <div class="flex justify-between">
                                    <span class="text-muted-foreground">Schedules</span>
                                    <span class="font-medium">{{ stats.total_schedules }}</span>
                                </div>
                            </div>
                        </CardContent>
                    </Card>

                    <Card>
                        <CardHeader>
                            <CardTitle>Account Info</CardTitle>
                        </CardHeader>
                        <CardContent>
                            <div class="space-y-4 text-sm">
                                <div>
                                    <span class="text-muted-foreground">Created</span>
                                    <p class="font-medium">{{ new Date(user.created_at).toLocaleString() }}</p>
                                </div>
                                <div>
                                    <span class="text-muted-foreground">Last Updated</span>
                                    <p class="font-medium">{{ new Date(user.updated_at).toLocaleString() }}</p>
                                </div>
                                <div v-if="user.profile?.aspri_name">
                                    <span class="text-muted-foreground">ASPRI Name</span>
                                    <p class="font-medium">{{ user.profile.aspri_name }}</p>
                                </div>
                            </div>
                        </CardContent>
                    </Card>

                    <Card v-if="stats.recent_activities.length > 0">
                        <CardHeader>
                            <CardTitle>Recent Activity</CardTitle>
                        </CardHeader>
                        <CardContent>
                            <div class="space-y-3">
                                <div v-for="activity in stats.recent_activities" :key="activity.id" class="text-sm">
                                    <p class="font-medium">{{ activity.action }}</p>
                                    <p class="text-xs text-muted-foreground">
                                        {{ new Date(activity.created_at).toLocaleString() }}
                                    </p>
                                </div>
                            </div>
                        </CardContent>
                    </Card>
                </div>
            </div>
        </div>
    </AdminLayout>
</template>
