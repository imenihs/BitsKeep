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
    /**
     * 目的: Api Responseのsuccessを担う。
     * 機能: 呼び出し元の入力を検証・整形し、担当するアプリ処理へ渡す。
     * 入力: $data, $message, $status。
     * 出力: JsonResponseで表される値。
     * 動作条件: 呼び出し元が必要な依存オブジェクトと正規化前の入力値を渡すこと。
     * 副作用: 状態変更を伴う場合がある。
     */
    public static function success(mixed $data = null, string $message = '', int $status = 200): JsonResponse
    {
        return response()->json([
            'success' => true,
            'data'    => $data,
            'message' => $message,
        ], $status);
    }

    /**
     * 目的: Api Responseのcreatedを担う。
     * 機能: 呼び出し元の入力を検証・整形し、担当するアプリ処理へ渡す。
     * 入力: $data, $message。
     * 出力: JsonResponseで表される値。
     * 動作条件: 呼び出し元が必要な依存オブジェクトと正規化前の入力値を渡すこと。
     * 副作用: 状態変更を伴う場合がある。
     */
    public static function created(mixed $data = null, string $message = '作成しました'): JsonResponse
    {
        return self::success($data, $message, 201);
    }

    /**
     * 目的: Api Responseのnocontentを担う。
     * 機能: 呼び出し元の入力を検証・整形し、担当するアプリ処理へ渡す。
     * 入力: なし。
     * 出力: JsonResponseで表される値。
     * 動作条件: 呼び出し元が必要な依存オブジェクトと正規化前の入力値を渡すこと。
     * 副作用: 状態変更を伴う場合がある。
     */
    public static function noContent(): JsonResponse
    {
        return response()->json(null, 204);
    }

    /**
     * 目的: Api Responseのerrorを担う。
     * 機能: 呼び出し元の入力を検証・整形し、担当するアプリ処理へ渡す。
     * 入力: $message, $errors, $status。
     * 出力: JsonResponseで表される値。
     * 動作条件: 呼び出し元が必要な依存オブジェクトと正規化前の入力値を渡すこと。
     * 副作用: 状態変更を伴う場合がある。
     */
    public static function error(string $message, array $errors = [], int $status = 400): JsonResponse
    {
        return response()->json([
            'success' => false,
            'message' => $message,
            'errors'  => $errors,
        ], $status);
    }

    /**
     * 目的: Api Responseのnotfoundを担う。
     * 機能: 呼び出し元の入力を検証・整形し、担当するアプリ処理へ渡す。
     * 入力: $message。
     * 出力: JsonResponseで表される値。
     * 動作条件: 呼び出し元が必要な依存オブジェクトと正規化前の入力値を渡すこと。
     * 副作用: 状態変更を伴う場合がある。
     */
    public static function notFound(string $message = 'リソースが見つかりません'): JsonResponse
    {
        return self::error($message, [], 404);
    }

    /**
     * 目的: Api Responseのforbiddenを担う。
     * 機能: 呼び出し元の入力を検証・整形し、担当するアプリ処理へ渡す。
     * 入力: $message。
     * 出力: JsonResponseで表される値。
     * 動作条件: 呼び出し元が必要な依存オブジェクトと正規化前の入力値を渡すこと。
     * 副作用: 状態変更を伴う場合がある。
     */
    public static function forbidden(string $message = '権限がありません'): JsonResponse
    {
        return self::error($message, [], 403);
    }

    /**
     * 目的: Api Responseのvalidationerrorを担う。
     * 機能: 呼び出し元の入力を検証・整形し、担当するアプリ処理へ渡す。
     * 入力: $errors, $message。
     * 出力: JsonResponseで表される値。
     * 動作条件: 呼び出し元が必要な依存オブジェクトと正規化前の入力値を渡すこと。
     * 副作用: 状態変更を伴う場合がある。
     */
    public static function validationError(array $errors, string $message = '入力内容を確認してください'): JsonResponse
    {
        return self::error($message, $errors, 422);
    }
}
