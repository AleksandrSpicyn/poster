<script setup lang="ts">
import ProjectArtwork from '@/components/projects/ProjectArtwork.vue';
import ConfirmationDialog from '@/components/ConfirmationDialog.vue';
import InputError from '@/components/InputError.vue';
import ListPanel from '@/components/ListPanel.vue';
import ProductionPanel from '@/components/ProductionPanel.vue';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import type { ProjectSummary } from '@/types/projects';
import type { PaginatedResource } from '@/types/content';
import type { CharacterSummary, EpisodeSummary, SeriesSummary } from '@/types/series';
import { Link, router, useForm } from '@inertiajs/vue3';
import { Plus } from '@lucide/vue';
import { computed } from 'vue';
import { useRoute } from 'ziggy-js';

const props = defineProps<{
    project: Pick<ProjectSummary, 'id' | 'name' | 'is_active' | 'cover'>;
    series: SeriesSummary;
    characters: PaginatedResource<CharacterSummary>;
    episodes: EpisodeSummary[];
}>();
const route = useRoute();
const params = { project: props.project.id, series: props.series.id };
const characterForm = useForm({ name: '' });
const characterErrors = computed<Record<string, string | undefined>>(() => characterForm.errors);
const episodeForm = useForm({ title: '' });
const chronologicalEpisodes = computed(() => [...props.episodes].sort((a, b) => a.number - b.number));
function episodeLabel(episode: EpisodeSummary) {
    if (episode.validity === 'requires_review') return 'Нужна проверка';
    if (episode.accepted_assembly_id) return 'Принято';
    if (episode.script_generation_status === 'failed') return 'Ошибка сценария';
    if (episode.script_generation_status === 'outcome_unknown') return 'Результат неизвестен';
    return episode.current_revision_id || episode.script_generation_status ? 'В работе' : 'Черновик';
}
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
    router.get(url, { page }, { preserveScroll: true });
}
</script>

<template>
    <div class="space-y-6">
        <p v-if="series.archived_at" class="bg-muted text-muted-foreground rounded-lg border p-4 text-sm">
            Сериал в архиве. История доступна для просмотра.
        </p>
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
                    <strong>{{ episode.title }}</strong>
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
        <form v-if="!series.archived_at" class="bg-card flex flex-wrap items-end gap-3 rounded-xl border p-5" @submit.prevent="createEpisode">
            <div class="grid min-w-48 flex-1 gap-2">
                <Label for="episode-title">Название эпизода</Label
                ><Input id="episode-title" v-model="episodeForm.title" required maxlength="255" :disabled="episodeForm.processing" /><InputError
                    :message="episodeForm.errors.title"
                />
            </div>
            <Button type="submit" class="h-11" :disabled="episodeForm.processing"><Plus class="size-4" aria-hidden="true" />Создать эпизод</Button>
        </form>
        <ProductionPanel
            :project-id="project.id"
            :series-id="series.id"
            :enabled="series.automation_enabled"
            :project-enabled="project.is_active"
            :archived="!!series.archived_at"
            :run="series.latest_production"
        />
        <section v-if="series.cycle_plan" class="bg-card space-y-3 rounded-xl border p-5" aria-label="План полной версии">
            <h2 class="text-lg font-semibold">План полной версии</h2>
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
            <form v-if="!series.archived_at" class="bg-card flex flex-wrap items-end gap-3 rounded-xl border p-5" @submit.prevent="createCharacter">
                <div class="grid min-w-48 flex-1 gap-2">
                    <Label for="character-name">Имя нового персонажа</Label
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
                    ><Plus class="size-4" aria-hidden="true" />Добавить персонажа</Button
                >
            </form>
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
        <div v-if="!series.archived_at" class="flex justify-end border-t pt-5">
            <ConfirmationDialog
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
    </div>
</template>
