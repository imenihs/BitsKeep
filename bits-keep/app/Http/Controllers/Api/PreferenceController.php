<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Responses\ApiResponse;
use App\Services\PreferenceService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class PreferenceController extends Controller
{
    public function __construct(private readonly PreferenceService $prefs) {}

    /**
     * GET /api/preferences/{key}
     * ユーザー設定値を取得する。
     */
    public function show(Request $request, string $key): JsonResponse
    {
        $value = $this->prefs->get($request->user(), $key);
        return ApiResponse::success(['key' => $key, 'value' => $value]);
    }

    /**
     * PUT /api/preferences/{key}
     * ユーザー設定値を保存する。
     */
    public function update(Request $request, string $key): JsonResponse
    {
        $request->validate([
            'value' => ['required'],
        ]);

        $this->prefs->set($request->user(), $key, $request->input('value'));
        return ApiResponse::success(['key' => $key, 'value' => $request->input('value')]);
    }

    /**
     * DELETE /api/preferences/{key}
     * ユーザー設定値を削除する。
     */
    public function destroy(Request $request, string $key): JsonResponse
    {
        $this->prefs->delete($request->user(), $key);
        return ApiResponse::noContent();
    }
}
