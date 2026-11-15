<script setup lang="ts">
import InputError from '@/components/InputError.vue';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import type { CharacterRevision, CharacterRouteParams } from '@/types/series';
import { useForm } from '@inertiajs/vue3';
import { computed } from 'vue';
import { useRoute } from 'ziggy-js';

const props = defineProps<{ revision: CharacterRevision; params: CharacterRouteParams; referenceViews: Record<string, string>; archived: boolean }>();
const route = useRoute();
const params = { ...props.params, revision: props.revision.id };
const editable = computed(() => props.revision.status === 'draft' && !props.archived);
const editForm = useForm({
    description: props.revision.description,
    appearance: { ...props.revision.appearance, notes: typeof props.revision.appearance.notes === 'string' ? props.revision.appearance.notes : '' },
    voice: { ...props.revision.voice, description: typeof props.revision.voice.description === 'string' ? props.revision.voice.description : '' },
    lock_version: props.revision.lock_version,
});
const uploadForm = useForm({ file: null as File | null, view: 'front', label: '', lock_version: props.revision.lock_version });
const approvalForm = useForm({ lock_version: props.revision.lock_version });
const removeForm = useForm({ lock_version: props.revision.lock_version });
const editErrors = computed<Record<string, string | undefined>>(() => editForm.errors);
const uploadErrors = computed<Record<string, string | undefined>>(() => uploadForm.errors);
const approvalErrors = computed<Record<string, string | undefined>>(() => approvalForm.errors);
const removeErrors = computed<Record<string, string | undefined>>(() => removeForm.errors);
const busy = computed(() => editForm.processing || uploadForm.processing || approvalForm.processing || removeForm.processing);
function update() {
    editForm.patch(route('characters.revisions.update', params), { preserveScroll: true, preserveState: 'errors' });
}
function upload() {
    uploadForm.post(route('characters.references.store', params), { preserveScroll: true, preserveState: 'errors' });
}
function approve() {
    approvalForm.post(route('characters.revisions.approve', params), { preserveScroll: true, preserveState: 'errors' });
}
function remove(media: number) {
    removeForm.delete(route('characters.references.destroy', { ...params, media }), { preserveScroll: true, preserveState: 'errors' });
}
function selectFile(event: Event) {
    uploadForm.file = (event.target as HTMLInputElement).files?.[0] ?? null;
}
</script>

