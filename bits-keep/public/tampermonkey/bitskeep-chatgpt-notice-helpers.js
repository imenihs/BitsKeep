// BitsKeep ChatGPT Helper notice utilities.
// ユーザー通知DOMをUserscript本体から分離し、配布スクリプトの責務と行数を抑える。
(function (global) {
    'use strict';

    /** 目的: Userscriptの通知UIヘルパーを生成する。機能: Tampermonkey連携の対象処理を安全に進める。入力: 関数シグネチャで指定された値。出力: 探索結果、抽出結果、生成値のいずれか。動作条件: BitsKeepまたはChatGPTの対象ページで実行されること。副作用: なし。 */
    global.BitsKeepChatGptNoticeHelpers = function createBitsKeepChatGptNoticeHelpers() {
        let userNoticeRoot = null;
        let userNoticeBody = null;

    /** 目的: 通知表示用DOMルートを用意する。機能: Tampermonkey連携の対象処理を安全に進める。入力: 関数シグネチャで指定された値。出力: 成功可否または完了Promise。動作条件: BitsKeepまたはChatGPTの対象ページで実行されること。副作用: DOM、GM storage、ウィンドウ状態、ネットワーク通信のいずれかを更新する場合がある。 */
    const ensureUserNoticeRoot = () => {
        if (userNoticeRoot) return;

        userNoticeRoot = document.createElement('div');
        userNoticeRoot.style.position = 'fixed';
        userNoticeRoot.style.left = '50%';
        userNoticeRoot.style.top = '16px';
        userNoticeRoot.style.transform = 'translateX(-50%)';
        userNoticeRoot.style.zIndex = '2147483646';
        userNoticeRoot.style.width = 'min(560px, calc(100vw - 24px))';
        userNoticeRoot.style.pointerEvents = 'none';

        userNoticeBody = document.createElement('div');
        userNoticeBody.style.display = 'none';
        userNoticeBody.style.pointerEvents = 'auto';
        userNoticeRoot.appendChild(userNoticeBody);

        document.documentElement.appendChild(userNoticeRoot);
    };

    /** 目的: ユーザーへ状態通知カードを表示する。機能: Tampermonkey連携の対象処理を安全に進める。入力: 通知トーン、タイトル、本文、任意アクション。出力: 処理結果またはなし。動作条件: BitsKeepまたはChatGPTの対象ページで実行されること。副作用: DOM、GM storage、ウィンドウ状態、ネットワーク通信のいずれかを更新する場合がある。 */
    const showUserNotice = ({ tone = 'info', title, message, actionLabel = '', onAction = null } = {}) => {
        ensureUserNoticeRoot();

        const toneStyles = {
            info: {
                border: 'rgba(59, 130, 246, 0.55)',
                bg: 'rgba(15, 23, 42, 0.96)',
                text: '#dbeafe',
                button: '#2563eb',
            },
            success: {
                border: 'rgba(16, 185, 129, 0.55)',
                bg: 'rgba(6, 78, 59, 0.96)',
                text: '#d1fae5',
                button: '#059669',
            },
            warning: {
                border: 'rgba(245, 158, 11, 0.55)',
                bg: 'rgba(120, 53, 15, 0.96)',
                text: '#fef3c7',
                button: '#d97706',
            },
            danger: {
                border: 'rgba(239, 68, 68, 0.55)',
                bg: 'rgba(127, 29, 29, 0.96)',
                text: '#fee2e2',
                button: '#dc2626',
            },
        };
        const style = toneStyles[tone] || toneStyles.info;

        userNoticeBody.innerHTML = '';
        userNoticeBody.style.display = 'block';
        userNoticeBody.style.border = `1px solid ${style.border}`;
        userNoticeBody.style.borderRadius = '14px';
        userNoticeBody.style.background = style.bg;
        userNoticeBody.style.color = style.text;
        userNoticeBody.style.boxShadow = '0 16px 40px rgba(15, 23, 42, 0.35)';
        userNoticeBody.style.padding = '14px 16px';
        userNoticeBody.style.backdropFilter = 'blur(10px)';
        userNoticeBody.style.font = '13px/1.5 "Helvetica Neue", "Hiragino Sans", "Yu Gothic", sans-serif';

        const titleEl = document.createElement('div');
        titleEl.style.fontWeight = '700';
        titleEl.style.fontSize = '14px';
        titleEl.textContent = title || '';
        userNoticeBody.appendChild(titleEl);

        const messageEl = document.createElement('div');
        messageEl.style.marginTop = '4px';
        messageEl.style.opacity = '0.92';
        messageEl.textContent = message || '';
        userNoticeBody.appendChild(messageEl);

        const actionRow = document.createElement('div');
        actionRow.style.display = 'flex';
        actionRow.style.gap = '8px';
        actionRow.style.marginTop = '10px';
        actionRow.style.justifyContent = 'flex-end';

        if (actionLabel && typeof onAction === 'function') {
            const actionButton = document.createElement('button');
            actionButton.type = 'button';
            actionButton.textContent = actionLabel;
            actionButton.style.border = 'none';
            actionButton.style.borderRadius = '10px';
            actionButton.style.padding = '8px 12px';
            actionButton.style.background = style.button;
            actionButton.style.color = '#fff';
            actionButton.style.cursor = 'pointer';
            actionButton.addEventListener('click', onAction);
            actionRow.appendChild(actionButton);
        }

        const closeButton = document.createElement('button');
        closeButton.type = 'button';
        closeButton.textContent = '閉じる';
        closeButton.style.border = `1px solid ${style.border}`;
        closeButton.style.borderRadius = '10px';
        closeButton.style.padding = '8px 12px';
        closeButton.style.background = 'transparent';
        closeButton.style.color = style.text;
        closeButton.style.cursor = 'pointer';
        closeButton.addEventListener('click', () => {
            userNoticeBody.style.display = 'none';
        });
        actionRow.appendChild(closeButton);

        userNoticeBody.appendChild(actionRow);
    };


        return {
            showUserNotice,
        };
    };
})(typeof unsafeWindow !== 'undefined' ? unsafeWindow : window);
