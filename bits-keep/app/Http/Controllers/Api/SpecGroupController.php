<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Responses\ApiResponse;
use App\Models\SpecGroup;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class SpecGroupController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $query = SpecGroup::query()
            ->where('name', '!=', '共通')
            ->withCount([
                'specTypes as usage_count',
                'templates as template_count',
            ]);

        if ($request->boolean('include_archived')) {
            $query->withTrashed();
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
                    ? "スペック詳細{$group->usage_count}件が候補に設定されています"
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

        $group = SpecGroup::create($this->validatedGroup($request));

        return ApiResponse::created($this->loadForEditor($group));
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

        $specGroup->update($this->validatedGroup($request, $specGroup));

        return ApiResponse::success($this->loadForEditor($specGroup));
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
            return ApiResponse::error("スペック詳細{$model->usage_count}件が候補に設定されているため完全削除できません", [], 422);
        }
        if ((int) $model->template_count > 0) {
            return ApiResponse::error("テンプレート{$model->template_count}件が所属中のため完全削除できません", [], 422);
        }

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
     * @return array<string, mixed>
     */
    private function validatedGroup(Request $request, ?SpecGroup $group = null): array
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:100', Rule::unique('spec_groups', 'name')->ignore($group?->id)],
            'description' => ['nullable', 'string', 'max:500'],
            'sort_order' => ['nullable', 'integer', 'min:0'],
        ]);

        return [
            'name' => $validated['name'],
            'description' => $validated['description'] ?? null,
            'sort_order' => $validated['sort_order'] ?? 0,
        ];
    }

    private function loadForEditor(SpecGroup $group): SpecGroup
    {
        $compactSpecType = fn ($q) => $q->select([
            'spec_types.id',
            'spec_types.name',
            'spec_types.name_ja',
            'spec_types.name_en',
            'spec_types.symbol',
            'spec_types.spec_scope',
            'spec_types.owner_spec_group_id',
            'spec_types.spec_kind',
            'spec_types.tolerance_settings',
            'spec_types.base_unit',
            'spec_types.sort_order',
        ]);

        return $group->load([
            'specTypes' => $compactSpecType,
            'templates' => fn ($q) => $q->with(['items.specType' => $compactSpecType]),
        ])->loadCount(['specTypes as usage_count', 'templates as template_count']);
    }
}
