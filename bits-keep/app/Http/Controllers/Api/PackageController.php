<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\StorePackageRequest;
use App\Http\Responses\ApiResponse;
use App\Models\Package;
use App\Support\FileStorage;

class PackageController extends Controller
{
    public function index()
    {
        $packages = Package::orderBy('sort_order')->orderBy('name')->get()->map(function ($p) {
            $p->image_url = FileStorage::url($p->image_path);
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
        $package->image_url = FileStorage::url($package->image_path);
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
        if ($package->components()->exists()) {
            return ApiResponse::error('このパッケージは部品に使用されているため削除できません。', [], 409);
        }
        FileStorage::delete($package->image_path);
        FileStorage::delete($package->pdf_path);
        $package->delete();
        return ApiResponse::noContent();
    }
}
