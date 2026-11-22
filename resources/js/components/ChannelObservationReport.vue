<script setup lang="ts">
import Heading from '@/components/Heading.vue';
import PublicationMetricChart from '@/components/PublicationMetricChart.vue';
import { Button } from '@/components/ui/button';
import { counterNames, dateLabel, platformNames, rangeLabel, signed, type MetricsReport } from '@/types/analytics';
import { router } from '@inertiajs/vue3';
import { CircleX } from '@lucide/vue';
import { computed, ref, watch } from 'vue';
import { useRoute } from 'ziggy-js';

const props = defineProps<{ report: MetricsReport; observed: NonNullable<MetricsReport['channelReport']> }>();
const route = useRoute();
const compared = ref(false);
const refreshFailed = ref(false);
const picker = ref<HTMLSelectElement | null>(null);
const channel = computed(() => props.report.filters.channel);
const channels = computed(() =>
    props.report.channels.filter((item) => props.report.filters.project === null || item.projects.includes(props.report.filters.project)),
);
const coverage = computed(() => props.report.coverage.find((row) => row.id === channel.value));
const canCompare = computed(() => props.observed.currentChange !== null && props.observed.previousChange !== null);
const unavailable = computed(() => {
    if (!props.observed.previousObserved) return 'Нет наблюдений за предыдущий период';
    if (props.observed.previousChange === null) return 'Недостаточно наблюдений за предыдущий период';
    return 'Недостаточно наблюдений за выбранный период';
});
const dates = computed(() => Object.keys(props.observed.observations));
const gaps = computed(() => dates.value.filter((date) => props.observed.observations[date] === null));
const title = computed(() => counterNames[props.report.filters.metric] + ' на дату');
const lastSuccess = computed(() =>
    coverage.value?.last_success_at
        ? dateLabel(coverage.value.last_success_at) +
          ', ' +
          dateLabel(coverage.value.last_success_at, { hour: '2-digit', minute: '2-digit', hourCycle: 'h23' }) +
          ' UTC'
        : null,
);
const failed = computed(() => refreshFailed.value || ['temporary_error', 'rate_limited'].includes(coverage.value?.state ?? ''));
watch(channel, () => (compared.value = false));
function keepReport() {
    if (picker.value && channel.value) picker.value.value = String(channel.value);
    refreshFailed.value = true;
    return false;
}
function changeChannel(event: Event) {
    const id = Number((event.currentTarget as HTMLSelectElement).value);
    const next = channels.value.find((item) => item.id === id);
    if (!next || next.id === channel.value) return;
    const filters = props.report.filters;
    router.get(
        route('dashboard.analytics'),
        {
            days: filters.days,
            metric: filters.metric,
            channel: next.id,
            ...(filters.project ? { project: filters.project } : {}),
            ...(filters.format ? { format: filters.format } : {}),
            ...(filters.from && filters.to ? { from: filters.from, to: filters.to } : {}),
        },
        {
            preserveScroll: true,
            preserveState: true,
            onSuccess: () => {
                refreshFailed.value = false;
                picker.value?.focus();
            },
            onNetworkError: keepReport,
            onHttpException: (response) => (response.status >= 500 ? keepReport() : undefined),
        },
    );
}
</script>
<template>
    <div class="workspace-page channel-report">
        <Heading title="Аналитика публикаций" />
        <p class="theme-caption"><span class="dark:hidden">Светлая тема</span><span class="hidden dark:inline">Тёмная тема</span></p>
        <section class="studio-panel channel-panel">
            <h2><span class="dark:hidden">Светлая тема</span><span class="hidden dark:inline">Тёмная тема</span></h2>
            <label for="report-channel">Канал отчёта</label>
            <select ref="picker" id="report-channel" :value="channel" @change="changeChannel">
                <option v-for="item in channels" :key="item.id" :value="item.id">
                    {{ item.name }} · {{ platformNames[item.platform] ?? item.platform }}
                </option>
            </select>
            <h3>{{ title }}</h3>
            <p>
                {{ rangeLabel(dates[0], report.to) }}<template v-if="gaps.length === 1"> · {{ dateLabel(gaps[0]) }} наблюдение пропущено</template
                ><template v-else-if="gaps.length"> · Пропущено дней: {{ gaps.length }}</template>
            </p>
            <PublicationMetricChart :values="observed.observations" :label="title" snapshots />
            <div v-if="failed" role="status" class="report-error">
                <CircleX aria-hidden="true" />
                <div>
                    <strong>Не удалось обновить отчёт</strong>
                    <p>
                        {{ report.updatedAt ? 'Показаны сохранённые наблюдения.' : 'Сохранённых наблюдений нет.'
                        }}<template v-if="lastSuccess"> Последний успех: {{ lastSuccess }}.</template>
                    </p>
                </div>
            </div>
            <p v-else-if="coverage">
                {{ coverage.status }}<template v-if="lastSuccess"> · {{ lastSuccess }}</template>
            </p>
            <Button
                variant="secondary"
                :disabled="!canCompare"
                :aria-expanded="compared"
                aria-controls="previous-period-report"
                @click="compared = !compared"
                >Сравнить с предыдущим периодом</Button
            >
            <div v-if="!canCompare" class="reason">
                <span>Причина</span><span>{{ unavailable }}</span>
            </div>
            <section
                v-if="compared && observed.currentChange !== null && observed.previousChange !== null"
                id="previous-period-report"
                aria-label="Прирост за каждый период"
            >
                <h3>Прирост за каждый период</h3>
                <div class="reason">
                    <span>{{ rangeLabel(dates[0], report.to) }}</span><strong>{{ signed(observed.currentChange) }}</strong>
                </div>
                <div class="reason">
                    <span>{{ rangeLabel(observed.previousFrom, observed.previousTo) }}</span><strong>{{ signed(observed.previousChange) }}</strong>
                </div>
            </section>
        </section>
    </div>
