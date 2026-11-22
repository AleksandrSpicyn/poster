<script setup lang="ts">
import ChannelIcon from '@/components/ChannelIcon.vue';
import ProjectSeriesFields from '@/components/projects/ProjectSeriesFields.vue';
import Heading from '@/components/Heading.vue';
import InputError from '@/components/InputError.vue';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import AppLayout from '@/layouts/AppLayout.vue';
import type { BreadcrumbItem } from '@/types';
import type { ProjectFormProps } from '@/types/projects';
import { Head, Link, useForm, usePage } from '@inertiajs/vue3';
import { LoaderCircle, Save } from '@lucide/vue';
import { computed, nextTick, onMounted, ref, watch } from 'vue';
import { useRoute } from 'ziggy-js';

const props = defineProps<ProjectFormProps>();
const route = useRoute();
const formElement = ref<HTMLFormElement | null>(null);
const requestError = ref('');
const initialMode =
    new URL(usePage().url, 'http://poster.local').searchParams.get('format') === 'series' && 'series' in props.contentModes
        ? 'series'
        : Object.keys(props.contentModes)[0];
const form = useForm({
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
    series_lock_version: props.series?.lock_version ?? null,
    automation_enabled: props.series?.automation_enabled ?? true,
    episode_limit: props.series?.episode_limit ?? '',
    channel_ids: props.auth.can_manage_channels ? [...props.selectedChannelIds] : null,
});
const draftKey = `poster:project:${props.auth.user.id}:${props.project?.id ?? 'new'}`;
const serverData = JSON.stringify(form.data());
const storageUnavailable = ref(false);
function clearDraft() {
    try {
        sessionStorage.removeItem(draftKey);
    } catch {
        storageUnavailable.value = true;
    }
}
onMounted(() => {
    try {
        const saved = JSON.parse(sessionStorage.getItem(draftKey) ?? 'null');
        if (saved?.base === serverData && saved.fields && typeof saved.fields === 'object') {
            const fields = Object.fromEntries(
                Object.keys(form.data())
                    .filter((key) => Object.hasOwn(saved.fields, key))
                    .map((key) => [key, saved.fields[key]]),
            );
            Object.assign(form, fields);
        } else if (saved) {
            clearDraft();
        }
    } catch {
        clearDraft();
    }
    watch(
        () => form.data(),
        (fields) => {
            try {
                if (JSON.stringify(fields) === serverData) {
                    clearDraft();
                    return;
                }
                sessionStorage.setItem(draftKey, JSON.stringify({ base: serverData, fields }));
            } catch {
                storageUnavailable.value = true;
            }
        },
        { deep: true, flush: 'sync' },
    );
});
const isSeries = computed(() => form.content_mode === 'series');
const formErrors = computed<Record<string, string | undefined>>(() => form.errors);
const title = computed(() => (isSeries.value ? 'Паспорт сериала' : props.project ? 'Настройки проекта' : 'Новый проект'));
const cancelUrl = computed(() => (props.project ? route('projects.show', props.project.id) : route('projects.index')));
const breadcrumbs = computed<BreadcrumbItem[]>(() => [
    { title: 'Проекты', href: route('projects.index') },
    ...(props.project ? [{ title: props.project.name, href: route('projects.show', props.project.id) }] : []),
    { title: title.value, href: props.project ? route('projects.edit', props.project.id) : route('projects.create') },
]);

function submit() {
    if (form.processing) return;
    requestError.value = '';

    form.transform((data) => ({
        ...data,
        series_specification: isSeries.value ? { ...data.series_specification, premise: data.prompt } : null,
    }));
    form.submit(props.project ? 'patch' : 'post', props.project ? route('projects.update', props.project.id) : route('projects.store'), {
        preserveScroll: 'errors',
        onSuccess: clearDraft,
        onFinish: () => {
            if (form.hasErrors) nextTick(() => formElement.value?.querySelector<HTMLElement>('[aria-invalid="true"]')?.focus());
        },
        onHttpException: (response) => {
            if (response.status === 419) return;
            requestError.value = 'Не удалось сохранить проект. Проверьте данные и повторите отправку.';
            return false;
        },
        onNetworkError: () => {
            requestError.value = 'Связь прервана. После подключения повторите сохранение.';
            return false;
        },
    });
}
</script>

