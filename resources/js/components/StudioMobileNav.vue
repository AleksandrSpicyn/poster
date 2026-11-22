<script setup lang="ts">
import { useSidebar } from '@/components/ui/sidebar';
import { useNavigation } from '@/composables/useNavigation';
import { Link } from '@inertiajs/vue3';
import { Menu } from '@lucide/vue';
import { computed } from 'vue';
import { useRoute } from 'ziggy-js';

const route = useRoute();
const { main } = useNavigation();
const { openMobile, setOpenMobile } = useSidebar();
const primaryPaths: string[] = ['dashboard', 'projects.index', 'posts'].map((name) => route(name, undefined, false));
const inMore = computed(() => main.value.some((item) => item.isActive && !primaryPaths.includes(item.href)));
const links = computed(() => main.value.filter((item) => primaryPaths.includes(item.href)));
</script>

<template>
    <nav aria-label="Основные разделы" class="mobile-nav">
        <Link v-for="item in links" :key="item.href" :href="item.href" :aria-current="item.isActive ? 'page' : undefined">
            <component :is="item.icon" aria-hidden="true" />{{ item.title }}
        </Link>
        <button type="button" :class="{ active: inMore }" :aria-expanded="openMobile" aria-haspopup="dialog" @click="setOpenMobile(true)">
            <Menu aria-hidden="true" />Ещё
        </button>
    </nav>
</template>

<style scoped>
.mobile-nav {
    position: fixed;
    z-index: 20;
    inset: auto 0 0;
    display: grid;
    grid-template-columns: repeat(4, 1fr);
    border-top: 1px solid var(--border);
    background: var(--sidebar);
    padding: 0 0 env(safe-area-inset-bottom);
}
.mobile-nav a,
.mobile-nav button {
    min-height: 65px;
    display: flex;
    align-items: center;
    justify-content: center;
    flex-direction: column;
    gap: 4px;
    color: var(--muted-foreground);
    font-size: 11px;
}
.mobile-nav [aria-current='page'],
.mobile-nav [aria-expanded='true'],
.mobile-nav button.active {
    color: var(--ring);
}
.mobile-nav svg {
    width: 20px;
    height: 20px;
}
.mobile-nav :focus-visible {
    outline: 2px solid var(--ring);
    outline-offset: -3px;
}
@media (min-width: 768px) {
    .mobile-nav {
        display: none;
    }
}
</style>
