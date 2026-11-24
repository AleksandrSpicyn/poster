import type { Counter, MetricsReport, PublicationTimeline } from '@/types/analytics';

export const metricPeriods = [
    { days: 7, label: 'Неделя', caption: 'за неделю' },
    { days: 30, label: 'Месяц', caption: 'за месяц' },
    { days: 180, label: 'Полгода', caption: 'за полгода' },
    { days: 365, label: 'Год', caption: 'за год' },
];

export const counterNames: Record<Counter, string> = { views: 'Просмотры', likes: 'Лайки', shares: 'Репосты', comments: 'Комментарии' };
export const platformNames: Record<string, string> = { youtube: 'YouTube', instagram: 'Instagram', facebook: 'Facebook', tiktok: 'TikTok', vk: 'VK' };
const numbers = new Intl.NumberFormat('ru-RU', { maximumFractionDigits: 1 });
export const amount = (value: number | null | undefined) => (value == null ? '—' : numbers.format(value));
export const signed = (value: number) => (value > 0 ? '+' + amount(value) : amount(value));
export function dateLabel(date: string, options: Intl.DateTimeFormatOptions = { day: 'numeric', month: 'long' }) {
    return new Intl.DateTimeFormat('ru-RU', { ...options, timeZone: 'UTC' }).format(new Date(date.length === 10 ? date + 'T00:00:00Z' : date));
}
export function rangeLabel(from: string, to: string) {
    return new Intl.DateTimeFormat('ru-RU', { day: 'numeric', month: 'long', timeZone: 'UTC' }).formatRange(
        new Date(from + 'T00:00:00Z'),
        new Date(to + 'T00:00:00Z'),
    );
}

export function observedCountersByDate(report: MetricsReport): Record<string, number | null> {
    const values: Record<string, number | null> = Object.fromEntries(Object.keys(report.daily).map((date) => [date, null]));
    for (const publication of report.publications) {
        for (const [date, value] of Object.entries(publication.snapshots)) {
            if (date in values) values[date] = (values[date] ?? 0) + value;
        }
    }
    return values;
}

export function rankedPublications(publications: PublicationTimeline[], metric: Counter) {
    return publications
        .flatMap((publication) => {
            const value = publication.latest[metric];
            return value == null ? [] : [{ publication, value }];
        })
        .sort((left, right) => right.value - left.value)
        .map((item) => item.publication);
}

export function platformShares(publications: PublicationTimeline[], metric: Counter) {
    const sums = new Map<string, number>();
    for (const p of publications) {
        const value = p.latest[metric];
        if (value != null) sums.set(p.platform, (sums.get(p.platform) ?? 0) + value);
    }
    const total = [...sums.values()].reduce((s, n) => s + Math.max(n, 0), 0);
    return [...sums].map(([key, value]) => ({ key, value, pct: total ? (100 * Math.max(value, 0)) / total : 0 })).sort((a, b) => b.value - a.value);
}

type ObservationInterval = PublicationTimeline['intervals'][number];

export function spansMultipleDays(interval: ObservationInterval) {
    return Date.parse(interval.to) - Date.parse(interval.from) > 86_400_000;
}

interface PublicationDay {
    date: string;
    value: number | null;
    interval?: ObservationInterval;
}

export function publicationDays(dates: string[], publication?: PublicationTimeline): PublicationDay[] {
    const intervals = new Map<string, ObservationInterval>();
    for (const interval of publication?.intervals ?? []) {
        if (!intervals.has(interval.to)) intervals.set(interval.to, interval);
    }
    return dates.map((date) => ({ date, value: publication?.snapshots[date] ?? null, interval: intervals.get(date) }));
}

export function observationLabel({ value, interval }: PublicationDay) {
    if (value === null) return 'Нет наблюдения';
    if (!interval) return amount(value) + ' · первое наблюдение';
    const period = spansMultipleDays(interval) ? ' за ' + rangeLabel(interval.from, interval.to) : '';
    return amount(value) + ' · прирост' + period + ' ' + signed(interval.change);
}
