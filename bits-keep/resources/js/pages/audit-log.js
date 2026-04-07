/**
 * 操作ログページ（SCR-010）
 * admin ロールのみアクセス
 */
import { ref, reactive, onMounted } from 'vue';
import { api } from '../api.js';
import { useToast } from '../composables/useToast.js';

export default function setup() {
    const { toasts, toastError } = useToast();
    const logs = ref([]);
    const meta = ref(null);   // paginator meta
    const loading = ref(false);

    const filters = reactive({
        action: '',
        resource_type: '',
        date_from: '',
        date_to: '',
        page: 1,
        per_page: 50,
    });

    // 展開中のdiff
    const expandedId = ref(null);
    const toggleDiff = (id) => { expandedId.value = expandedId.value === id ? null : id; };

    const fetchLogs = async () => {
        loading.value = true;
        try {
            const params = Object.fromEntries(
                Object.entries(filters).filter(([, v]) => v !== '' && v !== null)
            );
            const r = await api.get('/audit-logs?' + new URLSearchParams(params).toString());
            // paginate レスポンス構造
            logs.value  = r.data.data ?? r.data;
            meta.value  = r.data.meta ?? null;
        } catch { toastError('操作ログの取得に失敗しました'); }
        finally { loading.value = false; }
    };

    const applyFilter = () => { filters.page = 1; fetchLogs(); };
    const goPage = (p) => { filters.page = p; fetchLogs(); };

    const actionLabel = (a) => ({ created: '作成', updated: '更新', deleted: '削除' }[a] ?? a);
    const actionClass = (a) => ({
        created: 'bg-emerald-100 text-emerald-700',
        updated: 'bg-blue-100 text-blue-700',
        deleted: 'bg-red-100 text-red-700',
    }[a] ?? '');

    // diff の before/after を読みやすく整形
    const diffLines = (diff) => {
        if (!diff) return [];
        const lines = [];
        const before = diff.before ?? {};
        const after  = diff.after  ?? {};
        const keys = new Set([...Object.keys(before), ...Object.keys(after)]);
        keys.forEach(k => {
            if (JSON.stringify(before[k]) !== JSON.stringify(after[k])) {
                lines.push({ key: k, before: before[k], after: after[k] });
            }
        });
        return lines;
    };

    onMounted(fetchLogs);
    return { toasts, logs, meta, loading, filters, expandedId, toggleDiff,
             applyFilter, goPage, actionLabel, actionClass, diffLines };
}
