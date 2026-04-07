<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreComponentRequest;
use App\Http\Requests\UpdateComponentSectionRequest;
use App\Http\Responses\ApiResponse;
use App\Models\Component;
use App\Support\FileStorage;
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
        $query = Component::with(['categories', 'packages', 'inventoryBlocks'])
            ->withCount('inventoryBlocks');

        // フリーワード検索（部品名・型番・メーカー・説明）
        if ($q = $request->input('q')) {
            $query->where(function ($sub) use ($q) {
                $sub->where('part_number', 'ilike', "%{$q}%")
                    ->orWhere('common_name', 'ilike', "%{$q}%")
                    ->orWhere('manufacturer', 'ilike', "%{$q}%")
                    ->orWhere('description', 'ilike', "%{$q}%");
            });
        }

        // 分類フィルタ（複数選択 OR）
        if ($cats = $request->input('category_ids')) {
            $query->whereHas('categories', fn($q) => $q->whereIn('categories.id', (array)$cats));
        }

        // 入手可否フィルタ
        if ($status = $request->input('procurement_status')) {
            $query->where('procurement_status', $status);
        }

        // 在庫警告フィルタ
        if ($request->boolean('needs_reorder')) {
            $query->needsReorder();
        }

        // スペック数値範囲フィルタ（spec_type_id + min + max）
        if ($specTypeId = $request->input('spec_type_id')) {
            $query->whereHas('specs', function ($q) use ($request, $specTypeId) {
                $q->where('spec_type_id', $specTypeId);
                if ($min = $request->input('spec_min')) {
                    $q->where('value_numeric', '>=', (float)$min);
                }
                if ($max = $request->input('spec_max')) {
                    $q->where('value_numeric', '<=', (float)$max);
                }
            });
        }

        // 発注点フィルタ（在庫数が発注点以上）
        if ($minStock = $request->input('min_stock')) {
            $query->where('quantity_new', '>=', (int)$minStock);
        }

        $perPage = min((int)$request->input('per_page', 20), 100);
        $result  = $query->orderBy('part_number')->paginate($perPage);

        return ApiResponse::success($result);
    }

    /**
     * POST /api/components
     */
    public function store(StoreComponentRequest $request)
    {
        return DB::transaction(function () use ($request) {
            $data = $request->safe()->except(['image', 'datasheet', 'category_ids', 'package_ids', 'specs', 'suppliers']);
            $data['created_by'] = auth()->id();
            $data['updated_by'] = auth()->id();

            // ファイル保存
            if ($request->hasFile('image')) {
                $data['image_path'] = FileStorage::storeComponentImage($request->file('image'));
            }
            if ($request->hasFile('datasheet')) {
                $data['datasheet_path'] = FileStorage::storeDatasheet($request->file('datasheet'));
            }

            $component = Component::create($data);
            $this->syncRelations($component, $request);

            return ApiResponse::created($component->load(['categories', 'packages', 'specs.specType', 'componentSuppliers.supplier', 'componentSuppliers.priceBreaks']));
        });
    }

    /**
     * GET /api/components/{component}
     */
    public function show(Component $component)
    {
        $component->load([
            'categories', 'packages',
            'specs.specType',
            'componentSuppliers.supplier', 'componentSuppliers.priceBreaks',
            'inventoryBlocks.location',
            'transactions' => fn($q) => $q->latest()->limit(20),
            'altiumLink',
        ]);
        $component->image_url     = FileStorage::url($component->image_path);
        $component->datasheet_url = FileStorage::url($component->datasheet_path);
        return ApiResponse::success($component);
    }

    /**
     * PUT /api/components/{component}  — 全項目更新
     */
    public function update(StoreComponentRequest $request, Component $component)
    {
        return DB::transaction(function () use ($request, $component) {
            $data = $request->safe()->except(['image', 'datasheet', 'category_ids', 'package_ids', 'specs', 'suppliers']);
            $data['updated_by'] = auth()->id();

            if ($request->hasFile('image')) {
                FileStorage::delete($component->image_path);
                $data['image_path'] = FileStorage::storeComponentImage($request->file('image'));
            }
            if ($request->hasFile('datasheet')) {
                FileStorage::delete($component->datasheet_path);
                $data['datasheet_path'] = FileStorage::storeDatasheet($request->file('datasheet'));
            }

            $component->update($data);
            $this->syncRelations($component, $request);

            return ApiResponse::success($component->load(['categories', 'packages', 'specs.specType', 'componentSuppliers.supplier']));
        });
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
                    $data = $request->safe()->except(['image', 'datasheet', 'category_ids', 'package_ids']);
                    if ($request->hasFile('image')) {
                        FileStorage::delete($component->image_path);
                        $data['image_path'] = FileStorage::storeComponentImage($request->file('image'));
                    }
                    if ($request->hasFile('datasheet')) {
                        FileStorage::delete($component->datasheet_path);
                        $data['datasheet_path'] = FileStorage::storeDatasheet($request->file('datasheet'));
                    }
                    $component->update($data);
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
                            'spec_type_id'  => $spec['spec_type_id'],
                            'value'         => $spec['value'] ?? null,
                            'unit'          => $spec['unit'] ?? null,
                            'value_numeric' => $spec['value_numeric'] ?? null,
                        ]);
                    }
                    $component->save();
                    break;

                case 'suppliers':
                    // 送信された suppliers で全置換
                    $component->componentSuppliers()->each(fn($cs) => $cs->priceBreaks()->delete());
                    $component->componentSuppliers()->delete();
                    $this->syncSuppliers($component, $request->suppliers ?? []);
                    $component->save();
                    break;
            }

            return ApiResponse::success($component->load(['categories', 'packages', 'specs.specType', 'componentSuppliers.supplier', 'componentSuppliers.priceBreaks']));
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
                    'spec_type_id'  => $spec['spec_type_id'],
                    'value'         => $spec['value'] ?? null,
                    'unit'          => $spec['unit'] ?? null,
                    'value_numeric' => $spec['value_numeric'] ?? null,
                ]);
            }
        }
        if ($request->has('suppliers')) {
            $component->componentSuppliers()->each(fn($cs) => $cs->priceBreaks()->delete());
            $component->componentSuppliers()->delete();
            $this->syncSuppliers($component, $request->suppliers ?? []);
        }
    }

    private function syncSuppliers(Component $component, array $suppliers): void
    {
        foreach ($suppliers as $s) {
            $cs = $component->componentSuppliers()->create([
                'supplier_id'          => $s['supplier_id'],
                'supplier_part_number' => $s['supplier_part_number'] ?? null,
                'product_url'          => $s['product_url'] ?? null,
                'unit_price'           => $s['unit_price'] ?? null,
                'price_updated_at'     => $s['unit_price'] ? now() : null,
                'is_preferred'         => $s['is_preferred'] ?? false,
            ]);
            foreach ($s['price_breaks'] ?? [] as $pb) {
                $cs->priceBreaks()->create([
                    'min_qty'    => $pb['min_qty'],
                    'unit_price' => $pb['unit_price'],
                ]);
            }
        }
    }
}
