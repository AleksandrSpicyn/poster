<script setup lang="ts">
import { computed, ref } from 'vue';
import { Link, usePage } from '@inertiajs/vue3';
import { useRoute } from 'ziggy-js';
import Heading from '@/components/Heading.vue';
import ProjectArtwork from '@/components/projects/ProjectArtwork.vue';
import PublicationMetricsFilter from '@/components/PublicationMetricsFilter.vue';
import PublicationCounters from '@/components/PublicationCounters.vue';
import PublicationMetricChart from '@/components/PublicationMetricChart.vue';
import { Button } from '@/components/ui/button';
import { amount, counterNames, dateLabel, observedCountersByDate, platformNames, rangeLabel, signed } from '@/types/analytics';
import type { AnalyticsFilters, Counter, MetricsReport, PublicationTimeline } from '@/types/analytics';

const props = defineProps<{ report: MetricsReport; detail?: boolean }>();
const page = usePage();
const route = useRoute();
const filters = computed(() => props.report.filters);
type View = 'overview' | 'filters' | 'drill' | 'publication' | 'data' | 'coverage';
const params = computed(() => new URLSearchParams(page.url.split('?')[1] ?? ''));
const view = computed<View>(() => {
    const v = params.value.get('view');
    return v && ['filters', 'drill', 'publication', 'data', 'coverage'].includes(v) ? (v as View) : 'overview';
});
const filterProject = ref<number | null>(filters.value.project);
const project = computed(() => props.report.projects.find((p) => p.id === (view.value === 'filters' ? filterProject.value : filters.value.project)));
const selected = computed(() => {
    const key = params.value.get('publication');
    return key ? props.report.publications.find((p) => p.key === key) : props.report.publications[0];
});
const selectedProject = computed(() => props.report.projects.find((p) => p.id === selected.value?.projectId) ?? project.value);
const start = computed(() => Object.keys(props.report.daily)[0] ?? props.report.from);
const interval = computed(() => rangeLabel(start.value, props.report.to));
const period = computed(() => (filters.value.from ? interval.value : filters.value.days + ' дней'));
const names = counterNames;
const metric = computed(() => filters.value.metric);
const list = computed(() =>
    props.report.publications.filter((p) => p.latest[metric.value] != null).sort((a, b) => b.latest[metric.value]! - a.latest[metric.value]!),
);
const bestPublications = computed(() => list.value.slice(0, 5));
const showGrowth = ref(false);
const dailySnapshots = computed(() => observedCountersByDate(props.report));
const scope = computed(() =>
    [
        project.value?.name ?? 'Все проекты',
        filters.value.platform ? platformNames[filters.value.platform] : null,
        props.detail ? period.value : filters.value.from ? interval.value : 'Последние ' + period.value,
    ]
        .filter(Boolean)
        .join(' / '),
);
const mainTitle = computed(() => (view.value === 'drill' ? 'Публикации по показателю' : props.detail ? 'Аналитика публикаций' : 'Обзор студии'));
const subtitle = computed(() =>
    view.value === 'overview' ? scope.value : view.value === 'filters' ? 'Фильтры' : view.value === 'drill' ? names[metric.value] : undefined,
);
function url(v: View, patch: Partial<AnalyticsFilters> = {}, item?: PublicationTimeline, detail = props.detail) {
    return route(detail ? 'dashboard.analytics' : 'dashboard', {
        ...filters.value,
        ...patch,
        view: v === 'overview' ? undefined : v,
        publication: item?.key,
    });
}
const platforms = computed(() => {
    const sums = new Map<string, number>();
    for (const p of list.value) {
        const value = p.latest[metric.value];
        if (value != null) sums.set(p.platform, (sums.get(p.platform) ?? 0) + value);
    }
    const total = [...sums.values()].reduce((s, n) => s + Math.max(n, 0), 0);
    return [...sums].map(([key, value]) => ({ key, value, pct: total ? (100 * Math.max(value, 0)) / total : 0 })).sort((a, b) => b.value - a.value);
});
function entry(label: string, key: Counter) {
    const growth = props.report.summaries[key];
    return {
        label,
        value: props.report.totals[key],
        note: growth == null ? undefined : 'Измеренный прирост: ' + signed(growth),
        href: url('drill', { metric: key }),
    };
}
const dashboardCards = computed(() => Object.entries(names).map(([key, label]) => entry(label, key as Counter)));
const selectedWatch = computed(() => {
    const rows = props.report.publications;
    const minutes = rows.map((p) => p.watchMinutesChange);
    return {
        duration: rows.length === 1 ? (rows[0].latest.averageViewDuration ?? null) : null,
        percent: rows.length === 1 ? (rows[0].latest.averageViewPercentage ?? null) : null,
        hours: minutes.length && minutes.every((v): v is number => v != null) ? minutes.reduce((s, v) => s + v, 0) / 60 : null,
    };
});
const selectedDays = computed(() =>
    Object.keys(props.report.daily).map((date) => ({
        date,
        value: selected.value?.snapshots[date] ?? null,
        interval: selected.value?.intervals.find((i) => i.to === date),
    })),
);
const snapshotSeries = computed(() => Object.fromEntries(selectedDays.value.map((d) => [d.date, d.value])));
const tabs: { name: string; view: View }[] = [
    { name: 'Обзор', view: 'overview' },
    { name: 'Отчёт ролика', view: 'publication' },
    { name: 'Данные графика', view: 'data' },
    { name: 'Состояние сбора по каналам', view: 'coverage' },
];
</script>
<template>
    <div class="workspace-page analytics-screen" :data-view="view" :class="{ 'analytics-state': view !== 'overview' }">
        <header class="analytics-header">
            <Heading :title="mainTitle" :description="subtitle" />
            <Button v-if="view === 'overview'" as-child
                ><Link :href="url('filters')">{{ detail ? 'Изменить фильтры' : 'Выбрать период' }}</Link></Button
            >
        </header>
        <template v-if="view === 'overview'">
            <h2>Последние показатели за выбранный период</h2>
            <PublicationCounters :items="dashboardCards" />
            <div class="analytics-panels">
                <section class="studio-panel chart-panel">
                    <h2>{{ names[metric] }} по дням</h2>
                    <div class="analytics-tabs" aria-label="Режим графика">
                        <Button size="sm" :variant="showGrowth ? 'outline' : 'default'" :aria-pressed="!showGrowth" @click="showGrowth = false"
                            >Наблюдения</Button
                        >
                        <Button size="sm" :variant="showGrowth ? 'default' : 'outline'" :aria-pressed="showGrowth" @click="showGrowth = true"
                            >Прирост</Button
                        >
                    </div>
                    <p class="secondary">
                        {{ interval }} ·
                        {{ showGrowth ? 'Разница между замерами за соседние дни' : 'Сумма счётчиков роликов, измеренных в этот день' }}
                    </p>
                    <PublicationMetricChart
                        :values="showGrowth ? report.daily : dailySnapshots"
                        :snapshots="!showGrowth"
                        :label="names[metric] + (showGrowth ? ' · прирост' : ' · наблюдения')"
                    />
                </section>
                <section class="studio-panel platforms-panel">
                    <h2>
                        <Link :href="url('coverage', {}, selected, true)">{{ names[metric] }} по площадкам</Link>
                    </h2>
                    <Link
                        v-for="row in platforms"
                        :key="row.key"
                        :href="url('overview', { platform: row.key }, undefined, true)"
                        class="platform-row"
                    >
                        <span>{{ platformNames[row.key] ?? row.key }}</span><strong>{{ amount(row.value) }}</strong
                        ><i :class="'bar-' + row.key" :style="{ '--progress': row.pct + '%' }"
                    /></Link>
                    <p v-if="!platforms.length" class="secondary">Нет измерений за период</p>
                    <template v-if="detail">
                        <p v-if="selectedWatch.percent != null">Средний процент просмотра: {{ amount(selectedWatch.percent) }} %</p>
                        <p v-if="selectedWatch.duration != null">Среднее время просмотра: {{ amount(selectedWatch.duration) }} с</p>
                        <p v-if="selectedWatch.hours != null">Прирост времени просмотра: {{ amount(selectedWatch.hours) }} ч</p>
                    </template>
                </section>
            </div>
            <section v-if="bestPublications.length" class="best-publications">
                <h2>Лучшие публикации</h2>
                <Link v-for="p in bestPublications" :key="p.key" class="publication-link" :href="url('publication', {}, p, true)">
                    <ProjectArtwork :name="p.title" :cover="report.projects.find((x) => x.id === p.projectId)?.cover" /><span
                        ><strong>{{ p.title }}</strong
                        ><small
                            >{{ platformNames[p.platform] ?? p.platform }} ·
                            {{ amount(p.latest[metric]) + ' · ' + names[metric].toLowerCase() }}</small
                        ></span
                    >
                </Link>
            </section>
        </template>
        <template v-else>
            <nav v-if="['publication', 'data', 'coverage'].includes(view)" class="analytics-tabs" aria-label="Отчёт">
                <Link
                    v-for="tab in tabs"
                    :key="tab.view"
                    :aria-current="view === tab.view ? 'page' : undefined"
                    :href="url(tab.view, {}, selected, true)"
                    >{{ tab.name }}</Link
                >
            </nav>
            <div class="analytics-columns" :class="{ 'tabbed-report': ['publication', 'data', 'coverage'].includes(view) }">
                <PublicationMetricsFilter
                    v-if="view === 'filters'"
                    :report="report"
                    :detail="detail"
                    :interval="interval"
                    @select-project="filterProject = $event"
                />
                <section v-else-if="view === 'drill'" class="studio-panel">
                    <h2>{{ names[metric] }}</h2>
                    <strong>{{ names[metric] }} · {{ amount(report.totals[metric]) }}</strong>
                    <div class="analytics-tabs filter-chips">
                        <span>{{ project?.name ?? 'Все проекты' }}</span><span v-if="filters.platform">{{ platformNames[filters.platform] }}</span
                        ><span>{{ interval }}</span>
                    </div>
                    <Link v-for="p in list" :key="p.key" class="drill-row" :href="url('publication', {}, p, true)"
                        ><strong>{{ p.title }}</strong
                        ><small
                            >{{ amount(p.latest[metric]) }}<template v-if="p.change != null"> · прирост {{ signed(p.change) }}</template></small
                        ></Link
                    >
                    <div class="report-row">
                        <span>Всего по выборке</span><span>{{ amount(report.totals[metric]) }} · Публикаций: {{ list.length }}</span>
                    </div>
                    <Link :href="url('overview')" class="report-link">Вернуться в обзор ↗</Link>
                </section>
                <section v-else-if="view === 'coverage'" class="studio-panel">
                    <h2>Состояние сбора по каналам</h2>
                    <div v-for="c in report.coverage" :key="c.id" class="coverage-row" :data-state="c.state">
                        <Link :href="route('dashboard.analytics', { ...filters, channel: c.id })" class="font-bold hover:underline"
                            >{{ c.name }} · {{ platformNames[c.platform] ?? c.platform }}</Link
                        ><small
                            >{{ c.status
                            }}{{
                                c.last_success_at
                                    ? ' · ' +
                                      dateLabel(c.last_success_at, { day: 'numeric', month: 'short', hour: '2-digit', minute: '2-digit' }) +
                                      ' UTC'
                                    : ''
                            }}</small
                        >
                    </div>
                    <p v-if="!report.coverage.length">В этой выборке нет каналов.</p>
                    <div class="report-row">
                        <span>Период отчёта</span><span>{{ interval }}</span>
                    </div>
                </section>
                <section v-else class="studio-panel">
                    <h2>{{ view === 'data' ? 'Данные графика' : (selected?.title ?? 'Отчёт ролика') }}</h2>
                    <template v-if="selected">
                        <div v-if="view === 'publication'" class="publication-link">
                            <ProjectArtwork :name="selected.title" :cover="selectedProject?.cover" /><strong>{{
                                [selected.title, selectedProject?.name].filter(Boolean).join(' · ')
                            }}</strong>
                        </div>
                        <div v-if="view === 'publication'" class="report-row">
                            <span>Источник</span
                            ><span>{{ [platformNames[selected.platform], selected.channelName, 'Наблюдения API'].filter(Boolean).join(' · ') }}</span>
                        </div>
                        <strong>{{
                            view === 'data'
                                ? 'Наблюдения ' + { views: 'просмотров', likes: 'лайков', shares: 'репостов', comments: 'комментариев' }[metric]
                                : names[metric] + ' на дату'
                        }}</strong>
                        <p class="secondary">{{ interval }} · UTC</p>
                        <PublicationMetricChart snapshots :values="snapshotSeries" :label="names[metric] + ' по наблюдениям'" />
                        <template v-if="view === 'data'"
                            ><div
                                v-for="day in selectedDays"
                                :key="day.date"
                                class="report-row"
                                :class="{ 'coverage-row observation-gap': day.value === null }"
                            >
                                <span>{{ dateLabel(day.date) }}</span><span>{{
                                    day.value === null
                                        ? 'Нет наблюдения'
                                        : amount(day.value) +
                                          ' · ' +
                                          (day.interval
                                              ? (new Date(day.interval.to).getTime() - new Date(day.interval.from).getTime() > 86400000
                                                    ? 'прирост за ' + rangeLabel(day.interval.from, day.interval.to) + ' '
                                                    : 'прирост ') + signed(day.interval.change)
                                              : 'первое наблюдение')
                                }}</span>
                            </div></template
                        >
                        <template v-else
                            ><div class="report-row">
                                <span>Наблюдения</span
                                ><span>{{
                                    selectedDays
                                        .map(
                                            (d) =>
                                                dateLabel(d.date, { day: '2-digit', month: '2-digit' }) +
                                                ' — ' +
                                                (d.value === null ? 'пропуск' : amount(d.value)),
                                        )
                                        .join(' · ')
                                }}</span>
                            </div>
                            <div
                                v-for="i in selected.intervals.filter((i) => new Date(i.to).getTime() - new Date(i.from).getTime() > 86400000)"
                                :key="i.to"
                                class="report-row"
                            >
                                <span>{{ rangeLabel(i.from, i.to) }}</span><span
                                    >{{ signed(i.change) }} за интервал · разбивка по дням неизвестна</span
                                >
                            </div>
                            <a v-if="selected.url" class="report-link" :href="selected.url" target="_blank" rel="noopener noreferrer"
                                >Открыть публикацию на площадке ↗</a
                            >
                            <small v-if="selected.url" class="secondary"
                                >{{ platformNames[selected.platform] }} · {{ selected.title }}</small
                            ></template
                        >
                    </template>
                    <p v-else>За выбранный период публикация недоступна.</p>
                </section>
                <aside class="studio-panel report-card">
                    <template v-if="view === 'coverage'"
                        ><h2>Период сбора</h2>
                        <small>Проект</small>
                        <p>{{ project?.name ?? 'Все проекты' }}</p>
                        <small>Время</small>
                        <p>UTC</p></template
                    >
                    <template v-else-if="view === 'filters' || view === 'drill'"
                        ><ProjectArtwork v-if="project" :name="project.name" :cover="project.cover" />
                        <h2>{{ project?.name ?? 'Все проекты' }}</h2>
                        <small v-if="project">{{
                            project.is_clipping ? 'Клиппинг' : project.content_mode === 'series' ? 'Сериал' : 'Короткие видео'
                        }}</small></template
                    >
                    <template v-else-if="selected"
                        ><ProjectArtwork :name="selected.title" :cover="selectedProject?.cover" />
                        <h2>{{ selected.title }}</h2>
                        <small>Проект</small>
                        <p>{{ selectedProject?.name ?? '—' }}</p>
                        <small>Источник отчёта</small>
                        <p>{{ platformNames[selected.platform] }}</p></template
                    >
                </aside>
            </div></template
        >
    </div>
