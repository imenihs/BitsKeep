/**
 * 抵抗/容量ネットワーク探索ツール（SCR-011）
 * - 抵抗/容量/分圧の候補探索
 * - 可変抵抗 + 固定抵抗の標準値候補選定
 * - VR付き分圧の出力電圧範囲候補選定
 */
import {
    formatCapacitance as formatEngineeringCapacitance,
    formatCurrent as formatEngineeringCurrent,
    formatPower as formatEngineeringPower,
    formatResistance as formatEngineeringResistance,
    formatVoltage as formatEngineeringVoltage,
    normalizeEngineeringText,
    parsePartValue,
} from '../../utils/engineeringUnits.js';

export const PART_TYPE_OPTIONS = [
    { value: 'R', label: '抵抗' },
    { value: 'C', label: '容量' },
];
export const PART_TYPE_LABELS = {
    R: '抵抗',
    C: '容量',
    divider: '分圧',
};
export const MODE_OPTIONS = [
    { value: 'network', label: 'ネットワーク探索' },
    { value: 'divider', label: '分圧' },
    { value: 'variable', label: '可変抵抗' },
];
export const DIVIDER_MODE_OPTIONS = [
    { value: 'fixed', label: 'VR調整なし' },
    { value: 'variable', label: 'VR調整あり' },
];
export const DIVIDER_TARGET_MODE_OPTIONS = [
    { value: 'ratio', label: '比率' },
    { value: 'voltage', label: 'Vin/Vout' },
];
export const LOAD_TYPE_OPTIONS = [
    { value: 'resistance', label: '抵抗負荷' },
    { value: 'current', label: '電流負荷' },
];
export const SERIES_OPTIONS = ['E6', 'E12', 'E24', 'E48', 'E96', 'custom'];
export const VARIABLE_FIXED_SOURCE_OPTIONS = ['E12', 'E24', 'E48', 'E96', 'custom'];
export const VARIABLE_POT_SOURCE_OPTIONS = ['vr-common', 'E6', 'E12', 'custom'];
export const VARIABLE_REFERENCE_POSITION_OPTIONS = [
    { value: 'upper', label: '上限基準' },
    { value: 'center', label: '中心基準' },
    { value: 'lower', label: '下限基準' },
];
export const CIRCUIT_OPTIONS = [
    { value: 'series', label: '直列' },
    { value: 'parallel', label: '並列' },
    { value: 'mixed', label: '混在' },
];
const E_SERIES_BASES = {
    E6: [1, 1.5, 2.2, 3.3, 4.7, 6.8],
    E12: [1, 1.2, 1.5, 1.8, 2.2, 2.7, 3.3, 3.9, 4.7, 5.6, 6.8, 8.2],
    E24: [1, 1.1, 1.2, 1.3, 1.5, 1.6, 1.8, 2, 2.2, 2.4, 2.7, 3, 3.3, 3.6, 3.9, 4.3, 4.7, 5.1, 5.6, 6.2, 6.8, 7.5, 8.2, 9.1],
};
const COMMON_VR_VALUES = [100, 200, 500, 1000, 2000, 5000, 10000, 20000, 50000, 100000, 200000, 500000, 1000000];

/**
 * 目的: 画面入力の抵抗値・容量値・電圧・分圧比を計算用の基底数値へ変換する。
 * 機能: 共通単位パーサで解釈し、未対応表記は対象種別ごとの単位分岐で補完する。
 * 入力: `raw` は利用者入力文字列、`partType` は `R`、`C`、`V`、`divider`。
 * 出力: 変換できた数値。空文字や不正値はnull。
 * 動作条件: 分圧比の百分率は0.5のような比率へ変換し、電圧/抵抗/容量はSI接頭語を基底単位へ戻す。
 * 副作用: なし。
 */
export function parseTarget(raw, partType) {
    const source = normalizeEngineeringText(raw);
    if (!source) return null;

    if (partType === 'divider') {
        const match = source.match(/^([+-]?(?:\d+(?:\.\d*)?|\.\d+)(?:e[+-]?\d+)?)%$/iu);
        const value = parsePartValue(source, partType);
        if (match) return value;
        if (value !== null) return value;
        const numeric = Number(source);
        return Number.isFinite(numeric) ? numeric : null;
    }

    const value = parsePartValue(source, partType);
    if (value !== null) return value;

    const match = source.match(/^([+-]?(?:\d+(?:\.\d*)?|\.\d+)(?:e[+-]?\d+)?)\s*([A-Za-zΩ%]*)$/u);
    if (!match) {
        const numeric = Number(source);
        return Number.isFinite(numeric) ? numeric : null;
    }

    const numeric = Number(match[1]);
    const unit = match[2] ?? '';
    if (!Number.isFinite(numeric)) return null;

    if (partType === 'divider') {
        if (unit === '%') return numeric / 100;
        return numeric;
    }

    if (partType === 'V') {
        if (unit === '' || /^V$/u.test(unit)) return numeric;
        if (/^(m|mV)$/u.test(unit)) return numeric * 1e-3;
        if (/^(u|uV)$/u.test(unit)) return numeric * 1e-6;
        if (/^(k|kV)$/u.test(unit)) return numeric * 1e3;
    }

    if (partType === 'R') {
        if (unit === '' || /^Ω$/iu.test(unit) || /^ohms?$/iu.test(unit)) return numeric;
        if (/^(k|kΩ|kohms?)$/iu.test(unit)) return numeric * 1e3;
        if (/^(M|MΩ|M[oO]hms?|meg|megohms?)$/u.test(unit)) return numeric * 1e6;
        if (/^(m|mΩ)$/u.test(unit)) return numeric * 1e-3;
        if (/^(u|uΩ)$/u.test(unit)) return numeric * 1e-6;
    }

    if (partType === 'C') {
        if (unit === '' || /^F$/u.test(unit)) return numeric;
        if (/^(p|pF)$/u.test(unit)) return numeric * 1e-12;
        if (/^(n|nF)$/u.test(unit)) return numeric * 1e-9;
        if (/^(u|uF)$/u.test(unit)) return numeric * 1e-6;
        if (/^(m|mF)$/u.test(unit)) return numeric * 1e-3;
    }

    return numeric;
}

