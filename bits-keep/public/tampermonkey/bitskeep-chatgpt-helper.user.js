// ==UserScript==
// @name         BitsKeep ChatGPT Helper
// @namespace    https://bits-keep.rwc.0t0.jp/
// @version      0.1.0
// @description  BitsKeep のデータシート解析を ChatGPT Web と連携して自動化します
// @match        https://bits-keep.rwc.0t0.jp/*
// @match        http://bits-keep.rwc.0t0.jp/*
// @match        https://chatgpt.com/*
// @grant        GM_setValue
// @grant        GM_getValue
// @grant        GM_addValueChangeListener
// @grant        GM_openInTab
// @grant        GM_xmlhttpRequest
// @grant        unsafeWindow
// @connect      bits-keep.rwc.0t0.jp
// ==/UserScript==

(function () {
    'use strict';

    const JOB_KEY = 'bitskeep_chatgpt_job_v1';
    const STATUS_KEY = 'bitskeep_chatgpt_status_v1';
    const RESULT_KEY = 'bitskeep_chatgpt_result_v1';

    const sleep = (ms) => new Promise((resolve) => setTimeout(resolve, ms));
    const parseValue = (raw) => {
        if (!raw || typeof raw !== 'string') return null;
        try {
            return JSON.parse(raw);
        } catch {
            return null;
        }
    };

    const setStatus = async (payload) => {
        await GM_setValue(STATUS_KEY, JSON.stringify({
            ...payload,
            updatedAt: new Date().toISOString(),
        }));
    };

    const setResult = async (payload) => {
        await GM_setValue(RESULT_KEY, JSON.stringify({
            ...payload,
            updatedAt: new Date().toISOString(),
        }));
    };

    const dispatchPageEvent = (eventName, detail) => {
        window.dispatchEvent(new CustomEvent(eventName, { detail }));
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

    const findComposer = () => {
        return document.querySelector('#prompt-textarea')
            || document.querySelector('textarea[data-id]')
            || document.querySelector('textarea')
            || document.querySelector('div[contenteditable="true"][id="prompt-textarea"]')
            || document.querySelector('div[contenteditable="true"]');
    };

    const findSendButton = () => {
        return document.querySelector('button[data-testid*="send"]')
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

    const attachPdfToChatGpt = async (file) => {
        let input = findFileInput();
        if (!input) {
            findAttachButton()?.click();
            input = await waitFor(findFileInput, 5000, 200);
        }
        if (!input) {
            throw new Error('ChatGPT のファイル入力欄を見つけられませんでした。DOM変更の可能性があります。');
        }

        const dt = new DataTransfer();
        dt.items.add(file);
        input.files = dt.files;
        input.dispatchEvent(new Event('change', { bubbles: true }));
    };

    const submitPrompt = async () => {
        const button = await waitFor(findSendButton, 5000, 200);
        if (button) {
            button.click();
            return;
        }

        const composer = findComposer();
        if (composer instanceof HTMLTextAreaElement) {
            composer.dispatchEvent(new KeyboardEvent('keydown', { key: 'Enter', bubbles: true }));
            return;
        }

        throw new Error('ChatGPT の送信ボタンを見つけられませんでした。');
    };

    const latestAssistantMessage = () => {
        const byRole = document.querySelectorAll('[data-message-author-role="assistant"]');
        if (byRole.length > 0) {
            return byRole[byRole.length - 1];
        }

        const articles = document.querySelectorAll('main article');
        return articles.length > 0 ? articles[articles.length - 1] : null;
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
        let lastText = '';
        let stableSince = Date.now();
        const startedAt = Date.now();

        while (Date.now() - startedAt < 180000) {
            const message = latestAssistantMessage();
            if (message) {
                const currentText = message.innerText?.trim() ?? '';
                if (currentText && currentText !== lastText) {
                    lastText = currentText;
                    stableSince = Date.now();
                }

                const stopButton = document.querySelector('button[aria-label*="Stop"], button[data-testid*="stop"]');
                if (currentText && !stopButton && Date.now() - stableSince > 4000) {
                    return extractJsonCandidate(message);
                }
            }

            await sleep(1500);
        }

        throw new Error('ChatGPT の応答待機がタイムアウトしました。');
    };

    const downloadPdfBlob = (url) => new Promise((resolve, reject) => {
        GM_xmlhttpRequest({
            method: 'GET',
            url,
            responseType: 'blob',
            onload: (response) => {
                if (response.status >= 200 && response.status < 300 && response.response) {
                    resolve(response.response);
                    return;
                }
                reject(new Error(`PDF取得に失敗しました (${response.status})`));
            },
            onerror: () => reject(new Error('PDF取得中に通信エラーが発生しました。')),
        });
    });

    const runChatGptJob = async (job) => {
        if (!job?.job_id || !job?.target_datasheet?.signed_download_url) return;

        if (isLoginRequired()) {
            await setStatus({
                jobId: job.job_id,
                status: 'login_required',
                message: 'ChatGPT へログインしてください。',
            });
            return;
        }

        const composer = await waitFor(findComposer, 15000, 300);
        if (!composer) {
            await setStatus({
                jobId: job.job_id,
                status: 'failed',
                message: 'ChatGPT 入力欄を見つけられませんでした。DOM変更の可能性があります。',
            });
            return;
        }

        try {
            await setStatus({ jobId: job.job_id, status: 'downloading_pdf', message: '解析対象PDFを取得しています。' });
            const blob = await downloadPdfBlob(job.target_datasheet.signed_download_url);
            const file = new File([blob], job.target_datasheet.original_name || 'datasheet.pdf', { type: 'application/pdf' });

            await setStatus({ jobId: job.job_id, status: 'attaching_pdf', message: 'PDFを添付しています。' });
            await attachPdfToChatGpt(file);

            fillComposer(composer, job.prompt_text || '');
            await sleep(400);

            await setStatus({ jobId: job.job_id, status: 'submitting', message: 'プロンプトとPDFを送信しています。' });
            await submitPrompt();

            await setStatus({ jobId: job.job_id, status: 'waiting_response', message: 'ChatGPT の応答を待っています。' });
            const result = await waitForAssistantResponse();

            await setStatus({ jobId: job.job_id, status: 'result_ready', message: '結果を取得しました。' });
            await setResult({
                jobId: job.job_id,
                jsonText: result.jsonText,
                rawText: result.rawText,
            });
        } catch (error) {
            await setStatus({
                jobId: job.job_id,
                status: 'failed',
                message: error instanceof Error ? error.message : '自動解析に失敗しました。',
            });
        }
    };

    const initBitsKeepBridge = () => {
        unsafeWindow.__bitskeepTampermonkeyHelper = {
            connected: true,
            version: '0.1.0',
        };

        window.addEventListener('bitskeep-chatgpt-start', async (event) => {
            const job = event.detail;
            if (!job?.job_id) return;

            await GM_setValue(RESULT_KEY, '');
            await GM_setValue(JOB_KEY, JSON.stringify({
                ...job,
                requestedAt: new Date().toISOString(),
            }));
            await setStatus({
                jobId: job.job_id,
                status: 'queued',
                message: 'ChatGPT タブを起動しています。',
            });
            GM_openInTab('https://chatgpt.com/', { active: true, insert: true, setParent: true });
        });

        GM_addValueChangeListener(STATUS_KEY, (_, __, nextValue) => {
            const detail = parseValue(nextValue);
            if (detail) {
                dispatchPageEvent('bitskeep-chatgpt-status', detail);
            }
        });

        GM_addValueChangeListener(RESULT_KEY, (_, __, nextValue) => {
            const detail = parseValue(nextValue);
            if (detail && (detail.jsonText || detail.rawText)) {
                dispatchPageEvent('bitskeep-chatgpt-result', detail);
            }
        });
    };

    const initChatGptBridge = () => {
        let runningJobId = null;

        const handleRawJob = async (rawValue) => {
            const job = parseValue(rawValue);
            if (!job?.job_id || runningJobId === job.job_id) return;

            runningJobId = job.job_id;
            await runChatGptJob(job);
            runningJobId = null;
        };

        GM_addValueChangeListener(JOB_KEY, (_, __, nextValue) => {
            void handleRawJob(nextValue);
        });

        GM_getValue(JOB_KEY, '').then((value) => {
            if (value) {
                void handleRawJob(value);
            }
        });
    };

    if (location.host.includes('bits-keep.rwc.0t0.jp')) {
        initBitsKeepBridge();
    } else if (location.host === 'chatgpt.com') {
        initChatGptBridge();
    }
})();
