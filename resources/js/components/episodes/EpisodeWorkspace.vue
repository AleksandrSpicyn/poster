<script setup lang="ts">
import { createShotThumbnailLookup } from '@/lib/shotThumbnails';
import { useHashNavigation } from '@/composables/useHashNavigation';
import { useEpisodeScreenplay } from '@/composables/useEpisodeScreenplay';
import { useEpisodeMontage } from '@/composables/useEpisodeMontage';
import { useEpisodeSources } from '@/composables/useEpisodeSources';
import EpisodePreview from '@/components/episodes/EpisodePreview.vue';
import EpisodeMemory from '@/components/episodes/EpisodeMemory.vue';
import EpisodeOverview from '@/components/episodes/EpisodeOverview.vue';
import EpisodeRelease from '@/components/episodes/EpisodeRelease.vue';
import EpisodeShotList from '@/components/episodes/EpisodeShotList.vue';
import SeasonScriptImport from '@/components/episodes/SeasonScriptImport.vue';
import SceneScriptEditor from '@/components/episodes/SceneScriptEditor.vue';
import Heading from '@/components/Heading.vue';
import ProjectArtwork from '@/components/projects/ProjectArtwork.vue';
import InputError from '@/components/InputError.vue';
import { Button } from '@/components/ui/button';
import ConfirmationDialog from '@/components/ConfirmationDialog.vue';
import MontageShotSelection from '@/components/episodes/MontageShotSelection.vue';
import ScreenplayJsonDialog from '@/components/episodes/ScreenplayJsonDialog.vue';
import { Label } from '@/components/ui/label';
import AppLayout from '@/layouts/AppLayout.vue';
import type { EpisodeWorkspaceProps } from '@/types/episodes';
import { Head, Link, router } from '@inertiajs/vue3';
import { RefreshCw } from '@lucide/vue';
import { computed, ref, watch } from 'vue';
import { useRoute } from '@/composables/useRoute';

