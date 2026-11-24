import assert from 'node:assert/strict';
import test from 'node:test';
import { createShotThumbnailLookup } from '../../resources/js/lib/shotThumbnails.ts';

test('Миниатюра соответствует текущему содержимому редактируемого кадра', () => {
    const shot = { title: 'Перед рассветом', dialogue: [] };
    const revision = { scenes: [{ shots: [{ takes: [{ media: [{ collection: 'shot-image', url: '/frame.webp' }] }] }] }] };
    const lookup = createShotThumbnailLookup({ scenes: [{ shots: [{ ...shot }] }] }, revision);

    assert.equal(lookup(shot), '/frame.webp');
    shot.title = 'После рассвета';
    assert.equal(lookup(shot), undefined);
});
