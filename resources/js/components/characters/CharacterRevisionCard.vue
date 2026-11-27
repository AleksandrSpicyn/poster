<script setup lang="ts">
import CharacterVoicePreview from '@/components/characters/CharacterVoicePreview.vue';
import { useFormApi } from '@/lib/api/useFormApi';
import { formatCount } from '@/lib/formatCount';
import ImageFilePicker from '@/components/ImageFilePicker.vue';
import ConfirmationDialog from '@/components/ConfirmationDialog.vue';
import InputError from '@/components/InputError.vue';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import type { CharacterRevision, CharacterRouteParams } from '@/types/series';
import { useForm } from '@inertiajs/vue3';
import { computed, ref } from 'vue';

const props = defineProps<{
    revision: CharacterRevision;
    params: CharacterRouteParams;
    referenceViews: Record<string, string>;
    speechVoices: Record<string, string>;
    archived: boolean;
    latestRevision?: boolean;
    uploadOnly?: boolean;
    focused?: boolean;
    approvedRevision?: CharacterRevision | null;
}>();
const emit = defineEmits<{ openUpload: [] }>();
const submitForm = useFormApi();
const params = { ...props.params, revision: props.revision.id };
const editable = computed(() => props.revision.status === 'draft' && !props.archived);
const editForm = useForm({
    title: props.revision.title ?? '',
    description: props.revision.description,
    appearance: { ...props.revision.appearance, notes: typeof props.revision.appearance.notes === 'string' ? props.revision.appearance.notes : '' },
    voice: { ...props.revision.voice, description: typeof props.revision.voice.description === 'string' ? props.revision.voice.description : '' },
    lock_version: props.revision.lock_version,
});
const uploadForm = useForm({
    file: null as File | null,
    view: 'front',
    label: '',
    replace_voice_id: null as number | null,
    lock_version: props.revision.lock_version,
});
const approvalForm = useForm({ lock_version: props.revision.lock_version });
const removeForm = useForm({ lock_version: props.revision.lock_version });
const editErrors = computed<Record<string, string | undefined>>(() => editForm.errors);
const uploadErrors = computed<Record<string, string | undefined>>(() => uploadForm.errors);
const approvalErrors = computed<Record<string, string | undefined>>(() => approvalForm.errors);
const removeErrors = computed<Record<string, string | undefined>>(() => removeForm.errors);
const busy = computed(() => editForm.processing || uploadForm.processing || approvalForm.processing || removeForm.processing);
const viewLabels: Record<string, string> = {
    front: 'Анфас',
    profile: 'Профиль',
    three_quarter: 'Три четверти',
    full_body: 'В полный рост',
    expression: 'Выражение лица',
    other: 'Другой ракурс',
    voice: 'Образец голоса',
};
function viewLabel(view: string) {
    return viewLabels[view] ?? props.referenceViews[view] ?? view;
}

const uploadFailure = ref('');
const removeFailure = ref('');

function update() {
    submitForm(editForm, ['characters.revisions.update', params], { preserveScroll: true, preserveState: 'errors' });
}
function upload() {
    uploadFailure.value = '';
    uploadForm.replace_voice_id = uploadForm.view === 'voice' ? (props.revision.voice_sample?.id ?? null) : null;
    submitForm(uploadForm, ['characters.references.store', params], {
        preserveScroll: true,
        preserveState: 'errors',
        onNetworkError: () => {
            uploadFailure.value = 'Соединение прервалось. Проверьте список референсов перед повторной загрузкой.';
            return false;
        },
        onHttpException: (response) => {
            if (response.status === 419) return;
            uploadFailure.value = 'Не удалось подтвердить сохранение. Проверьте версию и повторите загрузку.';
            return false;
        },
    });
}
function approve() {
    submitForm(approvalForm, ['characters.revisions.approve', params], { preserveScroll: true, preserveState: 'errors' });
}
function remove(media: number) {
    removeFailure.value = '';
    submitForm(removeForm, ['characters.references.destroy', { ...params, media }], {
        preserveScroll: true,
        preserveState: 'errors',
        onNetworkError: () => {
            removeFailure.value = 'Связь прервана. Повторите удаление после подключения.';
            return false;
        },
        onHttpException: (response) => {
            if (response.status === 419) return;
            removeFailure.value = 'Не удалось подтвердить удаление. Обновите версию и попробуйте ещё раз.';
            return false;
        },
    });
}
function selectVoiceFile(event: Event) {
    const file = (event.target as HTMLInputElement).files?.[0];
    uploadForm.file = file ?? null;
    uploadForm.clearErrors('file');
    uploadFailure.value = '';
}
function selectFile(file: File) {
    uploadFailure.value = '';
    uploadForm.file = file;
    uploadForm.clearErrors('file');
}
</script>

