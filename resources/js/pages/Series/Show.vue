<script setup lang="ts">
import SeriesWorkspace from '@/components/SeriesWorkspace.vue';
import SeriesProduction from '@/components/SeriesProduction.vue';
import ProjectArtwork from '@/components/projects/ProjectArtwork.vue';
import Heading from '@/components/Heading.vue';
import AppLayout from '@/layouts/AppLayout.vue';
import type { ProjectChannel, ProjectSummary } from '@/types/projects';
import type { PaginatedResource } from '@/types/content';
import type { CharacterSummary, EpisodeSummary, SeriesSummary } from '@/types/series';
import { computed } from 'vue';
import { Head, Link, usePage } from '@inertiajs/vue3';
import { useRoute } from 'ziggy-js';

const props = defineProps<{
    project: Pick<ProjectSummary, 'id' | 'name' | 'is_active' | 'cover'> & { channels: ProjectChannel[] };
    series: SeriesSummary;
    characters: PaginatedResource<CharacterSummary>;
    episodes: EpisodeSummary[];
}>();
const route = useRoute();
const page = usePage();
const production = computed(() => new URL(page.url, 'https://poster.invalid').searchParams.get('section') === 'production');
const params = computed(() => ({ project: props.project.id, series: props.series.id }));
const currentEpisode = computed(() => {
    const run = props.series.latest_production;
    if (run?.episode_id) return props.episodes.find((episode) => episode.id === run.episode_id);
    const latest = props.episodes[0];
    return latest && !latest.accepted_assembly_id ? latest : null;
});
const episodeNumber = computed(
    () => props.series.latest_production?.episode_number ?? currentEpisode.value?.number ?? (props.episodes[0]?.number ?? 0) + 1,
);
const breadcrumbs = [
    { title: 'Проекты', href: route('projects.index') },
    { title: props.project.name, href: route('projects.show', props.project.id) },
];
</script>

<template>
    <AppLayout :breadcrumbs="breadcrumbs">
        <Head :title="project.name" />
        <div class="workspace-page series-overview" :class="{ 'production-view': production }">
            <div class="flex flex-col items-start justify-between gap-3 min-[1100px]:flex-row">
                <Heading :title="series.name" :description="production ? undefined : 'Сериал / Эпизоды и персонажи'" />
                <Link
                    v-if="!production"
                    class="bg-primary text-primary-foreground rounded-lg px-4 py-3 text-sm font-bold"
                    :href="route('projects.edit', project.id)"
                    >Открыть паспорт</Link
                >
            </div>
            <template v-if="production">
                <nav class="run-tabs" aria-label="Разделы сериала">
                    <Link :href="route('series.show', params)">Эпизоды</Link>
                    <Link :href="route('projects.edit', project.id)">Паспорт</Link>
                    <Link :href="route('series.show', { ...params, section: 'production' })" aria-current="page">Производство</Link>
                </nav>
                <div class="run-grid">
                    <SeriesProduction
                        :project-id="project.id"
                        :channels="project.channels"
                        :series-id="series.id"
                        :episode-title="series.latest_production?.episode_title || currentEpisode?.title || 'Эпизод ' + episodeNumber"
                        :episode-number="series.latest_production?.episode_number || episodeNumber"
                        :enabled="series.automation_enabled && project.is_active && !series.archived_at"
                        :run="series.latest_production"
                    />
                    <aside class="studio-panel run-aside">
                        <ProjectArtwork :name="project.name" :cover="project.cover" loading="eager" class="h-[106px] w-full rounded-lg" />
                        <h2>{{ series.latest_production?.episode_title || currentEpisode?.title || 'Эпизод ' + episodeNumber }}</h2>
                        <dl>
                            <dt>Проект</dt>
                            <dd>{{ project.name }}</dd>
                            <dt>Производство</dt>
                            <dd>Эпизод {{ String(episodeNumber).padStart(2, '0') }}</dd>
                        </dl>
                    </aside>
                </div>
            </template>
            <SeriesWorkspace v-else :project="project" :series="series" :characters="characters" :episodes="episodes" />
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
.run-tabs {
    display: flex;
    flex-wrap: wrap;
    gap: 8px;
}
.run-tabs a {
    padding: 10px 13px;
    border: 1px solid var(--border);
    background: var(--card);
    border-radius: 8px;
    font-size: 14px;
}
.run-tabs a[aria-current] {
    color: var(--primary);
    background: color-mix(in srgb, var(--primary) 9%, var(--card));
}
.run-grid {
    display: grid;
    gap: 20px;
    align-items: start;
}
.run-aside {
    display: none;
    padding: 15px;
}
.run-aside h2 {
    margin: 12px 0;
}
.run-aside dt {
    color: var(--muted-foreground);
    font-size: 13px;
}
.run-aside dd {
    margin-bottom: 12px;
    font-size: 15px;
}
@media (min-width: 1100px) {
    .run-grid {
        grid-template-columns: minmax(0, 2.36fr) minmax(0, 1fr);
    }
    .run-aside {
        display: block;
    }
}
.production-view {
    gap: 16px;
    padding-top: 21px;
}
.run-aside {
    padding-bottom: 5px;
}
@media (min-width: 1920px) {
    .run-grid {
        max-width: 1236px;
        grid-template-columns: minmax(0, 860px) minmax(0, 354px);
        gap: 22px;
    }
}
@media (min-width: 768px) and (max-width: 1099px) {
    .production-view {
        gap: 16px;
        padding-top: 21px;
    }
}
@media (max-width: 767px) {
    .production-view {
        gap: 14px;
        padding-top: 22px;
    }
}
</style>
