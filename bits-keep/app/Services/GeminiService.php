<?php

namespace App\Services;

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

    public function __construct(
        private AppSettingService $settings,
        private DatasheetPromptService $promptService,
    ) {}

    /**
     * 設定UIまたは .env から API キーを取得する。
     * 設定UIの値が優先される。
     */
    public function getApiKey(): ?string
    {
        $fromDb = $this->settings->get('gemini.api_key');
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
     * アップロード済みPDFから電子部品情報を構造化抽出する。
     *
     * @param  string  $fileUri  uploadFile() が返した URI
     * @return array{
     *   part_number: ?string,
     *   manufacturer: ?string,
     *   common_name: ?string,
     *   component_types: array<int, string>,
     *   package_names: array<int, string>,
     *   description: ?string,
     *   specs: array<int, array<string, mixed>>
     * }
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
     * 抽出結果を統一フォーマットに正規化する。
     */
    private function normalizeResult(array $raw): array
    {
        $specs = [];
        foreach ($raw['specs'] ?? [] as $item) {
            if (empty($item['name'])) {
                continue;
            }
            $profile = $this->normalizeProfile((string) ($item['value_profile'] ?? $item['profile'] ?? $item['value_mode'] ?? ''));
            $valueTyp = trim((string) ($item['value_typ'] ?? $item['typ'] ?? ''));
            $valueMin = trim((string) ($item['value_min'] ?? $item['min'] ?? ''));
            $valueMax = trim((string) ($item['value_max'] ?? $item['max'] ?? ''));
            $value = trim((string) ($item['value'] ?? ''));

            if ($profile === 'typ' && $valueTyp === '') {
                $valueTyp = $value;
            } elseif ($profile === 'range' && ($valueMin === '' || $valueMax === '')) {
                [$valueMin, $valueMax] = $this->splitRangeFallback($value, $valueMin, $valueMax);
            } elseif ($profile === 'max_only' && $valueMax === '') {
                $valueMax = $value;
            } elseif ($profile === 'min_only' && $valueMin === '') {
                $valueMin = $value;
            } elseif ($profile === 'triple' && ($valueMin === '' || $valueTyp === '' || $valueMax === '')) {
                [$valueMin, $valueTyp, $valueMax] = $this->splitTripleFallback($value, $valueMin, $valueTyp, $valueMax);
            }

            $specs[] = [
                'name' => (string) ($item['name'] ?? ''),
                'value_profile' => $profile,
                'value' => $value,
                'value_typ' => $valueTyp,
                'value_min' => $valueMin,
                'value_max' => $valueMax,
                'unit' => (string) ($item['unit'] ?? ''),
            ];
        }

        $componentTypes = collect($raw['component_types'] ?? [])
            ->when(empty($raw['component_types']) && ! empty($raw['component_type']), function ($collection) use ($raw) {
                return $collection->push($raw['component_type']);
            })
            ->map(fn ($item) => trim((string) $item))
            ->filter()
            ->unique()
            ->values()
            ->all();

        $packageNames = collect($raw['package_names'] ?? [])
            ->when(empty($raw['package_names']) && ! empty($raw['package_name']), function ($collection) use ($raw) {
                return $collection->push($raw['package_name']);
            })
            ->map(fn ($item) => trim((string) $item))
            ->filter()
            ->unique()
            ->values()
            ->all();

        return [
            'part_number' => ! empty($raw['part_number']) ? trim((string) $raw['part_number']) : null,
            'manufacturer' => ! empty($raw['manufacturer']) ? trim((string) $raw['manufacturer']) : null,
            'common_name' => ! empty($raw['common_name']) ? trim((string) $raw['common_name']) : null,
            'component_types' => $componentTypes,
            'package_names' => $packageNames,
            'description' => ! empty($raw['description']) ? trim((string) $raw['description']) : null,
            'specs' => $specs,
        ];
    }

    private function normalizeProfile(string $profile): string
    {
        $normalized = strtolower(trim($profile));

        return match ($normalized) {
            'range' => 'range',
            'max', 'max_only' => 'max_only',
            'min', 'min_only' => 'min_only',
            'triple' => 'triple',
            default => 'typ',
        };
    }

    /**
     * @return array{0: string, 1: string}
     */
    private function splitRangeFallback(string $value, string $currentMin, string $currentMax): array
    {
        if ($value === '') {
            return [$currentMin, $currentMax];
        }

        $parts = preg_split('/\s*(?:〜|~|～|to)\s*/iu', $value, 2) ?: [];
        if (count($parts) !== 2) {
            return [$currentMin, $currentMax];
        }

        return [
            $currentMin !== '' ? $currentMin : trim($parts[0]),
            $currentMax !== '' ? $currentMax : trim($parts[1]),
        ];
    }

    /**
     * @return array{0: string, 1: string, 2: string}
     */
    private function splitTripleFallback(string $value, string $currentMin, string $currentTyp, string $currentMax): array
    {
        if ($value === '') {
            return [$currentMin, $currentTyp, $currentMax];
        }

        $parts = preg_split('/\s*(?:\/|／|\|)\s*/u', $value, 3) ?: [];
        if (count($parts) !== 3) {
            return [$currentMin, $currentTyp, $currentMax];
        }

        return [
            $currentMin !== '' ? $currentMin : trim($parts[0]),
            $currentTyp !== '' ? $currentTyp : trim($parts[1]),
            $currentMax !== '' ? $currentMax : trim($parts[2]),
        ];
    }

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
