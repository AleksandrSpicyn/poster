import { inject } from 'vue';
import type { route as ziggyRoute } from 'ziggy-js';

export function useRoute(): typeof ziggyRoute {
    const route = inject<typeof ziggyRoute>('route');
    if (!route) throw new Error('ZiggyVue must be installed before resolving routes.');
    return route;
}
