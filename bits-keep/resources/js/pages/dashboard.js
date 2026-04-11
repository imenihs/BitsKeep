/**
 * ダッシュボード（SCR-000）
 * - 検索主導のホーム
 * - 主要アクション表示
 * - 主要導線をセクション化
 */
import { ref, computed, onMounted } from 'vue';
import { api } from '../api.js';

// 全クイックアクション定義（keyで識別）
const ACTION_DEFS = [
    { key: 'components',    label: '部品一覧',     desc: '登録部品の検索・絞り込み', url: '/components',        icon: '🔩' },
    { key: 'create',        label: '部品登録',     desc: '新規部品を登録する',       url: '/components/create', icon: '➕' },
    { key: 'stock-alert',   label: '在庫警告',     desc: '発注点を下回る部品を確認', url: '/stock-alert',       icon: '⚠️' },
    { key: 'projects',      label: '案件管理',     desc: '案件ごとの部品・コスト管理', url: '/projects',          icon: '📋' },
    { key: 'master',        label: 'マスタ管理',   desc: '分類・パッケージ・スペック種別', url: '/master',     icon: '⚙️' },
    { key: 'design-tools',  label: '設計ツール',   desc: 'ADC/電源/誤差/熱など設計解析', url: '/tools/design', icon: '🔬' },
    { key: 'calc',          label: '電卓',         desc: '式計算・進数変換・物理定数', url: '/tools/calc',   icon: '🧮' },
    { key: 'network',       label: 'ネットワーク探索', desc: '抵抗/容量の直並列組み合わせ', url: '/tools/network', icon: '🔌' },
    { key: 'users',         label: 'ユーザー管理', desc: 'ユーザーの招待・ロール変更', url: '/users',        icon: '👤', adminOnly: true },
    { key: 'audit-logs',    label: '操作ログ',     desc: '変更履歴の監査ログ',         url: '/audit-logs',   icon: '📝', adminOnly: true },
    { key: 'csv-import',    label: 'CSVインポート',desc: 'CSVで部品を一括登録',       url: '/csv-import',   icon: '📥', adminOnly: true },
];

const QUICK_ACTIONS_PREF_KEY = 'home_quick_actions';
const DEFAULT_QUICK_ACTION_KEYS = ['components', 'create', 'stock-alert', 'projects', 'design-tools'];

export default function setup() {
    const userName  = document.getElementById('app')?.dataset?.userName ?? 'ユーザー';
    const userRole  = document.getElementById('app')?.dataset?.role ?? 'viewer';
    const sectionLinks = [
        { href: '#launcher-section', label: '検索と起点' },
        { href: '#today-section', label: '今日の確認事項' },
        { href: '#quick-actions-section', label: '業務別メニュー' },
        { href: '#recent-section', label: '最近使った機能' },
        { href: '#all-functions-section', label: '全機能ショートカット' },
    ];
    const focusModes = ['全部', '部品', '案件', '機能'];
    const activeFocus = ref('全部');

    // ── グローバル検索 ─────────────────────────────────────
    const searchQuery   = ref('');
    const searchResults = ref([]);
    const searching     = ref(false);
    const searchError   = ref('');
    let searchTimer     = null;

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
                        sub: action.desc,
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
    const navigate = (url) => { location.href = url; };

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

    // ── クイックアクション ────────────────────────────────
    const quickActionKeys = ref([]);
    const preferenceError = ref('');

    // roleに応じた利用可能なアクションのみ返す
    const availableActions = computed(() =>
        ACTION_DEFS.filter(a => !a.adminOnly || userRole === 'admin')
    );

    const orderedAvailableActions = computed(() => {
        const avail = availableActions.value;
        if (!quickActionKeys.value.length) return avail;
        const ordered = [];
        quickActionKeys.value.forEach((key) => {
            const a = avail.find(x => x.key === key);
            if (a) ordered.push(a);
        });
        avail.forEach((a) => {
            if (!quickActionKeys.value.includes(a.key)) ordered.push(a);
        });
        return ordered;
    });

    const visibleQuickActionKeys = computed(() => {
        const fallback = DEFAULT_QUICK_ACTION_KEYS.filter((key) =>
            availableActions.value.some((action) => action.key === key)
        );

        return quickActionKeys.value.length ? quickActionKeys.value : fallback;
    });

    const quickActions = computed(() =>
        orderedAvailableActions.value
            .filter((action) => visibleQuickActionKeys.value.includes(action.key))
            .map(a =>
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
                sub: action.desc,
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
            const res = await api.get(`/preferences/${QUICK_ACTIONS_PREF_KEY}`);
            const val = res.data?.data?.value ?? res.data?.value;
            if (Array.isArray(val)) quickActionKeys.value = val;
            preferenceError.value = '';
        } catch {
            preferenceError.value = '主要アクション設定を読めませんでした。ホーム設定で並び順を再保存するか、既定表示で続行してください。';
        }
    };

    const openItem = (item) => navigate(item.url || item.href);
    const openFirstResult = () => {
        const first = launcherResults.value[0];
        if (first) openItem(first);
    };

    onMounted(() => {
        fetchSummary();
        loadOrder();
    });

    return {
        userName, userRole,
        sectionLinks, focusModes, activeFocus, setFocus,
        searchQuery, searchResults, searching,
        onSearchInput, navigate, launcherResults, openItem, openFirstResult, doSearch,
        alertCount, recentParts, statusCards, recentItems, searchError, summaryError, fetchSummary,
        quickActions, preferenceError,
    };
}
