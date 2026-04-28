/**
 * マスタ管理ページ（SCR-009）
 * パッケージ分類 / パッケージ詳細 / 部品分類 / スペック詳細 の CRUD
 * ?tab=package-groups|packages|part-categories|spec-types|spec-candidates|common-spec-types|tolerance-spec-types|spec-templates で初期タブを切り替え可
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
    const tabIds = ['package-groups', 'packages', 'part-categories', 'spec-types', 'spec-candidates', 'common-spec-types', 'tolerance-spec-types', 'spec-templates'];
    const legacyTabMap = {
        categories: 'part-categories',
        'spec-groups': 'part-categories',
    };
    const normalizeTab = (tab) => {
        const normalized = legacyTabMap[tab] ?? tab;
        return tabIds.includes(normalized) ? normalized : 'package-groups';
    };
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

    // ── パッケージ詳細 ────────────────────────────────────
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
        catch { fetchError.value = 'パッケージ詳細の取得に失敗しました。再試行してください。'; toastError('パッケージ詳細の取得に失敗しました'); }
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
        title: 'パッケージ詳細をアーカイブしますか？',
        message: `「${p.name}」をアーカイブします。\n使用件数: ${p.usage_count ?? 0}件`,
        actionLabel: 'アーカイブする',
        onConfirm: async () => {
            try { await api.delete(`/packages/${p.id}`); await fetchPackageGroups(); await fetchPackages(); toastSuccess('アーカイブしました'); }
            catch (e) { toastError(e.message); }
        },
    });
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

    // ── スペック詳細 ──────────────────────────────────────
    const specTypes = ref([]);
    const commonSpecTypes = ref([]);
    const specTypeOptions = ref([]);
    const isToleranceSpecType = (item) => (item?.spec_kind ?? 'normal') === 'tolerance';
    const isNormalSpecType = (item) => !isToleranceSpecType(item);
    const isCommonSpecType = (item) => (item?.spec_scope ?? 'group_local') === 'common';
    const activeSpecTypes = computed(() => splitActive(specTypes.value).filter((item) => !isCommonSpecType(item) && isNormalSpecType(item)));
    const archivedSpecTypes = computed(() => splitArchived(specTypes.value).filter((item) => !isCommonSpecType(item) && isNormalSpecType(item)));
    const activeCommonSpecTypes = computed(() => splitActive(commonSpecTypes.value).filter(isNormalSpecType));
    const archivedCommonSpecTypes = computed(() => splitArchived(commonSpecTypes.value).filter(isNormalSpecType));
    const activeToleranceSpecTypes = computed(() => splitActive(commonSpecTypes.value).filter(isToleranceSpecType));
    const archivedToleranceSpecTypes = computed(() => splitArchived(commonSpecTypes.value).filter(isToleranceSpecType));
    const activeSpecTypeOptions = computed(() => splitActive(specTypeOptions.value));
    const stSnapshot = ref(null);
    const stModal = reactive({
        open: false, isEdit: false, editId: null,
        form: { name: '', name_ja: '', name_en: '', symbol: '', aliases_text: '', description: '', value_type: 'numeric', sort_order: 0, unit: '', suggest_prefixes: [], display_prefixes: [], spec_scope: 'group_local', owner_spec_group_id: '', spec_kind: 'normal', tolerance_settings: { default_mode: 'symmetric', default_unit: '%', allowed_units: ['%', 'ppm'], grade_options: [], grade_options_text: '' } }
    });

    const fetchSpecTypes = async () => {
        fetchError.value = '';
        if (!selectedSpecGroupId.value) {
            specTypes.value = [];
            return;
        }

        const params = new URLSearchParams({
            include_archived: '1',
            scope: 'group_local',
            kind: 'normal',
            owner_spec_group_id: String(selectedSpecGroupId.value),
        });
        try { const r = await api.get(`/spec-types?${params.toString()}`); specTypes.value = r.data; }
        catch { fetchError.value = 'スペック詳細の取得に失敗しました。再試行してください。'; toastError('スペック詳細の取得に失敗しました'); }
    };
    const fetchCommonSpecTypes = async () => {
        fetchError.value = '';
        try { const r = await api.get('/spec-types?include_archived=1&scope=common'); commonSpecTypes.value = r.data; }
        catch { fetchError.value = '共通スペック詳細の取得に失敗しました。再試行してください。'; toastError('共通スペック詳細の取得に失敗しました'); }
    };
    const fetchSpecTypeOptions = async () => {
        fetchError.value = '';
        try { const r = await api.get('/spec-types?summary=1'); specTypeOptions.value = r.data ?? []; }
        catch { fetchError.value = 'スペック詳細候補の取得に失敗しました。再試行してください。'; toastError('スペック詳細候補の取得に失敗しました'); }
    };
    const normalizePrefixes = (prefixes) => Array.isArray(prefixes)
        ? prefixes.map((prefix) => prefix == null ? '' : String(prefix))
        : [];

    const toleranceUnitOptions = [
        { value: '%', label: '%' },
        { value: 'ppm', label: 'ppm' },
        { value: 'pF', label: 'pF' },
        { value: 'ppm/℃', label: 'ppm/℃' },
        { value: 'code', label: 'コード' },
    ];
    const defaultToleranceUnits = () => ['%', 'ppm'];
    const normalizeToleranceUnits = (units, fallback = defaultToleranceUnits()) => {
        const normalized = Array.isArray(units)
            ? units.map((unit) => String(unit ?? '').trim()).filter(Boolean)
            : [];

        return normalized.length > 0 ? [...new Set(normalized)] : fallback;
    };
    const defaultGradeOptions = () => [
        { label: 'F', value: 1, unit: '%' },
        { label: 'G', value: 2, unit: '%' },
        { label: 'J', value: 5, unit: '%' },
        { label: 'K', value: 10, unit: '%' },
        { label: 'M', value: 20, unit: '%' },
    ];
    const formatGradeOptions = (options = []) => Array.isArray(options)
        ? options.map((option) => {
            const label = option?.label ?? option?.rank ?? '';
            if (!label) return '';
            const unit = option?.unit ?? '%';
            if (option?.plus !== undefined || option?.minus !== undefined) {
                return `${label}: +${option?.plus ?? ''}/-${option?.minus ?? ''}${unit}`;
            }
            if (option?.value !== undefined) return `${label}: ±${option.value}${unit}`;
            return `${label}:`;
        }).filter(Boolean).join('\n')
        : '';
    const parseGradeOptions = (text = '', fallbackUnit = '%') => String(text ?? '')
        .split(/\r?\n/u)
        .map((line) => line.trim())
        .filter(Boolean)
        .map((line) => {
            const match = line.match(/^([^:：\s]+)\s*[:：]\s*(.+)$/u);
            if (!match) return null;
            const label = match[1].trim();
            const rawValue = match[2].trim();
            const asymmetric = rawValue.match(/^\+?\s*([0-9]+(?:\.[0-9]+)?)\s*\/\s*-?\s*([0-9]+(?:\.[0-9]+)?)\s*(ppm\/℃|%|ppm|pF|code)?$/u);
            if (asymmetric) {
                return {
                    label,
                    plus: Number(asymmetric[1]),
                    minus: Number(asymmetric[2]),
                    unit: asymmetric[3] || fallbackUnit || '%',
                };
            }
            const symmetric = rawValue.match(/^±?\s*([0-9]+(?:\.[0-9]+)?)\s*(ppm\/℃|%|ppm|pF|code)?$/u);
            if (symmetric) {
                return {
                    label,
                    value: Number(symmetric[1]),
                    unit: symmetric[2] || fallbackUnit || '%',
                };
            }
            return { label, text: rawValue, unit: fallbackUnit || '%' };
        })
        .filter(Boolean);
    const defaultToleranceSettings = (overrides = {}) => {
        const defaultUnit = overrides.default_unit ?? overrides.unit ?? '%';
        const gradeOptions = Array.isArray(overrides.grade_options) ? overrides.grade_options : defaultGradeOptions();
        return {
            default_mode: overrides.default_mode ?? overrides.mode ?? overrides.input_format ?? 'symmetric',
            default_unit: defaultUnit,
            allowed_units: normalizeToleranceUnits(overrides.allowed_units),
            grade_options: gradeOptions,
            grade_options_text: overrides.grade_options_text ?? overrides.rank_definitions ?? formatGradeOptions(gradeOptions),
        };
    };
    const normalizeToleranceSettings = (settings = {}, fallbackUnit = '%') => {
        let source = settings ?? {};
        if (typeof source === 'string') {
            try { source = JSON.parse(source); }
            catch { source = {}; }
        }
        const defaultUnit = source?.default_unit ?? source?.unit ?? fallbackUnit ?? '%';
        const gradeOptions = Array.isArray(source?.grade_options)
            ? source.grade_options
            : parseGradeOptions(source?.grade_options_text ?? source?.rank_definitions ?? '', defaultUnit);
        return defaultToleranceSettings({
            default_mode: source?.default_mode ?? source?.mode ?? source?.input_format ?? 'symmetric',
            default_unit: defaultUnit,
            allowed_units: normalizeToleranceUnits(source?.allowed_units),
            grade_options: gradeOptions.length > 0 ? gradeOptions : defaultGradeOptions(),
            grade_options_text: source?.grade_options_text ?? source?.rank_definitions ?? formatGradeOptions(gradeOptions),
        });
    };
    const compactToleranceSettings = (settings = {}, fallbackUnit = '%') => {
        const normalized = normalizeToleranceSettings(settings, fallbackUnit);
        const gradeOptions = parseGradeOptions(normalized.grade_options_text, normalized.default_unit);
        return {
            default_mode: normalized.default_mode || 'symmetric',
            default_unit: normalized.default_unit || '%',
            allowed_units: normalizeToleranceUnits(normalized.allowed_units),
            grade_options: gradeOptions.length > 0 ? gradeOptions : normalized.grade_options,
        };
    };
    const specTypeForm = (overrides = {}) => ({
        name: '',
        name_ja: '',
        name_en: '',
        symbol: '',
        aliases_text: '',
        description: '',
        value_type: 'numeric',
        sort_order: nextSortOrder(specTypes.value),
        unit: '',
        suggest_prefixes: [],
        display_prefixes: [],
        spec_scope: 'group_local',
        owner_spec_group_id: '',
        spec_kind: 'normal',
        tolerance_settings: defaultToleranceSettings(),
        ...overrides,
    });
    const openStAdd = (overrides = {}) => {
        const form = specTypeForm(overrides);
        stSnapshot.value = clone(form);
        Object.assign(stModal, { open: true, isEdit: false, editId: null, form });
    };
    const openCommonSpecTypeAdd = () => openStAdd({ spec_scope: 'common', owner_spec_group_id: null });
    const openToleranceSpecTypeAdd = () => openStAdd({
        spec_scope: 'common',
        owner_spec_group_id: null,
        spec_kind: 'tolerance',
        value_type: 'numeric',
        unit: '%',
        tolerance_settings: defaultToleranceSettings(),
    });
    const openLocalSpecTypeAdd = () => {
        if (!currentSpecGroup.value) {
            toastError('先に部品分類を選択してください');
            return;
        }
        openStAdd({ spec_scope: 'group_local', owner_spec_group_id: currentSpecGroup.value.id });
    };
    const openStEdit = async (s) => {
        let detail = s;
        if (!Array.isArray(s.aliases) || !Array.isArray(s.units) || s.suggest_prefixes === undefined) {
            try {
                const r = await api.get(`/spec-types/${s.id}`);
                detail = r.data;
            } catch (e) {
                toastError(e.message);
                return;
            }
        }
        const form = {
            name: detail.name, name_ja: detail.name_ja ?? detail.name, name_en: detail.name_en ?? '', symbol: detail.symbol ?? '',
            aliases_text: (detail.aliases ?? []).map((alias) => alias.alias).join('\n'),
            description: detail.description ?? '',
            value_type: detail.value_type ?? 'numeric',
            sort_order: detail.sort_order ?? 0,
            unit: detail.units?.[0]?.unit ?? detail.base_unit ?? '',
            suggest_prefixes: normalizePrefixes(detail.suggest_prefixes),
            display_prefixes: normalizePrefixes(detail.display_prefixes),
            spec_scope: detail.spec_scope ?? 'group_local',
            owner_spec_group_id: detail.owner_spec_group_id ?? '',
            spec_kind: detail.spec_kind ?? 'normal',
            tolerance_settings: normalizeToleranceSettings(detail.tolerance_settings, detail.units?.[0]?.unit ?? detail.base_unit ?? '%'),
        };
        stSnapshot.value = clone(form);
        Object.assign(stModal, { open: true, isEdit: true, editId: detail.id, form });
    };
    const openStDuplicate = (s, overrides = {}) => {
        const form = {
            name: copyName(s.name), name_ja: copyName(s.name_ja ?? s.name), name_en: s.name_en ?? '', symbol: s.symbol ?? '',
            aliases_text: (s.aliases ?? []).map((alias) => alias.alias).join('\n'),
            description: s.description ?? '',
            value_type: s.value_type ?? 'numeric',
            sort_order: nextSortOrder(specTypes.value),
            unit: s.units?.[0]?.unit ?? s.base_unit ?? '',
            suggest_prefixes: normalizePrefixes(s.suggest_prefixes),
            display_prefixes: normalizePrefixes(s.display_prefixes),
            spec_scope: s.spec_scope ?? 'group_local',
            owner_spec_group_id: s.owner_spec_group_id ?? '',
            spec_kind: s.spec_kind ?? 'normal',
            tolerance_settings: normalizeToleranceSettings(s.tolerance_settings, s.units?.[0]?.unit ?? s.base_unit ?? '%'),
            ...overrides,
        };
        stSnapshot.value = clone(form);
        Object.assign(stModal, { open: true, isEdit: false, editId: null, form });
    };
    const openCommonSpecTypeDuplicate = (specType) => openStDuplicate(specType, { spec_scope: 'common', owner_spec_group_id: null });

    const saveSpecType = async () => {
        try {
            const suggestPrefixes = normalizePrefixes(stModal.form.suggest_prefixes);
            const displayPrefixes = normalizePrefixes(stModal.form.display_prefixes);
            const toleranceSettings = compactToleranceSettings(stModal.form.tolerance_settings, stModal.form.unit || '%');
            const isTolerance = stModal.form.spec_kind === 'tolerance';
            const payload = {
                ...stModal.form,
                name: stModal.form.name_ja || stModal.form.name,
                owner_spec_group_id: stModal.form.spec_scope === 'common' ? null : (stModal.form.owner_spec_group_id || null),
                value_type: isTolerance ? 'numeric' : stModal.form.value_type,
                unit: isTolerance ? (toleranceSettings.default_unit || '%') : stModal.form.unit,
                aliases: String(stModal.form.aliases_text ?? '')
                    .split(/\r?\n/u)
                    .map((alias) => ({ alias: alias.trim() }))
                    .filter((item) => item.alias),
                suggest_prefixes: !isTolerance && suggestPrefixes.length > 0 ? suggestPrefixes : null,
                display_prefixes: !isTolerance && displayPrefixes.length > 0 ? displayPrefixes : null,
                tolerance_settings: isTolerance ? toleranceSettings : null,
            };
            delete payload.aliases_text;
            delete payload.default_unit;
            if (stModal.isEdit) await api.put(`/spec-types/${stModal.editId}`, payload);
            else await api.post('/spec-types', payload);
            toastSuccess('保存しました');
            stModal.open = false;
            stSnapshot.value = clone(stModal.form);
            await fetchSpecTypes();
            await fetchCommonSpecTypes();
            if (specTypeOptions.value.length > 0) await fetchSpecTypeOptions();
            if (selectedSpecGroupId.value) await fetchSpecGroups({ forceDetail: true });
        } catch (e) { toastError(e.message); }
    };
    const closeStModal = () => closeModalWithConfirm(stModal, stSnapshot.value);
    const specTypeGroups = (item) => item?.spec_groups ?? item?.specGroups ?? [];
    const specTypeOptionLabel = (item) => {
        const name = item?.name_ja || item?.name || 'スペック詳細';
        const suffix = [item?.symbol, item?.name_en].filter(Boolean).join(' / ');
        const label = suffix ? `${name} (${suffix})` : name;
        return isToleranceSpecType(item) ? `${label} [許容差]` : label;
    };
    const toleranceSettingsFor = (item) => normalizeToleranceSettings(item?.tolerance_settings, item?.units?.[0]?.unit ?? item?.base_unit ?? '%');
    const toleranceUnit = (item) => toleranceSettingsFor(item).default_unit || '%';
    const toleranceInputFormat = (item) => ({
        symmetric: '± 対称',
        asymmetric: '+/- 非対称',
        grade: 'ランク',
    })[toleranceSettingsFor(item).default_mode] || toleranceSettingsFor(item).default_mode || '± 対称';
    const toleranceAllowedUnits = (item) => toleranceSettingsFor(item).allowed_units.join(', ');

    const archiveSpecType = (s) => openConfirm({
        title: 'スペック詳細をアーカイブしますか？',
        message: `「${s.name}」をアーカイブします。\n使用件数: ${s.usage_count ?? 0}件`,
        actionLabel: 'アーカイブする',
        onConfirm: async () => {
            try { await api.delete(`/spec-types/${s.id}`); await fetchSpecTypes(); await fetchCommonSpecTypes(); if (specTypeOptions.value.length > 0) await fetchSpecTypeOptions(); if (selectedSpecGroupId.value) await fetchSpecGroups({ forceDetail: true }); toastSuccess('アーカイブしました'); }
            catch (e) { toastError(e.message); }
        },
    });
    const restoreSpecType = (s) => openConfirm({
        title: 'スペック詳細を復元しますか？',
        message: `「${s.name}」を復元します。`,
        actionLabel: '復元する',
        actionClass: 'border-emerald-400 text-emerald-700 hover:bg-emerald-50',
        onConfirm: async () => {
            try { await api.post(`/spec-types/${s.id}/restore`); await fetchSpecTypes(); await fetchCommonSpecTypes(); if (specTypeOptions.value.length > 0) await fetchSpecTypeOptions(); if (selectedSpecGroupId.value) await fetchSpecGroups({ forceDetail: true }); toastSuccess('復元しました'); }
            catch (e) { toastError(e.message); }
        },
    });
    // ── 部品分類 / 候補スペック詳細 / 入力テンプレート ─
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
    const specGroupSnapshot = ref(null);
    const specGroupModal = reactive({
        open: false, isEdit: false, editId: null,
        form: { name: '', description: '', sort_order: 0 },
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

    const hasSpecGroupDetail = (group) => Array.isArray(group?.spec_types) && Array.isArray(group?.templates);
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
    const sortSpecGroups = () => {
        specGroups.value.sort((a, b) => {
            const order = (a.sort_order ?? 0) - (b.sort_order ?? 0);
            if (order !== 0) return order;
            return String(a.name ?? '').localeCompare(String(b.name ?? ''), 'ja');
        });
    };
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
    const selectSpecGroup = async (group) => {
        if (Number(selectedSpecGroupId.value) === Number(group?.id)) {
            await fetchSpecGroupDetail(group?.id);
            return;
        }
        if (!await confirmDiscardUnsaved()) return;
        selectedSpecGroupId.value = group?.id ?? null;
        await fetchSpecGroupDetail(selectedSpecGroupId.value);
        if (activeTab.value === 'spec-types') await fetchSpecTypes();
    };
    const specGroupForm = (overrides = {}) => ({
        name: '',
        description: '',
        sort_order: nextSortOrder(specGroups.value),
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
        });
        specGroupSnapshot.value = clone(form);
        Object.assign(specGroupModal, { open: true, isEdit: true, editId: group.id, form });
    };
    const openSgDuplicate = (group) => {
        const form = specGroupForm({
            name: copyName(group.name),
            description: group.description ?? '',
        });
        specGroupSnapshot.value = clone(form);
        Object.assign(specGroupModal, { open: true, isEdit: false, editId: null, form });
    };
    const saveSpecGroup = async () => {
        try {
            const payload = {
                name: specGroupModal.form.name,
                description: specGroupModal.form.description ?? '',
                sort_order: specGroupModal.form.sort_order ?? 0,
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
    const closeSpecGroupModal = () => closeModalWithConfirm(specGroupModal, specGroupSnapshot.value);
    const archiveSpecGroup = (group) => openConfirm({
        title: '部品分類をアーカイブしますか？',
        message: `「${group.name}」をアーカイブします。\n候補スペック詳細: ${group.usage_count ?? 0}件 / 入力テンプレート: ${group.template_count ?? 0}件`,
        actionLabel: 'アーカイブする',
        onConfirm: async () => {
            try { await api.delete(`/spec-groups/${group.id}`); await fetchSpecGroups(); toastSuccess('アーカイブしました'); }
            catch (e) { toastError(e.message); }
        },
    });
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
    const isCommonSpecLinked = (item) => linkedCommonSpecTypeIds.value.has(Number(item.id));
    const memberState = (member) => member?.pivot?.is_required ? 'required' : (member?.pivot?.is_recommended ? 'recommended' : 'optional');
    const memberStateLabel = (member) => ({ required: '必須', recommended: '推奨', optional: '任意' })[memberState(member)] ?? '任意';
    const defaultProfileLabel = (profile) => ({
        typ: 'typ',
        range: 'range',
        max_only: 'max',
        min_only: 'min',
        triple: 'min/typ/max',
    })[profile] ?? '未指定';
    const memberDefaultLabel = (member) => {
        const profile = defaultProfileLabel(member?.pivot?.default_profile);
        const unit = member?.pivot?.default_unit;
        return unit ? `${profile} / ${unit}` : profile;
    };
    const candidateMemberTypeLabel = (member) => {
        if (isToleranceSpecType(member)) return '許容差';
        return isCommonSpecType(member) ? '共通' : '主所属';
    };
    const defaultCandidateSettingForm = () => ({ state: 'recommended', default_profile: 'typ', default_unit: '', note: '' });
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
        if (activeTab.value !== 'spec-candidates' || !currentSpecGroup.value || memberSnapshot.value === null) return false;
        return !same(memberPayload(currentSpecGroup.value.spec_types ?? []), memberPayload(memberSnapshot.value ?? []));
    };
    const refreshInlineDirty = () => {
        inlineDirty.value = hasMemberChanges();
    };
    const syncMemberSnapshot = (group = currentSpecGroup.value) => {
        memberSnapshot.value = clone(group?.spec_types ?? []);
        refreshInlineDirty();
    };
    const discardInlineEdits = () => {
        if (currentSpecGroup.value && memberSnapshot.value !== null) {
            currentSpecGroup.value.spec_types = clone(memberSnapshot.value);
        }
        inlineDirty.value = false;
    };
    const addSpecTypeToCurrentGroup = (specType, overrides = {}) => {
        if (!currentSpecGroup.value || !specType) return;
        const members = currentSpecGroup.value.spec_types ?? [];
        if (members.some((item) => Number(item.id) === Number(specType.id))) return;
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
        local: 'この部品分類を主所属にする未追加のスペック詳細はありません',
        common: '未追加の共通スペック詳細はありません',
        tolerance: '未追加の許容差スペック詳細はありません',
    })[candidateAddModal.mode] ?? '追加できる候補がありません');
    const openCandidateAddModal = async (mode) => {
        if (!currentSpecGroup.value) {
            toastError('先に部品分類を選択してください');
            return;
        }
        candidateAddModal.mode = mode;
        if (mode === 'local' && specTypeOptions.value.length === 0) await fetchSpecTypeOptions();
        if ((mode === 'common' || mode === 'tolerance') && commonSpecTypes.value.length === 0) await fetchCommonSpecTypes();
        candidateAddModal.open = true;
    };
    const closeCandidateAddModal = () => {
        candidateAddModal.open = false;
    };
    const addCandidateFromOption = (specType) => {
        addSpecTypeToCurrentGroup(specType);
        candidateAddModal.open = false;
    };
    const addCommonSpecToCurrentGroup = (specType) => {
        if (!currentSpecGroup.value) {
            toastError('先に部品分類を選択してください');
            return;
        }
        const members = currentSpecGroup.value.spec_types ?? [];
        if (members.some((item) => Number(item.id) === Number(specType.id))) return;
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
    };
    const removeCommonSpecFromCurrentGroup = (specType) => {
        const index = currentSpecGroup.value?.spec_types?.findIndex((item) => Number(item.id) === Number(specType.id)) ?? -1;
        if (index >= 0) removeSpecGroupMember(index);
    };
    const toggleCommonSpecForCurrentGroup = async (specType) => {
        if (!currentSpecGroup.value || specGroupMemberSaving.value) return;
        const wasLinked = isCommonSpecLinked(specType);
        const before = clone(currentSpecGroup.value.spec_types ?? []);
        if (wasLinked) removeCommonSpecFromCurrentGroup(specType);
        else addCommonSpecToCurrentGroup(specType);
        const saved = await syncSpecGroupMembers(wasLinked ? '候補から外しました' : '候補に入れました');
        if (!saved) {
            currentSpecGroup.value.spec_types = before;
            refreshInlineDirty();
        }
    };
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
    const closeCandidateSettingModal = () => closeModalWithConfirm(candidateSettingModal, candidateSettingSnapshot.value);
    const saveCandidateSetting = () => {
        const index = candidateSettingModal.index;
        const member = currentSpecGroup.value?.spec_types?.[index];
        if (!member) {
            candidateSettingModal.open = false;
            return;
        }
        member.pivot = {
            ...(member.pivot ?? {}),
            is_required: candidateSettingModal.form.state === 'required',
            is_recommended: candidateSettingModal.form.state !== 'optional',
            default_profile: candidateSettingModal.form.default_profile || null,
            default_unit: candidateSettingModal.form.default_unit || '',
            note: candidateSettingModal.form.note || '',
        };
        candidateSettingSnapshot.value = clone(candidateSettingModal.form);
        candidateSettingModal.open = false;
        refreshInlineDirty();
    };
    const removeSpecGroupMember = (index) => {
        currentSpecGroup.value?.spec_types?.splice(index, 1);
        refreshInlineDirty();
    };
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
            toastError('先に部品分類を選択してください');
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
            toastSuccess('入力テンプレートを保存しました');
            templateModal.open = false;
            templateSnapshot.value = clone(templateModal.form);
            await fetchSpecGroups({ forceDetail: true });
        } catch (e) { toastError(e.message); }
    };
    const closeTemplateModal = () => closeModalWithConfirm(templateModal, templateSnapshot.value);
    const archiveTemplate = (template) => openConfirm({
        title: '入力テンプレートをアーカイブしますか？',
        message: `「${template.name}」をアーカイブします。`,
        actionLabel: 'アーカイブする',
        onConfirm: async () => {
            try { await api.delete(`/spec-templates/${template.id}`); await fetchSpecGroups({ forceDetail: true }); toastSuccess('アーカイブしました'); }
            catch (e) { toastError(e.message); }
        },
    });

    // ── タブ切り替え時のフェッチ ──────────────────────────
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

    const closeAllEditorModals = () => {
        pkgGroupModal.open = false;
        pkgModal.open = false;
        stModal.open = false;
        specGroupModal.open = false;
        templateModal.open = false;
        candidateSettingModal.open = false;
        candidateAddModal.open = false;
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
    watch(() => specGroupModal.form, (value) => {
        if (specGroupModal.open) modalDirty.value = !same(value, specGroupSnapshot.value);
    }, { deep: true });
    watch(() => templateModal.form, (value) => {
        if (templateModal.open) modalDirty.value = !same(value, templateSnapshot.value);
    }, { deep: true });
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
    const candidateMemberDnD = {
        start: (i) => { dragSrc.value = i; dragTarget.value = i; },
        over:  (e, i) => { e.preventDefault(); dragTarget.value = i; },
        end:   () => { dragSrc.value = null; dragTarget.value = null; },
        drop:  (i) => {
            const from = dragSrc.value;
            dragSrc.value = null; dragTarget.value = null;
            if (from === null || from === i || !currentSpecGroup.value) return;
            const members = [...(currentSpecGroup.value.spec_types ?? [])];
            const [moved] = members.splice(from, 1);
            members.splice(i, 0, moved);
            currentSpecGroup.value.spec_types = members;
            refreshInlineDirty();
        },
    };
    const sgDnD  = makeDnD(activeSpecGroups,    (g) => ({
        url: `/spec-groups/${g.id}`,
        body: {
            name: g.name,
            description: g.description ?? '',
        },
    }), fetchSpecGroups);

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
        specGroups, selectedSpecGroupId, specGroupDetailLoading, specGroupMemberSaving, activeSpecGroups, archivedSpecGroups, currentSpecGroup, specGroupModal, openSgAdd, openSgEdit, openSgDuplicate, saveSpecGroup, closeSpecGroupModal, archiveSpecGroup, restoreSpecGroup, selectSpecGroup,
        memberState, memberStateLabel, memberDefaultLabel, candidateMemberTypeLabel, candidateSettingModal, candidateSettingMember, closeCandidateSettingModal, openCandidateSettingEdit, saveCandidateSetting, removeSpecGroupMember, candidateMemberDnD, syncSpecGroupMembers, inlineDirty,
        candidateAddModal, candidateAddTitle, candidateAddOptions, candidateAddEmptyMessage, openCandidateAddModal, closeCandidateAddModal, addCandidateFromOption,
        isCommonSpecType, isToleranceSpecType, activeCommonSpecTypes, archivedCommonSpecTypes, activeToleranceSpecTypes, archivedToleranceSpecTypes, isCommonSpecLinked, toggleCommonSpecForCurrentGroup, openCommonSpecTypeAdd, openToleranceSpecTypeAdd, openLocalSpecTypeAdd, openCommonSpecTypeDuplicate,
        toleranceUnitOptions, toleranceUnit, toleranceInputFormat, toleranceAllowedUnits,
        templateModal, openTemplateAdd, openTemplateEdit, openTemplateDuplicate, addTemplateItem, removeTemplateItem, moveTemplateItem, saveTemplate, closeTemplateModal, archiveTemplate,
        // スペック詳細
        specTypes, activeSpecTypes, activeSpecTypeOptions, archivedSpecTypes, stModal, openStAdd, openStEdit, openStDuplicate, saveSpecType, archiveSpecType, restoreSpecType, specTypeGroups, specTypeOptionLabel,
        renderSymbol,
    };
}
