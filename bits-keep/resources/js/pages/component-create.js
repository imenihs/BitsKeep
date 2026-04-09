import { ref, reactive, onMounted, computed } from 'vue';
import { api } from '../api.js';
import { useToast } from '../composables/useToast.js';
import { useNavigationConfirm } from '../composables/useNavigationConfirm.js';

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
    const manufacturerOptions = ref([]);

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
    useNavigationConfirm(saving, '登録処理中です。このまま画面を離れてもよいですか？');
    const manufacturerQuery = ref('');
    const categoryQuery = ref('');
    const packageQuery = ref('');

    const filteredManufacturers = computed(() => {
        const q = manufacturerQuery.value.trim().toLowerCase();
        if (!q) return manufacturerOptions.value.slice(0, 8);
        return manufacturerOptions.value.filter((name) => name.toLowerCase().includes(q)).slice(0, 8);
    });
    const manufacturerExactMatch = computed(() =>
        manufacturerOptions.value.some((name) => name.toLowerCase() === manufacturerQuery.value.trim().toLowerCase())
    );
    const filteredCategories = computed(() => {
        const q = categoryQuery.value.trim().toLowerCase();
        if (!q) return categories.value;
        return categories.value.filter((item) => item.name.toLowerCase().includes(q));
    });
    const filteredPackages = computed(() => {
        const q = packageQuery.value.trim().toLowerCase();
        if (!q) return packages.value;
        return packages.value.filter((item) => item.name.toLowerCase().includes(q));
    });
    const canCreateCategory = computed(() => {
        const q = categoryQuery.value.trim();
        if (!q) return false;
        return !categories.value.some((item) => item.name.toLowerCase() === q.toLowerCase());
    });
    const canCreatePackage = computed(() => {
        const q = packageQuery.value.trim();
        if (!q) return false;
        return !packages.value.some((item) => item.name.toLowerCase() === q.toLowerCase());
    });

    const syncManufacturerQuery = () => {
        manufacturerQuery.value = form.manufacturer ?? '';
    };
    const normalizeUniqueNames = (values) => [...new Set(values.map((value) => String(value).trim()).filter(Boolean))].sort((a, b) => a.localeCompare(b, 'ja'));
    const ensureManufacturerOption = (name) => {
        if (!name) return;
        manufacturerOptions.value = normalizeUniqueNames([...manufacturerOptions.value, name]);
    };

    // ── スペック操作 ──────────────────────────────────────
    const addSpec = () => form.specs.push({ spec_type_id: '', value: '', unit: '', value_numeric: null });
    const removeSpec = (i) => form.specs.splice(i, 1);
    const getUnits = (specTypeId) => specTypes.value.find(st => st.id == specTypeId)?.units ?? [];

    // ── 仕入先操作 ────────────────────────────────────────
    const createSupplierRow = () => ({
        supplier_id: '',
        supplier_name: '',
        supplier_part_number: '',
        product_url: '',
        unit_price: '',
        is_preferred: false,
        price_breaks: [],
    });
    const addSupplier = () => form.supplierRows.push(createSupplierRow());
    const removeSupplier = (i) => form.supplierRows.splice(i, 1);
    const addPriceBreak = (row) => row.price_breaks.push({ min_qty: 1, unit_price: '' });
    const removePriceBreak = (row, i) => row.price_breaks.splice(i, 1);

    const createMaster = async (type, name) => {
        const trimmed = name.trim();
        if (!trimmed) return null;
        try {
            const endpoints = { category: '/categories', package: '/packages', supplier: '/suppliers' };
            const res = await api.post(endpoints[type], { name: trimmed });
            toastSuccess(`追加しました: ${trimmed}`);
            return res.data;
        } catch (e) {
            toastError(e.message);
            return null;
        }
    };

    const selectManufacturer = (name) => {
        form.manufacturer = name;
        manufacturerQuery.value = name;
        ensureManufacturerOption(name);
    };
    const commitManufacturer = () => {
        const trimmed = manufacturerQuery.value.trim();
        form.manufacturer = trimmed;
        ensureManufacturerOption(trimmed);
    };

    const toggleCategory = (id) => {
        const exists = form.category_ids.includes(id);
        form.category_ids = exists
            ? form.category_ids.filter((value) => value !== id)
            : [...form.category_ids, id];
    };
    const togglePackage = (id) => {
        const exists = form.package_ids.includes(id);
        form.package_ids = exists
            ? form.package_ids.filter((value) => value !== id)
            : [...form.package_ids, id];
    };
    const addCategoryFromQuery = async () => {
        const created = await createMaster('category', categoryQuery.value);
        if (!created) return;
        categories.value = [...categories.value, created].sort((a, b) => a.name.localeCompare(b.name, 'ja'));
        toggleCategory(created.id);
        categoryQuery.value = '';
    };
    const addPackageFromQuery = async () => {
        const created = await createMaster('package', packageQuery.value);
        if (!created) return;
        packages.value = [...packages.value, created].sort((a, b) => a.name.localeCompare(b.name, 'ja'));
        togglePackage(created.id);
        packageQuery.value = '';
    };

    const filteredSuppliersForRow = (row) => {
        const q = (row.supplier_name ?? '').trim().toLowerCase();
        if (!q) return suppliers.value.slice(0, 8);
        return suppliers.value.filter((item) => item.name.toLowerCase().includes(q)).slice(0, 8);
    };
    const canCreateSupplierForRow = (row) => {
        const q = (row.supplier_name ?? '').trim();
        if (!q) return false;
        return !suppliers.value.some((item) => item.name.toLowerCase() === q.toLowerCase());
    };
    const selectSupplier = (row, supplier) => {
        row.supplier_id = supplier.id;
        row.supplier_name = supplier.name;
    };
    const commitSupplier = async (row) => {
        const trimmed = (row.supplier_name ?? '').trim();
        if (!trimmed) {
            row.supplier_id = '';
            return;
        }
        const existing = suppliers.value.find((item) => item.name.toLowerCase() === trimmed.toLowerCase());
        if (existing) {
            selectSupplier(row, existing);
            return;
        }
        const created = await createMaster('supplier', trimmed);
        if (!created) return;
        suppliers.value = [...suppliers.value, created].sort((a, b) => a.name.localeCompare(b.name, 'ja'));
        selectSupplier(row, created);
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
        const [catRes, pkgRes, stRes, supRes, compRes] = await Promise.all([
            api.get('/categories'), api.get('/packages'),
            api.get('/spec-types'), api.get('/suppliers'),
            api.get('/components?per_page=100'),
        ]).catch(() => [{ data: [] }, { data: [] }, { data: [] }, { data: [] }, { data: { data: [] } }]);

        categories.value = catRes.data ?? [];
        packages.value   = pkgRes.data ?? [];
        specTypes.value  = stRes.data  ?? [];
        suppliers.value  = supRes.data ?? [];
        manufacturerOptions.value = normalizeUniqueNames((compRes.data?.data ?? []).map((item) => item.manufacturer));

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
                        supplier_id: cs.supplier_id, supplier_name: cs.supplier?.name ?? '',
                        supplier_part_number: cs.supplier_part_number ?? '',
                        product_url: cs.product_url ?? '', unit_price: cs.unit_price ?? '',
                        is_preferred: cs.is_preferred, price_breaks: cs.price_breaks ?? [],
                    })),
                });
                syncManufacturerQuery();
                ensureManufacturerOption(form.manufacturer);
                currentImageUrl.value = p.image_url ?? '';
                currentDatasheetUrl.value = p.datasheet_url ?? '';
                currentDatasheetName.value = p.datasheet_path ? p.datasheet_path.split('/').pop() : '';
                imagePreviewUrl.value = currentImageUrl.value;
            } catch { toastError('部品情報の取得に失敗しました'); }
        } else {
            syncManufacturerQuery();
        }
    });

    return {
        toasts, isEdit, form, saving,
        imagePreviewUrl, currentImageUrl, currentDatasheetUrl, currentDatasheetName, datasheetFile,
        categories, packages, specTypes, suppliers,
        manufacturerQuery, filteredManufacturers, manufacturerExactMatch,
        categoryQuery, filteredCategories, canCreateCategory,
        packageQuery, filteredPackages, canCreatePackage,
        addSpec, removeSpec, getUnits,
        addSupplier, removeSupplier, addPriceBreak, removePriceBreak,
        selectManufacturer, commitManufacturer,
        toggleCategory, togglePackage, addCategoryFromQuery, addPackageFromQuery,
        filteredSuppliersForRow, canCreateSupplierForRow, selectSupplier, commitSupplier,
        onImageChange, onDatasheetChange,
        submit,
    };
}
