/**
 * エンジニア電卓（SCR-015）
 * - 式入力・評価（math.js不使用、Function評価）
 * - 進数表示（DEC/HEX/BIN/OCT）
 * - 定数プリセット（電気工学定数）
 * - 履歴（sessionStorage）
 */
import { ref, reactive, computed } from 'vue';

// 利用可能な定数・関数（安全な評価環境用）
const CONSTANTS = {
    pi: Math.PI, e: Math.E,
    // 物理定数
    c: 299792458,           // 光速 m/s
    h: 6.626e-34,           // プランク定数
    k: 1.380649e-23,        // ボルツマン定数
    q: 1.602176634e-19,     // 電気素量
    eps0: 8.854187817e-12,  // 真空の誘電率
    mu0: 1.2566370614e-6,   // 真空の透磁率
};

const SAFE_FUNCTIONS = {
    sin: Math.sin, cos: Math.cos, tan: Math.tan,
    asin: Math.asin, acos: Math.acos, atan: Math.atan, atan2: Math.atan2,
    sinh: Math.sinh, cosh: Math.cosh, tanh: Math.tanh,
    sqrt: Math.sqrt, cbrt: Math.cbrt,
    log: Math.log, log2: Math.log2, log10: Math.log10,
    exp: Math.exp, pow: Math.pow,
    abs: Math.abs, ceil: Math.ceil, floor: Math.floor, round: Math.round,
    min: Math.min, max: Math.max,
};

function safeEval(expr) {
    // 危険な構文を除去
    const sanitized = expr
        .replace(/[;{}[\]`]/g, '')      // 危険文字除去
        .replace(/\bimport\b|\brequire\b|\beval\b|\bFunction\b/g, '');

    // 定数・関数を展開してFunction評価
    const keys   = [...Object.keys(CONSTANTS), ...Object.keys(SAFE_FUNCTIONS)];
    const vals   = [...Object.values(CONSTANTS), ...Object.values(SAFE_FUNCTIONS)];
    // eslint-disable-next-line no-new-func
    const fn = new Function(...keys, `"use strict"; return (${sanitized});`);
    return fn(...vals);
}

export default function setup() {
    const expr    = ref('');
    const result  = ref(null);
    const error   = ref('');
    const history = ref(JSON.parse(sessionStorage.getItem('calc_history') ?? '[]'));
    const activeBase = ref('DEC');   // DEC/HEX/BIN/OCT

    const intResult = computed(() => {
        if (result.value === null || !Number.isInteger(result.value)) return null;
        return result.value;
    });

    const hexResult = computed(() => intResult.value !== null ? '0x' + intResult.value.toString(16).toUpperCase() : null);
    const binResult = computed(() => intResult.value !== null ? '0b' + intResult.value.toString(2) : null);
    const octResult = computed(() => intResult.value !== null ? '0' + intResult.value.toString(8) : null);

    const evaluate = () => {
        if (!expr.value.trim()) return;
        error.value = '';
        try {
            const val = safeEval(expr.value);
            if (typeof val !== 'number' || !isFinite(val)) throw new Error('数値以外の結果');
            result.value = val;
            // 履歴に追加
            history.value.unshift({ expr: expr.value, result: val, ts: Date.now() });
            history.value = history.value.slice(0, 30);  // 最大30件
            sessionStorage.setItem('calc_history', JSON.stringify(history.value));
        } catch (e) {
            error.value = '計算エラー: ' + e.message;
            result.value = null;
        }
    };

    const clearCalc = () => { expr.value = ''; result.value = null; error.value = ''; };

    const insertConst = (key) => { expr.value += key; };
    const useHistory = (h) => { expr.value = h.expr; result.value = h.result; };
    const clearHistory = () => { history.value = []; sessionStorage.removeItem('calc_history'); };

    const formatNum = (n) => {
        if (n === null || n === undefined) return '-';
        if (Number.isInteger(n)) return n.toLocaleString();
        return n.toPrecision(10).replace(/\.?0+$/, '');
    };

    const PRESET_CONSTANTS = Object.entries(CONSTANTS).map(([k, v]) => ({
        key: k, value: formatNum(v),
        label: {
            pi: 'π', e: 'e', c: '光速(c)', h: 'プランク(h)', k: 'ボルツマン(k)',
            q: '電気素量(q)', eps0: 'ε₀', mu0: 'μ₀',
        }[k] ?? k
    }));

    return { expr, result, error, history, activeBase, hexResult, binResult, octResult,
             evaluate, clearCalc, insertConst, useHistory, clearHistory, formatNum, PRESET_CONSTANTS };
}
