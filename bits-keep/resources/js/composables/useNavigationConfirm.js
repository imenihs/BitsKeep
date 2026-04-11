import { onBeforeUnmount, watch } from 'vue';

export function useNavigationConfirm(activeRef, message) {
    let enabled = false;

    const handleBeforeUnload = (event) => {
        if (!enabled) return;
        event.preventDefault();
        event.returnValue = message;
    };

    const handleDocumentClick = (event) => {
        if (!enabled) return;

        const target = event.target instanceof Element
            ? event.target.closest('a[href]')
            : null;

        if (!target) return;
        if (target.target === '_blank' || target.hasAttribute('download')) return;

        const href = target.getAttribute('href');
        if (!href || href.startsWith('#') || href.startsWith('javascript:')) return;

        if (!window.confirm(message)) {
            event.preventDefault();
        }
    };

    const handlePopState = () => {
        if (!enabled) return;
        if (!window.confirm(message)) {
            history.pushState(null, '', location.href);
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
