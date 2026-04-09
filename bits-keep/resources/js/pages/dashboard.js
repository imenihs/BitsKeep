/**
 * ダッシュボード（SCR-000）
 * - 検索主導のホーム
 * - 主要アクション並び替え
 * - 主要導線をセクション化
 */
import { ref, computed, onMounted, onUnmounted } from 'vue';
import { api } from '../api.js';

// 全クイックアクション定義（keyで識別）
const ACTION_DEFS = [
    { key: 'components',    label: '部品一覧',     url: '/components',        icon: '🔩' },
    { key: 'create',        label: '部品登録',     url: '/components/create', icon: '➕' },
    { key: 'stock-alert',   label: '在庫警告',     url: '/stock-alert',       icon: '⚠️' },
    { key: 'projects',      label: '案件管理',     url: '/projects',          icon: '📋' },
    { key: 'master',        label: 'マスタ管理',   url: '/master',            icon: '⚙️' },
    { key: 'design-tools',  label: '設計ツール',   url: '/tools/design',      icon: '🔬' },
    { key: 'calc',          label: '電卓',         url: '/tools/calc',        icon: '🧮' },
    { key: 'network',       label: 'ネットワーク探索', url: '/tools/network',  icon: '🔌' },
    { key: 'users',         label: 'ユーザー管理', url: '/users',             icon: '👤', adminOnly: true },
    { key: 'audit-logs',    label: '操作ログ',     url: '/audit-logs',        icon: '📝', adminOnly: true },
    { key: 'csv-import',    label: 'CSVインポート',url: '/csv-import',        icon: '📥', adminOnly: true },
];

const PREF_KEY = 'home_card_order';

