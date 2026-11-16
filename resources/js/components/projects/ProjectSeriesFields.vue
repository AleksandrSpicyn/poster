<script setup lang="ts">
import InputError from '@/components/InputError.vue';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import type { SeriesSpecification } from '@/types/series';

const specification = defineModel<SeriesSpecification & { story_outline: string }>('specification', { required: true });
const automationEnabled = defineModel<boolean>('automationEnabled', { required: true });
const episodeLimit = defineModel<number | string>('episodeLimit', { required: true });
const channelSourceProjectId = defineModel<number | null>('channelSourceProjectId', { required: true });
defineProps<{
    processing: boolean;
    errors: Record<string, string | undefined>;
    audiences: Record<string, string>;
    audioModes: Record<string, string>;
    channelSources: { id: number; name: string }[];
}>();
</script>

<template>
    <div class="space-y-6">
        <InputError :message="errors.series_lock_version" /><InputError :message="errors.series" />
        <div class="grid gap-5 sm:grid-cols-2">
            <div class="grid gap-2">
                <Label for="series-style">Visual style</Label
                ><Input
                    id="series-style"
                    v-model="specification.visual_style"
                    maxlength="2000"
                    :disabled="processing"
                    placeholder="For example, watercolor animation"
                /><InputError :message="errors['series_specification.visual_style']" />
            </div>
            <div class="grid gap-2">
                <Label for="series-language">Language</Label
                ><Input id="series-language" v-model="specification.language" maxlength="35" :disabled="processing" placeholder="en" /><InputError
                    :message="errors['series_specification.language']"
                />
            </div>
            <div class="grid gap-2">
                <Label for="series-audience">Audience</Label
                ><select
                    id="series-audience"
                    v-model="specification.audience"
                    :disabled="processing"
                    class="border-input bg-background focus-visible:ring-ring/50 h-11 rounded-md border px-3 text-sm outline-none focus-visible:ring-2"
                >
                    <option v-for="(label, value) in audiences" :key="value" :value="value">{{ label }}</option></select
                ><InputError :message="errors['series_specification.audience']" />
            </div>
            <div class="grid gap-2">
                <Label for="series-duration">Episode length in seconds</Label
                ><Input
                    id="series-duration"
                    v-model="specification.episode_duration_seconds"
                    type="number"
                    min="4"
                    max="300"
                    :disabled="processing"
                /><InputError :message="errors['series_specification.episode_duration_seconds']" />
            </div>
        </div>
        <div class="grid gap-2">
            <Label for="series-audio">Sound</Label>
            <select
                id="series-audio"
                v-model="specification.audio_mode"
                :disabled="processing"
                class="border-input bg-background focus-visible:ring-ring/50 h-11 rounded-md border px-3 text-sm outline-none focus-visible:ring-2"
            >
                <option v-for="(label, value) in audioModes" :key="value" :value="value">{{ label }}</option>
            </select>
            <p class="text-muted-foreground text-xs">
                Speech and sound uses the video model's audio support. Silent video requires dialogue-free shots and removes sound from the finished
                montage.
            </p>
            <InputError :message="errors['series_specification.audio_mode']" />
        </div>
        <div class="grid gap-2">
            <Label for="series-outline">Overall story</Label>
            <textarea
                id="series-outline"
                v-model="specification.story_outline"
                maxlength="20000"
                rows="8"
                :disabled="processing"
                class="border-input bg-background focus-visible:ring-ring/50 w-full resize-y rounded-md border p-3 text-sm leading-relaxed outline-none focus-visible:ring-2"
                placeholder="The main story, episode ideas, character arcs and the intended ending. The system develops each screenplay from this outline and the actual previous episodes."
            />
            <InputError :message="errors['series_specification.story_outline']" />
        </div>
        <div class="grid gap-2">
            <Label for="series-rules">Storytelling rules</Label
            ><textarea
                id="series-rules"
                v-model="specification.narrative_rules"
                maxlength="20000"
                rows="5"
                :disabled="processing"
                class="border-input bg-background focus-visible:ring-ring/50 w-full resize-y rounded-md border p-3 text-sm leading-relaxed outline-none focus-visible:ring-2"
                placeholder="Tone, recurring conflicts, pacing and how episodes should end."
            /><InputError :message="errors['series_specification.narrative_rules']" />
        </div>
        <fieldset class="grid gap-5 rounded-lg border p-4 sm:p-5">
            <legend class="px-2 text-sm font-semibold">Automatic production</legend>
            <label class="flex min-h-11 cursor-pointer items-start gap-3">
                <input v-model="automationEnabled" type="checkbox" class="accent-primary mt-1 size-4 shrink-0" :disabled="processing" />
                <span class="text-sm"
                    ><span class="font-medium">Produce and publish episodes automatically</span
                    ><span class="text-muted-foreground mt-1 block leading-relaxed"
                        >Enabling queues the first release. It waits for character references, then writes the screenplay, generates media, assembles,
                        saves story memory and publishes to connected YouTube and Instagram channels. Future episodes follow the schedule when it is
                        enabled.</span
                    ></span
                >
            </label>
            <InputError :message="errors.automation_enabled" />
            <div class="grid gap-2">
                <Label for="series-limit">Planned number of episodes</Label>
                <Input
                    id="series-limit"
                    v-model="episodeLimit"
                    type="number"
                    min="1"
                    max="1000"
                    placeholder="Ongoing series"
                    :disabled="processing"
                />
                <p class="text-muted-foreground text-xs">Leave empty for an ongoing series. Production stops after the last planned episode.</p>
                <InputError :message="errors.episode_limit" />
            </div>
            <div v-if="channelSources.length" class="grid gap-2">
                <Label for="series-channels">Publishing channels</Label>
                <select
                    id="series-channels"
                    v-model="channelSourceProjectId"
                    :disabled="processing"
                    class="border-input bg-background focus-visible:ring-ring/50 h-11 rounded-md border px-3 text-sm outline-none focus-visible:ring-2"
                >
                    <option :value="null">Keep this project's channels</option>
                    <option v-for="source in channelSources" :key="source.id" :value="source.id">Use channels from {{ source.name }}</option>
                </select>
                <p class="text-muted-foreground text-xs">
                    Reuse authorised YouTube and Instagram connections. Existing project channels stay connected.
                </p>
                <InputError :message="errors.channel_source_project_id" />
            </div>
        </fieldset>
    </div>
</template>
