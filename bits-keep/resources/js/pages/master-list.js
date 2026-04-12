/**
 * マスタ管理ページ（SCR-009）
 * 分類 / パッケージ分類 / パッケージ / スペック種別 の CRUD
 * data-tab="categories|package-groups|packages|spec-types" で初期タブを切り替え可
 */
import { ref, reactive, computed, onMounted, watch } from 'vue';
import { api } from '../api.js';
import { useToast } from '../composables/useToast.js';
import { useNavigationConfirm } from '../composables/useNavigationConfirm.js';

export default function setup() {
    const { toasts, toastSuccess, toastError } = useToast();
    const dirty = ref(false);
    useNavigationConfirm(dirty, '未保存の変更があります。このまま画面を離れてもよいですか？');

    // ── タブ ──────────────────────────────────────────────
    const appEl  = document.getElementById('app');
    const activeTab = ref(appEl?.dataset?.tab ?? 'categories');
    const canEdit = appEl?.dataset?.canEdit === '1';
    const isAdmin = appEl?.dataset?.isAdmin === '1';

    const clone = (value) => JSON.parse(JSON.stringify(value));
    const same = (a, b) => JSON.stringify(a) === JSON.stringify(b);
    const closeModalWithConfirm = (modal, snapshot) => {
        if (modal.open && !same(modal.form, snapshot) && !confirm('未保存の変更があります。閉じてもよいですか？')) return;
        modal.open = false;
    };

    // ── 分類 ─────────────────────────────────────────────
    const categories = ref([]);
    const catSnapshot = ref(null);
    const catModal = reactive({
        open: false, isEdit: false, editId: null,
        form: { name: '', description: '', sort_order: 0 }
    });

    const fetchCategories = async () => {
        try { const r = await api.get('/categories?include_archived=1'); categories.value = r.data; }
        catch { toastError('分類の取得に失敗しました'); }
    };

    const openCatAdd = () => {
        const form = { name: '', description: '', sort_order: (categories.value.at(-1)?.sort_order ?? 0) + 10 };
        catSnapshot.value = clone(form);
        Object.assign(catModal, { open: true, isEdit: false, editId: null, form });
    };
    const openCatEdit = (c) => {
        const form = { name: c.name, description: c.description ?? '', sort_order: c.sort_order ?? 0 };
        catSnapshot.value = clone(form);
        Object.assign(catModal, { open: true, isEdit: true, editId: c.id, form });
    };

    const saveCategory = async () => {
        try {
            if (catModal.isEdit) await api.put(`/categories/${catModal.editId}`, catModal.form);
            else await api.post('/categories', catModal.form);
            toastSuccess('保存しました'); catModal.open = false; catSnapshot.value = clone(catModal.form); await fetchCategories();
        } catch (e) { toastError(e.message); }
    };
    const closeCatModal = () => closeModalWithConfirm(catModal, catSnapshot.value);

    const archiveCategory = async (c) => {
        if (!confirm(`「${c.name}」をアーカイブしますか？\n使用件数: ${c.usage_count ?? 0}件`)) return;
        try { await api.delete(`/categories/${c.id}`); await fetchCategories(); toastSuccess('アーカイブしました'); }
        catch (e) { toastError(e.message); }
    };
    const restoreCategory = async (c) => {
        if (!confirm(`「${c.name}」を復元しますか？`)) return;
        try { await api.post(`/categories/${c.id}/restore`); await fetchCategories(); toastSuccess('復元しました'); }
        catch (e) { toastError(e.message); }
    };
    const forceDeleteCategory = async (c) => {
        if (!confirm(`「${c.name}」を完全削除しますか？\nこの操作は元に戻せません。`)) return;
        try { await api.delete(`/categories/${c.id}/force`); await fetchCategories(); toastSuccess('完全削除しました'); }
        catch (e) { toastError(e.message); }
    };
    const moveCategory = async (index, delta) => {
        const target = index + delta;
        if (target < 0 || target >= categories.value.length) return;
        const ordered = [...categories.value];
        [ordered[index], ordered[target]] = [ordered[target], ordered[index]];
        try {
            await Promise.all(ordered.map((item, idx) => api.put(`/categories/${item.id}`, {
                name: item.name,
                color: item.color ?? null,
                sort_order: (idx + 1) * 10,
            })));
            toastSuccess('並び順を更新しました');
            await fetchCategories();
        } catch (e) { toastError(e.message); }
    };

    // ── パッケージ ────────────────────────────────────────
    const packageGroups = ref([]);
    const pkgGroupSnapshot = ref(null);
    const pkgGroupModal = reactive({
        open: false, isEdit: false, editId: null,
        form: { name: '', description: '', sort_order: 0 }
    });

    const fetchPackageGroups = async () => {
        try { const r = await api.get('/package-groups?include_archived=1'); packageGroups.value = r.data; }
        catch { toastError('パッケージ分類の取得に失敗しました'); }
    };

    const openPkgGroupAdd = () => {
        const form = { name: '', description: '', sort_order: (packageGroups.value.at(-1)?.sort_order ?? 0) + 10 };
        pkgGroupSnapshot.value = clone(form);
        Object.assign(pkgGroupModal, { open: true, isEdit: false, editId: null, form });
    };
    const openPkgGroupEdit = (group) => {
        const form = { name: group.name, description: group.description ?? '', sort_order: group.sort_order ?? 0 };
        pkgGroupSnapshot.value = clone(form);
        Object.assign(pkgGroupModal, { open: true, isEdit: true, editId: group.id, form });
    };

    const savePackageGroup = async () => {
        try {
            if (pkgGroupModal.isEdit) await api.put(`/package-groups/${pkgGroupModal.editId}`, pkgGroupModal.form);
            else await api.post('/package-groups', pkgGroupModal.form);
            toastSuccess('保存しました'); pkgGroupModal.open = false; pkgGroupSnapshot.value = clone(pkgGroupModal.form); await fetchPackageGroups();
        } catch (e) { toastError(e.message); }
    };
    const closePkgGroupModal = () => closeModalWithConfirm(pkgGroupModal, pkgGroupSnapshot.value);

    const archivePackageGroup = async (group) => {
        if (!confirm(`「${group.name}」をアーカイブしますか？\n使用件数: ${group.usage_count ?? 0}件`)) return;
        try { await api.delete(`/package-groups/${group.id}`); await fetchPackageGroups(); toastSuccess('アーカイブしました'); }
        catch (e) { toastError(e.message); }
    };
    const restorePackageGroup = async (group) => {
        if (!confirm(`「${group.name}」を復元しますか？`)) return;
        try { await api.post(`/package-groups/${group.id}/restore`); await fetchPackageGroups(); toastSuccess('復元しました'); }
        catch (e) { toastError(e.message); }
    };
    const forceDeletePackageGroup = async (group) => {
        if (!confirm(`「${group.name}」を完全削除しますか？\nこの操作は元に戻せません。`)) return;
        try { await api.delete(`/package-groups/${group.id}/force`); await fetchPackageGroups(); toastSuccess('完全削除しました'); }
        catch (e) { toastError(e.message); }
    };
    const movePackageGroup = async (index, delta) => {
        const target = index + delta;
        if (target < 0 || target >= packageGroups.value.length) return;
        const ordered = [...packageGroups.value];
        [ordered[index], ordered[target]] = [ordered[target], ordered[index]];
        try {
            await Promise.all(ordered.map((item, idx) => api.put(`/package-groups/${item.id}`, {
                name: item.name,
                description: item.description ?? '',
                sort_order: (idx + 1) * 10,
            })));
            toastSuccess('並び順を更新しました');
            await fetchPackageGroups();
        } catch (e) { toastError(e.message); }
    };

    const packages = ref([]);
    const pkgSnapshot = ref(null);
    const pkgModal = reactive({
        open: false, isEdit: false, editId: null,
        form: { package_group_id: '', name: '', description: '', sort_order: 0 }
    });

    const fetchPackages = async () => {
        try { const r = await api.get('/packages?include_archived=1'); packages.value = r.data; }
        catch { toastError('パッケージの取得に失敗しました'); }
    };

    const openPkgAdd = () => {
        const form = { package_group_id: '', name: '', description: '', sort_order: (packages.value.at(-1)?.sort_order ?? 0) + 10 };
        pkgSnapshot.value = clone(form);
        Object.assign(pkgModal, { open: true, isEdit: false, editId: null, form });
    };
    const openPkgEdit = (p) => {
        const form = { package_group_id: p.package_group_id ?? '', name: p.name, description: p.description ?? '', sort_order: p.sort_order ?? 0 };
        pkgSnapshot.value = clone(form);
        Object.assign(pkgModal, { open: true, isEdit: true, editId: p.id, form });
    };

    const savePackage = async () => {
        try {
            if (pkgModal.isEdit) await api.put(`/packages/${pkgModal.editId}`, pkgModal.form);
            else await api.post('/packages', pkgModal.form);
            toastSuccess('保存しました'); pkgModal.open = false; pkgSnapshot.value = clone(pkgModal.form); await fetchPackages();
        } catch (e) { toastError(e.message); }
    };
    const closePkgModal = () => closeModalWithConfirm(pkgModal, pkgSnapshot.value);

    const archivePackage = async (p) => {
        if (!confirm(`「${p.name}」をアーカイブしますか？\n使用件数: ${p.usage_count ?? 0}件`)) return;
        try { await api.delete(`/packages/${p.id}`); await fetchPackages(); toastSuccess('アーカイブしました'); }
        catch (e) { toastError(e.message); }
    };
    const restorePackage = async (p) => {
        if (!confirm(`「${p.name}」を復元しますか？`)) return;
        try { await api.post(`/packages/${p.id}/restore`); await fetchPackages(); toastSuccess('復元しました'); }
        catch (e) { toastError(e.message); }
    };
    const forceDeletePackage = async (p) => {
        if (!confirm(`「${p.name}」を完全削除しますか？\nこの操作は元に戻せません。`)) return;
        try { await api.delete(`/packages/${p.id}/force`); await fetchPackages(); toastSuccess('完全削除しました'); }
        catch (e) { toastError(e.message); }
    };
    const movePackage = async (index, delta) => {
        const target = index + delta;
        if (target < 0 || target >= packages.value.length) return;
        const ordered = [...packages.value];
        [ordered[index], ordered[target]] = [ordered[target], ordered[index]];
        try {
            await Promise.all(ordered.map((item, idx) => api.put(`/packages/${item.id}`, {
                name: item.name,
                description: item.description ?? '',
                sort_order: (idx + 1) * 10,
            })));
            toastSuccess('並び順を更新しました');
            await fetchPackages();
        } catch (e) { toastError(e.message); }
    };

    // ── スペック種別 ──────────────────────────────────────
    const specTypes = ref([]);
    const stSnapshot = ref(null);
    const stModal = reactive({
        open: false, isEdit: false, editId: null,
        form: { name: '', description: '', value_type: 'numeric', sort_order: 0, unit: '' }
    });

    const fetchSpecTypes = async () => {
        try { const r = await api.get('/spec-types?include_archived=1'); specTypes.value = r.data; }
        catch { toastError('スペック種別の取得に失敗しました'); }
    };

    const openStAdd = () => {
        const form = { name: '', description: '', value_type: 'numeric', sort_order: (specTypes.value.at(-1)?.sort_order ?? 0) + 10, unit: '' };
        stSnapshot.value = clone(form);
        Object.assign(stModal, { open: true, isEdit: false, editId: null, form });
    };
    const openStEdit = (s) => {
        const form = {
            name: s.name, description: s.description ?? '',
            value_type: s.value_type ?? 'numeric',
            sort_order: s.sort_order ?? 0,
            unit: s.units?.[0]?.unit ?? '',
        };
        stSnapshot.value = clone(form);
        Object.assign(stModal, { open: true, isEdit: true, editId: s.id, form });
    };

    const saveSpecType = async () => {
        try {
            const payload = { ...stModal.form };
            if (stModal.isEdit) await api.put(`/spec-types/${stModal.editId}`, payload);
            else await api.post('/spec-types', payload);
            toastSuccess('保存しました'); stModal.open = false; stSnapshot.value = clone(stModal.form); await fetchSpecTypes();
        } catch (e) { toastError(e.message); }
    };
    const closeStModal = () => closeModalWithConfirm(stModal, stSnapshot.value);

    const archiveSpecType = async (s) => {
        if (!confirm(`「${s.name}」をアーカイブしますか？\n使用件数: ${s.usage_count ?? 0}件`)) return;
        try { await api.delete(`/spec-types/${s.id}`); await fetchSpecTypes(); toastSuccess('アーカイブしました'); }
        catch (e) { toastError(e.message); }
    };
    const restoreSpecType = async (s) => {
        if (!confirm(`「${s.name}」を復元しますか？`)) return;
        try { await api.post(`/spec-types/${s.id}/restore`); await fetchSpecTypes(); toastSuccess('復元しました'); }
        catch (e) { toastError(e.message); }
    };
    const forceDeleteSpecType = async (s) => {
        if (!confirm(`「${s.name}」を完全削除しますか？\nこの操作は元に戻せません。`)) return;
        try { await api.delete(`/spec-types/${s.id}/force`); await fetchSpecTypes(); toastSuccess('完全削除しました'); }
        catch (e) { toastError(e.message); }
    };
    const moveSpecType = async (index, delta) => {
        const target = index + delta;
        if (target < 0 || target >= specTypes.value.length) return;
        const ordered = [...specTypes.value];
        [ordered[index], ordered[target]] = [ordered[target], ordered[index]];
        try {
            await Promise.all(ordered.map((item, idx) => api.put(`/spec-types/${item.id}`, {
                name: item.name,
                description: item.description ?? '',
                value_type: item.value_type ?? 'numeric',
                unit: item.units?.[0]?.unit ?? '',
                sort_order: (idx + 1) * 10,
            })));
            toastSuccess('並び順を更新しました');
            await fetchSpecTypes();
        } catch (e) { toastError(e.message); }
    };

    // ── タブ切り替え時のフェッチ ──────────────────────────
    const switchTab = (tab) => {
        activeTab.value = tab;
        if (tab === 'categories' && categories.value.length === 0) fetchCategories();
        else if (tab === 'package-groups' && packageGroups.value.length === 0) fetchPackageGroups();
        else if (tab === 'packages') {
            if (packageGroups.value.length === 0) fetchPackageGroups();
            if (packages.value.length === 0) fetchPackages();
        }
        else if (tab === 'spec-types' && specTypes.value.length === 0) fetchSpecTypes();
    };

    onMounted(() => {
        // 初期タブのデータだけ取得
        switchTab(activeTab.value);
    });

    watch(() => catModal.form, (value) => {
        if (catModal.open) dirty.value = !same(value, catSnapshot.value);
    }, { deep: true });
    watch(() => pkgModal.form, (value) => {
        if (pkgModal.open) dirty.value = !same(value, pkgSnapshot.value);
    }, { deep: true });
    watch(() => pkgGroupModal.form, (value) => {
        if (pkgGroupModal.open) dirty.value = !same(value, pkgGroupSnapshot.value);
    }, { deep: true });
    watch(() => stModal.form, (value) => {
        if (stModal.open) dirty.value = !same(value, stSnapshot.value);
    }, { deep: true });
    watch([() => catModal.open, () => pkgGroupModal.open, () => pkgModal.open, () => stModal.open], ([catOpen, groupOpen, pkgOpen, stOpen]) => {
        if (!catOpen && !groupOpen && !pkgOpen && !stOpen) dirty.value = false;
    });

    return {
        toasts, activeTab, switchTab, canEdit, isAdmin, closeCatModal, closePkgGroupModal, closePkgModal, closeStModal,
        // 分類
        categories, catModal, openCatAdd, openCatEdit, saveCategory, archiveCategory, restoreCategory, forceDeleteCategory, moveCategory,
        // パッケージ分類
        packageGroups, pkgGroupModal, openPkgGroupAdd, openPkgGroupEdit, savePackageGroup, archivePackageGroup, restorePackageGroup, forceDeletePackageGroup, movePackageGroup,
        // パッケージ
        packages, pkgModal, openPkgAdd, openPkgEdit, savePackage, archivePackage, restorePackage, forceDeletePackage, movePackage,
        // スペック種別
        specTypes, stModal, openStAdd, openStEdit, saveSpecType, archiveSpecType, restoreSpecType, forceDeleteSpecType, moveSpecType,
    };
}
