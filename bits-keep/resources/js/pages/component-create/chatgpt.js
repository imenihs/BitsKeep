import { computed, nextTick, reactive, ref } from 'vue';
import { api } from '../../api.js';
import { useNavigationConfirm } from '../../composables/useNavigationConfirm.js';
/**
 * ChatGPT Web連携によるデータシート自動解析の状態、イベント同期、fallback操作を構成する。
 * 入力はPDF選択状態、解析結果ビルダー、モーダル制御関数で、戻り値は画面公開用の状態と操作関数。
 * 動作条件はTampermonkey helperがBitsKeep/ChatGPT双方で動作することで、一時PDF、sessionStorage、windowイベント、別タブ起動を副作用として持つ。
 */
// 目的: 部品登録画面のComponent Create Chat Gptを扱う。機能: 入力値を検証・整形し、画面または計算処理へ渡す。入力: 関数シグネチャの値。出力: 処理結果またはなし。動作条件: 部品登録画面の初期化後に呼び出す。副作用: Vue状態、localStorage、DOM、HTTP通信のいずれかを更新する場合がある。
export function useComponentCreateChatGpt(ctx) {
    const {
        datasheetFiles, datasheetLabels, datasheetTargetIndex, currentDatasheets, dirty,
        inlineSpecTypeModal, analyzing, helperResult, showHelperResultModal, pendingAiAction,
        showDatasheetManagerModal, buildHelperResult, hasHelperCandidates, releaseScrollLockIfNoModal,
        toastSuccess, toastError, logChatGptFlow, chatGptConfig, getChatGptRuntime, setChatGptRuntime,
        getAnalyzeDatasheet,
    } = ctx;
    const {
        CHATGPT_HELPER_MIN_VERSION, CHATGPT_WINDOW_NAME, BITSKEEP_WINDOW_NAME,
        CHATGPT_HELPER_RECHECK_STORAGE_KEY, CHATGPT_ACTIVE_JOB_STORAGE_KEY,
        CHATGPT_STATUS_STALE_MS, CHATGPT_QUEUED_STALE_MS, CHATGPT_WAITING_STALE_MS,
        CHATGPT_WORKER_STALE_MS, CHATGPT_WORKER_READY_WAIT_MS, CHATGPT_WORKER_READY_POLL_MS,
        CHATGPT_TEMP_CLEANUP_TIMEOUT_MS, CHATGPT_JOB_CREATE_TIMEOUT_MS,
    } = chatGptConfig;
    let chatGptWindowRef = null;
    let lastSyncedChatGptStatusAt = '';
    let lastSyncedChatGptResultAt = '';
    let chatGptHelperPromptShown = false;
    // ChatGPT 貼り付けモーダル
    const selectedDatasheetFile = computed(() => datasheetFiles.value[datasheetTargetIndex.value] ?? null);
    const hasDatasheetForAi = computed(() => datasheetFiles.value.length > 0);
    const showChatGPTPaste = ref(false);
    const chatGPTPasteText = ref('');
    const chatGPTPasteTextarea = ref(null);
    const navigationGuardActive = computed(() => (
        dirty.value
        || showHelperResultModal.value
        || inlineSpecTypeModal.open
        || !!helperResult.value
        || showChatGPTPaste.value
        || !!String(chatGPTPasteText.value ?? '').trim()
        || showDatasheetManagerModal.value
        || showChatGptRunModal.value
    ));
    useNavigationConfirm(navigationGuardActive, '未保存の入力があります。このまま画面を離れてもよいですか？');
    const chatGptStatusLabel = computed(() => {
        const labels = {
            idle: '待機中',
            preparing: 'PDF準備中',
            opening: 'ChatGPT起動中',
            waiting: 'ChatGPT待ち',
            review: '結果受信',
            failed: '自動取得失敗',
            login_required: 'ChatGPTログイン待ち',
        };
        return labels[chatGptJob.state] ?? '待機中';
    });
    const chatGptStatusChips = computed(() => {
        const chips = [
            {
                label: chatGptJob.connected ? 'Tampermonkey接続済み' : 'Tampermonkey未接続',
                tone: chatGptJob.connected ? 'ok' : 'warning',
            },
            {
                label: chatGptJob.helperVersion ? `helper v${chatGptJob.helperVersion}` : 'helper version 不明',
                tone: chatGptJob.helperVersion
                    ? (isChatGptHelperVersionCompatible() ? 'ok' : 'warning')
                    : 'warning',
            },
        ];
        if (datasheetFiles.value.length) {
            chips.push({
                label: selectedDatasheetFile.value ? `解析対象: ${selectedDatasheetFile.value.name}` : '解析対象未選択',
                tone: selectedDatasheetFile.value ? 'neutral' : 'warning',
            });
        }
        if (chatGptJob.state !== 'idle') {
            chips.push({
                label: chatGptStatusLabel.value,
                tone: chatGptJob.state === 'failed' ? 'danger' : (chatGptJob.state === 'review' ? 'ok' : 'neutral'),
            });
        }
        if (chatGptTempDatasheets.value.length) {
            chips.push({
                label: `temp PDF ${chatGptTempDatasheets.value.length}件`,
                tone: 'neutral',
            });
        }
        return chips;
    });
    const chatGptStepStates = computed(() => {
        const state = chatGptJob.state;
        return [
            {
                label: 'PDF準備',
                status: ['preparing', 'opening', 'waiting', 'review', 'failed', 'login_required'].includes(state) ? 'done' : 'current',
            },
            {
                label: 'ChatGPT起動',
                status: ['opening', 'waiting', 'review', 'failed', 'login_required'].includes(state)
                    ? (state === 'opening' || state === 'login_required' ? 'current' : 'done')
                    : 'pending',
            },
            {
                label: '解析待ち',
                status: ['waiting', 'review', 'failed'].includes(state)
                    ? (state === 'waiting' ? 'current' : 'done')
                    : 'pending',
            },
            {
                label: '結果確認',
                status: state === 'review' ? 'current' : 'pending',
            },
        ];
    });
    const canStartChatGptAutoFill = computed(() => (
        !!selectedDatasheetFile.value && chatGptJob.connected && isChatGptHelperVersionCompatible()
    ));
    const chatGptHelperIssue = computed(() => {
        if (!chatGptJob.connected) {
            return {
                kind: 'missing',
                title: 'Tampermonkey helper を検出できません',
                body: 'BitsKeep ページで userscript が動いていません。Tampermonkey の有効化対象URLとスクリプトの有効状態を確認してください。',
            };
        }
        if (!isChatGptHelperVersionCompatible()) {
            return {
                kind: 'outdated',
                title: 'Tampermonkey helper が旧版です',
                body: `現在の helper は v${chatGptJob.helperVersion || '不明'} です。ChatGPT自動入力には v${CHATGPT_HELPER_MIN_VERSION} 以上が必要です。`,
            };
        }
        return null;
    });
    const showChatGptRunHint = computed(() => (
        !selectedDatasheetFile.value
            ? '先に解析対象のPDFを選択してください。'
            : (!chatGptJob.connected
                ? 'Tampermonkey helper が BitsKeep ページで検出できていません。userscript の有効化対象URLを確認してください。'
                : (!isChatGptHelperVersionCompatible()
                    ? `Tampermonkey helper を更新してください。必要: v${CHATGPT_HELPER_MIN_VERSION}+ / 現在: v${chatGptJob.helperVersion || '不明'}`
                    : '解析開始後は別タブの ChatGPT で PDF 添付と送信を自動化します。'))
    ));
    // 目的: 部品登録画面のcompare Versionを扱う。機能: 入力値を検証・整形し、画面または計算処理へ渡す。入力: 関数シグネチャの値。出力: 表示値、配列、オブジェクト、数値のいずれか。動作条件: 部品登録画面の初期化後に呼び出す。副作用: なし。
    const compareVersion = (left, right) => {
        const leftParts = String(left ?? '').split('.').map((part) => Number.parseInt(part, 10) || 0);
        const rightParts = String(right ?? '').split('.').map((part) => Number.parseInt(part, 10) || 0);
        const length = Math.max(leftParts.length, rightParts.length);
        for (let index = 0; index < length; index += 1) {
            const leftPart = leftParts[index] ?? 0;
            const rightPart = rightParts[index] ?? 0;
            if (leftPart > rightPart) return 1;
            if (leftPart < rightPart) return -1;
        }
        return 0;
    };
    // 目的: 部品登録画面のwith Timeoutを扱う。機能: 入力値を検証・整形し、画面または計算処理へ渡す。入力: 関数シグネチャの値。出力: 処理結果またはなし。動作条件: 部品登録画面の初期化後に呼び出す。副作用: Vue状態、localStorage、DOM、HTTP通信のいずれかを更新する場合がある。
    const withTimeout = async (promise, timeoutMs, timeoutMessage) => {
        let timerId = null;
        try {
            return await Promise.race([
                promise,
                new Promise((_, reject) => {
                    timerId = window.setTimeout(() => {
                        reject(new Error(timeoutMessage || 'timeout'));
                    }, timeoutMs);
                }),
            ]);
        } finally {
            if (timerId !== null) {
                window.clearTimeout(timerId);
            }
        }
    };
    // 目的: 部品登録画面のsleepを扱う。機能: 入力値を検証・整形し、画面または計算処理へ渡す。入力: 関数シグネチャの値。出力: 処理結果またはなし。動作条件: 部品登録画面の初期化後に呼び出す。副作用: Vue状態、localStorage、DOM、HTTP通信のいずれかを更新する場合がある。
    const sleep = (ms) => new Promise((resolve) => window.setTimeout(resolve, ms));
    // 目的: 部品登録画面のis Chat Gpt Helper Version Compatibleを扱う。機能: 入力値を検証・整形し、画面または計算処理へ渡す。入力: 関数シグネチャの値。出力: 真偽値。動作条件: 部品登録画面の初期化後に呼び出す。副作用: なし。
    const isChatGptHelperVersionCompatible = () => (
        !!chatGptJob.helperVersion && compareVersion(chatGptJob.helperVersion, CHATGPT_HELPER_MIN_VERSION) >= 0
    );
    // 目的: 部品登録画面のpersist Active Chat Gpt Jobを扱う。機能: 入力値を検証・整形し、画面または計算処理へ渡す。入力: 関数シグネチャの値。出力: 処理結果またはなし。動作条件: 部品登録画面の初期化後に呼び出す。副作用: Vue状態、localStorage、DOM、HTTP通信のいずれかを更新する場合がある。
    const persistActiveChatGptJob = (overrides = {}) => {
        const jobId = overrides.jobId || chatGptJob.jobId;
        if (!jobId) return;
        window.sessionStorage.setItem(CHATGPT_ACTIVE_JOB_STORAGE_KEY, JSON.stringify({
            jobId,
            startedAt: overrides.startedAt || chatGptJob.updatedAt || new Date().toISOString(),
            datasheetLabel: overrides.datasheetLabel || datasheetTargetLabel.value || '',
        }));
    };
    // 目的: 部品登録画面のread Persisted Active Chat Gpt Jobを扱う。機能: 入力値を検証・整形し、画面または計算処理へ渡す。入力: 関数シグネチャの値。出力: 処理結果またはなし。動作条件: 部品登録画面の初期化後に呼び出す。副作用: Vue状態、localStorage、DOM、HTTP通信のいずれかを更新する場合がある。
    const readPersistedActiveChatGptJob = () => {
        const raw = window.sessionStorage.getItem(CHATGPT_ACTIVE_JOB_STORAGE_KEY);
        if (!raw) return null;
        try {
            return JSON.parse(raw);
        } catch {
            window.sessionStorage.removeItem(CHATGPT_ACTIVE_JOB_STORAGE_KEY);
            return null;
        }
    };
    // 目的: 部品登録画面のclear Persisted Active Chat Gpt Jobを扱う。機能: 入力値を検証・整形し、画面または計算処理へ渡す。入力: 関数シグネチャの値。出力: 処理結果またはなし。動作条件: 部品登録画面の初期化後に呼び出す。副作用: Vue状態、localStorage、DOM、HTTP通信のいずれかを更新する場合がある。
    const clearPersistedActiveChatGptJob = () => {
        window.sessionStorage.removeItem(CHATGPT_ACTIVE_JOB_STORAGE_KEY);
    };
    // 目的: 部品登録画面のreset Chat Gpt Job Stateを扱う。機能: 入力値を検証・整形し、画面または計算処理へ渡す。入力: 関数シグネチャの値。出力: 処理結果またはなし。動作条件: 部品登録画面の初期化後に呼び出す。副作用: Vue状態、localStorage、DOM、HTTP通信のいずれかを更新する場合がある。
    const resetChatGptJobState = ({ clearPersisted = true } = {}) => {
        chatGptJob.jobId = '';
        chatGptJob.state = 'idle';
        chatGptJob.detail = '';
        chatGptJob.error = '';
        chatGptJob.updatedAt = '';
        lastSyncedChatGptStatusAt = '';
        lastSyncedChatGptResultAt = '';
        chatGptUiLockSuppressed.value = false;
        if (clearPersisted) {
            clearPersistedActiveChatGptJob();
        }
    };
    // 目的: 部品登録画面のsync Tampermonkey Connectionを扱う。機能: 入力値を検証・整形し、画面または計算処理へ渡す。入力: 関数シグネチャの値。出力: 処理結果またはなし。動作条件: 部品登録画面の初期化後に呼び出す。副作用: Vue状態、localStorage、DOM、HTTP通信のいずれかを更新する場合がある。
    const syncTampermonkeyConnection = () => {
        const helper = window.__bitskeepTampermonkeyHelper;
        chatGptJob.connected = !!helper?.connected;
        chatGptJob.helperVersion = String(helper?.version ?? '');
        const helperLogPayload = {
            connected: chatGptJob.connected,
            helperVersion: chatGptJob.helperVersion || '(unknown)',
            hasHelperObject: !!helper,
            windowName: CHATGPT_WINDOW_NAME,
        };
        const nextSignature = JSON.stringify(helperLogPayload);
        if (nextSignature !== lastChatGptHelperLogSignature) {
            console.info('[BitsKeep][ChatGPT Helper]', helperLogPayload);
            lastChatGptHelperLogSignature = nextSignature;
        }
    };
    // 目的: 部品登録画面のsync Stored Chat Gpt Bridge Stateを扱う。機能: 入力値を検証・整形し、画面または計算処理へ渡す。入力: 関数シグネチャの値。出力: 処理結果またはなし。動作条件: 部品登録画面の初期化後に呼び出す。副作用: Vue状態、localStorage、DOM、HTTP通信のいずれかを更新する場合がある。
    const syncStoredChatGptBridgeState = async () => {
        const helper = window.__bitskeepTampermonkeyHelper;
        if (!helper?.connected) return;
        try {
            const [statusDetail, resultDetail] = await Promise.all([
                typeof helper.getStoredStatus === 'function' ? helper.getStoredStatus() : null,
                typeof helper.getStoredResult === 'function' ? helper.getStoredResult() : null,
            ]);
            if (statusDetail?.jobId && statusDetail.updatedAt && statusDetail.updatedAt !== lastSyncedChatGptStatusAt) {
                lastSyncedChatGptStatusAt = statusDetail.updatedAt;
                handleChatGptStatusEvent({ detail: statusDetail });
            }
            if (resultDetail?.jobId && resultDetail.updatedAt && resultDetail.updatedAt !== lastSyncedChatGptResultAt && (resultDetail.jsonText || resultDetail.rawText)) {
                lastSyncedChatGptResultAt = resultDetail.updatedAt;
                handleChatGptResultEvent({ detail: resultDetail });
            }
        } catch {
            // helper 側の現在値取得に失敗しても通常の event/poll 同期へ任せる。
        }
    };
    // 目的: 部品登録画面のsync Chat Gpt Worker Heartbeatを扱う。機能: 入力値を検証・整形し、画面または計算処理へ渡す。入力: 関数シグネチャの値。出力: 処理結果またはなし。動作条件: 部品登録画面の初期化後に呼び出す。副作用: Vue状態、localStorage、DOM、HTTP通信のいずれかを更新する場合がある。
    const syncChatGptWorkerHeartbeat = async () => {
        const helper = window.__bitskeepTampermonkeyHelper;
        if (!helper?.connected || typeof helper.getWorkerHeartbeat !== 'function') {
            chatGptWorkerHeartbeat.value = null;
            return;
        }
        try {
            chatGptWorkerHeartbeat.value = await helper.getWorkerHeartbeat();
        } catch {
            chatGptWorkerHeartbeat.value = null;
        }
    };
    // 目的: 部品登録画面のhas Fresh Chat Gpt Workerを扱う。機能: 入力値を検証・整形し、画面または計算処理へ渡す。入力: 関数シグネチャの値。出力: 真偽値。動作条件: 部品登録画面の初期化後に呼び出す。副作用: なし。
    const hasFreshChatGptWorker = () => {
        const updatedAt = Date.parse(chatGptWorkerHeartbeat.value?.updatedAt || '');
        if (Number.isNaN(updatedAt)) return false;
        return (Date.now() - updatedAt) < CHATGPT_WORKER_STALE_MS;
    };
    // 目的: 部品登録画面のis Chat Gpt Worker Readyを扱う。機能: 入力値を検証・整形し、画面または計算処理へ渡す。入力: 関数シグネチャの値。出力: 真偽値。動作条件: 部品登録画面の初期化後に呼び出す。副作用: なし。
    const isChatGptWorkerReady = () => (
        hasFreshChatGptWorker()
        && chatGptWorkerHeartbeat.value?.ready === true
        && chatGptWorkerHeartbeat.value?.acceptingJobs !== false
    );
    // 目的: 部品登録画面のwait For Chat Gpt Worker Readyを扱う。機能: 入力値を検証・整形し、画面または計算処理へ渡す。入力: 関数シグネチャの値。出力: 処理結果またはなし。動作条件: 部品登録画面の初期化後に呼び出す。副作用: Vue状態、localStorage、DOM、HTTP通信のいずれかを更新する場合がある。
    const waitForChatGptWorkerReady = async ({ openWindowIfNeeded = true } = {}) => {
        await syncChatGptWorkerHeartbeat();
        if (isChatGptWorkerReady()) {
            logChatGptFlow('worker.ready.existing', {
                workerHeartbeat: chatGptWorkerHeartbeat.value,
            });
            return true;
        }
        if (openWindowIfNeeded) {
            primeChatGptWindow();
        }
        logChatGptFlow('worker.ready.wait.start', {
            workerHeartbeat: chatGptWorkerHeartbeat.value,
        });
        const startedAt = Date.now();
        while ((Date.now() - startedAt) < CHATGPT_WORKER_READY_WAIT_MS) {
            await sleep(CHATGPT_WORKER_READY_POLL_MS);
            await syncChatGptWorkerHeartbeat();
            if (isChatGptWorkerReady()) {
                logChatGptFlow('worker.ready.wait.done', {
                    elapsedMs: Date.now() - startedAt,
                    workerHeartbeat: chatGptWorkerHeartbeat.value,
                });
                return true;
            }
        }
        logChatGptFlow('worker.ready.wait.timeout', {
            elapsedMs: Date.now() - startedAt,
            workerHeartbeat: chatGptWorkerHeartbeat.value,
        });
        return false;
    };
    // 目的: 部品登録画面のopen Chat Gpt Helper Update Modalを扱う。機能: 入力値を検証・整形し、画面または計算処理へ渡す。入力: 関数シグネチャの値。出力: 処理結果またはなし。動作条件: 部品登録画面の初期化後に呼び出す。副作用: Vue状態、localStorage、DOM、HTTP通信のいずれかを更新する場合がある。
    const openChatGptHelperUpdateModal = () => {
        syncTampermonkeyConnection();
        if (chatGptJob.connected && isChatGptHelperVersionCompatible()) {
            chatGptHelperCheckStatus.value = 'success';
            chatGptHelperCheckMessage.value = `helper v${chatGptJob.helperVersion} を検出しました。このまま ChatGPT自動解析を使えます。`;
        } else {
            chatGptHelperCheckStatus.value = 'idle';
            chatGptHelperCheckMessage.value = '';
        }
        showChatGptHelperUpdateModal.value = true;
    };
    // 目的: 部品登録画面のclose Chat Gpt Helper Update Modalを扱う。機能: 入力値を検証・整形し、画面または計算処理へ渡す。入力: 関数シグネチャの値。出力: 処理結果またはなし。動作条件: 部品登録画面の初期化後に呼び出す。副作用: Vue状態、localStorage、DOM、HTTP通信のいずれかを更新する場合がある。
    const closeChatGptHelperUpdateModal = () => {
        chatGptHelperCheckStatus.value = 'idle';
        chatGptHelperCheckMessage.value = '';
        showChatGptHelperUpdateModal.value = false;
        nextTick(() => {
            releaseScrollLockIfNoModal();
        });
    };
    // 目的: 部品登録画面のmaybe Prompt Chat Gpt Helper Updateを扱う。機能: 入力値を検証・整形し、画面または計算処理へ渡す。入力: 関数シグネチャの値。出力: 処理結果またはなし。動作条件: 部品登録画面の初期化後に呼び出す。副作用: Vue状態、localStorage、DOM、HTTP通信のいずれかを更新する場合がある。
    const maybePromptChatGptHelperUpdate = () => {
        if (chatGptHelperPromptShown) return;
        if (!chatGptHelperIssue.value) return;
        if (!showChatGptRunModal.value && !showChatGptHelperUpdateModal.value) return;
        chatGptHelperPromptShown = true;
        showChatGptHelperUpdateModal.value = true;
    };
    // 目的: 部品登録画面のensure Chat Gpt Helper Readyを扱う。機能: 入力値を検証・整形し、画面または計算処理へ渡す。入力: 関数シグネチャの値。出力: 処理結果またはなし。動作条件: 部品登録画面の初期化後に呼び出す。副作用: Vue状態、localStorage、DOM、HTTP通信のいずれかを更新する場合がある。
    const ensureChatGptHelperReady = ({ showModal = true, showToast = true } = {}) => {
        syncTampermonkeyConnection();
        const allowToast = showToast && !showChatGptHelperUpdateModal.value;
        if (!chatGptJob.connected) {
            if (allowToast) {
                toastError('Tampermonkey helper を検出できません。userscript を確認してください。');
            }
            if (showModal) {
                openChatGptHelperUpdateModal();
            }
            return false;
        }
        if (!isChatGptHelperVersionCompatible()) {
            if (allowToast) {
                toastError(`Tampermonkey helper が旧版です。v${CHATGPT_HELPER_MIN_VERSION}+ へ更新してください。現在: v${chatGptJob.helperVersion || '不明'}`);
            }
            if (showModal) {
                openChatGptHelperUpdateModal();
            }
            return false;
        }
        return true;
    };
    // 目的: 部品登録画面のreload For Chat Gpt Helper Updateを扱う。機能: 入力値を検証・整形し、画面または計算処理へ渡す。入力: 関数シグネチャの値。出力: 処理結果またはなし。動作条件: 部品登録画面の初期化後に呼び出す。副作用: Vue状態、localStorage、DOM、HTTP通信のいずれかを更新する場合がある。
    const reloadForChatGptHelperUpdate = () => {
        window.sessionStorage.setItem(CHATGPT_HELPER_RECHECK_STORAGE_KEY, '1');
        window.location.reload();
    };
    // 目的: 部品登録画面のhandle Chat Gpt Helper Reload Recheckを扱う。機能: 入力値を検証・整形し、画面または計算処理へ渡す。入力: 関数シグネチャの値。出力: 処理結果またはなし。動作条件: 部品登録画面の初期化後に呼び出す。副作用: Vue状態、localStorage、DOM、HTTP通信のいずれかを更新する場合がある。
    const handleChatGptHelperReloadRecheck = () => {
        if (ensureChatGptHelperReady({ showModal: false, showToast: false })) {
            chatGptHelperPromptShown = true;
            chatGptHelperCheckStatus.value = 'idle';
            chatGptHelperCheckMessage.value = '';
            toastSuccess(`Tampermonkey helper v${chatGptJob.helperVersion} を検出しました。ChatGPT自動入力を使えます。`);
            return;
        }
        openChatGptHelperUpdateModal();
        chatGptHelperCheckStatus.value = 'warning';
        chatGptHelperCheckMessage.value = `${chatGptHelperIssue.value.body} userscript 更新後は、この部品登録画面を再読込してください。`;
    };
    // 目的: 部品登録画面のprime Chat Gpt Windowを扱う。機能: 入力値を検証・整形し、画面または計算処理へ渡す。入力: 関数シグネチャの値。出力: 処理結果またはなし。動作条件: 部品登録画面の初期化後に呼び出す。副作用: Vue状態、localStorage、DOM、HTTP通信のいずれかを更新する場合がある。
    const primeChatGptWindow = () => {
        if (hasFreshChatGptWorker()) {
            logChatGptFlow('prime.reuse_worker', {
                workerHeartbeat: chatGptWorkerHeartbeat.value,
            });
            return;
        }
        try {
            const openedWindow = window.open('https://chatgpt.com/', CHATGPT_WINDOW_NAME);
            if (openedWindow) {
                chatGptWindowRef = openedWindow;
                chatGptWindowRef.focus?.();
                logChatGptFlow('prime.open_window', {
                    reusedNamedWindow: true,
                });
            }
        } catch {
            // ブラウザが拒否した場合は userscript 側 fallback に任せる。
        }
    };
    // 目的: 部品登録画面のupdate Chat Gpt Job Stateを扱う。機能: 入力値を検証・整形し、画面または計算処理へ渡す。入力: 関数シグネチャの値。出力: 処理結果またはなし。動作条件: 部品登録画面の初期化後に呼び出す。副作用: Vue状態、localStorage、DOM、HTTP通信のいずれかを更新する場合がある。
    const updateChatGptJobState = (state, detail = '', error = '', updatedAt = '') => {
        chatGptJob.state = state;
        chatGptJob.detail = detail;
        chatGptJob.error = error;
        chatGptJob.updatedAt = updatedAt || new Date().toISOString();
        if (!['preparing', 'opening', 'waiting'].includes(state)) {
            chatGptUiLockSuppressed.value = false;
        }
        if (chatGptJob.jobId && ['preparing', 'opening', 'waiting', 'login_required'].includes(state)) {
            persistActiveChatGptJob({ startedAt: chatGptJob.updatedAt });
        }
        if (['idle', 'review', 'failed'].includes(state)) {
            clearPersistedActiveChatGptJob();
        }
    };
    // 目的: 部品登録画面のhard Reset Chat Gpt Jobを扱う。機能: 入力値を検証・整形し、画面または計算処理へ渡す。入力: 関数シグネチャの値。出力: 処理結果またはなし。動作条件: 部品登録画面の初期化後に呼び出す。副作用: Vue状態、localStorage、DOM、HTTP通信のいずれかを更新する場合がある。
    const hardResetChatGptJob = async () => {
        const currentJobId = chatGptJob.jobId;
        await clearRemoteChatGptState(currentJobId);
        await clearChatGptTempDatasheets();
        helperResult.value = null;
        chatGPTPasteText.value = '';
        showHelperResultModal.value = false;
        showChatGPTPaste.value = false;
        showDatasheetManagerModal.value = false;
        showChatGptRunModal.value = false;
        chatGptGuideReason.value = '';
        pendingAiAction.value = '';
        chatGptUiLockSuppressed.value = false;
        resetChatGptJobState();
        chatGptWorkerHeartbeat.value = null;
        nextTick(() => {
            releaseScrollLockIfNoModal();
        });
        toastSuccess('ChatGPT ジョブ状態を破棄してリセットしました。');
    };
    // 目的: 部品登録画面のclear Remote Chat Gpt Stateを扱う。機能: 入力値を検証・整形し、画面または計算処理へ渡す。入力: 関数シグネチャの値。出力: 処理結果またはなし。動作条件: 部品登録画面の初期化後に呼び出す。副作用: Vue状態、localStorage、DOM、HTTP通信のいずれかを更新する場合がある。
    const clearRemoteChatGptState = async (jobId = '') => {
        const helper = window.__bitskeepTampermonkeyHelper;
        if (helper?.connected && typeof helper.clearRemoteState === 'function') {
            try {
                await helper.clearRemoteState(jobId);
            } catch {
                // remote state 掃除に失敗しても画面復帰を優先する。
            }
        }
    };
    // 目的: 部品登録画面のexpire Stale Chat Gpt Jobを扱う。機能: 入力値を検証・整形し、画面または計算処理へ渡す。入力: 関数シグネチャの値。出力: 処理結果またはなし。動作条件: 部品登録画面の初期化後に呼び出す。副作用: Vue状態、localStorage、DOM、HTTP通信のいずれかを更新する場合がある。
    const expireStaleChatGptJob = (jobId, message) => {
        if (!jobId || chatGptJob.jobId !== jobId) return;
        updateChatGptJobState('failed', message, message);
        showChatGptRunModal.value = false;
        void clearRemoteChatGptState(jobId);
        openChatGptGuide(`${message} ChatGPT タブを閉じた場合や、同期が途切れた場合は、もう一度「ChatGPTで自動入力」を実行してください。`);
        toastError(message);
    };
    // 目的: 部品登録画面のmaybe Expire Chat Gpt Job Stateを扱う。機能: 入力値を検証・整形し、画面または計算処理へ渡す。入力: 関数シグネチャの値。出力: 処理結果またはなし。動作条件: 部品登録画面の初期化後に呼び出す。副作用: Vue状態、localStorage、DOM、HTTP通信のいずれかを更新する場合がある。
    const maybeExpireChatGptJobState = () => {
        if (!chatGptJob.jobId || !['preparing', 'opening', 'waiting'].includes(chatGptJob.state)) return;
        if (!chatGptJob.updatedAt) return;
        const heartbeatAt = Date.parse(chatGptWorkerHeartbeat.value?.updatedAt || '');
        const hasFreshWorker = !Number.isNaN(heartbeatAt) && (Date.now() - heartbeatAt) < CHATGPT_WORKER_STALE_MS;
        if ((chatGptJob.state === 'preparing' || chatGptJob.state === 'opening') && !hasFreshWorker) {
            expireStaleChatGptJob(chatGptJob.jobId, 'ChatGPT タブが見つからないため、前回ジョブを破棄しました。');
            return;
        }
        const updatedAt = Date.parse(chatGptJob.updatedAt);
        if (Number.isNaN(updatedAt)) return;
        const staleMs = chatGptJob.state === 'waiting' ? CHATGPT_WAITING_STALE_MS : CHATGPT_STATUS_STALE_MS;
        if ((Date.now() - updatedAt) < staleMs) return;
        expireStaleChatGptJob(chatGptJob.jobId, 'ChatGPT 解析の状態更新が途切れました。');
    };
    // 目的: 部品登録画面のget Chat Gpt Status Stale Msを扱う。機能: 入力値を検証・整形し、画面または計算処理へ渡す。入力: 関数シグネチャの値。出力: 表示値、配列、オブジェクト、数値のいずれか。動作条件: 部品登録画面の初期化後に呼び出す。副作用: なし。
    const getChatGptStatusStaleMs = (status) => {
        if (status === 'queued') return CHATGPT_QUEUED_STALE_MS;
        if (status === 'waiting_response') return CHATGPT_WAITING_STALE_MS;
        return CHATGPT_STATUS_STALE_MS;
    };
    // 目的: 部品登録画面のclear Chat Gpt Temp Datasheetsを扱う。機能: 入力値を検証・整形し、画面または計算処理へ渡す。入力: 関数シグネチャの値。出力: 処理結果またはなし。動作条件: 部品登録画面の初期化後に呼び出す。副作用: Vue状態、localStorage、DOM、HTTP通信のいずれかを更新する場合がある。
    const clearChatGptTempDatasheets = async () => {
        const tokens = chatGptTempDatasheets.value.map((entry) => entry.token).filter(Boolean);
        if (!tokens.length) {
            chatGptTempDatasheets.value = [];
            logChatGptFlow('cleanup.skip', {
                reason: 'no_temp_tokens',
            });
            return;
        }
        logChatGptFlow('cleanup.start', {
            tokenCount: tokens.length,
        });
        const results = await Promise.allSettled(tokens.map((token) => withTimeout(
            api.delete(`/component-helper/chatgpt-jobs/${token}`),
            CHATGPT_TEMP_CLEANUP_TIMEOUT_MS,
            `temp cleanup timeout: ${token}`
        )));
        chatGptTempDatasheets.value = [];
        const failedTokens = results.flatMap((result, index) => (
            result.status === 'rejected'
                ? [{
                    token: tokens[index],
                    reason: result.reason?.message || 'cleanup_failed',
                }]
                : []
        ));
        logChatGptFlow('cleanup.done', {
            tokenCount: tokens.length,
            failedCount: failedTokens.length,
            failedTokens,
        });
    };
    // 目的: 部品登録画面のopen Datasheet Managerを扱う。機能: 入力値を検証・整形し、画面または計算処理へ渡す。入力: 関数シグネチャの値。出力: 処理結果またはなし。動作条件: 部品登録画面の初期化後に呼び出す。副作用: Vue状態、localStorage、DOM、HTTP通信のいずれかを更新する場合がある。
    const openDatasheetManager = () => {
        if (isChatGptJobBusy.value) return;
        showChatGptRunModal.value = false;
        showDatasheetManagerModal.value = true;
    };
    // 目的: 部品登録画面のclose Datasheet Managerを扱う。機能: 入力値を検証・整形し、画面または計算処理へ渡す。入力: 関数シグネチャの値。出力: 処理結果またはなし。動作条件: 部品登録画面の初期化後に呼び出す。副作用: Vue状態、localStorage、DOM、HTTP通信のいずれかを更新する場合がある。
    const closeDatasheetManager = () => {
        showDatasheetManagerModal.value = false;
        pendingAiAction.value = '';
        nextTick(() => {
            releaseScrollLockIfNoModal();
        });
    };
    // 目的: 部品登録画面のqueue Chat Gpt Jobを扱う。機能: 入力値を検証・整形し、画面または計算処理へ渡す。入力: 関数シグネチャの値。出力: 処理結果またはなし。動作条件: 部品登録画面の初期化後に呼び出す。副作用: Vue状態、localStorage、DOM、HTTP通信のいずれかを更新する場合がある。
    const queueChatGptJob = async (job) => {
        const helper = window.__bitskeepTampermonkeyHelper;
        logChatGptFlow('queue.start', {
            jobId: job?.job_id || '',
            hasHelper: !!helper,
            helperConnected: !!helper?.connected,
            helperVersion: chatGptJob.helperVersion || '(unknown)',
        });
        if (helper?.connected && typeof helper.enqueueJob === 'function') {
            try {
                const queued = await helper.enqueueJob(job);
                logChatGptFlow('queue.helper.result', {
                    jobId: job?.job_id || '',
                    queued: queued !== false,
                });
                if (queued !== false) {
                    return;
                }
            } catch (error) {
                console.error('[BitsKeep][ChatGPT Flow] queue.helper.failed', error);
                // userscript bridge が失敗した場合は CustomEvent fallback を使う。
            }
        }
        logChatGptFlow('queue.fallback.event', {
            jobId: job?.job_id || '',
        });
        window.dispatchEvent(new CustomEvent('bitskeep-chatgpt-start', { detail: job }));
    };
    // 目的: 部品登録画面のbegin Ai Actionを扱う。機能: 入力値を検証・整形し、画面または計算処理へ渡す。入力: 関数シグネチャの値。出力: 処理結果またはなし。動作条件: 部品登録画面の初期化後に呼び出す。副作用: Vue状態、localStorage、DOM、HTTP通信のいずれかを更新する場合がある。
    const beginAiAction = async (action) => {
        logChatGptFlow('begin.action', {
            action,
            datasheetCount: datasheetFiles.value.length,
            selectedIndex: datasheetTargetIndex.value,
            selectedName: selectedDatasheetFile.value?.name || '',
        });
        if (!datasheetFiles.value.length) {
            toastError('先にデータシートPDFを選択してください。');
            return;
        }
        if (action === 'chatgpt' && !ensureChatGptHelperReady()) {
            return;
        }
        if (datasheetFiles.value.length > 1) {
            pendingAiAction.value = action;
            openDatasheetManager();
            return;
        }
        pendingAiAction.value = '';
        if (action === 'gemini') {
            await getAnalyzeDatasheet()?.(true);
            return;
        }
        primeChatGptWindow();
        openChatGptRun();
        await nextTick();
        await startChatGPTAutoFill();
    };
    // 目的: 部品登録画面のconfirm Datasheet Target Selectionを扱う。機能: 入力値を検証・整形し、画面または計算処理へ渡す。入力: 関数シグネチャの値。出力: 処理結果またはなし。動作条件: 部品登録画面の初期化後に呼び出す。副作用: Vue状態、localStorage、DOM、HTTP通信のいずれかを更新する場合がある。
    const confirmDatasheetTargetSelection = async () => {
        const action = pendingAiAction.value;
        logChatGptFlow('confirm.target', {
            action,
            selectedIndex: datasheetTargetIndex.value,
            selectedName: selectedDatasheetFile.value?.name || '',
        });
        closeDatasheetManager();
        if (action === 'gemini') {
            await getAnalyzeDatasheet()?.(true);
            return;
        }
        if (action !== 'chatgpt') {
            return;
        }
        if (!ensureChatGptHelperReady()) {
            return;
        }
        primeChatGptWindow();
        openChatGptRun();
        await nextTick();
        await startChatGPTAutoFill();
    };
    // 目的: 部品登録画面のopen Chat Gpt Runを扱う。機能: 入力値を検証・整形し、画面または計算処理へ渡す。入力: 関数シグネチャの値。出力: 処理結果またはなし。動作条件: 部品登録画面の初期化後に呼び出す。副作用: Vue状態、localStorage、DOM、HTTP通信のいずれかを更新する場合がある。
    const openChatGptRun = () => {
        syncTampermonkeyConnection();
        chatGptGuideReason.value = '';
        showChatGptHelperUpdateModal.value = false;
        showDatasheetManagerModal.value = false;
        showChatGptRunModal.value = true;
    };
    // 目的: 部品登録画面のclose Chat Gpt Runを扱う。機能: 入力値を検証・整形し、画面または計算処理へ渡す。入力: 関数シグネチャの値。出力: 処理結果またはなし。動作条件: 部品登録画面の初期化後に呼び出す。副作用: Vue状態、localStorage、DOM、HTTP通信のいずれかを更新する場合がある。
    const closeChatGptRun = () => {
        if (isChatGptJobBusy.value) {
            toastError('ChatGPT 解析中はこの画面を閉じられません。結果受信まで待ってください。');
            return;
        }
        showChatGptRunModal.value = false;
        nextTick(() => {
            releaseScrollLockIfNoModal();
        });
    };
    // 目的: 部品登録画面のopen Chat Gpt Guideを扱う。機能: 入力値を検証・整形し、画面または計算処理へ渡す。入力: 関数シグネチャの値。出力: 処理結果またはなし。動作条件: 部品登録画面の初期化後に呼び出す。副作用: Vue状態、localStorage、DOM、HTTP通信のいずれかを更新する場合がある。
    const openChatGptGuide = (reason) => {
        chatGptGuideReason.value = reason;
        showChatGptRunModal.value = true;
    };
    // 目的: 部品登録画面のconsume Chat Gpt Raw Textを扱う。機能: 入力値を検証・整形し、画面または計算処理へ渡す。入力: 関数シグネチャの値。出力: 処理結果またはなし。動作条件: 部品登録画面の初期化後に呼び出す。副作用: Vue状態、localStorage、DOM、HTTP通信のいずれかを更新する場合がある。
    const consumeChatGptRawText = (rawText) => {
        let raw = String(rawText ?? '').trim();
        if (!raw) {
            return false;
        }
        raw = raw.replace(/^```(?:json)?\s*/i, '').replace(/\s*```$/, '').trim();
        let data;
        try {
            data = JSON.parse(raw);
        } catch {
            return false;
        }
        const result = buildHelperResult(data);
        if (!hasHelperCandidates(result)) {
            return false;
        }
        helperResult.value = result;
        showChatGptRunModal.value = false;
        showHelperResultModal.value = true;
        return true;
    };
    // 目的: 部品登録画面のopen Chat GPTPasteを扱う。機能: 入力値を検証・整形し、画面または計算処理へ渡す。入力: 関数シグネチャの値。出力: 処理結果またはなし。動作条件: 部品登録画面の初期化後に呼び出す。副作用: Vue状態、localStorage、DOM、HTTP通信のいずれかを更新する場合がある。
    const openChatGPTPaste = () => {
        showChatGptRunModal.value = false;
        showChatGPTPaste.value = true;
        nextTick(() => {
            chatGPTPasteTextarea.value?.focus?.();
        });
    };
    /**
     * ChatGPT が返した JSON テキストをパースして helperResult にセットする。
     * JSON にコードブロック（```json ... ```）が含まれていても除去して処理する。
     */
    // 目的: 部品登録画面のparse Chat GPTResultを扱う。機能: 入力値を検証・整形し、画面または計算処理へ渡す。入力: 関数シグネチャの値。出力: 表示値、配列、オブジェクト、数値のいずれか。動作条件: 部品登録画面の初期化後に呼び出す。副作用: なし。
    const parseChatGPTResult = () => {
        if (!chatGPTPasteText.value.trim()) {
            toastError('テキストを貼り付けてください。');
            return;
        }
        if (!consumeChatGptRawText(chatGPTPasteText.value)) {
            toastError('JSON の形式が正しくありません。ChatGPT の出力をそのまま貼り付けてください。');
            return;
        }
        chatGPTPasteText.value = '';
        showChatGPTPaste.value = false;
        nextTick(() => {
            releaseScrollLockIfNoModal();
        });
    };
    // 目的: 部品登録画面のdismiss Chat GPTPasteを扱う。機能: 入力値を検証・整形し、画面または計算処理へ渡す。入力: 関数シグネチャの値。出力: 処理結果またはなし。動作条件: 部品登録画面の初期化後に呼び出す。副作用: Vue状態、localStorage、DOM、HTTP通信のいずれかを更新する場合がある。
    const dismissChatGPTPaste = () => {
        showChatGPTPaste.value = false;
        nextTick(() => {
            releaseScrollLockIfNoModal();
        });
    };
    // 目的: 部品登録画面のhandle Chat Gpt Status Eventを扱う。機能: 入力値を検証・整形し、画面または計算処理へ渡す。入力: 関数シグネチャの値。出力: 処理結果またはなし。動作条件: 部品登録画面の初期化後に呼び出す。副作用: Vue状態、localStorage、DOM、HTTP通信のいずれかを更新する場合がある。
    const handleChatGptStatusEvent = (event) => {
        const detail = event.detail ?? {};
        logChatGptFlow('status.event.received', detail);
        if (!detail.jobId) return;
        if (!chatGptJob.jobId) {
            const persistedJob = readPersistedActiveChatGptJob();
            if (persistedJob?.jobId === detail.jobId) {
                chatGptJob.jobId = detail.jobId;
            }
        }
        if (detail.jobId !== chatGptJob.jobId) return;
        lastSyncedChatGptStatusAt = detail.updatedAt || lastSyncedChatGptStatusAt;
        if (['queued', 'opening_chatgpt', 'downloading_pdf', 'attaching_pdf', 'submitting', 'waiting_response'].includes(detail.status)) {
            const sourceUpdatedAt = Date.parse(detail.updatedAt || '');
            const staleMs = getChatGptStatusStaleMs(detail.status);
            if (!Number.isNaN(sourceUpdatedAt) && (Date.now() - sourceUpdatedAt) >= staleMs) {
                expireStaleChatGptJob(detail.jobId, 'ChatGPT 解析の古い状態が残っていたため、前回ジョブを破棄しました。');
                return;
            }
        }
        const statusMap = {
            queued: ['opening', 'ChatGPTタブを起動しています。'],
            opening_chatgpt: ['opening', 'ChatGPTタブを前面化しました。'],
            downloading_pdf: ['opening', '解析対象PDFをChatGPTタブへ渡しています。'],
            attaching_pdf: ['opening', 'PDFを添付しています。'],
            submitting: ['waiting', 'プロンプトとPDFを送信しています。'],
            waiting_response: ['waiting', 'ChatGPTの応答を待っています。'],
            result_ready: ['review', '結果を受信しました。候補を確認してください。'],
            login_required: ['login_required', 'ChatGPTへログインしてから再開してください。'],
            failed: ['failed', detail.message || '自動取得に失敗しました。'],
        };
        const [nextState, nextDetail] = statusMap[detail.status] ?? ['waiting', detail.message || 'ChatGPTの応答を待っています。'];
        updateChatGptJobState(nextState, nextDetail, detail.message || '', detail.updatedAt || '');
        if (['opening', 'waiting'].includes(nextState) && !chatGptUiLockSuppressed.value) {
            showChatGptRunModal.value = true;
        }
        if (detail.status === 'login_required') {
            openChatGptGuide('ChatGPT タブでログインしてから、もう一度「ChatGPTで自動入力」を実行してください。');
        }
        if (detail.status === 'failed') {
            if (detail.rawText) {
                chatGPTPasteText.value = detail.rawText;
            }
            openChatGptGuide(detail.message || '自動解析に失敗しました。「ChatGPTから貼り付け」に切り替えてください。');
        }
    };
    // 目的: 部品登録画面のhandle Chat Gpt Result Eventを扱う。機能: 入力値を検証・整形し、画面または計算処理へ渡す。入力: 関数シグネチャの値。出力: 処理結果またはなし。動作条件: 部品登録画面の初期化後に呼び出す。副作用: Vue状態、localStorage、DOM、HTTP通信のいずれかを更新する場合がある。
    const handleChatGptResultEvent = (event) => {
        const detail = event.detail ?? {};
        logChatGptFlow('result.event.received', {
            jobId: detail.jobId || '',
            hasJsonText: !!detail.jsonText,
            rawLength: String(detail.rawText ?? '').length,
            updatedAt: detail.updatedAt || '',
        });
        if (!detail.jobId) return;
        if (!chatGptJob.jobId) {
            const persistedJob = readPersistedActiveChatGptJob();
            if (persistedJob?.jobId === detail.jobId) {
                chatGptJob.jobId = detail.jobId;
            }
        }
        if (detail.jobId !== chatGptJob.jobId) return;
        lastSyncedChatGptResultAt = detail.updatedAt || lastSyncedChatGptResultAt;
        updateChatGptJobState('review', '結果を受信しました。候補を確認してください。', '', detail.updatedAt || '');
        showChatGptRunModal.value = false;
        window.focus?.();
        if (consumeChatGptRawText(detail.jsonText ?? detail.rawText ?? '')) {
            toastSuccess('ChatGPT の解析結果を受信しました。BitsKeep に戻って候補を確認してください。');
            return;
        }
        chatGPTPasteText.value = detail.rawText ?? '';
        openChatGptGuide('ChatGPT の返答から JSON を自動抽出できませんでした。貼り付け fallback へ切り替えてください。');
    };
    // 目的: 部品登録画面のopen Paste Fallback From Guideを扱う。機能: 入力値を検証・整形し、画面または計算処理へ渡す。入力: 関数シグネチャの値。出力: 処理結果またはなし。動作条件: 部品登録画面の初期化後に呼び出す。副作用: Vue状態、localStorage、DOM、HTTP通信のいずれかを更新する場合がある。
    const openPasteFallbackFromGuide = () => {
        chatGptGuideReason.value = '';
        openChatGPTPaste();
    };
    // 目的: 部品登録画面のcopy Chat Gpt Fallback Textを扱う。機能: 入力値を検証・整形し、画面または計算処理へ渡す。入力: 関数シグネチャの値。出力: 表示値、配列、オブジェクト、数値のいずれか。動作条件: 部品登録画面の初期化後に呼び出す。副作用: なし。
    const copyChatGptFallbackText = async () => {
        const rawText = String(chatGPTPasteText.value ?? '').trim();
        if (!rawText) {
            toastError('コピーできる ChatGPT 応答テキストがありません。');
            return;
        }
        try {
            await navigator.clipboard.writeText(rawText);
            toastSuccess('ChatGPT の応答テキストをコピーしました。');
        } catch {
            toastError('クリップボードへのコピーに失敗しました。');
        }
    };
    // 目的: 部品登録画面のstart Chat GPTAuto Fillを扱う。機能: 入力値を検証・整形し、画面または計算処理へ渡す。入力: 関数シグネチャの値。出力: 処理結果またはなし。動作条件: 部品登録画面の初期化後に呼び出す。副作用: Vue状態、localStorage、DOM、HTTP通信のいずれかを更新する場合がある。
    const startChatGPTAutoFill = async () => {
        logChatGptFlow('autofill.start', {
            datasheetCount: datasheetFiles.value.length,
            selectedIndex: datasheetTargetIndex.value,
            selectedName: selectedDatasheetFile.value?.name || '',
            helperConnected: chatGptJob.connected,
            helperVersion: chatGptJob.helperVersion || '(unknown)',
        });
        if (!datasheetFiles.value.length) {
            toastError('先にデータシートPDFを選択してください。');
            return;
        }
        if (!selectedDatasheetFile.value) {
            toastError('解析対象のPDFを選択してください。');
            return;
        }
        if (!ensureChatGptHelperReady()) {
            if (!chatGptJob.connected) {
                openChatGptGuide('Tampermonkey helper が未接続です。userscript を有効化してから再試行してください。');
                return;
            }
            openChatGptGuide(`Tampermonkey helper が古いです。userscript を更新してください。必要: v${CHATGPT_HELPER_MIN_VERSION}+ / 現在: v${chatGptJob.helperVersion || '不明'}`);
            return;
        }
        const workerReady = await waitForChatGptWorkerReady({ openWindowIfNeeded: true });
        if (!workerReady) {
            const message = 'ChatGPT タブの受信準備を確認できませんでした。ChatGPT タブを開いたまま、もう一度「ChatGPTで自動入力」を実行してください。';
            logChatGptFlow('worker.ready.failed', {
                message,
                workerHeartbeat: chatGptWorkerHeartbeat.value,
            });
            updateChatGptJobState('failed', message, message);
            openChatGptGuide(message);
            toastError(message);
            return;
        }
        try {
            logChatGptFlow('autofill.cleanup.previous.start', {
                previousJobId: chatGptJob.jobId || '',
                tempCount: chatGptTempDatasheets.value.length,
            });
            await clearRemoteChatGptState(chatGptJob.jobId || '');
            await clearChatGptTempDatasheets();
            helperResult.value = null;
            showHelperResultModal.value = false;
            updateChatGptJobState('preparing', '解析ジョブを準備しています。');
            logChatGptFlow('autofill.cleanup.previous.done', {
                previousJobId: chatGptJob.jobId || '',
            });
            const fd = new FormData();
            datasheetFiles.value.forEach((file, index) => {
                fd.append(`datasheets[${index}]`, file);
                fd.append(`datasheet_labels[${index}]`, datasheetLabels.value[index] ?? '');
            });
            fd.append('target_index', String(datasheetTargetIndex.value));
            logChatGptFlow('autofill.job.create.request', {
                datasheetCount: datasheetFiles.value.length,
                targetIndex: datasheetTargetIndex.value,
            });
            const response = await withTimeout(
                api.upload('/component-helper/chatgpt-jobs', fd, {
                    transport: 'xhr',
                    timeoutMs: CHATGPT_JOB_CREATE_TIMEOUT_MS,
                    timeoutMessage: 'ChatGPT 解析ジョブの作成がタイムアウトしました。ジョブを破棄してリセットしてから再試行してください。',
                    onEvent: (type, detail = {}) => {
                        logChatGptFlow(`autofill.job.create.xhr.${type}`, detail);
                    },
                }),
                CHATGPT_JOB_CREATE_TIMEOUT_MS + 1000,
                'ChatGPT 解析ジョブの作成がタイムアウトしました。ジョブを破棄してリセットしてから再試行してください。'
            );
            const job = response.data ?? {};
            logChatGptFlow('autofill.job.created', {
                jobId: job.job_id || '',
                datasheetCount: Array.isArray(job.datasheets) ? job.datasheets.length : 0,
                targetName: job.target_datasheet?.original_name || '',
            });
            chatGptTempDatasheets.value = Array.isArray(job.datasheets) ? job.datasheets : [];
            chatGptJob.jobId = job.job_id ?? '';
            persistActiveChatGptJob({
                jobId: chatGptJob.jobId,
                startedAt: new Date().toISOString(),
                datasheetLabel: datasheetTargetLabel.value,
            });
            updateChatGptJobState('opening', 'ChatGPTタブを起動しています。');
            await queueChatGptJob(job);
        } catch (e) {
            console.error('[BitsKeep][ChatGPT Flow] autofill.failed', e);
            logChatGptFlow('autofill.failed', {
                message: e.message || 'ChatGPT 自動解析ジョブの作成に失敗しました。',
                code: e.code || '',
                status: e.status || '',
            });
            await clearRemoteChatGptState('');
            updateChatGptJobState('failed', e.message || 'ChatGPT 自動解析ジョブの作成に失敗しました。', e.message || '');
            openChatGptGuide(e.message || 'ChatGPT 自動解析ジョブの作成に失敗しました。');
            toastError(e.message ?? 'ChatGPT 自動解析ジョブの作成に失敗しました。');
        }
    };
    /**
     * ChatGPT helperとの同期を開始する。入力は不要で、window名、sessionStorage復元、イベント監視、定期ポーリングを設定する。
     * 出力はなく、画面状態・タイマー・トースト表示を更新する副作用を持つ。
     */
    // 目的: 部品登録画面のinitialize Chat Gpt Bridgeを扱う。機能: 入力値を検証・整形し、画面または計算処理へ渡す。入力: 関数シグネチャの値。出力: 処理結果またはなし。動作条件: 部品登録画面の初期化後に呼び出す。副作用: Vue状態、localStorage、DOM、HTTP通信のいずれかを更新する場合がある。
    const initializeChatGptBridge = () => {
        syncTampermonkeyConnection();
        window.name = BITSKEEP_WINDOW_NAME;
        const persistedChatGptJob = readPersistedActiveChatGptJob();
        if (persistedChatGptJob?.jobId) {
            chatGptJob.jobId = persistedChatGptJob.jobId;
            updateChatGptJobState(
                'waiting',
                persistedChatGptJob.datasheetLabel
                    ? `前回の ChatGPT 解析状態を確認しています。対象: ${persistedChatGptJob.datasheetLabel}`
                    : '前回の ChatGPT 解析状態を確認しています。',
                '',
                persistedChatGptJob.startedAt || ''
            );
            showChatGptRunModal.value = true;
        }
        const shouldRecheckChatGptHelper = window.sessionStorage.getItem(CHATGPT_HELPER_RECHECK_STORAGE_KEY) === '1';
        if (shouldRecheckChatGptHelper) {
            window.sessionStorage.removeItem(CHATGPT_HELPER_RECHECK_STORAGE_KEY);
            handleChatGptHelperReloadRecheck();
        } else if (chatGptJob.connected && isChatGptHelperVersionCompatible()) {
            toastSuccess(`Tampermonkey helper v${chatGptJob.helperVersion} を検出しました。ChatGPT自動入力を使えます。`);
        }
        window.addEventListener('bitskeep-chatgpt-status', handleChatGptStatusEvent);
        window.addEventListener('bitskeep-chatgpt-result', handleChatGptResultEvent);
        void syncStoredChatGptBridgeState();
        void syncChatGptWorkerHeartbeat();
        setChatGptRuntime('tampermonkeyPollTimer', window.setInterval(() => {
            syncTampermonkeyConnection();
            void syncStoredChatGptBridgeState();
            void syncChatGptWorkerHeartbeat();
        }, 1500));
        setChatGptRuntime('chatGptJobWatchdogTimer', window.setInterval(maybeExpireChatGptJobState, 3000));
    };
    /**
     * ChatGPT helperとの同期を停止する。入力と戻り値はなく、イベント監視とタイマーを解除する。
     * 画面離脱時に古いポーリングやモーダルロックが残らないようDOM状態も戻す。
     */
    // 目的: 部品登録画面のcleanup Chat Gpt Bridgeを扱う。機能: 入力値を検証・整形し、画面または計算処理へ渡す。入力: 関数シグネチャの値。出力: 処理結果またはなし。動作条件: 部品登録画面の初期化後に呼び出す。副作用: Vue状態、localStorage、DOM、HTTP通信のいずれかを更新する場合がある。
    const cleanupChatGptBridge = () => {
        window.removeEventListener('bitskeep-chatgpt-status', handleChatGptStatusEvent);
        window.removeEventListener('bitskeep-chatgpt-result', handleChatGptResultEvent);
        const tampermonkeyPollTimer = getChatGptRuntime('tampermonkeyPollTimer');
        if (tampermonkeyPollTimer) {
            window.clearInterval(tampermonkeyPollTimer);
            setChatGptRuntime('tampermonkeyPollTimer', null);
        }
        const chatGptHelperPromptTimer = getChatGptRuntime('chatGptHelperPromptTimer');
        if (chatGptHelperPromptTimer) {
            window.clearTimeout(chatGptHelperPromptTimer);
            setChatGptRuntime('chatGptHelperPromptTimer', null);
        }
        const chatGptJobWatchdogTimer = getChatGptRuntime('chatGptJobWatchdogTimer');
        if (chatGptJobWatchdogTimer) {
            window.clearInterval(chatGptJobWatchdogTimer);
            setChatGptRuntime('chatGptJobWatchdogTimer', null);
        }
        document.documentElement.classList.remove('modal-open');
        document.body.classList.remove('modal-open');
    };
    return {
        selectedDatasheetFile, datasheetTargetLabel, hasDatasheetForAi, showChatGPTPaste, chatGPTPasteText, chatGPTPasteTextarea, navigationGuardActive,
        chatGptStatusLabel, chatGptStatusChips, chatGptStepStates, canStartChatGptAutoFill, chatGptHelperIssue, showChatGptRunHint,
        isChatGptHelperVersionCompatible, syncTampermonkeyConnection, syncStoredChatGptBridgeState, syncChatGptWorkerHeartbeat,
        openChatGptHelperUpdateModal, closeChatGptHelperUpdateModal, reloadForChatGptHelperUpdate, handleChatGptHelperReloadRecheck,
        hardResetChatGptJob, clearChatGptTempDatasheets, beginAiAction, confirmDatasheetTargetSelection, openChatGptRun, closeChatGptRun,
        openChatGPTPaste, parseChatGPTResult, dismissChatGPTPaste, openPasteFallbackFromGuide, copyChatGptFallbackText,
        startChatGPTAutoFill, chatGptGuideReason, chatGptJob, chatGptStatusChips, chatGptStepStates, canDismissChatGptRun,
        isChatGptJobBusy, anyModalOpen, showChatGptRunModal, showChatGptHelperUpdateModal, chatGptHelperCheckStatus, chatGptHelperCheckMessage,
        canStartChatGptAutoFill, initializeChatGptBridge, cleanupChatGptBridge, maybeExpireChatGptJobState, chatGptWorkerHeartbeat,
    };
}
