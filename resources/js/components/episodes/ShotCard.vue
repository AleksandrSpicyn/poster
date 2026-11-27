<script setup lang="ts">
import GenerationUsage from '@/components/billing/GenerationUsage.vue';
import { useFormApi } from '@/lib/api/useFormApi';
import InputError from '@/components/InputError.vue';
import ShotOperationControls from '@/components/episodes/ShotOperationControls.vue';
import { Button } from '@/components/ui/button';
import { Dialog, DialogClose, DialogContent, DialogDescription, DialogFooter, DialogTitle, DialogTrigger } from '@/components/ui/dialog';
import { Label } from '@/components/ui/label';
import type { EpisodeRouteParams, ShotSummary } from '@/types/episodes';
import { useForm } from '@inertiajs/vue3';
import { computed, ref } from 'vue';

const props = defineProps<{
    shot: ShotSummary;
    params: EpisodeRouteParams;
    revisionId: number;
    approved: boolean;
    archived: boolean;
    projectEnabled: boolean;
    requestKey: string;
    selectedTakeIds: number[];
    sceneTitle?: string;
    number?: number;
    sourceOptions: { id: number; label: string }[];
}>();
const submitForm = useFormApi();
const params = { ...props.params, revision: props.revisionId, scene: props.shot.scene_id, shot: props.shot.id };
const form = useForm({ source_prompt_id: props.shot.source_prompt_id ?? '', request_key: props.requestKey + '-' + props.shot.id, new_take: false });
const cancelForm = useForm({});
const stopping = ref<number | null>(null);
const orderedTakes = computed(() => [...props.shot.takes].sort((left, right) => left.take_number - right.take_number));
const savedTakes = computed(() =>
    props.shot.takes
        .filter((take) => take.generated)
        .map((take) => String(take.take_number).padStart(2, '0'))
        .sort()
        .join(', '),
);
const hasActiveRequest = computed(() =>
    props.shot.takes.some((take) =>
        take.operations.some((operation) =>
            ['frame_review', 'planned', 'submitting', 'submitted', 'running', 'submission_unknown'].includes(operation.status),
        ),
    ),
);
const errors = computed(() => Object.values(form.errors).join(' '));
function pauseHiddenMedia(event: Event) {
    const details = event.currentTarget;
    if (details instanceof HTMLDetailsElement && !details.open) {
        details.querySelectorAll('video').forEach((video) => video.pause());
    }
}
function generate(newTake: boolean) {
    form.new_take = newTake;
    form.transform((data) => ({ ...data, source_prompt_id: data.source_prompt_id === '' ? null : Number(data.source_prompt_id) }));
    submitForm(form, ['shots.generate', params], { preserveScroll: true, preserveState: 'errors' });
}
function stop(prompt: number) {
    if (cancelForm.processing || stopping.value !== prompt) return;
    submitForm(cancelForm, ['shots.cancel', { ...params, prompt }], {
        preserveScroll: true,
        preserveState: 'errors',
        onSuccess: () => {
            stopping.value = null;
        },
    });
}
</script>

