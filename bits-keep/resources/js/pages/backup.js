import { ref } from 'vue';
import { api } from '../api.js';
import { useToast } from '../composables/useToast.js';
import { useConfirmModal } from '../composables/useConfirmModal.js';

// 目的: 画面モジュールのsetupを扱う。機能: 入力値を検証・整形し、画面または計算処理へ渡す。入力: 関数シグネチャの値。出力: 処理結果またはなし。動作条件: 画面モジュールの初期化後に呼び出す。副作用: Vue状態、localStorage、DOM、HTTP通信のいずれかを更新する場合がある。
export default function setup() {
    const { toasts, toastSuccess, toastError } = useToast();
    const { ask } = useConfirmModal();

    const downloading   = ref(false);
    const downloadError = ref('');
    const selectedFile  = ref(null);
    const restoring     = ref(false);
    const restoreResult = ref(null);

    // 目的: 画面モジュールのformat Sizeを扱う。機能: 入力値を検証・整形し、画面または計算処理へ渡す。入力: 関数シグネチャの値。出力: 表示値、配列、オブジェクト、数値のいずれか。動作条件: 画面モジュールの初期化後に呼び出す。副作用: なし。
    const formatSize = (bytes) => {
        if (bytes < 1024) return bytes + ' B';
        if (bytes < 1024 * 1024) return (bytes / 1024).toFixed(1) + ' KB';
        return (bytes / 1024 / 1024).toFixed(1) + ' MB';
    };

    // 目的: 画面モジュールのdownload Backupを扱う。機能: 入力値を検証・整形し、画面または計算処理へ渡す。入力: 関数シグネチャの値。出力: 処理結果またはなし。動作条件: 画面モジュールの初期化後に呼び出す。副作用: Vue状態、localStorage、DOM、HTTP通信のいずれかを更新する場合がある。
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

    // 目的: 画面モジュールのon File Changeを扱う。機能: 入力値を検証・整形し、画面または計算処理へ渡す。入力: 関数シグネチャの値。出力: 処理結果またはなし。動作条件: 画面モジュールの初期化後に呼び出す。副作用: Vue状態、localStorage、DOM、HTTP通信のいずれかを更新する場合がある。
    const onFileChange = (e) => {
        selectedFile.value = e.target.files[0] ?? null;
        restoreResult.value = null;
    };

    // 目的: 画面モジュールのstart Restoreを扱う。機能: 入力値を検証・整形し、画面または計算処理へ渡す。入力: 関数シグネチャの値。出力: 処理結果またはなし。動作条件: 画面モジュールの初期化後に呼び出す。副作用: Vue状態、localStorage、DOM、HTTP通信のいずれかを更新する場合がある。
    const startRestore = async () => {
        if (!selectedFile.value) return;
        if (!await ask('現在の登録データを、選択したバックアップファイルの内容で置き換えます。\nこの操作は元に戻せません。復元しますか？')) return;

        restoring.value = true;
        restoreResult.value = null;
        try {
            const form = new FormData();
            form.append('file', selectedFile.value);
            const r = await api.upload('/backup/restore', form);
            restoreResult.value = { ok: true, message: r.message ?? '復元が完了しました' };
            toastSuccess('復元が完了しました');
        } catch (e) {
            restoreResult.value = { ok: false, message: e.message ?? '復元に失敗しました' };
            toastError(e.message ?? '復元に失敗しました');
        } finally {
            restoring.value = false;
        }
    };

    return { toasts, downloading, downloadError, selectedFile, restoring, restoreResult, formatSize, downloadBackup, onFileChange, startRestore };
}
