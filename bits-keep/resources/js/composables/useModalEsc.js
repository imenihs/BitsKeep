import { onMounted, onUnmounted } from 'vue';

/**
 * モーダルのESCキー閉じるハンドラ
 * handlers: Array<{ isOpen: () => boolean, close: () => void | Promise<void> }>
 * 先頭から順にチェックし、最初にisOpen()がtrueのものだけcloseを呼ぶ。
 */
// 目的: Vue共通composableのModal Escを扱う。機能: 入力値を検証・整形し、画面または計算処理へ渡す。入力: 関数シグネチャの値。出力: 処理結果またはなし。動作条件: Vue共通composableの初期化後に呼び出す。副作用: Vue状態、localStorage、DOM、HTTP通信のいずれかを更新する場合がある。
export function useModalEsc(handlers) {
    // 目的: Vue共通composableのon Key Downを扱う。機能: 入力値を検証・整形し、画面または計算処理へ渡す。入力: 関数シグネチャの値。出力: 処理結果またはなし。動作条件: Vue共通composableの初期化後に呼び出す。副作用: Vue状態、localStorage、DOM、HTTP通信のいずれかを更新する場合がある。
    const onKeyDown = async (e) => {
        if (e.key !== 'Escape') return;
        for (const { isOpen, close } of handlers) {
            if (isOpen()) {
                e.preventDefault();
                await close();
                return;
            }
        }
    };

    onMounted(() => window.addEventListener('keydown', onKeyDown));
    onUnmounted(() => window.removeEventListener('keydown', onKeyDown));
}