/**
 * 目的: カスタム候補値欄を探索エンジンへ渡せる正の数値配列に整える。
 * 機能: カンマまたは改行で分割し、部品種別に応じて `parseTarget` で基底値化する。
 * 入力: `raw` は複数候補を含む文字列、`partType` は対象種別。
 * 出力: 0より大きい候補値だけを含む配列。
 * 動作条件: 分圧探索のカスタム値は抵抗候補として解釈する。
 * 副作用: なし。
 */
export function normalizeCustomValues(raw, partType) {
    const valuePartType = partType === 'divider' ? 'R' : partType;

    return String(raw ?? '')
        .split(/[,\n]+/u)
        .map((value) => parseTarget(value, valuePartType))
        .filter((value) => value !== null && value > 0);
}

// 目的: 抵抗/容量探索のtrim Numberを扱う。機能: 入力値を検証・整形し、画面または計算処理へ渡す。入力: 関数シグネチャの値。出力: 表示値、配列、オブジェクト、数値のいずれか。動作条件: 抵抗/容量探索の初期化後に呼び出す。副作用: なし。
export function trimNumber(value, digits = 4) {
    if (!Number.isFinite(value)) return '-';
    return Number(value.toPrecision(digits)).toString();
}

// 目的: 抵抗/容量探索のround Significantを扱う。機能: 入力値を検証・整形し、画面または計算処理へ渡す。入力: 関数シグネチャの値。出力: 処理結果またはなし。動作条件: 抵抗/容量探索の初期化後に呼び出す。副作用: なし。
function roundSignificant(value, digits = 3) {
    if (!Number.isFinite(value) || value === 0) return 0;
    return Number(value.toPrecision(digits));
}

// 目的: 抵抗/容量探索のformat Resistanceを扱う。機能: 入力値を検証・整形し、画面または計算処理へ渡す。入力: 関数シグネチャの値。出力: 表示値、配列、オブジェクト、数値のいずれか。動作条件: 抵抗/容量探索の初期化後に呼び出す。副作用: なし。
export function formatResistance(value) {
    return formatEngineeringResistance(value);
}

// 目的: 抵抗/容量探索のformat Capacitanceを扱う。機能: 入力値を検証・整形し、画面または計算処理へ渡す。入力: 関数シグネチャの値。出力: 表示値、配列、オブジェクト、数値のいずれか。動作条件: 抵抗/容量探索の初期化後に呼び出す。副作用: なし。
export function formatCapacitance(value) {
    return formatEngineeringCapacitance(value);
}

// 目的: 抵抗/容量探索のformat Voltageを扱う。機能: 入力値を検証・整形し、画面または計算処理へ渡す。入力: 関数シグネチャの値。出力: 表示値、配列、オブジェクト、数値のいずれか。動作条件: 抵抗/容量探索の初期化後に呼び出す。副作用: なし。
export function formatVoltage(value) {
    return formatEngineeringVoltage(value);
}

// 目的: 抵抗/容量探索のformat Currentを扱う。機能: 入力値を検証・整形し、画面または計算処理へ渡す。入力: 関数シグネチャの値。出力: 表示値、配列、オブジェクト、数値のいずれか。動作条件: 抵抗/容量探索の初期化後に呼び出す。副作用: なし。
export function formatCurrent(value) {
    return formatEngineeringCurrent(value);
}

// 目的: 抵抗/容量探索のformat Powerを扱う。機能: 入力値を検証・整形し、画面または計算処理へ渡す。入力: 関数シグネチャの値。出力: 表示値、配列、オブジェクト、数値のいずれか。動作条件: 抵抗/容量探索の初期化後に呼び出す。副作用: なし。
export function formatPower(value) {
    return formatEngineeringPower(value);
}

// 目的: 抵抗/容量探索のformat Target Valueを扱う。機能: 入力値を検証・整形し、画面または計算処理へ渡す。入力: 関数シグネチャの値。出力: 表示値、配列、オブジェクト、数値のいずれか。動作条件: 抵抗/容量探索の初期化後に呼び出す。副作用: なし。
export function formatTargetValue(value, partType) {
    if (value === null) return '-';
    if (partType === 'divider') return `${trimNumber(value * 100)}%`;
    if (partType === 'V') return formatVoltage(value);
    return partType === 'C' ? formatCapacitance(value) : formatResistance(value);
}

