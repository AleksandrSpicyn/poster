import '../css/app.css';

import type { SharedData } from '@/types';
import { createInertiaApp } from '@inertiajs/vue3';
import { createApp, h } from 'vue';
import { ZiggyVue } from 'ziggy-js';
import { installAppearance } from './composables/useAppearance';
import { pageTitle, resolvePage } from './inertiaPages';

// Имена страниц приходят из Laravel; общие props и Ziggy связывают кабинет с серверными routes.
createInertiaApp<SharedData>({
    title: pageTitle,
    resolve: resolvePage,
    setup({ el, App, props, plugin }) {
        createApp({ render: () => h(App, props) })
            .use(installAppearance, props.initialPage.props.appearanceValues)
            .use(plugin)
            .use(ZiggyVue)
            .mount(el);
    },
    progress: {
        color: '#4B5563',
    },
});
