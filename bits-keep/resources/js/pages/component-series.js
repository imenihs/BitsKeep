import { computed, onMounted, reactive, ref, watch } from 'vue';
import { api } from '../api.js';
import { useToast } from '../composables/useToast.js';
import {
    appendUnitForDisplay,
    defaultInputPrefixesForUnit,
    formatEngineeringValue,
    normalizePrefixList,
    normalizeUnitLabel,
    parseEngineeringNumber,
    prefixLabel,
    prefixListText,
} from '../utils/engineeringUnits.js';

const E_SERIES_OPTIONS = ['E6', 'E12', 'E24', 'E48', 'E96'];
const E_SERIES_TYPES = ['e_series', 'hybrid_series'];

/**
 * 改行・カンマ・セミコロン区切りの入力を値配列へ分割する。
 * 入力は任意値、戻り値は空値を除いた文字列配列で、副作用はない。
 */
// 目的: 部品系列管理のsplit Listを扱う。機能: 入力値を検証・整形し、画面または計算処理へ渡す。入力: 関数シグネチャの値。出力: 処理結果またはなし。動作条件: 部品系列管理の初期化後に呼び出す。副作用: Vue状態、localStorage、DOM、HTTP通信のいずれかを更新する場合がある。
const splitList = (value) => String(value ?? '')
    .split(/[\s,;]+/)
    .map((item) => item.trim())
    .filter(Boolean);

/** 値が未入力扱いかを判定する。入力は任意値、戻り値は真偽値で副作用はない。 */
// 目的: 部品系列管理のis Blankを扱う。機能: 入力値を検証・整形し、画面または計算処理へ渡す。入力: 関数シグネチャの値。出力: 真偽値。動作条件: 部品系列管理の初期化後に呼び出す。副作用: なし。
const isBlank = (value) => value === null || value === undefined || String(value).trim() === '';

/** 値候補の作り方がE系列を使う形式かを判定する。入力は形式キー、戻り値は真偽値で副作用はない。 */
// 目的: 部品系列管理のis ESeries Policy Typeを扱う。機能: 入力値を検証・整形し、画面または計算処理へ渡す。入力: 関数シグネチャの値。出力: 真偽値。動作条件: 部品系列管理の初期化後に呼び出す。副作用: なし。
const isESeriesPolicyType = (type) => E_SERIES_TYPES.includes(type);

/**
 * 整数として扱える入力だけをnumberへ変換する。
 * 空値はnull、不正な数値もnullとして返し、副作用はない。
 */
// 目的: 部品系列管理のto Integer Or Nullを扱う。機能: 入力値を検証・整形し、画面または計算処理へ渡す。入力: 関数シグネチャの値。出力: 処理結果またはなし。動作条件: 部品系列管理の初期化後に呼び出す。副作用: なし。
const toIntegerOrNull = (value) => {
    if (isBlank(value)) return null;
    const number = Number(value);
    return Number.isInteger(number) ? number : null;
};

/**
 * 入力値をtrim済み文字列またはnullへ変換する。
 * 空文字はDBへ送らない条件としてnullにし、副作用はない。
 */
// 目的: 部品系列管理のclean Text Or Nullを扱う。機能: 入力値を検証・整形し、画面または計算処理へ渡す。入力: 関数シグネチャの値。出力: 処理結果またはなし。動作条件: 部品系列管理の初期化後に呼び出す。副作用: Vue状態、localStorage、DOM、HTTP通信のいずれかを更新する場合がある。
const cleanTextOrNull = (value) => {
    const text = String(value ?? '').trim();
    return text === '' ? null : text;
};

/**
 * 利用可能な接頭語から優先順に最初の候補を選ぶ。
 * 入力は候補配列と優先配列、戻り値は接頭語文字列で、副作用はない。
 */
// 目的: 部品系列管理のfirst Available Prefixを扱う。機能: 入力値を検証・整形し、画面または計算処理へ渡す。入力: 関数シグネチャの値。出力: 処理結果またはなし。動作条件: 部品系列管理の初期化後に呼び出す。副作用: Vue状態、localStorage、DOM、HTTP通信のいずれかを更新する場合がある。
const firstAvailablePrefix = (prefixes, preferred) => preferred.find((prefix) => prefixes.includes(prefix)) ?? '';

/**
 * decade値をユーザーが読める接頭語付き値へ変換する。
 * 入力は10の指数と単位、戻り値は表示文字列で、無効値はハイフンを返し副作用はない。
 */
