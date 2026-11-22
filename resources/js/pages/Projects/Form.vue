<script setup lang="ts">
import ConfirmationDialog from '@/components/ConfirmationDialog.vue';
import ChannelIcon from '@/components/ChannelIcon.vue';
import ProjectArtwork from '@/components/projects/ProjectArtwork.vue';
import ProjectSeriesFields from '@/components/projects/ProjectSeriesFields.vue';
import Heading from '@/components/Heading.vue';
import InputError from '@/components/InputError.vue';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import AppLayout from '@/layouts/AppLayout.vue';
import type { BreadcrumbItem } from '@/types';
import type { ProjectFormProps, ProjectSchedule } from '@/types/projects';
import { Head, Link, router, useForm, usePage } from '@inertiajs/vue3';
import { LoaderCircle, Save } from '@lucide/vue';
import { computed, nextTick, onMounted, onUnmounted, ref, watch } from 'vue';
import { useRoute } from 'ziggy-js';

const props = defineProps<ProjectFormProps>();
const route = useRoute();
const formElement = ref<HTMLFormElement | null>(null);
const requestError = ref('');
const deletion = useForm({});
const deleteError = ref('');
function deleteProject() {
    if (!props.project?.can_delete || props.project.blocking_runs_count || deletion.processing) return;
    deleteError.value = '';
    deletion.delete(route('projects.destroy', props.project.id), {
        onSuccess: clearDraft,
        onNetworkError: () => {
            deleteError.value = 'Связь прервана. Повторите удаление после подключения.';
            return false;
        },
        onHttpException: (response) => {
            if (response.status === 419) return;
            deleteError.value = 'Не удалось удалить проект. Обновите страницу и попробуйте ещё раз.';
            return false;
        },
    });
}
const initialMode =
    new URL(usePage().url, 'http://poster.local').searchParams.get('format') === 'series' && 'series' in props.contentModes
        ? 'series'
        : Object.keys(props.contentModes)[0];
