import { ref, reactive, computed, watch, onMounted } from 'vue';
import { api } from '../api.js';
import { useToast } from '../composables/useToast.js';
import { useNavigationConfirm } from '../composables/useNavigationConfirm.js';

export default function setup() {
    const { toasts, toastSuccess, toastError } = useToast();

    const locations      = ref([]);
    const loading        = ref(false);
    const inventoryMode  = ref(false);
    const countInputs    = reactive({});
    const dirty = ref(false);
    const snapshot = ref(null);
    useNavigationConfirm(dirty, '未保存の変更があります。このまま画面を離れてもよいですか？');

    const locationModal = reactive({ open: false, isEdit: false, form: { code: '', name: '', group: '', description: '', sort_order: 0 }, editId: null });
    const clone = (value) => JSON.parse(JSON.stringify(value));
    const same = (a, b) => JSON.stringify(a) === JSON.stringify(b);

    const fetchLocations = async () => {
        loading.value = true;
        try { const r = await api.get('/locations?include_archived=1'); locations.value = r.data; }
        catch { toastError('棚情報の取得に失敗しました'); }
        finally { loading.value = false; }
    };

    // グループ別にまとめる
    const grouped = computed(() => {
        const map = {};
        locations.value.forEach(loc => {
            const g = loc.group || '未分類';
            if (!map[g]) map[g] = [];
            map[g].push(loc);
        });
        return map;
    });

    // 棚卸しモード切替: ON時に現在在庫数をコピー
    watch(inventoryMode, (val) => {
        if (val) locations.value.forEach(loc => { countInputs[loc.id] = loc.stock_count ?? 0; });
    });

    const getCountDiff = (loc) => {
        const input = countInputs[loc.id];
        return input !== undefined ? input - (loc.stock_count ?? 0) : 0;
    };

    const saveInventory = async () => {
        const items = locations.value
            .filter(loc => getCountDiff(loc) !== 0)
            .map(loc => ({ location_id: loc.id, actual_qty: countInputs[loc.id] }));

        if (!items.length) { toastSuccess('変更なし'); inventoryMode.value = false; return; }

        try {
            const r = await api.post('/locations/inventory', { items });
            toastSuccess(r.message ?? '棚卸し完了');
            inventoryMode.value = false;
            await fetchLocations();
        } catch (e) { toastError(e.message); }
    };

    const openAdd = () => {
        const form = { code: '', name: '', group: '', description: '', sort_order: 0 };
        snapshot.value = clone(form);
        Object.assign(locationModal, { open: true, isEdit: false, editId: null, form });
    };
    const openEdit = (loc) => {
        const form = { code: loc.code, name: loc.name ?? '', group: loc.group ?? '', description: loc.description ?? '', sort_order: loc.sort_order };
        snapshot.value = clone(form);
        Object.assign(locationModal, { open: true, isEdit: true, editId: loc.id, form });
    };
    const closeModal = () => {
        if (locationModal.open && !same(locationModal.form, snapshot.value) && !confirm('未保存の変更があります。閉じてもよいですか？')) return;
        locationModal.open = false;
    };
    const saveLocation = async () => {
        try {
            if (locationModal.isEdit) await api.put(`/locations/${locationModal.editId}`, locationModal.form);
            else await api.post('/locations', locationModal.form);
            toastSuccess('保存しました');
            locationModal.open = false;
            snapshot.value = clone(locationModal.form);
            dirty.value = false;
            await fetchLocations();
        } catch (e) { toastError(e.message); }
    };
    const archiveLocation = async (loc) => {
        if (!confirm(`「${loc.code}」を廃止しますか？\n在庫ブロック: ${loc.inventory_block_count ?? 0}件\n代表棚参照: ${loc.primary_component_count ?? 0}件`)) return;
        try { await api.delete(`/locations/${loc.id}`); await fetchLocations(); toastSuccess('廃止しました'); }
        catch (e) { toastError(e.message); }
    };
    const restoreLocation = async (loc) => {
        if (!confirm(`「${loc.code}」を復元しますか？`)) return;
        try { await api.post(`/locations/${loc.id}/restore`); await fetchLocations(); toastSuccess('復元しました'); }
        catch (e) { toastError(e.message); }
    };
    const forceDeleteLocation = async (loc) => {
        if (!confirm(`「${loc.code}」を完全削除しますか？\nこの操作は元に戻せません。`)) return;
        try { await api.delete(`/locations/${loc.id}/force`); await fetchLocations(); toastSuccess('完全削除しました'); }
        catch (e) { toastError(e.message); }
    };

    onMounted(fetchLocations);
    watch(() => locationModal.form, (value) => {
        if (locationModal.open) dirty.value = !same(value, snapshot.value);
    }, { deep: true });
    watch(() => locationModal.open, (isOpen) => {
        if (!isOpen) dirty.value = false;
    });

    return { toasts, locations, loading, inventoryMode, countInputs, grouped, getCountDiff, saveInventory, locationModal, openAdd, openEdit, closeModal, saveLocation, archiveLocation, restoreLocation, forceDeleteLocation };
}
