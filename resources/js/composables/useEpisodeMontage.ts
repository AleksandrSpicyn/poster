import type { SharedData } from '@/types';
import type { EpisodeWorkspaceProps } from '@/types/episodes';
import { useForm, usePage } from '@inertiajs/vue3';
import { computed, onMounted, ref, watch } from 'vue';
import { useRoute } from 'ziggy-js';

type Fragment = { prompt_id: number; trim_start_ms: number; trim_end_ms: number };
const copy = (items: Fragment[]) => items.map((item) => ({ ...item }));

export function useEpisodeMontage(props: EpisodeWorkspaceProps) {
    const route = useRoute();
    const params = { series: props.series.id, episode: props.episode.id };
    const revision = computed(() => props.episode.current_revision);
    const shots = computed(() => revision.value?.scenes.flatMap((scene) => scene.shots) ?? []);
    const readyTakes = computed(() =>
        shots.value.map((shot) => shot.takes.filter((take) => take.generated && take.media.some((media) => media.collection === 'shot-video'))),
    );
    const latestAssemblyId = computed(
        () => props.assemblies.find((assembly) => assembly.episode_revision_id === revision.value?.id && assembly.status !== 'failed')?.id ?? 0,
    );
    const savedAssembly = computed(() =>
        props.assemblies.find(
            (assembly) => assembly.episode_revision_id === revision.value?.id && assembly.status !== 'failed' && !!assembly.selection?.length,
        ),
    );
    const serverSelection = computed(() =>
        shots.value.map((shot, index) => {
            const saved = savedAssembly.value?.selection?.find((item) => item.shot_id === shot.id);
            return {
                prompt_id: saved?.prompt_id ?? readyTakes.value[index][0]?.id ?? 0,
                trim_start_ms: saved?.trim_start_ms ?? 0,
                trim_end_ms: saved?.trim_end_ms ?? shot.duration_ms,
            };
        }),
    );
    const assemblyForm = useForm({ shots: copy(serverSelection.value) });
    const ownerId = usePage<SharedData>().props.auth.user.id;
    const draftKey = computed(() => `poster:montage:${ownerId}:${props.episode.id}:${revision.value?.id}`);
    const base = ref(copy(serverSelection.value));
    const restored = ref(false);
    const saveError = ref('');
    const stored = ref(false);
    const dirty = computed(() => JSON.stringify(base.value) !== JSON.stringify(assemblyForm.shots));
    const stale = computed(() => dirty.value && JSON.stringify(base.value) !== JSON.stringify(serverSelection.value));
    const difference = computed(() => {
        for (const [index, local] of assemblyForm.shots.entries()) {
            const server = serverSelection.value[index],
                original = base.value[index];
            if (!server || !original) continue;
            const fields = { trim_end_ms: 'Конец фрагмента, мс', trim_start_ms: 'Начало фрагмента, мс', prompt_id: 'Дубль' };
            for (const key of Object.keys(fields) as (keyof Fragment)[]) {
                if (local[key] !== server[key] && (local[key] !== original[key] || server[key] !== original[key]))
                    return { label: fields[key], local: local[key], server: server[key], index };
            }
        }
        return null;
    });
    function clearMontageDraft() {
        restored.value = false;
        saveError.value = '';
        stored.value = false;
        try {
            sessionStorage.removeItem(draftKey.value);
        } catch {
            /* Storage is optional. */
        }
    }
    function resetSelection() {
        base.value = copy(serverSelection.value);
        assemblyForm.shots = copy(base.value);
        assemblyForm.clearErrors();
        clearMontageDraft();
    }
    function keepSelection() {
        const edits = copy(assemblyForm.shots),
            previous = base.value;
        base.value = copy(serverSelection.value);
        assemblyForm.shots = base.value.map((current, index) => {
            const local = edits[index],
                saved = previous[index];
            if (!local || !saved) return { ...current };
            return {
                prompt_id: local.prompt_id !== saved.prompt_id ? local.prompt_id : current.prompt_id,
                trim_start_ms: local.trim_start_ms !== saved.trim_start_ms ? local.trim_start_ms : current.trim_start_ms,
                trim_end_ms: local.trim_end_ms !== saved.trim_end_ms ? local.trim_end_ms : current.trim_end_ms,
            };
        });
        restored.value = true;
    }
    onMounted(() => {
        try {
            const draft = JSON.parse(sessionStorage.getItem(draftKey.value) ?? 'null');
            const items = Array.isArray(draft) ? draft : draft?.shots;
            const matches = (items: unknown): items is Fragment[] =>
                Array.isArray(items) &&
                items.length === shots.value.length &&
                items.every(
                    (item, i) =>
                        item &&
                        Number.isFinite(item.trim_start_ms) &&
                        Number.isFinite(item.trim_end_ms) &&
                        (item.prompt_id === 0 || shots.value[i].takes.some((take) => take.generated && take.id === item.prompt_id)),
                );
            if (matches(items)) {
                if (matches(draft.base)) base.value = copy(draft.base);
                assemblyForm.shots = copy(items);
                restored.value = dirty.value;
                stored.value = dirty.value;
            }
        } catch {
            clearMontageDraft();
        }
        watch(
            () => assemblyForm.shots,
            (items) => {
                if (!dirty.value) {
                    clearMontageDraft();
                    return;
                }
                try {
                    sessionStorage.setItem(draftKey.value, JSON.stringify({ base: base.value, shots: items }));
                    stored.value = true;
                } catch {
                    stored.value = false;
                }
            },
            { deep: true, flush: 'sync' },
        );
    });
    watch(() => props.episode.current_revision_id, resetSelection);
    watch(serverSelection, () => {
        if (!dirty.value) resetSelection();
    });
    const assemblyErrors = computed(() => Object.values(assemblyForm.errors).join(' '));
    const canBuildAssembly = computed(
        () =>
            !stale.value &&
            assemblyForm.shots.length > 0 &&
            assemblyForm.shots.every(
                (item, index) =>
                    shots.value[index]?.takes.some((take) => take.id === item.prompt_id && take.generated) &&
                    Number.isInteger(item.trim_start_ms) &&
                    Number.isInteger(item.trim_end_ms) &&
                    item.trim_start_ms >= 0 &&
                    item.trim_end_ms > item.trim_start_ms &&
                    item.trim_end_ms <= shots.value[index].duration_ms,
            ),
    );
    function buildAssembly() {
        if (!revision.value || !canBuildAssembly.value || assemblyForm.processing) return;
        saveError.value = '';
        const failed = () => {
            saveError.value = stored.value
                ? 'Сервер недоступен. В этой вкладке сохранилась локальная копия.'
                : 'Не удалось сохранить монтаж. Оставьте вкладку открытой и повторите отправку.';
            return false as const;
        };
        assemblyForm
            .transform((data) => ({ ...data, base_assembly_id: latestAssemblyId.value }))
            .post(route('episodes.assemblies.store', { ...params, revision: revision.value.id }), {
                preserveScroll: true,
                preserveState: 'errors',
                onSuccess: clearMontageDraft,
                onNetworkError: failed,
                onHttpException: (response) => (response.status === 419 ? undefined : failed()),
            });
    }
    return {
        readyTakes,
        assemblyForm,
        assemblyErrors,
        shots,
        buildAssembly,
        canBuildAssembly,
        restored,
        saveError,
        stale,
        difference,
        savedAssembly,
        resetSelection,
        keepSelection,
    };
}
