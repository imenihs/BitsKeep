import { computed, reactive, watch } from 'vue';

// 保存/復元対象のツールIDをAPI入力検証とフロント表示で共有する。
export const SAVED_ANALYSIS_TOOL_IDS = [
    'network-search',
    'divider-design',
    'variable-resistor',
    'eia96',
    'adc',
    'cap-life',
    'divider',
    'shunt',
    'power',
    'battery-runtime',
    'comparator',
    'thermal',
    'interface',
    'tolerance',
    'bode',
    'ovp',
    'tvs',
    'fuse',
    'polyfuse',
    'protection',
    'logic-ic',
    'connector',
    'cable',
    'jumper',
    'startup',
];

/**
 * reactiveをAPI保存用の素のJSONへ変換する。
 * @param {unknown} value 変換対象。
 * @returns {unknown} JSON互換の複製値。
 * @sideEffects なし。
 */
// 目的: 設計解析ツールのclone Plainを扱う。機能: 入力値を検証・整形し、画面または計算処理へ渡す。入力: 関数シグネチャの値。出力: 処理結果またはなし。動作条件: 設計解析ツールの初期化後に呼び出す。副作用: Vue状態、localStorage、DOM、HTTP通信のいずれかを更新する場合がある。
const clonePlain = (value) => JSON.parse(JSON.stringify(value));

/**
 * Laravel APIのdata包みと素配列の両方から一覧を取り出す。
 * @param {object|Array} data APIレスポンスJSON。
 * @returns {Array} 一覧配列。形式不一致時は空配列。
 * @sideEffects なし。
 */
// 目的: 設計解析ツールのextract Api Listを扱う。機能: 入力値を検証・整形し、画面または計算処理へ渡す。入力: 関数シグネチャの値。出力: 表示値、配列、オブジェクト、数値のいずれか。動作条件: 設計解析ツールの初期化後に呼び出す。副作用: なし。
const extractApiList = (data) => {
    const payload = data?.data ?? data;
    if (Array.isArray(payload)) return payload;
    if (Array.isArray(payload?.data)) return payload.data;
    return [];
};

/**
 * 差分表示用に長すぎる値を短縮する。
 * @param {unknown} value 入力値。
 * @returns {string} 画面へ出せる短縮表記。
 * @sideEffects なし。
 */
// 目的: 設計解析ツールのpreview Valueを扱う。機能: 入力値を検証・整形し、画面または計算処理へ渡す。入力: 関数シグネチャの値。出力: 処理結果またはなし。動作条件: 設計解析ツールの初期化後に呼び出す。副作用: Vue状態、localStorage、DOM、HTTP通信のいずれかを更新する場合がある。
const previewValue = (value) => {
    if (value === undefined) return '未設定';
    if (value === null) return 'null';
    const raw = typeof value === 'string' ? value : JSON.stringify(value);
    return raw.length > 64 ? `${raw.slice(0, 61)}...` : raw;
};

/**
 * 保存済み入力と現在入力の変更点を最大12件へ要約する。
 * @param {object} previous 保存済み入力。
 * @param {object} current 現在入力。
 * @returns {string[]} 差分行。
 * @sideEffects なし。
 */
// 目的: 設計解析ツールのdiff Payloadを扱う。機能: 入力値を検証・整形し、画面または計算処理へ渡す。入力: 関数シグネチャの値。出力: 処理結果またはなし。動作条件: 設計解析ツールの初期化後に呼び出す。副作用: Vue状態、localStorage、DOM、HTTP通信のいずれかを更新する場合がある。
const diffPayload = (previous, current) => {
    const keys = Array.from(new Set([...Object.keys(previous ?? {}), ...Object.keys(current ?? {})]));
    return keys
        .filter((key) => JSON.stringify(previous?.[key]) !== JSON.stringify(current?.[key]))
        .slice(0, 12)
        .map((key) => `${key}: ${previewValue(previous?.[key])} -> ${previewValue(current?.[key])}`);
};

/**
 * 案件/部品IDとして扱える正整数だけを正規化する。
 * @param {unknown} value ID候補。
 * @returns {number|null} 正規化ID、または無効時null。
 * @sideEffects なし。
 */
// 目的: 設計解析ツールのnormalize Idを扱う。機能: 入力値を検証・整形し、画面または計算処理へ渡す。入力: 関数シグネチャの値。出力: 表示値、配列、オブジェクト、数値のいずれか。動作条件: 設計解析ツールの初期化後に呼び出す。副作用: なし。
const normalizeId = (value) => {
    const id = Number(value);
    return Number.isInteger(id) && id > 0 ? id : null;
};

/**
 * 案件名が未展開のセッションへ最低限の表示名を補う。
 * @param {unknown} id 案件ID候補。
 * @returns {{id: number, name: string}|null} 表示用案件情報。
 * @sideEffects なし。
 */
