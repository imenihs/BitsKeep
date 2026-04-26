/**
 * マスタ管理ページ（SCR-009）
 * 分類 / パッケージ分類 / パッケージ / スペック種別 の CRUD
 * data-tab="categories|package-groups|packages|spec-types" で初期タブを切り替え可
 */
import { ref, reactive, computed, onMounted, watch } from 'vue';
import { api } from '../api.js';
import { useToast } from '../composables/useToast.js';
import { useNavigationConfirm } from '../composables/useNavigationConfirm.js';
import { useConfirmModal } from '../composables/useConfirmModal.js';

export default function setup() {
    const { toasts, toastSuccess, toastError } = useToast();
    const { ask } = useConfirmModal();
    const dirty = ref(false);
    useNavigationConfirm(dirty, '未保存の変更があります。このまま画面を離れてもよいですか？');

    // ── タブ ──────────────────────────────────────────────
    const appEl  = document.getElementById('app');
    const activeTab = ref(appEl?.dataset?.tab ?? 'categories');
    const canEdit = appEl?.dataset?.canEdit === '1';
    const isAdmin = appEl?.dataset?.isAdmin === '1';

    const clone = (value) => JSON.parse(JSON.stringify(value));
    const same = (a, b) => JSON.stringify(a) === JSON.stringify(b);
    const splitActive = (items) => items.filter((item) => !item.deleted_at);
    const splitArchived = (items) => items.filter((item) => item.deleted_at);
    const nextSortOrder = (items) => (splitActive(items).at(-1)?.sort_order ?? 0) + 10;
    const copyName = (name) => `${name} コピー`;
    const closeModalWithConfirm = async (modal, snapshot) => {
        if (modal.open && !same(modal.form, snapshot) && !await ask('未保存の変更があります。閉じてもよいですか？')) return;
        modal.open = false;
    };

    const fetchError = ref('');

    // ── ドラッグ&ドロップ並び替え ─────────────────────────
    const dragSrc = ref(null);
    const dragTarget = ref(null);

    const makeDnD = (arr, buildPayload, fetchFn) => ({
        start: (i) => { dragSrc.value = i; dragTarget.value = i; },
        over:  (e, i) => { e.preventDefault(); dragTarget.value = i; },
        end:   () => { dragSrc.value = null; dragTarget.value = null; },
        drop:  async (i) => {
            const from = dragSrc.value;
            dragSrc.value = null; dragTarget.value = null;
            if (from === null || from === i) return;
            const items = [...arr.value];
            const [moved] = items.splice(from, 1);
            items.splice(i, 0, moved);
            arr.value = items; // 楽観的更新
            try {
                await Promise.all(items.map((item, idx) => api.put(buildPayload(item).url, { ...buildPayload(item).body, sort_order: (idx + 1) * 10 })));
                toastSuccess('並び順を更新しました');
                await fetchFn();
            } catch (e) { toastError(e.message); await fetchFn(); }
        },
    });

    // ── 汎用確認モーダル ──────────────────────────────────
    const confirmModal = reactive({ open: false, title: '', message: '', actionLabel: '', actionClass: '', onConfirm: null });
    const openConfirm = ({ title, message, actionLabel, actionClass = 'border-red-400 text-red-600 hover:bg-red-50', onConfirm }) => {
        Object.assign(confirmModal, { open: true, title, message, actionLabel, actionClass, onConfirm });
    };
    const doConfirm = async () => {
        confirmModal.open = false;
        await confirmModal.onConfirm?.();
    };

    // ── 分類 ─────────────────────────────────────────────
    const categories = ref([]);
    const activeCategories = computed({
        get: () => splitActive(categories.value),
        set: (items) => { categories.value = [...items, ...splitArchived(categories.value)]; },
    });
    const archivedCategories = computed(() => splitArchived(categories.value));
    const catSnapshot = ref(null);
    const catModal = reactive({
        open: false, isEdit: false, editId: null,
        form: { name: '', description: '', sort_order: 0 }
    });

    const fetchCategories = async () => {
        fetchError.value = '';
        try { const r = await api.get('/categories?include_archived=1'); categories.value = r.data; }
        catch { fetchError.value = '分類の取得に失敗しました。再試行してください。'; toastError('分類の取得に失敗しました'); }
    };

    const openCatAdd = () => {
        const form = { name: '', description: '', sort_order: nextSortOrder(categories.value) };
        catSnapshot.value = clone(form);
        Object.assign(catModal, { open: true, isEdit: false, editId: null, form });
    };
    const openCatEdit = (c) => {
        const form = { name: c.name, description: c.description ?? '', sort_order: c.sort_order ?? 0 };
        catSnapshot.value = clone(form);
        Object.assign(catModal, { open: true, isEdit: true, editId: c.id, form });
    };
    const openCatDuplicate = (c) => {
        const form = { name: copyName(c.name), description: c.description ?? '', sort_order: nextSortOrder(categories.value) };
        catSnapshot.value = clone(form);
        Object.assign(catModal, { open: true, isEdit: false, editId: null, form });
    };

    const saveCategory = async () => {
        try {
            if (catModal.isEdit) await api.put(`/categories/${catModal.editId}`, catModal.form);
            else await api.post('/categories', catModal.form);
            toastSuccess('保存しました'); catModal.open = false; catSnapshot.value = clone(catModal.form); await fetchCategories();
        } catch (e) { toastError(e.message); }
    };
    const closeCatModal = () => closeModalWithConfirm(catModal, catSnapshot.value);

    const archiveCategory = (c) => openConfirm({
        title: '分類をアーカイブしますか？',
        message: `「${c.name}」をアーカイブします。\n使用件数: ${c.usage_count ?? 0}件`,
        actionLabel: 'アーカイブする',
        onConfirm: async () => {
            try { await api.delete(`/categories/${c.id}`); await fetchCategories(); toastSuccess('アーカイブしました'); }
            catch (e) { toastError(e.message); }
        },
    });
    const restoreCategory = (c) => openConfirm({
        title: '分類を復元しますか？',
        message: `「${c.name}」を復元します。`,
        actionLabel: '復元する',
        actionClass: 'border-emerald-400 text-emerald-700 hover:bg-emerald-50',
        onConfirm: async () => {
            try { await api.post(`/categories/${c.id}/restore`); await fetchCategories(); toastSuccess('復元しました'); }
            catch (e) { toastError(e.message); }
        },
    });
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
    const activePackageGroups = computed({
        get: () => splitActive(packageGroups.value),
        set: (items) => { packageGroups.value = [...items, ...splitArchived(packageGroups.value)]; },
    });
    const archivedPackageGroups = computed(() => splitArchived(packageGroups.value));
    const pkgGroupSnapshot = ref(null);
    const pkgGroupModal = reactive({
        open: false, isEdit: false, editId: null,
        form: { name: '', description: '', sort_order: 0 }
    });

    const fetchPackageGroups = async () => {
        fetchError.value = '';
        try { const r = await api.get('/package-groups?include_archived=1'); packageGroups.value = r.data; }
        catch { fetchError.value = 'パッケージ分類の取得に失敗しました。再試行してください。'; toastError('パッケージ分類の取得に失敗しました'); }
    };

    const openPkgGroupAdd = () => {
        const form = { name: '', description: '', sort_order: nextSortOrder(packageGroups.value) };
        pkgGroupSnapshot.value = clone(form);
        Object.assign(pkgGroupModal, { open: true, isEdit: false, editId: null, form });
    };
    const openPkgGroupEdit = (group) => {
        const form = { name: group.name, description: group.description ?? '', sort_order: group.sort_order ?? 0 };
        pkgGroupSnapshot.value = clone(form);
        Object.assign(pkgGroupModal, { open: true, isEdit: true, editId: group.id, form });
    };
    const openPkgGroupDuplicate = (group) => {
        const form = { name: copyName(group.name), description: group.description ?? '', sort_order: nextSortOrder(packageGroups.value) };
        pkgGroupSnapshot.value = clone(form);
        Object.assign(pkgGroupModal, { open: true, isEdit: false, editId: null, form });
    };

    const savePackageGroup = async () => {
        try {
            if (pkgGroupModal.isEdit) await api.put(`/package-groups/${pkgGroupModal.editId}`, pkgGroupModal.form);
            else await api.post('/package-groups', pkgGroupModal.form);
            toastSuccess('保存しました'); pkgGroupModal.open = false; pkgGroupSnapshot.value = clone(pkgGroupModal.form); await fetchPackageGroups();
        } catch (e) { toastError(e.message); }
    };
    const closePkgGroupModal = () => closeModalWithConfirm(pkgGroupModal, pkgGroupSnapshot.value);

    const archivePackageGroup = (group) => openConfirm({
        title: 'パッケージ分類をアーカイブしますか？',
        message: `「${group.name}」をアーカイブします。\n使用件数: ${group.usage_count ?? 0}件`,
        actionLabel: 'アーカイブする',
        onConfirm: async () => {
            try { await api.delete(`/package-groups/${group.id}`); await fetchPackageGroups(); toastSuccess('アーカイブしました'); }
            catch (e) { toastError(e.message); }
        },
    });
    const restorePackageGroup = (group) => openConfirm({
        title: 'パッケージ分類を復元しますか？',
        message: `「${group.name}」を復元します。`,
        actionLabel: '復元する',
        actionClass: 'border-emerald-400 text-emerald-700 hover:bg-emerald-50',
        onConfirm: async () => {
            try { await api.post(`/package-groups/${group.id}/restore`); await fetchPackageGroups(); toastSuccess('復元しました'); }
            catch (e) { toastError(e.message); }
        },
    });
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
    const activePackages = computed({
        get: () => splitActive(packages.value),
        set: (items) => { packages.value = [...items, ...splitArchived(packages.value)]; },
    });
    const archivedPackages = computed(() => splitArchived(packages.value));
    const pkgSnapshot = ref(null);
    const pkgModal = reactive({
        open: false, isEdit: false, editId: null,
        form: { package_group_id: '', name: '', description: '', sort_order: 0 }
    });

    const fetchPackages = async () => {
        fetchError.value = '';
        try { const r = await api.get('/packages?include_archived=1'); packages.value = r.data; }
        catch { fetchError.value = 'パッケージの取得に失敗しました。再試行してください。'; toastError('パッケージの取得に失敗しました'); }
    };

    const openPkgAdd = () => {
        const form = { package_group_id: '', name: '', description: '', sort_order: nextSortOrder(packages.value) };
        pkgSnapshot.value = clone(form);
        Object.assign(pkgModal, { open: true, isEdit: false, editId: null, form });
    };
    const openPkgEdit = (p) => {
        const form = { package_group_id: p.package_group_id ?? '', name: p.name, description: p.description ?? '', sort_order: p.sort_order ?? 0 };
        pkgSnapshot.value = clone(form);
        Object.assign(pkgModal, { open: true, isEdit: true, editId: p.id, form });
    };
    const openPkgDuplicate = (p) => {
        const form = { package_group_id: p.package_group_id ?? '', name: copyName(p.name), description: p.description ?? '', sort_order: nextSortOrder(packages.value) };
        pkgSnapshot.value = clone(form);
        Object.assign(pkgModal, { open: true, isEdit: false, editId: null, form });
    };

    const savePackage = async () => {
        try {
            if (pkgModal.isEdit) await api.put(`/packages/${pkgModal.editId}`, pkgModal.form);
            else await api.post('/packages', pkgModal.form);
            toastSuccess('保存しました'); pkgModal.open = false; pkgSnapshot.value = clone(pkgModal.form); await fetchPackages();
        } catch (e) { toastError(e.message); }
    };
    const closePkgModal = () => closeModalWithConfirm(pkgModal, pkgSnapshot.value);

    const archivePackage = (p) => openConfirm({
        title: 'パッケージをアーカイブしますか？',
        message: `「${p.name}」をアーカイブします。\n使用件数: ${p.usage_count ?? 0}件`,
        actionLabel: 'アーカイブする',
        onConfirm: async () => {
            try { await api.delete(`/packages/${p.id}`); await fetchPackages(); toastSuccess('アーカイブしました'); }
            catch (e) { toastError(e.message); }
        },
    });
    const restorePackage = (p) => openConfirm({
        title: 'パッケージを復元しますか？',
        message: `「${p.name}」を復元します。`,
        actionLabel: '復元する',
        actionClass: 'border-emerald-400 text-emerald-700 hover:bg-emerald-50',
        onConfirm: async () => {
            try { await api.post(`/packages/${p.id}/restore`); await fetchPackages(); toastSuccess('復元しました'); }
            catch (e) { toastError(e.message); }
        },
    });
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
    const activeSpecTypes = computed({
        get: () => splitActive(specTypes.value),
        set: (items) => { specTypes.value = [...items, ...splitArchived(specTypes.value)]; },
    });
    const archivedSpecTypes = computed(() => splitArchived(specTypes.value));
    const stSnapshot = ref(null);
    const stModal = reactive({
        open: false, isEdit: false, editId: null,
        form: { name: '', name_ja: '', name_en: '', symbol: '', aliases_text: '', description: '', value_type: 'numeric', sort_order: 0, unit: '' }
    });

    const fetchSpecTypes = async () => {
        fetchError.value = '';
        try { const r = await api.get('/spec-types?include_archived=1'); specTypes.value = r.data; }
        catch { fetchError.value = 'スペック種別の取得に失敗しました。再試行してください。'; toastError('スペック種別の取得に失敗しました'); }
    };

    const openStAdd = () => {
        const form = { name: '', name_ja: '', name_en: '', symbol: '', aliases_text: '', description: '', value_type: 'numeric', sort_order: nextSortOrder(specTypes.value), unit: '' };
        stSnapshot.value = clone(form);
        Object.assign(stModal, { open: true, isEdit: false, editId: null, form });
    };
    const openStEdit = (s) => {
        const form = {
            name: s.name, name_ja: s.name_ja ?? s.name, name_en: s.name_en ?? '', symbol: s.symbol ?? '',
            aliases_text: (s.aliases ?? []).map((alias) => alias.alias).join('\n'),
            description: s.description ?? '',
            value_type: s.value_type ?? 'numeric',
            sort_order: s.sort_order ?? 0,
            unit: s.units?.[0]?.unit ?? '',
        };
        stSnapshot.value = clone(form);
        Object.assign(stModal, { open: true, isEdit: true, editId: s.id, form });
    };
    const openStDuplicate = (s) => {
        const form = {
            name: copyName(s.name), name_ja: copyName(s.name_ja ?? s.name), name_en: s.name_en ?? '', symbol: s.symbol ?? '',
            aliases_text: (s.aliases ?? []).map((alias) => alias.alias).join('\n'),
            description: s.description ?? '',
            value_type: s.value_type ?? 'numeric',
            sort_order: nextSortOrder(specTypes.value),
            unit: s.units?.[0]?.unit ?? '',
        };
        stSnapshot.value = clone(form);
        Object.assign(stModal, { open: true, isEdit: false, editId: null, form });
    };

    const saveSpecType = async () => {
        try {
            const payload = {
                ...stModal.form,
                name: stModal.form.name_ja || stModal.form.name,
                aliases: String(stModal.form.aliases_text ?? '')
                    .split(/\r?\n/u)
                    .map((alias) => ({ alias: alias.trim() }))
                    .filter((item) => item.alias),
            };
            delete payload.aliases_text;
            if (stModal.isEdit) await api.put(`/spec-types/${stModal.editId}`, payload);
            else await api.post('/spec-types', payload);
            toastSuccess('保存しました'); stModal.open = false; stSnapshot.value = clone(stModal.form); await fetchSpecTypes();
        } catch (e) { toastError(e.message); }
    };
    const closeStModal = () => closeModalWithConfirm(stModal, stSnapshot.value);
    const buildSpecTypeUpdatePayload = (item, sortOrder = item.sort_order ?? 0) => ({
        name: item.name_ja || item.name,
        name_ja: item.name_ja ?? item.name ?? '',
        name_en: item.name_en ?? '',
        symbol: item.symbol ?? '',
        aliases: (item.aliases ?? []).map((alias) => ({
            alias: alias.alias,
            locale: alias.locale ?? null,
            kind: alias.kind ?? null,
        })),
        description: item.description ?? '',
        value_type: item.value_type ?? 'numeric',
        unit: item.units?.[0]?.unit ?? '',
        sort_order: sortOrder,
    });

    const archiveSpecType = (s) => openConfirm({
        title: 'スペック種別をアーカイブしますか？',
        message: `「${s.name}」をアーカイブします。\n使用件数: ${s.usage_count ?? 0}件`,
        actionLabel: 'アーカイブする',
        onConfirm: async () => {
            try { await api.delete(`/spec-types/${s.id}`); await fetchSpecTypes(); toastSuccess('アーカイブしました'); }
            catch (e) { toastError(e.message); }
        },
    });
    const restoreSpecType = (s) => openConfirm({
        title: 'スペック種別を復元しますか？',
        message: `「${s.name}」を復元します。`,
        actionLabel: '復元する',
        actionClass: 'border-emerald-400 text-emerald-700 hover:bg-emerald-50',
        onConfirm: async () => {
            try { await api.post(`/spec-types/${s.id}/restore`); await fetchSpecTypes(); toastSuccess('復元しました'); }
            catch (e) { toastError(e.message); }
        },
    });
    const moveSpecType = async (index, delta) => {
        const target = index + delta;
        if (target < 0 || target >= specTypes.value.length) return;
        const ordered = [...specTypes.value];
        [ordered[index], ordered[target]] = [ordered[target], ordered[index]];
        try {
            await Promise.all(ordered.map((item, idx) => api.put(`/spec-types/${item.id}`, {
                ...buildSpecTypeUpdatePayload(item, (idx + 1) * 10),
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

    // DnDインスタンスはすべてのrefが揃ったここで生成する
    const catDnD = makeDnD(activeCategories,    (c) => ({ url: `/categories/${c.id}`,     body: { name: c.name, color: c.color ?? null } }), fetchCategories);
    const pgDnD  = makeDnD(activePackageGroups, (g) => ({ url: `/package-groups/${g.id}`, body: { name: g.name, description: g.description ?? '' } }), fetchPackageGroups);
    const pkgDnD = makeDnD(activePackages,      (p) => ({ url: `/packages/${p.id}`,       body: { package_group_id: p.package_group_id ?? '', name: p.name, description: p.description ?? '' } }), fetchPackages);
    const stDnD  = makeDnD(activeSpecTypes,     (s) => ({ url: `/spec-types/${s.id}`,     body: buildSpecTypeUpdatePayload(s) }), fetchSpecTypes);

    return {
        toasts, fetchError, activeTab, switchTab, canEdit, isAdmin, closeCatModal, closePkgGroupModal, closePkgModal, closeStModal,
        confirmModal, doConfirm,
        dragSrc, dragTarget, catDnD, pgDnD, pkgDnD, stDnD,
        fetchCategories, fetchPackageGroups, fetchPackages, fetchSpecTypes,
        // 分類
        categories, activeCategories, archivedCategories, catModal, openCatAdd, openCatEdit, openCatDuplicate, saveCategory, archiveCategory, restoreCategory, moveCategory,
        // パッケージ分類
        packageGroups, activePackageGroups, archivedPackageGroups, pkgGroupModal, openPkgGroupAdd, openPkgGroupEdit, openPkgGroupDuplicate, savePackageGroup, archivePackageGroup, restorePackageGroup, movePackageGroup,
        // パッケージ
        packages, activePackages, archivedPackages, pkgModal, openPkgAdd, openPkgEdit, openPkgDuplicate, savePackage, archivePackage, restorePackage, movePackage,
        // スペック種別
        specTypes, activeSpecTypes, archivedSpecTypes, stModal, openStAdd, openStEdit, openStDuplicate, saveSpecType, archiveSpecType, restoreSpecType, moveSpecType,
    };
}
