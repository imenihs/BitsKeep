import { ref, computed, watch } from 'vue';

const HISTORY_KEY = 'bitskeep-calc-history';
const FAVORITES_KEY = 'bitskeep-calc-favorites';

const CONSTANTS = {
    pi: Math.PI,
    e: Math.E,
    c: 299792458,
    h: 6.62607015e-34,
    k: 1.380649e-23,
    q: 1.602176634e-19,
    eps0: 8.854187817e-12,
    mu0: 1.2566370614e-6,
};

const SAFE_FUNCTIONS = {
    sin: Math.sin, cos: Math.cos, tan: Math.tan,
    asin: Math.asin, acos: Math.acos, atan: Math.atan, atan2: Math.atan2,
    sinh: Math.sinh, cosh: Math.cosh, tanh: Math.tanh,
    sqrt: Math.sqrt, cbrt: Math.cbrt,
    log: Math.log, ln: Math.log, log2: Math.log2, log10: Math.log10,
    exp: Math.exp, pow: Math.pow,
    abs: Math.abs, ceil: Math.ceil, floor: Math.floor, round: Math.round,
    min: Math.min, max: Math.max,
};

const E_SERIES = {
    E6: [1.0, 1.5, 2.2, 3.3, 4.7, 6.8],
    E12: [1.0, 1.2, 1.5, 1.8, 2.2, 2.7, 3.3, 3.9, 4.7, 5.6, 6.8, 8.2],
    E24: [1.0, 1.1, 1.2, 1.3, 1.5, 1.6, 1.8, 2.0, 2.2, 2.4, 2.7, 3.0, 3.3, 3.6, 3.9, 4.3, 4.7, 5.1, 5.6, 6.2, 6.8, 7.5, 8.2, 9.1],
    E96: [1.00,1.02,1.05,1.07,1.10,1.13,1.15,1.18,1.21,1.24,1.27,1.30,1.33,1.37,1.40,1.43,1.47,1.50,1.54,1.58,1.62,1.65,1.69,1.74,1.78,1.82,1.87,1.91,1.96,2.00,2.05,2.10,2.15,2.21,2.26,2.32,2.37,2.43,2.49,2.55,2.61,2.67,2.74,2.80,2.87,2.94,3.01,3.09,3.16,3.24,3.32,3.40,3.48,3.57,3.65,3.74,3.83,3.92,4.02,4.12,4.22,4.32,4.42,4.53,4.64,4.75,4.87,4.99,5.11,5.23,5.36,5.49,5.62,5.76,5.90,6.04,6.19,6.34,6.49,6.65,6.81,6.98,7.15,7.32,7.50,7.68,7.87,8.06,8.25,8.45,8.66,8.87,9.09,9.31,9.53,9.76],
};

function storageGet(key, fallback) {
    try {
        return JSON.parse(window.localStorage.getItem(key) ?? 'null') ?? fallback;
    } catch {
        return fallback;
    }
}

function storageSet(key, value) {
    window.localStorage.setItem(key, JSON.stringify(value));
}

function formatNum(n) {
    if (n === null || n === undefined || Number.isNaN(n)) return '-';
    if (!Number.isFinite(n)) return String(n);
    if (Number.isInteger(n) && Math.abs(n) < 1e15) return n.toLocaleString();
    return Number(n).toPrecision(10).replace(/\.?0+$/u, '').replace(/e\+?/u, 'e');
}

function formatEngineering(n) {
    if (!Number.isFinite(n) || n === 0) return String(n ?? 0);
    const prefixes = [
        [-12, 'p'], [-9, 'n'], [-6, 'u'], [-3, 'm'],
        [0, ''], [3, 'k'], [6, 'M'], [9, 'G'],
    ];
    const exp = Math.floor(Math.log10(Math.abs(n)) / 3) * 3;
    const normalized = prefixes.find(([p]) => p === Math.max(-12, Math.min(9, exp))) ?? [0, ''];
    const value = n / Math.pow(10, normalized[0]);
    return `${Number(value.toPrecision(6)).toString()}${normalized[1]}`;
}

function nearestESeries(seriesName, value) {
    const table = E_SERIES[seriesName] ?? E_SERIES.E24;
    if (!value || value <= 0) return value;
    const exp = Math.floor(Math.log10(value));
    let best = null;
    for (let decade = exp - 1; decade <= exp + 1; decade += 1) {
        const base = 10 ** decade;
        for (const m of table) {
            const candidate = m * base;
            const err = Math.abs(candidate - value);
            if (!best || err < best.err) best = { value: candidate, err };
        }
    }
    return best?.value ?? value;
}

function transformExpression(expr) {
    return expr
        .replace(/\r/g, '')
        .replace(/\^/g, '**')
        .replace(/(\d+(?:\.\d+)?(?:e[+\-]?\d+)?)([GMkmunpf])\b/g, (_, num, suffix) => {
            const map = { G: 'e9', M: 'e6', k: 'e3', m: 'e-3', u: 'e-6', n: 'e-9', p: 'e-12', f: 'e-15' };
            return `${num}${map[suffix]}`;
        });
}

function solveEquation(fnOrExpr, guess = 1, maxIter = 32, tol = 1e-9) {
    const fn = typeof fnOrExpr === 'function'
        ? fnOrExpr
        : (x) => safeEval(String(fnOrExpr).replace(/\bx\b/g, `(${x})`), {});
    let x = Number(guess);
    for (let i = 0; i < maxIter; i += 1) {
        const y = Number(fn(x));
        const dx = Math.max(Math.abs(x) * 1e-6, 1e-9);
        const dy = (Number(fn(x + dx)) - y) / dx;
        if (!Number.isFinite(y) || !Number.isFinite(dy) || Math.abs(dy) < 1e-15) break;
        const next = x - y / dy;
        if (Math.abs(next - x) < tol) return next;
        x = next;
    }
    return x;
}

