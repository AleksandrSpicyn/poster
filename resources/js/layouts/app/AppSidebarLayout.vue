<script setup lang="ts">
import AppContent from '@/components/AppContent.vue';
import AppFeedback from '@/components/AppFeedback.vue';
import AppShell from '@/components/AppShell.vue';
import AppSidebar from '@/components/AppSidebar.vue';
import AppSidebarHeader from '@/components/AppSidebarHeader.vue';
import StudioMobileNav from '@/components/StudioMobileNav.vue';
import WorkspaceLiveStatus from '@/components/WorkspaceLiveStatus.vue';
import type { BreadcrumbItemType } from '@/types';

interface Props {
    breadcrumbs?: BreadcrumbItemType[];
}

withDefaults(defineProps<Props>(), {
    breadcrumbs: () => [],
});
</script>

<template>
    <AppShell variant="sidebar">
        <AppSidebar />
        <AppContent variant="sidebar" class="min-w-0 pb-[calc(66px+env(safe-area-inset-bottom))] md:pb-0">
            <AppSidebarHeader :breadcrumbs="breadcrumbs" />
            <AppFeedback />
            <WorkspaceLiveStatus />
            <slot />
        </AppContent>
        <StudioMobileNav />
    </AppShell>
</template>
