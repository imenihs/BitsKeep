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
