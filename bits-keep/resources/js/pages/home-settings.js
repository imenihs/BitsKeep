import { computed, onMounted, ref } from 'vue';
import { api } from '../api.js';
import { useNavigationConfirm } from '../composables/useNavigationConfirm.js';

const QUICK_ACTIONS_PREF_KEY = 'home_quick_actions';
const ACTION_DEFS = [
    { key: 'components', label: '部品一覧', desc: '登録部品の検索・絞り込み', icon: '🔩', adminOnly: false },
    { key: 'create', label: '部品登録', desc: '新規部品を登録する', icon: '➕', adminOnly: false },
    { key: 'stock-alert', label: '在庫警告', desc: '発注点を下回る部品を確認', icon: '⚠️', adminOnly: false },
    { key: 'projects', label: '案件管理', desc: '案件ごとの部品・コスト管理', icon: '📋', adminOnly: false },
    { key: 'master', label: 'マスタ管理', desc: '分類・パッケージ・スペック種別', icon: '⚙️', adminOnly: false },
    { key: 'design-tools', label: '設計ツール', desc: 'ADC/電源/誤差/熱など設計解析', icon: '🔬', adminOnly: false },
    { key: 'calc', label: '電卓', desc: '式計算・進数変換・物理定数', icon: '🧮', adminOnly: false },
    { key: 'network', label: 'ネットワーク探索', desc: '抵抗/容量の直並列組み合わせ', icon: '🔌', adminOnly: false },
    { key: 'users', label: 'ユーザー管理', desc: 'ユーザーの招待・ロール変更', icon: '👤', adminOnly: true },
    { key: 'audit-logs', label: '操作ログ', desc: '変更履歴の監査ログ', icon: '📝', adminOnly: true },
    { key: 'csv-import', label: 'CSVインポート', desc: 'CSVで部品を一括登録', icon: '📥', adminOnly: true },
];
const DEFAULT_QUICK_ACTION_KEYS = ['components', 'create', 'stock-alert', 'projects', 'design-tools'];

export default function setup() {
    const appEl = document.getElementById('app');
    const userRole = appEl?.dataset?.role ?? 'viewer';
    const actionsSaving = ref(false);
    useNavigationConfirm(actionsSaving, '保存処理中です。このまま画面を離れてもよいですか？');
    const actionsMessage = ref('');
    const actionsError = ref('');
    const quickActionKeys = ref([]);
    const dragPayload = ref(null);

    const availableActions = computed(() =>
        ACTION_DEFS.filter((action) => !action.adminOnly || userRole === 'admin')
    );
    const visibleKeys = computed(() => {
        const fallback = DEFAULT_QUICK_ACTION_KEYS.filter((key) =>
            availableActions.value.some((action) => action.key === key)
        );
        return quickActionKeys.value.length ? quickActionKeys.value : fallback;
    });
    const visibleActions = computed(() =>
        visibleKeys.value
            .map((key) => availableActions.value.find((action) => action.key === key))
            .filter(Boolean)
    );
    const hiddenActions = computed(() =>
        availableActions.value.filter((action) => !visibleKeys.value.includes(action.key))
    );

    const loadQuickActions = async () => {
        actionsError.value = '';
        try {
            const r = await api.get(`/preferences/${QUICK_ACTIONS_PREF_KEY}`);
            const value = r.data?.data?.value ?? r.data?.value;
            quickActionKeys.value = Array.isArray(value) ? value : [];
        } catch {
            quickActionKeys.value = [];
        }
    };

    const persistQuickActions = async () => {
        if (visibleActions.value.length === 0) {
            actionsMessage.value = '';
            actionsError.value = '主要アクションを1件以上選択してください。';
            return;
        }

        actionsSaving.value = true;
        actionsMessage.value = '';
        actionsError.value = '';
        try {
            await api.put(`/preferences/${QUICK_ACTIONS_PREF_KEY}`, {
                value: visibleActions.value.map((action) => action.key),
            });
            quickActionKeys.value = visibleActions.value.map((action) => action.key);
            actionsMessage.value = '主要アクションを保存しました';
        } catch (e) {
            actionsError.value = e.message ?? '主要アクションの保存に失敗しました。';
        } finally {
            actionsSaving.value = false;
        }
    };

    const resetQuickActions = () => {
        quickActionKeys.value = DEFAULT_QUICK_ACTION_KEYS.filter((key) =>
            availableActions.value.some((action) => action.key === key)
        );
        actionsMessage.value = '';
        actionsError.value = '';
    };

    const startDrag = (list, index) => {
        dragPayload.value = { list, index };
    };

    const allowDrop = (e) => {
        e.preventDefault();
    };

    const moveAction = (toList, toIndex = null) => {
        if (!dragPayload.value) return;

        const sourceVisible = [...visibleActions.value];
        const sourceHidden = [...hiddenActions.value];
        const fromList = dragPayload.value.list;
        const fromIndex = dragPayload.value.index;
        const sourceArray = fromList === 'visible' ? sourceVisible : sourceHidden;
        const targetArray = toList === 'visible' ? sourceVisible : sourceHidden;
        const [moved] = sourceArray.splice(fromIndex, 1);

        if (!moved) {
            dragPayload.value = null;
            return;
        }

        let insertIndex = toIndex ?? targetArray.length;
        if (fromList === toList && fromIndex < insertIndex) insertIndex -= 1;
        targetArray.splice(insertIndex, 0, moved);

        quickActionKeys.value = sourceVisible.map((action) => action.key);
        dragPayload.value = null;
        actionsMessage.value = '';
        actionsError.value = '';
    };

    onMounted(loadQuickActions);

    return {
        actionsSaving,
        actionsMessage,
        actionsError,
        visibleActions,
        hiddenActions,
        persistQuickActions,
        resetQuickActions,
        startDrag,
        allowDrop,
        moveAction,
    };
}
