<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Responses\ApiResponse;
use App\Models\Component;
use App\Support\FileStorage;
use Illuminate\Database\Eloquent\Collection as EloquentCollection;
use Illuminate\Http\Request;

/**
 * 部品比較 API（SCR-004）
 * 複数部品のスペック・価格・在庫を横並び表示するためのデータ取得
 */
class ComponentCompareController extends Controller
{
    // GET /api/components/compare?ids[]=1&ids[]=2&ids[]=3
    public function compare(Request $request)
    {
        $validated = $request->validate([
            'ids' => ['required', 'array', 'min:2', 'max:5'],
            'ids.*' => ['integer', 'exists:components,id'],
        ]);

        $components = Component::with([
            'categories',
            'packages',
            'specs.specType',
            'componentSuppliers.supplier',
            'componentSuppliers.priceBreaks',
            'inventoryBlocks',
        ])->findMany($validated['ids']);

        // 指定順序を保持
        $ordered = collect($validated['ids'])->map(
            fn ($id) => $components->firstWhere('id', $id)
        )->filter()->values();

        // 全部品に存在するスペック種別 + profile を収集（比較軸）
        $specAxisMap = [];
        foreach ($ordered as $comp) {
            foreach ($comp->specs as $spec) {
                $axisKey = $this->specAxisKey($spec->spec_type_id, (string) ($spec->value_profile ?? 'typ'));
                if (! isset($specAxisMap[$axisKey])) {
                    $specAxisMap[$axisKey] = [
                        'key' => $axisKey,
                        'id' => $spec->spec_type_id,
                        'name' => $spec->specType->name ?? '不明',
                        'value_profile' => $spec->value_profile ?? 'typ',
                        'display_name' => $this->buildDisplayName($spec->specType->name ?? '不明', $spec->value_profile ?? 'typ'),
                    ];
                }
            }
        }

        // 部品ごとにスペックを spec_type_id + profile をキーにしたマップへ変換
        $result = $ordered->map(function ($comp) use ($specAxisMap) {
            $specsByAxis = $comp->specs->mapWithKeys(fn ($spec) => [
                $this->specAxisKey($spec->spec_type_id, (string) ($spec->value_profile ?? 'typ')) => $spec,
            ]);
            $specValues = collect($specAxisMap)->mapWithKeys(fn ($axis) => [
                $axis['key'] => [
                    'value' => $specsByAxis[$axis['key']]->value ?? null,
                    'value_profile' => $specsByAxis[$axis['key']]->value_profile ?? $axis['value_profile'],
                    'value_numeric_typ' => $specsByAxis[$axis['key']]->value_numeric_typ ?? null,
                    'value_numeric_min' => $specsByAxis[$axis['key']]->value_numeric_min ?? null,
                    'value_numeric_max' => $specsByAxis[$axis['key']]->value_numeric_max ?? null,
                    'normalized_unit' => $specsByAxis[$axis['key']]->normalized_unit ?? null,
                    'unit' => $specsByAxis[$axis['key']]->unit ?? null,
                ],
            ])->toArray();

            // 最安値
            $prices = $comp->componentSuppliers->flatMap->priceBreaks->pluck('unit_price');
            $cheapestPrice = $prices->isNotEmpty() ? $prices->min() : null;

            return [
                'id' => $comp->id,
                'part_number' => $comp->part_number,
                'common_name' => $comp->common_name,
                'manufacturer' => $comp->manufacturer,
                'image_url' => FileStorage::url($comp->image_path),
                'procurement_status' => $comp->procurement_status,
                'quantity_new' => $comp->quantity_new,
                'quantity_used' => $comp->quantity_used,
                'categories' => $comp->categories->pluck('name'),
                'packages' => $comp->packages->pluck('name'),
                'specs' => $specValues,
                'cheapest_price' => $cheapestPrice,
                'suppliers' => $comp->componentSuppliers->map(fn ($cs) => [
                    'name' => $cs->supplier->name,
                    'part_number' => $cs->supplier_part_number,
                    'min_price' => $cs->priceBreaks->min('unit_price'),
                ]),
            ];
        });

        return ApiResponse::success([
            'spec_types' => array_values($specAxisMap),
            'components' => $result,
        ]);
    }