// 目的: 設計解析ツールのproject Fallbackを扱う。機能: 入力値を検証・整形し、画面または計算処理へ渡す。入力: 関数シグネチャの値。出力: 処理結果またはなし。動作条件: 設計解析ツールの初期化後に呼び出す。副作用: Vue状態、localStorage、DOM、HTTP通信のいずれかを更新する場合がある。
const projectFallback = (id) => (normalizeId(id) ? { id: normalizeId(id), name: `案件 #${normalizeId(id)}` } : null);

/**
 * 部品名が未展開のセッションへ最低限の表示名を補う。
 * @param {unknown} id 部品ID候補。
 * @returns {{id: number, part_number: string}|null} 表示用部品情報。
 * @sideEffects なし。
 */
// 目的: 設計解析ツールのcomponent Fallbackを扱う。機能: 入力値を検証・整形し、画面または計算処理へ渡す。入力: 関数シグネチャの値。出力: 処理結果またはなし。動作条件: 設計解析ツールの初期化後に呼び出す。副作用: Vue状態、localStorage、DOM、HTTP通信のいずれかを更新する場合がある。
const componentFallback = (id) => (normalizeId(id) ? { id: normalizeId(id), part_number: `部品 #${normalizeId(id)}` } : null);

/**
 * 設計解析ツールの保存、検索紐づけ、セッション一覧、復元、削除をまとめる。
 * @param {object} deps 画面状態、計算レポート、API/数値変換ヘルパー、各ツール入力。
 * @returns {object} 保存フォーム状態、候補検索、保存/復元/削除操作、テンプレート操作。
 * @sideEffects 解析セッションAPIへfetchし、ユーザー操作時にactiveToolIdや各ツール入力を復元する。
 */
