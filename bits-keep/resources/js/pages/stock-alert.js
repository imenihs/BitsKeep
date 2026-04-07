import { ref, computed, onMounted } from 'vue';
import { api } from '../api.js';
import { useToast } from '../composables/useToast.js';

export default function setup() {
    const { toasts, toastSuccess, toastError } = useToast();
    const alerts     = ref([]);
    const loading    = ref(false);
    const orderList  = ref([]);
    const orderModal = ref(false);

    const fetchAlerts = async () => {
        loading.value = true;
        try { const r = await api.get('/stock-alerts'); alerts.value = r.data; }
        catch { toastError('在庫警告の取得に失敗しました'); }
        finally { loading.value = false; }
    };

    const inOrder = (alert) => orderList.value.some(o => o.id === alert.id);
    const toggleOrder = (alert) => {
        const idx = orderList.value.findIndex(o => o.id === alert.id);
        if (idx >= 0) orderList.value.splice(idx, 1);
        else orderList.value.push({
            id: alert.id, name: alert.common_name || alert.part_number,
            partNumber: alert.part_number,
            price: alert.cheapest_price ?? 0,
            supplierName: alert.cheapest_supplier?.name ?? '未設定',
            orderQty: Math.max((alert.threshold_new - alert.quantity_new), 1),
        });
    };

    // 商社別集計
    const orderBySupplier = computed(() => {
        const map = {};
        orderList.value.forEach(o => {
            if (!map[o.supplierName]) map[o.supplierName] = { items: [], total: 0 };
            map[o.supplierName].items.push(o);
            map[o.supplierName].total += o.price * o.orderQty;
        });
        return map;
    });

    const grandTotal = computed(() => orderList.value.reduce((s, o) => s + o.price * o.orderQty, 0));

    const exportCsv = () => {
        const header = '部品名,型番,商社,数量,単価,小計\n';
        const rows = orderList.value.map(o =>
            `"${o.name}","${o.partNumber}","${o.supplierName}",${o.orderQty},${o.price},${o.price * o.orderQty}`
        ).join('\n');
        const blob = new Blob(['\uFEFF' + header + rows], { type: 'text/csv;charset=utf-8;' });
        const url = URL.createObjectURL(blob);
        const a = document.createElement('a');
        a.href = url; a.download = `発注リスト_${new Date().toISOString().slice(0,10)}.csv`;
        a.click(); URL.revokeObjectURL(url);
    };

    const urgencyClass = (u) => u < 0.3 ? 'tag-eol' : u < 1 ? 'tag-warning' : 'tag-ok';

    onMounted(fetchAlerts);
    return { toasts, alerts, loading, orderList, orderModal, inOrder, toggleOrder, orderBySupplier, grandTotal, exportCsv, urgencyClass };
}
