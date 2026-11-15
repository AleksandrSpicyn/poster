<script setup lang="ts">
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
const form = useForm({ name: props.project?.name ?? '', prompt: props.project?.prompt ?? '' });
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
                            : 'Give your next video series a name and a clear creative direction.'
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
                        <p id="project-name-help" class="text-muted-foreground text-xs">A recognizable name for this video series.</p>
                        <InputError id="project-name-error" :message="form.errors.name" />
                    </div>
                    <div class="grid gap-2">
                        <Label for="project-prompt">Base prompt <span class="text-muted-foreground font-normal">(required)</span></Label>
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
                        <InputError id="project-prompt-error" :message="form.errors.prompt" />
                    </div>
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
