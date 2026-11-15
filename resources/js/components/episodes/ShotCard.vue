<script setup lang="ts">
import InputError from '@/components/InputError.vue';
import ShotOperationControls from '@/components/episodes/ShotOperationControls.vue';
import { Button } from '@/components/ui/button';
import { Label } from '@/components/ui/label';
import type { EpisodeRouteParams, ShotSummary } from '@/types/episodes';
import { useForm } from '@inertiajs/vue3';
import { computed } from 'vue';
import { useRoute } from 'ziggy-js';

const props = defineProps<{
    shot: ShotSummary;
    params: EpisodeRouteParams;
    revisionId: number;
    approved: boolean;
    archived: boolean;
    projectEnabled: boolean;
    requestKey: string;
    sourceOptions: { id: number; label: string }[];
}>();
const route = useRoute();
const params = { ...props.params, revision: props.revisionId, scene: props.shot.scene_id, shot: props.shot.id };
const form = useForm({ source_prompt_id: props.shot.source_prompt_id ?? '', request_key: props.requestKey + '-' + props.shot.id, new_take: false });
const cancelForm = useForm({});
const hasActiveRequest = computed(() =>
    props.shot.takes.some((take) =>
        take.operations.some((operation) => ['planned', 'submitting', 'submitted', 'running', 'submission_unknown'].includes(operation.status)),
    ),
);
const errors = computed(() => Object.values(form.errors).join(' '));
function generate(newTake: boolean) {
    form.new_take = newTake;
    form.transform((data) => ({ ...data, source_prompt_id: data.source_prompt_id === '' ? null : Number(data.source_prompt_id) })).post(
        route('shots.generate', params),
        { preserveScroll: true, preserveState: 'errors' },
    );
}
function stop(prompt: number) {
    cancelForm.post(route('shots.cancel', { ...params, prompt }), { preserveScroll: true, preserveState: 'errors' });
}
</script>

<template>
    <article class="bg-card space-y-4 rounded-xl border p-5">
        <div class="flex flex-wrap items-start justify-between gap-3">
            <h4 class="font-semibold">Shot {{ shot.position }} · {{ shot.duration_ms / 1000 }}s</h4>
            <span class="text-muted-foreground text-xs">{{ shot.transition_mode.replaceAll('_', ' ') }}</span>
        </div>
        <p class="text-sm leading-relaxed whitespace-pre-wrap">{{ shot.action }}</p>
        <div v-if="approved && !archived && projectEnabled" class="flex flex-wrap items-end gap-3">
            <div v-if="shot.transition_mode !== 'new_scene'" class="grid gap-2">
                <Label :for="'shot-source-' + shot.id">Continue from a previous take</Label
                ><select
                    :id="'shot-source-' + shot.id"
                    v-model="form.source_prompt_id"
                    class="border-input bg-background h-11 max-w-80 rounded-md border px-3 text-sm"
                    :disabled="form.processing || hasActiveRequest"
                >
                    <option value="">Use the screenplay source</option>
                    <option v-for="source in sourceOptions" :key="source.id" :value="source.id">{{ source.label }}</option>
                </select>
            </div>
            <Button type="button" class="h-11" :disabled="form.processing || hasActiveRequest" @click="generate(shot.takes.length > 0)">{{
                form.processing ? 'Starting…' : shot.takes.length ? 'Generate a new take' : 'Generate shot'
            }}</Button>
        </div>
        <p v-if="approved && !archived && projectEnabled" class="text-muted-foreground text-xs">
            Starts media generation using the approved screenplay and selected references. A new take can use provider credits.
        </p>
        <InputError :message="errors" />
        <p v-if="!shot.takes.length" class="text-muted-foreground text-sm">No takes yet.</p>
        <div class="space-y-4">
            <section v-for="take in shot.takes" :key="take.id" class="bg-muted/30 space-y-3 rounded-lg border p-4">
                <div class="flex flex-wrap items-center justify-between gap-3">
                    <h5 class="text-sm font-semibold">Take {{ take.take_number }}</h5>
                    <span class="text-muted-foreground text-xs">{{ take.generated ? 'Ready for montage' : 'In progress or needs attention' }}</span>
                </div>
                <div class="grid gap-3 sm:grid-cols-2">
                    <template v-for="media in take.media" :key="media.id"
                        ><video
                            v-if="media.collection === 'shot-video'"
                            :src="media.url"
                            controls
                            preload="metadata"
                            class="bg-muted max-h-80 w-full rounded-md" /><img
                            v-else-if="media.collection === 'shot-image'"
                            :src="media.url"
                            alt="Generated opening frame"
                            loading="lazy"
                            class="bg-muted max-h-80 w-full rounded-md object-contain"
                    /></template>
                </div>
                <ul class="space-y-1 text-xs">
                    <li v-for="operation in take.operations" :key="operation.id" class="text-muted-foreground">
                        {{ operation.stage }}: {{ operation.status_label }}<span v-if="operation.error_code"> · {{ operation.error_code }}</span
                        ><span v-if="operation.cancel_requested_at"> · Stop requested</span>
                    </li>
                </ul>
                <p v-if="take.operations.some((item) => item.status === 'submission_unknown')" class="text-destructive text-sm">
                    The provider may have received this request. Its result needs checking before another submission.
                </p>
                <Button
                    v-if="!take.generated && take.operations.some((item) => ['planned', 'submitting', 'submitted', 'running'].includes(item.status))"
                    type="button"
                    variant="outline"
                    class="h-11"
                    :disabled="cancelForm.processing"
                    @click="stop(take.id)"
                    >Stop next steps</Button
                >
                <ShotOperationControls v-if="!archived" :take="take" :params="params" />
            </section>
        </div>
    </article>
</template>
