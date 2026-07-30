<?php

namespace App\Services\Datasheet;

use App\Exceptions\DatasheetAnalysisException;
use App\Services\GeminiService;

/**
 * Gemini API へ PDF を直接渡してデータシートを解析する。
 * PDF をそのまま受け取れるため前処理を持たない。
 */
class GeminiDatasheetAnalyzer implements DatasheetAnalyzer
{
    /**
     * 目的: 解析に必要な依存オブジェクトを受け取る。
     * 機能: Gemini 通信サービスを保持する。
     * 入力: $gemini は Gemini 通信サービス。
     * 出力: インスタンス。
     * 動作条件: なし。
     * 副作用: なし。
     */
    public function __construct(
        private GeminiService $gemini,
    ) {}

    /**
     * 目的: エンジン識別子を返す。
     * 機能: 設定値と解析記録で使う内部キーを返す。
     * 入力: なし。
     * 出力: gemini。
     * 動作条件: なし。
     * 副作用: なし。
     */
    public function key(): string
    {
        return 'gemini';
    }

    /**
     * 目的: 画面へ出す表示名を返す。
     * 機能: 連携設定の選択肢に出す名称を返す。
     * 入力: なし。
     * 出力: 表示名。
     * 動作条件: なし。
     * 副作用: なし。
     */
    public function label(): string
    {
        return 'Gemini 解析';
    }

    /**
     * 目的: Gemini が使える状態かを返す。
     * 機能: APIキーの設定有無を確認する。
     * 入力: なし。
     * 出力: available と message を持つ配列。
     * 動作条件: なし。
     * 副作用: DBまたは設定値を参照する。
     *
     * @return array<string, mixed>
     */
    public function checkAvailability(): array
    {
        $configured = $this->gemini->isConfigured();

        return [
            'available' => $configured,
            'message' => $configured ? null : 'Gemini APIキーが設定されていません。連携設定から登録してください。',
            'authenticated' => $configured,
        ];
    }

    /**
     * 目的: データシートPDFを Gemini で解析し、正規化済みの結果を返す。
     * 機能: PDFをアップロードして構造化抽出を行う。
     * 入力: $pdfPath は対象PDFの絶対パス、$onPhase は進行状態の通知先。
     * 出力: 正規化済み結果を持つ DatasheetAnalysisOutcome。
     * 動作条件: APIキーが設定済みで、$pdfPath が存在すること。
     * 副作用: 外部APIへPDFを送信する。
     *
     * @throws DatasheetAnalysisException 解析に失敗した場合
     */
    public function analyze(string $pdfPath, ?callable $onPhase = null): DatasheetAnalysisOutcome
    {
        if (! is_file($pdfPath)) {
            throw DatasheetAnalysisException::unreadablePdf('解析対象のPDFが見つかりませんでした。もう一度アップロードしてください。');
        }

        $availability = $this->checkAvailability();
        if (! $availability['available']) {
            throw DatasheetAnalysisException::notAuthenticated((string) $availability['message']);
        }

        try {
            // アップロードと解析はどちらも待ち時間があるため、まとめて解析中として見せる
            if ($onPhase !== null) {
                $onPhase('preparing');
            }
            $fileUri = $this->gemini->uploadFile($pdfPath);

            if ($onPhase !== null) {
                $onPhase('running');
            }

            // GeminiService 側で正規化済みの形まで揃えて返る
            return new DatasheetAnalysisOutcome($this->gemini->analyzeDatasheet($fileUri));
        } catch (\InvalidArgumentException $e) {
            // ファイルサイズ超過など、入力自体が条件を満たしていない場合
            throw DatasheetAnalysisException::unreadablePdf($e->getMessage());
        } catch (\RuntimeException $e) {
            throw $this->classifyRuntimeFailure($e);
        }
    }

    /**
     * 目的: Gemini 側の失敗を失敗種別へ振り分ける。
     * 機能: 利用上限起因かどうかを文面から判定する。
     * 入力: $e は GeminiService が投げた例外。
     * 出力: 失敗種別を持つ例外。
     * 動作条件: なし。
     * 副作用: なし。
     */
    private function classifyRuntimeFailure(\RuntimeException $e): DatasheetAnalysisException
    {
        $message = $e->getMessage();

        // 上限到達は時間を置けば通るため、恒久的な失敗と分けて再実行を促す
        if (str_contains($message, '利用上限')) {
            return DatasheetAnalysisException::quotaExceeded($message);
        }

        return DatasheetAnalysisException::unknown($message, $e);
    }
}
