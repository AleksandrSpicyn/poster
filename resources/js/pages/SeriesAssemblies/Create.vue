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
            { title: 'Сериалы', href: route('series.index') },
            { title: series.name, href: route('series.show', { project: series.project_id, series: series.id }) },
            { title: 'Параметры сборки', href: route('series_assemblies.create', series.id) },
        ]"
    >
        <Head title="Параметры сборки" />
        <div class="workspace-page">
            <Heading title="Сборка сериала" :description="series.name" />
            <SeriesAssemblyParameters :series="series" :parameters="parameters" />
        </div>
    </AppLayout>
</template>
