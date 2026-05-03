import { computed, onMounted, ref } from 'vue';
import { api } from '../api.js';
import { useToast } from '../composables/useToast.js';
import { useStockOrderDraft } from '../composables/useStockOrderDraft.js';
import { useFormatter } from '../composables/useFormatter.js';

const purchaseUnitOptions = [
    { value: 'loose', label: 'バラ' },
    { value: 'tape', label: 'テープ' },
    { value: 'tray', label: 'トレー' },
    { value: 'reel', label: 'リール' },
    { value: 'box', label: '箱' },
];

// 目的: 在庫/発注管理のsetupを扱う。機能: 入力値を検証・整形し、画面または計算処理へ渡す。入力: 関数シグネチャの値。出力: 処理結果またはなし。動作条件: 在庫/発注管理の初期化後に呼び出す。副作用: Vue状態、localStorage、DOM、HTTP通信のいずれかを更新する場合がある。
export default function setup() {
    const { toasts, toastSuccess, toastError } = useToast();
    const { orderDraft, removeOrderItem, clearOrderDraft, replaceOrderDraft } = useStockOrderDraft();
    const notionExportingSupplier = ref('');

    // 発注追跡（DB記録済み発注）
    const trackedOrders = ref([]);
    const trackError = ref('');
    const trackLoading = ref(false);

    // 目的: 在庫/発注管理のfetch Tracked Ordersを扱う。機能: 入力値を検証・整形し、画面または計算処理へ渡す。入力: 関数シグネチャの値。出力: 処理結果またはなし。動作条件: 在庫/発注管理の初期化後に呼び出す。副作用: Vue状態、localStorage、DOM、HTTP通信のいずれかを更新する場合がある。
    const fetchTrackedOrders = async () => {
        trackLoading.value = true;
        trackError.value = '';
        try {
            const r = await api.get('/stock-orders?status=pending');
            trackedOrders.value = Array.isArray(r.data?.data) ? r.data.data : [];
        } catch (e) {
            trackError.value = e.message || '発注追跡データの取得に失敗しました';
        } finally {
            trackLoading.value = false;
        }
    };

    // 目的: 在庫/発注管理のupdate Order Statusを扱う。機能: 入力値を検証・整形し、画面または計算処理へ渡す。入力: 関数シグネチャの値。出力: 処理結果またはなし。動作条件: 在庫/発注管理の初期化後に呼び出す。副作用: Vue状態、localStorage、DOM、HTTP通信のいずれかを更新する場合がある。
    const updateOrderStatus = async (order, status) => {
        const labels = { received: '受取済みにしました', cancelled: 'キャンセルしました' };
        try {
            await api.patch(`/stock-orders/${order.id}`, { status });
            order.status = status;
            toastSuccess(labels[status] ?? '更新しました');
        } catch (e) {
            toastError(e.message);
        }
    };

    // 発注ドラフトをDBに保存して追跡へ移す
    // 目的: 在庫/発注管理のcommit To Trackingを扱う。機能: 入力値を検証・整形し、画面または計算処理へ渡す。入力: 関数シグネチャの値。出力: 処理結果またはなし。動作条件: 在庫/発注管理の初期化後に呼び出す。副作用: Vue状態、localStorage、DOM、HTTP通信のいずれかを更新する場合がある。
    const commitToTracking = async () => {
        const exportable = orderDraft.value.filter((item) => item.supplierId && Number(item.orderQty) > 0);
        if (!exportable.length) {
            toastError('商社・数量が入力された行がありません');
            return;
        }
        try {
            await Promise.all(exportable.map((item) => api.post('/stock-orders', {
                component_id: item.id,
                supplier_id: item.supplierId,
                quantity: Number(item.orderQty),
                status: 'pending',
                order_date: new Date().toISOString().slice(0, 10),
            })));
            toastSuccess(`${exportable.length} 件を発注記録に保存しました`);
            clearOrderDraft();
            await fetchTrackedOrders();
        } catch (e) {
            toastError(e.message);
        }
    };

    // 目的: 在庫/発注管理のpurchase Unit Labelを扱う。機能: 入力値を検証・整形し、画面または計算処理へ渡す。入力: 関数シグネチャの値。出力: 処理結果またはなし。動作条件: 在庫/発注管理の初期化後に呼び出す。副作用: Vue状態、localStorage、DOM、HTTP通信のいずれかを更新する場合がある。
    const purchaseUnitLabel = (value) => purchaseUnitOptions.find((option) => option.value === value)?.label ?? '未設定';

    const exportGroups = computed(() => {
        const grouped = {};
        orderDraft.value.forEach((item) => {
            const supplierName = item.supplierName || '未選択';
            if (!grouped[supplierName]) {
                grouped[supplierName] = { items: [], total: 0 };
            }
            grouped[supplierName].items.push(item);
            grouped[supplierName].total += Number(item.price || 0) * Number(item.orderQty || 0);
        });
        return grouped;
    });

    const grandTotal = computed(() => orderDraft.value.reduce((sum, item) => (
        sum + (Number(item.price || 0) * Number(item.orderQty || 0))
    ), 0));

    // 目的: 在庫/発注管理のsave Draftを扱う。機能: 入力値を検証・整形し、画面または計算処理へ渡す。入力: 関数シグネチャの値。出力: 処理結果またはなし。動作条件: 在庫/発注管理の初期化後に呼び出す。副作用: Vue状態、localStorage、DOM、HTTP通信のいずれかを更新する場合がある。
    const saveDraft = () => replaceOrderDraft([...orderDraft.value]);

    // 目的: 在庫/発注管理のhydrate Draft Optionsを扱う。機能: 入力値を検証・整形し、画面または計算処理へ渡す。入力: 関数シグネチャの値。出力: 処理結果またはなし。動作条件: 在庫/発注管理の初期化後に呼び出す。副作用: Vue状態、localStorage、DOM、HTTP通信のいずれかを更新する場合がある。
    const hydrateDraftOptions = async () => {
        if (!orderDraft.value.length) return;
        try {
            const response = await api.get('/stock-alerts');
            const alerts = Array.isArray(response?.data) ? response.data : [];
            const alertMap = new Map(alerts.map((alert) => [String(alert.id), alert]));

            replaceOrderDraft(orderDraft.value.map((item) => {
                const source = alertMap.get(String(item.id));
                if (!source) return item;
                return {
                    ...item,
                    packageName: source.package_name ?? item.packageName ?? '',
                    quantityNew: source.quantity_new ?? item.quantityNew ?? 0,
                    quantityUsed: source.quantity_used ?? item.quantityUsed ?? 0,
                    supplierOptions: source.supplier_options ?? item.supplierOptions ?? [],
                };
            }));
        } catch {
            toastError('発注候補の商社候補を更新できませんでした');
        }
    };

    // 目的: 在庫/発注管理のselect Supplierを扱う。機能: 入力値を検証・整形し、画面または計算処理へ渡す。入力: 関数シグネチャの値。出力: 処理結果またはなし。動作条件: 在庫/発注管理の初期化後に呼び出す。副作用: Vue状態、localStorage、DOM、HTTP通信のいずれかを更新する場合がある。
    const selectSupplier = (item) => {
        const selected = (item.supplierOptions ?? []).find((option) => String(option.supplier_id) === String(item.supplierId)) ?? null;
        item.supplierName = selected?.name ?? '';
        item.supplierPartNumber = selected?.supplier_part_number ?? '';
        item.price = Number(selected?.unit_price ?? 0);
        if (!item.purchaseUnit && selected?.purchase_unit) {
            item.purchaseUnit = selected.purchase_unit;
        }
        saveDraft();
    };

    // 目的: 在庫/発注管理のexport Supplier Csvを扱う。機能: 入力値を検証・整形し、画面または計算処理へ渡す。入力: 関数シグネチャの値。出力: 処理結果またはなし。動作条件: 在庫/発注管理の初期化後に呼び出す。副作用: Vue状態、localStorage、DOM、HTTP通信のいずれかを更新する場合がある。
    const exportSupplierCsv = (supplierName, group) => {
        const exportableItems = group.items.filter((item) => Number(item.orderQty || 0) > 0);
        const excludedCount = group.items.length - exportableItems.length;

        if (!exportableItems.length) {
            toastError(`${supplierName} は購入数量が 0 のため、CSV出力対象がありません`);
            return;
        }

        const header = '型番,通称,パッケージ,購入単位,商社,商社型番,数量,単価,小計\n';
        const rows = exportableItems.map((item) => (
            `"${item.partNumber}","${item.name && item.name !== item.partNumber ? item.name : ''}","${item.packageName ?? ''}","${purchaseUnitLabel(item.purchaseUnit)}","${item.supplierName}","${item.supplierPartNumber ?? ''}",${item.orderQty},${item.price},${Number(item.price || 0) * Number(item.orderQty || 0)}`
        )).join('\n');
        const blob = new Blob(['\uFEFF' + header + rows], { type: 'text/csv;charset=utf-8;' });
        const url = URL.createObjectURL(blob);
        const link = document.createElement('a');
        const safeName = supplierName.replace(/[\\/:*?"<>|]/g, '_');
        link.href = url;
        link.download = `発注リスト_${safeName}_${new Date().toISOString().slice(0, 10)}.csv`;
        link.click();
        URL.revokeObjectURL(url);
        if (excludedCount > 0) {
            toastSuccess(`${supplierName} のCSVを出力しました。購入数量 0 の ${excludedCount} 件は除外しました`);
            return;
        }
        toastSuccess(`${supplierName} のCSVを出力しました`);
    };

    // 目的: 在庫/発注管理のexport Supplier Notionを扱う。機能: 入力値を検証・整形し、画面または計算処理へ渡す。入力: 関数シグネチャの値。出力: 処理結果またはなし。動作条件: 在庫/発注管理の初期化後に呼び出す。副作用: Vue状態、localStorage、DOM、HTTP通信のいずれかを更新する場合がある。
    const exportSupplierNotion = async (supplierName, group) => {
        const exportableItems = group.items.filter((item) => Number(item.orderQty || 0) > 0);
        if (!exportableItems.length) {
            toastError(`${supplierName} は購入数量が 0 のため、Notion出力対象がありません`);
            return;
        }

        notionExportingSupplier.value = supplierName;
        try {
            const response = await api.post('/stock-orders/export/notion', {
                supplier_name: supplierName,
                items: exportableItems.map((item) => ({
                    name: item.name,
                    part_number: item.partNumber,
                    package_name: item.packageName ?? '',
                    supplier_part_number: item.supplierPartNumber ?? '',
                    purchase_unit_label: purchaseUnitLabel(item.purchaseUnit),
                    quantity: Number(item.orderQty || 0),
                    unit_price: Number(item.price || 0),
                    subtotal: Number(item.price || 0) * Number(item.orderQty || 0),
                })),
            });
            const pageUrl = response.data?.page_url ?? '';
            if (pageUrl) {
                window.open(pageUrl, '_blank', 'noopener');
            }
            toastSuccess(`${supplierName} をNotionへ出力しました`);
        } catch (e) {
            toastError(e.message ?? 'Notion出力に失敗しました');
        } finally {
            notionExportingSupplier.value = '';
        }
    };

    // 目的: 在庫/発注管理のremove Itemを扱う。機能: 入力値を検証・整形し、画面または計算処理へ渡す。入力: 関数シグネチャの値。出力: 処理結果またはなし。動作条件: 在庫/発注管理の初期化後に呼び出す。副作用: Vue状態、localStorage、DOM、HTTP通信のいずれかを更新する場合がある。
    const removeItem = (itemId) => removeOrderItem(itemId);
    // 目的: 在庫/発注管理のclear Allを扱う。機能: 入力値を検証・整形し、画面または計算処理へ渡す。入力: 関数シグネチャの値。出力: 処理結果またはなし。動作条件: 在庫/発注管理の初期化後に呼び出す。副作用: Vue状態、localStorage、DOM、HTTP通信のいずれかを更新する場合がある。
    const clearAll = () => {
        clearOrderDraft();
        toastSuccess('発注候補をクリアしました');
    };

    onMounted(() => { hydrateDraftOptions(); fetchTrackedOrders(); });

    return {
        toasts,
        orderDraft,
        exportGroups,
        grandTotal,
        purchaseUnitLabel,
        saveDraft,
        selectSupplier,
        purchaseUnitOptions,
        exportSupplierCsv,
        exportSupplierNotion,
        notionExportingSupplier,
        removeItem,
        clearAll,
        trackedOrders,
        trackError,
        trackLoading,
        fetchTrackedOrders,
        updateOrderStatus,
        commitToTracking,
        ...useFormatter(),
    };
}
