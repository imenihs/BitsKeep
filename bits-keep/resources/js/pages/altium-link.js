/**
 * Altium連携管理ページ（SCR-014）
 * ライブラリ一覧 + 部品リンク確認
 */
import { ref, reactive, onMounted, watch } from 'vue';
import { api } from '../api.js';
import { useToast } from '../composables/useToast.js';
import { useNavigationConfirm } from '../composables/useNavigationConfirm.js';
import { useConfirmModal } from '../composables/useConfirmModal.js';

export default function setup() {
    const { toasts, toastSuccess, toastError } = useToast();
    const { ask } = useConfirmModal();
    const libraries = ref([]);
    const fetchError = ref('');
    const dirty = ref(false);
    const snapshot = ref(null);
    useNavigationConfirm(dirty, '未保存の変更があります。このまま画面を離れてもよいですか？');

    // ライブラリモーダル
    const libModal = reactive({
        open: false, isEdit: false, editId: null,
        form: { name: '', type: 'SchLib', path: '', note: '' }
    });
    const clone = (value) => JSON.parse(JSON.stringify(value));
    const same = (a, b) => JSON.stringify(a) === JSON.stringify(b);

    const fetchLibraries = async () => {
        fetchError.value = '';
        try { const r = await api.get('/altium/libraries'); libraries.value = r.data; }
        catch (e) { fetchError.value = e.message || 'ライブラリ一覧の取得に失敗しました'; }
    };

    const openLibAdd = () => {
        const form = { name: '', type: 'SchLib', path: '', note: '' };
        snapshot.value = clone(form);
        Object.assign(libModal, { open: true, isEdit: false, editId: null, form });
    };
    const openLibEdit = (l) => {
        const form = { name: l.name, type: l.type, path: l.path, note: l.note ?? '' };
        snapshot.value = clone(form);
        Object.assign(libModal, { open: true, isEdit: true, editId: l.id, form });
    };
    const closeLibModal = async () => {
        if (libModal.open && !same(libModal.form, snapshot.value) && !await ask('未保存の変更があります。閉じてもよいですか？')) return;
        libModal.open = false;
    };

    const saveLib = async () => {
        try {
            if (libModal.isEdit) await api.put(`/altium/libraries/${libModal.editId}`, libModal.form);
            else await api.post('/altium/libraries', libModal.form);
            toastSuccess('保存しました'); libModal.open = false; snapshot.value = clone(libModal.form); dirty.value = false; await fetchLibraries();
        } catch (e) { toastError(e.message); }
    };

    const deleteLib = async (l) => {
        if (!await ask(`「${l.name}」を削除しますか？\n紐づいている部品のリンクがNULLになります。`)) return;
        try { await api.delete(`/altium/libraries/${l.id}`); await fetchLibraries(); toastSuccess('削除しました'); }
        catch (e) { toastError(e.message); }
    };

    onMounted(fetchLibraries);
    watch(() => libModal.form, (value) => {
        if (libModal.open) dirty.value = !same(value, snapshot.value);
    }, { deep: true });
    watch(() => libModal.open, (isOpen) => {
        if (!isOpen) dirty.value = false;
    });
    return { toasts, libraries, fetchError, fetchLibraries, libModal, openLibAdd, openLibEdit, closeLibModal, saveLib, deleteLib };
}
