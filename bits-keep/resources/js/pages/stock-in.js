import { computed, onMounted, reactive, ref } from 'vue';
import { api } from '../api.js';
import { useToast } from '../composables/useToast.js';

export default function setup() {
    const { toasts, toastSuccess, toastError } = useToast();
    const query = ref('');
    const parts = ref([]);
    const alertParts = ref([]);
    const locations = ref([]);
    const loading = ref(false);
    const submitting = ref(false);
    const selectedPart = ref(null);
    // 入庫完了後に「次の部品へ」を促す状態
    const justSubmitted = ref(false);
    // セッション内の処理ログ（画面を閉じるまで保持）
    const processedLog = ref([]);
    const form = reactive({
        stock_type: 'box',
        condition: 'new',
        quantity: 1,
        location_id: '',
        lot_number: '',
        reel_code: '',
        note: '',
    });

    const stockTypeLabel = {
        reel: 'リール',
        tape: 'テープ',
        tray: 'トレー',
        loose: 'バラ',
        box: '箱',
    };

    const matchingBlocks = computed(() => {
        if (!selectedPart.value) return [];
        return (selectedPart.value.inventory_blocks ?? []).filter((block) =>
            String(block.location_id ?? '') === String(form.location_id ?? '')
            && block.stock_type === form.stock_type
            && block.condition === form.condition
            && String(block.lot_number ?? '') === String(form.lot_number ?? '')
            && String(block.reel_code ?? '') === String(form.reel_code ?? '')
        );
    });
    const willMerge = computed(() => matchingBlocks.value.length > 0);

    const search = async () => {
        loading.value = true;
        try {
            const res = await api.get(`/components?q=${encodeURIComponent(query.value)}&per_page=20&sort=updated_at`);
            parts.value = res.data.data ?? [];
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
            alertParts.value = (alertRes.data ?? []).slice(0, 8);
        } catch (e) {
            toastError(e.message ?? '初期データの取得に失敗しました');
        }
    };

    const choosePart = async (part) => {
        try {
            const res = await api.get(`/components/${part.id}`);
            selectedPart.value = res.data;
            form.location_id = selectedPart.value.primary_location_id ?? '';
            form.stock_type = 'box';
            form.condition = 'new';
            form.quantity = 1;
            form.lot_number = '';
            form.reel_code = '';
            form.note = '';
        } catch (e) {
            toastError(e.message ?? '部品詳細の取得に失敗しました');
        }
    };

    const submit = async () => {
        if (!selectedPart.value) return;
        submitting.value = true;
        try {
            const merged = willMerge.value;
            await api.post(`/components/${selectedPart.value.id}/stock-in`, form);
            const msg = merged ? '既存在庫へ加算しました' : '新しい在庫ブロックを作成しました';
            toastSuccess(msg);
            // 処理ログへ追記
            processedLog.value.unshift({
                partId: selectedPart.value.id,
                partNumber: selectedPart.value.part_number,
                commonName: selectedPart.value.common_name,
                quantity: form.quantity,
                merged,
                at: new Date().toLocaleTimeString('ja-JP', { hour: '2-digit', minute: '2-digit' }),
            });
            justSubmitted.value = true;
        } catch (e) {
            toastError(e.message ?? '入庫に失敗しました');
        } finally {
            submitting.value = false;
        }
    };

    // 次の部品へ進む（検索フォームへ戻す）
    const nextPart = () => {
        selectedPart.value = null;
        justSubmitted.value = false;
        query.value = '';
        parts.value = [];
    };

    onMounted(loadMasters);

    return {
        toasts,
        query,
        parts,
        alertParts,
        loading,
        selectedPart,
        form,
        locations,
        stockTypeLabel,
        matchingBlocks,
        willMerge,
        search,
        choosePart,
        submit,
        submitting,
        justSubmitted,
        processedLog,
        nextPart,
    };
}
