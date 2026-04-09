import { ref, onMounted } from 'vue';
import { api } from '../api.js';

export default function setup() {
    const appEl = document.getElementById('app');
    const canEdit = appEl?.dataset?.canEdit === '1';
    const loading = ref(true);
    const saving = ref(false);
    const saveMessage = ref('');
    const saveError = ref('');
    const statusError = ref('');
    const notion = ref({
        configured: false,
        token_configured: false,
        root_page_configured: false,
        missing: [],
        root_page_url: '',
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
            notion.value = r.data?.data ?? notion.value;
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
            notion.value = r.data?.data ?? notion.value;
            form.value.api_token = '';
            form.value.root_page_url = notion.value.root_page_url ?? '';
            saveMessage.value = r.message || '保存しました';
        } catch (e) {
            saveError.value = e.message;
        } finally {
            saving.value = false;
        }
    };

    onMounted(fetchStatus);

    return {
        loading,
        saving,
        notion,
        form,
        canEdit,
        save,
        saveMessage,
        saveError,
        statusError,
        fetchStatus,
    };
}
