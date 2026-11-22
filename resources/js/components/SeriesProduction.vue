<script setup lang="ts">
import ConfirmationDialog from '@/components/ConfirmationDialog.vue';
import InputError from '@/components/InputError.vue';
import { Button } from '@/components/ui/button';
import type { ProjectChannel } from '@/types/projects';
import type { ProductionRun } from '@/types/production';
import { Link, useForm } from '@inertiajs/vue3';
import { CheckCircle, Circle, CircleAlert, Clock } from '@lucide/vue';
import { computed, ref, watch } from 'vue';
import { useRoute } from 'ziggy-js';

const props = defineProps<{
    projectId: number;
    channels: ProjectChannel[];
    seriesId: number;
    enabled: boolean;
    autoPublish: boolean;
    planComplete: boolean;
    pinnedChannelIds?: number[] | null;
    episodeTitle: string;
    episodeNumber: number;
    run?: ProductionRun | null;
}>();
const route = useRoute();
const starting = useForm({
    request_key: crypto.randomUUID(),
    series_id: props.seriesId,
    channel_ids:
        props.pinnedChannelIds ??
        props.channels.filter((channel) => props.autoPublish && channel.can_receive_production).map((channel) => channel.id),
});
const transition = useForm({});
const checking = ref(false);
watch(
    () => props.run?.id,
    () => (checking.value = false),
);
const stopped = computed(() => props.run?.status === 'stopped');
const attention = computed(() => props.run?.status === 'needs_attention');
const reviewedTake = computed(() => props.run?.stage === 'media' && props.run?.has_reviewed_take && !props.run?.has_unresolved_effects);
const resume = computed(() => (stopped.value || attention.value) && !props.run?.has_unresolved_effects && (reviewedTake.value || checking.value));
const title = computed(() =>
    resume.value
        ? 'Продолжить производство'
        : stopped.value
          ? 'Производство остановлено'
          : attention.value
            ? 'Проверка производства'
            : !props.run && props.planComplete
              ? 'Производство завершено'
              : props.run
                ? 'Ход производства'
                : 'Запуск следующего эпизода',
);
const episodeLabel = computed(() => 'Эпизод ' + String(props.episodeNumber).padStart(2, '0'));
const recipients = computed(() =>
    props.channels.filter((channel) => (props.run?.channel_ids ? props.run.channel_ids.includes(channel.id) : channel.can_receive_production)),
);
const recipientLabel = computed(
    () =>
        recipients.value.map((channel) => channel.name + ' · ' + channel.type_label).join(', ') ||
        (props.run?.channel_ids?.length === 0 ? 'Без отправки' : 'Каналы не подключены'),
);
const groups = computed(() => {
    const stages = Object.keys(props.run?.stages ?? {});
    const active = stages.indexOf(props.run?.stage ?? '');
    return [
        { title: 'План полной версии', stages: ['planning'] },
        { title: 'Персонажи и сценарий', stages: ['preparing', 'screenplay'] },
        { title: 'Кадры и дубли', stages: ['media'] },
        { title: 'Монтаж и память истории', stages: ['montage', 'story'] },
        { title: 'Сборка полной версии', stages: ['final_montage', 'final_review'] },
        { title: 'Публикация', stages: ['publication'] },
    ]
        .filter((group) => group.stages.some((stage) => stages.includes(stage)))
        .filter((group) => !((attention.value || stopped.value) && props.run?.stage === 'media' && group.stages.includes('publication')))
        .filter((group) => !(stopped.value && props.run?.has_late_take && group.stages.includes('media')))
        .map((group) => {
            const last = Math.max(...group.stages.map((stage) => stages.indexOf(stage)));
            const complete = props.run?.status === 'completed' || (last >= 0 && last < active);
            const current = group.stages.includes(props.run?.stage ?? '');
            const label = complete ? 'Готово' : current ? props.run?.status_label : attention.value || stopped.value ? 'Приостановлено' : 'Ожидает';
            return { ...group, complete, current, label };
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
function start() {
    starting.post(route('production_runs.store', props.projectId), {
        preserveScroll: true,
        onSuccess: () => (starting.request_key = crypto.randomUUID()),
    });
}
function change(action: 'stop' | 'resume') {
    if (props.run) transition.post(route('production_runs.' + action, props.run.id), { preserveScroll: true });
}
</script>

<template>
    <section class="studio-panel run-panel">
        <h2>{{ title }}</h2>
        <div class="run-notice" role="status" :class="{ caution: (attention || stopped) && !resume, success: resume && reviewedTake }">
            <component :is="resume && reviewedTake ? CheckCircle : attention || stopped ? CircleAlert : Clock" aria-hidden="true" />
            <div>
                <strong>{{
                    resume
                        ? reviewedTake
                            ? 'Проверка завершена'
                            : 'Продолжение производства'
                        : stopped
                          ? 'Производство остановлено'
                          : attention
                            ? 'Требует внимания'
                            : run
                              ? run.is_active
                                  ? 'Производство идёт'
                                  : run.status_label
                              : planComplete
                                ? 'Все эпизоды приняты'
                                : 'Следующий эпизод'
                }}</strong>
                <p>
                    {{
                        resume && reviewedTake
                            ? 'Найден существующий дубль, файл готов.'
                            : stopped
                              ? 'Планировщик больше не запускает следующие этапы.'
                              : attention
                                ? 'Проверьте результат текущего этапа.'
                                : run
                                  ? episodeLabel + ' · ' + run.stage_label
                                  : episodeTitle + ' · ' + episodeLabel
                    }}
                </p>
            </div>
        </div>
        <fieldset v-if="!run && !planComplete" class="run-channels">
            <legend class="sr-only">Каналы выпуска</legend>
            <label v-for="channel in channels" :key="channel.id">
                <input
                    v-model="starting.channel_ids"
                    type="checkbox"
                    :value="channel.id"
                    :disabled="!channel.can_receive_production || pinnedChannelIds != null || starting.processing"
                />
                <span
                    ><strong>{{ channel.name }} · {{ channel.type_label }}</strong
                    ><small>{{
                        !channel.can_receive_production
                            ? 'Отправка недоступна'
                            : starting.channel_ids.includes(channel.id)
                              ? 'Канал подключён'
                              : 'Оставить без отправки'
                    }}</small></span
                >
            </label>
            <p v-if="!channels.length">Каналы не подключены. Видео будет собрано без отправки.</p>
        </fieldset>
        <template v-if="resume">
            <dl class="run-facts">
                <div>
                    <dt>Точка продолжения</dt>
                    <dd>{{ resumeStage }} · {{ episodeLabel }}</dd>
                </div>
                <div>
                    <dt>Получатели</dt>
                    <dd>{{ recipientLabel }}</dd>
                </div>
            </dl>
            <div class="run-notice">
                <Clock aria-hidden="true" />
                <div>
                    <strong>Продолжение</strong>
                    <p>Принятые кадры используются повторно.</p>
                </div>
            </div>
        </template>
        <template v-else-if="run">
            <ul class="run-steps">
                <li v-for="group in groups" :key="group.title" :aria-current="group.current ? 'step' : undefined">
                    <component :is="group.complete ? CheckCircle : Circle" aria-hidden="true" :class="{ complete: group.complete }" />
                    <div>
                        <strong>{{ group.title }}</strong>
                        <p>{{ group.label }}</p>
                    </div>
                </li>
            </ul>
            <dl v-if="stopped && run.has_late_take" class="run-facts">
                <div>
                    <dt>Последняя операция</dt>
                    <dd>Внешний дубль получен после остановки</dd>
                </div>
            </dl>
        </template>
        <div class="run-actions">
            <Button v-if="resume && enabled" :disabled="transition.processing" @click="change('resume')">Возобновить производство</Button>
            <template v-else-if="stopped || attention">
                <Button v-if="reviewHref" as-child
                    ><Link :href="reviewHref">{{ stopped ? 'Проверить результат' : 'Открыть ручную проверку' }}</Link></Button
                >
                <Button v-if="enabled && !run?.has_unresolved_effects" variant="outline" @click="checking = true">Продолжить после проверки</Button>
            </template>
            <ConfirmationDialog
                v-else-if="run?.is_active"
                heading="Остановка производства"
                title="Остановить производство?"
                description="Новые этапы не начнутся. Уже отправленный дубль может завершиться у провайдера."
                action="Остановить этапы"
                cancel="Продолжить производство"
                destructive
                :processing="transition.processing"
                :error="Object.values(transition.errors).join(' ')"
                @confirm="change('stop')"
            >
                <template #trigger><Button variant="outline">Остановить следующие этапы</Button></template>
                <div class="studio-row">
                    <dt>Текущий этап</dt>
                    <dd>{{ run.stage_label }}</dd>
                </div>
                <div class="studio-row">
                    <dt>Каналы выпуска</dt>
                    <dd>{{ recipientLabel }}</dd>
                </div>
            </ConfirmationDialog>
            <Button v-else-if="!run && !planComplete && enabled" :disabled="starting.processing" @click="start">Запустить производство</Button>
            <p v-else-if="!planComplete && run?.status !== 'completed'" class="text-muted-foreground text-sm">
                Проверьте настройки проекта и автоматизации сериала.
            </p>
        </div>
        <InputError :message="Object.values(starting.errors).join(' ') || Object.values(transition.errors).join(' ')" />
    </section>
</template>

<style scoped>
.run-channels label {
    display: flex;
    gap: 12px;
    align-items: center;
    border-bottom: 1px solid var(--border);
    padding-block: 9px;
}
.run-channels input {
    accent-color: var(--primary);
    width: 18px;
    height: 18px;
}
.run-channels small {
    display: block;
    font-size: 14px;
    color: var(--muted-foreground);
}

.run-steps [aria-current='step'] strong {
    color: var(--primary);
}
.run-panel {
    padding: 20px 24px 28px;
}
.run-panel h2 {
    margin: 0 0 20px;
}
.run-notice {
    display: flex;
    align-items: flex-start;
    position: relative;
    gap: 10px;
    border-radius: 7px;
    background: var(--muted);
    padding: 10px 12px;
}
.run-notice::before {
    content: '';
    position: absolute;
    inset: 7px auto 7px 0;
    width: 3px;
    background: var(--muted-foreground);
}
.run-notice.caution::before {
    background: #db976e;
}
.run-notice.success::before {
    background: var(--primary);
}
.run-notice svg,
.run-steps svg {
    width: 18px;
    height: 18px;
    flex-shrink: 0;
    margin-top: 3px;
}
.run-notice strong,
.run-steps strong {
    font-size: 16px;
}
.run-notice p,
.run-steps p,
.run-facts dt {
    color: var(--muted-foreground);
    font-size: 14px;
}
.run-steps li {
    display: flex;
    gap: 10px;
    padding: 8px 0 10px;
    border-bottom: 1px solid var(--border);
}
.run-steps svg {
    color: var(--muted-foreground);
}
.run-steps svg.complete {
    color: var(--primary);
}
.run-facts div {
    display: grid;
    grid-template-columns: 38% 1fr;
    padding: 12px 0;
    border-bottom: 1px solid var(--border);
}
.run-actions {
    display: flex;
    flex-wrap: wrap;
    gap: 10px;
    margin-top: 16px;
}
.run-actions :deep(button),
.run-actions a {
    min-height: 44px;
    font-weight: 700;
}
@media (max-width: 767px) {
    .run-panel {
        padding: 16px;
    }
    .run-panel:has(.run-channels) h2 {
        font-size: 18px;
        line-height: 24px;
    }
    .run-facts div {
        grid-template-columns: 1fr;
    }
}
</style>
