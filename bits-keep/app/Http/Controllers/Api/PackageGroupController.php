<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\StorePackageGroupRequest;
use App\Http\Responses\ApiResponse;
use App\Models\Package;
use App\Models\PackageGroup;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class PackageGroupController extends Controller
{
    /**
     * 目的: パッケージ分類の一覧を検索条件付きで返す。
     * 機能: HTTP入力を検証し、Eloquent操作またはサービス処理を行い、JSONレスポンスへ包む。
     * 入力: $request。
     * 出力: HTTP JSONレスポンス、ファイルレスポンス、またはnoContentレスポンス。
     * 動作条件: 認証済みユーザー、権限、バリデーション済み入力を前提にする。
     * 副作用: DB、ファイルストレージ、外部サービス、HTTPレスポンスのいずれかを操作する場合がある。
     */
    public function index(Request $request)
    {
        $query = PackageGroup::query()->withCount('packages as usage_count');
        if ($request->boolean('include_archived')) {
            $query->withTrashed();
        }

        $groups = $query->orderBy('sort_order')->orderBy('name')->get()->map(function (PackageGroup $group) {
            $group->can_force_delete = (bool) $group->deleted_at && $group->usage_count === 0;
            $group->force_delete_reason = $group->can_force_delete ? '' : ($group->usage_count > 0 ? "パッケージ{$group->usage_count}件で使用中" : '先にアーカイブしてください');
            return $group;
        });

        return ApiResponse::success($groups);
    }
    /**
     * 目的: パッケージ分類の検証済み入力から新規作成する。
     * 機能: HTTP入力を検証し、Eloquent操作またはサービス処理を行い、JSONレスポンスへ包む。
     * 入力: $request。
     * 出力: HTTP JSONレスポンス、ファイルレスポンス、またはnoContentレスポンス。
     * 動作条件: 認証済みユーザー、権限、バリデーション済み入力を前提にする。
     * 副作用: DB、ファイルストレージ、外部サービス、HTTPレスポンスのいずれかを操作する場合がある。
     */
    public function store(StorePackageGroupRequest $request)
    {
        return ApiResponse::created(PackageGroup::create($request->validated()));
    }
    /**
     * 目的: パッケージ分類の詳細を返す。
     * 機能: HTTP入力を検証し、Eloquent操作またはサービス処理を行い、JSONレスポンスへ包む。
     * 入力: $packageGroup。
     * 出力: HTTP JSONレスポンス、ファイルレスポンス、またはnoContentレスポンス。
     * 動作条件: 認証済みユーザー、権限、バリデーション済み入力を前提にする。
     * 副作用: DB、ファイルストレージ、外部サービス、HTTPレスポンスのいずれかを操作する場合がある。
     */
    public function show(PackageGroup $packageGroup)
    {
        return ApiResponse::success($packageGroup);
    }
    /**
     * 目的: パッケージ分類の検証済み入力で更新する。
     * 機能: HTTP入力を検証し、Eloquent操作またはサービス処理を行い、JSONレスポンスへ包む。
     * 入力: $request, $packageGroup。
     * 出力: HTTP JSONレスポンス、ファイルレスポンス、またはnoContentレスポンス。
     * 動作条件: 認証済みユーザー、権限、バリデーション済み入力を前提にする。
     * 副作用: DB、ファイルストレージ、外部サービス、HTTPレスポンスのいずれかを操作する場合がある。
     */
    public function update(StorePackageGroupRequest $request, PackageGroup $packageGroup)
    {
        $packageGroup->update($request->validated());
        return ApiResponse::success($packageGroup);
    }
    /**
     * 目的: パッケージ分類の削除またはアーカイブする。
     * 機能: HTTP入力を検証し、Eloquent操作またはサービス処理を行い、JSONレスポンスへ包む。
     * 入力: $packageGroup。
     * 出力: HTTP JSONレスポンス、ファイルレスポンス、またはnoContentレスポンス。
     * 動作条件: 認証済みユーザー、権限、バリデーション済み入力を前提にする。
     * 副作用: DB、ファイルストレージ、外部サービス、HTTPレスポンスのいずれかを操作する場合がある。
     */
    public function destroy(PackageGroup $packageGroup)
    {
        $packageGroup->delete();
        return ApiResponse::noContent();
    }
    /**
     * 目的: パッケージ分類のアーカイブ済みデータを復元する。
     * 機能: HTTP入力を検証し、Eloquent操作またはサービス処理を行い、JSONレスポンスへ包む。
     * 入力: $packageGroup。
     * 出力: HTTP JSONレスポンス、ファイルレスポンス、またはnoContentレスポンス。
     * 動作条件: 認証済みユーザー、権限、バリデーション済み入力を前提にする。
     * 副作用: DB、ファイルストレージ、外部サービス、HTTPレスポンスのいずれかを操作する場合がある。
     */
    public function restore(int $packageGroup)
    {
        $model = PackageGroup::withTrashed()->findOrFail($packageGroup);
        $model->restore();
        return ApiResponse::success($model);
    }
    /**
     * 目的: パッケージ分類のforcedestroyを処理する。
     * 機能: HTTP入力を検証し、Eloquent操作またはサービス処理を行い、JSONレスポンスへ包む。
     * 入力: $packageGroup。
     * 出力: HTTP JSONレスポンス、ファイルレスポンス、またはnoContentレスポンス。
     * 動作条件: 認証済みユーザー、権限、バリデーション済み入力を前提にする。
     * 副作用: DB、ファイルストレージ、外部サービス、HTTPレスポンスのいずれかを操作する場合がある。
     */
    public function forceDestroy(int $packageGroup)
    {
        $model = PackageGroup::withTrashed()->withCount('packages as usage_count')->findOrFail($packageGroup);
        if (!$model->deleted_at) {
            return ApiResponse::error('完全削除の前にアーカイブしてください', [], 422);
        }
        if ($model->usage_count > 0) {
            return ApiResponse::error("パッケージ{$model->usage_count}件で使用中のため完全削除できません", [], 422);
        }
        $model->forceDelete();

        return ApiResponse::noContent();
    }
    /**
     * 目的: パッケージ分類のreorderpackagesを処理する。
     * 機能: HTTP入力を検証し、Eloquent操作またはサービス処理を行い、JSONレスポンスへ包む。
     * 入力: $request, $packageGroup。
     * 出力: HTTP JSONレスポンス、ファイルレスポンス、またはnoContentレスポンス。
     * 動作条件: 認証済みユーザー、権限、バリデーション済み入力を前提にする。
     * 副作用: DB、ファイルストレージ、外部サービス、HTTPレスポンスのいずれかを操作する場合がある。
     */
    public function reorderPackages(Request $request, PackageGroup $packageGroup)
    {
        if (!$request->user()?->isEditor()) {
            return ApiResponse::forbidden();
        }

        $validated = $request->validate([
            'package_ids' => ['required', 'array'],
            'package_ids.*' => ['integer', 'exists:packages,id'],
        ]);

        $ids = array_values(array_unique(array_map('intval', $validated['package_ids'])));
        $groupPackageIds = Package::query()
            ->where('package_group_id', $packageGroup->id)
            ->whereIn('id', $ids)
            ->pluck('id')
            ->all();

        if (count($groupPackageIds) !== count($ids)) {
            return ApiResponse::validationError([
                'package_ids' => ['選択中のパッケージ分類に属するパッケージだけを並び替えできます。'],
            ]);
        }

        DB::transaction(function () use ($ids) {
            foreach ($ids as $index => $id) {
                Package::query()->whereKey($id)->update(['sort_order' => ($index + 1) * 10]);
            }
        });

        return ApiResponse::success(
            $packageGroup->packages()->withCount('components as usage_count')->get()
        );
    }
}
