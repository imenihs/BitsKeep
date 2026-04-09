import { ref, onMounted } from 'vue';
import { api } from '../api.js';
import { useNavigationConfirm } from '../composables/useNavigationConfirm.js';

export default function setup() {
    const appEl = document.getElementById('app');
    const canEdit = appEl?.dataset?.canEdit === '1';
    const loading = ref(true);
    const saving = ref(false);
    useNavigationConfirm(saving, '保存処理中です。このまま画面を離れてもよいですか？');
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
        } catch (e) {
            statusError.value = e.message ?? 'Notion設定状態の取得に失敗しました。';
        } finally {
            loading.value = false;
        }
    };

    const save = async () => {
        if (!canEdit) {
            saveError.value = 'このアカウントには連携設定を変更する権限がありません。editor 以上でログインしてください。';
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
            saveMessage.value = r.message || '保存しました';
        } catch (e) {
            saveError.value = e.message;
        } finally {
            saving.value = false;
        }
    };

    const clearToken = async () => {
        if (!canEdit) {
            saveError.value = 'このアカウントには連携設定を変更する権限がありません。editor 以上でログインしてください。';
            return;
        }
        if (!notion.value.token_configured || !confirm('保存済みの Notion API トークンを削除しますか？')) return;

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
        } catch (e) {
            saveError.value = e.message;
        } finally {
            deletingToken.value = false;
        }
    };

    const clearRootPage = async () => {
        if (!canEdit) {
            saveError.value = 'このアカウントには連携設定を変更する権限がありません。editor 以上でログインしてください。';
            return;
        }
        if (!notion.value.root_page_configured || !confirm('保存済みのルートページ URL を削除しますか？')) return;

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
            saveMessage.value = '保存済みルートページ URL を削除しました';
        } catch (e) {
            saveError.value = e.message;
        } finally {
            deletingRootPage.value = false;
        }
    };

    onMounted(() => {
        fetchStatus();
    });

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
    };
}
