export const SI_PREFIX_FACTORS = {
    Y: 1e24,
    Z: 1e21,
    E: 1e18,
    P: 1e15,
    T: 1e12,
    G: 1e9,
    M: 1e6,
    k: 1e3,
    '': 1,
    m: 1e-3,
    u: 1e-6,
    n: 1e-9,
    p: 1e-12,
    f: 1e-15,
};

export const IEC_PREFIX_FACTORS = {
    Ti: 1099511627776,
    Gi: 1073741824,
    Mi: 1048576,
    Ki: 1024,
};

export const PREFIX_FACTORS = {
    ...SI_PREFIX_FACTORS,
    ...IEC_PREFIX_FACTORS,
};

export const ENGINEERING_VALUE_PREFIX_FACTORS = {
    ...PREFIX_FACTORS,
    K: 1e3,
    meg: 1e6,
};

export const DECIMAL_PREFIX_OPTIONS = ['T', 'G', 'M', 'k', '', 'm', 'u', 'n', 'p', 'f'];
export const BYTE_BIT_PREFIX_OPTIONS = ['T', 'G', 'M', 'k', '', 'Ti', 'Gi', 'Mi', 'Ki'];
export const DECIMAL_PREFIX_ORDER = ['Y', 'Z', 'E', 'P', 'T', 'G', 'M', 'k', '', 'm', 'u', 'n', 'p', 'f'];
export const UNIVERSAL_PREFIX_ORDER = ['Y', 'Z', 'E', 'P', 'Ti', 'Gi', 'Mi', 'Ki', 'T', 'G', 'M', 'k', '', 'm', 'u', 'n', 'p', 'f'];
export const BYTE_BIT_PREFIX_ORDER = ['T', 'G', 'M', 'k', ''];
export const BINARY_IEC_PREFIXES = new Set(['Ti', 'Gi', 'Mi', 'Ki']);
export const DECIMAL_NON_FRACTIONAL_PREFIXES = new Set(['T', 'G', 'M', 'k']);
export const DECIMAL_FRACTIONAL_PREFIXES = new Set(['m', 'u', 'n', 'p', 'f']);
export const BYTE_BIT_BASE_UNITS = new Set(['B', 'bit', 'bps']);

/**
 * 接頭語入力を保存・比較用の表記へ正規化する。
 * 入力は任意値、戻り値は `k`、`u`、`meg` などの接頭語文字列で、副作用はない。
 */
// 目的: 共通ユーティリティのnormalize Prefix Tokenを扱う。機能: 入力値を検証・整形し、画面または計算処理へ渡す。入力: 関数シグネチャの値。出力: 表示値、配列、オブジェクト、数値のいずれか。動作条件: 共通ユーティリティの初期化後に呼び出す。副作用: なし。
export const normalizePrefixToken = (prefix) => {
    const normalized = prefix == null ? '' : String(prefix).trim();
    if (normalized === 'K') return 'k';
    if (normalized === 'µ' || normalized === 'μ') return 'u';
    if (/^meg$/iu.test(normalized)) return 'meg';

    return normalized;
};

/**
 * 接頭語配列を既知の値だけに絞り、順序を保った重複なし配列へ変換する。
 * 不正値はUI候補へ残さないため除外し、入力配列は変更しない。
 */
// 目的: 共通ユーティリティのnormalize Prefix Listを扱う。機能: 入力値を検証・整形し、画面または計算処理へ渡す。入力: 関数シグネチャの値。出力: 表示値、配列、オブジェクト、数値のいずれか。動作条件: 共通ユーティリティの初期化後に呼び出す。副作用: なし。
export const normalizePrefixList = (prefixes) => {
    if (!Array.isArray(prefixes)) return [];

    const seen = new Set();
    return prefixes
        .map(normalizePrefixToken)
        .filter((prefix) => Object.hasOwn(ENGINEERING_VALUE_PREFIX_FACTORS, prefix))
        .filter((prefix) => {
            if (seen.has(prefix)) return false;
            seen.add(prefix);
            return true;
        });
};

