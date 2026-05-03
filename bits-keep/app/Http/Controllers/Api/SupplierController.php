<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreSupplierRequest;
use App\Http\Responses\ApiResponse;
use App\Models\Supplier;
use Illuminate\Http\Request;

class SupplierController extends Controller
{
    /**
     * 目的: 仕入先の一覧を検索条件付きで返す。
     * 機能: HTTP入力を検証し、Eloquent操作またはサービス処理を行い、JSONレスポンスへ包む。
     * 入力: $request。
     * 出力: HTTP JSONレスポンス、ファイルレスポンス、またはnoContentレスポンス。
     * 動作条件: 認証済みユーザー、権限、バリデーション済み入力を前提にする。
     * 副作用: DB、ファイルストレージ、外部サービス、HTTPレスポンスのいずれかを操作する場合がある。
     */
    public function index(Request $request)
    {
        $query = Supplier::query()->withCount('componentSuppliers as usage_count');
        if ($request->boolean('include_archived')) {
            $query->withTrashed();
        }
        $suppliers = $query->orderBy('name')->get()->map(function (Supplier $supplier) {
            $supplier->can_force_delete = (bool) $supplier->deleted_at && $supplier->usage_count === 0;
            $supplier->force_delete_reason = $supplier->can_force_delete ? '' : ($supplier->usage_count > 0 ? "仕入先{$supplier->usage_count}件で使用中" : '先に取引停止してください');
            return $supplier;
        });

        return ApiResponse::success($suppliers);
    }
    /**
     * 目的: 仕入先の検証済み入力から新規作成する。
     * 機能: HTTP入力を検証し、Eloquent操作またはサービス処理を行い、JSONレスポンスへ包む。
     * 入力: $request。
     * 出力: HTTP JSONレスポンス、ファイルレスポンス、またはnoContentレスポンス。
     * 動作条件: 認証済みユーザー、権限、バリデーション済み入力を前提にする。
     * 副作用: DB、ファイルストレージ、外部サービス、HTTPレスポンスのいずれかを操作する場合がある。
     */
    public function store(StoreSupplierRequest $request)
    {
        return ApiResponse::created(Supplier::create($request->validated()));
    }
    /**
     * 目的: 仕入先の詳細を返す。
     * 機能: HTTP入力を検証し、Eloquent操作またはサービス処理を行い、JSONレスポンスへ包む。
     * 入力: $supplier。
     * 出力: HTTP JSONレスポンス、ファイルレスポンス、またはnoContentレスポンス。
     * 動作条件: 認証済みユーザー、権限、バリデーション済み入力を前提にする。
     * 副作用: DB、ファイルストレージ、外部サービス、HTTPレスポンスのいずれかを操作する場合がある。
     */
    public function show(Supplier $supplier)
    {
        return ApiResponse::success($supplier->load('shippingRules'));
    }
    /**
     * 目的: 仕入先の検証済み入力で更新する。
     * 機能: HTTP入力を検証し、Eloquent操作またはサービス処理を行い、JSONレスポンスへ包む。
     * 入力: $request, $supplier。
     * 出力: HTTP JSONレスポンス、ファイルレスポンス、またはnoContentレスポンス。
     * 動作条件: 認証済みユーザー、権限、バリデーション済み入力を前提にする。
     * 副作用: DB、ファイルストレージ、外部サービス、HTTPレスポンスのいずれかを操作する場合がある。
     */
    public function update(StoreSupplierRequest $request, Supplier $supplier)
    {
        $supplier->update($request->validated());
        return ApiResponse::success($supplier);
    }
    /**
     * 目的: 仕入先の削除またはアーカイブする。
     * 機能: HTTP入力を検証し、Eloquent操作またはサービス処理を行い、JSONレスポンスへ包む。
     * 入力: $supplier。
     * 出力: HTTP JSONレスポンス、ファイルレスポンス、またはnoContentレスポンス。
     * 動作条件: 認証済みユーザー、権限、バリデーション済み入力を前提にする。
     * 副作用: DB、ファイルストレージ、外部サービス、HTTPレスポンスのいずれかを操作する場合がある。
     */
    public function destroy(Supplier $supplier)
    {
        $supplier->delete();
        return ApiResponse::noContent();
    }
    /**
     * 目的: 仕入先のアーカイブ済みデータを復元する。
     * 機能: HTTP入力を検証し、Eloquent操作またはサービス処理を行い、JSONレスポンスへ包む。
     * 入力: $supplier。
     * 出力: HTTP JSONレスポンス、ファイルレスポンス、またはnoContentレスポンス。
     * 動作条件: 認証済みユーザー、権限、バリデーション済み入力を前提にする。
     * 副作用: DB、ファイルストレージ、外部サービス、HTTPレスポンスのいずれかを操作する場合がある。
     */
    public function restore(int $supplier)
    {
        $model = Supplier::withTrashed()->findOrFail($supplier);
        $model->restore();

        return ApiResponse::success($model);
    }
    /**
     * 目的: 仕入先のforcedestroyを処理する。
     * 機能: HTTP入力を検証し、Eloquent操作またはサービス処理を行い、JSONレスポンスへ包む。
     * 入力: $supplier。
     * 出力: HTTP JSONレスポンス、ファイルレスポンス、またはnoContentレスポンス。
     * 動作条件: 認証済みユーザー、権限、バリデーション済み入力を前提にする。
     * 副作用: DB、ファイルストレージ、外部サービス、HTTPレスポンスのいずれかを操作する場合がある。
     */
    public function forceDestroy(int $supplier)
    {
        $model = Supplier::withTrashed()->withCount('componentSuppliers as usage_count')->findOrFail($supplier);
        if (!$model->deleted_at) {
            return ApiResponse::error('完全削除の前に取引停止してください', [], 422);
        }
        if ($model->usage_count > 0) {
            return ApiResponse::error("仕入先{$model->usage_count}件で使用中のため完全削除できません", [], 422);
        }
        $model->forceDelete();

        return ApiResponse::noContent();
    }
}
