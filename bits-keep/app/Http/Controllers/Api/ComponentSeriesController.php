<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Responses\ApiResponse;
use App\Models\Component;
use App\Models\ComponentSeries;
use App\Models\ComponentSeriesValue;
use App\Models\ComponentSeriesValuePolicy;
use App\Models\SpecType;
use App\Services\ComponentSeriesValueGenerator;
use App\Services\SpecValueNormalizerService;
use App\Support\EngineeringUnits;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class ComponentSeriesController extends Controller
{
    /**
     * 目的: ComponentSeriesAPIの依存オブジェクトを受け取り、後続処理で使える状態にする。
     * 機能: 呼び出し元から受けた値を検証または整形し、対象処理へ渡す。
     * 入力: 関数シグネチャで指定された引数。
     * 出力: 型宣言または呼び出し規約に従う処理結果。
     * 動作条件: 呼び出し元が必要な依存オブジェクトと入力値を渡すこと。
     * 副作用: 依存オブジェクト、DB、ファイル、外部API、モデル状態のいずれかを更新する場合がある。
     */
    public function __construct(
        private readonly ComponentSeriesValueGenerator $generator,
    ) {}
    /**
     * 目的: 部品系列の一覧を検索条件付きで返す。
     * 機能: HTTP入力を検証し、Eloquent操作またはサービス処理を行い、JSONレスポンスへ包む。
     * 入力: $request。
     * 出力: HTTP JSONレスポンス、ファイルレスポンス、またはnoContentレスポンス。
     * 動作条件: 認証済みユーザー、権限、バリデーション済み入力を前提にする。
     * 副作用: DB、ファイルストレージ、外部サービス、HTTPレスポンスのいずれかを操作する場合がある。
     */
    public function index(Request $request): JsonResponse
    {
        $query = ComponentSeries::query()
            ->with(['specGroup', 'valueSpecType', 'package.packageGroup', 'policy'])
            ->withCount([
                'values as values_count',
                'values as enabled_values_count' => fn ($q) => $q->where('is_enabled', true),
                'components as materialized_components_count',
            ]);

        if ($request->boolean('include_archived')) {
            $query->withTrashed();
        }
        if ($specGroupId = $request->integer('spec_group_id')) {
            $query->where('spec_group_id', $specGroupId);
        }
        if ($q = trim((string) $request->input('q', ''))) {
            $query->where(function ($sub) use ($q) {
                $sub->where('name', 'ilike', "%{$q}%")
                    ->orWhere('manufacturer', 'ilike', "%{$q}%")
                    ->orWhere('description', 'ilike', "%{$q}%");
            });
        }

        return ApiResponse::success($query->orderBy('sort_order')->orderBy('name')->get());
    }
    /**
     * 目的: 部品系列の検証済み入力から新規作成する。
     * 機能: HTTP入力を検証し、Eloquent操作またはサービス処理を行い、JSONレスポンスへ包む。
     * 入力: $request。
     * 出力: HTTP JSONレスポンス、ファイルレスポンス、またはnoContentレスポンス。
     * 動作条件: 認証済みユーザー、権限、バリデーション済み入力を前提にする。
     * 副作用: DB、ファイルストレージ、外部サービス、HTTPレスポンスのいずれかを操作する場合がある。
     */
    public function store(Request $request): JsonResponse
    {
        if (! $request->user()?->isEditor()) {
            return ApiResponse::forbidden();
        }

        return DB::transaction(function () use ($request) {
            $seriesPayload = $this->validatedSeries($request);
            $series = ComponentSeries::create($seriesPayload + [
                'created_by' => $request->user()->id,
                'updated_by' => $request->user()->id,
            ]);
            $policy = $series->policy()->create($this->validatedPolicy($request, $seriesPayload['value_spec_type_id'] ?? null));
            $this->syncGeneratedValues($series, $policy);

            return ApiResponse::created($this->loadSeries($series));
        });
    }
    /**
     * 目的: 部品系列の詳細を返す。
     * 機能: HTTP入力を検証し、Eloquent操作またはサービス処理を行い、JSONレスポンスへ包む。
     * 入力: $componentSeries。
     * 出力: HTTP JSONレスポンス、ファイルレスポンス、またはnoContentレスポンス。
     * 動作条件: 認証済みユーザー、権限、バリデーション済み入力を前提にする。
     * 副作用: DB、ファイルストレージ、外部サービス、HTTPレスポンスのいずれかを操作する場合がある。
     */
    public function show(ComponentSeries $componentSeries): JsonResponse
    {
        return ApiResponse::success($this->loadSeries($componentSeries));
    }
    /**
     * 目的: 部品系列の検証済み入力で更新する。
     * 機能: HTTP入力を検証し、Eloquent操作またはサービス処理を行い、JSONレスポンスへ包む。
     * 入力: $request, $componentSeries。
     * 出力: HTTP JSONレスポンス、ファイルレスポンス、またはnoContentレスポンス。
     * 動作条件: 認証済みユーザー、権限、バリデーション済み入力を前提にする。
     * 副作用: DB、ファイルストレージ、外部サービス、HTTPレスポンスのいずれかを操作する場合がある。
     */
    public function update(Request $request, ComponentSeries $componentSeries): JsonResponse
    {
        if (! $request->user()?->isEditor()) {
            return ApiResponse::forbidden();
        }

        return DB::transaction(function () use ($request, $componentSeries) {
            $seriesPayload = $this->validatedSeries($request, $componentSeries);
            $componentSeries->update($seriesPayload + [
                'updated_by' => $request->user()->id,
            ]);
            $policy = $componentSeries->policy()->updateOrCreate(
                ['component_series_id' => $componentSeries->id],
                $this->validatedPolicy($request, $seriesPayload['value_spec_type_id'] ?? null)
            );
            $this->syncGeneratedValues($componentSeries, $policy);

            return ApiResponse::success($this->loadSeries($componentSeries));
        });
    }
    /**
     * 目的: 部品系列の削除またはアーカイブする。
     * 機能: HTTP入力を検証し、Eloquent操作またはサービス処理を行い、JSONレスポンスへ包む。
     * 入力: $request, $componentSeries。
     * 出力: HTTP JSONレスポンス、ファイルレスポンス、またはnoContentレスポンス。
     * 動作条件: 認証済みユーザー、権限、バリデーション済み入力を前提にする。
     * 副作用: DB、ファイルストレージ、外部サービス、HTTPレスポンスのいずれかを操作する場合がある。
     */
    public function destroy(Request $request, ComponentSeries $componentSeries): JsonResponse
    {
        if (! $request->user()?->isAdmin()) {
            return ApiResponse::forbidden();
        }

        $componentSeries->delete();

        return ApiResponse::noContent();
    }
    /**
     * 目的: 部品系列のアーカイブ済みデータを復元する。
     * 機能: HTTP入力を検証し、Eloquent操作またはサービス処理を行い、JSONレスポンスへ包む。
     * 入力: $request, $componentSeries。
     * 出力: HTTP JSONレスポンス、ファイルレスポンス、またはnoContentレスポンス。
     * 動作条件: 認証済みユーザー、権限、バリデーション済み入力を前提にする。
     * 副作用: DB、ファイルストレージ、外部サービス、HTTPレスポンスのいずれかを操作する場合がある。
     */
    public function restore(Request $request, int $componentSeries): JsonResponse
    {
        if (! $request->user()?->isAdmin()) {
            return ApiResponse::forbidden();
        }

        $series = ComponentSeries::withTrashed()->findOrFail($componentSeries);
        $series->restore();

        return ApiResponse::success($this->loadSeries($series));
    }
    /**
     * 目的: 部品系列の保存前プレビューを生成する。
     * 機能: HTTP入力を検証し、Eloquent操作またはサービス処理を行い、JSONレスポンスへ包む。
     * 入力: $request。
     * 出力: HTTP JSONレスポンス、ファイルレスポンス、またはnoContentレスポンス。
     * 動作条件: 認証済みユーザー、権限、バリデーション済み入力を前提にする。
     * 副作用: DB、ファイルストレージ、外部サービス、HTTPレスポンスのいずれかを操作する場合がある。
     */
    public function preview(Request $request): JsonResponse
    {
        if (! $request->user()?->isEditor()) {
            return ApiResponse::forbidden();
        }

        $policy = $this->validatedPolicy($request, $request->integer('value_spec_type_id') ?: null);

        return ApiResponse::success([
            'values' => $this->generator->generate($policy),
        ]);
    }
    /**
     * 目的: 部品系列の同期値を処理する。
     * 機能: HTTP入力を検証し、Eloquent操作またはサービス処理を行い、JSONレスポンスへ包む。
     * 入力: $request, $componentSeries。
     * 出力: HTTP JSONレスポンス、ファイルレスポンス、またはnoContentレスポンス。
     * 動作条件: 認証済みユーザー、権限、バリデーション済み入力を前提にする。
     * 副作用: DB、ファイルストレージ、外部サービス、HTTPレスポンスのいずれかを操作する場合がある。
     */
    public function syncValues(Request $request, ComponentSeries $componentSeries): JsonResponse
    {
        if (! $request->user()?->isEditor()) {
            return ApiResponse::forbidden();
        }

        $validated = $request->validate([
            'values' => ['required', 'array'],
            'values.*.value_text' => ['required', 'string', 'max:100'],
            'values.*.value_numeric' => ['nullable', 'numeric'],
            'values.*.unit' => ['nullable', 'string', 'max:40'],
            'values.*.origin' => ['nullable', 'string', 'max:32'],
            'values.*.source_series' => ['nullable', 'string', 'max:16'],
            'values.*.is_enabled' => ['nullable', 'boolean'],
            'values.*.is_stocked' => ['nullable', 'boolean'],
            'values.*.note' => ['nullable', 'string', 'max:1000'],
        ]);

        DB::transaction(function () use ($validated, $componentSeries) {
            foreach (array_values($validated['values']) as $index => $item) {
                $unit = trim((string) ($item['unit'] ?? ''));
                $numeric = array_key_exists('value_numeric', $item) && $item['value_numeric'] !== null
                    ? (float) $item['value_numeric']
                    : $this->generator->parseEngineeringNumber($item['value_text'], null, $unit);
                if ($numeric === null) {
                    continue;
                }
                $key = $this->generator->valueKey($numeric, $unit);

                $componentSeries->values()->updateOrCreate(
                    ['value_key' => $key],
                    [
                        'value_text' => $item['value_text'],
                        'value_numeric' => $numeric,
                        'unit' => $unit,
                        'origin' => $item['origin'] ?? 'manual',
                        'source_series' => $item['source_series'] ?? null,
                        'is_enabled' => (bool) ($item['is_enabled'] ?? true),
                        'is_stocked' => (bool) ($item['is_stocked'] ?? false),
                        'sort_order' => ($index + 1) * 10,
                        'note' => $item['note'] ?? null,
                    ]
                );
            }
        });

        return ApiResponse::success($this->loadSeries($componentSeries));
    }
    /**
     * 目的: 部品系列のmaterializeを処理する。
     * 機能: HTTP入力を検証し、Eloquent操作またはサービス処理を行い、JSONレスポンスへ包む。
     * 入力: $request, $componentSeries。
     * 出力: HTTP JSONレスポンス、ファイルレスポンス、またはnoContentレスポンス。
     * 動作条件: 認証済みユーザー、権限、バリデーション済み入力を前提にする。
     * 副作用: DB、ファイルストレージ、外部サービス、HTTPレスポンスのいずれかを操作する場合がある。
     */
    public function materialize(Request $request, ComponentSeries $componentSeries): JsonResponse
    {
        if (! $request->user()?->isEditor()) {
            return ApiResponse::forbidden();
        }

        $validated = $request->validate([
            'value_ids' => ['required', 'array', 'min:1'],
            'value_ids.*' => ['integer', 'exists:component_series_values,id'],
            'part_number_template' => ['nullable', 'string', 'max:200'],
            'common_name_template' => ['nullable', 'string', 'max:200'],
        ]);

        $values = $componentSeries->values()
            ->whereIn('id', array_map('intval', $validated['value_ids']))
            ->where('is_enabled', true)
            ->get();

        $created = [];
        $skipped = [];

        DB::transaction(function () use ($request, $componentSeries, $values, $validated, &$created, &$skipped) {
            $componentSeries->loadMissing(['specGroup', 'valueSpecType', 'package']);
            $specType = $componentSeries->valueSpecType;
            $normalizer = app(SpecValueNormalizerService::class);

            foreach ($values as $value) {
                if ($value->materialized_component_id) {
                    $skipped[] = ['value_id' => $value->id, 'reason' => 'already_materialized'];

                    continue;
                }

                $partNumber = $this->renderTemplate(
                    $validated['part_number_template'] ?? '{series}-{value}',
                    $componentSeries,
                    $value
                );
                $commonName = $this->renderTemplate(
                    $validated['common_name_template'] ?? '{series} {value}',
                    $componentSeries,
                    $value
                );

                $component = Component::create([
                    'manufacturer' => $componentSeries->manufacturer,
                    'part_number' => $partNumber,
                    'common_name' => $commonName,
                    'description' => trim((string) $componentSeries->description) ?: null,
                    'procurement_status' => 'active',
                    'quantity_new' => 0,
                    'quantity_used' => 0,
                    'threshold_new' => 0,
                    'threshold_used' => 0,
                    'package_id' => $componentSeries->package_id,
                    'component_series_id' => $componentSeries->id,
                    'component_series_value_id' => $value->id,
                    'created_by' => $request->user()->id,
                    'updated_by' => $request->user()->id,
                ]);

                if ($componentSeries->spec_group_id) {
                    $component->categories()->syncWithoutDetaching([$componentSeries->spec_group_id]);
                }
                if ($specType) {
                    $normalized = $normalizer->normalizeSpecPayload($specType, [
                        'spec_type_id' => $specType->id,
                        'value_profile' => 'typ',
                        'value' => $value->value_text,
                        'unit' => $value->unit,
                    ]);
                    $component->specs()->create([
                        'spec_type_id' => $specType->id,
                        'value' => $normalized['value'] ?? $value->value_text,
                        'unit' => $normalized['unit'] ?? $value->unit,
                        'value_profile' => $normalized['value_profile'] ?? 'typ',
                        'value_mode' => $normalized['value_mode'] ?? 'single',
                        'value_numeric' => $normalized['value_numeric'] ?? null,
                        'value_numeric_typ' => $normalized['value_numeric_typ'] ?? $value->value_numeric,
                        'value_numeric_min' => $normalized['value_numeric_min'] ?? null,
                        'value_numeric_max' => $normalized['value_numeric_max'] ?? null,
                        'normalized_unit' => $normalized['normalized_unit'] ?? $specType->base_unit ?? $value->unit,
                    ]);
                }

                $value->forceFill([
                    'materialized_component_id' => $component->id,
                    'is_stocked' => true,
                ])->save();

                $created[] = [
                    'value_id' => $value->id,
                    'component_id' => $component->id,
                    'part_number' => $component->part_number,
                ];
            }
        });

        return ApiResponse::success([
            'created' => $created,
            'skipped' => $skipped,
            'series' => $this->loadSeries($componentSeries),
        ]);
    }

    /**
     * 目的: 部品系列のvalidated系列を処理する。
     * 機能: HTTP入力を検証し、Eloquent操作またはサービス処理を行い、JSONレスポンスへ包む。
     * 入力: $request, $series。
     * 出力: HTTP JSONレスポンス、ファイルレスポンス、またはnoContentレスポンス。
     * 動作条件: 認証済みユーザー、権限、バリデーション済み入力を前提にする。
     * 副作用: DB、ファイルストレージ、外部サービス、HTTPレスポンスのいずれかを操作する場合がある。
     * @return array<string, mixed>
     */
    private function validatedSeries(Request $request, ?ComponentSeries $series = null): array
    {
        $validated = $request->validate([
            'spec_group_id' => ['nullable', 'integer', 'exists:spec_groups,id'],
            'value_spec_type_id' => ['nullable', 'integer', 'exists:spec_types,id'],
            'package_id' => ['nullable', 'integer', 'exists:packages,id'],
            'manufacturer' => ['nullable', 'string', 'max:200'],
            'name' => [
                'required',
                'string',
                'max:200',
                Rule::unique('component_series', 'name')
                    ->where( fn ($q) => $q->where('manufacturer', $request->input('manufacturer')))
                    ->ignore($series?->id),
            ],
            'description' => ['nullable', 'string', 'max:2000'],
            'status' => ['nullable', Rule::in(['active', 'archived', 'planning'])],
            'sort_order' => ['nullable', 'integer', 'min:0'],
        ]);

        return [
            'spec_group_id' => $validated['spec_group_id'] ?? null,
            'value_spec_type_id' => $validated['value_spec_type_id'] ?? null,
            'package_id' => $validated['package_id'] ?? null,
            'manufacturer' => $validated['manufacturer'] ?? null,
            'name' => $validated['name'],
            'description' => $validated['description'] ?? null,
            'status' => $validated['status'] ?? 'active',
            'sort_order' => $validated['sort_order'] ?? 0,
        ];
    }

    /**
     * 目的: 部品系列のvalidatedポリシーを処理する。
     * 機能: HTTP入力を検証し、Eloquent操作またはサービス処理を行い、JSONレスポンスへ包む。
     * 入力: $request, $valueSpecTypeId。
     * 出力: HTTP JSONレスポンス、ファイルレスポンス、またはnoContentレスポンス。
     * 動作条件: 認証済みユーザー、権限、バリデーション済み入力を前提にする。
     * 副作用: DB、ファイルストレージ、外部サービス、HTTPレスポンスのいずれかを操作する場合がある。
     * @return array<string, mixed>
     */
    private function validatedPolicy(Request $request, ?int $valueSpecTypeId = null): array
    {
        $validated = $request->validate([
            'policy' => ['nullable', 'array'],
            'policy.value_set_type' => ['nullable', Rule::in(['e_series', 'hybrid_series', 'custom_list', 'range_step', 'none'])],
            'policy.primary_series' => ['nullable', Rule::in(array_keys(ComponentSeriesValueGenerator::E_SERIES))],
            'policy.extra_series' => ['nullable'],
            'policy.extra_series.*' => [Rule::in(array_keys(ComponentSeriesValueGenerator::E_SERIES))],
            'policy.custom_values' => ['nullable'],
            'policy.extra_values' => ['nullable'],
            'policy.excluded_values' => ['nullable'],
            'policy.unit' => ['nullable', 'string', 'max:40'],
            'policy.decade_min' => ['nullable', 'integer', 'min:-15', 'max:14'],
            'policy.decade_max' => ['nullable', 'integer', 'min:-15', 'max:14'],
            'policy.range_min' => ['nullable'],
            'policy.range_max' => ['nullable'],
            'policy.range_step' => ['nullable'],
            'policy.rounding_digits' => ['nullable', 'integer', 'min:0', 'max:15'],
            'policy.generation_settings' => ['nullable', 'array'],
            'policy.generation_settings.include_zero' => ['nullable', 'boolean'],
            'policy.generation_settings.input_prefixes' => ['nullable', 'array'],
            'policy.generation_settings.input_prefixes.*' => ['nullable', 'string', 'max:4'],
            'policy.generation_settings.display_prefixes' => ['nullable', 'array'],
            'policy.generation_settings.display_prefixes.*' => ['nullable', 'string', 'max:4'],
        ], [
            'policy.decade_min.integer' => ':attributeは整数で入力してください。例: -1, 0, 3, 10',
            'policy.decade_min.min' => ':attributeは -15 以上で入力してください。',
            'policy.decade_min.max' => ':attributeは 14 以下で入力してください。',
            'policy.decade_max.integer' => ':attributeは整数で入力してください。例: -1, 0, 3, 10',
            'policy.decade_max.min' => ':attributeは -15 以上で入力してください。',
            'policy.decade_max.max' => ':attributeは 14 以下で入力してください。',
            'policy.primary_series.in' => ':attributeは E6、E12、E24、E48、E96 から選んでください。',
            'policy.extra_series.*.in' => '追加E系列は E6、E12、E24、E48、E96 から選んでください。',
        ], [
            'policy.value_set_type' => '値の作り方',
            'policy.primary_series' => '基準E系列',
            'policy.extra_series' => '追加E系列',
            'policy.unit' => '単位',
            'policy.decade_min' => 'E系列の開始桁',
            'policy.decade_max' => 'E系列の終了桁',
            'policy.range_min' => '範囲/刻みの開始値',
            'policy.range_max' => '範囲/刻みの終了値',
            'policy.range_step' => '範囲/刻みの刻み幅',
        ]);

        $policy = $validated['policy'] ?? [];

        $normalized = [
            'value_set_type' => $policy['value_set_type'] ?? 'none',
            'primary_series' => $policy['primary_series'] ?? null,
            'extra_series' => $this->normalizeJsonList($policy['extra_series'] ?? []),
            'custom_values' => $this->normalizeJsonList($policy['custom_values'] ?? []),
            'extra_values' => $this->normalizeJsonList($policy['extra_values'] ?? []),
            'excluded_values' => $this->normalizeJsonList($policy['excluded_values'] ?? []),
            'unit' => $policy['unit'] ?? null,
            'decade_min' => $policy['decade_min'] ?? null,
            'decade_max' => $policy['decade_max'] ?? null,
            'range_min' => $policy['range_min'] ?? null,
            'range_max' => $policy['range_max'] ?? null,
            'range_step' => $policy['range_step'] ?? null,
            'rounding_digits' => $policy['rounding_digits'] ?? 15,
            'generation_settings' => $policy['generation_settings'] ?? null,
        ];

        $normalized = $this->applySpecTypePrefixPolicy($normalized, $valueSpecTypeId);

        return $this->normalizePolicyForStorage($normalized);
    }

    /**
     * 目的: 部品系列の適用スペックtype接頭語ポリシーを処理する。
     * 機能: HTTP入力を検証し、Eloquent操作またはサービス処理を行い、JSONレスポンスへ包む。
     * 入力: $policy, $valueSpecTypeId。
     * 出力: HTTP JSONレスポンス、ファイルレスポンス、またはnoContentレスポンス。
     * 動作条件: 認証済みユーザー、権限、バリデーション済み入力を前提にする。
     * 副作用: DB、ファイルストレージ、外部サービス、HTTPレスポンスのいずれかを操作する場合がある。
     * @param  array<string, mixed>  $policy
     * @return array<string, mixed>
     */
    private function applySpecTypePrefixPolicy(array $policy, ?int $valueSpecTypeId): array
    {
        $settings = is_array($policy['generation_settings'] ?? null) ? $policy['generation_settings'] : [];
        $settings['input_prefixes'] = $this->normalizePrefixList($settings['input_prefixes'] ?? null);
        $settings['display_prefixes'] = $this->normalizePrefixList($settings['display_prefixes'] ?? null);

        $specType = $valueSpecTypeId ? SpecType::query()->find($valueSpecTypeId) : null;
        if ($specType) {
            $inputPrefixes = $this->normalizePrefixList($specType->suggest_prefixes);
            $displayPrefixes = $this->normalizePrefixList($specType->display_prefixes);
            if ($inputPrefixes !== null) {
                $settings['input_prefixes'] = $inputPrefixes;
            }
            if ($displayPrefixes !== null) {
                $settings['display_prefixes'] = $displayPrefixes;
            }
            $settings['prefix_source_spec_type_id'] = $specType->id;
        }

        $policy['generation_settings'] = $settings;

        return $policy;
    }

    /**
     * 目的: 部品系列の入力prefixesfromポリシーを処理する。
     * 機能: HTTP入力を検証し、Eloquent操作またはサービス処理を行い、JSONレスポンスへ包む。
     * 入力: $policy。
     * 出力: HTTP JSONレスポンス、ファイルレスポンス、またはnoContentレスポンス。
     * 動作条件: 認証済みユーザー、権限、バリデーション済み入力を前提にする。
     * 副作用: DB、ファイルストレージ、外部サービス、HTTPレスポンスのいずれかを操作する場合がある。
     * @param  array<string, mixed>  $policy
     * @return array<int, string>|null
     */
    private function inputPrefixesFromPolicy(array $policy): ?array
    {
        $settings = is_array($policy['generation_settings'] ?? null) ? $policy['generation_settings'] : [];

        return $this->normalizePrefixList($settings['input_prefixes'] ?? null);
    }

    /**
     * 目的: 部品系列の正規化接頭語一覧を処理する。
     * 機能: HTTP入力を検証し、Eloquent操作またはサービス処理を行い、JSONレスポンスへ包む。
     * 入力: $prefixes。
     * 出力: HTTP JSONレスポンス、ファイルレスポンス、またはnoContentレスポンス。
     * 動作条件: 認証済みユーザー、権限、バリデーション済み入力を前提にする。
     * 副作用: なし。
     * @return array<int, string>|null
     */
    private function normalizePrefixList(mixed $prefixes): ?array
    {
        if (! is_array($prefixes) || $prefixes === []) {
            return null;
        }

        $invalid = EngineeringUnits::invalidPrefixes($prefixes, EngineeringUnits::UNIVERSAL_PREFIX_ORDER);
        if ($invalid !== []) {
            throw ValidationException::withMessages([
                'generation_settings' => '値生成の接頭語候補に未対応の接頭語が含まれています: '.implode(', ', $invalid),
            ]);
        }

        $normalized = EngineeringUnits::normalizePrefixList($prefixes, EngineeringUnits::UNIVERSAL_PREFIX_ORDER);

        return $normalized === [] ? null : $normalized;
    }

    /**
     * 目的: 部品系列の正規化ポリシーforstorageを処理する。
     * 機能: HTTP入力を検証し、Eloquent操作またはサービス処理を行い、JSONレスポンスへ包む。
     * 入力: $policy。
     * 出力: HTTP JSONレスポンス、ファイルレスポンス、またはnoContentレスポンス。
     * 動作条件: 認証済みユーザー、権限、バリデーション済み入力を前提にする。
     * 副作用: なし。
     * @param  array<string, mixed>  $policy
     * @return array<string, mixed>
     */
    private function normalizePolicyForStorage(array $policy): array
    {
        $type = (string) ($policy['value_set_type'] ?? 'none');
        $errors = [];

        if (in_array($type, ['e_series', 'hybrid_series'], true)) {
            $rangeMin = $policy['range_min'] ?? null;
            $rangeMax = $policy['range_max'] ?? null;
            $inputPrefixes = $this->inputPrefixesFromPolicy($policy);
            $unit = (string) ($policy['unit'] ?? '');

            if (! blank($rangeMin) || ! blank($rangeMax)) {
                $min = $this->parsePolicyNumber($rangeMin, $inputPrefixes, $unit);
                $max = $this->parsePolicyNumber($rangeMax, $inputPrefixes, $unit);
                if (blank($rangeMin)) {
                    $errors['policy.range_min'][] = 'E系列の開始値を入力してください。例: 1, 0.1, 1k';
                } elseif ($min === null) {
                    $errors['policy.range_min'][] = 'E系列の開始値には数値を入力してください。例: 1, 0.1, 1k';
                } elseif ($min <= 0) {
                    $errors['policy.range_min'][] = 'E系列の開始値は0より大きい値にしてください。0Ωは「0を含む」で追加します。';
                }
                if (blank($rangeMax)) {
                    $errors['policy.range_max'][] = 'E系列の終了値を入力してください。例: 10G, 1M, 100k';
                } elseif ($max === null) {
                    $errors['policy.range_max'][] = 'E系列の終了値には数値を入力してください。例: 10G, 10GΩ, 10000000000';
                }
                if (($min ?? null) !== null && ($max ?? null) !== null && $min > 0 && $min > $max) {
                    $errors['policy.range_max'][] = 'E系列の終了値は開始値以上にしてください。例: 開始値=1、終了値=10G';
                }

                if (($min ?? null) !== null && ($max ?? null) !== null && $min > 0 && $min <= $max) {
                    $policy['range_min'] = $min;
                    $policy['range_max'] = $max;
                    $policy['decade_min'] = (int) floor(log10($min));
                    $policy['decade_max'] = (int) floor(log10($max));
                }
            } else {
                $decadeMin = $policy['decade_min'];
                $decadeMax = $policy['decade_max'];
                if ($decadeMin !== null && $decadeMax !== null && (int) $decadeMin > (int) $decadeMax) {
                    $errors['policy.decade_max'][] = 'E系列の終了桁は開始桁以上にしてください。例: 0.1Ωから10GΩまでなら開始桁=-1、終了桁=10';
                }
            }
            $policy['range_step'] = null;
        } else {
            $policy['primary_series'] = null;
            $policy['extra_series'] = [];
            $policy['decade_min'] = null;
            $policy['decade_max'] = null;
        }

        if ($type !== 'hybrid_series') {
            $policy['extra_values'] = [];
        }
        if ($type !== 'custom_list') {
            $policy['custom_values'] = [];
        }

        if ($type === 'range_step') {
            $parsed = [];
            $inputPrefixes = $this->inputPrefixesFromPolicy($policy);
            $unit = (string) ($policy['unit'] ?? '');
            $labels = [
                'range_min' => '範囲/刻みの開始値',
                'range_max' => '範囲/刻みの終了値',
                'range_step' => '範囲/刻みの刻み幅',
            ];
            foreach ($labels as $key => $label) {
                $raw = $policy[$key] ?? null;
                if (blank($raw)) {
                    $errors["policy.{$key}"][] = "{$label}を入力してください。";
                    continue;
                }
                $number = $this->parsePolicyNumber($raw, $inputPrefixes, $unit);
                if ($number === null) {
                    $errors["policy.{$key}"][] = "{$label}には数値を入力してください。例: 10G, 10GΩ, 10000000000";
                    continue;
                }
                $parsed[$key] = $number;
            }

            if (($parsed['range_step'] ?? null) !== null && $parsed['range_step'] <= 0) {
                $errors['policy.range_step'][] = '範囲/刻みの刻み幅は0より大きい数値にしてください。';
            }
            if (($parsed['range_min'] ?? null) !== null
                && ($parsed['range_max'] ?? null) !== null
                && $parsed['range_min'] > $parsed['range_max']) {
                $errors['policy.range_max'][] = '範囲/刻みの終了値は開始値以上にしてください。例: 開始値=0、終了値=10G';
            }

            $policy['range_min'] = $parsed['range_min'] ?? $policy['range_min'];
            $policy['range_max'] = $parsed['range_max'] ?? $policy['range_max'];
            $policy['range_step'] = $parsed['range_step'] ?? $policy['range_step'];
        } else {
            if (! in_array($type, ['e_series', 'hybrid_series'], true)) {
                $policy['range_min'] = null;
                $policy['range_max'] = null;
            }
            $policy['range_step'] = null;
        }

        $settings = is_array($policy['generation_settings'] ?? null) ? $policy['generation_settings'] : [];
        $policy['generation_settings'] = [
            ...$settings,
            'include_zero' => in_array($type, ['e_series', 'hybrid_series'], true)
                && filter_var($settings['include_zero'] ?? false, FILTER_VALIDATE_BOOLEAN),
        ];

        if ($errors !== []) {
            throw ValidationException::withMessages($errors);
        }

        return $policy;
    }
    /**
     * 目的: 部品系列の解析ポリシー番号を処理する。
     * 機能: HTTP入力を検証し、Eloquent操作またはサービス処理を行い、JSONレスポンスへ包む。
     * 入力: $value, $allowedPrefixes, $unit。
     * 出力: HTTP JSONレスポンス、ファイルレスポンス、またはnoContentレスポンス。
     * 動作条件: 認証済みユーザー、権限、バリデーション済み入力を前提にする。
     * 副作用: なし。
     */
    private function parsePolicyNumber(mixed $value, ?array $allowedPrefixes = null, string $unit = ''): ?float
    {
        if (! is_scalar($value)) {
            return null;
        }

        return $this->generator->parseEngineeringNumber($value, $allowedPrefixes, $unit);
    }

    /**
     * 目的: 部品系列の正規化JSON一覧を処理する。
     * 機能: HTTP入力を検証し、Eloquent操作またはサービス処理を行い、JSONレスポンスへ包む。
     * 入力: $values。
     * 出力: HTTP JSONレスポンス、ファイルレスポンス、またはnoContentレスポンス。
     * 動作条件: 認証済みユーザー、権限、バリデーション済み入力を前提にする。
     * 副作用: なし。
     * @return array<int, mixed>
     */
    private function normalizeJsonList(mixed $values): array
    {
        if (is_array($values)) {
            return array_values(array_filter($values, fn ($value) => trim((string) $value) !== ''));
        }

        return array_values(array_filter(
            array_map( fn ($value) => trim((string) $value), preg_split('/[\s,;]+/', (string) $values)), fn ($value) => $value !== ''
        ));
    }
    /**
     * 目的: 部品系列の同期generated値を処理する。
     * 機能: HTTP入力を検証し、Eloquent操作またはサービス処理を行い、JSONレスポンスへ包む。
     * 入力: $series, $policy。
     * 出力: HTTP JSONレスポンス、ファイルレスポンス、またはnoContentレスポンス。
     * 動作条件: 認証済みユーザー、権限、バリデーション済み入力を前提にする。
     * 副作用: DB、ファイルストレージ、外部サービス、HTTPレスポンスのいずれかを操作する場合がある。
     */
    private function syncGeneratedValues(ComponentSeries $series, ComponentSeriesValuePolicy $policy): void
    {
        $generated = $this->generator->generate($policy);
        $seen = [];
        foreach ($generated as $item) {
            $seen[] = $item['value_key'];
            $series->values()->updateOrCreate(
                ['value_key' => $item['value_key']],
                $item
            );
        }

        $query = $series->values()->whereNull('materialized_component_id');
        if ($seen !== []) {
            $query->whereNotIn('value_key', $seen);
        }
        $query->delete();
    }
    /**
     * 目的: 部品系列のrenderテンプレートを処理する。
     * 機能: HTTP入力を検証し、Eloquent操作またはサービス処理を行い、JSONレスポンスへ包む。
     * 入力: $template, $series, $value。
     * 出力: HTTP JSONレスポンス、ファイルレスポンス、またはnoContentレスポンス。
     * 動作条件: 認証済みユーザー、権限、バリデーション済み入力を前提にする。
     * 副作用: DB、ファイルストレージ、外部サービス、HTTPレスポンスのいずれかを操作する場合がある。
     */
    private function renderTemplate(string $template, ComponentSeries $series, ComponentSeriesValue $value): string
    {
        $text = strtr($template, [
            '{manufacturer}' => (string) $series->manufacturer,
            '{series}' => $series->name,
            '{value}' => $value->value_text,
            '{unit}' => (string) $value->unit,
        ]);

        return trim(preg_replace('/\s+/', ' ', $text) ?: $series->name.'-'.$value->value_text);
    }
    /**
     * 目的: 部品系列の読込系列を処理する。
     * 機能: HTTP入力を検証し、Eloquent操作またはサービス処理を行い、JSONレスポンスへ包む。
     * 入力: $series。
     * 出力: HTTP JSONレスポンス、ファイルレスポンス、またはnoContentレスポンス。
     * 動作条件: 認証済みユーザー、権限、バリデーション済み入力を前提にする。
     * 副作用: DB、ファイルストレージ、外部サービス、HTTPレスポンスのいずれかを操作する場合がある。
     */
    private function loadSeries(ComponentSeries $series): ComponentSeries
    {
        return $series->load([
            'specGroup',
            'valueSpecType',
            'package.packageGroup',
            'policy',
            'values.materializedComponent',
        ])->loadCount([
            'values as values_count',
            'values as enabled_values_count' => fn ($q) => $q->where('is_enabled', true),
            'components as materialized_components_count',
        ]);
    }
}
