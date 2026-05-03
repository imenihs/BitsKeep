import { nextTick, ref, computed, watch } from 'vue';
import {
    BIT_WIDTH_OPTIONS,
    FAVORITES_KEY,
    FUNCTION_GROUPS,
    HISTORY_KEY,
    SUGGESTION_ITEMS,
    UNSAFE_INTEGER_DISPLAY,
    complexPolar,
    evaluateComplexProgram,
    evaluateProgram,
    exactDisplayInt,
    formatBigInt,
    formatEngineering,
    formatNum,
    formatValue,
    isUnsafeIntegerNumber,
    math,
    normalizeComplexString,
    shouldUseComplexEngine,
    storageGet,
    storageSet,
    syntaxTokens,
    tokenClass,
    transformBitwiseOperators,
    tryEvaluateExactIntegerExpression,
    safeEval,
} from './engineering-calc/core.js';

/**
 * 目的: エンジニア電卓ページのVue公開状態と操作関数を構成する。
 * 機能: 入力式の即時計算、履歴/お気に入り、補完、進数表示、複素数表示を束ねる。
 * 入力: Vueテンプレートから参照されるイベントと、sessionStorage/localStorage上の保存済み履歴。
 * 出力: テンプレートへ渡すref/computed/操作関数のオブジェクト。
 * 動作条件: ブラウザ環境で `window`、`navigator.clipboard`、VueのリアクティブAPIが使えること。
 * 副作用: 履歴/お気に入りをWeb Storageへ保存し、コピー操作時にクリップボードへ結果を書き込む。
 */
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

    /**
     * 目的: 現在の式を評価し、結果表示とエラー表示を更新する。
     * 機能: 複素数表記を含む式はmathjs系、それ以外は安全化した独自評価系へ振り分ける。
     * 入力: `pushHistory` は明示実行時に履歴へ積むかを指定する真偽値。
     * 出力: 戻り値なし。`result`、`resultType`、`lastScope`、`error` を更新する。
     * 動作条件: `expr` が空なら計算せず表示状態をクリアする。
     * 副作用: `pushHistory` が真の場合、最大40件の履歴をsessionStorageへ保存する。
     */
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

    /**
     * 目的: エディタのカーソル直前から補完候補の検索語を抽出する。
     * 機能: 現在カーソル位置、補完開始位置、補完終了位置を同期する。
     * 入力: textarea DOMの選択位置と `expr` の現在文字列。
     * 出力: 戻り値なし。補完用refへ検索語と範囲を書き込む。
     * 動作条件: textarea参照が未生成の場合は何もしない。
     * 副作用: DOMは変更せず、Vueの補完状態だけを更新する。
     */
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

    // 目的: 工学電卓のon Editor Inputを扱う。機能: 入力値を検証・整形し、画面または計算処理へ渡す。入力: 関数シグネチャの値。出力: 処理結果またはなし。動作条件: 工学電卓の初期化後に呼び出す。副作用: Vue状態、localStorage、DOM、HTTP通信のいずれかを更新する場合がある。
    const onEditorInput = () => {
        nextTick(updateCompletion);
    };

    // 目的: 工学電卓のon Editor Keydownを扱う。機能: 入力値を検証・整形し、画面または計算処理へ渡す。入力: 関数シグネチャの値。出力: 処理結果またはなし。動作条件: 工学電卓の初期化後に呼び出す。副作用: Vue状態、localStorage、DOM、HTTP通信のいずれかを更新する場合がある。
    const onEditorKeydown = (event) => {
        if (event.key === 'Tab' && completionSuggestions.value.length > 0) {
            event.preventDefault();
            insertCompletion(completionSuggestions.value[0]);
        }
    };

    /**
     * 目的: 選択された補完候補を式エディタへ挿入する。
     * 機能: 補完範囲を候補文字列で置換し、関数候補では括弧内へカーソルを戻す。
     * 入力: `item.insert` に挿入文字列を持つ補完候補。
     * 出力: 戻り値なし。`expr` と補完状態を更新する。
     * 動作条件: `completionStart`/`completionEnd` が現在の式文字列に対応していること。
     * 副作用: nextTick後にtextareaへfocusし、カーソル位置を変更する。
     */
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

    // 目的: 工学電卓のapply Snippetを扱う。機能: 入力値を検証・整形し、画面または計算処理へ渡す。入力: 関数シグネチャの値。出力: 処理結果またはなし。動作条件: 工学電卓の初期化後に呼び出す。副作用: Vue状態、localStorage、DOM、HTTP通信のいずれかを更新する場合がある。
    const applySnippet = (value) => {
        expr.value = value;
        nextTick(updateCompletion);
    };

    watch(expr, () => {
        run(false);
        nextTick(updateCompletion);
    });

    // 目的: 工学電卓のclear Calcを扱う。機能: 入力値を検証・整形し、画面または計算処理へ渡す。入力: 関数シグネチャの値。出力: 処理結果またはなし。動作条件: 工学電卓の初期化後に呼び出す。副作用: Vue状態、localStorage、DOM、HTTP通信のいずれかを更新する場合がある。
    const clearCalc = () => {
        expr.value = '';
        result.value = null;
        error.value = '';
        errorLine.value = null;
        errorColumn.value = null;
        lastScope.value = {};
        completionPrefix.value = '';
    };

    // 目的: 工学電卓のHistoryを扱う。機能: 入力値を検証・整形し、画面または計算処理へ渡す。入力: 関数シグネチャの値。出力: 処理結果またはなし。動作条件: 工学電卓の初期化後に呼び出す。副作用: Vue状態、localStorage、DOM、HTTP通信のいずれかを更新する場合がある。
    const useHistory = (item) => {
        expr.value = item.expr;
        run(false);
    };

    // 目的: 工学電卓のclear Historyを扱う。機能: 入力値を検証・整形し、画面または計算処理へ渡す。入力: 関数シグネチャの値。出力: 処理結果またはなし。動作条件: 工学電卓の初期化後に呼び出す。副作用: Vue状態、localStorage、DOM、HTTP通信のいずれかを更新する場合がある。
    const clearHistory = () => {
        history.value = [];
        storageSet(HISTORY_KEY, []);
    };

    // 目的: 工学電卓のpin Historyを扱う。機能: 入力値を検証・整形し、画面または計算処理へ渡す。入力: 関数シグネチャの値。出力: 処理結果またはなし。動作条件: 工学電卓の初期化後に呼び出す。副作用: Vue状態、localStorage、DOM、HTTP通信のいずれかを更新する場合がある。
    const pinHistory = (item) => {
        if (favorites.value.some((favorite) => favorite.expr === item.expr)) return;
        favorites.value.unshift({ id: Date.now(), expr: item.expr });
        favorites.value = favorites.value.slice(0, 12);
        storageSet(FAVORITES_KEY, favorites.value);
    };

    // 目的: 工学電卓のsave Favoriteを扱う。機能: 入力値を検証・整形し、画面または計算処理へ渡す。入力: 関数シグネチャの値。出力: 処理結果またはなし。動作条件: 工学電卓の初期化後に呼び出す。副作用: Vue状態、localStorage、DOM、HTTP通信のいずれかを更新する場合がある。
    const saveFavorite = () => {
        if (!expr.value.trim()) return;
        if (favorites.value.some((item) => item.expr === expr.value)) return;
        favorites.value.unshift({ id: Date.now(), expr: expr.value });
        favorites.value = favorites.value.slice(0, 12);
        storageSet(FAVORITES_KEY, favorites.value);
    };

    // 目的: 工学電卓のFavoriteを扱う。機能: 入力値を検証・整形し、画面または計算処理へ渡す。入力: 関数シグネチャの値。出力: 処理結果またはなし。動作条件: 工学電卓の初期化後に呼び出す。副作用: Vue状態、localStorage、DOM、HTTP通信のいずれかを更新する場合がある。
    const useFavorite = (item) => {
        expr.value = item.expr;
        run(false);
    };

    // 目的: 工学電卓のcopy Resultを扱う。機能: 入力値を検証・整形し、画面または計算処理へ渡す。入力: 関数シグネチャの値。出力: 処理結果またはなし。動作条件: 工学電卓の初期化後に呼び出す。副作用: Vue状態、localStorage、DOM、HTTP通信のいずれかを更新する場合がある。
    const copyResult = async () => {
        if (result.value === null) return;
        await navigator.clipboard.writeText(formatValue(result.value));
        copied.value = true;
        window.setTimeout(() => { copied.value = false; }, 1200);
    };

    // 目的: 工学電卓のsave Current To Historyを扱う。機能: 入力値を検証・整形し、画面または計算処理へ渡す。入力: 関数シグネチャの値。出力: 処理結果またはなし。動作条件: 工学電卓の初期化後に呼び出す。副作用: Vue状態、localStorage、DOM、HTTP通信のいずれかを更新する場合がある。
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
