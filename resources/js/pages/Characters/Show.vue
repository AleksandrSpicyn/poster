<script setup lang="ts">
import CharacterRevisionCard from '@/components/characters/CharacterRevisionCard.vue';
import Heading from '@/components/Heading.vue';
import InputError from '@/components/InputError.vue';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import AppLayout from '@/layouts/AppLayout.vue';
import type { PaginatedResource } from '@/types/content';
import type { CharacterRevision, CharacterSummary, SeriesSummary } from '@/types/series';
import { Head, Link, router, useForm } from '@inertiajs/vue3';
import { computed } from 'vue';
import { useRoute } from 'ziggy-js';

const props = defineProps<{
    project: { id: number; name: string };
    series: SeriesSummary;
    character: CharacterSummary;
    revisions: PaginatedResource<CharacterRevision>;
    referenceViews: Record<string, string>;
}>();
const route = useRoute();
const params = { project: props.project.id, series: props.series.id, character: props.character.id };
const archived = !!props.character.archived_at || !!props.series.archived_at;
const nameForm = useForm({ name: props.character.name, lock_version: props.character.lock_version });
const revisionForm = useForm({ description: '', appearance: { notes: '' }, voice: { description: '' } });
const revisionErrors = computed<Record<string, string | undefined>>(() => revisionForm.errors);
const archiveForm = useForm({ lock_version: props.character.lock_version });
const breadcrumbs = [
    { title: 'Projects', href: route('projects.index') },
    { title: props.project.name, href: route('projects.show', props.project.id) },
    { title: props.character.name, href: route('characters.show', params) },
];
function saveName() {
    nameForm.patch(route('characters.update', params), { preserveState: 'errors' });
}
function createRevision() {
    revisionForm.post(route('characters.revisions.store', params), { preserveState: 'errors' });
}
function archive() {
    if (window.confirm('Archive this character? Previous appearance versions and episode history will remain.'))
        archiveForm.post(route('characters.archive', params), { preserveState: 'errors' });
}
function changePage(page: number) {
    router.get(route('characters.show', params), { page }, { preserveScroll: true });
}
</script>

<template>
    <AppLayout :breadcrumbs="breadcrumbs">
        <Head :title="character.name" />
        <div class="workspace-page max-w-6xl">
            <Heading
                :title="character.name"
                :description="
                    character.current_revision
                        ? 'Current approved appearance: version ' + character.current_revision.number
                        : 'Create a version, add reference views and approve the appearance.'
                "
            />
            <p v-if="archived" class="bg-muted text-muted-foreground rounded-lg border p-4 text-sm">
                This character or series is archived. Previous versions remain available.
            </p>
            <form v-if="!archived" class="bg-card flex flex-wrap items-end gap-3 rounded-xl border p-5" @submit.prevent="saveName">
                <div class="grid min-w-48 flex-1 gap-2">
                    <Label for="character-edit-name">Character name</Label
                    ><Input id="character-edit-name" v-model="nameForm.name" required maxlength="255" :disabled="nameForm.processing" /><InputError
                        :message="nameForm.errors.name"
                    /><InputError :message="nameForm.errors.lock_version" />
                </div>
                <Button type="submit" variant="outline" class="h-11" :disabled="nameForm.processing">Save name</Button>
            </form>
            <section v-if="!archived" class="bg-card space-y-4 rounded-xl border p-5 sm:p-7">
                <h2 class="font-semibold">Create an appearance version</h2>
                <p class="text-muted-foreground text-sm">
                    Describe the identity first, then add the reference views. Approved versions remain attached to the episodes that used them.
                </p>
                <form class="grid gap-4" @submit.prevent="createRevision">
                    <div class="grid gap-2">
                        <Label for="new-revision-description">Appearance and personality</Label
                        ><textarea
                            id="new-revision-description"
                            v-model="revisionForm.description"
                            required
                            maxlength="10000"
                            rows="4"
                            :disabled="revisionForm.processing"
                            class="border-input bg-background focus-visible:ring-ring/50 resize-y rounded-md border p-3 text-sm leading-relaxed outline-none focus-visible:ring-2"
                        /><InputError :message="revisionForm.errors.description" /><InputError :message="revisionErrors.character" />
                    </div>
                    <div class="grid gap-4 sm:grid-cols-2">
                        <div class="grid gap-2">
                            <Label for="new-revision-appearance">Visual details</Label
                            ><Input
                                id="new-revision-appearance"
                                v-model="revisionForm.appearance.notes"
                                placeholder="Clothes, face, body proportions"
                            />
                        </div>
                        <div class="grid gap-2">
                            <Label for="new-revision-voice">Voice and delivery</Label
                            ><Input id="new-revision-voice" v-model="revisionForm.voice.description" placeholder="Tone, accent, pace" />
                        </div>
                    </div>
                    <div><Button type="submit" class="h-11" :disabled="revisionForm.processing">Create draft version</Button></div>
                </form>
            </section>
            <div class="space-y-6">
                <CharacterRevisionCard
                    v-for="revision in revisions.data"
                    :key="revision.id + ':' + revision.lock_version"
                    :revision="revision"
                    :params="params"
                    :reference-views="referenceViews"
                    :archived="archived"
                />
                <p v-if="!revisions.data.length" class="text-muted-foreground p-5 text-sm">No appearance versions yet.</p>
            </div>
            <nav v-if="revisions.meta.last_page > 1" class="flex flex-wrap items-center justify-between gap-3" aria-label="Appearance version pages">
                <Button
                    variant="outline"
                    class="h-11"
                    :disabled="revisions.meta.current_page <= 1"
                    @click="changePage(revisions.meta.current_page - 1)"
                    >Previous versions</Button
                >
                <p class="text-muted-foreground text-sm">Page {{ revisions.meta.current_page }} of {{ revisions.meta.last_page }}</p>
                <Button
                    variant="outline"
                    class="h-11"
                    :disabled="revisions.meta.current_page >= revisions.meta.last_page"
                    @click="changePage(revisions.meta.current_page + 1)"
                    >More versions</Button
                >
            </nav>
            <form v-if="!archived" class="flex flex-wrap items-center justify-between gap-4 border-t pt-5" @submit.prevent="archive">
                <div>
                    <p class="text-muted-foreground text-sm">Keep previous versions and remove this character from future casting.</p>
                    <InputError :message="archiveForm.errors.lock_version" />
                </div>
                <Button type="submit" variant="outline" class="h-11" :disabled="archiveForm.processing">Archive character</Button>
            </form>
            <p v-if="series.automation_enabled" class="bg-muted text-muted-foreground rounded-lg border p-4 text-sm leading-relaxed">
                Upload all reference views before leaving. Automation approves the latest appearance after two minutes without changes, then continues
                the episode.
            </p>
            <Button as-child variant="outline" class="h-11 self-start"
                ><Link :href="route('projects.show', project.id)">Back to project</Link></Button
            >
        </div>
    </AppLayout>
</template>
