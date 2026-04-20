<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Responses\ApiResponse;
use App\Services\GeminiService;
use App\Services\SpecTypeMatchingService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;

/**
 * 部品登録補助機能（PDFデータシート解析）
 * POST /api/component-helper/analyze-datasheet
 */
class ComponentHelperController extends Controller
{
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

        } catch (\InvalidArgumentException $e) {
            return ApiResponse::validationError(['pdf' => [$e->getMessage()]]);
        } catch (\RuntimeException $e) {
            Log::error('ComponentHelper analyzeDatasheet failed', ['message' => $e->getMessage()]);
            return ApiResponse::error('データシートの解析に失敗しました。しばらく後で再試行してください。', [], 502);
        }

        return ApiResponse::success($result, '解析が完了しました');
    }
}
