import { onMounted, onUnmounted } from 'vue';

/**
 * モーダルのESCキー閉じるハンドラ
 * handlers: Array<{ isOpen: () => boolean, close: () => void | Promise<void> }>
 * 先頭から順にチェックし、最初にisOpen()がtrueのものだけcloseを呼ぶ。
 */
export function useModalEsc(handlers) {
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
