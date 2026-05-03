/**
 * 設計解析ツールの責務分割モジュール。
 * 親 setup から渡された reactive/computed と数値ヘルパーを使い、
 * 画面表示に必要な状態、計算結果、レポート生成関数を返す。
 */

/**
 * setupLogicReferenceTool は親から渡された依存を使ってツール責務を初期化する。
 * @param {object} deps 入力状態、数値変換、レポート生成などの依存。
 * @returns {object} Vueテンプレートへ公開する状態、computed、操作関数。
 * @sideEffects reactive状態とlocalStorageを更新する操作関数を含む。
 */
// 目的: 設計解析ツールのsetup Logic Reference Toolを扱う。機能: 入力値を検証・整形し、画面または計算処理へ渡す。入力: 関数シグネチャの値。出力: 処理結果またはなし。動作条件: 設計解析ツールの初期化後に呼び出す。副作用: Vue状態、localStorage、DOM、HTTP通信のいずれかを更新する場合がある。
export default function setupLogicReferenceTool({
    toFinite,
}) {
const LOGIC_74_STANDARD_FAMILIES = ['74LS', '74F', '74ALS', '74AS', '74HC', '74HCT', '74VHC', '74VHCT', '74LC', '74LVC', '74AC', '74ACT'];
const LOGIC_TTL_FAMILIES = ['74LS', '74F', '74ALS', '74AS'];
const LOGIC_CMOS_FAMILIES = ['74HC', '74HCU', '74HCT', '74VHC', '74VHCT', '74LC', '74LVC', '74AC', '74ACT'];
const LOGIC_ALL_FAMILY_OPTIONS = [
    ['any', '指定なし'],
    ['74LS', '74LS'],
    ['74F', '74F'],
    ['74ALS', '74ALS'],
    ['74AS', '74AS'],
    ['74HC', '74HC'],
    ['74HCU', '74HCU'],
    ['74HCT', '74HCT'],
    ['74VHC', '74VHC'],
    ['74VHCT', '74VHCT'],
    ['74LC', '74LC'],
    ['74LVC', '74LVC'],
    ['74AC', '74AC'],
    ['74ACT', '74ACT'],
    ['4000', '4000'],
    ['4000B', '4000B'],
    ['4500', '4500'],
    ['5000', '5000'],
];
const LOGIC_CONNECTION_FAMILY_OPTIONS = LOGIC_ALL_FAMILY_OPTIONS.filter(([value]) => value !== 'any');
const LOGIC_OUTPUT_OPTIONS = [
    ['any', '指定なし'],
    ['push-pull', 'Push-pull'],
    ['3state', '3-state'],
    ['open-collector', 'Open collector/drain'],
    ['analog-switch', 'Analog switch'],
    ['mixed', 'Mixed'],
];
const LOGIC_SERIES_SPECS = {
    '74LS': { label: '74LS TTL', vMin: 4.75, vMax: 5.25, input: 'ttl', output: 'ttl', inputMaxFixed: 5.5, note: 'TTL入力。HC CMOS入力を直接High保証できない場合があります。' },
    '74F': { label: '74F TTL', vMin: 4.75, vMax: 5.25, input: 'ttl', output: 'ttl-fast', inputMaxFixed: 5.5, note: '高速TTL。出力High保証値はTTL水準です。' },
    '74ALS': { label: '74ALS TTL', vMin: 4.5, vMax: 5.5, input: 'ttl', output: 'ttl', inputMaxFixed: 5.5, note: 'ALS TTL。' },
    '74AS': { label: '74AS TTL', vMin: 4.5, vMax: 5.5, input: 'ttl', output: 'ttl-fast', inputMaxFixed: 5.5, note: 'AS TTL。' },
    '74HC': { label: '74HC CMOS', vMin: 2, vMax: 6, input: 'cmos', output: 'cmos', note: 'CMOS入力。5V HCへTTL出力を直結するとHigh余裕が不足しがちです。' },
    '74HCU': { label: '74HCU CMOS unbuffered', vMin: 2, vMax: 6, input: 'cmos', output: 'cmos', note: '主にアンバッファインバータ。発振/リニア用途はデータシート条件必須です。' },
    '74HCT': { label: '74HCT TTL input CMOS', vMin: 4.5, vMax: 5.5, input: 'ttl', output: 'cmos', inputMaxFixed: 5.5, note: 'TTL入力互換。TTL->CMOS変換で使いやすい系統です。' },
    '74VHC': { label: '74VHC CMOS', vMin: 2, vMax: 5.5, input: 'cmos', output: 'cmos', inputMaxFixed: 5.5, note: '低電圧CMOS。多くは5V tolerant入力ですが型番条件を確認します。' },
    '74VHCT': { label: '74VHCT TTL input CMOS', vMin: 4.5, vMax: 5.5, input: 'ttl', output: 'cmos', inputMaxFixed: 5.5, note: 'TTL入力互換VHC。' },
    '74LC': { label: '74LC/LVC class', vMin: 1.65, vMax: 5.5, input: 'cmos', output: 'cmos', inputMaxFixed: 5.5, note: '74LC表記はLVC/LCX系の近似扱い。必ず型番データシートで確認します。' },
    '74LVC': { label: '74LVC CMOS', vMin: 1.65, vMax: 5.5, input: 'cmos', output: 'cmos', inputMaxFixed: 5.5, note: '低電圧CMOS。5V tolerantの有無は型番差があります。' },
    '74AC': { label: '74AC CMOS', vMin: 2, vMax: 6, input: 'cmos', output: 'cmos', note: '高速CMOS入力。' },
    '74ACT': { label: '74ACT TTL input CMOS', vMin: 4.5, vMax: 5.5, input: 'ttl', output: 'cmos', inputMaxFixed: 5.5, note: 'TTL入力互換AC。' },
    '4000': { label: '4000 CMOS', vMin: 3, vMax: 15, input: 'cmos', output: 'cmos', note: 'CD4000系。メーカーでVcc範囲と出力電流が大きく異なります。' },
    '4000B': { label: '4000B CMOS', vMin: 3, vMax: 18, input: 'cmos', output: 'cmos', note: 'Buffered 4000B系。' },
    '4500': { label: '4500 CMOS', vMin: 3, vMax: 18, input: 'cmos', output: 'cmos', note: 'CD4500系。表示/カウンタ/特殊機能が多い系統です。' },
    '5000': { label: '5000 CMOS', vMin: 3, vMax: 18, input: 'cmos', output: 'cmos', note: '5000/14500系相当の拡張CMOSとして扱います。' },
};
/**
 * ロジックICの電圧値を判定表向けに整形する。
 * 入力は数値候補、戻り値はV単位の文字列またはCHECKで、副作用はない。
 */
// 目的: 設計解析ツールのformat Logic Voltageを扱う。機能: 入力値を検証・整形し、画面または計算処理へ渡す。入力: 関数シグネチャの値。出力: 表示値、配列、オブジェクト、数値のいずれか。動作条件: 設計解析ツールの初期化後に呼び出す。副作用: なし。
const formatLogicVoltage = (value) => {
    const number = Number(value);
    return Number.isFinite(number) ? `${number.toFixed(2)} V` : 'CHECK';
};
/**
 * ロジックファミリと電源電圧から入出力しきい値の近似値を作る。
 * 対応外ファミリや無効電圧ではnullを返し、副作用はない。
 */
// 目的: 設計解析ツールのlogic Thresholdsを扱う。機能: 入力値を検証・整形し、画面または計算処理へ渡す。入力: 関数シグネチャの値。出力: 処理結果またはなし。動作条件: 設計解析ツールの初期化後に呼び出す。副作用: Vue状態、localStorage、DOM、HTTP通信のいずれかを更新する場合がある。
const logicThresholds = (family, vcc) => {
    const spec = LOGIC_SERIES_SPECS[family];
    const voltage = toFinite(vcc, Number.NaN);
    if (!spec || !Number.isFinite(voltage)) return null;
    const ttlInput = spec.input === 'ttl';
    const ttlOutput = spec.output === 'ttl' || spec.output === 'ttl-fast';
    return {
        family,
        label: spec.label,
        vcc: voltage,
        vMin: spec.vMin,
        vMax: spec.vMax,
        vccOk: voltage >= spec.vMin && voltage <= spec.vMax,
        vihMin: ttlInput ? 2.0 : voltage * 0.7,
        vilMax: ttlInput ? 0.8 : voltage * 0.3,
        vohMin: ttlOutput ? (spec.output === 'ttl-fast' ? 2.5 : 2.4) : voltage * 0.9,
        volMax: ttlOutput ? (spec.output === 'ttl-fast' ? 0.5 : 0.4) : voltage * 0.1,
        outputHighMax: voltage,
        inputMax: spec.inputMaxFixed ?? (voltage + 0.5),
        note: spec.note,
    };
};
/**
 * カタログ品のファミリが検索条件に合うかを判定する。
 * 4000系は近縁表記をまとめて扱い、戻り値は真偽値で副作用はない。
 */
// 目的: 設計解析ツールのlogic Family Matchesを扱う。機能: 入力値を検証・整形し、画面または計算処理へ渡す。入力: 関数シグネチャの値。出力: 処理結果またはなし。動作条件: 設計解析ツールの初期化後に呼び出す。副作用: Vue状態、localStorage、DOM、HTTP通信のいずれかを更新する場合がある。
const logicFamilyMatches = (itemFamily, selectedFamily) => {
    const family = String(selectedFamily || 'any').trim();
    if (!family || family === 'any') return true;
    if (family === '4000' || family === '4000B') return itemFamily === '4000' || itemFamily === '4000B';
    return itemFamily === family;
};
/**
 * ロジック機能名の別名を検索用キーへ正規化する。
 * 入力は機能名文字列、戻り値は正規化キーで、副作用はない。
 */
// 目的: 設計解析ツールのnormalize Logic Functionを扱う。機能: 入力値を検証・整形し、画面または計算処理へ渡す。入力: 関数シグネチャの値。出力: 表示値、配列、オブジェクト、数値のいずれか。動作条件: 設計解析ツールの初期化後に呼び出す。副作用: なし。
const normalizeLogicFunction = (value) => {
    const raw = String(value || 'any').trim().toLowerCase();
    const aliases = {
        inv: 'inverter',
        inverter: 'inverter',
        schmitt: 'schmitt-inverter',
        'schmitt-trigger': 'schmitt-inverter',
        'bus transceiver': 'bus-transceiver',
        'shift register': 'shift-register',
        'analog switch': 'analog-switch',
        'digital comparator': 'comparator',
        'digital-comparator': 'comparator',
    };
    return aliases[raw] ?? raw;
};
/**
 * カタログ品の機能が検索条件に合うかを判定する。
 * インバータ系の近縁機能をまとめて扱い、戻り値は真偽値で副作用はない。
 */
// 目的: 設計解析ツールのlogic Function Matchesを扱う。機能: 入力値を検証・整形し、画面または計算処理へ渡す。入力: 関数シグネチャの値。出力: 処理結果またはなし。動作条件: 設計解析ツールの初期化後に呼び出す。副作用: Vue状態、localStorage、DOM、HTTP通信のいずれかを更新する場合がある。
const logicFunctionMatches = (itemFunction, selectedFunction) => {
    const requested = normalizeLogicFunction(selectedFunction);
    if (requested === 'any') return true;
    if (requested === 'inverter') return itemFunction === 'inverter' || itemFunction === 'unbuffered-inverter';
    return itemFunction === requested;
};
/**
 * カタログ品へロジックファミリ由来の電源範囲と出力形式を補う。
 * 入力は部品候補、戻り値は補完済みの新規オブジェクトで、副作用はない。
 */
// 目的: 設計解析ツールのwith Logic Part Specを扱う。機能: 入力値を検証・整形し、画面または計算処理へ渡す。入力: 関数シグネチャの値。出力: 処理結果またはなし。動作条件: 設計解析ツールの初期化後に呼び出す。副作用: Vue状態、localStorage、DOM、HTTP通信のいずれかを更新する場合がある。
const withLogicPartSpec = (item) => {
    const spec = LOGIC_SERIES_SPECS[item.family] ?? LOGIC_SERIES_SPECS[item.family === '4000B' ? '4000' : item.family];
    return {
        ...item,
        output: item.output ?? 'push-pull',
        vMin: item.vMin ?? spec?.vMin ?? -Infinity,
        vMax: item.vMax ?? spec?.vMax ?? Infinity,
    };
};
/**
 * 型番重複を除いたロジックIC候補リストを作る。
 * 入力配列の順序を優先し、戻り値はフィルタ済み配列で、副作用はない。
 */
// 目的: 設計解析ツールのunique Logic Partsを扱う。機能: 入力値を検証・整形し、画面または計算処理へ渡す。入力: 関数シグネチャの値。出力: 処理結果またはなし。動作条件: 設計解析ツールの初期化後に呼び出す。副作用: Vue状態、localStorage、DOM、HTTP通信のいずれかを更新する場合がある。
const uniqueLogicParts = (items) => {
    const seen = new Set();
    return items.filter((item) => {
        if (seen.has(item.part)) return false;
        seen.add(item.part);
        return true;
    });
};
/**
 * 送信側/受信側ロジックシリーズのH/Lレベル互換性を判定する。
 * 入力はIF余裕フォーム、戻り値は判定結果オブジェクトで、副作用はない。
 */
// 目的: 設計解析ツールのlogic Level Compatibilityを扱う。機能: 入力値を検証・整形し、画面または計算処理へ渡す。入力: 関数シグネチャの値。出力: 処理結果またはなし。動作条件: 設計解析ツールの初期化後に呼び出す。副作用: Vue状態、localStorage、DOM、HTTP通信のいずれかを更新する場合がある。
const logicLevelCompatibility = (form) => {
    const driver = logicThresholds(form.driverFamily, form.driverVcc);
    const receiver = logicThresholds(form.receiverFamily, form.receiverVcc);
    if (!driver || !receiver) {
        return {
            status: 'check',
            verdict: 'CHECK',
            highMargin: null,
            lowMargin: null,
            inputOvervoltageMargin: null,
            warnings: ['送信側または受信側のロジックシリーズ条件が未定義です。'],
            missingConditions: ['シリーズ電気特性'],
            summary: 'シリーズ間接続条件を判定できません。',
        };
    }
    const highMargin = driver.vohMin - receiver.vihMin;
    const lowMargin = receiver.vilMax - driver.volMax;
    const inputOvervoltageMargin = receiver.inputMax - driver.outputHighMax;
    const warnings = [
        ...(!driver.vccOk ? [`送信側 ${driver.family} のVcc ${driver.vcc}V は推奨範囲 ${driver.vMin}-${driver.vMax}V 外です。`] : []),
        ...(!receiver.vccOk ? [`受信側 ${receiver.family} のVcc ${receiver.vcc}V は推奨範囲 ${receiver.vMin}-${receiver.vMax}V 外です。`] : []),
        ...(highMargin < 0 ? [`High余裕不足: VOH(min) ${driver.vohMin.toFixed(2)}V < VIH(min) ${receiver.vihMin.toFixed(2)}V`] : []),
        ...(lowMargin < 0 ? [`Low余裕不足: VOL(max) ${driver.volMax.toFixed(2)}V > VIL(max) ${receiver.vilMax.toFixed(2)}V`] : []),
        ...(inputOvervoltageMargin < 0 ? [`受信側入力耐圧超過の可能性: 出力High最大 ${driver.outputHighMax.toFixed(2)}V > 入力上限目安 ${receiver.inputMax.toFixed(2)}V`] : []),
        ...(highMargin >= 0 && highMargin < 0.2 ? ['High余裕が0.2V未満です。電源min/max、負荷、温度で再確認してください。'] : []),
        ...(lowMargin >= 0 && lowMargin < 0.2 ? ['Low余裕が0.2V未満です。電源min/max、負荷、温度で再確認してください。'] : []),
    ];
    const missingConditions = ['VOH/VOL測定時のIOH/IOL負荷', '温度範囲', '電源min/max', '立上り/立下り時間', '入力5V tolerantまたは入力クランプ電流', '未使用入力処理'];
    const hardFail = highMargin < 0 || lowMargin < 0 || inputOvervoltageMargin < 0;
    const softWarn = !driver.vccOk || !receiver.vccOk || highMargin < 0.2 || lowMargin < 0.2;
    const status = hardFail ? 'bad' : (softWarn ? 'warn' : 'ok');
    return {
        status,
        verdict: hardFail ? 'FAIL' : (softWarn ? 'WARN' : 'OK'),
        driver,
        receiver,
        highMargin,
        lowMargin,
        inputOvervoltageMargin,
        warnings,
        missingConditions,
        summary: hardFail
            ? `${driver.family} ${driver.vcc}V -> ${receiver.family} ${receiver.vcc}V はレベル条件を満たしません。`
            : `${driver.family} ${driver.vcc}V -> ${receiver.family} ${receiver.vcc}V は概算しきい値上は成立します。`,
    };
};
// 目的: 設計解析ツールのlogic74 Variantsを扱う。機能: 入力値を検証・整形し、画面または計算処理へ渡す。入力: 関数シグネチャの値。出力: 処理結果またはなし。動作条件: 設計解析ツールの初期化後に呼び出す。副作用: Vue状態、localStorage、DOM、HTTP通信のいずれかを更新する場合がある。
const logic74Variants = (variants, series = LOGIC_74_STANDARD_FAMILIES) => variants.flatMap((variant) => (
    (variant.series ?? series).map((family) => ({
        part: `${family}${variant.code}`,
        family,
        function: variant.function,
        inputs: variant.inputs ?? 1,
        gates: variant.gates ?? 1,
        pins: variant.pins ?? 14,
        output: variant.output ?? 'push-pull',
        note: variant.note,
    }))
));
// 目的: 設計解析ツールのlogic Cmos Partsを扱う。機能: 入力値を検証・整形し、画面または計算処理へ渡す。入力: 関数シグネチャの値。出力: 処理結果またはなし。動作条件: 設計解析ツールの初期化後に呼び出す。副作用: Vue状態、localStorage、DOM、HTTP通信のいずれかを更新する場合がある。
const logicCmosParts = (parts) => parts.map((part) => ({
    family: part.family ?? '4000',
    inputs: part.inputs ?? 1,
    gates: part.gates ?? 1,
    pins: part.pins ?? 14,
    output: part.output ?? 'push-pull',
    ...part,
}));
const LOGIC_FUNCTION_DEFS = [
    {
        key: 'nand',
        label: 'NAND',
        variants: [
            { code: '00', inputs: 2, gates: 4, pins: 14, note: 'Quad 2-input NAND' },
            { code: '10', inputs: 3, gates: 3, pins: 14, note: 'Triple 3-input NAND' },
            { code: '20', inputs: 4, gates: 2, pins: 14, note: 'Dual 4-input NAND' },
            { code: '30', inputs: 8, gates: 1, pins: 14, note: '8-input NAND' },
        ],
        cmos: [
            { part: 'CD4011B', family: '4000', inputs: 2, gates: 4, pins: 14, note: 'Quad 2-input NAND' },
            { part: 'CD4012B', family: '4000', inputs: 4, gates: 2, pins: 14, note: 'Dual 4-input NAND' },
            { part: 'CD4023B', family: '4000', inputs: 3, gates: 3, pins: 14, note: 'Triple 3-input NAND' },
            { part: 'CD4093B', family: '4000', inputs: 2, gates: 4, pins: 14, note: 'Schmitt NAND' },
        ],
    },
    {
        key: 'nor',
        label: 'NOR',
        variants: [
            { code: '02', inputs: 2, gates: 4, pins: 14, note: 'Quad 2-input NOR' },
            { code: '27', inputs: 3, gates: 3, pins: 14, note: 'Triple 3-input NOR' },
        ],
        cmos: [
            { part: 'CD4001B', family: '4000', inputs: 2, gates: 4, pins: 14, note: 'Quad 2-input NOR' },
            { part: 'CD4002B', family: '4000', inputs: 4, gates: 2, pins: 14, note: 'Dual 4-input NOR' },
            { part: 'CD4025B', family: '4000', inputs: 3, gates: 3, pins: 14, note: 'Triple 3-input NOR' },
        ],
    },
    {
        key: 'inverter',
        label: 'INV',
        variants: [
            { code: '04', inputs: 1, gates: 6, pins: 14, note: 'Hex inverter' },
            { code: '04', inputs: 1, gates: 6, pins: 14, series: ['74HCU'], note: 'Hex unbuffered inverter' },
            { code: '05', inputs: 1, gates: 6, pins: 14, output: 'open-collector', note: 'Hex inverter open collector/drain' },
        ],
        cmos: [
            { part: 'CD4049B', family: '4000', inputs: 1, gates: 6, pins: 16, note: 'Hex inverting buffer' },
            { part: 'CD4069UB', family: '4000', inputs: 1, gates: 6, pins: 14, note: 'Unbuffered inverter' },
        ],
    },
    {
        key: 'unbuffered-inverter',
        label: 'アンバッファインバータ',
        variants: [{ code: '04', inputs: 1, gates: 6, pins: 14, note: 'Unbuffered inverter', series: ['74HCU'] }],
        cmos: [{ part: 'CD4069UB', family: '4000', inputs: 1, gates: 6, pins: 14, note: 'Unbuffered inverter' }],
    },
    {
        key: 'schmitt-inverter',
        label: 'Schmitt',
        variants: [{ code: '14', inputs: 1, gates: 6, pins: 14, note: 'Hex Schmitt inverter' }],
        cmos: [{ part: 'CD40106B', family: '4000', inputs: 1, gates: 6, pins: 14, note: 'Hex Schmitt trigger' }, { part: 'CD4584B', family: '4500', inputs: 1, gates: 6, pins: 14, note: 'Hex Schmitt inverter' }],
    },
    {
        key: 'and',
        label: 'AND',
        variants: [
            { code: '08', inputs: 2, gates: 4, pins: 14, note: 'Quad 2-input AND' },
            { code: '11', inputs: 3, gates: 3, pins: 14, note: 'Triple 3-input AND' },
            { code: '21', inputs: 4, gates: 2, pins: 14, note: 'Dual 4-input AND' },
        ],
        cmos: [
            { part: 'CD4081B', family: '4000', inputs: 2, gates: 4, pins: 14, note: 'Quad 2-input AND' },
            { part: 'CD4073B', family: '4000', inputs: 3, gates: 3, pins: 14, note: 'Triple 3-input AND' },
        ],
    },
    {
        key: 'or',
        label: 'OR',
        variants: [{ code: '32', inputs: 2, gates: 4, pins: 14, note: 'Quad 2-input OR' }],
        cmos: [
            { part: 'CD4071B', family: '4000', inputs: 2, gates: 4, pins: 14, note: 'Quad 2-input OR' },
            { part: 'CD4072B', family: '4000', inputs: 4, gates: 2, pins: 14, note: 'Dual 4-input OR' },
            { part: 'CD4075B', family: '4000', inputs: 3, gates: 3, pins: 14, note: 'Triple 3-input OR' },
        ],
    },
    {
        key: 'xor',
        label: 'XOR',
        variants: [{ code: '86', inputs: 2, gates: 4, pins: 14, note: 'Quad XOR' }],
        cmos: [{ part: 'CD4070B', family: '4000', inputs: 2, gates: 4, pins: 14, note: 'Quad XOR' }, { part: 'CD4030B', family: '4000', inputs: 2, gates: 4, pins: 14, note: 'Quad XOR' }],
    },
    {
        key: 'xnor',
        label: 'XNOR',
        variants: [{ code: '266', inputs: 2, gates: 4, pins: 14, output: 'open-collector', note: 'Quad XNOR open collector/drain' }],
        cmos: [{ part: 'CD4077B', family: '4000', inputs: 2, gates: 4, pins: 14, note: 'Quad XNOR' }],
    },
    {
        key: 'buffer',
        label: 'バッファ/ドライバ',
        variants: [
            { code: '07', inputs: 1, gates: 6, pins: 14, output: 'open-collector', note: 'Hex buffer open collector/drain' },
            { code: '34', inputs: 1, gates: 6, pins: 14, note: 'Hex buffer' },
            { code: '125', inputs: 1, gates: 4, pins: 14, output: '3state', note: 'Quad 3-state buffer, active-low OE' },
            { code: '126', inputs: 1, gates: 4, pins: 14, output: '3state', note: 'Quad 3-state buffer, active-high OE' },
            { code: '240', inputs: 1, gates: 8, pins: 20, output: '3state', note: 'Octal inverting buffer/line driver' },
            { code: '244', inputs: 1, gates: 8, pins: 20, output: '3state', note: 'Octal buffer/line driver' },
            { code: '541', inputs: 1, gates: 8, pins: 20, output: '3state', note: 'Octal buffer, flow-through pinout' },
        ],
        cmos: [{ part: 'CD4050B', family: '4000', inputs: 1, gates: 6, pins: 16, note: 'Hex non-inverting buffer' }],
    },
    {
        key: 'bus-transceiver',
        label: 'バストランシーバ',
        variants: [
            { code: '245', inputs: 1, gates: 8, pins: 20, output: '3state', note: 'Octal bus transceiver' },
            { code: '640', inputs: 1, gates: 8, pins: 20, output: '3state', note: 'Octal inverting bus transceiver' },
            { code: '646', inputs: 1, gates: 8, pins: 24, output: '3state', note: 'Registered bus transceiver' },
        ],
        cmos: [],
    },
    {
        key: 'd-ff',
        label: 'D-FF',
        variants: [
            { code: '74', inputs: 1, gates: 2, pins: 14, note: 'Dual D flip-flop' },
            { code: '174', inputs: 1, gates: 6, pins: 16, note: 'Hex D flip-flop' },
            { code: '175', inputs: 1, gates: 4, pins: 16, note: 'Quad D flip-flop' },
            { code: '273', inputs: 1, gates: 8, pins: 20, note: 'Octal D flip-flop with clear' },
            { code: '374', inputs: 1, gates: 8, pins: 20, output: '3state', note: 'Octal D flip-flop 3-state' },
            { code: '574', inputs: 1, gates: 8, pins: 20, output: '3state', note: 'Octal D flip-flop flow-through' },
        ],
        cmos: [{ part: 'CD4013B', family: '4000', inputs: 1, gates: 2, pins: 14, note: 'Dual D flip-flop' }],
    },
    {
        key: 'jk-ff',
        label: 'JK-FF',
        variants: [
            { code: '73', inputs: 2, gates: 2, pins: 14, note: 'Dual JK flip-flop' },
            { code: '76', inputs: 2, gates: 2, pins: 16, note: 'Dual JK flip-flop preset/clear' },
            { code: '112', inputs: 2, gates: 2, pins: 16, note: 'Dual negative-edge JK flip-flop' },
        ],
        cmos: [{ part: 'CD4027B', family: '4000', inputs: 2, gates: 2, pins: 16, note: 'Dual JK flip-flop' }],
    },
    {
        key: 'latch',
        label: 'ラッチ',
        variants: [
            { code: '75', inputs: 1, gates: 4, pins: 16, note: '4-bit bistable latch' },
            { code: '373', inputs: 1, gates: 8, pins: 20, output: '3state', note: 'Octal transparent latch' },
            { code: '573', inputs: 1, gates: 8, pins: 20, output: '3state', note: 'Octal transparent latch flow-through' },
        ],
        cmos: [{ part: 'CD4042B', family: '4000', inputs: 1, gates: 4, pins: 16, note: 'Quad clocked D latch' }, { part: 'CD4508B', family: '4500', inputs: 1, gates: 8, pins: 24, note: 'Dual 4-bit latch' }],
    },
    {
        key: 'counter',
        label: 'カウンタ',
        variants: [
            { code: '90', inputs: 1, gates: 1, pins: 14, note: 'Decade counter' },
            { code: '93', inputs: 1, gates: 1, pins: 14, note: '4-bit binary counter' },
            { code: '160', inputs: 1, gates: 1, pins: 16, note: 'Sync decade counter' },
            { code: '161', inputs: 1, gates: 1, pins: 16, note: 'Sync binary counter' },
            { code: '163', inputs: 1, gates: 1, pins: 16, note: 'Sync binary counter clear' },
            { code: '190', inputs: 1, gates: 1, pins: 16, note: 'Up/down decade counter' },
            { code: '191', inputs: 1, gates: 1, pins: 16, note: 'Up/down binary counter' },
            { code: '390', inputs: 1, gates: 2, pins: 16, note: 'Dual decade ripple counter' },
            { code: '393', inputs: 1, gates: 2, pins: 14, note: 'Dual 4-bit ripple counter' },
        ],
        cmos: [
            { part: 'CD4017B', family: '4000', inputs: 1, gates: 1, pins: 16, note: 'Decade counter/divider' },
            { part: 'CD4020B', family: '4000', inputs: 1, gates: 1, pins: 16, note: '14-stage ripple counter' },
            { part: 'CD4024B', family: '4000', inputs: 1, gates: 1, pins: 14, note: '7-stage ripple counter' },
            { part: 'CD4040B', family: '4000', inputs: 1, gates: 1, pins: 16, note: '12-stage ripple counter' },
            { part: 'CD4518B', family: '4500', inputs: 1, gates: 2, pins: 16, note: 'Dual BCD counter' },
            { part: 'CD4520B', family: '4500', inputs: 1, gates: 2, pins: 16, note: 'Dual binary counter' },
            { part: 'MC14520B', family: '5000', inputs: 1, gates: 2, pins: 16, note: 'Dual binary counter' },
        ],
    },
    {
        key: 'shift-register',
        label: 'シフトレジスタ',
        variants: [
            { code: '164', inputs: 1, gates: 1, pins: 14, note: '8-bit SIPO shift register' },
            { code: '165', inputs: 1, gates: 1, pins: 16, note: '8-bit PISO shift register' },
            { code: '194', inputs: 1, gates: 1, pins: 16, note: '4-bit bidirectional universal shift register' },
            { code: '595', inputs: 1, gates: 1, pins: 16, output: '3state', note: '8-bit SIPO register with output latch' },
            { code: '597', inputs: 1, gates: 1, pins: 16, note: '8-bit PISO shift register' },
        ],
        cmos: [
            { part: 'CD4015B', family: '4000', inputs: 1, gates: 2, pins: 16, note: 'Dual 4-stage shift register' },
            { part: 'CD4021B', family: '4000', inputs: 1, gates: 1, pins: 16, note: '8-stage static shift register' },
            { part: 'CD4094B', family: '4000', inputs: 1, gates: 1, pins: 16, output: '3state', note: '8-stage shift-and-store bus register' },
        ],
    },
    {
        key: 'decoder',
        label: 'デコーダ/デマルチ',
        variants: [
            { code: '42', inputs: 4, gates: 1, pins: 16, note: 'BCD to decimal decoder' },
            { code: '138', inputs: 3, gates: 1, pins: 16, note: '3-to-8 decoder/demux' },
            { code: '139', inputs: 2, gates: 2, pins: 16, note: 'Dual 2-to-4 decoder/demux' },
            { code: '154', inputs: 4, gates: 1, pins: 24, note: '4-to-16 decoder/demux' },
        ],
        cmos: [
            { part: 'CD4028B', family: '4000', inputs: 4, gates: 1, pins: 16, note: 'BCD to decimal decoder' },
            { part: 'CD4511B', family: '4500', inputs: 4, gates: 1, pins: 16, note: 'BCD to 7-seg latch/decoder/driver' },
            { part: 'CD4543B', family: '4500', inputs: 4, gates: 1, pins: 16, note: 'BCD to 7-seg latch/decoder/driver' },
            { part: 'MC14511B', family: '5000', inputs: 4, gates: 1, pins: 16, note: 'BCD to 7-seg decoder' },
        ],
    },
    {
        key: 'encoder',
        label: 'エンコーダ',
        variants: [
            { code: '147', inputs: 10, gates: 1, pins: 16, note: '10-to-4 priority encoder' },
            { code: '148', inputs: 8, gates: 1, pins: 16, note: '8-to-3 priority encoder' },
        ],
        cmos: [{ part: 'CD4532B', family: '4500', inputs: 8, gates: 1, pins: 16, note: '8-bit priority encoder' }],
    },
    {
        key: 'mux',
        label: 'MUX/セレクタ',
        variants: [
            { code: '151', inputs: 8, gates: 1, pins: 16, note: '8-to-1 data selector' },
            { code: '153', inputs: 4, gates: 2, pins: 16, note: 'Dual 4-to-1 data selector' },
            { code: '157', inputs: 2, gates: 4, pins: 16, note: 'Quad 2-to-1 data selector' },
            { code: '158', inputs: 2, gates: 4, pins: 16, note: 'Quad 2-to-1 inverting selector' },
            { code: '251', inputs: 8, gates: 1, pins: 16, output: '3state', note: '8-to-1 selector 3-state' },
            { code: '257', inputs: 2, gates: 4, pins: 16, output: '3state', note: 'Quad 2-to-1 selector 3-state' },
        ],
        cmos: [
            { part: 'CD4051B', family: '4000', inputs: 3, gates: 1, pins: 16, output: 'analog-switch', note: '8ch analog mux/demux' },
            { part: 'CD4052B', family: '4000', inputs: 2, gates: 2, pins: 16, output: 'analog-switch', note: 'Dual 4ch analog mux/demux' },
            { part: 'CD4053B', family: '4000', inputs: 1, gates: 3, pins: 16, output: 'analog-switch', note: 'Triple 2ch analog mux/demux' },
        ],
    },
    {
        key: 'analog-switch',
        label: 'アナログSW',
        variants: [],
        cmos: [
            { part: 'CD4016B', family: '4000', inputs: 1, gates: 4, pins: 14, output: 'analog-switch', note: 'Quad bilateral switch' },
            { part: 'CD4066B', family: '4000', inputs: 1, gates: 4, pins: 14, output: 'analog-switch', note: 'Quad bilateral switch' },
            { part: 'CD4051B', family: '4000', inputs: 3, gates: 1, pins: 16, output: 'analog-switch', note: '8ch analog mux/demux' },
            { part: 'CD4052B', family: '4000', inputs: 2, gates: 2, pins: 16, output: 'analog-switch', note: 'Dual 4ch analog mux/demux' },
            { part: 'CD4053B', family: '4000', inputs: 1, gates: 3, pins: 16, output: 'analog-switch', note: 'Triple 2ch analog mux/demux' },
            { part: 'MC14551B', family: '5000', inputs: 2, gates: 2, pins: 16, output: 'analog-switch', note: 'Dual 4ch analog mux' },
        ],
    },
    {
        key: 'adder',
        label: '加算器',
        variants: [{ code: '83', inputs: 4, gates: 1, pins: 16, note: '4-bit binary full adder' }, { code: '283', inputs: 4, gates: 1, pins: 16, note: '4-bit binary full adder' }],
        cmos: [{ part: 'CD4008B', family: '4000', inputs: 4, gates: 1, pins: 16, note: '4-bit full adder' }],
    },
    {
        key: 'comparator',
        label: 'デジタル比較器',
        variants: [{ code: '85', inputs: 4, gates: 1, pins: 16, note: '4-bit magnitude comparator' }, { code: '688', inputs: 8, gates: 1, pins: 20, output: 'open-collector', note: '8-bit identity comparator' }],
        cmos: [{ part: 'CD4063B', family: '4000', inputs: 4, gates: 1, pins: 16, note: '4-bit magnitude comparator' }],
    },
    {
        key: 'parity',
        label: 'パリティ',
        variants: [{ code: '280', inputs: 9, gates: 1, pins: 14, note: '9-bit parity generator/checker' }],
        cmos: [],
    },
    {
        key: 'monostable',
        label: 'モノステーブル',
        variants: [
            { code: '121', inputs: 1, gates: 1, pins: 14, note: 'Monostable multivibrator' },
            { code: '123', inputs: 1, gates: 2, pins: 16, note: 'Dual retriggerable monostable' },
            { code: '221', inputs: 1, gates: 2, pins: 16, note: 'Dual monostable' },
        ],
        cmos: [{ part: 'CD4528B', family: '4500', inputs: 1, gates: 2, pins: 16, note: 'Dual monostable' }, { part: 'CD4538B', family: '4500', inputs: 1, gates: 2, pins: 16, note: 'Precision dual monostable' }],
    },
    {
        key: 'pll',
        label: 'PLL/VCO',
        variants: [],
        cmos: [{ part: 'CD4046B', family: '4000', inputs: 1, gates: 1, pins: 16, output: 'mixed', note: 'PLL with VCO' }],
    },
];
const LOGIC_FUNCTION_OPTIONS = [
    ['any', '指定なし'],
    ...LOGIC_FUNCTION_DEFS.map((item) => [item.key, item.label]),
];
const logicCatalog = LOGIC_FUNCTION_DEFS.flatMap((definition) => [
    ...logic74Variants(
        definition.variants.map((variant) => ({ ...variant, function: definition.key, note: variant.note || definition.label })),
        undefined
    ).filter((item) => {
        const variant = definition.variants.find((candidate) => candidate.code === item.part.replace(item.family, ''));
        return !variant?.series || variant.series.includes(item.family);
    }),
    ...logicCmosParts(definition.cmos.map((item) => ({ ...item, function: definition.key, note: item.note || definition.label }))),
]);



    return {
        LOGIC_CONNECTION_FAMILY_OPTIONS,
        LOGIC_ALL_FAMILY_OPTIONS,
        LOGIC_OUTPUT_OPTIONS,
        LOGIC_FUNCTION_OPTIONS,
        logicLevelCompatibility,
        uniqueLogicParts,
        logicCatalog,
        withLogicPartSpec,
        logicFamilyMatches,
        logicFunctionMatches,
    };
}
