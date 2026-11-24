import type { BillingOrder } from '@/types/billing';
const rubles = new Intl.NumberFormat('ru-RU', { style: 'currency', currency: 'RUB', minimumFractionDigits: 0, maximumFractionDigits: 2 });
const dates = new Intl.DateTimeFormat('ru-RU', { day: 'numeric', month: 'short', year: 'numeric', timeZone: 'Europe/Moscow' });
export const money = (kopecks: number) => rubles.format(kopecks / 100);
export function billingDate(utc: string | null) {
    if (!utc) return '—';
    return dates.format(new Date(utc.replace(' ', 'T') + 'Z'));
}
const labels: Record<string, string> = {
    open: 'Открыт',
    created: 'Подготовка',
    pending: 'Ожидает подтверждения',
    unknown: 'На сверке',
    succeeded: 'Подтверждён',
    paid: 'Оплачен',
    cancelled: 'Отменён',
    refunded: 'Возврат',
    partially_refunded: 'Частичный возврат',
    grant: 'Начисление',
    allocate: 'Распределение резерва по кадрам',
    refund_hold: 'Резерв для возврата',
    refund_release: 'Отмена резерва возврата',
    held: 'В резерве',
    consumed: 'Списано',
    released: 'Резерв освобождён',
    hold: 'Резерв',
    reserve: 'Резерв',
    consume: 'Расход',
    release: 'Освобождение резерва',
    expire: 'Истечение срока',
    revoke: 'Корректировка',
    payment: 'Оплата',
    offset: 'Зачёт предоплаты',
    refund: 'Возврат',
    registered: 'Зарегистрирован',
};
export const operationLabel = (status: string) => labels[status] ?? status;
export function orderStatus(order: BillingOrder) {
    return operationLabel(order.status === 'open' ? (order.payment_status ?? order.status) : order.status);
}
