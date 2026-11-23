<script setup lang="ts">
import { amount, dateLabel } from '@/types/analytics';
import { computed } from 'vue';
const props = defineProps<{ values: Record<string, number | null>; label: string; snapshots?: boolean; overview?: boolean }>();
const chart = computed(() => {
    const entries = Object.entries(props.values);
    const numbers = entries.flatMap(([, v]) => (v == null ? [] : [v]));
    const minimum = numbers.reduce((a, b) => Math.min(a, b), numbers[0] ?? 0);
    const maximum = numbers.reduce((a, b) => Math.max(a, b), numbers[0] ?? 0);
    const spread = Math.max(1, maximum - minimum);
    const magnitude = 10 ** Math.floor(Math.log10(spread / 2));
    const step = Math.max(1, Math.ceil(spread / (2 * magnitude)) * magnitude);
    const range = Math.max(1, Math.max(0, maximum) - Math.min(0, minimum));
    const unit = 10 ** Math.floor(Math.log10(range / 3));
    const dailyStep = Math.max(1, ([1, 2, 5, 10].find((n) => n * unit >= range / 3) ?? 10) * unit);
    const zoom = props.snapshots && !props.overview;
    const low = zoom ? Math.floor(minimum / magnitude) * magnitude : Math.floor(Math.min(0, minimum) / dailyStep) * dailyStep;
    const high = zoom
        ? low + step * Math.ceil(Math.max(1, maximum - low) / step)
        : Math.max(low + dailyStep, Math.ceil(Math.max(0, maximum) / dailyStep) * dailyStep);
    const y = (v: number) => (100 * (high - v)) / (high - low);
    const tickCount = Math.round((high - low) / (zoom ? step : dailyStep)) + 1;
    const ticks = Array.from({ length: tickCount }, (_, i) => ({
        y: (100 * i) / (tickCount - 1),
        value: high - ((high - low) * i) / (tickCount - 1),
    }));
    const first = entries.length ? Date.parse(entries[0][0]) : 0;
    const last = entries.length ? Date.parse(entries[entries.length - 1][0]) : first;
    const points = entries.map(([date, value]) => ({
        date,
        value,
        x: first === last ? 50 : (100 * (Date.parse(date) - first)) / (last - first),
        y: value === null ? null : y(value),
    }));
    const segments: { x: number; y: number; date: string; value: number | null }[][] = [];
    let segment: (typeof segments)[number] = [];
    for (const point of points) {
        if (point.y === null) {
            if (segment.length) segments.push(segment);
            segment = [];
        } else segment.push({ ...point, y: point.y });
    }
    if (segment.length) segments.push(segment);
    const labelCount = Math.min(points.length, points.length <= 7 ? 7 : 5);
    const labels = Array.from({ length: labelCount }, (_, i) => points[Math.round((i * (points.length - 1)) / Math.max(1, labelCount - 1))]);
    const markers = props.overview ? segments.filter((part) => part.length === 1).map((part) => part[0]) : points.filter((p) => p.y !== null);
    return { ticks, points, segments, markers, labels, baseline: y(0), empty: numbers.length === 0 };
});
function shortNumber(v: number) {
    if (props.overview && Math.abs(v) >= 1000 && Math.abs(v) < 1000000) return amount(v / 1000) + 'к';
    return new Intl.NumberFormat('ru', { notation: Math.abs(v) >= 1000 ? 'compact' : 'standard', maximumFractionDigits: 1 }).format(v);
}
</script>
<template>
    <figure class="metric-chart" :class="{ snapshots, overview }" :aria-label="label">
        <p v-if="chart.empty" class="chart-empty">Нет наблюдений за выбранный период</p>
        <template v-else>
            <div class="chart-plot">
                <div v-for="tick in chart.ticks" :key="tick.y" class="chart-rule" :style="{ top: tick.y + '%' }">
                    <span>{{ snapshots && !overview ? Math.round(tick.value) : shortNumber(tick.value) }}</span>
                </div>
                <svg viewBox="0 0 100 100" preserveAspectRatio="none" aria-hidden="true">
                    <g v-for="(segment, index) in chart.segments" :key="index">
                        <polygon
                            v-if="overview || !snapshots"
                            :points="
                                [[segment[0].x, chart.baseline], ...segment.map((p) => [p.x, p.y]), [segment[segment.length - 1].x, chart.baseline]]
                                    .map((point) => point.join(','))
                                    .join(' ')
                            "
                            fill="var(--accent)"
                        />
                        <polyline
                            :points="segment.map((p) => p.x + ',' + p.y).join(' ')"
                            fill="none"
                            stroke="currentColor"
                            stroke-linejoin="round"
                            :stroke-width="overview ? 3 : 2"
                            vector-effect="non-scaling-stroke"
                        />
                    </g>
                </svg>
                <template v-if="snapshots && !overview">
                    <span
                        v-for="p in chart.points.filter((p) => p.y === null)"
                        :key="p.date"
                        class="chart-missing"
                        :style="{ left: p.x + '%' }"
                        :title="dateLabel(p.date) + ' · Нет наблюдения'"
                        >—</span
                    >
                </template>
                <span
                    v-for="p in chart.markers"
                    :key="p.date"
                    class="chart-dot"
                    :title="dateLabel(p.date) + ' · ' + amount(p.value)"
                    :style="{ left: p.x + '%', top: p.y + '%' }"
                />
            </div>
            <div class="chart-labels">
                <span v-for="p in chart.labels" :key="p.date" :style="{ left: p.x + '%' }">{{
                    dateLabel(
                        p.date,
                        snapshots && !overview
                            ? { day: '2-digit', month: '2-digit' }
                            : chart.points.length <= 7
                              ? { weekday: 'short' }
                              : { day: 'numeric', month: 'numeric' },
                    )
                }}</span>
            </div>
            <component :is="overview ? 'details' : 'div'" :class="overview ? 'chart-data' : 'sr-only'">
                <summary v-if="overview">Значения по дням</summary>
                <table>
                    <caption class="sr-only">
                        {{
                            label
                        }}
                    </caption>
                    <thead>
                        <tr>
                            <th scope="col">Дата</th>
                            <th scope="col">{{ label }}</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr v-for="p in chart.points" :key="p.date">
                            <th scope="row">{{ dateLabel(p.date) }}</th>
                            <td>{{ p.value === null ? 'Нет наблюдения' : amount(p.value) }}</td>
                        </tr>
                    </tbody>
                </table>
            </component>
        </template>
    </figure>
