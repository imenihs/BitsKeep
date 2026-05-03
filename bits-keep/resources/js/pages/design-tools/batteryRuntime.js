import { computed, reactive, watch } from 'vue';

/**
 * 稼働時間を画面表示用の時間/日/月/年へ分解する。
 * @param {number|string} hours 稼働時間(h)。
 * @returns {{label: string, value: string}[]} 表示単位ごとのラベルと値。
 * @sideEffects なし。
 */
// 目的: 設計解析ツールのruntime Scale Rowsを扱う。機能: 入力値を検証・整形し、画面または計算処理へ渡す。入力: 関数シグネチャの値。出力: 処理結果またはなし。動作条件: 設計解析ツールの初期化後に呼び出す。副作用: Vue状態、localStorage、DOM、HTTP通信のいずれかを更新する場合がある。
const runtimeScaleRows = (hours) => {
    const value = Number(hours);
    if (!Number.isFinite(value) || value <= 0) {
        return [{ label: '時間', value: '0.00 h' }];
    }
    const rows = [{ label: '時間', value: `${value.toFixed(2)} h` }];
    if (value >= 24) rows.push({ label: '日', value: `${(value / 24).toFixed(2)} 日` });
    if (value >= 24 * 30) rows.push({ label: '月', value: `${(value / (24 * 30)).toFixed(2)} か月` });
    if (value >= 24 * 365) rows.push({ label: '年', value: `${(value / (24 * 365)).toFixed(2)} 年` });
    return rows;
};

/**
 * 稼働時間の複数単位表示を1行テキストへまとめる。
 * @param {number|string} hours 稼働時間(h)。
 * @returns {string} 時間/日/月/年の表示文字列。
 * @sideEffects なし。
 */
// 目的: 設計解析ツールのruntime Scale Textを扱う。機能: 入力値を検証・整形し、画面または計算処理へ渡す。入力: 関数シグネチャの値。出力: 処理結果またはなし。動作条件: 設計解析ツールの初期化後に呼び出す。副作用: Vue状態、localStorage、DOM、HTTP通信のいずれかを更新する場合がある。
const runtimeScaleText = (hours) => runtimeScaleRows(hours).map((row) => row.value).join(' / ');

/**
 * 割合値をグラフ凡例向けの固定小数点表記へ整える。
 * @param {number|string} value 割合値(%)。
 * @param {number} digits 小数桁数。
 * @returns {string} パーセント表示。
 * @sideEffects なし。
 */
// 目的: 設計解析ツールのformat Percent Textを扱う。機能: 入力値を検証・整形し、画面または計算処理へ渡す。入力: 関数シグネチャの値。出力: 表示値、配列、オブジェクト、数値のいずれか。動作条件: 設計解析ツールの初期化後に呼び出す。副作用: なし。
const formatPercentText = (value, digits = 2) => {
    const number = Number(value);
    if (!Number.isFinite(number)) return '-- %';
    if (number > 0 && number < 0.0001) return '<0.0001 %';
    return `${number.toFixed(digits)} %`;
};

/**
 * 円グラフ扇形の外周座標を計算する。
 * @param {number} cx 中心X座標。
 * @param {number} cy 中心Y座標。
 * @param {number} radius 半径。
 * @param {number} percent 0-100%の角度位置。
 * @returns {{x: number, y: number}} SVG座標。
 * @sideEffects なし。
 */
// 目的: 設計解析ツールのbattery Pie Pointを扱う。機能: 入力値を検証・整形し、画面または計算処理へ渡す。入力: 関数シグネチャの値。出力: 処理結果またはなし。動作条件: 設計解析ツールの初期化後に呼び出す。副作用: Vue状態、localStorage、DOM、HTTP通信のいずれかを更新する場合がある。
const batteryPiePoint = (cx, cy, radius, percent) => {
    const angle = (percent / 100) * Math.PI * 2 - Math.PI / 2;
    return {
        x: cx + Math.cos(angle) * radius,
        y: cy + Math.sin(angle) * radius,
    };
};

/**
 * 電池容量消費内訳のSVG扇形pathを生成する。
 * @param {number} cx 中心X座標。
 * @param {number} cy 中心Y座標。
 * @param {number} radius 半径。
 * @param {number} startPct 開始割合。
 * @param {number} endPct 終了割合。
 * @returns {string} SVG pathのd属性。
 * @sideEffects なし。
 */
