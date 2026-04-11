<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreComponentRequest;
use App\Http\Requests\UpdateComponentSectionRequest;
use App\Http\Responses\ApiResponse;
use App\Models\Category;
use App\Models\Component;
use App\Models\ComponentDatasheet;
use App\Support\FileStorage;
use RuntimeException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class ComponentController extends Controller
{
    /**
     * GET /api/components
     * フリーワード・分類・入手可否・スペック範囲フィルタ + ページネーション
     */
    public function index(Request $request)
    {
        $query = Component::with(['categories', 'packages', 'inventoryBlocks', 'componentSuppliers.supplier', 'datasheets'])
            ->withCount('inventoryBlocks');
        $sortMap = [
            'updated_at' => ['updated_at', 'desc'],
            'name' => ['common_name', 'asc'],
            'part_number' => ['part_number', 'asc'],
        ];

        // フリーワード検索（部品名・型番・メーカー・説明）
        if ($q = $request->input('q')) {
            $query->where(function ($sub) use ($q) {
                $sub->where('part_number', 'ilike', "%{$q}%")
                    ->orWhere('common_name', 'ilike', "%{$q}%")
                    ->orWhere('manufacturer', 'ilike', "%{$q}%")
                    ->orWhere('description', 'ilike', "%{$q}%")
                    ->orWhereHas('componentSuppliers', fn ($supplierQuery) => $supplierQuery->where('supplier_part_number', 'ilike', "%{$q}%"));
            });
        }

        // 分類フィルタ（複数選択 OR）
        if ($cats = $request->input('category_ids')) {
            $query->whereHas('categories', fn ($q) => $q->whereIn('categories.id', (array) $cats));
        }

        // 入手可否フィルタ
        if ($status = $request->input('procurement_status')) {
            $query->where('procurement_status', $status);
        }

        // メーカーフィルタ
        if ($manufacturer = $request->input('manufacturer')) {
            $query->where('manufacturer', 'ilike', '%' . $manufacturer . '%');
        }

        // パッケージフィルタ（複数選択 OR）
        if ($packageIds = $request->input('package_ids')) {
            $query->whereHas('packages', fn ($q) => $q->whereIn('packages.id', (array) $packageIds));
        }

        // スペック数値範囲フィルタ（spec_type_id + min + max）
        if ($specTypeId = $request->input('spec_type_id')) {
            $query->whereHas('specs', function ($q) use ($request, $specTypeId) {
                $q->where('spec_type_id', $specTypeId);
                if ($unit = $request->input('spec_unit')) {
                    $q->where('unit', 'ilike', '%' . $unit . '%');
                }
                if ($min = $request->input('spec_min')) {
                    $q->where('value_numeric', '>=', (float) $min);
                }
                if ($max = $request->input('spec_max')) {
                    $q->where('value_numeric', '<=', (float) $max);
                }
            });
        }
        elseif ($unit = $request->input('spec_unit')) {
            $query->whereHas('specs', fn ($q) => $q->where('unit', 'ilike', '%' . $unit . '%'));
        }

        // 在庫数下限フィルタ
        if ($minStock = $request->input('min_stock')) {
            $query->whereRaw('(quantity_new + quantity_used) >= ?', [(int) $minStock]);
        }

        if ($inventoryState = $request->input('inventory_state')) {
            match ($inventoryState) {
                'new' => $query->where('quantity_new', '>', 0),
                'used' => $query->where('quantity_used', '>', 0),
                'empty' => $query->whereRaw('(quantity_new + quantity_used) = 0'),
                'warning' => $query->where(function ($q) {
                    $q->whereColumn('quantity_new', '<', 'threshold_new')
                        ->orWhereColumn('quantity_used', '<', 'threshold_used');
                }),
                default => null,
            };
        }

        if ($purchasedFrom = $request->input('purchased_from')) {
            $query->whereHas('transactions', fn ($q) => $q->where('type', 'in')->whereDate('created_at', '>=', $purchasedFrom));
        }
        if ($purchasedTo = $request->input('purchased_to')) {
            $query->whereHas('transactions', fn ($q) => $q->where('type', 'in')->whereDate('created_at', '<=', $purchasedTo));
        }

        $perPage = min((int) $request->input('per_page', 20), 100);
        [$sortColumn, $sortDirection] = $sortMap[$request->input('sort', 'updated_at')] ?? $sortMap['updated_at'];
        $result = $query
            ->orderBy($sortColumn, $sortDirection)
            ->orderBy('part_number')
            ->paginate($perPage);
        $result->getCollection()->transform(fn (Component $component) => $this->decorateComponent($component));

        return ApiResponse::success($result);
    }

    /**
     * POST /api/components
     */
    public function store(StoreComponentRequest $request)
    {
        try {
            return DB::transaction(function () use ($request) {
                $data = $request->safe()->except(['image', 'datasheet', 'datasheets', 'duplicate_from_component_id', 'category_ids', 'package_ids', 'specs', 'suppliers']);
                $data['created_by'] = auth()->id();
                $data['updated_by'] = auth()->id();

                if ($request->hasFile('image')) {
                    $data['image_path'] = FileStorage::storeComponentImageNamed($request->file('image'), [
                        $request->input('part_number'),
                        $request->input('common_name'),
                        $this->firstCategoryName((array) $request->input('category_ids', [])),
                    ]);
                }
                $component = Component::create($data);
                if ($request->filled('duplicate_from_component_id') && !$request->hasFile('image')) {
                    $source = Component::with('datasheets')->find($request->integer('duplicate_from_component_id'));
                    if ($source) {
                        $component->image_path = $source->image_path;
                        $component->save();
                        foreach ($source->datasheets as $index => $sheet) {
                            $component->datasheets()->create([
                                'file_path' => $sheet->file_path,
                                'original_name' => $sheet->original_name,
                                'sort_order' => $index,
                            ]);
                        }
                    }
                }
                $this->syncDatasheets($component, $request);
                $this->syncRelations($component, $request);

                return ApiResponse::created($this->decorateComponent(
                    $component->load(['categories', 'packages', 'specs.specType', 'componentSuppliers.supplier', 'componentSuppliers.priceBreaks', 'primaryLocation', 'datasheets'])
                ));
            });
        } catch (RuntimeException $e) {
            return ApiResponse::error($e->getMessage(), [], 500);
        }
    }

    /**
     * GET /api/components/{component}
     */
    public function show(Component $component)
    {
        $component->load([
            'categories', 'packages',
            'specs.specType',
            'customAttributes',
            'componentSuppliers.supplier', 'componentSuppliers.priceBreaks',
            'inventoryBlocks.location',
            'primaryLocation',
            'datasheets',
            'transactions' => fn ($q) => $q->latest(),
            'projects',
            'altiumLink',
        ]);

        return ApiResponse::success($this->decorateComponent($component));
    }

    /**
     * PUT /api/components/{component}  — 全項目更新
     */
    public function update(StoreComponentRequest $request, Component $component)
    {
        try {
            return DB::transaction(function () use ($request, $component) {
                $data = $request->safe()->except(['image', 'datasheet', 'datasheets', 'duplicate_from_component_id', 'category_ids', 'package_ids', 'specs', 'suppliers']);
                $data['updated_by'] = auth()->id();

                if ($request->hasFile('image')) {
                    FileStorage::delete($component->image_path);
                    $data['image_path'] = FileStorage::storeComponentImageNamed($request->file('image'), [
                        $request->input('part_number', $component->part_number),
                        $request->input('common_name', $component->common_name),
                        $this->firstCategoryName((array) $request->input('category_ids', $component->categories()->pluck('categories.id')->all())),
                    ]);
                }
                $component->update($data);
                $this->syncDatasheets($component, $request);
                $this->syncRelations($component, $request);

                return ApiResponse::success($this->decorateComponent(
                    $component->load(['categories', 'packages', 'specs.specType', 'componentSuppliers.supplier', 'primaryLocation', 'datasheets', 'customAttributes'])
                ));
            });
        } catch (RuntimeException $e) {
            return ApiResponse::error($e->getMessage(), [], 500);
        }
    }

    /**
     * PATCH /api/components/{component}/{section}  — セクション別部分更新
     * section: basic / specs / suppliers
     */
    public function updateSection(UpdateComponentSectionRequest $request, Component $component, string $section)
    {
        return DB::transaction(function () use ($request, $component, $section) {
            $component->updated_by = auth()->id();

            switch ($section) {
                case 'basic':
                    $data = $request->safe()->except(['image', 'datasheet', 'datasheets', 'category_ids', 'package_ids']);
                    if ($request->hasFile('image')) {
                        FileStorage::delete($component->image_path);
                        $data['image_path'] = FileStorage::storeComponentImageNamed($request->file('image'), [
                            $request->input('part_number', $component->part_number),
                            $request->input('common_name', $component->common_name),
                            $this->firstCategoryName((array) $request->input('category_ids', $component->categories()->pluck('categories.id')->all())),
                        ]);
                    }
                    $component->update($data);
                    $this->syncDatasheets($component, $request);
                    if ($request->has('category_ids')) {
                        $component->categories()->sync($request->category_ids ?? []);
                    }
                    if ($request->has('package_ids')) {
                        $component->packages()->sync($request->package_ids ?? []);
                    }
                    break;

                case 'specs':
                    // 送信された specs 配列で全置換
                    $component->specs()->delete();
                    foreach ($request->specs as $spec) {
                        $component->specs()->create([
                            'spec_type_id' => $spec['spec_type_id'],
                            'value' => $spec['value'] ?? null,
                            'unit' => $spec['unit'] ?? null,
                            'value_numeric' => $spec['value_numeric'] ?? null,
                        ]);
                    }
                    $component->save();
                    break;

                case 'attributes':
                    // 送信された attributes 配列で全置換
                    $component->customAttributes()->delete();
                    foreach ($request->input('attributes', []) as $attr) {
                        $key = trim((string) ($attr['key'] ?? ''));
                        $value = trim((string) ($attr['value'] ?? ''));
                        if ($key === '' || $value === '') continue;
                        $component->customAttributes()->create([
                            'key'   => $key,
                            'value' => $value,
                        ]);
                    }
                    $component->save();
                    break;

                case 'suppliers':
                    // 送信された suppliers で全置換
                    $component->componentSuppliers()->each(fn ($cs) => $cs->priceBreaks()->delete());
                    $component->componentSuppliers()->delete();
                    $this->syncSuppliers($component, $request->suppliers ?? []);
                    $component->save();
                    break;
            }

            return ApiResponse::success($this->decorateComponent(
                $component->load(['categories', 'packages', 'specs.specType', 'componentSuppliers.supplier', 'componentSuppliers.priceBreaks', 'primaryLocation', 'datasheets', 'customAttributes'])
            ));
        });
    }

    /**
     * DELETE /api/components/{component}  — 論理削除
     */
    public function destroy(Component $component)
    {
        $component->delete();

        return ApiResponse::noContent();
    }

    // ── プライベートヘルパー ────────────────────────────────

    private function syncRelations(Component $component, $request): void
    {
        if ($request->has('category_ids')) {
            $component->categories()->sync($request->category_ids ?? []);
        }
        if ($request->has('package_ids')) {
            $component->packages()->sync($request->package_ids ?? []);
        }
        if ($request->has('specs')) {
            $component->specs()->delete();
            foreach ($request->specs as $spec) {
                $component->specs()->create([
                    'spec_type_id' => $spec['spec_type_id'],
                    'value' => $spec['value'] ?? null,
                    'unit' => $spec['unit'] ?? null,
                    'value_numeric' => $spec['value_numeric'] ?? null,
                ]);
            }
        }
        if ($request->has('suppliers')) {
            $component->componentSuppliers()->each(fn ($cs) => $cs->priceBreaks()->delete());
            $component->componentSuppliers()->delete();
            $this->syncSuppliers($component, $request->suppliers ?? []);
        }
    }

    private function syncSuppliers(Component $component, array $suppliers): void
    {
        foreach ($suppliers as $s) {
            $cs = $component->componentSuppliers()->create([
                'supplier_id' => $s['supplier_id'],
                'supplier_part_number' => $s['supplier_part_number'] ?? null,
                'product_url' => $s['product_url'] ?? null,
                'unit_price' => $s['unit_price'] ?? null,
                'price_updated_at' => $s['unit_price'] ? now() : null,
                'is_preferred' => $s['is_preferred'] ?? false,
            ]);
            foreach ($s['price_breaks'] ?? [] as $pb) {
                $cs->priceBreaks()->create([
                    'min_qty' => $pb['min_qty'],
                    'unit_price' => $pb['unit_price'],
                ]);
            }
        }
    }

    private function syncDatasheets(Component $component, Request $request): void
    {
        $files = [];
        if ($request->hasFile('datasheets')) {
            $files = array_merge($files, $request->file('datasheets'));
        }
        if ($request->hasFile('datasheet')) {
            $files[] = $request->file('datasheet');
        }

        if ($files === []) {
            return;
        }

        foreach ($component->datasheets as $sheet) {
            FileStorage::delete($sheet->file_path);
        }
        $component->datasheets()->delete();

        foreach (array_values($files) as $index => $file) {
            $path = FileStorage::storeComponentDatasheetNamed($file, [
                $request->input('part_number', $component->part_number),
                $request->input('common_name', $component->common_name),
                $this->firstCategoryName((array) $request->input('category_ids', $component->categories()->pluck('categories.id')->all())),
            ]);
            $component->datasheets()->create([
                'file_path' => $path,
                'original_name' => $file->getClientOriginalName(),
                'sort_order' => $index,
            ]);
        }

        $component->datasheet_path = $component->datasheets()->orderBy('sort_order')->value('file_path');
        $component->save();
    }

    private function decorateComponent(Component $component): Component
    {
        if ($component->relationLoaded('customAttributes')) {
            $component->custom_attributes = $component->customAttributes;
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
            $component->datasheets->transform(function (ComponentDatasheet $sheet) {
                $sheet->url = FileStorage::url($sheet->file_path);
                return $sheet;
            });
        }
        $component->needs_reorder = $component->quantity_new < $component->threshold_new
            || $component->quantity_used < $component->threshold_used;
        $cheapest = $component->componentSuppliers
            ? $component->componentSuppliers->filter(fn ($item) => $item->unit_price !== null)->sortBy('unit_price')->first()
            : null;
        $component->cheapest_unit_price = $cheapest?->unit_price;
        $component->cheapest_supplier_name = $cheapest?->supplier?->name;

        return $component;
    }

    private function firstCategoryName(array $categoryIds): ?string
    {
        if ($categoryIds === []) {
            return null;
        }

        return Category::query()
            ->whereIn('id', $categoryIds)
            ->orderBy('sort_order')
            ->orderBy('name')
            ->value('name');
    }
}
