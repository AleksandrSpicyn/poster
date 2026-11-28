<script setup lang="ts">
import GenerationUsage from '@/components/billing/GenerationUsage.vue';
import { useProductionRunActions } from '@/composables/useProductionRunActions';
import StopProductionDialog from '@/components/StopProductionDialog.vue';
import InputError from '@/components/InputError.vue';
import { Button } from '@/components/ui/button';
import ProjectArtwork from '@/components/projects/ProjectArtwork.vue';
import type { ProjectSummary, ProjectChannel } from '@/types/projects';
import type { SeriesSummary } from '@/types/series';
import { Link, useForm } from '@inertiajs/vue3';
import { Check, Clock } from '@lucide/vue';
import { computed, ref, watch } from 'vue';
import { useRoute } from '@/composables/useRoute';

const props = defineProps<{
    projectId: number;
    cover: ProjectSummary['cover'];
    projectName: string;
    channels: ProjectChannel[];
    seriesId: number;
    enabled: boolean;
    autoPublish: boolean;
    planComplete: boolean;
    pinnedChannelIds?: number[] | null;
    episodeTitle: string;
    episodeNumber: number;
    run?: SeriesSummary['latest_production'];
}>();
const route = useRoute();
const starting = useForm({
    request_key: crypto.randomUUID(),
    series_id: props.seriesId,
    channel_ids:
        props.pinnedChannelIds ??
        props.channels.filter((channel) => props.autoPublish && channel.can_receive_production).map((channel) => channel.id),
});
const { start, changeStatus, transition } = useProductionRunActions();
const checking = ref(false);
watch(
    [() => props.run?.id, () => props.run?.status, () => props.run?.stage, () => props.run?.has_unresolved_effects],
    () => (checking.value = false),
);
const stopped = computed(() => props.run?.status === 'stopped');
const attention = computed(() => props.run?.status === 'needs_attention');
const reviewedTake = computed(() => props.run?.stage === 'media' && props.run?.has_reviewed_take && !props.run?.has_unresolved_effects);
const resume = computed(() => (stopped.value || attention.value) && !props.run?.has_unresolved_effects && (reviewedTake.value || checking.value));
const statusLabel = computed(() => {
    if (resume.value) return reviewedTake.value ? 'Проверка завершена' : 'Готово к продолжению';
    if (props.run) return props.run.status === 'running' ? 'Производство идёт' : props.run.status_label;
    return props.planComplete ? 'Все эпизоды приняты' : 'До запуска';
});
const selectedIds = computed(() => (props.run ? props.run.channel_ids : starting.channel_ids));
const channelNames = computed(() =>
    selectedIds.value?.map((id) => {
        const channel = props.channels.find((item) => item.id === id);
        return channel?.name ?? 'Канал №' + id;
    }),
);
const recipientLabel = computed(() => channelNames.value?.join(', ') || (channelNames.value ? 'Без отправки' : 'Получатели не закреплены'));
const automaticDelivery = computed(() => (props.run ? props.run.automatic_delivery : starting.channel_ids.length > 0));
const steps = computed(() => {
    const run = props.run;
    if (!run) return [];
    const stages = Object.entries(run.stages);
    const active = stages.findIndex(([stage]) => stage === run.stage);
    return stages.map(([stage, title], index) => {
        const skipped = stage === 'publication' && !run.automatic_delivery;
        const complete = !skipped && (run.status === 'completed' || index < active);
        const current = run.status !== 'completed' && stage === run.stage;
        return { stage, title, complete, current, label: skipped ? 'Без отправки' : complete ? 'Готово' : current ? run.status_label : 'Ожидает' };
    });
});
const resumeStage = computed(() => {
    const run = props.run;
    if (!run || run.status !== 'stopped' || run.series_assembly_id) return run?.stage_label;
    return run.stages.planning || run.stages.preparing;
});
const reviewHref = computed(() => {
    if (props.run?.series_assembly_id) return route('series_assemblies.show', props.run.series_assembly_id);
    if (props.run?.episode_id) {
        const panel =
            ({ media: 'shots/review', montage: 'montage', publication: 'release' } as Record<string, string>)[props.run.stage] ?? 'screenplay';
        return route('episodes.show', { series: props.seriesId, episode: props.run.episode_id }) + '#' + panel;
    }
    return null;
});
</script>

