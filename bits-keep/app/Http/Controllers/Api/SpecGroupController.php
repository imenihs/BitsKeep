<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Responses\ApiResponse;
use App\Models\SpecGroup;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

class SpecGroupController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $query = SpecGroup::query()
            ->withCount([
                'specTypes as usage_count',
                'templates as template_count',
            ]);

        if ($request->boolean('include_archived')) {
            $query->withTrashed();
        }

        if ($request->boolean('with_categories')) {
            $query->with(['categories']);
        }

        if ($request->boolean('with_spec_types')) {
            $query->with([
                'specTypes' => fn ($q) => $q->with(['units', 'aliases']),
            ]);
        }

        if ($request->boolean('with_templates')) {
            $query->with([
                'templates' => fn ($q) => $q->with(['items.specType.units', 'items.specType.aliases']),
            ]);
        }

        $groups = $query->orderBy('sort_order')->orderBy('name')->get()->map(function (SpecGroup $group) {
            $group->can_force_delete = (bool) $group->deleted_at
                && (int) $group->usage_count === 0
                && (int) $group->template_count === 0;
            $group->force_delete_reason = $group->can_force_delete
                ? ''
                : ((int) $group->usage_count > 0
                    ? "スペック項目{$group->usage_count}件が所属中"
                    : ((int) $group->template_count > 0 ? "テンプレート{$group->template_count}件が所属中" : '先にアーカイブしてください'));

            return $group;
        });

        return ApiResponse::success($groups);
    }

    public function store(Request $request): JsonResponse
    {
        if (!$request->user()?->isAdmin()) {
            return ApiResponse::forbidden();
        }

        $data = $this->validatedGroup($request);

        return DB::transaction(function () use ($data) {
            $group = SpecGroup::create($data['attributes']);
            $this->syncCategories($group, $data['category_links']);

            return ApiResponse::created($this->loadForEditor($group));
        });
    }

    public function show(SpecGroup $specGroup): JsonResponse
    {
        return ApiResponse::success($this->loadForEditor($specGroup));
    }

    public function update(Request $request, SpecGroup $specGroup): JsonResponse
    {
        if (!$request->user()?->isAdmin()) {
            return ApiResponse::forbidden();
        }

        $data = $this->validatedGroup($request, $specGroup);

        return DB::transaction(function () use ($data, $specGroup) {
            $specGroup->update($data['attributes']);
            $this->syncCategories($specGroup, $data['category_links']);

            return ApiResponse::success($this->loadForEditor($specGroup));
        });
    }

    public function destroy(Request $request, SpecGroup $specGroup): JsonResponse
    {
        if (!$request->user()?->isAdmin()) {
            return ApiResponse::forbidden();
        }

        $specGroup->delete();

        return ApiResponse::noContent();
    }

    public function restore(Request $request, int $specGroup): JsonResponse
    {
        if (!$request->user()?->isAdmin()) {
            return ApiResponse::forbidden();
        }

        $model = SpecGroup::withTrashed()->findOrFail($specGroup);
        $model->restore();

        return ApiResponse::success($this->loadForEditor($model));
    }

    public function forceDestroy(Request $request, int $specGroup): JsonResponse
    {
        if (!$request->user()?->isAdmin()) {
            return ApiResponse::forbidden();
        }

        $model = SpecGroup::withTrashed()
            ->withCount(['specTypes as usage_count', 'templates as template_count'])
            ->findOrFail($specGroup);

        if (!$model->deleted_at) {
            return ApiResponse::error('完全削除の前にアーカイブしてください', [], 422);
        }
        if ((int) $model->usage_count > 0) {
            return ApiResponse::error("スペック項目{$model->usage_count}件が所属中のため完全削除できません", [], 422);
        }
        if ((int) $model->template_count > 0) {
            return ApiResponse::error("テンプレート{$model->template_count}件が所属中のため完全削除できません", [], 422);
        }

        $model->categories()->detach();
        $model->forceDelete();

        return ApiResponse::noContent();
    }

    public function syncSpecTypes(Request $request, SpecGroup $specGroup): JsonResponse
    {
        if (!$request->user()?->isAdmin()) {
            return ApiResponse::forbidden();
        }

        $validated = $request->validate([
            'items' => ['nullable', 'array'],
            'items.*.spec_type_id' => ['required', 'integer', 'exists:spec_types,id'],
            'items.*.sort_order' => ['nullable', 'integer', 'min:0'],
            'items.*.is_required' => ['nullable', 'boolean'],
            'items.*.is_recommended' => ['nullable', 'boolean'],
            'items.*.default_profile' => ['nullable', 'in:typ,range,max_only,min_only,triple'],
            'items.*.default_unit' => ['nullable', 'string', 'max:40'],
            'items.*.note' => ['nullable', 'string', 'max:255'],
        ]);

        $sync = [];
        foreach (array_values($validated['items'] ?? []) as $index => $item) {
            $specTypeId = (int) $item['spec_type_id'];
            $sync[$specTypeId] = [
                'sort_order' => (int) ($item['sort_order'] ?? (($index + 1) * 10)),
                'is_required' => (bool) ($item['is_required'] ?? false),
                'is_recommended' => (bool) ($item['is_recommended'] ?? true),
                'default_profile' => $item['default_profile'] ?? null,
                'default_unit' => $item['default_unit'] ?? null,
                'note' => $item['note'] ?? null,
            ];
        }

        $specGroup->specTypes()->sync($sync);

        return ApiResponse::success($this->loadForEditor($specGroup));
    }

    /**
     * @return array{attributes: array<string, mixed>, category_links: array<int, array<string, mixed>>}
     */
    private function validatedGroup(Request $request, ?SpecGroup $group = null): array
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:100', Rule::unique('spec_groups', 'name')->ignore($group?->id)],
            'description' => ['nullable', 'string', 'max:500'],
            'sort_order' => ['nullable', 'integer', 'min:0'],
            'category_links' => ['nullable', 'array'],
            'category_links.*.category_id' => ['required', 'integer', 'exists:categories,id'],
            'category_links.*.sort_order' => ['nullable', 'integer', 'min:0'],
            'category_links.*.is_primary' => ['nullable', 'boolean'],
        ]);

        return [
            'attributes' => [
                'name' => $validated['name'],
                'description' => $validated['description'] ?? null,
                'sort_order' => $validated['sort_order'] ?? 0,
            ],
            'category_links' => array_values($validated['category_links'] ?? []),
        ];
    }

    /**
     * @param  array<int, array<string, mixed>>  $categoryLinks
     */
    private function syncCategories(SpecGroup $group, array $categoryLinks): void
    {
        $sync = [];
        foreach ($categoryLinks as $index => $link) {
            $categoryId = (int) $link['category_id'];
            $sync[$categoryId] = [
                'sort_order' => (int) ($link['sort_order'] ?? (($index + 1) * 10)),
                'is_primary' => (bool) ($link['is_primary'] ?? false),
            ];
        }

        $group->categories()->sync($sync);
    }

    private function loadForEditor(SpecGroup $group): SpecGroup
    {
        return $group->load([
            'categories',
            'specTypes' => fn ($q) => $q->with(['units', 'aliases']),
            'templates' => fn ($q) => $q->with(['items.specType.units', 'items.specType.aliases']),
        ])->loadCount(['specTypes as usage_count', 'templates as template_count']);
    }
}
