import { useRoute } from '@/composables/useRoute';
import { listRequestErrors } from '@/lib/api/listRequestErrors';
import type { ListFilters } from '@/types/content';
import { router, usePage } from '@inertiajs/vue3';
import { computed, reactive, ref, watch } from 'vue';

/** Keeps filters in sync with server props, including browser back/forward navigation. */
export function useListFilters(
    name: 'posts' | 'prompts' | 'projects.index' | 'projects.show' | 'channels.index',
    serverFilters: () => ListFilters,
    routeParameters?: () => Record<string, string | number>,
) {
    const route = useRoute();
    const page = usePage();
    const committed = computed(serverFilters);
    const filters = reactive({ ...committed.value });
    const keys = Object.keys(filters) as (keyof ListFilters)[];
    const loading = ref(false);
    const error = ref('');

    watch([() => page.url.split('#')[0], ...keys.map((key) => () => committed.value[key])], () => Object.assign(filters, committed.value));

    function refresh() {
        router.get(
            route(name, routeParameters?.()),
            { ...filters },
            {
                preserveScroll: true,
                preserveState: true,
                onStart: () => {
                    loading.value = true;
                    error.value = '';
                },
                onFinish: () => {
                    loading.value = false;
                },
                onError: (errors) => {
                    error.value = Object.values(errors).join(' ');
                },
                ...listRequestErrors(error),
            },
        );
    }

    function applyFilters() {
        filters.page = 1;
        refresh();
    }

    function changeSort(sort: string) {
        filters.sort = sort;
        applyFilters();
    }

    function changePerPage(perPage: number) {
        filters.perPage = perPage;
        applyFilters();
    }

    function changePage(page: number) {
        filters.page = page;
        refresh();
    }

    return { filters, loading, error, applyFilters, changeSort, changePerPage, changePage, refresh };
}
