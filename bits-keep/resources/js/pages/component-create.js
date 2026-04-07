import { ref, reactive, computed, onMounted } from 'vue';
import { api } from '../api.js';
import { useToast } from '../composables/useToast.js';

export default function setup() {
    const { toasts, toastSuccess, toastError } = useToast();
    const appEl = document.getElementById('app');
    const editId = appEl?.dataset?.id ?? null; // 編集時はID、新規はnull
    const isEdit = !!editId;

    // ── マスタデータ ──────────────────────────────────────
    const categories = ref([]);
    const packages   = ref([]);
    const specTypes  = ref([]);
    const suppliers  = ref([]);

    // ── フォーム ──────────────────────────────────────────
    const form = reactive({
        part_number: '', manufacturer: '', common_name: '', description: '',
        procurement_status: 'active',
        threshold_new: 0, threshold_used: 0,
        category_ids: [], package_ids: [],
        specs: [],       // [{ spec_type_id, value, unit, value_numeric }]
        supplierRows: [], // [{ supplier_id, supplier_part_number, product_url, unit_price, is_preferred, price_breaks:[] }]
    });

    const saving = ref(false);

    // ── スペック操作 ──────────────────────────────────────
    const addSpec = () => form.specs.push({ spec_type_id: '', value: '', unit: '', value_numeric: null });
    const removeSpec = (i) => form.specs.splice(i, 1);
    const getUnits = (specTypeId) => specTypes.value.find(st => st.id == specTypeId)?.units ?? [];

    // ── 仕入先操作 ────────────────────────────────────────
    const addSupplier = () => form.supplierRows.push({ supplier_id: '', supplier_part_number: '', product_url: '', unit_price: '', is_preferred: false, price_breaks: [] });
    const removeSupplier = (i) => form.supplierRows.splice(i, 1);
    const addPriceBreak = (row) => row.price_breaks.push({ min_qty: 1, unit_price: '' });
    const removePriceBreak = (row, i) => row.price_breaks.splice(i, 1);

    // ── マスタ追加ポップアップ ────────────────────────────
    const masterModal = ref({ open: false, type: '', newName: '' });
    const openMasterModal = (type) => { masterModal.value = { open: true, type, newName: '' }; };
    const addMaster = async () => {
        const name = masterModal.value.newName.trim();
        if (!name) return;
        try {
            const endpoints = { category: '/categories', package: '/packages', supplier: '/suppliers' };
            const res = await api.post(endpoints[masterModal.value.type], { name });
            if (masterModal.value.type === 'category')  { categories.value.push(res.data); form.category_ids.push(res.data.id); }
            if (masterModal.value.type === 'package')   { packages.value.push(res.data);   form.package_ids.push(res.data.id); }
            if (masterModal.value.type === 'supplier')  {
                suppliers.value.push(res.data);
                // 最後に追加した仕入先行に自動選択
                if (form.supplierRows.length) form.supplierRows.at(-1).supplier_id = res.data.id;
            }
            masterModal.value.open = false;
            toastSuccess(`追加しました: ${name}`);
        } catch (e) {
            toastError(e.message);
        }
    };

    // ── 保存 ──────────────────────────────────────────────
    const submit = async () => {
        saving.value = true;
        const payload = {
            ...form,
            suppliers: form.supplierRows.map(r => ({
                supplier_id: r.supplier_id,
                supplier_part_number: r.supplier_part_number,
                product_url: r.product_url,
                unit_price: r.unit_price || null,
                is_preferred: r.is_preferred,
                price_breaks: r.price_breaks,
            })),
        };
        delete payload.supplierRows;

        try {
            if (isEdit) {
                await api.put(`/components/${editId}`, payload);
                toastSuccess('更新しました');
                setTimeout(() => { location.href = `/components/${editId}`; }, 800);
            } else {
                const res = await api.post('/components', payload);
                toastSuccess('登録しました');
                setTimeout(() => { location.href = `/components/${res.data.id}`; }, 800);
            }
        } catch (e) {
            toastError(e.message);
        } finally {
            saving.value = false;
        }
    };

    // ── 初期ロード ────────────────────────────────────────
    onMounted(async () => {
        const [catRes, pkgRes, stRes, supRes] = await Promise.all([
            api.get('/categories'), api.get('/packages'),
            api.get('/spec-types'), api.get('/suppliers'),  // suppliersAPIはPhase 3で実装
        ]).catch(() => [{ data: [] }, { data: [] }, { data: [] }, { data: [] }]);

        categories.value = catRes.data ?? [];
        packages.value   = pkgRes.data ?? [];
        specTypes.value  = stRes.data  ?? [];
        suppliers.value  = supRes.data ?? [];

        // 編集モードなら既存データをロード
        if (isEdit) {
            try {
                const res = await api.get(`/components/${editId}`);
                const p = res.data;
                Object.assign(form, {
                    part_number: p.part_number, manufacturer: p.manufacturer ?? '',
                    common_name: p.common_name ?? '', description: p.description ?? '',
                    procurement_status: p.procurement_status,
                    threshold_new: p.threshold_new, threshold_used: p.threshold_used,
                    category_ids: p.categories.map(c => c.id),
                    package_ids:  p.packages.map(pk => pk.id),
                    specs: p.specs.map(s => ({ spec_type_id: s.spec_type_id, value: s.value ?? '', unit: s.unit ?? '', value_numeric: s.value_numeric })),
                    supplierRows: p.component_suppliers.map(cs => ({
                        supplier_id: cs.supplier_id, supplier_part_number: cs.supplier_part_number ?? '',
                        product_url: cs.product_url ?? '', unit_price: cs.unit_price ?? '',
                        is_preferred: cs.is_preferred, price_breaks: cs.price_breaks ?? [],
                    })),
                });
            } catch { toastError('部品情報の取得に失敗しました'); }
        }
    });

    return {
        toasts, isEdit, form, saving,
        categories, packages, specTypes, suppliers,
        addSpec, removeSpec, getUnits,
        addSupplier, removeSupplier, addPriceBreak, removePriceBreak,
        masterModal, openMasterModal, addMaster,
        submit,
    };
}
