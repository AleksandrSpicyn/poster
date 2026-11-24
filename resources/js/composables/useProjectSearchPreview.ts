import { useRoute } from '@/composables/useRoute';
import { fetchProjectSearchPreview, type ProjectSearchPreview } from '@/lib/api/projects';
import type { ListFilters } from '@/types/content';
import type { ProjectsPageProps } from '@/types/projects';
import { usePage } from '@inertiajs/vue3';
import { ref, watch, type Ref } from 'vue';

export function useProjectSearchPreview(searching: Ref<boolean>, filters: ListFilters) {
    const route = useRoute();
    const page = usePage<ProjectsPageProps>();
    const preview = ref<ProjectSearchPreview>();
    watch(
        () => [searching.value, filters.search, filters.active, filters.clipping, page.props.projects] as const,
        ([open, search, active, clipping, projects], _, onCleanup) => {
            preview.value = undefined;
            if (!open) return;
            if (
                search === page.props.filters.search &&
                !page.props.filters.archived &&
                !!active === !!page.props.filters.active &&
                !!clipping === !!page.props.filters.clipping
            ) {
                preview.value = { total: projects.meta.total, name: projects.data[0]?.name };
                return;
            }

            const controller = new AbortController();
            const timer = window.setTimeout(async () => {
                try {
                    const result = await fetchProjectSearchPreview(route, { search, active, clipping }, page, controller.signal);
                    if (!controller.signal.aborted) preview.value = result;
                } catch {
                    // The full search remains available when its background preview is interrupted.
                }
            }, 220);
            onCleanup(() => {
                window.clearTimeout(timer);
                controller.abort();
            });
        },
    );
    return preview;
}
