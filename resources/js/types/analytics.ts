import type { ProjectSummary } from '@/types/projects';

export type Counter = 'views' | 'likes' | 'shares' | 'comments';
export type AnalyticsFilters = {
    days: number;
    metric: Counter;
    platform: string | null;
    project: number | null;
    from: string | null;
    to: string | null;
    format: 'short' | 'series' | null;
    exclude_channels: number[];
};
export type AnalyticsProject = Pick<ProjectSummary, 'id' | 'name' | 'cover' | 'is_clipping' | 'content_mode'>;
export type AnalyticsChannel = { id: number; name: string; platform: string; projects: number[] };
export type PublicationTimeline = {
    key: string;
    platform: string;
    title: string;
    projectId: number | null;
    channelName: string | null;
    url: string | null;
    change: number | null;
    watchMinutesChange: number | null;
    snapshots: Record<string, number>;
    intervals: { from: string; to: string; change: number }[];
    latest: Record<string, number | undefined>;
};
export type MetricsReport = {
    from: string;
    to: string;
    updatedAt: string | null;
    filters: AnalyticsFilters;
    change: number | null;
    summaries: Record<Counter, number | null>;
    totals: Record<Counter, number | null>;
    daily: Record<string, number | null>;
    production: {
        projects: number;
        openEpisodes: number;
        latestGeneration: string | null;
        generationStage: string | null;
        latestPublication: string | null;
    } | null;
    publications: PublicationTimeline[];
    projects: AnalyticsProject[];
    channels: AnalyticsChannel[];
    coverage: { id: number; name: string; status: string; state: string | null; platform: string; last_success_at: string | null }[];
};
export const counterNames: Record<Counter, string> = { views: 'Просмотры', likes: 'Лайки', shares: 'Репосты', comments: 'Комментарии' };
export const platformNames: Record<string, string> = { youtube: 'YouTube', instagram: 'Instagram', tiktok: 'TikTok', vk: 'VK' };
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
