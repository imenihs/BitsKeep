import { computed, nextTick, reactive, ref, watch } from 'vue';
import { api } from '../../api.js';
import { useComponentCreateChatGpt } from './chatgpt.js';
import { useServerDatasheetAnalysis } from './serverAnalysis.js';
import { buildSpecDraftFromApi, getSpecBaseUnit } from '../../utils/specValue.js';

/**
 * 部品登録画面のデータシート解析、ChatGPT連携、解析結果レビューを構成する。
 * 入力はフォーム、PDF選択状態、スペック操作API、通知関数で、戻り値はAI補助UIの状態と操作関数。
 * 動作条件はPDF選択と外部helper/Gemini APIの可用性で、一時PDF作成、sessionStorage、windowイベント、モーダル表示を副作用として持つ。
 */
// 目的: 部品登録画面のComponent Create Aiを扱う。機能: 入力値を検証・整形し、画面または計算処理へ渡す。入力: 関数シグネチャの値。出力: 処理結果またはなし。動作条件: 部品登録画面の初期化後に呼び出す。副作用: Vue状態、localStorage、DOM、HTTP通信のいずれかを更新する場合がある。
export function useComponentCreateAi(ctx) {
    const {
        form, dirty, datasheetFiles, datasheetLabels, datasheetTargetIndex,
        currentDatasheets, inlineSpecTypeModal, manufacturerQuery, helperSpecGroups,
        helperSpecTemplates, helperSuggestionLoading, categories, packages, specTypes,
        toastSuccess, toastError, ensureManufacturerOption, findCategoryById, findPackageById,
        findSpecTypeById, matchByName, normalizeHelperText, specTypeSearchText, prepareSpecDraftForEdit,
        templateItemSpecType, templateItemProfile, templateItemUnit, hasSpecTypeRow,
        applyToleranceDefaults, syncNormalSpecUnitToBase,
        chatGptConfig, logChatGptFlow, getChatGptRuntime, setChatGptRuntime,
    } = ctx;
    const analyzing = ref(false);
    const helperResult = ref(null);
    const showDatasheetManagerModal = ref(false);
    const showHelperResultModal = ref(false);
    const pendingAiAction = ref('');

    // 目的: AI補助系モーダル表示中の背景スクロールを制御する。入力は表示状態。出力はなし。副作用としてhtml/bodyのclassを更新する。
    const syncScrollLock = (isOpen) => {
        document.documentElement.classList.toggle('modal-open', Boolean(isOpen));
        document.body.classList.toggle('modal-open', Boolean(isOpen));
    };
    // 目的: 主要モーダルが閉じた後にスクロールロックを解除する。入力と出力はなし。副作用としてhtml/bodyのclassを更新する。
    const releaseScrollLockIfNoModal = () => {
        const stillOpen = inlineSpecTypeModal.open
            || showDatasheetManagerModal.value
            || showHelperResultModal.value;
        if (!stillOpen) {
            syncScrollLock(false);
        }
    };
    const helperResultSummary = computed(() => ({
        basicCount: [
            helperResult.value?.part_number,
            helperResult.value?.manufacturer,
            helperResult.value?.common_name,
            helperResult.value?.description,
        ].filter((field) => field?.apply && String(field?.value ?? '').trim()).length,
        categoryCount: (helperResult.value?.categories ?? []).filter((item) => item.apply).length,
        packageCount: helperResult.value?.package_apply ? 1 : 0,
        specCount: (helperResult.value?.specs ?? []).filter((item) => item.apply).length,
    }));

    // 目的: 部品登録画面のcreate Helper Basic Fieldを扱う。機能: 入力値を検証・整形し、画面または計算処理へ渡す。入力: 関数シグネチャの値。出力: 表示値、配列、オブジェクト、数値のいずれか。動作条件: 部品登録画面の初期化後に呼び出す。副作用: なし。
    const createHelperBasicField = (value = '') => ({
        value: String(value ?? '').trim(),
        apply: String(value ?? '').trim() !== '',
    });

    // 目的: 部品登録画面のcreate Helper Category Candidateを扱う。機能: 入力値を検証・整形し、画面または計算処理へ渡す。入力: 関数シグネチャの値。出力: 表示値、配列、オブジェクト、数値のいずれか。動作条件: 部品登録画面の初期化後に呼び出す。副作用: なし。
    const createHelperCategoryCandidate = (overrides = {}) => {
        const rawName = String(overrides.name ?? '').trim();
        const matchedCategory = overrides.category_id
            ? findCategoryById(overrides.category_id)
            : matchByName(rawName, categories.value);

        return {
            name: rawName || matchedCategory?.name || '',
            category_id: matchedCategory?.id ?? overrides.category_id ?? '',
            matched: overrides.matched ?? !!matchedCategory,
            apply: overrides.apply ?? (rawName !== '' || !!matchedCategory),
        };
    };

    // 目的: 部品登録画面のcreate Helper Package Candidateを扱う。機能: 入力値を検証・整形し、画面または計算処理へ渡す。入力: 関数シグネチャの値。出力: 表示値、配列、オブジェクト、数値のいずれか。動作条件: 部品登録画面の初期化後に呼び出す。副作用: なし。
    const createHelperPackageCandidate = (overrides = {}) => {
        const rawName = String(overrides.name ?? '').trim();
        const matchedPackage = overrides.package_id
            ? findPackageById(overrides.package_id)
            : matchByName(rawName, packages.value);

        return {
            name: rawName || matchedPackage?.name || '',
            package_group_id: matchedPackage?.package_group_id ?? overrides.package_group_id ?? '',
            package_id: matchedPackage?.id ?? overrides.package_id ?? '',
            matched: overrides.matched ?? !!matchedPackage,
            package_query: String(overrides.package_query ?? '').trim(),
        };
    };

    // 目的: 部品登録画面のcreate Helper Spec Candidateを扱う。機能: 入力値を検証・整形し、画面または計算処理へ渡す。入力: 関数シグネチャの値。出力: 表示値、配列、オブジェクト、数値のいずれか。動作条件: 部品登録画面の初期化後に呼び出す。副作用: なし。
    const createHelperSpecCandidate = (overrides = {}) => {
        const rawName = String(overrides.name ?? '').trim();
        const rawNameJa = String(overrides.name_ja ?? rawName).trim();
        const rawNameEn = String(overrides.name_en ?? '').trim();
        const rawSymbol = String(overrides.symbol ?? '').trim();
        const matchedSpecType = overrides.spec_type_id
            ? findSpecTypeById(overrides.spec_type_id)
            : matchByName(rawNameJa || rawNameEn || rawSymbol || rawName, specTypes.value, specTypeSearchText);
        const specTypeId = matchedSpecType?.id ?? overrides.spec_type_id ?? '';
        const draft = prepareSpecDraftForEdit(buildSpecDraftFromApi({
            ...overrides,
            spec_type_id: specTypeId,
            unit: overrides.unit ?? getSpecBaseUnit(matchedSpecType),
        }), matchedSpecType);

        return {
            name: rawName,
            name_ja: rawNameJa,
            name_en: rawNameEn,
            symbol: rawSymbol,
            spec_type_name: matchedSpecType?.name_ja ?? matchedSpecType?.name ?? rawNameJa,
            value_profile: draft.value_profile,
            value_typ: draft.value_typ,
            value_min: draft.value_min,
            value_max: draft.value_max,
            unit: draft.unit,
            spec_type_id: specTypeId,
            matched: overrides.matched ?? !!matchedSpecType,
            apply: overrides.apply ?? (
                rawName !== ''
                || String(overrides.value ?? '').trim() !== ''
                || String(overrides.value_typ ?? '').trim() !== ''
                || String(overrides.value_min ?? '').trim() !== ''
                || String(overrides.value_max ?? '').trim() !== ''
            ),
        };
    };
    // 目的: 部品登録画面のcreate Helper Spec From Template Itemを扱う。機能: 入力値を検証・整形し、画面または計算処理へ渡す。入力: 関数シグネチャの値。出力: 表示値、配列、オブジェクト、数値のいずれか。動作条件: 部品登録画面の初期化後に呼び出す。副作用: なし。
    const createHelperSpecFromTemplateItem = (item) => {
        const specType = templateItemSpecType(item);

        return createHelperSpecCandidate({
            spec_type_id: item?.spec_type_id ?? specType?.id ?? '',
            name: specType?.name ?? specType?.name_ja ?? '',
            name_ja: specType?.name_ja ?? specType?.name ?? '',
            name_en: specType?.name_en ?? '',
            symbol: specType?.symbol ?? '',
            value_profile: templateItemProfile(item),
            unit: templateItemUnit(item, specType),
            apply: true,
            matched: !!specType,
        });
    };
    const helperSelectedCategoryIds = computed(() => [
        ...new Set((helperResult.value?.categories ?? [])
            .filter((category) => category.apply && category.category_id)
            .map((category) => Number(category.category_id))
            .filter(Boolean)),
    ]);
    let helperSuggestionRequestSeq = 0;
    // 目的: 部品登録画面のfetch Spec Suggestions For Helperを扱う。機能: 入力値を検証・整形し、画面または計算処理へ渡す。入力: 関数シグネチャの値。出力: 処理結果またはなし。動作条件: 部品登録画面の初期化後に呼び出す。副作用: Vue状態、localStorage、DOM、HTTP通信のいずれかを更新する場合がある。
    const fetchSpecSuggestionsForHelper = async () => {
        const categoryIds = helperSelectedCategoryIds.value;
        const seq = ++helperSuggestionRequestSeq;

        if (!categoryIds.length) {
            helperSpecGroups.value = [];
            helperSpecTemplates.value = [];
            helperSuggestionLoading.value = false;
            return;
        }

        helperSuggestionLoading.value = true;
        try {
            const params = new URLSearchParams();
            categoryIds.forEach((categoryId) => params.append('category_ids[]', categoryId));
            const res = await api.get(`/spec-suggestions?${params.toString()}`);
            if (seq !== helperSuggestionRequestSeq) return;

            helperSpecGroups.value = res.data?.groups ?? [];
            helperSpecTemplates.value = res.data?.templates ?? [];
        } catch {
            if (seq !== helperSuggestionRequestSeq) return;
            helperSpecGroups.value = [];
            helperSpecTemplates.value = [];
        } finally {
            if (seq === helperSuggestionRequestSeq) {
                helperSuggestionLoading.value = false;
            }
        }
    };
    // 目的: 部品登録画面のapply Helper Templateを扱う。機能: 入力値を検証・整形し、画面または計算処理へ渡す。入力: 関数シグネチャの値。出力: 処理結果またはなし。動作条件: 部品登録画面の初期化後に呼び出す。副作用: Vue状態、localStorage、DOM、HTTP通信のいずれかを更新する場合がある。
    const applyHelperTemplate = (template) => {
        if (!helperResult.value) return;

        const rows = Array.isArray(template?.items) ? template.items : [];
        if (!rows.length) {
            toastError('この入力テンプレートにはスペック行がありません');
            return;
        }

        let added = 0;
        let skipped = 0;
        rows.forEach((item) => {
            const specTypeId = Number(item?.spec_type_id ?? templateItemSpecType(item)?.id ?? 0);
            if (!specTypeId || hasSpecTypeRow(specTypeId, helperResult.value.specs ?? [])) {
                skipped++;
                return;
            }

            helperResult.value.specs.push(createHelperSpecFromTemplateItem(item));
            added++;
        });

        if (added > 0) {
            toastSuccess(skipped > 0
                ? `${template.name} をスペック候補へ追加しました（追加 ${added} 件 / 既存 ${skipped} 件）`
                : `${template.name} をスペック候補へ追加しました（追加 ${added} 件）`);
            return;
        }

        toastError('既に同じスペック詳細の候補があります');
    };

    // 目的: 部品登録画面のextract Category Namesを扱う。機能: 入力値を検証・整形し、画面または計算処理へ渡す。入力: 関数シグネチャの値。出力: 表示値、配列、オブジェクト、数値のいずれか。動作条件: 部品登録画面の初期化後に呼び出す。副作用: なし。
    const extractCategoryNames = (data) => {
        const names = [];

        if (Array.isArray(data?.component_types)) {
            names.push(...data.component_types);
        }

        if (Array.isArray(data?.category_names)) {
            names.push(...data.category_names);
        }

        if (Array.isArray(data?.categories)) {
            names.push(...data.categories.map((item) => (typeof item === 'string' ? item : item?.name ?? '')));
        }

        if (typeof data?.component_type === 'string') {
            names.push(data.component_type);
        }

        return [...new Set(
            names
                .map((value) => String(value ?? '').trim())
                .filter(Boolean)
        )];
    };

    // 目的: 部品登録画面のextract Package Candidatesを扱う。機能: 入力値を検証・整形し、画面または計算処理へ渡す。入力: 関数シグネチャの値。出力: 表示値、配列、オブジェクト、数値のいずれか。動作条件: 部品登録画面の初期化後に呼び出す。副作用: なし。
    const extractPackageCandidates = (data) => {
        const candidates = [];
        // 目的: 部品登録画面のadd Candidateを扱う。機能: 入力値を検証・整形し、画面または計算処理へ渡す。入力: 関数シグネチャの値。出力: 処理結果またはなし。動作条件: 部品登録画面の初期化後に呼び出す。副作用: Vue状態、localStorage、DOM、HTTP通信のいずれかを更新する場合がある。
        const addCandidate = (value) => {
            if (typeof value === 'string') {
                const name = value.trim();
                if (name) candidates.push({ name });
                return;
            }

            if (!value || typeof value !== 'object') return;
            const name = String(value.name ?? value.package_name ?? value.package ?? value.type ?? '').trim();
            const packageId = value.package_id ?? '';
            const packageGroupId = value.package_group_id ?? value.group_id ?? '';
            if (name || packageId) {
                candidates.push({
                    name,
                    package_id: packageId,
                    package_group_id: packageGroupId,
                });
            }
        };

        if (Array.isArray(data?.package_names)) {
            data.package_names.forEach(addCandidate);
        }

        if (Array.isArray(data?.packages)) {
            data.packages.forEach(addCandidate);
        }

        if (typeof data?.package_name === 'string') {
            addCandidate(data.package_name);
        }

        if (typeof data?.package === 'string') {
            addCandidate(data.package);
        } else {
            addCandidate(data?.package);
        }

        if (typeof data?.package_type === 'string') {
            addCandidate(data.package_type);
        }

        const seen = new Set;
        return candidates.filter((candidate) => {
            const key = `${candidate.package_id || ''}:${String(candidate.name ?? '').toLowerCase()}`;
            if (seen.has(key)) return false;
            seen.add(key);
            return true;
        });
    };

    // 目的: 部品登録画面のbuild Helper Resultを扱う。機能: 入力値を検証・整形し、画面または計算処理へ渡す。入力: 関数シグネチャの値。出力: 表示値、配列、オブジェクト、数値のいずれか。動作条件: 部品登録画面の初期化後に呼び出す。副作用: なし。
    const buildHelperResult = (data) => {
        const source = data ?? {};
        const packageCandidates = extractPackageCandidates(source).map((candidate) => createHelperPackageCandidate(candidate));
        const preferredPackageIndex = packageCandidates.findIndex((item) => item.package_id);
        const selectedPackageIndex = preferredPackageIndex >= 0 ? preferredPackageIndex : (packageCandidates.length ? 0 : null);

        return {
            part_number: createHelperBasicField(source.part_number),
            manufacturer: createHelperBasicField(source.manufacturer),
            common_name: createHelperBasicField(source.common_name),
            description: createHelperBasicField(source.description),
            categories: extractCategoryNames(source).map((name) => createHelperCategoryCandidate({ name })),
            package_apply: packageCandidates.length > 0,
            selected_package_index: selectedPackageIndex,
            packages: packageCandidates,
            specs: (Array.isArray(source.specs) ? source.specs : []).map((spec) => createHelperSpecCandidate(spec)),
        };
    };

    // 目的: 部品登録画面のhas Helper Candidatesを扱う。機能: 入力値を検証・整形し、画面または計算処理へ渡す。入力: 関数シグネチャの値。出力: 真偽値。動作条件: 部品登録画面の初期化後に呼び出す。副作用: なし。
    const hasHelperCandidates = (result) => {
        if (!result) return false;

        return [
            result.part_number?.value,
            result.manufacturer?.value,
            result.common_name?.value,
            result.description?.value,
        ].some((value) => String(value ?? '').trim() !== '')
            || (result.categories ?? []).length > 0
            || (result.packages ?? []).length > 0
            || (result.specs ?? []).length > 0;
    };

    // 目的: 部品登録画面のopen Helper Result Modalを扱う。機能: 入力値を検証・整形し、画面または計算処理へ渡す。入力: 関数シグネチャの値。出力: 処理結果またはなし。動作条件: 部品登録画面の初期化後に呼び出す。副作用: Vue状態、localStorage、DOM、HTTP通信のいずれかを更新する場合がある。
    const openHelperResultModal = () => {
        if (!helperResult.value) return;
        showHelperResultModal.value = true;
    };

    // 目的: 部品登録画面のclose Helper Result Modalを扱う。機能: 入力値を検証・整形し、画面または計算処理へ渡す。入力: 関数シグネチャの値。出力: 処理結果またはなし。動作条件: 部品登録画面の初期化後に呼び出す。副作用: Vue状態、localStorage、DOM、HTTP通信のいずれかを更新する場合がある。
    const closeHelperResultModal = () => {
        showHelperResultModal.value = false;
        nextTick(() => {
            releaseScrollLockIfNoModal();
        });
    };

    // 目的: 部品登録画面のdiscard Helper Resultを扱う。機能: 入力値を検証・整形し、画面または計算処理へ渡す。入力: 関数シグネチャの値。出力: 処理結果またはなし。動作条件: 部品登録画面の初期化後に呼び出す。副作用: Vue状態、localStorage、DOM、HTTP通信のいずれかを更新する場合がある。
    const discardHelperResult = () => {
        helperResult.value = null;
        showHelperResultModal.value = false;
        nextTick(() => {
            releaseScrollLockIfNoModal();
        });
    };

    // 目的: 部品登録画面のadd Helper Categoryを扱う。機能: 入力値を検証・整形し、画面または計算処理へ渡す。入力: 関数シグネチャの値。出力: 処理結果またはなし。動作条件: 部品登録画面の初期化後に呼び出す。副作用: Vue状態、localStorage、DOM、HTTP通信のいずれかを更新する場合がある。
    const addHelperCategory = () => {
        helperResult.value?.categories.push(createHelperCategoryCandidate({ apply: true }));
    };

    // 目的: 部品登録画面のremove Helper Categoryを扱う。機能: 入力値を検証・整形し、画面または計算処理へ渡す。入力: 関数シグネチャの値。出力: 処理結果またはなし。動作条件: 部品登録画面の初期化後に呼び出す。副作用: Vue状態、localStorage、DOM、HTTP通信のいずれかを更新する場合がある。
    const removeHelperCategory = (index) => {
        helperResult.value?.categories.splice(index, 1);
    };

    // 目的: 部品登録画面のadd Helper Specを扱う。機能: 入力値を検証・整形し、画面または計算処理へ渡す。入力: 関数シグネチャの値。出力: 処理結果またはなし。動作条件: 部品登録画面の初期化後に呼び出す。副作用: Vue状態、localStorage、DOM、HTTP通信のいずれかを更新する場合がある。
    const addHelperSpec = () => {
        helperResult.value?.specs.push(createHelperSpecCandidate({ apply: true }));
    };

    // 目的: 部品登録画面のadd Helper Packageを扱う。機能: 入力値を検証・整形し、画面または計算処理へ渡す。入力: 関数シグネチャの値。出力: 処理結果またはなし。動作条件: 部品登録画面の初期化後に呼び出す。副作用: Vue状態、localStorage、DOM、HTTP通信のいずれかを更新する場合がある。
    const addHelperPackage = () => {
        if (!helperResult.value) return;

        helperResult.value.packages.push(createHelperPackageCandidate());
        if (helperResult.value.selected_package_index === null || helperResult.value.selected_package_index === '') {
            helperResult.value.selected_package_index = helperResult.value.packages.length - 1;
        }
    };

    // 目的: 部品登録画面のremove Helper Packageを扱う。機能: 入力値を検証・整形し、画面または計算処理へ渡す。入力: 関数シグネチャの値。出力: 処理結果またはなし。動作条件: 部品登録画面の初期化後に呼び出す。副作用: Vue状態、localStorage、DOM、HTTP通信のいずれかを更新する場合がある。
    const removeHelperPackage = (index) => {
        if (!helperResult.value) return;

        helperResult.value.packages.splice(index, 1);

        if (helperResult.value.packages.length === 0) {
            helperResult.value.selected_package_index = null;
            return;
        }

        const selectedIndex = helperResult.value.selected_package_index === null || helperResult.value.selected_package_index === ''
            ? null
            : Number(helperResult.value.selected_package_index);

        if (selectedIndex === null || selectedIndex === index) {
            helperResult.value.selected_package_index = 0;
            return;
        }

        if (selectedIndex > index) {
            helperResult.value.selected_package_index = selectedIndex - 1;
        }
    };

    // 目的: 部品登録画面のremove Helper Specを扱う。機能: 入力値を検証・整形し、画面または計算処理へ渡す。入力: 関数シグネチャの値。出力: 処理結果またはなし。動作条件: 部品登録画面の初期化後に呼び出す。副作用: Vue状態、localStorage、DOM、HTTP通信のいずれかを更新する場合がある。
    const removeHelperSpec = (index) => {
        helperResult.value?.specs.splice(index, 1);
    };

    // 目的: 部品登録画面のhandle Helper Category Selectionを扱う。機能: 入力値を検証・整形し、画面または計算処理へ渡す。入力: 関数シグネチャの値。出力: 処理結果またはなし。動作条件: 部品登録画面の初期化後に呼び出す。副作用: Vue状態、localStorage、DOM、HTTP通信のいずれかを更新する場合がある。
    const handleHelperCategorySelection = (candidate) => {
        const matchedCategory = findCategoryById(candidate.category_id);
        candidate.matched = !!matchedCategory;
        if (matchedCategory && !String(candidate.name ?? '').trim()) {
            candidate.name = matchedCategory.name;
        }
    };

    // 目的: 部品登録画面のhandle Helper Spec Type Selectionを扱う。機能: 入力値を検証・整形し、画面または計算処理へ渡す。入力: 関数シグネチャの値。出力: 処理結果またはなし。動作条件: 部品登録画面の初期化後に呼び出す。副作用: Vue状態、localStorage、DOM、HTTP通信のいずれかを更新する場合がある。
    const handleHelperSpecTypeSelection = (spec) => {
        const matchedType = findSpecTypeById(spec.spec_type_id);
        spec.matched = !!matchedType;
        spec.spec_type_name = matchedType?.name_ja ?? matchedType?.name ?? '';
        if (matchedType) {
            applyToleranceDefaults(spec, matchedType);
            syncNormalSpecUnitToBase(spec, matchedType);
        }
    };

    // 目的: 部品登録画面のhandle Helper Package Group Changeを扱う。機能: 入力値を検証・整形し、画面または計算処理へ渡す。入力: 関数シグネチャの値。出力: 処理結果またはなし。動作条件: 部品登録画面の初期化後に呼び出す。副作用: Vue状態、localStorage、DOM、HTTP通信のいずれかを更新する場合がある。
    const handleHelperPackageGroupChange = (packageCandidate) => {
        if (!packageCandidate) return;

        packageCandidate.package_query = '';
        if (!packageCandidate.package_group_id) {
            packageCandidate.package_id = '';
            packageCandidate.matched = false;
            return;
        }

        const selectedPackage = findPackageById(packageCandidate.package_id);
        if (!selectedPackage || Number(selectedPackage.package_group_id) !== Number(packageCandidate.package_group_id)) {
            packageCandidate.package_id = '';
            packageCandidate.matched = false;
        }
    };

    // 目的: 部品登録画面のhandle Helper Package Selectionを扱う。機能: 入力値を検証・整形し、画面または計算処理へ渡す。入力: 関数シグネチャの値。出力: 処理結果またはなし。動作条件: 部品登録画面の初期化後に呼び出す。副作用: Vue状態、localStorage、DOM、HTTP通信のいずれかを更新する場合がある。
    const handleHelperPackageSelection = (packageCandidate) => {
        if (!packageCandidate) return;

        const matchedPackage = findPackageById(packageCandidate.package_id);
        if (!matchedPackage) {
            packageCandidate.matched = false;
            return;
        }

        packageCandidate.package_group_id = matchedPackage.package_group_id;
        packageCandidate.matched = true;
        if (!String(packageCandidate.name ?? '').trim()) {
            packageCandidate.name = matchedPackage.name;
        }
    };

    // 目的: 部品登録画面のhelper Filtered Packagesを扱う。機能: 入力値を検証・整形し、画面または計算処理へ渡す。入力: 関数シグネチャの値。出力: 処理結果またはなし。動作条件: 部品登録画面の初期化後に呼び出す。副作用: Vue状態、localStorage、DOM、HTTP通信のいずれかを更新する場合がある。
    const helperFilteredPackages = (packageCandidate) => {
        const groupId = packageCandidate?.package_group_id;
        if (!groupId) return [];

        const scopedPackages = packages.value.filter((item) => Number(item.package_group_id) === Number(groupId));
        const query = String(packageCandidate?.package_query ?? '').trim().toLowerCase();
        if (!query) return scopedPackages;

        return scopedPackages.filter((item) => item.name.toLowerCase().includes(query));
    };

    // 目的: 解析レビューで採用対象にした候補を登録フォームへ反映する。入力はなし。出力はなし。
    // 動作条件はhelperResultが存在すること。副作用としてform、メーカー候補、dirty、モーダル状態を更新する。
    const applyHelperResult = () => {
        const result = helperResult.value;
        if (!result) return;

        if (result.part_number?.apply) form.part_number = result.part_number.value;
        if (result.manufacturer?.apply) {
            form.manufacturer = result.manufacturer.value;
            manufacturerQuery.value = result.manufacturer.value;
            ensureManufacturerOption(result.manufacturer.value);
        }
        if (result.common_name?.apply) form.common_name = result.common_name.value;
        if (result.description?.apply) form.description = result.description.value;

        (result.categories ?? [])
            .filter((category) => category.apply && category.category_id)
            .forEach((category) => {
                const categoryId = Number(category.category_id);
                if (!form.category_ids.includes(categoryId)) {
                    form.category_ids.push(categoryId);
                }
            });

        if (result.package_apply) {
            const selectedPackage = result.packages?.[Number(result.selected_package_index ?? 0)] ?? null;
            if (selectedPackage?.package_group_id) form.package_group_id = selectedPackage.package_group_id;
            if (selectedPackage?.package_id) form.package_id = selectedPackage.package_id;
        }

        (result.specs ?? [])
            .filter((spec) => spec.apply && spec.spec_type_id)
            .forEach((spec) => {
                if (hasSpecTypeRow(spec.spec_type_id)) return;
                const specType = findSpecTypeById(spec.spec_type_id);
                form.specs.push(prepareSpecDraftForEdit({
                    ...spec,
                    spec_type_name: specType?.name_ja ?? specType?.name ?? spec.spec_type_name ?? spec.name_ja ?? spec.name ?? '',
                }, specType));
            });

        dirty.value = true;
        showHelperResultModal.value = false;
        toastSuccess('解析候補をフォームへ反映しました');
        nextTick(() => {
            releaseScrollLockIfNoModal();
        });
    };

    // 目的: 選択中PDFをGemini解析へ渡し、レビュー候補を作る。入力は自動実行時のフラグ。出力はなし。
    // 動作条件はPDFが選択済みで解析APIが利用できること。副作用としてHTTP通信、helperResult、モーダル状態、通知を更新する。
    const analyzeDatasheet = async () => {
        const targetFile = selectedDatasheetFile.value;
        if (!targetFile) {
            toastError('先にデータシートPDFを選択してください');
            return;
        }

        analyzing.value = true;
        try {
            const fd = new FormData();
            fd.append('pdf', targetFile);
            const res = await api.upload('/component-helper/analyze-datasheet', fd);
            const result = buildHelperResult(res.data ?? res);
            if (!hasHelperCandidates(result)) {
                toastError('解析候補を取得できませんでした');
                return;
            }
            helperResult.value = result;
            showHelperResultModal.value = true;
            showDatasheetManagerModal.value = false;
            pendingAiAction.value = '';
            toastSuccess('解析候補を取得しました。内容を確認してください');
        } catch (e) {
            toastError(e.message ?? 'データシート解析に失敗しました');
        } finally {
            analyzing.value = false;
        }
    };

    // 目的: 部品登録画面のopen Datasheet Managerを扱う。機能: 入力値を検証・整形し、画面または計算処理へ渡す。入力: 関数シグネチャの値。出力: 処理結果またはなし。動作条件: 部品登録画面の初期化後に呼び出す。副作用: Vue状態、localStorage、DOM、HTTP通信のいずれかを更新する場合がある。
    const openDatasheetManager = () => {
        if (chatGpt.isChatGptJobBusy.value) return;
        chatGpt.showChatGptRunModal.value = false;
        showDatasheetManagerModal.value = true;
    };

    // 目的: 部品登録画面のclose Datasheet Managerを扱う。機能: 入力値を検証・整形し、画面または計算処理へ渡す。入力: 関数シグネチャの値。出力: 処理結果またはなし。動作条件: 部品登録画面の初期化後に呼び出す。副作用: Vue状態、localStorage、DOM、HTTP通信のいずれかを更新する場合がある。
    const closeDatasheetManager = () => {
        showDatasheetManagerModal.value = false;
        pendingAiAction.value = '';
        nextTick(() => {
            releaseScrollLockIfNoModal();
        });
    };

    const chatGpt = useComponentCreateChatGpt({
        datasheetFiles, datasheetLabels, datasheetTargetIndex, currentDatasheets, dirty,
        inlineSpecTypeModal, analyzing, helperResult, showHelperResultModal, showDatasheetManagerModal,
        buildHelperResult, hasHelperCandidates, releaseScrollLockIfNoModal, toastSuccess, toastError,
        logChatGptFlow, chatGptConfig, getChatGptRuntime, setChatGptRuntime,
        pendingAiAction, getAnalyzeDatasheet: () => analyzeDatasheet,
        // 解析対象PDFの選択後にサーバ側解析へ戻るための入口。
        // serverAnalysis はこの後で組み立てるため、遅延評価で受け渡す
        getServerAnalysisStart: () => serverAnalysis.startAnalysis,
    });
    const {
        selectedDatasheetFile, datasheetTargetLabel, hasDatasheetForAi, showChatGPTPaste, chatGPTPasteText, chatGPTPasteTextarea, navigationGuardActive,
        chatGptStatusLabel, chatGptStatusChips, chatGptStepStates, canStartChatGptAutoFill, chatGptHelperIssue, showChatGptRunHint,
        isChatGptHelperVersionCompatible, syncTampermonkeyConnection, syncStoredChatGptBridgeState, syncChatGptWorkerHeartbeat,
        openChatGptHelperUpdateModal, closeChatGptHelperUpdateModal, reloadForChatGptHelperUpdate, handleChatGptHelperReloadRecheck,
        hardResetChatGptJob, clearChatGptTempDatasheets, resetChatGptJobState, beginAiAction, confirmDatasheetTargetSelection, openChatGptRun, closeChatGptRun,
        openChatGPTPaste, parseChatGPTResult, dismissChatGPTPaste, openPasteFallbackFromGuide, copyChatGptFallbackText,
        startChatGPTAutoFill, chatGptGuideReason, chatGptJob, canDismissChatGptRun, isChatGptJobBusy,
        showChatGptRunModal, showChatGptHelperUpdateModal, chatGptHelperCheckStatus, chatGptHelperCheckMessage,
        initializeChatGptBridge, cleanupChatGptBridge, chatGptWorkerHeartbeat,
    } = chatGpt;

    // サーバ側で完結する解析。ブラウザのタブを閉じても解析が続くため、
    // 解析中の進行表示と復帰はこの composable が持つ
    const serverAnalysis = useServerDatasheetAnalysis({
        selectedDatasheetFile, helperResult, showHelperResultModal, showDatasheetManagerModal,
        buildHelperResult, hasHelperCandidates, toastSuccess, toastError,
    });

    /**
     * 目的: データシートから自動入力を開始する。
     * 機能: 複数PDFを選んでいる場合は解析対象の選択を挟み、1件ならそのまま解析を開始する。
     * 入力: なし。選択中PDFは画面の状態から取る。
     * 出力: なし。
     * 動作条件: データシートPDFが1件以上選択済みであること。
     * 副作用: モーダル状態、HTTP通信、解析状態を更新する。
     */
    const startServerAnalysis = async () => {
        if (!datasheetFiles.value.length) {
            toastError('先にデータシートPDFを選択してください。');
            return;
        }

        // 複数PDFのうちどれを解析するかは利用者が決める。勝手に1件目を解析しない
        if (datasheetFiles.value.length > 1) {
            pendingAiAction.value = 'server';
            openDatasheetManager();
            return;
        }

        pendingAiAction.value = '';
        await serverAnalysis.startAnalysis();
    };

    watch(chatGpt.anyModalOpen, (isOpen) => {
        syncScrollLock(isOpen);
    }, { immediate: true });

    watch(() => helperSelectedCategoryIds.value.join(','), () => {
        void fetchSpecSuggestionsForHelper();
    });

    watch(() => datasheetFiles.value.length, (length) => {
        if (length === 0) {
            datasheetTargetIndex.value = 0;
            return;
        }

        if (datasheetTargetIndex.value >= length) {
            datasheetTargetIndex.value = 0;
        }
    });

    watch(() => [chatGptJob.connected, chatGptJob.helperVersion], () => {
        if (chatGptHelperIssue.value === null) {
            if (showChatGptHelperUpdateModal.value) {
                chatGptHelperCheckStatus.value = 'success';
                chatGptHelperCheckMessage.value = `helper v${chatGptJob.helperVersion} を検出しました。このまま ChatGPT自動解析を使えます。`;
            }
            return;
        }

        if (showChatGptHelperUpdateModal.value && chatGptHelperCheckStatus.value === 'success') {
            chatGptHelperCheckStatus.value = 'warning';
            chatGptHelperCheckMessage.value = chatGptHelperIssue.value.body;
        }
    });

    return {
        // サーバ側解析（主導線）
        startServerAnalysis,
        serverAnalysisState: serverAnalysis.analysisState,
        serverAnalysisFailureMessage: serverAnalysis.failureMessage,
        serverAnalysisRetryable: serverAnalysis.retryable,
        serverAnalysisNeedsSetup: serverAnalysis.needsEngineSetup,
        serverAnalysisSuggestPaste: serverAnalysis.suggestPasteFallback,
        isServerAnalyzing: serverAnalysis.isAnalyzing,
        serverAnalysisProgressLabel: serverAnalysis.progressLabel,
        cancelServerAnalysis: serverAnalysis.cancelAnalysis,
        resumeServerAnalysis: serverAnalysis.resumeAnalysis,
        dismissServerAnalysisFailure: serverAnalysis.resetAnalysis,

        analyzing, helperResult, showDatasheetManagerModal, showChatGptRunModal,
        showHelperResultModal, showChatGptHelperUpdateModal, chatGptHelperCheckStatus,
        chatGptHelperCheckMessage, chatGptGuideReason, pendingAiAction,
        chatGptJob, chatGptWorkerHeartbeat, isChatGptJobBusy,
        canDismissChatGptRun, syncScrollLock, releaseScrollLockIfNoModal,
        helperResultSummary, datasheetTargetLabel, selectedDatasheetFile, hasDatasheetForAi,
        showChatGPTPaste, chatGPTPasteText, chatGPTPasteTextarea, navigationGuardActive,
        chatGptStatusLabel, chatGptStatusChips, chatGptStepStates, canStartChatGptAutoFill,
        chatGptHelperIssue, showChatGptRunHint, isChatGptHelperVersionCompatible,
        syncTampermonkeyConnection, syncStoredChatGptBridgeState, syncChatGptWorkerHeartbeat,
        openChatGptHelperUpdateModal, closeChatGptHelperUpdateModal,
        reloadForChatGptHelperUpdate, handleChatGptHelperReloadRecheck,
        hardResetChatGptJob, clearChatGptTempDatasheets, resetChatGptJobState,
        openDatasheetManager, closeDatasheetManager, beginAiAction,
        confirmDatasheetTargetSelection, openChatGptRun, closeChatGptRun,
        openChatGPTPaste, parseChatGPTResult, dismissChatGPTPaste,
        openPasteFallbackFromGuide,
        copyChatGptFallbackText, startChatGPTAutoFill, analyzeDatasheet, applyHelperResult,
        openHelperResultModal, closeHelperResultModal, discardHelperResult, applyHelperTemplate,
        addHelperCategory, removeHelperCategory, addHelperSpec, removeHelperSpec,
        addHelperPackage, removeHelperPackage, handleHelperCategorySelection, handleHelperSpecTypeSelection,
        handleHelperPackageGroupChange, handleHelperPackageSelection, helperFilteredPackages,
        initializeChatGptBridge, cleanupChatGptBridge,
    };
}
