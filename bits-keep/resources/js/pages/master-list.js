/**
 * マスタ管理ページ（SCR-009）
 * 分類 / パッケージ分類 / パッケージ / スペック項目 の CRUD
 * ?tab=categories|package-groups|packages|spec-groups|spec-types で初期タブを切り替え可
 */
import { ref, reactive, computed, onMounted, watch } from 'vue';
import { api } from '../api.js';
import { useToast } from '../composables/useToast.js';
import { useNavigationConfirm } from '../composables/useNavigationConfirm.js';
import { useConfirmModal } from '../composables/useConfirmModal.js';
import { renderSymbol } from '../utils/specValue.js';

export default function setup() {
    const { toasts, toastSuccess, toastError } = useToast();
    const { ask } = useConfirmModal();
    const modalDirty = ref(false);
    const inlineDirty = ref(false);
    const dirty = computed(() => modalDirty.value || inlineDirty.value);
    useNavigationConfirm(dirty, '未保存の変更があります。このまま画面を離れてもよいですか？');

    // ── タブ ──────────────────────────────────────────────
    const appEl  = document.getElementById('app');
    const tabIds = ['categories', 'package-groups', 'packages', 'spec-groups', 'spec-types'];
    const normalizeTab = (tab) => tabIds.includes(tab) ? tab : 'categories';
    const tabFromUrl = () => {
        const params = new URLSearchParams(window.location.search);
        const fromQuery = params.get('tab');
        const fromHash = window.location.hash?.startsWith('#tab=') ? window.location.hash.slice(5) : '';
        return normalizeTab(fromQuery || fromHash || appEl?.dataset?.tab);
    };
    const activeTab = ref(tabFromUrl());
    const canEdit = appEl?.dataset?.canEdit === '1';
    const isAdmin = appEl?.dataset?.isAdmin === '1';

    const syncTabToUrl = (tab, { replace = false } = {}) => {
        const url = new URL(window.location.href);
        url.searchParams.set('tab', normalizeTab(tab));
        if (url.hash?.startsWith('#tab=')) url.hash = '';
        const method = replace ? 'replaceState' : 'pushState';
        window.history?.[method]?.({ tab: normalizeTab(tab) }, '', url);
    };

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
    const selectedPackageGroupId = ref(null);
    const activePackageGroups = computed({
        get: () => splitActive(packageGroups.value),
        set: (items) => { packageGroups.value = [...items, ...splitArchived(packageGroups.value)]; },
    });
    const archivedPackageGroups = computed(() => splitArchived(packageGroups.value));
    const currentPackageGroup = computed(() => packageGroups.value.find((group) => Number(group.id) === Number(selectedPackageGroupId.value)) ?? null);
    const ensureSelectedPackageGroup = () => {
        const activeGroups = activePackageGroups.value;
        if (activeGroups.length === 0) {
            selectedPackageGroupId.value = null;
            return;
        }
        if (!activeGroups.some((group) => Number(group.id) === Number(selectedPackageGroupId.value))) {
            selectedPackageGroupId.value = activeGroups[0].id;
        }
    };
    const pkgGroupSnapshot = ref(null);
    const pkgGroupModal = reactive({
        open: false, isEdit: false, editId: null,
        form: { name: '', description: '', sort_order: 0 }
    });

    const fetchPackageGroups = async () => {
        fetchError.value = '';
        try {
            const r = await api.get('/package-groups?include_archived=1');
            packageGroups.value = r.data;
            ensureSelectedPackageGroup();
            if (activeTab.value === 'packages' && selectedPackageGroupId.value && packages.value.length === 0) {
                await fetchPackages();
            }
        }
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
        form: {
            package_group_id: '',
            name: '',
            description: '',
            size_x: '',
            size_y: '',
            size_z: '',
            image: null,
            pdf: null,
            image_url: '',
            pdf_url: '',
            sort_order: 0,
        }
    });

    const packageForm = (overrides = {}) => ({
        package_group_id: selectedPackageGroupId.value ?? '',
        name: '',
        description: '',
        size_x: '',
        size_y: '',
        size_z: '',
        image: null,
        pdf: null,
        image_url: '',
        pdf_url: '',
        sort_order: nextSortOrder(packages.value),
        ...overrides,
    });

    const packageDimensions = (p) => {
        const values = [p.size_x, p.size_y, p.size_z]
            .map((value) => value === null || value === undefined || value === '' ? '' : Number(value).toLocaleString(undefined, { maximumFractionDigits: 4 }))
            .filter(Boolean);
        return values.length > 0 ? `${values.join(' x ')} mm` : '-';
    };

    const onPackageFileChange = (field, event) => {
        pkgModal.form[field] = event.target.files?.[0] ?? null;
    };

    const packageFormData = () => {
        const form = new FormData();
        ['package_group_id', 'name', 'description', 'size_x', 'size_y', 'size_z', 'sort_order'].forEach((key) => {
            form.append(key, pkgModal.form[key] ?? '');
        });
        if (pkgModal.form.image) form.append('image', pkgModal.form.image);
        if (pkgModal.form.pdf) form.append('pdf', pkgModal.form.pdf);
        return form;
    };

    const fetchPackages = async () => {
        fetchError.value = '';
        ensureSelectedPackageGroup();
        if (!selectedPackageGroupId.value) {
            packages.value = [];
            return;
        }
        try {
            const r = await api.get(`/packages?include_archived=1&package_group_id=${selectedPackageGroupId.value}`);
            packages.value = r.data;
        }
        catch { fetchError.value = 'パッケージの取得に失敗しました。再試行してください。'; toastError('パッケージの取得に失敗しました'); }
    };

    const selectPackageGroup = async (group) => {
        selectedPackageGroupId.value = group?.id ?? null;
        packages.value = [];
        await fetchPackages();
    };

    const openPkgAdd = () => {
        if (!selectedPackageGroupId.value) {
            toastError('先にパッケージ分類を選択してください');
            return;
        }
        const form = packageForm();
        pkgSnapshot.value = clone(form);
        Object.assign(pkgModal, { open: true, isEdit: false, editId: null, form });
    };
    const openPkgEdit = (p) => {
        const form = packageForm({
            package_group_id: p.package_group_id ?? selectedPackageGroupId.value ?? '',
            name: p.name,
            description: p.description ?? '',
            size_x: p.size_x ?? '',
            size_y: p.size_y ?? '',
            size_z: p.size_z ?? '',
            image_url: p.image_url ?? '',
            pdf_url: p.pdf_url ?? '',
            sort_order: p.sort_order ?? 0,
        });
        pkgSnapshot.value = clone(form);
        Object.assign(pkgModal, { open: true, isEdit: true, editId: p.id, form });
    };
    const openPkgDuplicate = (p) => {
        const form = packageForm({
            package_group_id: p.package_group_id ?? selectedPackageGroupId.value ?? '',
            name: copyName(p.name),
            description: p.description ?? '',
            size_x: p.size_x ?? '',
            size_y: p.size_y ?? '',
            size_z: p.size_z ?? '',
        });
        pkgSnapshot.value = clone(form);
        Object.assign(pkgModal, { open: true, isEdit: false, editId: null, form });
    };

    const savePackage = async () => {
        try {
            if (pkgModal.isEdit) await api.uploadPut(`/packages/${pkgModal.editId}`, packageFormData());
            else await api.upload('/packages', packageFormData());
            if (pkgModal.form.package_group_id) selectedPackageGroupId.value = pkgModal.form.package_group_id;
            toastSuccess('保存しました'); pkgModal.open = false; pkgSnapshot.value = clone(pkgModal.form); await fetchPackageGroups(); await fetchPackages();
        } catch (e) { toastError(e.message); }
    };
    const closePkgModal = () => closeModalWithConfirm(pkgModal, pkgSnapshot.value);

    const archivePackage = (p) => openConfirm({
        title: 'パッケージをアーカイブしますか？',
        message: `「${p.name}」をアーカイブします。\n使用件数: ${p.usage_count ?? 0}件`,
        actionLabel: 'アーカイブする',
        onConfirm: async () => {
            try { await api.delete(`/packages/${p.id}`); await fetchPackageGroups(); await fetchPackages(); toastSuccess('アーカイブしました'); }
            catch (e) { toastError(e.message); }
        },
    });
    const restorePackage = (p) => openConfirm({
        title: 'パッケージを復元しますか？',
        message: `「${p.name}」を復元します。`,
        actionLabel: '復元する',
        actionClass: 'border-emerald-400 text-emerald-700 hover:bg-emerald-50',
        onConfirm: async () => {
            try { await api.post(`/packages/${p.id}/restore`); await fetchPackageGroups(); await fetchPackages(); toastSuccess('復元しました'); }
            catch (e) { toastError(e.message); }
        },
    });
    const movePackage = async (index, delta) => {
        const target = index + delta;
        if (target < 0 || target >= activePackages.value.length || !selectedPackageGroupId.value) return;
        const ordered = [...activePackages.value];
        [ordered[index], ordered[target]] = [ordered[target], ordered[index]];
        activePackages.value = ordered;
        try {
            await api.put(`/package-groups/${selectedPackageGroupId.value}/packages/reorder`, {
                package_ids: ordered.map((item) => item.id),
            });
            toastSuccess('並び順を更新しました');
            await fetchPackages();
        } catch (e) { toastError(e.message); await fetchPackages(); }
    };

    // ── スペック項目 ──────────────────────────────────────
    const specTypes = ref([]);
    const activeSpecTypes = computed({
        get: () => splitActive(specTypes.value),
        set: (items) => { specTypes.value = [...items, ...splitArchived(specTypes.value)]; },
    });
    const archivedSpecTypes = computed(() => splitArchived(specTypes.value));
    const stSnapshot = ref(null);
    const stModal = reactive({
        open: false, isEdit: false, editId: null,
        form: { name: '', name_ja: '', name_en: '', symbol: '', aliases_text: '', description: '', value_type: 'numeric', sort_order: 0, unit: '', suggest_prefixes: [], display_prefixes: [] }
    });

    const fetchSpecTypes = async () => {
        fetchError.value = '';
        try { const r = await api.get('/spec-types?include_archived=1'); specTypes.value = r.data; }
        catch { fetchError.value = 'スペック項目の取得に失敗しました。再試行してください。'; toastError('スペック項目の取得に失敗しました'); }
    };

    const openStAdd = () => {
        const form = { name: '', name_ja: '', name_en: '', symbol: '', aliases_text: '', description: '', value_type: 'numeric', sort_order: nextSortOrder(specTypes.value), unit: '', suggest_prefixes: [], display_prefixes: [] };
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
            suggest_prefixes: Array.isArray(s.suggest_prefixes) ? [...s.suggest_prefixes] : [],
            display_prefixes: Array.isArray(s.display_prefixes) ? [...s.display_prefixes] : [],
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
            suggest_prefixes: Array.isArray(s.suggest_prefixes) ? [...s.suggest_prefixes] : [],
            display_prefixes: Array.isArray(s.display_prefixes) ? [...s.display_prefixes] : [],
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
                suggest_prefixes: stModal.form.suggest_prefixes?.length > 0 ? stModal.form.suggest_prefixes : null,
                display_prefixes: stModal.form.display_prefixes?.length > 0 ? stModal.form.display_prefixes : null,
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
        suggest_prefixes: Array.isArray(item.suggest_prefixes) && item.suggest_prefixes.length > 0 ? item.suggest_prefixes : null,
        display_prefixes: Array.isArray(item.display_prefixes) && item.display_prefixes.length > 0 ? item.display_prefixes : null,
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
        title: 'スペック項目をアーカイブしますか？',
        message: `「${s.name}」をアーカイブします。\n使用件数: ${s.usage_count ?? 0}件`,
        actionLabel: 'アーカイブする',
        onConfirm: async () => {
            try { await api.delete(`/spec-types/${s.id}`); await fetchSpecTypes(); toastSuccess('アーカイブしました'); }
            catch (e) { toastError(e.message); }
        },
    });
    const restoreSpecType = (s) => openConfirm({
        title: 'スペック項目を復元しますか？',
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

    // ── スペック分類 / テンプレート ───────────────────────
    const specGroups = ref([]);
    const selectedSpecGroupId = ref(null);
    const activeSpecGroups = computed({
        get: () => splitActive(specGroups.value),
        set: (items) => { specGroups.value = [...items, ...splitArchived(specGroups.value)]; },
    });
    const archivedSpecGroups = computed(() => splitArchived(specGroups.value));
    const currentSpecGroup = computed(() => specGroups.value.find((group) => Number(group.id) === Number(selectedSpecGroupId.value)) ?? null);
    const specGroupSnapshot = ref(null);
    const specGroupModal = reactive({
        open: false, isEdit: false, editId: null,
        form: { name: '', description: '', sort_order: 0, category_links: [] },
    });
    const memberEditor = reactive({
        spec_type_id: '',
        state: 'recommended',
        default_profile: 'typ',
        default_unit: '',
        note: '',
    });
    const memberSnapshot = ref(null);
    const templateSnapshot = ref(null);
    const templateModal = reactive({
        open: false, isEdit: false, editId: null,
        form: { spec_group_id: '', name: '', description: '', sort_order: 0, items: [] },
    });

    const replaceSpecGroup = (group) => {
        const index = specGroups.value.findIndex((item) => Number(item.id) === Number(group.id));
        if (index >= 0) specGroups.value.splice(index, 1, group);
        else specGroups.value.push(group);
        selectedSpecGroupId.value = group.id;
    };
    const ensureSelectedSpecGroup = () => {
        const activeGroups = activeSpecGroups.value;
        if (activeGroups.length === 0) {
            selectedSpecGroupId.value = null;
            return;
        }
        if (!activeGroups.some((group) => Number(group.id) === Number(selectedSpecGroupId.value))) {
            selectedSpecGroupId.value = activeGroups[0].id;
        }
    };
    const fetchSpecGroups = async () => {
        fetchError.value = '';
        try {
            const r = await api.get('/spec-groups?include_archived=1&with_categories=1&with_spec_types=1&with_templates=1');
            specGroups.value = r.data ?? [];
            ensureSelectedSpecGroup();
            syncMemberSnapshot();
        } catch {
            fetchError.value = 'スペック分類の取得に失敗しました。再試行してください。';
            toastError('スペック分類の取得に失敗しました');
        }
    };
    const selectSpecGroup = async (group) => {
        if (Number(selectedSpecGroupId.value) === Number(group?.id)) return;
        if (!await confirmDiscardUnsaved()) return;
        selectedSpecGroupId.value = group?.id ?? null;
    };
    const specGroupCategoryLinks = (group) => (group?.categories ?? []).map((category, index) => ({
        category_id: category.id,
        sort_order: category.pivot?.sort_order ?? ((index + 1) * 10),
        is_primary: !!category.pivot?.is_primary,
    }));
    const specGroupForm = (overrides = {}) => ({
        name: '',
        description: '',
        sort_order: nextSortOrder(specGroups.value),
        category_links: [],
        ...overrides,
    });
    const openSgAdd = () => {
        const form = specGroupForm();
        specGroupSnapshot.value = clone(form);
        Object.assign(specGroupModal, { open: true, isEdit: false, editId: null, form });
    };
    const openSgEdit = (group) => {
        const form = specGroupForm({
            name: group.name,
            description: group.description ?? '',
            sort_order: group.sort_order ?? 0,
            category_links: specGroupCategoryLinks(group),
        });
        specGroupSnapshot.value = clone(form);
        Object.assign(specGroupModal, { open: true, isEdit: true, editId: group.id, form });
    };
    const openSgDuplicate = (group) => {
        const form = specGroupForm({
            name: copyName(group.name),
            description: group.description ?? '',
            category_links: specGroupCategoryLinks(group),
        });
        specGroupSnapshot.value = clone(form);
        Object.assign(specGroupModal, { open: true, isEdit: false, editId: null, form });
    };
    const specGroupCategoryLinkIndex = (categoryId) => specGroupModal.form.category_links.findIndex((link) => Number(link.category_id) === Number(categoryId));
    const isSpecGroupCategoryLinked = (categoryId) => specGroupCategoryLinkIndex(categoryId) >= 0;
    const isSpecGroupPrimaryCategory = (categoryId) => {
        const index = specGroupCategoryLinkIndex(categoryId);
        return index >= 0 && !!specGroupModal.form.category_links[index].is_primary;
    };
    const toggleSpecGroupCategory = (categoryId) => {
        const index = specGroupCategoryLinkIndex(categoryId);
        if (index >= 0) {
            specGroupModal.form.category_links.splice(index, 1);
            return;
        }
        specGroupModal.form.category_links.push({
            category_id: categoryId,
            sort_order: (specGroupModal.form.category_links.length + 1) * 10,
            is_primary: false,
        });
    };
    const toggleSpecGroupPrimaryCategory = (categoryId) => {
        const index = specGroupCategoryLinkIndex(categoryId);
        if (index < 0) return;
        specGroupModal.form.category_links[index].is_primary = !specGroupModal.form.category_links[index].is_primary;
    };
    const saveSpecGroup = async () => {
        try {
            const payload = {
                ...specGroupModal.form,
                category_links: specGroupModal.form.category_links.map((link, index) => ({
                    category_id: link.category_id,
                    sort_order: (index + 1) * 10,
                    is_primary: !!link.is_primary,
                })),
            };
            const res = specGroupModal.isEdit
                ? await api.put(`/spec-groups/${specGroupModal.editId}`, payload)
                : await api.post('/spec-groups', payload);
            replaceSpecGroup(res.data);
            toastSuccess('保存しました');
            specGroupModal.open = false;
            specGroupSnapshot.value = clone(specGroupModal.form);
            await fetchSpecGroups();
        } catch (e) { toastError(e.message); }
    };
    const closeSpecGroupModal = () => closeModalWithConfirm(specGroupModal, specGroupSnapshot.value);
    const archiveSpecGroup = (group) => openConfirm({
        title: 'スペック分類をアーカイブしますか？',
        message: `「${group.name}」をアーカイブします。\n所属スペック項目: ${group.usage_count ?? 0}件 / テンプレート: ${group.template_count ?? 0}件`,
        actionLabel: 'アーカイブする',
        onConfirm: async () => {
            try { await api.delete(`/spec-groups/${group.id}`); await fetchSpecGroups(); toastSuccess('アーカイブしました'); }
            catch (e) { toastError(e.message); }
        },
    });
    const restoreSpecGroup = (group) => openConfirm({
        title: 'スペック分類を復元しますか？',
        message: `「${group.name}」を復元します。`,
        actionLabel: '復元する',
        actionClass: 'border-emerald-400 text-emerald-700 hover:bg-emerald-50',
        onConfirm: async () => {
            try { await api.post(`/spec-groups/${group.id}/restore`); await fetchSpecGroups(); toastSuccess('復元しました'); }
            catch (e) { toastError(e.message); }
        },
    });
    const unassignedSpecTypes = computed(() => {
        const assigned = new Set((currentSpecGroup.value?.spec_types ?? []).map((item) => Number(item.id)));
        return activeSpecTypes.value.filter((item) => !assigned.has(Number(item.id)));
    });
    const memberState = (member) => member?.pivot?.is_required ? 'required' : (member?.pivot?.is_recommended ? 'recommended' : 'optional');
    const setMemberState = (member, state) => {
        member.pivot = member.pivot ?? {};
        member.pivot.is_required = state === 'required';
        member.pivot.is_recommended = state !== 'optional';
    };
    const defaultMemberEditor = () => ({ spec_type_id: '', state: 'recommended', default_profile: 'typ', default_unit: '', note: '' });
    const memberPayload = (members = []) => members.map((member, index) => ({
        spec_type_id: Number(member.id),
        sort_order: (index + 1) * 10,
        is_required: !!member.pivot?.is_required,
        is_recommended: !!member.pivot?.is_recommended,
        default_profile: member.pivot?.default_profile || '',
        default_unit: member.pivot?.default_unit || '',
        note: member.pivot?.note || '',
    }));
    const hasMemberChanges = () => {
        if (activeTab.value !== 'spec-groups' || !currentSpecGroup.value || memberSnapshot.value === null) return false;
        return !same(memberPayload(currentSpecGroup.value.spec_types ?? []), memberPayload(memberSnapshot.value ?? []));
    };
    const hasMemberEditorChanges = () => {
        if (activeTab.value !== 'spec-groups') return false;
        const defaults = defaultMemberEditor();
        return Object.keys(defaults).some((key) => String(memberEditor[key] ?? '') !== String(defaults[key] ?? ''));
    };
    const refreshInlineDirty = () => {
        inlineDirty.value = hasMemberChanges() || hasMemberEditorChanges();
    };
    const syncMemberSnapshot = (group = currentSpecGroup.value) => {
        memberSnapshot.value = clone(group?.spec_types ?? []);
        refreshInlineDirty();
    };
    const resetMemberEditor = () => {
        Object.assign(memberEditor, defaultMemberEditor());
    };
    const discardInlineEdits = () => {
        if (currentSpecGroup.value && memberSnapshot.value !== null) {
            currentSpecGroup.value.spec_types = clone(memberSnapshot.value);
        }
        resetMemberEditor();
        inlineDirty.value = false;
    };
    const addSpecGroupMember = () => {
        if (!currentSpecGroup.value || !memberEditor.spec_type_id) return;
        const specType = specTypes.value.find((item) => Number(item.id) === Number(memberEditor.spec_type_id));
        if (!specType) return;
        const members = currentSpecGroup.value.spec_types ?? [];
        if (members.some((item) => Number(item.id) === Number(specType.id))) return;
        members.push({
            ...clone(specType),
            pivot: {
                sort_order: (members.length + 1) * 10,
                is_required: memberEditor.state === 'required',
                is_recommended: memberEditor.state !== 'optional',
                default_profile: memberEditor.default_profile || null,
                default_unit: memberEditor.default_unit || '',
                note: memberEditor.note || '',
            },
        });
        currentSpecGroup.value.spec_types = members;
        resetMemberEditor();
    };
    const removeSpecGroupMember = (index) => {
        currentSpecGroup.value?.spec_types?.splice(index, 1);
    };
    const moveSpecGroupMember = (index, delta) => {
        const members = currentSpecGroup.value?.spec_types ?? [];
        const target = index + delta;
        if (target < 0 || target >= members.length) return;
        [members[index], members[target]] = [members[target], members[index]];
    };
    const syncSpecGroupMembers = async () => {
        if (!currentSpecGroup.value) return;
        try {
            const items = (currentSpecGroup.value.spec_types ?? []).map((member, index) => ({
                spec_type_id: member.id,
                sort_order: (index + 1) * 10,
                is_required: !!member.pivot?.is_required,
                is_recommended: !!member.pivot?.is_recommended,
                default_profile: member.pivot?.default_profile || null,
                default_unit: member.pivot?.default_unit || null,
                note: member.pivot?.note || null,
            }));
            const res = await api.put(`/spec-groups/${currentSpecGroup.value.id}/spec-types`, { items });
            replaceSpecGroup(res.data);
            syncMemberSnapshot(res.data);
            resetMemberEditor();
            toastSuccess('所属スペック項目を保存しました');
        } catch (e) { toastError(e.message); }
    };
    const templateItem = (overrides = {}) => ({
        spec_type_id: '',
        default_profile: 'typ',
        default_unit: '',
        is_required: false,
        note: '',
        ...overrides,
    });
    const templateForm = (overrides = {}) => ({
        spec_group_id: currentSpecGroup.value?.id ?? '',
        name: '',
        description: '',
        sort_order: ((currentSpecGroup.value?.templates ?? []).length + 1) * 10,
        items: [],
        ...overrides,
    });
    const openTemplateAdd = () => {
        if (!currentSpecGroup.value) {
            toastError('先にスペック分類を選択してください');
            return;
        }
        const form = templateForm({ items: [templateItem()] });
        templateSnapshot.value = clone(form);
        Object.assign(templateModal, { open: true, isEdit: false, editId: null, form });
    };
    const openTemplateEdit = (template) => {
        const form = templateForm({
            spec_group_id: template.spec_group_id ?? currentSpecGroup.value?.id ?? '',
            name: template.name,
            description: template.description ?? '',
            sort_order: template.sort_order ?? 0,
            items: (template.items ?? []).map((item) => templateItem({
                spec_type_id: item.spec_type_id,
                default_profile: item.default_profile || 'typ',
                default_unit: item.default_unit ?? '',
                is_required: !!item.is_required,
                note: item.note ?? '',
            })),
        });
        templateSnapshot.value = clone(form);
        Object.assign(templateModal, { open: true, isEdit: true, editId: template.id, form });
    };
    const openTemplateDuplicate = (template) => {
        const form = templateForm({
            spec_group_id: template.spec_group_id ?? currentSpecGroup.value?.id ?? '',
            name: copyName(template.name),
            description: template.description ?? '',
            items: (template.items ?? []).map((item) => templateItem({
                spec_type_id: item.spec_type_id,
                default_profile: item.default_profile || 'typ',
                default_unit: item.default_unit ?? '',
                is_required: !!item.is_required,
                note: item.note ?? '',
            })),
        });
        templateSnapshot.value = clone(form);
        Object.assign(templateModal, { open: true, isEdit: false, editId: null, form });
    };
    const addTemplateItem = () => templateModal.form.items.push(templateItem());
    const removeTemplateItem = (index) => templateModal.form.items.splice(index, 1);
    const moveTemplateItem = (index, delta) => {
        const target = index + delta;
        const items = templateModal.form.items;
        if (target < 0 || target >= items.length) return;
        [items[index], items[target]] = [items[target], items[index]];
    };
    const saveTemplate = async () => {
        try {
            const payload = {
                ...templateModal.form,
                items: templateModal.form.items
                    .filter((item) => item.spec_type_id)
                    .map((item, index) => ({
                        ...item,
                        sort_order: (index + 1) * 10,
                        default_profile: item.default_profile || null,
                        default_unit: item.default_unit || null,
                        is_required: !!item.is_required,
                    })),
            };
            if (templateModal.isEdit) await api.put(`/spec-templates/${templateModal.editId}`, payload);
            else await api.post('/spec-templates', payload);
            toastSuccess('テンプレートを保存しました');
            templateModal.open = false;
            templateSnapshot.value = clone(templateModal.form);
            await fetchSpecGroups();
        } catch (e) { toastError(e.message); }
    };
    const closeTemplateModal = () => closeModalWithConfirm(templateModal, templateSnapshot.value);
    const archiveTemplate = (template) => openConfirm({
        title: 'スペックテンプレートをアーカイブしますか？',
        message: `「${template.name}」をアーカイブします。`,
        actionLabel: 'アーカイブする',
        onConfirm: async () => {
            try { await api.delete(`/spec-templates/${template.id}`); await fetchSpecGroups(); toastSuccess('アーカイブしました'); }
            catch (e) { toastError(e.message); }
        },
    });

    // ── タブ切り替え時のフェッチ ──────────────────────────
    const retryActiveTab = async () => {
        if (!await confirmDiscardUnsaved()) return;
        if (activeTab.value === 'categories') return fetchCategories();
        if (activeTab.value === 'package-groups') return fetchPackageGroups();
        if (activeTab.value === 'packages') return fetchPackages();
        if (activeTab.value === 'spec-groups') return fetchSpecGroups();
        return fetchSpecTypes();
    };

    const closeAllEditorModals = () => {
        catModal.open = false;
        pkgGroupModal.open = false;
        pkgModal.open = false;
        stModal.open = false;
        specGroupModal.open = false;
        templateModal.open = false;
    };
    const confirmDiscardUnsaved = async () => {
        if (!dirty.value) return true;
        const confirmed = await ask('未保存の変更があります。このまま移動すると編集内容は失われます。移動してもよいですか？');
        if (!confirmed) return false;
        discardInlineEdits();
        closeAllEditorModals();
        modalDirty.value = false;
        return true;
    };
    const switchTab = async (tab, { updateUrl = true, replaceUrl = false } = {}) => {
        const nextTab = normalizeTab(tab);
        const changed = activeTab.value !== nextTab;
        if (changed && !await confirmDiscardUnsaved()) {
            if (!updateUrl) syncTabToUrl(activeTab.value, { replace: true });
            return;
        }
        activeTab.value = nextTab;
        if (updateUrl && (changed || replaceUrl)) syncTabToUrl(nextTab, { replace: replaceUrl });

        if (nextTab === 'categories' && categories.value.length === 0) fetchCategories();
        else if (nextTab === 'package-groups' && packageGroups.value.length === 0) fetchPackageGroups();
        else if (nextTab === 'packages') {
            if (packageGroups.value.length === 0) fetchPackageGroups();
            fetchPackages();
        }
        else if (nextTab === 'spec-groups') {
            if (categories.value.length === 0) fetchCategories();
            if (specTypes.value.length === 0) fetchSpecTypes();
            fetchSpecGroups();
        }
        else if (nextTab === 'spec-types' && specTypes.value.length === 0) fetchSpecTypes();
    };

    onMounted(() => {
        // 初期タブのデータだけ取得し、URLにも現在タブを明示する
        switchTab(activeTab.value, { updateUrl: true, replaceUrl: true });
        window.addEventListener('popstate', () => {
            switchTab(tabFromUrl(), { updateUrl: false });
        });
    });

    watch(() => catModal.form, (value) => {
        if (catModal.open) modalDirty.value = !same(value, catSnapshot.value);
    }, { deep: true });
    watch(() => pkgModal.form, (value) => {
        if (pkgModal.open) modalDirty.value = !same(value, pkgSnapshot.value);
    }, { deep: true });
    watch(() => pkgGroupModal.form, (value) => {
        if (pkgGroupModal.open) modalDirty.value = !same(value, pkgGroupSnapshot.value);
    }, { deep: true });
    watch(() => stModal.form, (value) => {
        if (stModal.open) modalDirty.value = !same(value, stSnapshot.value);
    }, { deep: true });
    watch(() => specGroupModal.form, (value) => {
        if (specGroupModal.open) modalDirty.value = !same(value, specGroupSnapshot.value);
    }, { deep: true });
    watch(() => templateModal.form, (value) => {
        if (templateModal.open) modalDirty.value = !same(value, templateSnapshot.value);
    }, { deep: true });
    watch([() => catModal.open, () => pkgGroupModal.open, () => pkgModal.open, () => stModal.open, () => specGroupModal.open, () => templateModal.open], ([catOpen, groupOpen, pkgOpen, stOpen, specGroupOpen, templateOpen]) => {
        if (!catOpen && !groupOpen && !pkgOpen && !stOpen && !specGroupOpen && !templateOpen) modalDirty.value = false;
    });
    watch(() => currentSpecGroup.value?.id, () => {
        syncMemberSnapshot();
        resetMemberEditor();
        refreshInlineDirty();
    });
    watch(() => currentSpecGroup.value?.spec_types, refreshInlineDirty, { deep: true });
    watch(memberEditor, refreshInlineDirty, { deep: true });
    watch(() => activeTab.value, refreshInlineDirty);

    // DnDインスタンスはすべてのrefが揃ったここで生成する
    const catDnD = makeDnD(activeCategories,    (c) => ({ url: `/categories/${c.id}`,     body: { name: c.name, color: c.color ?? null } }), fetchCategories);
    const pgDnD  = makeDnD(activePackageGroups, (g) => ({ url: `/package-groups/${g.id}`, body: { name: g.name, description: g.description ?? '' } }), fetchPackageGroups);
    const pkgDnD = {
        start: (i) => { dragSrc.value = i; dragTarget.value = i; },
        over:  (e, i) => { e.preventDefault(); dragTarget.value = i; },
        end:   () => { dragSrc.value = null; dragTarget.value = null; },
        drop:  async (i) => {
            const from = dragSrc.value;
            dragSrc.value = null; dragTarget.value = null;
            if (from === null || from === i) return;
            const items = [...activePackages.value];
            const [moved] = items.splice(from, 1);
            items.splice(i, 0, moved);
            activePackages.value = items;
            try {
                await api.put(`/package-groups/${selectedPackageGroupId.value}/packages/reorder`, {
                    package_ids: items.map((item) => item.id),
                });
                toastSuccess('並び順を更新しました');
                await fetchPackages();
            } catch (e) { toastError(e.message); await fetchPackages(); }
        },
    };
    const stDnD  = makeDnD(activeSpecTypes,     (s) => ({ url: `/spec-types/${s.id}`,     body: buildSpecTypeUpdatePayload(s) }), fetchSpecTypes);

    return {
        toasts, fetchError, activeTab, switchTab, retryActiveTab, canEdit, isAdmin, closeCatModal, closePkgGroupModal, closePkgModal, closeStModal,
        confirmModal, doConfirm,
        dragSrc, dragTarget, catDnD, pgDnD, pkgDnD, stDnD,
        fetchCategories, fetchPackageGroups, fetchPackages, fetchSpecTypes, fetchSpecGroups,
        // 分類
        categories, activeCategories, archivedCategories, catModal, openCatAdd, openCatEdit, openCatDuplicate, saveCategory, archiveCategory, restoreCategory, moveCategory,
        // パッケージ分類
        packageGroups, selectedPackageGroupId, currentPackageGroup, activePackageGroups, archivedPackageGroups, selectPackageGroup, pkgGroupModal, openPkgGroupAdd, openPkgGroupEdit, openPkgGroupDuplicate, savePackageGroup, archivePackageGroup, restorePackageGroup, movePackageGroup,
        // パッケージ
        packages, activePackages, archivedPackages, pkgModal, openPkgAdd, openPkgEdit, openPkgDuplicate, savePackage, archivePackage, restorePackage, movePackage, packageDimensions, onPackageFileChange,
        // スペック分類
        specGroups, selectedSpecGroupId, activeSpecGroups, archivedSpecGroups, currentSpecGroup, specGroupModal, openSgAdd, openSgEdit, openSgDuplicate, saveSpecGroup, closeSpecGroupModal, archiveSpecGroup, restoreSpecGroup, selectSpecGroup,
        isSpecGroupCategoryLinked, isSpecGroupPrimaryCategory, toggleSpecGroupCategory, toggleSpecGroupPrimaryCategory,
        memberEditor, unassignedSpecTypes, memberState, setMemberState, addSpecGroupMember, removeSpecGroupMember, moveSpecGroupMember, syncSpecGroupMembers, inlineDirty,
        templateModal, openTemplateAdd, openTemplateEdit, openTemplateDuplicate, addTemplateItem, removeTemplateItem, moveTemplateItem, saveTemplate, closeTemplateModal, archiveTemplate,
        // スペック項目
        specTypes, activeSpecTypes, archivedSpecTypes, stModal, openStAdd, openStEdit, openStDuplicate, saveSpecType, archiveSpecType, restoreSpecType, moveSpecType,
        renderSymbol,
    };
}
