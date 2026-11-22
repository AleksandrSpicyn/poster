import type { ChannelType, SharedData } from '@/types';
import type { ManagedChannel } from '@/types/channels';
import type { PaginatedResource, PromptSummary } from '@/types/content';
import type { ProductionRun } from '@/types/production';
import type { CharacterSummary, EpisodeSummary, SeriesSummary } from '@/types/series';

export interface ProjectSummary {
    settings_version: number;
    id: number;
    name: string;
    prompt: string;
    cover: { id: number; url: string; x: number; y: number; source: 'upload' | 'frame' } | null;
    is_clipping: boolean;
    content_mode: string;
    content_mode_label: string;
    is_active: boolean;
    archived_at: string | null;
    latest_production?: ProductionRun | null;
    prompts_count: number;
    ready_prompts_count: number;
    channels_count: number;
    can_delete: boolean;
    created_at: string | null;
    updated_at: string | null;
}

export interface ProjectChannel {
    can_receive_production: boolean;
    id: number;
    name: string;
    type: ChannelType;
    type_label: string;
    posts_count: number;
}

export interface ProjectsPageProps extends SharedData {
    projects: PaginatedResource<ProjectSummary>;
    information: { project: ProjectSummary & { channels: ProjectChannel[] }; owner: string; episodes: number; accepted: number } | null;
    filters: { search: string; sort: string; active?: boolean; archived?: boolean; updated?: boolean; clipping?: boolean; alphabetical?: boolean };
    sortDirections: Record<string, string>;
}

export interface ProjectPageProps extends SharedData {
    clipWeek: { from: string; to: string; observedOn: string | null; clips: { title: string; views: number }[] } | null;
    gamingTrends: {
        game: string;
        entries: { title: string; channel: string; views: string; views_per_day?: number | null; url: string }[];
        checkedAt: string;
    }[];
    opusClips: PaginatedResource<{
        id: number;
        clip_id: string;
        source: string;
        source_url: string;
        game: string | null;
        title: string | null;
        duration_ms: number | null;
        score: number | null;
        publications: { media_id: string | null; platform: string; status: string | null; url: string | null; publish_at: string | null }[];
        observations: { platform: string; media_id: string; date: string; metrics: Record<string, number> }[];
    }> | null;
    project: ProjectSummary & { channels: ProjectChannel[] };
    prompts: PaginatedResource<PromptSummary>;
    filters: { sort: string; updated?: boolean; active?: boolean };
    sortDirections: Record<string, string>;
    series: SeriesSummary | null;
    characters: PaginatedResource<CharacterSummary> | null;
    episodes: EpisodeSummary[];
}

export interface ProjectFormProps extends SharedData {
    project:
        | (Pick<ProjectSummary, 'id' | 'name' | 'prompt' | 'content_mode' | 'is_active' | 'settings_version' | 'updated_at' | 'can_delete'> & {
              blocking_runs_count: number;
          })
        | null;
    contentModes: Record<string, string>;
    series: SeriesSummary | null;
    channels: ManagedChannel[];
    selectedChannelIds: number[];
    audiences: Record<string, string>;
    audioModes: Record<string, string>;
    defaultAudioMode: string;
    outputModes: Record<string, string>;
    cycleStructures: Record<string, string>;
    sceneModes: Record<string, string>;
    limits: { name: number; prompt: number };
}
