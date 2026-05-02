import assert from 'node:assert/strict';
import {
    calculateVariable,
    calculateVariableDivider,
    default as setupResistanceCalc,
    formatResistance,
    formatCurrent,
    formatPower,
    normalizeNetworkResponse,
    normalizeCustomValues,
    parseTarget,
    dividerRatioFromVoltages,
} from '../resources/js/pages/resistance-calc.js';

function unwrapVueValue(value) {
    if (value && typeof value === 'object' && value.__v_isRef === true) return value.value;
    return value;
}

function findDividerSubmodeOptions(surface) {
    const matches = [];

    const visit = (value, path = '', depth = 0) => {
        const raw = unwrapVueValue(value);
        if (Array.isArray(raw)) {
            const values = raw.map((item) => String(unwrapVueValue(item)?.value ?? unwrapVueValue(item)?.id ?? ''));
            const labels = raw.map((item) => String(unwrapVueValue(item)?.label ?? unwrapVueValue(item)?.name ?? ''));
            const keyLooksLikeDividerMode = /divider/iu.test(path) && /mode/iu.test(path);
            const hasDividerSubmodeLabels = labels.includes('VR調整なし') && labels.includes('VR調整あり');

            if (keyLooksLikeDividerMode && hasDividerSubmodeLabels) {
                matches.push({ path, values, labels });
            }
            return;
        }

        if (!raw || typeof raw !== 'object' || depth >= 3) return;
        Object.entries(raw).forEach(([key, nestedValue]) => {
            visit(nestedValue, path ? `${path}.${key}` : key, depth + 1);
        });
    };

    visit(surface);
    return matches;
}

assert.equal(parseTarget('4.7kΩ', 'R'), 4700);
assert.equal(parseTarget('10k', 'R'), 10000);
assert.ok(Math.abs(parseTarget('4.7u', 'C') - 4.7e-6) < 1e-18);
assert.ok(Math.abs(parseTarget('100n', 'C') - 100e-9) < 1e-18);
assert.ok(Math.abs(parseTarget('100nF', 'C') - 100e-9) < 1e-18);
assert.equal(parseTarget('3.3V', 'V'), 3.3);
assert.equal(parseTarget('2500mV', 'V'), 2.5);
assert.equal(parseTarget('50%', 'divider'), 0.5);
assert.equal(formatResistance(4700), '4.7kΩ');
assert.equal(formatCurrent(0), '0A');
assert.equal(formatCurrent(0.001), '1mA');
assert.equal(formatPower(0.005), '5mW');
assert.deepEqual(normalizeCustomValues('10k, 4.7k', 'R'), [10000, 4700]);
assert.ok(Math.abs(normalizeCustomValues('4.7u, 100n', 'C')[0] - 4.7e-6) < 1e-18);
assert.ok(Math.abs(normalizeCustomValues('4.7u, 100n', 'C')[1] - 100e-9) < 1e-18);
assert.deepEqual(normalizeCustomValues('10k', 'divider'), [10000]);

const appSurface = setupResistanceCalc();
const topLevelModes = unwrapVueValue(appSurface.modeOptions);
const topLevelModeValues = topLevelModes.map((mode) => mode.value);
const topLevelModeLabels = topLevelModes.map((mode) => mode.label);
assert.equal(topLevelModes.length, 3, 'Top-level tool tabs must be Network, Divider, and Variable Resistor only.');
assert.ok(topLevelModeValues.includes('network'), 'Top-level tabs must include network mode.');
assert.ok(topLevelModeValues.includes('divider'), 'Top-level tabs must include divider mode.');
assert.ok(topLevelModeValues.includes('variable'), 'Top-level tabs must include variable resistor mode.');
assert.ok(topLevelModeLabels.includes('ネットワーク探索'), 'Top-level tabs must include ネットワーク探索.');
assert.ok(topLevelModeLabels.includes('分圧'), 'Top-level tabs must include 分圧.');
assert.ok(topLevelModeLabels.includes('可変抵抗'), 'Top-level tabs must include 可変抵抗.');
assert.equal(
    topLevelModes.some((mode) => mode.value === 'divider-variable'),
    false,
    'Divider VR must not be exposed as a top-level mode value.',
);
assert.equal(
    topLevelModes.some((mode) => mode.label === '分圧VR' || /divider\s*vr/iu.test(mode.label)),
    false,
    'Divider VR must not be exposed as a top-level mode label.',
);
assert.ok(
    findDividerSubmodeOptions(appSurface).length > 0,
    'Divider UI must expose VR adjustment off/on as divider submodes.',
);
const networkPartTypeOptions = unwrapVueValue(appSurface.networkPartTypeOptions ?? appSurface.partTypeOptions);
assert.equal(
    networkPartTypeOptions.some((type) => type.value === 'divider' || type.label === '分圧'),
    false,
    'Network exploration part-type options must be resistor/capacitor only.',
);
assert.equal(typeof appSurface.setLoadResistanceInfinite, 'function');
assert.equal(typeof appSurface.setLoadCurrentZero, 'function');
const fixedDividerLoadState = { load_type: 'current', load_resistance_raw: '10k', load_current_raw: '1mA' };
appSurface.setLoadResistanceInfinite(fixedDividerLoadState);
assert.equal(fixedDividerLoadState.load_type, 'resistance');
assert.equal(fixedDividerLoadState.load_resistance_raw, '∞');
appSurface.setLoadCurrentZero(fixedDividerLoadState);
assert.equal(fixedDividerLoadState.load_type, 'current');
assert.equal(fixedDividerLoadState.load_current_raw, '0');
const vrDividerLoadState = { load_type: 'current', load_resistance_raw: '4.7k', load_current_raw: '500uA' };
appSurface.setLoadResistanceInfinite(vrDividerLoadState);
assert.equal(vrDividerLoadState.load_type, 'resistance');
assert.equal(vrDividerLoadState.load_resistance_raw, '∞');
appSurface.setLoadCurrentZero(vrDividerLoadState);
assert.equal(vrDividerLoadState.load_type, 'current');
assert.equal(vrDividerLoadState.load_current_raw, '0');

