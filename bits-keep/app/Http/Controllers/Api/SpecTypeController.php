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
        $query = SpecType::with(['units', 'aliases'])->withCount('componentSpecs as usage_count');
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
            $payload = $this->normalizePayload($request->safe()->except(['unit', 'aliases']));
            if ($request->filled('unit') && empty($payload['base_unit'])) {
                $payload['base_unit'] = $request->string('unit')->toString();
            }
            $specType = SpecType::create($payload);

            if ($request->filled('unit')) {
                $specType->units()->create([
                    'unit' => $request->string('unit')->toString(),
                    'factor' => 1,
                    'sort_order' => 0,
                ]);
            }
            $this->syncAliases($specType, (array) $request->input('aliases', []));

            return ApiResponse::created($specType->load(['units', 'aliases']));
        });
    }

    public function show(SpecType $specType)
    {
        return ApiResponse::success($specType->load(['units', 'aliases']));
    }

    public function update(StoreSpecTypeRequest $request, SpecType $specType)
    {
        return DB::transaction(function () use ($request, $specType) {
            $payload = $this->normalizePayload($request->safe()->except(['unit', 'aliases']));
            if ($request->has('unit')) {
                $payload['base_unit'] = $request->filled('unit') ? $request->string('unit')->toString() : null;
            }
            $specType->update($payload);

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
            if ($request->has('aliases')) {
                $this->syncAliases($specType, (array) $request->input('aliases', []));
            }

            return ApiResponse::success($specType->load(['units', 'aliases']));
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

        return ApiResponse::success($model->load(['units', 'aliases']));
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

    /**
     * @param  array<string, mixed>  $payload
     * @return array<string, mixed>
     */
    private function normalizePayload(array $payload): array
    {
        $nameJa = trim((string) ($payload['name_ja'] ?? ''));
        $name = trim((string) ($payload['name'] ?? ''));
        if ($nameJa === '' && $name !== '') {
            $payload['name_ja'] = $name;
        }
        if ($name === '' && $nameJa !== '') {
            $payload['name'] = $nameJa;
        }

        return $payload;
    }

    /**
     * @param  array<int, array<string, mixed>|string>  $aliases
     */
    private function syncAliases(SpecType $specType, array $aliases): void
    {
        $specType->aliases()->delete();

        foreach (array_values($aliases) as $index => $entry) {
            $alias = is_array($entry) ? trim((string) ($entry['alias'] ?? '')) : trim((string) $entry);
            if ($alias === '') {
                continue;
            }

            $specType->aliases()->create([
                'alias' => $alias,
                'locale' => is_array($entry) ? ($entry['locale'] ?? null) : null,
                'kind' => is_array($entry) ? ($entry['kind'] ?? null) : null,
                'sort_order' => ($index + 1) * 10,
            ]);
        }
    }
}
