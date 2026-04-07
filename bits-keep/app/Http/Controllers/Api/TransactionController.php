<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\StockInRequest;
use App\Http\Requests\StockOutRequest;
use App\Http\Responses\ApiResponse;
use App\Models\Component;
use App\Models\InventoryBlock;
use App\Models\Transaction;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class TransactionController extends Controller
{
    /**
     * GET /api/components/{component}/transactions
     * 部品の入出庫履歴
     */
    public function index(Request $request, Component $component)
    {
        $transactions = $component->transactions()
            ->with(['user', 'inventoryBlock', 'project'])
            ->latest()
            ->paginate(20);
        return ApiResponse::success($transactions);
    }

    /**
     * POST /api/components/{component}/stock-in
     * 入庫: inventory_block を新規作成 or 既存ブロックに加算
     */
    public function stockIn(StockInRequest $request, Component $component)
    {
        return DB::transaction(function () use ($request, $component) {
            // 同条件ブロックが既存なら加算、なければ新規作成
            $block = InventoryBlock::firstOrCreate(
                [
                    'component_id' => $component->id,
                    'location_id'  => $request->location_id,
                    'stock_type'   => $request->stock_type,
                    'condition'    => $request->condition,
                    'lot_number'   => $request->lot_number,
                    'reel_code'    => $request->reel_code,
                ],
                ['quantity' => 0]
            );

            $before = $block->quantity;
            $block->increment('quantity', $request->quantity);

            // 在庫サマリを更新
            $field = 'quantity_' . $request->condition; // quantity_new / quantity_used
            $component->increment($field, $request->quantity);

            // 履歴記録
            Transaction::create([
                'component_id'       => $component->id,
                'inventory_block_id' => $block->id,
                'user_id'            => auth()->id(),
                'type'               => 'in',
                'quantity'           => $request->quantity,
                'quantity_before'    => $before,
                'quantity_after'     => $before + $request->quantity,
                'note'               => $request->note,
            ]);

            return ApiResponse::success([
                'inventory_block' => $block->fresh(),
                'quantity_new'    => $component->fresh()->quantity_new,
                'quantity_used'   => $component->fresh()->quantity_used,
            ], '入庫しました');
        });
    }

    /**
     * POST /api/components/{component}/stock-out
     * 出庫: 指定 inventory_block から減算
     */
    public function stockOut(StockOutRequest $request, Component $component)
    {
        return DB::transaction(function () use ($request, $component) {
            $block = InventoryBlock::findOrFail($request->inventory_block_id);

            // 在庫不足チェック
            if ($block->quantity < $request->quantity) {
                return ApiResponse::error(
                    "在庫が不足しています。現在の在庫: {$block->quantity}",
                    [],
                    422
                );
            }

            $before = $block->quantity;
            $block->decrement('quantity', $request->quantity);

            // 在庫サマリを更新
            $field = 'quantity_' . $block->condition;
            $component->decrement($field, $request->quantity);

            // 履歴記録
            Transaction::create([
                'component_id'       => $component->id,
                'inventory_block_id' => $block->id,
                'user_id'            => auth()->id(),
                'type'               => 'out',
                'quantity'           => -$request->quantity,
                'quantity_before'    => $before,
                'quantity_after'     => $before - $request->quantity,
                'project_id'         => $request->project_id,
                'note'               => $request->note,
            ]);

            return ApiResponse::success([
                'inventory_block' => $block->fresh(),
                'quantity_new'    => $component->fresh()->quantity_new,
                'quantity_used'   => $component->fresh()->quantity_used,
            ], '出庫しました');
        });
    }
}
