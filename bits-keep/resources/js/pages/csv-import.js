/**
 * CSVインポートページ（SCR-013）
 * 4ステップウィザード: アップロード → プレビュー → 確認 → 完了
 */
import { ref, reactive, computed } from 'vue';
import { api } from '../api.js';
import { useToast } from '../composables/useToast.js';

// 目的: 画面モジュールのsetupを扱う。機能: 入力値を検証・整形し、画面または計算処理へ渡す。入力: 関数シグネチャの値。出力: 処理結果またはなし。動作条件: 画面モジュールの初期化後に呼び出す。副作用: Vue状態、localStorage、DOM、HTTP通信のいずれかを更新する場合がある。
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
    // 目的: 画面モジュールのon File Changeを扱う。機能: 入力値を検証・整形し、画面または計算処理へ渡す。入力: 関数シグネチャの値。出力: 処理結果またはなし。動作条件: 画面モジュールの初期化後に呼び出す。副作用: Vue状態、localStorage、DOM、HTTP通信のいずれかを更新する場合がある。
    const onFileChange = (e) => {
        selectedFile.value = e.target.files[0] ?? null;
    };

    // 目的: 画面モジュールのupload Previewを扱う。機能: 入力値を検証・整形し、画面または計算処理へ渡す。入力: 関数シグネチャの値。出力: 処理結果またはなし。動作条件: 画面モジュールの初期化後に呼び出す。副作用: Vue状態、localStorage、DOM、HTTP通信のいずれかを更新する場合がある。
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
    // 目的: 画面モジュールのgo Confirmを扱う。機能: 入力値を検証・整形し、画面または計算処理へ渡す。入力: 関数シグネチャの値。出力: 処理結果またはなし。動作条件: 画面モジュールの初期化後に呼び出す。副作用: Vue状態、localStorage、DOM、HTTP通信のいずれかを更新する場合がある。
    const goConfirm = () => {
        if (preview.rows.length === 0) { toastError('インポート可能な行がありません'); return; }
        step.value = 3;
    };

    // ── Step3: コミット ──────────────────────────────────────
    // 目的: 画面モジュールのcommit Importを扱う。機能: 入力値を検証・整形し、画面または計算処理へ渡す。入力: 関数シグネチャの値。出力: 処理結果またはなし。動作条件: 画面モジュールの初期化後に呼び出す。副作用: Vue状態、localStorage、DOM、HTTP通信のいずれかを更新する場合がある。
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
    // 目的: 画面モジュールのresetを扱う。機能: 入力値を検証・整形し、画面または計算処理へ渡す。入力: 関数シグネチャの値。出力: 処理結果またはなし。動作条件: 画面モジュールの初期化後に呼び出す。副作用: Vue状態、localStorage、DOM、HTTP通信のいずれかを更新する場合がある。
    const reset = () => {
        step.value = 1;
        selectedFile.value = null;
        if (fileInput.value) fileInput.value.value = '';
        Object.assign(preview, { headers: [], rows: [], errors: [], total: 0 });
        Object.assign(result, { created: 0, skipped: [] });
    };

    // 目的: 画面モジュールのprocurement Labelを扱う。機能: 入力値を検証・整形し、画面または計算処理へ渡す。入力: 関数シグネチャの値。出力: 処理結果またはなし。動作条件: 画面モジュールの初期化後に呼び出す。副作用: Vue状態、localStorage、DOM、HTTP通信のいずれかを更新する場合がある。
    const procurementLabel = (v) => ({ active: '入手可', nrnd: 'NRND', eol: 'EOL', custom: 'カスタム' }[v] ?? v);
    const csvHeaderLabels = {
        part_number: '型番',
        common_name: '通称',
        manufacturer: 'メーカー',
        description: '説明',
        procurement_status: '調達状態',
        quantity_new: '新品在庫数',
        quantity_used: '中古在庫数',
        category_names: '分類名',
        package_name: 'パッケージ名',
        location_code: '棚コード',
        supplier_name: '商社名',
        supplier_part_number: '商社型番',
        unit_price: '単価',
        product_url: '商品URL',
    };
    // 目的: 画面モジュールのcsv Header Labelを扱う。機能: 入力値を検証・整形し、画面または計算処理へ渡す。入力: 関数シグネチャの値。出力: 処理結果またはなし。動作条件: 画面モジュールの初期化後に呼び出す。副作用: Vue状態、localStorage、DOM、HTTP通信のいずれかを更新する場合がある。
    const csvHeaderLabel = (header) => csvHeaderLabels[header] ?? header;
    const stepCards = computed(() => ([
        {
            key: 'file',
            label: 'ファイル',
            value: selectedFile.value ? selectedFile.value.name : '未選択',
            state: selectedFile.value ? 'ok' : 'pending',
        },
        {
            key: 'rows',
            label: '登録可能',
            value: `${preview.rows.length} 行`,
            state: preview.rows.length > 0 ? 'ok' : (step.value > 1 ? 'warning' : 'pending'),
        },
        {
            key: 'errors',
            label: 'エラー',
            value: `${preview.errors.length} 件`,
            state: preview.errors.length > 0 ? 'danger' : (step.value > 1 ? 'ok' : 'pending'),
        },
        {
            key: 'commit',
            label: '実行',
            value: step.value === 4 ? `${result.created} 件登録` : '未実行',
            state: step.value === 4 ? 'ok' : 'pending',
        },
    ]));

    return { toasts, step, uploading, committing, fileInput, selectedFile,
             preview, result, onFileChange, uploadPreview, goConfirm, commitImport, reset,
             procurementLabel, csvHeaderLabel, stepCards };
}
