import assert from 'node:assert/strict';
import test from 'node:test';
import { createSSRApp, h } from 'vue';
import { renderToString } from 'vue/server-renderer';
import { ZiggyVue } from 'ziggy-js';
import { useRoute } from '../../resources/js/composables/useRoute.ts';

test('Серверный рендер формирует ссылки с адресом текущего запроса', async () => {
    const origins = ['https://first.example.test', 'https://second.example.test'];
    const links = await Promise.all(
        origins.map((url) => {
            const app = createSSRApp({
                setup() {
                    const route = useRoute();
                    return () => h('a', { href: route('projects.show', 21) }, 'Проект');
                },
            });
            app.use(ZiggyVue, {
                url,
                location: new URL('/projects', url),
                defaults: {},
                routes: { 'projects.show': { uri: 'projects/{project}', methods: ['GET', 'HEAD'] } },
            });
            return renderToString(app);
        }),
    );

    assert.deepEqual(
        links,
        origins.map((url) => `<a href="${url}/projects/21">Проект</a>`),
    );
});
