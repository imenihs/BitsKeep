import { ref, computed, onBeforeUnmount } from 'vue';
import { api } from '../../api.js';

// 進行状態を問い合わせる間隔（ミリ秒）。解析は数十秒から数分かかるため、短くしても意味がない
const POLL_INTERVAL_MS = 2000;

// 解析IDの保存先。リロードしても進行中の解析へ復帰できるようにする
const STORAGE_KEY = 'bitskeep_datasheet_analysis_v1';

// 進行状態ごとの画面表示文。解析エンジン名は出さない。利用者にとって重要なのは今どの段階かだけ
const STATE_LABELS = {
    queued: '解析の順番を待っています',
    preparing: 'データシートを読み取り用に変換しています',
    running: 'データシートを解析しています',
};

/**
 * 目的: サーバ側で実行するデータシート解析の開始、進行監視、結果受け取りを扱う。
 * 機能: PDFを解析APIへ渡して解析IDを受け取り、完了まで進行状態を問い合わせ、完了時に候補確認へ渡す。
 * 入力: ctx は選択中PDF、解析候補の組み立て関数、モーダル状態、通知関数を持つ親のコンテキスト。
 * 出力: 画面が使う状態と操作関数。
 * 動作条件: 部品登録画面の setup 内で呼び出すこと。
 * 副作用: HTTP通信、localStorage、タイマー、親のモーダル状態と通知を更新する。
 */
