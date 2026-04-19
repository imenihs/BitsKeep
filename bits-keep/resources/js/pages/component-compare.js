/**
 * 部品比較ページ（SCR-004）
 * URL: ?ids=1,2,3 または 一覧ページから比較リスト経由
 */
import { ref, computed, onMounted, reactive } from 'vue';
import { api } from '../api.js';
import { useToast } from '../composables/useToast.js';
import { useFormatter } from '../composables/useFormatter.js';

export default function setup() {
    const { toasts, toastSuccess, toastError } = useToast();
    const { formatCurrency } = useFormatter();
    const specTypes  = ref([]);
    const components = ref([]);
    const loading    = ref(false);
    const loadError  = ref('');
    const emptyState = ref('');
    const compareIds = ref([]);
    const diffOnly   = ref(false);
    const showAddModal = ref(false);
    const addSearch = ref('');
    const addResults = ref([]);
    const addLoading = ref(false);
    const drawer = reactive({
        open: false,
        part: null,
        selectedProject: null,
        adding: false,
    });

    const syncCompareUrl = (ids) => {
        const query = ids.length ? `?ids=${ids.join(',')}` : '';
        window.history.replaceState({}, '', `/component-compare${query}`);
    };

    const fetchCompare = async (ids) => {
        compareIds.value = ids;
        syncCompareUrl(ids);
        loadError.value = '';
        emptyState.value = '';
        specTypes.value = [];
        components.value = [];

        if (ids.length === 0) {
            emptyState.value = 'none';
            return;
        }

        if (ids.length < 2) {
            emptyState.value = 'insufficient';
            return;
        }

        loading.value = true;
        try {
            const params = ids.map(id => `ids[]=${id}`).join('&');
            const r = await api.get(`/components/compare?${params}`);
            specTypes.value  = r.data.spec_types;
            components.value = r.data.components;
            if (components.value.length < 2) {
                emptyState.value = 'insufficient';
            }
        } catch {
            loadError.value = '比較データの取得に失敗しました。URLを確認するか、部品一覧から比較対象を選び直してください。';
            toastError('比較データの取得に失敗しました');
        }
        finally { loading.value = false; }
    };

    onMounted(() => {
        const params  = new URLSearchParams(window.location.search);
        const idsStr  = params.get('ids') ?? '';
        const ids     = idsStr.split(',').map(Number).filter(Boolean);
        fetchCompare(ids);
    });

    // 差分ハイライト: 同一スペック種別で値が全部同じなら差分なし
    const hasDiff = (specTypeId) => {
        const values = components.value.map(c => c.specs[specTypeId]?.value_numeric);
        const nonNull = values.filter(v => v !== null && v !== undefined);
        if (nonNull.length < 2) return false;
        return new Set(nonNull).size > 1;
    };

    const visibleSpecTypes = computed(() => (
        diffOnly.value
            ? specTypes.value.filter((st) => hasDiff(st.id))
            : specTypes.value
    ));

    const removeComponent = (componentId) => {
        const next = components.value.filter((comp) => comp.id !== componentId);
        components.value = next;
        compareIds.value = next.map((comp) => comp.id);
        syncCompareUrl(compareIds.value);
        emptyState.value = next.length < 2 ? 'insufficient' : '';
    };

    const moveComponent = (index, direction) => {
        const target = index + direction;
        if (target < 0 || target >= components.value.length) return;
        const next = [...components.value];
        [next[index], next[target]] = [next[target], next[index]];
        components.value = next;
        compareIds.value = next.map((comp) => comp.id);
        syncCompareUrl(compareIds.value);
    };

    const searchParts = async () => {
        addLoading.value = true;
        try {
            const params = new URLSearchParams({ per_page: '8' });
            if (addSearch.value.trim()) params.set('q', addSearch.value.trim());
            const r = await api.get(`/components?${params}`);
            const ids = new Set(compareIds.value);
            addResults.value = (r.data?.data ?? r.data ?? []).filter((item) => !ids.has(item.id));
        } catch {
            addResults.value = [];
            toastError('追加候補の部品検索に失敗しました');
        } finally {
            addLoading.value = false;
        }
    };

    const openAddModal = async () => {
        showAddModal.value = true;
        addSearch.value = '';
        await searchParts();
    };

    const addPart = async (part) => {
        if (compareIds.value.length >= 5) {
            toastError('比較は最大5件までです');
            return;
        }
        await fetchCompare([...compareIds.value, part.id]);
        showAddModal.value = false;
    };

    const openProjectDrawer = (part) => {
        drawer.open = true;
        drawer.part = part;
        drawer.selectedProject = null;
        drawer.adding = false;
    };

    const handleNewProjectCreated = (project) => {
        drawer.selectedProject = project;
    };

    const addToProject = async () => {
        if (!drawer.part || !drawer.selectedProject?.id) return;
        drawer.adding = true;
        try {
            await api.post(`/projects/${drawer.selectedProject.id}/components`, {
                component_id: drawer.part.id,
                required_qty: 1,
            });
            toastSuccess(`「${drawer.part.common_name || drawer.part.part_number}」を案件へ追加しました`);
            drawer.open = false;
        } catch (e) {
            toastError(e.message ?? '案件への追加に失敗しました');
        } finally {
            drawer.adding = false;
        }
    };

    const statusLabel = (s) => ({ active: '入手可', nrnd: 'NRND', eol: 'EOL', custom: 'カスタム' }[s] ?? s);
    const statusClass = (s) => ({ active: 'tag-ok', nrnd: 'tag-warning', eol: 'tag-eol' }[s] ?? '');
    const compareCountLabel = computed(() => `${components.value.length}件比較中`);
    const canShowTable = computed(() => !loading.value && !loadError.value && components.value.length >= 2);

    return {
        toasts,
        specTypes,
        visibleSpecTypes,
        components,
        loading,
        loadError,
        emptyState,
        compareIds,
        diffOnly,
        showAddModal,
        addSearch,
        addResults,
        addLoading,
        drawer,
        compareCountLabel,
        canShowTable,
        fetchCompare,
        searchParts,
        openAddModal,
        addPart,
        openProjectDrawer,
        handleNewProjectCreated,
        addToProject,
        removeComponent,
        moveComponent,
        hasDiff,
        statusLabel,
        statusClass,
        formatCurrency,
    };
}
