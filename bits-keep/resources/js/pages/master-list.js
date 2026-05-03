/**
 * 公開 setup: マスタ管理ページ（SCR-009）の画面状態と操作関数を Vue へ公開する。
 * 目的/機能: タブ別マスタの取得、編集モーダル、並び替え、候補同期を統合する。
 * 入力: #app の data 属性、URL の tab/group_id、各 API から返るマスタデータ。
 * 出力: Blade テンプレートが参照する ref/computed/reactive と操作関数。
 * 動作条件: Laravel 側で権限 data 属性と CSRF が埋め込まれ、API が認証済みで呼べること。
 * 副作用: API 通信、URL 履歴更新、toast 表示、未保存確認、モーダル状態更新を行う。
 */
import { ref, reactive, computed, onMounted, watch } from 'vue';
import { api } from '../api.js';
import { useToast } from '../composables/useToast.js';
import { useNavigationConfirm } from '../composables/useNavigationConfirm.js';
import { useConfirmModal } from '../composables/useConfirmModal.js';
import { renderSymbol } from '../utils/specValue.js';
import { usePackageMaster } from './master-list/packageMaster.js';
import { useSpecTypeMaster } from './master-list/specTypeMaster.js';
import { createMasterReorderDnd, createPackageDnd, createCandidateMemberDnd } from './master-list/dnd.js';
import {
    clone,
    closeModalWithConfirm as confirmDirtyModalClose,
    copyName,
    nextSortOrder,
    normalizeTab,
    same,
    specGroupCandidateCounts,
    specGroupIdFromUrl,
    specGroupSeriesModeLabel,
    specGroupSidebarMeta as buildSpecGroupSidebarMeta,
    specGroupSpecTypes,
    splitActive,
    splitArchived,
    syncSpecGroupToUrl as syncSpecGroupSelectionToUrl,
    syncTabToUrl,
    tabFromUrl,
} from './master-list/pageHelpers.js';

