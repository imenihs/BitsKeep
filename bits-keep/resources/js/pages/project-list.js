/**
 * 案件管理ページ（SCR-005）
 * 独自案件CRUD + 使用部品・コスト積算
 * Notion同期は未実装（チェックリスト Phase 4-1 Notion系タスク）
 */
import { ref, reactive, computed, onMounted } from 'vue';
import { api } from '../api.js';
import { useToast } from '../composables/useToast.js';

export default function setup() {
    const { toasts, toastSuccess, toastError } = useToast();
    const projects  = ref([]);
    const meta      = ref(null);
    const loading   = ref(false);
    const detailProject = ref(null);    // 詳細パネル
    const costSummary   = ref(null);
    const costLoading   = ref(false);

    const filters = reactive({ q: '', status: 'active', page: 1, per_page: 20 });

    // モーダル
    const modal = reactive({
        open: false, isEdit: false, editId: null,
        form: { name: '', description: '', status: 'active', color: '#2563eb' }
    });

    // 使用部品追加フォーム
    const addCompForm = reactive({ component_id: '', required_qty: 1, searching: false, searchResults: [], keyword: '' });

    const fetchProjects = async () => {
        loading.value = true;
        try {
            const params = Object.fromEntries(Object.entries(filters).filter(([, v]) => v !== ''));
            const r = await api.get('/projects?' + new URLSearchParams(params));
            projects.value = r.data.data ?? r.data;
            meta.value     = r.data.meta ?? null;
        } catch { toastError('案件一覧の取得に失敗しました'); }
        finally { loading.value = false; }
    };

    const openDetail = async (proj) => {
        detailProject.value = null; costSummary.value = null;
        try {
            const r = await api.get(`/projects/${proj.id}`);
            detailProject.value = r.data.project;
            costSummary.value   = r.data.cost_summary;
        } catch { toastError('案件詳細の取得に失敗しました'); }
    };

    const openAdd = () => Object.assign(modal, { open: true, isEdit: false, editId: null,
        form: { name: '', description: '', status: 'active', color: '#2563eb' } });
    const openEdit = (p) => Object.assign(modal, { open: true, isEdit: true, editId: p.id,
        form: { name: p.name, description: p.description ?? '', status: p.status, color: p.color ?? '#2563eb' } });

    const save = async () => {
        try {
            if (modal.isEdit) await api.put(`/projects/${modal.editId}`, modal.form);
            else await api.post('/projects', modal.form);
            toastSuccess('保存しました'); modal.open = false; await fetchProjects();
        } catch (e) { toastError(e.message); }
    };

    const deleteProject = async (p) => {
        if (!confirm(`「${p.name}」を削除しますか？`)) return;
        try { await api.delete(`/projects/${p.id}`); await fetchProjects(); toastSuccess('削除しました'); }
        catch (e) { toastError(e.message); }
    };

    // 部品検索（インライン）
    const searchComponents = async () => {
        if (!addCompForm.keyword) return;
        addCompForm.searching = true;
        try {
            const r = await api.get(`/components?q=${encodeURIComponent(addCompForm.keyword)}&per_page=10`);
            addCompForm.searchResults = r.data.data ?? r.data;
        } catch { toastError('部品検索に失敗しました'); }
        finally { addCompForm.searching = false; }
    };

    const selectComp = (c) => {
        addCompForm.component_id = c.id;
        addCompForm.keyword = c.common_name || c.part_number;
        addCompForm.searchResults = [];
    };

    const addComponent = async () => {
        if (!detailProject.value || !addCompForm.component_id) return;
        try {
            await api.post(`/projects/${detailProject.value.id}/components`, {
                component_id: addCompForm.component_id,
                required_qty: addCompForm.required_qty,
            });
            toastSuccess('部品を追加しました');
            addCompForm.component_id = ''; addCompForm.keyword = ''; addCompForm.required_qty = 1;
            await openDetail(detailProject.value);
        } catch (e) { toastError(e.message); }
    };

    const removeComponent = async (comp) => {
        if (!detailProject.value) return;
        try {
            await api.delete(`/projects/${detailProject.value.id}/components/${comp.id}`);
            await openDetail(detailProject.value);
            toastSuccess('削除しました');
        } catch (e) { toastError(e.message); }
    };

    const statusLabel = (s) => ({ active: '進行中', archived: 'アーカイブ' }[s] ?? s);
    const statusClass = (s) => s === 'active' ? 'tag-ok' : 'opacity-50';

    const applyFilter = () => { filters.page = 1; fetchProjects(); };

    onMounted(fetchProjects);
    return {
        toasts, projects, meta, loading, filters, modal, detailProject, costSummary, costLoading, addCompForm,
        fetchProjects, openDetail, openAdd, openEdit, save, deleteProject,
        searchComponents, selectComp, addComponent, removeComponent,
        statusLabel, statusClass, applyFilter,
    };
}
