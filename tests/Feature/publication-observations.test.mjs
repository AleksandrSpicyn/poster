import assert from 'node:assert/strict';
import test from 'node:test';
import { observationLabel, publicationDays, rangeLabel } from '../../resources/js/lib/analytics.ts';

test('Отчёт различает нулевое наблюдение, пропуск и прирост за несколько дней', () => {
    const dates = ['2026-11-01', '2026-11-02', '2026-11-03', '2026-11-04'];
    const days = publicationDays(dates, {
        snapshots: { [dates[0]]: 0, [dates[2]]: 5, [dates[3]]: 5 },
        intervals: [
            { from: dates[0], to: dates[2], change: 5 },
            { from: dates[2], to: dates[3], change: 0 },
        ],
    });
    assert.deepEqual(days.map(observationLabel), [
        '0 · первое наблюдение',
        'Нет наблюдения',
        '5 · прирост за ' + rangeLabel(dates[0], dates[2]) + ' +5',
        '5 · прирост 0',
    ]);
});
