/**
 * 設計解析ツールハブ（SCR-016）
 * タブ切り替えで複数ツールを提供（全てフロント計算）
 *
 * 収録ツール:
 * 1. ADCコード/スケーリング
 * 2. 電解コンデンサ寿命推定
 * 3. ネットワーク探索/分圧/可変抵抗設計
 * 4. チップ抵抗 EIA-96コード早見表
 * 5. NTC/PTC温度変換
 * 6. 電流検出解析（シャント抵抗）
 * 7. 電源余裕解析（供給電力 vs 消費電力）
 * 8. 比較器しきい値/ヒステリシス
 * 9. 熱設計（熱抵抗チェーン）
 * 10. インタフェース余裕解析（VOH/VOL/VIH/VIL）
 * 11. 誤差/歩留まり、保護回路、接続/起動診断などの簡易設計補助
 *
 * 詳細UX受入固定語:
 * 受動部品: 目標値/E系列/在庫値/許容誤差/温度係数/抵抗電力/実装条件
 * ADC: min/typ/max入力/Vref/オフセット/入力源インピーダンス/サンプル時間/固定小数点係数
 * EIA-96: 3文字マーキング/0Ω/3桁/4桁/BOM値/サイズ/メーカー
 * NTC/PTC: 測定対象範囲/固定抵抗候補/自己発熱/ADC量子化/min/typ/max表
 * 電流検出: 片方向/双方向/Rs電力定格/ケルビン接続/ADC量子化幅/CMRR/帯域
 * 電源: レールツリー/上流換算負荷/ドロップアウト/突入電流の時間幅/最大負荷シナリオ/支配負荷
 * バッテリー: 電池パック/動作条件/周期負荷/実効稼働時間/電圧下限到達時間/劣化/効率
 * 比較器: 入力極性/基準方式/R1/R3比/VOH/VOL/入力バイアス/ノイズ余裕
 * 熱: 発熱源/周囲温度/通常Tj/最悪Tj/ディレーティング/支配熱抵抗/放熱候補
 * IF/ロジック/コネクタ/保護/起動: レベル余裕/候補IC/Pin1/定格/故障波形/動作順序/PG/RESET/バックパワー
 */
import { ref, reactive, computed, watch } from 'vue';
import setupEia96Tool from './design-tools/eia96Tool.js';
import setupDesignToolDiagrams from './design-tools/diagrams.js';
import setupConnectorTool from './design-tools/connectorTool.js';
import setupLogicReferenceTool from './design-tools/logicReferenceTool.js';
import setupQuickTools from './design-tools/quickTools.js';
import setupAnalogDesignTools from './design-tools/analogTools.js';
import setupBatteryRuntimeTool from './design-tools/batteryRuntime.js';
import setupAnalysisSessions from './design-tools/analysisSessions.js';
import setupPassiveNetworkTool from './resistance-calc.js';
import {
    formatSiValue,
    normalizeEngineeringText,
    normalizeUnitText,
    parseEngineeringNumberDetail,
    parseUnitPrefixFactor,
} from '../utils/engineeringUnits.js';

/**
 * 設計解析ツールページ全体のVue状態を初期化する。
 * @returns {object} Bladeテンプレートへ公開するタブ状態、計算結果、保存/復元操作。
 * @sideEffects localStorageから直近タブ/グループ/並び順を読み書きし、必要時に解析APIへfetchする。
 */
