<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreCategoryRequest;
use App\Http\Responses\ApiResponse;
use App\Models\Category;

class CategoryController extends Controller
{
    // GET /api/categories
    public function index()
    {
        $categories = Category::orderBy('sort_order')->orderBy('name')->get();
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
        // 紐づき部品がある場合は削除不可
        if ($category->components()->exists()) {
            return ApiResponse::error('この分類は部品に使用されているため削除できません。', [], 409);
        }
        $category->delete();
        return ApiResponse::noContent();
    }
}
