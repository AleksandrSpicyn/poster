import { createInertiaApp } from '@inertiajs/vue3';
import vue from '@vitejs/plugin-vue';
import assert from 'node:assert/strict';
import test from 'node:test';
import { fileURLToPath } from 'node:url';
import { createServer } from 'vite';
import { createSSRApp, h } from 'vue';
import { renderToString } from 'vue/server-renderer';
import { ZiggyVue } from 'ziggy-js';

test('кнопки тарифов открывают оформление соответствующего продукта', async () => {
    const server = await createServer({
        configFile: false,
        plugins: [vue()],
        server: { middlewareMode: true, hmr: false },
        resolve: { alias: { '@': fileURLToPath(new URL('../../resources/js', import.meta.url)) } },
    });

    try {
        const { default: Pricing } = await server.ssrLoadModule('/resources/js/pages/Pricing.vue');
        const url = 'https://poster.example.test';
        const catalog = ['one_off', 'author', 'studio'].map((code) => ({ code, label: code, seconds: 30, amount: 100, monthly: code !== 'one_off' }));
        const routes = Object.fromEntries(
            ['home', 'pricing', 'login', 'billing.checkout'].map((name) => [
                name,
                { uri: name === 'billing.checkout' ? 'billing/checkout/{product}' : name, methods: ['GET'] },
            ]),
        );
        const { body } = await createInertiaApp({
            page: { component: 'Pricing', props: { catalog, auth: { user: null } }, url: '/pricing', version: null },
            resolve: () => Pricing,
            render: renderToString,
            setup: ({ App, props, plugin }) =>
                createSSRApp({ render: () => h(App, props) })
                    .use(plugin)
                    .use(ZiggyVue, { url, routes, location: new URL('/pricing', url), defaults: {} }),
        });

        for (const { code, label } of catalog) {
            const link = [...body.matchAll(/<a\b[^>]*>/g)].map(([tag]) => tag).find((tag) => tag.includes(`aria-label="Выбрать пакет «${label}»"`));
            assert.ok(link?.includes(`href="${url}/billing/checkout/${code}"`));
        }
    } finally {
        await server.close();
    }
});
