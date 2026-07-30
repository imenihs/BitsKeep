<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Responses\ApiResponse;
use App\Services\Datasheet\DatasheetRecommendationDecorator;
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
    /**
     * 目的: データシートPDFを一時保存し、ChatGPT連携用の解析ジョブを作成する。
     * 機能: 呼び出し元から受けた値を検証または整形し、対象処理へ渡す。
     * 入力: 関数シグネチャで指定された引数。
     * 出力: 型宣言または呼び出し規約に従う処理結果。
     * 動作条件: 呼び出し元が必要な依存オブジェクトと入力値を渡すこと。
     * 副作用: 依存オブジェクト、DB、ファイル、外部API、モデル状態のいずれかを更新する場合がある。
     */
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

    /**
     * 目的: 部品登録補助のdestroychatgptjobを処理する。
     * 機能: HTTP入力を検証し、Eloquent操作またはサービス処理を行い、JSONレスポンスへ包む。
     * 入力: $token, $tempDatasheets。
     * 出力: HTTP JSONレスポンス、ファイルレスポンス、またはnoContentレスポンス。
     * 動作条件: 認証済みユーザー、権限、バリデーション済み入力を前提にする。
     * 副作用: DB、ファイルストレージ、外部サービス、HTTPレスポンスのいずれかを操作する場合がある。
     */
    public function destroyChatGptJob(string $token, TempDatasheetService $tempDatasheets): JsonResponse
    {
        $deleted = $tempDatasheets->deleteToken($token);
        if (! $deleted) {
            return ApiResponse::notFound('対象の一時PDFが見つかりません。');
        }

        return ApiResponse::success(null, '一時PDFを破棄しました');
    }

    /**
     * 目的: 部品登録補助のdownloadtempデータシートを処理する。
     * 機能: HTTP入力を検証し、Eloquent操作またはサービス処理を行い、JSONレスポンスへ包む。
     * 入力: $token, $tempDatasheets。
     * 出力: HTTP JSONレスポンス、ファイルレスポンス、またはnoContentレスポンス。
     * 動作条件: 認証済みユーザー、権限、バリデーション済み入力を前提にする。
     * 副作用: DB、ファイルストレージ、外部サービス、HTTPレスポンスのいずれかを操作する場合がある。
     */
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

    /**
     * 目的: 部品登録補助のanalyzeデータシートを処理する。
     * 機能: HTTP入力を検証し、Eloquent操作またはサービス処理を行い、JSONレスポンスへ包む。
     * 入力: $request, $gemini, $matcher。
     * 出力: HTTP JSONレスポンス、ファイルレスポンス、またはnoContentレスポンス。
     * 動作条件: 認証済みユーザー、権限、バリデーション済み入力を前提にする。
     * 副作用: DB、ファイルストレージ、外部サービス、HTTPレスポンスのいずれかを操作する場合がある。
     */
    public function analyzeDatasheet(
        Request $request,
        GeminiService $gemini,
        SpecTypeMatchingService $matcher,
        DatasheetRecommendationDecorator $decorator
    ): JsonResponse {
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
            $result = $decorator->decorate($result);

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
}
