<script setup lang="ts">
import ProjectArtwork from '@/components/projects/ProjectArtwork.vue';
import ConfirmationDialog from '@/components/ConfirmationDialog.vue';
import InputError from '@/components/InputError.vue';
import ListPanel from '@/components/ListPanel.vue';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import type { ProjectSummary } from '@/types/projects';
import type { PaginatedResource } from '@/types/content';
import type { CharacterSummary, EpisodeSummary, SeriesSummary } from '@/types/series';
import { Link, router, useForm, usePage } from '@inertiajs/vue3';
import { Ellipsis, Plus } from '@lucide/vue';
import { computed, nextTick, ref } from 'vue';
import { useRoute } from 'ziggy-js';

const props = defineProps<{
    project: Pick<ProjectSummary, 'id' | 'name' | 'is_active' | 'cover'>;
    series: SeriesSummary;
    characters: PaginatedResource<CharacterSummary>;
    episodes: EpisodeSummary[];
    actionsTarget?: string;
}>();
const route = useRoute();
const params = { project: props.project.id, series: props.series.id };
const characterForm = useForm({ name: '', story_role: '' });
const characterErrors = computed<Record<string, string | undefined>>(() => characterForm.errors);
const episodeForm = useForm({ title: '', objective: '' });
const actions = ref<HTMLDetailsElement | null>(null);
const showingPlan = ref(false);
const planHeading = ref<HTMLElement | null>(null);
const adding = ref<'episode' | 'character' | null>(null);
const episodeField = ref<InstanceType<typeof Input> | null>(null);
const characterField = ref<InstanceType<typeof Input> | null>(null);
async function openEntry(kind: 'episode' | 'character') {
    if (actions.value) actions.value.open = false;
    adding.value = kind;
    await nextTick();
    (kind === 'episode' ? episodeField.value : characterField.value)?.focus();
}
function cancelEntry() {
    if (adding.value === 'episode') {
        episodeForm.reset();
        episodeForm.clearErrors();
    } else {
        characterForm.reset();
        characterForm.clearErrors();
    }
    adding.value = null;
    nextTick(() => actions.value?.querySelector('summary')?.focus());
}
const chronologicalEpisodes = computed(() => [...props.episodes].sort((a, b) => a.number - b.number));
function episodeLabel(episode: EpisodeSummary) {
    if (episode.validity === 'requires_review') return 'Нужна проверка';
    if (episode.accepted_assembly_id) return 'Принято';
    if (episode.script_generation_status === 'failed') return 'Ошибка сценария';
    if (episode.script_generation_status === 'outcome_unknown') return 'Результат неизвестен';
    return episode.current_revision_id || episode.script_generation_status ? 'В работе' : 'Черновик';
}
const workspace = usePage();
const withArchived = computed(() => workspace.props.withArchived !== false);
const archiveForm = useForm({ lock_version: props.series.lock_version });

function createCharacter() {
    characterForm.post(route('characters.store', params), { preserveState: 'errors' });
}
function createEpisode() {
    episodeForm.post(route('episodes.store', { series: props.series.id }), { preserveState: 'errors' });
}
function archive() {
    archiveForm.post(route('series.archive', params), { preserveState: 'errors' });
}
function changePage(page: number) {
    const url = route().current('series.show') ? route('series.show', params) : route('projects.show', props.project.id);
    router.get(url, { page, with_archived: Number(withArchived.value) }, { preserveScroll: true });
}
function toggleArchived(event: Event) {
    const checked = (event.target as HTMLInputElement).checked;
    router.get(route('series.show', params), { with_archived: Number(checked) }, { preserveScroll: true, preserveState: true });
}
</script>

