import { useFormApi } from '@/lib/api/useFormApi';
import { createScreenplayScene, createScreenplayShot } from '@/lib/screenplay';
import type { SharedData } from '@/types';
import type { EpisodeWorkspaceProps, Screenplay } from '@/types/episodes';
import { useForm, usePage } from '@inertiajs/vue3';
import { useOnline } from '@vueuse/core';
import { computed, onMounted, ref, watch } from 'vue';

export function useEpisodeScreenplay(props: EpisodeWorkspaceProps) {
    const submitForm = useFormApi();

    const online = useOnline();
    const draftStored = ref(false);
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
    const restored = ref(false);
    const saveError = ref('');
    const sourceVersion = ref(revision.value?.number ?? 0);
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
    const changedFields = computed(() => {
        const saved: Screenplay = props.screenplay ?? JSON.parse(initialScriptJson);
        const current = scriptForm.script;
        const changes: string[] = [];
        if (generateForm.brief) changes.push('Задание на сценарий');
        if (current.title !== saved.title) changes.push('Название');
        if (current.objective !== saved.objective) changes.push('Цель эпизода');
        if (current.public_caption !== saved.public_caption) changes.push('Описание для публикации');
        if (JSON.stringify(current.character_revision_ids) !== JSON.stringify(saved.character_revision_ids)) changes.push('Состав персонажей');
        for (let i = 0; i < Math.max(current.scenes.length, saved.scenes.length); i++) {
            if (JSON.stringify(current.scenes[i]) !== JSON.stringify(saved.scenes[i])) changes.push('Сцена ' + (i + 1));
        }
        if (scriptText.value !== initialScriptJson && scriptText.value !== JSON.stringify(current, null, 2)) changes.push('Текст для импорта');
        return changes;
    });
    const draftKey = `poster:episode:${usePage<SharedData>().props.auth.user.id}:${props.episode.id}`;
    function clearDraft() {
        draftStored.value = false;
        restored.value = false;
        saveError.value = '';
        try {
            sessionStorage.removeItem(draftKey);
        } catch {
            // The editor remains available when the browser disables storage.
        }
    }
    function persistDraft() {
        try {
            sessionStorage.setItem(
                draftKey,
                JSON.stringify({
                    version: scriptForm.lock_version,
                    source: sourceVersion.value,
                    script: scriptForm.script,
                    text: scriptText.value,
                    brief: generateForm.brief,
                }),
            );
            draftStored.value = true;
        } catch {
            draftStored.value = false;
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
                draftStored.value = true;
                restored.value = true;
                if (Number.isInteger(draft.source)) sourceVersion.value = draft.source;
                scriptForm.lock_version = draft.version;
                generateForm.lock_version = draft.version;
                scriptForm.script = draft.script;
                scriptText.value = draft.text;
                generateForm.brief = draft.brief;
            } else {
                clearDraft();
            }
            watch(() => [scriptForm.script, scriptText.value, generateForm.brief], persistDraft, { deep: true, flush: 'sync' });
        } catch {
            clearDraft();
        }
    });
    watch(
        () => props.screenplay,
        (screenplay) => {
            if (!screenplay || hasDraft.value || scriptForm.processing) return;
            const script = JSON.parse(JSON.stringify(screenplay)) as Screenplay;
            sourceVersion.value = revision.value?.number ?? 0;
            scriptForm.defaults({ script, lock_version: props.episode.lock_version });
            scriptForm.reset();
            scriptText.value = JSON.stringify(script, null, 2);
            generateForm.lock_version = props.episode.lock_version;
            clearDraft();
        },
    );
    const conflict = computed(() => {
        const saved = props.screenplay;
        if (!saved) return null;
        const fields = {
            expected_final_state: 'Финал сцены',
            objective: 'Цель сцены',
            initial_state: 'В начале сцены',
            title: 'Название сцены',
            location: 'Место',
            time: 'Время',
        } as const;
        for (const [index, scene] of scriptForm.script.scenes.entries()) {
            const remote = saved.scenes[index];
            if (!remote) continue;
            for (const key of Object.keys(fields) as (keyof typeof fields)[]) {
                if (scene[key] !== remote[key]) return { label: fields[key], local: scene[key], server: remote[key], index };
            }
        }
        for (const key of ['title', 'objective', 'public_caption'] as const) {
            if (scriptForm.script[key] !== saved[key])
                return {
                    label: { title: 'Название эпизода', objective: 'Цель эпизода', public_caption: 'Описание для публикации' }[key],
                    local: scriptForm.script[key],
                    server: saved[key],
                    index: null,
                };
        }
        return null;
    });
    function loadServerVersion() {
        if (!props.screenplay) return;
        const saved = JSON.parse(JSON.stringify(props.screenplay)) as Screenplay;
        scriptForm.defaults({ script: saved, lock_version: props.episode.lock_version });
        scriptForm.reset();
        scriptForm.clearErrors();
        generateForm.lock_version = props.episode.lock_version;

        sourceVersion.value = revision.value?.number ?? 0;
        scriptText.value = JSON.stringify(saved, null, 2);
        generateForm.brief = '';
        clearDraft();
    }
    function keepLocalVersion() {
        // The user explicitly chose their complete local screenplay. The current server revision stays in history.
        scriptForm.lock_version = props.episode.lock_version;
        generateForm.lock_version = props.episode.lock_version;
        sourceVersion.value = revision.value?.number ?? 0;
        persistDraft();
        restored.value = true;
    }
    function failed() {
        saveError.value = draftStored.value
            ? 'Сервер недоступен. В этой вкладке сохранилась локальная копия.'
            : 'Не удалось сохранить изменения. Оставьте вкладку открытой и повторите отправку.';
        return false as const;
    }
    function postScript(script: Screenplay) {
        scriptForm.transform((data) => ({ ...data, script }));
        submitForm(scriptForm, ['episodes.scripts.store', params], {
            preserveState: 'errors',
            onSuccess: clearDraft,
            onNetworkError: failed,
            onHttpException: (response) => (response.status === 419 ? undefined : failed()),
            onFinish: () => {
                scriptForm.transform((data) => data);
            },
        });
    }
    function saveScript() {
        if (!online.value || scriptForm.processing || editorStale.value) return;
        saveError.value = '';
        postScript(scriptForm.script);
    }
    function checkJson(): Screenplay | null {
        jsonError.value = '';
        let parsed: unknown;
        try {
            parsed = JSON.parse(scriptText.value);
            if (typeof parsed !== 'object' || parsed === null || Array.isArray(parsed)) throw new Error('Screenplay must be an object.');
        } catch {
            jsonError.value = 'Введите сценарий в формате JSON.';
            return null;
        }
        const candidate = parsed as Partial<Screenplay>;
        if (!Array.isArray(candidate.scenes)) {
            jsonError.value = 'scenes · ожидается массив сцен.';
            return null;
        }
        const invalid = candidate.scenes.findIndex((scene) => !scene || !Array.isArray(scene.shots));
        if (invalid >= 0) {
            const value = candidate.scenes[invalid]?.shots;
            jsonError.value = 'scenes[' + invalid + '].shots · ожидается массив кадров' + (typeof value === 'string' ? ', получена строка.' : '.');
            return null;
        }
        return candidate as Screenplay;
    }
    function importScript() {
        if (!online.value || scriptForm.processing || editorStale.value) return;
        saveError.value = '';
        const parsed = checkJson();
        if (parsed) postScript(parsed);
    }
    function generateScript() {
        submitForm(generateForm, ['episodes.scripts.generate', params], { preserveState: 'errors', onSuccess: clearDraft });
    }
    function approveScript() {
        if (revision.value)
            submitForm(approveForm, ['episodes.scripts.approve', { ...params, revision: revision.value.id }], {
                preserveScroll: true,
                preserveState: 'errors',
            });
    }
    return {
        online,
        draftStored,
        scriptForm,
        generateForm,
        approveForm,
        scriptText,
        jsonError,
        checkJson,
        restored,
        saveError,
        sourceVersion,
        conflict,
        keepLocalVersion,
        loadServerVersion,
        scriptErrors,
        editorErrors,
        generateErrors,
        approveErrors,
        editorStale,
        clearDraft,
        hasDraft,
        changedFields,
        saveScript,
        importScript,
        generateScript,
        approveScript,
    };
}
