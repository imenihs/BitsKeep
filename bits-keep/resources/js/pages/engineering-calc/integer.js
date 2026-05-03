const MAX_SAFE_BIGINT = BigInt(Number.MAX_SAFE_INTEGER);
const MIN_SAFE_BIGINT = BigInt(Number.MIN_SAFE_INTEGER);

class ExactIntegerParseError extends Error {}

// 目的: 工学電卓のtokenize Exact Integer Expressionを扱う。機能: 入力値を検証・整形し、画面または計算処理へ渡す。入力: 関数シグネチャの値。出力: 表示値、配列、オブジェクト、数値のいずれか。動作条件: 工学電卓の初期化後に呼び出す。副作用: なし。
function tokenizeExactIntegerExpression(expr) {
    const tokens = [];
    let index = 0;

    // 目的: 工学電卓のfailを扱う。機能: 入力値を検証・整形し、画面または計算処理へ渡す。入力: 関数シグネチャの値。出力: 処理結果またはなし。動作条件: 工学電卓の初期化後に呼び出す。副作用: Vue状態、localStorage、DOM、HTTP通信のいずれかを更新する場合がある。
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

// 目的: 工学電卓のparse Integer Literalを扱う。機能: 入力値を検証・整形し、画面または計算処理へ渡す。入力: 関数シグネチャの値。出力: 表示値、配列、オブジェクト、数値のいずれか。動作条件: 工学電卓の初期化後に呼び出す。副作用: なし。
function parseIntegerLiteral(text) {
    return BigInt(text);
}

// 目的: 工学電卓のto Exact Integerを扱う。機能: 入力値を検証・整形し、画面または計算処理へ渡す。入力: 関数シグネチャの値。出力: 処理結果またはなし。動作条件: 工学電卓の初期化後に呼び出す。副作用: なし。
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

// 目的: 工学電卓のensure Exact Argsを扱う。機能: 入力値を検証・整形し、画面または計算処理へ渡す。入力: 関数シグネチャの値。出力: 処理結果またはなし。動作条件: 工学電卓の初期化後に呼び出す。副作用: Vue状態、localStorage、DOM、HTTP通信のいずれかを更新する場合がある。
function ensureExactArgs(name, args, min, max = min) {
    if (args.length < min || args.length > max) {
        throw new ExactIntegerParseError(`${name} argument count mismatch`);
    }
}

// 目的: 工学電卓のreduce Exact Bitsを扱う。機能: 入力値を検証・整形し、画面または計算処理へ渡す。入力: 関数シグネチャの値。出力: 処理結果またはなし。動作条件: 工学電卓の初期化後に呼び出す。副作用: Vue状態、localStorage、DOM、HTTP通信のいずれかを更新する場合がある。
function reduceExactBits(args, fallback, reducer) {
    if (!args.length) return fallback;
    const [first, ...rest] = args;
    return rest.reduce(reducer, first);
}

// 目的: 工学電卓のapply Exact Integer Functionを扱う。機能: 入力値を検証・整形し、画面または計算処理へ渡す。入力: 関数シグネチャの値。出力: 処理結果またはなし。動作条件: 工学電卓の初期化後に呼び出す。副作用: Vue状態、localStorage、DOM、HTTP通信のいずれかを更新する場合がある。
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

// 目的: 工学電卓のfinalize Integer Resultを扱う。機能: 入力値を検証・整形し、画面または計算処理へ渡す。入力: 関数シグネチャの値。出力: 処理結果またはなし。動作条件: 工学電卓の初期化後に呼び出す。副作用: Vue状態、localStorage、DOM、HTTP通信のいずれかを更新する場合がある。
function finalizeIntegerResult(value) {
    if (value <= MAX_SAFE_BIGINT && value >= MIN_SAFE_BIGINT) {
        return Number(value);
    }
    return value;
}

/**
 * 目的: Number精度を失う可能性がある整数式をBigIntベースで先に評価する。
 * 機能: 整数リテラル、ビット演算、整数関数だけで構成された式を独自パーサで処理する。
 * 入力: `expr` は評価対象の1行式、`scope` は代入済みの整数値候補。
 * 出力: 安全整数範囲内ならNumber、範囲外ならBigInt、対象外の式ならnull。
 * 動作条件: 小数、未対応関数、論理演算子などが含まれる場合は通常評価へ譲るためnullを返す。
 * 副作用: なし。安全整数範囲外のNumberを整数演算へ渡した場合だけ例外で止める。
 */
export function tryEvaluateExactIntegerExpression(expr, scope = {}) {
    try {
        const tokens = tokenizeExactIntegerExpression(expr);
        if (!tokens.length) return null;
        return finalizeIntegerResult(new ExactIntegerParser(tokens, scope).parse());
    } catch (error) {
        if (error instanceof ExactIntegerParseError) return null;
        throw error;
    }
}

// 目的: 工学電卓のto Bit Big Intを扱う。機能: 入力値を検証・整形し、画面または計算処理へ渡す。入力: 関数シグネチャの値。出力: 処理結果またはなし。動作条件: 工学電卓の初期化後に呼び出す。副作用: なし。
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

// 目的: 工学電卓のfinalize Bit Resultを扱う。機能: 入力値を検証・整形し、画面または計算処理へ渡す。入力: 関数シグネチャの値。出力: 処理結果またはなし。動作条件: 工学電卓の初期化後に呼び出す。副作用: Vue状態、localStorage、DOM、HTTP通信のいずれかを更新する場合がある。
function finalizeBitResult(value) {
    return finalizeIntegerResult(value);
}

/**
 * 目的: 電卓式から呼べる正確なビット演算ヘルパーを生成する。
 * 機能: and/or/not/xor系とシフト演算をBigIntで処理し、表示可能ならNumberへ戻す。
 * 入力: なし。各戻り関数は整数値、真偽値、BigIntを受け取る。
 * 出力: safeEval/mathjs parserへ注入するビット演算関数群。
 * 動作条件: 各引数は有限の整数であること。小数や安全範囲外Numberは例外にする。
 * 副作用: なし。
 */
export function buildBitHelpers() {
    // 目的: 工学電卓のreduce Bitsを扱う。機能: 入力値を検証・整形し、画面または計算処理へ渡す。入力: 関数シグネチャの値。出力: 処理結果またはなし。動作条件: 工学電卓の初期化後に呼び出す。副作用: Vue状態、localStorage、DOM、HTTP通信のいずれかを更新する場合がある。
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