</template>
<style scoped>
.metric-chart {
    padding: 14px 9px 0 42px;
    color: var(--ring);
}
.chart-plot {
    position: relative;
    height: 176px;
}
.chart-plot svg {
    position: absolute;
    inset: 0;
    width: 100%;
    height: 100%;
    overflow: visible;
}
.chart-rule {
    position: absolute;
    left: 0;
    right: 0;
    border-top: 1px solid var(--border);
}
.chart-rule span {
    position: absolute;
    right: calc(100% + 8px);
    transform: translateY(-50%);
    font-size: 11px;
    color: var(--muted-foreground);
}
.chart-dot {
    position: absolute;
    width: 7px;
    height: 7px;
    transform: translate(-50%, -50%);
    border-radius: 100%;
    background: currentColor;
}
.chart-missing {
    position: absolute;
    top: 50%;
    transform: translate(-50%, -50%);
    color: var(--warning);
}
.snapshots {
    padding-left: 40px;
    padding-right: 14px;
}
.snapshots .chart-labels {
    margin-top: 4px;
}
@media (min-width: 768px) {
    .snapshots .chart-plot {
        height: 94px;
    }
}
.chart-labels {
    position: relative;
    margin: 10px 0 0;
    height: 18px;
    color: var(--muted-foreground);
    font-size: 11px;
}
.chart-labels span {
    position: absolute;
    transform: translateX(-50%);
    white-space: nowrap;
}
.chart-empty {
    color: var(--muted-foreground);
    padding: 40px 0;
}
@media (max-width: 767px) {
    .chart-plot {
        height: 127px;
    }
    .metric-chart {
        padding-left: 40px;
    }
}
.overview {
    margin-top: 16px;
    padding: 0;
}
.overview .chart-plot {
    margin: 10px 16px 0 42px;
    height: auto;
    aspect-ratio: 388 / 108;
}
.overview .chart-labels {
    margin: 10px 16px 0 42px;
}
.overview .chart-labels,
.overview .chart-rule span {
    font-family: var(--font-mono);
    font-variant-numeric: tabular-nums;
}
.overview .chart-rule span {
    left: -42px;
    right: auto;
}
.overview .chart-labels span::first-letter {
    text-transform: uppercase;
}
.chart-data {
    margin-top: 12px;
    font-size: 14px;
    color: var(--foreground);
}
.chart-data summary {
    cursor: pointer;
    min-height: 44px;
    align-content: center;
    color: var(--muted-foreground);
    font: 400 16px/1.5 var(--font-sans);
}
.chart-data table {
    width: 100%;
    border-collapse: collapse;
    font-variant-numeric: tabular-nums;
}
.chart-data th,
.chart-data td {
    padding: 8px 0;
    text-align: start;
    border-bottom: 1px solid var(--border);
}
.chart-data :is(th, td):last-child {
    text-align: end;
}
</style>
