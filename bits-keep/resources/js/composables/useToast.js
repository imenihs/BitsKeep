import { ref } from 'vue';

const toasts = ref([]);
let _id = 0;

// 目的: Vue共通composableのToastを扱う。機能: 入力値を検証・整形し、画面または計算処理へ渡す。入力: 関数シグネチャの値。出力: 処理結果またはなし。動作条件: Vue共通composableの初期化後に呼び出す。副作用: Vue状態、localStorage、DOM、HTTP通信のいずれかを更新する場合がある。
export function useToast() {
    // 目的: Vue共通composableのshowを扱う。機能: 入力値を検証・整形し、画面または計算処理へ渡す。入力: 関数シグネチャの値。出力: 処理結果またはなし。動作条件: Vue共通composableの初期化後に呼び出す。副作用: Vue状態、localStorage、DOM、HTTP通信のいずれかを更新する場合がある。
    const show = (msg, type = 'success', duration = 2800) => {
        const id = ++_id;
        toasts.value.push({ id, msg, type });
        setTimeout(() => {
            toasts.value = toasts.value.filter(t => t.id !== id);
        }, duration);
    };
    return {
        toasts,
        toastSuccess: (msg) => show(msg, 'success'),
        toastError:   (msg) => show(msg, 'error'),
    };
}