<template>
    <div class="space-y-6">
        <p v-if="series.archived_at" class="bg-muted text-muted-foreground rounded-lg border p-4 text-sm">
            Сериал в архиве. История доступна для просмотра.
        </p>
        <Teleport defer :to="actionsTarget || 'body'" :disabled="!actionsTarget">
            <details
                v-if="!series.archived_at || series.cycle_plan"
                ref="actions"
                class="relative w-fit"
                @keydown.esc="
                    actions?.removeAttribute('open');
                    actions?.querySelector('summary')?.focus();
                "
            >
                <summary
                    :class="
                        actionsTarget
                            ? 'flex size-11 cursor-pointer list-none items-center justify-center rounded-md'
                            : 'cursor-pointer rounded-md px-3 py-2 text-sm font-medium'
                    "
                >
                    <Ellipsis v-if="actionsTarget" class="size-5" aria-hidden="true" />
                    <span :class="{ 'sr-only': actionsTarget }">Меню сериала</span>
                </summary>
                <div
                    class="bg-popover text-popover-foreground absolute z-20 mt-1 grid min-w-52 gap-1 rounded-lg border p-2 shadow-md"
                    :class="{ 'max-md:right-0': actionsTarget }"
                >
                    <Button v-if="!series.archived_at" type="button" variant="ghost" @click="openEntry('episode')">Новый эпизод</Button>
                    <Button v-if="!series.archived_at" type="button" variant="ghost" @click="openEntry('character')">Новый персонаж</Button>
                    <Button
                        v-if="series.cycle_plan"
                        type="button"
                        variant="ghost"
                        @click="
                            showingPlan = true;
                            actions?.removeAttribute('open');
                            $nextTick(() => planHeading?.focus());
                        "
                        >План полной версии</Button
                    >
                    <ConfirmationDialog
                        v-if="!series.archived_at"
                        heading="Архивация сериала"
                        title="Архивировать сериал?"
                        description="Новые эпизоды не будут запускаться автоматически."
                        action="Архивировать сериал"
                        :processing="archiveForm.processing"
                        :error="archiveForm.errors.lock_version"
                        @confirm="archive"
                    >
                        <div class="studio-row">
                            <dt>Эпизоды</dt>
                            <dd>{{ series.episodes_count ?? episodes.length }}</dd>
                        </div>
                        <div class="studio-row">
                            <dt>Производство</dt>
                            <dd>{{ series.latest_production?.is_active ? series.latest_production.stage_label : 'Активных запусков нет' }}</dd>
                        </div>
                    </ConfirmationDialog>
                </div>
            </details>
        </Teleport>
        <div class="grid items-start gap-4 md:grid-cols-[min(34%,400px)_minmax(0,1fr)]">
            <ProjectArtwork :name="project.name" :cover="project.cover" class="h-[210px] w-full rounded-xl md:h-[393px]" />
            <section class="min-w-0 [&_h2]:mb-4" aria-labelledby="series-episodes">
                <h2 id="series-episodes" class="text-xl font-semibold">Эпизоды</h2>
                <p v-if="!episodes.length" class="text-muted-foreground text-sm">Добавьте персонажей и создайте первый эпизод.</p>
                <Link
                    v-for="episode in chronologicalEpisodes"
                    :key="episode.id"
                    :href="route('episodes.show', { series: series.id, episode: episode.id })"
                    :aria-label="'Открыть эпизод ' + episode.title"
                    class="studio-history-row"
                >
                    <strong
                        >{{ episode.title
                        }}<small v-if="episode.objective" class="text-muted-foreground block font-normal">{{ episode.objective }}</small></strong
                    >
                    <span
                        :class="episode.accepted_assembly_id && episode.validity !== 'requires_review' ? 'text-primary' : 'text-muted-foreground'"
                        >{{ episodeLabel(episode) }}</span
                    >
                </Link>
                <p v-if="(series.episodes_count ?? 0) > episodes.length" class="text-muted-foreground text-sm">
                    Последние {{ episodes.length }} из {{ series.episodes_count }}
                </p>
            </section>
        </div>

        <form v-if="!series.archived_at && adding === 'episode'" class="studio-panel grid gap-4" @submit.prevent="createEpisode">
            <h2 class="text-xl font-bold">Новый эпизод</h2>
            <div class="grid min-w-48 flex-1 gap-2">
                <Label for="episode-title">Название эпизода</Label
                ><Input
                    id="episode-title"
                    ref="episodeField"
                    v-model="episodeForm.title"
                    required
                    maxlength="255"
                    :disabled="episodeForm.processing"
                /><InputError :message="episodeForm.errors.title" />
            </div>
            <label class="grid gap-2 text-sm"
                >Цель эпизода<Input v-model="episodeForm.objective" maxlength="2000" :disabled="episodeForm.processing"
            /></label>
            <InputError :message="episodeForm.errors.objective" />
            <div class="flex flex-wrap gap-2">
                <Button type="submit" class="h-11" :disabled="episodeForm.processing"
                    ><Plus class="size-4" aria-hidden="true" />Создать эпизод</Button
                >
                <Button type="button" variant="outline" :disabled="episodeForm.processing" @click="cancelEntry">Отмена</Button>
            </div>
        </form>
        <Button as-child variant="outline" class="h-11">
            <Link :href="route('series.show', { ...params, section: 'production' })">Производство</Link>
        </Button>
        <section v-if="showingPlan && series.cycle_plan" class="bg-card space-y-3 rounded-xl border p-5" aria-label="План полной версии">
            <h2 ref="planHeading" tabindex="-1" class="text-lg font-semibold">План полной версии</h2>
            <Button v-if="series.output_mode === 'single_video'" as-child variant="outline"
                ><Link :href="route('series_assemblies.create', series.id)">Параметры сборки</Link></Button
            >
            <p>{{ series.cycle_plan.objective }}</p>
            <ol class="space-y-2">
                <li v-for="part in series.cycle_plan.parts" :key="part.number" class="text-sm">
                    <strong>Эпизод {{ part.number }}.</strong> {{ part.objective }} — {{ part.expected_result }}
                </li>
            </ol>
        </section>
        <section class="space-y-4" aria-labelledby="series-characters">
            <h2 id="series-characters" class="text-lg font-semibold">Персонажи</h2>
            <form v-if="!series.archived_at && adding === 'character'" class="studio-panel grid gap-4" @submit.prevent="createCharacter">
                <h3 class="text-xl font-bold">Новый персонаж</h3>
                <div class="grid min-w-48 flex-1 gap-2">
                    <Label for="character-name">Имя нового персонажа</Label
                    ><Input
                        id="character-name"
                        ref="characterField"
                        v-model="characterForm.name"
                        required
                        maxlength="255"
                        :disabled="characterForm.processing"
                        :aria-invalid="!!characterForm.errors.name"
                    /><InputError :message="characterForm.errors.name" /><InputError :message="characterErrors.series" />
                </div>
                <label class="grid gap-2 text-sm"
                    >Роль в истории<Input v-model="characterForm.story_role" maxlength="2000" :disabled="characterForm.processing"
                /></label>
                <InputError :message="characterForm.errors.story_role" />
                <div class="flex flex-wrap gap-2">
                    <Button type="submit" class="h-11" :disabled="characterForm.processing"
                        ><Plus class="size-4" aria-hidden="true" />Добавить персонажа</Button
                    >
                    <Button type="button" variant="outline" :disabled="characterForm.processing" @click="cancelEntry">Отмена</Button>
                </div>
            </form>
            <label class="inline-flex min-h-11 items-center gap-2 text-sm">
                <input type="checkbox" :checked="withArchived" @change="toggleArchived" />Показывать архивных
            </label>
            <ListPanel
                :meta="characters.meta"
                :count="characters.data.length"
                :loading="false"
                error=""
                empty-title="Пока нет персонажей"
                empty-description="Добавьте персонажа, опишите его внешность и загрузите ракурсы."
                @page="changePage"
                @refresh="router.reload()"
            >
                <template #filters><p class="text-muted-foreground text-sm">Персонажи и утверждённые образы</p></template>
                <div class="divide-y">
                    <article v-for="character in characters.data" :key="character.id" class="flex flex-wrap items-center justify-between gap-4 p-5">
                        <div>
                            <h3 class="font-semibold">{{ character.name }}</h3>
                            <p v-if="character.story_role" class="text-muted-foreground text-sm">{{ character.story_role }}</p>
                            <p class="text-muted-foreground mt-1 text-sm">
                                {{
                                    character.current_revision
                                        ? 'Утверждённый образ · версия ' + character.current_revision.number
                                        : 'Пока нет утверждённого образа'
                                }}<span v-if="character.archived_at"> · В архиве</span>
                            </p>
                        </div>
                        <Button as-child variant="outline" class="h-11"
                            ><Link :href="route('characters.show', { ...params, character: character.id })">Открыть персонажа</Link></Button
                        >
                    </article>
                </div>
            </ListPanel>
        </section>
    </div>
</template>