// 目的: 設計解析ツールのbattery Pie Pathを扱う。機能: 入力値を検証・整形し、画面または計算処理へ渡す。入力: 関数シグネチャの値。出力: 処理結果またはなし。動作条件: 設計解析ツールの初期化後に呼び出す。副作用: Vue状態、localStorage、DOM、HTTP通信のいずれかを更新する場合がある。
const batteryPiePath = (cx, cy, radius, startPct, endPct) => {
    const start = Math.max(0, Math.min(100, startPct));
    const end = Math.max(start, Math.min(100, endPct));
    if (end - start <= 0) return '';
    const startPoint = batteryPiePoint(cx, cy, radius, start);
    const endPoint = batteryPiePoint(cx, cy, radius, end);
    const largeArc = end - start > 50 ? 1 : 0;
    return [
        `M ${cx.toFixed(2)} ${cy.toFixed(2)}`,
        `L ${startPoint.x.toFixed(2)} ${startPoint.y.toFixed(2)}`,
        `A ${radius} ${radius} 0 ${largeArc} 1 ${endPoint.x.toFixed(2)} ${endPoint.y.toFixed(2)}`,
        'Z',
    ].join(' ');
};

/**
 * バッテリー稼働時間ツールの状態、計算結果、グラフ用データを組み立てる。
 * @param {object} deps 数値変換と定格有無判定の依存。
 * @returns {object} 電池入力、稼働時間計算結果、グラフ/円グラフデータ、負荷操作関数。
 * @sideEffects Vueのwatchで電池種別選択時に公称電圧と内部抵抗を補正する。
 */
