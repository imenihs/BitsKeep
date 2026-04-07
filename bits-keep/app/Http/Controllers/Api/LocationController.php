<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreLocationRequest;
use App\Http\Responses\ApiResponse;
use App\Models\Location;
use Illuminate\Http\Request;

class LocationController extends Controller
{
    public function index()
    {
        // グループ→sort_order順で返す。在庫数は inventory_blocks から集計
        $locations = Location::withCount(['inventoryBlocks as stock_count' => fn($q) => $q->selectRaw('sum(quantity)')])
            ->orderBy('group')->orderBy('sort_order')->orderBy('code')
            ->get();
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
        if ($location->inventoryBlocks()->where('quantity', '>', 0)->exists()) {
            return ApiResponse::error('在庫のある棚は削除できません。', [], 409);
        }
        $location->delete();
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
