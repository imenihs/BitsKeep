import { onBeforeUnmount, watch } from 'vue';
import { useConfirmModal } from './useConfirmModal.js';

// 目的: Vue共通composableのNavigation Confirmを扱う。機能: 入力値を検証・整形し、画面または計算処理へ渡す。入力: 関数シグネチャの値。出力: 処理結果またはなし。動作条件: Vue共通composableの初期化後に呼び出す。副作用: Vue状態、localStorage、DOM、HTTP通信のいずれかを更新する場合がある。
export function useNavigationConfirm(activeRef, confirmMessage) {
    let enabled = false;
    const { ask } = useConfirmModal();

    // 目的: Vue共通composableのhandle Before Unloadを扱う。機能: 入力値を検証・整形し、画面または計算処理へ渡す。入力: 関数シグネチャの値。出力: 処理結果またはなし。動作条件: Vue共通composableの初期化後に呼び出す。副作用: Vue状態、localStorage、DOM、HTTP通信のいずれかを更新する場合がある。
    const handleBeforeUnload = (event) => {
        if (!enabled) return;
        // beforeunload はブラウザネイティブのダイアログが必須。カスタムモーダル不可。
        event.preventDefault();
        event.returnValue = confirmMessage;
    };

    // 目的: Vue共通composableのhandle Document Clickを扱う。機能: 入力値を検証・整形し、画面または計算処理へ渡す。入力: 関数シグネチャの値。出力: 処理結果またはなし。動作条件: Vue共通composableの初期化後に呼び出す。副作用: Vue状態、localStorage、DOM、HTTP通信のいずれかを更新する場合がある。
    const handleDocumentClick = async (event) => {
        if (!enabled) return;

        const target = event.target instanceof Element
            ? event.target.closest('a[href]')
            : null;

        if (!target) return;
        if (target.target === '_blank' || target.hasAttribute('download')) return;

        const href = target.getAttribute('href');
        if (!href || href.startsWith('#') || href.startsWith('javascript:')) return;

        // リンククリックをいったん止めて自前モーダルで確認
        event.preventDefault();
        const confirmed = await ask(confirmMessage);
        if (confirmed) {
            window.removeEventListener('beforeunload', handleBeforeUnload);
            window.location.href = href;
        }
    };

    // 目的: Vue共通composableのhandle Pop Stateを扱う。機能: 入力値を検証・整形し、画面または計算処理へ渡す。入力: 関数シグネチャの値。出力: 処理結果またはなし。動作条件: Vue共通composableの初期化後に呼び出す。副作用: Vue状態、localStorage、DOM、HTTP通信のいずれかを更新する場合がある。
    const handlePopState = async () => {
        if (!enabled) return;
        // ブラウザバックを一旦打ち消し、自前モーダルで確認
        history.pushState(null, '', location.href);
        const confirmed = await ask(confirmMessage);
        if (confirmed) {
            disable();
            history.back();
        }
    };

    // 目的: Vue共通composableのenableを扱う。機能: 入力値を検証・整形し、画面または計算処理へ渡す。入力: 関数シグネチャの値。出力: 処理結果またはなし。動作条件: Vue共通composableの初期化後に呼び出す。副作用: Vue状態、localStorage、DOM、HTTP通信のいずれかを更新する場合がある。
    const enable = () => {
        if (enabled) return;
        enabled = true;
        window.addEventListener('beforeunload', handleBeforeUnload);
        document.addEventListener('click', handleDocumentClick, true);
        window.addEventListener('popstate', handlePopState);
    };

    // 目的: Vue共通composableのdisableを扱う。機能: 入力値を検証・整形し、画面または計算処理へ渡す。入力: 関数シグネチャの値。出力: 処理結果またはなし。動作条件: Vue共通composableの初期化後に呼び出す。副作用: Vue状態、localStorage、DOM、HTTP通信のいずれかを更新する場合がある。
    const disable = () => {
        if (!enabled) return;
        enabled = false;
        window.removeEventListener('beforeunload', handleBeforeUnload);
        document.removeEventListener('click', handleDocumentClick, true);
        window.removeEventListener('popstate', handlePopState);
    };

    watch(activeRef, (active) => {
        if (active) enable();
        else disable();
    }, { immediate: true });

    onBeforeUnmount(() => {
        disable();
    });

    return { disableNavigationConfirm: disable };
}
