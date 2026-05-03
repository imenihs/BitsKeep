import { computed, reactive, ref } from 'vue';
import { api } from '../../api.js';
import {
    createEmptySpecRow,
    getSpecBaseUnit,
    getSpecDisplayName,
    getSpecProfileBadgeLabel,
    getSpecProfileControlLabel,
    getSpecProfileHelpText,
    getSpecUnitSuggestions,
    normalizeBaseUnitInput,
    normalizeSpecDraft,
    normalizeSpecDraftUnitToBase,
    normalizeSpecProfile,
    SPEC_PROFILE_OPTIONS,
} from '../../utils/specValue.js';
import {
    isByteBitUnit,
    normalizePrefixList,
    prefixOptionsForUnit,
    prefixPolicyHelpForUnit,
    sanitizePrefixesForUnit as sanitizeEngineeringPrefixesForUnit,
    syncPrefixSelectionForUnit,
} from '../../utils/engineeringUnits.js';

/**
 * 部品登録画面のスペック候補、入力テンプレート、インラインスペック詳細追加を構成する。
 * 入力は親setupのリアクティブ状態と通知関数で、戻り値はBladeから使う操作関数群。
 * 動作条件は spec-groups/spec-types API が利用できることで、候補取得や保存時にAPI更新とトースト表示の副作用を持つ。
 */
