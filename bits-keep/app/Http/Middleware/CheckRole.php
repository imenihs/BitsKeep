<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * ロールチェックミドルウェア
 * 使用例: Route::middleware('role:admin') / Route::middleware('role:editor')
 */
class CheckRole
{
    /**
     * 目的: 画面/APIへ入る前にユーザー権限が必要ロールを満たすか確認する。
     * 機能: 呼び出し元の入力を検証・整形し、担当するアプリ処理へ渡す。
     * 入力: $request, $next, $role。
     * 出力: Responseで表される値。
     * 動作条件: 呼び出し元が必要な依存オブジェクトと正規化前の入力値を渡すこと。
     * 副作用: 状態変更を伴う場合がある。
     */
    public function handle(Request $request, Closure $next, string $role): Response
    {
        $user = $request->user();

        if (!$user || !$user->is_active) {
            abort(403, 'アカウントが無効です。');
        }

        $allowed = match($role) {
            'admin'  => $user->isAdmin(),
            'editor' => $user->isEditor(),
            'viewer' => $user->isViewer(),
            default  => false,
        };

        if (!$allowed) {
            abort(403, 'この操作を行う権限がありません。');
        }

        return $next($request);
    }
}
