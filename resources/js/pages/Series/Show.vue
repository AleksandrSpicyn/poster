<script setup lang="ts">
import Heading from '@/components/Heading.vue';
import InputError from '@/components/InputError.vue';
import ListPanel from '@/components/ListPanel.vue';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import AppLayout from '@/layouts/AppLayout.vue';
import type { PaginatedResource } from '@/types/content';
import type { CharacterSummary, EpisodeSummary, SeriesSummary } from '@/types/series';
import { Head, Link, router, useForm } from '@inertiajs/vue3';
import { Pencil, Plus } from '@lucide/vue';
import { computed } from 'vue';
import { useRoute } from 'ziggy-js';

const props = defineProps<{
    project: { id: number; name: string };
    series: SeriesSummary;
    characters: PaginatedResource<CharacterSummary>;
    episodes: EpisodeSummary[];
}>();
const route = useRoute();
const params = { project: props.project.id, series: props.series.id };
const characterForm = useForm({ name: '' });
const characterErrors = computed<Record<string, string | undefined>>(() => characterForm.errors);
const episodeForm = useForm({ title: '' });
const archiveForm = useForm({ lock_version: props.series.lock_version });
const breadcrumbs = [
    { title: 'Series', href: route('series.index') },
    { title: props.series.name, href: route('series.show', params) },
];

function createCharacter() {
    characterForm.post(route('characters.store', params), { preserveState: 'errors' });
}
function createEpisode() {
    episodeForm.post(route('episodes.store', { series: props.series.id }), { preserveState: 'errors' });
}
function archive() {
    if (window.confirm('Archive this series? Its characters, episodes and history will stay available.'))
        archiveForm.post(route('series.archive', params), { preserveState: 'errors' });
}
function changePage(page: number) {
    router.get(route('series.show', params), { page }, { preserveScroll: true });
}
</script>

