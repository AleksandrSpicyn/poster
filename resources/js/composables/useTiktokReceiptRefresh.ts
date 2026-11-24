import { router } from '@inertiajs/vue3';
import { onScopeDispose, ref, watch, type WatchSource } from 'vue';

export function useTiktokReceiptRefresh(selection: WatchSource<number | string | null>[]) {
    const refreshingReceipt = ref(false);
    const receiptError = ref('');
    let revision = 0;
    let cancel: (() => void) | undefined;

    function reset() {
        revision++;
        cancel?.();
        cancel = undefined;
        receiptError.value = '';
        refreshingReceipt.value = false;
    }

    watch(selection, reset, { flush: 'sync' });
    onScopeDispose(reset);

    function refreshReceipt() {
        if (refreshingReceipt.value) return;
        const current = revision;
        refreshingReceipt.value = true;
        router.reload({
            only: ['videos'],
            onCancelToken: (token) => {
                cancel = token.cancel;
            },
            onSuccess: () => {
                if (current === revision) receiptError.value = '';
            },
            onHttpException: (response) => {
                if (response.status === 419) return;
                if (current === revision) receiptError.value = 'Не удалось обновить статус. Повторите проверку.';
                return false;
            },
            onNetworkError: () => {
                if (current === revision) receiptError.value = 'Нет связи. Проверьте подключение и повторите попытку.';
                return false;
            },
            onFinish: () => {
                if (current !== revision) return;
                refreshingReceipt.value = false;
                cancel = undefined;
            },
        });
    }
    return { refreshingReceipt, receiptError, refreshReceipt };
}
