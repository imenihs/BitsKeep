/**
 * 設計解析ツールの責務分割モジュール。
 * 親 setup から渡された reactive/computed と数値ヘルパーを使い、
 * 画面表示に必要な状態、計算結果、レポート生成関数を返す。
 */

/**
 * setupDesignToolDiagrams は親から渡された依存を使ってツール責務を初期化する。
 * @param {object} deps 入力状態、数値変換、レポート生成などの依存。
 * @returns {object} Vueテンプレートへ公開する状態、computed、操作関数。
 * @sideEffects reactive状態とlocalStorageを更新する操作関数を含む。
 */
// 目的: 設計解析ツールのsetup Design Tool Diagramsを扱う。機能: 入力値を検証・整形し、画面または計算処理へ渡す。入力: 関数シグネチャの値。出力: 処理結果またはなし。動作条件: 設計解析ツールの初期化後に呼び出す。副作用: Vue状態、localStorage、DOM、HTTP通信のいずれかを更新する場合がある。
export default function setupDesignToolDiagrams({
    computed,
    activeToolId,
    passiveToolMode,
    passiveNetwork,
    eia96,
    eia96Lookup,
    eia96MultiplierBySelected,
    adc,
    adcResult,
    cap,
    capResult,
    divider,
    dividerResult,
    shunt,
    shuntResult,
    power,
    powerResult,
    battery,
    batteryProfiles,
    batteryResult,
    comp,
    compResult,
    thermal,
    thermalResult,
    iface,
    ifaceResult,
    quickForms,
    connectorActiveTemplate,
    connectorSummary,
    connectorPinMap,
}) {
const activeDiagram = computed(() => {
    const passiveMode = passiveToolMode(activeToolId.value);
    if (passiveMode === 'network') {
        const form = passiveNetwork.form;
        return {
            type: 'flow',
            title: 'R/Cネットワーク探索',
            subtitle: '目標値に対し、部品種別、E系列、接続形、素子数範囲を決めて候補を比較します。',
            formula: 'target -> series / parallel / mixed -> closest candidate',
            parts: [
                { key: 'part_type', label: '種別', desc: passiveNetwork.partTypeLabel },
                { key: 'target_raw', label: '目標値', desc: form.target_raw },
                { key: 'series', label: '探索元', desc: form.inventory_only ? '在庫値' : form.series },
                { key: 'circuit_types', label: '接続', desc: form.circuit_types.join(' / ') || '未選択' },
                { key: 'results', label: '候補', desc: `${passiveNetwork.results.length}件` },
            ],
            blocks: [
                { key: 'part_type', label: passiveNetwork.partTypeLabel, sub: form.inventory_only ? '在庫値' : form.series },
                { key: 'target_raw', label: '目標値', sub: form.target_raw },
                { key: 'circuit_types', label: '接続形', sub: form.circuit_types.join('/') || '-' },
                { key: 'results', label: '候補', sub: `${passiveNetwork.results.length}件` },
            ],
            assumptions: ['温度係数、電圧係数、寄生成分、実装ばらつきは探索結果へ含めません。'],
        };
    }

    if (passiveMode === 'divider') {
        const form = passiveNetwork.form;
        const variableDivider = form.divider_mode === 'variable';
        return {
            type: 'divider',
            title: variableDivider ? 'VR分圧の抵抗位置' : '分圧抵抗の位置関係',
            subtitle: variableDivider
                ? 'R上、VR、R下と負荷条件から出力可変範囲と端点電力を見ます。'
                : '入力電圧をR1/R2で分け、負荷込みのVout候補と抵抗電力を確認します。',
            formula: variableDivider ? 'Vout range = Vin * (Rbottom + VR position) / Rtotal' : 'Vout = Vin * R2 / (R1 + R2)',
            keys: { input: 'input_voltage_raw', upper: 'target_raw', lower: 'load_resistance_raw', output: 'output_voltage_raw' },
            parts: [
                { key: 'input_voltage_raw', label: 'Vin', desc: `${form.input_voltage_raw} V` },
                { key: 'target_raw', label: variableDivider ? 'R上 + VR' : '分圧比', desc: variableDivider ? passiveNetwork.dividerVariable.nominal_pot_raw : form.target_raw },
                { key: 'load_resistance_raw', label: '負荷', desc: passiveNetwork.dividerLoadConfig.display },
                { key: 'output_voltage_raw', label: 'Vout', desc: form.divider_target_mode === 'voltage' ? form.output_voltage_raw : `${form.target_raw} of Vin` },
            ],
            assumptions: ['入力源インピーダンス、後段入力電流、抵抗温度係数、VR摺動ノイズは別途確認します。'],
        };
    }

    if (passiveMode === 'variable') {
        const variable = passiveNetwork.variable;
        return {
            type: 'flow',
            title: '固定抵抗 + 可変抵抗の調整範囲',
            subtitle: '基準抵抗値と可変幅から固定抵抗、VR値、端点範囲を選びます。',
            formula: 'Rlow / Rhigh = f(Rfixed, Rpot, circuit)',
            parts: [
                { key: 'reference_raw', label: '基準値', desc: variable.reference_raw },
                { key: 'span_raw', label: '可変幅', desc: `${variable.span_raw}${variable.span_mode === 'percent' ? '%' : ' Ω'}` },
                { key: 'fixed_source', label: '固定抵抗', desc: variable.fixed_source },
                { key: 'pot_source', label: 'VR', desc: variable.pot_source === 'vr-common' ? '標準VR値' : variable.pot_source },
                { key: 'result', label: '候補', desc: `${passiveNetwork.variableResult.candidates.length}件` },
            ],
            blocks: [
                { key: 'reference_raw', label: '基準値', sub: variable.reference_raw },
                { key: 'span_raw', label: '可変幅', sub: `${variable.span_raw}${variable.span_mode === 'percent' ? '%' : 'Ω'}` },
                { key: 'fixed_source', label: '固定抵抗', sub: variable.fixed_source },
                { key: 'result', label: '候補範囲', sub: `${passiveNetwork.variableResult.candidates.length}件` },
            ],
            assumptions: ['VRの機械寿命、摺動ノイズ、温度係数、端点残留抵抗はデータシートで確認します。'],
        };
    }

    if (activeToolId.value === 'eia96') {
        const lookup = eia96Lookup.value;
        const multiplier = eia96MultiplierBySelected.value;
        return {
            type: 'flow',
            title: 'チップ抵抗 EIA-96コード早見表',
            subtitle: '2桁インデックスと倍率文字から、1%系チップ抵抗のマーキング値を確認します。',
            formula: 'R = E96[index] * multiplier(letter)',
            parts: [
                { key: 'code', label: 'コード', desc: eia96.codeQuery || '未入力' },
                { key: 'index', label: '01-96', desc: lookup.valid ? `${lookup.baseCode} = ${lookup.baseValue}` : 'E96インデックス' },
                { key: 'multiplier', label: '倍率文字', desc: `${multiplier.label} / ${multiplier.range}` },
                { key: 'value', label: '抵抗値', desc: lookup.valid ? lookup.display : '未確定' },
            ],
            blocks: [
                { key: 'code', label: eia96.codeQuery || 'コード', sub: lookup.valid ? lookup.normalized : '例: 01C' },
                { key: 'index', label: 'E96値', sub: lookup.valid ? `${lookup.baseValue}` : '01-96' },
                { key: 'multiplier', label: '倍率', sub: multiplier.label },
                { key: 'value', label: '抵抗値', sub: lookup.valid ? lookup.display : '--' },
            ],
            assumptions: ['EIA-96の3文字マーキング用です。0Ω、3桁/4桁SMDコード、メーカー独自表記、マーキング省略品は別途確認します。'],
        };
    }

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
                { key: 'L0', label: '定格寿命', sub: `${cap.L0} h @ ${cap.T0} ℃` },
                { key: 'T', label: '温度ストレス', sub: `${cap.T} ℃` },
                { key: 'V', label: '電圧ストレス', sub: `${cap.V} / ${cap.Vr} V` },
                { key: 'life', label: '推定寿命', sub: `${capResult.value.life_y} 年` },
            ],
            assumptions: ['自己発熱はESRと暫定熱抵抗10℃/Wからの目安です。電圧は寿命倍率ではなくディレーティングで確認します。'],
        };
    }

    if (activeToolId.value === 'divider') {
        const thermistorOnHighSide = divider.position === 'high';
        return {
            type: 'divider-ntc',
            title: 'NTC/PTC測定分圧の位置関係',
            subtitle: '測定済み抵抗の温度換算に加え、温度-電圧カーブと感度が測定対象温度に来ているかを確認します。',
            formula: divider.sensorType === 'ptc'
                ? 'R(T)=R0*exp(-B*(1/T - 1/T0)), Vout=f(Rth,Rfix)'
                : 'R(T)=R0*exp(B*(1/T - 1/T0)), Vout=f(Rth,Rfix)',
            keys: {
                input: 'adcVref',
                upper: thermistorOnHighSide ? 'Rmeas' : 'fixedResistor',
                lower: thermistorOnHighSide ? 'fixedResistor' : 'Rmeas',
                output: 'temp',
            },
            parts: [
                { key: 'fixedResistor', label: 'Rfix', desc: `固定抵抗 ${divider.fixedResistor} Ω` },
                { key: 'R0', label: 'R0', desc: `基準抵抗 ${divider.R0} Ω` },
                { key: 'T0', label: 'T0', desc: `基準温度 ${divider.T0} ℃` },
                { key: 'B', label: divider.sensorType === 'ptc' ? 'PTC係数' : 'B定数', desc: `${divider.B}` },
                { key: 'Rmeas', label: 'Rth', desc: `測定抵抗 ${divider.Rmeas} Ω` },
                { key: 'temp', label: '測定範囲', desc: `${divider.targetTempMin}-${divider.targetTempMax} ℃` },
            ],
            assumptions: ['ここでは温度センサ測定回路として、Rth/Rfixの配置、感度分布、測定対象温度の重なりを確認します。', '自己発熱、固定抵抗公差、ADC量子化誤差は温度判定の未評価条件です。'],
        };
    }

    if (activeToolId.value === 'shunt') {
        const bidirectional = shunt.senseMode === 'bidirectional';
        return {
            type: 'shunt',
            title: 'ローサイド・シャント電流検出',
            subtitle: bidirectional
                ? 'Rs両端電圧を増幅し、ゼロ電流オフセットを足して負電流もADC範囲へ入れます。'
                : '負荷の下側にRsを置き、Rs両端をオフセットなしで増幅してADCへ入れます。',
            formula: bidirectional ? 'Vout = Vzero + I * Rs * gain' : 'Vout = I * Rs * gain',
            parts: [
                { key: 'I', label: 'I', desc: `負荷電流 ${shunt.I} A` },
                { key: 'load', label: '負荷', desc: '測定対象の負荷' },
                { key: 'Rs', label: 'Rs シャント抵抗', desc: `${shunt.Rs} Ω` },
                { key: 'Vshunt', label: 'Vshunt', desc: `${shuntResult.value.Vshunt_mv} mV` },
                { key: 'gain', label: 'gain', desc: `アンプゲイン ${shunt.gain}` },
                { key: 'Vzero', label: bidirectional ? 'Vzero' : '0A基準', desc: `${shuntResult.value.zero_offset_v} V` },
                { key: 'Vout', label: 'Vout/ADC', desc: `${shuntResult.value.Vout} V / code ${shuntResult.value.adc_code}` },
            ],
            assumptions: [
                'この図はローサイド検出です。ハイサイド検出では同相入力範囲とサージ耐性を別途確認します。',
                'Rs損失は測定レンジの最大絶対電流と入力したシャント抵抗の定格電力で判断します。',
                bidirectional ? '双方向では0A出力オフセットを基準に、負電流でVoutが下がります。' : '片方向では負電流を測定レンジに含めません。',
            ],
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
            assumptions: ['通常余裕は入力した供給電力と負荷合計を直接比較します。効率は子レールの上流換算負荷にだけ反映します。', '突入電流は時間幅と流れるレールが未確定なら判定不足として扱います。'],
        };
    }

    if (activeToolId.value === 'battery-runtime') {
        return null;
    }

    if (activeToolId.value === 'comparator') {
        const dividerReference = comp.referenceMode === 'divider';
        const positiveInput = comp.inputPolarity !== 'negative';
        const negativeDividerReference = !positiveInput && dividerReference;
        return {
            type: 'comparator',
            title: `${positiveInput ? '+入力(非反転)' : '-入力(反転)'} 比較器: ${dividerReference ? 'Vcc分圧基準' : 'Vref基準'} + 正帰還`,
            subtitle: positiveInput
                ? 'R1はVinから+入力ノードへ入る抵抗、R3はOUTから同じ+入力ノードへ戻る正帰還抵抗です。'
                : (negativeDividerReference
                    ? 'Vinは-入力へ入れ、R2/R4のテブナン抵抗RthとOUT/R3で作る+入力基準へ正帰還を戻します。R1はショート扱いです。'
                    : 'Vinは-入力へ入れ、Vref/R1とOUT/R3で作る+入力基準へ正帰還を戻します。'),
            formula: comp.R3 > 0
                ? (positiveInput ? 'Vin(th)=Vref*(1+R1/R3)-Vout*(R1/R3)' : 'Vin(th)=(Vref(th)*R3+Vout*Rsrc)/(Rsrc+R3)')
                : 'R3=0 のため Vin(th)=Vref',
            parts: [
                { key: 'Vcc', label: 'Vcc', desc: `${comp.Vcc} V` },
                ...(dividerReference
                    ? [
                        { key: 'R2', label: 'R2 Vcc側', desc: `${comp.R2} Ω` },
                        { key: 'R4', label: 'R4 GND側', desc: `${comp.R4} Ω` },
                    ]
                    : [{ key: 'Vref', label: 'Vref', desc: `${comp.Vref} V` }]),
                { key: 'R1', label: positiveInput ? 'R1 Vin側抵抗' : (negativeDividerReference ? 'R1 ショート' : 'R1 基準側抵抗'), desc: negativeDividerReference ? '0 Ω扱い' : `${comp.R1} Ω` },
                { key: 'R3', label: 'R3 OUT→V+正帰還', desc: comp.R3 > 0 ? `${comp.R3} Ω` : 'なし' },
                { key: 'inputPolarity', label: 'Vin入力先', desc: positiveInput ? '+入力' : '-入力' },
                { key: 'out', label: 'OUT', desc: `High ${comp.Vcc} V想定` },
            ],
            assumptions: [
                positiveInput ? 'Vin上昇でOUTはLow→High、Vin下降でOUTはHigh→Lowへ遷移します。' : 'Vin上昇でOUTはHigh→Low、Vin下降でOUTはLow→Highへ遷移します。',
                negativeDividerReference ? '正帰還は常に+入力側へ戻します。-入力/Vcc分圧方式ではR1をショートし、R2/R4合成抵抗RthとR3でヒステリシスを作ります。' : '正帰還は常に+入力側へ戻します。-入力/Vref方式ではR1は基準側抵抗として扱います。',
                'Vcc分圧基準ではR2/R4をVref(th)とRthへテブナン変換します。',
            ],
        };
    }

    if (activeToolId.value === 'thermal') {
        return {
            type: 'thermal',
            title: '熱抵抗チェーン',
            subtitle: '発熱源から周囲温度までの熱抵抗を直列に積み上げます。',
            formula: 'Tj = Tambient + P * sum(Rth)',
            parts: [
                { key: 'P', label: 'P 発熱源', desc: `${thermal.P} W` },
                { key: 'Tambient', label: 'Ta 周囲温度', desc: `${thermal.Tambient} ℃` },
                { key: 'nodes', label: 'Rth chain', desc: `${thermal.nodes.length} stages` },
                { key: 'Tj', label: 'Tj', desc: `${thermalResult.value.Tjunction} ℃` },
            ],
            assumptions: ['基板銅箔、風速、隣接発熱体は未モデル化です。'],
        };
    }

    if (activeToolId.value === 'interface') {
        return {
            type: 'interface',
            title: 'ロジック出力と入力しきい値',
            subtitle: 'ドライバのVOH/VOL、レシーバのVIH/VIL、シリーズ+Vccの代表しきい値を向かい合わせて余裕を見ます。',
            formula: 'H margin = VOH - VIH, L margin = VIL - VOL',
            parts: [
                { key: 'family', label: 'Series', desc: ifaceResult.value.logic_pair },
                { key: 'VOH', label: 'VOH', desc: `${iface.VOH} V` },
                { key: 'VOL', label: 'VOL', desc: `${iface.VOL} V` },
                { key: 'VIH', label: 'VIH', desc: `${iface.VIH} V` },
                { key: 'VIL', label: 'VIL', desc: `${iface.VIL} V` },
            ],
            assumptions: ['シリーズしきい値は代表近似です。電源min/max、温度、出力電流条件はデータシートで確認します。'],
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
                { key: 'r', label: 'R フィルタ抵抗', desc: `${quick.r} Ω` },
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
                labels: { input: 'Vin最大', series: 'Rser', clamp: 'クランプ', load: '負荷' },
                parts: [
                    { key: 'vinMax', label: 'Vin最大', desc: `${quick.vinMax} V` },
                    { key: 'seriesR', label: 'Rser 直列抵抗', desc: `${quick.seriesR} Ω` },
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
                    { key: 'lineImpedance', label: 'Zline', desc: `${quick.lineImpedance} Ω` },
                    { key: 'clampV', label: 'TVS clamp', desc: `${quick.clampV} V` },
                    { key: 'pulseMs', label: 'Pulse', desc: `${quick.pulseMs} ms` },
                ],
            },
            fuse: {
                title: 'ヒューズと負荷電流の位置関係',
                subtitle: '電源と負荷の間にF1を置き、ディレーティング後の使用可能電流と比べます。',
                formula: 'Iusable = Irated * (1 - derating)',
                keys: { input: 'ratedCurrent', series: 'ratedCurrent', clamp: 'deratingPct', load: 'loadCurrent' },
                labels: { input: '電源', series: 'F1', clamp: 'ディレーティング', load: '負荷' },
                parts: [
                    { key: 'ratedCurrent', label: 'F1 定格電流', desc: `${quick.ratedCurrent} A` },
                    { key: 'loadCurrent', label: 'Iload', desc: `${quick.loadCurrent} A` },
                    { key: 'ambient', label: 'Ta', desc: `${quick.ambient} ℃` },
                    { key: 'deratingPct', label: 'derating', desc: `${quick.deratingPct} %` },
                ],
            },
            polyfuse: {
                title: 'ポリスイッチの保持/トリップ領域',
                subtitle: 'PTCを負荷直列に入れ、保持電流、トリップ電流、自己発熱を確認します。',
                formula: 'Ploss = Iload^2 * Rptc',
                keys: { input: 'tripCurrent', series: 'holdCurrent', clamp: 'resistance', load: 'loadCurrent' },
                labels: { input: '電源', series: 'PTC', clamp: 'RPTC', load: '負荷' },
                parts: [
                    { key: 'holdCurrent', label: 'Ihold', desc: `${quick.holdCurrent} A` },
                    { key: 'tripCurrent', label: 'Itrip', desc: `${quick.tripCurrent} A` },
                    { key: 'loadCurrent', label: 'Iload', desc: `${quick.loadCurrent} A` },
                    { key: 'resistance', label: 'Rptc', desc: `${quick.resistance} Ω` },
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
            title: 'ロジックICの機能候補',
            subtitle: '機能、ファミリ、電源電圧、出力形式から候補ICとデータシート確認項目を見ます。系列間の電圧レベル判定はIF余裕で扱います。',
            formula: 'function + family + Vcc + package -> candidate IC',
            parts: [
                { key: 'family', label: 'family', desc: quick.family },
                { key: 'function', label: 'function', desc: quick.function },
                { key: 'supplyV', label: 'Vcc', desc: `${quick.supplyV} V` },
                { key: 'packagePins', label: 'pins', desc: `${quick.packagePins || 'any'}` },
            ],
            blocks: [
                { key: 'family', label: quick.family, sub: `${quick.supplyV} V` },
                { key: 'function', label: quick.function, sub: `${quick.inputs}入力` },
                { key: 'packagePins', label: 'パッケージ', sub: `${quick.packagePins || '不問'} pins` },
                { key: 'outputType', label: '出力', sub: quick.outputType },
            ],
            assumptions: ['同一型番でもファミリごとにVIH/VIL、ドライブ電流、速度、入力耐圧が異なります。', '接続先とのVOH/VOL/VIH/VIL判定はIF余裕で確認します。'],
        };
    }

    if (activeToolId.value === 'connector') {
        const template = connectorActiveTemplate.value;
        const summary = connectorSummary.value;
        return {
            type: 'connector',
            title: 'コネクタ設計/ピン配置',
            subtitle: '標準テンプレート、写真/図、ピン割付、電圧電流margin、BOM/シルク注記を同時に確認します。',
            formula: 'pin margin = Irating/pin * derating - Ipin',
            parts: [
                { key: 'connectorType', label: 'template', desc: template?.label ?? quick.connectorType },
                { key: 'viewSide', label: 'view', desc: quick.viewSide },
                { key: 'pinAssignments', label: 'pin map', desc: `${summary.assigned.length}/${connectorPinMap.value.length} assigned` },
                { key: 'currentPerPin', label: 'I margin', desc: `${summary.maxPinCurrent.toFixed(3)} A max/pin` },
                { key: 'datasheetUrl', label: 'evidence', desc: quick.datasheetUrl || template?.datasheetUrl || '未登録' },
                { key: 'pin1Mark', label: 'pin1', desc: quick.pin1Mark },
            ],
            assumptions: ['嵌合面/はんだ面/ケーブル側の視点、Pin1根拠、定格根拠URL、写真/図が不足する場合はPASSにしません。'],
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
            assumptions: ['嵌合面/はんだ面のどちらで書いたピン列かを図面に明記します。'],
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
                { key: 'bom', label: 'BOM注記', desc: '量産注記' },
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
            formula: 'レール,親,立上りms/リセットms + PG/RESET/逆給電',
            parts: [
                { key: 'template', label: 'template', desc: quick.template },
                { key: 'rails', label: 'レール', desc: '名称,親,時間' },
                { key: 'reset', label: 'RESET/PG', desc: `${quick.resetHoldMs} ms hold` },
                { key: 'backpower', label: 'back-power', desc: '部分給電経路' },
            ],
            assumptions: ['PG閾値、RESET入力しきい値、保護ダイオード電流定格はデータシートで確認します。'],
        };
    }

    return null;
});



    return {
        activeDiagram,
    };
}
