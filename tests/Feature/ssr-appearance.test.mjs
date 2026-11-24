import assert from 'node:assert/strict';
import test from 'node:test';
import { createSSRApp, h } from 'vue';
import { renderToString } from 'vue/server-renderer';
import { installAppearance, useAppearance } from '../../resources/js/composables/useAppearance.ts';

test('SSR сохраняет выбранную тему отдельно для каждого запроса', async () => {
    const values = { LIGHT: 'light', DARK: 'dark', SYSTEM: 'system' };
    const rendered = await Promise.all(
        [values.DARK, values.LIGHT].map((initial) => {
            const app = createSSRApp({
                setup() {
                    const { appearance } = useAppearance();
                    return () => h('span', appearance.value);
                },
            });
            app.use(installAppearance, values, initial);
            return renderToString(app);
        }),
    );

    assert.deepEqual(rendered, ['<span>dark</span>', '<span>light</span>']);
});
