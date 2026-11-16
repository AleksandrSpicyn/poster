import type { ChannelType, SharedData } from '@/types';
import type { ManagedChannel } from '@/types/channels';
import type { PaginatedResource, PromptSummary } from '@/types/content';
import type { ProductionRun } from '@/types/production';
import type { CharacterSummary, EpisodeSummary, SeriesSummary } from '@/types/series';

export interface ProjectSummary {
    id: number;
    name: string;
    prompt: string;
    content_mode: string;
    content_mode_label: string;
    is_active: boolean;
    latest_production?: ProductionRun | null;
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
    series: SeriesSummary | null;
    characters: PaginatedResource<CharacterSummary> | null;
    episodes: EpisodeSummary[];
}

export interface ProjectFormProps extends SharedData {
    project: Pick<ProjectSummary, 'id' | 'name' | 'prompt' | 'content_mode' | 'is_active'> | null;
    contentModes: Record<string, string>;
    series: SeriesSummary | null;
    channels: ManagedChannel[];
    selectedChannelIds: number[];
    audiences: Record<string, string>;
    audioModes: Record<string, string>;
    defaultAudioMode: string;
    limits: { name: number; prompt: number };
}
