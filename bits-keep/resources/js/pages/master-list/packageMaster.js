import { ref, reactive, computed } from 'vue';
import { api } from '../../api.js';

/**
 * パッケージ分類とパッケージ詳細の状態・取得・保存処理を束ねる。
 * 目的: マスタ管理 setup からパッケージ系責務を分離し、一覧とモーダルの状態を一箇所で管理する。
 * 入力: Vue の activeTab、通知関数、確認モーダル関数、共通ユーティリティ。
 * 出力: Blade から参照する reactive/ref、取得関数、追加/編集/複製/保存/アーカイブ関数。
 * 動作条件: /package-groups と /packages API が利用でき、編集時は呼び出し元で権限表示を制御する。
 * 副作用: API 通信、toast 表示、選択中パッケージ分類、モーダル開閉、ファイル入力状態を更新する。
 */
export function usePackageMaster({
    activeTab,
    toastSuccess,
    toastError,
    openConfirm,
    closeModalWithConfirm,
    clone,
    splitActive,
    splitArchived,
    nextSortOrder,
    copyName,
    fetchError,
}) {
    // パッケージ詳細
    const packageGroups = ref([]);
    const selectedPackageGroupId = ref(null);
    const activePackageGroups = computed({
        get: () => splitActive(packageGroups.value),
        set: (items) => { packageGroups.value = [...items, ...splitArchived(packageGroups.value)]; },
    });
    const archivedPackageGroups = computed(() => splitArchived(packageGroups.value));
    const currentPackageGroup = computed(() => packageGroups.value.find((group) => Number(group.id) === Number(selectedPackageGroupId.value)) ?? null);
    // 目的: マスタ管理画面のensure Selected Package Groupを扱う。機能: 入力値を検証・整形し、画面または計算処理へ渡す。入力: 関数シグネチャの値。出力: 処理結果またはなし。動作条件: マスタ管理画面の初期化後に呼び出す。副作用: Vue状態、localStorage、DOM、HTTP通信のいずれかを更新する場合がある。
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

    // 目的: マスタ管理画面のfetch Package Groupsを扱う。機能: 入力値を検証・整形し、画面または計算処理へ渡す。入力: 関数シグネチャの値。出力: 処理結果またはなし。動作条件: マスタ管理画面の初期化後に呼び出す。副作用: Vue状態、localStorage、DOM、HTTP通信のいずれかを更新する場合がある。
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

    // 目的: マスタ管理画面のopen Pkg Group Addを扱う。機能: 入力値を検証・整形し、画面または計算処理へ渡す。入力: 関数シグネチャの値。出力: 処理結果またはなし。動作条件: マスタ管理画面の初期化後に呼び出す。副作用: Vue状態、localStorage、DOM、HTTP通信のいずれかを更新する場合がある。
    const openPkgGroupAdd = () => {
        const form = { name: '', description: '', sort_order: nextSortOrder(packageGroups.value) };
        pkgGroupSnapshot.value = clone(form);
        Object.assign(pkgGroupModal, { open: true, isEdit: false, editId: null, form });
    };
    // 目的: マスタ管理画面のopen Pkg Group Editを扱う。機能: 入力値を検証・整形し、画面または計算処理へ渡す。入力: 関数シグネチャの値。出力: 処理結果またはなし。動作条件: マスタ管理画面の初期化後に呼び出す。副作用: Vue状態、localStorage、DOM、HTTP通信のいずれかを更新する場合がある。
    const openPkgGroupEdit = (group) => {
        const form = { name: group.name, description: group.description ?? '', sort_order: group.sort_order ?? 0 };
        pkgGroupSnapshot.value = clone(form);
        Object.assign(pkgGroupModal, { open: true, isEdit: true, editId: group.id, form });
    };
    // 目的: マスタ管理画面のopen Pkg Group Duplicateを扱う。機能: 入力値を検証・整形し、画面または計算処理へ渡す。入力: 関数シグネチャの値。出力: 処理結果またはなし。動作条件: マスタ管理画面の初期化後に呼び出す。副作用: Vue状態、localStorage、DOM、HTTP通信のいずれかを更新する場合がある。
    const openPkgGroupDuplicate = (group) => {
        const form = { name: copyName(group.name), description: group.description ?? '', sort_order: nextSortOrder(packageGroups.value) };
        pkgGroupSnapshot.value = clone(form);
        Object.assign(pkgGroupModal, { open: true, isEdit: false, editId: null, form });
    };

    // 目的: マスタ管理画面のsave Package Groupを扱う。機能: 入力値を検証・整形し、画面または計算処理へ渡す。入力: 関数シグネチャの値。出力: 処理結果またはなし。動作条件: マスタ管理画面の初期化後に呼び出す。副作用: Vue状態、localStorage、DOM、HTTP通信のいずれかを更新する場合がある。
    const savePackageGroup = async () => {
        try {
            if (pkgGroupModal.isEdit) await api.put(`/package-groups/${pkgGroupModal.editId}`, pkgGroupModal.form);
            else await api.post('/package-groups', pkgGroupModal.form);
            toastSuccess('保存しました'); pkgGroupModal.open = false; pkgGroupSnapshot.value = clone(pkgGroupModal.form); await fetchPackageGroups();
        } catch (e) { toastError(e.message); }
    };
    // 目的: マスタ管理画面のclose Pkg Group Modalを扱う。機能: 入力値を検証・整形し、画面または計算処理へ渡す。入力: 関数シグネチャの値。出力: 処理結果またはなし。動作条件: マスタ管理画面の初期化後に呼び出す。副作用: Vue状態、localStorage、DOM、HTTP通信のいずれかを更新する場合がある。
    const closePkgGroupModal = () => closeModalWithConfirm(pkgGroupModal, pkgGroupSnapshot.value);

    // 目的: マスタ管理画面のarchive Package Groupを扱う。機能: 入力値を検証・整形し、画面または計算処理へ渡す。入力: 関数シグネチャの値。出力: 処理結果またはなし。動作条件: マスタ管理画面の初期化後に呼び出す。副作用: Vue状態、localStorage、DOM、HTTP通信のいずれかを更新する場合がある。
    const archivePackageGroup = (group) => openConfirm({
        title: 'パッケージ分類をアーカイブしますか？',
        message: `「${group.name}」をアーカイブします。\n使用件数: ${group.usage_count ?? 0}件`,
        actionLabel: 'アーカイブする',
        onConfirm: async () => {
            try { await api.delete(`/package-groups/${group.id}`); await fetchPackageGroups(); toastSuccess('アーカイブしました'); }
            catch (e) { toastError(e.message); }
        },
    });
    // 目的: マスタ管理画面のrestore Package Groupを扱う。機能: 入力値を検証・整形し、画面または計算処理へ渡す。入力: 関数シグネチャの値。出力: 処理結果またはなし。動作条件: マスタ管理画面の初期化後に呼び出す。副作用: Vue状態、localStorage、DOM、HTTP通信のいずれかを更新する場合がある。
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
    // 目的: マスタ管理画面のmove Package Groupを扱う。機能: 入力値を検証・整形し、画面または計算処理へ渡す。入力: 関数シグネチャの値。出力: 処理結果またはなし。動作条件: マスタ管理画面の初期化後に呼び出す。副作用: Vue状態、localStorage、DOM、HTTP通信のいずれかを更新する場合がある。
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

    // 目的/機能: パッケージ詳細モーダルのフォーム初期値を作る。入力: 上書き値。出力: 保存用フォーム。動作条件: 分類選択中なら分類IDを初期設定。副作用: なし。
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

    // 目的: マスタ管理画面のpackage Dimensionsを扱う。機能: 入力値を検証・整形し、画面または計算処理へ渡す。入力: 関数シグネチャの値。出力: 処理結果またはなし。動作条件: マスタ管理画面の初期化後に呼び出す。副作用: Vue状態、localStorage、DOM、HTTP通信のいずれかを更新する場合がある。
    const packageDimensions = (p) => {
        const values = [p.size_x, p.size_y, p.size_z]
            .map((value) => value === null || value === undefined || value === '' ? '' : Number(value).toLocaleString(undefined, { maximumFractionDigits: 4 }))
            .filter(Boolean);
        return values.length > 0 ? `${values.join(' x ')} mm` : '-';
    };

    // 目的: マスタ管理画面のon Package File Changeを扱う。機能: 入力値を検証・整形し、画面または計算処理へ渡す。入力: 関数シグネチャの値。出力: 処理結果またはなし。動作条件: マスタ管理画面の初期化後に呼び出す。副作用: Vue状態、localStorage、DOM、HTTP通信のいずれかを更新する場合がある。
    const onPackageFileChange = (field, event) => {
        pkgModal.form[field] = event.target.files?.[0] ?? null;
    };

    // 目的/機能: パッケージ詳細フォームをファイル添付可能な送信形式へ変換する。入力: pkgModal.form。出力: FormData。動作条件: 画像/PDFは選択時だけ添付。副作用: なし。
    const packageFormData = () => {
        const form = new FormData();
        ['package_group_id', 'name', 'description', 'size_x', 'size_y', 'size_z', 'sort_order'].forEach((key) => {
            form.append(key, pkgModal.form[key] ?? '');
        });
        if (pkgModal.form.image) form.append('image', pkgModal.form.image);
        if (pkgModal.form.pdf) form.append('pdf', pkgModal.form.pdf);
        return form;
    };

    // 目的: マスタ管理画面のfetch Packagesを扱う。機能: 入力値を検証・整形し、画面または計算処理へ渡す。入力: 関数シグネチャの値。出力: 処理結果またはなし。動作条件: マスタ管理画面の初期化後に呼び出す。副作用: Vue状態、localStorage、DOM、HTTP通信のいずれかを更新する場合がある。
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
        catch { fetchError.value = 'パッケージ詳細の取得に失敗しました。再試行してください。'; toastError('パッケージ詳細の取得に失敗しました'); }
    };

    // 目的: マスタ管理画面のselect Package Groupを扱う。機能: 入力値を検証・整形し、画面または計算処理へ渡す。入力: 関数シグネチャの値。出力: 処理結果またはなし。動作条件: マスタ管理画面の初期化後に呼び出す。副作用: Vue状態、localStorage、DOM、HTTP通信のいずれかを更新する場合がある。
    const selectPackageGroup = async (group) => {
        selectedPackageGroupId.value = group?.id ?? null;
        packages.value = [];
        await fetchPackages();
    };

    // 目的: マスタ管理画面のopen Pkg Addを扱う。機能: 入力値を検証・整形し、画面または計算処理へ渡す。入力: 関数シグネチャの値。出力: 処理結果またはなし。動作条件: マスタ管理画面の初期化後に呼び出す。副作用: Vue状態、localStorage、DOM、HTTP通信のいずれかを更新する場合がある。
    const openPkgAdd = () => {
        if (!selectedPackageGroupId.value) {
            toastError('先にパッケージ分類を選択してください');
            return;
        }
        const form = packageForm();
        pkgSnapshot.value = clone(form);
        Object.assign(pkgModal, { open: true, isEdit: false, editId: null, form });
    };
    // 目的: マスタ管理画面のopen Pkg Editを扱う。機能: 入力値を検証・整形し、画面または計算処理へ渡す。入力: 関数シグネチャの値。出力: 処理結果またはなし。動作条件: マスタ管理画面の初期化後に呼び出す。副作用: Vue状態、localStorage、DOM、HTTP通信のいずれかを更新する場合がある。
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
    // 目的: マスタ管理画面のopen Pkg Duplicateを扱う。機能: 入力値を検証・整形し、画面または計算処理へ渡す。入力: 関数シグネチャの値。出力: 処理結果またはなし。動作条件: マスタ管理画面の初期化後に呼び出す。副作用: Vue状態、localStorage、DOM、HTTP通信のいずれかを更新する場合がある。
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

    // 目的/機能: パッケージ詳細を新規/更新APIへ保存する。入力: pkgModal.form。出力: なし。動作条件: package_group_id が設定済み。副作用: upload API通信、分類選択、一覧再取得、toast、モーダル終了。
    const savePackage = async () => {
        try {
            if (pkgModal.isEdit) await api.uploadPut(`/packages/${pkgModal.editId}`, packageFormData());
            else await api.upload('/packages', packageFormData());
            if (pkgModal.form.package_group_id) selectedPackageGroupId.value = pkgModal.form.package_group_id;
            toastSuccess('保存しました'); pkgModal.open = false; pkgSnapshot.value = clone(pkgModal.form); await fetchPackageGroups(); await fetchPackages();
        } catch (e) { toastError(e.message); }
    };
    // 目的: マスタ管理画面のclose Pkg Modalを扱う。機能: 入力値を検証・整形し、画面または計算処理へ渡す。入力: 関数シグネチャの値。出力: 処理結果またはなし。動作条件: マスタ管理画面の初期化後に呼び出す。副作用: Vue状態、localStorage、DOM、HTTP通信のいずれかを更新する場合がある。
    const closePkgModal = () => closeModalWithConfirm(pkgModal, pkgSnapshot.value);

    // 目的: マスタ管理画面のarchive Packageを扱う。機能: 入力値を検証・整形し、画面または計算処理へ渡す。入力: 関数シグネチャの値。出力: 処理結果またはなし。動作条件: マスタ管理画面の初期化後に呼び出す。副作用: Vue状態、localStorage、DOM、HTTP通信のいずれかを更新する場合がある。
    const archivePackage = (p) => openConfirm({
        title: 'パッケージ詳細をアーカイブしますか？',
        message: `「${p.name}」をアーカイブします。\n使用件数: ${p.usage_count ?? 0}件`,
        actionLabel: 'アーカイブする',
        onConfirm: async () => {
            try { await api.delete(`/packages/${p.id}`); await fetchPackageGroups(); await fetchPackages(); toastSuccess('アーカイブしました'); }
            catch (e) { toastError(e.message); }
        },
    });
    // 目的: マスタ管理画面のrestore Packageを扱う。機能: 入力値を検証・整形し、画面または計算処理へ渡す。入力: 関数シグネチャの値。出力: 処理結果またはなし。動作条件: マスタ管理画面の初期化後に呼び出す。副作用: Vue状態、localStorage、DOM、HTTP通信のいずれかを更新する場合がある。
    const restorePackage = (p) => openConfirm({
        title: 'パッケージ詳細を復元しますか？',
        message: `「${p.name}」を復元します。`,
        actionLabel: '復元する',
        actionClass: 'border-emerald-400 text-emerald-700 hover:bg-emerald-50',
        onConfirm: async () => {
            try { await api.post(`/packages/${p.id}/restore`); await fetchPackageGroups(); await fetchPackages(); toastSuccess('復元しました'); }
            catch (e) { toastError(e.message); }
        },
    });
    // 目的: マスタ管理画面のmove Packageを扱う。機能: 入力値を検証・整形し、画面または計算処理へ渡す。入力: 関数シグネチャの値。出力: 処理結果またはなし。動作条件: マスタ管理画面の初期化後に呼び出す。副作用: Vue状態、localStorage、DOM、HTTP通信のいずれかを更新する場合がある。
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


    return {
        packageGroups,
        selectedPackageGroupId,
        activePackageGroups,
        archivedPackageGroups,
        currentPackageGroup,
        ensureSelectedPackageGroup,
        pkgGroupSnapshot,
        pkgGroupModal,
        fetchPackageGroups,
        openPkgGroupAdd,
        openPkgGroupEdit,
        openPkgGroupDuplicate,
        savePackageGroup,
        closePkgGroupModal,
        archivePackageGroup,
        restorePackageGroup,
        movePackageGroup,
        packages,
        activePackages,
        archivedPackages,
        pkgSnapshot,
        pkgModal,
        packageForm,
        packageDimensions,
        onPackageFileChange,
        packageFormData,
        fetchPackages,
        selectPackageGroup,
        openPkgAdd,
        openPkgEdit,
        openPkgDuplicate,
        savePackage,
        closePkgModal,
        archivePackage,
        restorePackage,
        movePackage,
    };
}
