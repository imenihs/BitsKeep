import { ref, reactive, onMounted, watch } from 'vue';
import { api } from '../api.js';
import { useToast } from '../composables/useToast.js';
import { useNavigationConfirm } from '../composables/useNavigationConfirm.js';
import { useFormatter } from '../composables/useFormatter.js';
import { useConfirmModal } from '../composables/useConfirmModal.js';

const COLOR_PALETTE = [
    '#ef4444', '#f97316', '#eab308', '#22c55e',
    '#06b6d4', '#2563eb', '#7c3aed', '#ec4899',
    '#6b7280', '#64748b', '#78716c', '#8b5cf6',
    '#14b8a6', '#3b82f6', '#f59e0b', '#6366f1',
];

// 目的: 画面モジュールのsetupを扱う。機能: 入力値を検証・整形し、画面または計算処理へ渡す。入力: 関数シグネチャの値。出力: 処理結果またはなし。動作条件: 画面モジュールの初期化後に呼び出す。副作用: Vue状態、localStorage、DOM、HTTP通信のいずれかを更新する場合がある。
export default function setup() {
    const { toasts, toastSuccess, toastError } = useToast();
    const { formatCurrency } = useFormatter();
    const { ask } = useConfirmModal();
    const suppliers = ref([]);
    const fetchError = ref('');
    const dirty = ref(false);
    const snapshot = ref(null);
    useNavigationConfirm(dirty, '未保存の変更があります。このまま画面を離れてもよいですか？');
    const modal = reactive({ open: false, isEdit: false, editId: null,
        form: { name: '', url: '', color: '#2563eb', lead_days: '', free_shipping_threshold: '', note: '' },
        showCustomColor: false });
    // 目的: 画面モジュールのcloneを扱う。機能: 入力値を検証・整形し、画面または計算処理へ渡す。入力: 関数シグネチャの値。出力: 処理結果またはなし。動作条件: 画面モジュールの初期化後に呼び出す。副作用: Vue状態、localStorage、DOM、HTTP通信のいずれかを更新する場合がある。
    const clone = (value) => JSON.parse(JSON.stringify(value));
    // 目的: 画面モジュールのsameを扱う。機能: 入力値を検証・整形し、画面または計算処理へ渡す。入力: 関数シグネチャの値。出力: 処理結果またはなし。動作条件: 画面モジュールの初期化後に呼び出す。副作用: Vue状態、localStorage、DOM、HTTP通信のいずれかを更新する場合がある。
    const same = (a, b) => JSON.stringify(a) === JSON.stringify(b);

    // Determine if custom color should be shown (not in palette)
    // 目的: 画面モジュールのis Custom Colorを扱う。機能: 入力値を検証・整形し、画面または計算処理へ渡す。入力: 関数シグネチャの値。出力: 真偽値。動作条件: 画面モジュールの初期化後に呼び出す。副作用: なし。
    const isCustomColor = (color) => !COLOR_PALETTE.includes(color?.toLowerCase());

    // Check contrast ratio (simple luminance-based check)
    // 目的: 画面モジュールのget Contrastを扱う。機能: 入力値を検証・整形し、画面または計算処理へ渡す。入力: 関数シグネチャの値。出力: 表示値、配列、オブジェクト、数値のいずれか。動作条件: 画面モジュールの初期化後に呼び出す。副作用: なし。
    const getContrast = (hexColor) => {
        if (!hexColor) return 1;
        const rgb = parseInt(hexColor.slice(1), 16);
        const r = (rgb >> 16) & 255, g = (rgb >> 8) & 255, b = rgb & 255;
        const luminance = (0.299 * r + 0.587 * g + 0.114 * b) / 255;
        return luminance > 0.7 ? 'light' : 'dark';
    };
    // 目的: 画面モジュールのhas Low Contrastを扱う。機能: 入力値を検証・整形し、画面または計算処理へ渡す。入力: 関数シグネチャの値。出力: 真偽値。動作条件: 画面モジュールの初期化後に呼び出す。副作用: なし。
    const hasLowContrast = (color) => {
        const c = getContrast(color);
        return c === 'light'; // warn if too bright
    };

    // 目的: 画面モジュールのfetch Suppliersを扱う。機能: 入力値を検証・整形し、画面または計算処理へ渡す。入力: 関数シグネチャの値。出力: 処理結果またはなし。動作条件: 画面モジュールの初期化後に呼び出す。副作用: Vue状態、localStorage、DOM、HTTP通信のいずれかを更新する場合がある。
    const fetchSuppliers = async () => {
        fetchError.value = '';
        try { const r = await api.get('/suppliers?include_archived=1'); suppliers.value = r.data; }
        catch { fetchError.value = '商社情報の取得に失敗しました。再試行するか、しばらく待ってから再読み込みしてください。'; toastError('商社情報の取得に失敗しました'); }
    };

    // 目的: 画面モジュールのopen Addを扱う。機能: 入力値を検証・整形し、画面または計算処理へ渡す。入力: 関数シグネチャの値。出力: 処理結果またはなし。動作条件: 画面モジュールの初期化後に呼び出す。副作用: Vue状態、localStorage、DOM、HTTP通信のいずれかを更新する場合がある。
    const openAdd = () => {
        const form = { name: '', url: '', color: '#2563eb', lead_days: '', free_shipping_threshold: '', note: '' };
        snapshot.value = clone(form);
        Object.assign(modal, { open: true, isEdit: false, editId: null, form });
    };
    // 目的: 画面モジュールのopen Editを扱う。機能: 入力値を検証・整形し、画面または計算処理へ渡す。入力: 関数シグネチャの値。出力: 処理結果またはなし。動作条件: 画面モジュールの初期化後に呼び出す。副作用: Vue状態、localStorage、DOM、HTTP通信のいずれかを更新する場合がある。
    const openEdit = (s) => {
        const form = { name: s.name, url: s.url ?? '', color: s.color ?? '#2563eb', lead_days: s.lead_days ?? '', free_shipping_threshold: s.free_shipping_threshold ?? '', note: s.note ?? '' };
        snapshot.value = clone(form);
        Object.assign(modal, { open: true, isEdit: true, editId: s.id, form });
    };
    // 目的: 画面モジュールのclose Modalを扱う。機能: 入力値を検証・整形し、画面または計算処理へ渡す。入力: 関数シグネチャの値。出力: 処理結果またはなし。動作条件: 画面モジュールの初期化後に呼び出す。副作用: Vue状態、localStorage、DOM、HTTP通信のいずれかを更新する場合がある。
    const closeModal = async () => {
        if (modal.open && !same(modal.form, snapshot.value) && !await ask('未保存の変更があります。閉じてもよいですか？')) return;
        modal.open = false;
    };

    // 目的: 画面モジュールのsaveを扱う。機能: 入力値を検証・整形し、画面または計算処理へ渡す。入力: 関数シグネチャの値。出力: 処理結果またはなし。動作条件: 画面モジュールの初期化後に呼び出す。副作用: Vue状態、localStorage、DOM、HTTP通信のいずれかを更新する場合がある。
    const save = async () => {
        try {
            if (modal.isEdit) await api.put(`/suppliers/${modal.editId}`, modal.form);
            else await api.post('/suppliers', modal.form);
            toastSuccess('保存しました'); modal.open = false; snapshot.value = clone(modal.form); dirty.value = false; await fetchSuppliers();
        } catch (e) { toastError(e.message); }
    };

    // 目的: 画面モジュールのarchive Supplierを扱う。機能: 入力値を検証・整形し、画面または計算処理へ渡す。入力: 関数シグネチャの値。出力: 処理結果またはなし。動作条件: 画面モジュールの初期化後に呼び出す。副作用: Vue状態、localStorage、DOM、HTTP通信のいずれかを更新する場合がある。
    const archiveSupplier = async (s) => {
        if (!await ask(`「${s.name}」を取引停止にしますか？\n危険度: 低\n使用件数: ${s.usage_count ?? 0}件\n新規登録時の商社候補から外れますが、過去の価格履歴や部品との紐付けは残り、あとで復元できます。`)) return;
        try { await api.delete(`/suppliers/${s.id}`); await fetchSuppliers(); toastSuccess('取引停止にしました'); }
        catch (e) { toastError(e.message); }
    };
    // 目的: 画面モジュールのrestore Supplierを扱う。機能: 入力値を検証・整形し、画面または計算処理へ渡す。入力: 関数シグネチャの値。出力: 処理結果またはなし。動作条件: 画面モジュールの初期化後に呼び出す。副作用: Vue状態、localStorage、DOM、HTTP通信のいずれかを更新する場合がある。
    const restoreSupplier = async (s) => {
        if (!await ask(`「${s.name}」を復元しますか？\n危険度: 低\n新規登録時の商社候補に戻します。`)) return;
        try { await api.post(`/suppliers/${s.id}/restore`); await fetchSuppliers(); toastSuccess('復元しました'); }
        catch (e) { toastError(e.message); }
    };
    // 目的: 画面モジュールのforce Delete Supplierを扱う。機能: 入力値を検証・整形し、画面または計算処理へ渡す。入力: 関数シグネチャの値。出力: 処理結果またはなし。動作条件: 画面モジュールの初期化後に呼び出す。副作用: Vue状態、localStorage、DOM、HTTP通信のいずれかを更新する場合がある。
    const forceDeleteSupplier = async (s) => {
        if (!await ask(`「${s.name}」を完全削除しますか？\n危険度: 高\nこの操作は元に戻せません。履歴や参照がない商社だけ実行できます。`)) return;
        try { await api.delete(`/suppliers/${s.id}/force`); await fetchSuppliers(); toastSuccess('完全削除しました'); }
        catch (e) { toastError(e.message); }
    };

    onMounted(fetchSuppliers);
    watch(() => modal.form, (value) => {
        if (modal.open) dirty.value = !same(value, snapshot.value);
    }, { deep: true });
    watch(() => modal.open, (isOpen) => {
        if (!isOpen) dirty.value = false;
    });

    return { toasts, suppliers, fetchError, modal, openAdd, openEdit, closeModal, save, archiveSupplier, restoreSupplier, forceDeleteSupplier, fetchSuppliers, formatCurrency, COLOR_PALETTE, isCustomColor, hasLowContrast };
}
