<script setup lang="ts">
import ProjectArchive from '@/components/projects/ProjectArchive.vue';
import SeriesWorkspace from '@/components/SeriesWorkspace.vue';
import SeriesProduction from '@/components/SeriesProduction.vue';
import ProjectArtwork from '@/components/projects/ProjectArtwork.vue';
import SeriesMaterials from '@/components/SeriesMaterials.vue';
import AppLayout from '@/layouts/AppLayout.vue';
import type { ProjectChannel, ProjectSummary } from '@/types/projects';
import type { PaginatedResource } from '@/types/content';
import type { CharacterSummary, EpisodeSummary, SeriesSummary } from '@/types/series';
import { computed } from 'vue';
import { Head, Link, usePage } from '@inertiajs/vue3';
import { useRoute } from '@/composables/useRoute';

const props = defineProps<{
    project: Pick<ProjectSummary, 'id' | 'name' | 'is_active' | 'cover' | 'archived_at'> & { channels: ProjectChannel[] };
    series: SeriesSummary;
    characters: PaginatedResource<CharacterSummary>;
    episodes: EpisodeSummary[];
}>();
const route = useRoute();
const page = usePage();
const sections = { overview: 'Обзор', production: 'Производство', materials: 'Материалы' } as const;
const section = computed(() => {
    const key = new URL(page.url, page.props.ziggy.url).searchParams.get('section');
    return key === 'production' || key === 'materials' ? key : 'overview';
});
function sectionUrl(key: keyof typeof sections) {
    return route('series.show', { project: props.project.id, series: props.series.id, ...(key === 'overview' ? {} : { section: key }) });
}

const episodePlanComplete = computed(() => {
    const latest = props.episodes[0];
    return (
        props.series.output_mode === 'episodes' &&
        props.series.episode_limit != null &&
        latest?.accepted_assembly_id != null &&
        latest.number >= props.series.episode_limit
    );
});
const displayedRun = computed(() => {
    const run = props.series.latest_production;
    return props.series.output_mode === 'episodes' && !episodePlanComplete.value && run?.status === 'completed' ? null : run;
});
const currentEpisode = computed(() => {
    const run = displayedRun.value;
    if (run?.episode_id) return props.episodes.find((episode) => episode.id === run.episode_id);
    const latest = props.episodes[0];
    return latest && (!latest.accepted_assembly_id || episodePlanComplete.value) ? latest : null;
});
const episodeNumber = computed(() => displayedRun.value?.episode_number ?? currentEpisode.value?.number ?? (props.episodes[0]?.number ?? 0) + 1);
const breadcrumbs = computed(() => [
    { title: 'Сериалы', href: route('series.index') },
    { title: props.series.name, href: route('series.show', { project: props.project.id, series: props.series.id }) },
    { title: sections[section.value], href: page.url },
]);
</script>

<template>
    <AppLayout series-header :breadcrumbs="breadcrumbs">
        <Head :title="series.name + ' · ' + sections[section]" />
        <div class="workspace-page">
            <header>
                <div class="flex items-center gap-3">
                    <ProjectArtwork :name="series.name" :cover="project.cover" class="size-10 shrink-0 rounded-lg min-[700px]:size-12" />
                    <h1 class="min-w-0 text-3xl font-bold wrap-anywhere">{{ series.name }}</h1>
                </div>
                <nav class="mt-3 flex gap-1 border-b pb-2 min-[700px]:gap-2" aria-label="Разделы сериала">
                    <Link
                        v-for="(label, key) in sections"
                        :key="key"
                        :href="sectionUrl(key)"
                        :aria-current="section === key ? 'page' : undefined"
                        class="inline-flex min-h-11 items-center rounded-lg px-2 text-sm min-[700px]:px-4"
                        :class="section === key ? 'bg-accent text-accent-foreground font-bold' : 'text-muted-foreground hover:bg-muted'"
                        >{{ label }}</Link
                    >
                </nav>
            </header>
            <div v-if="section === 'overview'" id="series-heading-actions" class="flex flex-wrap gap-2">
                <Link class="bg-primary text-primary-foreground rounded-lg px-4 py-3 text-sm font-bold" :href="route('projects.edit', project.id)"
                    >Открыть паспорт</Link
                >
            </div>
            <SeriesMaterials v-if="section === 'materials'" :series="series" />
            <template v-else-if="section === 'production'">
                <div class="run-grid">
                    <SeriesProduction
                        :key="displayedRun?.id ?? episodeNumber + '-next'"
                        :project-id="project.id"
                        :channels="project.channels"
                        :series-id="series.id"
                        :auto-publish="series.auto_publish"
                        :plan-complete="episodePlanComplete"
                        :pinned-channel-ids="
                            series.output_mode === 'single_video' ? series.latest_production?.channel_ids : currentEpisode?.delivery_channel_ids
                        "
                        :episode-title="displayedRun?.episode_title || currentEpisode?.title || 'Эпизод ' + episodeNumber"
                        :episode-number="episodeNumber"
                        :enabled="
                            project.is_active && !series.archived_at && (!displayedRun || series.automation_enabled || displayedRun.manual_start)
                        "
                        :run="displayedRun"
                    />
                    <aside class="studio-panel run-aside">
                        <ProjectArtwork :name="project.name" :cover="project.cover" loading="eager" class="h-[106px] w-full rounded-lg" />
                        <h2>{{ displayedRun?.episode_title || currentEpisode?.title || 'Эпизод ' + episodeNumber }}</h2>
                        <dl>
                            <dt>Проект</dt>
                            <dd>{{ project.name }}</dd>
                            <dt>Производство</dt>
                            <dd>Эпизод {{ String(episodeNumber).padStart(2, '0') }}</dd>
                        </dl>
                    </aside>
                </div>
            </template>
            <SeriesWorkspace
                v-else
                actions-target="#series-heading-actions"
                :project="project"
                :series="series"
                :characters="characters"
                :episodes="episodes"
            />
            <ProjectArchive v-if="section === 'overview'" :project-id="project.id" :archived="!!project.archived_at" />
        </div>
    </AppLayout>
</template>

<style scoped>
.run-grid {
    display: grid;
    gap: 20px;
    align-items: start;
}
.run-aside {
    display: none;
    padding: 15px;
    padding-bottom: 5px;
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
@media (min-width: 1920px) {
    .run-grid {
        max-width: 1236px;
        grid-template-columns: minmax(0, 860px) minmax(0, 354px);
        gap: 22px;
    }
}
</style>