/**
 * 単位表記ゆれを計算用の共通表記へ寄せる。
 * `µ/μ`、`Ω`、`ohm`、先頭 `K` のみを変換し、数値のスケール変換はしない。
 */
// 目的: 共通ユーティリティのnormalize Unit Labelを扱う。機能: 入力値を検証・整形し、画面または計算処理へ渡す。入力: 関数シグネチャの値。出力: 表示値、配列、オブジェクト、数値のいずれか。動作条件: 共通ユーティリティの初期化後に呼び出す。副作用: なし。
export const normalizeUnitLabel = (value = '') => String(value ?? '')
    .trim()
    .replaceAll('μ', 'u')
    .replaceAll('µ', 'u')
    .replaceAll('Ω', 'Ω')
    .replaceAll('ω', 'Ω')
    .replace(/\bohms?\b/iu, 'Ω')
    .replace(/\bohm\b/iu, 'Ω')
    .replace(/^meg(?=[A-Za-zΩ])/iu, 'M')
    .replace(/^K(?!i)(?=[A-Za-zΩ])/u, 'k');

/**
 * 単位を含む入力文字列を演算前の比較用文字列へ正規化する。
 * 入力は任意の文字列、戻り値は空白や表記ゆれを除いた文字列で、副作用はない。
 */
// 目的: 共通ユーティリティのnormalize Unit Textを扱う。機能: 入力値を検証・整形し、画面または計算処理へ渡す。入力: 関数シグネチャの値。出力: 表示値、配列、オブジェクト、数値のいずれか。動作条件: 共通ユーティリティの初期化後に呼び出す。副作用: なし。
export const normalizeUnitText = (value = '') => normalizeEngineeringText(value);

/**
 * 工学表記の数値/単位文字列から空白、カンマ、全角マイナスなどを除く。
 * 画面入力やCSV由来の文字列を対象にし、戻り値はパース前の正規化文字列で、副作用はない。
 */
// 目的: 共通ユーティリティのnormalize Engineering Textを扱う。機能: 入力値を検証・整形し、画面または計算処理へ渡す。入力: 関数シグネチャの値。出力: 表示値、配列、オブジェクト、数値のいずれか。動作条件: 共通ユーティリティの初期化後に呼び出す。副作用: なし。
export const normalizeEngineeringText = (value = '') => normalizeUnitLabel(value)
    .replace(/[，,]/gu, '')
    .replace(/[−－–—]/gu, '-')
    .replace(/\s+/gu, '');

/**
 * 単位がB/bit/bps系かを判定する。
 * 入力は単位文字列、戻り値はIEC接頭語ポリシーの対象可否で、副作用はない。
 */
// 目的: 共通ユーティリティのis Byte Bit Unitを扱う。機能: 入力値を検証・整形し、画面または計算処理へ渡す。入力: 関数シグネチャの値。出力: 真偽値。動作条件: 共通ユーティリティの初期化後に呼び出す。副作用: なし。
export const isByteBitUnit = (unit = '') => BYTE_BIT_BASE_UNITS.has(normalizeUnitLabel(unit));

/**
 * 単位に応じた既定の入力接頭語候補を返す。
 * B/bit/bps系では分数接頭語を除外し、戻り値は新しい配列で副作用はない。
 */
// 目的: 共通ユーティリティのdefault Input Prefixes For Unitを扱う。機能: 入力値を検証・整形し、画面または計算処理へ渡す。入力: 関数シグネチャの値。出力: 処理結果またはなし。動作条件: 共通ユーティリティの初期化後に呼び出す。副作用: Vue状態、localStorage、DOM、HTTP通信のいずれかを更新する場合がある。
export const defaultInputPrefixesForUnit = (unit = '') => (
    isByteBitUnit(unit) ? BYTE_BIT_PREFIX_ORDER : ['T', 'G', 'M', 'k', '', 'm', 'u', 'n', 'p', 'f']
);

/**
 * 接頭語配列を係数の大きい順へ並べる。
 * 入力配列は正規化してから扱い、戻り値はソート済み配列で副作用はない。
 */