<template>
    <div class="production-board">
        <div class="production-stack">
            <section class="production-card" aria-label="Текущий эпизод">
                <p
                    role="status"
                    class="flex items-center gap-2 text-sm font-medium"
                    :class="attention ? 'text-destructive' : run?.is_active ? 'text-(--progress)' : 'text-muted-foreground'"
                >
                    <span aria-hidden="true" class="size-2 rounded-full bg-current" />{{ statusLabel }}
                </p>
                <h2 class="mt-1 text-2xl leading-tight font-bold min-[1100px]:text-[28px]">{{ episodeTitle }}</h2>
                <p class="text-muted-foreground mt-1 font-mono text-xs uppercase">Эпизод {{ String(episodeNumber).padStart(2, '0') }}</p>
                <div class="action-line">
                    <div class="text-muted-foreground min-w-0 space-y-1 text-sm">
                        <p v-if="run">{{ resume ? 'Точка продолжения' : 'Текущий этап' }} — {{ resume ? resumeStage : run.stage_label }}</p>
                        <p v-if="stopped && run?.has_late_take">Внешний дубль получен после остановки.</p>
                        <p v-if="resume">Принятые кадры используются повторно.</p>
                        <p v-else-if="run?.has_unresolved_effects">Ожидается подтверждение результата операции.</p>
                        <p v-else-if="attention">Проверьте результат текущего этапа.</p>
                        <p v-else-if="stopped">Следующие этапы не запустятся автоматически.</p>
                        <p v-else-if="!run">{{ planComplete ? 'План выпуска завершён.' : 'Проверьте настройки выпуска перед запуском.' }}</p>
                        <p v-if="run?.message" role="status">{{ run.message }}</p>
                    </div>
                    <div class="run-actions">
                        <Button v-if="resume && enabled" :disabled="transition.processing" @click="run && changeStatus(run.id, 'resume')"
                            >Возобновить производство</Button
                        >
                        <template v-else-if="stopped || attention">
                            <Button v-if="reviewHref" as-child
                                ><Link :href="reviewHref">{{ stopped ? 'Проверить результат' : 'Открыть ручную проверку' }}</Link></Button
                            >
                            <Button v-if="enabled && !run?.has_unresolved_effects" variant="outline" @click="checking = true"
                                >Продолжить после проверки</Button
                            >
                        </template>
                        <StopProductionDialog
                            v-else-if="run?.is_active"
                            :stage="run.stage_label"
                            :recipients="recipientLabel"
                            :processing="transition.processing"
                            :error="Object.values(transition.errors).join(' ')"
                            @confirm="changeStatus(run.id, 'stop')"
                        />
                        <Button v-else-if="!run && !planComplete && enabled" :disabled="starting.processing" @click="start(projectId, starting)"
                            >Запустить производство</Button
                        >
                        <p v-else-if="!planComplete && run?.status !== 'completed'" class="text-muted-foreground text-sm">
                            Проверьте настройки проекта и автоматизации сериала.
                        </p>
                    </div>
                </div>
                <InputError :message="Object.values(starting.errors).join(' ') || Object.values(transition.errors).join(' ')" />
            </section>
            <section class="production-card" aria-labelledby="production-steps">
                <h2 id="production-steps">Ход производства</h2>
                <ol v-if="steps.length" class="mt-3 divide-y">
                    <li
                        v-for="(step, index) in steps"
                        :key="step.stage"
                        :aria-current="step.current ? 'step' : undefined"
                        class="flex min-h-[62px] items-center gap-3 py-2 text-sm"
                    >
                        <span
                            aria-hidden="true"
                            class="grid size-8 shrink-0 place-items-center rounded-lg font-mono text-xs"
                            :class="
                                step.complete
                                    ? 'bg-accent text-accent-foreground'
                                    : step.current
                                      ? 'bg-muted text-(--progress)'
                                      : 'bg-muted text-muted-foreground'
                            "
                        >
                            <Check v-if="step.complete" class="size-4" /><Clock v-else-if="step.current" class="size-4" /><template v-else>{{
                                String(index + 1).padStart(2, '0')
                            }}</template>
                        </span>
                        <strong class="min-w-0 flex-1">{{ step.title }}</strong><span
                            class="max-w-[40%] text-right text-xs"
                            :class="step.current ? 'text-(--progress)' : 'text-muted-foreground'"
                            >{{ step.label }}</span
                        >
                    </li>
                </ol>
                <p v-else class="text-muted-foreground mt-4 text-sm">{{ planComplete ? 'Все эпизоды приняты.' : 'Этапы появятся после запуска.' }}</p>
            </section>
        </div>
        <div class="production-stack">
            <section class="production-card" aria-labelledby="release-settings">
                <ProjectArtwork :name="projectName" :cover="cover" class="mb-4 aspect-video w-full rounded-lg" />
                <h2 id="release-settings">Настройки выпуска</h2>
                <dl class="mt-3 text-sm">
                    <div class="fact">
                        <dt>Режим</dt>
                        <dd>{{ automaticDelivery ? 'Автопубликация' : 'Сборка без отправки' }}</dd>
                    </div>
                    <div v-if="run || planComplete" class="fact border-t">
                        <dt>Каналы</dt>
                        <dd>{{ recipientLabel }}</dd>
                    </div>
                </dl>
                <fieldset v-if="!run && !planComplete" class="min-w-0 border-t text-sm">
                    <legend class="sr-only">Каналы выпуска</legend>
                    <p aria-hidden="true" class="text-muted-foreground pt-3">Каналы</p>
                    <label v-for="channel in channels" :key="channel.id" class="flex min-h-11 items-center gap-2 py-2">
                        <input
                            v-model="starting.channel_ids"
                            type="checkbox"
                            :value="channel.id"
                            :disabled="!enabled || !channel.can_receive_production || pinnedChannelIds != null || starting.processing"
                            class="accent-primary size-4 shrink-0"
                        />
                        <span
                            >{{ channel.name }} · {{ channel.type_label
                            }}<small v-if="!channel.can_receive_production" class="text-muted-foreground block">Отправка недоступна</small></span
                        >
                    </label>
                    <p v-if="!channels.length" class="text-muted-foreground mt-2">Каналы не подключены.</p>
                </fieldset>
            </section>
            <section class="production-card" aria-labelledby="production-usage">
                <h2 id="production-usage">Расход генерации</h2>
                <GenerationUsage v-if="run" :id="run.id" :key="run.id" kind="run" summary :updated-at="run.updated_at" />
                <GenerationUsage v-else-if="!planComplete" :id="projectId" :series-id="seriesId" kind="production" summary />
                <p v-if="!run && planComplete" class="text-muted-foreground mt-3 text-sm">Нет данных о запуске.</p>
            </section>
        </div>
    </div>
