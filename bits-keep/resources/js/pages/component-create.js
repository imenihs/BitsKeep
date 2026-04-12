import { ref, reactive, onMounted, computed, watch } from 'vue';
import { api } from '../api.js';
import { useToast } from '../composables/useToast.js';
import { useNavigationConfirm } from '../composables/useNavigationConfirm.js';

export default function setup() {
    const { toasts, toastSuccess, toastError } = useToast();
    const appEl = document.getElementById('app');
    const editId = appEl?.dataset?.id ?? null; // 編集時はID、新規はnull
    const isEdit = !!editId;
    const duplicateFromId = new URLSearchParams(window.location.search).get('duplicate_from');

    // ── マスタデータ ──────────────────────────────────────
    const categories = ref([]);
    const packageGroups = ref([]);
    const packages   = ref([]);
    const specTypes  = ref([]);
    const suppliers  = ref([]);
    const locations  = ref([]);
    const manufacturerOptions = ref([]);

    // ── フォーム ──────────────────────────────────────────
    const form = reactive({
        part_number: '', manufacturer: '', common_name: '', description: '',
        procurement_status: 'active',
        threshold_new: 0, threshold_used: 0,
        primary_location_id: '',
        category_ids: [],
        package_group_id: '',
        package_id: '',
        specs: [],       // [{ spec_type_id, value, unit, value_numeric }]
        supplierRows: [], // [{ supplier_id, supplier_part_number, product_url, unit_price, is_preferred, price_breaks:[] }]
    });
    const imageFile = ref(null);
    const datasheetFiles = ref([]);
    const imagePreviewUrl = ref('');
    const currentImageUrl = ref('');
    const currentDatasheets = ref([]);

    const saving = ref(false);
    const dirty = ref(false);
    const initialSnapshot = ref('');
    const masterLoadError = ref('');
    useNavigationConfirm(dirty, '未保存の入力があります。このまま画面を離れてもよいですか？');
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
        const scopedPackages = form.package_group_id
            ? packages.value.filter((item) => item.package_group_id === Number(form.package_group_id))
            : [];
        if (!q) return scopedPackages;
        return scopedPackages.filter((item) => item.name.toLowerCase().includes(q));
    });
    const canCreateCategory = computed(() => {
        const q = categoryQuery.value.trim();
        if (!q) return false;
        return !categories.value.some((item) => item.name.toLowerCase() === q.toLowerCase());
    });
    const canCreatePackage = computed(() => {
        const q = packageQuery.value.trim();
        if (!q || !form.package_group_id) return false;
        return !packages.value.some((item) => item.package_group_id === Number(form.package_group_id) && item.name.toLowerCase() === q.toLowerCase());
    });
    const canCreateSupplier = computed(() => appEl?.dataset?.canCreateSupplier === '1');

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
        purchase_unit: '',
        unit_price: '',
        is_preferred: false,
        price_breaks: [],
    });
    const addSupplier = () => form.supplierRows.push(createSupplierRow());
    const removeSupplier = (i) => form.supplierRows.splice(i, 1);
    const addPriceBreak = (row) => row.price_breaks.push({ min_qty: 1, unit_price: '' });
    const removePriceBreak = (row, i) => row.price_breaks.splice(i, 1);

    const createMaster = async (type, name, extra = {}) => {
        const trimmed = name.trim();
        if (!trimmed) return null;
        try {
            const endpoints = { category: '/categories', package: '/packages', supplier: '/suppliers' };
            const res = await api.post(endpoints[type], { name: trimmed, ...extra });
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
    const selectPackage = (id) => {
        form.package_id = id;
    };
    const addCategoryFromQuery = async () => {
        const created = await createMaster('category', categoryQuery.value);
        if (!created) return;
        categories.value = [...categories.value, created].sort((a, b) => a.name.localeCompare(b.name, 'ja'));
        toggleCategory(created.id);
        categoryQuery.value = '';
    };
    const addPackageFromQuery = async () => {
        const created = await createMaster('package', packageQuery.value, { package_group_id: form.package_group_id });
        if (!created) return;
        packages.value = [...packages.value, created].sort((a, b) => a.name.localeCompare(b.name, 'ja'));
        form.package_id = created.id;
        packageQuery.value = '';
    };

    const filteredSuppliersForRow = (row) => {
        const q = (row.supplier_name ?? '').trim().toLowerCase();
        if (!q) return suppliers.value.slice(0, 8);
        return suppliers.value.filter((item) => item.name.toLowerCase().includes(q)).slice(0, 8);
    };
    const canCreateSupplierForRow = (row) => {
        if (!canCreateSupplier.value) return false;
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
        datasheetFiles.value = Array.from(event.target.files ?? []);
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
        payload.append('primary_location_id', form.primary_location_id ? String(form.primary_location_id) : '');

        form.category_ids.forEach((categoryId, index) => {
            payload.append(`category_ids[${index}]`, String(categoryId));
        });
        payload.append('package_group_id', form.package_group_id ? String(form.package_group_id) : '');
        payload.append('package_id', form.package_id ? String(form.package_id) : '');
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
            payload.append(`suppliers[${index}][purchase_unit]`, row.purchase_unit ?? '');
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
        if (duplicateFromId && !isEdit) {
            payload.append('duplicate_from_component_id', duplicateFromId);
        }
        datasheetFiles.value.forEach((file, index) => {
            payload.append(`datasheets[${index}]`, file);
        });

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
                dirty.value = false;
                setTimeout(() => { location.href = `/components/${editId}`; }, 800);
            } else {
                const res = await api.upload('/components', payload);
                toastSuccess('登録しました');
                dirty.value = false;
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
        const [catRes, groupRes, pkgRes, stRes, supRes, compRes, locRes] = await Promise.all([
            api.get('/categories'), api.get('/package-groups'), api.get('/packages'),
            api.get('/spec-types'), api.get('/suppliers'),
            api.get('/components?per_page=100'),
            api.get('/locations'),
        ]).catch(() => {
            masterLoadError.value = '初期データの取得に失敗しました。再読込するか、マスタ管理を確認してください。';
            return [{ data: [] }, { data: [] }, { data: [] }, { data: [] }, { data: [] }, { data: { data: [] } }, { data: [] }];
        });

        categories.value = catRes.data ?? [];
        packageGroups.value = groupRes.data ?? [];
        packages.value   = pkgRes.data ?? [];
        specTypes.value  = stRes.data  ?? [];
        suppliers.value  = supRes.data ?? [];
        locations.value  = locRes.data ?? [];
        manufacturerOptions.value = normalizeUniqueNames((compRes.data?.data ?? []).map((item) => item.manufacturer));

        // 編集モードなら既存データをロード
        const loadSourceComponent = async (id) => {
            const res = await api.get(`/components/${id}`);
            const p = res.data;
            Object.assign(form, {
                part_number: p.part_number, manufacturer: p.manufacturer ?? '',
                common_name: p.common_name ?? '', description: p.description ?? '',
                procurement_status: p.procurement_status,
                threshold_new: p.threshold_new, threshold_used: p.threshold_used,
                primary_location_id: p.primary_location_id ?? '',
                category_ids: p.categories.map(c => c.id),
                package_group_id: p.package_group?.id ?? p.package?.package_group_id ?? '',
                package_id: p.package?.id ?? p.packages?.[0]?.id ?? '',
                specs: p.specs.map(s => ({ spec_type_id: s.spec_type_id, value: s.value ?? '', unit: s.unit ?? '', value_numeric: s.value_numeric })),
                supplierRows: p.component_suppliers.map(cs => ({
                    supplier_id: cs.supplier_id, supplier_name: cs.supplier?.name ?? '',
                    supplier_part_number: cs.supplier_part_number ?? '',
                    product_url: cs.product_url ?? '', purchase_unit: cs.purchase_unit ?? '', unit_price: cs.unit_price ?? '',
                    is_preferred: cs.is_preferred, price_breaks: cs.price_breaks ?? [],
                })),
            });
            syncManufacturerQuery();
            ensureManufacturerOption(form.manufacturer);
            currentImageUrl.value = p.image_url ?? '';
            currentDatasheets.value = (p.datasheets ?? []).map((sheet) => ({
                name: sheet.original_name || sheet.file_path.split('/').pop(),
                url: sheet.url,
            }));
            imagePreviewUrl.value = currentImageUrl.value;
        };

        if (isEdit) {
            try {
                await loadSourceComponent(editId);
            } catch { toastError('部品情報の取得に失敗しました'); }
        } else if (duplicateFromId) {
            try {
                await loadSourceComponent(duplicateFromId);
                form.part_number = '';
                form.common_name = form.common_name ? `${form.common_name} コピー` : '';
                toastSuccess('複製元を読み込みました。型番と差分だけ調整してください。');
            } catch {
                toastError('複製元部品の取得に失敗しました');
            }
        } else {
            syncManufacturerQuery();
        }

        initialSnapshot.value = JSON.stringify(form);
    });

    watch(form, () => {
        if (!initialSnapshot.value || saving.value) return;
        dirty.value = JSON.stringify(form) !== initialSnapshot.value || !!imageFile.value || datasheetFiles.value.length > 0;
    }, { deep: true });

    watch(() => form.package_group_id, (groupId, previousGroupId) => {
        if (!groupId) {
            form.package_id = '';
            packageQuery.value = '';
            return;
        }

        const selectedPackage = packages.value.find((item) => item.id === Number(form.package_id));
        if (!selectedPackage || selectedPackage.package_group_id !== Number(groupId)) {
            form.package_id = '';
        }

        if (groupId !== previousGroupId) {
            packageQuery.value = '';
        }
    });

    return {
        toasts, isEdit, form, saving, dirty, locations, masterLoadError, canCreateSupplier,
        imagePreviewUrl, currentImageUrl, currentDatasheets, datasheetFiles,
        categories, packageGroups, packages, specTypes, suppliers,
        manufacturerQuery, filteredManufacturers, manufacturerExactMatch,
        categoryQuery, filteredCategories, canCreateCategory,
        packageQuery, filteredPackages, canCreatePackage,
        addSpec, removeSpec, getUnits,
        addSupplier, removeSupplier, addPriceBreak, removePriceBreak,
        selectManufacturer, commitManufacturer,
        toggleCategory, selectPackage, addCategoryFromQuery, addPackageFromQuery,
        filteredSuppliersForRow, canCreateSupplierForRow, selectSupplier, commitSupplier,
        onImageChange, onDatasheetChange,
        submit, duplicateFromId,
    };
}