// 目的: 共通ユーティリティのsort Prefixes By Factorを扱う。機能: 入力値を検証・整形し、画面または計算処理へ渡す。入力: 関数シグネチャの値。出力: 表示値、配列、オブジェクト、数値のいずれか。動作条件: 共通ユーティリティの初期化後に呼び出す。副作用: なし。
export const sortPrefixesByFactor = (prefixes) => normalizePrefixList(prefixes)
    .sort((a, b) => (ENGINEERING_VALUE_PREFIX_FACTORS[b] ?? 1) - (ENGINEERING_VALUE_PREFIX_FACTORS[a] ?? 1));

/**
 * 接頭語の画面表示名を作る。
 * 空文字は無印として扱い、戻り値はラベル文字列で副作用はない。
 */
// 目的: 共通ユーティリティのprefix Labelを扱う。機能: 入力値を検証・整形し、画面または計算処理へ渡す。入力: 関数シグネチャの値。出力: 処理結果またはなし。動作条件: 共通ユーティリティの初期化後に呼び出す。副作用: Vue状態、localStorage、DOM、HTTP通信のいずれかを更新する場合がある。
export const prefixLabel = (prefix) => (prefix === '' ? '無印' : prefix);

/**
 * 接頭語候補配列をUI説明用の短い文字列へ変換する。
 * 入力が空または不正値のみなら無印を返し、副作用はない。
 */
// 目的: 共通ユーティリティのprefix List Textを扱う。機能: 入力値を検証・整形し、画面または計算処理へ渡す。入力: 関数シグネチャの値。出力: 処理結果またはなし。動作条件: 共通ユーティリティの初期化後に呼び出す。副作用: Vue状態、localStorage、DOM、HTTP通信のいずれかを更新する場合がある。
export const prefixListText = (prefixes) => {
    const labels = normalizePrefixList(prefixes).map(prefixLabel);
    return labels.length ? labels.join(' / ') : '無印';
};

/**
 * 単位種別に対して選択可能な接頭語候補だけを残す。
 * B/bit/bps は10進系とIEC系を混在させず、その他の単位ではIEC候補を除外する。
 */
// 目的: 共通ユーティリティのsanitize Prefixes For Unitを扱う。機能: 入力値を検証・整形し、画面または計算処理へ渡す。入力: 関数シグネチャの値。出力: 処理結果またはなし。動作条件: 共通ユーティリティの初期化後に呼び出す。副作用: Vue状態、localStorage、DOM、HTTP通信のいずれかを更新する場合がある。
export const sanitizePrefixesForUnit = (prefixes = [], unit = '') => {
    const normalized = normalizePrefixList(prefixes)
        .filter((prefix) => prefix === '' || DECIMAL_PREFIX_OPTIONS.includes(prefix) || BINARY_IEC_PREFIXES.has(prefix));
    const unique = [...new Set(normalized)];
    if (!isByteBitUnit(unit)) {
        return unique.filter((prefix) => !BINARY_IEC_PREFIXES.has(prefix));
    }

    const withoutFractional = unique.filter((prefix) => !DECIMAL_FRACTIONAL_PREFIXES.has(prefix));
    const hasBinary = withoutFractional.some((prefix) => BINARY_IEC_PREFIXES.has(prefix));
    if (hasBinary) {
        return withoutFractional.filter((prefix) => prefix === '' || BINARY_IEC_PREFIXES.has(prefix));
    }

    return withoutFractional.filter((prefix) => prefix === '' || DECIMAL_NON_FRACTIONAL_PREFIXES.has(prefix));
};

/**
 * 接頭語チェックボックス操作後の候補リストを単位ポリシーへ同期する。
 * IEC系と10進系の排他制御だけを行い、DOM操作などの副作用は持たない。
 */