<template>
    <section class="bg-card space-y-6 rounded-xl border p-5 sm:p-7" :class="{ 'upload-panel': uploadOnly, 'revision-editor': focused }">
        <template v-if="!uploadOnly">
            <div class="flex flex-wrap items-center justify-between gap-3">
                <h2 class="font-semibold">{{ focused ? 'Версии' : 'Версия ' + revision.number }}</h2>
                <span v-if="!focused || !editable || !approvedRevision" class="bg-muted text-muted-foreground rounded-full px-3 py-1 text-xs">{{
                    (focused ? 'Версия ' + String(revision.number).padStart(2, '0') + ' · ' : '') +
                    (revision.status === 'approved' ? 'Утверждена' : revision.status === 'superseded' ? 'Заменена' : 'Черновик')
                }}</span>
            </div>
            <div v-if="editable && approvedRevision" class="grid gap-3 sm:grid-cols-2" aria-label="Сравнение версий">
                <div class="bg-muted rounded-lg border p-3">
                    <strong class="text-primary">Версия {{ String(approvedRevision.number).padStart(2, '0') }}</strong>
                    <p class="mt-2 whitespace-pre-wrap">Утверждена: {{ approvedRevision.description }}</p>
                </div>
                <div class="bg-muted rounded-lg border p-3">
                    <strong>Версия {{ String(revision.number).padStart(2, '0') }}</strong>
                    <p class="mt-2 whitespace-pre-wrap">Черновик: {{ revision.description }}</p>
                </div>
            </div>
            <form v-if="editable" class="grid gap-4" :aria-busy="editForm.processing" @submit.prevent="update">
                <div class="grid gap-2">
                    <Label :for="'revision-title-' + revision.id">Название версии {{ String(revision.number).padStart(2, '0') }}</Label>
                    <Input :id="'revision-title-' + revision.id" v-model="editForm.title" maxlength="255" :disabled="busy" />
                    <InputError :message="editForm.errors.title" />
                </div>
                <div class="grid gap-2">
                    <Label :for="'revision-description-' + revision.id">Описание героя</Label
                    ><textarea
                        :id="'revision-description-' + revision.id"
                        v-model="editForm.description"
                        :rows="focused ? 2 : 5"
                        required
                        maxlength="10000"
                        :disabled="busy"
                        class="border-input bg-background focus-visible:ring-ring/50 resize-y rounded-md border p-3 text-sm leading-relaxed outline-none focus-visible:ring-2"
                    /><InputError :message="editForm.errors.description" />
                </div>
                <InputError :message="editForm.errors.lock_version" /><InputError :message="editErrors.revision" />
                <div v-if="editForm.isDirty" role="status" class="bg-muted rounded-lg border-l-4 border-amber-500 p-4">
                    <strong>Есть несохранённая правка</strong>
                    <p>Сначала сохраните изменения версии {{ String(revision.number).padStart(2, '0') }}.</p>
                </div>
                <div><Button type="submit" variant="default" class="h-11 font-bold" :disabled="busy">Сохранить версию</Button></div>
                <div class="grid gap-4 sm:grid-cols-2">
                    <div class="grid gap-2">
                        <Label :for="'revision-notes-' + revision.id">Детали внешности</Label
                        ><Input
                            :id="'revision-notes-' + revision.id"
                            v-model="editForm.appearance.notes"
                            :disabled="busy"
                            placeholder="Одежда, черты лица, пропорции"
                        />
                    </div>
                    <div class="grid gap-2">
                        <Label :for="'revision-voice-' + revision.id">Голос и манера речи</Label
                        ><Input
                            :id="'revision-voice-' + revision.id"
                            v-model="editForm.voice.description"
                            :disabled="busy"
                            placeholder="Тембр, акцент, темп"
                        />
                    </div>
                </div>
            </form>
            <div v-else class="space-y-2">
                <strong v-if="revision.title">{{ revision.title }}</strong>
                <p class="text-sm leading-relaxed whitespace-pre-wrap">{{ revision.description }}</p>
                <dl v-if="editForm.appearance.notes || editForm.voice.description" class="grid gap-2 text-sm">
                    <div v-if="editForm.appearance.notes">
                        <dt class="font-medium">Детали внешности</dt>
                        <dd>{{ editForm.appearance.notes }}</dd>
                    </div>
                    <div v-if="editForm.voice.description">
                        <dt class="font-medium">Голос и манера речи</dt>
                        <dd>{{ editForm.voice.description }}</dd>
                    </div>
                </dl>
                <p v-if="!archived" class="text-muted-foreground text-xs">Чтобы изменить утверждённый образ, создайте новую версию.</p>
            </div>
            <CharacterVoicePreview
                v-if="editable && latestRevision"
                :current-sample="revision.voice_sample?.id ?? null"
                :params="params"
                :voices="speechVoices"
                :version="revision.lock_version"
                :disabled="busy || editForm.isDirty"
            />
            <div v-if="revision.voice_sample" class="space-y-2">
                <h3 class="text-sm font-semibold">Образец голоса</h3>
                <audio controls preload="none" :src="revision.voice_sample.url" aria-label="Прослушать голос персонажа" class="w-full" />
                <Button v-if="editable" type="button" variant="outline" :disabled="busy || editForm.isDirty" @click="remove(revision.voice_sample.id)"
                    >Удалить образец голоса</Button
                >
                <InputError :message="removeFailure || removeErrors.revision || removeForm.errors.lock_version" />
            </div>
            <div class="space-y-3">
                <h3 class="text-sm font-semibold">Референсы</h3>
                <p v-if="!revision.references?.length" class="text-muted-foreground text-sm">
                    {{ editable ? 'Добавьте изображения анфас, в профиль и в полный рост.' : 'Изображений пока нет.' }}
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
                                :alt="reference.label || 'Образ персонажа · ' + viewLabel(reference.view)"
                                loading="lazy"
                                class="bg-muted aspect-square w-full object-contain"
                        /></a>
                        <figcaption class="text-muted-foreground text-xs break-words">
                            {{ reference.label || viewLabel(reference.view) }}
                        </figcaption>
                        <ConfirmationDialog
                            v-if="editable"
                            :heading="revision.references?.length === 3 ? 'Удаление одного из трёх референсов' : 'Удаление референса'"
                            :title="'Удалить референс «' + viewLabel(reference.view) + '»?'"
                            description="Остальные изображения останутся в этой версии."
                            action="Удалить референс"
                            :processing="removeForm.processing"
                            :error="removeFailure || removeErrors.revision || removeForm.errors.lock_version"
                            destructive
                            @confirm="remove(reference.id)"
                        >
                            <template #trigger
                                ><Button type="button" variant="outline" class="h-11 w-full" :disabled="busy || editForm.isDirty"
                                    >Удалить референс</Button
                                ></template
                            >
                            <template #preview>
                                <div class="flex items-center gap-3 py-4">
                                    <img :src="reference.url" alt="" class="h-14 w-20 rounded-md object-cover" /><span
                                        ><template v-if="reference.file_name || reference.label"
                                            >{{ reference.file_name || reference.label }} · </template
                                        >{{ viewLabel(reference.view) }} · версия {{ String(revision.number).padStart(2, '0') }}</span
                                    >
                                </div>
                            </template>
                            <div class="studio-row">
                                <dt>Сейчас</dt>
                                <dd>
                                    {{ formatCount(revision.references?.length ?? 0, 'изображение', 'изображения', 'изображений') }} · после удаления
                                    останется {{ (revision.references?.length ?? 1) - 1 }}
                                </dd>
                            </div>
                        </ConfirmationDialog>
                    </figure>
                </div>
                <InputError :message="removeForm.errors.lock_version" /><InputError :message="removeErrors.revision" />
            </div>
            <Button v-if="editable" type="button" variant="outline" :disabled="busy || editForm.isDirty" @click="emit('openUpload')"
                >Добавить референс</Button
            >
        </template>
        <form v-if="editable && uploadOnly" class="grid min-w-0 gap-4" @submit.prevent="upload">
            <h2 class="text-xl font-semibold">{{ uploadFailure ? 'Референсы' : 'Добавление референса' }}</h2>
            <div v-if="uploadFailure && revision.references?.[0]" class="flex items-center gap-3">
                <img :src="revision.references[0].url" alt="" class="h-14 w-20 rounded-md object-cover" />
                <strong>{{ viewLabel(revision.references[0].view) }} · текущий референс</strong>
            </div>
            <slot v-if="!uploadFailure" name="version" />
            <div v-show="!uploadFailure" class="grid gap-2">
                <Label :for="'reference-view-' + revision.id">Тип референса</Label
                ><select
                    :id="'reference-view-' + revision.id"
                    v-model="uploadForm.view"
                    :disabled="busy"
                    class="border-input bg-background h-11 rounded-md border px-3 text-sm"
                    @change="
                        uploadForm.file = null;
                        uploadForm.clearErrors();
                    "
                >
                    <option v-for="value in Object.keys(referenceViews)" :key="value" :value="value" :disabled="value === 'voice' && !latestRevision">
                        {{ viewLabel(value) }}
                    </option></select
                ><InputError :message="uploadForm.errors.view" />
            </div>
            <div class="grid min-w-0 gap-2">
                <template v-if="uploadForm.view === 'voice'">
                    <Label :for="'voice-sample-' + revision.id">Запись одного говорящего без музыки</Label>
                    <input
                        :id="'voice-sample-' + revision.id"
                        type="file"
                        accept=".wav,.mp3,audio/wav,audio/mpeg"
                        required
                        :disabled="busy || editForm.isDirty"
                        @change="selectVoiceFile"
                    />
                    <p class="text-muted-foreground text-xs">
                        WAV или MP3, 2–30 с, до 10 МБ. Для диалога трёх героев в Seedance Mini подойдут записи по 3–5 с.
                    </p>
                </template>
                <ImageFilePicker
                    v-else
                    label="Изображение персонажа"
                    required
                    :file="uploadForm.file"
                    :disabled="busy || editForm.isDirty"
                    @select="selectFile"
                />
                <InputError :message="uploadForm.errors.file" />
            </div>
            <div v-show="!uploadFailure" class="grid gap-2">
                <Label :for="'reference-label-' + revision.id">Подпись</Label
                ><Input
                    :id="'reference-label-' + revision.id"
                    v-model="uploadForm.label"
                    maxlength="255"
                    :disabled="busy"
                    placeholder="Например, герой в синем пальто"
                /><InputError :message="uploadForm.errors.label" />
            </div>
            <div v-if="uploadFailure" role="alert" class="bg-muted border-destructive rounded-lg border-l-4 p-4">
                <strong>Не удалось загрузить файл</strong>
                <p>{{ uploadFailure }}</p>
            </div>
            <div class="flex flex-wrap items-end gap-2">
                <Button type="submit" class="h-11" :disabled="busy || !uploadForm.file || editForm.isDirty">{{
                    uploadForm.view === 'voice' && revision.voice_sample
                        ? 'Заменить образец голоса'
                        : uploadFailure
                          ? 'Повторить загрузку'
                          : 'Добавить референс'
                }}</Button>
            </div>
            <InputError :message="uploadForm.errors.lock_version" /><InputError :message="uploadErrors.revision" /><InputError
                :message="uploadErrors.character"
            />
        </form>
        <form v-if="editable && !uploadOnly" class="flex flex-wrap items-center justify-between gap-4 border-t pt-5" @submit.prevent="approve">
            <div class="max-w-prose">
                <p class="text-muted-foreground text-sm">Утвердите версию, когда образ и референсы готовы к использованию в эпизодах.</p>
                <InputError :message="approvalForm.errors.lock_version" /><InputError :message="approvalErrors.revision" /><InputError
                    :message="approvalErrors.references"
                /><InputError :message="approvalErrors.character" />
            </div>
            <Button type="submit" class="h-11" :disabled="busy || editForm.isDirty">Утвердить версию</Button>
        </form>
    </section>
