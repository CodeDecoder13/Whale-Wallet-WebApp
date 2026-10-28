<script setup lang="ts">
import { usePoll } from '@inertiajs/vue3';
import AppContent from '@/components/AppContent.vue';
import AppShell from '@/components/AppShell.vue';
import AppSidebar from '@/components/AppSidebar.vue';
import AppSidebarHeader from '@/components/AppSidebarHeader.vue';
import ReleaseNoticeDialog from '@/components/ReleaseNoticeDialog.vue';
import WhaleChatPill from '@/components/WhaleChatPill.vue';
import { Toaster } from '@/components/ui/sonner';
import type { BreadcrumbItem } from '@/types';

type Props = {
    breadcrumbs?: BreadcrumbItem[];
};

// numbers stay live: every 10s re-fetch the page props in place (paused while the tab is hidden; forms and dialogs keep their state)
usePoll(10_000);

withDefaults(defineProps<Props>(), {
    breadcrumbs: () => [],
});
</script>

<template>
    <AppShell variant="sidebar">
        <AppSidebar />
        <AppContent variant="sidebar" class="min-w-0 overflow-x-clip">
            <AppSidebarHeader :breadcrumbs="breadcrumbs" />
            <slot />
        </AppContent>
        <WhaleChatPill />
        <ReleaseNoticeDialog />
        <Toaster />
    </AppShell>
</template>
