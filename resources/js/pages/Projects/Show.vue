<script setup lang="ts">
import ChannelIcon from '@/components/ChannelIcon.vue';
import EmptyState from '@/components/EmptyState.vue';
import GenerationItem from '@/components/GenerationItem.vue';
import Heading from '@/components/Heading.vue';
import ListControls from '@/components/ListControls.vue';
import ListPanel from '@/components/ListPanel.vue';
import ProjectNotice from '@/components/projects/ProjectNotice.vue';
import ProductionPanel from '@/components/ProductionPanel.vue';
import SeriesWorkspace from '@/components/SeriesWorkspace.vue';
import { Button } from '@/components/ui/button';
import {
    Dialog,
    DialogClose,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogHeader,
    DialogTitle,
    DialogTrigger,
} from '@/components/ui/dialog';
import { useListFilters } from '@/composables/useListFilters';
import AppLayout from '@/layouts/AppLayout.vue';
import { formatProjectDate } from '@/lib/formatProjectDate';
import type { BreadcrumbItem } from '@/types';
import type { ProjectPageProps } from '@/types/projects';
import { Head, Link, useForm, usePage } from '@inertiajs/vue3';
import { LoaderCircle, Pencil, Trash2 } from '@lucide/vue';
import { computed, ref } from 'vue';
import { useRoute } from 'ziggy-js';

const route = useRoute();
const page = usePage<ProjectPageProps>();
const { filters, loading, error, changeSort, changePerPage, changePage, refresh } = useListFilters(
    'projects.show',
    () => ({
        sort: page.props.filters.sort,
        perPage: (page.props.opusClips ?? page.props.prompts).meta.per_page,
        page: (page.props.opusClips ?? page.props.prompts).meta.current_page,
    }),
    () => ({ project: page.props.project.id }),
);
const breadcrumbs = computed<BreadcrumbItem[]>(() => [
    { title: 'Projects', href: route('projects.index') },
    { title: page.props.project.name, href: route('projects.show', page.props.project.id) },
]);
const metricLabels: Record<string, string> = {
    views: 'Views',
    engagedViews: 'Engaged views',
    averageViewDuration: 'Average watch (s)',
    averageViewPercentage: 'Average viewed (%)',
    shares: 'Shares',
    reach: 'Reach',
    saved: 'Saves',
};
const deleteOpen = ref(false);
const deleteError = ref('');
const deleteForm = useForm({});

function destroyProject() {
    if (deleteForm.processing) return;
    deleteError.value = '';
    deleteForm.delete(route('projects.destroy', page.props.project.id), {
        onSuccess: () => {
            deleteOpen.value = false;
        },
        onHttpException: (response) => {
            if (response.status === 419) return;
            deleteError.value = 'This project could not be deleted. Refresh its details and try again.';
            return false;
        },
        onNetworkError: () => {
            deleteError.value = 'Connection lost. Reconnect and try again.';
            return false;
        },
    });
}
</script>

