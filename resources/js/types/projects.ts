import type { ChannelType, SharedData } from '@/types';
import type { PaginatedResource, PromptSummary } from '@/types/content';

export interface ProjectSummary {
    id: number;
    name: string;
    prompt: string;
    prompts_count: number;
    ready_prompts_count: number;
    channels_count: number;
    can_delete: boolean;
    created_at: string | null;
    updated_at: string | null;
}

export interface ProjectChannel {
    id: number;
    name: string;
    type: ChannelType;
    type_label: string;
    posts_count: number;
}

export interface ProjectsPageProps extends SharedData {
    projects: PaginatedResource<ProjectSummary>;
    filters: { search: string; sort: string };
    sortDirections: Record<string, string>;
}

export interface ProjectPageProps extends SharedData {
    project: ProjectSummary & { channels: ProjectChannel[] };
    prompts: PaginatedResource<PromptSummary>;
    filters: { sort: string };
    sortDirections: Record<string, string>;
}

export interface ProjectFormProps {
    project: Pick<ProjectSummary, 'id' | 'name' | 'prompt'> | null;
    limits: { name: number; prompt: number };
}
