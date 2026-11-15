<script setup lang="ts">
import AssemblyCard from '@/components/episodes/AssemblyCard.vue';
import ShotCard from '@/components/episodes/ShotCard.vue';
import SceneScriptEditor from '@/components/episodes/SceneScriptEditor.vue';
import Heading from '@/components/Heading.vue';
import InputError from '@/components/InputError.vue';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import AppLayout from '@/layouts/AppLayout.vue';
import type { EpisodeWorkspaceProps, Screenplay } from '@/types/episodes';
import { Head, Link, router, useForm } from '@inertiajs/vue3';
import { RefreshCw } from '@lucide/vue';
import { computed, ref, watch } from 'vue';
import { useRoute } from 'ziggy-js';

const props = defineProps<EpisodeWorkspaceProps>();
const route = useRoute();
const params = { series: props.series.id, episode: props.episode.id };
const breadcrumbs = [
    { title: 'Projects', href: route('projects.index') },
    { title: props.project.name, href: route('projects.show', props.project.id) },
    { title: props.episode.title, href: route('episodes.show', params) },
];
const revision = computed(() => props.episode.current_revision);
const shots = computed(() => revision.value?.scenes.flatMap((scene) => scene.shots) ?? []);
const archived = computed(() => !!props.series.archived_at);
const storyContext = computed(() => revision.value?.context ?? props.sourceAssembly?.continuity_snapshot);
const initialScript: Screenplay = props.screenplay ?? {
    title: props.episode.title,
    objective: '',
    character_revision_ids: props.characters.length ? [props.characters[0].id] : [],
    scenes: [
        {
            title: '',
            location: '',
            time: '',
            objective: '',
            initial_state: '',
            expected_final_state: '',
            is_closed: false,
            shots: [
                {
                    duration_ms: 5000,
                    action: '',
                    camera: 'Medium shot',
                    transition_mode: 'new_scene',
                    dialogue: [],
                    source_prompt_id: null,
                    source_assembly_id: null,
                    source_time_ms: 0,
                },
            ],
        },
    ],
};
const initialScriptJson = JSON.stringify(initialScript, null, 2);
const scriptText = ref(initialScriptJson);
const jsonError = ref('');
const scriptForm = useForm({ script: initialScript, lock_version: props.episode.lock_version });
const generateForm = useForm({ brief: '', lock_version: props.episode.lock_version, request_key: props.requestKey });
const approveForm = useForm({});
const assemblyForm = useForm({
    shots: shots.value.map((shot) => ({
        prompt_id: shot.takes.find((take) => take.generated)?.id ?? 0,
        trim_start_ms: 0,
        trim_end_ms: shot.duration_ms,
    })),
});
const scriptErrors = computed(() => Object.values(scriptForm.errors).join(' '));
const editorErrors = computed<Record<string, string | undefined>>(() => scriptForm.errors);
const continuationSources = computed(() => [
    ...(props.sourceAssembly
        ? [
              {
                  kind: 'assembly' as const,
                  id: props.sourceAssembly.id,
                  label: 'End of the previous accepted episode',
                  scene_position: 0,
                  shot_position: 0,
              },
          ]
        : []),
    ...(revision.value?.scenes.flatMap((scene) =>
        scene.shots.flatMap((shot) =>
            shot.takes
                .filter((take) => take.generated)
                .map((take) => ({
                    kind: 'take' as const,
                    id: take.id,
                    label: scene.title + ' · shot ' + shot.position + ' · take ' + take.take_number,
                    scene_position: scene.position,
                    shot_position: shot.position,
                })),
        ),
    ) ?? []),
]);
const generateErrors = computed(() => Object.values(generateForm.errors).join(' '));
const approveErrors = computed(() => Object.values(approveForm.errors).join(' '));
const assemblyErrors = computed(() => Object.values(assemblyForm.errors).join(' '));
const editorStale = computed(() => scriptForm.lock_version !== props.episode.lock_version);
watch(
    () => props.screenplay,
    (screenplay) => {
        if (!screenplay || scriptForm.isDirty || scriptForm.processing || scriptText.value !== JSON.stringify(scriptForm.script, null, 2)) return;
        const script = JSON.parse(JSON.stringify(screenplay)) as Screenplay;
        scriptForm.defaults({ script, lock_version: props.episode.lock_version });
        scriptForm.reset();
        scriptText.value = JSON.stringify(script, null, 2);
    },
);
watch(
    () => props.episode.current_revision_id,
    () => {
        assemblyForm.shots = shots.value.map((shot) => ({ prompt_id: 0, trim_start_ms: 0, trim_end_ms: shot.duration_ms }));
    },
);
const allAssemblies = computed(() =>
    props.episode.accepted_assembly && !props.assemblies.some((item) => item.id === props.episode.accepted_assembly?.id)
        ? [props.episode.accepted_assembly, ...props.assemblies]
        : props.assemblies,
);

