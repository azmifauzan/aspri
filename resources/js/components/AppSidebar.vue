<script setup lang="ts">
import NavMain from '@/components/NavMain.vue';
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
import { dashboard, finance } from '@/routes';
import adminRoutes from '@/routes/admin';
import { index as chatIndex } from '@/routes/chat';
import notesRoutes from '@/routes/notes';
import pluginsRoutes from '@/routes/plugins';
import schedulesRoutes from '@/routes/schedules';
import { type NavItem } from '@/types';
import { Link, usePage } from '@inertiajs/vue3';
import { Calendar, Crown, LayoutGrid, MessageSquare, Puzzle, Settings, StickyNote, Wallet } from 'lucide-vue-next';
import { computed } from 'vue';
import AppLogo from './AppLogo.vue';

const page = usePage();
const { isCurrentUrl } = useCurrentUrl();

const isAdmin = computed(() => {
    const role = page.props.auth?.user?.role;
    return role === 'admin' || role === 'super_admin';
});

const mainNavItems: NavItem[] = [
    {
        title: 'Dashboard',
        href: dashboard(),
        icon: LayoutGrid,
    },
    {
        title: 'Chat',
        href: chatIndex(),
        icon: MessageSquare,
    },
    {
        title: 'Keuangan',
        href: finance(),
        icon: Wallet,
    },
    {
        title: 'Jadwal',
        href: schedulesRoutes.index(),
        icon: Calendar,
    },
    {
        title: 'Notes',
        href: notesRoutes.index(),
        icon: StickyNote,
    },
    {
        title: 'Plugin',
        href: pluginsRoutes.index(),
        icon: Puzzle,
        label: 'Beta',
    },
];
</script>

<template>
    <Sidebar collapsible="icon" variant="inset">
        <SidebarHeader>
            <SidebarMenu>
                <SidebarMenuItem>
                    <SidebarMenuButton size="lg" as-child>
                        <Link :href="dashboard()">
                            <AppLogo />
                        </Link>
                    </SidebarMenuButton>
                </SidebarMenuItem>
            </SidebarMenu>
        </SidebarHeader>

        <SidebarContent>
            <NavMain :items="mainNavItems" />

            <!-- Admin Section -->
            <SidebarGroup v-if="isAdmin" class="px-2 py-0">
                <SidebarGroupLabel>Administration</SidebarGroupLabel>
                <SidebarMenu>
                    <SidebarMenuItem>
                        <SidebarMenuButton as-child :is-active="isCurrentUrl(adminRoutes.index())" tooltip="Admin Panel">
                            <Link :href="adminRoutes.index()">
                                <Settings />
                                <span>Admin Panel</span>
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
    <slot />
</template>