const form = useForm({
    schedule: props.scheduleSettings?.configured
        ? { enabled: props.scheduleSettings.enabled, time: props.scheduleSettings.time, timezone: props.scheduleSettings.timezone }
        : (null as ProjectSchedule | null),
    settings_version: props.project?.settings_version ?? null,
    name: props.project?.name ?? '',
    prompt: props.series?.specification.premise ?? props.project?.prompt ?? '',
    content_mode: props.project?.content_mode ?? initialMode,
    is_active: props.project?.is_active ?? true,
    output_mode: props.series?.output_mode ?? 'episodes',
    series_specification: {
        premise: '',
        visual_style: 'cinematic',
        language: 'en',
        audience: 'general',
        episode_duration_seconds: 15,
        shot_duration_seconds: 15,
        narrative_rules: '',
        cycle_structure: 'continuous_story',
        scene_mode: 'scripted',
        audio_mode: props.defaultAudioMode,
        ...props.series?.specification,
        story_outline: props.series?.specification.story_outline ?? '',
    },
    cast_settings: props.series?.cast_settings ?? { lead_character_id: null as number | null, voice_description: '', use_series_voice: false },
    series_lock_version: props.series?.lock_version ?? null,
    automation_enabled: props.series?.automation_enabled ?? true,
    auto_publish: props.series?.auto_publish ?? false,
    episode_limit: props.series?.episode_limit ?? '',
    channel_ids: props.auth.can_manage_channels ? [...props.selectedChannelIds] : null,
});
const legacyDraftKey = `poster:project:${props.auth.user.id}:new`;
const draftKey = props.project ? `poster:project:${props.auth.user.id}:${props.project.id}` : `${legacyDraftKey}:${initialMode}`;
const serverData = form.data();
type Fields = typeof serverData;
type FieldKey = keyof Fields;
const draftBase = ref<Fields>(serverData);
const restored = ref(false);
const conflict = ref(false);
const storageUnavailable = ref(false);
const settingsLabels: Record<FieldKey, string> = {
    schedule: 'Расписание производства',
    settings_version: 'Версия',
    name: 'Название',
    prompt: 'Описание',
    content_mode: 'Формат производства',
    is_active: 'Состояние',
    output_mode: 'Формат выпуска',
    series_specification: 'Паспорт сериала',
    cast_settings: 'Герои и звук',
    series_lock_version: 'Версия сериала',
    automation_enabled: 'Автоматизация',
    auto_publish: 'Автоматическая публикация',
    episode_limit: 'Число эпизодов',
    channel_ids: 'Каналы проекта',
};
const fieldKeys = Object.keys(settingsLabels) as FieldKey[];
const changedKeys = computed(() =>
    fieldKeys.filter(
        (key) =>
            key !== 'settings_version' && key !== 'series_lock_version' && JSON.stringify(draftBase.value[key]) !== JSON.stringify(serverData[key]),
    ),
);
function clearDraft() {
    try {
        sessionStorage.removeItem(draftKey);
    } catch {
        storageUnavailable.value = true;
    }
}
function discardDraft() {
    conflict.value = false;
    restored.value = false;
    draftBase.value = serverData;
    form.reset();
    form.clearErrors();
    clearDraft();
}
function keepDraft() {
    const local = form.data();
    const edits = fieldKeys.filter(
        (key) => key !== 'settings_version' && key !== 'series_lock_version' && JSON.stringify(local[key]) !== JSON.stringify(draftBase.value[key]),
    );
    draftBase.value = serverData;
    conflict.value = false;
    form.reset();
    Object.assign(form, Object.fromEntries(edits.map((key) => [key, local[key]])));
    restored.value = true;
}
onMounted(() => {
    try {
        let stored = sessionStorage.getItem(draftKey);
        if (!props.project && !stored) {
            const legacy = sessionStorage.getItem(legacyDraftKey);
            if (legacy && JSON.parse(legacy)?.fields?.content_mode === initialMode) {
                sessionStorage.setItem(draftKey, legacy);
                sessionStorage.removeItem(legacyDraftKey);
                stored = legacy;
            }
        }
        if (stored) {
            const saved = JSON.parse(stored);
            const base = typeof saved?.base === 'string' ? JSON.parse(saved.base) : saved?.base;
            if (base && typeof base === 'object' && saved.fields && typeof saved.fields === 'object') {
                // Older sessions stored a JSON string and had no settings version.
                const prior = { ...serverData, ...base };
                draftBase.value = prior;
                conflict.value = fieldKeys.some((key) => JSON.stringify(prior[key]) !== JSON.stringify(serverData[key]));
                Object.assign(
                    form,
                    Object.fromEntries(fieldKeys.filter((key) => Object.hasOwn(saved.fields, key)).map((key) => [key, saved.fields[key]])),
                );
                restored.value = true;
            } else clearDraft();
        }
    } catch {
        clearDraft();
    }
    watch(
        () => form.data(),
        (fields) => {
            try {
                if (!conflict.value && JSON.stringify(fields) === JSON.stringify(serverData)) {
                    clearDraft();
                    return;
                }
                sessionStorage.setItem(draftKey, JSON.stringify({ base: draftBase.value, fields }));
                storageUnavailable.value = false;
            } catch {
                storageUnavailable.value = true;
            }
        },
        { deep: true, flush: 'sync' },
    );
});
const schedule = computed(() => form.schedule ?? props.scheduleSettings);
function changeSchedule(patch: Partial<ProjectSchedule>) {
    if (!schedule.value) return;
    const { enabled, time, timezone } = schedule.value;
    form.schedule = { enabled, time, timezone, ...patch };
}
const nextRunLabel = computed(() => {
    const saved = props.scheduleSettings;
    const current = schedule.value;
    if (!saved || !current) return '';
    if (!current.enabled) return 'Выключен';
    if (saved.time !== current.time || saved.timezone !== current.timezone || saved.enabled !== current.enabled) return 'После сохранения';
    return saved.next
        ? new Intl.DateTimeFormat('ru', { timeZone: saved.timezone, day: 'numeric', month: 'long', hour: '2-digit', minute: '2-digit' }).format(
              new Date(saved.next),
          )
        : 'Не запланирован';
});
const isSeries = computed(() => form.content_mode === 'series');
const section = ref('main');
const detailSection = computed(() => ['channels', 'format', 'cast', 'plan', 'automation', 'deletion'].includes(section.value));
const sections = computed((): Record<string, string> =>
    isSeries.value && section.value !== 'format'
        ? {
              main: 'Мир и сюжет',
              cast: 'Герои и звук',
              plan: 'Выпуски и автоматизация',
              ...(props.project && section.value !== 'cast' ? { automation: 'Автоматизация', access: 'Доступ' } : {}),
          }
        : props.project
          ? { main: 'Основное', channels: 'Каналы проекта', automation: 'Автоматизация', access: 'Доступ', deletion: 'Удаление проекта' }
          : { main: 'Основные сведения', format: 'Формат производства', channels: 'Каналы проекта' },
);
const formatOptions = computed(() => ['series', 'short'].filter((value) => Object.hasOwn(props.contentModes, value)));
const selectedChannelNames = computed(() =>
    props.channels.filter((channel) => form.channel_ids?.includes(channel.id)).map((channel) => channel.name),
);
const clippingUrl = computed(() => route('clipping.index', { view: 'select' }));
function selectFormat(event: Event) {
    const select = event.currentTarget as HTMLSelectElement;
    if (!props.project && select.value === 'clipping') {
        select.value = form.content_mode;
        router.visit(clippingUrl.value);
        return;
    }
    form.content_mode = select.value;
}
const sharedChannels = computed(() =>
    props.channels.filter(
        (channel) => props.selectedChannelIds.includes(channel.id) && channel.projects?.some((project) => project.id !== props.project?.id),
    ),
);
function readSection() {
    const hash = window.location.hash.slice(1);
    section.value =
        ['main', 'format', 'channels', 'cast', 'plan', 'automation', 'deletion'].includes(hash) || (hash === 'access' && props.project)
            ? hash
            : 'main';
}
onMounted(() => {
    readSection();
    window.addEventListener('hashchange', readSection);
});
onUnmounted(() => window.removeEventListener('hashchange', readSection));
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
    isSeries.value && section.value !== 'format' ? 'Паспорт сериала' : props.project ? 'Настройки проекта' : 'Новый проект',
);
const cancelUrl = computed(() => (props.project ? route('projects.show', props.project.id) : route('projects.index')));
const breadcrumbs = computed<BreadcrumbItem[]>(() => [
    isSeries.value && section.value !== 'format'
        ? { title: 'Сериалы', href: route('series.index') }
        : { title: 'Проекты', href: route('projects.index') },
]);

