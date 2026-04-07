/**
 * Altium連携管理ページ（SCR-014）
 * ライブラリ一覧 + 部品リンク確認
 */
import { ref, reactive, onMounted } from 'vue';
import { api } from '../api.js';
import { useToast } from '../composables/useToast.js';

export default function setup() {
    const { toasts, toastSuccess, toastError } = useToast();
    const libraries = ref([]);

    // ライブラリモーダル
    const libModal = reactive({
        open: false, isEdit: false, editId: null,
        form: { name: '', type: 'SchLib', path: '', note: '' }
    });

    const fetchLibraries = async () => {
        try { const r = await api.get('/altium/libraries'); libraries.value = r.data; }
        catch { toastError('ライブラリ一覧の取得に失敗しました'); }
    };

    const openLibAdd = () => Object.assign(libModal, {
        open: true, isEdit: false, editId: null,
        form: { name: '', type: 'SchLib', path: '', note: '' }
    });
    const openLibEdit = (l) => Object.assign(libModal, {
        open: true, isEdit: true, editId: l.id,
        form: { name: l.name, type: l.type, path: l.path, note: l.note ?? '' }
    });

    const saveLib = async () => {
        try {
            if (libModal.isEdit) await api.put(`/altium/libraries/${libModal.editId}`, libModal.form);
            else await api.post('/altium/libraries', libModal.form);
            toastSuccess('保存しました'); libModal.open = false; await fetchLibraries();
        } catch (e) { toastError(e.message); }
    };

    const deleteLib = async (l) => {
        if (!confirm(`「${l.name}」を削除しますか？\n紐づいている部品のリンクがNULLになります。`)) return;
        try { await api.delete(`/altium/libraries/${l.id}`); await fetchLibraries(); toastSuccess('削除しました'); }
        catch (e) { toastError(e.message); }
    };

    onMounted(fetchLibraries);
    return { toasts, libraries, libModal, openLibAdd, openLibEdit, saveLib, deleteLib };
}
