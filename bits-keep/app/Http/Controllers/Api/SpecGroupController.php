<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Responses\ApiResponse;
use App\Models\SpecGroup;
use App\Models\SpecType;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Schema;
use Illuminate\Validation\Rule;

class SpecGroupController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $hasComponentSeries = $this->hasComponentSeriesTable();
        $hasSeriesManagementMode = $this->hasSeriesManagementModeColumn();
        $countRelations = $this->countRelations($hasComponentSeries);

        $query = SpecGroup::query()
            ->where('name', '!=', '共通')
            ->withCount($countRelations);

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

        $groups = $query->orderBy('sort_order')->orderBy('name')->get()->map(function (SpecGroup $group) use ($hasComponentSeries, $hasSeriesManagementMode) {
            if (! $hasComponentSeries) {
                $group->series_count = 0;
            }
            if (! $hasSeriesManagementMode) {
                $group->series_management_mode = 'single';
            }

            $group->can_force_delete = (bool) $group->deleted_at
                && (int) $group->usage_count === 0
                && (int) $group->template_count === 0
                && (int) $group->series_count === 0;
            $group->force_delete_reason = $group->can_force_delete
                ? ''
                : ((int) $group->usage_count > 0
                    ? "スペック詳細{$group->usage_count}件が候補に設定されています"
                    : ((int) $group->template_count > 0
                        ? "テンプレート{$group->template_count}件が所属中"
                        : ((int) $group->series_count > 0 ? "部品シリーズ{$group->series_count}件が所属中" : '先にアーカイブしてください')));

            return $group;
        });

        return ApiResponse::success($groups);
    }

    public function store(Request $request): JsonResponse
    {
        if (! $request->user()?->isAdmin()) {
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
        if (! $request->user()?->isAdmin()) {
            return ApiResponse::forbidden();
        }

        $specGroup->update($this->validatedGroup($request, $specGroup));

        return ApiResponse::success($this->loadForEditor($specGroup));
    }

    public function destroy(Request $request, SpecGroup $specGroup): JsonResponse
    {
        if (! $request->user()?->isAdmin()) {
            return ApiResponse::forbidden();
        }

        $specGroup->delete();

        return ApiResponse::noContent();
    }

    public function restore(Request $request, int $specGroup): JsonResponse
    {
        if (! $request->user()?->isAdmin()) {
            return ApiResponse::forbidden();
        }

        $model = SpecGroup::withTrashed()->findOrFail($specGroup);
        $model->restore();

        return ApiResponse::success($this->loadForEditor($model));
    }

    public function forceDestroy(Request $request, int $specGroup): JsonResponse
    {
        if (! $request->user()?->isAdmin()) {
            return ApiResponse::forbidden();
        }

        $countRelations = $this->countRelations($this->hasComponentSeriesTable());

        $model = SpecGroup::withTrashed()
            ->withCount($countRelations)
            ->findOrFail($specGroup);

        if (! $model->deleted_at) {
            return ApiResponse::error('完全削除の前にアーカイブしてください', [], 422);
        }
        if ((int) $model->usage_count > 0) {
            return ApiResponse::error("スペック詳細{$model->usage_count}件が候補に設定されているため完全削除できません", [], 422);
        }
        if ((int) $model->template_count > 0) {
            return ApiResponse::error("テンプレート{$model->template_count}件が所属中のため完全削除できません", [], 422);
        }
        if ((int) ($model->series_count ?? 0) > 0) {
            return ApiResponse::error("部品シリーズ{$model->series_count}件が所属中のため完全削除できません", [], 422);
        }

        $model->forceDelete();

        return ApiResponse::noContent();
    }

    public function syncSpecTypes(Request $request, SpecGroup $specGroup): JsonResponse
    {
        if (! $request->user()?->isAdmin()) {
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
            'series_management_mode' => ['nullable', Rule::in(['single', 'series_optional', 'series_recommended'])],
        ]);

        $payload = [
            'name' => $validated['name'],
            'description' => $validated['description'] ?? null,
            'sort_order' => $validated['sort_order'] ?? 0,
        ];
        if ($this->hasSeriesManagementModeColumn()) {
            $payload['series_management_mode'] = $validated['series_management_mode'] ?? 'single';
        }

        return $payload;
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

        $hasComponentSeries = $this->hasComponentSeriesTable();
        $countRelations = $this->countRelations($hasComponentSeries);

        $group->load([
            'specTypes' => $compactSpecType,
            'templates' => fn ($q) => $q->with(['items.specType' => $compactSpecType]),
        ])->loadCount($countRelations);

        if (! $hasComponentSeries) {
            $group->series_count = 0;
        }
        if (! $this->hasSeriesManagementModeColumn()) {
            $group->series_management_mode = 'single';
        }

        return $group;
    }

    /**
     * @return array<int|string, mixed>
     */
    private function countRelations(bool $includeSeries = true): array
    {
        $relations = [
            'specTypes as usage_count',
            'specTypes as local_candidate_count' => fn ($q) => $q
                ->where('spec_scope', SpecType::SCOPE_GROUP_LOCAL)
                ->where('spec_kind', SpecType::KIND_NORMAL),
            'specTypes as common_candidate_count' => fn ($q) => $q
                ->where('spec_scope', SpecType::SCOPE_COMMON)
                ->where('spec_kind', SpecType::KIND_NORMAL),
            'specTypes as tolerance_candidate_count' => fn ($q) => $q
                ->where('spec_scope', SpecType::SCOPE_COMMON)
                ->where('spec_kind', SpecType::KIND_TOLERANCE),
            'ownedSpecTypes as owned_spec_type_count',
            'templates as template_count',
        ];

        if ($includeSeries) {
            $relations[] = 'componentSeries as series_count';
        }

        return $relations;
    }

    private function hasComponentSeriesTable(): bool
    {
        return Schema::hasTable('component_series');
    }

    private function hasSeriesManagementModeColumn(): bool
    {
        return Schema::hasColumn('spec_groups', 'series_management_mode');
    }
}
