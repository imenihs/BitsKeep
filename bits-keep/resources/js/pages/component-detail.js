import { ref, computed, onMounted, reactive } from 'vue';
import { api } from '../api.js';
import { useToast } from '../composables/useToast.js';

export default function setup() {
    const { toasts, toastSuccess, toastError } = useToast();

    // Blade側から data-id 属性で部品IDを受け取る
    const componentId = document.getElementById('app')?.dataset?.id;

    const part       = ref(null);
    const loading    = ref(true);
    const loadError  = ref('');
    const sections   = reactive({ basic: true, detail: true, custom: true, integration: true });
    const categories = ref([]);
    const packages = ref([]);
    const specTypes = ref([]);
    const suppliers = ref([]);
    const locations = ref([]);

    // 編集モーダル
    const editModal  = ref({ open: false, section: '', title: '', form: {} });
    // 出庫モーダル
    const stockOutModal = ref({ open: false, blockId: null, maxQty: 0, qty: 1, projectId: '', note: '' });
    // 入庫モーダル（在庫追加）
    const stockInModal  = ref({ open: false, form: { stock_type: 'loose', condition: 'new', quantity: 1, lot_number: '', reel_code: '', location_id: '', note: '' } });

    const stockTypeLabel = { reel: 'リール', tape: 'テープ', tray: 'トレイ', loose: 'バラ', box: '箱' };
    const procurementOptions = [
        { value: 'active', label: '量産中' }, { value: 'eol', label: 'EOL' },
        { value: 'last_time', label: '在庫限り' }, { value: 'nrnd', label: '新規非推奨' },
    ];
    const stockConditionLabel = { new: '新品', used: '中古' };

    const fetchPart = async () => {
        loading.value = true;
        loadError.value = '';
        try {
            const res = await api.get(`/components/${componentId}`);
            part.value = res.data;
            await fetchSimilar();
        } catch {
            part.value = null;
            loadError.value = '部品情報の取得に失敗しました。URLを確認するか、部品一覧から開き直してください。';
            toastError('部品情報の取得に失敗しました');
        } finally {
            loading.value = false;
        }
    };

    const fetchMasters = async () => {
        try {
            const [categoryRes, packageRes, specTypeRes, supplierRes, locationRes] = await Promise.all([
                api.get('/categories'),
                api.get('/packages'),
                api.get('/spec-types'),
                api.get('/suppliers'),
                api.get('/locations'),
            ]);
            categories.value = categoryRes.data ?? [];
            packages.value = packageRes.data ?? [];
            specTypes.value = specTypeRes.data ?? [];
            suppliers.value = supplierRes.data ?? [];
            locations.value = locationRes.data ?? [];
        } catch {
            toastError('編集用の候補取得に失敗しました');
        }
    };

    // セクション別編集モーダルを開く
    const openEdit = (section) => {
        const p = part.value;
        const forms = {
            basic: {
                title: '基本情報を編集',
                form: {
                    part_number: p.part_number, manufacturer: p.manufacturer ?? '',
                    common_name: p.common_name ?? '', description: p.description ?? '',
                    procurement_status: p.procurement_status,
                    threshold_new: p.threshold_new, threshold_used: p.threshold_used,
                    primary_location_id: p.primary_location_id ?? '',
                    category_ids: p.categories.map(c => c.id),
                    package_ids:  p.packages.map(pk => pk.id),
                },
            },
            specs: {
                title: 'スペックを編集',
                form: { specs: p.specs.map(s => ({ spec_type_id: s.spec_type_id, value: s.value ?? '', unit: s.unit ?? '', value_numeric: s.value_numeric ?? '' })) },
            },
            suppliers: {
                title: '仕入先情報を編集',
                form: { suppliers: p.component_suppliers.map(cs => ({
                    supplier_id: cs.supplier_id,
                    supplier_part_number: cs.supplier_part_number ?? '',
                    product_url: cs.product_url ?? '',
                    unit_price: cs.unit_price ?? '',
                    is_preferred: !!cs.is_preferred,
                    price_breaks: (cs.price_breaks ?? []).map(pb => ({ min_qty: pb.min_qty, unit_price: pb.unit_price })),
                })) },
            },
        };
        editModal.value = { open: true, section, ...forms[section] };
    };

    // セクション保存（PATCH / ファイルありの場合は multipart POST + _method=PATCH）
    const saveSection = async () => {
        try {
            const form = editModal.value.form;
            // basic セクションでファイルが添付されている場合は FormData で送る
            if (editModal.value.section === 'basic' && (form._newImage || form._newDatasheets?.length)) {
                const fd = new FormData();
                // テキスト項目
                const textKeys = ['part_number', 'manufacturer', 'common_name', 'description',
                    'procurement_status', 'threshold_new', 'threshold_used', 'primary_location_id'];
                textKeys.forEach(k => { if (form[k] != null) fd.append(k, form[k]); });
                (form.category_ids ?? []).forEach(id => fd.append('category_ids[]', id));
                (form.package_ids ?? []).forEach(id => fd.append('package_ids[]', id));
                // ファイル項目
                if (form._newImage) fd.append('image', form._newImage);
                (form._newDatasheets ?? []).forEach(f => fd.append('datasheets[]', f));
                await api.uploadPatch(`/components/${componentId}/basic`, fd);
            } else {
                // ファイルなし → 通常 JSON PATCH（_newImage/_newDatasheets は除外）
                const { _newImage, _newDatasheets, ...payload } = form;
                await api.patch(`/components/${componentId}/${editModal.value.section}`, payload);
            }
            toastSuccess('保存しました');
            editModal.value.open = false;
            await fetchPart();
        } catch (e) {
            toastError(e.message);
        }
    };

    // 出庫
    const openStockOut = (block) => {
        stockOutModal.value = { open: true, blockId: block.id, maxQty: block.quantity, qty: 1, projectId: '', note: '' };
    };
    const submitStockOut = async () => {
        try {
            await api.post(`/components/${componentId}/stock-out`, {
                inventory_block_id: stockOutModal.value.blockId,
                quantity: stockOutModal.value.qty,
                project_id: stockOutModal.value.projectId || null,
                note: stockOutModal.value.note,
            });
            toastSuccess('出庫しました');
            stockOutModal.value.open = false;
            await fetchPart();
        } catch (e) {
            toastError(e.message);
        }
    };

    // 入庫
    const submitStockIn = async () => {
        try {
            await api.post(`/components/${componentId}/stock-in`, stockInModal.value.form);
            toastSuccess('入庫しました');
            stockInModal.value.open = false;
            await fetchPart();
        } catch (e) {
            toastError(e.message);
        }
    };

    // 類似部品
    const similarParts = ref([]);
    const similarLoading = ref(false);
    const similarError = ref('');
    const fetchSimilar = async () => {
        if (similarLoading.value) return;
        similarLoading.value = true;
        similarError.value = '';
        similarParts.value = [];
        try {
            const r = await api.get(`/components/${componentId}/similar`);
            similarParts.value = r.data;
        } catch {
            similarError.value = '類似部品の取得に失敗しました。比較画面へ進むか、再試行してください。';
        }
        finally { similarLoading.value = false; }
    };

    // 論理削除
    const deletePart = async () => {
        if (!confirm('この部品を削除しますか？')) return;
        try {
            await api.delete(`/components/${componentId}`);
            toastSuccess('削除しました');
            setTimeout(() => { location.href = '/components'; }, 1000);
        } catch (e) {
            toastError(e.message);
        }
    };

    onMounted(async () => {
        await Promise.all([fetchPart(), fetchMasters()]);
    });

    const preferredSupplier = computed(() => {
        const suppliers = part.value?.component_suppliers ?? [];
        return suppliers.find((item) => item.is_preferred) ?? suppliers[0] ?? null;
    });

    const stockSummary = computed(() => {
        const blocks = part.value?.inventory_blocks ?? [];
        return blocks.reduce((acc, block) => {
            if (block.condition === 'used') acc.used += block.quantity ?? 0;
            else acc.new += block.quantity ?? 0;
            return acc;
        }, { new: 0, used: 0 });
    });

    const recentTransactions = computed(() => (part.value?.transactions ?? []).slice(0, 5));

    return {
        toasts, part, loading, loadError, componentId,
        sections, stockTypeLabel, stockConditionLabel, procurementOptions,
        categories, packages, specTypes, suppliers, locations,
        preferredSupplier, stockSummary, recentTransactions,
        editModal, openEdit, saveSection,
        stockOutModal, openStockOut, submitStockOut,
        stockInModal, submitStockIn,
        deletePart,
        similarParts, similarLoading, similarError, fetchSimilar, fetchPart,
    };
}
