/**
 * 設計解析ツールハブ（SCR-016）
 * タブ切り替えで複数ツールを提供（全てフロント計算）
 *
 * 収録ツール:
 * 1. ADCコード/スケーリング
 * 2. 電解コンデンサ寿命推定
 * 3. 抵抗分圧/温度変換（NTC/PTC対応）
 * 4. 電流検出解析（シャント抵抗）
 * 5. 電源余裕解析（供給電力 vs 消費電力）
 * 6. 比較器しきい値/ヒステリシス
 * 7. 熱設計（熱抵抗チェーン）
 * 8. インタフェース余裕解析（VOH/VOL/VIH/VIL）
 * 9. 誤差/歩留まり、保護回路、接続/起動診断などの簡易設計補助
 */
import { ref, reactive, computed } from 'vue';

export default function setup() {
    const activeToolId = ref(document.getElementById('app')?.dataset?.tool ?? 'adc');

    const tools = [
        { id: 'adc',        label: 'ADCスケーリング', desc: '入力電圧をADCデジタルコードに変換し、スケーリング係数・LSBサイズ・フルスケール誤差を計算します。' },
        { id: 'cap-life',   label: 'コンデンサ寿命',  desc: 'アレニウス則に基づき、動作温度・リプル電流から電解コンデンサの推定寿命を算出します。' },
        { id: 'divider',    label: '分圧・温度変換',  desc: '抵抗分圧回路の出力電圧を計算し、NTC/PTCサーミスタの温度変換も行います。' },
        { id: 'shunt',      label: '電流検出',        desc: 'シャント抵抗の両端電圧と消費電力から電流値を求め、検出回路の設計値を評価します。' },
        { id: 'power',      label: '電源余裕',        desc: '供給電力と各負荷の消費電力を比較し、電源の余裕度（マージン）を確認します。' },
        { id: 'comparator', label: '比較器',          desc: '比較器のしきい値電圧とヒステリシス幅を計算します。ポジティブ/ネガティブフィードバック対応。' },
        { id: 'thermal',    label: '熱設計',          desc: '熱抵抗チェーンを積み上げ、接合温度を推定します。放熱板・TIM・パッケージ熱抵抗を考慮。' },
        { id: 'interface',  label: 'IF余裕',          desc: 'VOH/VOL/VIH/VILを入力してロジックインタフェースの電圧余裕（ノイズマージン）を評価します。' },
        { id: 'tolerance',  label: '誤差/歩留まり',   desc: '部品公差の最悪値/RSSと、正規分布前提の歩留まりを同じフォームで確認します。' },
        { id: 'bode',       label: '周波数応答',       desc: '一次RCフィルタのカットオフ、指定周波数でのゲイン、位相を見積もります。' },
        { id: 'ovp',        label: '過電圧保護',       desc: '直列抵抗、クランプ電圧、入力過電圧から保護素子電流と損失を確認します。' },
        { id: 'tvs',        label: 'TVS保護',          desc: 'サージ電圧とインピーダンスからTVSのピーク電流・ピーク電力を見積もります。' },
        { id: 'fuse',       label: 'ヒューズ選定',     desc: '定格電流、負荷電流、周囲温度からヒューズ選定の余裕を確認します。' },
        { id: 'polyfuse',   label: 'ポリスイッチ',     desc: '保持電流・抵抗・負荷電流から発熱と保持余裕を確認します。' },
        { id: 'protection', label: '保護協調',         desc: 'OVP/TVS/ヒューズ/PTC/eFuse/逆接保護を同じ故障順序で確認します。' },
        { id: 'logic-ic',   label: 'ロジックIC参照',   desc: '74xx/40xx系の機能、入力数、電源範囲、出力形式から置換候補と注意点を確認します。' },
        { id: 'connector',  label: 'コネクタ参照',     desc: 'ピン数、電流、用途からコネクタ選定時の確認観点を整理します。' },
        { id: 'cable',      label: 'ケーブル判定',     desc: '両端ピン列を比較し、ストレート/クロス/カスタム配線を判定します。' },
        { id: 'jumper',     label: '0Ω/Jumper整理',   desc: '0Ω、未実装、ジャンパ設定の目的と量産状態を一覧化します。' },
        { id: 'startup',    label: '起動診断',         desc: '電源レール、依存関係、リセット解除順をテンプレートで確認します。' },
    ];
    const activeTool = computed(() => tools.find(t => t.id === activeToolId.value));
    const hubBands = [
        { label: '共通条件', value: 'pass/fail、margin、支配要因、次アクションまで返す' },
        { label: '初期優先帯', value: '誤差/電源/ADC/IF/電流検出を設計判断として扱う' },
        { label: '見送り基準', value: '単発公式だけの電卓は採用せず、条件不足なら判定不能にする' },
    ];

    const ENGINEERING_PREFIX_FACTORS = {
        Y: 1e24,
        Z: 1e21,
        E: 1e18,
        P: 1e15,
        T: 1e12,
        G: 1e9,
        M: 1e6,
        meg: 1e6,
        k: 1e3,
        K: 1e3,
        '': 1,
        m: 1e-3,
        u: 1e-6,
        n: 1e-9,
        p: 1e-12,
        f: 1e-15,
        Ti: 1099511627776,
        Gi: 1073741824,
        Mi: 1048576,
        Ki: 1024,
    };
    const normalizeUnitText = (value) => String(value ?? '')
        .replace(/[μµ]/g, 'u')
        .replace(/[Ωω]/g, 'ohm')
        .replace(/[−－]/g, '-')
        .trim();
    const normalizePrefixToken = (prefix = '') => {
        const normalized = String(prefix || '').trim();
        if (/^meg$/iu.test(normalized)) return 'meg';
        if (normalized === 'µ' || normalized === 'μ') return 'u';
        return normalized;
    };
    const engineeringMultiplier = (prefix) => ENGINEERING_PREFIX_FACTORS[normalizePrefixToken(prefix)] ?? 1;
    const parseEngineeringNumberDetail = (value) => {
        if (typeof value === 'number') {
            return Number.isFinite(value) ? { value, hasUnit: false } : null;
        }
        const raw = normalizeUnitText(value).replace(/,/g, '').replace(/\s+/g, '');
        if (!raw) return null;
        const match = raw.match(/^([-+]?(?:\d+\.?\d*|\.\d+)(?:[eE][-+]?\d+)?)(Ti|Gi|Mi|Ki|MEG|Meg|meg|Y|Z|E|P|T|G|M|k|K|m|u|n|p|f)?([A-Za-z%°℃/_^.-].*)?$/u);
        if (!match) return null;
        const number = Number(match[1]);
        if (!Number.isFinite(number)) return null;
        return {
            value: number * engineeringMultiplier(match[2] || ''),
            hasPrefix: String(match[2] || '').trim() !== '',
            hasUnit: String(match[3] || '').trim() !== '',
        };
    };
    const parseEngineeringNumber = (value, fallback = 0) => {
        const parsed = parseEngineeringNumberDetail(value);
        return parsed ? parsed.value : fallback;
    };
    const toFinite = (value, fallback = 0) => {
        const number = parseEngineeringNumber(value, Number.NaN);
        return Number.isFinite(number) ? number : fallback;
    };
    const formatNumber = (value, digits = 3, unit = '') => {
        const number = Number(value);
        if (!Number.isFinite(number)) return `--${unit ? ` ${unit}` : ''}`;
        return `${number.toFixed(digits)}${unit ? ` ${unit}` : ''}`;
    };
    const unitMultiplier = (spec) => {
        const unit = normalizeUnitText(spec?.unit || spec?.normalized_unit || '')
            .replace(/\s+/g, '')
            .replace(/^meg(?=ohm|v|a|w|f|hz|s|j)/iu, 'M')
            .replace(/^micro(?=ohm|v|a|w|f|hz|s|j)/iu, 'u');
        if (!unit || unit.includes('%')) return 1;
        const match = unit.match(/^(Ti|Gi|Mi|Ki|Y|Z|E|P|T|G|M|meg|k|K|m|u|n|p|f)?(?:ohm|V|v|A|a|W|w|F|f|Hz|hz|S|s|J|j|B|bit|bps)/u);
        if (!match) return 1;
        return engineeringMultiplier(match[1]);
    };
    const parseNumber = (value, fallback = 0) => parseEngineeringNumber(value, fallback);
    const setNumericInput = (target, key, value, storedUnitFactor = 1) => {
        const raw = typeof value === 'object' && value?.target ? value.target.value : value;
        if (raw === null || raw === undefined || String(raw).trim() === '') {
            target[key] = '';
            return;
        }
        const parsed = parseEngineeringNumberDetail(raw);
        if (parsed) {
            const scale = Number(storedUnitFactor) || 1;
            const shouldConvertToStoredUnit = scale !== 1 && (parsed.hasUnit || parsed.hasPrefix);
            target[key] = shouldConvertToStoredUnit ? parsed.value / scale : parsed.value;
        }
    };
    const report = ({
        verdict,
        tone,
        summary,
        metrics = [],
        dominantFactors = [],
        warnings = [],
        nextActions = [],
        assumptions = [],
        missingConditions = [],
        margin = null,
        copySummary = '',
        candidateLinks = [],
    }) => ({
        verdict,
        tone,
        summary,
        metrics,
        dominantFactors,
        warnings,
        nextActions,
        assumptions,
        missingConditions,
        margin,
        copySummary: copySummary || `${verdict}: ${summary}`,
        candidateLinks,
    });
    const hasRating = (value) => {
        if (value === null || value === undefined) return false;
        if (typeof value === 'string' && value.trim() === '') return false;
        return Number.isFinite(parseEngineeringNumber(value, Number.NaN));
    };
    const ratingMissing = (ratings) => ratings.filter((rating) => !hasRating(rating.value)).map((rating) => rating.label);
    const diagramFocus = ref(null);
    const focusDiagram = (key) => {
        if (key) diagramFocus.value = key;
    };
    const clearDiagramFocus = () => {
        diagramFocus.value = null;
    };
    const isDiagramFocused = (key) => diagramFocus.value === key;
    const diagramItemClass = (key) => {
        const active = diagramFocus.value === key;
        const dimmed = diagramFocus.value && diagramFocus.value !== key;
        return {
            'is-active': active,
            'is-dimmed': dimmed,
        };
    };

    const quickForms = reactive({
        tolerance: { nominal: 1000, tolerancePct: 1, count: 2, lsl: 1980, usl: 2020, mean: 2000, sigma: 5, errorSources: 'R1,1\nR2,1\nADC,0.05', monteCarloRuns: 10000, targetCenter: 2000 },
        bode: { type: 'lowpass', r: 10000, c: 0.00000001, freq: 1000, rTolerancePct: 1, cTolerancePct: 10, passbandFreq: 100, stopbandFreq: 10000 },
        ovp: { vinMax: 24, vClamp: 5.6, seriesR: 1000, loadCurrent: 0.002, currentRating: '', powerRating: '', seriesPowerRating: '' },
        tvs: { surgeV: 1000, lineImpedance: 42, clampV: 33, pulseMs: 1, waveformFactor: 0.5, peakPowerRating: '', energyRating: '' },
        fuse: { ratedCurrent: 2, loadCurrent: 1.2, ambient: 50, deratingPct: 25, currentRating: '' },
        polyfuse: { holdCurrent: 0.75, tripCurrent: 1.5, loadCurrent: 0.5, resistance: 0.4, ambient: 40, holdCurrentRating: '', powerRating: '' },
        protection: { faultV: 24, faultCurrent: 3, tvsPowerRating: 600, fuseI2t: 10, ptcHold: 0.75, efuseLimit: 2, reverseDrop: 0.4, loadCurrent: 0.6 },
        'logic-ic': { family: '74HC', function: 'nand', inputs: 2, packagePins: 14, supplyV: 3.3, outputType: 'push-pull' },
        connector: { pins: 10, currentPerPin: 1, environment: 'board-to-wire', connectorType: 'pin-header', viewSide: 'mating-face', pin1Mark: 'silk-dot', pitchMm: 2.54, currentRatingPerPin: '', tempRiseLimit: '', deratingPct: 50 },
        cable: { endA: '1,2,3,4', endB: '1,2,3,4' },
        jumper: { entries: 'JP1,0Ω,debug,未実装,未実装,BOM DNP\nR105,0Ω,variant,実装,実装,BOM mount\nJP_BOOT,ジャンパ,boot,切替,未実装,BOM option' },
        startup: { template: 'pmic-mcu', rails: 'VIN,,10\n3V3,VIN,5\n1V8,3V3,3\nRESET,3V3,20', pgSignals: 'PG_3V3,3V3,5\nRESET_MCU,3V3,20', partialPowerPaths: 'I2C_SDA->MCU_VDD\nUSB_D+->3V3', resetHoldMs: 20 },
    });

    const activeDiagram = computed(() => {
        if (activeToolId.value === 'adc') {
            return {
                type: 'flow',
                title: 'ADC入力レンジの流れ',
                subtitle: '入力電圧、オフセット、Vrefの関係を見てからコード化係数を確認します。',
                formula: 'code = round((Vin - offset) / Vref * (2^bits - 1))',
                parts: [
                    { key: 'vin', label: 'Vin', desc: 'ADCへ入る入力電圧' },
                    { key: 'offset', label: 'offset', desc: '0点補正電圧' },
                    { key: 'vref', label: 'Vref', desc: 'ADC基準電圧' },
                    { key: 'adc', label: 'ADC', desc: `${adc.bits}bit 変換器` },
                    { key: 'code', label: 'コード', desc: `現在 ${adcResult.value.code}` },
                ],
                blocks: [
                    { key: 'vin', label: 'Vin', sub: `${adc.vin} V` },
                    { key: 'offset', label: 'offset補正', sub: `${adc.offset} V` },
                    { key: 'adc', label: `${adc.bits}bit ADC`, sub: `Vref ${adc.vref} V` },
                    { key: 'code', label: '変換コード', sub: adcResult.value.hex },
                ],
                assumptions: ['入力源インピーダンス、サンプル時間、ADC入力容量は別途確認します。'],
            };
        }

        if (activeToolId.value === 'cap-life') {
            return {
                type: 'flow',
                title: '電解コンデンサのストレス要因',
                subtitle: '定格寿命に対し、温度とリプル自己発熱が寿命を支配します。',
                formula: 'life = L0 * 2^((T0 - T_effective)/10)',
                parts: [
                    { key: 'L0', label: 'L0', desc: '定格寿命' },
                    { key: 'T', label: 'T', desc: '動作温度' },
                    { key: 'V', label: 'V', desc: '動作電圧' },
                    { key: 'life', label: '寿命', desc: `${capResult.value.life_y} 年` },
                ],
                blocks: [
                    { key: 'L0', label: '定格寿命', sub: `${cap.L0} h @ ${cap.T0} degC` },
                    { key: 'T', label: '温度ストレス', sub: `${cap.T} degC` },
                    { key: 'V', label: '電圧ストレス', sub: `${cap.V} / ${cap.Vr} V` },
                    { key: 'life', label: '推定寿命', sub: `${capResult.value.life_y} 年` },
                ],
                assumptions: ['自己発熱はESRと暫定熱抵抗10degC/Wからの目安です。電圧は寿命倍率ではなくディレーティングで確認します。'],
            };
        }

        if (activeToolId.value === 'divider') {
            if (divider.mode === 'ntc') {
                return {
                    type: 'divider-ntc',
                    title: 'NTC抵抗値から温度へ変換',
                    subtitle: '測定済みのNTC抵抗値をB定数式へ入れ、温度を逆算します。',
                    formula: '1/T = 1/T0 + ln(Rntc / R0) / B',
                    keys: { input: 'Rmeas', upper: 'R0', lower: 'Rmeas', output: 'temp' },
                    parts: [
                        { key: 'R0', label: 'R0', desc: `基準抵抗 ${divider.R0} ohm` },
                        { key: 'T0', label: 'T0', desc: `基準温度 ${divider.T0} degC` },
                        { key: 'B', label: 'B', desc: `B定数 ${divider.B}` },
                        { key: 'Rmeas', label: 'Rntc', desc: `測定抵抗 ${divider.Rmeas} ohm` },
                        { key: 'temp', label: '温度', desc: `${dividerResult.value.temp_c} degC` },
                    ],
                    assumptions: ['自己発熱、固定抵抗公差、ADC量子化誤差は未評価です。'],
                };
            }
            return {
                type: 'divider',
                title: '抵抗分圧の位置関係',
                subtitle: 'Vin側がR1、GND側がR2です。中央ノードVoutを後段へ渡します。',
                formula: 'Vout = Vin * R2 / (R1 + R2)',
                keys: { input: 'vin', upper: 'r1', lower: 'r2', output: 'vout' },
                parts: [
                    { key: 'vin', label: 'Vin', desc: `入力 ${divider.vin} V` },
                    { key: 'r1', label: 'R1 上側抵抗', desc: `${divider.r1} ohm` },
                    { key: 'r2', label: 'R2 下側抵抗', desc: `${divider.r2} ohm` },
                    { key: 'vout', label: 'Vout', desc: `${dividerResult.value.vout} V` },
                ],
                assumptions: ['後段入力インピーダンスは十分高い前提です。'],
            };
        }

        if (activeToolId.value === 'shunt') {
            return {
                type: 'shunt',
                title: 'ローサイド電流検出の位置関係',
                subtitle: '負荷電流がRsを流れ、シャント電圧をアンプで増幅してVoutにします。',
                formula: shunt.mode === 'from_vout' ? 'I = Vout / gain / Rs' : 'Vout = I * Rs * gain',
                parts: [
                    { key: 'I', label: 'I', desc: `負荷電流 ${shunt.mode === 'from_current' ? shunt.I : shuntResult.value.I} A` },
                    { key: 'Rs', label: 'Rs シャント抵抗', desc: `${shunt.Rs} ohm` },
                    { key: 'gain', label: 'gain', desc: `アンプゲイン ${shunt.gain}` },
                    { key: 'Vout', label: 'Vout', desc: `${shunt.mode === 'from_current' ? shuntResult.value.Vout : shunt.Vout} V` },
                ],
                assumptions: ['ケルビン接続、アンプ入力範囲、シャント電力定格は別途確認します。'],
            };
        }

        if (activeToolId.value === 'power') {
            const loadCount = power.loads.length;
            return {
                type: 'power',
                title: '電源から負荷群への電力配分',
                subtitle: '供給電力に対し、各負荷の電流と電圧から消費電力を積み上げます。',
                formula: 'Pload = sum(Iload * Vrail), margin = Psupply - Pload',
                parts: [
                    { key: 'supply', label: '供給電力', desc: `${power.supply_w} W` },
                    { key: 'loads', label: '負荷群', desc: `${loadCount} loads` },
                    { key: 'margin', label: '余裕', desc: `${powerResult.value.margin} W` },
                ],
                assumptions: ['突入電流、効率、温度ディレーティングは未入力です。'],
            };
        }

        if (activeToolId.value === 'comparator') {
            return {
                type: 'comparator',
                title: '比較器ヒステリシスの抵抗位置',
                subtitle: 'R1は入力直列、R2は基準側、R3は出力から基準しきい値ノードへ戻る帰還抵抗です。',
                formula: comp.R3 > 0 ? 'Vth = (Vref/R2 + Vout/R3) / (1/R2 + 1/R3)' : 'R3=0 のため Vth = Vref',
                parts: [
                    { key: 'Vcc', label: 'Vcc', desc: `${comp.Vcc} V` },
                    { key: 'Vref', label: 'Vref', desc: `${comp.Vref} V` },
                    { key: 'R1', label: 'R1 入力直列抵抗', desc: `${comp.R1} ohm` },
                    { key: 'R2', label: 'R2 基準側抵抗', desc: `${comp.R2} ohm` },
                    { key: 'R3', label: 'R3 帰還抵抗', desc: comp.R3 > 0 ? `${comp.R3} ohm` : 'なし' },
                    { key: 'out', label: 'OUT', desc: `High ${comp.Vcc} V想定` },
                ],
                assumptions: ['R1は入力直列抵抗として扱い、基準しきい値式には入れません。入力オフセット、出力High/Low実電圧、入力バイアスはデータシート値へ置き換えます。'],
            };
        }

        if (activeToolId.value === 'thermal') {
            return {
                type: 'thermal',
                title: '熱抵抗チェーン',
                subtitle: '発熱源から周囲温度までの熱抵抗を直列に積み上げます。',
                formula: 'Tj = Tambient + P * sum(Rth)',
                parts: [
                    { key: 'P', label: 'P 発熱', desc: `${thermal.P} W` },
                    { key: 'Tambient', label: 'Ta 周囲温度', desc: `${thermal.Tambient} degC` },
                    { key: 'nodes', label: 'Rth chain', desc: `${thermal.nodes.length} stages` },
                    { key: 'Tj', label: 'Tj', desc: `${thermalResult.value.Tjunction} degC` },
                ],
                assumptions: ['基板銅箔、風速、隣接発熱体は未モデル化です。'],
            };
        }

        if (activeToolId.value === 'interface') {
            return {
                type: 'interface',
                title: 'ロジック出力と入力しきい値',
                subtitle: 'ドライバのVOH/VOLとレシーバのVIH/VILを向かい合わせて余裕を見ます。',
                formula: 'H margin = VOH - VIH, L margin = VIL - VOL',
                parts: [
                    { key: 'VOH', label: 'VOH', desc: `${iface.VOH} V` },
                    { key: 'VOL', label: 'VOL', desc: `${iface.VOL} V` },
                    { key: 'VIH', label: 'VIH', desc: `${iface.VIH} V` },
                    { key: 'VIL', label: 'VIL', desc: `${iface.VIL} V` },
                ],
                assumptions: ['電源min/max、温度、出力電流条件は未入力です。'],
            };
        }

        const quick = quickForms[activeToolId.value];
        if (!quick) return null;

        if (activeToolId.value === 'tolerance') {
            return {
                type: 'flow',
                title: '誤差源から歩留まりへの流れ',
                subtitle: '公称値と公差を、最悪値/RSS/正規分布の歩留まりへ展開します。',
                formula: 'RSS = nominal * tolerance * sqrt(count)',
                parts: [
                    { key: 'nominal', label: '公称値', desc: `${quick.nominal}` },
                    { key: 'tolerancePct', label: '公差', desc: `${quick.tolerancePct} %` },
                    { key: 'sigma', label: 'sigma', desc: `${quick.sigma}` },
                    { key: 'yield', label: 'yield', desc: '推定歩留まり' },
                ],
                blocks: [
                    { key: 'nominal', label: '公称値', sub: `${quick.nominal}` },
                    { key: 'tolerancePct', label: '公差源', sub: `${quick.tolerancePct} % x ${quick.count}` },
                    { key: 'sigma', label: '分布条件', sub: `mean ${quick.mean}, sigma ${quick.sigma}` },
                    { key: 'yield', label: '歩留まり', sub: `${quick.lsl} - ${quick.usl}` },
                ],
                assumptions: ['分布形状、相関、温度依存は未入力です。'],
            };
        }

        if (activeToolId.value === 'bode') {
            const lowpass = quick.type === 'lowpass';
            return {
                type: 'bode',
                title: lowpass ? 'RCローパスの部品位置' : 'RCハイパスの部品位置',
                subtitle: lowpass ? 'Rは入力直列、Cは出力ノードからGNDへ入ります。' : 'Cは入力直列、Rは出力ノードからGNDへ入ります。',
                formula: 'fc = 1 / (2πRC)',
                variant: quick.type,
                parts: [
                    { key: 'r', label: 'R フィルタ抵抗', desc: `${quick.r} ohm` },
                    { key: 'c', label: 'C フィルタ容量', desc: `${quick.c} F` },
                    { key: 'freq', label: '評価周波数', desc: `${quick.freq} Hz` },
                    { key: 'out', label: 'Vout', desc: '評価点' },
                ],
                assumptions: ['負荷インピーダンス、部品公差、寄生成分は未入力です。'],
            };
        }

        if (['ovp', 'tvs', 'fuse', 'polyfuse'].includes(activeToolId.value)) {
            const protectionMap = {
                ovp: {
                    title: '過電圧クランプの電流経路',
                    subtitle: '入力過電圧を直列抵抗で制限し、クランプ素子へ逃がします。',
                    formula: 'Iclamp = max((VinMax - Vclamp) / Rser - Iload, 0)',
                    keys: { input: 'vinMax', series: 'seriesR', clamp: 'vClamp', load: 'loadCurrent' },
                    labels: { input: 'Vin max', series: 'Rser', clamp: 'Clamp', load: 'Load' },
                    parts: [
                        { key: 'vinMax', label: 'Vin max', desc: `${quick.vinMax} V` },
                        { key: 'seriesR', label: 'Rser 直列抵抗', desc: `${quick.seriesR} ohm` },
                        { key: 'vClamp', label: 'Clamp', desc: `${quick.vClamp} V` },
                        { key: 'loadCurrent', label: 'Iload', desc: `${quick.loadCurrent} A` },
                    ],
                },
                tvs: {
                    title: 'TVSサージ電流経路',
                    subtitle: '線路インピーダンスを通ったサージをTVSがクランプします。',
                    formula: 'Ipeak = (Vsurge - Vclamp) / Zline',
                    keys: { input: 'surgeV', series: 'lineImpedance', clamp: 'clampV', load: 'pulseMs' },
                    labels: { input: 'Vsurge', series: 'Zline', clamp: 'TVS', load: 'Pulse' },
                    parts: [
                        { key: 'surgeV', label: 'Vsurge', desc: `${quick.surgeV} V` },
                        { key: 'lineImpedance', label: 'Zline', desc: `${quick.lineImpedance} ohm` },
                        { key: 'clampV', label: 'TVS clamp', desc: `${quick.clampV} V` },
                        { key: 'pulseMs', label: 'Pulse', desc: `${quick.pulseMs} ms` },
                    ],
                },
                fuse: {
                    title: 'ヒューズと負荷電流の位置関係',
                    subtitle: '電源と負荷の間にF1を置き、ディレーティング後の使用可能電流と比べます。',
                    formula: 'Iusable = Irated * (1 - derating)',
                    keys: { input: 'ratedCurrent', series: 'ratedCurrent', clamp: 'deratingPct', load: 'loadCurrent' },
                    labels: { input: 'Supply', series: 'F1', clamp: 'derating', load: 'Load' },
                    parts: [
                        { key: 'ratedCurrent', label: 'F1 定格電流', desc: `${quick.ratedCurrent} A` },
                        { key: 'loadCurrent', label: 'Iload', desc: `${quick.loadCurrent} A` },
                        { key: 'ambient', label: 'Ta', desc: `${quick.ambient} degC` },
                        { key: 'deratingPct', label: 'derating', desc: `${quick.deratingPct} %` },
                    ],
                },
                polyfuse: {
                    title: 'ポリスイッチの保持/トリップ領域',
                    subtitle: 'PTCを負荷直列に入れ、保持電流、トリップ電流、自己発熱を確認します。',
                    formula: 'Ploss = Iload^2 * Rptc',
                    keys: { input: 'tripCurrent', series: 'holdCurrent', clamp: 'resistance', load: 'loadCurrent' },
                    labels: { input: 'Supply', series: 'PTC', clamp: 'RPTC', load: 'Load' },
                    parts: [
                        { key: 'holdCurrent', label: 'Ihold', desc: `${quick.holdCurrent} A` },
                        { key: 'tripCurrent', label: 'Itrip', desc: `${quick.tripCurrent} A` },
                        { key: 'loadCurrent', label: 'Iload', desc: `${quick.loadCurrent} A` },
                        { key: 'resistance', label: 'Rptc', desc: `${quick.resistance} ohm` },
                    ],
                },
            };
            return {
                type: 'protection',
                ...protectionMap[activeToolId.value],
                assumptions: ['部品のパルス/連続定格、温度ディレーティング、故障波形は未入力です。'],
            };
        }

        if (activeToolId.value === 'protection') {
            return {
                type: 'flow',
                title: '保護協調の故障電流経路',
                subtitle: '入力異常をTVS/OVPで抑え、ヒューズ/PTC/eFuse/逆接保護の順に定格余裕を見ます。',
                formula: 'fault -> TVS/OVP -> fuse/PTC/eFuse -> load',
                parts: [
                    { key: 'faultV', label: '故障電圧', desc: `${quick.faultV} V` },
                    { key: 'faultCurrent', label: '故障電流', desc: `${quick.faultCurrent} A` },
                    { key: 'tvsPowerRating', label: 'TVS', desc: `${quick.tvsPowerRating} W` },
                    { key: 'efuseLimit', label: 'eFuse', desc: `${quick.efuseLimit} A` },
                    { key: 'reverseDrop', label: '逆接保護', desc: `${quick.reverseDrop} V` },
                ],
                blocks: [
                    { key: 'faultV', label: '入力異常', sub: `${quick.faultV} V / ${quick.faultCurrent} A` },
                    { key: 'tvsPowerRating', label: 'クランプ', sub: `TVS ${quick.tvsPowerRating} W` },
                    { key: 'fuseI2t', label: '遮断', sub: `I2t ${quick.fuseI2t}` },
                    { key: 'loadCurrent', label: '負荷', sub: `${quick.loadCurrent} A` },
                ],
                assumptions: ['故障波形、ヒューズ時間電流特性、TVS熱インピーダンスはデータシートで確認します。'],
            };
        }

        if (activeToolId.value === 'logic-ic') {
            return {
                type: 'flow',
                title: 'ロジックICの機能と入出力条件',
                subtitle: '機能、ファミリ、電源電圧、出力形式を候補ICとデータシート確認項目へつなげます。',
                formula: 'family + function + Vcc + output -> candidate IC',
                parts: [
                    { key: 'family', label: 'family', desc: quick.family },
                    { key: 'function', label: 'function', desc: quick.function },
                    { key: 'supplyV', label: 'Vcc', desc: `${quick.supplyV} V` },
                    { key: 'outputType', label: 'output', desc: quick.outputType },
                ],
                blocks: [
                    { key: 'family', label: quick.family, sub: `${quick.supplyV} V` },
                    { key: 'function', label: quick.function, sub: `${quick.inputs}入力` },
                    { key: 'packagePins', label: 'package', sub: `${quick.packagePins} pins` },
                    { key: 'outputType', label: 'output', sub: quick.outputType },
                ],
                assumptions: ['同一型番でもファミリごとにVIH/VIL、ドライブ電流、速度、入力耐圧が異なります。'],
            };
        }

        if (activeToolId.value === 'connector') {
            return {
                type: 'connector',
                title: 'コネクタの視点と電流配分',
                subtitle: 'ピン1、嵌合面/はんだ面、1pin電流、温度上昇条件を同時に確認します。',
                formula: 'Iusable = Irating/pin * derating, Itotal = pins * currentPerPin',
                parts: [
                    { key: 'connectorType', label: 'type', desc: quick.connectorType },
                    { key: 'viewSide', label: 'view', desc: quick.viewSide },
                    { key: 'environment', label: '用途', desc: quick.environment },
                    { key: 'pins', label: 'pins', desc: `${quick.pins}` },
                    { key: 'currentPerPin', label: 'I/pin', desc: `${quick.currentPerPin} A` },
                    { key: 'pin1Mark', label: 'pin1', desc: quick.pin1Mark },
                ],
                assumptions: ['mating face/solder sideの視点、隣接ピン同時通電、温度上昇条件は図面注記に残します。'],
            };
        }

        if (activeToolId.value === 'cable') {
            return {
                type: 'connector',
                title: '端A/端Bのピン列対応',
                subtitle: '両端の視点が揃っているかを見て、ストレート/反転/カスタムを判定します。',
                formula: 'EndA[i] == EndB[i] ならストレート',
                parts: [
                    { key: 'endA', label: '端A', desc: quick.endA },
                    { key: 'endB', label: '端B', desc: quick.endB },
                ],
                assumptions: ['mating face/solder sideのどちらで書いたピン列かを図面に明記します。'],
            };
        }

        if (activeToolId.value === 'jumper') {
            return {
                type: 'flow',
                title: 'ジャンパ設定から量産状態へ',
                subtitle: 'Ref、種類、目的、実装状態を並べ、未定義の量産リスクを減らします。',
                formula: 'Ref, type, purpose, state',
                parts: [
                    { key: 'entries', label: 'entries', desc: 'ジャンパ一覧' },
                    { key: 'state', label: 'state', desc: '実装/未実装/切替' },
                    { key: 'bom', label: 'BOM note', desc: '量産注記' },
                ],
                blocks: [
                    { key: 'entries', label: 'Ref一覧', sub: '0Ω / JP' },
                    { key: 'state', label: '実装状態', sub: '実装 / 未実装' },
                    { key: 'bom', label: 'BOM注記', sub: '量産初期値' },
                ],
                assumptions: ['量産時の標準状態とデバッグ時の変更手順を別途残します。'],
            };
        }

        if (activeToolId.value === 'startup') {
            return {
                type: 'startup',
                title: '起動依存グラフ',
                subtitle: '親レール、子レール、PG/RESET、部分給電経路を依存関係として見ます。',
                formula: 'rail,parent,rise_ms/reset_ms + pg/reset/backpower',
                parts: [
                    { key: 'template', label: 'template', desc: quick.template },
                    { key: 'rails', label: 'rails', desc: 'rail,parent,time' },
                    { key: 'reset', label: 'RESET/PG', desc: `${quick.resetHoldMs} ms hold` },
                    { key: 'backpower', label: 'back-power', desc: '部分給電経路' },
                ],
                assumptions: ['PG閾値、RESET入力しきい値、保護ダイオード電流定格はデータシートで確認します。'],
            };
        }

        return null;
    });

    const normalCdf = (x) => 0.5 * (1 + erf(x / Math.SQRT2));
    const erf = (x) => {
        const sign = x < 0 ? -1 : 1;
        const abs = Math.abs(x);
        const t = 1 / (1 + 0.3275911 * abs);
        const y = 1 - (((((1.061405429 * t - 1.453152027) * t) + 1.421413741) * t - 0.284496736) * t + 0.254829592) * t * Math.exp(-abs * abs);
        return sign * y;
    };

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
                    { key: 'pulseMs', label: 'パルス幅(ms)', type: 'number', diagramKey: 'pulseMs', storedUnitFactor: 1e-3 },
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
                    ['周囲温度', `${toFinite(f.ambient).toFixed(1)} degC`],
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
            const catalog = [
                { part: '74HC00', family: '74HC', function: 'nand', inputs: 2, gates: 4, pins: 14, vMin: 2, vMax: 6, output: 'push-pull', note: '汎用NAND' },
                { part: '74HC10', family: '74HC', function: 'nand', inputs: 3, gates: 3, pins: 14, vMin: 2, vMax: 6, output: 'push-pull', note: '3入力NAND' },
                { part: '74HCT00', family: '74HCT', function: 'nand', inputs: 2, gates: 4, pins: 14, vMin: 4.5, vMax: 5.5, output: 'push-pull', note: 'TTL入力互換' },
                { part: 'CD4011B', family: '4000B', function: 'nand', inputs: 2, gates: 4, pins: 14, vMin: 3, vMax: 15, output: 'push-pull', note: '広電源範囲' },
                { part: '74HC04', family: '74HC', function: 'inverter', inputs: 1, gates: 6, pins: 14, vMin: 2, vMax: 6, output: 'push-pull', note: '汎用インバータ' },
                { part: '74HC14', family: '74HC', function: 'schmitt-inverter', inputs: 1, gates: 6, pins: 14, vMin: 2, vMax: 6, output: 'push-pull', note: 'シュミット入力' },
                { part: '74HC74', family: '74HC', function: 'd-ff', inputs: 1, gates: 2, pins: 14, vMin: 2, vMax: 6, output: 'push-pull', note: 'D-FF' },
                { part: '74HC86', family: '74HC', function: 'xor', inputs: 2, gates: 4, pins: 14, vMin: 2, vMax: 6, output: 'push-pull', note: 'XOR' },
                { part: '74HC125', family: '74HC', function: 'buffer', inputs: 1, gates: 4, pins: 14, vMin: 2, vMax: 6, output: '3state', note: '3-state buffer' },
                { part: '74HC138', family: '74HC', function: 'decoder', inputs: 3, gates: 1, pins: 16, vMin: 2, vMax: 6, output: 'push-pull', note: '3-to-8 decoder' },
                { part: '74HC595', family: '74HC', function: 'shift-register', inputs: 1, gates: 1, pins: 16, vMin: 2, vMax: 6, output: '3state', note: '8bit serial-in parallel-out' },
                { part: 'CD4051B', family: '4000B', function: 'mux', inputs: 3, gates: 1, pins: 16, vMin: 3, vMax: 15, output: 'analog-switch', note: '8ch analog mux' },
                { part: 'CD4066B', family: '4000B', function: 'analog-switch', inputs: 1, gates: 4, pins: 14, vMin: 3, vMax: 15, output: 'analog-switch', note: 'quad bilateral switch' },
            ];
            const matches = catalog.filter((item) => {
                const familyMatch = f.family === 'any' || item.family === f.family;
                const functionMatch = f.function === 'any' || item.function === f.function;
                const outputMatch = f.outputType === 'any' || item.output === f.outputType;
                const pinMatch = !toFinite(f.packagePins) || item.pins === toFinite(f.packagePins);
                const inputMatch = !toFinite(f.inputs) || item.inputs === toFinite(f.inputs);
                return familyMatch && functionMatch && outputMatch && pinMatch && inputMatch;
            });
            const supplyMatches = matches.filter((item) => toFinite(f.supplyV) >= item.vMin && toFinite(f.supplyV) <= item.vMax);
            const best = supplyMatches[0] ?? matches[0] ?? null;
            const tone = !matches.length ? 'warn' : (supplyMatches.length ? 'check' : 'bad');
            return {
                title: 'ロジックICリファレンス',
                model: 'logic-ic',
                fields: [
                    { key: 'family', label: 'ファミリ', type: 'select', options: [['any', '指定なし'], ['74HC', '74HC'], ['74HCT', '74HCT'], ['4000B', '4000B']], diagramKey: 'family' },
                    { key: 'function', label: '機能', type: 'select', options: [['any', '指定なし'], ['nand', 'NAND'], ['inverter', 'インバータ'], ['schmitt-inverter', 'シュミットINV'], ['d-ff', 'D-FF'], ['xor', 'XOR'], ['buffer', 'バッファ'], ['decoder', 'デコーダ'], ['shift-register', 'シフトレジスタ'], ['mux', 'MUX'], ['analog-switch', 'アナログSW']], diagramKey: 'function' },
                    { key: 'inputs', label: '入力数の目安', type: 'number', diagramKey: 'function' },
                    { key: 'packagePins', label: 'ピン数(0=不問)', type: 'number', diagramKey: 'packagePins' },
                    { key: 'supplyV', label: '使用Vcc(V)', type: 'number', diagramKey: 'supplyV' },
                    { key: 'outputType', label: '出力形式', type: 'select', options: [['any', '指定なし'], ['push-pull', 'Push-pull'], ['3state', '3-state'], ['analog-switch', 'Analog switch']], diagramKey: 'outputType' },
                ],
                rows: [
                    ['候補数', `${matches.length}`],
                    ['Vcc範囲内', `${supplyMatches.length}`],
                    ['第一候補', best ? `${best.part} / ${best.note}` : '該当なし'],
                    ['候補一覧', (supplyMatches.length ? supplyMatches : matches).slice(0, 6).map((item) => item.part).join(', ') || '該当なし'],
                    ['確認条件', best ? `${best.vMin}-${best.vMax} V / ${best.pins}pin / ${best.output}` : '条件を広げて再検索'],
                ],
                tone,
                dominantFactors: ['ファミリ', '機能', 'Vcc範囲'],
                warnings: [
                    ...(!matches.length ? ['機能・ファミリ・ピン数の条件に合う候補がありません。'] : []),
                    ...(matches.length && !supplyMatches.length ? ['候補ICの電源範囲外です。ファミリまたはVccを見直してください。'] : []),
                    'VIH/VIL、VOH/VOL、出力電流、伝搬遅延、入力5V tolerantはデータシートで確認してください。',
                ],
                missingConditions: ['温度範囲', '負荷電流', '速度条件', '入力しきい値条件'],
                nextActions: ['候補型番のデータシートでVcc、入力しきい値、出力電流、パッケージピン配置を確認する'],
            };
        }

        if (activeToolId.value === 'connector') {
            const pins = Math.max(toFinite(f.pins), 1);
            const totalCurrent = pins * toFinite(f.currentPerPin);
            const missingRatings = ratingMissing([
                { label: '1pin定格電流', value: f.currentRatingPerPin },
                { label: '温度上昇上限', value: f.tempRiseLimit },
            ]);
            const usablePerPin = hasRating(f.currentRatingPerPin)
                ? toFinite(f.currentRatingPerPin) * Math.max(0, Math.min(100, toFinite(f.deratingPct, 50))) / 100
                : null;
            const perPinMargin = usablePerPin === null ? null : usablePerPin - toFinite(f.currentPerPin);
            const viewNote = f.viewSide === 'mating-face' ? 'mating face基準でピン番号を書く' : 'solder side基準で左右反転を明記する';
            const tone = missingRatings.length ? 'check' : (perPinMargin < 0 ? 'bad' : (perPinMargin / Math.max(usablePerPin, 1e-12) < 0.2 ? 'warn' : 'check'));
            return {
                title: 'コネクタ視点補助リファレンス',
                model: 'connector',
                fields: [
                    { key: 'connectorType', label: 'コネクタ種別', type: 'select', options: [['pin-header', 'ピンヘッダ'], ['jst', 'JST系'], ['idc', 'IDC'], ['dsub', 'D-sub'], ['rj45', 'RJ45'], ['usb', 'USB']], diagramKey: 'connectorType' },
                    { key: 'environment', label: '用途', type: 'select', options: [['board-to-wire', '基板-電線'], ['board-to-board', '基板-基板'], ['external', '外部I/F']], diagramKey: 'environment' },
                    { key: 'viewSide', label: '図面視点', type: 'select', options: [['mating-face', 'mating face'], ['solder-side', 'solder side']], diagramKey: 'viewSide' },
                    { key: 'pin1Mark', label: 'Pin1表示', type: 'select', options: [['silk-dot', 'シルク点'], ['triangle', '三角'], ['square-pad', '角ランド'], ['key-notch', 'キー/ノッチ']], diagramKey: 'pin1Mark' },
                    { key: 'pins', label: 'ピン数', type: 'number', diagramKey: 'pins' },
                    { key: 'pitchMm', label: 'ピッチ(mm)', type: 'number', diagramKey: 'pins', storedUnitFactor: 1e-3 },
                    { key: 'currentPerPin', label: '1pin電流(A)', type: 'number', diagramKey: 'currentPerPin' },
                    { key: 'currentRatingPerPin', label: '1pin定格電流(A)', type: 'number', diagramKey: 'currentPerPin' },
                    { key: 'deratingPct', label: '電流derating(%)', type: 'number', diagramKey: 'currentPerPin' },
                    { key: 'tempRiseLimit', label: '温度上昇上限(degC)', type: 'number', diagramKey: 'currentPerPin' },
                ],
                rows: [
                    ['総電流目安', `${totalCurrent.toFixed(2)} A`],
                    ['derating後1pin', usablePerPin === null ? 'CHECK' : `${usablePerPin.toFixed(3)} A`],
                    ['1pin余裕', perPinMargin === null ? 'CHECK' : `${perPinMargin.toFixed(3)} A`],
                    ['図面注記', `${viewNote} / Pin1=${f.pin1Mark}`],
                    ['量産確認', `${f.connectorType}, ${pins}pin, ${toFinite(f.pitchMm).toFixed(2)}mm, ${f.environment}`],
                    ['確認観点', f.environment === 'external' ? '抜き差し回数/ラッチ/ESD' : '誤挿入防止/極性/実装高さ'],
                ],
                tone,
                missingConditions: missingRatings,
                warnings: [
                    ...(missingRatings.length ? ['定格電流または温度上昇上限が未入力のためPASSにはしません。'] : []),
                    ...(perPinMargin !== null && perPinMargin < 0 ? ['derating後の1pin電流定格を超えています。'] : []),
                    '嵌合面とはんだ面の左右反転、Pin1表示、キー形状を図面に併記してください。',
                ],
                nextActions: ['データシートの温度上昇条件、全ピン同時通電条件、mating face/solder side図を確認する'],
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
                    { key: 'rails', label: 'rail,parent,rise_ms/reset_ms', type: 'textarea', diagramKey: 'rails' },
                    { key: 'pgSignals', label: 'PG/RESET信号 名称,親,遅延ms', type: 'textarea', diagramKey: 'reset' },
                    { key: 'partialPowerPaths', label: '部分給電/逆流経路', type: 'textarea', diagramKey: 'backpower' },
                    { key: 'resetHoldMs', label: 'RESET保持時間(ms)', type: 'number', diagramKey: 'reset' },
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

    // ══════════════════════════════════════════════
    // 1. ADCコード/スケーリング
    // ══════════════════════════════════════════════
    const adc = reactive({
        bits: 12, vref: 3.3, vin: 1.65, vinMin: 0.1, vinTyp: 1.65, vinMax: 3.0, offset: 0, physicalMin: 0, physicalMax: 100, fixedPointBits: 16,
    });
    const adcResult = computed(() => {
        const codeCount = Math.pow(2, adc.bits);
        const fullScale = codeCount - 1;
        const lsb       = adc.vref / Math.max(codeCount, 1);
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

    // ══════════════════════════════════════════════
    // 2. 電解コンデンサ寿命推定（アレニウス則）
    // ══════════════════════════════════════════════
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

    // ══════════════════════════════════════════════
    // 3. 抵抗分圧 / NTC温度変換
    // ══════════════════════════════════════════════
    const divider = reactive({
        mode: 'voltage',  // 'voltage' | 'ntc'
        // 電圧分圧
        vin: 5.0, r1: 10000, r2: 10000,
        // NTC
        R0: 10000, T0: 25, B: 3950, Rmeas: 10000, tempMin: -20, tempMax: 85, tempStep: 25, adcBits: 12, adcVref: 3.3, pullupCandidates: '4700,10000,22000',
    });
    const dividerResult = computed(() => {
        if (divider.mode === 'voltage') {
            const vout = divider.vin * divider.r2 / (divider.r1 + divider.r2);
            const ratio = divider.r2 / (divider.r1 + divider.r2);
            return { vout: vout.toFixed(4), ratio: (ratio * 100).toFixed(2) };
        } else {
            // NTC: 1/T = 1/T0 + (1/B)*ln(R/R0)
            const T0k  = divider.T0 + 273.15;
            const Tk   = 1 / (1 / T0k + Math.log(divider.Rmeas / divider.R0) / divider.B);
            const Tc   = Tk - 273.15;
            return { temp_c: Tc.toFixed(2), temp_k: Tk.toFixed(2) };
        }
    });

    // ══════════════════════════════════════════════
    // 4. 電流検出（シャント抵抗）
    // ══════════════════════════════════════════════
    const shunt = reactive({
        Rs: 0.01,    // シャント抵抗(Ω)
        gain: 20,    // アンプゲイン
        Vout: 0.1,   // アンプ出力(V)
        mode: 'from_vout',  // 'from_vout' | 'from_current'
        I: 5,        // 電流(A) → Vout計算用
        powerRating: 0.25,
        tcrPpm: 50,
        ampOffsetUv: 50,
        adcBits: 12,
        adcVref: 3.3,
    });
    const shuntResult = computed(() => {
        const adcLsbMv = shunt.adcVref / (2 ** shunt.adcBits - 1) * 1000;
        const offsetErrorA = shunt.ampOffsetUv / 1e6 / Math.max(shunt.Rs, 1e-12);
        const offsetOutputMv = shunt.ampOffsetUv * Math.max(shunt.gain, 0) / 1000;
        const adcLsbA = adcLsbMv / 1000 / Math.max(shunt.Rs * shunt.gain, 1e-12);
        const enrich = (current, vout, p, vshunt) => ({
            vout_effective_min_v: (vout - Math.abs(offsetOutputMv) / 1000).toFixed(4),
            vout_effective_max_v: (vout + Math.abs(offsetOutputMv) / 1000).toFixed(4),
            P_mW: (p * 1000).toFixed(3),
            Vshunt_mv: (vshunt * 1000).toFixed(3),
            adc_lsb_mv: adcLsbMv.toFixed(4),
            adc_lsb_a: adcLsbA.toFixed(6),
            offset_error_a: offsetErrorA.toFixed(5),
            offset_error_pct: (Math.abs(offsetErrorA) / Math.max(Math.abs(current), 1e-12) * 100).toFixed(3),
            offset_output_mv: offsetOutputMv.toFixed(4),
            vout_value: vout.toFixed(4),
            vout_margin_high_v: (shunt.adcVref - (vout + Math.abs(offsetOutputMv) / 1000)).toFixed(4),
            vout_margin_low_v: (vout - Math.abs(offsetOutputMv) / 1000).toFixed(4),
            power_margin_mw: ((shunt.powerRating - p) * 1000).toFixed(3),
        });
        if (shunt.mode === 'from_vout') {
            const Vshunt = shunt.Vout / Math.max(shunt.gain, 1e-12);
            const I      = Vshunt / shunt.Rs;
            const P      = I * I * shunt.Rs;
            return { I: I.toFixed(4), measured_current_a: I.toFixed(4), ...enrich(I, shunt.Vout, P, Vshunt) };
        } else {
            const Vshunt = shunt.I * shunt.Rs;
            const Vout   = Vshunt * shunt.gain;
            const P      = shunt.I * shunt.I * shunt.Rs;
            return { Vout: Vout.toFixed(4), measured_current_a: shunt.I.toFixed(4), ...enrich(shunt.I, Vout, P, Vshunt) };
        }
    });

    // ══════════════════════════════════════════════
    // 5. 電源余裕解析
    // ══════════════════════════════════════════════
    const power = reactive({
        supply_w: 10,
        efficiencyPct: 85,
        dropoutV: 0.3,
        inrushA: 1.5,
        maxLoadFactor: 1.5,
        rails: 'VIN,,12,2\n3V3,VIN,3.3,0.4\n1V8,3V3,1.8,0.2',
        loads: [{ label: 'MCU', mA: 50, V: 3.3 }, { label: 'Sensor', mA: 20, V: 3.3 }],
    });
    const addLoad   = () => power.loads.push({ label: '', mA: 0, V: 3.3 });
    const removeLoad = (i) => power.loads.splice(i, 1);
    const powerResult = computed(() => {
        const loadItems = power.loads.map((load, index) => ({
            label: load.label || `Load${index + 1}`,
            mA: toFinite(load.mA),
            voltage: toFinite(load.V),
            watts: toFinite(load.mA) * toFinite(load.V) / 1000,
        }));
        const totalW  = loadItems.reduce((s, load) => s + load.watts, 0);
        const effectiveSupply = power.supply_w * power.efficiencyPct / 100;
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
        const assignedLoadIndexes = new Set();
        loadItems.forEach((load, index) => {
            const match = rails.reduce((best, rail) => {
                const tolerance = Math.max(Math.abs(rail.voltage) * 0.03, 0.05);
                const distance = Math.abs(load.voltage - rail.voltage);
                return distance <= tolerance && distance < best.distance ? { rail, distance } : best;
            }, { rail: null, distance: Infinity }).rail;
            if (!match) return;
            match.directLoadW += load.watts;
            match.loadNames.push(load.label);
            assignedLoadIndexes.add(index);
        });
        const railByName = new Map(rails.map((rail) => [rail.name, rail]));
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
        const inputEquivalentW = (roots.length ? roots.reduce((sum, rail) => sum + rail.rolledLoadW, 0) : 0) + unassignedW;
        const maxScenarioW = inputEquivalentW * power.maxLoadFactor;
        const margin  = effectiveSupply - inputEquivalentW;
        const worstMargin = effectiveSupply - maxScenarioW;
        const percent = (inputEquivalentW / Math.max(effectiveSupply, 1e-12) * 100).toFixed(1);
        const railOk = rails.every((rail) => !rail.overloaded);
        return { totalW: totalW.toFixed(3), unassignedW: unassignedW.toFixed(3), inputEquivalentW: inputEquivalentW.toFixed(3), margin: margin.toFixed(3), worstMargin: worstMargin.toFixed(3), percent, ok: margin >= 0 && worstMargin >= 0 && railOk && unassignedLoads.length === 0 && toFinite(power.inrushA) <= 0, railMargins: rails, unassignedLoads, maxScenarioW: maxScenarioW.toFixed(3) };
    });

    // ══════════════════════════════════════════════
    // 6. 比較器しきい値/ヒステリシス
    // ══════════════════════════════════════════════
    const comp = reactive({
        Vcc: 3.3, VOH: 3.3, VOL: 0, R1: 100000, R2: 100000, R3: 0, Vref: 1.65, tolerancePct: 1, inputOffsetMv: 5, noiseMv: 20, candidateResistors: '10000,47000,100000',
    });
    const compResult = computed(() => {
        const toleranceBase = Math.max(Math.abs(comp.Vref), Math.abs(comp.Vcc), 1e-12);
        const r2 = Math.max(comp.R2, 1e-12);
        if (comp.R3 <= 0) {
            const toleranceBand = toleranceBase * comp.tolerancePct / 100 + comp.inputOffsetMv / 1000;
            return { Vth: comp.Vref.toFixed(4), hysteresis: '0.0000', Vth_high: comp.Vref.toFixed(4), Vth_low: comp.Vref.toFixed(4), Vth_rising: comp.Vref.toFixed(4), Vth_falling: comp.Vref.toFixed(4), tolerance_band: toleranceBand.toFixed(4), noise_margin: (-((comp.noiseMv / 1000) * 2 + toleranceBand)).toFixed(4), candidates: [], topology: '基準入力のみ', input_series_ohm: comp.R1 };
        }
        const r3 = Math.max(comp.R3, 1e-12);
        const thresholdForOutput = (vout) => ((comp.Vref / r2) + (vout / r3)) / ((1 / r2) + (1 / r3));
        const Vth_rising = thresholdForOutput(comp.VOL);
        const Vth_falling = thresholdForOutput(comp.VOH);
        const hyst     = Math.abs(Vth_rising - Vth_falling);
        const Vth_high = Math.max(Vth_rising, Vth_falling);
        const Vth_low  = Math.min(Vth_rising, Vth_falling);
        const toleranceBand = Math.max(Math.abs(Vth_high), Math.abs(Vth_low), toleranceBase) * comp.tolerancePct / 100 + comp.inputOffsetMv / 1000;
        const noiseMargin = hyst - (comp.noiseMv / 1000) * 2 - toleranceBand;
        const candidates = String(comp.candidateResistors).split(/[\s,;]+/).filter(Boolean).slice(0, 6).map((value) => parseNumber(value)).filter((value) => value > 0);
        return { Vth_high: Vth_high.toFixed(4), Vth_low: Vth_low.toFixed(4), Vth_rising: Vth_rising.toFixed(4), Vth_falling: Vth_falling.toFixed(4), hysteresis: hyst.toFixed(4), tolerance_band: toleranceBand.toFixed(4), noise_margin: noiseMargin.toFixed(4), candidates, topology: 'R2/R3基準帰還', input_series_ohm: comp.R1 };
    });

    // ══════════════════════════════════════════════
    // 7. 熱設計（熱抵抗チェーン）
    // ══════════════════════════════════════════════
    const thermal = reactive({
        P: 1.0,        // 消費電力(W)
        Tambient: 25,  // 雰囲気温度(°C)
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
    const addNode    = () => thermal.nodes.push({ label: '熱抵抗', Rth: 1 });
    const removeNode = (i) => thermal.nodes.splice(i, 1);
    const thermalResult = computed(() => {
        const totalRth = thermal.nodes.reduce((s, n) => s + n.Rth, 0);
        const Tjunction = thermal.Tambient + thermal.P * totalRth;
        const worstTjunction = thermal.Tambient + thermal.P * thermal.scenarioMultiplier * totalRth;
        const cumulative = [];
        let T = thermal.Tambient;
        for (const n of [...thermal.nodes].reverse()) {
            T += thermal.P * n.Rth;
            cumulative.unshift({ label: n.label, T: T.toFixed(1) });
        }
        const heatsinkCandidates = String(thermal.heatsinkCandidates).split(/[\s,;]+/).filter(Boolean).map((value) => {
            const rth = Number(value);
            const fixedChainRth = thermal.nodes
                .filter((node) => !String(node.label).toLowerCase().includes('θsa') && !String(node.label).includes('放熱板-雰囲気'))
                .reduce((sum, node) => sum + toFinite(node.Rth), 0);
            const candidateTotalRth = fixedChainRth + rth;
            return { rth, totalRth: candidateTotalRth, tj: (thermal.Tambient + thermal.P * candidateTotalRth).toFixed(1) };
        });
        const deratingMargin = thermal.TjLimit - Tjunction - thermal.deratingSlope * Math.max(thermal.Tambient - 25, 0);
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

    // ══════════════════════════════════════════════
    // 8. インタフェース余裕解析
    // ══════════════════════════════════════════════
    const iface = reactive({
        VOH: 2.4, VOL: 0.4,    // 出力側
        VIH: 2.0, VIL: 0.8,    // 入力側
        Vcc_out: 3.3, Vcc_in: 3.3,
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
        const baudError = (iface.uartActualBaud - iface.uartNominalBaud) / Math.max(iface.uartNominalBaud, 1e-12) * 100;
        const riseNs = 0.8473 * iface.pullupOhm * iface.i2cBusCapPf * 1e-3;
        const pullupLow = iface.i2cRiseNsLimit / Math.max(0.8473 * iface.i2cBusCapPf * 1e-3, 1e-12);
        const sinkMa = iface.Vcc_in / Math.max(iface.pullupOhm, 1e-12) * 1000;
        const pullupMin = iface.Vcc_in / Math.max(iface.i2cSinkMaLimit / 1000, 1e-12);
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
        };
    });

    const field = (target, key, label, group = '基本条件', type = 'number', diagramKey = key, storedUnitFactor = 1) => ({ target, key, label, group, type, diagramKey, storedUnitFactor });
    const advancedInputGroups = computed(() => {
        const groups = {
            adc: [
                field(adc, 'vinMin', 'Vin min (V)', '基本条件', 'number', 'vin'),
                field(adc, 'vinTyp', 'Vin typ (V)', '基本条件', 'number', 'vin'),
                field(adc, 'vinMax', 'Vin max (V)', '最悪条件', 'number', 'vin'),
                field(adc, 'physicalMin', '物理量 最小', '出力・保存', 'number', 'code'),
                field(adc, 'physicalMax', '物理量 最大', '出力・保存', 'number', 'code'),
                field(adc, 'fixedPointBits', '固定小数点 Q bits', '出力・保存', 'number', 'code'),
            ],
            'cap-life': [
                field(cap, 'rippleCurrent', 'リプル電流 (A)', '最悪条件', 'number', 'T'),
                field(cap, 'esr', 'ESR (ohm)', '部品定格', 'number', 'T'),
                field(cap, 'ambientWorst', '周囲温度 worst (degC)', '最悪条件', 'number', 'T'),
                field(cap, 'targetLifeY', '目標寿命 (年)', '出力・保存', 'number', 'life'),
                field(cap, 'voltageDeratingPct', '電圧ディレーティング (%)', '部品定格', 'number', 'V'),
            ],
            divider: divider.mode === 'ntc'
                ? [
                    field(divider, 'tempMin', '温度 sweep min (degC)', '最悪条件', 'number', 'temp'),
                    field(divider, 'tempMax', '温度 sweep max (degC)', '最悪条件', 'number', 'temp'),
                    field(divider, 'tempStep', '温度 sweep step (degC)', '最悪条件', 'number', 'temp'),
                    field(divider, 'pullupCandidates', 'プルアップ候補 (ohm)', '部品定格', 'text', 'R0'),
                    field(divider, 'adcBits', 'ADC bits', '出力・保存', 'number', 'temp'),
                    field(divider, 'adcVref', 'ADC Vref (V)', '出力・保存', 'number', 'temp'),
                ]
                : [
                    field(divider, 'tempMin', '想定温度 min (degC)', '最悪条件', 'number', 'vin'),
                    field(divider, 'tempMax', '想定温度 max (degC)', '最悪条件', 'number', 'vin'),
                    field(divider, 'pullupCandidates', '候補抵抗 (ohm)', '部品定格', 'text', 'r1'),
                ],
            shunt: [
                field(shunt, 'powerRating', 'Rs電力定格 (W)', '部品定格', 'number', 'Rs'),
                field(shunt, 'tcrPpm', 'Rs TCR (ppm/degC)', '最悪条件', 'number', 'Rs'),
                field(shunt, 'ampOffsetUv', 'アンプオフセット (uV)', '最悪条件', 'number', 'gain', 1e-6),
                field(shunt, 'adcBits', 'ADC bits', '出力・保存', 'number', 'Vout'),
                field(shunt, 'adcVref', 'ADC Vref (V)', '出力・保存', 'number', 'Vout'),
            ],
            power: [
                field(power, 'efficiencyPct', '効率 (%)', '部品定格', 'number', 'supply'),
                field(power, 'dropoutV', 'dropout (V)', '最悪条件', 'number', 'supply'),
                field(power, 'inrushA', '突入電流 (A)', '最悪条件', 'number', 'loads'),
                field(power, 'maxLoadFactor', '最大負荷係数', '最悪条件', 'number', 'loads'),
                field(power, 'rails', 'レールツリー name,parent,V,A', '基本条件', 'textarea', 'loads'),
            ],
            comparator: [
                field(comp, 'VOH', '出力High電圧 (V)', '基本条件', 'number', 'out'),
                field(comp, 'VOL', '出力Low電圧 (V)', '基本条件', 'number', 'out'),
                field(comp, 'tolerancePct', '抵抗/基準公差 (%)', '部品定格', 'number', 'R1'),
                field(comp, 'inputOffsetMv', '入力オフセット (mV)', '最悪条件', 'number', 'Vref', 1e-3),
                field(comp, 'noiseMv', 'ノイズ振幅 (mV)', '最悪条件', 'number', 'out', 1e-3),
                field(comp, 'candidateResistors', '抵抗候補 (ohm)', '部品定格', 'text', 'R2'),
            ],
            thermal: [
                field(thermal, 'scenarioMultiplier', '最悪発熱係数', '最悪条件', 'number', 'P'),
                field(thermal, 'deratingSlope', 'ディレーティング (degC/degC)', '部品定格', 'number', 'Tj'),
                field(thermal, 'heatsinkCandidates', '放熱候補 Rth', '部品定格', 'text', 'nodes'),
            ],
            interface: [
                field(iface, 'Vcc_out', '送信側Vcc (V)', '基本条件', 'number', 'VOH'),
                field(iface, 'Vcc_in', '受信側Vcc (V)', '基本条件', 'number', 'VIH'),
                field(iface, 'tempMin', '温度 min (degC)', '最悪条件', 'number', 'VOH'),
                field(iface, 'tempMax', '温度 max (degC)', '最悪条件', 'number', 'VIH'),
                field(iface, 'uartNominalBaud', 'UART nominal baud', '出力・保存', 'number', 'VOH'),
                field(iface, 'uartActualBaud', 'UART actual baud', '出力・保存', 'number', 'VIH'),
                field(iface, 'i2cBusCapPf', 'I2Cバス容量 (pF)', '最悪条件', 'number', 'VOL', 1e-12),
                field(iface, 'i2cRiseNsLimit', 'I2C立上り上限 (ns)', '部品定格', 'number', 'VIL', 1e-9),
                field(iface, 'pullupOhm', 'プルアップ (ohm)', '部品定格', 'number', 'VOL'),
                field(iface, 'i2cSinkMaLimit', 'I2C Lowシンク定格 (mA)', '部品定格', 'number', 'VOL', 1e-3),
            ],
        }[activeToolId.value] ?? [];

        return ['基本条件', '最悪条件', '部品定格', '出力・保存']
            .map((label) => ({
                label,
                fields: groups.filter((item) => item.group === label),
            }))
            .filter((group) => group.fields.length);
    });

    const analysisTemplates = [
        {
            id: 'mcu-adc',
            label: 'MCU ADC入力',
            tool_id: 'adc',
            title: 'テンプレート MCU ADC入力',
            payload: { bits: 12, vref: 3.3, vin: 1.65, vinMin: 0.1, vinTyp: 1.65, vinMax: 3.0, offset: 0, physicalMin: 0, physicalMax: 100, fixedPointBits: 16 },
        },
        {
            id: 'rail-3v3',
            label: '3.3V電源',
            tool_id: 'power',
            title: 'テンプレート 3.3V電源余裕',
            payload: { supply_w: 10, efficiencyPct: 85, dropoutV: 0.3, inrushA: 1.5, maxLoadFactor: 1.5, rails: 'VIN,,12,2\n3V3,VIN,3.3,0.4', loads: [{ label: 'MCU', mA: 80, V: 3.3 }, { label: 'Sensor', mA: 20, V: 3.3 }] },
        },
        {
            id: 'i2c-bus',
            label: 'I2Cバス',
            tool_id: 'interface',
            title: 'テンプレート I2Cバス余裕',
            payload: { VOH: 2.4, VOL: 0.4, VIH: 2.0, VIL: 0.8, Vcc_out: 3.3, Vcc_in: 3.3, uartNominalBaud: 115200, uartActualBaud: 115200, i2cBusCapPf: 200, i2cRiseNsLimit: 300, pullupOhm: 2200, i2cSinkMaLimit: 3, tempMin: -40, tempMax: 85 },
        },
        {
            id: 'tvs-protection',
            label: 'TVS保護',
            tool_id: 'protection',
            title: 'テンプレート TVS保護協調',
            payload: { faultV: 24, faultCurrent: 3, tvsPowerRating: 600, fuseI2t: 10, ptcHold: 0.75, efuseLimit: 2, reverseDrop: 0.4, loadCurrent: 0.6 },
        },
    ];
    const templateState = reactive({
        selected: analysisTemplates[0]?.id ?? '',
        status: '',
        message: '',
        error: '',
    });
    const selectedTemplate = computed(() => analysisTemplates.find((item) => item.id === templateState.selected) ?? analysisTemplates[0]);
    const clonePlain = (value) => JSON.parse(JSON.stringify(value));
    const toolPayloadTargets = () => ({
        adc,
        'cap-life': cap,
        divider,
        shunt,
        power,
        comparator: comp,
        thermal,
        interface: iface,
    });
    const applyToolPayload = (toolId, payload) => {
        const target = quickForms[toolId] ?? toolPayloadTargets()[toolId];
        if (!target || !payload) return;
        Object.assign(target, clonePlain(payload));
    };
    const applyAnalysisTemplate = () => {
        const template = selectedTemplate.value;
        if (!template) return;
        activeToolId.value = template.tool_id;
        applyToolPayload(template.tool_id, template.payload);
        outputSave.title = template.title;
        templateState.status = 'success';
        templateState.message = `${template.label} を入力へ反映しました。`;
        templateState.error = '';
    };

    const apiJson = async (url, options = {}) => {
        const token = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') ?? '';
        const hasBody = options.body !== undefined;
        const response = await fetch(url, {
            ...options,
            headers: {
                Accept: 'application/json',
                ...(hasBody ? { 'Content-Type': 'application/json' } : {}),
                ...(token ? { 'X-CSRF-TOKEN': token } : {}),
                ...(options.headers ?? {}),
            },
        });
        const data = await response.json().catch(() => ({}));
        if (!response.ok) {
            throw new Error(data.message || `APIエラー (${response.status})`);
        }
        return data;
    };

    const outputSave = reactive({
        title: '',
        projectId: '',
        componentId: '',
        bomLineKey: '',
        saving: false,
        status: '',
        message: '',
        error: '',
    });
    const componentImport = reactive({
        loading: false,
        status: '',
        message: '',
        error: '',
        component: null,
        applied: [],
    });
    const specLabel = (spec) => `${spec?.spec_type?.name ?? spec?.specType?.name ?? ''} ${spec?.name ?? ''} ${spec?.unit ?? ''} ${spec?.normalized_unit ?? ''}`.toLowerCase();
    const specNumber = (spec) => {
        const numericCandidates = [
            spec?.value_numeric_typ,
            spec?.value_numeric_max,
            spec?.value_numeric_min,
            spec?.normalized_value_numeric,
            spec?.normalized_value,
        ];
        for (const candidate of numericCandidates) {
            const value = Number(candidate);
            if (Number.isFinite(value)) return value;
        }
        const textCandidates = [
            spec?.value,
            spec?.value_text,
        ];
        for (const candidate of textCandidates) {
            const raw = normalizeUnitText(candidate);
            const value = parseNumber(raw, Number.NaN);
            const hasExplicitUnit = /[A-Za-zΩohm%]/u.test(raw.replace(/[-+]?(?:\d+\.?\d*|\.\d+)(?:e[-+]?\d+)?/iu, ''));
            if (Number.isFinite(value)) return hasExplicitUnit ? value : value * unitMultiplier(spec);
        }
        return null;
    };
    const findSpecValue = (component, patterns, scale = 1) => {
        const specs = component?.specs ?? [];
        const hit = specs.find((spec) => patterns.some((pattern) => specLabel(spec).includes(pattern)));
        const value = hit ? specNumber(hit) : null;
        return value === null ? null : value * scale;
    };
    const applyComponentSpecs = (component) => {
        const applied = [];
        const applyNumber = (target, key, value, label) => {
            if (value === null || !Number.isFinite(value)) return;
            target[key] = value;
            applied.push(`${label}: ${value}`);
        };

        if (activeToolId.value === 'adc') {
            applyNumber(adc, 'vref', findSpecValue(component, ['vref', 'reference', '基準電圧']), 'Vref');
            applyNumber(adc, 'vinTyp', findSpecValue(component, ['input voltage', '入力電圧', 'vin']), 'Vin typ');
        } else if (activeToolId.value === 'cap-life') {
            applyNumber(cap, 'Vr', findSpecValue(component, ['rated voltage', '耐圧', '定格電圧']), '定格電圧');
            applyNumber(cap, 'esr', findSpecValue(component, ['esr']), 'ESR');
            applyNumber(cap, 'rippleCurrent', findSpecValue(component, ['ripple', 'リプル']), 'リプル電流');
        } else if (activeToolId.value === 'divider') {
            const resistance = findSpecValue(component, ['resistance', '抵抗']);
            applyNumber(divider, divider.mode === 'ntc' ? 'R0' : 'r1', resistance, '抵抗値');
        } else if (activeToolId.value === 'shunt') {
            applyNumber(shunt, 'Rs', findSpecValue(component, ['resistance', '抵抗']), 'Rs');
            applyNumber(shunt, 'powerRating', findSpecValue(component, ['power', '定格電力']), '電力定格');
        } else if (activeToolId.value === 'power') {
            applyNumber(power, 'supply_w', findSpecValue(component, ['power', '電力']), '供給電力');
            applyNumber(power, 'dropoutV', findSpecValue(component, ['dropout']), 'dropout');
        } else if (activeToolId.value === 'comparator') {
            applyNumber(comp, 'Vcc', findSpecValue(component, ['supply voltage', '電源電圧', 'vcc']), 'Vcc');
            applyNumber(comp, 'inputOffsetMv', findSpecValue(component, ['offset', 'オフセット'], 1000), '入力オフセット');
        } else if (activeToolId.value === 'thermal') {
            applyNumber(thermal, 'P', findSpecValue(component, ['power dissipation', '消費電力', '損失']), '発熱');
            applyNumber(thermal, 'TjLimit', findSpecValue(component, ['junction', 'tj']), 'Tj上限');
        } else if (activeToolId.value === 'interface') {
            applyNumber(iface, 'Vcc_out', findSpecValue(component, ['supply voltage', '電源電圧', 'vcc']), '送信側Vcc');
            applyNumber(iface, 'VOH', findSpecValue(component, ['voh']), 'VOH');
            applyNumber(iface, 'VOL', findSpecValue(component, ['vol']), 'VOL');
        }

        return applied;
    };
    const loadComponentContext = async () => {
        componentImport.loading = true;
        componentImport.status = '';
        componentImport.message = '';
        componentImport.error = '';
        componentImport.applied = [];
        try {
            if (!outputSave.componentId) {
                throw new Error('部品を指定してください。');
            }
            const data = await apiJson(`/api/components/${outputSave.componentId}`);
            componentImport.component = data.data ?? data;
            componentImport.applied = applyComponentSpecs(componentImport.component);
            componentImport.status = 'success';
            componentImport.message = componentImport.applied.length
                ? '登録部品のスペック値を現在の解析入力へ反映しました。'
                : '登録部品を読み込みました。対応するスペック値は手動確認してください。';
        } catch (error) {
            componentImport.status = 'error';
            componentImport.error = error?.message || '登録部品の取り込みに失敗しました。';
        } finally {
            componentImport.loading = false;
        }
    };
    const loadedComponentName = computed(() => {
        const component = componentImport.component;
        if (!component) return '';
        return [component.part_number, component.common_name, component.manufacturer].filter(Boolean).join(' / ');
    });
    const loadedComponentStock = computed(() => {
        const component = componentImport.component;
        if (!component) return null;
        return toFinite(component.quantity_new) + toFinite(component.quantity_used);
    });

    const savedAnalysis = reactive({
        loading: false,
        status: '',
        message: '',
        error: '',
        sessions: [],
        diff: null,
    });
    const previewValue = (value) => {
        if (value === undefined) return '未設定';
        if (value === null) return 'null';
        const raw = typeof value === 'string' ? value : JSON.stringify(value);
        return raw.length > 64 ? `${raw.slice(0, 61)}...` : raw;
    };
    const diffPayload = (previous, current) => {
        const keys = Array.from(new Set([...Object.keys(previous ?? {}), ...Object.keys(current ?? {})]));
        return keys
            .filter((key) => JSON.stringify(previous?.[key]) !== JSON.stringify(current?.[key]))
            .slice(0, 12)
            .map((key) => `${key}: ${previewValue(previous?.[key])} -> ${previewValue(current?.[key])}`);
    };
    const buildSavedDiff = (session) => {
        if (!session) return null;
        return {
            title: session.title,
            previousVerdict: session.verdict || session.result_payload?.verdict || 'CHECK',
            currentVerdict: analysisReport.value?.verdict ?? 'CHECK',
            changes: diffPayload(session.input_payload ?? {}, currentInputPayload.value),
            previousSummary: session.summary ?? '',
            currentSummary: analysisReport.value?.copySummary ?? analysisReport.value?.summary ?? '',
        };
    };
    const loadSavedAnalysis = async () => {
        savedAnalysis.loading = true;
        savedAnalysis.status = '';
        savedAnalysis.error = '';
        savedAnalysis.message = '';
        savedAnalysis.diff = null;
        try {
            const params = new URLSearchParams({ tool_id: activeToolId.value });
            if (outputSave.projectId) params.set('project_id', outputSave.projectId);
            if (outputSave.componentId) params.set('component_id', outputSave.componentId);
            const data = await apiJson(`/api/analysis-sessions?${params.toString()}`);
            savedAnalysis.sessions = data.data ?? [];
            savedAnalysis.diff = buildSavedDiff(savedAnalysis.sessions[0]);
            savedAnalysis.status = 'success';
            savedAnalysis.message = savedAnalysis.sessions.length
                ? `${savedAnalysis.sessions.length}件の保存済み解析を読み込みました。`
                : '同条件の保存済み解析はありません。';
        } catch (error) {
            savedAnalysis.status = 'error';
            savedAnalysis.error = error?.message || '保存済み解析の取得に失敗しました。';
        } finally {
            savedAnalysis.loading = false;
        }
    };
    const candidateLinksForActiveTool = computed(() => {
        const links = [];
        const componentId = componentImport.component?.id ?? outputSave.componentId;
        if (componentId) {
            links.push({ label: `部品詳細 #${componentId}`, url: `/components/${componentId}` });
        }
        if (componentImport.component) {
            links.push({
                label: `在庫 ${loadedComponentStock.value ?? 0} pcs`,
                url: `/components/${componentImport.component.id}`,
            });
        }
        links.push({
            label: `${activeTool.value?.label ?? activeToolId.value} 候補検索`,
            url: `/components?q=${encodeURIComponent(activeTool.value?.label ?? activeToolId.value)}`,
        });
        return links;
    });
    const designReport = (data) => report({
        ...data,
        candidateLinks: [
            ...candidateLinksForActiveTool.value,
            ...(data.candidateLinks ?? []),
        ],
    });

    const currentInputPayload = computed(() => {
        const quick = quickForms[activeToolId.value];
        if (quick) return { ...quick };
        const payloads = {
            adc,
            'cap-life': cap,
            divider,
            shunt,
            power,
            comparator: comp,
            thermal,
            interface: iface,
        };
        return JSON.parse(JSON.stringify(payloads[activeToolId.value] ?? {}));
    });
    const analysisPayload = computed(() => {
        const reportData = analysisReport.value;
        return {
            tool_id: activeToolId.value,
            title: outputSave.title || `${activeTool.value?.label ?? activeToolId.value} ${reportData?.verdict ?? ''}`.trim(),
            verdict: reportData?.verdict ?? 'CHECK',
            summary: reportData?.copySummary || reportData?.summary || '',
            input_payload: currentInputPayload.value,
            result_payload: reportData,
            candidate_links: reportData?.candidateLinks ?? [],
            project_id: outputSave.projectId ? Number(outputSave.projectId) : null,
            component_id: outputSave.componentId ? Number(outputSave.componentId) : null,
            bom_line_key: outputSave.bomLineKey || null,
        };
    });
    const copyAnalysisSummary = async () => {
        if (!analysisReport.value?.copySummary || !navigator?.clipboard) return;
        await navigator.clipboard.writeText(analysisReport.value.copySummary);
    };
    const saveAnalysisReport = async () => {
        outputSave.saving = true;
        outputSave.status = '';
        outputSave.message = '';
        outputSave.error = '';
        try {
            const token = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') ?? '';
            const response = await fetch('/api/analysis-sessions', {
                method: 'POST',
                headers: {
                    'Accept': 'application/json',
                    'Content-Type': 'application/json',
                    ...(token ? { 'X-CSRF-TOKEN': token } : {}),
                },
                body: JSON.stringify(analysisPayload.value),
            });
            const data = await response.json().catch(() => ({}));
            if (!response.ok) {
                throw new Error(data.message || `保存に失敗しました (${response.status})`);
            }
            outputSave.status = 'success';
            outputSave.message = data.message || '解析セッションを保存しました。';
        } catch (error) {
            outputSave.status = 'error';
            outputSave.error = error?.message || '保存に失敗しました。';
        } finally {
            outputSave.saving = false;
        }
    };
    const duplicateAnalysisTemplate = async () => {
        applyAnalysisTemplate();
        if (!outputSave.title) {
            outputSave.title = selectedTemplate.value?.title ?? '解析テンプレート複製';
        }
        await saveAnalysisReport();
        if (outputSave.status === 'success') {
            templateState.status = 'success';
            templateState.message = outputSave.projectId
                ? 'テンプレートを案件付き解析セッションとして複製保存しました。'
                : 'テンプレートをユーザーの解析セッションとして複製保存しました。';
        }
    };

    const analysisReport = computed(() => {
        if (activeToolId.value === 'adc') {
            const codeCount = Math.pow(2, adc.bits);
            const fullScale = codeCount - 1;
            const lsb = adc.vref / Math.max(codeCount, 1);
            const rawCode = (adc.vin - adc.offset) / Math.max(lsb, 1e-12);
            const lowerHeadroom = adc.vinMin - adc.offset;
            const upperHeadroom = adc.vref - (adc.vinMax - adc.offset);
            const rangeUse = fullScale > 0 ? adcResult.value.code / fullScale : 0;
            const rangeUseMax = fullScale > 0 ? adcResult.value.max_code / fullScale : 0;
            const clipped = adcResult.value.clipped || adcResult.value.range_clipped || adc.vref <= 0;
            const tone = clipped ? 'bad' : (rangeUse < 0.1 || rangeUse > 0.9 ? 'warn' : 'ok');
            const summary = clipped
                ? 'min/max入力のいずれかがADCレンジ外です。Vref、オフセット、前段分圧またはゲインの見直しが必要です。'
                : rangeUse < 0.1
                    ? 'レンジ使用率が低く、分解能を捨てています。前段ゲインまたはVrefを見直す余地があります。'
                    : rangeUse > 0.9
                        ? '上側ヘッドルームが小さく、ばらつきや過渡でクリップしやすい状態です。'
                        : '入力はレンジ内で、コード化係数とヘッドルームを設計値として使えます。';
            return designReport({
                verdict: clipped ? 'FAIL' : (tone === 'warn' ? 'WARN' : 'PASS'),
                tone,
                summary,
                metrics: [
                    { label: 'レンジ使用率', value: `${(rangeUse * 100).toFixed(1)} %` },
                    { label: 'min/typ/max入力', value: `${adc.vinMin} / ${adc.vinTyp} / ${adc.vinMax} V` },
                    { label: 'min/typ/maxコード', value: `${adcResult.value.min_code} / ${adcResult.value.typ_code} / ${adcResult.value.max_code}` },
                    { label: 'maxレンジ使用率', value: `${(rangeUseMax * 100).toFixed(1)} %` },
                    { label: '下側余裕', value: formatNumber(lowerHeadroom, 4, 'V') },
                    { label: '上側余裕', value: formatNumber(upperHeadroom, 4, 'V') },
                    { label: '未丸めコード', value: formatNumber(rawCode, 2) },
                    { label: '理想LSB', value: `${adcResult.value.lsb_mv} mV/code` },
                    { label: '量子化誤差', value: `${adcResult.value.quant_error_mv} mV` },
                    { label: '物理量LSB', value: adcResult.value.physical_lsb },
                    { label: '固定小数点係数', value: `Q${adc.fixedPointBits} ${adcResult.value.fixed_q}` },
                ],
                dominantFactors: ['Vref', 'オフセット', '前段ゲイン/分圧比'],
                warnings: [
                    ...(clipped ? ['クリップ後のコードだけを使うと、異常条件を隠します。'] : []),
                    '理想LSBは Vref / 2^N で算出しています。データシートのコード遷移条件に合わせて丸め方式を確認してください。',
                ],
                nextActions: clipped
                    ? ['最大/最小入力を入れたレンジ設計に戻し、Vrefまたは分圧比を再設定する']
                    : ['センサ最小/最大値でも同じ確認を行い、ファーム定数に丸め誤差を含める', adcResult.value.c_code],
            });
        }

        if (activeToolId.value === 'cap-life') {
            const lifeY = parseNumber(capResult.value.life_y);
            const worstLifeY = parseNumber(capResult.value.worst_life_y);
            const targetMarginY = parseNumber(capResult.value.target_margin_y);
            const voltageStress = cap.V / Math.max(cap.Vr, 1e-12);
            const effectiveTemp = parseNumber(capResult.value.effective_temp_c);
            const tempDelta = cap.T0 - effectiveTemp;
            const tone = worstLifeY < cap.targetLifeY || !capResult.value.derating_ok ? 'bad' : (lifeY < cap.targetLifeY * 1.5 || voltageStress > 0.8 || tempDelta < 20 || targetMarginY < 2 ? 'warn' : 'ok');
            return designReport({
                verdict: tone === 'bad' ? 'FAIL' : (tone === 'warn' ? 'WARN' : 'CHECK'),
                tone: tone === 'ok' ? 'neutral' : tone,
                summary: tone === 'ok'
                    ? '寿命は温度条件上の目安として成立しています。熱抵抗を実条件へ置き換えると判定を確定できます。'
                    : '寿命またはディレーティング余裕が弱いです。温度、定格電圧、部品グレードのいずれかが支配しています。',
                metrics: [
                    { label: '推定寿命', value: `${capResult.value.life_y} 年` },
                    { label: '最悪温度寿命', value: `${capResult.value.worst_life_y} 年` },
                    { label: '目標寿命余裕', value: `${capResult.value.target_margin_y} 年` },
                    { label: '定格温度との差', value: formatNumber(tempDelta, 1, 'degC') },
                    { label: '実効温度', value: `${capResult.value.effective_temp_c} degC` },
                    { label: '最悪実効温度', value: `${capResult.value.worst_temp_c} degC` },
                    { label: '電圧使用率', value: `${(voltageStress * 100).toFixed(1)} %` },
                    { label: 'リプル自己発熱', value: `${capResult.value.self_heat_c} degC` },
                    { label: 'リプル損失', value: `${capResult.value.ripple_loss_w} W` },
                    { label: '電圧derating', value: capResult.value.derating_ok ? 'OK' : 'NG' },
                ],
                dominantFactors: [
                    Math.abs(tempDelta) < 20 ? '動作温度' : '定格寿命',
                    voltageStress > 0.8 ? '定格電圧余裕' : '温度係数',
                ],
                warnings: [
                    ...(voltageStress > 1 ? ['動作電圧が定格電圧を超えています。'] : []),
                    ...(!capResult.value.derating_ok ? [`${cap.voltageDeratingPct}%ディレーティングを超えています。`] : []),
                    ...(worstLifeY < cap.targetLifeY ? ['最悪周囲温度条件では目標寿命を下回ります。'] : []),
                    '電圧条件で寿命を延ばす係数は使わず、定格超過とディレーティングだけを判定します。',
                    'リプル自己発熱は暫定10degC/Wのため、部品/実装条件で置き換えてください。',
                ],
                missingConditions: ['コンデンサケース熱抵抗または実測温度', 'リプル電流の周波数条件'],
                nextActions: tone === 'ok'
                    ? ['周囲温度の最悪条件とリプル電流条件で再計算する']
                    : ['105/125degC品への変更、定格電圧の引き上げ、発熱源からの配置分離を検討する'],
            });
        }

        if (activeToolId.value === 'divider') {
            if (divider.mode === 'voltage') {
                const totalR = divider.r1 + divider.r2;
                const dividerCurrent = divider.vin / Math.max(totalR, 1e-12);
                const candidates = String(divider.pullupCandidates).split(/[\s,;]+/).filter(Boolean).slice(0, 4);
                const tone = dividerCurrent > 0.001 || dividerCurrent < 0.00001 ? 'warn' : 'ok';
                return designReport({
                    verdict: tone === 'warn' ? 'WARN' : 'PASS',
                    tone,
                    summary: tone === 'ok'
                        ? '分圧比と自己消費は目安範囲です。負荷インピーダンスを入れると成立性を確定できます。'
                        : '分圧電流が極端です。消費電流、入力バイアス、ADCサンプル容量の影響を確認してください。',
                    metrics: [
                        { label: '分圧比', value: `${dividerResult.value.ratio} %` },
                        { label: '分圧電流', value: formatNumber(dividerCurrent * 1000, 3, 'mA') },
                        { label: '合成抵抗', value: formatNumber(totalR, 0, 'ohm') },
                        { label: '候補抵抗', value: candidates.join(', ') || '未入力' },
                        { label: '温度範囲', value: `${divider.tempMin} - ${divider.tempMax} degC` },
                    ],
                    dominantFactors: ['R1/R2比', '合成抵抗', '後段入力インピーダンス'],
                    warnings: ['負荷インピーダンス未入力のため、後段での分圧ずれは未判定です。'],
                    nextActions: ['ADC入力ならサンプル時間と入力インピーダンス条件を確認する'],
                });
            }
            const pullups = String(divider.pullupCandidates).split(/[\s,;]+/).filter(Boolean).map((value) => parseNumber(value)).filter((value) => value > 0).slice(0, 4);
            const fullScale = 2 ** divider.adcBits - 1;
            const resistanceAtTemp = (tempC) => {
                const tk = tempC + 273.15;
                const t0k = divider.T0 + 273.15;
                return divider.R0 * Math.exp(divider.B * ((1 / Math.max(tk, 1e-12)) - (1 / Math.max(t0k, 1e-12))));
            };
            const codeFor = (resistance, pullup) => {
                const voltage = divider.adcVref * resistance / Math.max(resistance + pullup, 1e-12);
                return Math.round(voltage / Math.max(divider.adcVref, 1e-12) * fullScale);
            };
            const adcCodes = pullups.map((pullup) => {
                const code = codeFor(divider.Rmeas, pullup);
                return `${pullup}ohm:${code}`;
            });
            const step = Math.max(Math.abs(toFinite(divider.tempStep, 25)), 1);
            const sweepTemps = [];
            const minTemp = Math.min(toFinite(divider.tempMin), toFinite(divider.tempMax));
            const maxTemp = Math.max(toFinite(divider.tempMin), toFinite(divider.tempMax));
            for (let temp = minTemp; temp <= maxTemp + 1e-9 && sweepTemps.length < 12; temp += step) {
                sweepTemps.push(temp);
            }
            if (!sweepTemps.includes(maxTemp)) sweepTemps.push(maxTemp);
            const primaryPullup = pullups[0] ?? divider.R0;
            const sweepRows = sweepTemps.map((temp) => {
                const resistance = resistanceAtTemp(temp);
                return `${temp}C:${Math.round(resistance)}ohm/${codeFor(resistance, primaryPullup)}`;
            });
            const minCode = codeFor(resistanceAtTemp(minTemp), primaryPullup);
            const maxCode = codeFor(resistanceAtTemp(maxTemp), primaryPullup);
            const tempSpan = divider.tempMax - divider.tempMin;
            const codeSpan = Math.abs(maxCode - minCode);
            const linearCoeff = codeSpan > 0 ? tempSpan / codeSpan : 0;
            return designReport({
                verdict: 'CHECK',
                tone: 'warn',
                summary: 'B定数式による温度換算と温度範囲sweepです。自己発熱と固定抵抗公差を入れると設計判定へ進めます。',
                metrics: [
                    { label: '換算温度', value: `${dividerResult.value.temp_c} degC` },
                    { label: 'B定数', value: `${divider.B}` },
                    { label: '温度sweep', value: `${divider.tempMin} - ${divider.tempMax} / ${divider.tempStep} degC` },
                    { label: 'ADCコード表', value: adcCodes.join(' / ') || '候補なし' },
                    { label: '温度別コード', value: sweepRows.join(' / ') || '未計算' },
                    { label: '線形化係数', value: `${linearCoeff.toFixed(5)} degC/code` },
                ],
                dominantFactors: ['B定数', '測定抵抗', '自己発熱'],
                warnings: ['B定数単独モデルです。高精度用途ではSteinhart-Hart係数または実測テーブルへ置き換えてください。'],
                missingConditions: ['自己発熱条件', '固定抵抗公差', 'サーミスタ許容差'],
                nextActions: ['min/typ/max抵抗表またはSteinhart-Hart係数で温度範囲の誤差を見る'],
            });
        }

        if (activeToolId.value === 'shunt') {
            const lossMw = parseNumber(shuntResult.value.P_mW);
            const vshuntMv = parseNumber(shuntResult.value.Vshunt_mv);
            const powerMarginMw = parseNumber(shuntResult.value.power_margin_mw);
            const ratingMw = Math.max(shunt.powerRating * 1000, 1e-12);
            const offsetPct = parseNumber(shuntResult.value.offset_error_pct);
            const adcLsbA = parseNumber(shuntResult.value.adc_lsb_a);
            const measuredCurrent = Math.max(Math.abs(parseNumber(shuntResult.value.measured_current_a)), 1e-12);
            const adcLsbPct = adcLsbA / measuredCurrent * 100;
            const voutHighMargin = parseNumber(shuntResult.value.vout_margin_high_v);
            const voutLowMargin = parseNumber(shuntResult.value.vout_margin_low_v);
            const invalidInput = shunt.Rs <= 0 || shunt.gain <= 0 || shunt.adcBits <= 0 || shunt.adcVref <= 0;
            const tcrDriftPct = Math.abs(toFinite(shunt.tcrPpm)) * 100 / 1_000_000;
            const tone = invalidInput || powerMarginMw < 0 || vshuntMv > 150 || voutHighMargin < 0 || voutLowMargin < 0 || offsetPct > 5
                ? 'bad'
                : (powerMarginMw / ratingMw < 0.2 || lossMw > 250 || vshuntMv > 75 || offsetPct > 1 || adcLsbPct > 1 ? 'warn' : 'ok');
            return designReport({
                verdict: tone === 'bad' ? 'FAIL' : (tone === 'warn' ? 'WARN' : 'PASS'),
                tone,
                summary: tone === 'ok'
                    ? 'シャント損失と検出電圧は低めで、次はアンプ入力範囲とADCレンジの確認に進めます。'
                    : 'シャント損失または電圧降下が大きく、発熱・電圧ロス・アンプ飽和のリスクがあります。',
                metrics: [
                    { label: 'シャント電圧', value: `${vshuntMv.toFixed(3)} mV` },
                    { label: 'シャント損失', value: `${lossMw.toFixed(3)} mW` },
                    { label: 'アンプゲイン', value: `${shunt.gain}` },
                    { label: 'Rs定格余裕', value: `${shuntResult.value.power_margin_mw} mW` },
                    { label: 'TCR', value: `${shunt.tcrPpm} ppm/degC` },
                    { label: '100degC TCR目安', value: `${tcrDriftPct.toFixed(3)} %` },
                    { label: '入力換算オフセット誤差', value: `${shuntResult.value.offset_error_a} A` },
                    { label: 'オフセット誤差率', value: `${shuntResult.value.offset_error_pct} %` },
                    { label: 'ADC電流LSB', value: `${shuntResult.value.adc_lsb_a} A` },
                    { label: 'Vout下側余裕', value: `${shuntResult.value.vout_margin_low_v} V` },
                    { label: 'Vout上側余裕', value: `${shuntResult.value.vout_margin_high_v} V` },
                    { label: '出力オフセット', value: `${shuntResult.value.offset_output_mv} mV` },
                    { label: 'ADC 1LSB', value: `${shuntResult.value.adc_lsb_mv} mV` },
                ],
                dominantFactors: ['Rs', '最大電流', 'アンプゲイン'],
                warnings: [
                    ...(shunt.Rs <= 0 ? ['シャント抵抗は0Ωより大きい値が必要です。'] : []),
                    ...(shunt.gain <= 0 ? ['アンプゲインが0以下です。'] : []),
                    ...(shunt.adcVref <= 0 ? ['ADC Vrefが0以下です。'] : []),
                    ...(parseNumber(shuntResult.value.power_margin_mw) < 0 ? ['シャント抵抗の電力定格を超えています。'] : []),
                    ...(powerMarginMw >= 0 && powerMarginMw / ratingMw < 0.2 ? ['シャント抵抗の電力定格余裕が20%未満です。'] : []),
                    ...(voutHighMargin < 0 ? ['オフセット込みのアンプ出力がADC上限を超えています。'] : []),
                    ...(voutLowMargin < 0 ? ['オフセット込みのアンプ出力が0V未満です。'] : []),
                    ...(offsetPct > 1 ? ['入力オフセット誤差が測定電流に対して大きいです。'] : []),
                    ...(adcLsbPct > 1 ? ['ADC量子化幅が測定電流に対して大きいです。'] : []),
                    ...(tcrDriftPct > 0.5 ? ['TCR由来の温度ドリフトが0.5%を超える可能性があります。'] : []),
                    'ケルビン接続で配線抵抗を検出誤差から外してください。',
                ],
                nextActions: tone === 'ok'
                    ? ['最大電流時のVoutとADCフルスケールを照合する']
                    : ['Rsを下げる、ケルビン接続/電力定格を確認する、ゲイン側で分解能を稼ぐ'],
            });
        }

        if (activeToolId.value === 'power') {
            const supply = Math.max(power.supply_w * power.efficiencyPct / 100, 1e-12);
            const total = parseNumber(powerResult.value.totalW);
            const margin = parseNumber(powerResult.value.margin);
            const worstMargin = parseNumber(powerResult.value.worstMargin);
            const marginPct = margin / supply * 100;
            const largest = power.loads.reduce((max, load) => {
                const watts = toFinite(load.mA) * toFinite(load.V) / 1000;
                return watts > max.watts ? { label: load.label || '未命名負荷', watts } : max;
            }, { label: '', watts: -Infinity });
            const railWarnings = powerResult.value.railMargins.flatMap((rail) => [
                ...(rail.dropoutMargin !== null && rail.dropoutMargin <= 0 ? [`${rail.name} は親レールとの差から見たdropout余裕がありません。`] : []),
                ...(rail.overloaded ? [`${rail.name} は子レール込み負荷 ${formatNumber(rail.rolledLoadW, 3, 'W')} がレール容量 ${formatNumber(rail.capacityW, 3, 'W')} を超えています。`] : []),
            ]);
            const unassignedWarnings = powerResult.value.unassignedLoads.map((load) => `${load.label} は一致する電圧レールがありません。`);
            const hasRailOverload = powerResult.value.railMargins.some((rail) => rail.overloaded);
            const unresolvedInrush = toFinite(power.inrushA) > 0;
            const tone = margin < 0 || worstMargin < 0 || hasRailOverload ? 'bad' : (marginPct < 20 || worstMargin / supply * 100 < 10 || unassignedWarnings.length ? 'warn' : 'ok');
            return designReport({
                verdict: tone === 'bad' ? 'FAIL' : (tone === 'warn' ? 'WARN' : (unresolvedInrush ? 'CHECK' : 'PASS')),
                tone,
                summary: tone === 'ok'
                    ? '電源容量は合計負荷に対して20%以上の余裕があります。突入条件を時間幅付きで入れると起動時判定まで確定できます。'
                    : '電源余裕が不足または薄いです。最大負荷、起動電流、温度ディレーティングで再評価してください。',
                metrics: [
                    { label: '消費合計', value: formatNumber(total, 3, 'W') },
                    { label: '上流換算負荷', value: `${powerResult.value.inputEquivalentW} W` },
                    { label: '未割当負荷', value: `${powerResult.value.unassignedW} W` },
                    { label: '余裕', value: `${formatNumber(margin, 3, 'W')} / ${marginPct.toFixed(1)} %` },
                    { label: '最悪余裕', value: formatNumber(worstMargin, 3, 'W') },
                    { label: '最大負荷シナリオ', value: `${powerResult.value.maxScenarioW} W` },
                    { label: '効率後供給', value: formatNumber(supply, 3, 'W') },
                    { label: '最大負荷', value: `${largest.label} ${formatNumber(largest.watts, 3, 'W')}` },
                    { label: 'レール数', value: `${powerResult.value.railMargins.length}` },
                    { label: '突入/最大係数', value: `${power.inrushA} A / x${power.maxLoadFactor}` },
                ],
                dominantFactors: largest.label ? [largest.label, '電源定格', '起動時ピーク'] : ['電源定格'],
                warnings: [
                    ...railWarnings,
                    ...unassignedWarnings,
                    ...(unresolvedInrush ? ['突入電流はA単位の入力のみなので、電力余裕へは加算していません。'] : []),
                ],
                missingConditions: unresolvedInrush ? ['突入電流の時間幅', '突入電流が流れるレール', '電源のピーク電流定格'] : [],
                nextActions: tone === 'ok'
                    ? ['各負荷のmax電流と起動シーケンスでも同じ余裕を確認する']
                    : ['最大負荷を分離する、電源定格を上げる、起動順をずらす'],
            });
        }

        if (activeToolId.value === 'comparator') {
            const high = parseNumber(compResult.value.Vth_rising);
            const low = parseNumber(compResult.value.Vth_falling);
            const hyst = parseNumber(compResult.value.hysteresis);
            const noiseMargin = parseNumber(compResult.value.noise_margin);
            const outOfRange = high < 0 || low < 0 || high > comp.Vcc || low > comp.Vcc;
            const tone = outOfRange ? 'bad' : (hyst <= 0 || noiseMargin < 0 ? 'warn' : 'ok');
            return designReport({
                verdict: tone === 'bad' ? 'FAIL' : (tone === 'warn' ? 'WARN' : 'CHECK'),
                tone,
                summary: outOfRange
                    ? 'しきい値が電源範囲外です。抵抗ネットワークか基準電圧の式を見直してください。'
                    : hyst <= 0
                        ? 'ヒステリシスなしです。ノイズ源がある入力ではチャタリング余裕が判定できません。'
                        : noiseMargin < 0
                            ? 'ヒステリシス幅に対してノイズ・公差余裕が不足しています。帰還抵抗比または基準条件の見直し対象です。'
                        : 'R2/R3の基準帰還としてしきい値を見積もりました。実出力電圧と入力バイアスを入れると確定できます。',
                metrics: [
                    { label: 'Low -> High', value: formatNumber(high, 4, 'V') },
                    { label: 'High -> Low', value: formatNumber(low, 4, 'V') },
                    { label: 'ヒステリシス', value: formatNumber(hyst, 4, 'V') },
                    { label: 'しきい値公差帯', value: `${compResult.value.tolerance_band} V` },
                    { label: 'ノイズ余裕', value: `${compResult.value.noise_margin} V` },
                    { label: 'モデル', value: compResult.value.topology },
                    { label: 'R1入力直列', value: formatNumber(compResult.value.input_series_ohm, 0, 'ohm') },
                    { label: '抵抗候補数', value: `${compResult.value.candidates.length}` },
                ],
                dominantFactors: ['Vref', 'R1/R2/R3比', '出力振幅'],
                warnings: [
                    ...(parseNumber(compResult.value.noise_margin) < 0 ? ['ノイズ振幅と公差を含めるとヒステリシス余裕が不足します。'] : []),
                    '入力バイアス、出力High/Low実電圧はデータシート条件へ置き換えてください。',
                ],
                missingConditions: ['出力High/Low実電圧', '入力バイアス電流', '比較器入力構成の確定'],
                nextActions: tone === 'ok'
                    ? ['センサ誤差とノイズ振幅をヒステリシス幅に重ねて確認する']
                    : ['R3とVrefを再設定し、しきい値を0-Vcc範囲内へ戻す'],
            });
        }

        if (activeToolId.value === 'thermal') {
            const tj = parseNumber(thermalResult.value.Tjunction);
            const worstTj = parseNumber(thermalResult.value.worstTjunction);
            const deratingMargin = parseNumber(thermalResult.value.deratingMargin);
            const margin = thermal.TjLimit - tj;
            const maxNode = thermal.nodes.reduce((max, node) => toFinite(node.Rth) > max.Rth ? node : max, { label: '', Rth: -Infinity });
            const worstMargin = thermal.TjLimit - worstTj;
            const tone = margin < 0 || worstMargin < 0 || deratingMargin < 0 ? 'bad' : (margin < 20 || worstMargin < 10 || deratingMargin < 10 ? 'warn' : 'ok');
            return designReport({
                verdict: tone === 'bad' ? 'FAIL' : (tone === 'warn' ? 'WARN' : 'PASS'),
                tone,
                summary: tone === 'ok'
                    ? '通常Tj、最悪Tj、ディレーティング後の余裕が残っています。支配熱抵抗を設計根拠として残せます。'
                    : '熱余裕が不足または薄いです。支配熱抵抗か消費電力を直接下げる必要があります。',
                metrics: [
                    { label: 'Tj余裕', value: formatNumber(margin, 1, 'degC') },
                    { label: '最悪Tj余裕', value: formatNumber(worstMargin, 1, 'degC') },
                    { label: '最悪Tj', value: `${thermalResult.value.worstTjunction} degC` },
                    { label: 'derating余裕', value: `${thermalResult.value.deratingMargin} degC` },
                    { label: '合計熱抵抗', value: formatNumber(thermalResult.value.totalRth, 2, 'degC/W') },
                    { label: '支配熱抵抗', value: `${maxNode.label} ${formatNumber(maxNode.Rth, 2, 'degC/W')}` },
                    { label: '放熱候補', value: thermalResult.value.heatsinkCandidates.map((item) => `${item.rth}:${item.tj}C`).join(' / ') || '未入力' },
                ],
                dominantFactors: [maxNode.label || '熱抵抗チェーン', '消費電力', '周囲温度'],
                warnings: [
                    ...(worstTj > thermal.TjLimit ? ['最悪発熱シナリオでTj上限を超えます。'] : []),
                    ...(deratingMargin < 0 ? ['ディレーティング後の温度余裕が不足しています。'] : []),
                    '基板銅箔、風速、隣接発熱体は未モデル化です。',
                ],
                nextActions: tone === 'ok'
                    ? ['最悪周囲温度と最大消費電力で再計算する']
                    : ['支配熱抵抗を下げる、放熱面積/風量を増やす、消費電力を下げる'],
            });
        }

        if (activeToolId.value === 'interface') {
            const high = parseNumber(ifaceResult.value.high_margin);
            const low = parseNumber(ifaceResult.value.low_margin);
            const minMargin = Math.min(high, low);
            const riseExceeded = parseNumber(ifaceResult.value.i2c_rise_ns) > iface.i2cRiseNsLimit;
            const sinkExceeded = parseNumber(ifaceResult.value.i2c_sink_ma) > iface.i2cSinkMaLimit;
            const uartErrorAbs = Math.abs(parseNumber(ifaceResult.value.uart_error_pct));
            const tone = minMargin <= 0 || sinkExceeded || uartErrorAbs > 5 ? 'bad' : (minMargin < 0.2 || riseExceeded || uartErrorAbs > 2 ? 'warn' : 'ok');
            return designReport({
                verdict: tone === 'bad' ? 'FAIL' : (tone === 'warn' ? 'WARN' : 'PASS'),
                tone,
                summary: tone === 'ok'
                    ? 'H/L両側の電圧余裕があります。温度・電源ばらつきを重ねると設計判定を確定できます。'
                    : 'ロジックレベル余裕が不足または薄いです。レベル変換、プルアップ、電源条件の見直し対象です。',
                metrics: [
                    { label: 'H余裕', value: formatNumber(high, 3, 'V') },
                    { label: 'L余裕', value: formatNumber(low, 3, 'V') },
                    { label: '最小余裕', value: formatNumber(minMargin, 3, 'V') },
                    { label: 'UART誤差', value: `${ifaceResult.value.uart_error_pct} %` },
                    { label: 'I2C立上り', value: `${ifaceResult.value.i2c_rise_ns} ns` },
                    { label: 'Pull-up候補', value: `${ifaceResult.value.pullup_candidate_ohm} ohm以下` },
                    { label: 'I2C Lowシンク', value: `${ifaceResult.value.i2c_sink_ma} mA` },
                    { label: 'Pull-up下限', value: `${ifaceResult.value.pullup_min_ohm} ohm以上` },
                    { label: '電源/温度条件', value: `${iface.Vcc_out}/${iface.Vcc_in} V, ${iface.tempMin}-${iface.tempMax} degC` },
                ],
                dominantFactors: high <= low ? ['VOH/VIH', 'pull-up', '温度'] : ['VOL/VIL', 'pull-up', '温度'],
                warnings: [
                    ...(Math.abs(parseNumber(ifaceResult.value.uart_error_pct)) > 2 ? ['UARTボーレート誤差が2%を超えています。'] : []),
                    ...(parseNumber(ifaceResult.value.i2c_rise_ns) > iface.i2cRiseNsLimit ? ['I2C立上り時間が上限を超えています。'] : []),
                    ...(sinkExceeded ? ['I2C Low時のシンク電流が定格を超えています。'] : []),
                    '出力電流条件と電源min/maxはデータシート条件で確認してください。',
                ],
                nextActions: tone === 'ok'
                    ? ['データシートのmin/max条件へ置き換えて余裕を再計算する']
                    : ['レベルシフタ追加、プルアップ電圧変更、同一電源ドメイン化を検討する'],
            });
        }

        const qt = quickTool.value;
        if (!qt) {
            return designReport({
                verdict: 'CHECK',
                tone: 'neutral',
                summary: 'このツールの判定モデルが未定義です。',
                warnings: ['計算値だけでは設計判断として不足しています。'],
                missingConditions: ['判定条件', 'margin', '支配要因'],
                nextActions: ['判定条件、margin、支配要因を追加する'],
            });
        }

        const quickTone = qt.tone === 'bad' ? 'bad' : (qt.tone === 'warn' ? 'warn' : (qt.tone === 'check' ? 'neutral' : 'ok'));
        const quickVerdict = qt.tone === 'check' ? 'CHECK' : (quickTone === 'bad' ? 'FAIL' : (quickTone === 'warn' ? 'WARN' : 'PASS'));
        const genericActions = {
            tolerance: ['歩留まりが悪い場合は平均のセンタリング、公差ランク、部品点数を見直す'],
            bode: ['評価周波数で必要なゲイン/位相余裕を決め、R/C公差込みで再計算する'],
            ovp: ['保護素子の連続/パルス定格と温度ディレーティングを入力して合否化する'],
            tvs: ['TVSのピークパルス電力定格、波形、繰り返し条件と照合する'],
            fuse: ['周囲温度、突入電流、I2t条件を入れて定格選定を確定する'],
            polyfuse: ['保持電流の温度ディレーティングとトリップ時間をデータシートで確認する'],
            protection: ['TVS熱インピーダンス、ヒューズ時間電流特性、eFuse制限順序を同じ故障波形で確認する'],
            'logic-ic': ['VIH/VIL、出力電流、速度、電源範囲、ピン配置を候補型番のデータシートで照合する'],
            connector: ['電流定格、嵌合方向、mating face/solder sideの図を部品候補へ紐づける'],
            cable: ['量産図面には端A/端Bの視点とピン1方向を併記する'],
            jumper: ['量産初期値、デバッグ用、未実装の目的をBOM注記へ落とす'],
            startup: ['各レールのPG信号、リセット解除条件、最大立上り時間を追加する'],
        };
        const capability = {
            tolerance: ['最悪/RSS', '歩留まり', '支配公差'],
            bode: ['fc', '指定周波数ゲイン', '位相'],
            ovp: ['クランプ電流', '損失', '不足定格'],
            tvs: ['ピーク電流', 'ピーク電力', 'エネルギー'],
            fuse: ['負荷率', 'ディレーティング後余裕', '過電流保護'],
            polyfuse: ['保持余裕', '発熱', 'トリップ比'],
            protection: ['TVS余裕', '遮断I2t', 'eFuse/逆接保護'],
            'logic-ic': ['候補型番', 'Vcc範囲', '入出力形式'],
            connector: ['電流目安', '用途別確認観点', '参照不足条件'],
            cable: ['結線判定', '端子数差', '図面化注意'],
            jumper: ['状態数', '量産実装数', '未定義目的'],
            startup: ['依存関係', 'PG/RESET', 'バックパワー'],
        };
        return designReport({
            verdict: quickVerdict,
            tone: quickTone,
            summary: qt.summary || `${qt.title} の計算値を、設計上の確認項目へ展開しています。`,
            metrics: qt.rows.map((row) => ({ label: row[0], value: row[1] })),
            dominantFactors: qt.dominantFactors ?? capability[qt.model] ?? ['入力条件'],
            warnings: qt.warnings ?? [],
            nextActions: qt.nextActions ?? genericActions[qt.model] ?? ['部品定格と最悪条件を追加して判定する'],
            missingConditions: qt.missingConditions ?? [],
            margin: qt.margin ?? null,
            assumptions: activeDiagram.value?.assumptions ?? [],
            copySummary: `${quickVerdict}: ${qt.title}。${qt.summary || '計算値を設計確認項目へ展開しています。'} ${qt.missingConditions?.length ? `不足条件: ${qt.missingConditions.join('、')}。` : ''}`,
            candidateLinks: qt.candidateLinks ?? [],
        });
    });

    return {
        activeToolId, tools, activeTool, hubBands,
        adc, adcResult,
        cap, capResult,
        divider, dividerResult,
        shunt, shuntResult,
        power, powerResult, addLoad, removeLoad,
        comp, compResult,
        thermal, thermalResult, thermalReferences, addNode, removeNode,
        iface, ifaceResult,
        quickForms, quickTool, analysisReport,
        outputSave, analysisPayload, copyAnalysisSummary, saveAnalysisReport,
        advancedInputGroups,
        analysisTemplates, templateState, selectedTemplate, applyAnalysisTemplate, duplicateAnalysisTemplate,
        componentImport, loadComponentContext, loadedComponentName, loadedComponentStock,
        savedAnalysis, loadSavedAnalysis,
        activeDiagram, diagramFocus, focusDiagram, clearDiagramFocus, isDiagramFocused, diagramItemClass,
        parseNumber, setNumericInput,
    };
}
