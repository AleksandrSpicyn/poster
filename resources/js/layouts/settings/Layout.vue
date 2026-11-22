<script setup lang="ts">
import Heading from '@/components/Heading.vue';
import { Link, usePage } from '@inertiajs/vue3';
import { useRoute } from 'ziggy-js';

defineProps<{ title: string; description: string }>();
const route = useRoute();
const page = usePage();
const links = [
    { label: 'Профиль', href: route('profile.edit', undefined, false) },
    { label: 'Пароль', href: route('password.edit', undefined, false) },
    { label: 'Оформление', href: route('appearance', undefined, false) },
];
</script>

<template>
    <div class="workspace-page">
        <header class="settings-header"><Heading :title="title" :description="description" /><slot name="action" /></header>
        <div class="settings-grid">
            <nav aria-label="Настройки аккаунта">
                <h2>В этом разделе</h2>
                <slot name="navigation"
                    ><Link
                        v-for="item in links"
                        :key="item.href"
                        :href="item.href"
                        :aria-current="page.url.split('?')[0] === item.href ? 'page' : undefined"
                        >{{ item.label }}</Link
                    ></slot
                >
            </nav>
            <section class="settings-content"><slot /></section>
        </div>
    </div>
</template>

<style scoped>
.settings-grid {
    display: grid;
    align-items: start;
    gap: 16px;
}
nav {
    display: flex;
    flex-wrap: wrap;
    gap: 6px;
}
nav h2 {
    display: none;
}
nav :deep(a) {
    display: flex;
    align-items: center;
    padding: 0 12px;
    min-height: 44px;
    border-radius: 9px;
    font-size: 14px;
    background: var(--secondary);
}
nav :deep(a[aria-current='page']) {
    background: var(--accent);
    color: var(--accent-foreground);
    font-weight: 700;
}
.settings-content {
    min-width: 0;
    border-radius: 12px;
    border: 1px solid var(--border);
    background: var(--card);
    padding: clamp(16px, 2vw, 26px);
}
.settings-content :deep([data-slot='input']) {
    background: var(--background);
    min-height: 48px;
    font-size: 16px;
}
.settings-content :deep([data-slot='button']) {
    min-height: 44px;
}
.settings-content > :deep(* + *) {
    margin-top: 32px;
}
@media (min-width: 1100px) {
    .settings-grid {
        grid-template-columns: min(28%, 276px) minmax(0, 852px);
    }
    nav {
        min-height: 240px;
        flex-direction: column;
        border: 1px solid var(--border);
        border-radius: 12px;
        padding: 16px 10px;
        background: var(--card);
    }
    nav h2 {
        display: block;
        margin: 0 12px 12px;
        font-size: 20px;
        font-weight: 700;
    }
    nav :deep(a) {
        background: transparent;
    }
}

.settings-header {
    display: flex;
    flex-wrap: wrap;
    justify-content: space-between;
    align-items: start;
    gap: 16px;
}
.settings-header :deep(button) {
    min-height: 44px;
    font-weight: 700;
}
.settings-content :deep(label) {
    display: block;
    margin: 12px 0 6px;
    font-size: 14px;
    color: var(--muted-foreground);
    font-weight: 400;
}
.settings-content :deep(input) {
    height: 48px;
}
@media (max-width: 1099px) {
    .settings-header {
        flex-direction: column;
    }
}
@media (min-width: 1920px) {
    .settings-grid {
        grid-template-columns: 276px minmax(0, 820px);
    }
}
</style>
