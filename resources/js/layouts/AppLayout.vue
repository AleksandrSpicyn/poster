<script setup lang="ts">
import { SidebarInset, SidebarProvider } from '@/components/ui/sidebar';
import AppFeedback from '@/components/AppFeedback.vue';
import { usePage } from '@inertiajs/vue3';
import AppSidebar from '@/components/AppSidebar.vue';
import AppSidebarHeader from '@/components/AppSidebarHeader.vue';
import StudioMobileNav from '@/components/StudioMobileNav.vue';
import { useWorkspaceRealtime } from '@/composables/useWorkspaceRealtime';
import WorkspaceLiveStatus from '@/components/WorkspaceLiveStatus.vue';
import type { BreadcrumbItem, SharedData } from '@/types';

interface Props {
    breadcrumbs?: BreadcrumbItem[];
    seriesHeader?: boolean;
}

const sidebarOpen = usePage<SharedData>().props.sidebarOpen;
const { connected, enabled, refresh } = useWorkspaceRealtime();

withDefaults(defineProps<Props>(), {
    breadcrumbs: () => [],
});
</script>

<template>
    <SidebarProvider :default-open="sidebarOpen">
        <AppSidebar><WorkspaceLiveStatus :connected="connected" :enabled="enabled" @refresh="refresh" /></AppSidebar>
        <SidebarInset class="min-w-0 pb-[calc(66px+env(safe-area-inset-bottom))] min-[700px]:pb-0">
            <AppSidebarHeader :breadcrumbs="breadcrumbs" :series-header="seriesHeader" />
            <AppFeedback />
            <p
                v-if="enabled && !connected"
                class="text-muted-foreground mx-4 flex items-center justify-between gap-2 text-xs min-[700px]:hidden"
                role="status"
            >
                Восстанавливаем связь<button class="min-h-11 underline" @click="refresh">Обновить</button>
            </p>
            <slot />
        </SidebarInset>
        <StudioMobileNav />
    </SidebarProvider>
</template>

<style>
@media (max-width: 699px) {
    html:has(.mobile-nav) {
        scroll-padding-block-end: calc(66px + env(safe-area-inset-bottom));
    }
}
</style>