// 目的: 抵抗/容量探索のparse Currentを扱う。機能: 入力値を検証・整形し、画面または計算処理へ渡す。入力: 関数シグネチャの値。出力: 表示値、配列、オブジェクト、数値のいずれか。動作条件: 抵抗/容量探索の初期化後に呼び出す。副作用: なし。
function parseCurrent(raw) {
    const source = normalizeEngineeringText(raw);
    if (!source) return null;

    const match = source.match(/^([+-]?(?:\d+(?:\.\d*)?|\.\d+)(?:e[+-]?\d+)?)\s*([A-Za-z]*)$/u);
    if (!match) {
        const numeric = Number(source);
        return Number.isFinite(numeric) ? numeric : null;
    }

    const value = Number(match[1]);
    const unit = match[2] ?? '';
    if (!Number.isFinite(value)) return null;
    if (unit === '' || /^A$/u.test(unit)) return value;
    if (/^(m|mA)$/u.test(unit)) return value * 1e-3;
    if (/^(u|uA)$/u.test(unit)) return value * 1e-6;
    if (/^(n|nA)$/u.test(unit)) return value * 1e-9;

    return value;
}

// 目的: 抵抗/容量探索のparse Load Resistanceを扱う。機能: 入力値を検証・整形し、画面または計算処理へ渡す。入力: 関数シグネチャの値。出力: 表示値、配列、オブジェクト、数値のいずれか。動作条件: 抵抗/容量探索の初期化後に呼び出す。副作用: なし。
function parseLoadResistance(raw) {
    const source = normalizeEngineeringText(raw);
    if (!source || /^(∞|inf|infinity|open|none|無限大|無限)$/iu.test(source)) {
        return Infinity;
    }

    return parseTarget(source, 'R');
}

// 目的: 抵抗/容量探索のparallel Pairを扱う。機能: 入力値を検証・整形し、画面または計算処理へ渡す。入力: 関数シグネチャの値。出力: 処理結果またはなし。動作条件: 抵抗/容量探索の初期化後に呼び出す。副作用: Vue状態、localStorage、DOM、HTTP通信のいずれかを更新する場合がある。
function parallelPair(a, b) {
    if (a <= 0 || b <= 0) return 0;
    if (!Number.isFinite(a)) return b;
    if (!Number.isFinite(b)) return a;

    return 1 / ((1 / a) + (1 / b));
}

/**
 * 目的: 分圧計算で使う負荷条件を抵抗負荷または電流負荷として正規化する。
 * 機能: 抵抗負荷は∞表記を開放扱いにし、電流負荷は負値を0Aへ丸める。
 * 入力: `raw` は load_type と load_resistance_raw/load_current_raw を持つフォーム状態。
 * 出力: `{ type, current, resistance, valid, display }` の負荷設定。
 * 動作条件: 未指定の抵抗負荷は無負荷、未指定の電流負荷は0Aとして扱う。
 * 副作用: なし。
 */
export function dividerLoad(raw) {
    const type = raw.load_type || 'resistance';
    if (type === 'current') {
        const parsedCurrent = parseCurrent(raw.load_current_raw ?? '0');
        const current = parsedCurrent ?? 0;

        return {
            type,
            current: Math.max(0, current),
            resistance: Infinity,
            valid: parsedCurrent !== null && current >= 0,
            display: formatCurrent(Math.max(0, current)),
        };
    }

    const resistance = parseLoadResistance(raw.load_resistance_raw ?? '∞');

    return {
        type: 'resistance',
        current: 0,
        resistance,
        valid: resistance !== null && resistance >= 0,
        display: resistance === Infinity ? '∞Ω' : formatResistance(resistance),
    };
}
// 目的: 抵抗/容量探索のloaded Divider Outputを扱う。機能: 入力値を検証・整形し、画面または計算処理へ渡す。入力: 関数シグネチャの値。出力: 処理結果またはなし。動作条件: 抵抗/容量探索の初期化後に呼び出す。副作用: Vue状態、localStorage、DOM、HTTP通信のいずれかを更新する場合がある。
export function loadedDividerOutput({ inputVoltage, topResistance, bottomResistance, load }) {
    if (!Number.isFinite(inputVoltage) || inputVoltage <= 0 || topResistance < 0 || bottomResistance < 0) {
        return { voltage: NaN, ratio: NaN, sourceCurrent: NaN, outputCurrent: NaN };
    }

    if (load.type === 'current') {
        const total = topResistance + bottomResistance;
        if (total <= 0) return { voltage: NaN, ratio: NaN, sourceCurrent: NaN, outputCurrent: load.current };
        const noLoadVoltage = inputVoltage * (bottomResistance / total);
        const theveninResistance = parallelPair(topResistance, bottomResistance);
        const voltage = noLoadVoltage - (load.current * theveninResistance);
        return {
            voltage,
            ratio: voltage / inputVoltage,
            sourceCurrent: topResistance > 0 ? (inputVoltage - voltage) / topResistance : NaN,
            outputCurrent: load.current,
        };
    }

    const loadedBottom = load.resistance === Infinity
        ? bottomResistance
        : parallelPair(bottomResistance, load.resistance);
    const total = topResistance + loadedBottom;
    const voltage = total > 0 ? inputVoltage * (loadedBottom / total) : NaN;

    return {
        voltage,
        ratio: voltage / inputVoltage,
        sourceCurrent: total > 0 ? inputVoltage / total : NaN,
        outputCurrent: load.resistance > 0 && Number.isFinite(load.resistance) ? voltage / load.resistance : 0,
    };
}

