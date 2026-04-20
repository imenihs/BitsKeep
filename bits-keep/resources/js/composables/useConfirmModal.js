import { ref } from 'vue';

// グローバルシングルトン状態（全ページで1つのモーダルを共有）
const visible = ref(false);
const message = ref('');
let _resolve = null;

/**
 * 自前確認モーダル（window.confirm の代替）
 * ask() を await することで、ユーザーが選択するまで待機できる。
 */
export function useConfirmModal() {
    const ask = (msg = '未保存の変更があります。このまま閉じますか？') => {
        message.value = msg;
        visible.value = true;
        return new Promise((res) => { _resolve = res; });
    };

    const confirm = () => {
        visible.value = false;
        _resolve?.(true);
        _resolve = null;
    };

    const cancel = () => {
        visible.value = false;
        _resolve?.(false);
        _resolve = null;
    };

    return { visible, message, ask, confirm, cancel };
}