function submit() {
    if (form.processing || conflict.value) return;
    requestError.value = '';

    form.transform((data) => ({
        ...data,
        cast_settings: isSeries.value ? data.cast_settings : null,
        series_specification: isSeries.value ? { ...data.series_specification, premise: data.prompt } : null,
    }));
    form.submit(props.project ? 'patch' : 'post', props.project ? route('projects.update', props.project.id) : route('projects.store'), {
        preserveScroll: 'errors',
        onSuccess: clearDraft,
        onError: (errors) => {
            if (errors.name || errors.prompt) window.location.hash = 'main';
            else if (Object.keys(errors).some((key) => key.startsWith('cast_settings.'))) window.location.hash = 'cast';
            else if (errors.channel_ids || Object.keys(errors).some((key) => key.startsWith('channel_ids.'))) window.location.hash = 'channels';
            else if (Object.keys(errors).some((key) => key.startsWith('schedule.'))) window.location.hash = 'automation';
            else if (isSeries.value && !errors.settings_version)
                window.location.hash = Object.keys(errors).some((key) =>
                    [
                        'series_specification.audio_mode',
                        'series_specification.visual_style',
                        'series_specification.language',
                        'series_specification.audience',
                    ].includes(key),
                )
                    ? 'cast'
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
            class="workspace-page project-editor"
            :class="{
                'project-detail': detailSection,
                'schedule-editor': section === 'automation',
                'cast-editor': section === 'cast',
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
                    v-if="!['access', 'deletion'].includes(section) && !detailSection"
                    type="submit"
                    form="project-form"
                    class="h-11"
                    :disabled="form.processing || conflict"
                    >{{ isSeries ? 'Сохранить сериал' : project ? 'Сохранить' : 'Создать проект' }}</Button
                >
            </div>
            <div class="grid items-start gap-4 min-[1100px]:grid-cols-[min(28%,276px)_minmax(0,1fr)]">
                <nav
                    v-if="section !== 'format'"
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
                    <Link v-if="project && section !== 'cast'" :href="route('projects.cover.edit', project.id)" class="project-tab"
                        >Обложка проекта</Link
                    >
                </nav>
                <form
                    ref="formElement"
                    id="project-form"
                    class="bg-card min-w-0 space-y-6 rounded-xl border p-5 sm:p-7"
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
                    <div v-if="section === 'format' || (!project && initialMode !== 'series' && section === 'main')" class="grid gap-2">
                        <fieldset v-if="section === 'format'" class="grid gap-2">
                            <legend class="sr-only">Формат производства</legend>
                            <label
                                v-for="value in formatOptions"
                                :key="value"
                                class="flex min-h-14 cursor-pointer items-center gap-3 rounded-lg border p-3"
                            >
                                <input
                                    type="radio"
                                    v-model="form.content_mode"
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
                            <Link v-if="!project" :href="clippingUrl" class="flex min-h-14 items-center gap-3 rounded-lg border p-3 font-semibold"
                                ><span class="format-circle" aria-hidden="true"></span
                                ><span
                                    ><strong>Клиппинг</strong
                                    ><small class="text-muted-foreground block font-normal">Импорт готовых клипов из источника</small></span
                                ></Link
                            >
                        </fieldset>
                        <Label v-else for="project-mode">Формат</Label>
                        <select
                            v-if="section !== 'format'"
                            id="project-mode"
                            :value="form.content_mode"
                            @change="selectFormat"
                            :disabled="form.processing || conflict"
                            class="border-input bg-background focus-visible:ring-ring/50 h-11 rounded-md border px-3 text-sm outline-none focus-visible:ring-2"
                        >
                            <option v-for="(label, value) in contentModes" :key="value" :value="value" :disabled="!!series && value !== 'series'">
                                {{ value === 'series' ? 'Сериал' : value === 'short' ? 'Короткие ролики' : label }}
                            </option>
                            <option v-if="!project" value="clipping">Клиппинг</option>
                        </select>
                        <InputError :message="form.errors.content_mode" />
                    </div>
                    <div v-show="section === 'main'" class="grid gap-2">
                        <Label for="project-prompt">{{ isSeries ? 'Завязка' : project ? 'Базовый промпт' : 'Описание' }}</Label>
                        <textarea
                            rows="1"
                            id="project-prompt"
                            v-model="form.prompt"
                            name="prompt"
                            required
                            :maxlength="limits.prompt"
                            :disabled="form.processing || conflict"
                            :aria-invalid="!!form.errors.prompt"
                            :aria-describedby="form.errors.prompt ? 'project-prompt-error' : undefined"
                            class="border-input bg-background placeholder:text-muted-foreground focus-visible:ring-ring/50 min-h-12 w-full resize-y rounded-md border px-3 py-3 text-sm leading-relaxed outline-none focus-visible:ring-2 disabled:cursor-not-allowed disabled:opacity-60"
                            :class="{ 'border-destructive': !!form.errors.prompt }"
                            placeholder="Опишите тему, аудиторию и настроение будущих роликов."
                        />
                        <InputError id="project-prompt-error" :message="form.errors.prompt" /><InputError
                            :message="form.errors.series_specification"
                        /><InputError :message="formErrors['series_specification.premise']" />
                    </div>
                    <fieldset v-if="project && section === 'automation' && schedule" class="grid gap-4" :disabled="form.processing || conflict">
                        <label class="flex items-center gap-3 rounded-lg border p-3">
                            <input
                                type="checkbox"
                                :checked="schedule.enabled"
                                @change="changeSchedule({ enabled: ($event.target as HTMLInputElement).checked })"
                            />
                            <span><strong class="block">Автоматический запуск</strong><small>По расписанию проекта</small></span>
                        </label>
                        <div class="grid gap-2">
                            <Label for="project-start-time">Время запуска</Label>
                            <Input
                                id="project-start-time"
                                type="time"
                                required
                                :model-value="schedule.time"
                                @update:model-value="changeSchedule({ time: String($event) })"
                            />
                            <InputError :message="formErrors['schedule.time']" />
                        </div>
                        <div class="grid gap-2">
                            <Label for="project-timezone">Часовой пояс</Label>
                            <select
                                id="project-timezone"
                                class="list-select"
                                :value="schedule.timezone"
                                @change="changeSchedule({ timezone: ($event.target as HTMLSelectElement).value })"
                            >
                                <option v-for="timezone in scheduleSettings?.timezones" :key="timezone">{{ timezone }}</option></select
                            ><InputError :message="formErrors['schedule.timezone']" />
                        </div>
                        <p class="flex justify-between gap-3">
                            <span>Следующий запуск</span><output>{{ nextRunLabel }}</output>
                        </p>
                    </fieldset>
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
                    <div v-if="((!isSeries && section === 'main') || section === 'plan') && auth.can_manage_channels" class="grid gap-2">
                        <span class="text-muted-foreground text-sm">Каналы</span>
                        <a href="#channels" class="list-select flex items-center">{{ selectedChannelNames.join(', ') || 'Выбрать каналы' }}</a>
                    </div>
                    <dl v-if="project && section === 'access'" class="grid gap-4">
                        <div>
                            <dt class="text-muted-foreground text-sm">Владелец проекта</dt>
                            <dd>{{ auth.user.name }}</dd>
                        </div>
                        <div>
                            <dt class="text-muted-foreground text-sm">Электронная почта</dt>
                            <dd class="break-all">{{ auth.user.email }}</dd>
                        </div>
                        <div>
                            <dt class="text-muted-foreground text-sm">Роль</dt>
                            <dd>{{ auth.can_view_logs ? 'Администратор' : 'Владелец' }}</dd>
                        </div>
                    </dl>
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
                        <ConfirmationDialog
                            heading="Подтверждение удаления"
                            :title="'Удалить «' + project.name + '»?'"
                            description="После удаления восстановить проект нельзя."
                            action="Удалить проект"
                            destructive
                            :processing="deletion.processing"
                            :error="deleteError"
                            @confirm="deleteProject"
                        >
                            <template #trigger
                                ><Button
                                    variant="destructive"
                                    type="button"
                                    :disabled="!project.can_delete || !!project.blocking_runs_count || deletion.processing"
                                    >Удалить проект</Button
                                ></template
                            >
                            <div class="studio-row">
                                <dt>Проект</dt>
                                <dd>{{ project.name }} · {{ project.content_mode === 'series' ? 'сериал' : 'короткие ролики' }}</dd>
                            </div>
                            <div class="studio-row">
                                <dt>Активные задания</dt>
                                <dd>{{ project.blocking_runs_count || 'Нет' }}</dd>
                            </div>
                        </ConfirmationDialog>
                    </div>

                    <ProjectSeriesFields
                        v-if="isSeries && ['main', 'cast', 'plan'].includes(section)"
                        :characters="castCharacters ?? []"
                        v-model:cast="form.cast_settings"
                        :section="section"
                        v-model:specification="form.series_specification"
                        v-model:automation-enabled="form.automation_enabled"
                        v-model:auto-publish="form.auto_publish"
                        v-model:episode-limit="form.episode_limit"
                        v-model:output-mode="form.output_mode"
                        :output-modes="outputModes"
                        :cycle-structures="cycleStructures"
                        :scene-modes="sceneModes"
                        :processing="form.processing || conflict"
                        :errors="formErrors"
                        :audiences="audiences"
                        :audio-modes="audioModes"
                    />
                    <fieldset
                        id="publishing-channels"
                        tabindex="-1"
                        v-if="auth.can_manage_channels && section === 'channels'"
                        class="space-y-3 rounded-lg border p-4 sm:p-5"
                    >
                        <legend class="sr-only">Каналы публикации</legend>

                        <div v-if="channels.length" class="grid gap-2">
                            <label
                                v-for="channel in channels"
                                :key="channel.id"
                                class="hover:bg-muted/50 flex min-h-14 cursor-pointer items-center gap-3 rounded-lg border p-3"
                            >
                                <input
                                    v-model="form.channel_ids"
                                    type="checkbox"
                                    :value="channel.id"
                                    :disabled="form.processing || conflict"
                                    class="accent-primary size-4 shrink-0"
                                />
                                <ChannelIcon :channel="channel.type" />
                                <span class="min-w-0 flex-1 text-sm"
                                    ><span class="block font-medium break-words">{{ channel.name }} · {{ channel.type_label }}</span
                                    ><span class="text-muted-foreground block text-xs"
                                        >{{
                                            channel.projects?.length
                                                ? 'Выбран в проектах ' + channel.projects.map((project) => '«' + project.name + '»').join(', ')
                                                : 'Доступен для подключения'
                                        }}
                                        ·
                                        {{
                                            !channel.is_active
                                                ? 'Приостановлен'
                                                : channel.connected
                                                  ? (channel.account_name ?? 'Подключён')
                                                  : 'Нужно подключение'
                                        }}</span
                                    ></span
                                >
                            </label>
                        </div>
                        <p v-else class="text-muted-foreground rounded-lg border border-dashed p-4 text-sm">
                            Подключите канал, чтобы выбрать его для проекта.
                        </p>
                        <div class="border-primary bg-muted rounded-md border-l-4 px-3 py-2">
                            <strong class="block text-sm">Один владелец</strong>
                            <p class="text-muted-foreground text-sm">Снятие выбора затронет только этот проект.</p>
                        </div>
                        <InputError :message="form.errors.channel_ids" />
                        <InputError
                            v-for="(error, key) in formErrors"
                            :key="key"
                            :message="String(key).startsWith('channel_ids.') ? error : undefined"
                        />
                        <Link
                            :href="route('channels.index')"
                            class="text-primary inline-flex min-h-11 items-center text-sm font-medium underline underline-offset-4"
                            >Управление каналами</Link
                        >
                        <p class="text-muted-foreground text-xs">Отправка доступна после подключения и включения канала.</p>
                    </fieldset>
                    <div v-if="!['main', 'access', 'deletion'].includes(section)" class="flex flex-wrap items-center justify-end gap-3 border-t pt-5">
                        <Button v-if="!['automation', 'cast', 'format'].includes(section)" as-child variant="outline" class="h-11"
                            ><Link :href="cancelUrl" @click="clearDraft">Отмена</Link></Button
                        >
                        <Button v-if="!project && section === 'format'" as-child class="h-11">
                            <a href="#main">Сохранить формат</a>
                        </Button>
                        <Button v-else type="submit" class="h-11" :disabled="form.processing || conflict"
                            ><LoaderCircle v-if="form.processing" class="size-4 motion-safe:animate-spin" aria-hidden="true" /><Save
                                v-else-if="!['automation', 'cast', 'format'].includes(section)"
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
                <aside v-if="detailSection" class="studio-panel hidden min-w-0 space-y-3 min-[1100px]:block">
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
.project-editor #project-form > .grid {
    margin-block: 16px 0;
}
.project-editor #project-form > .grid > :is(input, select, textarea, .list-select) {
    min-height: 48px;
    background: var(--background);
    color: var(--foreground);
    font-size: 16px;
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
.schedule-editor #project-form fieldset > label {
    border: 0;
    border-bottom: 1px solid var(--border);
    border-radius: 0;
    padding: 0 0 14px;
}
.schedule-editor fieldset input[type='checkbox'] {
    width: 20px;
    height: 20px;
    accent-color: var(--primary);
}
.schedule-editor fieldset :is(input[type='time'], select) {
    height: 46px;
    background: var(--background);
    color: var(--foreground);
}
.schedule-editor fieldset > p {
    justify-content: start;
    align-items: baseline;
    gap: 16px;
}
.schedule-editor fieldset > p span {
    width: 36%;
}
.schedule-editor #project-form > div:last-child {
    justify-content: start;
    margin-top: 14px;
    padding-top: 12px;
}
@media (max-width: 767px) {
    .schedule-editor #project-form {
        padding: 14px 16px 20px;
    }
    .schedule-editor fieldset > p {
        flex-direction: column;
        gap: 0;
    }
    .schedule-editor fieldset > p span {
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
.format-editor fieldset input,
.format-circle {
    width: 18px;
    height: 18px;
    accent-color: var(--primary);
}
.format-circle {
    border: 1px solid var(--border);
    border-radius: 50%;
    flex-shrink: 0;
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
