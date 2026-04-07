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
        $alerts = Component::with(['categories', 'componentSuppliers.supplier'])
            ->needsReorder()
            ->get()
            ->map(function ($c) {
                // 逼迫度 = 在庫数 / 発注点（小さいほど深刻）
                $urgencyNew  = $c->threshold_new  > 0 ? $c->quantity_new  / $c->threshold_new  : 1;
                $urgencyUsed = $c->threshold_used > 0 ? $c->quantity_used / $c->threshold_used : 1;
                $c->urgency  = min($urgencyNew, $urgencyUsed);
                // 最安値仕入先
                $c->cheapest_supplier = $c->componentSuppliers->sortBy('unit_price')->first()?->supplier;
                $c->cheapest_price    = $c->componentSuppliers->sortBy('unit_price')->first()?->unit_price;
                return $c;
            })
            ->sortBy('urgency')
            ->values();

        return ApiResponse::success($alerts);
    }
}