</template>

<style scoped>
.revision-editor {
    padding: 20px 24px 28px;
}
.revision-editor h2 {
    font-size: 20px;
}
.revision-editor form textarea {
    min-height: 46px;
}
.revision-editor [aria-label='Сравнение версий'] > div {
    padding: 10px 12px;
}
@media (max-width: 767px) {
    .revision-editor {
        padding: 14px 15px 20px;
    }
}

.upload-panel {
    padding: 20px 24px 28px;
}
.upload-panel :deep(select),
.upload-panel :deep([data-slot='input']) {
    height: 46px;
    background: var(--background);
    font-size: 16px;
}
.upload-panel [data-slot='input'] {
    height: 44px;
}
.upload-panel form > button,
.upload-panel button[type='submit'] {
    font-weight: 650;
    padding-inline: 16px;
}
.upload-panel label:has(input[type='file']) {
    border-color: var(--input);
    border-style: solid;
    padding-inline: 12px;
    font-size: 16px;
    line-height: 1.4;
}
.upload-panel :deep(label:not(:has(input[type='file']))) {
    color: var(--muted-foreground);
    font-weight: 400;
}
.upload-panel :deep(select) {
    color: var(--foreground);
}
@media (max-width: 767px) {
    .upload-panel {
        padding: 12px 15px 20px;
    }
    .upload-panel form {
        gap: 14px;
    }
}
</style>
