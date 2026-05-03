/**
 * 操作ログページ（SCR-010）
 * admin ロールのみアクセス
 */
import { ref, reactive, onMounted } from 'vue';
import { api } from '../api.js';
import { useToast } from '../composables/useToast.js';
import { useFormatter } from '../composables/useFormatter.js';

// 目的: 画面モジュールのsetupを扱う。機能: 入力値を検証・整形し、画面または計算処理へ渡す。入力: 関数シグネチャの値。出力: 処理結果またはなし。動作条件: 画面モジュールの初期化後に呼び出す。副作用: Vue状態、localStorage、DOM、HTTP通信のいずれかを更新する場合がある。
export default function setup() {
    const { toasts, toastError } = useToast();
    const { formatDate } = useFormatter();
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
    // 目的: 画面モジュールのtoggle Diffを扱う。機能: 入力値を検証・整形し、画面または計算処理へ渡す。入力: 関数シグネチャの値。出力: 処理結果またはなし。動作条件: 画面モジュールの初期化後に呼び出す。副作用: なし。
    const toggleDiff = (id) => { expandedId.value = expandedId.value === id ? null : id; };

    // 目的: 画面モジュールのfetch Logsを扱う。機能: 入力値を検証・整形し、画面または計算処理へ渡す。入力: 関数シグネチャの値。出力: 処理結果またはなし。動作条件: 画面モジュールの初期化後に呼び出す。副作用: Vue状態、localStorage、DOM、HTTP通信のいずれかを更新する場合がある。
    const fetchLogs = async () => {
        loading.value = true;
        try {
            const params = Object.fromEntries(
                Object.entries(filters).filter(([, v]) => v !== '' && v !== null)
            );
            if (params.resource_type) params.resource_type = resourceTypeFilterValue(params.resource_type);
            const r = await api.get('/audit-logs?' + new URLSearchParams(params).toString());
            // paginate レスポンス構造
            logs.value  = r.data.data ?? r.data;
            meta.value  = r.data.meta ?? null;
        } catch { toastError('操作ログの取得に失敗しました'); }
        finally { loading.value = false; }
    };

    // 目的: 画面モジュールのapply Filterを扱う。機能: 入力値を検証・整形し、画面または計算処理へ渡す。入力: 関数シグネチャの値。出力: 処理結果またはなし。動作条件: 画面モジュールの初期化後に呼び出す。副作用: Vue状態、localStorage、DOM、HTTP通信のいずれかを更新する場合がある。
    const applyFilter = () => { filters.page = 1; fetchLogs(); };
    // 目的: 画面モジュールのgo Pageを扱う。機能: 入力値を検証・整形し、画面または計算処理へ渡す。入力: 関数シグネチャの値。出力: 処理結果またはなし。動作条件: 画面モジュールの初期化後に呼び出す。副作用: Vue状態、localStorage、DOM、HTTP通信のいずれかを更新する場合がある。
    const goPage = (p) => { filters.page = p; fetchLogs(); };

    // フィルタが何か適用されているかチェック
    // 目的: 画面モジュールのhas Active Filterを扱う。機能: 入力値を検証・整形し、画面または計算処理へ渡す。入力: 関数シグネチャの値。出力: 真偽値。動作条件: 画面モジュールの初期化後に呼び出す。副作用: なし。
    const hasActiveFilter = () => filters.action || filters.resource_type || filters.date_from || filters.date_to;

    // フィルタをクリア
    // 目的: 画面モジュールのclear Filtersを扱う。機能: 入力値を検証・整形し、画面または計算処理へ渡す。入力: 関数シグネチャの値。出力: 処理結果またはなし。動作条件: 画面モジュールの初期化後に呼び出す。副作用: Vue状態、localStorage、DOM、HTTP通信のいずれかを更新する場合がある。
    const clearFilters = () => {
        filters.action = '';
        filters.resource_type = '';
        filters.date_from = '';
        filters.date_to = '';
        applyFilter();
    };

    // 目的: 画面モジュールのaction Labelを扱う。機能: 入力値を検証・整形し、画面または計算処理へ渡す。入力: 関数シグネチャの値。出力: 処理結果またはなし。動作条件: 画面モジュールの初期化後に呼び出す。副作用: Vue状態、localStorage、DOM、HTTP通信のいずれかを更新する場合がある。
    const actionLabel = (a) => ({ created: '作成', updated: '更新', deleted: '削除' }[a] ?? a);
    const resourceTypeLabels = {
        Component: '部品',
        Location: '棚',
        Supplier: '商社',
        Project: '案件',
        User: 'ユーザー',
        Package: 'パッケージ',
        PackageGroup: 'パッケージ分類',
        SpecGroup: '部品分類',
        SpecType: 'スペック詳細',
        SpecTemplate: '入力テンプレート',
        AltiumLibrary: 'Altiumライブラリ',
    };
    // 目的: 画面モジュールのresource Type Keyを扱う。機能: 入力値を検証・整形し、画面または計算処理へ渡す。入力: 関数シグネチャの値。出力: 処理結果またはなし。動作条件: 画面モジュールの初期化後に呼び出す。副作用: Vue状態、localStorage、DOM、HTTP通信のいずれかを更新する場合がある。
    const resourceTypeKey = (type) => String(type ?? '').split('\\').pop();
    // 目的: 画面モジュールのresource Type Labelを扱う。機能: 入力値を検証・整形し、画面または計算処理へ渡す。入力: 関数シグネチャの値。出力: 処理結果またはなし。動作条件: 画面モジュールの初期化後に呼び出す。副作用: Vue状態、localStorage、DOM、HTTP通信のいずれかを更新する場合がある。
    const resourceTypeLabel = (type) => resourceTypeLabels[resourceTypeKey(type)] ?? type;
    // 目的: 画面モジュールのresource Type Filter Valueを扱う。機能: 入力値を検証・整形し、画面または計算処理へ渡す。入力: 関数シグネチャの値。出力: 処理結果またはなし。動作条件: 画面モジュールの初期化後に呼び出す。副作用: Vue状態、localStorage、DOM、HTTP通信のいずれかを更新する場合がある。
    const resourceTypeFilterValue = (value) => {
        const trimmed = String(value ?? '').trim();
        const found = Object.entries(resourceTypeLabels).find(([, label]) => label === trimmed);
        return found?.[0] ?? trimmed;
    };
    // 目的: 画面モジュールのdiff Key Labelを扱う。機能: 入力値を検証・整形し、画面または計算処理へ渡す。入力: 関数シグネチャの値。出力: 処理結果またはなし。動作条件: 画面モジュールの初期化後に呼び出す。副作用: Vue状態、localStorage、DOM、HTTP通信のいずれかを更新する場合がある。
    const diffKeyLabel = (key) => ({
        part_number: '型番',
        common_name: '通称',
        manufacturer: 'メーカー',
        procurement_status: '調達状態',
        quantity_new: '新品在庫数',
        quantity_used: '中古在庫数',
        location_id: '棚',
        supplier_id: '商社',
        business_code: '事業',
        source_type: '登録元',
        deleted_at: '削除日時',
    }[key] ?? key);
    // 目的: 画面モジュールのaction Classを扱う。機能: 入力値を検証・整形し、画面または計算処理へ渡す。入力: 関数シグネチャの値。出力: 処理結果またはなし。動作条件: 画面モジュールの初期化後に呼び出す。副作用: Vue状態、localStorage、DOM、HTTP通信のいずれかを更新する場合がある。
    const actionClass = (a) => ({
        created: 'bg-emerald-100 text-emerald-700',
        updated: 'bg-blue-100 text-blue-700',
        deleted: 'bg-red-100 text-red-700',
    }[a] ?? '');

    // diff の before/after を読みやすく整形
    // 目的: 画面モジュールのdiff Linesを扱う。機能: 入力値を検証・整形し、画面または計算処理へ渡す。入力: 関数シグネチャの値。出力: 処理結果またはなし。動作条件: 画面モジュールの初期化後に呼び出す。副作用: Vue状態、localStorage、DOM、HTTP通信のいずれかを更新する場合がある。
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
             applyFilter, goPage, hasActiveFilter, clearFilters, actionLabel, actionClass, resourceTypeLabel, diffKeyLabel, diffLines, formatDate };
}