<template>
    <AppLayout :breadcrumbs="breadcrumbs">
        <Head :title="title" />
        <div class="workspace-page">
            <div class="flex flex-wrap items-start justify-between gap-4">
                <Heading :title="title" :description="project ? project.name + ' / Настройки' : 'Создание / Основные сведения'" />
                <Button type="submit" form="project-form" class="h-11" :disabled="form.processing">{{
                    project ? 'Сохранить' : 'Создать проект'
                }}</Button>
            </div>
            <div class="grid items-start gap-4 min-[1100px]:grid-cols-[min(28%,276px)_minmax(0,1fr)]">
                <nav
                    aria-label="Разделы проекта"
                    class="min-[1100px]:bg-card flex flex-wrap gap-2 min-[1100px]:flex-col min-[1100px]:rounded-xl min-[1100px]:border min-[1100px]:p-5"
                >
                    <h2 class="hidden text-lg font-bold min-[1100px]:block">В этом разделе</h2>
                    <a href="#project-name" class="bg-sidebar-accent text-sidebar-accent-foreground rounded-lg p-3 text-sm">Основное</a>
                    <a href="#project-active" class="bg-muted rounded-lg p-3 text-sm">Состояние</a>
                    <a v-if="auth.can_manage_channels" href="#publishing-channels" class="bg-muted rounded-lg p-3 text-sm">Каналы</a>
                </nav>
                <form
                    ref="formElement"
                    id="project-form"
                    class="bg-card min-w-0 space-y-6 rounded-xl border p-5 sm:p-7"
                    :aria-busy="form.processing"
                    @submit.prevent="submit"
                >
                    <h2 class="text-xl font-bold">Параметры</h2>
                    <p v-if="storageUnavailable" role="status">Браузер не смог сохранить черновик этой вкладки.</p>
                    <p v-if="requestError" role="alert" class="border-destructive/20 bg-destructive/5 text-destructive rounded-lg border p-3 text-sm">
                        {{ requestError }}
                    </p>
                    <div class="grid gap-2">
                        <Label for="project-name">Название <span class="text-muted-foreground font-normal">(обязательно)</span></Label>
                        <Input
                            id="project-name"
                            v-model="form.name"
                            name="name"
                            required
                            :maxlength="limits.name"
                            :disabled="form.processing"
                            autocomplete="off"
                            placeholder="Например, Лунный атлас"
                            class="h-11"
                            :aria-invalid="!!form.errors.name"
                            :aria-describedby="form.errors.name ? 'project-name-help project-name-error' : 'project-name-help'"
                        />
                        <p id="project-name-help" class="text-muted-foreground text-xs">A recognizable name for your content project.</p>
                        <InputError id="project-name-error" :message="form.errors.name" />
                    </div>
                    <div class="grid gap-2">
                        <Label for="project-mode">Формат</Label>
                        <select
                            id="project-mode"
                            v-model="form.content_mode"
                            :disabled="form.processing"
                            class="border-input bg-background focus-visible:ring-ring/50 h-11 rounded-md border px-3 text-sm outline-none focus-visible:ring-2"
                        >
                            <option v-for="(label, value) in contentModes" :key="value" :value="value" :disabled="!!series && value !== 'series'">
                                {{ value === 'series' ? 'Сериал' : value === 'short' ? 'Короткие ролики' : label }}
                            </option>
                        </select>
                        <p class="text-muted-foreground text-xs">Для сериала каждый запуск по расписанию создаёт следующий эпизод.</p>
                        <InputError :message="form.errors.content_mode" />
                    </div>
                    <div class="space-y-2 rounded-lg border p-4">
                        <label for="project-active" class="flex min-h-11 cursor-pointer items-start gap-3">
                            <input
                                id="project-active"
                                v-model="form.is_active"
                                type="checkbox"
                                class="accent-primary mt-1 size-4 shrink-0"
                                :disabled="form.processing"
                                aria-describedby="project-active-help"
                            />
                            <span class="text-sm">
                                <span class="font-medium">Проект активен</span>
                                <span id="project-active-help" class="text-muted-foreground mt-1 block leading-relaxed">
                                    Расписание пропускает приостановленный проект. Уже отправленные задания могут завершиться.
                                </span>
                            </span>
                        </label>
                        <InputError :message="form.errors.is_active" />
                    </div>
                    <div class="grid gap-2">
                        <Label for="project-prompt"
                            >{{ isSeries ? 'Мир и завязка' : 'Базовый промпт' }}
                            <span class="text-muted-foreground font-normal">(обязательно)</span></Label
                        >
                        <textarea
                            id="project-prompt"
                            v-model="form.prompt"
                            name="prompt"
                            required
                            :maxlength="limits.prompt"
                            :disabled="form.processing"
                            :aria-invalid="!!form.errors.prompt"
                            :aria-describedby="form.errors.prompt ? 'project-prompt-help project-prompt-error' : 'project-prompt-help'"
                            class="border-input bg-background placeholder:text-muted-foreground focus-visible:ring-ring/50 min-h-28 w-full resize-y rounded-md border px-3 py-3 text-sm leading-relaxed outline-none focus-visible:ring-2 disabled:cursor-not-allowed disabled:opacity-60"
                            :class="{ 'border-destructive': !!form.errors.prompt }"
                            placeholder="Опишите тему, аудиторию и настроение будущих роликов."
                        />
                        <div class="text-muted-foreground flex flex-wrap justify-between gap-2 text-xs">
                            <p id="project-prompt-help" class="max-w-prose">Используется при подготовке новых идей.</p>
                            <span class="tabular-nums">{{ form.prompt.length.toLocaleString() }} / {{ limits.prompt.toLocaleString() }}</span>
                        </div>
                        <InputError id="project-prompt-error" :message="form.errors.prompt" /><InputError
                            :message="form.errors.series_specification"
                        /><InputError :message="formErrors['series_specification.premise']" />
                    </div>
                    <ProjectSeriesFields
                        v-if="isSeries"
                        v-model:specification="form.series_specification"
                        v-model:automation-enabled="form.automation_enabled"
                        v-model:episode-limit="form.episode_limit"
                        v-model:output-mode="form.output_mode"
                        :output-modes="outputModes"
                        :cycle-structures="cycleStructures"
                        :scene-modes="sceneModes"
                        :processing="form.processing"
                        :errors="formErrors"
                        :audiences="audiences"
                        :audio-modes="audioModes"
                    />
                    <fieldset id="publishing-channels" tabindex="-1" v-if="auth.can_manage_channels" class="space-y-3 rounded-lg border p-4 sm:p-5">
                        <legend class="px-2 text-sm font-semibold">Каналы публикации</legend>
                        <p class="text-muted-foreground text-sm">Выберите каналы. Каждый из них можно использовать в нескольких проектах.</p>
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
                                    :disabled="form.processing"
                                    class="accent-primary size-4 shrink-0"
                                />
                                <ChannelIcon :channel="channel.type" />
                                <span class="min-w-0 flex-1 text-sm"
                                    ><span class="block font-medium break-words">{{ channel.name }}</span
                                    ><span class="text-muted-foreground block text-xs"
                                        >{{ channel.type_label }} ·
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
                    <div class="flex flex-wrap items-center justify-end gap-3 border-t pt-5">
                        <Button as-child variant="outline" class="h-11"><Link :href="cancelUrl" @click="clearDraft">Отмена</Link></Button>
                        <Button type="submit" class="h-11" :disabled="form.processing"
                            ><LoaderCircle v-if="form.processing" class="size-4 motion-safe:animate-spin" aria-hidden="true" /><Save
                                v-else
                                class="size-4"
                                aria-hidden="true"
                            />{{ form.processing ? 'Сохранение…' : project ? 'Сохранить изменения' : 'Создать проект' }}</Button
                        >
                    </div>
                </form>
            </div>
        </div>
    </AppLayout>
</template>
