<script setup lang="ts">
import { Link } from '@inertiajs/vue3';
import {
    CircleHelp,
    LayoutGrid,
    MessageCircle,
    PiggyBank,
    Receipt,
    Repeat,
    Sparkles,
    TrendingUp,
    Wallet,
} from '@lucide/vue';
import AppLogo from '@/components/AppLogo.vue';
import NavFooter from '@/components/NavFooter.vue';
import NavMain from '@/components/NavMain.vue';
import NavUser from '@/components/NavUser.vue';
import {
    Sidebar,
    SidebarContent,
    SidebarFooter,
    SidebarHeader,
    SidebarMenu,
    SidebarMenuButton,
    SidebarMenuItem,
} from '@/components/ui/sidebar';
import { dashboard, forecast } from '@/routes';
import { index as cards } from '@/routes/cards';
import { index as chat } from '@/routes/chat';
import { index as pods } from '@/routes/pods';
import { index as subscriptions } from '@/routes/subscriptions';
import { index as tips } from '@/routes/tips';
import { index as transactions } from '@/routes/transactions';
import type { NavItem } from '@/types';

const mainNavItems: NavItem[] = [
    { title: 'Dashboard', href: dashboard(), icon: LayoutGrid },
    { title: 'Cards', href: cards(), icon: Wallet },
    { title: 'Pods', href: pods(), icon: PiggyBank },
    { title: 'Transactions', href: transactions(), icon: Receipt },
    { title: 'Forecast', href: forecast(), icon: TrendingUp },
    { title: 'Bills & Subs', href: subscriptions(), icon: Repeat },
    { title: 'Tips', href: tips(), icon: Sparkles },
    { title: 'Whale Chat', href: chat(), icon: MessageCircle },
];

const footerNavItems: NavItem[] = [
    {
        title: 'Need help?',
        href: 'mailto:help@whalewallet.app',
        icon: CircleHelp,
    },
];
</script>

<template>
    <Sidebar collapsible="offcanvas">
        <SidebarHeader class="gap-4 p-4">
            <SidebarMenu>
                <SidebarMenuItem>
                    <SidebarMenuButton size="lg" as-child>
                        <Link :href="dashboard()">
                            <AppLogo tagline />
                        </Link>
                    </SidebarMenuButton>
                </SidebarMenuItem>
            </SidebarMenu>
        </SidebarHeader>

        <SidebarContent>
            <NavMain :items="mainNavItems" />
        </SidebarContent>

        <SidebarFooter class="p-4">
            <NavFooter :items="footerNavItems" />
            <NavUser />
        </SidebarFooter>
    </Sidebar>
    <slot />
</template>
