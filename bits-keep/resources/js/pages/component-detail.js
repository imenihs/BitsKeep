import { ref, computed, onMounted, reactive } from 'vue';
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
        unit: '',
        aliases_text: '',
    });
    const inlineSpecTypeModal = reactive({
        open: false,
        saving: false,
        targetSpec: null,
        form: createInlineSpecTypeForm(),
    });

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
                api.get('/categories'),
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
        } catch {
            toastError('編集用の候補取得に失敗しました');
        }
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
                form: { specs: p.specs.map((s) => buildSpecDraftFromApi(s)) },
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
        await loadFavorites();
        await Promise.all([fetchPart(), fetchMasters()]);
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

    const specTypeOptionLabel = (specType) => {
        const primary = String(specType?.name_ja ?? specType?.name ?? '').trim();
        const symbol = String(specType?.symbol ?? '').trim();
        const english = String(specType?.name_en ?? '').trim();
        const suffix = [symbol, english].filter(Boolean).join(' / ');

        return suffix ? `${primary} (${suffix})` : primary;
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
    const openInlineSpecTypeModal = (spec = null) => {
        if (!canCreateSpecType.value) return;

        const selected = getSpecTypeById(spec?.spec_type_id);
        const rawName = String(spec?.name ?? '').trim();
        const nameJa = String(spec?.name_ja ?? '').trim()
            || (selected ? '' : String(spec?.spec_type_name ?? '').trim())
            || rawName;
        const aliases = [rawName].filter((value) => value && normalizeName(value) !== normalizeName(nameJa));

        inlineSpecTypeModal.targetSpec = spec;
        inlineSpecTypeModal.form = {
            name_ja: nameJa,
            name_en: String(spec?.name_en ?? '').trim(),
            symbol: String(spec?.symbol ?? '').trim(),
            unit: String(spec?.unit ?? '').trim(),
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

        const existing = matchSpecTypeByName(name);
        if (existing) return existing;

        try {
            const res = await api.post('/spec-types', {
                name,
                name_ja: nameJa || name,
                name_en: String(spec?.name_en ?? '').trim(),
                symbol: String(spec?.symbol ?? '').trim(),
                unit: String(spec?.unit ?? '').trim(),
                aliases: buildSpecTypeAliases(
                    spec?.aliases_text ?? '',
                    [spec?.name],
                    [name, nameJa, spec?.name_en, spec?.symbol]
                ),
                sort_order: (specTypes.value.at(-1)?.sort_order ?? 0) + 10,
            });
            specTypes.value = sortSpecTypes([...specTypes.value, res.data]);
            toastSuccess(`スペック種別を追加しました: ${name}`);
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
            toastError('スペック種別の日本語名を入力してください');
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
        toastError(`スペック種別が未選択です: ${labels.join('、')}${suffix}`);
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

    return {
        toasts, part, loading, loadError, componentId,
        sections, stockTypeLabel, stockConditionLabel, procurementOptions,
        categories, packageGroups, packages, specTypes, suppliers, locations,
        preferredSupplier, stockSummary, allTransactions, displayedTransactions, hasMoreTransactions, showAllTransactions,
        outgoingTransactions, incomingTransactions,
        formatTransactionTimestamp,
        canSaveEditModal,
        specProfileOptions, createEmptySpecRow, getUnitSuggestions, specPreview, specDisplayName,
        canCreateSpecType, inlineSpecTypeModal, specTypeOptionLabel,
        handleSpecTypeSelection, openInlineSpecTypeModal, closeInlineSpecTypeModal, saveInlineSpecType, changeSpecProfile,
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
