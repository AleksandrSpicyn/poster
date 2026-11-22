<script setup lang="ts">
import { Link, router } from '@inertiajs/vue3';
import { computed } from 'vue';
import { useRoute } from 'ziggy-js';

type Counter = 'views' | 'likes' | 'shares' | 'comments';
type Filters = { days: number; metric: Counter; platform: string | null; project: number | null };
type Timeline = {
    account: string;
    platform: string;
    mediaId: string;
    title: string;
    change: number | null;
    snapshots: Record<string, number>;
    intervals: { from: string; to: string; change: number }[];
};
export type MetricsReport = {
    from: string;
    to: string;
    updatedAt: string | null;
    filters: Filters;
    change: number | null;
    daily: Record<string, number | null>;
    publications: Timeline[];
    projects: { id: number; name: string }[];
    coverage: { name: string; status: string; last_success_at: string | null }[];
};
const props = defineProps<{ report: MetricsReport; detail?: boolean }>();
const route = useRoute();
const counters: Record<Counter, string> = { views: 'Просмотры', likes: 'Лайки', shares: 'Репосты', comments: 'Комментарии' };
const chart = computed(() => {
    const days = Object.entries(props.report.daily);
    const values = days.flatMap(([, value]) => (value === null ? [] : [value]));
    const low = Math.min(0, ...values);
    const high = Math.max(0, ...values);
    const y = (value: number) => (high === low ? 72 : 134 - ((value - low) / (high - low)) * 120);
    const points = [];
    let path = '';
    let startsSegment = true;
    for (const [index, [date, value]] of days.entries()) {
        if (value === null) {
            startsSegment = true;
            continue;
        }
        const x = 12 + (index / Math.max(1, days.length - 1)) * 976;
        path += `${startsSegment ? 'M' : 'L'}${x},${y(value)} `;
        points.push({ date, value, x, y: y(value) });
        startsSegment = false;
    }
    return { path, points, zero: y(0) };
});
const format = new Intl.NumberFormat('ru', { signDisplay: 'exceptZero' });
function filter(key: keyof Filters, event: Event) {
    const value = (event.target as HTMLSelectElement).value;
    const filters = { ...props.report.filters, [key]: key === 'days' ? Number(value) : value || null };
    router.get(route(props.detail ? 'dashboard.analytics' : 'dashboard'), filters, {
        preserveScroll: true,
        preserveState: true,
        only: ['analytics'],
    });
}
</script>
<template>
    <section class="studio-panel" aria-labelledby="analytics-heading">
        <header class="flex flex-wrap items-start justify-between gap-4">
            <div>
                <h2 id="analytics-heading">{{ detail ? 'Наблюдения за период' : 'Аналитика публикаций' }}</h2>
                <Link v-if="!detail" :href="route('dashboard.analytics', report.filters)" class="underline">Подробный отчёт</Link>
                <p>{{ report.from }} — {{ report.to }} · UTC</p>
            </div>
            <div class="flex flex-wrap gap-3">
                <label
                    >Проект<select aria-label="Проект" :value="report.filters.project ?? ''" @change="filter('project', $event)">
                        <option value="">Все проекты</option>
                        <option v-for="project in report.projects" :key="project.id" :value="project.id">{{ project.name }}</option>
                    </select></label
                >
                <label
                    >Период
                    <select aria-label="Период" :value="report.filters.days" @change="filter('days', $event)">
                        <option v-for="period in [7, 30, 90]" :key="period" :value="period">{{ period }} дней</option>
                    </select>
                </label>
                <label
                    >Площадка
                    <select aria-label="Площадка" :value="report.filters.platform ?? ''" @change="filter('platform', $event)">
                        <option value="">Все площадки</option>
                        <option value="youtube">YouTube</option>
                        <option value="instagram">Instagram</option>
                        <option value="tiktok">TikTok</option>
                    </select>
                </label>
                <label
                    >Показатель
                    <select aria-label="Показатель" :value="report.filters.metric" @change="filter('metric', $event)">
                        <option v-for="(name, key) in counters" :key="key" :value="key">{{ name }}</option>
                    </select>
                </label>
            </div>
        </header>
        <h3 class="mt-5 font-semibold">{{ counters[report.filters.metric] }} · наблюдаемый прирост</h3>
        <p class="text-3xl font-semibold tabular-nums">{{ report.change === null ? 'Нет базы сравнения' : format.format(report.change) }}</p>
        <p v-if="report.updatedAt">Последнее наблюдение {{ new Date(report.updatedAt).toLocaleString('ru', { timeZone: 'UTC' }) }} UTC</p>
        <figure v-if="chart.points.length" class="mt-4 rounded-lg border px-4 py-3">
            <figcaption class="font-semibold">{{ counters[report.filters.metric] }} по дням</figcaption>
            <svg
                viewBox="0 0 1000 150"
                preserveAspectRatio="none"
                class="text-primary mt-3 h-40 w-full"
                role="img"
                aria-label="Суточный прирост по соседним наблюдениям"
            >
                <line v-for="row in [14, 74, 134]" :key="row" x1="0" x2="1000" :y1="row" :y2="row" stroke="var(--border)" />
                <line x1="0" x2="1000" :y1="chart.zero" :y2="chart.zero" stroke="var(--muted-foreground)" stroke-dasharray="3 6" />
                <path :d="chart.path" fill="none" stroke="currentColor" stroke-width="2" vector-effect="non-scaling-stroke" />
                <circle v-for="point in chart.points" :key="point.date" :cx="point.x" :cy="point.y" r="3" fill="currentColor">
                    <title>{{ point.date }} · {{ format.format(point.value) }}</title>
                </circle>
            </svg>
            <div class="text-muted-foreground flex justify-between text-xs">
                <time>{{ report.from }}</time
                ><time>{{ report.to }}</time>
            </div>
            <details class="mt-3 text-sm">
                <summary class="cursor-pointer">Данные графика</summary>
                <dl class="mt-2 grid grid-cols-[auto_1fr] gap-x-4">
                    <template v-for="(value, date) in report.daily" :key="date">
                        <dt>{{ date }}</dt>
                        <dd>{{ value === null ? 'Нет суточной пары' : format.format(value) }}</dd>
                    </template>
                </dl>
            </details>
        </figure>
        <p v-if="report.publications.length" class="mt-2 text-sm">
            График учитывает соседние дни. Прирост за пропущенные дни указан в интервалах публикации.
        </p>
        <p v-else class="mt-4">За выбранный период сохранённых наблюдений нет.</p>
        <details v-if="report.coverage.length" class="mt-2 border-t py-2">
            <summary class="cursor-pointer">Состояние сбора по каналам</summary>
            <ul class="mt-2 space-y-2">
                <li v-for="(channel, index) in report.coverage" :key="index">
                    {{ channel.name }} · {{ channel.status }}
                    <small v-if="channel.last_success_at">
                        · {{ new Date(channel.last_success_at).toLocaleString('ru', { timeZone: 'UTC' }) }} UTC</small
                    >
                </li>
            </ul>
        </details>
        <details v-for="item in report.publications" :key="JSON.stringify([item.platform, item.account, item.mediaId])" class="mt-2 border-t py-2">
            <summary class="cursor-pointer">
                {{ item.title }} · {{ item.platform }} · {{ item.change === null ? 'Нет базы сравнения' : format.format(item.change) }}
            </summary>
            <p class="break-all">{{ item.account }} · {{ item.mediaId }}</p>
            <p v-if="!Object.keys(item.snapshots).length">Площадка не вернула показатель.</p>
            <dl v-else class="grid grid-cols-[auto_1fr] gap-x-4">
                <template v-for="(value, date) in item.snapshots" :key="date"
                    ><dt>{{ date }}</dt>
                    <dd>{{ value.toLocaleString('ru') }}</dd></template
                >
            </dl>
            <p v-for="interval in item.intervals" :key="interval.to">
                {{ interval.from }} — {{ interval.to }} · {{ format.format(interval.change) }}
            </p>
        </details>
    </section>
</template>
<style scoped>
select {
    display: block;
    padding: 0.375rem 0.5rem;
    border: 1px solid var(--border);
    border-radius: 0.375rem;
    background: var(--background);
    color: var(--foreground);
}
</style>
