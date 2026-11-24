<script setup lang="ts">
import AppLayout from '@/layouts/AppLayout.vue';
import Heading from '@/components/Heading.vue';
import { Head, Link } from '@inertiajs/vue3';
import { useRoute } from '@/composables/useRoute';
import { computed } from 'vue';
const props = defineProps<{
    connection: { id: number; name: string };
    sources: { id: string; title: string | null }[];
    sourceBindings: { external_id: string; project_id: number }[];
}>();
const route = useRoute();
const bindingsBySource = computed(() => {
    const projects = new Map<string, number[]>();
    for (const binding of props.sourceBindings) {
        const ids = projects.get(binding.external_id);
        if (ids) ids.push(binding.project_id);
        else projects.set(binding.external_id, [binding.project_id]);
    }
    return projects;
});
</script>
<template>
    <AppLayout
        :breadcrumbs="[
            { title: 'Источники', href: route('clipping.index') },
            { title: connection.name, href: route('clipping.edit', connection.id) },
            { title: 'Проекты площадки', href: route('clipping.sources', connection.id) },
        ]"
        ><Head title="Проекты площадки" />
        <div class="workspace-page">
            <Heading title="Проекты площадки" :description="connection.name" />
            <p v-if="!sources.length">Проекты не найдены.</p>
            <ul class="divide-y">
                <li v-for="source in sources" :key="source.id" class="flex flex-wrap items-center justify-between gap-2 py-3">
                    <span>{{ source.title || source.id }}</span><template v-for="projectId in bindingsBySource.get(source.id)" :key="projectId"
                        ><Link class="underline" :href="route('projects.show', projectId)">Открыть проект</Link></template
                    >
                </li>
            </ul>
        </div></AppLayout
    >
</template>
