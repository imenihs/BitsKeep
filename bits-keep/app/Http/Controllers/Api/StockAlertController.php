<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Responses\ApiResponse;
use App\Models\Component;

class StockAlertController extends Controller
{
    /**
     * 目的: 在庫警告の一覧を検索条件付きで返す。
     * 機能: HTTP入力を検証し、Eloquent操作またはサービス処理を行い、JSONレスポンスへ包む。
     * 入力: なし。
     * 出力: HTTP JSONレスポンス、ファイルレスポンス、またはnoContentレスポンス。
     * 動作条件: 認証済みユーザー、権限、バリデーション済み入力を前提にする。
     * 副作用: DB、ファイルストレージ、外部サービス、HTTPレスポンスのいずれかを操作する場合がある。
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
                    ->filter( fn ($item) => $item->supplier)
                    ->sortBy([ fn ($item) => $item->unit_price === null ? 1 : 0,
                        'unit_price',
                    ])
                    ->values();

                $cheapest = $suppliers->first();

                $c->supplier_options = $suppliers->map( fn ($item) => [
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
