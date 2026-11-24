import assert from 'node:assert/strict';
import test from 'node:test';
import { createRenderer, nextTick, reactive } from 'vue';
import { useProjectSettingsDraft } from '../../resources/js/composables/useProjectSettingsDraft.ts';

test('Черновик переживает обновление проекта и объединяется с новой версией', async (context) => {
    const stored = new Map();
    const storage = Object.getOwnPropertyDescriptor(globalThis, 'sessionStorage');
    Object.defineProperty(globalThis, 'sessionStorage', {
        configurable: true,
        value: {
            getItem: (key) => stored.get(key) ?? null,
            setItem: (key, value) => stored.set(key, value),
            removeItem: (key) => stored.delete(key),
        },
    });
    context.after(() => {
        if (storage) Object.defineProperty(globalThis, 'sessionStorage', storage);
        else delete globalThis.sessionStorage;
    });

    const props = reactive({
        project: { id: 7, name: 'Проект', prompt: 'Описание', settings_version: 1 },
        auth: { user: { id: 8 }, can_manage_channels: false },
        contentModes: { short: 'Ролик' },
        defaultAudioMode: 'silent',
    });
    let draft;
    const renderer = createRenderer({ createComment: () => ({}), insert() {}, remove() {}, parentNode() {}, nextSibling() {} });
    const app = renderer.createApp({
        setup() {
            draft = useProjectSettingsDraft(props);
            return () => null;
        },
    });
    app.mount({});
    context.after(() => app.unmount());

    draft.form.series_specification.visual_style = 'animation';
    props.series = { specification: { language: 'ru' }, lock_version: 2 };
    draft.form.name = 'Мой черновик';
    props.project = { ...props.project, prompt: 'Новое описание', settings_version: 2 };
    await nextTick();

    assert.equal(draft.conflict.value, true);
    assert.equal(draft.form.name, 'Мой черновик');
    assert.deepEqual(draft.changedKeys.value, ['prompt', 'series_specification']);
    draft.keepDraft();
    assert.equal(draft.form.series_specification.visual_style, 'animation');
    assert.equal(draft.form.series_specification.language, 'ru');
    assert.equal(draft.form.series_lock_version, 2);
    assert.equal(draft.form.prompt, 'Новое описание');
    assert.equal(draft.form.settings_version, 2);
    assert.equal(draft.form.name, 'Мой черновик');
    assert.equal(draft.conflict.value, false);
    assert.equal(JSON.parse(stored.get('poster:project:8:7')).base.settings_version, 2);

    draft.discardDraft();
    props.project = { ...props.project, name: 'Подтверждено', settings_version: 3 };
    await nextTick();
    assert.equal(draft.conflict.value, false);
    assert.equal(draft.form.name, 'Подтверждено');
    assert.equal(stored.size, 0);
});
