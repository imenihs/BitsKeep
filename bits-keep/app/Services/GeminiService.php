<?php

namespace App\Services;

use App\Services\Datasheet\DatasheetResultNormalizer;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * Gemini API との通信を担う。
 * Files API でPDFをアップロードし、generateContent で構造化抽出を行う。
 */
class GeminiService
{
    private const BASE_URL = 'https://generativelanguage.googleapis.com';

    /**
     * 先頭を主モデル、後続を provider 側の廃止や未対応時のフォールバックとする。
     * 2026-04 時点で 1.5 系は shutdown 済みのため使わない。
     */
    private const MODELS = [
        'gemini-2.5-flash',
        'gemini-2.5-flash-lite',
    ];

    private const RETRYABLE_STATUSES = [429, 500, 502, 503, 504];

    private const GENERATE_RETRY_COUNT = 2;

    private const GENERATE_RETRY_DELAY_USEC = 800000;

    // PDF最大サイズ: 15MB
    private const MAX_PDF_BYTES = 15 * 1024 * 1024;

    /**
     * 目的: Geminiサービスの依存オブジェクトを受け取り、後続処理で使える状態にする。
     * 機能: 呼び出し元から受けた値を検証または整形し、対象処理へ渡す。
     * 入力: 関数シグネチャで指定された引数。
     * 出力: 型宣言または呼び出し規約に従う処理結果。
     * 動作条件: 呼び出し元が必要な依存オブジェクトと入力値を渡すこと。
     * 副作用: 依存オブジェクト、DB、ファイル、外部API、モデル状態のいずれかを更新する場合がある。
     */
    public function __construct(
        private AppSettingService $settings,
        private DatasheetPromptService $promptService,
        private DatasheetResultNormalizer $normalizer,
    ) {}

    /**
     * 目的: Geminiのgetapikeyを担う。
     * 機能: ドメイン入力を正規化し、外部API、DB、計算処理のいずれかへ橋渡しする。
     * 入力: なし。
     * 出力: ?stringで表される値。
     * 動作条件: 呼び出し元が必要な依存オブジェクトと正規化前の入力値を渡すこと。
     * 副作用: DB、外部API、ファイル、ログのいずれかを操作する場合がある。
     */
    public function getApiKey(): ?string
    {
        $fromDb = $this->settings->get('gemini.api_key');
        $fromEnv = config('services.gemini.api_key') ?: env('GEMINI_API_KEY');

        return $fromDb ?: $fromEnv ?: null;
    }

    /**
     * 目的: Geminiのisconfiguredを担う。
     * 機能: ドメイン入力を正規化し、外部API、DB、計算処理のいずれかへ橋渡しする。
     * 入力: なし。
     * 出力: boolで表される値。
     * 動作条件: 呼び出し元が必要な依存オブジェクトと正規化前の入力値を渡すこと。
     * 副作用: DB、外部API、ファイル、ログのいずれかを操作する場合がある。
     */
    public function isConfigured(): bool
    {
        return ! empty($this->getApiKey());
    }

    /**
     * 目的: Geminiのuploadfileを担う。
     * 機能: ドメイン入力を正規化し、外部API、DB、計算処理のいずれかへ橋渡しする。
     * 入力: $localPath, $mimeType。
     * 出力: stringで表される値。
     * 動作条件: 呼び出し元が必要な依存オブジェクトと正規化前の入力値を渡すこと。
     * 副作用: DB、外部API、ファイル、ログのいずれかを操作する場合がある。
     *
     * @param  string  $localPath  サーバ上のPDF絶対パス
     * @return string fileUri（generateContent の parts.fileData.fileUri に使う）
     */
    public function uploadFile(string $localPath, string $mimeType = 'application/pdf'): string
    {
        $key = $this->getApiKey();
        $fileSize = filesize($localPath);

        if ($fileSize > self::MAX_PDF_BYTES) {
            throw new \InvalidArgumentException('PDFファイルが15MBを超えています。');
        }

        // Files API: resumable upload
        $displayName = basename($localPath);
        $initRes = Http::withHeaders([
            'X-Goog-Upload-Protocol' => 'resumable',
            'X-Goog-Upload-Command' => 'start',
            'X-Goog-Upload-Header-Content-Length' => $fileSize,
            'X-Goog-Upload-Header-Content-Type' => $mimeType,
            'Content-Type' => 'application/json',
        ])
            ->post(self::BASE_URL."/upload/v1beta/files?key={$key}", [
                'file' => ['display_name' => $displayName],
            ]);

        if (! $initRes->successful()) {
            Log::error('Gemini Files API init failed', [
                'status' => $initRes->status(),
                'body' => $initRes->body(),
            ]);
            throw new \RuntimeException(
                $this->buildGeminiErrorMessage($initRes->status(), 'ファイルアップロードの初期化')
            );
        }

        $uploadUrl = $initRes->header('X-Goog-Upload-URL');
        if (! $uploadUrl) {
            throw new \RuntimeException('Gemini Files API からアップロードURLを取得できませんでした。');
        }

        // ファイル本体をアップロード
        $uploadRes = Http::withHeaders([
            'Content-Length' => $fileSize,
            'X-Goog-Upload-Offset' => 0,
            'X-Goog-Upload-Command' => 'upload, finalize',
        ])->withBody(fopen($localPath, 'r'), $mimeType)->post($uploadUrl);

        if (! $uploadRes->successful()) {
            Log::error('Gemini file upload failed', [
                'status' => $uploadRes->status(),
                'body' => $uploadRes->body(),
            ]);
            throw new \RuntimeException(
                $this->buildGeminiErrorMessage($uploadRes->status(), 'ファイルアップロード')
            );
        }

        $fileUri = $uploadRes->json('file.uri');
        if (! $fileUri) {
            throw new \RuntimeException('Gemini からファイル URI を取得できませんでした。');
        }

        return $fileUri;
    }

