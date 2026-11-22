<script setup lang="ts">
import { useEpisodeScreenplay } from '@/composables/useEpisodeScreenplay';
import { useEpisodeMontage } from '@/composables/useEpisodeMontage';
import { useEpisodeSources } from '@/composables/useEpisodeSources';
import AssemblyCard from '@/components/episodes/AssemblyCard.vue';
import ShotCard from '@/components/episodes/ShotCard.vue';
import SceneScriptEditor from '@/components/episodes/SceneScriptEditor.vue';
import Heading from '@/components/Heading.vue';
import ProjectArtwork from '@/components/projects/ProjectArtwork.vue';
import InputError from '@/components/InputError.vue';
import { Button } from '@/components/ui/button';
import ConfirmationDialog from '@/components/ConfirmationDialog.vue';
import TrimInterval from '@/components/episodes/TrimInterval.vue';
import { Label } from '@/components/ui/label';
import AppLayout from '@/layouts/AppLayout.vue';
import type { EpisodeWorkspaceProps } from '@/types/episodes';
import { Head, Link, router } from '@inertiajs/vue3';
import { RefreshCw } from '@lucide/vue';
import { computed, onMounted, onUnmounted, ref, watch } from 'vue';
import { useRoute } from 'ziggy-js';

const props = defineProps<EpisodeWorkspaceProps>();
const route = useRoute();
const params = { series: props.series.id, episode: props.episode.id };
const breadcrumbs = [
    { title: 'Проекты', href: route('projects.index') },
    { title: props.project.name, href: route('projects.show', props.project.id) },
    { title: props.episode.title, href: route('episodes.show', params) },
];
const revision = computed(() => props.episode.current_revision);
const saveTime = new Intl.DateTimeFormat('ru', { hour: '2-digit', minute: '2-digit', timeZone: 'UTC' });
const scriptStatusLabels: Record<string, string> = {
    queued: 'В очереди',
    running: 'В работе',
    completed: 'Готово',
    failed: 'Не удалось подготовить сценарий',
    outcome_unknown: 'Результат требует проверки',
};
const revisionStatusLabels: Record<string, string> = { draft: 'черновик', approved: 'утверждена', superseded: 'предыдущая версия' };
const archived = computed(() => !!props.series.archived_at);
const storyContext = computed(() => revision.value?.context ?? props.sourceAssembly?.continuity_snapshot);
const {
    online,
    draftStored,
    scriptForm,
    generateForm,
    approveForm,
    scriptText,
    jsonError,
    scriptErrors,
    editorErrors,
    generateErrors,
    approveErrors,
    editorStale,
    clearDraft,
    hasDraft,
    changedFields,
    saveScript,
    importScript,
    generateScript,
    approveScript,
} = useEpisodeScreenplay(props);
const { assemblyForm, assemblyErrors, shots, buildAssembly, canBuildAssembly } = useEpisodeMontage(props);
const { continuationSources, readySourcesFor } = useEpisodeSources(props);
const allAssemblies = computed(() =>
    props.episode.accepted_assembly && !props.assemblies.some((item) => item.id === props.episode.accepted_assembly?.id)
        ? [props.episode.accepted_assembly, ...props.assemblies]
        : props.assemblies,
);

