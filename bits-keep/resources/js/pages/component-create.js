import { ref, reactive, onMounted, onBeforeUnmount, computed, watch, nextTick } from 'vue';
import { api } from '../api.js';
import { useToast } from '../composables/useToast.js';
import { useNavigationConfirm } from '../composables/useNavigationConfirm.js';
import {
    buildSpecDraftFromApi,
    buildSpecPayload,
    createEmptySpecRow,
    getSpecDisplayName,
    getSpecUnitSuggestions,
    normalizeSpecDraft,
    normalizeSpecProfile,
    SPEC_PROFILE_OPTIONS,
} from '../utils/specValue.js';

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
    let chatGptWindowRef = null;
    let lastChatGptHelperLogSignature = '';
    let lastSyncedChatGptStatusAt = '';
    let lastSyncedChatGptResultAt = '';

    const logChatGptFlow = (stage, detail = {}) => {
        console.info('[BitsKeep][ChatGPT Flow]', stage, detail);
        const helper = window.__bitskeepTampermonkeyHelper;
        if (helper?.connected && typeof helper.appendDebugLog === 'function') {
            helper.appendDebugLog(`flow.${stage}`, 'BitsKeep 画面側ログです。', detail);
        }
    };

    // ── マスタデータ ──────────────────────────────────────
    const categories = ref([]);
    const packageGroups = ref([]);
    const packages   = ref([]);
    const specTypes  = ref([]);
    const suppliers  = ref([]);
    const locations  = ref([]);
    const altiumLibraries = ref([]);
    const manufacturerOptions = ref([]);

    // ── フォーム ──────────────────────────────────────────
    const form = reactive({
        part_number: '', manufacturer: '', common_name: '', description: '',
        procurement_status: 'active',
        threshold_new: 0, threshold_used: 0,
        primary_location_id: '',
        category_ids: [],
        package_group_id: '',
        package_id: '',
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
    const specProfileOptions = SPEC_PROFILE_OPTIONS;

    const syncManufacturerQuery = () => {
        manufacturerQuery.value = form.manufacturer ?? '';
    };
    const normalizeUniqueNames = (values) => [...new Set(values.map((value) => String(value).trim()).filter(Boolean))].sort((a, b) => a.localeCompare(b, 'ja'));
    const ensureManufacturerOption = (name) => {
        if (!name) return;
        manufacturerOptions.value = normalizeUniqueNames([...manufacturerOptions.value, name]);
    };

    // ── スペック操作 ──────────────────────────────────────
    const addSpec = () => form.specs.push(createEmptySpecRow());
    const removeSpec = (i) => form.specs.splice(i, 1);
    const getUnitSuggestions = (specTypeId) => getSpecUnitSuggestions(specTypes.value.find(st => st.id == specTypeId));
    const specDisplayName = (spec) => getSpecDisplayName(spec, findSpecTypeById(spec?.spec_type_id));
    const specIdentityKey = (spec) => `${Number(spec?.spec_type_id ?? 0)}:${normalizeSpecProfile(spec?.value_profile)}`;
    const addCustomAttribute = () => form.custom_attributes.push({ key: '', value: '' });
    const removeCustomAttribute = (i) => form.custom_attributes.splice(i, 1);

    // ── 仕入先操作 ────────────────────────────────────────
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
    const addSupplier = () => form.supplierRows.push(createSupplierRow());
    const removeSupplier = (i) => form.supplierRows.splice(i, 1);
    const addPriceBreak = (row) => row.price_breaks.push({ min_qty: 1, unit_price: '' });
    const removePriceBreak = (row, i) => row.price_breaks.splice(i, 1);

    const createMaster = async (type, name, extra = {}) => {
        const trimmed = name.trim();
        if (!trimmed) return null;
        try {
            const endpoints = { category: '/categories', package: '/packages', supplier: '/suppliers' };
            const res = await api.post(endpoints[type], { name: trimmed, ...extra });
            toastSuccess(`追加しました: ${trimmed}`);
            return res.data;
        } catch (e) {
            toastError(e.message);
            return null;
        }
    };

    const selectManufacturer = (name) => {
        form.manufacturer = name;
        manufacturerQuery.value = name;
        ensureManufacturerOption(name);
        manufacturerSuggestionsOpen.value = false;
    };
    const commitManufacturer = () => {
        const trimmed = manufacturerQuery.value.trim();
        form.manufacturer = trimmed;
        ensureManufacturerOption(trimmed);
        manufacturerSuggestionsOpen.value = false;
    };

    const toggleCategory = (id) => {
        const exists = form.category_ids.includes(id);
        form.category_ids = exists
            ? form.category_ids.filter((value) => value !== id)
            : [...form.category_ids, id];
    };
    const selectPackage = (id) => {
        form.package_id = id;
    };
    const addCategoryFromQuery = async () => {
        const created = await createMaster('category', categoryQuery.value);
        if (!created) return;
        categories.value = [...categories.value, created].sort((a, b) => a.name.localeCompare(b.name, 'ja'));
        toggleCategory(created.id);
        categoryQuery.value = '';
    };
    const addPackageFromQuery = async () => {
        const created = await createMaster('package', packageQuery.value, { package_group_id: form.package_group_id });
        if (!created) return;
        packages.value = [...packages.value, created].sort((a, b) => a.name.localeCompare(b.name, 'ja'));
        form.package_id = created.id;
        packageQuery.value = '';
    };

    const filteredSuppliersForRow = (row) => {
        const q = (row.supplier_name ?? '').trim().toLowerCase();
        if (!q) return suppliers.value.slice(0, 8);
        return suppliers.value.filter((item) => item.name.toLowerCase().includes(q)).slice(0, 8);
    };
    const canCreateSupplierForRow = (row) => {
        if (!canCreateSupplier.value) return false;
        const q = (row.supplier_name ?? '').trim();
        if (!q) return false;
        return !suppliers.value.some((item) => item.name.toLowerCase() === q.toLowerCase());
    };
    const selectSupplier = (row, supplier) => {
        row.supplier_id = supplier.id;
        row.supplier_name = supplier.name;
    };
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

    const revokePreviewUrl = () => {
        if (imagePreviewUrl.value?.startsWith('blob:')) {
            URL.revokeObjectURL(imagePreviewUrl.value);
        }
    };

    const onImageChange = (event) => {
        const [file] = event.target.files ?? [];
        imageFile.value = file ?? null;
        revokePreviewUrl();
        imagePreviewUrl.value = file ? URL.createObjectURL(file) : (currentImageUrl.value || '');
    };

    const onDatasheetChange = async (event) => {
        await clearChatGptTempDatasheets();
        datasheetFiles.value = Array.from(event.target.files ?? []);
        datasheetLabels.value = datasheetFiles.value.map((_, index) => datasheetLabels.value[index] ?? '');
        datasheetTargetIndex.value = 0;
        resetChatGptJobState();
    };

    const createDatasheetDraft = (sheet = {}) => ({
        id: sheet.id ?? '',
        original_name: sheet.original_name ?? sheet.name ?? '',
        display_name: sheet.display_name ?? '',
        url: sheet.url ?? '',
    });

    // ── データシート解析ヘルパー共通 ─────────────────────────
    const analyzing = ref(false);
    const helperResult = ref(null);
    const showDatasheetManagerModal = ref(false);
    const showChatGptRunModal = ref(false);
    const showHelperResultModal = ref(false);
    const showChatGptHelperUpdateModal = ref(false);
    const chatGptHelperCheckStatus = ref('idle');
    const chatGptHelperCheckMessage = ref('');
    const chatGptGuideReason = ref('');
    const pendingAiAction = ref('');
    const chatGptTempDatasheets = ref([]);
    const chatGptJob = reactive({
        connected: false,
        helperVersion: '',
        jobId: '',
        state: 'idle',
        detail: '',
        error: '',
        updatedAt: '',
    });
    const chatGptWorkerHeartbeat = ref(null);
    let tampermonkeyPollTimer = null;
    let chatGptHelperPromptTimer = null;
    let chatGptJobWatchdogTimer = null;
    let chatGptHelperPromptShown = false;
    const chatGptUiLockSuppressed = ref(false);
    const isChatGptJobRunning = computed(() => ['preparing', 'opening', 'waiting'].includes(chatGptJob.state));
    const isChatGptJobBusy = computed(() => isChatGptJobRunning.value && !chatGptUiLockSuppressed.value);
    const canDismissChatGptRun = computed(() => !isChatGptJobBusy.value);
    const anyModalOpen = computed(() => (
        isChatGptJobBusy.value
        || analyzing.value
        || showDatasheetManagerModal.value
        || showChatGptRunModal.value
        || showHelperResultModal.value
        || showChatGptHelperUpdateModal.value
        || showChatGPTPaste.value
    ));
    const syncScrollLock = (isLocked) => {
        document.documentElement.classList.toggle('modal-open', !!isLocked);
        document.body.classList.toggle('modal-open', !!isLocked);
    };
    const releaseScrollLockIfNoModal = () => {
        if (!anyModalOpen.value) {
            syncScrollLock(false);
        }
    };
    const helperResultSummary = computed(() => {
        if (!helperResult.value) return null;

        const basicCount = [
            helperResult.value.part_number?.value,
            helperResult.value.manufacturer?.value,
            helperResult.value.common_name?.value,
            helperResult.value.description?.value,
        ].filter((value) => String(value ?? '').trim() !== '').length;

        const categoryCount = (helperResult.value.categories ?? [])
            .filter((item) => String(item.name ?? '').trim() !== '' || item.category_id)
            .length;

        const packageCount = (helperResult.value.packages ?? [])
            .filter((item) => String(item.name ?? '').trim() !== '' || item.package_id)
            .length;

        const specCount = (helperResult.value.specs ?? [])
            .filter((item) =>
                String(item.name ?? '').trim() !== '' ||
                String(item.value_typ ?? '').trim() !== '' ||
                String(item.value_min ?? '').trim() !== '' ||
                String(item.value_max ?? '').trim() !== '' ||
                String(item.unit ?? '').trim() !== '' ||
                item.spec_type_id
            )
            .length;

        return { basicCount, categoryCount, packageCount, specCount };
    });
    const datasheetTargetLabel = computed(() => {
        if (selectedDatasheetFile.value) {
            return datasheetLabels.value[datasheetTargetIndex.value]?.trim() || selectedDatasheetFile.value.name;
        }

        if (currentDatasheets.value.length === 1) {
            const currentSheet = currentDatasheets.value[0];
            return currentSheet.display_name || currentSheet.original_name || '既存PDF';
        }

        return '';
    });

    const normalizeHelperText = (value) =>
        String(value ?? '')
            .toLowerCase()
            .replace(/[\s()\[\]_.-]/gu, '');

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

    const findCategoryById = (categoryId) =>
        categories.value.find((item) => Number(item.id) === Number(categoryId)) ?? null;

    const findPackageById = (packageId) =>
        packages.value.find((item) => Number(item.id) === Number(packageId)) ?? null;

    const findSpecTypeById = (specTypeId) =>
        specTypes.value.find((item) => Number(item.id) === Number(specTypeId)) ?? null;

    const specPreview = (spec) => normalizeSpecDraft(spec, findSpecTypeById(spec.spec_type_id));

    const createHelperBasicField = (value = '') => ({
        value: String(value ?? '').trim(),
        apply: String(value ?? '').trim() !== '',
    });

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

    const createHelperSpecCandidate = (overrides = {}) => {
        const rawName = String(overrides.name ?? '').trim();
        const matchedSpecType = overrides.spec_type_id
            ? findSpecTypeById(overrides.spec_type_id)
            : matchByName(rawName, specTypes.value);
        const draft = buildSpecDraftFromApi(overrides);

        return {
            name: rawName || matchedSpecType?.name || '',
            value_profile: draft.value_profile,
            value_typ: draft.value_typ,
            value_min: draft.value_min,
            value_max: draft.value_max,
            unit: draft.unit,
            spec_type_id: matchedSpecType?.id ?? overrides.spec_type_id ?? '',
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

    const extractPackageNames = (data) => {
        const names = [];

        if (Array.isArray(data?.package_names)) {
            names.push(...data.package_names);
        }

        if (Array.isArray(data?.packages)) {
            names.push(...data.packages.map((item) => (typeof item === 'string' ? item : item?.name ?? '')));
        }

        if (typeof data?.package_name === 'string') {
            names.push(data.package_name);
        }

        if (typeof data?.package === 'string') {
            names.push(data.package);
        } else if (data?.package?.name) {
            names.push(data.package.name);
        }

        if (typeof data?.package_type === 'string') {
            names.push(data.package_type);
        }

        return [...new Set(
            names
                .map((value) => String(value ?? '').trim())
                .filter(Boolean)
        )];
    };

    const buildHelperResult = (data) => {
        const source = data ?? {};
        const packageCandidates = extractPackageNames(source).map((name) => createHelperPackageCandidate({ name }));
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

    const openHelperResultModal = () => {
        if (!helperResult.value) return;
        showHelperResultModal.value = true;
    };

    const closeHelperResultModal = () => {
        showHelperResultModal.value = false;
        nextTick(() => {
            releaseScrollLockIfNoModal();
        });
    };

    const discardHelperResult = () => {
        helperResult.value = null;
        showHelperResultModal.value = false;
        nextTick(() => {
            releaseScrollLockIfNoModal();
        });
    };

    const addHelperCategory = () => {
        helperResult.value?.categories.push(createHelperCategoryCandidate({ apply: true }));
    };

    const removeHelperCategory = (index) => {
        helperResult.value?.categories.splice(index, 1);
    };

    const addHelperSpec = () => {
        helperResult.value?.specs.push(createHelperSpecCandidate({ apply: true }));
    };

    const addHelperPackage = () => {
        if (!helperResult.value) return;

        helperResult.value.packages.push(createHelperPackageCandidate());
        if (helperResult.value.selected_package_index === null || helperResult.value.selected_package_index === '') {
            helperResult.value.selected_package_index = helperResult.value.packages.length - 1;
        }
    };

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

    const removeHelperSpec = (index) => {
        helperResult.value?.specs.splice(index, 1);
    };

    const handleHelperCategorySelection = (candidate) => {
        const matchedCategory = findCategoryById(candidate.category_id);
        candidate.matched = !!matchedCategory;
        if (matchedCategory && !String(candidate.name ?? '').trim()) {
            candidate.name = matchedCategory.name;
        }
    };

    const handleHelperSpecTypeSelection = (spec) => {
        const matchedType = findSpecTypeById(spec.spec_type_id);
        spec.matched = !!matchedType;
        if (matchedType && !String(spec.name ?? '').trim()) {
            spec.name = matchedType.name;
        }
    };

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

    const helperFilteredPackages = (packageCandidate) => {
        const groupId = packageCandidate?.package_group_id;
        if (!groupId) return [];

        const scopedPackages = packages.value.filter((item) => Number(item.package_group_id) === Number(groupId));
        const query = String(packageCandidate?.package_query ?? '').trim().toLowerCase();
        if (!query) return scopedPackages;

        return scopedPackages.filter((item) => item.name.toLowerCase().includes(query));
    };

    // ── ChatGPT 貼り付けモーダル ───────────────────────────
    const selectedDatasheetFile = computed(() => datasheetFiles.value[datasheetTargetIndex.value] ?? null);
    const hasDatasheetForAi = computed(() => datasheetFiles.value.length > 0);
    const showChatGPTPaste = ref(false);
    const chatGPTPasteText = ref('');
    const chatGPTPasteTextarea = ref(null);
    const navigationGuardActive = computed(() => (
        dirty.value
        || showHelperResultModal.value
        || !!helperResult.value
        || showChatGPTPaste.value
        || !!String(chatGPTPasteText.value ?? '').trim()
        || showDatasheetManagerModal.value
        || showChatGptRunModal.value
    ));
    useNavigationConfirm(navigationGuardActive, '未保存の入力があります。このまま画面を離れてもよいですか？');
    const chatGptStatusLabel = computed(() => {
        const labels = {
            idle: '待機中',
            preparing: 'PDF準備中',
            opening: 'ChatGPT起動中',
            waiting: 'ChatGPT待ち',
            review: '結果受信',
            failed: '自動取得失敗',
            login_required: 'ChatGPTログイン待ち',
        };

        return labels[chatGptJob.state] ?? '待機中';
    });
    const chatGptStatusChips = computed(() => {
        const chips = [
            {
                label: chatGptJob.connected ? 'Tampermonkey接続済み' : 'Tampermonkey未接続',
                tone: chatGptJob.connected ? 'ok' : 'warning',
            },
            {
                label: chatGptJob.helperVersion ? `helper v${chatGptJob.helperVersion}` : 'helper version 不明',
                tone: chatGptJob.helperVersion
                    ? (isChatGptHelperVersionCompatible() ? 'ok' : 'warning')
                    : 'warning',
            },
        ];

        if (datasheetFiles.value.length) {
            chips.push({
                label: selectedDatasheetFile.value ? `解析対象: ${selectedDatasheetFile.value.name}` : '解析対象未選択',
                tone: selectedDatasheetFile.value ? 'neutral' : 'warning',
            });
        }

        if (chatGptJob.state !== 'idle') {
            chips.push({
                label: chatGptStatusLabel.value,
                tone: chatGptJob.state === 'failed' ? 'danger' : (chatGptJob.state === 'review' ? 'ok' : 'neutral'),
            });
        }

        if (chatGptTempDatasheets.value.length) {
            chips.push({
                label: `temp PDF ${chatGptTempDatasheets.value.length}件`,
                tone: 'neutral',
            });
        }

        return chips;
    });
    const chatGptStepStates = computed(() => {
        const state = chatGptJob.state;

        return [
            {
                label: 'PDF準備',
                status: ['preparing', 'opening', 'waiting', 'review', 'failed', 'login_required'].includes(state) ? 'done' : 'current',
            },
            {
                label: 'ChatGPT起動',
                status: ['opening', 'waiting', 'review', 'failed', 'login_required'].includes(state)
                    ? (state === 'opening' || state === 'login_required' ? 'current' : 'done')
                    : 'pending',
            },
            {
                label: '解析待ち',
                status: ['waiting', 'review', 'failed'].includes(state)
                    ? (state === 'waiting' ? 'current' : 'done')
                    : 'pending',
            },
            {
                label: '結果確認',
                status: state === 'review' ? 'current' : 'pending',
            },
        ];
    });
    const canStartChatGptAutoFill = computed(() => (
        !!selectedDatasheetFile.value && chatGptJob.connected && isChatGptHelperVersionCompatible()
    ));
    const chatGptHelperIssue = computed(() => {
        if (!chatGptJob.connected) {
            return {
                kind: 'missing',
                title: 'Tampermonkey helper を検出できません',
                body: 'BitsKeep ページで userscript が動いていません。Tampermonkey の有効化対象URLとスクリプトの有効状態を確認してください。',
            };
        }

        if (!isChatGptHelperVersionCompatible()) {
            return {
                kind: 'outdated',
                title: 'Tampermonkey helper が旧版です',
                body: `現在の helper は v${chatGptJob.helperVersion || '不明'} です。ChatGPT自動入力には v${CHATGPT_HELPER_MIN_VERSION} 以上が必要です。`,
            };
        }

        return null;
    });
    const showChatGptRunHint = computed(() => (
        !selectedDatasheetFile.value
            ? '先に解析対象のPDFを選択してください。'
            : (!chatGptJob.connected
                ? 'Tampermonkey helper が BitsKeep ページで検出できていません。userscript の有効化対象URLを確認してください。'
                : (!isChatGptHelperVersionCompatible()
                    ? `Tampermonkey helper を更新してください。必要: v${CHATGPT_HELPER_MIN_VERSION}+ / 現在: v${chatGptJob.helperVersion || '不明'}`
                    : '解析開始後は別タブの ChatGPT で PDF 添付と送信を自動化します。'))
    ));

    const compareVersion = (left, right) => {
        const leftParts = String(left ?? '').split('.').map((part) => Number.parseInt(part, 10) || 0);
        const rightParts = String(right ?? '').split('.').map((part) => Number.parseInt(part, 10) || 0);
        const length = Math.max(leftParts.length, rightParts.length);

        for (let index = 0; index < length; index += 1) {
            const leftPart = leftParts[index] ?? 0;
            const rightPart = rightParts[index] ?? 0;
            if (leftPart > rightPart) return 1;
            if (leftPart < rightPart) return -1;
        }

        return 0;
    };

    const withTimeout = async (promise, timeoutMs, timeoutMessage) => {
        let timerId = null;

        try {
            return await Promise.race([
                promise,
                new Promise((_, reject) => {
                    timerId = window.setTimeout(() => {
                        reject(new Error(timeoutMessage || 'timeout'));
                    }, timeoutMs);
                }),
            ]);
        } finally {
            if (timerId !== null) {
                window.clearTimeout(timerId);
            }
        }
    };

    const sleep = (ms) => new Promise((resolve) => window.setTimeout(resolve, ms));

    const isChatGptHelperVersionCompatible = () => (
        !!chatGptJob.helperVersion && compareVersion(chatGptJob.helperVersion, CHATGPT_HELPER_MIN_VERSION) >= 0
    );

    const persistActiveChatGptJob = (overrides = {}) => {
        const jobId = overrides.jobId || chatGptJob.jobId;
        if (!jobId) return;

        window.sessionStorage.setItem(CHATGPT_ACTIVE_JOB_STORAGE_KEY, JSON.stringify({
            jobId,
            startedAt: overrides.startedAt || chatGptJob.updatedAt || new Date().toISOString(),
            datasheetLabel: overrides.datasheetLabel || datasheetTargetLabel.value || '',
        }));
    };

    const readPersistedActiveChatGptJob = () => {
        const raw = window.sessionStorage.getItem(CHATGPT_ACTIVE_JOB_STORAGE_KEY);
        if (!raw) return null;

        try {
            return JSON.parse(raw);
        } catch {
            window.sessionStorage.removeItem(CHATGPT_ACTIVE_JOB_STORAGE_KEY);
            return null;
        }
    };

    const clearPersistedActiveChatGptJob = () => {
        window.sessionStorage.removeItem(CHATGPT_ACTIVE_JOB_STORAGE_KEY);
    };

    const resetChatGptJobState = ({ clearPersisted = true } = {}) => {
        chatGptJob.jobId = '';
        chatGptJob.state = 'idle';
        chatGptJob.detail = '';
        chatGptJob.error = '';
        chatGptJob.updatedAt = '';
        lastSyncedChatGptStatusAt = '';
        lastSyncedChatGptResultAt = '';
        chatGptUiLockSuppressed.value = false;
        if (clearPersisted) {
            clearPersistedActiveChatGptJob();
        }
    };

    const syncTampermonkeyConnection = () => {
        const helper = window.__bitskeepTampermonkeyHelper;
        chatGptJob.connected = !!helper?.connected;
        chatGptJob.helperVersion = String(helper?.version ?? '');
        const helperLogPayload = {
            connected: chatGptJob.connected,
            helperVersion: chatGptJob.helperVersion || '(unknown)',
            hasHelperObject: !!helper,
            windowName: CHATGPT_WINDOW_NAME,
        };
        const nextSignature = JSON.stringify(helperLogPayload);
        if (nextSignature !== lastChatGptHelperLogSignature) {
            console.info('[BitsKeep][ChatGPT Helper]', helperLogPayload);
            lastChatGptHelperLogSignature = nextSignature;
        }
    };

    const syncStoredChatGptBridgeState = async () => {
        const helper = window.__bitskeepTampermonkeyHelper;
        if (!helper?.connected) return;

        try {
            const [statusDetail, resultDetail] = await Promise.all([
                typeof helper.getStoredStatus === 'function' ? helper.getStoredStatus() : null,
                typeof helper.getStoredResult === 'function' ? helper.getStoredResult() : null,
            ]);

            if (statusDetail?.jobId && statusDetail.updatedAt && statusDetail.updatedAt !== lastSyncedChatGptStatusAt) {
                lastSyncedChatGptStatusAt = statusDetail.updatedAt;
                handleChatGptStatusEvent({ detail: statusDetail });
            }
            if (resultDetail?.jobId && resultDetail.updatedAt && resultDetail.updatedAt !== lastSyncedChatGptResultAt && (resultDetail.jsonText || resultDetail.rawText)) {
                lastSyncedChatGptResultAt = resultDetail.updatedAt;
                handleChatGptResultEvent({ detail: resultDetail });
            }
        } catch {
            // helper 側の現在値取得に失敗しても通常の event/poll 同期へ任せる。
        }
    };

    const syncChatGptWorkerHeartbeat = async () => {
        const helper = window.__bitskeepTampermonkeyHelper;
        if (!helper?.connected || typeof helper.getWorkerHeartbeat !== 'function') {
            chatGptWorkerHeartbeat.value = null;
            return;
        }

        try {
            chatGptWorkerHeartbeat.value = await helper.getWorkerHeartbeat();
        } catch {
            chatGptWorkerHeartbeat.value = null;
        }
    };

    const hasFreshChatGptWorker = () => {
        const updatedAt = Date.parse(chatGptWorkerHeartbeat.value?.updatedAt || '');
        if (Number.isNaN(updatedAt)) return false;

        return (Date.now() - updatedAt) < CHATGPT_WORKER_STALE_MS;
    };

    const isChatGptWorkerReady = () => (
        hasFreshChatGptWorker()
        && chatGptWorkerHeartbeat.value?.ready === true
        && chatGptWorkerHeartbeat.value?.acceptingJobs !== false
    );

    const waitForChatGptWorkerReady = async ({ openWindowIfNeeded = true } = {}) => {
        await syncChatGptWorkerHeartbeat();
        if (isChatGptWorkerReady()) {
            logChatGptFlow('worker.ready.existing', {
                workerHeartbeat: chatGptWorkerHeartbeat.value,
            });
            return true;
        }

        if (openWindowIfNeeded) {
            primeChatGptWindow();
        }

        logChatGptFlow('worker.ready.wait.start', {
            workerHeartbeat: chatGptWorkerHeartbeat.value,
        });

        const startedAt = Date.now();
        while ((Date.now() - startedAt) < CHATGPT_WORKER_READY_WAIT_MS) {
            await sleep(CHATGPT_WORKER_READY_POLL_MS);
            await syncChatGptWorkerHeartbeat();

            if (isChatGptWorkerReady()) {
                logChatGptFlow('worker.ready.wait.done', {
                    elapsedMs: Date.now() - startedAt,
                    workerHeartbeat: chatGptWorkerHeartbeat.value,
                });
                return true;
            }
        }

        logChatGptFlow('worker.ready.wait.timeout', {
            elapsedMs: Date.now() - startedAt,
            workerHeartbeat: chatGptWorkerHeartbeat.value,
        });
        return false;
    };

    const openChatGptHelperUpdateModal = () => {
        syncTampermonkeyConnection();
        if (chatGptJob.connected && isChatGptHelperVersionCompatible()) {
            chatGptHelperCheckStatus.value = 'success';
            chatGptHelperCheckMessage.value = `helper v${chatGptJob.helperVersion} を検出しました。このまま ChatGPT自動解析を使えます。`;
        } else {
            chatGptHelperCheckStatus.value = 'idle';
            chatGptHelperCheckMessage.value = '';
        }
        showChatGptHelperUpdateModal.value = true;
    };

    const closeChatGptHelperUpdateModal = () => {
        chatGptHelperCheckStatus.value = 'idle';
        chatGptHelperCheckMessage.value = '';
        showChatGptHelperUpdateModal.value = false;
        nextTick(() => {
            releaseScrollLockIfNoModal();
        });
    };

    const maybePromptChatGptHelperUpdate = () => {
        if (chatGptHelperPromptShown) return;
        if (!chatGptHelperIssue.value) return;

        chatGptHelperPromptShown = true;
        showChatGptHelperUpdateModal.value = true;
    };

    const ensureChatGptHelperReady = ({ showModal = true, showToast = true } = {}) => {
        syncTampermonkeyConnection();
        const allowToast = showToast && !showChatGptHelperUpdateModal.value;

        if (!chatGptJob.connected) {
            if (allowToast) {
                toastError('Tampermonkey helper を検出できません。userscript を確認してください。');
            }
            if (showModal) {
                openChatGptHelperUpdateModal();
            }
            return false;
        }

        if (!isChatGptHelperVersionCompatible()) {
            if (allowToast) {
                toastError(`Tampermonkey helper が旧版です。v${CHATGPT_HELPER_MIN_VERSION}+ へ更新してください。現在: v${chatGptJob.helperVersion || '不明'}`);
            }
            if (showModal) {
                openChatGptHelperUpdateModal();
            }
            return false;
        }

        return true;
    };

    const reloadForChatGptHelperUpdate = () => {
        window.sessionStorage.setItem(CHATGPT_HELPER_RECHECK_STORAGE_KEY, '1');
        window.location.reload();
    };

    const handleChatGptHelperReloadRecheck = () => {
        if (ensureChatGptHelperReady({ showModal: false, showToast: false })) {
            chatGptHelperPromptShown = true;
            chatGptHelperCheckStatus.value = 'idle';
            chatGptHelperCheckMessage.value = '';
            toastSuccess(`Tampermonkey helper v${chatGptJob.helperVersion} を検出しました。ChatGPT自動入力を使えます。`);
            return;
        }

        openChatGptHelperUpdateModal();
        chatGptHelperCheckStatus.value = 'warning';
        chatGptHelperCheckMessage.value = `${chatGptHelperIssue.value.body} userscript 更新後は、この部品登録画面を再読込してください。`;
    };

    const primeChatGptWindow = () => {
        if (hasFreshChatGptWorker()) {
            logChatGptFlow('prime.reuse_worker', {
                workerHeartbeat: chatGptWorkerHeartbeat.value,
            });
            return;
        }

        try {
            const openedWindow = window.open('https://chatgpt.com/', CHATGPT_WINDOW_NAME);
            if (openedWindow) {
                chatGptWindowRef = openedWindow;
                chatGptWindowRef.focus?.();
                logChatGptFlow('prime.open_window', {
                    reusedNamedWindow: true,
                });
            }
        } catch {
            // ブラウザが拒否した場合は userscript 側 fallback に任せる。
        }
    };

    const updateChatGptJobState = (state, detail = '', error = '', updatedAt = '') => {
        chatGptJob.state = state;
        chatGptJob.detail = detail;
        chatGptJob.error = error;
        chatGptJob.updatedAt = updatedAt || new Date().toISOString();
        if (!['preparing', 'opening', 'waiting'].includes(state)) {
            chatGptUiLockSuppressed.value = false;
        }
        if (chatGptJob.jobId && ['preparing', 'opening', 'waiting', 'login_required'].includes(state)) {
            persistActiveChatGptJob({ startedAt: chatGptJob.updatedAt });
        }
        if (['idle', 'review', 'failed'].includes(state)) {
            clearPersistedActiveChatGptJob();
        }
    };

    const hardResetChatGptJob = async () => {
        const currentJobId = chatGptJob.jobId;

        await clearRemoteChatGptState(currentJobId);
        await clearChatGptTempDatasheets();

        helperResult.value = null;
        chatGPTPasteText.value = '';
        showHelperResultModal.value = false;
        showChatGPTPaste.value = false;
        showDatasheetManagerModal.value = false;
        showChatGptRunModal.value = false;
        chatGptGuideReason.value = '';
        pendingAiAction.value = '';
        chatGptUiLockSuppressed.value = false;
        resetChatGptJobState();
        chatGptWorkerHeartbeat.value = null;

        nextTick(() => {
            releaseScrollLockIfNoModal();
        });
        toastSuccess('ChatGPT ジョブ状態を破棄してリセットしました。');
    };

    const clearRemoteChatGptState = async (jobId = '') => {
        const helper = window.__bitskeepTampermonkeyHelper;
        if (helper?.connected && typeof helper.clearRemoteState === 'function') {
            try {
                await helper.clearRemoteState(jobId);
            } catch {
                // remote state 掃除に失敗しても画面復帰を優先する。
            }
        }
    };

    const expireStaleChatGptJob = (jobId, message) => {
        if (!jobId || chatGptJob.jobId !== jobId) return;

        updateChatGptJobState('failed', message, message);
        showChatGptRunModal.value = false;
        void clearRemoteChatGptState(jobId);
        openChatGptGuide(`${message} ChatGPT タブを閉じた場合や、同期が途切れた場合は、もう一度「ChatGPTで自動入力」を実行してください。`);
        toastError(message);
    };

    const maybeExpireChatGptJobState = () => {
        if (!chatGptJob.jobId || !['preparing', 'opening', 'waiting'].includes(chatGptJob.state)) return;
        if (!chatGptJob.updatedAt) return;

        const heartbeatAt = Date.parse(chatGptWorkerHeartbeat.value?.updatedAt || '');
        const hasFreshWorker = !Number.isNaN(heartbeatAt) && (Date.now() - heartbeatAt) < CHATGPT_WORKER_STALE_MS;

        if ((chatGptJob.state === 'preparing' || chatGptJob.state === 'opening') && !hasFreshWorker) {
            expireStaleChatGptJob(chatGptJob.jobId, 'ChatGPT タブが見つからないため、前回ジョブを破棄しました。');
            return;
        }

        const updatedAt = Date.parse(chatGptJob.updatedAt);
        if (Number.isNaN(updatedAt)) return;
        const staleMs = chatGptJob.state === 'waiting' ? CHATGPT_WAITING_STALE_MS : CHATGPT_STATUS_STALE_MS;
        if ((Date.now() - updatedAt) < staleMs) return;

        expireStaleChatGptJob(chatGptJob.jobId, 'ChatGPT 解析の状態更新が途切れました。');
    };

    const getChatGptStatusStaleMs = (status) => {
        if (status === 'queued') return CHATGPT_QUEUED_STALE_MS;
        if (status === 'waiting_response') return CHATGPT_WAITING_STALE_MS;
        return CHATGPT_STATUS_STALE_MS;
    };

    const clearChatGptTempDatasheets = async () => {
        const tokens = chatGptTempDatasheets.value.map((entry) => entry.token).filter(Boolean);
        if (!tokens.length) {
            chatGptTempDatasheets.value = [];
            logChatGptFlow('cleanup.skip', {
                reason: 'no_temp_tokens',
            });
            return;
        }

        logChatGptFlow('cleanup.start', {
            tokenCount: tokens.length,
        });

        const results = await Promise.allSettled(tokens.map((token) => withTimeout(
            api.delete(`/component-helper/chatgpt-jobs/${token}`),
            CHATGPT_TEMP_CLEANUP_TIMEOUT_MS,
            `temp cleanup timeout: ${token}`
        )));
        chatGptTempDatasheets.value = [];

        const failedTokens = results.flatMap((result, index) => (
            result.status === 'rejected'
                ? [{
                    token: tokens[index],
                    reason: result.reason?.message || 'cleanup_failed',
                }]
                : []
        ));

        logChatGptFlow('cleanup.done', {
            tokenCount: tokens.length,
            failedCount: failedTokens.length,
            failedTokens,
        });
    };

    const openDatasheetManager = () => {
        if (isChatGptJobBusy.value) return;
        showChatGptRunModal.value = false;
        showDatasheetManagerModal.value = true;
    };

    const closeDatasheetManager = () => {
        showDatasheetManagerModal.value = false;
        pendingAiAction.value = '';
        nextTick(() => {
            releaseScrollLockIfNoModal();
        });
    };

    const queueChatGptJob = async (job) => {
        const helper = window.__bitskeepTampermonkeyHelper;
        logChatGptFlow('queue.start', {
            jobId: job?.job_id || '',
            hasHelper: !!helper,
            helperConnected: !!helper?.connected,
            helperVersion: chatGptJob.helperVersion || '(unknown)',
        });

        if (helper?.connected && typeof helper.enqueueJob === 'function') {
            try {
                const queued = await helper.enqueueJob(job);
                logChatGptFlow('queue.helper.result', {
                    jobId: job?.job_id || '',
                    queued: queued !== false,
                });
                if (queued !== false) {
                    return;
                }
            } catch (error) {
                console.error('[BitsKeep][ChatGPT Flow] queue.helper.failed', error);
                // userscript bridge が失敗した場合は CustomEvent fallback を使う。
            }
        }

        logChatGptFlow('queue.fallback.event', {
            jobId: job?.job_id || '',
        });
        window.dispatchEvent(new CustomEvent('bitskeep-chatgpt-start', { detail: job }));
    };

    const beginAiAction = async (action) => {
        logChatGptFlow('begin.action', {
            action,
            datasheetCount: datasheetFiles.value.length,
            selectedIndex: datasheetTargetIndex.value,
            selectedName: selectedDatasheetFile.value?.name || '',
        });
        if (!datasheetFiles.value.length) {
            toastError('先にデータシートPDFを選択してください。');
            return;
        }

        if (action === 'chatgpt' && !ensureChatGptHelperReady()) {
            return;
        }

        if (datasheetFiles.value.length > 1) {
            pendingAiAction.value = action;
            openDatasheetManager();
            return;
        }

        pendingAiAction.value = '';
        if (action === 'gemini') {
            await analyzeDatasheet(true);
            return;
        }

        primeChatGptWindow();
        openChatGptRun();
        await nextTick();
        await startChatGPTAutoFill();
    };

    const confirmDatasheetTargetSelection = async () => {
        const action = pendingAiAction.value;
        logChatGptFlow('confirm.target', {
            action,
            selectedIndex: datasheetTargetIndex.value,
            selectedName: selectedDatasheetFile.value?.name || '',
        });
        closeDatasheetManager();

        if (action === 'gemini') {
            await analyzeDatasheet(true);
            return;
        }

        if (action !== 'chatgpt') {
            return;
        }

        if (!ensureChatGptHelperReady()) {
            return;
        }

        primeChatGptWindow();
        openChatGptRun();
        await nextTick();
        await startChatGPTAutoFill();
    };

    const openChatGptRun = () => {
        syncTampermonkeyConnection();
        chatGptGuideReason.value = '';
        showChatGptHelperUpdateModal.value = false;
        showDatasheetManagerModal.value = false;
        showChatGptRunModal.value = true;
    };

    const closeChatGptRun = () => {
        if (isChatGptJobBusy.value) {
            toastError('ChatGPT 解析中はこの画面を閉じられません。結果受信まで待ってください。');
            return;
        }
        showChatGptRunModal.value = false;
        nextTick(() => {
            releaseScrollLockIfNoModal();
        });
    };

    const openChatGptGuide = (reason) => {
        chatGptGuideReason.value = reason;
        showChatGptRunModal.value = true;
    };

    const consumeChatGptRawText = (rawText) => {
        let raw = String(rawText ?? '').trim();
        if (!raw) {
            return false;
        }

        raw = raw.replace(/^```(?:json)?\s*/i, '').replace(/\s*```$/, '').trim();

        let data;
        try {
            data = JSON.parse(raw);
        } catch {
            return false;
        }

        const result = buildHelperResult(data);
        if (!hasHelperCandidates(result)) {
            return false;
        }

        helperResult.value = result;
        showChatGptRunModal.value = false;
        showHelperResultModal.value = true;
        return true;
    };

    const openChatGPTPaste = () => {
        showChatGptRunModal.value = false;
        showChatGPTPaste.value = true;
        nextTick(() => {
            chatGPTPasteTextarea.value?.focus?.();
        });
    };

    /**
     * ChatGPT が返した JSON テキストをパースして helperResult にセットする。
     * JSON にコードブロック（```json ... ```）が含まれていても除去して処理する。
     */
    const parseChatGPTResult = () => {
        if (!chatGPTPasteText.value.trim()) {
            toastError('テキストを貼り付けてください。');
            return;
        }

        if (!consumeChatGptRawText(chatGPTPasteText.value)) {
            toastError('JSON の形式が正しくありません。ChatGPT の出力をそのまま貼り付けてください。');
            return;
        }
        chatGPTPasteText.value = '';
        showChatGPTPaste.value = false;
        nextTick(() => {
            releaseScrollLockIfNoModal();
        });
    };

    const dismissChatGPTPaste = () => {
        showChatGPTPaste.value = false;
        nextTick(() => {
            releaseScrollLockIfNoModal();
        });
    };

    const handleChatGptStatusEvent = (event) => {
        const detail = event.detail ?? {};
        logChatGptFlow('status.event.received', detail);
        if (!detail.jobId) return;

        if (!chatGptJob.jobId) {
            const persistedJob = readPersistedActiveChatGptJob();
            if (persistedJob?.jobId === detail.jobId) {
                chatGptJob.jobId = detail.jobId;
            }
        }
        if (detail.jobId !== chatGptJob.jobId) return;
        lastSyncedChatGptStatusAt = detail.updatedAt || lastSyncedChatGptStatusAt;

        if (['queued', 'opening_chatgpt', 'downloading_pdf', 'attaching_pdf', 'submitting', 'waiting_response'].includes(detail.status)) {
            const sourceUpdatedAt = Date.parse(detail.updatedAt || '');
            const staleMs = getChatGptStatusStaleMs(detail.status);
            if (!Number.isNaN(sourceUpdatedAt) && (Date.now() - sourceUpdatedAt) >= staleMs) {
                expireStaleChatGptJob(detail.jobId, 'ChatGPT 解析の古い状態が残っていたため、前回ジョブを破棄しました。');
                return;
            }
        }

        const statusMap = {
            queued: ['opening', 'ChatGPTタブを起動しています。'],
            opening_chatgpt: ['opening', 'ChatGPTタブを前面化しました。'],
            downloading_pdf: ['opening', '解析対象PDFをChatGPTタブへ渡しています。'],
            attaching_pdf: ['opening', 'PDFを添付しています。'],
            submitting: ['waiting', 'プロンプトとPDFを送信しています。'],
            waiting_response: ['waiting', 'ChatGPTの応答を待っています。'],
            result_ready: ['review', '結果を受信しました。候補を確認してください。'],
            login_required: ['login_required', 'ChatGPTへログインしてから再開してください。'],
            failed: ['failed', detail.message || '自動取得に失敗しました。'],
        };

        const [nextState, nextDetail] = statusMap[detail.status] ?? ['waiting', detail.message || 'ChatGPTの応答を待っています。'];
        updateChatGptJobState(nextState, nextDetail, detail.message || '', detail.updatedAt || '');

        if (['opening', 'waiting'].includes(nextState) && !chatGptUiLockSuppressed.value) {
            showChatGptRunModal.value = true;
        }

        if (detail.status === 'login_required') {
            openChatGptGuide('ChatGPT タブでログインしてから、もう一度「ChatGPTで自動入力」を実行してください。');
        }

        if (detail.status === 'failed') {
            if (detail.rawText) {
                chatGPTPasteText.value = detail.rawText;
            }
            openChatGptGuide(detail.message || '自動解析に失敗しました。「ChatGPTから貼り付け」に切り替えてください。');
        }
    };

    const handleChatGptResultEvent = (event) => {
        const detail = event.detail ?? {};
        logChatGptFlow('result.event.received', {
            jobId: detail.jobId || '',
            hasJsonText: !!detail.jsonText,
            rawLength: String(detail.rawText ?? '').length,
            updatedAt: detail.updatedAt || '',
        });
        if (!detail.jobId) return;

        if (!chatGptJob.jobId) {
            const persistedJob = readPersistedActiveChatGptJob();
            if (persistedJob?.jobId === detail.jobId) {
                chatGptJob.jobId = detail.jobId;
            }
        }
        if (detail.jobId !== chatGptJob.jobId) return;
        lastSyncedChatGptResultAt = detail.updatedAt || lastSyncedChatGptResultAt;

        updateChatGptJobState('review', '結果を受信しました。候補を確認してください。', '', detail.updatedAt || '');
        showChatGptRunModal.value = false;
        window.focus?.();

        if (consumeChatGptRawText(detail.jsonText ?? detail.rawText ?? '')) {
            toastSuccess('ChatGPT の解析結果を受信しました。BitsKeep に戻って候補を確認してください。');
            return;
        }

        chatGPTPasteText.value = detail.rawText ?? '';
        openChatGptGuide('ChatGPT の返答から JSON を自動抽出できませんでした。貼り付け fallback へ切り替えてください。');
    };

    const openPasteFallbackFromGuide = () => {
        chatGptGuideReason.value = '';
        openChatGPTPaste();
    };

    const copyChatGptFallbackText = async () => {
        const rawText = String(chatGPTPasteText.value ?? '').trim();
        if (!rawText) {
            toastError('コピーできる ChatGPT 応答テキストがありません。');
            return;
        }

        try {
            await navigator.clipboard.writeText(rawText);
            toastSuccess('ChatGPT の応答テキストをコピーしました。');
        } catch {
            toastError('クリップボードへのコピーに失敗しました。');
        }
    };

    const startChatGPTAutoFill = async () => {
        logChatGptFlow('autofill.start', {
            datasheetCount: datasheetFiles.value.length,
            selectedIndex: datasheetTargetIndex.value,
            selectedName: selectedDatasheetFile.value?.name || '',
            helperConnected: chatGptJob.connected,
            helperVersion: chatGptJob.helperVersion || '(unknown)',
        });
        if (!datasheetFiles.value.length) {
            toastError('先にデータシートPDFを選択してください。');
            return;
        }

        if (!selectedDatasheetFile.value) {
            toastError('解析対象のPDFを選択してください。');
            return;
        }

        if (!ensureChatGptHelperReady()) {
            if (!chatGptJob.connected) {
                openChatGptGuide('Tampermonkey helper が未接続です。userscript を有効化してから再試行してください。');
                return;
            }

            openChatGptGuide(`Tampermonkey helper が古いです。userscript を更新してください。必要: v${CHATGPT_HELPER_MIN_VERSION}+ / 現在: v${chatGptJob.helperVersion || '不明'}`);
            return;
        }

        const workerReady = await waitForChatGptWorkerReady({ openWindowIfNeeded: true });
        if (!workerReady) {
            const message = 'ChatGPT タブの受信準備を確認できませんでした。ChatGPT タブを開いたまま、もう一度「ChatGPTで自動入力」を実行してください。';
            logChatGptFlow('worker.ready.failed', {
                message,
                workerHeartbeat: chatGptWorkerHeartbeat.value,
            });
            updateChatGptJobState('failed', message, message);
            openChatGptGuide(message);
            toastError(message);
            return;
        }

        try {
            logChatGptFlow('autofill.cleanup.previous.start', {
                previousJobId: chatGptJob.jobId || '',
                tempCount: chatGptTempDatasheets.value.length,
            });
            await clearRemoteChatGptState(chatGptJob.jobId || '');
            await clearChatGptTempDatasheets();
            helperResult.value = null;
            showHelperResultModal.value = false;
            updateChatGptJobState('preparing', '解析ジョブを準備しています。');
            logChatGptFlow('autofill.cleanup.previous.done', {
                previousJobId: chatGptJob.jobId || '',
            });

            const fd = new FormData();
            datasheetFiles.value.forEach((file, index) => {
                fd.append(`datasheets[${index}]`, file);
                fd.append(`datasheet_labels[${index}]`, datasheetLabels.value[index] ?? '');
            });
            fd.append('target_index', String(datasheetTargetIndex.value));

            logChatGptFlow('autofill.job.create.request', {
                datasheetCount: datasheetFiles.value.length,
                targetIndex: datasheetTargetIndex.value,
            });
            const response = await withTimeout(
                api.upload('/component-helper/chatgpt-jobs', fd, {
                    transport: 'xhr',
                    timeoutMs: CHATGPT_JOB_CREATE_TIMEOUT_MS,
                    timeoutMessage: 'ChatGPT 解析ジョブの作成がタイムアウトしました。ジョブを破棄してリセットしてから再試行してください。',
                    onEvent: (type, detail = {}) => {
                        logChatGptFlow(`autofill.job.create.xhr.${type}`, detail);
                    },
                }),
                CHATGPT_JOB_CREATE_TIMEOUT_MS + 1000,
                'ChatGPT 解析ジョブの作成がタイムアウトしました。ジョブを破棄してリセットしてから再試行してください。'
            );
            const job = response.data ?? {};
            logChatGptFlow('autofill.job.created', {
                jobId: job.job_id || '',
                datasheetCount: Array.isArray(job.datasheets) ? job.datasheets.length : 0,
                targetName: job.target_datasheet?.original_name || '',
            });
            chatGptTempDatasheets.value = Array.isArray(job.datasheets) ? job.datasheets : [];
            chatGptJob.jobId = job.job_id ?? '';
            persistActiveChatGptJob({
                jobId: chatGptJob.jobId,
                startedAt: new Date().toISOString(),
                datasheetLabel: datasheetTargetLabel.value,
            });
            updateChatGptJobState('opening', 'ChatGPTタブを起動しています。');
            await queueChatGptJob(job);
        } catch (e) {
            console.error('[BitsKeep][ChatGPT Flow] autofill.failed', e);
            logChatGptFlow('autofill.failed', {
                message: e.message || 'ChatGPT 自動解析ジョブの作成に失敗しました。',
                code: e.code || '',
                status: e.status || '',
            });
            await clearRemoteChatGptState('');
            updateChatGptJobState('failed', e.message || 'ChatGPT 自動解析ジョブの作成に失敗しました。', e.message || '');
            openChatGptGuide(e.message || 'ChatGPT 自動解析ジョブの作成に失敗しました。');
            toastError(e.message ?? 'ChatGPT 自動解析ジョブの作成に失敗しました。');
        }
    };

    /**
     * PDFデータシートを Gemini で解析し、helperResult に結果をセットする。
     */
    const analyzeDatasheet = async (skipSelection = false) => {
        if (!skipSelection && datasheetFiles.value.length > 1) {
            pendingAiAction.value = 'gemini';
            openDatasheetManager();
            return;
        }

        const file = selectedDatasheetFile.value;
        if (!file) {
            toastError('先にデータシートPDFを選択してください。');
            return;
        }

        analyzing.value = true;
        helperResult.value = null;
        showHelperResultModal.value = false;
        try {
            const fd = new FormData();
            fd.append('pdf', file);
            const r = await api.upload('/component-helper/analyze-datasheet', fd);
            const data = r.data;
            const result = buildHelperResult(data ?? {});

            if (!hasHelperCandidates(result)) {
                toastError('データシートから情報を抽出できませんでした。手動で入力してください。');
                return;
            }

            helperResult.value = result;
            showHelperResultModal.value = true;
        } catch (e) {
            const msg = e.message ?? '';
            if (e.status === 403) {
                toastError('Gemini APIキーが未設定です。連携設定から登録してください。');
            } else if (e.status === 422) {
                const validationMessage = Object.values(e.errors ?? {})
                    .flat()
                    .find((value) => String(value ?? '').trim() !== '');
                toastError(validationMessage ?? '入力内容を確認してください。');
            } else if (msg) {
                toastError(msg);
            } else {
                toastError('解析に失敗しました。しばらく後で再試行してください。');
            }
        } finally {
            analyzing.value = false;
        }
    };

    /**
     * 解析結果パネルで選択されたフィールドをフォームに書き込む。
     */
    const applyHelperResult = () => {
        const r = helperResult.value;
        if (!r) return;

        let appliedCount = 0;
        let skippedSpecs = 0;
        let skippedCategories = 0;
        let skippedPackage = false;

        if (r.part_number.apply  && r.part_number.value) {
            form.part_number = r.part_number.value;
            appliedCount++;
        }
        if (r.manufacturer.apply && r.manufacturer.value) {
            form.manufacturer = r.manufacturer.value;
            manufacturerQuery.value = r.manufacturer.value;
            ensureManufacturerOption(r.manufacturer.value);
            appliedCount++;
        }
        if (r.common_name.apply  && r.common_name.value) {
            form.common_name = r.common_name.value;
            appliedCount++;
        }
        if (r.description.apply  && r.description.value) {
            form.description = r.description.value;
            appliedCount++;
        }

        const categoryIdsToAdd = [];
        for (const category of r.categories ?? []) {
            if (!category.apply) continue;
            if (!category.category_id) {
                skippedCategories++;
                continue;
            }

            categoryIdsToAdd.push(Number(category.category_id));
        }
        if (categoryIdsToAdd.length) {
            form.category_ids = [...new Set([...form.category_ids, ...categoryIdsToAdd])];
            appliedCount += categoryIdsToAdd.length;
        }

        if (r.package_apply) {
            const selectedPackageIndex = r.selected_package_index === null || r.selected_package_index === ''
                ? null
                : Number(r.selected_package_index);
            const selectedPackage = selectedPackageIndex === null ? null : r.packages?.[selectedPackageIndex] ?? null;

            if (selectedPackage?.package_id) {
                form.package_group_id = selectedPackage.package_group_id ? String(selectedPackage.package_group_id) : '';
                form.package_id = String(selectedPackage.package_id);
                appliedCount++;
            } else if ((r.packages ?? []).some((item) => String(item.name ?? '').trim() !== '' || item.package_id)) {
                skippedPackage = true;
            } else if (selectedPackage && String(selectedPackage.name ?? '').trim() !== '') {
                skippedPackage = true;
            }
        }

        for (const spec of r.specs ?? []) {
            if (!spec.apply) continue;
            if (!spec.spec_type_id) {
                skippedSpecs++;
                continue;
            }

            const existing = form.specs.find((item) => specIdentityKey(item) === specIdentityKey(spec));
            if (existing) {
                existing.value_profile = normalizeSpecProfile(spec.value_profile);
                existing.value_typ = spec.value_typ;
                existing.value_min = spec.value_min;
                existing.value_max = spec.value_max;
                existing.unit = spec.unit;
            } else {
                form.specs.push({
                    ...createEmptySpecRow(),
                    spec_type_id: spec.spec_type_id,
                    value_profile: normalizeSpecProfile(spec.value_profile),
                    value_typ: spec.value_typ,
                    value_min: spec.value_min,
                    value_max: spec.value_max,
                    unit: spec.unit,
                });
            }
            appliedCount++;
        }

        if (appliedCount === 0) {
            toastError('適用できる候補がありません。分類・パッケージ・スペック種別を確認してください。');
            return;
        }

        const warnings = [];
        if (skippedCategories > 0) warnings.push(`分類 ${skippedCategories} 件`);
        if (skippedSpecs > 0) warnings.push(`スペック ${skippedSpecs} 件`);
        if (skippedPackage) warnings.push('パッケージ 1 件');

        if (warnings.length > 0) {
            toastSuccess(`解析結果を適用しました（一部未反映: ${warnings.join(' / ')}）`);
        } else {
            toastSuccess('解析結果を適用しました。');
        }

        helperResult.value = null;
        showHelperResultModal.value = false;
    };

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

    // ── 保存 ──────────────────────────────────────────────
    const submit = async () => {
        saving.value = true;
        const payload = buildPayload();

        try {
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

    // ── 初期ロード ────────────────────────────────────────
    onMounted(async () => {
        const [catRes, groupRes, pkgRes, stRes, supRes, compRes, locRes, altiumRes] = await Promise.all([
            api.get('/categories'), api.get('/package-groups'), api.get('/packages'),
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

        // 編集モードなら既存データをロード
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
                specs: p.specs.map((s) => buildSpecDraftFromApi(s)),
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

        syncTampermonkeyConnection();
        window.name = BITSKEEP_WINDOW_NAME;
        const persistedChatGptJob = readPersistedActiveChatGptJob();
        if (persistedChatGptJob?.jobId) {
            chatGptJob.jobId = persistedChatGptJob.jobId;
            updateChatGptJobState(
                'waiting',
                persistedChatGptJob.datasheetLabel
                    ? `前回の ChatGPT 解析状態を確認しています。対象: ${persistedChatGptJob.datasheetLabel}`
                    : '前回の ChatGPT 解析状態を確認しています。',
                '',
                persistedChatGptJob.startedAt || ''
            );
            showChatGptRunModal.value = true;
        }
        const shouldRecheckChatGptHelper = window.sessionStorage.getItem(CHATGPT_HELPER_RECHECK_STORAGE_KEY) === '1';
        if (shouldRecheckChatGptHelper) {
            window.sessionStorage.removeItem(CHATGPT_HELPER_RECHECK_STORAGE_KEY);
            handleChatGptHelperReloadRecheck();
        } else if (chatGptJob.connected && isChatGptHelperVersionCompatible()) {
            toastSuccess(`Tampermonkey helper v${chatGptJob.helperVersion} を検出しました。ChatGPT自動入力を使えます。`);
        }
        window.addEventListener('bitskeep-chatgpt-status', handleChatGptStatusEvent);
        window.addEventListener('bitskeep-chatgpt-result', handleChatGptResultEvent);
        void syncStoredChatGptBridgeState();
        void syncChatGptWorkerHeartbeat();
        tampermonkeyPollTimer = window.setInterval(() => {
            syncTampermonkeyConnection();
            void syncStoredChatGptBridgeState();
            void syncChatGptWorkerHeartbeat();
        }, 1500);
        chatGptJobWatchdogTimer = window.setInterval(maybeExpireChatGptJobState, 3000);
        if (!shouldRecheckChatGptHelper) {
            chatGptHelperPromptTimer = window.setTimeout(maybePromptChatGptHelperUpdate, 900);
        }
    });

    onBeforeUnmount(() => {
        window.removeEventListener('bitskeep-chatgpt-status', handleChatGptStatusEvent);
        window.removeEventListener('bitskeep-chatgpt-result', handleChatGptResultEvent);
        if (tampermonkeyPollTimer) {
            window.clearInterval(tampermonkeyPollTimer);
            tampermonkeyPollTimer = null;
        }
        if (chatGptHelperPromptTimer) {
            window.clearTimeout(chatGptHelperPromptTimer);
            chatGptHelperPromptTimer = null;
        }
        if (chatGptJobWatchdogTimer) {
            window.clearInterval(chatGptJobWatchdogTimer);
            chatGptJobWatchdogTimer = null;
        }
        document.documentElement.classList.remove('modal-open');
        document.body.classList.remove('modal-open');
    });

    watch(anyModalOpen, (isOpen) => {
        syncScrollLock(isOpen);
    }, { immediate: true });

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

        maybePromptChatGptHelperUpdate();
    });

    return {
        toasts, isEdit, form, saving, dirty, locations, masterLoadError, canCreateSupplier,
        imagePreviewUrl, currentImageUrl, currentDatasheets, datasheetFiles, datasheetLabels, datasheetTargetIndex,
        categories, packageGroups, packages, specTypes, suppliers,
        altiumLibraries, schLibraries, pcbLibraries,
        manufacturerQuery, filteredManufacturers, manufacturerExactMatch,
        manufacturerSuggestionsOpen,
        categoryQuery, filteredCategories, canCreateCategory,
        packageQuery, filteredPackages, canCreatePackage,
        specProfileOptions,
        addSpec, removeSpec, getUnitSuggestions, specPreview, specDisplayName, addCustomAttribute, removeCustomAttribute,
        addSupplier, removeSupplier, addPriceBreak, removePriceBreak,
        selectManufacturer, commitManufacturer,
        toggleCategory, selectPackage, addCategoryFromQuery, addPackageFromQuery,
        filteredSuppliersForRow, canCreateSupplierForRow, selectSupplier, commitSupplier,
        onImageChange, onDatasheetChange,
        analyzing, helperResult, helperResultSummary, showHelperResultModal,
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
        showChatGptHelperUpdateModal, chatGptHelperIssue, chatGptHelperCheckStatus, chatGptHelperCheckMessage,
        openChatGptHelperUpdateModal, closeChatGptHelperUpdateModal, reloadForChatGptHelperUpdate,
        showChatGPTPaste, chatGPTPasteText, chatGPTPasteTextarea, openChatGPTPaste, beginAiAction, openChatGptRun, closeChatGptRun, startChatGPTAutoFill,
        parseChatGPTResult, dismissChatGPTPaste,
        chatGptGuideReason, openPasteFallbackFromGuide,
        copyChatGptFallbackText,
        hardResetChatGptJob,
        chatGptStatusChips, chatGptStepStates, canStartChatGptAutoFill, showChatGptRunHint, chatGptJob,
        submit, duplicateFromId,
    };
}
