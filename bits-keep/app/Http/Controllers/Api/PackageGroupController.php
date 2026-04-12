<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\StorePackageGroupRequest;
use App\Http\Responses\ApiResponse;
use App\Models\PackageGroup;
use Illuminate\Http\Request;

class PackageGroupController extends Controller
{
    public function index(Request $request)
    {
        $query = PackageGroup::query()->withCount('packages as usage_count');
        if ($request->boolean('include_archived')) {
            $query->withTrashed();
        }

        $groups = $query->orderBy('sort_order')->orderBy('name')->get()->map(function (PackageGroup $group) {
            $group->can_force_delete = (bool) $group->deleted_at && $group->usage_count === 0;
            $group->force_delete_reason = $group->can_force_delete ? '' : ($group->usage_count > 0 ? "詳細パッケージ{$group->usage_count}件で使用中" : '先にアーカイブしてください');
            return $group;
        });

        return ApiResponse::success($groups);
    }

    public function store(StorePackageGroupRequest $request)
    {
        return ApiResponse::created(PackageGroup::create($request->validated()));
    }

    public function show(PackageGroup $packageGroup)
    {
        return ApiResponse::success($packageGroup);
    }

    public function update(StorePackageGroupRequest $request, PackageGroup $packageGroup)
    {
        $packageGroup->update($request->validated());
        return ApiResponse::success($packageGroup);
    }

    public function destroy(PackageGroup $packageGroup)
    {
        $packageGroup->delete();
        return ApiResponse::noContent();
    }

    public function restore(int $packageGroup)
    {
        $model = PackageGroup::withTrashed()->findOrFail($packageGroup);
        $model->restore();
        return ApiResponse::success($model);
    }

    public function forceDestroy(int $packageGroup)
    {
        $model = PackageGroup::withTrashed()->withCount('packages as usage_count')->findOrFail($packageGroup);
        if (!$model->deleted_at) {
            return ApiResponse::error('完全削除の前にアーカイブしてください', [], 422);
        }
        if ($model->usage_count > 0) {
            return ApiResponse::error("詳細パッケージ{$model->usage_count}件で使用中のため完全削除できません", [], 422);
        }
        $model->forceDelete();

        return ApiResponse::noContent();
    }
}
