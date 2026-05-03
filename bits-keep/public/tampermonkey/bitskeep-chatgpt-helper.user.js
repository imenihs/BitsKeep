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
// @require      https://bits-keep.rwc.0t0.jp/tampermonkey/bitskeep-chatgpt-notice-helpers.js
// @require      https://bits-keep.rwc.0t0.jp/tampermonkey/bitskeep-chatgpt-dom-helpers.js
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
    let unloadSucceededJobId = null;

    /** 目的: 非同期ポーリング間隔を制御する。機能: Tampermonkey連携の対象処理を安全に進める。入力: 待機ミリ秒。出力: 処理結果またはなし。動作条件: BitsKeepまたはChatGPTの対象ページで実行されること。副作用: なし。 */
    const sleep = (ms) => new Promise((resolve) => setTimeout(resolve, ms));
    /** 目的: GM storage値を安全にJSON化する。機能: Tampermonkey連携の対象処理を安全に進める。入力: 関数シグネチャで指定された値。出力: 処理結果またはなし。動作条件: BitsKeepまたはChatGPTの対象ページで実行されること。副作用: なし。 */
    const parseValue = (raw) => {
        if (!raw || typeof raw !== 'string') return null;
        try {
            return JSON.parse(raw);
        } catch {
            return null;
        }
    };

    /** 目的: デバッグ付帯情報を文字列化する。機能: Tampermonkey連携の対象処理を安全に進める。入力: 関数シグネチャで指定された値。出力: 処理結果またはなし。動作条件: BitsKeepまたはChatGPTの対象ページで実行されること。副作用: なし。 */
    const toDebugText = (value) => {
        if (value === null || value === undefined) return '';
        if (typeof value === 'string') return value;
        try {
            return JSON.stringify(value);
        } catch {
            return String(value);
        }
    };

    /** 目的: デバッグログを1行表示へ整形する。機能: Tampermonkey連携の対象処理を安全に進める。入力: 関数シグネチャで指定された値。出力: 処理結果またはなし。動作条件: BitsKeepまたはChatGPTの対象ページで実行されること。副作用: なし。 */
    const formatDebugEntry = (entry) => {
        const prefix = `${entry.at} [${entry.page}] ${entry.stage}`;
        return entry.message ? `${prefix}: ${entry.message}` : prefix;
    };

    /** 目的: デバッグパネルを最新ログで再描画する。機能: Tampermonkey連携の対象処理を安全に進める。入力: 関数シグネチャで指定された値。出力: 処理結果またはなし。動作条件: BitsKeepまたはChatGPTの対象ページで実行されること。副作用: なし。 */
    const renderDebugPanel = () => {
        if (!debugPanelReady || !debugPanelBody || !debugPanelStatus) return;
        debugPanelStatus.textContent = `helper v${HELPER_VERSION} | ${PAGE_ROLE} | ${debugEntries.length} logs`;
        debugPanelBody.textContent = debugEntries.map((entry) => {
            const extra = entry.extra ? `\n  ${toDebugText(entry.extra)}` : '';
            return `${formatDebugEntry(entry)}${extra}`;
        }).join('\n\n');
        debugPanelBody.scrollTop = debugPanelBody.scrollHeight;
    };

    const { showUserNotice } = window.BitsKeepChatGptNoticeHelpers?.() ?? {};
    if (!showUserNotice) {
        throw new Error('BitsKeep ChatGPT notice helpers を読み込めませんでした。');
    }

    /** 目的: デバッグログをGM storageへ保存する。機能: Tampermonkey連携の対象処理を安全に進める。入力: 関数シグネチャで指定された値。出力: 処理結果またはなし。動作条件: BitsKeepまたはChatGPTの対象ページで実行されること。副作用: DOM、GM storage、ウィンドウ状態、ネットワーク通信のいずれかを更新する場合がある。 */
    const persistDebugEntries = () => {
        void GM_setValue(DEBUG_KEY, JSON.stringify({
            updatedAt: new Date().toISOString(),
            entries: debugEntries,
        }));
    };

    /** 目的: 処理段階をコンソールとデバッグパネルへ記録する。機能: Tampermonkey連携の対象処理を安全に進める。入力: 関数シグネチャで指定された値。出力: 処理結果またはなし。動作条件: BitsKeepまたはChatGPTの対象ページで実行されること。副作用: DOM、GM storage、ウィンドウ状態、ネットワーク通信のいずれかを更新する場合がある。 */
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

    /** 目的: デバッグパネルDOMを初期化する。機能: Tampermonkey連携の対象処理を安全に進める。入力: 関数シグネチャで指定された値。出力: 処理結果またはなし。動作条件: BitsKeepまたはChatGPTの対象ページで実行されること。副作用: DOM、GM storage、ウィンドウ状態、ネットワーク通信のいずれかを更新する場合がある。 */
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

    /** 目的: GM storage上のデバッグログを画面へ同期する。機能: Tampermonkey連携の対象処理を安全に進める。入力: 関数シグネチャで指定された値。出力: 処理結果またはなし。動作条件: BitsKeepまたはChatGPTの対象ページで実行されること。副作用: DOM、GM storage、ウィンドウ状態、ネットワーク通信のいずれかを更新する場合がある。 */
    const syncDebugEntriesFromStorage = async () => {
        const payload = parseValue(await GM_getValue(DEBUG_KEY, ''));
        if (!payload?.entries || !Array.isArray(payload.entries)) return;
        debugEntries = payload.entries.slice(-DEBUG_LOG_LIMIT);
        renderDebugPanel();
    };

    /** 目的: BitsKeepとChatGPT間のジョブ状態を保存して通知する。機能: Tampermonkey連携の対象処理を安全に進める。入力: 関数シグネチャで指定された値。出力: 処理結果またはなし。動作条件: BitsKeepまたはChatGPTの対象ページで実行されること。副作用: DOM、GM storage、ウィンドウ状態、ネットワーク通信のいずれかを更新する場合がある。 */
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

    /** 目的: ChatGPT解析結果をGM storageへ保存する。機能: Tampermonkey連携の対象処理を安全に進める。入力: 関数シグネチャで指定された値。出力: 処理結果またはなし。動作条件: BitsKeepまたはChatGPTの対象ページで実行されること。副作用: DOM、GM storage、ウィンドウ状態、ネットワーク通信のいずれかを更新する場合がある。 */
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

    /** 目的: BitsKeep画面へCustomEventで状態を通知する。機能: Tampermonkey連携の対象処理を安全に進める。入力: 関数シグネチャで指定された値。出力: 処理結果またはなし。動作条件: BitsKeepまたはChatGPTの対象ページで実行されること。副作用: DOM、GM storage、ウィンドウ状態、ネットワーク通信のいずれかを更新する場合がある。 */
    const dispatchPageEvent = (eventName, detail) => {
        window.dispatchEvent(new CustomEvent(eventName, { detail }));
    };

    /** 目的: BitsKeep画面へフォーカスを戻す。機能: Tampermonkey連携の対象処理を安全に進める。入力: 関数シグネチャで指定された値。出力: 処理結果またはなし。動作条件: BitsKeepまたはChatGPTの対象ページで実行されること。副作用: DOM、GM storage、ウィンドウ状態、ネットワーク通信のいずれかを更新する場合がある。 */
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

    /** 目的: 処理完了後にBitsKeep画面へ状態付きで戻る。機能: Tampermonkey連携の対象処理を安全に進める。入力: 関数シグネチャで指定された値。出力: 処理結果またはなし。動作条件: BitsKeepまたはChatGPTの対象ページで実行されること。副作用: DOM、GM storage、ウィンドウ状態、ネットワーク通信のいずれかを更新する場合がある。 */
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

    /** 目的: GM storage上のジョブ状態を掃除する。機能: Tampermonkey連携の対象処理を安全に進める。入力: 関数シグネチャで指定された値。出力: 処理結果またはなし。動作条件: BitsKeepまたはChatGPTの対象ページで実行されること。副作用: DOM、GM storage、ウィンドウ状態、ネットワーク通信のいずれかを更新する場合がある。 */
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

    /** 目的: ChatGPTワーカーの生存状態を保存する。機能: Tampermonkey連携の対象処理を安全に進める。入力: 関数シグネチャで指定された値。出力: 処理結果またはなし。動作条件: BitsKeepまたはChatGPTの対象ページで実行されること。副作用: DOM、GM storage、ウィンドウ状態、ネットワーク通信のいずれかを更新する場合がある。 */
    const writeWorkerHeartbeat = async (extra = {}) => {
        if (PAGE_ROLE !== 'chatgpt') return;

        await GM_setValue(WORKER_HEARTBEAT_KEY, JSON.stringify({
            path: location.pathname,
            href: location.href,
            updatedAt: new Date().toISOString(),
            ...extra,
        }));
    };

    /** 目的: BitsKeep画面からChatGPTワーカーへジョブを投入する。機能: Tampermonkey連携の対象処理を安全に進める。入力: 関数シグネチャで指定された値。出力: 処理結果またはなし。動作条件: BitsKeepまたはChatGPTの対象ページで実行されること。副作用: DOM、GM storage、ウィンドウ状態、ネットワーク通信のいずれかを更新する場合がある。 */
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

    /** 目的: 同期条件が成立するまでポーリングする。機能: Tampermonkey連携の対象処理を安全に進める。入力: 条件関数、タイムアウト、ポーリング間隔。出力: 成功可否または完了Promise。動作条件: BitsKeepまたはChatGPTの対象ページで実行されること。副作用: DOM、GM storage、ウィンドウ状態、ネットワーク通信のいずれかを更新する場合がある。 */
    const waitFor = async (resolver, timeoutMs = 15000, intervalMs = 250) => {
        const startedAt = Date.now();
        while (Date.now() - startedAt < timeoutMs) {
            const value = resolver();
            if (value) return value;
            await sleep(intervalMs);
        }
        return null;
    };

    /** 目的: 非同期条件が成立するまでポーリングする。機能: Tampermonkey連携の対象処理を安全に進める。入力: 条件関数、タイムアウト、ポーリング間隔。出力: 成功可否または完了Promise。動作条件: BitsKeepまたはChatGPTの対象ページで実行されること。副作用: DOM、GM storage、ウィンドウ状態、ネットワーク通信のいずれかを更新する場合がある。 */
    const waitForAsync = async (resolver, timeoutMs = 15000, intervalMs = 250) => {
        const startedAt = Date.now();
        while (Date.now() - startedAt < timeoutMs) {
            const value = await resolver();
            if (value) return value;
            await sleep(intervalMs);
        }
        return null;
    };

    /** 目的: ChatGPT画面の読み込み完了を待つ。機能: Tampermonkey連携の対象処理を安全に進める。入力: 関数シグネチャで指定された値。出力: 成功可否または完了Promise。動作条件: BitsKeepまたはChatGPTの対象ページで実行されること。副作用: DOM、GM storage、ウィンドウ状態、ネットワーク通信のいずれかを更新する場合がある。 */
    const waitForDocumentReady = async (timeoutMs = 60000) => {
        pushDebugLog('chatgpt.ready.wait', 'document.readyState complete を待機します。', { readyState: document.readyState });
        const ready = await waitFor(() => document.readyState === 'complete', timeoutMs, 250);
        if (!ready) {
            throw new Error('ChatGPT の画面読み込み完了を待てませんでした。ページ表示が遅延しています。');
        }
        pushDebugLog('chatgpt.ready.done', 'document.readyState complete を確認しました。');
    };

    /** 目的: ワーカータブ識別子を生成する。機能: Tampermonkey連携の対象処理を安全に進める。入力: 関数シグネチャで指定された値。出力: 探索結果、抽出結果、生成値のいずれか。動作条件: BitsKeepまたはChatGPTの対象ページで実行されること。副作用: なし。 */
    const createTabId = () => {
        try {
            return crypto.randomUUID();
        } catch {
            return `tab-${Date.now()}-${Math.random().toString(16).slice(2)}`;
        }
    };

    const chatGptDomHelpers = window.BitsKeepChatGptDomHelpers?.({
        sleep,
        waitFor,
        pushDebugLog,
        GM_xmlhttpRequest,
    });
    if (!chatGptDomHelpers) {
        throw new Error('BitsKeep ChatGPT DOM helpers を読み込めませんでした。');
    }
    const {
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
    } = chatGptDomHelpers;

    /** 目的: ジョブの二重実行を防ぐclaimを取得する。機能: Tampermonkey連携の対象処理を安全に進める。入力: 関数シグネチャで指定された値。出力: 成功可否または完了Promise。動作条件: BitsKeepまたはChatGPTの対象ページで実行されること。副作用: DOM、GM storage、ウィンドウ状態、ネットワーク通信のいずれかを更新する場合がある。 */
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

    /** 目的: 実行中ジョブのclaim期限を延長する。機能: Tampermonkey連携の対象処理を安全に進める。入力: 関数シグネチャで指定された値。出力: 成功可否または完了Promise。動作条件: BitsKeepまたはChatGPTの対象ページで実行されること。副作用: DOM、GM storage、ウィンドウ状態、ネットワーク通信のいずれかを更新する場合がある。 */
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

    /** 目的: 自タブが保持するclaimを解放する。機能: Tampermonkey連携の対象処理を安全に進める。入力: 関数シグネチャで指定された値。出力: 成功可否または完了Promise。動作条件: BitsKeepまたはChatGPTの対象ページで実行されること。副作用: DOM、GM storage、ウィンドウ状態、ネットワーク通信のいずれかを更新する場合がある。 */
    const releaseClaimIfOwned = async (jobId, tabId) => {
        const currentClaim = parseValue(await GM_getValue(JOB_CLAIM_KEY, ''));
        if (currentClaim?.jobId === jobId && currentClaim?.tabId === tabId) {
            await GM_setValue(JOB_CLAIM_KEY, '');
        }
    };

    /** 目的: PDF解析ジョブをChatGPT画面上で実行する。機能: Tampermonkey連携の対象処理を安全に進める。入力: 関数シグネチャで指定された値。出力: 処理結果またはなし。動作条件: BitsKeepまたはChatGPTの対象ページで実行されること。副作用: DOM、GM storage、ウィンドウ状態、ネットワーク通信のいずれかを更新する場合がある。 */
    const runChatGptJob = async (job, tabId) => {
        if (!job?.job_id || !job?.target_datasheet?.signed_download_url) return;

        let heartbeatTimer = null;
        let lastStatusPayload = null;
        /** 目的: ジョブ状態の保存と画面通知をまとめる。機能: Tampermonkey連携の対象処理を安全に進める。入力: 関数シグネチャで指定された値。出力: 処理結果またはなし。動作条件: BitsKeepまたはChatGPTの対象ページで実行されること。副作用: DOM、GM storage、ウィンドウ状態、ネットワーク通信のいずれかを更新する場合がある。 */
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

    /** 目的: BitsKeep画面側のGM storageブリッジを初期化する。機能: Tampermonkey連携の対象処理を安全に進める。入力: 関数シグネチャで指定された値。出力: 処理結果またはなし。動作条件: BitsKeepまたはChatGPTの対象ページで実行されること。副作用: DOM、GM storage、ウィンドウ状態、ネットワーク通信のいずれかを更新する場合がある。 */
    const initBitsKeepBridge = () => {
        let bridgePollTimer = null;
        let lastStatusUpdatedAt = '';
        let lastResultUpdatedAt = '';

        /** 目的: 保存済みジョブ状態をBitsKeep画面へ再送する。機能: Tampermonkey連携の対象処理を安全に進める。入力: 関数シグネチャで指定された値。出力: 処理結果またはなし。動作条件: BitsKeepまたはChatGPTの対象ページで実行されること。副作用: DOM、GM storage、ウィンドウ状態、ネットワーク通信のいずれかを更新する場合がある。 */
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

        /** 目的: 保存済み解析結果をBitsKeep画面へ再送する。機能: Tampermonkey連携の対象処理を安全に進める。入力: 関数シグネチャで指定された値。出力: 処理結果またはなし。動作条件: BitsKeepまたはChatGPTの対象ページで実行されること。副作用: DOM、GM storage、ウィンドウ状態、ネットワーク通信のいずれかを更新する場合がある。 */
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

        // BitsKeep画面からの解析開始イベントを受け取り、ChatGPT側ジョブキューへ保存する。
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

        // 画面離脱時にポーリングタイマーを解除する。ブラウザ資源を残さないための副作用を持つ。
        window.addEventListener('beforeunload', () => {
            if (bridgePollTimer !== null) {
                window.clearInterval(bridgePollTimer);
                bridgePollTimer = null;
            }
        });
    };

    /** 目的: ChatGPT画面側のワーカー監視を初期化する。機能: Tampermonkey連携の対象処理を安全に進める。入力: 関数シグネチャで指定された値。出力: 処理結果またはなし。動作条件: BitsKeepまたはChatGPTの対象ページで実行されること。副作用: DOM、GM storage、ウィンドウ状態、ネットワーク通信のいずれかを更新する場合がある。 */
    const initChatGptBridge = () => {
        let runningJobId = null;
        let pendingRawJob = '';
        const tabId = createTabId();
        let pollTimer = null;
        let heartbeatTimer = null;
        let idleJobLogged = false;
        pushDebugLog('init.chatgpt', 'ChatGPT bridge を初期化しました。', { tabId, path: location.pathname });

        /** 目的: GM storageから読んだジョブを検証して実行する。機能: Tampermonkey連携の対象処理を安全に進める。入力: 関数シグネチャで指定された値。出力: 処理結果またはなし。動作条件: BitsKeepまたはChatGPTの対象ページで実行されること。副作用: DOM、GM storage、ウィンドウ状態、ネットワーク通信のいずれかを更新する場合がある。 */
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

        /** 目的: 保存済みジョブを定期確認する。機能: Tampermonkey連携の対象処理を安全に進める。入力: 関数シグネチャで指定された値。出力: 処理結果またはなし。動作条件: BitsKeepまたはChatGPTの対象ページで実行されること。副作用: DOM、GM storage、ウィンドウ状態、ネットワーク通信のいずれかを更新する場合がある。 */
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