// 目的: 抵抗/容量探索のbranch Currentを扱う。機能: 入力値を検証・整形し、画面または計算処理へ渡す。入力: 関数シグネチャの値。出力: 処理結果またはなし。動作条件: 抵抗/容量探索の初期化後に呼び出す。副作用: Vue状態、localStorage、DOM、HTTP通信のいずれかを更新する場合がある。
export function branchCurrent(voltage, resistance) {
    if (!Number.isFinite(voltage) || !Number.isFinite(resistance) || resistance <= 0) return NaN;
    return voltage / resistance;
}

// 目的: 抵抗/容量探索のresistor Powerを扱う。機能: 入力値を検証・整形し、画面または計算処理へ渡す。入力: 関数シグネチャの値。出力: 処理結果またはなし。動作条件: 抵抗/容量探索の初期化後に呼び出す。副作用: Vue状態、localStorage、DOM、HTTP通信のいずれかを更新する場合がある。
export function resistorPower(current, resistance) {
    if (!Number.isFinite(current) || !Number.isFinite(resistance) || resistance <= 0) return NaN;
    return current * current * resistance;
}

/**
 * 目的: Vin/Vout入力から分圧比ターゲットを算出する。
 * 機能: 電圧表記を基底Vへ変換し、Vout/Vinが0から1の範囲かを判定する。
 * 入力: `inputRaw` は入力電圧、`outputRaw` は目標出力電圧の文字列。
 * 出力: `{ input, output, ratio, valid }`。
 * 動作条件: VinとVoutは正の有限値で、VoutはVin未満であること。
 * 副作用: なし。
 */
export function dividerRatioFromVoltages(inputRaw, outputRaw) {
    const input = parseTarget(inputRaw, 'V');
    const output = parseTarget(outputRaw, 'V');
    const ratio = input && output !== null ? output / input : null;

    return {
        input,
        output,
        ratio,
        valid: Number.isFinite(input) && input > 0
            && Number.isFinite(output) && output > 0
            && Number.isFinite(ratio) && ratio > 0 && ratio < 1,
    };
}

// 目的: 抵抗/容量探索のseries Basesを扱う。機能: 入力値を検証・整形し、画面または計算処理へ渡す。入力: 関数シグネチャの値。出力: 処理結果またはなし。動作条件: 抵抗/容量探索の初期化後に呼び出す。副作用: Vue状態、localStorage、DOM、HTTP通信のいずれかを更新する場合がある。
function seriesBases(series) {
    if (E_SERIES_BASES[series]) return E_SERIES_BASES[series];
    const count = Number(String(series).replace('E', ''));
    if (!Number.isFinite(count) || count <= 0) return E_SERIES_BASES.E24;

    return Array.from({ length: count }, (_, index) => roundSignificant(10 ** (index / count), count >= 48 ? 3 : 2));
}

// 目的: 抵抗/容量探索のunique Sortedを扱う。機能: 入力値を検証・整形し、画面または計算処理へ渡す。入力: 関数シグネチャの値。出力: 処理結果またはなし。動作条件: 抵抗/容量探索の初期化後に呼び出す。副作用: Vue状態、localStorage、DOM、HTTP通信のいずれかを更新する場合がある。
function uniqueSorted(values) {
    return [...new Set(values.filter((value) => Number.isFinite(value) && value > 0).map((value) => roundSignificant(value, 6)))]
        .sort((a, b) => a - b);
}

// 目的: 抵抗/容量探索のgenerate Series Valuesを扱う。機能: 入力値を検証・整形し、画面または計算処理へ渡す。入力: 関数シグネチャの値。出力: 処理結果またはなし。動作条件: 抵抗/容量探索の初期化後に呼び出す。副作用: Vue状態、localStorage、DOM、HTTP通信のいずれかを更新する場合がある。
function generateSeriesValues(series, ideal, minFactor = 0.25, maxFactor = 4) {
    if (!Number.isFinite(ideal) || ideal <= 0) return [];

    const bases = seriesBases(series);
    const min = ideal * minFactor;
    const max = ideal * maxFactor;
    const minDecade = Math.floor(Math.log10(min)) - 1;
    const maxDecade = Math.ceil(Math.log10(max)) + 1;
    const values = [];

    for (let decade = minDecade; decade <= maxDecade; decade += 1) {
        const multiplier = 10 ** decade;
        bases.forEach((base) => {
            const value = roundSignificant(base * multiplier, 6);
            if (value >= min && value <= max) values.push(value);
        });
    }

    return uniqueSorted(values);
}

// 目的: 抵抗/容量探索のnearest Valuesを扱う。機能: 入力値を検証・整形し、画面または計算処理へ渡す。入力: 関数シグネチャの値。出力: 処理結果またはなし。動作条件: 抵抗/容量探索の初期化後に呼び出す。副作用: Vue状態、localStorage、DOM、HTTP通信のいずれかを更新する場合がある。
export function nearestValues(values, ideal, limit = 14) {
    if (!Number.isFinite(ideal) || ideal <= 0) return uniqueSorted(values).slice(0, limit);

    return uniqueSorted(values)
        .map((value) => ({
            value,
            distance: Math.abs(Math.log10(value / ideal)),
        }))
        .sort((a, b) => a.distance - b.distance || a.value - b.value)
        .slice(0, limit)
        .map((item) => item.value)
        .sort((a, b) => a - b);
}

