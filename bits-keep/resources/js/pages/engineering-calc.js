import { nextTick, ref, computed, watch } from 'vue';
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
const MAX_SAFE_BIGINT = BigInt(Number.MAX_SAFE_INTEGER);
const MIN_SAFE_BIGINT = BigInt(Number.MIN_SAFE_INTEGER);
const UNSAFE_INTEGER_DISPLAY = 'Number安全整数範囲外のため整数表示を抑制';

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

function flattenNumbers(values) {
    return values.flat(Infinity).map(Number).filter((value) => Number.isFinite(value));
}

function clampByte(value) {
    return Math.max(0, Math.min(255, Math.round(Number(value) || 0)));
}

function rgb(r, g, b) {
    const bytes = [r, g, b].map(clampByte);
    return `#${bytes.map((value) => value.toString(16).padStart(2, '0')).join('').toUpperCase()}`;
}

function dateDiff(start, end, unit = 'day') {
    const startMs = new Date(start).getTime();
    const endMs = new Date(end).getTime();
    if (!Number.isFinite(startMs) || !Number.isFinite(endMs)) return Number.NaN;
    const diffMs = endMs - startMs;
    const divisors = {
        ms: 1,
        sec: 1000,
        min: 60000,
        hour: 3600000,
        day: 86400000,
    };
    return diffMs / (divisors[unit] ?? divisors.day);
}

