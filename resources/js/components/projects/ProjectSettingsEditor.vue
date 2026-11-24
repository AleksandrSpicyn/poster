<script setup lang="ts">
import { useProjectSettingsDraft } from '@/composables/useProjectSettingsDraft';
import { useFormApi } from '@/lib/api/useFormApi';
import { useHashNavigation } from '@/composables/useHashNavigation';
import SeriesSettingsTabs from '@/components/projects/SeriesSettingsTabs.vue';
import { seriesSettingsSections } from '@/lib/seriesSettingsSections';
import DeleteProjectDialog from '@/components/projects/DeleteProjectDialog.vue';
import ProjectChannelFields from '@/components/projects/ProjectChannelFields.vue';
import ProjectArtwork from '@/components/projects/ProjectArtwork.vue';
import ProjectScheduleFields from '@/components/projects/ProjectScheduleFields.vue';
import ProjectSeriesFields from '@/components/projects/ProjectSeriesFields.vue';
import Heading from '@/components/Heading.vue';
import InputError from '@/components/InputError.vue';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import AppLayout from '@/layouts/AppLayout.vue';
import type { BreadcrumbItem } from '@/types';
import type { ProjectFormProps } from '@/types/projects';
import { Head, Link, router } from '@inertiajs/vue3';
import { LoaderCircle, Save } from '@lucide/vue';
import { computed, nextTick, ref } from 'vue';
import { useRoute } from '@/composables/useRoute';

const props = defineProps<ProjectFormProps>();
const submitForm = useFormApi();
const route = useRoute();
const formElement = ref<HTMLFormElement | null>(null);
const requestError = ref('');
const {
    form,
    dedicatedSeries,
    initialMode,
    restored,
    conflict,
    storageUnavailable,
    changedKeys,
    settingsLabels,
    draftBase,
    serverData,
    discardDraft,
    keepDraft,
    clearDraft,
} = useProjectSettingsDraft(props);
const schedule = computed(() => form.schedule ?? props.scheduleSettings);
const isSeries = computed(() => form.content_mode === 'series');
const section = ref('main');
const detailSection = computed(() => ['channels', 'format', 'cast', 'plan', 'automation', 'deletion'].includes(section.value));
const sections = computed((): Record<string, string> => {
    if (!props.project) {
        return {
            main: 'Основные сведения',
            ...(dedicatedSeries ? {} : { format: 'Формат производства' }),
            ...(props.auth.can_manage_channels ? { channels: 'Каналы проекта' } : {}),
            ...(isSeries.value ? { cast: seriesSettingsSections.cast, plan: seriesSettingsSections.plan } : {}),
        };
    }
    return isSeries.value
        ? seriesSettingsSections
        : { main: 'Основное', channels: 'Каналы проекта', automation: 'Автоматизация', deletion: 'Удаление проекта' };
});
const formatOptions = computed(() => ['series', 'short'].filter((value) => Object.hasOwn(props.contentModes, value)));
const selectedChannelNames = computed(() =>
    props.channels.filter((channel) => form.channel_ids?.includes(channel.id)).map((channel) => channel.name),
);
const sharedChannels = computed(() =>
    props.channels.filter(
        (channel) => props.selectedChannelIds.includes(channel.id) && channel.projects?.some((project) => project.id !== props.project?.id),
    ),
);
function readSection() {
    const requested = window.location.hash.slice(1);
    const hash = isSeries.value && requested === 'automation' ? 'plan' : requested;
    if (!props.project) {
        section.value = Object.hasOwn(sections.value, hash) ? hash : 'main';
        return;
    }
    section.value = ['main', 'format', 'channels', 'cast', 'plan', 'automation', 'deletion'].includes(hash) ? hash : 'main';
}
useHashNavigation(readSection);
function revealRequiredField(event: Event) {
    const input = event.target;
    if (!(input instanceof HTMLElement) || !['project-name', 'project-prompt'].includes(input.id) || section.value === 'main') return;
    event.preventDefault();
    section.value = 'main';
    window.location.hash = 'main';
    void nextTick(() => input.focus());
}
const formErrors = computed<Record<string, string | undefined>>(() => form.errors);
const title = computed(() =>
    props.project ? (isSeries.value ? 'Паспорт сериала' : 'Настройки проекта') : initialMode === 'series' ? 'Новый сериал' : 'Новый проект',
);
const cancelUrl = computed(() => (props.project ? route('projects.show', props.project.id) : route('projects.index')));
const breadcrumbs = computed<BreadcrumbItem[]>(() => [
    (props.project ? isSeries.value : initialMode === 'series')
        ? { title: 'Сериалы', href: route('series.index') }
        : { title: 'Проекты', href: route('projects.index') },
    ...(props.project
        ? [
              props.series
                  ? { title: props.series.name, href: route('series.show', { project: props.project.id, series: props.series.id }) }
                  : { title: props.project.name, href: route('projects.show', props.project.id) },
          ]
        : []),
    { title: title.value, href: props.project ? route('projects.edit', props.project.id) : route('projects.create') },
]);

