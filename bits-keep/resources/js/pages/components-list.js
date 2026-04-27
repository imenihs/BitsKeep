import { ref, computed, watch, onMounted } from 'vue';
import { api } from '../api.js';
import { useToast } from '../composables/useToast.js';
import { useFavoriteComponents } from '../composables/useFavoriteComponents.js';
import { useFormatter } from '../composables/useFormatter.js';
import { SPEC_PROFILE_OPTIONS } from '../utils/specValue.js';

export default function setup() {
    const { toasts, toastSuccess, toastError } = useToast();
    const { favoriteIds, loadFavorites, toggleFavorite, isFavorite } = useFavoriteComponents();

    // ── フィルタ状態 ──────────────────────────────────────────
    const searchQuery      = ref('');
    const filterCategories = ref([]);
    const filterStatus     = ref('');
    const advancedOpen     = ref(false);
    const advManufacturer  = ref('');
    const packageGroups    = ref([]);
    const advPackageGroupId = ref('');
    const advPackageId     = ref('');
    const advPackageQuery  = ref('');
    const advSpecTypeId    = ref('');
    const advSpecProfile   = ref('');
    const advUnit          = ref('');
    const advMin           = ref('');
    const advMax           = ref('');
    const advMinStock      = ref('');
    const advInventoryState = ref('');
    const advPurchasedFrom = ref('');
    const advPurchasedTo   = ref('');
    const sortOrder        = ref('updated_at');
    const favoriteOnly     = ref(false);

    // ── ページネーション ──────────────────────────────────────
    const page    = ref(1);
    const perPage = ref(20);
    const total   = ref(0);
    const lastPage = ref(1);

    // ── データ ────────────────────────────────────────────────
    const parts      = ref([]);
    const categories = ref([]);
    const packages   = ref([]);
    const specTypes  = ref([]);
    const loading    = ref(false);
    const alertCount = ref(0);
    const listError = ref('');
    const masterError = ref('');
    const specProfileOptions = [{ value: '', label: '全件' }, ...SPEC_PROFILE_OPTIONS];

    // ── 比較リスト ────────────────────────────────────────────
    const compareList = ref([]);
    const toggleCompare = (part) => {
        const idx = compareList.value.findIndex(p => p.id === part.id);
        if (idx >= 0) compareList.value.splice(idx, 1);
        else if (compareList.value.length < 5) compareList.value.push(part);
        else toastError('比較は最大5件までです');
    };
    const inCompare = (part) => compareList.value.some(p => p.id === part.id);
    const compareUrl = computed(() => {
        const ids = compareList.value.map((part) => part.id);
        return ids.length >= 2 ? `/component-compare?ids=${ids.join(',')}` : '/component-compare';
    });
    const handleToggleFavorite = async (componentId) => {
        try {
            const wasFavorite = isFavorite(componentId);
            await toggleFavorite(componentId);
            toastSuccess(wasFavorite ? 'お気に入りから外しました' : 'お気に入りに追加しました');
            if (favoriteOnly.value) {
                fetchParts();
            }
        } catch {
            toastError('お気に入りの保存に失敗しました');
        }
    };
    const selectedCategoryNames = computed(() =>
        categories.value
            .filter((item) => filterCategories.value.includes(item.id))
            .map((item) => item.name)
    );
    const activeFilterChips = computed(() => {
        const chips = [];
        if (searchQuery.value) chips.push({ key: 'q', label: `検索: ${searchQuery.value}` });
        filterCategories.value.forEach((categoryId) => {
            const categoryName = categories.value.find((item) => item.id == categoryId)?.name ?? `#${categoryId}`;
            chips.push({ key: `cat:${categoryId}`, label: `分類: ${categoryName}` });
        });
        if (filterStatus.value) {
            chips.push({ key: 'status', label: `入手可否: ${procurementLabel[filterStatus.value] ?? filterStatus.value}` });
        }
        if (favoriteOnly.value) chips.push({ key: 'favoriteOnly', label: 'お気に入りのみ' });
        if (advManufacturer.value) chips.push({ key: 'manufacturer', label: `メーカー: ${advManufacturer.value}` });
        if (advPackageGroupId.value) {
            const groupName = packageGroups.value.find((item) => item.id == advPackageGroupId.value)?.name ?? `#${advPackageGroupId.value}`;
            chips.push({ key: 'packageGroup', label: `パッケージ分類: ${groupName}` });
        }
        if (advPackageId.value) {
            const packageName = packages.value.find((item) => item.id == advPackageId.value)?.name ?? `#${advPackageId.value}`;
            chips.push({ key: 'package', label: `パッケージ: ${packageName}` });
        }
        if (advSpecTypeId.value) {
            const specName = specTypes.value.find((item) => item.id == advSpecTypeId.value)?.name ?? '指定';
            chips.push({ key: 'specType', label: `スペック: ${specName}` });
            if (advSpecProfile.value) {
                const profileLabel = specProfileOptions.find((item) => item.value === advSpecProfile.value)?.label ?? advSpecProfile.value;
                chips.push({ key: 'specProfile', label: `照合基準: ${profileLabel}` });
            }
        }
        if (advUnit.value) chips.push({ key: 'unit', label: `単位: ${advUnit.value}` });
        if (advMin.value) chips.push({ key: 'specMin', label: `最小: ${advMin.value}` });
        if (advMax.value) chips.push({ key: 'specMax', label: `最大: ${advMax.value}` });
        if (advMinStock.value) chips.push({ key: 'minStock', label: `在庫下限: ${advMinStock.value}` });
        if (advInventoryState.value) chips.push({ key: 'inventoryState', label: `在庫状態: ${advInventoryState.value}` });
        if (advPurchasedFrom.value) chips.push({ key: 'purchasedFrom', label: `購入日From: ${advPurchasedFrom.value}` });
        if (advPurchasedTo.value) chips.push({ key: 'purchasedTo', label: `購入日To: ${advPurchasedTo.value}` });
        return chips;
    });
    const isFiltered = computed(() => activeFilterChips.value.length > 0);
    const emptyState = computed(() => {
        if (loading.value || parts.value.length > 0 || listError.value) return null;
        if (isFiltered.value) {
            return {
                title: '絞り込み条件に一致する部品がありません',
                desc: '条件を1つ外すか、すべてクリアして探し直してください。',
                actions: ['clear', 'retry'],
            };
        }
        return {
            title: 'まだ部品が登録されていません',
            desc: '新規登録するか、CSVインポートからまとめて登録してください。',
            actions: ['create', 'csv'],
        };
    });

    // ── フィルタリセット ──────────────────────────────────────
    const hasFilter = computed(() =>
        searchQuery.value || filterCategories.value.length || filterStatus.value || favoriteOnly.value || advManufacturer.value || advPackageGroupId.value || advPackageId.value || advSpecTypeId.value || advUnit.value || advMin.value || advMax.value || advMinStock.value || advInventoryState.value || advPurchasedFrom.value || advPurchasedTo.value
    );
    const clearFilters = () => {
        searchQuery.value = ''; filterCategories.value = [];
        filterStatus.value = ''; advancedOpen.value = false;
        favoriteOnly.value = false;
        advManufacturer.value = ''; advPackageGroupId.value = ''; advPackageId.value = ''; advPackageQuery.value = '';
        advSpecTypeId.value = ''; advSpecProfile.value = ''; advUnit.value = ''; advMin.value = '';
        advMax.value = ''; advMinStock.value = '';
        advInventoryState.value = ''; advPurchasedFrom.value = ''; advPurchasedTo.value = '';
    };
    const removeFilterChip = (key) => {
        if (key === 'q') searchQuery.value = '';
        else if (key.startsWith('cat:')) {
            const id = Number(key.split(':')[1]);
            filterCategories.value = filterCategories.value.filter((value) => value !== id);
        } else if (key === 'status') filterStatus.value = '';
        else if (key === 'favoriteOnly') favoriteOnly.value = false;
        else if (key === 'manufacturer') advManufacturer.value = '';
        else if (key === 'packageGroup') { advPackageGroupId.value = ''; advPackageId.value = ''; advPackageQuery.value = ''; }
        else if (key === 'package') advPackageId.value = '';
        else if (key === 'specType') advSpecTypeId.value = '';
        else if (key === 'specProfile') advSpecProfile.value = '';
        else if (key === 'unit') advUnit.value = '';
        else if (key === 'specMin') advMin.value = '';
        else if (key === 'specMax') advMax.value = '';
        else if (key === 'minStock') advMinStock.value = '';
        else if (key === 'inventoryState') advInventoryState.value = '';
        else if (key === 'purchasedFrom') advPurchasedFrom.value = '';
        else if (key === 'purchasedTo') advPurchasedTo.value = '';
    };

    // ── APIフェッチ ───────────────────────────────────────────
    const fetchParts = async () => {
        loading.value = true;
        listError.value = '';
        try {
            const params = new URLSearchParams();
            if (favoriteOnly.value) {
                if (!favoriteIds.value.length) {
                    parts.value = [];
                    total.value = 0;
                    lastPage.value = 1;
                    return;
                }
                favoriteIds.value.forEach((id) => params.append('ids[]', id));
            }
            if (searchQuery.value)           params.set('q', searchQuery.value);
            if (filterCategories.value.length) filterCategories.value.forEach(id => params.append('category_ids[]', id));
            if (filterStatus.value)          params.set('procurement_status', filterStatus.value);
            if (advManufacturer.value)       params.set('manufacturer', advManufacturer.value);
            if (advPackageGroupId.value)     params.set('package_group_id', advPackageGroupId.value);
            if (advPackageId.value)          params.set('package_id', advPackageId.value);
            if (advSpecTypeId.value)         params.set('spec_type_id', advSpecTypeId.value);
            if (advSpecTypeId.value && advSpecProfile.value) params.set('spec_profile', advSpecProfile.value);
            if (advUnit.value)               params.set('spec_unit', advUnit.value);
            if (advMin.value)                params.set('spec_min', advMin.value);
            if (advMax.value)                params.set('spec_max', advMax.value);
            if (advMinStock.value)           params.set('min_stock', advMinStock.value);
            if (advInventoryState.value)     params.set('inventory_state', advInventoryState.value);
            if (advPurchasedFrom.value)      params.set('purchased_from', advPurchasedFrom.value);
            if (advPurchasedTo.value)        params.set('purchased_to', advPurchasedTo.value);
            params.set('sort', sortOrder.value);
            params.set('page', page.value);
            params.set('per_page', perPage.value);

            const res = await api.get(`/components?${params}`);
            parts.value    = res.data.data;
            total.value    = res.data.total;
            lastPage.value = res.data.last_page;
        } catch (e) {
            toastError('部品一覧の取得に失敗しました');
            listError.value = '部品一覧の取得に失敗しました。再試行するか、検索条件を見直してください。';
        } finally {
            loading.value = false;
        }
    };

    const fetchMasters = async () => {
        masterError.value = '';
        try {
            const [catRes, pkgGroupRes, pkgRes, stRes, alertRes] = await Promise.all([
                api.get('/categories'),
                api.get('/package-groups'),
                api.get('/packages'),
                api.get('/spec-types'),
                api.get('/stock-alerts'),
            ]);
            categories.value = catRes.data;
            packageGroups.value = pkgGroupRes.data;
            packages.value   = pkgRes.data;
            specTypes.value  = stRes.data;
            alertCount.value = alertRes.data?.length ?? 0;
        } catch {
            masterError.value = '分類・パッケージ・スペック項目・警告件数の取得に失敗しました。最低限の部品一覧は閲覧できますが、絞り込み候補が欠ける可能性があります。';
            categories.value = [];
            packageGroups.value = [];
            packages.value = [];
            specTypes.value = [];
            alertCount.value = 0;
            toastError('部品一覧の補助データ取得に失敗しました');
        }
    };

    // フィルタ変更でページリセット
    watch([searchQuery, filterCategories, filterStatus, favoriteOnly, advManufacturer, advPackageGroupId, advPackageId, advSpecTypeId, advSpecProfile, advUnit, advMin, advMax, advMinStock, advInventoryState, advPurchasedFrom, advPurchasedTo, sortOrder], () => {
        page.value = 1;
        fetchParts();
    });
    watch([page, perPage], fetchParts);

    const procurementLabel = { active: '量産中', eol: 'EOL', last_time: '在庫限り', nrnd: '非推奨' };
    const procurementClass = { active: 'tag-ok', eol: 'tag-eol', last_time: 'tag-warning', nrnd: 'tag-warning' };
    const filteredAdvancedPackages = computed(() => {
        if (!advPackageGroupId.value) return [];
        const scopedPackages = packages.value.filter((item) => item.package_group_id === Number(advPackageGroupId.value));
        const q = advPackageQuery.value.trim().toLowerCase();
        if (!q) return scopedPackages;
        return scopedPackages.filter((item) => item.name.toLowerCase().includes(q));
    });

    watch(advPackageGroupId, (groupId) => {
        advPackageQuery.value = '';
        if (!groupId) {
            advPackageId.value = '';
            return;
        }
        const selectedPackage = packages.value.find((item) => item.id == advPackageId.value);
        if (!selectedPackage || selectedPackage.package_group_id !== Number(groupId)) {
            advPackageId.value = '';
        }
    });

    onMounted(async () => {
        await loadFavorites();
        await fetchMasters();
        await fetchParts();
    });

    return {
        toasts, searchQuery, filterCategories, filterStatus, favoriteOnly,
        advancedOpen, advManufacturer, packageGroups, advPackageGroupId, advPackageId, advPackageQuery, filteredAdvancedPackages, advSpecTypeId, advSpecProfile, advUnit, advMin, advMax, advMinStock, advInventoryState, advPurchasedFrom, advPurchasedTo,
        sortOrder,
        page, perPage, total, lastPage,
        parts, categories, packages, specTypes, loading, alertCount, listError, masterError, fetchMasters,
        compareList, toggleCompare, inCompare,
        compareUrl,
        specProfileOptions,
        selectedCategoryNames, activeFilterChips, hasFilter, clearFilters, removeFilterChip, fetchParts, emptyState, isFiltered,
        favoriteIds, handleToggleFavorite, isFavorite,
        procurementLabel, procurementClass,
        ...useFormatter(),
    };
}
