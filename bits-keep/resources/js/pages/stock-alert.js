import { ref, computed, onMounted } from 'vue';
import { api } from '../api.js';
import { useToast } from '../composables/useToast.js';

export default function setup() {
    const { toasts, toastSuccess, toastError } = useToast();
    const alerts     = ref([]);
    const loading    = ref(false);
    const orderList  = ref([]);
    const orderModal = ref(false);
    const purchaseUnitOptions = [
        { value: 'loose', label: 'バラ' },
        { value: 'tape', label: 'テープ' },
        { value: 'tray', label: 'トレー' },
        { value: 'reel', label: 'リール' },
        { value: 'box', label: '箱' },
    ];
    const purchaseUnitLabel = (value) => purchaseUnitOptions.find((option) => option.value === value)?.label ?? '未設定';

    const toOrderItem = (alert) => {
        const selected = selectedSupplier(alert);
        return {
            id: alert.id,
            name: alert.common_name || alert.part_number,
            partNumber: alert.part_number,
            packageName: alert.package_name ?? '',
            supplierId: selected?.supplier_id ?? null,
            supplierName: selected?.name ?? '未設定',
            supplierPartNumber: selected?.supplier_part_number ?? '',
            purchaseUnit: alert.selected_purchase_unit ?? selected?.purchase_unit ?? '',
            price: selected?.unit_price ?? 0,
            orderQty: Math.max((alert.threshold_new - alert.quantity_new), 1),
        };
    };

    const selectedSupplier = (alert) => {
        const options = alert.supplier_options ?? [];
        return options.find((option) => String(option.supplier_id) === String(alert.selected_supplier_id))
            ?? null;
    };

    const syncOrderSelection = (alert) => {
        const selected = selectedSupplier(alert);
        if (!alert.selected_purchase_unit) {
            alert.selected_purchase_unit = selected?.purchase_unit ?? '';
        }

        const orderItem = orderList.value.find((item) => item.id === alert.id);
        if (!orderItem) return;
        orderItem.supplierId = selected?.supplier_id ?? null;
        orderItem.supplierName = selected?.name ?? '未設定';
        orderItem.supplierPartNumber = selected?.supplier_part_number ?? '';
        orderItem.purchaseUnit = alert.selected_purchase_unit;
        orderItem.price = selected?.unit_price ?? 0;
    };

    const syncOrderPurchaseUnit = (alert) => {
        const orderItem = orderList.value.find((item) => item.id === alert.id);
        if (!orderItem) return;
        orderItem.purchaseUnit = alert.selected_purchase_unit ?? '';
    };

    const fetchAlerts = async () => {
        loading.value = true;
        try {
            const r = await api.get('/stock-alerts');
            alerts.value = (r.data ?? []).map((alert) => ({
                ...alert,
                selected_supplier_id: '',
                selected_purchase_unit: '',
            }));
        }
        catch { toastError('在庫警告の取得に失敗しました'); }
        finally { loading.value = false; }
    };

    const inOrder = (alert) => orderList.value.some(o => o.id === alert.id);
    const canOrder = (alert) => !!alert.selected_supplier_id && !!alert.selected_purchase_unit;
    const toggleOrder = (alert) => {
        if (!inOrder(alert) && !canOrder(alert)) {
            toastError(!alert.selected_supplier_id ? '購入商社を選択してください' : '今回の購入単位を選択してください');
            return;
        }
        const idx = orderList.value.findIndex(o => o.id === alert.id);
        if (idx >= 0) orderList.value.splice(idx, 1);
        else orderList.value.push(toOrderItem(alert));
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

    const exportSupplierCsv = (supplierName, group) => {
        const header = '部品名,型番,パッケージ,購入単位,商社,数量,単価,小計\n';
        const rows = group.items.map(o =>
            `"${o.name}","${o.partNumber}","${o.packageName ?? ''}","${purchaseUnitLabel(o.purchaseUnit)}","${o.supplierName}",${o.orderQty},${o.price},${o.price * o.orderQty}`
        ).join('\n');
        const blob = new Blob(['\uFEFF' + header + rows], { type: 'text/csv;charset=utf-8;' });
        const url = URL.createObjectURL(blob);
        const a = document.createElement('a');
        const safeName = supplierName.replace(/[\\/:*?"<>|]/g, '_');
        a.href = url; a.download = `発注リスト_${safeName}_${new Date().toISOString().slice(0,10)}.csv`;
        a.click(); URL.revokeObjectURL(url);
        toastSuccess(`${supplierName} のCSVを出力しました`);
    };

    const urgencyClass = (u) => u < 0.3 ? 'tag-eol' : u < 1 ? 'tag-warning' : 'tag-ok';

    onMounted(fetchAlerts);
    return {
        toasts,
        alerts,
        loading,
        orderList,
        orderModal,
        inOrder,
        canOrder,
        toggleOrder,
        orderBySupplier,
        grandTotal,
        exportSupplierCsv,
        urgencyClass,
        selectedSupplier,
        syncOrderSelection,
        syncOrderPurchaseUnit,
        purchaseUnitOptions,
        purchaseUnitLabel,
    };
}
