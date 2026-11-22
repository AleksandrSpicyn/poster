import type { ContinuationSource, ScreenplayScene, ScreenplayShot } from '@/types/episodes';

export function createScreenplayShot(overrides: Partial<ScreenplayShot> = {}): ScreenplayShot {
    return {
        title: '',
        plan_type: '',
        duration_ms: 15000,
        action: '',
        camera: '',
        transition_mode: 'new_scene',
        dialogue: [],
        source_prompt_id: null,
        source_assembly_id: null,
        source_time_ms: 0,
        ...overrides,
    };
}

export function createScreenplayScene(overrides: Partial<ScreenplayScene> = {}): ScreenplayScene {
    return {
        title: '',
        location: '',
        time: '',
        objective: '',
        initial_state: '',
        expected_final_state: '',
        is_closed: false,
        shots: [createScreenplayShot()],
        ...overrides,
    };
}

export function sourcePrecedes(source: ContinuationSource, scenePosition: number, shotPosition: number): boolean {
    return (
        source.kind === 'assembly' ||
        source.scene_position < scenePosition ||
        (source.scene_position === scenePosition && source.shot_position < shotPosition)
    );
}
