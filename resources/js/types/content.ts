import type { ChannelType, SharedData } from '@/types';

/** Wire contracts of PostResource and PromptResource (including nullable deleted relations). */
export interface PostSummary {
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

export interface ResourceCollection<T> {
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
}

export interface ListPageProps extends SharedData {
    sortDirections: Record<string, string>;
    defaultSort: string;
    filters: { sort?: string | null; type?: string | null };
}

export interface PostsPageProps extends ListPageProps {
    posts: PaginatedResource<PostSummary>;
    allTypes: string;
    types: Record<string, string>;
}

export interface PromptsPageProps extends ListPageProps {
    prompts: PaginatedResource<PromptSummary>;
}

export interface DashboardPageProps extends SharedData {
    stats: { prompts: number; pending: number; ready: number; posts: number };
    recentPrompts: ResourceCollection<PromptSummary>;
    recentPosts: ResourceCollection<PostSummary>;
}
