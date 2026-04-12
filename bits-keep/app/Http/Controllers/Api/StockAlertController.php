<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Responses\ApiResponse;
use App\Models\Component;

class StockAlertController extends Controller
{
    /**
     * GET /api/stock-alerts
     * 発注点を下回っている部品を逼迫度（在庫/発注点）昇順で返す
     */
    public function index()
    {
        $alerts = Component::with(['categories', 'packages', 'componentSuppliers.supplier'])
            ->needsReorder()
            ->get()
            ->map(function ($c) {
                // 逼迫度 = 在庫数 / 発注点（小さいほど深刻）
                $urgencyNew  = $c->threshold_new  > 0 ? $c->quantity_new  / $c->threshold_new  : 1;
                $urgencyUsed = $c->threshold_used > 0 ? $c->quantity_used / $c->threshold_used : 1;
                $c->urgency  = min($urgencyNew, $urgencyUsed);
                $suppliers = $c->componentSuppliers
                    ->filter(fn ($item) => $item->supplier)
                    ->sortBy([
                        fn ($item) => $item->unit_price === null ? 1 : 0,
                        'unit_price',
                    ])
                    ->values();

                $cheapest = $suppliers->first();

                $c->supplier_options = $suppliers->map(fn ($item) => [
                    'component_supplier_id' => $item->id,
                    'supplier_id' => $item->supplier_id,
                    'name' => $item->supplier->name,
                    'supplier_part_number' => $item->supplier_part_number,
                    'purchase_unit' => $item->purchase_unit,
                    'unit_price' => $item->unit_price,
                    'is_preferred' => (bool) $item->is_preferred,
                ])->values();

                // 最安値仕入先
                $c->cheapest_supplier = $cheapest?->supplier;
                $c->cheapest_price    = $cheapest?->unit_price;
                $c->package_name = $c->packages->sortBy('sort_order')->pluck('name')->first();
                return $c;
            })
            ->sortBy('urgency')
            ->values();

        return ApiResponse::success($alerts);
    }
}
