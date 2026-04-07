/**
 * CSVインポートページ（SCR-013）
 * 4ステップウィザード: アップロード → プレビュー → 確認 → 完了
 */
import { ref, reactive, computed } from 'vue';
import { api } from '../api.js';
import { useToast } from '../composables/useToast.js';

export default function setup() {
    const { toasts, toastSuccess, toastError } = useToast();

    const step = ref(1);   // 1:upload 2:preview 3:confirm 4:done
    const uploading   = ref(false);
    const committing  = ref(false);
    const fileInput   = ref(null);
    const selectedFile = ref(null);

    const preview = reactive({ headers: [], rows: [], errors: [], total: 0 });
    const result  = reactive({ created: 0, skipped: [] });

    // ── Step1: ファイル選択 ──────────────────────────────────
    const onFileChange = (e) => {
        selectedFile.value = e.target.files[0] ?? null;
    };

    const uploadPreview = async () => {
        if (!selectedFile.value) return;
        uploading.value = true;
        try {
            const formData = new FormData();
            formData.append('file', selectedFile.value);
            const r = await api.upload('/import/csv/preview', formData);
            Object.assign(preview, r.data);
            step.value = 2;
        } catch (e) { toastError('アップロードに失敗しました: ' + e.message); }
        finally { uploading.value = false; }
    };

    // ── Step2→3: 確認へ ──────────────────────────────────────
    const goConfirm = () => {
        if (preview.rows.length === 0) { toastError('インポート可能な行がありません'); return; }
        step.value = 3;
    };

    // ── Step3: コミット ──────────────────────────────────────
    const commitImport = async () => {
        committing.value = true;
        try {
            const r = await api.post('/import/csv/commit', { rows: preview.rows });
            Object.assign(result, r.data);
            toastSuccess(r.message ?? 'インポート完了');
            step.value = 4;
        } catch (e) { toastError('インポートに失敗しました: ' + e.message); }
        finally { committing.value = false; }
    };

    // ── リセット ─────────────────────────────────────────────
    const reset = () => {
        step.value = 1;
        selectedFile.value = null;
        if (fileInput.value) fileInput.value.value = '';
        Object.assign(preview, { headers: [], rows: [], errors: [], total: 0 });
        Object.assign(result, { created: 0, skipped: [] });
    };

    const procurementLabel = (v) => ({ active: '入手可', nrnd: 'NRND', eol: 'EOL', custom: 'カスタム' }[v] ?? v);

    return { toasts, step, uploading, committing, fileInput, selectedFile,
             preview, result, onFileChange, uploadPreview, goConfirm, commitImport, reset,
             procurementLabel };
}
