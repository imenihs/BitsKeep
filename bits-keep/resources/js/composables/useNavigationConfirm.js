import { onBeforeUnmount, watch } from 'vue';
import { useConfirmModal } from './useConfirmModal.js';

export function useNavigationConfirm(activeRef, confirmMessage) {
    let enabled = false;
    const { ask } = useConfirmModal();

    const handleBeforeUnload = (event) => {
        if (!enabled) return;
        // beforeunload はブラウザネイティブのダイアログが必須。カスタムモーダル不可。
        event.preventDefault();
        event.returnValue = confirmMessage;
    };

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

    const enable = () => {
        if (enabled) return;
        enabled = true;
        window.addEventListener('beforeunload', handleBeforeUnload);
        document.addEventListener('click', handleDocumentClick, true);
        window.addEventListener('popstate', handlePopState);
    };

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
