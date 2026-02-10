<script setup lang="ts">
import AppContent from '@/components/AppContent.vue';
import AppShell from '@/components/AppShell.vue';
import AppSidebarHeader from '@/components/AppSidebarHeader.vue';
import NavUser from '@/components/NavUser.vue';
import {
    Sidebar,
    SidebarContent,
    SidebarFooter,
    SidebarGroup,
    SidebarGroupLabel,
    SidebarHeader,
    SidebarMenu,
    SidebarMenuButton,
    SidebarMenuItem,
} from '@/components/ui/sidebar';
import { useCurrentUrl } from '@/composables/useCurrentUrl';
import { dashboard } from '@/routes';
import admin from '@/routes/admin';
import { type BreadcrumbItem, type NavItem } from '@/types';
import { Link } from '@inertiajs/vue3';
import { Activity, ArrowLeft, CreditCard, Database, FileText, LayoutDashboard, Settings, Users } from 'lucide-vue-next';

type Props = {
    breadcrumbs?: BreadcrumbItem[];
};

withDefaults(defineProps<Props>(), {
    breadcrumbs: () => [],
});

const { isCurrentUrl } = useCurrentUrl();

const adminNavItems: NavItem[] = [
    {
        title: 'Dashboard',
        href: admin.index(),
        icon: LayoutDashboard,
    },
    {
        title: 'Users',
        href: admin.users.index(),
        icon: Users,
    },
    {
        title: 'Payments',
        href: admin.payments.index(),
        icon: CreditCard,
    },
    {
        title: 'Queue Monitor',
        href: admin.queues.index(),
        icon: Database,
    },
    {
        title: 'Activity Logs',
        href: admin.activity.index(),
        icon: Activity,
    },
    {
        title: 'Settings',
        href: admin.settings.index(),
        icon: Settings,
    },
];
</script>

<template>
    <AppShell variant="sidebar">
        <Sidebar collapsible="icon" variant="inset">
            <SidebarHeader>
                <SidebarMenu>
                    <SidebarMenuItem>
                        <SidebarMenuButton size="lg" as-child>
                            <Link :href="admin.index()">
                                <div class="bg-primary text-primary-foreground flex aspect-square size-8 items-center justify-center rounded-md">
                                    <FileText class="size-4" />
                                </div>
                                <div class="flex flex-col gap-0.5 leading-none">
                                    <span class="font-bold">Admin Panel</span>
                                    <span class="text-xs text-muted-foreground">ASPRI</span>
                                </div>
                            </Link>
                        </SidebarMenuButton>
                    </SidebarMenuItem>
                </SidebarMenu>
            </SidebarHeader>

            <SidebarContent>
                <SidebarGroup class="px-2 py-0">
                    <SidebarGroupLabel>Administration</SidebarGroupLabel>
                    <SidebarMenu>
                        <SidebarMenuItem v-for="item in adminNavItems" :key="item.title">
                            <SidebarMenuButton as-child :is-active="isCurrentUrl(item.href)" :tooltip="item.title">
                                <Link :href="item.href">
                                    <component :is="item.icon" />
                                    <span>{{ item.title }}</span>
                                </Link>
                            </SidebarMenuButton>
                        </SidebarMenuItem>
                    </SidebarMenu>
                </SidebarGroup>

                <SidebarGroup class="px-2 py-0">
                    <SidebarGroupLabel>User Panel</SidebarGroupLabel>
                    <SidebarMenu>
                        <SidebarMenuItem>
                            <SidebarMenuButton as-child :is-active="isCurrentUrl(dashboard())" :tooltip="'User Dashboard'">
                                <Link :href="dashboard()">
                                    <ArrowLeft class="size-4" />
                                    <span>Kembali ke Panel</span>
                                </Link>
                            </SidebarMenuButton>
                        </SidebarMenuItem>
                    </SidebarMenu>
                </SidebarGroup>
            </SidebarContent>

            <SidebarFooter>
                <NavUser />
            </SidebarFooter>
        </Sidebar>

        <AppContent variant="sidebar" class="overflow-x-hidden">
            <AppSidebarHeader :breadcrumbs="breadcrumbs" />
            <slot />
        </AppContent>
    </AppShell>
</template>
