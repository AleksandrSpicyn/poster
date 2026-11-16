import type { EpisodeWorkspaceProps } from '@/types/episodes';
import { useForm } from '@inertiajs/vue3';
import { computed, watch } from 'vue';
import { useRoute } from 'ziggy-js';

export function useEpisodeMontage(props: EpisodeWorkspaceProps) {
    const route = useRoute();
    const params = { series: props.series.id, episode: props.episode.id };
    const revision = computed(() => props.episode.current_revision);
    const shots = computed(() => revision.value?.scenes.flatMap((scene) => scene.shots) ?? []);
    const assemblyForm = useForm({
        shots: shots.value.map((shot) => ({
            prompt_id: shot.takes.find((take) => take.generated)?.id ?? 0,
            trim_start_ms: 0,
            trim_end_ms: shot.duration_ms,
        })),
    });
    const assemblyErrors = computed(() => Object.values(assemblyForm.errors).join(' '));
    watch(
        () => props.episode.current_revision_id,
        () => {
            assemblyForm.shots = shots.value.map((shot) => ({ prompt_id: 0, trim_start_ms: 0, trim_end_ms: shot.duration_ms }));
        },
    );
    function buildAssembly() {
        if (revision.value)
            assemblyForm.post(route('episodes.assemblies.store', { ...params, revision: revision.value.id }), {
                preserveScroll: true,
                preserveState: 'errors',
            });
    }
    return { assemblyForm, assemblyErrors, shots, buildAssembly };
}
