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

    // ── 比較リスト ────────────────────────────────────────────
    const compareList = ref([]);
    const toggleCompare = (part) => {
        const idx = compareList.value.findIndex(p => p.id === part.id);
        if (idx >= 0) compareList.value.splice(idx, 1);
        else if (compareList.value.length < 4) compareList.value.push(part);
        else toastError('比較は最大4件まです');
    };
    const inCompare = (part) => compareList.value.some(p => p.id === part.id);

    // ── フィルタリセット ──────────────────────────────────────
    const hasFilter = computed(() =>
        searchQuery.value || filterCategories.value.length || filterStatus.value || needsReorder.value
    );
    const clearFilters = () => {
        searchQuery.value = ''; filterCategories.value = [];
        filterStatus.value = ''; needsReorder.value = false;
        advancedOpen.value = false; advSpecTypeId.value = '';
        advMin.value = ''; advMax.value = ''; advUnit.value = '';
    };

    // ── APIフェッチ ───────────────────────────────────────────
    const fetchParts = async () => {
        loading.value = true;
        try {
            const params = new URLSearchParams();
            if (searchQuery.value)           params.set('q', searchQuery.value);
            if (filterCategories.value.length) filterCategories.value.forEach(id => params.append('category_ids[]', id));
            if (filterStatus.value)          params.set('procurement_status', filterStatus.value);
            if (needsReorder.value)          params.set('needs_reorder', '1');
            if (advSpecTypeId.value)         params.set('spec_type_id', advSpecTypeId.value);
            if (advMin.value)                params.set('spec_min', advMin.value);
            if (advMax.value)                params.set('spec_max', advMax.value);
            params.set('page', page.value);
            params.set('per_page', perPage.value);

            const res = await api.get(`/components?${params}`);
            parts.value    = res.data.data;
            total.value    = res.data.total;
            lastPage.value = res.data.last_page;
        } catch (e) {
            toastError('部品一覧の取得に失敗しました');
        } finally {
            loading.value = false;
        }
    };

    const fetchMasters = async () => {
        const [catRes, stRes, alertRes] = await Promise.all([
            api.get('/categories'),
            api.get('/spec-types'),
            api.get('/components?needs_reorder=1&per_page=1'),
        ]);
        categories.value = catRes.data;
        specTypes.value  = stRes.data;
        alertCount.value = alertRes.data.total;
    };

    // フィルタ変更でページリセット
    watch([searchQuery, filterCategories, filterStatus, needsReorder, advSpecTypeId, advMin, advMax], () => {
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
        page, perPage, total, lastPage,
        parts, categories, specTypes, loading, alertCount,
        compareList, toggleCompare, inCompare,
        hasFilter, clearFilters, fetchParts,
        procurementLabel, procurementClass,
    };
}
