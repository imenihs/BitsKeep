<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreLocationRequest;
use App\Http\Responses\ApiResponse;
use App\Models\Component;
use App\Models\Location;
use Illuminate\Http\Request;

class LocationController extends Controller
{
    public function index(Request $request)
    {
        // グループ→sort_order順で返す。在庫数は inventory_blocks から集計
        $query = Location::withCount([
            'inventoryBlocks as stock_count' => fn($q) => $q->selectRaw('coalesce(sum(quantity), 0)'),
            'inventoryBlocks as inventory_block_count',
            'children as child_count',
        ]);
        if ($request->boolean('include_archived')) {
            $query->withTrashed();
        }
        $locations = $query
            ->orderBy('group')->orderBy('sort_order')->orderBy('code')
            ->get()
            ->map(function (Location $location) {
                $primaryRefs = Component::where('primary_location_id', $location->id)->count();
                $location->primary_component_count = $primaryRefs;
                $location->can_force_delete = (bool) $location->deleted_at
                    && $location->inventory_block_count === 0
                    && $location->child_count === 0
                    && $primaryRefs === 0;
                $location->force_delete_reason = $location->can_force_delete
                    ? ''
                    : ($location->inventory_block_count > 0
                        ? "在庫ブロック{$location->inventory_block_count}件が残っています"
                        : ($location->child_count > 0
                            ? "子棚{$location->child_count}件が残っています"
                            : ($primaryRefs > 0 ? "代表保管棚として{$primaryRefs}件で使用中" : '先に廃止してください')));
                return $location;
            });
        return ApiResponse::success($locations);
    }

    public function store(StoreLocationRequest $request)
    {
        return ApiResponse::created(Location::create($request->validated()));
    }

    public function show(Location $location)
    {
        $location->load(['inventoryBlocks.component', 'children']);
        return ApiResponse::success($location);
    }

    public function update(StoreLocationRequest $request, Location $location)
    {
        $location->update($request->validated());
        return ApiResponse::success($location);
    }

    public function destroy(Location $location)
    {
        $location->delete();
        return ApiResponse::noContent();
    }

    public function restore(int $location)
    {
        $model = Location::withTrashed()->findOrFail($location);
        $model->restore();

        return ApiResponse::success($model);
    }

    public function forceDestroy(int $location)
    {
        $model = Location::withTrashed()
            ->withCount([
                'inventoryBlocks as inventory_block_count',
                'children as child_count',
            ])
            ->findOrFail($location);

        $primaryRefs = Component::where('primary_location_id', $model->id)->count();
        if (!$model->deleted_at) {
            return ApiResponse::error('完全削除の前に廃止してください', [], 422);
        }
        if ($model->inventory_block_count > 0 || $model->child_count > 0 || $primaryRefs > 0) {
            return ApiResponse::error('在庫・子棚・代表棚参照が残っているため完全削除できません', [], 422);
        }
        $model->forceDelete();

        return ApiResponse::noContent();
    }

    /**
     * POST /api/locations/inventory  — 棚卸し保存
     * [{ location_id, actual_qty }] を受けて差分をtransactionsに記録
     */
    public function saveInventory(Request $request)
    {
        $request->validate([
            'items'                => ['required', 'array'],
            'items.*.location_id'  => ['required', 'integer', 'exists:locations,id'],
            'items.*.actual_qty'   => ['required', 'integer', 'min:0'],
        ]);

        $updated = 0;
        foreach ($request->items as $item) {
            $blocks = \App\Models\InventoryBlock::where('location_id', $item['location_id'])->get();
            $currentTotal = $blocks->sum('quantity');
            $diff = $item['actual_qty'] - $currentTotal;

            if ($diff === 0) continue;

            // 差分をtransactionsに記録（adjust）
            // 最初のblockを対象に数量を補正（複数blockがある場合は最初のblock調整）
            $block = $blocks->first();
            if ($block) {
                $before = $block->quantity;
                $block->increment('quantity', $diff);
                $field = 'quantity_' . $block->condition;
                $block->component->increment($field, $diff);

                \App\Models\Transaction::create([
                    'component_id'       => $block->component_id,
                    'inventory_block_id' => $block->id,
                    'user_id'            => auth()->id(),
                    'type'               => 'adjust',
                    'quantity'           => $diff,
                    'quantity_before'    => $before,
                    'quantity_after'     => $before + $diff,
                    'note'               => '棚卸し調整',
                ]);
                $updated++;
            }
        }

        return ApiResponse::success(['updated' => $updated], "棚卸しを完了しました（{$updated}件更新）");
    }
}