// 目的: マスタ管理ページのVue状態と操作を組み立てる。機能: タブ、CRUD、並び替え、候補同期を各partialへ公開する。入力: #app data属性とURL状態。出力: テンプレートから参照する状態/関数。動作条件: 認証済み画面でAPIとCSRFが利用できること。副作用: API通信、URL履歴、toast、モーダル状態を更新する。
export default function setup() {
    const { toasts, toastSuccess, toastError } = useToast();
    const { ask } = useConfirmModal();
    const modalDirty = ref(false);
    const inlineDirty = ref(false);
    const dirty = computed(() => modalDirty.value || inlineDirty.value);
    useNavigationConfirm(dirty, '未保存の変更があります。このまま画面を離れてもよいですか？');
    const appEl  = document.getElementById('app');
    const activeTab = ref(tabFromUrl(appEl));
    const requestedSpecGroupId = ref(specGroupIdFromUrl());
    const canEdit = appEl?.dataset?.canEdit === '1';
    const isAdmin = appEl?.dataset?.isAdmin === '1';
    // 目的: マスタ管理画面のclose Modal With Confirmを扱う。機能: 入力値を検証・整形し、画面または計算処理へ渡す。入力: 関数シグネチャの値。出力: 処理結果またはなし。動作条件: マスタ管理画面の初期化後に呼び出す。副作用: Vue状態、localStorage、DOM、HTTP通信のいずれかを更新する場合がある。
    const closeModalWithConfirm = async (modal, snapshot) => {
        await confirmDirtyModalClose(ask, modal, snapshot);
    };
    const fetchError = ref('');
    // ドラッグ&ドロップ並び替え
    const dragSrc = ref(null);
    const dragTarget = ref(null);
    // 汎用確認モーダル
    const confirmModal = reactive({ open: false, title: '', message: '', actionLabel: '', actionClass: '', onConfirm: null });
    // 目的: マスタ管理画面のopen Confirmを扱う。機能: 入力値を検証・整形し、画面または計算処理へ渡す。入力: 関数シグネチャの値。出力: 処理結果またはなし。動作条件: マスタ管理画面の初期化後に呼び出す。副作用: Vue状態、localStorage、DOM、HTTP通信のいずれかを更新する場合がある。
    const openConfirm = ({ title, message, actionLabel, actionClass = 'border-red-400 text-red-600 hover:bg-red-50', onConfirm }) => {
        Object.assign(confirmModal, { open: true, title, message, actionLabel, actionClass, onConfirm });
    };
    // 目的: マスタ管理画面のdo Confirmを扱う。機能: 入力値を検証・整形し、画面または計算処理へ渡す。入力: 関数シグネチャの値。出力: 処理結果またはなし。動作条件: マスタ管理画面の初期化後に呼び出す。副作用: Vue状態、localStorage、DOM、HTTP通信のいずれかを更新する場合がある。
    const doConfirm = async () => {
        confirmModal.open = false;
        await confirmModal.onConfirm?.();
    };
    const packageMaster = usePackageMaster({
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
    });
    const {
        packageGroups,
        selectedPackageGroupId,
        activePackageGroups,
        archivedPackageGroups,
        currentPackageGroup,
        pkgGroupModal,
        pkgGroupSnapshot,
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
        pkgModal,
        pkgSnapshot,
        packageDimensions,
        onPackageFileChange,
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
    } = packageMaster;
    const specGroups = ref([]);
    const selectedSpecGroupId = ref(null);
    const specGroupDetailLoadingId = ref(null);
    const specGroupMemberSaving = ref(false);
    const specGroupDetailLoading = computed(() => Number(specGroupDetailLoadingId.value) === Number(selectedSpecGroupId.value));
    const activeSpecGroups = computed({
        get: () => splitActive(specGroups.value),
        set: (items) => { specGroups.value = [...items, ...splitArchived(specGroups.value)]; },
    });
    const archivedSpecGroups = computed(() => splitArchived(specGroups.value));
    const currentSpecGroup = computed(() => specGroups.value.find((group) => Number(group.id) === Number(selectedSpecGroupId.value)) ?? null);
    const specTypeMaster = useSpecTypeMaster({
        selectedSpecGroupId,
        currentSpecGroup,
        fetchSpecGroups: (...args) => fetchSpecGroups(...args),
        fetchError,
        toastSuccess,
        toastError,
        openConfirm,
        closeModalWithConfirm,
        clone,
        splitActive,
        splitArchived,
        nextSortOrder,
        copyName,
    });
    const {
        specTypes,
        commonSpecTypes,
        specTypeOptions,
        isToleranceSpecType,
        isCommonSpecType,
        activeSpecTypes,
        archivedSpecTypes,
        activeCommonSpecTypes,
        archivedCommonSpecTypes,
        activeToleranceSpecTypes,
        archivedToleranceSpecTypes,
        activeSpecTypeOptions,
        stModal,
        stSnapshot,
        fetchSpecTypes,
        fetchCommonSpecTypes,
        fetchSpecTypeOptions,
        prefixOptionsFor,
        prefixPolicyHelp,
        stModalTitle,
        syncPrefixList,
        toleranceUnitOptions,
        toleranceUnit,
        toleranceInputFormat,
        toleranceAllowedUnits,
        openCommonSpecTypeAdd,
        openToleranceSpecTypeAdd,
        openLocalSpecTypeAdd,
        openCommonSpecTypeDuplicate,
        saveSpecType,
        closeStModal,
        specTypeGroups,
        specTypeOptionLabel,
        archiveSpecType,
        restoreSpecType,
        openStAdd,
        openStEdit,
        openStDuplicate,
    } = specTypeMaster;
    // ── 部品分類 / 候補スペック詳細 / 入力テンプレート ─
    const currentSpecGroupCounts = computed(() => specGroupCandidateCounts(currentSpecGroup.value));
    // 目的: マスタ管理画面のspec Group Sidebar Metaを扱う。機能: 入力値を検証・整形し、画面または計算処理へ渡す。入力: 関数シグネチャの値。出力: 処理結果またはなし。動作条件: マスタ管理画面の初期化後に呼び出す。副作用: Vue状態、localStorage、DOM、HTTP通信のいずれかを更新する場合がある。
    const specGroupSidebarMeta = (group) => buildSpecGroupSidebarMeta(group, activeTab.value);
    const specGroupSnapshot = ref(null);
    const specGroupModal = reactive({
        open: false, isEdit: false, editId: null,
        form: { name: '', description: '', sort_order: 0, series_management_mode: 'single' },
    });
    const memberSnapshot = ref(null);
    const candidateSettingSnapshot = ref(null);
    const candidateSettingModal = reactive({
        open: false,
        index: null,
        form: { state: 'recommended', default_profile: 'typ', default_unit: '', note: '' },
    });
    const candidateSettingMember = computed(() => currentSpecGroup.value?.spec_types?.[candidateSettingModal.index] ?? null);
    const candidateAddModal = reactive({
        open: false,
        mode: 'local',
    });
    const templateSnapshot = ref(null);
    const templateModal = reactive({
        open: false, isEdit: false, editId: null,
        form: { spec_group_id: '', name: '', description: '', sort_order: 0, items: [] },
    });
    const selectedTemplateSpecGroup = computed(() =>
        specGroups.value.find((group) => Number(group.id) === Number(templateModal.form.spec_group_id)) ?? null
    );
    const templateSpecGroupLoading = computed(() =>
        !!templateModal.form.spec_group_id
        && Number(specGroupDetailLoadingId.value) === Number(templateModal.form.spec_group_id)
    );
    const templateCandidateSpecTypeIds = computed(() =>
        new Set(specGroupSpecTypes(selectedTemplateSpecGroup.value).map((item) => Number(item.id)))
    );
    const templateSpecTypeOptions = computed(() => splitActive(specGroupSpecTypes(selectedTemplateSpecGroup.value)));
    // 目的: マスタ管理画面のtemplate Spec Type Options For Itemを扱う。機能: 入力値を検証・整形し、画面または計算処理へ渡す。入力: 関数シグネチャの値。出力: 処理結果またはなし。動作条件: マスタ管理画面の初期化後に呼び出す。副作用: Vue状態、localStorage、DOM、HTTP通信のいずれかを更新する場合がある。
    const templateSpecTypeOptionsForItem = (item = {}) => {
        const selectedId = Number(item?.spec_type_id ?? 0);
        const candidates = [...templateSpecTypeOptions.value];
        if (selectedId && !candidates.some((specType) => Number(specType.id) === selectedId)) {
            const selected = item?.spec_type
                ?? item?.specType
                ?? specTypeOptions.value.find((specType) => Number(specType.id) === selectedId)
                ?? null;
            if (selected) candidates.unshift(selected);
        }
        const seen = new Set;
        return candidates.filter((specType) => {
            const id = Number(specType.id);
            if (!id || seen.has(id)) return false;
            seen.add(id);
            return true;
        });
    };
    // 目的: マスタ管理画面のtemplate Spec Type Option Labelを扱う。機能: 入力値を検証・整形し、画面または計算処理へ渡す。入力: 関数シグネチャの値。出力: 処理結果またはなし。動作条件: マスタ管理画面の初期化後に呼び出す。副作用: Vue状態、localStorage、DOM、HTTP通信のいずれかを更新する場合がある。
    const templateSpecTypeOptionLabel = (specType) => {
        const label = specTypeOptionLabel(specType);
        return templateCandidateSpecTypeIds.value.has(Number(specType?.id)) ? label : `${label}（候補外）`;
    };
    // 目的: マスタ管理画面のensure Template Spec Group Detailを扱う。機能: 入力値を検証・整形し、画面または計算処理へ渡す。入力: 関数シグネチャの値。出力: 処理結果またはなし。動作条件: マスタ管理画面の初期化後に呼び出す。副作用: Vue状態、localStorage、DOM、HTTP通信のいずれかを更新する場合がある。
    const ensureTemplateSpecGroupDetail = async () => {
        const groupId = Number(templateModal.form.spec_group_id);
        if (groupId) await fetchSpecGroupDetail(groupId);
    };
    // 目的: マスタ管理画面のhas Spec Group Detailを扱う。機能: 入力値を検証・整形し、画面または計算処理へ渡す。入力: 関数シグネチャの値。出力: 真偽値。動作条件: マスタ管理画面の初期化後に呼び出す。副作用: なし。
    const hasSpecGroupDetail = (group) => Array.isArray(group?.spec_types) && Array.isArray(group?.templates);
    // 目的: マスタ管理画面のnormalize Spec Groupを扱う。機能: 入力値を検証・整形し、画面または計算処理へ渡す。入力: 関数シグネチャの値。出力: 表示値、配列、オブジェクト、数値のいずれか。動作条件: マスタ管理画面の初期化後に呼び出す。副作用: なし。
    const normalizeSpecGroup = (group, existing = null) => {
        const next = { ...group };
        if (hasSpecGroupDetail(group)) {
            next._detail_loaded = true;
            return next;
        }
        if (existing?._detail_loaded) {
            next.spec_types = existing.spec_types ?? [];
            next.templates = existing.templates ?? [];
            next._detail_loaded = true;
        }
        return next;
    };
    // 目的: マスタ管理画面のsort Spec Groupsを扱う。機能: 入力値を検証・整形し、画面または計算処理へ渡す。入力: 関数シグネチャの値。出力: 表示値、配列、オブジェクト、数値のいずれか。動作条件: マスタ管理画面の初期化後に呼び出す。副作用: なし。
    const sortSpecGroups = () => {
        specGroups.value.sort((a, b) => {
            const order = (a.sort_order ?? 0) - (b.sort_order ?? 0);
            if (order !== 0) return order;
            return String(a.name ?? '').localeCompare(String(b.name ?? ''), 'ja');
        });
    };
    // 目的: マスタ管理画面のreplace Spec Groupを扱う。機能: 入力値を検証・整形し、画面または計算処理へ渡す。入力: 関数シグネチャの値。出力: 処理結果またはなし。動作条件: マスタ管理画面の初期化後に呼び出す。副作用: Vue状態、localStorage、DOM、HTTP通信のいずれかを更新する場合がある。
    const replaceSpecGroup = (group, { select = true } = {}) => {
        const index = specGroups.value.findIndex((item) => Number(item.id) === Number(group.id));
        const existing = index >= 0 ? specGroups.value[index] : null;
        const next = normalizeSpecGroup(group, existing);
        if (index >= 0) specGroups.value.splice(index, 1, next);
        else specGroups.value.push(next);
        sortSpecGroups();
        if (select) selectedSpecGroupId.value = next.id;
        return next;
    };
    // 目的: マスタ管理画面のensure Selected Spec Groupを扱う。機能: 入力値を検証・整形し、画面または計算処理へ渡す。入力: 関数シグネチャの値。出力: 処理結果またはなし。動作条件: マスタ管理画面の初期化後に呼び出す。副作用: Vue状態、localStorage、DOM、HTTP通信のいずれかを更新する場合がある。
    const ensureSelectedSpecGroup = () => {
        const activeGroups = activeSpecGroups.value;
        if (activeGroups.length === 0) {
            selectedSpecGroupId.value = null;
            requestedSpecGroupId.value = null;
            return;
        }
        if (requestedSpecGroupId.value && activeGroups.some((group) => Number(group.id) === Number(requestedSpecGroupId.value))) {
            selectedSpecGroupId.value = requestedSpecGroupId.value;
            requestedSpecGroupId.value = null;
            return;
        }
        requestedSpecGroupId.value = null;
        if (!activeGroups.some((group) => Number(group.id) === Number(selectedSpecGroupId.value))) {
            selectedSpecGroupId.value = activeGroups[0].id;
        }
    };
    // 目的: マスタ管理画面のfetch Spec Group Detailを扱う。機能: 入力値を検証・整形し、画面または計算処理へ渡す。入力: 関数シグネチャの値。出力: 処理結果またはなし。動作条件: マスタ管理画面の初期化後に呼び出す。副作用: Vue状態、localStorage、DOM、HTTP通信のいずれかを更新する場合がある。
    const fetchSpecGroupDetail = async (groupId, { force = false } = {}) => {
        const id = Number(groupId);
        if (!id) return null;
        const current = specGroups.value.find((group) => Number(group.id) === id);
        if (!force && hasSpecGroupDetail(current)) {
            syncMemberSnapshot(current);
            return current;
        }
        specGroupDetailLoadingId.value = id;
        try {
            const r = await api.get(`/spec-groups/${id}`);
            const detail = replaceSpecGroup(r.data, { select: false });
            if (Number(selectedSpecGroupId.value) === id) syncMemberSnapshot(detail);
            return detail;
        } catch {
            fetchError.value = '部品分類詳細の取得に失敗しました。再試行してください。';
            toastError('部品分類詳細の取得に失敗しました');
            return null;
        } finally {
            if (Number(specGroupDetailLoadingId.value) === id) specGroupDetailLoadingId.value = null;
        }
    };
    // 目的: マスタ管理画面のfetch Spec Groupsを扱う。機能: 入力値を検証・整形し、画面または計算処理へ渡す。入力: 関数シグネチャの値。出力: 処理結果またはなし。動作条件: マスタ管理画面の初期化後に呼び出す。副作用: Vue状態、localStorage、DOM、HTTP通信のいずれかを更新する場合がある。
    const fetchSpecGroups = async ({ forceDetail = false } = {}) => {
        fetchError.value = '';
        try {
            const previous = new Map(specGroups.value.map((group) => [Number(group.id), group]));
            const r = await api.get('/spec-groups?include_archived=1');
            specGroups.value = (r.data ?? []).map((group) => normalizeSpecGroup(group, previous.get(Number(group.id))));
            ensureSelectedSpecGroup();
            if (selectedSpecGroupId.value) await fetchSpecGroupDetail(selectedSpecGroupId.value, { force: forceDetail });
            else syncMemberSnapshot(null);
        } catch {
            fetchError.value = '部品分類の取得に失敗しました。再試行してください。';
            toastError('部品分類の取得に失敗しました');
        }
    };
    // 目的: マスタ管理画面のselect Spec Groupを扱う。機能: 入力値を検証・整形し、画面または計算処理へ渡す。入力: 関数シグネチャの値。出力: 処理結果またはなし。動作条件: マスタ管理画面の初期化後に呼び出す。副作用: Vue状態、localStorage、DOM、HTTP通信のいずれかを更新する場合がある。
    const selectSpecGroup = async (group) => {
        if (Number(selectedSpecGroupId.value) === Number(group?.id)) {
            await fetchSpecGroupDetail(group?.id);
            return;
        }
        if (!await confirmDiscardUnsaved()) return;
        selectedSpecGroupId.value = group?.id ?? null;
        syncSpecGroupSelectionToUrl(activeTab, selectedSpecGroupId.value);
        await fetchSpecGroupDetail(selectedSpecGroupId.value);
        if (activeTab.value === 'spec-types') await fetchSpecTypes();
    };
    // 目的: マスタ管理画面のspec Group Formを扱う。機能: 入力値を検証・整形し、画面または計算処理へ渡す。入力: 関数シグネチャの値。出力: 処理結果またはなし。動作条件: マスタ管理画面の初期化後に呼び出す。副作用: Vue状態、localStorage、DOM、HTTP通信のいずれかを更新する場合がある。
    const specGroupForm = (overrides = {}) => ({
        name: '',
        description: '',
        sort_order: nextSortOrder(specGroups.value),
        series_management_mode: 'single',
        ...overrides,
    });
    // 目的: マスタ管理画面のopen Sg Addを扱う。機能: 入力値を検証・整形し、画面または計算処理へ渡す。入力: 関数シグネチャの値。出力: 処理結果またはなし。動作条件: マスタ管理画面の初期化後に呼び出す。副作用: Vue状態、localStorage、DOM、HTTP通信のいずれかを更新する場合がある。
    const openSgAdd = () => {
        const form = specGroupForm();
        specGroupSnapshot.value = clone(form);
        Object.assign(specGroupModal, { open: true, isEdit: false, editId: null, form });
    };
    // 目的: マスタ管理画面のopen Sg Editを扱う。機能: 入力値を検証・整形し、画面または計算処理へ渡す。入力: 関数シグネチャの値。出力: 処理結果またはなし。動作条件: マスタ管理画面の初期化後に呼び出す。副作用: Vue状態、localStorage、DOM、HTTP通信のいずれかを更新する場合がある。
    const openSgEdit = (group) => {
        const form = specGroupForm({
            name: group.name,
            description: group.description ?? '',
            sort_order: group.sort_order ?? 0,
            series_management_mode: group.series_management_mode ?? 'single',
        });
        specGroupSnapshot.value = clone(form);
        Object.assign(specGroupModal, { open: true, isEdit: true, editId: group.id, form });
    };
    // 目的: マスタ管理画面のopen Sg Duplicateを扱う。機能: 入力値を検証・整形し、画面または計算処理へ渡す。入力: 関数シグネチャの値。出力: 処理結果またはなし。動作条件: マスタ管理画面の初期化後に呼び出す。副作用: Vue状態、localStorage、DOM、HTTP通信のいずれかを更新する場合がある。
    const openSgDuplicate = (group) => {
        const form = specGroupForm({
            name: copyName(group.name),
            description: group.description ?? '',
            series_management_mode: group.series_management_mode ?? 'single',
        });
        specGroupSnapshot.value = clone(form);
        Object.assign(specGroupModal, { open: true, isEdit: false, editId: null, form });
    };
    // 目的/機能: 部品分類フォームを保存する。入力: specGroupModal.form。出力: なし。動作条件: 管理者操作でモーダルが開いていること。副作用: API通信、一覧更新、toast、モーダル終了。
    const saveSpecGroup = async () => {
        try {
            const payload = {
                name: specGroupModal.form.name,
                description: specGroupModal.form.description ?? '',
                sort_order: specGroupModal.form.sort_order ?? 0,
                series_management_mode: specGroupModal.form.series_management_mode ?? 'single',
            };
            const res = specGroupModal.isEdit
                ? await api.put(`/spec-groups/${specGroupModal.editId}`, payload)
                : await api.post('/spec-groups', payload);
            replaceSpecGroup(res.data);
            toastSuccess('保存しました');
            specGroupModal.open = false;
            specGroupSnapshot.value = clone(specGroupModal.form);
        } catch (e) { toastError(e.message); }
    };
    // 目的: マスタ管理画面のclose Spec Group Modalを扱う。機能: 入力値を検証・整形し、画面または計算処理へ渡す。入力: 関数シグネチャの値。出力: 処理結果またはなし。動作条件: マスタ管理画面の初期化後に呼び出す。副作用: Vue状態、localStorage、DOM、HTTP通信のいずれかを更新する場合がある。
    const closeSpecGroupModal = () => closeModalWithConfirm(specGroupModal, specGroupSnapshot.value);
    // 目的: マスタ管理画面のarchive Spec Groupを扱う。機能: 入力値を検証・整形し、画面または計算処理へ渡す。入力: 関数シグネチャの値。出力: 処理結果またはなし。動作条件: マスタ管理画面の初期化後に呼び出す。副作用: Vue状態、localStorage、DOM、HTTP通信のいずれかを更新する場合がある。
    const archiveSpecGroup = (group) => openConfirm({
        title: '部品分類をアーカイブしますか？',
        message: `「${group.name}」をアーカイブします。\n入力候補: ${group.usage_count ?? 0}件 / テンプレート: ${group.template_count ?? 0}件`,
        actionLabel: 'アーカイブする',
        onConfirm: async () => {
            try { await api.delete(`/spec-groups/${group.id}`); await fetchSpecGroups(); toastSuccess('アーカイブしました'); }
            catch (e) { toastError(e.message); }
        },
    });
    // 目的: マスタ管理画面のrestore Spec Groupを扱う。機能: 入力値を検証・整形し、画面または計算処理へ渡す。入力: 関数シグネチャの値。出力: 処理結果またはなし。動作条件: マスタ管理画面の初期化後に呼び出す。副作用: Vue状態、localStorage、DOM、HTTP通信のいずれかを更新する場合がある。
    const restoreSpecGroup = (group) => openConfirm({
        title: '部品分類を復元しますか？',
        message: `「${group.name}」を復元します。`,
        actionLabel: '復元する',
        actionClass: 'border-emerald-400 text-emerald-700 hover:bg-emerald-50',
        onConfirm: async () => {
            try { const res = await api.post(`/spec-groups/${group.id}/restore`); replaceSpecGroup(res.data); toastSuccess('復元しました'); }
            catch (e) { toastError(e.message); }
        },
    });
    const assignedSpecTypeIds = computed(() => new Set((currentSpecGroup.value?.spec_types ?? []).map((item) => Number(item.id))));
    const linkedCommonSpecTypeIds = computed(() => new Set((currentSpecGroup.value?.spec_types ?? [])
        .filter(isCommonSpecType)
        .map((item) => Number(item.id))));
    // 目的: マスタ管理画面のis Common Spec Linkedを扱う。機能: 入力値を検証・整形し、画面または計算処理へ渡す。入力: 関数シグネチャの値。出力: 真偽値。動作条件: マスタ管理画面の初期化後に呼び出す。副作用: なし。
    const isCommonSpecLinked = (item) => linkedCommonSpecTypeIds.value.has(Number(item.id));
    // 目的: マスタ管理画面のmember Stateを扱う。機能: 入力値を検証・整形し、画面または計算処理へ渡す。入力: 関数シグネチャの値。出力: 処理結果またはなし。動作条件: マスタ管理画面の初期化後に呼び出す。副作用: Vue状態、localStorage、DOM、HTTP通信のいずれかを更新する場合がある。
    const memberState = (member) => member?.pivot?.is_required ? 'required' : (member?.pivot?.is_recommended ? 'recommended' : 'optional');
    // 目的: マスタ管理画面のmember State Labelを扱う。機能: 入力値を検証・整形し、画面または計算処理へ渡す。入力: 関数シグネチャの値。出力: 処理結果またはなし。動作条件: マスタ管理画面の初期化後に呼び出す。副作用: Vue状態、localStorage、DOM、HTTP通信のいずれかを更新する場合がある。
    const memberStateLabel = (member) => ({ required: '必須', recommended: '推奨', optional: '任意' })[memberState(member)] ?? '任意';
    // 目的: マスタ管理画面のdefault Profile Labelを扱う。機能: 入力値を検証・整形し、画面または計算処理へ渡す。入力: 関数シグネチャの値。出力: 表示値、配列、オブジェクト、数値のいずれか。動作条件: マスタ管理画面の初期化後に呼び出す。副作用: なし。
    const defaultProfileLabel = (profile) => ({
        typ: 'TYP',
        range: 'MIN..MAX',
        max_only: 'MAX',
        min_only: 'MIN',
        triple: 'MIN/TYP/MAX',
    })[profile] ?? '未指定';
    // 目的: マスタ管理画面のmember Default Labelを扱う。機能: 入力値を検証・整形し、画面または計算処理へ渡す。入力: 関数シグネチャの値。出力: 処理結果またはなし。動作条件: マスタ管理画面の初期化後に呼び出す。副作用: Vue状態、localStorage、DOM、HTTP通信のいずれかを更新する場合がある。
    const memberDefaultLabel = (member) => {
        const profile = defaultProfileLabel(member?.pivot?.default_profile);
        const unit = member?.pivot?.default_unit;
        return unit ? `${profile} / ${unit}` : profile;
    };
    // 目的: マスタ管理画面のcandidate Member Type Labelを扱う。機能: 入力値を検証・整形し、画面または計算処理へ渡す。入力: 関数シグネチャの値。出力: 真偽値。動作条件: マスタ管理画面の初期化後に呼び出す。副作用: Vue状態、localStorage、DOM、HTTP通信のいずれかを更新する場合がある。
    const candidateMemberTypeLabel = (member) => {
        if (isToleranceSpecType(member)) return '許容差';
        return isCommonSpecType(member) ? '共通' : '個別';
    };
    // 目的: マスタ管理画面のcandidate Member Type Titleを扱う。機能: 入力値を検証・整形し、画面または計算処理へ渡す。入力: 関数シグネチャの値。出力: 真偽値。動作条件: マスタ管理画面の初期化後に呼び出す。副作用: Vue状態、localStorage、DOM、HTTP通信のいずれかを更新する場合がある。
    const candidateMemberTypeTitle = (member) => {
        if (isToleranceSpecType(member)) return '許容差スペック詳細';
        if (isCommonSpecType(member)) return '共通スペック詳細';
        return '左で選んだ部品分類に持たせるスペック詳細';
    };
    // 目的: マスタ管理画面のdefault Candidate Setting Formを扱う。機能: 入力値を検証・整形し、画面または計算処理へ渡す。入力: 関数シグネチャの値。出力: 表示値、配列、オブジェクト、数値のいずれか。動作条件: マスタ管理画面の初期化後に呼び出す。副作用: なし。
    const defaultCandidateSettingForm = () => ({ state: 'recommended', default_profile: 'typ', default_unit: '', note: '' });
    // 目的: マスタ管理画面のmember Payloadを扱う。機能: 入力値を検証・整形し、画面または計算処理へ渡す。入力: 関数シグネチャの値。出力: 処理結果またはなし。動作条件: マスタ管理画面の初期化後に呼び出す。副作用: Vue状態、localStorage、DOM、HTTP通信のいずれかを更新する場合がある。
    const memberPayload = (members = []) => members.map((member, index) => ({
        spec_type_id: Number(member.id),
        sort_order: (index + 1) * 10,
        is_required: !!member.pivot?.is_required,
        is_recommended: !!member.pivot?.is_recommended,
        default_profile: member.pivot?.default_profile || '',
        default_unit: member.pivot?.default_unit || '',
        note: member.pivot?.note || '',
    }));
    // 目的: マスタ管理画面のhas Member Changesを扱う。機能: 入力値を検証・整形し、画面または計算処理へ渡す。入力: 関数シグネチャの値。出力: 真偽値。動作条件: マスタ管理画面の初期化後に呼び出す。副作用: なし。
    const hasMemberChanges = () => {
        if (activeTab.value !== 'spec-candidates' || !currentSpecGroup.value || memberSnapshot.value === null) return false;
        return !same(memberPayload(currentSpecGroup.value.spec_types ?? []), memberPayload(memberSnapshot.value ?? []));
    };
    // 目的: マスタ管理画面のrefresh Inline Dirtyを扱う。機能: 入力値を検証・整形し、画面または計算処理へ渡す。入力: 関数シグネチャの値。出力: 処理結果またはなし。動作条件: マスタ管理画面の初期化後に呼び出す。副作用: Vue状態、localStorage、DOM、HTTP通信のいずれかを更新する場合がある。
    const refreshInlineDirty = () => {
        inlineDirty.value = hasMemberChanges();
    };
    // 目的: マスタ管理画面のsync Member Snapshotを扱う。機能: 入力値を検証・整形し、画面または計算処理へ渡す。入力: 関数シグネチャの値。出力: 処理結果またはなし。動作条件: マスタ管理画面の初期化後に呼び出す。副作用: Vue状態、localStorage、DOM、HTTP通信のいずれかを更新する場合がある。
    const syncMemberSnapshot = (group = currentSpecGroup.value) => {
        memberSnapshot.value = clone(group?.spec_types ?? []);
        refreshInlineDirty();
    };
    // 目的: マスタ管理画面のdiscard Inline Editsを扱う。機能: 入力値を検証・整形し、画面または計算処理へ渡す。入力: 関数シグネチャの値。出力: 処理結果またはなし。動作条件: マスタ管理画面の初期化後に呼び出す。副作用: Vue状態、localStorage、DOM、HTTP通信のいずれかを更新する場合がある。
    const discardInlineEdits = () => {
        if (currentSpecGroup.value && memberSnapshot.value !== null) {
            currentSpecGroup.value.spec_types = clone(memberSnapshot.value);
        }
        inlineDirty.value = false;
    };
    // 目的: マスタ管理画面のmember Display Nameを扱う。機能: 入力値を検証・整形し、画面または計算処理へ渡す。入力: 関数シグネチャの値。出力: 処理結果またはなし。動作条件: マスタ管理画面の初期化後に呼び出す。副作用: Vue状態、localStorage、DOM、HTTP通信のいずれかを更新する場合がある。
    const memberDisplayName = (member) => member?.name_ja || member?.name || 'このスペック詳細';
    // 目的/機能: 候補一覧の局所変更を保存APIへ同期する。入力: 変更関数と成功文言。出力: 保存成否。動作条件: 部品分類選択中かつ保存中でないこと。副作用: 楽観更新、失敗時復元、toast。
    const persistSpecGroupMemberMutation = async (mutate, successMessage) => {
        if (!currentSpecGroup.value || specGroupMemberSaving.value) return false;
        const before = clone(currentSpecGroup.value.spec_types ?? []);
        const changed = mutate();
        if (changed === false) return false;
        refreshInlineDirty();
        const saved = await syncSpecGroupMembers(successMessage);
        if (!saved && currentSpecGroup.value) {
            currentSpecGroup.value.spec_types = before;
            refreshInlineDirty();
        }
        return saved;
    };
    // 目的: マスタ管理画面のadd Spec Type To Current Groupを扱う。機能: 入力値を検証・整形し、画面または計算処理へ渡す。入力: 関数シグネチャの値。出力: 処理結果またはなし。動作条件: マスタ管理画面の初期化後に呼び出す。副作用: Vue状態、localStorage、DOM、HTTP通信のいずれかを更新する場合がある。
    const addSpecTypeToCurrentGroup = (specType, overrides = {}) => {
        if (!currentSpecGroup.value || !specType) return false;
        const members = currentSpecGroup.value.spec_types ?? [];
        if (members.some((item) => Number(item.id) === Number(specType.id))) return false;
        members.push({
            ...clone(specType),
            pivot: {
                sort_order: (members.length + 1) * 10,
                is_required: false,
                is_recommended: true,
                default_profile: 'typ',
                default_unit: specType.tolerance_settings?.default_unit ?? specType.base_unit ?? specType.units?.[0]?.unit ?? '',
                note: '',
                ...overrides,
            },
        });
        currentSpecGroup.value.spec_types = members;
        refreshInlineDirty();
        return true;
    };
    const candidateAddTitle = computed(() => ({
        local: 'スペック詳細から追加',
        common: '共通スペック詳細から追加',
        tolerance: '許容差スペック詳細から追加',
    })[candidateAddModal.mode] ?? '候補を追加');
    const candidateAddOptions = computed(() => {
        const assigned = assignedSpecTypeIds.value;
        if (candidateAddModal.mode === 'common') {
            return activeCommonSpecTypes.value.filter((item) => !assigned.has(Number(item.id)));
        }
        if (candidateAddModal.mode === 'tolerance') {
            return activeToleranceSpecTypes.value.filter((item) => !assigned.has(Number(item.id)));
        }
        return activeSpecTypeOptions.value.filter((item) => {
            if (assigned.has(Number(item.id)) || isCommonSpecType(item) || isToleranceSpecType(item)) return false;
            return Number(item.owner_spec_group_id) === Number(selectedSpecGroupId.value);
        });
    });
    const candidateAddEmptyMessage = computed(() => ({
        local: 'この部品分類に追加できる未追加のスペック詳細はありません',
        common: '未追加の共通スペック詳細はありません',
        tolerance: '未追加の許容差スペック詳細はありません',
    })[candidateAddModal.mode] ?? '追加できる候補がありません');
    // 目的: マスタ管理画面のopen Candidate Add Modalを扱う。機能: 入力値を検証・整形し、画面または計算処理へ渡す。入力: 関数シグネチャの値。出力: 処理結果またはなし。動作条件: マスタ管理画面の初期化後に呼び出す。副作用: Vue状態、localStorage、DOM、HTTP通信のいずれかを更新する場合がある。
    const openCandidateAddModal = async (mode) => {
        if (specGroupMemberSaving.value) return;
        if (!currentSpecGroup.value) {
            toastError('先に部品分類を選択してください');
            return;
        }
        candidateAddModal.mode = mode;
        if (mode === 'local' && specTypeOptions.value.length === 0) await fetchSpecTypeOptions();
        if ((mode === 'common' || mode === 'tolerance') && commonSpecTypes.value.length === 0) await fetchCommonSpecTypes();
        candidateAddModal.open = true;
    };
    // 目的: マスタ管理画面のclose Candidate Add Modalを扱う。機能: 入力値を検証・整形し、画面または計算処理へ渡す。入力: 関数シグネチャの値。出力: 処理結果またはなし。動作条件: マスタ管理画面の初期化後に呼び出す。副作用: Vue状態、localStorage、DOM、HTTP通信のいずれかを更新する場合がある。
    const closeCandidateAddModal = () => {
        candidateAddModal.open = false;
    };
    // 目的: マスタ管理画面のadd Candidate From Optionを扱う。機能: 入力値を検証・整形し、画面または計算処理へ渡す。入力: 関数シグネチャの値。出力: 処理結果またはなし。動作条件: マスタ管理画面の初期化後に呼び出す。副作用: Vue状態、localStorage、DOM、HTTP通信のいずれかを更新する場合がある。
    const addCandidateFromOption = async (specType) => {
        const saved = await persistSpecGroupMemberMutation(
            () => addSpecTypeToCurrentGroup(specType),
            '候補に追加しました',
        );
        if (saved) candidateAddModal.open = false;
    };
    // 目的: マスタ管理画面のadd Common Spec To Current Groupを扱う。機能: 入力値を検証・整形し、画面または計算処理へ渡す。入力: 関数シグネチャの値。出力: 処理結果またはなし。動作条件: マスタ管理画面の初期化後に呼び出す。副作用: Vue状態、localStorage、DOM、HTTP通信のいずれかを更新する場合がある。
    const addCommonSpecToCurrentGroup = (specType) => {
        if (!currentSpecGroup.value) {
            toastError('先に部品分類を選択してください');
            return false;
        }
        const members = currentSpecGroup.value.spec_types ?? [];
        if (members.some((item) => Number(item.id) === Number(specType.id))) return false;
        members.push({
            ...clone(specType),
            pivot: {
                sort_order: (members.length + 1) * 10,
                is_required: false,
                is_recommended: true,
                default_profile: 'typ',
                default_unit: specType.tolerance_settings?.default_unit ?? specType.base_unit ?? specType.units?.[0]?.unit ?? '',
                note: '',
            },
        });
        currentSpecGroup.value.spec_types = members;
        refreshInlineDirty();
        return true;
    };
    // 目的: マスタ管理画面のremove Spec Group Member By Idを扱う。機能: 入力値を検証・整形し、画面または計算処理へ渡す。入力: 関数シグネチャの値。出力: 処理結果またはなし。動作条件: マスタ管理画面の初期化後に呼び出す。副作用: Vue状態、localStorage、DOM、HTTP通信のいずれかを更新する場合がある。
    const removeSpecGroupMemberById = (specTypeId) => {
        const members = currentSpecGroup.value?.spec_types;
        const index = members?.findIndex((item) => Number(item.id) === Number(specTypeId)) ?? -1;
        if (!members || index < 0) return false;
        members.splice(index, 1);
        currentSpecGroup.value.spec_types = members;
        refreshInlineDirty();
        return true;
    };
    // 目的: マスタ管理画面のconfirm Remove Spec Group Memberを扱う。機能: 入力値を検証・整形し、画面または計算処理へ渡す。入力: 関数シグネチャの値。出力: 処理結果またはなし。動作条件: マスタ管理画面の初期化後に呼び出す。副作用: Vue状態、localStorage、DOM、HTTP通信のいずれかを更新する場合がある。
    const confirmRemoveSpecGroupMember = (memberOrIndex) => {
        if (!currentSpecGroup.value || specGroupMemberSaving.value) return;
        const member = typeof memberOrIndex === 'number'
            ? currentSpecGroup.value.spec_types?.[memberOrIndex]
            : memberOrIndex;
        if (!member) return;
        const groupId = currentSpecGroup.value.id;
        const groupName = currentSpecGroup.value.name;
        openConfirm({
            title: '候補から外しますか？',
            message: `「${memberDisplayName(member)}」を「${groupName}」の候補から外します。\nスペック詳細そのものは削除されません。`,
            actionLabel: '候補から外す',
            onConfirm: async () => {
                if (Number(currentSpecGroup.value?.id) !== Number(groupId)) return;
                await persistSpecGroupMemberMutation(
                    () => removeSpecGroupMemberById(member.id),
                    '候補から外しました',
                );
            },
        });
    };
    // 目的: マスタ管理画面のtoggle Common Spec For Current Groupを扱う。機能: 入力値を検証・整形し、画面または計算処理へ渡す。入力: 関数シグネチャの値。出力: 処理結果またはなし。動作条件: マスタ管理画面の初期化後に呼び出す。副作用: なし。
    const toggleCommonSpecForCurrentGroup = async (specType) => {
        if (!currentSpecGroup.value || specGroupMemberSaving.value) return;
        const wasLinked = isCommonSpecLinked(specType);
        if (wasLinked) {
            confirmRemoveSpecGroupMember(specType);
            return;
        }
        await persistSpecGroupMemberMutation(
            () => addCommonSpecToCurrentGroup(specType),
            '候補に入れました',
        );
    };
    // 目的: マスタ管理画面のopen Candidate Setting Editを扱う。機能: 入力値を検証・整形し、画面または計算処理へ渡す。入力: 関数シグネチャの値。出力: 処理結果またはなし。動作条件: マスタ管理画面の初期化後に呼び出す。副作用: Vue状態、localStorage、DOM、HTTP通信のいずれかを更新する場合がある。
    const openCandidateSettingEdit = (member, index) => {
        const form = {
            state: memberState(member),
            default_profile: member?.pivot?.default_profile || '',
            default_unit: member?.pivot?.default_unit || '',
            note: member?.pivot?.note || '',
        };
        candidateSettingSnapshot.value = clone(form);
        Object.assign(candidateSettingModal, { open: true, index, form });
    };
    // 目的: マスタ管理画面のclose Candidate Setting Modalを扱う。機能: 入力値を検証・整形し、画面または計算処理へ渡す。入力: 関数シグネチャの値。出力: 処理結果またはなし。動作条件: マスタ管理画面の初期化後に呼び出す。副作用: Vue状態、localStorage、DOM、HTTP通信のいずれかを更新する場合がある。
    const closeCandidateSettingModal = () => closeModalWithConfirm(candidateSettingModal, candidateSettingSnapshot.value);
    // 目的: マスタ管理画面のsave Candidate Settingを扱う。機能: 入力値を検証・整形し、画面または計算処理へ渡す。入力: 関数シグネチャの値。出力: 処理結果またはなし。動作条件: マスタ管理画面の初期化後に呼び出す。副作用: Vue状態、localStorage、DOM、HTTP通信のいずれかを更新する場合がある。
    const saveCandidateSetting = async () => {
        const index = candidateSettingModal.index;
        const member = currentSpecGroup.value?.spec_types?.[index];
        if (!member) {
            candidateSettingModal.open = false;
            return;
        }
        const form = clone(candidateSettingModal.form);
        const saved = await persistSpecGroupMemberMutation(() => {
            const target = currentSpecGroup.value?.spec_types?.[index];
            if (!target || Number(target.id) !== Number(member.id)) return false;
            target.pivot = {
                ...(target.pivot ?? {}),
                is_required: form.state === 'required',
                is_recommended: form.state !== 'optional',
                default_profile: form.default_profile || null,
                default_unit: form.default_unit || '',
                note: form.note || '',
            };
            return true;
        }, '候補設定を保存しました');
        if (saved) {
            candidateSettingSnapshot.value = clone(form);
            candidateSettingModal.open = false;
            modalDirty.value = false;
        }
    };
    // 目的/機能: 候補スペック詳細の順序/必須/既定値を一括保存する。入力: 成功文言。出力: 保存成否。動作条件: 候補詳細が読込済みで保存中でないこと。副作用: API通信、部品分類詳細更新、関連一覧再取得。
    const syncSpecGroupMembers = async (successMessage = '候補スペック詳細を保存しました') => {
        if (!currentSpecGroup.value || specGroupMemberSaving.value) return false;
        specGroupMemberSaving.value = true;
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
            await fetchSpecTypes();
            await fetchCommonSpecTypes();
            toastSuccess(successMessage);
            return true;
        } catch (e) {
            toastError(e.message);
            return false;
        } finally {
            specGroupMemberSaving.value = false;
        }
    };
    // 目的: マスタ管理画面のtemplate Itemを扱う。機能: 入力値を検証・整形し、画面または計算処理へ渡す。入力: 関数シグネチャの値。出力: 表示値、配列、オブジェクト、数値のいずれか。動作条件: マスタ管理画面の初期化後に呼び出す。副作用: なし。
    const templateItem = (overrides = {}) => ({
        spec_type_id: '',
        spec_type: null,
        default_profile: 'typ',
        default_unit: '',
        is_required: false,
        note: '',
        ...overrides,
    });
    // 目的/機能: 入力テンプレート編集フォームの初期値を作る。入力: 上書き値。出力: 保存用フォーム。動作条件: 選択中部品分類がある場合は初期紐付けする。副作用: なし。
    const templateForm = (overrides = {}) => ({
        spec_group_id: currentSpecGroup.value?.id ?? '',
        name: '',
        description: '',
        sort_order: ((currentSpecGroup.value?.templates ?? []).length + 1) * 10,
        items: [],
        ...overrides,
    });
    // 目的: マスタ管理画面のopen Template Addを扱う。機能: 入力値を検証・整形し、画面または計算処理へ渡す。入力: 関数シグネチャの値。出力: 処理結果またはなし。動作条件: マスタ管理画面の初期化後に呼び出す。副作用: Vue状態、localStorage、DOM、HTTP通信のいずれかを更新する場合がある。
    const openTemplateAdd = async () => {
        if (!currentSpecGroup.value) {
            toastError('先に部品分類を選択してください');
            return;
        }
        const form = templateForm({ items: [templateItem()] });
        templateSnapshot.value = clone(form);
        Object.assign(templateModal, { open: true, isEdit: false, editId: null, form });
        await ensureTemplateSpecGroupDetail();
    };
    // 目的: マスタ管理画面のopen Template Editを扱う。機能: 入力値を検証・整形し、画面または計算処理へ渡す。入力: 関数シグネチャの値。出力: 処理結果またはなし。動作条件: マスタ管理画面の初期化後に呼び出す。副作用: Vue状態、localStorage、DOM、HTTP通信のいずれかを更新する場合がある。
    const openTemplateEdit = async (template) => {
        const form = templateForm({
            spec_group_id: template.spec_group_id ?? currentSpecGroup.value?.id ?? '',
            name: template.name,
            description: template.description ?? '',
            sort_order: template.sort_order ?? 0,
            items: (template.items ?? []).map((item) => templateItem({
                spec_type_id: item.spec_type_id,
                spec_type: item.spec_type ?? item.specType ?? null,
                default_profile: item.default_profile || 'typ',
                default_unit: item.default_unit ?? '',
                is_required: !!item.is_required,
                note: item.note ?? '',
            })),
        });
        templateSnapshot.value = clone(form);
        Object.assign(templateModal, { open: true, isEdit: true, editId: template.id, form });
        await ensureTemplateSpecGroupDetail();
    };
    // 目的: マスタ管理画面のopen Template Duplicateを扱う。機能: 入力値を検証・整形し、画面または計算処理へ渡す。入力: 関数シグネチャの値。出力: 処理結果またはなし。動作条件: マスタ管理画面の初期化後に呼び出す。副作用: Vue状態、localStorage、DOM、HTTP通信のいずれかを更新する場合がある。
    const openTemplateDuplicate = async (template) => {
        const form = templateForm({
            spec_group_id: template.spec_group_id ?? currentSpecGroup.value?.id ?? '',
            name: copyName(template.name),
            description: template.description ?? '',
            items: (template.items ?? []).map((item) => templateItem({
                spec_type_id: item.spec_type_id,
                spec_type: item.spec_type ?? item.specType ?? null,
                default_profile: item.default_profile || 'typ',
                default_unit: item.default_unit ?? '',
                is_required: !!item.is_required,
                note: item.note ?? '',
            })),
        });
        templateSnapshot.value = clone(form);
        Object.assign(templateModal, { open: true, isEdit: false, editId: null, form });
        await ensureTemplateSpecGroupDetail();
    };
    // 目的: マスタ管理画面のadd Template Itemを扱う。機能: 入力値を検証・整形し、画面または計算処理へ渡す。入力: 関数シグネチャの値。出力: 処理結果またはなし。動作条件: マスタ管理画面の初期化後に呼び出す。副作用: Vue状態、localStorage、DOM、HTTP通信のいずれかを更新する場合がある。
    const addTemplateItem = () => templateModal.form.items.push(templateItem());
    // 目的: マスタ管理画面のremove Template Itemを扱う。機能: 入力値を検証・整形し、画面または計算処理へ渡す。入力: 関数シグネチャの値。出力: 処理結果またはなし。動作条件: マスタ管理画面の初期化後に呼び出す。副作用: Vue状態、localStorage、DOM、HTTP通信のいずれかを更新する場合がある。
    const removeTemplateItem = (index) => templateModal.form.items.splice(index, 1);
    // 目的: マスタ管理画面のmove Template Itemを扱う。機能: 入力値を検証・整形し、画面または計算処理へ渡す。入力: 関数シグネチャの値。出力: 処理結果またはなし。動作条件: マスタ管理画面の初期化後に呼び出す。副作用: Vue状態、localStorage、DOM、HTTP通信のいずれかを更新する場合がある。
    const moveTemplateItem = (index, delta) => {
        const target = index + delta;
        const items = templateModal.form.items;
        if (target < 0 || target >= items.length) return;
        [items[index], items[target]] = [items[target], items[index]];
    };
    const templateItemDnD = {
        start: (i) => { dragSrc.value = i; dragTarget.value = i; },
        over: (e, i) => { e.preventDefault(); dragTarget.value = i; },
        end: () => { dragSrc.value = null; dragTarget.value = null; },
        drop: (i) => {
            const from = dragSrc.value;
            dragSrc.value = null; dragTarget.value = null;
            if (from === null || from === i) return;
            const items = templateModal.form.items;
            const [moved] = items.splice(from, 1);
            items.splice(i, 0, moved);
        },
    };
    // 目的/機能: 入力テンプレートと項目行を保存する。入力: templateModal.form。出力: なし。動作条件: spec_type_id のある行だけ保存対象。副作用: API通信、部品分類詳細再取得、toast、モーダル終了。
    const saveTemplate = async () => {
        try {
            const payload = {
                ...templateModal.form,
                items: templateModal.form.items
                    .filter((item) => item.spec_type_id)
                    .map((item, index) => ({
                        spec_type_id: item.spec_type_id,
                        sort_order: (index + 1) * 10,
                        default_profile: item.default_profile || null,
                        default_unit: item.default_unit || null,
                        is_required: !!item.is_required,
                        note: item.note || null,
                    })),
            };
            if (templateModal.isEdit) await api.put(`/spec-templates/${templateModal.editId}`, payload);
            else await api.post('/spec-templates', payload);
            toastSuccess('入力テンプレートを保存しました');
            templateModal.open = false;
            templateSnapshot.value = clone(templateModal.form);
            await fetchSpecGroups({ forceDetail: true });
        } catch (e) { toastError(e.message); }
    };
    // 目的: マスタ管理画面のclose Template Modalを扱う。機能: 入力値を検証・整形し、画面または計算処理へ渡す。入力: 関数シグネチャの値。出力: 処理結果またはなし。動作条件: マスタ管理画面の初期化後に呼び出す。副作用: Vue状態、localStorage、DOM、HTTP通信のいずれかを更新する場合がある。
    const closeTemplateModal = () => closeModalWithConfirm(templateModal, templateSnapshot.value);
    // 目的: マスタ管理画面のarchive Templateを扱う。機能: 入力値を検証・整形し、画面または計算処理へ渡す。入力: 関数シグネチャの値。出力: 処理結果またはなし。動作条件: マスタ管理画面の初期化後に呼び出す。副作用: Vue状態、localStorage、DOM、HTTP通信のいずれかを更新する場合がある。
    const archiveTemplate = (template) => openConfirm({
        title: '入力テンプレートをアーカイブしますか？',
        message: `「${template.name}」をアーカイブします。`,
        actionLabel: 'アーカイブする',
        onConfirm: async () => {
            try { await api.delete(`/spec-templates/${template.id}`); await fetchSpecGroups({ forceDetail: true }); toastSuccess('アーカイブしました'); }
            catch (e) { toastError(e.message); }
        },
    });
    // タブ切り替え時のフェッチ
    // 目的: マスタ管理画面のretry Active Tabを扱う。機能: 入力値を検証・整形し、画面または計算処理へ渡す。入力: 関数シグネチャの値。出力: 処理結果またはなし。動作条件: マスタ管理画面の初期化後に呼び出す。副作用: Vue状態、localStorage、DOM、HTTP通信のいずれかを更新する場合がある。
    const retryActiveTab = async () => {
        if (!await confirmDiscardUnsaved()) return;
        if (activeTab.value === 'part-categories') return fetchSpecGroups({ forceDetail: true });
        if (activeTab.value === 'package-groups') return fetchPackageGroups();
        if (activeTab.value === 'packages') return fetchPackages();
        if (activeTab.value === 'spec-types') {
            await fetchSpecGroups({ forceDetail: true });
            return fetchSpecTypes();
        }
        if (['spec-candidates', 'spec-templates', 'common-spec-types', 'tolerance-spec-types'].includes(activeTab.value)) {
            await fetchCommonSpecTypes();
            await fetchSpecTypeOptions();
            return fetchSpecGroups({ forceDetail: true });
        }
        return fetchSpecTypes();
    };
    // 目的: マスタ管理画面のclose All Editor Modalsを扱う。機能: 入力値を検証・整形し、画面または計算処理へ渡す。入力: 関数シグネチャの値。出力: 処理結果またはなし。動作条件: マスタ管理画面の初期化後に呼び出す。副作用: Vue状態、localStorage、DOM、HTTP通信のいずれかを更新する場合がある。
    const closeAllEditorModals = () => {
        pkgGroupModal.open = false;
        pkgModal.open = false;
        stModal.open = false;
        specGroupModal.open = false;
        templateModal.open = false;
        candidateSettingModal.open = false;
        candidateAddModal.open = false;
    };
    // 目的: マスタ管理画面のconfirm Discard Unsavedを扱う。機能: 入力値を検証・整形し、画面または計算処理へ渡す。入力: 関数シグネチャの値。出力: 処理結果またはなし。動作条件: マスタ管理画面の初期化後に呼び出す。副作用: Vue状態、localStorage、DOM、HTTP通信のいずれかを更新する場合がある。
    const confirmDiscardUnsaved = async () => {
        if (!dirty.value) return true;
        const confirmed = await ask('未保存の変更があります。このまま移動すると編集内容は失われます。移動してもよいですか？');
        if (!confirmed) return false;
        discardInlineEdits();
        closeAllEditorModals();
        modalDirty.value = false;
        return true;
    };
    // 目的/機能: 表示タブを切り替え必要データを取得する。入力: タブIDとURL更新指定。出力: なし。動作条件: 未保存変更は破棄確認を通す。副作用: URL履歴、activeTab、API取得、未保存状態更新。
    const switchTab = async (tab, { updateUrl = true, replaceUrl = false } = {}) => {
        const nextTab = normalizeTab(tab);
        const changed = activeTab.value !== nextTab;
        if (changed && !await confirmDiscardUnsaved()) {
            if (!updateUrl) syncTabToUrl(activeTab.value, { replace: true });
            return;
        }
        activeTab.value = nextTab;
        if (updateUrl && (changed || replaceUrl)) syncTabToUrl(nextTab, { replace: replaceUrl });
        if (nextTab === 'part-categories' && specGroups.value.length === 0) fetchSpecGroups();
        else if (nextTab === 'package-groups' && packageGroups.value.length === 0) fetchPackageGroups();
        else if (nextTab === 'packages') {
            if (packageGroups.value.length === 0) fetchPackageGroups();
            fetchPackages();
        }
        else if (nextTab === 'spec-types') {
            await fetchSpecGroups();
            await fetchSpecTypes();
        }
        else if (['spec-candidates', 'spec-templates', 'common-spec-types', 'tolerance-spec-types'].includes(nextTab)) {
            if (commonSpecTypes.value.length === 0) fetchCommonSpecTypes();
            fetchSpecTypeOptions();
            fetchSpecGroups();
        }
    };
    onMounted(() => {
        // 初期タブのデータだけ取得し、URLにも現在タブを明示する
        switchTab(activeTab.value, { updateUrl: true, replaceUrl: true });
        window.addEventListener('popstate', () => {
            requestedSpecGroupId.value = specGroupIdFromUrl();
            switchTab(tabFromUrl(), { updateUrl: false });
        });
    });
    watch(() => pkgModal.form, (value) => {
        if (pkgModal.open) modalDirty.value = !same(value, pkgSnapshot.value);
    }, { deep: true });
    watch(() => pkgGroupModal.form, (value) => {
        if (pkgGroupModal.open) modalDirty.value = !same(value, pkgGroupSnapshot.value);
    }, { deep: true });
    watch(() => stModal.form, (value) => {
        if (stModal.open) modalDirty.value = !same(value, stSnapshot.value);
    }, { deep: true });
    watch(() => stModal.form.unit, () => {
        if (!stModal.open) return;
        syncPrefixList('suggest_prefixes');
        syncPrefixList('display_prefixes');
    });
    watch(() => specGroupModal.form, (value) => {
        if (specGroupModal.open) modalDirty.value = !same(value, specGroupSnapshot.value);
    }, { deep: true });
    watch(() => templateModal.form, (value) => {
        if (templateModal.open) modalDirty.value = !same(value, templateSnapshot.value);
    }, { deep: true });
    watch(() => templateModal.form.spec_group_id, async () => {
        if (!templateModal.open) return;
        await ensureTemplateSpecGroupDetail();
    });
    watch(() => candidateSettingModal.form, (value) => {
        if (candidateSettingModal.open) modalDirty.value = !same(value, candidateSettingSnapshot.value);
    }, { deep: true });
    watch([() => pkgGroupModal.open, () => pkgModal.open, () => stModal.open, () => specGroupModal.open, () => templateModal.open, () => candidateSettingModal.open], ([groupOpen, pkgOpen, stOpen, specGroupOpen, templateOpen, candidateOpen]) => {
        if (!groupOpen && !pkgOpen && !stOpen && !specGroupOpen && !templateOpen && !candidateOpen) modalDirty.value = false;
    });
    watch(() => currentSpecGroup.value?.id, () => {
        syncMemberSnapshot();
        refreshInlineDirty();
        if (activeTab.value === 'spec-types') fetchSpecTypes();
    });
    watch(() => currentSpecGroup.value?.spec_types, refreshInlineDirty, { deep: true });
    watch(() => activeTab.value, refreshInlineDirty);
    // DnDインスタンスはすべてのrefが揃ったここで生成する
    const pgDnD = createMasterReorderDnd({
        arr: activePackageGroups, buildPayload: (g) => ({ url: `/package-groups/${g.id}`, body: { name: g.name, description: g.description ?? '' } }),
        fetchFn: fetchPackageGroups, dragSrc, dragTarget, toastSuccess, toastError,
    });
    const pkgDnD = createPackageDnd({ activePackages, selectedPackageGroupId, fetchPackages, dragSrc, dragTarget, toastSuccess, toastError });
    const candidateMemberDnD = createCandidateMemberDnd({ currentSpecGroup, persistSpecGroupMemberMutation, dragSrc, dragTarget });
    const sgDnD = createMasterReorderDnd({
        arr: activeSpecGroups,
        buildPayload: (g) => ({
            url: `/spec-groups/${g.id}`,
            body: {
                name: g.name,
                description: g.description ?? '',
            },
        }),
        fetchFn: fetchSpecGroups,
        dragSrc,
        dragTarget,
        toastSuccess,
        toastError,
    });
    return {
        toasts, fetchError, activeTab, switchTab, retryActiveTab, canEdit, isAdmin, closePkgGroupModal, closePkgModal, closeStModal,
        confirmModal, doConfirm,
        dragSrc, dragTarget, pgDnD, pkgDnD, sgDnD,
        fetchPackageGroups, fetchPackages, fetchSpecTypes, fetchCommonSpecTypes, fetchSpecTypeOptions, fetchSpecGroups,
        // パッケージ分類
        packageGroups, selectedPackageGroupId, currentPackageGroup, activePackageGroups, archivedPackageGroups, selectPackageGroup, pkgGroupModal, openPkgGroupAdd, openPkgGroupEdit, openPkgGroupDuplicate, savePackageGroup, archivePackageGroup, restorePackageGroup, movePackageGroup,
        // パッケージ詳細
        packages, activePackages, archivedPackages, pkgModal, openPkgAdd, openPkgEdit, openPkgDuplicate, savePackage, archivePackage, restorePackage, movePackage, packageDimensions, onPackageFileChange,
        // 部品分類（スペック詳細の親）
        specGroups, selectedSpecGroupId, specGroupDetailLoading, specGroupMemberSaving, activeSpecGroups, archivedSpecGroups, currentSpecGroup, currentSpecGroupCounts, specGroupSidebarMeta, specGroupSeriesModeLabel, specGroupModal, openSgAdd, openSgEdit, openSgDuplicate, saveSpecGroup, closeSpecGroupModal, archiveSpecGroup, restoreSpecGroup, selectSpecGroup,
        memberState, memberStateLabel, memberDefaultLabel, candidateMemberTypeLabel, candidateMemberTypeTitle, candidateSettingModal, candidateSettingMember, closeCandidateSettingModal, openCandidateSettingEdit, saveCandidateSetting, confirmRemoveSpecGroupMember, candidateMemberDnD, syncSpecGroupMembers, inlineDirty,
        candidateAddModal, candidateAddTitle, candidateAddOptions, candidateAddEmptyMessage, openCandidateAddModal, closeCandidateAddModal, addCandidateFromOption,
        isCommonSpecType, isToleranceSpecType, activeCommonSpecTypes, archivedCommonSpecTypes, activeToleranceSpecTypes, archivedToleranceSpecTypes, isCommonSpecLinked, toggleCommonSpecForCurrentGroup, openCommonSpecTypeAdd, openToleranceSpecTypeAdd, openLocalSpecTypeAdd, openCommonSpecTypeDuplicate,
        toleranceUnitOptions, toleranceUnit, toleranceInputFormat, toleranceAllowedUnits,
        prefixOptionsFor, prefixPolicyHelp, stModalTitle, syncPrefixList,
        templateModal, selectedTemplateSpecGroup, templateSpecGroupLoading, templateSpecTypeOptions, templateSpecTypeOptionsForItem, templateSpecTypeOptionLabel, openTemplateAdd, openTemplateEdit, openTemplateDuplicate, addTemplateItem, removeTemplateItem, moveTemplateItem, templateItemDnD, saveTemplate, closeTemplateModal, archiveTemplate,
        // スペック詳細
        specTypes, activeSpecTypes, activeSpecTypeOptions, archivedSpecTypes, stModal, openStAdd, openStEdit, openStDuplicate, saveSpecType, archiveSpecType, restoreSpecType, specTypeGroups, specTypeOptionLabel,
        renderSymbol,
    };
}
