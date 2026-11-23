import { resolvePageComponent } from 'laravel-vite-plugin/inertia-helpers';
import type { DefineComponent } from 'vue';

const pages = import.meta.glob<DefineComponent>('./pages/**/*.vue');
const appName = import.meta.env.VITE_APP_NAME || 'Poster';

export function pageTitle(title: string) {
    return `${title} - ${appName}`;
}

export function resolvePage(name: string) {
    return resolvePageComponent(`./pages/${name}.vue`, pages);
}
