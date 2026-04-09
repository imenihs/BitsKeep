/**
 * 抵抗/容量ネットワーク探索ツール（SCR-011）
 * 直列・並列・分圧の組み合わせ探索
 */
import { ref, reactive, computed } from 'vue';
import { api } from '../api.js';

export default function setup() {
    // ── 入力フォーム ──────────────────────────────────────
    const form = reactive({
        part_type:     'R',         // R | C | divider
        target_raw:    '',          // 入力文字列（単位付き対応）
        tolerance_pct: 5.0,
        series:        'E24',       // E6|E12|E24|E48|E96|custom
        custom_values: '',          // カンマ区切り入力
        min_elements:  1,
        max_elements:  4,
        inventory_only: false,
        circuit_types: ['series', 'parallel', 'mixed'],  // 探索回路種別
        total_res_min_raw: '',
        total_res_max_raw: '',
    });

    // ── 結果 ──────────────────────────────────────────────
    const results    = ref([]);
    const searching  = ref(false);
    const elapsedMs  = ref(null);
    const truncated  = ref(false);
    const error      = ref('');

    // ── 単位変換 ─────────────────────────────────────────
    // 入力値を内部値（Ω or F）に変換
    const parseTarget = (raw, partType) => {
        const s = String(raw).trim().toLowerCase().replace(/,/g, '');
        if (!s) return null;

        if (partType === 'R' || partType === 'divider') {
            if (/^[\d.]+$/.test(s))                return parseFloat(s);
            if (/^([\d.]+)\s*mΩ$/i.test(s))        return parseFloat(s) * 1e-3;
            if (/^([\d.]+)\s*(kohm|kΩ|k)$/i.test(s)) return parseFloat(s) * 1e3;
            if (/^([\d.]+)\s*(mohm|mΩ|m)$/i.test(s)) return parseFloat(s) * 1e6;
            if (/^([\d.]+)\s*%$/.test(s))           return parseFloat(s) / 100; // 分圧比
        }
        if (partType === 'C') {
            if (/^[\d.]+$/.test(s))                return parseFloat(s);
            if (/^([\d.]+)\s*(pf|p)$/i.test(s))    return parseFloat(s) * 1e-12;
            if (/^([\d.]+)\s*(nf|n)$/i.test(s))    return parseFloat(s) * 1e-9;
            if (/^([\d.]+)\s*(uf|μf|u)$/i.test(s)) return parseFloat(s) * 1e-6;
            if (/^([\d.]+)\s*(mf)$/i.test(s))      return parseFloat(s) * 1e-3;
        }
        const n = parseFloat(s);
        return isNaN(n) ? null : n;
    };

    const targetValue = computed(() => parseTarget(form.target_raw, form.part_type));

    const targetValid = computed(() => {
        const v = targetValue.value;
        if (v === null) return false;
            if (form.part_type === 'divider') return v > 0 && v < 1;
            return v > 0;
    });

    // ── 探索実行 ─────────────────────────────────────────
    const search = async () => {
        if (!targetValid.value) { error.value = '有効な目標値を入力してください'; return; }
        error.value = '';
        searching.value = true;
        results.value   = [];

        try {
            const payload = {
                target:         targetValue.value,
                tolerance_pct:  form.tolerance_pct,
                part_type:      form.part_type,
                series:         form.series,
                min_elements:   form.min_elements,
                max_elements:   form.max_elements,
                inventory_only: form.inventory_only,
                circuit_types:  form.circuit_types,
            };
            if (form.part_type === 'divider') {
                payload.total_res_min = parseTarget(form.total_res_min_raw, 'R') ?? 0;
                payload.total_res_max = parseTarget(form.total_res_max_raw, 'R') ?? null;
            }

            if (form.series === 'custom') {
                payload.custom_values = form.custom_values
                    .split(',')
                    .map(v => parseTarget(v.trim(), form.part_type))
                    .filter(v => v !== null && v > 0);
            }

            const r = await api.post('/calc/networks/search', payload);
            const data = r.data?.data ?? r.data;
            results.value = data.candidates ?? [];
            elapsedMs.value = data.elapsed_ms ?? null;
            truncated.value = data.truncated ?? false;
        } catch (e) {
            error.value = e.message ?? '探索中にエラーが発生しました';
        } finally {
            searching.value = false;
        }
    };

    // ── 表示ヘルパー ─────────────────────────────────────
    const circuitTypeLabel = (t) => ({ series: '直列', parallel: '並列', divider: '分圧', single: '単体', mixed: '複雑' }[t] ?? t);

    const errorClass = (errPct) => {
        if (errPct <= 1)   return 'text-emerald-600';
        if (errPct <= 5)   return 'text-amber-600';
        return 'text-red-600';
    };

    const toggleCircuitType = (type) => {
        const idx = form.circuit_types.indexOf(type);
        if (idx >= 0) form.circuit_types.splice(idx, 1);
        else form.circuit_types.push(type);
    };

    const partTypeLabel = computed(() => ({
        R: '抵抗', C: 'コンデンサ', divider: '分圧回路'
    }[form.part_type] ?? form.part_type));

    const targetHint = computed(() => ({
        R: '例: 4.7kΩ → 4.7k または 4700',
        C: '例: 100nF → 100n または 100e-9',
        divider: '例: 0.5 または 50% (Vout/Vin)',
    }[form.part_type] ?? ''));

    return {
        form, results, searching, elapsedMs, truncated, error,
        targetValue, targetValid, partTypeLabel, targetHint,
        search, circuitTypeLabel, errorClass, toggleCircuitType,
    };
}
