// 目的: Vue共通composableのFormatterを扱う。機能: 入力値を検証・整形し、画面または計算処理へ渡す。入力: 関数シグネチャの値。出力: 処理結果またはなし。動作条件: Vue共通composableの初期化後に呼び出す。副作用: Vue状態、localStorage、DOM、HTTP通信のいずれかを更新する場合がある。
export function useFormatter() {
    // 金額: ¥1,234 形式。合計は整数円、単価は最大2桁
    // 目的: Vue共通composableのformat Currencyを扱う。機能: 入力値を検証・整形し、画面または計算処理へ渡す。入力: 関数シグネチャの値。出力: 表示値、配列、オブジェクト、数値のいずれか。動作条件: Vue共通composableの初期化後に呼び出す。副作用: なし。
    const formatCurrency = (value, { decimals = 0 } = {}) => {
        if (value === null || value === undefined) return '¥0';
        const num = parseFloat(value);
        if (isNaN(num)) return '¥0';
        return '¥' + num.toLocaleString('ja-JP', {
            minimumFractionDigits: decimals,
            maximumFractionDigits: decimals,
        });
    };

    // 日付: YYYY/MM/DD または YYYY/MM/DD HH:MM
    // 目的: Vue共通composableのformat Dateを扱う。機能: 入力値を検証・整形し、画面または計算処理へ渡す。入力: 関数シグネチャの値。出力: 表示値、配列、オブジェクト、数値のいずれか。動作条件: Vue共通composableの初期化後に呼び出す。副作用: なし。
    const formatDate = (value, { time = false } = {}) => {
        if (!value) return '';
        const date = new Date(value);
        if (isNaN(date.getTime())) return '';

        const year = date.getFullYear();
        const month = String(date.getMonth() + 1).padStart(2, '0');
        const day = String(date.getDate()).padStart(2, '0');
        let result = `${year}/${month}/${day}`;

        if (time) {
            const hour = String(date.getHours()).padStart(2, '0');
            const minute = String(date.getMinutes()).padStart(2, '0');
            result += ` ${hour}:${minute}`;
        }

        return result;
    };

    // 数値: 桁区切りカンマ
    // 目的: Vue共通composableのformat Numberを扱う。機能: 入力値を検証・整形し、画面または計算処理へ渡す。入力: 関数シグネチャの値。出力: 表示値、配列、オブジェクト、数値のいずれか。動作条件: Vue共通composableの初期化後に呼び出す。副作用: なし。
    const formatNumber = (value) => {
        if (value === null || value === undefined) return '0';
        const num = parseFloat(value);
        if (isNaN(num)) return '0';
        return num.toLocaleString('ja-JP');
    };

    return {
        formatCurrency,
        formatDate,
        formatNumber,
    };
}
