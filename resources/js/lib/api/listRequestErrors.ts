import type { VisitCallbacks } from '@inertiajs/core';
import type { Ref } from 'vue';

export function listRequestErrors(error: Ref<string>): Pick<VisitCallbacks, 'onHttpException' | 'onNetworkError'> {
    return {
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
    };
}
