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
import { Head, Link, useForm } from '@inertiajs/vue3';
import { ArrowLeft, LoaderCircle, Save } from '@lucide/vue';
import { computed, nextTick, ref } from 'vue';
import { useRoute } from 'ziggy-js';

const props = defineProps<ProjectFormProps>();
const route = useRoute();
const formElement = ref<HTMLFormElement | null>(null);
const requestError = ref('');
const form = useForm({
    name: props.project?.name ?? '',
    prompt: props.series?.specification.premise ?? props.project?.prompt ?? '',
    content_mode: props.project?.content_mode ?? Object.keys(props.contentModes)[0],
    is_active: props.project?.is_active ?? true,
    series_specification: {
        premise: '',
        visual_style: 'cinematic',
        language: 'en',
        audience: 'general',
        episode_duration_seconds: 10,
        narrative_rules: '',
        audio_mode: props.defaultAudioMode,
        ...props.series?.specification,
        story_outline: props.series?.specification.story_outline ?? '',
    },
    series_lock_version: props.series?.lock_version ?? null,
    automation_enabled: props.series?.automation_enabled ?? true,
    episode_limit: props.series?.episode_limit ?? '',
    channel_ids: props.auth.can_manage_channels ? [...props.selectedChannelIds] : null,
});
const isSeries = computed(() => form.content_mode === 'series');
const formErrors = computed<Record<string, string | undefined>>(() => form.errors);
const title = computed(() => (props.project ? 'Edit project' : 'New project'));
const cancelUrl = computed(() => (props.project ? route('projects.show', props.project.id) : route('projects.index')));
const breadcrumbs = computed<BreadcrumbItem[]>(() => [
    { title: 'Projects', href: route('projects.index') },
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
        onFinish: () => {
            if (form.hasErrors) nextTick(() => formElement.value?.querySelector<HTMLElement>('[aria-invalid="true"]')?.focus());
        },
        onHttpException: (response) => {
            if (response.status === 419) return;
            requestError.value = 'Your project could not be saved. Your changes are still here; please try again.';
            return false;
        },
        onNetworkError: () => {
            requestError.value = 'Connection lost. Your changes are still here; reconnect and try again.';
            return false;
        },
    });
}
</script>