export function useServerDatasheetAnalysis(ctx) {
    const {
        selectedDatasheetFile,
        helperResult,
        showHelperResultModal,
        showDatasheetManagerModal,
        buildHelperResult,
        hasHelperCandidates,
        toastSuccess,
        toastError,
    } = ctx;

    // 進行中の解析ID。null なら解析していない
    const analysisId = ref('');
    // 直近に取得した進行状態。queued / preparing / running / succeeded / failed
    const analysisState = ref('');
    // 失敗時の利用者向け文面
    const failureMessage = ref('');
    // 失敗種別。連携設定へ誘導するか、再実行を勧めるかの判断に使う
    const failureKind = ref('');
    // 再実行を勧めてよい失敗かどうか
    const retryable = ref(false);
    // 解析開始要求の送信中フラグ。二重送信を防ぐ
    const starting = ref(false);

    let pollTimer = null;

    // 解析が動いている間は部品登録画面の操作を制限し、進行状態を出す
    const isAnalyzing = computed(() => {
        return starting.value || ['queued', 'preparing', 'running'].includes(analysisState.value);
    });

    // 進行状態の表示文。未知の状態でも空にせず、解析中として見せる
    const progressLabel = computed(() => {
        if (starting.value) return 'データシートを送信しています';
        return STATE_LABELS[analysisState.value] || '解析しています';
    });

    // 失敗が未ログイン起因なら、再実行ではなく連携設定へ誘導する
    const needsEngineSetup = computed(() => {
        return ['not_authenticated', 'environment'].includes(failureKind.value);
    });

    // 解析不能PDFや形式不一致は、貼り付け入力へ逃がすほうが早い
    const suggestPasteFallback = computed(() => {
        return ['unreadable_pdf', 'schema_mismatch'].includes(failureKind.value);
    });

    /**
     * 目的: 進行状態の問い合わせタイマーを止める。
     * 機能: 動作中のタイマーを解除する。
     * 入力: なし。
     * 出力: なし。
     * 動作条件: なし。
     * 副作用: タイマーを解除する。
     */
    const stopPolling = () => {
        if (pollTimer !== null) {
            clearTimeout(pollTimer);
            pollTimer = null;
        }
    };

    /**
     * 目的: 解析状態をすべて初期化する。
     * 機能: 状態、失敗理由、保存済み解析IDを消し、タイマーを止める。
     * 入力: なし。
     * 出力: なし。
     * 動作条件: なし。
     * 副作用: localStorage を消し、タイマーを解除する。
     */
    const resetAnalysis = () => {
        stopPolling();
        analysisId.value = '';
        analysisState.value = '';
        failureMessage.value = '';
        failureKind.value = '';
        retryable.value = false;
        starting.value = false;
        try {
            window.localStorage.removeItem(STORAGE_KEY);
        } catch {
            // localStorage が使えない環境でも解析自体は動くため、保存失敗は無視する
        }
    };

    /**
     * 目的: 進行中の解析IDを保存する。
     * 機能: リロード後に復帰できるよう localStorage へ書く。
     * 入力: $id は解析ID。
     * 出力: なし。
     * 動作条件: なし。
     * 副作用: localStorage を更新する。
     */
    const persistAnalysisId = (id) => {
        try {
            window.localStorage.setItem(STORAGE_KEY, id);
        } catch {
            // 保存できなくても解析は継続する。復帰できないだけで機能は失われない
        }
    };

    /**
     * 目的: 完了した解析結果を候補確認へ渡す。
     * 機能: 解析結果を候補の形へ組み立て、候補があれば確認モーダルを開く。
     * 入力: $result は解析APIが返した結果。
     * 出力: なし。
     * 動作条件: 解析が完了していること。
     * 副作用: 親の候補状態とモーダル状態、通知を更新する。
     */
    const acceptResult = (result) => {
        const built = buildHelperResult(result);
        // 候補が空のまま確認モーダルを開くと、利用者は何も選べない画面を見せられる
        if (!hasHelperCandidates(built)) {
            failureMessage.value = '解析できましたが、登録に使える候補が見つかりませんでした。貼り付け入力をお試しください。';
            failureKind.value = 'schema_mismatch';
            retryable.value = false;
            analysisState.value = 'failed';
            return;
        }

        helperResult.value = built;
        showHelperResultModal.value = true;
        showDatasheetManagerModal.value = false;
        toastSuccess('解析候補を取得しました。内容を確認してください');
        resetAnalysis();
    };

    /**
     * 目的: 解析の進行状態を1回問い合わせる。
     * 機能: 状態を取得し、完了なら結果を受け取り、未完了なら次回問い合わせを予約する。
     * 入力: なし。
     * 出力: なし。
     * 動作条件: analysisId が設定済みであること。
     * 副作用: HTTP通信を行い、状態とタイマーを更新する。
     */
    const pollOnce = async () => {
        if (!analysisId.value) return;

        let payload = null;
        try {
            const res = await api.get(`/datasheet-analyses/${analysisId.value}`);
            payload = res.data ?? res;
        } catch (e) {
            // 通信の一時失敗で解析を失ったことにしない。次回問い合わせで回復を試みる
            pollTimer = setTimeout(pollOnce, POLL_INTERVAL_MS);
            return;
        }

        analysisState.value = payload.state ?? '';

        if (payload.state === 'succeeded') {
            stopPolling();
            acceptResult(payload.result ?? {});
            return;
        }

        if (payload.state === 'failed') {
            stopPolling();
            failureMessage.value = payload.failure_message || '解析に失敗しました。';
            failureKind.value = payload.failure_kind || '';
            retryable.value = Boolean(payload.retryable);
            try {
                window.localStorage.removeItem(STORAGE_KEY);
            } catch {
                // 保存の後始末に失敗しても、失敗表示そのものには影響しない
            }
            return;
        }

        pollTimer = setTimeout(pollOnce, POLL_INTERVAL_MS);
    };

    /**
     * 目的: 選択中のPDFで解析を開始する。
     * 機能: PDFを解析APIへ送り、解析IDを受け取って進行監視を始める。
     * 入力: なし。選択中PDFは親の状態から取る。
     * 出力: なし。
     * 動作条件: データシートPDFが選択済みであること。
     * 副作用: HTTP通信、localStorage、タイマー、通知を更新する。
     */
    const startAnalysis = async () => {
        const targetFile = selectedDatasheetFile.value;
        if (!targetFile) {
            toastError('先にデータシートPDFを選択してください');
            return;
        }
        // 解析中の再押下でジョブを二重に積むと、利用枠を無駄に消費する
        if (isAnalyzing.value) return;

        resetAnalysis();
        starting.value = true;

        try {
            const fd = new FormData();
            fd.append('pdf', targetFile);
            // multipart の送信は fetch 経路が不安定な環境があるため、XHR 経路を使う
            const res = await api.upload('/datasheet-analyses', fd, { transport: 'xhr' });
            const payload = res.data ?? res;

            analysisId.value = payload.analysis_id ?? '';
            analysisState.value = payload.state ?? 'queued';
            if (!analysisId.value) {
                throw new Error('解析を開始できませんでした。もう一度お試しください。');
            }

            persistAnalysisId(analysisId.value);
            showDatasheetManagerModal.value = false;
            pollTimer = setTimeout(pollOnce, POLL_INTERVAL_MS);
        } catch (e) {
            analysisState.value = 'failed';
            failureMessage.value = e.message ?? '解析を開始できませんでした。';
            // 開始できなかった理由は API 側の判定に依存するため、種別は付けずに再実行を勧める
            failureKind.value = '';
            retryable.value = true;
        } finally {
            starting.value = false;
        }
    };

    /**
     * 目的: 進行中の解析を中止する。
     * 機能: 解析の破棄をサーバへ伝え、画面の解析状態を初期化する。
     * 入力: なし。
     * 出力: なし。
     * 動作条件: なし。中止対象が無い場合も安全に呼べる。
     * 副作用: HTTP通信を行い、状態とタイマーを初期化する。
     */
    const cancelAnalysis = async () => {
        const id = analysisId.value;
        resetAnalysis();
        if (!id) return;

        try {
            await api.delete(`/datasheet-analyses/${id}`);
        } catch {
            // 破棄が通らなくても画面側は解放する。ワーカー側は上限時間で必ず終了する
        }
    };

    /**
     * 目的: リロード後に進行中の解析へ復帰する。
     * 機能: 保存済み解析IDがあれば状態を取得し、未完了なら進行監視を再開する。
     * 入力: なし。
     * 出力: なし。
     * 動作条件: 部品登録画面の初期化時に呼び出すこと。
     * 副作用: HTTP通信、状態とタイマーを更新する。
     */
    const resumeAnalysis = async () => {
        let saved = '';
        try {
            saved = window.localStorage.getItem(STORAGE_KEY) || '';
        } catch {
            saved = '';
        }
        if (!saved) return;

        analysisId.value = saved;
        // 復帰時は待たずに1回問い合わせる。既に完了していれば結果をそのまま受け取れる
        await pollOnce();
    };

    // 画面を離れるときにタイマーを残すと、破棄済みの状態を更新しようとして例外になる
    onBeforeUnmount(() => {
        stopPolling();
    });

    return {
        analysisId, analysisState, failureMessage, failureKind, retryable,
        isAnalyzing, progressLabel, needsEngineSetup, suggestPasteFallback,
        startAnalysis, cancelAnalysis, resumeAnalysis, resetAnalysis,
    };
}