// 目的: 部品登録画面のComponent Create Specsを扱う。機能: 入力値を検証・整形し、画面または計算処理へ渡す。入力: 関数シグネチャの値。出力: 処理結果またはなし。動作条件: 部品登録画面の初期化後に呼び出す。副作用: Vue状態、localStorage、DOM、HTTP通信のいずれかを更新する場合がある。
export function useComponentCreateSpecs(ctx) {
    const {
        form, categories, specTypes, specGroups, specSuggestionTypes, specTemplates,
        selectedSpecGroupId, selectedSpecCandidateId, selectedSpecTemplateId,
        specTypeSearchQuery, specSuggestionLoading, helperSpecGroups,
        toastSuccess, toastError, canCreateSpecType,
    } = ctx;
    // スペック操作
    // 目的: 部品登録画面のremove Specを扱う。機能: 入力値を検証・整形し、画面または計算処理へ渡す。入力: 関数シグネチャの値。出力: 処理結果またはなし。動作条件: 部品登録画面の初期化後に呼び出す。副作用: Vue状態、localStorage、DOM、HTTP通信のいずれかを更新する場合がある。
    const removeSpec = (i) => form.specs.splice(i, 1);
    // 目的: 部品登録画面のget Unit Suggestionsを扱う。機能: 入力値を検証・整形し、画面または計算処理へ渡す。入力: 関数シグネチャの値。出力: 表示値、配列、オブジェクト、数値のいずれか。動作条件: 部品登録画面の初期化後に呼び出す。副作用: なし。
    const getUnitSuggestions = (specTypeId) => getSpecUnitSuggestions(specTypes.value.find(st => st.id == specTypeId));
    // 目的: 部品登録画面のspec Display Nameを扱う。機能: 入力値を検証・整形し、画面または計算処理へ渡す。入力: 関数シグネチャの値。出力: 処理結果またはなし。動作条件: 部品登録画面の初期化後に呼び出す。副作用: Vue状態、localStorage、DOM、HTTP通信のいずれかを更新する場合がある。
    const specDisplayName = (spec) => getSpecDisplayName(spec, findSpecTypeById(spec?.spec_type_id));
    // 目的: 部品登録画面のspec Profile Badgeを扱う。機能: 入力値を検証・整形し、画面または計算処理へ渡す。入力: 関数シグネチャの値。出力: 処理結果またはなし。動作条件: 部品登録画面の初期化後に呼び出す。副作用: Vue状態、localStorage、DOM、HTTP通信のいずれかを更新する場合がある。
    const specProfileBadge = (spec) => getSpecProfileBadgeLabel(spec?.value_profile);
    // 目的: 部品登録画面のspec Profile Control Labelを扱う。機能: 入力値を検証・整形し、画面または計算処理へ渡す。入力: 関数シグネチャの値。出力: 処理結果またはなし。動作条件: 部品登録画面の初期化後に呼び出す。副作用: Vue状態、localStorage、DOM、HTTP通信のいずれかを更新する場合がある。
    const specProfileControlLabel = (profile) => getSpecProfileControlLabel(profile);
    // 目的: 部品登録画面のspec Profile Help Textを扱う。機能: 入力値を検証・整形し、画面または計算処理へ渡す。入力: 関数シグネチャの値。出力: 処理結果またはなし。動作条件: 部品登録画面の初期化後に呼び出す。副作用: Vue状態、localStorage、DOM、HTTP通信のいずれかを更新する場合がある。
    const specProfileHelpText = (profile) => getSpecProfileHelpText(profile);
    // 目的: 部品登録画面のspec Type Option Labelを扱う。機能: 入力値を検証・整形し、画面または計算処理へ渡す。入力: 関数シグネチャの値。出力: 処理結果またはなし。動作条件: 部品登録画面の初期化後に呼び出す。副作用: Vue状態、localStorage、DOM、HTTP通信のいずれかを更新する場合がある。
    const specTypeOptionLabel = (specType) => {
        const primary = String(specType?.name_ja ?? specType?.name ?? '').trim();
        const symbol = String(specType?.symbol ?? '').trim();
        const english = String(specType?.name_en ?? '').trim();
        const suffix = [symbol, english].filter(Boolean).join(' / ');

        return suffix ? `${primary} (${suffix})` : primary;
    };
    // 目的: 部品登録画面のspec Type Short Labelを扱う。機能: 入力値を検証・整形し、画面または計算処理へ渡す。入力: 関数シグネチャの値。出力: 処理結果またはなし。動作条件: 部品登録画面の初期化後に呼び出す。副作用: Vue状態、localStorage、DOM、HTTP通信のいずれかを更新する場合がある。
    const specTypeShortLabel = (specType) => {
        const primary = String(specType?.name_ja ?? specType?.name ?? '').trim();
        const symbol = String(specType?.symbol ?? '').trim();

        return [primary, symbol].filter(Boolean).join(' ');
    };
    // 目的: 部品登録画面のspec Identity Keyを扱う。機能: 入力値を検証・整形し、画面または計算処理へ渡す。入力: 関数シグネチャの値。出力: 処理結果またはなし。動作条件: 部品登録画面の初期化後に呼び出す。副作用: Vue状態、localStorage、DOM、HTTP通信のいずれかを更新する場合がある。
    const specIdentityKey = (spec) => {
        const typeId = Number(spec?.spec_type_id ?? 0);
        const sourceKey = [
            spec?.name_ja,
            spec?.symbol,
            spec?.name_en,
            spec?.name,
        ].map((value) => String(value ?? '').trim().toLowerCase()).find(Boolean);

        return `${typeId || sourceKey || 'no-type'}:${normalizeSpecProfile(spec?.value_profile)}`;
    };
    // 目的: 部品登録画面のtemplate Item Spec Typeを扱う。機能: 入力値を検証・整形し、画面または計算処理へ渡す。入力: 関数シグネチャの値。出力: 表示値、配列、オブジェクト、数値のいずれか。動作条件: 部品登録画面の初期化後に呼び出す。副作用: なし。
    const templateItemSpecType = (item) => item?.spec_type ?? item?.specType ?? findSpecTypeById(item?.spec_type_id);
    // 目的: 部品登録画面のtemplate Item Profileを扱う。機能: 入力値を検証・整形し、画面または計算処理へ渡す。入力: 関数シグネチャの値。出力: 表示値、配列、オブジェクト、数値のいずれか。動作条件: 部品登録画面の初期化後に呼び出す。副作用: なし。
    const templateItemProfile = (item) => normalizeSpecProfile(item?.value_profile ?? item?.default_profile ?? 'typ');
    // 目的: 部品登録画面のtemplate Item Unitを扱う。機能: 入力値を検証・整形し、画面または計算処理へ渡す。入力: 関数シグネチャの値。出力: 表示値、配列、オブジェクト、数値のいずれか。動作条件: 部品登録画面の初期化後に呼び出す。副作用: なし。
    const templateItemUnit = (item, specType = null) => {
        const baseUnit = !isToleranceSpecType(specType) ? getSpecBaseUnit(specType) : '';

        return String(baseUnit || item?.unit || item?.default_unit || specType?.base_unit || specType?.units?.[0]?.unit || '').trim();
    };
    // 目的: 部品登録画面のspec Template Groupを扱う。機能: 入力値を検証・整形し、画面または計算処理へ渡す。入力: 関数シグネチャの値。出力: 処理結果またはなし。動作条件: 部品登録画面の初期化後に呼び出す。副作用: Vue状態、localStorage、DOM、HTTP通信のいずれかを更新する場合がある。
    const specTemplateGroup = (template) =>
        specGroupOptions.value.find((group) => Number(group.id) === Number(template?.spec_group_id))
            ?? specGroups.value.find((group) => Number(group.id) === Number(template?.spec_group_id))
            ?? helperSpecGroups.value.find((group) => Number(group.id) === Number(template?.spec_group_id))
            ?? null;
    // 目的: 部品登録画面のspec Template Labelを扱う。機能: 入力値を検証・整形し、画面または計算処理へ渡す。入力: 関数シグネチャの値。出力: 処理結果またはなし。動作条件: 部品登録画面の初期化後に呼び出す。副作用: Vue状態、localStorage、DOM、HTTP通信のいずれかを更新する場合がある。
    const specTemplateLabel = (template) => {
        const groupName = specTemplateGroup(template)?.name;
        return groupName ? `${template.name} / ${groupName}` : template.name;
    };
    // 目的: 部品登録画面のhas Spec Type Rowを扱う。機能: 入力値を検証・整形し、画面または計算処理へ渡す。入力: 関数シグネチャの値。出力: 真偽値。動作条件: 部品登録画面の初期化後に呼び出す。副作用: なし。
    const hasSpecTypeRow = (specTypeId, rows = form.specs) =>
        rows.some((spec) => Number(spec.spec_type_id) === Number(specTypeId));
    // 目的: 部品登録画面のis Tolerance Spec Typeを扱う。機能: 入力値を検証・整形し、画面または計算処理へ渡す。入力: 関数シグネチャの値。出力: 真偽値。動作条件: 部品登録画面の初期化後に呼び出す。副作用: なし。
    const isToleranceSpecType = (specType) => (specType?.spec_kind ?? 'normal') === 'tolerance';
    // 目的: 部品登録画面のdefault Tolerance Settingsを扱う。機能: 入力値を検証・整形し、画面または計算処理へ渡す。入力: 関数シグネチャの値。出力: 表示値、配列、オブジェクト、数値のいずれか。動作条件: 部品登録画面の初期化後に呼び出す。副作用: なし。
    const defaultToleranceSettings = (fallbackUnit = '%') => ({
        default_mode: 'symmetric',
        default_unit: fallbackUnit || '%',
        allowed_units: [fallbackUnit || '%'],
        grade_options: [],
    });
    // 目的: 部品登録画面のnormalize Tolerance Settingsを扱う。機能: 入力値を検証・整形し、画面または計算処理へ渡す。入力: 関数シグネチャの値。出力: 表示値、配列、オブジェクト、数値のいずれか。動作条件: 部品登録画面の初期化後に呼び出す。副作用: なし。
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
    // 目的: 部品登録画面のtolerance Settings For Spec Typeを扱う。機能: 入力値を検証・整形し、画面または計算処理へ渡す。入力: 関数シグネチャの値。出力: 処理結果またはなし。動作条件: 部品登録画面の初期化後に呼び出す。副作用: なし。
    const toleranceSettingsForSpecType = (specType) =>
        normalizeToleranceSettings(specType?.tolerance_settings, specType?.base_unit ?? specType?.units?.[0]?.unit ?? '%');
    // 目的: 部品登録画面のspec Type For Specを扱う。機能: 入力値を検証・整形し、画面または計算処理へ渡す。入力: 関数シグネチャの値。出力: 処理結果またはなし。動作条件: 部品登録画面の初期化後に呼び出す。副作用: Vue状態、localStorage、DOM、HTTP通信のいずれかを更新する場合がある。
    const specTypeForSpec = (spec) => findSpecTypeById(spec?.spec_type_id);
    // 目的: 部品登録画面のis Tolerance Spec Rowを扱う。機能: 入力値を検証・整形し、画面または計算処理へ渡す。入力: 関数シグネチャの値。出力: 真偽値。動作条件: 部品登録画面の初期化後に呼び出す。副作用: なし。
    const isToleranceSpecRow = (spec) => isToleranceSpecType(specTypeForSpec(spec));
    // 目的: 部品登録画面のtolerance Settings For Specを扱う。機能: 入力値を検証・整形し、画面または計算処理へ渡す。入力: 関数シグネチャの値。出力: 処理結果またはなし。動作条件: 部品登録画面の初期化後に呼び出す。副作用: なし。
    const toleranceSettingsForSpec = (spec) => toleranceSettingsForSpecType(specTypeForSpec(spec));
    // 目的: 部品登録画面のtolerance Unit Options Forを扱う。機能: 入力値を検証・整形し、画面または計算処理へ渡す。入力: 関数シグネチャの値。出力: 処理結果またはなし。動作条件: 部品登録画面の初期化後に呼び出す。副作用: なし。
    const toleranceUnitOptionsFor = (spec) => toleranceSettingsForSpec(spec).allowed_units;
    // 目的: 部品登録画面のtolerance Value Placeholderを扱う。機能: 入力値を検証・整形し、画面または計算処理へ渡す。入力: 関数シグネチャの値。出力: 処理結果またはなし。動作条件: 部品登録画面の初期化後に呼び出す。副作用: なし。
    const toleranceValuePlaceholder = (spec) => {
        const mode = toleranceSettingsForSpec(spec).default_mode;
        if (mode === 'grade') return '例: J / 5 / +80/-20';
        if (mode === 'asymmetric') return '例: +80/-20';

        return '例: 5 / ±5';
    };
    // 目的: 部品登録画面のtolerance Grade Options Forを扱う。機能: 入力値を検証・整形し、画面または計算処理へ渡す。入力: 関数シグネチャの値。出力: 処理結果またはなし。動作条件: 部品登録画面の初期化後に呼び出す。副作用: なし。
    const toleranceGradeOptionsFor = (spec) => toleranceSettingsForSpec(spec).grade_options;
    // 目的: 部品登録画面のtolerance Grade Option Labelを扱う。機能: 入力値を検証・整形し、画面または計算処理へ渡す。入力: 関数シグネチャの値。出力: 処理結果またはなし。動作条件: 部品登録画面の初期化後に呼び出す。副作用: なし。
    const toleranceGradeOptionLabel = (option) => {
        const label = String(option?.label ?? option?.rank ?? '').trim();
        const unit = String(option?.unit ?? '').trim();
        if (option?.plus !== undefined || option?.minus !== undefined) {
            return `${label} +${option?.plus ?? ''}/-${option?.minus ?? ''}${unit}`;
        }
        if (option?.value !== undefined) return `${label} ±${option.value}${unit}`;
        return label;
    };
    // 目的: 部品登録画面のtolerance Grade Option Valueを扱う。機能: 入力値を検証・整形し、画面または計算処理へ渡す。入力: 関数シグネチャの値。出力: 処理結果またはなし。動作条件: 部品登録画面の初期化後に呼び出す。副作用: なし。
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
    // 目的: 部品登録画面のapply Tolerance Grade Optionを扱う。機能: 入力値を検証・整形し、画面または計算処理へ渡す。入力: 関数シグネチャの値。出力: 処理結果またはなし。動作条件: 部品登録画面の初期化後に呼び出す。副作用: Vue状態、localStorage、DOM、HTTP通信のいずれかを更新する場合がある。
    const applyToleranceGradeOption = (spec, option) => {
        spec.value_profile = 'typ';
        spec.value_typ = toleranceGradeOptionValue(option);
        spec.unit = String(option?.unit ?? '').trim() || toleranceSettingsForSpec(spec).default_unit || spec.unit || '%';
    };
    const activeToleranceGradeMenu = ref('');
    // 目的: 部品登録画面のtolerance Grade Menu Keyを扱う。機能: 入力値を検証・整形し、画面または計算処理へ渡す。入力: 関数シグネチャの値。出力: 処理結果またはなし。動作条件: 部品登録画面の初期化後に呼び出す。副作用: なし。
    const toleranceGradeMenuKey = (scope, index) => `${scope}-${index}`;
    // 目的: 部品登録画面のis Tolerance Grade Menu Openを扱う。機能: 入力値を検証・整形し、画面または計算処理へ渡す。入力: 関数シグネチャの値。出力: 真偽値。動作条件: 部品登録画面の初期化後に呼び出す。副作用: なし。
    const isToleranceGradeMenuOpen = (scope, index) => activeToleranceGradeMenu.value === toleranceGradeMenuKey(scope, index);
    // 目的: 部品登録画面のclose Tolerance Grade Menuを扱う。機能: 入力値を検証・整形し、画面または計算処理へ渡す。入力: 関数シグネチャの値。出力: 処理結果またはなし。動作条件: 部品登録画面の初期化後に呼び出す。副作用: Vue状態、localStorage、DOM、HTTP通信のいずれかを更新する場合がある。
    const closeToleranceGradeMenu = () => {
        activeToleranceGradeMenu.value = '';
    };
    // 目的: 部品登録画面のtoggle Tolerance Grade Menuを扱う。機能: 入力値を検証・整形し、画面または計算処理へ渡す。入力: 関数シグネチャの値。出力: 処理結果またはなし。動作条件: 部品登録画面の初期化後に呼び出す。副作用: なし。
    const toggleToleranceGradeMenu = (scope, index, spec) => {
        if (!toleranceGradeOptionsFor(spec).length) {
            closeToleranceGradeMenu();
            return;
        }

        const key = toleranceGradeMenuKey(scope, index);
        activeToleranceGradeMenu.value = activeToleranceGradeMenu.value === key ? '' : key;
    };
    // 目的: 部品登録画面のselect Tolerance Grade Optionを扱う。機能: 入力値を検証・整形し、画面または計算処理へ渡す。入力: 関数シグネチャの値。出力: 処理結果またはなし。動作条件: 部品登録画面の初期化後に呼び出す。副作用: Vue状態、localStorage、DOM、HTTP通信のいずれかを更新する場合がある。
    const selectToleranceGradeOption = (spec, option) => {
        applyToleranceGradeOption(spec, option);
        closeToleranceGradeMenu();
    };
    // 目的: 部品登録画面のspec Base Unitを扱う。機能: 入力値を検証・整形し、画面または計算処理へ渡す。入力: 関数シグネチャの値。出力: 処理結果またはなし。動作条件: 部品登録画面の初期化後に呼び出す。副作用: Vue状態、localStorage、DOM、HTTP通信のいずれかを更新する場合がある。
    const specBaseUnit = (spec) => isToleranceSpecRow(spec) ? '' : getSpecBaseUnit(specTypeForSpec(spec));
    // 目的: 部品登録画面のhas Spec Base Unitを扱う。機能: 入力値を検証・整形し、画面または計算処理へ渡す。入力: 関数シグネチャの値。出力: 真偽値。動作条件: 部品登録画面の初期化後に呼び出す。副作用: なし。
    const hasSpecBaseUnit = (spec) => !!specBaseUnit(spec);
    const syncNormalSpecUnitToBase = (spec, specType = specTypeForSpec(spec)) => {
        if (!spec || !specType || isToleranceSpecType(specType)) return spec;

        return normalizeSpecDraftUnitToBase(spec, specType);
    };
    const prepareSpecDraftForEdit = (spec, specType = specTypeForSpec(spec)) =>
        syncNormalSpecUnitToBase(applyToleranceDefaults(spec, specType), specType);
    // 目的: 部品登録画面のbuild Spec Row From Template Itemを扱う。機能: 入力値を検証・整形し、画面または計算処理へ渡す。入力: 関数シグネチャの値。出力: 表示値、配列、オブジェクト、数値のいずれか。動作条件: 部品登録画面の初期化後に呼び出す。副作用: なし。
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
    // 目的: 部品登録画面のhandle Spec Type Selectionを扱う。機能: 入力値を検証・整形し、画面または計算処理へ渡す。入力: 関数シグネチャの値。出力: 処理結果またはなし。動作条件: 部品登録画面の初期化後に呼び出す。副作用: Vue状態、localStorage、DOM、HTTP通信のいずれかを更新する場合がある。
    const handleSpecTypeSelection = (spec) => {
        const selected = findSpecTypeById(spec?.spec_type_id);
        if (selected) {
            spec.spec_type_name = selected.name_ja ?? selected.name ?? '';
            applyToleranceDefaults(spec, selected);
            syncNormalSpecUnitToBase(spec, selected);
        } else {
            spec.spec_type_name = '';
        }
    };
    // 目的: 部品登録画面のbuild Spec Type Aliasesを扱う。機能: 入力値を検証・整形し、画面または計算処理へ渡す。入力: 関数シグネチャの値。出力: 表示値、配列、オブジェクト、数値のいずれか。動作条件: 部品登録画面の初期化後に呼び出す。副作用: なし。
    const buildSpecTypeAliases = (aliasesText, extraAliases = [], excludedValues = []) => {
        const excluded = new Set(excludedValues.map((value) => normalizeHelperText(value)).filter(Boolean));
        const seen = new Set;

        return [
            ...String(aliasesText ?? '').split(/\r?\n/u),
            ...extraAliases,
        ].map((value) => String(value ?? '').trim())
            .filter((value) => {
                const key = normalizeHelperText(value);
                if (!key || excluded.has(key) || seen.has(key)) return false;
                seen.add(key);
                return true;
            })
            .map((alias) => ({ alias }));
    };
    // 目的: 部品登録画面のsort Spec Typesを扱う。機能: 入力値を検証・整形し、画面または計算処理へ渡す。入力: 関数シグネチャの値。出力: 表示値、配列、オブジェクト、数値のいずれか。動作条件: 部品登録画面の初期化後に呼び出す。副作用: なし。
    const sortSpecTypes = (items) => [...items].sort((a, b) => {
        const sortOrder = Number(a.sort_order ?? 0) - Number(b.sort_order ?? 0);
        return sortOrder || String(a.name_ja ?? a.name ?? '').localeCompare(String(b.name_ja ?? b.name ?? ''), 'ja');
    });
    // 目的: 部品登録画面のopen Inline Spec Type Modalを扱う。機能: 入力値を検証・整形し、画面または計算処理へ渡す。入力: 関数シグネチャの値。出力: 処理結果またはなし。動作条件: 部品登録画面の初期化後に呼び出す。副作用: Vue状態、localStorage、DOM、HTTP通信のいずれかを更新する場合がある。
    const openInlineSpecTypeModal = (spec = null) => {
        if (!canCreateSpecType.value) return;
        if (!resolveInlineSpecOwnerGroup()) {
            toastError('スペック詳細を追加する部品分類を1つ選んでください');
            return;
        }

        const selected = findSpecTypeById(spec?.spec_type_id);
        const rawName = String(spec?.name ?? '').trim();
        const nameJa = String(spec?.name_ja ?? '').trim()
            || (selected ? '' : String(spec?.spec_type_name ?? '').trim())
            || rawName;
        const aliases = [rawName].filter((value) => value && normalizeHelperText(value) !== normalizeHelperText(nameJa));
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
    // 目的: 部品登録画面のclose Inline Spec Type Modalを扱う。機能: 入力値を検証・整形し、画面または計算処理へ渡す。入力: 関数シグネチャの値。出力: 処理結果またはなし。動作条件: 部品登録画面の初期化後に呼び出す。副作用: Vue状態、localStorage、DOM、HTTP通信のいずれかを更新する場合がある。
    const closeInlineSpecTypeModal = (force = false) => {
        if (inlineSpecTypeModal.saving && force !== true) return;
        inlineSpecTypeModal.open = false;
        inlineSpecTypeModal.targetSpec = null;
        inlineSpecTypeModal.form = createInlineSpecTypeForm();
    };
    // 目的: 部品登録画面のchange Spec Profileを扱う。機能: 入力値を検証・整形し、画面または計算処理へ渡す。入力: 関数シグネチャの値。出力: 処理結果またはなし。動作条件: 部品登録画面の初期化後に呼び出す。副作用: Vue状態、localStorage、DOM、HTTP通信のいずれかを更新する場合がある。
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

    // 目的: 部品登録画面のnormalize Helper Textを扱う。機能: 入力値を検証・整形し、画面または計算処理へ渡す。入力: 関数シグネチャの値。出力: 表示値、配列、オブジェクト、数値のいずれか。動作条件: 部品登録画面の初期化後に呼び出す。副作用: なし。
    const normalizeHelperText = (value) =>
        String(value ?? '')
            .toLowerCase()
            .replace(/[\s()\[\]_.-]/gu, '');

    // 目的: 部品登録画面のmatch By Nameを扱う。機能: 入力値を検証・整形し、画面または計算処理へ渡す。入力: 関数シグネチャの値。出力: 処理結果またはなし。動作条件: 部品登録画面の初期化後に呼び出す。副作用: Vue状態、localStorage、DOM、HTTP通信のいずれかを更新する場合がある。
    const matchByName = (name, items, resolver = (item) => item.name) => {
        const normalized = normalizeHelperText(name);
        if (!normalized) return null;

        let matched = items.find((item) => normalizeHelperText(resolver(item)) === normalized);
        if (matched) return matched;

        matched = items.find((item) => {
            const itemName = normalizeHelperText(resolver(item));
            return itemName && (normalized.includes(itemName) || itemName.includes(normalized));
        });

        return matched ?? null;
    };

    // 目的: 部品登録画面のspec Type Search Textを扱う。機能: 入力値を検証・整形し、画面または計算処理へ渡す。入力: 関数シグネチャの値。出力: 処理結果またはなし。動作条件: 部品登録画面の初期化後に呼び出す。副作用: Vue状態、localStorage、DOM、HTTP通信のいずれかを更新する場合がある。
    const specTypeSearchText = (item) => [
        item?.name,
        item?.name_ja,
        item?.name_en,
        item?.symbol,
        ...(item?.aliases ?? []).map((alias) => alias.alias),
    ].filter(Boolean).join(' ');

    // 目的: 部品登録画面のfetch Spec Types For Inline Createを扱う。機能: 入力値を検証・整形し、画面または計算処理へ渡す。入力: 関数シグネチャの値。出力: 処理結果またはなし。動作条件: 部品登録画面の初期化後に呼び出す。副作用: Vue状態、localStorage、DOM、HTTP通信のいずれかを更新する場合がある。
    const fetchSpecTypesForInlineCreate = async () => {
        try {
            const res = await api.get('/spec-types');
            specTypes.value = res.data ?? [];
        } catch {
            // 保存時の本筋を邪魔しない。作成失敗時は呼び出し元でエラーを表示する。
        }
    };

    // 目的: 部品登録画面のfind Category By Idを扱う。機能: 入力値を検証・整形し、画面または計算処理へ渡す。入力: 関数シグネチャの値。出力: 表示値、配列、オブジェクト、数値のいずれか。動作条件: 部品登録画面の初期化後に呼び出す。副作用: なし。
    const findCategoryById = (categoryId) =>
        categories.value.find((item) => Number(item.id) === Number(categoryId)) ?? null;

    // 目的: 部品登録画面のfind Package By Idを扱う。機能: 入力値を検証・整形し、画面または計算処理へ渡す。入力: 関数シグネチャの値。出力: 表示値、配列、オブジェクト、数値のいずれか。動作条件: 部品登録画面の初期化後に呼び出す。副作用: なし。
    const findPackageById = (packageId) =>
        packages.value.find((item) => Number(item.id) === Number(packageId)) ?? null;

    // 目的: 部品登録画面のfind Spec Type By Idを扱う。機能: 入力値を検証・整形し、画面または計算処理へ渡す。入力: 関数シグネチャの値。出力: 表示値、配列、オブジェクト、数値のいずれか。動作条件: 部品登録画面の初期化後に呼び出す。副作用: なし。
    const findSpecTypeById = (specTypeId) =>
        specTypes.value.find((item) => Number(item.id) === Number(specTypeId)) ?? null;

    // 目的: 部品登録画面のspec Previewを扱う。機能: 入力値を検証・整形し、画面または計算処理へ渡す。入力: 関数シグネチャの値。出力: 処理結果またはなし。動作条件: 部品登録画面の初期化後に呼び出す。副作用: Vue状態、localStorage、DOM、HTTP通信のいずれかを更新する場合がある。
    const specPreview = (spec) => normalizeSpecDraft(spec, findSpecTypeById(spec.spec_type_id));
    let specSuggestionRequestSeq = 0;
    // 目的: 部品登録画面のnormalize Spec Group Idを扱う。機能: 入力値を検証・整形し、画面または計算処理へ渡す。入力: 関数シグネチャの値。出力: 表示値、配列、オブジェクト、数値のいずれか。動作条件: 部品登録画面の初期化後に呼び出す。副作用: なし。
    const normalizeSpecGroupId = (value) => {
        if (value === '' || value === null || value === undefined) return '';
        return String(value);
    };
    // 目的: 部品登録画面のgroup Spec Typesを扱う。機能: 入力値を検証・整形し、画面または計算処理へ渡す。入力: 関数シグネチャの値。出力: 処理結果またはなし。動作条件: 部品登録画面の初期化後に呼び出す。副作用: Vue状態、localStorage、DOM、HTTP通信のいずれかを更新する場合がある。
    const groupSpecTypes = (group) => group?.spec_types ?? group?.specTypes ?? [];
    const specGroupOptions = computed(() => categories.value ?? []);
    // 目的: 部品登録画面のgroup Templatesを扱う。機能: 入力値を検証・整形し、画面または計算処理へ渡す。入力: 関数シグネチャの値。出力: 処理結果またはなし。動作条件: 部品登録画面の初期化後に呼び出す。副作用: Vue状態、localStorage、DOM、HTTP通信のいずれかを更新する場合がある。
    const groupTemplates = (group) => group?.templates ?? [];
    const allSpecTemplates = computed(() =>
        specGroupOptions.value.flatMap((group) =>
            groupTemplates(group).map((template) => ({
                ...template,
                spec_group_id: template.spec_group_id ?? group.id,
            }))
        )
    );
    // 目的: 部品登録画面のfetch Spec Suggestions For Formを扱う。機能: 入力値を検証・整形し、画面または計算処理へ渡す。入力: 関数シグネチャの値。出力: 処理結果またはなし。動作条件: 部品登録画面の初期化後に呼び出す。副作用: Vue状態、localStorage、DOM、HTTP通信のいずれかを更新する場合がある。
    const fetchSpecSuggestionsForForm = async () => {
        const categoryIds = form.category_ids.map((id) => Number(id)).filter(Boolean);
        const seq = ++specSuggestionRequestSeq;

        if (!categoryIds.length) {
            specSuggestionTypes.value = [];
            selectedSpecGroupId.value = 'all';
            selectedSpecCandidateId.value = '';
            selectedSpecTemplateId.value = '';
            specSuggestionLoading.value = false;
            return;
        }

        specSuggestionLoading.value = true;
        try {
            const params = new URLSearchParams();
            categoryIds.forEach((categoryId) => params.append('category_ids[]', categoryId));
            const res = await api.get(`/spec-suggestions?${params.toString()}`);
            if (seq !== specSuggestionRequestSeq) return;

            specGroups.value = res.data?.groups ?? [];
            specSuggestionTypes.value = res.data?.spec_types ?? [];
            specTemplates.value = res.data?.templates ?? [];

            const currentId = normalizeSpecGroupId(selectedSpecGroupId.value);
            const currentStillAvailable = currentId === ''
                || currentId === 'all'
                || specGroupOptions.value.some((group) => String(group.id) === currentId);
            if (!currentStillAvailable) {
                selectedSpecGroupId.value = 'all';
            }
        } catch {
            if (seq !== specSuggestionRequestSeq) return;
            specGroups.value = [];
            specSuggestionTypes.value = [];
            specTemplates.value = [];
            toastError('部品分類候補の取得に失敗しました。必要なら全スペック詳細から選択してください');
        } finally {
            if (seq === specSuggestionRequestSeq) {
                specSuggestionLoading.value = false;
            }
        }
    };
    const selectedSpecGroup = computed(() => {
        const groupId = normalizeSpecGroupId(selectedSpecGroupId.value);
        if (!groupId || groupId === 'all') return null;
        return specGroupOptions.value.find((group) => String(group.id) === groupId) ?? null;
    });
    const isAllSpecTypesSelected = computed(() => normalizeSpecGroupId(selectedSpecGroupId.value) === 'all');
    const selectedSpecCategoryIds = computed(() => form.category_ids.map((id) => Number(id)).filter(Boolean));
    const hasSelectedSpecCategories = computed(() => selectedSpecCategoryIds.value.length > 0);
    const selectedSpecGroupLabel = computed(() => {
        if (isAllSpecTypesSelected.value) return 'フィルタしない';
        if (selectedSpecGroup.value) return selectedSpecGroup.value.name;
        if (specSuggestionTypes.value.length) return '推奨候補';
        return hasSelectedSpecCategories.value ? '候補スペック詳細なし' : '部品分類未選択';
    });
    const recommendedSpecTypeIds = computed(() => new Set(specSuggestionTypes.value.map((item) => Number(item.id))));
    const scopedSpecTypes = computed(() => {
        const group = selectedSpecGroup.value;
        if (group) return groupSpecTypes(group);
        if (isAllSpecTypesSelected.value) return specTypes.value;
        if (specSuggestionTypes.value.length) return specSuggestionTypes.value;
        return [];
    });
    // 目的: 部品登録画面のresolve Inline Spec Owner Groupを扱う。機能: 入力値を検証・整形し、画面または計算処理へ渡す。入力: 関数シグネチャの値。出力: 表示値、配列、オブジェクト、数値のいずれか。動作条件: 部品登録画面の初期化後に呼び出す。副作用: なし。
    const resolveInlineSpecOwnerGroup = () => {
        const selectedGroupId = normalizeSpecGroupId(selectedSpecGroupId.value);
        if (selectedGroupId && selectedGroupId !== 'all') {
            const group = specGroupOptions.value.find((item) => String(item.id) === selectedGroupId)
                ?? findCategoryById(selectedGroupId);
            if (group) {
                return { id: Number(group.id), name: group.name };
            }
        }

        const suggestedGroups = specGroups.value.filter((group) => group.is_suggested);
        if (suggestedGroups.length === 1) {
            const [group] = suggestedGroups;
            return { id: Number(group.id), name: group.name };
        }

        if (selectedSpecCategoryIds.value.length === 1) {
            const categoryId = selectedSpecCategoryIds.value[0];
            const category = findCategoryById(categoryId);
            return { id: categoryId, name: category?.name ?? '' };
        }

        return null;
    };
    // 目的: 部品登録画面のattach Spec Type To Owner Groupを扱う。機能: 入力値を検証・整形し、画面または計算処理へ渡す。入力: 関数シグネチャの値。出力: 処理結果またはなし。動作条件: 部品登録画面の初期化後に呼び出す。副作用: Vue状態、localStorage、DOM、HTTP通信のいずれかを更新する場合がある。
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
    // 目的: 部品登録画面のspec Group Candidate Payloadを扱う。機能: 入力値を検証・整形し、画面または計算処理へ渡す。入力: 関数シグネチャの値。出力: 処理結果またはなし。動作条件: 部品登録画面の初期化後に呼び出す。副作用: Vue状態、localStorage、DOM、HTTP通信のいずれかを更新する場合がある。
    const specGroupCandidatePayload = (specType, index = 0) => ({
        spec_type_id: Number(specType.id),
        sort_order: Number(specType?.pivot?.sort_order ?? specType?.sort_order ?? ((index + 1) * 10)),
        is_required: Boolean(specType?.pivot?.is_required ?? false),
        is_recommended: Boolean(specType?.pivot?.is_recommended ?? true),
        default_profile: specType?.pivot?.default_profile ?? 'typ',
        default_unit: specType?.pivot?.default_unit ?? specType?.base_unit ?? specType?.units?.[0]?.unit ?? null,
        note: specType?.pivot?.note ?? null,
    });
    // 目的: 部品登録画面のensure Spec Type Linked To Owner Groupを扱う。機能: 入力値を検証・整形し、画面または計算処理へ渡す。入力: 関数シグネチャの値。出力: 処理結果またはなし。動作条件: 部品登録画面の初期化後に呼び出す。副作用: Vue状態、localStorage、DOM、HTTP通信のいずれかを更新する場合がある。
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
    // 目的: 部品登録画面のspec Type Picker Option Labelを扱う。機能: 入力値を検証・整形し、画面または計算処理へ渡す。入力: 関数シグネチャの値。出力: 処理結果またはなし。動作条件: 部品登録画面の初期化後に呼び出す。副作用: Vue状態、localStorage、DOM、HTTP通信のいずれかを更新する場合がある。
    const specTypePickerOptionLabel = (specType) => {
        const label = specTypeOptionLabel(specType);
        return isAllSpecTypesSelected.value && recommendedSpecTypeIds.value.has(Number(specType?.id))
            ? `${label}（推奨）`
            : label;
    };
    // 目的: 部品登録画面のfiltered Spec Types For Pickerを扱う。機能: 入力値を検証・整形し、画面または計算処理へ渡す。入力: 関数シグネチャの値。出力: 処理結果またはなし。動作条件: 部品登録画面の初期化後に呼び出す。副作用: Vue状態、localStorage、DOM、HTTP通信のいずれかを更新する場合がある。
    const filteredSpecTypesForPicker = (spec = null) => {
        const query = normalizeHelperText(specTypeSearchQuery.value);
        const selected = findSpecTypeById(spec?.spec_type_id);
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
                return normalizeHelperText(specTypeSearchText(item)).includes(query);
            })
            .sort((a, b) => {
                if (!isAllSpecTypesSelected.value) return 0;
                const aRecommended = recommendedSpecTypeIds.value.has(Number(a.id));
                const bRecommended = recommendedSpecTypeIds.value.has(Number(b.id));
                return Number(bRecommended) - Number(aRecommended);
            });
    };
    // 目的: 部品登録画面のshow Recommended Spec Typesを扱う。機能: 入力値を検証・整形し、画面または計算処理へ渡す。入力: 関数シグネチャの値。出力: 処理結果またはなし。動作条件: 部品登録画面の初期化後に呼び出す。副作用: Vue状態、localStorage、DOM、HTTP通信のいずれかを更新する場合がある。
    const showRecommendedSpecTypes = () => {
        selectedSpecGroupId.value = '';
        specTypeSearchQuery.value = '';
    };
    // 目的: 部品登録画面のshow All Spec Typesを扱う。機能: 入力値を検証・整形し、画面または計算処理へ渡す。入力: 関数シグネチャの値。出力: 処理結果またはなし。動作条件: 部品登録画面の初期化後に呼び出す。副作用: Vue状態、localStorage、DOM、HTTP通信のいずれかを更新する場合がある。
    const showAllSpecTypes = () => {
        selectedSpecGroupId.value = 'all';
        specTypeSearchQuery.value = '';
    };
    const recommendedSpecGroupIdSet = computed(() =>
        new Set(specGroups.value.filter((group) => group.is_suggested).map((group) => Number(group.id)))
    );
    const visibleSpecTemplates = computed(() => {
        const groupId = normalizeSpecGroupId(selectedSpecGroupId.value);

        if (groupId && groupId !== 'all') {
            return groupTemplates(selectedSpecGroup.value);
        }

        return allSpecTemplates.value;
    });
    const selectedSpecCandidate = computed(() =>
        filteredSpecTypesForPicker().find((item) => Number(item.id) === Number(selectedSpecCandidateId.value)) ?? null
    );
    const selectedSpecTemplate = computed(() =>
        visibleSpecTemplates.value.find((template) => Number(template.id) === Number(selectedSpecTemplateId.value)) ?? null
    );
    const selectedSpecTemplateItems = computed(() => (
        Array.isArray(selectedSpecTemplate.value?.items) ? selectedSpecTemplate.value.items : []
    ));
    // 目的: 部品登録画面のtemplate Item Preview Labelを扱う。機能: 入力値を検証・整形し、画面または計算処理へ渡す。入力: 関数シグネチャの値。出力: 表示値、配列、オブジェクト、数値のいずれか。動作条件: 部品登録画面の初期化後に呼び出す。副作用: なし。
    const templateItemPreviewLabel = (item) => {
        const specType = templateItemSpecType(item);
        return specTypeShortLabel(specType) || item?.spec_type_name || 'スペック';
    };
    // 目的: 部品登録画面のhandle Spec Group Picker Changeを扱う。機能: 入力値を検証・整形し、画面または計算処理へ渡す。入力: 関数シグネチャの値。出力: 処理結果またはなし。動作条件: 部品登録画面の初期化後に呼び出す。副作用: Vue状態、localStorage、DOM、HTTP通信のいずれかを更新する場合がある。
    const handleSpecGroupPickerChange = () => {
        specTypeSearchQuery.value = '';
        selectedSpecCandidateId.value = '';
        selectedSpecTemplateId.value = '';
        void ensureSpecGroupDetail(selectedSpecGroupId.value);
    };
    // 目的: 部品登録画面のadd Selected Spec Candidateを扱う。機能: 入力値を検証・整形し、画面または計算処理へ渡す。入力: 関数シグネチャの値。出力: 処理結果またはなし。動作条件: 部品登録画面の初期化後に呼び出す。副作用: Vue状態、localStorage、DOM、HTTP通信のいずれかを更新する場合がある。
    const addSelectedSpecCandidate = () => {
        const specType = selectedSpecCandidate.value;
        if (!specType?.id) {
            toastError('スペック候補を選択してください');
            return;
        }
        if (hasSpecTypeRow(specType.id)) {
            toastError('既に同じスペック詳細の行があります');
            return;
        }

        form.specs.push(buildSpecRowFromSpecType(specType));
        selectedSpecCandidateId.value = '';
    };
    // 目的: 部品登録画面のapply Spec Templateを扱う。機能: 入力値を検証・整形し、画面または計算処理へ渡す。入力: 関数シグネチャの値。出力: 処理結果またはなし。動作条件: 部品登録画面の初期化後に呼び出す。副作用: Vue状態、localStorage、DOM、HTTP通信のいずれかを更新する場合がある。
    const applySpecTemplate = (template) => {
        const rows = Array.isArray(template?.items) ? template.items : [];
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

            form.specs.push(buildSpecRowFromTemplateItem(item));
            added++;
        });

        if (template?.spec_group_id) {
            selectedSpecGroupId.value = String(template.spec_group_id);
        }

        if (added > 0) {
            toastSuccess(skipped > 0
                ? `${template.name} を適用しました（追加 ${added} 件 / 既存 ${skipped} 件）`
                : `${template.name} を適用しました（追加 ${added} 件）`);
            return;
        }

        toastError('既に同じスペック詳細の行があります');
    };
    // 目的: 部品登録画面のapply Selected Spec Templateを扱う。機能: 入力値を検証・整形し、画面または計算処理へ渡す。入力: 関数シグネチャの値。出力: 処理結果またはなし。動作条件: 部品登録画面の初期化後に呼び出す。副作用: Vue状態、localStorage、DOM、HTTP通信のいずれかを更新する場合がある。
    const applySelectedSpecTemplate = () => {
        if (!selectedSpecTemplate.value) {
            toastError('入力テンプレートを選択してください');
            return;
        }

        applySpecTemplate(selectedSpecTemplate.value);
    };


    // 目的: 部品登録画面のcreate Spec Type From Draftを扱う。機能: 入力値を検証・整形し、画面または計算処理へ渡す。入力: 関数シグネチャの値。出力: 表示値、配列、オブジェクト、数値のいずれか。動作条件: 部品登録画面の初期化後に呼び出す。副作用: なし。
    const createSpecTypeFromDraft = async (spec) => {
        const nameJa = String(spec?.name_ja ?? spec?.spec_type_name ?? spec?.name ?? '').trim();
        const name = nameJa || String(spec?.name ?? '').trim();
        if (!name) return null;

        const ownerGroup = resolveInlineSpecOwnerGroup();
        if (!ownerGroup) {
            toastError('スペック詳細を追加する部品分類を1つ選んでください');
            return null;
        }
        const existing = matchByName(name, specTypes.value, specTypeSearchText);
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
            await fetchSpecTypesForInlineCreate();
            const matchedAfterReload = matchByName(name, specTypes.value, specTypeSearchText);
            if (matchedAfterReload) return matchedAfterReload;
            toastError(e.message);
            return null;
        }
    };

    // 目的: 部品登録画面のbuild Spec Row From Spec Typeを扱う。機能: 入力値を検証・整形し、画面または計算処理へ渡す。入力: 関数シグネチャの値。出力: 表示値、配列、オブジェクト、数値のいずれか。動作条件: 部品登録画面の初期化後に呼び出す。副作用: なし。
    const buildSpecRowFromSpecType = (specType) => prepareSpecDraftForEdit({
        ...createEmptySpecRow(),
        spec_type_id: specType?.id ?? '',
        spec_type_name: specType?.name_ja ?? specType?.name ?? '',
        unit: isToleranceSpecType(specType)
            ? toleranceSettingsForSpecType(specType).default_unit
            : (specType?.base_unit ?? specType?.units?.[0]?.unit ?? ''),
    }, specType);

    // 目的: 部品登録画面のadd Inline Created Spec Typeを扱う。機能: 入力値を検証・整形し、画面または計算処理へ渡す。入力: 関数シグネチャの値。出力: 処理結果またはなし。動作条件: 部品登録画面の初期化後に呼び出す。副作用: Vue状態、localStorage、DOM、HTTP通信のいずれかを更新する場合がある。
    const addInlineCreatedSpecType = (specType) => {
        if (!specType?.id) return;

        const ownerGroup = resolveInlineSpecOwnerGroup();
        if (ownerGroup?.id) {
            selectedSpecGroupId.value = String(ownerGroup.id);
        }
        selectedSpecCandidateId.value = String(specType.id);

        if (hasSpecTypeRow(specType.id)) {
            toastError('既に同じスペック詳細の行があります');
            return;
        }

        form.specs.push(buildSpecRowFromSpecType(specType));
        selectedSpecCandidateId.value = '';
    };

    // 目的: 部品登録画面のsave Inline Spec Typeを扱う。機能: 入力値を検証・整形し、画面または計算処理へ渡す。入力: 関数シグネチャの値。出力: 処理結果またはなし。動作条件: 部品登録画面の初期化後に呼び出す。副作用: Vue状態、localStorage、DOM、HTTP通信のいずれかを更新する場合がある。
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
                targetSpec.matched = true;
            } else {
                addInlineCreatedSpecType(created);
            }
            closeInlineSpecTypeModal(true);
        } finally {
            inlineSpecTypeModal.saving = false;
        }
    };



    return {
        specProfileOptions,
        canCreateSpecType,
        inlineSpecTypeModal,
        isInlineByteBitPrefixUnit,
        normalizeInlinePrefixes,
        sanitizeInlinePrefixesForUnit,
        inlinePrefixOptionsFor,
        inlinePrefixPolicyHelp,
        syncInlinePrefixList,
        removeSpec,
        getUnitSuggestions,
        specDisplayName,
        specProfileBadge,
        specProfileControlLabel,
        specProfileHelpText,
        specTypeOptionLabel,
        specTypeShortLabel,
        specIdentityKey,
        templateItemSpecType,
        templateItemProfile,
        templateItemUnit,
        specTemplateGroup,
        specTemplateLabel,
        hasSpecTypeRow,
        isToleranceSpecType,
        toleranceSettingsForSpecType,
        specTypeForSpec,
        isToleranceSpecRow,
        toleranceUnitOptionsFor,
        toleranceValuePlaceholder,
        toleranceGradeOptionsFor,
        toleranceGradeOptionLabel,
        applyToleranceDefaults,
        activeToleranceGradeMenu,
        isToleranceGradeMenuOpen,
        closeToleranceGradeMenu,
        toggleToleranceGradeMenu,
        selectToleranceGradeOption,
        hasSpecBaseUnit,
        syncNormalSpecUnitToBase,
        prepareSpecDraftForEdit,
        buildSpecRowFromTemplateItem,
        handleSpecTypeSelection,
        buildSpecTypeAliases,
        sortSpecTypes,
        openInlineSpecTypeModal,
        closeInlineSpecTypeModal,
        changeSpecProfile,
        normalizeHelperText,
        matchByName,
        specTypeSearchText,
        fetchSpecTypesForInlineCreate,
        findCategoryById,
        findPackageById,
        findSpecTypeById,
        specPreview,
        fetchSpecSuggestionsForForm,
        selectedSpecGroup,
        isAllSpecTypesSelected,
        selectedSpecCategoryIds,
        hasSelectedSpecCategories,
        selectedSpecGroupLabel,
        recommendedSpecTypeIds,
        scopedSpecTypes,
        resolveInlineSpecOwnerGroup,
        attachSpecTypeToOwnerGroup,
        specTypePickerOptionLabel,
        filteredSpecTypesForPicker,
        showRecommendedSpecTypes,
        showAllSpecTypes,
        recommendedSpecGroupIdSet,
        visibleSpecTemplates,
        selectedSpecCandidate,
        selectedSpecTemplate,
        selectedSpecTemplateItems,
        templateItemPreviewLabel,
        handleSpecGroupPickerChange,
        addSelectedSpecCandidate,
        applySpecTemplate,
        applySelectedSpecTemplate,
        createSpecTypeFromDraft,
        buildSpecRowFromSpecType,
        addInlineCreatedSpecType,
        saveInlineSpecType,
        mergeSpecGroupDetails,
        fetchSpecGroupCatalog,
        ensureSpecGroupDetail,
        groupSpecTypes,
        specGroupOptions,
        groupTemplates,
        allSpecTemplates,
    };
}