<template>
    <AppLayout :breadcrumbs="breadcrumbs">
        <Head :title="series.name" />
        <div class="workspace-page">
            <div class="flex flex-wrap items-start justify-between gap-4">
                <Heading :title="series.name" :description="project.name + ' · Story world version ' + series.specification_version" />
                <Button v-if="!series.archived_at" as-child variant="outline" class="h-11"
                    ><Link :href="route('series.edit', params)"><Pencil class="size-4" aria-hidden="true" />Edit series</Link></Button
                >
            </div>
            <p v-if="series.archived_at" class="bg-muted text-muted-foreground rounded-lg border p-4 text-sm">
                This series is archived. Its history remains available.
            </p>
            <section class="bg-card grid gap-5 rounded-xl border p-5 sm:p-7">
                <div>
                    <h2 class="font-semibold">World and premise</h2>
                    <p class="text-muted-foreground mt-2 text-sm leading-relaxed whitespace-pre-wrap">{{ series.specification.premise }}</p>
                </div>
                <dl class="grid grid-cols-2 gap-4 text-sm sm:grid-cols-4">
                    <div>
                        <dt class="text-muted-foreground">Style</dt>
                        <dd>{{ series.specification.visual_style }}</dd>
                    </div>
                    <div>
                        <dt class="text-muted-foreground">Audience</dt>
                        <dd>{{ series.specification.audience }}</dd>
                    </div>
                    <div>
                        <dt class="text-muted-foreground">Language</dt>
                        <dd>{{ series.specification.language }}</dd>
                    </div>
                    <div>
                        <dt class="text-muted-foreground">Episode length</dt>
                        <dd>{{ series.specification.episode_duration_seconds }} seconds</dd>
                    </div>
                </dl>
                <div v-if="series.specification.narrative_rules">
                    <h3 class="text-sm font-semibold">Storytelling rules</h3>
                    <p class="text-muted-foreground mt-2 text-sm leading-relaxed whitespace-pre-wrap">{{ series.specification.narrative_rules }}</p>
                </div>
            </section>
            <section class="space-y-4" aria-labelledby="series-characters">
                <h2 id="series-characters" class="text-lg font-semibold">Characters</h2>
                <form
                    v-if="!series.archived_at"
                    class="bg-card flex flex-wrap items-end gap-3 rounded-xl border p-5"
                    @submit.prevent="createCharacter"
                >
                    <div class="grid min-w-48 flex-1 gap-2">
                        <Label for="character-name">New character name</Label
                        ><Input
                            id="character-name"
                            v-model="characterForm.name"
                            required
                            maxlength="255"
                            :disabled="characterForm.processing"
                            :aria-invalid="!!characterForm.errors.name"
                        /><InputError :message="characterForm.errors.name" /><InputError :message="characterErrors.series" />
                    </div>
                    <Button type="submit" class="h-11" :disabled="characterForm.processing"
                        ><Plus class="size-4" aria-hidden="true" />Add character</Button
                    >
                </form>
                <ListPanel
                    :meta="characters.meta"
                    :count="characters.data.length"
                    :loading="false"
                    error=""
                    empty-title="No characters yet"
                    empty-description="Create a character, describe their appearance and upload reference views."
                    @page="changePage"
                    @refresh="router.reload()"
                >
                    <template #filters><p class="text-muted-foreground text-sm">Stable identities and approved appearance versions</p></template>
                    <div class="divide-y">
                        <article
                            v-for="character in characters.data"
                            :key="character.id"
                            class="flex flex-wrap items-center justify-between gap-4 p-5"
                        >
                            <div>
                                <h3 class="font-semibold">{{ character.name }}</h3>
                                <p class="text-muted-foreground mt-1 text-sm">
                                    {{
                                        character.current_revision
                                            ? 'Approved appearance v' + character.current_revision.number
                                            : 'No approved appearance yet'
                                    }}<span v-if="character.archived_at"> · Archived</span>
                                </p>
                            </div>
                            <Button as-child variant="outline" class="h-11"
                                ><Link :href="route('characters.show', { ...params, character: character.id })">Manage character</Link></Button
                            >
                        </article>
                    </div>
                </ListPanel>
            </section>
            <section class="space-y-4" aria-labelledby="series-episodes">
                <div class="flex flex-wrap items-center justify-between gap-3">
                    <h2 id="series-episodes" class="text-lg font-semibold">Episodes</h2>
                    <p class="text-muted-foreground text-sm">Latest 20 of {{ series.episodes_count ?? episodes.length }}</p>
                </div>
                <form v-if="!series.archived_at" class="bg-card flex flex-wrap items-end gap-3 rounded-xl border p-5" @submit.prevent="createEpisode">
                    <div class="grid min-w-48 flex-1 gap-2">
                        <Label for="episode-title">New episode title</Label
                        ><Input
                            id="episode-title"
                            v-model="episodeForm.title"
                            required
                            maxlength="255"
                            :disabled="episodeForm.processing"
                        /><InputError :message="episodeForm.errors.title" />
                    </div>
                    <Button type="submit" class="h-11" :disabled="episodeForm.processing"
                        ><Plus class="size-4" aria-hidden="true" />Create episode</Button
                    >
                </form>
                <div class="bg-card divide-y rounded-xl border">
                    <p v-if="!episodes.length" class="text-muted-foreground p-6 text-sm">Create the first episode after defining your characters.</p>
                    <article v-for="episode in episodes" :key="episode.id" class="flex flex-wrap items-center justify-between gap-4 p-5">
                        <div>
                            <h3 class="font-semibold">{{ episode.number }}. {{ episode.title }}</h3>
                            <p v-if="episode.validity === 'requires_review'" class="text-destructive mt-1 text-sm">
                                Source changed — review this continuation
                            </p>
                        </div>
                        <Button as-child variant="outline" class="h-11"
                            ><Link :href="route('episodes.show', { series: series.id, episode: episode.id })">Open episode</Link></Button
                        >
                    </article>
                </div>
            </section>
            <form v-if="!series.archived_at" class="flex flex-wrap items-center justify-between gap-4 border-t pt-5" @submit.prevent="archive">
                <div>
                    <p class="text-muted-foreground text-sm">Archiving keeps characters, episodes and previous publications.</p>
                    <InputError :message="archiveForm.errors.lock_version" />
                </div>
                <Button type="submit" variant="outline" class="h-11" :disabled="archiveForm.processing">Archive series</Button>
            </form>
        </div>
    </AppLayout>
</template>
