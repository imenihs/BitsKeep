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

    onMounted(() => {
        fetchStatus();
        fetchGeminiStatus();
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
    };
}
