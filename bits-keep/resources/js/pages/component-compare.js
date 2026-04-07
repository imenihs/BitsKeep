/**
 * 部品比較ページ（SCR-004）
 * URL: ?ids=1,2,3 または 一覧ページから比較リスト経由
 */
import { ref, computed, onMounted } from 'vue';
import { api } from '../api.js';
import { useToast } from '../composables/useToast.js';

export default function setup() {
    const { toasts, toastError } = useToast();
    const specTypes  = ref([]);
    const components = ref([]);
    const loading    = ref(false);

    const fetchCompare = async (ids) => {
        if (ids.length < 2) return;
        loading.value = true;
        try {
            const params = ids.map(id => `ids[]=${id}`).join('&');
            const r = await api.get(`/components/compare?${params}`);
            specTypes.value  = r.data.spec_types;
            components.value = r.data.components;
        } catch { toastError('比較データの取得に失敗しました'); }
        finally { loading.value = false; }
    };

    onMounted(() => {
        const params  = new URLSearchParams(window.location.search);
        const idsStr  = params.get('ids') ?? '';
        const ids     = idsStr.split(',').map(Number).filter(Boolean);
        if (ids.length >= 2) fetchCompare(ids);
    });

    // 差分ハイライト: 同一スペック種別で値が全部同じなら差分なし
    const hasDiff = (specTypeId) => {
        const values = components.value.map(c => c.specs[specTypeId]?.value_numeric);
        const nonNull = values.filter(v => v !== null && v !== undefined);
        if (nonNull.length < 2) return false;
        return new Set(nonNull).size > 1;
    };

    const statusLabel = (s) => ({ active: '入手可', nrnd: 'NRND', eol: 'EOL', custom: 'カスタム' }[s] ?? s);
    const statusClass = (s) => ({ active: 'tag-ok', nrnd: 'tag-warning', eol: 'tag-eol' }[s] ?? '');

    return { toasts, specTypes, components, loading, hasDiff, statusLabel, statusClass };
}