export default function setup() {
    const userName  = document.getElementById('app')?.dataset?.userName ?? 'ユーザー';
    const userRole  = document.getElementById('app')?.dataset?.role ?? 'viewer';
    const sectionLinks = [
        { href: '#launcher-section', label: '検索と起点' },
        { href: '#today-section', label: '今日の確認事項' },
        { href: '#quick-actions-section', label: '主要アクション' },
        { href: '#recent-section', label: '最近の部品' },
    ];
    const focusModes = ['全部', '部品', '案件', '機能'];
    const activeFocus = ref('全部');

    // ── グローバル検索 ─────────────────────────────────────
    const searchOpen    = ref(false);
    const searchQuery   = ref('');
    const searchResults = ref([]);
    const searching     = ref(false);
    const searchError   = ref('');
    let searchTimer     = null;

    const openSearch  = () => { searchOpen.value = true; searchQuery.value = ''; searchResults.value = []; };
    const closeSearch = () => { searchOpen.value = false; };
    const setFocus = (mode) => { activeFocus.value = mode; };

    const doSearch = async () => {
        if (!searchQuery.value.trim()) {
            searchResults.value = [];
            searchError.value = '';
            return;
        }
        searching.value = true;
        searchError.value = '';
        try {
            const [partsRes, projectsRes] = await Promise.allSettled([
                api.get(`/components?q=${encodeURIComponent(searchQuery.value)}&per_page=5`),
                api.get(`/projects?q=${encodeURIComponent(searchQuery.value)}&per_page=3`),
            ]);
            const results = [];
            if (partsRes.status === 'fulfilled' && activeFocus.value !== '案件' && activeFocus.value !== '機能') {
                (partsRes.value.data?.data ?? partsRes.value.data ?? []).forEach(c => results.push({
                    type: 'component', icon: '🔩',
                    label: c.common_name || c.part_number,
                    sub: c.part_number,
                    url: `/components/${c.id}`,
                }));
            }
            if (projectsRes.status === 'fulfilled' && activeFocus.value !== '部品' && activeFocus.value !== '機能') {
                (projectsRes.value.data?.data ?? projectsRes.value.data ?? []).forEach(p => results.push({
                    type: 'project', icon: '📋',
                    label: p.name,
                    sub: p.status === 'active' ? '進行中' : 'アーカイブ',
                    url: `/projects`,
                }));
            }
            if (activeFocus.value === '全部' || activeFocus.value === '機能') {
                availableActions.value
                    .filter((action) => action.label.includes(searchQuery.value))
                    .slice(0, 4)
                    .forEach((action) => results.push({
                        type: 'function',
                        icon: action.icon,
                        label: action.label,
                        sub: '主要アクション',
                        url: action.url,
                    }));
            }
            searchResults.value = results;
        } catch {
            searchError.value = '検索に失敗しました。再試行するか、部品一覧・案件管理から直接進んでください。';
            searchResults.value = [];
        }
        finally { searching.value = false; }
    };

    const onSearchInput = () => { clearTimeout(searchTimer); searchTimer = setTimeout(doSearch, 300); };
    const navigate = (url) => { closeSearch(); location.href = url; };

    // ── 今日の確認事項 ──────────────────────────────────────
    const alertCount  = ref(0);
    const recentParts = ref([]);
    const projectCount = ref(0);
    const summaryError = ref('');

    const fetchSummary = async () => {
        summaryError.value = '';
        try {
            const [alertRes, partsRes, projectsRes] = await Promise.allSettled([
                api.get('/stock-alerts'),
                api.get('/components?per_page=5&sort=updated_at'),
                api.get('/projects?per_page=1'),
            ]);
            if (alertRes.status === 'fulfilled')  alertCount.value  = alertRes.value.data?.length ?? 0;
            if (partsRes.status === 'fulfilled')  recentParts.value = partsRes.value.data?.data ?? partsRes.value.data ?? [];
            if (projectsRes.status === 'fulfilled') {
                projectCount.value = projectsRes.value.data?.meta?.total ?? projectsRes.value.data?.total ?? projectsRes.value.data?.data?.length ?? 0;
            }
            if (alertRes.status !== 'fulfilled' || partsRes.status !== 'fulfilled' || projectsRes.status !== 'fulfilled') {
                summaryError.value = '一部のサマリー取得に失敗しました。再読込するか、各一覧画面から直接確認してください。';
            }
        } catch {
            summaryError.value = 'ホーム要約の取得に失敗しました。再読込するか、各一覧画面から直接確認してください。';
        }
    };

    // ── クイックアクション（並び替え対応） ─────────────────
    const cardOrder       = ref([]);   // ユーザー保存順（keyの配列）
    const sortMode        = ref(false);// 並び替えモード ON/OFF
    const savingOrder     = ref(false);
    const dragSrcIndex    = ref(null);

    // roleに応じた利用可能なアクションのみ返す
    const availableActions = computed(() =>
        ACTION_DEFS.filter(a => !a.adminOnly || userRole === 'admin')
    );

    // 保存済み順序でソートしたアクション（未登録のキーは末尾に追加）
    const sortedActions = computed(() => {
        const avail = availableActions.value;
        if (!cardOrder.value.length) return avail;
        const ordered = [];
        cardOrder.value.forEach(key => {
            const a = avail.find(x => x.key === key);
            if (a) ordered.push(a);
        });
        // 保存済み順序にないアクション（新規追加分）を末尾に追加
        avail.forEach(a => { if (!cardOrder.value.includes(a.key)) ordered.push(a); });
        return ordered;
    });

    // 在庫警告バッジを付与したアクション一覧
    const quickActions = computed(() =>
        sortedActions.value.map(a =>
            a.key === 'stock-alert' ? { ...a, badge: alertCount.value || null } : a
        )
    );
    const statusCards = computed(() => ([
        {
            title: '在庫警告',
            value: alertCount.value > 0 ? `${alertCount.value}件` : 'なし',
            desc: alertCount.value > 0 ? '発注点を下回る部品があります' : '今は緊急対応はありません',
            color: '#d97706',
            icon: '⚠️',
            url: '/stock-alert',
        },
        {
            title: '登録案件',
            value: `${projectCount.value}件`,
            desc: 'Notion同期案件と独自案件を統合表示',
            color: '#0f766e',
            icon: '📋',
            url: '/projects',
        },
        {
            title: '最近更新',
            value: `${recentParts.value.length}件`,
            desc: '更新された部品から再開できます',
            color: '#2563eb',
            icon: '🔩',
            url: '/components',
        },
    ]));
    const recentItems = computed(() => recentParts.value.slice(0, 5).map((part) => ({
        name: part.common_name || part.part_number,
        group: `${part.part_number} / 在庫 ${part.quantity_new ?? 0}`,
        href: `/components/${part.id}`,
        icon: '🔩',
    })));
    const launcherResults = computed(() => {
        if (searchQuery.value.trim()) return searchResults.value;
        return [
            ...quickActions.value.slice(0, 4).map((action) => ({
                type: 'function',
                icon: action.icon,
                label: action.label,
                sub: '主要アクション',
                url: action.url,
            })),
            ...recentParts.value.slice(0, 3).map((part) => ({
                type: 'component',
                icon: '🔩',
                label: part.common_name || part.part_number,
                sub: part.part_number,
                url: `/components/${part.id}`,
            })),
        ];
    });

    // ユーザー設定から並び順を取得
    const loadOrder = async () => {
        try {
            const res = await api.get(`/preferences/${PREF_KEY}`);
            const val = res.data?.data?.value ?? res.data?.value;
            if (Array.isArray(val)) cardOrder.value = val;
        } catch { /* 未設定なら無視 */ }
    };

    // 並び順を保存
    const saveOrder = async () => {
        savingOrder.value = true;
        try {
            const order = sortedActions.value.map(a => a.key);
            await api.put(`/preferences/${PREF_KEY}`, { value: order });
            cardOrder.value = order;
            sortMode.value  = false;
        } catch {
            summaryError.value = 'クイックアクションの並び順保存に失敗しました。再試行してください。';
        }
        finally { savingOrder.value = false; }
    };

    // ドラッグ&ドロップで並び替え（並び替えモード時のみ有効）
    const onDragStart = (index) => { dragSrcIndex.value = index; };
    const onDragOver  = (e) => { e.preventDefault(); };
    const onDrop      = (index) => {
        if (dragSrcIndex.value === null || dragSrcIndex.value === index) return;
        const arr  = [...sortedActions.value.map(a => a.key)];
        const [removed] = arr.splice(dragSrcIndex.value, 1);
        arr.splice(index, 0, removed);
        cardOrder.value   = arr;
        dragSrcIndex.value = null;
    };

    // Ctrl+K でランチャー起動
    const onKeyDown = (e) => {
        if ((e.ctrlKey || e.metaKey) && e.key === 'k') { e.preventDefault(); openSearch(); }
        if (e.key === 'Escape') closeSearch();
    };
    const openItem = (item) => navigate(item.url || item.href);
    const openFirstResult = () => {
        const first = launcherResults.value[0];
        if (first) openItem(first);
    };

    onMounted(() => {
        fetchSummary();
        loadOrder();
        window.addEventListener('keydown', onKeyDown);
    });
    onUnmounted(() => window.removeEventListener('keydown', onKeyDown));

    return {
        userName, userRole,
        sectionLinks, focusModes, activeFocus, setFocus,
        searchOpen, searchQuery, searchResults, searching,
        openSearch, closeSearch, onSearchInput, navigate, launcherResults, openItem, openFirstResult, doSearch,
        alertCount, recentParts, statusCards, recentItems, searchError, summaryError, fetchSummary,
        quickActions, sortMode, savingOrder,
        saveOrder, onDragStart, onDragOver, onDrop,
    };
}
