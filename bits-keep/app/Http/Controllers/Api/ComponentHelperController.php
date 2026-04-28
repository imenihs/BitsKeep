<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Responses\ApiResponse;
use App\Models\SpecGroup;
use App\Services\DatasheetPromptService;
use App\Services\GeminiService;
use App\Services\SpecTypeMatchingService;
use App\Services\TempDatasheetService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\Str;

/**
 * 部品登録補助機能（PDFデータシート解析）
 * POST /api/component-helper/analyze-datasheet
 */
class ComponentHelperController extends Controller
{
    public function createChatGptJob(
        Request $request,
        DatasheetPromptService $promptService,
        TempDatasheetService $tempDatasheets
    ): JsonResponse {
        $startedAt = microtime(true);
        Log::info('ComponentHelper createChatGptJob start', [
            'user_id' => $request->user()?->id,
            'content_length' => $request->server('CONTENT_LENGTH'),
            'target_index' => $request->input('target_index'),
            'datasheet_count' => count((array) $request->file('datasheets', [])),
        ]);

        $request->validate([
            'datasheets' => ['required', 'array', 'min:1'],
            'datasheets.*' => ['file', 'mimes:pdf', 'max:20480'],
            'datasheet_labels' => ['nullable', 'array'],
            'datasheet_labels.*' => ['nullable', 'string', 'max:120'],
            'target_index' => ['required', 'integer', 'min:0'],
        ]);

        $files = array_values($request->file('datasheets', []));
        $targetIndex = (int) $request->integer('target_index');
        if (! isset($files[$targetIndex])) {
            return ApiResponse::validationError(['target_index' => ['解析対象のPDFを選択してください。']]);
        }

        try {
            $entries = $tempDatasheets->createMany($files, (array) $request->input('datasheet_labels', []));
        } catch (\InvalidArgumentException $e) {
            Log::warning('ComponentHelper createChatGptJob validation/runtime failure', [
                'message' => $e->getMessage(),
                'elapsed_ms' => (int) ((microtime(true) - $startedAt) * 1000),
            ]);
            return ApiResponse::validationError(['datasheets' => [$e->getMessage()]]);
        } catch (\RuntimeException $e) {
            Log::error('ComponentHelper createChatGptJob failed', ['message' => $e->getMessage()]);

            return ApiResponse::error($e->getMessage(), [], 500);
        }

        $targetEntry = $entries[$targetIndex];
        $expiresAt = Carbon::parse($targetEntry['expires_at']);

        $jobId = (string) Str::uuid();
        Log::info('ComponentHelper createChatGptJob success', [
            'user_id' => $request->user()?->id,
            'job_id' => $jobId,
            'datasheet_count' => count($entries),
            'target_index' => $targetIndex,
            'elapsed_ms' => (int) ((microtime(true) - $startedAt) * 1000),
        ]);

        return ApiResponse::success([
            'job_id' => $jobId,
            'prompt_text' => $promptService->getPromptText(),
            'target_index' => $targetIndex,
            'temp_upload_token' => $targetEntry['token'],
            'temp_datasheet_tokens' => array_column($entries, 'token'),
            'datasheets' => array_map(function (array $entry, int $index) use ($targetIndex) {
                return [
                    ...$entry,
                    'is_target' => $index === $targetIndex,
                ];
            }, $entries, array_keys($entries)),
            'target_datasheet' => [
                ...$targetEntry,
                'is_target' => true,
                'signed_download_url' => URL::temporarySignedRoute(
                    'component-helper.temp-datasheets.show',
                    $expiresAt,
                    ['token' => $targetEntry['token']]
                ),
            ],
            'expires_at' => $expiresAt->toIso8601String(),
        ], 'ChatGPT解析ジョブを作成しました');
    }

    public function destroyChatGptJob(string $token, TempDatasheetService $tempDatasheets): JsonResponse
    {
        $deleted = $tempDatasheets->deleteToken($token);
        if (! $deleted) {
            return ApiResponse::notFound('対象の一時PDFが見つかりません。');
        }

        return ApiResponse::success(null, '一時PDFを破棄しました');
    }

    public function downloadTempDatasheet(string $token, TempDatasheetService $tempDatasheets)
    {
        try {
            $meta = $tempDatasheets->getActiveMeta($token);
            $absolutePath = Storage::disk('local')->path($meta['file_path']);

            return response()->download(
                $absolutePath,
                $meta['original_name'] ?? ($token.'.pdf'),
                ['Content-Type' => $meta['mime_type'] ?? 'application/pdf']
            );
        } catch (\RuntimeException $e) {
            abort(404, $e->getMessage());
        }
    }

