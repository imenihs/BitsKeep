/**
 * 設計解析ツールの責務分割モジュール。
 * 親 setup から渡された reactive/computed と数値ヘルパーを使い、
 * 画面表示に必要な状態、計算結果、レポート生成関数を返す。
 */

/**
 * setupConnectorTool は親から渡された依存を使ってツール責務を初期化する。
 * @param {object} deps 入力状態、数値変換、レポート生成などの依存。
 * @returns {object} Vueテンプレートへ公開する状態、computed、操作関数。
 * @sideEffects reactive状態とlocalStorageを更新する操作関数を含む。
 */
// 目的: 設計解析ツールのsetup Connector Toolを扱う。機能: 入力値を検証・整形し、画面または計算処理へ渡す。入力: 関数シグネチャの値。出力: 処理結果またはなし。動作条件: 設計解析ツールの初期化後に呼び出す。副作用: Vue状態、localStorage、DOM、HTTP通信のいずれかを更新する場合がある。
export default function setupConnectorTool({
    computed,
    ref,
    quickForms,
    toFinite,
    hasRating,
}) {
const connectorTemplateCatalog = [
    {
        id: 'usb-c-receptacle',
        label: 'USB Type-C Receptacle',
        family: 'USB',
        standard: 'USB Type-C',
        pins: 24,
        rows: 2,
        pitchMm: 0.5,
        gender: 'receptacle',
        voltageRatingV: 20,
        currentRatingPerPin: 1.25,
        numbering: 'A1-A12 / B1-B12, 嵌合面基準',
        viewSide: 'mating-face',
        pin1Mark: 'key-notch',
        matingPart: 'USB Type-C plug',
        photoUrl: '',
        diagramUrl: '',
        datasheetUrl: '',
        notes: 'CC/SBU、シールド、差動ペア極性を確認する',
        pinsMap: [
            ['A1', 'GND', 'ground'], ['A2', 'TX1+', 'diff'], ['A3', 'TX1-', 'diff'], ['A4', 'VBUS', 'power'], ['A5', 'CC1', 'signal'], ['A6', 'D+', 'diff'],
            ['A7', 'D-', 'diff'], ['A8', 'SBU1', 'signal'], ['A9', 'VBUS', 'power'], ['A10', 'RX2-', 'diff'], ['A11', 'RX2+', 'diff'], ['A12', 'GND', 'ground'],
            ['B1', 'GND', 'ground'], ['B2', 'TX2+', 'diff'], ['B3', 'TX2-', 'diff'], ['B4', 'VBUS', 'power'], ['B5', 'CC2', 'signal'], ['B6', 'D+', 'diff'],
            ['B7', 'D-', 'diff'], ['B8', 'SBU2', 'signal'], ['B9', 'VBUS', 'power'], ['B10', 'RX1-', 'diff'], ['B11', 'RX1+', 'diff'], ['B12', 'GND', 'ground'],
        ],
    },
    {
        id: 'usb2-type-a',
        label: 'USB 2.0 Type-A',
        family: 'USB',
        standard: 'USB 2.0',
        pins: 4,
        rows: 1,
        pitchMm: 2.5,
        gender: 'receptacle',
        voltageRatingV: 5,
        currentRatingPerPin: 1,
        numbering: '1-4, 嵌合面基準',
        viewSide: 'mating-face',
        pin1Mark: 'key-notch',
        matingPart: 'USB Type-A plug',
        pinsMap: [['1', 'VBUS', 'power'], ['2', 'D-', 'diff'], ['3', 'D+', 'diff'], ['4', 'GND', 'ground']],
    },
    {
        id: 'dsub-de9',
        label: 'D-sub DE-9',
        family: 'D-sub',
        standard: 'DE-9',
        pins: 9,
        rows: 2,
        pitchMm: 2.77,
        gender: 'plug/socket',
        voltageRatingV: 125,
        currentRatingPerPin: 3,
        numbering: '上段1-5/下段6-9, 嵌合面基準',
        viewSide: 'mating-face',
        pin1Mark: 'shell-mark',
        matingPart: 'DE-9 mate',
        pinsMap: Array.from({ length: 9 }, (_, index) => [`${index + 1}`, '', 'signal']),
    },
    {
        id: 'dsub-db25',
        label: 'D-sub DB-25',
        family: 'D-sub',
        standard: 'DB-25',
        pins: 25,
        rows: 2,
        pitchMm: 2.77,
        gender: 'plug/socket',
        voltageRatingV: 125,
        currentRatingPerPin: 3,
        numbering: '上段1-13/下段14-25, 嵌合面基準',
        viewSide: 'mating-face',
        pin1Mark: 'shell-mark',
        matingPart: 'DB-25 mate',
        pinsMap: Array.from({ length: 25 }, (_, index) => [`${index + 1}`, '', 'signal']),
    },
    {
        id: 'idc-2x5',
        label: 'IDC 2x5',
        family: 'IDC',
        standard: '2.54mm ribbon',
        pins: 10,
        rows: 2,
        pitchMm: 2.54,
        gender: 'header/socket',
        voltageRatingV: 50,
        currentRatingPerPin: 1,
        numbering: '奇数列/偶数列, key notch基準',
        viewSide: 'mating-face',
        pin1Mark: 'triangle',
        matingPart: 'IDC 10P socket',
        pinsMap: Array.from({ length: 10 }, (_, index) => [`${index + 1}`, '', index % 2 === 0 ? 'signal' : 'ground']),
    },
    {
        id: 'pin-header-2x5',
        label: '2.54mm Pin Header 2x5',
        family: 'pin-header',
        standard: '2.54mm',
        pins: 10,
        rows: 2,
        pitchMm: 2.54,
        gender: 'header',
        voltageRatingV: 50,
        currentRatingPerPin: 1,
        numbering: 'シルクPin1基準',
        viewSide: 'mating-face',
        pin1Mark: 'square-pad',
        matingPart: '2.54mm socket',
        pinsMap: Array.from({ length: 10 }, (_, index) => [`${index + 1}`, '', 'signal']),
    },
    {
        id: 'jst-xh-4',
        label: 'JST XH 4P',
        family: 'JST',
        standard: 'XH',
        pins: 4,
        rows: 1,
        pitchMm: 2.5,
        gender: 'board header',
        voltageRatingV: 250,
        currentRatingPerPin: 3,
        numbering: 'lock側/嵌合面のPin1を図示',
        viewSide: 'mating-face',
        pin1Mark: 'key-notch',
        matingPart: 'JST XH housing',
        pinsMap: [['1', 'V+', 'power'], ['2', 'SIG1', 'signal'], ['3', 'SIG2', 'signal'], ['4', 'GND', 'ground']],
    },
    {
        id: 'jst-ph-2',
        label: 'JST PH 2P',
        family: 'JST',
        standard: 'PH',
        pins: 2,
        rows: 1,
        pitchMm: 2,
        gender: 'board header',
        voltageRatingV: 100,
        currentRatingPerPin: 2,
        numbering: 'lock側/嵌合面のPin1を図示',
        viewSide: 'mating-face',
        pin1Mark: 'key-notch',
        matingPart: 'JST PH housing',
        pinsMap: [['1', 'V+', 'power'], ['2', 'GND', 'ground']],
    },
    {
        id: 'molex-microfit-2x3',
        label: 'Molex Micro-Fit 2x3',
        family: 'Molex',
        standard: 'Micro-Fit',
        pins: 6,
        rows: 2,
        pitchMm: 3,
        gender: 'plug/receptacle',
        voltageRatingV: 600,
        currentRatingPerPin: 5,
        numbering: 'latch/key基準',
        viewSide: 'mating-face',
        pin1Mark: 'key-notch',
        matingPart: 'Micro-Fit mate',
        pinsMap: [['1', 'V+', 'power'], ['2', 'V+', 'power'], ['3', 'SIG', 'signal'], ['4', 'GND', 'ground'], ['5', 'GND', 'ground'], ['6', 'SHIELD', 'shield']],
    },
    {
        id: 'rj45-8p8c',
        label: 'RJ45 8P8C',
        family: 'RJ45',
        standard: '8P8C',
        pins: 8,
        rows: 1,
        pitchMm: 1.02,
        gender: 'jack/plug',
        voltageRatingV: 57,
        currentRatingPerPin: 1,
        numbering: 'clip away, contact side基準',
        viewSide: 'mating-face',
        pin1Mark: 'key-notch',
        matingPart: '8P8C plug',
        pinsMap: [['1', 'BI_DA+', 'diff'], ['2', 'BI_DA-', 'diff'], ['3', 'BI_DB+', 'diff'], ['4', 'BI_DC+', 'diff'], ['5', 'BI_DC-', 'diff'], ['6', 'BI_DB-', 'diff'], ['7', 'BI_DD+', 'diff'], ['8', 'BI_DD-', 'diff']],
    },
];
// 目的: 設計解析ツールのsafe Local Storageを扱う。機能: 入力値を検証・整形し、画面または計算処理へ渡す。入力: 関数シグネチャの値。出力: 処理結果またはなし。動作条件: 設計解析ツールの初期化後に呼び出す。副作用: なし。
const safeLocalStorage = () => {
    try {
        return typeof window !== 'undefined' ? window.localStorage : null;
    } catch {
        return null;
    }
};
// 目的: 設計解析ツールのload Connector User Templatesを扱う。機能: 入力値を検証・整形し、画面または計算処理へ渡す。入力: 関数シグネチャの値。出力: 処理結果またはなし。動作条件: 設計解析ツールの初期化後に呼び出す。副作用: Vue状態、localStorage、DOM、HTTP通信のいずれかを更新する場合がある。
const loadConnectorUserTemplates = () => {
    const storage = safeLocalStorage();
    if (!storage) return [];
    try {
        const parsed = JSON.parse(storage.getItem('bitskeep.connectorTemplates') || '[]');
        return Array.isArray(parsed) ? parsed : [];
    } catch {
        return [];
    }
};
const connectorUserTemplates = ref(loadConnectorUserTemplates());
// 目的: 設計解析ツールのsave Connector User Templatesを扱う。機能: 入力値を検証・整形し、画面または計算処理へ渡す。入力: 関数シグネチャの値。出力: 処理結果またはなし。動作条件: 設計解析ツールの初期化後に呼び出す。副作用: Vue状態、localStorage、DOM、HTTP通信のいずれかを更新する場合がある。
const saveConnectorUserTemplates = () => {
    const storage = safeLocalStorage();
    if (!storage) return;
    storage.setItem('bitskeep.connectorTemplates', JSON.stringify(connectorUserTemplates.value));
};
// 目的: 設計解析ツールのnormalize Connector Templateを扱う。機能: 入力値を検証・整形し、画面または計算処理へ渡す。入力: 関数シグネチャの値。出力: 表示値、配列、オブジェクト、数値のいずれか。動作条件: 設計解析ツールの初期化後に呼び出す。副作用: なし。
const normalizeConnectorTemplate = (template) => ({
    id: template.id,
    label: template.label,
    family: template.family || template.standard || 'custom',
    standard: template.standard || template.family || 'custom',
    pins: Math.max(1, Math.round(toFinite(template.pins, 1))),
    rows: Math.max(1, Math.round(toFinite(template.rows, 1))),
    pitchMm: toFinite(template.pitchMm, 2.54),
    gender: template.gender || '',
    voltageRatingV: toFinite(template.voltageRatingV, 0),
    currentRatingPerPin: toFinite(template.currentRatingPerPin, 0),
    numbering: template.numbering || 'Pin1から昇順',
    viewSide: template.viewSide || 'mating-face',
    pin1Mark: template.pin1Mark || 'silk-dot',
    matingPart: template.matingPart || '',
    photoUrl: template.photoUrl || '',
    diagramUrl: template.diagramUrl || '',
    datasheetUrl: template.datasheetUrl || '',
    notes: template.notes || '',
    pinsMap: Array.isArray(template.pinsMap) ? template.pinsMap : [],
    userDefined: Boolean(template.userDefined),
});
/**
 * 標準テンプレートとユーザー保存テンプレートを統合する。
 * 戻り値は正規化済みテンプレート配列で、副作用はない。
 */
const connectorCatalog = computed(() => [
    ...connectorTemplateCatalog.map(normalizeConnectorTemplate),
    ...connectorUserTemplates.value.map(normalizeConnectorTemplate),
]);
/**
 * 現在選択中のコネクタテンプレートを解決する。
 * 未選択またはID不一致時は先頭テンプレートへフォールバックし、副作用はない。
 */
const connectorActiveTemplate = computed(() => (
    connectorCatalog.value.find((item) => item.id === quickForms.connector.selectedTemplateId)
    ?? connectorCatalog.value[0]
));
/** コネクタテンプレート選択肢を生成する。戻り値は `[id, label]` 配列で副作用はない。 */
const connectorTemplateOptions = computed(() => connectorCatalog.value.map((template) => [template.id, `${template.label} (${template.pins}P)`]));
/**
 * 選択テンプレートの定格・ピン情報をフォームへ反映する。
 * replaceAssignmentsがtrueまたは未入力時にピン割付も置換し、quickForms.connectorを更新する副作用がある。
 */
// 目的: 設計解析ツールのapply Connector Templateを扱う。機能: 入力値を検証・整形し、画面または計算処理へ渡す。入力: 関数シグネチャの値。出力: 処理結果またはなし。動作条件: 設計解析ツールの初期化後に呼び出す。副作用: Vue状態、localStorage、DOM、HTTP通信のいずれかを更新する場合がある。
const applyConnectorTemplate = (template = connectorActiveTemplate.value, replaceAssignments = true) => {
    if (!template) return;
    quickForms.connector.selectedTemplateId = template.id;
    quickForms.connector.pins = template.pins;
    quickForms.connector.connectorType = template.family;
    quickForms.connector.pitchMm = template.pitchMm;
    quickForms.connector.currentRatingPerPin = template.currentRatingPerPin || quickForms.connector.currentRatingPerPin;
    quickForms.connector.voltageRatingV = template.voltageRatingV || quickForms.connector.voltageRatingV;
    quickForms.connector.viewSide = template.viewSide;
    quickForms.connector.pin1Mark = template.pin1Mark;
    quickForms.connector.matingPart = template.matingPart || quickForms.connector.matingPart;
    quickForms.connector.photoUrl = template.photoUrl || quickForms.connector.photoUrl;
    quickForms.connector.diagramUrl = template.diagramUrl || quickForms.connector.diagramUrl;
    quickForms.connector.datasheetUrl = template.datasheetUrl || quickForms.connector.datasheetUrl;
    if (replaceAssignments || !quickForms.connector.pinAssignments.trim()) {
        quickForms.connector.pinAssignments = template.pinsMap.map((pin) => `${pin[0]},${pin[1] || ''},${pin[2] || 'signal'},0,0,,,`).join('\n');
    }
};
/**
 * ユーザー入力からコネクタテンプレートを作成して保存する。
 * 名前未入力時は何もせず、保存時はlocalStorage相当の保存処理とフォーム反映の副作用がある。
 */
// 目的: 設計解析ツールのsave Connector Templateを扱う。機能: 入力値を検証・整形し、画面または計算処理へ渡す。入力: 関数シグネチャの値。出力: 処理結果またはなし。動作条件: 設計解析ツールの初期化後に呼び出す。副作用: Vue状態、localStorage、DOM、HTTP通信のいずれかを更新する場合がある。
const saveConnectorTemplate = () => {
    const name = String(quickForms.connector.userTemplateName || '').trim();
    if (!name) return;
    const id = `user-${Date.now().toString(36)}-${name.toLowerCase().replace(/[^a-z0-9]+/gu, '-').replace(/^-|-$/g, '') || 'connector'}`;
    const pins = Math.max(1, Math.round(toFinite(quickForms.connector.userTemplatePins, 1)));
    const template = normalizeConnectorTemplate({
        id,
        label: name,
        family: 'user',
        standard: quickForms.connector.userTemplateStandard || 'user',
        pins,
        rows: Math.max(1, Math.round(toFinite(quickForms.connector.userTemplateRows, 1))),
        pitchMm: toFinite(quickForms.connector.userTemplatePitchMm, 2.54),
        gender: quickForms.connector.userTemplateGender,
        voltageRatingV: toFinite(quickForms.connector.userTemplateVoltageRatingV, 0),
        currentRatingPerPin: toFinite(quickForms.connector.userTemplateCurrentRatingPerPin, 0),
        numbering: quickForms.connector.userTemplateNumbering,
        viewSide: quickForms.connector.viewSide,
        pin1Mark: quickForms.connector.pin1Mark,
        matingPart: quickForms.connector.userTemplateMatingPart,
        photoUrl: quickForms.connector.userTemplatePhotoUrl,
        diagramUrl: quickForms.connector.userTemplateDiagramUrl,
        datasheetUrl: quickForms.connector.userTemplateDatasheetUrl,
        notes: quickForms.connector.userTemplateNotes,
        pinsMap: Array.from({ length: pins }, (_, index) => [`${index + 1}`, '', 'signal']),
        userDefined: true,
    });
    connectorUserTemplates.value = [...connectorUserTemplates.value, template];
    saveConnectorUserTemplates();
    applyConnectorTemplate(template);
    quickForms.connector.userTemplateName = '';
};
/**
 * 選択テンプレートからピン一覧の基礎データを作る。
 * 明示pinsMapがあれば優先し、戻り値はピン定義配列で副作用はない。
 */
const connectorTemplatePins = computed(() => {
    const template = connectorActiveTemplate.value;
    const explicit = template?.pinsMap?.length ? template.pinsMap : [];
    if (explicit.length) {
        return explicit.map((pin, index) => ({
            pin: String(pin[0] || index + 1),
            defaultSignal: pin[1] || '',
            defaultType: pin[2] || 'signal',
        }));
    }
    return Array.from({ length: Math.max(1, toFinite(template?.pins, quickForms.connector.pins)) }, (_, index) => ({
        pin: String(index + 1),
        defaultSignal: '',
        defaultType: 'signal',
    }));
});
/**
 * ピン割付テキストを構造化データへ変換する。
 * 入力はquickForms.connector.pinAssignmentsで、戻り値は割付配列、副作用はない。
 */
// 目的: 設計解析ツールのparse Connector Assignmentsを扱う。機能: 入力値を検証・整形し、画面または計算処理へ渡す。入力: 関数シグネチャの値。出力: 表示値、配列、オブジェクト、数値のいずれか。動作条件: 設計解析ツールの初期化後に呼び出す。副作用: なし。
const parseConnectorAssignments = () => String(quickForms.connector.pinAssignments || '')
    .split('\n')
    .map((row) => row.trim())
    .filter(Boolean)
    .map((row) => {
        const [pin, signal, type, voltage, current, color, awg, note] = row.split(',').map((value) => String(value || '').trim());
        return {
            pin,
            signal,
            type: type || 'signal',
            voltage: toFinite(voltage, 0),
            current: Math.abs(toFinite(current, 0)),
            color,
            awg,
            note,
            raw: row,
        };
    });
/** ピン割付テキストの最新構造化結果を返すcomputed。戻り値は割付配列で副作用はない。 */
const connectorAssignments = computed(parseConnectorAssignments);
/**
 * AWGサイズから目安電流上限を返す。
 * 未知サイズはnullを返し、戻り値はA単位の数値で副作用はない。
 */
// 目的: 設計解析ツールのawg Current Limitを扱う。機能: 入力値を検証・整形し、画面または計算処理へ渡す。入力: 関数シグネチャの値。出力: 処理結果またはなし。動作条件: 設計解析ツールの初期化後に呼び出す。副作用: Vue状態、localStorage、DOM、HTTP通信のいずれかを更新する場合がある。
const awgCurrentLimit = (awg) => {
    const normalized = String(awg || '').replace(/AWG/iu, '').trim();
    const table = { 30: 0.3, 28: 0.5, 26: 0.8, 24: 1.5, 22: 3, 20: 5, 18: 7, 16: 10 };
    const value = Math.round(toFinite(normalized, 0));
    return table[value] ?? null;
};
/**
 * テンプレートピンとユーザー割付を統合し、電流/電圧/AWG余裕を計算する。
 * 戻り値はピンごとの表示・判定データで、副作用はない。
 */
const connectorPinMap = computed(() => {
    const assignmentByPin = new Map(connectorAssignments.value.map((assignment) => [assignment.pin, assignment]));
    return connectorTemplatePins.value.map((pin) => {
        const assignment = assignmentByPin.get(pin.pin);
        const merged = {
            ...pin,
            ...(assignment || {}),
            signal: assignment?.signal || pin.defaultSignal || '',
            type: assignment?.type || pin.defaultType || 'signal',
            voltage: assignment?.voltage ?? 0,
            current: assignment?.current ?? 0,
        };
        const currentRating = toFinite(quickForms.connector.currentRatingPerPin, connectorActiveTemplate.value?.currentRatingPerPin ?? 0);
        const usableCurrent = currentRating * Math.max(0, Math.min(100, toFinite(quickForms.connector.deratingPct, 80))) / 100;
        const voltageRating = toFinite(quickForms.connector.voltageRatingV, connectorActiveTemplate.value?.voltageRatingV ?? 0);
        const awgLimit = awgCurrentLimit(merged.awg);
        return {
            ...merged,
            usableCurrent,
            currentMargin: usableCurrent - Math.abs(merged.current),
            voltageMargin: voltageRating - Math.abs(merged.voltage),
            awgLimit,
            awgMargin: awgLimit === null ? null : awgLimit - Math.abs(merged.current),
            assigned: Boolean(assignment || pin.defaultSignal),
        };
    });
});
/**
 * コネクタ設計の未割付、定格不足、根拠不足をまとめて判定する。
 * connectorPinMapとフォーム条件を入力にし、戻り値はサマリオブジェクトで副作用はない。
 */
const connectorSummary = computed(() => {
    const pins = connectorPinMap.value;
    const assigned = pins.filter((pin) => pin.assigned && pin.type !== 'nc');
    const powerPins = assigned.filter((pin) => pin.type === 'power');
    const groundPins = assigned.filter((pin) => pin.type === 'ground');
    const diffPins = assigned.filter((pin) => pin.type === 'diff');
    const overCurrent = assigned.filter((pin) => pin.usableCurrent > 0 && pin.currentMargin < 0);
    const overVoltage = assigned.filter((pin) => toFinite(quickForms.connector.voltageRatingV, connectorActiveTemplate.value?.voltageRatingV ?? 0) > 0 && pin.voltageMargin < 0);
    const overAwg = assigned.filter((pin) => pin.awgMargin !== null && pin.awgMargin < 0);
    const unassigned = pins.filter((pin) => !pin.assigned);
    const noCurrentRating = !hasRating(quickForms.connector.currentRatingPerPin);
    const noVoltageRating = !hasRating(quickForms.connector.voltageRatingV);
    const noEvidence = !quickForms.connector.datasheetUrl && !quickForms.connector.diagramUrl && !connectorActiveTemplate.value?.datasheetUrl && !connectorActiveTemplate.value?.diagramUrl;
    const noAsset = !quickForms.connector.photoUrl && !quickForms.connector.diagramUrl && !connectorActiveTemplate.value?.photoUrl && !connectorActiveTemplate.value?.diagramUrl;
    const missingConditions = [
        ...(noCurrentRating ? ['1pin定格電流'] : []),
        ...(noVoltageRating ? ['定格電圧'] : []),
        ...(noEvidence ? ['定格根拠URL/図'] : []),
        ...(noAsset ? ['写真またはピン配置図'] : []),
        ...(unassigned.length ? [`未割付ピン ${unassigned.length}本`] : []),
        ...(groundPins.length === 0 ? ['GND/シールド基準'] : []),
        ...(quickForms.connector.viewSide ? [] : ['図面視点']),
    ];
    const warnings = [
        ...overCurrent.map((pin) => `${pin.pin} ${pin.signal || '-'} はderating後電流定格を超過`),
        ...overVoltage.map((pin) => `${pin.pin} ${pin.signal || '-'} は定格電圧を超過`),
        ...overAwg.map((pin) => `${pin.pin} ${pin.signal || '-'} はAWG ${pin.awg} の電流目安を超過`),
        ...(powerPins.length > 1 ? ['電源ピン並列使用は接触抵抗差、GND先行、ホットプラグ順序を確認してください。'] : []),
        ...(diffPins.length % 2 !== 0 ? ['差動ペア指定が奇数です。極性とペア割付を確認してください。'] : []),
        ...(connectorActiveTemplate.value?.family === 'USB' ? ['USBはCC/SBU/シールド、ESD、VBUS突入、GND接続順を確認してください。'] : []),
        ...(connectorActiveTemplate.value?.family === 'D-sub' ? ['D-subはシェル接続、固定ねじ、嵌合面/はんだ面の左右反転を図面で確認してください。'] : []),
        ...(connectorActiveTemplate.value?.family === 'RJ45' ? ['RJ45はペア割付、PoE電流、シールド有無を確認してください。'] : []),
    ];
    const totalCurrent = assigned.reduce((sum, pin) => sum + Math.abs(pin.current), 0);
    const maxPinCurrent = assigned.reduce((max, pin) => Math.max(max, Math.abs(pin.current)), 0);
    const status = overCurrent.length || overVoltage.length || overAwg.length ? 'bad' : (missingConditions.length || warnings.length ? 'check' : 'ok');
    return {
        assigned,
        unassigned,
        powerPins,
        groundPins,
        diffPins,
        overCurrent,
        overVoltage,
        overAwg,
        totalCurrent,
        maxPinCurrent,
        missingConditions,
        warnings,
        status,
    };
});



    return {
        connectorCatalog,
        connectorActiveTemplate,
        connectorTemplateOptions,
        connectorPinMap,
        connectorSummary,
        connectorAssignments,
        connectorUserTemplates,
        applyConnectorTemplate,
        saveConnectorTemplate,
    };
}
