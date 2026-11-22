<script setup lang="ts">
import AppContent from '@/components/AppContent.vue';
import AppFeedback from '@/components/AppFeedback.vue';
import AppShell from '@/components/AppShell.vue';
import AppSidebar from '@/components/AppSidebar.vue';
import AppSidebarHeader from '@/components/AppSidebarHeader.vue';
import StudioMobileNav from '@/components/StudioMobileNav.vue';
import { useWorkspaceRealtime } from '@/composables/useWorkspaceRealtime';
import WorkspaceLiveStatus from '@/components/WorkspaceLiveStatus.vue';
import type { BreadcrumbItemType } from '@/types';

interface Props {
    breadcrumbs?: BreadcrumbItemType[];
}

const { connected, enabled, refresh } = useWorkspaceRealtime();

withDefaults(defineProps<Props>(), {
    breadcrumbs: () => [],
});
</script>

<template>
    <AppShell variant="sidebar">
        <AppSidebar><WorkspaceLiveStatus :connected="connected" :enabled="enabled" @refresh="refresh" /></AppSidebar>
        <AppContent variant="sidebar" class="min-w-0 pb-[calc(66px+env(safe-area-inset-bottom))] md:pb-0">
            <AppSidebarHeader :breadcrumbs="breadcrumbs" />
            <AppFeedback />
            <p
                v-if="enabled && !connected"
                class="text-muted-foreground mx-4 flex items-center justify-between gap-2 text-xs md:hidden"
                role="status"
            >
                Восстанавливаем связь<button class="min-h-11 underline" @click="refresh">Обновить</button>
            </p>
            <slot />
        </AppContent>
        <StudioMobileNav />
    </AppShell>
</template>
