<script setup lang="ts">
import Heading from '@/components/Heading.vue';
import InputError from '@/components/InputError.vue';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import AppLayout from '@/layouts/AppLayout.vue';
import type { SeriesSummary } from '@/types/series';
import { Head, Link, useForm } from '@inertiajs/vue3';
import { computed } from 'vue';
import { useRoute } from 'ziggy-js';

const props = defineProps<{
    project: { id: number; name: string };
    series: SeriesSummary | null;
    audiences: Record<string, string>;
    audioModes: Record<string, string>;
    defaultAudioMode: string;
}>();
const route = useRoute();
const title = computed(() => (props.series ? 'Edit series' : 'New series'));
const params = computed(() => ({ project: props.project.id, series: props.series?.id }));
const cancelUrl = computed(() => (props.series ? route('series.show', params.value) : route('projects.show', props.project.id)));
const form = useForm({
    name: props.series?.name ?? '',
    specification: {
        premise: '',
        visual_style: 'cinematic',
        language: 'en',
        audience: 'general',
        episode_duration_seconds: 30,
        narrative_rules: '',
        audio_mode: props.defaultAudioMode,
        ...props.series?.specification,
    },
    lock_version: props.series?.lock_version ?? null,
});
const formErrors = computed<Record<string, string | undefined>>(() => form.errors);
const breadcrumbs = computed(() => [
    { title: 'Projects', href: route('projects.index') },
    { title: props.project.name, href: route('projects.show', props.project.id) },
    { title: title.value, href: props.series ? route('series.edit', params.value) : route('series.create', props.project.id) },
]);

function submit() {
    form.submit(props.series ? 'patch' : 'post', props.series ? route('series.update', params.value) : route('series.store', props.project.id), {
        preserveScroll: 'errors',
        preserveState: 'errors',
    });
}
</script>

<template>
    <AppLayout :breadcrumbs="breadcrumbs">
        <Head :title="title" />
        <div class="workspace-page max-w-4xl">
            <Heading :title="title" description="Define the world, visual style and storytelling rules that future episodes share." />
            <form class="bg-card grid gap-6 rounded-xl border p-5 sm:p-7" :aria-busy="form.processing" @submit.prevent="submit">
                <InputError :message="form.errors.lock_version" /><InputError :message="formErrors.series" />
                <InputError :message="form.errors.specification" />
                <div class="grid gap-2">
                    <Label for="series-name">Series name</Label
                    ><Input
                        id="series-name"
                        v-model="form.name"
                        required
                        maxlength="255"
                        :disabled="form.processing"
                        :aria-invalid="!!form.errors.name"
                    />
                    <InputError :message="form.errors.name" />
                </div>
                <div class="grid gap-2">
                    <Label for="series-premise">World and premise</Label>
                    <textarea
                        id="series-premise"
                        v-model="form.specification.premise"
                        required
                        maxlength="20000"
                        rows="7"
                        :disabled="form.processing"
                        :aria-invalid="!!form.errors['specification.premise']"
                        class="border-input bg-background focus-visible:ring-ring/50 w-full resize-y rounded-md border p-3 text-sm leading-relaxed outline-none focus-visible:ring-2"
                    />
                    <InputError :message="form.errors['specification.premise']" />
                </div>
                <div class="grid gap-5 sm:grid-cols-2">
                    <div class="grid gap-2">
                        <Label for="series-style">Visual style</Label
                        ><Input
                            id="series-style"
                            v-model="form.specification.visual_style"
                            maxlength="2000"
                            :disabled="form.processing"
                            placeholder="For example, watercolor animation"
                        /><InputError :message="form.errors['specification.visual_style']" />
                    </div>
                    <div class="grid gap-2">
                        <Label for="series-language">Language</Label
                        ><Input
                            id="series-language"
                            v-model="form.specification.language"
                            maxlength="35"
                            :disabled="form.processing"
                            placeholder="en"
                        /><InputError :message="form.errors['specification.language']" />
                    </div>
                    <div class="grid gap-2">
                        <Label for="series-audience">Audience</Label
                        ><select
                            id="series-audience"
                            v-model="form.specification.audience"
                            :disabled="form.processing"
                            class="border-input bg-background focus-visible:ring-ring/50 h-11 rounded-md border px-3 text-sm outline-none focus-visible:ring-2"
                        >
                            <option v-for="(label, value) in audiences" :key="value" :value="value">{{ label }}</option></select
                        ><InputError :message="form.errors['specification.audience']" />
                    </div>
                    <div class="grid gap-2">
                        <Label for="series-duration">Episode length in seconds</Label
                        ><Input
                            id="series-duration"
                            v-model="form.specification.episode_duration_seconds"
                            type="number"
                            min="4"
                            max="300"
                            :disabled="form.processing"
                        /><InputError :message="form.errors['specification.episode_duration_seconds']" />
                    </div>
                </div>
                <div class="grid gap-2">
                    <Label for="series-audio">Sound</Label>
                    <select
                        id="series-audio"
                        v-model="form.specification.audio_mode"
                        :disabled="form.processing"
                        class="border-input bg-background focus-visible:ring-ring/50 h-11 rounded-md border px-3 text-sm outline-none focus-visible:ring-2"
                    >
                        <option v-for="(label, value) in audioModes" :key="value" :value="value">{{ label }}</option>
                    </select>
                    <p class="text-muted-foreground text-xs">
                        Speech and sound uses the video model's audio support. Silent video requires dialogue-free shots and removes sound from the
                        finished montage.
                    </p>
                    <InputError :message="form.errors['specification.audio_mode']" />
                </div>
                <div class="grid gap-2">
                    <Label for="series-rules">Storytelling rules</Label
                    ><textarea
                        id="series-rules"
                        v-model="form.specification.narrative_rules"
                        maxlength="20000"
                        rows="5"
                        :disabled="form.processing"
                        class="border-input bg-background focus-visible:ring-ring/50 w-full resize-y rounded-md border p-3 text-sm leading-relaxed outline-none focus-visible:ring-2"
                        placeholder="Tone, recurring conflicts, pacing and how episodes should end."
                    /><InputError :message="form.errors['specification.narrative_rules']" />
                </div>
                <div class="flex flex-wrap justify-end gap-3 border-t pt-5">
                    <Button as-child variant="outline" class="h-11"><Link :href="cancelUrl">Cancel</Link></Button
                    ><Button type="submit" class="h-11" :disabled="form.processing || !!series?.archived_at">{{
                        form.processing ? 'Saving…' : 'Save series'
                    }}</Button>
                </div>
            </form>
        </div>
    </AppLayout>
</template>
