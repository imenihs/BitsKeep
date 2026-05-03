import { ref } from 'vue';

// グローバルシングルトン状態（全ページで1つのモーダルを共有）
const visible = ref(false);
const message = ref('');
let _resolve = null;

/**
 * 自前確認モーダル（window.confirm の代替）
 * ask() を await することで、ユーザーが選択するまで待機できる。
 */
// 目的: Vue共通composableのConfirm Modalを扱う。機能: 入力値を検証・整形し、画面または計算処理へ渡す。入力: 関数シグネチャの値。出力: 処理結果またはなし。動作条件: Vue共通composableの初期化後に呼び出す。副作用: Vue状態、localStorage、DOM、HTTP通信のいずれかを更新する場合がある。
export function useConfirmModal() {
    // 目的: Vue共通composableのaskを扱う。機能: 入力値を検証・整形し、画面または計算処理へ渡す。入力: 関数シグネチャの値。出力: 処理結果またはなし。動作条件: Vue共通composableの初期化後に呼び出す。副作用: Vue状態、localStorage、DOM、HTTP通信のいずれかを更新する場合がある。
    const ask = (msg = '未保存の変更があります。このまま閉じますか？') => {
        message.value = msg;
        visible.value = true;
        return new Promise((res) => { _resolve = res; });
    };

    // 目的: Vue共通composableのconfirmを扱う。機能: 入力値を検証・整形し、画面または計算処理へ渡す。入力: 関数シグネチャの値。出力: 処理結果またはなし。動作条件: Vue共通composableの初期化後に呼び出す。副作用: Vue状態、localStorage、DOM、HTTP通信のいずれかを更新する場合がある。
    const confirm = () => {
        visible.value = false;
        _resolve?.(true);
        _resolve = null;
    };

    // 目的: Vue共通composableのcancelを扱う。機能: 入力値を検証・整形し、画面または計算処理へ渡す。入力: 関数シグネチャの値。出力: 真偽値。動作条件: Vue共通composableの初期化後に呼び出す。副作用: Vue状態、localStorage、DOM、HTTP通信のいずれかを更新する場合がある。
    const cancel = () => {
        visible.value = false;
        _resolve?.(false);
        _resolve = null;
    };

    return { visible, message, ask, confirm, cancel };
}
