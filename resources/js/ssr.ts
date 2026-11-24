import type { SharedData } from '@/types';
import type { Page } from '@inertiajs/core';
import { createInertiaApp } from '@inertiajs/vue3';
import createServer from '@inertiajs/vue3/server';
import { createSSRApp, h } from 'vue';
import { renderToString } from 'vue/server-renderer';
import { ZiggyVue } from 'ziggy-js';
import { installAppearance } from './composables/useAppearance';
import { pageTitle, resolvePage } from './inertiaPages';

// Отдельная точка SSR использует те же страницы и props, без браузерной инициализации темы.
createServer((page) =>
    createInertiaApp<SharedData>({
        page: page as Page<SharedData>,
        render: renderToString,
        title: pageTitle,
        resolve: resolvePage,
        setup({ App, props, plugin }) {
            return createSSRApp({ render: () => h(App, props) })
                .use(installAppearance, props.initialPage.props.appearanceValues, props.initialPage.props.appearance)
                .use(plugin)
                .use(ZiggyVue, { ...props.initialPage.props.ziggy, location: new URL(props.initialPage.props.ziggy.location) });
        },
    }),
);