    // GET /api/components/{component}/similar
    public function similar(Component $component)
    {
        // 同一分類に属し、スペックが近い部品を取得（数値スペックの類似度で近似）
        $categoryIds = $component->categories->pluck('id');

        // 同じ分類に属する部品（自分を除く）を取得
        $candidates = Component::with(['categories', 'packages', 'specs.specType', 'componentSuppliers.priceBreaks'])
            ->where('id', '!=', $component->id)
            ->whereHas('categories', fn ($q) => $q->whereIn('categories.id', $categoryIds))
            ->take(50)  // 候補を絞ってからスコアリング
            ->get();

        if ($candidates->isEmpty()) {
            return ApiResponse::success([]);
        }

        // 類似判定は typ を持つ spec のみを使用する
        $baseSpecs = $this->representativeTypSpecs($component->specs);

        // 類似スコア計算（数値スペックのユークリッド距離ベース）
        $scored = $candidates->map(function ($cand) use ($baseSpecs) {
            if ($baseSpecs->isEmpty()) {
                // スペックなし → 全て同一スコア
                return ['component' => $cand, 'score' => 0];
            }

            $totalDiff = 0;
            $matchedCount = 0;
            $candidateSpecs = $this->representativeTypSpecs($cand->specs);
            foreach ($baseSpecs as $typeId => $baseSpec) {
                $candSpec = $candidateSpecs->get($typeId);
                $distance = $candSpec ? $this->specDistance($baseSpec, $candSpec) : null;
                if ($distance !== null) {
                    // 相対差（0=完全一致、大きいほど異なる）
                    $totalDiff += $distance;
                    $matchedCount++;
                }
            }

            $score = $matchedCount > 0 ? $totalDiff / $matchedCount : PHP_FLOAT_MAX;

            return ['component' => $cand, 'score' => $score];
        });

        // スコア昇順（近い順）で上位10件
        $similar = $scored->sortBy('score')->take(10)->map(function ($item) {
            $c = $item['component'];
            $cheapestPrice = $c->componentSuppliers->flatMap->priceBreaks->min('unit_price');

            return [
                'id' => $c->id,
                'part_number' => $c->part_number,
                'common_name' => $c->common_name,
                'manufacturer' => $c->manufacturer,
                'procurement_status' => $c->procurement_status,
                'quantity_new' => $c->quantity_new,
                'categories' => $c->categories->pluck('name'),
                'packages' => $c->packages->pluck('name'),
                'cheapest_price' => $cheapestPrice,
                'similarity_score' => round($item['score'], 4),
            ];
        })->values();

        return ApiResponse::success($similar);
    }

    private function specDistance(object $baseSpec, object $candSpec): ?float
    {
        $baseRepresentative = $baseSpec->value_numeric_typ;
        $candRepresentative = $candSpec->value_numeric_typ;

        if ($baseRepresentative === null || $candRepresentative === null) {
            return null;
        }

        if ((float) $baseRepresentative == 0.0) {
            return null;
        }

        return abs(((float) $candRepresentative - (float) $baseRepresentative) / (float) $baseRepresentative);
    }

    private function representativeTypSpecs(EloquentCollection $specs): \Illuminate\Support\Collection
    {
        return $specs
            ->filter(fn ($spec) => in_array($spec->value_profile, ['typ', 'triple'], true) && $spec->value_numeric_typ !== null)
            ->sortBy(fn ($spec) => $spec->value_profile === 'triple' ? 0 : 1)
            ->groupBy('spec_type_id')
            ->map(fn ($group) => $group->first());
    }

    private function specAxisKey(int|string|null $specTypeId, ?string $profile): string
    {
        $normalizedProfile = $profile ?: 'typ';

        return sprintf('%s:%s', (string) $specTypeId, $normalizedProfile);
    }

    private function buildDisplayName(string $baseName, string $profile): string
    {
        return match ($profile) {
            'max_only' => '最大'.$baseName,
            'min_only' => '最小'.$baseName,
            'range' => $baseName.'範囲',
            default => $baseName,
        };
    }
}