const panels = [
    { key: 'screenplay', label: 'Сценарий' },
    { key: 'shots', label: 'Кадры' },
    { key: 'montage', label: 'Монтаж' },
    { key: 'release', label: 'Публикация' },
];
const panelTitles: Record<string, string> = {
    screenplay: 'Сценарий эпизода',
    shots: 'Кадры и дубли',
    montage: 'Монтаж эпизода',
    release: 'Публикация эпизода',
};
const releaseSections = [
    { key: 'preview', label: 'Предпросмотр' },
    { key: 'recipients', label: 'Получатели' },
    { key: 'confirmation', label: 'Подтверждение' },
] as const;
const releaseSection = ref<(typeof releaseSections)[number]['key']>('preview');
const panel = ref('screenplay');
const recoveringLine = ref('');
const dialogueFocus = computed(() => panel.value === 'screenplay' && !!recoveringLine.value);
function leaveDialogueFocus() {
    recoveringLine.value = '';
}
watch([editorStale, scriptErrors, archived], ([stale, errors, isArchived]) => {
    if (stale || errors || isArchived) leaveDialogueFocus();
});
function restorePanel() {
    const key = window.location.hash.slice(1);
    panel.value = panels.some((item) => item.key === key) ? key : 'screenplay';
}
onMounted(() => {
    restorePanel();
    window.addEventListener('hashchange', restorePanel);
});
onUnmounted(() => window.removeEventListener('hashchange', restorePanel));
const preview = computed(() => allAssemblies.value.flatMap((item) => item.media).find((item) => item.collection === 'episode-video'));
function refresh() {
    router.get(
        route('episodes.show', params) + window.location.hash,
        {},
        {
            replace: true,
            preserveState: () => {
                clearDraft();
                return false;
            },
        },
    );
}
</script>