// 目的: 抵抗/容量探索のsource Labelを扱う。機能: 入力値を検証・整形し、画面または計算処理へ渡す。入力: 関数シグネチャの値。出力: 処理結果またはなし。動作条件: 抵抗/容量探索の初期化後に呼び出す。副作用: Vue状態、localStorage、DOM、HTTP通信のいずれかを更新する場合がある。
export function sourceLabel(source, kind) {
    if (source === 'vr-common') return '標準VR値';
    if (source === 'custom') return kind === 'fixed' ? 'カスタム固定' : 'カスタムVR';
    return `${source}${kind === 'fixed' ? '固定' : 'VR'}`;
}

// 目的: 抵抗/容量探索のvariable Source Valuesを扱う。機能: 入力値を検証・整形し、画面または計算処理へ渡す。入力: 関数シグネチャの値。出力: 処理結果またはなし。動作条件: 抵抗/容量探索の初期化後に呼び出す。副作用: Vue状態、localStorage、DOM、HTTP通信のいずれかを更新する場合がある。
function variableSourceValues(source, ideal, customRaw, kind) {
    if (source === 'custom') return nearestValues(normalizeCustomValues(customRaw, 'R'), ideal, 20);
    if (source === 'vr-common') return nearestValues(COMMON_VR_VALUES, ideal, 14);
    return nearestValues(generateSeriesValues(source, ideal), ideal, kind === 'fixed' ? 16 : 14);
}

// 目的: 抵抗/容量探索のfixed Source Valuesを扱う。機能: 入力値を検証・整形し、画面または計算処理へ渡す。入力: 関数シグネチャの値。出力: 処理結果またはなし。動作条件: 抵抗/容量探索の初期化後に呼び出す。副作用: Vue状態、localStorage、DOM、HTTP通信のいずれかを更新する場合がある。
export function fixedSourceValues(source, ideals, customRaw) {
    const idealValues = uniqueSorted(ideals);
    if (source === 'custom') {
        return nearestValues(normalizeCustomValues(customRaw, 'R'), idealValues[0] ?? 1, 30);
    }

    const values = idealValues.flatMap((ideal) => generateSeriesValues(source, ideal, 0.25, 4));
    return uniqueSorted(values);
}

// 目的: 抵抗/容量探索のnearly Equalを扱う。機能: 入力値を検証・整形し、画面または計算処理へ渡す。入力: 関数シグネチャの値。出力: 処理結果またはなし。動作条件: 抵抗/容量探索の初期化後に呼び出す。副作用: Vue状態、localStorage、DOM、HTTP通信のいずれかを更新する場合がある。
export function nearlyEqual(a, b, rel = 1e-6) {
    if (!Number.isFinite(a) || !Number.isFinite(b)) return false;
    return Math.abs(a - b) <= Math.max(Math.abs(a), Math.abs(b), 1) * rel;
}

// 目的: 抵抗/容量探索のendpoint Errorを扱う。機能: 入力値を検証・整形し、画面または計算処理へ渡す。入力: 関数シグネチャの値。出力: 処理結果またはなし。動作条件: 抵抗/容量探索の初期化後に呼び出す。副作用: Vue状態、localStorage、DOM、HTTP通信のいずれかを更新する場合がある。
function endpointError(actual, target) {
    if (!Number.isFinite(actual) || !Number.isFinite(target) || target <= 0) return Infinity;
    return ((actual - target) / target) * 100;
}

// 目的: 抵抗/容量探索のendpoint Rangeを扱う。機能: 入力値を検証・整形し、画面または計算処理へ渡す。入力: 関数シグネチャの値。出力: 処理結果またはなし。動作条件: 抵抗/容量探索の初期化後に呼び出す。副作用: Vue状態、localStorage、DOM、HTTP通信のいずれかを更新する場合がある。
function endpointRange(circuit, fixed, pot) {
    if (circuit === 'parallel') {
        if (fixed <= 0 || pot <= 0) return { low: 0, high: fixed };
        return {
            low: 1 / ((1 / fixed) + (1 / pot)),
            high: fixed,
        };
    }

    return {
        low: fixed,
        high: fixed + pot,
    };
}

// 目的: 抵抗/容量探索のformat Ohm Deltaを扱う。機能: 入力値を検証・整形し、画面または計算処理へ渡す。入力: 関数シグネチャの値。出力: 表示値、配列、オブジェクト、数値のいずれか。動作条件: 抵抗/容量探索の初期化後に呼び出す。副作用: なし。
function formatOhmDelta(value) {
    if (!Number.isFinite(value)) return '-';
    return `${value >= 0 ? '+' : ''}${formatResistance(Math.abs(value))}`;
}

// 目的: 抵抗/容量探索のformat Percent Numberを扱う。機能: 入力値を検証・整形し、画面または計算処理へ渡す。入力: 関数シグネチャの値。出力: 表示値、配列、オブジェクト、数値のいずれか。動作条件: 抵抗/容量探索の初期化後に呼び出す。副作用: なし。
export function formatPercentNumber(value, digits = 4) {
    if (!Number.isFinite(value)) return '-';
    return `${trimNumber(value, digits)}%`;
}

// 目的: 抵抗/容量探索のnormalize Tolerance Pctを扱う。機能: 入力値を検証・整形し、画面または計算処理へ渡す。入力: 関数シグネチャの値。出力: 表示値、配列、オブジェクト、数値のいずれか。動作条件: 抵抗/容量探索の初期化後に呼び出す。副作用: なし。
export function normalizeTolerancePct(value) {
    return Math.max(0, Math.min(100, Number(value) || 0));
}

