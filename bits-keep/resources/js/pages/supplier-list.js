import { ref, reactive, onMounted, watch } from 'vue';
import { api } from '../api.js';
import { useToast } from '../composables/useToast.js';
import { useNavigationConfirm } from '../composables/useNavigationConfirm.js';
import { useFormatter } from '../composables/useFormatter.js';

const COLOR_PALETTE = [
    '#ef4444', '#f97316', '#eab308', '#22c55e',
    '#06b6d4', '#2563eb', '#7c3aed', '#ec4899',
    '#6b7280', '#64748b', '#78716c', '#8b5cf6',
    '#14b8a6', '#3b82f6', '#f59e0b', '#6366f1',
];

export default function setup() {
    const { toasts, toastSuccess, toastError } = useToast();
    const { formatCurrency } = useFormatter();
    const suppliers = ref([]);
    const fetchError = ref('');
    const dirty = ref(false);
    const snapshot = ref(null);
    useNavigationConfirm(dirty, '未保存の変更があります。このまま画面を離れてもよいですか？');
    const modal = reactive({ open: false, isEdit: false, editId: null,
        form: { name: '', url: '', color: '#2563eb', lead_days: '', free_shipping_threshold: '', note: '' },
        showCustomColor: false });
    const clone = (value) => JSON.parse(JSON.stringify(value));
    const same = (a, b) => JSON.stringify(a) === JSON.stringify(b);

    // Determine if custom color should be shown (not in palette)
    const isCustomColor = (color) => !COLOR_PALETTE.includes(color?.toLowerCase());

    // Check contrast ratio (simple luminance-based check)
    const getContrast = (hexColor) => {
        if (!hexColor) return 1;
        const rgb = parseInt(hexColor.slice(1), 16);
        const r = (rgb >> 16) & 255, g = (rgb >> 8) & 255, b = rgb & 255;
        const luminance = (0.299 * r + 0.587 * g + 0.114 * b) / 255;
        return luminance > 0.7 ? 'light' : 'dark';
    };
    const hasLowContrast = (color) => {
        const c = getContrast(color);
        return c === 'light'; // warn if too bright
    };

    const fetchSuppliers = async () => {
        fetchError.value = '';
        try { const r = await api.get('/suppliers?include_archived=1'); suppliers.value = r.data; }
        catch { fetchError.value = '商社情報の取得に失敗しました。再試行するか、しばらく待ってから再読み込みしてください。'; toastError('商社情報の取得に失敗しました'); }
    };

    const openAdd = () => {
        const form = { name: '', url: '', color: '#2563eb', lead_days: '', free_shipping_threshold: '', note: '' };
        snapshot.value = clone(form);
        Object.assign(modal, { open: true, isEdit: false, editId: null, form });
    };
    const openEdit = (s) => {
        const form = { name: s.name, url: s.url ?? '', color: s.color ?? '#2563eb', lead_days: s.lead_days ?? '', free_shipping_threshold: s.free_shipping_threshold ?? '', note: s.note ?? '' };
        snapshot.value = clone(form);
        Object.assign(modal, { open: true, isEdit: true, editId: s.id, form });
    };
    const closeModal = () => {
        if (modal.open && !same(modal.form, snapshot.value) && !confirm('未保存の変更があります。閉じてもよいですか？')) return;
        modal.open = false;
    };

    const save = async () => {
        try {
            if (modal.isEdit) await api.put(`/suppliers/${modal.editId}`, modal.form);
            else await api.post('/suppliers', modal.form);
            toastSuccess('保存しました'); modal.open = false; snapshot.value = clone(modal.form); dirty.value = false; await fetchSuppliers();
        } catch (e) { toastError(e.message); }
    };

    const archiveSupplier = async (s) => {
        if (!confirm(`「${s.name}」を取引停止にしますか？\n使用件数: ${s.usage_count ?? 0}件`)) return;
        try { await api.delete(`/suppliers/${s.id}`); await fetchSuppliers(); toastSuccess('取引停止にしました'); }
        catch (e) { toastError(e.message); }
    };
    const restoreSupplier = async (s) => {
        if (!confirm(`「${s.name}」を復元しますか？`)) return;
        try { await api.post(`/suppliers/${s.id}/restore`); await fetchSuppliers(); toastSuccess('復元しました'); }
        catch (e) { toastError(e.message); }
    };
    const forceDeleteSupplier = async (s) => {
        if (!confirm(`「${s.name}」を完全削除しますか？\nこの操作は元に戻せません。`)) return;
        try { await api.delete(`/suppliers/${s.id}/force`); await fetchSuppliers(); toastSuccess('完全削除しました'); }
        catch (e) { toastError(e.message); }
    };

    onMounted(fetchSuppliers);
    watch(() => modal.form, (value) => {
        if (modal.open) dirty.value = !same(value, snapshot.value);
    }, { deep: true });
    watch(() => modal.open, (isOpen) => {
        if (!isOpen) dirty.value = false;
    });

    return { toasts, suppliers, fetchError, modal, openAdd, openEdit, closeModal, save, archiveSupplier, restoreSupplier, forceDeleteSupplier, fetchSuppliers, formatCurrency, COLOR_PALETTE, isCustomColor, hasLowContrast };
}