// 目的: 設計解析ツールのsetup Analysis Sessionsを扱う。機能: 入力値を検証・整形し、画面または計算処理へ渡す。入力: 関数シグネチャの値。出力: 処理結果またはなし。動作条件: 設計解析ツールの初期化後に呼び出す。副作用: Vue状態、localStorage、DOM、HTTP通信のいずれかを更新する場合がある。
export default function setupAnalysisSessions({
    activeToolId,
    activeTool,
    analysisReport,
    currentInputPayload,
    normalizeToolId,
    setToolGroup,
    toolGroupForToolId,
    applyToolPayload,
    apiJson,
    toFinite,
    parseNumber,
    unitMultiplier,
    formatResistanceValue,
    normalizeEngineeringText,
    adc,
    cap,
    divider,
    eia96,
    shunt,
    power,
    comp,
    thermal,
    iface,
}) {
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
            payload: { supply_w: 10, efficiencyPct: 85, dropoutV: 0.3, inrushA: 1.5, maxLoadFactor: 1.5, rails: 'VIN,,12,2\n3V3,VIN,3.3,0.4', loads: [{ label: 'MCU', mA: 80, V: 3.3, rail: '3V3' }, { label: 'Sensor', mA: 20, V: 3.3, rail: '3V3' }] },
        },
        {
            id: 'i2c-bus',
            label: 'I2Cバス',
            tool_id: 'interface',
            title: 'テンプレート I2Cバス余裕',
            payload: { VOH: 2.4, VOL: 0.4, VIH: 2.0, VIL: 0.8, driverVcc: 3.3, receiverVcc: 3.3, uartNominalBaud: 115200, uartActualBaud: 115200, i2cBusCapPf: 200, i2cRiseNsLimit: 300, pullupOhm: 2200, i2cSinkMaLimit: 3, tempMin: -40, tempMax: 85 },
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

    const outputSave = reactive({
        title: '',
        project: null,
        projectId: '',
        component: null,
        componentId: '',
        bomLineKey: '',
        saving: false,
        status: '',
        message: '',
        error: '',
    });

    const componentSelection = reactive({
        query: '',
        loading: false,
        open: false,
        options: [],
        selected: null,
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

    const savedAnalysis = reactive({
        loading: false,
        status: '',
        message: '',
        error: '',
        sessions: [],
        diff: null,
        selectedId: null,
        deleteTarget: null,
        deletingId: null,
    });

    let componentSearchTimer = null;

    // 案件選択オブジェクトの変更を保存payload用IDへ同期する。入力は選択済み案件で、outputSave.projectIdを更新する副作用がある。
    watch(() => outputSave.project, (project) => {
        outputSave.projectId = project?.id ? String(project.id) : '';
    }, { flush: 'sync' });

    /**
     * 現在選択中の解析テンプレートを解決する。
     * templateState.selectedを条件にし、戻り値はテンプレート定義で、副作用はない。
     */
    const selectedTemplate = computed(() => analysisTemplates.find((item) => item.id === templateState.selected) ?? analysisTemplates[0]);

    /**
     * 部品候補を選択UI用の1行ラベルへ変換する。
     * 入力は部品オブジェクト、戻り値は型番/通称/メーカーの結合文字列で、副作用はない。
     */
    // 目的: 設計解析ツールのcomponent Labelを扱う。機能: 入力値を検証・整形し、画面または計算処理へ渡す。入力: 関数シグネチャの値。出力: 処理結果またはなし。動作条件: 設計解析ツールの初期化後に呼び出す。副作用: Vue状態、localStorage、DOM、HTTP通信のいずれかを更新する場合がある。
    const componentLabel = (component) => {
        if (!component) return '';
        return [component.part_number, component.common_name, component.manufacturer].filter(Boolean).join(' / ') || `部品 #${component.id}`;
    };

    /** 保存済み解析の案件表示名を返す。案件が未展開ならID表示へフォールバックし、副作用はない。 */
    // 目的: 設計解析ツールのsession Project Labelを扱う。機能: 入力値を検証・整形し、画面または計算処理へ渡す。入力: 関数シグネチャの値。出力: 処理結果またはなし。動作条件: 設計解析ツールの初期化後に呼び出す。副作用: Vue状態、localStorage、DOM、HTTP通信のいずれかを更新する場合がある。
    const sessionProjectLabel = (session) => session?.project?.name ?? (session?.project_id ? `案件 #${session.project_id}` : '未紐づけ');

    /** 保存済み解析の部品表示名を返す。部品が未展開ならID表示へフォールバックし、副作用はない。 */
    // 目的: 設計解析ツールのsession Component Labelを扱う。機能: 入力値を検証・整形し、画面または計算処理へ渡す。入力: 関数シグネチャの値。出力: 処理結果またはなし。動作条件: 設計解析ツールの初期化後に呼び出す。副作用: Vue状態、localStorage、DOM、HTTP通信のいずれかを更新する場合がある。
    const sessionComponentLabel = (session) => componentLabel(session?.component) || (session?.component_id ? `部品 #${session.component_id}` : '未紐づけ');

    /**
     * 保存済み解析の更新日時を日本語ロケールの短い日時へ整形する。
     * 入力はセッション、戻り値は表示文字列で、副作用はない。
     */
    // 目的: 設計解析ツールのsession Updated At Labelを扱う。機能: 入力値を検証・整形し、画面または計算処理へ渡す。入力: 関数シグネチャの値。出力: 処理結果またはなし。動作条件: 設計解析ツールの初期化後に呼び出す。副作用: Vue状態、localStorage、DOM、HTTP通信のいずれかを更新する場合がある。
    const sessionUpdatedAtLabel = (session) => {
        const value = session?.updated_at ?? session?.created_at;
        if (!value) return '-';
        const date = new Date(value);
        if (Number.isNaN(date.getTime())) return String(value);
        return date.toLocaleString('ja-JP', { year: 'numeric', month: '2-digit', day: '2-digit', hour: '2-digit', minute: '2-digit' });
    };

    /** スペック検索用に名称と単位を小文字文字列へまとめる。入力スペックは変更しない。 */
    // 目的: 設計解析ツールのspec Labelを扱う。機能: 入力値を検証・整形し、画面または計算処理へ渡す。入力: 関数シグネチャの値。出力: 処理結果またはなし。動作条件: 設計解析ツールの初期化後に呼び出す。副作用: Vue状態、localStorage、DOM、HTTP通信のいずれかを更新する場合がある。
    const specLabel = (spec) => `${spec?.spec_type?.name ?? spec?.specType?.name ?? ''} ${spec?.name ?? ''} ${spec?.unit ?? ''} ${spec?.normalized_unit ?? ''}`.toLowerCase();

    /**
     * 部品スペックから計算に使える数値を取り出す。
     * 正規化済み数値を優先し、文字列値は単位係数を条件に換算して、副作用はない。
     */
    // 目的: 設計解析ツールのspec Numberを扱う。機能: 入力値を検証・整形し、画面または計算処理へ渡す。入力: 関数シグネチャの値。出力: 処理結果またはなし。動作条件: 設計解析ツールの初期化後に呼び出す。副作用: Vue状態、localStorage、DOM、HTTP通信のいずれかを更新する場合がある。
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
            const raw = normalizeEngineeringText(candidate);
            const value = parseNumber(raw, Number.NaN);
            const hasExplicitUnit = /[A-Za-zΩohm%]/u.test(raw.replace(/[-+]?(?:\d+\.?\d*|\.\d+)(?:e[-+]?\d+)?/iu, ''));
            if (Number.isFinite(value)) return hasExplicitUnit ? value : value * unitMultiplier(spec);
        }
        return null;
    };

    /**
     * 部品内スペックから指定パターンに合う数値を検索する。
     * 入力は部品・検索語配列・倍率、戻り値は数値またはnullで、副作用はない。
     */
    // 目的: 設計解析ツールのfind Spec Valueを扱う。機能: 入力値を検証・整形し、画面または計算処理へ渡す。入力: 関数シグネチャの値。出力: 表示値、配列、オブジェクト、数値のいずれか。動作条件: 設計解析ツールの初期化後に呼び出す。副作用: なし。
    const findSpecValue = (component, patterns, scale = 1) => {
        const specs = component?.specs ?? [];
        const hit = specs.find((spec) => patterns.some((pattern) => specLabel(spec).includes(pattern)));
        const value = hit ? specNumber(hit) : null;
        return value === null ? null : value * scale;
    };

    /**
     * 選択中ツールに応じて登録部品のスペック値を入力欄へ反映する。
     * 入力は部品詳細、戻り値は反映項目の説明配列で、各ツールのreactive状態を更新する副作用がある。
     */
    // 目的: 設計解析ツールのapply Component Specsを扱う。機能: 入力値を検証・整形し、画面または計算処理へ渡す。入力: 関数シグネチャの値。出力: 処理結果またはなし。動作条件: 設計解析ツールの初期化後に呼び出す。副作用: Vue状態、localStorage、DOM、HTTP通信のいずれかを更新する場合がある。
    const applyComponentSpecs = (component) => {
        const applied = [];
        // 読み取れた数値だけを対象ツール入力へ代入する。targetを更新し、appliedへ表示ログを追加する。
        // 目的: 設計解析ツールのapply Numberを扱う。機能: 入力値を検証・整形し、画面または計算処理へ渡す。入力: 関数シグネチャの値。出力: 処理結果またはなし。動作条件: 設計解析ツールの初期化後に呼び出す。副作用: Vue状態、localStorage、DOM、HTTP通信のいずれかを更新する場合がある。
        const applyNumber = (target, key, value, label) => {
            if (value === null || !Number.isFinite(value)) return;
            target[key] = value;
            applied.push(`${label}: ${value}`);
        };

        if (activeToolId.value === 'adc') {
            applyNumber(adc, 'vref', findSpecValue(component, ['vref', 'reference', '基準電圧']), 'Vref');
            applyNumber(adc, 'vinTyp', findSpecValue(component, ['input voltage', '入力電圧', 'vin']), '入力電圧 標準');
        } else if (activeToolId.value === 'cap-life') {
            applyNumber(cap, 'Vr', findSpecValue(component, ['rated voltage', '耐圧', '定格電圧']), '定格電圧');
            applyNumber(cap, 'esr', findSpecValue(component, ['esr']), 'ESR');
            applyNumber(cap, 'rippleCurrent', findSpecValue(component, ['ripple', 'リプル']), 'リプル電流');
        } else if (activeToolId.value === 'divider') {
            const resistance = findSpecValue(component, ['resistance', '抵抗']);
            applyNumber(divider, 'R0', resistance, '基準抵抗');
        } else if (activeToolId.value === 'eia96') {
            const resistance = findSpecValue(component, ['resistance', '抵抗']);
            if (resistance !== null && Number.isFinite(resistance)) {
                eia96.valueQuery = formatResistanceValue(resistance);
                applied.push(`抵抗値: ${eia96.valueQuery}`);
            }
        } else if (activeToolId.value === 'shunt') {
            applyNumber(shunt, 'Rs', findSpecValue(component, ['resistance', '抵抗']), 'Rs');
            applyNumber(shunt, 'powerRating', findSpecValue(component, ['power', '定格電力']), '電力定格');
        } else if (activeToolId.value === 'power') {
            applyNumber(power, 'supply_w', findSpecValue(component, ['power', '電力']), '供給電力');
            applyNumber(power, 'dropoutV', findSpecValue(component, ['dropout']), 'ドロップアウト');
        } else if (activeToolId.value === 'comparator') {
            applyNumber(comp, 'Vcc', findSpecValue(component, ['supply voltage', '電源電圧', 'vcc']), 'Vcc');
            applyNumber(comp, 'inputOffsetMv', findSpecValue(component, ['offset', 'オフセット'], 1000), '入力オフセット');
        } else if (activeToolId.value === 'thermal') {
            applyNumber(thermal, 'P', findSpecValue(component, ['power dissipation', '消費電力', '損失']), '発熱');
            applyNumber(thermal, 'TjLimit', findSpecValue(component, ['junction', 'tj']), 'Tj上限');
        } else if (activeToolId.value === 'interface') {
            const vcc = findSpecValue(component, ['supply voltage', '電源電圧', 'vcc']);
            applyNumber(iface, 'driverVcc', vcc, '送信側Vcc');
            applyNumber(iface, 'receiverVcc', vcc, '受信側Vcc');
            applyNumber(iface, 'VOH', findSpecValue(component, ['voh']), 'VOH');
            applyNumber(iface, 'VOL', findSpecValue(component, ['vol']), 'VOL');
        }

        return applied;
    };

    /**
     * 検索候補から部品を選び、保存対象と検索欄へ反映する。
     * 入力は部品候補、戻り値はなく、componentSelectionとoutputSaveを更新する副作用がある。
     */
    // 目的: 設計解析ツールのselect Component Candidateを扱う。機能: 入力値を検証・整形し、画面または計算処理へ渡す。入力: 関数シグネチャの値。出力: 処理結果またはなし。動作条件: 設計解析ツールの初期化後に呼び出す。副作用: Vue状態、localStorage、DOM、HTTP通信のいずれかを更新する場合がある。
    const selectComponentCandidate = (component) => {
        componentSelection.selected = component;
        componentSelection.query = componentLabel(component);
        componentSelection.open = false;
        outputSave.component = component;
        outputSave.componentId = component?.id ? String(component.id) : '';
    };

    /**
     * 部品選択と取り込み結果を初期状態へ戻す。
     * 入力は不要、戻り値はなく、検索欄・保存対象・反映ログを消す副作用がある。
     */
    // 目的: 設計解析ツールのclear Component Selectionを扱う。機能: 入力値を検証・整形し、画面または計算処理へ渡す。入力: 関数シグネチャの値。出力: 処理結果またはなし。動作条件: 設計解析ツールの初期化後に呼び出す。副作用: Vue状態、localStorage、DOM、HTTP通信のいずれかを更新する場合がある。
    const clearComponentSelection = () => {
        componentSelection.selected = null;
        componentSelection.query = '';
        componentSelection.options = [];
        outputSave.component = null;
        outputSave.componentId = '';
        componentImport.component = null;
        componentImport.applied = [];
    };

    /**
     * 部品検索APIから候補一覧を取得する。
     * componentSelection.queryを入力条件にし、候補配列とエラー状態を更新する副作用がある。
     */
    // 目的: 設計解析ツールのsearch Component Candidatesを扱う。機能: 入力値を検証・整形し、画面または計算処理へ渡す。入力: 関数シグネチャの値。出力: 処理結果またはなし。動作条件: 設計解析ツールの初期化後に呼び出す。副作用: Vue状態、localStorage、DOM、HTTP通信のいずれかを更新する場合がある。
    const searchComponentCandidates = async () => {
        componentSelection.loading = true;
        componentSelection.error = '';
        try {
            const params = new URLSearchParams({ per_page: '8' });
            if (componentSelection.query.trim()) params.set('q', componentSelection.query.trim());
            const data = await apiJson(`/api/components?${params.toString()}`);
            componentSelection.options = extractApiList(data);
            componentSelection.open = true;
        } catch (error) {
            componentSelection.options = [];
            componentSelection.error = error?.message || '部品候補の取得に失敗しました。';
        } finally {
            componentSelection.loading = false;
        }
    };

    /**
     * 部品検索欄の入力変更を処理する。
     * 既存選択を解除し、250ms後に候補検索を予約する副作用がある。
     */
    // 目的: 設計解析ツールのon Component Search Inputを扱う。機能: 入力値を検証・整形し、画面または計算処理へ渡す。入力: 関数シグネチャの値。出力: 処理結果またはなし。動作条件: 設計解析ツールの初期化後に呼び出す。副作用: Vue状態、localStorage、DOM、HTTP通信のいずれかを更新する場合がある。
    const onComponentSearchInput = () => {
        outputSave.component = null;
        outputSave.componentId = '';
        componentSelection.selected = null;
        componentSelection.open = true;
        clearTimeout(componentSearchTimer);
        componentSearchTimer = setTimeout(searchComponentCandidates, 250);
    };

    /**
     * 部品候補ドロップダウンを開く。
     * 候補が未取得なら検索APIを呼び、componentSelection.openを更新する副作用がある。
     */
    // 目的: 設計解析ツールのopen Component Searchを扱う。機能: 入力値を検証・整形し、画面または計算処理へ渡す。入力: 関数シグネチャの値。出力: 処理結果またはなし。動作条件: 設計解析ツールの初期化後に呼び出す。副作用: Vue状態、localStorage、DOM、HTTP通信のいずれかを更新する場合がある。
    const openComponentSearch = async () => {
        componentSelection.open = true;
        if (componentSelection.options.length === 0) {
            await searchComponentCandidates();
        }
    };

    /**
     * 選択部品の詳細を読み込み、現在の解析入力へ使えるスペック値を反映する。
     * outputSave.componentIdを条件にAPIを呼び、取り込み状態と各ツール入力を更新する副作用がある。
     */
    // 目的: 設計解析ツールのload Component Contextを扱う。機能: 入力値を検証・整形し、画面または計算処理へ渡す。入力: 関数シグネチャの値。出力: 処理結果またはなし。動作条件: 設計解析ツールの初期化後に呼び出す。副作用: Vue状態、localStorage、DOM、HTTP通信のいずれかを更新する場合がある。
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
            outputSave.component = componentImport.component;
            componentSelection.selected = componentImport.component;
            componentSelection.query = componentLabel(componentImport.component);
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

    /**
     * 取り込み済みまたは選択済み部品の表示名を返す。
     * 戻り値は文字列で、reactive状態の変更は行わない。
     */
    const loadedComponentName = computed(() => {
        const component = componentImport.component ?? componentSelection.selected;
        return componentLabel(component);
    });

    /**
     * 取り込み済み部品の新品/中古合計在庫を算出する。
     * 部品未読込時はnullを返し、副作用はない。
     */
    const loadedComponentStock = computed(() => {
        const component = componentImport.component;
        if (!component) return null;
        return toFinite(component.quantity_new) + toFinite(component.quantity_used);
    });

    /**
     * 保存済み解析と現在入力の差分プレビューを作る。
     * 入力は保存済みセッション、戻り値は差分オブジェクトまたはnullで、副作用はない。
     */
    // 目的: 設計解析ツールのbuild Saved Diffを扱う。機能: 入力値を検証・整形し、画面または計算処理へ渡す。入力: 関数シグネチャの値。出力: 表示値、配列、オブジェクト、数値のいずれか。動作条件: 設計解析ツールの初期化後に呼び出す。副作用: なし。
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

    /**
     * 保存済み解析一覧へセッションを追加または差し替える。
     * 入力はAPI保存結果で、savedAnalysis.sessionsを更新する副作用がある。
     */
    // 目的: 設計解析ツールのupsert Saved Sessionを扱う。機能: 入力値を検証・整形し、画面または計算処理へ渡す。入力: 関数シグネチャの値。出力: 処理結果またはなし。動作条件: 設計解析ツールの初期化後に呼び出す。副作用: Vue状態、localStorage、DOM、HTTP通信のいずれかを更新する場合がある。
    const upsertSavedSession = (session) => {
        if (!session?.id) return;
        const index = savedAnalysis.sessions.findIndex((item) => item.id === session.id);
        if (index >= 0) {
            savedAnalysis.sessions.splice(index, 1, session);
        } else {
            savedAnalysis.sessions.unshift(session);
        }
    };

    /**
     * 現在のツール/案件/部品条件に合う保存済み解析を取得する。
     * API呼び出しを行い、一覧・選択中差分・ステータスを更新する副作用がある。
     */
    // 目的: 設計解析ツールのload Saved Analysisを扱う。機能: 入力値を検証・整形し、画面または計算処理へ渡す。入力: 関数シグネチャの値。出力: 処理結果またはなし。動作条件: 設計解析ツールの初期化後に呼び出す。副作用: Vue状態、localStorage、DOM、HTTP通信のいずれかを更新する場合がある。
    const loadSavedAnalysis = async () => {
        savedAnalysis.loading = true;
        savedAnalysis.status = '';
        savedAnalysis.error = '';
        savedAnalysis.message = '';
        savedAnalysis.diff = null;
        try {
            const params = new URLSearchParams({ tool_id: normalizeToolId(activeToolId.value) });
            const projectId = outputSave.project?.id ?? outputSave.projectId;
            const componentId = outputSave.component?.id ?? outputSave.componentId;
            if (projectId) params.set('project_id', projectId);
            if (componentId) params.set('component_id', componentId);
            if (outputSave.bomLineKey) params.set('bom_line_key', outputSave.bomLineKey);
            const data = await apiJson(`/api/analysis-sessions?${params.toString()}`);
            savedAnalysis.sessions = extractApiList(data);
            savedAnalysis.selectedId = savedAnalysis.sessions[0]?.id ?? null;
            savedAnalysis.diff = buildSavedDiff(savedAnalysis.sessions[0]);
            savedAnalysis.status = 'success';
            savedAnalysis.message = savedAnalysis.sessions.length
                ? `${savedAnalysis.sessions.length}件の保存済み解析を読み込みました。`
                : '保存済み解析はありません。保存後に一覧から復元できます。';
        } catch (error) {
            savedAnalysis.status = 'error';
            savedAnalysis.error = error?.message || '保存済み解析の取得に失敗しました。';
        } finally {
            savedAnalysis.loading = false;
        }
    };

    /**
     * 現在の解析結果と紐づけ条件から保存API payloadを組み立てる。
     * 戻り値はJSON保存用オブジェクトで、副作用はない。
     */
    const analysisPayload = computed(() => {
        const reportData = analysisReport.value;
        const toolId = normalizeToolId(activeToolId.value);
        return {
            tool_id: toolId,
            title: outputSave.title || `${activeTool.value?.label ?? toolId} ${reportData?.verdict ?? ''}`.trim(),
            verdict: reportData?.verdict ?? 'CHECK',
            summary: reportData?.copySummary || reportData?.summary || '',
            input_payload: currentInputPayload.value,
            result_payload: reportData,
            candidate_links: reportData?.candidateLinks ?? [],
            project_id: normalizeId(outputSave.project?.id ?? outputSave.projectId),
            component_id: normalizeId(outputSave.component?.id ?? outputSave.componentId),
            bom_line_key: outputSave.bomLineKey || null,
        };
    });

    /**
     * 現在の解析サマリをクリップボードへコピーする。
     * copySummaryとClipboard APIがある場合だけ書き込み、副作用としてクリップボードを更新する。
     */
    // 目的: 設計解析ツールのcopy Analysis Summaryを扱う。機能: 入力値を検証・整形し、画面または計算処理へ渡す。入力: 関数シグネチャの値。出力: 処理結果またはなし。動作条件: 設計解析ツールの初期化後に呼び出す。副作用: Vue状態、localStorage、DOM、HTTP通信のいずれかを更新する場合がある。
    const copyAnalysisSummary = async () => {
        if (!analysisReport.value?.copySummary || !navigator?.clipboard) return;
        await navigator.clipboard.writeText(analysisReport.value.copySummary);
    };

    /**
     * 現在の解析セッションをAPIへ保存する。
     * analysisPayloadを入力にPOSTし、保存状態と保存済み一覧を更新する副作用がある。
     */
    // 目的: 設計解析ツールのsave Analysis Reportを扱う。機能: 入力値を検証・整形し、画面または計算処理へ渡す。入力: 関数シグネチャの値。出力: 処理結果またはなし。動作条件: 設計解析ツールの初期化後に呼び出す。副作用: Vue状態、localStorage、DOM、HTTP通信のいずれかを更新する場合がある。
    const saveAnalysisReport = async () => {
        outputSave.saving = true;
        outputSave.status = '';
        outputSave.message = '';
        outputSave.error = '';
        try {
            const data = await apiJson('/api/analysis-sessions', {
                method: 'POST',
                body: JSON.stringify(analysisPayload.value),
            });
            outputSave.status = 'success';
            outputSave.message = data.message || '解析セッションを保存しました。';
            upsertSavedSession(data.data ?? data);
        } catch (error) {
            outputSave.status = 'error';
            outputSave.error = error?.message || '保存に失敗しました。';
        } finally {
            outputSave.saving = false;
        }
    };

    /**
     * 選択中テンプレートの入力条件を現在のツールへ反映する。
     * activeToolId、ツールグループ、入力payload、タイトル、テンプレート状態を更新する副作用がある。
     */
    // 目的: 設計解析ツールのapply Analysis Templateを扱う。機能: 入力値を検証・整形し、画面または計算処理へ渡す。入力: 関数シグネチャの値。出力: 処理結果またはなし。動作条件: 設計解析ツールの初期化後に呼び出す。副作用: Vue状態、localStorage、DOM、HTTP通信のいずれかを更新する場合がある。
    const applyAnalysisTemplate = () => {
        const template = selectedTemplate.value;
        if (!template) return;
        const toolId = normalizeToolId(template.tool_id);
        setToolGroup?.(toolGroupForToolId?.(toolId) ?? 'all');
        activeToolId.value = toolId;
        applyToolPayload(toolId, clonePlain(template.payload));
        outputSave.title = template.title;
        templateState.status = 'success';
        templateState.message = `${template.label} を入力へ反映しました。`;
        templateState.error = '';
    };

    /**
     * 選択中テンプレートを現在ユーザーの保存済み解析として複製する。
     * テンプレート反映後に保存APIを呼び、保存状態とメッセージを更新する副作用がある。
     */
    // 目的: 設計解析ツールのduplicate Analysis Templateを扱う。機能: 入力値を検証・整形し、画面または計算処理へ渡す。入力: 関数シグネチャの値。出力: 処理結果またはなし。動作条件: 設計解析ツールの初期化後に呼び出す。副作用: Vue状態、localStorage、DOM、HTTP通信のいずれかを更新する場合がある。
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

    /**
     * 保存済み解析一覧で選択中の行と差分プレビューを更新する。
     * 入力はセッション、戻り値はなく、savedAnalysis.selectedId/diffを変更する。
     */
    // 目的: 設計解析ツールのselect Saved Analysisを扱う。機能: 入力値を検証・整形し、画面または計算処理へ渡す。入力: 関数シグネチャの値。出力: 処理結果またはなし。動作条件: 設計解析ツールの初期化後に呼び出す。副作用: Vue状態、localStorage、DOM、HTTP通信のいずれかを更新する場合がある。
    const selectSavedAnalysis = (session) => {
        savedAnalysis.selectedId = session?.id ?? null;
        savedAnalysis.diff = buildSavedDiff(session);
    };

    /**
     * 保存済み解析の入力条件を現在の画面へ復元する。
     * ツール選択、入力payload、案件/部品/BOM条件を更新する副作用がある。
     */
    // 目的: 設計解析ツールのrestore Saved Analysisを扱う。機能: 入力値を検証・整形し、画面または計算処理へ渡す。入力: 関数シグネチャの値。出力: 処理結果またはなし。動作条件: 設計解析ツールの初期化後に呼び出す。副作用: Vue状態、localStorage、DOM、HTTP通信のいずれかを更新する場合がある。
    const restoreSavedAnalysis = (session) => {
        if (!session) return;
        const toolId = normalizeToolId(session.tool_id);
        if (SAVED_ANALYSIS_TOOL_IDS.includes(toolId)) {
            setToolGroup?.(toolGroupForToolId?.(toolId) ?? 'all');
            activeToolId.value = toolId;
        }
        applyToolPayload(toolId, clonePlain(session.input_payload ?? {}));
        outputSave.title = session.title ?? '';
        outputSave.project = session.project ?? projectFallback(session.project_id);
        outputSave.projectId = session.project_id ? String(session.project_id) : '';
        outputSave.component = session.component ?? componentFallback(session.component_id);
        outputSave.componentId = session.component_id ? String(session.component_id) : '';
        componentSelection.selected = outputSave.component;
        componentSelection.query = componentLabel(outputSave.component);
        outputSave.bomLineKey = session.bom_line_key ?? '';
        selectSavedAnalysis(session);
        savedAnalysis.status = 'success';
        savedAnalysis.message = '保存済み解析の入力条件を現在の入力へ復元しました。';
    };

    /**
     * 保存済み解析の削除確認対象を設定する。
     * 入力はセッション、戻り値はなく、deleteTargetとエラー状態を更新する。
     */
    // 目的: 設計解析ツールのbegin Delete Saved Analysisを扱う。機能: 入力値を検証・整形し、画面または計算処理へ渡す。入力: 関数シグネチャの値。出力: 処理結果またはなし。動作条件: 設計解析ツールの初期化後に呼び出す。副作用: Vue状態、localStorage、DOM、HTTP通信のいずれかを更新する場合がある。
    const beginDeleteSavedAnalysis = (session) => {
        savedAnalysis.deleteTarget = session;
        savedAnalysis.error = '';
    };

    /**
     * 保存済み解析の削除確認を取り消す。
     * 入力は不要、戻り値はなく、deleteTargetをクリアする副作用がある。
     */
    // 目的: 設計解析ツールのcancel Delete Saved Analysisを扱う。機能: 入力値を検証・整形し、画面または計算処理へ渡す。入力: 関数シグネチャの値。出力: 真偽値。動作条件: 設計解析ツールの初期化後に呼び出す。副作用: Vue状態、localStorage、DOM、HTTP通信のいずれかを更新する場合がある。
    const cancelDeleteSavedAnalysis = () => {
        savedAnalysis.deleteTarget = null;
    };

    /**
     * 確認対象の保存済み解析を削除APIへ送る。
     * deleteTargetがある場合だけDELETEし、一覧・選択状態・ステータスを更新する副作用がある。
     */
    // 目的: 設計解析ツールのdelete Saved Analysisを扱う。機能: 入力値を検証・整形し、画面または計算処理へ渡す。入力: 関数シグネチャの値。出力: 処理結果またはなし。動作条件: 設計解析ツールの初期化後に呼び出す。副作用: Vue状態、localStorage、DOM、HTTP通信のいずれかを更新する場合がある。
    const deleteSavedAnalysis = async () => {
        const target = savedAnalysis.deleteTarget;
        if (!target?.id) return;
        savedAnalysis.deletingId = target.id;
        savedAnalysis.error = '';
        try {
            await apiJson(`/api/analysis-sessions/${target.id}`, { method: 'DELETE' });
            savedAnalysis.sessions = savedAnalysis.sessions.filter((session) => session.id !== target.id);
            if (savedAnalysis.selectedId === target.id) {
                savedAnalysis.selectedId = null;
                savedAnalysis.diff = null;
            }
            savedAnalysis.deleteTarget = null;
            savedAnalysis.status = 'success';
            savedAnalysis.message = '保存済み解析を削除しました。';
        } catch (error) {
            savedAnalysis.error = error?.status === 403
                ? '削除権限がありません。editor以上の権限でログインしてください。'
                : (error?.message || '保存済み解析の削除に失敗しました。');
        } finally {
            savedAnalysis.deletingId = null;
        }
    };

    return {
        analysisTemplates,
        templateState,
        selectedTemplate,
        outputSave,
        componentSelection,
        componentImport,
        savedAnalysis,
        analysisPayload,
        copyAnalysisSummary,
        saveAnalysisReport,
        applyAnalysisTemplate,
        duplicateAnalysisTemplate,
        componentLabel,
        selectComponentCandidate,
        clearComponentSelection,
        searchComponentCandidates,
        onComponentSearchInput,
        openComponentSearch,
        loadComponentContext,
        loadedComponentName,
        loadedComponentStock,
        loadSavedAnalysis,
        selectSavedAnalysis,
        restoreSavedAnalysis,
        beginDeleteSavedAnalysis,
        cancelDeleteSavedAnalysis,
        deleteSavedAnalysis,
        sessionProjectLabel,
        sessionComponentLabel,
        sessionUpdatedAtLabel,
    };
}
