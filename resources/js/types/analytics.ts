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
    channel?: number | null;
};
type AnalyticsProject = Pick<ProjectSummary, 'id' | 'name' | 'cover' | 'is_clipping' | 'content_mode'>;
type AnalyticsChannel = { id: number; name: string; platform: string; projects: number[] };
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
    channelReport?: {
        observations: Record<string, number | null>;
        previousFrom: string;
        previousTo: string;
        currentChange: number | null;
        previousChange: number | null;
        previousObserved: boolean;
    } | null;
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
