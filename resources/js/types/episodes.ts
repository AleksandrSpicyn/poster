import type { ChannelType } from '@/types';
import type { ProjectSummary } from '@/types/projects';
import type { CharacterRevision, SeriesSummary } from '@/types/series';

interface EpisodeMedia {
    id: number;
    collection: string;
    mime_type: string;
    url: string;
}
interface GenerationOperation {
    stage_label: string;
    approved_frame_media_id?: number | null;
    can_retry_download: boolean;
    retrying_download: boolean;
    id: number;
    stage: string;
    status: string;
    status_label: string;
    error_code: string | null;
    cancel_requested_at: string | null;
}
export interface ShotTake {
    inputs?: { images: number[]; speakers: { name: string; sample: number | null }[]; prompt: string; duration: number; model: string };
    id: number;
    shot_id: number;
    take_number: number;
    generated: boolean;
    media: EpisodeMedia[];
    operations: GenerationOperation[];
}
export interface ShotSummary {
    title?: string | null;
    plan_type?: string | null;
    transition_mode_label: string;
    id: number;
    scene_id: number;
    position: number;
    duration_ms: number;
    action: string;
    camera?: { description?: string };
    transition_mode: string;
    source_prompt_id: number | null;
    source_assembly_id: number | null;
    takes: ShotTake[];
}
interface SceneSummary {
    id: number;
    position: number;
    title: string;
    is_closed: boolean;
    shots: ShotSummary[];
}
export interface EpisodeRevisionSummary {
    created_at: string | null;
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
    requires_audio_review?: boolean;
    id: number;
    episode_revision_id: number;
    number: number;
    status: string;
    lock_version: number;
    duration_ms: number | null;
    selection?: { shot_id?: number; prompt_id?: number; trim_start_ms?: number; trim_end_ms?: number }[];
    error_message: string | null;
    continuity_snapshot: { confirmed_events?: string; character_state?: string; open_threads?: string; scene_closed?: boolean };
    media: EpisodeMedia[];
    accepted_at: string | null;
}
export interface PublicationSummary {
    platform?: ChannelType | null;
    url?: string | null;
    id: number;
    episode_assembly_id: number;
    channel_id: number;
    can_verify: boolean;
    channel_name: string | null;
    status: string;
    error_message: string | null;
    created_at: string | null;
    scheduled_at: string | null;
    published_at: string | null;
}
interface EpisodeSummary {
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
export interface ShotCue {
    kind: 'sound' | 'text' | 'insert';
    text: string;
    start_ms: number;
    end_ms: number;
    asset_id?: number | null;
}
export interface ShotDirection {
    notes?: string | null;
    visible_character_ids: number[] | null;
    reference_ids: number[];
    review_frame: boolean;
    cues: ShotCue[];
}
export interface ScreenplayShot {
    direction?: ShotDirection | null;
    title?: string | null;
    plan_type?: string | null;
    duration_ms: number;
    action: string;
    camera: string;
    transition_mode: string;
    dialogue: { character_id: number; text: string; start_ms?: number | null; end_ms?: number | null }[];
    source_prompt_id?: number | null;
    source_assembly_id?: number | null;
    source_time_ms?: number;
}
export interface Screenplay {
    target_duration_seconds?: number | null;
    scene_notes?: string | null;
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
    project: Pick<ProjectSummary, 'id' | 'name' | 'is_active' | 'cover'>;
    series: SeriesSummary;
    episode: EpisodeSummary;
    characters: (CharacterRevision & { character_name?: string })[];
    assemblies: EpisodeAssemblySummary[];
    publications: PublicationSummary[];
    facebookFileError?: string | null;
    channels: { facebook_page?: string | null; id: number; name: string; type: ChannelType; type_label: string }[];
    screenplay: Screenplay | null;
    requestKey: string;
    transitionModes: Record<string, string>;
    sourceAssembly: EpisodeAssemblySummary | null;
    memoryAssembly: EpisodeAssemblySummary | null;
    predecessorNumber: number | null;
}
export interface EpisodeRouteParams {
    series: number;
    episode: number;
}
