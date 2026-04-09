import { ref, computed, watch, onMounted } from 'vue';
import { api } from '../api.js';
import { useToast } from '../composables/useToast.js';

export default function setup() {
    const { toasts, toastSuccess, toastError } = useToast();

    // ── フィルタ状態 ──────────────────────────────────────────
    const searchQuery      = ref('');
    const filterCategories = ref([]);
    const filterStatus     = ref('');
    const needsReorder     = ref(false);
    const advancedOpen     = ref(false);
    const advSpecTypeId    = ref('');
    const advMin           = ref('');
    const advMax           = ref('');
    const advUnit          = ref('');
    const sortOrder        = ref('updated_at');

    // ── ページネーション ──────────────────────────────────────
    const page    = ref(1);
    const perPage = ref(20);
    const total   = ref(0);
    const lastPage = ref(1);

    // ── データ ────────────────────────────────────────────────
    const parts      = ref([]);
    const categories = ref([]);
    const specTypes  = ref([]);
    const loading    = ref(false);
    const alertCount = ref(0);
    const listError = ref('');
    const masterError = ref('');

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
    const selectedCategoryNames = computed(() =>
        categories.value
            .filter((item) => filterCategories.value.includes(item.id))
            .map((item) => item.name)
    );
    const activeFilterChips = computed(() => {
        const chips = [];
        if (searchQuery.value) chips.push({ key: 'q', label: `検索: ${searchQuery.value}` });
        selectedCategoryNames.value.forEach((name, index) => {
            chips.push({ key: `cat:${filterCategories.value[index] ?? name}`, label: `分類: ${name}` });
        });
        if (filterStatus.value) {
            chips.push({ key: 'status', label: `入手可否: ${procurementLabel[filterStatus.value] ?? filterStatus.value}` });
        }
        if (needsReorder.value) chips.push({ key: 'reorder', label: '在庫警告のみ' });
        if (advSpecTypeId.value) {
            const specName = specTypes.value.find((item) => item.id == advSpecTypeId.value)?.name ?? '指定';
            chips.push({ key: 'specType', label: `スペック: ${specName}` });
        }
        if (advMin.value) chips.push({ key: 'specMin', label: `最小: ${advMin.value}` });
        if (advMax.value) chips.push({ key: 'specMax', label: `最大: ${advMax.value}` });
        return chips;
    });

    // ── フィルタリセット ──────────────────────────────────────
    const hasFilter = computed(() =>
        searchQuery.value || filterCategories.value.length || filterStatus.value || needsReorder.value || advSpecTypeId.value || advMin.value || advMax.value
    );
    const clearFilters = () => {
        searchQuery.value = ''; filterCategories.value = [];
        filterStatus.value = ''; needsReorder.value = false;
        advancedOpen.value = false; advSpecTypeId.value = '';
        advMin.value = ''; advMax.value = ''; advUnit.value = '';
    };
    const removeFilterChip = (key) => {
        if (key === 'q') searchQuery.value = '';
        else if (key.startsWith('cat:')) {
            const id = Number(key.split(':')[1]);
            filterCategories.value = filterCategories.value.filter((value) => value !== id);
        } else if (key === 'status') filterStatus.value = '';
        else if (key === 'reorder') needsReorder.value = false;
        else if (key === 'specType') advSpecTypeId.value = '';
        else if (key === 'specMin') advMin.value = '';
        else if (key === 'specMax') advMax.value = '';
    };

    // ── APIフェッチ ───────────────────────────────────────────
    const fetchParts = async () => {
        loading.value = true;
        listError.value = '';
        try {
            const params = new URLSearchParams();
            if (searchQuery.value)           params.set('q', searchQuery.value);
            if (filterCategories.value.length) filterCategories.value.forEach(id => params.append('category_ids[]', id));
            if (filterStatus.value)          params.set('procurement_status', filterStatus.value);
            if (needsReorder.value)          params.set('needs_reorder', '1');
            if (advSpecTypeId.value)         params.set('spec_type_id', advSpecTypeId.value);
            if (advMin.value)                params.set('spec_min', advMin.value);
            if (advMax.value)                params.set('spec_max', advMax.value);
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
            const [catRes, stRes, alertRes] = await Promise.all([
                api.get('/categories'),
                api.get('/spec-types'),
                api.get('/components?needs_reorder=1&per_page=1'),
            ]);
            categories.value = catRes.data;
            specTypes.value  = stRes.data;
            alertCount.value = alertRes.data.total;
        } catch {
            masterError.value = '分類・スペック種別・警告件数の取得に失敗しました。最低限の部品一覧は閲覧できますが、絞り込み候補が欠ける可能性があります。';
            categories.value = [];
            specTypes.value = [];
            alertCount.value = 0;
            toastError('部品一覧の補助データ取得に失敗しました');
        }
    };

    // フィルタ変更でページリセット
    watch([searchQuery, filterCategories, filterStatus, needsReorder, advSpecTypeId, advMin, advMax, sortOrder], () => {
        page.value = 1;
        fetchParts();
    });
    watch([page, perPage], fetchParts);

    const procurementLabel = { active: '量産中', eol: 'EOL', last_time: '在庫限り', nrnd: '非推奨' };
    const procurementClass = { active: 'tag-ok', eol: 'tag-eol', last_time: 'tag-warning', nrnd: 'tag-warning' };

    onMounted(async () => {
        await fetchMasters();
        await fetchParts();
    });

    return {
        toasts, searchQuery, filterCategories, filterStatus, needsReorder,
        advancedOpen, advSpecTypeId, advMin, advMax, advUnit,
        sortOrder,
        page, perPage, total, lastPage,
        parts, categories, specTypes, loading, alertCount, listError, masterError, fetchMasters,
        compareList, toggleCompare, inCompare,
        compareUrl,
        selectedCategoryNames, activeFilterChips, hasFilter, clearFilters, removeFilterChip, fetchParts,
        procurementLabel, procurementClass,
    };
}
