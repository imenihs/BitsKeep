/**
 * 抵抗/容量ネットワーク探索ツール（SCR-011）
 * 直列・並列・分圧の組み合わせ探索
 */
import { ref, reactive, computed } from 'vue';
import { api } from '../api.js';

export default function setup() {
    const activeMode = ref('network');
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
    const presets = [
        { label: '1kΩ 抵抗/E24', type: 'R', target: '1k', tolerance: 5, series: 'E24', circuits: ['series', 'parallel'] },
        { label: '10kΩ 分圧 1/2', type: 'divider', target: '50%', tolerance: 1, series: 'E24', circuits: ['divider'], min: '1k', max: '100k' },
        { label: '100nF 容量/E12', type: 'C', target: '100n', tolerance: 10, series: 'E12', circuits: ['parallel'] },
    ];
    const applyPreset = (preset) => {
        form.part_type = preset.type;
        form.target_raw = preset.target;
        form.tolerance_pct = preset.tolerance;
        form.series = preset.series;
        form.circuit_types = [...preset.circuits];
        form.total_res_min_raw = preset.min ?? '';
        form.total_res_max_raw = preset.max ?? '';
    };

    // ── 単位変換 ─────────────────────────────────────────
    // 入力値を内部値（Ω or F）に変換
    const parseTarget = (raw, partType) => {
        const s = String(raw).trim()
            .replace(/,/g, '')
            .replace(/[Ω]/gu, 'Ω')
            .replace(/[µμ]/gu, 'u');
        if (!s) return null;

        const match = s.match(/^([+-]?(?:\d+(?:\.\d*)?|\.\d+)(?:e[+-]?\d+)?)\s*([A-Za-zΩ%]*)$/u);
        if (!match) {
            const n = parseFloat(s);
            return isNaN(n) ? null : n;
        }

        const value = parseFloat(match[1]);
        const unit = match[2] ?? '';

        if (partType === 'R' || partType === 'divider') {
            if (unit === '%') return value / 100; // 分圧比
            if (unit === '' || /^Ω$/iu.test(unit) || /^ohms?$/iu.test(unit)) return value;
            if (/^(k|kΩ|kohms?)$/iu.test(unit)) return value * 1e3;
            if (/^(M|MΩ|M[oO]hms?|meg|megohms?)$/u.test(unit)) return value * 1e6;
            if (/^(m|mΩ)$/u.test(unit)) return value * 1e-3;
            if (/^(u|uΩ)$/u.test(unit)) return value * 1e-6;
        }
        if (partType === 'C') {
            if (unit === '' || /^F$/u.test(unit)) return value;
            if (/^(p|pF)$/u.test(unit)) return value * 1e-12;
            if (/^(n|nF)$/u.test(unit)) return value * 1e-9;
            if (/^(u|uF)$/u.test(unit)) return value * 1e-6;
            if (/^(m|mF)$/u.test(unit)) return value * 1e-3;
        }

        return value;
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

    const variable = reactive({
        total_raw: '10k',
        span_mode: 'percent',
        span_raw: '20',
        circuit: 'series',
    });
    const variableResult = computed(() => {
        const total = parseTarget(variable.total_raw, 'R') ?? 0;
        const spanInput = variable.span_mode === 'percent'
            ? ((parseFloat(String(variable.span_raw).replace('%', '')) || 0) / 100)
            : (parseTarget(variable.span_raw, 'R') ?? 0);
        const span = variable.span_mode === 'percent' ? total * spanInput : spanInput;
        const pot = Math.max(0, Math.min(total, span));
        const fixed = Math.max(0, total - pot);
        const low = variable.circuit === 'series' ? fixed : (fixed > 0 && pot > 0 ? 1 / (1 / fixed + 1 / pot) : 0);
        const high = variable.circuit === 'series' ? fixed + pot : fixed;

        return {
            totalDisplay: formatResistance(total),
            potDisplay: formatResistance(pot),
            fixedDisplay: formatResistance(fixed),
            rangeDisplay: `${formatResistance(low)} 〜 ${formatResistance(high)}`,
            valid: total > 0 && pot > 0,
        };
    });

    const formatResistance = (value) => {
        if (!Number.isFinite(value) || value <= 0) return '0Ω';
        if (value >= 1e6) return `${(value / 1e6).toFixed(3).replace(/\.?0+$/, '')}MΩ`;
        if (value >= 1e3) return `${(value / 1e3).toFixed(3).replace(/\.?0+$/, '')}kΩ`;
        if (value < 1) return `${(value * 1000).toFixed(3).replace(/\.?0+$/, '')}mΩ`;
        return `${value.toFixed(3).replace(/\.?0+$/, '')}Ω`;
    };

    return {
        activeMode,
        form, results, searching, elapsedMs, truncated, error,
        presets, applyPreset,
        targetValue, targetValid, partTypeLabel, targetHint,
        search, circuitTypeLabel, errorClass, toggleCircuitType,
        variable, variableResult,
    };
}