<template>
    <AppLayout :breadcrumbs="dialogueFocus ? [{ title: 'Сериалы', href: route('series.index') }] : breadcrumbs">
        <Head :title="episode.title" />
        <div class="workspace-page" :class="{ 'dialogue-workspace': dialogueFocus }" @keydown.esc="leaveDialogueFocus">
            <header v-if="dialogueFocus" class="space-y-3">
                <Heading title="Сценарий эпизода" />
                <button type="button" class="text-muted-foreground text-sm" aria-label="Вернуться в черновик сценария" @click="leaveDialogueFocus">
                    Черновик
                </button>
            </header>
            <div v-show="!dialogueFocus" class="flex flex-wrap items-start justify-between gap-4">
                <Heading
                    :title="panelTitles[panel] ?? 'Сценарий эпизода'"
                    :description="series.name + ' / Эпизод ' + episode.number + ' · ' + episode.title"
                />
                <ConfirmationDialog
                    v-if="hasDraft"
                    heading="Сброс несохранённых изменений"
                    title="Сбросить черновик?"
                    description="Несохранённые изменения будут заменены последней сохранённой версией сценария."
                    action="Сбросить изменения"
                    cancel="Остаться в редакторе"
                    destructive
                    @confirm="refresh"
                >
                    <template #trigger
                        ><Button variant="outline" class="h-11"><RefreshCw class="size-4" aria-hidden="true" />Обновить результаты</Button></template
                    >

                    <div class="studio-row">
                        <dt>Последнее сохранение</dt>
                        <dd>
                            <template v-if="revision"
                                ><time v-if="revision.created_at" :datetime="revision.created_at"
                                    >{{ saveTime.format(new Date(revision.created_at)) }} UTC · </time
                                >Версия {{ revision.number }}</template
                            ><template v-else>Сценарий ещё не сохранён</template>
                        </dd>
                    </div>
                    <div class="studio-row">
                        <dt>Несохранено</dt>
                        <dd>{{ changedFields.join(', ') || 'Черновик сценария' }}</dd>
                    </div>
                </ConfirmationDialog>
                <Button v-else variant="outline" class="h-11" @click="refresh"
                    ><RefreshCw class="size-4" aria-hidden="true" />Обновить результаты</Button
                >
            </div>
            <nav v-show="!dialogueFocus" aria-label="Этапы эпизода" class="flex flex-wrap gap-2">
                <a
                    v-for="item in panels"
                    :key="item.key"
                    :href="'#' + item.key"
                    :aria-current="panel === item.key ? 'page' : undefined"
                    class="bg-muted aria-[current=page]:bg-sidebar-accent aria-[current=page]:text-sidebar-accent-foreground inline-flex min-h-10 items-center rounded-lg px-4 text-sm"
                    @click="panel = item.key"
                    >{{ item.label }}</a
                >
            </nav>
            <p v-if="archived" class="bg-muted text-muted-foreground rounded-lg border p-4 text-sm">
                Сериал в архиве. Сохранённые сценарии, дубли и монтажи доступны для просмотра.
            </p>
            <p v-if="!project.is_active" role="status" class="bg-muted text-muted-foreground rounded-lg border p-4 text-sm">
                Проект выключен. Включите его в настройках, чтобы создавать и публиковать новые видео.
            </p>
            <p
                v-if="episode.validity === 'requires_review'"
                role="alert"
                class="border-destructive/30 text-destructive rounded-lg border p-4 text-sm"
            >
                Предыдущий принятый эпизод изменился. Проверьте источник и сохраните новую версию сценария перед продолжением или публикацией.
            </p>
            <div
                class="episode-columns grid items-start gap-4"
                :class="panel === 'release' ? 'md:grid-cols-[min(25%,276px)_minmax(0,1fr)]' : 'md:grid-cols-[min(34%,400px)_minmax(0,1fr)]'"
            >
                <aside v-show="dialogueFocus" class="dialogue-preview studio-panel">
                    <ProjectArtwork :name="project.name" :cover="project.cover" loading="eager" class="rounded-lg" />
                    <h2>{{ series.name }}</h2>
                    <p>Эпизод {{ String(episode.number).padStart(2, '0') }}</p>
                </aside>
                <nav v-if="panel === 'release'" class="studio-panel grid gap-1" aria-label="Разделы публикации">
                    <h2>В этом разделе</h2>
                    <button
                        v-for="item in releaseSections"
                        :key="item.key"
                        type="button"
                        class="aria-pressed:bg-sidebar-accent min-h-10 rounded-lg px-3 text-left text-sm"
                        :aria-pressed="releaseSection === item.key"
                        @click="releaseSection = item.key"
                    >
                        {{ item.label }}
                    </button>
                </nav>
                <div v-else-if="!dialogueFocus && preview" class="bg-sidebar rounded-xl border p-3">
                    <video
                        :src="preview.url"
                        aria-label="Предпросмотр эпизода"
                        controls
                        preload="metadata"
                        class="mx-auto max-h-[420px] w-full rounded-lg"
                    />
                </div>
                <div v-else-if="!dialogueFocus" class="studio-panel">
                    <h2>Предпросмотр эпизода</h2>
                    <p>Видео появится после сборки.</p>
                </div>
                <div class="min-w-0 space-y-5">
                    <div v-show="panel === 'screenplay'" class="space-y-5">
                        <details class="bg-card space-y-4 rounded-xl border p-5 sm:p-7">
                            <summary class="cursor-pointer font-semibold">
                                {{ revision ? 'Память истории на момент сохранения' : 'Память истории для следующего эпизода' }}
                            </summary>
                            <p v-if="revision" class="text-muted-foreground text-xs">
                                Версия паспорта {{ revision.context.specification_version ?? '—' }} · версия истории
                                {{ revision.base_continuity_version }}
                            </p>
                            <dl
                                v-if="storyContext?.confirmed_events || storyContext?.character_state || storyContext?.open_threads"
                                class="grid gap-4 text-sm"
                            >
                                <div v-if="storyContext.confirmed_events" class="space-y-1">
                                    <dt class="font-medium">События предыдущих эпизодов</dt>
                                    <dd class="text-muted-foreground whitespace-pre-wrap">{{ storyContext.confirmed_events }}</dd>
                                </div>
                                <div v-if="storyContext.character_state" class="space-y-1">
                                    <dt class="font-medium">Состояние персонажей</dt>
                                    <dd class="text-muted-foreground whitespace-pre-wrap">{{ storyContext.character_state }}</dd>
                                </div>
                                <div v-if="storyContext.open_threads" class="space-y-1">
                                    <dt class="font-medium">Открытые линии сюжета</dt>
                                    <dd class="text-muted-foreground whitespace-pre-wrap">{{ storyContext.open_threads }}</dd>
                                </div>
                            </dl>
                            <p v-else class="text-muted-foreground text-sm">
                                Первый эпизод опирается на паспорт сериала и утверждённые образы персонажей.
                            </p>
                            <p
                                v-if="storyContext?.scene_closed !== undefined && storyContext?.scene_closed !== null"
                                class="text-muted-foreground text-sm"
                            >
                                {{
                                    storyContext.scene_closed
                                        ? 'Предыдущая сцена завершена. Начните новую сцену.'
                                        : 'Предыдущая сцена открыта. Её можно продолжить с последних кадров.'
                                }}
                            </p>
                        </details>
                        <section v-if="!archived" class="bg-card space-y-4 rounded-xl border p-5 sm:p-7" aria-labelledby="script-generation-title">
                            <h2 id="script-generation-title" class="text-lg font-semibold">Подготовка сценария</h2>
                            <form class="grid gap-3" @submit.prevent="generateScript">
                                <Label for="script-brief">Что должно произойти в эпизоде?</Label
                                ><textarea
                                    id="script-brief"
                                    v-model="generateForm.brief"
                                    required
                                    maxlength="5000"
                                    rows="3"
                                    class="border-input bg-background focus-visible:ring-ring/50 resize-y rounded-md border p-3 text-sm outline-none focus-visible:ring-2"
                                    placeholder="Продолжите сцену или опишите начало следующей."
                                /><InputError :message="generateErrors" />
                                <div>
                                    <Button
                                        type="submit"
                                        class="h-11"
                                        :disabled="
                                            !project.is_active ||
                                            editorStale ||
                                            generateForm.processing ||
                                            ['queued', 'running'].includes(episode.script_generation_status ?? '') ||
                                            !characters.length
                                        "
                                        >Создать черновик</Button
                                    >
                                </div>
                            </form>
                            <p v-if="episode.script_generation_status" class="text-muted-foreground text-sm" role="status">
                                Подготовка сценария · {{ scriptStatusLabels[episode.script_generation_status] }}
                            </p>
                            <p v-if="episode.script_generation_error" class="text-destructive text-sm">
                                Проверьте доступность проекта и актуальность сценария перед повторной подготовкой.
                            </p>
                            <p v-if="!characters.length" class="text-muted-foreground text-sm">
                                Чтобы написать эпизод, утвердите образ хотя бы одного персонажа.
                                <Link :href="route('projects.show', project.id)" class="text-primary underline">Персонажи</Link>
                            </p>
                        </section>
                        <section class="bg-card space-y-4 rounded-xl border p-5 sm:p-7" aria-labelledby="script-editor-title">
                            <div class="flex flex-wrap items-center justify-between gap-3">
                                <h2 id="script-editor-title" class="text-lg font-semibold">Редактор сценария</h2>
                                <span v-if="revision" class="text-muted-foreground text-sm"
                                    >Версия {{ revision.number
                                    }}<template v-if="revisionStatusLabels[revision.status]">
                                        · {{ revisionStatusLabels[revision.status] }}</template
                                    ></span
                                >
                            </div>
                            <p class="text-muted-foreground text-sm">
                                Выберите персонажей, расположите сцены по порядку и опишите кадры. После сохранения предыдущие версии останутся в
                                истории.
                            </p>
                            <p v-if="editorStale" role="alert" class="text-destructive text-sm">
                                Появилась новая версия эпизода. Скопируйте изменения из черновика перед загрузкой новой версии.
                            </p>
                            <form class="grid gap-5" @submit.prevent="saveScript" @invalid.capture="leaveDialogueFocus">
                                <SceneScriptEditor
                                    v-model="scriptForm.script"
                                    v-model:recovering-line="recoveringLine"
                                    :characters="characters"
                                    :transitions="transitionModes"
                                    :sources="continuationSources"
                                    :errors="editorErrors"
                                    :online="online"
                                    :draft-stored="draftStored"
                                    :disabled="archived || scriptForm.processing || editorStale"
                                />
                                <InputError :message="scriptErrors" />
                                <div class="flex flex-wrap gap-3">
                                    <Button
                                        type="submit"
                                        class="h-11"
                                        :disabled="!online || archived || scriptForm.processing || editorStale || !characters.length"
                                        >Сохранить новую версию</Button
                                    ><Button
                                        v-if="revision?.status === 'draft'"
                                        type="button"
                                        variant="outline"
                                        class="h-11"
                                        :disabled="archived || approveForm.processing || scriptForm.isDirty"
                                        @click="approveScript"
                                        >Утвердить версию {{ revision.number }}</Button
                                    >
                                </div>
                                <InputError :message="approveErrors" />
                            </form>
                            <details class="space-y-4 rounded-lg border p-4">
                                <summary class="cursor-pointer text-sm font-medium">Импорт и экспорт сценария в JSON</summary>
                                <p class="text-muted-foreground mt-3 text-xs">Импорт сохранит новую версию сценария.</p>
                                <Button type="button" variant="outline" class="h-11" @click="scriptText = JSON.stringify(scriptForm.script, null, 2)"
                                    >Экспортировать черновик в JSON</Button
                                ><Label for="screenplay-json">Сценарий в формате JSON</Label
                                ><textarea
                                    id="screenplay-json"
                                    v-model="scriptText"
                                    rows="14"
                                    spellcheck="false"
                                    :disabled="archived || scriptForm.processing"
                                    class="border-input bg-background focus-visible:ring-ring/50 mt-2 w-full resize-y rounded-md border p-4 font-mono text-xs leading-relaxed outline-none focus-visible:ring-2"
                                />
                                <p class="text-muted-foreground text-xs">
                                    При импорте нулевая отметка времени выбирает последний кадр. Остальные отметки задаются в миллисекундах.
                                </p>
                                <InputError :message="jsonError" /><Button
                                    type="button"
                                    variant="outline"
                                    class="h-11"
                                    :disabled="archived || scriptForm.processing || editorStale"
                                    @click="importScript"
                                    >Импортировать новую версию</Button
                                >
                            </details>
                        </section>
                    </div>
                    <p v-if="panel === 'shots' && !revision" class="studio-panel">Сохраните первую версию сценария, чтобы перейти к кадрам.</p>
                    <p v-if="panel === 'montage' && (revision?.status !== 'approved' || archived)" class="studio-panel">
                        Для нового монтажа нужен утверждённый сценарий активного сериала.
                    </p>
                    <section v-show="panel === 'shots'" v-if="revision" class="space-y-5" aria-labelledby="episode-shots-title">
                        <h2 id="episode-shots-title" class="text-lg font-semibold">Кадры и дубли</h2>
                        <p v-if="revision.status !== 'approved'" class="text-muted-foreground text-sm">
                            Review and approve the screenplay before starting media generation.
                        </p>
                        <section v-for="scene in revision.scenes" :key="scene.id" class="space-y-4">
                            <div>
                                <h3 class="font-semibold">{{ scene.position }}. {{ scene.title }}</h3>
                                <p class="text-muted-foreground mt-1 text-xs">
                                    Planned ending: {{ scene.is_closed ? 'scene closed' : 'scene open' }}
                                </p>
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
                        v-show="panel === 'montage'"
                        v-if="revision?.status === 'approved' && !archived"
                        class="bg-card space-y-4 rounded-xl border p-5 sm:p-7"
                        aria-labelledby="montage-builder-title"
                    >
                        <h2 id="montage-builder-title" class="text-lg font-semibold">Монтаж эпизода</h2>
                        <form class="space-y-4" @submit.prevent="buildAssembly">
                            <div v-for="(shot, index) in shots" :key="shot.id" class="grid items-end gap-3 rounded-lg border p-4 sm:grid-cols-3">
                                <div class="grid gap-2">
                                    <Label :for="'take-' + shot.id">Кадр {{ index + 1 }} · готовый дубль</Label
                                    ><select
                                        :id="'take-' + shot.id"
                                        v-model="assemblyForm.shots[index].prompt_id"
                                        required
                                        class="border-input bg-background h-11 rounded-md border px-3 text-sm"
                                    >
                                        <option :value="0" disabled>Выберите готовый дубль</option>
                                        <option v-for="take in shot.takes.filter((item) => item.generated)" :key="take.id" :value="take.id">
                                            Дубль {{ take.take_number }}
                                        </option>
                                    </select>
                                </div>
                                <TrimInterval
                                    :shot-id="shot.id"
                                    :duration="shot.duration_ms"
                                    :thumbnail="
                                        shot.takes
                                            .find((take) => take.id === assemblyForm.shots[index].prompt_id)
                                            ?.media.find((media) => media.collection === 'shot-image')?.url
                                    "
                                    v-model:start="assemblyForm.shots[index].trim_start_ms"
                                    v-model:end="assemblyForm.shots[index].trim_end_ms"
                                />
                            </div>
                            <InputError :message="assemblyErrors" /><Button
                                type="submit"
                                class="h-11"
                                :disabled="!project.is_active || assemblyForm.processing || !canBuildAssembly"
                                >Собрать монтаж</Button
                            >
                        </form>
                    </section>
                    <section v-show="panel === 'release'" class="space-y-5" aria-labelledby="episode-montages-title">
                        <h2 id="episode-montages-title" class="sr-only">Сборки и публикация</h2>
                        <p v-if="!allAssemblies.length" class="text-muted-foreground text-sm">Здесь появятся сборки для просмотра и приёмки.</p>
                        <AssemblyCard
                            v-for="assembly in allAssemblies"
                            :key="assembly.id + ':' + assembly.lock_version"
                            :assembly="assembly"
                            :section="releaseSection"
                            @navigate="releaseSection = $event"
                            :params="params"
                            :channels="channels.filter((channel) => ['youtube', 'instagram', 'tiktok'].includes(channel.type))"
                            :publications="publications.filter((publication) => publication.episode_assembly_id === assembly.id)"
                            :can-publish="series.output_mode !== 'single_video'"
                            :current-accepted="episode.accepted_assembly_id === assembly.id && episode.validity === 'current'"
                            :archived="archived"
                            :project-enabled="project.is_active"
                        />
                    </section>
                </div>
            </div>
        </div>
    </AppLayout>
