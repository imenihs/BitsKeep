<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Responses\ApiResponse;
use App\Models\SpecGroup;
use App\Models\SpecType;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class SpecSuggestionController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $categoryIds = collect((array) $request->input('category_ids', []))
            ->map(fn ($id) => (int) $id)
            ->filter()
            ->values()
            ->all();
        $q = trim((string) $request->query('q', ''));

        $groupsQuery = SpecGroup::query()
            ->with([
                'categories',
                'specTypes' => fn ($query) => $query->with(['units', 'aliases']),
                'templates' => fn ($query) => $query->with(['items.specType.units', 'items.specType.aliases']),
            ])
            ->orderBy('sort_order')
            ->orderBy('name');

        if ($categoryIds !== []) {
            $groupsQuery->where(function ($query) use ($categoryIds) {
                $query->whereHas('categories', fn ($categoryQuery) => $categoryQuery->whereIn('categories.id', $categoryIds))
                    ->orWhere('name', '共通');
            });
        }

        $groups = $groupsQuery->get();
        $specTypeIds = $groups->flatMap(fn (SpecGroup $group) => $group->specTypes->pluck('id'))->unique()->values();

        $specTypesQuery = SpecType::query()->with(['units', 'aliases']);
        if ($specTypeIds->isNotEmpty()) {
            $specTypesQuery->whereIn('id', $specTypeIds);
        }
        if ($q !== '') {
            $like = "%{$q}%";
            $specTypesQuery->where(function ($query) use ($like) {
                $query->where('name', 'like', $like)
                    ->orWhere('name_ja', 'like', $like)
                    ->orWhere('name_en', 'like', $like)
                    ->orWhere('symbol', 'like', $like)
                    ->orWhereHas('aliases', fn ($aliasQuery) => $aliasQuery->where('alias', 'like', $like));
            });
        }

        return ApiResponse::success([
            'groups' => $groups,
            'spec_types' => $specTypesQuery->orderBy('sort_order')->orderBy('name')->get(),
            'templates' => $groups->flatMap(fn (SpecGroup $group) => $group->templates)->values(),
        ]);
    }
}
