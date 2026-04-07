<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Responses\ApiResponse;
use App\Models\AuditLog;
use Illuminate\Http\Request;

/**
 * 操作ログ API（SCR-010）
 * admin ロールのみアクセス可
 */
class AuditLogController extends Controller
{
    // GET /api/audit-logs
    public function index(Request $request)
    {
        if (! $request->user()->isAdmin()) {
            return ApiResponse::forbidden();
        }

        $query = AuditLog::with('user:id,name,email')
            ->orderByDesc('created_at');

        // フィルタ: アクション（created/updated/deleted）
        if ($request->filled('action')) {
            $query->where('action', $request->action);
        }

        // フィルタ: リソース種別
        if ($request->filled('resource_type')) {
            $query->where('resource_type', $request->resource_type);
        }

        // フィルタ: リソースID
        if ($request->filled('resource_id')) {
            $query->where('resource_id', $request->resource_id);
        }

        // フィルタ: ユーザーID
        if ($request->filled('user_id')) {
            $query->where('user_id', $request->user_id);
        }

        // フィルタ: 日付範囲
        if ($request->filled('date_from')) {
            $query->where('created_at', '>=', $request->date_from . ' 00:00:00');
        }
        if ($request->filled('date_to')) {
            $query->where('created_at', '<=', $request->date_to . ' 23:59:59');
        }

        $logs = $query->paginate($request->input('per_page', 50));

        return ApiResponse::success($logs);
    }
}
