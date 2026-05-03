import assert from 'node:assert/strict';
import {
    formatCurrent,
    formatEngineeringValue,
    formatPower,
    formatResistance,
    formatSiValue,
    normalizeUnitLabel,
    parseEngineeringNumber,
    parseEngineeringNumberDetail,
    parsePartValue,
    prefixOptionsForUnit,
    prefixPolicyHelpForUnit,
    sanitizePrefixesForUnit,
    syncPrefixSelectionForUnit,
} from '../resources/js/utils/engineeringUnits.js';

/**
 * 浮動小数点の丸め誤差を許容して数値一致を検証する。
 * 入力は実測値・期待値・許容差で、失敗時はassert例外を投げる副作用がある。
 */
// 目的: フロントテストのassert Closeを扱う。機能: 入力値を検証・整形し、画面または計算処理へ渡す。入力: 関数シグネチャの値。出力: 処理結果またはなし。動作条件: フロントテストの初期化後に呼び出す。副作用: 失敗時にassert例外を投げる場合がある。
const assertClose = (actual, expected, tolerance = 1e-12) => {
    assert.ok(Math.abs(actual - expected) <= tolerance, `${actual} !== ${expected}`);
};

assert.equal(normalizeUnitLabel('KΩ'), 'kΩ');
assert.equal(normalizeUnitLabel('µF'), 'uF');
assert.equal(normalizeUnitLabel('MEGΩ'), 'MΩ');
assert.equal(normalizeUnitLabel('ohm'), 'Ω');
assertClose(parseEngineeringNumber('4.7u', null, 'F'), 4.7e-6);
assertClose(parseEngineeringNumber('512KiB'), 512 * 1024);
assertClose(parseEngineeringNumberDetail('3.3μV').value, 3.3e-6);
assert.equal(parseEngineeringNumberDetail('1MΩ').unit, 'Ω');
assertClose(parseEngineeringNumberDetail('1MEGΩ').value, 1e6);
assertClose(parsePartValue('4.7kΩ', 'R'), 4700);
assertClose(parsePartValue('100nF', 'C'), 100e-9);
assertClose(parsePartValue('2500mV', 'V'), 2.5);
assertClose(parsePartValue('50%', 'divider'), 0.5);
assert.equal(formatResistance(4700), '4.7kΩ');
assert.equal(formatCurrent(0.001), '1mA');
assert.equal(formatPower(0.005), '5mW');
assert.equal(formatEngineeringValue(0.0000047, 'F', ['u', 'n', 'p']), '4.7uF');
assert.equal(formatSiValue(0.000919, 'Wh'), '919.000 uWh');
assert.deepEqual(sanitizePrefixesForUnit(['Mi', 'Ki', 'm', ''], 'B'), ['Mi', 'Ki', '']);
assert.deepEqual(sanitizePrefixesForUnit(['Mi', 'k', 'u'], 'V'), ['k', 'u']);
assert.deepEqual(syncPrefixSelectionForUnit(['M', 'Mi', ''], 'B', 'Mi'), ['Mi', '']);
assert.deepEqual(syncPrefixSelectionForUnit(['Mi', 'M', ''], 'B', 'M'), ['M', '']);
assert.equal(prefixOptionsForUnit('B').some((item) => item.value === 'Mi'), true);
assert.equal(prefixOptionsForUnit('V').some((item) => item.value === 'Mi'), false);
assert.match(prefixPolicyHelpForUnit('B'), /IEC/u);
assert.match(prefixPolicyHelpForUnit('V', '値入力時'), /候補接頭辞/u);
