<script setup lang="ts">
import Heading from '@/components/Heading.vue';
import { Link, usePage } from '@inertiajs/vue3';
import { useRoute } from 'ziggy-js';

defineProps<{ title: string; description: string }>();
const route = useRoute();
const page = usePage();
const links = [
    { label: 'Профиль', href: route('profile.edit', undefined, false) },
    { label: 'Безопасность', href: route('password.edit', undefined, false) },
    { label: 'Оформление', href: route('appearance', undefined, false) },
];
</script>

<template>
    <div class="workspace-page">
        <Heading :title="title" :description="description" />
        <div class="settings-grid">
            <nav aria-label="Настройки аккаунта">
                <h2>В этом разделе</h2>
                <Link
                    v-for="item in links"
                    :key="item.href"
                    :href="item.href"
                    :aria-current="page.url.split('?')[0] === item.href ? 'page' : undefined"
                    >{{ item.label }}</Link
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
nav a {
    display: flex;
    align-items: center;
    padding: 0 12px;
    min-height: 44px;
    border-radius: 9px;
    font-size: 14px;
    background: var(--secondary);
}
nav a[aria-current='page'] {
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
        grid-template-columns: min(28%, 276px) minmax(0, 820px);
    }
    nav {
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
    nav a {
        background: transparent;
    }
}
</style>
