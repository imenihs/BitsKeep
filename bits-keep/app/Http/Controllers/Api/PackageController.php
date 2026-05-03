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
    /**
     * 目的: パッケージの一覧を検索条件付きで返す。
     * 機能: HTTP入力を検証し、Eloquent操作またはサービス処理を行い、JSONレスポンスへ包む。
     * 入力: $request。
     * 出力: HTTP JSONレスポンス、ファイルレスポンス、またはnoContentレスポンス。
     * 動作条件: 認証済みユーザー、権限、バリデーション済み入力を前提にする。
     * 副作用: DB、ファイルストレージ、外部サービス、HTTPレスポンスのいずれかを操作する場合がある。
     */
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
    /**
     * 目的: パッケージの検証済み入力から新規作成する。
     * 機能: HTTP入力を検証し、Eloquent操作またはサービス処理を行い、JSONレスポンスへ包む。
     * 入力: $request。
     * 出力: HTTP JSONレスポンス、ファイルレスポンス、またはnoContentレスポンス。
     * 動作条件: 認証済みユーザー、権限、バリデーション済み入力を前提にする。
     * 副作用: DB、ファイルストレージ、外部サービス、HTTPレスポンスのいずれかを操作する場合がある。
     */
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
    /**
     * 目的: パッケージの詳細を返す。
     * 機能: HTTP入力を検証し、Eloquent操作またはサービス処理を行い、JSONレスポンスへ包む。
     * 入力: $package。
     * 出力: HTTP JSONレスポンス、ファイルレスポンス、またはnoContentレスポンス。
     * 動作条件: 認証済みユーザー、権限、バリデーション済み入力を前提にする。
     * 副作用: DB、ファイルストレージ、外部サービス、HTTPレスポンスのいずれかを操作する場合がある。
     */
    public function show(Package $package)
    {
        $package->load('packageGroup');
        $package->image_url = FileStorage::url($package->image_path);
        $package->pdf_url = FileStorage::url($package->pdf_path);
        return ApiResponse::success($package);
    }
    /**
     * 目的: パッケージの検証済み入力で更新する。
     * 機能: HTTP入力を検証し、Eloquent操作またはサービス処理を行い、JSONレスポンスへ包む。
     * 入力: $request, $package。
     * 出力: HTTP JSONレスポンス、ファイルレスポンス、またはnoContentレスポンス。
     * 動作条件: 認証済みユーザー、権限、バリデーション済み入力を前提にする。
     * 副作用: DB、ファイルストレージ、外部サービス、HTTPレスポンスのいずれかを操作する場合がある。
     */
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
    /**
     * 目的: パッケージの削除またはアーカイブする。
     * 機能: HTTP入力を検証し、Eloquent操作またはサービス処理を行い、JSONレスポンスへ包む。
     * 入力: $package。
     * 出力: HTTP JSONレスポンス、ファイルレスポンス、またはnoContentレスポンス。
     * 動作条件: 認証済みユーザー、権限、バリデーション済み入力を前提にする。
     * 副作用: DB、ファイルストレージ、外部サービス、HTTPレスポンスのいずれかを操作する場合がある。
     */
    public function destroy(Package $package)
    {
        $package->delete();
        return ApiResponse::noContent();
    }
    /**
     * 目的: パッケージのアーカイブ済みデータを復元する。
     * 機能: HTTP入力を検証し、Eloquent操作またはサービス処理を行い、JSONレスポンスへ包む。
     * 入力: $package。
     * 出力: HTTP JSONレスポンス、ファイルレスポンス、またはnoContentレスポンス。
     * 動作条件: 認証済みユーザー、権限、バリデーション済み入力を前提にする。
     * 副作用: DB、ファイルストレージ、外部サービス、HTTPレスポンスのいずれかを操作する場合がある。
     */
    public function restore(int $package)
    {
        $model = Package::withTrashed()->findOrFail($package);
        $model->restore();

        return ApiResponse::success($model);
    }
    /**
     * 目的: パッケージのforcedestroyを処理する。
     * 機能: HTTP入力を検証し、Eloquent操作またはサービス処理を行い、JSONレスポンスへ包む。
     * 入力: $package。
     * 出力: HTTP JSONレスポンス、ファイルレスポンス、またはnoContentレスポンス。
     * 動作条件: 認証済みユーザー、権限、バリデーション済み入力を前提にする。
     * 副作用: DB、ファイルストレージ、外部サービス、HTTPレスポンスのいずれかを操作する場合がある。
     */
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