const props = defineProps<EpisodeWorkspaceProps>();
const route = useRoute();
const params = { series: props.series.id, episode: props.episode.id };
const breadcrumbs = [
    { title: 'Сериалы', href: route('series.index') },
    { title: props.series.name, href: route('series.show', { project: props.project.id, series: props.series.id }) },
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
    checkJson,
    restored: scriptRestored,
    saveError: scriptSaveError,
    sourceVersion,
    conflict: scriptConflict,
    keepLocalVersion,
    loadServerVersion,
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
const {
    assemblyForm,
    assemblyErrors,
    shots,
    readyTakes,
    buildAssembly,
    canBuildAssembly,
    stale,
    restored,
    saveError,
    difference,
    resetSelection,
    keepSelection,
} = useEpisodeMontage(props);
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
const panel = ref('screenplay');
const detail = ref<number | null>(null);
const memoryOpen = ref(false);
const overview = computed(() => panel.value !== 'shots' && panel.value !== 'release' && !!revision.value && detail.value === null);
const editingShot = computed(() => (panel.value === 'montage' && detail.value !== null ? shots.value[detail.value] : null));
const editingTake = computed(() => editingShot.value?.takes.find((take) => take.id === assemblyForm.shots[detail.value ?? 0]?.prompt_id));
const editingVideo = computed(() => editingTake.value?.media.find((m) => m.collection === 'shot-video'));
const editingImage = computed(() => editingTake.value?.media.find((m) => m.collection === 'shot-image'));
const selectedDuration = ref<number | null>(null);
watch(editingVideo, () => {
    selectedDuration.value = null;
});
const thumbnailForShot = computed(() => createShotThumbnailLookup(props.screenplay, revision.value));
const shotThumbnails = computed(() => scriptForm.script.scenes.map((scene) => scene.shots.map(thumbnailForShot.value)));
const acceptedTakeIds = computed(() => props.episode.accepted_assembly?.selection?.flatMap((shot) => (shot.prompt_id ? [shot.prompt_id] : [])) ?? []);
const firstThumbnail = computed(
    () =>
        shots.value
            .flatMap((shot) => shot.takes)
            .flatMap((take) => take.media)
            .find((media) => media.collection === 'shot-image')?.url,
);
function openRow(index: number) {
    window.location.hash = panel.value + '/' + (index + 1);
}
const recoveringLine = ref('');
const dialogueFocus = computed(() => panel.value === 'screenplay' && !!recoveringLine.value);
function leaveDialogueFocus() {
    recoveringLine.value = '';
}
watch([editorStale, scriptErrors, archived], ([stale, errors, isArchived]) => {
    if (stale || errors || isArchived) leaveDialogueFocus();
});
function restorePanel() {
    const [key, row] = window.location.hash.slice(1).split('/');
    memoryOpen.value = key === 'history' && (!!revision.value?.source_assembly_id || !!props.sourceAssembly);
    const index =
        key === 'shots' && row === 'review'
            ? shots.value.findIndex((shot) =>
                  shot.takes.some((take) => take.operations.some((operation) => operation.status === 'submission_unknown')),
              )
            : key === 'shots'
              ? shots.value.findIndex((shot) => shot.id === Number(row))
              : Number(row) - 1;
    detail.value =
        Number.isInteger(index) && index >= 0 && index < (key === 'screenplay' ? scriptForm.script.scenes.length : shots.value.length) ? index : null;
    leaveDialogueFocus();
    panel.value = panels.some((item) => item.key === key) ? key : 'screenplay';
}
useHashNavigation(restorePanel);
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
    <AppLayout :breadcrumbs="breadcrumbs">
        <Head :title="episode.title" />
        <div
            class="workspace-page"
            :class="{
                'dialogue-workspace': dialogueFocus,
                'episode-shots-page': panel === 'shots',
                'episode-release-page': panel === 'release',
                'screenplay-page': panel === 'screenplay' && !dialogueFocus,
                'episode-overview-page': overview,
                'scene-detail-page': panel === 'screenplay' && detail !== null && !dialogueFocus,
                'trim-detail-page': panel === 'montage' && detail !== null,
            }"
            @keydown.esc="leaveDialogueFocus"
        >
            <template v-if="!memoryOpen || panel === 'release'">
                <header v-if="dialogueFocus" class="space-y-3">
                    <Heading title="Сценарий эпизода" />
                    <button
                        type="button"
                        class="text-muted-foreground text-sm"
                        aria-label="Вернуться в черновик сценария"
                        @click="leaveDialogueFocus"
                    >
                        Черновик
                    </button>
                </header>
                <div v-show="!dialogueFocus" class="flex flex-wrap items-start justify-between gap-4">
                    <Heading
                        v-if="panel !== 'shots'"
                        :title="panel === 'release' ? episode.title : (panelTitles[panel] ?? 'Сценарий эпизода')"
                        :description="series.name + ' / Эпизод ' + String(episode.number).padStart(2, '0')"
                    />
                    <div v-else class="flex min-w-0 flex-1 flex-wrap items-baseline gap-x-3">
                        <h1 class="text-[30px] font-bold wrap-anywhere min-[700px]:text-[40px]">{{ episode.title }}</h1>
                        <span class="text-muted-foreground font-mono text-sm">Эпизод {{ String(episode.number).padStart(2, '0') }}</span>
                    </div>
                    <Button
                        v-if="overview && panel === 'montage'"
                        class="h-11"
                        :disabled="archived || revision?.status !== 'approved' || !project.is_active || assemblyForm.processing || !canBuildAssembly"
                        @click="buildAssembly"
                        >Собрать монтаж</Button
                    >
                    <Button
                        v-else-if="overview && panel === 'screenplay'"
                        class="h-11"
                        :disabled="!online || archived || scriptForm.processing || editorStale || !characters.length"
                        @click="saveScript"
                        >Сохранить черновик</Button
                    >
                    <ConfirmationDialog
                        v-else-if="hasDraft && panel !== 'release'"
                        heading="Сброс несохранённых изменений"
                        title="Сбросить черновик?"
                        description="Несохранённые изменения будут заменены последней сохранённой версией сценария."
                        action="Сбросить изменения"
                        cancel="Остаться в редакторе"
                        destructive
                        @confirm="refresh"
                    >
                        <template #trigger
                            ><Button variant="outline" class="h-11"
                                ><RefreshCw class="size-4" aria-hidden="true" /><span :class="{ 'sr-only': panel === 'shots' }"
                                    >Обновить результаты</span
                                ></Button
                            ></template
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
                    <Button v-else-if="panel !== 'release'" variant="outline" class="h-11" @click="refresh"
                        ><RefreshCw class="size-4" aria-hidden="true" /><span :class="{ 'sr-only': panel === 'shots' }"
                            >Обновить результаты</span
                        ></Button
                    >
                </div>
            </template>
            <nav v-show="!dialogueFocus" aria-label="Этапы эпизода" class="flex flex-wrap gap-2">
                <a
                    v-for="item in panels"
                    :key="item.key"
                    :href="'#' + item.key"
                    :aria-current="!memoryOpen && panel === item.key ? 'page' : undefined"
                    class="bg-muted aria-[current=page]:bg-sidebar-accent aria-[current=page]:text-sidebar-accent-foreground inline-flex min-h-10 items-center rounded-lg px-4 text-sm"
                    @click="panel = item.key"
                    >{{ item.label }}</a
                >
                <a
                    v-if="revision?.source_assembly_id || sourceAssembly"
                    :aria-current="memoryOpen ? 'page' : undefined"
                    href="#history"
                    class="bg-muted aria-[current=page]:bg-sidebar-accent aria-[current=page]:text-sidebar-accent-foreground inline-flex min-h-10 items-center rounded-lg px-4 text-sm"
                    >История</a
                >
            </nav>
            <EpisodeRelease v-if="panel === 'release'" :workspace="props" :assemblies="allAssemblies" />
            <EpisodeMemory v-else-if="memoryOpen" :workspace="props" :assembly="revision ? memoryAssembly : sourceAssembly" />
            <template v-else>
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
                <EpisodeShotList
                    v-if="panel === 'shots' && revision"
                    :revision="revision"
                    :selected-take-ids="acceptedTakeIds"
                    :detail="detail"
                    :params="params"
                    :archived="archived"
                    :project-enabled="project.is_active"
                    :request-key="requestKey"
                    :ready-sources-for="readySourcesFor"
                />
                <p v-else-if="panel === 'shots'" class="studio-panel">Сохраните первую версию сценария, чтобы перейти к кадрам.</p>
                <div v-else class="episode-columns grid items-start gap-4 md:grid-cols-[min(34%,400px)_minmax(0,1fr)]">
                    <aside v-show="dialogueFocus" class="dialogue-preview studio-panel">
                        <ProjectArtwork :name="project.name" :cover="project.cover" loading="eager" class="rounded-lg" />
                        <h2>{{ series.name }}</h2>
                        <p>Эпизод {{ String(episode.number).padStart(2, '0') }}</p>
                    </aside>
                    <aside v-if="editingShot && !dialogueFocus" class="studio-panel trim-player">
                        <h2>Предпросмотр</h2>
                        <video
                            v-if="editingVideo"
                            :key="editingVideo.id"
                            :src="editingVideo.url"
                            :poster="editingImage?.url"
                            controls
                            preload="metadata"
                            aria-label="Предпросмотр выбранного дубля"
                            @loadedmetadata="selectedDuration = ($event.target as HTMLVideoElement).duration"
                        />
                        <p v-if="editingTake">
                            Кадр {{ String((detail ?? 0) + 1).padStart(2, '0') }} · дубль {{ String(editingTake.take_number).padStart(2, '0') }}
                        </p>
                        <p v-else>Выберите готовый дубль</p>
                    </aside>
                    <EpisodePreview v-else-if="!dialogueFocus && preview" class="episode-video" :src="preview.url" :poster="firstThumbnail" />
                    <div v-else-if="!dialogueFocus" class="studio-panel">
                        <h2>Предпросмотр эпизода</h2>
                        <p>Видео появится после сборки.</p>
                    </div>
                    <div class="min-w-0 space-y-5">
                        <EpisodeOverview
                            v-if="overview && revision"
                            :panel="panel"
                            :revision="revision"
                            :screenplay="scriptForm.script"
                            :selection="assemblyForm.shots"
                            @open="openRow"
                        />
                        <a
                            v-if="detail !== null && !dialogueFocus"
                            :href="'#' + panel"
                            class="text-muted-foreground inline-block text-sm underline"
                            >{{ panelTitles[panel] }}</a
                        >
                        <section
                            v-if="panel === 'screenplay' && !dialogueFocus && (scriptRestored || scriptSaveError || editorStale)"
                            class="studio-panel space-y-3"
                            role="status"
                        >
                            <h2>Черновик</h2>
                            <strong>{{
                                editorStale ? 'Серверная версия изменилась' : scriptSaveError ? 'Сохранение не выполнено' : 'Черновик восстановлен'
                            }}</strong>
                            <p v-if="editorStale">
                                В другой вкладке сохранена версия {{ revision?.number }}. Ваш ввод основан на версии {{ sourceVersion }}.
                            </p>
                            <p v-else>{{ scriptSaveError || 'Ввод из этой вкладки восстановлен после перезагрузки.' }}</p>
                            <div v-if="editorStale && scriptConflict" class="grid gap-3 sm:grid-cols-2">
                                <p>В этой вкладке<br />{{ scriptConflict.local }}</p>
                                <p>На сервере<br />{{ scriptConflict.server }}</p>
                                <p>{{ scriptConflict.label }}</p>
                            </div>
                            <div class="flex flex-wrap gap-3">
                                <Button v-if="editorStale" variant="outline" @click="keepLocalVersion">Оставить локальную копию</Button>
                                <Button v-else :disabled="!online || archived || scriptForm.processing" @click="saveScript">{{
                                    scriptSaveError ? 'Повторить сохранение' : 'Сохранить изменения'
                                }}</Button>
                                <Button variant="outline" @click="loadServerVersion">{{
                                    editorStale ? 'Загрузить версию ' + revision?.number : 'Сбросить'
                                }}</Button>
                            </div>
                        </section>
                        <div v-show="!overview && panel === 'screenplay'" class="script-workspace space-y-5">
                            <details class="bg-card rounded-xl border p-5 open:space-y-4 sm:p-6">
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
                            <section
                                v-if="!archived"
                                class="bg-card space-y-4 rounded-xl border p-5 sm:p-7"
                                aria-labelledby="script-generation-title"
                            >
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
                                        :scene-index="detail"
                                        :thumbnails="shotThumbnails"
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
                            </section>
                        </div>
                        <ScreenplayJsonDialog
                            v-if="panel === 'screenplay' && !dialogueFocus"
                            v-model="scriptText"
                            :episode-id="episode.id"
                            :screenplay="scriptForm.script"
                            :json-error="jsonError"
                            :save-error="scriptSaveError || scriptErrors"
                            :draft-stored="draftStored"
                            :read-only="archived"
                            :processing="scriptForm.processing"
                            :disabled="archived || editorStale || !online"
                            :check-json="checkJson"
                            @import="importScript"
                            ><SeasonScriptImport
                                v-if="!archived"
                                :series="series"
                                :episode="episode"
                                :disabled="editorStale || !online || scriptForm.processing"
                        /></ScreenplayJsonDialog>
                        <p v-if="panel === 'montage' && (revision?.status !== 'approved' || archived)" class="studio-panel">
                            Для нового монтажа нужен утверждённый сценарий активного сериала.
                        </p>

                        <section v-if="panel === 'montage' && (stale || saveError || restored)" class="studio-panel space-y-3" role="status">
                            <h2>{{ stale ? 'Серверная версия изменилась' : saveError ? 'Сохранение не выполнено' : 'Черновик восстановлен' }}</h2>
                            <p v-if="stale && difference">
                                {{ difference.label }} · в черновике {{ difference.local }}, в сохранённом монтаже {{ difference.server }}.
                            </p>
                            <p v-else-if="saveError">{{ saveError }}</p>
                            <Button v-if="stale" @click="keepSelection">Оставить локальную копию</Button>
                            <Button v-if="saveError && !stale" :disabled="!canBuildAssembly || assemblyForm.processing" @click="buildAssembly"
                                >Повторить сохранение</Button
                            >
                            <Button variant="outline" @click="resetSelection">Загрузить сохранённый монтаж</Button>
                        </section>
                        <section
                            v-show="!overview && panel === 'montage'"
                            v-if="revision?.status === 'approved' && !archived"
                            class="bg-card space-y-4 rounded-xl border p-5 sm:p-7"
                            aria-labelledby="montage-builder-title"
                        >
                            <h2 id="montage-builder-title" class="text-lg font-semibold">
                                {{
                                    editingShot
                                        ? 'Кадр ' +
                                          String((detail ?? 0) + 1).padStart(2, '0') +
                                          ' · ' +
                                          (editingShot.title || editingShot.camera?.description || editingShot.action)
                                        : 'Монтаж эпизода'
                                }}
                            </h2>
                            <form class="space-y-4" @submit.prevent="buildAssembly">
                                <MontageShotSelection
                                    v-for="(shot, index) in shots"
                                    v-show="detail === null || detail === index"
                                    :key="shot.id"
                                    v-model:take-id="assemblyForm.shots[index].prompt_id"
                                    v-model:start="assemblyForm.shots[index].trim_start_ms"
                                    v-model:end="assemblyForm.shots[index].trim_end_ms"
                                    :shot="shot"
                                    :ready-takes="readyTakes[index]"
                                    :selected-duration="editingShot?.id === shot.id ? selectedDuration : null"
                                />
                                <InputError :message="assemblyErrors" /><Button
                                    type="submit"
                                    class="h-11"
                                    :disabled="!project.is_active || assemblyForm.processing || !canBuildAssembly"
                                    >Собрать монтаж</Button
                                >
                            </form>
                        </section>
                    </div>
                </div>
            </template>
        </div>
    </AppLayout>
