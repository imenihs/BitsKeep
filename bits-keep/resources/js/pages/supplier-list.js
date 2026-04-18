import { ref, reactive, onMounted, watch } from 'vue';
import { api } from '../api.js';
import { useToast } from '../composables/useToast.js';
import { useNavigationConfirm } from '../composables/useNavigationConfirm.js';
import { useFormatter } from '../composables/useFormatter.js';

export default function setup() {
    const { toasts, toastSuccess, toastError } = useToast();
    const { formatCurrency } = useFormatter();
    const suppliers = ref([]);
    const dirty = ref(false);
    const snapshot = ref(null);
    useNavigationConfirm(dirty, '未保存の変更があります。このまま画面を離れてもよいですか？');
    const modal = reactive({ open: false, isEdit: false, editId: null,
        form: { name: '', url: '', color: '#2563eb', lead_days: '', free_shipping_threshold: '', note: '' } });
    const clone = (value) => JSON.parse(JSON.stringify(value));
    const same = (a, b) => JSON.stringify(a) === JSON.stringify(b);

    const fetchSuppliers = async () => {
        try { const r = await api.get('/suppliers?include_archived=1'); suppliers.value = r.data; }
        catch { toastError('商社情報の取得に失敗しました'); }
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

    return { toasts, suppliers, modal, openAdd, openEdit, closeModal, save, archiveSupplier, restoreSupplier, forceDeleteSupplier, formatCurrency };
}
