import type { SharedData } from '@/types';
import type { EpisodeWorkspaceProps } from '@/types/episodes';
import { useForm, usePage } from '@inertiajs/vue3';
import { computed, onMounted, watch } from 'vue';
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
    const ownerId = usePage<SharedData>().props.auth.user.id;
    const draftKey = computed(() => `poster:montage:${ownerId}:${props.episode.id}:${revision.value?.id}`);
    function clearMontageDraft() {
        try {
            sessionStorage.removeItem(draftKey.value);
        } catch {
            // Browsers can disable storage.
        }
    }
    onMounted(() => {
        try {
            const draft = JSON.parse(sessionStorage.getItem(draftKey.value) ?? 'null');
            if (
                Array.isArray(draft) &&
                draft.length === shots.value.length &&
                draft.every(
                    (item, i) =>
                        item &&
                        Number.isFinite(item.trim_start_ms) &&
                        Number.isFinite(item.trim_end_ms) &&
                        (item.prompt_id === 0 || shots.value[i].takes.some((take) => take.generated && take.id === item.prompt_id)),
                )
            ) {
                assemblyForm.shots = draft;
            }
            watch(
                () => assemblyForm.shots,
                (items) => {
                    try {
                        sessionStorage.setItem(draftKey.value, JSON.stringify(items));
                    } catch {
                        // Editing remains available without storage.
                    }
                },
                { deep: true, flush: 'sync' },
            );
        } catch {
            clearMontageDraft();
        }
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
                onSuccess: clearMontageDraft,
            });
    }
    return { assemblyForm, assemblyErrors, shots, buildAssembly };
}
