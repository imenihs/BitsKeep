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
     * 目的: 入出庫の一覧を検索条件付きで返す。
     * 機能: HTTP入力を検証し、Eloquent操作またはサービス処理を行い、JSONレスポンスへ包む。
     * 入力: $request, $component。
     * 出力: HTTP JSONレスポンス、ファイルレスポンス、またはnoContentレスポンス。
     * 動作条件: 認証済みユーザー、権限、バリデーション済み入力を前提にする。
     * 副作用: DB、ファイルストレージ、外部サービス、HTTPレスポンスのいずれかを操作する場合がある。
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
     * 目的: 入出庫のstockinを処理する。
     * 機能: HTTP入力を検証し、Eloquent操作またはサービス処理を行い、JSONレスポンスへ包む。
     * 入力: $request, $component。
     * 出力: HTTP JSONレスポンス、ファイルレスポンス、またはnoContentレスポンス。
     * 動作条件: 認証済みユーザー、権限、バリデーション済み入力を前提にする。
     * 副作用: DB、ファイルストレージ、外部サービス、HTTPレスポンスのいずれかを操作する場合がある。
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
     * 目的: 入出庫のstockoutを処理する。
     * 機能: HTTP入力を検証し、Eloquent操作またはサービス処理を行い、JSONレスポンスへ包む。
     * 入力: $request, $component。
     * 出力: HTTP JSONレスポンス、ファイルレスポンス、またはnoContentレスポンス。
     * 動作条件: 認証済みユーザー、権限、バリデーション済み入力を前提にする。
     * 副作用: DB、ファイルストレージ、外部サービス、HTTPレスポンスのいずれかを操作する場合がある。
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
