import { ref, reactive, computed, watch, onMounted } from 'vue';
import { api } from '../api.js';
import { useToast } from '../composables/useToast.js';

export default function setup() {
    const { toasts, toastSuccess, toastError } = useToast();

    const locations      = ref([]);
    const loading        = ref(false);
    const inventoryMode  = ref(false);
    const countInputs    = reactive({});

    const locationModal = reactive({ open: false, isEdit: false, form: { code: '', name: '', group: '', description: '', sort_order: 0 }, editId: null });

    const fetchLocations = async () => {
        loading.value = true;
        try { const r = await api.get('/locations'); locations.value = r.data; }
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
        Object.assign(locationModal, { open: true, isEdit: false, editId: null,
            form: { code: '', name: '', group: '', description: '', sort_order: 0 } });
    };
    const openEdit = (loc) => {
        Object.assign(locationModal, { open: true, isEdit: true, editId: loc.id,
            form: { code: loc.code, name: loc.name ?? '', group: loc.group ?? '', description: loc.description ?? '', sort_order: loc.sort_order } });
    };
    const saveLocation = async () => {
        try {
            if (locationModal.isEdit) await api.put(`/locations/${locationModal.editId}`, locationModal.form);
            else await api.post('/locations', locationModal.form);
            toastSuccess('保存しました');
            locationModal.open = false;
            await fetchLocations();
        } catch (e) { toastError(e.message); }
    };
    const deleteLocation = async (loc) => {
        if (!confirm(`「${loc.code}」を削除しますか？`)) return;
        try { await api.delete(`/locations/${loc.id}`); await fetchLocations(); toastSuccess('削除しました'); }
        catch (e) { toastError(e.message); }
    };

    onMounted(fetchLocations);

    return { toasts, locations, loading, inventoryMode, countInputs, grouped, getCountDiff, saveInventory, locationModal, openAdd, openEdit, saveLocation, deleteLocation };
}
