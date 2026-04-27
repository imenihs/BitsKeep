<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\StorePackageRequest;
use App\Http\Responses\ApiResponse;
use App\Models\Package;
use Illuminate\Http\Request;
use App\Support\FileStorage;

class PackageController extends Controller
{
    public function index(Request $request)
    {
        $query = Package::query()->with(['packageGroup'])->withCount('components as usage_count');
        if ($request->boolean('include_archived')) {
            $query->withTrashed();
        }
        if ($groupId = $request->integer('package_group_id')) {
            $query->where('package_group_id', $groupId);
        }
        $packages = $query->orderBy('sort_order')->orderBy('name')->get()->map(function ($p) {
            $p->image_url = FileStorage::url($p->image_path);
            $p->pdf_url = FileStorage::url($p->pdf_path);
            $p->can_force_delete = (bool) $p->deleted_at && $p->usage_count === 0;
            $p->force_delete_reason = $p->can_force_delete ? '' : ($p->usage_count > 0 ? "部品{$p->usage_count}件で使用中" : '先にアーカイブしてください');
            return $p;
        });
        return ApiResponse::success($packages);
    }

    public function store(StorePackageRequest $request)
    {
        $data = $request->safe()->except(['image', 'pdf']);

        if ($request->hasFile('image')) {
            $data['image_path'] = FileStorage::storePackageImage($request->file('image'));
        }
        if ($request->hasFile('pdf')) {
            $data['pdf_path'] = FileStorage::storeDatasheet($request->file('pdf'));
        }

        $package = Package::create($data);
        return ApiResponse::created($package);
    }

    public function show(Package $package)
    {
        $package->load('packageGroup');
        $package->image_url = FileStorage::url($package->image_path);
        $package->pdf_url = FileStorage::url($package->pdf_path);
        return ApiResponse::success($package);
    }

    public function update(StorePackageRequest $request, Package $package)
    {
        $data = $request->safe()->except(['image', 'pdf']);

        if ($request->hasFile('image')) {
            FileStorage::delete($package->image_path); // 旧ファイル削除
            $data['image_path'] = FileStorage::storePackageImage($request->file('image'));
        }
        if ($request->hasFile('pdf')) {
            FileStorage::delete($package->pdf_path);
            $data['pdf_path'] = FileStorage::storeDatasheet($request->file('pdf'));
        }

        $package->update($data);
        return ApiResponse::success($package);
    }

    public function destroy(Package $package)
    {
        $package->delete();
        return ApiResponse::noContent();
    }

    public function restore(int $package)
    {
        $model = Package::withTrashed()->findOrFail($package);
        $model->restore();

        return ApiResponse::success($model);
    }

    public function forceDestroy(int $package)
    {
        $model = Package::withTrashed()->withCount('components as usage_count')->findOrFail($package);
        if (!$model->deleted_at) {
            return ApiResponse::error('完全削除の前にアーカイブしてください', [], 422);
        }
        if ($model->usage_count > 0) {
            return ApiResponse::error("部品{$model->usage_count}件で使用中のため完全削除できません", [], 422);
        }
        FileStorage::delete($model->image_path);
        FileStorage::delete($model->pdf_path);
        $model->forceDelete();

        return ApiResponse::noContent();
    }
}
