<?php

namespace App\Http\Controllers\Api\Concerns;

use App\Models\Component;
use App\Models\ComponentDatasheet;
use App\Models\Package;
use App\Models\SpecGroup;
use App\Support\FileStorage;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

trait DecoratesComponentResponses
{
    /**
     * 目的: 部品APIレスポンスへ画面表示用の派生値を付与する。
     * 機能: 読込済みリレーション、画像URL、データシートURL、在庫警告、最安仕入先を整形する。
     * 入力: 装飾対象のComponent。
     * 出力: 装飾済みComponent。
     * 動作条件: Controller側で必要リレーションをwith/loadしてから呼び出すこと。
     * 副作用: Componentインスタンスの動的属性とリレーション順序を更新する。
     */
    private function decorateComponent(Component $component): Component
    {
        if ($component->relationLoaded('package') || $component->relationLoaded('packages')) {
            $package = $component->relationLoaded('package')
                ? $component->package
                : $component->packages->first();
            $component->setRelation('package', $package);
            $component->setRelation('packages', $package ? collect([$package]) : collect());
            $component->package_group = $package?->packageGroup;
            $component->package_name = $package?->name;
        }

        if ($component->relationLoaded('customAttributes')) {
            $component->custom_attributes = $component->customAttributes;
        }

        if ($component->relationLoaded('specs')) {
            $this->sortSpecsByMasterOrder($component);
        }

        if ($component->relationLoaded('componentSeries')) {
            $component->component_series_name = $component->componentSeries?->name;
        }
        if ($component->relationLoaded('componentSeriesValue')) {
            $component->component_series_value_text = $component->componentSeriesValue?->value_text;
        }

        if ($component->relationLoaded('inventoryBlocks')) {
            $stockTypeOrder = ['reel' => 0, 'tape' => 1, 'tray' => 2, 'loose' => 3, 'box' => 4];
            $conditionOrder = ['new' => 0, 'used' => 1];

            $component->setRelation('inventoryBlocks', $component->inventoryBlocks
                ->sortBy([
                    fn ($block) => $block->location->sort_order ?? PHP_INT_MAX,
                    fn ($block) => $block->location->code ?? 'ZZZ',
                    fn ($block) => $conditionOrder[$block->condition] ?? 99,
                    fn ($block) => $stockTypeOrder[$block->stock_type] ?? 99,
                    fn ($block) => $block->id,
                ])
                ->values());
        }

        $component->image_url = FileStorage::url($component->image_path);
        $primarySheet = $component->relationLoaded('datasheets')
            ? $component->datasheets->first()
            : ($component->datasheet_path ? (object) ['file_path' => $component->datasheet_path, 'original_name' => basename($component->datasheet_path)] : null);
        $component->datasheet_url = FileStorage::url($primarySheet?->file_path);
        $component->datasheet_path = $primarySheet?->file_path;
        if ($component->relationLoaded('datasheets')) {
            $datasheetCount = $component->datasheets->count();
            $component->datasheets->transform(function (ComponentDatasheet $sheet, int $index) use ($datasheetCount) {
                $sheet->url = FileStorage::url($sheet->file_path);
                $sheet->display_name = $sheet->note
                    ?: ($sheet->original_name
                        ?: 'データシート'.($datasheetCount > 1 ? ' '.($index + 1) : ''));

                return $sheet;
            });
        }
        $component->needs_reorder = $component->quantity_new < $component->threshold_new
            || $component->quantity_used < $component->threshold_used;
        if ($component->relationLoaded('componentSuppliers')) {
            $cheapest = $component->componentSuppliers
                ->filter(fn ($item) => $item->unit_price !== null)
                ->sortBy('unit_price')
                ->first();
            $component->cheapest_unit_price = $cheapest?->unit_price;
            $component->cheapest_supplier_name = $cheapest?->supplier?->name;
        }

        return $component;
    }