function submit() {
    if (form.processing || conflict.value) return;
    requestError.value = '';

    form.transform((data) => ({
        ...data,
        cast_settings: isSeries.value ? data.cast_settings : null,
        series_specification: isSeries.value ? { ...data.series_specification, premise: data.prompt } : null,
    }));
    submitForm(form, props.project ? ['projects.update', props.project.id] : 'projects.store', {
        preserveScroll: 'errors',
        onSuccess: clearDraft,
        onError: (errors) => {
            if (errors.name || errors.prompt) window.location.hash = 'main';
            else if (errors.content_mode && !props.project) window.location.hash = 'format';
            else if (Object.keys(errors).some((key) => key.startsWith('cast_settings.'))) window.location.hash = 'cast';
            else if (errors.channel_ids || Object.keys(errors).some((key) => key.startsWith('channel_ids.'))) window.location.hash = 'channels';
            else if (Object.keys(errors).some((key) => key.startsWith('schedule.'))) window.location.hash = isSeries.value ? 'plan' : 'automation';
            else if (isSeries.value && !errors.settings_version)
                window.location.hash = Object.keys(errors).some((key) =>
                    ['series_specification.visual_style', 'series_specification.language', 'series_specification.audience'].includes(key),
                )
                    ? 'main'
                    : 'plan';
            if (errors.settings_version && props.project)
                router.visit(route('projects.edit', props.project.id), { replace: true, preserveState: false, preserveScroll: true });
        },
        onFinish: () => {
            if (form.hasErrors) nextTick(() => formElement.value?.querySelector<HTMLElement>('[aria-invalid="true"]')?.focus());
        },
        onHttpException: (response) => {
            if (response.status === 419) return;
            requestError.value = 'Не удалось сохранить проект. Проверьте данные и повторите отправку.';
            return false;
        },
        onNetworkError: () => {
            requestError.value = storageUnavailable.value
                ? 'Сервер недоступен. Оставьте вкладку открытой и повторите сохранение.'
                : 'Сервер недоступен. В этой вкладке сохранилась локальная копия.';
            return false;
        },
    });
}
</script>

