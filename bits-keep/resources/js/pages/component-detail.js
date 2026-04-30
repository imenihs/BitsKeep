import { ref, computed, onMounted, onBeforeUnmount, reactive } from 'vue';
import { api } from '../api.js';
import { useToast } from '../composables/useToast.js';
import { useFavoriteComponents } from '../composables/useFavoriteComponents.js';
import { useFormatter } from '../composables/useFormatter.js';
import { useConfirmModal } from '../composables/useConfirmModal.js';
import {
    buildSpecDraftFromApi,
    buildSpecPayload,
    createEmptySpecRow,
    getSpecDisplayName,
    getSpecProfileBadgeLabel,
    getSpecUnitSuggestions,
    normalizeSpecDraft,
    normalizeSpecProfile,
    SPEC_PROFILE_OPTIONS,
} from '../utils/specValue.js';

export default function setup() {
    const { toasts, toastSuccess, toastError } = useToast();
    const { formatCurrency } = useFormatter();
    const { ask } = useConfirmModal();
    const { loadFavorites, toggleFavorite, isFavorite } = useFavoriteComponents();

    // Blade側から data-id 属性で部品IDを受け取る
    const appEl = document.getElementById('app');
    const componentId = appEl?.dataset?.id;
    const canCreateSpecType = computed(() => appEl?.dataset?.canCreateSpecType === '1');

    const part       = ref(null);
    const loading    = ref(true);
    const loadError  = ref('');
    const sections   = reactive({ basic: true, detail: true, custom: true, integration: true });
    const categories = ref([]);
    const packageGroups = ref([]);
    const packages = ref([]);
    const specTypes = ref([]);
    const specGroups = ref([]);
    const specSuggestionTypes = ref([]);
    const specTemplates = ref([]);
    const selectedSpecGroupId = ref('all');
    const selectedSpecTypeId = ref('');
    const selectedSpecTemplateId = ref('');
    const specTypeSearchQuery = ref('');
    const specSuggestionLoading = ref(false);
    const suppliers = ref([]);
    const locations = ref([]);
    const detailCategoryQuery = ref('');
    const showAllTransactions = ref(false);
    const basicImageFile = ref(null);
    const basicDatasheetFiles = ref([]);
    const basicDatasheetLabels = ref([]);
    const editModalSnapshot = ref('');
    const packageFilterQuery = ref('');
    const specProfileOptions = SPEC_PROFILE_OPTIONS;
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
    const normalizeInlinePrefixes = (prefixes) => Array.isArray(prefixes)
        ? prefixes.map((prefix) => prefix == null ? '' : String(prefix))
        : [];
    const decimalInlinePrefixOptions = ['T', 'G', 'M', 'k', '', 'm', 'u', 'n', 'p', 'f'];
    const byteBitInlinePrefixOptions = ['T', 'G', 'M', 'k', '', 'Ti', 'Gi', 'Mi', 'Ki'];
    const binaryInlineIecPrefixes = new Set(['Ti', 'Gi', 'Mi', 'Ki']);
    const decimalInlineNonFractionalPrefixes = new Set(['T', 'G', 'M', 'k']);
    const decimalInlineFractionalPrefixes = new Set(['m', 'u', 'n', 'p', 'f']);
    const byteBitInlineUnits = new Set(['B', 'bit', 'bps']);
    const normalizeInlineUnitForPrefixPolicy = (unit = '') => String(unit ?? '')
        .trim()
        .replaceAll('μ', 'u')
        .replaceAll('µ', 'u')
        .replaceAll('Ω', 'Ω')
        .replace(/\bohms?\b/iu, 'Ω')
        .replace(/^K(?!i)(?=[A-Za-zΩ])/u, 'k');
    const isInlineByteBitPrefixUnit = (unit = inlineSpecTypeModal.form.unit) => byteBitInlineUnits.has(normalizeInlineUnitForPrefixPolicy(unit));
    const normalizeInlinePrefixToken = (prefix) => {
        const normalized = prefix == null ? '' : String(prefix).trim();
        return normalized === 'K' ? 'k' : normalized;
    };
    const sanitizeInlinePrefixesForUnit = (prefixes = [], unit = inlineSpecTypeModal.form.unit) => {
        const normalized = normalizeInlinePrefixes(prefixes)
            .map(normalizeInlinePrefixToken)
            .filter((prefix) => prefix === '' || decimalInlinePrefixOptions.includes(prefix) || binaryInlineIecPrefixes.has(prefix));
        const unique = [...new Set(normalized)];
        if (!isInlineByteBitPrefixUnit(unit)) {
            return unique.filter((prefix) => !binaryInlineIecPrefixes.has(prefix));
        }

        const withoutFractional = unique.filter((prefix) => !decimalInlineFractionalPrefixes.has(prefix));
        const hasBinary = withoutFractional.some((prefix) => binaryInlineIecPrefixes.has(prefix));
        if (hasBinary) {
            return withoutFractional.filter((prefix) => prefix === '' || binaryInlineIecPrefixes.has(prefix));
        }

        return withoutFractional.filter((prefix) => prefix === '' || decimalInlineNonFractionalPrefixes.has(prefix));
    };
    const inlinePrefixOptionsFor = () => {
        const isByteBit = isInlineByteBitPrefixUnit();
        return (isByteBit ? byteBitInlinePrefixOptions : decimalInlinePrefixOptions)
            .map((prefix) => ({
                value: prefix,
                label: prefix === '' ? '（無印）' : prefix,
                disabled: !isByteBit && binaryInlineIecPrefixes.has(prefix),
            }));
    };
    const inlinePrefixPolicyHelp = computed(() => (
        isInlineByteBitPrefixUnit()
            ? 'B / bit / bps 系は 10進（T G M k）または IEC（Ti Gi Mi Ki）のどちらか一方を使います。無印は共通で使えます。'
            : '単位入力時の候補接頭辞です。未選択なら汎用候補（T G M k 無印 m u n p f）を使います。'
    ));
    const syncInlinePrefixList = (field, changedPrefix = null) => {
        let prefixes = normalizeInlinePrefixes(inlineSpecTypeModal.form[field]).map(normalizeInlinePrefixToken);
        const changed = normalizeInlinePrefixToken(changedPrefix);
        if (isInlineByteBitPrefixUnit() && prefixes.includes(changed)) {
            if (binaryInlineIecPrefixes.has(changed)) {
                prefixes = prefixes.filter((prefix) => !decimalInlineNonFractionalPrefixes.has(prefix) && !decimalInlineFractionalPrefixes.has(prefix));
            } else if (decimalInlineNonFractionalPrefixes.has(changed)) {
                prefixes = prefixes.filter((prefix) => !binaryInlineIecPrefixes.has(prefix) && !decimalInlineFractionalPrefixes.has(prefix));
            }
        }
        inlineSpecTypeModal.form[field] = sanitizeInlinePrefixesForUnit(prefixes);
    };

    // 編集モーダル
    const editModal  = ref({ open: false, section: '', title: '', form: {} });
    // 出庫モーダル
    const stockOutModal = ref({ open: false, blockId: null, maxQty: 0, qty: 1, projectId: '', note: '' });
    // 入庫モーダル（在庫追加）
    const stockInModal  = ref({ open: false, form: { stock_type: 'loose', condition: 'new', quantity: 1, lot_number: '', reel_code: '', location_id: '', note: '' } });

    const stockTypeLabel = { reel: 'リール', tape: 'テープ', tray: 'トレイ', loose: 'バラ', box: '箱' };
    const procurementOptions = [
        { value: 'active', label: '量産中' }, { value: 'eol', label: 'EOL' },
        { value: 'last_time', label: '在庫限り' }, { value: 'nrnd', label: '新規非推奨' },
    ];
    const stockConditionLabel = { new: '新品', used: '中古' };

    const createDatasheetDraft = (sheet = {}) => ({
        id: sheet.id ?? '',
        original_name: sheet.original_name ?? '',
        display_name: sheet.display_name ?? '',
        url: sheet.url ?? '',
    });

    const fetchPart = async () => {
        loading.value = true;
        loadError.value = '';
        try {
            const res = await api.get(`/components/${componentId}`);
            part.value = res.data;
            await fetchSimilar();
        } catch {
            part.value = null;
            loadError.value = '部品情報の取得に失敗しました。URLを確認するか、部品一覧から開き直してください。';
            toastError('部品情報の取得に失敗しました');
        } finally {
            loading.value = false;
        }
    };

    const fetchMasters = async () => {
        try {
            const [categoryRes, packageGroupRes, packageRes, specTypeRes, supplierRes, locationRes] = await Promise.all([
                api.get('/spec-groups'),
                api.get('/package-groups'),
                api.get('/packages'),
                api.get('/spec-types'),
                api.get('/suppliers'),
                api.get('/locations'),
            ]);
            categories.value = categoryRes.data ?? [];
            packageGroups.value = packageGroupRes.data ?? [];
            packages.value = packageRes.data ?? [];
            specTypes.value = specTypeRes.data ?? [];
            suppliers.value = supplierRes.data ?? [];
            locations.value = locationRes.data ?? [];
            void fetchSpecGroupCatalog();
        } catch {
            toastError('編集用の候補取得に失敗しました');
        }
    };
    const defaultSpecGroupIdForPart = () => {
        const category = (part.value?.categories ?? [])
            .find((item) => Number(item?.id) > 0);

        return category?.id ? String(category.id) : 'all';
    };

    // セクション別編集モーダルを開く
    const openEdit = (section) => {
        const p = part.value;
        basicImageFile.value = null;
        basicDatasheetFiles.value = [];
        basicDatasheetLabels.value = [];
        packageFilterQuery.value = '';
        detailCategoryQuery.value = '';
        const forms = {
            basic: {
                title: '基本情報を編集',
                form: {
                    part_number: p.part_number, manufacturer: p.manufacturer ?? '',
                    common_name: p.common_name ?? '', description: p.description ?? '',
                    procurement_status: p.procurement_status,
                    threshold_new: p.threshold_new, threshold_used: p.threshold_used,
                    primary_location_id: p.primary_location_id ?? '',
                    category_ids: p.categories.map(c => c.id),
                    package_group_id: p.package_group?.id ?? p.package?.package_group_id ?? '',
                    package_id: p.package?.id ?? p.packages?.[0]?.id ?? '',
                    datasheets: (p.datasheets ?? []).map((sheet) => createDatasheetDraft({
                        id: sheet.id,
                        original_name: sheet.original_name ?? '',
                        display_name: sheet.display_name ?? '',
                        url: sheet.url ?? '',
                    })),
                },
            },
            specs: {
                title: 'スペックを編集',
                form: { specs: p.specs.map((s) => prepareSpecDraftForEdit(buildSpecDraftFromApi(s))) },
            },
            attributes: {
                title: 'カスタムフィールドを編集',
                form: {
                    attributes: (p.custom_attributes ?? []).map(a => ({ key: a.key ?? '', value: a.value ?? '' })),
                },
            },
            suppliers: {
                title: '仕入先情報を編集',
                form: { suppliers: p.component_suppliers.map(cs => ({
                    supplier_id: cs.supplier_id,
                    supplier_part_number: cs.supplier_part_number ?? '',
                    product_url: cs.product_url ?? '',
                    purchase_unit: cs.purchase_unit ?? '',
                    unit_price: cs.unit_price ?? '',
                    is_preferred: !!cs.is_preferred,
                    price_breaks: (cs.price_breaks ?? []).map(pb => ({ min_qty: pb.min_qty, unit_price: pb.unit_price })),
                })) },
            },
        };
        editModal.value = { open: true, section, ...forms[section] };
        if (section === 'specs') {
            specTypeSearchQuery.value = '';
            selectedSpecGroupId.value = defaultSpecGroupIdForPart();
            selectedSpecTypeId.value = '';
            selectedSpecTemplateId.value = '';
            void ensureSpecGroupDetail(selectedSpecGroupId.value);
            fetchSpecSuggestionsForCurrentPart();
        }
        editModalSnapshot.value = JSON.stringify(editModal.value.form);
    };

    const closeEditModal = async () => {
        if (!editModal.value.open) return;

        const currentSnapshot = JSON.stringify(editModal.value.form);
        const hasFileSelection = !!basicImageFile.value || basicDatasheetFiles.value.length > 0;
        const changed = currentSnapshot !== editModalSnapshot.value || hasFileSelection;

        if (changed && !await ask('未保存の入力があります。このまま閉じますか？')) {
            return;
        }

        editModal.value.open = false;
        basicImageFile.value = null;
        basicDatasheetFiles.value = [];
        basicDatasheetLabels.value = [];
        packageFilterQuery.value = '';
        detailCategoryQuery.value = '';
        editModalSnapshot.value = '';
    };

    const onBasicDatasheetsChange = (event) => {
        basicDatasheetFiles.value = Array.from(event.target.files ?? []);
        basicDatasheetLabels.value = basicDatasheetFiles.value.map((_, index) => basicDatasheetLabels.value[index] ?? '');
    };

    // セクション保存（PATCH / ファイルありの場合は multipart POST + _method=PATCH）
    const saveSection = async () => {
        try {
            const form = editModal.value.form;
            // basic セクションは、部品登録/編集と同じ multipart + full update 経路へ統一する。
            // ファイル有無で経路を分けると、詳細画面だけ保存差分が出やすい。
            if (editModal.value.section === 'basic') {
                const fd = new FormData();
                // テキスト項目
                const textKeys = ['part_number', 'manufacturer', 'common_name', 'description',
                    'procurement_status', 'threshold_new', 'threshold_used', 'primary_location_id', 'package_group_id', 'package_id'];
                textKeys.forEach(k => { if (form[k] != null) fd.append(k, form[k]); });
                (form.category_ids ?? []).forEach(id => fd.append('category_ids[]', id));
                // ファイル項目
                if (basicImageFile.value) fd.append('image', basicImageFile.value);
                basicDatasheetFiles.value.forEach((file, index) => {
                    fd.append(`datasheets[${index}]`, file);
                    fd.append(`datasheet_labels[${index}]`, basicDatasheetLabels.value[index] ?? '');
                });
                if (basicDatasheetFiles.value.length === 0) {
                    (form.datasheets ?? []).forEach((sheet, index) => {
                        fd.append(`existing_datasheets[${index}][id]`, String(sheet.id ?? ''));
                        fd.append(`existing_datasheets[${index}][display_name]`, sheet.display_name ?? '');
                    });
                }
                await api.uploadPut(`/components/${componentId}`, fd);
            } else {
                // ファイルなし → 通常 JSON PATCH（_newImage/_newDatasheets は除外）
                if (editModal.value.section === 'specs') {
                    await resolveSpecTypesBeforeSave();
                    if (!validateSpecsBeforeSave()) return;
                }
                const payload = editModal.value.section === 'specs'
                    ? { specs: (form.specs ?? []).map((spec) => buildSpecPayload(spec)) }
                    : form;
                await api.patch(`/components/${componentId}/${editModal.value.section}`, payload);
            }
            toastSuccess('保存しました');
            editModal.value.open = false;
            basicImageFile.value = null;
            basicDatasheetFiles.value = [];
            basicDatasheetLabels.value = [];
            editModalSnapshot.value = '';
            await fetchPart();
        } catch (e) {
            console.error('[component-detail save failed]', e);
            toastError(e.message);
        }
    };

    // 出庫
    const openStockOut = (block) => {
        stockOutModal.value = { open: true, blockId: block.id, maxQty: block.quantity, qty: 1, projectId: '', note: '' };
    };
    const submitStockOut = async () => {
        try {
            await api.post(`/components/${componentId}/stock-out`, {
                inventory_block_id: stockOutModal.value.blockId,
                quantity: stockOutModal.value.qty,
                project_id: stockOutModal.value.projectId || null,
                note: stockOutModal.value.note,
            });
            toastSuccess('出庫しました');
            stockOutModal.value.open = false;
            await fetchPart();
        } catch (e) {
            toastError(e.message);
        }
    };

    // 入庫
    const submitStockIn = async () => {
        try {
            await api.post(`/components/${componentId}/stock-in`, stockInModal.value.form);
            toastSuccess('入庫しました');
            stockInModal.value.open = false;
            await fetchPart();
        } catch (e) {
            toastError(e.message);
        }
    };

    // 類似部品
    const similarParts = ref([]);
    const similarLoading = ref(false);
    const similarError = ref('');
    const fetchSimilar = async () => {
        if (similarLoading.value) return;
        similarLoading.value = true;
        similarError.value = '';
        similarParts.value = [];
        try {
            const r = await api.get(`/components/${componentId}/similar`);
            similarParts.value = r.data;
        } catch {
            similarError.value = '類似部品の取得に失敗しました。比較画面へ進むか、再試行してください。';
        }
        finally { similarLoading.value = false; }
    };

    // ページURLをクリップボードにコピー
    const copyLink = () => {
        const url = location.href;
        if (navigator.clipboard) {
            navigator.clipboard.writeText(url).then(() => toastSuccess('URLをコピーしました'));
        } else {
            const el = document.createElement('textarea');
            el.value = url;
            document.body.appendChild(el);
            el.select();
            document.execCommand('copy');
            document.body.removeChild(el);
            toastSuccess('URLをコピーしました');
        }
    };

    // 論理削除
    const deletePart = async () => {
        if (!confirm('この部品を削除しますか？')) return;
        try {
            await api.delete(`/components/${componentId}`);
            toastSuccess('削除しました');
            setTimeout(() => { location.href = '/components'; }, 1000);
        } catch (e) {
            toastError(e.message);
        }
    };

    const handleToggleFavorite = async () => {
        try {
            const wasFavorite = isFavorite(componentId);
            await toggleFavorite(componentId);
            toastSuccess(wasFavorite ? 'お気に入りから外しました' : 'お気に入りに追加しました');
        } catch {
            toastError('お気に入りの保存に失敗しました');
        }
    };

    onMounted(async () => {
        window.addEventListener('click', closeToleranceGradeMenu);
        await loadFavorites();
        await Promise.all([fetchPart(), fetchMasters()]);
    });

    onBeforeUnmount(() => {
        window.removeEventListener('click', closeToleranceGradeMenu);
    });

    const preferredSupplier = computed(() => {
        const suppliers = part.value?.component_suppliers ?? [];
        return suppliers.find((item) => item.is_preferred) ?? suppliers[0] ?? null;
    });

    const stockSummary = computed(() => {
        const blocks = part.value?.inventory_blocks ?? [];
        return blocks.reduce((acc, block) => {
            if (block.condition === 'used') acc.used += block.quantity ?? 0;
            else acc.new += block.quantity ?? 0;
            return acc;
        }, { new: 0, used: 0 });
    });

    const allTransactions = computed(() => part.value?.transactions ?? []);
    const displayedTransactions = computed(() => showAllTransactions.value ? allTransactions.value : allTransactions.value.slice(0, 5));
    const hasMoreTransactions = computed(() => allTransactions.value.length > 5);
    const outgoingTransactions = computed(() => allTransactions.value.filter((tx) => tx.type === 'out'));
    const incomingTransactions = computed(() => allTransactions.value.filter((tx) => tx.type === 'in'));
    const formatTransactionTimestamp = (value) => {
        if (!value) return '—';
        return String(value).replace('T', ' ').substring(0, 19).replaceAll('-', '/');
    };
    const canSaveEditModal = computed(() => {
        if (!editModal.value.open) return true;
        if (editModal.value.section !== 'attributes') return true;

        const attributes = editModal.value.form?.attributes ?? [];
        const normalized = attributes
            .map((attr) => ({
                key: String(attr.key ?? '').trim(),
                value: String(attr.value ?? '').trim(),
            }))
            .filter((attr) => attr.key !== '' || attr.value !== '');

        if (normalized.length === 0) return false;
        if (normalized.some((attr) => attr.key === '' || attr.value === '')) return false;

        const keys = normalized.map((attr) => attr.key);
        return new Set(keys).size === keys.length;
    });

    const filteredDetailPackages = computed(() => {
        const groupId = editModal.value.form?.package_group_id;
        if (!groupId) return [];

        const scopedPackages = packages.value.filter((item) => item.package_group_id === Number(groupId));
        const q = packageFilterQuery.value.trim().toLowerCase();
        if (!q) return scopedPackages;
        return scopedPackages.filter((item) => item.name.toLowerCase().includes(q));
    });

    const filteredDetailCategories = computed(() => {
        const q = detailCategoryQuery.value.trim().toLowerCase();
        if (!q) return categories.value;
        return categories.value.filter((item) => item.name.toLowerCase().includes(q));
    });

    const toggleDetailCategory = (categoryId) => {
        const ids = editModal.value.form?.category_ids ?? [];
        editModal.value.form.category_ids = ids.includes(categoryId)
            ? ids.filter((value) => value !== categoryId)
            : [...ids, categoryId];
    };

    const handlePackageGroupChange = () => {
        const groupId = editModal.value.form?.package_group_id;
        packageFilterQuery.value = '';
        if (!groupId) {
            editModal.value.form.package_id = '';
            return;
        }

        const selectedPackage = packages.value.find((item) => item.id === Number(editModal.value.form.package_id));
        if (!selectedPackage || selectedPackage.package_group_id !== Number(groupId)) {
            editModal.value.form.package_id = '';
        }
    };

    const getSpecTypeById = (specTypeId) =>
        specTypes.value.find((item) => Number(item.id) === Number(specTypeId)) ?? null;

    const isToleranceSpecType = (specType) => (specType?.spec_kind ?? 'normal') === 'tolerance';
    const defaultToleranceSettings = (fallbackUnit = '%') => ({
        default_mode: 'symmetric',
        default_unit: fallbackUnit || '%',
        allowed_units: [fallbackUnit || '%'],
        grade_options: [],
    });
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
    const toleranceSettingsForSpecType = (specType) =>
        normalizeToleranceSettings(specType?.tolerance_settings, specType?.base_unit ?? specType?.units?.[0]?.unit ?? '%');
    const specTypeForSpec = (spec) => getSpecTypeById(spec?.spec_type_id);
    const isToleranceSpecRow = (spec) => isToleranceSpecType(specTypeForSpec(spec));
    const toleranceSettingsForSpec = (spec) => toleranceSettingsForSpecType(specTypeForSpec(spec));
    const toleranceUnitOptionsFor = (spec) => toleranceSettingsForSpec(spec).allowed_units;
    const toleranceValuePlaceholder = (spec) => {
        const mode = toleranceSettingsForSpec(spec).default_mode;
        if (mode === 'grade') return '例: J / 5 / +80/-20';
        if (mode === 'asymmetric') return '例: +80/-20';

        return '例: 5 / ±5';
    };
    const toleranceGradeOptionsFor = (spec) => toleranceSettingsForSpec(spec).grade_options;
    const toleranceGradeOptionLabel = (option) => {
        const label = String(option?.label ?? option?.rank ?? '').trim();
        const unit = String(option?.unit ?? '').trim();
        if (option?.plus !== undefined || option?.minus !== undefined) {
            return `${label} +${option?.plus ?? ''}/-${option?.minus ?? ''}${unit}`;
        }
        if (option?.value !== undefined) return `${label} ±${option.value}${unit}`;
        return label;
    };
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
    const applyToleranceGradeOption = (spec, option) => {
        spec.value_profile = 'typ';
        spec.value_typ = toleranceGradeOptionValue(option);
        spec.unit = String(option?.unit ?? '').trim() || toleranceSettingsForSpec(spec).default_unit || spec.unit || '%';
    };
    const activeToleranceGradeMenu = ref('');
    const toleranceGradeMenuKey = (scope, index) => `${scope}-${index}`;
    const isToleranceGradeMenuOpen = (scope, index) => activeToleranceGradeMenu.value === toleranceGradeMenuKey(scope, index);
    const closeToleranceGradeMenu = () => {
        activeToleranceGradeMenu.value = '';
    };
    const toggleToleranceGradeMenu = (scope, index, spec) => {
        if (!toleranceGradeOptionsFor(spec).length) {
            closeToleranceGradeMenu();
            return;
        }

        const key = toleranceGradeMenuKey(scope, index);
        activeToleranceGradeMenu.value = activeToleranceGradeMenu.value === key ? '' : key;
    };
    const selectToleranceGradeOption = (spec, option) => {
        applyToleranceGradeOption(spec, option);
        closeToleranceGradeMenu();
    };
    const prepareSpecDraftForEdit = (spec) => applyToleranceDefaults(spec);

    const specTypeOptionLabel = (specType) => {
        const primary = String(specType?.name_ja ?? specType?.name ?? '').trim();
        const symbol = String(specType?.symbol ?? '').trim();
        const english = String(specType?.name_en ?? '').trim();
        const suffix = [symbol, english].filter(Boolean).join(' / ');

        return suffix ? `${primary} (${suffix})` : primary;
    };
    const specTypeShortLabel = (specType) => {
        const primary = String(specType?.name_ja ?? specType?.name ?? '').trim();
        const symbol = String(specType?.symbol ?? '').trim();

        return [primary, symbol].filter(Boolean).join(' ');
    };
    const normalizeName = (value) => String(value ?? '').toLowerCase().replace(/[\s()\[\]_.-]/gu, '');
    const specTypeSearchText = (item) => [
        item?.name,
        item?.name_ja,
        item?.name_en,
        item?.symbol,
        ...(item?.aliases ?? []).map((alias) => alias.alias),
    ].filter(Boolean).join(' ');
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
    const handleSpecTypeSelection = (spec) => {
        const selected = getSpecTypeById(spec?.spec_type_id);
        if (selected) {
            spec.spec_type_name = selected.name_ja ?? selected.name ?? '';
            applyToleranceDefaults(spec, selected);
        } else {
            spec.spec_type_name = '';
        }
    };
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
    const sortSpecTypes = (items) => [...items].sort((a, b) => {
        const sortOrder = Number(a.sort_order ?? 0) - Number(b.sort_order ?? 0);
        return sortOrder || String(a.name_ja ?? a.name ?? '').localeCompare(String(b.name_ja ?? b.name ?? ''), 'ja');
    });
    const normalizeSpecGroupId = (value) => {
        if (value === '' || value === null || value === undefined) return '';
        return String(value);
    };
    const groupSpecTypes = (group) => group?.spec_types ?? group?.specTypes ?? [];
    const specGroupOptions = computed(() => categories.value ?? []);
    const groupTemplates = (group) => group?.templates ?? [];
    const allSpecTemplates = computed(() =>
        specGroupOptions.value.flatMap((group) =>
            groupTemplates(group).map((template) => ({
                ...template,
                spec_group_id: template.spec_group_id ?? group.id,
            }))
        )
    );
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
    const fetchSpecGroupCatalog = async () => {
        try {
            const res = await api.get('/spec-groups?with_spec_types=1&with_templates=1');
            mergeSpecGroupDetails(res.data ?? []);
        } catch {
            // 基本の部品分類一覧は維持する。候補詳細は選択時に再取得する。
        }
    };
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
    const specGroupCandidatePayload = (specType, index = 0) => ({
        spec_type_id: Number(specType.id),
        sort_order: Number(specType?.pivot?.sort_order ?? specType?.sort_order ?? ((index + 1) * 10)),
        is_required: Boolean(specType?.pivot?.is_required ?? false),
        is_recommended: Boolean(specType?.pivot?.is_recommended ?? true),
        default_profile: specType?.pivot?.default_profile ?? 'typ',
        default_unit: specType?.pivot?.default_unit ?? specType?.base_unit ?? specType?.units?.[0]?.unit ?? null,
        note: specType?.pivot?.note ?? null,
    });
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
    const specTypePickerOptionLabel = (specType) => {
        const label = specTypeOptionLabel(specType);
        return isAllSpecTypesSelected.value && recommendedSpecTypeIds.value.has(Number(specType?.id))
            ? `${label}（推奨）`
            : label;
    };
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
    const specTemplateGroup = (template) =>
        specGroupOptions.value.find((group) => Number(group.id) === Number(template?.spec_group_id)) ?? null;
    const specTemplateLabel = (template) => {
        const groupName = specTemplateGroup(template)?.name;
        return groupName ? `${template.name} / ${groupName}` : template.name;
    };
    const templateItemSpecType = (item) => item?.spec_type ?? item?.specType ?? getSpecTypeById(item?.spec_type_id);
    const templateItemProfile = (item) => normalizeSpecProfile(item?.value_profile ?? item?.default_profile ?? 'typ');
    const templateItemUnit = (item, specType = null) =>
        String(item?.unit ?? item?.default_unit ?? specType?.base_unit ?? specType?.units?.[0]?.unit ?? '').trim();
    const specTemplatePreviewItems = computed(() =>
        (selectedSpecTemplate.value?.items ?? []).map((item) => {
            const specType = templateItemSpecType(item);
            return {
                id: item?.id ?? `${item?.spec_type_id ?? specType?.id ?? 'no-type'}-${templateItemProfile(item)}`,
                label: specTypeShortLabel(specType) || String(item?.name ?? item?.name_ja ?? 'スペック詳細未設定').trim(),
            };
        })
    );
    const hasSpecTypeRow = (specTypeId, rows = editModal.value.form?.specs ?? []) =>
        rows.some((spec) => Number(spec.spec_type_id) === Number(specTypeId));
    const buildSpecRowFromSpecType = (specType) => ({
        ...createEmptySpecRow(),
        spec_type_id: specType?.id ?? '',
        spec_type_name: specType?.name_ja ?? specType?.name ?? '',
        value_profile: normalizeSpecProfile('typ'),
        unit: String(isToleranceSpecType(specType)
            ? toleranceSettingsForSpecType(specType).default_unit
            : (specType?.base_unit ?? specType?.units?.[0]?.unit ?? '')).trim(),
    });
    const buildSpecRowFromTemplateItem = (item) => {
        const specType = templateItemSpecType(item);

        return applyToleranceDefaults({
            ...createEmptySpecRow(),
            spec_type_id: item?.spec_type_id ?? specType?.id ?? '',
            spec_type_name: specType?.name_ja ?? specType?.name ?? '',
            value_profile: templateItemProfile(item),
            unit: templateItemUnit(item, specType),
        }, specType);
    };
    const syncSpecPickerSelections = () => {
        if (selectedSpecTypeId.value && !filteredSpecTypesForPicker().some((item) => String(item.id) === String(selectedSpecTypeId.value))) {
            selectedSpecTypeId.value = '';
        }
        if (selectedSpecTemplateId.value && !visibleSpecTemplates.value.some((item) => String(item.id) === String(selectedSpecTemplateId.value))) {
            selectedSpecTemplateId.value = '';
        }
    };
    const handleSpecGroupPickerChange = () => {
        specTypeSearchQuery.value = '';
        selectedSpecTypeId.value = '';
        selectedSpecTemplateId.value = '';
        void ensureSpecGroupDetail(selectedSpecGroupId.value);
        syncSpecPickerSelections();
    };
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
        const unit = String(spec?.unit ?? '').trim();

        inlineSpecTypeModal.targetSpec = spec;
        inlineSpecTypeModal.form = {
            name_ja: nameJa,
            name_en: String(spec?.name_en ?? '').trim(),
            symbol: String(spec?.symbol ?? '').trim(),
            description: String(spec?.description ?? '').trim(),
            value_type: spec?.value_type ?? 'numeric',
            unit,
            suggest_prefixes: sanitizeInlinePrefixesForUnit(spec?.suggest_prefixes ?? [], unit),
            display_prefixes: sanitizeInlinePrefixesForUnit(spec?.display_prefixes ?? [], unit),
            aliases_text: aliases.join('\n'),
        };
        inlineSpecTypeModal.open = true;
    };
    const closeInlineSpecTypeModal = (force = false) => {
        if (inlineSpecTypeModal.saving && force !== true) return;
        inlineSpecTypeModal.open = false;
        inlineSpecTypeModal.targetSpec = null;
        inlineSpecTypeModal.form = createInlineSpecTypeForm();
    };
    const fetchSpecTypes = async () => {
        try {
            const res = await api.get('/spec-types');
            specTypes.value = res.data ?? [];
        } catch {
            // 保存処理側で必要なエラーを出す。
        }
    };
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
            const unit = String(spec?.unit ?? '').trim();
            const suggestPrefixes = sanitizeInlinePrefixesForUnit(spec?.suggest_prefixes ?? [], unit);
            const displayPrefixes = sanitizeInlinePrefixesForUnit(spec?.display_prefixes ?? [], unit);
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
            }
            closeInlineSpecTypeModal(true);
        } finally {
            inlineSpecTypeModal.saving = false;
        }
    };
    const resolveSpecTypesBeforeSave = async () => {
        for (const spec of editModal.value.form?.specs ?? []) {
            handleSpecTypeSelection(spec);
        }
    };
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
    const getUnitSuggestions = (specTypeId) => getSpecUnitSuggestions(getSpecTypeById(specTypeId));
    const specPreview = (spec) => normalizeSpecDraft(spec, getSpecTypeById(spec.spec_type_id));
    const specDisplayName = (spec) => getSpecDisplayName(spec, getSpecTypeById(spec?.spec_type_id));
    const specProfileBadge = (spec) => getSpecProfileBadgeLabel(spec?.value_profile);

    return {
        toasts, part, loading, loadError, componentId,
        sections, stockTypeLabel, stockConditionLabel, procurementOptions,
        categories, packageGroups, packages, specTypes, specGroups, specGroupOptions, specSuggestionTypes, specTemplates, specSuggestionLoading, suppliers, locations,
        preferredSupplier, stockSummary, allTransactions, displayedTransactions, hasMoreTransactions, showAllTransactions,
        outgoingTransactions, incomingTransactions,
        formatTransactionTimestamp,
        canSaveEditModal,
        specProfileOptions, createEmptySpecRow, getUnitSuggestions, specPreview, specDisplayName, specProfileBadge,
        canCreateSpecType, inlineSpecTypeModal, specTypeOptionLabel, specTypePickerOptionLabel,
        inlinePrefixOptionsFor, inlinePrefixPolicyHelp, syncInlinePrefixList,
        selectedSpecGroupId, selectedSpecGroupLabel, selectedSpecTypeId, selectedSpecTemplateId,
        selectedSpecTemplate, visibleSpecTemplates, specTemplatePreviewItems, specTemplateLabel,
        masterSpecGroupUrl,
        scopedSpecTypes, filteredSpecTypesForPicker, specTypeSearchQuery,
        handleSpecGroupPickerChange, addSelectedSpecType, applySelectedSpecTemplate,
        handleSpecTypeSelection, openInlineSpecTypeModal, closeInlineSpecTypeModal, saveInlineSpecType, changeSpecProfile,
        isToleranceSpecRow, toleranceUnitOptionsFor, toleranceValuePlaceholder,
        toleranceGradeOptionsFor, toleranceGradeOptionLabel,
        isToleranceGradeMenuOpen, toggleToleranceGradeMenu, closeToleranceGradeMenu, selectToleranceGradeOption,
        packageFilterQuery, filteredDetailPackages, handlePackageGroupChange,
        detailCategoryQuery, filteredDetailCategories, toggleDetailCategory,
        basicImageFile, basicDatasheetFiles, basicDatasheetLabels, onBasicDatasheetsChange,
        editModal, openEdit, closeEditModal, saveSection,
        stockOutModal, openStockOut, submitStockOut,
        stockInModal, submitStockIn,
        handleToggleFavorite, isFavorite,
        copyLink, deletePart,
        similarParts, similarLoading, similarError, fetchSimilar, fetchPart,
        formatCurrency,
    };
}
