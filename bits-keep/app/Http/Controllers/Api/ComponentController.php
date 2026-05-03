<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Controllers\Api\Concerns\DecoratesComponentResponses;
use App\Http\Requests\StoreComponentRequest;
use App\Http\Requests\UpdateComponentSectionRequest;
use App\Http\Responses\ApiResponse;
use App\Models\Component;
use App\Models\SpecType;
use App\Services\SpecValueNormalizerService;
use App\Services\TempDatasheetService;
use App\Support\FileStorage;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use RuntimeException;

class ComponentController extends Controller
{
    use DecoratesComponentResponses;

    /**
     * 目的: 部品の一覧を検索条件付きで返す。
     * 機能: HTTP入力を検証し、Eloquent操作またはサービス処理を行い、JSONレスポンスへ包む。
     * 入力: $request。
     * 出力: HTTP JSONレスポンス、ファイルレスポンス、またはnoContentレスポンス。
     * 動作条件: 認証済みユーザー、権限、バリデーション済み入力を前提にする。
     * 副作用: DB、ファイルストレージ、外部サービス、HTTPレスポンスのいずれかを操作する場合がある。
     */
    public function index(Request $request)
    {
        $query = Component::query()
            ->select('components.*')
            ->with(['categories', 'package.packageGroup'])
            ->withCount(['inventoryBlocks', 'componentSuppliers', 'datasheets'])
            ->addSelect([
                'cheapest_unit_price' => DB::table('component_suppliers')
                    ->select('component_suppliers.unit_price')
                    ->whereColumn('component_suppliers.component_id', 'components.id')
                    ->whereNotNull('component_suppliers.unit_price')
                    ->orderBy('component_suppliers.unit_price')
                    ->limit(1),
                'cheapest_supplier_name' => DB::table('component_suppliers')
                    ->join('suppliers', 'suppliers.id', '=', 'component_suppliers.supplier_id')
                    ->select('suppliers.name')
                    ->whereColumn('component_suppliers.component_id', 'components.id')
                    ->whereNull('suppliers.deleted_at')
                    ->whereNotNull('component_suppliers.unit_price')
                    ->orderBy('component_suppliers.unit_price')
                    ->limit(1),
            ]);

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

        if ($ids = $request->input('ids')) {
            $normalizedIds = array_values(array_filter(array_map('intval', (array) $ids), fn ($id) => $id > 0));
            if (count($normalizedIds) === 0) {
                $query->whereRaw('1 = 0');
            } else {
                $query->whereIn('components.id', $normalizedIds);
            }
        }

        // 部品分類フィルタ（複数選択 OR）
        if ($cats = $request->input('category_ids')) {
            $query->whereHas('categories', fn ($q) => $q->whereIn('spec_groups.id', (array) $cats));
        }

        // 入手可否フィルタ
        if ($status = $request->input('procurement_status')) {
            $query->where('procurement_status', $status);
        }

        // メーカーフィルタ
        if ($manufacturer = $request->input('manufacturer')) {
            $query->where('manufacturer', 'ilike', '%'.$manufacturer.'%');
        }

        // パッケージフィルタ
        if ($packageId = $request->integer('package_id')) {
            $query->where('package_id', $packageId);
        } elseif ($packageIds = $request->input('package_ids')) {
            $query->whereIn('package_id', array_map('intval', (array) $packageIds));
        }

        if ($packageGroupId = $request->integer('package_group_id')) {
            $query->whereHas('package', fn ($q) => $q->where('package_group_id', $packageGroupId));
        }

        if ($componentSeriesId = $request->integer('component_series_id')) {
            $query->where('component_series_id', $componentSeriesId);
        }

        // スペック数値フィルタ（spec_type_id + profile + min/max）
        if ($specTypeId = $request->input('spec_type_id')) {
            $specType = SpecType::with('units')->find($specTypeId);
            $normalizer = app(SpecValueNormalizerService::class);
            $queryMin = $normalizer->normalizeSearchBound($specType, $request->input('spec_min'), $request->input('spec_unit'));
            $queryMax = $normalizer->normalizeSearchBound($specType, $request->input('spec_max'), $request->input('spec_unit'));
            $queryUnit = (string) $request->input('spec_unit', '');
            $queryProfile = (string) $request->input('spec_profile', '');

            $query->whereHas('specs', function ($q) use ($specTypeId, $queryMin, $queryMax, $queryUnit, $queryProfile) {
                $q->where('spec_type_id', $specTypeId);

                [$allowedProfiles, $numericColumn, $minColumn, $maxColumn] = match ($queryProfile) {
                    'range' => [['range', 'triple'], null, 'value_numeric_min', 'value_numeric_max'],
                    'max_only' => [['max_only', 'triple'], 'value_numeric_max', null, null],
                    'min_only' => [['min_only', 'triple'], 'value_numeric_min', null, null],
                    'triple' => [['triple'], 'value_numeric_typ', null, null],
                    default => [['typ', 'triple'], 'value_numeric_typ', null, null],
                };

                $q->whereIn('value_profile', $allowedProfiles);

                if ($queryMin === null && $queryMax === null && $queryUnit !== '') {
                    $q->where(function ($unitQuery) use ($queryUnit) {
                        $unitQuery->where('unit', 'ilike', '%'.$queryUnit.'%')
                            ->orWhere('normalized_unit', 'ilike', '%'.$queryUnit.'%');
                    });
                }

                if ($queryProfile === '' || $queryProfile === 'all') {
                    if ($queryMin === null && $queryMax === null) {
                        return;
                    }

                    $q->where(function ($profileQuery) use ($queryMin, $queryMax) {
                        $profileQuery
                            ->where(function ($sub) use ($queryMin, $queryMax) {
                                $sub->whereIn('value_profile', ['typ', 'triple'])
                                    ->whereNotNull('value_numeric_typ');
                                if ($queryMin !== null) {
                                    $sub->whereRaw('value_numeric_typ >= ?', [$queryMin]);
                                }
                                if ($queryMax !== null) {
                                    $sub->whereRaw('value_numeric_typ <= ?', [$queryMax]);
                                }
                            })
                            ->orWhere(function ($sub) use ($queryMin, $queryMax) {
                                $sub->whereIn('value_profile', ['max_only', 'triple'])
                                    ->whereNotNull('value_numeric_max');
                                if ($queryMin !== null) {
                                    $sub->whereRaw('value_numeric_max >= ?', [$queryMin]);
                                }
                                if ($queryMax !== null) {
                                    $sub->whereRaw('value_numeric_max <= ?', [$queryMax]);
                                }
                            })
                            ->orWhere(function ($sub) use ($queryMin, $queryMax) {
                                $sub->whereIn('value_profile', ['min_only', 'triple'])
                                    ->whereNotNull('value_numeric_min');
                                if ($queryMin !== null) {
                                    $sub->whereRaw('value_numeric_min >= ?', [$queryMin]);
                                }
                                if ($queryMax !== null) {
                                    $sub->whereRaw('value_numeric_min <= ?', [$queryMax]);
                                }
                            })
                            ->orWhere(function ($sub) use ($queryMin, $queryMax) {
                                $sub->whereIn('value_profile', ['range', 'triple']);
                                if ($queryMin !== null) {
                                    $sub->whereRaw('value_numeric_max >= ?', [$queryMin]);
                                }
                                if ($queryMax !== null) {
                                    $sub->whereRaw('value_numeric_min <= ?', [$queryMax]);
                                }
                            });
                    });

                    return;
                }

                if ($queryProfile === 'range') {
                    if ($queryMin !== null) {
                        $q->whereRaw("{$maxColumn} >= ?", [$queryMin]);
                    }
                    if ($queryMax !== null) {
                        $q->whereRaw("{$minColumn} <= ?", [$queryMax]);
                    }

                    return;
                }

                if ($queryMin !== null) {
                    $q->whereRaw("{$numericColumn} >= ?", [$queryMin]);
                }
                if ($queryMax !== null) {
                    $q->whereRaw("{$numericColumn} <= ?", [$queryMax]);
                }
            });
        } elseif ($unit = $request->input('spec_unit')) {
            $query->whereHas('specs', fn ($q) => $q->where('unit', 'ilike', '%'.$unit.'%'));
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
        $this->applyComponentOrdering($query, (string) $request->input('sort', 'catalog'));
        $result = $query->paginate($perPage);
        $result->getCollection()->transform( fn (Component $component) => $this->decorateComponent($component));

        return ApiResponse::success($result);
    }

    /**
     * 目的: 部品の検証済み入力から新規作成する。
     * 機能: HTTP入力を検証し、Eloquent操作またはサービス処理を行い、JSONレスポンスへ包む。
     * 入力: $request。
     * 出力: HTTP JSONレスポンス、ファイルレスポンス、またはnoContentレスポンス。
     * 動作条件: 認証済みユーザー、権限、バリデーション済み入力を前提にする。
     * 副作用: DB、ファイルストレージ、外部サービス、HTTPレスポンスのいずれかを操作する場合がある。
     */
    public function store(StoreComponentRequest $request)
    {
        try {
            return DB::transaction(function () use ($request) {
                $this->assertPackageSelection($request->input('package_group_id'), $request->input('package_id'));

                $data = $request->safe()->except(['image', 'datasheet', 'datasheets', 'duplicate_from_component_id', 'category_ids', 'package_group_id', 'package_id', 'specs', 'suppliers', 'attributes', 'altium']);
                $data['created_by'] = auth()->id();
                $data['updated_by'] = auth()->id();
                $data['package_id'] = $request->input('package_id') ?: null;

                if ($request->hasFile('image')) {
                    $data['image_path'] = FileStorage::storeComponentImageNamed($request->file('image'), [
                        $request->input('part_number'),
                        $request->input('common_name'),
                        $this->firstCategoryName((array) $request->input('category_ids', [])),
                    ]);
                }
                $component = Component::create($data);
                if ($request->filled('duplicate_from_component_id') && ! $request->hasFile('image')) {
                    $source = Component::with('datasheets')->find($request->integer('duplicate_from_component_id'));
                    if ($source) {
                        $component->image_path = $source->image_path;
                        $component->save();
                        foreach ($source->datasheets as $index => $sheet) {
                            $component->datasheets()->create([
                                'file_path' => $sheet->file_path,
                                'original_name' => $sheet->original_name,
                                'sort_order' => $index,
                                'note' => $sheet->note,
                            ]);
                        }
                        $component->datasheet_path = $source->datasheets->sortBy('sort_order')->first()?->file_path;
                        $component->save();
                    }
                }
                $this->syncDatasheets($component, $request);
                $this->syncRelations($component, $request);

                return ApiResponse::created($this->decorateComponent(
                    $component->load(['categories', 'package.packageGroup', 'packages.packageGroup', 'componentSeries', 'componentSeriesValue', 'specs.specType', 'componentSuppliers.supplier', 'componentSuppliers.priceBreaks', 'primaryLocation', 'datasheets'])
                ));
            });
        } catch (ValidationException $e) {
            return ApiResponse::error($e->getMessage(), $e->errors(), 422);
        } catch (RuntimeException $e) {
            return ApiResponse::error($e->getMessage(), [], 500);
        }
    }

    /**
     * 目的: 部品の詳細を返す。
     * 機能: HTTP入力を検証し、Eloquent操作またはサービス処理を行い、JSONレスポンスへ包む。
     * 入力: $component。
     * 出力: HTTP JSONレスポンス、ファイルレスポンス、またはnoContentレスポンス。
     * 動作条件: 認証済みユーザー、権限、バリデーション済み入力を前提にする。
     * 副作用: DB、ファイルストレージ、外部サービス、HTTPレスポンスのいずれかを操作する場合がある。
     */
    public function show(Component $component)
    {
        $component->loadCount('transactions');
        $component->load([
            'categories', 'package.packageGroup', 'packages.packageGroup',
            'componentSeries',
            'componentSeriesValue',
            'specs.specType',
            'customAttributes',
            'componentSuppliers.supplier', 'componentSuppliers.priceBreaks',
            'inventoryBlocks.location',
            'primaryLocation',
            'datasheets',
            'transactions' => fn ($q) => $q->latest()->limit(20),
            'projects',
            'altiumLink',
        ]);

        return ApiResponse::success($this->decorateComponent($component));
    }

    /**
     * 目的: 部品の検証済み入力で更新する。
     * 機能: HTTP入力を検証し、Eloquent操作またはサービス処理を行い、JSONレスポンスへ包む。
     * 入力: $request, $component。
     * 出力: HTTP JSONレスポンス、ファイルレスポンス、またはnoContentレスポンス。
     * 動作条件: 認証済みユーザー、権限、バリデーション済み入力を前提にする。
     * 副作用: DB、ファイルストレージ、外部サービス、HTTPレスポンスのいずれかを操作する場合がある。
     */
    public function update(StoreComponentRequest $request, Component $component)
    {
        try {
            return DB::transaction(function () use ($request, $component) {
                $this->assertPackageSelection($request->input('package_group_id'), $request->input('package_id'));

                $data = $request->safe()->except(['image', 'datasheet', 'datasheets', 'duplicate_from_component_id', 'category_ids', 'package_group_id', 'package_id', 'specs', 'suppliers', 'attributes', 'altium']);
                $data['updated_by'] = auth()->id();
                $data['package_id'] = $request->input('package_id') ?: null;

                if ($request->hasFile('image')) {
                    FileStorage::delete($component->image_path);
                    $data['image_path'] = FileStorage::storeComponentImageNamed($request->file('image'), [
                        $request->input('part_number', $component->part_number),
                        $request->input('common_name', $component->common_name),
                        $this->firstCategoryName((array) $request->input('category_ids', $component->categories()->pluck('spec_groups.id')->all())),
                    ]);
                }
                $component->update($data);
                $this->syncDatasheets($component, $request);
                $this->syncRelations($component, $request);

                return ApiResponse::success($this->decorateComponent(
                    $component->load(['categories', 'package.packageGroup', 'packages.packageGroup', 'componentSeries', 'componentSeriesValue', 'specs.specType', 'componentSuppliers.supplier', 'primaryLocation', 'datasheets', 'customAttributes'])
                ));
            });
        } catch (ValidationException $e) {
            return ApiResponse::error($e->getMessage(), $e->errors(), 422);
        } catch (RuntimeException $e) {
            return ApiResponse::error($e->getMessage(), [], 500);
        }
    }

    /**
     * 目的: 部品のupdatesectionを処理する。
     * 機能: HTTP入力を検証し、Eloquent操作またはサービス処理を行い、JSONレスポンスへ包む。
     * 入力: $request, $component, $section。
     * 出力: HTTP JSONレスポンス、ファイルレスポンス、またはnoContentレスポンス。
     * 動作条件: 認証済みユーザー、権限、バリデーション済み入力を前提にする。
     * 副作用: DB、ファイルストレージ、外部サービス、HTTPレスポンスのいずれかを操作する場合がある。
     */
    public function updateSection(UpdateComponentSectionRequest $request, Component $component, string $section)
    {
        return DB::transaction(function () use ($request, $component, $section) {
            $component->updated_by = auth()->id();

            switch ($section) {
                case 'basic':
                    $this->assertPackageSelection($request->input('package_group_id'), $request->input('package_id'));

                    $data = $request->safe()->except(['image', 'datasheet', 'datasheets', 'category_ids', 'package_group_id', 'package_id']);
                    if ($request->has('package_id')) {
                        $data['package_id'] = $request->input('package_id') ?: null;
                    }
                    if ($request->hasFile('image')) {
                        FileStorage::delete($component->image_path);
                        $data['image_path'] = FileStorage::storeComponentImageNamed($request->file('image'), [
                            $request->input('part_number', $component->part_number),
                            $request->input('common_name', $component->common_name),
                            $this->firstCategoryName((array) $request->input('category_ids', $component->categories()->pluck('spec_groups.id')->all())),
                        ]);
                    }
                    $component->update($data);
                    $this->syncDatasheets($component, $request);
                    if ($request->has('category_ids')) {
                        $component->categories()->sync($request->category_ids ?? []);
                    }
                    break;

                case 'specs':
                    // 送信された specs 配列で全置換
                    $this->syncSpecs($component, (array) $request->input('specs', []));
                    $component->save();
                    break;

                case 'attributes':
                    // 送信された attributes 配列で全置換
                    $component->customAttributes()->delete();
                    foreach ($request->input('attributes', []) as $attr) {
                        $key = trim((string) ($attr['key'] ?? ''));
                        $value = trim((string) ($attr['value'] ?? ''));
                        if ($key === '' || $value === '') {
                            continue;
                        }
                        $component->customAttributes()->create([
                            'key' => $key,
                            'value' => $value,
                        ]);
                    }
                    $component->save();
                    break;

                case 'suppliers':
                    // 送信された suppliers で全置換
                    $component->componentSuppliers()->each( fn ($cs) => $cs->priceBreaks()->delete());
                    $component->componentSuppliers()->delete();
                    $this->syncSuppliers($component, $request->suppliers ?? []);
                    $component->save();
                    break;
            }

            return ApiResponse::success($this->decorateComponent(
                $component->load(['categories', 'package.packageGroup', 'packages.packageGroup', 'componentSeries', 'componentSeriesValue', 'specs.specType', 'componentSuppliers.supplier', 'componentSuppliers.priceBreaks', 'primaryLocation', 'datasheets', 'customAttributes'])
            ));
        });
    }

    /**
     * 目的: 部品の削除またはアーカイブする。
     * 機能: HTTP入力を検証し、Eloquent操作またはサービス処理を行い、JSONレスポンスへ包む。
     * 入力: $component。
     * 出力: HTTP JSONレスポンス、ファイルレスポンス、またはnoContentレスポンス。
     * 動作条件: 認証済みユーザー、権限、バリデーション済み入力を前提にする。
     * 副作用: DB、ファイルストレージ、外部サービス、HTTPレスポンスのいずれかを操作する場合がある。
     */
    public function destroy(Component $component)
    {
        $component->delete();

        return ApiResponse::noContent();
    }

    /**
     * 目的: 部品の適用部品orderingを処理する。
     * 機能: HTTP入力を検証し、Eloquent操作またはサービス処理を行い、JSONレスポンスへ包む。
     * 入力: $query, $sort。
     * 出力: HTTP JSONレスポンス、ファイルレスポンス、またはnoContentレスポンス。
     * 動作条件: 認証済みユーザー、権限、バリデーション済み入力を前提にする。
     * 副作用: DB、ファイルストレージ、外部サービス、HTTPレスポンスのいずれかを操作する場合がある。
     */
    private function applyComponentOrdering(Builder $query, string $sort): void
    {
        switch ($sort) {
            case 'updated_at':
                $query
                    ->orderByDesc('components.updated_at')
                    ->orderByRaw('COALESCE(components.part_number_sort_key, components.part_number)')
                    ->orderBy('components.part_number')
                    ->orderBy('components.id');
                break;

            case 'name':
                $query
                    ->orderBy('components.common_name')
                    ->orderByRaw('COALESCE(components.part_number_sort_key, components.part_number)')
                    ->orderBy('components.part_number')
                    ->orderBy('components.id');
                break;

            case 'part_number':
                $query
                    ->orderByRaw('COALESCE(components.part_number_sort_key, components.part_number)')
                    ->orderBy('components.part_number')
                    ->orderBy('components.manufacturer')
                    ->orderBy('components.id');
                break;

            default:
                $this->applyCatalogOrdering($query);
                break;
        }
    }
    /**
     * 目的: 部品の適用catalogorderingを処理する。
     * 機能: HTTP入力を検証し、Eloquent操作またはサービス処理を行い、JSONレスポンスへ包む。
     * 入力: $query。
     * 出力: HTTP JSONレスポンス、ファイルレスポンス、またはnoContentレスポンス。
     * 動作条件: 認証済みユーザー、権限、バリデーション済み入力を前提にする。
     * 副作用: DB、ファイルストレージ、外部サービス、HTTPレスポンスのいずれかを操作する場合がある。
     */
    private function applyCatalogOrdering(Builder $query): void
    {
        $categoryOrderSql = 'FROM component_spec_group '
            .'INNER JOIN spec_groups ON spec_groups.id = component_spec_group.spec_group_id '
            .'WHERE component_spec_group.component_id = components.id '
            .'AND spec_groups.deleted_at IS NULL '
            .'ORDER BY spec_groups.sort_order, spec_groups.name, spec_groups.id LIMIT 1';
        $packageGroupOrderSql = 'FROM packages '
            .'LEFT JOIN package_groups ON package_groups.id = packages.package_group_id '
            .'AND package_groups.deleted_at IS NULL '
            .'WHERE packages.id = components.package_id '
            .'AND packages.deleted_at IS NULL LIMIT 1';
        $packageOrderSql = 'FROM packages '
            .'WHERE packages.id = components.package_id '
            .'AND packages.deleted_at IS NULL LIMIT 1';

        $query
            ->orderByRaw("COALESCE((SELECT spec_groups.sort_order {$categoryOrderSql}), 2147483647)")
            ->orderByRaw("COALESCE((SELECT spec_groups.name {$categoryOrderSql}), '')")
            ->orderByRaw("COALESCE((SELECT package_groups.sort_order {$packageGroupOrderSql}), 2147483647)")
            ->orderByRaw("COALESCE((SELECT package_groups.name {$packageGroupOrderSql}), '')")
            ->orderByRaw("COALESCE((SELECT packages.sort_order {$packageOrderSql}), 2147483647)")
            ->orderByRaw("COALESCE((SELECT packages.name {$packageOrderSql}), '')")
            ->orderByRaw('COALESCE(components.part_number_sort_key, components.part_number)')
            ->orderBy('components.part_number')
            ->orderBy('components.manufacturer')
            ->orderBy('components.id');
    }
    /**
     * 目的: 部品の同期関連を処理する。
     * 機能: HTTP入力を検証し、Eloquent操作またはサービス処理を行い、JSONレスポンスへ包む。
     * 入力: $component, $request。
     * 出力: HTTP JSONレスポンス、ファイルレスポンス、またはnoContentレスポンス。
     * 動作条件: 認証済みユーザー、権限、バリデーション済み入力を前提にする。
     * 副作用: DB、ファイルストレージ、外部サービス、HTTPレスポンスのいずれかを操作する場合がある。
     */
    private function syncRelations(Component $component, $request): void
    {
        if ($request->has('category_ids')) {
            $component->categories()->sync($request->category_ids ?? []);
        }
        if ($request->has('specs')) {
            $this->syncSpecs($component, (array) $request->input('specs', []));
        }
        if ($request->has('suppliers')) {
            $component->componentSuppliers()->each( fn ($cs) => $cs->priceBreaks()->delete());
            $component->componentSuppliers()->delete();
            $this->syncSuppliers($component, $request->suppliers ?? []);
        }
        if ($request->has('attributes')) {
            $component->customAttributes()->delete();
            foreach ($request->input('attributes', []) as $attr) {
                $key = trim((string) ($attr['key'] ?? ''));
                $value = trim((string) ($attr['value'] ?? ''));
                if ($key === '' || $value === '') {
                    continue;
                }
                $component->customAttributes()->create([
                    'key' => $key,
                    'value' => $value,
                ]);
            }
        }
        if ($request->hasAny(['altium.sch_library_id', 'altium.sch_symbol', 'altium.pcb_library_id', 'altium.pcb_footprint'])) {
            $this->syncAltiumLink($component, (array) $request->input('altium', []));
        }
    }
    /**
     * 目的: 部品の同期仕入先を処理する。
     * 機能: HTTP入力を検証し、Eloquent操作またはサービス処理を行い、JSONレスポンスへ包む。
     * 入力: $component, $suppliers。
     * 出力: HTTP JSONレスポンス、ファイルレスポンス、またはnoContentレスポンス。
     * 動作条件: 認証済みユーザー、権限、バリデーション済み入力を前提にする。
     * 副作用: DB、ファイルストレージ、外部サービス、HTTPレスポンスのいずれかを操作する場合がある。
     */
    private function syncSuppliers(Component $component, array $suppliers): void
    {
        foreach ($suppliers as $s) {
            $cs = $component->componentSuppliers()->create([
                'supplier_id' => $s['supplier_id'],
                'supplier_part_number' => $s['supplier_part_number'] ?? null,
                'product_url' => $s['product_url'] ?? null,
                'purchase_unit' => $s['purchase_unit'] ?? null,
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
    /**
     * 目的: 部品の同期スペックを処理する。
     * 機能: HTTP入力を検証し、Eloquent操作またはサービス処理を行い、JSONレスポンスへ包む。
     * 入力: $component, $specs。
     * 出力: HTTP JSONレスポンス、ファイルレスポンス、またはnoContentレスポンス。
     * 動作条件: 認証済みユーザー、権限、バリデーション済み入力を前提にする。
     * 副作用: DB、ファイルストレージ、外部サービス、HTTPレスポンスのいずれかを操作する場合がある。
     */
    private function syncSpecs(Component $component, array $specs): void
    {
        $component->specs()->delete();

        if ($specs === []) {
            return;
        }

        $specTypeMap = SpecType::with('units')
            ->whereIn('id', collect($specs)->pluck('spec_type_id')->filter()->map( fn ($id) => (int) $id)->unique()->values())
            ->get()
            ->keyBy('id');

        /** @var SpecValueNormalizerService $normalizer */
        $normalizer = app(SpecValueNormalizerService::class);

        foreach ($specs as $spec) {
            $normalized = $normalizer->normalizeSpecPayload(
                $specTypeMap->get((int) ($spec['spec_type_id'] ?? 0)),
                (array) $spec
            );

            $component->specs()->create([
                'spec_type_id' => $spec['spec_type_id'],
                'value' => $normalized['value'] ?? null,
                'unit' => $normalized['unit'] ?? null,
                'value_profile' => $normalized['value_profile'] ?? 'typ',
                'value_mode' => $normalized['value_mode'] ?? 'single',
                'value_numeric' => $normalized['value_numeric'] ?? null,
                'value_numeric_typ' => $normalized['value_numeric_typ'] ?? null,
                'value_numeric_min' => $normalized['value_numeric_min'] ?? null,
                'value_numeric_max' => $normalized['value_numeric_max'] ?? null,
                'normalized_unit' => $normalized['normalized_unit'] ?? null,
            ]);
        }
    }
    /**
     * 目的: 部品の同期altiumlinkを処理する。
     * 機能: HTTP入力を検証し、Eloquent操作またはサービス処理を行い、JSONレスポンスへ包む。
     * 入力: $component, $altium。
     * 出力: HTTP JSONレスポンス、ファイルレスポンス、またはnoContentレスポンス。
     * 動作条件: 認証済みユーザー、権限、バリデーション済み入力を前提にする。
     * 副作用: DB、ファイルストレージ、外部サービス、HTTPレスポンスのいずれかを操作する場合がある。
     */
    private function syncAltiumLink(Component $component, array $altium): void
    {
        $payload = [
            'sch_library_id' => ! empty($altium['sch_library_id']) ? (int) $altium['sch_library_id'] : null,
            'sch_symbol' => $altium['sch_symbol'] ?? null,
            'pcb_library_id' => ! empty($altium['pcb_library_id']) ? (int) $altium['pcb_library_id'] : null,
            'pcb_footprint' => $altium['pcb_footprint'] ?? null,
        ];

        $hasAnyValue = collect($payload)->contains( fn ($value) => $value !== null && $value !== '');

        if (! $hasAnyValue) {
            if ($component->altiumLink) {
                $component->altiumLink()->delete();
            }

            return;
        }

        $component->altiumLink()->updateOrCreate(
            ['component_id' => $component->id],
            $payload
        );
    }
    /**
     * 目的: 部品の同期データシートを処理する。
     * 機能: HTTP入力を検証し、Eloquent操作またはサービス処理を行い、JSONレスポンスへ包む。
     * 入力: $component, $request。
     * 出力: HTTP JSONレスポンス、ファイルレスポンス、またはnoContentレスポンス。
     * 動作条件: 認証済みユーザー、権限、バリデーション済み入力を前提にする。
     * 副作用: DB、ファイルストレージ、外部サービス、HTTPレスポンスのいずれかを操作する場合がある。
     */
    private function syncDatasheets(Component $component, Request $request): void
    {
        $files = [];
        if ($request->hasFile('datasheets')) {
            $files = array_merge($files, $request->file('datasheets'));
        }
        if ($request->hasFile('datasheet')) {
            $files[] = $request->file('datasheet');
        }

        $tempTokens = array_values(array_filter(array_map( fn ($value) => trim((string) $value),
            (array) $request->input('temp_datasheet_tokens', [])
        )));

        if ($files === [] && $tempTokens === []) {
            $this->syncExistingDatasheetLabels($component, $request);

            return;
        }

        $labels = array_values((array) $request->input('datasheet_labels', []));
        $tempLabels = array_values((array) $request->input('temp_datasheet_labels', []));

        $createdSheets = [];
        foreach (array_values($files) as $index => $file) {
            $path = FileStorage::storeComponentDatasheetNamed($file, [
                $request->input('part_number', $component->part_number),
                $request->input('common_name', $component->common_name),
                $this->firstCategoryName((array) $request->input('category_ids', $component->categories()->pluck('spec_groups.id')->all())),
            ]);
            $createdSheets[] = [
                'file_path' => $path,
                'original_name' => $file->getClientOriginalName(),
                'note' => $this->normalizeDatasheetDisplayName($labels[$index] ?? null),
            ];
        }

        if ($tempTokens !== []) {
            $claimedTempSheets = app(TempDatasheetService::class)->claimMany(
                $tempTokens,
                $tempLabels,
                [
                    $request->input('part_number', $component->part_number),
                    $request->input('common_name', $component->common_name),
                    $this->firstCategoryName((array) $request->input('category_ids', $component->categories()->pluck('spec_groups.id')->all())),
                ]
            );

            foreach ($claimedTempSheets as $sheet) {
                $createdSheets[] = [
                    'file_path' => $sheet['file_path'],
                    'original_name' => $sheet['original_name'],
                    'note' => $this->normalizeDatasheetDisplayName($sheet['display_name'] ?? null),
                ];
            }
        }

        $oldDatasheetPaths = $component->datasheets->pluck('file_path')->all();
        $component->datasheets()->delete();

        foreach ($createdSheets as $index => $sheet) {
            $component->datasheets()->create([
                'file_path' => $sheet['file_path'],
                'original_name' => $sheet['original_name'],
                'sort_order' => $index,
                'note' => $sheet['note'],
            ]);
        }

        foreach ($oldDatasheetPaths as $oldPath) {
            FileStorage::delete($oldPath);
        }

        $component->datasheet_path = $component->datasheets()->orderBy('sort_order')->value('file_path');
        $component->save();
    }
    /**
     * 目的: 部品の同期既存データシートlabelsを処理する。
     * 機能: HTTP入力を検証し、Eloquent操作またはサービス処理を行い、JSONレスポンスへ包む。
     * 入力: $component, $request。
     * 出力: HTTP JSONレスポンス、ファイルレスポンス、またはnoContentレスポンス。
     * 動作条件: 認証済みユーザー、権限、バリデーション済み入力を前提にする。
     * 副作用: DB、ファイルストレージ、外部サービス、HTTPレスポンスのいずれかを操作する場合がある。
     */
    private function syncExistingDatasheetLabels(Component $component, Request $request): void
    {
        $entries = $request->input('existing_datasheets');
        if (! is_array($entries) || $entries === []) {
            return;
        }

        $sheets = $component->datasheets()->get()->keyBy('id');
        foreach ($entries as $entry) {
            if (! is_array($entry)) {
                continue;
            }

            $sheetId = (int) ($entry['id'] ?? 0);
            if ($sheetId <= 0 || ! $sheets->has($sheetId)) {
                continue;
            }

            $sheet = $sheets->get($sheetId);
            $displayName = $this->normalizeDatasheetDisplayName($entry['display_name'] ?? null);
            if ($sheet->note !== $displayName) {
                $sheet->note = $displayName;
                $sheet->save();
            }
        }
    }
    /**
     * 目的: 部品の正規化データシート表示名称を処理する。
     * 機能: HTTP入力を検証し、Eloquent操作またはサービス処理を行い、JSONレスポンスへ包む。
     * 入力: $value。
     * 出力: HTTP JSONレスポンス、ファイルレスポンス、またはnoContentレスポンス。
     * 動作条件: 認証済みユーザー、権限、バリデーション済み入力を前提にする。
     * 副作用: なし。
     */
    private function normalizeDatasheetDisplayName(mixed $value): ?string
    {
        $trimmed = trim((string) ($value ?? ''));

        return $trimmed === '' ? null : $trimmed;
    }
}