/**
 * 目的: 抵抗候補の許容差が端点値へ与える振れ幅を見積もる。
 * 機能: 独立ばらつきのRSS目安と、全組み合わせコーナーの最小/最大を同時に計算する。
 * 入力: `items` は公称値と許容差、`nominal` は公称評価値、`evaluate` は候補値配列から評価値を返す関数。
 * 出力: 表示文字列を含む範囲オブジェクト。許容差なしまたは評価不能ならnull。
 * 動作条件: item数が増えるとコーナー組み合わせが指数的に増えるため、固定/VR/上下抵抗程度の小規模評価で使う。
 * 副作用: なし。
 */
export function toleranceRangeForValues(items, nominal, evaluate, formatter) {
    if (!items.some((item) => normalizeTolerancePct(item.tolerancePct) > 0) || !Number.isFinite(nominal)) {
        return null;
    }

    const epsilon = 1e-6;
    const sumSquares = items.reduce((sum, item, index) => {
        const value = Number(item.value);
        const tolerance = normalizeTolerancePct(item.tolerancePct) / 100;
        if (!Number.isFinite(value) || value <= 0 || tolerance <= 0) return sum;
        const up = items.map((entry) => Number(entry.value));
        const down = items.map((entry) => Number(entry.value));
        up[index] = value * (1 + epsilon);
        down[index] = value * (1 - epsilon);
        const upActual = evaluate(up);
        const downActual = evaluate(down);
        if (!Number.isFinite(upActual) || !Number.isFinite(downActual)) return sum;
        const derivative = (upActual - downActual) / (2 * value * epsilon);
        return sum + ((derivative * value * tolerance) ** 2);
    }, 0);
    const spread = Math.sqrt(sumSquares);
    const rssLow = nominal - spread;
    const rssHigh = nominal + spread;

    const corners = items.reduce((list, item) => {
        const tolerance = normalizeTolerancePct(item.tolerancePct) / 100;
        const value = Number(item.value);
        const options = tolerance > 0 ? [value * (1 - tolerance), value * (1 + tolerance)] : [value];
        return list.flatMap((corner) => options.map((option) => [...corner, option]));
    }, [[]]);
    const cornerValues = corners
        .map((corner) => evaluate(corner))
        .filter((value) => Number.isFinite(value));
    if (cornerValues.length === 0) return null;

    const cornerLow = Math.min(...cornerValues);
    const cornerHigh = Math.max(...cornerValues);

    return {
        rssLow,
        rssHigh,
        rssSpread: spread,
        rssLowDisplay: formatter(rssLow),
        rssHighDisplay: formatter(rssHigh),
        rssSpreadDisplay: formatter(Math.abs(spread)),
        rssRangeDisplay: `${formatter(rssLow)} 〜 ${formatter(rssHigh)}`,
        cornerLow,
        cornerHigh,
        cornerLowDisplay: formatter(cornerLow),
        cornerHighDisplay: formatter(cornerHigh),
        cornerRangeDisplay: `${formatter(cornerLow)} 〜 ${formatter(cornerHigh)}`,
    };
}

// 目的: 抵抗/容量探索のvariable Candidate Toleranceを扱う。機能: 入力値を検証・整形し、画面または計算処理へ渡す。入力: 関数シグネチャの値。出力: 処理結果またはなし。動作条件: 抵抗/容量探索の初期化後に呼び出す。副作用: Vue状態、localStorage、DOM、HTTP通信のいずれかを更新する場合がある。
function variableCandidateTolerance(circuit, fixed, pot, fixedTolerancePct, potTolerancePct) {
    const fixedTol = normalizeTolerancePct(fixedTolerancePct);
    const potTol = normalizeTolerancePct(potTolerancePct);
    if (fixedTol <= 0 && potTol <= 0) return {};

    const items = [
        { value: fixed, tolerancePct: fixedTol },
        { value: pot, tolerancePct: potTol },
    ];
    const nominal = endpointRange(circuit, fixed, pot);
    const lowRange = toleranceRangeForValues(
        items,
        nominal.low,
        ([candidateFixed, candidatePot]) => endpointRange(circuit, candidateFixed, candidatePot).low,
        formatResistance,
    );
    const highRange = toleranceRangeForValues(
        items,
        nominal.high,
        ([candidateFixed, candidatePot]) => endpointRange(circuit, candidateFixed, candidatePot).high,
        formatResistance,
    );
    if (!lowRange || !highRange) return {};

    return {
        fixedTolerancePct: fixedTol,
        potTolerancePct: potTol,
        toleranceDisplay: `固定 ±${formatPercentNumber(fixedTol)} / VR ±${formatPercentNumber(potTol)}`,
        rssLowEndpointRangeDisplay: lowRange.rssRangeDisplay,
        rssHighEndpointRangeDisplay: highRange.rssRangeDisplay,
        rssAdjustableRangeDisplay: `${lowRange.rssLowDisplay} 〜 ${highRange.rssHighDisplay}`,
        cornerLowEndpointRangeDisplay: lowRange.cornerRangeDisplay,
        cornerHighEndpointRangeDisplay: highRange.cornerRangeDisplay,
        cornerAdjustableRangeDisplay: `${lowRange.cornerLowDisplay} 〜 ${highRange.cornerHighDisplay}`,
    };
}

