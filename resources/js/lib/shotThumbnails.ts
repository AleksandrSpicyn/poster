import type { EpisodeRevisionSummary, Screenplay, ScreenplayShot } from '@/types/episodes';

function shotSignature(shot: ScreenplayShot) {
    return JSON.stringify([
        shot.title ?? '',
        shot.plan_type ?? '',
        shot.action,
        shot.camera,
        shot.duration_ms,
        shot.transition_mode,
        shot.dialogue.map((line) => [line.character_id, line.text]),
        shot.source_prompt_id ?? null,
        shot.source_assembly_id ?? null,
        shot.source_time_ms ?? 0,
    ]);
}
export function createShotThumbnailLookup(screenplay: Screenplay | null, revision: EpisodeRevisionSummary | null) {
    const bySignature = new Map<string, string | undefined>();
    screenplay?.scenes.forEach((scene, sceneIndex) => {
        scene.shots.forEach((shot, shotIndex) => {
            const key = shotSignature(shot);
            const media = revision?.scenes[sceneIndex]?.shots[shotIndex]?.takes.flatMap((take) => take.media);
            bySignature.set(key, bySignature.has(key) ? undefined : media?.find((item) => item.collection === 'shot-image')?.url);
        });
    });
    return (shot: ScreenplayShot) => bySignature.get(shotSignature(shot));
}
