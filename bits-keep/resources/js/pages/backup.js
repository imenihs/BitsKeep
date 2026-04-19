import { ref } from 'vue';
import { api } from '../api.js';
import { useToast } from '../composables/useToast.js';

export default function setup() {
    const { toasts, toastSuccess, toastError } = useToast();

    const downloading   = ref(false);
    const downloadError = ref('');
    const selectedFile  = ref(null);
    const restoring     = ref(false);
    const restoreResult = ref(null);

    const formatSize = (bytes) => {
        if (bytes < 1024) return bytes + ' B';
        if (bytes < 1024 * 1024) return (bytes / 1024).toFixed(1) + ' KB';
        return (bytes / 1024 / 1024).toFixed(1) + ' MB';
    };

    const downloadBackup = async () => {
        downloading.value = true;
        downloadError.value = '';
        try {
            // ストリームレスポンスなので fetch で直接扱う
            const csrfToken = document.querySelector('meta[name="csrf-token"]')?.content ?? '';
            const res = await fetch('/api/backup/download', {
                headers: { 'X-CSRF-TOKEN': csrfToken },
                credentials: 'same-origin',
            });
            if (!res.ok) {
                const json = await res.json().catch(() => ({}));
                throw new Error(json.message ?? 'ダウンロードに失敗しました');
            }
            const blob = await res.blob();
            const disposition = res.headers.get('Content-Disposition') ?? '';
            const match = disposition.match(/filename="([^"]+)"/);
            const filename = match ? match[1] : 'bitskeep_backup.sql.gz';
            const url = URL.createObjectURL(blob);
            const a = document.createElement('a');
            a.href = url;
            a.download = filename;
            a.click();
            URL.revokeObjectURL(url);
            toastSuccess('ダウンロードしました');
        } catch (e) {
            downloadError.value = e.message;
            toastError(e.message);
        } finally {
            downloading.value = false;
        }
    };

    const onFileChange = (e) => {
        selectedFile.value = e.target.files[0] ?? null;
        restoreResult.value = null;
    };

    const startRestore = async () => {
        if (!selectedFile.value) return;
        if (!confirm('現在のDBデータが上書きされます。本当に書き戻しますか？')) return;

        restoring.value = true;
        restoreResult.value = null;
        try {
            const form = new FormData();
            form.append('file', selectedFile.value);
            const r = await api.upload('/backup/restore', form);
            restoreResult.value = { ok: true, message: r.message ?? 'リストアが完了しました' };
            toastSuccess('リストアが完了しました');
        } catch (e) {
            restoreResult.value = { ok: false, message: e.message ?? 'リストアに失敗しました' };
            toastError(e.message ?? 'リストアに失敗しました');
        } finally {
            restoring.value = false;
        }
    };

    return { toasts, downloading, downloadError, selectedFile, restoring, restoreResult, formatSize, downloadBackup, onFileChange, startRestore };
}
