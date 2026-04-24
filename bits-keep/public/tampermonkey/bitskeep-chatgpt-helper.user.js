// ==UserScript==
// @name         BitsKeep ChatGPT Helper
// @namespace    https://bits-keep.rwc.0t0.jp/
// @version      0.1.34
// @description  BitsKeep のデータシート解析を ChatGPT Web と連携して自動化します
// @downloadURL  https://bits-keep.rwc.0t0.jp/tampermonkey/bitskeep-chatgpt-helper.user.js
// @updateURL    https://bits-keep.rwc.0t0.jp/tampermonkey/bitskeep-chatgpt-helper.user.js
// @match        https://bits-keep.rwc.0t0.jp/*
// @match        http://bits-keep.rwc.0t0.jp/*
// @match        https://chatgpt.com/*
// @grant        GM_setValue
// @grant        GM_getValue
// @grant        GM_addValueChangeListener
// @grant        GM_xmlhttpRequest
// @grant        unsafeWindow
// @connect      bits-keep.rwc.0t0.jp
// ==/UserScript==

(function () {
    'use strict';

    const JOB_KEY = 'bitskeep_chatgpt_job_v1';
    const STATUS_KEY = 'bitskeep_chatgpt_status_v1';
    const RESULT_KEY = 'bitskeep_chatgpt_result_v1';
    const JOB_CLAIM_KEY = 'bitskeep_chatgpt_job_claim_v1';
    const BITSKEEP_URL_KEY = 'bitskeep_chatgpt_bitskeep_url_v1';
    const WORKER_HEARTBEAT_KEY = 'bitskeep_chatgpt_worker_heartbeat_v1';
    const DEBUG_KEY = 'bitskeep_chatgpt_debug_v1';
    const BITSKEEP_WINDOW_NAME = 'bitskeep-component-create';
    const JOB_CLAIM_TTL_MS = 10 * 60 * 1000;
    const JOB_HEARTBEAT_INTERVAL_MS = 2000;
    const DEBUG_LOG_LIMIT = 240;
    const HELPER_VERSION = '0.1.34';
    const PAGE_ROLE = location.host.includes('bits-keep.rwc.0t0.jp') ? 'bitskeep' : 'chatgpt';
    const DEBUG_PANEL_ENABLED = false;
    let debugEntries = [];
    let debugPanelBody = null;
    let debugPanelStatus = null;
    let debugPanelReady = false;
    let userNoticeRoot = null;
    let userNoticeBody = null;
    let unloadSucceededJobId = null;

    const sleep = (ms) => new Promise((resolve) => setTimeout(resolve, ms));
    const parseValue = (raw) => {
        if (!raw || typeof raw !== 'string') return null;
        try {
            return JSON.parse(raw);
        } catch {
            return null;
        }
    };

    const toDebugText = (value) => {
        if (value === null || value === undefined) return '';
        if (typeof value === 'string') return value;
        try {
            return JSON.stringify(value);
        } catch {
            return String(value);
        }
    };

    const formatDebugEntry = (entry) => {
        const prefix = `${entry.at} [${entry.page}] ${entry.stage}`;
        return entry.message ? `${prefix}: ${entry.message}` : prefix;
    };

    const renderDebugPanel = () => {
        if (!debugPanelReady || !debugPanelBody || !debugPanelStatus) return;
        debugPanelStatus.textContent = `helper v${HELPER_VERSION} | ${PAGE_ROLE} | ${debugEntries.length} logs`;
        debugPanelBody.textContent = debugEntries.map((entry) => {
            const extra = entry.extra ? `\n  ${toDebugText(entry.extra)}` : '';
            return `${formatDebugEntry(entry)}${extra}`;
        }).join('\n\n');
        debugPanelBody.scrollTop = debugPanelBody.scrollHeight;
    };

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

    const persistDebugEntries = () => {
        void GM_setValue(DEBUG_KEY, JSON.stringify({
            updatedAt: new Date().toISOString(),
            entries: debugEntries,
        }));
    };

    const pushDebugLog = (stage, message = '', extra = null) => {
        const entry = {
            at: new Date().toLocaleTimeString('ja-JP', { hour12: false }),
            page: PAGE_ROLE,
            stage,
            message,
            extra,
        };

        debugEntries = [...debugEntries, entry].slice(-DEBUG_LOG_LIMIT);
        const consoleArgs = [`[BitsKeep Helper][${PAGE_ROLE}] ${stage}`];
        if (message) consoleArgs.push(message);
        if (extra) consoleArgs.push(extra);
        console.info(...consoleArgs);
        renderDebugPanel();
        persistDebugEntries();
    };

    const installDebugPanel = () => {
        if (!DEBUG_PANEL_ENABLED) return;
        if (debugPanelReady) return;

        const panel = document.createElement('details');
        panel.open = true;
        panel.style.position = 'fixed';
        panel.style.right = '12px';
        panel.style.bottom = '12px';
        panel.style.width = 'min(420px, calc(100vw - 24px))';
        panel.style.maxHeight = '55vh';
        panel.style.zIndex = '2147483647';
        panel.style.border = '1px solid rgba(148, 163, 184, 0.45)';
        panel.style.borderRadius = '12px';
        panel.style.background = 'rgba(15, 23, 42, 0.95)';
        panel.style.color = '#e2e8f0';
        panel.style.boxShadow = '0 10px 30px rgba(15, 23, 42, 0.35)';
        panel.style.backdropFilter = 'blur(10px)';
        panel.style.font = '12px/1.5 ui-monospace, SFMono-Regular, Menlo, Consolas, monospace';

        const summary = document.createElement('summary');
        summary.style.cursor = 'pointer';
        summary.style.listStyle = 'none';
        summary.style.padding = '10px 12px';
        summary.style.display = 'flex';
        summary.style.alignItems = 'center';
        summary.style.justifyContent = 'space-between';
        summary.style.gap = '8px';

        debugPanelStatus = document.createElement('span');
        debugPanelStatus.textContent = 'helper debug';
        summary.appendChild(debugPanelStatus);

        const actions = document.createElement('div');
        actions.style.display = 'flex';
        actions.style.gap = '6px';

        const copyButton = document.createElement('button');
        copyButton.type = 'button';
        copyButton.textContent = 'Copy';
        copyButton.style.border = '1px solid rgba(148, 163, 184, 0.35)';
        copyButton.style.background = 'rgba(30, 41, 59, 0.9)';
        copyButton.style.color = '#e2e8f0';
        copyButton.style.borderRadius = '8px';
        copyButton.style.padding = '4px 8px';
        copyButton.style.cursor = 'pointer';
        copyButton.addEventListener('click', async (event) => {
            event.preventDefault();
            event.stopPropagation();
            const text = debugEntries.map((entry) => {
                const extra = entry.extra ? `\n${toDebugText(entry.extra)}` : '';
                return `${formatDebugEntry(entry)}${extra}`;
            }).join('\n\n');
            await navigator.clipboard.writeText(text || '[BitsKeep Helper] no logs');
            pushDebugLog('debug.copy', 'デバッグログをクリップボードへコピーしました。');
        });
        actions.appendChild(copyButton);

        const clearButton = document.createElement('button');
        clearButton.type = 'button';
        clearButton.textContent = 'Clear';
        clearButton.style.border = '1px solid rgba(148, 163, 184, 0.35)';
        clearButton.style.background = 'rgba(30, 41, 59, 0.9)';
        clearButton.style.color = '#e2e8f0';
        clearButton.style.borderRadius = '8px';
        clearButton.style.padding = '4px 8px';
        clearButton.style.cursor = 'pointer';
        clearButton.addEventListener('click', (event) => {
            event.preventDefault();
            event.stopPropagation();
            debugEntries = [];
            renderDebugPanel();
            persistDebugEntries();
            pushDebugLog('debug.clear', 'デバッグログをクリアしました。');
        });
        actions.appendChild(clearButton);

        summary.appendChild(actions);
        panel.appendChild(summary);

        debugPanelBody = document.createElement('pre');
        debugPanelBody.style.margin = '0';
        debugPanelBody.style.padding = '0 12px 12px';
        debugPanelBody.style.whiteSpace = 'pre-wrap';
        debugPanelBody.style.overflow = 'auto';
        debugPanelBody.style.maxHeight = 'calc(55vh - 44px)';
        debugPanelBody.textContent = '';
        panel.appendChild(debugPanelBody);

        document.documentElement.appendChild(panel);
        debugPanelReady = true;
        renderDebugPanel();
    };

    const syncDebugEntriesFromStorage = async () => {
        const payload = parseValue(await GM_getValue(DEBUG_KEY, ''));
        if (!payload?.entries || !Array.isArray(payload.entries)) return;
        debugEntries = payload.entries.slice(-DEBUG_LOG_LIMIT);
        renderDebugPanel();
    };

    const setStatus = async (payload, options = {}) => {
        const {
            suppressDebug = false,
            suppressNotice = false,
        } = options;

        if (!suppressDebug) {
            pushDebugLog('status', payload.status, {
                jobId: payload.jobId,
                message: payload.message || '',
            });
        }
        await GM_setValue(STATUS_KEY, JSON.stringify({
            ...payload,
            updatedAt: new Date().toISOString(),
        }));

        if (PAGE_ROLE === 'chatgpt' && !suppressNotice) {
            if (payload.status === 'waiting_response') {
                showUserNotice({
                    tone: 'info',
                    title: 'ChatGPT 解析中',
                    message: '解析が完了したら自動で BitsKeep へ戻ります。戻らなければ下の「BitsKeep に戻る」を押してください。',
                    actionLabel: 'BitsKeep に戻る',
                    onAction: () => { void returnToBitsKeep('processing_notice'); },
                });
            } else if (payload.status === 'login_required') {
                showUserNotice({
                    tone: 'warning',
                    title: 'ChatGPT へのログインが必要です',
                    message: 'ログイン後に自動で戻らなければ、下の「BitsKeep に戻る」を押して BitsKeep 側から再実行してください。',
                    actionLabel: 'BitsKeep に戻る',
                    onAction: () => { void returnToBitsKeep('login_required', { closeCurrentTab: true, navigateCurrentTab: true }); },
                });
            } else if (payload.status === 'failed') {
                showUserNotice({
                    tone: 'danger',
                    title: 'ChatGPT 自動解析に失敗しました',
                    message: `${payload.message || '自動解析に失敗しました。'} このタブは閉じません。下の「BitsKeep に戻る」で元タブへ戻れなければ、手動で BitsKeep タブへ戻って fallback を選んでください。`,
                    actionLabel: 'BitsKeep に戻る',
                    onAction: () => { void returnToBitsKeep('failed_notice'); },
                });
            }
        }
    };

    const setResult = async (payload) => {
        pushDebugLog('result', '解析結果を保存しました。', {
            jobId: payload.jobId,
            hasJsonText: !!payload.jsonText,
            rawLength: payload.rawText?.length || 0,
        });
        await GM_setValue(RESULT_KEY, JSON.stringify({
            ...payload,
            updatedAt: new Date().toISOString(),
        }));

        if (!payload.jsonText) {
            pushDebugLog('result.manual_required', 'JSON 抽出に失敗したため、ChatGPT タブは閉じません。', {
                jobId: payload.jobId,
                rawLength: payload.rawText?.length || 0,
            });
            if (PAGE_ROLE === 'chatgpt') {
                showUserNotice({
                    tone: 'warning',
                    title: 'JSON を自動抽出できませんでした',
                    message: 'このタブは閉じません。返答内容を確認し、必要なら BitsKeep 側の貼り付け fallback を使ってください。戻れない場合は手動で BitsKeep タブへ戻ってください。',
                    actionLabel: 'BitsKeep に戻る',
                    onAction: () => { void returnToBitsKeep('result_manual_required'); },
                });
            }
            return;
        }

        void returnToBitsKeep('result_ready', { closeCurrentTab: true, navigateCurrentTab: false });
        if (PAGE_ROLE === 'chatgpt') {
            showUserNotice({
                tone: 'success',
                title: '解析が完了しました',
                message: '自動で BitsKeep に戻ります。戻らなければ下の「BitsKeep に戻る」を押して、抽出候補を確認してください。',
                actionLabel: 'BitsKeep に戻る',
                onAction: () => { void returnToBitsKeep('result_notice', { closeCurrentTab: true, navigateCurrentTab: true }); },
            });
        }
    };

    const dispatchPageEvent = (eventName, detail) => {
        window.dispatchEvent(new CustomEvent(eventName, { detail }));
    };

    const focusBitsKeepWindow = async (reason) => {
        try {
            if (window.opener && !window.opener.closed) {
                window.opener.focus();
                pushDebugLog('bitskeep.focus', 'opener 経由で BitsKeep タブへ戻します。', { reason });
                return true;
            }
        } catch (error) {
            pushDebugLog('bitskeep.focus.failed', 'opener 経由の focus に失敗しました。', {
                reason,
                error: error instanceof Error ? error.message : String(error),
            });
        }
        pushDebugLog('bitskeep.focus.failed', 'opener が無いため既存 BitsKeep タブへ focus できませんでした。', { reason });
        return false;
    };

    const returnToBitsKeep = async (reason, options = {}) => {
        const {
            closeCurrentTab = false,
            navigateCurrentTab = false,
        } = options;

        const focused = await focusBitsKeepWindow(reason);
        if (focused) return true;

        if (closeCurrentTab) {
            pushDebugLog('bitskeep.return.close', 'ChatGPT タブを閉じて BitsKeep へ戻ることを試みます。', { reason });
            window.close();
            await sleep(250);
            if (window.closed) {
                return true;
            }
        }

        if (navigateCurrentTab) {
            const bitsKeepUrl = String(await GM_getValue(BITSKEEP_URL_KEY, ''));
            if (bitsKeepUrl) {
                pushDebugLog('bitskeep.return.navigate', '現在のタブを BitsKeep へ遷移させます。', {
                    reason,
                    bitsKeepUrl,
                });
                window.location.href = bitsKeepUrl;
                return true;
            }
        }

        pushDebugLog('bitskeep.return.failed', 'BitsKeep へ戻る導線を実行できませんでした。', {
            reason,
            closeCurrentTab,
            navigateCurrentTab,
        });

        if (PAGE_ROLE === 'chatgpt' && reason === 'failed_notice') {
            showUserNotice({
                tone: 'warning',
                title: '手動で BitsKeep に戻ってください',
                message: 'このタブは閉じずに残します。必要ならこのタブの内容を確認したうえで、手動で BitsKeep タブへ戻って fallback を選んでください。',
            });
        }
        return false;
    };

    const clearRemoteState = async (jobId = '') => {
        const [queuedJob, claim, status, result] = await Promise.all([
            GM_getValue(JOB_KEY, ''),
            GM_getValue(JOB_CLAIM_KEY, ''),
            GM_getValue(STATUS_KEY, ''),
            GM_getValue(RESULT_KEY, ''),
        ]);
        const parsedJob = parseValue(queuedJob);
        const parsedClaim = parseValue(claim);
        const parsedStatus = parseValue(status);
        const parsedResult = parseValue(result);

        if (!jobId || parsedJob?.job_id === jobId) {
            await GM_setValue(JOB_KEY, '');
        }
        if (!jobId || parsedClaim?.jobId === jobId) {
            await GM_setValue(JOB_CLAIM_KEY, '');
        }
        if (!jobId || parsedStatus?.jobId === jobId) {
            await GM_setValue(STATUS_KEY, '');
        }
        if (!jobId || parsedResult?.jobId === jobId) {
            await GM_setValue(RESULT_KEY, '');
        }
    };

    const writeWorkerHeartbeat = async (extra = {}) => {
        if (PAGE_ROLE !== 'chatgpt') return;

        await GM_setValue(WORKER_HEARTBEAT_KEY, JSON.stringify({
            path: location.pathname,
            href: location.href,
            updatedAt: new Date().toISOString(),
            ...extra,
        }));
    };

    const queueJobForChatGpt = async (job) => {
        if (!job?.job_id) return false;

        pushDebugLog('queue', 'BitsKeep から ChatGPT ジョブを登録しました。', {
            jobId: job.job_id,
            pdfName: job.target_datasheet?.original_name || '',
            promptLength: job.prompt_text?.length || 0,
        });
        if (PAGE_ROLE === 'bitskeep') {
            await GM_setValue(BITSKEEP_URL_KEY, location.href);
        }
        await GM_setValue(RESULT_KEY, '');
        await GM_setValue(JOB_KEY, JSON.stringify({
            ...job,
            requestedAt: new Date().toISOString(),
        }));
        await setStatus({
            jobId: job.job_id,
            status: 'queued',
            message: 'ChatGPT タブへ解析ジョブを渡しました。',
        });

        return true;
    };

    const waitFor = async (resolver, timeoutMs = 15000, intervalMs = 250) => {
        const startedAt = Date.now();
        while (Date.now() - startedAt < timeoutMs) {
            const value = resolver();
            if (value) return value;
            await sleep(intervalMs);
        }
        return null;
    };

    const waitForAsync = async (resolver, timeoutMs = 15000, intervalMs = 250) => {
        const startedAt = Date.now();
        while (Date.now() - startedAt < timeoutMs) {
            const value = await resolver();
            if (value) return value;
            await sleep(intervalMs);
        }
        return null;
    };

    const waitForDocumentReady = async (timeoutMs = 60000) => {
        pushDebugLog('chatgpt.ready.wait', 'document.readyState complete を待機します。', { readyState: document.readyState });
        const ready = await waitFor(() => document.readyState === 'complete', timeoutMs, 250);
        if (!ready) {
            throw new Error('ChatGPT の画面読み込み完了を待てませんでした。ページ表示が遅延しています。');
        }
        pushDebugLog('chatgpt.ready.done', 'document.readyState complete を確認しました。');
    };

    const createTabId = () => {
        try {
            return crypto.randomUUID();
        } catch {
            return `tab-${Date.now()}-${Math.random().toString(16).slice(2)}`;
        }
    };

    const normalizeText = (value) => (value || '').replace(/\s+/g, ' ').trim().toLowerCase();

    const getNodeLabel = (node) => normalizeText(
        node?.getAttribute?.('aria-label')
        || node?.getAttribute?.('title')
        || node?.textContent
        || ''
    );

    const isElementVisible = (node) => {
        if (!(node instanceof Element)) return false;
        const style = window.getComputedStyle(node);
        if (style.display === 'none' || style.visibility === 'hidden' || style.opacity === '0') {
            return false;
        }
        const rect = node.getBoundingClientRect();
        return rect.width > 0 && rect.height > 0;
    };

    const isElementDisabled = (node) => {
        if (!(node instanceof Element)) return false;
        return node.matches(':disabled') || node.getAttribute('aria-disabled') === 'true';
    };

    const listInteractiveLabels = (selector) => Array.from(document.querySelectorAll(selector))
        .map((node) => getNodeLabel(node))
        .filter(Boolean)
        .slice(0, 20);

    const findComposer = () => {
        return document.querySelector('#prompt-textarea')
            || document.querySelector('textarea[data-id]')
            || document.querySelector('textarea')
            || document.querySelector('div[contenteditable="true"][id="prompt-textarea"]')
            || document.querySelector('div[contenteditable="true"]');
    };

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

    const findAttachButton = () => {
        return document.querySelector('button[aria-label*="Attach"]')
            || document.querySelector('button[aria-label*="アップロード"]')
            || document.querySelector('button[aria-label*="添付"]');
    };

    const findFileInput = () => {
        return document.querySelector('input[type="file"]');
    };

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

    const isLoginRequired = () => {
        if (location.pathname.includes('/auth') || location.pathname.includes('/login')) {
            return true;
        }

        return !!document.querySelector('a[href*="login"], button[data-testid="login-button"]');
    };

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

    const getComposerText = () => {
        const composer = findComposer();
        if (!composer) return '';
        if (composer instanceof HTMLTextAreaElement) {
            return composer.value?.trim() || '';
        }
        return composer.textContent?.trim() || '';
    };

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

    const getTurnText = (node) => {
        if (!node) return '';
        const text = node.innerText?.trim() || node.textContent?.trim() || '';
        return text;
    };

    const chooseLongestNode = (nodes) => {
        if (!Array.isArray(nodes) || nodes.length === 0) return null;
        return nodes.reduce((best, node) => {
            if (!best) return node;
            return getTurnText(node).length >= getTurnText(best).length ? node : best;
        }, null);
    };

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

    const latestAssistantMessage = () => {
        const candidates = getAssistantCandidates();
        if (candidates.length === 0) {
            return null;
        }

        return chooseLongestNode(candidates.slice(-3)) || candidates[candidates.length - 1] || null;
    };

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

    const tryClaimJob = async (jobId, tabId) => {
        const currentClaim = parseValue(await GM_getValue(JOB_CLAIM_KEY, ''));
        const claimFresh = currentClaim?.lastSeenAt
            && (Date.now() - Date.parse(currentClaim.lastSeenAt)) <= JOB_CLAIM_TTL_MS;

        if (claimFresh && currentClaim?.jobId === jobId && currentClaim?.tabId === tabId) {
            pushDebugLog('claim.keep', '既存の claim を維持します。', { jobId, tabId });
            await GM_setValue(JOB_CLAIM_KEY, JSON.stringify({
                jobId,
                tabId,
                lastSeenAt: new Date().toISOString(),
            }));
            return true;
        }

        if (claimFresh && currentClaim?.jobId === jobId && currentClaim?.tabId !== tabId) {
            pushDebugLog('claim.skip', '別タブが claim 中のため処理しません。', {
                jobId,
                tabId,
                claimedBy: currentClaim?.tabId,
            });
            return false;
        }

        if (claimFresh && currentClaim?.jobId && currentClaim.jobId !== jobId) {
            pushDebugLog('claim.replace', '別ジョブの古い claim を上書きします。', {
                jobId,
                tabId,
                previousClaim: currentClaim,
            });
        }

        await GM_setValue(JOB_CLAIM_KEY, JSON.stringify({
            jobId,
            tabId,
            lastSeenAt: new Date().toISOString(),
        }));

        const confirmedClaim = parseValue(await GM_getValue(JOB_CLAIM_KEY, ''));
        pushDebugLog('claim.result', confirmedClaim?.jobId === jobId && confirmedClaim?.tabId === tabId
            ? 'claim を取得しました。'
            : 'claim 取得に失敗しました。', {
            jobId,
            tabId,
            confirmedClaim,
        });
        return confirmedClaim?.jobId === jobId && confirmedClaim?.tabId === tabId;
    };

    const refreshClaim = async (jobId, tabId) => {
        const currentClaim = parseValue(await GM_getValue(JOB_CLAIM_KEY, ''));
        if (currentClaim?.jobId !== jobId || currentClaim?.tabId !== tabId) return false;

        await GM_setValue(JOB_CLAIM_KEY, JSON.stringify({
            jobId,
            tabId,
            lastSeenAt: new Date().toISOString(),
        }));
        return true;
    };

    const releaseClaimIfOwned = async (jobId, tabId) => {
        const currentClaim = parseValue(await GM_getValue(JOB_CLAIM_KEY, ''));
        if (currentClaim?.jobId === jobId && currentClaim?.tabId === tabId) {
            await GM_setValue(JOB_CLAIM_KEY, '');
        }
    };

    const runChatGptJob = async (job, tabId) => {
        if (!job?.job_id || !job?.target_datasheet?.signed_download_url) return;

        let heartbeatTimer = null;
        let lastStatusPayload = null;
        const updateJobStatus = async (status, message) => {
            lastStatusPayload = {
                jobId: job.job_id,
                status,
                message,
            };
            await setStatus(lastStatusPayload);
        };

        try {
            pushDebugLog('job.start', 'ChatGPT ジョブを開始します。', {
                jobId: job.job_id,
                tabId,
                pdfName: job.target_datasheet?.original_name || '',
                promptLength: job.prompt_text?.length || 0,
                path: location.pathname,
            });
            heartbeatTimer = window.setInterval(() => {
                if (!lastStatusPayload) return;
                void refreshClaim(job.job_id, tabId);
                void setStatus(lastStatusPayload, { suppressDebug: true, suppressNotice: true });
            }, JOB_HEARTBEAT_INTERVAL_MS);

            await updateJobStatus('opening_chatgpt', 'ChatGPT タブの表示完了を待っています。');
            await waitForDocumentReady();
            await sleep(1200);

            if (isLoginRequired()) {
                pushDebugLog('job.login_required', 'ChatGPT ログインが必要です。', { path: location.pathname });
                await updateJobStatus('login_required', 'ChatGPT へログインしてください。');
                return;
            }

            await ensureNewChatWorkspace();

            const initialComposer = await waitFor(findComposer, 45000, 300);
            if (!initialComposer) {
                throw new Error('ChatGPT 入力欄を見つけられませんでした。ページ表示が遅いか、DOM変更の可能性があります。');
            }
            pushDebugLog('chatgpt.composer', '初回入力欄を検出しました。', {
                tag: initialComposer.tagName,
                id: initialComposer.id || '',
            });

            await updateJobStatus('opening_chatgpt', 'Temporary Chat を有効化しています。');
            await ensureTemporaryChat();
            await sleep(600);
            const composer = await waitFor(findComposer, 15000, 250);
            if (!composer) {
                throw new Error('Temporary Chat 切替後の入力欄を再取得できませんでした。');
            }
            pushDebugLog('chatgpt.composer', 'Temporary Chat 切替後の入力欄を再取得しました。', {
                tag: composer.tagName,
                id: composer.id || '',
            });

            await updateJobStatus('downloading_pdf', '解析対象PDFを取得しています。');
            const blob = await downloadPdfBlob(job.target_datasheet.signed_download_url);
            const file = new File([blob], job.target_datasheet.original_name || 'datasheet.pdf', { type: 'application/pdf' });

            await updateJobStatus('attaching_pdf', 'PDFを添付しています。');
            await attachPdfToChatGpt(file);

            fillComposer(composer, job.prompt_text || '');
            pushDebugLog('chatgpt.prompt', 'プロンプトを入力欄へ流し込みました。', {
                promptLength: job.prompt_text?.length || 0,
                composerTag: composer.tagName,
            });
            await sleep(400);

            await updateJobStatus('submitting', 'プロンプトとPDFを送信しています。');
            await submitPrompt();
            await waitForSubmissionStart();

            await updateJobStatus('waiting_response', 'ChatGPT の応答を待っています。');
            const result = await waitForAssistantResponse();

            await updateJobStatus('result_ready', '結果を取得しました。');
            unloadSucceededJobId = job.job_id;
            await setResult({
                jobId: job.job_id,
                jsonText: result.jsonText,
                rawText: result.rawText,
            });
        } catch (error) {
            pushDebugLog('job.failed', error instanceof Error ? error.message : '自動解析に失敗しました。', {
                jobId: job.job_id,
                path: location.pathname,
                readyState: document.readyState,
                hasComposer: !!findComposer(),
                hasSendButton: !!findSendButton(),
                hasFileInput: !!findFileInput(),
                hasAttachButton: !!findAttachButton(),
                temporaryActive: isTemporaryChatActive(),
            });
            await updateJobStatus('failed', error instanceof Error ? error.message : '自動解析に失敗しました。');
        } finally {
            if (heartbeatTimer !== null) {
                window.clearInterval(heartbeatTimer);
                heartbeatTimer = null;
            }
            pushDebugLog('job.finally', 'ジョブ後処理へ入ります。', {
                jobId: job.job_id,
                tabId,
            });
            const queuedJob = parseValue(await GM_getValue(JOB_KEY, ''));
            if (queuedJob?.job_id === job.job_id) {
                await GM_setValue(JOB_KEY, '');
            }
            await releaseClaimIfOwned(job.job_id, tabId);
        }
    };

    const initBitsKeepBridge = () => {
        let bridgePollTimer = null;
        let lastStatusUpdatedAt = '';
        let lastResultUpdatedAt = '';

        const dispatchStoredStatus = async () => {
            const detail = parseValue(await GM_getValue(STATUS_KEY, ''));
            if (!detail?.updatedAt || detail.updatedAt === lastStatusUpdatedAt) return;
            lastStatusUpdatedAt = detail.updatedAt;
            pushDebugLog('poll.status', 'poll で status を同期しました。', {
                jobId: detail.jobId,
                status: detail.status,
            });
            dispatchPageEvent('bitskeep-chatgpt-status', detail);
        };

        const dispatchStoredResult = async () => {
            const detail = parseValue(await GM_getValue(RESULT_KEY, ''));
            if (!detail?.updatedAt || detail.updatedAt === lastResultUpdatedAt) return;
            if (!(detail.jsonText || detail.rawText)) return;
            lastResultUpdatedAt = detail.updatedAt;
            pushDebugLog('poll.result', 'poll で result を同期しました。', {
                jobId: detail.jobId,
                hasJsonText: !!detail.jsonText,
            });
            dispatchPageEvent('bitskeep-chatgpt-result', detail);
        };

        unsafeWindow.__bitskeepTampermonkeyHelper = {
            connected: true,
            version: HELPER_VERSION,
            enqueueJob: queueJobForChatGpt,
            getDebugLogs: () => debugEntries.slice(),
            appendDebugLog: (stage, message = '', extra = null) => {
                pushDebugLog(stage, message, extra);
            },
            clearRemoteState,
            getStoredStatus: async () => parseValue(await GM_getValue(STATUS_KEY, '')),
            getStoredResult: async () => parseValue(await GM_getValue(RESULT_KEY, '')),
            getWorkerHeartbeat: async () => parseValue(await GM_getValue(WORKER_HEARTBEAT_KEY, '')),
        };
        pushDebugLog('init.bitskeep', 'BitsKeep bridge を初期化しました。');

        window.addEventListener('bitskeep-chatgpt-start', async (event) => {
            const job = event.detail;
            if (!job?.job_id) return;
            pushDebugLog('event.start', 'bitskeep-chatgpt-start を受信しました。', { jobId: job.job_id });
            await queueJobForChatGpt(job);
        });

        GM_addValueChangeListener(STATUS_KEY, (_, __, nextValue) => {
            const detail = parseValue(nextValue);
            if (detail) {
                lastStatusUpdatedAt = detail.updatedAt || lastStatusUpdatedAt;
                dispatchPageEvent('bitskeep-chatgpt-status', detail);
            }
        });

        GM_addValueChangeListener(RESULT_KEY, (_, __, nextValue) => {
            const detail = parseValue(nextValue);
            if (detail && (detail.jsonText || detail.rawText)) {
                lastResultUpdatedAt = detail.updatedAt || lastResultUpdatedAt;
                dispatchPageEvent('bitskeep-chatgpt-result', detail);
            }
        });

        void dispatchStoredStatus();
        void dispatchStoredResult();

        bridgePollTimer = window.setInterval(() => {
            void dispatchStoredStatus();
            void dispatchStoredResult();
        }, 1000);

        window.addEventListener('beforeunload', () => {
            if (bridgePollTimer !== null) {
                window.clearInterval(bridgePollTimer);
                bridgePollTimer = null;
            }
        });
    };

    const initChatGptBridge = () => {
        let runningJobId = null;
        let pendingRawJob = '';
        const tabId = createTabId();
        let pollTimer = null;
        let heartbeatTimer = null;
        let idleJobLogged = false;
        pushDebugLog('init.chatgpt', 'ChatGPT bridge を初期化しました。', { tabId, path: location.pathname });

        const handleRawJob = async (rawValue) => {
            pendingRawJob = rawValue || pendingRawJob;

            const job = parseValue(pendingRawJob);
            if (!job?.job_id) return;
            idleJobLogged = false;
            pushDebugLog('job.detected', 'pending job を検出しました。', { jobId: job.job_id, runningJobId });
            if (runningJobId === job.job_id) {
                pushDebugLog('job.skip', '同一 job が実行中のため無視します。', { jobId: job.job_id });
                return;
            }
            unloadSucceededJobId = null;
            if (!(await tryClaimJob(job.job_id, tabId))) return;

            runningJobId = job.job_id;
            await runChatGptJob(job, tabId);
            runningJobId = null;
            pendingRawJob = '';
        };

        const pollStoredJob = async () => {
            const storedJob = await GM_getValue(JOB_KEY, '');
            if (!storedJob) {
                if (!idleJobLogged) {
                    pushDebugLog('poll.idle', 'pending job はまだありません。BitsKeep 側のジョブ作成待ちです。', {
                        tabId,
                        path: location.pathname,
                    });
                    idleJobLogged = true;
                }
                return;
            }
            pushDebugLog('poll.job', 'poll で pending job を確認しました。');
            await handleRawJob(storedJob);
        };

        try {
            GM_addValueChangeListener(JOB_KEY, (_, __, nextValue) => {
                void handleRawJob(nextValue);
            });

            void (async () => {
                const value = await GM_getValue(JOB_KEY, '');
                if (!value) {
                    pushDebugLog('job.bootstrap.empty', '初期ロード時点では pending job はありません。', {
                        tabId,
                        path: location.pathname,
                    });
                    idleJobLogged = true;
                    return;
                }

                pushDebugLog('job.bootstrap', '初期ロード時の pending job を確認しました。');
                await handleRawJob(value);
            })();

            window.addEventListener('focus', () => {
                void pollStoredJob();
            });
            document.addEventListener('visibilitychange', () => {
                void pollStoredJob();
            });

            pollTimer = window.setInterval(() => {
                void pollStoredJob();
            }, 1500);
        heartbeatTimer = window.setInterval(() => {
            void writeWorkerHeartbeat({
                tabId,
                runningJobId,
                ready: true,
                acceptingJobs: !runningJobId,
            });
        }, 2000);
        void writeWorkerHeartbeat({
            tabId,
            runningJobId,
            ready: true,
            acceptingJobs: !runningJobId,
        });
            window.setTimeout(() => {
                void pollStoredJob();
            }, 1500);
            window.setTimeout(() => {
                void pollStoredJob();
            }, 5000);
            pushDebugLog('bridge.ready', 'ChatGPT 側 listener と poll を開始しました。', {
                tabId,
                pollIntervalMs: 1500,
                heartbeatIntervalMs: 2000,
            });
            window.addEventListener('beforeunload', () => {
                if (pollTimer !== null) {
                    window.clearInterval(pollTimer);
                    pollTimer = null;
                }
                if (heartbeatTimer !== null) {
                    window.clearInterval(heartbeatTimer);
                    heartbeatTimer = null;
                }
                if (runningJobId && runningJobId !== unloadSucceededJobId) {
                    void setStatus({
                        jobId: runningJobId,
                        status: 'failed',
                        message: 'ChatGPT タブが閉じられたため、自動解析を中断しました。',
                    }, { suppressNotice: true });
                    void releaseClaimIfOwned(runningJobId, tabId);
                }
            void GM_setValue(WORKER_HEARTBEAT_KEY, JSON.stringify({
                tabId,
                runningJobId: null,
                ready: false,
                acceptingJobs: false,
                closedAt: new Date().toISOString(),
                updatedAt: new Date().toISOString(),
            }));
        });
        } catch (error) {
            pushDebugLog('init.chatgpt.failed', error instanceof Error ? error.message : 'ChatGPT bridge 初期化に失敗しました。', {
                tabId,
                path: location.pathname,
            });
            throw error;
        }
    };

    if (location.host.includes('bits-keep.rwc.0t0.jp')) {
        installDebugPanel();
        void syncDebugEntriesFromStorage();
        GM_addValueChangeListener(DEBUG_KEY, (_, __, nextValue) => {
            const payload = parseValue(nextValue);
            if (!payload?.entries || !Array.isArray(payload.entries)) return;
            debugEntries = payload.entries.slice(-DEBUG_LOG_LIMIT);
            renderDebugPanel();
        });
        initBitsKeepBridge();
    } else if (location.host === 'chatgpt.com') {
        installDebugPanel();
        void syncDebugEntriesFromStorage();
        GM_addValueChangeListener(DEBUG_KEY, (_, __, nextValue) => {
            const payload = parseValue(nextValue);
            if (!payload?.entries || !Array.isArray(payload.entries)) return;
            debugEntries = payload.entries.slice(-DEBUG_LOG_LIMIT);
            renderDebugPanel();
        });
        initChatGptBridge();
    }
})();
