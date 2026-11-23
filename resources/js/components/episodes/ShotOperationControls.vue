<script setup lang="ts">
import { useFormApi } from '@/lib/api/useFormApi';
import InputError from '@/components/InputError.vue';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import type { EpisodeRouteParams, ShotTake } from '@/types/episodes';
import { useForm } from '@inertiajs/vue3';
import { computed } from 'vue';

const props = defineProps<{ take: ShotTake; params: EpisodeRouteParams & { revision: number; scene: number; shot: number } }>();
const submitForm = useFormApi();
const resolveForm = useForm({ external_id: '', note: '' });
const retryForm = useForm({});
const abandonForm = useForm({ note: '', accept_unknown_outcome: false });
const unknown = computed(() => props.take.operations.find((operation) => operation.stage === 'video' && operation.status === 'submission_unknown'));
const unresolved = computed(() => props.take.operations.find((operation) => operation.status === 'submission_unknown'));
const canRetry = computed(() => props.take.operations.some((operation) => operation.can_retry_download));
const retrying = computed(() => props.take.operations.some((operation) => operation.retrying_download));
const failedDownload = computed(() => props.take.operations.some((operation) => operation.stage === 'download' && operation.status === 'failed'));
const resolveErrors = computed(() => Object.values(resolveForm.errors).join(' '));
const retryErrors = computed(() => Object.values(retryForm.errors).join(' '));
const abandonErrors = computed(() => Object.values(abandonForm.errors).join(' '));
function resolve() {
    if (unknown.value)
        submitForm(resolveForm, ['shots.resolve', { ...props.params, prompt: props.take.id, operation: unknown.value.id }], {
            preserveScroll: true,
            preserveState: 'errors',
        });
}
function retry() {
    submitForm(retryForm, ['shots.download.retry', { ...props.params, prompt: props.take.id }], { preserveScroll: true, preserveState: 'errors' });
}
function abandon() {
    if (!unresolved.value) return;
    submitForm(abandonForm, ['shots.abandon', { ...props.params, prompt: props.take.id, operation: unresolved.value.id }], {
        preserveScroll: true,
        preserveState: 'errors',
    });
}
</script>

<template>
    <div v-if="unresolved || canRetry || retrying || failedDownload" class="space-y-4 border-t pt-4">
        <details v-if="unknown" class="space-y-3">
            <summary class="cursor-pointer text-sm font-medium">Проверка задачи у провайдера</summary>
            <p class="text-muted-foreground mt-3 text-xs">
                Укажите идентификатор из кабинета провайдера, чтобы получить результат существующей задачи.
            </p>
            <form class="mt-3 grid gap-3" @submit.prevent="resolve">
                <div class="grid gap-2">
                    <Label :for="'provider-task-' + take.id">Идентификатор задачи</Label
                    ><Input
                        :id="'provider-task-' + take.id"
                        v-model="resolveForm.external_id"
                        required
                        maxlength="200"
                        :disabled="resolveForm.processing"
                    />
                </div>
                <div class="grid gap-2">
                    <Label :for="'task-note-' + take.id">Что проверено</Label
                    ><Input :id="'task-note-' + take.id" v-model="resolveForm.note" maxlength="1000" :disabled="resolveForm.processing" />
                </div>
                <InputError :message="resolveErrors" />
                <div>
                    <Button type="submit" variant="outline" class="h-11" :disabled="resolveForm.processing">Проверить существующую задачу</Button>
                </div>
            </form>
        </details>
        <details v-if="unresolved" class="space-y-3">
            <summary class="cursor-pointer text-sm font-medium">Закрыть попытку после проверки</summary>
            <p class="text-muted-foreground mt-3 text-sm">
                Проверьте историю задачи у провайдера. Он мог выполнить запрос и списать средства. После закрытия попытки можно создать новый дубль.
            </p>
            <form class="mt-3 grid gap-3" @submit.prevent="abandon">
                <div class="grid gap-2">
                    <Label :for="'abandon-note-' + take.id">Что проверено</Label>
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
                    <span>Я проверил попытку. Результат остаётся неизвестным. За новый дубль провайдер может списать средства.</span>
                </label>
                <InputError :message="abandonErrors" />
                <div>
                    <Button
                        type="submit"
                        variant="outline"
                        class="h-11"
                        :disabled="abandonForm.processing || !abandonForm.accept_unknown_outcome || !abandonForm.note.trim()"
                    >
                        Закрыть попытку
                    </Button>
                </div>
            </form>
        </details>
        <div v-if="failedDownload" role="alert">
            <strong>Ошибка загрузки</strong>
            <p>Генерация завершена, но готовый файл не удалось скачать.</p>
        </div>
        <div v-if="retrying" role="status">
            <strong>Загрузка готового файла</strong>
            <p>Исходная задача сохранена.</p>
        </div>
        <form v-if="canRetry" class="space-y-3" @submit.prevent="retry">
            <p class="text-muted-foreground text-sm">Повтор использует существующий файл провайдера.</p>
            <InputError :message="retryErrors" /><Button type="submit" variant="outline" class="h-11" :disabled="retryForm.processing"
                >Повторить загрузку</Button
            >
        </form>
    </div>
</template>
