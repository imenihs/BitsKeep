import { buildBitHelpers, tryEvaluateExactIntegerExpression } from './integer.js';
export { tryEvaluateExactIntegerExpression } from './integer.js';
import { all, create } from 'mathjs';
import { ENGINEERING_VALUE_PREFIX_FACTORS } from '../../utils/engineeringUnits.js';

export const HISTORY_KEY = 'bitskeep-calc-history';
export const FAVORITES_KEY = 'bitskeep-calc-favorites';
export const math = create(all);
const CALC_PREFIX_TOKENS = ['Ti', 'Gi', 'Mi', 'Ki', 'T', 'G', 'M', 'k', 'm', 'u', 'n', 'p', 'f'];
const PREFIX_MAP = Object.fromEntries(CALC_PREFIX_TOKENS.map((prefix) => [
    prefix,
    `*${ENGINEERING_VALUE_PREFIX_FACTORS[prefix] ?? 1}`,
]));
export const BIT_WIDTH_OPTIONS = [8, 12, 16, 24, 32, 48, 64];
export const UNSAFE_INTEGER_DISPLAY = 'Number安全整数範囲外のため整数表示を抑制';

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

// 目的: 工学電卓のflatten Numbersを扱う。機能: 入力値を検証・整形し、画面または計算処理へ渡す。入力: 関数シグネチャの値。出力: 処理結果またはなし。動作条件: 工学電卓の初期化後に呼び出す。副作用: Vue状態、localStorage、DOM、HTTP通信のいずれかを更新する場合がある。
function flattenNumbers(values) {
    return values.flat(Infinity).map(Number).filter((value) => Number.isFinite(value));
}

// 目的: 工学電卓のclamp Byteを扱う。機能: 入力値を検証・整形し、画面または計算処理へ渡す。入力: 関数シグネチャの値。出力: 処理結果またはなし。動作条件: 工学電卓の初期化後に呼び出す。副作用: なし。
function clampByte(value) {
    return Math.max(0, Math.min(255, Math.round(Number(value) || 0)));
}

// 目的: 工学電卓のrgbを扱う。機能: 入力値を検証・整形し、画面または計算処理へ渡す。入力: 関数シグネチャの値。出力: 処理結果またはなし。動作条件: 工学電卓の初期化後に呼び出す。副作用: Vue状態、localStorage、DOM、HTTP通信のいずれかを更新する場合がある。
function rgb(r, g, b) {
    const bytes = [r, g, b].map(clampByte);
    return `#${bytes.map((value) => value.toString(16).padStart(2, '0')).join('').toUpperCase()}`;
}

// 目的: 工学電卓のdate Diffを扱う。機能: 入力値を検証・整形し、画面または計算処理へ渡す。入力: 関数シグネチャの値。出力: 処理結果またはなし。動作条件: 工学電卓の初期化後に呼び出す。副作用: Vue状態、localStorage、DOM、HTTP通信のいずれかを更新する場合がある。
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

