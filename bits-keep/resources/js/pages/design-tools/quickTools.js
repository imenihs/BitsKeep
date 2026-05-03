/**
 * 設計解析ツールの責務分割モジュール。
 * 親 setup から渡された reactive/computed と数値ヘルパーを使い、
 * 画面表示に必要な状態、計算結果、レポート生成関数を返す。
 */

/**
 * setupQuickTools は親から渡された依存を使ってツール責務を初期化する。
 * @param {object} deps 入力状態、数値変換、レポート生成などの依存。
 * @returns {object} Vueテンプレートへ公開する状態、computed、操作関数。
 * @sideEffects reactive状態とlocalStorageを更新する操作関数を含む。
 */
// 目的: 設計解析ツールのsetup Quick Toolsを扱う。機能: 入力値を検証・整形し、画面または計算処理へ渡す。入力: 関数シグネチャの値。出力: 処理結果またはなし。動作条件: 設計解析ツールの初期化後に呼び出す。副作用: Vue状態、localStorage、DOM、HTTP通信のいずれかを更新する場合がある。
export default function setupQuickTools({
    computed,
    activeToolId,
    quickForms,
    toFinite,
    hasRating,
    ratingMissing,
    connectorActiveTemplate,
    connectorPinMap,
    connectorSummary,
    connectorTemplateOptions,
    LOGIC_CONNECTION_FAMILY_OPTIONS,
    LOGIC_ALL_FAMILY_OPTIONS,
    LOGIC_OUTPUT_OPTIONS,
    LOGIC_FUNCTION_OPTIONS,
    logicCatalog,
    withLogicPartSpec,
    uniqueLogicParts,
    logicFamilyMatches,
    logicFunctionMatches,
}) {
/**
 * 正規分布の累積確率を近似計算する。
 * 入力は標準化済み値、戻り値は0〜1の確率で、副作用はない。
 */
// 目的: 設計解析ツールのnormal Cdfを扱う。機能: 入力値を検証・整形し、画面または計算処理へ渡す。入力: 関数シグネチャの値。出力: 処理結果またはなし。動作条件: 設計解析ツールの初期化後に呼び出す。副作用: Vue状態、localStorage、DOM、HTTP通信のいずれかを更新する場合がある。
const normalCdf = (x) => 0.5 * (1 + erf(x / Math.SQRT2));
/**
 * 誤差関数erfを近似計算する。
 * 入力は実数、戻り値は近似値で、歩留まり計算以外の副作用はない。
 */
// 目的: 設計解析ツールのerfを扱う。機能: 入力値を検証・整形し、画面または計算処理へ渡す。入力: 関数シグネチャの値。出力: 処理結果またはなし。動作条件: 設計解析ツールの初期化後に呼び出す。副作用: Vue状態、localStorage、DOM、HTTP通信のいずれかを更新する場合がある。
const erf = (x) => {
    const sign = x < 0 ? -1 : 1;
    const abs = Math.abs(x);
    const t = 1 / (1 + 0.3275911 * abs);
    const y = 1 - (((((1.061405429 * t - 1.453152027) * t) + 1.421413741) * t - 0.284496736) * t + 0.254829592) * t * Math.exp(-abs * abs);
    return sign * y;
};

/**
 * 現在選択中のクイック設計ツール定義と計算結果を組み立てる。
 * activeToolIdとquickFormsを入力条件にし、戻り値は画面表示用モデルで副作用はない。
 */
const quickTool = computed(() => {
    const f = quickForms[activeToolId.value];
    if (!f) return null;

    if (activeToolId.value === 'tolerance') {
        const nominal = toFinite(f.nominal);
        const tol = toFinite(f.tolerancePct) / 100;
        const count = Math.max(1, toFinite(f.count, 1));
        const sources = String(f.errorSources)
            .split('\n')
            .map((row) => row.split(',').map((value) => value.trim()))
            .filter((row) => row[0])
            .map((row, index) => ({
                label: row[0] || `誤差源${index + 1}`,
                pct: Math.abs(toFinite(row[1], toFinite(f.tolerancePct))),
            }));
        const sourcePcts = sources.length ? sources.map((source) => source.pct / 100) : Array.from({ length: count }, () => tol);
        const worst = nominal * sourcePcts.reduce((sum, sourceTol) => sum + sourceTol, 0);
        const rss = nominal * Math.sqrt(sourcePcts.reduce((sum, sourceTol) => sum + sourceTol * sourceTol, 0));
        const sigma = Math.max(toFinite(f.sigma), 1e-12);
        const yieldRate = (normalCdf((toFinite(f.usl) - toFinite(f.mean)) / sigma) - normalCdf((toFinite(f.lsl) - toFinite(f.mean)) / sigma)) * 100;
        const dominantSource = sources.reduce((max, source) => source.pct > max.pct ? source : max, { label: '同一公差部品', pct: toFinite(f.tolerancePct) });
        const centerOffset = toFinite(f.mean) - toFinite(f.targetCenter);
        const sampleEquivalentPasses = Math.max(0, Math.min(toFinite(f.monteCarloRuns), toFinite(f.monteCarloRuns) * yieldRate / 100));
        return {
            title: '誤差/歩留まり解析',
            model: 'tolerance',
            fields: [
                { key: 'nominal', label: '公称値', type: 'number', diagramKey: 'nominal' },
                { key: 'tolerancePct', label: '公差(%)', type: 'number', diagramKey: 'tolerancePct' },
                { key: 'count', label: '同一公差部品数', type: 'number', diagramKey: 'tolerancePct' },
                { key: 'errorSources', label: '誤差源リスト 名称,%', type: 'textarea', diagramKey: 'tolerancePct' },
                { key: 'lsl', label: '下限 LSL', type: 'number', diagramKey: 'yield' },
                { key: 'usl', label: '上限 USL', type: 'number', diagramKey: 'yield' },
                { key: 'mean', label: '平均', type: 'number', diagramKey: 'sigma' },
                { key: 'sigma', label: 'σ', type: 'number', diagramKey: 'sigma' },
                { key: 'targetCenter', label: '中心目標', type: 'number', diagramKey: 'sigma' },
                { key: 'monteCarloRuns', label: '正規分布サンプル換算数', type: 'number', diagramKey: 'yield' },
            ],
            rows: [
                ['最悪値幅', `±${worst.toFixed(4)}`],
                ['RSS幅', `±${rss.toFixed(4)}`],
                ['推定歩留まり', `${Math.max(0, Math.min(100, yieldRate)).toFixed(3)} %`],
                ['正規分布換算', `${sampleEquivalentPasses.toFixed(0)} / ${toFinite(f.monteCarloRuns).toFixed(0)} pass`],
                ['中心ずれ', `${centerOffset.toFixed(4)}`],
                ['支配誤差源', `${dominantSource.label} ${dominantSource.pct.toFixed(3)} %`],
            ],
            tone: yieldRate >= 99 ? 'ok' : yieldRate >= 95 ? 'warn' : 'bad',
            dominantFactors: [dominantSource.label, 'RSS', '中心値'],
            nextActions: [
                Math.abs(centerOffset) > sigma ? '平均値を規格中心へ寄せ、中心値最適化後の歩留まりを再確認する' : '支配誤差源の公差ランクを上げるとRSS幅を直接下げられます',
            ],
        };
    }

    if (activeToolId.value === 'bode') {
        const r = Math.max(toFinite(f.r), 1e-12);
        const c = Math.max(toFinite(f.c), 1e-18);
        const freq = Math.max(toFinite(f.freq), 1e-12);
        const fc = 1 / (2 * Math.PI * r * c);
        const ratio = freq / fc;
        const gain = f.type === 'highpass'
            ? ratio / Math.sqrt(1 + ratio * ratio)
            : 1 / Math.sqrt(1 + ratio * ratio);
        const phase = f.type === 'highpass'
            ? 90 - Math.atan(ratio) * 180 / Math.PI
            : -Math.atan(ratio) * 180 / Math.PI;
        const tolFactor = (toFinite(f.rTolerancePct) + toFinite(f.cTolerancePct)) / 100;
        const fcMin = fc / (1 + tolFactor);
        const fcMax = fc / Math.max(1 - tolFactor, 0.01);
        const passFreq = Math.max(toFinite(f.passbandFreq), 1e-12);
        const stopFreq = Math.max(toFinite(f.stopbandFreq), 1e-12);
        const passMargin = f.type === 'highpass' ? passFreq / fcMax : fcMin / passFreq;
        const stopMargin = f.type === 'highpass' ? fcMin / stopFreq : stopFreq / fcMax;
        const sweepRows = [fc / 10, fc, fc * 10].map((sweepFreq) => {
            const sweepRatio = sweepFreq / fc;
            const sweepGain = f.type === 'highpass'
                ? sweepRatio / Math.sqrt(1 + sweepRatio * sweepRatio)
                : 1 / Math.sqrt(1 + sweepRatio * sweepRatio);
            const sweepPhase = f.type === 'highpass'
                ? 90 - Math.atan(sweepRatio) * 180 / Math.PI
                : -Math.atan(sweepRatio) * 180 / Math.PI;
            return `${sweepFreq.toFixed(1)}Hz:${(20 * Math.log10(sweepGain)).toFixed(1)}dB/${sweepPhase.toFixed(0)}deg`;
        });
        return {
            title: '周波数応答/ボード線図解析',
            model: 'bode',
            fields: [
                { key: 'type', label: '方式', type: 'select', options: [['lowpass', 'RC Low-pass'], ['highpass', 'RC High-pass']], diagramKey: 'out' },
                { key: 'r', label: 'R フィルタ抵抗(Ω)', type: 'number', diagramKey: 'r' },
                { key: 'c', label: 'C フィルタ容量(F)', type: 'number', diagramKey: 'c' },
                { key: 'freq', label: '評価周波数(Hz)', type: 'number', diagramKey: 'freq' },
                { key: 'rTolerancePct', label: 'R公差(%)', type: 'number', diagramKey: 'r' },
                { key: 'cTolerancePct', label: 'C公差(%)', type: 'number', diagramKey: 'c' },
                { key: 'passbandFreq', label: '通過帯域端(Hz)', type: 'number', diagramKey: 'freq' },
                { key: 'stopbandFreq', label: '阻止帯域端(Hz)', type: 'number', diagramKey: 'freq' },
            ],
            rows: [
                ['fc', `${fc.toFixed(3)} Hz`],
                ['fc範囲', `${fcMin.toFixed(3)} - ${fcMax.toFixed(3)} Hz`],
                ['ゲイン', `${(20 * Math.log10(gain)).toFixed(2)} dB`],
                ['位相', `${phase.toFixed(2)} deg`],
                ['帯域margin', `pass ${passMargin.toFixed(2)}x / stop ${stopMargin.toFixed(2)}x`],
                ['簡易Bode点', sweepRows.join(' / ')],
            ],
            tone: passMargin >= 3 && stopMargin >= 3 ? 'ok' : 'warn',
            margin: Math.min(passMargin, stopMargin),
            warnings: ['一次RC近似です。負荷インピーダンスと寄生成分は別途確認してください。'],
        };
    }

    if (activeToolId.value === 'ovp') {
        const current = Math.max((toFinite(f.vinMax) - toFinite(f.vClamp)) / Math.max(toFinite(f.seriesR), 1e-12) - toFinite(f.loadCurrent), 0);
        const seriesCurrent = current + Math.max(toFinite(f.loadCurrent), 0);
        const power = current * toFinite(f.vClamp);
        const seriesPower = seriesCurrent * seriesCurrent * Math.max(toFinite(f.seriesR), 0);
        const missingRatings = ratingMissing([
            { label: '保護素子電流定格', value: f.currentRating },
            { label: '保護素子損失定格', value: f.powerRating },
            { label: '直列抵抗損失定格', value: f.seriesPowerRating },
        ]);
        const currentMargin = toFinite(f.currentRating) - current;
        const powerMargin = toFinite(f.powerRating) - power;
        const seriesPowerMargin = toFinite(f.seriesPowerRating) - seriesPower;
        const tone = missingRatings.length ? 'check' : (currentMargin < 0 || powerMargin < 0 || seriesPowerMargin < 0 ? 'bad' : (currentMargin / Math.max(toFinite(f.currentRating), 1e-12) < 0.2 || powerMargin / Math.max(toFinite(f.powerRating), 1e-12) < 0.2 || seriesPowerMargin / Math.max(toFinite(f.seriesPowerRating), 1e-12) < 0.2 ? 'warn' : 'ok'));
        return {
            title: '過電圧保護回路設計',
            model: 'ovp',
            fields: [
                { key: 'vinMax', label: 'Vin 最大入力電圧(V)', type: 'number', diagramKey: 'vinMax' },
                { key: 'vClamp', label: 'Vz クランプ電圧(V)', type: 'number', diagramKey: 'vClamp' },
                { key: 'seriesR', label: 'Rser 直列抵抗(Ω)', type: 'number', diagramKey: 'seriesR' },
                { key: 'loadCurrent', label: 'Iload 負荷電流(A)', type: 'number', diagramKey: 'loadCurrent' },
                { key: 'currentRating', label: '保護素子電流定格(A)', type: 'number', diagramKey: 'vClamp' },
                { key: 'powerRating', label: '保護素子損失定格(W)', type: 'number', diagramKey: 'vClamp' },
                { key: 'seriesPowerRating', label: 'Rser損失定格(W)', type: 'number', diagramKey: 'seriesR' },
            ],
            rows: [
                ['保護素子電流', `${current.toFixed(4)} A`],
                ['保護素子損失', `${power.toFixed(4)} W`],
                ['Rser電流', `${seriesCurrent.toFixed(4)} A`],
                ['Rser損失', `${seriesPower.toFixed(4)} W`],
                ['電流定格余裕', missingRatings.includes('保護素子電流定格') ? 'CHECK' : `${currentMargin.toFixed(4)} A`],
                ['損失定格余裕', missingRatings.includes('保護素子損失定格') ? 'CHECK' : `${powerMargin.toFixed(4)} W`],
                ['Rser損失余裕', missingRatings.includes('直列抵抗損失定格') ? 'CHECK' : `${seriesPowerMargin.toFixed(4)} W`],
            ],
            tone,
            margin: missingRatings.length ? null : Math.min(currentMargin / Math.max(toFinite(f.currentRating), 1e-12), powerMargin / Math.max(toFinite(f.powerRating), 1e-12), seriesPowerMargin / Math.max(toFinite(f.seriesPowerRating), 1e-12)),
            missingConditions: missingRatings,
            warnings: [
                ...(missingRatings.length ? ['保護素子と直列抵抗の定格が未入力のため合否は確定できません。'] : []),
                ...(!missingRatings.length && seriesPowerMargin < 0 ? ['直列抵抗の損失定格を超えています。'] : []),
            ],
        };
    }

    if (activeToolId.value === 'tvs') {
        const current = Math.max((toFinite(f.surgeV) - toFinite(f.clampV)) / Math.max(toFinite(f.lineImpedance), 1e-12), 0);
        const power = current * toFinite(f.clampV);
        const waveformFactor = Math.max(0.1, Math.min(toFinite(f.waveformFactor, 0.5), 1));
        const energy = power * toFinite(f.pulseMs) / 1000 * waveformFactor;
        const missingRatings = ratingMissing([
            { label: 'TVSピークパルス電力定格', value: f.peakPowerRating },
            { label: 'TVSパルスエネルギー定格', value: f.energyRating },
        ]);
        const powerMargin = toFinite(f.peakPowerRating) - power;
        const energyMargin = toFinite(f.energyRating) - energy;
        const ratingTone = missingRatings.length ? 'check' : (powerMargin < 0 || energyMargin < 0 ? 'bad' : (powerMargin / Math.max(toFinite(f.peakPowerRating), 1e-12) < 0.2 || energyMargin / Math.max(toFinite(f.energyRating), 1e-12) < 0.2 ? 'warn' : 'check'));
        return {
            title: 'TVS保護回路設計',
            model: 'tvs',
            fields: [
                { key: 'surgeV', label: 'Vsurge サージ電圧(V)', type: 'number', diagramKey: 'surgeV' },
                { key: 'lineImpedance', label: 'Zline 線路インピーダンス(Ω)', type: 'number', diagramKey: 'lineImpedance' },
                { key: 'clampV', label: 'Vclamp TVSクランプ電圧(V)', type: 'number', diagramKey: 'clampV' },
                { key: 'pulseMs', label: 'パルス幅(s)', type: 'number', diagramKey: 'pulseMs', storedUnitFactor: 1e-3, forceUnitConversion: true },
                { key: 'waveformFactor', label: '波形係数', type: 'number', diagramKey: 'pulseMs' },
                { key: 'peakPowerRating', label: 'TVSピークパルス電力定格(W)', type: 'number', diagramKey: 'clampV' },
                { key: 'energyRating', label: 'TVSパルスエネルギー定格(J)', type: 'number', diagramKey: 'pulseMs' },
            ],
            rows: [
                ['ピーク電流', `${current.toFixed(2)} A`],
                ['ピーク電力', `${power.toFixed(1)} W`],
                ['パルスエネルギー', `${energy.toFixed(4)} J`],
                ['波形係数', `${waveformFactor.toFixed(2)}`],
                ['電力定格余裕', missingRatings.includes('TVSピークパルス電力定格') ? 'CHECK' : `${powerMargin.toFixed(1)} W`],
                ['エネルギー定格余裕', missingRatings.includes('TVSパルスエネルギー定格') ? 'CHECK' : `${energyMargin.toFixed(4)} J`],
            ],
            tone: ratingTone,
            margin: missingRatings.length ? null : Math.min(powerMargin / Math.max(toFinite(f.peakPowerRating), 1e-12), energyMargin / Math.max(toFinite(f.energyRating), 1e-12)),
            missingConditions: [...missingRatings, 'サージ波形', 'TVS温度ディレーティング', '繰り返しサージ条件'],
            warnings: [
                ...(missingRatings.length ? ['TVS定格が未入力のため、計算値は負荷条件の整理に留まります。'] : []),
                '線路インピーダンス固定のピーク近似です。規格波形の電流波形と波形係数で照合してください。',
            ],
        };
    }

    if (activeToolId.value === 'fuse') {
        const baseRating = hasRating(f.currentRating) ? toFinite(f.currentRating) : toFinite(f.ratedCurrent);
        const ambientExtraDeratingPct = Math.max(0, toFinite(f.ambient) - 25) * 0.5;
        const effectiveDeratingPct = Math.min(90, Math.max(0, toFinite(f.deratingPct) + ambientExtraDeratingPct));
        const usable = baseRating * (1 - effectiveDeratingPct / 100);
        const margin = usable - toFinite(f.loadCurrent);
        const missingRatings = ratingMissing([{ label: 'ヒューズ電流定格', value: f.currentRating }]);
        const tone = missingRatings.length ? 'check' : (margin < 0 ? 'bad' : (margin / Math.max(baseRating, 1e-12) < 0.2 ? 'warn' : 'check'));
        return {
            title: 'ヒューズ選定確認',
            model: 'fuse',
            fields: [
                { key: 'ratedCurrent', label: 'F1 定格電流(A)', type: 'number', diagramKey: 'ratedCurrent' },
                { key: 'currentRating', label: '照合するヒューズ電流定格(A)', type: 'number', diagramKey: 'ratedCurrent' },
                { key: 'loadCurrent', label: 'Iload 負荷電流(A)', type: 'number', diagramKey: 'loadCurrent' },
                { key: 'ambient', label: 'Ta 周囲温度(°C)', type: 'number', diagramKey: 'ambient' },
                { key: 'deratingPct', label: 'ディレーティング(%)', type: 'number', diagramKey: 'deratingPct' },
            ],
            rows: [
                ['使用可能電流', missingRatings.length ? 'CHECK' : `${usable.toFixed(3)} A`],
                ['余裕', missingRatings.length ? 'CHECK' : `${margin.toFixed(3)} A`],
                ['負荷率', missingRatings.length ? 'CHECK' : `${(toFinite(f.loadCurrent) / Math.max(usable, 1e-12) * 100).toFixed(1)} %`],
                ['適用derating', `${effectiveDeratingPct.toFixed(1)} %`],
                ['周囲温度', `${toFinite(f.ambient).toFixed(1)} ℃`],
            ],
            tone,
            margin: missingRatings.length ? null : margin,
            missingConditions: [...missingRatings, '時間電流特性', '突入I2t', '周囲温度ディレーティング曲線'],
            warnings: [
                ...(missingRatings.length ? ['照合するヒューズ定格が未入力のため、負荷電流との合否は未確定です。'] : []),
                ...(ambientExtraDeratingPct > 0 ? ['周囲温度による追加ディレーティングを概算で加味しています。'] : []),
                '連続電流だけの確認です。遮断時間と突入I2tを入れるまで合否は確定できません。',
            ],
        };
    }

    if (activeToolId.value === 'polyfuse') {
        const loss = toFinite(f.loadCurrent) ** 2 * toFinite(f.resistance);
        const holdRating = hasRating(f.holdCurrentRating) ? toFinite(f.holdCurrentRating) : toFinite(f.holdCurrent);
        const holdMargin = holdRating - toFinite(f.loadCurrent);
        const powerMargin = toFinite(f.powerRating) - loss;
        const missingRatings = ratingMissing([
            { label: 'PTC保持電流定格', value: f.holdCurrentRating },
            { label: 'PTC許容損失定格', value: f.powerRating },
        ]);
        const tone = missingRatings.length ? 'check' : (holdMargin < 0 || powerMargin < 0 ? 'bad' : (holdMargin / Math.max(holdRating, 1e-12) < 0.2 || powerMargin / Math.max(toFinite(f.powerRating), 1e-12) < 0.2 ? 'warn' : 'check'));
        return {
            title: 'ポリスイッチ発熱設計',
            model: 'polyfuse',
            fields: [
                { key: 'holdCurrent', label: 'PTC 保持電流(A)', type: 'number', diagramKey: 'holdCurrent' },
                { key: 'holdCurrentRating', label: '照合する保持電流定格(A)', type: 'number', diagramKey: 'holdCurrent' },
                { key: 'tripCurrent', label: 'PTC トリップ電流(A)', type: 'number', diagramKey: 'tripCurrent' },
                { key: 'loadCurrent', label: 'Iload 負荷電流(A)', type: 'number', diagramKey: 'loadCurrent' },
                { key: 'resistance', label: 'RPTC 抵抗(Ω)', type: 'number', diagramKey: 'resistance' },
                { key: 'powerRating', label: 'PTC許容損失定格(W)', type: 'number', diagramKey: 'resistance' },
                { key: 'ambient', label: 'Ta 周囲温度(°C)', type: 'number', diagramKey: 'ambient' },
            ],
            rows: [
                ['発熱', `${loss.toFixed(4)} W`],
                ['保持余裕', missingRatings.includes('PTC保持電流定格') ? 'CHECK' : `${holdMargin.toFixed(3)} A`],
                ['損失定格余裕', missingRatings.includes('PTC許容損失定格') ? 'CHECK' : `${powerMargin.toFixed(4)} W`],
                ['トリップ比', `${(toFinite(f.loadCurrent) / Math.max(toFinite(f.tripCurrent), 1e-12) * 100).toFixed(1)} %`],
            ],
            tone,
            margin: missingRatings.length ? null : Math.min(holdMargin, powerMargin),
            missingConditions: [...missingRatings, 'トリップ時間曲線', '温度ディレーティング曲線'],
            warnings: [
                ...(missingRatings.length ? ['温度ディレーティング後の保持電流定格と許容損失が未入力です。'] : []),
                '保持電流と発熱だけの確認です。トリップ時間は部品曲線で照合してください。',
            ],
        };
    }

    if (activeToolId.value === 'protection') {
        const faultPower = toFinite(f.faultV) * toFinite(f.faultCurrent);
        const i2tDemand = toFinite(f.faultCurrent) ** 2 * 0.01;
        const tvsMargin = toFinite(f.tvsPowerRating) - faultPower;
        const fuseMargin = toFinite(f.fuseI2t) - i2tDemand;
        const ptcMargin = toFinite(f.ptcHold) - toFinite(f.loadCurrent);
        const efuseLoadMargin = toFinite(f.efuseLimit) - toFinite(f.loadCurrent);
        const efuseFaultWindow = toFinite(f.faultCurrent) - toFinite(f.efuseLimit);
        const reverseLoss = toFinite(f.reverseDrop) * toFinite(f.loadCurrent);
        const missing = ratingMissing([
            { label: 'TVSピーク電力定格', value: f.tvsPowerRating },
            { label: 'ヒューズI2t定格', value: f.fuseI2t },
            { label: 'PTC保持電流', value: f.ptcHold },
            { label: 'eFuse電流制限', value: f.efuseLimit },
            { label: '逆接保護電圧降下', value: f.reverseDrop },
        ]);
        const marginRatios = missing.length ? [] : [
            tvsMargin / Math.max(toFinite(f.tvsPowerRating), 1e-12),
            fuseMargin / Math.max(toFinite(f.fuseI2t), 1e-12),
            ptcMargin / Math.max(toFinite(f.ptcHold), 1e-12),
            efuseLoadMargin / Math.max(toFinite(f.efuseLimit), 1e-12),
            efuseFaultWindow / Math.max(toFinite(f.faultCurrent), 1e-12),
        ];
        const minRatio = marginRatios.length ? Math.min(...marginRatios) : null;
        const baseTone = missing.length ? 'check' : (minRatio < 0 ? 'bad' : (minRatio < 0.2 ? 'warn' : 'check'));
        return {
            title: '保護協調モデル',
            model: 'protection',
            fields: [
                { key: 'faultV', label: '故障電圧(V)', type: 'number', diagramKey: 'faultV' },
                { key: 'faultCurrent', label: '故障電流(A)', type: 'number', diagramKey: 'faultCurrent' },
                { key: 'tvsPowerRating', label: 'TVSピーク電力定格(W)', type: 'number', diagramKey: 'tvsPowerRating' },
                { key: 'fuseI2t', label: 'ヒューズI2t定格(A2s)', type: 'number', diagramKey: 'fuseI2t' },
                { key: 'ptcHold', label: 'PTC保持電流(A)', type: 'number', diagramKey: 'loadCurrent' },
                { key: 'efuseLimit', label: 'eFuse電流制限(A)', type: 'number', diagramKey: 'efuseLimit' },
                { key: 'reverseDrop', label: '逆接保護電圧降下(V)', type: 'number', diagramKey: 'reverseDrop' },
                { key: 'loadCurrent', label: '通常負荷電流(A)', type: 'number', diagramKey: 'loadCurrent' },
            ],
            rows: [
                ['故障電力', `${faultPower.toFixed(2)} W`],
                ['TVS余裕', `${tvsMargin.toFixed(2)} W`],
                ['TVS余裕率', missing.length ? 'CHECK' : `${(marginRatios[0] * 100).toFixed(1)} %`],
                ['ヒューズI2t余裕', `${fuseMargin.toFixed(3)} A2s`],
                ['ヒューズ余裕率', missing.length ? 'CHECK' : `${(marginRatios[1] * 100).toFixed(1)} %`],
                ['PTC保持余裕', `${ptcMargin.toFixed(3)} A`],
                ['PTC余裕率', missing.length ? 'CHECK' : `${(marginRatios[2] * 100).toFixed(1)} %`],
                ['eFuse通常負荷余裕', `${efuseLoadMargin.toFixed(3)} A`],
                ['eFuse故障検出幅', `${efuseFaultWindow.toFixed(3)} A`],
                ['逆接保護損失', `${reverseLoss.toFixed(3)} W`],
            ],
            tone: baseTone,
            missingConditions: [...missing, '故障波形', '保護素子の動作順序', '遮断時間'],
            margin: minRatio,
            warnings: [
                ...(efuseFaultWindow < 0 ? ['eFuse電流制限が故障電流以上で、故障時に制限が効かない可能性があります。'] : []),
                '故障波形は10ms相当の簡易I2tで近似しています。実波形なしではPASS判定にしません。',
            ],
            nextActions: ['実波形、温度ディレーティング、保護素子の順序をデータシートで照合する'],
        };
    }

    if (activeToolId.value === 'logic-ic') {
        const requestedPins = Math.max(0, Math.round(toFinite(f.packagePins, 0)));
        const requestedInputs = Math.max(0, Math.round(toFinite(f.inputs, 0)));
        const supplyV = toFinite(f.supplyV, Number.NaN);
        const familyRank = new Map(LOGIC_CONNECTION_FAMILY_OPTIONS.map(([family], index) => [family, index]));
        const matches = uniqueLogicParts(logicCatalog.map(withLogicPartSpec).filter((item) => {
            const familyMatch = logicFamilyMatches(item.family, f.family);
            const functionMatch = logicFunctionMatches(item.function, f.function);
            const outputMatch = f.outputType === 'any' || item.output === f.outputType;
            const pinMatch = !requestedPins || item.pins === requestedPins;
            const inputMatch = !requestedInputs || item.inputs === requestedInputs;
            return familyMatch && functionMatch && outputMatch && pinMatch && inputMatch;
        })).sort((a, b) => (familyRank.get(a.family) ?? 999) - (familyRank.get(b.family) ?? 999) || a.part.localeCompare(b.part));
        const supplyMatches = Number.isFinite(supplyV)
            ? matches.filter((item) => supplyV >= item.vMin && supplyV <= item.vMax)
            : [];
        const best = supplyMatches[0] ?? matches[0] ?? null;
        const candidateTone = !matches.length ? 'warn' : (Number.isFinite(supplyV) && !supplyMatches.length ? 'bad' : 'check');
        const tone = candidateTone;
        const candidateRows = (supplyMatches.length ? supplyMatches : matches).slice(0, 8).map((item) => item.part).join(', ');
        return {
            title: 'ロジックICリファレンス',
            model: 'logic-ic',
            fields: [
                { key: 'family', label: '候補ファミリ', type: 'select', options: LOGIC_ALL_FAMILY_OPTIONS, diagramKey: 'family' },
                { key: 'function', label: '機能', type: 'select', options: LOGIC_FUNCTION_OPTIONS, diagramKey: 'function' },
                { key: 'inputs', label: '入力数の目安', type: 'number', diagramKey: 'function' },
                { key: 'packagePins', label: 'ピン数(0=不問)', type: 'number', diagramKey: 'packagePins' },
                { key: 'supplyV', label: '使用Vcc(V)', type: 'number', diagramKey: 'supplyV' },
                { key: 'outputType', label: '出力形式', type: 'select', options: LOGIC_OUTPUT_OPTIONS, diagramKey: 'outputType' },
            ],
            rows: [
                ['候補数', `${matches.length}`],
                ['Vcc範囲内', Number.isFinite(supplyV) ? `${supplyMatches.length}` : 'CHECK'],
                ['第一候補', best ? `${best.part} / ${best.note}` : '該当なし'],
                ['候補一覧', candidateRows || '該当なし'],
                ['確認条件', best ? `${best.vMin}-${best.vMax} V / ${best.pins}pin / ${best.output}` : '条件を広げて再検索'],
            ],
            tone,
            dominantFactors: ['候補ファミリ', '機能', 'Vcc範囲', '出力形式'],
            warnings: [
                ...(!matches.length ? ['機能・ファミリ・ピン数の条件に合う候補がありません。'] : []),
                ...(matches.length && Number.isFinite(supplyV) && !supplyMatches.length ? ['候補ICの電源範囲外です。ファミリまたはVccを見直してください。'] : []),
                ...(String(f.family).includes('HCU')
                    ? ['74HCUはアンバッファ用途です。発振/リニア動作、未使用入力、低速エッジでの消費電流を確認してください。']
                    : []),
                '未使用入力はデータシート推奨に従いVCC/GND等へ固定してください。浮き入力は貫通電流、発振、誤動作の原因になります。',
                '接続先とのVOH/VOL/VIH/VIL、入力耐圧、5V tolerant条件はIF余裕で確認してください。',
            ],
            missingConditions: [
                ...new Set([
                    ...(Number.isFinite(supplyV) ? [] : ['使用Vcc']),
                    ...(!matches.length ? ['候補ファミリ/機能/ピン条件'] : []),
                    ...(matches.length && Number.isFinite(supplyV) && !supplyMatches.length ? ['候補ICのVcc範囲'] : []),
                    '伝搬遅延/最大周波数',
                    'パッケージピン配置',
                    '未使用入力の固定方法',
                ]),
            ],
            margin: null,
            summary: tone === 'bad'
                ? '候補検索条件に成立しない条件があります。ファミリ、機能、Vcc、ピン数を見直してください。'
                : '候補ICを概算しました。接続レベル判定はIF余裕で確認し、最終的にはデータシート条件へ置き換えます。',
            nextActions: tone === 'bad'
                ? ['ファミリ、機能、Vcc、ピン数条件を広げて候補を出し直す']
                : ['候補型番のデータシートでVcc、出力電流、伝搬遅延、ピン配置、未使用入力処理を確認し、IF余裕で接続先レベルを判定する'],
        };
    }

    if (activeToolId.value === 'connector') {
        const template = connectorActiveTemplate.value;
        const summary = connectorSummary.value;
        const pins = connectorPinMap.value.length;
        const currentRating = toFinite(f.currentRatingPerPin, template?.currentRatingPerPin ?? 0);
        const voltageRating = toFinite(f.voltageRatingV, template?.voltageRatingV ?? 0);
        const usablePerPin = currentRating > 0
            ? currentRating * Math.max(0, Math.min(100, toFinite(f.deratingPct, 80))) / 100
            : null;
        const viewNote = f.viewSide === 'mating-face' ? '嵌合面基準でピン番号を書く' : 'はんだ面基準で左右反転を明記する';
        const tone = summary.status === 'bad' ? 'bad' : (summary.status === 'ok' ? 'ok' : 'check');
        return {
            title: 'コネクタ設計/ピン配置',
            model: 'connector',
            fields: [
                { key: 'selectedTemplateId', label: '標準/ユーザーコネクタ', type: 'select', options: connectorTemplateOptions.value, diagramKey: 'connectorType' },
                { key: 'environment', label: '用途', type: 'select', options: [['board-to-wire', '基板-電線'], ['board-to-board', '基板-基板'], ['external', '外部I/F']], diagramKey: 'environment' },
                { key: 'viewSide', label: '図面視点', type: 'select', options: [['mating-face', '嵌合面'], ['solder-side', 'はんだ面'], ['cable-side', 'ケーブル側']], diagramKey: 'viewSide' },
                { key: 'pin1Mark', label: 'Pin1表示', type: 'select', options: [['silk-dot', 'シルク点'], ['triangle', '三角'], ['square-pad', '角ランド'], ['key-notch', 'キー/ノッチ'], ['shell-mark', 'シェル刻印']], diagramKey: 'pin1Mark' },
                { key: 'currentRatingPerPin', label: '1pin定格電流(A)', type: 'number', diagramKey: 'currentPerPin' },
                { key: 'voltageRatingV', label: '定格電圧(V)', type: 'number', diagramKey: 'currentPerPin' },
                { key: 'deratingPct', label: '電流derating(%)', type: 'number', diagramKey: 'currentPerPin' },
                { key: 'tempRiseLimit', label: '温度上昇上限(℃)', type: 'number', diagramKey: 'currentPerPin' },
                { key: 'photoUrl', label: '写真URL', type: 'text', diagramKey: 'datasheetUrl' },
                { key: 'diagramUrl', label: 'ピン配置図URL', type: 'text', diagramKey: 'datasheetUrl' },
                { key: 'datasheetUrl', label: 'データシートURL', type: 'text', diagramKey: 'datasheetUrl' },
                { key: 'matingPart', label: '相手側部品', type: 'text', diagramKey: 'connectorType' },
                { key: 'bomNote', label: 'BOM注記', type: 'text', diagramKey: 'pinAssignments' },
                { key: 'silkNote', label: 'シルク/組立注記', type: 'text', diagramKey: 'pinAssignments' },
                { key: 'pinAssignments', label: 'ピン割付 pin,signal,type,voltage,current,color,awg,note', type: 'textarea', diagramKey: 'pinAssignments' },
            ],
            rows: [
                ['テンプレート', `${template?.label ?? '未選択'} / ${template?.standard ?? '-'}`],
                ['写真/図', `${f.photoUrl || template?.photoUrl ? '写真あり' : '写真なし'} / ${f.diagramUrl || template?.diagramUrl ? '図あり' : '図なし'}`],
                ['ピン割付', `${summary.assigned.length} / ${pins}`],
                ['総電流', `${summary.totalCurrent.toFixed(3)} A`],
                ['最大pin電流', `${summary.maxPinCurrent.toFixed(3)} A`],
                ['derating後1pin', usablePerPin === null ? 'CHECK' : `${usablePerPin.toFixed(3)} A`],
                ['電流超過', `${summary.overCurrent.length}`],
                ['電圧超過', `${summary.overVoltage.length} / rating ${voltageRating || 'CHECK'} V`],
                ['AWG超過', `${summary.overAwg.length}`],
                ['電源/GND/差動', `${summary.powerPins.length}/${summary.groundPins.length}/${summary.diffPins.length}`],
                ['図面注記', `${viewNote} / Pin1=${f.pin1Mark}`],
                ['BOM/シルク', `${f.bomNote || 'BOM注記なし'} / ${f.silkNote || 'シルク注記なし'}`],
            ],
            tone,
            missingConditions: summary.missingConditions,
            warnings: [
                ...summary.warnings,
                ...(hasRating(f.tempRiseLimit) ? [] : ['温度上昇上限が未入力のため、全ピン同時通電のPASS判定はしません。']),
                '嵌合面/はんだ面/ケーブル側の左右反転、Pin1表示、キー形状を図面に併記してください。',
            ],
            nextActions: ['写真またはピン配置図、データシート定格、相手側部品、未割付ピンのNC理由をそろえる'],
        };
    }

    if (activeToolId.value === 'cable') {
        const endA = String(f.endA).split(',').map((v) => v.trim()).filter(Boolean);
        const endB = String(f.endB).split(',').map((v) => v.trim()).filter(Boolean);
        const straight = endA.length === endB.length && endA.every((pin, index) => pin === endB[index]);
        const reversed = endA.length === endB.length && endA.every((pin, index) => pin === endB[endB.length - index - 1]);
        return {
            title: 'ケーブルストレート/クロス判定',
            model: 'cable',
            fields: [
                { key: 'endA', label: '端Aピン列', type: 'text', diagramKey: 'endA' },
                { key: 'endB', label: '端Bピン列', type: 'text', diagramKey: 'endB' },
            ],
            rows: [['判定', straight ? 'ストレート' : (reversed ? '反転/クロス' : 'カスタム配線')], ['端A本数', `${endA.length}`], ['端B本数', `${endB.length}`]],
            tone: straight ? 'ok' : 'warn',
        };
    }

    if (activeToolId.value === 'jumper') {
        const rawRows = String(f.entries).split('\n').map((row) => row.trim()).filter(Boolean);
        const rows = rawRows.map((row) => row.split(',').map((v) => v.trim()));
        const entries = rows.filter((row) => row.length >= 4);
        const invalidRows = rows.filter((row) => row.length < 4);
        const mounted = entries.filter((row) => row[3] === '実装').length;
        const validStates = new Set(['実装', '未実装', '切替', 'DNP']);
        const invalidStates = entries.filter((row) => !validStates.has(row[3]));
        const missingPurpose = entries.filter((row) => !row[2]);
        const missingProduction = entries.filter((row) => !row[4]);
        const duplicateRefs = entries
            .map((row) => row[0])
            .filter((ref, index, refs) => ref && refs.indexOf(ref) !== index);
        const bomNotes = entries.map((row) => `${row[0]}:${row[4] || row[3]}:${row[5] || 'BOM注記なし'}`).slice(0, 5);
        const warningCount = invalidRows.length + invalidStates.length + missingPurpose.length + missingProduction.length + duplicateRefs.length;
        return {
            title: '0Ω/未実装/ジャンパ整理',
            model: 'jumper',
            fields: [{ key: 'entries', label: 'Ref,種類,目的,状態,量産初期値,BOM注記', type: 'textarea', diagramKey: 'entries' }],
            rows: [
                ['登録数', `${entries.length}`],
                ['実装', `${mounted}`],
                ['未実装/切替', `${entries.length - mounted}`],
                ['不正行', `${invalidRows.length}`],
                ['目的未記入', `${missingPurpose.length}`],
                ['量産初期値未記入', `${missingProduction.length}`],
                ['重複Ref', `${new Set(duplicateRefs).size}`],
                ['BOM注記', bomNotes.join(' / ') || '未入力'],
            ],
            tone: warningCount ? 'warn' : 'ok',
            warnings: [
                ...invalidRows.map((row) => `列不足: ${row.join(',')}`),
                ...invalidStates.map((row) => `${row[0]} の状態 ${row[3]} は未定義です。`),
                ...missingPurpose.map((row) => `${row[0]} の目的が未記入です。`),
                ...missingProduction.map((row) => `${row[0]} の量産初期値が未記入です。`),
                ...Array.from(new Set(duplicateRefs)).map((ref) => `${ref} が重複しています。`),
            ],
            missingConditions: warningCount ? ['量産初期値', 'BOM注記', '変更手順'] : [],
            nextActions: warningCount
                ? ['目的、量産初期値、BOM注記、重複Refを整理してから量産BOMへ反映する']
                : ['BOM注記とデバッグ時の変更手順を設計メモへ転記する'],
        };
    }

    if (activeToolId.value === 'startup') {
        const rows = String(f.rails).split('\n').map((row) => row.split(',').map((v) => v.trim())).filter((row) => row[0]);
        const warnings = rows.filter((row) => row[1] && !rows.some((candidate) => candidate[0] === row[1]));
        const pgRows = String(f.pgSignals).split('\n').map((row) => row.split(',').map((v) => v.trim())).filter((row) => row[0]);
        const partialPaths = String(f.partialPowerPaths).split('\n').map((row) => row.trim()).filter(Boolean);
        const resetHold = toFinite(f.resetHoldMs);
        const unresolvedPg = pgRows.filter((row) => row[1] && !rows.some((candidate) => candidate[0] === row[1]));
        const backPowerWarnings = partialPaths.filter((path) => path.includes('->'));
        return {
            title: '起動・停止/リセット/依存関係診断',
            model: 'startup',
            fields: [
                { key: 'template', label: 'テンプレート', type: 'select', options: [['pmic-mcu', 'PMIC + MCU'], ['fpga-ddr', 'FPGA + DDR']], diagramKey: 'template' },
                { key: 'rails', label: 'レール,親,立上りms/リセットms', type: 'textarea', diagramKey: 'rails' },
                { key: 'pgSignals', label: 'PG/RESET信号 名称,親,遅延ms', type: 'textarea', diagramKey: 'reset' },
                { key: 'partialPowerPaths', label: '部分給電/逆流経路', type: 'textarea', diagramKey: 'backpower' },
                { key: 'resetHoldMs', label: 'RESET保持時間(s)', type: 'number', diagramKey: 'reset', storedUnitFactor: 1e-3, forceUnitConversion: true },
            ],
            rows: [
                ['ノード数', `${rows.length}`],
                ['依存不明', `${warnings.length + unresolvedPg.length}`],
                ['PG/RESET数', `${pgRows.length}`],
                ['部分給電経路', `${partialPaths.length}`],
                ['RESET保持', `${resetHold.toFixed(1)} ms`],
                ['判定', warnings.length || unresolvedPg.length ? '親レール未定義あり' : (backPowerWarnings.length ? 'バックパワー要確認' : '依存関係は成立')],
            ],
            tone: warnings.length || unresolvedPg.length || backPowerWarnings.length ? 'warn' : 'ok',
            warnings: [
                ...warnings.map((row) => `${row[0]} の親 ${row[1]} が未定義です。`),
                ...unresolvedPg.map((row) => `${row[0]} の親 ${row[1]} が未定義です。`),
                ...backPowerWarnings.map((path) => `部分給電/バックパワー経路 ${path} の保護を確認してください。`),
            ],
            missingConditions: resetHold <= 0 ? ['RESET保持時間'] : [],
            nextActions: ['PG閾値、RESET解除条件、部分給電時の入力保護電流をデータシートで照合する'],
        };
    }

    return null;
});



    return {
        quickTool,
    };
}
