<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreSpecTypeRequest;
use App\Http\Responses\ApiResponse;
use App\Models\SpecType;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class SpecTypeController extends Controller
{
    private const BYTE_BIT_BASE_UNITS = ['B', 'bit', 'bps'];

    private const BINARY_IEC_PREFIXES = ['Ti', 'Gi', 'Mi', 'Ki'];

    private const DECIMAL_NON_FRACTIONAL_PREFIXES = ['T', 'G', 'M', 'k'];

    private const DECIMAL_FRACTIONAL_PREFIXES = ['m', 'u', 'n', 'p', 'f'];

    private const BYTE_BIT_ALLOWED_PREFIXES = ['T', 'G', 'M', 'k', '', 'Ti', 'Gi', 'Mi', 'Ki'];

    public function index(Request $request)
    {
        if ($request->boolean('summary')) {
            $query = SpecType::query()->select([
                'id',
                'name',
                'name_ja',
                'name_en',
                'symbol',
                'spec_scope',
                'owner_spec_group_id',
                'spec_kind',
                'tolerance_settings',
                'base_unit',
                'sort_order',
                'deleted_at',
            ]);

            $this->applySpecTypeFilters($query, $request);

            if ($request->boolean('include_archived')) {
                $query->withTrashed();
            }

            return ApiResponse::success($query->orderBy('sort_order')->orderBy('name')->get());
        }

        $query = SpecType::with(['units', 'aliases', 'ownerSpecGroup', 'specGroups'])->withCount('componentSpecs as usage_count');
        $this->applySpecTypeFilters($query, $request);
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
            $payload = $request->safe()->except(['unit', 'aliases']);
            if ($request->filled('unit') && empty($payload['base_unit'])) {
                $payload['base_unit'] = $request->string('unit')->toString();
            }
            $payload = $this->normalizePayload($payload);
            $specType = SpecType::create($payload);

            if ($request->filled('unit')) {
                $specType->units()->create([
                    'unit' => $request->string('unit')->toString(),
                    'factor' => 1,
                    'sort_order' => 0,
                ]);
            }
            $this->syncAliases($specType, (array) $request->input('aliases', []));
            $this->attachOwnerGroup($specType);

            return ApiResponse::created($specType->load(['units', 'aliases', 'ownerSpecGroup']));
        });
    }

    public function show(SpecType $specType)
    {
        return ApiResponse::success($specType->load(['units', 'aliases', 'ownerSpecGroup']));
    }

    public function update(StoreSpecTypeRequest $request, SpecType $specType)
    {
        return DB::transaction(function () use ($request, $specType) {
            $payload = $request->safe()->except(['unit', 'aliases']);
            if (! $request->has('spec_scope')) {
                $payload['spec_scope'] = $specType->spec_scope;
            }
            if (! $request->has('owner_spec_group_id')) {
                $payload['owner_spec_group_id'] = $specType->owner_spec_group_id;
            }
            if (! $request->has('spec_kind')) {
                $payload['spec_kind'] = $specType->spec_kind;
            }
            if (! $request->has('tolerance_settings')) {
                $payload['tolerance_settings'] = $specType->tolerance_settings;
            }
            if ($request->has('unit')) {
                $payload['base_unit'] = $request->filled('unit') ? $request->string('unit')->toString() : null;
            } elseif (
                ! array_key_exists('base_unit', $payload)
                && (array_key_exists('suggest_prefixes', $payload) || array_key_exists('display_prefixes', $payload))
            ) {
                $payload['base_unit'] = $specType->base_unit;
            }
            $payload = $this->normalizePayload($payload);
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
            $this->attachOwnerGroup($specType);

            return ApiResponse::success($specType->load(['units', 'aliases', 'ownerSpecGroup']));
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

        return ApiResponse::success($model->load(['units', 'aliases', 'ownerSpecGroup']));
    }

    public function forceDestroy(int $specType)
    {
        $model = SpecType::withTrashed()->withCount('componentSpecs as usage_count')->findOrFail($specType);
        if (! $model->deleted_at) {
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

        $payload['spec_scope'] = $payload['spec_scope'] ?? SpecType::SCOPE_GROUP_LOCAL;
        if ($payload['spec_scope'] === SpecType::SCOPE_COMMON) {
            $payload['owner_spec_group_id'] = null;
        } elseif (array_key_exists('owner_spec_group_id', $payload) && $payload['owner_spec_group_id'] === '') {
            $payload['owner_spec_group_id'] = null;
        }

        $payload['spec_kind'] = $payload['spec_kind'] ?? SpecType::KIND_NORMAL;
        if ($payload['spec_kind'] !== SpecType::KIND_TOLERANCE) {
            $payload['tolerance_settings'] = null;
        } else {
            $payload['tolerance_settings'] = $this->normalizeToleranceSettings($payload['tolerance_settings'] ?? null);
        }

        foreach (['suggest_prefixes', 'display_prefixes'] as $key) {
            if (array_key_exists($key, $payload)) {
                $payload[$key] = $this->normalizePrefixList($payload[$key], (string) ($payload['base_unit'] ?? ''), $key);
            }
        }

        return $payload;
    }

    /**
     * @return array<int, string>|null
     */
    private function normalizePrefixList(mixed $prefixes, string $baseUnit, string $field): ?array
    {
        if ($prefixes === null) {
            return null;
        }

        if (! is_array($prefixes)) {
            return [];
        }

        $normalized = array_map(
            fn ($prefix) => $this->normalizePrefix($prefix),
            $prefixes
        );

        $normalized = array_values(array_unique($normalized));
        $hasIec = count(array_intersect($normalized, self::BINARY_IEC_PREFIXES)) > 0;

        if (! $this->isByteBitUnit($baseUnit)) {
            if ($hasIec) {
                throw ValidationException::withMessages([
                    $field => 'IEC接頭語は B / bit / bps 系のスペック詳細だけで使用できます。',
                ]);
            }

            return $normalized;
        }

        $invalid = array_values(array_filter(
            $normalized,
            fn ($prefix) => ! in_array($prefix, self::BYTE_BIT_ALLOWED_PREFIXES, true)
        ));
        if ($invalid !== []) {
            throw ValidationException::withMessages([
                $field => 'B / bit / bps 系では T/G/M/k/無印 または Ti/Gi/Mi/Ki だけを接頭語候補にできます。',
            ]);
        }

        $fractional = array_values(array_intersect($normalized, self::DECIMAL_FRACTIONAL_PREFIXES));
        if ($fractional !== []) {
            throw ValidationException::withMessages([
                $field => 'B / bit / bps 系では m/u/n/p/f のような小数系接頭語は使用できません。',
            ]);
        }

        $hasDecimal = count(array_intersect($normalized, self::DECIMAL_NON_FRACTIONAL_PREFIXES)) > 0;
        if ($hasIec && $hasDecimal) {
            throw ValidationException::withMessages([
                $field => 'B / bit / bps 系では 10進接頭語（T/G/M/k）と IEC 接頭語（Ti/Gi/Mi/Ki）を同時に選択できません。',
            ]);
        }

        return $normalized;
    }

    private function normalizePrefix(mixed $prefix): string
    {
        $normalized = $prefix === null ? '' : trim((string) $prefix);

        return $normalized === 'K' ? 'k' : $normalized;
    }

    private function isByteBitUnit(string $unit): bool
    {
        return in_array($this->normalizeUnitLabel($unit), self::BYTE_BIT_BASE_UNITS, true);
    }

    private function normalizeUnitLabel(string $unit): string
    {
        $normalized = trim(str_replace(['μ', 'µ', 'Ω'], ['u', 'u', 'Ω'], $unit));
        $normalized = preg_replace('/\bohms?\b/iu', 'Ω', $normalized) ?? $normalized;

        return preg_replace('/^K(?!i)(?=[A-Za-zΩ])/u', 'k', $normalized) ?? $normalized;
    }

    private function applySpecTypeFilters($query, Request $request): void
    {
        $scope = $request->query('scope');
        if (in_array($scope, [SpecType::SCOPE_COMMON, SpecType::SCOPE_GROUP_LOCAL], true)) {
            $query->where('spec_scope', $scope);
        }

        if ($request->filled('owner_spec_group_id')) {
            $query->where('owner_spec_group_id', (int) $request->query('owner_spec_group_id'));
        }

        $kind = $request->query('kind');
        if (in_array($kind, [SpecType::KIND_NORMAL, SpecType::KIND_TOLERANCE], true)) {
            $query->where('spec_kind', $kind);
        }
    }

    /**
     * @return array<string, mixed>
     */
    private function normalizeToleranceSettings(mixed $settings): array
    {
        $settings = is_array($settings) ? $settings : [];
        $defaultUnit = (string) ($settings['default_unit'] ?? $settings['unit'] ?? '%');
        $defaultMode = (string) ($settings['default_mode'] ?? $settings['mode'] ?? $settings['input_format'] ?? 'symmetric');
        $allowedUnits = array_values(array_unique(array_filter(array_map(
            fn ($unit) => trim((string) $unit),
            (array) ($settings['allowed_units'] ?? [$defaultUnit])
        ), fn ($unit) => $unit !== '')));
        $fallbackAllowedUnit = $defaultUnit !== '' ? $defaultUnit : '%';

        $gradeOptions = [];
        foreach ((array) ($settings['grade_options'] ?? []) as $option) {
            if (! is_array($option)) {
                continue;
            }

            $label = trim((string) ($option['label'] ?? $option['rank'] ?? ''));
            if ($label === '') {
                continue;
            }

            $normalized = [
                'label' => $label,
                'unit' => trim((string) ($option['unit'] ?? $defaultUnit)),
            ];
            foreach (['value', 'plus', 'minus'] as $key) {
                if (array_key_exists($key, $option) && $option[$key] !== null && $option[$key] !== '') {
                    $normalized[$key] = is_numeric($option[$key]) ? (float) $option[$key] : $option[$key];
                }
            }
            if (array_key_exists('text', $option) && trim((string) $option['text']) !== '') {
                $normalized['text'] = trim((string) $option['text']);
            }

            $gradeOptions[] = $normalized;
        }

        return [
            'default_mode' => in_array($defaultMode, ['symmetric', 'asymmetric', 'grade'], true) ? $defaultMode : 'symmetric',
            'default_unit' => $defaultUnit !== '' ? $defaultUnit : '%',
            'allowed_units' => $allowedUnits !== [] ? $allowedUnits : [$fallbackAllowedUnit],
            'grade_options' => $gradeOptions,
        ];
    }

    private function attachOwnerGroup(SpecType $specType): void
    {
        if ($specType->spec_scope !== SpecType::SCOPE_GROUP_LOCAL || ! $specType->owner_spec_group_id) {
            return;
        }

        $alreadyAttached = DB::table('spec_group_spec_type')
            ->where('spec_group_id', $specType->owner_spec_group_id)
            ->where('spec_type_id', $specType->id)
            ->exists();

        if ($alreadyAttached) {
            return;
        }

        $nextSortOrder = ((int) DB::table('spec_group_spec_type')
            ->where('spec_group_id', $specType->owner_spec_group_id)
            ->max('sort_order')) + 10;

        $specType->specGroups()->syncWithoutDetaching([
            $specType->owner_spec_group_id => [
                'sort_order' => $nextSortOrder,
                'is_required' => false,
                'is_recommended' => true,
                'default_profile' => 'typ',
                'default_unit' => $specType->base_unit,
                'note' => null,
            ],
        ]);
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