<template>
    <AppLayout :breadcrumbs="breadcrumbs">
        <Head :title="title" />
        <div class="workspace-page max-w-6xl">
            <div>
                <Link :href="cancelUrl" class="text-muted-foreground hover:text-foreground mb-4 inline-flex min-h-11 items-center gap-2 text-sm"
                    ><ArrowLeft class="size-4" aria-hidden="true" />Back to {{ project ? 'project' : 'projects' }}</Link
                >
                <Heading
                    :title="title"
                    :description="
                        project
                            ? 'Refine the direction for future ideas. Existing generations and channels stay with this project.'
                            : 'Choose standalone videos or a series, then define its creative direction.'
                    "
                />
            </div>
            <div class="grid items-start gap-6 lg:grid-cols-[minmax(0,1fr)_17rem]">
                <form
                    ref="formElement"
                    class="bg-card min-w-0 space-y-6 rounded-xl border p-5 sm:p-7"
                    :aria-busy="form.processing"
                    @submit.prevent="submit"
                >
                    <p v-if="requestError" role="alert" class="border-destructive/20 bg-destructive/5 text-destructive rounded-lg border p-3 text-sm">
                        {{ requestError }}
                    </p>
                    <div class="grid gap-2">
                        <Label for="project-name">Project name <span class="text-muted-foreground font-normal">(required)</span></Label>
                        <Input
                            id="project-name"
                            v-model="form.name"
                            name="name"
                            required
                            :maxlength="limits.name"
                            :disabled="form.processing"
                            autocomplete="off"
                            placeholder="For example, Wildlife stories"
                            class="h-11"
                            :aria-invalid="!!form.errors.name"
                            :aria-describedby="form.errors.name ? 'project-name-help project-name-error' : 'project-name-help'"
                        />
                        <p id="project-name-help" class="text-muted-foreground text-xs">A recognizable name for your content project.</p>
                        <InputError id="project-name-error" :message="form.errors.name" />
                    </div>
                    <div class="grid gap-2">
                        <Label for="project-mode">Content type</Label>
                        <select
                            id="project-mode"
                            v-model="form.content_mode"
                            :disabled="form.processing"
                            class="border-input bg-background focus-visible:ring-ring/50 h-11 rounded-md border px-3 text-sm outline-none focus-visible:ring-2"
                        >
                            <option v-for="(label, value) in contentModes" :key="value" :value="value" :disabled="!!series && value !== 'series'">
                                {{ label }}
                            </option>
                        </select>
                        <p class="text-muted-foreground text-xs">A series project creates the next episode at each scheduled trigger.</p>
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
                                <span class="font-medium">Project enabled</span>
                                <span id="project-active-help" class="text-muted-foreground mt-1 block leading-relaxed">
                                    Disabled projects stop production and are skipped by the scheduler. Existing content, characters and channels stay
                                    available. Submitted work may still finish.
                                </span>
                            </span>
                        </label>
                        <InputError :message="form.errors.is_active" />
                    </div>
                    <div class="grid gap-2">
                        <Label for="project-prompt"
                            >{{ isSeries ? 'World and premise' : 'Base prompt' }}
                            <span class="text-muted-foreground font-normal">(required)</span></Label
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
                            class="border-input bg-background placeholder:text-muted-foreground focus-visible:ring-ring/50 min-h-72 w-full resize-y rounded-md border px-3 py-3 text-sm leading-relaxed outline-none focus-visible:ring-2 disabled:cursor-not-allowed disabled:opacity-60"
                            :class="{ 'border-destructive': !!form.errors.prompt }"
                            placeholder="Describe your subject, audience, tone and the kind of stories you want to tell."
                        />
                        <div class="text-muted-foreground flex flex-wrap justify-between gap-2 text-xs">
                            <p id="project-prompt-help" class="max-w-prose">This brief guides the ideas created for your project.</p>
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
                        :processing="form.processing"
                        :errors="formErrors"
                        :audiences="audiences"
                        :audio-modes="audioModes"
                    />
                    <fieldset v-if="auth.can_manage_channels" class="space-y-3 rounded-lg border p-4 sm:p-5">
                        <legend class="px-2 text-sm font-semibold">Publishing channels</legend>
                        <p class="text-muted-foreground text-sm">Select where this project publishes. A channel can be shared by several projects.</p>
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
                                                ? 'Disabled'
                                                : channel.connected
                                                  ? (channel.account_name ?? 'Connected')
                                                  : 'Connection needed'
                                        }}</span
                                    ></span
                                >
                            </label>
                        </div>
                        <p v-else class="text-muted-foreground rounded-lg border border-dashed p-4 text-sm">
                            No channels yet. Create a connection, then select it here.
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
                            >Manage channels</Link
                        >
                        <p class="text-muted-foreground text-xs">
                            Disabled or disconnected channels remain selected but cannot publish until they are ready.
                        </p>
                    </fieldset>
                    <div class="flex flex-wrap items-center justify-end gap-3 border-t pt-5">
                        <Button as-child variant="outline" class="h-11"><Link :href="cancelUrl">Cancel</Link></Button>
                        <Button type="submit" class="h-11" :disabled="form.processing"
                            ><LoaderCircle v-if="form.processing" class="size-4 motion-safe:animate-spin" aria-hidden="true" /><Save
                                v-else
                                class="size-4"
                                aria-hidden="true"
                            />{{ form.processing ? 'Saving…' : project ? 'Save changes' : 'Create project' }}</Button
                        >
                    </div>
                </form>
                <aside class="text-muted-foreground space-y-3 text-sm leading-relaxed lg:pt-2">
                    <h2 class="text-foreground font-semibold">Give your series a clear direction</h2>
                    <p>Include the subject, audience, setting and mood you want future ideas to share.</p>
                    <p>Useful details can be a recurring character, a visual style or a simple story format.</p>
                    <p v-if="project" class="border-t pt-3">Changes guide future generations. Your existing videos keep their original ideas.</p>
                </aside>
            </div>
        </div>
    </AppLayout>
</template>
