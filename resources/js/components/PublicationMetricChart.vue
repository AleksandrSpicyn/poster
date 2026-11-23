<script setup lang="ts">
import { amount, dateLabel } from '@/types/analytics';
import { computed } from 'vue';
const props = defineProps<{ values: Record<string, number | null>; label: string; snapshots?: boolean }>();
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
    const low = props.snapshots ? Math.floor(minimum / magnitude) * magnitude : Math.floor(Math.min(0, minimum) / dailyStep) * dailyStep;
    const high = props.snapshots
        ? low + step * Math.ceil(Math.max(1, maximum - low) / step)
        : Math.max(low + dailyStep, Math.ceil(Math.max(0, maximum) / dailyStep) * dailyStep);
    const y = (v: number) => (100 * (high - v)) / (high - low);
    const tickCount = Math.round((high - low) / (props.snapshots ? step : dailyStep)) + 1;
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
    const segments: { x: number; y: number }[][] = [];
    let segment: { x: number; y: number }[] = [];
    for (const point of points) {
        if (point.y === null) {
            if (segment.length) segments.push(segment);
            segment = [];
        } else segment.push({ x: point.x, y: point.y });
    }
    if (segment.length) segments.push(segment);
    const labels = points.filter((_, i) => i === points.length - 1 || i % Math.ceil(points.length / 7) === 0);
    return { ticks, points, segments, labels, empty: numbers.length === 0 };
});
function shortNumber(v: number) {
    return new Intl.NumberFormat('ru', { notation: Math.abs(v) >= 1000 ? 'compact' : 'standard', maximumFractionDigits: 1 }).format(v);
}
</script>
<template>
    <figure class="metric-chart" :class="{ snapshots }" :aria-label="label">
        <p v-if="chart.empty" class="chart-empty">Нет наблюдений за выбранный период</p>
        <template v-else>
            <div class="chart-plot">
                <div v-for="tick in chart.ticks" :key="tick.y" class="chart-rule" :style="{ top: tick.y + '%' }">
                    <span>{{ snapshots ? Math.round(tick.value) : shortNumber(tick.value) }}</span>
                </div>
                <svg viewBox="0 0 100 100" preserveAspectRatio="none" aria-hidden="true">
                    <g v-for="(segment, index) in chart.segments" :key="index">
                        <polygon
                            v-if="!snapshots"
                            :points="
                                segment[0].x + ',100 ' + segment.map((p) => p.x + ',' + p.y).join(' ') + ' ' + segment[segment.length - 1].x + ',100'
                            "
                            fill="currentColor"
                            opacity=".12"
                        />
                        <polyline
                            :points="segment.map((p) => p.x + ',' + p.y).join(' ')"
                            fill="none"
                            stroke="currentColor"
                            stroke-width="2"
                            vector-effect="non-scaling-stroke"
                        />
                    </g>
                </svg>
                <template v-if="snapshots">
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
                    v-for="p in chart.points.filter((p) => p.y !== null)"
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
                        snapshots
                            ? { day: '2-digit', month: '2-digit' }
                            : chart.points.length <= 7
                              ? { weekday: 'short' }
                              : { day: 'numeric', month: 'numeric' },
                    )
                }}</span>
            </div>
            <table class="sr-only">
                <caption>
                    {{
                        label
                    }}
                </caption>
                <tbody>
                    <tr v-for="p in chart.points" :key="p.date">
                        <th>{{ dateLabel(p.date) }}</th>
                        <td>{{ p.value === null ? 'Нет наблюдения' : amount(p.value) }}</td>
                    </tr>
                </tbody>
            </table>
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
</style>
