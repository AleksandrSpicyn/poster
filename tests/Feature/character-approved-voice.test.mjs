import vue from '@vitejs/plugin-vue';
import assert from 'node:assert/strict';
import test from 'node:test';
import { fileURLToPath } from 'node:url';
import { createServer } from 'vite';
import { createSSRApp } from 'vue';
import { renderToString } from 'vue/server-renderer';
import { ZiggyVue } from 'ziggy-js';

test('замена голоса доступна в карточке текущей утверждённой версии', async () => {
    const server = await createServer({
        configFile: false,
        plugins: [vue()],
        server: { middlewareMode: true, hmr: false },
        resolve: { alias: { '@': fileURLToPath(new URL('../../resources/js', import.meta.url)) } },
    });

    try {
        const { default: Card } = await server.ssrLoadModule('/resources/js/components/characters/CharacterRevisionCard.vue');
        const revision = {
            id: 2,
            number: 2,
            status: 'approved',
            appearance: {},
            voice: {},
            lock_version: 3,
            voice_sample: { id: 7, url: '/voice.wav' },
        };
        for (const [approvedId, archived, editable] of [
            [2, false, true],
            [1, false, false],
            [2, true, false],
        ]) {
            const app = createSSRApp(Card, {
                revision,
                approvedRevision: { ...revision, id: approvedId },
                params: { project: 1, series: 1, character: 1 },
                archived,
                latestRevision: false,
                speechVoices: {},
                referenceViews: {},
            }).use(ZiggyVue, { url: 'http://localhost', routes: {}, defaults: {} });
            const html = await renderToString(app);
            assert.match(html, /<audio[^>]+src="\/voice.wav"/);
            assert.equal(html.includes('type="file"'), editable);
            assert.equal(html.includes('Заменить образец голоса'), editable);
            assert.doesNotMatch(html, /Тип референса|Сохранить версию/);
        }
    } finally {
        await server.close();
    }
});
