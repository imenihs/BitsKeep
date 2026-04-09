<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Responses\ApiResponse;
use App\Services\AppSettingService;
use Illuminate\Http\Request;

class IntegrationSettingsController extends Controller
{
    public function showNotion(AppSettingService $settings)
    {
        return ApiResponse::success($settings->getNotionConfig());
    }

    public function updateNotion(Request $request, AppSettingService $settings)
    {
        if (! $request->user()->isEditor()) {
            return ApiResponse::forbidden();
        }

        $validated = $request->validate([
            'api_token' => ['nullable', 'string'],
            'root_page_url' => ['nullable', 'string'],
        ]);

        try {
            $config = $settings->updateNotionConfig(
                $validated['api_token'] ?? null,
                $validated['root_page_url'] ?? null,
            );
        } catch (\InvalidArgumentException $e) {
            return ApiResponse::validationError([
                'root_page_url' => [$e->getMessage()],
            ]);
        }

        return ApiResponse::success($config, '連携設定を保存しました');
    }
}
