export function useFormatter() {
    // 金額: ¥1,234 形式。合計は整数円、単価は最大2桁
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
