<?php

namespace App\Http\Responses;

use Illuminate\Http\JsonResponse;

/**
 * API レスポンス共通フォーマット
 *
 * 成功: { "success": true,  "data": {...},    "message": "..." }
 * 失敗: { "success": false, "errors": {...},  "message": "..." }
 */
class ApiResponse
{
    public static function success(mixed $data = null, string $message = '', int $status = 200): JsonResponse
    {
        return response()->json([
            'success' => true,
            'data'    => $data,
            'message' => $message,
        ], $status);
    }

    public static function created(mixed $data = null, string $message = '作成しました'): JsonResponse
    {
        return self::success($data, $message, 201);
    }

    public static function noContent(): JsonResponse
    {
        return response()->json(null, 204);
    }

    public static function error(string $message, array $errors = [], int $status = 400): JsonResponse
    {
        return response()->json([
            'success' => false,
            'message' => $message,
            'errors'  => $errors,
        ], $status);
    }

    public static function notFound(string $message = 'リソースが見つかりません'): JsonResponse
    {
        return self::error($message, [], 404);
    }

    public static function forbidden(string $message = '権限がありません'): JsonResponse
    {
        return self::error($message, [], 403);
    }

    public static function validationError(array $errors, string $message = '入力内容を確認してください'): JsonResponse
    {
        return self::error($message, $errors, 422);
    }
}
