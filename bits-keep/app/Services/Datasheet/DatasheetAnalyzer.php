<?php

namespace App\Services\Datasheet;

use App\Exceptions\DatasheetAnalysisException;

/**
 * データシート解析エンジンの共通口。
 * エンジンを差し替えても、解析結果の後段（スペック詳細照合、分類候補、テンプレート推薦）を
 * 分岐させないため、入出力の形をこの口へ揃える。
 */
interface DatasheetAnalyzer
{
    /**
     * 目的: エンジン識別子を返す。
     * 機能: 設定値や解析記録に保存する内部キーを返す。
     * 入力: なし。
     * 出力: claude や gemini といった識別子。
     * 動作条件: なし。
     * 副作用: なし。
     */
    public function key(): string;

    /**
     * 目的: 画面へ出す表示名を返す。
     * 機能: 連携設定の選択肢に出す名称を返す。
     * 入力: なし。
     * 出力: 表示名。
     * 動作条件: なし。
     * 副作用: なし。
     */
    public function label(): string;

    /**
     * 目的: このエンジンが今使える状態かを返す。
     * 機能: 認証や実行環境の前提を確認し、使えない場合は理由を添える。
     * 入力: なし。
     * 出力: available と message、および画面表示用の詳細を持つ配列。
     * 動作条件: なし。
     * 副作用: 外部コマンドやファイルの存在確認を行う場合がある。
     *
     * @return array<string, mixed>
     */
    public function checkAvailability(): array;

    /**
     * 目的: データシートPDFを解析し、正規化済みの結果を返す。
     * 機能: PDFをエンジンへ渡し、返った内容を正規化済み解析結果へ変換する。
     * 入力: $pdfPath は対象PDFの絶対パス、$onPhase は進行状態を通知する呼び出し先。
     * 出力: 正規化済み解析結果と、入力の渡し方を持つ DatasheetAnalysisOutcome。
     * 動作条件: $pdfPath が存在し、checkAvailability() が available であること。
     * 副作用: 外部API呼び出しまたは外部プロセス実行、作業ファイルの生成と削除を行う。
     *
     * @param  callable(string): void|null  $onPhase  preparing / running を受け取る
     *
     * @throws DatasheetAnalysisException 解析に失敗した場合
     */
    public function analyze(string $pdfPath, ?callable $onPhase = null): DatasheetAnalysisOutcome;
}
