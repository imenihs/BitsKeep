<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreSpecTypeRequest;
use App\Http\Responses\ApiResponse;
use App\Models\SpecType;
use App\Models\SpecUnit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class SpecTypeController extends Controller
{
    public function index(Request $request)
    {
        $query = SpecType::with('units')->withCount('componentSpecs as usage_count');
        if ($request->boolean('include_archived')) {
            $query->withTrashed();
        }
        $types = $query->orderBy('sort_order')->orderBy('name')->get()->map(function (SpecType $type) {
            $type->can_force_delete = (bool) $type->deleted_at && $type->usage_count === 0;
            $type->force_delete_reason = $type->can_force_delete ? '' : ($type->usage_count > 0 ? "スペック{$type->usage_count}件で使用中" : '先にアーカイブしてください');
            return $type;
        });
        return ApiResponse::success($types);
    }

    public function store(StoreSpecTypeRequest $request)
    {
        return DB::transaction(function () use ($request) {
            $specType = SpecType::create($request->safe()->except('unit'));

            if ($request->filled('unit')) {
                $specType->units()->create([
                    'unit' => $request->string('unit')->toString(),
                    'factor' => 1,
                    'sort_order' => 0,
                ]);
            }

            return ApiResponse::created($specType->load('units'));
        });
    }

    public function show(SpecType $specType)
    {
        return ApiResponse::success($specType->load('units'));
    }

    public function update(StoreSpecTypeRequest $request, SpecType $specType)
    {
        return DB::transaction(function () use ($request, $specType) {
            $specType->update($request->safe()->except('unit'));

            if ($request->has('unit')) {
                $specType->units()->delete();
                if ($request->filled('unit')) {
                    $specType->units()->create([
                        'unit' => $request->string('unit')->toString(),
                        'factor' => 1,
                        'sort_order' => 0,
                    ]);
                }
            }

            return ApiResponse::success($specType->load('units'));
        });
    }

    public function destroy(SpecType $specType)
    {
        $specType->delete();
        return ApiResponse::noContent();
    }

    public function restore(int $specType)
    {
        $model = SpecType::withTrashed()->findOrFail($specType);
        $model->restore();

        return ApiResponse::success($model->load('units'));
    }

    public function forceDestroy(int $specType)
    {
        $model = SpecType::withTrashed()->withCount('componentSpecs as usage_count')->findOrFail($specType);
        if (!$model->deleted_at) {
            return ApiResponse::error('完全削除の前にアーカイブしてください', [], 422);
        }
        if ($model->usage_count > 0) {
            return ApiResponse::error("スペック{$model->usage_count}件で使用中のため完全削除できません", [], 422);
        }
        $model->units()->delete();
        $model->forceDelete();

        return ApiResponse::noContent();
    }
}
