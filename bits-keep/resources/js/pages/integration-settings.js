import { ref, onMounted, watch } from 'vue';
import { api } from '../api.js';
import { useNavigationConfirm } from '../composables/useNavigationConfirm.js';
import { useConfirmModal } from '../composables/useConfirmModal.js';

// 目的: 画面モジュールのsetupを扱う。機能: 入力値を検証・整形し、画面または計算処理へ渡す。入力: 関数シグネチャの値。出力: 処理結果またはなし。動作条件: 画面モジュールの初期化後に呼び出す。副作用: Vue状態、localStorage、DOM、HTTP通信のいずれかを更新する場合がある。
export default function setup() {
    const appEl = document.getElementById('app');
    const canEdit = appEl?.dataset?.canEdit === '1';
    const loading = ref(true);
    const saving = ref(false);
    const dirty = ref(false);
    const { ask } = useConfirmModal();
    useNavigationConfirm(dirty, '未保存の変更があります。このまま画面を離れてもよいですか？');
    const deletingToken = ref(false);
    const deletingRootPage = ref(false);
    const saveMessage = ref('');
    const saveError = ref('');
    const statusError = ref('');
    const notion = ref({
        configured: false,
        token_configured: false,
        root_page_configured: false,
        missing: [],
        token_preview: '',
        root_page_url: '',
        health: null,
    });
    const form = ref({
        api_token: '',
        root_page_url: '',
    });
    const initialRootPageUrl = ref('');

    // 目的: 画面モジュールのfetch Statusを扱う。機能: 入力値を検証・整形し、画面または計算処理へ渡す。入力: 関数シグネチャの値。出力: 処理結果またはなし。動作条件: 画面モジュールの初期化後に呼び出す。副作用: Vue状態、localStorage、DOM、HTTP通信のいずれかを更新する場合がある。
    const fetchStatus = async () => {
        loading.value = true;
        statusError.value = '';
        try {
            const r = await api.get('/settings/integrations/notion');
            notion.value = r.data?.data ?? r.data ?? notion.value;
            form.value = {
                api_token: '',
                root_page_url: notion.value.root_page_url ?? '',
            };
            initialRootPageUrl.value = form.value.root_page_url;
            dirty.value = false;
        } catch (e) {
            statusError.value = e.message ?? 'Notion設定状態の取得に失敗しました。';
        } finally {
            loading.value = false;
        }
    };

    // 目的: 画面モジュールのsaveを扱う。機能: 入力値を検証・整形し、画面または計算処理へ渡す。入力: 関数シグネチャの値。出力: 処理結果またはなし。動作条件: 画面モジュールの初期化後に呼び出す。副作用: Vue状態、localStorage、DOM、HTTP通信のいずれかを更新する場合がある。
    const save = async () => {
        if (!canEdit) {
            saveError.value = 'このアカウントには連携設定を変更する権限がありません。編集者以上でログインしてください。';
            saveMessage.value = '';
            return;
        }

        saving.value = true;
        saveMessage.value = '';
        saveError.value = '';
        try {
            const r = await api.put('/settings/integrations/notion', form.value);
            notion.value = r.data?.data ?? r.data ?? notion.value;
            form.value = {
                api_token: '',
                root_page_url: notion.value.root_page_url ?? '',
            };
            initialRootPageUrl.value = form.value.root_page_url;
            saveMessage.value = r.message || '保存しました';
            dirty.value = false;
        } catch (e) {
            saveError.value = e.message;
        } finally {
            saving.value = false;
        }
    };

    // 目的: 画面モジュールのclear Tokenを扱う。機能: 入力値を検証・整形し、画面または計算処理へ渡す。入力: 関数シグネチャの値。出力: 処理結果またはなし。動作条件: 画面モジュールの初期化後に呼び出す。副作用: Vue状態、localStorage、DOM、HTTP通信のいずれかを更新する場合がある。
    const clearToken = async () => {
        if (!canEdit) {
            saveError.value = 'このアカウントには連携設定を変更する権限がありません。編集者以上でログインしてください。';
            return;
        }
        if (!notion.value.token_configured || !await ask('保存済みの Notion API トークンを削除しますか？')) return;

        deletingToken.value = true;
        saveMessage.value = '';
        saveError.value = '';
        try {
            const r = await api.put('/settings/integrations/notion', {
                api_token: '',
                root_page_url: notion.value.root_page_url ?? '',
                clear_api_token: true,
                clear_root_page_url: false,
            });
            notion.value = r.data?.data ?? r.data ?? notion.value;
            form.value.api_token = '';
            saveMessage.value = '保存済みトークンを削除しました';
            dirty.value = false;
        } catch (e) {
            saveError.value = e.message;
        } finally {
            deletingToken.value = false;
        }
    };

    // 目的: 画面モジュールのclear Root Pageを扱う。機能: 入力値を検証・整形し、画面または計算処理へ渡す。入力: 関数シグネチャの値。出力: 処理結果またはなし。動作条件: 画面モジュールの初期化後に呼び出す。副作用: Vue状態、localStorage、DOM、HTTP通信のいずれかを更新する場合がある。
    const clearRootPage = async () => {
        if (!canEdit) {
            saveError.value = 'このアカウントには連携設定を変更する権限がありません。編集者以上でログインしてください。';
            return;
        }
        if (!notion.value.root_page_configured || !await ask('保存済みのルートページ URL を削除しますか？')) return;

        deletingRootPage.value = true;
        saveMessage.value = '';
        saveError.value = '';
        try {
            const r = await api.put('/settings/integrations/notion', {
                api_token: '',
                root_page_url: '',
                clear_api_token: false,
                clear_root_page_url: true,
            });
            notion.value = r.data?.data ?? r.data ?? notion.value;
            form.value.root_page_url = '';
            initialRootPageUrl.value = '';
            saveMessage.value = '保存済みルートページ URL を削除しました';
            dirty.value = false;
        } catch (e) {
            saveError.value = e.message;
        } finally {
            deletingRootPage.value = false;
        }
    };

    // ── Gemini APIキー設定 ────────────────────────────────
    const gemini = ref({ configured: false, key_preview: null });
    const geminiForm = ref({ api_key: '' });
    const geminiSaving = ref(false);
    const geminiDeleting = ref(false);
    const geminiMessage = ref('');
    const geminiError = ref('');

    // 目的: 画面モジュールのfetch Gemini Statusを扱う。機能: 入力値を検証・整形し、画面または計算処理へ渡す。入力: 関数シグネチャの値。出力: 処理結果またはなし。動作条件: 画面モジュールの初期化後に呼び出す。副作用: Vue状態、localStorage、DOM、HTTP通信のいずれかを更新する場合がある。
    const fetchGeminiStatus = async () => {
        try {
            const r = await api.get('/settings/integrations/gemini');
            gemini.value = r.data?.data ?? r.data ?? gemini.value;
        } catch {
            // Gemini設定取得失敗は非致命的。サイレントで継続。
        }
    };

    // 目的: 画面モジュールのsave Geminiを扱う。機能: 入力値を検証・整形し、画面または計算処理へ渡す。入力: 関数シグネチャの値。出力: 処理結果またはなし。動作条件: 画面モジュールの初期化後に呼び出す。副作用: Vue状態、localStorage、DOM、HTTP通信のいずれかを更新する場合がある。
    const saveGemini = async () => {
        if (!canEdit) { geminiError.value = '編集者以上の権限が必要です。'; return; }
        geminiSaving.value = true;
        geminiMessage.value = '';
        geminiError.value = '';
        try {
            const r = await api.put('/settings/integrations/gemini', { api_key: geminiForm.value.api_key });
            gemini.value = r.data?.data ?? r.data ?? gemini.value;
            geminiForm.value.api_key = '';
            geminiMessage.value = r.message || 'APIキーを保存しました';
            dirty.value = false;
        } catch (e) { geminiError.value = e.message; }
        finally { geminiSaving.value = false; }
    };

    // 目的: 画面モジュールのclear Gemini Keyを扱う。機能: 入力値を検証・整形し、画面または計算処理へ渡す。入力: 関数シグネチャの値。出力: 処理結果またはなし。動作条件: 画面モジュールの初期化後に呼び出す。副作用: Vue状態、localStorage、DOM、HTTP通信のいずれかを更新する場合がある。
    const clearGeminiKey = async () => {
        if (!canEdit) { geminiError.value = '編集者以上の権限が必要です。'; return; }
        if (!gemini.value.configured || !await ask('保存済みの Gemini APIキーを削除しますか？')) return;
        geminiDeleting.value = true;
        geminiMessage.value = '';
        geminiError.value = '';
        try {
            const r = await api.put('/settings/integrations/gemini', { clear_api_key: true });
            gemini.value = r.data?.data ?? r.data ?? gemini.value;
            geminiMessage.value = 'APIキーを削除しました';
        } catch (e) { geminiError.value = e.message; }
        finally { geminiDeleting.value = false; }
    };

    // ── データシート解析の実行方式 ──────────────────────────
    // 解析方式は運用設定であり、利用者が部品登録画面で毎回選ぶものではない。
    // ここで1つ決め、部品登録画面の入口は1本に保つ
    const datasheetEngine = ref({ active: '', active_label: '', available: false, message: null, options: [] });
    const datasheetEngineSaving = ref(false);
    const datasheetEngineMessage = ref('');
    const datasheetEngineError = ref('');

    /**
     * 目的: データシート解析の実行方式と利用可否を取得する。
     * 機能: 設定APIから現在の方式、選択肢、利用できない場合の理由を読む。
     * 入力: なし。
     * 出力: なし。
     * 動作条件: 認証済みで画面が初期化済みであること。
     * 副作用: HTTP通信と画面状態を更新する。
     */
    const fetchDatasheetEngine = async () => {
        try {
            const r = await api.get('/settings/integrations/datasheet-engine');
            datasheetEngine.value = r.data?.data ?? r.data ?? datasheetEngine.value;
        } catch (e) {
            // 取得できないと現在の方式が分からず判断できないため、ここは理由を出す
            datasheetEngineError.value = e.message ?? '解析方式の状態を取得できませんでした。';
        }
    };

    /**
     * 目的: データシート解析の実行方式を変更する。
     * 機能: 選択された方式を保存し、保存後の利用可否を反映する。
     * 入力: $key は方式の識別子。
     * 出力: なし。
     * 動作条件: 編集者以上の権限を持つこと。
     * 副作用: HTTP通信と画面状態を更新する。
     */
    const saveDatasheetEngine = async (key) => {
        if (!canEdit) { datasheetEngineError.value = '編集者以上の権限が必要です。'; return; }
        if (!key || key === datasheetEngine.value.active) return;

        datasheetEngineSaving.value = true;
        datasheetEngineMessage.value = '';
        datasheetEngineError.value = '';
        try {
            const r = await api.put('/settings/integrations/datasheet-engine', { engine: key });
            datasheetEngine.value = r.data?.data ?? r.data ?? datasheetEngine.value;
            datasheetEngineMessage.value = r.message || '解析方式を保存しました';
        } catch (e) { datasheetEngineError.value = e.message; }
        finally { datasheetEngineSaving.value = false; }
    };

    onMounted(() => {
        fetchStatus();
        fetchGeminiStatus();
        fetchDatasheetEngine();
    });

    watch(form, (value) => {
        dirty.value = value.api_token.trim() !== '' || value.root_page_url !== initialRootPageUrl.value;
    }, { deep: true });

    watch(geminiForm, (value) => {
        if (value.api_key.trim() !== '') dirty.value = true;
    }, { deep: true });

    return {
        loading,
        saving,
        deletingToken,
        deletingRootPage,
        notion,
        form,
        canEdit,
        save,
        clearToken,
        clearRootPage,
        saveMessage,
        saveError,
        statusError,
        fetchStatus,
        gemini,
        geminiForm,
        geminiSaving,
        geminiDeleting,
        geminiMessage,
        geminiError,
        saveGemini,
        clearGeminiKey,
        datasheetEngine,
        datasheetEngineSaving,
        datasheetEngineMessage,
        datasheetEngineError,
        saveDatasheetEngine,
        fetchDatasheetEngine,
    };
}
