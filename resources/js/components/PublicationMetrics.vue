<script setup lang="ts">
import { router } from '@inertiajs/vue3';
import { computed } from 'vue';
import { useRoute } from 'ziggy-js';

type Counter = 'views' | 'likes' | 'shares' | 'comments';
type Filters = { days: number; metric: Counter; platform: string | null };
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
};
const props = defineProps<{ report: MetricsReport }>();
const route = useRoute();
const counters: Record<Counter, string> = { views: 'Просмотры', likes: 'Лайки', shares: 'Репосты', comments: 'Комментарии' };
const scale = computed(() => Math.max(1, ...Object.values(props.report.daily).map((value) => Math.abs(value ?? 0))));
const format = new Intl.NumberFormat('ru', { signDisplay: 'exceptZero' });
function filter(key: keyof Filters, event: Event) {
    const value = (event.target as HTMLSelectElement).value;
    const filters = { ...props.report.filters, [key]: key === 'days' ? Number(value) : value || null };
    router.get(route('dashboard'), filters, { preserveScroll: true, preserveState: true, only: ['analytics'] });
}
</script>
<template>
    <section class="studio-panel" aria-labelledby="analytics-heading">
        <header class="flex flex-wrap items-start justify-between gap-4">
            <div>
                <h2 id="analytics-heading">Аналитика публикаций</h2>
                <p>{{ report.from }} — {{ report.to }} · UTC</p>
            </div>
            <div class="flex flex-wrap gap-3">
                <label
                    >Период
                    <select :value="report.filters.days" @change="filter('days', $event)">
                        <option v-for="period in [7, 30, 90]" :key="period" :value="period">{{ period }} дней</option>
                    </select>
                </label>
                <label
                    >Площадка
                    <select :value="report.filters.platform ?? ''" @change="filter('platform', $event)">
                        <option value="">Все площадки</option>
                        <option value="youtube">YouTube</option>
                        <option value="instagram">Instagram</option>
                        <option value="tiktok">TikTok</option>
                    </select>
                </label>
                <label
                    >Показатель
                    <select :value="report.filters.metric" @change="filter('metric', $event)">
                        <option v-for="(name, key) in counters" :key="key" :value="key">{{ name }}</option>
                    </select>
                </label>
            </div>
        </header>
        <h3 class="mt-5 font-semibold">{{ counters[report.filters.metric] }} · наблюдаемый прирост</h3>
        <p class="text-3xl font-semibold tabular-nums">{{ report.change === null ? 'Нет базы сравнения' : format.format(report.change) }}</p>
        <p v-if="report.updatedAt">Последнее наблюдение {{ new Date(report.updatedAt).toLocaleString('ru', { timeZone: 'UTC' }) }} UTC</p>
        <div
            v-if="report.change !== null"
            class="chart mt-4 flex h-24 gap-px"
            role="img"
            aria-label="Суточный прирост по публикациям с двумя соседними наблюдениями"
        >
            <span
                v-for="(value, date) in report.daily"
                :key="date"
                class="relative min-w-0 flex-1"
                :title="date + ' · ' + (value === null ? 'Нет суточной пары' : format.format(value))"
                ><span
                    v-if="value !== null"
                    class="absolute inset-x-0"
                    :class="value < 0 ? 'bg-destructive top-1/2' : 'bg-primary bottom-1/2'"
                    :style="{ height: Math.max(1, (Math.abs(value) / scale) * 49) + '%' }"
            /></span>
        </div>
        <p v-if="report.publications.length" class="mt-2 text-sm">
            График учитывает соседние дни. Прирост за пропущенные дни указан в интервалах публикации.
        </p>
        <p v-else class="mt-4">За выбранный период сохранённых наблюдений нет.</p>
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
.chart {
    background: linear-gradient(to bottom, transparent calc(50% - 1px), var(--border) calc(50% - 1px), var(--border) 50%, transparent 50%);
}
select {
    display: block;
    padding: 0.375rem 0.5rem;
    border: 1px solid var(--border);
    border-radius: 0.375rem;
    background: var(--background);
    color: var(--foreground);
}
</style>
