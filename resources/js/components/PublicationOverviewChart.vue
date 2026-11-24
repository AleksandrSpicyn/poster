<script setup lang="ts">
import PublicationMetricChart from '@/components/PublicationMetricChart.vue';
import { Button } from '@/components/ui/button';
import { amount, counterNames as names, observedCountersByDate, signed } from '@/lib/analytics';
import type { Counter, MetricsReport } from '@/types/analytics';
import { computed } from 'vue';

const props = defineProps<{ report: MetricsReport; chartPeriod: string }>();
const showGrowth = defineModel<boolean>({ required: true });
const metric = computed(() => props.report.filters.metric);
const chartTitles: Record<Counter, string> = {
    views: 'Динамика просмотров',
    likes: 'Динамика лайков',
    shares: 'Динамика репостов',
    comments: 'Динамика комментариев',
};
const chartTotal = computed(() => {
    const growth = props.report.summaries[metric.value];
    return showGrowth.value && growth !== null ? signed(growth) : amount(showGrowth.value ? null : props.report.totals[metric.value]);
});
const dailySnapshots = computed(() => observedCountersByDate(props.report));
</script>

<template>
    <section class="studio-panel chart-panel">
        <h2>{{ chartTitles[metric] }}</h2>
        <p class="secondary">{{ showGrowth ? 'Прирост' : names[metric] }} {{ chartPeriod }}</p>
        <strong class="chart-total">{{ chartTotal }}</strong>
        <PublicationMetricChart
            overview
            :values="showGrowth ? report.daily : dailySnapshots"
            :snapshots="!showGrowth"
            :label="showGrowth ? 'Прирост · ' + names[metric] : names[metric]"
        />
        <div class="analytics-tabs chart-modes" role="group" aria-label="Режим графика">
            <Button size="sm" :variant="showGrowth ? 'outline' : 'default'" :aria-pressed="!showGrowth" @click="showGrowth = false"
                >Наблюдения</Button
            >
            <Button size="sm" :variant="showGrowth ? 'default' : 'outline'" :aria-pressed="showGrowth" @click="showGrowth = true">Прирост</Button>
        </div>
    </section>
</template>

<style scoped>
.chart-panel {
    padding: 16px 24px;
}
.chart-panel > h2 {
    margin: 0 0 12px;
    font-family: var(--font-sans);
    font-size: 20px;
    font-weight: 700;
    line-height: 1.4;
}
.secondary {
    display: block;
    color: var(--muted-foreground);
    font: 400 14px/1.5 var(--font-sans);
}
.analytics-tabs {
    display: flex;
    flex-wrap: wrap;
    gap: 8px;
}
.chart-modes {
    margin: 10px 0;
}
.chart-modes [aria-pressed='true'] {
    background: var(--accent);
    color: var(--ring);
    border: 1px solid var(--ring);
}
.chart-total {
    display: block;
    margin-top: 5px;
    font: 700 28px/1.5 var(--font-mono);
    font-variant-numeric: tabular-nums;
}
@media (max-width: 767px) {
    .chart-panel {
        padding: 16px;
    }
}
</style>