// 目的: 部品系列管理のformat Power Of Tenを扱う。機能: 入力値を検証・整形し、画面または計算処理へ渡す。入力: 関数シグネチャの値。出力: 表示値、配列、オブジェクト、数値のいずれか。動作条件: 部品系列管理の初期化後に呼び出す。副作用: なし。
const formatPowerOfTen = (decade, unit = '') => {
    const number = toIntegerOrNull(decade);
    if (number === null) return '-';

    const positivePrefixes = [
        [12, 'T'],
        [9, 'G'],
        [6, 'M'],
        [3, 'k'],
        [0, ''],
    ];
    if (number >= 0) {
        const [power, prefix] = positivePrefixes.find(([power]) => number >= power) ?? [0, ''];
        return `${10 ** (number - power)}${prefix}${unit}`;
    }
    if (number === -1) return `0.1${unit}`;
    if (number === -2) return `0.01${unit}`;

    const negativePrefixes = {
        [-3]: 'm',
        [-6]: 'u',
        [-9]: 'n',
        [-12]: 'p',
        [-15]: 'f',
    };
    const power = Math.max(-15, Math.floor(number / 3) * 3);
    const prefix = negativePrefixes[power] ?? '';
    return `${10 ** (number - power)}${prefix}${unit}`;
};

/**
 * 部品シリーズ編集フォームの初期値を生成する。
 * 入力は不要、戻り値は新しいフォームオブジェクトで、副作用はない。
 */
// 目的: 部品系列管理のempty Formを扱う。機能: 入力値を検証・整形し、画面または計算処理へ渡す。入力: 関数シグネチャの値。出力: 処理結果またはなし。動作条件: 部品系列管理の初期化後に呼び出す。副作用: Vue状態、localStorage、DOM、HTTP通信のいずれかを更新する場合がある。
const emptyForm = () => ({
    id: null,
    spec_group_id: '',
    value_spec_type_id: '',
    package_group_id: '',
    package_id: '',
    manufacturer: '',
    name: '',
    description: '',
    status: 'active',
    sort_order: 0,
    policy: {
        value_set_type: 'hybrid_series',
        primary_series: 'E12',
        extra_series_text: 'E24',
        values_text: '',
        excluded_values_text: '',
        unit: '',
        decade_min: 0,
        decade_max: 10,
        range_min: '1',
        range_max: '10G',
        range_step: '',
        include_zero: false,
    },
});

