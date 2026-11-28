import type { ShotTake } from '@/types/episodes';

export function activeTake(take: ShotTake) {
    return take.operations.some((op) => ['frame_review', 'planned', 'submitting', 'submitted', 'running', 'submission_unknown'].includes(op.status));
}

export function takeStatus(take?: ShotTake) {
    if (!take) return 'Пока нет дублей';
    if (take.generated) return 'Готов к монтажу';
    const ops = take.operations;
    if (ops.some((op) => op.status === 'submission_unknown')) return 'Требуется проверка';
    if (ops.some((op) => op.retrying_download)) return 'Загрузка ролика';
    if (ops.some((op) => op.can_retry_download || (op.stage === 'download' && op.status === 'failed'))) return 'Ошибка загрузки';
    if (activeTake(take) && ops.some((op) => op.cancel_requested_at)) return 'Запрошена остановка';
    if (ops.some((op) => op.status === 'frame_review')) return 'Согласование первого кадра';
    return (
        ops.find((op) => ['running', 'submitting', 'submitted', 'failed'].includes(op.status))?.status_label ??
        ops.at(-1)?.status_label ??
        'В ожидании'
    );
}

// Shot mutations return to the episode. Keep its selected shot across the Inertia update.
export function keepShotLocation(shotId: number) {
    return (page: { url: string }) => {
        const incoming = new URL(page.url, location.href);
        if (incoming.pathname === location.pathname && location.hash.startsWith('#shots')) {
            page.url = incoming.pathname + incoming.search + '#shots/' + shotId;
        }
    };
}

export function preferredTake(takes: ShotTake[]) {
    const ordered = [...takes].sort((a, b) => b.take_number - a.take_number);
    return ordered.find((t) => t.operations.some((op) => op.status === 'submission_unknown')) ?? ordered.find(activeTake) ?? ordered[0];
}