</template>
<style scoped>
.production-board,
.production-stack {
    display: grid;
    gap: 16px;
    min-width: 0;
    align-items: start;
}
.production-card {
    min-width: 0;
    overflow-wrap: anywhere;
    padding: 16px;
    border: 1px solid var(--border);
    border-radius: 12px;
    background: var(--card);
}
.production-card h2[id] {
    font-size: 20px;
    font-weight: 700;
    line-height: 1.3;
}
.action-line {
    display: flex;
    flex-direction: column;
    align-items: flex-start;
    gap: 12px;
    border-top: 1px solid var(--border);
    margin-top: 20px;
    padding-top: 16px;
}
.run-actions {
    display: flex;
    flex-wrap: wrap;
    gap: 8px;
    max-width: 100%;
}
.run-actions:empty {
    display: none;
}
.run-actions :deep(:is(button, a)) {
    white-space: normal;
    min-height: 44px;
    max-width: 100%;
    height: auto;
}
.fact {
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 12px;
    min-height: 42px;
    padding-block: 6px;
}
.fact dt {
    color: var(--muted-foreground);
}
.fact dd {
    min-width: 0;
    text-align: right;
}
@media (min-width: 700px) {
    .production-board {
        grid-template-columns: minmax(0, 1fr) minmax(215px, 32%);
    }
}
@media (min-width: 1100px) {
    .production-board,
    .production-stack {
        gap: 24px;
    }
    .production-card {
        padding: 24px;
    }
    .action-line {
        flex-direction: row;
        align-items: center;
        justify-content: space-between;
    }
}
@media (max-width: 699px) {
    .run-actions,
    .run-actions :deep(:is(button, a)) {
        width: 100%;
    }
}
</style>