<template>
    <AppLayout :breadcrumbs="breadcrumbs">
        <Head :title="title" />
        <div
            class="workspace-page project-editor wrap-anywhere"
            :class="{
                'project-detail': !project || detailSection || isSeries,
                'project-main': section === 'main' && !isSeries,
                'schedule-editor': section === 'automation',
                'cast-editor': section === 'cast',
                'channel-editor': section === 'channels',
                'format-editor': section === 'format',
            }"
        >
            <div class="flex flex-wrap items-start justify-between gap-4">
                <Heading
                    :title="title"
                    :description="
                        detailSection
                            ? section === 'format'
                                ? 'Формат производства'
                                : undefined
                            : isSeries
                              ? 'Создание или изменение проекта сериала'
                              : project
                                ? project.name + ' / Настройки'
                                : 'Создание / Основные сведения'
                    "
                />
                <Button
                    v-if="section !== 'deletion' && !detailSection"
                    type="submit"
                    form="project-form"
                    class="h-11"
                    :disabled="form.processing || conflict"
                    >{{ project ? 'Сохранить' : isSeries ? 'Создать сериал' : 'Создать проект' }}</Button
                >
            </div>
            <div class="grid items-start gap-4 min-[1100px]:grid-cols-[min(28%,276px)_minmax(0,1fr)]">
                <SeriesSettingsTabs v-if="isSeries && project" :project-id="project.id" :section="section" />
                <nav
                    v-else-if="!project || section !== 'format'"
                    aria-label="Разделы проекта"
                    class="min-[1100px]:bg-card flex flex-wrap gap-2 min-[1100px]:flex-col min-[1100px]:rounded-xl min-[1100px]:border min-[1100px]:p-5"
                >
                    <h2 class="hidden text-lg font-bold min-[1100px]:block">В этом разделе</h2>
                    <a
                        v-for="(label, key) in sections"
                        :key="key"
                        :href="'#' + key"
                        :aria-current="section === key ? 'page' : undefined"
                        class="project-tab"
                        >{{ label }}</a
                    >
                    <Link v-if="project" :href="route('projects.cover.edit', project.id)" class="project-tab">Обложка проекта</Link>
                </nav>
                <form
                    id="project-form"
                    ref="formElement"
                    class="bg-card grid min-w-0 gap-6 rounded-xl border p-5 sm:p-7"
                    :aria-busy="form.processing"
                    @invalid.capture="revealRequiredField"
                    @submit.prevent="submit"
                >
                    <h2 class="text-xl font-bold">
                        {{
                            section === 'main'
                                ? 'Параметры'
                                : section === 'channels'
                                  ? 'Каналы проекта'
                                  : section === 'automation'
                                    ? 'Расписание'
                                    : (sections[section] ?? (section === 'format' ? 'Формат производства' : ''))
                        }}
                    </h2>
                    <section v-if="conflict" role="alert" class="bg-muted border-l-4 border-amber-500 p-4">
                        <h3 class="font-semibold">Серверная версия изменилась</h3>
                        <p>
                            Ваша копия основана на версии {{ draftBase.settings_version }}. Сейчас сохранена версия {{ project?.settings_version }}.
                        </p>
                        <p v-if="changedKeys.length">Изменившиеся поля: {{ changedKeys.map((key) => settingsLabels[key]).join(', ') }}</p>
                        <p v-if="changedKeys.includes('name')" class="mt-2 break-words">{{ form.name }} → {{ serverData.name }}</p>
                        <div class="mt-3 flex flex-wrap gap-2">
                            <Button type="button" variant="outline" @click="keepDraft">Оставить локальную копию</Button
                            ><Button type="button" variant="outline" @click="discardDraft">Загрузить версию {{ project?.settings_version }}</Button>
                        </div>
                    </section>
                    <section v-else-if="restored" role="status" class="bg-muted border-primary border-l-4 p-4">
                        <h3 class="font-semibold">Черновик восстановлен</h3>
                        <p>Ввод из этой вкладки восстановлен после перезагрузки.</p>
                        <p v-if="project?.updated_at">
                            Версия {{ project.settings_version }} ·
                            {{ new Date(project.updated_at).toLocaleString('ru-RU', { timeZone: 'UTC' }) }} UTC
                        </p>
                        <Button type="button" variant="outline" @click="discardDraft">Сбросить</Button>
                    </section>
                    <p v-if="storageUnavailable" role="status">Браузер не смог сохранить черновик этой вкладки.</p>
                    <p v-if="requestError" role="alert" class="border-destructive/20 bg-destructive/5 text-destructive rounded-lg border p-3 text-sm">
                        <strong class="block">Сохранение не выполнено</strong>{{ requestError }}
                        <Button type="submit" variant="outline" :disabled="form.processing || conflict">Повторить сохранение</Button>
                    </p>
                    <div v-show="section === 'main'" class="grid gap-2">
                        <Label for="project-name">Название</Label>
                        <Input
                            id="project-name"
                            v-model="form.name"
                            name="name"
                            required
                            :maxlength="limits.name"
                            :disabled="form.processing || conflict"
                            autocomplete="off"
                            placeholder="Например, Лунный атлас"
                            class="h-11"
                            :aria-invalid="!!form.errors.name"
                            :aria-describedby="form.errors.name ? 'project-name-error' : undefined"
                        />
                        <InputError id="project-name-error" :message="form.errors.name" />
                    </div>
                    <div v-if="section === 'format'" class="grid gap-2">
                        <fieldset class="grid gap-2">
                            <legend class="sr-only">Формат производства</legend>
                            <label
                                v-for="value in formatOptions"
                                :key="value"
                                class="flex min-h-14 cursor-pointer items-center gap-3 rounded-lg border p-3"
                            >
                                <input
                                    v-model="form.content_mode"
                                    type="radio"
                                    :value="value"
                                    :disabled="form.processing || conflict || (!!series && value !== 'series')"
                                    name="format-choice"
                                />
                                <span
                                    ><strong>{{ value === 'series' ? 'Сериал' : 'Короткие ролики' }}</strong>
                                    <small class="text-muted-foreground block">{{
                                        value === 'series' ? 'Повторяющиеся герои и связанные эпизоды' : 'Независимые сценарии и монтаж'
                                    }}</small></span
                                >
                            </label>
                        </fieldset>
                        <InputError :message="form.errors.content_mode" />
                    </div>
                    <div v-show="section === 'main'" class="grid gap-2">
                        <Label for="project-prompt">{{ isSeries ? 'Завязка' : project ? 'Базовый промпт' : 'Описание' }}</Label>
                        <textarea
                            id="project-prompt"
                            v-model="form.prompt"
                            rows="5"
                            name="prompt"
                            required
                            :maxlength="limits.prompt"
                            :disabled="form.processing || conflict"
                            :aria-invalid="!!form.errors.prompt"
                            :aria-describedby="form.errors.prompt ? 'project-prompt-error' : undefined"
                            class="border-input bg-background placeholder:text-muted-foreground focus-visible:ring-ring/50 w-full resize-y rounded-md border px-3 py-3 text-sm leading-relaxed outline-none focus-visible:ring-2 disabled:cursor-not-allowed disabled:opacity-60"
                            :class="{ 'border-destructive': !!form.errors.prompt }"
                            placeholder="Опишите тему, аудиторию и настроение будущих роликов."
                        />
                        <InputError id="project-prompt-error" :message="form.errors.prompt" /><InputError
                            :message="form.errors.series_specification"
                        /><InputError :message="formErrors['series_specification.premise']" />
                    </div>
                    <div v-if="!isSeries && project && section === 'main'" class="grid gap-2">
                        <Label for="project-active">Состояние</Label>
                        <select id="project-active" v-model="form.is_active" class="list-select" :disabled="form.processing || conflict">
                            <option :value="true">Активен</option>
                            <option :value="false">Приостановлен</option>
                        </select>
                        <InputError :message="form.errors.is_active" />
                    </div>
                    <div v-if="isSeries && section === 'main'" class="grid gap-2">
                        <Label for="episode-limit">Число эпизодов</Label>
                        <Input
                            id="episode-limit"
                            v-model="form.episode_limit"
                            type="number"
                            min="1"
                            max="1000"
                            placeholder="Без ограничения"
                            :disabled="form.processing || conflict"
                        />
                        <InputError :message="form.errors.episode_limit" />
                    </div>
                    <div v-if="isSeries && section === 'main'" class="grid gap-2">
                        <Label for="passport-output">Режим выпуска</Label>
                        <select id="passport-output" v-model="form.output_mode" class="list-select" :disabled="form.processing || conflict">
                            <option v-for="(_, mode) in outputModes" :key="mode" :value="mode">
                                {{ mode === 'episodes' ? 'По эпизодам' : 'Одно полное видео' }}
                            </option>
                        </select>
                        <InputError :message="form.errors.output_mode" />
                    </div>
                    <div v-if="project && ((!isSeries && section === 'main') || section === 'plan') && auth.can_manage_channels" class="grid gap-2">
                        <span class="text-muted-foreground text-sm">Каналы</span>
                        <a href="#channels" class="list-select flex items-center">{{ selectedChannelNames.join(', ') || 'Выбрать каналы' }}</a>
                    </div>
                    <div v-if="project && section === 'deletion'" class="space-y-4">
                        <div class="bg-muted rounded-lg border-l-4 border-amber-500 p-4">
                            <strong>Удаление проекта</strong>
                            <p class="text-muted-foreground text-sm">Удаление доступно для пустого проекта без связанных каналов и производства.</p>
                        </div>
                        <div class="studio-row">
                            <span>Проект</span><strong>{{ project.name }}</strong>
                        </div>
                        <div class="studio-row">
                            <span>Активное производство</span><span>{{ project.blocking_runs_count || 'Нет' }}</span>
                        </div>
                        <div class="studio-row">
                            <span>Общий канал</span><span>{{ sharedChannels.map((channel) => channel.name).join(', ') || 'Нет' }}</span>
                        </div>
                        <DeleteProjectDialog :project="project" @deleted="clearDraft" />
                    </div>

                    <ProjectSeriesFields
                        v-if="isSeries && ['main', 'cast', 'plan'].includes(section)"
                        v-model:cast="form.cast_settings"
                        v-model:specification="form.series_specification"
                        v-model:automation-enabled="form.automation_enabled"
                        v-model:auto-publish="form.auto_publish"
                        v-model:episode-limit="form.episode_limit"
                        v-model:output-mode="form.output_mode"
                        :characters="castCharacters ?? []"
                        :section="section"
                        :output-modes="outputModes"
                        :cycle-structures="cycleStructures"
                        :scene-modes="sceneModes"
                        :processing="form.processing || conflict"
                        :errors="formErrors"
                        :audiences="audiences"
                        :audio-modes="audioModes"
                    />
                    <ProjectScheduleFields
                        v-if="project && schedule && (isSeries ? section === 'plan' : section === 'automation')"
                        :model-value="schedule"
                        :saved="scheduleSettings"
                        :disabled="form.processing || conflict"
                        :is-series="isSeries"
                        :errors="formErrors"
                        @update:model-value="form.schedule = $event"
                    />
                    <ProjectChannelFields
                        v-if="auth.can_manage_channels && section === 'channels'"
                        v-model="form.channel_ids"
                        :channels="channels"
                        :disabled="form.processing || conflict"
                        :errors="formErrors"
                    />
                    <div
                        v-if="!['main', 'deletion'].includes(section)"
                        :class="section === 'channels' ? 'justify-start' : 'justify-end border-t pt-5'"
                        class="flex flex-wrap items-center gap-3"
                    >
                        <Button v-if="!['automation', 'cast', 'format', 'channels'].includes(section)" as-child variant="outline" class="h-11"
                            ><Link :href="cancelUrl" @click="clearDraft">Отмена</Link></Button
                        >
                        <Button v-if="!project && ['format', 'channels'].includes(section)" as-child class="h-11">
                            <a href="#main">К основным сведениям</a>
                        </Button>
                        <Button v-else type="submit" class="h-11" :disabled="form.processing || conflict"
                            ><LoaderCircle v-if="form.processing" class="size-4 motion-safe:animate-spin" aria-hidden="true" /><Save
                                v-else-if="!['automation', 'cast', 'format', 'channels'].includes(section)"
                                class="size-4"
                                aria-hidden="true"
                            />{{
                                form.processing
                                    ? 'Сохранение…'
                                    : section === 'automation'
                                      ? 'Сохранить расписание'
                                      : section === 'channels'
                                        ? 'Сохранить каналы'
                                        : section === 'format'
                                          ? 'Сохранить формат'
                                          : isSeries
                                            ? 'Сохранить паспорт'
                                            : 'Сохранить изменения'
                            }}</Button
                        >
                    </div>
                </form>
                <aside v-if="!project" class="studio-panel hidden min-w-0 min-[1100px]:block" aria-label="Сводка проекта">
                    <h2 class="text-xl font-bold">{{ form.name.trim() || 'Новый ' + (isSeries ? 'сериал' : 'проект') }}</h2>
                    <dl class="mt-4 grid gap-3 break-words">
                        <div>
                            <dt>Формат</dt>
                            <dd>{{ isSeries ? 'Сериал' : 'Короткие ролики' }}</dd>
                        </div>
                        <div v-if="form.prompt.trim()">
                            <dt>{{ isSeries ? 'Завязка' : 'Описание' }}</dt>
                            <dd class="whitespace-pre-wrap">{{ form.prompt }}</dd>
                        </div>
                        <div v-if="selectedChannelNames.length">
                            <dt>Каналы</dt>
                            <dd>{{ selectedChannelNames.join(', ') }}</dd>
                        </div>
                        <div v-if="isSeries && form.episode_limit">
                            <dt>Количество эпизодов</dt>
                            <dd>{{ form.episode_limit }}</dd>
                        </div>
                    </dl>
                </aside>
                <aside v-else-if="detailSection || isSeries" class="studio-panel hidden min-w-0 space-y-3 min-[1100px]:block">
                    <ProjectArtwork
                        v-if="project"
                        :name="project.name"
                        :cover="project.cover"
                        :class="section === 'cast' ? 'h-[106px] w-full rounded-lg' : 'aspect-[3/1] rounded-lg'"
                    />
                    <h2 class="text-lg font-semibold">
                        {{ project?.name || (isSeries && section !== 'format' ? form.name || 'Паспорт сериала' : 'Мои проекты') }}
                    </h2>
                    <template v-if="section === 'cast'">
                        <div><span class="text-muted-foreground block text-sm">Проект</span>{{ project?.name || form.name }}</div>
                        <div>
                            <span class="text-muted-foreground block text-sm">Сериал</span
                            >{{ series?.last_episode_number ? 'Эпизод ' + String(series.last_episode_number).padStart(2, '0') : 'Нет эпизодов' }}
                        </div>
                    </template>
                    <template v-else>
                        <div><span class="text-muted-foreground block text-sm">Студия</span>{{ auth.user.name }}</div>
                        <div><span class="text-muted-foreground block text-sm">Рабочая область</span>Моя студия</div>
                    </template>
                </aside>
            </div>
        </div>
    </AppLayout>