// 目的: 設計解析ツール本体のsetupを扱う。機能: 入力値を検証・整形し、画面または計算処理へ渡す。入力: 関数シグネチャの値。出力: 処理結果またはなし。動作条件: 設計解析ツール本体の初期化後に呼び出す。副作用: Vue状態、localStorage、DOM、HTTP通信のいずれかを更新する場合がある。
export default function setup() {
    const DESIGN_TOOL_ORDER_KEY = 'bitskeep.designTools.toolOrder.v1';
    const DESIGN_TOOL_ACTIVE_KEY = 'bitskeep.designTools.lastTool.v1';
    const DESIGN_TOOL_GROUP_KEY = 'bitskeep.designTools.toolGroup.v1';
    // 目的: 設計解析ツール本体のread Local Valueを扱う。機能: 入力値を検証・整形し、画面または計算処理へ渡す。入力: 関数シグネチャの値。出力: 処理結果またはなし。動作条件: 設計解析ツール本体の初期化後に呼び出す。副作用: Vue状態、localStorage、DOM、HTTP通信のいずれかを更新する場合がある。
    const readLocalValue = (key) => {
        try {
            return globalThis?.localStorage?.getItem(key) ?? null;
        } catch {
            return null;
        }
    };
    // 目的: 設計解析ツール本体のwrite Local Valueを扱う。機能: 入力値を検証・整形し、画面または計算処理へ渡す。入力: 関数シグネチャの値。出力: 処理結果またはなし。動作条件: 設計解析ツール本体の初期化後に呼び出す。副作用: Vue状態、localStorage、DOM、HTTP通信のいずれかを更新する場合がある。
    const writeLocalValue = (key, value) => {
        try {
            globalThis?.localStorage?.setItem(key, value);
        } catch {
            // localStorageが使えない環境では並び順保存だけ諦める。
        }
    };
    // 目的: 設計解析ツール本体のremove Local Valueを扱う。機能: 入力値を検証・整形し、画面または計算処理へ渡す。入力: 関数シグネチャの値。出力: 処理結果またはなし。動作条件: 設計解析ツール本体の初期化後に呼び出す。副作用: Vue状態、localStorage、DOM、HTTP通信のいずれかを更新する場合がある。
    const removeLocalValue = (key) => {
        try {
            globalThis?.localStorage?.removeItem(key);
        } catch {
            // localStorageが使えない環境では何もしない。
        }
    };
    // 目的: 設計解析ツール本体のnormalize Tool Idを扱う。機能: 入力値を検証・整形し、画面または計算処理へ渡す。入力: 関数シグネチャの値。出力: 表示値、配列、オブジェクト、数値のいずれか。動作条件: 設計解析ツール本体の初期化後に呼び出す。副作用: なし。
    const normalizeToolId = (toolId) => {
        const raw = String(toolId ?? '').trim().toLowerCase();
        if (!raw) return 'network-search';
        const token = raw.replace(/[\s_]+/g, '-').replace(/-+/g, '-');
        const compact = token.replace(/-/g, '');
        const toolAliases = {
            'passive-network': 'network-search',
            'batteryruntime': 'battery-runtime',
            'battery-runtime': 'battery-runtime',
            'battery': 'battery-runtime',
        };
        return toolAliases[raw] ?? toolAliases[token] ?? toolAliases[compact] ?? token;
    };
    const passiveToolModes = {
        'network-search': 'network',
        'divider-design': 'divider',
        'variable-resistor': 'variable',
    };
    // 目的: 設計解析ツール本体のpassive Tool Modeを扱う。機能: 入力値を検証・整形し、画面または計算処理へ渡す。入力: 関数シグネチャの値。出力: 処理結果またはなし。動作条件: 設計解析ツール本体の初期化後に呼び出す。副作用: Vue状態、localStorage、DOM、HTTP通信のいずれかを更新する場合がある。
    const passiveToolMode = (toolId) => passiveToolModes[normalizeToolId(toolId)] ?? null;
    // 目的: 設計解析ツール本体のis Passive Design Toolを扱う。機能: 入力値を検証・整形し、画面または計算処理へ渡す。入力: 関数シグネチャの値。出力: 真偽値。動作条件: 設計解析ツール本体の初期化後に呼び出す。副作用: なし。
    const isPassiveDesignTool = (toolId) => passiveToolMode(toolId) !== null;
    // 目的: 設計解析ツール本体のquery Tool Idを扱う。機能: 入力値を検証・整形し、画面または計算処理へ渡す。入力: 関数シグネチャの値。出力: 処理結果またはなし。動作条件: 設計解析ツール本体の初期化後に呼び出す。副作用: Vue状態、localStorage、DOM、HTTP通信のいずれかを更新する場合がある。
    const queryToolId = (() => {
        try {
            return new URLSearchParams(globalThis?.location?.search || '').get('tool');
        } catch {
            return null;
        }
    })();
    const datasetToolId = document.getElementById('app')?.dataset?.tool || null;
    const explicitToolId = queryToolId || datasetToolId || null;
    const requestedToolId = explicitToolId || readLocalValue(DESIGN_TOOL_ACTIVE_KEY) || 'network-search';
    const activeToolId = ref(normalizeToolId(requestedToolId));
    // 目的: 設計解析ツール本体のunwrap Setup Refsを扱う。機能: 入力値を検証・整形し、画面または計算処理へ渡す。入力: 関数シグネチャの値。出力: 処理結果またはなし。動作条件: 設計解析ツール本体の初期化後に呼び出す。副作用: Vue状態、localStorage、DOM、HTTP通信のいずれかを更新する場合がある。
    const unwrapSetupRefs = (surface) => new Proxy(surface, {
        get(target, key) {
            const value = target[key];
            return value && typeof value === 'object' && value.__v_isRef === true ? value.value : value;
        },
    });
    const passiveNetwork = unwrapSetupRefs(setupPassiveNetworkTool());
    const isToolOrderEditing = ref(false);

    const toolGroups = [
        { id: 'all', label: 'すべて', desc: '登録済みの全ツールを表示' },
        { id: 'passive', label: '受動部品', desc: '抵抗/容量、分圧、可変抵抗、EIA-96' },
        { id: 'measure', label: '計測・変換', desc: 'ADC、温度変換、電流検出、IF余裕' },
        { id: 'margin', label: '余裕・信頼性', desc: '電源、電池、寿命、熱、比較器、誤差、周波数応答' },
        { id: 'protection', label: '保護・起動', desc: '過電圧、TVS、ヒューズ、保護協調、起動診断' },
        { id: 'reference', label: '参照・実装', desc: 'ロジックIC、コネクタ、ケーブル、0Ω/Jumper' },
    ];
    const validToolGroupIds = new Set(toolGroups.map((group) => group.id));
    const defaultTools = [
        { id: 'network-search', group: 'passive', label: 'ネットワーク探索', desc: '抵抗/容量の直列・並列・混在候補を目標値、許容差、E系列、在庫値から探索します。' },
        { id: 'divider-design', group: 'passive', label: '分圧', desc: '通常分圧とVR分圧を、Vin/Vout、負荷、総抵抗、素子許容差込みで設計します。' },
        { id: 'variable-resistor', group: 'passive', label: '可変抵抗', desc: '固定抵抗とVRの組み合わせで、基準値、可変幅、端点誤差を確認します。' },
        { id: 'eia96', group: 'passive', label: 'EIA-96早見表', desc: 'チップ抵抗器のEIA-96コードを抵抗値へ変換し、倍率別の早見表と値検索を確認します。' },
        { id: 'adc', group: 'measure', label: 'ADCスケーリング', desc: '入力電圧をADCデジタルコードに変換し、スケーリング係数・LSBサイズ・フルスケール誤差を計算します。' },
        { id: 'cap-life', group: 'margin', label: 'コンデンサ寿命', desc: 'アレニウス則に基づき、動作温度・リプル電流から電解コンデンサの推定寿命を算出します。' },
        { id: 'divider', group: 'measure', label: 'NTC/PTC温度変換', desc: 'サーミスタ測定回路の温度変換、温度-電圧カーブ、感度の良い/悪い温度域、自己発熱、ADCコード表を確認します。' },
        { id: 'shunt', group: 'measure', label: '電流検出', desc: 'シャント抵抗の両端電圧と消費電力から電流値を求め、検出回路の設計値を評価します。' },
        { id: 'power', group: 'margin', label: '電源余裕', desc: '供給電力と各負荷の消費電力を比較し、電源の余裕度（マージン）を確認します。' },
        { id: 'battery-runtime', group: 'margin', label: 'バッテリー稼働', desc: '電池容量、種類、周期負荷から平均電流、稼働時間、放電電圧カーブを見積もります。' },
        { id: 'comparator', group: 'margin', label: '比較器', desc: 'Vref方式またはVcc分圧方式を選び、R1/R3入力側ヒステリシスのしきい値と余裕を計算します。' },
        { id: 'thermal', group: 'margin', label: '熱設計', desc: '熱抵抗チェーンを積み上げ、接合温度を推定します。放熱板・TIM・パッケージ熱抵抗を考慮。' },
        { id: 'interface', group: 'measure', label: 'IF余裕', desc: 'VOH/VOL/VIH/VILを入力してロジックインタフェースの電圧余裕（ノイズマージン）を評価します。' },
        { id: 'tolerance', group: 'margin', label: '誤差/歩留まり', desc: '部品公差の最悪値/RSSと、正規分布前提の歩留まりを同じフォームで確認します。' },
        { id: 'bode', group: 'margin', label: '周波数応答', desc: '一次RCフィルタのカットオフ、指定周波数でのゲイン、位相を見積もります。' },
        { id: 'ovp', group: 'protection', label: '過電圧保護', desc: '直列抵抗、クランプ電圧、入力過電圧から保護素子電流と損失を確認します。' },
        { id: 'tvs', group: 'protection', label: 'TVS保護', desc: 'サージ電圧とインピーダンスからTVSのピーク電流・ピーク電力を見積もります。' },
        { id: 'fuse', group: 'protection', label: 'ヒューズ選定', desc: '定格電流、負荷電流、周囲温度からヒューズ選定の余裕を確認します。' },
        { id: 'polyfuse', group: 'protection', label: 'ポリスイッチ', desc: '保持電流・抵抗・負荷電流から発熱と保持余裕を確認します。' },
        { id: 'protection', group: 'protection', label: '保護協調', desc: 'OVP/TVS/ヒューズ/PTC/eFuse/逆接保護を同じ故障順序で確認します。' },
        { id: 'logic-ic', group: 'reference', label: 'ロジックIC参照', desc: '74xx/40xx系の機能、入力数、電源範囲、出力形式から置換候補と注意点を確認します。' },
        { id: 'connector', group: 'reference', label: 'コネクタ設計/ピン配置', desc: '写真/図つきカタログ、ピン割付、ピン別電圧/電流、derating、ケーブル/BOM注記を確認します。' },
        { id: 'cable', group: 'reference', label: 'ケーブル判定', desc: '両端ピン列を比較し、ストレート/クロス/カスタム配線を判定します。' },
        { id: 'jumper', group: 'reference', label: '0Ω/Jumper整理', desc: '0Ω、未実装、ジャンパ設定の目的と量産状態を一覧化します。' },
        { id: 'startup', group: 'protection', label: '起動診断', desc: '電源レール、依存関係、リセット解除順をテンプレートで確認します。' },
    ];
    // 目的: 設計解析ツール本体のtool Group For Tool Idを扱う。機能: 入力値を検証・整形し、画面または計算処理へ渡す。入力: 関数シグネチャの値。出力: 処理結果またはなし。動作条件: 設計解析ツール本体の初期化後に呼び出す。副作用: なし。
    const toolGroupForToolId = (toolId) => defaultTools.find((tool) => tool.id === normalizeToolId(toolId))?.group ?? 'passive';
    const storedToolGroup = readLocalValue(DESIGN_TOOL_GROUP_KEY);
    const activeToolGroup = ref(explicitToolId
        ? toolGroupForToolId(activeToolId.value)
        : (validToolGroupIds.has(storedToolGroup) ? storedToolGroup : toolGroupForToolId(activeToolId.value)));
    const tools = reactive([...defaultTools]);
    // 目的: 設計解析ツール本体のpersist Tool Orderを扱う。機能: 入力値を検証・整形し、画面または計算処理へ渡す。入力: 関数シグネチャの値。出力: 処理結果またはなし。動作条件: 設計解析ツール本体の初期化後に呼び出す。副作用: Vue状態、localStorage、DOM、HTTP通信のいずれかを更新する場合がある。
    const persistToolOrder = () => writeLocalValue(DESIGN_TOOL_ORDER_KEY, JSON.stringify(tools.map((tool) => tool.id)));
    // 目的: 設計解析ツール本体のapply Stored Tool Orderを扱う。機能: 入力値を検証・整形し、画面または計算処理へ渡す。入力: 関数シグネチャの値。出力: 処理結果またはなし。動作条件: 設計解析ツール本体の初期化後に呼び出す。副作用: Vue状態、localStorage、DOM、HTTP通信のいずれかを更新する場合がある。
    const applyStoredToolOrder = () => {
        const raw = readLocalValue(DESIGN_TOOL_ORDER_KEY);
        if (!raw) return;
        try {
            const savedOrder = JSON.parse(raw);
            if (!Array.isArray(savedOrder)) return;
            const byId = new Map(defaultTools.map((tool) => [tool.id, tool]));
            const usedIds = new Set();
            const ordered = savedOrder.map((id) => byId.get(normalizeToolId(id))).filter((tool) => {
                if (!tool || usedIds.has(tool.id)) return false;
                usedIds.add(tool.id);
                return true;
            });
            const seen = new Set(ordered.map((tool) => tool.id));
            const missing = defaultTools.filter((tool) => !seen.has(tool.id));
            tools.splice(0, tools.length, ...ordered, ...missing);
        } catch {
            removeLocalValue(DESIGN_TOOL_ORDER_KEY);
        }
    };
    // 目的: 設計解析ツール本体のmove Tool Tabを扱う。機能: 入力値を検証・整形し、画面または計算処理へ渡す。入力: 関数シグネチャの値。出力: 処理結果またはなし。動作条件: 設計解析ツール本体の初期化後に呼び出す。副作用: Vue状態、localStorage、DOM、HTTP通信のいずれかを更新する場合がある。
    const moveToolTab = (toolId, direction) => {
        const index = tools.findIndex((tool) => tool.id === normalizeToolId(toolId));
        const nextIndex = index + direction;
        if (index < 0 || nextIndex < 0 || nextIndex >= tools.length) return;
        const [tool] = tools.splice(index, 1);
        tools.splice(nextIndex, 0, tool);
        persistToolOrder();
    };
    // 目的: 設計解析ツール本体のreset Tool Orderを扱う。機能: 入力値を検証・整形し、画面または計算処理へ渡す。入力: 関数シグネチャの値。出力: 処理結果またはなし。動作条件: 設計解析ツール本体の初期化後に呼び出す。副作用: Vue状態、localStorage、DOM、HTTP通信のいずれかを更新する場合がある。
    const resetToolOrder = () => {
        tools.splice(0, tools.length, ...defaultTools);
        activeToolId.value = 'network-search';
        activeToolGroup.value = 'passive';
        removeLocalValue(DESIGN_TOOL_ORDER_KEY);
        removeLocalValue(DESIGN_TOOL_ACTIVE_KEY);
        removeLocalValue(DESIGN_TOOL_GROUP_KEY);
    };
    applyStoredToolOrder();
    if (!tools.some((tool) => tool.id === normalizeToolId(activeToolId.value))) {
        activeToolId.value = tools[0]?.id ?? 'network-search';
    }
    // 目的: 設計解析ツール本体のfirst Tool Id For Groupを扱う。機能: 入力値を検証・整形し、画面または計算処理へ渡す。入力: 関数シグネチャの値。出力: 処理結果またはなし。動作条件: 設計解析ツール本体の初期化後に呼び出す。副作用: Vue状態、localStorage、DOM、HTTP通信のいずれかを更新する場合がある。
    const firstToolIdForGroup = (groupId) => (groupId === 'all'
        ? tools[0]
        : tools.find((tool) => tool.group === groupId))?.id ?? tools[0]?.id ?? 'network-search';
    // 目的: 設計解析ツール本体のensure Active Tool Matches Groupを扱う。機能: 入力値を検証・整形し、画面または計算処理へ渡す。入力: 関数シグネチャの値。出力: 処理結果またはなし。動作条件: 設計解析ツール本体の初期化後に呼び出す。副作用: Vue状態、localStorage、DOM、HTTP通信のいずれかを更新する場合がある。
    const ensureActiveToolMatchesGroup = (groupId = activeToolGroup.value) => {
        if (groupId === 'all') return;
        const normalizedToolId = normalizeToolId(activeToolId.value);
        if (tools.some((tool) => tool.id === normalizedToolId && tool.group === groupId)) return;
        activeToolId.value = firstToolIdForGroup(groupId);
    };
    ensureActiveToolMatchesGroup();
    watch(activeToolId, (toolId) => {
        const normalizedToolId = normalizeToolId(toolId);
        if (normalizedToolId !== toolId) {
            activeToolId.value = normalizedToolId;
            return;
        }
        if (tools.some((tool) => tool.id === normalizedToolId)) {
            writeLocalValue(DESIGN_TOOL_ACTIVE_KEY, normalizedToolId);
        }
        const mode = passiveToolMode(normalizedToolId);
        if (mode && passiveNetwork.activeMode !== mode) {
            passiveNetwork.setActiveMode(mode);
        }
    }, { immediate: true, flush: 'sync' });
    // 目的: 設計解析ツール本体のset Tool Groupを扱う。機能: 入力値を検証・整形し、画面または計算処理へ渡す。入力: 関数シグネチャの値。出力: 処理結果またはなし。動作条件: 設計解析ツール本体の初期化後に呼び出す。副作用: Vue状態、localStorage、DOM、HTTP通信のいずれかを更新する場合がある。
    const setToolGroup = (groupId) => {
        const nextGroupId = validToolGroupIds.has(groupId) ? groupId : 'all';
        activeToolGroup.value = nextGroupId;
        writeLocalValue(DESIGN_TOOL_GROUP_KEY, nextGroupId);
        ensureActiveToolMatchesGroup(nextGroupId);
    };
    const activeTool = computed(() => tools.find(t => t.id === normalizeToolId(activeToolId.value)));
    const selectedToolGroup = computed(() => toolGroups.find((group) => group.id === activeToolGroup.value) ?? toolGroups[0]);
    const visibleTools = computed(() => {
        if (activeToolGroup.value === 'all') return tools;
        return tools.filter((tool) => tool.group === activeToolGroup.value);
    });
    const activeToolInVisibleGroup = computed(() => visibleTools.value.some((tool) => tool.id === normalizeToolId(activeToolId.value)));
    const hubBands = [
        { label: '共通条件', value: 'pass/fail、margin、支配要因、次アクションまで返す' },
        { label: '受動部品', value: 'R/C探索、分圧、VR分圧、可変抵抗をトップレベルツールで扱う' },
        { label: '見送り基準', value: '単発公式だけの電卓は採用せず、条件不足なら判定不能にする' },
    ];
    // 目的: 設計解析ツール本体のparse Engineering Numberを扱う。機能: 入力値を検証・整形し、画面または計算処理へ渡す。入力: 関数シグネチャの値。出力: 表示値、配列、オブジェクト、数値のいずれか。動作条件: 設計解析ツール本体の初期化後に呼び出す。副作用: なし。
    const parseEngineeringNumber = (value, fallback = 0) => {
        const parsed = parseEngineeringNumberDetail(value);
        return parsed ? parsed.value : fallback;
    };
    // 目的: 設計解析ツール本体のto Finiteを扱う。機能: 入力値を検証・整形し、画面または計算処理へ渡す。入力: 関数シグネチャの値。出力: 処理結果またはなし。動作条件: 設計解析ツール本体の初期化後に呼び出す。副作用: なし。
    const toFinite = (value, fallback = 0) => {
        const number = parseEngineeringNumber(value, Number.NaN);
        return Number.isFinite(number) ? number : fallback;
    };
    // 目的: 設計解析ツール本体のformat Numberを扱う。機能: 入力値を検証・整形し、画面または計算処理へ渡す。入力: 関数シグネチャの値。出力: 表示値、配列、オブジェクト、数値のいずれか。動作条件: 設計解析ツール本体の初期化後に呼び出す。副作用: なし。
    const formatNumber = (value, digits = 3, unit = '') => {
        const number = Number(value);
        if (!Number.isFinite(number)) return `--${unit ? ` ${unit}` : ''}`;
        return `${number.toFixed(digits)}${unit ? ` ${unit}` : ''}`;
    };
    // 目的: 設計解析ツール本体のformat Metric Valueを扱う。機能: 入力値を検証・整形し、画面または計算処理へ渡す。入力: 関数シグネチャの値。出力: 表示値、配列、オブジェクト、数値のいずれか。動作条件: 設計解析ツール本体の初期化後に呼び出す。副作用: なし。
    const formatMetricValue = (value, unit = '', digits = 3, fallback = '--') => {
        const number = Number(value);
        if (!Number.isFinite(number)) return fallback;
        return unit
            ? formatSiValue(number, unit, digits)
            : number.toFixed(digits);
    };
    // 目的: 設計解析ツール本体のunit Multiplierを扱う。機能: 入力値を検証・整形し、画面または計算処理へ渡す。入力: 関数シグネチャの値。出力: 処理結果またはなし。動作条件: 設計解析ツール本体の初期化後に呼び出す。副作用: Vue状態、localStorage、DOM、HTTP通信のいずれかを更新する場合がある。
    const unitMultiplier = (spec) => {
        const unit = normalizeEngineeringText(spec?.unit || spec?.normalized_unit || '')
            .replace(/^meg(?=Ω|v|a|w|f|hz|s|j)/iu, 'M')
            .replace(/^micro(?=Ω|v|a|w|f|hz|s|j)/iu, 'u');
        if (!unit || unit.includes('%')) return 1;
        return parseUnitPrefixFactor(unit).factor;
    };
    // 目的: 設計解析ツール本体のparse Numberを扱う。機能: 入力値を検証・整形し、画面または計算処理へ渡す。入力: 関数シグネチャの値。出力: 表示値、配列、オブジェクト、数値のいずれか。動作条件: 設計解析ツール本体の初期化後に呼び出す。副作用: なし。
    const parseNumber = (value, fallback = 0) => parseEngineeringNumber(value, fallback);
    const numericInputDrafts = reactive({});
    const numericTargetIds = new WeakMap();
    let numericTargetSequence = 0;
    // 目的: 設計解析ツール本体のnumeric Input Keyを扱う。機能: 入力値を検証・整形し、画面または計算処理へ渡す。入力: 関数シグネチャの値。出力: 処理結果またはなし。動作条件: 設計解析ツール本体の初期化後に呼び出す。副作用: Vue状態、localStorage、DOM、HTTP通信のいずれかを更新する場合がある。
    const numericInputKey = (target, key) => {
        if (!target || typeof target !== 'object') return `primitive:${String(key)}`;
        if (!numericTargetIds.has(target)) {
            numericTargetSequence += 1;
            numericTargetIds.set(target, numericTargetSequence);
        }
        return `${numericTargetIds.get(target)}:${String(key)}`;
    };
    // 目的: 設計解析ツール本体のnormalize Editable Numberを扱う。機能: 入力値を検証・整形し、画面または計算処理へ渡す。入力: 関数シグネチャの値。出力: 表示値、配列、オブジェクト、数値のいずれか。動作条件: 設計解析ツール本体の初期化後に呼び出す。副作用: なし。
    const normalizeEditableNumber = (raw, key, storedUnitFactor = 1, forceUnitConversion = false) => {
        const parsed = parseEngineeringNumberDetail(raw);
        if (!parsed) return null;
        const normalizedRaw = normalizeEngineeringText(raw);
        if (/ppm$/iu.test(normalizedRaw) && String(key).toLowerCase().includes('ppm')) {
            return Number(normalizedRaw.replace(/ppm$/iu, ''));
        }
        const scale = Number(storedUnitFactor) || 1;
        const shouldConvertToStoredUnit = scale !== 1 && (forceUnitConversion || parsed.hasUnit || parsed.hasPrefix);
        return shouldConvertToStoredUnit ? parsed.value / scale : parsed.value;
    };
    // 目的: 設計解析ツール本体のnumeric Values Equalを扱う。機能: 入力値を検証・整形し、画面または計算処理へ渡す。入力: 関数シグネチャの値。出力: 処理結果またはなし。動作条件: 設計解析ツール本体の初期化後に呼び出す。副作用: Vue状態、localStorage、DOM、HTTP通信のいずれかを更新する場合がある。
    const numericValuesEqual = (actual, expected) => {
        if (actual === expected) return true;
        const actualNumber = parseEngineeringNumber(actual, Number.NaN);
        const expectedNumber = parseEngineeringNumber(expected, Number.NaN);
        if (!Number.isFinite(actualNumber) || !Number.isFinite(expectedNumber)) return false;
        return Math.abs(actualNumber - expectedNumber) <= Math.max(1e-12, Math.abs(expectedNumber) * 1e-12);
    };
    // 目的: 設計解析ツール本体のnumeric Input Valueを扱う。機能: 入力値を検証・整形し、画面または計算処理へ渡す。入力: 関数シグネチャの値。出力: 処理結果またはなし。動作条件: 設計解析ツール本体の初期化後に呼び出す。副作用: Vue状態、localStorage、DOM、HTTP通信のいずれかを更新する場合がある。
    const numericInputValue = (target, key) => {
        const draftKey = numericInputKey(target, key);
        const draft = numericInputDrafts[draftKey];
        if (draft && numericValuesEqual(target[key], draft.value)) {
            return draft.raw;
        }
        if (draft) delete numericInputDrafts[draftKey];
        return target[key];
    };
    // 目的: 設計解析ツール本体のseed Numeric Input Draftを扱う。機能: 入力値を検証・整形し、画面または計算処理へ渡す。入力: 関数シグネチャの値。出力: 処理結果またはなし。動作条件: 設計解析ツール本体の初期化後に呼び出す。副作用: Vue状態、localStorage、DOM、HTTP通信のいずれかを更新する場合がある。
    const seedNumericInputDraft = (target, key, raw, storedUnitFactor = 1, forceUnitConversion = false) => {
        const normalized = normalizeEditableNumber(raw, key, storedUnitFactor, forceUnitConversion);
        if (normalized === null || !Number.isFinite(normalized)) return;
        numericInputDrafts[numericInputKey(target, key)] = { raw: String(raw), value: normalized };
    };
    // 目的: 設計解析ツール本体のset Numeric Inputを扱う。機能: 入力値を検証・整形し、画面または計算処理へ渡す。入力: 関数シグネチャの値。出力: 処理結果またはなし。動作条件: 設計解析ツール本体の初期化後に呼び出す。副作用: Vue状態、localStorage、DOM、HTTP通信のいずれかを更新する場合がある。
    const setNumericInput = (target, key, value, storedUnitFactor = 1, forceUnitConversion = false) => {
        const raw = typeof value === 'object' && value?.target ? value.target.value : value;
        const draftKey = numericInputKey(target, key);
        if (raw === null || raw === undefined || String(raw).trim() === '') {
            target[key] = '';
            numericInputDrafts[draftKey] = { raw: '', value: '' };
            return;
        }
        const normalized = normalizeEditableNumber(raw, key, storedUnitFactor, forceUnitConversion);
        if (normalized !== null && Number.isFinite(normalized)) {
            target[key] = normalized;
            numericInputDrafts[draftKey] = { raw: String(raw), value: normalized };
            return;
        }
        numericInputDrafts[draftKey] = { raw: String(raw), value: target[key] };
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
    }) => {
        const issueDetails = [
            ...warnings.filter(Boolean),
            ...(missingConditions.length ? [`不足条件: ${missingConditions.join('、')}`] : []),
        ];
        const needsIssueHeadline = tone !== 'ok' && issueDetails.length > 0;
        const vagueSummary = /どれか|いずれか|確認が必要|不足または薄い|追加確認が必要|基準を満たさない|判定モデルが未定義/u.test(summary);
        const issueHeadline = issueDetails.length > 1
            ? `${issueDetails[0]} 他${issueDetails.length - 1}件: ${issueDetails.slice(1, 3).join(' / ')}`
            : issueDetails[0];
        const resolvedSummary = needsIssueHeadline
            ? (vagueSummary ? issueHeadline : `${summary} 対象: ${issueDetails.slice(0, 3).join(' / ')}`)
            : summary;
        const summaryLines = needsIssueHeadline
            ? (
                vagueSummary
                    ? [
                        ...issueDetails.slice(0, 4),
                        ...(issueDetails.length > 4 ? [`他${issueDetails.length - 4}件の確認事項があります。`] : []),
                    ]
                    : [
                        summary,
                        ...issueDetails.slice(0, 4),
                        ...(issueDetails.length > 4 ? [`他${issueDetails.length - 4}件の確認事項があります。`] : []),
                    ]
            )
            : [summary];
        return {
            verdict,
            tone,
            summary: resolvedSummary,
            summaryLines,
            metrics,
            dominantFactors,
            warnings,
            nextActions,
            assumptions,
            missingConditions,
            margin,
            copySummary: copySummary || `${verdict}: ${resolvedSummary}`,
            candidateLinks,
        };
    };
    // 目的: 設計解析ツール本体のhas Ratingを扱う。機能: 入力値を検証・整形し、画面または計算処理へ渡す。入力: 関数シグネチャの値。出力: 真偽値。動作条件: 設計解析ツール本体の初期化後に呼び出す。副作用: なし。
    const hasRating = (value) => {
        if (value === null || value === undefined) return false;
        if (typeof value === 'string' && value.trim() === '') return false;
        return Number.isFinite(parseEngineeringNumber(value, Number.NaN));
    };
    // 目的: 設計解析ツール本体のrating Missingを扱う。機能: 入力値を検証・整形し、画面または計算処理へ渡す。入力: 関数シグネチャの値。出力: 処理結果またはなし。動作条件: 設計解析ツール本体の初期化後に呼び出す。副作用: Vue状態、localStorage、DOM、HTTP通信のいずれかを更新する場合がある。
    const ratingMissing = (ratings) => ratings.filter((rating) => !hasRating(rating.value)).map((rating) => rating.label);
    const diagramFocus = ref(null);
    // 目的: 設計解析ツール本体のfocus Diagramを扱う。機能: 入力値を検証・整形し、画面または計算処理へ渡す。入力: 関数シグネチャの値。出力: 処理結果またはなし。動作条件: 設計解析ツール本体の初期化後に呼び出す。副作用: Vue状態、localStorage、DOM、HTTP通信のいずれかを更新する場合がある。
    const focusDiagram = (key) => {
        if (key) diagramFocus.value = key;
    };
    // 目的: 設計解析ツール本体のclear Diagram Focusを扱う。機能: 入力値を検証・整形し、画面または計算処理へ渡す。入力: 関数シグネチャの値。出力: 処理結果またはなし。動作条件: 設計解析ツール本体の初期化後に呼び出す。副作用: Vue状態、localStorage、DOM、HTTP通信のいずれかを更新する場合がある。
    const clearDiagramFocus = () => {
        diagramFocus.value = null;
    };
    // 目的: 設計解析ツール本体のis Diagram Focusedを扱う。機能: 入力値を検証・整形し、画面または計算処理へ渡す。入力: 関数シグネチャの値。出力: 真偽値。動作条件: 設計解析ツール本体の初期化後に呼び出す。副作用: なし。
    const isDiagramFocused = (key) => diagramFocus.value === key;
    // 目的: 設計解析ツール本体のdiagram Item Classを扱う。機能: 入力値を検証・整形し、画面または計算処理へ渡す。入力: 関数シグネチャの値。出力: 処理結果またはなし。動作条件: 設計解析ツール本体の初期化後に呼び出す。副作用: Vue状態、localStorage、DOM、HTTP通信のいずれかを更新する場合がある。
    const diagramItemClass = (key) => {
        const active = diagramFocus.value === key;
        const dimmed = diagramFocus.value && diagramFocus.value !== key;
        return {
            'is-active': active,
            'is-dimmed': dimmed,
        };
    };

    const {
        eia96, eia96BaseValues, eia96Multipliers, eia96Lookup, eia96ReverseMatches,
        eia96SelectedRows, eia96FilteredRows, eia96MultiplierBySelected, formatResistanceValue,
    } = setupEia96Tool({ computed, reactive, parseEngineeringNumber });

    const quickForms = reactive({
        tolerance: { nominal: 1000, tolerancePct: 1, count: 2, lsl: 1980, usl: 2020, mean: 2000, sigma: 5, errorSources: 'R1,1\nR2,1\nADC,0.05', monteCarloRuns: 10000, targetCenter: 2000 },
        bode: { type: 'lowpass', r: 10000, c: 0.00000001, freq: 1000, rTolerancePct: 1, cTolerancePct: 10, passbandFreq: 100, stopbandFreq: 10000 },
        ovp: { vinMax: 24, vClamp: 5.6, seriesR: 1000, loadCurrent: 0.002, currentRating: '', powerRating: '', seriesPowerRating: '' },
        tvs: { surgeV: 1000, lineImpedance: 42, clampV: 33, pulseMs: 1, waveformFactor: 0.5, peakPowerRating: '', energyRating: '' },
        fuse: { ratedCurrent: 2, loadCurrent: 1.2, ambient: 50, deratingPct: 25, currentRating: '' },
        polyfuse: { holdCurrent: 0.75, tripCurrent: 1.5, loadCurrent: 0.5, resistance: 0.4, ambient: 40, holdCurrentRating: '', powerRating: '' },
        protection: { faultV: 24, faultCurrent: 3, tvsPowerRating: 600, fuseI2t: 10, ptcHold: 0.75, efuseLimit: 2, reverseDrop: 0.4, loadCurrent: 0.6 },
        'logic-ic': {
            family: '74HC',
            function: 'nand',
            inputs: 2,
            packagePins: 14,
            supplyV: 3.3,
            outputType: 'push-pull',
        },
        connector: {
            selectedTemplateId: 'usb-c-receptacle',
            pins: 24,
            currentPerPin: 1,
            environment: 'external',
            connectorType: 'USB',
            viewSide: 'mating-face',
            pin1Mark: 'key-notch',
            pitchMm: 0.5,
            currentRatingPerPin: 1.25,
            voltageRatingV: 20,
            tempRiseLimit: '',
            deratingPct: 80,
            matingPart: 'USB Type-C plug',
            photoUrl: '',
            diagramUrl: '',
            datasheetUrl: '',
            bomNote: 'USB-Cレセプタクルの嵌合面ピン配置を図面へ添付',
            silkNote: 'Pin1/CC1/CC2/Shieldをシルクまたは組立図で明示',
            pinAssignments: [
                'A1,GND,ground,0,0.8,black,24,シェル近傍GND',
                'A4,VBUS,power,5,0.8,red,24,VBUS電源',
                'A5,CC1,signal,5,0.001,white,30,CC pull設定',
                'A6,D+,diff,3.3,0.02,green,30,USB2 pair',
                'A7,D-,diff,3.3,0.02,white,30,USB2 pair',
                'B4,VBUS,power,5,0.8,red,24,VBUS電源',
                'B5,CC2,signal,5,0.001,white,30,CC pull設定',
                'B6,D+,diff,3.3,0.02,green,30,USB2 pair',
                'B7,D-,diff,3.3,0.02,white,30,USB2 pair',
                'B12,GND,ground,0,0.8,black,24,シェル近傍GND',
            ].join('\n'),
            userTemplateName: '',
            userTemplateStandard: '',
            userTemplatePins: 2,
            userTemplateRows: 1,
            userTemplatePitchMm: 2.54,
            userTemplateGender: '',
            userTemplateVoltageRatingV: 50,
            userTemplateCurrentRatingPerPin: 1,
            userTemplateNumbering: 'Pin1から昇順',
            userTemplatePhotoUrl: '',
            userTemplateDiagramUrl: '',
            userTemplateDatasheetUrl: '',
            userTemplateMatingPart: '',
            userTemplateNotes: '',
        },
        cable: { endA: '1,2,3,4', endB: '1,2,3,4' },
        jumper: { entries: 'JP1,0Ω,debug,未実装,未実装,BOM DNP\nR105,0Ω,variant,実装,実装,BOM mount\nJP_BOOT,ジャンパ,boot,切替,未実装,BOM option' },
        startup: { template: 'pmic-mcu', rails: 'VIN,,10\n3V3,VIN,5\n1V8,3V3,3\nRESET,3V3,20', pgSignals: 'PG_3V3,3V3,5\nRESET_MCU,3V3,20', partialPowerPaths: 'I2C_SDA->MCU_VDD\nUSB_D+->3V3', resetHoldMs: 20 },
    });

    const {
        connectorCatalog, connectorActiveTemplate, connectorTemplateOptions, connectorPinMap,
        connectorSummary, connectorAssignments, connectorUserTemplates,
        applyConnectorTemplate, saveConnectorTemplate,
    } = setupConnectorTool({ computed, ref, quickForms, toFinite, hasRating });

    const {
        LOGIC_CONNECTION_FAMILY_OPTIONS, LOGIC_ALL_FAMILY_OPTIONS, LOGIC_OUTPUT_OPTIONS,
        LOGIC_FUNCTION_OPTIONS, logicLevelCompatibility, uniqueLogicParts, logicCatalog,
        withLogicPartSpec, logicFamilyMatches, logicFunctionMatches,
    } = setupLogicReferenceTool({ toFinite });

    const { quickTool } = setupQuickTools({
        computed, activeToolId, quickForms, toFinite, hasRating, ratingMissing,
        connectorActiveTemplate, connectorPinMap, connectorSummary, connectorTemplateOptions,
        LOGIC_CONNECTION_FAMILY_OPTIONS, LOGIC_ALL_FAMILY_OPTIONS, LOGIC_OUTPUT_OPTIONS,
        LOGIC_FUNCTION_OPTIONS, logicCatalog, withLogicPartSpec, uniqueLogicParts,
        logicFamilyMatches, logicFunctionMatches,
    });

    const {
        adc, adcResult, cap, capResult, divider, dividerResult, dividerGraph,
        dividerGraphCursor, dividerGraphTooltip, updateDividerGraphCursor, clearDividerGraphCursor,
        shunt, shuntResult, power, powerResult, addLoad, removeLoad,
        battery, batteryProfiles, batteryResult, batteryGraph, batteryCapacityPie,
        batteryGraphCursor, batteryGraphTooltipBox, updateBatteryGraphCursor, clearBatteryGraphCursor,
        addBatteryLoad, removeBatteryLoad, formatRuntimeText, comp, compResult, thermal,
        thermalResult, thermalReferences, addNode, removeNode, iface, ifaceResult,
    } = setupAnalogDesignTools({
        computed, reactive, toFinite, hasRating, parseNumber, formatNumber,
        setupBatteryRuntimeTool, logicLevelCompatibility,
    });

    const { activeDiagram } = setupDesignToolDiagrams({
        computed, activeToolId, passiveToolMode, passiveNetwork, eia96, eia96Lookup,
        eia96MultiplierBySelected, adc, adcResult, cap, capResult, divider, dividerResult,
        shunt, shuntResult, power, powerResult, battery, batteryProfiles, batteryResult,
        comp, compResult, thermal, thermalResult, iface, ifaceResult, quickForms,
        connectorActiveTemplate, connectorSummary, connectorPinMap,
    });

    // 目的: 設計解析ツール本体のfieldを扱う。機能: 入力値を検証・整形し、画面または計算処理へ渡す。入力: 関数シグネチャの値。出力: 処理結果またはなし。動作条件: 設計解析ツール本体の初期化後に呼び出す。副作用: Vue状態、localStorage、DOM、HTTP通信のいずれかを更新する場合がある。
    const field = (target, key, label, group = '基本条件', type = 'number', diagramKey = key, storedUnitFactor = 1, forceUnitConversion = storedUnitFactor !== 1) => ({
        target, key, label, group, type, diagramKey, storedUnitFactor, forceUnitConversion,
    });
    const toolSupplementInputGroups = computed(() => {
        const groups = {
            adc: [field(adc, 'vinMin', '入力電圧 最小 (V)'), field(adc, 'vinTyp', '入力電圧 標準 (V)'), field(adc, 'vinMax', '入力電圧 最大 (V)'), field(adc, 'physicalMin', '物理量 最小'), field(adc, 'physicalMax', '物理量 最大'), field(adc, 'fixedPointBits', '固定小数点Qビット')],
            'cap-life': [field(cap, 'rippleCurrent', 'リプル電流 (A)'), field(cap, 'esr', 'ESR (Ω)'), field(cap, 'ambientWorst', '周囲温度 最悪 (℃)'), field(cap, 'targetLifeY', '目標寿命 (年)'), field(cap, 'voltageDeratingPct', '電圧ディレーティング (%)')],
            divider: [field(divider, 'tempMin', '温度掃引 最小 (℃)'), field(divider, 'tempMax', '温度掃引 最大 (℃)'), field(divider, 'tempStep', '温度掃引 刻み (℃)'), field(divider, 'pullupCandidates', '固定抵抗候補 (Ω)', '部品定格', 'text'), field(divider, 'adcBits', 'ADCビット数'), field(divider, 'adcVref', 'ADC基準電圧 (V)')],
            shunt: [field(shunt, 'tcrPpm', 'Rs TCR (ppm/℃)'), field(shunt, 'ampOffsetUv', 'アンプオフセット (V)', '最悪条件', 'number', 'gain', 1e-6)],
            power: [field(power, 'efficiencyPct', '効率 (%)'), field(power, 'dropoutV', 'ドロップアウト (V)'), field(power, 'inrushA', '突入電流 (A)'), field(power, 'maxLoadFactor', '最大負荷倍率'), field(power, 'rails', 'レール定義', '部品定格', 'textarea')],
            comparator: [field(comp, 'tolerancePct', '抵抗/基準公差 (%)'), field(comp, 'inputOffsetMv', '入力オフセット (V)', '最悪条件', 'number', 'R1', 1e-3), field(comp, 'inputBiasNa', '入力バイアス (A)', '最悪条件', 'number', 'R1', 1e-9), field(comp, 'noiseMv', 'ノイズ振幅 (V)', '最悪条件', 'number', 'R3', 1e-3), field(comp, 'candidateResistors', '抵抗候補 (Ω)', '部品定格', 'text')],
            thermal: [field(thermal, 'scenarioMultiplier', '最悪発熱倍率'), field(thermal, 'deratingSlope', 'ディレーティング傾き (℃/℃)'), field(thermal, 'heatsinkCandidates', '放熱候補 θsa (℃/W)', '部品定格', 'text')],
            interface: [field(iface, 'tempMin', '温度 最小 (℃)'), field(iface, 'tempMax', '温度 最大 (℃)'), field(iface, 'uartNominalBaud', 'UART公称ボーレート'), field(iface, 'uartActualBaud', 'UART実ボーレート'), field(iface, 'i2cBusCapPf', 'I2Cバス容量 (F)', '最悪条件', 'number', 'family', 1e-12), field(iface, 'pullupOhm', 'I2C Pull-up (Ω)'), field(iface, 'i2cSinkMaLimit', 'I2C Lowシンク上限 (A)', '部品定格', 'number', 'family', 1e-3)],
        };
        const fields = groups[normalizeToolId(activeToolId.value)] ?? [];
        return Object.entries(fields.reduce((acc, item) => {
            (acc[item.group] ??= []).push(item);
            return acc;
        }, {})).map(([group, items]) => ({ group, fields: items }));
    });

    // 目的: 設計解析ツール本体のclone Plainを扱う。機能: 入力値を検証・整形し、画面または計算処理へ渡す。入力: 関数シグネチャの値。出力: 処理結果またはなし。動作条件: 設計解析ツール本体の初期化後に呼び出す。副作用: Vue状態、localStorage、DOM、HTTP通信のいずれかを更新する場合がある。
    const clonePlain = (value) => JSON.parse(JSON.stringify(value));
    // 目的: 設計解析ツール本体のpassive Network Input Payloadを扱う。機能: 入力値を検証・整形し、画面または計算処理へ渡す。入力: 関数シグネチャの値。出力: 処理結果またはなし。動作条件: 設計解析ツール本体の初期化後に呼び出す。副作用: Vue状態、localStorage、DOM、HTTP通信のいずれかを更新する場合がある。
    const passiveNetworkInputPayload = (toolId) => {
        const mode = passiveToolMode(toolId);
        if (!mode) return null;
        return { mode, form: clonePlain(passiveNetwork.form), variable: clonePlain(passiveNetwork.variable), dividerVariable: clonePlain(passiveNetwork.dividerVariable) };
    };
    // 目的: 設計解析ツール本体のapply Passive Network Payloadを扱う。機能: 入力値を検証・整形し、画面または計算処理へ渡す。入力: 関数シグネチャの値。出力: 処理結果またはなし。動作条件: 設計解析ツール本体の初期化後に呼び出す。副作用: Vue状態、localStorage、DOM、HTTP通信のいずれかを更新する場合がある。
    const applyPassiveNetworkPayload = (toolId, payload = {}) => {
        const mode = passiveToolMode(toolId);
        if (!mode) return false;
        passiveNetwork.setActiveMode(mode);
        if (payload.form) Object.assign(passiveNetwork.form, payload.form);
        if (payload.variable) Object.assign(passiveNetwork.variable, payload.variable);
        if (payload.dividerVariable) Object.assign(passiveNetwork.dividerVariable, payload.dividerVariable);
        return true;
    };
    // 目的: 設計解析ツール本体のtool Payload Targetsを扱う。機能: 入力値を検証・整形し、画面または計算処理へ渡す。入力: 関数シグネチャの値。出力: 処理結果またはなし。動作条件: 設計解析ツール本体の初期化後に呼び出す。副作用: なし。
    const toolPayloadTargets = () => ({ adc, 'cap-life': cap, divider, shunt, power, 'battery-runtime': battery, comparator: comp, thermal, interface: iface });
    // 目的: 設計解析ツール本体のapply Tool Payloadを扱う。機能: 入力値を検証・整形し、画面または計算処理へ渡す。入力: 関数シグネチャの値。出力: 処理結果またはなし。動作条件: 設計解析ツール本体の初期化後に呼び出す。副作用: Vue状態、localStorage、DOM、HTTP通信のいずれかを更新する場合がある。
    const applyToolPayload = (toolId, payload = {}) => {
        const normalizedToolId = normalizeToolId(toolId);
        if (applyPassiveNetworkPayload(normalizedToolId, payload)) return;
        const target = quickForms[normalizedToolId] ?? toolPayloadTargets()[normalizedToolId];
        if (!target || typeof payload !== 'object') return;
        Object.assign(target, clonePlain(payload));
    };
    // 目的: 設計解析ツール本体のapi Jsonを扱う。機能: 入力値を検証・整形し、画面または計算処理へ渡す。入力: 関数シグネチャの値。出力: 処理結果またはなし。動作条件: 設計解析ツール本体の初期化後に呼び出す。副作用: Vue状態、localStorage、DOM、HTTP通信のいずれかを更新する場合がある。
    const apiJson = async (url, options = {}) => {
        const token = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') ?? '';
        const response = await fetch(url, {
            credentials: 'same-origin',
            headers: { Accept: 'application/json', ...(options.body !== undefined ? { 'Content-Type': 'application/json' } : {}), ...(token ? { 'X-CSRF-TOKEN': token } : {}) },
            ...options,
        });
        if (response.status === 204) return {};
        const data = await response.json().catch(() => ({}));
        if (!response.ok) {
            const error = new Error(data.message || 'APIエラー (' + response.status + ')');
            error.status = response.status;
            throw error;
        }
        return data;
    };
    const currentInputPayload = computed(() => {
        const normalizedToolId = normalizeToolId(activeToolId.value);
        const passivePayload = passiveNetworkInputPayload(normalizedToolId);
        if (passivePayload) return passivePayload;
        return clonePlain(quickForms[normalizedToolId] ?? toolPayloadTargets()[normalizedToolId] ?? {});
    });

    const candidateLinksForActiveTool = computed(() => []);
    // 目的: 設計解析ツール本体のdesign Reportを扱う。機能: 入力値を検証・整形し、画面または計算処理へ渡す。入力: 関数シグネチャの値。出力: 処理結果またはなし。動作条件: 設計解析ツール本体の初期化後に呼び出す。副作用: Vue状態、localStorage、DOM、HTTP通信のいずれかを更新する場合がある。
    const designReport = (data) => report({
        assumptions: activeDiagram.value?.assumptions ?? [],
        candidateLinks: candidateLinksForActiveTool.value,
        ...data,
    });
    // 目的: 設計解析ツール本体のquick Design Reportを扱う。機能: 入力値を検証・整形し、画面または計算処理へ渡す。入力: 関数シグネチャの値。出力: 処理結果またはなし。動作条件: 設計解析ツール本体の初期化後に呼び出す。副作用: Vue状態、localStorage、DOM、HTTP通信のいずれかを更新する場合がある。
    const quickDesignReport = () => {
        const qt = quickTool.value;
        if (!qt) return designReport({ verdict: 'CHECK', tone: 'neutral', summary: '判定モデルを確認します。', metrics: [{ label: '状態', value: 'CHECK' }], nextActions: ['判定条件を確認する'] });
        const quickTone = qt.tone === 'bad' ? 'bad' : (qt.tone === 'warn' ? 'warn' : (qt.tone === 'check' ? 'neutral' : 'ok'));
        const checkOnlyQuickModels = new Set(['tolerance', 'bode', 'cable', 'jumper', 'startup']);
        const quickVerdict = qt.tone === 'check' || (quickTone === 'ok' && checkOnlyQuickModels.has(qt.model)) ? 'CHECK' : (quickTone === 'bad' ? 'FAIL' : (quickTone === 'warn' ? 'WARN' : 'PASS'));
        return designReport({
            verdict: quickVerdict,
            tone: quickTone,
            summary: qt.summary || qt.title + ' の計算値を、設計上の確認項目へ展開しています。',
            metrics: qt.rows.map((row) => ({ label: row[0], value: row[1] })),
            dominantFactors: qt.dominantFactors ?? ['入力条件'],
            warnings: qt.warnings ?? [],
            nextActions: qt.nextActions ?? ['部品定格と最悪条件を追加して判定する'],
            missingConditions: qt.missingConditions ?? [],
            margin: qt.margin ?? null,
            copySummary: quickVerdict + ': ' + qt.title + '。' + (qt.summary || '計算値を設計確認項目へ展開しています。'),
            candidateLinks: qt.candidateLinks ?? candidateLinksForActiveTool.value,
        });
    };
    const analysisReport = computed(() => {
        const toolId = normalizeToolId(activeToolId.value);
        if (passiveToolMode(toolId)) {
            return designReport({ verdict: 'CHECK', tone: 'neutral', summary: (activeTool.value?.label ?? toolId) + ' の候補と条件を確認してください。', metrics: passiveNetwork.statusMetrics.map((item) => ({ label: item.label, value: item.value })), dominantFactors: ['目標値', 'E系列', '許容差'], warnings: passiveNetwork.warnings ?? [], nextActions: (passiveNetwork.nextActions?.length ? passiveNetwork.nextActions : ['候補を比較する']), missingConditions: ['温度係数', '抵抗電力', '実装条件'] });
        }
        if (toolId === 'eia96') {
            const lookup = eia96Lookup.value;
            return designReport({ verdict: lookup.valid ? 'CHECK' : 'WARN', tone: lookup.valid ? 'neutral' : 'warn', summary: lookup.valid ? lookup.normalized + ' は ' + lookup.display + ' です。' : (lookup.warnings?.[0] ?? 'EIA-96コードを確認してください。'), metrics: [{ label: 'EIA-96コード', value: lookup.normalized || eia96.codeQuery }, { label: '抵抗値', value: lookup.valid ? lookup.display : 'CHECK' }, { label: '逆引き候補', value: eia96ReverseMatches.value[0]?.code ?? '未入力' }], dominantFactors: ['3文字マーキング', '倍率文字'], warnings: lookup.warnings ?? [], nextActions: ['BOM値、サイズ、メーカー表記と照合する'], missingConditions: ['BOM値', 'サイズ', 'メーカー表記'] });
        }
        if (toolId === 'power') {
            const supplyExceeded = powerResult.value.supplyExceeded || powerResult.value.railMargins.some((rail) => rail.overloaded);
            const unresolvedInrush = toFinite(power.inrushA) > 0;
            const largest = power.loads.reduce((max, load) => (toFinite(load.mA) * toFinite(load.V) / 1000) > max.watts ? { label: load.label || '負荷', watts: toFinite(load.mA) * toFinite(load.V) / 1000 } : max, { label: '', watts: -Infinity });
            const warnings = [...powerResult.value.railMargins.filter((rail) => rail.overloaded).map((rail) => rail.name + 'レールが過負荷です。'), ...(powerResult.value.unassignedLoads.length ? ['未割当負荷があります。'] : []), ...(unresolvedInrush ? ['突入電流の時間幅が未定義です。'] : [])];
            return designReport({ verdict: supplyExceeded ? 'FAIL' : 'CHECK', tone: supplyExceeded ? 'bad' : 'neutral', summary: supplyExceeded ? '供給、負荷、レール、突入条件を確認してください。 ' + warnings.join(' / ') : '通常負荷とレール余裕は概算上成立しています。突入電流の時間幅を確認してください。', metrics: [{ label: '負荷合計', value: powerResult.value.totalW + ' W' }, { label: '上流換算負荷', value: powerResult.value.inputEquivalentW + ' W' }, { label: '通常余裕', value: powerResult.value.margin + ' W' }, { label: '最大負荷', value: largest.label + ' ' + largest.watts.toFixed(3) + ' W' }], dominantFactors: [largest.label, 'レールツリー', '効率'], warnings, nextActions: ['レール割当、効率、dropout、突入電流を確認する'], missingConditions: ['突入電流の時間幅'] });
        }
        if (toolId === 'battery-runtime') {
            const result = batteryResult.value;
            const invalidLoadWarnings = result.loads.flatMap((load) => [
                ...(!load.efficiencyValid ? [load.name + ' の効率 ' + load.rawEfficiencyPct + '% は0%超かつ100%以下が必要です。'] : []),
                ...(load.durationSec > toFinite(battery.cycleSec) ? [load.name + ' のON秒数 ' + load.durationSec.toFixed(3) + ' s が周期 ' + toFinite(battery.cycleSec).toFixed(3) + ' s を超えています。'] : []),
            ]);
            const capacityRuntimeH = parseNumber(result.capacityRuntimeHours);
            const runtimeToMinH = parseNumber(result.runtimeToMinVoltageHours);
            const voltageCutsRuntime = runtimeToMinH < capacityRuntimeH;
            const runtimeText = formatRuntimeText(parseNumber(result.runtimeHours));
            const tone = invalidLoadWarnings.length ? 'bad' : (voltageCutsRuntime ? 'warn' : 'neutral');
            return designReport({ verdict: tone === 'bad' ? 'FAIL' : (tone === 'warn' ? 'WARN' : 'CHECK'), tone, summary: invalidLoadWarnings[0] || (voltageCutsRuntime ? '電圧下限到達 ' + runtimeToMinH.toFixed(2) + ' h が容量ベース ' + capacityRuntimeH.toFixed(2) + ' h より先です。システム最低電圧 ' + parseNumber(result.systemMinVoltage).toFixed(3) + ' V を確認してください。' : '実効稼働時間は ' + runtimeText + ' です。'), metrics: [{ label: '実効稼働時間', value: runtimeText }, { label: 'ON秒数合計(重複可)', value: result.totalDurationSec + ' s' }, { label: 'Wh/周期', value: formatSiValue(result.whPerCycle, 'Wh') + ' / ' + formatSiValue(result.mahPerCycle, 'mAh') }, { label: '平均電力', value: formatSiValue(result.averagePowerW, 'W') }, { label: '平均電流', value: formatSiValue(result.averageCurrentMa, 'mA') }, { label: 'ピーク負荷', value: formatSiValue(result.peakPowerW, 'W') + ' / ' + result.peakLoadName }, { label: '容量ベース時間', value: formatRuntimeText(capacityRuntimeH) }, { label: '電圧下限到達時間', value: formatRuntimeText(runtimeToMinH) }], dominantFactors: [result.dominantLoadName, '平均電流', '使用可能容量'], warnings: invalidLoadWarnings, nextActions: ['実測電流プロファイル、温度、劣化後容量、レギュレータ効率で再見積もりする'], missingConditions: ['温度条件', '電池劣化', '効率'] });
        }
        if (toolId === 'adc') {
            const clipped = adcResult.value.clipped || adc.vinMax > adc.vref || adc.vref <= 0;
            return designReport({ verdict: clipped ? 'FAIL' : 'CHECK', tone: clipped ? 'bad' : 'neutral', summary: clipped ? 'ADC入力範囲がVrefを超えています。' : 'ADCコードとスケーリング係数を確認できます。', metrics: [{ label: 'ADCコード', value: String(adcResult.value.code) }, { label: 'LSB', value: adcResult.value.lsb_v + ' V' }, { label: '固定小数点係数', value: String(adcResult.value.fw_scale) }], dominantFactors: ['Vin', 'Vref', 'オフセット'], warnings: clipped ? ['入力上限がVrefを超えています。'] : [], nextActions: ['入力源インピーダンスとサンプル時間を確認する'], missingConditions: ['入力源インピーダンス', 'サンプル時間'] });
        }
        if (toolId === 'thermal') {
            const tj = parseNumber(thermalResult.value.Tjunction);
            const worstTj = parseNumber(thermalResult.value.worstTjunction);
            const margin = thermal.TjLimit - tj;
            const tone = margin < 0 ? 'bad' : (worstTj > thermal.TjLimit ? 'warn' : 'neutral');
            const maxNode = thermal.nodes.reduce((max, node) => toFinite(node.Rth) > max.Rth ? node : max, { label: '', Rth: -Infinity });
            return designReport({ verdict: tone === 'bad' ? 'FAIL' : (tone === 'warn' ? 'WARN' : 'CHECK'), tone, summary: tone === 'bad' ? '通常Tjが上限を超えています。' : '通常Tj、最悪Tj、ディレーティング余裕を確認できます。', metrics: [{ label: 'Tj余裕', value: formatNumber(margin, 1, '℃') }, { label: '最悪Tj', value: thermalResult.value.worstTjunction + ' ℃' }, { label: '支配熱抵抗', value: maxNode.label + ' ' + formatNumber(maxNode.Rth, 2, '℃/W') }], dominantFactors: [maxNode.label || '熱抵抗チェーン', '消費電力', '周囲温度'], warnings: tone === 'warn' ? ['最悪発熱シナリオでTj上限を超えます。'] : [], nextActions: ['支配熱抵抗と放熱候補を確認する'], missingConditions: ['基板銅箔条件', '筐体/風速条件'] });
        }
        if (toolId === 'comparator') {
            const noiseMargin = parseNumber(compResult.value.noise_margin);
            const high = parseNumber(compResult.value.Vth_rising);
            const low = parseNumber(compResult.value.Vth_falling);
            const bad = high < 0 || low < 0 || high > comp.Vcc || low > comp.Vcc;
            const r3Disconnected = toFinite(comp.R3) <= 0;
            const metrics = [
                { label: '入力極性', value: compResult.value.input_polarity_label },
                { label: '基準方式', value: comp.referenceMode === 'divider' ? 'Vcc分圧' : 'Vref外部基準' },
                { label: 'R1/R3比', value: compResult.value.feedback_ratio },
                { label: 'Vin上昇時しきい値', value: `${formatNumber(high, 4, 'V')} / ${compResult.value.rising_transition}` },
                { label: 'Vin下降時しきい値', value: `${formatNumber(low, 4, 'V')} / ${compResult.value.falling_transition}` },
                { label: 'ヒステリシス', value: `${compResult.value.hysteresis} V` },
                { label: 'ノイズ余裕', value: `${compResult.value.noise_margin} V` },
                { label: 'R1 基準側抵抗', value: comp.inputPolarity === 'negative' ? `${compResult.value.source_resistance_ohm} Ω` : '' },
                { label: 'R1 ショート', value: compResult.value.r1_shorted ? 'R1は基準分圧へ短絡扱い' : '' },
            ];
            if (comp.inputPolarity !== 'negative') {
                metrics.push({ label: 'R1 Vin側抵抗', value: `${compResult.value.input_series_ohm} Ω` });
            }

            return designReport({
                verdict: bad ? 'FAIL' : (noiseMargin < 0 ? 'WARN' : 'CHECK'),
                tone: bad ? 'bad' : (noiseMargin < 0 ? 'warn' : 'neutral'),
                summary: bad
                    ? 'しきい値が電源範囲内で成立していません。'
                    : '比較器しきい値とヒステリシスを確認できます。',
                metrics,
                dominantFactors: ['基準方式', 'R1/R3比', 'VOH/VOL'],
                warnings: [
                    ...(noiseMargin < 0 ? ['ノイズ余裕が不足しています。'] : []),
                    ...(r3Disconnected ? ['R3が0Ωのためヒステリシスなしとして計算しています。'] : []),
                ],
                nextActions: ['入力バイアスと基準源インピーダンスを確認する'],
                missingConditions: ['出力High/Low実電圧', '入力バイアス電流'],
            });
        }
        if (toolId === 'interface') {
            const high = parseNumber(ifaceResult.value.high_margin);
            const low = parseNumber(ifaceResult.value.low_margin);
            const bad = high <= 0 || low <= 0 || ifaceResult.value.logic_level.status === 'bad';
            return designReport({ verdict: bad ? 'FAIL' : 'CHECK', tone: bad ? 'bad' : 'neutral', summary: bad ? (ifaceResult.value.logic_level.warnings[0] ?? 'ロジックレベル余裕が不足しています。') : 'H/L両側の電圧余裕とシリーズ間レベルを確認できます。', metrics: [{ label: 'シリーズ接続', value: ifaceResult.value.logic_level_verdict + ' / ' + ifaceResult.value.logic_pair }, { label: 'シリーズH/L余裕', value: ifaceResult.value.logic_high_margin + ' / ' + ifaceResult.value.logic_low_margin + ' V' }, { label: '入力耐圧余裕', value: ifaceResult.value.logic_input_overvoltage_margin + ' V' }, { label: '系列しきい値', value: 'VOH ' + ifaceResult.value.logic_driver_voh_min + ' / VOL ' + ifaceResult.value.logic_driver_vol_max + ' / VIH ' + ifaceResult.value.logic_receiver_vih_min + ' / VIL ' + ifaceResult.value.logic_receiver_vil_max + ' V' }, { label: 'H余裕', value: formatNumber(high, 3, 'V') }, { label: 'L余裕', value: formatNumber(low, 3, 'V') }, { label: 'I2C Lowシンク', value: ifaceResult.value.i2c_sink_ma + ' mA' }], dominantFactors: ['VOH/VIH', 'VOL/VIL', 'pull-up'], warnings: ifaceResult.value.logic_level.warnings, nextActions: ['データシートmin/max条件へ置き換える'], missingConditions: ['電源min/max', '温度範囲', '出力電流条件'] });
        }
        if (toolId === 'divider') {
            return designReport({ verdict: 'CHECK', tone: 'neutral', summary: 'NTC/PTCの温度変換、対象範囲感度、自己発熱、公差起因温度振れを確認できます。', metrics: [{ label: '測定温度', value: dividerResult.value.temp_c + ' ℃' }, { label: '対象範囲感度', value: String(dividerGraph.value.targetRatio) }, { label: '自己発熱', value: String(dividerGraph.value.targetSelfHeat) + ' ℃' }, { label: '公差起因温度振れ', value: String(dividerGraph.value.targetToleranceTempError) + ' ℃' }], dominantFactors: ['測定対象範囲', '固定抵抗候補', '自己発熱'], warnings: dividerGraph.value.sensitivityTone === 'bad' ? ['測定対象範囲が有効な感度範囲から外れています。'] : [], nextActions: ['min/typ/max表とADC量子化を確認する'], missingConditions: ['min/typ/max表', '部品定格'] });
        }
        if (toolId === 'shunt') {
            const powerMargin = parseNumber(shuntResult.value.power_margin_mw, Number.NaN);
            const powerRatingFail = Number.isFinite(powerMargin) && powerMargin < 0;
            const unipolarNegativeRange = shunt.senseMode === 'unipolar'
                && (toFinite(shunt.currentMin) < 0 || toFinite(shunt.currentMax) < 0);
            const rangeMarginFail = [
                shuntResult.value.adc_range_typ_margin_low_v,
                shuntResult.value.adc_range_typ_margin_high_v,
                shuntResult.value.amp_range_typ_margin_low_v,
                shuntResult.value.amp_range_typ_margin_high_v,
            ].some((margin) => parseNumber(margin, 0) < 0);
            const failReasons = [
                ...(powerRatingFail ? ['Rs電力定格超過'] : []),
                ...(unipolarNegativeRange ? ['片方向検出で負電流範囲'] : []),
                ...(rangeMarginFail ? ['ADC/アンプ出力範囲不足'] : []),
            ];
            const fail = failReasons.length > 0;
            const tcrDriftPct = Math.abs(toFinite(shunt.tcrPpm)) * 100 / 1000000 * 100;
            const metrics = [
                { label: '入力換算オフセット誤差', value: `${shuntResult.value.offset_error_a} A` },
                { label: 'ADC電流LSB', value: `${shuntResult.value.adc_lsb_a} A` },
                { label: '100℃ TCR目安', value: `${tcrDriftPct.toFixed(3)} %` },
                { label: '最小電流時アンプ出力', value: `${shuntResult.value.vout_at_imin_v} V` },
                { label: '最大電流時アンプ出力', value: `${shuntResult.value.vout_at_imax_v} V` },
                { label: 'ADCコード', value: `${shuntResult.value.adc_code} / ${shuntResult.value.adc_bin} / ${shuntResult.value.adc_hex}` },
                { label: 'ADC下限余裕', value: `${shuntResult.value.adc_range_margin_low_v} V` },
                { label: 'ADC上限余裕', value: `${shuntResult.value.adc_range_margin_high_v} V` },
                { label: 'アンプ出力下限余裕', value: `${shuntResult.value.amp_range_margin_low_v} V` },
                { label: 'アンプ出力上限余裕', value: `${shuntResult.value.amp_range_margin_high_v} V` },
            ];
            if (fail) {
                metrics.unshift({ label: 'FAIL理由', value: failReasons.join(' / ') });
            }

            return designReport({
                verdict: fail ? 'FAIL' : 'CHECK',
                tone: fail ? 'bad' : 'neutral',
                summary: fail
                    ? `${failReasons[0]}: シャント抵抗、検出方式、ADC/アンプ範囲を見直してください。`
                    : 'シャント電流検出のADC/アンプ余裕とRs定格を確認できます。',
                metrics,
                dominantFactors: ['Rs電力定格', 'ADC余裕', 'アンプ余裕'],
                warnings: [
                    ...(powerRatingFail ? ['Rs定格超過: シャント損失が入力したRs電力定格を超えています。'] : []),
                    ...(unipolarNegativeRange ? ['片方向検出では負電流範囲を扱えません。双方向検出またはオフセット基準へ切り替えてください。'] : []),
                    ...(rangeMarginFail ? ['ADCまたはアンプの出力範囲余裕が不足しています。'] : []),
                ],
                nextActions: ['ケルビン接続、CMRR、帯域を確認する'],
                missingConditions: ['CMRR', '帯域'],
            });
        }
        if (['cap-life'].includes(toolId)) {
            const resultMap = { 'cap-life': capResult.value, divider: dividerResult.value, shunt: shuntResult.value };
            return designReport({ verdict: 'CHECK', tone: 'neutral', summary: (activeTool.value?.label ?? toolId) + ' の計算結果を確認できます。', metrics: Object.entries(resultMap[toolId]).slice(0, 8).map(([label, value]) => ({ label, value: String(value) })), dominantFactors: ['入力条件', '部品定格'], warnings: [], nextActions: ['最悪条件とデータシート定格を確認する'], missingConditions: ['min/typ/max表', '部品定格'] });
        }
        return quickDesignReport();
    });

    const {
        outputSave, analysisPayload, copyAnalysisSummary, saveAnalysisReport,
        analysisTemplates, templateState, selectedTemplate, applyAnalysisTemplate, duplicateAnalysisTemplate,
        componentSelection, componentLabel, selectComponentCandidate, clearComponentSelection,
        searchComponentCandidates, onComponentSearchInput, openComponentSearch,
        componentImport, loadComponentContext, loadedComponentName, loadedComponentStock,
        savedAnalysis, loadSavedAnalysis, selectSavedAnalysis, restoreSavedAnalysis,
        beginDeleteSavedAnalysis, cancelDeleteSavedAnalysis, deleteSavedAnalysis,
        sessionProjectLabel, sessionComponentLabel, sessionUpdatedAtLabel,
    } = setupAnalysisSessions({
        activeToolId, activeTool, tools, currentInputPayload, analysisReport,
        apiJson, applyToolPayload, normalizeToolId, toolGroupForToolId, setToolGroup,
        unitMultiplier, parseNumber, normalizeEngineeringText,
    });

    const workflowDetail = {
        'network-search': {
            spec: '目標値、部品種別、接続形、許容差、在庫値を使うかを決める',
            input: 'E系列、素子数範囲、カスタム値、採用素子許容差を入力する',
            result: '候補数、誤差、RSS/コーナー範囲、探索元を確認する',
            next: '採用候補を比較し、温度係数、電力、実装条件を部品候補へ紐づける',
        },
        'divider-design': {
            spec: 'Vin/Voutまたは比率、負荷、総抵抗範囲、通常/VR分圧の前提を決める',
            input: 'E系列、許容差、負荷抵抗/負荷電流、VR端点条件を入力する',
            result: 'Vout誤差、候補抵抗、回路電流、抵抗電力、端点範囲を確認する',
            next: '後段入力電流、抵抗電力定格、温度係数、VR摺動条件を詰める',
        },
        'variable-resistor': {
            spec: '基準抵抗値、可変幅、基準位置、直列/並列トリムを決める',
            input: '固定抵抗ソース、VRソース、固定/VR許容差、カスタム候補値を入力する',
            result: '固定抵抗、VR、上下限範囲、端点誤差、候補タグを確認する',
            next: 'VR電力、端点残留抵抗、摺動ノイズ、機械寿命をデータシートで確認する',
        },
        eia96: {
            spec: '対象チップ抵抗がEIA-96の3文字マーキングか、3桁/4桁/0Ω表記かを確認する',
            input: 'コード、抵抗値、倍率文字を入力し、別表記R/S/Hも含めて早見表を引く',
            result: 'E96インデックス、倍率、抵抗値、近傍候補、検索結果を確認する',
            next: 'BOM値、許容差、サイズ、メーカーのマーキング仕様と照合する',
        },
    };
    const workflowStageLabels = [
        ['spec', '仕様・前提確認'],
        ['input', 'パラメータ入力'],
        ['result', '結果確認'],
        ['next', '次アクション'],
    ];
    // 目的: 設計解析ツール本体のbuild Workflowを扱う。機能: 入力値を検証・整形し、画面または計算処理へ渡す。入力: 関数シグネチャの値。出力: 表示値、配列、オブジェクト、数値のいずれか。動作条件: 設計解析ツール本体の初期化後に呼び出す。副作用: なし。
    const buildWorkflow = (toolId) => {
        const normalizedToolId = normalizeToolId(toolId);
        const tool = tools.find((item) => item.id === normalizedToolId);
        const label = tool?.label ?? normalizedToolId;
        const details = workflowDetail[normalizedToolId] ?? {
            spec: `${label}の適用範囲、判定条件、未評価の最悪条件を確認する`,
            input: `${label}の基本条件、最悪条件、部品定格、保存用条件を入力する`,
            result: `${label}の判定、margin、支配要因、不足条件を確認する`,
            next: '不足条件を埋め、部品定格、データシート、実測条件と照合する',
        };
        const current = normalizedToolId === normalizeToolId(activeToolId.value) ? analysisReport.value : null;
        return workflowStageLabels.map(([key, stageLabel]) => ({
            key,
            label: stageLabel,
            desc: key === 'result' && current
                ? `${current.verdict}: ${current.summary}`
                : details[key],
        }));
    };
    const workflowByTool = computed(() => Object.fromEntries(
        tools.map((tool) => [tool.id, buildWorkflow(tool.id)])
    ));
    const workflow = computed(() => workflowByTool.value[normalizeToolId(activeToolId.value)] ?? buildWorkflow(activeToolId.value));

    // 目的: 設計解析ツール本体のseed Design Tool Sample Inputsを扱う。機能: 入力値を検証・整形し、画面または計算処理へ渡す。入力: 関数シグネチャの値。出力: 処理結果またはなし。動作条件: 設計解析ツール本体の初期化後に呼び出す。副作用: Vue状態、localStorage、DOM、HTTP通信のいずれかを更新する場合がある。
    const seedDesignToolSampleInputs = () => {
        [
            [adc, 'vin', '1.65'], [adc, 'vinMin', '100m'], [adc, 'vinTyp', '1.65'], [adc, 'vinMax', '3.0'],
            [cap, 'rippleCurrent', '200m'], [cap, 'esr', '500m'],
            [divider, 'R0', '10k'], [divider, 'Rmeas', '10k'], [divider, 'fixedResistor', '10k'],
            [shunt, 'Rs', '10m'], [shunt, 'I', '5'], [shunt, 'currentMax', '5'], [shunt, 'outputOffset', '1.65'], [shunt, 'powerRating', '250m'], [shunt, 'ampOffsetUv', '50u', 1e-6, true],
            [power.loads[0], 'mA', '50m', 1e-3, true], [power.loads[1], 'mA', '20m', 1e-3, true],
            [battery, 'capacityMah', '1000m', 1e-3, true], [battery, 'internalResistance', '120m'], [battery.loads[0], 'currentMa', '1m', 1e-3, true], [battery.loads[1], 'currentMa', '100u', 1e-3, true], [battery.loads[2], 'currentMa', '30m', 1e-3, true],
            [comp, 'R1', '100k'], [comp, 'R2', '100k'], [comp, 'R3', '1M'], [comp, 'R4', '100k'], [comp, 'inputOffsetMv', '5m', 1e-3, true], [comp, 'inputBiasNa', '50n', 1e-9, true], [comp, 'noiseMv', '20m', 1e-3, true],
            [thermal, 'P', '1'], [thermal.nodes[0], 'Rth', '5'], [thermal.nodes[1], 'Rth', '500m'], [thermal.nodes[2], 'Rth', '10'],
            [iface, 'driverVcc', '5'], [iface, 'receiverVcc', '5'], [iface, 'i2cBusCapPf', '200p', 1e-12, true], [iface, 'i2cRiseNsLimit', '300n', 1e-9, true], [iface, 'pullupOhm', '2.2k'], [iface, 'i2cSinkMaLimit', '3m', 1e-3, true],
            [quickForms.tolerance, 'nominal', '1k'], [quickForms.bode, 'r', '10k'], [quickForms.bode, 'c', '10n'], [quickForms.bode, 'freq', '1k'], [quickForms.bode, 'passbandFreq', '100'], [quickForms.bode, 'stopbandFreq', '10k'],
            [quickForms.ovp, 'seriesR', '1k'], [quickForms.ovp, 'loadCurrent', '2m'],
            [quickForms.tvs, 'surgeV', '1k'], [quickForms.tvs, 'pulseMs', '1m', 1e-3, true],
            [quickForms.fuse, 'loadCurrent', '1.2'], [quickForms.polyfuse, 'resistance', '400m'],
            [quickForms.protection, 'loadCurrent', '600m'], [quickForms.connector, 'userTemplatePitchMm', '2.54m', 1e-3, true], [quickForms.startup, 'resetHoldMs', '20m', 1e-3, true],
        ].forEach(([target, key, raw, storedUnitFactor = 1, forceUnitConversion = false]) => seedNumericInputDraft(target, key, raw, storedUnitFactor, forceUnitConversion));
        divider.pullupCandidates = '4.7k,10k,22k';
        comp.candidateResistors = '10k,47k,100k';
    };
    seedDesignToolSampleInputs();

    return {
        activeToolId, tools, toolGroups, activeToolGroup, selectedToolGroup, visibleTools, activeToolInVisibleGroup, setToolGroup,
        activeTool, hubBands, isToolOrderEditing, moveToolTab, resetToolOrder,
        eia96, eia96BaseValues, eia96Multipliers, eia96Lookup, eia96ReverseMatches, eia96SelectedRows, eia96FilteredRows, eia96MultiplierBySelected,
        adc, adcResult,
        cap, capResult,
        divider, dividerResult, dividerGraph, dividerGraphCursor, dividerGraphTooltip, updateDividerGraphCursor, clearDividerGraphCursor,
        shunt, shuntResult,
        power, powerResult, addLoad, removeLoad,
        battery, batteryProfiles, batteryResult, batteryGraph, batteryCapacityPie, batteryGraphCursor, batteryGraphTooltipBox, updateBatteryGraphCursor, clearBatteryGraphCursor, addBatteryLoad, removeBatteryLoad,
        comp, compResult,
        thermal, thermalResult, thermalReferences, addNode, removeNode,
        iface, ifaceResult,
        logicConnectionFamilyOptions: LOGIC_CONNECTION_FAMILY_OPTIONS,
        quickForms, quickTool, analysisReport,
        connectorCatalog, connectorActiveTemplate, connectorTemplateOptions, connectorPinMap, connectorSummary, connectorAssignments,
        connectorUserTemplates, applyConnectorTemplate, saveConnectorTemplate,
        outputSave, currentInputPayload, analysisPayload, copyAnalysisSummary, saveAnalysisReport,
        toolSupplementInputGroups, workflow, workflowByTool,
        analysisTemplates, templateState, selectedTemplate, applyAnalysisTemplate, duplicateAnalysisTemplate,
        componentSelection, componentLabel, selectComponentCandidate, clearComponentSelection,
        searchComponentCandidates, onComponentSearchInput, openComponentSearch,
        componentImport, loadComponentContext, loadedComponentName, loadedComponentStock,
        savedAnalysis, loadSavedAnalysis, selectSavedAnalysis, restoreSavedAnalysis,
        beginDeleteSavedAnalysis, cancelDeleteSavedAnalysis, deleteSavedAnalysis,
        sessionProjectLabel, sessionComponentLabel, sessionUpdatedAtLabel,
        activeDiagram, diagramFocus, focusDiagram, clearDiagramFocus, isDiagramFocused, diagramItemClass,
        passiveNetwork,
        parseNumber, setNumericInput, numericInputValue,
    };
}