function saveScript() {
    scriptForm.transform((data) => data).post(route('episodes.scripts.store', params), { preserveState: 'errors' });
}
function importScript() {
    jsonError.value = '';
    let parsed: unknown;
    try {
        parsed = JSON.parse(scriptText.value);
        if (typeof parsed !== 'object' || parsed === null || Array.isArray(parsed)) throw new Error('Screenplay must be an object.');
    } catch {
        jsonError.value = 'Enter a valid screenplay JSON object before importing.';
        return;
    }
    scriptForm
        .transform((data) => ({ ...data, script: parsed as Screenplay }))
        .post(route('episodes.scripts.store', params), {
            preserveState: 'errors',
            onFinish: () => {
                scriptForm.transform((data) => data);
            },
        });
}
function readySourcesFor(shotId: number) {
    const scene = revision.value?.scenes.find((item) => item.shots.some((shot) => shot.id === shotId));
    const shot = scene?.shots.find((item) => item.id === shotId);
    if (!scene || !shot) return [];
    return continuationSources.value.filter(
        (source) =>
            source.kind === 'take' &&
            (source.scene_position < scene.position || (source.scene_position === scene.position && source.shot_position < shot.position)),
    );
}
function generateScript() {
    generateForm.post(route('episodes.scripts.generate', params), { preserveState: 'errors' });
}
function approveScript() {
    if (revision.value)
        approveForm.post(route('episodes.scripts.approve', { ...params, revision: revision.value.id }), {
            preserveScroll: true,
            preserveState: 'errors',
        });
}
function buildAssembly() {
    if (revision.value)
        assemblyForm.post(route('episodes.assemblies.store', { ...params, revision: revision.value.id }), {
            preserveScroll: true,
            preserveState: 'errors',
        });
}
function refresh() {
    if (scriptForm.isDirty && !window.confirm('Reload the current episode and discard unsaved screenplay edits?')) return;
    router.get(route('episodes.show', params), {}, { preserveState: false, replace: true });
}
</script>

