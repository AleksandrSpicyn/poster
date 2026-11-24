import type { VisitCallbacks } from '@inertiajs/core';

export function listRequestErrors(setError: (message: string) => void): Pick<VisitCallbacks, 'onHttpException' | 'onNetworkError'> {
    return {
        onHttpException: (response) => {
            if (response.status === 419) return;
            setError(
                response.status === 403
                    ? 'Доступ к списку ограничен.'
                    : 'Не удалось обновить список. На экране остались последние загруженные данные.',
            );
            return false;
        },
        onNetworkError: () => {
            setError('Нет связи. Проверьте подключение и повторите попытку.');
            return false;
        },
    };
}
