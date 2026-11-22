import type { ListFilters } from '@/types/content';
import { router } from '@inertiajs/vue3';
import { reactive, ref, watch } from 'vue';
import { useRoute } from 'ziggy-js';

/** Keeps filters in sync with server props, including browser back/forward navigation. */
export function useListFilters(
    name: 'posts' | 'prompts' | 'projects.index' | 'projects.show' | 'channels.index',
    serverFilters: () => ListFilters,
    routeParameters?: () => Record<string, string | number>,
) {
    const route = useRoute();
    const filters = reactive(serverFilters());
    const loading = ref(false);
    const error = ref('');

    watch(serverFilters, (value) => Object.assign(filters, value));

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
                onHttpException: (response) => {
                    if (response.status === 419) return;
                    error.value =
                        response.status === 403
                            ? 'Доступ к списку ограничен.'
                            : 'Не удалось обновить список. На экране остались последние загруженные данные.';
                    return false;
                },
                onNetworkError: () => {
                    error.value = 'Нет связи. Проверьте подключение и повторите попытку.';
                    return false;
                },
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
