import { createScreenplayScene, createScreenplayShot } from '@/lib/screenplay';
import type { EpisodeWorkspaceProps, Screenplay } from '@/types/episodes';
import { useForm } from '@inertiajs/vue3';
import { computed, ref, watch } from 'vue';
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
    watch(
        () => props.screenplay,
        (screenplay) => {
            if (!screenplay || scriptForm.isDirty || scriptForm.processing || scriptText.value !== JSON.stringify(scriptForm.script, null, 2)) return;
            const script = JSON.parse(JSON.stringify(screenplay)) as Screenplay;
            scriptForm.defaults({ script, lock_version: props.episode.lock_version });
            scriptForm.reset();
            scriptText.value = JSON.stringify(script, null, 2);
        },
    );
    function saveScript() {
        scriptForm.transform((data) => data).post(route('episodes.scripts.store', params), { preserveState: 'errors' });
    }
    function importScript() {
        jsonError.value = '';
        let parsed: unknown;
        try {
            parsed = JSON.parse(scriptText.value);
            if (typeof parsed !== 'object' || parsed === null || Array.isArray(parsed)) throw new Error('Screenplay must be an object.');
        } catch {
            jsonError.value = 'Enter a valid screenplay JSON object before importing.';
            return;
        }
        scriptForm
            .transform((data) => ({ ...data, script: parsed as Screenplay }))
            .post(route('episodes.scripts.store', params), {
                preserveState: 'errors',
                onFinish: () => {
                    scriptForm.transform((data) => data);
                },
            });
    }
    function generateScript() {
        generateForm.post(route('episodes.scripts.generate', params), { preserveState: 'errors' });
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
        saveScript,
        importScript,
        generateScript,
        approveScript,
    };
}
