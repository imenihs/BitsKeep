/**
 * 設計解析ツールの責務分割モジュール。
 * 親 setup から渡された reactive/computed と数値ヘルパーを使い、
 * 画面表示に必要な状態、計算結果、レポート生成関数を返す。
 */

/**
 * setupAnalogDesignTools は親から渡された依存を使ってツール責務を初期化する。
 * @param {object} deps 入力状態、数値変換、レポート生成などの依存。
 * @returns {object} Vueテンプレートへ公開する状態、computed、操作関数。
 * @sideEffects reactive状態とlocalStorageを更新する操作関数を含む。
 */
// 目的: 設計解析ツールのsetup Analog Design Toolsを扱う。機能: 入力値を検証・整形し、画面または計算処理へ渡す。入力: 関数シグネチャの値。出力: 処理結果またはなし。動作条件: 設計解析ツールの初期化後に呼び出す。副作用: Vue状態、localStorage、DOM、HTTP通信のいずれかを更新する場合がある。
export default function setupAnalogDesignTools({
    computed,
    reactive,
    toFinite,
    hasRating,
    parseNumber,
    formatNumber,
    setupBatteryRuntimeTool,
    logicLevelCompatibility,
}) {
// ADCコード/スケーリング
const adc = reactive({
    bits: 12, vref: 3.3, vin: 1.65, vinMin: 0.1, vinTyp: 1.65, vinMax: 3.0, offset: 0, physicalMin: 0, physicalMax: 100, fixedPointBits: 16,
});
const adcResult = computed(() => {
    const codeCount = Math.pow(2, adc.bits);
    const fullScale = codeCount - 1;
    const lsb       = adc.vref / Math.max(codeCount, 1);
    // 目的: 設計解析ツールのcode Forを扱う。機能: 入力値を検証・整形し、画面または計算処理へ渡す。入力: 関数シグネチャの値。出力: 処理結果またはなし。動作条件: 設計解析ツールの初期化後に呼び出す。副作用: Vue状態、localStorage、DOM、HTTP通信のいずれかを更新する場合がある。
    const codeFor = (vin) => Math.floor((vin - adc.offset) / Math.max(lsb, 1e-12));
    const code      = codeFor(adc.vin);
    const clamped   = Math.max(0, Math.min(fullScale, code));
    const minCode = codeFor(adc.vinMin);
    const typCode = codeFor(adc.vinTyp);
    const maxCode = codeFor(adc.vinMax);
    const physicalSpan = adc.physicalMax - adc.physicalMin;
    const fixedScale = physicalSpan / Math.max(codeCount, 1);
    const fixedQ = Math.round(fixedScale * (2 ** Math.max(adc.fixedPointBits, 1)));
    return {
        code:     clamped,
        hex:      '0x' + clamped.toString(16).toUpperCase().padStart(Math.ceil(adc.bits / 4), '0'),
        lsb_mv:   (lsb * 1000).toFixed(4),
        quant_error_mv: (lsb * 500).toFixed(4),
        min_code: Math.max(0, Math.min(fullScale, minCode)),
        typ_code: Math.max(0, Math.min(fullScale, typCode)),
        max_code: Math.max(0, Math.min(fullScale, maxCode)),
        range_clipped: minCode < 0 || maxCode > fullScale,
        physical_lsb: fixedScale.toFixed(6),
        fixed_q: fixedQ,
        c_code: `int32_t phys_q${adc.fixedPointBits} = ((int32_t)adc_code * ${fixedQ}) + ${(adc.physicalMin * (2 ** Math.max(adc.fixedPointBits, 1))).toFixed(0)};`,
        percent:  (clamped / fullScale * 100).toFixed(2),
        clipped:  code < 0 || code > fullScale,
    };
});

// 電解コンデンサ寿命推定
const cap = reactive({
    L0: 2000,   // 定格寿命(h)
    T0: 105,    // 定格温度(°C)
    T:  65,     // 動作温度(°C)
    Vr: 50,     // 定格電圧(V)
    V:  35,     // 動作電圧(V)
    rippleCurrent: 0.2,
    esr: 0.5,
    targetLifeY: 5,
    ambientWorst: 85,
    voltageDeratingPct: 80,
});
const capResult = computed(() => {
    const assumedThermalResistance = 10;
    const rippleLoss = Math.max(cap.rippleCurrent, 0) * Math.max(cap.rippleCurrent, 0) * Math.max(cap.esr, 0);
    const selfHeat = rippleLoss * assumedThermalResistance;
    const effectiveTemp = cap.T + selfHeat;
    const worstTemp = Math.max(cap.T, cap.ambientWorst) + selfHeat;
    const tempFactor    = Math.pow(2, (cap.T0 - effectiveTemp) / 10);
    const worstTempFactor = Math.pow(2, (cap.T0 - worstTemp) / 10);
    const deratingOk = cap.V <= cap.Vr * cap.voltageDeratingPct / 100;
    const life          = cap.L0 * tempFactor;
    const worstLife = cap.L0 * worstTempFactor;
    return {
        life_h:    Math.round(life),
        life_y:    (life / 8760).toFixed(1),
        worst_life_y: (worstLife / 8760).toFixed(1),
        self_heat_c: selfHeat.toFixed(2),
        ripple_loss_w: rippleLoss.toFixed(4),
        effective_temp_c: effectiveTemp.toFixed(1),
        worst_temp_c: worstTemp.toFixed(1),
        target_margin_y: (life / 8760 - cap.targetLifeY).toFixed(1),
        derating_ok: deratingOk,
        temp_factor:    tempFactor.toFixed(2),
        voltage_factor: '1.00',
    };
});

// NTC/PTC温度変換
const divider = reactive({
    sensorType: 'ntc',
    position: 'low',
    R0: 10000, T0: 25, B: 3950, Rmeas: 10000, fixedResistor: 10000, fixedResistorTolerancePct: 1, thermistorTolerancePct: 1, dissipationMwPerC: 2, tempMin: -20, tempMax: 85, tempStep: 25, targetTempMin: 20, targetTempMax: 40, adcBits: 12, adcVref: 3.3, pullupCandidates: '4700,10000,22000',
});
const dividerGraphCursor = reactive({
    active: false,
    x: 0,
    y: 0,
    temp: 0,
    voltage: 0,
    sensitivity: 0,
});
// 目的: 設計解析ツールのdivider Thermistor Directionを扱う。機能: 入力値を検証・整形し、画面または計算処理へ渡す。入力: 関数シグネチャの値。出力: 処理結果またはなし。動作条件: 設計解析ツールの初期化後に呼び出す。副作用: Vue状態、localStorage、DOM、HTTP通信のいずれかを更新する場合がある。
const dividerThermistorDirection = () => divider.sensorType === 'ptc' ? -1 : 1;
// 目的: 設計解析ツールのdivider Resistance At Tempを扱う。機能: 入力値を検証・整形し、画面または計算処理へ渡す。入力: 関数シグネチャの値。出力: 処理結果またはなし。動作条件: 設計解析ツールの初期化後に呼び出す。副作用: Vue状態、localStorage、DOM、HTTP通信のいずれかを更新する場合がある。
const dividerResistanceAtTemp = (tempC) => {
    const beta = Math.max(Math.abs(toFinite(divider.B, 3950)), 1e-12);
    const tk = Math.max(toFinite(tempC) + 273.15, 1e-12);
    const t0k = Math.max(toFinite(divider.T0) + 273.15, 1e-12);
    const exponent = Math.max(-60, Math.min(60, dividerThermistorDirection() * beta * ((1 / tk) - (1 / t0k))));
    return Math.max(toFinite(divider.R0, 10000), 1e-12) * Math.exp(exponent);
};
// 目的: 設計解析ツールのdivider Temp For Resistanceを扱う。機能: 入力値を検証・整形し、画面または計算処理へ渡す。入力: 関数シグネチャの値。出力: 処理結果またはなし。動作条件: 設計解析ツールの初期化後に呼び出す。副作用: Vue状態、localStorage、DOM、HTTP通信のいずれかを更新する場合がある。
const dividerTempForResistance = (resistance) => {
    const beta = Math.max(Math.abs(toFinite(divider.B, 3950)), 1e-12);
    const t0k = Math.max(toFinite(divider.T0) + 273.15, 1e-12);
    const ratio = Math.max(toFinite(resistance, divider.R0), 1e-12) / Math.max(toFinite(divider.R0, 10000), 1e-12);
    const invTk = (1 / t0k) + (Math.log(ratio) / (dividerThermistorDirection() * beta));
    if (!Number.isFinite(invTk) || invTk <= 0) return Number.NaN;
    return (1 / invTk) - 273.15;
};
// 目的: 設計解析ツールのdivider Voltage For Resistanceを扱う。機能: 入力値を検証・整形し、画面または計算処理へ渡す。入力: 関数シグネチャの値。出力: 処理結果またはなし。動作条件: 設計解析ツールの初期化後に呼び出す。副作用: Vue状態、localStorage、DOM、HTTP通信のいずれかを更新する場合がある。
const dividerVoltageForResistance = (resistance, fixedResistor = divider.fixedResistor) => {
    const rTherm = Math.max(toFinite(resistance), 1e-12);
    const rFixed = Math.max(toFinite(fixedResistor, divider.R0), 1e-12);
    const vref = Math.max(toFinite(divider.adcVref, 3.3), 1e-12);
    return divider.position === 'high'
        ? vref * rFixed / (rFixed + rTherm)
        : vref * rTherm / (rFixed + rTherm);
};
// 目的: 設計解析ツールのdivider Power For Resistanceを扱う。機能: 入力値を検証・整形し、画面または計算処理へ渡す。入力: 関数シグネチャの値。出力: 処理結果またはなし。動作条件: 設計解析ツールの初期化後に呼び出す。副作用: Vue状態、localStorage、DOM、HTTP通信のいずれかを更新する場合がある。
const dividerPowerForResistance = (resistance, fixedResistor = divider.fixedResistor) => {
    const rTherm = Math.max(toFinite(resistance), 1e-12);
    const rFixed = Math.max(toFinite(fixedResistor, divider.R0), 1e-12);
    const vref = Math.max(toFinite(divider.adcVref, 3.3), 0);
    const current = vref / Math.max(rFixed + rTherm, 1e-12);
    return current * current * rTherm;
};
// 目的: 設計解析ツールのdivider Code For Voltageを扱う。機能: 入力値を検証・整形し、画面または計算処理へ渡す。入力: 関数シグネチャの値。出力: 処理結果またはなし。動作条件: 設計解析ツールの初期化後に呼び出す。副作用: Vue状態、localStorage、DOM、HTTP通信のいずれかを更新する場合がある。
const dividerCodeForVoltage = (voltage) => {
    const fullScale = 2 ** Math.max(Math.round(toFinite(divider.adcBits, 12)), 1) - 1;
    return Math.max(0, Math.min(fullScale, Math.round(voltage / Math.max(toFinite(divider.adcVref, 3.3), 1e-12) * fullScale)));
};
const dividerResult = computed(() => {
    const Tc = dividerTempForResistance(divider.Rmeas);
    const Tk = Tc + 273.15;
    const measuredVoltage = dividerVoltageForResistance(divider.Rmeas);
    const powerW = dividerPowerForResistance(divider.Rmeas);
    const selfHeatC = powerW * 1000 / Math.max(toFinite(divider.dissipationMwPerC, 2), 1e-12);
    return {
        temp_c: Number.isFinite(Tc) ? Tc.toFixed(2) : '--',
        temp_k: Number.isFinite(Tk) ? Tk.toFixed(2) : '--',
        voltage_v: formatNumber(measuredVoltage, 4, 'V'),
        adc_code: `${dividerCodeForVoltage(measuredVoltage)}`,
        power_mw: (powerW * 1000).toFixed(4),
        self_heat_c: selfHeatC.toFixed(3),
    };
});
const dividerGraph = computed(() => {
    const minTemp = Math.min(toFinite(divider.tempMin, -20), toFinite(divider.tempMax, 85));
    const maxTemp = Math.max(toFinite(divider.tempMin, -20), toFinite(divider.tempMax, 85));
    const targetMin = Math.min(toFinite(divider.targetTempMin, minTemp), toFinite(divider.targetTempMax, maxTemp));
    const targetMax = Math.max(toFinite(divider.targetTempMin, minTemp), toFinite(divider.targetTempMax, maxTemp));
    const span = Math.max(maxTemp - minTemp, 1e-9);
    const sampleCount = 96;
    const width = 640;
    const height = 260;
    const plot = { left: 52, top: 22, width: 540, height: 152 };
    const sensitivityPlot = { left: 52, top: 198, width: 540, height: 42 };
    const vref = Math.max(toFinite(divider.adcVref, 3.3), 1e-12);
    const fixedResistor = Math.max(toFinite(divider.fixedResistor, divider.R0), 1e-12);
    const fixedTolerance = Math.max(toFinite(divider.fixedResistorTolerancePct, 0), 0) / 100;
    const thermistorTolerance = Math.max(toFinite(divider.thermistorTolerancePct, 0), 0) / 100;
    const dissipationMwPerC = Math.max(toFinite(divider.dissipationMwPerC, 2), 1e-12);
    // 目的: 設計解析ツールのbuild Sampleを扱う。機能: 入力値を検証・整形し、画面または計算処理へ渡す。入力: 関数シグネチャの値。出力: 表示値、配列、オブジェクト、数値のいずれか。動作条件: 設計解析ツールの初期化後に呼び出す。副作用: なし。
    const buildSample = (temp) => {
        const resistance = dividerResistanceAtTemp(temp);
        const voltage = dividerVoltageForResistance(resistance, fixedResistor);
        const toleranceVoltages = [
            dividerVoltageForResistance(resistance * (1 - thermistorTolerance), fixedResistor * (1 - fixedTolerance)),
            dividerVoltageForResistance(resistance * (1 - thermistorTolerance), fixedResistor * (1 + fixedTolerance)),
            dividerVoltageForResistance(resistance * (1 + thermistorTolerance), fixedResistor * (1 - fixedTolerance)),
            dividerVoltageForResistance(resistance * (1 + thermistorTolerance), fixedResistor * (1 + fixedTolerance)),
        ];
        const toleranceMinVoltage = Math.min(...toleranceVoltages);
        const toleranceMaxVoltage = Math.max(...toleranceVoltages);
        const powerW = dividerPowerForResistance(resistance, fixedResistor);
        const sensitivity = Math.abs(
            dividerVoltageForResistance(dividerResistanceAtTemp(temp + 0.25), fixedResistor)
            - dividerVoltageForResistance(dividerResistanceAtTemp(temp - 0.25), fixedResistor)
        ) / 0.5;
        const voltageToleranceHalf = Math.max(Math.abs(voltage - toleranceMinVoltage), Math.abs(toleranceMaxVoltage - voltage));
        const toleranceTempError = sensitivity > 0 ? voltageToleranceHalf / sensitivity : Number.POSITIVE_INFINITY;
        return {
            temp,
            resistance,
            voltage,
            toleranceMinVoltage,
            toleranceMaxVoltage,
            sensitivity,
            toleranceTempError,
            powerW,
            selfHeatC: powerW * 1000 / dissipationMwPerC,
            code: dividerCodeForVoltage(voltage),
        };
    };
    const samples = Array.from({ length: sampleCount }, (_, index) => {
        const ratio = sampleCount <= 1 ? 0 : index / (sampleCount - 1);
        return buildSample(minTemp + span * ratio);
    });
    const maxSensitivity = Math.max(...samples.map((sample) => sample.sensitivity), 1e-12);
    const best = samples.reduce((max, sample) => sample.sensitivity > max.sensitivity ? sample : max, samples[0]);
    const worst = samples.reduce((min, sample) => sample.sensitivity < min.sensitivity ? sample : min, samples[0]);
    const targetEvalTemps = [...new Set([targetMin, (targetMin + targetMax) / 2, targetMax])]
        .filter((temp) => temp >= minTemp && temp <= maxTemp);
    const targetSamples = [
        ...samples.filter((sample) => sample.temp >= targetMin && sample.temp <= targetMax),
        ...targetEvalTemps.map((temp) => buildSample(temp)),
    ];
    const targetAverageSensitivity = targetSamples.length
        ? targetSamples.reduce((sum, sample) => sum + sample.sensitivity, 0) / targetSamples.length
        : 0;
    const targetMinimumSensitivity = targetSamples.length
        ? Math.min(...targetSamples.map((sample) => sample.sensitivity))
        : 0;
    const targetMaxPower = targetSamples.length
        ? Math.max(...targetSamples.map((sample) => sample.powerW))
        : Math.max(...samples.map((sample) => sample.powerW), 0);
    const targetMaxSelfHeat = targetSamples.length
        ? Math.max(...targetSamples.map((sample) => sample.selfHeatC))
        : Math.max(...samples.map((sample) => sample.selfHeatC), 0);
    const targetMaxToleranceTempError = targetSamples.length
        ? Math.max(...targetSamples.map((sample) => sample.toleranceTempError).filter(Number.isFinite), 0)
        : Math.max(...samples.map((sample) => sample.toleranceTempError).filter(Number.isFinite), 0);
    const targetMaxVoltageBand = targetSamples.length
        ? Math.max(...targetSamples.map((sample) => sample.toleranceMaxVoltage - sample.toleranceMinVoltage), 0)
        : Math.max(...samples.map((sample) => sample.toleranceMaxVoltage - sample.toleranceMinVoltage), 0);
    const targetRatio = targetAverageSensitivity / maxSensitivity;
    const targetInsideSweep = targetMin >= minTemp && targetMax <= maxTemp;
    const sensitivityTone = !targetInsideSweep || !targetSamples.length || targetRatio < 0.25
        ? 'bad'
        : (targetRatio < 0.6 || targetMinimumSensitivity / maxSensitivity < 0.35 || targetMaxSelfHeat > 0.5 ? 'warn' : 'ok');
    // 目的: 設計解析ツールのx Forを扱う。機能: 入力値を検証・整形し、画面または計算処理へ渡す。入力: 関数シグネチャの値。出力: 処理結果またはなし。動作条件: 設計解析ツールの初期化後に呼び出す。副作用: Vue状態、localStorage、DOM、HTTP通信のいずれかを更新する場合がある。
    const xFor = (temp) => plot.left + (temp - minTemp) / span * plot.width;
    // 目的: 設計解析ツールのy For Voltageを扱う。機能: 入力値を検証・整形し、画面または計算処理へ渡す。入力: 関数シグネチャの値。出力: 処理結果またはなし。動作条件: 設計解析ツールの初期化後に呼び出す。副作用: Vue状態、localStorage、DOM、HTTP通信のいずれかを更新する場合がある。
    const yForVoltage = (voltage) => plot.top + (1 - Math.max(0, Math.min(vref, voltage)) / vref) * plot.height;
    // 目的: 設計解析ツールのy For Sensitivityを扱う。機能: 入力値を検証・整形し、画面または計算処理へ渡す。入力: 関数シグネチャの値。出力: 処理結果またはなし。動作条件: 設計解析ツールの初期化後に呼び出す。副作用: Vue状態、localStorage、DOM、HTTP通信のいずれかを更新する場合がある。
    const yForSensitivity = (sensitivity) => sensitivityPlot.top + (1 - Math.max(0, Math.min(maxSensitivity, sensitivity)) / maxSensitivity) * sensitivityPlot.height;
    const points = samples.map((sample) => `${xFor(sample.temp).toFixed(1)},${yForVoltage(sample.voltage).toFixed(1)}`).join(' ');
    const toleranceMinPoints = samples.map((sample) => `${xFor(sample.temp).toFixed(1)},${yForVoltage(sample.toleranceMinVoltage).toFixed(1)}`).join(' ');
    const toleranceMaxPoints = samples.map((sample) => `${xFor(sample.temp).toFixed(1)},${yForVoltage(sample.toleranceMaxVoltage).toFixed(1)}`).join(' ');
    const sensitivityPoints = samples.map((sample) => `${xFor(sample.temp).toFixed(1)},${yForSensitivity(sample.sensitivity).toFixed(1)}`).join(' ');
    const measuredTemp = dividerTempForResistance(divider.Rmeas);
    const measuredVoltage = dividerVoltageForResistance(divider.Rmeas, fixedResistor);
    const targetBandX = Math.max(plot.left, Math.min(plot.left + plot.width, xFor(targetMin)));
    const targetBandRight = Math.max(plot.left, Math.min(plot.left + plot.width, xFor(targetMax)));
    // 目的: 設計解析ツールのrange Labelを扱う。機能: 入力値を検証・整形し、画面または計算処理へ渡す。入力: 関数シグネチャの値。出力: 処理結果またはなし。動作条件: 設計解析ツールの初期化後に呼び出す。副作用: Vue状態、localStorage、DOM、HTTP通信のいずれかを更新する場合がある。
    const rangeLabel = (predicate) => {
        const ranges = [];
        let start = null;
        let last = null;
        for (const sample of samples) {
            if (predicate(sample)) {
                if (start === null) start = sample.temp;
                last = sample.temp;
            } else if (start !== null) {
                ranges.push(`${start.toFixed(0)}-${last.toFixed(0)}C`);
                start = null;
                last = null;
            }
        }
        if (start !== null) ranges.push(`${start.toFixed(0)}-${last.toFixed(0)}C`);
        return ranges.slice(0, 3).join(' / ') || '-';
    };
    const fixedCandidates = [
        fixedResistor,
        ...String(divider.pullupCandidates).split(/[\s,;]+/).filter(Boolean).map((value) => parseNumber(value)).filter((value) => value > 0),
    ];
    const uniqueFixedCandidates = [...new Set(fixedCandidates.map((value) => Math.round(value * 1e6) / 1e6))].slice(0, 5);
    const candidateSensitivity = uniqueFixedCandidates.map((candidate) => {
        const values = targetSamples.length ? targetSamples : samples;
        const sensitivities = values.map((sample) => Math.abs(
            dividerVoltageForResistance(dividerResistanceAtTemp(sample.temp + 0.25), candidate)
            - dividerVoltageForResistance(dividerResistanceAtTemp(sample.temp - 0.25), candidate)
        ) / 0.5);
        const average = sensitivities.reduce((sum, value) => sum + value, 0) / Math.max(sensitivities.length, 1);
        return {
            resistor: candidate,
            label: `${formatNumber(candidate, 0, 'Ω')} -> ${(average * 1000).toFixed(2)} mV/℃`,
            average,
        };
    }).sort((a, b) => b.average - a.average);
    return {
        width,
        height,
        plot,
        sensitivityPlot,
        maxSensitivity,
        points,
        toleranceMinPoints,
        toleranceMaxPoints,
        sensitivityPoints,
        targetBand: {
            x: targetBandX,
            width: Math.max(0, targetBandRight - targetBandX),
        },
        measuredPoint: Number.isFinite(measuredTemp)
            ? { x: xFor(measuredTemp), y: yForVoltage(measuredVoltage), temp: measuredTemp, voltage: measuredVoltage }
            : null,
        bestPoint: { x: xFor(best.temp), y: yForSensitivity(best.sensitivity), voltageY: yForVoltage(best.voltage), temp: best.temp, voltage: best.voltage, sensitivity: best.sensitivity },
        axis: {
            minTemp: `${minTemp.toFixed(0)}℃`,
            maxTemp: `${maxTemp.toFixed(0)}℃`,
            vref: `${vref.toFixed(2)}V`,
            zero: '0V',
        },
        voltageRange: `${Math.min(...samples.map((sample) => sample.voltage)).toFixed(3)}-${Math.max(...samples.map((sample) => sample.voltage)).toFixed(3)} V`,
        bestSensitivity: `${(best.sensitivity * 1000).toFixed(3)} mV/℃ @ ${best.temp.toFixed(1)}℃`,
        worstSensitivity: `${(worst.sensitivity * 1000).toFixed(3)} mV/℃ @ ${worst.temp.toFixed(1)}℃`,
        targetSensitivity: `${(targetAverageSensitivity * 1000).toFixed(3)} mV/℃ 平均 / ${(targetMinimumSensitivity * 1000).toFixed(3)} 最小`,
        targetPower: `${(targetMaxPower * 1000).toFixed(4)} mW 最大`,
        targetSelfHeat: `${targetMaxSelfHeat.toFixed(3)} ℃ 最大`,
        targetToleranceTempError: `${targetMaxToleranceTempError.toFixed(3)} ℃ 最大`,
        targetVoltageToleranceBand: `${(targetMaxVoltageBand * 1000).toFixed(3)} mV 最大`,
        targetRatio,
        targetInsideSweep,
        sensitivityTone,
        highSensitivityRange: rangeLabel((sample) => sample.sensitivity / maxSensitivity >= 0.6),
        lowSensitivityRange: rangeLabel((sample) => sample.sensitivity / maxSensitivity <= 0.25),
        candidateSensitivity,
        samples,
    };
});
const dividerGraphTooltip = computed(() => {
    if (!dividerGraphCursor.active) return null;
    return {
        x: Math.max(76, Math.min(500, dividerGraphCursor.x + 12)),
        y: Math.max(34, Math.min(204, dividerGraphCursor.y - 58)),
        temp: `${dividerGraphCursor.temp.toFixed(1)} ℃`,
        voltage: `${dividerGraphCursor.voltage.toFixed(4)} V`,
        sensitivity: `${(dividerGraphCursor.sensitivity * 1000).toFixed(3)} mV/℃`,
    };
});
// 目的: 設計解析ツールのupdate Divider Graph Cursorを扱う。機能: 入力値を検証・整形し、画面または計算処理へ渡す。入力: 関数シグネチャの値。出力: 処理結果またはなし。動作条件: 設計解析ツールの初期化後に呼び出す。副作用: Vue状態、localStorage、DOM、HTTP通信のいずれかを更新する場合がある。
const updateDividerGraphCursor = (event) => {
    const svg = event?.currentTarget;
    if (!svg?.createSVGPoint) return;
    const graph = dividerGraph.value;
    const point = svg.createSVGPoint();
    point.x = event.clientX;
    point.y = event.clientY;
    const cursor = point.matrixTransform(svg.getScreenCTM().inverse());
    const x = Math.max(graph.plot.left, Math.min(graph.plot.left + graph.plot.width, cursor.x));
    const ratio = (x - graph.plot.left) / Math.max(graph.plot.width, 1e-12);
    const minTemp = parseNumber(graph.axis.minTemp);
    const maxTemp = parseNumber(graph.axis.maxTemp);
    const temp = minTemp + (maxTemp - minTemp) * ratio;
    const resistance = dividerResistanceAtTemp(temp);
    const voltage = dividerVoltageForResistance(resistance);
    const sensitivity = Math.abs(
        dividerVoltageForResistance(dividerResistanceAtTemp(temp + 0.25))
        - dividerVoltageForResistance(dividerResistanceAtTemp(temp - 0.25))
    ) / 0.5;
    const vref = Math.max(toFinite(divider.adcVref, 3.3), 1e-12);
    const y = graph.plot.top + (1 - Math.max(0, Math.min(vref, voltage)) / vref) * graph.plot.height;
    Object.assign(dividerGraphCursor, {
        active: true,
        x,
        y,
        temp,
        voltage,
        sensitivity,
    });
};
// 目的: 設計解析ツールのclear Divider Graph Cursorを扱う。機能: 入力値を検証・整形し、画面または計算処理へ渡す。入力: 関数シグネチャの値。出力: 処理結果またはなし。動作条件: 設計解析ツールの初期化後に呼び出す。副作用: Vue状態、localStorage、DOM、HTTP通信のいずれかを更新する場合がある。
const clearDividerGraphCursor = () => {
    dividerGraphCursor.active = false;
};

// 電流検出
const shunt = reactive({
    Rs: 0.01,    // シャント抵抗(Ω)
    gain: 20,    // アンプゲイン
    senseMode: 'unipolar', // 'unipolar' | 'bidirectional'
    I: 5,        // 電流(A) → Vout計算用
    currentMin: 0,
    currentMax: 5,
    outputOffset: 1.65,
    powerRating: 0.25,
    tcrPpm: 50,
    ampOffsetUv: 50,
    adcBits: 12,
    adcVref: 3.3,
    ampOutputMin: 0,
    ampOutputMax: 3.3,
});
const shuntResult = computed(() => {
    const rs = Math.max(toFinite(shunt.Rs), 1e-12);
    const gain = Math.max(toFinite(shunt.gain), 1e-12);
    const bits = Math.max(1, Math.min(32, Math.round(toFinite(shunt.adcBits, 12))));
    const adcMin = 0;
    const adcMax = Math.max(toFinite(shunt.adcVref, 3.3), 1e-12);
    const adcSpan = Math.max(adcMax - adcMin, 1e-12);
    const maxCode = (2 ** bits) - 1;
    const ampMin = Math.min(toFinite(shunt.ampOutputMin), toFinite(shunt.ampOutputMax, adcMax));
    const ampMax = Math.max(toFinite(shunt.ampOutputMin), toFinite(shunt.ampOutputMax, adcMax));
    const ampSpan = Math.max(ampMax - ampMin, 1e-12);
    const zeroOffset = shunt.senseMode === 'bidirectional' ? toFinite(shunt.outputOffset, adcMax / 2) : 0;
    const adcLsbMv = adcSpan / maxCode * 1000;
    const offsetErrorA = toFinite(shunt.ampOffsetUv) / 1e6 / rs;
    const offsetOutputMv = toFinite(shunt.ampOffsetUv) * gain / 1000;
    const offsetOutputV = Math.abs(offsetOutputMv) / 1000;
    const adcLsbA = adcLsbMv / 1000 / Math.max(rs * gain, 1e-12);
    const hasPowerRatingValue = hasRating(shunt.powerRating);
    // 目的: 設計解析ツールのadc Code Forを扱う。機能: 入力値を検証・整形し、画面または計算処理へ渡す。入力: 関数シグネチャの値。出力: 処理結果またはなし。動作条件: 設計解析ツールの初期化後に呼び出す。副作用: Vue状態、localStorage、DOM、HTTP通信のいずれかを更新する場合がある。
    const adcCodeFor = (vout) => {
        const raw = ((vout - adcMin) / adcSpan) * maxCode;
        const clamped = Math.max(0, Math.min(maxCode, Math.floor(raw)));
        return {
            raw,
            clamped,
            bin: clamped.toString(2).padStart(bits, '0'),
            hex: `0x${clamped.toString(16).toUpperCase()}`,
            pct: (clamped / maxCode * 100),
        };
    };
    // 目的: 設計解析ツールのpoint From Currentを扱う。機能: 入力値を検証・整形し、画面または計算処理へ渡す。入力: 関数シグネチャの値。出力: 処理結果またはなし。動作条件: 設計解析ツールの初期化後に呼び出す。副作用: Vue状態、localStorage、DOM、HTTP通信のいずれかを更新する場合がある。
    const pointFromCurrent = (current) => {
        const vshunt = current * rs;
        const vout = zeroOffset + (vshunt * gain);
        const p = current * current * rs;
        const code = adcCodeFor(vout);
        return {
            current,
            vshunt,
            vout,
            p,
            code,
            adcLowerMargin: vout - offsetOutputV - adcMin,
            adcUpperMargin: adcMax - (vout + offsetOutputV),
            ampLowerMargin: vout - offsetOutputV - ampMin,
            ampUpperMargin: ampMax - (vout + offsetOutputV),
        };
    };
    const point = pointFromCurrent(toFinite(shunt.I));
    const inputMinCurrent = toFinite(shunt.currentMin);
    const inputMaxCurrent = toFinite(shunt.currentMax);
    const rangeLowCurrent = Math.min(inputMinCurrent, inputMaxCurrent);
    const rangeHighCurrent = Math.max(inputMinCurrent, inputMaxCurrent);
    const inputMinPoint = pointFromCurrent(inputMinCurrent);
    const inputMaxPoint = pointFromCurrent(inputMaxCurrent);
    const rangeLowPoint = pointFromCurrent(rangeLowCurrent);
    const rangeHighPoint = pointFromCurrent(rangeHighCurrent);
    const rangeVoutMin = Math.min(rangeLowPoint.vout, rangeHighPoint.vout);
    const rangeVoutMax = Math.max(rangeLowPoint.vout, rangeHighPoint.vout);
    const maxCurrentForLoss = Math.max(Math.abs(rangeLowCurrent), Math.abs(rangeHighCurrent), Math.abs(point.current));
    const rangePowerW = maxCurrentForLoss * maxCurrentForLoss * rs;
    const adcRangeUsedPct = (rangeVoutMax - rangeVoutMin) / adcSpan * 100;
    const ampRangeUsedPct = (rangeVoutMax - rangeVoutMin) / ampSpan * 100;
    const adcRangeTypLowerMargin = rangeVoutMin - adcMin;
    const adcRangeTypUpperMargin = adcMax - rangeVoutMax;
    const ampRangeTypLowerMargin = rangeVoutMin - ampMin;
    const ampRangeTypUpperMargin = ampMax - rangeVoutMax;
    const adcRangeLowerMargin = rangeVoutMin - offsetOutputV - adcMin;
    const adcRangeUpperMargin = adcMax - (rangeVoutMax + offsetOutputV);
    const ampRangeLowerMargin = rangeVoutMin - offsetOutputV - ampMin;
    const ampRangeUpperMargin = ampMax - (rangeVoutMax + offsetOutputV);
    return {
        I: point.current.toFixed(4),
        Vout: point.vout.toFixed(4),
        measured_current_a: point.current.toFixed(4),
        vout_effective_min_v: (point.vout - Math.abs(offsetOutputMv) / 1000).toFixed(4),
        vout_effective_max_v: (point.vout + Math.abs(offsetOutputMv) / 1000).toFixed(4),
        P_mW: (point.p * 1000).toFixed(3),
        range_P_mW: (rangePowerW * 1000).toFixed(3),
        Vshunt_mv: (point.vshunt * 1000).toFixed(3),
        adc_lsb_mv: adcLsbMv.toFixed(4),
        adc_lsb_a: adcLsbA.toFixed(6),
        offset_error_a: offsetErrorA.toFixed(5),
        offset_error_pct: (Math.abs(offsetErrorA) / Math.max(Math.abs(point.current), 1e-12) * 100).toFixed(3),
        offset_output_mv: offsetOutputMv.toFixed(4),
        zero_offset_v: zeroOffset.toFixed(4),
        vout_value: point.vout.toFixed(4),
        adc_code: String(point.code.clamped),
        adc_code_raw: point.code.raw.toFixed(2),
        adc_bin: point.code.bin,
        adc_hex: point.code.hex,
        adc_code_pct: point.code.pct.toFixed(2),
        adc_margin_high_v: point.adcUpperMargin.toFixed(4),
        adc_margin_low_v: point.adcLowerMargin.toFixed(4),
        amp_margin_high_v: point.ampUpperMargin.toFixed(4),
        amp_margin_low_v: point.ampLowerMargin.toFixed(4),
        adc_range_used_pct: adcRangeUsedPct.toFixed(2),
        amp_range_used_pct: ampRangeUsedPct.toFixed(2),
        adc_range_margin_low_v: adcRangeLowerMargin.toFixed(4),
        adc_range_margin_high_v: adcRangeUpperMargin.toFixed(4),
        amp_range_margin_low_v: ampRangeLowerMargin.toFixed(4),
        amp_range_margin_high_v: ampRangeUpperMargin.toFixed(4),
        adc_range_typ_margin_low_v: adcRangeTypLowerMargin.toFixed(4),
        adc_range_typ_margin_high_v: adcRangeTypUpperMargin.toFixed(4),
        amp_range_typ_margin_low_v: ampRangeTypLowerMargin.toFixed(4),
        amp_range_typ_margin_high_v: ampRangeTypUpperMargin.toFixed(4),
        input_current_min_a: inputMinCurrent.toFixed(4),
        input_current_max_a: inputMaxCurrent.toFixed(4),
        vout_at_imin_v: inputMinPoint.vout.toFixed(4),
        vout_at_imax_v: inputMaxPoint.vout.toFixed(4),
        range_current_min_a: rangeLowCurrent.toFixed(4),
        range_current_max_a: rangeHighCurrent.toFixed(4),
        range_vout_min_v: rangeVoutMin.toFixed(4),
        range_vout_max_v: rangeVoutMax.toFixed(4),
        range_vout_worst_min_v: (rangeVoutMin - offsetOutputV).toFixed(4),
        range_vout_worst_max_v: (rangeVoutMax + offsetOutputV).toFixed(4),
        power_margin_mw: hasPowerRatingValue ? ((toFinite(shunt.powerRating) - rangePowerW) * 1000).toFixed(3) : 'CHECK',
    };
});

// 電源余裕解析
const power = reactive({
    supply_w: 10,
    efficiencyPct: 85,
    dropoutV: 0.3,
    inrushA: 1.5,
    maxLoadFactor: 1.5,
    rails: 'VIN,,12,2\n3V3,VIN,3.3,0.4\n1V8,3V3,1.8,0.2',
    loads: [{ label: 'MCU', mA: 50, V: 3.3, rail: '3V3' }, { label: 'Sensor', mA: 20, V: 3.3, rail: '3V3' }],
});
// 目的: 設計解析ツールのadd Loadを扱う。機能: 入力値を検証・整形し、画面または計算処理へ渡す。入力: 関数シグネチャの値。出力: 処理結果またはなし。動作条件: 設計解析ツールの初期化後に呼び出す。副作用: Vue状態、localStorage、DOM、HTTP通信のいずれかを更新する場合がある。
const addLoad   = () => power.loads.push({ label: '', mA: 0, V: 3.3, rail: '' });
// 目的: 設計解析ツールのremove Loadを扱う。機能: 入力値を検証・整形し、画面または計算処理へ渡す。入力: 関数シグネチャの値。出力: 処理結果またはなし。動作条件: 設計解析ツールの初期化後に呼び出す。副作用: Vue状態、localStorage、DOM、HTTP通信のいずれかを更新する場合がある。
const removeLoad = (i) => power.loads.splice(i, 1);
const powerResult = computed(() => {
    const loadItems = power.loads.map((load, index) => ({
        label: load.label || `負荷${index + 1}`,
        mA: toFinite(load.mA),
        voltage: toFinite(load.V),
        rail: String(load.rail ?? '').trim(),
        watts: toFinite(load.mA) * toFinite(load.V) / 1000,
    }));
    const totalW  = loadItems.reduce((s, load) => s + load.watts, 0);
    const supplyW = toFinite(power.supply_w);
    const rails = String(power.rails).split('\n')
        .map((row) => row.split(',').map((v) => v.trim()))
        .filter((row) => row[0])
        .map((row) => ({
            name: row[0],
            parent: row[1] || '-',
            voltage: toFinite(row[2]),
            current: toFinite(row[3]),
            capacityW: toFinite(row[2]) * toFinite(row[3]),
            directLoadW: 0,
            rolledLoadW: 0,
            marginW: 0,
            loadNames: [],
            dropoutMargin: null,
            overloaded: false,
        }));
    const railByName = new Map(rails.map((rail) => [rail.name, rail]));
    const assignedLoadIndexes = new Set();
    loadItems.forEach((load, index) => {
        const explicitRail = load.rail ? railByName.get(load.rail) : null;
        const match = load.rail ? explicitRail : rails.reduce((best, rail) => {
            const tolerance = Math.max(Math.abs(rail.voltage) * 0.03, 0.05);
            const distance = Math.abs(load.voltage - rail.voltage);
            return distance <= tolerance && distance < best.distance ? { rail, distance } : best;
        }, { rail: null, distance: Infinity }).rail;
        if (!match) return;
        match.directLoadW += load.watts;
        match.loadNames.push(load.label);
        assignedLoadIndexes.add(index);
    });
    const childrenByParent = new Map();
    rails.forEach((rail) => {
        if (!rail.parent || rail.parent === '-' || !railByName.has(rail.parent)) return;
        const children = childrenByParent.get(rail.parent) ?? [];
        children.push(rail);
        childrenByParent.set(rail.parent, children);
    });
    const efficiency = Math.max(toFinite(power.efficiencyPct) / 100, 0.01);
    const rollup = (rail, seen = new Set()) => {
        if (seen.has(rail.name)) return rail.directLoadW;
        seen.add(rail.name);
        const childInputW = (childrenByParent.get(rail.name) ?? [])
            .reduce((sum, child) => sum + rollup(child, new Set(seen)) / efficiency, 0);
        rail.rolledLoadW = rail.directLoadW + childInputW;
        rail.marginW = rail.capacityW - rail.rolledLoadW;
        const parent = railByName.get(rail.parent);
        rail.dropoutMargin = parent ? parent.voltage - rail.voltage - power.dropoutV : null;
        rail.overloaded = rail.capacityW > 0 && rail.rolledLoadW > rail.capacityW;
        return rail.rolledLoadW;
    };
    rails.forEach((rail) => rollup(rail));
    const unassignedLoads = loadItems.filter((_, index) => !assignedLoadIndexes.has(index));
    const unassignedW = unassignedLoads.reduce((sum, load) => sum + load.watts, 0);
    const roots = rails.filter((rail) => !rail.parent || rail.parent === '-' || !railByName.has(rail.parent));
    // 目的: 設計解析ツールのinput Equivalent Wを扱う。機能: 入力値を検証・整形し、画面または計算処理へ渡す。入力: 関数シグネチャの値。出力: 処理結果またはなし。動作条件: 設計解析ツールの初期化後に呼び出す。副作用: Vue状態、localStorage、DOM、HTTP通信のいずれかを更新する場合がある。
    const inputEquivalentW = (roots.length ? roots.reduce((sum, rail) => sum + rail.rolledLoadW, 0) : 0) + unassignedW;
    const maxScenarioW = totalW * power.maxLoadFactor;
    const inputMaxScenarioW = inputEquivalentW * power.maxLoadFactor;
    const margin  = supplyW - totalW;
    const inputEquivalentMargin = supplyW - inputEquivalentW;
    const worstMargin = supplyW - maxScenarioW;
    const inputWorstMargin = supplyW - inputMaxScenarioW;
    const percent = (totalW / Math.max(supplyW, 1e-12) * 100).toFixed(1);
    const railOk = rails.every((rail) => !rail.overloaded);
    const supplyExceeded = margin < 0 || worstMargin < 0 || inputEquivalentMargin < 0 || inputWorstMargin < 0;
    const capacityExceeded = supplyExceeded || !railOk;
    return {
        totalW: totalW.toFixed(3),
        unassignedW: unassignedW.toFixed(3),
        inputEquivalentW: inputEquivalentW.toFixed(3),
        inputEquivalentMargin: inputEquivalentMargin.toFixed(3),
        margin: margin.toFixed(3),
        worstMargin: worstMargin.toFixed(3),
        inputWorstMargin: inputWorstMargin.toFixed(3),
        percent,
        ok: !capacityExceeded,
        capacityExceeded,
        supplyExceeded,
        railMargins: rails,
        unassignedLoads,
        maxScenarioW: maxScenarioW.toFixed(3),
        inputMaxScenarioW: inputMaxScenarioW.toFixed(3),
    };
});

const {
    battery,
    batteryProfiles,
    batteryResult,
    batteryGraph,
    batteryCapacityPie,
    batteryGraphCursor,
    batteryGraphTooltipBox,
    updateBatteryGraphCursor,
    clearBatteryGraphCursor,
    addBatteryLoad,
    removeBatteryLoad,
    formatRuntimeText,
} = setupBatteryRuntimeTool({ toFinite, hasRating, parseNumber });

// 比較器しきい値/ヒステリシス
const comp = reactive({
    referenceMode: 'external',
    inputPolarity: 'positive',
    Vcc: 3.3, VOH: 3.3, VOL: 0, R1: 100000, R2: 100000, R3: 1000000, R4: 100000, Vref: 1.65, tolerancePct: 1, inputOffsetMv: 5, inputBiasNa: 50, noiseMv: 20, candidateResistors: '10000,47000,100000',
});
const compResult = computed(() => {
    const positiveInput = comp.inputPolarity !== 'negative';
    const r1Raw = Math.max(toFinite(comp.R1), 0);
    const r1 = Math.max(r1Raw, 1e-12);
    const r2 = Math.max(toFinite(comp.R2), 1e-12);
    const r3Raw = toFinite(comp.R3);
    const r3Connected = r3Raw > 0;
    const r3 = Math.max(r3Raw, 1e-12);
    const r4 = Math.max(toFinite(comp.R4), 1e-12);
    const dividerReference = comp.referenceMode === 'divider';
    const referenceVoltage = dividerReference
        ? toFinite(comp.Vcc) * r4 / (r2 + r4)
        : toFinite(comp.Vref);
    const referenceThevenin = dividerReference ? (r2 * r4) / (r2 + r4) : 0;
    const tolerancePct = Math.abs(toFinite(comp.tolerancePct));
    const inputOffsetV = Math.abs(toFinite(comp.inputOffsetMv)) / 1000;
    const inputBiasNa = Math.abs(toFinite(comp.inputBiasNa));
    const noiseV = Math.abs(toFinite(comp.noiseMv)) / 1000;
    const negativeDividerReference = !positiveInput && dividerReference;
    const sourceResistance = positiveInput
        ? r1
        : Math.max((negativeDividerReference ? 0 : r1Raw) + referenceThevenin, 1e-12);
    const feedbackRatio = positiveInput
        ? (r3Connected ? r1 / r3 : 0)
        : (r3Connected ? sourceResistance / (sourceResistance + r3) : 0);
    // 目的: 設計解析ツールのthreshold For Outputを扱う。機能: 入力値を検証・整形し、画面または計算処理へ渡す。入力: 関数シグネチャの値。出力: 処理結果またはなし。動作条件: 設計解析ツールの初期化後に呼び出す。副作用: Vue状態、localStorage、DOM、HTTP通信のいずれかを更新する場合がある。
    const thresholdForOutput = (vout) => {
        if (!r3Connected) return referenceVoltage;
        return positiveInput
            ? referenceVoltage * (1 + feedbackRatio) - toFinite(vout) * feedbackRatio
            : (referenceVoltage * r3 + toFinite(vout) * sourceResistance) / (sourceResistance + r3);
    };
    const Vth_rising = positiveInput ? thresholdForOutput(comp.VOL) : thresholdForOutput(comp.VOH);
    const Vth_falling = positiveInput ? thresholdForOutput(comp.VOH) : thresholdForOutput(comp.VOL);
    const hyst     = Math.abs(Vth_rising - Vth_falling);
    const Vth_high = Math.max(Vth_rising, Vth_falling);
    const Vth_low  = Math.min(Vth_rising, Vth_falling);
    const inputEquivalentOhm = positiveInput ? (r3Connected ? (r1 * r3) / (r1 + r3) : r1) : 0;
    const referenceEquivalentOhm = positiveInput
        ? referenceThevenin
        : (r3Connected ? (sourceResistance * r3) / (sourceResistance + r3) : sourceResistance);
    const inputBiasErrorV = inputBiasNa * 1e-9 * inputEquivalentOhm;
    const referenceBiasErrorV = inputBiasNa * 1e-9 * referenceEquivalentOhm;
    const toleranceBase = Math.max(Math.abs(Vth_high), Math.abs(Vth_low), Math.abs(referenceVoltage), 1e-12);
    const toleranceBand = toleranceBase * tolerancePct / 100 + inputOffsetV + inputBiasErrorV + referenceBiasErrorV;
    const noiseMargin = hyst - noiseV * 2 - toleranceBand;
    const candidates = String(comp.candidateResistors).split(/[\s,;]+/).filter(Boolean).slice(0, 6).map((value) => parseNumber(value)).filter((value) => value > 0);
    const topology = `${positiveInput ? '+入力(非反転)' : '-入力(反転)'} / ${dividerReference ? 'Vcc分圧基準' : 'Vref基準'}${r3Connected ? ' + OUT→V+正帰還' : ' / ヒステリシスなし'}`;
    return {
        Vth_high: Vth_high.toFixed(4),
        Vth_low: Vth_low.toFixed(4),
        Vth_rising: Vth_rising.toFixed(4),
        Vth_falling: Vth_falling.toFixed(4),
        hysteresis: hyst.toFixed(4),
        tolerance_band: toleranceBand.toFixed(4),
        noise_margin: noiseMargin.toFixed(4),
        candidates,
        topology,
        reference_voltage_v: referenceVoltage.toFixed(4),
        reference_thevenin_ohm: referenceThevenin.toFixed(0),
        feedback_ratio: feedbackRatio.toFixed(5),
        input_bias_error_v: inputBiasErrorV.toFixed(5),
        reference_bias_error_v: referenceBiasErrorV.toFixed(5),
        input_series_ohm: negativeDividerReference ? 0 : r1Raw,
        r1_shorted: negativeDividerReference,
        source_resistance_ohm: sourceResistance.toFixed(0),
        input_polarity_label: positiveInput ? '+入力(非反転)' : '-入力(反転)',
        rising_transition: positiveInput ? 'OUT Low→High' : 'OUT High→Low',
        falling_transition: positiveInput ? 'OUT High→Low' : 'OUT Low→High',
    };
});

// 熱設計
const thermal = reactive({
    P: 1.0,        // 消費電力(W)
    Tambient: 25,  // 周囲温度(°C)
    TjLimit: 125,
    scenarioMultiplier: 1.5,
    deratingSlope: 0.5,
    heatsinkCandidates: '20,10,5',
    nodes: [
        { label: '接合-ケース(θjc)', Rth: 5 },
        { label: 'ケース-放熱板(θcs)', Rth: 0.5 },
        { label: '放熱板-雰囲気(θsa)', Rth: 10 },
    ],
});
// 目的: 設計解析ツールのadd Nodeを扱う。機能: 入力値を検証・整形し、画面または計算処理へ渡す。入力: 関数シグネチャの値。出力: 処理結果またはなし。動作条件: 設計解析ツールの初期化後に呼び出す。副作用: Vue状態、localStorage、DOM、HTTP通信のいずれかを更新する場合がある。
const addNode    = () => thermal.nodes.push({ label: '熱抵抗', Rth: 1 });
// 目的: 設計解析ツールのremove Nodeを扱う。機能: 入力値を検証・整形し、画面または計算処理へ渡す。入力: 関数シグネチャの値。出力: 処理結果またはなし。動作条件: 設計解析ツールの初期化後に呼び出す。副作用: Vue状態、localStorage、DOM、HTTP通信のいずれかを更新する場合がある。
const removeNode = (i) => thermal.nodes.splice(i, 1);
const thermalResult = computed(() => {
    const totalRth = thermal.nodes.reduce((s, n) => s + toFinite(n.Rth), 0);
    const power = toFinite(thermal.P);
    const ambient = toFinite(thermal.Tambient);
    const Tjunction = ambient + power * totalRth;
    const worstTjunction = ambient + power * Math.max(toFinite(thermal.scenarioMultiplier, 1), 0) * totalRth;
    const cumulative = [];
    let boundaryTemp = Tjunction;
    thermal.nodes.forEach((node) => {
        const before = boundaryTemp;
        boundaryTemp -= power * toFinite(node.Rth);
        cumulative.push({ label: node.label, hot: before.toFixed(1), cold: boundaryTemp.toFixed(1), drop: (before - boundaryTemp).toFixed(1) });
    });
    const heatsinkCandidates = String(thermal.heatsinkCandidates).split(/[\s,;]+/).filter(Boolean).map((value) => {
        const rth = Number(value);
        const fixedChainRth = thermal.nodes
            .filter((node) => !String(node.label).toLowerCase().includes('θsa') && !String(node.label).includes('放熱板-雰囲気'))
            .reduce((sum, node) => sum + toFinite(node.Rth), 0);
        const candidateTotalRth = fixedChainRth + rth;
        return { rth, totalRth: candidateTotalRth, tj: (ambient + power * candidateTotalRth).toFixed(1) };
    });
    const deratingMargin = thermal.TjLimit - Tjunction - thermal.deratingSlope * Math.max(ambient - 25, 0);
    return { Tjunction: Tjunction.toFixed(1), worstTjunction: worstTjunction.toFixed(1), totalRth, cumulative, heatsinkCandidates, deratingMargin: deratingMargin.toFixed(1), ok: Tjunction <= thermal.TjLimit };
});
const thermalReferences = [
    { group: 'θjc', label: 'SOT-23', value: '80〜150 °C/W' },
    { group: 'θjc', label: 'TO-220', value: '1〜5 °C/W' },
    { group: 'TIM θcs', label: 'シリコングリス', value: '0.1〜0.5 °C/W' },
    { group: 'TIM θcs', label: '絶縁シート', value: '0.5〜2 °C/W' },
    { group: 'θsa', label: '小型自然空冷', value: '20〜60 °C/W' },
    { group: 'θsa', label: '大型/強制空冷', value: '2〜15 °C/W' },
];

// インタフェース余裕解析
const iface = reactive({
    VOH: 2.4, VOL: 0.4,    // 出力側
    VIH: 2.0, VIL: 0.8,    // 入力側
    driverFamily: '74LS',
    driverVcc: 5,
    receiverFamily: '74HCT',
    receiverVcc: 5,
    uartNominalBaud: 115200,
    uartActualBaud: 116000,
    i2cBusCapPf: 200,
    i2cRiseNsLimit: 300,
    pullupOhm: 2200,
    i2cSinkMaLimit: 3,
    tempMin: -40,
    tempMax: 85,
});
const ifaceResult = computed(() => {
    const high_margin = iface.VOH - iface.VIH;
    const low_margin  = iface.VIL - iface.VOL;
    const logicLevel = logicLevelCompatibility(iface);
    const baudError = (iface.uartActualBaud - iface.uartNominalBaud) / Math.max(iface.uartNominalBaud, 1e-12) * 100;
    const riseNs = 0.8473 * iface.pullupOhm * iface.i2cBusCapPf * 1e-3;
    const pullupLow = iface.i2cRiseNsLimit / Math.max(0.8473 * iface.i2cBusCapPf * 1e-3, 1e-12);
    const sinkMa = iface.receiverVcc / Math.max(iface.pullupOhm, 1e-12) * 1000;
    const pullupMin = iface.receiverVcc / Math.max(iface.i2cSinkMaLimit / 1000, 1e-12);
    return {
        high_margin: high_margin.toFixed(3),
        low_margin:  low_margin.toFixed(3),
        uart_error_pct: baudError.toFixed(3),
        i2c_rise_ns: riseNs.toFixed(1),
        pullup_candidate_ohm: pullupLow.toFixed(0),
        i2c_sink_ma: sinkMa.toFixed(3),
        pullup_min_ohm: pullupMin.toFixed(0),
        i2c_sink_ok: sinkMa <= iface.i2cSinkMaLimit,
        high_ok: high_margin > 0,
        low_ok:  low_margin > 0,
        logic_level: logicLevel,
        logic_level_verdict: logicLevel.verdict,
        logic_high_margin: logicLevel.highMargin === null ? 'CHECK' : logicLevel.highMargin.toFixed(3),
        logic_low_margin: logicLevel.lowMargin === null ? 'CHECK' : logicLevel.lowMargin.toFixed(3),
        logic_input_overvoltage_margin: logicLevel.inputOvervoltageMargin === null ? 'CHECK' : logicLevel.inputOvervoltageMargin.toFixed(3),
        logic_pair: logicLevel.driver && logicLevel.receiver
            ? `${logicLevel.driver.family} ${logicLevel.driver.vcc}V -> ${logicLevel.receiver.family} ${logicLevel.receiver.vcc}V`
            : '条件未定義',
        logic_driver_voh_min: logicLevel.driver ? logicLevel.driver.vohMin.toFixed(3) : 'CHECK',
        logic_driver_vol_max: logicLevel.driver ? logicLevel.driver.volMax.toFixed(3) : 'CHECK',
        logic_receiver_vih_min: logicLevel.receiver ? logicLevel.receiver.vihMin.toFixed(3) : 'CHECK',
        logic_receiver_vil_max: logicLevel.receiver ? logicLevel.receiver.vilMax.toFixed(3) : 'CHECK',
    };
});



    return {
        adc,
        adcResult,
        cap,
        capResult,
        divider,
        dividerResult,
        dividerGraph,
        dividerGraphCursor,
        dividerGraphTooltip,
        updateDividerGraphCursor,
        clearDividerGraphCursor,
        shunt,
        shuntResult,
        power,
        powerResult,
        addLoad,
        removeLoad,
        battery,
        batteryProfiles,
        batteryResult,
        batteryGraph,
        batteryCapacityPie,
        batteryGraphCursor,
        batteryGraphTooltipBox,
        updateBatteryGraphCursor,
        clearBatteryGraphCursor,
        addBatteryLoad,
        removeBatteryLoad,
        formatRuntimeText,
        comp,
        compResult,
        thermal,
        thermalResult,
        thermalReferences,
        addNode,
        removeNode,
        iface,
        ifaceResult,
    };
}
