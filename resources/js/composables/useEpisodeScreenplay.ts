import { createScreenplayScene, createScreenplayShot } from '@/lib/screenplay';
import type { SharedData } from '@/types';
import type { EpisodeWorkspaceProps, Screenplay } from '@/types/episodes';
import { useForm, usePage } from '@inertiajs/vue3';
import { computed, onMounted, ref, watch } from 'vue';
import { useRoute } from 'ziggy-js';

export function useEpisodeScreenplay(props: EpisodeWorkspaceProps) {
    const route = useRoute();
    const params = { series: props.series.id, episode: props.episode.id };
    const revision = computed(() => props.episode.current_revision);
    const initialScript: Screenplay = props.screenplay ?? {
        title: props.episode.title,
        objective: '',
        character_revision_ids: props.characters.length ? [props.characters[0].id] : [],
        scenes: [createScreenplayScene({ shots: [createScreenplayShot({ camera: 'Medium shot' })] })],
    };
    const initialScriptJson = JSON.stringify(initialScript, null, 2);
    const scriptText = ref(initialScriptJson);
    const jsonError = ref('');
    const scriptForm = useForm({ script: initialScript, lock_version: props.episode.lock_version });
    const generateForm = useForm({ brief: '', lock_version: props.episode.lock_version, request_key: props.requestKey });
    const approveForm = useForm({});
    const scriptErrors = computed(() => Object.values(scriptForm.errors).join(' '));
    const editorErrors = computed<Record<string, string | undefined>>(() => scriptForm.errors);
    const generateErrors = computed(() => Object.values(generateForm.errors).join(' '));
    const approveErrors = computed(() => Object.values(approveForm.errors).join(' '));
    const editorStale = computed(() => scriptForm.lock_version !== props.episode.lock_version);
    const hasDraft = computed(
        () => scriptForm.isDirty || Boolean(generateForm.brief) || scriptText.value !== JSON.stringify(scriptForm.script, null, 2),
    );
    const draftKey = `poster:episode:${usePage<SharedData>().props.auth.user.id}:${props.episode.id}`;
    function clearDraft() {
        try {
            sessionStorage.removeItem(draftKey);
        } catch {
            // The editor remains available when the browser disables storage.
        }
    }
    onMounted(() => {
        try {
            const draft = JSON.parse(sessionStorage.getItem(draftKey) ?? 'null');
            if (
                Number.isInteger(draft?.version) &&
                Array.isArray(draft.script?.scenes) &&
                typeof draft.text === 'string' &&
                typeof draft.brief === 'string'
            ) {
                scriptForm.lock_version = draft.version;
                generateForm.lock_version = draft.version;
                scriptForm.script = draft.script;
                scriptText.value = draft.text;
                generateForm.brief = draft.brief;
            } else {
                clearDraft();
            }
            watch(
                () => [scriptForm.script, scriptText.value, generateForm.brief],
                () => {
                    try {
                        sessionStorage.setItem(
                            draftKey,
                            JSON.stringify({
                                version: scriptForm.lock_version,
                                script: scriptForm.script,
                                text: scriptText.value,
                                brief: generateForm.brief,
                            }),
                        );
                    } catch {
                        // Storage can also fill up while editing.
                    }
                },
                { deep: true, flush: 'sync' },
            );
        } catch {
            clearDraft();
        }
    });
    watch(
        () => props.screenplay,
        (screenplay) => {
            if (!screenplay || hasDraft.value || scriptForm.processing) return;
            const script = JSON.parse(JSON.stringify(screenplay)) as Screenplay;
            scriptForm.defaults({ script, lock_version: props.episode.lock_version });
            scriptForm.reset();
            scriptText.value = JSON.stringify(script, null, 2);
            generateForm.lock_version = props.episode.lock_version;
            clearDraft();
        },
    );
    function saveScript() {
        scriptForm.transform((data) => data).post(route('episodes.scripts.store', params), { preserveState: 'errors', onSuccess: clearDraft });
    }
    function importScript() {
        jsonError.value = '';
        let parsed: unknown;
        try {
            parsed = JSON.parse(scriptText.value);
            if (typeof parsed !== 'object' || parsed === null || Array.isArray(parsed)) throw new Error('Screenplay must be an object.');
        } catch {
            jsonError.value = 'Введите сценарий в формате JSON.';
            return;
        }
        scriptForm
            .transform((data) => ({ ...data, script: parsed as Screenplay }))
            .post(route('episodes.scripts.store', params), {
                preserveState: 'errors',
                onSuccess: clearDraft,
                onFinish: () => {
                    scriptForm.transform((data) => data);
                },
            });
    }
    function generateScript() {
        generateForm.post(route('episodes.scripts.generate', params), { preserveState: 'errors', onSuccess: clearDraft });
    }
    function approveScript() {
        if (revision.value)
            approveForm.post(route('episodes.scripts.approve', { ...params, revision: revision.value.id }), {
                preserveScroll: true,
                preserveState: 'errors',
            });
    }
    return {
        scriptForm,
        generateForm,
        approveForm,
        scriptText,
        jsonError,
        scriptErrors,
        editorErrors,
        generateErrors,
        approveErrors,
        editorStale,
        clearDraft,
        hasDraft,
        saveScript,
        importScript,
        generateScript,
        approveScript,
    };
}