    /**
     * 目的: 部品詳細のスペックをマスタ候補順へ並び替える。
     * 機能: 所属部品分類の候補順、スペック詳細のsort_order、名称、IDで安定ソートする。
     * 入力: specsリレーションを持つComponent。
     * 出力: なし。
     * 動作条件: Componentのcategoriesまたはspecsリレーションが利用できること。
     * 副作用: Componentのspecsリレーションを並び替え済みCollectionへ置き換える。
     */
    private function sortSpecsByMasterOrder(Component $component): void
    {
        $categoryIds = $component->relationLoaded('categories')
            ? $component->categories->pluck('id')->map(fn ($id) => (int) $id)->all()
            : $component->categories()->pluck('spec_groups.id')->map(fn ($id) => (int) $id)->all();

        $candidateOrder = [];
        if ($categoryIds !== []) {
            $rows = DB::table('spec_group_spec_type')
                ->join('spec_groups', 'spec_groups.id', '=', 'spec_group_spec_type.spec_group_id')
                ->whereIn('spec_group_spec_type.spec_group_id', $categoryIds)
                ->whereNull('spec_groups.deleted_at')
                ->orderBy('spec_groups.sort_order')
                ->orderBy('spec_groups.name')
                ->orderBy('spec_group_spec_type.sort_order')
                ->orderBy('spec_group_spec_type.spec_type_id')
                ->get([
                    'spec_group_spec_type.spec_type_id',
                    'spec_group_spec_type.sort_order as candidate_sort_order',
                    'spec_groups.sort_order as group_sort_order',
                    'spec_groups.name as group_name',
                ]);

            foreach ($rows as $index => $row) {
                $specTypeId = (int) $row->spec_type_id;
                $candidateOrder[$specTypeId] ??= [
                    'rank' => $index,
                    'group_sort_order' => (int) $row->group_sort_order,
                    'group_name' => (string) $row->group_name,
                    'candidate_sort_order' => (int) $row->candidate_sort_order,
                ];
            }
        }

        $component->setRelation('specs', $component->specs
            ->sort(function ($left, $right) use ($candidateOrder) {
                $leftType = $left->specType;
                $rightType = $right->specType;
                $leftCandidate = $candidateOrder[(int) $left->spec_type_id] ?? null;
                $rightCandidate = $candidateOrder[(int) $right->spec_type_id] ?? null;

                return [
                    $leftCandidate === null ? 1 : 0,
                    $leftCandidate['rank'] ?? PHP_INT_MAX,
                    $leftCandidate['candidate_sort_order'] ?? PHP_INT_MAX,
                    (int) ($leftType?->sort_order ?? PHP_INT_MAX),
                    (string) ($leftType?->name_ja ?? $leftType?->name ?? ''),
                    (int) $left->id,
                ] <=> [
                    $rightCandidate === null ? 1 : 0,
                    $rightCandidate['rank'] ?? PHP_INT_MAX,
                    $rightCandidate['candidate_sort_order'] ?? PHP_INT_MAX,
                    (int) ($rightType?->sort_order ?? PHP_INT_MAX),
                    (string) ($rightType?->name_ja ?? $rightType?->name ?? ''),
                    (int) $right->id,
                ];
            })
            ->values());
    }

    /**
     * 目的: Decorates Component Responsesの検証パッケージ選択を処理する。
     * 機能: HTTP入力を検証し、Eloquent操作またはサービス処理を行い、JSONレスポンスへ包む。
     * 入力: $packageGroupId, $packageId。
     * 出力: HTTP JSONレスポンス、ファイルレスポンス、またはnoContentレスポンス。
     * 動作条件: 認証済みユーザー、権限、バリデーション済み入力を前提にする。
     * 副作用: DB、ファイルストレージ、外部サービス、HTTPレスポンスのいずれかを操作する場合がある。
     */
    private function assertPackageSelection(mixed $packageGroupId, mixed $packageId): void
    {
        if ($packageGroupId && ! $packageId) {
            throw ValidationException::withMessages(['package_id' => 'パッケージを選択してください。']);
        }

        if ($packageId && ! $packageGroupId) {
            throw ValidationException::withMessages(['package_group_id' => '先にパッケージ分類を選択してください。']);
        }

        if (! $packageId) {
            return;
        }

        $package = Package::find($packageId);
        if (! $package) {
            throw ValidationException::withMessages(['package_id' => '選択したパッケージが存在しません。']);
        }

        if ($packageGroupId && (int) $package->package_group_id !== (int) $packageGroupId) {
            throw ValidationException::withMessages(['package_id' => 'パッケージが選択中のパッケージ分類に属していません。']);
        }
    }

    /**
     * 目的: 部品が属する最初の分類名を表示用に取得する。
     * 機能: sort_orderと名称順で先頭のSpecGroup名を返す。
     * 入力: 部品分類ID配列。
     * 出力: 分類名またはnull。
     * 動作条件: ID配列は整数化済みであること。
     * 副作用: SpecGroupをDB参照する。
     */
    private function firstCategoryName(array $categoryIds): ?string
    {
        if ($categoryIds === []) {
            return null;
        }

        return SpecGroup::query()
            ->whereIn('id', $categoryIds)
            ->orderBy('sort_order')
            ->orderBy('name')
            ->value('name');
    }
}