    public function analyzeDatasheet(Request $request, GeminiService $gemini, SpecTypeMatchingService $matcher): JsonResponse
    {
        // APIキー未設定チェック（DB or .env）
        if (! $gemini->isConfigured()) {
            return ApiResponse::error(
                'Gemini APIキーが設定されていません。連携設定から登録してください。',
                ['api_key' => ['未設定']],
                403
            );
        }

        $request->validate([
            'pdf' => ['required', 'file', 'mimes:pdf', 'max:15360'], // 15MB
        ]);

        $file = $request->file('pdf');
        $tmpPath = $file->getRealPath();

        try {
            // Gemini Files API へアップロード
            $fileUri = $gemini->uploadFile($tmpPath);

            // 構造化抽出
            $result = $gemini->analyzeDatasheet($fileUri);

            // spec_type とのマッチング
            $result['specs'] = $matcher->match($result['specs']);
            $result = $this->appendCategorySpecRecommendations($result);

        } catch (\InvalidArgumentException $e) {
            return ApiResponse::validationError(['pdf' => [$e->getMessage()]]);
        } catch (\RuntimeException $e) {
            Log::error('ComponentHelper analyzeDatasheet failed', ['message' => $e->getMessage()]);

            return ApiResponse::error($e->getMessage(), [], 502);
        } catch (\Throwable $e) {
            Log::error('ComponentHelper analyzeDatasheet failed', [
                'message' => $e->getMessage(),
                'exception' => get_class($e),
            ]);

            return ApiResponse::error('データシートの解析中にサーバー内部でエラーが発生しました。', [], 500);
        }

        return ApiResponse::success($result, '解析が完了しました');
    }

    /**
     * @param  array<string, mixed>  $result
     * @return array<string, mixed>
     */
    private function appendCategorySpecRecommendations(array $result): array
    {
        $categoryCandidates = $this->resolveCategoryCandidates($this->extractCategoryNames($result));
        $categoryIds = collect($categoryCandidates)
            ->pluck('category_id')
            ->filter()
            ->map(fn ($id) => (int) $id)
            ->unique()
            ->values();

        $groups = $categoryIds->isEmpty()
            ? collect()
            : SpecGroup::query()
                ->where('name', '!=', '共通')
                ->whereIn('id', $categoryIds)
                ->with([
                    'specTypes' => fn ($query) => $query->with(['units', 'aliases']),
                    'templates' => fn ($query) => $query->with(['items.specType.units', 'items.specType.aliases']),
                ])
                ->orderBy('sort_order')
                ->orderBy('name')
                ->get();

        $groups->each(function (SpecGroup $group) {
            $group->is_suggested = true;
        });

        $templates = $groups
            ->flatMap(function (SpecGroup $group) {
                return $group->templates->each(function ($template) {
                    $template->is_suggested = true;
                });
            })
            ->values();

        $result['category_candidates'] = $categoryCandidates;
        $result['recommended_spec_groups'] = $groups->values();
        $result['template_candidates'] = $templates;
        $result['recommended_group_ids'] = $groups->pluck('id')->values();
        $result['recommended_template_ids'] = $templates->pluck('id')->values();

        return $result;
    }

    /**
     * @param  array<string, mixed>  $result
     * @return array<int, string>
     */
    private function extractCategoryNames(array $result): array
    {
        $names = collect();

        foreach (['component_types', 'category_names', 'categories'] as $key) {
            $values = $result[$key] ?? [];
            if (! is_array($values)) {
                continue;
            }

            foreach ($values as $value) {
                if (is_string($value)) {
                    $names->push($value);
                } elseif (is_array($value)) {
                    $names->push($value['name'] ?? $value['category_name'] ?? '');
                }
            }
        }

        foreach (['component_type', 'category_name'] as $key) {
            if (is_string($result[$key] ?? null)) {
                $names->push($result[$key]);
            }
        }

        return $names
            ->map(fn ($value) => trim((string) $value))
            ->filter()
            ->unique()
            ->values()
            ->all();
    }

    /**
     * @param  array<int, string>  $names
     * @return array<int, array<string, mixed>>
     */
    private function resolveCategoryCandidates(array $names): array
    {
        $categories = SpecGroup::query()
            ->where('name', '!=', '共通')
            ->orderBy('sort_order')
            ->orderBy('name')
            ->get();

        return collect($names)
            ->map(function (string $name) use ($categories) {
                $matched = $this->matchCategoryByName($name, $categories);

                return [
                    'name' => $name,
                    'category_id' => $matched?->id,
                    'category' => $matched,
                    'matched' => (bool) $matched,
                ];
            })
            ->values()
            ->all();
    }

    private function matchCategoryByName(string $name, $categories): ?SpecGroup
    {
        $normalized = $this->normalizeMatchText($name);
        if ($normalized === '') {
            return null;
        }

        $matched = $categories->first(fn (SpecGroup $category) => $this->normalizeMatchText($category->name) === $normalized);
        if ($matched) {
            return $matched;
        }

        return $categories->first(function (SpecGroup $category) use ($normalized) {
            $categoryName = $this->normalizeMatchText($category->name);

            return $categoryName !== '' && (str_contains($normalized, $categoryName) || str_contains($categoryName, $normalized));
        });
    }

    private function normalizeMatchText(?string $value): string
    {
        return mb_strtolower(preg_replace('/[\s()\[\]_.-]+/u', '', (string) $value) ?? '');
    }
}
