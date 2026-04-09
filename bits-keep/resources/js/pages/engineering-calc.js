import { ref, computed, watch } from 'vue';
import { all, create } from 'mathjs';

const HISTORY_KEY = 'bitskeep-calc-history';
const FAVORITES_KEY = 'bitskeep-calc-favorites';
const math = create(all);
const PREFIX_MAP = {
    Ti: '*1099511627776',
    Gi: '*1073741824',
    Mi: '*1048576',
    Ki: '*1024',
    T: 'e12',
    G: 'e9',
    M: 'e6',
    k: 'e3',
    m: 'e-3',
    u: 'e-6',
    n: 'e-9',
    p: 'e-12',
    f: 'e-15',
};
const BIT_WIDTH_OPTIONS = [8, 12, 16, 24, 32, 48, 64];

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

const FUNCTION_GROUPS = [
    {
        key: 'basic',
        label: '基本',
        items: [
            { name: 'abs(x)', desc: '絶対値', example: 'abs(-12)' },
            { name: 'round(x)', desc: '四捨五入', example: 'round(3.6)' },
            { name: 'floor(x)', desc: '切り捨て', example: 'floor(3.6)' },
            { name: 'ceil(x)', desc: '切り上げ', example: 'ceil(3.2)' },
            { name: 'min(a, b)', desc: '小さい方', example: 'min(3, 8)' },
            { name: 'max(a, b)', desc: '大きい方', example: 'max(3, 8)' },
        ],
    },
    {
        key: 'trig',
        label: '三角',
        items: [
            { name: 'sin(x)', desc: '正弦', example: 'sin(pi / 6)' },
            { name: 'cos(x)', desc: '余弦', example: 'cos(pi / 3)' },
            { name: 'tan(x)', desc: '正接', example: 'tan(pi / 4)' },
            { name: 'asin(x)', desc: '逆正弦', example: 'asin(0.5)' },
            { name: 'acos(x)', desc: '逆余弦', example: 'acos(0.5)' },
            { name: 'atan(x)', desc: '逆正接', example: 'atan(1)' },
        ],
    },
    {
        key: 'log',
        label: '指数',
        items: [
            { name: 'sqrt(x)', desc: '平方根', example: 'sqrt(2)' },
            { name: 'cbrt(x)', desc: '立方根', example: 'cbrt(8)' },
            { name: 'pow(a, b)', desc: 'べき乗', example: 'pow(2, 10)' },
            { name: 'exp(x)', desc: 'eのx乗', example: 'exp(1)' },
            { name: 'ln(x)', desc: '自然対数', example: 'ln(10)' },
            { name: 'log10(x)', desc: '常用対数', example: 'log10(1000)' },
        ],
    },
    {
        key: 'eng',
        label: '設計',
        items: [
            { name: 'esRound(series, value)', desc: 'E系列へ丸め', example: 'esRound("E24", 4.83k)' },
            { name: 'eng(value)', desc: '工学表記文字列', example: 'eng(0.000047)' },
            { name: 'solve(eq, var)', desc: '方程式を解く', example: 'solve(10=1/((1/x)+(1/20)), x)' },
            { name: 'solve(fn, guess)', desc: '従来の近似解法', example: 'solve(x => x*x - 2, 1.4)' },
        ],
    },
    {
        key: 'logic',
        label: '論理',
        items: [
            { name: 'and(a, b, ...)', desc: 'ビットAND', example: 'and(0b1100, 0b1010)' },
            { name: 'or(a, b, ...)', desc: 'ビットOR', example: 'or(0b1100, 0b0011)' },
            { name: 'not(x)', desc: 'ビットNOT', example: 'not(0b0011)' },
            { name: 'exor(a, b)', desc: 'ビットXOR', example: 'exor(0b1100, 0b1010)' },
            { name: 'exnor(a, b)', desc: 'ビットXNOR', example: 'exnor(0b1100, 0b1010)' },
            { name: 'nor(a, b, ...)', desc: 'ビットNOR', example: 'nor(0b1100, 0b0011)' },
            { name: 'nand(a, b, ...)', desc: 'ビットNAND', example: 'nand(0b1100, 0b1010)' },
        ],
    },
    {
        key: 'const',
        label: '定数',
        items: [
            { name: 'pi, e', desc: '数学定数', example: '2 * pi' },
            { name: 'c, h, k, q', desc: '物理定数', example: 'q * 5' },
            { name: 'eps0, mu0', desc: '真空定数', example: '1 / sqrt(mu0 * eps0)' },
            { name: 'j', desc: '虚数単位', example: '3 + 4j' },
            { name: 'T/G/M/k/m/u/n/p/f', desc: '10進接頭辞', example: '3.3T / 10k' },
            { name: 'Ti/Gi/Mi/Ki', desc: '1024系接頭辞', example: '64Mi / 8Ki' },
        ],
    },
];

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
        [0, ''], [3, 'k'], [6, 'M'], [9, 'G'], [12, 'T'],
    ];
    const exp = Math.floor(Math.log10(Math.abs(n)) / 3) * 3;
    const normalized = prefixes.find(([p]) => p === Math.max(-12, Math.min(12, exp))) ?? [0, ''];
    const value = n / Math.pow(10, normalized[0]);
    return `${Number(value.toPrecision(6)).toString()}${normalized[1]}`;
}

