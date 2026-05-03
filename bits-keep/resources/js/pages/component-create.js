import { ref, reactive, onMounted, onBeforeUnmount, computed, watch } from 'vue';
import { api } from '../api.js';
import { useToast } from '../composables/useToast.js';
import { buildSpecDraftFromApi, buildSpecPayload, renderSymbol } from '../utils/specValue.js';
import { useComponentCreateSpecs } from './component-create/specs.js';
import { useComponentCreateAi } from './component-create/ai.js';

/**
 * 部品作成/編集画面の公開setup。
 * 入力はBladeのdata属性とURLクエリで、戻り値はフォーム状態、候補、保存/解析操作をVueテンプレートへ公開する。
 * 動作条件は各マスタAPIが参照可能なことで、初期ロード、PDF一時保存、部品保存、画面遷移、トースト表示の副作用を持つ。
 */
// 目的: 部品登録画面のsetupを扱う。機能: 入力値を検証・整形し、画面または計算処理へ渡す。入力: 関数シグネチャの値。出力: 処理結果またはなし。動作条件: 部品登録画面の初期化後に呼び出す。副作用: Vue状態、localStorage、DOM、HTTP通信のいずれかを更新する場合がある。
export default function setup() {
    const { toasts, toastSuccess, toastError } = useToast();
    const appEl = document.getElementById('app');
    const editId = appEl?.dataset?.id ?? null; // 編集時はID、新規はnull
    const isEdit = !!editId;
    const duplicateFromId = new URLSearchParams(window.location.search).get('duplicate_from');
    const CHATGPT_HELPER_MIN_VERSION = appEl?.dataset?.chatgptHelperMinVersion || '0.1.34';
    const CHATGPT_WINDOW_NAME = 'bitskeep-chatgpt-worker';
    const BITSKEEP_WINDOW_NAME = 'bitskeep-component-create';
    const CHATGPT_HELPER_RECHECK_STORAGE_KEY = 'bitskeep-chatgpt-helper-recheck';
    const CHATGPT_ACTIVE_JOB_STORAGE_KEY = 'bitskeep-chatgpt-active-job';
    const CHATGPT_STATUS_STALE_MS = 30000;
    const CHATGPT_QUEUED_STALE_MS = 15000;
    const CHATGPT_WAITING_STALE_MS = 180000;
    const CHATGPT_WORKER_STALE_MS = 8000;
    const CHATGPT_WORKER_READY_WAIT_MS = 8000;
    const CHATGPT_WORKER_READY_POLL_MS = 400;
    const CHATGPT_TEMP_CLEANUP_TIMEOUT_MS = 4000;
    const CHATGPT_JOB_CREATE_TIMEOUT_MS = 15000;
    let lastChatGptHelperLogSignature = '';

    // 目的: 部品登録画面のlog Chat Gpt Flowを扱う。機能: 入力値を検証・整形し、画面または計算処理へ渡す。入力: 関数シグネチャの値。出力: 処理結果またはなし。動作条件: 部品登録画面の初期化後に呼び出す。副作用: Vue状態、localStorage、DOM、HTTP通信のいずれかを更新する場合がある。
    const logChatGptFlow = (stage, detail = {}) => {
        console.info('[BitsKeep][ChatGPT Flow]', stage, detail);
        const helper = window.__bitskeepTampermonkeyHelper;
        if (helper?.connected && typeof helper.appendDebugLog === 'function') {
            helper.appendDebugLog(`flow.${stage}`, 'BitsKeep 画面側ログです。', detail);
        }
    };

    // マスタデータ
    const categories = ref([]);
    const packageGroups = ref([]);
    const packages   = ref([]);
    const specTypes  = ref([]);
    const specGroups = ref([]);
    const specSuggestionTypes = ref([]);
    const specTemplates = ref([]);
    const selectedSpecGroupId = ref('all');
    const selectedSpecCandidateId = ref('');
    const selectedSpecTemplateId = ref('');
    const specTypeSearchQuery = ref('');
    const specSuggestionLoading = ref(false);
    const helperSpecGroups = ref([]);
    const helperSpecTemplates = ref([]);
    const helperSuggestionLoading = ref(false);
    const componentRegistrationMode = ref('single');
    const componentSeriesOptions = ref([]);
    const componentSeriesLoading = ref(false);
    const componentSeriesLoadError = ref('');
    const suppliers  = ref([]);
    const locations  = ref([]);
    const altiumLibraries = ref([]);
    const manufacturerOptions = ref([]);

    // フォーム
    const form = reactive({
        part_number: '', manufacturer: '', common_name: '', description: '',
        procurement_status: 'active',
        threshold_new: 0, threshold_used: 0,
        primary_location_id: '',
        category_ids: [],
        package_group_id: '',
        package_id: '',
        component_series_id: '',
        component_series_value_id: '',
        specs: [],       // [{ spec_type_id, value_profile, value_typ, value_min, value_max, unit }]
        custom_attributes: [],
        altium: {
            sch_library_id: '',
            sch_symbol: '',
            pcb_library_id: '',
            pcb_footprint: '',
        },
        supplierRows: [], // [{ supplier_id, supplier_part_number, product_url, unit_price, is_preferred, price_breaks:[] }]
    });
    const imageFile = ref(null);
    const datasheetFiles = ref([]);
    const datasheetLabels = ref([]);
    const datasheetTargetIndex = ref(0);
    const imagePreviewUrl = ref('');
    const currentImageUrl = ref('');
    const currentDatasheets = ref([]);

    const saving = ref(false);
    const dirty = ref(false);
    const initialSnapshot = ref('');
    const masterLoadError = ref('');
    const manufacturerQuery = ref('');
    const manufacturerSuggestionsOpen = ref(false);
    const categoryQuery = ref('');
    const packageQuery = ref('');
    const schLibraries = computed(() => altiumLibraries.value.filter((library) => library.type === 'SchLib'));
    const pcbLibraries = computed(() => altiumLibraries.value.filter((library) => library.type === 'PcbLib'));

    const filteredManufacturers = computed(() => {
        const q = manufacturerQuery.value.trim().toLowerCase();
        if (!q) return manufacturerOptions.value.slice(0, 8);
        return manufacturerOptions.value.filter((name) => name.toLowerCase().includes(q)).slice(0, 8);
    });
    const manufacturerExactMatch = computed(() =>
        manufacturerOptions.value.some((name) => name.toLowerCase() === manufacturerQuery.value.trim().toLowerCase())
    );
    const filteredCategories = computed(() => {
        const q = categoryQuery.value.trim().toLowerCase();
        if (!q) return categories.value;
        return categories.value.filter((item) => item.name.toLowerCase().includes(q));
    });
    const filteredPackages = computed(() => {
        const q = packageQuery.value.trim().toLowerCase();
        const scopedPackages = form.package_group_id
            ? packages.value.filter((item) => item.package_group_id === Number(form.package_group_id))
            : [];
        if (!q) return scopedPackages;
        return scopedPackages.filter((item) => item.name.toLowerCase().includes(q));
    });
    const selectedComponentSeries = computed(() =>
        componentSeriesOptions.value.find((item) => Number(item.id) === Number(form.component_series_id)) ?? null
    );
    const componentSeriesValueOptions = computed(() =>
        (selectedComponentSeries.value?.values ?? []).filter((item) => item.is_enabled !== false)
    );
    // 目的: 部品登録画面のcomponent Series Option Labelを扱う。機能: 入力値を検証・整形し、画面または計算処理へ渡す。入力: 関数シグネチャの値。出力: 処理結果またはなし。動作条件: 部品登録画面の初期化後に呼び出す。副作用: Vue状態、localStorage、DOM、HTTP通信のいずれかを更新する場合がある。
    const componentSeriesOptionLabel = (series) => {
        const groupName = series?.spec_group?.name ?? series?.specGroup?.name ?? '';
        const packageName = series?.package?.name ?? '';
        const suffix = [groupName, packageName].filter(Boolean).join(' / ');

        return suffix ? `${series.name} / ${suffix}` : series.name;
    };
    const canCreateCategory = computed(() => {
        const q = categoryQuery.value.trim();
        if (!q) return false;
        return !categories.value.some((item) => item.name.toLowerCase() === q.toLowerCase());
    });
    const canCreatePackage = computed(() => {
        const q = packageQuery.value.trim();
        if (!q || !form.package_group_id) return false;
        return !packages.value.some((item) => item.package_group_id === Number(form.package_group_id) && item.name.toLowerCase() === q.toLowerCase());
    });
    const canCreateSupplier = computed(() => appEl?.dataset?.canCreateSupplier === '1');
    const canCreateSpecType = computed(() => appEl?.dataset?.canCreateSpecType === '1');

    // 目的: 部品登録画面のsync Manufacturer Queryを扱う。機能: 入力値を検証・整形し、画面または計算処理へ渡す。入力: 関数シグネチャの値。出力: 処理結果またはなし。動作条件: 部品登録画面の初期化後に呼び出す。副作用: Vue状態、localStorage、DOM、HTTP通信のいずれかを更新する場合がある。
    const syncManufacturerQuery = () => {
        manufacturerQuery.value = form.manufacturer ?? '';
    };
    // 目的: 部品登録画面のnormalize Unique Namesを扱う。機能: 入力値を検証・整形し、画面または計算処理へ渡す。入力: 関数シグネチャの値。出力: 表示値、配列、オブジェクト、数値のいずれか。動作条件: 部品登録画面の初期化後に呼び出す。副作用: なし。
    const normalizeUniqueNames = (values) => [...new Set(values.map((value) => String(value).trim()).filter(Boolean))].sort((a, b) => a.localeCompare(b, 'ja'));
    // 目的: 部品登録画面のensure Manufacturer Optionを扱う。機能: 入力値を検証・整形し、画面または計算処理へ渡す。入力: 関数シグネチャの値。出力: 処理結果またはなし。動作条件: 部品登録画面の初期化後に呼び出す。副作用: Vue状態、localStorage、DOM、HTTP通信のいずれかを更新する場合がある。
    const ensureManufacturerOption = (name) => {
        if (!name) return;
        manufacturerOptions.value = normalizeUniqueNames([...manufacturerOptions.value, name]);
    };
    // 目的: 部品登録画面のmerge Component Series Detailを扱う。機能: 入力値を検証・整形し、画面または計算処理へ渡す。入力: 関数シグネチャの値。出力: 処理結果またはなし。動作条件: 部品登録画面の初期化後に呼び出す。副作用: Vue状態、localStorage、DOM、HTTP通信のいずれかを更新する場合がある。
    const mergeComponentSeriesDetail = (series) => {
        if (!series?.id) return;
        const exists = componentSeriesOptions.value.some((item) => Number(item.id) === Number(series.id));
        componentSeriesOptions.value = exists
            ? componentSeriesOptions.value.map((item) => Number(item.id) === Number(series.id) ? { ...item, ...series } : item)
            : [...componentSeriesOptions.value, series];
    };
    // 目的: 部品登録画面のfetch Component Series Optionsを扱う。機能: 入力値を検証・整形し、画面または計算処理へ渡す。入力: 関数シグネチャの値。出力: 処理結果またはなし。動作条件: 部品登録画面の初期化後に呼び出す。副作用: Vue状態、localStorage、DOM、HTTP通信のいずれかを更新する場合がある。
    const fetchComponentSeriesOptions = async () => {
        componentSeriesLoading.value = true;
        componentSeriesLoadError.value = '';
        try {
            const res = await api.get('/component-series');
            componentSeriesOptions.value = res.data ?? [];
        } catch {
            componentSeriesOptions.value = [];
            componentSeriesLoadError.value = '部品シリーズを取得できませんでした';
        } finally {
            componentSeriesLoading.value = false;
        }
    };
    // 目的: 部品登録画面のensure Component Series Detailを扱う。機能: 入力値を検証・整形し、画面または計算処理へ渡す。入力: 関数シグネチャの値。出力: 処理結果またはなし。動作条件: 部品登録画面の初期化後に呼び出す。副作用: Vue状態、localStorage、DOM、HTTP通信のいずれかを更新する場合がある。
    const ensureComponentSeriesDetail = async (seriesId) => {
        if (!seriesId) return;
        const current = componentSeriesOptions.value.find((item) => Number(item.id) === Number(seriesId));
        if (Array.isArray(current?.values)) return;

        try {
            const res = await api.get(`/component-series/${seriesId}`);
            mergeComponentSeriesDetail(res.data);
        } catch {
            toastError('部品シリーズの値候補を取得できませんでした');
        }
    };

    const specs = useComponentCreateSpecs({
        form, categories, specTypes, specGroups, specSuggestionTypes, specTemplates,
        selectedSpecGroupId, selectedSpecCandidateId, selectedSpecTemplateId,
        specTypeSearchQuery, specSuggestionLoading, helperSpecGroups,
        toastSuccess, toastError, canCreateSpecType,
    });
    const {
        specProfileOptions, inlineSpecTypeModal, normalizeInlinePrefixes, sanitizeInlinePrefixesForUnit,
        inlinePrefixOptionsFor, inlinePrefixPolicyHelp, syncInlinePrefixList, removeSpec,
        getUnitSuggestions, specDisplayName, specProfileBadge, specProfileControlLabel, specProfileHelpText,
        specTypeOptionLabel, specTypeShortLabel, templateItemSpecType, templateItemProfile, templateItemUnit,
        specTemplateLabel, hasSpecTypeRow, isToleranceSpecType, toleranceSettingsForSpecType,
        isToleranceSpecRow, toleranceUnitOptionsFor, toleranceValuePlaceholder, toleranceGradeOptionsFor,
        toleranceGradeOptionLabel, isToleranceGradeMenuOpen, closeToleranceGradeMenu, toggleToleranceGradeMenu,
        selectToleranceGradeOption, hasSpecBaseUnit, syncNormalSpecUnitToBase, prepareSpecDraftForEdit,
        handleSpecTypeSelection, sortSpecTypes, openInlineSpecTypeModal, closeInlineSpecTypeModal,
        changeSpecProfile, normalizeHelperText, matchByName, specTypeSearchText, findCategoryById,
        findPackageById, findSpecTypeById, specPreview, fetchSpecSuggestionsForForm, selectedSpecGroupLabel,
        scopedSpecTypes, specTypePickerOptionLabel, filteredSpecTypesForPicker, showRecommendedSpecTypes,
        showAllSpecTypes, visibleSpecTemplates, selectedSpecTemplate, selectedSpecTemplateItems,
        templateItemPreviewLabel, handleSpecGroupPickerChange, addSelectedSpecCandidate, applySpecTemplate,
        applySelectedSpecTemplate, createSpecTypeFromDraft, fetchSpecGroupCatalog, ensureSpecGroupDetail,
        specGroupOptions,
    } = specs;

    // 目的: 部品登録画面のadd Custom Attributeを扱う。機能: 入力値を検証・整形し、画面または計算処理へ渡す。入力: 関数シグネチャの値。出力: 処理結果またはなし。動作条件: 部品登録画面の初期化後に呼び出す。副作用: Vue状態、localStorage、DOM、HTTP通信のいずれかを更新する場合がある。
    const addCustomAttribute = () => form.custom_attributes.push({ key: '', value: '' });
    // 目的: 部品登録画面のremove Custom Attributeを扱う。機能: 入力値を検証・整形し、画面または計算処理へ渡す。入力: 関数シグネチャの値。出力: 処理結果またはなし。動作条件: 部品登録画面の初期化後に呼び出す。副作用: Vue状態、localStorage、DOM、HTTP通信のいずれかを更新する場合がある。
    const removeCustomAttribute = (i) => form.custom_attributes.splice(i, 1);

    // 仕入先操作
    // 目的: 部品登録画面のcreate Supplier Rowを扱う。機能: 入力値を検証・整形し、画面または計算処理へ渡す。入力: 関数シグネチャの値。出力: 表示値、配列、オブジェクト、数値のいずれか。動作条件: 部品登録画面の初期化後に呼び出す。副作用: なし。
    const createSupplierRow = () => ({
        supplier_id: '',
        supplier_name: '',
        supplier_part_number: '',
        product_url: '',
        purchase_unit: '',
        unit_price: '',
        is_preferred: false,
        price_breaks: [],
    });
    // 目的: 部品登録画面のadd Supplierを扱う。機能: 入力値を検証・整形し、画面または計算処理へ渡す。入力: 関数シグネチャの値。出力: 処理結果またはなし。動作条件: 部品登録画面の初期化後に呼び出す。副作用: Vue状態、localStorage、DOM、HTTP通信のいずれかを更新する場合がある。
    const addSupplier = () => form.supplierRows.push(createSupplierRow());
    // 目的: 部品登録画面のremove Supplierを扱う。機能: 入力値を検証・整形し、画面または計算処理へ渡す。入力: 関数シグネチャの値。出力: 処理結果またはなし。動作条件: 部品登録画面の初期化後に呼び出す。副作用: Vue状態、localStorage、DOM、HTTP通信のいずれかを更新する場合がある。
    const removeSupplier = (i) => form.supplierRows.splice(i, 1);
    // 目的: 部品登録画面のadd Price Breakを扱う。機能: 入力値を検証・整形し、画面または計算処理へ渡す。入力: 関数シグネチャの値。出力: 処理結果またはなし。動作条件: 部品登録画面の初期化後に呼び出す。副作用: Vue状態、localStorage、DOM、HTTP通信のいずれかを更新する場合がある。
    const addPriceBreak = (row) => row.price_breaks.push({ min_qty: 1, unit_price: '' });
    // 目的: 部品登録画面のremove Price Breakを扱う。機能: 入力値を検証・整形し、画面または計算処理へ渡す。入力: 関数シグネチャの値。出力: 処理結果またはなし。動作条件: 部品登録画面の初期化後に呼び出す。副作用: Vue状態、localStorage、DOM、HTTP通信のいずれかを更新する場合がある。
    const removePriceBreak = (row, i) => row.price_breaks.splice(i, 1);

    // 目的: 部品登録画面のcreate Masterを扱う。機能: 入力値を検証・整形し、画面または計算処理へ渡す。入力: 関数シグネチャの値。出力: 表示値、配列、オブジェクト、数値のいずれか。動作条件: 部品登録画面の初期化後に呼び出す。副作用: なし。
    const createMaster = async (type, name, extra = {}) => {
        const trimmed = name.trim();
        if (!trimmed) return null;
        try {
            const endpoints = { category: '/spec-groups', package: '/packages', supplier: '/suppliers' };
            const res = await api.post(endpoints[type], { name: trimmed, ...extra });
            toastSuccess(`追加しました: ${trimmed}`);
            return res.data;
        } catch (e) {
            toastError(e.message);
            return null;
        }
    };

    // 目的: 部品登録画面のselect Manufacturerを扱う。機能: 入力値を検証・整形し、画面または計算処理へ渡す。入力: 関数シグネチャの値。出力: 処理結果またはなし。動作条件: 部品登録画面の初期化後に呼び出す。副作用: Vue状態、localStorage、DOM、HTTP通信のいずれかを更新する場合がある。
    const selectManufacturer = (name) => {
        form.manufacturer = name;
        manufacturerQuery.value = name;
        ensureManufacturerOption(name);
        manufacturerSuggestionsOpen.value = false;
    };
    // 目的: 部品登録画面のcommit Manufacturerを扱う。機能: 入力値を検証・整形し、画面または計算処理へ渡す。入力: 関数シグネチャの値。出力: 処理結果またはなし。動作条件: 部品登録画面の初期化後に呼び出す。副作用: Vue状態、localStorage、DOM、HTTP通信のいずれかを更新する場合がある。
    const commitManufacturer = () => {
        const trimmed = manufacturerQuery.value.trim();
        form.manufacturer = trimmed;
        ensureManufacturerOption(trimmed);
        manufacturerSuggestionsOpen.value = false;
    };

    // 目的: 部品登録画面のtoggle Categoryを扱う。機能: 入力値を検証・整形し、画面または計算処理へ渡す。入力: 関数シグネチャの値。出力: 処理結果またはなし。動作条件: 部品登録画面の初期化後に呼び出す。副作用: なし。
    const toggleCategory = (id) => {
        const exists = form.category_ids.includes(id);
        form.category_ids = exists
            ? form.category_ids.filter((value) => value !== id)
            : [...form.category_ids, id];
    };
    // 目的: 部品登録画面のselect Packageを扱う。機能: 入力値を検証・整形し、画面または計算処理へ渡す。入力: 関数シグネチャの値。出力: 処理結果またはなし。動作条件: 部品登録画面の初期化後に呼び出す。副作用: Vue状態、localStorage、DOM、HTTP通信のいずれかを更新する場合がある。
    const selectPackage = (id) => {
        form.package_id = id;
    };
    // 目的: 部品登録画面のadd Category From Queryを扱う。機能: 入力値を検証・整形し、画面または計算処理へ渡す。入力: 関数シグネチャの値。出力: 処理結果またはなし。動作条件: 部品登録画面の初期化後に呼び出す。副作用: Vue状態、localStorage、DOM、HTTP通信のいずれかを更新する場合がある。
    const addCategoryFromQuery = async () => {
        const created = await createMaster('category', categoryQuery.value);
        if (!created) return;
        categories.value = [...categories.value, created].sort((a, b) => a.name.localeCompare(b.name, 'ja'));
        toggleCategory(created.id);
        categoryQuery.value = '';
    };
    // 目的: 部品登録画面のadd Package From Queryを扱う。機能: 入力値を検証・整形し、画面または計算処理へ渡す。入力: 関数シグネチャの値。出力: 処理結果またはなし。動作条件: 部品登録画面の初期化後に呼び出す。副作用: Vue状態、localStorage、DOM、HTTP通信のいずれかを更新する場合がある。
    const addPackageFromQuery = async () => {
        const created = await createMaster('package', packageQuery.value, { package_group_id: form.package_group_id });
        if (!created) return;
        packages.value = [...packages.value, created].sort((a, b) => a.name.localeCompare(b.name, 'ja'));
        form.package_id = created.id;
        packageQuery.value = '';
    };

    // 目的: 部品登録画面のfiltered Suppliers For Rowを扱う。機能: 入力値を検証・整形し、画面または計算処理へ渡す。入力: 関数シグネチャの値。出力: 処理結果またはなし。動作条件: 部品登録画面の初期化後に呼び出す。副作用: Vue状態、localStorage、DOM、HTTP通信のいずれかを更新する場合がある。
    const filteredSuppliersForRow = (row) => {
        const q = (row.supplier_name ?? '').trim().toLowerCase();
        if (!q) return suppliers.value.slice(0, 8);
        return suppliers.value.filter((item) => item.name.toLowerCase().includes(q)).slice(0, 8);
    };
    // 目的: 部品登録画面のcan Create Supplier For Rowを扱う。機能: 入力値を検証・整形し、画面または計算処理へ渡す。入力: 関数シグネチャの値。出力: 真偽値。動作条件: 部品登録画面の初期化後に呼び出す。副作用: Vue状態、localStorage、DOM、HTTP通信のいずれかを更新する場合がある。
    const canCreateSupplierForRow = (row) => {
        if (!canCreateSupplier.value) return false;
        const q = (row.supplier_name ?? '').trim();
        if (!q) return false;
        return !suppliers.value.some((item) => item.name.toLowerCase() === q.toLowerCase());
    };
    // 目的: 部品登録画面のselect Supplierを扱う。機能: 入力値を検証・整形し、画面または計算処理へ渡す。入力: 関数シグネチャの値。出力: 処理結果またはなし。動作条件: 部品登録画面の初期化後に呼び出す。副作用: Vue状態、localStorage、DOM、HTTP通信のいずれかを更新する場合がある。
    const selectSupplier = (row, supplier) => {
        row.supplier_id = supplier.id;
        row.supplier_name = supplier.name;
    };
    // 目的: 部品登録画面のcommit Supplierを扱う。機能: 入力値を検証・整形し、画面または計算処理へ渡す。入力: 関数シグネチャの値。出力: 処理結果またはなし。動作条件: 部品登録画面の初期化後に呼び出す。副作用: Vue状態、localStorage、DOM、HTTP通信のいずれかを更新する場合がある。
    const commitSupplier = async (row) => {
        const trimmed = (row.supplier_name ?? '').trim();
        if (!trimmed) {
            row.supplier_id = '';
            return;
        }
        const existing = suppliers.value.find((item) => item.name.toLowerCase() === trimmed.toLowerCase());
        if (existing) {
            selectSupplier(row, existing);
            return;
        }
        const created = await createMaster('supplier', trimmed);
        if (!created) return;
        suppliers.value = [...suppliers.value, created].sort((a, b) => a.name.localeCompare(b.name, 'ja'));
        selectSupplier(row, created);
    };

    // 目的: 部品登録画面のrevoke Preview Urlを扱う。機能: 入力値を検証・整形し、画面または計算処理へ渡す。入力: 関数シグネチャの値。出力: 処理結果またはなし。動作条件: 部品登録画面の初期化後に呼び出す。副作用: Vue状態、localStorage、DOM、HTTP通信のいずれかを更新する場合がある。
    const revokePreviewUrl = () => {
        if (imagePreviewUrl.value?.startsWith('blob:')) {
            URL.revokeObjectURL(imagePreviewUrl.value);
        }
    };

    // 目的: 部品登録画面のon Image Changeを扱う。機能: 入力値を検証・整形し、画面または計算処理へ渡す。入力: 関数シグネチャの値。出力: 処理結果またはなし。動作条件: 部品登録画面の初期化後に呼び出す。副作用: Vue状態、localStorage、DOM、HTTP通信のいずれかを更新する場合がある。
    const onImageChange = (event) => {
        const [file] = event.target.files ?? [];
        imageFile.value = file ?? null;
        revokePreviewUrl();
        imagePreviewUrl.value = file ? URL.createObjectURL(file) : (currentImageUrl.value || '');
    };

    // 目的: 部品登録画面のon Datasheet Changeを扱う。機能: 入力値を検証・整形し、画面または計算処理へ渡す。入力: 関数シグネチャの値。出力: 処理結果またはなし。動作条件: 部品登録画面の初期化後に呼び出す。副作用: Vue状態、localStorage、DOM、HTTP通信のいずれかを更新する場合がある。
    const onDatasheetChange = async (event) => {
        await clearChatGptTempDatasheets();
        datasheetFiles.value = Array.from(event.target.files ?? []);
        datasheetLabels.value = datasheetFiles.value.map((_, index) => datasheetLabels.value[index] ?? '');
        datasheetTargetIndex.value = 0;
        resetChatGptJobState();
    };

    // 目的: 部品登録画面のcreate Datasheet Draftを扱う。機能: 入力値を検証・整形し、画面または計算処理へ渡す。入力: 関数シグネチャの値。出力: 表示値、配列、オブジェクト、数値のいずれか。動作条件: 部品登録画面の初期化後に呼び出す。副作用: なし。
    const createDatasheetDraft = (sheet = {}) => ({
        id: sheet.id ?? '',
        original_name: sheet.original_name ?? sheet.name ?? '',
        display_name: sheet.display_name ?? '',
        url: sheet.url ?? '',
    });

    const chatGptConfig = {
        CHATGPT_HELPER_MIN_VERSION, CHATGPT_WINDOW_NAME, BITSKEEP_WINDOW_NAME,
        CHATGPT_HELPER_RECHECK_STORAGE_KEY, CHATGPT_ACTIVE_JOB_STORAGE_KEY,
        CHATGPT_STATUS_STALE_MS, CHATGPT_QUEUED_STALE_MS, CHATGPT_WAITING_STALE_MS,
        CHATGPT_WORKER_STALE_MS, CHATGPT_WORKER_READY_WAIT_MS, CHATGPT_WORKER_READY_POLL_MS,
        CHATGPT_TEMP_CLEANUP_TIMEOUT_MS, CHATGPT_JOB_CREATE_TIMEOUT_MS,
    };
    const chatGptRuntime = {
        tampermonkeyPollTimer: null,
        chatGptHelperPromptTimer: null,
        chatGptJobWatchdogTimer: null,
    };
    // 目的: 部品登録画面のget Chat Gpt Runtimeを扱う。機能: 入力値を検証・整形し、画面または計算処理へ渡す。入力: 関数シグネチャの値。出力: 表示値、配列、オブジェクト、数値のいずれか。動作条件: 部品登録画面の初期化後に呼び出す。副作用: なし。
    const getChatGptRuntime = (key) => chatGptRuntime[key];
    // 目的: 部品登録画面のset Chat Gpt Runtimeを扱う。機能: 入力値を検証・整形し、画面または計算処理へ渡す。入力: 関数シグネチャの値。出力: 処理結果またはなし。動作条件: 部品登録画面の初期化後に呼び出す。副作用: Vue状態、localStorage、DOM、HTTP通信のいずれかを更新する場合がある。
    const setChatGptRuntime = (key, value) => { chatGptRuntime[key] = value; };
    const ai = useComponentCreateAi({
        form, dirty, datasheetFiles, datasheetLabels, datasheetTargetIndex, currentDatasheets,
        inlineSpecTypeModal, manufacturerQuery, helperSpecGroups, helperSpecTemplates, helperSuggestionLoading,
        categories, packages, specTypes, toastSuccess, toastError, ensureManufacturerOption,
        findCategoryById, findPackageById, findSpecTypeById, matchByName, normalizeHelperText,
        specTypeSearchText, prepareSpecDraftForEdit, templateItemSpecType, templateItemProfile,
        templateItemUnit, hasSpecTypeRow, applyToleranceDefaults: specs.applyToleranceDefaults,
        syncNormalSpecUnitToBase, chatGptConfig, logChatGptFlow, getChatGptRuntime, setChatGptRuntime,
    });
    const {
        analyzing, helperResult, helperResultSummary, showHelperResultModal, applyHelperTemplate,
        helperFilteredPackages, analyzeDatasheet, openHelperResultModal, closeHelperResultModal,
        discardHelperResult, applyHelperResult, addHelperCategory, removeHelperCategory, handleHelperCategorySelection,
        addHelperPackage, removeHelperPackage, addHelperSpec, removeHelperSpec, handleHelperSpecTypeSelection,
        handleHelperPackageGroupChange, handleHelperPackageSelection, showDatasheetManagerModal,
        openDatasheetManager, closeDatasheetManager, confirmDatasheetTargetSelection, datasheetTargetLabel,
        hasDatasheetForAi, isChatGptHelperVersionCompatible, isChatGptJobBusy, canDismissChatGptRun,
        showChatGptRunModal, showChatGptHelperUpdateModal, chatGptHelperIssue, chatGptHelperCheckStatus,
        chatGptHelperCheckMessage, openChatGptHelperUpdateModal, closeChatGptHelperUpdateModal,
        reloadForChatGptHelperUpdate, showChatGPTPaste, chatGPTPasteText, chatGPTPasteTextarea,
        openChatGPTPaste, beginAiAction, openChatGptRun, closeChatGptRun, startChatGPTAutoFill,
        parseChatGPTResult, dismissChatGPTPaste, chatGptGuideReason, openPasteFallbackFromGuide,
        copyChatGptFallbackText, hardResetChatGptJob, chatGptStatusChips, chatGptStepStates,
        canStartChatGptAutoFill, showChatGptRunHint, chatGptJob, clearChatGptTempDatasheets,
        initializeChatGptBridge, cleanupChatGptBridge,
    } = ai;

    /**
     * 現在の入力状態を部品保存APIへ渡すFormDataへ変換する。
     * 入力はform、画像、データシート、既存PDF表示名で、戻り値はmultipart送信用payload。
     * 動作条件は保存前バリデーション通過後で、データ作成だけを行い通信副作用は持たない。
     */
    // 目的: 部品登録画面のbuild Payloadを扱う。機能: 入力値を検証・整形し、画面または計算処理へ渡す。入力: 関数シグネチャの値。出力: 表示値、配列、オブジェクト、数値のいずれか。動作条件: 部品登録画面の初期化後に呼び出す。副作用: なし。
    const buildPayload = () => {
        const payload = new FormData();

        payload.append('part_number', form.part_number ?? '');
        payload.append('manufacturer', form.manufacturer ?? '');
        payload.append('common_name', form.common_name ?? '');
        payload.append('description', form.description ?? '');
        payload.append('procurement_status', form.procurement_status ?? 'active');
        payload.append('threshold_new', String(form.threshold_new ?? 0));
        payload.append('threshold_used', String(form.threshold_used ?? 0));
        payload.append('primary_location_id', form.primary_location_id ? String(form.primary_location_id) : '');

        form.category_ids.forEach((categoryId, index) => {
            payload.append(`category_ids[${index}]`, String(categoryId));
        });
        payload.append('package_group_id', form.package_group_id ? String(form.package_group_id) : '');
        payload.append('package_id', form.package_id ? String(form.package_id) : '');
        payload.append('component_series_id', componentRegistrationMode.value === 'series' && form.component_series_id ? String(form.component_series_id) : '');
        payload.append('component_series_value_id', componentRegistrationMode.value === 'series' && form.component_series_value_id ? String(form.component_series_value_id) : '');
        form.specs.forEach((spec, index) => {
            const specPayload = buildSpecPayload(spec);
            payload.append(`specs[${index}][spec_type_id]`, String(specPayload.spec_type_id ?? ''));
            payload.append(`specs[${index}][value_profile]`, specPayload.value_profile ?? 'typ');
            payload.append(`specs[${index}][value]`, specPayload.value ?? '');
            payload.append(`specs[${index}][value_typ]`, specPayload.value_typ ?? '');
            payload.append(`specs[${index}][value_min]`, specPayload.value_min ?? '');
            payload.append(`specs[${index}][value_max]`, specPayload.value_max ?? '');
            payload.append(`specs[${index}][unit]`, specPayload.unit ?? '');
        });
        form.custom_attributes.forEach((attr, index) => {
            payload.append(`attributes[${index}][key]`, attr.key ?? '');
            payload.append(`attributes[${index}][value]`, attr.value ?? '');
        });
        payload.append('altium[sch_library_id]', form.altium.sch_library_id ? String(form.altium.sch_library_id) : '');
        payload.append('altium[sch_symbol]', form.altium.sch_symbol ?? '');
        payload.append('altium[pcb_library_id]', form.altium.pcb_library_id ? String(form.altium.pcb_library_id) : '');
        payload.append('altium[pcb_footprint]', form.altium.pcb_footprint ?? '');
        form.supplierRows.forEach((row, index) => {
            payload.append(`suppliers[${index}][supplier_id]`, String(row.supplier_id ?? ''));
            payload.append(`suppliers[${index}][supplier_part_number]`, row.supplier_part_number ?? '');
            payload.append(`suppliers[${index}][product_url]`, row.product_url ?? '');
            payload.append(`suppliers[${index}][purchase_unit]`, row.purchase_unit ?? '');
            payload.append(`suppliers[${index}][unit_price]`, row.unit_price === '' || row.unit_price === null ? '' : String(row.unit_price));
            payload.append(`suppliers[${index}][is_preferred]`, row.is_preferred ? '1' : '0');

            row.price_breaks.forEach((priceBreak, priceBreakIndex) => {
                payload.append(`suppliers[${index}][price_breaks][${priceBreakIndex}][min_qty]`, String(priceBreak.min_qty ?? 1));
                payload.append(`suppliers[${index}][price_breaks][${priceBreakIndex}][unit_price]`, priceBreak.unit_price === '' || priceBreak.unit_price === null ? '' : String(priceBreak.unit_price));
            });
        });

        if (imageFile.value) {
            payload.append('image', imageFile.value);
        }
        if (duplicateFromId && !isEdit) {
            payload.append('duplicate_from_component_id', duplicateFromId);
        }

        if (chatGptTempDatasheets.value.length) {
            chatGptTempDatasheets.value.forEach((sheet, index) => {
                payload.append(`temp_datasheet_tokens[${index}]`, sheet.token);
                payload.append(`temp_datasheet_labels[${index}]`, datasheetLabels.value[index] ?? sheet.display_name ?? '');
            });
        } else {
            datasheetFiles.value.forEach((file, index) => {
                payload.append(`datasheets[${index}]`, file);
                payload.append(`datasheet_labels[${index}]`, datasheetLabels.value[index] ?? '');
            });
        }

        if (isEdit && datasheetFiles.value.length === 0 && chatGptTempDatasheets.value.length === 0) {
            currentDatasheets.value.forEach((sheet, index) => {
                payload.append(`existing_datasheets[${index}][id]`, String(sheet.id ?? ''));
                payload.append(`existing_datasheets[${index}][display_name]`, sheet.display_name ?? '');
            });
        }

        return payload;
    };

    // 目的: 部品登録画面のvalidate Before Submitを扱う。機能: 入力値を検証・整形し、画面または計算処理へ渡す。入力: 関数シグネチャの値。出力: 処理結果またはなし。動作条件: 部品登録画面の初期化後に呼び出す。副作用: Vue状態、localStorage、DOM、HTTP通信のいずれかを更新する場合がある。
    const validateBeforeSubmit = () => {
        const missingSpecTypeRows = form.specs
            .map((spec, index) => ({ spec, index }))
            .filter(({ spec }) => !spec.spec_type_id);

        if (missingSpecTypeRows.length > 0) {
            const labels = missingSpecTypeRows
                .slice(0, 4)
                .map(({ spec, index }) => `${index + 1}行目${spec.spec_type_name || spec.name_ja || spec.name ? `「${spec.spec_type_name || spec.name_ja || spec.name}」` : ''}`);
            const suffix = missingSpecTypeRows.length > labels.length ? ` ほか${missingSpecTypeRows.length - labels.length}件` : '';
            toastError(`スペック詳細が未選択です: ${labels.join('、')}${suffix}`);
            return false;
        }

        return true;
    };

    // 目的: 部品登録画面のresolve Spec Types Before Submitを扱う。機能: 入力値を検証・整形し、画面または計算処理へ渡す。入力: 関数シグネチャの値。出力: 表示値、配列、オブジェクト、数値のいずれか。動作条件: 部品登録画面の初期化後に呼び出す。副作用: なし。
    const resolveSpecTypesBeforeSubmit = async () => {
        for (const spec of form.specs) {
            handleSpecTypeSelection(spec);
        }
    };

    /**
     * 部品の新規登録または編集保存を実行する。
     * 入力は画面全体のフォーム状態で、出力は保存成功後の詳細画面遷移。
     * 動作条件はスペック詳細が確定済みであること、部品APIが利用可能なことで、API送信、トースト、dirty解除、location変更の副作用を持つ。
     */
    // 目的: 部品登録画面のsubmitを扱う。機能: 入力値を検証・整形し、画面または計算処理へ渡す。入力: 関数シグネチャの値。出力: 処理結果またはなし。動作条件: 部品登録画面の初期化後に呼び出す。副作用: Vue状態、localStorage、DOM、HTTP通信のいずれかを更新する場合がある。
    const submit = async () => {
        saving.value = true;

        try {
            await resolveSpecTypesBeforeSubmit();
            if (!validateBeforeSubmit()) return;

            const payload = buildPayload();
            if (isEdit) {
                await api.uploadPut(`/components/${editId}`, payload);
                toastSuccess('更新しました');
                dirty.value = false;
                setTimeout(() => { location.href = `/components/${editId}`; }, 800);
            } else {
                const res = await api.upload('/components', payload);
                toastSuccess('登録しました');
                dirty.value = false;
                setTimeout(() => { location.href = `/components/${res.data.id}`; }, 800);
            }
        } catch (e) {
            toastError(e.message);
        } finally {
            saving.value = false;
        }
    };

    // 初期ロード
    onMounted(async () => {
        window.addEventListener('click', closeToleranceGradeMenu);
        const [catRes, groupRes, pkgRes, stRes, supRes, compRes, locRes, altiumRes] = await Promise.all([
            api.get('/spec-groups'), api.get('/package-groups'), api.get('/packages'),
            api.get('/spec-types'), api.get('/suppliers'),
            api.get('/components?per_page=100'),
            api.get('/locations'),
            api.get('/altium/libraries'),
        ]).catch(() => {
            masterLoadError.value = '初期データの取得に失敗しました。再読込するか、マスタ管理を確認してください。';
            return [{ data: [] }, { data: [] }, { data: [] }, { data: [] }, { data: [] }, { data: { data: [] } }, { data: [] }, { data: [] }];
        });

        categories.value = catRes.data ?? [];
        packageGroups.value = groupRes.data ?? [];
        packages.value   = pkgRes.data ?? [];
        specTypes.value  = stRes.data  ?? [];
        suppliers.value  = supRes.data ?? [];
        locations.value  = locRes.data ?? [];
        altiumLibraries.value = altiumRes.data ?? [];
        manufacturerOptions.value = normalizeUniqueNames((compRes.data?.data ?? []).map((item) => item.manufacturer));
        void fetchSpecGroupCatalog();
        void fetchComponentSeriesOptions();

        // 編集モードなら既存データをロード
        // 目的: 部品登録画面のload Source Componentを扱う。機能: 入力値を検証・整形し、画面または計算処理へ渡す。入力: 関数シグネチャの値。出力: 処理結果またはなし。動作条件: 部品登録画面の初期化後に呼び出す。副作用: Vue状態、localStorage、DOM、HTTP通信のいずれかを更新する場合がある。
        const loadSourceComponent = async (id) => {
            const res = await api.get(`/components/${id}`);
            const p = res.data;
            Object.assign(form, {
                part_number: p.part_number, manufacturer: p.manufacturer ?? '',
                common_name: p.common_name ?? '', description: p.description ?? '',
                procurement_status: p.procurement_status,
                threshold_new: p.threshold_new, threshold_used: p.threshold_used,
                primary_location_id: p.primary_location_id ?? '',
                category_ids: p.categories.map(c => c.id),
                package_group_id: p.package_group?.id ?? p.package?.package_group_id ?? '',
                package_id: p.package?.id ?? p.packages?.[0]?.id ?? '',
                component_series_id: p.component_series_id ?? '',
                component_series_value_id: p.component_series_value_id ?? '',
                specs: p.specs.map((s) => prepareSpecDraftForEdit(buildSpecDraftFromApi(s))),
                custom_attributes: (p.custom_attributes ?? []).map((attr) => ({ key: attr.key ?? '', value: attr.value ?? '' })),
                altium: {
                    sch_library_id: p.altiumLink?.sch_library_id ?? '',
                    sch_symbol: p.altiumLink?.sch_symbol ?? '',
                    pcb_library_id: p.altiumLink?.pcb_library_id ?? '',
                    pcb_footprint: p.altiumLink?.pcb_footprint ?? '',
                },
                supplierRows: p.component_suppliers.map(cs => ({
                    supplier_id: cs.supplier_id, supplier_name: cs.supplier?.name ?? '',
                    supplier_part_number: cs.supplier_part_number ?? '',
                    product_url: cs.product_url ?? '', purchase_unit: cs.purchase_unit ?? '', unit_price: cs.unit_price ?? '',
                    is_preferred: cs.is_preferred, price_breaks: cs.price_breaks ?? [],
                })),
            });
            syncManufacturerQuery();
            componentRegistrationMode.value = form.component_series_id ? 'series' : 'single';
            if (form.component_series_id) {
                void ensureComponentSeriesDetail(form.component_series_id);
            }
            ensureManufacturerOption(form.manufacturer);
            currentImageUrl.value = p.image_url ?? '';
            currentDatasheets.value = (p.datasheets ?? []).map((sheet) => ({
                ...createDatasheetDraft({
                    id: sheet.id,
                    original_name: sheet.original_name || sheet.file_path.split('/').pop(),
                    display_name: sheet.display_name ?? '',
                    url: sheet.url,
                }),
            }));
            imagePreviewUrl.value = currentImageUrl.value;
        };

        if (isEdit) {
            try {
                await loadSourceComponent(editId);
            } catch { toastError('部品情報の取得に失敗しました'); }
        } else if (duplicateFromId) {
            try {
                await loadSourceComponent(duplicateFromId);
                form.part_number = '';
                form.common_name = form.common_name ? `${form.common_name} コピー` : '';
                toastSuccess('複製元を読み込みました。型番と差分だけ調整してください。');
            } catch {
                toastError('複製元部品の取得に失敗しました');
            }
        } else {
            syncManufacturerQuery();
        }

        initialSnapshot.value = JSON.stringify({
            form,
            datasheets: currentDatasheets.value.map((sheet) => ({ id: sheet.id, display_name: sheet.display_name })),
        });

        initializeChatGptBridge();
    });

    onBeforeUnmount(() => {
        window.removeEventListener('click', closeToleranceGradeMenu);
        cleanupChatGptBridge();
    });


    watch(() => JSON.stringify({
        form,
        datasheets: currentDatasheets.value.map((sheet) => ({ id: sheet.id, display_name: sheet.display_name })),
    }), (snapshot) => {
        if (!initialSnapshot.value || saving.value) return;
        dirty.value = snapshot !== initialSnapshot.value || !!imageFile.value || datasheetFiles.value.length > 0;
    });

    watch(() => form.package_group_id, (groupId, previousGroupId) => {
        if (!groupId) {
            form.package_id = '';
            packageQuery.value = '';
            return;
        }

        const selectedPackage = packages.value.find((item) => item.id === Number(form.package_id));
        if (!selectedPackage || selectedPackage.package_group_id !== Number(groupId)) {
            form.package_id = '';
        }

        if (groupId !== previousGroupId) {
            packageQuery.value = '';
        }
    });

    watch(componentRegistrationMode, (mode) => {
        if (mode !== 'series') {
            form.component_series_id = '';
            form.component_series_value_id = '';
        }
    });

    watch(() => form.component_series_id, (seriesId) => {
        if (!seriesId) {
            form.component_series_value_id = '';
            return;
        }

        const currentValue = componentSeriesValueOptions.value.find((item) => Number(item.id) === Number(form.component_series_value_id));
        if (!currentValue) {
            form.component_series_value_id = '';
        }
        void ensureComponentSeriesDetail(seriesId);
    });

    watch(() => form.category_ids.map((id) => Number(id)).filter(Boolean).sort((a, b) => a - b).join(','), () => {
        void fetchSpecSuggestionsForForm();
    });

    watch(() => selectedSpecGroupId.value, () => {
        handleSpecGroupPickerChange();
    });

    watch(() => filteredSpecTypesForPicker().map((item) => Number(item.id)).join(','), (ids) => {
        if (!selectedSpecCandidateId.value) return;
        const availableIds = ids.split(',').filter(Boolean);
        if (!availableIds.includes(String(Number(selectedSpecCandidateId.value)))) {
            selectedSpecCandidateId.value = '';
        }
    });

    watch(() => visibleSpecTemplates.value.map((template) => Number(template.id)).join(','), () => {
        const templates = visibleSpecTemplates.value;
        const currentTemplate = templates.find((template) => Number(template.id) === Number(selectedSpecTemplateId.value));
        if (selectedSpecTemplateId.value && !currentTemplate) {
            selectedSpecTemplateId.value = '';
        }
    }, { immediate: true });




    return {
        toasts, isEdit, form, saving, dirty, locations, masterLoadError, canCreateSupplier,
        imagePreviewUrl, currentImageUrl, currentDatasheets, datasheetFiles, datasheetLabels, datasheetTargetIndex,
        categories, packageGroups, packages, specTypes, specGroups, specGroupOptions, specSuggestionTypes, specTemplates, visibleSpecTemplates, specSuggestionLoading, suppliers,
        componentRegistrationMode, componentSeriesOptions, componentSeriesLoading, componentSeriesLoadError, selectedComponentSeries, componentSeriesValueOptions, componentSeriesOptionLabel, fetchComponentSeriesOptions,
        altiumLibraries, schLibraries, pcbLibraries,
        manufacturerQuery, filteredManufacturers, manufacturerExactMatch,
        manufacturerSuggestionsOpen,
        categoryQuery, filteredCategories, canCreateCategory,
        packageQuery, filteredPackages, canCreatePackage,
        specProfileOptions, specProfileBadge, specProfileControlLabel, specProfileHelpText, canCreateSpecType, inlineSpecTypeModal, specTypeOptionLabel, specTypePickerOptionLabel,
        inlinePrefixOptionsFor, inlinePrefixPolicyHelp, syncInlinePrefixList,
        selectedSpecGroupId, selectedSpecGroupLabel, selectedSpecCandidateId, selectedSpecTemplateId, selectedSpecTemplate,
        selectedSpecTemplateItems, scopedSpecTypes, filteredSpecTypesForPicker, specTypeSearchQuery, showRecommendedSpecTypes, showAllSpecTypes, isAllSpecTypesSelected,
        addSelectedSpecCandidate, applySpecTemplate, applySelectedSpecTemplate, specTemplateLabel, templateItemPreviewLabel,
        removeSpec, getUnitSuggestions, hasSpecBaseUnit, specPreview, specDisplayName, handleSpecTypeSelection, openInlineSpecTypeModal, closeInlineSpecTypeModal, saveInlineSpecType, changeSpecProfile,
        isToleranceSpecRow, toleranceUnitOptionsFor, toleranceValuePlaceholder,
        toleranceGradeOptionsFor, toleranceGradeOptionLabel,
        isToleranceGradeMenuOpen, toggleToleranceGradeMenu, closeToleranceGradeMenu, selectToleranceGradeOption,
        addCustomAttribute, removeCustomAttribute,
        addSupplier, removeSupplier, addPriceBreak, removePriceBreak,
        selectManufacturer, commitManufacturer,
        toggleCategory, selectPackage, addCategoryFromQuery, addPackageFromQuery,
        filteredSuppliersForRow, canCreateSupplierForRow, selectSupplier, commitSupplier,
        onImageChange, onDatasheetChange,
        analyzing, helperResult, helperResultSummary, showHelperResultModal,
        helperSpecGroups, helperSpecTemplates, helperSuggestionLoading, applyHelperTemplate,
        helperFilteredPackages,
        analyzeDatasheet, openHelperResultModal, closeHelperResultModal, discardHelperResult, applyHelperResult,
        addHelperCategory, removeHelperCategory, handleHelperCategorySelection,
        addHelperPackage, removeHelperPackage,
        addHelperSpec, removeHelperSpec, handleHelperSpecTypeSelection,
        handleHelperPackageGroupChange, handleHelperPackageSelection,
        showDatasheetManagerModal, openDatasheetManager, closeDatasheetManager, confirmDatasheetTargetSelection,
        datasheetTargetLabel,
        hasDatasheetForAi,
        isChatGptHelperVersionCompatible,
        isChatGptJobBusy, canDismissChatGptRun,
        showChatGptRunModal, showChatGptHelperUpdateModal, chatGptHelperIssue, chatGptHelperCheckStatus, chatGptHelperCheckMessage,
        openChatGptHelperUpdateModal, closeChatGptHelperUpdateModal, reloadForChatGptHelperUpdate,
        showChatGPTPaste, chatGPTPasteText, chatGPTPasteTextarea, openChatGPTPaste, beginAiAction, openChatGptRun, closeChatGptRun, startChatGPTAutoFill,
        parseChatGPTResult, dismissChatGPTPaste,
        chatGptGuideReason, openPasteFallbackFromGuide,
        copyChatGptFallbackText,
        hardResetChatGptJob,
        chatGptStatusChips, chatGptStepStates, canStartChatGptAutoFill, showChatGptRunHint, chatGptJob,
        submit, duplicateFromId,
        renderSymbol,
    };
}
