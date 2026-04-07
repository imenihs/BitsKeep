import { ref, computed, onMounted } from 'vue';
import { api } from '../api.js';
import { useToast } from '../composables/useToast.js';

export default function setup() {
    const { toasts, toastSuccess, toastError } = useToast();

    // Blade側から data-id 属性で部品IDを受け取る
    const componentId = document.getElementById('app')?.dataset?.id;

    const part       = ref(null);
    const loading    = ref(true);

    // 編集モーダル
    const editModal  = ref({ open: false, section: '', title: '', form: {} });
    // 出庫モーダル
    const stockOutModal = ref({ open: false, blockId: null, maxQty: 0, qty: 1, projectId: '', note: '' });
    // 入庫モーダル（在庫追加）
    const stockInModal  = ref({ open: false, form: { stock_type: 'loose', condition: 'new', quantity: 1, lot_number: '', reel_code: '', location_id: '', note: '' } });

    const stockTypeLabel = { reel: 'リール', tape: 'テープ', tray: 'トレイ', loose: 'バラ', box: '箱' };
    const procurementOptions = [
        { value: 'active', label: '量産中' }, { value: 'eol', label: 'EOL' },
        { value: 'last_time', label: '在庫限り' }, { value: 'nrnd', label: '新規非推奨' },
    ];

    const fetchPart = async () => {
        loading.value = true;
        try {
            const res = await api.get(`/components/${componentId}`);
            part.value = res.data;
        } catch {
            toastError('部品情報の取得に失敗しました');
        } finally {
            loading.value = false;
        }
    };

    // セクション別編集モーダルを開く
    const openEdit = (section) => {
        const p = part.value;
        const forms = {
            basic: {
                title: '基本情報を編集',
                form: {
                    part_number: p.part_number, manufacturer: p.manufacturer ?? '',
                    common_name: p.common_name ?? '', description: p.description ?? '',
                    procurement_status: p.procurement_status,
                    threshold_new: p.threshold_new, threshold_used: p.threshold_used,
                    category_ids: p.categories.map(c => c.id),
                    package_ids:  p.packages.map(pk => pk.id),
                },
            },
            specs: {
                title: 'スペックを編集',
                form: { specs: p.specs.map(s => ({ ...s })) },
            },
            suppliers: {
                title: '仕入先情報を編集',
                form: { suppliers: p.component_suppliers.map(cs => ({ ...cs, price_breaks: [...(cs.price_breaks ?? [])] })) },
            },
        };
        editModal.value = { open: true, section, ...forms[section] };
    };

    // セクション保存（PATCH）
    const saveSection = async () => {
        try {
            await api.patch(`/components/${componentId}/${editModal.value.section}`, editModal.value.form);
            toastSuccess('保存しました');
            editModal.value.open = false;
            await fetchPart();
        } catch (e) {
            toastError(e.message);
        }
    };

    // 全体編集（PUT） — 基本情報フォームを流用
    const saveAll = async () => {
        try {
            await api.put(`/components/${componentId}`, editModal.value.form);
            toastSuccess('保存しました');
            editModal.value.open = false;
            await fetchPart();
        } catch (e) {
            toastError(e.message);
        }
    };

    // 出庫
    const openStockOut = (block) => {
        stockOutModal.value = { open: true, blockId: block.id, maxQty: block.quantity, qty: 1, projectId: '', note: '' };
    };
    const submitStockOut = async () => {
        try {
            await api.post(`/components/${componentId}/stock-out`, {
                inventory_block_id: stockOutModal.value.blockId,
                quantity: stockOutModal.value.qty,
                project_id: stockOutModal.value.projectId || null,
                note: stockOutModal.value.note,
            });
            toastSuccess('出庫しました');
            stockOutModal.value.open = false;
            await fetchPart();
        } catch (e) {
            toastError(e.message);
        }
    };

    // 入庫
    const submitStockIn = async () => {
        try {
            await api.post(`/components/${componentId}/stock-in`, stockInModal.value.form);
            toastSuccess('入庫しました');
            stockInModal.value.open = false;
            await fetchPart();
        } catch (e) {
            toastError(e.message);
        }
    };

    // 類似部品
    const similarParts = ref([]);
    const similarLoading = ref(false);
    const fetchSimilar = async () => {
        if (similarLoading.value) return;
        similarLoading.value = true;
        try {
            const r = await api.get(`/components/${componentId}/similar`);
            similarParts.value = r.data;
        } catch { /* 無視 */ }
        finally { similarLoading.value = false; }
    };

    // 論理削除
    const deletePart = async () => {
        if (!confirm('この部品を削除しますか？')) return;
        try {
            await api.delete(`/components/${componentId}`);
            toastSuccess('削除しました');
            setTimeout(() => { location.href = '/components'; }, 1000);
        } catch (e) {
            toastError(e.message);
        }
    };

    onMounted(fetchPart);

    return {
        toasts, part, loading, componentId,
        stockTypeLabel, procurementOptions,
        editModal, openEdit, saveSection, saveAll,
        stockOutModal, openStockOut, submitStockOut,
        stockInModal, submitStockIn,
        deletePart,
        similarParts, similarLoading, fetchSimilar,
    };
}