// 目的: 共通ユーティリティのsync Prefix Selection For Unitを扱う。機能: 入力値を検証・整形し、画面または計算処理へ渡す。入力: 関数シグネチャの値。出力: 処理結果またはなし。動作条件: 共通ユーティリティの初期化後に呼び出す。副作用: Vue状態、localStorage、DOM、HTTP通信のいずれかを更新する場合がある。
export const syncPrefixSelectionForUnit = (prefixes = [], unit = '', changedPrefix = null) => {
    let normalized = normalizePrefixList(prefixes);
    const changed = normalizePrefixToken(changedPrefix);
    if (isByteBitUnit(unit) && normalized.includes(changed)) {
        if (BINARY_IEC_PREFIXES.has(changed)) {
            normalized = normalized.filter((prefix) => !DECIMAL_NON_FRACTIONAL_PREFIXES.has(prefix) && !DECIMAL_FRACTIONAL_PREFIXES.has(prefix));
        } else if (DECIMAL_NON_FRACTIONAL_PREFIXES.has(changed)) {
            normalized = normalized.filter((prefix) => !BINARY_IEC_PREFIXES.has(prefix) && !DECIMAL_FRACTIONAL_PREFIXES.has(prefix));
        }
    }

    return sanitizePrefixesForUnit(normalized, unit);
};

/**
 * 単位に応じた接頭語チェックボックス候補を生成する。
 * 入力は基準単位文字列、戻り値はvalue/label/disabledを持つ配列で、副作用はない。
 */
// 目的: 共通ユーティリティのprefix Options For Unitを扱う。機能: 入力値を検証・整形し、画面または計算処理へ渡す。入力: 関数シグネチャの値。出力: 処理結果またはなし。動作条件: 共通ユーティリティの初期化後に呼び出す。副作用: Vue状態、localStorage、DOM、HTTP通信のいずれかを更新する場合がある。
export const prefixOptionsForUnit = (unit = '') => {
    const byteBit = isByteBitUnit(unit);
    return (byteBit ? BYTE_BIT_PREFIX_OPTIONS : DECIMAL_PREFIX_OPTIONS).map((prefix) => ({
        value: prefix,
        label: prefix === '' ? '（無印）' : prefix,
        disabled: !byteBit && BINARY_IEC_PREFIXES.has(prefix),
    }));
};

/**
 * 接頭語候補UIに表示するポリシー説明文を返す。
 * B/bit/bps系ではIEC排他条件を説明し、戻り値は日本語文で副作用はない。
 */
// 目的: 共通ユーティリティのprefix Policy Help For Unitを扱う。機能: 入力値を検証・整形し、画面または計算処理へ渡す。入力: 関数シグネチャの値。出力: 処理結果またはなし。動作条件: 共通ユーティリティの初期化後に呼び出す。副作用: Vue状態、localStorage、DOM、HTTP通信のいずれかを更新する場合がある。
export const prefixPolicyHelpForUnit = (unit = '', noun = '値入力時') => (
    isByteBitUnit(unit)
        ? 'B / bit / bps 系は 10進（T G M k）または IEC（Ti Gi Mi Ki）のどちらか一方を使います。無印は共通で使えます。'
        : `${noun}の候補接頭辞です。未選択なら汎用候補（T G M k 無印 m u n p f）を使います。`
);

/**
 * 値文字列末尾の単位を取り除いて数値部を取り出す。
 * unitが一致する場合のみ除去し、戻り値は正規化済み文字列で副作用はない。
 */
// 目的: 共通ユーティリティのstrip Unit Suffixを扱う。機能: 入力値を検証・整形し、画面または計算処理へ渡す。入力: 関数シグネチャの値。出力: 処理結果またはなし。動作条件: 共通ユーティリティの初期化後に呼び出す。副作用: Vue状態、localStorage、DOM、HTTP通信のいずれかを更新する場合がある。
export const stripUnitSuffix = (text, unit = '') => {
    const normalized = normalizeEngineeringText(text);
    const normalizedUnit = normalizeEngineeringText(unit);
    if (normalizedUnit && normalized.endsWith(normalizedUnit)) {
        return normalized.slice(0, -normalizedUnit.length);
    }

    return normalized;
};

/**
 * 表示用の値文字列へ単位を補う。
 * 既に同じ単位で終わる場合は二重付与せず、空値はハイフンを返す。
 */
