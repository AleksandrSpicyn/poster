<script setup lang="ts">
import AppLogo from '@/components/AppLogo.vue';
import Breadcrumbs from '@/components/Breadcrumbs.vue';
import { SidebarTrigger, useSidebar } from '@/components/ui/sidebar';
import type { BreadcrumbItemType } from '@/types';
import { Link } from '@inertiajs/vue3';
import { useRoute } from 'ziggy-js';

withDefaults(defineProps<{ breadcrumbs?: BreadcrumbItemType[] }>(), { breadcrumbs: () => [] });
const route = useRoute();
const { setOpenMobile } = useSidebar();
</script>
<template>
    <header class="studio-top">
        <Link :href="route('dashboard')" class="md:hidden" aria-label="Poster — обзор студии"><AppLogo /></Link>
        <button class="text-muted-foreground ml-auto min-h-11 text-sm md:hidden" @click="setOpenMobile(true)">Моя студия</button>
        <div class="hidden min-w-0 flex-1 items-center gap-4 md:flex">
            <Link class="text-muted-foreground shrink-0 text-sm" :href="route('dashboard')">Моя студия</Link>
            <span aria-hidden="true" class="text-muted-foreground">/</span><Breadcrumbs v-if="breadcrumbs.length" :breadcrumbs="breadcrumbs" />
            <SidebarTrigger class="ml-auto size-11" />
        </div>
    </header>
</template>
<style scoped>
.studio-top {
    height: 66px;
    width: var(--studio-content-width);
    max-width: var(--studio-content-max);
    margin-inline: auto;
    display: flex;
    align-items: center;
    border-bottom: 1px solid var(--border);
}
@media (max-width: 767px) {
    .studio-top {
        height: 63px;
        width: 100%;
        padding-inline: 16px;
    }
}
</style>
