<script setup lang="ts">
import AppLayout from '@/layouts/AppLayout.vue';
import Heading from '@/components/Heading.vue';
import { Head, Link } from '@inertiajs/vue3';
import { useRoute } from 'ziggy-js';
defineProps<{
    connection: { id: number; name: string };
    sources: { id: string; title: string | null }[];
    sourceBindings: { external_id: string; project_id: number }[];
}>();
const route = useRoute();
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
                    <span>{{ source.title || source.id }}</span
                    ><template v-for="binding in sourceBindings.filter((item) => item.external_id === source.id)" :key="binding.project_id"
                        ><Link class="underline" :href="route('projects.show', binding.project_id)">Открыть проект</Link></template
                    >
                </li>
            </ul>
        </div></AppLayout
    >
</template>