</template>

<style scoped>
.dialogue-workspace {
    padding-block-start: 12px;
}
.dialogue-workspace :deep(.dialogue-focus [role='status']) {
    min-height: 66px;
}
.dialogue-workspace :deep(.dialogue-focus input) {
    height: 46px;
}

.dialogue-workspace .episode-columns {
    grid-template-columns: minmax(0, 1fr) minmax(0, 0.45fr);
    gap: 16px;
}
.dialogue-preview {
    grid-column: 2;
    grid-row: 1;
    padding: 16px;
}
.dialogue-preview > :first-child {
    aspect-ratio: 2.45;
}
.dialogue-preview h2 {
    margin-block: 12px 4px;
}
.dialogue-workspace .episode-columns > :last-child {
    grid-column: 1;
    grid-row: 1;
}
/* Keep the live input in the document so reconnecting preserves the caret and mobile keyboard. */
.dialogue-workspace .episode-columns > :last-child :deep(:has(.dialogue-focus)) {
    display: contents;
}
.dialogue-workspace .episode-columns > :last-child :deep(:has(.dialogue-focus) > :not(:has(.dialogue-focus), .dialogue-focus)) {
    display: none;
}
.dialogue-workspace :deep(.dialogue-focus) {
    display: flex;
    flex-direction: column;
    align-items: stretch;
    gap: 16px;
    margin: 0;
    padding: 20px 24px 28px;
    background: var(--card);
    border: 1px solid var(--border);
    border-radius: 12px;
}
.dialogue-workspace :deep(.dialogue-focus > button[type='submit']) {
    align-self: start;
}
.dialogue-workspace :deep(.dialogue-focus > button:disabled) {
    color: var(--muted-foreground);
    background: var(--muted);
    opacity: 1;
}
@media (max-width: 1099px) {
    .dialogue-workspace .episode-columns {
        display: block;
    }
    .dialogue-preview {
        display: none;
    }
}
@media (max-width: 767px) {
    :global([data-slot='sidebar-inset']:has(.dialogue-workspace) > p[role='status']),
    :global([data-slot='sidebar-inset']:has(.dialogue-workspace) > .studio-top > button),
    :global([data-slot='sidebar-inset']:has(.dialogue-workspace) ~ .mobile-nav) {
        display: none;
    }

    .dialogue-workspace :deep(.dialogue-focus) {
        padding: 16px;
    }
}
</style>