function normalizeComplexString(value) {
    const re = Number(value.re ?? 0);
    const im = Number(value.im ?? 0);
    const reText = formatNum(re);
    const imAbsText = formatNum(Math.abs(im));

    if (im === 0) return reText;
    if (re === 0) return `${im < 0 ? '-' : ''}${imAbsText}j`;
    return `${reText} ${im < 0 ? '-' : '+'} ${imAbsText}j`;
}

function complexPolar(value, unit = 'deg') {
    const re = Number(value.re ?? 0);
    const im = Number(value.im ?? 0);
    const mag = Math.sqrt((re ** 2) + (im ** 2));
    const rad = Math.atan2(im, re);
    const angle = unit === 'deg' ? (rad * 180 / Math.PI) : rad;
    return `${formatNum(mag)} ∠ ${formatNum(angle)}${unit === 'deg' ? 'deg' : 'rad'}`;
}

function formatValue(value) {
    if (math.isComplex(value)) return normalizeComplexString(value);
    return formatNum(value);
}

function formatBigInt(value) {
    const sign = value < 0 ? '-' : '';
    const abs = (value < 0 ? -value : value).toString();
    return `${sign}${abs.replace(/\B(?=(\d{3})+(?!\d))/g, ',')}`;
}

function splitTopLevel(value, delimiter = ',') {
    const result = [];
    let current = '';
    let depth = 0;
    let quote = null;

    for (let i = 0; i < value.length; i += 1) {
        const ch = value[i];
        const prev = value[i - 1];

        if (quote) {
            current += ch;
            if (ch === quote && prev !== '\\') quote = null;
            continue;
        }

        if (ch === '"' || ch === '\'' || ch === '`') {
            quote = ch;
            current += ch;
            continue;
        }

        if ('([{'.includes(ch)) {
            depth += 1;
            current += ch;
            continue;
        }

        if (')]}'.includes(ch)) {
            depth -= 1;
            current += ch;
            continue;
        }

        if (ch === delimiter && depth === 0) {
            result.push(current.trim());
            current = '';
            continue;
        }

        current += ch;
    }

    if (current.trim()) result.push(current.trim());
    return result;
}

function findTopLevelEquals(value) {
    let depth = 0;
    let quote = null;

    for (let i = 0; i < value.length; i += 1) {
        const ch = value[i];
        const prev = value[i - 1];
        const next = value[i + 1];

        if (quote) {
            if (ch === quote && prev !== '\\') quote = null;
            continue;
        }

        if (ch === '"' || ch === '\'' || ch === '`') {
            quote = ch;
            continue;
        }

        if ('([{'.includes(ch)) {
            depth += 1;
            continue;
        }

        if (')]}'.includes(ch)) {
            depth -= 1;
            continue;
        }

        if (depth === 0 && ch === '=' && next !== '>' && prev !== '<' && prev !== '>' && prev !== '!' && next !== '=') {
            return i;
        }
    }

    return -1;
}

function buildBitHelpers() {
    return {
        and: (...values) => {
            if (!values.length) return 0;
            return values.slice(1).reduce((acc, value) => acc & Number(value), Number(values[0]));
        },
        or: (...values) => {
            if (!values.length) return 0;
            return values.slice(1).reduce((acc, value) => acc | Number(value), Number(values[0]));
        },
        not: (value) => ~Number(value),
        exor: (...values) => {
            if (!values.length) return 0;
            return values.slice(1).reduce((acc, value) => acc ^ Number(value), Number(values[0]));
        },
        exnor: (...values) => ~values.slice(1).reduce((acc, value) => acc ^ Number(value), Number(values[0] ?? 0)),
        nor: (...values) => ~values.slice(1).reduce((acc, value) => acc | Number(value), Number(values[0] ?? 0)),
        nand: (...values) => ~values.slice(1).reduce((acc, value) => acc & Number(value), Number(values[0] ?? 0)),
    };
}

