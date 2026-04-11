<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreSupplierRequest;
use App\Http\Responses\ApiResponse;
use App\Models\Supplier;
use Illuminate\Http\Request;

class SupplierController extends Controller
{
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

    public function store(StoreSupplierRequest $request)
    {
        return ApiResponse::created(Supplier::create($request->validated()));
    }

    public function show(Supplier $supplier)
    {
        return ApiResponse::success($supplier->load('shippingRules'));
    }

    public function update(StoreSupplierRequest $request, Supplier $supplier)
    {
        $supplier->update($request->validated());
        return ApiResponse::success($supplier);
    }

    public function destroy(Supplier $supplier)
    {
        $supplier->delete();
        return ApiResponse::noContent();
    }

    public function restore(int $supplier)
    {
        $model = Supplier::withTrashed()->findOrFail($supplier);
        $model->restore();

        return ApiResponse::success($model);
    }
}
