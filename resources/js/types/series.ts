import type { ProductionRun } from '@/types/production';
import type { ProjectSummary } from '@/types/projects';

export interface SeriesSpecification {
    premise: string;
    visual_style: string;
    language: string;
    audience: string;
    episode_duration_seconds: number;
    shot_duration_seconds?: number;
    narrative_rules: string;
    audio_mode: string;
    story_outline?: string;
    cycle_structure?: string;
    scene_mode?: string;
}

export interface SeriesSummary {
    id: number;
    project_id: number;
    name: string;
    specification: SeriesSpecification;
    specification_version: number;
    continuity_version: number;
    lock_version: number;
    automation_enabled: boolean;
    output_mode: string;
    cycle_plan?: { objective: string; parts: { number: number; objective: string; expected_result: string }[] } | null;
    cycle_paused?: boolean;
    episode_limit: number | null;
    latest_production?: ProductionRun | null;
    archived_at: string | null;
    characters_count?: number;
    episodes_count?: number;
    accepted_episodes_count?: number;
    project?: Pick<ProjectSummary, 'id' | 'name' | 'prompt' | 'cover'>;
}

export interface CharacterReference {
    file_name: string | null;
    id: number;
    label: string;
    view: string;
    checksum: string | null;
    url: string;
}

export interface CharacterRevision {
    title?: string | null;
    id: number;
    character_id: number;
    number: number;
    status: string;
    description: string;
    appearance: Record<string, unknown>;
    voice: Record<string, unknown>;
    lock_version: number;
    approved_at: string | null;
    references?: CharacterReference[];
}

export interface CharacterSummary {
    story_role?: string | null;
    id: number;
    series_id: number;
    name: string;
    current_revision_id: number | null;
    lock_version: number;
    archived_at: string | null;
    current_revision?: CharacterRevision | null;
}

export interface EpisodeSummary {
    objective?: string | null;
    current_revision_id: number | null;
    accepted_assembly_id: number | null;
    script_generation_status: string | null;
    id: number;
    series_id: number;
    number: number;
    title: string;
    validity: string;
}

export interface CharacterRouteParams {
    project: number;
    series: number;
    character: number;
}

export interface AssemblyParameters {
    parts: { number: number; title: string | null; version: number | null; ready: boolean }[];
    can_build: boolean;
}
