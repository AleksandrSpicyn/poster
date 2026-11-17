export interface ProductionRun {
    id: number;
    project_id: number;
    series_id: number | null;
    episode_id: number | null;
    series_assembly_id: number | null;
    prompt_id: number | null;
    status: string;
    status_label: string;
    stage: string;
    stage_label: string;
    stages: Record<string, string>;
    is_active: boolean;
    blocks_next_run: boolean;
    message: string | null;
    updated_at: string;
}