</template>

<style scoped>
.episode-shots-page > nav {
    gap: 4px;
    border-bottom: 1px solid var(--border);
    padding-bottom: 8px;
}
.episode-shots-page > nav a:where(:not([aria-current])) {
    background: transparent;
}
@media (max-width: 699px) {
    .episode-shots-page > nav a {
        padding-inline: 8px;
        font-size: 13px;
    }
}

@media (max-width: 699px) {
    .episode-release-page {
        gap: 16px;
    }
    .episode-release-page > nav {
        gap: 4px;
    }
    .episode-release-page > nav a {
        padding-inline: 7px;
        font-size: 13px;
    }
}

.screenplay-page {
    container: screenplay / inline-size;
}
@container screenplay (max-width: 36rem) {
    .episode-columns {
        grid-template-columns: minmax(0, 1fr);
    }
}
.episode-overview-page > div:first-of-type > button {
    font-weight: 700;
}
.episode-overview-page > nav a {
    padding-inline: 12px;
}
.episode-overview-page > .episode-columns {
    margin-top: -4px;
}
@media (min-width: 1100px) {
    .screenplay-page {
        container: screenplay / inline-size;
    }
    @container screenplay (max-width: 36rem) {
        .episode-columns {
            grid-template-columns: minmax(0, 1fr);
        }
    }
    .episode-overview-page > div:first-of-type > button {
        margin-top: 6px;
    }
}
@media (max-width: 1099px) {
    .episode-overview-page > div:first-of-type {
        gap: 11px;
    }
}
@media (max-width: 767px) {
    .episode-overview-page {
        padding-top: 15px;
    }
    .episode-overview-page > div:first-of-type {
        gap: 14px;
    }
}

@media (max-width: 639px) {
    .trim-detail-page [aria-labelledby='montage-builder-title'] {
        padding: 14px 15px 20px;
    }
}
.trim-player video {
    width: 100%;
    max-height: 410px;
    margin: 16px 0;
    background: #111;
}
.scene-detail-page .episode-columns {
    display: block;
}
.scene-detail-page :is(.episode-video, .episode-columns > .studio-panel) {
    display: none;
}
.scene-detail-page .script-workspace > section:has(#script-generation-title) {
    order: 2;
}
.scene-detail-page .script-workspace {
    display: flex;
    flex-direction: column;
}
.scene-detail-page .script-workspace > section:has(#script-editor-title) > .flex:first-child,
.scene-detail-page .script-workspace > section:has(#script-editor-title) > .text-muted-foreground {
    display: none;
}
.scene-detail-page .script-workspace > section:has(#script-editor-title) {
    border: none;
    background: transparent;
    padding: 0;
    order: 0;
}
.scene-detail-page .script-workspace > details {
    order: 1;
}
@media (max-width: 767px) {
    .trim-player {
        display: none;
    }
}
@media (max-width: 1099px) {
    .episode-overview-page > div:first-of-type {
        flex-direction: column;
        align-items: flex-start;
    }
}
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
