import { ref, reactive, onMounted } from 'vue';
import { api } from '../api.js';
import { useToast } from '../composables/useToast.js';

export default function setup() {
    const { toasts, toastSuccess, toastError } = useToast();
    const suppliers = ref([]);
    const modal = reactive({ open: false, isEdit: false, editId: null,
        form: { name: '', url: '', color: '#2563eb', lead_days: '', free_shipping_threshold: '', note: '' } });

    const fetchSuppliers = async () => {
        try { const r = await api.get('/suppliers'); suppliers.value = r.data; }
        catch { toastError('商社情報の取得に失敗しました'); }
    };

    const openAdd = () => { Object.assign(modal, { open: true, isEdit: false, editId: null,
        form: { name: '', url: '', color: '#2563eb', lead_days: '', free_shipping_threshold: '', note: '' } }); };
    const openEdit = (s) => { Object.assign(modal, { open: true, isEdit: true, editId: s.id,
        form: { name: s.name, url: s.url ?? '', color: s.color ?? '#2563eb', lead_days: s.lead_days ?? '', free_shipping_threshold: s.free_shipping_threshold ?? '', note: s.note ?? '' } }); };

    const save = async () => {
        try {
            if (modal.isEdit) await api.put(`/suppliers/${modal.editId}`, modal.form);
            else await api.post('/suppliers', modal.form);
            toastSuccess('保存しました'); modal.open = false; await fetchSuppliers();
        } catch (e) { toastError(e.message); }
    };

    const deleteSupplier = async (s) => {
        if (!confirm(`「${s.name}」を削除しますか？`)) return;
        try { await api.delete(`/suppliers/${s.id}`); await fetchSuppliers(); toastSuccess('削除しました'); }
        catch (e) { toastError(e.message); }
    };

    onMounted(fetchSuppliers);
    return { toasts, suppliers, modal, openAdd, openEdit, save, deleteSupplier };
}
