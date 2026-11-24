import { useRoute } from '@/composables/useRoute';
import { fetchBillingUsage, fetchProductionQuote } from '@/lib/api/billing';
import { onUnmounted, ref, watch } from 'vue';

export interface GenerationUsageTarget {
    kind: 'shot' | 'run' | 'production';
    id: number;
    seriesId?: number;
}

export function useGenerationUsage(props: GenerationUsageTarget) {
    const route = useRoute();
    const data = ref<Awaited<ReturnType<typeof fetchBillingUsage>>>();
    const error = ref('');
    let controller: AbortController | undefined;
    async function refresh() {
        controller?.abort();
        const request = new AbortController();
        controller = request;
        error.value = '';
        data.value = undefined;
        try {
            const result =
                props.kind === 'production'
                    ? await fetchProductionQuote(route, props.id, props.seriesId, request.signal)
                    : await fetchBillingUsage(route, props.kind, props.id, request.signal);
            if (!request.signal.aborted) data.value = result;
        } catch (failure) {
            if (!request.signal.aborted) error.value = failure instanceof Error ? failure.message : 'Не удалось загрузить баланс.';
        }
    }
    const open = ref(false);
    watch([() => props.kind, () => props.id, () => props.seriesId], () => {
        if (open.value) void refresh();
    });
    function setOpen(value: boolean) {
        open.value = value;
        if (open.value) void refresh();
        else controller?.abort();
    }
    onUnmounted(() => controller?.abort());
    return { data, error, refresh, setOpen };
}