</template>
<style scoped>
.channel-report {
    gap: 16px;
    padding-block-start: 16px;
}
.theme-caption,
.channel-panel > p,
.channel-panel > label,
.report-error p {
    color: var(--muted-foreground);
    font-size: 14px;
}
.channel-panel {
    display: grid;
    gap: 8px;
    align-content: start;
    padding: clamp(16px, 1.5vw, 24px);
}
.channel-panel h2 {
    margin: 0 0 8px;
    font-size: 20px;
}
.channel-panel h3 {
    font-size: 16px;
    font-weight: 700;
}
.channel-panel select {
    border: 1px solid var(--border);
    border-radius: 8px;
    color: var(--foreground);
    background: var(--background);
    height: 44px;
    padding: 0 12px;
    width: 100%;
}
.channel-panel select:focus-visible {
    outline: 2px solid var(--ring);
    outline-offset: 2px;
}
.channel-panel :deep(.metric-chart) {
    color: var(--sidebar-accent-foreground);
    padding-top: 6px;
}
.channel-panel :deep(.chart-plot) {
    height: 96px;
}
.channel-panel .report-error {
    display: flex;
    align-items: flex-start;
    gap: 12px;
    border-inline-start: 3px solid var(--destructive);
    padding: 12px;
    background: var(--muted);
    border-radius: 8px;
}
.report-error svg {
    width: 20px;
    height: 20px;
    color: var(--destructive);
}
.report-error strong {
    color: var(--destructive);
}
.channel-panel > button:disabled {
    opacity: 1;
    color: var(--muted-foreground);
    background: var(--muted);
}
.channel-panel > button {
    width: fit-content;
    min-height: 44px;
    margin-top: 6px;
}
.reason {
    display: grid;
    grid-template-columns: minmax(110px, 39%) 1fr;
    gap: 0 10px;
    padding-block: 4px 16px;
    border-bottom: 1px solid var(--border);
    font-size: 14px;
}
@media (max-width: 767px) {
    .reason {
        grid-template-columns: 1fr;
    }
    .channel-panel > button {
        width: 100%;
    }
}
</style>
