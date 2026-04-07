/**
 * ダッシュボード（SCR-000）
 * - グローバル検索ランチャー（Ctrl+K）
 * - 今日の確認事項カード（在庫警告・最近アクセス）
 * - クイックアクション
 */
import { ref, reactive, computed, onMounted, onUnmounted } from 'vue';
import { api } from '../api.js';

export default function setup() {
    const userName  = document.getElementById('app')?.dataset?.userName ?? 'ユーザー';
    const userRole  = document.getElementById('app')?.dataset?.role ?? 'viewer';

    // ── グローバル検索 ─────────────────────────────────────
    const searchOpen  = ref(false);
    const searchQuery = ref('');
    const searchResults = ref([]);
    const searching   = ref(false);
    let searchTimer   = null;

    const openSearch  = () => { searchOpen.value = true; searchQuery.value = ''; searchResults.value = []; };
    const closeSearch = () => { searchOpen.value = false; };

    const doSearch = async () => {
        if (!searchQuery.value.trim()) { searchResults.value = []; return; }
        searching.value = true;
        try {
            // 部品・案件・棚を並列検索
            const [partsRes, projectsRes] = await Promise.allSettled([
                api.get(`/components?q=${encodeURIComponent(searchQuery.value)}&per_page=5`),
                api.get(`/projects?q=${encodeURIComponent(searchQuery.value)}&per_page=3`),
            ]);

            const results = [];
            if (partsRes.status === 'fulfilled') {
                const items = partsRes.value.data?.data ?? partsRes.value.data ?? [];
                items.forEach(c => results.push({
                    type: 'component', icon: '🔩',
                    label: c.common_name || c.part_number,
                    sub: c.part_number,
                    url: `/components/${c.id}`,
                }));
            }
            if (projectsRes.status === 'fulfilled') {
                const items = projectsRes.value.data?.data ?? projectsRes.value.data ?? [];
                items.forEach(p => results.push({
                    type: 'project', icon: '📋',
                    label: p.name,
                    sub: p.status === 'active' ? '進行中' : 'アーカイブ',
                    url: `/projects`,
                }));
            }
            searchResults.value = results;
        } catch { /* 無視 */ }
        finally { searching.value = false; }
    };

    const onSearchInput = () => {
        clearTimeout(searchTimer);
        searchTimer = setTimeout(doSearch, 300);
    };

    const navigate = (url) => { closeSearch(); location.href = url; };

    // ── 今日の確認事項 ──────────────────────────────────────
    const alertCount   = ref(0);
    const recentParts  = ref([]);

    const fetchSummary = async () => {
        try {
            const [alertRes, partsRes] = await Promise.allSettled([
                api.get('/stock-alerts'),
                api.get('/components?per_page=5&sort=updated_at'),
            ]);
            if (alertRes.status === 'fulfilled') {
                alertCount.value = alertRes.value.data?.length ?? 0;
            }
            if (partsRes.status === 'fulfilled') {
                recentParts.value = partsRes.value.data?.data ?? partsRes.value.data ?? [];
            }
        } catch { /* 無視 */ }
    };

    // ── クイックアクション ──────────────────────────────────
    const quickActions = computed(() => {
        const base = [
            { label: '部品一覧', url: '/components', icon: '🔩' },
            { label: '部品登録', url: '/components/create', icon: '➕' },
            { label: '在庫警告', url: '/stock-alert', icon: '⚠️', badge: alertCount.value || null },
            { label: '案件管理', url: '/projects', icon: '📋' },
            { label: 'マスタ管理', url: '/master', icon: '⚙️' },
            { label: '設計ツール', url: '/tools/design', icon: '🔬' },
            { label: '電卓', url: '/tools/calc', icon: '🧮' },
        ];
        // admin追加
        if (userRole === 'admin') {
            base.push(
                { label: 'ユーザー管理', url: '/users', icon: '👤' },
                { label: '操作ログ', url: '/audit-logs', icon: '📝' },
                { label: 'CSVインポート', url: '/csv-import', icon: '📥' },
            );
        }
        return base;
    });

    // Ctrl+K でランチャー起動
    const onKeyDown = (e) => {
        if ((e.ctrlKey || e.metaKey) && e.key === 'k') {
            e.preventDefault();
            openSearch();
        }
        if (e.key === 'Escape') closeSearch();
    };

    onMounted(() => {
        fetchSummary();
        window.addEventListener('keydown', onKeyDown);
    });
    onUnmounted(() => window.removeEventListener('keydown', onKeyDown));

    return { userName, userRole, searchOpen, searchQuery, searchResults, searching,
             openSearch, closeSearch, onSearchInput, navigate,
             alertCount, recentParts, quickActions };
}
