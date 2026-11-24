import { sourcePrecedes } from '@/lib/screenplay';
import type { ContinuationSource, EpisodeWorkspaceProps } from '@/types/episodes';
import { computed } from 'vue';

export function useEpisodeSources(props: EpisodeWorkspaceProps) {
    const revision = computed(() => props.episode.current_revision);
    const continuationSources = computed<ContinuationSource[]>(() => [
        ...(props.sourceAssembly
            ? [
                  {
                      kind: 'assembly' as const,
                      id: props.sourceAssembly.id,
                      label: 'Завершение предыдущего принятого эпизода',
                      scene_position: 0,
                      shot_position: 0,
                  },
              ]
            : []),
        ...(revision.value?.scenes.flatMap((scene) =>
            scene.shots.flatMap((shot) =>
                shot.takes
                    .filter((take) => take.generated)
                    .map((take) => ({
                        kind: 'take' as const,
                        id: take.id,
                        label: scene.title + ' · кадр ' + shot.position + ' · дубль ' + take.take_number,
                        scene_position: scene.position,
                        shot_position: shot.position,
                    })),
            ),
        ) ?? []),
    ]);
    function readySourcesFor(scenePosition: number, shotPosition: number) {
        return continuationSources.value.filter((source) => source.kind === 'take' && sourcePrecedes(source, scenePosition, shotPosition));
    }
    return { continuationSources, readySourcesFor };
}
