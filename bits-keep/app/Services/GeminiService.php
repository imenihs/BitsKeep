<?php

namespace App\Services;

use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * Gemini API との通信を担う。
 * Files API でPDFをアップロードし、generateContent で構造化抽出を行う。
 */
class GeminiService
{
    private const BASE_URL    = 'https://generativelanguage.googleapis.com';
    private const MODEL       = 'gemini-1.5-flash';
    // PDF最大サイズ: 15MB
    private const MAX_PDF_BYTES = 15 * 1024 * 1024;

    public function __construct(private AppSettingService $settings) {}

    /**
     * 設定UIまたは .env から API キーを取得する。
     * 設定UIの値が優先される。
     */
    public function getApiKey(): ?string
    {
        $fromDb  = $this->settings->get('gemini.api_key');
        $fromEnv = config('services.gemini.api_key') ?: env('GEMINI_API_KEY');
        return $fromDb ?: $fromEnv ?: null;
    }

    public function isConfigured(): bool
    {
        return ! empty($this->getApiKey());
    }

    /**
     * ローカルのPDFファイルを Gemini Files API へアップロードし、fileUri を返す。
     *
     * @param  string  $localPath  サーバ上のPDF絶対パス
     * @param  string  $mimeType
     * @return string  fileUri（generateContent の parts.fileData.fileUri に使う）
     */
    public function uploadFile(string $localPath, string $mimeType = 'application/pdf'): string
    {
        $key      = $this->getApiKey();
        $fileSize = filesize($localPath);

        if ($fileSize > self::MAX_PDF_BYTES) {
            throw new \InvalidArgumentException('PDFファイルが15MBを超えています。');
        }

        // Files API: resumable upload
        $displayName = basename($localPath);
        $initRes = Http::withHeaders([
            'X-Goog-Upload-Protocol' => 'resumable',
            'X-Goog-Upload-Command'  => 'start',
            'X-Goog-Upload-Header-Content-Length' => $fileSize,
            'X-Goog-Upload-Header-Content-Type'   => $mimeType,
            'Content-Type' => 'application/json',
        ])
        ->post(self::BASE_URL . "/upload/v1beta/files?key={$key}", [
            'file' => ['display_name' => $displayName],
        ]);

        if (! $initRes->successful()) {
            Log::error('Gemini Files API init failed', ['body' => $initRes->body()]);
            throw new \RuntimeException('Gemini Files API の初期化に失敗しました: ' . $initRes->body());
        }

        $uploadUrl = $initRes->header('X-Goog-Upload-URL');
        if (! $uploadUrl) {
            throw new \RuntimeException('Gemini Files API からアップロードURLを取得できませんでした。');
        }

        // ファイル本体をアップロード
        $uploadRes = Http::withHeaders([
            'Content-Length' => $fileSize,
            'X-Goog-Upload-Offset'  => 0,
            'X-Goog-Upload-Command' => 'upload, finalize',
        ])->withBody(fopen($localPath, 'r'), $mimeType)->post($uploadUrl);

        if (! $uploadRes->successful()) {
            Log::error('Gemini file upload failed', ['body' => $uploadRes->body()]);
            throw new \RuntimeException('Gemini へのファイルアップロードに失敗しました: ' . $uploadRes->body());
        }

        $fileUri = $uploadRes->json('file.uri');
        if (! $fileUri) {
            throw new \RuntimeException('Gemini からファイル URI を取得できませんでした。');
        }

        return $fileUri;
    }

    /**
     * アップロード済みPDFから電子部品情報を構造化抽出する。
     *
     * @param  string  $fileUri  uploadFile() が返した URI
     * @return array{
     *   part_number: ?string,
     *   manufacturer: ?string,
     *   common_name: ?string,
     *   description: ?string,
     *   specs: array<array{name: string, value: string, unit: string}>
     * }
     */
    public function analyzeDatasheet(string $fileUri): array
    {
        $key = $this->getApiKey();

        $prompt = <<<'PROMPT'
あなたは電子部品データシートの解析を専門とするアシスタントです。
添付のPDFデータシートから以下の情報をJSON形式で抽出してください。

抽出フォーマット（必ずこのJSONだけを返すこと。前置きや説明不要）:
{
  "part_number": "型番（例: BC547）",
  "manufacturer": "メーカー名",
  "common_name": "部品の一般的な通称・種別（例: NPN汎用トランジスタ、Nチャネルパワーモスfet）",
  "description": "部品の概要説明（1〜3文）",
  "specs": [
    { "name": "スペック名", "value": "値", "unit": "単位" }
  ]
}

specsには以下を含めてください（取得できる限り全て）:
- 絶対最大定格（最大電圧・最大電流・最大電力・動作温度など）
- 電気的特性（hFE・VCE(sat)・RDS(on)・Vf・Vth・IDS(max)・遮断周波数など）
- 動作条件（供給電圧範囲・入力電圧範囲など）

注意:
- 値は数値のみ（単位をvalueに含めない）
- 条件付き値は「Vce=5V時のIcは...」のように nameに条件を含めてよい
- 不明な項目は null にする
- specs が存在しない場合は空配列 [] にする
PROMPT;

        $res = Http::timeout(60)->post(
            self::BASE_URL . "/v1beta/models/" . self::MODEL . ":generateContent?key={$key}",
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
            Log::error('Gemini generateContent failed', ['status' => $res->status(), 'body' => $res->body()]);
            throw new \RuntimeException('Gemini の解析APIでエラーが発生しました: ' . $res->body());
        }

        // candidates[0].content.parts[0].text がJSON文字列
        $text = $res->json('candidates.0.content.parts.0.text') ?? '';
        // response_mime_type=application/json の場合はそのままdecode可
        $parsed = json_decode($text, true);

        if (! is_array($parsed)) {
            Log::warning('Gemini response parse failed', ['text' => $text]);
            throw new \RuntimeException('Gemini の解析結果をパースできませんでした。');
        }

        return $this->normalizeResult($parsed);
    }

    /**
     * 抽出結果を統一フォーマットに正規化する。
     */
    private function normalizeResult(array $raw): array
    {
        $specs = [];
        foreach ($raw['specs'] ?? [] as $item) {
            if (empty($item['name'])) continue;
            $specs[] = [
                'name'  => (string) ($item['name'] ?? ''),
                'value' => (string) ($item['value'] ?? ''),
                'unit'  => (string) ($item['unit'] ?? ''),
            ];
        }

        return [
            'part_number'  => $raw['part_number']  ? trim((string) $raw['part_number'])  : null,
            'manufacturer' => $raw['manufacturer']  ? trim((string) $raw['manufacturer']) : null,
            'common_name'  => $raw['common_name']   ? trim((string) $raw['common_name'])  : null,
            'description'  => $raw['description']   ? trim((string) $raw['description'])  : null,
            'specs'        => $specs,
        ];
    }
}
