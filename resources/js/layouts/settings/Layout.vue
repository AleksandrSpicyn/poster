<script setup lang="ts">
import Heading from '@/components/Heading.vue';
import { Link, usePage } from '@inertiajs/vue3';
import { useRoute } from '@/composables/useRoute';

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
        <header class="settings-header"><Heading :title="title" :description="description" /><slot name="action" /></header>
        <nav aria-label="Настройки аккаунта" class="workspace-tabs">
            <Link
                v-for="item in links"
                :key="item.href"
                :href="item.href"
                :aria-current="page.url.split(/[?#]/)[0] === item.href ? 'page' : undefined"
                >{{ item.label }}</Link
            >
        </nav>
        <div class="grid gap-4">
            <section class="settings-content studio-panel"><slot /></section>
        </div>
    </div>
</template>

<style scoped>
.settings-header {
    display: flex;
    flex-wrap: wrap;
    align-items: start;
    justify-content: space-between;
    gap: 1rem;
}
.settings-content {
    min-width: 0;
}
.settings-content :deep(.account-form) {
    display: grid;
    gap: 1.25rem;
}
.settings-content :deep(.account-form label) {
    margin-bottom: 0.375rem;
}
</style>