<template>
    <AppLayout :breadcrumbs="breadcrumbs">
        <Head :title="page.props.project.name" />
        <div class="workspace-page">
            <ProjectNotice />
            <div class="flex flex-col items-start justify-between gap-4 sm:flex-row">
                <div class="w-full min-w-0 flex-1 space-y-3 [&_h1]:break-words">
                    <Heading
                        :title="page.props.project.name"
                        :description="page.props.project.content_mode_label + ' · Creative direction, publishing channels and production history.'"
                    />
                    <dl class="text-muted-foreground flex flex-wrap gap-x-6 gap-y-2 text-xs">
                        <div class="flex gap-1">
                            <dt>Created</dt>
                            <dd>{{ formatProjectDate(page.props.project.created_at) }}</dd>
                        </div>
                        <div class="flex gap-1">
                            <dt>Last edited</dt>
                            <dd>{{ formatProjectDate(page.props.project.updated_at) }}</dd>
                        </div>
                    </dl>
                </div>
                <Button as-child variant="outline" class="h-11 shrink-0"
                    ><Link :href="route('projects.edit', page.props.project.id)"
                        ><Pencil class="size-4" aria-hidden="true" />Edit project</Link
                    ></Button
                >
            </div>

            <p
                v-if="!page.props.project.is_active && !page.props.opusClips"
                role="status"
                class="bg-muted text-muted-foreground rounded-lg border p-4 text-sm"
            >
                This project is disabled. Generation and publication are paused. Enable it in project settings to produce new content.
            </p>

            <dl class="bg-card grid divide-x rounded-xl border" :class="page.props.opusClips ? 'grid-cols-2' : 'grid-cols-3'">
                <div class="px-4 py-4 sm:px-6">
                    <dt class="text-muted-foreground text-xs sm:text-sm">
                        {{ page.props.opusClips ? 'Imported clips' : page.props.series ? 'Episodes' : 'Generations' }}
                    </dt>
                    <dd class="mt-2 text-2xl font-semibold tabular-nums">
                        {{ page.props.opusClips?.meta.total ?? page.props.series?.episodes_count ?? page.props.project.prompts_count }}
                    </dd>
                </div>
                <div v-if="!page.props.opusClips" class="px-4 py-4 sm:px-6">
                    <dt class="text-muted-foreground text-xs sm:text-sm">{{ page.props.series ? 'Characters' : 'Ready videos' }}</dt>
                    <dd class="mt-2 text-2xl font-semibold tabular-nums">
                        {{ page.props.series?.characters_count ?? page.props.project.ready_prompts_count }}
                    </dd>
                </div>
                <div class="px-4 py-4 sm:px-6">
                    <dt class="text-muted-foreground text-xs sm:text-sm">Channels</dt>
                    <dd class="mt-2 text-2xl font-semibold tabular-nums">{{ page.props.project.channels_count }}</dd>
                </div>
            </dl>

            <ProductionPanel
                v-if="!page.props.opusClips && page.props.auth.can_manage_series && !page.props.series && page.props.project.content_mode === 'short'"
                :project-id="page.props.project.id"
                :run="page.props.project.latest_production"
                :project-enabled="page.props.project.is_active"
            />

            <div class="grid items-start gap-6 lg:grid-cols-[minmax(0,1.6fr)_minmax(16rem,1fr)]">
                <section class="bg-card min-w-0 overflow-hidden rounded-xl border" aria-labelledby="project-brief-heading">
                    <div class="border-b px-5 py-4">
                        <h2 id="project-brief-heading" class="font-semibold">{{ page.props.series ? 'World and premise' : 'Base prompt' }}</h2>
                        <p class="text-muted-foreground mt-1 text-sm">The creative direction used for new ideas.</p>
                    </div>
                    <div
                        tabindex="0"
                        role="region"
                        :aria-label="page.props.series ? 'World and premise' : 'Base prompt'"
                        class="focus-visible:ring-ring/50 max-h-72 overflow-y-auto p-5 text-sm leading-7 break-words whitespace-pre-wrap outline-none focus-visible:ring-2 focus-visible:ring-inset"
                    >
                        {{ page.props.series?.specification.premise ?? page.props.project.prompt }}
                    </div>
                </section>
                <section class="bg-card min-w-0 overflow-hidden rounded-xl border" aria-labelledby="project-channels-heading">
                    <div class="border-b px-5 py-4">
                        <h2 id="project-channels-heading" class="font-semibold">Publishing channels</h2>
                        <p class="text-muted-foreground mt-1 text-sm">Destinations connected to this project.</p>
                    </div>
                    <ul v-if="page.props.project.channels.length" class="divide-y">
                        <li v-for="channel in page.props.project.channels" :key="channel.id" class="flex items-center gap-3 px-5 py-4">
                            <ChannelIcon :channel="channel.type" class="size-5 shrink-0" />
                            <div class="min-w-0 flex-1">
                                <p class="text-sm font-medium break-words">{{ channel.name }}</p>
                                <p class="text-muted-foreground mt-0.5 text-xs">{{ channel.type_label }}</p>
                            </div>
                            <Link v-if="page.props.opusClips" :href="route('channels.edit', channel.id)" class="text-sm underline"
                                >Manage connection</Link
                            >
                            <span v-else class="text-muted-foreground shrink-0 text-xs tabular-nums">{{ channel.posts_count }} posts</span>
                        </li>
                    </ul>
                    <EmptyState
                        v-else
                        title="No channels connected"
                        description="Generated videos will still be available in this project's history."
                    />
                </section>
            </div>

            <section v-if="page.props.opusClips" aria-labelledby="opus-clips-heading" class="space-y-4">
                <details v-for="report in page.props.gamingTrends" :key="report.game" class="bg-card rounded-xl border p-5">
                    <summary class="cursor-pointer font-medium">{{ report.game }} · YouTube weekly leaders</summary>
                    <p class="text-muted-foreground my-2 text-xs">Updated {{ formatProjectDate(report.checkedAt) }}</p>
                    <ol class="list-inside list-decimal space-y-2 text-sm">
                        <li v-for="{ title, channel, views, views_per_day, url } in report.entries" :key="url">
                            <a :href="url" target="_blank" rel="noopener noreferrer" class="underline">{{ title }}</a> · {{ channel }} ·
                            {{ views }} views<span v-if="views_per_day != null"> · {{ views_per_day }} views/day since upload (minimum 1 day)</span>
                        </li>
                    </ol>
                    <p v-if="!report.entries.length" class="text-muted-foreground text-sm">No matching videos this week.</p>
                </details>
                <h2 id="opus-clips-heading" class="text-lg font-semibold">OpusClip library</h2>
                <ListPanel
                    :meta="page.props.opusClips.meta"
                    :count="page.props.opusClips.data.length"
                    :loading="loading"
                    :error="error"
                    empty-title="No clips yet"
                    empty-description="Clips will appear after the next import."
                    @page="changePage"
                    @refresh="refresh"
                >
                    <ul class="divide-y">
                        <li v-for="clip in page.props.opusClips.data" :key="clip.id" class="space-y-2 p-5">
                            <h3 class="font-medium">{{ clip.title || clip.clip_id }}</h3>
                            <p class="text-muted-foreground flex gap-4 text-xs">
                                <a :href="clip.source_url" target="_blank" rel="noopener noreferrer" class="underline">{{
                                    clip.game ?? clip.source
                                }}</a>
                                <span v-if="clip.duration_ms !== null">{{ (clip.duration_ms / 1000).toFixed(1) }} s</span>
                                <span v-if="clip.score !== null">OpusClip score {{ clip.score }}</span>
                            </p>
                            <ul v-if="clip.publications.length" class="space-y-1 text-sm">
                                <li v-for="(post, index) in clip.publications" :key="index">
                                    <a v-if="post.url" :href="post.url" target="_blank" rel="noopener noreferrer" class="underline">{{
                                        post.platform
                                    }}</a>
                                    <span v-else>{{ post.platform }}</span> · {{ post.status ?? 'Status unavailable' }}
                                </li>
                            </ul>
                            <p v-else class="text-muted-foreground text-xs">No publication reported by OpusClip</p>
                            <p v-if="clip.publications.some((post) => post.url) && !clip.observations.length" class="text-muted-foreground text-xs">
                                Awaiting platform analytics
                            </p>
                            <div v-for="row in clip.observations" :key="row.platform + row.media_id" class="text-sm">
                                <p>{{ row.platform }} · {{ row.date }}</p>
                                <dl class="flex flex-wrap gap-x-5 text-xs">
                                    <div v-for="(value, metric) in row.metrics" :key="metric">
                                        <dt class="text-muted-foreground">{{ metricLabels[metric] ?? metric }}</dt>
                                        <dd>{{ value }}</dd>
                                    </div>
                                </dl>
                            </div>
                        </li>
                    </ul>
                </ListPanel>
            </section>
            <SeriesWorkspace
                v-else-if="page.props.series && page.props.characters"
                :project="page.props.project"
                :series="page.props.series"
                :characters="page.props.characters"
                :episodes="page.props.episodes"
            />
            <section v-else class="min-w-0 space-y-4" aria-labelledby="project-history-heading">
                <div>
                    <h2 id="project-history-heading" class="text-lg font-semibold">Generation history</h2>
                    <p class="text-muted-foreground mt-1 text-sm">Follow each idea from its first frame to a finished video.</p>
                </div>
                <ListPanel
                    :meta="page.props.prompts.meta"
                    :count="page.props.prompts.data.length"
                    :loading="loading"
                    :error="error"
                    empty-title="No generations yet"
                    empty-description="Ideas and finished videos for this project will appear here."
                    @page="changePage"
                    @refresh="refresh"
                >
                    <template #filters>
                        <ListControls
                            :sort="filters.sort"
                            :per-page="filters.perPage"
                            :directions="page.props.sortDirections"
                            :disabled="loading"
                            @sort="changeSort"
                            @per-page="changePerPage"
                        />
                    </template>
                    <div class="divide-y"><GenerationItem v-for="prompt in page.props.prompts.data" :key="prompt.id" :prompt="prompt" /></div>
                </ListPanel>
            </section>

            <section
                class="flex flex-col items-start justify-between gap-4 rounded-xl border p-5 sm:flex-row sm:items-center"
                aria-labelledby="delete-project-heading"
            >
                <div>
                    <h2 id="delete-project-heading" class="text-sm font-semibold">Delete project</h2>
                    <p class="text-muted-foreground mt-1 max-w-prose text-sm">
                        {{
                            page.props.project.can_delete
                                ? 'This project is empty and can be permanently removed.'
                                : 'Projects with generations or publishing channels cannot be deleted.'
                        }}
                    </p>
                </div>
                <Dialog v-model:open="deleteOpen">
                    <DialogTrigger as-child
                        ><Button variant="destructive" class="h-11 shrink-0" :disabled="!page.props.project.can_delete" @click="deleteError = ''"
                            ><Trash2 class="size-4" aria-hidden="true" />Delete project</Button
                        ></DialogTrigger
                    >
                    <DialogContent class="max-h-[calc(100dvh-2rem)] overflow-y-auto">
                        <form class="min-w-0 space-y-6" @submit.prevent="destroyProject">
                            <DialogHeader
                                ><DialogTitle class="pr-4 leading-snug break-words">Delete {{ page.props.project.name }}?</DialogTitle
                                ><DialogDescription
                                    >This empty project will be permanently removed. This action cannot be undone.</DialogDescription
                                ></DialogHeader
                            >
                            <p v-if="deleteError" role="alert" class="text-destructive text-sm">{{ deleteError }}</p>
                            <DialogFooter class="gap-2">
                                <DialogClose as-child
                                    ><Button type="button" variant="outline" class="h-11" :disabled="deleteForm.processing"
                                        >Cancel</Button
                                    ></DialogClose
                                >
                                <Button type="submit" variant="destructive" class="h-11" :disabled="deleteForm.processing"
                                    ><LoaderCircle v-if="deleteForm.processing" class="size-4 motion-safe:animate-spin" aria-hidden="true" />{{
                                        deleteForm.processing ? 'Deleting…' : 'Delete project'
                                    }}</Button
                                >
                            </DialogFooter>
                        </form>
                    </DialogContent>
                </Dialog>
            </section>
        </div>
    </AppLayout>
</template>
