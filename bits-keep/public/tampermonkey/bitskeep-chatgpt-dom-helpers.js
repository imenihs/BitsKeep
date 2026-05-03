// BitsKeep ChatGPT Helper DOM utilities.
// Tampermonkey本体を1000行目安へ近づけるため、ChatGPT画面DOM探索と応答抽出を分離する。
(function (global) {
    'use strict';

    /** 目的: ChatGPT画面DOM操作ヘルパーを依存関数付きで生成する。機能: Tampermonkey連携の対象処理を安全に進める。入力: 関数シグネチャで指定された値。出力: 探索結果、抽出結果、生成値のいずれか。動作条件: BitsKeepまたはChatGPTの対象ページで実行されること。副作用: なし。 */
    global.BitsKeepChatGptDomHelpers = function createBitsKeepChatGptDomHelpers({ sleep, waitFor, pushDebugLog, GM_xmlhttpRequest }) {
        if (typeof sleep !== 'function' || typeof waitFor !== 'function' || typeof pushDebugLog !== 'function' || typeof GM_xmlhttpRequest !== 'function') {
            throw new Error('BitsKeep ChatGPT DOM helpers の依存関数が不足しています。');
        }

    /** 目的: DOMラベル比較用に文字列を正規化する。機能: Tampermonkey連携の対象処理を安全に進める。入力: 関数シグネチャで指定された値。出力: 処理結果またはなし。動作条件: BitsKeepまたはChatGPTの対象ページで実行されること。副作用: なし。 */
    const normalizeText = (value) => (value || '').replace(/\s+/g, ' ').trim().toLowerCase();

    /** 目的: DOM要素の表示ラベルを取得する。機能: Tampermonkey連携の対象処理を安全に進める。入力: 関数シグネチャで指定された値。出力: 探索結果、抽出結果、生成値のいずれか。動作条件: BitsKeepまたはChatGPTの対象ページで実行されること。副作用: なし。 */
    const getNodeLabel = (node) => normalizeText(
        node?.getAttribute?.('aria-label')
        || node?.getAttribute?.('title')
        || node?.textContent
        || ''
    );

    /** 目的: 候補DOMが操作可能な表示状態か判定する。機能: Tampermonkey連携の対象処理を安全に進める。入力: 関数シグネチャで指定された値。出力: 成功可否または完了Promise。動作条件: BitsKeepまたはChatGPTの対象ページで実行されること。副作用: なし。 */
    const isElementVisible = (node) => {
        if (!(node instanceof Element)) return false;
        const style = window.getComputedStyle(node);
        if (style.display === 'none' || style.visibility === 'hidden' || style.opacity === '0') {
            return false;
        }
        const rect = node.getBoundingClientRect();
        return rect.width > 0 && rect.height > 0;
    };

    /** 目的: 候補DOMが無効状態か判定する。機能: Tampermonkey連携の対象処理を安全に進める。入力: 関数シグネチャで指定された値。出力: 成功可否または完了Promise。動作条件: BitsKeepまたはChatGPTの対象ページで実行されること。副作用: なし。 */
    const isElementDisabled = (node) => {
        if (!(node instanceof Element)) return false;
        return node.matches(':disabled') || node.getAttribute('aria-disabled') === 'true';
    };

    /** 目的: デバッグ用に操作候補ラベルを収集する。機能: Tampermonkey連携の対象処理を安全に進める。入力: 関数シグネチャで指定された値。出力: 処理結果またはなし。動作条件: BitsKeepまたはChatGPTの対象ページで実行されること。副作用: なし。 */
    const listInteractiveLabels = (selector) => Array.from(document.querySelectorAll(selector))
        .map((node) => getNodeLabel(node))
        .filter(Boolean)
        .slice(0, 20);

    /** 目的: ChatGPT入力欄DOMを探索する。機能: Tampermonkey連携の対象処理を安全に進める。入力: 関数シグネチャで指定された値。出力: 探索結果、抽出結果、生成値のいずれか。動作条件: BitsKeepまたはChatGPTの対象ページで実行されること。副作用: なし。 */
    const findComposer = () => {
        return document.querySelector('#prompt-textarea')
            || document.querySelector('textarea[data-id]')
            || document.querySelector('textarea')
            || document.querySelector('div[contenteditable="true"][id="prompt-textarea"]')
            || document.querySelector('div[contenteditable="true"]');
    };

    /** 目的: ChatGPT送信ボタンDOMを探索する。機能: Tampermonkey連携の対象処理を安全に進める。入力: 関数シグネチャで指定された値。出力: 探索結果、抽出結果、生成値のいずれか。動作条件: BitsKeepまたはChatGPTの対象ページで実行されること。副作用: なし。 */
    const findSendButton = () => {
        const candidates = Array.from(document.querySelectorAll('button, [role="button"]')).filter((node) => {
            if (!isElementVisible(node)) return false;
            const label = getNodeLabel(node);
            if (!label) return false;
            return label.includes('send')
                || label.includes('send prompt')
                || label.includes('メッセージを送信')
                || label.includes('送信');
        });

        return candidates.find((node) => !isElementDisabled(node))
            || candidates[0]
            || document.querySelector('button[data-testid*="send"]')
            || document.querySelector('button[aria-label*="Send"]')
            || document.querySelector('button[aria-label*="送信"]');
    };

    /** 目的: ChatGPT添付ボタンDOMを探索する。機能: Tampermonkey連携の対象処理を安全に進める。入力: 関数シグネチャで指定された値。出力: 探索結果、抽出結果、生成値のいずれか。動作条件: BitsKeepまたはChatGPTの対象ページで実行されること。副作用: なし。 */
    const findAttachButton = () => {
        return document.querySelector('button[aria-label*="Attach"]')
            || document.querySelector('button[aria-label*="アップロード"]')
            || document.querySelector('button[aria-label*="添付"]');
    };

    /** 目的: ファイル入力DOMを探索する。機能: Tampermonkey連携の対象処理を安全に進める。入力: 関数シグネチャで指定された値。出力: 探索結果、抽出結果、生成値のいずれか。動作条件: BitsKeepまたはChatGPTの対象ページで実行されること。副作用: なし。 */
    const findFileInput = () => {
        return document.querySelector('input[type="file"]');
    };

    /** 目的: 新規チャット開始DOMを探索する。機能: Tampermonkey連携の対象処理を安全に進める。入力: 関数シグネチャで指定された値。出力: 探索結果、抽出結果、生成値のいずれか。動作条件: BitsKeepまたはChatGPTの対象ページで実行されること。副作用: なし。 */
    const findNewChatButton = () => {
        return document.querySelector('a[href="/"]')
            || document.querySelector('button[data-testid*="new-chat"]')
            || Array.from(document.querySelectorAll('button, a')).find((node) => {
                const text = getNodeLabel(node);
                return text === 'new chat'
                    || text === '新しいチャット'
                    || text === '新規チャット';
            })
            || null;
    };

    /** 目的: Temporary Chat切替DOMを探索する。機能: Tampermonkey連携の対象処理を安全に進める。入力: 関数シグネチャで指定された値。出力: 探索結果、抽出結果、生成値のいずれか。動作条件: BitsKeepまたはChatGPTの対象ページで実行されること。副作用: なし。 */
    const findTemporaryButton = () => {
        const buttons = Array.from(document.querySelectorAll('button, [role="button"], [aria-pressed], [role="menuitem"], [role="switch"]'));
        return buttons.find((button) => {
            const text = getNodeLabel(button);
            if (!text) return false;
            return text === 'temporary'
                || text.includes('temporary chat')
                || text.includes('temporary')
                || text === '一時'
                || text.includes('一時チャット');
        }) || null;
    };

    /** 目的: モデルメニューDOMを探索する。機能: Tampermonkey連携の対象処理を安全に進める。入力: 関数シグネチャで指定された値。出力: 探索結果、抽出結果、生成値のいずれか。動作条件: BitsKeepまたはChatGPTの対象ページで実行されること。副作用: なし。 */
    const findModelMenuButton = () => {
        const candidates = Array.from(document.querySelectorAll('header button, header [role="button"], nav button, nav [role="button"], button[aria-haspopup="menu"], [role="button"][aria-haspopup="menu"]'));
        return candidates.find((node) => {
            const text = getNodeLabel(node);
            if (!text) return false;
            return text === 'chatgpt'
                || text.includes('chatgpt')
                || text.includes('model')
                || text.includes('モデル')
                || text.includes('gpt-')
                || text.includes('gpt ');
        }) || null;
    };

    /** 目的: Temporary Chat案内表示を検出する。機能: Tampermonkey連携の対象処理を安全に進める。入力: 関数シグネチャで指定された値。出力: 成功可否または完了Promise。動作条件: BitsKeepまたはChatGPTの対象ページで実行されること。副作用: なし。 */
    const hasTemporaryChatBanner = () => {
        const bannerTexts = Array.from(document.querySelectorAll('main h1, main h2, main [role="heading"], main p, main span'))
            .map((node) => normalizeText(node.textContent))
            .filter(Boolean);

        return bannerTexts.some((text) =>
            text === '一時チャット'
            || text.includes('temporary chat')
            || text.includes('このチャットはチャット履歴に表示されず')
            || text.includes('モデルの学習にも使用されません')
            || text.includes('won’t appear in history')
            || text.includes('will not appear in history')
        );
    };

    /** 目的: Temporary Chatが有効か判定する。機能: Tampermonkey連携の対象処理を安全に進める。入力: 関数シグネチャで指定された値。出力: 成功可否または完了Promise。動作条件: BitsKeepまたはChatGPTの対象ページで実行されること。副作用: なし。 */
    const isTemporaryChatActive = () => {
        const searchParams = new URLSearchParams(location.search);
        if (searchParams.get('temporary-chat') === 'true') {
            return true;
        }

        const button = findTemporaryButton();
        if (button?.getAttribute('aria-pressed') === 'true') {
            return true;
        }

        const candidates = Array.from(document.querySelectorAll('[role="status"], [aria-live]'));
        if (candidates.some((node) => {
            const text = normalizeText(node.textContent);
            if (!text) return false;
            return text.includes('temporary chat')
                || text.includes('temporary chats')
                || text.includes('一時チャット');
        })) {
            return true;
        }

        return hasTemporaryChatBanner();
    };

    /** 目的: 既存会話から新規チャット画面へ移動する。機能: Tampermonkey連携の対象処理を安全に進める。入力: 関数シグネチャで指定された値。出力: 成功可否または完了Promise。動作条件: BitsKeepまたはChatGPTの対象ページで実行されること。副作用: DOM、GM storage、ウィンドウ状態、ネットワーク通信のいずれかを更新する場合がある。 */
    const ensureNewChatWorkspace = async () => {
        if (location.pathname === '/' || location.pathname === '') {
            pushDebugLog('chatgpt.workspace', '既に新規チャット画面です。', { path: location.pathname });
            return;
        }

        const newChatButton = await waitFor(findNewChatButton, 15000, 250);
        if (!newChatButton) {
            throw new Error('新規チャットの開始ボタンを見つけられませんでした。ChatGPT の画面構成が変わった可能性があります。');
        }

        pushDebugLog('chatgpt.workspace', '新規チャットボタンを押します。', { path: location.pathname });
        newChatButton.click();

        const moved = await waitFor(() => location.pathname === '/' || location.pathname === '', 10000, 250);
        if (!moved) {
            await sleep(1200);
        }
    };

    /** 目的: 履歴へ残さないTemporary Chatを有効化する。機能: Tampermonkey連携の対象処理を安全に進める。入力: 関数シグネチャで指定された値。出力: 成功可否または完了Promise。動作条件: BitsKeepまたはChatGPTの対象ページで実行されること。副作用: DOM、GM storage、ウィンドウ状態、ネットワーク通信のいずれかを更新する場合がある。 */
    const ensureTemporaryChat = async () => {
        if (isTemporaryChatActive()) {
            pushDebugLog('chatgpt.temporary', 'Temporary Chat は既に有効です。');
            return;
        }

        let button = await waitFor(findTemporaryButton, 2500, 250);
        if (!button) {
            const modelMenuButton = await waitFor(findModelMenuButton, 5000, 250);
            if (modelMenuButton) {
                pushDebugLog('chatgpt.temporary.menu', 'モデルメニューを開いて Temporary Chat を探します。', {
                    triggerLabel: getNodeLabel(modelMenuButton),
                    headerLabels: listInteractiveLabels('header button, header [role="button"], nav button, nav [role="button"]'),
                });
                modelMenuButton.click();
                await sleep(600);
                button = await waitFor(findTemporaryButton, 4000, 250);
            }
        }
        if (!button) {
            pushDebugLog('chatgpt.temporary.missing', 'Temporary Chat の切替UIを検出できませんでした。', {
                headerLabels: listInteractiveLabels('header button, header [role="button"], nav button, nav [role="button"]'),
                visibleButtons: listInteractiveLabels('button, [role="button"], [role="menuitem"], [role="switch"]'),
            });
            throw new Error('Temporary Chat の切り替えボタンを見つけられませんでした。ChatGPT の画面構成が変わった可能性があります。');
        }

        pushDebugLog('chatgpt.temporary', 'Temporary Chat へ切り替えます。');
        button.click();

        const active = await waitFor(() => isTemporaryChatActive(), 8000, 250);
        if (!active) {
            pushDebugLog('chatgpt.temporary.inactive', 'Temporary Chat の見た目は切り替わったが、有効判定が false のままです。', {
                search: location.search,
                hasTemporaryBanner: hasTemporaryChatBanner(),
                headerLabels: listInteractiveLabels('header button, header [role="button"], nav button, nav [role="button"]'),
            });
            throw new Error('Temporary Chat を有効化できませんでした。通常履歴へ送信しないため処理を中止しました。');
        }
    };

    /** 目的: ChatGPTログイン要求状態を判定する。機能: Tampermonkey連携の対象処理を安全に進める。入力: 関数シグネチャで指定された値。出力: 成功可否または完了Promise。動作条件: BitsKeepまたはChatGPTの対象ページで実行されること。副作用: なし。 */
    const isLoginRequired = () => {
        if (location.pathname.includes('/auth') || location.pathname.includes('/login')) {
            return true;
        }

        return !!document.querySelector('a[href*="login"], button[data-testid="login-button"]');
    };

    /** 目的: ChatGPT入力欄へプロンプトを流し込む。機能: Tampermonkey連携の対象処理を安全に進める。入力: 関数シグネチャで指定された値。出力: 処理結果またはなし。動作条件: BitsKeepまたはChatGPTの対象ページで実行されること。副作用: DOM、GM storage、ウィンドウ状態、ネットワーク通信のいずれかを更新する場合がある。 */
    const fillComposer = (composer, text) => {
        composer.focus();

        if (composer instanceof HTMLTextAreaElement) {
            composer.value = text;
            composer.dispatchEvent(new Event('input', { bubbles: true }));
            return;
        }

        composer.textContent = text;
        composer.dispatchEvent(new InputEvent('input', { bubbles: true, data: text, inputType: 'insertText' }));
    };

    /** 目的: 入力欄の現在テキストを取得する。機能: Tampermonkey連携の対象処理を安全に進める。入力: 関数シグネチャで指定された値。出力: 探索結果、抽出結果、生成値のいずれか。動作条件: BitsKeepまたはChatGPTの対象ページで実行されること。副作用: なし。 */
    const getComposerText = () => {
        const composer = findComposer();
        if (!composer) return '';
        if (composer instanceof HTMLTextAreaElement) {
            return composer.value?.trim() || '';
        }
        return composer.textContent?.trim() || '';
    };

    /** 目的: PDFファイルをChatGPTへ添付する。機能: Tampermonkey連携の対象処理を安全に進める。入力: 関数シグネチャで指定された値。出力: 処理結果またはなし。動作条件: BitsKeepまたはChatGPTの対象ページで実行されること。副作用: DOM、GM storage、ウィンドウ状態、ネットワーク通信のいずれかを更新する場合がある。 */
    const attachPdfToChatGpt = async (file) => {
        let input = findFileInput();
        if (!input) {
            pushDebugLog('chatgpt.attach', '添付ボタンを押して input[type=file] を探します。');
            findAttachButton()?.click();
            input = await waitFor(findFileInput, 15000, 250);
        }
        if (!input) {
            throw new Error('ChatGPT のファイル入力欄を見つけられませんでした。DOM変更の可能性があります。');
        }

        const dt = new DataTransfer();
        dt.items.add(file);
        input.files = dt.files;
        input.dispatchEvent(new Event('change', { bubbles: true }));
        pushDebugLog('chatgpt.attach', 'PDF を input[type=file] へ設定しました。', {
            fileName: file.name,
            size: file.size,
        });
    };

    /** 目的: ChatGPTへプロンプトを送信する。機能: Tampermonkey連携の対象処理を安全に進める。入力: 関数シグネチャで指定された値。出力: 処理結果またはなし。動作条件: BitsKeepまたはChatGPTの対象ページで実行されること。副作用: DOM、GM storage、ウィンドウ状態、ネットワーク通信のいずれかを更新する場合がある。 */
    const submitPrompt = async () => {
        const button = await waitFor(findSendButton, 5000, 200);
        if (button) {
            pushDebugLog('chatgpt.submit', '送信ボタンで送信します。');
            button.click();
            return;
        }

        const composer = findComposer();
        if (composer instanceof HTMLTextAreaElement) {
            pushDebugLog('chatgpt.submit', 'Enter キー送信へフォールバックします。');
            composer.dispatchEvent(new KeyboardEvent('keydown', { key: 'Enter', bubbles: true }));
            return;
        }

        throw new Error('ChatGPT の送信ボタンを見つけられませんでした。');
    };

    /** 目的: 会話ターンDOMを収集する。機能: Tampermonkey連携の対象処理を安全に進める。入力: 関数シグネチャで指定された値。出力: 処理結果またはなし。動作条件: BitsKeepまたはChatGPTの対象ページで実行されること。副作用: なし。 */
    const listConversationTurns = () => {
        const roleTurns = Array.from(document.querySelectorAll('[data-message-author-role]')).filter(isElementVisible);
        if (roleTurns.length > 0) {
            return roleTurns;
        }

        const conversationTurns = Array.from(document.querySelectorAll(
            'main article, main [data-testid^="conversation-turn-"], main [data-testid*="conversation-turn"], main section[data-testid*="conversation"]'
        )).filter(isElementVisible);
        if (conversationTurns.length > 0) {
            return conversationTurns;
        }

        return Array.from(document.querySelectorAll('main article, main [role="article"], main .markdown')).filter(isElementVisible);
    };

    /** 目的: 会話ターン本文を取得する。機能: Tampermonkey連携の対象処理を安全に進める。入力: 関数シグネチャで指定された値。出力: 探索結果、抽出結果、生成値のいずれか。動作条件: BitsKeepまたはChatGPTの対象ページで実行されること。副作用: なし。 */
    const getTurnText = (node) => {
        if (!node) return '';
        const text = node.innerText?.trim() || node.textContent?.trim() || '';
        return text;
    };

    /** 目的: 候補DOMから最も本文が長い要素を選ぶ。機能: Tampermonkey連携の対象処理を安全に進める。入力: 関数シグネチャで指定された値。出力: 探索結果、抽出結果、生成値のいずれか。動作条件: BitsKeepまたはChatGPTの対象ページで実行されること。副作用: なし。 */
    const chooseLongestNode = (nodes) => {
        if (!Array.isArray(nodes) || nodes.length === 0) return null;
        return nodes.reduce((best, node) => {
            if (!best) return node;
            return getTurnText(node).length >= getTurnText(best).length ? node : best;
        }, null);
    };

    /** 目的: assistant応答候補DOMを収集する。機能: Tampermonkey連携の対象処理を安全に進める。入力: 関数シグネチャで指定された値。出力: 探索結果、抽出結果、生成値のいずれか。動作条件: BitsKeepまたはChatGPTの対象ページで実行されること。副作用: なし。 */
    const getAssistantCandidates = () => {
        const roleTurns = Array.from(document.querySelectorAll('[data-message-author-role="assistant"]')).filter(isElementVisible);
        if (roleTurns.length > 0) {
            return roleTurns;
        }

        const conversationTurns = listConversationTurns();
        if (conversationTurns.length === 0) {
            return [];
        }

        const candidates = [];
        for (let index = conversationTurns.length - 1; index >= 0; index -= 1) {
            const turn = conversationTurns[index];
            const label = getNodeLabel(turn);
            const text = getTurnText(turn);
            if (!text) continue;
            if (!label || (!label.includes('you said') && !label.includes('user') && !label.includes('あなた') && !label.includes('you'))) {
                candidates.unshift(turn);
            }
        }

        return candidates;
    };

    /** 目的: 最新assistant応答DOMを取得する。機能: Tampermonkey連携の対象処理を安全に進める。入力: 関数シグネチャで指定された値。出力: 探索結果、抽出結果、生成値のいずれか。動作条件: BitsKeepまたはChatGPTの対象ページで実行されること。副作用: なし。 */
    const latestAssistantMessage = () => {
        const candidates = getAssistantCandidates();
        if (candidates.length === 0) {
            return null;
        }

        return chooseLongestNode(candidates.slice(-3)) || candidates[candidates.length - 1] || null;
    };

    /** 目的: 最新user投稿DOMを取得する。機能: Tampermonkey連携の対象処理を安全に進める。入力: 関数シグネチャで指定された値。出力: 探索結果、抽出結果、生成値のいずれか。動作条件: BitsKeepまたはChatGPTの対象ページで実行されること。副作用: なし。 */
    const latestUserMessage = () => {
        const roleTurns = Array.from(document.querySelectorAll('[data-message-author-role="user"]')).filter(isElementVisible);
        if (roleTurns.length > 0) {
            return roleTurns[roleTurns.length - 1];
        }

        const conversationTurns = listConversationTurns();
        for (let index = conversationTurns.length - 1; index >= 0; index -= 1) {
            const turn = conversationTurns[index];
            const label = getNodeLabel(turn);
            if (label.includes('you said') || label.includes('user') || label.includes('あなた') || label.includes('you')) {
                return turn;
            }
        }

        return null;
    };

    /** 目的: 生成中表示または停止ボタンを探す。機能: Tampermonkey連携の対象処理を安全に進める。入力: 関数シグネチャで指定された値。出力: 探索結果、抽出結果、生成値のいずれか。動作条件: BitsKeepまたはChatGPTの対象ページで実行されること。副作用: なし。 */
    const findPendingResponseIndicator = () => {
        const strictMatch = document.querySelector(
            'button[data-testid*="stop"], button[aria-label*="Stop generating"], button[aria-label*="Stop streaming"], button[aria-label*="回答を停止"], button[aria-label*="生成を停止"]'
        );
        if (strictMatch && isElementVisible(strictMatch)) {
            return strictMatch;
        }

        return Array.from(document.querySelectorAll('button, [role="button"]')).find((node) => {
            if (!isElementVisible(node)) return false;
            const label = getNodeLabel(node);
            if (!label) return false;
            return label === 'stop generating'
                || label === 'stop streaming'
                || label === 'stop'
                || label.includes('回答を停止')
                || label.includes('生成を停止');
        }) || null;
    };

    /** 目的: 送信成立をDOM変化で確認する。機能: Tampermonkey連携の対象処理を安全に進める。入力: 関数シグネチャで指定された値。出力: 成功可否または完了Promise。動作条件: BitsKeepまたはChatGPTの対象ページで実行されること。副作用: DOM、GM storage、ウィンドウ状態、ネットワーク通信のいずれかを更新する場合がある。 */
    const waitForSubmissionStart = async () => {
        const startedAt = Date.now();
        const initialTurnCount = listConversationTurns().length;
        const initialComposerText = getComposerText();

        pushDebugLog('chatgpt.submit.wait', '送信成立の確認を待ちます。', {
            initialTurnCount,
            initialComposerLength: initialComposerText.length,
        });

        while (Date.now() - startedAt < 15000) {
            const turnCount = listConversationTurns().length;
            const userText = getTurnText(latestUserMessage());
            const composerText = getComposerText();
            const sendButton = findSendButton();

            const pendingIndicator = findPendingResponseIndicator();
            if (pendingIndicator) {
                pushDebugLog('chatgpt.submit.confirmed', '応答中UIを検出しました。', {
                    turnCount,
                    composerLength: composerText.length,
                    indicatorLabel: getNodeLabel(pendingIndicator),
                });
                return;
            }

            if (turnCount > initialTurnCount && userText) {
                pushDebugLog('chatgpt.submit.confirmed', '会話ターン増加で送信成立を確認しました。', {
                    turnCount,
                    userLength: userText.length,
                });
                return;
            }

            if (initialComposerText && composerText.length === 0) {
                pushDebugLog('chatgpt.submit.confirmed', '入力欄クリアで送信成立を確認しました。', {
                    turnCount,
                });
                return;
            }

            if (sendButton && isElementDisabled(sendButton) && composerText.length < initialComposerText.length) {
                pushDebugLog('chatgpt.submit.confirmed', '送信ボタン無効化で送信成立を確認しました。', {
                    turnCount,
                    composerLength: composerText.length,
                });
                return;
            }

            await sleep(400);
        }

        pushDebugLog('chatgpt.submit.unconfirmed', '送信成立を確認できませんでした。', {
            turnCount: listConversationTurns().length,
            composerLength: getComposerText().length,
            pendingIndicatorLabel: getNodeLabel(findPendingResponseIndicator()),
            hasSendButton: !!findSendButton(),
        });
        throw new Error('ChatGPT への送信成立を確認できませんでした。送信ボタン押下後も会話が開始されていません。');
    };

    /** 目的: assistant応答からJSON候補を抽出する。機能: Tampermonkey連携の対象処理を安全に進める。入力: 関数シグネチャで指定された値。出力: 探索結果、抽出結果、生成値のいずれか。動作条件: BitsKeepまたはChatGPTの対象ページで実行されること。副作用: なし。 */
    const extractJsonCandidate = (root) => {
        if (!root) return { jsonText: '', rawText: '' };

        const codeBlocks = Array.from(root.querySelectorAll('pre code'))
            .map((node) => node.textContent?.trim() ?? '')
            .filter(Boolean);
        for (const block of codeBlocks) {
            try {
                JSON.parse(block);
                return { jsonText: block, rawText: root.innerText.trim() };
            } catch {
                // continue
            }
        }

        const rawText = root.innerText?.trim() ?? '';
        const fencedMatch = rawText.match(/```(?:json)?\s*([\s\S]*?)```/i);
        if (fencedMatch?.[1]) {
            const candidate = fencedMatch[1].trim();
            try {
                JSON.parse(candidate);
                return { jsonText: candidate, rawText };
            } catch {
                // continue
            }
        }

        const firstBrace = rawText.indexOf('{');
        const lastBrace = rawText.lastIndexOf('}');
        if (firstBrace >= 0 && lastBrace > firstBrace) {
            const candidate = rawText.slice(firstBrace, lastBrace + 1).trim();
            try {
                JSON.parse(candidate);
                return { jsonText: candidate, rawText };
            } catch {
                // continue
            }
        }

        return { jsonText: '', rawText };
    };

    /** 目的: ChatGPT応答完了を待って解析結果を返す。機能: Tampermonkey連携の対象処理を安全に進める。入力: 関数シグネチャで指定された値。出力: 成功可否または完了Promise。動作条件: BitsKeepまたはChatGPTの対象ページで実行されること。副作用: DOM、GM storage、ウィンドウ状態、ネットワーク通信のいずれかを更新する場合がある。 */
    const waitForAssistantResponse = async () => {
        pushDebugLog('chatgpt.response.wait', 'ChatGPT 応答待機を開始します。');
        let lastText = '';
        let stableSince = Date.now();
        const startedAt = Date.now();
        let lastDebugAt = 0;
        let lastGrowthAt = Date.now();

        while (Date.now() - startedAt < 180000) {
            const message = latestAssistantMessage();
            if (message) {
                const currentText = getTurnText(message);
                if (currentText && currentText !== lastText) {
                    lastText = currentText;
                    stableSince = Date.now();
                    lastGrowthAt = Date.now();
                }

                const stopButton = findPendingResponseIndicator();
                if (currentText && !stopButton && Date.now() - stableSince > 4000) {
                    pushDebugLog('chatgpt.response.done', 'ChatGPT 応答を検出しました。', {
                        responseLength: currentText.length,
                    });
                    return extractJsonCandidate(message);
                }

                const stalledMs = Date.now() - lastGrowthAt;
                if (currentText && stopButton && stalledMs > 90000 && currentText.length >= 300) {
                    pushDebugLog('chatgpt.response.stalled', '停止UIが残留していますが、応答本文が長時間増えていないため完了扱いにします。', {
                        responseLength: currentText.length,
                        stalledForMs: stalledMs,
                        pendingIndicatorLabel: getNodeLabel(stopButton),
                    });
                    return extractJsonCandidate(message);
                }
            }

            if (Date.now() - lastDebugAt > 15000) {
                lastDebugAt = Date.now();
                pushDebugLog('chatgpt.response.pending', '応答待機中です。', {
                    assistantLength: lastText.length,
                    stableForMs: Date.now() - stableSince,
                    turnCount: listConversationTurns().length,
                    pendingIndicatorLabel: getNodeLabel(findPendingResponseIndicator()),
                    latestUserLength: getTurnText(latestUserMessage()).length,
                    assistantCandidateLengths: getAssistantCandidates().slice(-4).map((node) => getTurnText(node).length),
                });
            }

            await sleep(1500);
        }

        throw new Error('ChatGPT の応答待機がタイムアウトしました。');
    };

    /** 目的: 署名付きURLからPDF Blobを取得する。機能: Tampermonkey連携の対象処理を安全に進める。入力: 関数シグネチャで指定された値。出力: 探索結果、抽出結果、生成値のいずれか。動作条件: BitsKeepまたはChatGPTの対象ページで実行されること。副作用: DOM、GM storage、ウィンドウ状態、ネットワーク通信のいずれかを更新する場合がある。 */
    const downloadPdfBlob = (url) => new Promise((resolve, reject) => {
        pushDebugLog('chatgpt.download', '署名付きURLから PDF を取得します。');
        GM_xmlhttpRequest({
            method: 'GET',
            url,
            responseType: 'blob',
            onload: (response) => {
                if (response.status >= 200 && response.status < 300 && response.response) {
                    pushDebugLog('chatgpt.download', 'PDF 取得に成功しました。', { status: response.status });
                    resolve(response.response);
                    return;
                }
                reject(new Error(`PDF取得に失敗しました (${response.status})`));
            },
            onerror: () => reject(new Error('PDF取得中に通信エラーが発生しました。')),
        });
    });


        return {
            normalizeText,
            getNodeLabel,
            isElementVisible,
            isElementDisabled,
            listInteractiveLabels,
            findComposer,
            findSendButton,
            findAttachButton,
            findFileInput,
            findNewChatButton,
            findTemporaryButton,
            findModelMenuButton,
            hasTemporaryChatBanner,
            isTemporaryChatActive,
            ensureNewChatWorkspace,
            ensureTemporaryChat,
            isLoginRequired,
            fillComposer,
            getComposerText,
            attachPdfToChatGpt,
            submitPrompt,
            listConversationTurns,
            getTurnText,
            chooseLongestNode,
            getAssistantCandidates,
            latestAssistantMessage,
            latestUserMessage,
            findPendingResponseIndicator,
            waitForSubmissionStart,
            extractJsonCandidate,
            waitForAssistantResponse,
            downloadPdfBlob,
        };
    };
})(typeof unsafeWindow !== 'undefined' ? unsafeWindow : window);
