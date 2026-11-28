<script setup lang="ts">
import GenerationUsage from '@/components/billing/GenerationUsage.vue';
import { useFormApi } from '@/lib/api/useFormApi';
import InputError from '@/components/InputError.vue';
import ShotOperationControls from '@/components/episodes/ShotOperationControls.vue';
import { Button } from '@/components/ui/button';
import { Dialog, DialogClose, DialogContent, DialogDescription, DialogFooter, DialogTitle, DialogTrigger } from '@/components/ui/dialog';
import EpisodePreview from './EpisodePreview.vue';
import ShotGenerationDialog from './ShotGenerationDialog.vue';
import { keepShotLocation, takeStatus, preferredTake } from '@/lib/shotTakes';
import type { ContinuationSource, EpisodeRouteParams, ShotSummary } from '@/types/episodes';
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
    sourceOptions: ContinuationSource[];
}>();
const submitForm = useFormApi();
const params = { ...props.params, revision: props.revisionId, scene: props.shot.scene_id, shot: props.shot.id };
const cancelForm = useForm({});
const stopping = ref<number | null>(null);
const orderedTakes = computed(() => [...props.shot.takes].sort((left, right) => right.take_number - left.take_number));
const savedTakes = computed(() =>
    props.shot.takes
        .filter((take) => take.generated)
        .map((take) => String(take.take_number).padStart(2, '0'))
        .sort()
        .join(', '),
);
const viewedId = ref<number>();
const take = computed(() => orderedTakes.value.find((t) => t.id === viewedId.value) ?? preferredTake(orderedTakes.value));
const video = computed(() => take.value?.media.find((m) => m.collection === 'shot-video'));
const image = computed(() => take.value?.media.find((m) => m.collection === 'shot-image'));
const heading = computed(
    () => 'Кадр ' + String(props.number ?? props.shot.position).padStart(2, '0') + ' · ' + (props.shot.title || props.shot.action),
);
const primaryOperation = computed(() =>
    take.value?.operations.some((op) => op.status === 'submission_unknown' || op.status === 'frame_review' || op.can_retry_download),
);
function stop(prompt: number) {
    if (cancelForm.processing || stopping.value !== prompt) return;
    submitForm(cancelForm, ['shots.cancel', { ...params, prompt }], {
        preserveScroll: true,
        preserveState: 'errors',
        onBeforeUpdate: keepShotLocation(props.shot.id),
        onSuccess: () => {
            stopping.value = null;
        },
    });
}
</script>

<template>
    <div class="shot-detail grid min-w-0 gap-4 min-[851px]:gap-6">
        <section class="studio-panel min-w-0 space-y-4 wrap-anywhere">
            <header class="grid grid-cols-[minmax(0,1fr)_fit-content(32%)] items-start gap-2">
                <div class="min-w-0">
                    <h2 class="mb-0">{{ heading }}</h2>
                    <p class="text-muted-foreground mt-1 text-sm">
                        {{ sceneTitle }} · {{ (shot.duration_ms / 1000).toLocaleString('ru') }} с · {{ shot.transition_mode_label }}
                    </p>
                </div>
                <span class="text-muted-foreground text-xs" role="status">{{ takeStatus(take) }}</span>
            </header>
            <EpisodePreview
                v-if="video"
                :key="video.id"
                :src="video.url"
                :poster="image?.url"
                timeline
                show-fullscreen
                :label="'Видео дубля ' + take?.take_number"
                player-label="Предпросмотр дубля"
                play-label="Воспроизвести дубль"
                pause-label="Приостановить дубль"
            />
            <Dialog v-else-if="image"
                ><DialogTrigger as-child
                    ><button class="frame-preview block max-w-full overflow-hidden rounded-lg bg-black" aria-label="Увеличить первый кадр">
                        <img :src="image.url" alt="Первый кадр дубля" class="max-w-full object-contain" /></button
                ></DialogTrigger>
                <DialogContent :aria-describedby="undefined" class="sm:max-w-3xl"
                    ><DialogTitle>Первый кадр · дубль {{ take?.take_number }}</DialogTitle
                    ><img :src="image.url" alt="Увеличенный первый кадр" class="max-h-[72dvh] w-full object-contain"
                /></DialogContent>
            </Dialog>
            <p v-else class="bg-muted text-muted-foreground rounded-lg px-4 py-5 text-sm">
                {{ take ? 'Медиафайл пока недоступен.' : 'Здесь появится первый кадр или видео дубля.' }}
            </p>
            <div v-if="take" class="flex flex-wrap items-center justify-between gap-3">
                <strong class="text-sm">Дубль {{ String(take.take_number).padStart(2, '0') }}</strong>
                <ShotOperationControls v-if="!archived" :key="take.id" :take="take" :params="params" />
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
                <details v-if="take.inputs?.prompt || take.operations.length" class="w-full text-sm">
                    <summary>Что передаётся в генерацию</summary>
                    <ul class="text-muted-foreground my-2">
                        <li v-for="operation in take.operations" :key="operation.id">{{ operation.stage_label }} · {{ operation.status_label }}</li>
                    </ul>
                    <template v-if="take.inputs?.prompt"
                        ><p>
                            {{ take.inputs.model }} · {{ take.inputs.duration }} с · изображения первого кадра:
                            {{ take.inputs.images.join(', ') || 'продолжение' }}
                        </p>
                        <p v-for="speaker in take.inputs.speakers" :key="speaker.name">
                            {{ speaker.name }} · {{ speaker.sample ? 'образец №' + speaker.sample : 'описание' }}
                        </p>
                        <pre class="max-h-64 overflow-auto whitespace-pre-wrap">{{ take.inputs.prompt }}</pre>
                    </template>
                </details>
            </div>
            <div class="flex flex-wrap items-center justify-between gap-3 border-t pt-4">
                <GenerationUsage :id="shot.id" kind="shot" />
                <ShotGenerationDialog
                    :shot="shot"
                    :heading="heading"
                    :params="params"
                    :source-options="sourceOptions"
                    :request-key="requestKey"
                    :approved="approved"
                    :archived="archived"
                    :project-enabled="projectEnabled"
                    :secondary="!!primaryOperation"
                />
            </div>
        </section>
        <section class="studio-panel min-w-0">
            <h2>Дубли кадра</h2>
            <p v-if="!orderedTakes.length" class="text-muted-foreground mt-3 text-sm">Пока нет дублей.</p>
            <ul v-else class="mt-3 divide-y border-t">
                <li v-for="item in orderedTakes" :key="item.id">
                    <button
                        class="aria-pressed:bg-accent aria-pressed:text-accent-foreground flex min-h-12 w-full flex-wrap items-center justify-between gap-2 rounded-sm px-2 text-left text-sm"
                        :aria-pressed="item.id === take?.id"
                        @click="viewedId = item.id"
                    >
                        <strong>Дубль {{ String(item.take_number).padStart(2, '0') }}</strong
                        ><span>{{ selectedTakeIds.includes(item.id) ? 'Принят в монтаж' : takeStatus(item) }}</span>
                    </button>
                </li>
            </ul>
        </section>
    </div>
</template>
<style scoped>
.frame-preview img {
    height: clamp(175px, 32vw, 360px);
    width: auto;
}
.shot-detail :deep(.episode-preview video) {
    width: 100%;
    height: clamp(175px, 32vw, 360px);
}
</style>
