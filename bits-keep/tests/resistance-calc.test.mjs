import assert from 'node:assert/strict';
import {
    calculateVariable,
    formatResistance,
    normalizeNetworkResponse,
    normalizeCustomValues,
    parseTarget,
} from '../resources/js/pages/resistance-calc.js';

assert.equal(parseTarget('4.7kΩ', 'R'), 4700);
assert.equal(parseTarget('10k', 'R'), 10000);
assert.ok(Math.abs(parseTarget('4.7u', 'C') - 4.7e-6) < 1e-18);
assert.ok(Math.abs(parseTarget('100n', 'C') - 100e-9) < 1e-18);
assert.ok(Math.abs(parseTarget('100nF', 'C') - 100e-9) < 1e-18);
assert.equal(parseTarget('50%', 'divider'), 0.5);
assert.equal(formatResistance(4700), '4.7kΩ');
assert.deepEqual(normalizeCustomValues('10k, 4.7k', 'R'), [10000, 4700]);
assert.ok(Math.abs(normalizeCustomValues('4.7u, 100n', 'C')[0] - 4.7e-6) < 1e-18);
assert.ok(Math.abs(normalizeCustomValues('4.7u, 100n', 'C')[1] - 100e-9) < 1e-18);
assert.deepEqual(normalizeCustomValues('10k', 'divider'), [10000]);

const response = normalizeNetworkResponse({
    success: true,
    data: {
        result: { candidates: [{ expression: '1k + 2k' }], elapsed_ms: 12 },
        summary: '1 件の候補が見つかりました',
        warnings: ['探索量上限'],
        next_actions: ['条件を絞る'],
    },
});
assert.equal(response.result.candidates.length, 1);
assert.deepEqual(response.warnings, ['探索量上限']);
assert.deepEqual(response.nextActions, ['条件を絞る']);

const invalid = normalizeNetworkResponse({
    success: true,
    data: {
        result: null,
        summary: '任意値が入力されていません',
        warnings: ['任意値が入力されていません'],
        next_actions: ['E系列を選ぶ'],
    },
});
assert.equal(invalid.result, null);
assert.equal(invalid.summary, '任意値が入力されていません');

const seriesTrim = calculateVariable({
    reference_raw: '10k',
    span_mode: 'percent',
    span_raw: '20',
    reference_position: 'upper',
    circuit: 'series',
});
assert.equal(seriesTrim.valid, true);
assert.equal(seriesTrim.requirement.low, 8000);
assert.equal(seriesTrim.requirement.high, 10000);
assert.equal(seriesTrim.ideal.fixed, 8000);
assert.equal(seriesTrim.ideal.pot, 2000);
assert.equal(seriesTrim.bestCandidate.fixed, 5100);
assert.equal(seriesTrim.bestCandidate.pot, 5000);
assert.equal(seriesTrim.bestCandidate.coversTargetRange, true);
assert.equal(seriesTrim.bestCandidate.status, 'check');
assert.equal(seriesTrim.bestCandidate.verdict, 'CHECK');
assert.ok(seriesTrim.bestCandidate.tags.includes('VR範囲側補正'));
assert.ok(seriesTrim.bestCandidate.tags.includes('固定抵抗再計算'));
assert.ok(seriesTrim.bestCandidate.tags.includes('要部品選定'));
assert.ok(seriesTrim.bestCandidate.tags.includes('許容差/電力未評価'));
assert.ok(seriesTrim.bestCandidate.tags.includes('購入/在庫未確認'));

const centerTrim = calculateVariable({
    reference_raw: '10k',
    span_mode: 'percent',
    span_raw: '20',
    reference_position: 'center',
    circuit: 'series',
});
assert.equal(centerTrim.requirement.low, 9000);
assert.equal(centerTrim.requirement.high, 11000);

const lowerTrim = calculateVariable({
    reference_raw: '10k',
    span_mode: 'percent',
    span_raw: '20',
    reference_position: 'lower',
    circuit: 'series',
});
assert.equal(lowerTrim.requirement.low, 10000);
assert.equal(lowerTrim.requirement.high, 12000);
assert.equal(lowerTrim.bestCandidate.fixed, 10000);
assert.equal(lowerTrim.bestCandidate.pot, 2000);

const ohmTrim = calculateVariable({
    reference_raw: '10k',
    span_mode: 'ohm',
    span_raw: '2k',
    reference_position: 'upper',
    circuit: 'series',
});
assert.deepEqual(
    { low: ohmTrim.requirement.low, high: ohmTrim.requirement.high },
    { low: seriesTrim.requirement.low, high: seriesTrim.requirement.high },
);

const parallelTrim = calculateVariable({
    reference_raw: '10k',
    span_mode: 'ohm',
    span_raw: '2k',
    reference_position: 'upper',
    circuit: 'parallel',
});
assert.equal(parallelTrim.valid, true);
assert.equal(Math.round(parallelTrim.ideal.pot), 40000);
assert.equal(parallelTrim.bestCandidate.fixed, 10000);
assert.equal(parallelTrim.bestCandidate.pot, 20000);
assert.ok(Math.abs(parallelTrim.bestCandidate.low - 6666.666666666666) < 1e-9);
assert.equal(parallelTrim.bestCandidate.coversTargetRange, true);
assert.equal(parallelTrim.bestCandidate.status, 'check');

const customTrim = calculateVariable({
    reference_raw: '10k',
    span_mode: 'percent',
    span_raw: '20',
    circuit: 'series',
    fixed_source: 'custom',
    fixed_custom_values: '8k',
    pot_source: 'custom',
    pot_custom_values: '2k',
    endpoint_tolerance_pct: 0,
});
assert.equal(customTrim.bestCandidate.fixed, 8000);
assert.equal(customTrim.bestCandidate.pot, 2000);
assert.equal(customTrim.bestCandidate.fixedSource, 'custom');
assert.equal(customTrim.bestCandidate.status, 'check');

const rangeShortage = calculateVariable({
    reference_raw: '10k',
    span_mode: 'percent',
    span_raw: '20',
    circuit: 'series',
    fixed_source: 'custom',
    fixed_custom_values: '8.2k',
    pot_source: 'custom',
    pot_custom_values: '2k',
});
assert.equal(rangeShortage.bestCandidate.status, 'warn');
assert.equal(rangeShortage.bestCandidate.coversTargetRange, false);

const toleratedShortageStillWarn = calculateVariable({
    reference_raw: '10k',
    span_mode: 'percent',
    span_raw: '20',
    circuit: 'series',
    fixed_source: 'custom',
    fixed_custom_values: '8.2k',
    pot_source: 'custom',
    pot_custom_values: '2k',
    endpoint_tolerance_pct: 20,
});
assert.equal(toleratedShortageStillWarn.bestCandidate.status, 'warn');
assert.equal(toleratedShortageStillWarn.bestCandidate.coversTargetRange, false);
assert.equal(toleratedShortageStillWarn.bestCandidate.nearTargetRange, true);

const parallelShortage = calculateVariable({
    reference_raw: '10k',
    span_mode: 'ohm',
    span_raw: '2k',
    circuit: 'parallel',
    fixed_source: 'custom',
    fixed_custom_values: '10k',
    pot_source: 'custom',
    pot_custom_values: '50k',
});
assert.equal(parallelShortage.bestCandidate.status, 'warn');
assert.equal(parallelShortage.bestCandidate.coversTargetRange, false);
assert.equal(parallelTrim.bestCandidate.operatorDisplay, '||');

console.log('resistance calc assertions passed');
