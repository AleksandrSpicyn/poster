import assert from 'node:assert/strict';
import test from 'node:test';
import { parseScreenplayImport } from '../../resources/js/lib/screenplay.ts';

test('Импорт объясняет ошибку кадра и передаёт исходные поля на проверку серверу', () => {
    assert.equal(parseScreenplayImport('[]').script, null);
    assert.equal(parseScreenplayImport('{"scenes":[{"shots":"кадр"}]}').error, 'scenes[0].shots · ожидается массив кадров, получена строка.');

    const input = { title: null, scenes: [{ shots: [{}] }], extension: [] };
    assert.deepEqual(parseScreenplayImport(JSON.stringify(input)), { script: input, error: '' });
});
