import { ref, computed, onMounted, onBeforeUnmount, reactive } from 'vue';
import { api } from '../api.js';
import { useToast } from '../composables/useToast.js';
import { useFavoriteComponents } from '../composables/useFavoriteComponents.js';
import { useFormatter } from '../composables/useFormatter.js';
import { useConfirmModal } from '../composables/useConfirmModal.js';
import { buildSpecDraftFromApi, buildSpecPayload } from '../utils/specValue.js';
import { useComponentDetailSpecs } from './component-detail/specs.js';

/**
 * 部品詳細画面の公開setup。
 * 入力はBladeのdata-idで、戻り値は表示データ、編集モーダル、在庫操作、スペック編集操作をVueテンプレートへ公開する。
 * 動作条件は部品詳細APIが参照可能なことで、部品取得、セクション保存、入出庫、お気に入り更新、トースト表示の副作用を持つ。
 */
// 目的: 部品詳細画面のsetupを扱う。機能: 入力値を検証・整形し、画面または計算処理へ渡す。入力: 関数シグネチャの値。出力: 処理結果またはなし。動作条件: 部品詳細画面の初期化後に呼び出す。副作用: Vue状態、localStorage、DOM、HTTP通信のいずれかを更新する場合がある。
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
    const mastersLoaded = ref(false);
    const mastersLoading = ref(false);
    let masterLoadPromise = null;
    const detailCategoryQuery = ref('');
    const showAllTransactions = ref(false);
    const basicImageFile = ref(null);
    const basicDatasheetFiles = ref([]);
    const basicDatasheetLabels = ref([]);
    const editModalSnapshot = ref('');
    const packageFilterQuery = ref('');
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

    // 目的: 部品詳細画面のcreate Datasheet Draftを扱う。機能: 入力値を検証・整形し、画面または計算処理へ渡す。入力: 関数シグネチャの値。出力: 表示値、配列、オブジェクト、数値のいずれか。動作条件: 部品詳細画面の初期化後に呼び出す。副作用: なし。
    const createDatasheetDraft = (sheet = {}) => ({
        id: sheet.id ?? '',
        original_name: sheet.original_name ?? '',
        display_name: sheet.display_name ?? '',
        url: sheet.url ?? '',
    });

    // 目的: 部品詳細画面のfetch Partを扱う。機能: 入力値を検証・整形し、画面または計算処理へ渡す。入力: 関数シグネチャの値。出力: 処理結果またはなし。動作条件: 部品詳細画面の初期化後に呼び出す。副作用: Vue状態、localStorage、DOM、HTTP通信のいずれかを更新する場合がある。
    const fetchPart = async () => {
        loading.value = true;
        loadError.value = '';
        try {
            const res = await api.get(`/components/${componentId}`);
            part.value = res.data;
            void fetchSimilar();
        } catch {
            part.value = null;
            loadError.value = '部品情報の取得に失敗しました。URLを確認するか、部品一覧から開き直してください。';
            toastError('部品情報の取得に失敗しました');
        } finally {
            loading.value = false;
        }
    };

    // 目的: 部品詳細画面のfetch Mastersを扱う。機能: 入力値を検証・整形し、画面または計算処理へ渡す。入力: 関数シグネチャの値。出力: 処理結果またはなし。動作条件: 部品詳細画面の初期化後に呼び出す。副作用: Vue状態、localStorage、DOM、HTTP通信のいずれかを更新する場合がある。
    const fetchMasters = async () => {
        if (mastersLoaded.value) return Promise.resolve();
        if (masterLoadPromise) return masterLoadPromise;

        mastersLoading.value = true;
        masterLoadPromise = (async () => {
            try {
                const [categoryRes, packageGroupRes, packageRes, specTypeRes, supplierRes, locationRes] = await Promise.all([
                    api.get('/spec-groups'),
                    api.get('/package-groups'),
                    api.get('/packages'),
                    api.get('/spec-types'),
                    api.get('/suppliers'),
                    api.get('/locations'),
                ]);
                mergeSpecGroupDetails(categoryRes.data ?? []);
                packageGroups.value = packageGroupRes.data ?? [];
                packages.value = packageRes.data ?? [];
                specTypes.value = specTypeRes.data ?? [];
                suppliers.value = supplierRes.data ?? [];
                locations.value = locationRes.data ?? [];
                mastersLoaded.value = true;
            } catch {
                toastError('編集用の候補取得に失敗しました');
            } finally {
                mastersLoading.value = false;
                masterLoadPromise = null;
            }
        })();

        return masterLoadPromise;
    };
    // 目的: 部品詳細画面のensure Masters Loadedを扱う。機能: 入力値を検証・整形し、画面または計算処理へ渡す。入力: 関数シグネチャの値。出力: 処理結果またはなし。動作条件: 部品詳細画面の初期化後に呼び出す。副作用: Vue状態、localStorage、DOM、HTTP通信のいずれかを更新する場合がある。
    const ensureMastersLoaded = () => fetchMasters();
    const editMasterDataLoading = computed(() =>
        editModal.value.open
        && editModal.value.section !== 'attributes'
        && mastersLoading.value
        && !mastersLoaded.value
    );
    // 目的: 部品詳細画面のdefault Spec Group Id For Partを扱う。機能: 入力値を検証・整形し、画面または計算処理へ渡す。入力: 関数シグネチャの値。出力: 表示値、配列、オブジェクト、数値のいずれか。動作条件: 部品詳細画面の初期化後に呼び出す。副作用: なし。
    const defaultSpecGroupIdForPart = () => {
        const category = (part.value?.categories ?? [])
            .find((item) => Number(item?.id) > 0);

        return category?.id ? String(category.id) : 'all';
    };

    // セクション別編集モーダルを開く
    // 目的: 部品詳細画面のopen Editを扱う。機能: 入力値を検証・整形し、画面または計算処理へ渡す。入力: 関数シグネチャの値。出力: 処理結果またはなし。動作条件: 部品詳細画面の初期化後に呼び出す。副作用: Vue状態、localStorage、DOM、HTTP通信のいずれかを更新する場合がある。
    const openEdit = (section) => {
        const p = part.value;
        if (!p) return;

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
        if (section !== 'attributes') {
            void ensureMastersLoaded().then(() => {
                if (editModal.value.open && editModal.value.section === 'specs') {
                    editModal.value.form.specs.forEach((spec) => handleSpecTypeSelection(spec));
                    syncSpecPickerSelections();
                }
            });
        }
        if (section === 'specs') {
            specTypeSearchQuery.value = '';
            selectedSpecGroupId.value = defaultSpecGroupIdForPart();
            selectedSpecTypeId.value = '';
            selectedSpecTemplateId.value = '';
            void ensureSpecGroupDetail(selectedSpecGroupId.value);
            void fetchSpecGroupCatalog();
            void fetchSpecSuggestionsForCurrentPart();
        }
        editModalSnapshot.value = JSON.stringify(editModal.value.form);
    };

    // 目的: 部品詳細画面のclose Edit Modalを扱う。機能: 入力値を検証・整形し、画面または計算処理へ渡す。入力: 関数シグネチャの値。出力: 処理結果またはなし。動作条件: 部品詳細画面の初期化後に呼び出す。副作用: Vue状態、localStorage、DOM、HTTP通信のいずれかを更新する場合がある。
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

    // 目的: 部品詳細画面のon Basic Datasheets Changeを扱う。機能: 入力値を検証・整形し、画面または計算処理へ渡す。入力: 関数シグネチャの値。出力: 処理結果またはなし。動作条件: 部品詳細画面の初期化後に呼び出す。副作用: Vue状態、localStorage、DOM、HTTP通信のいずれかを更新する場合がある。
    const onBasicDatasheetsChange = (event) => {
        basicDatasheetFiles.value = Array.from(event.target.files ?? []);
        basicDatasheetLabels.value = basicDatasheetFiles.value.map((_, index) => basicDatasheetLabels.value[index] ?? '');
    };

    /**
     * 編集モーダルの対象セクションを保存する。
     * 入力はeditModal.formと選択ファイルで、出力は保存後に再取得されたpart状態。
     * basicはmultipart更新、specsはスペックpayloadへ変換し、API送信、モーダル終了、ファイル選択破棄、トースト表示の副作用を持つ。
     */
    // 目的: 部品詳細画面のsave Sectionを扱う。機能: 入力値を検証・整形し、画面または計算処理へ渡す。入力: 関数シグネチャの値。出力: 処理結果またはなし。動作条件: 部品詳細画面の初期化後に呼び出す。副作用: Vue状態、localStorage、DOM、HTTP通信のいずれかを更新する場合がある。
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
    // 目的: 部品詳細画面のopen Stock Outを扱う。機能: 入力値を検証・整形し、画面または計算処理へ渡す。入力: 関数シグネチャの値。出力: 処理結果またはなし。動作条件: 部品詳細画面の初期化後に呼び出す。副作用: Vue状態、localStorage、DOM、HTTP通信のいずれかを更新する場合がある。
    const openStockOut = (block) => {
        stockOutModal.value = { open: true, blockId: block.id, maxQty: block.quantity, qty: 1, projectId: '', note: '' };
    };
    // 目的: 部品詳細画面のsubmit Stock Outを扱う。機能: 入力値を検証・整形し、画面または計算処理へ渡す。入力: 関数シグネチャの値。出力: 処理結果またはなし。動作条件: 部品詳細画面の初期化後に呼び出す。副作用: Vue状態、localStorage、DOM、HTTP通信のいずれかを更新する場合がある。
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
    // 目的: 部品詳細画面のopen Stock Inを扱う。機能: 入力値を検証・整形し、画面または計算処理へ渡す。入力: 関数シグネチャの値。出力: 処理結果またはなし。動作条件: 部品詳細画面の初期化後に呼び出す。副作用: Vue状態、localStorage、DOM、HTTP通信のいずれかを更新する場合がある。
    const openStockIn = async () => {
        await ensureMastersLoaded();
        stockInModal.value.form.location_id = part.value?.primary_location_id || '';
        stockInModal.value.open = true;
    };
    // 目的: 部品詳細画面のsubmit Stock Inを扱う。機能: 入力値を検証・整形し、画面または計算処理へ渡す。入力: 関数シグネチャの値。出力: 処理結果またはなし。動作条件: 部品詳細画面の初期化後に呼び出す。副作用: Vue状態、localStorage、DOM、HTTP通信のいずれかを更新する場合がある。
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
    // 目的: 部品詳細画面のfetch Similarを扱う。機能: 入力値を検証・整形し、画面または計算処理へ渡す。入力: 関数シグネチャの値。出力: 処理結果またはなし。動作条件: 部品詳細画面の初期化後に呼び出す。副作用: Vue状態、localStorage、DOM、HTTP通信のいずれかを更新する場合がある。
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
    // 目的: 部品詳細画面のcopy Linkを扱う。機能: 入力値を検証・整形し、画面または計算処理へ渡す。入力: 関数シグネチャの値。出力: 表示値、配列、オブジェクト、数値のいずれか。動作条件: 部品詳細画面の初期化後に呼び出す。副作用: なし。
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
    // 目的: 部品詳細画面のdelete Partを扱う。機能: 入力値を検証・整形し、画面または計算処理へ渡す。入力: 関数シグネチャの値。出力: 処理結果またはなし。動作条件: 部品詳細画面の初期化後に呼び出す。副作用: Vue状態、localStorage、DOM、HTTP通信のいずれかを更新する場合がある。
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

    // 目的: 部品詳細画面のhandle Toggle Favoriteを扱う。機能: 入力値を検証・整形し、画面または計算処理へ渡す。入力: 関数シグネチャの値。出力: 処理結果またはなし。動作条件: 部品詳細画面の初期化後に呼び出す。副作用: Vue状態、localStorage、DOM、HTTP通信のいずれかを更新する場合がある。
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
        void loadFavorites();
        await fetchPart();
        window.setTimeout(() => {
            void ensureMastersLoaded();
        }, 250);
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
    // 目的: 部品詳細画面のformat Transaction Timestampを扱う。機能: 入力値を検証・整形し、画面または計算処理へ渡す。入力: 関数シグネチャの値。出力: 表示値、配列、オブジェクト、数値のいずれか。動作条件: 部品詳細画面の初期化後に呼び出す。副作用: なし。
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
    // 目的: 部品詳細画面のdetail Category Nameを扱う。機能: 入力値を検証・整形し、画面または計算処理へ渡す。入力: 関数シグネチャの値。出力: 処理結果またはなし。動作条件: 部品詳細画面の初期化後に呼び出す。副作用: Vue状態、localStorage、DOM、HTTP通信のいずれかを更新する場合がある。
    const detailCategoryName = (categoryId) =>
        categories.value.find((item) => Number(item.id) === Number(categoryId))?.name
        ?? (part.value?.categories ?? []).find((item) => Number(item.id) === Number(categoryId))?.name
        ?? '部品分類';

    // 目的: 部品詳細画面のtoggle Detail Categoryを扱う。機能: 入力値を検証・整形し、画面または計算処理へ渡す。入力: 関数シグネチャの値。出力: 処理結果またはなし。動作条件: 部品詳細画面の初期化後に呼び出す。副作用: なし。
    const toggleDetailCategory = (categoryId) => {
        const ids = editModal.value.form?.category_ids ?? [];
        editModal.value.form.category_ids = ids.includes(categoryId)
            ? ids.filter((value) => value !== categoryId)
            : [...ids, categoryId];
    };

    // 目的: 部品詳細画面のhandle Package Group Changeを扱う。機能: 入力値を検証・整形し、画面または計算処理へ渡す。入力: 関数シグネチャの値。出力: 処理結果またはなし。動作条件: 部品詳細画面の初期化後に呼び出す。副作用: Vue状態、localStorage、DOM、HTTP通信のいずれかを更新する場合がある。
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


    const componentSpecs = useComponentDetailSpecs({
        part, editModal, categories, specTypes, specGroups, specSuggestionTypes, specTemplates,
        selectedSpecGroupId, selectedSpecTypeId, selectedSpecTemplateId, specTypeSearchQuery,
        specSuggestionLoading, canCreateSpecType, defaultSpecGroupIdForPart, toastSuccess, toastError,
    });
    const {
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
    } = componentSpecs;


    return {
        toasts, part, loading, loadError, componentId,
        sections, stockTypeLabel, stockConditionLabel, procurementOptions,
        categories, packageGroups, packages, specTypes, specGroups, specGroupOptions, specSuggestionTypes, specTemplates, specSuggestionLoading, suppliers, locations,
        preferredSupplier, stockSummary, allTransactions, displayedTransactions, hasMoreTransactions, showAllTransactions,
        outgoingTransactions, incomingTransactions,
        formatTransactionTimestamp,
        canSaveEditModal, editMasterDataLoading,
        specProfileOptions, createEmptySpecRow, getUnitSuggestions, hasSpecBaseUnit, specPreview, specDisplayName, specProfileBadge, specProfileControlLabel, specProfileHelpText,
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
        detailCategoryQuery, filteredDetailCategories, detailCategoryName, toggleDetailCategory,
        basicImageFile, basicDatasheetFiles, basicDatasheetLabels, onBasicDatasheetsChange,
        editModal, openEdit, closeEditModal, saveSection,
        stockOutModal, openStockOut, submitStockOut,
        stockInModal, openStockIn, submitStockIn,
        handleToggleFavorite, isFavorite,
        copyLink, deletePart,
        similarParts, similarLoading, similarError, fetchSimilar, fetchPart,
        formatCurrency,
    };
}