// 目的: 共通ユーティリティのappend Unit For Displayを扱う。機能: 入力値を検証・整形し、画面または計算処理へ渡す。入力: 関数シグネチャの値。出力: 処理結果またはなし。動作条件: 共通ユーティリティの初期化後に呼び出す。副作用: Vue状態、localStorage、DOM、HTTP通信のいずれかを更新する場合がある。
export const appendUnitForDisplay = (value, unit = '') => {
    const text = String(value ?? '').trim();
    if (!text) return '-';
    const normalizedUnit = normalizeUnitLabel(unit);
    if (!normalizedUnit) return text;

    return normalizeUnitLabel(text).endsWith(normalizedUnit) ? text : `${text}${unit}`;
};

/**
 * 接頭語付き数値を値・元数値・接頭語・単位へ分解する。
 * 戻り値の `value` は接頭語係数を掛けた基準値で、未知形式は null を返す。
 */
// 目的: 共通ユーティリティのparse Engineering Number Detailを扱う。機能: 入力値を検証・整形し、画面または計算処理へ渡す。入力: 関数シグネチャの値。出力: 表示値、配列、オブジェクト、数値のいずれか。動作条件: 共通ユーティリティの初期化後に呼び出す。副作用: なし。
export const parseEngineeringNumberDetail = (value) => {
    if (typeof value === 'number') {
        return Number.isFinite(value)
            ? { value, rawValue: value, prefix: '', factor: 1, unit: '', hasPrefix: false, hasUnit: false }
            : null;
    }

    const raw = normalizeEngineeringText(value);
    if (!raw) return null;
    const match = raw.match(/^([-+]?(?:\d+\.?\d*|\.\d+)(?:[eE][-+]?\d+)?)(Ti|Gi|Mi|Ki|MEG|Meg|meg|Y|Z|E|P|T|G|M|k|K|m|u|n|p|f)?([A-Za-zΩ%°℃/_^.-].*)?$/u);
    if (!match) return null;
    const rawValue = Number(match[1]);
    if (!Number.isFinite(rawValue)) return null;
    const rawPrefix = match[2] || '';
    const prefix = normalizePrefixToken(rawPrefix);
    const factor = ENGINEERING_VALUE_PREFIX_FACTORS[prefix] ?? 1;
    const unit = normalizeUnitLabel(match[3] || '');

    return {
        value: rawValue * factor,
        rawValue,
        prefix,
        factor,
        unit,
        hasPrefix: rawPrefix.trim() !== '',
        hasUnit: unit.trim() !== '',
    };
};

/**
 * 接頭語付き数値を基準値の number へ変換する。
 * allowedPrefixes を指定した場合は候補外の接頭語を拒否し、unit 指定時は末尾単位を除去して読む。
 */
// 目的: 共通ユーティリティのparse Engineering Numberを扱う。機能: 入力値を検証・整形し、画面または計算処理へ渡す。入力: 関数シグネチャの値。出力: 表示値、配列、オブジェクト、数値のいずれか。動作条件: 共通ユーティリティの初期化後に呼び出す。副作用: なし。
export const parseEngineeringNumber = (value, allowedPrefixes = null, unit = '') => {
    if (typeof value === 'number') {
        return Number.isFinite(value) ? value : null;
    }

    if (allowedPrefixes === null && String(unit ?? '') === '') {
        return parseEngineeringNumberDetail(value)?.value ?? null;
    }

    const text = stripUnitSuffix(String(value ?? '').trim(), unit);
    if (!text) return null;
    const match = text.match(/^([+-]?(?:\d+(?:\.\d*)?|\.\d+)(?:[eE][+-]?\d+)?)(Ti|Gi|Mi|Ki|MEG|Meg|meg|[YZEPTGMkKmunpf]?)/u);
    if (!match || match[0] !== text) return null;
    const prefix = normalizePrefixToken(match[2] ?? '');
    const allowed = allowedPrefixes === null ? null : normalizePrefixList(allowedPrefixes);
    if (allowed !== null && !allowed.includes(prefix)) return null;
    const factor = ENGINEERING_VALUE_PREFIX_FACTORS[prefix] ?? 1;

    return Number(match[1]) * factor;
};

