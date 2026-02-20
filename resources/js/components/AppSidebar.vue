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
import { useI18n } from 'vue-i18n';
import AppLogo from './AppLogo.vue';

const { t } = useI18n();
const page = usePage();
const { isCurrentUrl } = useCurrentUrl();

const isAdmin = computed(() => {
    const role = page.props.auth?.user?.role;
    return role === 'admin' || role === 'super_admin';
});

const mainNavItems = computed<NavItem[]>(() => [
    {
        title: t('common.dashboard'),
        href: dashboard(),
        icon: LayoutGrid,
    },
    {
        title: t('common.navChat'),
        href: chatIndex(),
        icon: MessageSquare,
    },
    {
        title: t('common.navFinance'),
        href: finance(),
        icon: Wallet,
    },
    {
        title: t('common.navSchedule'),
        href: schedulesRoutes.index(),
        icon: Calendar,
    },
    {
        title: t('common.navNotes'),
        href: notesRoutes.index(),
        icon: StickyNote,
    },
    {
        title: t('common.navPlugin'),
        href: pluginsRoutes.index(),
        icon: Puzzle,
        label: 'Beta',
    },
]);
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
                <SidebarGroupLabel>{{ $t('common.administration') }}</SidebarGroupLabel>
                <SidebarMenu>
                    <SidebarMenuItem>
                        <SidebarMenuButton as-child :is-active="isCurrentUrl(adminRoutes.index())" :tooltip="$t('common.adminPanel')">
                            <Link :href="adminRoutes.index()">
                                <Settings />
                                <span>{{ $t('common.adminPanel') }}</span>
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

