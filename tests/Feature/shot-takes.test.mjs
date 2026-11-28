import assert from 'node:assert/strict';
import test from 'node:test';
import { activeTake, preferredTake, takeStatus } from '../../resources/js/lib/shotTakes.ts';

test('Карточка открывает дубль, который требует проверки, даже при наличии более нового видео', () => {
    const unknown = { take_number: 1, operations: [{ status: 'submission_unknown' }] };
    const review = { take_number: 2, operations: [{ status: 'frame_review' }] };
    const ready = { take_number: 3, generated: true, operations: [] };

    assert.equal(preferredTake([ready, review, unknown]), unknown);
    assert.equal(preferredTake([ready, review]), review);
    assert.equal(preferredTake([ready]), ready);
    assert.ok(activeTake(unknown));
    assert.ok(activeTake(review));
    assert.equal(activeTake(ready), false);
});

test('После остановки дубль показывает окончательный статус, а повторная загрузка имеет собственный статус', () => {
    const stopped = { operations: [{ status: 'cancelled', status_label: 'Остановлено', cancel_requested_at: '2026-11-27' }] };
    const retrying = { operations: [{ status: 'running', retrying_download: true }] };

    assert.equal(takeStatus(stopped), 'Остановлено');
    assert.equal(activeTake(stopped), false);
    assert.equal(takeStatus(retrying), 'Загрузка ролика');
});