</template>
<style scoped>
.analytics-screen {
    gap: 20px;
}
.analytics-header {
    display: flex;
    align-items: start;
    justify-content: space-between;
    gap: 12px;
}
.analytics-header h1 {
    overflow-wrap: anywhere;
}
.analytics-header [data-slot='button'] {
    min-height: 44px;
    font-weight: 700;
}
.analytics-panels {
    display: grid;
    grid-template-columns: minmax(0, 1.85fr) minmax(0, 1fr);
    gap: 16px;
}
.analytics-panels .studio-panel {
    min-height: 322px;
}
.analytics-panels h2,
.analytics-columns h2,
.best-publications h2 {
    margin: 0 0 10px;
    font-size: 20px;
    font-weight: 700;
}
.secondary,
.publication-link small,
.report-card small,
.drill-row small,
.coverage-row small {
    display: block;
    color: var(--muted-foreground);
    font-size: 14px;
}
.platforms-panel,
.chart-panel {
    padding: 16px 24px;
}
.platform-row {
    display: grid;
    grid-template-columns: 1fr auto;
    align-items: center;
    gap: 6px;
    padding: 11px 0;
    border-bottom: 1px solid var(--border);
    font-size: 14px;
}
.platform-row i {
    grid-column: 1/-1;
    background: var(--muted);
    height: 4px;
}
.platform-row i::before {
    display: block;
    content: '';
    height: 100%;
    width: var(--progress);
    background: var(--primary);
}
.platform-row .bar-instagram::before {
    background: #beb2e2;
}
.platform-row .bar-tiktok::before {
    background: #e8ac7d;
}
.best-publications h2 {
    margin-bottom: 14px;
}
.publication-link {
    display: flex;
    align-items: center;
    gap: 12px;
    margin: 8px 0;
}
.publication-link > div:first-child {
    height: 54px;
    width: 80px;
    border-radius: 8px;
    flex: none;
}
.publication-link small,
.filter-channel small,
.drill-row small {
    margin-top: 4px;
}
.analytics-state {
    padding-top: 18px;
    gap: 20px;
}
.analytics-columns {
    display: grid;
    grid-template-columns: minmax(0, 2.23fr) minmax(0, 1fr);
    gap: 16px;
    align-items: start;
}
.analytics-state:has(.tabbed-report) {
    padding-top: 15px;
}
.analytics-tabs {
    display: flex;
    flex-wrap: wrap;
    gap: 8px;
}
.analytics-tabs :is(a, span) {
    padding: 10px 12px;
    border-radius: 8px;
    background: var(--muted);
    font-size: 14px;
}
.analytics-tabs a[aria-current='page'] {
    color: var(--primary);
    background: color-mix(in srgb, var(--primary) 14%, var(--card));
}
.report-row > span:first-child {
    font-size: 14px;
    color: var(--muted-foreground);
}
.report-card {
    padding: 16px;
}
.report-card > div {
    height: 112px;
    border-radius: 8px;
}
.report-card h2 {
    margin: 12px 0 4px;
}
.report-card small {
    margin: 10px 0 0;
}
.filter-chips {
    margin: 12px 0;
}
.drill-row,
.report-row {
    display: grid;
    gap: 4px;
    padding: 10px 0;
    border-bottom: 1px solid var(--border);
    overflow-wrap: anywhere;
}
.report-row {
    grid-template-columns: minmax(0, 0.6fr) minmax(0, 1fr);
    font-size: 14px;
}

