<script setup lang="ts">
import InputError from '@/components/InputError.vue';
import PublicationRecoveryForm from '@/components/episodes/PublicationRecoveryForm.vue';
import { Button } from '@/components/ui/button';
import { Label } from '@/components/ui/label';
import type { EpisodeAssemblySummary, EpisodeRouteParams, PublicationSummary } from '@/types/episodes';
import { useForm } from '@inertiajs/vue3';
import { computed } from 'vue';
import { useRoute } from 'ziggy-js';

const props = defineProps<{
    assembly: EpisodeAssemblySummary;
    params: EpisodeRouteParams;
    channels: { id: number; name: string; type: string; type_label: string }[];
    publications: PublicationSummary[];
    currentAccepted: boolean;
    archived: boolean;
    projectEnabled: boolean;
    canPublish?: boolean;
}>();
const route = useRoute();
const params = { ...props.params, revision: props.assembly.episode_revision_id, assembly: props.assembly.id };
const acceptForm = useForm({
    lock_version: props.assembly.lock_version,
    confirmed_events: '',
    character_state: '',
    open_threads: '',
    scene_closed: false,
});
const publishForm = useForm({ channel_ids: [] as number[] });
const acceptErrors = computed(() => Object.values(acceptForm.errors).join(' '));
const publishErrors = computed(() => Object.values(publishForm.errors).join(' '));
function accept() {
    acceptForm.post(route('episodes.assemblies.accept', params), { preserveScroll: true, preserveState: 'errors' });
}
function publish() {
    if (window.confirm('Schedule this episode for the selected channels?'))
        publishForm.post(route('episodes.assemblies.publish', params), { preserveScroll: true, preserveState: 'errors' });
}
</script>

<template>
    <section class="bg-card space-y-5 rounded-xl border p-5 sm:p-7">
        <div class="flex flex-wrap items-center justify-between gap-3">
            <h3 class="font-semibold">Montage {{ assembly.number }}</h3>
            <span class="bg-muted text-muted-foreground rounded-full px-3 py-1 text-xs">{{ assembly.status }}</span>
        </div>
        <p v-if="assembly.error_message" role="alert" class="text-destructive text-sm">{{ assembly.error_message }}</p>
        <video
            v-for="media in assembly.media.filter((item) => item.collection === 'episode-video')"
            :key="media.id"
            :src="media.url"
            controls
            preload="metadata"
            class="bg-muted max-h-[34rem] w-full rounded-lg"
        />
        <form v-if="assembly.status === 'ready' && !archived" class="grid gap-4 border-t pt-5" @submit.prevent="accept">
            <p class="text-muted-foreground text-sm">Watch the montage and confirm what actually happened. These facts guide the next episode.</p>
            <div class="grid gap-2">
                <Label :for="'events-' + assembly.id">Confirmed events</Label
                ><textarea
                    :id="'events-' + assembly.id"
                    v-model="acceptForm.confirmed_events"
                    required
                    maxlength="12000"
                    rows="3"
                    class="border-input bg-background focus-visible:ring-ring/50 rounded-md border p-3 text-sm outline-none focus-visible:ring-2"
                />
            </div>
            <div class="grid gap-2">
                <Label :for="'state-' + assembly.id">Characters at the end</Label
                ><textarea
                    :id="'state-' + assembly.id"
                    v-model="acceptForm.character_state"
                    required
                    maxlength="8000"
                    rows="3"
                    class="border-input bg-background focus-visible:ring-ring/50 rounded-md border p-3 text-sm outline-none focus-visible:ring-2"
                />
            </div>
            <div class="grid gap-2">
                <Label :for="'threads-' + assembly.id">Unresolved story threads</Label
                ><textarea
                    :id="'threads-' + assembly.id"
                    v-model="acceptForm.open_threads"
                    maxlength="8000"
                    rows="2"
                    class="border-input bg-background focus-visible:ring-ring/50 rounded-md border p-3 text-sm outline-none focus-visible:ring-2"
                />
            </div>
            <label class="flex min-h-11 items-center gap-3 text-sm"
                ><input v-model="acceptForm.scene_closed" type="checkbox" class="size-4" />The final scene is finished</label
            >
            <InputError :message="acceptErrors" />
            <div><Button type="submit" class="h-11" :disabled="acceptForm.processing">Accept montage and update story</Button></div>
        </form>
        <div v-if="assembly.status === 'accepted'" class="space-y-2 border-t pt-4">
            <h4 class="text-sm font-semibold">Confirmed story state</h4>
            <p class="text-sm whitespace-pre-wrap">{{ assembly.continuity_snapshot.confirmed_events }}</p>
            <p class="text-muted-foreground text-sm whitespace-pre-wrap">{{ assembly.continuity_snapshot.character_state }}</p>
            <p class="text-muted-foreground text-xs">
                {{
                    assembly.continuity_snapshot.scene_closed
                        ? 'Scene finished — a new scene can follow.'
                        : 'Scene open — continuation can start from the final frames.'
                }}
            </p>
        </div>
        <form v-if="canPublish !== false && currentAccepted && !archived && projectEnabled" class="space-y-4 border-t pt-5" @submit.prevent="publish">
            <h4 class="font-semibold">Publish this accepted version</h4>
            <p v-if="!channels.length" class="text-muted-foreground text-sm">No publishing channels are configured for this project.</p>
            <label v-for="channel in channels" :key="channel.id" class="flex min-h-11 items-center gap-3 text-sm"
                ><input v-model="publishForm.channel_ids" type="checkbox" :value="channel.id" class="size-4" />{{ channel.name }} ·
                {{ channel.type_label }}</label
            >
            <p v-if="channels.some((channel) => channel.type === 'tiktok' && publishForm.channel_ids.includes(channel.id))" class="text-sm">
                Open the notification in your TikTok inbox to review and publish the video.
            </p>
            <InputError :message="publishErrors" /><Button
                type="submit"
                class="h-11"
                :disabled="publishForm.processing || !publishForm.channel_ids.length"
                >Schedule publication</Button
            >
        </form>
        <ul v-if="publications.length" class="space-y-2 border-t pt-4 text-sm">
            <li v-for="publication in publications" :key="publication.id">
                {{ publication.channel_name }}: {{ publication.status }}
                <time v-if="publication.scheduled_at" :datetime="publication.scheduled_at">{{
                    new Date(publication.scheduled_at).toLocaleString()
                }}</time>
                <p v-if="publication.error_message" class="text-destructive mt-1 text-xs">{{ publication.error_message }}</p>
                <PublicationRecoveryForm v-if="publication.status === 'submission_unknown'" :publication="publication" :params="params" />
            </li>
        </ul>
    </section>
</template>
