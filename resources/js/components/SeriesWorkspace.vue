<script setup lang="ts">
import InputError from '@/components/InputError.vue';
import ListPanel from '@/components/ListPanel.vue';
import ProductionPanel from '@/components/ProductionPanel.vue';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import type { PaginatedResource } from '@/types/content';
import type { CharacterSummary, EpisodeSummary, SeriesSummary } from '@/types/series';
import { Link, router, useForm } from '@inertiajs/vue3';
import { Plus } from '@lucide/vue';
import { computed } from 'vue';
import { useRoute } from 'ziggy-js';

const props = defineProps<{
    project: { id: number; name: string; is_active: boolean };
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
    router.get(route('projects.show', props.project.id), { page }, { preserveScroll: true });
}
</script>

<template>
    <div class="space-y-6">
        <p v-if="series.archived_at" class="bg-muted text-muted-foreground rounded-lg border p-4 text-sm">
            This series is archived. Its history remains available.
        </p>
        <ProductionPanel
            :project-id="project.id"
            :series-id="series.id"
            :enabled="series.automation_enabled"
            :project-enabled="project.is_active"
            :archived="!!series.archived_at"
            :run="series.latest_production"
        />
        <section v-if="series.cycle_plan" class="bg-card space-y-3 rounded-xl border p-5" aria-label="Complete video plan">
            <h2 class="text-lg font-semibold">Complete video plan</h2>
            <p>{{ series.cycle_plan.objective }}</p>
            <ol class="space-y-2">
                <li v-for="part in series.cycle_plan.parts" :key="part.number" class="text-sm"><strong>Part {{ part.number }}:</strong> {{ part.objective }} — {{ part.expected_result }}</li>
            </ol>
        </section>
        <section class="space-y-4" aria-labelledby="series-characters">
            <h2 id="series-characters" class="text-lg font-semibold">Characters</h2>
            <form v-if="!series.archived_at" class="bg-card flex flex-wrap items-end gap-3 rounded-xl border p-5" @submit.prevent="createCharacter">
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
                    <article v-for="character in characters.data" :key="character.id" class="flex flex-wrap items-center justify-between gap-4 p-5">
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
                    ><Input id="episode-title" v-model="episodeForm.title" required maxlength="255" :disabled="episodeForm.processing" /><InputError
                        :message="episodeForm.errors.title"
                    />
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
</template>