.report-link {
    display: inline-flex;
    padding: 12px 0;
    color: var(--primary);
    font-weight: 700;
}
.coverage-row {
    padding: 10px 12px;
    margin-bottom: 8px;
    border-inline-start: 3px solid var(--border);
    border-radius: 6px;
    background: var(--muted);
}
.observation-gap {
    display: grid;
    grid-template-columns: 1fr;
    margin: 4px 0;
    padding-inline-start: 38px;
    position: relative;
    border-inline-start-color: #e8ac7d;
}
.observation-gap::before {
    content: '!';
    position: absolute;
    inset: 12px auto auto 11px;
    border: 1px solid currentColor;
    border-radius: 50%;
    width: 16px;
    height: 16px;
    line-height: 14px;
    text-align: center;
    color: #e8ac7d;
}
.analytics-screen .observation-gap > span:first-child {
    color: #e8ac7d;
}
@media (min-width: 768px) {
    .analytics-screen[data-view='data'] .report-row:not(.observation-gap),
    .analytics-screen[data-view='publication'] .report-row {
        min-height: 47px;
    }

    .analytics-screen[data-view='coverage'] .coverage-row {
        min-height: 64px;
    }
}
@media (min-width: 768px) and (max-width: 1099px) {
    .analytics-screen:not(.analytics-state) > .publication-counters {
        margin-top: 5px;
    }
}
@media (min-width: 1920px) {
    .analytics-panels {
        grid-template-columns: minmax(0, 1.835fr) minmax(0, 1fr);
    }
}
.observation-gap span:first-child {
    font-weight: 700;
}

