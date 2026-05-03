import { computed, reactive, ref } from 'vue';
import { api } from '../../api.js';
import {
    createEmptySpecRow, getSpecBaseUnit, getSpecDisplayName, getSpecProfileBadgeLabel,
    getSpecProfileControlLabel, getSpecProfileHelpText, getSpecUnitSuggestions,
    normalizeBaseUnitInput, normalizeSpecDraft, normalizeSpecDraftUnitToBase,
    normalizeSpecProfile, SPEC_PROFILE_OPTIONS,
} from '../../utils/specValue.js';
import {
    isByteBitUnit, normalizePrefixList, prefixOptionsForUnit, prefixPolicyHelpForUnit,
    sanitizePrefixesForUnit as sanitizeEngineeringPrefixesForUnit, syncPrefixSelectionForUnit,
} from '../../utils/engineeringUnits.js';

/**
 * 部品詳細画面のスペック編集、候補追加、入力テンプレート適用を構成する。
 * 入力は詳細画面の部品状態・編集モーダル・マスタ候補で、戻り値は編集UIから呼ぶ操作関数群。
 * 動作条件はマスタ候補ロード後で、候補詳細取得、スペック詳細作成、候補リンク更新、トースト通知の副作用を持つ。
 */
// 目的: 部品詳細画面のComponent Detail Specsを扱う。機能: 入力値を検証・整形し、画面または計算処理へ渡す。入力: 関数シグネチャの値。出力: 処理結果またはなし。動作条件: 部品詳細画面の初期化後に呼び出す。副作用: Vue状態、localStorage、DOM、HTTP通信のいずれかを更新する場合がある。
export function useComponentDetailSpecs(ctx) {
    const {
        part, editModal, categories, specTypes, specGroups, specSuggestionTypes, specTemplates,
        selectedSpecGroupId, selectedSpecTypeId, selectedSpecTemplateId, specTypeSearchQuery,
        specSuggestionLoading, canCreateSpecType, defaultSpecGroupIdForPart, toastSuccess, toastError,
    } = ctx;
    const specProfileOptions = SPEC_PROFILE_OPTIONS;
    // 目的: 部品詳細画面のcreate Inline Spec Type Formを扱う。機能: 入力値を検証・整形し、画面または計算処理へ渡す。入力: 関数シグネチャの値。出力: 表示値、配列、オブジェクト、数値のいずれか。動作条件: 部品詳細画面の初期化後に呼び出す。副作用: なし。
    const createInlineSpecTypeForm = () => ({
        name_ja: '',
        name_en: '',
        symbol: '',
        description: '',
        value_type: 'numeric',
        unit: '',
        suggest_prefixes: [],
        display_prefixes: [],
        aliases_text: '',
    });
    const inlineSpecTypeModal = reactive({
        open: false,
        saving: false,
        targetSpec: null,
        form: createInlineSpecTypeForm(),
    });
    /** インライン追加フォームの単位がB/bit/bps系かを判定する。入力は単位文字列、戻り値は真偽値で副作用はない。 */
    // 目的: 部品詳細画面のis Inline Byte Bit Prefix Unitを扱う。機能: 入力値を検証・整形し、画面または計算処理へ渡す。入力: 関数シグネチャの値。出力: 真偽値。動作条件: 部品詳細画面の初期化後に呼び出す。副作用: なし。
    const isInlineByteBitPrefixUnit = (unit = inlineSpecTypeModal.form.unit) => isByteBitUnit(unit);
    const normalizeInlinePrefixes = normalizePrefixList;
    /** インライン追加フォームの接頭語候補を単位ポリシーに合わせて絞る。戻り値は配列で副作用はない。 */
    // 目的: 部品詳細画面のsanitize Inline Prefixes For Unitを扱う。機能: 入力値を検証・整形し、画面または計算処理へ渡す。入力: 関数シグネチャの値。出力: 処理結果またはなし。動作条件: 部品詳細画面の初期化後に呼び出す。副作用: Vue状態、localStorage、DOM、HTTP通信のいずれかを更新する場合がある。
    const sanitizeInlinePrefixesForUnit = (prefixes = [], unit = inlineSpecTypeModal.form.unit) => sanitizeEngineeringPrefixesForUnit(prefixes, unit);
    /** インライン追加フォームの単位に応じた接頭語選択肢を作る。戻り値はUI候補配列で副作用はない。 */
    // 目的: 部品詳細画面のinline Prefix Options Forを扱う。機能: 入力値を検証・整形し、画面または計算処理へ渡す。入力: 関数シグネチャの値。出力: 処理結果またはなし。動作条件: 部品詳細画面の初期化後に呼び出す。副作用: Vue状態、localStorage、DOM、HTTP通信のいずれかを更新する場合がある。
    const inlinePrefixOptionsFor = () => prefixOptionsForUnit(inlineSpecTypeModal.form.unit);
    const inlinePrefixPolicyHelp = computed(() => (
        prefixPolicyHelpForUnit(inlineSpecTypeModal.form.unit, '値入力時')
    ));
    // 目的: 部品詳細画面のsync Inline Prefix Listを扱う。機能: 入力値を検証・整形し、画面または計算処理へ渡す。入力: 関数シグネチャの値。出力: 処理結果またはなし。動作条件: 部品詳細画面の初期化後に呼び出す。副作用: Vue状態、localStorage、DOM、HTTP通信のいずれかを更新する場合がある。
    const syncInlinePrefixList = (field, changedPrefix = null) => {
        inlineSpecTypeModal.form[field] = syncPrefixSelectionForUnit(inlineSpecTypeModal.form[field], inlineSpecTypeModal.form.unit, changedPrefix);
    };


    // 目的: 部品詳細画面のget Spec Type By Idを扱う。機能: 入力値を検証・整形し、画面または計算処理へ渡す。入力: 関数シグネチャの値。出力: 表示値、配列、オブジェクト、数値のいずれか。動作条件: 部品詳細画面の初期化後に呼び出す。副作用: なし。
    const getSpecTypeById = (specTypeId) =>
        specTypes.value.find((item) => Number(item.id) === Number(specTypeId)) ?? null;

    // 目的: 部品詳細画面のis Tolerance Spec Typeを扱う。機能: 入力値を検証・整形し、画面または計算処理へ渡す。入力: 関数シグネチャの値。出力: 真偽値。動作条件: 部品詳細画面の初期化後に呼び出す。副作用: なし。
    const isToleranceSpecType = (specType) => (specType?.spec_kind ?? 'normal') === 'tolerance';
    // 目的: 部品詳細画面のdefault Tolerance Settingsを扱う。機能: 入力値を検証・整形し、画面または計算処理へ渡す。入力: 関数シグネチャの値。出力: 表示値、配列、オブジェクト、数値のいずれか。動作条件: 部品詳細画面の初期化後に呼び出す。副作用: なし。
    const defaultToleranceSettings = (fallbackUnit = '%') => ({
        default_mode: 'symmetric',
        default_unit: fallbackUnit || '%',
        allowed_units: [fallbackUnit || '%'],
        grade_options: [],
    });
    // 目的: 部品詳細画面のnormalize Tolerance Settingsを扱う。機能: 入力値を検証・整形し、画面または計算処理へ渡す。入力: 関数シグネチャの値。出力: 表示値、配列、オブジェクト、数値のいずれか。動作条件: 部品詳細画面の初期化後に呼び出す。副作用: なし。
    const normalizeToleranceSettings = (settings = {}, fallbackUnit = '%') => {
        const source = settings && typeof settings === 'object' ? settings : {};
        const defaultUnit = String(source.default_unit ?? source.unit ?? fallbackUnit ?? '%').trim() || '%';
        const allowedUnits = Array.isArray(source.allowed_units)
            ? [...new Set(source.allowed_units.map((unit) => String(unit ?? '').trim()).filter(Boolean))]
            : [defaultUnit];

        return {
            ...defaultToleranceSettings(defaultUnit),
            default_mode: source.default_mode ?? source.mode ?? source.input_format ?? 'symmetric',
            default_unit: defaultUnit,
            allowed_units: allowedUnits.length ? allowedUnits : [defaultUnit],
            grade_options: Array.isArray(source.grade_options) ? source.grade_options : [],
        };
    };
    // 目的: 部品詳細画面のtolerance Settings For Spec Typeを扱う。機能: 入力値を検証・整形し、画面または計算処理へ渡す。入力: 関数シグネチャの値。出力: 処理結果またはなし。動作条件: 部品詳細画面の初期化後に呼び出す。副作用: なし。
    const toleranceSettingsForSpecType = (specType) =>
        normalizeToleranceSettings(specType?.tolerance_settings, specType?.base_unit ?? specType?.units?.[0]?.unit ?? '%');
    // 目的: 部品詳細画面のspec Type For Specを扱う。機能: 入力値を検証・整形し、画面または計算処理へ渡す。入力: 関数シグネチャの値。出力: 処理結果またはなし。動作条件: 部品詳細画面の初期化後に呼び出す。副作用: Vue状態、localStorage、DOM、HTTP通信のいずれかを更新する場合がある。
    const specTypeForSpec = (spec) => getSpecTypeById(spec?.spec_type_id);
    // 目的: 部品詳細画面のis Tolerance Spec Rowを扱う。機能: 入力値を検証・整形し、画面または計算処理へ渡す。入力: 関数シグネチャの値。出力: 真偽値。動作条件: 部品詳細画面の初期化後に呼び出す。副作用: なし。
    const isToleranceSpecRow = (spec) => isToleranceSpecType(specTypeForSpec(spec));
    // 目的: 部品詳細画面のtolerance Settings For Specを扱う。機能: 入力値を検証・整形し、画面または計算処理へ渡す。入力: 関数シグネチャの値。出力: 処理結果またはなし。動作条件: 部品詳細画面の初期化後に呼び出す。副作用: なし。
    const toleranceSettingsForSpec = (spec) => toleranceSettingsForSpecType(specTypeForSpec(spec));
    // 目的: 部品詳細画面のtolerance Unit Options Forを扱う。機能: 入力値を検証・整形し、画面または計算処理へ渡す。入力: 関数シグネチャの値。出力: 処理結果またはなし。動作条件: 部品詳細画面の初期化後に呼び出す。副作用: なし。
    const toleranceUnitOptionsFor = (spec) => toleranceSettingsForSpec(spec).allowed_units;
    // 目的: 部品詳細画面のtolerance Value Placeholderを扱う。機能: 入力値を検証・整形し、画面または計算処理へ渡す。入力: 関数シグネチャの値。出力: 処理結果またはなし。動作条件: 部品詳細画面の初期化後に呼び出す。副作用: なし。
    const toleranceValuePlaceholder = (spec) => {
        const mode = toleranceSettingsForSpec(spec).default_mode;
        if (mode === 'grade') return '例: J / 5 / +80/-20';
        if (mode === 'asymmetric') return '例: +80/-20';

        return '例: 5 / ±5';
    };
    // 目的: 部品詳細画面のtolerance Grade Options Forを扱う。機能: 入力値を検証・整形し、画面または計算処理へ渡す。入力: 関数シグネチャの値。出力: 処理結果またはなし。動作条件: 部品詳細画面の初期化後に呼び出す。副作用: なし。
    const toleranceGradeOptionsFor = (spec) => toleranceSettingsForSpec(spec).grade_options;
    // 目的: 部品詳細画面のtolerance Grade Option Labelを扱う。機能: 入力値を検証・整形し、画面または計算処理へ渡す。入力: 関数シグネチャの値。出力: 処理結果またはなし。動作条件: 部品詳細画面の初期化後に呼び出す。副作用: なし。
    const toleranceGradeOptionLabel = (option) => {
        const label = String(option?.label ?? option?.rank ?? '').trim();
        const unit = String(option?.unit ?? '').trim();
        if (option?.plus !== undefined || option?.minus !== undefined) {
            return `${label} +${option?.plus ?? ''}/-${option?.minus ?? ''}${unit}`;
        }
        if (option?.value !== undefined) return `${label} ±${option.value}${unit}`;
        return label;
    };
    // 目的: 部品詳細画面のtolerance Grade Option Valueを扱う。機能: 入力値を検証・整形し、画面または計算処理へ渡す。入力: 関数シグネチャの値。出力: 処理結果またはなし。動作条件: 部品詳細画面の初期化後に呼び出す。副作用: なし。
    const toleranceGradeOptionValue = (option) => {
        if (option?.text) return String(option.text);
        if (option?.plus !== undefined || option?.minus !== undefined) {
            return `+${option?.plus ?? ''}/-${option?.minus ?? ''}`;
        }
        if (option?.value !== undefined) return String(option.value);

        return String(option?.label ?? option?.rank ?? '');
    };
    const applyToleranceDefaults = (spec, specType = specTypeForSpec(spec)) => {
        if (!isToleranceSpecType(specType)) return spec;
        const settings = toleranceSettingsForSpecType(specType);
        spec.value_profile = 'typ';
        if (!String(spec.unit ?? '').trim()) {
            spec.unit = settings.default_unit || specType?.base_unit || '%';
        }
        if (!String(spec.value_typ ?? '').trim()) {
            const rangeValue = [spec.value_min, spec.value_max].filter(Boolean).join('〜');
            spec.value_typ = rangeValue || spec.value || '';
        }
        return spec;
    };
    // 目的: 部品詳細画面のapply Tolerance Grade Optionを扱う。機能: 入力値を検証・整形し、画面または計算処理へ渡す。入力: 関数シグネチャの値。出力: 処理結果またはなし。動作条件: 部品詳細画面の初期化後に呼び出す。副作用: Vue状態、localStorage、DOM、HTTP通信のいずれかを更新する場合がある。
    const applyToleranceGradeOption = (spec, option) => {
        spec.value_profile = 'typ';
        spec.value_typ = toleranceGradeOptionValue(option);
        spec.unit = String(option?.unit ?? '').trim() || toleranceSettingsForSpec(spec).default_unit || spec.unit || '%';
    };
    const activeToleranceGradeMenu = ref('');
    // 目的: 部品詳細画面のtolerance Grade Menu Keyを扱う。機能: 入力値を検証・整形し、画面または計算処理へ渡す。入力: 関数シグネチャの値。出力: 処理結果またはなし。動作条件: 部品詳細画面の初期化後に呼び出す。副作用: なし。
    const toleranceGradeMenuKey = (scope, index) => `${scope}-${index}`;
    // 目的: 部品詳細画面のis Tolerance Grade Menu Openを扱う。機能: 入力値を検証・整形し、画面または計算処理へ渡す。入力: 関数シグネチャの値。出力: 真偽値。動作条件: 部品詳細画面の初期化後に呼び出す。副作用: なし。
    const isToleranceGradeMenuOpen = (scope, index) => activeToleranceGradeMenu.value === toleranceGradeMenuKey(scope, index);
    // 目的: 部品詳細画面のclose Tolerance Grade Menuを扱う。機能: 入力値を検証・整形し、画面または計算処理へ渡す。入力: 関数シグネチャの値。出力: 処理結果またはなし。動作条件: 部品詳細画面の初期化後に呼び出す。副作用: Vue状態、localStorage、DOM、HTTP通信のいずれかを更新する場合がある。
    const closeToleranceGradeMenu = () => {
        activeToleranceGradeMenu.value = '';
    };
    // 目的: 部品詳細画面のtoggle Tolerance Grade Menuを扱う。機能: 入力値を検証・整形し、画面または計算処理へ渡す。入力: 関数シグネチャの値。出力: 処理結果またはなし。動作条件: 部品詳細画面の初期化後に呼び出す。副作用: なし。
    const toggleToleranceGradeMenu = (scope, index, spec) => {
        if (!toleranceGradeOptionsFor(spec).length) {
            closeToleranceGradeMenu();
            return;
        }

        const key = toleranceGradeMenuKey(scope, index);
        activeToleranceGradeMenu.value = activeToleranceGradeMenu.value === key ? '' : key;
    };
    // 目的: 部品詳細画面のselect Tolerance Grade Optionを扱う。機能: 入力値を検証・整形し、画面または計算処理へ渡す。入力: 関数シグネチャの値。出力: 処理結果またはなし。動作条件: 部品詳細画面の初期化後に呼び出す。副作用: Vue状態、localStorage、DOM、HTTP通信のいずれかを更新する場合がある。
    const selectToleranceGradeOption = (spec, option) => {
        applyToleranceGradeOption(spec, option);
        closeToleranceGradeMenu();
    };
    // 目的: 部品詳細画面のspec Base Unitを扱う。機能: 入力値を検証・整形し、画面または計算処理へ渡す。入力: 関数シグネチャの値。出力: 処理結果またはなし。動作条件: 部品詳細画面の初期化後に呼び出す。副作用: Vue状態、localStorage、DOM、HTTP通信のいずれかを更新する場合がある。
    const specBaseUnit = (spec) => isToleranceSpecRow(spec) ? '' : getSpecBaseUnit(specTypeForSpec(spec));
    // 目的: 部品詳細画面のhas Spec Base Unitを扱う。機能: 入力値を検証・整形し、画面または計算処理へ渡す。入力: 関数シグネチャの値。出力: 真偽値。動作条件: 部品詳細画面の初期化後に呼び出す。副作用: なし。
    const hasSpecBaseUnit = (spec) => !!specBaseUnit(spec);
    const syncNormalSpecUnitToBase = (spec, specType = specTypeForSpec(spec)) => {
        if (!spec || !specType || isToleranceSpecType(specType)) return spec;

        return normalizeSpecDraftUnitToBase(spec, specType);
    };
    const prepareSpecDraftForEdit = (spec, specType = specTypeForSpec(spec)) =>
        syncNormalSpecUnitToBase(applyToleranceDefaults(spec, specType), specType);

    // 目的: 部品詳細画面のspec Type Option Labelを扱う。機能: 入力値を検証・整形し、画面または計算処理へ渡す。入力: 関数シグネチャの値。出力: 処理結果またはなし。動作条件: 部品詳細画面の初期化後に呼び出す。副作用: Vue状態、localStorage、DOM、HTTP通信のいずれかを更新する場合がある。
    const specTypeOptionLabel = (specType) => {
        const primary = String(specType?.name_ja ?? specType?.name ?? '').trim();
        const symbol = String(specType?.symbol ?? '').trim();
        const english = String(specType?.name_en ?? '').trim();
        const suffix = [symbol, english].filter(Boolean).join(' / ');

        return suffix ? `${primary} (${suffix})` : primary;
    };
    // 目的: 部品詳細画面のspec Type Short Labelを扱う。機能: 入力値を検証・整形し、画面または計算処理へ渡す。入力: 関数シグネチャの値。出力: 処理結果またはなし。動作条件: 部品詳細画面の初期化後に呼び出す。副作用: Vue状態、localStorage、DOM、HTTP通信のいずれかを更新する場合がある。
    const specTypeShortLabel = (specType) => {
        const primary = String(specType?.name_ja ?? specType?.name ?? '').trim();
        const symbol = String(specType?.symbol ?? '').trim();

        return [primary, symbol].filter(Boolean).join(' ');
    };
    // 目的: 部品詳細画面のnormalize Nameを扱う。機能: 入力値を検証・整形し、画面または計算処理へ渡す。入力: 関数シグネチャの値。出力: 表示値、配列、オブジェクト、数値のいずれか。動作条件: 部品詳細画面の初期化後に呼び出す。副作用: なし。
    const normalizeName = (value) => String(value ?? '').toLowerCase().replace(/[\s()\[\]_.-]/gu, '');
    // 目的: 部品詳細画面のspec Type Search Textを扱う。機能: 入力値を検証・整形し、画面または計算処理へ渡す。入力: 関数シグネチャの値。出力: 処理結果またはなし。動作条件: 部品詳細画面の初期化後に呼び出す。副作用: Vue状態、localStorage、DOM、HTTP通信のいずれかを更新する場合がある。
    const specTypeSearchText = (item) => [
        item?.name,
        item?.name_ja,
        item?.name_en,
        item?.symbol,
        ...(item?.aliases ?? []).map((alias) => alias.alias),
    ].filter(Boolean).join(' ');
    // 目的: 部品詳細画面のmatch Spec Type By Nameを扱う。機能: 入力値を検証・整形し、画面または計算処理へ渡す。入力: 関数シグネチャの値。出力: 処理結果またはなし。動作条件: 部品詳細画面の初期化後に呼び出す。副作用: Vue状態、localStorage、DOM、HTTP通信のいずれかを更新する場合がある。
    const matchSpecTypeByName = (name) => {
        const normalized = normalizeName(name);
        if (!normalized) return null;

        let matched = specTypes.value.find((item) => normalizeName(specTypeSearchText(item)) === normalized);
        if (matched) return matched;

        matched = specTypes.value.find((item) => {
            const itemName = normalizeName(specTypeSearchText(item));
            return itemName && (normalized.includes(itemName) || itemName.includes(normalized));
        });

        return matched ?? null;
    };
    // 目的: 部品詳細画面のhandle Spec Type Selectionを扱う。機能: 入力値を検証・整形し、画面または計算処理へ渡す。入力: 関数シグネチャの値。出力: 処理結果またはなし。動作条件: 部品詳細画面の初期化後に呼び出す。副作用: Vue状態、localStorage、DOM、HTTP通信のいずれかを更新する場合がある。
    const handleSpecTypeSelection = (spec) => {
        const selected = getSpecTypeById(spec?.spec_type_id);
        if (selected) {
            spec.spec_type_name = selected.name_ja ?? selected.name ?? '';
            applyToleranceDefaults(spec, selected);
            syncNormalSpecUnitToBase(spec, selected);
        } else {
            spec.spec_type_name = '';
        }
    };
    // 目的: 部品詳細画面のbuild Spec Type Aliasesを扱う。機能: 入力値を検証・整形し、画面または計算処理へ渡す。入力: 関数シグネチャの値。出力: 表示値、配列、オブジェクト、数値のいずれか。動作条件: 部品詳細画面の初期化後に呼び出す。副作用: なし。
    const buildSpecTypeAliases = (aliasesText, extraAliases = [], excludedValues = []) => {
        const excluded = new Set(excludedValues.map((value) => normalizeName(value)).filter(Boolean));
        const seen = new Set;

        return [
            ...String(aliasesText ?? '').split(/\r?\n/u),
            ...extraAliases,
        ].map((value) => String(value ?? '').trim())
            .filter((value) => {
                const key = normalizeName(value);
                if (!key || excluded.has(key) || seen.has(key)) return false;
                seen.add(key);
                return true;
            })
            .map((alias) => ({ alias }));
    };
    // 目的: 部品詳細画面のsort Spec Typesを扱う。機能: 入力値を検証・整形し、画面または計算処理へ渡す。入力: 関数シグネチャの値。出力: 表示値、配列、オブジェクト、数値のいずれか。動作条件: 部品詳細画面の初期化後に呼び出す。副作用: なし。
    const sortSpecTypes = (items) => [...items].sort((a, b) => {
        const sortOrder = Number(a.sort_order ?? 0) - Number(b.sort_order ?? 0);
        return sortOrder || String(a.name_ja ?? a.name ?? '').localeCompare(String(b.name_ja ?? b.name ?? ''), 'ja');
    });
    // 目的: 部品詳細画面のnormalize Spec Group Idを扱う。機能: 入力値を検証・整形し、画面または計算処理へ渡す。入力: 関数シグネチャの値。出力: 表示値、配列、オブジェクト、数値のいずれか。動作条件: 部品詳細画面の初期化後に呼び出す。副作用: なし。
    const normalizeSpecGroupId = (value) => {
        if (value === '' || value === null || value === undefined) return '';
        return String(value);
    };
    // 目的: 部品詳細画面のgroup Spec Typesを扱う。機能: 入力値を検証・整形し、画面または計算処理へ渡す。入力: 関数シグネチャの値。出力: 処理結果またはなし。動作条件: 部品詳細画面の初期化後に呼び出す。副作用: Vue状態、localStorage、DOM、HTTP通信のいずれかを更新する場合がある。
    const groupSpecTypes = (group) => group?.spec_types ?? group?.specTypes ?? [];
    const specGroupOptions = computed(() => categories.value ?? []);
    // 目的: 部品詳細画面のgroup Templatesを扱う。機能: 入力値を検証・整形し、画面または計算処理へ渡す。入力: 関数シグネチャの値。出力: 処理結果またはなし。動作条件: 部品詳細画面の初期化後に呼び出す。副作用: Vue状態、localStorage、DOM、HTTP通信のいずれかを更新する場合がある。
    const groupTemplates = (group) => group?.templates ?? [];
    const allSpecTemplates = computed(() =>
        specGroupOptions.value.flatMap((group) =>
            groupTemplates(group).map((template) => ({
                ...template,
                spec_group_id: template.spec_group_id ?? group.id,
            }))
        )
    );
    // 目的: 部品詳細画面のmerge Spec Group Detailsを扱う。機能: 入力値を検証・整形し、画面または計算処理へ渡す。入力: 関数シグネチャの値。出力: 処理結果またはなし。動作条件: 部品詳細画面の初期化後に呼び出す。副作用: Vue状態、localStorage、DOM、HTTP通信のいずれかを更新する場合がある。
    const mergeSpecGroupDetails = (groups = []) => {
        if (!Array.isArray(groups) || !groups.length) return;
        const byId = new Map(categories.value.map((group) => [Number(group.id), group]));
        groups.forEach((group) => {
            byId.set(Number(group.id), {
                ...(byId.get(Number(group.id)) ?? {}),
                ...group,
                _detail_loaded: true,
            });
        });
        categories.value = [...byId.values()]
            .filter((group) => group?.id)
            .sort((a, b) => {
                const sortOrder = Number(a.sort_order ?? 0) - Number(b.sort_order ?? 0);
                return sortOrder || String(a.name ?? '').localeCompare(String(b.name ?? ''), 'ja');
            });
    };
    // 目的: 部品詳細画面のfetch Spec Group Catalogを扱う。機能: 入力値を検証・整形し、画面または計算処理へ渡す。入力: 関数シグネチャの値。出力: 処理結果またはなし。動作条件: 部品詳細画面の初期化後に呼び出す。副作用: Vue状態、localStorage、DOM、HTTP通信のいずれかを更新する場合がある。
    const fetchSpecGroupCatalog = async () => {
        try {
            const res = await api.get('/spec-groups?with_spec_types=1&with_templates=1');
            mergeSpecGroupDetails(res.data ?? []);
        } catch {
            // 基本の部品分類一覧は維持する。候補詳細は選択時に再取得する。
        }
    };
    // 目的: 部品詳細画面のensure Spec Group Detailを扱う。機能: 入力値を検証・整形し、画面または計算処理へ渡す。入力: 関数シグネチャの値。出力: 処理結果またはなし。動作条件: 部品詳細画面の初期化後に呼び出す。副作用: Vue状態、localStorage、DOM、HTTP通信のいずれかを更新する場合がある。
    const ensureSpecGroupDetail = async (groupId) => {
        if (!groupId || groupId === 'all') return null;
        const current = categories.value.find((group) => Number(group.id) === Number(groupId));
        if (current?._detail_loaded) return current;

        try {
            const res = await api.get(`/spec-groups/${groupId}`);
            mergeSpecGroupDetails([res.data]);
            return categories.value.find((group) => Number(group.id) === Number(groupId)) ?? res.data ?? null;
        } catch {
            toastError('部品分類の候補詳細を取得できませんでした');
            return null;
        }
    };
    // 目的: 部品詳細画面のfetch Spec Suggestions For Current Partを扱う。機能: 入力値を検証・整形し、画面または計算処理へ渡す。入力: 関数シグネチャの値。出力: 処理結果またはなし。動作条件: 部品詳細画面の初期化後に呼び出す。副作用: Vue状態、localStorage、DOM、HTTP通信のいずれかを更新する場合がある。
    const fetchSpecSuggestionsForCurrentPart = async () => {
        if (!part.value) return;
        const categoryIds = (part.value.categories ?? []).map((category) => Number(category.id)).filter(Boolean);
        if (!categoryIds.length) {
            specSuggestionTypes.value = [];
            selectedSpecGroupId.value = 'all';
            selectedSpecTypeId.value = '';
            selectedSpecTemplateId.value = '';
            specSuggestionLoading.value = false;
            return;
        }

        specSuggestionLoading.value = true;
        try {
            const params = new URLSearchParams();
            categoryIds.forEach((categoryId) => params.append('category_ids[]', categoryId));
            const res = await api.get(`/spec-suggestions?${params.toString()}`);
            specGroups.value = res.data?.groups ?? [];
            specSuggestionTypes.value = res.data?.spec_types ?? [];
            specTemplates.value = res.data?.templates ?? [];

            const currentId = normalizeSpecGroupId(selectedSpecGroupId.value);
            const currentStillAvailable = currentId === 'all'
                || specGroupOptions.value.some((group) => String(group.id) === currentId);
            if (!currentStillAvailable) {
                selectedSpecGroupId.value = 'all';
            }
            syncSpecPickerSelections();
        } catch {
            specSuggestionTypes.value = [];
            selectedSpecTypeId.value = '';
            selectedSpecTemplateId.value = '';
            toastError('部品分類候補の取得に失敗しました。必要なら全スペック詳細から選択してください');
        } finally {
            specSuggestionLoading.value = false;
        }
    };
    const selectedSpecGroup = computed(() => {
        const groupId = normalizeSpecGroupId(selectedSpecGroupId.value);
        if (!groupId || groupId === 'all') return null;
        return specGroupOptions.value.find((group) => String(group.id) === groupId) ?? null;
    });
    const isAllSpecTypesSelected = computed(() => normalizeSpecGroupId(selectedSpecGroupId.value) === 'all');
    const partSpecCategoryIds = computed(() =>
        (part.value?.categories ?? []).map((category) => Number(category.id)).filter(Boolean)
    );
    const hasPartSpecCategories = computed(() => partSpecCategoryIds.value.length > 0);
    const selectedSpecGroupLabel = computed(() => {
        if (isAllSpecTypesSelected.value) return 'フィルタしない';
        if (selectedSpecGroup.value) return selectedSpecGroup.value.name;
        return hasPartSpecCategories.value ? '候補スペック詳細なし' : '部品分類未選択';
    });
    const recommendedSpecTypeIds = computed(() => new Set(specSuggestionTypes.value.map((item) => Number(item.id))));
    const scopedSpecTypes = computed(() => {
        const group = selectedSpecGroup.value;
        if (group) return groupSpecTypes(group);
        if (isAllSpecTypesSelected.value) return specTypes.value;
        if (specSuggestionTypes.value.length) return specSuggestionTypes.value;
        return [];
    });
    // 目的: 部品詳細画面のresolve Inline Spec Owner Groupを扱う。機能: 入力値を検証・整形し、画面または計算処理へ渡す。入力: 関数シグネチャの値。出力: 表示値、配列、オブジェクト、数値のいずれか。動作条件: 部品詳細画面の初期化後に呼び出す。副作用: なし。
    const resolveInlineSpecOwnerGroup = () => {
        const selectedGroupId = normalizeSpecGroupId(selectedSpecGroupId.value);
        if (selectedGroupId && selectedGroupId !== 'all') {
            const group = specGroupOptions.value.find((item) => String(item.id) === selectedGroupId)
                ?? categories.value.find((item) => Number(item.id) === Number(selectedGroupId));
            if (group) {
                return { id: Number(group.id), name: group.name };
            }
        }

        const suggestedGroups = specGroups.value.filter((group) => group.is_suggested);
        if (suggestedGroups.length === 1) {
            const [group] = suggestedGroups;
            return { id: Number(group.id), name: group.name };
        }

        if (partSpecCategoryIds.value.length === 1) {
            const categoryId = partSpecCategoryIds.value[0];
            const category = categories.value.find((item) => Number(item.id) === Number(categoryId));
            return { id: categoryId, name: category?.name ?? '' };
        }

        return null;
    };
    // 目的: 部品詳細画面のattach Spec Type To Owner Groupを扱う。機能: 入力値を検証・整形し、画面または計算処理へ渡す。入力: 関数シグネチャの値。出力: 処理結果またはなし。動作条件: 部品詳細画面の初期化後に呼び出す。副作用: Vue状態、localStorage、DOM、HTTP通信のいずれかを更新する場合がある。
    const attachSpecTypeToOwnerGroup = (specType, ownerSpecGroupId) => {
        const group = specGroupOptions.value.find((item) => Number(item.id) === Number(ownerSpecGroupId));
        if (!group || !specType?.id) return;

        const currentTypes = groupSpecTypes(group);
        if (!currentTypes.some((item) => Number(item.id) === Number(specType.id))) {
            group.spec_types = sortSpecTypes([...currentTypes, specType]);
        }
        if (group.is_suggested && !specSuggestionTypes.value.some((item) => Number(item.id) === Number(specType.id))) {
            specSuggestionTypes.value = sortSpecTypes([...specSuggestionTypes.value, specType]);
        }
    };
    // 目的: 部品詳細画面のspec Group Candidate Payloadを扱う。機能: 入力値を検証・整形し、画面または計算処理へ渡す。入力: 関数シグネチャの値。出力: 処理結果またはなし。動作条件: 部品詳細画面の初期化後に呼び出す。副作用: Vue状態、localStorage、DOM、HTTP通信のいずれかを更新する場合がある。
    const specGroupCandidatePayload = (specType, index = 0) => ({
        spec_type_id: Number(specType.id),
        sort_order: Number(specType?.pivot?.sort_order ?? specType?.sort_order ?? ((index + 1) * 10)),
        is_required: Boolean(specType?.pivot?.is_required ?? false),
        is_recommended: Boolean(specType?.pivot?.is_recommended ?? true),
        default_profile: specType?.pivot?.default_profile ?? 'typ',
        default_unit: specType?.pivot?.default_unit ?? specType?.base_unit ?? specType?.units?.[0]?.unit ?? null,
        note: specType?.pivot?.note ?? null,
    });
    // 目的: 部品詳細画面のensure Spec Type Linked To Owner Groupを扱う。機能: 入力値を検証・整形し、画面または計算処理へ渡す。入力: 関数シグネチャの値。出力: 処理結果またはなし。動作条件: 部品詳細画面の初期化後に呼び出す。副作用: Vue状態、localStorage、DOM、HTTP通信のいずれかを更新する場合がある。
    const ensureSpecTypeLinkedToOwnerGroup = async (specType, ownerGroup) => {
        if (!specType?.id || !ownerGroup?.id) return false;

        const loadedGroup = await ensureSpecGroupDetail(ownerGroup.id);
        if (!loadedGroup) return false;
        const group = specGroupOptions.value.find((item) => Number(item.id) === Number(ownerGroup.id));
        const currentTypes = groupSpecTypes(group);
        if (currentTypes.some((item) => Number(item.id) === Number(specType.id))) {
            attachSpecTypeToOwnerGroup(specType, ownerGroup.id);
            return true;
        }

        const items = currentTypes.map((item, index) => specGroupCandidatePayload(item, index));
        const nextSortOrder = Math.max(0, ...items.map((item) => Number(item.sort_order ?? 0))) + 10;
        items.push({
            ...specGroupCandidatePayload(specType, items.length),
            sort_order: nextSortOrder,
            is_required: false,
            is_recommended: true,
        });

        try {
            const res = await api.put(`/spec-groups/${ownerGroup.id}/spec-types`, { items });
            mergeSpecGroupDetails([res.data]);
            attachSpecTypeToOwnerGroup(specType, ownerGroup.id);
            return true;
        } catch (e) {
            toastError(e.message || '既存スペック詳細を部品分類の候補に追加できませんでした');
            return false;
        }
    };
    // 目的: 部品詳細画面のspec Type Picker Option Labelを扱う。機能: 入力値を検証・整形し、画面または計算処理へ渡す。入力: 関数シグネチャの値。出力: 処理結果またはなし。動作条件: 部品詳細画面の初期化後に呼び出す。副作用: Vue状態、localStorage、DOM、HTTP通信のいずれかを更新する場合がある。
    const specTypePickerOptionLabel = (specType) => {
        const label = specTypeOptionLabel(specType);
        return isAllSpecTypesSelected.value && recommendedSpecTypeIds.value.has(Number(specType?.id))
            ? `${label}（推奨）`
            : label;
    };
    // 目的: 部品詳細画面のfiltered Spec Types For Pickerを扱う。機能: 入力値を検証・整形し、画面または計算処理へ渡す。入力: 関数シグネチャの値。出力: 処理結果またはなし。動作条件: 部品詳細画面の初期化後に呼び出す。副作用: Vue状態、localStorage、DOM、HTTP通信のいずれかを更新する場合がある。
    const filteredSpecTypesForPicker = (spec = null) => {
        const query = normalizeName(specTypeSearchQuery.value);
        const selected = getSpecTypeById(spec?.spec_type_id);
        const candidates = [...scopedSpecTypes.value];
        if (selected && !candidates.some((item) => Number(item.id) === Number(selected.id))) {
            candidates.unshift(selected);
        }

        const seen = new Set;
        return sortSpecTypes(candidates)
            .filter((item) => {
                const key = Number(item.id);
                if (seen.has(key)) return false;
                seen.add(key);
                if (!query) return true;
                return normalizeName(specTypeSearchText(item)).includes(query);
            })
            .sort((a, b) => {
                if (!isAllSpecTypesSelected.value) return 0;
                const aRecommended = recommendedSpecTypeIds.value.has(Number(a.id));
                const bRecommended = recommendedSpecTypeIds.value.has(Number(b.id));
                return Number(bRecommended) - Number(aRecommended);
            });
    };
    const visibleSpecTemplates = computed(() => {
        const groupId = normalizeSpecGroupId(selectedSpecGroupId.value);

        if (groupId && groupId !== 'all') {
            return groupTemplates(selectedSpecGroup.value);
        }

        return allSpecTemplates.value;
    });
    const selectedSpecTypeCandidate = computed(() =>
        filteredSpecTypesForPicker().find((item) => String(item.id) === String(selectedSpecTypeId.value)) ?? null
    );
    const selectedSpecTemplate = computed(() =>
        visibleSpecTemplates.value.find((template) => String(template.id) === String(selectedSpecTemplateId.value)) ?? null
    );
    // 目的: 部品詳細画面のmaster Spec Group Urlを扱う。機能: 入力値を検証・整形し、画面または計算処理へ渡す。入力: 関数シグネチャの値。出力: 処理結果またはなし。動作条件: 部品詳細画面の初期化後に呼び出す。副作用: Vue状態、localStorage、DOM、HTTP通信のいずれかを更新する場合がある。
    const masterSpecGroupUrl = (tab) => {
        const params = new URLSearchParams({ tab });
        const groupId = normalizeSpecGroupId(selectedSpecGroupId.value);
        const fallbackGroupId = defaultSpecGroupIdForPart();
        const targetGroupId = groupId && groupId !== 'all' ? groupId : fallbackGroupId;
        if (targetGroupId && targetGroupId !== 'all') {
            params.set('group_id', targetGroupId);
        }

        return `/master?${params.toString()}`;
    };
    // 目的: 部品詳細画面のspec Template Groupを扱う。機能: 入力値を検証・整形し、画面または計算処理へ渡す。入力: 関数シグネチャの値。出力: 処理結果またはなし。動作条件: 部品詳細画面の初期化後に呼び出す。副作用: Vue状態、localStorage、DOM、HTTP通信のいずれかを更新する場合がある。
    const specTemplateGroup = (template) =>
        specGroupOptions.value.find((group) => Number(group.id) === Number(template?.spec_group_id)) ?? null;
    // 目的: 部品詳細画面のspec Template Labelを扱う。機能: 入力値を検証・整形し、画面または計算処理へ渡す。入力: 関数シグネチャの値。出力: 処理結果またはなし。動作条件: 部品詳細画面の初期化後に呼び出す。副作用: Vue状態、localStorage、DOM、HTTP通信のいずれかを更新する場合がある。
    const specTemplateLabel = (template) => {
        const groupName = specTemplateGroup(template)?.name;
        return groupName ? `${template.name} / ${groupName}` : template.name;
    };
    // 目的: 部品詳細画面のtemplate Item Spec Typeを扱う。機能: 入力値を検証・整形し、画面または計算処理へ渡す。入力: 関数シグネチャの値。出力: 表示値、配列、オブジェクト、数値のいずれか。動作条件: 部品詳細画面の初期化後に呼び出す。副作用: なし。
    const templateItemSpecType = (item) => item?.spec_type ?? item?.specType ?? getSpecTypeById(item?.spec_type_id);
    // 目的: 部品詳細画面のtemplate Item Profileを扱う。機能: 入力値を検証・整形し、画面または計算処理へ渡す。入力: 関数シグネチャの値。出力: 表示値、配列、オブジェクト、数値のいずれか。動作条件: 部品詳細画面の初期化後に呼び出す。副作用: なし。
    const templateItemProfile = (item) => normalizeSpecProfile(item?.value_profile ?? item?.default_profile ?? 'typ');
    // 目的: 部品詳細画面のtemplate Item Unitを扱う。機能: 入力値を検証・整形し、画面または計算処理へ渡す。入力: 関数シグネチャの値。出力: 表示値、配列、オブジェクト、数値のいずれか。動作条件: 部品詳細画面の初期化後に呼び出す。副作用: なし。
    const templateItemUnit = (item, specType = null) => {
        const baseUnit = !isToleranceSpecType(specType) ? getSpecBaseUnit(specType) : '';

        return String(baseUnit || item?.unit || item?.default_unit || specType?.base_unit || specType?.units?.[0]?.unit || '').trim();
    };
    const specTemplatePreviewItems = computed(() =>
        (selectedSpecTemplate.value?.items ?? []).map((item) => {
            const specType = templateItemSpecType(item);
            return {
                id: item?.id ?? `${item?.spec_type_id ?? specType?.id ?? 'no-type'}-${templateItemProfile(item)}`,
                label: specTypeShortLabel(specType) || String(item?.name ?? item?.name_ja ?? 'スペック詳細未設定').trim(),
            };
        })
    );
    // 目的: 部品詳細画面のhas Spec Type Rowを扱う。機能: 入力値を検証・整形し、画面または計算処理へ渡す。入力: 関数シグネチャの値。出力: 真偽値。動作条件: 部品詳細画面の初期化後に呼び出す。副作用: なし。
    const hasSpecTypeRow = (specTypeId, rows = editModal.value.form?.specs ?? []) =>
        rows.some((spec) => Number(spec.spec_type_id) === Number(specTypeId));
    // 目的: 部品詳細画面のbuild Spec Row From Spec Typeを扱う。機能: 入力値を検証・整形し、画面または計算処理へ渡す。入力: 関数シグネチャの値。出力: 表示値、配列、オブジェクト、数値のいずれか。動作条件: 部品詳細画面の初期化後に呼び出す。副作用: なし。
    const buildSpecRowFromSpecType = (specType) => prepareSpecDraftForEdit({
        ...createEmptySpecRow(),
        spec_type_id: specType?.id ?? '',
        spec_type_name: specType?.name_ja ?? specType?.name ?? '',
        value_profile: normalizeSpecProfile('typ'),
        unit: String(isToleranceSpecType(specType)
            ? toleranceSettingsForSpecType(specType).default_unit
            : (specType?.base_unit ?? specType?.units?.[0]?.unit ?? '')).trim(),
    }, specType);
    // 目的: 部品詳細画面のbuild Spec Row From Template Itemを扱う。機能: 入力値を検証・整形し、画面または計算処理へ渡す。入力: 関数シグネチャの値。出力: 表示値、配列、オブジェクト、数値のいずれか。動作条件: 部品詳細画面の初期化後に呼び出す。副作用: なし。
    const buildSpecRowFromTemplateItem = (item) => {
        const specType = templateItemSpecType(item);

        return prepareSpecDraftForEdit({
            ...createEmptySpecRow(),
            spec_type_id: item?.spec_type_id ?? specType?.id ?? '',
            spec_type_name: specType?.name_ja ?? specType?.name ?? '',
            value_profile: templateItemProfile(item),
            unit: templateItemUnit(item, specType),
        }, specType);
    };
    // 目的: 部品詳細画面のsync Spec Picker Selectionsを扱う。機能: 入力値を検証・整形し、画面または計算処理へ渡す。入力: 関数シグネチャの値。出力: 処理結果またはなし。動作条件: 部品詳細画面の初期化後に呼び出す。副作用: Vue状態、localStorage、DOM、HTTP通信のいずれかを更新する場合がある。
    const syncSpecPickerSelections = () => {
        if (selectedSpecTypeId.value && !filteredSpecTypesForPicker().some((item) => String(item.id) === String(selectedSpecTypeId.value))) {
            selectedSpecTypeId.value = '';
        }
        if (selectedSpecTemplateId.value && !visibleSpecTemplates.value.some((item) => String(item.id) === String(selectedSpecTemplateId.value))) {
            selectedSpecTemplateId.value = '';
        }
    };
    // 目的: 部品詳細画面のhandle Spec Group Picker Changeを扱う。機能: 入力値を検証・整形し、画面または計算処理へ渡す。入力: 関数シグネチャの値。出力: 処理結果またはなし。動作条件: 部品詳細画面の初期化後に呼び出す。副作用: Vue状態、localStorage、DOM、HTTP通信のいずれかを更新する場合がある。
    const handleSpecGroupPickerChange = () => {
        specTypeSearchQuery.value = '';
        selectedSpecTypeId.value = '';
        selectedSpecTemplateId.value = '';
        void ensureSpecGroupDetail(selectedSpecGroupId.value);
        syncSpecPickerSelections();
    };
    // 目的: 部品詳細画面のadd Selected Spec Typeを扱う。機能: 入力値を検証・整形し、画面または計算処理へ渡す。入力: 関数シグネチャの値。出力: 処理結果またはなし。動作条件: 部品詳細画面の初期化後に呼び出す。副作用: Vue状態、localStorage、DOM、HTTP通信のいずれかを更新する場合がある。
    const addSelectedSpecType = () => {
        const specType = selectedSpecTypeCandidate.value;
        if (!specType?.id) {
            toastError('追加するスペック候補を選択してください');
            return;
        }

        if (hasSpecTypeRow(specType.id)) {
            toastError('既に同じスペック詳細の行があります');
            return;
        }

        editModal.value.form.specs.push(buildSpecRowFromSpecType(specType));
        selectedSpecTypeId.value = '';
    };
    // 目的: 部品詳細画面のadd Inline Created Spec Typeを扱う。機能: 入力値を検証・整形し、画面または計算処理へ渡す。入力: 関数シグネチャの値。出力: 処理結果またはなし。動作条件: 部品詳細画面の初期化後に呼び出す。副作用: Vue状態、localStorage、DOM、HTTP通信のいずれかを更新する場合がある。
    const addInlineCreatedSpecType = (specType) => {
        if (!specType?.id) return;

        const ownerGroup = resolveInlineSpecOwnerGroup();
        if (ownerGroup?.id) {
            selectedSpecGroupId.value = String(ownerGroup.id);
        }
        selectedSpecTypeId.value = String(specType.id);

        if (hasSpecTypeRow(specType.id)) {
            toastError('既に同じスペック詳細の行があります');
            return;
        }

        editModal.value.form.specs.push(buildSpecRowFromSpecType(specType));
        selectedSpecTypeId.value = '';
    };
    // 目的: 部品詳細画面のapply Selected Spec Templateを扱う。機能: 入力値を検証・整形し、画面または計算処理へ渡す。入力: 関数シグネチャの値。出力: 処理結果またはなし。動作条件: 部品詳細画面の初期化後に呼び出す。副作用: Vue状態、localStorage、DOM、HTTP通信のいずれかを更新する場合がある。
    const applySelectedSpecTemplate = () => {
        const template = selectedSpecTemplate.value;
        const rows = Array.isArray(template?.items) ? template.items : [];
        if (!template) {
            toastError('入力テンプレートを選択してください');
            return;
        }
        if (!rows.length) {
            toastError('この入力テンプレートにはスペック行がありません');
            return;
        }

        let added = 0;
        let skipped = 0;
        rows.forEach((item) => {
            const specTypeId = Number(item?.spec_type_id ?? templateItemSpecType(item)?.id ?? 0);
            if (!specTypeId || hasSpecTypeRow(specTypeId)) {
                skipped++;
                return;
            }

            editModal.value.form.specs.push(buildSpecRowFromTemplateItem(item));
            added++;
        });

        if (added > 0) {
            toastSuccess(skipped > 0
                ? `${template.name} を適用しました（追加 ${added} 件 / 既存 ${skipped} 件）`
                : `${template.name} を適用しました（追加 ${added} 件）`);
            return;
        }

        toastError('既に同じスペック詳細の行があります');
    };
    // 目的: 部品詳細画面のopen Inline Spec Type Modalを扱う。機能: 入力値を検証・整形し、画面または計算処理へ渡す。入力: 関数シグネチャの値。出力: 処理結果またはなし。動作条件: 部品詳細画面の初期化後に呼び出す。副作用: Vue状態、localStorage、DOM、HTTP通信のいずれかを更新する場合がある。
    const openInlineSpecTypeModal = (spec = null) => {
        if (!canCreateSpecType.value) return;
        if (!resolveInlineSpecOwnerGroup()) {
            toastError('スペック詳細を追加する部品分類を1つ選んでください');
            return;
        }

        const selected = getSpecTypeById(spec?.spec_type_id);
        const rawName = String(spec?.name ?? '').trim();
        const nameJa = String(spec?.name_ja ?? '').trim()
            || (selected ? '' : String(spec?.spec_type_name ?? '').trim())
            || rawName;
        const aliases = [rawName].filter((value) => value && normalizeName(value) !== normalizeName(nameJa));
        const unitDraft = normalizeBaseUnitInput(spec?.unit ?? '');
        const unit = unitDraft.unit;
        const suggestedPrefixes = unitDraft.prefix
            ? [...normalizeInlinePrefixes(spec?.suggest_prefixes ?? []), unitDraft.prefix]
            : (spec?.suggest_prefixes ?? []);
        const displayPrefixes = unitDraft.prefix
            ? [...normalizeInlinePrefixes(spec?.display_prefixes ?? []), unitDraft.prefix]
            : (spec?.display_prefixes ?? []);

        inlineSpecTypeModal.targetSpec = spec;
        inlineSpecTypeModal.form = {
            name_ja: nameJa,
            name_en: String(spec?.name_en ?? '').trim(),
            symbol: String(spec?.symbol ?? '').trim(),
            description: String(spec?.description ?? '').trim(),
            value_type: spec?.value_type ?? 'numeric',
            unit,
            suggest_prefixes: sanitizeInlinePrefixesForUnit(suggestedPrefixes, unit),
            display_prefixes: sanitizeInlinePrefixesForUnit(displayPrefixes, unit),
            aliases_text: aliases.join('\n'),
        };
        inlineSpecTypeModal.open = true;
    };
    // 目的: 部品詳細画面のclose Inline Spec Type Modalを扱う。機能: 入力値を検証・整形し、画面または計算処理へ渡す。入力: 関数シグネチャの値。出力: 処理結果またはなし。動作条件: 部品詳細画面の初期化後に呼び出す。副作用: Vue状態、localStorage、DOM、HTTP通信のいずれかを更新する場合がある。
    const closeInlineSpecTypeModal = (force = false) => {
        if (inlineSpecTypeModal.saving && force !== true) return;
        inlineSpecTypeModal.open = false;
        inlineSpecTypeModal.targetSpec = null;
        inlineSpecTypeModal.form = createInlineSpecTypeForm();
    };
    // 目的: 部品詳細画面のfetch Spec Typesを扱う。機能: 入力値を検証・整形し、画面または計算処理へ渡す。入力: 関数シグネチャの値。出力: 処理結果またはなし。動作条件: 部品詳細画面の初期化後に呼び出す。副作用: Vue状態、localStorage、DOM、HTTP通信のいずれかを更新する場合がある。
    const fetchSpecTypes = async () => {
        try {
            const res = await api.get('/spec-types');
            specTypes.value = res.data ?? [];
        } catch {
            // 保存処理側で必要なエラーを出す。
        }
    };
    // 目的: 部品詳細画面のcreate Spec Type From Draftを扱う。機能: 入力値を検証・整形し、画面または計算処理へ渡す。入力: 関数シグネチャの値。出力: 表示値、配列、オブジェクト、数値のいずれか。動作条件: 部品詳細画面の初期化後に呼び出す。副作用: なし。
    const createSpecTypeFromDraft = async (spec) => {
        const nameJa = String(spec?.name_ja ?? spec?.spec_type_name ?? spec?.name ?? '').trim();
        const name = nameJa || String(spec?.name ?? '').trim();
        if (!name) return null;

        const ownerGroup = resolveInlineSpecOwnerGroup();
        if (!ownerGroup) {
            toastError('スペック詳細を追加する部品分類を1つ選んでください');
            return null;
        }

        const existing = matchSpecTypeByName(name);
        if (existing) {
            const linked = await ensureSpecTypeLinkedToOwnerGroup(existing, ownerGroup);
            if (!linked) return null;
            toastSuccess(`既存のスペック詳細を候補に追加しました: ${name}`);
            return existing;
        }

        try {
            const unitDraft = normalizeBaseUnitInput(spec?.unit ?? '');
            const unit = unitDraft.unit;
            const suggestPrefixes = sanitizeInlinePrefixesForUnit(
                unitDraft.prefix ? [...normalizeInlinePrefixes(spec?.suggest_prefixes ?? []), unitDraft.prefix] : (spec?.suggest_prefixes ?? []),
                unit
            );
            const displayPrefixes = sanitizeInlinePrefixesForUnit(
                unitDraft.prefix ? [...normalizeInlinePrefixes(spec?.display_prefixes ?? []), unitDraft.prefix] : (spec?.display_prefixes ?? []),
                unit
            );
            const res = await api.post('/spec-types', {
                name,
                name_ja: nameJa || name,
                name_en: String(spec?.name_en ?? '').trim(),
                symbol: String(spec?.symbol ?? '').trim(),
                description: String(spec?.description ?? '').trim() || null,
                value_type: spec?.value_type ?? 'numeric',
                unit,
                suggest_prefixes: suggestPrefixes.length > 0 ? suggestPrefixes : null,
                display_prefixes: displayPrefixes.length > 0 ? displayPrefixes : null,
                spec_scope: 'group_local',
                owner_spec_group_id: ownerGroup.id,
                aliases: buildSpecTypeAliases(
                    spec?.aliases_text ?? '',
                    [spec?.name],
                    [name, nameJa, spec?.name_en, spec?.symbol]
                ),
                sort_order: (specTypes.value.at(-1)?.sort_order ?? 0) + 10,
            });
            specTypes.value = sortSpecTypes([...specTypes.value, res.data]);
            attachSpecTypeToOwnerGroup(res.data, ownerGroup.id);
            toastSuccess(`スペック詳細を追加しました: ${name}`);
            return res.data;
        } catch (e) {
            await fetchSpecTypes();
            const matchedAfterReload = matchSpecTypeByName(name);
            if (matchedAfterReload) return matchedAfterReload;
            toastError(e.message);
            return null;
        }
    };
    // 目的: 部品詳細画面のsave Inline Spec Typeを扱う。機能: 入力値を検証・整形し、画面または計算処理へ渡す。入力: 関数シグネチャの値。出力: 処理結果またはなし。動作条件: 部品詳細画面の初期化後に呼び出す。副作用: Vue状態、localStorage、DOM、HTTP通信のいずれかを更新する場合がある。
    const saveInlineSpecType = async () => {
        if (inlineSpecTypeModal.saving) return;

        const nameJa = String(inlineSpecTypeModal.form.name_ja ?? '').trim();
        if (!nameJa) {
            toastError('スペック詳細の日本語名を入力してください');
            return;
        }

        inlineSpecTypeModal.saving = true;
        try {
            const created = await createSpecTypeFromDraft(inlineSpecTypeModal.form);
            if (!created) return;

            const targetSpec = inlineSpecTypeModal.targetSpec;
            if (targetSpec) {
                targetSpec.spec_type_id = created.id;
                targetSpec.spec_type_name = created.name_ja ?? created.name ?? '';
            } else {
                addInlineCreatedSpecType(created);
            }
            closeInlineSpecTypeModal(true);
        } finally {
            inlineSpecTypeModal.saving = false;
        }
    };
    // 目的: 部品詳細画面のresolve Spec Types Before Saveを扱う。機能: 入力値を検証・整形し、画面または計算処理へ渡す。入力: 関数シグネチャの値。出力: 表示値、配列、オブジェクト、数値のいずれか。動作条件: 部品詳細画面の初期化後に呼び出す。副作用: なし。
    const resolveSpecTypesBeforeSave = async () => {
        for (const spec of editModal.value.form?.specs ?? []) {
            handleSpecTypeSelection(spec);
        }
    };
    // 目的: 部品詳細画面のvalidate Specs Before Saveを扱う。機能: 入力値を検証・整形し、画面または計算処理へ渡す。入力: 関数シグネチャの値。出力: 処理結果またはなし。動作条件: 部品詳細画面の初期化後に呼び出す。副作用: Vue状態、localStorage、DOM、HTTP通信のいずれかを更新する場合がある。
    const validateSpecsBeforeSave = () => {
        const missingRows = (editModal.value.form?.specs ?? [])
            .map((spec, index) => ({ spec, index }))
            .filter(({ spec }) => !spec.spec_type_id);
        if (!missingRows.length) return true;

        const labels = missingRows
            .slice(0, 4)
            .map(({ spec, index }) => `${index + 1}行目${spec.spec_type_name || spec.name_ja || spec.name ? `「${spec.spec_type_name || spec.name_ja || spec.name}」` : ''}`);
        const suffix = missingRows.length > labels.length ? ` ほか${missingRows.length - labels.length}件` : '';
        toastError(`スペック詳細が未選択です: ${labels.join('、')}${suffix}`);
        return false;
    };
    // 目的: 部品詳細画面のchange Spec Profileを扱う。機能: 入力値を検証・整形し、画面または計算処理へ渡す。入力: 関数シグネチャの値。出力: 処理結果またはなし。動作条件: 部品詳細画面の初期化後に呼び出す。副作用: Vue状態、localStorage、DOM、HTTP通信のいずれかを更新する場合がある。
    const changeSpecProfile = (spec, profile) => {
        const previous = normalizeSpecProfile(spec?.value_profile);
        const next = normalizeSpecProfile(profile);
        const typ = String(spec.value_typ ?? '').trim();
        const min = String(spec.value_min ?? '').trim();
        const max = String(spec.value_max ?? '').trim();
        const fallback = typ || max || min;

        if (next === 'typ' && !typ) {
            spec.value_typ = fallback;
        } else if (next === 'max_only' && !max) {
            spec.value_max = previous === 'typ' && typ ? typ : fallback;
        } else if (next === 'min_only' && !min) {
            spec.value_min = previous === 'typ' && typ ? typ : fallback;
        } else if ((next === 'range' || next === 'triple') && !typ && previous === 'max_only' && max) {
            spec.value_typ = max;
        } else if (next === 'triple' && !typ && previous === 'min_only' && min) {
            spec.value_typ = min;
        }

        spec.value_profile = next;
    };
    // 目的: 部品詳細画面のget Unit Suggestionsを扱う。機能: 入力値を検証・整形し、画面または計算処理へ渡す。入力: 関数シグネチャの値。出力: 表示値、配列、オブジェクト、数値のいずれか。動作条件: 部品詳細画面の初期化後に呼び出す。副作用: なし。
    const getUnitSuggestions = (specTypeId) => getSpecUnitSuggestions(getSpecTypeById(specTypeId));
    // 目的: 部品詳細画面のspec Previewを扱う。機能: 入力値を検証・整形し、画面または計算処理へ渡す。入力: 関数シグネチャの値。出力: 処理結果またはなし。動作条件: 部品詳細画面の初期化後に呼び出す。副作用: Vue状態、localStorage、DOM、HTTP通信のいずれかを更新する場合がある。
    const specPreview = (spec) => normalizeSpecDraft(spec, getSpecTypeById(spec.spec_type_id));
    // 目的: 部品詳細画面のspec Display Nameを扱う。機能: 入力値を検証・整形し、画面または計算処理へ渡す。入力: 関数シグネチャの値。出力: 処理結果またはなし。動作条件: 部品詳細画面の初期化後に呼び出す。副作用: Vue状態、localStorage、DOM、HTTP通信のいずれかを更新する場合がある。
    const specDisplayName = (spec) => getSpecDisplayName(spec, getSpecTypeById(spec?.spec_type_id));
    // 目的: 部品詳細画面のspec Profile Badgeを扱う。機能: 入力値を検証・整形し、画面または計算処理へ渡す。入力: 関数シグネチャの値。出力: 処理結果またはなし。動作条件: 部品詳細画面の初期化後に呼び出す。副作用: Vue状態、localStorage、DOM、HTTP通信のいずれかを更新する場合がある。
    const specProfileBadge = (spec) => getSpecProfileBadgeLabel(spec?.value_profile);
    // 目的: 部品詳細画面のspec Profile Control Labelを扱う。機能: 入力値を検証・整形し、画面または計算処理へ渡す。入力: 関数シグネチャの値。出力: 処理結果またはなし。動作条件: 部品詳細画面の初期化後に呼び出す。副作用: Vue状態、localStorage、DOM、HTTP通信のいずれかを更新する場合がある。
    const specProfileControlLabel = (profile) => getSpecProfileControlLabel(profile);
    // 目的: 部品詳細画面のspec Profile Help Textを扱う。機能: 入力値を検証・整形し、画面または計算処理へ渡す。入力: 関数シグネチャの値。出力: 処理結果またはなし。動作条件: 部品詳細画面の初期化後に呼び出す。副作用: Vue状態、localStorage、DOM、HTTP通信のいずれかを更新する場合がある。
    const specProfileHelpText = (profile) => getSpecProfileHelpText(profile);


    return {
        specProfileOptions, inlineSpecTypeModal, inlinePrefixOptionsFor, inlinePrefixPolicyHelp, syncInlinePrefixList,
        createEmptySpecRow, mergeSpecGroupDetails, fetchSpecGroupCatalog, ensureSpecGroupDetail,
        fetchSpecSuggestionsForCurrentPart, prepareSpecDraftForEdit, handleSpecTypeSelection,
        resolveSpecTypesBeforeSave, validateSpecsBeforeSave, specGroupOptions, selectedSpecGroupLabel,
        scopedSpecTypes, specTypePickerOptionLabel, filteredSpecTypesForPicker, visibleSpecTemplates,
        selectedSpecTemplate, masterSpecGroupUrl, specTemplateLabel, specTemplatePreviewItems,
        addSelectedSpecType, applySelectedSpecTemplate, openInlineSpecTypeModal, closeInlineSpecTypeModal,
        saveInlineSpecType, changeSpecProfile, getUnitSuggestions, hasSpecBaseUnit, specPreview,
        specDisplayName, specProfileBadge, specProfileControlLabel, specProfileHelpText, specTypeOptionLabel,
        isToleranceSpecRow, toleranceUnitOptionsFor, toleranceValuePlaceholder, toleranceGradeOptionsFor,
        toleranceGradeOptionLabel, isToleranceGradeMenuOpen, toggleToleranceGradeMenu, closeToleranceGradeMenu,
        selectToleranceGradeOption, handleSpecGroupPickerChange,
    };
}
