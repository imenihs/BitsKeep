import {
    branchCurrent,
    dividerLoad,
    fixedSourceValues,
    formatCurrent,
    formatPercentNumber,
    formatPower,
    formatResistance,
    formatVoltage,
    loadedDividerOutput,
    nearestValues,
    nearlyEqual,
    normalizeTolerancePct,
    parseTarget,
    resistorPower,
    sourceLabel,
    toleranceRangeForValues,
    trimNumber,
} from './core.js';

// 目的: 抵抗/容量探索のdivider Variable Requirementを扱う。機能: 入力値を検証・整形し、画面または計算処理へ渡す。入力: 関数シグネチャの値。出力: 処理結果またはなし。動作条件: 抵抗/容量探索の初期化後に呼び出す。副作用: Vue状態、localStorage、DOM、HTTP通信のいずれかを更新する場合がある。
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

// 目的: 抵抗/容量探索のfixed Divider Valuesを扱う。機能: 入力値を検証・整形し、画面または計算処理へ渡す。入力: 関数シグネチャの値。出力: 処理結果またはなし。動作条件: 抵抗/容量探索の初期化後に呼び出す。副作用: Vue状態、localStorage、DOM、HTTP通信のいずれかを更新する場合がある。
function fixedDividerValues(source, ideal, customRaw) {
    if (!Number.isFinite(ideal) || ideal <= 0) return [0];
    return nearestValues(fixedSourceValues(source, [ideal], customRaw), ideal, 10);
}

// 目的: 抵抗/容量探索のvariable Divider Toleranceを扱う。機能: 入力値を検証・整形し、画面または計算処理へ渡す。入力: 関数シグネチャの値。出力: 処理結果またはなし。動作条件: 抵抗/容量探索の初期化後に呼び出す。副作用: Vue状態、localStorage、DOM、HTTP通信のいずれかを更新する場合がある。
function variableDividerTolerance({
    inputVoltage,
    top,
    pot,
    bottom,
    load,
    topTolerancePct,
    potTolerancePct,
    bottomTolerancePct,
}) {
    const topTol = normalizeTolerancePct(topTolerancePct);
    const potTol = normalizeTolerancePct(potTolerancePct);
    const bottomTol = normalizeTolerancePct(bottomTolerancePct);
    if (topTol <= 0 && potTol <= 0 && bottomTol <= 0) return {};

    const items = [
        { value: top, tolerancePct: topTol },
        { value: pot, tolerancePct: potTol },
        { value: bottom, tolerancePct: bottomTol },
    ];
    // 目的: 抵抗/容量探索のevaluate Lowを扱う。機能: 入力値を検証・整形し、画面または計算処理へ渡す。入力: 関数シグネチャの値。出力: 処理結果またはなし。動作条件: 抵抗/容量探索の初期化後に呼び出す。副作用: Vue状態、localStorage、DOM、HTTP通信のいずれかを更新する場合がある。
    const evaluateLow = ([candidateTop, candidatePot, candidateBottom]) => loadedDividerOutput({
        inputVoltage,
        topResistance: candidateTop + candidatePot,
        bottomResistance: candidateBottom,
        load,
    }).voltage;
    // 目的: 抵抗/容量探索のevaluate Highを扱う。機能: 入力値を検証・整形し、画面または計算処理へ渡す。入力: 関数シグネチャの値。出力: 処理結果またはなし。動作条件: 抵抗/容量探索の初期化後に呼び出す。副作用: Vue状態、localStorage、DOM、HTTP通信のいずれかを更新する場合がある。
    const evaluateHigh = ([candidateTop, candidatePot, candidateBottom]) => loadedDividerOutput({
        inputVoltage,
        topResistance: candidateTop,
        bottomResistance: candidateBottom + candidatePot,
        load,
    }).voltage;
    const nominalLow = evaluateLow([top, pot, bottom]);
    const nominalHigh = evaluateHigh([top, pot, bottom]);
    const lowRange = toleranceRangeForValues(items, nominalLow, evaluateLow, formatVoltage);
    const highRange = toleranceRangeForValues(items, nominalHigh, evaluateHigh, formatVoltage);
    if (!lowRange || !highRange) return {};

    return {
        topTolerancePct: topTol,
        potTolerancePct: potTol,
        bottomTolerancePct: bottomTol,
        toleranceDisplay: `R上 ±${formatPercentNumber(topTol)} / VR ±${formatPercentNumber(potTol)} / R下 ±${formatPercentNumber(bottomTol)}`,
        rssLowEndpointRangeDisplay: lowRange.rssRangeDisplay,
        rssHighEndpointRangeDisplay: highRange.rssRangeDisplay,
        rssOutputRangeDisplay: `${lowRange.rssLowDisplay} 〜 ${highRange.rssHighDisplay}`,
        cornerLowEndpointRangeDisplay: lowRange.cornerRangeDisplay,
        cornerHighEndpointRangeDisplay: highRange.cornerRangeDisplay,
        cornerOutputRangeDisplay: `${lowRange.cornerLowDisplay} 〜 ${highRange.cornerHighDisplay}`,
    };
}

// 目的: 抵抗/容量探索のmake Variable Divider Candidateを扱う。機能: 入力値を検証・整形し、画面または計算処理へ渡す。入力: 関数シグネチャの値。出力: 処理結果またはなし。動作条件: 抵抗/容量探索の初期化後に呼び出す。副作用: Vue状態、localStorage、DOM、HTTP通信のいずれかを更新する場合がある。
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
    topTolerancePct = 0,
    potTolerancePct = 0,
    bottomTolerancePct = 0,
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
            topTolerancePct > 0 || potTolerancePct > 0 || bottomTolerancePct > 0 ? '許容差範囲表示' : '許容差未設定',
            '型番未選定',
        ].filter(Boolean),
        ...variableDividerTolerance({
            inputVoltage,
            top,
            pot,
            bottom,
            load,
            topTolerancePct,
            potTolerancePct,
            bottomTolerancePct,
        }),
    };
}

/**
 * 目的: VR付き分圧で指定出力範囲を満たすR上/VR/R下候補を作る。
 * 機能: 指定VR公称値を固定し、上下固定抵抗の近傍値を組み合わせて負荷込み端点を採点する。
 * 入力: `raw` はVin、出力上下限、VR公称値、総抵抗範囲、負荷、許容差、候補値ソース。
 * 出力: 妥当性、要求条件、理想抵抗値、候補リスト、最良候補、警告、式表示。
 * 動作条件: Vinが正、出力上限が下限より高くVin以下、VR値と出力比率差が正であること。
 * 副作用: なし。
 */
export function calculateVariableDivider(raw) {
    const requirement = dividerVariableRequirement(raw);
    const tolerancePct = Math.max(0, Number(raw.endpoint_tolerance_pct) || 0);
    const topTolerancePct = normalizeTolerancePct(raw.top_tolerance_pct);
    const potTolerancePct = normalizeTolerancePct(raw.pot_tolerance_pct);
    const bottomTolerancePct = normalizeTolerancePct(raw.bottom_tolerance_pct);
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
            topTolerancePct,
            potTolerancePct,
            bottomTolerancePct,
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