/**
 * `mA` や `KiB` のような接頭語付き単位を係数と基準単位へ分解する。
 * 戻り値は `{ factor, baseUnit }` で、副作用はない。
 */
// 目的: 共通ユーティリティのparse Unit Prefix Factorを扱う。機能: 入力値を検証・整形し、画面または計算処理へ渡す。入力: 関数シグネチャの値。出力: 表示値、配列、オブジェクト、数値のいずれか。動作条件: 共通ユーティリティの初期化後に呼び出す。副作用: なし。
export const parseUnitPrefixFactor = (unit) => {
    const normalized = normalizeUnitLabel(unit);
    if (!normalized) return { factor: 1, baseUnit: '' };
    const prefixList = ['Ti', 'Gi', 'Mi', 'Ki', 'meg', 'Y', 'Z', 'E', 'P', 'T', 'G', 'M', 'k', 'K', 'm', 'u', 'n', 'p', 'f'];
    for (const token of prefixList) {
        const prefix = normalizePrefixToken(token);
        if (normalized.startsWith(token) && normalized.length > token.length) {
            return { factor: ENGINEERING_VALUE_PREFIX_FACTORS[prefix] ?? 1, baseUnit: normalized.slice(token.length) };
        }
    }

    return { factor: 1, baseUnit: normalized };
};

/**
 * 有効数字を保ちながら画面表示用の短い数値文字列へ整える。
 * 入力が有限数でない場合は `--` を返し、副作用はない。
 */
// 目的: 共通ユーティリティのformat Display Numberを扱う。機能: 入力値を検証・整形し、画面または計算処理へ渡す。入力: 関数シグネチャの値。出力: 表示値、配列、オブジェクト、数値のいずれか。動作条件: 共通ユーティリティの初期化後に呼び出す。副作用: なし。
export const formatDisplayNumber = (value, digits = 6) => {
    const number = Number(value);
    if (!Number.isFinite(number)) return '--';
    if (number === 0) return '0';

    return Number(number.toPrecision(digits)).toString();
};

/**
 * 基準値を指定単位の表示接頭語へスケーリングして文字列化する。
 * displayPrefixes が空なら単位種別に応じた既定候補を使う。
 */
// 目的: 共通ユーティリティのformat Engineering Valueを扱う。機能: 入力値を検証・整形し、画面または計算処理へ渡す。入力: 関数シグネチャの値。出力: 表示値、配列、オブジェクト、数値のいずれか。動作条件: 共通ユーティリティの初期化後に呼び出す。副作用: なし。
export const formatEngineeringValue = (value, unit = '', displayPrefixes = null) => {
    const numeric = Number(value);
    if (!Number.isFinite(numeric)) return value ?? '';
    if (numeric === 0) return `0${unit}`;

    const prefixes = sortPrefixesByFactor(displayPrefixes?.length ? displayPrefixes : (isByteBitUnit(unit) ? BYTE_BIT_PREFIX_ORDER : DECIMAL_PREFIX_ORDER));
    const abs = Math.abs(numeric);
    const fallbackPrefix = prefixes.at(-1) ?? '';
    for (const prefix of prefixes) {
        const factor = ENGINEERING_VALUE_PREFIX_FACTORS[prefix] ?? 1;
        if (abs >= factor || prefix === fallbackPrefix) {
            const scaled = numeric / factor;
            if (Math.abs(scaled) >= 1 || prefix === fallbackPrefix) {
                return `${Number(scaled.toPrecision(12)).toString()}${prefix}${unit}`;
            }
        }
    }

    return `${numeric}${unit}`;
};

/**
 * 小型フォーマッタで使う数値丸めを行う。
 * 入力が有限数でない場合はハイフンを返し、副作用はない。
 */
// 目的: 共通ユーティリティのtrim Numberを扱う。機能: 入力値を検証・整形し、画面または計算処理へ渡す。入力: 関数シグネチャの値。出力: 表示値、配列、オブジェクト、数値のいずれか。動作条件: 共通ユーティリティの初期化後に呼び出す。副作用: なし。
const trimNumber = (value, digits = 4) => {
    if (!Number.isFinite(value)) return '-';
    return Number(value.toPrecision(digits)).toString();
};