export const FUNCTION_GROUPS = [
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

// 目的: 工学電卓のcompletion Insert Textを扱う。機能: 入力値を検証・整形し、画面または計算処理へ渡す。入力: 関数シグネチャの値。出力: 処理結果またはなし。動作条件: 工学電卓の初期化後に呼び出す。副作用: Vue状態、localStorage、DOM、HTTP通信のいずれかを更新する場合がある。
function completionInsertText(name) {
    const primary = name.split(',')[0].trim();
    return primary.includes('(') ? primary.replace(/\(.+\)$/u, '()') : primary;
}

export const SUGGESTION_ITEMS = FUNCTION_GROUPS.flatMap((group) =>
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

/**
 * 目的: 電卓の履歴またはお気に入りをWeb Storageから復元する。
 * 機能: 履歴キーはsessionStorage、それ以外はlocalStorageを読み、JSONとして解釈する。
 * 入力: `key` は保存キー、`fallback` は未保存または破損時の既定値。
 * 出力: 復元した値、または `fallback`。
 * 動作条件: ブラウザの `window.sessionStorage` / `window.localStorage` が使えること。
 * 副作用: 読み取りのみ。破損JSONは握りつぶして既定値へ戻す。
 */
export function storageGet(key, fallback) {
    try {
        const storage = key === HISTORY_KEY ? window.sessionStorage : window.localStorage;
        return JSON.parse(storage.getItem(key) ?? 'null') ?? fallback;
    } catch {
        return fallback;
    }
}

/**
 * 目的: 電卓の履歴またはお気に入りをWeb Storageへ保存する。
 * 機能: 履歴キーはsessionStorage、それ以外はlocalStorageへJSON文字列で保存する。
 * 入力: `key` は保存キー、`value` はJSON化できる保存対象。
 * 出力: 戻り値なし。
 * 動作条件: ブラウザのStorage APIが使え、`value` が循環参照を含まないこと。
 * 副作用: 対象Storageの同名キーを上書きする。
 */
export function storageSet(key, value) {
    const storage = key === HISTORY_KEY ? window.sessionStorage : window.localStorage;
    storage.setItem(key, JSON.stringify(value));
}

// 目的: 工学電卓のformat Numを扱う。機能: 入力値を検証・整形し、画面または計算処理へ渡す。入力: 関数シグネチャの値。出力: 表示値、配列、オブジェクト、数値のいずれか。動作条件: 工学電卓の初期化後に呼び出す。副作用: なし。
export function formatNum(n) {
    if (n === null || n === undefined || Number.isNaN(n)) return '-';
    if (typeof n === 'bigint') return formatBigInt(n);
    if (!Number.isFinite(n)) return String(n);
    if (Number.isInteger(n) && Math.abs(n) < 1e15) return n.toLocaleString();

    const text = Number(n).toPrecision(10).replace(/e\+?/u, 'e');
    if (!text.includes('e')) return text.replace(/\.?0+$/u, '');

    const [mantissa, exponent] = text.split('e');
    return `${mantissa.replace(/\.?0+$/u, '')}e${exponent}`;
}

// 目的: 工学電卓のformat Engineeringを扱う。機能: 入力値を検証・整形し、画面または計算処理へ渡す。入力: 関数シグネチャの値。出力: 表示値、配列、オブジェクト、数値のいずれか。動作条件: 工学電卓の初期化後に呼び出す。副作用: なし。
export function formatEngineering(n) {
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

// 目的: 工学電卓のnormalize Complex Stringを扱う。機能: 入力値を検証・整形し、画面または計算処理へ渡す。入力: 関数シグネチャの値。出力: 表示値、配列、オブジェクト、数値のいずれか。動作条件: 工学電卓の初期化後に呼び出す。副作用: なし。
export function normalizeComplexString(value) {
    const re = Number(value.re ?? 0);
    const im = Number(value.im ?? 0);
    const reText = formatNum(re);
    const imAbsText = formatNum(Math.abs(im));

    if (im === 0) return reText;
    if (re === 0) return `${im < 0 ? '-' : ''}${imAbsText}j`;
    return `${reText} ${im < 0 ? '-' : '+'} ${imAbsText}j`;
}

// 目的: 工学電卓のcomplex Polarを扱う。機能: 入力値を検証・整形し、画面または計算処理へ渡す。入力: 関数シグネチャの値。出力: 処理結果またはなし。動作条件: 工学電卓の初期化後に呼び出す。副作用: Vue状態、localStorage、DOM、HTTP通信のいずれかを更新する場合がある。
export function complexPolar(value, unit = 'deg') {
    const re = Number(value.re ?? 0);
    const im = Number(value.im ?? 0);
    const mag = Math.sqrt((re ** 2) + (im ** 2));
    const rad = Math.atan2(im, re);
    const angle = unit === 'deg' ? (rad * 180 / Math.PI) : rad;
    return `${formatNum(mag)} ∠ ${formatNum(angle)}${unit === 'deg' ? 'deg' : 'rad'}`;
}

/**
 * 目的: 計算結果を画面と履歴に出せる文字列へ変換する。
 * 機能: 複素数、BigInt、配列、関数、文字列、安全でない整数を型別に整形する。
 * 入力: 評価エンジンが返した任意の値。
 * 出力: 利用者へ表示する文字列。
 * 動作条件: mathjs複素数は `math.isComplex` で判定できること。
 * 副作用: なし。
 */
export function formatValue(value) {
    if (math.isComplex(value)) return normalizeComplexString(value);
    if (typeof value === 'bigint') return formatBigInt(value);
    if (typeof value === 'function') return 'function';
    if (Array.isArray(value)) return `[${value.map(formatValue).join(', ')}]`;
    if (typeof value === 'string') return value;
    if (isUnsafeIntegerNumber(value)) return UNSAFE_INTEGER_DISPLAY;
    return formatNum(value);
}

// 目的: 工学電卓のformat Big Intを扱う。機能: 入力値を検証・整形し、画面または計算処理へ渡す。入力: 関数シグネチャの値。出力: 表示値、配列、オブジェクト、数値のいずれか。動作条件: 工学電卓の初期化後に呼び出す。副作用: なし。
export function formatBigInt(value) {
    const sign = value < 0 ? '-' : '';
    const abs = (value < 0 ? -value : value).toString();
    return `${sign}${abs.replace(/\B(?=(\d{3})+(?!\d))/g, ',')}`;
}

// 目的: 工学電卓のis Unsafe Integer Numberを扱う。機能: 入力値を検証・整形し、画面または計算処理へ渡す。入力: 関数シグネチャの値。出力: 真偽値。動作条件: 工学電卓の初期化後に呼び出す。副作用: なし。
export function isUnsafeIntegerNumber(value) {
    return typeof value === 'number' && Number.isInteger(value) && !Number.isSafeInteger(value);
}

// 目的: 工学電卓のexact Display Intを扱う。機能: 入力値を検証・整形し、画面または計算処理へ渡す。入力: 関数シグネチャの値。出力: 処理結果またはなし。動作条件: 工学電卓の初期化後に呼び出す。副作用: Vue状態、localStorage、DOM、HTTP通信のいずれかを更新する場合がある。
export function exactDisplayInt(value) {
    if (typeof value === 'bigint') return value;
    if (Number.isSafeInteger(value)) return BigInt(value);
    return null;
}

// 目的: 工学電卓のsplit Top Levelを扱う。機能: 入力値を検証・整形し、画面または計算処理へ渡す。入力: 関数シグネチャの値。出力: 処理結果またはなし。動作条件: 工学電卓の初期化後に呼び出す。副作用: Vue状態、localStorage、DOM、HTTP通信のいずれかを更新する場合がある。
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

// 目的: 工学電卓のfind Top Level Equalsを扱う。機能: 入力値を検証・整形し、画面または計算処理へ渡す。入力: 関数シグネチャの値。出力: 表示値、配列、オブジェクト、数値のいずれか。動作条件: 工学電卓の初期化後に呼び出す。副作用: なし。
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


// 目的: 工学電卓のfind Matching Groupを扱う。機能: 入力値を検証・整形し、画面または計算処理へ渡す。入力: 関数シグネチャの値。出力: 表示値、配列、オブジェクト、数値のいずれか。動作条件: 工学電卓の初期化後に呼び出す。副作用: なし。
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

// 目的: 工学電卓のtransform Nested Bitwise Groupsを扱う。機能: 入力値を検証・整形し、画面または計算処理へ渡す。入力: 関数シグネチャの値。出力: 処理結果またはなし。動作条件: 工学電卓の初期化後に呼び出す。副作用: Vue状態、localStorage、DOM、HTTP通信のいずれかを更新する場合がある。
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

// 目的: 工学電卓のtop Level Operatorを扱う。機能: 入力値を検証・整形し、画面または計算処理へ渡す。入力: 関数シグネチャの値。出力: 処理結果またはなし。動作条件: 工学電卓の初期化後に呼び出す。副作用: なし。
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

// 目的: 工学電卓のtransform Top Level Bitwiseを扱う。機能: 入力値を検証・整形し、画面または計算処理へ渡す。入力: 関数シグネチャの値。出力: 処理結果またはなし。動作条件: 工学電卓の初期化後に呼び出す。副作用: Vue状態、localStorage、DOM、HTTP通信のいずれかを更新する場合がある。
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

// 目的: 工学電卓のtransform Bitwise Operatorsを扱う。機能: 入力値を検証・整形し、画面または計算処理へ渡す。入力: 関数シグネチャの値。出力: 処理結果またはなし。動作条件: 工学電卓の初期化後に呼び出す。副作用: Vue状態、localStorage、DOM、HTTP通信のいずれかを更新する場合がある。
export function transformBitwiseOperators(expr) {
    return transformTopLevelBitwise(transformNestedBitwiseGroups(expr));
}

// 目的: 工学電卓のtransform Prefixesを扱う。機能: 入力値を検証・整形し、画面または計算処理へ渡す。入力: 関数シグネチャの値。出力: 処理結果またはなし。動作条件: 工学電卓の初期化後に呼び出す。副作用: Vue状態、localStorage、DOM、HTTP通信のいずれかを更新する場合がある。
function transformPrefixes(expr) {
    return expr.replace(/(\d+(?:\.\d+)?(?:e[+\-]?\d+)?)(Ti|Gi|Mi|Ki|T|G|M|k|m|u|n|p|f)\b/g, (_, num, suffix) =>
        `(${num}${PREFIX_MAP[suffix]})`
    );
}

// 目的: 工学電卓のbuild Helpersを扱う。機能: 入力値を検証・整形し、画面または計算処理へ渡す。入力: 関数シグネチャの値。出力: 表示値、配列、オブジェクト、数値のいずれか。動作条件: 工学電卓の初期化後に呼び出す。副作用: なし。
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

// 目的: 工学電卓のnearest ESeriesを扱う。機能: 入力値を検証・整形し、画面または計算処理へ渡す。入力: 関数シグネチャの値。出力: 処理結果またはなし。動作条件: 工学電卓の初期化後に呼び出す。副作用: Vue状態、localStorage、DOM、HTTP通信のいずれかを更新する場合がある。
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

// 目的: 工学電卓のtransform Expressionを扱う。機能: 入力値を検証・整形し、画面または計算処理へ渡す。入力: 関数シグネチャの値。出力: 処理結果またはなし。動作条件: 工学電卓の初期化後に呼び出す。副作用: Vue状態、localStorage、DOM、HTTP通信のいずれかを更新する場合がある。
function transformExpression(expr) {
    return expr
        .replace(/\r/g, '')
        .replace(/\^/g, '**');
}

// 目的: 工学電卓のtransform Complex Expressionを扱う。機能: 入力値を検証・整形し、画面または計算処理へ渡す。入力: 関数シグネチャの値。出力: 処理結果またはなし。動作条件: 工学電卓の初期化後に呼び出す。副作用: Vue状態、localStorage、DOM、HTTP通信のいずれかを更新する場合がある。
function transformComplexExpression(expr) {
    return expr
        .replace(/\r/g, '')
        .replace(/\^/g, '^')
        .replace(/(^|[^\w.])j\b/g, '$1i')
        .replace(/(\d+(?:\.\d+)?(?:e[+\-]?\d+)?)j\b/g, '$1i');
}

// 目的: 工学電卓のsolve Equationを扱う。機能: 入力値を検証・整形し、画面または計算処理へ渡す。入力: 関数シグネチャの値。出力: 処理結果またはなし。動作条件: 工学電卓の初期化後に呼び出す。副作用: Vue状態、localStorage、DOM、HTTP通信のいずれかを更新する場合がある。
function solveEquation(fnOrExpr, guess = 1, maxIter = 32, tol = 1e-9, scope = {}) {
    const fn = typeof fnOrExpr === 'function'
        ? fnOrExpr
        : (x) => safeEval(String(fnOrExpr).replace(/\bx\b/g, `(${x})`), scope);

    const guesses = Array.isArray(guess) ? guess : [guess, 1, 10, 0.1, -1, 100, -10];
    let best = null;
    const acceptableError = Math.max(tol * 10, 1e-7);

    // 目的: 工学電卓のrememberを扱う。機能: 入力値を検証・整形し、画面または計算処理へ渡す。入力: 関数シグネチャの値。出力: 処理結果またはなし。動作条件: 工学電卓の初期化後に呼び出す。副作用: Vue状態、localStorage、DOM、HTTP通信のいずれかを更新する場合がある。
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

// 目的: 工学電卓のsolve Equation Expressionを扱う。機能: 入力値を検証・整形し、画面または計算処理へ渡す。入力: 関数シグネチャの値。出力: 処理結果またはなし。動作条件: 工学電卓の初期化後に呼び出す。副作用: Vue状態、localStorage、DOM、HTTP通信のいずれかを更新する場合がある。
function solveEquationExpression(equation, variableName, scope = {}) {
    const eqIndex = findTopLevelEquals(equation);
    if (eqIndex === -1) throw new Error('solve(eq, var) の第1引数は = を含む式にしてください');

    const left = equation.slice(0, eqIndex).trim();
    const right = equation.slice(eqIndex + 1).trim();
    // 目的: 工学電卓のfnを扱う。機能: 入力値を検証・整形し、画面または計算処理へ渡す。入力: 関数シグネチャの値。出力: 処理結果またはなし。動作条件: 工学電卓の初期化後に呼び出す。副作用: Vue状態、localStorage、DOM、HTTP通信のいずれかを更新する場合がある。
    const fn = (candidate) => {
        const localScope = { ...scope, [variableName]: candidate };
        return Number(safeEval(left, localScope)) - Number(safeEval(right, localScope));
    };

    return solveEquation(fn, [1, 10, 0.1, 20, -1], 48, 1e-9, scope);
}

// 目的: 工学電卓のtry Solve Equation Callを扱う。機能: 入力値を検証・整形し、画面または計算処理へ渡す。入力: 関数シグネチャの値。出力: 処理結果またはなし。動作条件: 工学電卓の初期化後に呼び出す。副作用: Vue状態、localStorage、DOM、HTTP通信のいずれかを更新する場合がある。
function trySolveEquationCall(expr, scope = {}) {
    const trimmed = expr.trim();
    if (!trimmed.startsWith('solve(') || !trimmed.endsWith(')')) return null;

    const args = splitTopLevel(trimmed.slice(6, -1));
    if (args.length !== 2 || !args[0].includes('=')) return null;

    const variableName = args[1].trim();
    if (!/^[a-zA-Z_]\w*$/u.test(variableName)) return null;

    return solveEquationExpression(args[0], variableName, scope);
}

// 目的: 工学電卓のnormalize Eval Errorを扱う。機能: 入力値を検証・整形し、画面または計算処理へ渡す。入力: 関数シグネチャの値。出力: 表示値、配列、オブジェクト、数値のいずれか。動作条件: 工学電卓の初期化後に呼び出す。副作用: なし。
function normalizeEvalError(error, lineNumber, lineText) {
    return {
        message: error.message ?? String(error),
        lineNumber,
        column: error.char ?? null,
        lineText,
    };
}

/**
 * 目的: 電卓式をブラウザ上で評価する前に、許可した構文とヘルパーへ正規化する。
 * 機能: solve(eq,var)、正確な整数演算、SI接頭辞、ビット演算子を順に処理して評価する。
 * 入力: `expr` は1行の式、`scope` は既存変数/関数の名前空間。
 * 出力: 評価結果の値。整数安全範囲外はBigIntで返る場合がある。
 * 動作条件: import/require/eval/Function等の文字列は除去し、許可ヘルパーだけをスコープへ渡す。
 * 副作用: `new Function` を生成して即時評価するが、渡す名前空間以外の状態は更新しない。
 */
export function safeEval(expr, scope = {}) {
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

/**
 * 目的: 複数行の電卓プログラムを上から順に評価する。
 * 機能: 変数代入、簡易関数定義、通常式を同一スコープで処理し、最後の値を返す。
 * 入力: `program` は改行区切りの式文字列。
 * 出力: `{ value, expr, scope }`。`value` は最後の結果、`expr` は最後に評価した式、`scope` はユーザー定義値。
 * 動作条件: 空行は無視し、代入左辺は識別子または単純な関数定義だけを受け付ける。
 * 副作用: ローカルスコープを構築するだけで、外部StorageやDOMは変更しない。
 */
export function evaluateProgram(program) {
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

// 目的: 工学電卓のsyntax Tokensを扱う。機能: 入力値を検証・整形し、画面または計算処理へ渡す。入力: 関数シグネチャの値。出力: 処理結果またはなし。動作条件: 工学電卓の初期化後に呼び出す。副作用: Vue状態、localStorage、DOM、HTTP通信のいずれかを更新する場合がある。
export function syntaxTokens(line) {
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

// 目的: 工学電卓のtoken Classを扱う。機能: 入力値を検証・整形し、画面または計算処理へ渡す。入力: 関数シグネチャの値。出力: 表示値、配列、オブジェクト、数値のいずれか。動作条件: 工学電卓の初期化後に呼び出す。副作用: なし。
export function tokenClass(type) {
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

// 目的: 工学電卓のshould Use Complex Engineを扱う。機能: 入力値を検証・整形し、画面または計算処理へ渡す。入力: 関数シグネチャの値。出力: 真偽値。動作条件: 工学電卓の初期化後に呼び出す。副作用: Vue状態、localStorage、DOM、HTTP通信のいずれかを更新する場合がある。
export function shouldUseComplexEngine(program) {
    return /(^|[^a-zA-Z_])j\b|=\s*.*j\b|\d+j\b/u.test(program);
}

// 目的: 工学電卓のcreate Complex Scopeを扱う。機能: 入力値を検証・整形し、画面または計算処理へ渡す。入力: 関数シグネチャの値。出力: 表示値、配列、オブジェクト、数値のいずれか。動作条件: 工学電卓の初期化後に呼び出す。副作用: なし。
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

/**
 * 目的: `j` を含む複素数式をmathjs parserで複数行評価する。
 * 機能: 電卓共通ヘルパーをparserへ登録し、最後の評価結果とユーザー定義スコープを返す。
 * 入力: `program` は複素数表記を含む可能性がある改行区切り式。
 * 出力: `{ value, expr, scope }`。`scope` には利用者が定義した非関数値だけを含める。
 * 動作条件: `j` はmathjsの虚数単位 `i` へ変換して評価する。
 * 副作用: 関数内で生成したmathjs parserだけを更新し、外部状態は変更しない。
 */
export function evaluateComplexProgram(program) {
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
