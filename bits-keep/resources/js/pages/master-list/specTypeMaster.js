import { ref, reactive, computed } from 'vue';
import { api } from '../../api.js';
import {
    isByteBitUnit,
    prefixOptionsForUnit,
    prefixPolicyHelpForUnit,
    sanitizePrefixesForUnit as sanitizeEngineeringPrefixesForUnit,
    syncPrefixSelectionForUnit,
} from '../../utils/engineeringUnits.js';

/**
 * スペック詳細・共通スペック詳細・許容差スペック詳細の編集責務を束ねる。
 * 目的: スペック詳細のフォーム生成、接頭辞同期、許容差設定の正規化、保存後同期を setup から分離する。
 * 入力: 選択中部品分類 ref、通知関数、確認モーダル関数、共通ユーティリティ、部品分類再読込関数。
 * 出力: Blade が使う一覧、モーダル状態、追加/編集/複製/保存/復元関数、表示ラベル関数。
 * 動作条件: 選択中部品分類が必要な個別スペック詳細は currentSpecGroup が存在する時だけ追加できる。
 * 副作用: /spec-types API 通信、toast 表示、スペック詳細一覧、共通一覧、候補選択肢、モーダル状態を更新する。
 */
export function useSpecTypeMaster({
    selectedSpecGroupId,
    currentSpecGroup,
    fetchSpecGroups,
    fetchError,
    toastSuccess,
    toastError,
    openConfirm,
    closeModalWithConfirm,
    clone,
    splitActive,
    splitArchived,
    nextSortOrder,
    copyName,
}) {
    // スペック詳細
    const specTypes = ref([]);
    const commonSpecTypes = ref([]);
    const specTypeOptions = ref([]);
    // 目的: マスタ管理画面のis Tolerance Spec Typeを扱う。機能: 入力値を検証・整形し、画面または計算処理へ渡す。入力: 関数シグネチャの値。出力: 真偽値。動作条件: マスタ管理画面の初期化後に呼び出す。副作用: なし。
    const isToleranceSpecType = (item) => (item?.spec_kind ?? 'normal') === 'tolerance';
    // 目的: マスタ管理画面のis Normal Spec Typeを扱う。機能: 入力値を検証・整形し、画面または計算処理へ渡す。入力: 関数シグネチャの値。出力: 真偽値。動作条件: マスタ管理画面の初期化後に呼び出す。副作用: なし。
    const isNormalSpecType = (item) => !isToleranceSpecType(item);
    // 目的: マスタ管理画面のis Common Spec Typeを扱う。機能: 入力値を検証・整形し、画面または計算処理へ渡す。入力: 関数シグネチャの値。出力: 真偽値。動作条件: マスタ管理画面の初期化後に呼び出す。副作用: なし。
    const isCommonSpecType = (item) => (item?.spec_scope ?? 'group_local') === 'common';
    const activeSpecTypes = computed(() => splitActive(specTypes.value).filter((item) => !isCommonSpecType(item) && isNormalSpecType(item)));
    const archivedSpecTypes = computed(() => splitArchived(specTypes.value).filter((item) => !isCommonSpecType(item) && isNormalSpecType(item)));
    const activeCommonSpecTypes = computed(() => splitActive(commonSpecTypes.value).filter(isNormalSpecType));
    const archivedCommonSpecTypes = computed(() => splitArchived(commonSpecTypes.value).filter(isNormalSpecType));
    const activeToleranceSpecTypes = computed(() => splitActive(commonSpecTypes.value).filter(isToleranceSpecType));
    const archivedToleranceSpecTypes = computed(() => splitArchived(commonSpecTypes.value).filter(isToleranceSpecType));
    const activeSpecTypeOptions = computed(() => splitActive(specTypeOptions.value));
    const stSnapshot = ref(null);
    const stModal = reactive({
        open: false, isEdit: false, editId: null,
        form: { name: '', name_ja: '', name_en: '', symbol: '', aliases_text: '', description: '', value_type: 'numeric', sort_order: 0, unit: '', suggest_prefixes: [], display_prefixes: [], spec_scope: 'group_local', owner_spec_group_id: '', spec_kind: 'normal', tolerance_settings: { default_mode: 'symmetric', default_unit: '%', allowed_units: ['%', 'ppm'], grade_options: [], grade_options_text: '' } }
    });

    // 目的: マスタ管理画面のfetch Spec Typesを扱う。機能: 入力値を検証・整形し、画面または計算処理へ渡す。入力: 関数シグネチャの値。出力: 処理結果またはなし。動作条件: マスタ管理画面の初期化後に呼び出す。副作用: Vue状態、localStorage、DOM、HTTP通信のいずれかを更新する場合がある。
    const fetchSpecTypes = async () => {
        fetchError.value = '';
        if (!selectedSpecGroupId.value) {
            specTypes.value = [];
            return;
        }

        const params = new URLSearchParams({
            include_archived: '1',
            scope: 'group_local',
            kind: 'normal',
            owner_spec_group_id: String(selectedSpecGroupId.value),
        });
        try { const r = await api.get(`/spec-types?${params.toString()}`); specTypes.value = r.data; }
        catch { fetchError.value = 'スペック詳細の取得に失敗しました。再試行してください。'; toastError('スペック詳細の取得に失敗しました'); }
    };
    // 目的: マスタ管理画面のfetch Common Spec Typesを扱う。機能: 入力値を検証・整形し、画面または計算処理へ渡す。入力: 関数シグネチャの値。出力: 処理結果またはなし。動作条件: マスタ管理画面の初期化後に呼び出す。副作用: Vue状態、localStorage、DOM、HTTP通信のいずれかを更新する場合がある。
    const fetchCommonSpecTypes = async () => {
        fetchError.value = '';
        try { const r = await api.get('/spec-types?include_archived=1&scope=common'); commonSpecTypes.value = r.data; }
        catch { fetchError.value = '共通スペック詳細の取得に失敗しました。再試行してください。'; toastError('共通スペック詳細の取得に失敗しました'); }
    };
    // 目的: マスタ管理画面のfetch Spec Type Optionsを扱う。機能: 入力値を検証・整形し、画面または計算処理へ渡す。入力: 関数シグネチャの値。出力: 処理結果またはなし。動作条件: マスタ管理画面の初期化後に呼び出す。副作用: Vue状態、localStorage、DOM、HTTP通信のいずれかを更新する場合がある。
    const fetchSpecTypeOptions = async () => {
        fetchError.value = '';
        try { const r = await api.get('/spec-types?summary=1'); specTypeOptions.value = r.data ?? []; }
        catch { fetchError.value = 'スペック詳細候補の取得に失敗しました。再試行してください。'; toastError('スペック詳細候補の取得に失敗しました'); }
    };
    // 目的: マスタ管理画面のis Byte Bit Prefix Unitを扱う。機能: 入力値を検証・整形し、画面または計算処理へ渡す。入力: 関数シグネチャの値。出力: 真偽値。動作条件: マスタ管理画面の初期化後に呼び出す。副作用: なし。
    const isByteBitPrefixUnit = (unit = stModal.form.unit) => isByteBitUnit(unit);
    // 目的: マスタ管理画面のsanitize Prefixes For Unitを扱う。機能: 入力値を検証・整形し、画面または計算処理へ渡す。入力: 関数シグネチャの値。出力: 処理結果またはなし。動作条件: マスタ管理画面の初期化後に呼び出す。副作用: Vue状態、localStorage、DOM、HTTP通信のいずれかを更新する場合がある。
    const sanitizePrefixesForUnit = (prefixes = [], unit = stModal.form.unit) => sanitizeEngineeringPrefixesForUnit(prefixes, unit);
    // 目的: マスタ管理画面のprefix Options Forを扱う。機能: 入力値を検証・整形し、画面または計算処理へ渡す。入力: 関数シグネチャの値。出力: 処理結果またはなし。動作条件: マスタ管理画面の初期化後に呼び出す。副作用: Vue状態、localStorage、DOM、HTTP通信のいずれかを更新する場合がある。
    const prefixOptionsFor = () => prefixOptionsForUnit(stModal.form.unit);
    const prefixPolicyHelp = computed(() => (
        prefixPolicyHelpForUnit(stModal.form.unit, '単位入力時')
    ));
    const stModalTitle = computed(() => {
        const base = stModal.form.spec_kind === 'tolerance'
            ? '許容差スペック詳細'
            : (stModal.form.spec_scope === 'common' ? '共通スペック詳細' : 'スペック詳細');
        return `${base}${stModal.isEdit ? '編集' : '追加'}`;
    });
    // 目的: マスタ管理画面のsync Prefix Listを扱う。機能: 入力値を検証・整形し、画面または計算処理へ渡す。入力: 関数シグネチャの値。出力: 処理結果またはなし。動作条件: マスタ管理画面の初期化後に呼び出す。副作用: Vue状態、localStorage、DOM、HTTP通信のいずれかを更新する場合がある。
    const syncPrefixList = (field, changedPrefix = null) => {
        stModal.form[field] = syncPrefixSelectionForUnit(stModal.form[field], stModal.form.unit, changedPrefix);
    };

    const toleranceUnitOptions = [
        { value: '%', label: '%' },
        { value: 'ppm', label: 'ppm' },
        { value: 'pF', label: 'pF' },
        { value: 'ppm/℃', label: 'ppm/℃' },
        { value: 'code', label: 'コード' },
    ];
    // 目的: マスタ管理画面のdefault Tolerance Unitsを扱う。機能: 入力値を検証・整形し、画面または計算処理へ渡す。入力: 関数シグネチャの値。出力: 表示値、配列、オブジェクト、数値のいずれか。動作条件: マスタ管理画面の初期化後に呼び出す。副作用: なし。
    const defaultToleranceUnits = () => ['%', 'ppm'];
    const normalizeToleranceUnits = (units, fallback = defaultToleranceUnits()) => {
        const normalized = Array.isArray(units)
            ? units.map((unit) => String(unit ?? '').trim()).filter(Boolean)
            : [];

        return normalized.length > 0 ? [...new Set(normalized)] : fallback;
    };
    // 目的: マスタ管理画面のdefault Grade Optionsを扱う。機能: 入力値を検証・整形し、画面または計算処理へ渡す。入力: 関数シグネチャの値。出力: 表示値、配列、オブジェクト、数値のいずれか。動作条件: マスタ管理画面の初期化後に呼び出す。副作用: なし。
    const defaultGradeOptions = () => [
        { label: 'F', value: 1, unit: '%' },
        { label: 'G', value: 2, unit: '%' },
        { label: 'J', value: 5, unit: '%' },
        { label: 'K', value: 10, unit: '%' },
        { label: 'M', value: 20, unit: '%' },
    ];
    // 目的: マスタ管理画面のformat Grade Optionsを扱う。機能: 入力値を検証・整形し、画面または計算処理へ渡す。入力: 関数シグネチャの値。出力: 表示値、配列、オブジェクト、数値のいずれか。動作条件: マスタ管理画面の初期化後に呼び出す。副作用: なし。
    const formatGradeOptions = (options = []) => Array.isArray(options)
        ? options.map((option) => {
            const label = option?.label ?? option?.rank ?? '';
            if (!label) return '';
            const unit = option?.unit ?? '%';
            if (option?.plus !== undefined || option?.minus !== undefined) {
                return `${label}: +${option?.plus ?? ''}/-${option?.minus ?? ''}${unit}`;
            }
            if (option?.value !== undefined) return `${label}: ±${option.value}${unit}`;
            return `${label}:`;
        }).filter(Boolean).join('\n')
        : '';
    // 目的: マスタ管理画面のparse Grade Optionsを扱う。機能: 入力値を検証・整形し、画面または計算処理へ渡す。入力: 関数シグネチャの値。出力: 表示値、配列、オブジェクト、数値のいずれか。動作条件: マスタ管理画面の初期化後に呼び出す。副作用: なし。
    const parseGradeOptions = (text = '', fallbackUnit = '%') => String(text ?? '')
        .split(/\r?\n/u)
        .map((line) => line.trim())
        .filter(Boolean)
        .map((line) => {
            const match = line.match(/^([^:：\s]+)\s*[:：]\s*(.+)$/u);
            if (!match) return null;
            const label = match[1].trim();
            const rawValue = match[2].trim();
            const asymmetric = rawValue.match(/^\+?\s*([0-9]+(?:\.[0-9]+)?)\s*\/\s*-?\s*([0-9]+(?:\.[0-9]+)?)\s*(ppm\/℃|%|ppm|pF|code)?$/u);
            if (asymmetric) {
                return {
                    label,
                    plus: Number(asymmetric[1]),
                    minus: Number(asymmetric[2]),
                    unit: asymmetric[3] || fallbackUnit || '%',
                };
            }
            const symmetric = rawValue.match(/^±?\s*([0-9]+(?:\.[0-9]+)?)\s*(ppm\/℃|%|ppm|pF|code)?$/u);
            if (symmetric) {
                return {
                    label,
                    value: Number(symmetric[1]),
                    unit: symmetric[2] || fallbackUnit || '%',
                };
            }
            return { label, text: rawValue, unit: fallbackUnit || '%' };
        })
        .filter(Boolean);
    // 目的: マスタ管理画面のdefault Tolerance Settingsを扱う。機能: 入力値を検証・整形し、画面または計算処理へ渡す。入力: 関数シグネチャの値。出力: 表示値、配列、オブジェクト、数値のいずれか。動作条件: マスタ管理画面の初期化後に呼び出す。副作用: なし。
    const defaultToleranceSettings = (overrides = {}) => {
        const defaultUnit = overrides.default_unit ?? overrides.unit ?? '%';
        const gradeOptions = Array.isArray(overrides.grade_options) ? overrides.grade_options : defaultGradeOptions();
        return {
            default_mode: overrides.default_mode ?? overrides.mode ?? overrides.input_format ?? 'symmetric',
            default_unit: defaultUnit,
            allowed_units: normalizeToleranceUnits(overrides.allowed_units),
            grade_options: gradeOptions,
            grade_options_text: overrides.grade_options_text ?? overrides.rank_definitions ?? formatGradeOptions(gradeOptions),
        };
    };
    // 目的/機能: 許容差設定を画面フォームで扱える形へ正規化する。入力: API値またはJSON文字列。出力: default/allowed/grade を持つ設定。動作条件: 不正JSONは既定値へ戻す。副作用: なし。
    const normalizeToleranceSettings = (settings = {}, fallbackUnit = '%') => {
        let source = settings ?? {};
        if (typeof source === 'string') {
            try { source = JSON.parse(source); }
            catch { source = {}; }
        }
        const defaultUnit = source?.default_unit ?? source?.unit ?? fallbackUnit ?? '%';
        const gradeOptions = Array.isArray(source?.grade_options)
            ? source.grade_options
            : parseGradeOptions(source?.grade_options_text ?? source?.rank_definitions ?? '', defaultUnit);
        return defaultToleranceSettings({
            default_mode: source?.default_mode ?? source?.mode ?? source?.input_format ?? 'symmetric',
            default_unit: defaultUnit,
            allowed_units: normalizeToleranceUnits(source?.allowed_units),
            grade_options: gradeOptions.length > 0 ? gradeOptions : defaultGradeOptions(),
            grade_options_text: source?.grade_options_text ?? source?.rank_definitions ?? formatGradeOptions(gradeOptions),
        });
    };
    // 目的: マスタ管理画面のcompact Tolerance Settingsを扱う。機能: 入力値を検証・整形し、画面または計算処理へ渡す。入力: 関数シグネチャの値。出力: 処理結果またはなし。動作条件: マスタ管理画面の初期化後に呼び出す。副作用: Vue状態、localStorage、DOM、HTTP通信のいずれかを更新する場合がある。
    const compactToleranceSettings = (settings = {}, fallbackUnit = '%') => {
        const normalized = normalizeToleranceSettings(settings, fallbackUnit);
        const gradeOptions = parseGradeOptions(normalized.grade_options_text, normalized.default_unit);
        return {
            default_mode: normalized.default_mode || 'symmetric',
            default_unit: normalized.default_unit || '%',
            allowed_units: normalizeToleranceUnits(normalized.allowed_units),
            grade_options: gradeOptions.length > 0 ? gradeOptions : normalized.grade_options,
        };
    };
    // 目的/機能: スペック詳細モーダルのフォーム初期値を作る。入力: 上書き値。出力: 保存用フォーム。動作条件: 個別/共通/許容差の呼び出し元が scope/kind を指定。副作用: なし。
    const specTypeForm = (overrides = {}) => ({
        name: '',
        name_ja: '',
        name_en: '',
        symbol: '',
        aliases_text: '',
        description: '',
        value_type: 'numeric',
        sort_order: nextSortOrder(specTypes.value),
        unit: '',
        suggest_prefixes: [],
        display_prefixes: [],
        spec_scope: 'group_local',
        owner_spec_group_id: '',
        spec_kind: 'normal',
        tolerance_settings: defaultToleranceSettings(),
        ...overrides,
    });
    // 目的: マスタ管理画面のopen St Addを扱う。機能: 入力値を検証・整形し、画面または計算処理へ渡す。入力: 関数シグネチャの値。出力: 処理結果またはなし。動作条件: マスタ管理画面の初期化後に呼び出す。副作用: Vue状態、localStorage、DOM、HTTP通信のいずれかを更新する場合がある。
    const openStAdd = (overrides = {}) => {
        const form = specTypeForm(overrides);
        stSnapshot.value = clone(form);
        Object.assign(stModal, { open: true, isEdit: false, editId: null, form });
    };
    // 目的: マスタ管理画面のopen Common Spec Type Addを扱う。機能: 入力値を検証・整形し、画面または計算処理へ渡す。入力: 関数シグネチャの値。出力: 処理結果またはなし。動作条件: マスタ管理画面の初期化後に呼び出す。副作用: Vue状態、localStorage、DOM、HTTP通信のいずれかを更新する場合がある。
    const openCommonSpecTypeAdd = () => openStAdd({ spec_scope: 'common', owner_spec_group_id: null });
    // 目的: マスタ管理画面のopen Tolerance Spec Type Addを扱う。機能: 入力値を検証・整形し、画面または計算処理へ渡す。入力: 関数シグネチャの値。出力: 処理結果またはなし。動作条件: マスタ管理画面の初期化後に呼び出す。副作用: Vue状態、localStorage、DOM、HTTP通信のいずれかを更新する場合がある。
    const openToleranceSpecTypeAdd = () => openStAdd({
        spec_scope: 'common',
        owner_spec_group_id: null,
        spec_kind: 'tolerance',
        value_type: 'numeric',
        unit: '%',
        tolerance_settings: defaultToleranceSettings(),
    });
    // 目的: マスタ管理画面のopen Local Spec Type Addを扱う。機能: 入力値を検証・整形し、画面または計算処理へ渡す。入力: 関数シグネチャの値。出力: 処理結果またはなし。動作条件: マスタ管理画面の初期化後に呼び出す。副作用: Vue状態、localStorage、DOM、HTTP通信のいずれかを更新する場合がある。
    const openLocalSpecTypeAdd = () => {
        if (!currentSpecGroup.value) {
            toastError('先に部品分類を選択してください');
            return;
        }
        openStAdd({ spec_scope: 'group_local', owner_spec_group_id: currentSpecGroup.value.id });
    };
    // 目的/機能: 一覧行から編集フォームを開く。入力: スペック詳細行。出力: なし。動作条件: 詳細情報不足時は単体取得する。副作用: API取得、接頭辞整理、モーダル状態更新。
    const openStEdit = async (s) => {
        let detail = s;
        if (!Array.isArray(s.aliases) || !Array.isArray(s.units) || s.suggest_prefixes === undefined) {
            try {
                const r = await api.get(`/spec-types/${s.id}`);
                detail = r.data;
            } catch (e) {
                toastError(e.message);
                return;
            }
        }
        const unit = detail.units?.[0]?.unit ?? detail.base_unit ?? '';
        const form = {
            name: detail.name, name_ja: detail.name_ja ?? detail.name, name_en: detail.name_en ?? '', symbol: detail.symbol ?? '',
            aliases_text: (detail.aliases ?? []).map((alias) => alias.alias).join('\n'),
            description: detail.description ?? '',
            value_type: detail.value_type ?? 'numeric',
            sort_order: detail.sort_order ?? 0,
            unit,
            suggest_prefixes: sanitizePrefixesForUnit(detail.suggest_prefixes, unit),
            display_prefixes: sanitizePrefixesForUnit(detail.display_prefixes, unit),
            spec_scope: detail.spec_scope ?? 'group_local',
            owner_spec_group_id: detail.owner_spec_group_id ?? '',
            spec_kind: detail.spec_kind ?? 'normal',
            tolerance_settings: normalizeToleranceSettings(detail.tolerance_settings, unit || '%'),
        };
        stSnapshot.value = clone(form);
        Object.assign(stModal, { open: true, isEdit: true, editId: detail.id, form });
    };
    // 目的: マスタ管理画面のopen St Duplicateを扱う。機能: 入力値を検証・整形し、画面または計算処理へ渡す。入力: 関数シグネチャの値。出力: 処理結果またはなし。動作条件: マスタ管理画面の初期化後に呼び出す。副作用: Vue状態、localStorage、DOM、HTTP通信のいずれかを更新する場合がある。
    const openStDuplicate = (s, overrides = {}) => {
        const unit = s.units?.[0]?.unit ?? s.base_unit ?? '';
        const form = {
            name: copyName(s.name), name_ja: copyName(s.name_ja ?? s.name), name_en: s.name_en ?? '', symbol: s.symbol ?? '',
            aliases_text: (s.aliases ?? []).map((alias) => alias.alias).join('\n'),
            description: s.description ?? '',
            value_type: s.value_type ?? 'numeric',
            sort_order: nextSortOrder(specTypes.value),
            unit,
            suggest_prefixes: sanitizePrefixesForUnit(s.suggest_prefixes, unit),
            display_prefixes: sanitizePrefixesForUnit(s.display_prefixes, unit),
            spec_scope: s.spec_scope ?? 'group_local',
            owner_spec_group_id: s.owner_spec_group_id ?? '',
            spec_kind: s.spec_kind ?? 'normal',
            tolerance_settings: normalizeToleranceSettings(s.tolerance_settings, unit || '%'),
            ...overrides,
        };
        stSnapshot.value = clone(form);
        Object.assign(stModal, { open: true, isEdit: false, editId: null, form });
    };
    // 目的: マスタ管理画面のopen Common Spec Type Duplicateを扱う。機能: 入力値を検証・整形し、画面または計算処理へ渡す。入力: 関数シグネチャの値。出力: 処理結果またはなし。動作条件: マスタ管理画面の初期化後に呼び出す。副作用: Vue状態、localStorage、DOM、HTTP通信のいずれかを更新する場合がある。
    const openCommonSpecTypeDuplicate = (specType) => openStDuplicate(specType, { spec_scope: 'common', owner_spec_group_id: null });

    // 目的/機能: スペック詳細フォームをAPI保存payloadへ変換して保存する。入力: stModal.form。出力: なし。動作条件: 許容差は numeric と既定単位へ寄せる。副作用: API通信、関連一覧/部品分類詳細再取得、toast、モーダル終了。
    const saveSpecType = async () => {
        try {
            const suggestPrefixes = sanitizePrefixesForUnit(stModal.form.suggest_prefixes);
            const displayPrefixes = sanitizePrefixesForUnit(stModal.form.display_prefixes);
            const toleranceSettings = compactToleranceSettings(stModal.form.tolerance_settings, stModal.form.unit || '%');
            const isTolerance = stModal.form.spec_kind === 'tolerance';
            const payload = {
                ...stModal.form,
                name: stModal.form.name_ja || stModal.form.name,
                owner_spec_group_id: stModal.form.spec_scope === 'common' ? null : (stModal.form.owner_spec_group_id || null),
                value_type: isTolerance ? 'numeric' : stModal.form.value_type,
                unit: isTolerance ? (toleranceSettings.default_unit || '%') : stModal.form.unit,
                aliases: String(stModal.form.aliases_text ?? '')
                    .split(/\r?\n/u)
                    .map((alias) => ({ alias: alias.trim() }))
                    .filter((item) => item.alias),
                suggest_prefixes: !isTolerance && suggestPrefixes.length > 0 ? suggestPrefixes : null,
                display_prefixes: !isTolerance && displayPrefixes.length > 0 ? displayPrefixes : null,
                tolerance_settings: isTolerance ? toleranceSettings : null,
            };
            delete payload.aliases_text;
            delete payload.default_unit;
            if (stModal.isEdit) await api.put(`/spec-types/${stModal.editId}`, payload);
            else await api.post('/spec-types', payload);
            toastSuccess('保存しました');
            stModal.open = false;
            stSnapshot.value = clone(stModal.form);
            await fetchSpecTypes();
            await fetchCommonSpecTypes();
            if (specTypeOptions.value.length > 0) await fetchSpecTypeOptions();
            if (selectedSpecGroupId.value) await fetchSpecGroups({ forceDetail: true });
        } catch (e) { toastError(e.message); }
    };
    // 目的: マスタ管理画面のclose St Modalを扱う。機能: 入力値を検証・整形し、画面または計算処理へ渡す。入力: 関数シグネチャの値。出力: 処理結果またはなし。動作条件: マスタ管理画面の初期化後に呼び出す。副作用: Vue状態、localStorage、DOM、HTTP通信のいずれかを更新する場合がある。
    const closeStModal = () => closeModalWithConfirm(stModal, stSnapshot.value);
    // 目的: マスタ管理画面のspec Type Groupsを扱う。機能: 入力値を検証・整形し、画面または計算処理へ渡す。入力: 関数シグネチャの値。出力: 処理結果またはなし。動作条件: マスタ管理画面の初期化後に呼び出す。副作用: Vue状態、localStorage、DOM、HTTP通信のいずれかを更新する場合がある。
    const specTypeGroups = (item) => item?.spec_groups ?? item?.specGroups ?? [];
    // 目的: マスタ管理画面のspec Type Option Labelを扱う。機能: 入力値を検証・整形し、画面または計算処理へ渡す。入力: 関数シグネチャの値。出力: 処理結果またはなし。動作条件: マスタ管理画面の初期化後に呼び出す。副作用: Vue状態、localStorage、DOM、HTTP通信のいずれかを更新する場合がある。
    const specTypeOptionLabel = (item) => {
        const name = item?.name_ja || item?.name || 'スペック詳細';
        const suffix = [item?.symbol, item?.name_en].filter(Boolean).join(' / ');
        const label = suffix ? `${name} (${suffix})` : name;
        return isToleranceSpecType(item) ? `${label} [許容差]` : label;
    };
    // 目的: マスタ管理画面のtolerance Settings Forを扱う。機能: 入力値を検証・整形し、画面または計算処理へ渡す。入力: 関数シグネチャの値。出力: 処理結果またはなし。動作条件: マスタ管理画面の初期化後に呼び出す。副作用: なし。
    const toleranceSettingsFor = (item) => normalizeToleranceSettings(item?.tolerance_settings, item?.units?.[0]?.unit ?? item?.base_unit ?? '%');
    // 目的: マスタ管理画面のtolerance Unitを扱う。機能: 入力値を検証・整形し、画面または計算処理へ渡す。入力: 関数シグネチャの値。出力: 処理結果またはなし。動作条件: マスタ管理画面の初期化後に呼び出す。副作用: なし。
    const toleranceUnit = (item) => toleranceSettingsFor(item).default_unit || '%';
    // 目的: マスタ管理画面のtolerance Input Formatを扱う。機能: 入力値を検証・整形し、画面または計算処理へ渡す。入力: 関数シグネチャの値。出力: 処理結果またはなし。動作条件: マスタ管理画面の初期化後に呼び出す。副作用: なし。
    const toleranceInputFormat = (item) => ({
        symmetric: '± 対称',
        asymmetric: '+/- 非対称',
        grade: 'ランク',
    })[toleranceSettingsFor(item).default_mode] || toleranceSettingsFor(item).default_mode || '± 対称';
    // 目的: マスタ管理画面のtolerance Allowed Unitsを扱う。機能: 入力値を検証・整形し、画面または計算処理へ渡す。入力: 関数シグネチャの値。出力: 処理結果またはなし。動作条件: マスタ管理画面の初期化後に呼び出す。副作用: なし。
    const toleranceAllowedUnits = (item) => toleranceSettingsFor(item).allowed_units.join(', ');

    // 目的: マスタ管理画面のarchive Spec Typeを扱う。機能: 入力値を検証・整形し、画面または計算処理へ渡す。入力: 関数シグネチャの値。出力: 処理結果またはなし。動作条件: マスタ管理画面の初期化後に呼び出す。副作用: Vue状態、localStorage、DOM、HTTP通信のいずれかを更新する場合がある。
    const archiveSpecType = (s) => openConfirm({
        title: 'スペック詳細をアーカイブしますか？',
        message: `「${s.name}」をアーカイブします。\n使用件数: ${s.usage_count ?? 0}件`,
        actionLabel: 'アーカイブする',
        onConfirm: async () => {
            try { await api.delete(`/spec-types/${s.id}`); await fetchSpecTypes(); await fetchCommonSpecTypes(); if (specTypeOptions.value.length > 0) await fetchSpecTypeOptions(); if (selectedSpecGroupId.value) await fetchSpecGroups({ forceDetail: true }); toastSuccess('アーカイブしました'); }
            catch (e) { toastError(e.message); }
        },
    });
    // 目的: マスタ管理画面のrestore Spec Typeを扱う。機能: 入力値を検証・整形し、画面または計算処理へ渡す。入力: 関数シグネチャの値。出力: 処理結果またはなし。動作条件: マスタ管理画面の初期化後に呼び出す。副作用: Vue状態、localStorage、DOM、HTTP通信のいずれかを更新する場合がある。
    const restoreSpecType = (s) => openConfirm({
        title: 'スペック詳細を復元しますか？',
        message: `「${s.name}」を復元します。`,
        actionLabel: '復元する',
        actionClass: 'border-emerald-400 text-emerald-700 hover:bg-emerald-50',
        onConfirm: async () => {
            try { await api.post(`/spec-types/${s.id}/restore`); await fetchSpecTypes(); await fetchCommonSpecTypes(); if (specTypeOptions.value.length > 0) await fetchSpecTypeOptions(); if (selectedSpecGroupId.value) await fetchSpecGroups({ forceDetail: true }); toastSuccess('復元しました'); }
            catch (e) { toastError(e.message); }
        },
    });

    return {
        specTypes,
        commonSpecTypes,
        specTypeOptions,
        isToleranceSpecType,
        isNormalSpecType,
        isCommonSpecType,
        activeSpecTypes,
        archivedSpecTypes,
        activeCommonSpecTypes,
        archivedCommonSpecTypes,
        activeToleranceSpecTypes,
        archivedToleranceSpecTypes,
        activeSpecTypeOptions,
        stSnapshot,
        stModal,
        fetchSpecTypes,
        fetchCommonSpecTypes,
        fetchSpecTypeOptions,
        isByteBitPrefixUnit,
        sanitizePrefixesForUnit,
        prefixOptionsFor,
        prefixPolicyHelp,
        stModalTitle,
        syncPrefixList,
        toleranceUnitOptions,
        defaultToleranceSettings,
        normalizeToleranceSettings,
        compactToleranceSettings,
        specTypeForm,
        openStAdd,
        openCommonSpecTypeAdd,
        openToleranceSpecTypeAdd,
        openLocalSpecTypeAdd,
        openStEdit,
        openStDuplicate,
        openCommonSpecTypeDuplicate,
        saveSpecType,
        closeStModal,
        specTypeGroups,
        specTypeOptionLabel,
        toleranceSettingsFor,
        toleranceUnit,
        toleranceInputFormat,
        toleranceAllowedUnits,
        archiveSpecType,
        restoreSpecType,
    };
}
