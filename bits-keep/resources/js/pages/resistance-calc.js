/**
 * 抵抗/容量ネットワーク探索ツール（SCR-011）
 * - 抵抗/容量/分圧の候補探索
 * - 可変抵抗 + 固定抵抗の標準値候補選定
 * - VR付き分圧の出力電圧範囲候補選定
 */
import { ref, reactive, computed } from 'vue';
import { api } from '../api.js';
import {
    CIRCUIT_OPTIONS,
    DIVIDER_MODE_OPTIONS,
    DIVIDER_TARGET_MODE_OPTIONS,
    LOAD_TYPE_OPTIONS,
    MODE_OPTIONS,
    PART_TYPE_LABELS,
    PART_TYPE_OPTIONS,
    SERIES_OPTIONS,
    VARIABLE_FIXED_SOURCE_OPTIONS,
    VARIABLE_POT_SOURCE_OPTIONS,
    VARIABLE_REFERENCE_POSITION_OPTIONS,
    calculateVariable,
    dividerLoad,
    dividerRatioFromVoltages,
    formatCurrent,
    formatPower,
    formatResistance,
    formatTargetValue,
    formatVoltage,
    normalizeCustomValues,
    normalizeNetworkResponse,
    parseTarget,
    trimNumber,
} from './resistance-calc/core.js';
import { calculateVariableDivider } from './resistance-calc/variableDivider.js';

export {
    calculateVariable,
    dividerRatioFromVoltages,
    formatCapacitance,
    formatCurrent,
    formatPower,
    formatResistance,
    formatVoltage,
    normalizeCustomValues,
    normalizeNetworkResponse,
    parseTarget,
} from './resistance-calc/core.js';
export { calculateVariableDivider } from './resistance-calc/variableDivider.js';

/**
 * 目的: 抵抗/容量ネットワーク探索ページのVue公開状態と操作関数を構成する。
 * 機能: ネットワーク探索、通常分圧、VR分圧、可変抵抗候補を同一画面で扱う。
 * 入力: Vueテンプレートからのフォーム入力、プリセット選択、API探索操作。
 * 出力: テンプレートへ渡すref/reactive/computed/操作関数のオブジェクト。
 * 動作条件: ブラウザ環境でVueが動作し、ネットワーク探索時は `/calc/networks/search` APIが利用できること。
 * 副作用: 探索実行時にAPIへPOSTし、検索結果・警告・比較トレイ状態を更新する。
 */
