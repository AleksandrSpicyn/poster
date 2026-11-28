<script setup lang="ts">
import { useAppearance } from '@/composables/useAppearance';
import { Moon, Sun } from '@lucide/vue';
import AppLogo from '@/components/AppLogo.vue';
import Breadcrumbs from '@/components/Breadcrumbs.vue';
import { SidebarTrigger, useSidebar } from '@/components/ui/sidebar';
import type { BreadcrumbItem } from '@/types';
import { computed } from 'vue';
import { Link } from '@inertiajs/vue3';
import { useRoute } from '@/composables/useRoute';

const props = withDefaults(defineProps<{ breadcrumbs?: BreadcrumbItem[]; seriesHeader?: boolean }>(), { breadcrumbs: () => [] });
const { updateAppearance } = useAppearance();
function toggleTheme() {
    updateAppearance(document.documentElement.classList.contains('dark') ? 'light' : 'dark');
}
const route = useRoute();
const studio = { title: 'Обзор', href: route('dashboard') };
const parentTrail = computed(() => props.breadcrumbs.at(-1)?.href.startsWith('#'));
const mobileReturn = computed(() => props.breadcrumbs.at(-2) ?? (props.breadcrumbs[0]?.href !== studio.href ? studio : undefined));
const { openMobile, setOpenMobile } = useSidebar();
</script>
<template>
    <header class="studio-top" :class="{ 'parent-trail': parentTrail }">
        <Link :href="route('dashboard')" class="min-[700px]:hidden" aria-label="Poster — обзор студии"><AppLogo /></Link>
        <nav v-if="mobileReturn && !parentTrail && !seriesHeader" class="mx-3 min-w-0 min-[700px]:hidden" aria-label="Хлебные крошки">
            <Link :href="mobileReturn.href" class="block truncate text-sm underline" :title="mobileReturn.title">{{ mobileReturn.title }}</Link>
        </nav>
        <button
            v-if="!seriesHeader"
            class="text-muted-foreground ml-auto min-h-11 translate-x-px translate-y-px px-4 text-[13px] min-[700px]:hidden"
            aria-haspopup="dialog"
            :aria-expanded="openMobile"
            @click="setOpenMobile(true)"
        >
            Моя студия
        </button>
        <Breadcrumbs v-if="parentTrail" class="mobile-parent-trail min-[700px]:hidden" :breadcrumbs="breadcrumbs" />
        <div class="hidden min-w-0 flex-1 items-center gap-4 min-[700px]:flex">
            <Link v-if="!parentTrail && !seriesHeader" class="text-muted-foreground shrink-0 text-sm" :href="route('dashboard')">Моя студия</Link>
            <span v-if="!parentTrail && !seriesHeader" aria-hidden="true" class="text-muted-foreground">/</span
            ><Breadcrumbs v-if="breadcrumbs.length" :breadcrumbs="breadcrumbs" />
            <SidebarTrigger v-if="!seriesHeader" class="ml-auto size-11" />
        </div>
        <button
            v-if="seriesHeader"
            class="bg-card ml-auto grid size-11 place-items-center rounded-lg border"
            aria-label="Переключить тему"
            @click="toggleTheme"
        >
            <Moon class="size-5 dark:hidden" /><Sun class="hidden size-5 dark:block" />
        </button>
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
@media (max-width: 699px) {
    .studio-top.parent-trail {
        flex-wrap: wrap;
        min-height: 93px;
        height: auto;
    }
    .mobile-parent-trail {
        order: 3;
        width: 100%;
    }
    .mobile-parent-trail :deep(a) {
        display: inline-flex;
        align-items: center;
        min-height: 44px;
        font-size: 12px;
    }

    .studio-top :deep(.studio-brand strong) {
        translate: -2px -2px;
    }
    .studio-top :deep(.studio-brand-mark i) {
        height: 21px;
    }
    .studio-top {
        height: 63px;
        width: 100%;
        padding-inline: 16px;
    }
}
</style>
