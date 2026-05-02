/**
 * 抵抗/容量ネットワーク探索ツール（SCR-011）
 * - 抵抗/容量/分圧の候補探索
 * - 可変抵抗 + 固定抵抗の標準値候補選定
 * - VR付き分圧の出力電圧範囲候補選定
 */
import { ref, reactive, computed } from 'vue';
import { api } from '../api.js';

const PART_TYPE_OPTIONS = [
    { value: 'R', label: '抵抗' },
    { value: 'C', label: '容量' },
];
const PART_TYPE_LABELS = {
    R: '抵抗',
    C: '容量',
    divider: '分圧',
};
const MODE_OPTIONS = [
    { value: 'network', label: 'ネットワーク探索' },
    { value: 'divider', label: '分圧' },
    { value: 'variable', label: '可変抵抗' },
];
const DIVIDER_MODE_OPTIONS = [
    { value: 'fixed', label: 'VR調整なし' },
    { value: 'variable', label: 'VR調整あり' },
];
const DIVIDER_TARGET_MODE_OPTIONS = [
    { value: 'ratio', label: '比率' },
    { value: 'voltage', label: 'Vin/Vout' },
];
const LOAD_TYPE_OPTIONS = [
    { value: 'resistance', label: '抵抗負荷' },
    { value: 'current', label: '電流負荷' },
];
const SERIES_OPTIONS = ['E6', 'E12', 'E24', 'E48', 'E96', 'custom'];
const VARIABLE_FIXED_SOURCE_OPTIONS = ['E12', 'E24', 'E48', 'E96', 'custom'];
const VARIABLE_POT_SOURCE_OPTIONS = ['vr-common', 'E6', 'E12', 'custom'];
const VARIABLE_REFERENCE_POSITION_OPTIONS = [
    { value: 'upper', label: '上限基準' },
    { value: 'center', label: '中心基準' },
    { value: 'lower', label: '下限基準' },
];
const CIRCUIT_OPTIONS = [
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

function normalizeRaw(raw) {
    return String(raw ?? '').trim()
        .replace(/,/g, '')
        .replace(/[Ω]/gu, 'Ω')
        .replace(/[µμ]/gu, 'u');
}

export function parseTarget(raw, partType) {
    const source = normalizeRaw(raw);
    if (!source) return null;

    const match = source.match(/^([+-]?(?:\d+(?:\.\d*)?|\.\d+)(?:e[+-]?\d+)?)\s*([A-Za-zΩ%]*)$/u);
    if (!match) {
        const numeric = Number(source);
        return Number.isFinite(numeric) ? numeric : null;
    }

    const value = Number(match[1]);
    const unit = match[2] ?? '';
    if (!Number.isFinite(value)) return null;

    if (partType === 'divider') {
        if (unit === '%') return value / 100;
        return value;
    }

    if (partType === 'V') {
        if (unit === '' || /^V$/u.test(unit)) return value;
        if (/^(m|mV)$/u.test(unit)) return value * 1e-3;
        if (/^(u|uV)$/u.test(unit)) return value * 1e-6;
        if (/^(k|kV)$/u.test(unit)) return value * 1e3;
    }

    if (partType === 'R') {
        if (unit === '' || /^Ω$/iu.test(unit) || /^ohms?$/iu.test(unit)) return value;
        if (/^(k|kΩ|kohms?)$/iu.test(unit)) return value * 1e3;
        if (/^(M|MΩ|M[oO]hms?|meg|megohms?)$/u.test(unit)) return value * 1e6;
        if (/^(m|mΩ)$/u.test(unit)) return value * 1e-3;
        if (/^(u|uΩ)$/u.test(unit)) return value * 1e-6;
    }

    if (partType === 'C') {
        if (unit === '' || /^F$/u.test(unit)) return value;
        if (/^(p|pF)$/u.test(unit)) return value * 1e-12;
        if (/^(n|nF)$/u.test(unit)) return value * 1e-9;
        if (/^(u|uF)$/u.test(unit)) return value * 1e-6;
        if (/^(m|mF)$/u.test(unit)) return value * 1e-3;
    }

    return value;
}

export function normalizeCustomValues(raw, partType) {
    const valuePartType = partType === 'divider' ? 'R' : partType;

    return String(raw ?? '')
        .split(/[,\n]+/u)
        .map((value) => parseTarget(value, valuePartType))
        .filter((value) => value !== null && value > 0);
}

function trimNumber(value, digits = 4) {
    if (!Number.isFinite(value)) return '-';
    return Number(value.toPrecision(digits)).toString();
}

function roundSignificant(value, digits = 3) {
    if (!Number.isFinite(value) || value === 0) return 0;
    return Number(value.toPrecision(digits));
}

export function formatResistance(value) {
    if (!Number.isFinite(value) || value <= 0) return '0Ω';
    if (value >= 1e6) return `${trimNumber(value / 1e6)}MΩ`;
    if (value >= 1e3) return `${trimNumber(value / 1e3)}kΩ`;
    if (value < 1) return `${trimNumber(value * 1000)}mΩ`;
    return `${trimNumber(value)}Ω`;
}

export function formatCapacitance(value) {
    if (!Number.isFinite(value) || value <= 0) return '0F';
    if (value < 1e-9) return `${trimNumber(value * 1e12)}pF`;
    if (value < 1e-6) return `${trimNumber(value * 1e9)}nF`;
    if (value < 1e-3) return `${trimNumber(value * 1e6)}uF`;
    return `${trimNumber(value * 1e3)}mF`;
}

export function formatVoltage(value) {
    if (!Number.isFinite(value)) return '-';
    const abs = Math.abs(value);
    if (abs > 0 && abs < 1) return `${trimNumber(value * 1000)}mV`;
    if (abs >= 1000) return `${trimNumber(value / 1000)}kV`;
    return `${trimNumber(value)}V`;
}

export function formatCurrent(value) {
    if (!Number.isFinite(value)) return '-';
    const abs = Math.abs(value);
    if (abs === 0) return '0A';
    if (abs < 1e-6) return `${trimNumber(value * 1e9)}nA`;
    if (abs < 1e-3) return `${trimNumber(value * 1e6)}uA`;
    if (abs < 1) return `${trimNumber(value * 1000)}mA`;
    return `${trimNumber(value)}A`;
}

export function formatPower(value) {
    if (!Number.isFinite(value)) return '-';
    const abs = Math.abs(value);
    if (abs === 0) return '0W';
    if (abs < 1e-6) return `${trimNumber(value * 1e9)}nW`;
    if (abs < 1e-3) return `${trimNumber(value * 1e6)}uW`;
    if (abs < 1) return `${trimNumber(value * 1000)}mW`;
    return `${trimNumber(value)}W`;
}

function formatTargetValue(value, partType) {
    if (value === null) return '-';
    if (partType === 'divider') return `${trimNumber(value * 100)}%`;
    if (partType === 'V') return formatVoltage(value);
    return partType === 'C' ? formatCapacitance(value) : formatResistance(value);
}

function parseCurrent(raw) {
    const source = normalizeRaw(raw);
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

function parseLoadResistance(raw) {
    const source = normalizeRaw(raw);
    if (!source || /^(∞|inf|infinity|open|none|無限大|無限)$/iu.test(source)) {
        return Infinity;
    }

    return parseTarget(source, 'R');
}

function parallelPair(a, b) {
    if (a <= 0 || b <= 0) return 0;
    if (!Number.isFinite(a)) return b;
    if (!Number.isFinite(b)) return a;

    return 1 / ((1 / a) + (1 / b));
}

function dividerLoad(raw) {
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

function loadedDividerOutput({ inputVoltage, topResistance, bottomResistance, load }) {
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

function branchCurrent(voltage, resistance) {
    if (!Number.isFinite(voltage) || !Number.isFinite(resistance) || resistance <= 0) return NaN;
    return voltage / resistance;
}

function resistorPower(current, resistance) {
    if (!Number.isFinite(current) || !Number.isFinite(resistance) || resistance <= 0) return NaN;
    return current * current * resistance;
}

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

function seriesBases(series) {
    if (E_SERIES_BASES[series]) return E_SERIES_BASES[series];
    const count = Number(String(series).replace('E', ''));
    if (!Number.isFinite(count) || count <= 0) return E_SERIES_BASES.E24;

    return Array.from({ length: count }, (_, index) => roundSignificant(10 ** (index / count), count >= 48 ? 3 : 2));
}

function uniqueSorted(values) {
    return [...new Set(values.filter((value) => Number.isFinite(value) && value > 0).map((value) => roundSignificant(value, 6)))]
        .sort((a, b) => a - b);
}

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

function nearestValues(values, ideal, limit = 14) {
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

function sourceLabel(source, kind) {
    if (source === 'vr-common') return '標準VR値';
    if (source === 'custom') return kind === 'fixed' ? 'カスタム固定' : 'カスタムVR';
    return `${source}${kind === 'fixed' ? '固定' : 'VR'}`;
}

function variableSourceValues(source, ideal, customRaw, kind) {
    if (source === 'custom') return nearestValues(normalizeCustomValues(customRaw, 'R'), ideal, 20);
    if (source === 'vr-common') return nearestValues(COMMON_VR_VALUES, ideal, 14);
    return nearestValues(generateSeriesValues(source, ideal), ideal, kind === 'fixed' ? 16 : 14);
}

function fixedSourceValues(source, ideals, customRaw) {
    const idealValues = uniqueSorted(ideals);
    if (source === 'custom') {
        return nearestValues(normalizeCustomValues(customRaw, 'R'), idealValues[0] ?? 1, 30);
    }

    const values = idealValues.flatMap((ideal) => generateSeriesValues(source, ideal, 0.25, 4));
    return uniqueSorted(values);
}

function nearlyEqual(a, b, rel = 1e-6) {
    if (!Number.isFinite(a) || !Number.isFinite(b)) return false;
    return Math.abs(a - b) <= Math.max(Math.abs(a), Math.abs(b), 1) * rel;
}

function endpointError(actual, target) {
    if (!Number.isFinite(actual) || !Number.isFinite(target) || target <= 0) return Infinity;
    return ((actual - target) / target) * 100;
}

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

function formatOhmDelta(value) {
    if (!Number.isFinite(value)) return '-';
    return `${value >= 0 ? '+' : ''}${formatResistance(Math.abs(value))}`;
}

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

function makeVariableCandidate({ circuit, fixed, pot, fixedSource, potSource, targetLow, targetHigh, idealFixed, idealPot, tolerancePct }) {
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
            '許容差/電力未評価',
            '型番未選定',
            '購入/在庫未確認',
        ].filter(Boolean),
        expression: circuit === 'parallel'
            ? `${formatResistance(fixed)} || VR ${formatResistance(pot)} => ${formatResistance(range.low)} 〜 ${formatResistance(range.high)}`
            : `${formatResistance(fixed)} + VR ${formatResistance(pot)} => ${formatResistance(range.low)} 〜 ${formatResistance(range.high)}`,
    };
}

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

export function calculateVariable(raw) {
    const requirement = variableRequirement(raw);
    const { reference, span, low, high } = requirement;
    const tolerancePct = Math.max(0, Number(raw.endpoint_tolerance_pct) || 0);
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

function dividerVariableRequirement(raw) {
    const inputVoltage = parseTarget(raw.input_voltage_raw, 'V') ?? 0;
    const outputMode = raw.output_mode || 'voltage';
    const parsedLowRatio = parseTarget(raw.output_low_ratio_raw, 'divider');
    const parsedHighRatio = parseTarget(raw.output_high_ratio_raw, 'divider');
    const lowRatio = outputMode === 'ratio'
        ? (parsedLowRatio ?? 0)
        : (inputVoltage > 0 ? (parseTarget(raw.output_low_raw, 'V') ?? 0) / inputVoltage : 0);
    const highRatio = outputMode === 'ratio'
        ? (parsedHighRatio ?? 0)
        : (inputVoltage > 0 ? (parseTarget(raw.output_high_raw, 'V') ?? 0) / inputVoltage : 0);
    const outputLow = inputVoltage > 0 ? inputVoltage * lowRatio : 0;
    const outputHigh = inputVoltage > 0 ? inputVoltage * highRatio : 0;
    const nominalPot = parseTarget(raw.nominal_pot_raw, 'R') ?? 0;
    const totalMin = parseTarget(raw.total_res_min_raw, 'R') ?? 0;
    const parsedTotalMax = parseTarget(raw.total_res_max_raw, 'R');
    const totalMax = parsedTotalMax && parsedTotalMax > 0 ? parsedTotalMax : Infinity;
    const load = dividerLoad(raw);
    const spanRatio = highRatio - lowRatio;

    return {
        inputVoltage,
        outputLow,
        outputHigh,
        outputMode,
        nominalPot,
        lowRatio,
        highRatio,
        spanRatio,
        totalMin,
        totalMax,
        load,
        valid: inputVoltage > 0
            && outputLow >= 0
            && outputHigh > outputLow
            && outputHigh <= inputVoltage
            && nominalPot > 0
            && totalMin >= 0
            && totalMax >= totalMin
            && load.valid
            && spanRatio > 0,
    };
}

function fixedDividerValues(source, ideal, customRaw) {
    if (!Number.isFinite(ideal) || ideal <= 0) return [0];
    return nearestValues(fixedSourceValues(source, [ideal], customRaw), ideal, 10);
}

function makeVariableDividerCandidate({
    inputVoltage,
    outputLow,
    outputHigh,
    top,
    pot,
    bottom,
    fixedSource,
    potSource,
    idealTop,
    idealBottom,
    idealPot,
    load,
    tolerancePct,
}) {
    const total = top + pot + bottom;
    const lowPoint = loadedDividerOutput({
        inputVoltage,
        topResistance: top + pot,
        bottomResistance: bottom,
        load,
    });
    const highPoint = loadedDividerOutput({
        inputVoltage,
        topResistance: top,
        bottomResistance: bottom + pot,
        load,
    });
    const low = lowPoint.voltage;
    const high = highPoint.voltage;
    const sourceCurrent = Math.max(lowPoint.sourceCurrent, highPoint.sourceCurrent);
    const outputCurrent = Math.max(lowPoint.outputCurrent, highPoint.outputCurrent);
    const lowBottomCurrent = branchCurrent(low, bottom);
    const highBottomBranchCurrent = branchCurrent(high, bottom + pot);
    const lowTopPower = resistorPower(lowPoint.sourceCurrent, top);
    const lowPotPower = resistorPower(lowPoint.sourceCurrent, pot);
    const lowBottomPower = resistorPower(lowBottomCurrent, bottom);
    const highTopPower = resistorPower(highPoint.sourceCurrent, top);
    const highPotPower = resistorPower(highBottomBranchCurrent, pot);
    const highBottomPower = resistorPower(highBottomBranchCurrent, bottom);
    const topPower = Math.max(lowTopPower, highTopPower);
    const potPower = Math.max(lowPotPower, highPotPower);
    const bottomPower = Math.max(lowBottomPower, highBottomPower);
    const lowResistorPower = lowTopPower + lowPotPower + lowBottomPower;
    const highResistorPower = highTopPower + highPotPower + highBottomPower;
    const resistorPowerTotal = Math.max(lowResistorPower, highResistorPower);
    const targetSpan = Math.max(outputHigh - outputLow, Number.EPSILON);
    const lowMargin = outputLow - low;
    const highMargin = high - outputHigh;
    const lowShortfall = Math.max(0, -lowMargin);
    const highShortfall = Math.max(0, -highMargin);
    const shortfall = lowShortfall + highShortfall;
    const endpointDeviation = Math.abs(low - outputLow) + Math.abs(high - outputHigh);
    const shortfallPct = (shortfall / targetSpan) * 100;
    const endpointDeviationPct = (endpointDeviation / targetSpan) * 100;
    const allowedShortfall = Math.max(targetSpan * (tolerancePct / 100), 1e-9);
    const coversTargetRange = shortfall <= 1e-9;
    const nearTargetRange = shortfall <= allowedShortfall;

    return {
        top,
        pot,
        bottom,
        total,
        low,
        high,
        inputVoltage,
        outputLow,
        outputHigh,
        sourceCurrent,
        outputCurrent,
        topPower,
        potPower,
        bottomPower,
        resistorPowerTotal,
        topDisplay: formatResistance(top),
        potDisplay: formatResistance(pot),
        bottomDisplay: formatResistance(bottom),
        totalDisplay: formatResistance(total),
        lowDisplay: formatVoltage(low),
        highDisplay: formatVoltage(high),
        targetLowDisplay: formatVoltage(outputLow),
        targetHighDisplay: formatVoltage(outputHigh),
        sourceCurrentDisplay: formatCurrent(sourceCurrent),
        outputCurrentDisplay: formatCurrent(outputCurrent),
        topPowerDisplay: formatPower(topPower),
        potPowerDisplay: formatPower(potPower),
        bottomPowerDisplay: formatPower(bottomPower),
        resistorPowerDisplay: formatPower(resistorPowerTotal),
        loadDisplay: load.display,
        lowMarginDisplay: `${lowMargin >= 0 ? '+' : ''}${formatVoltage(Math.abs(lowMargin))}`,
        highMarginDisplay: `${highMargin >= 0 ? '+' : ''}${formatVoltage(Math.abs(highMargin))}`,
        rangeMarginDisplay: coversTargetRange ? `${trimNumber(endpointDeviationPct, 3)}%` : `不足 ${trimNumber(shortfallPct, 3)}%`,
        shortfallPct,
        endpointDeviationPct,
        score: (coversTargetRange ? 0 : 100000) + shortfallPct * 100 + endpointDeviationPct,
        coversTargetRange,
        nearTargetRange,
        status: coversTargetRange ? 'check' : 'warn',
        verdict: coversTargetRange ? 'CHECK' : 'WARN',
        fixedSource,
        potSource,
        expression: `${formatVoltage(inputVoltage)} -> ${formatResistance(top)} + VR ${formatResistance(pot)} + ${formatResistance(bottom)} => ${formatVoltage(low)} 〜 ${formatVoltage(high)} / 負荷 ${load.display}`,
        tags: [
            sourceLabel(fixedSource, 'fixed'),
            sourceLabel(potSource, 'pot'),
            coversTargetRange ? '要求電圧範囲包含' : '要求電圧範囲不足',
            !nearlyEqual(top, idealTop) || !nearlyEqual(bottom, idealBottom) ? '固定抵抗再計算' : '固定抵抗理想近傍',
            !nearlyEqual(pot, idealPot) ? 'VR指定値から乖離' : 'VR指定値固定',
            endpointDeviationPct > 5 && coversTargetRange ? '端点広め' : '',
            load.type === 'current' ? '電流負荷込み' : (load.resistance === Infinity ? '無負荷分圧' : '抵抗負荷込み'),
            '許容差未評価',
            '型番未選定',
        ].filter(Boolean),
    };
}

export function calculateVariableDivider(raw) {
    const requirement = dividerVariableRequirement(raw);
    const tolerancePct = Math.max(0, Number(raw.endpoint_tolerance_pct) || 0);
    const fixedSource = raw.fixed_source || raw.series || 'E24';
    const fixedCustomValues = raw.fixed_custom_values ?? raw.custom_values ?? '';
    const potSource = raw.pot_source || 'vr-common';

    if (!requirement.valid) {
        return {
            valid: false,
            requirement,
            ideal: { top: 0, pot: 0, bottom: 0, total: 0, low: 0, high: 0 },
            candidates: [],
            bestCandidate: null,
            warnings: ['入力電圧と出力電圧範囲を確認してください'],
            expression: 'Vin -> R上 + VR + R下 -> GND',
        };
    }

    const potValues = [requirement.nominalPot];
    const candidates = potValues.flatMap((potValue) => {
        const idealTotalForPot = potValue / requirement.spanRatio;
        const idealTopForPot = (1 - requirement.highRatio) * idealTotalForPot;
        const idealBottomForPot = requirement.lowRatio * idealTotalForPot;
        const topValues = fixedDividerValues(fixedSource, idealTopForPot, fixedCustomValues);
        const bottomValues = fixedDividerValues(fixedSource, idealBottomForPot, fixedCustomValues);

        return topValues.flatMap((topValue) => bottomValues.map((bottomValue) => makeVariableDividerCandidate({
            inputVoltage: requirement.inputVoltage,
            outputLow: requirement.outputLow,
            outputHigh: requirement.outputHigh,
            top: topValue,
            pot: potValue,
            bottom: bottomValue,
            fixedSource,
            potSource,
            idealTop: idealTopForPot,
            idealBottom: idealBottomForPot,
            idealPot: requirement.nominalPot,
            load: requirement.load,
            tolerancePct,
        })));
    })
        .filter((candidate) => candidate.total >= requirement.totalMin && candidate.total <= requirement.totalMax)
        .sort((a, b) => a.score - b.score || a.total - b.total || a.top - b.top || a.bottom - b.bottom)
        .slice(0, 8);

    const bestCandidate = candidates.find((candidate) => candidate.status === 'check') ?? candidates[0] ?? null;
    const idealTotal = requirement.nominalPot / requirement.spanRatio;
    const idealTop = (1 - requirement.highRatio) * idealTotal;
    const idealBottom = requirement.lowRatio * idealTotal;

    return {
        valid: true,
        requirement,
        ideal: {
            top: idealTop,
            pot: requirement.nominalPot,
            bottom: idealBottom,
            total: idealTotal,
            low: requirement.outputLow,
            high: requirement.outputHigh,
        },
        candidates,
        bestCandidate,
        warnings: candidates.length ? [] : ['候補値ソースに採用候補がありません'],
        expression: `${formatVoltage(requirement.inputVoltage)} -> ${formatResistance(idealTop)} + VR ${formatResistance(requirement.nominalPot)} + ${formatResistance(idealBottom)} => ${formatVoltage(requirement.outputLow)} 〜 ${formatVoltage(requirement.outputHigh)} / 負荷 ${requirement.load.display}`,
    };
}

export default function setup() {
    const activeMode = ref('network');
    const form = reactive({
        part_type: 'R',
        divider_mode: 'fixed',
        divider_target_mode: 'ratio',
        target_raw: '1k',
        input_voltage_raw: '3.3',
        output_voltage_raw: '2.5',
        tolerance_pct: 5,
        series: 'E24',
        custom_values: '',
        min_elements: 1,
        max_elements: 2,
        inventory_only: false,
        circuit_types: ['series', 'parallel'],
        total_res_min_raw: '',
        total_res_max_raw: '',
        load_type: 'resistance',
        load_resistance_raw: '∞',
        load_current_raw: '0',
    });

    const results = ref([]);
    const searching = ref(false);
    const elapsedMs = ref(null);
    const truncated = ref(false);
    const error = ref('');
    const summary = ref('');
    const warnings = ref([]);
    const nextActions = ref([]);
    const poolInfo = ref(null);
    const compareIds = ref([]);

    const presets = [
        { label: '1kΩ', meta: '抵抗 E24', type: 'R', target: '1k', tolerance: 5, series: 'E24', circuits: ['series', 'parallel'] },
        { label: '100nF', meta: '容量 E12', type: 'C', target: '100n', tolerance: 10, series: 'E12', circuits: ['parallel', 'series'] },
        { label: '1/2', meta: '分圧 1k-100k', type: 'divider', targetMode: 'ratio', target: '50%', tolerance: 1, series: 'E24', circuits: ['divider'], min: '1k', max: '100k' },
        { label: '2.5V/3.3V', meta: '分圧', type: 'divider', targetMode: 'voltage', vin: '3.3', vout: '2.5', tolerance: 1, series: 'E96', circuits: ['divider'], min: '5k', max: '200k' },
    ];

    const dividerVoltageTarget = computed(() => dividerRatioFromVoltages(form.input_voltage_raw, form.output_voltage_raw));
    const isDividerVariableMode = computed(() => activeMode.value === 'divider' && form.divider_mode === 'variable');
    const targetValue = computed(() => {
        if (form.part_type === 'divider' && form.divider_target_mode === 'voltage') {
            return dividerVoltageTarget.value.valid ? dividerVoltageTarget.value.ratio : null;
        }

        return parseTarget(form.target_raw, form.part_type);
    });
    const targetValid = computed(() => {
        const value = targetValue.value;
        if (value === null) return false;
        if (form.part_type === 'divider') return value > 0 && value < 1;
        return value > 0;
    });
    const dividerLoadConfig = computed(() => dividerLoad(form));
    const loadValid = computed(() => {
        if (form.part_type !== 'divider') return true;
        if (!dividerLoadConfig.value.valid) return false;
        if (dividerLoadConfig.value.type === 'current' && dividerLoadConfig.value.current > 0) {
            const inputVoltage = parseTarget(form.input_voltage_raw, 'V');
            return Number.isFinite(inputVoltage) && inputVoltage > 0;
        }

        return true;
    });
    const elementRangeValid = computed(() => form.part_type === 'divider' || Number(form.min_elements) <= Number(form.max_elements));
    const circuitTypesValid = computed(() => form.part_type === 'divider' || form.circuit_types.length > 0);
    const formValid = computed(() => targetValid.value && loadValid.value && elementRangeValid.value && circuitTypesValid.value);
    const partTypeLabel = computed(() => PART_TYPE_LABELS[form.part_type] ?? form.part_type);
    const targetHint = computed(() => ({
        R: '4.7k / 4700 / 4.7kΩ',
        C: '100n / 0.1u / 100nF',
        divider: form.divider_target_mode === 'voltage' ? 'Vin と Vout から比率を計算' : '0.5 / 50%',
    }[form.part_type] ?? ''));
    const validationMessage = computed(() => {
        if (!targetValid.value) {
            if (form.part_type === 'divider' && form.divider_target_mode === 'voltage') return '入力電圧と出力電圧を確認してください';
            return form.part_type === 'divider' ? '分圧比は 0% 超 100% 未満です' : '目標値を確認してください';
        }
        if (!loadValid.value) return '負荷条件を確認してください';
        if (!elementRangeValid.value) return '素子数範囲を確認してください';
        if (!circuitTypesValid.value) return '回路種別を選択してください';
        return '';
    });

    const rankedResults = computed(() => results.value.map((candidate, index) => ({
        ...candidate,
        rank: index + 1,
        id: `${index}-${candidate.circuit_type}-${candidate.expression}-${candidate.actual_value}`,
    })));
    const comparedCandidates = computed(() => rankedResults.value.filter((candidate) => compareIds.value.includes(candidate.id)));
    const summaryText = computed(() => summary.value || (elapsedMs.value === null ? '未探索' : `${results.value.length}件`));
    const visibleWarnings = computed(() => {
        const messages = [...warnings.value];
        if (elapsedMs.value !== null) {
            messages.push(`${partTypeLabel.value}の公称値探索です。部品公差、温度、電力、DCバイアス、負荷条件は別途確認してください。`);
        }
        if (poolInfo.value?.raw && poolInfo.value?.used && poolInfo.value.used < poolInfo.value.raw) {
            messages.push(`3素子以上または混在探索は近傍 ${poolInfo.value.used} / ${poolInfo.value.raw} 候補から探索しています。`);
        }
        if (form.inventory_only && elapsedMs.value !== null) {
            messages.push('在庫値のみはE系列ではなく、登録済み在庫部品の値セットを使います。');
        }
        return [...new Set(messages)];
    });
    const statusMetrics = computed(() => [
        { label: '目標', value: poolInfo.value?.target ?? formatTargetValue(targetValue.value, form.part_type) },
        { label: '候補', value: elapsedMs.value === null ? '-' : `${results.value.length}件` },
        { label: '値プール', value: poolInfo.value?.raw ? `${poolInfo.value.used ?? '-'} / ${poolInfo.value.raw}` : '-' },
        { label: '探索元', value: form.inventory_only ? '在庫値' : form.series },
    ]);

    const resetSearchState = () => {
        results.value = [];
        elapsedMs.value = null;
        truncated.value = false;
        error.value = '';
        summary.value = '';
        warnings.value = [];
        nextActions.value = [];
        poolInfo.value = null;
        compareIds.value = [];
    };

    const setPartType = (type) => {
        const oldType = form.part_type;
        form.part_type = type;
        resetSearchState();
        if (type === 'divider') {
            form.circuit_types = ['divider'];
            form.min_elements = 2;
            form.max_elements = 2;
            form.inventory_only = false;
            if (oldType !== 'divider') {
                form.divider_mode = 'fixed';
                form.divider_target_mode = 'ratio';
                form.target_raw = '50%';
            }
            return;
        }
        if (form.circuit_types.includes('divider')) form.circuit_types = ['series', 'parallel'];
        if (type === 'C' && oldType !== 'C') form.target_raw = '100n';
        if (type === 'R' && oldType !== 'R') form.target_raw = '1k';
    };

    const setDividerMode = (mode) => {
        if (form.part_type !== 'divider') {
            setPartType('divider');
        } else {
            resetSearchState();
        }
        activeMode.value = 'divider';
        form.divider_mode = mode;
    };

    const setActiveMode = (mode) => {
        activeMode.value = mode;
        resetSearchState();
        if (mode === 'divider') {
            form.inventory_only = false;
            if (form.part_type !== 'divider') setPartType('divider');
            return;
        }
        if (mode === 'network' && form.part_type === 'divider') {
            setPartType('R');
        }
    };

    const applyPreset = (preset) => {
        activeMode.value = preset.type === 'divider' ? 'divider' : 'network';
        form.part_type = preset.type;
        form.inventory_only = false;
        form.divider_mode = 'fixed';
        form.divider_target_mode = preset.targetMode ?? 'ratio';
        form.target_raw = preset.target ?? form.target_raw;
        form.input_voltage_raw = preset.vin ?? form.input_voltage_raw;
        form.output_voltage_raw = preset.vout ?? form.output_voltage_raw;
        form.tolerance_pct = preset.tolerance;
        form.series = preset.series;
        form.circuit_types = [...preset.circuits];
        form.min_elements = preset.type === 'divider' ? 2 : 1;
        form.max_elements = preset.type === 'divider' ? 2 : 2;
        form.total_res_min_raw = preset.min ?? '';
        form.total_res_max_raw = preset.max ?? '';
        resetSearchState();
    };

    const toggleCircuitType = (type) => {
        const index = form.circuit_types.indexOf(type);
        if (index >= 0) form.circuit_types.splice(index, 1);
        else form.circuit_types.push(type);
        resetSearchState();
    };

    const search = async () => {
        if (!formValid.value) {
            error.value = validationMessage.value;
            return;
        }

        error.value = '';
        searching.value = true;
        resetSearchState();

        try {
            const payload = {
                target: targetValue.value,
                tolerance_pct: form.tolerance_pct,
                part_type: form.part_type,
                series: form.series,
                min_elements: form.part_type === 'divider' ? 2 : form.min_elements,
                max_elements: form.part_type === 'divider' ? 2 : form.max_elements,
                inventory_only: form.inventory_only,
                circuit_types: form.part_type === 'divider' ? ['divider'] : form.circuit_types,
            };

            if (form.part_type === 'divider') {
                payload.total_res_min = parseTarget(form.total_res_min_raw, 'R') ?? 0;
                payload.total_res_max = parseTarget(form.total_res_max_raw, 'R') ?? null;
                payload.load_type = dividerLoadConfig.value.type;
                payload.load_current = dividerLoadConfig.value.current;
                payload.load_resistance = Number.isFinite(dividerLoadConfig.value.resistance) ? dividerLoadConfig.value.resistance : null;
                payload.load_resistance_infinite = dividerLoadConfig.value.resistance === Infinity;
                const inputVoltage = parseTarget(form.input_voltage_raw, 'V');
                if (Number.isFinite(inputVoltage) && inputVoltage > 0) {
                    payload.input_voltage = inputVoltage;
                }
                if (form.divider_target_mode === 'voltage') {
                    payload.output_voltage = dividerVoltageTarget.value.output;
                }
            }
            if (form.series === 'custom') {
                payload.custom_values = normalizeCustomValues(form.custom_values, form.part_type);
            }

            const response = await api.post('/calc/networks/search', payload);
            const normalized = normalizeNetworkResponse(response.data);
            const result = normalized.result ?? {};
            results.value = result?.candidates ?? [];
            elapsedMs.value = result?.elapsed_ms ?? 0;
            truncated.value = Boolean(result?.truncated);
            summary.value = normalized.summary;
            warnings.value = normalized.warnings;
            nextActions.value = normalized.nextActions;
            poolInfo.value = result ? {
                raw: result.raw_pool_count ?? null,
                used: result.candidate_pool_count ?? null,
                limit: result.return_limit ?? null,
                target: result.target_display ?? null,
            } : null;
        } catch (err) {
            const responseMessage = err.response?.data?.message;
            const firstError = Object.values(err.response?.data?.errors ?? {})?.flat?.()?.[0];
            error.value = firstError || responseMessage || err.message || '探索に失敗しました';
            elapsedMs.value = 0;
        } finally {
            searching.value = false;
        }
    };

    const circuitTypeLabel = (type) => ({
        series: '直列',
        parallel: '並列',
        divider: '分圧',
        single: '単体',
        mixed: '混在',
    }[type] ?? type);

    const errorClass = (errPct) => {
        if (errPct <= 1) return 'text-[var(--color-tag-ok)]';
        if (errPct <= 5) return 'text-[var(--color-tag-warning)]';
        return 'text-[var(--color-tag-eol)]';
    };
    const variableStatusClass = (status) => ({
        check: 'border-amber-400 text-amber-700 bg-amber-50',
        warn: 'border-red-400 text-red-700 bg-red-50',
    }[status] ?? 'border-[var(--color-border)]');
    const topologyTokens = (expression) => String(expression ?? '')
        .split(/(\s+|\+|∥|\(|\))/u)
        .map((token) => token.trim())
        .filter(Boolean)
        .map((token) => ({
            text: token,
            type: ['+', '∥', '(', ')'].includes(token) ? 'operator' : 'part',
        }));
    const isCompared = (candidate) => compareIds.value.includes(candidate.id);
    const toggleCompare = (candidate) => {
        const index = compareIds.value.indexOf(candidate.id);
        if (index >= 0) {
            compareIds.value.splice(index, 1);
            return;
        }
        compareIds.value = [...compareIds.value.slice(-2), candidate.id];
    };
    const clearCompare = () => { compareIds.value = []; };
    const setLoadResistanceInfinite = (target) => {
        target.load_type = 'resistance';
        target.load_resistance_raw = '∞';
        if (target === form) resetSearchState();
    };
    const setLoadCurrentZero = (target) => {
        target.load_type = 'current';
        target.load_current_raw = '0';
        if (target === form) resetSearchState();
    };

    const variable = reactive({
        reference_raw: '10k',
        span_mode: 'percent',
        span_raw: '20',
        reference_position: 'upper',
        circuit: 'series',
        fixed_source: 'E24',
        fixed_custom_values: '',
        pot_source: 'vr-common',
        pot_custom_values: '',
    });
    const variableResult = computed(() => {
        const result = calculateVariable(variable);
        const ideal = result.ideal ?? { fixed: 0, pot: 0, low: 0, high: 0 };
        return {
            ...result,
            idealFixedDisplay: formatResistance(ideal.fixed),
            idealPotDisplay: formatResistance(ideal.pot),
            idealLowDisplay: formatResistance(ideal.low),
            idealHighDisplay: formatResistance(ideal.high),
            referenceDisplay: formatResistance(result.requirement?.reference ?? 0),
            spanDisplay: formatResistance(result.requirement?.span ?? 0),
            requirementLowDisplay: formatResistance(result.requirement?.low ?? 0),
            requirementHighDisplay: formatResistance(result.requirement?.high ?? 0),
            selectedFixedDisplay: result.bestCandidate?.fixedDisplay ?? '-',
            selectedPotDisplay: result.bestCandidate?.potDisplay ?? '-',
            selectedLowDisplay: result.bestCandidate?.lowDisplay ?? '-',
            selectedHighDisplay: result.bestCandidate?.highDisplay ?? '-',
        };
    });
    const dividerVariable = reactive({
        output_low_raw: '1',
        output_high_raw: '3',
        output_low_ratio_raw: '20%',
        output_high_ratio_raw: '60%',
        nominal_pot_raw: '10k',
        fixed_source: 'E24',
        fixed_custom_values: '',
        pot_source: 'vr-common',
        pot_custom_values: '',
        endpoint_tolerance_pct: 0,
        load_type: 'resistance',
        load_resistance_raw: '∞',
        load_current_raw: '0',
    });
    const dividerVariableResult = computed(() => {
        const result = calculateVariableDivider({
            ...dividerVariable,
            input_voltage_raw: form.input_voltage_raw,
            output_mode: form.divider_target_mode,
            fixed_source: form.series,
            fixed_custom_values: form.custom_values,
            endpoint_tolerance_pct: form.tolerance_pct,
            total_res_min_raw: form.total_res_min_raw,
            total_res_max_raw: form.total_res_max_raw,
            load_type: form.load_type,
            load_resistance_raw: form.load_resistance_raw,
            load_current_raw: form.load_current_raw,
        });
        const ideal = result.ideal ?? { top: 0, pot: 0, bottom: 0, total: 0, low: 0, high: 0 };
        const requirement = result.requirement ?? {};

        return {
            ...result,
            inputVoltageDisplay: formatVoltage(requirement.inputVoltage ?? 0),
            outputLowDisplay: formatVoltage(requirement.outputLow ?? 0),
            outputHighDisplay: formatVoltage(requirement.outputHigh ?? 0),
            nominalPotDisplay: formatResistance(requirement.nominalPot ?? 0),
            loadDisplay: requirement.load?.display ?? '-',
            ratioLowDisplay: Number.isFinite(requirement.lowRatio) ? `${trimNumber(requirement.lowRatio * 100, 4)}%` : '-',
            ratioHighDisplay: Number.isFinite(requirement.highRatio) ? `${trimNumber(requirement.highRatio * 100, 4)}%` : '-',
            idealTopDisplay: formatResistance(ideal.top),
            idealPotDisplay: formatResistance(ideal.pot),
            idealBottomDisplay: formatResistance(ideal.bottom),
            idealTotalDisplay: formatResistance(ideal.total),
            selectedTopDisplay: result.bestCandidate?.topDisplay ?? '-',
            selectedPotDisplay: result.bestCandidate?.potDisplay ?? '-',
            selectedBottomDisplay: result.bestCandidate?.bottomDisplay ?? '-',
            selectedLowDisplay: result.bestCandidate?.lowDisplay ?? '-',
            selectedHighDisplay: result.bestCandidate?.highDisplay ?? '-',
            selectedSourceCurrentDisplay: result.bestCandidate?.sourceCurrentDisplay ?? '-',
            selectedOutputCurrentDisplay: result.bestCandidate?.outputCurrentDisplay ?? '-',
            selectedTopPowerDisplay: result.bestCandidate?.topPowerDisplay ?? '-',
            selectedPotPowerDisplay: result.bestCandidate?.potPowerDisplay ?? '-',
            selectedBottomPowerDisplay: result.bestCandidate?.bottomPowerDisplay ?? '-',
            selectedResistorPowerDisplay: result.bestCandidate?.resistorPowerDisplay ?? '-',
        };
    });

    return {
        activeMode,
        modeOptions: MODE_OPTIONS,
        networkPartTypeOptions: PART_TYPE_OPTIONS,
        dividerModeOptions: DIVIDER_MODE_OPTIONS,
        dividerTargetModeOptions: DIVIDER_TARGET_MODE_OPTIONS,
        loadTypeOptions: LOAD_TYPE_OPTIONS,
        partTypeOptions: PART_TYPE_OPTIONS,
        seriesOptions: SERIES_OPTIONS,
        variableFixedSourceOptions: VARIABLE_FIXED_SOURCE_OPTIONS,
        variablePotSourceOptions: VARIABLE_POT_SOURCE_OPTIONS,
        variableReferencePositionOptions: VARIABLE_REFERENCE_POSITION_OPTIONS,
        circuitOptions: CIRCUIT_OPTIONS,
        form,
        results,
        rankedResults,
        searching,
        elapsedMs,
        truncated,
        error,
        summaryText,
        warnings: visibleWarnings,
        nextActions,
        poolInfo,
        statusMetrics,
        presets,
        targetValid,
        elementRangeValid,
        circuitTypesValid,
        formValid,
        validationMessage,
        partTypeLabel,
        targetHint,
        isDividerVariableMode,
        dividerVoltageTarget,
        dividerLoadConfig,
        comparedCandidates,
        variable,
        variableResult,
        dividerVariable,
        dividerVariableResult,
        setPartType,
        setDividerMode,
        setActiveMode,
        applyPreset,
        toggleCircuitType,
        search,
        circuitTypeLabel,
        errorClass,
        variableStatusClass,
        topologyTokens,
        isCompared,
        toggleCompare,
        clearCompare,
        setLoadResistanceInfinite,
        setLoadCurrentZero,
    };
}
