<script setup lang="ts">
import SeriesWorkspace from '@/components/SeriesWorkspace.vue';
import Heading from '@/components/Heading.vue';
import AppLayout from '@/layouts/AppLayout.vue';
import type { PaginatedResource } from '@/types/content';
import type { CharacterSummary, EpisodeSummary, SeriesSummary } from '@/types/series';
import { Head } from '@inertiajs/vue3';
import { useRoute } from 'ziggy-js';

const props = defineProps<{
    project: { id: number; name: string };
    series: SeriesSummary;
    characters: PaginatedResource<CharacterSummary>;
    episodes: EpisodeSummary[];
}>();
const route = useRoute();
const breadcrumbs = [
    { title: 'Projects', href: route('projects.index') },
    { title: props.project.name, href: route('projects.show', props.project.id) },
];
</script>

<template>
    <AppLayout :breadcrumbs="breadcrumbs">
        <Head :title="project.name" />
        <div class="workspace-page">
            <Heading :title="project.name" description="Series project" /><SeriesWorkspace
                :project="project"
                :series="series"
                :characters="characters"
                :episodes="episodes"
            />
        </div>
    </AppLayout>
</template>
