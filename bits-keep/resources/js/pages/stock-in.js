import { computed, onMounted, reactive, ref, watch } from 'vue';
import { api } from '../api.js';
import { useToast } from '../composables/useToast.js';

function createEntry(part) {
    return {
        key: `${part.id}-${Date.now()}-${Math.random().toString(16).slice(2)}`,
        part,
        form: reactive({
            stock_type: 'box',
            condition: 'new',
            quantity: 1,
            location_id: part.primary_location_id ?? '',
            lot_number: '',
            reel_code: '',
            note: '',
        }),
    };
}

export default function setup() {
    const { toasts, toastSuccess, toastError } = useToast();
    const query = ref('');
    const parts = ref([]);
    const selectedSearchIds = ref([]);
    const alertParts = ref([]);
    const locations = ref([]);
    const loading = ref(false);
    const submitting = ref(false);
    const selectedEntries = ref([]);
    const processedLog = ref([]);
    let searchTimer = null;

    const stockTypeLabel = {
        reel: 'リール',
        tape: 'テープ',
        tray: 'トレー',
        loose: 'バラ',
        box: '箱',
    };

    const queueCount = computed(() => selectedEntries.value.length);
    const searchSelectionCount = computed(() => selectedSearchIds.value.length);
    const selectedSearchResults = computed(() => parts.value.filter((part) => selectedSearchIds.value.includes(part.id)));

    const search = async () => {
        if (!query.value.trim()) {
            parts.value = [];
            selectedSearchIds.value = [];
            return;
        }

        loading.value = true;
        try {
            const res = await api.get(`/components?q=${encodeURIComponent(query.value)}&per_page=20&sort=updated_at`);
            const items = Array.isArray(res?.data?.data)
                ? res.data.data
                : Array.isArray(res?.data)
                    ? res.data
                    : [];
            parts.value = items;
            selectedSearchIds.value = selectedSearchIds.value.filter((id) => parts.value.some((part) => part.id === id));
        } catch (e) {
            toastError(e.message ?? '検索に失敗しました');
        } finally {
            loading.value = false;
        }
    };

    const loadMasters = async () => {
        try {
            const [locationRes, alertRes] = await Promise.all([
                api.get('/locations'),
                api.get('/stock-alerts'),
            ]);
            locations.value = locationRes.data ?? [];
            alertParts.value = alertRes.data ?? [];
        } catch (e) {
            toastError(e.message ?? '初期データの取得に失敗しました');
        }
    };

    const loadComponent = async (partId) => {
        const res = await api.get(`/components/${partId}`);
        return res.data;
    };

    const queuePart = async (partLike) => {
        if (selectedEntries.value.some((entry) => entry.part.id === partLike.id)) {
            toastError('すでに入庫対象に追加されています');
            return;
        }

        try {
            const part = await loadComponent(partLike.id);
            selectedEntries.value.push(createEntry(part));
        } catch (e) {
            toastError(e.message ?? '部品詳細の取得に失敗しました');
        }
    };

    const addSelectedResults = async () => {
        for (const part of selectedSearchResults.value) {
            // eslint-disable-next-line no-await-in-loop
            await queuePart(part);
        }
        selectedSearchIds.value = [];
    };

    const toggleSearchSelection = (partId) => {
        if (selectedSearchIds.value.includes(partId)) {
            selectedSearchIds.value = selectedSearchIds.value.filter((id) => id !== partId);
            return;
        }
        selectedSearchIds.value = [...selectedSearchIds.value, partId];
    };

    const removeEntry = (entryKey) => {
        selectedEntries.value = selectedEntries.value.filter((entry) => entry.key !== entryKey);
    };

    const matchingBlocks = (entry) => {
        if (!entry?.part) return [];
        return (entry.part.inventory_blocks ?? []).filter((block) =>
            String(block.location_id ?? '') === String(entry.form.location_id ?? '')
            && block.stock_type === entry.form.stock_type
            && block.condition === entry.form.condition
            && String(block.lot_number ?? '') === String(entry.form.lot_number ?? '')
            && String(block.reel_code ?? '') === String(entry.form.reel_code ?? '')
        );
    };

    const submitAll = async () => {
        if (!selectedEntries.value.length) return;

        submitting.value = true;
        try {
            const completedKeys = [];

            for (const entry of selectedEntries.value) {
                const merged = matchingBlocks(entry).length > 0;
                // eslint-disable-next-line no-await-in-loop
                await api.post(`/components/${entry.part.id}/stock-in`, entry.form);
                processedLog.value.unshift({
                    partId: entry.part.id,
                    partNumber: entry.part.part_number,
                    commonName: entry.part.common_name,
                    quantity: entry.form.quantity,
                    merged,
                    at: new Date().toLocaleTimeString('ja-JP', { hour: '2-digit', minute: '2-digit' }),
                });
                completedKeys.push(entry.key);
            }

            selectedEntries.value = selectedEntries.value.filter((entry) => !completedKeys.includes(entry.key));
            toastSuccess('選択した部品を一括入庫しました');
        } catch (e) {
            toastError(e.message ?? '入庫に失敗しました');
        } finally {
            submitting.value = false;
        }
    };

    watch(query, () => {
        clearTimeout(searchTimer);
        searchTimer = setTimeout(search, 250);
    });

    onMounted(loadMasters);

    return {
        toasts,
        query,
        parts,
        selectedSearchIds,
        selectedSearchResults,
        selectedEntries,
        searchSelectionCount,
        queueCount,
        alertParts,
        locations,
        loading,
        submitting,
        processedLog,
        stockTypeLabel,
        search,
        queuePart,
        addSelectedResults,
        toggleSearchSelection,
        removeEntry,
        matchingBlocks,
        submitAll,
    };
}
