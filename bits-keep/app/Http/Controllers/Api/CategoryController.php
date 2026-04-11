<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreCategoryRequest;
use App\Http\Responses\ApiResponse;
use App\Models\Category;
use Illuminate\Http\Request;

class CategoryController extends Controller
{
    // GET /api/categories
    public function index(Request $request)
    {
        $query = Category::query()->withCount('components as usage_count');
        if ($request->boolean('include_archived')) {
            $query->withTrashed();
        }
        $categories = $query->orderBy('sort_order')->orderBy('name')->get()->map(function (Category $category) {
            $category->can_force_delete = (bool) $category->deleted_at && $category->usage_count === 0;
            $category->force_delete_reason = $category->can_force_delete ? '' : ($category->usage_count > 0 ? "部品{$category->usage_count}件で使用中" : '先にアーカイブしてください');
            return $category;
        });
        return ApiResponse::success($categories);
    }

    // POST /api/categories
    public function store(StoreCategoryRequest $request)
    {
        $category = Category::create($request->validated());
        return ApiResponse::created($category);
    }

    // GET /api/categories/{category}
    public function show(Category $category)
    {
        return ApiResponse::success($category);
    }

    // PUT /api/categories/{category}
    public function update(StoreCategoryRequest $request, Category $category)
    {
        $category->update($request->validated());
        return ApiResponse::success($category);
    }

    // DELETE /api/categories/{category}
    public function destroy(Category $category)
    {
        $category->delete();
        return ApiResponse::noContent();
    }

    public function restore(int $category)
    {
        $model = Category::withTrashed()->findOrFail($category);
        $model->restore();

        return ApiResponse::success($model);
    }

    public function forceDestroy(int $category)
    {
        $model = Category::withTrashed()->withCount('components as usage_count')->findOrFail($category);
        if (!$model->deleted_at) {
            return ApiResponse::error('完全削除の前にアーカイブしてください', [], 422);
        }
        if ($model->usage_count > 0) {
            return ApiResponse::error("部品{$model->usage_count}件で使用中のため完全削除できません", [], 422);
        }
        $model->forceDelete();

        return ApiResponse::noContent();
    }
}
