<script setup lang="ts">
import InputError from '@/components/InputError.vue';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import type { EpisodeRouteParams, ShotTake } from '@/types/episodes';
import { useForm } from '@inertiajs/vue3';
import { computed } from 'vue';
import { useRoute } from 'ziggy-js';

const props = defineProps<{ take: ShotTake; params: EpisodeRouteParams & { revision: number; scene: number; shot: number } }>();
const route = useRoute();
const resolveForm = useForm({ external_id: '', note: '' });
const retryForm = useForm({});
const abandonForm = useForm({ note: '', accept_unknown_outcome: false });
const unknown = computed(() => props.take.operations.find((operation) => operation.stage === 'video' && operation.status === 'submission_unknown'));
const unresolved = computed(() => props.take.operations.find((operation) => operation.status === 'submission_unknown'));
const canRetry = computed(() =>
    props.take.operations.some((operation) => operation.stage === 'download' && ['failed', 'cancelled'].includes(operation.status)),
);
const resolveErrors = computed(() => Object.values(resolveForm.errors).join(' '));
const retryErrors = computed(() => Object.values(retryForm.errors).join(' '));
const abandonErrors = computed(() => Object.values(abandonForm.errors).join(' '));
function resolve() {
    if (unknown.value)
        resolveForm.post(route('shots.resolve', { ...props.params, prompt: props.take.id, operation: unknown.value.id }), {
            preserveScroll: true,
            preserveState: 'errors',
        });
}
function retry() {
    retryForm.post(route('shots.download.retry', { ...props.params, prompt: props.take.id }), { preserveScroll: true, preserveState: 'errors' });
}
function abandon() {
    if (!unresolved.value) return;
    abandonForm.post(route('shots.abandon', { ...props.params, prompt: props.take.id, operation: unresolved.value.id }), {
        preserveScroll: true,
        preserveState: 'errors',
    });
}
</script>

<template>
    <div v-if="unresolved || canRetry" class="space-y-4 border-t pt-4">
        <details v-if="unknown" class="space-y-3">
            <summary class="cursor-pointer text-sm font-medium">Check an existing provider task</summary>
            <p class="text-muted-foreground mt-3 text-xs">
                If the task is visible in your provider account, enter its identifier. This checks the existing task and never starts another video.
            </p>
            <form class="mt-3 grid gap-3" @submit.prevent="resolve">
                <div class="grid gap-2">
                    <Label :for="'provider-task-' + take.id">Provider task ID</Label
                    ><Input
                        :id="'provider-task-' + take.id"
                        v-model="resolveForm.external_id"
                        required
                        maxlength="200"
                        :disabled="resolveForm.processing"
                    />
                </div>
                <div class="grid gap-2">
                    <Label :for="'task-note-' + take.id">Verification note</Label
                    ><Input :id="'task-note-' + take.id" v-model="resolveForm.note" maxlength="1000" :disabled="resolveForm.processing" />
                </div>
                <InputError :message="resolveErrors" />
                <div><Button type="submit" variant="outline" class="h-11" :disabled="resolveForm.processing">Check existing task</Button></div>
            </form>
        </details>
        <details v-if="unresolved" class="space-y-3">
            <summary class="cursor-pointer text-sm font-medium">Close an unknown attempt after review</summary>
            <p class="text-muted-foreground mt-3 text-sm">
                Check the provider history first. Closing this attempt records your decision and allows a new take. The provider may already have
                processed or charged for the original request.
            </p>
            <form class="mt-3 grid gap-3" @submit.prevent="abandon">
                <div class="grid gap-2">
                    <Label :for="'abandon-note-' + take.id">What did you check?</Label>
                    <Input :id="'abandon-note-' + take.id" v-model="abandonForm.note" required maxlength="1000" :disabled="abandonForm.processing" />
                </div>
                <label class="flex min-h-11 cursor-pointer items-start gap-3 text-sm">
                    <input
                        v-model="abandonForm.accept_unknown_outcome"
                        type="checkbox"
                        required
                        class="mt-1 size-4 shrink-0"
                        :disabled="abandonForm.processing"
                    />
                    <span>I reviewed this attempt and accept that its external outcome is still unknown. A new take may use more credits.</span>
                </label>
                <InputError :message="abandonErrors" />
                <div>
                    <Button
                        type="submit"
                        variant="outline"
                        class="h-11"
                        :disabled="abandonForm.processing || !abandonForm.accept_unknown_outcome || !abandonForm.note.trim()"
                    >
                        Close this attempt
                    </Button>
                </div>
            </form>
        </details>
        <form v-if="canRetry" class="space-y-3" @submit.prevent="retry">
            <p class="text-muted-foreground text-xs">Download the already generated video again. This does not generate or pay for another video.</p>
            <InputError :message="retryErrors" /><Button type="submit" variant="outline" class="h-11" :disabled="retryForm.processing"
                >Retry existing video download</Button
            >
        </form>
    </div>
</template>
