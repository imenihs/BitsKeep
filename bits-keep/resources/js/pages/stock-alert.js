import { ref, computed, onMounted } from 'vue';
import { api } from '../api.js';
import { useToast } from '../composables/useToast.js';
import { useStockOrderDraft } from '../composables/useStockOrderDraft.js';

// 目的: 在庫/発注管理のsetupを扱う。機能: 入力値を検証・整形し、画面または計算処理へ渡す。入力: 関数シグネチャの値。出力: 処理結果またはなし。動作条件: 在庫/発注管理の初期化後に呼び出す。副作用: Vue状態、localStorage、DOM、HTTP通信のいずれかを更新する場合がある。
export default function setup() {
    const { toasts, toastSuccess, toastError } = useToast();
    const alerts         = ref([]);
    const fetchError     = ref('');
    const pendingOrders  = ref(new Map());
    const loading        = ref(false);
    const checkedIds     = ref([]);
    const { orderDraft, upsertOrderItem, removeOrderItem } = useStockOrderDraft();

    // 目的: 在庫/発注管理のto Order Itemを扱う。機能: 入力値を検証・整形し、画面または計算処理へ渡す。入力: 関数シグネチャの値。出力: 処理結果またはなし。動作条件: 在庫/発注管理の初期化後に呼び出す。副作用: なし。
    const toOrderItem = (alert) => {
        return {
            id: alert.id,
            name: alert.part_number || alert.common_name,
            partNumber: alert.part_number,
            packageName: alert.package_name ?? '',
            quantityNew: alert.quantity_new ?? 0,
            quantityUsed: alert.quantity_used ?? 0,
            supplierId: '',
            supplierName: '',
            supplierPartNumber: '',
            purchaseUnit: '',
            price: 0,
            orderQty: 0,
            supplierOptions: alert.supplier_options ?? [],
        };
    };

    // 目的: 在庫/発注管理のfetch Alertsを扱う。機能: 入力値を検証・整形し、画面または計算処理へ渡す。入力: 関数シグネチャの値。出力: 処理結果またはなし。動作条件: 在庫/発注管理の初期化後に呼び出す。副作用: Vue状態、localStorage、DOM、HTTP通信のいずれかを更新する場合がある。
    const fetchAlerts = async () => {
        loading.value = true;
        try {
            const r = await api.get('/stock-alerts');
            alerts.value = r.data ?? [];
            checkedIds.value = checkedIds.value.filter((id) => alerts.value.some((alert) => alert.id === id));
            await fetchPendingOrders();
        }
        catch { fetchError.value = '在庫警告の取得に失敗しました。再試行するか、しばらく待ってから再読み込みしてください。'; toastError('在庫警告の取得に失敗しました'); }
        finally { loading.value = false; }
    };

    // 目的: 在庫/発注管理のfetch Pending Ordersを扱う。機能: 入力値を検証・整形し、画面または計算処理へ渡す。入力: 関数シグネチャの値。出力: 処理結果またはなし。動作条件: 在庫/発注管理の初期化後に呼び出す。副作用: Vue状態、localStorage、DOM、HTTP通信のいずれかを更新する場合がある。
    const fetchPendingOrders = async () => {
        try {
            const requests = alerts.value.map(alert =>
                api.get(`/stock-orders/component/${alert.id}/pending`)
                    .then(r => ({ componentId: alert.id, orders: r.data ?? [] }))
                    .catch(() => ({ componentId: alert.id, orders: [] }))
            );
            const results = await Promise.all(requests);
            const map = new Map();
            results.forEach(({ componentId, orders }) => {
                map.set(componentId, orders);
            });
            pendingOrders.value = map;
        } catch {
            // Silently handle pending orders fetch failure
        }
    };

    // 目的: 在庫/発注管理のin Orderを扱う。機能: 入力値を検証・整形し、画面または計算処理へ渡す。入力: 関数シグネチャの値。出力: 処理結果またはなし。動作条件: 在庫/発注管理の初期化後に呼び出す。副作用: Vue状態、localStorage、DOM、HTTP通信のいずれかを更新する場合がある。
    const inOrder = (alert) => {
        const inDraft = orderDraft.value.some(o => o.id === alert.id);
        const pending = (pendingOrders.value.get(alert.id) ?? []).length > 0;
        return inDraft || pending;
    };

    // 目的: 在庫/発注管理のis Pendingを扱う。機能: 入力値を検証・整形し、画面または計算処理へ渡す。入力: 関数シグネチャの値。出力: 真偽値。動作条件: 在庫/発注管理の初期化後に呼び出す。副作用: なし。
    const isPending = (alert) => (pendingOrders.value.get(alert.id) ?? []).length > 0;
    const checkedAlerts = computed(() => alerts.value.filter((alert) => checkedIds.value.includes(alert.id) && !inOrder(alert)));

    // 目的: 在庫/発注管理のtoggle Checkedを扱う。機能: 入力値を検証・整形し、画面または計算処理へ渡す。入力: 関数シグネチャの値。出力: 処理結果またはなし。動作条件: 在庫/発注管理の初期化後に呼び出す。副作用: なし。
    const toggleChecked = (alertId) => {
        if (checkedIds.value.includes(alertId)) {
            checkedIds.value = checkedIds.value.filter((id) => id !== alertId);
            return;
        }
        checkedIds.value = [...checkedIds.value, alertId];
    };

    // 目的: 在庫/発注管理のadd Checked To Orderを扱う。機能: 入力値を検証・整形し、画面または計算処理へ渡す。入力: 関数シグネチャの値。出力: 処理結果またはなし。動作条件: 在庫/発注管理の初期化後に呼び出す。副作用: Vue状態、localStorage、DOM、HTTP通信のいずれかを更新する場合がある。
    const addCheckedToOrder = () => {
        if (!checkedAlerts.value.length) {
            toastError('発注対象を選択してください');
            return;
        }
        checkedAlerts.value.forEach((alert) => upsertOrderItem(toOrderItem(alert)));
        checkedIds.value = [];
        toastSuccess('発注候補へ追加しました');
    };

    // 目的: 在庫/発注管理のurgency Classを扱う。機能: 入力値を検証・整形し、画面または計算処理へ渡す。入力: 関数シグネチャの値。出力: 処理結果またはなし。動作条件: 在庫/発注管理の初期化後に呼び出す。副作用: Vue状態、localStorage、DOM、HTTP通信のいずれかを更新する場合がある。
    const urgencyClass = (u) => u < 0.3 ? 'tag-eol' : u < 1 ? 'tag-warning' : 'tag-ok';
    const orderCount = computed(() => orderDraft.value.length);
    const checkedCount = computed(() => checkedAlerts.value.length);

    onMounted(fetchAlerts);
    return {
        toasts,
        alerts,
        fetchError,
        loading,
        fetchAlerts,
        checkedIds,
        orderDraft,
        pendingOrders,
        orderCount,
        checkedCount,
        checkedAlerts,
        inOrder,
        isPending,
        toggleChecked,
        addCheckedToOrder,
        urgencyClass,
        removeOrderItem,
    };
}