function safeEval(expr, scope = {}) {
    const sanitized = transformExpression(expr).replace(/[;{}[\]`]/g, '').replace(/\bimport\b|\brequire\b|\beval\b|\bFunction\b/g, '');
    const helpers = {
        ...CONSTANTS,
        ...SAFE_FUNCTIONS,
        eng: (v) => formatEngineering(Number(v)),
        esRound: (series, value) => nearestESeries(series, Number(value)),
        solve: (fnOrExpr, guess) => solveEquation(fnOrExpr, guess),
    };
    const keys = [...Object.keys(helpers), ...Object.keys(scope)];
    const vals = [...Object.values(helpers), ...Object.values(scope)];
    // eslint-disable-next-line no-new-func
    const fn = new Function(...keys, `"use strict"; return (${sanitized});`);
    return fn(...vals);
}

function evaluateProgram(program) {
    const lines = program.split('\n').map((line) => line.trim()).filter(Boolean);
    const scope = {};
    let lastValue = null;
    let lastExpr = '';
    for (const line of lines) {
        const m = line.match(/^([a-zA-Z_]\w*)\s*=\s*(.+)$/u);
        if (m) {
            scope[m[1]] = safeEval(m[2], scope);
            lastValue = scope[m[1]];
            lastExpr = line;
        } else {
            lastValue = safeEval(line, scope);
            lastExpr = line;
        }
    }
    return { value: lastValue, expr: lastExpr, scope };
}

export default function setup() {
    const expr = ref(`0xff + 25k
esRound("E24", 4.83k)
solve(x => x*x - 2, 1.4)`);
    const error = ref('');
    const result = ref(null);
    const resultType = ref('number');
    const lastScope = ref({});
    const history = ref(storageGet(HISTORY_KEY, []));
    const favorites = ref(storageGet(FAVORITES_KEY, []));

    const snippets = [
        { label: '進数混在', value: '0xff + 25k' },
        { label: 'E系列丸め', value: 'esRound("E24", 4.83k)' },
        { label: '数値解法', value: 'solve(x => x*x - 2, 1.4)' },
        { label: 'ビット操作', value: '(0b101101 << 2) | 0x03' },
        { label: '複数行', value: 'vin = 5\nr1 = 10k\nr2 = 3.3k\nvin * r2 / (r1 + r2)' },
    ];

    const presetItems = [
        { name: 'esRound(series, value)', desc: 'E系列最近傍値へ丸め' },
        { name: 'solve(expr, guess)', desc: 'Newton法で近似解' },
        { name: 'eng(value)', desc: '工学表記へ整形' },
        { name: 'pi, e, c, k, q', desc: '定数プリセット' },
    ];

    const intResult = computed(() => Number.isInteger(result.value) ? Number(result.value) : null);
    const decResult = computed(() => result.value === null ? '-' : formatNum(result.value));
    const engResult = computed(() => typeof result.value === 'number' ? formatEngineering(result.value) : '-');
    const hexResult = computed(() => intResult.value !== null ? `0x${intResult.value.toString(16).toUpperCase()}` : '-');
    const binResult = computed(() => intResult.value !== null ? `0b${intResult.value.toString(2)}` : '-');
    const octResult = computed(() => intResult.value !== null ? `0${intResult.value.toString(8)}` : '-');
    const scopeEntries = computed(() => Object.entries(lastScope.value));

    const run = (pushHistory = false) => {
        if (!expr.value.trim()) {
            result.value = null;
            error.value = '';
            return;
        }
        try {
            const evaluated = evaluateProgram(expr.value);
            result.value = evaluated.value;
            resultType.value = typeof evaluated.value;
            lastScope.value = evaluated.scope;
            error.value = '';
            if (pushHistory) {
                history.value.unshift({
                    id: Date.now(),
                    expr: expr.value,
                    result: formatNum(evaluated.value),
                    meta: `${resultType.value} / ${engResult.value}`,
                });
                history.value = history.value.slice(0, 40);
                storageSet(HISTORY_KEY, history.value);
            }
        } catch (e) {
            error.value = `計算エラー: ${e.message}`;
            result.value = null;
            lastScope.value = {};
        }
    };

    watch(expr, () => run(false));

    const clearCalc = () => {
        expr.value = '';
        result.value = null;
        error.value = '';
        lastScope.value = {};
    };

    const useHistory = (item) => {
        expr.value = item.expr;
        run(false);
    };

    const clearHistory = () => {
        history.value = [];
        storageSet(HISTORY_KEY, []);
    };

    const saveFavorite = () => {
        if (!expr.value.trim()) return;
        favorites.value.unshift({ id: Date.now(), expr: expr.value });
        favorites.value = favorites.value.slice(0, 12);
        storageSet(FAVORITES_KEY, favorites.value);
    };

    const useFavorite = (item) => {
        expr.value = item.expr;
        run(false);
    };

    const copyResult = async () => {
        if (result.value === null) return;
        await navigator.clipboard.writeText(String(result.value));
    };

    run(false);

    return {
        expr, error, result, resultType, history, favorites,
        snippets, presetItems, scopeEntries,
        decResult, engResult, hexResult, binResult, octResult,
        run, clearCalc, useHistory, clearHistory, saveFavorite, useFavorite, copyResult, formatNum,
    };
}