/**
 * 抵抗値をΩ基準の数値からΩ/kΩ/MΩ/mΩ表記へ変換する。
 * 入力が0以下または無効な場合は0Ωを返し、副作用はない。
 */
// 目的: 共通ユーティリティのformat Resistanceを扱う。機能: 入力値を検証・整形し、画面または計算処理へ渡す。入力: 関数シグネチャの値。出力: 表示値、配列、オブジェクト、数値のいずれか。動作条件: 共通ユーティリティの初期化後に呼び出す。副作用: なし。
export const formatResistance = (value) => {
    if (!Number.isFinite(value) || value <= 0) return '0Ω';
    if (value >= 1e6) return `${trimNumber(value / 1e6)}MΩ`;
    if (value >= 1e3) return `${trimNumber(value / 1e3)}kΩ`;
    if (value < 1) return `${trimNumber(value * 1000)}mΩ`;
    return `${trimNumber(value)}Ω`;
};

/**
 * 静電容量をF基準の数値からpF/nF/uF/mF表記へ変換する。
 * 無効値や0以下は0Fとして扱い、副作用はない。
 */
// 目的: 共通ユーティリティのformat Capacitanceを扱う。機能: 入力値を検証・整形し、画面または計算処理へ渡す。入力: 関数シグネチャの値。出力: 表示値、配列、オブジェクト、数値のいずれか。動作条件: 共通ユーティリティの初期化後に呼び出す。副作用: なし。
export const formatCapacitance = (value) => {
    if (!Number.isFinite(value) || value <= 0) return '0F';
    if (value < 1e-9) return `${trimNumber(value * 1e12)}pF`;
    if (value < 1e-6) return `${trimNumber(value * 1e9)}nF`;
    if (value < 1e-3) return `${trimNumber(value * 1e6)}uF`;
    return `${trimNumber(value * 1e3)}mF`;
};

/**
 * 電圧値をV基準の数値からmV/V/kV表記へ変換する。
 * 入力が有限数でない場合はハイフンを返し、副作用はない。
 */
// 目的: 共通ユーティリティのformat Voltageを扱う。機能: 入力値を検証・整形し、画面または計算処理へ渡す。入力: 関数シグネチャの値。出力: 表示値、配列、オブジェクト、数値のいずれか。動作条件: 共通ユーティリティの初期化後に呼び出す。副作用: なし。
export const formatVoltage = (value) => {
    if (!Number.isFinite(value)) return '-';
    const abs = Math.abs(value);
    if (abs > 0 && abs < 1) return `${trimNumber(value * 1000)}mV`;
    if (abs >= 1000) return `${trimNumber(value / 1000)}kV`;
    return `${trimNumber(value)}V`;
};

/**
 * 電流値をA基準の数値からnA/uA/mA/A表記へ変換する。
 * 入力が有限数でない場合はハイフンを返し、副作用はない。
 */
// 目的: 共通ユーティリティのformat Currentを扱う。機能: 入力値を検証・整形し、画面または計算処理へ渡す。入力: 関数シグネチャの値。出力: 表示値、配列、オブジェクト、数値のいずれか。動作条件: 共通ユーティリティの初期化後に呼び出す。副作用: なし。
export const formatCurrent = (value) => {
    if (!Number.isFinite(value)) return '-';
    const abs = Math.abs(value);
    if (abs === 0) return '0A';
    if (abs < 1e-6) return `${trimNumber(value * 1e9)}nA`;
    if (abs < 1e-3) return `${trimNumber(value * 1e6)}uA`;
    if (abs < 1) return `${trimNumber(value * 1000)}mA`;
    return `${trimNumber(value)}A`;
};

/**
 * 電力値をW基準の数値からnW/uW/mW/W表記へ変換する。
 * 入力が有限数でない場合はハイフンを返し、副作用はない。
 */