    /**
     * 目的: Geminiのanalyzeデータシートを担う。
     * 機能: ドメイン入力を正規化し、外部API、DB、計算処理のいずれかへ橋渡しする。
     * 入力: $fileUri。
     * 出力: arrayで表される値。
     * 動作条件: 呼び出し元が必要な依存オブジェクトと正規化前の入力値を渡すこと。
     * 副作用: DB、外部API、ファイル、ログのいずれかを操作する場合がある。
     *
     * @param  string  $fileUri  uploadFile() が返した URI
     * @return array{
     */
    public function analyzeDatasheet(string $fileUri): array
    {
        $key = $this->getApiKey();
        $prompt = $this->promptService->getPromptText();
        $lastStatus = null;
        $finalErrorMessage = null;

        foreach (self::MODELS as $model) {
            for ($attempt = 1; $attempt <= self::GENERATE_RETRY_COUNT; $attempt++) {
                $res = Http::timeout(90)->post(
                    self::BASE_URL.'/v1beta/models/'.$model.":generateContent?key={$key}",
                    [
                        'contents' => [[
                            'parts' => [
                                ['file_data' => ['mime_type' => 'application/pdf', 'file_uri' => $fileUri]],
                                ['text' => $prompt],
                            ],
                        ]],
                        'generationConfig' => [
                            'response_mime_type' => 'application/json',
                        ],
                    ]
                );

                if (! $res->successful()) {
                    $lastStatus = $res->status();
                    $failureMessage = $this->buildGeminiErrorMessage($lastStatus, 'データシート解析');

                    Log::warning('Gemini generateContent failed for model', [
                        'model' => $model,
                        'attempt' => $attempt,
                        'status' => $lastStatus,
                        'body' => $res->body(),
                    ]);

                    // モデル廃止/未対応なら次の候補を試す
                    if ($lastStatus === 404) {
                        if ($finalErrorMessage === null) {
                            $finalErrorMessage = $failureMessage;
                        }

                        continue 2;
                    }

                    // 一時障害なら少し待って同モデルを1回だけ再試行し、だめなら次モデルへ逃がす
                    if (in_array($lastStatus, self::RETRYABLE_STATUSES, true)) {
                        $finalErrorMessage = $failureMessage;

                        if ($attempt < self::GENERATE_RETRY_COUNT) {
                            usleep(self::GENERATE_RETRY_DELAY_USEC);

                            continue;
                        }

                        continue 2;
                    }

                    throw new \RuntimeException($failureMessage);
                }

                // candidates[0].content.parts[0].text がJSON文字列
                $text = $res->json('candidates.0.content.parts.0.text') ?? '';
                // response_mime_type=application/json の場合はそのままdecode可
                $parsed = json_decode($text, true);

                if (! is_array($parsed)) {
                    Log::warning('Gemini response parse failed', ['model' => $model, 'text' => $text]);
                    throw new \RuntimeException('Gemini の解析結果をパースできませんでした。');
                }

                return $this->normalizeResult($parsed);
            }
        }

        throw new \RuntimeException(
            $finalErrorMessage ?? $this->buildGeminiErrorMessage($lastStatus, 'データシート解析')
        );
    }

    /**
     * 目的: Gemini が返した生JSONを正規化済み解析結果へ変換する。
     * 機能: 共通の正規化処理へ委譲する。
     * 入力: $raw は Gemini の応答をデコードした配列。
     * 出力: 正規化済み解析結果。
     * 動作条件: $raw がデコード済みの配列であること。
     * 副作用: なし。
     *
     * @param  array<string, mixed>  $raw
     * @return array<string, mixed>
     */
    private function normalizeResult(array $raw): array
    {
        // 正規化は解析エンジン間で同一でなければならないため、共通処理へ寄せる
        return $this->normalizer->normalize($raw);
    }

    /**
     * 目的: Geminiの生成geminierrormessageを担う。
     * 機能: ドメイン入力を正規化し、外部API、DB、計算処理のいずれかへ橋渡しする。
     * 入力: $status, $action。
     * 出力: stringで表される値。
     * 動作条件: 呼び出し元が必要な依存オブジェクトと正規化前の入力値を渡すこと。
     * 副作用: なし。
     */
    private function buildGeminiErrorMessage(?int $status, string $action): string
    {
        return match ($status) {
            404 => 'Gemini の利用可能モデルが見つかりません。連携設定を確認してください。',
            429 => 'Gemini 側の利用上限に達しました。少し時間を置いて再試行してください。',
            503 => 'Gemini 側が混雑しています。少し時間を置いて再試行してください。',
            500, 502, 504 => 'Gemini 側で一時的な障害が発生しています。少し時間を置いて再試行してください。',
            default => "Gemini の{$action}に失敗しました。",
        };
    }
}
