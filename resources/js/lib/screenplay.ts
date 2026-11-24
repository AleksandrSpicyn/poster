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

export function parseScreenplayImport(text: string): { script: Record<string, unknown> | null; error: string } {
    let script: unknown;
    try {
        script = JSON.parse(text);
    } catch {
        return { script: null, error: 'Введите сценарий в формате JSON.' };
    }
    if (typeof script !== 'object' || script === null || Array.isArray(script)) return { script: null, error: 'Введите сценарий в формате JSON.' };
    if (!('scenes' in script) || !Array.isArray(script.scenes)) return { script: null, error: 'scenes · ожидается массив сцен.' };

    for (const [index, scene] of script.scenes.entries()) {
        const shots: unknown = scene?.shots;
        if (!Array.isArray(shots)) {
            const detail = typeof shots === 'string' ? ', получена строка.' : '.';
            return { script: null, error: `scenes[${index}].shots · ожидается массив кадров${detail}` };
        }
    }
    return { script, error: '' };
}
