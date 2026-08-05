<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * データシート解析ジョブ1件の状態と結果。
 * 解析はキュー経由の非同期実行であり、画面をリロードしても状態を追跡できるよう
 * 進行状態と結果をこのテーブルへ持たせる。
 */
class DatasheetAnalysis extends Model
{
    // 待機中: キュー登録済みだがワーカーが未着手
    public const STATE_QUEUED = 'queued';

    // 準備中: PDFからテキストまたはページ画像を取り出している
    public const STATE_PREPARING = 'preparing';

    // 解析中: 解析エンジンの応答待ち
    public const STATE_RUNNING = 'running';

    // 完了: 正規化済み結果を保持している
    public const STATE_SUCCEEDED = 'succeeded';

    // 失敗: failure_kind と failure_message に理由を持つ
    public const STATE_FAILED = 'failed';

    /*
    | 中止: 利用者が自分の意思で止めた状態。
    | 失敗とは分けて扱う。利用者の操作どおりに終わっただけであり、
    | 「解析に失敗しました」と伝えるのは事実と異なるうえ、
    | 自分の操作を不具合と誤解させる。
    */
    public const STATE_CANCELED = 'canceled';

    // 未ログインまたはトークン失効。連携設定へ誘導する
    public const FAILURE_NOT_AUTHENTICATED = 'not_authenticated';

    // 実行時間の上限超過。再実行導線を出す
    public const FAILURE_TIMEOUT = 'timeout';

    // 出力がスキーマに適合しない。手貼り導線へ誘導する
    public const FAILURE_SCHEMA_MISMATCH = 'schema_mismatch';

    // PDFからテキストもページ画像も取り出せない。手貼り導線へ誘導する
    public const FAILURE_UNREADABLE_PDF = 'unreadable_pdf';

    // 利用枠の上限到達。時間を置いた再実行を促す
    public const FAILURE_QUOTA_EXCEEDED = 'quota_exceeded';

    // 実行環境の不備（コマンド不在など）。管理者向けの案内を出す
    public const FAILURE_ENVIRONMENT = 'environment';

    // 上記に当てはまらない失敗
    public const FAILURE_UNKNOWN = 'unknown';

    protected $fillable = [
        'public_id',
        'user_id',
        'temp_token',
        'engine',
        'state',
        'failure_kind',
        'failure_message',
        'input_mode',
        'input_page_count',
        'result',
        'started_at',
        'finished_at',
    ];

    protected $casts = [
        'result' => 'array',
        'input_page_count' => 'integer',
        'started_at' => 'datetime',
        'finished_at' => 'datetime',
    ];

    /**
     * 目的: DatasheetAnalysisからuserへのEloquentリレーションを返す。
     * 機能: 関連モデル取得用のクエリ定義をLaravelへ渡す。
     * 入力: なし。
     * 出力: BelongsTo リレーション。
     * 動作条件: 対象モデルインスタンスまたはEloquentクエリ上で呼び出すこと。
     * 副作用: なし。
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * 目的: 解析が終了済みかどうかを判定する。
     * 機能: state を完了・失敗・中止と比較する。
     * 入力: なし。
     * 出力: 終了済みなら true。
     * 動作条件: state が設定済みであること。
     * 副作用: なし。
     */
    public function isFinished(): bool
    {
        // 完了・失敗・中止のいずれもワーカー側の処理は終わっており、画面はポーリングを止めてよい
        return in_array($this->state, [self::STATE_SUCCEEDED, self::STATE_FAILED, self::STATE_CANCELED], true);
    }

    /**
     * 目的: 失敗種別から再実行を勧めてよいかを判定する。
     * 機能: 失敗種別を再実行で解消しうるものと突き合わせる。
     * 入力: なし。
     * 出力: 再実行を勧めてよいなら true。
     * 動作条件: 失敗時に呼び出すこと。
     * 副作用: なし。
     */
    public function isRetryable(): bool
    {
        // 中止は利用者の操作なので、同じPDFをそのまま解析し直せる
        if ($this->state === self::STATE_CANCELED) {
            return true;
        }

        // 上限超過と一時的な失敗は時間を置けば通る。未ログインや解析不能PDFは再実行しても同じ結果になる
        return in_array($this->failure_kind, [
            self::FAILURE_TIMEOUT,
            self::FAILURE_QUOTA_EXCEEDED,
            self::FAILURE_UNKNOWN,
        ], true);
    }
}
