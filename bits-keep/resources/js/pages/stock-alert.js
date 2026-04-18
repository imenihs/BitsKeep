import { ref, computed, onMounted } from 'vue';
import { api } from '../api.js';
import { useToast } from '../composables/useToast.js';
import { useStockOrderDraft } from '../composables/useStockOrderDraft.js';

export default function setup() {
    const { toasts, toastSuccess, toastError } = useToast();
    const alerts         = ref([]);
    const pendingOrders  = ref(new Map());
    const loading        = ref(false);
    const checkedIds     = ref([]);
    const { orderDraft, upsertOrderItem, removeOrderItem } = useStockOrderDraft();

    const toOrderItem = (alert) => {
        return {
            id: alert.id,
            name: alert.common_name || alert.part_number,
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

    const fetchAlerts = async () => {
        loading.value = true;
        try {
            const r = await api.get('/stock-alerts');
            alerts.value = r.data ?? [];
            checkedIds.value = checkedIds.value.filter((id) => alerts.value.some((alert) => alert.id === id));
            await fetchPendingOrders();
        }
        catch { toastError('在庫警告の取得に失敗しました'); }
        finally { loading.value = false; }
    };

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

    const inOrder = (alert) => {
        const inDraft = orderDraft.value.some(o => o.id === alert.id);
        const pending = (pendingOrders.value.get(alert.id) ?? []).length > 0;
        return inDraft || pending;
    };

    const isPending = (alert) => (pendingOrders.value.get(alert.id) ?? []).length > 0;
    const checkedAlerts = computed(() => alerts.value.filter((alert) => checkedIds.value.includes(alert.id) && !inOrder(alert)));

    const toggleChecked = (alertId) => {
        if (checkedIds.value.includes(alertId)) {
            checkedIds.value = checkedIds.value.filter((id) => id !== alertId);
            return;
        }
        checkedIds.value = [...checkedIds.value, alertId];
    };

    const addCheckedToOrder = () => {
        if (!checkedAlerts.value.length) {
            toastError('発注対象を選択してください');
            return;
        }
        checkedAlerts.value.forEach((alert) => upsertOrderItem(toOrderItem(alert)));
        checkedIds.value = [];
        toastSuccess('発注候補へ追加しました');
    };

    const urgencyClass = (u) => u < 0.3 ? 'tag-eol' : u < 1 ? 'tag-warning' : 'tag-ok';
    const orderCount = computed(() => orderDraft.value.length);
    const checkedCount = computed(() => checkedAlerts.value.length);

    onMounted(fetchAlerts);
    return {
        toasts,
        alerts,
        loading,
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