.coverage-row[data-state='collected'] {
    border-color: var(--primary);
}
.coverage-row[data-state='no_permission'] {
    border-color: var(--destructive);
}
.coverage-row[data-state='temporary_error'],
.coverage-row[data-state='rate_limited'] {
    border-color: #e8ac7d;
}
@media (min-width: 1100px) {
    .analytics-screen:not(.analytics-state) > .publication-counters {
        margin-top: 3px;
    }
    .analytics-columns.tabbed-report {
        grid-template-columns: minmax(0, 2.36fr) minmax(0, 1fr);
        gap: 20px;
    }
}
@media (min-width: 1920px) {
    .analytics-panels,
    .analytics-screen > .publication-counters,
    .best-publications,
    .analytics-columns {
        max-width: 1440px;
        width: 100%;
    }
}
@media (min-width: 1920px) {
    .analytics-columns.tabbed-report {
        max-width: 1236px;
        grid-template-columns: minmax(0, 2.43fr) minmax(0, 1fr);
        gap: 22px;
    }
}
@media (min-width: 768px) and (max-width: 1099px) {
    .analytics-panels {
        grid-template-columns: 1fr;
    }
    .analytics-panels .platforms-panel {
        min-height: 247px;
    }
}
@media (max-width: 1099px) {
    .analytics-columns {
        grid-template-columns: 1fr;
    }
    .report-card {
        display: none;
    }
    .analytics-header {
        display: grid;
        justify-content: start;
    }
    .analytics-header > [data-slot='button'] {
        justify-self: start;
    }
}
@media (max-width: 767px) {
    .analytics-panels {
        grid-template-columns: 1fr;
    }
    .analytics-panels .studio-panel {
        min-height: 0;
    }
    .analytics-header {
        gap: 14px;
    }
    .platforms-panel,
    .chart-panel {
        padding: 16px;
    }
    .report-row {
        grid-template-columns: 1fr;
    }
    .analytics-tabs :is(a, span) {
        font-size: 13px;
    }
    .analytics-screen {
        gap: 18px;
    }
}
</style>
