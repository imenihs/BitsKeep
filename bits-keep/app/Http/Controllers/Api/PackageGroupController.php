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
            return ApiResponse::error("パッケージ{$model->usage_count}件で使用中のため完全削除できません", [], 422);
        }
        $model->forceDelete();

        return ApiResponse::noContent();
    }

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
