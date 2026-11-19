import type { CharacterRevision, SeriesSummary } from '@/types/series';

export interface EpisodeMedia {
    id: number;
    collection: string;
    mime_type: string;
    url: string;
}
export interface GenerationOperation {
    id: number;
    stage: string;
    status: string;
    status_label: string;
    error_code: string | null;
    cancel_requested_at: string | null;
}
export interface ShotTake {
    id: number;
    shot_id: number;
    take_number: number;
    generated: boolean;
    media: EpisodeMedia[];
    operations: GenerationOperation[];
}
export interface ShotSummary {
    id: number;
    scene_id: number;
    position: number;
    duration_ms: number;
    action: string;
    transition_mode: string;
    source_prompt_id: number | null;
    source_assembly_id: number | null;
    takes: ShotTake[];
}
export interface SceneSummary {
    id: number;
    position: number;
    title: string;
    is_closed: boolean;
    shots: ShotSummary[];
}
export interface EpisodeRevisionSummary {
    id: number;
    number: number;
    title: string;
    objective: string;
    status: string;
    source_assembly_id: number | null;
    base_continuity_version: number;
    context: {
        specification_version: number | null;
        confirmed_events: string | null;
        character_state: string | null;
        open_threads: string | null;
        scene_closed: boolean | null;
    };
    scenes: SceneSummary[];
}
export interface EpisodeAssemblySummary {
    id: number;
    episode_revision_id: number;
    number: number;
    status: string;
    lock_version: number;
    duration_ms: number | null;
    error_message: string | null;
    continuity_snapshot: { confirmed_events?: string; character_state?: string; open_threads?: string; scene_closed?: boolean };
    media: EpisodeMedia[];
    accepted_at: string | null;
}
export interface PublicationSummary {
    id: number;
    episode_assembly_id: number;
    channel_id: number;
    channel_name: string | null;
    status: string;
    error_message: string | null;
    scheduled_at: string | null;
    published_at: string | null;
}
export interface EpisodeSummary {
    id: number;
    series_id: number;
    number: number;
    title: string;
    lock_version: number;
    validity: string;
    current_revision_id: number | null;
    accepted_assembly_id: number | null;
    current_revision: EpisodeRevisionSummary | null;
    accepted_assembly: EpisodeAssemblySummary | null;
    script_generation_status: string | null;
    script_generation_error: string | null;
}
export interface ScreenplayShot {
    duration_ms: number;
    action: string;
    camera: string;
    transition_mode: string;
    dialogue: { character_id: number; text: string }[];
    source_prompt_id?: number | null;
    source_assembly_id?: number | null;
    source_time_ms?: number;
}
export interface Screenplay {
    public_caption?: string | null;
    title: string;
    objective: string;
    character_revision_ids: number[];
    scenes: ScreenplayScene[];
}

export interface ScreenplayScene {
    title: string;
    location: string;
    time: string;
    objective: string;
    initial_state: string;
    expected_final_state: string;
    is_closed: boolean;
    shots: ScreenplayShot[];
}

export interface ContinuationSource {
    kind: 'take' | 'assembly';
    id: number;
    label: string;
    scene_position: number;
    shot_position: number;
}

export interface EpisodeWorkspaceProps {
    project: { id: number; name: string; is_active: boolean };
    series: SeriesSummary;
    episode: EpisodeSummary;
    characters: (CharacterRevision & { character_name?: string })[];
    assemblies: EpisodeAssemblySummary[];
    publications: PublicationSummary[];
    channels: { id: number; name: string; type: string; type_label: string }[];
    screenplay: Screenplay | null;
    requestKey: string;
    transitionModes: Record<string, string>;
    sourceAssembly: EpisodeAssemblySummary | null;
}
export interface EpisodeRouteParams {
    series: number;
    episode: number;
}
