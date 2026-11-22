<script setup lang="ts">
import SeriesWorkspace from '@/components/SeriesWorkspace.vue';
import Heading from '@/components/Heading.vue';
import AppLayout from '@/layouts/AppLayout.vue';
import type { ProjectSummary } from '@/types/projects';
import type { PaginatedResource } from '@/types/content';
import type { CharacterSummary, EpisodeSummary, SeriesSummary } from '@/types/series';
import { Head, Link } from '@inertiajs/vue3';
import { useRoute } from 'ziggy-js';

const props = defineProps<{
    project: Pick<ProjectSummary, 'id' | 'name' | 'is_active' | 'cover'>;
    series: SeriesSummary;
    characters: PaginatedResource<CharacterSummary>;
    episodes: EpisodeSummary[];
}>();
const route = useRoute();
const breadcrumbs = [
    { title: 'Проекты', href: route('projects.index') },
    { title: props.project.name, href: route('projects.show', props.project.id) },
];
</script>

<template>
    <AppLayout :breadcrumbs="breadcrumbs">
        <Head :title="project.name" />
        <div class="workspace-page series-overview">
            <div class="flex flex-col items-start justify-between gap-3 min-[1100px]:flex-row">
                <Heading :title="series.name" description="Сериал / Эпизоды и персонажи" />
                <Link class="bg-primary text-primary-foreground rounded-lg px-4 py-3 text-sm font-bold" :href="route('projects.edit', project.id)"
                    >Открыть паспорт</Link
                >
            </div>
            <SeriesWorkspace :project="project" :series="series" :characters="characters" :episodes="episodes" />
        </div>
    </AppLayout>
</template>

<style scoped>
.series-overview {
    gap: 23px;
}
@media (min-width: 768px) and (max-width: 1099px) {
    .series-overview {
        gap: 25px;
    }
}
@media (max-width: 767px) {
    .series-overview {
        gap: 19px;
    }
}
</style>