function transformPrefixes(expr) {
    return expr.replace(/(\d+(?:\.\d+)?(?:e[+\-]?\d+)?)(Ti|Gi|Mi|Ki|T|G|M|k|m|u|n|p|f)\b/g, (_, num, suffix) =>
        `(${num}${PREFIX_MAP[suffix]})`
    );
}

function buildHelpers(scope = {}) {
    const bitHelpers = buildBitHelpers();
    return {
        ...CONSTANTS,
        ...SAFE_FUNCTIONS,
        ...bitHelpers,
        eng: (v) => formatEngineering(Number(v)),
        esRound: (series, value) => nearestESeries(series, Number(value)),
        solve: (fnOrExpr, guess) => solveEquation(fnOrExpr, guess, 32, 1e-9, scope),
    };
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
        .replace(/\^/g, '**');
}

function transformComplexExpression(expr) {
    return expr
        .replace(/\r/g, '')
        .replace(/\^/g, '^')
        .replace(/(^|[^\w.])j\b/g, '$1i')
        .replace(/(\d+(?:\.\d+)?(?:e[+\-]?\d+)?)j\b/g, '$1i');
}

function solveEquation(fnOrExpr, guess = 1, maxIter = 32, tol = 1e-9, scope = {}) {
    const fn = typeof fnOrExpr === 'function'
        ? fnOrExpr
        : (x) => safeEval(String(fnOrExpr).replace(/\bx\b/g, `(${x})`), scope);

    const guesses = Array.isArray(guess) ? guess : [guess, 1, 10, 0.1, -1, 100, -10];
    let best = null;

    for (const seed of guesses) {
        let x = Number(seed);
        if (!Number.isFinite(x)) continue;

        for (let i = 0; i < maxIter; i += 1) {
            const y = Number(fn(x));
            const dx = Math.max(Math.abs(x) * 1e-6, 1e-9);
            const dy = (Number(fn(x + dx)) - y) / dx;
            if (!Number.isFinite(y) || !Number.isFinite(dy) || Math.abs(dy) < 1e-15) break;
            const next = x - y / dy;
            if (!best || Math.abs(y) < best.error) best = { value: x, error: Math.abs(y) };
            if (Math.abs(next - x) < tol) return next;
            x = next;
        }
    }

    return best?.value ?? Number(Array.isArray(guess) ? guess[0] : guess);
}

function solveEquationExpression(equation, variableName, scope = {}) {
    const eqIndex = findTopLevelEquals(equation);
    if (eqIndex === -1) throw new Error('solve(eq, var) の第1引数は = を含む式にしてください');

    const left = equation.slice(0, eqIndex).trim();
    const right = equation.slice(eqIndex + 1).trim();
    const fn = (candidate) => {
        const localScope = { ...scope, [variableName]: candidate };
        return Number(safeEval(left, localScope)) - Number(safeEval(right, localScope));
    };

    return solveEquation(fn, [1, 10, 0.1, 20, -1], 48, 1e-9, scope);
}

function trySolveEquationCall(expr, scope = {}) {
    const trimmed = expr.trim();
    if (!trimmed.startsWith('solve(') || !trimmed.endsWith(')')) return null;

    const args = splitTopLevel(trimmed.slice(6, -1));
    if (args.length !== 2 || !args[0].includes('=')) return null;

    const variableName = args[1].trim();
    if (!/^[a-zA-Z_]\w*$/u.test(variableName)) return null;

    return solveEquationExpression(args[0], variableName, scope);
}

function normalizeEvalError(error, lineNumber, lineText) {
    return {
        message: error.message ?? String(error),
        lineNumber,
        column: error.char ?? null,
        lineText,
    };
}