// 目的: 共通ユーティリティのformat Powerを扱う。機能: 入力値を検証・整形し、画面または計算処理へ渡す。入力: 関数シグネチャの値。出力: 表示値、配列、オブジェクト、数値のいずれか。動作条件: 共通ユーティリティの初期化後に呼び出す。副作用: なし。
export const formatPower = (value) => {
    if (!Number.isFinite(value)) return '-';
    const abs = Math.abs(value);
    if (abs === 0) return '0W';
    if (abs < 1e-6) return `${trimNumber(value * 1e9)}nW`;
    if (abs < 1e-3) return `${trimNumber(value * 1e6)}uW`;
    if (abs < 1) return `${trimNumber(value * 1000)}mW`;
    return `${trimNumber(value)}W`;
};

/**
 * 回路計算用の部品値を接頭語と単位つき入力から基準値へ変換する。
 * partType と異なる単位が付いた場合は旧挙動に合わせて数値部だけを返す。
 */
// 目的: 共通ユーティリティのparse Part Valueを扱う。機能: 入力値を検証・整形し、画面または計算処理へ渡す。入力: 関数シグネチャの値。出力: 表示値、配列、オブジェクト、数値のいずれか。動作条件: 共通ユーティリティの初期化後に呼び出す。副作用: なし。
export const parsePartValue = (raw, partType) => {
    const source = normalizeEngineeringText(raw);
    if (!source) return null;
    if (partType === 'divider') {
        const percent = source.match(/^([+-]?(?:\d+(?:\.\d*)?|\.\d+)(?:e[+-]?\d+)?)%$/iu);
        if (percent) return Number(percent[1]) / 100;
    }

    const parsed = parseEngineeringNumberDetail(source);
    if (parsed) {
        const expectedUnit = { R: 'Ω', C: 'F', V: 'V', A: 'A', W: 'W' }[partType] ?? '';
        if (expectedUnit && parsed.unit && parsed.unit !== expectedUnit) {
            return parsed.rawValue;
        }

        return parsed.value;
    }
    const numeric = Number(source);

    return Number.isFinite(numeric) ? numeric : null;
};

/**
 * SI接頭語で読みやすい計測値表示を作る。
 * 入力値は unit の接頭語を含む現在単位の値として扱い、戻り値は表示文字列のみで副作用はない。
 */
// 目的: 共通ユーティリティのformat Si Valueを扱う。機能: 入力値を検証・整形し、画面または計算処理へ渡す。入力: 関数シグネチャの値。出力: 表示値、配列、オブジェクト、数値のいずれか。動作条件: 共通ユーティリティの初期化後に呼び出す。副作用: なし。
export const formatSiValue = (value, unit = '', digits = 3) => {
    const number = Number(value);
    if (!Number.isFinite(number)) return `-- ${unit ? String(unit) : ''}`.trim();
    const normalizedUnit = String(unit ?? '').trim();
    if (!normalizedUnit) return number.toFixed(digits);
    const unitLower = normalizedUnit.toLowerCase();
    if (/%|℃|°C|\//u.test(normalizedUnit) || unitLower === 'h' || unitLower === 'sec' || unitLower === 's' || unitLower === 'min' || unitLower === 'ms') {
        return `${number.toFixed(digits)} ${normalizedUnit}`;
    }

    const parsedUnit = parseUnitPrefixFactor(normalizedUnit);
    if (!parsedUnit.baseUnit) return number.toFixed(digits);
    const baseValue = number * parsedUnit.factor;
    const abs = Math.abs(baseValue);
    if (abs === 0) return `0 ${parsedUnit.baseUnit}`;

    let exp = Math.max(-5, Math.min(8, Math.floor(Math.log10(abs) / 3)));
    let scaled = baseValue / Math.pow(10, exp * 3);
    while (Math.abs(scaled) >= 1000 && exp < 8) {
        exp += 1;
        scaled /= 1000;
    }
    while (Math.abs(scaled) < 1 && exp > -5) {
        exp -= 1;
        scaled *= 1000;
    }

    const prefix = DECIMAL_PREFIX_ORDER.find((candidate) => SI_PREFIX_FACTORS[candidate] === Math.pow(10, exp * 3)) ?? '';
    return `${scaled.toFixed(digits)} ${prefix}${parsedUnit.baseUnit}`;
};
