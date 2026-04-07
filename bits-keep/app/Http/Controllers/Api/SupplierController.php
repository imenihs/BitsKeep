<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreSupplierRequest;
use App\Http\Responses\ApiResponse;
use App\Models\Supplier;

class SupplierController extends Controller
{
    public function index()
    {
        return ApiResponse::success(Supplier::orderBy('name')->get());
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
        if ($supplier->componentSuppliers()->exists()) {
            return ApiResponse::error('この商社は部品に使用されているため削除できません。', [], 409);
        }
        $supplier->delete();
        return ApiResponse::noContent();
    }
}
