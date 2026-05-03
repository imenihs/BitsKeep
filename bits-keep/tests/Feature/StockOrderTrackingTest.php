<?php

namespace Tests\Feature;

use App\Models\Component;
use App\Models\StockOrder;
use App\Models\Supplier;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class StockOrderTrackingTest extends TestCase
{
    use RefreshDatabase;
    /**
     * 目的: 「editor can create stock order and pending endpoint returns it」の仕様を検証する。
     * 機能: 入力、APIレスポンス、永続化結果をアサーションで固定する。
     * 入力: なし。
     * 出力: 検証結果をPHPUnitアサーションへ渡す。
     * 動作条件: RefreshDatabaseまたはテスト用設定で実行されること。
     * 副作用: テストDB、HTTPセッション、モック状態を利用する。
     */
    public function test_editor_can_create_stock_order_and_pending_endpoint_returns_it(): void
    {
        $user = User::factory()->create([
            'role' => 'editor',
            'is_active' => true,
        ]);

        $component = Component::create([
            'part_number' => 'TRACK-001',
            'created_by' => $user->id,
            'updated_by' => $user->id,
        ]);

        $supplier = Supplier::create([
            'name' => 'テスト商社',
        ]);

        $createResponse = $this->actingAs($user)->post('/api/stock-orders', [
            'component_id' => $component->id,
            'supplier_id' => $supplier->id,
            'quantity' => 25,
            'status' => 'pending',
            'order_date' => '2026-04-22',
        ]);

        $createResponse->assertCreated()
            ->assertJsonPath('component_id', $component->id)
            ->assertJsonPath('supplier_id', $supplier->id)
            ->assertJsonPath('status', 'pending');

        $this->assertDatabaseHas('stock_orders', [
            'component_id' => $component->id,
            'supplier_id' => $supplier->id,
            'quantity' => 25,
            'status' => 'pending',
            'created_by' => $user->id,
        ]);

        $pendingResponse = $this->actingAs($user)->get("/api/stock-orders/component/{$component->id}/pending");
        $pendingResponse->assertOk()
            ->assertJsonCount(1)
            ->assertJsonPath('0.component_id', $component->id)
            ->assertJsonPath('0.status', 'pending');
    }
    /**
     * 目的: 「pending endpoint excludes received and cancelled orders」の仕様を検証する。
     * 機能: 入力、APIレスポンス、永続化結果をアサーションで固定する。
     * 入力: なし。
     * 出力: 検証結果をPHPUnitアサーションへ渡す。
     * 動作条件: RefreshDatabaseまたはテスト用設定で実行されること。
     * 副作用: テストDB、HTTPセッション、モック状態を利用する。
     */
    public function test_pending_endpoint_excludes_received_and_cancelled_orders(): void
    {
        $user = User::factory()->create([
            'role' => 'editor',
            'is_active' => true,
        ]);

        $component = Component::create([
            'part_number' => 'TRACK-002',
            'created_by' => $user->id,
            'updated_by' => $user->id,
        ]);

        $supplier = Supplier::create([
            'name' => '第二商社',
        ]);

        StockOrder::create([
            'component_id' => $component->id,
            'supplier_id' => $supplier->id,
            'quantity' => 10,
            'status' => 'pending',
            'created_by' => $user->id,
        ]);

        StockOrder::create([
            'component_id' => $component->id,
            'supplier_id' => $supplier->id,
            'quantity' => 11,
            'status' => 'received',
            'created_by' => $user->id,
        ]);

        StockOrder::create([
            'component_id' => $component->id,
            'supplier_id' => $supplier->id,
            'quantity' => 12,
            'status' => 'cancelled',
            'created_by' => $user->id,
        ]);

        $response = $this->actingAs($user)->get("/api/stock-orders/component/{$component->id}/pending");

        $response->assertOk()
            ->assertJsonCount(1)
            ->assertJsonPath('0.status', 'pending')
            ->assertJsonPath('0.quantity', 10);
    }
}
