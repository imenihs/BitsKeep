import { ref, reactive, onMounted } from 'vue';
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
    const imageFile = ref(null);
    const datasheetFile = ref(null);
    const imagePreviewUrl = ref('');
    const currentImageUrl = ref('');
    const currentDatasheetUrl = ref('');
    const currentDatasheetName = ref('');

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

    const revokePreviewUrl = () => {
        if (imagePreviewUrl.value?.startsWith('blob:')) {
            URL.revokeObjectURL(imagePreviewUrl.value);
        }
    };

    const onImageChange = (event) => {
        const [file] = event.target.files ?? [];
        imageFile.value = file ?? null;
        revokePreviewUrl();
        imagePreviewUrl.value = file ? URL.createObjectURL(file) : (currentImageUrl.value || '');
    };

    const onDatasheetChange = (event) => {
        const [file] = event.target.files ?? [];
        datasheetFile.value = file ?? null;
    };

    const buildPayload = () => {
        const payload = new FormData();

        payload.append('part_number', form.part_number ?? '');
        payload.append('manufacturer', form.manufacturer ?? '');
        payload.append('common_name', form.common_name ?? '');
        payload.append('description', form.description ?? '');
        payload.append('procurement_status', form.procurement_status ?? 'active');
        payload.append('threshold_new', String(form.threshold_new ?? 0));
        payload.append('threshold_used', String(form.threshold_used ?? 0));

        form.category_ids.forEach((categoryId, index) => {
            payload.append(`category_ids[${index}]`, String(categoryId));
        });
        form.package_ids.forEach((packageId, index) => {
            payload.append(`package_ids[${index}]`, String(packageId));
        });
        form.specs.forEach((spec, index) => {
            payload.append(`specs[${index}][spec_type_id]`, String(spec.spec_type_id ?? ''));
            payload.append(`specs[${index}][value]`, spec.value ?? '');
            payload.append(`specs[${index}][unit]`, spec.unit ?? '');
            payload.append(`specs[${index}][value_numeric]`, spec.value_numeric === null || spec.value_numeric === '' ? '' : String(spec.value_numeric));
        });
        form.supplierRows.forEach((row, index) => {
            payload.append(`suppliers[${index}][supplier_id]`, String(row.supplier_id ?? ''));
            payload.append(`suppliers[${index}][supplier_part_number]`, row.supplier_part_number ?? '');
            payload.append(`suppliers[${index}][product_url]`, row.product_url ?? '');
            payload.append(`suppliers[${index}][unit_price]`, row.unit_price === '' || row.unit_price === null ? '' : String(row.unit_price));
            payload.append(`suppliers[${index}][is_preferred]`, row.is_preferred ? '1' : '0');

            row.price_breaks.forEach((priceBreak, priceBreakIndex) => {
                payload.append(`suppliers[${index}][price_breaks][${priceBreakIndex}][min_qty]`, String(priceBreak.min_qty ?? 1));
                payload.append(`suppliers[${index}][price_breaks][${priceBreakIndex}][unit_price]`, priceBreak.unit_price === '' || priceBreak.unit_price === null ? '' : String(priceBreak.unit_price));
            });
        });

        if (imageFile.value) {
            payload.append('image', imageFile.value);
        }
        if (datasheetFile.value) {
            payload.append('datasheet', datasheetFile.value);
        }

        return payload;
    };

    // ── 保存 ──────────────────────────────────────────────
    const submit = async () => {
        saving.value = true;
        const payload = buildPayload();

        try {
            if (isEdit) {
                await api.uploadPut(`/components/${editId}`, payload);
                toastSuccess('更新しました');
                setTimeout(() => { location.href = `/components/${editId}`; }, 800);
            } else {
                const res = await api.upload('/components', payload);
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
                currentImageUrl.value = p.image_url ?? '';
                currentDatasheetUrl.value = p.datasheet_url ?? '';
                currentDatasheetName.value = p.datasheet_path ? p.datasheet_path.split('/').pop() : '';
                imagePreviewUrl.value = currentImageUrl.value;
            } catch { toastError('部品情報の取得に失敗しました'); }
        }
    });

    return {
        toasts, isEdit, form, saving,
        imagePreviewUrl, currentImageUrl, currentDatasheetUrl, currentDatasheetName, datasheetFile,
        categories, packages, specTypes, suppliers,
        addSpec, removeSpec, getUnits,
        addSupplier, removeSupplier, addPriceBreak, removePriceBreak,
        masterModal, openMasterModal, addMaster,
        onImageChange, onDatasheetChange,
        submit,
    };
}
