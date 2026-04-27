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
        { id: 'fuse',       label: 'ヒューズ寿命',     desc: '定格電流、負荷電流、周囲温度からヒューズ選定の余裕を確認します。' },
        { id: 'polyfuse',   label: 'ポリスイッチ',     desc: '保持電流・抵抗・負荷電流から発熱と保持余裕を確認します。' },
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

    const toFinite = (value, fallback = 0) => {
        const number = Number(value);
        return Number.isFinite(number) ? number : fallback;
    };
    const formatNumber = (value, digits = 3, unit = '') => {
        const number = Number(value);
        if (!Number.isFinite(number)) return `--${unit ? ` ${unit}` : ''}`;
        return `${number.toFixed(digits)}${unit ? ` ${unit}` : ''}`;
    };
    const parseNumber = (value, fallback = 0) => toFinite(String(value).replace(/[^0-9eE+\-.]/g, ''), fallback);
    const report = ({ verdict, tone, summary, metrics = [], dominantFactors = [], warnings = [], nextActions = [] }) => ({
        verdict,
        tone,
        summary,
        metrics,
        dominantFactors,
        warnings,
        nextActions,
    });
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
        tolerance: { nominal: 1000, tolerancePct: 1, count: 2, lsl: 1980, usl: 2020, mean: 2000, sigma: 5 },
        bode: { type: 'lowpass', r: 10000, c: 0.00000001, freq: 1000 },
        ovp: { vinMax: 24, vClamp: 5.6, seriesR: 1000, loadCurrent: 0.002 },
        tvs: { surgeV: 1000, lineImpedance: 42, clampV: 33, pulseMs: 1 },
        fuse: { ratedCurrent: 2, loadCurrent: 1.2, ambient: 50, deratingPct: 25 },
        polyfuse: { holdCurrent: 0.75, tripCurrent: 1.5, loadCurrent: 0.5, resistance: 0.4, ambient: 40 },
        connector: { pins: 10, currentPerPin: 1, environment: 'board-to-wire' },
        cable: { endA: '1,2,3,4', endB: '1,2,3,4' },
        jumper: { entries: 'JP1,0Ω,debug,未実装\nR105,0Ω,variant,実装\nJP_BOOT,ジャンパ,boot,切替' },
        startup: { template: 'pmic-mcu', rails: 'VIN,,10\n3V3,VIN,5\n1V8,3V3,3\nRESET,3V3,20' },
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
                    { key: 'code', label: 'code', desc: `現在 ${adcResult.value.code}` },
                ],
                blocks: [
                    { key: 'vin', label: 'Vin', sub: `${adc.vin} V` },
                    { key: 'offset', label: 'offset補正', sub: `${adc.offset} V` },
                    { key: 'adc', label: `${adc.bits}bit ADC`, sub: `Vref ${adc.vref} V` },
                    { key: 'code', label: 'digital code', sub: adcResult.value.hex },
                ],
                assumptions: ['入力源インピーダンス、サンプル時間、ADC入力容量は別途確認します。'],
            };
        }

        if (activeToolId.value === 'cap-life') {
            return {
                type: 'flow',
                title: '電解コンデンサのストレス要因',
                subtitle: '定格寿命に対し、温度と電圧ディレーティングが寿命を支配します。',
                formula: 'life = L0 * 2^((T0 - T)/10) * (Vr / V)^3',
                parts: [
                    { key: 'L0', label: 'L0', desc: '定格寿命' },
                    { key: 'T', label: 'T', desc: '動作温度' },
                    { key: 'V', label: 'V', desc: '動作電圧' },
                    { key: 'life', label: 'life', desc: `${capResult.value.life_y} 年` },
                ],
                blocks: [
                    { key: 'L0', label: '定格寿命', sub: `${cap.L0} h @ ${cap.T0} degC` },
                    { key: 'T', label: '温度ストレス', sub: `${cap.T} degC` },
                    { key: 'V', label: '電圧ストレス', sub: `${cap.V} / ${cap.Vr} V` },
                    { key: 'life', label: '推定寿命', sub: `${capResult.value.life_y} 年` },
                ],
                assumptions: ['リプル電流による自己発熱は未入力です。'],
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
                        { key: 'temp', label: 'Temp', desc: `${dividerResult.value.temp_c} degC` },
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
                subtitle: 'R1は入力側、R2は基準側、R3は出力から+INへ戻る帰還抵抗です。',
                formula: comp.R3 > 0 ? 'Vth = f(Vref, Vout, R1, R3)' : 'R3=0 のため Vth = Vref',
                parts: [
                    { key: 'Vcc', label: 'Vcc', desc: `${comp.Vcc} V` },
                    { key: 'Vref', label: 'Vref', desc: `${comp.Vref} V` },
                    { key: 'R1', label: 'R1 入力抵抗', desc: `${comp.R1} ohm` },
                    { key: 'R2', label: 'R2 基準側抵抗', desc: `${comp.R2} ohm` },
                    { key: 'R3', label: 'R3 帰還抵抗', desc: comp.R3 > 0 ? `${comp.R3} ohm` : 'なし' },
                    { key: 'out', label: 'OUT', desc: `High ${comp.Vcc} V想定` },
                ],
                assumptions: ['入力オフセット、出力High/Low実電圧、入力バイアスは未評価です。'],
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

        if (activeToolId.value === 'connector') {
            return {
                type: 'connector',
                title: 'コネクタの視点と電流配分',
                subtitle: 'ピン1、嵌合面、はんだ面、1pin電流を同時に確認します。',
                formula: 'Itotal = pins * currentPerPin',
                parts: [
                    { key: 'environment', label: '用途', desc: quick.environment },
                    { key: 'pins', label: 'pins', desc: `${quick.pins}` },
                    { key: 'currentPerPin', label: 'I/pin', desc: `${quick.currentPerPin} A` },
                ],
                assumptions: ['温度上昇条件、隣接ピン同時通電、嵌合方向は部品図面で確認します。'],
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
                subtitle: '親レール、子レール、RESETの解除順を依存関係として見ます。',
                formula: 'rail,parent,rise_ms/reset_ms',
                parts: [
                    { key: 'template', label: 'template', desc: quick.template },
                    { key: 'rails', label: 'rails', desc: 'rail,parent,time' },
                    { key: 'reset', label: 'RESET', desc: '解除条件' },
                ],
                assumptions: ['PG信号、部分給電、逆流経路は次段で追加します。'],
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
            const worst = nominal * tol * count;
            const rss = nominal * tol * Math.sqrt(count);
            const sigma = Math.max(toFinite(f.sigma), 1e-12);
            const yieldRate = (normalCdf((toFinite(f.usl) - toFinite(f.mean)) / sigma) - normalCdf((toFinite(f.lsl) - toFinite(f.mean)) / sigma)) * 100;
            return {
                title: '誤差/歩留まり解析',
                model: 'tolerance',
                fields: [
                    { key: 'nominal', label: '公称値', type: 'number', diagramKey: 'nominal' },
                    { key: 'tolerancePct', label: '公差(%)', type: 'number', diagramKey: 'tolerancePct' },
                    { key: 'count', label: '同一公差部品数', type: 'number', diagramKey: 'tolerancePct' },
                    { key: 'lsl', label: '下限 LSL', type: 'number', diagramKey: 'yield' },
                    { key: 'usl', label: '上限 USL', type: 'number', diagramKey: 'yield' },
                    { key: 'mean', label: '平均', type: 'number', diagramKey: 'sigma' },
                    { key: 'sigma', label: 'σ', type: 'number', diagramKey: 'sigma' },
                ],
                rows: [
                    ['最悪値幅', `±${worst.toFixed(4)}`],
                    ['RSS幅', `±${rss.toFixed(4)}`],
                    ['推定歩留まり', `${Math.max(0, Math.min(100, yieldRate)).toFixed(3)} %`],
                ],
                tone: yieldRate >= 99 ? 'ok' : yieldRate >= 95 ? 'warn' : 'bad',
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
            return {
                title: '周波数応答/ボード線図解析',
                model: 'bode',
                fields: [
                    { key: 'type', label: '方式', type: 'select', options: [['lowpass', 'RC Low-pass'], ['highpass', 'RC High-pass']], diagramKey: 'out' },
                    { key: 'r', label: 'R フィルタ抵抗(Ω)', type: 'number', diagramKey: 'r' },
                    { key: 'c', label: 'C フィルタ容量(F)', type: 'number', diagramKey: 'c' },
                    { key: 'freq', label: '評価周波数(Hz)', type: 'number', diagramKey: 'freq' },
                ],
                rows: [
                    ['fc', `${fc.toFixed(3)} Hz`],
                    ['ゲイン', `${(20 * Math.log10(gain)).toFixed(2)} dB`],
                    ['位相', `${phase.toFixed(2)} deg`],
                ],
                tone: Math.abs(20 * Math.log10(gain)) < 3 ? 'ok' : 'warn',
            };
        }

        if (activeToolId.value === 'ovp') {
            const current = Math.max((toFinite(f.vinMax) - toFinite(f.vClamp)) / Math.max(toFinite(f.seriesR), 1e-12) - toFinite(f.loadCurrent), 0);
            return {
                title: '過電圧保護回路設計',
                model: 'ovp',
                fields: [
                    { key: 'vinMax', label: 'Vin 最大入力電圧(V)', type: 'number', diagramKey: 'vinMax' },
                    { key: 'vClamp', label: 'Vz クランプ電圧(V)', type: 'number', diagramKey: 'vClamp' },
                    { key: 'seriesR', label: 'Rser 直列抵抗(Ω)', type: 'number', diagramKey: 'seriesR' },
                    { key: 'loadCurrent', label: 'Iload 負荷電流(A)', type: 'number', diagramKey: 'loadCurrent' },
                ],
                rows: [['保護素子電流', `${current.toFixed(4)} A`], ['保護素子損失', `${(current * toFinite(f.vClamp)).toFixed(4)} W`]],
                tone: current * toFinite(f.vClamp) < 0.5 ? 'ok' : 'warn',
            };
        }

        if (activeToolId.value === 'tvs') {
            const current = Math.max((toFinite(f.surgeV) - toFinite(f.clampV)) / Math.max(toFinite(f.lineImpedance), 1e-12), 0);
            const power = current * toFinite(f.clampV);
            return {
                title: 'TVS保護回路設計',
                model: 'tvs',
                fields: [
                    { key: 'surgeV', label: 'Vsurge サージ電圧(V)', type: 'number', diagramKey: 'surgeV' },
                    { key: 'lineImpedance', label: 'Zline 線路インピーダンス(Ω)', type: 'number', diagramKey: 'lineImpedance' },
                    { key: 'clampV', label: 'Vclamp TVSクランプ電圧(V)', type: 'number', diagramKey: 'clampV' },
                    { key: 'pulseMs', label: 'パルス幅(ms)', type: 'number', diagramKey: 'pulseMs' },
                ],
                rows: [['ピーク電流', `${current.toFixed(2)} A`], ['ピーク電力', `${power.toFixed(1)} W`], ['パルスエネルギー', `${(power * toFinite(f.pulseMs) / 1000).toFixed(4)} J`]],
                tone: power < 600 ? 'ok' : 'warn',
            };
        }

        if (activeToolId.value === 'fuse') {
            const usable = toFinite(f.ratedCurrent) * (1 - toFinite(f.deratingPct) / 100);
            const margin = usable - toFinite(f.loadCurrent);
            return {
                title: 'ヒューズ設計と期待寿命設計',
                model: 'fuse',
                fields: [
                    { key: 'ratedCurrent', label: 'F1 定格電流(A)', type: 'number', diagramKey: 'ratedCurrent' },
                    { key: 'loadCurrent', label: 'Iload 負荷電流(A)', type: 'number', diagramKey: 'loadCurrent' },
                    { key: 'ambient', label: 'Ta 周囲温度(°C)', type: 'number', diagramKey: 'ambient' },
                    { key: 'deratingPct', label: 'ディレーティング(%)', type: 'number', diagramKey: 'deratingPct' },
                ],
                rows: [['使用可能電流', `${usable.toFixed(3)} A`], ['余裕', `${margin.toFixed(3)} A`], ['負荷率', `${(toFinite(f.loadCurrent) / Math.max(usable, 1e-12) * 100).toFixed(1)} %`]],
                tone: margin > 0 ? 'ok' : 'bad',
            };
        }

        if (activeToolId.value === 'polyfuse') {
            const loss = toFinite(f.loadCurrent) ** 2 * toFinite(f.resistance);
            const holdMargin = toFinite(f.holdCurrent) - toFinite(f.loadCurrent);
            return {
                title: 'ポリスイッチ発熱設計',
                model: 'polyfuse',
                fields: [
                    { key: 'holdCurrent', label: 'PTC 保持電流(A)', type: 'number', diagramKey: 'holdCurrent' },
                    { key: 'tripCurrent', label: 'PTC トリップ電流(A)', type: 'number', diagramKey: 'tripCurrent' },
                    { key: 'loadCurrent', label: 'Iload 負荷電流(A)', type: 'number', diagramKey: 'loadCurrent' },
                    { key: 'resistance', label: 'RPTC 抵抗(Ω)', type: 'number', diagramKey: 'resistance' },
                    { key: 'ambient', label: 'Ta 周囲温度(°C)', type: 'number', diagramKey: 'ambient' },
                ],
                rows: [['発熱', `${loss.toFixed(4)} W`], ['保持余裕', `${holdMargin.toFixed(3)} A`], ['トリップ比', `${(toFinite(f.loadCurrent) / Math.max(toFinite(f.tripCurrent), 1e-12) * 100).toFixed(1)} %`]],
                tone: holdMargin > 0 ? 'ok' : 'bad',
            };
        }

        if (activeToolId.value === 'connector') {
            const pins = Math.max(toFinite(f.pins), 1);
            const totalCurrent = pins * toFinite(f.currentPerPin);
            return {
                title: 'コネクタ視点補助リファレンス',
                model: 'connector',
                fields: [
                    { key: 'environment', label: '用途', type: 'select', options: [['board-to-wire', '基板-電線'], ['board-to-board', '基板-基板'], ['external', '外部I/F']], diagramKey: 'environment' },
                    { key: 'pins', label: 'ピン数', type: 'number', diagramKey: 'pins' },
                    { key: 'currentPerPin', label: '1pin電流(A)', type: 'number', diagramKey: 'currentPerPin' },
                ],
                rows: [['総電流目安', `${totalCurrent.toFixed(2)} A`], ['確認観点', f.environment === 'external' ? '抜き差し回数/ラッチ/ESD' : '誤挿入防止/極性/実装高さ'], ['推奨確認', '定格電流は温度上昇条件で確認']],
                tone: toFinite(f.currentPerPin) <= 2 ? 'ok' : 'warn',
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
            const entries = String(f.entries).split('\n').map((row) => row.split(',').map((v) => v.trim())).filter((row) => row.length >= 4);
            const mounted = entries.filter((row) => row[3] === '実装').length;
            return {
                title: '0Ω/未実装/ジャンパ整理',
                model: 'jumper',
                fields: [{ key: 'entries', label: 'Ref,種類,目的,状態', type: 'textarea', diagramKey: 'entries' }],
                rows: [['登録数', `${entries.length}`], ['実装', `${mounted}`], ['未実装/切替', `${entries.length - mounted}`]],
                tone: entries.some((row) => row[2] === '') ? 'warn' : 'ok',
            };
        }

        if (activeToolId.value === 'startup') {
            const rows = String(f.rails).split('\n').map((row) => row.split(',').map((v) => v.trim())).filter((row) => row[0]);
            const warnings = rows.filter((row) => row[1] && !rows.some((candidate) => candidate[0] === row[1]));
            return {
                title: '起動・停止/リセット/依存関係診断',
                model: 'startup',
                fields: [
                    { key: 'template', label: 'テンプレート', type: 'select', options: [['pmic-mcu', 'PMIC + MCU'], ['fpga-ddr', 'FPGA + DDR']], diagramKey: 'template' },
                    { key: 'rails', label: 'rail,parent,rise_ms/reset_ms', type: 'textarea', diagramKey: 'rails' },
                ],
                rows: [['ノード数', `${rows.length}`], ['依存不明', `${warnings.length}`], ['判定', warnings.length ? '親レール未定義あり' : '依存関係は成立']],
                tone: warnings.length ? 'warn' : 'ok',
            };
        }

        return null;
    });

    // ══════════════════════════════════════════════
    // 1. ADCコード/スケーリング
    // ══════════════════════════════════════════════
    const adc = reactive({
        bits: 12, vref: 3.3, vin: 1.65, offset: 0,
    });
    const adcResult = computed(() => {
        const fullScale = Math.pow(2, adc.bits) - 1;
        const code      = Math.round((adc.vin - adc.offset) / adc.vref * fullScale);
        const lsb       = adc.vref / fullScale;
        const clamped   = Math.max(0, Math.min(fullScale, code));
        return {
            code:     clamped,
            hex:      '0x' + clamped.toString(16).toUpperCase().padStart(Math.ceil(adc.bits / 4), '0'),
            lsb_mv:   (lsb * 1000).toFixed(4),
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
    });
    const capResult = computed(() => {
        const tempFactor    = Math.pow(2, (cap.T0 - cap.T) / 10);
        const voltageFactor = Math.pow(cap.Vr / cap.V, 3);
        const life          = cap.L0 * tempFactor * voltageFactor;
        return {
            life_h:    Math.round(life),
            life_y:    (life / 8760).toFixed(1),
            temp_factor:    tempFactor.toFixed(2),
            voltage_factor: voltageFactor.toFixed(2),
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
        R0: 10000, T0: 25, B: 3950, Rmeas: 10000,
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
    });
    const shuntResult = computed(() => {
        if (shunt.mode === 'from_vout') {
            const Vshunt = shunt.Vout / shunt.gain;
            const I      = Vshunt / shunt.Rs;
            const P      = I * I * shunt.Rs;
            return { I: I.toFixed(4), Vshunt_mv: (Vshunt * 1000).toFixed(3), P_mW: (P * 1000).toFixed(3) };
        } else {
            const Vshunt = shunt.I * shunt.Rs;
            const Vout   = Vshunt * shunt.gain;
            const P      = shunt.I * shunt.I * shunt.Rs;
            return { Vout: Vout.toFixed(4), Vshunt_mv: (Vshunt * 1000).toFixed(3), P_mW: (P * 1000).toFixed(3) };
        }
    });

    // ══════════════════════════════════════════════
    // 5. 電源余裕解析
    // ══════════════════════════════════════════════
    const power = reactive({
        supply_w: 10,
        loads: [{ label: 'MCU', mA: 50, V: 3.3 }, { label: 'Sensor', mA: 20, V: 3.3 }],
    });
    const addLoad   = () => power.loads.push({ label: '', mA: 0, V: 3.3 });
    const removeLoad = (i) => power.loads.splice(i, 1);
    const powerResult = computed(() => {
        const totalW  = power.loads.reduce((s, l) => s + l.mA * l.V / 1000, 0);
        const margin  = power.supply_w - totalW;
        const percent = (totalW / power.supply_w * 100).toFixed(1);
        return { totalW: totalW.toFixed(3), margin: margin.toFixed(3), percent, ok: margin >= 0 };
    });

    // ══════════════════════════════════════════════
    // 6. 比較器しきい値/ヒステリシス
    // ══════════════════════════════════════════════
    const comp = reactive({
        Vcc: 3.3, R1: 100000, R2: 100000, R3: 0, Vref: 1.65,
    });
    const compResult = computed(() => {
        // 非反転入力: Vref → しきい値計算
        // ヒステリシス: R3をフィードバック抵抗として使用
        if (comp.R3 <= 0) {
            return { Vth: comp.Vref.toFixed(4), hysteresis: 0, Vth_high: comp.Vref.toFixed(4), Vth_low: comp.Vref.toFixed(4) };
        }
        const Vth_high = (comp.Vref * (1 + comp.R1 / comp.R3) - comp.Vcc * comp.R1 / comp.R3) ;
        const Vth_low  = comp.Vref * (1 + comp.R1 / comp.R3);
        const hyst     = Math.abs(Vth_high - Vth_low);
        return { Vth_high: Vth_high.toFixed(4), Vth_low: Vth_low.toFixed(4), hysteresis: hyst.toFixed(4) };
    });

    // ══════════════════════════════════════════════
    // 7. 熱設計（熱抵抗チェーン）
    // ══════════════════════════════════════════════
    const thermal = reactive({
        P: 1.0,        // 消費電力(W)
        Tambient: 25,  // 雰囲気温度(°C)
        TjLimit: 125,
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
        const cumulative = [];
        let T = thermal.Tambient;
        for (const n of [...thermal.nodes].reverse()) {
            T += thermal.P * n.Rth;
            cumulative.unshift({ label: n.label, T: T.toFixed(1) });
        }
        return { Tjunction: Tjunction.toFixed(1), totalRth, cumulative, ok: Tjunction <= thermal.TjLimit };
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
    });
    const ifaceResult = computed(() => {
        const high_margin = iface.VOH - iface.VIH;
        const low_margin  = iface.VIL - iface.VOL;
        return {
            high_margin: high_margin.toFixed(3),
            low_margin:  low_margin.toFixed(3),
            high_ok: high_margin > 0,
            low_ok:  low_margin > 0,
        };
    });

    const analysisReport = computed(() => {
        if (activeToolId.value === 'adc') {
            const fullScale = Math.pow(2, adc.bits) - 1;
            const rawCode = (adc.vin - adc.offset) / Math.max(adc.vref, 1e-12) * fullScale;
            const lowerHeadroom = adc.vin - adc.offset;
            const upperHeadroom = adc.vref - lowerHeadroom;
            const rangeUse = fullScale > 0 ? adcResult.value.code / fullScale : 0;
            const clipped = adcResult.value.clipped || adc.vref <= 0;
            const tone = clipped ? 'bad' : (rangeUse < 0.1 || rangeUse > 0.9 ? 'warn' : 'ok');
            const summary = clipped
                ? '入力がADCレンジ外です。Vref、オフセット、前段分圧またはゲインの見直しが必要です。'
                : rangeUse < 0.1
                    ? 'レンジ使用率が低く、分解能を捨てています。前段ゲインまたはVrefを見直す余地があります。'
                    : rangeUse > 0.9
                        ? '上側ヘッドルームが小さく、ばらつきや過渡でクリップしやすい状態です。'
                        : '入力はレンジ内で、コード化係数とヘッドルームを設計値として使えます。';
            return report({
                verdict: clipped ? 'FAIL' : (tone === 'warn' ? 'WARN' : 'PASS'),
                tone,
                summary,
                metrics: [
                    { label: 'レンジ使用率', value: `${(rangeUse * 100).toFixed(1)} %` },
                    { label: '下側余裕', value: formatNumber(lowerHeadroom, 4, 'V') },
                    { label: '上側余裕', value: formatNumber(upperHeadroom, 4, 'V') },
                    { label: '未丸めコード', value: formatNumber(rawCode, 2) },
                ],
                dominantFactors: ['Vref', 'オフセット', '前段ゲイン/分圧比'],
                warnings: clipped ? ['クリップ後のコードだけを使うと、異常条件を隠します。'] : [],
                nextActions: clipped
                    ? ['最大/最小入力を入れたレンジ設計に戻し、Vrefまたは分圧比を再設定する']
                    : ['センサ最小/最大値でも同じ確認を行い、ファーム定数に丸め誤差を含める'],
            });
        }

        if (activeToolId.value === 'cap-life') {
            const lifeY = parseNumber(capResult.value.life_y);
            const voltageStress = cap.V / Math.max(cap.Vr, 1e-12);
            const tempDelta = cap.T0 - cap.T;
            const tone = lifeY < 2 ? 'bad' : (lifeY < 5 || voltageStress > 0.8 || tempDelta < 20 ? 'warn' : 'ok');
            return report({
                verdict: tone === 'bad' ? 'FAIL' : (tone === 'warn' ? 'WARN' : 'PASS'),
                tone,
                summary: tone === 'ok'
                    ? '寿命は目安として成立しています。温度・電圧ディレーティングの根拠も残せます。'
                    : '寿命またはディレーティング余裕が弱いです。温度、定格電圧、部品グレードのいずれかが支配しています。',
                metrics: [
                    { label: '推定寿命', value: `${capResult.value.life_y} 年` },
                    { label: '定格温度との差', value: formatNumber(tempDelta, 1, 'degC') },
                    { label: '電圧使用率', value: `${(voltageStress * 100).toFixed(1)} %` },
                ],
                dominantFactors: [
                    Math.abs(tempDelta) < 20 ? '動作温度' : '定格寿命',
                    voltageStress > 0.8 ? '定格電圧余裕' : '温度係数',
                ],
                warnings: voltageStress > 1 ? ['動作電圧が定格電圧を超えています。'] : [],
                nextActions: tone === 'ok'
                    ? ['周囲温度の最悪条件とリプル電流条件で再計算する']
                    : ['105/125degC品への変更、定格電圧の引き上げ、発熱源からの配置分離を検討する'],
            });
        }

        if (activeToolId.value === 'divider') {
            if (divider.mode === 'voltage') {
                const totalR = divider.r1 + divider.r2;
                const dividerCurrent = divider.vin / Math.max(totalR, 1e-12);
                const tone = dividerCurrent > 0.001 || dividerCurrent < 0.00001 ? 'warn' : 'ok';
                return report({
                    verdict: tone === 'warn' ? 'WARN' : 'PASS',
                    tone,
                    summary: tone === 'ok'
                        ? '分圧比と自己消費は目安範囲です。負荷インピーダンスを入れると成立性を確定できます。'
                        : '分圧電流が極端です。消費電流、入力バイアス、ADCサンプル容量の影響を確認してください。',
                    metrics: [
                        { label: '分圧比', value: `${dividerResult.value.ratio} %` },
                        { label: '分圧電流', value: formatNumber(dividerCurrent * 1000, 3, 'mA') },
                        { label: '合成抵抗', value: formatNumber(totalR, 0, 'ohm') },
                    ],
                    dominantFactors: ['R1/R2比', '合成抵抗', '後段入力インピーダンス'],
                    warnings: ['負荷インピーダンス未入力のため、後段での分圧ずれは未判定です。'],
                    nextActions: ['ADC入力ならサンプル時間と入力インピーダンス条件を確認する'],
                });
            }
            return report({
                verdict: 'CHECK',
                tone: 'warn',
                summary: 'B定数式による温度換算です。サーミスタの温度範囲、自己発熱、固定抵抗公差を入れないと設計判定には不足します。',
                metrics: [
                    { label: '換算温度', value: `${dividerResult.value.temp_c} degC` },
                    { label: 'B定数', value: `${divider.B}` },
                ],
                dominantFactors: ['B定数', '測定抵抗', '自己発熱'],
                warnings: ['単一点換算のため、温度レンジ全体の誤差は未評価です。'],
                nextActions: ['min/typ/max抵抗表またはSteinhart-Hart係数で温度範囲の誤差を見る'],
            });
        }

        if (activeToolId.value === 'shunt') {
            const lossMw = parseNumber(shuntResult.value.P_mW);
            const vshuntMv = parseNumber(shuntResult.value.Vshunt_mv);
            const tone = lossMw > 500 || vshuntMv > 150 ? 'bad' : (lossMw > 250 || vshuntMv > 75 ? 'warn' : 'ok');
            return report({
                verdict: tone === 'bad' ? 'FAIL' : (tone === 'warn' ? 'WARN' : 'PASS'),
                tone,
                summary: tone === 'ok'
                    ? 'シャント損失と検出電圧は低めで、次はアンプ入力範囲とADCレンジの確認に進めます。'
                    : 'シャント損失または電圧降下が大きく、発熱・電圧ロス・アンプ飽和のリスクがあります。',
                metrics: [
                    { label: 'シャント電圧', value: `${vshuntMv.toFixed(3)} mV` },
                    { label: 'シャント損失', value: `${lossMw.toFixed(3)} mW` },
                    { label: 'アンプゲイン', value: `${shunt.gain}` },
                ],
                dominantFactors: ['Rs', '最大電流', 'アンプゲイン'],
                warnings: shunt.gain <= 0 ? ['アンプゲインが0以下です。'] : [],
                nextActions: tone === 'ok'
                    ? ['最大電流時のVoutとADCフルスケールを照合する']
                    : ['Rsを下げる、ケルビン接続/電力定格を確認する、ゲイン側で分解能を稼ぐ'],
            });
        }

        if (activeToolId.value === 'power') {
            const supply = Math.max(power.supply_w, 1e-12);
            const total = parseNumber(powerResult.value.totalW);
            const margin = supply - total;
            const marginPct = margin / supply * 100;
            const largest = power.loads.reduce((max, load) => {
                const watts = toFinite(load.mA) * toFinite(load.V) / 1000;
                return watts > max.watts ? { label: load.label || '未命名負荷', watts } : max;
            }, { label: '', watts: -Infinity });
            const tone = margin < 0 ? 'bad' : (marginPct < 20 ? 'warn' : 'ok');
            return report({
                verdict: tone === 'bad' ? 'FAIL' : (tone === 'warn' ? 'WARN' : 'PASS'),
                tone,
                summary: tone === 'ok'
                    ? '電源容量は合計負荷に対して20%以上の余裕があります。'
                    : '電源余裕が不足または薄いです。最大負荷、起動電流、温度ディレーティングで再評価してください。',
                metrics: [
                    { label: '消費合計', value: formatNumber(total, 3, 'W') },
                    { label: '余裕', value: `${formatNumber(margin, 3, 'W')} / ${marginPct.toFixed(1)} %` },
                    { label: '最大負荷', value: `${largest.label} ${formatNumber(largest.watts, 3, 'W')}` },
                ],
                dominantFactors: largest.label ? [largest.label, '電源定格', '起動時ピーク'] : ['電源定格'],
                warnings: ['突入電流、周囲温度、電源効率は未入力です。'],
                nextActions: tone === 'ok'
                    ? ['各負荷のmax電流と起動シーケンスでも同じ余裕を確認する']
                    : ['最大負荷を分離する、電源定格を上げる、起動順をずらす'],
            });
        }

        if (activeToolId.value === 'comparator') {
            const high = parseNumber(compResult.value.Vth_high);
            const low = parseNumber(compResult.value.Vth_low);
            const hyst = parseNumber(compResult.value.hysteresis);
            const outOfRange = high < 0 || low < 0 || high > comp.Vcc || low > comp.Vcc;
            const tone = outOfRange ? 'bad' : (hyst <= 0 ? 'warn' : 'ok');
            return report({
                verdict: tone === 'bad' ? 'FAIL' : (tone === 'warn' ? 'WARN' : 'PASS'),
                tone,
                summary: outOfRange
                    ? 'しきい値が電源範囲外です。抵抗ネットワークか基準電圧の式を見直してください。'
                    : hyst <= 0
                        ? 'ヒステリシスなしです。ノイズ源がある入力ではチャタリング余裕が判定できません。'
                        : 'しきい値は電源範囲内で、ヒステリシス幅を設計根拠として使えます。',
                metrics: [
                    { label: 'High -> Low', value: formatNumber(high, 4, 'V') },
                    { label: 'Low -> High', value: formatNumber(low, 4, 'V') },
                    { label: 'ヒステリシス', value: formatNumber(hyst, 4, 'V') },
                ],
                dominantFactors: ['Vref', 'R1/R2/R3比', '出力振幅'],
                warnings: ['入力オフセット、入力バイアス、出力High/Low実電圧は未入力です。'],
                nextActions: tone === 'ok'
                    ? ['センサ誤差とノイズ振幅をヒステリシス幅に重ねて確認する']
                    : ['R3とVrefを再設定し、しきい値を0-Vcc範囲内へ戻す'],
            });
        }

        if (activeToolId.value === 'thermal') {
            const tj = parseNumber(thermalResult.value.Tjunction);
            const margin = thermal.TjLimit - tj;
            const maxNode = thermal.nodes.reduce((max, node) => toFinite(node.Rth) > max.Rth ? node : max, { label: '', Rth: -Infinity });
            const tone = margin < 0 ? 'bad' : (margin < 20 ? 'warn' : 'ok');
            return report({
                verdict: tone === 'bad' ? 'FAIL' : (tone === 'warn' ? 'WARN' : 'PASS'),
                tone,
                summary: tone === 'ok'
                    ? 'Tjは閾値に対して20degC以上の余裕があります。支配熱抵抗を設計根拠として残せます。'
                    : '熱余裕が不足または薄いです。支配熱抵抗か消費電力を直接下げる必要があります。',
                metrics: [
                    { label: 'Tj余裕', value: formatNumber(margin, 1, 'degC') },
                    { label: '合計熱抵抗', value: formatNumber(thermalResult.value.totalRth, 2, 'degC/W') },
                    { label: '支配熱抵抗', value: `${maxNode.label} ${formatNumber(maxNode.Rth, 2, 'degC/W')}` },
                ],
                dominantFactors: [maxNode.label || '熱抵抗チェーン', '消費電力', '周囲温度'],
                warnings: ['基板銅箔、風速、隣接発熱体は未モデル化です。'],
                nextActions: tone === 'ok'
                    ? ['最悪周囲温度と最大消費電力で再計算する']
                    : ['支配熱抵抗を下げる、放熱面積/風量を増やす、消費電力を下げる'],
            });
        }

        if (activeToolId.value === 'interface') {
            const high = parseNumber(ifaceResult.value.high_margin);
            const low = parseNumber(ifaceResult.value.low_margin);
            const minMargin = Math.min(high, low);
            const tone = minMargin <= 0 ? 'bad' : (minMargin < 0.2 ? 'warn' : 'ok');
            return report({
                verdict: tone === 'bad' ? 'FAIL' : (tone === 'warn' ? 'WARN' : 'PASS'),
                tone,
                summary: tone === 'ok'
                    ? 'H/L両側の電圧余裕があります。温度・電源ばらつきを重ねると設計判定を確定できます。'
                    : 'ロジックレベル余裕が不足または薄いです。レベル変換、プルアップ、電源条件の見直し対象です。',
                metrics: [
                    { label: 'H余裕', value: formatNumber(high, 3, 'V') },
                    { label: 'L余裕', value: formatNumber(low, 3, 'V') },
                    { label: '最小余裕', value: formatNumber(minMargin, 3, 'V') },
                ],
                dominantFactors: high <= low ? ['VOH/VIH'] : ['VOL/VIL'],
                warnings: ['電源電圧min/max、温度、出力電流条件は未入力です。'],
                nextActions: tone === 'ok'
                    ? ['データシートのmin/max条件へ置き換えて余裕を再計算する']
                    : ['レベルシフタ追加、プルアップ電圧変更、同一電源ドメイン化を検討する'],
            });
        }

        const qt = quickTool.value;
        if (!qt) {
            return report({
                verdict: 'CHECK',
                tone: 'neutral',
                summary: 'このツールの判定モデルが未定義です。',
                warnings: ['計算値だけでは設計判断として不足しています。'],
                nextActions: ['判定条件、margin、支配要因を追加する'],
            });
        }

        const quickTone = qt.tone === 'bad' ? 'bad' : (qt.tone === 'warn' ? 'warn' : 'ok');
        const genericActions = {
            tolerance: ['歩留まりが悪い場合は平均のセンタリング、公差ランク、部品点数を見直す'],
            bode: ['評価周波数で必要なゲイン/位相余裕を決め、R/C公差込みで再計算する'],
            ovp: ['保護素子の連続/パルス定格と温度ディレーティングを入力して合否化する'],
            tvs: ['TVSのピークパルス電力定格、波形、繰り返し条件と照合する'],
            fuse: ['周囲温度、突入電流、I2t条件を入れて定格選定を確定する'],
            polyfuse: ['保持電流の温度ディレーティングとトリップ時間をデータシートで確認する'],
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
            connector: ['電流目安', '用途別確認観点', '参照不足条件'],
            cable: ['結線判定', '端子数差', '図面化注意'],
            jumper: ['状態数', '量産実装数', '未定義目的'],
            startup: ['依存関係', '親レール未定義', '起動順'],
        };
        return report({
            verdict: quickTone === 'bad' ? 'FAIL' : (quickTone === 'warn' ? 'WARN' : 'PASS'),
            tone: quickTone,
            summary: qt.summary || `${qt.title} の計算値を、設計上の確認項目へ展開しています。`,
            metrics: qt.rows.map((row) => ({ label: row[0], value: row[1] })),
            dominantFactors: capability[qt.model] ?? ['入力条件'],
            warnings: qt.warnings ?? [],
            nextActions: qt.nextActions ?? genericActions[qt.model] ?? ['部品定格と最悪条件を追加して判定する'],
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
        activeDiagram, diagramFocus, focusDiagram, clearDiagramFocus, isDiagramFocused, diagramItemClass,
    };
}
