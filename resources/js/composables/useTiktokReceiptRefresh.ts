import { router } from '@inertiajs/vue3';
import { ref } from 'vue';

export function useTiktokReceiptRefresh() {
    const refreshingReceipt = ref(false);
    const receiptError = ref('');
    function refreshReceipt() {
        if (refreshingReceipt.value) return;
        refreshingReceipt.value = true;
        router.reload({
            only: ['videos'],
            onSuccess: () => {
                receiptError.value = '';
            },
            onHttpException: (response) => {
                if (response.status === 419) return;
                receiptError.value = 'Не удалось обновить статус. Повторите проверку.';
                return false;
            },
            onNetworkError: () => {
                receiptError.value = 'Нет связи. Проверьте подключение и повторите попытку.';
                return false;
            },
            onFinish: () => {
                refreshingReceipt.value = false;
            },
        });
    }
    return { refreshingReceipt, receiptError, refreshReceipt };
}