<template>
    <AppLayout :breadcrumbs="breadcrumbs">
        <Head :title="episode.title" />
        <div class="workspace-page max-w-6xl">
            <div class="flex flex-wrap items-start justify-between gap-4">
                <Heading
                    :title="episode.number + '. ' + episode.title"
                    :description="series.name + ' · Prepare a screenplay, generate shots and accept a finished episode.'"
                /><Button variant="outline" class="h-11" @click="refresh"><RefreshCw class="size-4" aria-hidden="true" />Refresh results</Button>
            </div>
            <p v-if="archived" class="bg-muted text-muted-foreground rounded-lg border p-4 text-sm">
                The series is archived. Previous scripts, takes and montages remain available.
            </p>
            <p v-if="!project.is_active" role="status" class="bg-muted text-muted-foreground rounded-lg border p-4 text-sm">
                This project is disabled. Enable it in project settings to generate or publish content. Existing history remains available.
            </p>
            <p
                v-if="episode.validity === 'requires_review'"
                role="alert"
                class="border-destructive/30 text-destructive rounded-lg border p-4 text-sm"
            >
                An earlier accepted episode changed. Review the source and save a new screenplay before continuing or publishing this episode.
            </p>
            <details class="bg-card space-y-4 rounded-xl border p-5 sm:p-7">
                <summary class="cursor-pointer font-semibold">
                    {{ revision ? 'Story context saved with this screenplay' : 'Story context for the next episode' }}
                </summary>
                <p v-if="revision" class="text-muted-foreground text-xs">
                    Series brief version {{ revision.context.specification_version ?? '—' }} · story version {{ revision.base_continuity_version }}
                </p>
                <dl v-if="storyContext?.confirmed_events || storyContext?.character_state || storyContext?.open_threads" class="grid gap-4 text-sm">
                    <div v-if="storyContext.confirmed_events" class="space-y-1">
                        <dt class="font-medium">What has happened</dt>
                        <dd class="text-muted-foreground whitespace-pre-wrap">{{ storyContext.confirmed_events }}</dd>
                    </div>
                    <div v-if="storyContext.character_state" class="space-y-1">
                        <dt class="font-medium">Where the characters stand</dt>
                        <dd class="text-muted-foreground whitespace-pre-wrap">{{ storyContext.character_state }}</dd>
                    </div>
                    <div v-if="storyContext.open_threads" class="space-y-1">
                        <dt class="font-medium">Unresolved story threads</dt>
                        <dd class="text-muted-foreground whitespace-pre-wrap">{{ storyContext.open_threads }}</dd>
                    </div>
                </dl>
                <p v-else class="text-muted-foreground text-sm">The first episode starts from the series brief and approved character appearances.</p>
                <p v-if="storyContext?.scene_closed !== undefined && storyContext?.scene_closed !== null" class="text-muted-foreground text-sm">
                    {{
                        storyContext.scene_closed
                            ? 'The previous scene has finished. Begin a new scene.'
                            : 'The previous scene is open and can continue from its final frames.'
                    }}
                </p>
            </details>
            <section v-if="!archived" class="bg-card space-y-4 rounded-xl border p-5 sm:p-7" aria-labelledby="script-generation-title">
                <h2 id="script-generation-title" class="text-lg font-semibold">Prepare the screenplay</h2>
                <form class="grid gap-3" @submit.prevent="generateScript">
                    <Label for="script-brief">Describe what should happen next</Label
                    ><textarea
                        id="script-brief"
                        v-model="generateForm.brief"
                        required
                        maxlength="5000"
                        rows="3"
                        class="border-input bg-background focus-visible:ring-ring/50 resize-y rounded-md border p-3 text-sm outline-none focus-visible:ring-2"
                        placeholder="Continue the open scene, or describe where the next scene starts."
                    /><InputError :message="generateErrors" />
                    <div>
                        <Button
                            type="submit"
                            class="h-11"
                            :disabled="
                                !project.is_active ||
                                generateForm.processing ||
                                ['queued', 'running'].includes(episode.script_generation_status ?? '') ||
                                !characters.length
                            "
                            >Generate screenplay draft</Button
                        >
                    </div>
                </form>
                <p v-if="episode.script_generation_status" class="text-muted-foreground text-sm" role="status">
                    Screenplay request: {{ episode.script_generation_status.replaceAll('_', ' ') }}
                </p>
                <p v-if="episode.script_generation_error" class="text-destructive text-sm">{{ episode.script_generation_error }}</p>
                <p v-if="!characters.length" class="text-muted-foreground text-sm">
                    Approve at least one character appearance before writing an episode.
                    <Link :href="route('projects.show', project.id)" class="text-primary underline">Manage characters</Link>
                </p>
            </section>
            <section class="bg-card space-y-4 rounded-xl border p-5 sm:p-7" aria-labelledby="script-editor-title">
                <div class="flex flex-wrap items-center justify-between gap-3">
                    <h2 id="script-editor-title" class="text-lg font-semibold">Screenplay editor</h2>
                    <span v-if="revision" class="text-muted-foreground text-sm">Version {{ revision.number }} · {{ revision.status }}</span>
                </div>
                <p class="text-muted-foreground text-sm">
                    Choose characters, arrange scenes and describe each shot. Saving creates a new version and keeps previous scripts and takes.
                </p>
                <p v-if="editorStale" role="alert" class="text-destructive text-sm">
                    A newer episode version is available. Refresh before saving; export your unsaved draft from the advanced tools if you need to keep
                    it.
                </p>
                <form class="grid gap-5" @submit.prevent="saveScript">
                    <SceneScriptEditor
                        v-model="scriptForm.script"
                        :characters="characters"
                        :transitions="transitionModes"
                        :sources="continuationSources"
                        :errors="editorErrors"
                        :disabled="archived || scriptForm.processing || editorStale"
                    />
                    <InputError :message="scriptErrors" />
                    <div class="flex flex-wrap gap-3">
                        <Button type="submit" class="h-11" :disabled="archived || scriptForm.processing || editorStale || !characters.length"
                            >Save as new draft version</Button
                        ><Button
                            v-if="revision?.status === 'draft'"
                            type="button"
                            variant="outline"
                            class="h-11"
                            :disabled="archived || approveForm.processing || scriptForm.isDirty"
                            @click="approveScript"
                            >Approve reviewed version {{ revision.number }}</Button
                        >
                    </div>
                    <InputError :message="approveErrors" />
                </form>
                <details class="space-y-4 rounded-lg border p-4">
                    <summary class="cursor-pointer text-sm font-medium">Advanced: screenplay JSON import and export</summary>
                    <p class="text-muted-foreground mt-3 text-xs">
                        Use these tools to exchange a structured screenplay. The scene editor above is the current draft. Importing creates a new
                        version after server validation.
                    </p>
                    <Button type="button" variant="outline" class="h-11" @click="scriptText = JSON.stringify(scriptForm.script, null, 2)"
                        >Export current draft to JSON</Button
                    ><Label for="screenplay-json">Screenplay JSON</Label
                    ><textarea
                        id="screenplay-json"
                        v-model="scriptText"
                        rows="14"
                        spellcheck="false"
                        :disabled="archived || scriptForm.processing"
                        class="border-input bg-background focus-visible:ring-ring/50 mt-2 w-full resize-y rounded-md border p-4 font-mono text-xs leading-relaxed outline-none focus-visible:ring-2"
                    />
                    <p class="text-muted-foreground text-xs">
                        For imports, source_time_ms: 0 uses the final frame; a positive value selects a time in milliseconds.
                    </p>
                    <InputError :message="jsonError" /><Button
                        type="button"
                        variant="outline"
                        class="h-11"
                        :disabled="archived || scriptForm.processing || editorStale"
                        @click="importScript"
                        >Import JSON as a new draft version</Button
                    >
                </details>
            </section>
            <section v-if="revision" class="space-y-5" aria-labelledby="episode-shots-title">
                <h2 id="episode-shots-title" class="text-lg font-semibold">Scenes and shots</h2>
                <p v-if="revision.status !== 'approved'" class="text-muted-foreground text-sm">
                    Review and approve the screenplay before starting media generation.
                </p>
                <section v-for="scene in revision.scenes" :key="scene.id" class="space-y-4">
                    <div>
                        <h3 class="font-semibold">{{ scene.position }}. {{ scene.title }}</h3>
                        <p class="text-muted-foreground mt-1 text-xs">Planned ending: {{ scene.is_closed ? 'scene closed' : 'scene open' }}</p>
                    </div>
                    <ShotCard
                        v-for="shot in scene.shots"
                        :key="shot.id"
                        :shot="shot"
                        :params="params"
                        :revision-id="revision.id"
                        :approved="revision.status === 'approved'"
                        :archived="archived"
                        :project-enabled="project.is_active"
                        :request-key="requestKey"
                        :source-options="readySourcesFor(shot.id)"
                    />
                </section>
            </section>
            <section
                v-if="revision?.status === 'approved' && !archived"
                class="bg-card space-y-4 rounded-xl border p-5 sm:p-7"
                aria-labelledby="montage-builder-title"
            >
                <h2 id="montage-builder-title" class="text-lg font-semibold">Choose takes and build a montage</h2>
                <form class="space-y-4" @submit.prevent="buildAssembly">
                    <div v-for="(shot, index) in shots" :key="shot.id" class="grid items-end gap-3 rounded-lg border p-4 sm:grid-cols-3">
                        <div class="grid gap-2">
                            <Label :for="'take-' + shot.id">Shot {{ index + 1 }} · ready take</Label
                            ><select
                                :id="'take-' + shot.id"
                                v-model="assemblyForm.shots[index].prompt_id"
                                required
                                class="border-input bg-background h-11 rounded-md border px-3 text-sm"
                            >
                                <option :value="0" disabled>Choose a ready take</option>
                                <option v-for="take in shot.takes.filter((item) => item.generated)" :key="take.id" :value="take.id">
                                    Take {{ take.take_number }}
                                </option>
                            </select>
                        </div>
                        <div class="grid gap-2">
                            <Label :for="'trim-start-' + shot.id">Start in milliseconds</Label
                            ><Input :id="'trim-start-' + shot.id" v-model="assemblyForm.shots[index].trim_start_ms" type="number" min="0" />
                        </div>
                        <div class="grid gap-2">
                            <Label :for="'trim-end-' + shot.id">End in milliseconds</Label
                            ><Input
                                :id="'trim-end-' + shot.id"
                                v-model="assemblyForm.shots[index].trim_end_ms"
                                type="number"
                                min="1"
                                :max="shot.duration_ms"
                            />
                        </div>
                    </div>
                    <InputError :message="assemblyErrors" /><Button
                        type="submit"
                        class="h-11"
                        :disabled="!project.is_active || assemblyForm.processing || assemblyForm.shots.some((item) => !item.prompt_id)"
                        >Build selected montage</Button
                    >
                </form>
            </section>
            <section class="space-y-5" aria-labelledby="episode-montages-title">
                <h2 id="episode-montages-title" class="text-lg font-semibold">Montages and publication</h2>
                <p v-if="!allAssemblies.length" class="text-muted-foreground text-sm">Finished montages will appear here for review.</p>
                <AssemblyCard
                    v-for="assembly in allAssemblies"
                    :key="assembly.id + ':' + assembly.lock_version"
                    :assembly="assembly"
                    :params="params"
                    :channels="channels.filter((channel) => ['youtube', 'instagram'].includes(channel.type))"
                    :publications="publications.filter((publication) => publication.episode_assembly_id === assembly.id)"
                    :current-accepted="episode.accepted_assembly_id === assembly.id && episode.validity === 'current'"
                    :archived="archived"
                    :project-enabled="project.is_active"
                />
            </section>
        </div>
    </AppLayout>
</template>