const SAFE_FUNCTIONS = {
    sin: Math.sin, cos: Math.cos, tan: Math.tan,
    asin: Math.asin, acos: Math.acos, atan: Math.atan, atan2: Math.atan2,
    sinh: Math.sinh, cosh: Math.cosh, tanh: Math.tanh,
    sqrt: Math.sqrt, cbrt: Math.cbrt,
    log: Math.log, ln: Math.log, log2: Math.log2, log10: Math.log10,
    exp: Math.exp, pow: Math.pow,
    abs: Math.abs, ceil: Math.ceil, floor: Math.floor, round: Math.round,
    min: Math.min, max: Math.max,
    sum: (...values) => flattenNumbers(values).reduce((acc, value) => acc + value, 0),
    avg: (...values) => {
        const numbers = flattenNumbers(values);
        return numbers.length ? numbers.reduce((acc, value) => acc + value, 0) / numbers.length : Number.NaN;
    },
    clamp: (value, min, max) => Math.max(Number(min), Math.min(Number(max), Number(value))),
    rgb,
    dateDiff,
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
        key: 'type',
        label: '型補助',
        items: [
            { name: 'sum(values)', desc: '配列/引数の合計', example: 'sum([1, 2, 3], 4)' },
            { name: 'avg(values)', desc: '配列/引数の平均', example: 'avg([1, 2, 3, 4])' },
            { name: 'clamp(x, min, max)', desc: '範囲制限', example: 'clamp(5.6, 0, 3.3)' },
            { name: 'rgb(r, g, b)', desc: 'RGBを16進色へ変換', example: 'rgb(64, 128, 255)' },
            { name: 'dateDiff(a, b, unit)', desc: '日時差分', example: 'dateDiff("2026-04-30", "2026-05-02", "day")' },
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

function completionInsertText(name) {
    const primary = name.split(',')[0].trim();
    return primary.includes('(') ? primary.replace(/\(.+\)$/u, '()') : primary;
}

const SUGGESTION_ITEMS = FUNCTION_GROUPS.flatMap((group) =>
    group.items.map((item) => ({
        label: item.name,
        insert: completionInsertText(item.name),
        group: group.label,
    }))
);

const E_SERIES = {
    E6: [1.0, 1.5, 2.2, 3.3, 4.7, 6.8],
    E12: [1.0, 1.2, 1.5, 1.8, 2.2, 2.7, 3.3, 3.9, 4.7, 5.6, 6.8, 8.2],
    E24: [1.0, 1.1, 1.2, 1.3, 1.5, 1.6, 1.8, 2.0, 2.2, 2.4, 2.7, 3.0, 3.3, 3.6, 3.9, 4.3, 4.7, 5.1, 5.6, 6.2, 6.8, 7.5, 8.2, 9.1],
    E96: [1.00,1.02,1.05,1.07,1.10,1.13,1.15,1.18,1.21,1.24,1.27,1.30,1.33,1.37,1.40,1.43,1.47,1.50,1.54,1.58,1.62,1.65,1.69,1.74,1.78,1.82,1.87,1.91,1.96,2.00,2.05,2.10,2.15,2.21,2.26,2.32,2.37,2.43,2.49,2.55,2.61,2.67,2.74,2.80,2.87,2.94,3.01,3.09,3.16,3.24,3.32,3.40,3.48,3.57,3.65,3.74,3.83,3.92,4.02,4.12,4.22,4.32,4.42,4.53,4.64,4.75,4.87,4.99,5.11,5.23,5.36,5.49,5.62,5.76,5.90,6.04,6.19,6.34,6.49,6.65,6.81,6.98,7.15,7.32,7.50,7.68,7.87,8.06,8.25,8.45,8.66,8.87,9.09,9.31,9.53,9.76],
};

function storageGet(key, fallback) {
    try {
        const storage = key === HISTORY_KEY ? window.sessionStorage : window.localStorage;
        return JSON.parse(storage.getItem(key) ?? 'null') ?? fallback;
    } catch {
        return fallback;
    }
}

function storageSet(key, value) {
    const storage = key === HISTORY_KEY ? window.sessionStorage : window.localStorage;
    storage.setItem(key, JSON.stringify(value));
}

function formatNum(n) {
    if (n === null || n === undefined || Number.isNaN(n)) return '-';
    if (typeof n === 'bigint') return formatBigInt(n);
    if (!Number.isFinite(n)) return String(n);
    if (Number.isInteger(n) && Math.abs(n) < 1e15) return n.toLocaleString();

    const text = Number(n).toPrecision(10).replace(/e\+?/u, 'e');
    if (!text.includes('e')) return text.replace(/\.?0+$/u, '');

    const [mantissa, exponent] = text.split('e');
    return `${mantissa.replace(/\.?0+$/u, '')}e${exponent}`;
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
    if (typeof value === 'bigint') return formatBigInt(value);
    if (typeof value === 'function') return 'function';
    if (Array.isArray(value)) return `[${value.map(formatValue).join(', ')}]`;
    if (typeof value === 'string') return value;
    if (isUnsafeIntegerNumber(value)) return UNSAFE_INTEGER_DISPLAY;
    return formatNum(value);
}

function formatBigInt(value) {
    const sign = value < 0 ? '-' : '';
    const abs = (value < 0 ? -value : value).toString();
    return `${sign}${abs.replace(/\B(?=(\d{3})+(?!\d))/g, ',')}`;
}

function isUnsafeIntegerNumber(value) {
    return typeof value === 'number' && Number.isInteger(value) && !Number.isSafeInteger(value);
}

function exactDisplayInt(value) {
    if (typeof value === 'bigint') return value;
    if (Number.isSafeInteger(value)) return BigInt(value);
    return null;
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

class ExactIntegerParseError extends Error {}

function tokenizeExactIntegerExpression(expr) {
    const tokens = [];
    let index = 0;

    const fail = () => {
        throw new ExactIntegerParseError('not an exact integer expression');
    };

    while (index < expr.length) {
        const ch = expr[index];

        if (/\s/u.test(ch)) {
            index += 1;
            continue;
        }

        if (/\d/u.test(ch)) {
            const start = index;

            if (ch === '0' && /[xbo]/iu.test(expr[index + 1] ?? '')) {
                const base = expr[index + 1].toLowerCase();
                index += 2;
                const digitPattern = {
                    x: /[0-9a-f]/iu,
                    b: /[01]/u,
                    o: /[0-7]/u,
                }[base];
                const digitStart = index;
                while (index < expr.length && digitPattern.test(expr[index])) index += 1;
                if (index === digitStart) fail();
            } else {
                while (index < expr.length && /\d/u.test(expr[index])) index += 1;
            }

            if (/[A-Za-z_.]/u.test(expr[index] ?? '')) fail();
            tokens.push({ type: 'number', text: expr.slice(start, index) });
            continue;
        }

        if (/[A-Za-z_]/u.test(ch)) {
            const start = index;
            index += 1;
            while (index < expr.length && /\w/u.test(expr[index])) index += 1;
            tokens.push({ type: 'identifier', text: expr.slice(start, index) });
            continue;
        }

        const pair = expr.slice(index, index + 2);
        if (['**', '<<', '>>'].includes(pair)) {
            tokens.push({ type: 'operator', text: pair });
            index += 2;
            continue;
        }

        if (['&&', '||'].includes(pair)) fail();

        if ('+-*%^&|~(),'.includes(ch)) {
            tokens.push({ type: 'operator', text: ch });
            index += 1;
            continue;
        }

        fail();
    }

    return tokens;
}

function parseIntegerLiteral(text) {
    return BigInt(text);
}

function toExactInteger(value) {
    if (typeof value === 'bigint') return value;
    if (typeof value === 'boolean') return value ? 1n : 0n;
    if (typeof value === 'number') {
        if (!Number.isFinite(value) || !Number.isInteger(value)) {
            throw new ExactIntegerParseError('not an integer value');
        }
        if (!Number.isSafeInteger(value)) {
            throw new Error('Number安全整数範囲外の整数は正確な整数演算に使えません');
        }
        return BigInt(value);
    }
    throw new ExactIntegerParseError('not an integer value');
}

function ensureExactArgs(name, args, min, max = min) {
    if (args.length < min || args.length > max) {
        throw new ExactIntegerParseError(`${name} argument count mismatch`);
    }
}

function reduceExactBits(args, fallback, reducer) {
    if (!args.length) return fallback;
    const [first, ...rest] = args;
    return rest.reduce(reducer, first);
}

function applyExactIntegerFunction(name, args) {
    switch (name) {
        case 'and':
        case 'bitAnd':
            return reduceExactBits(args, 0n, (acc, value) => acc & value);
        case 'or':
        case 'bitOr':
            return reduceExactBits(args, 0n, (acc, value) => acc | value);
        case 'exor':
            return reduceExactBits(args, 0n, (acc, value) => acc ^ value);
        case 'not':
            ensureExactArgs(name, args, 1);
            return ~args[0];
        case 'exnor':
            return ~reduceExactBits(args, 0n, (acc, value) => acc ^ value);
        case 'nor':
            return ~reduceExactBits(args, 0n, (acc, value) => acc | value);
        case 'nand':
            return ~reduceExactBits(args, 0n, (acc, value) => acc & value);
        case 'bitShiftLeft':
            ensureExactArgs(name, args, 2);
            return args[0] << args[1];
        case 'bitShiftRight':
            ensureExactArgs(name, args, 2);
            return args[0] >> args[1];
        case 'pow':
            ensureExactArgs(name, args, 2);
            if (args[1] < 0n) throw new ExactIntegerParseError('negative exponent');
            return args[0] ** args[1];
        case 'abs':
            ensureExactArgs(name, args, 1);
            return args[0] < 0n ? -args[0] : args[0];
        case 'min':
            if (!args.length) throw new ExactIntegerParseError('min needs arguments');
            return args.reduce((acc, value) => value < acc ? value : acc);
        case 'max':
            if (!args.length) throw new ExactIntegerParseError('max needs arguments');
            return args.reduce((acc, value) => value > acc ? value : acc);
        case 'sum':
            return args.reduce((acc, value) => acc + value, 0n);
        default:
            throw new ExactIntegerParseError('unsupported exact integer function');
    }
}

class ExactIntegerParser {
    constructor(tokens, scope = {}) {
        this.tokens = tokens;
        this.scope = scope;
        this.index = 0;
    }

    peek() {
        return this.tokens[this.index] ?? null;
    }

    match(text) {
        if (this.peek()?.text !== text) return false;
        this.index += 1;
        return true;
    }

    expect(text) {
        if (!this.match(text)) throw new ExactIntegerParseError(`expected ${text}`);
    }

    parse() {
        const value = this.parseBitOr();
        if (this.peek()) throw new ExactIntegerParseError('unexpected token');
        return value;
    }

    parseBitOr() {
        let value = this.parseBitAnd();
        while (this.match('|')) {
            value |= this.parseBitAnd();
        }
        return value;
    }

    parseBitAnd() {
        let value = this.parseShift();
        while (this.match('&')) {
            value &= this.parseShift();
        }
        return value;
    }

    parseShift() {
        let value = this.parseAdditive();
        while (true) {
            if (this.match('<<')) {
                value <<= this.parseAdditive();
            } else if (this.match('>>')) {
                value >>= this.parseAdditive();
            } else {
                return value;
            }
        }
    }

    parseAdditive() {
        let value = this.parseMultiplicative();
        while (true) {
            if (this.match('+')) {
                value += this.parseMultiplicative();
            } else if (this.match('-')) {
                value -= this.parseMultiplicative();
            } else {
                return value;
            }
        }
    }

    parseMultiplicative() {
        let value = this.parseUnary();
        while (true) {
            if (this.match('*')) {
                value *= this.parseUnary();
            } else if (this.match('%')) {
                const divisor = this.parseUnary();
                if (divisor === 0n) throw new ExactIntegerParseError('modulo by zero');
                value %= divisor;
            } else {
                return value;
            }
        }
    }

    parseUnary() {
        if (this.match('+')) return this.parseUnary();
        if (this.match('-')) return -this.parseUnary();
        if (this.match('~')) return ~this.parseUnary();
        return this.parsePower();
    }

    parsePower() {
        let value = this.parsePrimary();
        if (this.match('**') || this.match('^')) {
            const exponent = this.parseUnary();
            if (exponent < 0n) throw new ExactIntegerParseError('negative exponent');
            value **= exponent;
        }
        return value;
    }

    parsePrimary() {
        const token = this.peek();
        if (!token) throw new ExactIntegerParseError('unexpected end');

        if (token.type === 'number') {
            this.index += 1;
            return parseIntegerLiteral(token.text);
        }

        if (token.type === 'identifier') {
            this.index += 1;
            if (this.match('(')) {
                const args = [];
                if (!this.match(')')) {
                    do {
                        args.push(this.parseBitOr());
                    } while (this.match(','));
                    this.expect(')');
                }
                return applyExactIntegerFunction(token.text, args);
            }
            if (token.text === 'true') return 1n;
            if (token.text === 'false') return 0n;
            if (Object.prototype.hasOwnProperty.call(this.scope, token.text)) {
                return toExactInteger(this.scope[token.text]);
            }
            throw new ExactIntegerParseError('unknown symbol');
        }

        if (this.match('(')) {
            const value = this.parseBitOr();
            this.expect(')');
            return value;
        }

        throw new ExactIntegerParseError('unexpected token');
    }
}

function finalizeIntegerResult(value) {
    if (value <= MAX_SAFE_BIGINT && value >= MIN_SAFE_BIGINT) {
        return Number(value);
    }
    return value;
}

function tryEvaluateExactIntegerExpression(expr, scope = {}) {
    try {
        const tokens = tokenizeExactIntegerExpression(expr);
        if (!tokens.length) return null;
        return finalizeIntegerResult(new ExactIntegerParser(tokens, scope).parse());
    } catch (error) {
        if (error instanceof ExactIntegerParseError) return null;
        throw error;
    }
}

function toBitBigInt(value) {
    if (typeof value === 'bigint') return value;
    if (typeof value === 'boolean') return value ? 1n : 0n;

    const number = Number(value);
    if (!Number.isFinite(number) || !Number.isInteger(number)) {
        throw new Error('ビット演算は整数だけを指定してください');
    }

    if (!Number.isSafeInteger(number)) {
        throw new Error('Number安全整数範囲外の整数は正確なビット演算に使えません');
    }

    return BigInt(number);
}

function finalizeBitResult(value) {
    return finalizeIntegerResult(value);
}

function buildBitHelpers() {
    const reduceBits = (values, fallback, reducer) => {
        if (!values.length) return 0;
        const [first = fallback, ...rest] = values.map(toBitBigInt);
        return finalizeBitResult(rest.reduce(reducer, first));
    };

    return {
        and: (...values) => reduceBits(values, 0n, (acc, value) => acc & value),
        or: (...values) => reduceBits(values, 0n, (acc, value) => acc | value),
        not: (value) => finalizeBitResult(~toBitBigInt(value)),
        exor: (...values) => reduceBits(values, 0n, (acc, value) => acc ^ value),
        exnor: (...values) => finalizeBitResult(~toBitBigInt(reduceBits(values, 0n, (acc, value) => acc ^ value))),
        nor: (...values) => finalizeBitResult(~toBitBigInt(reduceBits(values, 0n, (acc, value) => acc | value))),
        nand: (...values) => finalizeBitResult(~toBitBigInt(reduceBits(values, 0n, (acc, value) => acc & value))),
        bitAnd: (...values) => reduceBits(values, 0n, (acc, value) => acc & value),
        bitOr: (...values) => reduceBits(values, 0n, (acc, value) => acc | value),
        bitShiftLeft: (value, bits) => finalizeBitResult(toBitBigInt(value) << toBitBigInt(bits)),
        bitShiftRight: (value, bits) => finalizeBitResult(toBitBigInt(value) >> toBitBigInt(bits)),
    };
}

function findMatchingGroup(expr, startIndex, open, close) {
    let depth = 0;
    let quote = null;

    for (let i = startIndex; i < expr.length; i += 1) {
        const ch = expr[i];
        const prev = expr[i - 1];

        if (quote) {
            if (ch === quote && prev !== '\\') quote = null;
            continue;
        }

        if (ch === '"' || ch === '\'' || ch === '`') {
            quote = ch;
            continue;
        }

        if (ch === open) depth += 1;
        if (ch === close) depth -= 1;
        if (depth === 0) return i;
    }

    return -1;
}

function transformNestedBitwiseGroups(expr) {
    let result = '';

    for (let i = 0; i < expr.length; i += 1) {
        const ch = expr[i];

        if (ch === '"' || ch === '\'' || ch === '`') {
            const quote = ch;
            let end = i + 1;
            while (end < expr.length) {
                if (expr[end] === quote && expr[end - 1] !== '\\') break;
                end += 1;
            }
            result += expr.slice(i, end + 1);
            i = end;
            continue;
        }

        const close = ch === '(' ? ')' : ch === '[' ? ']' : null;
        if (close) {
            const end = findMatchingGroup(expr, i, ch, close);
            if (end === -1) {
                result += ch;
                continue;
            }

            const content = expr.slice(i + 1, end);
            result += `${ch}${transformBitwiseOperators(content)}${close}`;
            i = end;
            continue;
        }

        result += ch;
    }

    return result;
}

function topLevelOperator(expr, operators) {
    let depth = 0;
    let quote = null;
    let found = null;

    for (let i = 0; i < expr.length; i += 1) {
        const ch = expr[i];
        const prev = expr[i - 1];

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

        if (depth !== 0) continue;

        for (const operator of operators) {
            if (!expr.startsWith(operator, i)) continue;
            if (operator === '|' && (expr[i - 1] === '|' || expr[i + 1] === '|')) continue;
            if (operator === '&' && (expr[i - 1] === '&' || expr[i + 1] === '&')) continue;
            found = { index: i, operator };
            i += operator.length - 1;
            break;
        }
    }

    return found;
}

function transformTopLevelBitwise(expr) {
    const trimmed = expr.trim();
    const levels = [
        { operators: ['|'], helpers: { '|': 'bitOr' } },
        { operators: ['&'], helpers: { '&': 'bitAnd' } },
        { operators: ['<<', '>>'], helpers: { '<<': 'bitShiftLeft', '>>': 'bitShiftRight' } },
    ];

    for (const level of levels) {
        const found = topLevelOperator(trimmed, level.operators);
        if (!found) continue;

        const left = trimmed.slice(0, found.index);
        const right = trimmed.slice(found.index + found.operator.length);
        const helper = level.helpers[found.operator];
        return `${helper}(${transformBitwiseOperators(left)}, ${transformBitwiseOperators(right)})`;
    }

    return trimmed;
}

function transformBitwiseOperators(expr) {
    return transformTopLevelBitwise(transformNestedBitwiseGroups(expr));
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
    const acceptableError = Math.max(tol * 10, 1e-7);

    const remember = (value) => {
        const error = Math.abs(Number(fn(value)));
        if (Number.isFinite(error) && (!best || error < best.error)) {
            best = { value, error };
        }
        return error;
    };

    for (const seed of guesses) {
        let x = Number(seed);
        if (!Number.isFinite(x)) continue;

        for (let i = 0; i < maxIter; i += 1) {
            const y = Number(fn(x));
            const dx = Math.max(Math.abs(x) * 1e-6, 1e-9);
            const dy = (Number(fn(x + dx)) - y) / dx;
            if (Number.isFinite(y) && (!best || Math.abs(y) < best.error)) {
                best = { value: x, error: Math.abs(y) };
            }
            if (!Number.isFinite(y) || !Number.isFinite(dy) || Math.abs(dy) < 1e-15) break;
            const next = x - y / dy;
            if (Math.abs(next - x) < tol) {
                const error = remember(next);
                if (error <= acceptableError) return next;
                break;
            }
            x = next;
        }
    }

    if (best && best.error <= acceptableError) return best.value;
    throw new Error('solve は実数解へ収束しませんでした');
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

    const exactInteger = tryEvaluateExactIntegerExpression(expr, scope);
    if (exactInteger !== null) return exactInteger;

    const sanitized = transformPrefixes(transformExpression(transformBitwiseOperators(expr)))
        .replace(/[;{}`]/g, '')
        .replace(/\bimport\b|\brequire\b|\beval\b|\bFunction\b/g, '');
    const helpers = buildHelpers(scope);
    const keys = [...Object.keys(helpers), ...Object.keys(scope)];
    const vals = [...Object.values(helpers), ...Object.values(scope)];
    // eslint-disable-next-line no-new-func
    const fn = new Function(...keys, `"use strict"; return (${sanitized});`);
    return fn(...vals);
}

function evaluateProgram(program) {
    const lines = program.split('\n')
        .map((line, index) => ({ text: line.trim(), number: index + 1 }))
        .filter((line) => line.text.length > 0);
    const scope = {};
    let lastValue = null;
    let lastExpr = '';
    for (let index = 0; index < lines.length; index += 1) {
        const sourceLine = lines[index];
        const line = sourceLine.text;
        try {
            const eqIndex = findTopLevelEquals(line);
            if (eqIndex !== -1) {
                const leftSide = line.slice(0, eqIndex).trim();
                const rightSide = line.slice(eqIndex + 1).trim();
                const fnMatch = leftSide.match(/^([a-zA-Z_]\w*)\s*\(([^()]*)\)$/u);
                if (fnMatch) {
                    const [, name, argsRaw] = fnMatch;
                    const argNames = argsRaw.split(',').map((arg) => arg.trim()).filter(Boolean);
                    if (argNames.some((arg) => !/^[a-zA-Z_]\w*$/u.test(arg))) {
                        throw new Error('関数引数名が不正です');
                    }
                    scope[name] = (...values) => {
                        const localScope = { ...scope };
                        argNames.forEach((arg, argIndex) => {
                            localScope[arg] = values[argIndex];
                        });
                        return safeEval(rightSide, localScope);
                    };
                    lastValue = scope[name];
                    lastExpr = line;
                    continue;
                }

                if (/^[a-zA-Z_]\w*$/u.test(leftSide)) {
                    scope[leftSide] = safeEval(rightSide, scope);
                    lastValue = scope[leftSide];
                    lastExpr = line;
                    continue;
                }
            }

            lastValue = safeEval(line, scope);
            lastExpr = line;
        } catch (error) {
            throw normalizeEvalError(error, sourceLine.number, line);
        }
    }
    return { value: lastValue, expr: lastExpr, scope };
}

function syntaxTokens(line) {
    const tokens = [];
    const pattern = /(0x[0-9a-f]+|0b[01]+|0o[0-7]+|\d+(?:\.\d+)?(?:e[+\-]?\d+)?(?:Ti|Gi|Mi|Ki|T|G|M|k|m|u|n|p|f)?|"(?:[^"\\]|\\.)*"|'(?:[^'\\]|\\.)*'|[A-Za-z_]\w*|==|!=|<=|>=|&&|\|\||<<|>>|[+\-*/^=(),?:<>!&|[\]])/giu;
    let lastIndex = 0;
    let match;

    while ((match = pattern.exec(line)) !== null) {
        if (match.index > lastIndex) {
            tokens.push({ text: line.slice(lastIndex, match.index), type: 'plain' });
        }

        const text = match[0];
        let type = 'op';
        if (/^0x|^0b|^0o|^\d/iu.test(text)) type = 'number';
        else if (/^["']/u.test(text)) type = 'string';
        else if (Object.prototype.hasOwnProperty.call(CONSTANTS, text)) type = 'constant';
        else if (Object.prototype.hasOwnProperty.call(SAFE_FUNCTIONS, text) || ['solve', 'esRound', 'eng'].includes(text)) type = 'function';
        else if (/^[A-Za-z_]/u.test(text)) type = 'symbol';
        tokens.push({ text, type });
        lastIndex = pattern.lastIndex;
    }

    if (lastIndex < line.length) {
        tokens.push({ text: line.slice(lastIndex), type: 'plain' });
    }

    return tokens.length ? tokens : [{ text: ' ', type: 'plain' }];
}

function tokenClass(type) {
    return {
        number: 'text-[var(--color-primary)]',
        string: 'text-[var(--color-accent)]',
        constant: 'text-[var(--color-highlight)]',
        function: 'text-[var(--color-link)] font-semibold',
        symbol: 'opacity-90',
        op: 'opacity-55',
        plain: '',
    }[type] ?? '';
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
        ...SAFE_FUNCTIONS,
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

    const lines = program.split('\n')
        .map((line, index) => ({ text: line.trim(), number: index + 1 }))
        .filter((line) => line.text.length > 0);
    let lastValue = null;
    let lastExpr = '';

    for (let index = 0; index < lines.length; index += 1) {
        const sourceLine = lines[index];
        const line = sourceLine.text;
        try {
            const solved = trySolveEquationCall(line, parser.getAll());
            lastExpr = line;
            lastValue = solved ?? parser.evaluate(transformPrefixes(transformComplexExpression(line)));
        } catch (error) {
            throw normalizeEvalError(error, sourceLine.number, line);
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
    const calcTextarea = ref(null);
    const copied = ref(false);
    const angleUnit = ref('deg');
    const errorLine = ref(null);
    const errorColumn = ref(null);
    const bitWidth = ref(16);
    const signedMode = ref('unsigned');
    const activeFunctionGroup = ref(FUNCTION_GROUPS[0].key);
    const functionCatalogOpen = ref(false);
    const completionPrefix = ref('');
    const completionStart = ref(0);
    const completionEnd = ref(0);

    const snippets = [
        { label: '進数混在', value: '0xff + 25k' },
        { label: 'E系列丸め', value: 'esRound("E24", 4.83k)' },
        { label: '数値解法', value: 'solve(10=1/((1/x)+(1/20)), x)' },
        { label: '複素数', value: 'z = 3 + 4j\nz * (1 - 2j)' },
        { label: '1024接頭辞', value: '64Mi / 8Ki' },
        { label: '論理演算', value: 'nand(0b1100, 0b1010)' },
        { label: '定義関数', value: 'parallel(a, b) = 1 / ((1 / a) + (1 / b))\nparallel(10k, 22k)' },
        { label: '配列集計', value: 'avg([10k, 11k, 9.8k])' },
        { label: '色', value: 'rgb(64, 128, 255)' },
        { label: 'ビット操作', value: '(0b101101 << 2) | 0x03' },
        { label: '複数行', value: 'vin = 5\nr1 = 10k\nr2 = 3.3k\nvin * r2 / (r1 + r2)' },
    ];

    const presetItems = [
        { name: 'esRound(series, value)', desc: 'E系列最近傍値へ丸め' },
        { name: 'solve(eq, var)', desc: '方程式を解く' },
        { name: '3 + 4j', desc: '複素数は j 表記で入力' },
        { name: '64Mi / 8Ki', desc: '1024系接頭辞' },
        { name: 'nand(0b1100, 0b1010)', desc: '論理演算関数' },
        { name: 'parallel(a, b) = ...', desc: 'ユーザー定義関数' },
        { name: 'sum([1, 2, 3])', desc: '配列集計' },
        { name: 'rgb(64, 128, 255)', desc: '色コード' },
        { name: 'eng(value)', desc: '工学表記へ整形' },
        { name: 'pi, e, c, k, q', desc: '定数プリセット' },
    ];

    const isComplexResult = computed(() => math.isComplex(result.value));
    const intResult = computed(() => {
        return exactDisplayInt(result.value);
    });
    const unsafeIntegerNumber = computed(() => isUnsafeIntegerNumber(result.value));
    const editorLines = computed(() => expr.value.split('\n'));
    const syntaxLines = computed(() =>
        editorLines.value.map((line, index) => ({
            number: index + 1,
            tokens: syntaxTokens(line),
        }))
    );
    const activeFunctionItems = computed(() =>
        FUNCTION_GROUPS.find((group) => group.key === activeFunctionGroup.value)?.items ?? []
    );
    const completionSuggestions = computed(() => {
        const prefix = completionPrefix.value.toLowerCase();
        if (prefix.length < 1) return [];

        const scopeSuggestions = Object.keys(lastScope.value).map((name) => ({
            label: name,
            insert: name,
            group: '変数',
        }));

        return [...SUGGESTION_ITEMS, ...scopeSuggestions]
            .filter((item) => item.label.toLowerCase().startsWith(prefix) || item.insert.toLowerCase().startsWith(prefix))
            .slice(0, 8);
    });
    const decResult = computed(() => {
        if (result.value === null) return '-';
        if (isComplexResult.value) return normalizeComplexString(result.value);
        return formatValue(result.value);
    });
    const engResult = computed(() => {
        if (typeof result.value === 'number') return formatEngineering(result.value);
        if (typeof result.value === 'bigint') {
            const numericValue = Number(result.value);
            return Number.isSafeInteger(numericValue) ? formatEngineering(numericValue) : '-';
        }
        return '-';
    });
    const bitBaseValue = computed(() => {
        if (intResult.value === null) return null;
        try {
            return BigInt.asUintN(bitWidth.value, intResult.value);
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
        if (unsafeIntegerNumber.value) return UNSAFE_INTEGER_DISPLAY;
        if (displayIntValue.value === null) return decResult.value;
        return formatBigInt(displayIntValue.value);
    });
    const hexResult = computed(() => {
        if (unsafeIntegerNumber.value) return UNSAFE_INTEGER_DISPLAY;
        if (bitBaseValue.value === null) return '-';
        const digits = Math.max(1, Math.ceil(bitWidth.value / 4));
        return `0x${bitBaseValue.value.toString(16).toUpperCase().padStart(digits, '0')}`;
    });
    const binResult = computed(() => {
        if (unsafeIntegerNumber.value) return UNSAFE_INTEGER_DISPLAY;
        if (bitBaseValue.value === null) return '-';
        return `0b${bitBaseValue.value.toString(2).padStart(bitWidth.value, '0')}`;
    });
    const octResult = computed(() => {
        if (unsafeIntegerNumber.value) return UNSAFE_INTEGER_DISPLAY;
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

    const updateCompletion = () => {
        const element = calcTextarea.value;
        if (!element) return;

        const cursor = element.selectionStart ?? 0;
        const before = expr.value.slice(0, cursor);
        const match = before.match(/[A-Za-z_][\w]*$/u);
        completionPrefix.value = match?.[0] ?? '';
        completionEnd.value = cursor;
        completionStart.value = cursor - completionPrefix.value.length;
    };

    const onEditorInput = () => {
        nextTick(updateCompletion);
    };

    const onEditorKeydown = (event) => {
        if (event.key === 'Tab' && completionSuggestions.value.length > 0) {
            event.preventDefault();
            insertCompletion(completionSuggestions.value[0]);
        }
    };

    const insertCompletion = (item) => {
        const start = completionStart.value;
        const end = completionEnd.value;
        const nextExpr = `${expr.value.slice(0, start)}${item.insert}${expr.value.slice(end)}`;
        const cursor = start + item.insert.length - (item.insert.endsWith('()') ? 1 : 0);
        expr.value = nextExpr;
        completionPrefix.value = '';

        nextTick(() => {
            calcTextarea.value?.focus();
            calcTextarea.value?.setSelectionRange(cursor, cursor);
            updateCompletion();
        });
    };

    const applySnippet = (value) => {
        expr.value = value;
        nextTick(updateCompletion);
    };

    watch(expr, () => {
        run(false);
        nextTick(updateCompletion);
    });

    const clearCalc = () => {
        expr.value = '';
        result.value = null;
        error.value = '';
        errorLine.value = null;
        errorColumn.value = null;
        lastScope.value = {};
        completionPrefix.value = '';
    };

    const useHistory = (item) => {
        expr.value = item.expr;
        run(false);
    };

    const clearHistory = () => {
        history.value = [];
        storageSet(HISTORY_KEY, []);
    };

    const pinHistory = (item) => {
        if (favorites.value.some((favorite) => favorite.expr === item.expr)) return;
        favorites.value.unshift({ id: Date.now(), expr: item.expr });
        favorites.value = favorites.value.slice(0, 12);
        storageSet(FAVORITES_KEY, favorites.value);
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
        bitWidth, signedMode, bitWidthOptions: BIT_WIDTH_OPTIONS, unsafeIntegerNumber,
        functionGroups: FUNCTION_GROUPS, activeFunctionGroup, activeFunctionItems, functionCatalogOpen,
        syntaxLines, tokenClass, calcTextarea, completionPrefix, completionSuggestions,
        run, clearCalc, useHistory, clearHistory, pinHistory, saveFavorite, useFavorite, copyResult,
        saveCurrentToHistory, applySnippet, insertCompletion, onEditorInput, onEditorKeydown, updateCompletion,
        formatNum, formatValue,
    };
}

export const __engineeringCalcTest = {
    formatNum,
    formatValue,
    safeEval,
    evaluateProgram,
    evaluateComplexProgram,
    transformBitwiseOperators,
    tryEvaluateExactIntegerExpression,
    exactDisplayInt,
    isUnsafeIntegerNumber,
};
