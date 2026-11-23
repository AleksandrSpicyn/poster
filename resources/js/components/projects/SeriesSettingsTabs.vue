<script setup lang="ts">
import { seriesSettingsSections as sections } from '@/lib/seriesSettingsSections';
import { Link } from '@inertiajs/vue3';
import { useRoute } from 'ziggy-js';

const props = defineProps<{ projectId?: number; section: string }>();
const route = useRoute();

function href(key: string) {
    return props.section === 'cover' ? route('projects.edit', props.projectId) + '#' + key : '#' + key;
}
</script>
<template>
    <nav class="col-span-full flex flex-wrap gap-2" aria-label="Разделы паспорта сериала">
        <template v-for="(label, key) in sections" :key="key">
            <component
                :is="section === 'cover' ? Link : 'a'"
                v-if="projectId || (key !== 'automation' && key !== 'access')"
                :href="href(key)"
                :aria-current="section === key || (section === 'channels' && key === 'plan') ? 'page' : undefined"
                >{{ label }}</component
            >
        </template>
        <Link v-if="projectId" :href="route('projects.cover.edit', projectId)" :aria-current="section === 'cover' ? 'page' : undefined"
            >Обложка проекта</Link
        >
    </nav>
</template>
<style scoped>
a {
    background: var(--muted);
    color: var(--muted-foreground);
    border-radius: 8px;
    padding: 10px 12px;
    font-size: 14px;
}
a[aria-current='page'] {
    background: var(--sidebar-accent);
    color: var(--sidebar-accent-foreground);
    font-weight: 600;
}
</style>
