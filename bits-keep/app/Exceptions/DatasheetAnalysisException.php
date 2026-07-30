<?php

namespace App\Exceptions;

use App\Models\DatasheetAnalysis;
use RuntimeException;

/**
 * データシート解析の失敗を、利用者へ見せる案内と対処導線が決まる粒度で表す。
 * 失敗理由を文字列比較で推測する処理を各所へ散らさないため、
 * 失敗種別をこの例外に持たせて呼び出し元へ渡す。
 */
class DatasheetAnalysisException extends RuntimeException
{
    /**
     * 目的: 失敗種別と利用者向けメッセージを保持した例外を組み立てる。
     * 機能: 失敗種別を受け取り、RuntimeException として保持する。
     * 入力: $kind は DatasheetAnalysis の FAILURE_* 定数、$message は利用者向け文面。
     * 出力: 例外インスタンス。
     * 動作条件: $kind が DatasheetAnalysis の FAILURE_* のいずれかであること。
     * 副作用: なし。
     */
    public function __construct(
        private string $kind,
        string $message,
        ?\Throwable $previous = null,
    ) {
        parent::__construct($message, 0, $previous);
    }

    /**
     * 目的: 失敗種別を返す。
     * 機能: 保持している失敗種別をそのまま返す。
     * 入力: なし。
     * 出力: DatasheetAnalysis の FAILURE_* 定数。
     * 動作条件: なし。
     * 副作用: なし。
     */
    public function kind(): string
    {
        return $this->kind;
    }

    /**
     * 目的: 未ログインまたはトークン失効の失敗を作る。
     * 機能: 連携設定へ誘導する文面を添えて例外を生成する。
     * 入力: $message は上書きしたい文面。省略時は既定文面。
     * 出力: 例外インスタンス。
     * 動作条件: なし。
     * 副作用: なし。
     */
    public static function notAuthenticated(?string $message = null): self
    {
        return new self(
            DatasheetAnalysis::FAILURE_NOT_AUTHENTICATED,
            $message ?? '解析エンジンにログインできていません。連携設定から再ログインしてください。'
        );
    }

    /**
     * 目的: 実行時間の上限超過を表す失敗を作る。
     * 機能: 再実行を促す文面を添えて例外を生成する。
     * 入力: $seconds は超過した上限秒数。
     * 出力: 例外インスタンス。
     * 動作条件: なし。
     * 副作用: なし。
     */
    public static function timedOut(int $seconds): self
    {
        return new self(
            DatasheetAnalysis::FAILURE_TIMEOUT,
            "解析が制限時間{$seconds}秒を超えたため中断しました。もう一度実行してください。"
        );
    }

    /**
     * 目的: 出力がスキーマに適合しない失敗を作る。
     * 機能: 手貼り導線へ誘導する文面を添えて例外を生成する。
     * 入力: $message は上書きしたい文面。省略時は既定文面。
     * 出力: 例外インスタンス。
     * 動作条件: なし。
     * 副作用: なし。
     */
    public static function schemaMismatch(?string $message = null): self
    {
        return new self(
            DatasheetAnalysis::FAILURE_SCHEMA_MISMATCH,
            $message ?? '解析結果が想定した形式になりませんでした。貼り付け入力をお試しください。'
        );
    }

    /**
     * 目的: PDFから解析入力を取り出せない失敗を作る。
     * 機能: 手貼り導線へ誘導する文面を添えて例外を生成する。
     * 入力: $message は上書きしたい文面。省略時は既定文面。
     * 出力: 例外インスタンス。
     * 動作条件: なし。
     * 副作用: なし。
     */
    public static function unreadablePdf(?string $message = null): self
    {
        return new self(
            DatasheetAnalysis::FAILURE_UNREADABLE_PDF,
            $message ?? 'このPDFからは文字もページ画像も取り出せませんでした。貼り付け入力をお試しください。'
        );
    }

    /**
     * 目的: 利用枠の上限到達を表す失敗を作る。
     * 機能: 時間を置いた再実行を促す文面を添えて例外を生成する。
     * 入力: $message は上書きしたい文面。省略時は既定文面。
     * 出力: 例外インスタンス。
     * 動作条件: なし。
     * 副作用: なし。
     */
    public static function quotaExceeded(?string $message = null): self
    {
        return new self(
            DatasheetAnalysis::FAILURE_QUOTA_EXCEEDED,
            $message ?? '解析エンジンの利用上限に達しました。時間を置いて再実行してください。'
        );
    }

    /**
     * 目的: 実行環境の不備を表す失敗を作る。
     * 機能: 管理者が対処すべき内容を添えて例外を生成する。
     * 入力: $message は不足している前提を示す文面。
     * 出力: 例外インスタンス。
     * 動作条件: なし。
     * 副作用: なし。
     */
    public static function environment(string $message): self
    {
        return new self(DatasheetAnalysis::FAILURE_ENVIRONMENT, $message);
    }

    /**
     * 目的: 分類できない失敗を作る。
     * 機能: 受け取った文面のまま例外を生成する。
     * 入力: $message は利用者向け文面、$previous は元例外。
     * 出力: 例外インスタンス。
     * 動作条件: なし。
     * 副作用: なし。
     */
    public static function unknown(string $message, ?\Throwable $previous = null): self
    {
        return new self(DatasheetAnalysis::FAILURE_UNKNOWN, $message, $previous);
    }
}
