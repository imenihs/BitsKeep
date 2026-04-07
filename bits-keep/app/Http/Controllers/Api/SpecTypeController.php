<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreSpecTypeRequest;
use App\Http\Responses\ApiResponse;
use App\Models\SpecType;
use App\Models\SpecUnit;
use Illuminate\Support\Facades\DB;

class SpecTypeController extends Controller
{
    public function index()
    {
        // 単位候補を一緒にロード
        $types = SpecType::with('units')->orderBy('sort_order')->orderBy('name')->get();
        return ApiResponse::success($types);
    }

    public function store(StoreSpecTypeRequest $request)
    {
        return DB::transaction(function () use ($request) {
            $specType = SpecType::create($request->safe()->except('units'));

            // 単位候補を一括挿入
            if ($request->filled('units')) {
                foreach ($request->units as $i => $unit) {
                    $specType->units()->create([
                        'unit'       => $unit['unit'],
                        'factor'     => $unit['factor'],
                        'sort_order' => $unit['sort_order'] ?? $i,
                    ]);
                }
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
            $specType->update($request->safe()->except('units'));

            // 単位候補は送信された配列で全置換
            if ($request->has('units')) {
                $specType->units()->delete();
                foreach ($request->units as $i => $unit) {
                    $specType->units()->create([
                        'unit'       => $unit['unit'],
                        'factor'     => $unit['factor'],
                        'sort_order' => $unit['sort_order'] ?? $i,
                    ]);
                }
            }

            return ApiResponse::success($specType->load('units'));
        });
    }

    public function destroy(SpecType $specType)
    {
        if ($specType->componentSpecs()->exists()) {
            return ApiResponse::error('このスペック種別は部品に使用されているため削除できません。', [], 409);
        }
        $specType->units()->delete();
        $specType->delete();
        return ApiResponse::noContent();
    }
}
