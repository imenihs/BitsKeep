/**
 * 設計解析ツールの責務分割モジュール。
 * 親 setup から渡された reactive/computed と数値ヘルパーを使い、
 * 画面表示に必要な状態、計算結果、レポート生成関数を返す。
 */

/**
 * setupEia96Tool は親から渡された依存を使ってツール責務を初期化する。
 * @param {object} deps 入力状態、数値変換、レポート生成などの依存。
 * @returns {object} Vueテンプレートへ公開する状態、computed、操作関数。
 * @sideEffects reactive状態とlocalStorageを更新する操作関数を含む。
 */
// 目的: 設計解析ツールのsetup Eia96 Toolを扱う。機能: 入力値を検証・整形し、画面または計算処理へ渡す。入力: 関数シグネチャの値。出力: 処理結果またはなし。動作条件: 設計解析ツールの初期化後に呼び出す。副作用: Vue状態、localStorage、DOM、HTTP通信のいずれかを更新する場合がある。
export default function setupEia96Tool({
    computed,
    reactive,
    parseEngineeringNumber,
}) {
const eia96BaseValues = [
    100, 102, 105, 107, 110, 113, 115, 118,
    121, 124, 127, 130, 133, 137, 140, 143,
    147, 150, 154, 158, 162, 165, 169, 174,
    178, 182, 187, 191, 196, 200, 205, 210,
    215, 221, 226, 232, 237, 243, 249, 255,
    261, 267, 274, 280, 287, 294, 301, 309,
    316, 324, 332, 340, 348, 357, 365, 374,
    383, 392, 402, 412, 422, 432, 442, 453,
    464, 475, 487, 499, 511, 523, 536, 549,
    562, 576, 590, 604, 619, 634, 649, 665,
    681, 698, 715, 732, 750, 768, 787, 806,
    825, 845, 866, 887, 909, 931, 953, 976,
].map((value, index) => ({ code: String(index + 1).padStart(2, '0'), value }));
const eia96Multipliers = [
    { letter: 'Z', aliases: [], factor: 0.001, label: 'Z x0.001', range: '0.100 - 0.976 Ω' },
    { letter: 'Y', aliases: ['R'], factor: 0.01, label: 'Y/R x0.01', range: '1.00 - 9.76 Ω' },
    { letter: 'X', aliases: ['S'], factor: 0.1, label: 'X/S x0.1', range: '10.0 - 97.6 Ω' },
    { letter: 'A', aliases: [], factor: 1, label: 'A x1', range: '100 - 976 Ω' },
    { letter: 'B', aliases: ['H'], factor: 10, label: 'B/H x10', range: '1.00 - 9.76 kΩ' },
    { letter: 'C', aliases: [], factor: 100, label: 'C x100', range: '10.0 - 97.6 kΩ' },
    { letter: 'D', aliases: [], factor: 1000, label: 'D x1k', range: '100 - 976 kΩ' },
    { letter: 'E', aliases: [], factor: 10000, label: 'E x10k', range: '1.00 - 9.76 MΩ' },
    { letter: 'F', aliases: [], factor: 100000, label: 'F x100k', range: '10.0 - 97.6 MΩ' },
];
const eia96 = reactive({
    codeQuery: '01C',
    valueQuery: '10k',
    searchQuery: '',
    selectedMultiplier: 'C',
});
// 目的: 設計解析ツールのtrim Significantを扱う。機能: 入力値を検証・整形し、画面または計算処理へ渡す。入力: 関数シグネチャの値。出力: 表示値、配列、オブジェクト、数値のいずれか。動作条件: 設計解析ツールの初期化後に呼び出す。副作用: なし。
const trimSignificant = (value, digits = 3) => {
    const number = Number(value);
    if (!Number.isFinite(number)) return '--';
    const rounded = Number(number.toPrecision(digits));
    return Number.isInteger(rounded) ? String(rounded) : String(rounded);
};
// 目的: 設計解析ツールのformat Resistance Valueを扱う。機能: 入力値を検証・整形し、画面または計算処理へ渡す。入力: 関数シグネチャの値。出力: 表示値、配列、オブジェクト、数値のいずれか。動作条件: 設計解析ツールの初期化後に呼び出す。副作用: なし。
const formatResistanceValue = (ohms) => {
    const value = Number(ohms);
    if (!Number.isFinite(value)) return '--';
    const abs = Math.abs(value);
    if (abs >= 1e9) return `${trimSignificant(value / 1e9)} GΩ`;
    if (abs >= 1e6) return `${trimSignificant(value / 1e6)} MΩ`;
    if (abs >= 1e3) return `${trimSignificant(value / 1e3)} kΩ`;
    return `${trimSignificant(value)} Ω`;
};
// 目的: 設計解析ツールのcanonical Eia96 Multiplierを扱う。機能: 入力値を検証・整形し、画面または計算処理へ渡す。入力: 関数シグネチャの値。出力: 真偽値。動作条件: 設計解析ツールの初期化後に呼び出す。副作用: Vue状態、localStorage、DOM、HTTP通信のいずれかを更新する場合がある。
const canonicalEia96Multiplier = (letter) => {
    const raw = String(letter ?? '').trim().toUpperCase();
    return eia96Multipliers.find((multiplier) => multiplier.letter === raw || multiplier.aliases.includes(raw)) ?? null;
};
const eia96MultiplierBySelected = computed(() => (
    canonicalEia96Multiplier(eia96.selectedMultiplier) ?? eia96Multipliers.find((item) => item.letter === 'C') ?? eia96Multipliers[0]
));
// 目的: 設計解析ツールのeia96 Row Forを扱う。機能: 入力値を検証・整形し、画面または計算処理へ渡す。入力: 関数シグネチャの値。出力: 処理結果またはなし。動作条件: 設計解析ツールの初期化後に呼び出す。副作用: Vue状態、localStorage、DOM、HTTP通信のいずれかを更新する場合がある。
const eia96RowFor = (base, multiplier) => {
    const ohms = base.value * multiplier.factor;
    const aliases = multiplier.aliases.length ? ` / ${multiplier.aliases.join('/')}` : '';
    return {
        baseCode: base.code,
        baseValue: base.value,
        multiplier: multiplier.letter,
        multiplierAliases: multiplier.aliases,
        multiplierLabel: `${multiplier.letter}${aliases}`,
        multiplierFactor: multiplier.factor,
        code: `${base.code}${multiplier.letter}`,
        aliasCodes: multiplier.aliases.map((alias) => `${base.code}${alias}`),
        ohms,
        display: formatResistanceValue(ohms),
    };
};
// 目的: 設計解析ツールのparse Eia96 Codeを扱う。機能: 入力値を検証・整形し、画面または計算処理へ渡す。入力: 関数シグネチャの値。出力: 表示値、配列、オブジェクト、数値のいずれか。動作条件: 設計解析ツールの初期化後に呼び出す。副作用: なし。
const parseEia96Code = (raw) => {
    const normalized = String(raw ?? '').trim().toUpperCase().replace(/\s+/g, '');
    if (!normalized) {
        return { valid: false, normalized, warnings: ['EIA-96コードを入力してください。'] };
    }
    const match = normalized.match(/^(\d{2})([A-Z])$/u);
    if (!match) {
        return { valid: false, normalized, warnings: ['EIA-96は2桁インデックス + 倍率文字の3文字です。例: 01C, 68X, 96A'] };
    }
    const base = eia96BaseValues.find((item) => item.code === match[1]);
    const multiplier = canonicalEia96Multiplier(match[2]);
    if (!base) {
        return { valid: false, normalized, warnings: ['インデックスは01から96の範囲です。'] };
    }
    if (!multiplier) {
        return { valid: false, normalized, warnings: ['倍率文字は Z/Y/X/A/B/C/D/E/F または別表記 R/S/H を使います。'] };
    }
    const row = eia96RowFor(base, multiplier);
    return {
        ...row,
        valid: true,
        normalized,
        enteredMultiplier: match[2],
        warnings: multiplier.letter === match[2] ? [] : [`${match[2]} は ${multiplier.letter} の別表記として扱います。`],
    };
};
const eia96AllRows = computed(() => eia96Multipliers.flatMap((multiplier) => (
    eia96BaseValues.map((base) => eia96RowFor(base, multiplier))
)));
const eia96SelectedRows = computed(() => eia96BaseValues.map((base) => eia96RowFor(base, eia96MultiplierBySelected.value)));
const eia96Lookup = computed(() => parseEia96Code(eia96.codeQuery));
const eia96ReverseMatches = computed(() => {
    const target = parseEngineeringNumber(eia96.valueQuery, Number.NaN);
    if (!Number.isFinite(target) || target <= 0) return [];
    return eia96AllRows.value
        .map((row) => ({
            ...row,
            errorPct: (row.ohms - target) / target * 100,
            absErrorPct: Math.abs((row.ohms - target) / target * 100),
        }))
        .sort((a, b) => a.absErrorPct - b.absErrorPct)
        .slice(0, 8);
});
const eia96FilteredRows = computed(() => {
    const query = String(eia96.searchQuery ?? '').trim();
    if (!query) return [];
    const normalized = query.toUpperCase().replace(/\s+/g, '');
    const valueQuery = parseEngineeringNumber(query, Number.NaN);
    return eia96AllRows.value
        .filter((row) => {
            const codeHit = row.code.includes(normalized) || row.aliasCodes.some((code) => code.includes(normalized));
            const valueHit = Number.isFinite(valueQuery) && Math.abs(row.ohms - valueQuery) / Math.max(valueQuery, 1e-12) <= 0.02;
            const textHit = row.display.toUpperCase().replace(/\s+/g, '').includes(normalized);
            return codeHit || valueHit || textHit;
        })
        .slice(0, 72);
});



    return {
        eia96,
        eia96BaseValues,
        eia96Multipliers,
        eia96Lookup,
        eia96ReverseMatches,
        eia96SelectedRows,
        eia96FilteredRows,
        eia96MultiplierBySelected,
        formatResistanceValue,
    };
}
