/**
 * マスタ管理ページ（SCR-009）
 * 分類 / パッケージ / スペック種別 の CRUD
 * data-tab="categories|packages|spec-types" で初期タブを切り替え可
 */
import { ref, reactive, computed, onMounted } from 'vue';
import { api } from '../api.js';
import { useToast } from '../composables/useToast.js';

export default function setup() {
    const { toasts, toastSuccess, toastError } = useToast();

    // ── タブ ──────────────────────────────────────────────
    const appEl  = document.getElementById('app');
    const activeTab = ref(appEl?.dataset?.tab ?? 'categories');

    // ── 分類 ─────────────────────────────────────────────
    const categories = ref([]);
    const catModal = reactive({
        open: false, isEdit: false, editId: null,
        form: { name: '', description: '', sort_order: 0 }
    });

    const fetchCategories = async () => {
        try { const r = await api.get('/categories'); categories.value = r.data; }
        catch { toastError('分類の取得に失敗しました'); }
    };

    const openCatAdd = () => Object.assign(catModal, { open: true, isEdit: false, editId: null,
        form: { name: '', description: '', sort_order: 0 } });
    const openCatEdit = (c) => Object.assign(catModal, { open: true, isEdit: true, editId: c.id,
        form: { name: c.name, description: c.description ?? '', sort_order: c.sort_order ?? 0 } });

    const saveCategory = async () => {
        try {
            if (catModal.isEdit) await api.put(`/categories/${catModal.editId}`, catModal.form);
            else await api.post('/categories', catModal.form);
            toastSuccess('保存しました'); catModal.open = false; await fetchCategories();
        } catch (e) { toastError(e.message); }
    };

    const deleteCategory = async (c) => {
        if (!confirm(`「${c.name}」を削除しますか？`)) return;
        try { await api.delete(`/categories/${c.id}`); await fetchCategories(); toastSuccess('削除しました'); }
        catch (e) { toastError(e.message); }
    };

    // ── パッケージ ────────────────────────────────────────
    const packages = ref([]);
    const pkgModal = reactive({
        open: false, isEdit: false, editId: null,
        form: { name: '', description: '', sort_order: 0 }
    });

    const fetchPackages = async () => {
        try { const r = await api.get('/packages'); packages.value = r.data; }
        catch { toastError('パッケージの取得に失敗しました'); }
    };

    const openPkgAdd = () => Object.assign(pkgModal, { open: true, isEdit: false, editId: null,
        form: { name: '', description: '', sort_order: 0 } });
    const openPkgEdit = (p) => Object.assign(pkgModal, { open: true, isEdit: true, editId: p.id,
        form: { name: p.name, description: p.description ?? '', sort_order: p.sort_order ?? 0 } });

    const savePackage = async () => {
        try {
            if (pkgModal.isEdit) await api.put(`/packages/${pkgModal.editId}`, pkgModal.form);
            else await api.post('/packages', pkgModal.form);
            toastSuccess('保存しました'); pkgModal.open = false; await fetchPackages();
        } catch (e) { toastError(e.message); }
    };

    const deletePackage = async (p) => {
        if (!confirm(`「${p.name}」を削除しますか？`)) return;
        try { await api.delete(`/packages/${p.id}`); await fetchPackages(); toastSuccess('削除しました'); }
        catch (e) { toastError(e.message); }
    };

    // ── スペック種別 ──────────────────────────────────────
    const specTypes = ref([]);
    const stModal = reactive({
        open: false, isEdit: false, editId: null,
        form: { name: '', description: '', value_type: 'numeric', sort_order: 0, units: [] }
    });

    const fetchSpecTypes = async () => {
        try { const r = await api.get('/spec-types'); specTypes.value = r.data; }
        catch { toastError('スペック種別の取得に失敗しました'); }
    };

    const openStAdd = () => Object.assign(stModal, { open: true, isEdit: false, editId: null,
        form: { name: '', description: '', value_type: 'numeric', sort_order: 0, units: [] } });
    const openStEdit = (s) => Object.assign(stModal, { open: true, isEdit: true, editId: s.id,
        form: {
            name: s.name, description: s.description ?? '',
            value_type: s.value_type ?? 'numeric',
            sort_order: s.sort_order ?? 0,
            units: (s.units ?? []).map(u => ({ unit: u.unit, factor: u.factor, sort_order: u.sort_order }))
        } });

    // 単位の追加/削除
    const addUnit = () => stModal.form.units.push({ unit: '', factor: 1, sort_order: stModal.form.units.length });
    const removeUnit = (i) => stModal.form.units.splice(i, 1);

    const saveSpecType = async () => {
        try {
            const payload = { ...stModal.form };
            if (stModal.isEdit) await api.put(`/spec-types/${stModal.editId}`, payload);
            else await api.post('/spec-types', payload);
            toastSuccess('保存しました'); stModal.open = false; await fetchSpecTypes();
        } catch (e) { toastError(e.message); }
    };

    const deleteSpecType = async (s) => {
        if (!confirm(`「${s.name}」を削除しますか？`)) return;
        try { await api.delete(`/spec-types/${s.id}`); await fetchSpecTypes(); toastSuccess('削除しました'); }
        catch (e) { toastError(e.message); }
    };

    // ── タブ切り替え時のフェッチ ──────────────────────────
    const switchTab = (tab) => {
        activeTab.value = tab;
        if (tab === 'categories' && categories.value.length === 0) fetchCategories();
        else if (tab === 'packages' && packages.value.length === 0) fetchPackages();
        else if (tab === 'spec-types' && specTypes.value.length === 0) fetchSpecTypes();
    };

    onMounted(() => {
        // 初期タブのデータだけ取得
        switchTab(activeTab.value);
    });

    return {
        toasts, activeTab, switchTab,
        // 分類
        categories, catModal, openCatAdd, openCatEdit, saveCategory, deleteCategory,
        // パッケージ
        packages, pkgModal, openPkgAdd, openPkgEdit, savePackage, deletePackage,
        // スペック種別
        specTypes, stModal, openStAdd, openStEdit, saveSpecType, deleteSpecType, addUnit, removeUnit,
    };
}
