import type { ProjectFormProps, ProjectSchedule } from '@/types/projects';
import { useForm, usePage } from '@inertiajs/vue3';
import { computed, onMounted, ref, watch } from 'vue';

export function useProjectSettingsDraft(props: ProjectFormProps) {
    const dedicatedSeries = !props.project && new URL(usePage().url, 'http://poster.local').searchParams.get('format') === 'series';
    const initialMode = dedicatedSeries && 'series' in props.contentModes ? 'series' : Object.keys(props.contentModes)[0];
    const serverData = computed(() => ({
        schedule: props.scheduleSettings?.configured
            ? { enabled: props.scheduleSettings.enabled, time: props.scheduleSettings.time, timezone: props.scheduleSettings.timezone }
            : (null as ProjectSchedule | null),
        settings_version: props.project?.settings_version ?? null,
        name: props.project?.name ?? '',
        prompt: props.series?.specification.premise ?? props.project?.prompt ?? '',
        content_mode: props.project?.content_mode ?? initialMode,
        is_active: props.project?.is_active ?? true,
        output_mode: props.series?.output_mode ?? 'episodes',
        series_specification: {
            premise: '',
            visual_style: 'cinematic',
            language: 'en',
            audience: 'general',
            episode_duration_seconds: 15,
            shot_duration_seconds: 15,
            narrative_rules: '',
            cycle_structure: 'continuous_story',
            scene_mode: 'scripted',
            audio_mode: props.defaultAudioMode,
            ...props.series?.specification,
            story_outline: props.series?.specification.story_outline ?? '',
        },
        cast_settings: props.series?.cast_settings ?? { lead_character_id: null as number | null, voice_description: '', use_series_voice: false },
        series_lock_version: props.series?.lock_version ?? null,
        automation_enabled: props.series?.automation_enabled ?? true,
        auto_publish: props.series?.auto_publish ?? false,
        episode_limit: props.series?.episode_limit ?? '',
        channel_ids: props.auth.can_manage_channels ? [...props.selectedChannelIds] : null,
    }));
    const form = useForm(serverData.value);
    const legacyDraftKey = `poster:project:${props.auth.user.id}:new`;
    const draftKey = props.project ? `poster:project:${props.auth.user.id}:${props.project.id}` : `${legacyDraftKey}:${initialMode}`;
    type Fields = typeof serverData.value;
    type FieldKey = keyof Fields;
    const draftBase = ref<Fields>(serverData.value);
    const restored = ref(false);
    const conflict = ref(false);
    const storageUnavailable = ref(false);
    const settingsLabels: Record<FieldKey, string> = {
        schedule: 'Расписание производства',
        settings_version: 'Версия',
        name: 'Название',
        prompt: 'Описание',
        content_mode: 'Формат производства',
        is_active: 'Состояние',
        output_mode: 'Формат выпуска',
        series_specification: 'Паспорт сериала',
        cast_settings: 'Герои и звук',
        series_lock_version: 'Версия сериала',
        automation_enabled: 'Автоматизация',
        auto_publish: 'Автоматическая публикация',
        episode_limit: 'Число эпизодов',
        channel_ids: 'Каналы проекта',
    };
    const fieldKeys = Object.keys(settingsLabels) as FieldKey[];
    const changedKeys = computed(() =>
        fieldKeys.filter(
            (key) =>
                key !== 'settings_version' &&
                key !== 'series_lock_version' &&
                JSON.stringify(draftBase.value[key]) !== JSON.stringify(serverData.value[key]),
        ),
    );
    function clearDraft() {
        try {
            sessionStorage.removeItem(draftKey);
        } catch {
            storageUnavailable.value = true;
        }
    }
    function discardDraft() {
        conflict.value = false;
        restored.value = false;
        draftBase.value = serverData.value;
        form.reset();
        form.clearErrors();
        clearDraft();
    }
    function keepDraft() {
        const local = form.data();
        const edits = fieldKeys.filter(
            (key) =>
                key !== 'settings_version' && key !== 'series_lock_version' && JSON.stringify(local[key]) !== JSON.stringify(draftBase.value[key]),
        );
        draftBase.value = serverData.value;
        conflict.value = false;
        form.reset();
        Object.assign(form, Object.fromEntries(edits.map((key) => [key, local[key]])));
        restored.value = true;
    }
    watch(serverData, (latest, previous) => {
        if (JSON.stringify(latest) === JSON.stringify(previous)) return;
        const modified = JSON.stringify(form.data()) !== JSON.stringify(draftBase.value);
        form.defaults(latest);
        if (modified) {
            conflict.value = true;
            return;
        }
        discardDraft();
    });
    onMounted(() => {
        try {
            let stored = sessionStorage.getItem(draftKey);
            if (!props.project && !stored) {
                const legacy = sessionStorage.getItem(legacyDraftKey);
                if (legacy && JSON.parse(legacy)?.fields?.content_mode === initialMode) {
                    sessionStorage.setItem(draftKey, legacy);
                    sessionStorage.removeItem(legacyDraftKey);
                    stored = legacy;
                }
            }
            if (stored) {
                const saved = JSON.parse(stored);
                const base = typeof saved?.base === 'string' ? JSON.parse(saved.base) : saved?.base;
                if (base && typeof base === 'object' && saved.fields && typeof saved.fields === 'object') {
                    // Older sessions stored a JSON string and had no settings version.
                    const prior = { ...serverData.value, ...base };
                    draftBase.value = prior;
                    conflict.value = fieldKeys.some((key) => JSON.stringify(prior[key]) !== JSON.stringify(serverData.value[key]));
                    Object.assign(
                        form,
                        Object.fromEntries(fieldKeys.filter((key) => Object.hasOwn(saved.fields, key)).map((key) => [key, saved.fields[key]])),
                    );
                    if (dedicatedSeries) form.content_mode = 'series';
                    restored.value = true;
                } else clearDraft();
            }
        } catch {
            clearDraft();
        }
        watch(
            () => form.data(),
            (fields) => {
                try {
                    if (!conflict.value && JSON.stringify(fields) === JSON.stringify(serverData.value)) {
                        clearDraft();
                        return;
                    }
                    sessionStorage.setItem(draftKey, JSON.stringify({ base: draftBase.value, fields }));
                    storageUnavailable.value = false;
                } catch {
                    storageUnavailable.value = true;
                }
            },
            { deep: true, flush: 'sync' },
        );
    });
    return {
        form,
        dedicatedSeries,
        initialMode,
        restored,
        conflict,
        storageUnavailable,
        changedKeys,
        settingsLabels,
        draftBase,
        serverData,
        discardDraft,
        keepDraft,
        clearDraft,
    };
}
