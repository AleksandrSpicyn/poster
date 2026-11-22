<script setup lang="ts">
import SeriesAssemblyParameters from '@/components/SeriesAssemblyParameters.vue';
import Heading from '@/components/Heading.vue';
import AppLayout from '@/layouts/AppLayout.vue';
import type { AssemblyParameters } from '@/types/series';
import { Head } from '@inertiajs/vue3';
import { useRoute } from 'ziggy-js';
defineProps<{ series: { id: number; name: string; project_id: number }; parameters: AssemblyParameters }>();
const route = useRoute();
</script>
<template>
    <AppLayout
        :breadcrumbs="[
            { title: 'Проект', href: route('projects.show', series.project_id) },
            { title: series.name, href: route('series.show', { project: series.project_id, series: series.id }) },
        ]"
    >
        <Head title="Параметры сборки" />
        <main class="workspace-page">
            <Heading title="Сборка сериала" :description="series.name" />
            <SeriesAssemblyParameters :series="series" :parameters="parameters" />
        </main>
    </AppLayout>
</template>