// 目的: 部品系列管理のsetupを扱う。機能: 入力値を検証・整形し、画面または計算処理へ渡す。入力: 関数シグネチャの値。出力: 処理結果またはなし。動作条件: 部品系列管理の初期化後に呼び出す。副作用: Vue状態、localStorage、DOM、HTTP通信のいずれかを更新する場合がある。
export default function setup() {
    const { toasts, toastError } = useToast();
    const canEdit = document.getElementById('app')?.dataset?.canEdit === '1';
    const eSeriesOptions = E_SERIES_OPTIONS;
    const seriesList = ref([]);
    const specGroups = ref([]);
    const specTypes = ref([]);
    const packageGroups = ref([]);
    const packages = ref([]);
    const selected = ref(null);
    const previewRows = ref([]);
    const selectedValueIds = ref([]);
    const search = ref('');
    const error = ref('');
    const saving = ref(false);
    const form = reactive(emptyForm());
    const policyErrors = reactive({});

    const usesESeries = computed(() => isESeriesPolicyType(form.policy.value_set_type));
    const usesHybridSeries = computed(() => form.policy.value_set_type === 'hybrid_series');
    const usesCustomList = computed(() => form.policy.value_set_type === 'custom_list');
    const usesRangeStep = computed(() => form.policy.value_set_type === 'range_step');
    const usesValueTextList = computed(() => usesHybridSeries.value || usesCustomList.value);
    const usesExclusions = computed(() => form.policy.value_set_type !== 'none');
    const seriesRangeText = computed(() => {
        if (!usesESeries.value) return '';
        const unit = form.policy.unit || '';
        return `${appendUnitForDisplay(form.policy.range_min, unit)} から ${appendUnitForDisplay(form.policy.range_max, unit)} まで`;
    });
    const valueListLabel = computed(() => (usesCustomList.value ? '登録する値リスト' : '追加する値'));
    const valueListHelp = computed(() => (usesCustomList.value
        ? 'E系列を使わず、ここに書いた値だけを候補にします。'
        : '0Ωなど、E系列では生成されない値をここに追加します。'));
    const valueListPlaceholder = computed(() => {
        const prefixes = selectedSpecTypeInputPrefixes.value;
        const low = normalizeUnitLabel(form.policy.unit || selectedSpecTypeUnit.value) === 'F'
            ? firstAvailablePrefix(prefixes, ['f', 'p', 'n', 'u', 'm', ''])
            : '';
        const high = firstAvailablePrefix(prefixes, ['G', 'M', 'k', 'u', 'n', 'p', 'f', '']);
        return usesCustomList.value
            ? `1${low}, 10${low}, 10${high}`
            : `0, 1${low}, 10${high}`;
    });
    const selectedSpecGroup = computed(() =>
        specGroups.value.find((item) => Number(item.id) === Number(form.spec_group_id)) ?? null
    );
    const selectedSpecType = computed(() =>
        specTypes.value.find((item) => Number(item.id) === Number(form.value_spec_type_id)) ?? null
    );
    const selectedSpecTypeUnit = computed(() =>
        String(selectedSpecType.value?.base_unit ?? selectedSpecType.value?.units?.[0]?.unit ?? '').trim()
    );
    const selectedSpecTypeInputPrefixes = computed(() => {
        const prefixes = normalizePrefixList(selectedSpecType.value?.suggest_prefixes);
        return prefixes.length ? prefixes : defaultInputPrefixesForUnit(selectedSpecTypeUnit.value || form.policy.unit);
    });
    const selectedSpecTypeDisplayPrefixes = computed(() => {
        const prefixes = normalizePrefixList(selectedSpecType.value?.display_prefixes);
        return prefixes.length ? prefixes : null;
    });
    const inputPrefixHelpText = computed(() => `接頭語: ${prefixListText(selectedSpecTypeInputPrefixes.value)}`);
    const startValuePlaceholder = computed(() => {
        const prefixes = selectedSpecTypeInputPrefixes.value;
        if (normalizeUnitLabel(form.policy.unit || selectedSpecTypeUnit.value) === 'F') {
            if (prefixes.includes('f')) return '1f';
            if (prefixes.includes('p')) return '1p';
            if (prefixes.includes('u')) return '1u';
        }
        return '1';
    });
    const endValuePlaceholder = computed(() => {
        const prefixes = selectedSpecTypeInputPrefixes.value;
        const prefix = firstAvailablePrefix(prefixes, ['G', 'M', 'k', 'u', 'n', 'p', 'f', 'm', '']);
        return `10${prefix}`;
    });
    const rangeStepPlaceholder = computed(() => {
        const prefixes = selectedSpecTypeInputPrefixes.value;
        if (prefixes.includes('M')) return '100M';
        if (prefixes.includes('u')) return '100u';
        if (prefixes.includes('p')) return '100p';
        return '100';
    });
    const filteredSpecTypes = computed(() => {
        const current = selectedSpecType.value;
        if (!form.spec_group_id) return specTypes.value;

        const candidates = selectedSpecGroup.value?.spec_types ?? selectedSpecGroup.value?.specTypes ?? [];
        const rows = [...candidates];
        if (current && !rows.some((item) => Number(item.id) === Number(current.id))) {
            rows.unshift(current);
        }

        return rows;
    });
    const filteredPackages = computed(() => {
        if (!form.package_group_id) return [];

        return packages.value.filter((item) => Number(item.package_group_id) === Number(form.package_group_id));
    });

    const visibleValues = computed(() => {
        if (previewRows.value.length) return previewRows.value;
        return selected.value?.values ?? [];
    });
    const materializableValues = computed(() => (selected.value?.values ?? [])
        .filter((value) => value.id && value.is_enabled && !value.materialized_component_id));
    const materializeDisabled = computed(() =>
        !canEdit || !selected.value || previewRows.value.length > 0 || selectedValueIds.value.length === 0
    );
    const materializeHint = computed(() => {
        if (!canEdit) return '編集権限がありません';
        if (!selected.value) return 'シリーズを保存すると値を選択できます';
        if (previewRows.value.length > 0) return 'プレビュー中の値は保存後に部品化できます';
        if (!visibleValues.value.length) return '値候補を作成して保存してください';
        if (!materializableValues.value.length) return '部品化できる未登録値がありません';
        if (selectedValueIds.value.length === 0) return '左のチェック欄で値を選んでください';
        return `${selectedValueIds.value.length}件を部品として登録します`;
    });

    // 目的: 部品系列管理のreset Formを扱う。機能: 入力値を検証・整形し、画面または計算処理へ渡す。入力: 関数シグネチャの値。出力: 処理結果またはなし。動作条件: 部品系列管理の初期化後に呼び出す。副作用: Vue状態、localStorage、DOM、HTTP通信のいずれかを更新する場合がある。
    const resetForm = () => {
        Object.assign(form, emptyForm());
        previewRows.value = [];
        selectedValueIds.value = [];
        clearPolicyErrors();
    };

    // 目的: 部品系列管理のclear Policy Errorsを扱う。機能: 入力値を検証・整形し、画面または計算処理へ渡す。入力: 関数シグネチャの値。出力: 処理結果またはなし。動作条件: 部品系列管理の初期化後に呼び出す。副作用: Vue状態、localStorage、DOM、HTTP通信のいずれかを更新する場合がある。
    const clearPolicyErrors = () => {
        Object.keys(policyErrors).forEach((key) => {
            delete policyErrors[key];
        });
    };

    // 目的: 部品系列管理のset Policy Errorを扱う。機能: 入力値を検証・整形し、画面または計算処理へ渡す。入力: 関数シグネチャの値。出力: 処理結果またはなし。動作条件: 部品系列管理の初期化後に呼び出す。副作用: Vue状態、localStorage、DOM、HTTP通信のいずれかを更新する場合がある。
    const setPolicyError = (key, message) => {
        policyErrors[key] = [message];
    };

    // 目的: 部品系列管理のfield Errorを扱う。機能: 入力値を検証・整形し、画面または計算処理へ渡す。入力: 関数シグネチャの値。出力: 処理結果またはなし。動作条件: 部品系列管理の初期化後に呼び出す。副作用: Vue状態、localStorage、DOM、HTTP通信のいずれかを更新する場合がある。
    const fieldError = (key) => policyErrors[key]?.[0] ?? '';

    // 目的: 部品系列管理のfirst Policy Errorを扱う。機能: 入力値を検証・整形し、画面または計算処理へ渡す。入力: 関数シグネチャの値。出力: 処理結果またはなし。動作条件: 部品系列管理の初期化後に呼び出す。副作用: Vue状態、localStorage、DOM、HTTP通信のいずれかを更新する場合がある。
    const firstPolicyError = () => Object.values(policyErrors)
        .flat()
        .find(Boolean) ?? '';

    // 目的: 部品系列管理のshow Policy Error Summaryを扱う。機能: 入力値を検証・整形し、画面または計算処理へ渡す。入力: 関数シグネチャの値。出力: 処理結果またはなし。動作条件: 部品系列管理の初期化後に呼び出す。副作用: Vue状態、localStorage、DOM、HTTP通信のいずれかを更新する場合がある。
    const showPolicyErrorSummary = () => {
        const message = firstPolicyError() || 'シリーズ値の入力を確認してください。';
        error.value = message;
        toastError(message);
    };

    // 目的: 部品系列管理のvalidate Policy Formを扱う。機能: 入力値を検証・整形し、画面または計算処理へ渡す。入力: 関数シグネチャの値。出力: 処理結果またはなし。動作条件: 部品系列管理の初期化後に呼び出す。副作用: Vue状態、localStorage、DOM、HTTP通信のいずれかを更新する場合がある。
    const validatePolicyForm = () => {
        clearPolicyErrors();
        const type = form.policy.value_set_type;

        if (isESeriesPolicyType(type)) {
            const allowedPrefixes = selectedSpecTypeInputPrefixes.value;
            const unit = form.policy.unit || selectedSpecTypeUnit.value;
            const prefixHelp = `使える接頭語: ${prefixListText(allowedPrefixes)}`;
            const min = parseEngineeringNumber(form.policy.range_min, allowedPrefixes, unit);
            const max = parseEngineeringNumber(form.policy.range_max, allowedPrefixes, unit);
            if (isBlank(form.policy.range_min)) {
                setPolicyError('range_min', `開始値を入力してください。${prefixHelp}`);
            } else if (min === null) {
                setPolicyError('range_min', `開始値には数値と許可された接頭語を入力してください。${prefixHelp}`);
            } else if (min <= 0) {
                setPolicyError('range_min', 'E系列の開始値は0より大きい値にしてください。0Ωは「0を含む」で追加します。');
            }
            if (isBlank(form.policy.range_max)) {
                setPolicyError('range_max', `終了値を入力してください。${prefixHelp}`);
            } else if (max === null) {
                setPolicyError('range_max', `終了値には数値と許可された接頭語を入力してください。${prefixHelp}`);
            }
            if (min !== null && max !== null && min > 0 && min > max) {
                setPolicyError('range_max', '終了値は開始値以上にしてください。例: 開始値=1、終了値=10G');
            }
        }

        if (type === 'range_step') {
            const allowedPrefixes = selectedSpecTypeInputPrefixes.value;
            const unit = form.policy.unit || selectedSpecTypeUnit.value;
            const prefixHelp = `使える接頭語: ${prefixListText(allowedPrefixes)}`;
            const rangeFields = [
                ['range_min', '開始値'],
                ['range_max', '終了値'],
                ['range_step', '刻み幅'],
            ];
            const parsed = {};
            rangeFields.forEach(([key, label]) => {
                const value = form.policy[key];
                if (isBlank(value)) {
                    setPolicyError(key, `${label}を入力してください。`);
                    return;
                }
                const number = parseEngineeringNumber(value, allowedPrefixes, unit);
                if (number === null) {
                    setPolicyError(key, `${label}には数値と許可された接頭語を入力してください。${prefixHelp}`);
                    return;
                }
                parsed[key] = number;
            });
            if (parsed.range_step !== undefined && parsed.range_step <= 0) {
                setPolicyError('range_step', '刻み幅は0より大きい数値にしてください。');
            }
            if (parsed.range_min !== undefined && parsed.range_max !== undefined && parsed.range_min > parsed.range_max) {
                setPolicyError('range_max', '終了値は開始値以上にしてください。例: 開始値=0、終了値=10G');
            }
        }

        if (Object.keys(policyErrors).length) {
            showPolicyErrorSummary();
            return false;
        }

        return true;
    };

    // 目的: 部品系列管理のapply Request Errorを扱う。機能: 入力値を検証・整形し、画面または計算処理へ渡す。入力: 関数シグネチャの値。出力: 処理結果またはなし。動作条件: 部品系列管理の初期化後に呼び出す。副作用: Vue状態、localStorage、DOM、HTTP通信のいずれかを更新する場合がある。
    const applyRequestError = (e) => {
        clearPolicyErrors();
        Object.entries(e.errors ?? {}).forEach(([key, messages]) => {
            if (!key.startsWith('policy.')) return;
            policyErrors[key.replace(/^policy\./, '')] = Array.isArray(messages) ? messages : [String(messages)];
        });
        const message = firstPolicyError() || e.message;
        error.value = message;
        toastError(message);
    };

    // 目的: 部品系列管理のpolicy Payloadを扱う。機能: 入力値を検証・整形し、画面または計算処理へ渡す。入力: 関数シグネチャの値。出力: 処理結果またはなし。動作条件: 部品系列管理の初期化後に呼び出す。副作用: Vue状態、localStorage、DOM、HTTP通信のいずれかを更新する場合がある。
    const policyPayload = () => {
        const values = splitList(form.policy.values_text);
        const type = form.policy.value_set_type;
        const isSeries = isESeriesPolicyType(type);
        const isRange = type === 'range_step';
        const hasExplicitRange = isSeries && (!isBlank(form.policy.range_min) || !isBlank(form.policy.range_max));
        return {
            value_set_type: type,
            primary_series: isSeries ? form.policy.primary_series || null : null,
            extra_series: type === 'hybrid_series' ? splitList(form.policy.extra_series_text) : [],
            custom_values: type === 'custom_list' ? values : [],
            extra_values: type === 'hybrid_series' ? values : [],
            excluded_values: type === 'none' ? [] : splitList(form.policy.excluded_values_text),
            unit: form.policy.unit || null,
            decade_min: isSeries && !hasExplicitRange ? toIntegerOrNull(form.policy.decade_min) : null,
            decade_max: isSeries && !hasExplicitRange ? toIntegerOrNull(form.policy.decade_max) : null,
            range_min: isSeries || isRange ? cleanTextOrNull(form.policy.range_min) : null,
            range_max: isSeries || isRange ? cleanTextOrNull(form.policy.range_max) : null,
            range_step: isRange ? cleanTextOrNull(form.policy.range_step) : null,
            rounding_digits: 15,
            generation_settings: {
                include_zero: isSeries ? !!form.policy.include_zero : false,
                input_prefixes: selectedSpecTypeInputPrefixes.value,
                display_prefixes: selectedSpecTypeDisplayPrefixes.value,
            },
        };
    };

    // 目的: 部品系列管理のseries Payloadを扱う。機能: 入力値を検証・整形し、画面または計算処理へ渡す。入力: 関数シグネチャの値。出力: 処理結果またはなし。動作条件: 部品系列管理の初期化後に呼び出す。副作用: Vue状態、localStorage、DOM、HTTP通信のいずれかを更新する場合がある。
    const seriesPayload = () => ({
        spec_group_id: form.spec_group_id || null,
        value_spec_type_id: form.value_spec_type_id || null,
        package_id: form.package_id || null,
        manufacturer: form.manufacturer || null,
        name: form.name,
        description: form.description || null,
        status: form.status || 'active',
        sort_order: Number(form.sort_order || 0),
        policy: policyPayload(),
    });

    // 目的: 部品系列管理のsync Policy Unit From Spec Typeを扱う。機能: 入力値を検証・整形し、画面または計算処理へ渡す。入力: 関数シグネチャの値。出力: 処理結果またはなし。動作条件: 部品系列管理の初期化後に呼び出す。副作用: Vue状態、localStorage、DOM、HTTP通信のいずれかを更新する場合がある。
    const syncPolicyUnitFromSpecType = () => {
        if (selectedSpecTypeUnit.value) {
            form.policy.unit = selectedSpecTypeUnit.value;
        }
    };

    // 目的: 部品系列管理のhandle Spec Group Changeを扱う。機能: 入力値を検証・整形し、画面または計算処理へ渡す。入力: 関数シグネチャの値。出力: 処理結果またはなし。動作条件: 部品系列管理の初期化後に呼び出す。副作用: Vue状態、localStorage、DOM、HTTP通信のいずれかを更新する場合がある。
    const handleSpecGroupChange = () => {
        const candidates = filteredSpecTypes.value;
        if (form.value_spec_type_id
            && !candidates.some((item) => Number(item.id) === Number(form.value_spec_type_id))) {
            form.value_spec_type_id = '';
        }
        syncPolicyUnitFromSpecType();
    };

    // 目的: 部品系列管理のhandle Value Spec Type Changeを扱う。機能: 入力値を検証・整形し、画面または計算処理へ渡す。入力: 関数シグネチャの値。出力: 処理結果またはなし。動作条件: 部品系列管理の初期化後に呼び出す。副作用: Vue状態、localStorage、DOM、HTTP通信のいずれかを更新する場合がある。
    const handleValueSpecTypeChange = () => {
        syncPolicyUnitFromSpecType();
    };

    // 目的: 部品系列管理のhandle Package Group Changeを扱う。機能: 入力値を検証・整形し、画面または計算処理へ渡す。入力: 関数シグネチャの値。出力: 処理結果またはなし。動作条件: 部品系列管理の初期化後に呼び出す。副作用: Vue状態、localStorage、DOM、HTTP通信のいずれかを更新する場合がある。
    const handlePackageGroupChange = () => {
        if (form.package_id
            && !filteredPackages.value.some((item) => Number(item.id) === Number(form.package_id))) {
            form.package_id = '';
        }
    };

    // 目的: 部品系列管理のfill Formを扱う。機能: 入力値を検証・整形し、画面または計算処理へ渡す。入力: 関数シグネチャの値。出力: 処理結果またはなし。動作条件: 部品系列管理の初期化後に呼び出す。副作用: Vue状態、localStorage、DOM、HTTP通信のいずれかを更新する場合がある。
    const fillForm = (item) => {
        resetForm();
        const policy = item?.policy ?? {};
        form.id = item?.id ?? null;
        form.spec_group_id = item?.spec_group_id ?? '';
        form.value_spec_type_id = item?.value_spec_type_id ?? '';
        form.package_group_id = item?.package?.package_group_id ?? item?.package?.package_group?.id ?? '';
        form.package_id = item?.package_id ?? '';
        form.manufacturer = item?.manufacturer ?? '';
        form.name = item?.name ?? '';
        form.description = item?.description ?? '';
        form.status = item?.status ?? 'active';
        form.sort_order = item?.sort_order ?? 0;
        form.policy.value_set_type = policy.value_set_type ?? 'hybrid_series';
        form.policy.primary_series = policy.primary_series ?? 'E12';
        form.policy.extra_series_text = (policy.extra_series ?? []).join(', ');
        form.policy.values_text = [
            ...(policy.extra_values ?? []),
            ...(policy.custom_values ?? []),
        ].join(', ');
        form.policy.excluded_values_text = (policy.excluded_values ?? []).join(', ');
        const policyUnit = policy.unit ?? selectedSpecTypeUnit.value ?? 'Ω';
        form.policy.unit = policyUnit;
        form.policy.decade_min = policy.decade_min ?? 0;
        form.policy.decade_max = policy.decade_max ?? 10;
        const hasRangeMin = policy.range_min !== null && policy.range_min !== undefined;
        const hasRangeMax = policy.range_max !== null && policy.range_max !== undefined;
        form.policy.range_min = hasRangeMin
            ? formatEngineeringValue(policy.range_min, policyUnit, selectedSpecTypeDisplayPrefixes.value)
            : (isESeriesPolicyType(policy.value_set_type ?? 'hybrid_series') ? formatPowerOfTen(policy.decade_min ?? 0) : '');
        form.policy.range_max = hasRangeMax
            ? formatEngineeringValue(policy.range_max, policyUnit, selectedSpecTypeDisplayPrefixes.value)
            : (isESeriesPolicyType(policy.value_set_type ?? 'hybrid_series') ? formatPowerOfTen(policy.decade_max ?? 10) : '');
        form.policy.range_step = policy.range_step ?? '';
        form.policy.include_zero = !!policy.generation_settings?.include_zero;
        syncPolicyUnitFromSpecType();
    };

    // 目的: 部品系列管理のfetch Seriesを扱う。機能: 入力値を検証・整形し、画面または計算処理へ渡す。入力: 関数シグネチャの値。出力: 処理結果またはなし。動作条件: 部品系列管理の初期化後に呼び出す。副作用: Vue状態、localStorage、DOM、HTTP通信のいずれかを更新する場合がある。
    const fetchSeries = async () => {
        try {
            const params = new URLSearchParams({ include_archived: '1' });
            if (search.value.trim()) params.set('q', search.value.trim());
            const res = await api.get(`/component-series?${params.toString()}`);
            seriesList.value = res.data ?? [];
            error.value = '';
        } catch (e) {
            applyRequestError(e);
        }
    };

    // 目的: 部品系列管理のfetch Optionsを扱う。機能: 入力値を検証・整形し、画面または計算処理へ渡す。入力: 関数シグネチャの値。出力: 処理結果またはなし。動作条件: 部品系列管理の初期化後に呼び出す。副作用: Vue状態、localStorage、DOM、HTTP通信のいずれかを更新する場合がある。
    const fetchOptions = async () => {
        const [groups, types, pkgGroups, pkgs] = await Promise.all([
            api.get('/spec-groups?include_archived=1&with_spec_types=1'),
            api.get('/spec-types?include_archived=1'),
            api.get('/package-groups?include_archived=1'),
            api.get('/packages?include_archived=1'),
        ]);
        specGroups.value = groups.data ?? [];
        specTypes.value = types.data ?? [];
        packageGroups.value = pkgGroups.data ?? [];
        packages.value = pkgs.data ?? [];
    };

    // 目的: 部品系列管理のselect Seriesを扱う。機能: 入力値を検証・整形し、画面または計算処理へ渡す。入力: 関数シグネチャの値。出力: 処理結果またはなし。動作条件: 部品系列管理の初期化後に呼び出す。副作用: Vue状態、localStorage、DOM、HTTP通信のいずれかを更新する場合がある。
    const selectSeries = async (item) => {
        try {
            const res = await api.get(`/component-series/${item.id}`);
            selected.value = res.data;
            fillForm(res.data);
            error.value = '';
        } catch (e) {
            applyRequestError(e);
        }
    };

    // 目的: 部品系列管理のopen Newを扱う。機能: 入力値を検証・整形し、画面または計算処理へ渡す。入力: 関数シグネチャの値。出力: 処理結果またはなし。動作条件: 部品系列管理の初期化後に呼び出す。副作用: Vue状態、localStorage、DOM、HTTP通信のいずれかを更新する場合がある。
    const openNew = () => {
        selected.value = null;
        resetForm();
    };

    watch(() => form.value_spec_type_id, () => {
        syncPolicyUnitFromSpecType();
    });

    watch(() => form.spec_group_id, () => {
        handleSpecGroupChange();
    });

    watch(() => form.package_group_id, () => {
        handlePackageGroupChange();
    });

    // 目的: 部品系列管理のpreview Valuesを扱う。機能: 入力値を検証・整形し、画面または計算処理へ渡す。入力: 関数シグネチャの値。出力: 処理結果またはなし。動作条件: 部品系列管理の初期化後に呼び出す。副作用: Vue状態、localStorage、DOM、HTTP通信のいずれかを更新する場合がある。
    const previewValues = async () => {
        if (!validatePolicyForm()) return;
        try {
            const res = await api.post('/component-series/preview', {
                value_spec_type_id: form.value_spec_type_id || null,
                policy: policyPayload(),
            });
            previewRows.value = res.data?.values ?? [];
            selectedValueIds.value = [];
            error.value = '';
            clearPolicyErrors();
        } catch (e) {
            applyRequestError(e);
        }
    };

    // 目的: 部品系列管理のsave Seriesを扱う。機能: 入力値を検証・整形し、画面または計算処理へ渡す。入力: 関数シグネチャの値。出力: 処理結果またはなし。動作条件: 部品系列管理の初期化後に呼び出す。副作用: Vue状態、localStorage、DOM、HTTP通信のいずれかを更新する場合がある。
    const saveSeries = async () => {
        if (!validatePolicyForm()) return;
        saving.value = true;
        try {
            const payload = seriesPayload();
            const res = form.id
                ? await api.put(`/component-series/${form.id}`, payload)
                : await api.post('/component-series', payload);
            selected.value = res.data;
            fillForm(res.data);
            previewRows.value = [];
            await fetchSeries();
            error.value = '';
            clearPolicyErrors();
        } catch (e) {
            applyRequestError(e);
        } finally {
            saving.value = false;
        }
    };

    // 目的: 部品系列管理のmaterialize Selectedを扱う。機能: 入力値を検証・整形し、画面または計算処理へ渡す。入力: 関数シグネチャの値。出力: 処理結果またはなし。動作条件: 部品系列管理の初期化後に呼び出す。副作用: Vue状態、localStorage、DOM、HTTP通信のいずれかを更新する場合がある。
    const materializeSelected = async () => {
        if (materializeDisabled.value) return;
        try {
            const res = await api.post(`/component-series/${selected.value.id}/materialize`, {
                value_ids: selectedValueIds.value,
            });
            selected.value = res.data?.series ?? selected.value;
            fillForm(selected.value);
            selectedValueIds.value = [];
            await fetchSeries();
            error.value = '';
        } catch (e) {
            error.value = e.message;
            toastError(e.message);
        }
    };

    // 目的: 部品系列管理のorigin Labelを扱う。機能: 入力値を検証・整形し、画面または計算処理へ渡す。入力: 関数シグネチャの値。出力: 処理結果またはなし。動作条件: 部品系列管理の初期化後に呼び出す。副作用: Vue状態、localStorage、DOM、HTTP通信のいずれかを更新する場合がある。
    const originLabel = (origin, sourceSeries = '') => {
        const labels = {
            primary_generated: sourceSeries || '基準系列',
            extra_series: sourceSeries ? `追加 ${sourceSeries}` : '追加系列',
            custom_list: '任意値',
            manual: '手動追加',
            included_zero: '0含む',
            range_step: '範囲/刻み',
            excluded: '除外',
        };
        return labels[origin] ?? origin ?? '-';
    };

    onMounted(async () => {
        try {
            await Promise.all([fetchOptions(), fetchSeries()]);
            if (seriesList.value.length) await selectSeries(seriesList.value[0]);
        } catch (e) {
            error.value = e.message;
            toastError(e.message);
        }
    });

    return {
        toasts,
        canEdit,
        eSeriesOptions,
        seriesList,
        specGroups,
        specTypes,
        packageGroups,
        packages,
        selected,
        selectedValueIds,
        visibleValues,
        search,
        error,
        saving,
        form,
        policyErrors,
        filteredSpecTypes,
        filteredPackages,
        usesESeries,
        usesHybridSeries,
        usesCustomList,
        usesRangeStep,
        usesValueTextList,
        usesExclusions,
        seriesRangeText,
        inputPrefixHelpText,
        startValuePlaceholder,
        endValuePlaceholder,
        rangeStepPlaceholder,
        materializeDisabled,
        materializeHint,
        valueListLabel,
        valueListHelp,
        valueListPlaceholder,
        fetchSeries,
        selectSeries,
        openNew,
        handleSpecGroupChange,
        handleValueSpecTypeChange,
        handlePackageGroupChange,
        previewValues,
        saveSeries,
        materializeSelected,
        originLabel,
        fieldError,
    };
}