// 目的: 抵抗/容量探索のvariable Requirementを扱う。機能: 入力値を検証・整形し、画面または計算処理へ渡す。入力: 関数シグネチャの値。出力: 処理結果またはなし。動作条件: 抵抗/容量探索の初期化後に呼び出す。副作用: Vue状態、localStorage、DOM、HTTP通信のいずれかを更新する場合がある。
function variableRequirement(raw) {
    const reference = parseTarget(raw.reference_raw ?? raw.total_raw, 'R') ?? 0;
    const span = raw.span_mode === 'percent'
        ? reference * ((Number(String(raw.span_raw).replace('%', '')) || 0) / 100)
        : (parseTarget(raw.span_raw, 'R') ?? 0);
    const position = raw.reference_position || 'upper';
    let low = 0;
    let high = 0;

    if (position === 'center') {
        low = reference - (span / 2);
        high = reference + (span / 2);
    } else if (position === 'lower') {
        low = reference;
        high = reference + span;
    } else {
        low = reference - span;
        high = reference;
    }

    return {
        reference,
        span,
        position,
        low,
        high,
        valid: reference > 0 && span > 0 && low > 0 && high > low,
    };
}

// 目的: 抵抗/容量探索のmake Variable Candidateを扱う。機能: 入力値を検証・整形し、画面または計算処理へ渡す。入力: 関数シグネチャの値。出力: 処理結果またはなし。動作条件: 抵抗/容量探索の初期化後に呼び出す。副作用: Vue状態、localStorage、DOM、HTTP通信のいずれかを更新する場合がある。
function makeVariableCandidate({ circuit, fixed, pot, fixedSource, potSource, targetLow, targetHigh, idealFixed, idealPot, tolerancePct, fixedTolerancePct = 0, potTolerancePct = 0 }) {
    const range = endpointRange(circuit, fixed, pot);
    const lowErrorPct = endpointError(range.low, targetLow);
    const highErrorPct = endpointError(range.high, targetHigh);
    const targetSpan = Math.max(targetHigh - targetLow, Number.EPSILON);
    const lowMargin = targetLow - range.low;
    const highMargin = range.high - targetHigh;
    const lowShortfall = Math.max(0, -lowMargin);
    const highShortfall = Math.max(0, -highMargin);
    const shortfall = lowShortfall + highShortfall;
    const excess = Math.max(0, lowMargin) + Math.max(0, highMargin);
    const shortfallPct = (shortfall / targetSpan) * 100;
    const excessPct = (excess / targetSpan) * 100;
    const allowedShortfall = Math.max(targetSpan * (tolerancePct / 100), 1e-9);
    const nearTargetRange = shortfall <= allowedShortfall;
    const coversTargetRange = shortfall <= 1e-9;
    const potAdjusted = !nearlyEqual(pot, idealPot);
    const fixedAdjusted = !nearlyEqual(fixed, idealFixed);

    return {
        fixed,
        pot,
        low: range.low,
        high: range.high,
        targetLow,
        targetHigh,
        fixedDisplay: formatResistance(fixed),
        potDisplay: formatResistance(pot),
        lowDisplay: formatResistance(range.low),
        highDisplay: formatResistance(range.high),
        targetLowDisplay: formatResistance(targetLow),
        targetHighDisplay: formatResistance(targetHigh),
        lowErrorPct,
        highErrorPct,
        maxEndpointErrorPct: shortfallPct,
        shortfallPct,
        excessPct,
        score: (coversTargetRange ? 0 : 100000) + shortfallPct * 100 + excessPct,
        lowErrorDisplay: `${lowErrorPct >= 0 ? '+' : ''}${trimNumber(lowErrorPct, 3)}%`,
        highErrorDisplay: `${highErrorPct >= 0 ? '+' : ''}${trimNumber(highErrorPct, 3)}%`,
        lowMarginDisplay: formatOhmDelta(lowMargin),
        highMarginDisplay: formatOhmDelta(highMargin),
        maxEndpointErrorDisplay: `${trimNumber(shortfallPct, 3)}%`,
        rangeMarginDisplay: coversTargetRange ? `${trimNumber(excessPct, 3)}%` : `不足 ${trimNumber(shortfallPct, 3)}%`,
        coversTargetRange,
        nearTargetRange,
        verdict: coversTargetRange ? 'CHECK' : 'WARN',
        status: coversTargetRange ? 'check' : 'warn',
        fixedSource,
        potSource,
        operatorDisplay: circuit === 'parallel' ? '||' : '+',
        tags: [
            sourceLabel(fixedSource, 'fixed'),
            sourceLabel(potSource, 'pot'),
            coversTargetRange ? '要求範囲包含' : '要求範囲不足',
            potAdjusted ? 'VR範囲側補正' : 'VR理想近傍',
            fixedAdjusted ? '固定抵抗再計算' : '固定抵抗理想近傍',
            coversTargetRange ? '要部品選定' : '採用不可',
            !coversTargetRange && nearTargetRange ? '不足許容内' : '',
            '公称値候補',
            fixedTolerancePct > 0 || potTolerancePct > 0 ? '許容差範囲表示' : '許容差未設定',
            '型番未選定',
            '購入/在庫未確認',
        ].filter(Boolean),
        ...variableCandidateTolerance(circuit, fixed, pot, fixedTolerancePct, potTolerancePct),
        expression: circuit === 'parallel'
            ? `${formatResistance(fixed)} || VR ${formatResistance(pot)} => ${formatResistance(range.low)} 〜 ${formatResistance(range.high)}`
            : `${formatResistance(fixed)} + VR ${formatResistance(pot)} => ${formatResistance(range.low)} 〜 ${formatResistance(range.high)}`,
    };
}

