/**
 * 案件管理ページ（SCR-005）
 * 独自案件CRUD + 使用部品・コスト積算 + Notion同期
 */
import { ref, reactive, computed, onMounted } from 'vue';
import { api } from '../api.js';
import { useToast } from '../composables/useToast.js';
import { useFormatter } from '../composables/useFormatter.js';

export default function setup() {
    const { toasts, toastSuccess, toastError } = useToast();
    const { formatCurrency, formatDate } = useFormatter();
    const projects      = ref([]);
    const meta          = ref(null);
    const loading       = ref(false);
    const detailProject = ref(null);
    const costSummary   = ref(null);
    const businesses    = ref([]);   // 事業フィルタ用

    const filters = reactive({ q: '', status: 'active', source_type: '', business_code: '', page: 1, per_page: 20 });

    // 追加/編集モーダル
    const modal = reactive({
        open: false, isEdit: false, editId: null,
        form: { name: '', description: '', status: 'active', color: '#2563eb', business_code: '' }
    });

    // 使用部品追加フォーム
    const addCompForm = reactive({ component_id: '', required_qty: 1, searching: false, searchResults: [], keyword: '' });

    // Notion同期
    const syncing     = ref(false);
    const lastSyncRun = ref(null);
    const syncConfig  = ref({ configured: false, token_configured: false, root_page_configured: false, missing: [] });
    const supportError = ref('');
    const detailError = ref('');

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

    const fetchBusinesses = async () => {
        try {
            const r = await api.get('/project-businesses');
            businesses.value = r.data?.data ?? r.data ?? [];
        } catch {
            supportError.value = '事業フィルタ候補の取得に失敗しました。再読込するか、案件一覧のみで続行してください。';
        }
    };

    const fetchLastSyncRun = async () => {
        try {
            const r = await api.get('/projects/sync-runs');
            const runs = r.data?.data ?? r.data ?? [];
            lastSyncRun.value = runs[0] ?? null;
        } catch {
            supportError.value = '同期履歴の取得に失敗しました。Notion同期の結果確認は再読込後に再試行してください。';
        }
    };

    const fetchSyncStatus = async () => {
        try {
            const r = await api.get('/projects/sync/status');
            syncConfig.value = r.data?.data ?? r.data ?? syncConfig.value;
        } catch {
            supportError.value = 'Notion同期状態の取得に失敗しました。連携設定を確認するか、再読込してください。';
        }
    };

    const openDetail = async (proj) => {
        detailProject.value = null; costSummary.value = null; detailError.value = '';
        try {
            const r = await api.get(`/projects/${proj.id}`);
            detailProject.value = r.data.data?.project ?? r.data.project;
            costSummary.value   = r.data.data?.cost_summary ?? r.data.cost_summary;
        } catch {
            detailError.value = '案件詳細の取得に失敗しました。再試行するか、一覧を再読込してください。';
            toastError('案件詳細の取得に失敗しました');
        }
    };

    const openAdd = () => Object.assign(modal, {
        open: true, isEdit: false, editId: null,
        form: { name: '', description: '', status: 'active', color: '#2563eb', business_code: '' }
    });
    const openEdit = (p) => {
        // Notion由来は編集不可
        if (!p.is_editable) { toastError('Notion由来の案件は編集できません'); return; }
        Object.assign(modal, { open: true, isEdit: true, editId: p.id,
            form: { name: p.name, description: p.description ?? '', status: p.status,
                    color: p.color ?? '#2563eb', business_code: p.business_code ?? '' } });
    };

    const save = async () => {
        try {
            if (modal.isEdit) await api.put(`/projects/${modal.editId}`, modal.form);
            else await api.post('/projects', modal.form);
            toastSuccess('保存しました'); modal.open = false;
            await fetchProjects(); await fetchBusinesses();
        } catch (e) { toastError(e.response?.data?.message ?? e.message); }
    };

    const deleteProject = async (p) => {
        if (!p.is_editable) { toastError('Notion由来の案件は削除できません'); return; }
        if (!confirm(`「${p.name}」を削除しますか？`)) return;
        try { await api.delete(`/projects/${p.id}`); await fetchProjects(); toastSuccess('削除しました'); }
        catch (e) { toastError(e.response?.data?.message ?? e.message); }
    };

    // Notion同期
    const syncNotion = async () => {
        if (!syncConfig.value.configured) {
            toastError(`Notion同期は未設定です: ${syncConfig.value.missing.join(', ')}`);
            return;
        }
        syncing.value = true;
        try {
            const r = await api.post('/projects/sync/notion');
            const run = r.data?.data ?? r.data;
            toastSuccess(`同期完了: ${run.synced_count}件`);
            lastSyncRun.value = run;
            await fetchProjects(); await fetchBusinesses();
        } catch (e) {
            const msg = e.response?.data?.message ?? e.message;
            toastError('同期失敗: ' + msg);
        }
        finally { syncing.value = false; }
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
    const sourceLabel = (p) => p.source_type === 'notion' ? '[Notion]' : '[Local]';
    const sourceClass = (p) => p.source_type === 'notion' ? 'text-blue-600' : 'text-green-600';

    const applyFilter = () => { filters.page = 1; fetchProjects(); };

    const syncPanel = computed(() => {
        if (!syncConfig.value.configured) {
            return {
                tone: 'warning',
                badge: '未設定',
                title: 'Notion連携を先に整える',
                summary: syncConfig.value.missing?.length ? `不足: ${syncConfig.value.missing.join(', ')}` : '連携設定が必要です',
                actionLabel: '連携設定を開く',
                actionType: 'settings',
            };
        }

        if (syncConfig.value.health?.status === 'error') {
            return {
                tone: 'danger',
                badge: '要確認',
                title: '接続を確認してから同期',
                summary: syncConfig.value.health.message,
                actionLabel: '連携設定を開く',
                actionType: 'settings',
            };
        }

        if (!lastSyncRun.value) {
            return {
                tone: 'idle',
                badge: '未実行',
                title: '最初の同期を実行',
                summary: '案件一覧へ Notion の案件を取り込みます',
                actionLabel: 'Notion同期',
                actionType: 'sync',
            };
        }

        const run = lastSyncRun.value;

        if (run.status === 'running') {
            return {
                tone: 'progress',
                badge: '実行中',
                title: 'Notion同期を実行中',
                summary: '完了後に一覧を更新します',
                actionLabel: '同期中...',
                actionType: 'sync',
            };
        }

        if (run.status === 'success' && run.synced_count === 0 && run.error_detail) {
            return {
                tone: 'warning',
                badge: '0件',
                title: '同期対象が見つからない',
                summary: run.error_detail,
                actionLabel: '再同期',
                actionType: 'sync',
            };
        }

        if (run.status === 'success') {
            return {
                tone: 'ok',
                badge: '最新',
                title: `${run.synced_count}件を同期済み`,
                summary: run.finished_at ? `最終同期 ${run.finished_at.substring(0, 10)}` : '同期完了',
                actionLabel: '再同期',
                actionType: 'sync',
            };
        }

        return {
            tone: 'danger',
            badge: '失敗',
            title: '同期に失敗しました',
            summary: run.error_detail?.substring(0, 80) ?? '時間をおいて再実行してください',
            actionLabel: '再同期',
            actionType: 'sync',
        };
    });

    const reloadSupportData = () => {
        supportError.value = '';
        fetchBusinesses();
        fetchLastSyncRun();
        fetchSyncStatus();
    };

    onMounted(() => { fetchProjects(); reloadSupportData(); });

    return {
        toasts, projects, meta, loading, filters, modal, businesses,
        detailProject, costSummary, addCompForm,
        syncing, lastSyncRun, syncPanel, syncConfig, supportError, detailError, reloadSupportData,
        fetchProjects, openDetail, openAdd, openEdit, save, deleteProject,
        searchComponents, selectComp, addComponent, removeComponent,
        statusLabel, statusClass, sourceLabel, sourceClass,
        applyFilter, syncNotion,
        formatCurrency, formatDate,
    };
}