const dividerVoltageRatio = dividerRatioFromVoltages('3.3', '2.5');
assert.equal(dividerVoltageRatio.valid, true);
assert.ok(Math.abs(dividerVoltageRatio.ratio - 0.7575757575757576) < 1e-12);

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

const dividerVariableBase = {
    input_voltage_raw: '5',
    output_low_raw: '1',
    output_high_raw: '3',
    nominal_pot_raw: '10k',
    fixed_source: 'custom',
    fixed_custom_values: '10k, 5k',
    pot_source: 'custom',
    pot_custom_values: '10k',
    endpoint_tolerance_pct: 0,
    load_type: 'resistance',
    load_resistance_raw: '∞',
    load_current_raw: '0',
};
const dividerVariableNoLoad = calculateVariableDivider(dividerVariableBase);
assert.equal(dividerVariableNoLoad.valid, true);
assert.equal(dividerVariableNoLoad.bestCandidate.status, 'check');
assert.equal(dividerVariableNoLoad.bestCandidate.verdict, 'CHECK');
assert.equal(dividerVariableNoLoad.bestCandidate.pot, 10000);
assert.equal(dividerVariableNoLoad.bestCandidate.low, 1);
assert.equal(dividerVariableNoLoad.bestCandidate.high, 3);
assert.ok(Math.abs(dividerVariableNoLoad.bestCandidate.sourceCurrent - 0.0002) < 1e-12);
assert.ok(Math.abs(dividerVariableNoLoad.bestCandidate.topPower - 0.0004) < 1e-12);
assert.ok(Math.abs(dividerVariableNoLoad.bestCandidate.potPower - 0.0004) < 1e-12);
assert.ok(Math.abs(dividerVariableNoLoad.bestCandidate.bottomPower - 0.0002) < 1e-12);
assert.equal(dividerVariableNoLoad.bestCandidate.sourceCurrentDisplay, '200uA');
assert.equal(dividerVariableNoLoad.bestCandidate.topPowerDisplay, '400uW');
assert.equal(dividerVariableNoLoad.bestCandidate.potPowerDisplay, '400uW');
assert.equal(dividerVariableNoLoad.bestCandidate.bottomPowerDisplay, '200uW');
assert.ok(dividerVariableNoLoad.bestCandidate.tags.includes('無負荷分圧'));

const dividerVariableRatioRange = calculateVariableDivider({
    ...dividerVariableBase,
    output_mode: 'ratio',
    output_low_raw: '',
    output_high_raw: '',
    output_low_ratio_raw: '20%',
    output_high_ratio_raw: '60%',
});
assert.equal(dividerVariableRatioRange.valid, true);
assert.equal(dividerVariableRatioRange.requirement.outputLow, 1);
assert.equal(dividerVariableRatioRange.requirement.outputHigh, 3);
assert.equal(dividerVariableRatioRange.bestCandidate.pot, 10000);

const dividerVariableTotalRange = calculateVariableDivider({
    ...dividerVariableBase,
    fixed_custom_values: '5k, 10k, 20k',
    total_res_min_raw: '30k',
});
assert.equal(dividerVariableTotalRange.valid, true);
assert.ok(dividerVariableTotalRange.bestCandidate.total >= 30000);

const dividerVariableNominalPotLocked = calculateVariableDivider({
    ...dividerVariableBase,
    fixed_custom_values: '100, 200, 5k, 10k',
    pot_source: 'custom',
    pot_custom_values: '200, 10k',
});
assert.equal(dividerVariableNominalPotLocked.valid, true);
assert.equal(
    dividerVariableNominalPotLocked.bestCandidate.pot,
    10000,
    'nominal_pot_raw must be treated as the VR value to use, not as a loose preference.',
);
assert.notEqual(dividerVariableNominalPotLocked.bestCandidate.pot, 200);

const dividerVariableResistiveLoad = calculateVariableDivider({
    ...dividerVariableBase,
    load_resistance_raw: '10k',
});
assert.equal(dividerVariableResistiveLoad.valid, true);
assert.ok(dividerVariableResistiveLoad.bestCandidate.low < dividerVariableNoLoad.bestCandidate.low);
assert.ok(dividerVariableResistiveLoad.bestCandidate.high < dividerVariableNoLoad.bestCandidate.high);
assert.ok(dividerVariableResistiveLoad.bestCandidate.tags.includes('抵抗負荷込み'));

const dividerVariableCurrentLoad = calculateVariableDivider({
    ...dividerVariableBase,
    load_type: 'current',
    load_current_raw: '1mA',
});
assert.equal(dividerVariableCurrentLoad.valid, true);
assert.ok(Math.abs(dividerVariableCurrentLoad.requirement.load.current - 0.001) < 1e-12);
assert.ok(dividerVariableCurrentLoad.bestCandidate.low < dividerVariableNoLoad.bestCandidate.low);
assert.ok(dividerVariableCurrentLoad.bestCandidate.high < dividerVariableNoLoad.bestCandidate.high);
assert.ok(dividerVariableCurrentLoad.bestCandidate.tags.includes('電流負荷込み'));

console.log('resistance calc assertions passed');