<template>
    <article class="take-group studio-panel">
        <div class="flex flex-wrap items-start justify-between gap-3">
            <h4 class="font-semibold">
                Кадр {{ String(number ?? shot.position).padStart(2, '0') }} · {{ (shot.duration_ms / 1000).toLocaleString('ru') }} с
            </h4>
            <span class="text-muted-foreground text-xs">{{ shot.transition_mode_label }}</span>
        </div>
        <p class="text-sm leading-relaxed whitespace-pre-wrap">{{ shot.action }}</p>
        <GenerationUsage :id="shot.id" :key="shot.id" kind="shot" />
        <div v-if="approved && !archived && projectEnabled" class="take-generate flex flex-wrap items-end gap-3">
            <div v-if="shot.transition_mode !== 'new_scene'" class="grid gap-2">
                <Label :for="'shot-source-' + shot.id">Продолжить от дубля</Label
                ><select
                    :id="'shot-source-' + shot.id"
                    v-model="form.source_prompt_id"
                    class="list-select w-full max-w-80"
                    :disabled="form.processing || hasActiveRequest"
                >
                    <option value="">Источник из сценария</option>
                    <option v-for="source in sourceOptions" :key="source.id" :value="source.id">{{ source.label }}</option>
                </select>
            </div>
            <Button type="button" class="h-11" :disabled="form.processing || hasActiveRequest" @click="generate(shot.takes.length > 0)">{{
                form.processing ? 'Запуск…' : shot.takes.length ? 'Создать новый дубль' : 'Создать кадр'
            }}</Button>
        </div>
        <p v-if="approved && !archived && projectEnabled" class="text-muted-foreground text-xs">
            Новый дубль создаётся по утверждённому сценарию и выбранным референсам. Провайдер может списать средства за генерацию.
        </p>
        <InputError :message="errors" />
        <p v-if="!shot.takes.length" class="text-muted-foreground text-sm">Пока нет дублей.</p>
        <div class="take-list">
            <section v-for="take in orderedTakes" :key="take.id" class="space-y-2 border-b py-2">
                <details v-if="take.media.length" class="take-media" @toggle="pauseHiddenMedia">
                    <summary class="take-heading">
                        <img
                            v-if="take.media.some((media) => media.collection === 'shot-image')"
                            :src="take.media.find((media) => media.collection === 'shot-image')?.url"
                            alt=""
                        />
                        <span
                            ><strong>Дубль {{ String(take.take_number).padStart(2, '0') }}</strong
                            ><small>{{
                                selectedTakeIds.includes(take.id) ? 'Принят' : take.generated ? 'Готов к монтажу' : 'В работе или требует проверки'
                            }}</small></span
                        >
                    </summary>
                    <div class="grid gap-3 sm:grid-cols-2">
                        <template v-for="media in take.media" :key="media.id"
                            ><video
                                v-if="media.collection === 'shot-video'"
                                :src="media.url"
                                :aria-label="'Видео дубля ' + take.take_number"
                                controls
                                preload="metadata"
                                class="bg-muted max-h-80 w-full rounded-md" /><img
                                v-else-if="media.collection === 'shot-image'"
                                :src="media.url"
                                :alt="'Первый кадр дубля ' + take.take_number"
                                loading="lazy"
                                class="bg-muted max-h-80 w-full rounded-md object-contain"
                        /></template>
                    </div>
                </details>
                <div v-else class="take-heading">
                    <span
                        ><strong>Дубль {{ String(take.take_number).padStart(2, '0') }}</strong
                        ><small>{{ take.generated ? 'Готов к монтажу' : 'В работе или требует проверки' }}</small></span
                    >
                </div>
                <ul class="space-y-1 text-xs">
                    <li v-for="operation in take.operations" :key="operation.id" class="text-muted-foreground">
                        {{ operation.stage_label }} · {{ operation.status_label
                        }}<span v-if="operation.cancel_requested_at"> · Запрошена остановка</span>
                    </li>
                </ul>
                <p v-if="take.operations.some((item) => item.status === 'submission_unknown')" class="text-destructive text-sm">
                    Проверьте результат в кабинете провайдера перед повтором.
                </p>
                <Dialog
                    v-if="
                        !archived &&
                        !take.generated &&
                        take.operations.some(
                            (item) =>
                                ['frame_review', 'planned', 'submitting', 'submitted', 'running'].includes(item.status) && !item.cancel_requested_at,
                        )
                    "
                    :open="stopping === take.id"
                    @update:open="
                        (open) => {
                            stopping = open ? take.id : null;
                        }
                    "
                >
                    <DialogTrigger as-child><Button type="button" variant="outline" class="h-11">Остановить следующие шаги</Button></DialogTrigger>
                    <DialogContent class="studio-confirm" :show-close="false">
                        <DialogTitle>Остановить шаги дубля {{ String(take.take_number).padStart(2, '0') }}?</DialogTitle>
                        <DialogDescription>Уже отправленный запрос может завершиться у провайдера.</DialogDescription>
                        <dl>
                            <div class="studio-row">
                                <dt>Кадр</dt>
                                <dd>{{ sceneTitle }} · {{ String(number ?? shot.position).padStart(2, '0') }}</dd>
                            </div>
                            <div v-if="savedTakes" class="studio-row">
                                <dt>Сохранённые дубли</dt>
                                <dd>{{ savedTakes }} останутся доступны</dd>
                            </div>
                        </dl>
                        <InputError :message="Object.values(cancelForm.errors).join(' ')" />
                        <DialogFooter
                            ><Button type="button" variant="destructive" :disabled="cancelForm.processing" @click="stop(take.id)"
                                >Остановить шаги</Button
                            ><DialogClose as-child
                                ><Button type="button" variant="outline" :disabled="cancelForm.processing">Оставить в работе</Button></DialogClose
                            ></DialogFooter
                        >
                    </DialogContent>
                </Dialog>
                <details v-if="take.inputs?.prompt" class="text-sm">
                    <summary>Что передаётся в генерацию</summary>
                    <p>
                        {{ take.inputs.model }} · {{ take.inputs.duration }} с · изображения первого кадра:
                        {{ take.inputs.images.join(', ') || 'продолжение' }}
                    </p>
                    <p v-for="speaker in take.inputs.speakers" :key="speaker.name">
                        {{ speaker.name }} · {{ speaker.sample ? 'образец №' + speaker.sample : 'описание' }}
                    </p>
                    <pre class="max-h-64 overflow-auto whitespace-pre-wrap">{{ take.inputs.prompt }}</pre>
                </details>
                <ShotOperationControls v-if="!archived" :take="take" :params="params" />
            </section>
        </div>
    </article>
</template>

<style scoped>
.take-group {
    display: flex;
    flex-direction: column;
    gap: 12px;
}
.take-generate {
    order: 2;
}
.take-generate + p {
    order: 3;
}
.take-list {
    order: 1;
}
.take-heading {
    display: flex;
    align-items: center;
    gap: 12px;
    min-height: 44px;
}
summary.take-heading {
    cursor: pointer;
}
.take-heading img {
    width: 60px;
    height: 44px;
    border-radius: 6px;
    object-fit: cover;
}
.take-heading small {
    display: block;
    color: var(--muted-foreground);
}
.take-media[open] .take-heading {
    margin-bottom: 12px;
}
</style>