</template>

<style scoped>
.project-main button[form='project-form'] {
    font-weight: 700;
}
.project-tab {
    display: block;
    padding: 10px 12px;
    border-radius: 8px;
    font-size: 14px;
    color: var(--muted-foreground);
}
.project-tab[aria-current='page'] {
    background: var(--sidebar-accent);
    color: var(--sidebar-accent-foreground);
    font-weight: 600;
}
.project-editor #project-form {
    padding: 20px 24px 48px;
}
.project-editor #project-form > .grid > :is(input, select, textarea, .list-select) {
    min-height: 48px;
    background-color: var(--background);
    color: var(--foreground);
    font-size: 16px;
}
.project-editor #project-form :deep(:is(select, input[data-slot='input'])) {
    width: 100%;
    min-width: 0;
    height: 3rem;
    border-radius: var(--radius-md);
    background-color: var(--background);
    color: var(--foreground);
    font-size: 1rem;
}
.project-editor #project-form :deep(select) {
    padding-inline: 0.75rem 2.5rem;
}
.project-editor #project-form :deep(select:focus-visible) {
    border-color: var(--ring);
    outline: 2px solid var(--ring);
    outline-offset: 1px;
}
.schedule-editor #project-form :deep(fieldset > label) {
    border: 0;
    border-bottom: 1px solid var(--border);
    border-radius: 0;
    padding: 0 0 14px;
}
.project-editor #project-form > .grid > label {
    font-weight: 400;
    color: var(--muted-foreground);
}
@media (min-width: 1100px) {
    .project-editor > .grid {
        grid-template-columns: 276px minmax(0, 852px);
    }
    .project-editor nav {
        min-height: 240px;
        padding: 16px;
        gap: 6px;
    }
    .project-editor nav h2 {
        padding: 0 8px 8px;
    }
}
@media (max-width: 1099px) {
    .project-tab {
        background: var(--muted);
    }
    .project-editor > .grid {
        gap: 16px;
    }
    .project-editor > div:first-child {
        flex-direction: column;
    }
}
@media (max-width: 767px) {
    .project-main.workspace-page {
        gap: 24px;
    }
    .project-main > .grid {
        gap: 22px;
    }
    .project-editor {
        padding-block-start: 14px;
        gap: 20px;
    }
    .project-tab {
        padding: 10px;
        font-size: 13px;
    }
    .project-editor #project-form {
        padding: 16px 16px 32px;
    }
}
.project-detail nav h2 {
    display: none;
}
.project-detail #project-form {
    padding: 20px 24px;
}
.project-detail #publishing-channels {
    padding: 0;
    border: 0;
}
@media (min-width: 1100px) {
    .project-editor.project-detail > .grid {
        grid-template-columns: minmax(0, 2.3fr) minmax(0, 1fr);
        gap: 20px;
    }
    .project-detail nav {
        min-height: 0;
        padding: 0;
        grid-column: 1 / -1;
        flex-direction: row;
        border: 0;
        background: transparent;
    }
    .project-detail nav .project-tab {
        background: var(--muted);
    }
    .project-detail nav .project-tab[aria-current='page'] {
        background: var(--sidebar-accent);
    }
    .project-detail #project-form {
        grid-column: 1;
    }
}
.schedule-editor :deep(fieldset input[type='checkbox']) {
    width: 20px;
    height: 20px;
    accent-color: var(--primary);
}
.schedule-editor :deep(fieldset :is(input[type='time'], select)) {
    height: 46px;
    background-color: var(--background);
    color: var(--foreground);
}
.schedule-editor :deep(fieldset > p) {
    justify-content: start;
    align-items: baseline;
    gap: 16px;
}
.schedule-editor :deep(fieldset > p span) {
    width: 36%;
}
.schedule-editor #project-form > div:last-child {
    justify-content: start;
    margin-top: 14px;
    padding-top: 12px;
}
@media (max-width: 767px) {
    .channel-editor #project-form,
    .schedule-editor #project-form {
        padding: 14px 16px 20px;
    }
    .schedule-editor :deep(fieldset > p) {
        flex-direction: column;
        gap: 0;
    }
    .schedule-editor :deep(fieldset > p span) {
        width: auto;
    }
}
.cast-editor #project-form > .justify-end {
    justify-content: flex-start;
    padding-top: 12px;
    margin-top: 14px;
}
.cast-editor aside {
    padding: 15px;
}
.cast-editor #project-form {
    padding-bottom: 15px;
}
@media (max-width: 767px) {
    .cast-editor {
        padding-top: 15px;
    }
}
@media (min-width: 768px) {
    .cast-editor {
        padding-top: 23px;
        gap: 16px;
    }
}
@media (min-width: 1100px) {
    .cast-editor {
        padding-top: 19px;
    }
    .project-editor.cast-editor > .grid {
        grid-template-columns: minmax(0, 2.36fr) minmax(0, 1fr);
        gap: 20px;
    }
}
@media (min-width: 1920px) {
    .cast-editor {
        padding-top: 17px;
    }
    .project-editor.cast-editor > .grid {
        max-width: 1236px;
        grid-template-columns: minmax(0, 860px) minmax(0, 354px);
        gap: 22px;
    }
}
.format-editor #project-form > .grid {
    margin-top: 4px;
}
.format-editor fieldset {
    gap: 0;
}
.format-editor fieldset > :is(label, a) {
    padding: 12px 0;
    border: 0;
    border-bottom: 1px solid var(--border);
    border-radius: 0;
    min-height: 64px;
}
.format-editor fieldset input {
    width: 18px;
    height: 18px;
    accent-color: var(--primary);
}
.format-editor #project-form > div:last-child {
    margin-top: 12px;
    border: 0;
    padding: 0;
    justify-content: start;
}
@media (min-width: 1100px) {
    .project-editor.project-detail > .grid {
        grid-template-columns: minmax(0, 2.36fr) minmax(0, 1fr);
    }
}
</style>