// 目的: 設計解析ツールのsetup Battery Runtime Toolを扱う。機能: 入力値を検証・整形し、画面または計算処理へ渡す。入力: 関数シグネチャの値。出力: 処理結果またはなし。動作条件: 設計解析ツールの初期化後に呼び出す。副作用: Vue状態、localStorage、DOM、HTTP通信のいずれかを更新する場合がある。
export default function setupBatteryRuntimeTool({ toFinite, hasRating, parseNumber }) {
    const batteryGraphCursor = reactive({
        active: false,
        x: 0,
        y: 0,
        hours: 0,
        voltage: 0,
        remainingPct: 100,
    });
    const batteryProfiles = {
        lipo: {
            label: 'LiPo',
            nominalCellV: 3.7,
            fullCellV: 4.2,
            cutoffCellV: 3.0,
            defaultCellCount: 1,
            usablePct: 90,
            internalOhm: 0.12,
            maxCRate: 1,
            curve: [[0, 4.2], [0.05, 4.05], [0.2, 3.88], [0.65, 3.72], [0.9, 3.55], [1, 3.0]],
        },
        nimh: {
            label: 'ニッケル水素',
            nominalCellV: 1.2,
            fullCellV: 1.45,
            cutoffCellV: 1.0,
            defaultCellCount: 1,
            usablePct: 85,
            internalOhm: 0.08,
            maxCRate: 0.5,
            curve: [[0, 1.45], [0.08, 1.32], [0.35, 1.25], [0.8, 1.18], [1, 1.0]],
        },
        alkaline: {
            label: 'アルカリ',
            nominalCellV: 1.5,
            fullCellV: 1.6,
            cutoffCellV: 0.9,
            defaultCellCount: 1,
            usablePct: 70,
            internalOhm: 0.25,
            maxCRate: 0.2,
            curve: [[0, 1.6], [0.15, 1.45], [0.5, 1.25], [0.85, 1.05], [1, 0.9]],
        },
        manganese: {
            label: 'マンガン',
            nominalCellV: 1.5,
            fullCellV: 1.55,
            cutoffCellV: 0.8,
            defaultCellCount: 1,
            usablePct: 60,
            internalOhm: 0.45,
            maxCRate: 0.1,
            curve: [[0, 1.55], [0.2, 1.35], [0.55, 1.12], [0.85, 0.95], [1, 0.8]],
        },
        lead: {
            label: '鉛',
            nominalCellV: 2.0,
            fullCellV: 2.12,
            cutoffCellV: 1.75,
            defaultCellCount: 6,
            usablePct: 80,
            internalOhm: 0.03,
            maxCRate: 0.3,
            curve: [[0, 2.12], [0.1, 2.05], [0.5, 2.0], [0.85, 1.92], [1, 1.75]],
        },
    };
    const battery = reactive({
        type: 'lipo',
        capacityMah: 1000,
        cellCount: 1,
        nominalVoltage: 3.7,
        usablePct: 100,
        internalResistance: 0.12,
        cycleSec: 60,
        requiredHours: 24,
        systemMinVoltage: 3.0,
        loads: [
            { name: 'LED', voltageV: 3.3, currentMa: 1, efficiencyPct: 90, durationSec: 1 },
            { name: 'マイコン', voltageV: 3.3, currentMa: 0.1, efficiencyPct: 90, durationSec: 10 },
            { name: 'GPS', voltageV: 3.3, currentMa: 30, efficiencyPct: 90, durationSec: 30 },
        ],
    });
    // 目的: 設計解析ツールのadd Battery Loadを扱う。機能: 入力値を検証・整形し、画面または計算処理へ渡す。入力: 関数シグネチャの値。出力: 処理結果またはなし。動作条件: 設計解析ツールの初期化後に呼び出す。副作用: Vue状態、localStorage、DOM、HTTP通信のいずれかを更新する場合がある。
    const addBatteryLoad = () => battery.loads.push({ name: '', voltageV: battery.nominalVoltage, currentMa: 0, efficiencyPct: 100, durationSec: 0 });
    // 目的: 設計解析ツールのremove Battery Loadを扱う。機能: 入力値を検証・整形し、画面または計算処理へ渡す。入力: 関数シグネチャの値。出力: 処理結果またはなし。動作条件: 設計解析ツールの初期化後に呼び出す。副作用: Vue状態、localStorage、DOM、HTTP通信のいずれかを更新する場合がある。
    const removeBatteryLoad = (index) => battery.loads.splice(index, 1);
    const batteryProfile = computed(() => batteryProfiles[battery.type] ?? batteryProfiles.lipo);
    // 目的: 設計解析ツールのnormalized Battery Cell Countを扱う。機能: 入力値を検証・整形し、画面または計算処理へ渡す。入力: 関数シグネチャの値。出力: 表示値、配列、オブジェクト、数値のいずれか。動作条件: 設計解析ツールの初期化後に呼び出す。副作用: なし。
    const normalizedBatteryCellCount = (profile = batteryProfile.value) => Math.max(1, Math.round(toFinite(battery.cellCount, profile.defaultCellCount ?? 1)));
    const applyBatteryVoltageDefaults = (profile = batteryProfile.value, cellCount = normalizedBatteryCellCount(profile)) => {
        const cells = Math.max(1, Math.round(toFinite(cellCount, profile.defaultCellCount ?? 1)));
        battery.nominalVoltage = Number((profile.nominalCellV * cells).toFixed(3));
        battery.systemMinVoltage = Number((profile.cutoffCellV * cells).toFixed(3));
        battery.internalResistance = Number((profile.internalOhm * cells).toFixed(4));
    };
    watch(() => battery.type, () => {
        const profile = batteryProfile.value;
        const cells = Math.max(1, Math.round(toFinite(profile.defaultCellCount, 1)));
        battery.cellCount = cells;
        applyBatteryVoltageDefaults(profile, cells);
    }, { flush: 'sync' });
    watch(() => battery.cellCount, (value) => {
        if (!hasRating(value)) return;
        applyBatteryVoltageDefaults(batteryProfile.value, value);
    }, { flush: 'sync' });
    // 目的: 設計解析ツールのextrapolated Curve Limitを扱う。機能: 入力値を検証・整形し、画面または計算処理へ渡す。入力: 関数シグネチャの値。出力: 処理結果またはなし。動作条件: 設計解析ツールの初期化後に呼び出す。副作用: Vue状態、localStorage、DOM、HTTP通信のいずれかを更新する場合がある。
    const extrapolatedCurveLimit = (curve) => {
        if (!Array.isArray(curve) || curve.length < 2) return 1;
        const [prevDepth, prevVoltage] = curve[curve.length - 2];
        const [lastDepth, lastVoltage] = curve[curve.length - 1];
        const span = Math.max(lastDepth - prevDepth, 1e-12);
        const slope = (lastVoltage - prevVoltage) / span;
        if (slope >= 0) return lastDepth;
        const zeroVoltageDepth = lastDepth + Math.max(lastVoltage, 0) / Math.abs(slope);
        return Math.max(lastDepth, Math.min(zeroVoltageDepth, lastDepth + 0.5));
    };
    // 目的: 設計解析ツールのinterpolate Curveを扱う。機能: 入力値を検証・整形し、画面または計算処理へ渡す。入力: 関数シグネチャの値。出力: 処理結果またはなし。動作条件: 設計解析ツールの初期化後に呼び出す。副作用: Vue状態、localStorage、DOM、HTTP通信のいずれかを更新する場合がある。
    const interpolateCurve = (curve, depth, maxDepth = 1) => {
        const x = Math.max(0, Math.min(maxDepth, depth));
        for (let i = 1; i < curve.length; i += 1) {
            const [prevDepth, prevVoltage] = curve[i - 1];
            const [nextDepth, nextVoltage] = curve[i];
            if (x <= nextDepth) {
                const span = Math.max(nextDepth - prevDepth, 1e-12);
                const ratio = (x - prevDepth) / span;
                return prevVoltage + (nextVoltage - prevVoltage) * ratio;
            }
        }
        if (curve.length < 2) return Math.max(0, curve[curve.length - 1]?.[1] ?? 0);
        const [prevDepth, prevVoltage] = curve[curve.length - 2];
        const [lastDepth, lastVoltage] = curve[curve.length - 1];
        const span = Math.max(lastDepth - prevDepth, 1e-12);
        const slope = (lastVoltage - prevVoltage) / span;
        return Math.max(0, lastVoltage + slope * (x - lastDepth));
    };
    const batteryResult = computed(() => {
        const profile = batteryProfile.value;
        const nominalVoltage = Math.max(toFinite(battery.nominalVoltage, profile.nominalCellV), 1e-12);
        const cellCount = normalizedBatteryCellCount(profile);
        const nominalScale = nominalVoltage / Math.max(profile.nominalCellV * cellCount, 1e-12);
        const standardFullVoltage = profile.fullCellV * cellCount;
        const standardNominalVoltage = profile.nominalCellV * cellCount;
        const standardCutoffVoltage = profile.cutoffCellV * cellCount;
        const capacityMah = toFinite(battery.capacityMah);
        const usablePct = Math.max(0, Math.min(100, toFinite(battery.usablePct, profile.usablePct)));
        const fullCapacityWh = capacityMah / 1000 * nominalVoltage;
        const cycleSec = toFinite(battery.cycleSec);
        const loads = battery.loads.map((load, index) => {
            const voltageV = toFinite(load.voltageV, nominalVoltage);
            const currentMa = toFinite(load.currentMa);
            const rawEfficiencyPct = toFinite(load.efficiencyPct, 100);
            const efficiencyPct = Math.max(1e-9, Math.min(100, rawEfficiencyPct));
            const durationSec = toFinite(load.durationSec);
            const outputPowerW = voltageV * currentMa / 1000;
            const batteryPowerW = outputPowerW / (efficiencyPct / 100);
            const whPerCycle = batteryPowerW * durationSec / 3600;
            const batteryMaSec = nominalVoltage > 0 ? (batteryPowerW / nominalVoltage * 1000) * durationSec : 0;
            return {
                name: String(load.name || `負荷${index + 1}`),
                voltageV,
                currentMa,
                rawEfficiencyPct,
                efficiencyPct,
                efficiencyValid: rawEfficiencyPct > 0 && rawEfficiencyPct <= 100,
                durationSec,
                outputPowerW,
                batteryPowerW,
                whPerCycle,
                maSec: batteryMaSec,
                mahPerCycle: batteryMaSec / 3600,
                dutyPct: cycleSec > 0 ? durationSec / cycleSec * 100 : 0,
            };
        });
        const totalDurationSec = loads.reduce((sum, load) => sum + load.durationSec, 0);
        const maxDurationSec = loads.reduce((max, load) => Math.max(max, load.durationSec), 0);
        const totalMaSec = loads.reduce((sum, load) => sum + load.maSec, 0);
        const whPerCycle = loads.reduce((sum, load) => sum + load.whPerCycle, 0);
        const mahPerCycle = totalMaSec / 3600;
        const averagePowerW = cycleSec > 0 ? whPerCycle * 3600 / cycleSec : 0;
        const averageCurrentMa = nominalVoltage > 0 ? averagePowerW / nominalVoltage * 1000 : 0;
        const activeLoads = loads.filter((load) => load.durationSec > 0 && load.batteryPowerW > 0);
        const dominantPeakLoad = loads.reduce((best, load) => load.batteryPowerW > (best?.batteryPowerW ?? -Infinity) ? load : best, null);
        const simultaneousPeakPowerW = activeLoads.reduce((sum, load) => sum + load.batteryPowerW, 0);
        const peakPowerW = Math.max(averagePowerW, simultaneousPeakPowerW);
        const peakLoadName = activeLoads.length > 1
            ? '全負荷同時'
            : (activeLoads[0]?.name ?? dominantPeakLoad?.name ?? '-');
        const fullRuntimeHours = averagePowerW > 0 ? fullCapacityWh / averagePowerW : 0;
        const internalResistance = Math.max(toFinite(battery.internalResistance, profile.internalOhm), 0);
        const maxCurveDepth = extrapolatedCurveLimit(profile.curve);
        // 目的: 設計解析ツールのpack Voltage At Depthを扱う。機能: 入力値を検証・整形し、画面または計算処理へ渡す。入力: 関数シグネチャの値。出力: 処理結果またはなし。動作条件: 設計解析ツールの初期化後に呼び出す。副作用: Vue状態、localStorage、DOM、HTTP通信のいずれかを更新する場合がある。
        const packVoltageAtDepth = (depth) => {
            const cellVoltage = interpolateCurve(profile.curve, depth, maxCurveDepth) * nominalScale;
            const openVoltage = Math.max(0, cellVoltage * cellCount);
            if (peakPowerW <= 0 || internalResistance <= 0) return openVoltage;
            const discriminant = openVoltage ** 2 - 4 * peakPowerW * internalResistance;
            if (discriminant <= 0) return 0;
            return Math.max(0, (openVoltage + Math.sqrt(discriminant)) / 2);
        };
        const fullVoltage = profile.fullCellV * nominalScale * cellCount;
        const cutoffVoltage = profile.cutoffCellV * nominalScale * cellCount;
        const startVoltage = packVoltageAtDepth(0);
        const endVoltage = packVoltageAtDepth(1);
        const systemMinVoltage = toFinite(battery.systemMinVoltage, cutoffVoltage);
        // 目的: 設計解析ツールのvoltage Cutoff Depthを扱う。機能: 入力値を検証・整形し、画面または計算処理へ渡す。入力: 関数シグネチャの値。出力: 処理結果またはなし。動作条件: 設計解析ツールの初期化後に呼び出す。副作用: Vue状態、localStorage、DOM、HTTP通信のいずれかを更新する場合がある。
        const voltageCutoffDepth = (() => {
            if (startVoltage <= systemMinVoltage) return 0;
            if (packVoltageAtDepth(maxCurveDepth) > systemMinVoltage) return maxCurveDepth;
            let low = 0;
            let high = maxCurveDepth;
            for (let index = 0; index < 48; index += 1) {
                const mid = (low + high) / 2;
                if (packVoltageAtDepth(mid) > systemMinVoltage) {
                    low = mid;
                } else {
                    high = mid;
                }
            }
            return high;
        })();
        const usableDepth = usablePct / 100 * Math.max(1, voltageCutoffDepth);
        const usableCapacityMah = capacityMah * usableDepth;
        const usableEnergyWh = fullCapacityWh * usableDepth;
        const runtimeHours = averagePowerW > 0 ? usableEnergyWh / averagePowerW : 0;
        const runtimeCycles = whPerCycle > 0 ? usableEnergyWh / whPerCycle : 0;
        const runtimeToMinVoltageHours = fullRuntimeHours * voltageCutoffDepth;
        const effectiveRuntimeHours = Math.min(runtimeHours, runtimeToMinVoltageHours);
        const limitingFactor = Math.abs(runtimeHours - runtimeToMinVoltageHours) <= Math.max(0.01, effectiveRuntimeHours * 0.002)
            ? '容量/電圧下限'
            : (runtimeHours < runtimeToMinVoltageHours ? '容量' : '電圧下限');
        const dominantLoad = loads.reduce((best, load) => load.whPerCycle > (best?.whPerCycle ?? -Infinity) ? load : best, null);
        const cRate = capacityMah > 0 ? averageCurrentMa / capacityMah : 0;
        return {
            profile,
            loads,
            cellCount,
            nominalVoltage: nominalVoltage.toFixed(3),
            standardFullVoltage: standardFullVoltage.toFixed(3),
            standardNominalVoltage: standardNominalVoltage.toFixed(3),
            standardCutoffVoltage: standardCutoffVoltage.toFixed(3),
            fullVoltage: fullVoltage.toFixed(3),
            cutoffVoltage: cutoffVoltage.toFixed(3),
            startVoltage: startVoltage.toFixed(3),
            endVoltage: endVoltage.toFixed(3),
            systemMinVoltage: systemMinVoltage.toFixed(3),
            totalDurationSec: totalDurationSec.toFixed(3),
            maxDurationSec: maxDurationSec.toFixed(3),
            idleSec: (cycleSec - maxDurationSec).toFixed(3),
            averageCurrentMa: averageCurrentMa.toFixed(4),
            averagePowerW: averagePowerW.toFixed(6),
            peakPowerW: peakPowerW.toFixed(6),
            peakLoadName,
            mahPerCycle: mahPerCycle.toFixed(6),
            whPerCycle: whPerCycle.toFixed(6),
            fullCapacityWh: fullCapacityWh.toFixed(6),
            usableCapacityMah: usableCapacityMah.toFixed(3),
            usableEnergyWh: usableEnergyWh.toFixed(6),
            usableDepth: usableDepth.toFixed(4),
            capacityDepthPct: (usableDepth * 100).toFixed(1),
            capacityRuntimeHours: runtimeHours.toFixed(2),
            capacityRuntimeScaleRows: runtimeScaleRows(runtimeHours),
            capacityRuntimeScaleText: runtimeScaleText(runtimeHours),
            fullRuntimeHours: fullRuntimeHours.toFixed(2),
            fullRuntimeScaleText: runtimeScaleText(fullRuntimeHours),
            graphEndDepthValue: voltageCutoffDepth,
            graphEndDepth: voltageCutoffDepth.toFixed(5),
            graphEndHoursValue: runtimeToMinVoltageHours,
            graphEndHours: runtimeToMinVoltageHours.toFixed(2),
            runtimeHours: effectiveRuntimeHours.toFixed(2),
            runtimeDays: (effectiveRuntimeHours / 24).toFixed(2),
            runtimeScaleRows: runtimeScaleRows(effectiveRuntimeHours),
            runtimeScaleText: runtimeScaleText(effectiveRuntimeHours),
            runtimeCycles: runtimeCycles.toFixed(0),
            runtimeToMinVoltageHours: runtimeToMinVoltageHours.toFixed(2),
            runtimeToMinScaleRows: runtimeScaleRows(runtimeToMinVoltageHours),
            runtimeToMinScaleText: runtimeScaleText(runtimeToMinVoltageHours),
            limitingFactor,
            maxCurveDepth,
            cRate: cRate.toFixed(4),
            dominantLoadName: dominantLoad?.name ?? '-',
            dominantLoadPct: dominantLoad && whPerCycle > 0 ? (dominantLoad.whPerCycle / whPerCycle * 100).toFixed(1) : '0.0',
            packVoltageAtDepth,
        };
    });
    const batteryGraph = computed(() => {
        const result = batteryResult.value;
        const width = 700;
        const height = 300;
        const plot = { left: 78, top: 28, width: 540, height: 198 };
        const maxRuntime = Math.max(result.graphEndHoursValue ?? parseNumber(result.graphEndHours), 1e-9);
        const graphEndDepth = Math.max(0, result.graphEndDepthValue ?? parseNumber(result.graphEndDepth, 1));
        const graphEndVoltage = result.packVoltageAtDepth(graphEndDepth);
        const yMinRaw = Math.min(parseNumber(result.systemMinVoltage), graphEndVoltage);
        const yMaxRaw = Math.max(parseNumber(result.fullVoltage), parseNumber(result.startVoltage), parseNumber(result.nominalVoltage), parseNumber(result.systemMinVoltage));
        const yPadding = Math.max((yMaxRaw - yMinRaw) * 0.08, 0.05);
        const yMin = Math.max(0, yMinRaw - yPadding);
        const yMax = yMaxRaw + yPadding;
        const ySpan = Math.max(yMax - yMin, 1e-9);
        // 目的: 設計解析ツールのdepth At Ratioを扱う。機能: 入力値を検証・整形し、画面または計算処理へ渡す。入力: 関数シグネチャの値。出力: 処理結果またはなし。動作条件: 設計解析ツールの初期化後に呼び出す。副作用: Vue状態、localStorage、DOM、HTTP通信のいずれかを更新する場合がある。
        const depthAtRatio = (ratio) => Math.max(0, Math.min(1, ratio)) * graphEndDepth;
        // 目的: 設計解析ツールのvoltage At Ratioを扱う。機能: 入力値を検証・整形し、画面または計算処理へ渡す。入力: 関数シグネチャの値。出力: 処理結果またはなし。動作条件: 設計解析ツールの初期化後に呼び出す。副作用: Vue状態、localStorage、DOM、HTTP通信のいずれかを更新する場合がある。
        const voltageAtRatio = (ratio) => result.packVoltageAtDepth(depthAtRatio(ratio));
        // 目的: 設計解析ツールのx Forを扱う。機能: 入力値を検証・整形し、画面または計算処理へ渡す。入力: 関数シグネチャの値。出力: 処理結果またはなし。動作条件: 設計解析ツールの初期化後に呼び出す。副作用: Vue状態、localStorage、DOM、HTTP通信のいずれかを更新する場合がある。
        const xFor = (hours) => plot.left + Math.max(0, Math.min(1, hours / maxRuntime)) * plot.width;
        // 目的: 設計解析ツールのy Forを扱う。機能: 入力値を検証・整形し、画面または計算処理へ渡す。入力: 関数シグネチャの値。出力: 処理結果またはなし。動作条件: 設計解析ツールの初期化後に呼び出す。副作用: Vue状態、localStorage、DOM、HTTP通信のいずれかを更新する場合がある。
        const yFor = (voltage) => plot.top + (1 - ((voltage - yMin) / ySpan)) * plot.height;
        const samples = Array.from({ length: 121 }, (_, index) => {
            const ratio = index / 120;
            const hours = maxRuntime * ratio;
            const voltage = voltageAtRatio(ratio);
            return { ratio, hours, voltage, x: xFor(hours), y: yFor(voltage) };
        });
        const systemMinY = yFor(parseNumber(result.systemMinVoltage));
        return {
            width,
            height,
            plot,
            points: samples.map((point) => `${point.x.toFixed(1)},${point.y.toFixed(1)}`).join(' '),
            samples,
            runtimeHours: maxRuntime,
            graphEndDepth,
            graphEndVoltage,
            yMin,
            yMax,
            systemMinY,
            yTicks: [yMax, (yMax + yMin) / 2, yMin].map((value) => ({
                value,
                label: `${value.toFixed(2)}V`,
                y: yFor(value),
            })),
            xTicks: [0, 0.25, 0.5, 0.75, 1].map((ratio) => ({
                ratio,
                label: `${(maxRuntime * ratio).toFixed(ratio === 0 || maxRuntime >= 10 ? 1 : 2)}h`,
                x: plot.left + ratio * plot.width,
            })),
            xMaxLabel: `${maxRuntime.toFixed(2)}h`,
            nominalY: yFor(parseNumber(result.nominalVoltage)),
            nominalLabel: `${result.nominalVoltage}V`,
            fullLabel: `${result.fullVoltage}V`,
            cutoffY: yFor(parseNumber(result.cutoffVoltage)),
            cutoffLabel: `${result.cutoffVoltage}V`,
            systemMinLabel: `${result.systemMinVoltage}V`,
            voltageAtRatio,
            xFor,
            yFor,
        };
    });
    const batteryCapacityPie = computed(() => {
        const result = batteryResult.value;
        const width = 240;
        const height = 218;
        const cx = 120;
        const cy = 86;
        const radius = 68;
        const colors = ['#2563eb', '#059669', '#d97706', '#7c3aed', '#e11d48', '#0891b2', '#475569'];
        const totalWhPerCycle = result.loads.reduce((sum, load) => sum + Math.max(Number(load.whPerCycle) || 0, 0), 0);
        const rows = result.loads
            .map((load, index) => {
                const wh = Math.max(Number(load.whPerCycle) || 0, 0);
                const sharePct = totalWhPerCycle > 0 ? wh / totalWhPerCycle * 100 : 0;
                return {
                    key: `${index}-${load.name}`,
                    name: load.name,
                    sourceIndex: index,
                    wh,
                    whLabel: `${wh.toFixed(6)} Wh`,
                    sharePct,
                    sharePctLabel: formatPercentText(sharePct, sharePct < 1 ? 4 : 2),
                };
            })
            .filter((row) => row.wh > 0 || row.sharePct > 0)
            .sort((a, b) => (
                b.wh - a.wh
                    || a.sourceIndex - b.sourceIndex
            ));
        const sortedRows = rows.map((row, index) => ({
            ...row,
            color: colors[index % colors.length],
        }));
        let cursor = 0;
        const segments = sortedRows.map((row) => {
            const startPct = cursor;
            cursor += row.sharePct;
            const endPct = Math.min(cursor, 100);
            return {
                ...row,
                startPct,
                endPct,
                path: batteryPiePath(cx, cy, radius, startPct, endPct),
                visible: endPct > startPct,
            };
        }).filter((segment) => segment.visible);
        const totalSharePct = sortedRows.reduce((sum, row) => sum + row.sharePct, 0);
        return {
            width,
            height,
            cx,
            cy,
            radius,
            rows: sortedRows,
            segments,
            totalSharePct,
            totalSharePctLabel: formatPercentText(totalSharePct, 2),
            totalWhLabel: `${totalWhPerCycle.toFixed(6)} Wh/周期`,
        };
    });
    const batteryGraphTooltipBox = computed(() => {
        if (!batteryGraphCursor.active) return null;
        const graph = batteryGraph.value;
        const boxWidth = 154;
        const boxHeight = 58;
        const minX = graph.plot.left + 8;
        const maxX = graph.plot.left + graph.plot.width - boxWidth - 8;
        const minY = graph.plot.top + 8;
        const maxY = graph.plot.top + graph.plot.height - boxHeight - 8;
        return {
            x: Math.max(minX, Math.min(maxX, batteryGraphCursor.x + 12)),
            y: Math.max(minY, Math.min(maxY, batteryGraphCursor.y - boxHeight - 12)),
            width: boxWidth,
            height: boxHeight,
            time: `${batteryGraphCursor.hours.toFixed(2)} h`,
            voltage: `${batteryGraphCursor.voltage.toFixed(3)} V`,
            remaining: `残 ${batteryGraphCursor.remainingPct.toFixed(1)} %`,
        };
    });
    // 目的: 設計解析ツールのupdate Battery Graph Cursorを扱う。機能: 入力値を検証・整形し、画面または計算処理へ渡す。入力: 関数シグネチャの値。出力: 処理結果またはなし。動作条件: 設計解析ツールの初期化後に呼び出す。副作用: Vue状態、localStorage、DOM、HTTP通信のいずれかを更新する場合がある。
    const updateBatteryGraphCursor = (event) => {
        const graph = batteryGraph.value;
        const svg = event.currentTarget;
        let localX = null;
        if (svg?.createSVGPoint && svg?.getScreenCTM) {
            const matrix = svg.getScreenCTM();
            if (matrix) {
                const point = svg.createSVGPoint();
                point.x = event.clientX;
                point.y = event.clientY;
                localX = point.matrixTransform(matrix.inverse()).x;
            }
        }
        if (localX === null) {
            const rect = svg?.getBoundingClientRect?.();
            if (!rect) return;
            localX = (event.clientX - rect.left) * (graph.width / Math.max(rect.width, 1));
        }
        const x = Math.max(graph.plot.left, Math.min(graph.plot.left + graph.plot.width, localX));
        const ratio = (x - graph.plot.left) / graph.plot.width;
        const hours = graph.runtimeHours * ratio;
        const voltage = graph.voltageAtRatio(ratio);
        const remainingPct = graph.graphEndDepth > 0
            ? Math.max(0, (1 - ratio) * 100)
            : 0;
        Object.assign(batteryGraphCursor, {
            active: true,
            x,
            y: graph.yFor(voltage),
            hours,
            voltage,
            remainingPct,
        });
    };
    // 目的: 設計解析ツールのclear Battery Graph Cursorを扱う。機能: 入力値を検証・整形し、画面または計算処理へ渡す。入力: 関数シグネチャの値。出力: 処理結果またはなし。動作条件: 設計解析ツールの初期化後に呼び出す。副作用: Vue状態、localStorage、DOM、HTTP通信のいずれかを更新する場合がある。
    const clearBatteryGraphCursor = () => {
        batteryGraphCursor.active = false;
    };
    // 目的: 設計解析ツールのformat Runtime Textを扱う。機能: 入力値を検証・整形し、画面または計算処理へ渡す。入力: 関数シグネチャの値。出力: 表示値、配列、オブジェクト、数値のいずれか。動作条件: 設計解析ツールの初期化後に呼び出す。副作用: なし。
    const formatRuntimeText = (hours) => {
        const number = Number(hours);
        if (!Number.isFinite(number)) return runtimeScaleText(0);
        return runtimeScaleText(number);
    };

    return {
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
    };
}