function safeEval(expr, scope = {}) {
    const solved = trySolveEquationCall(expr, scope);
    if (solved !== null) return solved;

    const sanitized = transformPrefixes(transformExpression(expr))
        .replace(/[;{}[\]`]/g, '')
        .replace(/\bimport\b|\brequire\b|\beval\b|\bFunction\b/g, '');
    const helpers = buildHelpers(scope);
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
    for (let index = 0; index < lines.length; index += 1) {
        const line = lines[index];
        try {
            const m = line.match(/^([a-zA-Z_]\w*)\s*=\s*(.+)$/u);
            if (m) {
                scope[m[1]] = safeEval(m[2], scope);
                lastValue = scope[m[1]];
                lastExpr = line;
            } else {
                lastValue = safeEval(line, scope);
                lastExpr = line;
            }
        } catch (error) {
            throw normalizeEvalError(error, index + 1, line);
        }
    }
    return { value: lastValue, expr: lastExpr, scope };
}

function shouldUseComplexEngine(program) {
    return /(^|[^a-zA-Z_])j\b|=\s*.*j\b|\d+j\b/u.test(program);
}

function createComplexScope() {
    const bitHelpers = buildBitHelpers();
    return {
        pi: Math.PI,
        e: Math.E,
        c: 299792458,
        h: 6.62607015e-34,
        k: 1.380649e-23,
        q: 1.602176634e-19,
        eps0: 8.854187817e-12,
        mu0: 1.2566370614e-6,
        ...bitHelpers,
        esRound: (series, value) => nearestESeries(series, Number(value)),
        eng: (value) => formatEngineering(Number(value)),
        solve: (fnOrExpr, guess) => solveEquation(fnOrExpr, guess),
    };
}

function evaluateComplexProgram(program) {
    const parser = math.parser();
    const scope = createComplexScope();
    Object.entries(scope).forEach(([key, value]) => parser.set(key, value));

    const lines = program.split('\n').map((line) => line.trim()).filter(Boolean);
    let lastValue = null;
    let lastExpr = '';

    for (let index = 0; index < lines.length; index += 1) {
        const line = lines[index];
        try {
            const solved = trySolveEquationCall(line, parser.getAll());
            lastExpr = line;
            lastValue = solved ?? parser.evaluate(transformPrefixes(transformComplexExpression(line)));
        } catch (error) {
            throw normalizeEvalError(error, index + 1, line);
        }
    }

    const parserScope = parser.getAll();
    const userScope = Object.fromEntries(
        Object.entries(parserScope).filter(([key, value]) =>
            !Object.prototype.hasOwnProperty.call(scope, key) &&
            typeof value !== 'function'
        )
    );

    return { value: lastValue, expr: lastExpr, scope: userScope };
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
    const copied = ref(false);
    const angleUnit = ref('deg');
    const errorLine = ref(null);
    const errorColumn = ref(null);
    const bitWidth = ref(16);
    const signedMode = ref('unsigned');
    const activeFunctionGroup = ref(FUNCTION_GROUPS[0].key);
    const functionCatalogOpen = ref(false);

    const snippets = [
        { label: '進数混在', value: '0xff + 25k' },
        { label: 'E系列丸め', value: 'esRound("E24", 4.83k)' },
        { label: '数値解法', value: 'solve(10=1/((1/x)+(1/20)), x)' },
        { label: '複素数', value: 'z = 3 + 4j\nz * (1 - 2j)' },
        { label: '1024接頭辞', value: '64Mi / 8Ki' },
        { label: '論理演算', value: 'nand(0b1100, 0b1010)' },
        { label: 'ビット操作', value: '(0b101101 << 2) | 0x03' },
        { label: '複数行', value: 'vin = 5\nr1 = 10k\nr2 = 3.3k\nvin * r2 / (r1 + r2)' },
    ];

    const presetItems = [
        { name: 'esRound(series, value)', desc: 'E系列最近傍値へ丸め' },
        { name: 'solve(eq, var)', desc: '方程式を解く' },
        { name: '3 + 4j', desc: '複素数は j 表記で入力' },
        { name: '64Mi / 8Ki', desc: '1024系接頭辞' },
        { name: 'nand(0b1100, 0b1010)', desc: '論理演算関数' },
        { name: 'eng(value)', desc: '工学表記へ整形' },
        { name: 'pi, e, c, k, q', desc: '定数プリセット' },
    ];

    const isComplexResult = computed(() => math.isComplex(result.value));
    const intResult = computed(() => Number.isInteger(result.value) ? Number(result.value) : null);
    const editorLines = computed(() => expr.value.split('\n'));
    const activeFunctionItems = computed(() =>
        FUNCTION_GROUPS.find((group) => group.key === activeFunctionGroup.value)?.items ?? []
    );
    const decResult = computed(() => {
        if (result.value === null) return '-';
        if (isComplexResult.value) return normalizeComplexString(result.value);
        return formatNum(result.value);
    });
    const engResult = computed(() => typeof result.value === 'number' ? formatEngineering(result.value) : '-');
    const bitBaseValue = computed(() => {
        if (intResult.value === null) return null;
        try {
            const raw = BigInt(intResult.value);
            return BigInt.asUintN(bitWidth.value, raw);
        } catch {
            return null;
        }
    });
    const displayIntValue = computed(() => {
        if (bitBaseValue.value === null) return null;
        return signedMode.value === 'signed'
            ? BigInt.asIntN(bitWidth.value, bitBaseValue.value)
            : bitBaseValue.value;
    });
    const decDisplayResult = computed(() => {
        if (displayIntValue.value === null) return decResult.value;
        return formatBigInt(displayIntValue.value);
    });
    const hexResult = computed(() => {
        if (bitBaseValue.value === null) return '-';
        const digits = Math.max(1, Math.ceil(bitWidth.value / 4));
        return `0x${bitBaseValue.value.toString(16).toUpperCase().padStart(digits, '0')}`;
    });
    const binResult = computed(() => {
        if (bitBaseValue.value === null) return '-';
        return `0b${bitBaseValue.value.toString(2).padStart(bitWidth.value, '0')}`;
    });
    const octResult = computed(() => {
        if (bitBaseValue.value === null) return '-';
        const digits = Math.max(1, Math.ceil(bitWidth.value / 3));
        return `0${bitBaseValue.value.toString(8).padStart(digits, '0')}`;
    });
    const scopeEntries = computed(() => Object.entries(lastScope.value));
    const complexCartesian = computed(() => isComplexResult.value ? normalizeComplexString(result.value) : '-');
    const complexPolarValue = computed(() => isComplexResult.value ? complexPolar(result.value, angleUnit.value) : '-');

    const run = (pushHistory = false) => {
        if (!expr.value.trim()) {
            result.value = null;
            error.value = '';
            errorLine.value = null;
            errorColumn.value = null;
            return;
        }
        try {
            const evaluated = shouldUseComplexEngine(expr.value)
                ? evaluateComplexProgram(expr.value)
                : evaluateProgram(expr.value);
            result.value = evaluated.value;
            resultType.value = math.isComplex(evaluated.value) ? 'complex' : typeof evaluated.value;
            lastScope.value = evaluated.scope;
            error.value = '';
            errorLine.value = null;
            errorColumn.value = null;
            if (pushHistory) {
                history.value.unshift({
                    id: Date.now(),
                    expr: expr.value,
                    result: formatValue(evaluated.value),
                    meta: `${resultType.value} / ${engResult.value}`,
                });
                history.value = history.value.slice(0, 40);
                storageSet(HISTORY_KEY, history.value);
            }
        } catch (e) {
            error.value = `計算エラー: ${e.message}`;
            errorLine.value = e.lineNumber ?? null;
            errorColumn.value = e.column ?? null;
            result.value = null;
            lastScope.value = {};
        }
    };

    watch(expr, () => run(false));

    const clearCalc = () => {
        expr.value = '';
        result.value = null;
        error.value = '';
        errorLine.value = null;
        errorColumn.value = null;
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
        if (favorites.value.some((item) => item.expr === expr.value)) return;
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
        await navigator.clipboard.writeText(formatValue(result.value));
        copied.value = true;
        window.setTimeout(() => { copied.value = false; }, 1200);
    };

    const saveCurrentToHistory = () => {
        if (!expr.value.trim() || result.value === null || error.value) return;
        history.value.unshift({
            id: Date.now(),
            expr: expr.value,
            result: isComplexResult.value ? complexCartesian.value : formatNum(result.value),
            meta: `${resultType.value} / ${engResult.value}`,
        });
        history.value = history.value.slice(0, 40);
        storageSet(HISTORY_KEY, history.value);
    };

    run(false);

    return {
        expr, error, result, resultType, history, favorites,
        snippets, presetItems, scopeEntries, editorLines, errorLine, errorColumn,
        decResult, decDisplayResult, engResult, hexResult, binResult, octResult,
        copied, angleUnit, isComplexResult, complexCartesian, complexPolarValue,
        bitWidth, signedMode, bitWidthOptions: BIT_WIDTH_OPTIONS,
        functionGroups: FUNCTION_GROUPS, activeFunctionGroup, activeFunctionItems, functionCatalogOpen,
        run, clearCalc, useHistory, clearHistory, saveFavorite, useFavorite, copyResult, saveCurrentToHistory, formatNum, formatValue,
    };
}
