import type { ChannelType, SharedData } from '@/types';

/** Wire contracts of PostResource and PromptResource (including nullable deleted relations). */
interface PostSummary {
    title: string;
    channel_name: string | null;
    id: number;
    channel: ChannelType | null;
    url: string | null;
    created_at: string | null;
}

export interface PromptSummary {
    id: number;
    title: string;
    url: string;
    project: string | null;
    generated: boolean;
    stage: string;
    stage_label: string;
    created_at: string | null;
}

interface ResourceCollection<T> {
    data: T[];
}

export interface PaginatedResource<T> extends ResourceCollection<T> {
    links: { first: string; last: string; prev: string | null; next: string | null };
    meta: {
        current_page: number;
        from: number | null;
        to: number | null;
        last_page: number;
        per_page: number;
        total: number;
    };
}

export interface ListFilters {
    sort: string;
    perPage: number;
    page: number;
    type?: string;
    search?: string;
    project?: number | null;
    days?: number;
    active?: number | null;
    archived?: number;
    clipping?: number;
}

interface ListPageProps extends SharedData {
    sortDirections: Record<string, string>;
    defaultSort: string;
    filters: { sort?: string | null; type?: string | null; project?: number | null };
}

export interface PostsPageProps extends ListPageProps {
    activity: PaginatedResource<{
        event_at: string | null;
        created_at: string;
        id: number;
        title: string;
        channel_id: number | null;
        channel_name: string | null;
        url: string | null;
        platform: ChannelType | null;
        status: string;
        kind: 'post' | 'request';
    }>;
    periodDays: number;
    search: string;
    verifiableChannels: number[];
    destinations: { id: number; name: string; inbox: boolean; direct: boolean }[];
    requests: {
        id: number;
        title: string;
        platform: string | null;
        can_verify: boolean;
        channel_name: string;
        status: string;
        error_message: string | null;
    }[];
    posts: PaginatedResource<PostSummary>;
    allTypes: string;
    types: Record<string, string>;
}

export interface PromptsPageProps extends ListPageProps {
    projects: { id: number; name: string }[];
    prompts: PaginatedResource<PromptSummary>;
}

export interface DashboardPageProps extends SharedData {
    stats: { prompts: number; pending: number; ready: number; posts: number };
    recentPrompts: ResourceCollection<PromptSummary>;
    recentPosts: ResourceCollection<PostSummary>;
}