/**
 * 目的: ネットワーク探索APIレスポンスの包み方差異を画面用に吸収する。
 * 機能: `data` 直下またはpayload直下のresult/summary/warnings/next_actionsを同じ形へ揃える。
 * 入力: APIレスポンスdataまたは同等のオブジェクト。
 * 出力: `{ result, summary, warnings, nextActions }`。
 * 動作条件: 欠落フィールドは空値へ補完する。
 * 副作用: なし。
 */
export function normalizeNetworkResponse(payload) {
    const envelope = payload?.data ?? payload ?? {};
    const result = envelope?.result ?? null;
    return {
        result,
        summary: envelope?.summary ?? '',
        warnings: envelope?.warnings ?? [],
        nextActions: envelope?.next_actions ?? [],
    };
}

/**
 * 目的: 可変抵抗と固定抵抗で要求抵抗範囲を満たす標準値候補を作る。
 * 機能: 直列/並列の回路別に理想値を算出し、E系列/標準VR/カスタム値から近傍候補を採点する。
 * 入力: `raw` は基準抵抗、可変幅、回路、候補値ソース、許容差を含むフォーム状態。
 * 出力: 妥当性、要求範囲、理想値、候補リスト、最良候補、警告、式表示。
 * 動作条件: 基準値と可変幅が正で、要求下限が0より大きく上限が下限より大きいこと。
 * 副作用: なし。
 */
export function calculateVariable(raw) {
    const requirement = variableRequirement(raw);
    const { reference, span, low, high } = requirement;
    const tolerancePct = Math.max(0, Number(raw.endpoint_tolerance_pct) || 0);
    const fixedTolerancePct = normalizeTolerancePct(raw.fixed_tolerance_pct);
    const potTolerancePct = normalizeTolerancePct(raw.pot_tolerance_pct);
    const fixedSource = raw.fixed_source || 'E24';
    const potSource = raw.pot_source || 'vr-common';

    if (raw.circuit === 'parallel') {
        if (!requirement.valid) {
            return {
                valid: false,
                requirement,
                ideal: { fixed: reference, pot: 0, low, high },
                candidates: [],
                bestCandidate: null,
                warnings: ['入力値の組み合わせを確認してください'],
                expression: 'Rfixed || Rpot',
            };
        }
        const pot = 1 / ((1 / low) - (1 / high));
        const fixedValues = fixedSourceValues(fixedSource, [high], raw.fixed_custom_values);
        const candidates = fixedValues.flatMap((fixedValue) => {
            if (fixedValue <= low) return [];
            const potIdealForFixed = 1 / ((1 / low) - (1 / fixedValue));
            const potValues = variableSourceValues(potSource, potIdealForFixed, raw.pot_custom_values, 'pot');

            return potValues.map((potValue) => makeVariableCandidate({
                circuit: 'parallel',
                fixed: fixedValue,
                pot: potValue,
                fixedSource,
                potSource,
                targetLow: low,
                targetHigh: high,
                idealFixed: high,
                idealPot: potIdealForFixed,
                tolerancePct,
                fixedTolerancePct,
                potTolerancePct,
            }));
        })
            .sort((a, b) => a.score - b.score || a.fixed - b.fixed || a.pot - b.pot)
            .slice(0, 8);
        const bestCandidate = candidates.find((candidate) => candidate.status === 'check') ?? candidates[0] ?? null;
        return {
            valid: Number.isFinite(pot) && pot > 0,
            requirement,
            ideal: { fixed: high, pot, low, high },
            candidates,
            bestCandidate,
            warnings: candidates.length ? [] : ['候補値ソースに採用候補がありません'],
            expression: `${formatResistance(high)} || VR ${formatResistance(pot)} => ${formatResistance(low)} 〜 ${formatResistance(high)}`,
        };
    }

    const fixed = Math.max(0, high - span);
    const potValues = variableSourceValues(potSource, span, raw.pot_custom_values, 'pot');
    const candidates = potValues.flatMap((potValue) => {
        const fixedIdealForPot = high - potValue;
        const fixedValues = fixedSourceValues(
            fixedSource,
            [fixedIdealForPot, low, high - span],
            raw.fixed_custom_values,
        );

        return fixedValues.map((fixedValue) => makeVariableCandidate({
            circuit: 'series',
            fixed: fixedValue,
            pot: potValue,
            fixedSource,
            potSource,
            targetLow: low,
            targetHigh: high,
            idealFixed: fixedIdealForPot,
            idealPot: span,
            tolerancePct,
            fixedTolerancePct,
            potTolerancePct,
        }));
    })
        .sort((a, b) => a.score - b.score || a.fixed - b.fixed || a.pot - b.pot)
        .slice(0, 8);
    const bestCandidate = candidates.find((candidate) => candidate.status === 'check') ?? candidates[0] ?? null;

    return {
        valid: requirement.valid && fixed >= 0,
        requirement,
        ideal: { fixed, pot: span, low, high },
        candidates,
        bestCandidate,
        warnings: candidates.length ? [] : ['候補値ソースに採用候補がありません'],
        expression: `${formatResistance(fixed)} + VR ${formatResistance(span)} => ${formatResistance(fixed)} 〜 ${formatResistance(high)}`,
    };
}