<template>
    <section class="bg-card space-y-6 rounded-xl border p-5 sm:p-7">
        <div class="flex flex-wrap items-center justify-between gap-3">
            <h2 class="font-semibold">Appearance version {{ revision.number }}</h2>
            <span class="bg-muted text-muted-foreground rounded-full px-3 py-1 text-xs">{{ revision.status }}</span>
        </div>
        <form v-if="editable" class="grid gap-4" :aria-busy="editForm.processing" @submit.prevent="update">
            <div class="grid gap-2">
                <Label :for="'revision-description-' + revision.id">Appearance, personality and identity</Label
                ><textarea
                    :id="'revision-description-' + revision.id"
                    v-model="editForm.description"
                    rows="5"
                    required
                    maxlength="10000"
                    :disabled="busy"
                    class="border-input bg-background focus-visible:ring-ring/50 resize-y rounded-md border p-3 text-sm leading-relaxed outline-none focus-visible:ring-2"
                /><InputError :message="editForm.errors.description" />
            </div>
            <div class="grid gap-4 sm:grid-cols-2">
                <div class="grid gap-2">
                    <Label :for="'revision-notes-' + revision.id">Visual details</Label
                    ><Input
                        :id="'revision-notes-' + revision.id"
                        v-model="editForm.appearance.notes"
                        :disabled="busy"
                        placeholder="Clothes, proportions, distinctive details"
                    />
                </div>
                <div class="grid gap-2">
                    <Label :for="'revision-voice-' + revision.id">Voice and delivery</Label
                    ><Input
                        :id="'revision-voice-' + revision.id"
                        v-model="editForm.voice.description"
                        :disabled="busy"
                        placeholder="Tone, accent, pace"
                    />
                </div>
            </div>
            <InputError :message="editForm.errors.lock_version" /><InputError :message="editErrors.revision" />
            <div><Button type="submit" variant="outline" class="h-11" :disabled="busy">Save draft details</Button></div>
        </form>
        <div v-else class="space-y-2">
            <p class="text-sm leading-relaxed whitespace-pre-wrap">{{ revision.description }}</p>
            <p class="text-muted-foreground text-xs">Approved versions stay unchanged. Create a new version to update this character.</p>
        </div>
        <div class="space-y-3">
            <h3 class="text-sm font-semibold">Reference views</h3>
            <p v-if="!revision.references?.length" class="text-muted-foreground text-sm">
                Upload front, profile and full-body views created in your other application.
            </p>
            <div class="grid grid-cols-2 gap-4 sm:grid-cols-3 lg:grid-cols-4">
                <figure v-for="reference in revision.references ?? []" :key="reference.id" class="min-w-0 space-y-2">
                    <a
                        :href="reference.url"
                        target="_blank"
                        rel="noopener"
                        class="focus-visible:ring-ring/50 block overflow-hidden rounded-lg border outline-none focus-visible:ring-2"
                        ><img
                            :src="reference.url"
                            :alt="reference.label || 'Character reference: ' + reference.view"
                            loading="lazy"
                            class="bg-muted aspect-square w-full object-contain"
                    /></a>
                    <figcaption class="text-muted-foreground text-xs break-words">
                        {{ reference.label || referenceViews[reference.view] || reference.view }}
                    </figcaption>
                    <Button
                        v-if="editable"
                        type="button"
                        variant="outline"
                        class="h-11 w-full"
                        :disabled="busy || editForm.isDirty"
                        @click="remove(reference.id)"
                        >Remove reference</Button
                    >
                </figure>
            </div>
            <InputError :message="removeForm.errors.lock_version" /><InputError :message="removeErrors.revision" />
        </div>
        <form v-if="editable" class="grid gap-4 border-t pt-5 sm:grid-cols-2" @submit.prevent="upload">
            <div class="grid gap-2">
                <Label :for="'reference-file-' + revision.id">Image file</Label
                ><Input
                    :id="'reference-file-' + revision.id"
                    type="file"
                    accept="image/png,image/jpeg,image/webp"
                    required
                    :disabled="busy || editForm.isDirty"
                    @change="selectFile"
                />
                <p class="text-muted-foreground text-xs">PNG, JPEG or WebP, up to 10 MB. Save edited draft details before uploading.</p>
                <InputError :message="uploadForm.errors.file" />
            </div>
            <div class="grid gap-2">
                <Label :for="'reference-view-' + revision.id">View</Label
                ><select
                    :id="'reference-view-' + revision.id"
                    v-model="uploadForm.view"
                    :disabled="busy"
                    class="border-input bg-background h-11 rounded-md border px-3 text-sm"
                >
                    <option v-for="(label, value) in referenceViews" :key="value" :value="value">{{ label }}</option></select
                ><InputError :message="uploadForm.errors.view" />
            </div>
            <div class="grid gap-2">
                <Label :for="'reference-label-' + revision.id">Reference label</Label
                ><Input
                    :id="'reference-label-' + revision.id"
                    v-model="uploadForm.label"
                    maxlength="255"
                    :disabled="busy"
                    placeholder="For example, Mira in her blue coat"
                /><InputError :message="uploadForm.errors.label" />
            </div>
            <div class="flex items-end">
                <Button type="submit" class="h-11" :disabled="busy || !uploadForm.file || editForm.isDirty">Upload reference</Button>
            </div>
            <InputError :message="uploadForm.errors.lock_version" /><InputError :message="uploadErrors.revision" /><InputError
                :message="uploadErrors.character"
            />
        </form>
        <form v-if="editable" class="flex flex-wrap items-center justify-between gap-4 border-t pt-5" @submit.prevent="approve">
            <div class="max-w-prose">
                <p class="text-muted-foreground text-sm">Approve this version when the appearance and references are ready for use in episodes.</p>
                <InputError :message="approvalForm.errors.lock_version" /><InputError :message="approvalErrors.revision" /><InputError
                    :message="approvalErrors.references"
                /><InputError :message="approvalErrors.character" />
            </div>
            <Button type="submit" class="h-11" :disabled="busy || editForm.isDirty">Approve version</Button>
        </form>
    </section>
</template>