export default function setup() {
    const activeMode = ref('network');
    const form = reactive({
        part_type: 'R',
        divider_mode: 'fixed',
        divider_target_mode: 'ratio',
        target_raw: '1k',
        input_voltage_raw: '3.3',
        output_voltage_raw: '2.5',
        tolerance_pct: 5,
        element_tolerance_pct: 0,
        divider_upper_tolerance_pct: 0,
        divider_lower_tolerance_pct: 0,
        series: 'E24',
        custom_values: '',
        min_elements: 1,
        max_elements: 2,
        inventory_only: false,
        circuit_types: ['series', 'parallel'],
        total_res_min_raw: '',
        total_res_max_raw: '',
        load_type: 'resistance',
        load_resistance_raw: '∞',
        load_current_raw: '0',
    });

    const results = ref([]);
    const searching = ref(false);
    const elapsedMs = ref(null);
    const truncated = ref(false);
    const error = ref('');
    const summary = ref('');
    const warnings = ref([]);
    const nextActions = ref([]);
    const poolInfo = ref(null);
    const compareIds = ref([]);

    const presets = [
        { label: '1kΩ', meta: '抵抗 E24', type: 'R', target: '1k', tolerance: 5, series: 'E24', circuits: ['series', 'parallel'] },
        { label: '100nF', meta: '容量 E12', type: 'C', target: '100n', tolerance: 10, series: 'E12', circuits: ['parallel', 'series'] },
        { label: '1/2', meta: '分圧 1k-100k', type: 'divider', targetMode: 'ratio', target: '50%', tolerance: 1, series: 'E24', circuits: ['divider'], min: '1k', max: '100k' },
        { label: '2.5V/3.3V', meta: '分圧', type: 'divider', targetMode: 'voltage', vin: '3.3', vout: '2.5', tolerance: 1, series: 'E96', circuits: ['divider'], min: '5k', max: '200k' },
    ];

    const dividerVoltageTarget = computed(() => dividerRatioFromVoltages(form.input_voltage_raw, form.output_voltage_raw));
    const isDividerVariableMode = computed(() => activeMode.value === 'divider' && form.divider_mode === 'variable');
    const targetValue = computed(() => {
        if (form.part_type === 'divider' && form.divider_target_mode === 'voltage') {
            return dividerVoltageTarget.value.valid ? dividerVoltageTarget.value.ratio : null;
        }

        return parseTarget(form.target_raw, form.part_type);
    });
    const targetValid = computed(() => {
        const value = targetValue.value;
        if (value === null) return false;
        if (form.part_type === 'divider') return value > 0 && value < 1;
        return value > 0;
    });
    const dividerLoadConfig = computed(() => dividerLoad(form));
    const loadValid = computed(() => {
        if (form.part_type !== 'divider') return true;
        if (!dividerLoadConfig.value.valid) return false;
        if (dividerLoadConfig.value.type === 'current' && dividerLoadConfig.value.current > 0) {
            const inputVoltage = parseTarget(form.input_voltage_raw, 'V');
            return Number.isFinite(inputVoltage) && inputVoltage > 0;
        }

        return true;
    });
    const elementRangeValid = computed(() => form.part_type === 'divider' || Number(form.min_elements) <= Number(form.max_elements));
    const circuitTypesValid = computed(() => form.part_type === 'divider' || form.circuit_types.length > 0);
    const formValid = computed(() => targetValid.value && loadValid.value && elementRangeValid.value && circuitTypesValid.value);
    const partTypeLabel = computed(() => PART_TYPE_LABELS[form.part_type] ?? form.part_type);
    const targetHint = computed(() => ({
        R: '4.7k / 4700',
        C: '100n / 0.1u',
        divider: form.divider_target_mode === 'voltage' ? 'Vin と Vout から比率を計算' : '0.5 / 50%',
    }[form.part_type] ?? ''));
    const validationMessage = computed(() => {
        if (!targetValid.value) {
            if (form.part_type === 'divider' && form.divider_target_mode === 'voltage') return '入力電圧と出力電圧を確認してください';
            return form.part_type === 'divider' ? '分圧比は 0% 超 100% 未満です' : '目標値を確認してください';
        }
        if (!loadValid.value) return '負荷条件を確認してください';
        if (!elementRangeValid.value) return '素子数範囲を確認してください';
        if (!circuitTypesValid.value) return '回路種別を選択してください';
        return '';
    });

    const rankedResults = computed(() => results.value.map((candidate, index) => ({
        ...candidate,
        rank: index + 1,
        id: `${index}-${candidate.circuit_type}-${candidate.expression}-${candidate.actual_value}`,
    })));
    const comparedCandidates = computed(() => rankedResults.value.filter((candidate) => compareIds.value.includes(candidate.id)));
    const summaryText = computed(() => summary.value || (elapsedMs.value === null ? '未探索' : `${results.value.length}件`));
    const visibleWarnings = computed(() => {
        const messages = [...warnings.value];
        if (elapsedMs.value !== null && activeMode.value === 'network' && Number(form.element_tolerance_pct) > 0) {
            messages.push('採用素子許容差は独立ばらつきのRSS目安を主表示し、保証確認用に全素子同方向のコーナー範囲も併記します。温度、電圧係数、経年、寄生成分、ロット相関、実測分布は含みません。');
        } else if (elapsedMs.value !== null) {
            messages.push(`${partTypeLabel.value}の公称値探索です。温度、電力、DCバイアス、負荷条件は別途確認してください。`);
        }
        if (poolInfo.value?.raw && poolInfo.value?.used && poolInfo.value.used < poolInfo.value.raw) {
            messages.push(`3素子以上または混在探索は近傍 ${poolInfo.value.used} / ${poolInfo.value.raw} 候補から探索しています。`);
        }
        if (form.inventory_only && elapsedMs.value !== null) {
            messages.push('在庫値のみはE系列ではなく、登録済み在庫部品の値セットを使います。');
        }
        return [...new Set(messages)];
    });
    const statusMetrics = computed(() => [
        { label: '目標', value: poolInfo.value?.target ?? formatTargetValue(targetValue.value, form.part_type) },
        { label: '候補', value: elapsedMs.value === null ? '-' : `${results.value.length}件` },
        { label: '値プール', value: poolInfo.value?.raw ? `${poolInfo.value.used ?? '-'} / ${poolInfo.value.raw}` : '-' },
        { label: '探索元', value: form.inventory_only ? '在庫値' : form.series },
    ]);

    /**
     * 目的: 入力条件変更後に古い探索結果を残さないよう画面状態を初期化する。
     * 機能: 候補、経過時間、警告、次アクション、比較対象をまとめてクリアする。
     * 入力: なし。
     * 出力: 戻り値なし。
     * 動作条件: 新しい検索条件が確定する前後で呼ぶ。
     * 副作用: 検索結果関連のrefを空状態へ更新する。
     */
    const resetSearchState = () => {
        results.value = [];
        elapsedMs.value = null;
        truncated.value = false;
        error.value = '';
        summary.value = '';
        warnings.value = [];
        nextActions.value = [];
        poolInfo.value = null;
        compareIds.value = [];
    };

    /**
     * 目的: ネットワーク探索の対象部品種別を切り替える。
     * 機能: 抵抗/容量/分圧ごとの初期値、回路種別、素子数条件を整合させる。
     * 入力: `type` は `R`、`C`、`divider` のいずれか。
     * 出力: 戻り値なし。
     * 動作条件: 分圧へ切り替える場合は探索条件を2素子分圧へ固定する。
     * 副作用: `form` と検索結果状態を更新する。
     */
    const setPartType = (type) => {
        const oldType = form.part_type;
        form.part_type = type;
        resetSearchState();
        if (type === 'divider') {
            form.circuit_types = ['divider'];
            form.min_elements = 2;
            form.max_elements = 2;
            form.inventory_only = false;
            if (oldType !== 'divider') {
                form.divider_mode = 'fixed';
                form.divider_target_mode = 'ratio';
                form.target_raw = '50%';
            }
            return;
        }
        if (form.circuit_types.includes('divider')) form.circuit_types = ['series', 'parallel'];
        if (type === 'C' && oldType !== 'C') form.target_raw = '100n';
        if (type === 'R' && oldType !== 'R') form.target_raw = '1k';
    };

    // 目的: 抵抗/容量探索のset Divider Modeを扱う。機能: 入力値を検証・整形し、画面または計算処理へ渡す。入力: 関数シグネチャの値。出力: 処理結果またはなし。動作条件: 抵抗/容量探索の初期化後に呼び出す。副作用: Vue状態、localStorage、DOM、HTTP通信のいずれかを更新する場合がある。
    const setDividerMode = (mode) => {
        if (form.part_type !== 'divider') {
            setPartType('divider');
        } else {
            resetSearchState();
        }
        activeMode.value = 'divider';
        form.divider_mode = mode;
    };

    // 目的: 抵抗/容量探索のset Active Modeを扱う。機能: 入力値を検証・整形し、画面または計算処理へ渡す。入力: 関数シグネチャの値。出力: 処理結果またはなし。動作条件: 抵抗/容量探索の初期化後に呼び出す。副作用: Vue状態、localStorage、DOM、HTTP通信のいずれかを更新する場合がある。
    const setActiveMode = (mode) => {
        activeMode.value = mode;
        resetSearchState();
        if (mode === 'divider') {
            form.inventory_only = false;
            if (form.part_type !== 'divider') setPartType('divider');
            return;
        }
        if (mode === 'network' && form.part_type === 'divider') {
            setPartType('R');
        }
    };

    // 目的: 抵抗/容量探索のapply Presetを扱う。機能: 入力値を検証・整形し、画面または計算処理へ渡す。入力: 関数シグネチャの値。出力: 処理結果またはなし。動作条件: 抵抗/容量探索の初期化後に呼び出す。副作用: Vue状態、localStorage、DOM、HTTP通信のいずれかを更新する場合がある。
    const applyPreset = (preset) => {
        activeMode.value = preset.type === 'divider' ? 'divider' : 'network';
        form.part_type = preset.type;
        form.inventory_only = false;
        form.divider_mode = 'fixed';
        form.divider_target_mode = preset.targetMode ?? 'ratio';
        form.target_raw = preset.target ?? form.target_raw;
        form.input_voltage_raw = preset.vin ?? form.input_voltage_raw;
        form.output_voltage_raw = preset.vout ?? form.output_voltage_raw;
        form.tolerance_pct = preset.tolerance;
        form.element_tolerance_pct = preset.elementTolerance ?? 0;
        form.series = preset.series;
        form.circuit_types = [...preset.circuits];
        form.min_elements = preset.type === 'divider' ? 2 : 1;
        form.max_elements = preset.type === 'divider' ? 2 : 2;
        form.total_res_min_raw = preset.min ?? '';
        form.total_res_max_raw = preset.max ?? '';
        resetSearchState();
    };

    // 目的: 抵抗/容量探索のtoggle Circuit Typeを扱う。機能: 入力値を検証・整形し、画面または計算処理へ渡す。入力: 関数シグネチャの値。出力: 処理結果またはなし。動作条件: 抵抗/容量探索の初期化後に呼び出す。副作用: なし。
    const toggleCircuitType = (type) => {
        const index = form.circuit_types.indexOf(type);
        if (index >= 0) form.circuit_types.splice(index, 1);
        else form.circuit_types.push(type);
        resetSearchState();
    };

    /**
     * 目的: 現在の入力条件で受動部品ネットワーク候補をAPI検索する。
     * 機能: 画面フォームをAPI payloadへ変換し、結果候補・警告・プール情報へ正規化する。
     * 入力: `form`、`targetValue`、`dividerLoadConfig` などの現在のVue状態。
     * 出力: Promise。成功時は結果refを更新し、失敗時は `error` へ理由を入れる。
     * 動作条件: `formValid` が真で、分圧の電流負荷では有効なVinが指定されていること。
     * 副作用: API POST、検索中フラグ、検索結果、警告、比較状態を更新する。
     */
    const search = async () => {
        if (!formValid.value) {
            error.value = validationMessage.value;
            return;
        }

        error.value = '';
        searching.value = true;
        resetSearchState();

        try {
            const payload = {
                target: targetValue.value,
                tolerance_pct: form.tolerance_pct,
                element_tolerance_pct: form.part_type === 'divider' ? 0 : form.element_tolerance_pct,
                part_type: form.part_type,
                series: form.series,
                min_elements: form.part_type === 'divider' ? 2 : form.min_elements,
                max_elements: form.part_type === 'divider' ? 2 : form.max_elements,
                inventory_only: form.inventory_only,
                circuit_types: form.part_type === 'divider' ? ['divider'] : form.circuit_types,
            };

            if (form.part_type === 'divider') {
                payload.total_res_min = parseTarget(form.total_res_min_raw, 'R') ?? 0;
                payload.total_res_max = parseTarget(form.total_res_max_raw, 'R') ?? null;
                payload.load_type = dividerLoadConfig.value.type;
                payload.load_current = dividerLoadConfig.value.current;
                payload.load_resistance = Number.isFinite(dividerLoadConfig.value.resistance) ? dividerLoadConfig.value.resistance : null;
                payload.load_resistance_infinite = dividerLoadConfig.value.resistance === Infinity;
                payload.divider_upper_tolerance_pct = form.divider_upper_tolerance_pct;
                payload.divider_lower_tolerance_pct = form.divider_lower_tolerance_pct;
                const inputVoltage = parseTarget(form.input_voltage_raw, 'V');
                if (Number.isFinite(inputVoltage) && inputVoltage > 0) {
                    payload.input_voltage = inputVoltage;
                }
                if (form.divider_target_mode === 'voltage') {
                    payload.output_voltage = dividerVoltageTarget.value.output;
                }
            }
            if (form.series === 'custom') {
                payload.custom_values = normalizeCustomValues(form.custom_values, form.part_type);
            }

            const response = await api.post('/calc/networks/search', payload);
            const normalized = normalizeNetworkResponse(response.data);
            const result = normalized.result ?? {};
            results.value = result?.candidates ?? [];
            elapsedMs.value = result?.elapsed_ms ?? 0;
            truncated.value = Boolean(result?.truncated);
            summary.value = normalized.summary;
            warnings.value = normalized.warnings;
            nextActions.value = normalized.nextActions;
            poolInfo.value = result ? {
                raw: result.raw_pool_count ?? null,
                used: result.candidate_pool_count ?? null,
                limit: result.return_limit ?? null,
                target: result.target_display ?? null,
            } : null;
        } catch (err) {
            const responseMessage = err.response?.data?.message;
            const firstError = Object.values(err.response?.data?.errors ?? {})?.flat?.()?.[0];
            error.value = firstError || responseMessage || err.message || '探索に失敗しました';
            elapsedMs.value = 0;
        } finally {
            searching.value = false;
        }
    };

    // 目的: 抵抗/容量探索のcircuit Type Labelを扱う。機能: 入力値を検証・整形し、画面または計算処理へ渡す。入力: 関数シグネチャの値。出力: 処理結果またはなし。動作条件: 抵抗/容量探索の初期化後に呼び出す。副作用: Vue状態、localStorage、DOM、HTTP通信のいずれかを更新する場合がある。
    const circuitTypeLabel = (type) => ({
        series: '直列',
        parallel: '並列',
        divider: '分圧',
        single: '単体',
        mixed: '混在',
    }[type] ?? type);

    // 目的: 抵抗/容量探索のerror Classを扱う。機能: 入力値を検証・整形し、画面または計算処理へ渡す。入力: 関数シグネチャの値。出力: 処理結果またはなし。動作条件: 抵抗/容量探索の初期化後に呼び出す。副作用: Vue状態、localStorage、DOM、HTTP通信のいずれかを更新する場合がある。
    const errorClass = (errPct) => {
        if (errPct <= 1) return 'text-[var(--color-tag-ok)]';
        if (errPct <= 5) return 'text-[var(--color-tag-warning)]';
        return 'text-[var(--color-tag-eol)]';
    };
    // 目的: 抵抗/容量探索のvariable Status Classを扱う。機能: 入力値を検証・整形し、画面または計算処理へ渡す。入力: 関数シグネチャの値。出力: 処理結果またはなし。動作条件: 抵抗/容量探索の初期化後に呼び出す。副作用: Vue状態、localStorage、DOM、HTTP通信のいずれかを更新する場合がある。
    const variableStatusClass = (status) => ({
        check: 'border-amber-400 text-amber-700 bg-amber-50',
        warn: 'border-red-400 text-red-700 bg-red-50',
    }[status] ?? 'border-[var(--color-border)]');
    // 目的: 抵抗/容量探索のtopology Tokensを扱う。機能: 入力値を検証・整形し、画面または計算処理へ渡す。入力: 関数シグネチャの値。出力: 処理結果またはなし。動作条件: 抵抗/容量探索の初期化後に呼び出す。副作用: なし。
    const topologyTokens = (expression) => String(expression ?? '')
        .split(/(\s+|\+|∥|\(|\))/u)
        .map((token) => token.trim())
        .filter(Boolean)
        .map((token) => ({
            text: token,
            type: ['+', '∥', '(', ')'].includes(token) ? 'operator' : 'part',
        }));
    // 目的: 抵抗/容量探索のis Comparedを扱う。機能: 入力値を検証・整形し、画面または計算処理へ渡す。入力: 関数シグネチャの値。出力: 真偽値。動作条件: 抵抗/容量探索の初期化後に呼び出す。副作用: なし。
    const isCompared = (candidate) => compareIds.value.includes(candidate.id);
    // 目的: 抵抗/容量探索のtoggle Compareを扱う。機能: 入力値を検証・整形し、画面または計算処理へ渡す。入力: 関数シグネチャの値。出力: 処理結果またはなし。動作条件: 抵抗/容量探索の初期化後に呼び出す。副作用: なし。
    const toggleCompare = (candidate) => {
        const index = compareIds.value.indexOf(candidate.id);
        if (index >= 0) {
            compareIds.value.splice(index, 1);
            return;
        }
        compareIds.value = [...compareIds.value.slice(-2), candidate.id];
    };
    // 目的: 抵抗/容量探索のclear Compareを扱う。機能: 入力値を検証・整形し、画面または計算処理へ渡す。入力: 関数シグネチャの値。出力: 処理結果またはなし。動作条件: 抵抗/容量探索の初期化後に呼び出す。副作用: Vue状態、localStorage、DOM、HTTP通信のいずれかを更新する場合がある。
    const clearCompare = () => { compareIds.value = []; };
    // 目的: 抵抗/容量探索のset Load Resistance Infiniteを扱う。機能: 入力値を検証・整形し、画面または計算処理へ渡す。入力: 関数シグネチャの値。出力: 処理結果またはなし。動作条件: 抵抗/容量探索の初期化後に呼び出す。副作用: Vue状態、localStorage、DOM、HTTP通信のいずれかを更新する場合がある。
    const setLoadResistanceInfinite = (target) => {
        target.load_type = 'resistance';
        target.load_resistance_raw = '∞';
        if (target === form) resetSearchState();
    };
    // 目的: 抵抗/容量探索のset Load Current Zeroを扱う。機能: 入力値を検証・整形し、画面または計算処理へ渡す。入力: 関数シグネチャの値。出力: 処理結果またはなし。動作条件: 抵抗/容量探索の初期化後に呼び出す。副作用: Vue状態、localStorage、DOM、HTTP通信のいずれかを更新する場合がある。
    const setLoadCurrentZero = (target) => {
        target.load_type = 'current';
        target.load_current_raw = '0';
        if (target === form) resetSearchState();
    };

    const variable = reactive({
        reference_raw: '10k',
        span_mode: 'percent',
        span_raw: '20',
        reference_position: 'upper',
        circuit: 'series',
        fixed_source: 'E24',
        fixed_custom_values: '',
        pot_source: 'vr-common',
        pot_custom_values: '',
        fixed_tolerance_pct: 0,
        pot_tolerance_pct: 0,
    });
    const variableResult = computed(() => {
        const result = calculateVariable(variable);
        const ideal = result.ideal ?? { fixed: 0, pot: 0, low: 0, high: 0 };
        return {
            ...result,
            idealFixedDisplay: formatResistance(ideal.fixed),
            idealPotDisplay: formatResistance(ideal.pot),
            idealLowDisplay: formatResistance(ideal.low),
            idealHighDisplay: formatResistance(ideal.high),
            referenceDisplay: formatResistance(result.requirement?.reference ?? 0),
            spanDisplay: formatResistance(result.requirement?.span ?? 0),
            requirementLowDisplay: formatResistance(result.requirement?.low ?? 0),
            requirementHighDisplay: formatResistance(result.requirement?.high ?? 0),
            selectedFixedDisplay: result.bestCandidate?.fixedDisplay ?? '-',
            selectedPotDisplay: result.bestCandidate?.potDisplay ?? '-',
            selectedLowDisplay: result.bestCandidate?.lowDisplay ?? '-',
            selectedHighDisplay: result.bestCandidate?.highDisplay ?? '-',
        };
    });
    const dividerVariable = reactive({
        output_low_raw: '1',
        output_high_raw: '3',
        output_low_ratio_raw: '20%',
        output_high_ratio_raw: '60%',
        nominal_pot_raw: '10k',
        fixed_source: 'E24',
        fixed_custom_values: '',
        pot_source: 'vr-common',
        pot_custom_values: '',
        endpoint_tolerance_pct: 0,
        top_tolerance_pct: 0,
        pot_tolerance_pct: 0,
        bottom_tolerance_pct: 0,
        load_type: 'resistance',
        load_resistance_raw: '∞',
        load_current_raw: '0',
    });
    const dividerVariableResult = computed(() => {
        const result = calculateVariableDivider({
            ...dividerVariable,
            input_voltage_raw: form.input_voltage_raw,
            output_mode: form.divider_target_mode,
            fixed_source: form.series,
            fixed_custom_values: form.custom_values,
            endpoint_tolerance_pct: form.tolerance_pct,
            total_res_min_raw: form.total_res_min_raw,
            total_res_max_raw: form.total_res_max_raw,
            load_type: form.load_type,
            load_resistance_raw: form.load_resistance_raw,
            load_current_raw: form.load_current_raw,
        });
        const ideal = result.ideal ?? { top: 0, pot: 0, bottom: 0, total: 0, low: 0, high: 0 };
        const requirement = result.requirement ?? {};

        return {
            ...result,
            inputVoltageDisplay: formatVoltage(requirement.inputVoltage ?? 0),
            outputLowDisplay: formatVoltage(requirement.outputLow ?? 0),
            outputHighDisplay: formatVoltage(requirement.outputHigh ?? 0),
            nominalPotDisplay: formatResistance(requirement.nominalPot ?? 0),
            loadDisplay: requirement.load?.display ?? '-',
            ratioLowDisplay: Number.isFinite(requirement.lowRatio) ? `${trimNumber(requirement.lowRatio * 100, 4)}%` : '-',
            ratioHighDisplay: Number.isFinite(requirement.highRatio) ? `${trimNumber(requirement.highRatio * 100, 4)}%` : '-',
            idealTopDisplay: formatResistance(ideal.top),
            idealPotDisplay: formatResistance(ideal.pot),
            idealBottomDisplay: formatResistance(ideal.bottom),
            idealTotalDisplay: formatResistance(ideal.total),
            selectedTopDisplay: result.bestCandidate?.topDisplay ?? '-',
            selectedPotDisplay: result.bestCandidate?.potDisplay ?? '-',
            selectedBottomDisplay: result.bestCandidate?.bottomDisplay ?? '-',
            selectedLowDisplay: result.bestCandidate?.lowDisplay ?? '-',
            selectedHighDisplay: result.bestCandidate?.highDisplay ?? '-',
            selectedSourceCurrentDisplay: result.bestCandidate?.sourceCurrentDisplay ?? '-',
            selectedOutputCurrentDisplay: result.bestCandidate?.outputCurrentDisplay ?? '-',
            selectedTopPowerDisplay: result.bestCandidate?.topPowerDisplay ?? '-',
            selectedPotPowerDisplay: result.bestCandidate?.potPowerDisplay ?? '-',
            selectedBottomPowerDisplay: result.bestCandidate?.bottomPowerDisplay ?? '-',
            selectedResistorPowerDisplay: result.bestCandidate?.resistorPowerDisplay ?? '-',
        };
    });

    return {
        activeMode,
        modeOptions: MODE_OPTIONS,
        networkPartTypeOptions: PART_TYPE_OPTIONS,
        dividerModeOptions: DIVIDER_MODE_OPTIONS,
        dividerTargetModeOptions: DIVIDER_TARGET_MODE_OPTIONS,
        loadTypeOptions: LOAD_TYPE_OPTIONS,
        partTypeOptions: PART_TYPE_OPTIONS,
        seriesOptions: SERIES_OPTIONS,
        variableFixedSourceOptions: VARIABLE_FIXED_SOURCE_OPTIONS,
        variablePotSourceOptions: VARIABLE_POT_SOURCE_OPTIONS,
        variableReferencePositionOptions: VARIABLE_REFERENCE_POSITION_OPTIONS,
        circuitOptions: CIRCUIT_OPTIONS,
        form,
        results,
        rankedResults,
        searching,
        elapsedMs,
        truncated,
        error,
        summaryText,
        warnings: visibleWarnings,
        nextActions,
        poolInfo,
        statusMetrics,
        presets,
        targetValid,
        elementRangeValid,
        circuitTypesValid,
        formValid,
        validationMessage,
        partTypeLabel,
        targetHint,
        isDividerVariableMode,
        dividerVoltageTarget,
        dividerLoadConfig,
        comparedCandidates,
        variable,
        variableResult,
        dividerVariable,
        dividerVariableResult,
        setPartType,
        setDividerMode,
        setActiveMode,
        applyPreset,
        toggleCircuitType,
        search,
        circuitTypeLabel,
        errorClass,
        variableStatusClass,
        topologyTokens,
        isCompared,
        toggleCompare,
        clearCompare,
        setLoadResistanceInfinite,
        setLoadCurrentZero,
    };
}
